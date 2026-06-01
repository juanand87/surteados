<?php
/**
 * SURTEADOS — Email helper
 * Sends HTML email via SMTP (PHPMailer) when smtp_host is configured,
 * falls back to native mail() otherwise.
 *
 * @param array  $cfg       Key→value settings array (smtp_host, smtp_port,
 *                          smtp_user, smtp_pass, smtp_encryption,
 *                          smtp_from_email, smtp_from_name, site_name)
 * @param string $toEmail   Recipient email address
 * @param string $toName    Recipient display name
 * @param string $subject   Plain-text email subject (UTF-8)
 * @param string $htmlBody  Full HTML body
 */
function surteados_send_email(
    array  $cfg,
    string $toEmail,
    string $toName,
    string $subject,
    string $htmlBody
): bool {
    $fromEmail = $cfg['smtp_from_email'] ?: 'noreply@surteados.cl';
    $fromName  = $cfg['smtp_from_name']  ?: ($cfg['site_name'] ?? 'Surteados');
    $smtpHost  = trim($cfg['smtp_host']  ?? '');

    if ($smtpHost) {
        // Load PHPMailer autoloader once
        if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer', false)) {
            $autoload = __DIR__ . '/../vendor/autoload.php';
            if (file_exists($autoload)) require_once $autoload;
        }

        if (class_exists('PHPMailer\\PHPMailer\\PHPMailer', false)) {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host     = $smtpHost;
                $mail->SMTPAuth = true;
                $mail->Username = $cfg['smtp_user'] ?? '';
                $mail->Password = $cfg['smtp_pass'] ?? '';
                $enc = strtolower(trim($cfg['smtp_encryption'] ?? 'tls'));
                if ($enc === 'ssl') {
                    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
                    $mail->Port       = (int)($cfg['smtp_port'] ?: 465);
                } else {
                    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = (int)($cfg['smtp_port'] ?: 587);
                }
                $mail->CharSet = 'UTF-8';
                $mail->setFrom($fromEmail, $fromName);
                $mail->addAddress($toEmail, $toName);
                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body    = $htmlBody;
                $mail->send();
                return true;
            } catch (Throwable) {
                // SMTP failed — fall through to native mail()
            }
        }
    }

    return _surteados_native_mail($fromEmail, $fromName, $toEmail, $subject, $htmlBody);
}

function _surteados_native_mail(
    string $fromEmail,
    string $fromName,
    string $toEmail,
    string $subject,
    string $htmlBody
): bool {
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: {$fromName} <{$fromEmail}>\r\n";
    $headers .= "Reply-To: {$fromEmail}\r\n";
    $headers .= "X-Mailer: Surteados/1.0\r\n";
    $encoded  = "=?UTF-8?B?" . base64_encode($subject) . "?=";
    return (bool)@mail($toEmail, $encoded, $htmlBody, $headers);
}
