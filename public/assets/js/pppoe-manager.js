/**
 * pppoe-manager.js — PPPoE user management and monitoring via AJAX
 */
(function () {
    'use strict';

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    // -------------------------------------------------------------------------
    // Toggle monitor status
    // -------------------------------------------------------------------------
    document.querySelectorAll('.toggle-monitor').forEach(function (checkbox) {
        checkbox.addEventListener('change', function () {
            const id      = parseInt(this.dataset.id, 10);
            const checked = this.checked;
            const el      = this;

            fetch('/pppoe/' + id + '/toggle-monitor', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrf,
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ _csrf: csrf, monitored: checked ? 1 : 0 }),
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success) {
                        NOC.showToast('Monitoring ' + (checked ? 'enabled' : 'disabled') + '.', 'success');
                    } else {
                        el.checked = !checked;
                        NOC.showToast(data.message || 'Failed.', 'danger');
                    }
                })
                .catch(function () {
                    el.checked = !checked;
                    NOC.showToast('Network error.', 'danger');
                });
        });
    });

    // -------------------------------------------------------------------------
    // Delete PPPoE user
    // -------------------------------------------------------------------------
    document.querySelectorAll('.btn-delete-pppoe').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!confirm('Delete this PPPoE user? All traffic data will be lost.')) return;

            const id  = parseInt(this.dataset.id, 10);
            const row = this.closest('tr');

            fetch('/pppoe/' + id, {
                method: 'DELETE',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrf,
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ _csrf: csrf }),
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success) {
                        if (row) row.remove();
                        NOC.showToast('PPPoE user deleted.', 'success');
                    } else {
                        NOC.showToast(data.message || 'Delete failed.', 'danger');
                    }
                })
                .catch(function () { NOC.showToast('Network error.', 'danger'); });
        });
    });

    // -------------------------------------------------------------------------
    // Table search / filter
    // -------------------------------------------------------------------------
    function applyFilters() {
        const q        = (document.getElementById('pppoe-search')?.value || '').toLowerCase();
        const routerId = document.getElementById('filter-router')?.value || '';
        const status   = document.getElementById('filter-status')?.value || '';

        let visible = 0;
        document.querySelectorAll('#pppoe-table tbody tr').forEach(function (row) {
            const tm = !q || row.textContent.toLowerCase().includes(q);
            const rm = !routerId || (row.dataset.routerId === routerId);
            const sm = !status || (row.dataset.status === status);
            const show = tm && rm && sm;
            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });

        const cnt = document.getElementById('pppoe-count');
        if (cnt) cnt.textContent = visible;
    }

    ['pppoe-search', 'filter-router', 'filter-status'].forEach(function (id) {
        document.getElementById(id)?.addEventListener('input', applyFilters);
        document.getElementById(id)?.addEventListener('change', applyFilters);
    });

    // -------------------------------------------------------------------------
    // Live bandwidth on show page
    // -------------------------------------------------------------------------
    if (typeof window.PPPOE_ID !== 'undefined') {
        pollLive();
        setInterval(pollLive, 10000);
    }

    function pollLive() {
        const id = window.PPPOE_ID;
        if (!id) return;

        fetch('/api/v1/monitoring?type=pppoe&id=' + id + '&limit=1', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success || !data.data?.length) return;
                const latest = data.data[0];
                const inEl  = document.getElementById('bw-in');
                const outEl = document.getElementById('bw-out');
                if (inEl)  inEl.textContent  = NOC.formatBandwidth(latest.bytes_in_rate  ?? 0);
                if (outEl) outEl.textContent = NOC.formatBandwidth(latest.bytes_out_rate ?? 0);
            })
            .catch(function () {});
    }

    // -------------------------------------------------------------------------
    // Monitoring page — load table data
    // -------------------------------------------------------------------------
    if (document.getElementById('pppoe-tbody')) {
        loadMonitoringData();
        setInterval(loadMonitoringData, 30000);
    }

    function loadMonitoringData() {
        const routerFilter = document.getElementById('filter-router')?.value || '';
        const url = '/api/v1/monitoring?type=pppoe' + (routerFilter ? '&router_id=' + routerFilter : '');

        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success) return;
                renderMonitoringTable(data.data || []);
            })
            .catch(function () {});
    }

    function renderMonitoringTable(rows) {
        const tbody = document.getElementById('pppoe-tbody');
        if (!tbody) return;

        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">No monitored PPPoE users.</td></tr>';
            return;
        }

        tbody.innerHTML = rows.map(function (r) {
            const statusClass = r.status === 'connected' ? 'badge-success' : 'badge-secondary';
            return '<tr>' +
                '<td><a href="/pppoe/' + r.id + '" class="text-primary">' + escHtml(r.name || '') + '</a></td>' +
                '<td>' + escHtml(r.router_name || '') + '</td>' +
                '<td><code>' + escHtml(r.ip_address || '—') + '</code></td>' +
                '<td class="bandwidth-in">' + NOC.formatBandwidth(r.bytes_in_rate || 0) + '</td>' +
                '<td class="bandwidth-out">' + NOC.formatBandwidth(r.bytes_out_rate || 0) + '</td>' +
                '<td><span class="badge ' + statusClass + '">' + escHtml(r.status || '—') + '</span></td>' +
                '<td><a href="/monitoring/pppoe?user_id=' + r.id + '" class="btn btn-secondary btn-sm"><i class="fas fa-chart-line"></i></a></td>' +
                '</tr>';
        }).join('');

        const lu = document.getElementById('last-updated');
        if (lu) lu.textContent = new Date().toLocaleTimeString();

        const activeCnt = document.getElementById('cnt-active');
        if (activeCnt) {
            activeCnt.textContent = rows.filter(function (r) { return r.status === 'connected'; }).length;
        }
    }

    function escHtml(str) {
        const d = document.createElement('div');
        d.appendChild(document.createTextNode(str));
        return d.innerHTML;
    }

})();
