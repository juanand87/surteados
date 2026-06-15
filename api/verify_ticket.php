<?php
require __DIR__ . '/config.php';
require_once __DIR__ . '/ticket_verification_helper.php';

$token = trim((string)($_GET['token'] ?? ''));
$result = $token !== '' ? surteados_verify_ticket_token(db(), $token) : ['valid' => false, 'reason' => 'Token no informado'];
$valid = !empty($result['valid']);
$ticket = $result['ticket'] ?? [];
$number = (string)($result['number'] ?? '');
$title = $valid ? 'Ticket válido' : 'Ticket no válido';
$statusColor = $valid ? '#22c55e' : '#ef4444';
$ticketLabel = $number !== '' ? surteados_ticket_number_label($number) : '-';
$drawDate = !empty($ticket['draw_date']) ? date('d/m/Y H:i', strtotime($ticket['draw_date'])) : '-';
$paidDate = !empty($ticket['purchase_date']) ? date('d/m/Y H:i', strtotime($ticket['purchase_date'])) : '-';

header('Content-Type: text/html; charset=UTF-8');
header('X-Robots-Tag: noindex, nofollow');
?><!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($title) ?> - Surteados</title>
  <style>
    * { box-sizing: border-box; }
    body { margin:0; min-height:100vh; display:grid; place-items:center; padding:24px; font-family:Arial,Helvetica,sans-serif; background:#130322; color:#fff; }
    .box { width:min(92vw,560px); border:1px solid rgba(255,255,255,.14); border-radius:18px; padding:28px; background:linear-gradient(145deg,#260b4b,#160322); box-shadow:0 24px 80px rgba(0,0,0,.35); }
    .badge { display:inline-flex; align-items:center; gap:.5rem; padding:.45rem .75rem; border-radius:999px; background:<?= $statusColor ?>22; color:<?= $statusColor ?>; font-weight:800; font-size:.82rem; margin-bottom:16px; }
    h1 { margin:0 0 16px; font-size:2rem; }
    .row { display:flex; justify-content:space-between; gap:18px; padding:10px 0; border-bottom:1px solid rgba(255,255,255,.1); }
    .row span:first-child { color:rgba(255,255,255,.56); font-size:.78rem; text-transform:uppercase; letter-spacing:.08em; }
    .row span:last-child { text-align:right; font-weight:700; }
    .reason { margin-top:12px; color:#fecaca; line-height:1.5; }
  </style>
</head>
<body>
  <main class="box">
    <div class="badge"><?= $valid ? 'VERIFICADO' : 'RECHAZADO' ?></div>
    <h1><?= htmlspecialchars($title) ?></h1>
    <?php if ($valid): ?>
      <div class="row"><span>N° ticket</span><span><?= htmlspecialchars($ticketLabel) ?></span></div>
      <div class="row"><span>Sorteo</span><span><?= htmlspecialchars((string)$ticket['raffle_title']) ?></span></div>
      <div class="row"><span>Fecha sorteo</span><span><?= htmlspecialchars($drawDate) ?></span></div>
      <div class="row"><span>ID venta</span><span><?= htmlspecialchars((string)$ticket['flow_order']) ?></span></div>
      <div class="row"><span>Participante</span><span><?= htmlspecialchars((string)$ticket['buyer_name']) ?></span></div>
      <div class="row"><span>Correo</span><span><?= htmlspecialchars((string)$ticket['buyer_email']) ?></span></div>
      <div class="row"><span>Fecha compra</span><span><?= htmlspecialchars($paidDate) ?></span></div>
    <?php else: ?>
      <p class="reason"><?= htmlspecialchars((string)($result['reason'] ?? 'No fue posible verificar este ticket.')) ?></p>
    <?php endif; ?>
  </main>
</body>
</html>
