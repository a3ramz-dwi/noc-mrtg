/**
 * pppoe-manager.js — PPPoE monitor toggle, delete, discover helpers.
 *
 * Depends on: NOCApp (app.js)
 */
(function () {
  'use strict';

  const CSRF = () =>
    document.querySelector('meta[name="csrf-token"]')?.content ?? '';

  // -------------------------------------------------------------------------
  // Monitor toggle
  // -------------------------------------------------------------------------
  document.querySelectorAll('.toggle-monitor').forEach((chk) => {
    chk.addEventListener('change', () => {
      const id = chk.dataset.id;

      fetch(`/pppoe/${id}/toggle-monitor`, {
        method:  'POST',
        headers: {
          'X-CSRF-TOKEN':     CSRF(),
          'X-Requested-With': 'XMLHttpRequest',
          'Accept':           'application/json',
        },
      })
        .then((r) => r.json())
        .then((data) => {
          if (!data.success) {
            chk.checked = !chk.checked;
            NOCApp.showFlash(data.message ?? 'Toggle failed.', 'danger');
          }
        })
        .catch(() => {
          chk.checked = !chk.checked;
          NOCApp.showFlash('Network error.', 'danger');
        });
    });
  });

  // -------------------------------------------------------------------------
  // Delete PPPoE user
  // -------------------------------------------------------------------------
  document.querySelectorAll('.btn-delete-pppoe').forEach((btn) => {
    btn.addEventListener('click', () => {
      if (!confirm('Remove this PPPoE user from monitoring?')) return;

      const id = btn.dataset.id;

      fetch(`/pppoe/${id}`, {
        method:  'DELETE',
        headers: {
          'X-CSRF-TOKEN':     CSRF(),
          'X-Requested-With': 'XMLHttpRequest',
        },
      })
        .then((r) => r.json())
        .then((data) => {
          if (data.success) {
            NOCApp.showFlash('PPPoE user removed.', 'success');
            btn.closest('tr')?.remove();
          } else {
            NOCApp.showFlash(data.message ?? 'Delete failed.', 'danger');
          }
        })
        .catch(() => NOCApp.showFlash('Network error.', 'danger'));
    });
  });

  // -------------------------------------------------------------------------
  // Discover — select-all checkbox
  // -------------------------------------------------------------------------
  const checkAll = document.getElementById('check-all');
  if (checkAll) {
    checkAll.addEventListener('change', () => {
      document.querySelectorAll('.pppoe-check').forEach((c) => {
        c.checked = checkAll.checked;
      });
    });
  }
}());
