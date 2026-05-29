<?php
/** SURTEADOS — Customer Auth API (Mis Tickets) */
require __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = trim((string)($_GET['action'] ?? ''));

function is_local_dev(): bool {
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    return str_contains($host, 'localhost') || str_contains($host, '127.0.0.1') || str_contains(strtolower(BASE_URL), 'localhost');
}

function start_client_auth_session(): void {
    client_session_start();
}

function set_client_auth(array $user): void {
    start_client_auth_session();
    session_regenerate_id(true);
    $_SESSION['client_auth_email'] = strtolower(trim((string)($user['email'] ?? '')));
    $_SESSION['client_user_id']    = (int)($user['id'] ?? 0);
    $_SESSION['client_username']   = (string)($user['username'] ?? '');
}

function clear_client_auth(): void {
    start_client_auth_session();
    $_SESSION = [];
    session_destroy();
}

function send_code_email(string $toEmail, string $code): bool {
    $subject = 'Codigo de acceso - Mis Tickets Surteados';
    $message = "Hola,\n\nTu codigo de acceso para Mis Tickets es: {$code}\n\nEste codigo expira en 10 minutos.\n\nSi no solicitaste este acceso, ignora este mensaje.\n";
    $headers = "From: Surteados <no-reply@surteados.cl>\r\n" .
               "Reply-To: no-reply@surteados.cl\r\n" .
               "MIME-Version: 1.0\r\n" .
               "Content-Type: text/plain; charset=UTF-8\r\n";

    return @mail($toEmail, $subject, $message, $headers);
}

if ($method === 'GET' && $action === 'session') {
    start_client_auth_session();
    if (!empty($_SESSION['client_auth_email'])) {
        json_ok([
            'authenticated' => true,
            'email' => $_SESSION['client_auth_email'],
            'username' => $_SESSION['client_username'] ?? null,
        ]);
    }
    json_ok(['authenticated' => false]);
}

if ($method !== 'POST') {
    json_error('Método no permitido', 405);
}

$b = body();
$pdo = db();

if ($action === 'request_code') {
    $email = strtolower(trim((string)($b['email'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_error('Correo electrónico inválido');
    }

    $lastStmt = $pdo->prepare('SELECT created_at FROM ticket_access_codes WHERE email = ? ORDER BY id DESC LIMIT 1');
    $lastStmt->execute([$email]);
    $last = $lastStmt->fetchColumn();
    if ($last && strtotime($last) > (time() - 60)) {
        json_error('Espera 60 segundos antes de pedir otro código', 429);
    }

    $pdo->prepare('DELETE FROM ticket_access_codes WHERE expires_at < NOW() OR used_at IS NOT NULL')->execute();

    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $hash = password_hash($code, PASSWORD_DEFAULT);

    $ins = $pdo->prepare('INSERT INTO ticket_access_codes (email, code_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))');
    $ins->execute([$email, $hash]);

    $sent = send_code_email($email, $code);
    $resp = [
        'sent' => $sent,
        'message' => $sent
            ? 'Código enviado al correo electrónico.'
            : 'No se pudo enviar el correo. Revisa configuración de correo del servidor.',
    ];
    if (is_local_dev()) {
        $resp['dev_code'] = $code;
    }

    json_ok($resp);
}

if ($action === 'verify_code') {
    $email = strtolower(trim((string)($b['email'] ?? '')));
    $code  = trim((string)($b['code'] ?? ''));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) json_error('Correo inválido');
    if (!preg_match('/^\d{6}$/', $code)) json_error('Código inválido');

    $stmt = $pdo->prepare(
        'SELECT * FROM ticket_access_codes
          WHERE email = ? AND used_at IS NULL AND expires_at >= NOW()
          ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([$email]);
    $row = $stmt->fetch();
    if (!$row) json_error('Código vencido o no encontrado', 401);
    if ((int)$row['attempts'] >= 5) json_error('Demasiados intentos. Solicita un código nuevo.', 429);

    if (!password_verify($code, $row['code_hash'])) {
        $pdo->prepare('UPDATE ticket_access_codes SET attempts = attempts + 1 WHERE id = ?')->execute([$row['id']]);
        json_error('Código incorrecto', 401);
    }

    $pdo->prepare('UPDATE ticket_access_codes SET used_at = NOW() WHERE id = ?')->execute([$row['id']]);

    $uStmt = $pdo->prepare('SELECT id, username, email FROM customer_users WHERE email = ? LIMIT 1');
    $uStmt->execute([$email]);
    $user = $uStmt->fetch();

    set_client_auth([
        'id' => $user['id'] ?? 0,
        'username' => $user['username'] ?? null,
        'email' => $email,
    ]);

    json_ok([
        'authenticated' => true,
        'email' => $email,
        'username' => $user['username'] ?? null,
    ]);
}

if ($action === 'register') {
    $username = trim((string)($b['username'] ?? ''));
    $email    = strtolower(trim((string)($b['email'] ?? '')));
    $password = (string)($b['password'] ?? '');

    if (!preg_match('/^[a-zA-Z0-9._-]{3,30}$/', $username)) {
        json_error('Usuario inválido. Usa 3-30 caracteres: letras, números, punto, guion o guion bajo');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) json_error('Correo inválido');
    if (strlen($password) < 8) json_error('La contraseña debe tener al menos 8 caracteres');

    $stmt = $pdo->prepare('SELECT id FROM customer_users WHERE username = ? OR email = ? LIMIT 1');
    $stmt->execute([$username, $email]);
    if ($stmt->fetch()) json_error('Usuario o correo ya registrado');

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $ins = $pdo->prepare('INSERT INTO customer_users (username, email, password) VALUES (?, ?, ?)');
    $ins->execute([$username, $email, $hash]);

    set_client_auth(['id' => $pdo->lastInsertId(), 'username' => $username, 'email' => $email]);

    json_ok(['authenticated' => true, 'email' => $email, 'username' => $username]);
}

if ($action === 'login') {
    $identifier = trim((string)($b['identifier'] ?? ''));
    $password   = (string)($b['password'] ?? '');

    if ($identifier === '' || $password === '') json_error('Completa usuario/correo y contraseña');

    $stmt = $pdo->prepare('SELECT id, username, email, password FROM customer_users WHERE username = ? OR email = ? LIMIT 1');
    $stmt->execute([$identifier, strtolower($identifier)]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        json_error('Credenciales inválidas', 401);
    }

    set_client_auth($user);
    json_ok(['authenticated' => true, 'email' => $user['email'], 'username' => $user['username']]);
}

if ($action === 'logout') {
    clear_client_auth();
    json_ok(['authenticated' => false]);
}

json_error('Acción no válida', 400);
