<?php

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

function get_order_details($order) {
	$countries = new WC_Countries();

	// Mark special cases
    if ($order->get_status() === "completed") {
        $next_status = "wc-completed";
    } else if (in_array('combine', $order->get_coupon_codes())) {
		$next_status = "wc-combine";
	} else if (strpos($order->get_shipping_method(), "Self pick-up") !== false) {
		$next_status = "wc-self-pickup";
	} else if ($order->get_shipping_method() === "" || $order->get_status() === "cancelled") {
		$next_status = "unknown";
	} else {
		$next_status = "wc-completed";
	}

	// Name
	if ($order->get_shipping_first_name()) {
		$receipent = $order->get_shipping_first_name() . " " . $order->get_shipping_last_name();
	} else {
		$receipent = $order->get_billing_first_name() . " " . $order->get_billing_last_name();
	}

	// Address
	if ($order->get_shipping_country() === "" || $order->get_shipping_state() === "") {
		$state = $order->get_shipping_state();
		$next_status = "unknown";
	} else {
		$state = $countries->get_states()[$order->get_shipping_country()][$order->get_shipping_state()];
	}
	$address = array($order->get_shipping_address_1(),
					 $order->get_shipping_address_2(),
					 $order->get_shipping_city(),
					 $order->get_shipping_postcode(),
					 $state
					);
	$address = array_diff($address, [""]); //remove empty lines

	// Customer note + order notes
	$order_notes_raw = wc_get_order_notes(array('order_id' => $order->get_id()));
	$notes = array();
	foreach ($order_notes_raw as $order_note) {
		if (substr($order_note->content, 0, 14) !== "Adjusted stock"
			&& substr($order_note->content, 0, 13) !== "Stock levels "
			&& substr($order_note->content, 0, 8) !== "Deleted "
			&& substr($order_note->content, 0, 18) !== "Added line items: ") {
            $note = str_replace("\n", "<br>", $order_note->content);
            if ($order_note->customer_note) {
                $note = "<p class='customer-notes'>" . $note . "</p>";
            } else if ($note === "Printed consignment note") {
                $note = "<p class='printed-note'>" . $note . " on " . $order_note->date_created->date( 'Y-m-d' ) . "</p>";
            }
			array_push($notes, $note);
		}
	}
	array_unshift($notes, "<p class='customer-notes'>Customer: " . str_replace("\n", "<br>", $order->get_customer_note()) . "</p>");
    //--- To be deprecated
	if ($next_status === "wc-combine") {
		array_unshift($notes, "<strong>COMBINE COUPON</strong>");
    // To be deprecated ---
	} else if ($next_status === "wc-self-pickup") {
		array_unshift($notes, "<strong>SELF PICKUP</strong>");
	}
	$notes = array_diff($notes, [""]); //remove empty lines

	// Order items
	$items_raw = $order->get_items();
	$items = array();
    $items_count = 0;
	foreach ($items_raw as $item) {
		$reserved = wc_get_order_item_meta($item->get_id(), '_reserved') ? wc_get_order_item_meta($item->get_id(), '_reserved') : 0;
		$backordered = wc_get_order_item_meta($item->get_id(), 'Backordered') ? wc_get_order_item_meta($item->get_id(), 'Backordered') : 0;
		$item_status = '';
		if ($reserved !== 0) {
			$item_status .= $reserved . '&#x2714;'; // Tick symbol -> status done
		}
		if ($backordered !== 0) {
			$item_status .= $backordered . 'B';
            if ($order->get_status() !== "completed" && $order->get_status() !== "cancelled") {
                $next_status = "wc-on-backorder";
            }
		}
		if ($item_status === '') {
			$item_status = '-';
		}
        $hidden_order_itemmeta = apply_filters(
            'woocommerce_hidden_order_itemmeta',
            array(
                '_qty', '_tax_class', '_product_id', '_variation_id', '_line_subtotal', '_line_subtotal_tax', '_line_total', '_line_tax', 'method_id', 'cost', '_reduced_stock', 'Backordered', '_reserved',
            )
        );
        $item_metas = [];
        foreach ($item->get_formatted_meta_data("") as $meta) {
            if (in_array($meta->key, $hidden_order_itemmeta)) {
                continue;
            }
            $item_metas[$meta->key] = str_replace("\n", "<br>", $meta->value);
        }
		$to_reserve = ($item->get_quantity() - $reserved - $backordered) > 0 ? $item->get_quantity() - $reserved - $backordered : 0;
        $reserve_done = $reserved >= $item->get_quantity();
		array_push($items, array(
			'id'           => $item->get_id(),
			'name'         => $item->get_name(),
			'quantity'     => $item->get_quantity(),
			'status'       => $item_status,
            'metas'        => $item_metas,
			'to_reserve'   => $to_reserve, // reserved all readystock
            'reserve_done' => $reserve_done,
			'max_reserve'  => $item->get_quantity() - $reserved
		));
        $items_count += $item->get_quantity();
	}
    $fees = array();
    foreach ($order->get_fees() as $fee) {
        if ($fee->get_name() === "Via wallet") {
            continue;
        }
        array_push($fees, array('name' => $fee->get_name()));
        $items_count++;
    }

    if (function_exists("restore_combine_meta")) {
        $combined_meta = restore_combine_meta($order->get_id(), false);
    } else {
        $combined_meta = array("orders" => [], "others" => [], "next" => false);
    }
    return array('order_type'       => 'order_id',
                 'current_status'   => $order->get_status(),
				 'next_status' 	    => $next_status,
				 'id'               => $order->get_id(),
                 'date-created'     => $order->get_date_created()->date('y-m-d H:i'),
				 'receiver-name'    => $receipent,
				 'receiver-phone'   => get_post_meta($order->get_id(), '_shipping_phone', true) ? get_post_meta($order->get_id(), '_shipping_phone', true) : $order->get_billing_phone(),
				 'receiver-address' => implode(PHP_EOL, $address),
                 'notes'            => $notes,
                 'items-count'      => $items_count,
                 'items-total'      => $order->get_total(),
                 'items'            => $items,
                 'fees'             => $fees,
                 'combines'         => array_merge($combined_meta['orders'], $combined_meta['others']),
                 'combine-next'     => $combined_meta['next']);
}

function history_table_wrapper() {
    ob_start(); ?>
    <h2>History</h2>
    <input id='search-history' class="page-top" onKeyUp="searchHistory()" placeHolder="Search">
    <table class="history-table">
        <colgroup>
            <col style="width: 5%; text-align: center">
            <col style="width: 5%; text-align: center">
            <col style="width: 5%">
            <col style="width: 5%">
            <col style="width: 5%">
            <col style="width: 5%">
            <col style="width: 5%">
            <col style="width: 20%">
            <col style="width: 20%">
        </colgroup>
        <thead>
            <tr>
                <th>Timestamp</th>
                <th>Shipping date</th>
                <th>Order ID</th>
                <th>Courier</th>
                <th>Tracking number</th>
                <th>Previous status</th>
                <th>Next status</th>
                <th>Items reserved</th>
                <th>Notes</th>
            </tr>
        </thead>
    </table>
    <button class='button page-bottom' id='get-history-button' onClick='getHistory(false);'>Get earlier</button>
    <span class='status-update'></span>
    <div class='navigation'>
        <button class='button button-to-top' onClick='toTop();'><span class="dashicons dashicons-arrow-up-alt2"></span></button>
        <button class='button button-to-bottom' onClick='toBottom();'><span class="dashicons dashicons-arrow-down-alt2"></span></button>
    </div>
    <?php return ob_get_clean();
}
    

function history_table_body($history) {
    ob_start();
    krsort($history);
    ?>
    <?php foreach ($history as $timestamp => $details) { ?>
        <tbody>
        <tr class="timestamp-row">
            <?php $order_count = sizeof($details['orders']); ?>
            <td rowspan=<?php echo $order_count+1; ?>><?php echo $timestamp; ?></td>
            <td rowspan=<?php echo $order_count+1; ?>><?php echo isset($details['shipping_date']) ? $details['shipping_date'] : ""; ?></td>
        </tr>
        <?php foreach ($details['orders'] as $orderid => $changes) { ?>
            <tr>
                <td><?php echo $orderid; ?></td>
                <td><?php echo isset($changes['shipping_provider']) ? $changes['shipping_provider'] : ""; ?></td>
                <td><?php echo isset($changes['tracking_number']) ? $changes['tracking_number'] : ""; ?></td>
                <td><?php echo isset($changes['current_status']) ? $changes['current_status'] : ""; ?></td>
                <td><?php echo isset($changes['next_status']) ? $changes['next_status'] : ""; ?></td>
                <?php if (isset($changes['items_reserved'])) { ?>
                    <td><table class='items-reserved'><tbody>
                    <?php foreach ($changes['items_reserved'] as $itemid => $count) { ?>
                        <tr>
                            <td><?php echo order_item_name($orderid, $itemid); ?></td>
                            <td><?php echo $count; ?></td>
                        </tr>
                    <?php } ?>
                    </tbody></table></td>
                <?php } else { ?>
                    <td></td>
                <?php } ?>
                <td><?php echo isset($changes['order_notes']) ? $changes['order_notes'] : ""; ?></td>
            </tr>
        <?php } ?>
        </tbody>
    <?php } ?>
    <?php return ob_get_clean();
}

function history_next_month($current_month) {
    $all_files = scandir(plugin_dir_path( __FILE__ ) . '/logs/');
    $log_files = array_filter($all_files, function($f) {
        return substr($f, -5) === ".json";
    });
    rsort($log_files);
    foreach ($log_files as $idx => $filename) {
        if ($filename === $current_month . ".json" && $idx != count($log_files)-1) {
            return substr($log_files[$idx+1], 0, 4);
        }
    }
    return null;
}

function order_item_name($orderid, $itemid) {
    $order = wc_get_order($orderid);
    if ($order) {
        foreach ($order->get_items() as $item) {
            if ($item->get_id() == $itemid) {
                return $item->get_name();
            }
        }
    }
    return "";
}

function get_processing_orders($order_statuses, $order_ids, $exclude, $products, $paged) {
	$MAX_ORDERS = 20;
	$rows = array();
	
    if (empty($order_ids) && empty($order_statuses)) {
        // No row queried. Return empty table
        $result = array('next_page' => null,
                        'rows' => array());
    } else if (!empty($order_ids)) {
        // Queried by order IDs
        $skipped = array();
		foreach ($order_ids as $order_id) {
            if (in_array($order_id, $skipped)) {
                continue;
            }
			$order = wc_get_order($order_id);
			if ($order) {
                $details = get_order_details($order);
				array_push($rows, $details);
                $skipped = array_merge($skipped, $details['combines']);
			} else {
				header("HTTP/1.0 404 Not Found");
				echo "Order " . $order_id . " not found!";
				die();
			}
		}
		$result = array('next_page' => null,
				    	'rows' => $rows);
	}
	else {	
        // Queried by order status
        $skipped = array();
		while (count($rows) < $MAX_ORDERS) {
			$args = array(
				'status' => $order_statuses,
				'limit' => 20,
				'exclude' => $exclude,
				'orderby' => 'ID',
				'order' => 'ASC',
				'paginate' => true,
				'paged' => $paged++,
			);
			$query = wc_get_orders( $args );
			foreach ($query->orders as $order) {
                if (in_array($order->get_id(), $skipped)) {
                    continue;
                }
				$row = get_order_details($order);
                if (order_interested($row, $products)) {
					array_push($rows, $row);
                    $skipped = array_merge($skipped, $row['combines']);
                }
			}
			if ($paged > $query->max_num_pages) {
				break;
			}
		}
		$result = array('next_page' => $paged > $query->max_num_pages ? null : $paged,
				    	'rows' => $rows);
	}
	return $result;
}

function order_interested($order, $products) {
    $interested = empty($products) ? true : false;
    if (!empty($products)) {
        $interested = false;
        foreach ($order['items'] as $item) {
            foreach ($products as $product) {
                if (stripos($item['name'], $product) !== false) {
                    $interested = true;
                    break;
                }
            }
        }
    }
    return $interested;
}

function tracking_number_form($id, $next_status) {
    ob_start(); 
    global $wpdb;				
    $woo_shippment_table_name = $wpdb->prefix . 'woo_shippment_provider';
    $shippment_providers = $wpdb->get_results( "SELECT * FROM $woo_shippment_table_name WHERE shipping_country = 'MY' AND display_in_order = 1" );
    $default_provider = get_option("wc_ast_default_provider" );	
    ?>
    <p class="form-field tracking_provider_field">
        <label for="tracking_provider">Shipping Provider:</label>
        <select id="tracking_provider-<?php echo $id; ?>" name="tracking_provider" class="chosen_select" style="width:100%;" <?php echo $next_status !== 'wc-shipped' ? 'disabled' : ''; ?>>
        <?php foreach ( $shippment_providers as $providers ) {
            $selected = ( $default_provider == esc_attr( $providers->ts_slug )  ) ? 'selected' : '';
            echo '<option value="' . esc_attr( $providers->ts_slug ) . '" '.$selected. '>' . esc_html( $providers->provider_name ) . '</option>';
        } ?>
        </select>
    </p>
	<p class="form-field tracking_number_field">
		<label for="tracking_number-<?php echo $id; ?>">Tracking number:</label>
        <input
            type="text"
            class="short"
            style=""
            name="tracking_number"
            id="tracking_number-<?php echo $id; ?>"
            value=""
            autocomplete="off"
			<?php echo $next_status !== 'wc-shipped' ? 'disabled' : ''; ?>
			> 
        <a href=# onClick="tryFillTracking(event, this);">Try fill the rest</a>
	</p>
	<?php return ob_get_clean();
}
	

function next_order_status_select($id, $default) {
	ob_start();
	$order_statuses = wc_get_order_statuses(); ?>
	<p class="form-field next_order_status_field">
		<label for="next_order_status">Next order status:</label>
        <select name='next_order_status' id='next-order-satus-<?php echo $id; ?>' disabled>
		<?php foreach ($order_statuses as $key => $label) { ?>
			<option value='<?php echo $key; ?>'	<?php echo $key === $default ? ' selected' : ''; ?>>
				<?php echo $label; ?>
			</option>
		<?php } ?>
		</select>
	</p>
	<?php return ob_get_clean();
}

function get_empty_row($id) {
    return array('order_type'       => 'order_id',
                 'current_status'   => 'none',
                 'next_status' 	    => 'unknown',
                 'id'               => $id,
                 'receiver-name'    => "",
                 'date-created'     => "",
                 'receiver-phone'   => "",
                 'receiver-address' => "",
                 'notes'            => array(),
                 'items-count'      => 0,
                 'items-total'      => 0,
                 'items'            => array(),
                 'fees'             => array(),
                 'combines'         => array(),
                 'combine-next'     => false);
}

function get_order_row($row) {
    ob_start();

    if ($row['order_type'] !== 'order_id') { ?>
        <tr class="row-order disabled row-combine-others">
            <td colspan="8"><?php echo $row['description']; ?></td>
        </tr>
    <?php 
    } else {
        if ($row['current_status'] === 'completed') {
            $row_class = "disabled";
        } else if ($row['next_status'] === 'wc-completed') {
            $row_class = "disabled default-row-ship";
        } else if ($row['next_status'] === 'unknown') {
            $row_class = "disabled";
        } else {
            $row_class = "disabled default-row-reserved";
        }
        $row_class .= " row-id-" . $row['id'];?>
        <tr class="row-order <?php echo $row_class; ?>">
            <td>
                <div class="action">
                    <input type="checkbox" id="ship-checkbox-<?php echo $row['id'] ?>" class="ship-checkbox" />
                    <label for="ship-checkbox-<?php echo $row['id'] ?>">Print</label>
                </div>
                <div class="action">
                    <input type="checkbox" id="reserve-checkbox-<?php echo $row['id'] ?>" class="reserve-checkbox" />
                    <label for="reserve-checkbox-<?php echo $row['id'] ?>">Process</label>
                </div>
                <!--
                <div class="action">
                    <a href=# id="refresh-<?php echo $row['id'] ?>" class="refresh-row">Refresh</a>
                </div>
                -->
                <div class="action">
                    <a href=# id="remove-<?php echo $row['id'] ?>" class="remove-row">Remove</a>
                </div>
            </td>
            <td class='order-id-col'>
                <?php if (substr($row['id'], 0, 5) !== "empty") { ?>
                    <a href='<?php echo get_edit_post_link($row['id']); ?>' target="_blank"><?php echo $row['id']; ?></a>
                    <p class='date-created'>(<?php echo $row['date-created']; ?>)</p>
                <?php } ?>
            </td>
            <td class="receiver-name">
                <textarea class="receiver-name-textarea" id="receiver-name-textarea-<?php echo $row['id']; ?>"><?php echo $row['receiver-name'] ?></textarea>
            </td>
            <td class="receiver-phone">
                <textarea class="receiver-phone-textarea" id="receiver-phone-textarea-<?php echo $row['id']; ?>"><?php echo $row['receiver-phone'] ?></textarea>
            </td>
            <td class="receiver-address">
                <textarea class="receiver-address-textarea" id="receiver-address-textarea-<?php echo $row['id']; ?>"><?php echo $row['receiver-address'] ?></textarea>
            </td>
            <td class="remarks">
                <p>
                    <label>Consignment note remark:</label>
                    <textarea class='consignment-remark' id="consignment-remark-<?php echo $row['id']; ?>"></textarea>
                </p>
                <ul>
                <?php foreach ($row['notes'] as $note) {
                    echo "<li>" . $note . "</li>";
                } ?>
                </ul>
            </td>
            <td class="pack-items">
                <p>
                    <label>Pack list remark:</label>
                    <textarea class='pack-list-remark' id="pack-list-remark-<?php echo $row['id']; ?>"></textarea>
                </p>
                <div>Total: <input class='items-count' id='items-count-<?php echo $row['id'] ?>' value=<?php echo $row['items-count']; ?>> items (RM<input class='items-total' id='items-total-<?php echo $row['id'] ?>' value=<?php echo $row['items-total']; ?>>)</div>
                <table class='order-items'>
                <?php foreach ($row['items'] as $item) { ?>
                    <tr class="product-item <?php echo $item['reserve_done'] ? 'reserve-done' : ''; ?>">
                        <td class='reserve-action-col'>
                            <?php if ($item['quantity'] > 1) { ?>
                            <input type='number'
                                class='reserve-item-count'
                                id='reserve-item-count-<?php echo $item['id'] ?>'
                                min=0
                                max=<?php echo $item['max_reserve'] ?>
                                step=1
                                value=<?php echo $item['to_reserve'] ?>
                            >
                            <?php } ?>
                            <input type='checkbox' 
                                class='reserve-item-checkbox <?php echo $item['to_reserve'] ? 'default-reserve' : ''; ?>'
                                id='reserve-item-checkbox-<?php echo $item['id'] ?>'
                                <?php echo $item['to_reserve'] ? 'checked' : ''; ?> 
                                <?php echo $item['reserve_done'] ? 'disabled' : ''; ?>
                            >
                        </td>
                        <td class='reserve-status-col'><?php echo $item['status']; ?></td>
                        <td class='reserve-item-col' id='<?php echo $item['id']; ?>'>
                            <?php
                            echo $item['name'];
                            foreach ($item['metas'] as $meta_key => $meta_val) {
                                echo "<p class='item-meta'>" . $meta_key . " - " . $meta_val . "</p>";
                            }
                            ?>
                        </td>
                        <td class='total-quantity-col'><?php echo $item['quantity'] > 1 ? "<strong>" . $item['quantity'] . "</strong>" : $item['quantity']; ?></td>
                    </tr>
                <?php } ?>
                <?php foreach ($row['fees'] as $item) { ?>
                    <tr class="fee-item">
                        <td><input type='checkbox' class='reserve-item-checkbox default-reserve' checked></td>
                        <td>?</td>
                        <td><?php echo $item['name']; ?></td>
                        <td></td>
                    </tr>
                <?php } ?>
                </table>
            </td>
            <td class="next-actions">
                <p>Current status: <span class='current-status'><?php echo $row['current_status']; ?></span></p>
                <?php
                echo tracking_number_form($row['id'], $row['next_status']);
                echo next_order_status_select($row['id'], $row['next_status']);
                ?>
            </td>
        </tr>
    <?php
    }
    return ob_get_clean();
}

function print_table_body($rows) {
	ob_start();
    $skip_rows = array();
    echo "<tbody>";
	foreach ($rows as $row) {
        if (in_array($row['id'], $skip_rows)) {
            continue;
        }
        if (!empty($row['combines']) || $row['combine-next']) {
            echo '</tbody><tbody class="combined-orders">';
            echo get_order_row($row);
            foreach($row['combines'] as $combined) {
                $combined_order = wc_get_order($combined);
                if ($combined_order) {
                    $combined_details = get_order_details($combined_order);
                    echo get_order_row($combined_details);
                } else {
                    echo get_order_row(array('order_type' => 'combine_others', 'description' => $combined));
                }
            }
            if ($row['combine-next']) {
                echo get_order_row(array('order_type' => 'combine_next', 'description' => 'Combine next'));
            }
            echo "</tbody><tbody>";
        } else {
            echo get_order_row($row);
        }
	}
    echo "</tbody>";
	return ob_get_clean();
}

function bp_template_setting($data) {
    $saved_sessions = bp_get_saved_sessions();
	?>
	<div class='settings'>
        <div class="tab-nav">
            <label class="active">Get orders<input type="radio" name="bp-tab" value="get-orders" style="display: none" checked></label>
            <label>Settings<input type="radio" name="bp-tab" value="settings" style="display: none"></label>
        </div>
        <div class="tab tab-get-orders">
            <h3>Get orders</h3>
            <h4>Get orders mode</h4>
            <p class="form-field get-order-modes">
                <label class="active">
                    Get order by status
                    <input type='radio' id='by-order-status' name='get-order-mode' value='by-order-status' style="display:none" checked>
                </label> 
                <label>
                    Get order by ID
                    <input type='radio' id='by-order-id' name='get-order-mode' value='by-order-id' style="display:none">
                </label>
                <label>
                    Resume previous session
                    <input type='radio' id='resume-previous-session' name='get-order-mode' value='resume-previous-session' style="display:none">
                </label>
            </p>
            <div id='get-order-mode-container' class='by-order-status'>
                <div id='by-order-status-container'>
                    <h4>Exclude orders</h4>
                    <textarea id='exclude-list'></textarea>
                    <h4>Filter for products (Separated by enter)</h4>
                    <textarea id='products-list'></textarea>
                    <?php $order_statuses = wc_get_order_statuses(); ?>
                    <h4>Select order statuses to display</h4>
                    <p class="form-field order-statuses">
                        <?php foreach ($order_statuses as $key => $label) { ?>
                            <span class='order-status'>
                                <input type='checkbox' id='checkbox-<?php echo $key; ?>' <?php echo $key === 'wc-processing' ? 'checked' : ''; ?>>
                                <label for='checkbox-<?php echo $key; ?>'><?php echo $label; ?></label>
                            </span>
                        <?php } ?>
                    </p>
                </div>
                <div id='by-order-id-container'>
                    <h4>Order IDs (Separated by enter)</h4>
                    <textarea id='get-order-id'></textarea>
                </div>
                <div id='resume-previous-session-container'>
                    <h4>Resume session: </h4>
                    <select id="previous-session-id">
                        <option>Select one</option>
                        <option value="local">Local session (loading...)</option>
                        <?php foreach ($saved_sessions as $session) { ?>
                            <option value="<?php echo $session; ?>"><?php echo $session; ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <h3>Start</h3>
            <button type='button' onClick='getOrders();' class='button'>Get orders</button>
            <button type='button' onClick='getHistory(true);' class='button'>History</button>
            <span class='status-update'></span>
        </div>
        <div class="tab tab-settings" style="display:none">
            <h3>Settings</h3>
            <form class="bp-settings">
                <p class="form-field">
                  <label for="allow-combine">Allow combine orders</label>
                  <input type="checkbox" name="allow-combine" id="allow-combine" <?php echo get_option("allow_combine") ? "checked" : ""; ?>>
                </p>
                <p class="form-field">
                  <label for="bp-prefix">Prefix</label>
                  <input type="text" name="bp-prefix" id="bp-prefix" value="<?php echo get_option("bulk_print_prefix"); ?>">
                </p>
                <button type="button" class="button" onClick='saveBpSettings();'>Save settings</button>
                <span class='status-update'></span>
            </form>
        </div>
	</div>
	<?php
}

function bp_template_print_list($data) {
	?>
	<div class='print-list-wrapper page-top'>
		<h3>Print list <span class='session-name'></span></h3>
        <input type=checkbox id='print-all'><label for='print-all'>Print all</label>
        <input type=checkbox id='process-all'><label for='process-all'>Process all</label>
		<table class='print-list-table'>
            <thead>
                <tr>
                    <th>Actions</th>
                    <th>Order ID</th>
                    <th>Recipient</th>
                    <th>Phone</th>
                    <th>Address</th>
                    <th>Remarks</th>
                    <th>Items</th>
                    <th>Change status</th>
                </tr>
            </thead>
            <?php echo print_table_body($data['rows']); ?>
		</table>
	</div>
	<p class='page-bottom'>
		<label for='show-hide-orders'>Hide unticked orders</label>
		<input type='checkbox' id='show-hide-orders' onClick='showHideOrders(this);'>
	</p>
	<button id='get-more-button' class='button' onClick='getMoreProcessings(this);'>Get more</button>
	<div id='by-order-id-container'>
		<h4>Order IDs (Separated by enter)</h4>
		<textarea id='get-order-id'></textarea>
		<button id='add-orders-button' class='button' onClick='addOrders();'>Add orders</button>
        <button id='add-empty-button' class='button' onClick='addEmptyOrder();'>Add empty</button>
	</div>
	<button class='button' onClick='printConsignments();'>Consignment</button>
	<button class='button' onClick='printInvoice();'>Commercial invoice</button>
    <div class='invoice-template' style='display:none'><?php include_once 'bp_invoice_template.php'; ?></div>
	<button class='button' onClick='printPackingList();'>Packing list</button>
    <?php do_action( 'bulk_print_custom_actions' ); ?>
    <button class='button' onClick='getTrackingFromPD();'>Get tracking number</button>
	<p>
		<label for='ship-date'>Shipping date:</label>
		<input id='ship-date' value='<?php echo wp_date('Y-m-d'); ?>'>
	</p>
	<button id="update-status-btn" class='button-primary' onClick='updateOrderStatuses();'>Update status</button>
	<span class='status-update'></span>
    <div id='save-status'>Ready to save</div>
    <div class='navigation'>
        <button class='button button-to-top' onClick='toTop();'><span class="dashicons dashicons-arrow-up-alt2"></span></button>
        <button class='button button-to-bottom' onClick='toBottom();'><span class="dashicons dashicons-arrow-down-alt2"></span></button>
    </div>
	<?php
}

function template_consignment_page($step, $data) {
	ob_start(); ?>
	<div class='wrap'>
		<h2>Bulk Print Consignment</h2>
        <div class='bp-body'>
            <?php echo call_user_func('bp_template_' . $step, $data); ?>
        </div>
	</div>
	<?php return ob_get_clean();
}

function bp_consignment_page() {
    echo template_consignment_page('setting', null);
}

function bp_log_orders_changed($orders, $shipping_date) {
	$result = array();
    // Prepare contents to write
	$result['shipping_date'] = $shipping_date;
    $result['orders'] = [];
	foreach($orders as $order_id => $details) {
        $result['orders'][$order_id] = $details;
	}

    // Prepare directory if not exist
	if (!file_exists(plugin_dir_path( __FILE__ ) . '/logs')) {
    	mkdir(plugin_dir_path( __FILE__ ) . '/logs', 0777, true);
	}

    // Combine with previous history
    $history = [];
    $log_filename = plugin_dir_path( __FILE__ ) . '/logs/' . wp_date('ym') . '.json';
    if (file_exists($log_filename)) {
        $history_str = file_get_contents($log_filename);
        $history = json_decode($history_str, true);
    }
    $history[wp_date('ymd-Hi')] = $result;

    // Write to file
    $log = fopen($log_filename, 'w');
	fwrite($log, json_encode($history, JSON_PRETTY_PRINT));
	fclose($log);
}

function bp_save_session($data) {
    $save_dir = plugin_dir_path( __FILE__ ) . '/saves';
	if (!file_exists($save_dir)) {
    	mkdir($save_dir, 0777, true);
	}
    // remove old save files if too many
    $savefiles = array_values(array_filter(scandir($save_dir), "is_save_file"));
    while (count($savefiles) > 10) {
        $oldest_save = array_shift($savefiles);
        unlink($save_dir . "/" . $oldest_save);
    }

	$savefile = fopen($save_dir . '/' . $data['sessionName'] . '.json', 'w');
	fwrite($savefile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
	fclose($savefile);
}

function bp_get_saved_sessions() {
    $save_dir = plugin_dir_path( __FILE__ ) . '/saves';
	if (!file_exists($save_dir)) {
        return array();
    }
    $files = array_filter(scandir($save_dir), "is_save_file");
    $sessions = [];
    foreach ($files as $file) {
        $session = basename($file, ".json");
        $sessions []= $session;
    }
    return $sessions;
}

function is_save_file($file) {
    return strpos($file, ".") !== 0 && strpos($file, ".json") !== false;
}
