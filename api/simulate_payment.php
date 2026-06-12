<?php
/**
 * SURTEADOS - Simulated Payment (Demo only)
 * Generates real tickets as paid and sends the shared confirmation email.
 * Method: POST  Content-Type: application/json
 * Body: { items: [{raffleId, packId}], buyerName, buyerEmail, buyerPhone }
 */
require __DIR__ . '/config.php';
require_once __DIR__ . '/order_email_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$b = body();

$items         = $b['items'] ?? [];
$buyerName     = trim($b['buyerName'] ?? '');
$buyerRut      = trim($b['buyerRut'] ?? '');
$buyerEmail    = trim($b['buyerEmail'] ?? '');
$buyerPhone    = trim($b['buyerPhone'] ?? '');
$buyerAddress  = trim($b['buyerAddress'] ?? '');
$buyerComuna   = trim($b['buyerComuna'] ?? '');

if (!$items || !$buyerName || !$buyerEmail) {
    json_error('Datos incompletos: items, buyerName y buyerEmail son requeridos');
}
if (!$buyerAddress || !$buyerComuna) {
    json_error('Datos incompletos: buyerAddress y buyerComuna son requeridos');
}
if (!filter_var($buyerEmail, FILTER_VALIDATE_EMAIL)) {
    json_error('Email invalido');
}

$pdo = db();

function sim_genNumbers(PDO $pdo, string $raffleId, int $qty): array
{
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

$orderId = 'sim_' . bin2hex(random_bytes(6));
$createdTickets = [];
$total = 0;

try {
    $pdo->beginTransaction();

    foreach ($items as $item) {
        $raffleId = $item['raffleId'] ?? '';
        $packId = $item['packId'] ?? '';
        if (!$raffleId || !$packId) {
            continue;
        }

        $raffleStmt = $pdo->prepare('SELECT id, title, status, draw_date FROM raffles WHERE id = ?');
        $raffleStmt->execute([$raffleId]);
        $raffleRow = $raffleStmt->fetch();
        if (!$raffleRow || $raffleRow['status'] !== 'active') {
            continue;
        }
        if (raffle_sales_closed($raffleRow['draw_date'] ?? null)) {
            json_error(raffle_closed_sale_message($raffleRow['draw_date'] ?? null));
        }

        $packStmt = $pdo->prepare('SELECT * FROM raffle_packs WHERE id = ? AND raffle_id = ?');
        $packStmt->execute([$packId, $raffleId]);
        $pack = $packStmt->fetch();
        if (!$pack) {
            continue;
        }

        $qty = (int)$pack['qty'];
        $amount = (int)$pack['price'];
        $total += $amount;
        $numbers = sim_genNumbers($pdo, $raffleId, $qty);

        $ticketId = generate_id('t');
        $pdo->prepare(
            "INSERT INTO tickets
             (id, raffle_id, buyer_name, buyer_rut, buyer_email, buyer_phone, buyer_address, buyer_comuna,
              pack_id, pack_label, amount, payment_method, payment_status, ticket_numbers, flow_order)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
        )->execute([
            $ticketId,
            $raffleId,
            $buyerName,
            $buyerRut,
            $buyerEmail,
            $buyerPhone,
            $buyerAddress,
            $buyerComuna,
            $packId,
            $pack['label'],
            $amount,
            'demo',
            'paid',
            json_encode($numbers),
            $orderId,
        ]);

        $pdo->prepare('UPDATE raffles SET sold_tickets = sold_tickets + ? WHERE id = ?')
            ->execute([$qty, $raffleId]);

        $createdTickets[] = [
            'id' => $ticketId,
            'raffleId' => $raffleId,
            'raffleTitle' => $raffleRow['title'] ?? $raffleId,
            'packLabel' => $pack['label'],
            'ticketNumbers' => $numbers,
            'amount' => $amount,
        ];
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_error('Error al generar tickets: ' . $e->getMessage(), 500);
}

if (!$createdTickets) {
    json_error('No se pudieron generar tickets. Verifica que los packs existan.');
}

$mailSent = surteados_send_order_confirmation($pdo, $orderId, $buyerEmail);

json_ok([
    'orderId' => $orderId,
    'tickets' => $createdTickets,
    'total' => $total,
    'mailSent' => $mailSent,
]);
