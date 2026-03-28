<?php
/**
 * Interfaces — Show (Detail) View
 */
/** @var array $interface */
/** @var array $router */
/** @var array $trafficHistory */
?>
<div id="interface-show-page">

  <div class="d-flex align-items-center gap-2 mb-4">
    <a href="/interfaces" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
    <div>
      <h4 class="mb-0">
        <i class="fas fa-ethernet me-2 text-primary"></i>
        <?= htmlspecialchars($interface['if_name'] ?? '') ?>
      </h4>
      <small class="text-muted">
        <?= htmlspecialchars($router['name'] ?? '') ?> — <?= htmlspecialchars($interface['if_descr'] ?? '') ?>
      </small>
    </div>
    <div class="ms-auto">
      <div class="form-check form-switch d-inline-flex align-items-center gap-2">
        <label class="form-check-label text-muted">Monitor</label>
        <input class="form-check-input toggle-monitor" type="checkbox"
          data-id="<?= (int) $interface['id'] ?>"
          <?= (int) ($interface['monitored'] ?? 0) === 1 ? 'checked' : '' ?>>
      </div>
    </div>
  </div>

  <div class="row g-3">
    <!-- Info card -->
    <div class="col-lg-4">
      <div class="card">
        <div class="card-header">
          <h5 class="card-title"><i class="fas fa-info-circle me-2 text-info"></i>Interface Info</h5>
        </div>
        <div class="card-body">
          <table class="table-dark-custom w-100">
            <tbody>
              <tr><td class="text-muted" style="width:45%">ifIndex</td><td><?= (int) ($interface['if_index'] ?? 0) ?></td></tr>
              <tr><td class="text-muted">ifName</td><td><?= htmlspecialchars($interface['if_name'] ?? '') ?></td></tr>
              <tr><td class="text-muted">ifDescr</td><td><small><?= htmlspecialchars($interface['if_descr'] ?? '') ?></small></td></tr>
              <tr><td class="text-muted">Type</td><td><?= htmlspecialchars($interface['if_type'] ?? '') ?></td></tr>
              <tr>
                <td class="text-muted">Speed</td>
                <td>
                  <?php
                  $speed = (int) ($interface['if_speed'] ?? 0);
                  echo $speed > 0 ? number_format($speed / 1_000_000, 0) . ' Mbps' : '—';
                  ?>
                </td>
              </tr>
              <tr>
                <td class="text-muted">Oper Status</td>
                <td>
                  <?php
                  $operStatus = (int) ($interface['oper_status'] ?? 0);
                  $operClass  = $operStatus === 1 ? 'badge-success' : 'badge-secondary';
                  $operLabel  = $operStatus === 1 ? 'Up' : ($operStatus === 2 ? 'Down' : 'Unknown');
                  ?>
                  <span class="badge <?= $operClass ?>"><?= $operLabel ?></span>
                </td>
              </tr>
              <tr>
                <td class="text-muted">Admin Status</td>
                <td>
                  <?php
                  $adminStatus = (int) ($interface['admin_status'] ?? 0);
                  $adminClass  = $adminStatus === 1 ? 'badge-success' : 'badge-secondary';
                  $adminLabel  = $adminStatus === 1 ? 'Up' : ($adminStatus === 2 ? 'Down' : 'Unknown');
                  ?>
                  <span class="badge <?= $adminClass ?>"><?= $adminLabel ?></span>
                </td>
              </tr>
              <tr><td class="text-muted">Router</td><td><?= htmlspecialchars($router['name'] ?? '') ?></td></tr>
              <tr><td class="text-muted">Router IP</td><td><code><?= htmlspecialchars($router['ip_address'] ?? '') ?></code></td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Traffic chart -->
    <div class="col-lg-8">
      <div class="card">
        <div class="card-header">
          <h5 class="card-title"><i class="fas fa-chart-area me-2 text-success"></i>Traffic History (24h)</h5>
          <div class="d-flex gap-2 align-items-center">
            <select id="chart-range" class="form-select form-select-sm" style="width:120px;">
              <option value="24h">Last 24h</option>
              <option value="7d">Last 7 days</option>
              <option value="30d">Last 30 days</option>
            </select>
          </div>
        </div>
        <div class="card-body">
          <div class="chart-container" style="height:280px;">
            <canvas id="interface-traffic-chart"></canvas>
          </div>
        </div>
      </div>

      <!-- Live bandwidth -->
      <div class="card mt-3">
        <div class="card-header">
          <h5 class="card-title"><i class="fas fa-tachometer-alt me-2 text-warning"></i>Current Bandwidth</h5>
          <span class="badge badge-success" id="live-indicator">LIVE</span>
        </div>
        <div class="card-body">
          <div class="row text-center">
            <div class="col-6">
              <div class="bandwidth-display bandwidth-in" id="bw-in">0 bps</div>
              <div class="text-muted small"><i class="fas fa-arrow-down me-1 text-success"></i>Inbound</div>
            </div>
            <div class="col-6">
              <div class="bandwidth-display bandwidth-out" id="bw-out">0 bps</div>
              <div class="text-muted small"><i class="fas fa-arrow-up me-1 text-danger"></i>Outbound</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

</div>

<script>
const INTERFACE_ID = <?= (int) $interface['id'] ?>;
const ROUTER_ID    = <?= (int) ($interface['router_id'] ?? 0) ?>;
</script>
<?php $extraScripts = '<script src="' . APP_URL . '/assets/js/interface-manager.js"></script>'; ?>
