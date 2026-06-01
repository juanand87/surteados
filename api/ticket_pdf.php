<?php
/**
 * SURTEADOS — Ticket printable page / PDF
 * GET /api/ticket_pdf.php?orderId=sim_xxx&email=xxx
 *
 * Returns a printable HTML page with all purchased ticket images.
 * The browser print dialog allows saving as PDF.
 * Auto-triggers the print dialog on load.
 */
require __DIR__ . '/config.php';

$orderId = trim($_GET['orderId'] ?? '');
$email   = trim($_GET['email']   ?? '');

if (!$orderId || !$email) {
    http_response_code(400);
    echo '<p style="font-family:sans-serif;padding:2rem;">Parámetros inválidos.</p>';
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo '<p style="font-family:sans-serif;padding:2rem;">Email inválido.</p>';
    exit;
}

$pdo = db();

// Build a date expression compatible with older/newer ticket schemas.
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
      WHERE t.flow_order = ? AND t.buyer_email = ? AND t.payment_status = 'paid'
    ORDER BY paid_date ASC"
);
$stmt->execute([$orderId, $email]);
$tickets = $stmt->fetchAll();

if (!$tickets) {
    http_response_code(404);
    echo '<p style="font-family:sans-serif;padding:2rem;">No se encontraron imágenes para este pedido.</p>';
    exit;
}

$cfgStmt = $pdo->query(
    "SELECT `key`, `value` FROM settings WHERE `key` IN ('site_name','ticket_label','ticket_label_plural')"
);
$cfg = [];
foreach ($cfgStmt->fetchAll() as $row) $cfg[$row['key']] = $row['value'];

$siteName     = htmlspecialchars($cfg['site_name'] ?? 'Surteados');
$ticketLabelP = htmlspecialchars($cfg['ticket_label_plural'] ?? 'imágenes');
$buyerName    = htmlspecialchars($tickets[0]['buyer_name'] ?? '');
$orderIdSafe  = htmlspecialchars($orderId);
$emailSafe    = htmlspecialchars($email);

$total = array_sum(array_column($tickets, 'amount'));
$totalFmt = '$' . number_format($total, 0, ',', '.');

header('Content-Type: text/html; charset=UTF-8');
header('X-Robots-Tag: noindex, nofollow');
?><!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Mis imágenes compradas — <?= $siteName ?></title>
  <style>
    *                 { box-sizing: border-box; margin: 0; padding: 0; }
    body              { font-family: Arial, Helvetica, sans-serif; background: #f0eaf8; color: #1a1a2e; padding: 1.5rem; }
    .page-header      { text-align: center; margin-bottom: 1.8rem; padding: 1.6rem 2rem;
                        background: linear-gradient(135deg, #7c3aed, #db2777);
                        color: #fff; border-radius: 14px; }
    .page-header h1   { font-size: 1.5rem; margin-bottom: .4rem; }
    .page-header p    { opacity: .85; font-size: .9rem; }
    .order-info       { font-size: .75rem; opacity: .65; margin-top: .3rem; }
    .print-btn        { display: flex; align-items: center; justify-content: center; gap: .5rem;
                        margin: 0 auto 1.8rem; padding: .65rem 2.2rem;
                        background: #7c3aed; color: #fff; border: none;
                        border-radius: 50px; font-size: .95rem; font-weight: 700;
                        cursor: pointer; box-shadow: 0 4px 12px rgba(124,58,237,.35); }
    .tickets-wrap     { max-width: 640px; margin: 0 auto; }
    .ticket-card      { background: #fff; border-radius: 14px; padding: 1.4rem 1.5rem;
                        margin-bottom: 1.4rem; border: 2px solid #7c3aed;
                        box-shadow: 0 4px 18px rgba(124,58,237,.13); page-break-inside: avoid; }
    .ticket-img       { width: 100%; max-height: 210px; object-fit: cover;
                        border-radius: 10px; margin-bottom: 1rem; }
    .ticket-title     { font-size: 1.05rem; font-weight: 800; color: #5b21b6; margin-bottom: .3rem; }
    .ticket-meta      { font-size: .78rem; color: #777; margin-bottom: .9rem; }
    .ticket-meta strong { color: #444; }
    .numbers-label    { font-size: .82rem; font-weight: 700; color: #374151; margin-bottom: .45rem; }
    .numbers-grid     { display: flex; flex-wrap: wrap; gap: .35rem; }
    .number-chip      { background: linear-gradient(135deg, #7c3aed, #5b21b6);
                        color: #fff; padding: .3rem .65rem; border-radius: 7px;
                        font-family: 'Courier New', monospace; font-size: .85rem; font-weight: 700; }
    .summary          { max-width: 640px; margin: 0 auto 1.5rem;
                        background: #fff; border-radius: 12px; padding: 1rem 1.4rem;
                        border: 1px solid #ddd6fe; }
    .summary-row      { display: flex; justify-content: space-between; font-size: .85rem;
                        padding: .3rem 0; border-bottom: 1px solid #f3f4f6; }
    .summary-row:last-child { border-bottom: none; font-weight: 700; font-size: .95rem; }
    .footer-note      { text-align: center; color: #aaa; font-size: .72rem;
                        margin-top: 1.5rem; padding-bottom: 1.5rem; }
    @media print {
      body            { background: #fff; padding: .3rem; }
      .print-btn      { display: none !important; }
      .page-header    { border-radius: 0; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
      .ticket-card    { box-shadow: none; border: 1px solid #ccc; page-break-inside: avoid; }
      .number-chip    { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
  </style>
</head>
<body>
  <div class="page-header">
    <div style="font-size:2.4rem;margin-bottom:.4rem;">🎟️</div>
    <h1>¡Compra exitosa!</h1>
    <p>Hola <strong><?= $buyerName ?></strong>, aquí están tus imágenes compradas.</p>
    <div class="order-info">Pedido <?= $orderIdSafe ?> &nbsp;·&nbsp; <?= $emailSafe ?></div>
  </div>

  <button class="print-btn" onclick="window.print()">
    🖨️ Guardar como PDF / Imprimir
  </button>

  <div class="summary">
    <div class="summary-row">
      <span>📧 Correo confirmación:</span>
      <span><?= $emailSafe ?></span>
    </div>
    <div class="summary-row">
      <span>🎫 Total de imágenes:</span>
      <span><?= array_sum(array_map(fn($t) => count(json_decode($t['ticket_numbers'], true) ?? []), $tickets)) ?></span>
    </div>
    <div class="summary-row">
      <span>💰 Total pagado:</span>
      <span style="color:#7c3aed;"><?= $totalFmt ?></span>
    </div>
  </div>

  <div class="tickets-wrap">
  <?php foreach ($tickets as $t):
      $nums  = json_decode($t['ticket_numbers'], true) ?? [];
      $title = htmlspecialchars($t['raffle_title']);
      $label = htmlspecialchars($t['pack_label']);
      $amt   = '$' . number_format((int)$t['amount'], 0, ',', '.');
      $img   = $t['raffle_image'] ? htmlspecialchars($t['raffle_image']) : null;
      $rawDraw = $t['draw_date'] ?? null;
      $drawFmt = $rawDraw ? date('d/m/Y', strtotime($rawDraw)) : '—';
        $rawDate = $t['paid_date'] ?? null;
      $dateFmt = $rawDate ? date('d/m/Y H:i', strtotime($rawDate)) : '—';
  ?>
    <div class="ticket-card">
      <?php if ($img): ?>
        <img class="ticket-img" src="<?= $img ?>" alt="<?= $title ?>">
      <?php endif; ?>
      <div class="ticket-title"><?= $title ?></div>
      <div class="ticket-meta">
        Pack: <strong><?= $label ?></strong>
        &nbsp;·&nbsp; Monto: <strong><?= $amt ?></strong>
        &nbsp;·&nbsp; Sorteo: <strong><?= $drawFmt ?></strong>
        &nbsp;·&nbsp; Comprado: <strong><?= $dateFmt ?></strong>
      </div>
      <div class="numbers-label">Tus números (<?= count($nums) ?>):</div>
      <div class="numbers-grid">
        <?php foreach ($nums as $n): ?>
          <span class="number-chip"><?= htmlspecialchars($n) ?></span>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
  </div>

  <div class="footer-note">
    <?= $siteName ?> &nbsp;·&nbsp; Orden <?= $orderIdSafe ?> &nbsp;·&nbsp; <?= date('d/m/Y') ?>
    <br>Guarda este comprobante para participar en el sorteo. ¡Buena suerte! 🍀
  </div>

  <script>
    // Auto-trigger print dialog once the page (including images) has loaded
    window.addEventListener('load', function() {
      setTimeout(function() { window.print(); }, 900);
    });
  </script>
</body>
</html>
