<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES) ?>">
  <title><?= htmlspecialchars($pageTitle ?? 'NOC Manager') ?> | NOC Manager v<?= APP_VERSION ?></title>
  <!-- Bootstrap 5 Dark -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <!-- Custom CSS -->
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/noc-dashboard.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/dark-theme.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/modern-theme.css" id="theme-stylesheet">
</head>
<body class="dark-mode">
<div class="wrapper">
  <!-- Sidebar -->
  <?php include __DIR__ . '/sidebar.php'; ?>

  <!-- Main -->
  <div class="main-content">
    <!-- Navbar -->
    <nav class="navbar">
      <button id="sidebar-toggle" class="btn-icon d-lg-none" title="Toggle Sidebar">
        <i class="fas fa-bars"></i>
      </button>
      <span class="navbar-title"><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></span>
      <div class="navbar-actions">
        <span id="refresh-spinner" class="spinner" style="display:none;"></span>
        <small id="refresh-countdown" class="text-muted me-2" title="Next refresh">30s</small>
        <button id="theme-toggle" class="btn-icon" title="Toggle Theme">
          <i class="fas fa-sun"></i>
        </button>
        <a href="/settings" class="btn-icon" title="Settings">
          <i class="fas fa-cog"></i>
        </a>
        <div class="dropdown">
          <button class="btn-icon" data-bs-toggle="dropdown" title="User Menu">
            <i class="fas fa-user-circle"></i>
          </button>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><span class="dropdown-item-text text-muted small"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?></span></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="/settings"><i class="fas fa-cog me-2"></i>Settings</a></li>
            <li><a class="dropdown-item text-danger" href="/logout"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
          </ul>
        </div>
      </div>
    </nav>

    <!-- Flash messages slot -->
    <div id="flash-server" style="padding:16px 24px 0;">
      <?php if (!empty($flash)): ?>
        <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>" data-flash="<?= htmlspecialchars($flash['message']) ?>" data-flash-type="<?= htmlspecialchars($flash['type']) ?>"></div>
      <?php endif; ?>
    </div>

    <!-- Page Content -->
    <div class="page-content">
      <?= $content ?? '' ?>
    </div>

    <!-- Footer -->
    <footer class="footer">
      NOC Manager v<?= APP_VERSION ?> &mdash; <?= date('Y') ?> &mdash; All rights reserved.
    </footer>
  </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<!-- App JS -->
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<script src="<?= APP_URL ?>/assets/js/charts.js"></script>
<script src="<?= APP_URL ?>/assets/js/live-traffic.js"></script>
<script src="<?= APP_URL ?>/assets/js/dashboard.js"></script>
<?= $extraScripts ?? '' ?>
</body>
</html>
