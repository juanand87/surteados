<?php
/**
 * SURTEADOS - Email helper
 * Sends HTML email only through SMTP using PHPMailer.
 */
function surteados_send_email(
    array $cfg,
    string $toEmail,
    string $toName,
    string $subject,
    string $htmlBody
): bool {
    $GLOBALS['surteados_last_email_error'] = '';
    $fromEmail = $cfg['smtp_from_email'] ?: 'noreply@surteados.cl';
    $fromName = $cfg['smtp_from_name'] ?: ($cfg['site_name'] ?? 'Surteados');
    $smtpHost = surteados_normalize_smtp_host($cfg['smtp_host'] ?? '');

    if ($smtpHost === '') {
        surteados_set_email_error('SMTP host is empty');
        return false;
    }
    if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        surteados_set_email_error('Invalid SMTP from email');
        return false;
    }
    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        surteados_set_email_error('Invalid recipient email');
        return false;
    }
    if (!surteados_load_phpmailer()) {
        surteados_set_email_error('PHPMailer is not available. Upload api/lib/phpmailer/ or vendor/, or run composer install.');
        return false;
    }

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = $smtpHost;
        $mail->SMTPAuth = true;
        $mail->Username = $cfg['smtp_user'] ?? '';
        $mail->Password = $cfg['smtp_pass'] ?? '';
        $mail->Timeout = 20;

        $enc = strtolower(trim($cfg['smtp_encryption'] ?? 'tls'));
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

        $mail->CharSet = 'UTF-8';
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = trim(strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $htmlBody)));
        $mail->send();
        return true;
    } catch (Throwable $e) {
        surteados_set_email_error($mail->ErrorInfo ?: $e->getMessage());
        return false;
    }
}

function surteados_set_email_error(string $message): void
{
    $message = trim($message);
    $GLOBALS['surteados_last_email_error'] = $message;
    error_log('Surteados PHPMailer SMTP error: ' . $message);
}

function surteados_last_email_error(): string
{
    return (string)($GLOBALS['surteados_last_email_error'] ?? '');
}

function surteados_load_phpmailer(): bool
{
    if (class_exists('PHPMailer\\PHPMailer\\PHPMailer', false)) {
        return true;
    }

    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (file_exists($autoload)) {
        require_once $autoload;
    }
    if (class_exists('PHPMailer\\PHPMailer\\PHPMailer', false)) {
        return true;
    }

    $bases = [
        __DIR__ . '/../vendor/phpmailer/phpmailer/src',
        __DIR__ . '/lib/phpmailer/src',
    ];
    foreach ($bases as $base) {
        $files = [
            $base . '/Exception.php',
            $base . '/SMTP.php',
            $base . '/PHPMailer.php',
        ];
        $allPresent = true;
        foreach ($files as $file) {
            if (!file_exists($file)) {
                $allPresent = false;
                break;
            }
        }
        if (!$allPresent) {
            continue;
        }
        foreach ($files as $file) {
            require_once $file;
        }
        if (class_exists('PHPMailer\\PHPMailer\\PHPMailer', false)) {
            return true;
        }
    }
    return false;
}

function surteados_normalize_smtp_host(string $host): string
{
    $host = trim($host);
    $host = preg_replace('/^\s*(server|servidor|host|smtp)\s*:\s*/i', '', $host);
    $host = preg_replace('~^(ssl|tls|tcp)://~i', '', $host);
    $host = preg_replace('/\s+.*/', '', $host);
    $host = trim($host, " \t\n\r\0\x0B:/");
    if (strpos($host, ':') !== false && substr_count($host, ':') === 1) {
        $host = explode(':', $host, 2)[0];
    }
    return $host;
}
