<?php
/**
 * PPPoE — Index View
 */
/** @var array $users */
/** @var array $routers */
?>
<div id="pppoe-page">

  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h4 class="mb-0"><i class="fas fa-users me-2 text-primary"></i>PPPoE User Management</h4>
      <small class="text-muted">Monitor PPPoE user bandwidth</small>
    </div>
    <div class="d-flex gap-2">
      <?php if (!empty($routers)): ?>
        <div class="dropdown">
          <button class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown">
            <i class="fas fa-search me-1"></i>Discover PPPoE Users
          </button>
          <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end">
            <?php foreach ($routers as $r): ?>
              <li>
                <a class="dropdown-item" href="/routers/<?= (int) $r['id'] ?>/pppoe/discover">
                  <i class="fas fa-network-wired me-2"></i><?= htmlspecialchars($r['name']) ?>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Filter bar -->
  <div class="card mb-3">
    <div class="card-body py-2">
      <div class="row g-2 align-items-center">
        <div class="col-md-4">
          <input type="text" id="pppoe-search" class="form-control form-control-sm" placeholder="Search by username, IP...">
        </div>
        <div class="col-md-3">
          <select id="filter-router" class="form-select form-select-sm">
            <option value="">All Routers</option>
            <?php foreach ($routers ?? [] as $r): ?>
              <option value="<?= (int) $r['id'] ?>"><?= htmlspecialchars($r['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <select id="filter-status" class="form-select form-select-sm">
            <option value="">All Status</option>
            <option value="connected">Connected</option>
            <option value="disconnected">Disconnected</option>
          </select>
        </div>
        <div class="col-md-3 text-end">
          <span class="text-muted small">
            Total: <strong id="pppoe-count"><?= count($users ?? []) ?></strong> users
          </span>
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table-dark-custom w-100" id="pppoe-table">
          <thead>
            <tr>
              <th>Username</th>
              <th>Router</th>
              <th>IP Address</th>
              <th>Interface</th>
              <th>Connection Status</th>
              <th>Monitored</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($users)): ?>
              <tr>
                <td colspan="7" class="text-center text-muted py-4">
                  <i class="fas fa-users me-2"></i>No PPPoE users found.
                  Discover users from a router first.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($users as $user): ?>
                <tr data-router-id="<?= (int) $user['router_id'] ?>" data-status="<?= htmlspecialchars($user['status'] ?? '') ?>">
                  <td>
                    <a href="/pppoe/<?= (int) $user['id'] ?>" class="text-primary fw-bold">
                      <?= htmlspecialchars($user['name'] ?? '') ?>
                    </a>
                    <?php if (!empty($user['comment'])): ?>
                      <br><small class="text-muted"><?= htmlspecialchars(substr($user['comment'], 0, 40)) ?></small>
                    <?php endif; ?>
                  </td>
                  <td><?= htmlspecialchars($user['router_name'] ?? '') ?></td>
                  <td><code><?= htmlspecialchars($user['ip_address'] ?? '—') ?></code></td>
                  <td><?= htmlspecialchars($user['interface_name'] ?? '—') ?></td>
                  <td>
                    <?php
                    $statusClass = ($user['status'] ?? '') === 'connected' ? 'badge-success' : 'badge-secondary';
                    ?>
                    <span class="badge <?= $statusClass ?>"><?= htmlspecialchars(ucfirst($user['status'] ?? 'unknown')) ?></span>
                  </td>
                  <td>
                    <div class="form-check form-switch">
                      <input class="form-check-input toggle-monitor" type="checkbox"
                        data-id="<?= (int) $user['id'] ?>"
                        <?= (int) ($user['monitored'] ?? 0) === 1 ? 'checked' : '' ?>>
                    </div>
                  </td>
                  <td>
                    <div class="btn-group btn-group-sm">
                      <a href="/pppoe/<?= (int) $user['id'] ?>" class="btn btn-secondary" title="View">
                        <i class="fas fa-eye"></i>
                      </a>
                      <a href="/monitoring/pppoe?user_id=<?= (int) $user['id'] ?>" class="btn btn-info" title="Traffic">
                        <i class="fas fa-chart-line"></i>
                      </a>
                      <button class="btn btn-danger btn-delete-pppoe" data-id="<?= (int) $user['id'] ?>" title="Delete">
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

<?php $extraScripts = '<script src="' . APP_URL . '/assets/js/pppoe-manager.js"></script>'; ?>
