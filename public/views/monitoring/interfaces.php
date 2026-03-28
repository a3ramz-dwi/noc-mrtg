<?php
/**
 * Monitoring — Interfaces View
 */
?>
<div id="monitoring-interfaces-page">

  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h4 class="mb-0"><i class="fas fa-chart-area me-2 text-primary"></i>Interface Traffic Monitoring</h4>
      <small class="text-muted">Real-time bandwidth monitoring for router interfaces</small>
    </div>
    <div class="d-flex gap-2 align-items-center">
      <select id="filter-router" class="form-select form-select-sm" style="width:180px;">
        <option value="">All Routers</option>
      </select>
      <select id="chart-range" class="form-select form-select-sm" style="width:130px;">
        <option value="1h">Last 1h</option>
        <option value="24h" selected>Last 24h</option>
        <option value="7d">Last 7 days</option>
      </select>
      <button class="btn btn-secondary btn-sm" id="refresh-btn">
        <i class="fas fa-sync"></i>
      </button>
    </div>
  </div>

  <!-- Summary cards -->
  <div class="row g-3 mb-4" id="monitor-summary">
    <div class="col-md-3">
      <div class="stat-card blue">
        <div class="stat-icon"><i class="fas fa-ethernet"></i></div>
        <div class="stat-value" id="cnt-monitored">—</div>
        <div class="stat-label">Monitored Interfaces</div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="stat-card green">
        <div class="stat-icon"><i class="fas fa-arrow-down"></i></div>
        <div class="bandwidth-display bandwidth-in" id="total-bw-in">0 bps</div>
        <div class="stat-label">Total Inbound</div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="stat-card orange">
        <div class="stat-icon"><i class="fas fa-arrow-up"></i></div>
        <div class="bandwidth-display bandwidth-out" id="total-bw-out">0 bps</div>
        <div class="stat-label">Total Outbound</div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="stat-card purple">
        <div class="stat-icon"><i class="fas fa-clock"></i></div>
        <div class="stat-value" id="last-updated">—</div>
        <div class="stat-label">Last Updated</div>
      </div>
    </div>
  </div>

  <!-- Interfaces list with mini charts -->
  <div class="card">
    <div class="card-header">
      <h5 class="card-title"><i class="fas fa-list me-2"></i>Monitored Interfaces</h5>
      <input type="text" id="iface-search" class="form-control form-control-sm" placeholder="Search..." style="width:200px;">
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table-dark-custom w-100" id="monitor-table">
          <thead>
            <tr>
              <th>Interface</th>
              <th>Router</th>
              <th>Inbound</th>
              <th>Outbound</th>
              <th>Utilisation</th>
              <th>Status</th>
              <th></th>
            </tr>
          </thead>
          <tbody id="interfaces-tbody">
            <tr><td colspan="7" class="text-center text-muted py-4"><span class="spinner"></span> Loading...</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Detail chart modal -->
  <div class="modal fade" id="chartModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content bg-dark border border-secondary">
        <div class="modal-header border-secondary">
          <h5 class="modal-title" id="chartModalLabel">Interface Traffic</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="chart-container" style="height:320px;">
            <canvas id="modal-chart"></canvas>
          </div>
        </div>
      </div>
    </div>
  </div>

</div>

<script>
window.MONITOR_TYPE = 'interface';
</script>
<?php $extraScripts = '<script src="' . APP_URL . '/assets/js/interface-manager.js"></script>'; ?>
