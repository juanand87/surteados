<?php
/** SURTEADOS - SMTP test email through PHPMailer */
require __DIR__ . '/config.php';
require_once __DIR__ . '/email_helper.php';

auth_required();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$body = body();
$testEmail = trim((string)($body['test_email'] ?? ''));
if (!filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
    json_error('Ingresa un correo de prueba valido');
}

$keys = [
    'site_name',
    'smtp_host',
    'smtp_port',
    'smtp_user',
    'smtp_pass',
    'smtp_encryption',
    'smtp_from_email',
    'smtp_from_name',
];

$cfg = [];
$stmt = db()->query(
    "SELECT `key`, `value` FROM settings
      WHERE `key` IN ('site_name','smtp_host','smtp_port','smtp_user','smtp_pass',
                      'smtp_encryption','smtp_from_email','smtp_from_name')"
);
foreach ($stmt->fetchAll() as $row) {
    $cfg[$row['key']] = $row['value'];
}

foreach ($keys as $key) {
    if (array_key_exists($key, $body)) {
        $cfg[$key] = trim((string)$body[$key]);
    }
}

$siteName = $cfg['site_name'] ?? 'Surteados';
$siteNameSafe = htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8');
$htmlBody = "
  <div style='font-family:Arial,sans-serif;max-width:560px;margin:0 auto;padding:24px;background:#f8fafc;color:#111827;'>
    <h2 style='margin:0 0 12px;color:#7c3aed;'>Prueba SMTP correcta</h2>
    <p>Este correo confirma que la configuracion SMTP de <strong>{$siteNameSafe}</strong> puede enviar mensajes con PHPMailer.</p>
    <p style='font-size:13px;color:#6b7280;'>Fecha de prueba: " . date('d/m/Y H:i:s') . "</p>
  </div>";

$sent = surteados_send_email(
    $cfg,
    $testEmail,
    'Prueba SMTP',
    'Prueba SMTP - ' . $siteName,
    $htmlBody
);

if (!$sent) {
    $detail = function_exists('surteados_last_email_error') ? surteados_last_email_error() : '';
    $msg = 'No se pudo enviar por PHPMailer SMTP.';
    if ($detail !== '') $msg .= ' Detalle: ' . $detail;
    json_error($msg, 500);
}

json_ok(['sent' => true, 'to' => $testEmail]);
