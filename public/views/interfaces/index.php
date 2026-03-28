<?php
/**
 * Interfaces Index View
 *
 * @var array $interfaces  List of interface records
 * @var array $routers     List of routers for filter
 */
$pageTitle = 'Interfaces';
?>
<div id="interfaces-page">

  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="page-title"><i class="fas fa-ethernet me-2 text-success"></i>Interfaces</h2>
    <div class="btn-group">
      <?php foreach ($routers ?? [] as $r): ?>
        <a href="/routers/<?= (int) $r['id'] ?>/interfaces/discover"
           class="btn btn-outline-info btn-sm">
          <i class="fas fa-search me-1"></i> Discover on <?= htmlspecialchars($r['name']) ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="card-title mb-0"><i class="fas fa-ethernet me-2"></i>All Interfaces</h5>
      <span class="badge bg-success"><?= count($interfaces ?? []) ?> total</span>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0" id="interfaces-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Router</th>
              <th>Index</th>
              <th>Name</th>
              <th>Alias/Description</th>
              <th>Speed</th>
              <th>Oper Status</th>
              <th>Monitor</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($interfaces)): ?>
              <tr>
                <td colspan="9" class="text-center text-muted py-4">
                  <i class="fas fa-info-circle me-1"></i>
                  No interfaces found. Use <strong>Discover</strong> on a router to import interfaces.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($interfaces as $iface): ?>
                <tr>
                  <td><?= (int) $iface['id'] ?></td>
                  <td>
                    <a href="/routers/<?= (int) $iface['router_id'] ?>">
                      <?= htmlspecialchars($iface['router_name'] ?? '—') ?>
                    </a>
                  </td>
                  <td><?= (int) $iface['if_index'] ?></td>
                  <td>
                    <a href="/interfaces/<?= (int) $iface['id'] ?>">
                      <?= htmlspecialchars($iface['name'] ?? '') ?>
                    </a>
                  </td>
                  <td class="text-muted small"><?= htmlspecialchars($iface['alias'] ?? '') ?></td>
                  <td class="text-muted small"><?= htmlspecialchars($iface['speed'] ?? '') ?></td>
                  <td>
                    <?php $os = (int) ($iface['oper_status'] ?? 0); ?>
                    <span class="badge bg-<?= $os === 1 ? 'success' : 'secondary' ?>">
                      <?= $os === 1 ? 'up' : 'down' ?>
                    </span>
                  </td>
                  <td>
                    <div class="form-check form-switch">
                      <input class="form-check-input toggle-monitor"
                             type="checkbox"
                             data-id="<?= (int) $iface['id'] ?>"
                             <?= !empty($iface['monitored']) ? 'checked' : '' ?>>
                    </div>
                  </td>
                  <td class="text-end">
                    <div class="btn-group btn-group-sm">
                      <a href="/interfaces/<?= (int) $iface['id'] ?>" class="btn btn-outline-secondary" title="View">
                        <i class="fas fa-eye"></i>
                      </a>
                      <button type="button"
                              class="btn btn-outline-danger btn-delete-iface"
                              data-id="<?= (int) $iface['id'] ?>"
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

<script src="<?= APP_URL ?>/assets/js/interface-manager.js" defer></script>
