<?php
/**
 * Settings — Index View
 */
/** @var array  $settings */
/** @var string $csrf */
?>
<div id="settings-page">

  <div class="mb-4">
    <h4 class="mb-0"><i class="fas fa-cog me-2 text-primary"></i>Application Settings</h4>
    <small class="text-muted">Configure NOC Manager system settings</small>
  </div>

  <form method="POST" action="/settings" id="settings-form">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? '') ?>">

    <!-- Application Settings -->
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="card-title"><i class="fas fa-desktop me-2 text-info"></i>Application</h5>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Application Name</label>
            <input type="text" class="form-control" name="app_name"
              value="<?= htmlspecialchars($settings['app_name'] ?? 'NOC Manager') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Application URL</label>
            <input type="url" class="form-control" name="app_url"
              value="<?= htmlspecialchars($settings['app_url'] ?? '') ?>"
              placeholder="http://localhost">
          </div>
          <div class="col-md-6">
            <label class="form-label">Timezone</label>
            <select class="form-select" name="timezone">
              <?php
              $tz  = $settings['timezone'] ?? 'Asia/Jakarta';
              $tzs = ['Asia/Jakarta', 'Asia/Makassar', 'Asia/Jayapura', 'UTC'];
              foreach ($tzs as $t): ?>
                <option value="<?= htmlspecialchars($t) ?>" <?= $tz === $t ? 'selected' : '' ?>>
                  <?= htmlspecialchars($t) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Log Retention (days)</label>
            <input type="number" class="form-control" name="log_retention"
              value="<?= (int) ($settings['log_retention'] ?? 90) ?>" min="7" max="365">
          </div>
        </div>
      </div>
    </div>

    <!-- SNMP Settings -->
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="card-title"><i class="fas fa-network-wired me-2 text-warning"></i>SNMP Defaults</h5>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Default Community</label>
            <input type="text" class="form-control" name="snmp_community"
              value="<?= htmlspecialchars($settings['snmp_community'] ?? 'public') ?>">
          </div>
          <div class="col-md-2">
            <label class="form-label">SNMP Version</label>
            <select class="form-select" name="snmp_version">
              <option value="2c" <?= ($settings['snmp_version'] ?? '2c') === '2c' ? 'selected' : '' ?>>v2c</option>
              <option value="1"  <?= ($settings['snmp_version'] ?? '2c') === '1'  ? 'selected' : '' ?>>v1</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label">Port</label>
            <input type="number" class="form-control" name="snmp_port"
              value="<?= (int) ($settings['snmp_port'] ?? 161) ?>" min="1" max="65535">
          </div>
          <div class="col-md-2">
            <label class="form-label">Timeout (microseconds)</label>
            <input type="number" class="form-control" name="snmp_timeout"
              value="<?= (int) ($settings['snmp_timeout'] ?? 5000000) ?>" min="100000">
          </div>
          <div class="col-md-2">
            <label class="form-label">Retries</label>
            <input type="number" class="form-control" name="snmp_retries"
              value="<?= (int) ($settings['snmp_retries'] ?? 2) ?>" min="0" max="10">
          </div>
        </div>
      </div>
    </div>

    <!-- Polling Settings -->
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="card-title"><i class="fas fa-clock me-2 text-success"></i>Polling</h5>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Poll Interval (seconds)</label>
            <select class="form-select" name="poll_interval">
              <?php foreach ([60, 120, 300, 600] as $iv): ?>
                <option value="<?= $iv ?>" <?= (int)($settings['poll_interval'] ?? 300) === $iv ? 'selected' : '' ?>>
                  <?= $iv ?>s (<?= $iv / 60 ?> min)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>
    </div>

    <!-- MRTG Settings -->
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="card-title"><i class="fas fa-chart-bar me-2 text-purple"></i>MRTG</h5>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">MRTG Output Directory</label>
            <input type="text" class="form-control" name="mrtg_dir"
              value="<?= htmlspecialchars($settings['mrtg_dir'] ?? '/var/www/mrtg') ?>">
            <div class="form-text text-muted">Directory where MRTG HTML/PNG files are stored.</div>
          </div>
          <div class="col-md-6">
            <label class="form-label">MRTG Config Directory</label>
            <input type="text" class="form-control" name="mrtg_cfg"
              value="<?= htmlspecialchars($settings['mrtg_cfg'] ?? '/etc/mrtg') ?>">
            <div class="form-text text-muted">Directory for generated MRTG .cfg files.</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Read-only system info -->
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="card-title"><i class="fas fa-server me-2 text-secondary"></i>System Info <small class="text-muted">(read-only)</small></h5>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label text-muted">PHP Version</label>
            <div class="form-control-plaintext text-muted"><?= PHP_VERSION ?></div>
          </div>
          <div class="col-md-4">
            <label class="form-label text-muted">APP_DIR</label>
            <div class="form-control-plaintext text-muted"><small><?= htmlspecialchars(defined('APP_DIR') ? APP_DIR : '—') ?></small></div>
          </div>
          <div class="col-md-4">
            <label class="form-label text-muted">Environment</label>
            <div class="form-control-plaintext text-muted"><?= htmlspecialchars(defined('APP_ENV') ? APP_ENV : '—') ?></div>
          </div>
        </div>
      </div>
    </div>

    <div class="d-flex gap-2">
      <button type="submit" class="btn btn-primary">
        <i class="fas fa-save me-1"></i>Save Settings
      </button>
      <a href="/" class="btn btn-secondary">Cancel</a>
    </div>
  </form>

</div>
