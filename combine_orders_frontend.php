<?php

define("ALLOWED_COMBINE_STATUS", array('on-backorder', 'processing', 'on-hold', 'combine', 'exchange'));

/**
 * Helper functions
 */
function combined_orders_to_string($combined_orders, $combine_remarks) {

    if (empty($combined_orders)) {
        return "";
    }

    // Make combine next more verbose
    foreach ($combined_orders as $key => $order) {
        if ($order === "next") {
            $combined_orders[$key] = "Combine with next order";
        } else if ($order  === "others") {
            $combined_orders[$key] = "Other order ID (To be reviewed) - " . $combine_remarks;
        } else if ($order === "fb") {
            $combined_orders[$key] = "FB / IG manual order (To be reviewed) - " . $combine_remarks;
        } else {
            $order_object = wc_get_order($order);
            if ($order_object) {
                $order_url = wc_get_order($order)->get_view_order_url();
                $combined_orders[$key] = "<a href=" . $order_url . " target=_blank>" . $order . "</a>";
            } else {
                $combined_orders[$key] = $order;
            }
        }
    }

    $order_str = implode(", ", $combined_orders);
    return $order_str;
}

function combine_reminders($combine_next) {
    if ($combine_next) {
        ob_start(); ?>

        <ul class='combine-reminders'>
        <li>We will reserve the items ordered until request received to ship out the items.</li>
        <li>Please request for ship out the items in your next order.</li>
        <li>If you would like to stop combining current order(s), please contact us through FB / IG / Whatsapp.</li>
        </ul>

        <?php return ob_get_clean();
    } else {
        ob_start(); ?>

        <ul class='combine-reminders'>
        <li>Shipping fee will be applied if the combined orders' total is less than RM120.</li>
        <li>All items will be shipped at once when all backordered items (if any) arrived.</li>
        </ul>

        <?php return ob_get_clean();
    }
}

function combined_spending($combined_orders) {
    $totals = WC()->cart->get_cart_contents_total();
    foreach ($combined_orders as $combined) {
        if ($combined === "next"
            || substr($combined, 0, 2) === 'fb' 
            || substr($combined, 0, 6) === 'others') {
            continue;
        }
        $order = wc_get_order($combined);
        $totals += $order->get_total();
    }
    return $totals;
}

function shipping_paid($combined_orders) {
    foreach ($combined_orders as $combined) {
        $order = wc_get_order($combined);
        if ($order && $order->get_shipping_total() > 0) {
            return true;
        }
    }
}

/**
 * Templates
 */
function checkout_combine_form() {
    if (!WC()->cart->needs_shipping()) {
        return;
    }

    $user_combine_meta = get_user_meta(get_current_user_id(), '_combine_order', true);
    $combined_orders = isset($user_combine_meta['orders']) ? $user_combine_meta['orders'] : null;

    // verify again if the combine is still valid
    if (!empty($combined_orders) && !combine_status_valid($combined_orders)) {
        wc_add_notice('Your combined orders are outdated. Please reapply.', 'error');
        delete_user_meta(get_current_user_id(), '_combine_order');
        $combined_orders = "";
    }

    $combine_remarks = isset($user_combine_meta['remarks']) ? $user_combine_meta['remarks'] : null;
    $combined_orders_text = combined_orders_to_string($combined_orders, $combine_remarks);
    $combine_reminders = "<ul class='combine-reminders'></ul>";
    if (!empty($combined_orders)) {
        $combine_next = in_array("next", $combined_orders);
        $combine_reminders = combine_reminders($combine_next);
    }
    ?>

    <div class='combine-form'>
        <h3>Combine orders</h3>
        <p>
            If you would like to combine this order with previous / next order,
            Please apply it below.
        </p>

        <?php if (!is_user_logged_in()) { ?>
            <p>Please <a href=# class="combine-showlogin">log in</a> to use the function</p>
        <?php } else { ?>
            <div class='combine-edit-fields' style='display:none'>
                <label for='combine-order'>Combine with order(s):</label>
                <select id='combine-order' style='width: 100%'>
                    <option value="" disabled selected>Select order(s) to combine</option>
                    <option value="next">Combine next</option>
                </select>
                <p class='form-row' id='combine-others-remarks-container' style='display:none'>
                    <label for='combine-others-remarks'>Remarks: <abbr class='required' title='required'>*</abbr></label>
                    <input type='text' class='input-text' id='combine-others-remarks'></input>
                </p>
                <p class='form-row' id='combine-next-container' style='display:none'>
                    <input type='checkbox' id='ship-now-checkbox' checked>
                    <label for='ship-now-checkbox'>Ship immediately when all items ready</label>
                </p>
                <button type='button' id='apply-combine'>Apply combine</button>
            </div>
            <div class='combine-view-fields' style='display:none'>
                <label>Combined with order(s):</label>
                <div class='combined-feedback'><?php echo $combined_orders_text; ?></div>
                <?php echo $combine_reminders; ?>
                <button type='button' id='cancel-combine'>Cancel combine</button>
            </div>
        <?php } ?>

    </div>

<?php
}

/**
 * Ajax functions
 */
function customer_get_orders() {
    $customer = get_current_user_id();
    $orders = wc_get_orders(array('customer' => $customer, 'limit' => 10));
    $checked = [];
    $result = array('can_combine' => [], 'cannot_combine' => []);

    foreach ($orders as $id => $order) {
        if (in_array($order->get_id(), $checked)) {
            continue;
        }
        if (in_array($order->get_status(), ALLOWED_COMBINE_STATUS)) {
            $combined_meta = restore_combine_meta($order->get_id(), true);
            $combined_status = [];
            // for valid orders, get current status
            foreach ($combined_meta['orders'] as $c) {
                $corder = wc_get_order($c);
                $combined_status[$c] = $corder->get_status();
            }
            // for FB / other orders, no status
            foreach ($combined_meta['others'] as $c) {
                $combined_status[$c] = "N/A";
            }
            $checked = array_merge($checked, $combined_meta['orders']);
            array_push($result['can_combine'], $combined_status);
        } else {
            $result['cannot_combine'][$order->get_id()] = $order->get_status();
        }
    }
    // remove combined orders if found
    foreach ($checked as $c) {
        unset($result['cannot_combine'][$c]);
    }
    krsort($result['cannot_combine']);

    echo json_encode($result);
    wp_die();
}

/**
 * Styles and scripts
 */
function combine_form_scripts() {
    if (!is_page('checkout')) {
        return;
    }
    wp_enqueue_style( 'combine-frontend', plugins_url('combineOrdersFrontend.css', __FILE__), array(), "v0.01" );
	wp_register_script( "combine-frontend", plugins_url('combineOrdersFrontend.js', __FILE__), array('jquery'), "v0.04", true );
    wp_localize_script( 'combine-frontend', 'combine', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ),
                                                              'applyCombineNonce' => wp_create_nonce( 'apply-combine' ),
                                                              'cancelCombineNonce' => wp_create_nonce( 'cancel-combine' )));
	wp_enqueue_script( 'combine-frontend' );
}

function apply_combine() {
    check_ajax_referer( 'apply-combine', 'security' );
    $orders = isset($_POST['orders']) ? $_POST['orders'] : null;
    $remarks = isset($_POST['remarks']) ? $_POST['remarks'] : "";
    $success = true;
    $orders_feedback = "";

    // Validation
    if (empty($orders)) {
        wc_add_notice('Please choose the order to combined from the list', 'error');
        $success = false;
    } else {
        $orders = array_unique($orders);
        foreach ($orders as $order) {
            if ($order === "others" || $order === "fb") {
                $remarks = trim($remarks);
                if ($remarks === "") {
                    wc_add_notice('Please put the remark for which order you would like to combine', 'error');
                    $success = false;
                    break;
                }
            } else if (!combine_valid($order)) {
                wc_add_notice('Order to be combined invalid. Please contact us if you think this is an error', 'error');
                $success = false;
                break;
            }
        }
    }

    // Success logics
    if ($success) {
        // Check total spending elligible for free shipping
        $user_combine_meta = array('orders' => $orders,
                                   'remarks' => $remarks,
                                   'free_shipping_reminder' => "");
        update_user_meta(get_current_user_id(), "_combine_order", $user_combine_meta);
        WC()->cart->calculate_totals();
        wc_add_notice('Orders combined successfully', 'success');
        $orders_feedback = combined_orders_to_string($orders, $remarks);
    }
    $combine_next = in_array("next", $orders);
    echo json_encode(array('orders' => $orders_feedback,
                           'notice' => wc_print_notices(true),
                           'reminders' => combine_reminders($combine_next)));
    wp_die();
}

function cancel_combine() {
    check_ajax_referer( 'cancel-combine', 'security' );
    delete_user_meta(get_current_user_id(), "_combine_order");
    WC()->cart->calculate_totals();
    wc_add_notice('Combine cancelled', 'success');
    echo json_encode(array('notice' => wc_print_notices(true)));
    wp_die();
}

/**
 * Hooks
 */
function packages_register_combine($packages) {
    if (!is_user_logged_in()) {
        return $packages;
    }
    foreach ($packages as $key => $package) {
        if ($combined = get_user_meta(get_current_user_id(), "_combine_order", true)) {
            $packages[$key]['combine'] = $combined;
        }
    }
    return $packages;
}

function combine_allow_free_shipping_method($available, $package, $fsmethod) {
    if (!empty($package['combine'])) {
        // Always free ship for FB, others
        foreach ($package['combine']['orders'] as $combined) {
            if (in_array($combined, ['fb', 'others', 'next'])) {
                $package['combine']['free_shipping_reminder'] = "free shipping";
                $available = true;
                break;
            }
        }

        if (!$available && shipping_paid($package['combine']['orders'])) {
            // Shipping fee only need to be paid once
            $package['combine']['free_shipping_reminder'] = "Shipping already paid - free shipping";
            $available = true;
        }
        
        if (!$available) {
            $totals = combined_spending($package['combine']['orders']);

            if ($package['destination']['country'] === "SG") {
                // no free shipping for Singapore customers
                $package['combine']['free_shipping_reminder'] = "Shipping fee of RM40 is applied for each parcel to Singapore";
                $available = false;
            } else if ($totals >= $fsmethod->min_amount) {
                // Check total spending elligible for free shipping
                $package['combine']['free_shipping_reminder'] = "Combined value more than RM120 - free shipping";
                $available = true;
            } else {
                $package['combine']['free_shipping_reminder'] = "RM" . ($fsmethod->min_amount - $totals) . " left for free shipping";
                $available = false;
            }
        }

        update_user_meta(get_current_user_id(), "_combine_order", $package['combine']);
    }

    // Normal calculation for non combined orders
    return $available;
}

function review_order_show_combine() {
    $user_combine_meta = get_user_meta(get_current_user_id(), '_combine_order', true);
    if (!empty($user_combine_meta)) {
        $combined_orders = $user_combine_meta['orders'];
        $combine_remarks = $user_combine_meta['remarks'];
        $free_shipping = $user_combine_meta['free_shipping_reminder'];
        ?>
        <tr class="review-combined-feedback">
            <td>Combined orders:</td>
            <td>
                <?php echo combined_orders_to_string($combined_orders, $combine_remarks); ?> 
                <span class="free-shipping-reminder"><?php echo "(" . $free_shipping . ")"; ?></span> 
                <a id='cancel-combine'>[Cancel combine]</a></td>
        </tr>
        <?php
    }
}

function checkout_store_combined_orders($order_id) {
    if ($user_combine_meta = get_user_meta(get_current_user_id(), '_combine_order', true)) {
        $combined_orders = $user_combine_meta['orders'];

        array_push($combined_orders, $order_id);
        foreach ($combined_orders as $i => $order) {
            if (in_array($order, ['fb', 'others'])) {
                $combined_orders[$i] = $order . " - " . $user_combine_meta['remarks'];
            }
        }
        update_combined_orders($order_id, $combined_orders);
        delete_user_meta(get_current_user_id(), '_combine_order');
    }
}

function remove_combined_when_cancelling_pending($order_id) {
    if (has_combined_order($order_id)) {
        update_combined_orders($order_id, [$order_id]);
    }
}

function combine_code_deprecated_message($msg, $msg_code, $coupon) {
    if ($coupon->get_code() === "combine") {
        return $msg . '<br>Take note that coupon code COMBINE is <span style="color: red">not required</span> when using the new combine system.<br>This coupon code will not be supported anymore starting from <span style="color: red">May 2021</span>.<br>Please try out the new combine system instead 😊';
    }
    return $msg;
}

function combine_code_deprecated_forever_message($err, $err_code, $instance) {
    if ($instance->get_code() === 'combine') {
        return "Coupon code COMBINE is already discontinued. Please use the new combine system instead.<br>If you face any issue, don't hesitate to contact us. 😊 ";
    }
    return $err;
}


// TODO: do not allow combine code when combine system in effect

function my_orders_add_combine_column($col) {
    $col['order-combines'] = 'Combined orders';
    return $col;
}

function my_orders_show_combined_orders($order) {
    $combined_orders = restore_combine_meta($order->get_id(), false);
    $orders_verbose = [];

    foreach ($combined_orders['orders'] as $combined) {
        $order = wc_get_order($combined);
        if ($order) {
            $order_url = wc_get_order($combined)->get_view_order_url();
            $orders_verbose []= "<a href=" . $order_url . " target=_blank>" . $combined . "</a>";
        }
    }
    foreach ($combined_orders['others'] as $other) {
        $orders_verbose []= $other;
    }
    if ($combined_orders['next']) {
        $orders_verbose []= "Combine with next order";
    }

    if ($orders_verbose) {
        echo implode(", ", $orders_verbose);
    } else {
        echo "-";
    }
}

function mylog2($message) {
    // Prepare directory if not exist
    if (!file_exists(plugin_dir_path(__FILE__) . '/logs')) {
        mkdir(plugin_dir_path(__FILE__) . '/logs', 0777, true);
    }

    // TODO: Implement log rotate to prevent it from being too large
    $num = 0;
    $filename = plugin_dir_path(__FILE__) . "/logs/events_" . sprintf('%02d', $num) . ".log";

    error_log(wp_date("ymdHis") . " - " . $message . "\n", 3, $filename);
}

require_once plugin_dir_path( __FILE__ ) . 'combine_orders_common.php';

add_action( 'woocommerce_checkout_shipping', 'checkout_combine_form', 20 );

add_action( 'wp_enqueue_scripts', 'combine_form_scripts' );

add_action( 'wp_ajax_customer_get_orders', 'customer_get_orders' );
add_action( 'wp_ajax_apply_combine', 'apply_combine' );
add_action( 'wp_ajax_cancel_combine', 'cancel_combine' );

add_filter( 'woocommerce_cart_shipping_packages', 'packages_register_combine', 20 );
add_filter( 'woocommerce_shipping_free_shipping_is_available', 'combine_allow_free_shipping_method', 10, 3 );
add_action( 'woocommerce_review_order_before_order_total', 'review_order_show_combine', 20 );
add_action( 'woocommerce_checkout_update_order_meta', 'checkout_store_combined_orders' );
add_action( 'woocommerce_cancelled_order', 'remove_combined_when_cancelling_pending' );
add_filter( 'woocommerce_coupon_message', 'combine_code_deprecated_message', 20, 3 );
add_filter( 'woocommerce_coupon_error','combine_code_deprecated_forever_message',10,3 );

// view orders hooks
add_filter( 'woocommerce_account_orders_columns', 'my_orders_add_combine_column', 20 );
add_action( 'woocommerce_my_account_my_orders_column_order-combines', 'my_orders_show_combined_orders', 20 );
