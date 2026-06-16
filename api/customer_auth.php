<?php
/** SURTEADOS — Customer Auth API (Mis Tickets) */
require __DIR__ . '/config.php';
require_once __DIR__ . '/email_helper.php';
require_once __DIR__ . '/location_helper.php';

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

function normalize_chilean_rut(string $rut): string {
    $rut = strtoupper(preg_replace('/[^0-9K]/i', '', $rut));
    if (strlen($rut) < 2) return $rut;
    $body = substr($rut, 0, -1);
    $dv = substr($rut, -1);
    return number_format((int)$body, 0, '', '.') . '-' . $dv;
}

function is_valid_chilean_rut(string $rut): bool {
    $rut = strtoupper(preg_replace('/[^0-9K]/i', '', $rut));
    if (strlen($rut) < 2) return false;
    $body = substr($rut, 0, -1);
    $dv = substr($rut, -1);
    if (!ctype_digit($body)) return false;
    $sum = 0;
    $multiplier = 2;
    for ($i = strlen($body) - 1; $i >= 0; $i--) {
        $sum += ((int)$body[$i]) * $multiplier;
        $multiplier = $multiplier === 7 ? 2 : $multiplier + 1;
    }
    $expected = 11 - ($sum % 11);
    $expectedDv = $expected === 11 ? '0' : ($expected === 10 ? 'K' : (string)$expected);
    return $dv === $expectedDv;
}

function ensure_customer_auth_schema(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS customer_users (
      id           INT AUTO_INCREMENT PRIMARY KEY,
      username     VARCHAR(50)  NOT NULL UNIQUE,
      email        VARCHAR(150) NOT NULL UNIQUE,
      password     VARCHAR(255) NOT NULL,
      created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS ticket_access_codes (
      id           BIGINT AUTO_INCREMENT PRIMARY KEY,
      email        VARCHAR(150) NOT NULL,
      code_hash    VARCHAR(255) NOT NULL,
      attempts     TINYINT UNSIGNED DEFAULT 0,
      used_at      DATETIME NULL,
      expires_at   DATETIME NOT NULL,
      created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_email_exp (email, expires_at),
      INDEX idx_used_at (used_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS customer_email_verifications (
      id           BIGINT AUTO_INCREMENT PRIMARY KEY,
      email        VARCHAR(150) NOT NULL,
      code_hash    VARCHAR(255) NOT NULL,
      attempts     TINYINT UNSIGNED DEFAULT 0,
      used_at      DATETIME NULL,
      expires_at   DATETIME NOT NULL,
      created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_email_exp (email, expires_at),
      INDEX idx_used_at (used_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $cols = $pdo->query("SHOW COLUMNS FROM customer_users")->fetchAll();
    $existing = array_column($cols, 'Field');
    $adds = [
        'full_name' => "ALTER TABLE customer_users ADD COLUMN full_name VARCHAR(180) NULL AFTER email",
        'phone' => "ALTER TABLE customer_users ADD COLUMN phone VARCHAR(40) NULL AFTER full_name",
        'address' => "ALTER TABLE customer_users ADD COLUMN address VARCHAR(255) NULL AFTER phone",
        'commune_id' => "ALTER TABLE customer_users ADD COLUMN commune_id INT NULL AFTER address",
        'comuna' => "ALTER TABLE customer_users ADD COLUMN comuna VARCHAR(120) NULL AFTER commune_id",
        'rut' => "ALTER TABLE customer_users ADD COLUMN rut VARCHAR(30) NULL AFTER comuna",
        'status' => "ALTER TABLE customer_users ADD COLUMN status ENUM('pending','active','blocked') NOT NULL DEFAULT 'pending' AFTER rut",
        'email_verified_at' => "ALTER TABLE customer_users ADD COLUMN email_verified_at DATETIME NULL AFTER status",
        'google_id' => "ALTER TABLE customer_users ADD COLUMN google_id VARCHAR(120) NULL AFTER email_verified_at",
        'auth_provider' => "ALTER TABLE customer_users ADD COLUMN auth_provider VARCHAR(30) NOT NULL DEFAULT 'email' AFTER google_id",
    ];
    $addedStatus = false;
    foreach ($adds as $field => $sql) {
        if (!in_array($field, $existing, true)) {
            $pdo->exec($sql);
            if ($field === 'status') {
                $addedStatus = true;
            }
        }
    }
    if ($addedStatus) {
        $pdo->exec("UPDATE customer_users SET status = 'active', email_verified_at = COALESCE(email_verified_at, created_at, NOW()) WHERE email_verified_at IS NULL");
    }
}

function customer_email_cfg(PDO $pdo): array {
    $rows = $pdo->query(
        "SELECT `key`, `value` FROM settings
          WHERE `key` IN ('site_name','smtp_from_email','smtp_from_name','smtp_host','smtp_port','smtp_user','smtp_pass','smtp_encryption')"
    )->fetchAll();
    $cfg = [];
    foreach ($rows as $row) $cfg[$row['key']] = $row['value'];
    return $cfg;
}

function send_code_email(PDO $pdo, string $toEmail, string $code): bool {
    $cfg = customer_email_cfg($pdo);
    $siteName = htmlspecialchars($cfg['site_name'] ?? 'Surteados', ENT_QUOTES, 'UTF-8');
    $codeSafe = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
    $html = "
      <div style='font-family:Arial,sans-serif;max-width:560px;margin:0 auto;background:#140b30;color:#e2e8f0;border-radius:16px;overflow:hidden;border:1px solid #2d1f5e;'>
        <div style='background:linear-gradient(135deg,#7c3aed,#db2777);padding:24px;text-align:center;'>
          <h1 style='margin:0;color:#fff;font-size:22px;'>Codigo de acceso</h1>
          <p style='margin:8px 0 0;color:rgba(255,255,255,.82);'>{$siteName}</p>
        </div>
        <div style='padding:28px;text-align:center;'>
          <p style='margin:0 0 16px;color:#a0a0b0;'>Usa este codigo para ingresar a Mis Imagenes:</p>
          <div style='display:inline-block;background:#fff;color:#140b30;font-size:32px;font-weight:800;letter-spacing:8px;padding:14px 22px;border-radius:12px;'>{$codeSafe}</div>
          <p style='margin:18px 0 0;color:#a0a0b0;font-size:13px;'>Este codigo expira en 10 minutos. Si no solicitaste este acceso, ignora este mensaje.</p>
        </div>
      </div>";

    return surteados_send_email($cfg, $toEmail, '', 'Codigo de acceso - Mis Imagenes Surteados', $html);
}

function send_account_verification_email(PDO $pdo, string $toEmail, string $code): bool {
    $cfg = customer_email_cfg($pdo);
    $siteName = htmlspecialchars($cfg['site_name'] ?? 'Surteados', ENT_QUOTES, 'UTF-8');
    $codeSafe = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
    $html = "
      <div style='font-family:Arial,sans-serif;max-width:560px;margin:0 auto;background:#140b30;color:#e2e8f0;border-radius:16px;overflow:hidden;border:1px solid #2d1f5e;'>
        <div style='background:linear-gradient(135deg,#7c3aed,#db2777);padding:24px;text-align:center;'>
          <h1 style='margin:0;color:#fff;font-size:22px;'>Verifica tu cuenta</h1>
          <p style='margin:8px 0 0;color:rgba(255,255,255,.82);'>{$siteName}</p>
        </div>
        <div style='padding:28px;text-align:center;'>
          <p style='margin:0 0 16px;color:#a0a0b0;'>Ingresa este codigo para activar tu cuenta en Surteados:</p>
          <div style='display:inline-block;background:#fff;color:#140b30;font-size:32px;font-weight:800;letter-spacing:8px;padding:14px 22px;border-radius:12px;'>{$codeSafe}</div>
          <p style='margin:18px 0 0;color:#a0a0b0;font-size:13px;'>Este codigo expira en 30 minutos. Si no creaste esta cuenta, ignora este mensaje.</p>
        </div>
      </div>";

    return surteados_send_email($cfg, $toEmail, '', 'Verifica tu cuenta - Surteados', $html);
}
if ($action === 'captcha') {
    start_client_auth_session();
    $a = random_int(2, 9);
    $c = random_int(2, 9);
    $_SESSION['customer_register_captcha'] = $a + $c;
    json_ok(['question' => "{$a} + {$c}"]);
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
ensure_customer_auth_schema($pdo);

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

    $sent = send_code_email($pdo, $email, $code);
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
    start_client_auth_session();
    $fullName = trim((string)($b['fullName'] ?? ''));
    $phone = trim((string)($b['phone'] ?? ''));
    $address = trim((string)($b['address'] ?? ''));
    $buyerComuna = trim((string)($b['buyerComuna'] ?? ''));
    $buyerCommuneId = $b['buyerCommuneId'] ?? null;
    $rut = trim((string)($b['rut'] ?? ''));
    $email = strtolower(trim((string)($b['email'] ?? '')));
    $emailConfirm = strtolower(trim((string)($b['emailConfirm'] ?? '')));
    $password = (string)($b['password'] ?? '');
    $passwordConfirm = (string)($b['passwordConfirm'] ?? '');
    $captcha = trim((string)($b['captcha'] ?? ''));

    if ($fullName === '' || $phone === '' || $address === '' || $buyerComuna === '' || $rut === '') {
        json_error('Completa todos los datos obligatorios');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) json_error('Correo invalido');
    if ($email !== $emailConfirm) json_error('Los correos no coinciden');
    if (strlen($password) < 8) json_error('La clave debe tener al menos 8 caracteres');
    if ($password !== $passwordConfirm) json_error('Las claves no coinciden');
    if (!is_valid_chilean_rut($rut)) json_error('RUT chileno invalido');
    $rut = normalize_chilean_rut($rut);
    if ($captcha === '' || (int)$captcha !== (int)($_SESSION['customer_register_captcha'] ?? -1)) {
        json_error('Captcha incorrecto');
    }

    $existingStmt = $pdo->prepare('SELECT id, status, email_verified_at FROM customer_users WHERE email = ? LIMIT 1');
    $existingStmt->execute([$email]);
    $existing = $existingStmt->fetch();
    if ($existing && (($existing['status'] ?? '') === 'active' || !empty($existing['email_verified_at']))) {
        json_error('Este correo ya tiene una cuenta verificada. Inicia sesion o solicita un codigo por correo.');
    }

    $commune = surteados_resolve_commune($pdo, $buyerCommuneId, $buyerComuna);
    $username = substr(preg_replace('/[^a-zA-Z0-9._-]/', '_', explode('@', $email)[0]), 0, 24);
    if (strlen($username) < 3) $username = 'user';
    $baseUsername = $username;
    $suffix = 1;
    while (true) {
        $stmt = $pdo->prepare('SELECT id FROM customer_users WHERE username = ? AND email <> ? LIMIT 1');
        $stmt->execute([$username, $email]);
        if (!$stmt->fetch()) break;
        $username = substr($baseUsername, 0, 24) . $suffix++;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $ins = $pdo->prepare(
        "INSERT INTO customer_users (username, email, full_name, phone, address, commune_id, comuna, rut, status, email_verified_at, password)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NULL, ?)
         ON DUPLICATE KEY UPDATE
           username = VALUES(username),
           full_name = VALUES(full_name),
           phone = VALUES(phone),
           address = VALUES(address),
           commune_id = VALUES(commune_id),
           comuna = VALUES(comuna),
           rut = VALUES(rut),
           status = 'pending',
           email_verified_at = NULL"
    );
    $ins->execute([$username, $email, $fullName, $phone, $address, $commune['id'], $commune['name'], $rut, $hash]);

    unset($_SESSION['customer_register_captcha']);
    $pdo->prepare('DELETE FROM customer_email_verifications WHERE email = ? AND (expires_at < NOW() OR used_at IS NOT NULL)')->execute([$email]);

    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $codeHash = password_hash($code, PASSWORD_DEFAULT);
    $pdo->prepare('INSERT INTO customer_email_verifications (email, code_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))')
        ->execute([$email, $codeHash]);

    $sent = send_account_verification_email($pdo, $email, $code);
    $resp = [
        'registered' => true,
        'pendingVerification' => true,
        'email' => $email,
        'sent' => $sent,
        'message' => $sent
            ? 'Te enviamos un codigo para verificar tu cuenta.'
            : 'La cuenta quedo pendiente, pero no se pudo enviar el correo de verificacion. Revisa la configuracion SMTP.',
    ];
    if (is_local_dev()) {
        $resp['dev_code'] = $code;
    }
    json_ok($resp);
}

if ($action === 'verify_register') {
    $email = strtolower(trim((string)($b['email'] ?? '')));
    $code = trim((string)($b['code'] ?? ''));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) json_error('Correo invalido');
    if (!preg_match('/^\d{6}$/', $code)) json_error('Codigo invalido');

    $stmt = $pdo->prepare(
        'SELECT * FROM customer_email_verifications
          WHERE email = ? AND used_at IS NULL AND expires_at >= NOW()
          ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([$email]);
    $row = $stmt->fetch();
    if (!$row) json_error('Codigo vencido o no encontrado', 401);
    if ((int)$row['attempts'] >= 5) json_error('Demasiados intentos. Solicita un nuevo registro.', 429);

    if (!password_verify($code, $row['code_hash'])) {
        $pdo->prepare('UPDATE customer_email_verifications SET attempts = attempts + 1 WHERE id = ?')->execute([$row['id']]);
        json_error('Codigo incorrecto', 401);
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE customer_email_verifications SET used_at = NOW() WHERE id = ?')->execute([$row['id']]);
        $pdo->prepare("UPDATE customer_users SET status = 'active', email_verified_at = NOW() WHERE email = ?")->execute([$email]);
        $uStmt = $pdo->prepare('SELECT id, username, email, status FROM customer_users WHERE email = ? LIMIT 1');
        $uStmt->execute([$email]);
        $user = $uStmt->fetch();
        if (!$user || $user['status'] !== 'active') {
            throw new RuntimeException('No se pudo activar la cuenta');
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        json_error('No se pudo activar la cuenta. Intentalo nuevamente.', 500);
    }

    set_client_auth($user);
    json_ok(['authenticated' => true, 'email' => $user['email'], 'username' => $user['username']]);
}

if ($action === 'login') {
    $identifier = trim((string)($b['identifier'] ?? ''));
    $password   = (string)($b['password'] ?? '');

    if ($identifier === '' || $password === '') json_error('Completa usuario/correo y contrasena');

    $stmt = $pdo->prepare('SELECT id, username, email, password, status, email_verified_at FROM customer_users WHERE username = ? OR email = ? LIMIT 1');
    $stmt->execute([$identifier, strtolower($identifier)]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        json_error('Credenciales invalidas', 401);
    }
    if (($user['status'] ?? 'pending') !== 'active' || empty($user['email_verified_at'])) {
        json_error('Debes verificar tu correo antes de iniciar sesion.', 403);
    }

    set_client_auth($user);
    json_ok(['authenticated' => true, 'email' => $user['email'], 'username' => $user['username']]);
}

if ($action === 'logout') {
    clear_client_auth();
    json_ok(['authenticated' => false]);
}

json_error('Acción no válida', 400);
