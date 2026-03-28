<?php
/**
 * PPPoE Users Index View
 *
 * @var array $users    List of PPPoE user records
 * @var array $routers  List of routers for discover links
 */
$pageTitle = 'PPPoE Users';
?>
<div id="pppoe-page">

  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="page-title"><i class="fas fa-users me-2 text-info"></i>PPPoE Users</h2>
    <div class="dropdown">
      <button class="btn btn-outline-info dropdown-toggle" data-bs-toggle="dropdown">
        <i class="fas fa-search me-1"></i> Discover
      </button>
      <ul class="dropdown-menu dropdown-menu-end">
        <?php if (empty($routers)): ?>
          <li><span class="dropdown-item-text text-muted">No routers available</span></li>
        <?php else: ?>
          <?php foreach ($routers as $r): ?>
            <li>
              <a class="dropdown-item" href="/routers/<?= (int) $r['id'] ?>/pppoe/discover">
                <?= htmlspecialchars($r['name']) ?>
              </a>
            </li>
          <?php endforeach; ?>
        <?php endif; ?>
      </ul>
    </div>
  </div>

  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="card-title mb-0"><i class="fas fa-users me-2"></i>All PPPoE Users</h5>
      <span class="badge bg-info"><?= count($users ?? []) ?> total</span>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0" id="pppoe-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Router</th>
              <th>Username</th>
              <th>IP Address</th>
              <th>Service</th>
              <th>Status</th>
              <th>Monitor</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($users)): ?>
              <tr>
                <td colspan="8" class="text-center text-muted py-4">
                  <i class="fas fa-info-circle me-1"></i>
                  No PPPoE users found. Use <strong>Discover</strong> to import users.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($users as $u): ?>
                <tr>
                  <td><?= (int) $u['id'] ?></td>
                  <td>
                    <a href="/routers/<?= (int) $u['router_id'] ?>">
                      <?= htmlspecialchars($u['router_name'] ?? '—') ?>
                    </a>
                  </td>
                  <td>
                    <a href="/pppoe/<?= (int) $u['id'] ?>">
                      <?= htmlspecialchars($u['username'] ?? '') ?>
                    </a>
                  </td>
                  <td><code><?= htmlspecialchars($u['ip_address'] ?? '—') ?></code></td>
                  <td class="text-muted small"><?= htmlspecialchars($u['service'] ?? '—') ?></td>
                  <td>
                    <?php $status = $u['status'] ?? 'disconnected'; ?>
                    <span class="badge bg-<?= $status === 'connected' ? 'success' : 'secondary' ?>">
                      <?= htmlspecialchars($status) ?>
                    </span>
                  </td>
                  <td>
                    <div class="form-check form-switch">
                      <input class="form-check-input toggle-monitor"
                             type="checkbox"
                             data-id="<?= (int) $u['id'] ?>"
                             <?= !empty($u['monitor']) ? 'checked' : '' ?>>
                    </div>
                  </td>
                  <td class="text-end">
                    <div class="btn-group btn-group-sm">
                      <a href="/pppoe/<?= (int) $u['id'] ?>" class="btn btn-outline-secondary" title="View">
                        <i class="fas fa-eye"></i>
                      </a>
                      <button type="button"
                              class="btn btn-outline-danger btn-delete-pppoe"
                              data-id="<?= (int) $u['id'] ?>"
                              title="Remove">
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

<script src="<?= APP_URL ?>/assets/js/pppoe-manager.js" defer></script>
