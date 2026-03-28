<?php
/**
 * Interfaces — Index View
 */
/** @var array $interfaces */
/** @var array $routers */
?>
<div id="interfaces-page">

  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h4 class="mb-0"><i class="fas fa-ethernet me-2 text-primary"></i>Interface Management</h4>
      <small class="text-muted">Monitor router interface traffic</small>
    </div>
    <div class="d-flex gap-2">
      <?php if (!empty($routers)): ?>
        <div class="dropdown">
          <button class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown">
            <i class="fas fa-search me-1"></i>Discover Interfaces
          </button>
          <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end">
            <?php foreach ($routers as $r): ?>
              <li>
                <a class="dropdown-item" href="/routers/<?= (int) $r['id'] ?>/interfaces/discover">
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
          <input type="text" id="iface-search" class="form-control form-control-sm" placeholder="Search by name, router, description...">
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
            Total: <strong id="iface-count"><?= count($interfaces ?? []) ?></strong> interfaces
          </span>
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table-dark-custom w-100" id="interfaces-table">
          <thead>
            <tr>
              <th>Interface</th>
              <th>Router</th>
              <th>Description</th>
              <th>Type</th>
              <th>Speed</th>
              <th>Oper Status</th>
              <th>Monitored</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($interfaces)): ?>
              <tr>
                <td colspan="8" class="text-center text-muted py-4">
                  <i class="fas fa-ethernet me-2"></i>No interfaces found.
                  Discover interfaces from a router first.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($interfaces as $iface): ?>
                <tr data-router-id="<?= (int) $iface['router_id'] ?>" data-monitored="<?= (int) ($iface['monitored'] ?? 0) ?>">
                  <td>
                    <a href="/interfaces/<?= (int) $iface['id'] ?>" class="text-primary fw-bold">
                      <?= htmlspecialchars($iface['if_name'] ?? '') ?>
                    </a>
                    <br><small class="text-muted">ifIndex: <?= (int) ($iface['if_index'] ?? 0) ?></small>
                  </td>
                  <td><?= htmlspecialchars($iface['router_name'] ?? '') ?></td>
                  <td><small><?= htmlspecialchars(substr($iface['if_descr'] ?? '', 0, 40)) ?></small></td>
                  <td><span class="badge badge-secondary"><?= htmlspecialchars($iface['if_type'] ?? '') ?></span></td>
                  <td>
                    <?php
                    $speed = (int) ($iface['if_speed'] ?? 0);
                    echo $speed > 0 ? number_format($speed / 1_000_000, 0) . ' Mbps' : '—';
                    ?>
                  </td>
                  <td>
                    <?php
                    $operStatus = (int) ($iface['oper_status'] ?? 0);
                    $operClass  = $operStatus === 1 ? 'badge-success' : 'badge-secondary';
                    $operLabel  = $operStatus === 1 ? 'Up' : ($operStatus === 2 ? 'Down' : 'Unknown');
                    ?>
                    <span class="badge <?= $operClass ?>"><?= $operLabel ?></span>
                  </td>
                  <td>
                    <div class="form-check form-switch">
                      <input class="form-check-input toggle-monitor" type="checkbox"
                        data-id="<?= (int) $iface['id'] ?>"
                        <?= (int) ($iface['monitored'] ?? 0) === 1 ? 'checked' : '' ?>
                        title="Toggle monitoring">
                    </div>
                  </td>
                  <td>
                    <div class="btn-group btn-group-sm">
                      <a href="/interfaces/<?= (int) $iface['id'] ?>" class="btn btn-secondary" title="View">
                        <i class="fas fa-eye"></i>
                      </a>
                      <a href="/monitoring/interfaces?interface_id=<?= (int) $iface['id'] ?>" class="btn btn-info" title="Traffic">
                        <i class="fas fa-chart-line"></i>
                      </a>
                      <button class="btn btn-danger btn-delete-iface" data-id="<?= (int) $iface['id'] ?>" title="Delete">
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

<?php $extraScripts = '<script src="' . APP_URL . '/assets/js/interface-manager.js"></script>'; ?>
