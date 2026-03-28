<?php
/**
 * Router Create View
 *
 * @var string $csrf  CSRF token
 */
$pageTitle = 'Add Router';
?>
<div id="router-create-page">

  <div class="mb-4">
    <a href="/routers" class="text-muted text-decoration-none small">
      <i class="fas fa-arrow-left me-1"></i> Back to Routers
    </a>
    <h2 class="page-title mt-1"><i class="fas fa-plus me-2 text-primary"></i>Add Router</h2>
  </div>

  <div class="card" style="max-width:640px;">
    <div class="card-body">

      <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
          <ul class="mb-0">
            <?php foreach ((array) $errors as $err): ?>
              <li><?= htmlspecialchars((string) $err) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form method="POST" action="/routers" id="router-form">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? '') ?>">

        <div class="mb-3">
          <label class="form-label" for="name">Router Name <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="name" name="name"
                 value="<?= htmlspecialchars($old['name'] ?? '') ?>"
                 placeholder="e.g. Core-Router-1" required>
        </div>

        <div class="mb-3">
          <label class="form-label" for="ip_address">IP Address <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="ip_address" name="ip_address"
                 value="<?= htmlspecialchars($old['ip_address'] ?? '') ?>"
                 placeholder="e.g. 192.168.1.1" required>
        </div>

        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label" for="snmp_community">SNMP Community</label>
            <input type="text" class="form-control" id="snmp_community" name="snmp_community"
                   value="<?= htmlspecialchars($old['snmp_community'] ?? 'public') ?>">
          </div>
          <div class="col-md-3 mb-3">
            <label class="form-label" for="snmp_version">SNMP Version</label>
            <select class="form-select" id="snmp_version" name="snmp_version">
              <?php foreach (['1', '2c'] as $v): ?>
                <option value="<?= $v ?>" <?= ($old['snmp_version'] ?? '2c') === $v ? 'selected' : '' ?>>
                  v<?= $v ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3 mb-3">
            <label class="form-label" for="snmp_port">SNMP Port</label>
            <input type="number" class="form-control" id="snmp_port" name="snmp_port"
                   value="<?= (int) ($old['snmp_port'] ?? 161) ?>" min="1" max="65535">
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label" for="status">Status</label>
          <select class="form-select" id="status" name="status">
            <option value="active"   <?= ($old['status'] ?? 'active') === 'active'   ? 'selected' : '' ?>>Active</option>
            <option value="inactive" <?= ($old['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
          </select>
        </div>

        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-save me-1"></i> Save Router
          </button>
          <a href="/routers" class="btn btn-outline-secondary">Cancel</a>
        </div>
      </form>

    </div>
  </div>
</div>
