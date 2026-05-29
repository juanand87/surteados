<?php
/** SURTEADOS — Auth API */
require __DIR__ . '/config.php';

session_name(SESSION_NAME);
if (session_status() === PHP_SESSION_NONE) session_start();

$method = $_SERVER['REQUEST_METHOD'];

// ── GET: check session ────────────────────────────────────────────────────────
if ($method === 'GET') {
    if (!empty($_SESSION['admin_id'])) {
        json_ok(['logged_in' => true, 'username' => $_SESSION['admin_username']]);
    }
    json_ok(['logged_in' => false]);
}

// ── POST: login ───────────────────────────────────────────────────────────────
if ($method === 'POST') {
    $body     = body();
    $username = trim($body['username'] ?? '');
    $password = trim($body['password'] ?? '');

    if (!$username || !$password) {
        json_error('Usuario y contraseña requeridos');
    }

    $stmt = db()->prepare('SELECT id, username, password FROM admin_users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        // Rate-limiting hint (no real delay needed for demo — add in production)
        json_error('Credenciales incorrectas', 401);
    }

    session_regenerate_id(true);
    $_SESSION['admin_id']       = $user['id'];
    $_SESSION['admin_username'] = $user['username'];
    json_ok(['username' => $user['username']]);
}

// ── DELETE: logout ────────────────────────────────────────────────────────────
if ($method === 'DELETE') {
    $_SESSION = [];
    session_destroy();
    json_ok(['logged_out' => true]);
}
