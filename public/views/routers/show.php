<?php
/**
 * Routers — Show (Detail) View
 */
/** @var array $router */
?>
<div id="router-show-page">

  <div class="d-flex align-items-center gap-2 mb-4">
    <a href="/routers" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
    <div>
      <h4 class="mb-0">
        <i class="fas fa-network-wired me-2 text-primary"></i>
        <?= htmlspecialchars($router['name']) ?>
      </h4>
      <small class="text-muted"><?= htmlspecialchars($router['ip_address']) ?></small>
    </div>
    <div class="ms-auto d-flex gap-2">
      <button class="btn btn-info btn-sm" id="test-snmp-btn" data-id="<?= (int) $router['id'] ?>">
        <i class="fas fa-plug me-1"></i>Test SNMP
      </button>
      <button class="btn btn-secondary btn-sm" id="refresh-info-btn" data-id="<?= (int) $router['id'] ?>">
        <i class="fas fa-sync me-1"></i>Refresh Info
      </button>
      <a href="/routers/<?= (int) $router['id'] ?>/edit" class="btn btn-warning btn-sm">
        <i class="fas fa-edit me-1"></i>Edit
      </a>
    </div>
  </div>

  <!-- Status bar -->
  <div class="row g-3 mb-4">
    <div class="col-md-3">
      <?php
      $statusClass = match($router['status'] ?? 'inactive') {
          'active' => 'green', 'error' => 'red', default => 'purple'
      };
      ?>
      <div class="stat-card <?= $statusClass ?>">
        <div class="stat-icon"><i class="fas fa-circle"></i></div>
        <div class="stat-value"><?= htmlspecialchars(ucfirst($router['status'] ?? 'inactive')) ?></div>
        <div class="stat-label">Status</div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="stat-card blue">
        <div class="stat-icon"><i class="fas fa-ethernet"></i></div>
        <div class="stat-value"><?= (int) ($router['interface_count'] ?? 0) ?></div>
        <div class="stat-label">Interfaces</div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="stat-card purple">
        <div class="stat-icon"><i class="fas fa-layer-group"></i></div>
        <div class="stat-value"><?= (int) ($router['queue_count'] ?? 0) ?></div>
        <div class="stat-label">Queues</div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="stat-card orange">
        <div class="stat-icon"><i class="fas fa-users"></i></div>
        <div class="stat-value"><?= (int) ($router['pppoe_count'] ?? 0) ?></div>
        <div class="stat-label">PPPoE Users</div>
      </div>
    </div>
  </div>

  <div class="row g-3">
    <!-- System info -->
    <div class="col-lg-6">
      <div class="card">
        <div class="card-header">
          <h5 class="card-title"><i class="fas fa-info-circle me-2 text-info"></i>System Information</h5>
        </div>
        <div class="card-body">
          <table class="table-dark-custom w-100">
            <tbody>
              <tr><td class="text-muted" style="width:40%">Name</td><td><?= htmlspecialchars($router['name']) ?></td></tr>
              <tr><td class="text-muted">IP Address</td><td><code><?= htmlspecialchars($router['ip_address']) ?></code></td></tr>
              <tr><td class="text-muted">SNMP Version</td><td><?= htmlspecialchars($router['snmp_version'] ?? '2c') ?></td></tr>
              <tr><td class="text-muted">SNMP Community</td><td><code><?= htmlspecialchars($router['snmp_community'] ?? 'public') ?></code></td></tr>
              <tr><td class="text-muted">SNMP Port</td><td><?= (int) ($router['snmp_port'] ?? 161) ?></td></tr>
              <?php if (!empty($router['sysName'])): ?>
                <tr><td class="text-muted">sysName</td><td><?= htmlspecialchars($router['sysName']) ?></td></tr>
              <?php endif; ?>
              <?php if (!empty($router['sysDescr'])): ?>
                <tr><td class="text-muted">sysDescr</td><td><small><?= htmlspecialchars($router['sysDescr']) ?></small></td></tr>
              <?php endif; ?>
              <?php if (!empty($router['sysUptime'])): ?>
                <tr><td class="text-muted">Uptime</td><td><?= htmlspecialchars($router['sysUptime']) ?></td></tr>
              <?php endif; ?>
              <tr><td class="text-muted">Last Seen</td><td><?= htmlspecialchars($router['last_seen'] ?? 'Never') ?></td></tr>
            </tbody>
          </table>
          <div id="snmp-test-result" class="mt-3" style="display:none;"></div>
        </div>
      </div>
    </div>

    <!-- Quick actions -->
    <div class="col-lg-6">
      <div class="card">
        <div class="card-header">
          <h5 class="card-title"><i class="fas fa-tools me-2 text-warning"></i>Quick Actions</h5>
        </div>
        <div class="card-body">
          <div class="d-grid gap-2">
            <a href="/routers/<?= (int) $router['id'] ?>/interfaces/discover" class="btn btn-primary">
              <i class="fas fa-search me-2"></i>Discover Interfaces
            </a>
            <a href="/routers/<?= (int) $router['id'] ?>/queues/discover" class="btn btn-primary">
              <i class="fas fa-layer-group me-2"></i>Discover Queues
            </a>
            <a href="/routers/<?= (int) $router['id'] ?>/pppoe/discover" class="btn btn-primary">
              <i class="fas fa-users me-2"></i>Discover PPPoE Users
            </a>
            <a href="/interfaces?router_id=<?= (int) $router['id'] ?>" class="btn btn-secondary">
              <i class="fas fa-ethernet me-2"></i>View Interfaces
            </a>
            <a href="/queues?router_id=<?= (int) $router['id'] ?>" class="btn btn-secondary">
              <i class="fas fa-list me-2"></i>View Queues
            </a>
            <a href="/pppoe?router_id=<?= (int) $router['id'] ?>" class="btn btn-secondary">
              <i class="fas fa-users me-2"></i>View PPPoE Users
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>

</div>

<?php $extraScripts = '<script src="' . APP_URL . '/assets/js/router-manager.js"></script>'; ?>
