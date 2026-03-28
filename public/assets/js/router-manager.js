/**
 * router-manager.js — Router CRUD and SNMP test via AJAX
 */
(function () {
    'use strict';

    const APP = window.APP || {};

    // -------------------------------------------------------------------------
    // Delete router
    // -------------------------------------------------------------------------
    let pendingDeleteId = null;
    const deleteModal    = document.getElementById('deleteModal');

    document.querySelectorAll('.btn-delete-router').forEach(function (btn) {
        btn.addEventListener('click', function () {
            pendingDeleteId = parseInt(this.dataset.id, 10);
            const nameEl = document.getElementById('delete-router-name');
            if (nameEl) nameEl.textContent = this.dataset.name || '#' + pendingDeleteId;
            if (deleteModal) {
                const modal = bootstrap.Modal.getOrCreateInstance(deleteModal);
                modal.show();
            }
        });
    });

    document.getElementById('confirm-delete-btn')?.addEventListener('click', function () {
        if (!pendingDeleteId) return;

        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Deleting…';

        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

        fetch('/routers/' + pendingDeleteId, {
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
                    bootstrap.Modal.getInstance(deleteModal)?.hide();
                    const row = document.querySelector('[data-id="' + pendingDeleteId + '"]')?.closest('tr');
                    if (row) row.remove();
                    NOC.showToast('Router deleted.', 'success');
                } else {
                    NOC.showToast(data.message || 'Failed to delete router.', 'danger');
                }
            })
            .catch(function () { NOC.showToast('Network error.', 'danger'); })
            .finally(function () {
                btn.disabled = false;
                btn.textContent = 'Delete';
            });
    });

    // -------------------------------------------------------------------------
    // Test SNMP connection
    // -------------------------------------------------------------------------
    function testSnmp(routerId, resultEl) {
        if (!routerId) return;

        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const btnEl = document.getElementById('test-snmp-btn');

        if (btnEl) {
            btnEl.disabled = true;
            btnEl.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Testing…';
        }

        if (resultEl) {
            resultEl.style.display = 'block';
            resultEl.className = 'alert alert-info mt-2';
            resultEl.textContent = 'Testing SNMP connection…';
        }

        fetch('/routers/' + routerId + '/test', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf,
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ _csrf: csrf }),
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                const ok = data.data?.reachable ?? false;
                if (resultEl) {
                    resultEl.className = 'alert ' + (ok ? 'alert-success' : 'alert-danger') + ' mt-2';
                    resultEl.textContent = ok ? '✓ SNMP reachable — router is responding.' : '✗ ' + (data.message || 'SNMP did not respond.');
                }
                NOC.showToast(ok ? 'SNMP test passed.' : 'SNMP test failed.', ok ? 'success' : 'danger');
            })
            .catch(function () {
                if (resultEl) {
                    resultEl.className = 'alert alert-danger mt-2';
                    resultEl.textContent = '✗ Network error during SNMP test.';
                }
                NOC.showToast('Network error.', 'danger');
            })
            .finally(function () {
                if (btnEl) {
                    btnEl.disabled = false;
                    btnEl.innerHTML = '<i class="fas fa-plug me-1"></i>Test SNMP';
                }
            });
    }

    document.getElementById('test-snmp-btn')?.addEventListener('click', function () {
        const id     = parseInt(this.dataset.id, 10) || 0;
        const result = document.getElementById('snmp-test-result');
        testSnmp(id, result);
    });

    // -------------------------------------------------------------------------
    // Refresh router info
    // -------------------------------------------------------------------------
    document.getElementById('refresh-info-btn')?.addEventListener('click', function () {
        const id  = parseInt(this.dataset.id, 10) || 0;
        if (!id) return;

        const btn  = this;
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Refreshing…';

        fetch('/routers/' + id + '/refresh', {
            method: 'POST',
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
                    NOC.showToast('Router info refreshed.', 'success');
                    setTimeout(function () { location.reload(); }, 800);
                } else {
                    NOC.showToast(data.message || 'Refresh failed.', 'danger');
                }
            })
            .catch(function () { NOC.showToast('Network error.', 'danger'); })
            .finally(function () {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-sync me-1"></i>Refresh Info';
            });
    });

    // -------------------------------------------------------------------------
    // Table search filter
    // -------------------------------------------------------------------------
    document.getElementById('router-search')?.addEventListener('input', function () {
        const q = this.value.toLowerCase();
        document.querySelectorAll('#routers-table tbody tr').forEach(function (row) {
            row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
    });

})();
