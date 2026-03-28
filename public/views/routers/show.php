<?php
/**
 * Router Detail View
 *
 * @var array $router  Router record with details
 */
$pageTitle = 'Router: ' . htmlspecialchars($router['name'] ?? '');
?>
<div id="router-show-page">

  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <a href="/routers" class="text-muted text-decoration-none small">
        <i class="fas fa-arrow-left me-1"></i> Back to Routers
      </a>
      <h2 class="page-title mt-1">
        <i class="fas fa-network-wired me-2 text-primary"></i>
        <?= htmlspecialchars($router['name'] ?? 'Router') ?>
      </h2>
    </div>
    <div class="btn-group">
      <a href="/routers/<?= (int) $router['id'] ?>/edit" class="btn btn-outline-primary">
        <i class="fas fa-edit me-1"></i> Edit
      </a>
      <a href="/routers/<?= (int) $router['id'] ?>/interfaces/discover" class="btn btn-outline-info">
        <i class="fas fa-search me-1"></i> Discover Interfaces
      </a>
    </div>
  </div>

  <div class="row g-3">
    <!-- Router Info Card -->
    <div class="col-lg-5">
      <div class="card h-100">
        <div class="card-header">
          <h5 class="card-title mb-0"><i class="fas fa-info-circle me-2 text-info"></i>Router Info</h5>
        </div>
        <div class="card-body">
          <dl class="row mb-0">
            <dt class="col-sm-4 text-muted">IP Address</dt>
            <dd class="col-sm-8"><code><?= htmlspecialchars($router['ip_address'] ?? '') ?></code></dd>

            <dt class="col-sm-4 text-muted">SNMP Version</dt>
            <dd class="col-sm-8">v<?= htmlspecialchars($router['snmp_version'] ?? '2c') ?></dd>

            <dt class="col-sm-4 text-muted">Community</dt>
            <dd class="col-sm-8"><code><?= htmlspecialchars($router['snmp_community'] ?? 'public') ?></code></dd>

            <dt class="col-sm-4 text-muted">SNMP Port</dt>
            <dd class="col-sm-8"><?= (int) ($router['snmp_port'] ?? 161) ?></dd>

            <dt class="col-sm-4 text-muted">Status</dt>
            <dd class="col-sm-8">
              <?php $status = $router['status'] ?? 'unknown'; ?>
              <span class="badge bg-<?= $status === 'active' ? 'success' : ($status === 'error' ? 'danger' : 'secondary') ?>">
                <?= htmlspecialchars($status) ?>
              </span>
            </dd>

            <dt class="col-sm-4 text-muted">System Name</dt>
            <dd class="col-sm-8"><?= htmlspecialchars($router['identity'] ?? '—') ?></dd>

            <dt class="col-sm-4 text-muted">System Description</dt>
            <dd class="col-sm-8 text-muted small"><?= htmlspecialchars($router['model'] ?? '—') ?></dd>

            <dt class="col-sm-4 text-muted">Last Polled</dt>
            <dd class="col-sm-8 text-muted small">
              <?= $router['last_seen'] ? htmlspecialchars($router['last_seen']) : '—' ?>
            </dd>

            <dt class="col-sm-4 text-muted">Created</dt>
            <dd class="col-sm-8 text-muted small">
              <?= $router['created_at'] ? htmlspecialchars($router['created_at']) : '—' ?>
            </dd>
          </dl>
        </div>
        <div class="card-footer d-flex gap-2">
          <button type="button"
                  class="btn btn-sm btn-outline-info"
                  id="btn-test-snmp"
                  data-id="<?= (int) $router['id'] ?>">
            <i class="fas fa-plug me-1"></i> Test SNMP
          </button>
          <button type="button"
                  class="btn btn-sm btn-outline-secondary"
                  id="btn-refresh-info"
                  data-id="<?= (int) $router['id'] ?>">
            <i class="fas fa-sync me-1"></i> Refresh Info
          </button>
        </div>
      </div>
    </div>

    <!-- Interfaces Card -->
    <div class="col-lg-7">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="card-title mb-0">
            <i class="fas fa-ethernet me-2 text-success"></i>Interfaces
          </h5>
          <a href="/routers/<?= (int) $router['id'] ?>/interfaces/discover" class="btn btn-sm btn-outline-success">
            <i class="fas fa-search me-1"></i> Discover
          </a>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
              <thead>
                <tr>
                  <th>Index</th>
                  <th>Name</th>
                  <th>Alias</th>
                  <th>Speed</th>
                  <th>Status</th>
                  <th>Monitor</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($router['interfaces'])): ?>
                  <tr>
                    <td colspan="6" class="text-center text-muted py-3">
                      No interfaces. <a href="/routers/<?= (int) $router['id'] ?>/interfaces/discover">Discover now</a>.
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($router['interfaces'] as $iface): ?>
                    <tr>
                      <td><?= (int) $iface['if_index'] ?></td>
                      <td><a href="/interfaces/<?= (int) $iface['id'] ?>"><?= htmlspecialchars($iface['name'] ?? '') ?></a></td>
                      <td class="text-muted small"><?= htmlspecialchars($iface['alias'] ?? '') ?></td>
                      <td class="text-muted small"><?= htmlspecialchars($iface['speed'] ?? '') ?></td>
                      <td>
                        <?php $os = (int) ($iface['oper_status'] ?? 0); ?>
                        <span class="badge bg-<?= $os === 1 ? 'success' : 'secondary' ?>">
                          <?= $os === 1 ? 'up' : 'down' ?>
                        </span>
                      </td>
                      <td>
                        <?= $iface['monitored'] ? '<i class="fas fa-eye text-success"></i>' : '<i class="fas fa-eye-slash text-secondary"></i>' ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="<?= APP_URL ?>/assets/js/router-manager.js" defer></script>
