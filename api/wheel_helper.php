<?php
require_once __DIR__ . '/email_helper.php';

function surteados_ensure_wheel_tables(PDO $pdo): void
{
    static $done = false;
    if ($done) return;

    $pdo->exec("CREATE TABLE IF NOT EXISTS wheel_prizes (
      id VARCHAR(25) PRIMARY KEY,
      title VARCHAR(180) NOT NULL,
      description TEXT NULL,
      prize_type VARCHAR(30) NOT NULL DEFAULT 'percent',
      discount_type VARCHAR(20) NOT NULL DEFAULT 'percent',
      discount_value INT NOT NULL DEFAULT 0,
      probability DECIMAL(6,2) NOT NULL DEFAULT 0,
      code_prefix VARCHAR(20) DEFAULT 'RULETA',
      active TINYINT(1) NOT NULL DEFAULT 1,
      display_order INT NOT NULL DEFAULT 0,
      color1 VARCHAR(20) DEFAULT '#7c3aed',
      color2 VARCHAR(20) DEFAULT '#f59e0b',
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      INDEX idx_active_order (active, display_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS discount_codes (
      id VARCHAR(25) PRIMARY KEY,
      code VARCHAR(40) NOT NULL UNIQUE,
      source VARCHAR(30) NOT NULL DEFAULT 'wheel',
      prize_id VARCHAR(25) NULL,
      email VARCHAR(180) NULL,
      discount_type VARCHAR(20) NOT NULL DEFAULT 'none',
      discount_value INT NOT NULL DEFAULT 0,
      status VARCHAR(20) NOT NULL DEFAULT 'active',
      used_order_id VARCHAR(100) NULL,
      used_at DATETIME NULL,
      expires_at DATETIME NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_discount_status (status),
      INDEX idx_discount_email (email),
      INDEX idx_discount_prize (prize_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS wheel_spins (
      id VARCHAR(25) PRIMARY KEY,
      email VARCHAR(180) NOT NULL,
      prize_id VARCHAR(25) NULL,
      discount_code_id VARCHAR(25) NULL,
      ip_hash CHAR(64) NULL,
      user_agent VARCHAR(255) NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      UNIQUE KEY uq_wheel_email (email),
      INDEX idx_wheel_prize (prize_id),
      INDEX idx_wheel_created (created_at),
      INDEX idx_wheel_ip (ip_hash)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $cols = $pdo->query("SHOW COLUMNS FROM tickets")->fetchAll();
    $existing = array_column($cols, 'Field');
    if (!in_array('original_amount', $existing, true)) {
        $pdo->exec("ALTER TABLE tickets ADD COLUMN original_amount INT DEFAULT NULL AFTER amount");
    }
    if (!in_array('discount_code', $existing, true)) {
        $pdo->exec("ALTER TABLE tickets ADD COLUMN discount_code VARCHAR(40) NULL AFTER original_amount");
        $pdo->exec("ALTER TABLE tickets ADD INDEX idx_discount_code (discount_code)");
    }
    if (!in_array('discount_amount', $existing, true)) {
        $pdo->exec("ALTER TABLE tickets ADD COLUMN discount_amount INT NOT NULL DEFAULT 0 AFTER discount_code");
    }

    $pdo->prepare("INSERT INTO settings (`key`,`value`) VALUES ('wheel_enabled','0') ON DUPLICATE KEY UPDATE `key`=`key`")->execute();
    $done = true;
}

function surteados_wheel_email(string $email): string
{
    return mb_strtolower(trim($email));
}

function surteados_request_ip(): string
{
    $candidates = [
        $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '',
        $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
        $_SERVER['REMOTE_ADDR'] ?? '',
    ];
    foreach ($candidates as $candidate) {
        $ip = trim(explode(',', (string)$candidate)[0]);
        if ($ip !== '') return $ip;
    }
    return 'unknown';
}

function surteados_wheel_ip_hash(): string
{
    return hash('sha256', surteados_request_ip() . '|' . (defined('DB_NAME') ? DB_NAME : 'surteados'));
}

function surteados_wheel_public_prize(array $p): array
{
    return [
        'id' => (string)$p['id'],
        'title' => (string)$p['title'],
        'description' => (string)($p['description'] ?? ''),
        'prizeType' => (string)($p['prize_type'] ?? 'custom'),
        'discountType' => (string)($p['discount_type'] ?? 'none'),
        'discountValue' => (int)($p['discount_value'] ?? 0),
        'probability' => (float)($p['probability'] ?? 0),
        'active' => !empty($p['active']),
        'displayOrder' => (int)($p['display_order'] ?? 0),
        'color1' => (string)($p['color1'] ?? '#7c3aed'),
        'color2' => (string)($p['color2'] ?? '#f59e0b'),
    ];
}

function surteados_wheel_probability_total(array $prizes): float
{
    $total = 0.0;
    foreach ($prizes as $p) {
        if (!empty($p['active'])) $total += (float)($p['probability'] ?? 0);
    }
    return round($total, 2);
}

function surteados_wheel_pick_prize(array $prizes): array
{
    $weighted = [];
    $total = 0;
    foreach ($prizes as $p) {
        if (empty($p['active'])) continue;
        $weight = (int)round(((float)($p['probability'] ?? 0)) * 100);
        if ($weight <= 0) continue;
        $total += $weight;
        $weighted[] = ['limit' => $total, 'prize' => $p];
    }
    if ($total <= 0) {
        throw new RuntimeException('La ruleta no tiene premios con probabilidad disponible.');
    }
    $roll = random_int(1, $total);
    foreach ($weighted as $entry) {
        if ($roll <= $entry['limit']) return $entry['prize'];
    }
    return $weighted[count($weighted) - 1]['prize'];
}

function surteados_generate_discount_code(PDO $pdo, string $prefix = 'RULETA'): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $length = 8;

    for ($i = 0; $i < 50; $i++) {
        $code = '';
        for ($j = 0; $j < $length; $j++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        $stmt = $pdo->prepare('SELECT 1 FROM discount_codes WHERE code = ? LIMIT 1');
        $stmt->execute([$code]);
        if (!$stmt->fetchColumn()) return $code;
    }

    throw new RuntimeException('No se pudo generar un código único.');
}

function surteados_discount_from_total(array $codeRow, int $total): int
{
    $total = max(0, $total);
    $type = (string)($codeRow['discount_type'] ?? 'none');
    $value = (int)($codeRow['discount_value'] ?? 0);
    if ($total <= 0 || $value <= 0) return 0;
    if ($type === 'percent') {
        return min($total, (int)round($total * min(100, $value) / 100));
    }
    if ($type === 'fixed') {
        return min($total, $value);
    }
    return 0;
}

function surteados_find_valid_discount(PDO $pdo, string $code, string $email, int $total): array
{
    surteados_ensure_wheel_tables($pdo);
    $code = strtoupper(trim($code));
    if ($code === '') return ['valid' => false, 'error' => 'Ingresa un código de descuento.'];
    $email = surteados_wheel_email($email);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['valid' => false, 'error' => 'Correo inválido para validar el código.'];

    $stmt = $pdo->prepare('SELECT * FROM discount_codes WHERE code = ? LIMIT 1');
    $stmt->execute([$code]);
    $row = $stmt->fetch();
    if (!$row) return ['valid' => false, 'error' => 'Código no encontrado.'];
    if (($row['status'] ?? '') !== 'active') return ['valid' => false, 'error' => 'Este código ya fue usado o no está disponible.'];
    if (!empty($row['expires_at']) && strtotime((string)$row['expires_at']) < time()) return ['valid' => false, 'error' => 'Este código expiró.'];
    if (!empty($row['email']) && surteados_wheel_email((string)$row['email']) !== $email) {
        return ['valid' => false, 'error' => 'Este código pertenece a otro correo.'];
    }

    $discount = surteados_discount_from_total($row, $total);
    if ($discount <= 0) return ['valid' => false, 'error' => 'El código no genera descuento para esta compra.'];

    return [
        'valid' => true,
        'code' => $code,
        'discountType' => $row['discount_type'],
        'discountValue' => (int)$row['discount_value'],
        'discountAmount' => $discount,
        'totalBefore' => $total,
        'totalAfter' => max(0, $total - $discount),
    ];
}

function surteados_reserve_discount_code(PDO $pdo, string $code, string $email, string $orderId): bool
{
    $stmt = $pdo->prepare("UPDATE discount_codes SET status='reserved', used_order_id=? WHERE code=? AND status='active' AND (email IS NULL OR email='' OR LOWER(email)=LOWER(?))");
    $stmt->execute([$orderId, strtoupper(trim($code)), surteados_wheel_email($email)]);
    return $stmt->rowCount() === 1;
}

function surteados_release_discount_code(PDO $pdo, string $orderId): void
{
    $pdo->prepare("UPDATE discount_codes SET status='active', used_order_id=NULL WHERE status='reserved' AND used_order_id=?")->execute([$orderId]);
}

function surteados_mark_discount_used(PDO $pdo, string $code, string $orderId): void
{
    $code = strtoupper(trim($code));
    if ($code === '') return;
    $pdo->prepare("UPDATE discount_codes SET status='used', used_order_id=?, used_at=NOW() WHERE code=? AND status IN ('active','reserved')")->execute([$orderId, $code]);
}

function surteados_send_wheel_email(PDO $pdo, string $email, array $prize, string $code = ''): bool
{
    $cfgStmt = $pdo->query("SELECT `key`, `value` FROM settings WHERE `key` IN ('site_name','site_url','smtp_from_email','smtp_from_name','smtp_host','smtp_port','smtp_user','smtp_pass','smtp_encryption')");
    $cfg = [];
    foreach ($cfgStmt->fetchAll() as $row) $cfg[$row['key']] = $row['value'];

    $siteName = $cfg['site_name'] ?? 'Surteados';
    $siteUrl = rtrim(trim((string)($cfg['site_url'] ?? BASE_URL)), '/');
    $title = htmlspecialchars((string)$prize['title'], ENT_QUOTES, 'UTF-8');
    $desc = htmlspecialchars((string)($prize['description'] ?? ''), ENT_QUOTES, 'UTF-8');
    $codeSafe = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
    $siteSafe = htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8');
    $logo = 'https://www.surteados.cl/assets/uploads/logo_79fc52eace063168.png';

    $codeBlock = $code !== ''
        ? "<div style='background:#fff;border:2px dashed #7c3aed;border-radius:14px;padding:16px 18px;text-align:center;margin:20px 0;'><div style='font-size:12px;color:#64748b;font-weight:700;text-transform:uppercase;letter-spacing:1px;'>Tu código de descuento</div><div style='font-size:26px;color:#1e1450;font-weight:900;letter-spacing:1px;margin-top:6px;'>{$codeSafe}</div><p style='font-size:12px;color:#64748b;margin:8px 0 0;'>Guárdalo. Es único y solo puede usarse una vez con este correo.</p></div>"
        : "<p style='color:#a0a0b0;font-size:14px;line-height:1.6;'>Este premio no genera código de descuento automático. Guarda este correo como comprobante.</p>";

    $html = "<!DOCTYPE html><html lang='es'><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1'></head><body style='margin:0;padding:0;background:#0d0520;font-family:Arial,sans-serif;color:#e2e8f0;'><div style='max-width:560px;margin:32px auto;background:#140b30;border:1px solid #2d1f5e;border-radius:18px;overflow:hidden;'><div style='background:linear-gradient(135deg,#7c3aed,#db2777,#f59e0b);padding:28px;text-align:center;'><img src='{$logo}' alt='Surteados' style='width:150px;max-width:70%;height:auto;margin:0 auto 12px;display:block;'><h1 style='margin:0;color:#fff;font-size:23px;font-weight:900;'>Premio de ruleta</h1><p style='margin:8px 0 0;color:rgba(255,255,255,.82);font-size:14px;'>{$siteSafe}</p></div><div style='padding:28px 32px;'><p style='margin:0 0 12px;font-size:16px;'>Ganaste: <strong style='color:#fff;'>{$title}</strong></p>" . ($desc !== '' ? "<p style='color:#a0a0b0;font-size:14px;line-height:1.6;margin:0 0 16px;'>{$desc}</p>" : '') . $codeBlock . "<div style='text-align:center;margin-top:24px;'><a href='{$siteUrl}' style='display:inline-block;background:linear-gradient(135deg,#7c3aed,#db2777);color:#fff;text-decoration:none;padding:13px 30px;border-radius:999px;font-weight:800;'>Ir a Surteados</a></div></div><div style='padding:14px 28px;border-top:1px solid #2d1f5e;text-align:center;font-size:11px;color:#6b7280;'>Correo automático de {$siteSafe}</div></div></body></html>";

    return surteados_send_email($cfg, $email, '', 'Tu premio de la ruleta Surteados', $html);
}