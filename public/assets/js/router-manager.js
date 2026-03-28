/**
 * router-manager.js — Router CRUD UI helpers
 *
 * Handles: delete confirmation, SNMP test, refresh info.
 * Depends on: NOCApp (app.js)
 */
(function () {
  'use strict';

  const CSRF = () =>
    document.querySelector('meta[name="csrf-token"]')?.content ?? '';

  // -------------------------------------------------------------------------
  // Delete router
  // -------------------------------------------------------------------------
  document.querySelectorAll('.btn-delete-router').forEach((btn) => {
    btn.addEventListener('click', () => {
      const id   = btn.dataset.id;
      const name = btn.dataset.name ?? 'this router';

      if (!confirm(`Delete router "${name}"?\n\nThis will also remove all associated interfaces, queues, and PPPoE users.`)) {
        return;
      }

      fetch(`/routers/${id}`, {
        method:  'DELETE',
        headers: {
          'X-CSRF-TOKEN':   CSRF(),
          'X-Requested-With': 'XMLHttpRequest',
        },
      })
        .then((r) => r.json())
        .then((data) => {
          if (data.success) {
            NOCApp.showFlash('Router deleted.', 'success');
            btn.closest('tr')?.remove();
          } else {
            NOCApp.showFlash(data.message ?? 'Delete failed.', 'danger');
          }
        })
        .catch(() => NOCApp.showFlash('Network error.', 'danger'));
    });
  });

  // -------------------------------------------------------------------------
  // Test SNMP connection
  // -------------------------------------------------------------------------
  document.querySelectorAll('.btn-test-snmp, #btn-test-snmp').forEach((btn) => {
    btn.addEventListener('click', () => {
      const id = btn.dataset.id;
      const originalText = btn.innerHTML;

      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Testing…';

      fetch(`/routers/${id}/test`, {
        method:  'POST',
        headers: {
          'X-CSRF-TOKEN':     CSRF(),
          'X-Requested-With': 'XMLHttpRequest',
          'Accept':           'application/json',
        },
      })
        .then((r) => r.json())
        .then((data) => {
          const ok  = data.data?.reachable ?? false;
          const msg = ok ? '✓ Router is reachable via SNMP.' : '✗ Router did not respond.';
          NOCApp.showFlash(msg, ok ? 'success' : 'warning');
        })
        .catch(() => NOCApp.showFlash('Network error during SNMP test.', 'danger'))
        .finally(() => {
          btn.disabled = false;
          btn.innerHTML = originalText;
        });
    });
  });

  // -------------------------------------------------------------------------
  // Refresh system info
  // -------------------------------------------------------------------------
  const refreshBtn = document.getElementById('btn-refresh-info');
  if (refreshBtn) {
    refreshBtn.addEventListener('click', () => {
      const id = refreshBtn.dataset.id;
      refreshBtn.disabled = true;
      refreshBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Refreshing…';

      fetch(`/routers/${id}/refresh`, {
        method:  'POST',
        headers: {
          'X-CSRF-TOKEN':     CSRF(),
          'X-Requested-With': 'XMLHttpRequest',
          'Accept':           'application/json',
        },
      })
        .then((r) => r.json())
        .then((data) => {
          if (data.success) {
            NOCApp.showFlash('Router info refreshed. Reloading…', 'success');
            setTimeout(() => location.reload(), 1200);
          } else {
            NOCApp.showFlash(data.message ?? 'Refresh failed.', 'danger');
          }
        })
        .catch(() => NOCApp.showFlash('Network error.', 'danger'))
        .finally(() => {
          refreshBtn.disabled = false;
          refreshBtn.innerHTML = '<i class="fas fa-sync me-1"></i> Refresh Info';
        });
    });
  }
}());
