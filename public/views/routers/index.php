<?php
/**
 * Routers — Index View
 */
/** @var array $routers */
?>
<div id="routers-page">

  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h4 class="mb-0"><i class="fas fa-network-wired me-2 text-primary"></i>Router Management</h4>
      <small class="text-muted">Manage and monitor your MikroTik routers</small>
    </div>
    <a href="/routers/create" class="btn btn-primary">
      <i class="fas fa-plus me-1"></i>Add Router
    </a>
  </div>

  <!-- Stats row -->
  <div class="row g-3 mb-4">
    <div class="col-md-3">
      <div class="stat-card blue">
        <div class="stat-icon"><i class="fas fa-server"></i></div>
        <div class="stat-value" id="cnt-total"><?= count($routers ?? []) ?></div>
        <div class="stat-label">Total Routers</div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="stat-card green">
        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
        <div class="stat-value" id="cnt-active"><?= count(array_filter($routers ?? [], fn($r) => $r['status'] === 'active')) ?></div>
        <div class="stat-label">Active</div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="stat-card red">
        <div class="stat-icon"><i class="fas fa-exclamation-circle"></i></div>
        <div class="stat-value" id="cnt-error"><?= count(array_filter($routers ?? [], fn($r) => $r['status'] === 'error')) ?></div>
        <div class="stat-label">Error</div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="stat-card purple">
        <div class="stat-icon"><i class="fas fa-pause-circle"></i></div>
        <div class="stat-value" id="cnt-inactive"><?= count(array_filter($routers ?? [], fn($r) => $r['status'] === 'inactive')) ?></div>
        <div class="stat-label">Inactive</div>
      </div>
    </div>
  </div>

  <!-- Router table -->
  <div class="card">
    <div class="card-header">
      <h5 class="card-title"><i class="fas fa-list me-2"></i>All Routers</h5>
      <div class="d-flex gap-2">
        <input type="text" id="router-search" class="form-control form-control-sm" placeholder="Search..." style="width:200px;">
      </div>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table-dark-custom w-100" id="routers-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>IP Address</th>
              <th>SNMP</th>
              <th>Interfaces</th>
              <th>Status</th>
              <th>Last Seen</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($routers)): ?>
              <tr>
                <td colspan="7" class="text-center text-muted py-4">
                  <i class="fas fa-info-circle me-2"></i>No routers found.
                  <a href="/routers/create" class="ms-2">Add your first router →</a>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($routers as $router): ?>
                <tr>
                  <td>
                    <a href="/routers/<?= (int) $router['id'] ?>" class="text-primary fw-bold">
                      <?= htmlspecialchars($router['name']) ?>
                    </a>
                    <?php if (!empty($router['sysDescr'])): ?>
                      <br><small class="text-muted"><?= htmlspecialchars(substr($router['sysDescr'], 0, 50)) ?></small>
                    <?php endif; ?>
                  </td>
                  <td><code><?= htmlspecialchars($router['ip_address']) ?></code></td>
                  <td>
                    <span class="badge badge-info">v<?= htmlspecialchars($router['snmp_version'] ?? '2c') ?></span>
                    <small class="text-muted">:<?= (int) ($router['snmp_port'] ?? 161) ?></small>
                  </td>
                  <td>
                    <a href="/interfaces?router_id=<?= (int) $router['id'] ?>" class="text-muted">
                      <?= (int) ($router['interface_count'] ?? 0) ?> ifaces
                    </a>
                  </td>
                  <td>
                    <?php
                    $statusClass = match($router['status'] ?? 'inactive') {
                        'active'   => 'badge-success',
                        'error'    => 'badge-danger',
                        default    => 'badge-secondary',
                    };
                    ?>
                    <span class="badge <?= $statusClass ?>"><?= htmlspecialchars($router['status'] ?? 'inactive') ?></span>
                  </td>
                  <td>
                    <small class="text-muted">
                      <?= !empty($router['last_seen']) ? htmlspecialchars($router['last_seen']) : 'Never' ?>
                    </small>
                  </td>
                  <td>
                    <div class="btn-group btn-group-sm">
                      <a href="/routers/<?= (int) $router['id'] ?>" class="btn btn-secondary" title="View">
                        <i class="fas fa-eye"></i>
                      </a>
                      <a href="/routers/<?= (int) $router['id'] ?>/edit" class="btn btn-secondary" title="Edit">
                        <i class="fas fa-edit"></i>
                      </a>
                      <button class="btn btn-info btn-test-snmp" data-id="<?= (int) $router['id'] ?>" title="Test SNMP">
                        <i class="fas fa-plug"></i>
                      </button>
                      <button class="btn btn-danger btn-delete-router" data-id="<?= (int) $router['id'] ?>" data-name="<?= htmlspecialchars($router['name']) ?>" title="Delete">
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

  <!-- Delete confirm modal -->
  <div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content bg-dark border border-secondary">
        <div class="modal-header border-secondary">
          <h5 class="modal-title text-danger"><i class="fas fa-trash me-2"></i>Delete Router</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p>Are you sure you want to delete router <strong id="delete-router-name"></strong>?</p>
          <p class="text-warning small"><i class="fas fa-exclamation-triangle me-1"></i>This will also delete all associated interfaces, queues, and traffic data.</p>
        </div>
        <div class="modal-footer border-secondary">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-danger" id="confirm-delete-btn">Delete</button>
        </div>
      </div>
    </div>
  </div>

</div>

<?php $extraScripts = '<script src="' . APP_URL . '/assets/js/router-manager.js"></script>'; ?>
