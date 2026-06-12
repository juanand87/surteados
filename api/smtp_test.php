<?php
/** SURTEADOS - SMTP test email */
require __DIR__ . '/config.php';

auth_required();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
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
$host = trim((string)($cfg['smtp_host'] ?? ''));
$fromEmail = trim((string)($cfg['smtp_from_email'] ?? ''));
$fromName = trim((string)($cfg['smtp_from_name'] ?? ''));

if ($host === '') {
    json_error('Debes indicar el servidor SMTP');
}
if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
    json_error('Debes indicar un email remitente valido');
}
if ($fromName === '') {
    $fromName = $siteName ?: 'Surteados';
}

$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {
    json_error('No se encontro PHPMailer. Ejecuta composer install en el proyecto.', 500);
}
require_once $autoload;

if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer', false)) {
    json_error('PHPMailer no esta disponible en el proyecto.', 500);
}

$mail = new PHPMailer\PHPMailer\PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host = $host;
    $mail->SMTPAuth = trim((string)($cfg['smtp_user'] ?? '')) !== '' || trim((string)($cfg['smtp_pass'] ?? '')) !== '';
    $mail->Username = (string)($cfg['smtp_user'] ?? '');
    $mail->Password = (string)($cfg['smtp_pass'] ?? '');
    $mail->Timeout = 15;

    $enc = strtolower(trim((string)($cfg['smtp_encryption'] ?? 'tls')));
    if ($enc === 'ssl') {
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = (int)($cfg['smtp_port'] ?: 465);
    } elseif ($enc === 'none') {
        $mail->SMTPSecure = false;
        $mail->SMTPAutoTLS = false;
        $mail->Port = (int)($cfg['smtp_port'] ?: 25);
    } else {
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = (int)($cfg['smtp_port'] ?: 587);
    }

    $siteNameSafe = htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8');
    $mail->CharSet = 'UTF-8';
    $mail->setFrom($fromEmail, $fromName);
    $mail->addAddress($testEmail);
    $mail->isHTML(true);
    $mail->Subject = 'Prueba SMTP - ' . $siteName;
    $mail->Body = "
      <div style='font-family:Arial,sans-serif;max-width:560px;margin:0 auto;padding:24px;background:#f8fafc;color:#111827;'>
        <h2 style='margin:0 0 12px;color:#7c3aed;'>Prueba SMTP correcta</h2>
        <p>Este correo confirma que la configuracion SMTP de <strong>{$siteNameSafe}</strong> puede enviar mensajes.</p>
        <p style='font-size:13px;color:#6b7280;'>Fecha de prueba: " . date('d/m/Y H:i:s') . "</p>
      </div>";
    $mail->AltBody = 'Prueba SMTP correcta para ' . $siteName . ' - ' . date('d/m/Y H:i:s');
    $mail->send();
} catch (Throwable $e) {
    $message = trim($mail->ErrorInfo ?: $e->getMessage());
    json_error('No se pudo enviar por SMTP: ' . $message, 500);
}

json_ok(['sent' => true, 'to' => $testEmail]);
