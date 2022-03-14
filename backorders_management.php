<?php

function backorders_scripts($hook) {
	if ( 'woocommerce_page_bo-management' !== $hook ) {
		return;
	}
    $version = "0.2";
    wp_enqueue_style( 'backorders_management', plugins_url('backorders.css', __FILE__), array(), $version );
	wp_register_script( "backorders_management", plugins_url('backorders.js', __FILE__), array('jquery', 'jquery-blockui'), $version );
	wp_localize_script( 'backorders_management', 'bo', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) ) );
	wp_enqueue_script( 'backorders_management' );
}

function bo_management_page() {
    $items = wc_get_products(
        array(
            'visibility' => 'visible',
            'status' => 'publish',
            'date_modified' => '>' . strtotime('-2 months'),
            'limit' => -1
        )
    );
    $stocks = array();
    foreach ($items as $item) {
        if ($item->is_type('variable')) {
            foreach ($item->get_children() as $child_id) {
                $child = new WC_Product_Variation($child_id);
                $bo_history = get_backordered_history($child_id);
                array_push($stocks, array('id' => $child_id,
                                          'name' => $child->get_name(),
                                          'quantity' => $child->get_stock_quantity(),
                                          'history' => $bo_history));
            }
        } else {
            $bo_history = get_backordered_history($item->get_id());
            array_push($stocks, array('id' => $item->get_id(),
                                      'name' => $item->get_name(),
                                      'quantity' => $item->get_stock_quantity(),
                                      'history' => $bo_history));
        }
    }
    usort($stocks, "sort_by_quantity_name");
    ?>

    <div class="update-received-amounts" style="display: none">
        <button class="button-primary" onClick="updateReceivedAmounts();">Update received</button>
    </div>
    <h2>Backorders</h2>
    <div class="table-actions">
        <label for="filter-needed">Show only not enough</label>
        <input type="checkbox" name="filter-needed" id="filter-needed" onChange="filterRows();" />
        <label for="filter-not-received">Show only not received</label>
        <input type="checkbox" name="filter-not-received" id="filter-not-received" onChange="filterRows();" />
    </div>
    <table class='backorder-table'>
        <thead>
            <tr>
                <th>Item</th>
                <th>Qty</th>
                <th>Need</th>
                <th>Backorder history</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($stocks as $item) { ?>
                <tr class="product-row">
                    <td class='name-col'><?php echo $item['name']; ?></td>
                    <td class='quantity-col'><?php echo $item['quantity']; ?></td>
                    <td class='needed-col'>.</td>
                    <td class='action-col'>
                        <label for='backordered-amount'>Add ordered : </label>
                        <input class= 'add-history-input' type='number' id='backordered-amount'></input>
                        <a class='action add-history-button' onClick='addBackorderHistory(event);' productid=<?php echo $item['id']; ?> href=#>Add</a>
                        <?php echo get_formatted_history($item['id'], $item['history']); ?>
                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>

    <?php
}

function exclude_duplicate_backorder_history($exclude_meta) {
    array_push($exclude_meta, '_bo_history');
    return $exclude_meta;
}

/**
 * Helper functions
 **/
function sort_by_quantity_name($a, $b) {
    if (($a['quantity'] < 0 && $b['quantity'] < 0) ||
        ($a['quantity'] >= 0 && $b['quantity'] >= 0)) {
        return strcasecmp($a['name'], $b['name']);
    } else {
        return $a['quantity'] < $b['quantity'] ? -1 : 1;
    }
}

function get_backordered_history($id) {
    $bo_meta = get_post_meta($id, "_bo_history", true);
    if ($bo_meta) {
        return $bo_meta;
    }
    return null;
}

function get_formatted_history($id, $histories) {
    if (!$histories) {
        return;
    }

    ob_start(); ?>

    <table class='history'><tbody>
    <?php foreach ($histories as $idx => $history) { ?>
        <tr
            class="
                history-row
                <?php echo $history['received'] >= $history['ordered'] ? 'received-all' : ''; ?>
                <?php echo isset($history['completed']) && $history['completed'] ? 'completed' : ''; ?>
            "
        >
            <td class='col-ordered'>
                Ordered
                <span class='ordered-amount'><?php echo $history['ordered']; ?></span>
                (<?php echo $history['ordered_date']; ?>)
            </td>
            <td class='col-received'>
                Received
                <input class='update-history-input' type='number' min='0' value="<?php echo $history['received']; ?>" productid=<?php echo $id; ?> idx=<?php echo $idx; ?> />
                <?php echo $history['received_date'] === "" ? "" : "(" . $history['received_date'] . ")"; ?>
            </td>
            <td class='col-complete-mark'>
                <span class="completed-mark dashicons dashicons-yes-alt"></span>
            </td>
            <td class='col-action'>
                <span
                    class='action remove-history-button dashicons dashicons-trash'
                    productid=<?php echo $id; ?>
                    idx=<?php echo $idx; ?>
                >
                </span>
                <span
                    class="action complete-history-button dashicons dashicons-yes"
                    productid=<?php echo $id; ?>
                    idx=<?php echo $idx; ?>
                >
                </span>
            </td>
        </tr>
    <?php } ?>
    </tbody></table>

    <?php return ob_get_clean();
}

/**
 * AJAX FUNCTIONS
 */
function bo_add_backorder_history() {
    $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : -1;
    $amount = isset($_POST['amount']) ? (int)$_POST['amount'] : -1;
    
    if ($product_id === -1) {
        wp_die("Product ID not provided", 400);
    } else if (get_post_type($product_id) !== "product" && get_post_type($product_id) !== "product_variation") {
        wp_die("Product ID " . $product_id . " not found!", 404);
    } else if ($amount < 1) {
        wp_die("Ordered amount not provided", 400);
    }

    $history = get_backordered_history($product_id);
    if ($history === null) {
        $history = [];
    }

    array_unshift($history, array(
        "ordered_date" => current_time("Y-m-d"),
        "ordered" => $amount,
        "received" => 0,
        "received_date" => ""
    ));
    update_post_meta($product_id, '_bo_history', $history);
    echo json_encode(array('history' => get_formatted_history($product_id, $history)));
    wp_die();
}

function bo_remove_backorder_history() {
    $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : -1;
    $idx = isset($_POST['index']) ? (int)$_POST['index'] : 99999;

    if ($product_id === -1) {
        wp_die("Product ID not provided", 400);
    } else if (get_post_type($product_id) !== "product" && get_post_type($product_id) !== "product_variation") {
        wp_die("Product ID " . $product_id . " not found!", 404);
    }

    $history = get_backordered_history($product_id);
    if ($idx >= count($history)) {
        wp_die("Invalid index", 400);
    }

    // Revert stock changes due to received entries
    $product = wc_get_product($product_id);
    if ($history[$idx]['received'] > 0) {
        wc_update_product_stock($product, $history[$idx]['received'], 'decrease');
    }

    // Remove entry from history
    unset($history[$idx]);
    $history = array_values($history);
    if (count($history) > 0) {
        update_post_meta($product_id, '_bo_history', $history);
    } else {
        delete_post_meta($product_id, '_bo_history');
    }
        
    echo json_encode(array('history' => get_formatted_history($product_id, $history),
                           'stock' => $product->get_stock_quantity()));
    wp_die();
}

function bo_mark_complete_backorder_history() {
    $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : -1;
    $idx = isset($_POST['index']) ? (int)$_POST['index'] : 99999;

    if ($product_id === -1) {
        wp_die("Product ID not provided", 400);
    } else if (get_post_type($product_id) !== "product" && get_post_type($product_id) !== "product_variation") {
        wp_die("Product ID " . $product_id . " not found!", 404);
    }

    $history = get_backordered_history($product_id);
    if ($idx >= count($history)) {
        wp_die("Invalid index", 400);
    }

    $history[$idx]['completed'] = !$history[$idx]['completed'];
    $history[$idx]['received_date'] = current_time("Y-m-d");
    update_post_meta($product_id, '_bo_history', $history);
    echo json_encode(array('history' => get_formatted_history($product_id, $history)));
    wp_die();
}

function bo_update_received_amount() {
    $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : -1;
    $idx = isset($_POST['index']) ? (int)$_POST['index'] : 99999;
    $new_received = isset($_POST['received']) ? (int)$_POST['received'] : -1;

    if ($product_id === -1) {
        wp_die("Product ID not provided", 400);
    } else if (get_post_type($product_id) !== "product" && get_post_type($product_id) !== "product_variation") {
        wp_die("Product ID " . $product_id . " not found!", 404);
    } else if ($new_received < 0) {
        wp_die("Received amount not provided", 400);
    }

    $history = get_backordered_history($product_id);
    if ($idx >= count($history)) {
        wp_die("Invalid index", 400);
    }

    // Update stock value
    $prev_received = $history[$idx]['received'];
    $product = wc_get_product($product_id);
    $updated_stock_amount = $product->get_stock_quantity() - $prev_received + $new_received;
    wc_update_product_stock($product, $updated_stock_amount);

    // Update history
    $history[$idx]['received'] = $new_received;
    $history[$idx]['received_date'] = current_time("Y-m-d");
    update_post_meta($product_id, '_bo_history', $history);
    echo json_encode(array('history' => get_formatted_history($product_id, $history),
                           'stock' => $product->get_stock_quantity()));
    wp_die();
}

add_action( 'admin_enqueue_scripts', 'backorders_scripts' );
add_filter( 'woocommerce_duplicate_product_exclude_meta', 'exclude_duplicate_backorder_history' );
add_action( 'wp_ajax_bo_add_backorder_history', 'bo_add_backorder_history' );
add_action( 'wp_ajax_bo_remove_backorder_history', 'bo_remove_backorder_history' );
add_action( 'wp_ajax_bo_mark_complete_backorder_history', 'bo_mark_complete_backorder_history' );
add_action( 'wp_ajax_bo_update_received_amount', 'bo_update_received_amount' );
