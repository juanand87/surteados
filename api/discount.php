<?php
require __DIR__ . '/config.php';
require_once __DIR__ . '/wheel_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Method not allowed', 405);

$pdo = db();
surteados_ensure_wheel_tables($pdo);
$b = body();
$items = $b['items'] ?? [];
$email = trim((string)($b['buyerEmail'] ?? $b['email'] ?? ''));
$code = trim((string)($b['code'] ?? ''));

if (!is_array($items) || count($items) === 0) json_error('El carrito está vacío.');

$total = 0;
foreach ($items as $item) {
    $raffleId = trim((string)($item['raffleId'] ?? ''));
    $packId = trim((string)($item['packId'] ?? ''));
    if ($raffleId === '' || $packId === '') json_error('Item inválido.');
    $stmt = $pdo->prepare('SELECT price FROM raffle_packs WHERE id = ? AND raffle_id = ? LIMIT 1');
    $stmt->execute([$packId, $raffleId]);
    $price = $stmt->fetchColumn();
    if ($price === false) json_error('Uno de los packs no está disponible.');
    $total += (int)$price;
}

$result = surteados_find_valid_discount($pdo, $code, $email, $total);
if (!$result['valid']) json_error($result['error'] ?? 'Código inválido.');
json_ok($result);