<?php
/**
 * SURTEADOS — Página de resultado de pago (Flow.cl return URL)
 * URL: /surteados/pago-exitoso.php?token=XXXX
 */
require __DIR__ . '/api/config.php';

$token  = trim($_GET['token'] ?? '');
$tickets = [];
$ticket = null;
$allNums = [];
$totalAmount = 0;

if ($token) {
    $stmt = db()->prepare(
        'SELECT t.*, r.title AS raffle_title
           FROM tickets t
           LEFT JOIN raffles r ON r.id = t.raffle_id
      WHERE t.flow_token = ?
      ORDER BY t.purchase_date ASC'
    );
    $stmt->execute([$token]);
  $tickets = $stmt->fetchAll();
  if ($tickets) {
    $ticket = $tickets[0];
    foreach ($tickets as $t) {
      $nums = json_decode($t['ticket_numbers'] ?? '[]', true) ?? [];
      $allNums = array_merge($allNums, $nums);
      $totalAmount += (int)($t['amount'] ?? 0);
    }
    }
}

$isPending = $ticket && $ticket['payment_status'] === 'pending';
$isPaid    = $ticket && $ticket['payment_status'] === 'paid';
$isFailed  = $ticket && in_array($ticket['payment_status'], ['failed', 'refunded']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $isPaid ? '¡Pago exitoso!' : ($isPending ? 'Verificando pago…' : 'Resultado del pago') ?> — Surteados</title>
  <?php if ($isPending): ?>
  <meta http-equiv="refresh" content="6;url=pago-exitoso.php?token=<?= htmlspecialchars($token) ?>">
  <?php endif; ?>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/styles.css">
  <style>
    .result-page { min-height:100vh; display:flex; flex-direction:column; align-items:center; justify-content:center; padding:2rem; background:var(--bg-base); }
    .result-card { background:#fff; border-radius:1.5rem; box-shadow:0 8px 40px rgba(0,0,0,.1); padding:3rem 2.5rem; max-width:600px; width:100%; text-align:center; }
    .result-icon { font-size:4rem; margin-bottom:1rem; }
    .result-card h1 { font-size:1.75rem; margin-bottom:.5rem; }
    .result-card p  { color:var(--text-secondary); margin-bottom:1.5rem; }
    .ticket-grid { display:flex; flex-wrap:wrap; gap:.6rem; justify-content:center; margin:1.5rem 0; }
    .ticket-num  { background:var(--bg-base); border:2px solid var(--color-primary); color:var(--color-primary); padding:.4rem 1rem; border-radius:.5rem; font-family:monospace; font-size:1.05rem; font-weight:700; }
    .spin { animation:spin 1.2s linear infinite; display:inline-block; }
    @keyframes spin { to { transform: rotate(360deg); } }
  </style>
</head>
<body>
<nav class="navbar" style="background:rgba(10,10,15,.98);">
  <div class="navbar-inner">
    <a href="index.php" class="navbar-logo"><div class="logo-icon">🎟️</div><span class="brand">Sur<em>tea</em>dos</span></a>
    <div class="navbar-actions"><a href="sorteos.php" class="btn btn-outline btn-sm">Ver sorteos</a></div>
  </div>
</nav>

<div class="result-page">
  <div class="result-card">

    <?php if ($isPaid): ?>
      <div class="result-icon">🎉</div>
      <h1>¡Pago exitoso!</h1>
      <p>Tu compra fue procesada correctamente. Guarda tus números de ticket.</p>

      <div style="background:var(--bg-base); border-radius:1rem; padding:1.5rem; margin-bottom:1.5rem;">
        <p style="font-weight:600; margin-bottom:.25rem;">Compra #<?= htmlspecialchars($ticket['flow_order'] ?? '') ?></p>
        <p style="font-size:.85rem; color:var(--text-secondary); margin-bottom:1rem;">
          <?= count($tickets) ?> sorteo(s) — <?= '$' . number_format($totalAmount, 0, ',', '.') ?> CLP
        </p>

        <?php foreach ($tickets as $t): ?>
          <?php $tNums = json_decode($t['ticket_numbers'] ?? '[]', true) ?? []; ?>
          <div style="background:#fff;border:1px solid #eee;border-radius:.75rem;padding:.9rem;margin-bottom:.75rem;text-align:left;">
            <p style="font-weight:700;margin:0 0 .35rem 0;"><?= htmlspecialchars($t['raffle_title'] ?? 'Sorteo') ?></p>
            <p style="font-size:.82rem;color:var(--text-secondary);margin:0 0 .5rem 0;">
              <?= htmlspecialchars($t['pack_label'] ?? '') ?> — <?= '$' . number_format((int)$t['amount'], 0, ',', '.') ?> CLP
            </p>
            <div class="ticket-grid" style="justify-content:flex-start; margin:.2rem 0 0;">
              <?php foreach ($tNums as $n): ?>
                <span class="ticket-num"><?= htmlspecialchars($n) ?></span>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>

        <p style="font-weight:700; margin-top:1rem; margin-bottom:.25rem; font-size:.95rem;">Total números asignados: <?= count($allNums) ?></p>
      </div>

      <p style="font-size:.82rem; color:var(--text-secondary);">
        Comprador: <strong><?= htmlspecialchars($ticket['buyer_name']) ?></strong>
        (<?= htmlspecialchars($ticket['buyer_email']) ?>)
      </p>
      <p style="font-size:.78rem; color:var(--text-secondary); margin-top:.25rem;">
        Guarda este número de orden Flow: <code><?= htmlspecialchars($ticket['flow_order'] ?? '') ?></code>
      </p>

      <a href="sorteos.php" class="btn btn-primary" style="margin-top:1.5rem;">Ver más sorteos</a>

    <?php elseif ($isPending): ?>
      <div class="result-icon"><span class="spin">⏳</span></div>
      <h1>Verificando tu pago…</h1>
      <p>Estamos confirmando el pago con Flow.cl. Esta página se actualizará automáticamente en unos segundos.</p>
      <p style="font-size:.82rem;">Si el pago fue aprobado, tus tickets aparecerán aquí en breve.</p>
      <div style="width:48px; height:4px; background:var(--color-primary); border-radius:2px; margin:1.5rem auto; animation:spin 1.5s linear infinite;"></div>
      <a href="sorteos.php" class="btn btn-ghost btn-sm" style="margin-top:.5rem;">Volver a sorteos</a>

    <?php elseif ($isFailed): ?>
      <div class="result-icon">❌</div>
      <h1>Pago no completado</h1>
      <p>El pago fue <?= $ticket['payment_status'] === 'refunded' ? 'devuelto' : 'rechazado o cancelado' ?>.</p>
      <p style="font-size:.85rem;">Si tienes dudas contáctanos. Estado: <code><?= htmlspecialchars($ticket['payment_status']) ?></code></p>
      <a href="sorteos.php" class="btn btn-primary" style="margin-top:1rem;">Intentar de nuevo</a>

    <?php else: ?>
      <div class="result-icon">🤔</div>
      <h1>No encontramos tu pago</h1>
      <p>No pudimos encontrar una compra asociada a este enlace.</p>
      <?php if (!$token): ?>
      <p style="font-size:.85rem;">El enlace no contiene un token de pago válido.</p>
      <?php endif; ?>
      <a href="sorteos.php" class="btn btn-primary" style="margin-top:1rem;">Volver a sorteos</a>
    <?php endif; ?>

  </div>
</div>
</body>
</html>
