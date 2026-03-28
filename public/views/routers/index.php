<?php
/**
 * Routers Index View
 *
 * @var array $routers  List of router records from RouterService::listRouters()
 */
$pageTitle = 'Routers';
?>
<div id="routers-page">

  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="page-title"><i class="fas fa-network-wired me-2 text-primary"></i>Routers</h2>
    <a href="/routers/create" class="btn btn-primary">
      <i class="fas fa-plus me-1"></i> Add Router
    </a>
  </div>

  <?php if (!empty($_SESSION['_flash']['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      <?= htmlspecialchars($_SESSION['_flash']['success']) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <?php if (!empty($_SESSION['_flash']['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <?= htmlspecialchars($_SESSION['_flash']['error']) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="card-title mb-0"><i class="fas fa-list me-2"></i>All Routers</h5>
      <span class="badge bg-primary"><?= count($routers ?? []) ?> total</span>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0" id="routers-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Name</th>
              <th>IP Address</th>
              <th>SNMP</th>
              <th>Status</th>
              <th>Interfaces</th>
              <th>Last Polled</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($routers)): ?>
              <tr>
                <td colspan="8" class="text-center text-muted py-4">
                  <i class="fas fa-info-circle me-1"></i> No routers found.
                  <a href="/routers/create">Add your first router</a>.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($routers as $router): ?>
                <tr>
                  <td><?= (int) $router['id'] ?></td>
                  <td>
                    <a href="/routers/<?= (int) $router['id'] ?>" class="fw-semibold">
                      <?= htmlspecialchars($router['name']) ?>
                    </a>
                  </td>
                  <td><code><?= htmlspecialchars($router['ip_address']) ?></code></td>
                  <td>
                    v<?= htmlspecialchars($router['snmp_version'] ?? '2c') ?>
                    / <?= htmlspecialchars($router['snmp_community'] ?? 'public') ?>
                  </td>
                  <td>
                    <?php $status = $router['status'] ?? 'unknown'; ?>
                    <span class="badge bg-<?= $status === 'active' ? 'success' : ($status === 'error' ? 'danger' : 'secondary') ?>">
                      <?= htmlspecialchars($status) ?>
                    </span>
                  </td>
                  <td><?= (int) ($router['interface_count'] ?? 0) ?></td>
                  <td class="text-muted small">
                    <?= $router['last_polled'] ? htmlspecialchars($router['last_polled']) : '—' ?>
                  </td>
                  <td class="text-end">
                    <div class="btn-group btn-group-sm">
                      <a href="/routers/<?= (int) $router['id'] ?>" class="btn btn-outline-secondary" title="View">
                        <i class="fas fa-eye"></i>
                      </a>
                      <a href="/routers/<?= (int) $router['id'] ?>/edit" class="btn btn-outline-primary" title="Edit">
                        <i class="fas fa-edit"></i>
                      </a>
                      <button type="button"
                              class="btn btn-outline-info btn-test-snmp"
                              data-id="<?= (int) $router['id'] ?>"
                              title="Test SNMP">
                        <i class="fas fa-plug"></i>
                      </button>
                      <button type="button"
                              class="btn btn-outline-danger btn-delete-router"
                              data-id="<?= (int) $router['id'] ?>"
                              data-name="<?= htmlspecialchars($router['name']) ?>"
                              title="Delete">
                        <i class="fas fa-trash"></i>
                      </button>
                    </div>
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

<script src="<?= APP_URL ?>/assets/js/router-manager.js" defer></script>
