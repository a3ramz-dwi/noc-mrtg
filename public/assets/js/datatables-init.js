/**
 * datatables-init.js — Bootstrap DataTables on all tables with id ending in "-table".
 *
 * Gracefully skips initialisation if the DataTables library is not loaded.
 */
(function () {
  'use strict';

  function initTables () {
    const tables = document.querySelectorAll('table[id$="-table"]');

    if (!tables.length) return;

    // If jQuery + DataTables are available, use them.
    if (typeof window.jQuery !== 'undefined' && typeof window.jQuery.fn.DataTable !== 'undefined') {
      tables.forEach((table) => {
        window.jQuery(table).DataTable({
          pageLength:    25,
          responsive:    true,
          order:         [],
          language: {
            search:      'Filter:',
            lengthMenu:  'Show _MENU_ entries',
            info:        'Showing _START_ to _END_ of _TOTAL_ entries',
            paginate: {
              previous:  '«',
              next:      '»',
            },
          },
        });
      });
      return;
    }

    // Fallback: a lightweight client-side filter without jQuery.
    tables.forEach((table) => {
      const wrapper = document.createElement('div');
      wrapper.className = 'mb-2 d-flex justify-content-end';

      const input = document.createElement('input');
      input.type        = 'search';
      input.className   = 'form-control form-control-sm';
      input.style.width = '240px';
      input.placeholder = 'Filter…';
      input.setAttribute('aria-label', 'Filter table');

      input.addEventListener('input', () => {
        const q     = input.value.toLowerCase().trim();
        const rows  = table.querySelectorAll('tbody tr');

        rows.forEach((row) => {
          const text = row.textContent.toLowerCase();
          row.style.display = q === '' || text.includes(q) ? '' : 'none';
        });
      });

      wrapper.appendChild(input);
      table.parentElement?.insertBefore(wrapper, table);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initTables);
  } else {
    initTables();
  }
}());
