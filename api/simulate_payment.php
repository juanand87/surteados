<?php
/**
 * SURTEADOS - Simulated Payment (Demo only)
 * Generates real tickets as paid and sends the shared confirmation email.
 * Method: POST  Content-Type: application/json
 * Body: { items: [{raffleId, packId}], buyerName, buyerEmail, buyerPhone }
 */
require __DIR__ . '/config.php';
require_once __DIR__ . '/order_email_helper.php';
require_once __DIR__ . '/location_helper.php';
require_once __DIR__ . '/ticket_number_helper.php';

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
$buyerCommuneId = $b['buyerCommuneId'] ?? null;

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
surteados_ensure_ticket_number_tables($pdo);
$buyerCommune = surteados_resolve_commune($pdo, $buyerCommuneId, $buyerComuna);
$buyerComuna = $buyerCommune['name'];
$buyerCommuneId = $buyerCommune['id'];

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

        $ticketId = generate_id('t');
        $numbers = surteados_allocate_ticket_numbers($pdo, $ticketId, $raffleId, $qty);
        $pdo->prepare(
            "INSERT INTO tickets
             (id, raffle_id, buyer_name, buyer_rut, buyer_email, buyer_phone, buyer_address, buyer_comuna,
              buyer_commune_id, pack_id, pack_label, amount, payment_method, payment_status, ticket_numbers, flow_order)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
        )->execute([
            $ticketId,
            $raffleId,
            $buyerName,
            $buyerRut,
            $buyerEmail,
            $buyerPhone,
            $buyerAddress,
            $buyerComuna,
            $buyerCommuneId,
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
$mailError = function_exists('surteados_last_email_error') ? surteados_last_email_error() : '';

json_ok([
    'orderId' => $orderId,
    'tickets' => $createdTickets,
    'total' => $total,
    'mailSent' => $mailSent,
    'mailError' => $mailSent ? '' : $mailError,
]);
