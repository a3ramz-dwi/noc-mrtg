<?php
/**
 * Dashboard Index View
 * Wrapped by main.php layout via $content buffer
 */
?>
<div id="dashboard-page">

  <!-- Live ticker -->
  <div class="live-ticker mb-4">
    <span class="ticker-label">LIVE</span>
    <span class="ticker-content" id="ticker-bandwidth">Loading traffic data...</span>
    <span class="text-muted small">
      <span class="spinner" id="refresh-spinner-ticker" style="display:none;"></span>
      Next: <span id="refresh-countdown">30s</span>
    </span>
  </div>

  <!-- Stat Cards Row -->
  <div class="row g-3 mb-4">
    <div class="col-xl-2 col-md-4 col-sm-6">
      <div class="stat-card blue">
        <div class="stat-icon"><i class="fas fa-network-wired"></i></div>
        <div class="stat-value" id="stat-total-routers">--</div>
        <div class="stat-label">Total Routers</div>
      </div>
    </div>
    <div class="col-xl-2 col-md-4 col-sm-6">
      <div class="stat-card green">
        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
        <div class="stat-value" id="stat-online-routers">--</div>
        <div class="stat-label">Online</div>
      </div>
    </div>
    <div class="col-xl-2 col-md-4 col-sm-6">
      <div class="stat-card red">
        <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
        <div class="stat-value" id="stat-offline-routers">--</div>
        <div class="stat-label">Offline</div>
      </div>
    </div>
    <div class="col-xl-2 col-md-4 col-sm-6">
      <div class="stat-card purple">
        <div class="stat-icon"><i class="fas fa-ethernet"></i></div>
        <div class="stat-value" id="stat-monitored-interfaces">--</div>
        <div class="stat-label">Monitored IFs</div>
      </div>
    </div>
    <div class="col-xl-2 col-md-4 col-sm-6">
      <div class="stat-card green">
        <div class="stat-icon"><i class="fas fa-arrow-down"></i></div>
        <div class="stat-value bandwidth-in" id="stat-bandwidth-in">--</div>
        <div class="stat-label">Total In</div>
      </div>
    </div>
    <div class="col-xl-2 col-md-4 col-sm-6">
      <div class="stat-card orange">
        <div class="stat-icon"><i class="fas fa-arrow-up"></i></div>
        <div class="stat-value bandwidth-out" id="stat-bandwidth-out">--</div>
        <div class="stat-label">Total Out</div>
      </div>
    </div>
  </div>

  <!-- Charts Row -->
  <div class="row g-3 mb-4">
    <div class="col-lg-8">
      <div class="card">
        <div class="card-header">
          <h5 class="card-title"><i class="fas fa-chart-line me-2 text-primary"></i>Network Traffic (24h)</h5>
          <span class="badge badge-info">Auto-refresh</span>
        </div>
        <div class="card-body">
          <div class="chart-container" style="height:280px;">
            <canvas id="traffic-24h-chart"></canvas>
          </div>
        </div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card">
        <div class="card-header">
          <h5 class="card-title"><i class="fas fa-chart-bar me-2 text-warning"></i>Top 10 Interfaces</h5>
        </div>
        <div class="card-body">
          <div class="chart-container" style="height:280px;">
            <canvas id="top-interfaces-chart"></canvas>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Router Grid + Events Row -->
  <div class="row g-3 mb-4">
    <div class="col-lg-7">
      <div class="card">
        <div class="card-header">
          <h5 class="card-title"><i class="fas fa-server me-2 text-info"></i>Router Status</h5>
          <a href="/routers" class="btn btn-secondary btn-sm">View All</a>
        </div>
        <div class="card-body">
          <div class="router-grid" id="router-status-grid">
            <div style="color:var(--text-muted);text-align:center;padding:24px;">
              <span class="spinner"></span> Loading routers...
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-lg-5">
      <div class="card">
        <div class="card-header">
          <h5 class="card-title"><i class="fas fa-history me-2 text-success"></i>Recent Events</h5>
        </div>
        <div class="card-body p-0">
          <table class="table-dark-custom w-100">
            <thead>
              <tr>
                <th>Time</th>
                <th>Action</th>
                <th>Target</th>
                <th>User</th>
              </tr>
            </thead>
            <tbody id="recent-events-tbody">
              <tr><td colspan="4" style="text-align:center;color:var(--text-muted);padding:20px;">Loading...</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Quick Stats Row -->
  <div class="row g-3">
    <div class="col-md-6">
      <div class="card">
        <div class="card-header">
          <h5 class="card-title"><i class="fas fa-users me-2 text-purple"></i>PPPoE &amp; Queues</h5>
          <div class="d-flex gap-2">
            <a href="/pppoe" class="btn btn-secondary btn-sm">PPPoE</a>
            <a href="/queues" class="btn btn-secondary btn-sm">Queues</a>
          </div>
        </div>
        <div class="card-body">
          <div class="row text-center">
            <div class="col-6">
              <div class="bandwidth-display" id="stat-active-sessions" style="color:var(--accent-purple)">--</div>
              <div class="stat-label">Active PPPoE</div>
            </div>
            <div class="col-6">
              <div class="bandwidth-display" id="stat-active-queues" style="color:var(--accent-blue)">--</div>
              <div class="stat-label">Active Queues</div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card">
        <div class="card-header">
          <h5 class="card-title"><i class="fas fa-tools me-2 text-warning"></i>Quick Actions</h5>
        </div>
        <div class="card-body">
          <div class="d-flex flex-wrap gap-2">
            <a href="/routers/create" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i>Add Router</a>
            <a href="/mrtg/generate" class="btn btn-warning btn-sm"><i class="fas fa-cog me-1"></i>Gen MRTG</a>
            <a href="/monitoring/live" class="btn btn-success btn-sm"><i class="fas fa-broadcast-tower me-1"></i>Live View</a>
            <a href="/settings" class="btn btn-secondary btn-sm"><i class="fas fa-sliders-h me-1"></i>Settings</a>
          </div>
        </div>
      </div>
    </div>
  </div>

</div>
