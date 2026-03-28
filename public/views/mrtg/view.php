<?php
/**
 * MRTG — View Config
 */
/** @var array $config */
?>
<div id="mrtg-view-page">

  <div class="d-flex align-items-center gap-2 mb-4">
    <a href="/mrtg" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
    <div>
      <h4 class="mb-0"><i class="fas fa-file-code me-2 text-primary"></i>MRTG Config</h4>
      <small class="text-muted"><?= htmlspecialchars($config['config_file'] ?? '') ?></small>
    </div>
    <div class="ms-auto d-flex gap-2">
      <button class="btn btn-warning btn-sm" id="regen-btn"
        data-router-id="<?= (int) ($config['router_id'] ?? 0) ?>">
        <i class="fas fa-sync me-1"></i>Regenerate
      </button>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-lg-3">
      <div class="card">
        <div class="card-header">
          <h5 class="card-title"><i class="fas fa-info-circle me-2 text-info"></i>Info</h5>
        </div>
        <div class="card-body">
          <table class="table-dark-custom w-100">
            <tbody>
              <tr><td class="text-muted">Router</td><td><?= htmlspecialchars($config['router_name'] ?? '') ?></td></tr>
              <tr><td class="text-muted">Config File</td><td><small><code><?= htmlspecialchars($config['config_file'] ?? '') ?></code></small></td></tr>
              <tr><td class="text-muted">Targets</td><td><?= (int) ($config['target_count'] ?? 0) ?></td></tr>
              <tr>
                <td class="text-muted">Status</td>
                <td>
                  <?php
                  $statusClass = match($config['status'] ?? 'pending') {
                      'active' => 'badge-success', 'error' => 'badge-danger', default => 'badge-warning'
                  };
                  ?>
                  <span class="badge <?= $statusClass ?>"><?= htmlspecialchars(ucfirst($config['status'] ?? '')) ?></span>
                </td>
              </tr>
              <tr><td class="text-muted">Generated</td><td><small><?= htmlspecialchars($config['generated_at'] ?? '—') ?></small></td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="col-lg-9">
      <div class="card">
        <div class="card-header">
          <h5 class="card-title"><i class="fas fa-code me-2 text-success"></i>Config Content</h5>
          <button class="btn btn-secondary btn-sm" id="copy-btn" title="Copy to clipboard">
            <i class="fas fa-copy me-1"></i>Copy
          </button>
        </div>
        <div class="card-body p-0">
          <pre class="bg-dark p-4 m-0 text-success small" id="config-content" style="max-height:600px;overflow-y:auto;"><?= htmlspecialchars($config['config_content'] ?? '# Config not available') ?></pre>
        </div>
      </div>
    </div>
  </div>

</div>

<script>
document.getElementById('copy-btn')?.addEventListener('click', function () {
    const text = document.getElementById('config-content').innerText;
    navigator.clipboard.writeText(text).then(() => {
        this.innerHTML = '<i class="fas fa-check me-1"></i>Copied!';
        setTimeout(() => { this.innerHTML = '<i class="fas fa-copy me-1"></i>Copy'; }, 2000);
    });
});
</script>
