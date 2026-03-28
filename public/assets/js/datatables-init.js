/**
 * datatables-init.js — DataTables initialization for NOC Manager tables
 *
 * Provides a lightweight client-side table enhancement:
 * sorting, pagination, and search on tables with class `dt-table`.
 * Falls back gracefully if DataTables library is not loaded.
 */
(function () {
    'use strict';

    // -------------------------------------------------------------------------
    // Built-in lightweight table sorter (no external dependency)
    // -------------------------------------------------------------------------

    /**
     * Attach sorting behaviour to a <table> element.
     * Clicking a <th> sorts by that column; clicking again reverses.
     *
     * @param {HTMLTableElement} table
     */
    function initSortableTable(table) {
        const headers = table.querySelectorAll('thead th');
        const tbody   = table.tBodies[0];
        if (!tbody) return;

        let sortedCol = -1;
        let sortAsc   = true;

        headers.forEach(function (th, colIdx) {
            th.style.cursor = 'pointer';
            th.title        = 'Click to sort';

            th.addEventListener('click', function () {
                if (sortedCol === colIdx) {
                    sortAsc = !sortAsc;
                } else {
                    sortedCol = colIdx;
                    sortAsc   = true;
                }

                // Clear all sort indicators.
                headers.forEach(function (h) {
                    h.classList.remove('sort-asc', 'sort-desc');
                });
                th.classList.add(sortAsc ? 'sort-asc' : 'sort-desc');

                const rows = Array.from(tbody.querySelectorAll('tr:not(.dt-empty)'));

                rows.sort(function (a, b) {
                    const aText = (a.cells[colIdx]?.textContent || '').trim();
                    const bText = (b.cells[colIdx]?.textContent || '').trim();

                    // Numeric sort.
                    const aNum = parseFloat(aText);
                    const bNum = parseFloat(bText);
                    let cmp;

                    if (!isNaN(aNum) && !isNaN(bNum)) {
                        cmp = aNum - bNum;
                    } else {
                        cmp = aText.localeCompare(bText, undefined, { numeric: true });
                    }

                    return sortAsc ? cmp : -cmp;
                });

                rows.forEach(function (row) { tbody.appendChild(row); });
            });
        });
    }

    // -------------------------------------------------------------------------
    // Pagination helper
    // -------------------------------------------------------------------------

    /**
     * Add simple pagination controls below a table.
     *
     * @param {HTMLTableElement} table
     * @param {number}           pageSize  Rows per page (default 25).
     */
    function initPagination(table, pageSize) {
        pageSize = pageSize || 25;

        const tbody    = table.tBodies[0];
        if (!tbody) return;

        let currentPage = 1;

        const container = document.createElement('div');
        container.className = 'dt-pagination d-flex justify-content-between align-items-center p-2 text-muted small';
        table.parentNode.insertBefore(container, table.nextSibling);

        function getVisibleRows() {
            return Array.from(tbody.querySelectorAll('tr:not(.dt-empty)')).filter(function (r) {
                return r.style.display !== 'none';
            });
        }

        function render() {
            const rows      = getVisibleRows();
            const total     = rows.length;
            const totalPages = Math.max(1, Math.ceil(total / pageSize));

            currentPage = Math.min(currentPage, totalPages);

            const start = (currentPage - 1) * pageSize;
            const end   = Math.min(start + pageSize, total);

            rows.forEach(function (row, idx) {
                row.setAttribute('data-page-hidden', idx < start || idx >= end ? '1' : '0');
                row.style.display = (idx < start || idx >= end) ? 'none' : '';
            });

            container.innerHTML =
                '<span>Showing ' + (total ? start + 1 : 0) + '–' + end + ' of ' + total + '</span>' +
                '<div class="btn-group btn-group-sm">' +
                '<button class="btn btn-secondary dt-prev" ' + (currentPage <= 1 ? 'disabled' : '') + '>«</button>' +
                '<button class="btn btn-secondary dt-page" disabled>' + currentPage + ' / ' + totalPages + '</button>' +
                '<button class="btn btn-secondary dt-next" ' + (currentPage >= totalPages ? 'disabled' : '') + '>»</button>' +
                '</div>';

            container.querySelector('.dt-prev')?.addEventListener('click', function () {
                if (currentPage > 1) { currentPage--; render(); }
            });
            container.querySelector('.dt-next')?.addEventListener('click', function () {
                if (currentPage < totalPages) { currentPage++; render(); }
            });
        }

        // Re-render when external filters change row visibility.
        const obs = new MutationObserver(function () { render(); });
        obs.observe(tbody, { attributes: true, subtree: true, attributeFilter: ['style'] });

        render();

        // Expose re-render for external use.
        table._dtPaginationRender = render;
    }

    // -------------------------------------------------------------------------
    // Auto-init on DOMContentLoaded
    // -------------------------------------------------------------------------
    function init() {
        document.querySelectorAll('table.dt-table, table[data-dt]').forEach(function (table) {
            const pageSize = parseInt(table.dataset.pageSize, 10) || 25;
            const noSort   = table.dataset.noSort !== undefined;
            const noPaging = table.dataset.noPaging !== undefined;

            if (!noSort)   initSortableTable(table);
            if (!noPaging) initPagination(table, pageSize);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Expose init for dynamic tables.
    window.NOCDataTables = { initSortable: initSortableTable, initPagination: initPagination, init: init };

})();
