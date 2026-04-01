/**
 * cashier_report.js
 * Handles the shift detail modal and auto-submit filter on the Cashier Report page.
 * Depends on main.js being loaded first (uses openModal, apiFetch, escHtml, fmtCurrency).
 */

'use strict';

/* =============================================================================
   AUTO-SUBMIT FILTER — submits form immediately on date or cashier change
   ============================================================================= */

document.addEventListener('change', function (e) {
    if (e.target.dataset.action === 'auto-submit-report') {
        var form = document.getElementById('reportFilterForm');
        if (form) form.submit();
    }
});

/* =============================================================================
   SHIFT DETAIL MODAL
   ============================================================================= */

document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-action="view-shift"]');
    if (!btn) return;

    var shiftId = btn.dataset.shiftId;
    var cashier = btn.dataset.cashier;
    var body    = document.getElementById('shiftDetailBody');
    var title   = document.getElementById('shiftDetailTitle');

    if (!shiftId || !body) return;

    title.textContent = 'Shift Transactions \u2014 ' + cashier;
    body.innerHTML    = '<div class="loading-cell"><i class="fas fa-spinner fa-spin" aria-hidden="true"></i> Loading...</div>';

    // Use main.js openModal so App.activeModal is set and close-modal button works
    openModal('shiftDetailModal');

    apiFetch('cashier_report.php?ajax=shift&shift_id=' + encodeURIComponent(shiftId))
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.success || !data.data.transactions || !data.data.transactions.length) {
                body.innerHTML =
                    '<div class="empty-state">'
                    + '<div class="empty-icon"><i class="fas fa-receipt"></i></div>'
                    + '<p class="empty-title">No Transactions</p>'
                    + '<p class="empty-sub">No sales were recorded during this shift.</p>'
                    + '</div>';
                return;
            }

            var rows = '', totalQty = 0, totalRev = 0;

            data.data.transactions.forEach(function (t) {
                if (t.status === 'Sold') {
                    totalQty += parseInt(t.quantity_sold, 10);
                    totalRev += parseFloat(t.total_price);
                }

                // Use escHtml and fmtCurrency from main.js (always loaded before this file)
                var statusBadge = {
                    'Sold':                 '<span class="badge badge-success">Sold</span>',
                    'Refunded (Restocked)': '<span class="badge badge-warning">Refunded</span>',
                    'Refunded (Damaged)':   '<span class="badge badge-danger">Refunded</span>',
                }[t.status] || '<span class="badge badge-gray">' + escHtml(t.status) + '</span>';

                var d       = new Date((t.sale_date + '').replace(' ', 'T'));
                var timeStr = isNaN(d.getTime()) ? t.sale_date
                    : d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' })
                      + '<br><span class="text-muted">'
                      + d.toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit' })
                      + '</span>';

                rows +=
                    '<tr>'
                    + '<td>' + timeStr + '</td>'
                    + '<td><strong>' + escHtml(t.product_name) + '</strong></td>'
                    + '<td>' + escHtml(t.brand) + '</td>'
                    + '<td class="col-center">' + parseInt(t.quantity_sold, 10) + '</td>'
                    + '<td>' + fmtCurrency(t.unit_price) + '</td>'
                    + '<td>' + fmtCurrency(t.total_price) + '</td>'
                    + '<td>' + statusBadge + '</td>'
                    + '</tr>';
            });

            body.innerHTML =
                '<div class="table-wrap">'
                + '<table class="table shift-detail-table">'
                + '<thead><tr>'
                + '<th>Time</th><th>Product</th><th>Brand</th>'
                + '<th class="col-center">Qty</th><th>Unit Price</th><th>Total</th><th>Status</th>'
                + '</tr></thead>'
                + '<tbody>' + rows + '</tbody>'
                + '<tfoot><tr class="shift-totals-row">'
                + '<td colspan="3"><strong>SHIFT TOTAL</strong></td>'
                + '<td class="col-center"><strong>' + totalQty + '</strong></td>'
                + '<td></td>'
                + '<td><strong>' + fmtCurrency(totalRev) + '</strong></td>'
                + '<td></td>'
                + '</tr></tfoot>'
                + '</table>'
                + '</div>';
        })
        .catch(function (err) {
            if (err && (err.message === 'session_expired' || err.message === 'access_denied')) return;
            body.innerHTML = '<div class="alert alert-error">Failed to load shift data. Please try again.</div>';
        });
});
