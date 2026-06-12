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
if (file_exists($autoload)) {
    require_once $autoload;
}

$enc = strtolower(trim((string)($cfg['smtp_encryption'] ?? 'tls')));
$port = (int)($cfg['smtp_port'] ?? 0);
if ($port <= 0) {
    $port = $enc === 'ssl' ? 465 : ($enc === 'none' ? 25 : 587);
}

$subject = 'Prueba SMTP - ' . $siteName;
$siteNameSafe = htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8');
$htmlBody = "
      <div style='font-family:Arial,sans-serif;max-width:560px;margin:0 auto;padding:24px;background:#f8fafc;color:#111827;'>
        <h2 style='margin:0 0 12px;color:#7c3aed;'>Prueba SMTP correcta</h2>
        <p>Este correo confirma que la configuracion SMTP de <strong>{$siteNameSafe}</strong> puede enviar mensajes.</p>
        <p style='font-size:13px;color:#6b7280;'>Fecha de prueba: " . date('d/m/Y H:i:s') . "</p>
      </div>";
$altBody = 'Prueba SMTP correcta para ' . $siteName . ' - ' . date('d/m/Y H:i:s');

if (class_exists('PHPMailer\\PHPMailer\\PHPMailer', false)) {
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = $host;
        $mail->SMTPAuth = trim((string)($cfg['smtp_user'] ?? '')) !== '' || trim((string)($cfg['smtp_pass'] ?? '')) !== '';
        $mail->Username = (string)($cfg['smtp_user'] ?? '');
        $mail->Password = (string)($cfg['smtp_pass'] ?? '');
        $mail->Timeout = 15;

        if ($enc === 'ssl') {
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($enc === 'none') {
            $mail->SMTPSecure = false;
            $mail->SMTPAutoTLS = false;
        } else {
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        }
        $mail->Port = $port;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($testEmail);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = $altBody;
        $mail->send();
    } catch (Throwable $e) {
        $message = trim($mail->ErrorInfo ?: $e->getMessage());
        json_error('No se pudo enviar por SMTP: ' . $message, 500);
    }
} else {
    try {
        surteados_send_smtp_direct($cfg, $host, $port, $enc, $fromEmail, $fromName, $testEmail, $subject, $htmlBody, $altBody);
    } catch (Throwable $e) {
        json_error('No se pudo enviar por SMTP: ' . $e->getMessage(), 500);
    }
}

json_ok(['sent' => true, 'to' => $testEmail]);

function surteados_send_smtp_direct(
    array $cfg,
    string $host,
    int $port,
    string $enc,
    string $fromEmail,
    string $fromName,
    string $toEmail,
    string $subject,
    string $htmlBody,
    string $altBody
): void {
    $remote = ($enc === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
    $errno = 0;
    $errstr = '';
    $socket = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT);
    if (!$socket) {
        throw new RuntimeException("No se pudo conectar a {$host}:{$port}. {$errstr}");
    }
    stream_set_timeout($socket, 15);

    try {
        smtp_expect($socket, [220]);
        smtp_command($socket, 'EHLO ' . smtp_local_host(), [250]);

        if ($enc === 'tls') {
            smtp_command($socket, 'STARTTLS', [220]);
            $cryptoOk = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if ($cryptoOk !== true) {
                throw new RuntimeException('El servidor no acepto STARTTLS');
            }
            smtp_command($socket, 'EHLO ' . smtp_local_host(), [250]);
        }

        $user = (string)($cfg['smtp_user'] ?? '');
        $pass = (string)($cfg['smtp_pass'] ?? '');
        if ($user !== '' || $pass !== '') {
            smtp_command($socket, 'AUTH LOGIN', [334]);
            smtp_command($socket, base64_encode($user), [334]);
            smtp_command($socket, base64_encode($pass), [235]);
        }

        smtp_command($socket, 'MAIL FROM:<' . $fromEmail . '>', [250]);
        smtp_command($socket, 'RCPT TO:<' . $toEmail . '>', [250, 251]);
        smtp_command($socket, 'DATA', [354]);

        $boundary = 'surteados_' . bin2hex(random_bytes(8));
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'From: ' . smtp_header_name($fromName) . ' <' . $fromEmail . '>',
            'To: <' . $toEmail . '>',
            'Subject: ' . $encodedSubject,
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];
        $message = implode("\r\n", $headers) . "\r\n\r\n";
        $message .= "--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
        $message .= chunk_split(base64_encode($altBody));
        $message .= "\r\n--{$boundary}\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
        $message .= chunk_split(base64_encode($htmlBody));
        $message .= "\r\n--{$boundary}--\r\n";

        fwrite($socket, str_replace("\n.", "\n..", $message) . "\r\n.\r\n");
        smtp_expect($socket, [250]);
        smtp_command($socket, 'QUIT', [221]);
    } finally {
        fclose($socket);
    }
}

function smtp_command($socket, string $command, array $expected): string
{
    fwrite($socket, $command . "\r\n");
    return smtp_expect($socket, $expected);
}

function smtp_expect($socket, array $expected): string
{
    $response = '';
    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        if (strlen($line) >= 4 && $line[3] === ' ') {
            break;
        }
    }
    $code = (int)substr($response, 0, 3);
    if (!in_array($code, $expected, true)) {
        $clean = trim(preg_replace('/\s+/', ' ', $response));
        throw new RuntimeException($clean ?: 'Respuesta SMTP inesperada');
    }
    return $response;
}

function smtp_local_host(): string
{
    $host = $_SERVER['SERVER_NAME'] ?? 'localhost';
    return preg_replace('/[^a-zA-Z0-9.-]/', '', $host) ?: 'localhost';
}

function smtp_header_name(string $name): string
{
    $name = trim(str_replace(["\r", "\n"], '', $name));
    if ($name === '') return 'Surteados';
    return '=?UTF-8?B?' . base64_encode($name) . '?=';
}
