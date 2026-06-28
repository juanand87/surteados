<?php
/** SURTEADOS - Simulated Payment (Demo only) */
require __DIR__ . '/config.php';
require_once __DIR__ . '/order_email_helper.php';
require_once __DIR__ . '/location_helper.php';
require_once __DIR__ . '/ticket_number_helper.php';
require_once __DIR__ . '/wheel_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Method not allowed', 405);

$b = body();
$items = $b['items'] ?? [];
$buyerName = trim($b['buyerName'] ?? '');
$buyerRut = trim($b['buyerRut'] ?? '');
$buyerEmail = trim($b['buyerEmail'] ?? '');
$buyerPhone = trim($b['buyerPhone'] ?? '');
$buyerAddress = trim($b['buyerAddress'] ?? '');
$buyerComuna = trim($b['buyerComuna'] ?? '');
$buyerCommuneId = $b['buyerCommuneId'] ?? null;
$discountCode = strtoupper(trim((string)($b['discountCode'] ?? '')));

if (!$items || !$buyerName || !$buyerEmail) json_error('Datos incompletos: items, buyerName y buyerEmail son requeridos');
if (!$buyerAddress || !$buyerComuna) json_error('Datos incompletos: buyerAddress y buyerComuna son requeridos');
if (!filter_var($buyerEmail, FILTER_VALIDATE_EMAIL)) json_error('Email invalido');

$pdo = db();
surteados_ensure_ticket_number_tables($pdo);
surteados_ensure_wheel_tables($pdo);
$buyerCommune = surteados_resolve_commune($pdo, $buyerCommuneId, $buyerComuna);
$buyerComuna = $buyerCommune['name'];
$buyerCommuneId = $buyerCommune['id'];

$resolvedItems = [];
$total = 0;
foreach ($items as $item) {
    $raffleId = $item['raffleId'] ?? '';
    $packId = $item['packId'] ?? '';
    if (!$raffleId || !$packId) continue;

    $raffleStmt = $pdo->prepare('SELECT id, title, status, draw_date FROM raffles WHERE id = ?');
    $raffleStmt->execute([$raffleId]);
    $raffleRow = $raffleStmt->fetch();
    if (!$raffleRow || $raffleRow['status'] !== 'active') continue;
    if (raffle_sales_closed($raffleRow['draw_date'] ?? null)) json_error(raffle_closed_sale_message($raffleRow['draw_date'] ?? null));

    $packStmt = $pdo->prepare('SELECT * FROM raffle_packs WHERE id = ? AND raffle_id = ?');
    $packStmt->execute([$packId, $raffleId]);
    $pack = $packStmt->fetch();
    if (!$pack) continue;

    $total += (int)$pack['price'];
    $resolvedItems[] = ['raffle' => $raffleRow, 'pack' => $pack];
}
if (!$resolvedItems) json_error('No se pudieron generar tickets. Verifica que los packs existan.');

$discount = null;
$discountAmount = 0;
if ($discountCode !== '') {
    $discount = surteados_find_valid_discount($pdo, $discountCode, $buyerEmail, $total);
    if (empty($discount['valid'])) json_error($discount['error'] ?? 'Código de descuento inválido.');
    $discountAmount = (int)$discount['discountAmount'];
}

$orderId = 'sim_' . bin2hex(random_bytes(6));
$createdTickets = [];

try {
    $pdo->beginTransaction();
    if ($discountCode !== '' && !surteados_reserve_discount_code($pdo, $discountCode, $buyerEmail, $orderId)) {
        throw new RuntimeException('El código de descuento ya no está disponible.');
    }

    $remainingDiscount = $discountAmount;
    $lastIndex = count($resolvedItems) - 1;
    foreach ($resolvedItems as $idx => $entry) {
        $raffleRow = $entry['raffle'];
        $pack = $entry['pack'];
        $raffleId = $raffleRow['id'];
        $packId = $pack['id'];
        $qty = (int)$pack['qty'];
        $originalAmount = (int)$pack['price'];
        if ($discountAmount > 0) {
            $lineDiscount = ($idx === $lastIndex)
                ? $remainingDiscount
                : min($originalAmount, (int)floor($discountAmount * ($originalAmount / max(1, $total))));
            $remainingDiscount -= $lineDiscount;
        } else {
            $lineDiscount = 0;
        }
        $amount = max(0, $originalAmount - $lineDiscount);

        $ticketId = generate_id('t');
        $numbers = surteados_allocate_ticket_numbers($pdo, $ticketId, $raffleId, $qty);
        $pdo->prepare(
            "INSERT INTO tickets
             (id, raffle_id, buyer_name, buyer_rut, buyer_email, buyer_phone, buyer_address, buyer_comuna,
              buyer_commune_id, pack_id, pack_label, amount, original_amount, discount_code, discount_amount,
              payment_method, payment_status, ticket_numbers, flow_order)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
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
            $originalAmount,
            $discountCode ?: null,
            $lineDiscount,
            'demo',
            'paid',
            json_encode($numbers),
            $orderId,
        ]);

        $pdo->prepare('UPDATE raffles SET sold_tickets = sold_tickets + ? WHERE id = ?')->execute([$qty, $raffleId]);
        $createdTickets[] = [
            'id' => $ticketId,
            'raffleId' => $raffleId,
            'raffleTitle' => $raffleRow['title'] ?? $raffleId,
            'packLabel' => $pack['label'],
            'ticketNumbers' => $numbers,
            'amount' => $amount,
        ];
    }

    if ($discountCode !== '') surteados_mark_discount_used($pdo, $discountCode, $orderId);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
        surteados_release_discount_code($pdo, $orderId);
    }
    json_error('Error al generar tickets: ' . $e->getMessage(), 500);
}

$mailSent = surteados_send_order_confirmation($pdo, $orderId, $buyerEmail);
$mailError = function_exists('surteados_last_email_error') ? surteados_last_email_error() : '';

json_ok([
    'orderId' => $orderId,
    'tickets' => $createdTickets,
    'total' => max(0, $total - $discountAmount),
    'discount' => $discount ? [
        'code' => $discountCode,
        'amount' => $discountAmount,
        'totalBefore' => $total,
        'totalAfter' => max(0, $total - $discountAmount),
    ] : null,
    'mailSent' => $mailSent,
    'mailError' => $mailSent ? '' : $mailError,
]);