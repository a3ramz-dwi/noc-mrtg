<?php
/**
 * Monitoring — Queue Traffic View
 */
$pageTitle = 'Monitoring — Queues';
?>
<div id="monitoring-queues-page">

  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="page-title">
      <i class="fas fa-chart-bar me-2 text-warning"></i>Queue Traffic Monitoring
    </h2>
    <div class="btn-group btn-group-sm">
      <a href="/monitoring/interfaces" class="btn btn-outline-secondary">Interfaces</a>
      <a href="/monitoring/pppoe"      class="btn btn-outline-secondary">PPPoE</a>
      <a href="/monitoring/live"       class="btn btn-outline-info">Live</a>
    </div>
  </div>

  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="card-title mb-0">
        <i class="fas fa-layer-group me-2 text-warning"></i>Monitored Queues
      </h5>
      <div class="d-flex align-items-center gap-2">
        <select class="form-select form-select-sm" id="timerange" style="width:auto;">
          <option value="1">1 Hour</option>
          <option value="6">6 Hours</option>
          <option value="24" selected>24 Hours</option>
          <option value="168">7 Days</option>
        </select>
        <button class="btn btn-sm btn-outline-primary" id="refresh-btn">
          <i class="fas fa-sync"></i>
        </button>
      </div>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0" id="monitoring-table">
          <thead>
            <tr>
              <th>Router</th>
              <th>Queue Name</th>
              <th>Target</th>
              <th>In (bps)</th>
              <th>Out (bps)</th>
              <th>Max Limit</th>
              <th>Last Updated</th>
              <th>Chart</th>
            </tr>
          </thead>
          <tbody id="monitoring-tbody">
            <tr>
              <td colspan="8" class="text-center text-muted py-4">
                <span class="spinner-border spinner-border-sm me-2"></span>
                Loading monitoring data…
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
