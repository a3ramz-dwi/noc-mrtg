/**
 * NOC Dashboard - Main Application JavaScript
 */
const NOCApp = (function () {
  'use strict';

  // Config
  const config = {
    csrfToken: null,
    theme: localStorage.getItem('noc-theme') || 'dark',
    autoRefreshInterval: 30000,
    autoRefreshTimer: null,
    sidebarOpen: false,
  };

  // Init
  function init() {
    config.csrfToken = document.querySelector('meta[name="csrf-token"]')
      ? document.querySelector('meta[name="csrf-token"]').getAttribute('content')
      : '';
    applyTheme(config.theme);
    bindGlobalEvents();
    initTableSearch();
    processFlashMessages();
    console.log('[NOC] App initialized, theme:', config.theme);
  }

  // AJAX Helpers
  function request(method, url, data) {
    const opts = {
      method: method.toUpperCase(),
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-Token': config.csrfToken,
      },
    };
    if (data && method.toUpperCase() !== 'GET') {
      opts.body = JSON.stringify(data);
    }
    return fetch(url, opts).then(function (res) {
      if (!res.ok) {
        return res.json().catch(function () {
          throw new Error('HTTP ' + res.status);
        }).then(function (err) {
          throw err;
        });
      }
      return res.json();
    });
  }

  function get(url) { return request('GET', url); }
  function post(url, data) { return request('POST', url, data); }
  function put(url, data) { return request('PUT', url, data); }
  function del(url) { return request('DELETE', url); }

  // Flash messages
  function showFlash(message, type) {
    type = type || 'info';
    const container = document.getElementById('flash-container') || createFlashContainer();
    const el = document.createElement('div');
    el.className = 'alert alert-' + type + ' alert-dismissible fade-in';
    el.innerHTML = '<i class="fas fa-' + getFlashIcon(type) + '"></i> ' + escapeHtml(message) +
      '<button type="button" class="alert-close" onclick="this.parentElement.remove()">&times;</button>';
    container.appendChild(el);
    setTimeout(function () { el.remove(); }, 5000);
  }

  function getFlashIcon(type) {
    const icons = { success: 'check-circle', danger: 'exclamation-circle', warning: 'exclamation-triangle', info: 'info-circle' };
    return icons[type] || 'info-circle';
  }

  function createFlashContainer() {
    const c = document.createElement('div');
    c.id = 'flash-container';
    c.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;min-width:300px;max-width:420px;';
    document.body.appendChild(c);
    return c;
  }

  function processFlashMessages() {
    document.querySelectorAll('[data-flash]').forEach(function (el) {
      showFlash(el.dataset.flash, el.dataset.flashType || 'info');
      el.remove();
    });
  }

  // Confirmation dialogs
  function confirm(message, callback) {
    const modal = document.getElementById('confirm-modal');
    if (modal) {
      document.getElementById('confirm-message').textContent = message;
      document.getElementById('confirm-ok').onclick = function () {
        hideModal('confirm-modal');
        callback(true);
      };
      document.getElementById('confirm-cancel').onclick = function () {
        hideModal('confirm-modal');
        callback(false);
      };
      showModal('confirm-modal');
    } else {
      callback(window.confirm(message));
    }
  }

  function showModal(id) {
    const m = document.getElementById(id);
    if (m) { m.style.display = 'flex'; m.classList.add('active'); }
  }

  function hideModal(id) {
    const m = document.getElementById(id);
    if (m) { m.style.display = 'none'; m.classList.remove('active'); }
  }

  // Form validation
  function validateForm(formEl) {
    let valid = true;
    formEl.querySelectorAll('[required]').forEach(function (field) {
      const err = formEl.querySelector('[data-error="' + field.name + '"]');
      if (!field.value.trim()) {
        valid = false;
        field.classList.add('is-invalid');
        if (err) err.textContent = field.dataset.errorMsg || 'This field is required.';
      } else {
        field.classList.remove('is-invalid');
        if (err) err.textContent = '';
      }
    });
    return valid;
  }

  // Theme switcher
  function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    document.body.classList.toggle('dark-mode', theme === 'dark');
    document.body.classList.toggle('light-mode', theme === 'light');
    const btn = document.getElementById('theme-toggle');
    if (btn) {
      btn.innerHTML = theme === 'dark'
        ? '<i class="fas fa-sun"></i>'
        : '<i class="fas fa-moon"></i>';
      btn.title = theme === 'dark' ? 'Switch to Light Mode' : 'Switch to Dark Mode';
    }
    config.theme = theme;
    localStorage.setItem('noc-theme', theme);
  }

  function toggleTheme() {
    applyTheme(config.theme === 'dark' ? 'light' : 'dark');
  }

  // Auto-refresh
  function startAutoRefresh(callback, interval) {
    stopAutoRefresh();
    interval = interval || config.autoRefreshInterval;
    config.autoRefreshTimer = setInterval(callback, interval);
  }

  function stopAutoRefresh() {
    if (config.autoRefreshTimer) {
      clearInterval(config.autoRefreshTimer);
      config.autoRefreshTimer = null;
    }
  }

  // Sidebar toggle
  function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    if (sidebar) {
      config.sidebarOpen = !config.sidebarOpen;
      sidebar.classList.toggle('open', config.sidebarOpen);
    }
  }

  // Table search/filter
  function initTableSearch() {
    document.querySelectorAll('[data-table-search]').forEach(function (input) {
      const tableId = input.dataset.tableSearch;
      const table = document.getElementById(tableId);
      if (!table) return;
      input.addEventListener('input', function () {
        const q = this.value.toLowerCase();
        table.querySelectorAll('tbody tr').forEach(function (row) {
          const text = row.textContent.toLowerCase();
          row.style.display = text.includes(q) ? '' : 'none';
        });
      });
    });
  }

  // HTML escape
  function escapeHtml(str) {
    const d = document.createElement('div');
    d.appendChild(document.createTextNode(str));
    return d.innerHTML;
  }

  // Bind global events
  function bindGlobalEvents() {
    const themeBtn = document.getElementById('theme-toggle');
    if (themeBtn) themeBtn.addEventListener('click', toggleTheme);

    const sidebarToggle = document.getElementById('sidebar-toggle');
    if (sidebarToggle) sidebarToggle.addEventListener('click', toggleSidebar);

    // Confirm delete buttons
    document.querySelectorAll('[data-confirm]').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        const msg = this.dataset.confirm || 'Are you sure?';
        const href = this.href || this.dataset.action;
        NOCApp.confirm(msg, function (ok) {
          if (ok && href) window.location.href = href;
        });
      });
    });

    // Method override forms
    document.querySelectorAll('[data-method]').forEach(function (el) {
      el.addEventListener('click', function (e) {
        e.preventDefault();
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = this.href || this.dataset.action;
        const inp = document.createElement('input');
        inp.type = 'hidden'; inp.name = '_method'; inp.value = this.dataset.method;
        form.appendChild(inp);
        document.body.appendChild(form);
        form.submit();
      });
    });
  }

  // Format bytes
  function formatBytes(bytes, decimals) {
    decimals = decimals === undefined ? 2 : decimals;
    if (!bytes || bytes === 0) return '0 bps';
    const k = 1000;
    const sizes = ['bps', 'Kbps', 'Mbps', 'Gbps', 'Tbps'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(decimals)) + ' ' + sizes[i];
  }

  // Public API
  return {
    init: init,
    get: get,
    post: post,
    put: put,
    del: del,
    showFlash: showFlash,
    confirm: confirm,
    toggleTheme: toggleTheme,
    startAutoRefresh: startAutoRefresh,
    stopAutoRefresh: stopAutoRefresh,
    toggleSidebar: toggleSidebar,
    validateForm: validateForm,
    formatBytes: formatBytes,
    escapeHtml: escapeHtml,
  };
})();

document.addEventListener('DOMContentLoaded', function () { NOCApp.init(); });
