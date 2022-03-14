"use strict"

let emptyCount = 1;
let printedHistory = new Set();
let sessionName = null;
let timeoutHandler = null;
let lastSaveTime = 0;
let serverSaveDisabled = false;

jQuery(document).ready(function() {
  // tab
  jQuery('input[name=bp-tab]').click(function() {
    jQuery('.tab-nav label').removeClass("active");
    jQuery(this).closest('label').addClass("active");
    jQuery('.tab').hide();
    jQuery(`.tab-${this.value}`).show();
  });

  // Get order modes
  jQuery('input[name=get-order-mode]').click(function() {
    jQuery('.get-order-modes label').removeClass("active");
    jQuery(this).closest('label').addClass("active");
    jQuery('#get-order-mode-container').removeClass().addClass(this.value);
  });

  // Resume session
  const savedSession = localStorage.getItem('printlist');
  if (savedSession !== null) {
    jQuery('#previous-session-id option[value=local]').html(`${JSON.parse(savedSession)['id']} (local)`);
  } else {
    jQuery('#previous-session-id option[value=local]').html('No local session found.');
  }

  jQuery(document).on('change', 'input, select', saveSession);
  jQuery(document).on('input', 'input, textarea', saveSession);

});

function saveBpSettings() {
  let allowCombine = jQuery('#allow-combine').prop('checked');
  let prefix = jQuery('#bp-prefix').val();
  jQuery('.status-update').html("");

  jQuery.ajax({
    type : "post",
    url : bp.ajaxurl,
    dataType: 'json',
    data : {
      action: 'bp_consignment_save_settings',
      allowCombine,
      prefix,
    },
    success: function(response) {
      jQuery('.status-update').html("Settings saved");
      bp.prefix = prefix;
    },
    error: function(err) {
      console.log(err);
      if (err.status === 404) {
        jQuery('.status-update').html('Error: ' + err.responseText);
      } else {
        jQuery('.status-update').html('Error');
        console.log(err.responseText);
      }
    }
  });
}

function saveSession() {
  if (sessionName) {
    jQuery('#save-status')
      .removeClass('error')
      .removeClass('disabled')
      .addClass('saving')
      .html('Saving...');
    clearTimeout(timeoutHandler);
    timeoutHandler = setTimeout(async () => {
      savePrintlistLocal();
      // once taken, do not retry saving to server
      if (serverSaveDisabled) {
        jQuery('#save-status').removeClass('saving').addClass('disabled').html('Saved locally');
        return;
      }
      try {
        await savePrintlistAjax();
        jQuery('#save-status')
          .removeClass('saving')
          .html('Saved');
      } catch (err) {
        if (err.message === "session taken") {
          jQuery('#save-status')
            .removeClass('saving')
            .addClass('disabled')
            .html('Someone else is editing. Not saved to server');
        } else {
          jQuery('#save-status').removeClass('saving').addClass('error').html('Error saving');
        }
      }
    },
      2000);
  }
}

function getHistory(first) {
  jQuery('.status-update')[0].innerHTML = 'Processing';

  var month = "";
  if (first) {
    const now = new Date();
    month = `${now.getFullYear().toString().slice(-2)}${('0' + (now.getMonth()+1)).slice(-2)}`;
  } else {
    month = jQuery('#get-history-button').data('month');
  }

  jQuery.ajax({
    type : "get",
    url : bp.ajaxurl,
    dataType: 'json',
    data : {
      action: 'bp_consignment_get_history',
      month
    },
    success: function(response) {
      if (first) {
        jQuery('#wpbody-content .wrap').html(response.wrapper);
      }
      jQuery('.history-table').append(response.body);
      if (response.nextMonth) {
        jQuery('#get-history-button').data('month', response.nextMonth);
        jQuery('.status-update')[0].innerHTML = '';
      } else {
        jQuery('#get-history-button').prop('disabled', true);
        jQuery('.status-update')[0].innerHTML = 'No more history';
      }
    },
    error: function(err) {
      console.log(err);
      if (err.status === 404) {
        jQuery('.status-update')[0].innerHTML = 'Error: ' + err.responseText;
      } else {
        jQuery('.status-update')[0].innerHTML = 'Error';
        console.log(err.responseText);
      }
    }
  });
}

function getOrders() {
  if (jQuery('input[name=get-order-mode]:checked').val() === 'resume-previous-session') {
    let sessionId = jQuery('select#previous-session-id').val();
    if (sessionId === 'local') {
      loadLocalSession();
    } else if (sessionId !== "") {
      loadServerSession(sessionId);
    }
    return;
  }

  var statuses;
  var orderIds;
  jQuery('.status-update')[0].innerHTML = 'Processing';
  if (jQuery('input[name=get-order-mode]:checked').val() === 'by-order-status') {
    statuses = jQuery('input[type=checkbox]:checked').map(function() {
      return this.id.replace('checkbox-', '');
    }).get();
    orderIds = null;
  } else {
    orderIds = splitByLines(jQuery('textarea#get-order-id').val());
    statuses = null;
  }
  const productsListVal = jQuery('#products-list')[0].value;
  var productsList;
  var excludeList = [ 10524, 10855 ].concat(splitByLines(jQuery('#exclude-list').val()).map(Number));
  if (productsListVal !== "") {
    productsList = splitByLines(productsListVal);
  } else {
    productsList = null;
  }
  jQuery.ajax({
    type : "post",
    url : bp.ajaxurl,
    dataType: 'json',
    data : {
      step: 'split_list',
      action: 'bp_consignment_get_processings',
      statuses: statuses,
      order_ids: orderIds,
      products: productsList,
      exclude: excludeList, //exclude defect and mysterious box. Hack for now, to be fixed later
    },
    success: function(response) {
      jQuery('#wpbody-content .wrap').html(response.content);
      updateGetMoreButton(statuses, productsList, excludeList, response.next_page);
      splitListPageInit();
      jQuery('.status-update').html(rowsSummary());
      sessionName = getSessionName(statuses, orderIds, productsList, excludeList);
      jQuery('.session-name').html(sessionName);
      //history.pushState('print', "", 'admin.php?page=bp-consignment?state=split_list');
    },
    error: function(err) {
      console.log(err);
      if (err.status === 404) {
        jQuery('.status-update')[0].innerHTML = 'Error: ' + err.responseText;
      } else {
        jQuery('.status-update')[0].innerHTML = 'Error';
        console.log(err.responseText);
      }
    }
  })
}

function getMoreProcessings(ele) {
  const statuses = jQuery(ele).data('statuses');
  const productsList = jQuery(ele).data('products');
  const excludeList = jQuery(ele).data('exclude');
  const page = jQuery(ele).data('nextPage');
  ajaxGetOrders({statuses: statuses,
    products: productsList,
    exclude: excludeList,
    paged: page});
}

function addEmptyOrder() {
  var emptyRow = jQuery('.row-id-empty-0').clone();
  emptyRow.addClass('row-id-empty-' + emptyCount).removeClass('row-id-empty-0');
  emptyRow.find('label[for=ship-checkbox-empty-0]').attr('for', 'ship-checkbox-empty-' + emptyCount);
  emptyRow.find('label[for=reserve-checkbox-empty-0]').attr('for', 'reserve-checkbox-empty-' + emptyCount);
  emptyRow.find('#ship-checkbox-empty-0').attr('id', 'ship-checkbox-empty-' + emptyCount);
  emptyRow.find('#reserve-checkbox-empty-0').attr('id', 'reserve-checkbox-empty-' + emptyCount);
  jQuery('.print-list-table').append(jQuery("<tbody>").append(emptyRow));
  splitListPageInit();
  emptyCount++;
}

function addOrders() {
  const orderIds = splitByLines(jQuery('textarea#get-order-id').val());
  ajaxGetOrders({order_ids: orderIds});
}

function rowsSummary() {
  const rowsCount = jQuery('.row-order').length - 1; // -1 for empty row
  var summary;
  if (!jQuery('#get-more-button').prop('disabled')) {
    summary = rowsCount + ' orders displayed';
  } else {
    summary = rowsCount + ' orders displayed. No more order found';
  }
  return summary;
}

function showHideOrders(ele) {
  if (ele.checked) {
    for (let tbodyEle of jQuery(".print-list-table > tbody")) {
      let tbody = jQuery(tbodyEle);
      if (!tbody.hasClass("combined-orders")) {
        tbody.find('.disabled, .done').addClass('hide');
      } else {
        if (tbody.find('.row-order:not(.disabled):not(.done)').length === 0) {
          tbody.find('.row-order').addClass('hide');
        }
      }
    }
  } else {
    jQuery('.hide').removeClass('hide');
  }
  ele.scrollIntoView();
}

function toTop() {
  jQuery('html, body').animate({
    scrollTop: jQuery(".page-top").offset().top - 50
  }, 300);
}

function toBottom() {
  jQuery('html, body').animate({
    scrollTop: jQuery(".page-bottom").offset().top + jQuery(".page-bottom").height()
  }, 300);
}

function getSessionName(statuses, orderIds, products, exclude) {
  const now = new Date();
  const t = `${now.getFullYear().toString().slice(-2)}${('0' + (now.getMonth()+1)).slice(-2)}${('0' + now.getDate()).slice(-2)}_${('0' + now.getHours()).slice(-2)}${('0' + now.getMinutes()).slice(-2)}_`;
  const s = statuses ? statuses.map(e => e.slice(3)).join("_") : "";
  const i = orderIds ? "ids" : "";
  return t + s + i;
}

function ajaxGetOrders(param) {
  jQuery('.status-update')[0].innerHTML = 'Processing';
  jQuery.ajax({
    type : "post",
    url : bp.ajaxurl,
    dataType : 'json',
    data : {
      step: 'split_list',
      action: 'bp_consignment_more_processings',
      ...param
    },
    success: function(response) {
      // Check for unique order ID before continue
      const existingOrders = jQuery('.row-order').map(function() {
        const orderId = /row-id-\d+/.exec(this.className);
        if (orderId) {
          return orderId[0];
        }
      }).get();
      var newOrders = jQuery(response.content);
      for (var orderClass of existingOrders) {
        newOrders.find("." + orderClass).remove();
      }
      jQuery('.print-list-table').append(newOrders);
      if ('statuses' in param) {
        updateGetMoreButton(param.statuses, param.productsList, param.excludeList, response.next_page);
      }

      // Remove tbodys without any order id
      var tbodys = jQuery(".print-list-table > tbody");
      for (var tbody of tbodys) {
        var foundOrderId = false;
        for (var row of jQuery(tbody).find(".row-order")) {
          if (/row-id-/.test(row.className)) {
            foundOrderId = true;
            break;
          }
        }
        if (!foundOrderId) {
          tbody.remove();
        }
      }

      splitListPageInit();
      saveSession();
      jQuery('.status-update')[0].innerHTML = rowsSummary();
      //history.pushState('print', "", 'admin.php?page=bp-consignment?state=split_list');
    },
    error: function(err) {
      if (err.status === 404) {
        jQuery('.status-update')[0].innerHTML = 'Error: ' + err.responseText;
      } else {
        jQuery('.status-update')[0].innerHTML = 'Error';
        console.log(err.responseText);
      }
    }
  });
}

function splitListPageInit() {
  jQuery('.print-list-table input[type=checkbox]').unbind('change');
  jQuery('.ship-checkbox').change(function() {
    const thisRow = jQuery(this).closest('tr')[0];
    const reserveCheckbox = jQuery(thisRow).find('.reserve-checkbox');
    if (this.checked) {
      thisRow.classList.add('row-ship');
      thisRow.classList.remove('disabled');
      // reserve all items
      jQuery(thisRow).find('.order-items tr:not(.reserve-done) .reserve-item-checkbox').prop('checked', true);
      jQuery(thisRow).find('.order-items tr:not(.reserve-done) .reserve-item-count').map(function() { this.value = this.max; });
      // default to shipped, default process order
      jQuery(thisRow).find('select[name=next_order_status]').val('wc-completed');
      reserveCheckbox.prop('checked', true);
    } else {
      thisRow.classList.remove('row-ship');
      // revert reserve to default values
      jQuery(thisRow).find('.order-items tr:not(.reserve-done) .reserve-item-checkbox:not(.default-reserve)').prop('checked', false);
      jQuery(thisRow).find('.order-items tr:not(.reserve-done) .reserve-item-count').map(function() { this.value = this.min; });
      if (!reserveCheckbox.prop('checked')) {
        thisRow.classList.add('disabled');
      }
    }
    reserveCheckbox.change();
    jQuery(thisRow).find('select[name=next_order_status]').change();
    updateGlobalCheckbox('#print-all', '.ship-checkbox');
    updateGlobalCheckbox('#process-all', '.reserve-checkbox');
  });
  jQuery('.reserve-checkbox').change(function() {
    const thisRow = jQuery(this).closest('tr')[0];
    const shipCheckbox = jQuery(thisRow).find('.ship-checkbox');
    if (this.checked) {
      thisRow.classList.add('row-process');
      thisRow.classList.remove('disabled');
    } else {
      thisRow.classList.remove('row-process');
      if (!shipCheckbox.prop('checked')) {
        thisRow.classList.add('disabled');
      }
    }
    jQuery(thisRow).find('select[name=next_order_status]').change();
    updateGlobalCheckbox('#print-all', '.ship-checkbox');
    updateGlobalCheckbox('#process-all', '.reserve-checkbox');
  });
  jQuery('select[name=next_order_status]').change(function() {
    const thisRow = jQuery(this).closest('tr')[0];
    if (thisRow.classList.contains('row-process')) {
      jQuery(thisRow).find('select[name=next_order_status]').prop('disabled', false);
      jQuery(thisRow).find('select[name=tracking_provider]').prop('disabled', false);
      jQuery(thisRow).find('input[name=tracking_number]').prop('disabled', false);
    } else {
      jQuery(thisRow).find('select[name=next_order_status]').prop('disabled', true);
      jQuery(thisRow).find('select[name=tracking_provider]').prop('disabled', true);
      jQuery(thisRow).find('input[name=tracking_number]').prop('disabled', true);
    }
  });
  jQuery('#print-all').change(function() {
    if (jQuery(this).prop('checked') === true) {
      jQuery('.row-order:not(.done):not(.hide):not(.row-id-empty-0) .ship-checkbox:not(:checked)').click();
    } else {
      jQuery('.row-order:not(.done):not(.hide):not(.row-id-empty-0) .ship-checkbox:checked').click();
    }
  });
  jQuery('#process-all').change(function() {
    if (jQuery(this).prop('checked') === true) {
      jQuery('.row-order:not(.done):not(.hide):not(.row-id-empty-0) .reserve-checkbox:not(:checked)').click();
    } else {
      jQuery('.row-order:not(.done):not(.hide):not(.row-id-empty-0) .reserve-checkbox:checked').click();
    }
  });

  // jQuery('.refresh-row').click(function() {
  //     const thisRow = jQuery(this).closest('tr')[0];
  // });
  jQuery('.remove-row').click(function(eve) {
    eve.preventDefault();
    const thisRow = jQuery(this).closest('tr');
    const thisTBody = thisRow.closest('tbody');
    thisRow.remove();
    if (thisTBody.children('tr:not(.row-combine-others)').length === 0) {
      thisTBody.remove();
    }
  });
}

function updateGlobalCheckbox(globalCheckbox, childrenCheckbox) {
  if (jQuery(childrenCheckbox).filter(':not([id$=empty-0])').get().every((ele) => ele.checked)) {
    jQuery(globalCheckbox).prop("checked", true);
  } else {
    jQuery(globalCheckbox).prop("checked", false);
  }
}

function updateGetMoreButton(statuses, products, exclude, nextPage) {
  jQuery('#get-more-button').data('statuses', statuses);
  jQuery('#get-more-button').data('products', products);
  jQuery('#get-more-button').data('exclude', exclude);
  if (nextPage !== null) {
    jQuery('#get-more-button').data('nextPage', nextPage);
  } else {
    jQuery('#get-more-button').prop('disabled', true);
  }
}

function updateOrderStatuses() {
  let update = confirm("Please make sure that the information are correct before update");
  if (update !== true) {
    return;
  }
  // TODO: in case update status happened in between get more, value of "paged" changed. It causes some orders to be skipped
  // Should get new paged value from server
  jQuery('#update-status-btn').prop('disabled', true);
  jQuery('.status-update')[0].innerHTML = 'Processing';
  const rows = jQuery(".row-process:not(.disabled):not(.done)");
  if (rows.length === 0) {
    alert('No valid row selected');
    return;
  }
  var orders = {};
  for (var row of rows) {
    const details = extractRow(row);
    const currentStatus = jQuery(row).find(".current-status")[0].innerHTML;
    var itemsReserved = {};
    for (var item in details['items']) {
      if (details['items'][item]['reserved']) {
        itemsReserved[item] = details['items'][item]['reserved-count'];
      }
    }
    orders[details['id']] = {shipping_provider: details['shipping-provider'],
      tracking_number: details['tracking-number'],
      current_status: currentStatus,
      next_status: details['next-status'],
      items_reserved: itemsReserved,
      order_notes: details['packlist-remark'],
    };
  }
  const shippingDate = jQuery("#ship-date").val();
  jQuery.ajax({
    type : "post",
    url : bp.ajaxurl,
    dataType : 'json',
    data : {
      action: 'bp_consignment_update_order_statuses',
      orders: orders,
      shipping_date: shippingDate
    },
    success: function(response) {
      console.log(response);
      for (var row of rows) {
        row.classList.add('done');
        row.classList.remove('row-ship');
        row.classList.remove('row-process');
        jQuery(row).find('input, textarea, checkbox, select').prop('disabled', true);
      }
      saveSession();
      jQuery('.status-update')[0].innerHTML = 'Updated ' + rows.length + ' rows.';
      jQuery('#update-status-btn').prop('disabled', false);
    },
    error: function(err) {
      jQuery('.status-update')[0].innerHTML = 'Error';
      console.log(err);
      console.log(err.responseText);
      jQuery('#update-status-btn').prop('disabled', false);
    }
  });
}

function splitByLines(input) {
  let trimmed = input.split("\n").map(function(val) {
    return val.trim();
  });
  return trimmed.filter(function(val) { return val; });
}

function verifyAddressLineLength(addr) {
  const lines = addr.split("\n");
  console.log(lines);
}

function printElem(elem, title) {
  var mywindow = window.open('');

  mywindow.document.write('<html><head><title>' + title  + '</title>');
  mywindow.document.write('<link rel="stylesheet" href="' + bp.printcss + '"></head><body>');
  mywindow.document.write('</body></html>');
  jQuery(mywindow.document.body).append(elem);

  mywindow.document.close(); // necessary for IE >= 10
  mywindow.focus(); // necessary for IE >= 10*/

  setTimeout(function() {mywindow.print();}, 400);

  return true
}

function generateConsignmentDiv(details) {
  const $ = jQuery;
  const today = new Date();
  const id = details.id.startsWith('empty') ? '' : details['id'];
  const printDiv = $('<div class="print-page"></div>').append(
    $(`<div class="id">${bp.prefix ? bp.prefix + "-" : ""}${id}</div>`),
    $('<div class="receiver-address">' + details['receiver-address'] + '</div>'),
    $('<div class="lower-part">').append(
      $('<div class="consignment-remark">' + details['consignment-remark'] + '</div>'),
      $('<div class="name-phone"></div').append(
        $('<div class="receiver-name">' + details['receiver-name'] + '</div>'),
        $('<div class="receiver-phone">' + details['receiver-phone'] + '</div>'),
      )
    ),
    $('<div class="date">' + `${today.getDate()}/${today.getMonth()+1}/${today.getFullYear()}` + '</div>')
  );
  return printDiv[0].outerHTML;
}

function generatePackRow(row) {
  const details = jQuery(row);
  if (details.hasClass("row-combine-others")) {
    return jQuery(`<tr><td colspan="2">${details.children()[0].innerHTML}</td></tr>`);
  }

  const id = /row-id-(\S+)/.exec(jQuery(row).attr('class')) ? /row-id-(\S+)/.exec(jQuery(row).attr('class'))[1] : null;
  const receiver = details.find('.receiver-name-textarea')[0].value;
  const remark = details.find('.pack-list-remark')[0].value;
  var items;
  if (remark !== "") {
    items = jQuery('<div class="pack-list-remark">' + remark + '</div>').append(details.find('.order-items').clone());
  } else {
    items = details.find('.order-items').clone();
  }
  items.find('input[type=checkbox]:checked').replaceWith('\u2714');
  return jQuery('<tr></tr>').append(jQuery('<td></td>').append(id + '<br>' + receiver),
    jQuery('<td></td>').append(items));
}

function printConsignments() {
  const printRows = jQuery(".row-ship:not(.disabled):not(.done)");
  var printDivs = jQuery('<div></div>');
  var printedOrders = [];

  if (printRows.length === 0) {
    alert("No row selected");
    return;
  }

  for (var tbodyEle of jQuery(".print-list-table > tbody")) {
    const tbody = jQuery(tbodyEle);
    // if not combine
    if (!tbody.hasClass("combined-orders")) {
      for (var row of tbody.find(".row-ship:not(.disabled):not(.done)")) {
        const extracted = extractRow(row);
        printedOrders.push(extracted['id']);
        printDivs.append(generateConsignmentDiv(extracted));
      }
    } else {
      const shipCombined = tbody.find(".row-ship:not(.disabled):not(.done)");
      var combinedIds = [];
      if (shipCombined.length !== 0) {
        var extracted = extractRow(shipCombined[0]);
        for (var combinedOrder of tbody.find(".row-order:not(.row-combine-others)")) {
          const extractedCombined = extractRow(combinedOrder);
          combinedIds.push(extractedCombined['id']);
        }
        printedOrders = printedOrders.concat(combinedIds);
        extracted['id'] = combinedIds.join(" ");
        printDivs.append(generateConsignmentDiv(extracted));
      }
    }
  }
  const newPrinted = printedOrders.filter(x => !printedHistory.has(x));
  if (newPrinted.length > 0) {
    ajaxRecordPrinted(newPrinted);
  }
  printedHistory = new Set([...printedHistory, ...newPrinted]);
  printElem(printDivs[0].innerHTML, 'Consignments');
}

function printInvoice() {
  const today = new Date();
  const todayString = `${today.getDate()}/${today.getMonth()+1}/${today.getFullYear()}`;
  var invoicePage = jQuery('<div></div>');
  var invoiceNeeded = false;

  for (var tbody of jQuery('.print-list-table > tbody')) {
    const orders = jQuery(tbody).find('.row-ship:not(.disabled):not(.done)');
    if (orders.length === 0) {
      continue;
    }

    for (var row of orders) {
      const order = extractRow(row);
      if (!order['receiver-address'].match(/(Sarawak|Sabah|Singapore)/i)) {
        continue;
      }

      const invoice = jQuery('.invoice-template .commercial-invoice-page').clone();

      invoice.find('.tracking-number').html(order['tracking-number']);
      invoice.find('.tracking-date').html(todayString);
      invoice.find('.receiver-name').html(order['receiver-name']);
      invoice.find('.receiver-address').html(order['receiver-address'].replace(/\n/g, "<br />"));
      invoice.find('.receiver-number').html(order['receiver-phone']);
      invoicePage.append(invoice);
      invoiceNeeded = true;

      if (jQuery(tbody).hasClass('combined-orders')) {
        const combinedOrders = jQuery(tbody).find('.row-ship:not(.disabled):not(.done)').get().map(extractRow);
        const itemsCount = combinedOrders.reduce((sum, ele) => sum + parseInt(ele['items-count']), 0);
        const itemsTotal = combinedOrders.reduce((sum, ele) => sum + parseInt(ele['items-total']), 0);
        invoice.find('.quantity').html(itemsCount);
        invoice.find('.amount').html(itemsTotal);
        break;
      } else {
        invoice.find('.quantity').html(order['items-count']);
        invoice.find('.amount').html(order['items-total']);
      }
    }
  }

  if (invoiceNeeded) {
    printElem(invoicePage, 'Invoice');
  } else {
    alert("Invoice not needed.");
  }
}

function printPackingList() {
  const today = new Date();
  var shipTable = jQuery('<table class="pack-table">').append('<col style="width: 30%">', '<col style="width: 70%">');
  var reserveTable = jQuery('<table class="pack-table">').append('<col style="width: 30%">', '<col style="width: 70%">');
  var printDiv = jQuery('<div class="pack-list">').append(
    '<h1>' + `${today.getDate()}/${today.getMonth()+1}/${today.getFullYear()}` + '</h1>',
    '<h1>Ship list</h1>',
    shipTable,
    '<h1>Reserve List</h1>',
    reserveTable);
  const shipRows = jQuery(".row-ship:not(.disabled):not(.done)");
  const reserveRows = jQuery(".row-process:not(.disabled):not(.done)");
  if (shipRows.length === 0 && reserveRows.length === 0) {
    alert("No row selected");
    return;
  }

  for (var tbodyEle of jQuery(".print-list-table > tbody")) {
    const tbody = jQuery(tbodyEle);
    if (!tbody.hasClass("combined-orders")) {
      // if not combine
      // add row to ship / reserve table
      const shipRows = tbody.find(".row-ship:not(.disabled):not(.done)");
      const reserveRows = tbody.find(".row-process:not(.row-ship):not(.disabled):not(.done)");
      var shipTbody = jQuery("<tbody></tbody>");
      var reserveTbody = jQuery("<tbody></tbody>");
      for (var row of shipRows) {
        shipTbody.append(generatePackRow(row));
      }
      for (var row of reserveRows) {
        // Only print those that have something to reserve
        const reserveItems = jQuery(row).find(".reserve-item-checkbox:checked");
        if (reserveItems.length > 0) {
          reserveTbody.append(generatePackRow(row));
        }
      }
      shipTable.append(shipTbody);
      reserveTable.append(reserveTbody);
    } else {
      // else if combine and to ship / reserve
      const shipCombined = (tbody.find(".row-ship:not(.disabled):not(.done)").length !== 0);
      const reserveCombined = (tbody.find(".row-process:not(.row-ship):not(.disabled):not(.done) .reserve-item-checkbox:checked").length !== 0);
      if (shipCombined || reserveCombined) {
        const spacingTbody = jQuery("<tbody class='spacing'></tbody>");
        //   create a tbody for all orders
        var combinedTbody = jQuery("<tbody class='combined-orders'></tbody>");
        const combinedRows = tbody.find(".row-order");
        for (var row of combinedRows) {
          combinedTbody.append(generatePackRow(row));
        }
        if (shipCombined) {
          shipTable.append(combinedTbody);
          shipTable.append(spacingTbody);
        } else if (reserveCombined) {
          reserveTable.append(combinedTbody);
          reserveTable.append(spacingTbody);
        }
      }
    }
  }
  printElem(printDiv, 'Packing list');
}

function extractRow(row) {
  const toShip = jQuery(row).find('.ship-checkbox').prop('checked');
  const toReserve = jQuery(row).find('.reserve-checkbox').prop('checked');
  const nextStatus = jQuery(row).find('select[name=next_order_status]').val();
  const orderId = /row-id-(\S+)/.exec(jQuery(row).attr('class')) ? /row-id-(\S+)/.exec(jQuery(row).attr('class'))[1] : null;
  const receiverName = jQuery(row).find('.receiver-name-textarea').val();
  const receiverPhone = jQuery(row).find('.receiver-phone-textarea').val();
  const receiverAddress = jQuery(row).find('.receiver-address-textarea').val();
  const consignmentRemark = jQuery(row).find('.consignment-remark').val();
  const packlistRemark = jQuery(row).find('.pack-list-remark').val();
  const orderItems = jQuery(row).find('.order-items tr.product-item');
  const shippingProvider = jQuery(row).find('select[name=tracking_provider]').val();
  const trackingNumber = jQuery(row).find('input[name=tracking_number]').val();
  const itemsCount = jQuery(row).find('.items-count').val();
  const itemsTotal = jQuery(row).find('.items-total').val();
  var items = {};
  for (var item of orderItems) {
    const itemRow = jQuery(item);
    const itemId = itemRow.find('.reserve-item-col').attr('id');
    const reserved = itemRow.find('.reserve-item-checkbox').prop('checked');
    const reservedCount = itemRow.find('.reserve-item-count').length ? itemRow.find('.reserve-item-count').val() : "1";
    items[itemId] = {'reserved': reserved, 'reserved-count': reservedCount};
  }

  return {'to-ship': toShip,
    'to-reserve': toReserve,
    'next-status': nextStatus,
    'id': orderId,
    'receiver-name': receiverName,
    'receiver-phone': receiverPhone,
    'receiver-address': receiverAddress,
    'consignment-remark': consignmentRemark,
    'packlist-remark': packlistRemark,
    'shipping-provider': shippingProvider,
    'tracking-number': trackingNumber,
    'items-count': itemsCount,
    'items-total': itemsTotal,
    'items': items
  };
}

function ajaxRecordPrinted(orders) {
  const prevInfo = jQuery('.status-update')[0].innerHTML;
  jQuery('.status-update')[0].innerHTML = 'Processing';
  jQuery.ajax({
    type : "post",
    url : bp.ajaxurl,
    dataType : 'json',
    data : {
      action: 'bp_record_printed_consignment',
      orders: orders,
    },
    success: function(response) {
      jQuery('.status-update')[0].innerHTML = 'Recorded ' + orders.length + ' printed orders';
      setTimeout(function() { jQuery('.status-update')[0].innerHTML = prevInfo; }, 2000);
    },

  });
}

function tryFillTracking(e, startElement) {
  e.preventDefault();
  const rowGroups = jQuery(".print-list-table tbody");

  const thisTracking = jQuery(startElement).prev().val();
  const startOrder = jQuery(startElement).closest(".row-order")[0];
  var trackingNumber = {prefix: thisTracking.slice(0, -6),
    number: parseInt(thisTracking.slice(-6)),
    string: function() { return `${this.prefix}${('00000'+this.number).toString().slice(-6)}` }};
  var started = false;

  rowGroups.each(function(tIdx, tbody) {
    const shipRows = jQuery(tbody).find(".row-ship");
    if (shipRows.length === 0) {
      return;
    }

    shipRows.each(function(rIdx, row) {
      if (row == startOrder) {
        started = true;
      }
      if (!started) {
        return;
      }
      jQuery(row).find("input[name=tracking_number]").val(trackingNumber.string());
      if (!tbody.classList.contains("combined-orders")) {
        trackingNumber.number++;
      }
    });
    if (started && tbody.classList.contains("combined-orders")) {
      trackingNumber.number++;
    }
  });
  saveSession();
}

async function savePrintlistAjax() {
  const html = jQuery('.print-list-table')[0].outerHTML;
  const states = extractInputs();
  const getMoreButton = jQuery('#get-more-button');
  const getMore = {
    statuses: jQuery(getMoreButton).data('statuses'),
    products: jQuery(getMoreButton).data('products'),
    exclude: jQuery(getMoreButton).data('exclude'),
    nextPage: jQuery(getMoreButton).data('nextPage'),
  };

  let error = null;

  await jQuery.ajax({
    type : "post",
    url : bp.ajaxurl,
    data : {
      data: JSON.stringify({
        sessionName,
        html,
        states,
        getMore,
      }),
      last_save: lastSaveTime,
      action: 'bp_consignment_save_printlist',
    },
    success: function(response) {
      if (response === "session taken") {
        serverSaveDisabled = true;
        error = new Error("session taken");
      } else {
        lastSaveTime = parseInt(response);
      }
    },
    error: function(err) {
      console.log("ERROR", err);
    }
  });

  if (error) {
    throw error;
  }
}

function savePrintlistLocal() {
  const getMoreButton = jQuery('#get-more-button');
  let saveObject = {
    id: sessionName,
    html: jQuery('.print-list-table')[0].outerHTML,
    states: extractInputs(),
    getMore: {
      statuses: jQuery(getMoreButton).data('statuses'),
      products: jQuery(getMoreButton).data('products'),
      exclude: jQuery(getMoreButton).data('exclude'),
      nextPage: jQuery(getMoreButton).data('nextPage'),
    },
  };
  localStorage.setItem("printlist", JSON.stringify(saveObject));
}

function extractInputs() {
  return jQuery("#wpbody-content :input").map((_, e) => {
    if (e.type === "submit") return;
    if (e.type === "checkbox") return {id: e.id, value: e.checked};
    return {id: e.id, value: e.value};
  }).get();
}

function loadLocalSession() {
  jQuery.ajax({
    type : "post",
    url : bp.ajaxurl,
    dataType: 'json',
    data : {
      step: 'split_list',
      action: 'bp_consignment_get_processings',
    },
    success : function(response) {
      const saved = JSON.parse(localStorage.getItem("printlist"));

      jQuery('#wpbody-content .wrap').replaceWith(response.content);
      jQuery(".print-list-table").replaceWith(saved['html']);
      saved['states'].forEach((e) => {
        const target = jQuery(`#${e.id}`)[0];
        if (typeof(target) === "undefined") return;
        if (target.type === "checkbox") target.checked = e.value;
        else target.value = e.value;
      });
      if (saved['getMore'] !== undefined) {
        let getMoreButton = jQuery('#get-more-button');
        Object.entries(saved['getMore']).forEach(([key, val]) => {
          getMoreButton.data(key, val);
        });
      }
      splitListPageInit();
      sessionName = saved['id'];
      jQuery('.session-name').html(`${sessionName} (Resumed)`);
      // jQuery('#get-more-button').prop('disabled', true);
    }
  });
}

async function loadServerSession(sessionId) {
  await jQuery.ajax({
    type : "post",
    url : bp.ajaxurl,
    dataType: 'json',
    data : {
      step: 'split_list',
      action: 'bp_consignment_get_processings',
    },
    success : function(response) {
      jQuery('#wpbody-content .wrap').replaceWith(response.content);
    }
  });
  jQuery.ajax({
    type : "get",
    url : bp.ajaxurl,
    dataType: 'json',
    data : {
      session: sessionId,
      action: 'bp_consignment_get_saved_session',
    },
    success : function(saved) {
      jQuery(".print-list-table").replaceWith(saved['html']);
      saved['states'].forEach((e) => {
        const target = jQuery(`#${e.id}`)[0];
        if (typeof(target) === "undefined") return;
        if (target.type === "checkbox") target.checked = e.value;
        else target.value = e.value;
      });
      if (saved['getMore'] !== undefined) {
        let getMoreButton = jQuery('#get-more-button');
        Object.entries(saved['getMore']).forEach(([key, val]) => {
          getMoreButton.data(key, val);
        });
      }
      splitListPageInit();
      sessionName = sessionId;
      lastSaveTime = saved['saveTime'] ? saved['saveTime'] : Date.now() / 1000;
      jQuery('.session-name').html(`${sessionName} (Resumed)`);
      // jQuery('#get-more-button').prop('disabled', true);
    }
  });
}

function searchHistory() {
  const searchValue = jQuery('#search-history').val().toUpperCase();
  var timestampRow = null;
  for (var row of jQuery('.history-table > tbody > tr')) {
    if (row.classList.contains("timestamp-row")) {
      row.style.display = "none";
      timestampRow = row;
      continue;
    }
    for (var cell of jQuery(row).children('td')) {
      if (cell.innerHTML.toUpperCase().includes(searchValue)) {
        row.style.display = "";
        timestampRow.style.display = "";
        break
      } else {
        row.style.display = "none";
      }
    }
  }
}

function getTrackingFromPD() {
  const providers = {
    citylink: "citylink",
    dhl: "dhl-ms"
  };

  let results = [];
  jQuery('.status-update').html('Getting tracking numbers from ParcelDaily...');
  jQuery.ajax({
    type : "get",
    url : "https://api.parceldaily.com/v1/partner/orders",
    dataType: 'json',
    headers : {
      token: "edc2351c-a97a-4136-92f6-64a5f0fcfad9",
      merchantid: "f6H3rBtJzr",
    },
    success: function(response) {
      let notUpdated = 0;
      let orders = response.orders.reduce((arr, r) => {
        let orderId = /\[(\S+)\]/.exec(r.receiver)[1];
        arr[orderId] = { connote: r.consignNote, serviceProvider: r.serviceProvider };
        return arr;
      }, {});
      jQuery('.row-ship:not(.disabled):not(.done)').map((_, r) => {
        let row = extractRow(r);
        if (orders[row.id] !== undefined) {
          jQuery(r).find("[name=tracking_number]").val(orders[row.id].connote);
          jQuery(r).find("[name=tracking_provider]").val(providers[orders[row.id].serviceProvider]);
        } else {
          console.log(row.id, undefined);
          notUpdated++;
        }
      });
      jQuery('.status-update').html(`Done (${notUpdated} orders not updated)`);
    },
    error: function(err) {
      console.log(err);
      jQuery('.status-update').html('Error');
    }
  });
}
