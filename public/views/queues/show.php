<?php
/**
 * Queue Detail View
 *
 * @var array $queue  Queue record with traffic stats
 */
$pageTitle = 'Queue: ' . htmlspecialchars($queue['name'] ?? '');
?>
<div id="queue-show-page">

  <div class="mb-4">
    <a href="/queues" class="text-muted text-decoration-none small">
      <i class="fas fa-arrow-left me-1"></i> Back to Queues
    </a>
    <h2 class="page-title mt-1">
      <i class="fas fa-layer-group me-2 text-warning"></i>
      <?= htmlspecialchars($queue['name'] ?? 'Queue') ?>
    </h2>
  </div>

  <div class="row g-3">
    <div class="col-lg-4">
      <div class="card">
        <div class="card-header">
          <h5 class="card-title mb-0"><i class="fas fa-info-circle me-2 text-info"></i>Queue Details</h5>
        </div>
        <div class="card-body">
          <dl class="row mb-0">
            <dt class="col-sm-5 text-muted">Router</dt>
            <dd class="col-sm-7">
              <a href="/routers/<?= (int) $queue['router_id'] ?>">
                <?= htmlspecialchars($queue['router_name'] ?? '—') ?>
              </a>
            </dd>

            <dt class="col-sm-5 text-muted">Queue Name</dt>
            <dd class="col-sm-7"><?= htmlspecialchars($queue['name'] ?? '') ?></dd>

            <dt class="col-sm-5 text-muted">Target</dt>
            <dd class="col-sm-7"><code><?= htmlspecialchars($queue['target'] ?? '—') ?></code></dd>

            <dt class="col-sm-5 text-muted">Max Limit</dt>
            <dd class="col-sm-7">
              <?php
              $ul = $queue['max_limit_upload']   ?? null;
              $dl = $queue['max_limit_download'] ?? null;
              if ($ul !== null && $dl !== null) {
                  echo htmlspecialchars($ul . ' / ' . $dl);
              } elseif ($ul !== null) {
                  echo htmlspecialchars($ul . ' / —');
              } elseif ($dl !== null) {
                  echo htmlspecialchars('— / ' . $dl);
              } else {
                  echo '—';
              }
              ?>
            </dd>

            <dt class="col-sm-5 text-muted">Burst Limit</dt>
            <dd class="col-sm-7">
              <?php
              $bul = $queue['burst_limit_upload']   ?? null;
              $bdl = $queue['burst_limit_download'] ?? null;
              if ($bul !== null && $bdl !== null) {
                  echo htmlspecialchars($bul . ' / ' . $bdl);
              } elseif ($bul !== null) {
                  echo htmlspecialchars($bul . ' / —');
              } elseif ($bdl !== null) {
                  echo htmlspecialchars('— / ' . $bdl);
              } else {
                  echo '—';
              }
              ?>
            </dd>

            <dt class="col-sm-5 text-muted">Monitor</dt>
            <dd class="col-sm-7">
              <div class="form-check form-switch">
                <input class="form-check-input toggle-monitor"
                       type="checkbox"
                       data-id="<?= (int) $queue['id'] ?>"
                       <?= !empty($queue['monitored']) ? 'checked' : '' ?>>
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
const QUEUE_ID = <?= (int) $queue['id'] ?>;
</script>
<script src="<?= APP_URL ?>/assets/js/charts.js" defer></script>
<script src="<?= APP_URL ?>/assets/js/queue-manager.js" defer></script>
