<?php
/**
 * Queue Discovery View
 *
 * @var array  $router      Router that was polled
 * @var array  $discovered  Discovered queues from SNMP/API
 */
$pageTitle = 'Discover Queues: ' . htmlspecialchars($router['name'] ?? '');
?>
<div id="queue-discover-page">

  <div class="mb-4">
    <a href="/routers/<?= (int) $router['id'] ?>" class="text-muted text-decoration-none small">
      <i class="fas fa-arrow-left me-1"></i> Back to Router
    </a>
    <h2 class="page-title mt-1">
      <i class="fas fa-search me-2 text-info"></i>
      Discover Queues — <?= htmlspecialchars($router['name'] ?? '') ?>
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
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">
          <i class="fas fa-layer-group me-2 text-warning"></i>
          Discovered Queues (<?= count($discovered) ?>)
        </h5>
      </div>
      <div class="card-body p-0">
        <form method="POST" action="/queues/import" id="import-form">
          <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? '') ?>">
          <input type="hidden" name="router_id" value="<?= (int) $router['id'] ?>">

          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead>
                <tr>
                  <th width="40"><input type="checkbox" id="check-all" class="form-check-input"></th>
                  <th>Queue Name</th>
                  <th>Target</th>
                  <th>Max Limit Upload</th>
                  <th>Max Limit Download</th>
                  <th>Imported</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($discovered as $q): ?>
                  <tr>
                    <td>
                      <?php if (empty($q['imported'])): ?>
                        <input type="checkbox"
                               name="queue_indexes[]"
                               value="<?= (int) ($q['queue_index'] ?? 0) ?>"
                               class="form-check-input queue-check">
                      <?php else: ?>
                        <i class="fas fa-check text-success"></i>
                      <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($q['name'] ?? '') ?></td>
                    <td class="text-muted small"><?= htmlspecialchars($q['target'] ?? '') ?></td>
                    <td class="text-muted small"><?= htmlspecialchars((string) ($q['max_limit_upload'] ?? '')) ?></td>
                    <td class="text-muted small"><?= htmlspecialchars((string) ($q['max_limit_download'] ?? '')) ?></td>
                    <td><?= !empty($q['imported']) ? '<span class="badge bg-info">Yes</span>' : '—' ?></td>
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
      No queues discovered via SNMP/API.
    </div>
  <?php endif; ?>
</div>

<script src="<?= APP_URL ?>/assets/js/queue-manager.js" defer></script>
