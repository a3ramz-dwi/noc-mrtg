/**
 * queue-manager.js — Queue management and monitoring via AJAX
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

            fetch('/queues/' + id + '/toggle-monitor', {
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
    // Delete queue
    // -------------------------------------------------------------------------
    document.querySelectorAll('.btn-delete-queue').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!confirm('Delete this queue? All traffic data will be lost.')) return;

            const id  = parseInt(this.dataset.id, 10);
            const row = this.closest('tr');

            fetch('/queues/' + id, {
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
                        NOC.showToast('Queue deleted.', 'success');
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
        const q         = (document.getElementById('queue-search')?.value || '').toLowerCase();
        const routerId  = document.getElementById('filter-router')?.value || '';
        const monitored = document.getElementById('filter-monitor')?.value;

        let visible = 0;
        document.querySelectorAll('#queues-table tbody tr').forEach(function (row) {
            const tm = !q || row.textContent.toLowerCase().includes(q);
            const rm = !routerId || (row.dataset.routerId === routerId);
            const mm = monitored === '' || monitored === undefined ? true : (row.dataset.monitored === monitored);
            const show = tm && rm && mm;
            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });

        const cnt = document.getElementById('queue-count');
        if (cnt) cnt.textContent = visible;
    }

    ['queue-search', 'filter-router', 'filter-monitor'].forEach(function (id) {
        document.getElementById(id)?.addEventListener('input', applyFilters);
        document.getElementById(id)?.addEventListener('change', applyFilters);
    });

    // -------------------------------------------------------------------------
    // Live bandwidth on show page
    // -------------------------------------------------------------------------
    if (typeof window.QUEUE_ID !== 'undefined') {
        pollLive();
        setInterval(pollLive, 10000);
    }

    function pollLive() {
        const id = window.QUEUE_ID;
        if (!id) return;

        fetch('/api/v1/monitoring?type=queue&id=' + id + '&limit=1', {
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
    if (document.getElementById('queues-tbody')) {
        loadMonitoringData();
        setInterval(loadMonitoringData, 30000);
    }

    function loadMonitoringData() {
        const routerFilter = document.getElementById('filter-router')?.value || '';
        const url = '/api/v1/monitoring?type=queue' + (routerFilter ? '&router_id=' + routerFilter : '');

        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success) return;
                renderMonitoringTable(data.data || []);
            })
            .catch(function () {});
    }

    function renderMonitoringTable(rows) {
        const tbody = document.getElementById('queues-tbody');
        if (!tbody) return;

        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">No monitored queues.</td></tr>';
            return;
        }

        tbody.innerHTML = rows.map(function (r) {
            const maxDown = parseInt(r.max_limit_down || 0, 10);
            const maxFmt  = maxDown > 0
                ? '↓' + fmtBps(maxDown) + ' / ↑' + fmtBps(parseInt(r.max_limit_up || 0, 10))
                : '—';

            return '<tr>' +
                '<td><a href="/queues/' + r.id + '" class="text-primary">' + escHtml(r.name || '') + '</a></td>' +
                '<td>' + escHtml(r.router_name || '') + '</td>' +
                '<td><code>' + escHtml(r.target || '') + '</code></td>' +
                '<td class="bandwidth-in">' + NOC.formatBandwidth(r.bytes_in_rate || 0) + '</td>' +
                '<td class="bandwidth-out">' + NOC.formatBandwidth(r.bytes_out_rate || 0) + '</td>' +
                '<td><small class="text-muted">' + maxFmt + '</small></td>' +
                '<td><a href="/monitoring/queues?queue_id=' + r.id + '" class="btn btn-secondary btn-sm"><i class="fas fa-chart-line"></i></a></td>' +
                '</tr>';
        }).join('');

        const lu = document.getElementById('last-updated');
        if (lu) lu.textContent = new Date().toLocaleTimeString();
    }

    function fmtBps(bps) {
        if (bps >= 1_000_000) return (bps / 1_000_000).toFixed(1) + 'M';
        if (bps >= 1_000)     return (bps / 1_000).toFixed(0) + 'k';
        return bps + '';
    }

    function escHtml(str) {
        const d = document.createElement('div');
        d.appendChild(document.createTextNode(str));
        return d.innerHTML;
    }

})();
