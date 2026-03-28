<?php
/**
 * Queues — Index View
 */
/** @var array $queues */
/** @var array $routers */
?>
<div id="queues-page">

  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h4 class="mb-0"><i class="fas fa-layer-group me-2 text-primary"></i>Simple Queue Management</h4>
      <small class="text-muted">Monitor MikroTik simple queue traffic</small>
    </div>
    <div class="d-flex gap-2">
      <?php if (!empty($routers)): ?>
        <div class="dropdown">
          <button class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown">
            <i class="fas fa-search me-1"></i>Discover Queues
          </button>
          <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end">
            <?php foreach ($routers as $r): ?>
              <li>
                <a class="dropdown-item" href="/routers/<?= (int) $r['id'] ?>/queues/discover">
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
          <input type="text" id="queue-search" class="form-control form-control-sm" placeholder="Search by name, target...">
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
          <select id="filter-monitor" class="form-select form-select-sm">
            <option value="">All</option>
            <option value="1">Monitored</option>
            <option value="0">Not Monitored</option>
          </select>
        </div>
        <div class="col-md-3 text-end">
          <span class="text-muted small">
            Total: <strong id="queue-count"><?= count($queues ?? []) ?></strong> queues
          </span>
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table-dark-custom w-100" id="queues-table">
          <thead>
            <tr>
              <th>Queue Name</th>
              <th>Router</th>
              <th>Target</th>
              <th>Max Limit</th>
              <th>Burst Limit</th>
              <th>Monitored</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($queues)): ?>
              <tr>
                <td colspan="7" class="text-center text-muted py-4">
                  <i class="fas fa-layer-group me-2"></i>No queues found.
                  Discover queues from a router first.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($queues as $queue): ?>
                <tr data-router-id="<?= (int) $queue['router_id'] ?>" data-monitored="<?= (int) ($queue['monitored'] ?? 0) ?>">
                  <td>
                    <a href="/queues/<?= (int) $queue['id'] ?>" class="text-primary fw-bold">
                      <?= htmlspecialchars($queue['name'] ?? '') ?>
                    </a>
                    <?php if (!empty($queue['comment'])): ?>
                      <br><small class="text-muted"><?= htmlspecialchars(substr($queue['comment'], 0, 40)) ?></small>
                    <?php endif; ?>
                  </td>
                  <td><?= htmlspecialchars($queue['router_name'] ?? '') ?></td>
                  <td><code><?= htmlspecialchars($queue['target'] ?? '') ?></code></td>
                  <td>
                    <?php
                    $maxUp   = (int) ($queue['max_limit_up']   ?? 0);
                    $maxDown = (int) ($queue['max_limit_down'] ?? 0);
                    if ($maxDown > 0 || $maxUp > 0) {
                        $fmt = fn(int $b) => $b >= 1_000_000 ? number_format($b / 1_000_000, 1) . 'M' : number_format($b / 1_000, 0) . 'k';
                        echo '↓' . $fmt($maxDown) . ' / ↑' . $fmt($maxUp);
                    } else {
                        echo '—';
                    }
                    ?>
                  </td>
                  <td>
                    <?php
                    $burstUp   = (int) ($queue['burst_limit_up']   ?? 0);
                    $burstDown = (int) ($queue['burst_limit_down'] ?? 0);
                    if ($burstDown > 0 || $burstUp > 0) {
                        $fmt = fn(int $b) => $b >= 1_000_000 ? number_format($b / 1_000_000, 1) . 'M' : number_format($b / 1_000, 0) . 'k';
                        echo '↓' . $fmt($burstDown) . ' / ↑' . $fmt($burstUp);
                    } else {
                        echo '—';
                    }
                    ?>
                  </td>
                  <td>
                    <div class="form-check form-switch">
                      <input class="form-check-input toggle-monitor" type="checkbox"
                        data-id="<?= (int) $queue['id'] ?>"
                        <?= (int) ($queue['monitored'] ?? 0) === 1 ? 'checked' : '' ?>>
                    </div>
                  </td>
                  <td>
                    <div class="btn-group btn-group-sm">
                      <a href="/queues/<?= (int) $queue['id'] ?>" class="btn btn-secondary" title="View">
                        <i class="fas fa-eye"></i>
                      </a>
                      <a href="/monitoring/queues?queue_id=<?= (int) $queue['id'] ?>" class="btn btn-info" title="Traffic">
                        <i class="fas fa-chart-line"></i>
                      </a>
                      <button class="btn btn-danger btn-delete-queue" data-id="<?= (int) $queue['id'] ?>" title="Delete">
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

<?php $extraScripts = '<script src="' . APP_URL . '/assets/js/queue-manager.js"></script>'; ?>
