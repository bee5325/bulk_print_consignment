<?php

/**
 * Templates
 */
function show_combine_orders($order) {
    $combined_meta = restore_combine_meta($order->get_id(), false);
    $combined_orders = array_diff($combined_meta['orders'], array($order->get_id()));
    $other_combines = $combined_meta['others'];  // TODO: display and edit other combines
    $combine_next = $combined_meta['next'];
    ?>

    <div class='form-field combine-order-field'>
        <h3>
            Combine with orders:
            <a href="#" class="edit_combine"><?php esc_html_e( 'Edit', 'woocommerce' ); ?></a>
            <a href="#" class="done_combine" style="display:none"><?php esc_html_e( 'Done', 'woocommerce' ); ?></a>
        </h3>

        <div class='edit-combined-list' style="display:none">
            <input id='combined-order-id' placeholder='order ID to be combined'>
            <div class='combined-order-autocomplete' style="display:none"></div>
            <div class='other-combines-edit'>
                <input id='other-combines-input' placeholder='Other combine'>
                <a href=# class='other-combines-add'>Add</a>
            </div>
            <a href="#" class="detach-combine"><?php esc_html_e( 'Detach order from combined', 'woocommerce' ); ?></a>
            <div id='combine-feedback'></div>
        </div>

        <div class='combined-list'>
            <?php if ($combined_orders || $other_combines) {
                if ($combined_orders) {
                    echo '<ul class="combined-orders">';
                    foreach ($combined_orders as $combined_order) {
                        echo '<li class="combined-order combined-id-' . $combined_order .'">';
                        echo '<a href=' . get_edit_post_link($combined_order) . ' target="_blank">' . $combined_order . '</a>';
                        echo '<span class="remove-combined dashicons dashicons-no-alt" style="display:none"></span>';
                        echo '</li>';
                    }
                    echo '</ul>';
                }
                if ($other_combines) {
                    echo '<ul class="other-combines">';
                    foreach ($other_combines as $other) {
                        echo '<li class="other-combined">';
                        echo '<p>' . $other . '</p>';
                        echo '<span class="remove-combined dashicons dashicons-no-alt" style="display:none"></span>';
                        echo '</li>';
                    }
                    echo '</ul>';
                }
            } else {
                echo '<p>No combined order</p>';
            } ?>
        </div>

        <p class="combine-next" <?php echo $combine_next ? "" : "style='display:none'"; ?>>
            <input type="checkbox" id="combine-next-checkbox" <?php echo $combine_next ? "checked" : ""; ?> style="display:none">
            Combine next order
        </p>

    </div>
    <?php
}

/**
 * Styles and scripts
 */
function combine_orders_scripts() {
    global $post;
	if ( !is_null($post) && 'shop_order' !== $post->post_type ) {
		return;
	}
    wp_enqueue_style( 'combine_orders', plugins_url('combineOrders.css', __FILE__) );
	wp_register_script( "combine_orders", plugins_url('combineOrders.js', __FILE__), array('jquery') );
	wp_localize_script( 'combine_orders', 'co', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) ) );
	wp_enqueue_script( 'combine_orders' );
}

/**
 * Ajax functions
 */
function combine_orders_update_ajax() {
    $current_id = isset($_POST['current_id']) ? $_POST['current_id'] : null;
    $combined_ids = isset($_POST['combined_ids']) ? $_POST['combined_ids'] : array();
    foreach ($combined_ids as $order) {
        if (!combine_valid($order)) {
            wp_die($order . " is not a valid order ID! Other orders should always start with either 'fb - ' or 'others - '", 404);
        }
    }
    update_combined_orders($current_id, $combined_ids);
    $combined_meta = restore_combine_meta($current_id, false);
    $order_links = array_map(function($order) {
            return '<a href=' . get_edit_post_link($order) . ' target=_blank>' . $order . '</a><span class="remove-combined dashicons dashicons-no-alt"></span>';
        }, $combined_meta['orders']);
    $others_li = array_map(function($order) {
            return '<p>' . $order . '</p><span class="remove-combined dashicons dashicons-no-alt"></span>';
        }, $combined_meta['others']);
    echo json_encode(array( 'orders' => $order_links,
                            'others' => $others_li,
                            'next'   => $combined_meta['next']));
    wp_die();
}

function combine_orders_check_orderid_ajax() {
    $order_id = isset($_GET['order_id']) ? $_GET['order_id'] : null;
    if (combine_valid($order_id)) {
        $combined = restore_combine_meta($order_id, true);
        if ($combined['next']) {
            array_push($combined['orders'], 'next');
        }
        echo json_encode(array('found' => true,
            'orders' => $combined['orders']));
    } else {
        echo json_encode(array('found' => false,
            'orders' => array()));
    }
    wp_die();
}

/**
 * Hooks
 */
function remove_combined_when_order_cancelled($order_id) {
    if (has_combined_order($order_id)) {
        update_combined_orders($order_id, [$order_id]);
    }
}

require_once plugin_dir_path( __FILE__ ) . 'combine_orders_common.php';

add_action( 'woocommerce_admin_order_data_after_order_details', 'show_combine_orders', 10 );

add_action( 'admin_enqueue_scripts', 'combine_orders_scripts' );

add_action( 'wp_ajax_combine_orders_update', 'combine_orders_update_ajax' );

add_action( 'wp_ajax_combine_orders_check_orderid', 'combine_orders_check_orderid_ajax' );

add_action( 'woocommerce_order_status_cancelled', 'remove_combined_when_order_cancelled', 10 );
