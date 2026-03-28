<?php
/**
 * Routers — Create View
 */
/** @var string $csrf */
/** @var array  $old    old input on validation failure */
/** @var array  $errors validation errors */
$old    = $old    ?? [];
$errors = $errors ?? [];
?>
<div id="router-create-page">

  <div class="d-flex align-items-center gap-2 mb-4">
    <a href="/routers" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
    <div>
      <h4 class="mb-0"><i class="fas fa-plus-circle me-2 text-primary"></i>Add New Router</h4>
      <small class="text-muted">Register a new MikroTik router for monitoring</small>
    </div>
  </div>

  <div class="row">
    <div class="col-lg-7">
      <div class="card">
        <div class="card-header">
          <h5 class="card-title">Router Details</h5>
        </div>
        <div class="card-body">
          <form method="POST" action="/routers" id="router-form">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? '') ?>">

            <div class="mb-3">
              <label class="form-label">Router Name <span class="text-danger">*</span></label>
              <input type="text" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>"
                name="name" value="<?= htmlspecialchars($old['name'] ?? '') ?>"
                placeholder="e.g. Core-Router-01" required>
              <?php if (isset($errors['name'])): ?>
                <div class="invalid-feedback"><?= htmlspecialchars($errors['name']) ?></div>
              <?php endif; ?>
            </div>

            <div class="mb-3">
              <label class="form-label">IP Address <span class="text-danger">*</span></label>
              <input type="text" class="form-control <?= isset($errors['ip_address']) ? 'is-invalid' : '' ?>"
                name="ip_address" value="<?= htmlspecialchars($old['ip_address'] ?? '') ?>"
                placeholder="e.g. 192.168.1.1" required pattern="^[\d.]+$">
              <?php if (isset($errors['ip_address'])): ?>
                <div class="invalid-feedback"><?= htmlspecialchars($errors['ip_address']) ?></div>
              <?php endif; ?>
            </div>

            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">SNMP Community</label>
                <input type="text" class="form-control" name="snmp_community"
                  value="<?= htmlspecialchars($old['snmp_community'] ?? 'public') ?>"
                  placeholder="public">
              </div>
              <div class="col-md-3 mb-3">
                <label class="form-label">SNMP Version</label>
                <select class="form-select" name="snmp_version">
                  <option value="2c" <?= ($old['snmp_version'] ?? '2c') === '2c' ? 'selected' : '' ?>>v2c</option>
                  <option value="1"  <?= ($old['snmp_version'] ?? '2c') === '1'  ? 'selected' : '' ?>>v1</option>
                  <option value="3"  <?= ($old['snmp_version'] ?? '2c') === '3'  ? 'selected' : '' ?>>v3</option>
                </select>
              </div>
              <div class="col-md-3 mb-3">
                <label class="form-label">SNMP Port</label>
                <input type="number" class="form-control" name="snmp_port"
                  value="<?= (int) ($old['snmp_port'] ?? 161) ?>" min="1" max="65535">
              </div>
            </div>

            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">API Username <small class="text-muted">(optional)</small></label>
                <input type="text" class="form-control" name="username"
                  value="<?= htmlspecialchars($old['username'] ?? '') ?>"
                  placeholder="admin" autocomplete="off">
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">API Password <small class="text-muted">(optional)</small></label>
                <input type="password" class="form-control" name="password"
                  placeholder="Leave blank to keep unchanged" autocomplete="new-password">
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label">Status</label>
              <select class="form-select" name="status">
                <option value="active"   <?= ($old['status'] ?? 'active') === 'active'   ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= ($old['status'] ?? 'active') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
              </select>
            </div>

            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-primary">
                <i class="fas fa-save me-1"></i>Save Router
              </button>
              <button type="button" class="btn btn-secondary" id="test-snmp-btn" disabled>
                <i class="fas fa-plug me-1"></i>Test SNMP
              </button>
              <a href="/routers" class="btn btn-secondary">Cancel</a>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="card">
        <div class="card-header">
          <h5 class="card-title"><i class="fas fa-info-circle me-2 text-info"></i>Help</h5>
        </div>
        <div class="card-body">
          <p class="text-muted small">Configure SNMP on your MikroTik router:</p>
          <pre class="bg-dark border rounded p-2 small text-success">/snmp set enabled=yes
/snmp community set [ find name=public ] \
  name=public read-access=yes \
  addresses=10.0.0.0/8</pre>
          <hr class="border-secondary">
          <ul class="text-muted small mb-0">
            <li>Ensure UDP port 161 is open on the router</li>
            <li>SNMP v2c is recommended for MikroTik</li>
            <li>Use a read-only community string</li>
            <li>API credentials are optional (for future use)</li>
          </ul>
        </div>
      </div>
    </div>
  </div>

</div>
