<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES) ?>">
  <title>Login | NOC Manager</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/noc-dashboard.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/dark-theme.css">
  <style>
    body { background: #0d1117; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
    .login-wrapper { width: 100%; max-width: 400px; padding: 24px; }
    .login-card { background: #161b22; border: 1px solid #30363d; border-radius: 12px; padding: 40px 36px; }
    .login-logo { text-align: center; margin-bottom: 32px; }
    .login-logo .logo-icon { width: 64px; height: 64px; background: linear-gradient(135deg,#58a6ff,#bc8cff); border-radius: 16px; display: inline-flex; align-items: center; justify-content: center; font-size: 28px; color: #fff; margin-bottom: 16px; }
    .login-logo h1 { font-size: 22px; font-weight: 700; color: #e6edf3; margin: 0; }
    .login-logo p { color: #8b949e; font-size: 13px; margin-top: 4px; }
    .form-group { margin-bottom: 20px; }
    .input-icon { position: relative; }
    .input-icon .form-control { padding-left: 40px; }
    .input-icon .icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #6e7681; font-size: 14px; }
    .btn-login { width: 100%; padding: 10px; font-size: 15px; font-weight: 600; background: linear-gradient(135deg,#58a6ff,#bc8cff); border: none; border-radius: 8px; color: #fff; cursor: pointer; transition: opacity 0.2s; }
    .btn-login:hover { opacity: 0.9; }
    .error-box { background: rgba(248,81,73,0.1); border: 1px solid rgba(248,81,73,0.3); border-radius: 8px; padding: 12px 16px; color: #f85149; font-size: 13px; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; }
    .login-footer { text-align: center; margin-top: 20px; font-size: 12px; color: #6e7681; }
  </style>
</head>
<body class="dark-mode">
  <div class="login-wrapper">
    <div class="login-card">
      <div class="login-logo">
        <div class="logo-icon"><i class="fas fa-network-wired"></i></div>
        <h1>NOC Manager</h1>
        <p>Network Operations Center Dashboard</p>
      </div>

      <?php if (!empty($error)): ?>
        <div class="error-box">
          <i class="fas fa-exclamation-circle"></i>
          <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="/login" id="login-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">

        <div class="form-group">
          <label class="form-label" for="username">Username</label>
          <div class="input-icon">
            <i class="fas fa-user icon"></i>
            <input type="text" class="form-control" id="username" name="username"
              placeholder="Enter username" required autocomplete="username"
              value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="password">Password</label>
          <div class="input-icon">
            <i class="fas fa-lock icon"></i>
            <input type="password" class="form-control" id="password" name="password"
              placeholder="Enter password" required autocomplete="current-password">
            <button type="button" onclick="togglePass()" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:#6e7681;cursor:pointer;" title="Show/hide password">
              <i class="fas fa-eye" id="pass-eye"></i>
            </button>
          </div>
        </div>

        <button type="submit" class="btn-login">
          <i class="fas fa-sign-in-alt me-2"></i>Sign In
        </button>
      </form>

      <div class="login-footer">
        NOC Manager v<?= APP_VERSION ?> &mdash; Secure Access
      </div>
    </div>
  </div>

  <script>
    function togglePass() {
      const f = document.getElementById('password');
      const e = document.getElementById('pass-eye');
      if (f.type === 'password') { f.type = 'text'; e.className = 'fas fa-eye-slash'; }
      else { f.type = 'password'; e.className = 'fas fa-eye'; }
    }
    document.getElementById('login-form').addEventListener('submit', function (e) {
      const u = document.getElementById('username').value.trim();
      const p = document.getElementById('password').value;
      if (!u || !p) { e.preventDefault(); alert('Please enter username and password.'); }
    });
  </script>
</body>
</html>
