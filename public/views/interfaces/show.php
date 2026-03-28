<?php
/**
 * Interface Detail View
 *
 * @var array $interface  Interface record with traffic stats
 */
$pageTitle = 'Interface: ' . htmlspecialchars($interface['if_name'] ?? '');
?>
<div id="interface-show-page">

  <div class="mb-4">
    <a href="/interfaces" class="text-muted text-decoration-none small">
      <i class="fas fa-arrow-left me-1"></i> Back to Interfaces
    </a>
    <h2 class="page-title mt-1">
      <i class="fas fa-ethernet me-2 text-success"></i>
      <?= htmlspecialchars($interface['if_name'] ?? 'Interface') ?>
    </h2>
  </div>

  <div class="row g-3">
    <div class="col-lg-4">
      <div class="card">
        <div class="card-header">
          <h5 class="card-title mb-0"><i class="fas fa-info-circle me-2 text-info"></i>Details</h5>
        </div>
        <div class="card-body">
          <dl class="row mb-0">
            <dt class="col-sm-5 text-muted">Router</dt>
            <dd class="col-sm-7">
              <a href="/routers/<?= (int) $interface['router_id'] ?>">
                <?= htmlspecialchars($interface['router_name'] ?? '—') ?>
              </a>
            </dd>

            <dt class="col-sm-5 text-muted">IF Index</dt>
            <dd class="col-sm-7"><?= (int) $interface['if_index'] ?></dd>

            <dt class="col-sm-5 text-muted">Name</dt>
            <dd class="col-sm-7"><?= htmlspecialchars($interface['if_name'] ?? '') ?></dd>

            <dt class="col-sm-5 text-muted">Alias</dt>
            <dd class="col-sm-7 text-muted"><?= htmlspecialchars($interface['if_alias'] ?? '—') ?></dd>

            <dt class="col-sm-5 text-muted">Description</dt>
            <dd class="col-sm-7 text-muted small"><?= htmlspecialchars($interface['if_descr'] ?? '—') ?></dd>

            <dt class="col-sm-5 text-muted">Speed</dt>
            <dd class="col-sm-7"><?= htmlspecialchars($interface['if_speed'] ?? '—') ?></dd>

            <dt class="col-sm-5 text-muted">MAC</dt>
            <dd class="col-sm-7"><code><?= htmlspecialchars($interface['if_phys_address'] ?? '—') ?></code></dd>

            <dt class="col-sm-5 text-muted">Admin Status</dt>
            <dd class="col-sm-7">
              <?php $as = (int) ($interface['admin_status'] ?? 0); ?>
              <span class="badge bg-<?= $as === 1 ? 'primary' : 'secondary' ?>">
                <?= $as === 1 ? 'up' : 'down' ?>
              </span>
            </dd>

            <dt class="col-sm-5 text-muted">Oper Status</dt>
            <dd class="col-sm-7">
              <?php $os = (int) ($interface['oper_status'] ?? 0); ?>
              <span class="badge bg-<?= $os === 1 ? 'success' : 'secondary' ?>">
                <?= $os === 1 ? 'up' : 'down' ?>
              </span>
            </dd>

            <dt class="col-sm-5 text-muted">Monitor</dt>
            <dd class="col-sm-7">
              <div class="form-check form-switch">
                <input class="form-check-input toggle-monitor"
                       type="checkbox"
                       data-id="<?= (int) $interface['id'] ?>"
                       <?= !empty($interface['monitor']) ? 'checked' : '' ?>>
              </div>
            </dd>
          </dl>
        </div>
      </div>
    </div>

    <!-- Traffic Chart -->
    <div class="col-lg-8">
      <div class="card">
        <div class="card-header">
          <h5 class="card-title mb-0">
            <i class="fas fa-chart-area me-2 text-primary"></i>Traffic (24h)
          </h5>
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
const IF_ID = <?= (int) $interface['id'] ?>;
</script>
<script src="<?= APP_URL ?>/assets/js/charts.js" defer></script>
<script src="<?= APP_URL ?>/assets/js/interface-manager.js" defer></script>
