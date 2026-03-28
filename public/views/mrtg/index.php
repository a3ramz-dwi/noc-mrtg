<?php
/**
 * MRTG — Index View
 */
/** @var array $configs */
?>
<div id="mrtg-page">

  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h4 class="mb-0"><i class="fas fa-cog me-2 text-primary"></i>MRTG Configuration</h4>
      <small class="text-muted">Manage and generate MRTG configurations for traffic monitoring</small>
    </div>
    <div class="d-flex gap-2">
      <a href="/mrtg/generate" class="btn btn-warning">
        <i class="fas fa-magic me-1"></i>Generate Config
      </a>
    </div>
  </div>

  <!-- Stats -->
  <div class="row g-3 mb-4">
    <div class="col-md-4">
      <div class="stat-card blue">
        <div class="stat-icon"><i class="fas fa-file-code"></i></div>
        <div class="stat-value"><?= count($configs ?? []) ?></div>
        <div class="stat-label">Total Configs</div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="stat-card green">
        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
        <div class="stat-value"><?= count(array_filter($configs ?? [], fn($c) => $c['status'] === 'active')) ?></div>
        <div class="stat-label">Active</div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="stat-card orange">
        <div class="stat-icon"><i class="fas fa-clock"></i></div>
        <div class="stat-value"><?= count(array_filter($configs ?? [], fn($c) => $c['status'] === 'pending')) ?></div>
        <div class="stat-label">Pending</div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h5 class="card-title"><i class="fas fa-list me-2"></i>MRTG Config Files</h5>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table-dark-custom w-100" id="mrtg-table">
          <thead>
            <tr>
              <th>Router</th>
              <th>Config File</th>
              <th>Targets</th>
              <th>Status</th>
              <th>Generated</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($configs)): ?>
              <tr>
                <td colspan="6" class="text-center text-muted py-4">
                  <i class="fas fa-file-code me-2"></i>No MRTG configs generated yet.
                  <a href="/mrtg/generate" class="ms-2">Generate now →</a>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($configs as $config): ?>
                <tr>
                  <td><?= htmlspecialchars($config['router_name'] ?? '') ?></td>
                  <td>
                    <code><?= htmlspecialchars($config['config_file'] ?? '') ?></code>
                  </td>
                  <td><?= (int) ($config['target_count'] ?? 0) ?></td>
                  <td>
                    <?php
                    $statusClass = match($config['status'] ?? 'pending') {
                        'active'  => 'badge-success',
                        'error'   => 'badge-danger',
                        default   => 'badge-warning',
                    };
                    ?>
                    <span class="badge <?= $statusClass ?>"><?= htmlspecialchars(ucfirst($config['status'] ?? 'pending')) ?></span>
                  </td>
                  <td>
                    <small class="text-muted"><?= htmlspecialchars($config['generated_at'] ?? '—') ?></small>
                  </td>
                  <td>
                    <div class="btn-group btn-group-sm">
                      <a href="/mrtg/<?= (int) $config['id'] ?>" class="btn btn-secondary" title="View Config">
                        <i class="fas fa-eye"></i>
                      </a>
                      <button class="btn btn-warning btn-regen" data-router-id="<?= (int) ($config['router_id'] ?? 0) ?>" title="Regenerate">
                        <i class="fas fa-sync"></i>
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

  <!-- MRTG setup info -->
  <div class="card mt-4">
    <div class="card-header">
      <h5 class="card-title"><i class="fas fa-terminal me-2 text-info"></i>Running MRTG</h5>
    </div>
    <div class="card-body">
      <p class="text-muted small mb-2">After generating configs, run MRTG manually or via cron:</p>
      <pre class="bg-dark border rounded p-3 small text-success"># Run MRTG for a specific router
mrtg /etc/mrtg/router-1.cfg

# Cron job — run every 5 minutes
*/5 * * * * php <?= htmlspecialchars(defined('APP_DIR') ? APP_DIR : '/var/www/noc') ?>/cron/mrtg_generate.php >> /var/log/noc/mrtg_generate.log 2>&amp;1</pre>
    </div>
  </div>

</div>
