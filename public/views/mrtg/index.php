<?php
/**
 * MRTG Configuration Index View
 *
 * @var array $configs  List of MRTG config records
 */
$pageTitle = 'MRTG Manager';
?>
<div id="mrtg-page">

  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="page-title">
      <i class="fas fa-chart-area me-2 text-primary"></i>MRTG Manager
    </h2>
    <form method="POST" action="/mrtg/generate" id="generate-form">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? '') ?>">
      <button type="submit" class="btn btn-primary" id="generate-btn">
        <i class="fas fa-cogs me-1"></i> Generate MRTG Config
      </button>
    </form>
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

  <!-- MRTG Overview -->
  <div class="row g-3 mb-4">
    <div class="col-md-4">
      <div class="stat-card blue">
        <div class="stat-icon"><i class="fas fa-file-code"></i></div>
        <div class="stat-value"><?= count($configs ?? []) ?></div>
        <div class="stat-label">Config Targets</div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="stat-card green">
        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
        <div class="stat-value">
          <?= count(array_filter($configs ?? [], fn($c) => ($c['status'] ?? '') === 'active')) ?>
        </div>
        <div class="stat-label">Active</div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="stat-card orange">
        <div class="stat-icon"><i class="fas fa-clock"></i></div>
        <div class="stat-value">
          <?= count(array_filter($configs ?? [], fn($c) => ($c['status'] ?? '') === 'pending')) ?>
        </div>
        <div class="stat-label">Pending</div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h5 class="card-title mb-0">
        <i class="fas fa-list me-2"></i>MRTG Targets
      </h5>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0" id="mrtg-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Target</th>
              <th>Description</th>
              <th>Type</th>
              <th>Status</th>
              <th>Last Generated</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($configs)): ?>
              <tr>
                <td colspan="7" class="text-center text-muted py-4">
                  <i class="fas fa-info-circle me-1"></i>
                  No MRTG targets found. Click <strong>Generate MRTG Config</strong> to create targets from monitored interfaces.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($configs as $c): ?>
                <tr>
                  <td><?= (int) $c['id'] ?></td>
                  <td><code><?= htmlspecialchars($c['target_key'] ?? '') ?></code></td>
                  <td class="text-muted small"><?= htmlspecialchars($c['description'] ?? '') ?></td>
                  <td>
                    <span class="badge bg-secondary"><?= htmlspecialchars($c['target_type'] ?? '') ?></span>
                  </td>
                  <td>
                    <?php $status = $c['status'] ?? 'pending'; ?>
                    <span class="badge bg-<?= $status === 'active' ? 'success' : ($status === 'error' ? 'danger' : 'warning text-dark') ?>">
                      <?= htmlspecialchars($status) ?>
                    </span>
                  </td>
                  <td class="text-muted small">
                    <?= $c['last_generated'] ? htmlspecialchars($c['last_generated']) : '—' ?>
                  </td>
                  <td class="text-end">
                    <a href="/mrtg/<?= (int) $c['id'] ?>" class="btn btn-sm btn-outline-secondary" title="View Config">
                      <i class="fas fa-eye"></i>
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script src="<?= APP_URL ?>/assets/js/datatables-init.js" defer></script>
