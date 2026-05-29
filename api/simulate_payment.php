<?php
/**
 * SURTEADOS — Simulated Payment (Demo only)
 * Generates real tickets as 'paid' and sends a confirmation email.
 * Method: POST  Content-Type: application/json
 * Body: { items: [{raffleId, packId}], buyerName, buyerEmail, buyerPhone }
 */
require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$b = body();

$items      = $b['items']      ?? [];
$buyerName  = trim($b['buyerName']  ?? '');
$buyerRut   = trim($b['buyerRut']   ?? '');
$buyerEmail = trim($b['buyerEmail'] ?? '');
$buyerPhone = trim($b['buyerPhone'] ?? '');
$buyerAddress = trim($b['buyerAddress'] ?? '');
$buyerComuna  = trim($b['buyerComuna']  ?? '');

if (!$items || !$buyerName || !$buyerEmail) {
    json_error('Datos incompletos: items, buyerName y buyerEmail son requeridos');
}
if (!$buyerAddress || !$buyerComuna) {
  json_error('Datos incompletos: buyerAddress y buyerComuna son requeridos');
}
if (!filter_var($buyerEmail, FILTER_VALIDATE_EMAIL)) {
    json_error('Email inválido');
}

$pdo = db();

// ── Generate unique ticket numbers for a raffle ────────────────────────────
function sim_genNumbers(PDO $pdo, string $raffleId, int $qty): array
{
    $stmt = $pdo->prepare(
        "SELECT ticket_numbers FROM tickets
          WHERE raffle_id = ? AND payment_status = 'paid' AND ticket_numbers IS NOT NULL"
    );
    $stmt->execute([$raffleId]);

    $existing = [];
    foreach ($stmt->fetchAll() as $row) {
        $nums     = json_decode($row['ticket_numbers'], true) ?? [];
        $existing = array_merge($existing, $nums);
    }
    $existingSet = array_flip($existing);

    $numbers  = [];
    $attempts = 0;
    while (count($numbers) < $qty && $attempts < 100000) {
        $num = str_pad(mt_rand(1, 99999), 6, '0', STR_PAD_LEFT);
        if (!isset($existingSet[$num])) {
            $numbers[]         = $num;
            $existingSet[$num] = true;
        }
        $attempts++;
    }
    return $numbers;
}

$orderId        = 'sim_' . bin2hex(random_bytes(6));
$createdTickets = [];
$total          = 0;

try {
    $pdo->beginTransaction();

    foreach ($items as $item) {
        $raffleId = $item['raffleId'] ?? '';
        $packId   = $item['packId']   ?? '';
        if (!$raffleId || !$packId) continue;

      $raffleStmt = $pdo->prepare('SELECT id, title, status, draw_date FROM raffles WHERE id = ?');
      $raffleStmt->execute([$raffleId]);
      $raffleRow = $raffleStmt->fetch();
      if (!$raffleRow || $raffleRow['status'] !== 'active') {
        continue;
      }
      if (raffle_sales_closed($raffleRow['draw_date'] ?? null)) {
        json_error(raffle_closed_sale_message($raffleRow['draw_date'] ?? null));
      }

        // Get pack info
        $packStmt = $pdo->prepare('SELECT * FROM raffle_packs WHERE id = ? AND raffle_id = ?');
        $packStmt->execute([$packId, $raffleId]);
        $pack = $packStmt->fetch();
        if (!$pack) continue;

        $qty     = (int)$pack['qty'];
        $amount  = (int)$pack['price'];
        $total  += $amount;
        $numbers = sim_genNumbers($pdo, $raffleId, $qty);

        $ticketId = generate_id('t');
        $pdo->prepare(
            "INSERT INTO tickets
             (id, raffle_id, buyer_name, buyer_rut, buyer_email, buyer_phone, buyer_address, buyer_comuna,
                pack_id, pack_label, amount, payment_method, payment_status,
                ticket_numbers, flow_order)
           VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
        )->execute([
          $ticketId, $raffleId, $buyerName, $buyerRut, $buyerEmail, $buyerPhone, $buyerAddress, $buyerComuna,
            $packId, $pack['label'], $amount, 'demo', 'paid',
            json_encode($numbers), $orderId,
        ]);

        $pdo->prepare('UPDATE raffles SET sold_tickets = sold_tickets + ? WHERE id = ?')
            ->execute([$qty, $raffleId]);

        // Get raffle title
        $raffle = ['title' => $raffleRow['title'] ?? $raffleId];

        $createdTickets[] = [
            'id'            => $ticketId,
            'raffleId'      => $raffleId,
            'raffleTitle'   => $raffle['title'] ?? $raffleId,
            'packLabel'     => $pack['label'],
            'ticketNumbers' => $numbers,
            'amount'        => $amount,
        ];
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    json_error('Error al generar tickets: ' . $e->getMessage(), 500);
}

if (!$createdTickets) {
    json_error('No se pudieron generar tickets. Verifica que los packs existan.');
}

// ── Send confirmation email ────────────────────────────────────────────────
$cfgStmt = $pdo->query(
    "SELECT `key`, `value` FROM settings
      WHERE `key` IN ('site_name','smtp_from_email','smtp_from_name','site_url')"
);
$cfg = [];
foreach ($cfgStmt->fetchAll() as $row) {
    $cfg[$row['key']] = $row['value'];
}

$siteName  = $cfg['site_name']       ?? 'Surteados';
$fromEmail = $cfg['smtp_from_email'] ?: 'noreply@surteados.cl';
$fromName  = $cfg['smtp_from_name']  ?: $siteName;
$siteUrl   = rtrim($cfg['site_url']  ?: BASE_URL, '/');
$ticketLabel  = $cfg['ticket_label'] ?? 'imagen';
$ticketLabelP = $cfg['ticket_label_plural'] ?? 'imagenes';

// Build table rows
$ticketRows = '';
foreach ($createdTickets as $t) {
    $nums        = implode(', ', array_map('htmlspecialchars', $t['ticketNumbers']));
    $title       = htmlspecialchars($t['raffleTitle']);
    $label       = htmlspecialchars($t['packLabel']);
    $ticketRows .= "
      <tr>
        <td style='padding:8px 12px;border-bottom:1px solid #2d1f5e;color:#e2e8f0;'>{$title}</td>
        <td style='padding:8px 12px;border-bottom:1px solid #2d1f5e;color:#e2e8f0;'>{$label}</td>
        <td style='padding:8px 12px;border-bottom:1px solid #2d1f5e;font-family:monospace;font-size:12px;color:#c4b5fd;'>{$nums}</td>
      </tr>";
}

$totalFormatted = '$' . number_format($total, 0, ',', '.');
$ticketsUrl     = $siteUrl . '/mis-tickets.php?email=' . rawurlencode($buyerEmail);
$buyerNameSafe  = htmlspecialchars($buyerName);
$buyerEmailSafe = htmlspecialchars($buyerEmail);
$ticketLabelPSafe = htmlspecialchars($ticketLabelP);
$ticketLabelPTitle = htmlspecialchars(ucfirst($ticketLabelP));

$htmlBody = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#0d0520;font-family:Arial,sans-serif;color:#e2e8f0;">
  <div style="max-width:580px;margin:32px auto;background:#140b30;border-radius:16px;overflow:hidden;border:1px solid #2d1f5e;">
    <div style="background:linear-gradient(135deg,#7c3aed,#db2777);padding:28px 32px;text-align:center;">
      <div style="font-size:36px;margin-bottom:8px;">🎉</div>
      <h1 style="margin:0;color:#fff;font-size:22px;font-weight:800;">¡Compra confirmada!</h1>
      <p style="margin:8px 0 0;color:rgba(255,255,255,.8);font-size:14px;">{$siteName}</p>
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
        <a href="{$ticketsUrl}" style="display:inline-block;background:linear-gradient(135deg,#7c3aed,#db2777);color:#fff;text-decoration:none;padding:13px 32px;border-radius:50px;font-weight:700;font-size:15px;letter-spacing:.3px;">🎫 Ver mis {$ticketLabelPSafe}</a>
      </div>

      <p style="font-size:12px;color:#6b7280;text-align:center;margin:0;">¡Buena suerte en el sorteo! 🍀</p>
    </div>
    <div style="padding:14px 32px;border-top:1px solid #2d1f5e;text-align:center;font-size:11px;color:#6b7280;">
      Correo automático de {$siteName} · No respondas este mensaje
    </div>
  </div>
</body>
</html>
HTML;

$headers  = "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/html; charset=UTF-8\r\n";
$headers .= "From: {$fromName} <{$fromEmail}>\r\n";
$headers .= "Reply-To: {$fromEmail}\r\n";
$headers .= "X-Mailer: Surteados/1.0\r\n";

$subject  = "=?UTF-8?B?" . base64_encode("🎟️ Tus {$ticketLabelP} de {$siteName} — Compra confirmada") . "?=";
$mailSent = @mail($buyerEmail, $subject, $htmlBody, $headers);

json_ok([
    'orderId'  => $orderId,
    'tickets'  => $createdTickets,
    'total'    => $total,
    'mailSent' => $mailSent,
]);
