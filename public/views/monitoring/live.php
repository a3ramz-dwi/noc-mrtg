<?php
/**
 * Live Monitoring View — Real-time traffic dashboard
 */
$pageTitle = 'Live Monitoring';
?>
<div id="live-monitoring-page">

  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="page-title">
      <i class="fas fa-broadcast-tower me-2 text-danger"></i>Live Monitoring
      <span class="badge bg-danger ms-2 blink" style="font-size:12px;">LIVE</span>
    </h2>
    <div class="btn-group btn-group-sm">
      <a href="/monitoring/interfaces" class="btn btn-outline-secondary">Interfaces</a>
      <a href="/monitoring/queues"     class="btn btn-outline-secondary">Queues</a>
      <a href="/monitoring/pppoe"      class="btn btn-outline-secondary">PPPoE</a>
    </div>
  </div>

  <!-- Live Stats Row -->
  <div class="row g-3 mb-4" id="live-stats">
    <div class="col-md-3">
      <div class="stat-card blue">
        <div class="stat-icon"><i class="fas fa-arrow-down"></i></div>
        <div class="stat-value" id="live-total-in">—</div>
        <div class="stat-label">Total In</div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="stat-card orange">
        <div class="stat-icon"><i class="fas fa-arrow-up"></i></div>
        <div class="stat-value" id="live-total-out">—</div>
        <div class="stat-label">Total Out</div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="stat-card green">
        <div class="stat-icon"><i class="fas fa-ethernet"></i></div>
        <div class="stat-value" id="live-active-ifaces">—</div>
        <div class="stat-label">Active Interfaces</div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="stat-card purple">
        <div class="stat-icon"><i class="fas fa-clock"></i></div>
        <div class="stat-value" id="live-last-update">—</div>
        <div class="stat-label">Last Updated</div>
      </div>
    </div>
  </div>

  <!-- Live Chart -->
  <div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="card-title mb-0">
        <i class="fas fa-chart-line me-2 text-primary"></i>Live Network Traffic
      </h5>
      <div class="d-flex gap-2 align-items-center">
        <span class="text-muted small" id="refresh-label">Refreshes every 30s</span>
        <button class="btn btn-sm btn-outline-secondary" id="pause-btn">
          <i class="fas fa-pause"></i> Pause
        </button>
      </div>
    </div>
    <div class="card-body">
      <div class="chart-container" style="height:300px;">
        <canvas id="live-chart"></canvas>
      </div>
    </div>
  </div>

  <!-- Top Interfaces Table -->
  <div class="card">
    <div class="card-header">
      <h5 class="card-title mb-0">
        <i class="fas fa-sort-amount-down me-2 text-warning"></i>Top Interfaces by Traffic
      </h5>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover table-sm mb-0">
          <thead>
            <tr>
              <th>#</th>
              <th>Interface</th>
              <th>Router</th>
              <th>In (bps)</th>
              <th>Out (bps)</th>
              <th>Utilization</th>
            </tr>
          </thead>
          <tbody id="top-interfaces-tbody">
            <tr>
              <td colspan="6" class="text-center text-muted py-3">
                <span class="spinner-border spinner-border-sm me-2"></span>
                Loading…
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<style>
@keyframes blink { 50% { opacity: 0; } }
.blink { animation: blink 1s step-start infinite; }
</style>

<script src="<?= APP_URL ?>/assets/js/charts.js" defer></script>
<script src="<?= APP_URL ?>/assets/js/live-traffic.js" defer></script>
