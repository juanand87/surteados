<?php
/**
 * SURTEADOS — Cart checkout (multi-raffle, single payment)
 *
 * POST  /api/cart.php
 * Body: {
 *   items:       [{ raffleId, packId }, ...],
 *   buyerName:   string,
 *   buyerEmail:  string,
 *   buyerPhone:  string,
 *   paymentMethod: 'transfer' | 'flow' | ...   (only 'transfer' fully implemented)
 * }
 */
require __DIR__ . '/config.php';
require_once __DIR__ . '/location_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$b = body();

$items       = $b['items']         ?? [];
$buyerName   = trim($b['buyerName']  ?? '');
$buyerRut    = trim($b['buyerRut']   ?? '');
$buyerEmail  = trim($b['buyerEmail'] ?? '');
$buyerPhone  = trim($b['buyerPhone'] ?? '');
$buyerAddress = trim($b['buyerAddress'] ?? '');
$buyerComuna  = trim($b['buyerComuna']  ?? '');
$buyerCommuneId = $b['buyerCommuneId'] ?? null;
$payMethod   = trim($b['paymentMethod'] ?? 'transfer');

// ── Validate inputs ──────────────────────────────────────────────────────────
if (!is_array($items) || count($items) === 0) {
    json_error('El carrito está vacío');
}
if (count($items) > 10) {
    json_error('Máximo 10 sorteos por compra');
}
if (!$buyerName) {
    json_error('Nombre requerido');
}
if (!filter_var($buyerEmail, FILTER_VALIDATE_EMAIL)) {
    json_error('Email inválido');
}
if (!$buyerAddress || !$buyerComuna) {
    json_error('Dirección y comuna requeridas');
}

$buyerName  = htmlspecialchars($buyerName,  ENT_QUOTES, 'UTF-8');
$buyerPhone = htmlspecialchars($buyerPhone, ENT_QUOTES, 'UTF-8');
$buyerAddress = htmlspecialchars($buyerAddress, ENT_QUOTES, 'UTF-8');
$buyerComuna  = htmlspecialchars($buyerComuna, ENT_QUOTES, 'UTF-8');

$pdo = db();
$buyerCommune = surteados_resolve_commune($pdo, $buyerCommuneId, $buyerComuna);
$buyerComuna = htmlspecialchars($buyerCommune['name'], ENT_QUOTES, 'UTF-8');
$buyerCommuneId = $buyerCommune['id'];

// ── Validate & load all items ────────────────────────────────────────────────
$resolved = [];
$totalAmount = 0;

foreach ($items as $idx => $item) {
    $raffleId = trim($item['raffleId'] ?? '');
    $packId   = trim($item['packId']   ?? '');

    if (!$raffleId || !$packId) {
        json_error("Item #{$idx}: raffleId y packId son requeridos");
    }

    $stmt = $pdo->prepare('SELECT id, title, status, draw_date FROM raffles WHERE id = ?');
    $stmt->execute([$raffleId]);
    $raffle = $stmt->fetch();
    if (!$raffle || $raffle['status'] !== 'active') {
        json_error('El sorteo "' . $raffleId . '" ya no está disponible');
    }
    if (raffle_sales_closed($raffle['draw_date'] ?? null)) {
        json_error(raffle_closed_sale_message($raffle['draw_date'] ?? null));
    }

    $stmt = $pdo->prepare('SELECT * FROM raffle_packs WHERE id = ? AND raffle_id = ?');
    $stmt->execute([$packId, $raffleId]);
    $pack = $stmt->fetch();
    if (!$pack) {
        json_error('Pack "' . $packId . '" no encontrado para el sorteo "' . $raffleId . '"');
    }

    $qty = (int)$pack['qty'];
    if ($qty < 1) {
        json_error("Pack inválido: {$packId}");
    }

    $resolved[] = compact('raffle', 'pack', 'qty');
    $totalAmount += (int)$pack['price'];
}

// ── Create all tickets atomically ────────────────────────────────────────────
$pdo->beginTransaction();
try {
    $createdTickets = [];

    foreach ($resolved as $entry) {
        $raffle    = $entry['raffle'];
        $pack      = $entry['pack'];
        $qty       = $entry['qty'];

        $numbers = generateCartNumbers($pdo, $raffle['id'], $qty);
        if (count($numbers) < $qty) {
            throw new \RuntimeException(
                'No hay suficientes números disponibles en el sorteo "' . $raffle['title'] . '"'
            );
        }

        $ticketId = generate_id('t');

        $pdo->prepare(
            'INSERT INTO tickets
               (id, raffle_id, buyer_name, buyer_rut, buyer_email, buyer_phone, buyer_address, buyer_comuna, buyer_commune_id,
                pack_id, pack_label, amount, payment_method, payment_status, ticket_numbers)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            $ticketId,
            $raffle['id'],
            $buyerName,
            $buyerRut,
            $buyerEmail,
            $buyerPhone,
            $buyerAddress,
            $buyerComuna,
            $buyerCommuneId,
            $pack['id'],
            $pack['label'],
            $pack['price'],
            $payMethod,
            'pending',
            json_encode($numbers),
        ]);

        $pdo->prepare('UPDATE raffles SET sold_tickets = sold_tickets + ? WHERE id = ?')
            ->execute([$qty, $raffle['id']]);

        $createdTickets[] = [
            'ticketId'      => $ticketId,
            'raffleId'      => $raffle['id'],
            'raffleTitle'   => $raffle['title'],
            'packLabel'     => $pack['label'],
            'amount'        => (int)$pack['price'],
            'ticketNumbers' => $numbers,
        ];
    }

    $pdo->commit();
} catch (\Throwable $e) {
    $pdo->rollBack();
    json_error($e->getMessage());
}

json_ok([
    'tickets'     => $createdTickets,
    'totalAmount' => $totalAmount,
]);

// ── Helper ────────────────────────────────────────────────────────────────────
function generateCartNumbers(PDO $pdo, string $raffleId, int $qty): array
{
    $stmt = $pdo->prepare(
        "SELECT ticket_numbers FROM tickets
          WHERE raffle_id = ?
            AND payment_status IN ('paid', 'pending')
            AND ticket_numbers IS NOT NULL"
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
