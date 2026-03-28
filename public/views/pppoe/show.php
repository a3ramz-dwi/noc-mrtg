<?php
/**
 * PPPoE — Show (Detail) View
 */
/** @var array $user */
/** @var array $router */
?>
<div id="pppoe-show-page">

  <div class="d-flex align-items-center gap-2 mb-4">
    <a href="/pppoe" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
    <div>
      <h4 class="mb-0">
        <i class="fas fa-user me-2 text-primary"></i>
        <?= htmlspecialchars($user['name'] ?? '') ?>
      </h4>
      <small class="text-muted"><?= htmlspecialchars($router['name'] ?? '') ?></small>
    </div>
    <div class="ms-auto">
      <div class="form-check form-switch d-inline-flex align-items-center gap-2">
        <label class="form-check-label text-muted">Monitor</label>
        <input class="form-check-input toggle-monitor" type="checkbox"
          data-id="<?= (int) $user['id'] ?>"
          <?= (int) ($user['monitored'] ?? 0) === 1 ? 'checked' : '' ?>>
      </div>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-lg-4">
      <div class="card">
        <div class="card-header">
          <h5 class="card-title"><i class="fas fa-info-circle me-2 text-info"></i>User Info</h5>
        </div>
        <div class="card-body">
          <table class="table-dark-custom w-100">
            <tbody>
              <tr><td class="text-muted" style="width:45%">Username</td><td><?= htmlspecialchars($user['name'] ?? '') ?></td></tr>
              <tr><td class="text-muted">IP Address</td><td><code><?= htmlspecialchars($user['ip_address'] ?? '—') ?></code></td></tr>
              <tr><td class="text-muted">Interface</td><td><?= htmlspecialchars($user['interface_name'] ?? '—') ?></td></tr>
              <tr><td class="text-muted">Router</td><td><?= htmlspecialchars($router['name'] ?? '') ?></td></tr>
              <tr>
                <td class="text-muted">Status</td>
                <td>
                  <?php
                  $statusClass = ($user['status'] ?? '') === 'connected' ? 'badge-success' : 'badge-secondary';
                  ?>
                  <span class="badge <?= $statusClass ?>"><?= htmlspecialchars(ucfirst($user['status'] ?? 'unknown')) ?></span>
                </td>
              </tr>
              <?php if (!empty($user['comment'])): ?>
                <tr><td class="text-muted">Comment</td><td><?= htmlspecialchars($user['comment']) ?></td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="col-lg-8">
      <div class="card">
        <div class="card-header">
          <h5 class="card-title"><i class="fas fa-chart-area me-2 text-success"></i>Traffic History</h5>
          <select id="chart-range" class="form-select form-select-sm" style="width:120px;">
            <option value="24h">Last 24h</option>
            <option value="7d">Last 7 days</option>
            <option value="30d">Last 30 days</option>
          </select>
        </div>
        <div class="card-body">
          <div class="chart-container" style="height:280px;">
            <canvas id="pppoe-traffic-chart"></canvas>
          </div>
        </div>
      </div>

      <div class="card mt-3">
        <div class="card-header">
          <h5 class="card-title"><i class="fas fa-tachometer-alt me-2 text-warning"></i>Current Bandwidth</h5>
          <span class="badge badge-success">LIVE</span>
        </div>
        <div class="card-body">
          <div class="row text-center">
            <div class="col-6">
              <div class="bandwidth-display bandwidth-in" id="bw-in">0 bps</div>
              <div class="text-muted small"><i class="fas fa-arrow-down me-1 text-success"></i>Download</div>
            </div>
            <div class="col-6">
              <div class="bandwidth-display bandwidth-out" id="bw-out">0 bps</div>
              <div class="text-muted small"><i class="fas fa-arrow-up me-1 text-danger"></i>Upload</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

</div>

<script>
const PPPOE_ID  = <?= (int) $user['id'] ?>;
const ROUTER_ID = <?= (int) ($user['router_id'] ?? 0) ?>;
</script>
<?php $extraScripts = '<script src="' . APP_URL . '/assets/js/pppoe-manager.js"></script>'; ?>
