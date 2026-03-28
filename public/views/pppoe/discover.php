<?php
/**
 * PPPoE Discovery View
 *
 * @var array  $router      Router being polled
 * @var array  $discovered  Discovered PPPoE sessions
 */
$pageTitle = 'Discover PPPoE: ' . htmlspecialchars($router['name'] ?? '');
?>
<div id="pppoe-discover-page">

  <div class="mb-4">
    <a href="/routers/<?= (int) $router['id'] ?>" class="text-muted text-decoration-none small">
      <i class="fas fa-arrow-left me-1"></i> Back to Router
    </a>
    <h2 class="page-title mt-1">
      <i class="fas fa-search me-2 text-info"></i>
      Discover PPPoE — <?= htmlspecialchars($router['name'] ?? '') ?>
    </h2>
  </div>

  <?php if (!empty($error)): ?>
    <div class="alert alert-danger">
      <i class="fas fa-exclamation-triangle me-2"></i>
      <?= htmlspecialchars($error) ?>
    </div>
  <?php endif; ?>

  <?php if (!empty($discovered)): ?>
    <div class="card">
      <div class="card-header">
        <h5 class="card-title mb-0">
          <i class="fas fa-users me-2 text-info"></i>
          Discovered PPPoE Users (<?= count($discovered) ?>)
        </h5>
      </div>
      <div class="card-body p-0">
        <form method="POST" action="/pppoe/import" id="import-form">
          <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? '') ?>">
          <input type="hidden" name="router_id" value="<?= (int) $router['id'] ?>">

          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead>
                <tr>
                  <th width="40"><input type="checkbox" id="check-all" class="form-check-input"></th>
                  <th>Username</th>
                  <th>IP Address</th>
                  <th>MAC Address</th>
                  <th>Service</th>
                  <th>Status</th>
                  <th>Imported</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($discovered as $u): ?>
                  <tr>
                    <td>
                      <?php if (empty($u['imported'])): ?>
                        <input type="checkbox"
                               name="users[]"
                               value="<?= htmlspecialchars($u['username']) ?>"
                               class="form-check-input pppoe-check">
                      <?php else: ?>
                        <i class="fas fa-check text-success"></i>
                      <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($u['username'] ?? '') ?></td>
                    <td><code><?= htmlspecialchars($u['ip_address'] ?? '—') ?></code></td>
                    <td><code><?= htmlspecialchars($u['mac_address'] ?? '—') ?></code></td>
                    <td class="text-muted small"><?= htmlspecialchars($u['service'] ?? '') ?></td>
                    <td>
                      <?php $status = $u['status'] ?? 'disconnected'; ?>
                      <span class="badge bg-<?= $status === 'connected' ? 'success' : 'secondary' ?>">
                        <?= htmlspecialchars($status) ?>
                      </span>
                    </td>
                    <td><?= !empty($u['imported']) ? '<span class="badge bg-info">Yes</span>' : '—' ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <div class="p-3 d-flex gap-2">
            <button type="submit" class="btn btn-primary">
              <i class="fas fa-download me-1"></i> Import Selected
            </button>
            <a href="/routers/<?= (int) $router['id'] ?>" class="btn btn-outline-secondary">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  <?php else: ?>
    <div class="alert alert-info">
      <i class="fas fa-info-circle me-2"></i>
      No PPPoE sessions discovered.
    </div>
  <?php endif; ?>
</div>

<script src="<?= APP_URL ?>/assets/js/pppoe-manager.js" defer></script>
