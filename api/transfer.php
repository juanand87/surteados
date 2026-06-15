<?php
/** SURTEADOS - Bank transfer payment (immediate ticket assignment, pending payment status) */
require __DIR__ . '/config.php';
require_once __DIR__ . '/location_helper.php';
require_once __DIR__ . '/ticket_number_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$b = body();

$raffleId = trim($b['raffleId'] ?? '');
$packId = trim($b['packId'] ?? '');
$buyerName = trim($b['buyerName'] ?? '');
$buyerRut = trim($b['buyerRut'] ?? '');
$buyerEmail = trim($b['buyerEmail'] ?? '');
$buyerPhone = trim($b['buyerPhone'] ?? '');
$buyerAddress = trim($b['buyerAddress'] ?? '');
$buyerComuna = trim($b['buyerComuna'] ?? '');
$buyerCommuneId = $b['buyerCommuneId'] ?? null;

if (!$raffleId || !$packId || !$buyerName || !$buyerEmail) {
    json_error('Datos incompletos: se requiere sorteo, pack, nombre y email');
}
if (!$buyerAddress || !$buyerComuna) {
    json_error('Datos incompletos: se requiere direccion y comuna');
}
if (!filter_var($buyerEmail, FILTER_VALIDATE_EMAIL)) {
    json_error('Email invalido');
}

$buyerName = htmlspecialchars($buyerName, ENT_QUOTES, 'UTF-8');
$buyerPhone = htmlspecialchars($buyerPhone, ENT_QUOTES, 'UTF-8');
$buyerAddress = htmlspecialchars($buyerAddress, ENT_QUOTES, 'UTF-8');
$buyerComuna = htmlspecialchars($buyerComuna, ENT_QUOTES, 'UTF-8');

$pdo = db();
surteados_ensure_ticket_number_tables($pdo);
$buyerCommune = surteados_resolve_commune($pdo, $buyerCommuneId, $buyerComuna);
$buyerComuna = htmlspecialchars($buyerCommune['name'], ENT_QUOTES, 'UTF-8');
$buyerCommuneId = $buyerCommune['id'];

$stmt = $pdo->prepare('SELECT id, title, status, draw_date FROM raffles WHERE id = ?');
$stmt->execute([$raffleId]);
$raffle = $stmt->fetch();
if (!$raffle || $raffle['status'] !== 'active') {
    json_error('Sorteo no disponible');
}
if (raffle_sales_closed($raffle['draw_date'] ?? null)) {
    json_error(raffle_closed_sale_message($raffle['draw_date'] ?? null));
}

$stmt = $pdo->prepare('SELECT * FROM raffle_packs WHERE id = ? AND raffle_id = ?');
$stmt->execute([$packId, $raffleId]);
$pack = $stmt->fetch();
if (!$pack) {
    json_error('Pack no encontrado');
}

$qty = (int)$pack['qty'];
if ($qty < 1) {
    json_error('Pack invalido');
}

$ticketId = generate_id('t');

try {
    $pdo->beginTransaction();
    $numbers = surteados_allocate_ticket_numbers($pdo, $ticketId, $raffleId, $qty);

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

    $pdo->prepare('UPDATE raffles SET sold_tickets = sold_tickets + ? WHERE id = ?')
        ->execute([$qty, $raffleId]);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    json_error('No se pudieron asignar numeros de imagen: ' . $e->getMessage(), 500);
}

json_ok([
    'ticketId' => $ticketId,
    'ticketNumbers' => $numbers,
    'amount' => (int)$pack['price'],
]);
