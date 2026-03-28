/**
 * NOC Dashboard - Dashboard Real-time Updates
 */
const NOCDashboard = (function () {
  'use strict';

  let trafficChart = null;
  let interfaceChart = null;
  const REFRESH_INTERVAL = 30000;

  function init() {
    fetchStats();
    initTrafficChart();
    initInterfacesChart();
    NOCApp.startAutoRefresh(fetchStats, REFRESH_INTERVAL);
    startRefreshCountdown();
  }

  function fetchStats() {
    updateRefreshIndicator(true);
    NOCApp.get('/api/v1/dashboard/stats')
      .then(function (data) {
        if (!data || !data.success) return;
        updateStatCards(data.stats);
        updateRouterGrid(data.routers);
        updateRecentEvents(data.events);
        updateTrafficChart(data.traffic24h);
        updateTickerBandwidth(data.stats);
      })
      .catch(function (err) {
        console.warn('[Dashboard] Stats fetch error:', err);
      })
      .finally(function () {
        updateRefreshIndicator(false);
      });
  }

  function updateStatCards(stats) {
    if (!stats) return;
    setElText('stat-total-routers', stats.total_routers || 0);
    setElText('stat-online-routers', stats.online_routers || 0);
    setElText('stat-offline-routers', stats.offline_routers || 0);
    setElText('stat-monitored-interfaces', stats.monitored_interfaces || 0);
    setElText('stat-bandwidth-in', NOCApp.formatBytes(stats.total_bandwidth_in || 0));
    setElText('stat-bandwidth-out', NOCApp.formatBytes(stats.total_bandwidth_out || 0));
    setElText('stat-active-sessions', stats.active_pppoe || 0);
    setElText('stat-active-queues', stats.active_queues || 0);
  }

  function updateRouterGrid(routers) {
    const container = document.getElementById('router-status-grid');
    if (!container || !routers) return;
    container.innerHTML = '';
    routers.forEach(function (router) {
      const card = document.createElement('div');
      card.className = 'router-card';
      const statusClass = router.status === 'online' ? 'online' : 'offline';
      card.innerHTML = [
        '<div class="router-card-header">',
        '  <div>',
        '    <div class="router-name">' + NOCApp.escapeHtml(router.name) + '</div>',
        '    <div class="router-ip">' + NOCApp.escapeHtml(router.ip_address) + '</div>',
        '  </div>',
        '  <span class="badge badge-' + statusClass + '">' + router.status + '</span>',
        '</div>',
        '<div class="router-stats">',
        '  <div class="router-stat">',
        '    <div class="router-stat-value">' + (router.interface_count || 0) + '</div>',
        '    <div class="router-stat-label">Interfaces</div>',
        '  </div>',
        '  <div class="router-stat">',
        '    <div class="router-stat-value bandwidth-in">' + NOCApp.formatBytes(router.bandwidth_in || 0) + '</div>',
        '    <div class="router-stat-label">In</div>',
        '  </div>',
        '  <div class="router-stat">',
        '    <div class="router-stat-value bandwidth-out">' + NOCApp.formatBytes(router.bandwidth_out || 0) + '</div>',
        '    <div class="router-stat-label">Out</div>',
        '  </div>',
        '</div>',
        '<div style="margin-top:12px;">',
        '  <a href="/routers/' + router.id + '" class="btn btn-secondary btn-sm">View</a>',
        '</div>',
      ].join('');
      container.appendChild(card);
    });
    if (routers.length === 0) {
      container.innerHTML = '<div style="color:var(--text-muted);text-align:center;padding:24px;">No routers found.</div>';
    }
  }

  function updateRecentEvents(events) {
    const tbody = document.getElementById('recent-events-tbody');
    if (!tbody || !events) return;
    tbody.innerHTML = '';
    events.forEach(function (evt) {
      const tr = document.createElement('tr');
      tr.innerHTML = [
        '<td><code>' + NOCApp.escapeHtml(evt.created_at || '') + '</code></td>',
        '<td>' + NOCApp.escapeHtml(evt.action || '') + '</td>',
        '<td>' + NOCApp.escapeHtml(evt.target || '') + '</td>',
        '<td>' + NOCApp.escapeHtml(evt.user || 'system') + '</td>',
      ].join('');
      tbody.appendChild(tr);
    });
    if (events.length === 0) {
      tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;color:var(--text-muted);">No recent events.</td></tr>';
    }
  }

  function initTrafficChart() {
    const canvas = document.getElementById('traffic-24h-chart');
    if (!canvas || typeof Chart === 'undefined') return;
    const labels = generateTimeLabels(24);
    trafficChart = NOCCharts.createTrafficChart('traffic-24h-chart', labels, new Array(24).fill(0), new Array(24).fill(0));
  }

  function initInterfacesChart() {
    const canvas = document.getElementById('top-interfaces-chart');
    if (!canvas || typeof Chart === 'undefined') return;
    interfaceChart = NOCCharts.createBarChart('top-interfaces-chart', [], []);
  }

  function updateTrafficChart(traffic) {
    if (!trafficChart || !traffic) return;
    NOCCharts.updateChart(trafficChart, traffic.labels, traffic.in_data, traffic.out_data);
  }

  function updateTickerBandwidth(stats) {
    const ticker = document.getElementById('ticker-bandwidth');
    if (!ticker || !stats) return;
    ticker.textContent = 'IN: ' + NOCApp.formatBytes(stats.total_bandwidth_in || 0) +
      '  |  OUT: ' + NOCApp.formatBytes(stats.total_bandwidth_out || 0) +
      '  |  Routers Online: ' + (stats.online_routers || 0) + '/' + (stats.total_routers || 0);
  }

  function generateTimeLabels(hours) {
    const labels = [];
    const now = new Date();
    for (let i = hours - 1; i >= 0; i--) {
      const d = new Date(now.getTime() - i * 3600000);
      labels.push(d.getHours().toString().padStart(2, '0') + ':00');
    }
    return labels;
  }

  function setElText(id, val) {
    const el = document.getElementById(id);
    if (el) el.textContent = val;
  }

  function updateRefreshIndicator(loading) {
    const el = document.getElementById('refresh-spinner');
    if (el) el.style.display = loading ? 'inline-block' : 'none';
  }

  let countdownTimer = null;
  function startRefreshCountdown() {
    let seconds = REFRESH_INTERVAL / 1000;
    const el = document.getElementById('refresh-countdown');
    if (!el) return;
    clearInterval(countdownTimer);
    countdownTimer = setInterval(function () {
      seconds--;
      el.textContent = seconds + 's';
      if (seconds <= 0) {
        seconds = REFRESH_INTERVAL / 1000;
      }
    }, 1000);
  }

  return { init: init, fetchStats: fetchStats };
})();

document.addEventListener('DOMContentLoaded', function () {
  if (document.getElementById('dashboard-page')) {
    NOCDashboard.init();
  }
});
