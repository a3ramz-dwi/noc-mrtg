<?php
/**
 * Interface Discovery View
 *
 * @var array  $router      Router that was polled
 * @var array  $discovered  Discovered interfaces from SNMP
 */
$pageTitle = 'Discover Interfaces: ' . htmlspecialchars($router['name'] ?? '');
?>
<div id="interface-discover-page">

  <div class="mb-4">
    <a href="/routers/<?= (int) $router['id'] ?>" class="text-muted text-decoration-none small">
      <i class="fas fa-arrow-left me-1"></i> Back to Router
    </a>
    <h2 class="page-title mt-1">
      <i class="fas fa-search me-2 text-info"></i>
      Discover Interfaces — <?= htmlspecialchars($router['name'] ?? '') ?>
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
          <i class="fas fa-ethernet me-2 text-success"></i>
          Discovered Interfaces (<?= count($discovered) ?>)
        </h5>
      </div>
      <div class="card-body p-0">
        <form method="POST" action="/interfaces/import" id="import-form">
          <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? '') ?>">
          <input type="hidden" name="router_id" value="<?= (int) $router['id'] ?>">

          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead>
                <tr>
                  <th width="40"><input type="checkbox" id="check-all" class="form-check-input"></th>
                  <th>Index</th>
                  <th>Name</th>
                  <th>Alias/Description</th>
                  <th>Speed</th>
                  <th>Admin</th>
                  <th>Oper</th>
                  <th>Already Imported</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($discovered as $iface): ?>
                  <tr>
                    <td>
                      <?php if (empty($iface['imported'])): ?>
                        <input type="checkbox"
                               name="if_indexes[]"
                               value="<?= (int) $iface['if_index'] ?>"
                               class="form-check-input iface-check">
                      <?php else: ?>
                        <i class="fas fa-check text-success" title="Already imported"></i>
                      <?php endif; ?>
                    </td>
                    <td><?= (int) $iface['if_index'] ?></td>
                    <td><?= htmlspecialchars($iface['name'] ?? '') ?></td>
                    <td class="text-muted small">
                      <?= htmlspecialchars($iface['alias'] ?? $iface['description'] ?? '') ?>
                    </td>
                    <td class="text-muted small"><?= htmlspecialchars((string) ($iface['speed'] ?? '')) ?></td>
                    <td>
                      <?php $as = $iface['admin_status'] ?? 'down'; ?>
                      <span class="badge bg-<?= $as === 'up' ? 'primary' : 'secondary' ?>"><?= $as ?></span>
                    </td>
                    <td>
                      <?php $os = $iface['oper_status'] ?? 'down'; ?>
                      <span class="badge bg-<?= $os === 'up' ? 'success' : 'secondary' ?>"><?= $os ?></span>
                    </td>
                    <td><?= !empty($iface['imported']) ? '<span class="badge bg-info">Yes</span>' : '—' ?></td>
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
      No interfaces were discovered via SNMP.
      Make sure the router is reachable and SNMP community is correct.
    </div>
  <?php endif; ?>
</div>

<script src="<?= APP_URL ?>/assets/js/interface-manager.js" defer></script>
