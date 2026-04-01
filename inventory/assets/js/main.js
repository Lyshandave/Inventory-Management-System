/**
 * main.js — Shoes Inventory Management System
 * Plain ES5-compatible JavaScript. No frameworks. No inline styles or handlers.
 */

'use strict';

/* =============================================================================
   CONSTANTS
   ============================================================================= */

var BEST_SELLER_BADGES = {
    1: { cls: 'bs-gold',   text: '🥇 Best Seller' },
    2: { cls: 'bs-silver', text: '🥈 Top 2'       },
    3: { cls: 'bs-bronze', text: '🥉 Top 3'       },
    4: { cls: 'bs-top',    text: '⭐ Top 4'       },
    5: { cls: 'bs-top',    text: '⭐ Top 5'       },
};

/* =============================================================================
   APPLICATION STATE
   ============================================================================= */

var App = {
    activeModal:    null,
    productId:      null,
    productPrice:   0,
    productStock:   0,
    refundId:       null,
    refundPrice:    0,
    tableView:      'active',
    salesRange:     'all',
    salesAjaxUrl:   '',
    sellPending:    false,
    archivePending: false,
    restorePending: {},
    toastTimer:     null,
};

/* =============================================================================
   UTILITIES
   ============================================================================= */

function byId(id) {
    return document.getElementById(id);
}

function setText(id, value) {
    var el = byId(id);
    if (el) el.textContent = value;
}

function escHtml(str) {
    var d = document.createElement('div');
    d.appendChild(document.createTextNode(str || ''));
    return d.innerHTML;
}

function escAttr(str) {
    return (str || '').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

function fmtCurrency(amount) {
    return '\u20b1' + parseFloat(amount || 0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

function fmtCurrencyCompact(n) {
    n = parseFloat(n || 0);
    var sign = n < 0 ? '-' : '';
    var abs  = Math.abs(n);
    if (abs >= 1e9) return '\u20b1' + sign + (abs / 1e9).toFixed(2) + 'B';
    if (abs >= 1e6) return '\u20b1' + sign + (abs / 1e6).toFixed(2) + 'M';
    if (abs >= 1e3) {
        var k = abs / 1e3;
        return '\u20b1' + sign + (k % 1 === 0 ? k.toFixed(0) : k.toFixed(1)) + 'K';
    }
    return '\u20b1' + sign + abs.toFixed(2);
}

function fmtNumber(n) {
    return parseInt(n || 0).toLocaleString();
}

function fmtDate(str) {
    if (!str) return '\u2014';
    var d = new Date((str + '').replace(' ', 'T'));
    return isNaN(d) ? str : d.toLocaleString('en-PH', {
        year: 'numeric', month: 'short', day: 'numeric',
        hour: '2-digit', minute: '2-digit',
    });
}

function getStockStatus(qty, minStock) {
    if (qty <= 0)        return 'out_of_stock';
    if (qty <= minStock) return 'low_stock';
    return 'in_stock';
}

function buildStockBadge(qty, minStock) {
    var status = getStockStatus(qty, minStock);
    var map = {
        in_stock:     { badge: 'badge-success', dot: 'dot-in',  label: 'In Stock'     },
        low_stock:    { badge: 'badge-warning', dot: 'dot-low', label: 'Low Stock'    },
        out_of_stock: { badge: 'badge-danger',  dot: 'dot-out', label: 'Out of Stock' },
    };
    var s = map[status];
    return '<span class="badge ' + s.badge + '">'
         + '<span class="stock-dot ' + s.dot + '"></span>'
         + s.label + '</span>';
}

function buildSaleStatusBadge(status) {
    var classes = {
        'Sold':                 'badge-success',
        'Refunded (Restocked)': 'badge-warning',
        'Refunded (Damaged)':   'badge-danger',
    };
    var cls = classes[status] || 'badge-gray';
    return '<span class="badge ' + cls + '">' + escHtml(status) + '</span>';
}

function setButtonLoading(btn, isLoading, idleHtml) {
    if (!btn) return;
    btn.disabled  = isLoading;
    btn.innerHTML = isLoading ? '<i class="fas fa-spinner fa-spin"></i>' : idleHtml;
}

/* =============================================================================
   FETCH WRAPPER — handles session expiry (401) and access denied (403)
   ============================================================================= */

function apiFetch(url, options) {
    return fetch(url, options).then(function (r) {
        if (r.status === 401) {
            return r.json().then(function (data) {
                showToast('Session Expired', 'Please log in again.', 'warning');
                setTimeout(function () {
                    window.location.href = data.redirect || '../index.php';
                }, 1500);
                return Promise.reject(new Error('session_expired'));
            });
        }
        if (r.status === 403) {
            return r.json().then(function (data) {
                showToast('Access Denied', data.message || 'You do not have permission.', 'error');
                return Promise.reject(new Error('access_denied'));
            });
        }
        return r;
    });
}

/* =============================================================================
   TOAST
   ============================================================================= */

function showToast(title, message, type) {
    var toast = byId('toast');
    if (!toast) return;

    var iconMap = { success: 'fa-check', error: 'fa-times-circle', warning: 'fa-exclamation-triangle' };
    var iconEl  = toast.querySelector('.toast-icon');
    var barEl   = toast.querySelector('.toast-bar');

    setText('toastTitle', title);
    setText('toastText', message);

    iconEl.innerHTML = '<i class="fas ' + (iconMap[type] || 'fa-check') + '"></i>';
    toast.className  = 'toast ' + (type || 'success');
    toast.classList.add('visible');

    barEl.className = 'toast-bar';
    void barEl.offsetWidth; // force reflow to restart animation
    barEl.classList.add('running');

    if (App.toastTimer) clearTimeout(App.toastTimer);
    var duration = (type === 'error' || type === 'warning') ? 4500 : 2500;
    App.toastTimer = setTimeout(hideToast, duration);
}

function hideToast() {
    var toast = byId('toast');
    if (toast) toast.classList.remove('visible');
    if (App.toastTimer) clearTimeout(App.toastTimer);
}

/* =============================================================================
   MODAL
   ============================================================================= */

function openModal(modalId) {
    var modal = byId(modalId);
    if (!modal) return;
    var scrollW = window.innerWidth - document.documentElement.clientWidth;
    document.documentElement.style.setProperty('--sb-w', scrollW + 'px');
    document.body.classList.add('modal-open');
    modal.classList.remove('hidden');
    App.activeModal = modal;
}

function closeModal() {
    if (!App.activeModal) return;
    App.activeModal.classList.add('hidden');
    document.body.classList.remove('modal-open');
    document.documentElement.style.setProperty('--sb-w', '0px');

    // Reset End Shift modal
    var preConfirm    = byId('shiftPreConfirm');
    var summaryBox    = byId('shiftSummaryBox');
    var footerConfirm = byId('endShiftFooterConfirm');
    var footerDone    = byId('endShiftFooterDone');
    var endShiftBtn   = byId('endShiftBtn');
    if (preConfirm)    preConfirm.classList.remove('hidden');
    if (summaryBox)    summaryBox.classList.add('hidden');
    if (footerConfirm) footerConfirm.classList.remove('hidden');
    if (footerDone)    footerDone.classList.add('hidden');
    if (endShiftBtn)   endShiftBtn.disabled = false;

    // Reset Cashier Logout modal
    var logoutChoiceBox  = byId('logoutChoiceBox');
    var logoutSummaryBox = byId('logoutShiftSummaryBox');
    var logoutFooter     = byId('cashierLogoutFooter');
    if (logoutChoiceBox)  logoutChoiceBox.classList.remove('hidden');
    if (logoutSummaryBox) logoutSummaryBox.classList.add('hidden');
    if (logoutFooter) {
        logoutFooter.innerHTML =
            '<button class="btn btn-secondary" data-action="close-modal">Cancel</button>';
    }

    App.activeModal = null;
    App.productId   = null;
}

/* =============================================================================
   PRODUCTS TABLE — filtering and empty state
   ============================================================================= */

function filterTable() {
    var search   = getInputValue('searchInput');
    var category = getInputValue('categoryFilter');
    var brand    = getInputValue('brandFilter');
    var gender   = getInputValue('genderFilter');
    var rows     = document.querySelectorAll('#productsTable tbody tr[data-id]');
    var visible  = 0;

    rows.forEach(function (row) {
        var archived    = row.dataset.archived === '1';
        var matchView   = (App.tableView === 'active'   && !archived)
                       || (App.tableView === 'archived' &&  archived);
        var searchText  = (row.dataset.name     || '') + ' '
                        + (row.dataset.brand    || '') + ' '
                        + (row.dataset.category || '') + ' '
                        + (row.dataset.color    || '');
        var matchSearch = !search || searchText.toLowerCase().indexOf(search) !== -1;
        var matchCat    = !category || (row.dataset.category || '') === category;
        var matchBrand  = !brand    || (row.dataset.brand    || '') === brand;
        var rowGender   = (row.dataset.gender || '').toLowerCase();
        var matchGender = !gender
            || rowGender === gender
            || (gender !== 'unisex' && rowGender === 'unisex');
        var show = matchView && matchSearch && matchCat && matchBrand && matchGender;
        row.classList.toggle('row-hidden', !show);
        if (show) visible++;
    });

    updateEmptyState(visible, !!(search || category || brand || gender));
    updateBrandBar(brand);
    sortTableByBestSeller();
}

function getInputValue(id) {
    var el = byId(id);
    return el ? el.value.toLowerCase().trim() : '';
}

function updateEmptyState(visibleCount, hasFilter) {
    var row = byId('emptyRow');
    if (!row) return;

    if (visibleCount > 0) {
        row.classList.add('hidden');
        return;
    }

    var cfg;
    if (hasFilter) {
        cfg = { icon: 'fa-search',   title: 'No Results',           sub: 'No products match your search or filter.' };
    } else if (App.tableView === 'archived') {
        cfg = { icon: 'fa-box-open', title: 'No Archived Products', sub: 'Archive a product from the Active tab to see it here.' };
    } else {
        cfg = { icon: 'fa-inbox',    title: 'No Active Products',   sub: 'Add your first product to get started.' };
    }

    var iconEl  = row.querySelector('.empty-icon i');
    var titleEl = row.querySelector('.empty-title');
    var subEl   = row.querySelector('.empty-sub');
    if (iconEl)  iconEl.className    = 'fas ' + cfg.icon;
    if (titleEl) titleEl.textContent = cfg.title;
    if (subEl)   subEl.textContent   = cfg.sub;
    row.classList.remove('hidden');
}

function switchView(view) {
    App.tableView = view;
    document.querySelectorAll('[data-action="switch-view"]').forEach(function (tab) {
        var isActive = tab.dataset.view === view;
        tab.classList.toggle('active', isActive);
        tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
    });
    filterTable();
}

function updateBrandBar(brand) {
    var bar = byId('brandStockInfo');
    if (!bar) return;
    if (!brand) { bar.classList.add('hidden'); return; }

    var totalStock = 0, count = 0;
    document.querySelectorAll('#productsTable tbody tr[data-id]').forEach(function (row) {
        if (row.dataset.archived !== '1' && (row.dataset.brand || '') === brand) {
            totalStock += parseInt(row.dataset.quantity) || 0;
            count++;
        }
    });

    bar.innerHTML = '<i class="fas fa-tags"></i> <strong>' + escHtml(brand) + '</strong>'
        + ' \u2014 ' + count + ' product(s) \u00b7 '
        + '<strong>' + fmtNumber(totalStock) + ' units</strong> in stock';
    bar.classList.remove('hidden');
}

/* =============================================================================
   BEST SELLER SORT
   ============================================================================= */

function sortTableByBestSeller() {
    var tbody = document.querySelector('#productsTable tbody');
    if (!tbody) return;

    var rows     = Array.prototype.slice.call(tbody.querySelectorAll('tr[data-id]'));
    var emptyRow = byId('emptyRow');
    var active   = rows.filter(function (r) { return r.dataset.archived !== '1'; });
    var archived = rows.filter(function (r) { return r.dataset.archived === '1'; });

    active.sort(function (a, b) {
        var rankA = parseInt(a.dataset.bsRank) || 999;
        var rankB = parseInt(b.dataset.bsRank) || 999;
        if (rankA !== rankB) return rankA - rankB;
        // Stable tiebreaker: alphabetical by product name (never by stock/quantity)
        var nameA = (a.dataset.name || '').toLowerCase();
        var nameB = (b.dataset.name || '').toLowerCase();
        return nameA < nameB ? -1 : nameA > nameB ? 1 : 0;
    });

    active.forEach(function (r)   { tbody.appendChild(r); });
    archived.forEach(function (r) { tbody.appendChild(r); });
    if (emptyRow) tbody.appendChild(emptyRow);
}

/* =============================================================================
   DASHBOARD STATS REFRESH
   ============================================================================= */

function refreshStats() {
    apiFetch('dashboard_stats.php')
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (!d.success) return;

            setText('dashTotalProducts', fmtNumber(d.totalProducts));
            setText('dashTotalStock',    fmtNumber(d.totalStock));
            setText('dashTotalSales',    fmtNumber(d.totalSales));
            setText('dashTotalRevenue',  fmtCurrencyCompact(d.totalRevenue));
            if (d.totalProfit !== undefined) setText('dashTotalProfit', fmtCurrencyCompact(d.totalProfit));

            var badge = byId('archivedCountBadge');
            if (badge) {
                badge.textContent = d.archivedCount;
                badge.classList.toggle('hidden', d.archivedCount === 0);
            }

            var alertEl  = byId('lowStockAlert');
            var alertTxt = byId('lowStockAlertText');
            if (alertEl && alertTxt) {
                var hasIssue = d.lowStockCount > 0 || d.outOfStock > 0;
                alertEl.classList.toggle('hidden', !hasIssue);
                if (hasIssue) {
                    var msg = '';
                    if (d.outOfStock    > 0) msg += '<strong>' + d.outOfStock    + ' product(s) are out of stock.</strong> ';
                    if (d.lowStockCount > 0) msg += '<strong>' + d.lowStockCount + ' product(s) are running low.</strong> Restock soon.';
                    alertTxt.innerHTML = msg;
                }
            }

            if (d.bestSellerIds) refreshBestSellerBadges(d.bestSellerIds);
        })
        .catch(function () { /* silent — stats refresh is best-effort */ });
}

/* =============================================================================
   PRODUCT ROW — DOM updates after sell / add-stock
   ============================================================================= */

function updateProductRow(productId, newQty) {
    var row = document.querySelector('tr[data-id="' + productId + '"]');
    if (!row) return;

    var minStock         = parseInt(row.dataset.minStock) || 0;
    row.dataset.quantity = newQty;

    var qtyEl = row.querySelector('.stock-qty');
    if (qtyEl) qtyEl.textContent = newQty;

    var statusCell = row.querySelector('.status-cell');
    if (statusCell) statusCell.innerHTML = buildStockBadge(newQty, minStock);

    rebuildActionButtons(row, newQty, false);
}

function refreshBestSellerBadges(bestSellerIds) {
    document.querySelectorAll('#productsTable tbody tr[data-id]').forEach(function (row) {
        var nameCell = row.querySelector('.name-cell');
        if (!nameCell) return;

        var oldBadge = nameCell.querySelector('.bs-badge');
        if (oldBadge) oldBadge.parentNode.removeChild(oldBadge);

        var rank = parseInt(bestSellerIds[row.dataset.id]) || 0;
        row.dataset.bsRank = rank || '';

        if (rank >= 1 && rank <= 5) {
            var b    = BEST_SELLER_BADGES[rank];
            var span = document.createElement('span');
            span.className   = 'bs-badge ' + b.cls;
            span.textContent = b.text;
            nameCell.appendChild(span);
        }
    });
    sortTableByBestSeller();
}

function rebuildActionButtons(row, qty, isArchived) {
    var cell = row.querySelector('.actions-cell');
    if (!cell) return;

    var id      = row.dataset.id;
    var name    = row.dataset.name;
    var max     = parseInt(row.dataset.maxStock) || 9999;
    var isAdmin = document.body.dataset.isAdmin === '1';

    if (isArchived) {
        if (!isAdmin) { cell.innerHTML = ''; return; }
        cell.innerHTML =
            '<button class="btn btn-sm btn-teal btn-icon" data-action="restore" data-id="' + id + '" title="Restore" aria-label="Restore">'
            + '<i class="fas fa-undo" aria-hidden="true"></i></button>';
        return;
    }

    var outOfStock = qty <= 0;

    var html =
        '<button class="btn btn-sm btn-success btn-icon" data-action="open-add-stock"'
        + ' data-id="'    + id            + '"'
        + ' data-name="'  + escAttr(name) + '"'
        + ' data-stock="' + qty           + '"'
        + ' data-max="'   + max           + '"'
        + ' title="Add Stock" aria-label="Add Stock">'
        + '<i class="fas fa-plus" aria-hidden="true"></i></button>'

        + '<button class="btn btn-sm btn-info btn-icon' + (outOfStock ? ' btn-disabled' : '') + '"'
        + ' data-action="open-sell"'
        + ' data-id="'    + id                    + '"'
        + ' data-name="'  + escAttr(name)          + '"'
        + ' data-price="' + row.dataset.price       + '"'
        + ' data-stock="' + qty                     + '"'
        + ' title="Sell" aria-label="Sell"'
        + (outOfStock ? ' disabled aria-disabled="true"' : '') + '>'
        + '<i class="fas fa-cash-register" aria-hidden="true"></i></button>';

    if (isAdmin) {
        html +=
            '<a href="edit_product.php?id=' + id + '" class="btn btn-sm btn-warning btn-icon" title="Edit" aria-label="Edit">'
            + '<i class="fas fa-edit" aria-hidden="true"></i></a>'

            + '<button class="btn btn-sm btn-gray btn-icon" data-action="open-archive"'
            + ' data-id="'   + id            + '"'
            + ' data-name="' + escAttr(name) + '"'
            + ' title="Archive" aria-label="Archive">'
            + '<i class="fas fa-archive" aria-hidden="true"></i></button>';
    }

    cell.innerHTML = html;
}

/* =============================================================================
   QUANTITY STEPPER
   ============================================================================= */

function stepQty(inputId, delta) {
    var input = byId(inputId);
    if (!input) return;
    var min  = parseInt(input.min, 10) || 1;
    var max  = parseInt(input.max, 10) || 9999;
    var next = Math.min(Math.max((parseInt(input.value, 10) || 1) + delta, min), max);
    input.value = next;
    if (inputId === 'sellQty')        updateSellTotal();
    if (inputId === 'refundQtyInput') updateRefundTotal();
}

/* =============================================================================
   ADD STOCK MODAL
   ============================================================================= */

function openAddStockModal(id, name, stock, max) {
    var currentQty = parseInt(stock, 10) || 0;
    var maxQty     = parseInt(max,   10) || 9999;
    var canAdd     = maxQty - currentQty;

    if (canAdd <= 0) {
        showToast('At Maximum', 'Stock is already at the maximum (' + maxQty + '). Edit the product to raise the limit.', 'warning');
        return;
    }

    App.productId = id;
    setText('stockProductName', name);
    setText('stockCurrent',     currentQty + ' units');
    setText('stockMax',         maxQty     + ' units');
    setText('stockCanAdd',      canAdd     + ' unit(s) remaining');

    var input   = byId('addStockQty');
    input.value = 1;
    input.min   = 1;
    input.max   = canAdd;
    openModal('addStockModal');
}

function submitAddStock() {
    var qty = parseInt(byId('addStockQty').value, 10) || 0;
    if (qty < 1) { showToast('Error', 'Quantity must be at least 1.', 'error'); return; }

    var btn = byId('addStockBtn');
    setButtonLoading(btn, true);

    var fd = new FormData();
    fd.append('product_id', App.productId);
    fd.append('quantity',   qty);

    apiFetch('add_stock.php', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                var id = App.productId;
                closeModal();
                showToast('Stock Added', data.message, 'success');
                updateProductRow(id, data.data.new_stock);
                refreshProductStock();
                refreshStats();
                broadcastUpdate('stock');
            } else {
                showToast('Error', data.message, 'error');
            }
        })
        .catch(function (e) {
            if (e && (e.message === 'session_expired' || e.message === 'access_denied')) return;
            showToast('Error', 'Network error. Please try again.', 'error');
        })
        .finally(function () {
            setButtonLoading(btn, false, '<i class="fas fa-plus-circle"></i> Add Stock');
        });
}

/* =============================================================================
   SELL MODAL
   ============================================================================= */

function openSellModal(id, name, price, stock) {
    var qty = parseInt(stock, 10) || 0;
    if (qty <= 0) {
        showToast('Out of Stock', '"' + name + '" has no available stock.', 'error');
        return;
    }

    App.productId    = id;
    App.productPrice = parseFloat(price) || 0;
    App.productStock = qty;

    setText('sellProductName', name);
    setText('sellStock',       qty + ' units');
    setText('sellUnitPrice',   fmtCurrency(App.productPrice));

    var input   = byId('sellQty');
    input.value = 1;
    input.min   = 1;
    input.max   = qty;
    updateSellTotal();
    openModal('sellModal');
}

function updateSellTotal() {
    var qty = parseInt((byId('sellQty') || {}).value, 10) || 0;
    setText('sellTotal', fmtCurrency(qty * App.productPrice));
}

function submitSell() {
    if (App.sellPending) return;

    var qty = parseInt(byId('sellQty').value, 10) || 0;
    if (qty < 1)               { showToast('Invalid Quantity',   'Quantity must be at least 1.', 'error'); return; }
    if (App.productStock <= 0) { showToast('Out of Stock',       'No stock available.', 'error'); return; }
    if (qty > App.productStock){ showToast('Insufficient Stock', 'Only ' + App.productStock + ' unit(s) available.', 'error'); return; }

    App.sellPending = true;
    var btn = byId('sellBtn');
    setButtonLoading(btn, true);

    var fd = new FormData();
    fd.append('product_id', App.productId);
    fd.append('quantity',   qty);

    apiFetch('sell_product.php', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                var id = App.productId;
                closeModal();
                showToast('Sale Complete', data.message, 'success');
                updateProductRow(id, data.data.new_stock);
                refreshProductStock();
                refreshStats();
                if (byId('salesBody')) {
                    var startVal = byId('startDate') ? byId('startDate').value : '';
                    var endVal   = byId('endDate')   ? byId('endDate').value   : '';
                    loadSalesData(App.salesRange, startVal, endVal);
                }
                broadcastUpdate('sell');
            } else {
                showToast('Error', data.message, 'error');
            }
        })
        .catch(function (e) {
            if (e && (e.message === 'session_expired' || e.message === 'access_denied')) return;
            showToast('Error', 'Network error. Please try again.', 'error');
        })
        .finally(function () {
            App.sellPending = false;
            setButtonLoading(btn, false, '<i class="fas fa-cash-register"></i> Confirm Sale');
        });
}

/* =============================================================================
   ARCHIVE / RESTORE
   ============================================================================= */

function openArchiveModal(id, name) {
    App.productId = id;
    setText('archiveName', name);
    openModal('archiveModal');
}

function submitArchive() {
    if (App.archivePending) return;
    App.archivePending = true;
    var btn = byId('archiveBtn');
    setButtonLoading(btn, true);
    sendArchiveRequest(App.productId, 'archive', function () {
        App.archivePending = false;
        setButtonLoading(btn, false, '<i class="fas fa-archive"></i> Archive');
    });
}

function restoreProduct(id) {
    if (App.restorePending[id]) return;
    App.restorePending[id] = true;
    var btn = document.querySelector('tr[data-id="' + id + '"] [data-action="restore"]');
    if (btn) setButtonLoading(btn, true);
    sendArchiveRequest(id, 'restore', function () { App.restorePending[id] = false; });
}

function sendArchiveRequest(id, action, onDone) {
    var fd = new FormData();
    fd.append('product_id', id);
    fd.append('action',     action);

    apiFetch('archive_product.php', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                if (action === 'archive') closeModal();
                applyArchiveToRow(id, action === 'archive');
                var toastTitle = action === 'archive' ? 'Archived' : 'Restored';
                var toastMsg   = action === 'restore'
                    ? data.message + ' Switch to Active tab to see it.'
                    : data.message;
                showToast(toastTitle, toastMsg, 'success');
                refreshProductStock();
                refreshStats();
                broadcastUpdate('archive');
            } else {
                showToast('Error', data.message, 'error');
            }
        })
        .catch(function (e) {
            if (e && (e.message === 'session_expired' || e.message === 'access_denied')) return;
            showToast('Error', 'Network error.', 'error');
        })
        .finally(function () { if (onDone) onDone(); });
}

function applyArchiveToRow(id, isArchiving) {
    var row = document.querySelector('tr[data-id="' + id + '"]');
    if (!row) return;

    row.dataset.archived = isArchiving ? '1' : '0';
    row.classList.toggle('row-archived', isArchiving);

    var statusCell = row.querySelector('.status-cell');
    if (statusCell) {
        if (isArchiving) {
            statusCell.innerHTML = '<span class="badge badge-gray"><i class="fas fa-archive" aria-hidden="true"></i> Archived</span>';
        } else {
            var qty      = parseInt(row.dataset.quantity,  10) || 0;
            var minStock = parseInt(row.dataset.minStock, 10) || 0;
            statusCell.innerHTML = buildStockBadge(qty, minStock);
        }
    }

    rebuildActionButtons(row, parseInt(row.dataset.quantity, 10) || 0, isArchiving);
    filterTable();
}

/* =============================================================================
   SALES HISTORY
   ============================================================================= */

var _salesAbortCtrl   = null;
var _salesSpinnerTimer = null;

function setRange(range) {
    App.salesRange = range;
    document.querySelectorAll('.range-tab').forEach(function (tab) {
        var active = tab.dataset.range === range;
        tab.classList.toggle('active', active);
        tab.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    var customRow = byId('customDateRow');
    if (customRow) customRow.classList.toggle('hidden', range !== 'custom');
    if (range !== 'custom') loadSalesData(range);
}

function applyCustomRange() {
    var start = byId('startDate') ? byId('startDate').value : '';
    var end   = byId('endDate')   ? byId('endDate').value   : '';
    if (!start || !end) return;
    if (start > end) { showToast('Invalid Range', 'Start date must be before or equal to end date.', 'error'); return; }
    loadSalesData('custom', start, end);
}

function loadSalesData(range, start, end) {
    if (_salesAbortCtrl) _salesAbortCtrl.abort();
    _salesAbortCtrl = new AbortController();
    var signal = _salesAbortCtrl.signal;

    if (_salesSpinnerTimer) { clearTimeout(_salesSpinnerTimer); _salesSpinnerTimer = null; }

    var body = byId('salesBody');
    _salesSpinnerTimer = setTimeout(function () {
        if (body) {
            body.innerHTML =
                '<tr><td colspan="11" class="loading-cell">'
                + '<i class="fas fa-spinner fa-spin" aria-hidden="true"></i>'
                + ' Loading...</td></tr>';
        }
    }, 150);

    var url = App.salesAjaxUrl + '?ajax=1&range=' + encodeURIComponent(range);
    if (range === 'custom' && start && end) {
        url += '&start_date=' + encodeURIComponent(start) + '&end_date=' + encodeURIComponent(end);
    }

    fetch(url, { signal: signal })
        .then(function (r) {
            if (r.status === 401) {
                return r.json().then(function (d) {
                    showToast('Session Expired', 'Please log in again.', 'warning');
                    setTimeout(function () { window.location.href = d.redirect || '../index.php'; }, 1500);
                    return Promise.reject(new Error('session_expired'));
                });
            }
            return r.json();
        })
        .then(function (data) {
            if (_salesSpinnerTimer) { clearTimeout(_salesSpinnerTimer); _salesSpinnerTimer = null; }
            if (!data.success) { showToast('Error', data.message || 'Failed to load.', 'error'); return; }
            renderSalesTable(data);
            renderSalesStats(data);
        })
        .catch(function (e) {
            if (_salesSpinnerTimer) { clearTimeout(_salesSpinnerTimer); _salesSpinnerTimer = null; }
            if (e && e.name === 'AbortError') return;
            if (e && (e.message === 'session_expired' || e.message === 'access_denied')) return;
            showToast('Error', 'Failed to load sales data.', 'error');
        });
}

function renderSalesTable(data) {
    var body = byId('salesBody');
    if (!body) return;

    if (!data.sales || data.sales.length === 0) {
        var isAll = App.salesRange === 'all';
        body.innerHTML =
            '<tr><td colspan="11">'
            + '<div class="empty-state">'
            + '<div class="empty-icon"><i class="fas ' + (isAll ? 'fa-receipt' : 'fa-calendar-times') + '"></i></div>'
            + '<p class="empty-title">' + (isAll ? 'No Transactions Yet' : 'No Sales for This Period') + '</p>'
            + '<p class="empty-sub">'  + (isAll ? 'Sales will appear here once products are sold.' : 'Try selecting a different date range.') + '</p>'
            + '</div></td></tr>';
        updatePrintFooter(data);
        return;
    }

    var html = '';
    data.sales.forEach(function (s) {
        var isSold    = s.status === 'Sold';
        var profit    = isSold ? parseFloat(s.total_price) - parseFloat(s.total_cost || 0) : 0;
        var profitCls = profit > 0 ? 'profit-positive' : (profit < 0 ? 'profit-negative' : 'profit-zero');
        var profitStr = isSold ? fmtCurrency(profit) : '\u2014';

        var refundBtn = s.status === 'Sold'
            ? '<button class="btn btn-sm btn-warning btn-icon" data-action="open-refund"'
              + ' data-id="'    + s.id                    + '"'
              + ' data-name="'  + escAttr(s.product_name) + '"'
              + ' data-qty="'   + s.quantity_sold          + '"'
              + ' data-price="' + s.unit_price             + '"'
              + ' data-date="'  + escAttr(s.sale_date)    + '"'
              + ' title="Refund" aria-label="Refund">'
              + '<i class="fas fa-undo" aria-hidden="true"></i></button>'
            : '';

        html +=
            '<tr>'
            + '<td>'                    + fmtDate(s.sale_date)                     + '</td>'
            + '<td><strong>'            + escHtml(s.product_name) + '</strong></td>'
            + '<td>'                    + escHtml(s.brand)                         + '</td>'
            + '<td>'                    + escHtml(s.category)                      + '</td>'
            + '<td class="col-center">' + parseInt(s.quantity_sold, 10)            + '</td>'
            + '<td>'                    + fmtCurrency(s.unit_price)                + '</td>'
            + '<td>'                    + fmtCurrency(s.total_price)               + '</td>'
            + '<td class="' + profitCls + '">' + profitStr                         + '</td>'
            + '<td class="text-muted">' + escHtml(s.sold_by_name || '\u2014')      + '</td>'
            + '<td>'                    + buildSaleStatusBadge(s.status)           + '</td>'
            + '<td class="no-print">'   + refundBtn                                + '</td>'
            + '</tr>';
    });

    body.innerHTML = html;
    updatePrintFooter(data);
}

function renderSalesStats(data) {
    setText('statTotalSales', fmtNumber(data.totalSales));
    setText('statItemsSold',  fmtNumber(data.totalItems));
    setText('statRevenue',    fmtCurrencyCompact(data.totalRevenue));
    setText('statRefund',     fmtCurrencyCompact(data.totalRefund  || 0));
    setText('statNet',        fmtCurrencyCompact(data.netRevenue   || 0));
    setText('statProfit',     fmtCurrencyCompact(data.totalProfit  || 0));
    setText('recordCount',    fmtNumber(data.totalRecords) + ' records');

    setText('pSales',   fmtNumber(data.totalSales));
    setText('pItems',   fmtNumber(data.totalItems));
    setText('pRevenue', fmtCurrency(data.totalRevenue));
    setText('pRefund',  fmtCurrency(data.totalRefund  || 0));
    setText('pNet',     fmtCurrency(data.netRevenue   || 0));
    setText('pProfit',  fmtCurrency(data.totalProfit  || 0));

    var ps = byId('printSummary');
    if (ps) ps.classList.remove('hidden');
    updatePrintMeta();
}

function updatePrintMeta() {
    var labels = { all: 'All Time', today: 'Today', week: 'Last 7 Days', month: 'Last 30 Days', custom: 'Custom Range' };
    setText('printRange', 'Period: ' + (labels[App.salesRange] || App.salesRange));
    setText('printDate',  new Date().toLocaleString());
}

function updatePrintFooter(data) {
    var el = byId('printFooter');
    if (!el) return;
    el.classList.remove('hidden');
    setText('pfSales',   fmtNumber(data.totalRecords));
    setText('pfItems',   fmtNumber(data.totalItems));
    setText('pfRevenue', fmtCurrency(data.netRevenue || data.totalRevenue));
}

/* =============================================================================
   REFUND MODAL
   ============================================================================= */

function openRefundModal(id, name, qty, unitPrice, saleDate) {
    App.refundId    = id;
    App.refundPrice = parseFloat(unitPrice) || 0;
    var qtyInt = parseInt(qty, 10) || 0;

    setText('refundProduct',   name);
    setText('refundUnitPrice', fmtCurrency(App.refundPrice));
    setText('refundDate',      fmtDate(saleDate));
    setText('refundQtyMax',    'of ' + qtyInt);

    var qtyInput = byId('refundQtyInput');
    if (qtyInput) {
        qtyInput.min      = 1;
        qtyInput.max      = qtyInt;
        qtyInput.value    = qtyInt;
        qtyInput.onchange = updateRefundTotal;
        qtyInput.oninput  = updateRefundTotal;
    }
    updateRefundTotal();

    var reasonEl = byId('refundReason');
    if (reasonEl) reasonEl.value = '';

    document.querySelectorAll('input[name="refundCondition"]').forEach(function (r) {
        r.checked = false;
        var opt = r.closest('.radio-option');
        if (opt) opt.classList.remove('is-checked');
    });

    openModal('refundModal');
}

function updateRefundTotal() {
    var qtyInput = byId('refundQtyInput');
    var totalEl  = byId('refundTotal');
    if (!qtyInput || !totalEl) return;
    var qty = Math.min(Math.max(parseInt(qtyInput.value, 10) || 1, 1), parseInt(qtyInput.max, 10) || 1);
    qtyInput.value      = qty;
    totalEl.textContent = fmtCurrency(qty * (App.refundPrice || 0));
}

function submitRefund() {
    var condition = document.querySelector('input[name="refundCondition"]:checked');
    if (!condition) { showToast('Error', 'Please select the item condition.', 'error'); return; }

    var qtyInput  = byId('refundQtyInput');
    var refundQty = qtyInput ? Math.max(1, parseInt(qtyInput.value, 10) || 1) : 1;
    var btn       = byId('refundBtn');
    setButtonLoading(btn, true);

    var reasonEl = byId('refundReason');
    var fd = new FormData();
    fd.append('transaction_id', App.refundId);
    fd.append('condition',      condition.value);
    fd.append('refund_qty',     refundQty);
    fd.append('reason',         reasonEl ? reasonEl.value : '');

    apiFetch('process_refund.php', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                closeModal();
                showToast('Refund Processed', data.message, 'success');
                refreshProductStock();
                refreshStats();
                var start = byId('startDate') ? byId('startDate').value : '';
                var end   = byId('endDate')   ? byId('endDate').value   : '';
                loadSalesData(App.salesRange, start, end);
                broadcastUpdate('refund');
            } else {
                showToast('Error', data.message, 'error');
            }
        })
        .catch(function (e) {
            if (e && (e.message === 'session_expired' || e.message === 'access_denied')) return;
            showToast('Error', 'Network error.', 'error');
        })
        .finally(function () {
            setButtonLoading(btn, false, '<i class="fas fa-undo"></i> Confirm Refund');
        });
}

/* =============================================================================
   STOCK LIMIT HINTS (Add / Edit Product forms)
   ============================================================================= */

function initAddProductHints() {
    var form = document.querySelector('[data-form="add-product"]');
    if (!form) return;

    var qtyIn = byId('quantity');
    var minIn = byId('min_stock');
    var maxIn = byId('max_stock');
    var hint  = byId('qtyHint');

    function update() {
        if (!qtyIn || !minIn || !maxIn || !hint) return;
        var qty = parseInt(qtyIn.value, 10) || 0;
        var min = parseInt(minIn.value, 10) || 0;
        var max = parseInt(maxIn.value, 10) || 9999;
        qtyIn.max = max;
        hint.textContent = 'Must be 0 or between ' + min + ' (min) and ' + max + ' (max).';
        // FIX: only flag as error if qty > 0 AND (below min or above max)
        hint.classList.toggle('error', qty > 0 && (qty < min || qty > max));
    }

    [qtyIn, minIn, maxIn].forEach(function (el) { if (el) el.addEventListener('input', update); });
    update();
}

function initEditProductHints() {
    var form = document.querySelector('[data-form="edit-product"]');
    if (!form) return;

    var qtyIn = byId('edit_quantity');
    var minIn = byId('edit_min_stock');
    var maxIn = byId('edit_max_stock');
    var hint  = byId('editQtyHint');

    function update() {
        if (!qtyIn || !minIn || !maxIn || !hint) return;
        var qty = parseInt(qtyIn.value, 10) || 0;
        var min = parseInt(minIn.value, 10) || 0;
        var max = parseInt(maxIn.value, 10) || 9999;
        qtyIn.max = max;
        hint.textContent = 'Must be 0 (out of stock), or between ' + min + ' (min) and ' + max + ' (max).';
        // FIX: corrected boolean precedence — qty > 0 required for min check
        hint.classList.toggle('error', qty > 0 && (qty < min || qty > max));
    }

    [qtyIn, minIn, maxIn].forEach(function (el) { if (el) el.addEventListener('input', update); });
    update();
}

/* =============================================================================
   IMAGE PREVIEW
   ============================================================================= */

function handleImagePreview(input) {
    if (!input.files || !input.files[0]) return;
    var previewId = input.dataset.previewTarget;
    var wrapId    = input.dataset.previewWrap;
    var reader    = new FileReader();
    reader.onload = function (e) {
        var preview = byId(previewId);
        var wrap    = byId(wrapId);
        if (preview) preview.src = e.target.result;
        if (wrap)    wrap.classList.remove('hidden');
    };
    reader.readAsDataURL(input.files[0]);
}

/* =============================================================================
   PROFIT PREVIEW (Add / Edit product forms)
   ============================================================================= */

function initProfitPreview() {
    var costInput  = byId('cost_price');
    var priceInput = byId('price');
    var preview    = byId('profitPreview');
    if (!costInput || !priceInput || !preview) return;

    function update() {
        var cost   = parseFloat(costInput.value)  || 0;
        var price  = parseFloat(priceInput.value) || 0;
        var profit = price - cost;

        if (price <= 0) {
            preview.textContent = '\u2014';
            preview.className   = 'profit-preview';
            return;
        }

        var margin = price > 0 ? ((profit / price) * 100).toFixed(1) : 0;
        preview.textContent = fmtCurrency(profit) + '  (' + margin + '%)';
        preview.className   = 'profit-preview';
        if (profit > 0) preview.classList.add('positive');
        else if (profit < 0) preview.classList.add('negative');
    }

    costInput.addEventListener('input',  update);
    priceInput.addEventListener('input', update);
    update();
}

/* =============================================================================
   PASSWORD VISIBILITY TOGGLE
   ============================================================================= */

function togglePasswordVisibility(inputId) {
    var input = byId(inputId);
    var btn   = document.querySelector('[data-action="toggle-password"][data-target="' + inputId + '"]');
    if (!input || !btn) return;
    var isHidden = input.type === 'password';
    input.type   = isHidden ? 'text' : 'password';
    var icon = btn.querySelector('i');
    if (icon) icon.className = isHidden ? 'fas fa-eye-slash' : 'fas fa-eye';
}

/* =============================================================================
   MOBILE NAVBAR
   ============================================================================= */

function initNavbar() {
    var toggle = document.querySelector('.navbar-toggle');
    var nav    = document.querySelector('.navbar-nav');
    if (!toggle || !nav) return;

    toggle.addEventListener('click', function (e) {
        e.stopPropagation();
        var isOpen = nav.classList.toggle('open');
        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });

    nav.addEventListener('click', function (e) {
        if (e.target.classList.contains('nav-link')) {
            nav.classList.remove('open');
            toggle.setAttribute('aria-expanded', 'false');
        }
    });

    document.addEventListener('click', function (e) {
        if (nav.classList.contains('open') && !nav.contains(e.target) && !toggle.contains(e.target)) {
            nav.classList.remove('open');
            toggle.setAttribute('aria-expanded', 'false');
        }
    });
}

/* =============================================================================
   END SHIFT
   ============================================================================= */

function openEndShiftModal() {
    var preConfirm  = byId('shiftPreConfirm');
    var summaryBox  = byId('shiftSummaryBox');
    var endShiftBtn = byId('endShiftBtn');
    if (preConfirm)  preConfirm.classList.remove('hidden');
    if (summaryBox)  summaryBox.classList.add('hidden');
    if (endShiftBtn) endShiftBtn.disabled = false;

    setText('shiftTxnCount',  '—');
    setText('shiftItemCount', '—');
    setText('shiftRevenue',   '—');

    apiFetch('dashboard_stats.php')
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (!d.success) return;
            setText('shiftTxnCount',  fmtNumber(d.totalSales));
            setText('shiftItemCount', fmtNumber(d.totalItems || 0));
            setText('shiftRevenue',   fmtCurrency(d.totalRevenue));
        })
        .catch(function () { /* silent */ });

    openModal('endShiftModal');
}

function submitEndShift() {
    var btn = byId('endShiftBtn');
    setButtonLoading(btn, true);

    apiFetch('end_shift.php', { method: 'POST', body: new FormData() })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                var d = data.data;
                setText('shiftSumTxn',     fmtNumber(d.total_transactions));
                setText('shiftSumItems',   fmtNumber(d.total_items));
                setText('shiftSumRevenue', fmtCurrency(d.total_revenue));

                if (d.ended_at) {
                    var outTime = new Date(d.ended_at.replace(' ', 'T'));
                    setText('shiftOutTime', isNaN(outTime) ? d.ended_at
                        : outTime.toLocaleString('en-PH', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' }));
                }

                var preConfirm = byId('shiftPreConfirm');
                var summaryBox = byId('shiftSummaryBox');
                if (preConfirm) preConfirm.classList.add('hidden');
                if (summaryBox) summaryBox.classList.remove('hidden');

                var footerConfirm = byId('endShiftFooterConfirm');
                var footerDone    = byId('endShiftFooterDone');
                if (footerConfirm) footerConfirm.classList.add('hidden');
                if (footerDone)    footerDone.classList.remove('hidden');

                setText('dashTotalSales',   '0');
                setText('dashTotalRevenue', '\u20b10.00');
                if (byId('dashTotalProfit')) setText('dashTotalProfit', '\u20b10.00');

                showToast('Shift Ended', 'Your shift has been recorded. A new shift has started.', 'success');
            } else {
                showToast('Error', data.message, 'error');
            }
        })
        .catch(function (e) {
            if (e && (e.message === 'session_expired' || e.message === 'access_denied')) return;
            showToast('Error', 'Network error.', 'error');
        })
        .finally(function () {
            var b = byId('endShiftBtn');
            setButtonLoading(b, false, '<i class="fas fa-flag-checkered" aria-hidden="true"></i> End Shift');
        });
}

/* =============================================================================
   CASHIER LOGOUT
   ============================================================================= */

function openCashierLogoutModal() {
    var choiceBox  = byId('logoutChoiceBox');
    var summaryBox = byId('logoutShiftSummaryBox');
    var footer     = byId('cashierLogoutFooter');
    if (choiceBox)  choiceBox.classList.remove('hidden');
    if (summaryBox) summaryBox.classList.add('hidden');
    if (footer) {
        footer.innerHTML = '<button class="btn btn-secondary" data-action="close-modal">Cancel</button>';
    }

    openModal('cashierLogoutModal');

    var optEnd    = byId('logoutOptEndShift');
    var optDirect = byId('logoutOptDirect');

    if (optEnd) {
        var newOptEnd = optEnd.cloneNode(true);
        optEnd.parentNode.replaceChild(newOptEnd, optEnd);
        newOptEnd.addEventListener('click', handleLogoutWithEndShift);
    }
    if (optDirect) {
        var newOptDirect = optDirect.cloneNode(true);
        optDirect.parentNode.replaceChild(newOptDirect, optDirect);
        newOptDirect.addEventListener('click', handleDirectLogout);
    }
}

function handleLogoutWithEndShift() {
    var choiceBox  = byId('logoutChoiceBox');
    var summaryBox = byId('logoutShiftSummaryBox');
    var footer     = byId('cashierLogoutFooter');

    if (footer) {
        footer.innerHTML =
            '<button class="btn btn-secondary" disabled>'
            + '<i class="fas fa-spinner fa-spin" aria-hidden="true"></i> Processing...</button>';
    }

    var fd = new FormData();
    fd.append('logout', '1');

    apiFetch('end_shift.php', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                var d = data.data;
                setText('logoutSumTxn',     fmtNumber(d.total_transactions));
                setText('logoutSumItems',   fmtNumber(d.total_items));
                setText('logoutSumRevenue', fmtCurrency(d.total_revenue));

                if (d.ended_at) {
                    var outTime = new Date(d.ended_at.replace(' ', 'T'));
                    setText('logoutOutTime', isNaN(outTime) ? d.ended_at
                        : outTime.toLocaleString('en-PH', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' }));
                }

                if (choiceBox)  choiceBox.classList.add('hidden');
                if (summaryBox) summaryBox.classList.remove('hidden');
                if (footer)     footer.innerHTML = '';

                setTimeout(function () { window.location.href = 'logout.php'; }, 2500);
            } else {
                showToast('Error', data.message, 'error');
                if (footer) {
                    footer.innerHTML = '<button class="btn btn-secondary" data-action="close-modal">Cancel</button>';
                }
            }
        })
        .catch(function (e) {
            if (e && (e.message === 'session_expired' || e.message === 'access_denied')) return;
            showToast('Error', 'Network error. Try again.', 'error');
            if (footer) {
                footer.innerHTML = '<button class="btn btn-secondary" data-action="close-modal">Cancel</button>';
            }
        });
}

function handleDirectLogout() {
    window.location.href = 'logout.php';
}

/* =============================================================================
   EVENT DELEGATION — clicks
   ============================================================================= */

document.addEventListener('click', function (e) {
    if (e.target.classList.contains('modal-overlay')) { closeModal(); return; }

    var stepBtn = e.target.closest('[data-action="step-qty"]');
    if (stepBtn) { stepQty(stepBtn.dataset.target, parseInt(stepBtn.dataset.delta, 10) || 1); return; }

    var viewTab = e.target.closest('[data-action="switch-view"]');
    if (viewTab) { switchView(viewTab.dataset.view); return; }

    var rangeTab = e.target.closest('.range-tab');
    if (rangeTab && rangeTab.dataset.range) { setRange(rangeTab.dataset.range); return; }

    var el = e.target.closest('[data-action]');
    if (!el) return;

    switch (el.dataset.action) {
        case 'close-modal':     closeModal(); break;
        case 'hide-toast':      hideToast();  break;
        case 'toggle-password': togglePasswordVisibility(el.dataset.target); break;
        case 'open-end-shift':  openEndShiftModal(); break;
        case 'submit-end-shift': submitEndShift(); break;
        case 'open-add-stock':  openAddStockModal(el.dataset.id, el.dataset.name, el.dataset.stock, el.dataset.max); break;
        case 'submit-add-stock': submitAddStock(); break;
        case 'open-sell':       openSellModal(el.dataset.id, el.dataset.name, el.dataset.price, el.dataset.stock); break;
        case 'submit-sell':     submitSell(); break;
        case 'open-archive':    openArchiveModal(el.dataset.id, el.dataset.name); break;
        case 'submit-archive':  submitArchive(); break;
        case 'restore':         restoreProduct(el.dataset.id); break;
        case 'open-refund':     openRefundModal(el.dataset.id, el.dataset.name, el.dataset.qty, el.dataset.price, el.dataset.date); break;
        case 'submit-refund':   submitRefund(); break;
        case 'print':           window.print(); break;
        case 'cashier-logout':  openCashierLogoutModal(); break;
        case 'dismiss-lock-warning': /* handled by auto-lock IIFE */ break;
    }
});

/* =============================================================================
   EVENT DELEGATION — inputs / changes
   ============================================================================= */

document.addEventListener('input', function (e) {
    var id = e.target.id;
    if (id === 'searchInput') filterTable();
    if (id === 'sellQty')     updateSellTotal();
    if (e.target.hasAttribute('required')) {
        e.target.classList.toggle('invalid', e.target.value.trim() === '');
    }
});

document.addEventListener('change', function (e) {
    var id = e.target.id;
    if (id === 'categoryFilter' || id === 'brandFilter' || id === 'genderFilter') filterTable();
    if (id === 'sellQty') updateSellTotal();
    if (e.target.dataset.action === 'auto-custom-range') applyCustomRange();
    if (e.target.dataset.action === 'preview-image') handleImagePreview(e.target);
    if (e.target.dataset.action === 'toggle-image-dim') {
        var img = byId(e.target.dataset.target);
        if (img) img.classList.toggle('img-dimmed', e.target.checked);
    }
    if (e.target.type === 'radio') {
        var name = e.target.name;
        if (name) {
            document.querySelectorAll('input[name="' + name + '"]').forEach(function (radio) {
                var opt = radio.closest('.radio-option');
                if (opt) opt.classList.toggle('is-checked', radio.checked);
            });
        }
    }
});

/* =============================================================================
   KEYBOARD
   ============================================================================= */

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeModal();
});

/* =============================================================================
   FORM VALIDATION
   ============================================================================= */

document.addEventListener('submit', function (e) {
    var form = e.target.closest('form[data-form]');
    if (!form) return;

    var required = form.querySelectorAll('[required]');
    var valid    = true;
    required.forEach(function (field) {
        var empty = field.value.trim() === '';
        field.classList.toggle('invalid', empty);
        if (empty) valid = false;
    });

    if (!valid) {
        e.preventDefault();
        showToast('Missing Fields', 'Please fill in all required fields.', 'error');
    }
});

/* =============================================================================
   AUTO-LOCK — redirects to lock.php after inactivity (cashier only)
   ============================================================================= */

(function () {
    var lockMins = parseInt((document.body.dataset.autoLock || '0'), 10);
    if (!lockMins || lockMins <= 0) return;

    var IDLE_MS           = lockMins * 60 * 1000;
    var WARN_MS           = 30 * 1000;
    var warnTimer         = null;
    var lockTimer         = null;
    var countdownInterval = null;

    function resetTimers() {
        clearTimeout(warnTimer);
        clearTimeout(lockTimer);
        clearInterval(countdownInterval);
        hideWarning();
        warnTimer = setTimeout(showWarning, IDLE_MS - WARN_MS);
        lockTimer = setTimeout(doLock,      IDLE_MS);
    }

    function showWarning() {
        var overlay = document.getElementById('autoLockOverlay');
        var countEl = document.getElementById('autoLockCount');
        if (!overlay || !countEl) return;

        var secs = Math.floor(WARN_MS / 1000);
        countEl.textContent = secs;
        overlay.classList.remove('hidden');

        countdownInterval = setInterval(function () {
            secs -= 1;
            countEl.textContent = secs > 0 ? secs : 0;
            if (secs <= 0) clearInterval(countdownInterval);
        }, 1000);
    }

    function hideWarning() {
        var overlay = document.getElementById('autoLockOverlay');
        if (overlay) overlay.classList.add('hidden');
        clearInterval(countdownInterval);
    }

    function doLock() { window.location.href = 'lock.php'; }

    ['mousemove', 'mousedown', 'keydown', 'touchstart', 'scroll', 'click'].forEach(function (ev) {
        document.addEventListener(ev, resetTimers, { passive: true });
    });

    document.addEventListener('click', function (e) {
        if (e.target.closest('[data-action="dismiss-lock-warning"]')) resetTimers();
    });

    resetTimers();
}());

/* =============================================================================
   CROSS-TAB SYNC
   ============================================================================= */

function broadcastUpdate(type) {
    if (window._syncBroadcast) window._syncBroadcast.postMessage({ type: type });
}

/* =============================================================================
   REAL-TIME SYNC — BroadcastChannel + long-poll
   ============================================================================= */

function syncAllData() {
    refreshStats();
    if (byId('productsTable')) refreshProductStock();
    if (byId('salesBody') && App.salesRange !== 'custom') {
        var startVal = byId('startDate') ? byId('startDate').value : '';
        var endVal   = byId('endDate')   ? byId('endDate').value   : '';
        loadSalesData(App.salesRange, startVal, endVal);
    }
}

function refreshProductStock() {
    apiFetch('products_sync.php')
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (!d.success) return;
            document.querySelectorAll('#productsTable tbody tr[data-id]').forEach(function (row) {
                var id = parseInt(row.dataset.id, 10);
                if (!id || row.dataset.archived === '1') return;
                var newQty = d.stock[id];
                if (newQty === undefined) return;
                newQty = parseInt(newQty, 10);
                var currentQty = parseInt(row.dataset.quantity, 10) || 0;
                if (newQty === currentQty) return;
                row.dataset.quantity = newQty;
                var qtyEl = row.querySelector('.stock-qty');
                if (qtyEl) qtyEl.textContent = newQty;
                var minStock   = parseInt(row.dataset.minStock, 10) || 0;
                var statusCell = row.querySelector('.status-cell');
                if (statusCell) statusCell.innerHTML = buildStockBadge(newQty, minStock);
                rebuildActionButtons(row, newQty, false);
            });
            if (d.bestSellers) refreshBestSellerBadges(d.bestSellers);
        })
        .catch(function () { /* silent — sync is best-effort */ });
}

/* =============================================================================
   BOOT
   ============================================================================= */

document.addEventListener('DOMContentLoaded', function () {
    initNavbar();

    if (byId('productsTable')) filterTable();

    var salesUrl = document.body.dataset.salesUrl;
    if (salesUrl) {
        App.salesAjaxUrl = salesUrl;
        updatePrintMeta();
        (function () {
            var g = function (id) {
                var el = document.getElementById(id);
                return el ? el.textContent.trim() : '';
            };
            setText('pSales',   g('statTotalSales'));
            setText('pItems',   g('statItemsSold'));
            setText('pRevenue', g('statRevenue'));
            setText('pRefund',  g('statRefund'));
            setText('pNet',     g('statNet'));
            setText('pProfit',  g('statProfit'));
            var rcEl   = document.getElementById('recordCount');
            var rcText = rcEl ? rcEl.textContent.replace(' records', '').trim() : g('statTotalSales');
            setText('pfSales',   rcText);
            setText('pfItems',   g('statItemsSold'));
            setText('pfRevenue', g('statNet'));
            var ps = byId('printSummary');
            if (ps) ps.classList.remove('hidden');
            var pf = byId('printFooter');
            if (pf) pf.classList.remove('hidden');
        }());
    }

    initAddProductHints();
    initEditProductHints();
    initProfitPreview();

    var body = document.body;
    if (body.dataset.toastText) {
        showToast(
            body.dataset.toastTitle || 'Notice',
            body.dataset.toastText,
            body.dataset.toastType  || 'success'
        );
    }

    // BroadcastChannel — instant same-browser tab sync
    (function () {
        if (!window.BroadcastChannel) return;
        var ch = new BroadcastChannel('shoes_inventory_sync');
        ch.onmessage = function () { syncAllData(); };
        window._syncBroadcast = ch;
    }());

    // Long-poll — instant cross-device sync
    (function () {
        var hasDashboard = !!byId('productsTable');
        var hasSalesBody = !!byId('salesBody');
        if (!hasDashboard && !hasSalesBody) return;

        var lastTs       = 0;
        var longPollCtrl = null;
        var retryTimer   = null;

        function startLongPoll() {
            if (longPollCtrl) longPollCtrl.abort();
            longPollCtrl = new AbortController();

            fetch('sync_check.php?since=' + lastTs, { signal: longPollCtrl.signal, cache: 'no-store' })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (lastTs === 0) {
                        lastTs     = d.ts;
                        retryTimer = setTimeout(startLongPoll, 0);
                        return;
                    }
                    if (d.changed) { lastTs = d.ts; syncAllData(); }
                    retryTimer = setTimeout(startLongPoll, 100);
                })
                .catch(function (e) {
                    if (e && e.name === 'AbortError') return;
                    retryTimer = setTimeout(startLongPoll, 2000);
                });
        }

        startLongPoll();

        window.addEventListener('beforeunload', function () {
            if (longPollCtrl) longPollCtrl.abort();
            if (retryTimer)   clearTimeout(retryTimer);
        });
    }());
});
