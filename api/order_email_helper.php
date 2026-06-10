<?php
require_once __DIR__ . '/email_helper.php';

function surteados_send_order_confirmation(PDO $pdo, string $orderId, string $buyerEmail = ''): bool
{
    $orderId = trim($orderId);
    if ($orderId === '') return false;

    $sentKey = 'order_mail_sent_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $orderId);
    $sentStmt = $pdo->prepare('SELECT `value` FROM settings WHERE `key` = ? LIMIT 1');
    $sentStmt->execute([$sentKey]);
    if ($sentStmt->fetchColumn()) return true;

    $params = [$orderId];
    $emailWhere = '';
    if ($buyerEmail !== '') {
        $emailWhere = ' AND t.buyer_email = ?';
        $params[] = $buyerEmail;
    }

    $stmt = $pdo->prepare(
        "SELECT t.*, r.title AS raffle_title
           FROM tickets t
           LEFT JOIN raffles r ON r.id = t.raffle_id
          WHERE t.flow_order = ?
            AND t.payment_status = 'paid'
            {$emailWhere}
          ORDER BY t.purchase_date ASC"
    );
    $stmt->execute($params);
    $tickets = $stmt->fetchAll();
    if (!$tickets) return false;

    $cfgStmt = $pdo->query(
        "SELECT `key`, `value` FROM settings
          WHERE `key` IN ('site_name','smtp_from_email','smtp_from_name','site_url',
                          'smtp_host','smtp_port','smtp_user','smtp_pass','smtp_encryption',
                          'ticket_label','ticket_label_plural')"
    );
    $cfg = [];
    foreach ($cfgStmt->fetchAll() as $row) {
        $cfg[$row['key']] = $row['value'];
    }

    $siteName = $cfg['site_name'] ?? 'Surteados';
    $siteUrl = surteados_order_site_url($cfg['site_url'] ?? '');
    $ticketLabelP = $cfg['ticket_label_plural'] ?? 'imagenes';
    $ticketLabelPSafe = htmlspecialchars($ticketLabelP, ENT_QUOTES, 'UTF-8');
    $ticketLabelPTitle = htmlspecialchars(ucfirst($ticketLabelP), ENT_QUOTES, 'UTF-8');

    $buyerName = (string)($tickets[0]['buyer_name'] ?? '');
    $buyerEmail = (string)($tickets[0]['buyer_email'] ?? $buyerEmail);
    if (!filter_var($buyerEmail, FILTER_VALIDATE_EMAIL)) return false;

    $ticketRows = '';
    $total = 0;
    foreach ($tickets as $ticket) {
        $numbers = json_decode($ticket['ticket_numbers'] ?? '[]', true) ?? [];
        $nums = implode(', ', array_map(fn($n) => htmlspecialchars((string)$n, ENT_QUOTES, 'UTF-8'), $numbers));
        $title = htmlspecialchars($ticket['raffle_title'] ?: 'Sorteo', ENT_QUOTES, 'UTF-8');
        $label = htmlspecialchars($ticket['pack_label'] ?? '', ENT_QUOTES, 'UTF-8');
        $total += (int)($ticket['amount'] ?? 0);
        $ticketRows .= "
          <tr>
            <td style='padding:8px 12px;border-bottom:1px solid #2d1f5e;color:#e2e8f0;'>{$title}</td>
            <td style='padding:8px 12px;border-bottom:1px solid #2d1f5e;color:#e2e8f0;'>{$label}</td>
            <td style='padding:8px 12px;border-bottom:1px solid #2d1f5e;font-family:monospace;font-size:12px;color:#c4b5fd;'>{$nums}</td>
          </tr>";
    }

    $totalFormatted = '$' . number_format($total, 0, ',', '.');
    $ticketsUrl = $siteUrl . '/api/ticket_pdf.php?orderId=' . rawurlencode($orderId) . '&email=' . rawurlencode($buyerEmail);
    $buyerNameSafe = htmlspecialchars($buyerName, ENT_QUOTES, 'UTF-8');
    $buyerEmailSafe = htmlspecialchars($buyerEmail, ENT_QUOTES, 'UTF-8');
    $siteNameSafe = htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8');

    $htmlBody = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#0d0520;font-family:Arial,sans-serif;color:#e2e8f0;">
  <div style="max-width:580px;margin:32px auto;background:#140b30;border-radius:16px;overflow:hidden;border:1px solid #2d1f5e;">
    <div style="background:linear-gradient(135deg,#7c3aed,#db2777);padding:28px 32px;text-align:center;">
      <div style="font-size:36px;margin-bottom:8px;">🎉</div>
      <h1 style="margin:0;color:#fff;font-size:22px;font-weight:800;">¡Compra confirmada!</h1>
      <p style="margin:8px 0 0;color:rgba(255,255,255,.8);font-size:14px;">{$siteNameSafe}</p>
    </div>
    <div style="padding:28px 32px;">
      <p style="margin:0 0 8px;font-size:16px;">Hola <strong style="color:#fff;">{$buyerNameSafe}</strong>,</p>
      <p style="margin:0 0 20px;color:#a0a0b0;font-size:14px;line-height:1.6;">Tus {$ticketLabelPSafe} han sido asignados exitosamente. Guarda este correo como comprobante de participación.</p>
      <table style="width:100%;border-collapse:collapse;font-size:13px;margin-bottom:20px;">
        <thead>
          <tr style="background:rgba(124,58,237,.25);">
            <th style="padding:8px 12px;text-align:left;color:#c4b5fd;font-weight:600;">Sorteo</th>
            <th style="padding:8px 12px;text-align:left;color:#c4b5fd;font-weight:600;">Pack</th>
            <th style="padding:8px 12px;text-align:left;color:#c4b5fd;font-weight:600;">Nº {$ticketLabelPTitle}</th>
          </tr>
        </thead>
        <tbody>{$ticketRows}</tbody>
      </table>
      <div style="background:rgba(124,58,237,.12);border:1px solid rgba(124,58,237,.3);border-radius:10px;padding:14px 18px;margin-bottom:24px;">
        <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
          <span style="color:#a0a0b0;font-size:13px;">💰 Total pagado:</span>
          <strong style="color:#f59e0b;font-size:15px;">{$totalFormatted}</strong>
        </div>
        <div style="display:flex;justify-content:space-between;">
          <span style="color:#a0a0b0;font-size:13px;">📧 Correo:</span>
          <span style="font-size:13px;">{$buyerEmailSafe}</span>
        </div>
      </div>
      <div style="text-align:center;margin-bottom:20px;">
        <a href="{$ticketsUrl}" style="display:inline-block;background:linear-gradient(135deg,#7c3aed,#db2777);color:#fff;text-decoration:none;padding:13px 32px;border-radius:50px;font-weight:700;font-size:15px;letter-spacing:.3px;">📄 Ver mis imágenes compradas</a>
      </div>
      <p style="font-size:12px;color:#6b7280;text-align:center;margin:0;">¡Buena suerte en el sorteo! 🍀</p>
    </div>
    <div style="padding:14px 32px;border-top:1px solid #2d1f5e;text-align:center;font-size:11px;color:#6b7280;">
      Correo automático de {$siteNameSafe} · No respondas este mensaje
    </div>
  </div>
</body>
</html>
HTML;

    $subject = "🎟️ Tus {$ticketLabelP} de {$siteName} — Compra confirmada";
    $sent = surteados_send_email($cfg, $buyerEmail, $buyerName, $subject, $htmlBody);
    if ($sent) {
        $mark = $pdo->prepare(
            "INSERT INTO settings (`key`,`value`) VALUES (?, '1')
             ON DUPLICATE KEY UPDATE `value` = '1'"
        );
        $mark->execute([$sentKey]);
    }
    return $sent;
}

function surteados_order_site_url(string $url): string
{
    $url = trim($url);
    if ($url === '') $url = BASE_URL;
    $url = preg_replace('~/(api/flow_callback\.php|pago-exitoso\.php)(/.*)?$~i', '', $url);
    $url = preg_replace('~/api/?$~i', '', $url);
    return rtrim($url, '/');
}
