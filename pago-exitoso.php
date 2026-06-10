<?php
/**
 * SURTEADOS — Página de resultado de pago (Flow.cl return URL)
 * URL: /surteados/pago-exitoso.php?token=XXXX
 */
require __DIR__ . '/api/config.php';
require __DIR__ . '/api/FlowAPI.php';
require __DIR__ . '/api/order_email_helper.php';

client_session_start();

$requestToken = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$requestOrderId = trim((string)($_GET['orderId'] ?? $_POST['orderId'] ?? ''));
$orderId = $requestOrderId !== '' ? $requestOrderId : trim((string)($_SESSION['last_flow_order_id'] ?? ''));
$token = $requestToken !== ''
    ? $requestToken
    : ($requestOrderId === '' ? trim((string)($_SESSION['last_flow_token'] ?? '')) : '');
$tickets = [];
$ticket = null;
$allNums = [];
$totalAmount = 0;

if ($token) {
    syncFlowStatusFromReturn($token);
} elseif ($orderId) {
    syncFlowOrderStatusFromReturn($orderId);
}

if ($token || $orderId) {
    if ($token) {
        $stmt = db()->prepare(
            'SELECT t.*, r.title AS raffle_title
               FROM tickets t
               LEFT JOIN raffles r ON r.id = t.raffle_id
              WHERE t.flow_token = ?
              ORDER BY t.purchase_date ASC'
        );
        $stmt->execute([$token]);
    } else {
        $stmt = db()->prepare(
            'SELECT t.*, r.title AS raffle_title
               FROM tickets t
               LEFT JOIN raffles r ON r.id = t.raffle_id
              WHERE t.flow_order = ?
              ORDER BY t.purchase_date ASC'
        );
        $stmt->execute([$orderId]);
    }
  $tickets = $stmt->fetchAll();
  if ($tickets) {
    $ticket = $tickets[0];
    if (!$orderId) $orderId = (string)($ticket['flow_order'] ?? '');
    if (!$token) $token = (string)($ticket['flow_token'] ?? '');
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

if ($isPaid && !empty($ticket['flow_order'])) {
    try {
        surteados_send_order_confirmation(db(), (string)$ticket['flow_order'], (string)($ticket['buyer_email'] ?? ''));
    } catch (Throwable $mailError) {
        error_log('Order email error: ' . $mailError->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $isPaid ? '¡Pago exitoso!' : ($isPending ? 'Verificando pago…' : 'Resultado del pago') ?> — Surteados</title>
  <?php if ($isPending): ?>
  <meta http-equiv="refresh" content="6;url=pago-exitoso.php?<?= $token ? 'token=' . htmlspecialchars(rawurlencode($token)) : 'orderId=' . htmlspecialchars(rawurlencode($orderId)) ?>">
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

      <?php
        $pdfOrderId = htmlspecialchars($ticket['flow_order'] ?? '');
        $pdfEmail   = urlencode($ticket['buyer_email'] ?? '');
      ?>
      <a href="api/ticket_pdf.php?orderId=<?= $pdfOrderId ?>&email=<?= $pdfEmail ?>" target="_blank" rel="noopener"
         class="btn btn-primary" style="margin-top:1.5rem; display:block; width:100%; text-align:center;">
        📄 Ver mis imágenes compradas
      </a>
      <a href="sorteos.php" class="btn btn-ghost btn-sm" style="margin-top:.75rem;">Ver más sorteos</a>

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
      <?php if (!$token && !$orderId): ?>
      <p style="font-size:.85rem;">El enlace no contiene un identificador de pago válido.</p>
      <?php endif; ?>
      <a href="sorteos.php" class="btn btn-primary" style="margin-top:1rem;">Volver a sorteos</a>
    <?php endif; ?>

  </div>
</div>
</body>
</html>

<?php
function syncFlowStatusFromReturn(string $token): void {
    try {
        $pdo = db();
        $stmt = $pdo->query(
            "SELECT `key`, `value` FROM settings
              WHERE `key` IN ('flow_api_key','flow_secret_key','flow_environment')"
        );
        $flowCfg = [];
        foreach ($stmt->fetchAll() as $row) {
            $flowCfg[$row['key']] = $row['value'];
        }

        $apiKey = $flowCfg['flow_api_key'] ?? '';
        $secretKey = $flowCfg['flow_secret_key'] ?? '';
        if (!$apiKey || !$secretKey) return;

        $flow = new FlowAPI($apiKey, $secretKey, $flowCfg['flow_environment'] ?? 'sandbox');
        $status = $flow->getPaymentStatus($token);
        $commerceOrder = $status['commerceOrder'] ?? '';
        if (!$commerceOrder) return;

        $stmt = $pdo->prepare('SELECT * FROM tickets WHERE flow_order = ?');
        $stmt->execute([$commerceOrder]);
        $tickets = $stmt->fetchAll();
        if (!$tickets) return;

        $flowStatus = (int)($status['status'] ?? 0);
        if ($flowStatus === 2) {
            $pendingTickets = array_values(array_filter($tickets, fn($t) => $t['payment_status'] === 'pending'));
            if (!$pendingTickets) return;

            $emailJobs = [];
            $pdo->beginTransaction();
            try {
                foreach ($pendingTickets as $ticket) {
                    $qty = 1;
                    if ($ticket['pack_id']) {
                        $packStmt = $pdo->prepare('SELECT qty FROM raffle_packs WHERE id = ?');
                        $packStmt->execute([$ticket['pack_id']]);
                        $pack = $packStmt->fetch();
                        if ($pack) $qty = (int)$pack['qty'];
                    }

                    $numbers = generateReturnUniqueNumbers($pdo, $ticket['raffle_id'], $qty);
                    $pdo->prepare(
                        "UPDATE tickets
                            SET payment_status = 'paid',
                                ticket_numbers = ?,
                                flow_token = ?,
                                flow_order = ?
                          WHERE id = ?"
                    )->execute([
                        json_encode($numbers),
                        $status['token'] ?? $token,
                        $ticket['flow_order'] ?: $commerceOrder,
                        $ticket['id'],
                    ]);

                    $pdo->prepare('UPDATE raffles SET sold_tickets = sold_tickets + ? WHERE id = ?')
                        ->execute([$qty, $ticket['raffle_id']]);
                    $emailJobs[$ticket['flow_order'] ?: $commerceOrder] = $ticket['buyer_email'] ?? '';
                }
                $pdo->commit();
                foreach ($emailJobs as $orderId => $email) {
                    try {
                        surteados_send_order_confirmation($pdo, (string)$orderId, (string)$email);
                    } catch (Throwable $mailError) {
                        error_log('Order email error: ' . $mailError->getMessage());
                    }
                }
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
            }
        } elseif ($flowStatus === 3) {
            $pdo->prepare("UPDATE tickets SET payment_status = 'failed' WHERE payment_status = 'pending' AND flow_order = ?")
                ->execute([$commerceOrder]);
        } elseif ($flowStatus === 4) {
            $pdo->prepare("UPDATE tickets SET payment_status = 'refunded' WHERE payment_status = 'pending' AND flow_order = ?")
                ->execute([$commerceOrder]);
        }
    } catch (Throwable $e) {
        error_log('Flow return sync error: ' . $e->getMessage());
    }
}

function syncFlowOrderStatusFromReturn(string $orderId): void {
    try {
        $pdo = db();
        $stmt = $pdo->query(
            "SELECT `key`, `value` FROM settings
              WHERE `key` IN ('flow_api_key','flow_secret_key','flow_environment')"
        );
        $flowCfg = [];
        foreach ($stmt->fetchAll() as $row) {
            $flowCfg[$row['key']] = $row['value'];
        }

        $apiKey = $flowCfg['flow_api_key'] ?? '';
        $secretKey = $flowCfg['flow_secret_key'] ?? '';
        if (!$apiKey || !$secretKey) return;

        $flow = new FlowAPI($apiKey, $secretKey, $flowCfg['flow_environment'] ?? 'sandbox');
        $status = $flow->getStatusByCommerceId($orderId);
        if (empty($status['commerceOrder'])) {
            $status['commerceOrder'] = $orderId;
        }

        $commerceOrder = $status['commerceOrder'];
        $stmt = $pdo->prepare('SELECT * FROM tickets WHERE flow_order = ?');
        $stmt->execute([$commerceOrder]);
        $tickets = $stmt->fetchAll();
        if (!$tickets) return;

        $flowStatus = (int)($status['status'] ?? 0);
        if ($flowStatus === 2) {
            $pendingTickets = array_values(array_filter($tickets, fn($t) => $t['payment_status'] === 'pending'));
            if (!$pendingTickets) return;

            $emailJobs = [];
            $pdo->beginTransaction();
            try {
                foreach ($pendingTickets as $ticket) {
                    $qty = 1;
                    if ($ticket['pack_id']) {
                        $packStmt = $pdo->prepare('SELECT qty FROM raffle_packs WHERE id = ?');
                        $packStmt->execute([$ticket['pack_id']]);
                        $pack = $packStmt->fetch();
                        if ($pack) $qty = (int)$pack['qty'];
                    }

                    $numbers = generateReturnUniqueNumbers($pdo, $ticket['raffle_id'], $qty);
                    $pdo->prepare(
                        "UPDATE tickets
                            SET payment_status = 'paid',
                                ticket_numbers = ?,
                                flow_token = COALESCE(NULLIF(?, ''), flow_token),
                                flow_order = ?
                          WHERE id = ?"
                    )->execute([
                        json_encode($numbers),
                        $status['token'] ?? '',
                        $ticket['flow_order'] ?: $commerceOrder,
                        $ticket['id'],
                    ]);

                    $pdo->prepare('UPDATE raffles SET sold_tickets = sold_tickets + ? WHERE id = ?')
                        ->execute([$qty, $ticket['raffle_id']]);
                    $emailJobs[$ticket['flow_order'] ?: $commerceOrder] = $ticket['buyer_email'] ?? '';
                }
                $pdo->commit();
                foreach ($emailJobs as $jobOrderId => $email) {
                    try {
                        surteados_send_order_confirmation($pdo, (string)$jobOrderId, (string)$email);
                    } catch (Throwable $mailError) {
                        error_log('Order email error: ' . $mailError->getMessage());
                    }
                }
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
            }
        } elseif ($flowStatus === 3) {
            $pdo->prepare("UPDATE tickets SET payment_status = 'failed' WHERE payment_status = 'pending' AND flow_order = ?")
                ->execute([$commerceOrder]);
        } elseif ($flowStatus === 4) {
            $pdo->prepare("UPDATE tickets SET payment_status = 'refunded' WHERE payment_status = 'pending' AND flow_order = ?")
                ->execute([$commerceOrder]);
        }
    } catch (Throwable $e) {
        error_log('Flow order return sync error: ' . $e->getMessage());
    }
}

function generateReturnUniqueNumbers(PDO $pdo, string $raffleId, int $qty): array {
    $stmt = $pdo->prepare(
        "SELECT ticket_numbers FROM tickets
          WHERE raffle_id = ? AND payment_status = 'paid' AND ticket_numbers IS NOT NULL"
    );
    $stmt->execute([$raffleId]);

    $existing = [];
    foreach ($stmt->fetchAll() as $row) {
        $nums = json_decode($row['ticket_numbers'], true) ?? [];
        $existing = array_merge($existing, $nums);
    }
    $existingSet = array_flip($existing);

    $numbers = [];
    $attempts = 0;
    while (count($numbers) < $qty && $attempts < 100000) {
        $num = str_pad((string)mt_rand(1, 99999), 6, '0', STR_PAD_LEFT);
        if (!isset($existingSet[$num])) {
            $numbers[] = $num;
            $existingSet[$num] = true;
        }
        $attempts++;
    }
    return $numbers;
}
?>
