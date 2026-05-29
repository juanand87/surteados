<?php
/** SURTEADOS — Admin login */
require __DIR__ . '/../api/config.php';

session_name(SESSION_NAME);
if (session_status() === PHP_SESSION_NONE) session_start();

if (!empty($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username && $password) {
        $stmt = db()->prepare('SELECT id, username, password FROM admin_users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id']       = $user['id'];
            $_SESSION['admin_username'] = $user['username'];
            header('Location: dashboard.php');
            exit;
        }
    }
    $error = 'Usuario o contraseña incorrectos.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Acceso Administrador — Surteados</title>
  <link rel="stylesheet" href="../assets/css/styles.css">
  <style>
    body { display:flex; justify-content:center; align-items:center; min-height:100vh; background:var(--bg-base); }
    .login-box { background:#fff; padding:2.5rem; border-radius:1.25rem; box-shadow:0 8px 32px rgba(0,0,0,.12); width:100%; max-width:400px; }
    .login-logo { text-align:center; margin-bottom:1.5rem; }
    .login-logo span { font-size:2.5rem; }
    .login-logo h1 { font-size:1.4rem; color:var(--primary); margin:.25rem 0 0; }
    .login-logo p  { color:var(--text-secondary); font-size:.85rem; margin:0; }
    .form-label { font-weight:600; }
    .btn-login { width:100%; margin-top:.5rem; }
    .login-error { background:#fee2e2; color:#dc2626; border-radius:.5rem; padding:.75rem 1rem; font-size:.88rem; margin-bottom:1rem; }
    .login-footer { text-align:center; margin-top:1.25rem; font-size:.8rem; color:var(--text-secondary); }
  </style>
</head>
<body>
<div class="login-box">
  <div class="login-logo">
    <span>🎰</span>
    <h1>Surteados</h1>
    <p>Acceso privado</p>
  </div>

  <?php if ($error): ?>
    <div class="login-error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST">
    <div class="form-group">
      <label class="form-label" for="username">Usuario</label>
      <input class="form-control" id="username" type="text" name="username"
             value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
             required autocomplete="username" autofocus>
    </div>
    <div class="form-group">
      <label class="form-label" for="password">Contraseña</label>
      <input class="form-control" id="password" type="password" name="password"
             required autocomplete="current-password">
    </div>
    <button class="btn btn-primary btn-login" type="submit">Ingresar</button>
  </form>

  <div class="login-footer">
    ¿Primera vez? <a href="install.php">Configurar administrador</a>
  </div>
</div>
</body>
</html>
