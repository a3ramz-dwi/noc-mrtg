<?php
/**
 * PPPoE User Detail View
 *
 * @var array $session  PPPoE session record with traffic stats
 */
$pageTitle = 'PPPoE: ' . htmlspecialchars($session['name'] ?? '');
?>
<div id="pppoe-show-page">

  <div class="mb-4">
    <a href="/pppoe" class="text-muted text-decoration-none small">
      <i class="fas fa-arrow-left me-1"></i> Back to PPPoE Users
    </a>
    <h2 class="page-title mt-1">
      <i class="fas fa-user me-2 text-info"></i>
      <?= htmlspecialchars($session['name'] ?? 'PPPoE User') ?>
    </h2>
  </div>

  <div class="row g-3">
    <div class="col-lg-4">
      <div class="card">
        <div class="card-header">
          <h5 class="card-title mb-0"><i class="fas fa-info-circle me-2 text-info"></i>User Details</h5>
        </div>
        <div class="card-body">
          <dl class="row mb-0">
            <dt class="col-sm-5 text-muted">Router</dt>
            <dd class="col-sm-7">
              <a href="/routers/<?= (int) $session['router_id'] ?>">
                <?= htmlspecialchars($session['router_name'] ?? '—') ?>
              </a>
            </dd>

            <dt class="col-sm-5 text-muted">Username</dt>
            <dd class="col-sm-7"><?= htmlspecialchars($session['name'] ?? '') ?></dd>

            <dt class="col-sm-5 text-muted">IP Address</dt>
            <dd class="col-sm-7"><code><?= htmlspecialchars($session['remote_address'] ?? '—') ?></code></dd>

            <dt class="col-sm-5 text-muted">MAC Address</dt>
            <dd class="col-sm-7"><code><?= htmlspecialchars($session['caller_id'] ?? '—') ?></code></dd>

            <dt class="col-sm-5 text-muted">Service</dt>
            <dd class="col-sm-7"><?= htmlspecialchars($session['service'] ?? '—') ?></dd>

            <dt class="col-sm-5 text-muted">Profile</dt>
            <dd class="col-sm-7"><?= htmlspecialchars($session['profile'] ?? '—') ?></dd>

            <dt class="col-sm-5 text-muted">Status</dt>
            <dd class="col-sm-7">
              <?php $status = $session['status'] ?? 'disconnected'; ?>
              <span class="badge bg-<?= $status === 'connected' ? 'success' : 'secondary' ?>">
                <?= htmlspecialchars($status) ?>
              </span>
            </dd>

            <dt class="col-sm-5 text-muted">Uptime</dt>
            <dd class="col-sm-7"><?= htmlspecialchars($session['uptime'] ?? '—') ?></dd>

            <dt class="col-sm-5 text-muted">Monitor</dt>
            <dd class="col-sm-7">
              <div class="form-check form-switch">
                <input class="form-check-input toggle-monitor"
                       type="checkbox"
                       data-id="<?= (int) $session['id'] ?>"
                       <?= !empty($session['monitored']) ? 'checked' : '' ?>>
              </div>
            </dd>
          </dl>
        </div>
      </div>
    </div>

    <div class="col-lg-8">
      <div class="card">
        <div class="card-header">
          <h5 class="card-title mb-0"><i class="fas fa-chart-area me-2 text-primary"></i>Traffic (24h)</h5>
        </div>
        <div class="card-body">
          <div class="chart-container" style="height:280px;">
            <canvas id="traffic-chart"></canvas>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
const PPPOE_ID = <?= (int) $session['id'] ?>;
</script>
<script src="<?= APP_URL ?>/assets/js/charts.js" defer></script>
<script src="<?= APP_URL ?>/assets/js/pppoe-manager.js" defer></script>
