<?php
/**
 * Queues Index View
 *
 * @var array $queues   List of queue records
 * @var array $routers  List of routers for discover links
 */
$pageTitle = 'Simple Queues';
?>
<div id="queues-page">

  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="page-title"><i class="fas fa-layer-group me-2 text-warning"></i>Simple Queues</h2>
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
              <a class="dropdown-item" href="/routers/<?= (int) $r['id'] ?>/queues/discover">
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
      <h5 class="card-title mb-0"><i class="fas fa-layer-group me-2"></i>All Queues</h5>
      <span class="badge bg-warning text-dark"><?= count($queues ?? []) ?> total</span>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0" id="queues-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Router</th>
              <th>Queue Name</th>
              <th>Target</th>
              <th>Max Limit</th>
              <th>Monitor</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($queues)): ?>
              <tr>
                <td colspan="7" class="text-center text-muted py-4">
                  <i class="fas fa-info-circle me-1"></i>
                  No queues found. Use <strong>Discover</strong> on a router to import queues.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($queues as $q): ?>
                <tr>
                  <td><?= (int) $q['id'] ?></td>
                  <td>
                    <a href="/routers/<?= (int) $q['router_id'] ?>">
                      <?= htmlspecialchars($q['router_name'] ?? '—') ?>
                    </a>
                  </td>
                  <td>
                    <a href="/queues/<?= (int) $q['id'] ?>">
                      <?= htmlspecialchars($q['name'] ?? '') ?>
                    </a>
                  </td>
                  <td class="text-muted small"><?= htmlspecialchars($q['target'] ?? '—') ?></td>
                  <td class="text-muted small">
                    <?php
                    $ul = $q['max_limit_upload']   ?? null;
                    $dl = $q['max_limit_download'] ?? null;
                    if ($ul !== null && $dl !== null) {
                        echo htmlspecialchars($ul . '/' . $dl);
                    } elseif ($ul !== null || $dl !== null) {
                        echo htmlspecialchars(($ul ?? '—') . '/' . ($dl ?? '—'));
                    } else {
                        echo '—';
                    }
                    ?>
                  </td>
                  <td>
                    <div class="form-check form-switch">
                      <input class="form-check-input toggle-monitor"
                             type="checkbox"
                             data-id="<?= (int) $q['id'] ?>"
                             <?= !empty($q['monitored']) ? 'checked' : '' ?>>
                    </div>
                  </td>
                  <td class="text-end">
                    <div class="btn-group btn-group-sm">
                      <a href="/queues/<?= (int) $q['id'] ?>" class="btn btn-outline-secondary" title="View">
                        <i class="fas fa-eye"></i>
                      </a>
                      <button type="button"
                              class="btn btn-outline-danger btn-delete-queue"
                              data-id="<?= (int) $q['id'] ?>"
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

<script src="<?= APP_URL ?>/assets/js/queue-manager.js" defer></script>
