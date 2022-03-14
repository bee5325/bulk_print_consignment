function addBackorderHistory(e) {
    e.preventDefault();
    const cell = jQuery(e.target).closest('td.action-col');
    const productRow = jQuery(e.target).closest('tr.product-row');
    const addValue = cell.find('.add-history-input').val();
    const productID = cell.find('.add-history-button').attr('productid');
    productRow.children('td').block({message: null, overlayCSS: { background: '#fff', opacity: 0.6 }});
	jQuery.ajax({
        type : "post",
        url : bo.ajaxurl,
		dataType: 'json',
        data : {
			action: 'bo_add_backorder_history',
            product_id: productID,
            amount: addValue
		},
        success: function(response) {
            cell.find('table').remove()
            cell.append(response.history);
            cell.find('.add-history-input').val('');
            checkRowEnough(productRow);
            productRow.children('td').unblock();
        },
		error: function(err) {
            console.log('error');
			console.log(err);
		}
    });
}

function removeBackorderHistory(e) {
    e.preventDefault();
    const cell = jQuery(e.target).closest('td.action-col');
    const productRow = jQuery(e.target).closest('tr.product-row');
    const productID = jQuery(e.target).attr('productid');
    const index = jQuery(e.target).attr('idx');
    productRow.children('td').block({message: null, overlayCSS: { background: '#fff', opacity: 0.6 }});
	jQuery.ajax({
        type : "post",
        url : bo.ajaxurl,
		dataType: 'json',
        data : {
			action: 'bo_remove_backorder_history',
            product_id: productID,
            index
		},
        success: function(response) {
            cell.find('table').remove();
            cell.append(response.history);
            cell.closest('tr').find('.quantity-col').html(response.stock);
            checkRowEnough(productRow);
            productRow.children('td').unblock();
        },
		error: function(err) {
            console.log('error');
			console.log(err);
		}
    });
}

function markCompleteBackorderHistory(e) {
    e.preventDefault();
    const cell = jQuery(e.target).closest('td.action-col');
    const productRow = jQuery(e.target).closest('tr.product-row');
    const received = jQuery(e.target).closest('tr.history-row').find('.update-history-input').val();
    const productID = jQuery(e.target).attr('productid');
    const index = jQuery(e.target).attr('idx');
    productRow.children('td').block({message: null, overlayCSS: { background: '#fff', opacity: 0.6 }});
	jQuery.ajax({
        type : "post",
        url : bo.ajaxurl,
		dataType: 'json',
        data : {
			action: 'bo_mark_complete_backorder_history',
            product_id: productID,
            index
		},
        success: function(response) {
            cell.find('table').remove();
            cell.append(response.history);
            checkRowEnough(productRow);
            productRow.children('td').unblock();
        },
		error: function(err) {
            console.log('error');
			console.log(err);
		}
    });
}

function markChangedAmount(e) {
    if (e.target.value === e.target.getAttribute('value')) {
        e.target.classList.remove('changed');
    } else {
        e.target.classList.add('changed');
    }

    if (jQuery('.update-history-input.changed').length === 0) {
        jQuery('.update-received-amounts').hide(200);
    } else {
        jQuery('.update-received-amounts').show(200);
    }
}

function updateReceivedAmounts() {
    jQuery(".update-received-amounts button").prop("disabled", true);
    const changed = jQuery('.update-history-input.changed');
    let promises = [];

    for (let input of changed) {
        const cell = jQuery(input).closest('td.action-col');
        const productRow = jQuery(input).closest('tr.product-row');
        const received = jQuery(input).val();
        const productID = jQuery(input).attr('productid');
        const index = jQuery(input).attr('idx');
        productRow.block({message: null, overlayCSS: { background: '#fff', opacity: 0.6 }});
        promises.push(
            jQuery.ajax({
                type : "post",
                url : bo.ajaxurl,
                dataType: 'json',
                data : {
                    action: 'bo_update_received_amount',
                    product_id: productID,
                    index,
                    received
                },
                success: function(response) {
                    cell.find('table').remove();
                    cell.append(response.history);
                    cell.closest('tr').find('.quantity-col').html(response.stock);
                    checkRowEnough(productRow);
                    productRow.unblock();
                },
                error: function(err) {
                    console.log('error');
                    console.log(err);
                }
            })
        );
    }

    jQuery
        .when(...promises)
        .done(function() {
            jQuery(".update-received-amounts").hide(200);
            jQuery(".update-received-amounts button").prop("disabled", false);
        })
        .fail(function(err) {
            jQuery(".update-received-amounts").append(
                jQuery("<strong style='color: red'>Error update received amounts. Please contact IT immediately</strong>")
            );
            console.log(err);
        });
}

function checkRowEnough(row) {
    jQuery(row).removeClass("not-enough");
    jQuery(row).removeClass("not-received");
    jQuery(row).find('.needed-col').html("");

    const stock = parseInt(jQuery(row).find('.quantity-col').html());
    const needed = stock >= 0 ? 0 : stock * -1;

    const notReceived = jQuery(row).find('table.history tr:not(.completed)').map(function() {
        let received = parseInt(jQuery(this).find('.update-history-input').val());
        let ordered = parseInt(jQuery(this).find('.ordered-amount').html());
        return (received > ordered) ? 0 : ordered - received;
    }).get().reduce(function(sum, e) { return sum + e; }, 0);

    if (notReceived < needed) {
        jQuery(row).addClass("not-enough");
        jQuery(row).find('.needed-col').html(needed - notReceived);
    } else if (notReceived > 0) {
        jQuery(row).addClass("not-received");
    }
}

function filterRows() {
    let rowsToShow = "";
    if (jQuery("#filter-needed").prop("checked")) {
        rowsToShow += ":not(.not-enough)";
    }
    if (jQuery("#filter-not-received").prop("checked")) {
        rowsToShow += ":not(.not-received)";
    }

    jQuery(".product-row.hide").removeClass("hide");
    if (rowsToShow !== "") {
        jQuery(`.product-row${rowsToShow}`).addClass("hide");
    }
}

jQuery(document).ready(function() {
    for (var row of jQuery('.product-row')) {
        checkRowEnough(row);
    }

    jQuery(document).on('input', '.update-history-input', markChangedAmount);
    jQuery(document).on('click', '.remove-history-button', removeBackorderHistory);
    jQuery(document).on('click', '.complete-history-button', markCompleteBackorderHistory);
});
