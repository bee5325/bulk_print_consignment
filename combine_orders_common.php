<?php

function has_combined_order($id) {
    $combined_orders = get_post_meta($id, '_combine_orders', true);
    return $combined_orders !== "";
}

function get_formatted_combines($id) {
    $combined_meta = restore_combine_meta($id, false);
    $combines = array_merge($combined_meta['orders'], $combined_meta['others']);
    if ($combined_meta['next']) {
        $combines []= 'Combine with next order';
    }
    return implode(", ", $combines);
}

function restore_combine_meta($id, $include_self) {
    $combined_orders = get_post_meta($id, '_combine_orders', true);
    $combine_next = false;
    $orders = [];
    $others = [];

    if ($combined_orders) {
        $combined_orders = json_decode($combined_orders);
        foreach ($combined_orders as $order) {
            if (!$include_self && $order == $id) {
                continue;
            } else if ($order === 'next') {
                $combine_next = true;
            } else if (substr($order, 0, 2) === 'fb' || substr($order, 0, 6) === 'others') {
                array_push($others, $order);
            } else {
                array_push($orders, intval($order));
            }
        }
    } else {
        if (!$include_self) {
            $orders = [];
        } else {
            $orders = [$id];
        }
    }

    return array('orders' => $orders,
                 'others' => $others,
                 'next'   => $combine_next);
}

function combine_valid($order) {
    if ($order != "next"
        && substr($order, 0, 4) !== 'fb -'
        && substr($order, 0, 8) !== 'others -'
        && get_post_type($order) !== "shop_order") {
        return false;
    }
    return true;
}

function combine_status_valid($orders) {
    foreach ($orders as $order_id) {
        $order = wc_get_order($order_id);
        // for fb / other orders, always valid
        if (!$order) {
          return true;
        }

        if (in_array($order->get_status(), ALLOWED_COMBINE_STATUS)
            || $order_id === "next"
            || substr($order_id, 0, 4) === 'fb -'
            || substr($order_id, 0, 8) === 'others -'
        ) {
            return true;
        }
    }
    return false;
}

/**
 * Update combined order list in post meta
 *
 * @param current_id : order id where this is triggered from
 * @param combined_ids : array of ids to combined (must include current_id)
 *                       - if current_id not included, error case, reject (include empty case)
 *                       - if only current_id, detach current order from the rest
 *                       - else, update all affected orders
 */
function update_combined_orders($current_id, $combined_ids) {
    $previous_combined_meta = restore_combine_meta($current_id, true);
    $previous_combined = $previous_combined_meta['orders'];
    $previous_combine_next = $previous_combined_meta['next'];

    // Separate new combine orders and combine next
    $new_combined = array_unique($combined_ids);
    $combine_next = in_array("next", $new_combined);
    if ($combine_next) {
        $new_combined = array_diff($new_combined, ['next']);
    }

    if (!in_array($current_id, $combined_ids)) {
        // This check is to prevent mistake by user
        // TODO: change this to proper error handling
        wp_die("Combined list must contain current order ID!", 400);
    } else if (count($new_combined) === 1 && !$combine_next) {
        // Detach current order from other combined orders
        $new_combined = array_diff($previous_combined, [$current_id]);
        store_combine_meta([$current_id], false);
        store_combine_meta($new_combined, $previous_combine_next);
    } else {
        // All other add or remove cases
        $remove_list = array_diff($previous_combined, $new_combined);
        foreach ($remove_list as $remove) {
            store_combine_meta([$remove], false);
        }
        store_combine_meta($new_combined, $combine_next);
    }
}

function store_combine_meta($combined, $combine_next) {
    $with_combine_next = $combine_next ? array_merge($combined, ["next"]) : $combined;
    if (count($with_combine_next) === 1) {
        $order_id = $with_combine_next[0];
        delete_post_meta($order_id, '_combine_orders');
        $order = wc_get_order($order_id);
        if ($order) {
            $order->add_order_note("Removed all combines", false, true);
        }
    } else {
        foreach ($combined as $c) {
            if (substr($c, 0, 2) === 'fb' || substr($c, 0, 6) === 'others') {
                continue;
            }
            update_post_meta($c, '_combine_orders', json_encode($with_combine_next));
            $order = wc_get_order($c);
            $exclude_self = array_diff($with_combine_next, [$c]);
            $order->add_order_note("Set combined orders to " . implode(", ", $exclude_self), false, true);
        }
    }
}


?>
