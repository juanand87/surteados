<?php
/** SURTEADOS — Flow.cl payment callback (webhook from Flow.cl) */
require __DIR__ . '/config.php';
require __DIR__ . '/FlowAPI.php';
require __DIR__ . '/order_email_helper.php';
require_once __DIR__ . '/ticket_number_helper.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && str_contains($_SERVER['REQUEST_URI'] ?? '', 'flow_callback.php/pago-exitoso.php')) {
    $query = $_SERVER['QUERY_STRING'] ?? '';
    header('Location: ' . rtrim(BASE_URL, '/') . '/pago-exitoso.php' . ($query ? '?' . $query : ''), true, 302);
    exit;
}

// Flow sends a POST with 'token' in the body
$token = trim($_POST['token'] ?? '');
if (!$token) {
    http_response_code(400);
    echo 'Missing token';
    exit;
}

$pdo = db();
surteados_ensure_ticket_number_tables($pdo);

// Load Flow credentials
$stmt = $pdo->query(
    "SELECT `key`, `value` FROM settings
      WHERE `key` IN ('flow_api_key','flow_secret_key','flow_environment')"
);
$flowCfg = [];
foreach ($stmt->fetchAll() as $row) {
    $flowCfg[$row['key']] = $row['value'];
}

$apiKey    = $flowCfg['flow_api_key']     ?? '';
$secretKey = $flowCfg['flow_secret_key']  ?? '';
$env       = $flowCfg['flow_environment'] ?? 'sandbox';

if (!$apiKey || !$secretKey) {
    http_response_code(500);
    echo 'Flow not configured';
    exit;
}

try {
    $flow   = new FlowAPI($apiKey, $secretKey, $env);
    $status = $flow->getPaymentStatus($token);
} catch (\Exception $e) {
    http_response_code(500);
    echo 'Flow error: ' . $e->getMessage();
    exit;
}

// commerceOrder in Flow = our order ID (or legacy ticket ID)
$commerceOrder = $status['commerceOrder'] ?? '';
if (!$commerceOrder) {
    http_response_code(400);
    echo 'No commerceOrder';
    exit;
}

// Find all tickets for order (new flow), fallback to legacy single-ticket flow
$stmt = $pdo->prepare('SELECT * FROM tickets WHERE flow_order = ?');
$stmt->execute([$commerceOrder]);
$tickets = $stmt->fetchAll();

if (!$tickets) {
    $stmt = $pdo->prepare('SELECT * FROM tickets WHERE id = ?');
    $stmt->execute([$commerceOrder]);
    $legacy = $stmt->fetch();
    if (!$legacy) {
        http_response_code(404);
        echo 'Order not found';
        exit;
    }
    $tickets = [$legacy];
}

// Flow status codes: 1=pending, 2=paid, 3=rejected, 4=cancelled
$flowStatus = (int)($status['status'] ?? 0);

if ($flowStatus === 2) {
    // Idempotency: if all are already paid, return OK without side effects
    $pendingTickets = array_values(array_filter($tickets, fn($t) => $t['payment_status'] === 'pending'));
    if (count($pendingTickets) === 0) {
        http_response_code(200);
        echo 'OK';
        exit;
    }

    try {
        $pdo->beginTransaction();

        $emailJobs = [];
        foreach ($pendingTickets as $ticket) {
            $pack = null;
            if ($ticket['pack_id']) {
                $stmt2 = $pdo->prepare('SELECT qty FROM raffle_packs WHERE id = ?');
                $stmt2->execute([$ticket['pack_id']]);
                $pack = $stmt2->fetch();
            }
            $qty = $pack ? (int)$pack['qty'] : 1;

            $numbers = surteados_allocate_ticket_numbers($pdo, (string)$ticket['id'], (string)$ticket['raffle_id'], $qty);

            $pdo->prepare(
                "UPDATE tickets
                    SET payment_status = 'paid',
                        ticket_numbers = ?,
                        flow_token     = ?,
                        flow_order     = ?
                  WHERE id = ?"
            )->execute([
                json_encode($numbers),
                $status['token']       ?? $token,
                $ticket['flow_order']  ?: $commerceOrder,
                $ticket['id'],
            ]);

            // Increment raffle sold_tickets for each paid pending item
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
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(500);
        echo 'Callback processing error: ' . $e->getMessage();
        exit;
    }

} elseif ($flowStatus === 3) {
    $pdo->prepare("UPDATE tickets SET payment_status = 'failed' WHERE payment_status = 'pending' AND (flow_order = ? OR id = ?)")
        ->execute([$commerceOrder, $commerceOrder]);
} elseif ($flowStatus === 4) {
    $pdo->prepare("UPDATE tickets SET payment_status = 'refunded' WHERE payment_status = 'pending' AND (flow_order = ? OR id = ?)")
        ->execute([$commerceOrder, $commerceOrder]);
}

http_response_code(200);
echo 'OK';

// ── Helper ────────────────────────────────────────────────────────────────────
