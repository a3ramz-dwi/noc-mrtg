<?php
/**
 * Monitoring — Live Traffic View
 */
?>
<div id="monitoring-live-page">

  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h4 class="mb-0"><i class="fas fa-broadcast-tower me-2 text-success"></i>Live Traffic Monitor</h4>
      <small class="text-muted">Real-time bandwidth across all monitored entities — auto-refreshes every 10s</small>
    </div>
    <div class="d-flex gap-2 align-items-center">
      <span class="badge badge-success" id="live-badge">
        <span class="spinner-grow spinner-grow-sm me-1" role="status"></span>LIVE
      </span>
      <select id="filter-type" class="form-select form-select-sm" style="width:150px;">
        <option value="all">All Types</option>
        <option value="interface">Interfaces</option>
        <option value="queue">Queues</option>
        <option value="pppoe">PPPoE</option>
      </select>
      <select id="filter-router" class="form-select form-select-sm" style="width:180px;">
        <option value="">All Routers</option>
      </select>
    </div>
  </div>

  <!-- Total bandwidth ticker -->
  <div class="live-ticker mb-4">
    <span class="ticker-label">TOTAL BW</span>
    <span class="ticker-content">
      <i class="fas fa-arrow-down text-success me-1"></i>In: <strong id="ticker-in">0 bps</strong>
      &nbsp;&nbsp;
      <i class="fas fa-arrow-up text-danger me-1"></i>Out: <strong id="ticker-out">0 bps</strong>
    </span>
    <span class="text-muted small ms-3">
      Updated: <span id="ticker-time">—</span>
    </span>
  </div>

  <!-- Live chart -->
  <div class="card mb-4">
    <div class="card-header">
      <h5 class="card-title"><i class="fas fa-chart-line me-2 text-primary"></i>Total Bandwidth (Last 2 minutes)</h5>
    </div>
    <div class="card-body">
      <div class="chart-container" style="height:200px;">
        <canvas id="live-bandwidth-chart"></canvas>
      </div>
    </div>
  </div>

  <!-- Top consumers -->
  <div class="row g-3 mb-4">
    <div class="col-lg-6">
      <div class="card">
        <div class="card-header">
          <h5 class="card-title"><i class="fas fa-arrow-down text-success me-2"></i>Top Consumers — Inbound</h5>
        </div>
        <div class="card-body p-0">
          <table class="table-dark-custom w-100" id="top-in-table">
            <thead>
              <tr><th>#</th><th>Name</th><th>Type</th><th>Router</th><th>Inbound</th></tr>
            </thead>
            <tbody id="top-in-tbody">
              <tr><td colspan="5" class="text-center text-muted py-3"><span class="spinner"></span></td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="card">
        <div class="card-header">
          <h5 class="card-title"><i class="fas fa-arrow-up text-danger me-2"></i>Top Consumers — Outbound</h5>
        </div>
        <div class="card-body p-0">
          <table class="table-dark-custom w-100" id="top-out-table">
            <thead>
              <tr><th>#</th><th>Name</th><th>Type</th><th>Router</th><th>Outbound</th></tr>
            </thead>
            <tbody id="top-out-tbody">
              <tr><td colspan="5" class="text-center text-muted py-3"><span class="spinner"></span></td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- All entities -->
  <div class="card">
    <div class="card-header">
      <h5 class="card-title"><i class="fas fa-list me-2"></i>All Monitored Entities</h5>
      <input type="text" id="entity-search" class="form-control form-control-sm" placeholder="Search..." style="width:200px;">
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table-dark-custom w-100" id="live-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Type</th>
              <th>Router</th>
              <th>Inbound</th>
              <th>Outbound</th>
              <th>Trend</th>
            </tr>
          </thead>
          <tbody id="live-tbody">
            <tr><td colspan="6" class="text-center text-muted py-4"><span class="spinner"></span> Connecting to live feed...</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>

<?php $extraScripts = '<script src="' . APP_URL . '/assets/js/live-traffic.js"></script>'; ?>
