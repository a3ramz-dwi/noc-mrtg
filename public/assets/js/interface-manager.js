/**
 * interface-manager.js — Interface management and monitoring via AJAX
 */
(function () {
    'use strict';

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    // -------------------------------------------------------------------------
    // Toggle monitor status
    // -------------------------------------------------------------------------
    document.querySelectorAll('.toggle-monitor').forEach(function (checkbox) {
        checkbox.addEventListener('change', function () {
            const id = parseInt(this.dataset.id, 10);
            const checked = this.checked;
            const el = this;

            fetch('/interfaces/' + id + '/toggle-monitor', {
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
                        const row = el.closest('tr');
                        if (row) row.dataset.monitored = checked ? '1' : '0';
                    } else {
                        el.checked = !checked; // revert
                        NOC.showToast(data.message || 'Failed to update.', 'danger');
                    }
                })
                .catch(function () {
                    el.checked = !checked;
                    NOC.showToast('Network error.', 'danger');
                });
        });
    });

    // -------------------------------------------------------------------------
    // Delete interface
    // -------------------------------------------------------------------------
    document.querySelectorAll('.btn-delete-iface').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!confirm('Delete this interface? All traffic data will be lost.')) return;

            const id = parseInt(this.dataset.id, 10);
            const row = this.closest('tr');

            fetch('/interfaces/' + id, {
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
                        NOC.showToast('Interface deleted.', 'success');
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
    document.getElementById('iface-search')?.addEventListener('input', function () {
        applyFilters();
    });

    document.getElementById('filter-router')?.addEventListener('change', function () {
        applyFilters();
    });

    document.getElementById('filter-monitor')?.addEventListener('change', function () {
        applyFilters();
    });

    function applyFilters() {
        const q         = (document.getElementById('iface-search')?.value || '').toLowerCase();
        const routerId  = document.getElementById('filter-router')?.value || '';
        const monitored = document.getElementById('filter-monitor')?.value;

        let visible = 0;
        document.querySelectorAll('#interfaces-table tbody tr').forEach(function (row) {
            const textMatch    = !q || row.textContent.toLowerCase().includes(q);
            const routerMatch  = !routerId || (row.dataset.routerId === routerId);
            const monitorMatch = monitored === '' || monitored === undefined ? true : (row.dataset.monitored === monitored);

            const show = textMatch && routerMatch && monitorMatch;
            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });

        const cnt = document.getElementById('iface-count');
        if (cnt) cnt.textContent = visible;
    }

    // -------------------------------------------------------------------------
    // Live bandwidth polling on show page
    // -------------------------------------------------------------------------
    if (typeof window.INTERFACE_ID !== 'undefined') {
        startLivePolling();
    }

    function startLivePolling() {
        pollLive();
        setInterval(pollLive, 10000);
    }

    function pollLive() {
        const id = window.INTERFACE_ID;
        if (!id) return;

        fetch('/api/v1/monitoring?type=interface&id=' + id + '&limit=1', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success || !data.data?.length) return;
                const latest = data.data[0];
                updateBandwidth('bw-in',  latest.bytes_in_rate  ?? 0);
                updateBandwidth('bw-out', latest.bytes_out_rate ?? 0);
            })
            .catch(function () {});
    }

    function updateBandwidth(elId, bps) {
        const el = document.getElementById(elId);
        if (el) el.textContent = NOC.formatBandwidth(bps);
    }

    // -------------------------------------------------------------------------
    // Monitoring page — load table data
    // -------------------------------------------------------------------------
    if (document.getElementById('interfaces-tbody')) {
        loadMonitoringData();
        setInterval(loadMonitoringData, 30000);
    }

    function loadMonitoringData() {
        const routerFilter = document.getElementById('filter-router')?.value || '';
        const url = '/api/v1/monitoring?type=interface' + (routerFilter ? '&router_id=' + routerFilter : '');

        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success) return;
                renderMonitoringTable(data.data || []);
            })
            .catch(function () {});
    }

    function renderMonitoringTable(rows) {
        const tbody = document.getElementById('interfaces-tbody');
        if (!tbody) return;

        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">No monitored interfaces.</td></tr>';
            return;
        }

        tbody.innerHTML = rows.map(function (r) {
            return '<tr>' +
                '<td><a href="/interfaces/' + r.id + '" class="text-primary">' + escHtml(r.if_name || '') + '</a></td>' +
                '<td>' + escHtml(r.router_name || '') + '</td>' +
                '<td class="bandwidth-in">' + NOC.formatBandwidth(r.bytes_in_rate || 0) + '</td>' +
                '<td class="bandwidth-out">' + NOC.formatBandwidth(r.bytes_out_rate || 0) + '</td>' +
                '<td>' + buildUtil(r) + '</td>' +
                '<td><span class="badge ' + (r.oper_status === 1 ? 'badge-success' : 'badge-secondary') + '">' + (r.oper_status === 1 ? 'Up' : 'Down') + '</span></td>' +
                '<td><a href="/monitoring/interfaces?interface_id=' + r.id + '" class="btn btn-secondary btn-sm"><i class="fas fa-chart-line"></i></a></td>' +
                '</tr>';
        }).join('');

        document.getElementById('last-updated').textContent = new Date().toLocaleTimeString();
    }

    function buildUtil(r) {
        const bwIn   = r.bytes_in_rate  || 0;
        const bwOut  = r.bytes_out_rate || 0;
        const speed  = (r.if_speed || 0) / 8; // bytes/s
        if (!speed) return '—';
        const pct = Math.min(100, Math.round(Math.max(bwIn, bwOut) / speed * 100));
        const cls = pct > 80 ? 'bg-danger' : pct > 60 ? 'bg-warning' : 'bg-success';
        return '<div class="progress" style="height:6px;width:80px;"><div class="progress-bar ' + cls + '" style="width:' + pct + '%"></div></div><small class="text-muted">' + pct + '%</small>';
    }

    function escHtml(str) {
        const d = document.createElement('div');
        d.appendChild(document.createTextNode(str));
        return d.innerHTML;
    }

})();
