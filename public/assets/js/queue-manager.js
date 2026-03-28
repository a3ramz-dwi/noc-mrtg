/**
 * queue-manager.js — Queue monitor toggle, delete, discover helpers.
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

      fetch(`/queues/${id}/toggle-monitor`, {
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
  // Delete queue
  // -------------------------------------------------------------------------
  document.querySelectorAll('.btn-delete-queue').forEach((btn) => {
    btn.addEventListener('click', () => {
      if (!confirm('Remove this queue from monitoring?')) return;

      const id = btn.dataset.id;

      fetch(`/queues/${id}`, {
        method:  'DELETE',
        headers: {
          'X-CSRF-TOKEN':     CSRF(),
          'X-Requested-With': 'XMLHttpRequest',
        },
      })
        .then((r) => r.json())
        .then((data) => {
          if (data.success) {
            NOCApp.showFlash('Queue removed.', 'success');
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
      document.querySelectorAll('.queue-check').forEach((c) => {
        c.checked = checkAll.checked;
      });
    });
  }
}());
