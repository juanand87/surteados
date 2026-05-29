<?php
/** SURTEADOS — Admin first-run installer */
$configPath = __DIR__ . '/../api/config.php';
require $configPath;

session_name(SESSION_NAME);
session_start();

$error   = '';
$success = '';

// Check if any admin user already exists
$pdo      = db();
$hasAdmin = (int)$pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username  = trim($_POST['username']  ?? '');
    $password  = trim($_POST['password']  ?? '');
    $password2 = trim($_POST['password2'] ?? '');

    if (!$username || !$password) {
        $error = 'Usuario y contraseña son requeridos.';
    } elseif (strlen($password) < 8) {
        $error = 'La contraseña debe tener al menos 8 caracteres.';
    } elseif ($password !== $password2) {
        $error = 'Las contraseñas no coinciden.';
    } elseif ($hasAdmin) {
        $error = 'Ya existe un administrador. Si necesitas restablecer la contraseña hazlo desde la base de datos.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $pdo->prepare('INSERT INTO admin_users (username, password) VALUES (?,?)')
            ->execute([$username, $hash]);
        $success = 'Administrador creado. Ya puedes <a href="index.php">iniciar sesión</a>.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Instalar — Surteados</title>
  <link rel="stylesheet" href="../assets/css/styles.css">
  <style>
    body { display:flex; justify-content:center; align-items:center; min-height:100vh; background:var(--bg-base); }
    .install-box { background:#fff; padding:2.5rem; border-radius:1rem; box-shadow:0 4px 24px rgba(0,0,0,.12); width:100%; max-width:420px; }
    .install-box h1 { margin-bottom:1.5rem; font-size:1.5rem; color:var(--primary); text-align:center; }
    .install-box .badge { background:#fef9c3; color:#854d0e; border:1px solid #fde68a; border-radius:.5rem; padding:.75rem 1rem; font-size:.85rem; margin-bottom:1.5rem; }
    .install-box .form-label { font-weight:600; }
    .install-box .btn { width:100%; margin-top:.5rem; }
    .msg-error { color:#dc2626; background:#fee2e2; border-radius:.5rem; padding:.75rem 1rem; margin-bottom:1rem; font-size:.9rem; }
    .msg-success { color:#166534; background:#dcfce7; border-radius:.5rem; padding:.75rem 1rem; margin-bottom:1rem; font-size:.9rem; }
  </style>
</head>
<body>
<div class="install-box">
  <h1>🎰 Instalar Surteados</h1>

  <?php if ($hasAdmin && !$success): ?>
    <div class="msg-error">Ya existe un administrador. Este instalador está deshabilitado.</div>
    <p style="text-align:center"><a href="index.php">Ir al panel de acceso</a></p>
  <?php else: ?>
    <div class="badge">⚠️ Elimina o protege este archivo después de la instalación.</div>

    <?php if ($error): ?><div class="msg-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="msg-success"><?= $success ?></div><?php else: ?>

    <form method="POST">
      <div class="form-group">
        <label class="form-label">Nombre de usuario</label>
        <input class="form-control" type="text" name="username" required autocomplete="username">
      </div>
      <div class="form-group">
        <label class="form-label">Contraseña</label>
        <input class="form-control" type="password" name="password" required minlength="8" autocomplete="new-password">
      </div>
      <div class="form-group">
        <label class="form-label">Confirmar contraseña</label>
        <input class="form-control" type="password" name="password2" required minlength="8" autocomplete="new-password">
      </div>
      <button type="submit" class="btn btn-primary">Crear administrador</button>
    </form>

    <?php endif; ?>
  <?php endif; ?>
</div>
</body>
</html>
