<?php

/**
 * @wordpress-plugin
 * Plugin Name:       Bulk print consignment
 * Description:       Print consignment notes
 * Version:           1.0.3
 * Author:            bee5325
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Activation and deactivation hooks
 * Not needed for now
 */
register_activation_hook( __FILE__, 'activate_bp_consignment' );
register_deactivation_hook( __FILE__, 'deactivate_bp_consignment' );

/**
 * Helper funcitons
 */
function bp_consignment_submenu() {
	add_submenu_page( 'woocommerce', 'Print Consignments', 'Print Consignments', 'manage_woocommerce', 'bp-consignment', 'bp_consignment_page' ); 
}

function bo_management_submenu() {
	add_submenu_page( 'woocommerce', 'Backorders', 'Backorders', 'manage_woocommerce', 'bo-management', 'bo_management_page' ); 
}

function register_print_consignment_bulk_action($bulk_actions) {
  $bulk_actions['print_consignments'] = 'Print consignments';
  return $bulk_actions;
}

function print_consignment_bulk_action_handler( $redirect_to, $doaction, $post_ids ) {
  if ( $doaction !== 'print_consignments' ) {
    return $redirect_to;
  }
  foreach ( $post_ids as $post_id ) {
    // Perform action for each post.
  }
  $redirect_to = add_query_arg( 'count', count( $post_ids ), $redirect_to );
  return $redirect_to;
}

function bulk_action_print_consignments_admin_notice() {
  if ( ! empty( $_REQUEST['count'] ) ) {
    printf( '<div id="message" class="updated fade">' .
      intval( $_REQUEST['count'] ) . ' orders added to print list.' . '</div>' );
  }
}

function consignment_scripts($hook) {
	if ( 'woocommerce_page_bp-consignment' !== $hook ) {
		return;
	}

    $version = '0.83';
    wp_enqueue_style( 'bp_consignment', plugins_url('consignment.css', __FILE__), array(), $version );
	wp_enqueue_style( 'bp_consignment_print', plugins_url('consignmentPrint.css', __FILE__), array(), $version );

	wp_register_script( "bp_consignment", plugins_url('consignment.js', __FILE__), array('jquery'), $version );
	wp_localize_script( 'bp_consignment', 'bp', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ),
                                                       'invoiceurl' => plugins_url('bp_invoice_template.php', __FILE__),
                                                       'printcss' => plugins_url('consignmentPrint.css', __FILE__) . "?ver=" . $version,
                                                       'prefix' => get_option('bulk_print_prefix') ) );
	wp_enqueue_script( 'bp_consignment' );
}

function bp_consignment_save_settings() {
    update_option('bulk_print_prefix', $_POST['prefix']);
    update_option('allow_combine', $_POST['allowCombine'] === "true");
    echo json_encode(
        array(
            'prefix' => get_option('bulk_print_prefix'),
            'allowCombine' => get_option('allow_combine')
        )
    );
    die();
}

function bp_consignment_get_processings() {
	$statuses = isset($_POST['statuses']) ? $_POST['statuses'] : null;
	$order_ids = isset($_POST['order_ids']) ? $_POST['order_ids'] : null;
	$exclude = isset($_POST['exclude']) ? $_POST['exclude'] : null;
	$products = isset($_POST['products']) ? $_POST['products'] : null;
	$data = get_processing_orders($statuses, $order_ids, $exclude, $products, 1);
    array_push($data['rows'], get_empty_row("empty-0"));
	echo json_encode(array('next_page' => $data['next_page'],
					       'content' => template_consignment_page('print_list', $data))); 
	die();
}

function bp_consignment_more_processings() {
	$statuses = isset($_POST['statuses']) ? $_POST['statuses'] : null;
	$order_ids = isset($_POST['order_ids']) ? $_POST['order_ids'] : null;
	$exclude = isset($_POST['exclude']) ? $_POST['exclude'] : null;
	$products = isset($_POST['products']) ? $_POST['products'] : null;
	$paged = isset($_POST['paged']) ? (int)$_POST['paged'] : null;
	$data = get_processing_orders($statuses, $order_ids, $exclude, $products, $paged);
	echo json_encode(array('next_page' => $data['next_page'],
					       'content' => print_table_body($data['rows'])));
	die();
}

function bp_consignment_update_order_statuses() {
    // TODO: in case update status happened in between get more, value of "paged" changed. It causes some orders to be skipped
    // Should reply new paged value, by checking the last order given before
	$orders = isset($_POST['orders']) ? $_POST['orders'] : null;
	$shipping_date = isset($_POST['shipping_date']) ? $_POST['shipping_date'] : null;
	if (!is_null($orders)) {
		$tracking_action = new WC_Advanced_Shipment_Tracking_Actions();
		foreach ($orders as $order_id => $details) {
            if (substr($order_id, 0, 5) === "empty") {
                continue;
            }
			if (!empty($details['order_notes'])) {
				$order = wc_get_order($order_id);
				$order->add_order_note($details['order_notes'], false, true);
			}
			if (isset($details['items_reserved'])) {
				foreach ($details['items_reserved'] as $item_id => $item_count) {
					if (wc_get_order_item_meta($item_id, "_reserved")) {
						$prev_reserved = wc_get_order_item_meta($item_id, "_reserved");
						wc_update_order_item_meta($item_id, "_reserved", $item_count + $prev_reserved);
					} else {
						wc_update_order_item_meta($item_id, "_reserved", $item_count);
					}
				}
			}
			if ($details['next_status'] === 'wc-completed' && !empty($details['tracking_number'])) {
				$tracking_action->insert_tracking_item($order_id, array('tracking_provider' => $details['shipping_provider'],
																		'tracking_number' => $details['tracking_number'],
																		'date_shipped' => $shipping_date,
																		'status_shipped' => 1));
			} else if (!empty($details['tracking_number'])) {
				$order = new WC_Order($order_id);
				$tracking_action->insert_tracking_item($order_id, array('tracking_provider' => $details['shipping_provider'],
																		'tracking_number' => $details['tracking_number'],
																		'date_shipped' => $shipping_date,
																	    'status_shipped' => ''));
				$order->update_status($details['next_status']);
			} else {
				$order = new WC_Order($order_id);
				$order->update_status($details['next_status']);
			}
		}
		bp_log_orders_changed($orders, $shipping_date);
		echo json_encode($orders);
	}
	wp_die();
}

function bp_consignment_save_printlist() {
    try {
        $current_timestamp = time();
        $data = json_decode(stripslashes($_POST['data']), true);
        $data['saveTime'] = $current_timestamp;
        $session_name = $data['sessionName'];
        $last_save = $_POST['last_save'];

        // check if requested last save time is newer than file saved in server
        $save_file_path = plugin_dir_path( __FILE__ ) . '/saves/' . $session_name . '.json';

        // new save
        if (!file_exists($save_file_path)) {
            bp_save_session($data);
            echo $current_timestamp;
            wp_die();
        }

        $save_file = file_get_contents($save_file_path);
        $saved = json_decode($save_file, true);
        $server_last_save = isset($saved['saveTime']) ? $saved['saveTime'] : 0;

        if ($last_save >= $server_last_save) {
            bp_save_session($data);
            echo $current_timestamp;
            wp_die();
        } else {
            echo "session taken";
            wp_die();
        }
    } catch (Exception $err) {
        wp_die($err, 500);
    }
}

function bp_consignment_get_saved_session() {
    $session = $_GET['session'];
    $save_file = plugin_dir_path( __FILE__ ) . '/saves/' . $session . '.json';
	if (!file_exists($save_file)) {
        wp_die("Saved file not found", 400);
    }

    $saved = json_decode(file_get_contents($save_file), true);
    if (!isset($saved['saveTime'])) {
      $saved['saveTime'] = time();
    }
    echo json_encode($saved);
    wp_die();
}

function bp_record_printed_consignment() {
    $orders = isset($_POST['orders']) ? $_POST['orders'] : array();
    foreach ($orders as $order_id) {
        $order = wc_get_order($order_id);
        if ($order) {
            $order->add_order_note("Printed consignment note", false, false);
        }
    }
}

function bp_consignment_get_history() {
    if (!isset($_GET['month'])) {
        wp_die("Parameter month not given", 400);
    }

    $log_file = plugin_dir_path( __FILE__ ) . '/logs/' . $_GET['month'] . '.json';
    if (!file_exists($log_file)) {
        wp_die("Log file for " . $_GET['month'] . " not found!", 404);
    }

    $response = [];
    $history_str = file_get_contents($log_file);
    $history = json_decode($history_str, true);
    $response['wrapper'] = history_table_wrapper();
    $response['body'] = history_table_body($history);
    $response['nextMonth'] = history_next_month($_GET['month']);
    echo json_encode($response);
    wp_die();
}

function bp_reserve_item() {
	?>
	<button type='button' class='button' onClick='reserveItems();'>Reserve</button>
	<?php
}

/**
 * Include files
 **/
function bp_include_admin_files() {
	include_once plugin_dir_path( __FILE__ ) . 'bp_templates.php';
    include_once plugin_dir_path( __FILE__ ) . 'backorders_management.php';

    if (get_option('allow_combine')) {
        include_once plugin_dir_path( __FILE__ ) . 'combine_orders.php';
    }
}

function bp_include_frontend_files() {
    /* include_once plugin_dir_path( __FILE__ ) . 'candle_combo/candle_combo.php'; */

    if (get_option('allow_combine')) {
        include_once plugin_dir_path( __FILE__ ) . 'combine_orders_frontend.php';
    }
}

/**
 * Actions and filters
 **/
add_action( 'admin_init', 'bp_include_admin_files' );

add_action( 'init', 'bp_include_frontend_files' );

add_action( 'admin_menu', 'bp_consignment_submenu', 99 );
add_action( 'admin_menu', 'bo_management_submenu', 99 );

add_filter( 'bulk_actions-edit-shop_order', 'register_print_consignment_bulk_action' );

add_filter( 'handle_bulk_actions-edit-shop_order', 'print_consignment_bulk_action_handler', 10, 3 );

add_action( 'admin_notices', 'bulk_action_print_consignments_admin_notice' );

add_action( 'admin_enqueue_scripts', 'consignment_scripts' );

add_action( 'wp_ajax_bp_consignment_save_settings', 'bp_consignment_save_settings' );
add_action( 'wp_ajax_bp_consignment_get_processings', 'bp_consignment_get_processings' );
add_action( 'wp_ajax_bp_consignment_more_processings', 'bp_consignment_more_processings' );
add_action( 'wp_ajax_bp_consignment_update_order_statuses', 'bp_consignment_update_order_statuses' );
add_action( 'wp_ajax_bp_consignment_save_printlist', 'bp_consignment_save_printlist' );
add_action( 'wp_ajax_bp_consignment_get_saved_session', 'bp_consignment_get_saved_session' );
add_action( 'wp_ajax_bp_record_printed_consignment', 'bp_record_printed_consignment' );
add_action( 'wp_ajax_bp_consignment_get_history', 'bp_consignment_get_history' );

add_action( 'woocommerce_order_item_add_action_buttons', 'bp_reserve_item', 99 );
