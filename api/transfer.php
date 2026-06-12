<?php
/** SURTEADOS — Bank transfer payment (immediate ticket assignment, pending payment status) */
require __DIR__ . '/config.php';
require_once __DIR__ . '/location_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$b = body();

$raffleId   = trim($b['raffleId']   ?? '');
$packId     = trim($b['packId']     ?? '');
$buyerName  = trim($b['buyerName']  ?? '');
$buyerRut   = trim($b['buyerRut']   ?? '');
$buyerEmail = trim($b['buyerEmail'] ?? '');
$buyerPhone = trim($b['buyerPhone'] ?? '');
$buyerAddress = trim($b['buyerAddress'] ?? '');
$buyerComuna  = trim($b['buyerComuna']  ?? '');
$buyerCommuneId = $b['buyerCommuneId'] ?? null;

if (!$raffleId || !$packId || !$buyerName || !$buyerEmail) {
    json_error('Datos incompletos: se requiere sorteo, pack, nombre y email');
}
if (!$buyerAddress || !$buyerComuna) {
    json_error('Datos incompletos: se requiere dirección y comuna');
}

if (!filter_var($buyerEmail, FILTER_VALIDATE_EMAIL)) {
    json_error('Email inválido');
}

// Sanitise text inputs
$buyerName  = htmlspecialchars($buyerName,  ENT_QUOTES, 'UTF-8');
$buyerPhone = htmlspecialchars($buyerPhone, ENT_QUOTES, 'UTF-8');
$buyerAddress = htmlspecialchars($buyerAddress, ENT_QUOTES, 'UTF-8');
$buyerComuna  = htmlspecialchars($buyerComuna, ENT_QUOTES, 'UTF-8');

$pdo = db();
$buyerCommune = surteados_resolve_commune($pdo, $buyerCommuneId, $buyerComuna);
$buyerComuna = htmlspecialchars($buyerCommune['name'], ENT_QUOTES, 'UTF-8');
$buyerCommuneId = $buyerCommune['id'];

// Verify raffle is active
$stmt = $pdo->prepare('SELECT id, title, status, draw_date FROM raffles WHERE id = ?');
$stmt->execute([$raffleId]);
$raffle = $stmt->fetch();
if (!$raffle || $raffle['status'] !== 'active') {
    json_error('Sorteo no disponible');
}
if (raffle_sales_closed($raffle['draw_date'] ?? null)) {
    json_error(raffle_closed_sale_message($raffle['draw_date'] ?? null));
}

// Verify pack belongs to raffle
$stmt = $pdo->prepare('SELECT * FROM raffle_packs WHERE id = ? AND raffle_id = ?');
$stmt->execute([$packId, $raffleId]);
$pack = $stmt->fetch();
if (!$pack) {
    json_error('Pack no encontrado');
}

$qty = (int)$pack['qty'];
if ($qty < 1) {
    json_error('Pack inválido');
}

// Generate unique ticket numbers immediately (check paid + pending to avoid duplicates)
$numbers = generateTransferNumbers($pdo, $raffleId, $qty);
if (count($numbers) < $qty) {
    json_error('No hay suficientes números disponibles en este sorteo');
}

$ticketId = generate_id('t');

$pdo->prepare(
    'INSERT INTO tickets
       (id, raffle_id, buyer_name, buyer_rut, buyer_email, buyer_phone, buyer_address, buyer_comuna, buyer_commune_id,
        pack_id, pack_label, amount, payment_method, payment_status, ticket_numbers)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
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
    $pack['price'],
    'transfer',
    'pending',
    json_encode($numbers),
]);

// Increment raffle sold_tickets
$pdo->prepare('UPDATE raffles SET sold_tickets = sold_tickets + ? WHERE id = ?')
    ->execute([$qty, $raffleId]);

json_ok([
    'ticketId'      => $ticketId,
    'ticketNumbers' => $numbers,
    'amount'        => (int)$pack['price'],
]);

// ── Helper ────────────────────────────────────────────────────────────────────
function generateTransferNumbers(PDO $pdo, string $raffleId, int $qty): array
{
    // Include both paid and pending to avoid assigning duplicates
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
            $numbers[]          = $num;
            $existingSet[$num]  = true;
        }
        $attempts++;
    }
    return $numbers;
}
