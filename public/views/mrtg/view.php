<?php
/**
 * MRTG Config Detail View
 *
 * @var array $config  MRTG config record with file content
 */
$pageTitle = 'MRTG Config: ' . htmlspecialchars($config['target_key'] ?? '');
?>
<div id="mrtg-view-page">

  <div class="mb-4">
    <a href="/mrtg" class="text-muted text-decoration-none small">
      <i class="fas fa-arrow-left me-1"></i> Back to MRTG Manager
    </a>
    <h2 class="page-title mt-1">
      <i class="fas fa-file-code me-2 text-primary"></i>
      <?= htmlspecialchars($config['target_key'] ?? 'MRTG Config') ?>
    </h2>
  </div>

  <div class="row g-3">
    <div class="col-lg-4">
      <div class="card">
        <div class="card-header">
          <h5 class="card-title mb-0"><i class="fas fa-info-circle me-2 text-info"></i>Config Info</h5>
        </div>
        <div class="card-body">
          <dl class="row mb-0">
            <dt class="col-sm-5 text-muted">Target Key</dt>
            <dd class="col-sm-7"><code><?= htmlspecialchars($config['target_key'] ?? '') ?></code></dd>

            <dt class="col-sm-5 text-muted">Type</dt>
            <dd class="col-sm-7">
              <span class="badge bg-secondary"><?= htmlspecialchars($config['target_type'] ?? '') ?></span>
            </dd>

            <dt class="col-sm-5 text-muted">Status</dt>
            <dd class="col-sm-7">
              <?php $status = $config['status'] ?? 'pending'; ?>
              <span class="badge bg-<?= $status === 'active' ? 'success' : ($status === 'error' ? 'danger' : 'warning text-dark') ?>">
                <?= htmlspecialchars($status) ?>
              </span>
            </dd>

            <dt class="col-sm-5 text-muted">Description</dt>
            <dd class="col-sm-7 text-muted small"><?= htmlspecialchars($config['description'] ?? '—') ?></dd>

            <dt class="col-sm-5 text-muted">Config File</dt>
            <dd class="col-sm-7 text-muted small">
              <code><?= htmlspecialchars($config['config_file'] ?? '—') ?></code>
            </dd>

            <dt class="col-sm-5 text-muted">Last Generated</dt>
            <dd class="col-sm-7 text-muted small">
              <?= $config['last_generated'] ? htmlspecialchars($config['last_generated']) : '—' ?>
            </dd>
          </dl>
        </div>
      </div>
    </div>

    <?php if (!empty($config['config_content'])): ?>
    <div class="col-lg-8">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="card-title mb-0">
            <i class="fas fa-code me-2 text-success"></i>Config Content
          </h5>
          <a href="/mrtg/<?= (int) $config['id'] ?>/download" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-download me-1"></i> Download
          </a>
        </div>
        <div class="card-body p-0">
          <pre class="p-3 mb-0 text-success" style="background:#0d1117;max-height:500px;overflow:auto;font-size:12px;"><?= htmlspecialchars($config['config_content']) ?></pre>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>
