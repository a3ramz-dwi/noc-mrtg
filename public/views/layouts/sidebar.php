<?php
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
function isActive(string $path, string $current, bool $exact = false): string {
    if ($exact) return $current === $path ? 'active' : '';
    return str_starts_with($current, $path) ? 'active' : '';
}
?>
<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="brand-icon"><i class="fas fa-network-wired"></i></div>
    <div>
      <div class="brand-text">NOC Manager</div>
      <div class="brand-sub">v<?= APP_VERSION ?></div>
    </div>
  </div>

  <nav class="sidebar-nav">
    <ul class="nav-item" style="list-style:none;padding:0;">

      <li class="nav-item">
        <a href="/" class="nav-link <?= isActive('/', $currentPath, true) ?>">
          <span class="nav-icon"><i class="fas fa-tachometer-alt"></i></span>
          <span>Dashboard</span>
        </a>
      </li>

      <div class="nav-section-title">Network</div>

      <li class="nav-item">
        <a href="/routers" class="nav-link <?= isActive('/routers', $currentPath) ?>">
          <span class="nav-icon"><i class="fas fa-network-wired"></i></span>
          <span>Routers</span>
        </a>
      </li>

      <li class="nav-item">
        <a href="/interfaces" class="nav-link <?= isActive('/interfaces', $currentPath) ?>">
          <span class="nav-icon"><i class="fas fa-ethernet"></i></span>
          <span>Interfaces</span>
        </a>
      </li>

      <li class="nav-item">
        <a href="/queues" class="nav-link <?= isActive('/queues', $currentPath) ?>">
          <span class="nav-icon"><i class="fas fa-layer-group"></i></span>
          <span>Queues</span>
        </a>
      </li>

      <li class="nav-item">
        <a href="/pppoe" class="nav-link <?= isActive('/pppoe', $currentPath) ?>">
          <span class="nav-icon"><i class="fas fa-users"></i></span>
          <span>PPPoE Users</span>
        </a>
      </li>

      <div class="nav-section-title">Monitoring</div>

      <li class="nav-item">
        <a href="/monitoring/interfaces" class="nav-link <?= isActive('/monitoring/interfaces', $currentPath) ?>">
          <span class="nav-icon"><i class="fas fa-chart-line"></i></span>
          <span>Interfaces</span>
        </a>
      </li>

      <li class="nav-item">
        <a href="/monitoring/queues" class="nav-link <?= isActive('/monitoring/queues', $currentPath) ?>">
          <span class="nav-icon"><i class="fas fa-chart-bar"></i></span>
          <span>Queues</span>
        </a>
      </li>

      <li class="nav-item">
        <a href="/monitoring/pppoe" class="nav-link <?= isActive('/monitoring/pppoe', $currentPath) ?>">
          <span class="nav-icon"><i class="fas fa-user-clock"></i></span>
          <span>PPPoE</span>
        </a>
      </li>

      <li class="nav-item">
        <a href="/monitoring/live" class="nav-link <?= isActive('/monitoring/live', $currentPath) ?>">
          <span class="nav-icon"><i class="fas fa-broadcast-tower"></i></span>
          <span>Live Bandwidth</span>
          <span class="badge badge-online ms-auto" style="font-size:9px;">LIVE</span>
        </a>
      </li>

      <div class="nav-section-title">Config</div>

      <li class="nav-item">
        <a href="/mrtg" class="nav-link <?= isActive('/mrtg', $currentPath) ?>">
          <span class="nav-icon"><i class="fas fa-cog"></i></span>
          <span>MRTG Config</span>
        </a>
      </li>

      <li class="nav-item">
        <a href="/settings" class="nav-link <?= isActive('/settings', $currentPath) ?>">
          <span class="nav-icon"><i class="fas fa-sliders-h"></i></span>
          <span>Settings</span>
        </a>
      </li>

      <div class="nav-section-title">Account</div>

      <li class="nav-item">
        <a href="/logout" class="nav-link" style="color:var(--accent-red);">
          <span class="nav-icon"><i class="fas fa-sign-out-alt"></i></span>
          <span>Logout</span>
        </a>
      </li>

    </ul>
  </nav>
</aside>
