var checkAjax = null; // to keep track of whether the check ajax is still running

jQuery(document).ready(function() {
    jQuery('a.edit_combine').click(function(e) {
        e.preventDefault();
        jQuery('a.edit_combine').hide();
        jQuery('a.done_combine').show();
        jQuery('.edit-combined-list').show();
        jQuery('#combine-next-checkbox').show();
        jQuery('.combine-next').show();
        jQuery('.remove-combined').show();
    });
	jQuery('a.done_combine').click(function(e) {
        e.preventDefault();
        jQuery('#combined-order-id').val("");
        jQuery('.combined-order-autocomplete').html("");
        jQuery('.combined-order-autocomplete').data("orders", []);
        jQuery('a.edit_combine').show();
        jQuery('a.done_combine').hide();
        jQuery('.edit-combined-list').hide();
        jQuery('#combine-next-checkbox').hide();
        jQuery('.remove-combined').hide();
        if (!jQuery('#combine-next-checkbox').prop("checked")) {
            jQuery('.combine-next').hide();
        }
    });
	jQuery('a.detach-combine').click(detachCombine);
    jQuery('#combined-order-id').on('input', checkOrderID);
    jQuery('#combined-order-id').keyup(enterSaveCombine);
    jQuery('.combined-order-autocomplete').click(saveCombineOrderId);
    jQuery('.remove-combined').click(removeCombined);
    jQuery('#combine-next-checkbox').click(saveCombineNext);
    jQuery('.other-combines-add').click(saveCombineOthers);
    jQuery('.combined-order-autocomplete').data("orders", []);
});

function getCombinedIdsFromFields() {
    const currentId = woocommerce_admin_meta_boxes.post_id;
    const combineNext = jQuery('#combine-next-checkbox').prop('checked');
    const combinedIdsNew = jQuery('.combined-order-autocomplete').data("orders");
    const combinedIdsOld = jQuery('.combined-order a').map(function(idx, ele) { return ele.innerHTML; }).get();
    const combinedOthersNew = jQuery('#other-combines-input').val();
    const combinedOthersOld = jQuery('.other-combined p').map(function(idx, ele) { return ele.innerHTML; }).get();
    var combinedIds = combinedIdsNew.concat(combinedIdsOld, combinedOthersNew, combinedOthersOld);
    if (combineNext && !combinedIds.includes('next')) {
        combinedIds.push('next');
    }
    combinedIds = combinedIds.filter(function (el) {
          return el !== "";
    });
    combinedIds.push(currentId);
    return combinedIds;
}

function enterSaveCombine(e) {
    if (e.keyCode !== 13) { // enter key
        return;
    }
    saveCombineOrderId();
}

function saveCombineOrderId() {
    // verify input before save
    if (jQuery('.combined-order-autocomplete.valid').length === 0) {
        return;
    }
    jQuery('#other-combines-input').val("");
    combinedIds = getCombinedIdsFromFields();
    saveCombine(combinedIds);
}

function saveCombineOthers(e) {
    e.preventDefault();
    jQuery('#combined-order-id').val("");
    jQuery('.combined-order-autocomplete').html("").data("orders",[]).hide().removeClass('valid');
    combinedIds = getCombinedIdsFromFields();
    saveCombine(combinedIds);
}

function saveCombineNext(e) {
    jQuery('#combined-order-id').val("");
    jQuery('.combined-order-autocomplete').html("").data("orders",[]).hide().removeClass('valid');
    jQuery('#other-combines-input').val("");
    combinedIds = getCombinedIdsFromFields();
    saveCombine(combinedIds);
}

function removeCombined(e) {
    jQuery(e.currentTarget).parent().remove();
    jQuery('#combined-order-id').val("");
    jQuery('.combined-order-autocomplete').html("").data("orders",[]).hide().removeClass('valid');
    jQuery('#other-combines-input').val("");
    combinedIds = getCombinedIdsFromFields();
    saveCombine(combinedIds);
}

function detachCombine(e) {
    const currentId = woocommerce_admin_meta_boxes.post_id;
    e.preventDefault();
    saveCombine([currentId]);
}

function saveCombine(combinedIds) {
    const currentId = woocommerce_admin_meta_boxes.post_id;
    jQuery('.combine-order-field').block({message: null, overlayCSS: { background: '#fff', opacity: 0.6 }});
	jQuery.ajax({
        type : "post",
        url : co.ajaxurl,
		dataType: 'json',
        data : {
            action : 'combine_orders_update',
            current_id : currentId,
            combined_ids : combinedIds,
        },
        success: function(response) {
            jQuery('#combined-order-id').val("");
            jQuery('.combined-order-autocomplete').html("").hide().removeClass('valid');
            jQuery('.combined-order-autocomplete').data("orders", []);
            jQuery('#other-combines-input').val("");
            jQuery('#combine-next-checkbox').prop('checked', false);

            var newIDList = jQuery('<ul class="combined-orders"></ul>');
            var newOtherList = jQuery('<ul class="other-combines"></ul>');

            jQuery('.combined-orders').remove();
            jQuery('.other-combines').remove();
            jQuery('.combined-list').append(newIDList, newOtherList);
            for (var order of response.orders) {
                newIDList.append('<li class="combined-order">' + order + '</li>');
            }
            for (var order of response.others) {
                newOtherList.append('<li class="other-combined">' + order + '</li>');
            }
            if (response.next) {
                jQuery('#combine-next-checkbox').prop('checked', true);
            }

            jQuery('.remove-combined').click(removeCombined);
            if (jQuery('.combined-list li').length === 0) {
                jQuery('.combined-list').html('<p>No combined order</p>');
            }
            jQuery('.combine-order-field').unblock();
        },
        error: function(err) {
            jQuery('#combine-feedback').html(err.responseText);
            jQuery('.combine-order-field').unblock();
            console.log(err);
        }
    });
}

function checkOrderID() {
    orderID = jQuery('#combined-order-id').val();
    oldOrders = jQuery('.combined-order a').map(function(idx, ele) { return ele.innerHTML; }).get();
    jQuery('.combined-order-autocomplete').removeClass('valid').html('searching...').show();
    if (checkAjax !== null) {
        checkAjax.abort();
    }
    if (orderID === "") {
        jQuery('.combined-order-autocomplete').html("");
        jQuery('.combined-order-autocomplete').hide();
        return;
    } else if (orderID === woocommerce_admin_meta_boxes.post_id) {
        jQuery('.combined-order-autocomplete').html("Cannot combine with self");
        return;
    } else if (oldOrders.includes(orderID)) {
        jQuery('.combined-order-autocomplete').html("Already combined");
        return;
    }
    checkAjax = jQuery.ajax({
        type : "get",
        url : co.ajaxurl,
		dataType: 'json',
        data : {
            action : 'combine_orders_check_orderid',
            order_id : orderID,
        },
        success: function(response) {
            if (response.found) {
                const orders = response.orders.join(" + ");
                jQuery('.combined-order-autocomplete').html(orders);
                jQuery('.combined-order-autocomplete').data("orders", response.orders);
                jQuery('.combined-order-autocomplete').addClass('valid');
            } else {
                jQuery('.combined-order-autocomplete').html("Order not found");
            }
            checkAjax = null;
        },
        error: function(err) {
            if (err.statusText !== "abort") {
                jQuery('#combine-feedback').html(err.responseText);
                console.log(err);
            }
            checkAjax = null;
        }
    });
}

function splitByLines(input) {
    trimmed = input.split("\n").map(function(val) {
        return val.trim();
    });
    return trimmed.filter(function(val) { return val; });
}
