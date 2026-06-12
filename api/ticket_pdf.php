<?php
/**
 * SURTEADOS - Printable purchased images / PDF view.
 * GET /api/ticket_pdf.php?orderId=xxx&email=xxx
 */
require __DIR__ . '/config.php';

$orderId = trim((string)($_GET['orderId'] ?? ''));
$email   = trim((string)($_GET['email'] ?? ''));

if ($orderId === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo '<p style="font-family:sans-serif;padding:2rem;">Parametros invalidos.</p>';
    exit;
}

$pdo = db();

$colStmt = $pdo->query('SHOW COLUMNS FROM tickets');
$ticketColumns = [];
foreach ($colStmt->fetchAll() as $col) {
    if (!empty($col['Field'])) $ticketColumns[$col['Field']] = true;
}

$dateParts = [];
if (isset($ticketColumns['paid_at'])) $dateParts[] = 't.paid_at';
if (isset($ticketColumns['purchase_date'])) $dateParts[] = 't.purchase_date';
if (isset($ticketColumns['created_at'])) $dateParts[] = 't.created_at';
$dateExpr = $dateParts ? ('COALESCE(' . implode(', ', $dateParts) . ')') : 'NULL';

$stmt = $pdo->prepare(
    "SELECT t.id, t.ticket_numbers, t.pack_label, t.amount,
            t.buyer_name, t.buyer_email,
            {$dateExpr} AS paid_date,
            r.title AS raffle_title, r.image_url AS raffle_image,
            r.draw_date
       FROM tickets t
       JOIN raffles r ON r.id = t.raffle_id
      WHERE t.flow_order = ?
        AND t.buyer_email = ?
        AND t.payment_status = 'paid'
      ORDER BY paid_date ASC"
);
$stmt->execute([$orderId, $email]);
$tickets = $stmt->fetchAll();

if (!$tickets) {
    http_response_code(404);
    echo '<p style="font-family:sans-serif;padding:2rem;">No se encontraron imagenes para este pedido.</p>';
    exit;
}

$cfgRows = $pdo->query(
    "SELECT `key`, `value` FROM settings WHERE `key` IN ('site_name','ticket_label','ticket_label_plural')"
)->fetchAll();
$cfg = [];
foreach ($cfgRows as $row) $cfg[$row['key']] = $row['value'];

$ticketItems = [];
foreach ($tickets as $ticket) {
    $numbers = json_decode($ticket['ticket_numbers'] ?? '[]', true) ?? [];
    $count = max(1, count($numbers));
    $unitAmount = $count > 0 ? (int)round((int)$ticket['amount'] / $count) : (int)$ticket['amount'];

    foreach ($numbers as $index => $number) {
        $ticketItems[] = [
            'number'       => (string)$number,
            'index'        => $index + 1,
            'count'        => $count,
            'amount'       => $unitAmount,
            'pack_label'   => $ticket['pack_label'] ?? '',
            'raffle_title' => $ticket['raffle_title'] ?? '',
            'raffle_image' => $ticket['raffle_image'] ?? '',
            'draw_date'    => $ticket['draw_date'] ?? null,
            'paid_date'    => $ticket['paid_date'] ?? null,
        ];
    }
}

$siteName = htmlspecialchars($cfg['site_name'] ?? 'Surteados', ENT_QUOTES, 'UTF-8');
$ticketLabelP = htmlspecialchars($cfg['ticket_label_plural'] ?? 'imagenes', ENT_QUOTES, 'UTF-8');
$buyerName = htmlspecialchars($tickets[0]['buyer_name'] ?? '', ENT_QUOTES, 'UTF-8');
$orderIdSafe = htmlspecialchars($orderId, ENT_QUOTES, 'UTF-8');
$emailSafe = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
$total = array_sum(array_column($tickets, 'amount'));
$totalFmt = '$' . number_format($total, 0, ',', '.');

header('Content-Type: text/html; charset=UTF-8');
header('X-Robots-Tag: noindex, nofollow');
?><!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Mis <?= $ticketLabelP ?> compradas - <?= $siteName ?></title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: Arial, Helvetica, sans-serif; background: #f0eaf8; color: #1a1a2e; padding: 1.5rem; }
    .page-header { text-align: center; margin-bottom: 1.8rem; padding: 1.6rem 2rem; background: linear-gradient(135deg, #7c3aed, #db2777); color: #fff; border-radius: 14px; }
    .page-header h1 { font-size: 1.5rem; margin-bottom: .4rem; }
    .page-header p { opacity: .88; font-size: .9rem; }
    .order-info { font-size: .75rem; opacity: .72; margin-top: .3rem; }
    .print-btn { display: flex; align-items: center; justify-content: center; gap: .5rem; margin: 0 auto 1.8rem; padding: .65rem 2.2rem; background: #7c3aed; color: #fff; border: none; border-radius: 50px; font-size: .95rem; font-weight: 700; cursor: pointer; box-shadow: 0 4px 12px rgba(124,58,237,.35); }
    .summary { max-width: 720px; margin: 0 auto 1.5rem; background: #fff; border-radius: 12px; padding: 1rem 1.4rem; border: 1px solid #ddd6fe; }
    .summary-row { display: flex; justify-content: space-between; gap: 1rem; font-size: .85rem; padding: .3rem 0; border-bottom: 1px solid #f3f4f6; }
    .summary-row:last-child { border-bottom: none; font-weight: 700; font-size: .95rem; }
    .tickets-wrap { max-width: 720px; margin: 0 auto; }
    .ticket-card { background: #fff; border-radius: 14px; padding: 1.4rem 1.5rem; margin-bottom: 1.4rem; border: 2px solid #7c3aed; box-shadow: 0 4px 18px rgba(124,58,237,.13); page-break-inside: avoid; break-inside: avoid; }
    .ticket-img { width: 100%; max-height: 250px; object-fit: cover; border-radius: 10px; margin-bottom: 1rem; }
    .ticket-title { font-size: 1.08rem; font-weight: 800; color: #5b21b6; margin-bottom: .35rem; }
    .ticket-meta { font-size: .78rem; color: #666; line-height: 1.7; margin-bottom: .9rem; }
    .ticket-meta strong { color: #333; }
    .numbers-label { font-size: .82rem; font-weight: 700; color: #374151; margin-bottom: .45rem; }
    .ticket-number-hero { background: linear-gradient(135deg, #7c3aed, #5b21b6); color: #fff; border-radius: 12px; padding: 1rem; text-align: center; font-family: "Courier New", monospace; font-size: 2rem; font-weight: 800; letter-spacing: .08em; margin: 1rem 0 .2rem; }
    .footer-note { text-align: center; color: #888; font-size: .72rem; margin-top: 1.5rem; padding-bottom: 1.5rem; }
    @media print {
      body { background: #fff; padding: .3rem; }
      .print-btn { display: none !important; }
      .page-header { border-radius: 0; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
      .ticket-card { box-shadow: none; border: 1px solid #ccc; page-break-inside: avoid; break-inside: avoid; }
      .ticket-number-hero { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
  </style>
</head>
<body>
  <div class="page-header">
    <div style="font-size:2.4rem;margin-bottom:.4rem;">🎟️</div>
    <h1>Compra exitosa</h1>
    <p>Hola <strong><?= $buyerName ?></strong>, aqui estan tus <?= $ticketLabelP ?> compradas.</p>
    <div class="order-info">Pedido <?= $orderIdSafe ?> &middot; <?= $emailSafe ?></div>
  </div>

  <button class="print-btn" onclick="window.print()">Guardar como PDF / Imprimir</button>

  <div class="summary">
    <div class="summary-row"><span>Correo confirmacion:</span><span><?= $emailSafe ?></span></div>
    <div class="summary-row"><span>Total de <?= $ticketLabelP ?>:</span><span><?= count($ticketItems) ?></span></div>
    <div class="summary-row"><span>Total pagado:</span><span style="color:#7c3aed;"><?= $totalFmt ?></span></div>
  </div>

  <div class="tickets-wrap">
    <?php foreach ($ticketItems as $item):
        $title = htmlspecialchars($item['raffle_title'], ENT_QUOTES, 'UTF-8');
        $label = htmlspecialchars($item['pack_label'], ENT_QUOTES, 'UTF-8');
        $num = htmlspecialchars($item['number'], ENT_QUOTES, 'UTF-8');
        $amt = '$' . number_format((int)$item['amount'], 0, ',', '.');
        $img = $item['raffle_image'] ? htmlspecialchars($item['raffle_image'], ENT_QUOTES, 'UTF-8') : null;
        $drawFmt = $item['draw_date'] ? date('d/m/Y', strtotime($item['draw_date'])) : '-';
        $dateFmt = $item['paid_date'] ? date('d/m/Y H:i', strtotime($item['paid_date'])) : '-';
    ?>
    <div class="ticket-card">
      <?php if ($img): ?><img class="ticket-img" src="<?= $img ?>" alt="<?= $title ?>"><?php endif; ?>
      <div class="ticket-title"><?= $title ?></div>
      <div class="ticket-meta">
        Pack: <strong><?= $label ?></strong>
        &nbsp;&middot;&nbsp; Imagen: <strong><?= (int)$item['index'] ?> de <?= (int)$item['count'] ?></strong>
        &nbsp;&middot;&nbsp; Valor ref.: <strong><?= $amt ?></strong>
        &nbsp;&middot;&nbsp; Sorteo: <strong><?= $drawFmt ?></strong>
        &nbsp;&middot;&nbsp; Comprado: <strong><?= $dateFmt ?></strong>
      </div>
      <div class="numbers-label">Codigo de esta imagen:</div>
      <div class="ticket-number-hero"><?= $num ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="footer-note">
    <?= $siteName ?> &middot; Orden <?= $orderIdSafe ?> &middot; <?= date('d/m/Y') ?>
    <br>Guarda este comprobante para participar en el sorteo. Buena suerte.
  </div>

  <script>
    window.addEventListener('load', function() {
      setTimeout(function() { window.print(); }, 900);
    });
  </script>
</body>
</html>
