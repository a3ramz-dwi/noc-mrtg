<?php
/**
 * Settings View
 *
 * @var array  $settings  Current settings key-value array
 * @var string $csrf      CSRF token
 * @var string $success   Flash success message
 * @var string $error     Flash error message
 */
$pageTitle = 'Settings';
?>
<div id="settings-page">

  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="page-title">
      <i class="fas fa-cog me-2 text-secondary"></i>Settings
    </h2>
  </div>

  <?php if (!empty($success)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      <i class="fas fa-check-circle me-2"></i>
      <?= htmlspecialchars($success) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <i class="fas fa-exclamation-triangle me-2"></i>
      <?= htmlspecialchars($error) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <form method="POST" action="/settings">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? '') ?>">

    <!-- Application Settings -->
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="card-title mb-0">
          <i class="fas fa-sliders-h me-2 text-primary"></i>Application
        </h5>
      </div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label" for="app_name">Application Name</label>
            <input type="text" class="form-control" id="app_name" name="app_name"
                   value="<?= htmlspecialchars($settings['app_name'] ?? 'NOC Manager') ?>">
          </div>
        </div>
      </div>
    </div>

    <!-- MRTG Settings -->
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="card-title mb-0">
          <i class="fas fa-chart-area me-2 text-primary"></i>MRTG
        </h5>
      </div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label" for="mrtg_dir">MRTG Data Directory</label>
            <input type="text" class="form-control font-monospace" id="mrtg_dir" name="mrtg_dir"
                   value="<?= htmlspecialchars($settings['mrtg_dir'] ?? '/var/www/mrtg') ?>">
            <div class="form-text">Where MRTG stores .png graphs and log files.</div>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label" for="mrtg_cfg_dir">MRTG Config Directory</label>
            <input type="text" class="form-control font-monospace" id="mrtg_cfg_dir" name="mrtg_cfg_dir"
                   value="<?= htmlspecialchars($settings['mrtg_cfg_dir'] ?? '/etc/mrtg') ?>">
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label" for="mrtg_bin">MRTG Binary Path</label>
            <input type="text" class="form-control font-monospace" id="mrtg_bin" name="mrtg_bin"
                   value="<?= htmlspecialchars($settings['mrtg_bin'] ?? '/usr/bin/mrtg') ?>">
          </div>
        </div>
      </div>
    </div>

    <!-- SNMP Settings -->
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="card-title mb-0">
          <i class="fas fa-network-wired me-2 text-success"></i>SNMP Defaults
        </h5>
      </div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-4 mb-3">
            <label class="form-label" for="snmp_community">Default Community</label>
            <input type="text" class="form-control" id="snmp_community" name="snmp_community"
                   value="<?= htmlspecialchars($settings['snmp_community'] ?? 'public') ?>">
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label" for="snmp_version">SNMP Version</label>
            <select class="form-select" id="snmp_version" name="snmp_version">
              <?php foreach (['1', '2c'] as $v): ?>
                <option value="<?= $v ?>" <?= ($settings['snmp_version'] ?? '2c') === $v ? 'selected' : '' ?>>
                  v<?= $v ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2 mb-3">
            <label class="form-label" for="snmp_port">Port</label>
            <input type="number" class="form-control" id="snmp_port" name="snmp_port"
                   min="1" max="65535"
                   value="<?= (int) ($settings['snmp_port'] ?? 161) ?>">
          </div>
          <div class="col-md-2 mb-3">
            <label class="form-label" for="snmp_retries">Retries</label>
            <input type="number" class="form-control" id="snmp_retries" name="snmp_retries"
                   min="0" max="10"
                   value="<?= (int) ($settings['snmp_retries'] ?? 2) ?>">
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label" for="snmp_timeout">Timeout (µs)</label>
            <input type="number" class="form-control" id="snmp_timeout" name="snmp_timeout"
                   min="100000"
                   value="<?= (int) ($settings['snmp_timeout'] ?? 5000000) ?>">
            <div class="form-text">In microseconds (5000000 = 5 seconds).</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Logging -->
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="card-title mb-0">
          <i class="fas fa-file-alt me-2 text-secondary"></i>Logging
        </h5>
      </div>
      <div class="card-body">
        <div class="col-md-6 mb-3">
          <label class="form-label" for="log_dir">Log Directory</label>
          <input type="text" class="form-control font-monospace" id="log_dir" name="log_dir"
                 value="<?= htmlspecialchars($settings['log_dir'] ?? '/var/log/noc') ?>">
        </div>
      </div>
    </div>

    <!-- Environment Info (Read-only) -->
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="card-title mb-0">
          <i class="fas fa-info-circle me-2 text-info"></i>Environment (Read-only)
        </h5>
      </div>
      <div class="card-body">
        <dl class="row mb-0">
          <dt class="col-sm-3 text-muted">App URL</dt>
          <dd class="col-sm-9"><code><?= htmlspecialchars($settings['app_url'] ?? '') ?></code></dd>

          <dt class="col-sm-3 text-muted">Environment</dt>
          <dd class="col-sm-9">
            <span class="badge bg-<?= ($settings['app_env'] ?? '') === 'production' ? 'danger' : 'info' ?>">
              <?= htmlspecialchars($settings['app_env'] ?? '') ?>
            </span>
          </dd>

          <dt class="col-sm-3 text-muted">Version</dt>
          <dd class="col-sm-9"><?= htmlspecialchars($settings['app_version'] ?? '') ?></dd>

          <dt class="col-sm-3 text-muted">PHP Version</dt>
          <dd class="col-sm-9"><?= PHP_VERSION ?></dd>
        </dl>
      </div>
    </div>

    <div class="d-flex gap-2">
      <button type="submit" class="btn btn-primary">
        <i class="fas fa-save me-1"></i> Save Settings
      </button>
    </div>
  </form>
</div>
