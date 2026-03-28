<?php
/**
 * Routers — Edit View
 */
/** @var array  $router */
/** @var string $csrf */
$errors = $errors ?? [];
?>
<div id="router-edit-page">

  <div class="d-flex align-items-center gap-2 mb-4">
    <a href="/routers/<?= (int) $router['id'] ?>" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
    <div>
      <h4 class="mb-0"><i class="fas fa-edit me-2 text-warning"></i>Edit Router</h4>
      <small class="text-muted"><?= htmlspecialchars($router['name']) ?></small>
    </div>
  </div>

  <div class="row">
    <div class="col-lg-7">
      <div class="card">
        <div class="card-header">
          <h5 class="card-title">Router Details</h5>
        </div>
        <div class="card-body">
          <form method="POST" action="/routers/<?= (int) $router['id'] ?>" id="router-edit-form">
            <input type="hidden" name="_method" value="PUT">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? '') ?>">

            <div class="mb-3">
              <label class="form-label">Router Name <span class="text-danger">*</span></label>
              <input type="text" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>"
                name="name" value="<?= htmlspecialchars($router['name'] ?? '') ?>" required>
              <?php if (isset($errors['name'])): ?>
                <div class="invalid-feedback"><?= htmlspecialchars($errors['name']) ?></div>
              <?php endif; ?>
            </div>

            <div class="mb-3">
              <label class="form-label">IP Address <span class="text-danger">*</span></label>
              <input type="text" class="form-control <?= isset($errors['ip_address']) ? 'is-invalid' : '' ?>"
                name="ip_address" value="<?= htmlspecialchars($router['ip_address'] ?? '') ?>" required pattern="^[\d.]+$">
              <?php if (isset($errors['ip_address'])): ?>
                <div class="invalid-feedback"><?= htmlspecialchars($errors['ip_address']) ?></div>
              <?php endif; ?>
            </div>

            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">SNMP Community</label>
                <input type="text" class="form-control" name="snmp_community"
                  value="<?= htmlspecialchars($router['snmp_community'] ?? 'public') ?>">
              </div>
              <div class="col-md-3 mb-3">
                <label class="form-label">SNMP Version</label>
                <select class="form-select" name="snmp_version">
                  <option value="2c" <?= ($router['snmp_version'] ?? '2c') === '2c' ? 'selected' : '' ?>>v2c</option>
                  <option value="1"  <?= ($router['snmp_version'] ?? '2c') === '1'  ? 'selected' : '' ?>>v1</option>
                  <option value="3"  <?= ($router['snmp_version'] ?? '2c') === '3'  ? 'selected' : '' ?>>v3</option>
                </select>
              </div>
              <div class="col-md-3 mb-3">
                <label class="form-label">SNMP Port</label>
                <input type="number" class="form-control" name="snmp_port"
                  value="<?= (int) ($router['snmp_port'] ?? 161) ?>" min="1" max="65535">
              </div>
            </div>

            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">API Username <small class="text-muted">(optional)</small></label>
                <input type="text" class="form-control" name="username"
                  value="<?= htmlspecialchars($router['username'] ?? '') ?>" autocomplete="off">
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">API Password <small class="text-muted">(leave blank to keep)</small></label>
                <input type="password" class="form-control" name="password"
                  placeholder="Leave blank to keep unchanged" autocomplete="new-password">
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label">Status</label>
              <select class="form-select" name="status">
                <option value="active"   <?= ($router['status'] ?? 'active') === 'active'   ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= ($router['status'] ?? 'active') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
              </select>
            </div>

            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-warning">
                <i class="fas fa-save me-1"></i>Update Router
              </button>
              <button type="button" class="btn btn-info" id="test-snmp-btn" data-id="<?= (int) $router['id'] ?>">
                <i class="fas fa-plug me-1"></i>Test SNMP
              </button>
              <a href="/routers/<?= (int) $router['id'] ?>" class="btn btn-secondary">Cancel</a>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="card">
        <div class="card-header">
          <h5 class="card-title"><i class="fas fa-info-circle me-2 text-info"></i>Router Info</h5>
        </div>
        <div class="card-body">
          <?php if (!empty($router['sysDescr'])): ?>
            <div class="mb-2">
              <small class="text-muted">System Description</small>
              <div class="small"><?= htmlspecialchars($router['sysDescr']) ?></div>
            </div>
          <?php endif; ?>
          <?php if (!empty($router['sysName'])): ?>
            <div class="mb-2">
              <small class="text-muted">System Name</small>
              <div><?= htmlspecialchars($router['sysName']) ?></div>
            </div>
          <?php endif; ?>
          <?php if (!empty($router['last_seen'])): ?>
            <div class="mb-2">
              <small class="text-muted">Last Seen</small>
              <div><?= htmlspecialchars($router['last_seen']) ?></div>
            </div>
          <?php endif; ?>
          <div id="snmp-test-result" class="mt-3" style="display:none;"></div>
        </div>
      </div>
    </div>
  </div>

</div>

<?php $extraScripts = '<script src="' . APP_URL . '/assets/js/router-manager.js"></script>'; ?>
