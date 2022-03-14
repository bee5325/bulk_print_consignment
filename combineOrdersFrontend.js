var customerOrdersObtained = false;

jQuery(document).ready(function() {
    jQuery.blockUI.defaults.overlayCSS.cursor = 'default';
    jQuery("#combine-order").select2({width: 'resolve'});
    jQuery("#combine-order").on('select2:opening', getCustomerOrders);
    jQuery("#combine-order").on('change', refreshCombineField);
    jQuery("#apply-combine").on('click', applyCombine);
    jQuery( document.body ).on('click', '#cancel-combine', cancelCombine );
    jQuery(".combine-showlogin").on('click', showLogin);
    if (jQuery(".combined-feedback").html() === "") {
        allowEditCombine(true);
    } else {
        allowEditCombine(false);
    }
});

function refreshCombineField(e) {
    const selected = jQuery(e.target).val();
    jQuery("#ship-now-checkbox").prop("checked", true);
    if (selected == "others") {
        jQuery("label[for=combine-others-remarks]").html("Order ID <abbr class='required' title='required'>*</abbr>");
        jQuery("#combine-others-remarks-container").show(200);
        jQuery("#combine-next-container").slideDown();
    } else if (selected === "fb") {
        jQuery("label[for=combine-others-remarks]").html("FB / IG username <abbr class='required' title='required'>*</abbr>");
        jQuery("#combine-others-remarks-container").show(200);
        jQuery("#combine-next-container").slideDown();
    } else if (selected === "next") {
        jQuery("#combine-others-remarks-container").hide(200);
        jQuery("#combine-next-container").slideUp();
    } else {
        jQuery("#combine-others-remarks-container").hide(200);
        jQuery("#combine-next-container").slideDown();
    }
}

function allowEditCombine(allow) {
    if (allow) {
        jQuery(".combine-edit-fields").slideDown(200);
        jQuery(".combine-view-fields").slideUp(200);
    } else {
        jQuery(".combine-edit-fields").slideUp(200);
        jQuery(".combine-view-fields").slideDown(200);
    }
}

function getCustomerOrders(e) {
    if (customerOrdersObtained) {
        return;
    }
    e.preventDefault();
    jQuery('.combine-form').block({message: null, overlayCSS: { background: '#fff', opacity: 0.6 }});
	jQuery.ajax({
        type : "get",
        url : combine.ajaxurl,
		dataType: 'json',
        data : {
			action: 'customer_get_orders',
		},
        success: function(response) {
            for (var orderGroup of response.can_combine) {
                const orderValue = Object.keys(orderGroup).join("_");
                var orderText = "";
                var count = 0;
                for (var order in orderGroup) {
                    orderText += order + " (" + orderGroup[order] + ")";
                    if (++count !== Object.keys(orderGroup).length) {
                        orderText += " + ";
                    }
                }
                jQuery('#combine-order').append('<option value="' + orderValue + '">' + orderText + '</option>');
            }
            for (var order in response.cannot_combine) {
                const orderStatus = response.cannot_combine[order];
                jQuery('#combine-order').append('<option value="' + order + '" disabled>' + order + ' (' + orderStatus + ')</option>');
            }
            customerOrdersObtained = true;
            jQuery('.combine-form').unblock();
            jQuery('#combine-order').select2('open');
        },
		error: function(err) {
            console.log(err);
        }
    });
}

function applyCombine() {
    jQuery('.combine-form').block({message: null, overlayCSS: { background: '#fff', opacity: 0.6 }});
    var combineOrders = jQuery("#combine-order").val() ? jQuery("#combine-order").val().split("_") : [];
    if (!jQuery("#ship-now-checkbox").prop("checked") && !combineOrders.includes("next")) {
        combineOrders.push("next");
    }
    const combineRemarks = jQuery("#combine-others-remarks").val();
    jQuery.ajax({
        type : "post",
        url : combine.ajaxurl,
        dataType : "json",
        data : {
            action : 'apply_combine',
            security : combine.applyCombineNonce,
            orders : combineOrders,
            remarks : combineRemarks,
        },
        success: function(response) {
            jQuery( '.woocommerce-error, .woocommerce-message' ).remove();
            jQuery('.combine-form').unblock();

            if (response.notice) {
                jQuery('.combine-form').before(response.notice);
            }
            if (response.orders === "") {
                // scroll to error notice when not successful
                jQuery('html, body').animate({
                    scrollTop: jQuery(".woocommerce-error").offset().top - 20
                }, 300);
            } else {
                allowEditCombine(false);
                jQuery('.combined-feedback').html(response.orders);
                jQuery('.combine-reminders').replaceWith(response.reminders);
                jQuery( document.body ).trigger( 'update_checkout', { update_shipping_method: false } );
            }
        },
        error: function(err) {
            console.log(err);
        },
    });
}

function cancelCombine() {
    jQuery('.combine-form').block({message: null, overlayCSS: { background: '#fff', opacity: 0.6 }});
    jQuery.ajax({
        type : "post",
        url : combine.ajaxurl,
        dataType : "json",
        data : {
            action : 'cancel_combine',
            security : combine.cancelCombineNonce,
        },
        success: function(response) {
            jQuery( '.woocommerce-error, .woocommerce-message' ).remove();
            jQuery('.combine-form').unblock();

            if (response && response.notice) {
                jQuery('.combine-form').before(response.notice);
            }
            allowEditCombine(true);
            jQuery( document.body ).trigger( 'update_checkout', { update_shipping_method: false } );
        },
        error: function(err) {
            console.log(err);
        },
    });
}

function showLogin(e) {
    e.preventDefault();
    jQuery( 'form.login, form.woocommerce-form--login' ).slideDown();
    jQuery('html, body').animate({
        scrollTop: jQuery('form.login, form.woocommerce-form--login').offset().top - 20
    }, 800);
}
