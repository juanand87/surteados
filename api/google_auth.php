<?php
/** SURTEADOS - Google OAuth for customers */
require __DIR__ . '/config.php';

$action = trim((string)($_GET['action'] ?? 'start'));

function google_settings(PDO $pdo): array {
    $keys = ['google_client_id', 'google_client_secret', 'google_redirect_uri', 'site_url'];
    $ph = implode(',', array_fill(0, count($keys), '?'));
    $stmt = $pdo->prepare("SELECT `key`, `value` FROM settings WHERE `key` IN ($ph)");
    $stmt->execute($keys);
    $cfg = [];
    foreach ($stmt->fetchAll() as $row) {
        $cfg[$row['key']] = trim((string)$row['value']);
    }
    return $cfg;
}

function google_redirect_uri(array $cfg): string {
    if (!empty($cfg['google_redirect_uri'])) {
        return $cfg['google_redirect_uri'];
    }
    return rtrim(BASE_URL, '/') . '/api/google_auth.php?action=callback';
}

function google_http_post(string $url, array $data): array {
    $body = http_build_query($data);
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT => 20,
        ]);
        $raw = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    } else {
        $raw = file_get_contents($url, false, stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $body,
                'timeout' => 20,
            ],
        ]));
        $code = 200;
    }
    $json = json_decode((string)$raw, true);
    return ['code' => $code, 'json' => is_array($json) ? $json : []];
}

function google_http_get(string $url, string $accessToken): array {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken],
            CURLOPT_TIMEOUT => 20,
        ]);
        $raw = curl_exec($ch);
        curl_close($ch);
    } else {
        $raw = file_get_contents($url, false, stream_context_create([
            'http' => [
                'header' => "Authorization: Bearer {$accessToken}\r\n",
                'timeout' => 20,
            ],
        ]));
    }
    $json = json_decode((string)$raw, true);
    return is_array($json) ? $json : [];
}

function google_customer_schema(PDO $pdo): void {
    $cols = $pdo->query("SHOW COLUMNS FROM customer_users")->fetchAll();
    $existing = array_column($cols, 'Field');
    $adds = [
        'full_name' => "ALTER TABLE customer_users ADD COLUMN full_name VARCHAR(180) NULL AFTER email",
        'status' => "ALTER TABLE customer_users ADD COLUMN status ENUM('pending','active','blocked') NOT NULL DEFAULT 'pending' AFTER rut",
        'email_verified_at' => "ALTER TABLE customer_users ADD COLUMN email_verified_at DATETIME NULL AFTER status",
        'google_id' => "ALTER TABLE customer_users ADD COLUMN google_id VARCHAR(120) NULL AFTER email_verified_at",
        'auth_provider' => "ALTER TABLE customer_users ADD COLUMN auth_provider VARCHAR(30) NOT NULL DEFAULT 'email' AFTER google_id",
    ];
    foreach ($adds as $field => $sql) {
        if (!in_array($field, $existing, true)) {
            $pdo->exec($sql);
        }
    }
}

function google_unique_username(PDO $pdo, string $email): string {
    $base = substr(preg_replace('/[^a-zA-Z0-9._-]/', '_', explode('@', $email)[0]), 0, 24);
    if (strlen($base) < 3) $base = 'google';
    $username = $base;
    $suffix = 1;
    while (true) {
        $stmt = $pdo->prepare('SELECT id FROM customer_users WHERE username = ? AND email <> ? LIMIT 1');
        $stmt->execute([$username, $email]);
        if (!$stmt->fetch()) return $username;
        $username = substr($base, 0, 24) . $suffix++;
    }
}

function google_fail(string $message): never {
    $url = rtrim(BASE_URL, '/') . '/mis-imagenes.php?auth_error=' . rawurlencode($message) . '#login';
    header('Location: ' . $url);
    exit;
}

$pdo = db();
$cfg = google_settings($pdo);
$clientId = $cfg['google_client_id'] ?? '';
$clientSecret = $cfg['google_client_secret'] ?? '';
$redirectUri = google_redirect_uri($cfg);

if ($clientId === '' || $clientSecret === '') {
    google_fail('Google Login no esta configurado.');
}

client_session_start();

if ($action === 'start') {
    $state = bin2hex(random_bytes(16));
    $_SESSION['google_oauth_state'] = $state;
    $params = [
        'client_id' => $clientId,
        'redirect_uri' => $redirectUri,
        'response_type' => 'code',
        'scope' => 'openid email profile',
        'state' => $state,
        'prompt' => 'select_account',
    ];
    header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params));
    exit;
}

if ($action !== 'callback') {
    google_fail('Accion Google no valida.');
}

$state = (string)($_GET['state'] ?? '');
$code = (string)($_GET['code'] ?? '');
if ($state === '' || $code === '' || !hash_equals((string)($_SESSION['google_oauth_state'] ?? ''), $state)) {
    google_fail('No se pudo validar la respuesta de Google.');
}
unset($_SESSION['google_oauth_state']);

$token = google_http_post('https://oauth2.googleapis.com/token', [
    'code' => $code,
    'client_id' => $clientId,
    'client_secret' => $clientSecret,
    'redirect_uri' => $redirectUri,
    'grant_type' => 'authorization_code',
]);
$accessToken = (string)($token['json']['access_token'] ?? '');
if ($accessToken === '') {
    google_fail('Google no entrego un token valido.');
}

$profile = google_http_get('https://openidconnect.googleapis.com/v1/userinfo', $accessToken);
$email = strtolower(trim((string)($profile['email'] ?? '')));
$googleId = trim((string)($profile['sub'] ?? ''));
$name = trim((string)($profile['name'] ?? ''));
$verified = (bool)($profile['email_verified'] ?? false);
if ($email === '' || $googleId === '' || !$verified || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    google_fail('Google no entrego un correo verificado.');
}

google_customer_schema($pdo);
$username = google_unique_username($pdo, $email);
$password = password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT);

$stmt = $pdo->prepare(
    "INSERT INTO customer_users (username, email, full_name, google_id, auth_provider, status, email_verified_at, password)
     VALUES (?, ?, ?, ?, 'google', 'active', NOW(), ?)
     ON DUPLICATE KEY UPDATE
       full_name = COALESCE(NULLIF(VALUES(full_name), ''), full_name),
       google_id = VALUES(google_id),
       auth_provider = 'google',
       status = 'active',
       email_verified_at = COALESCE(email_verified_at, NOW()),
       password = password"
);
$stmt->execute([$username, $email, $name, $googleId, $password]);

$userStmt = $pdo->prepare('SELECT id, username, email FROM customer_users WHERE email = ? LIMIT 1');
$userStmt->execute([$email]);
$user = $userStmt->fetch();
if (!$user) {
    google_fail('No se pudo crear la sesion con Google.');
}

session_regenerate_id(true);
$_SESSION['client_auth_email'] = $user['email'];
$_SESSION['client_user_id'] = (int)$user['id'];
$_SESSION['client_username'] = (string)$user['username'];

header('Location: ' . rtrim(BASE_URL, '/') . '/mis-imagenes.php');
exit;
