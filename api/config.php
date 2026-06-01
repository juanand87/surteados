<?php
/**
 * SURTEADOS — API Config
 * DB connection, helpers, shared constants
 */

define('DB_HOST',   'localhost');
define('DB_USER',   'root');
define('DB_PASS',   '');
define('DB_NAME',   'surteados_db');
// Detectar BASE_URL automáticamente (localhost usa /surteados, producción en raíz)
if (isset($_SERVER['HTTP_HOST'])) {
    $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host = (string)$_SERVER['HTTP_HOST'];
    $isLocal = stripos($host, 'localhost') === 0 || stripos($host, '127.0.0.1') === 0;
    $basePath = $isLocal ? '/surteados' : '';
    define('BASE_URL', $scheme . '://' . $host . $basePath);
} else {
    define('BASE_URL', 'http://localhost/surteados');
}
define('SESSION_NAME', 'surteados_admin');
define('CLIENT_SESSION_NAME', 'surteados_client');

// ── PDO singleton ────────────────────────────────────────────────────────────
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',
                DB_USER, DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        } catch (PDOException $e) {
            json_error('DB error: ' . $e->getMessage(), 500);
        }
    }
    return $pdo;
}

// ── Response helpers ──────────────────────────────────────────────────────────
function json_ok(mixed $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_error(string $msg, int $code = 400): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Session auth guard ────────────────────────────────────────────────────────
function auth_required(): void {
    session_name(SESSION_NAME);
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['admin_id'])) {
        json_error('No autorizado', 401);
    }
}

function client_session_start(): void {
    session_name(CLIENT_SESSION_NAME);
    if (session_status() === PHP_SESSION_NONE) session_start();
}

function client_auth_email(): ?string {
    client_session_start();
    $email = trim((string)($_SESSION['client_auth_email'] ?? ''));
    return $email !== '' ? $email : null;
}

function client_auth_required(): string {
    $email = client_auth_email();
    if (!$email) json_error('No autorizado', 401);
    return $email;
}

// ── Read JSON body or POST ────────────────────────────────────────────────────
function body(): array {
    $raw = file_get_contents('php://input');
    $json = json_decode($raw, true);
    if ($json !== null) return $json;
    return $_POST ?: [];
}

// ── ID generator ──────────────────────────────────────────────────────────────
function generate_id(string $prefix = 'id'): string {
    return $prefix . substr(uniqid(), -8) . rand(10, 99);
}

function raffle_sales_closes_at(?string $drawDate): ?DateTimeImmutable {
    if (!$drawDate) return null;
    try {
        $draw = new DateTimeImmutable($drawDate);
    } catch (Throwable $e) {
        return null;
    }
    return $draw->sub(new DateInterval('PT24H'));
}

function raffle_sales_closed(?string $drawDate): bool {
    $closesAt = raffle_sales_closes_at($drawDate);
    if (!$closesAt) return false;
    return (new DateTimeImmutable('now')) >= $closesAt;
}

function raffle_closed_sale_message(?string $drawDate): string {
    if (!$drawDate) {
        return 'Se ha cerrado la compra de imagenes.';
    }
    try {
        $draw = new DateTimeImmutable($drawDate);
    } catch (Throwable $e) {
        return 'Se ha cerrado la compra de imagenes.';
    }

    $now = new DateTimeImmutable('now');
    $diff = $now->diff($draw);
    $parts = [];
    if ($diff->d > 0) $parts[] = $diff->d . 'd';
    if ($diff->h > 0) $parts[] = $diff->h . 'h';
    if ($diff->i > 0 && count($parts) < 2) $parts[] = $diff->i . 'm';
    if (!$parts) $parts[] = 'menos de 1 minuto';
    return 'Se ha cerrado la compra de imagenes, faltan ' . implode(' ', $parts) . ' para que puedas ganar.';
}

// ── Settings helper ───────────────────────────────────────────────────────────
function get_settings(array $keys = []): array {
    $pdo = db();
    if ($keys) {
        $ph   = implode(',', array_fill(0, count($keys), '?'));
        $stmt = $pdo->prepare("SELECT `key`,`value` FROM settings WHERE `key` IN ($ph)");
        $stmt->execute(array_values($keys));
    } else {
        $stmt = $pdo->query("SELECT `key`,`value` FROM settings");
    }
    $out = [];
    foreach ($stmt->fetchAll() as $r) $out[$r['key']] = $r['value'];
    return $out;
}

// ── CORS (same origin — keep for flexibility) ─────────────────────────────────
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
