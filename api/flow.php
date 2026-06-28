<?php
/** SURTEADOS — Flow.cl payment initiation (single or multi-item cart) */
require __DIR__ . '/config.php';
require __DIR__ . '/FlowAPI.php';
require_once __DIR__ . '/location_helper.php';
require_once __DIR__ . '/wheel_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$b = body();

$buyerName  = trim($b['buyerName']  ?? '');
$buyerRut   = trim($b['buyerRut']   ?? '');
$buyerEmail = trim($b['buyerEmail'] ?? '');
$buyerPhone = trim($b['buyerPhone'] ?? '');
$buyerAddress = trim($b['buyerAddress'] ?? '');
$buyerComuna  = trim($b['buyerComuna']  ?? '');
$buyerCommuneId = $b['buyerCommuneId'] ?? null;
$items = $b['items'] ?? [];
$discountCode = strtoupper(trim((string)($b['discountCode'] ?? '')));

if (!$buyerName || !$buyerEmail) json_error('Datos incompletos: se requiere nombre y email');
if (!$buyerAddress || !$buyerComuna) json_error('Datos incompletos: se requiere dirección y comuna');
if (!filter_var($buyerEmail, FILTER_VALIDATE_EMAIL)) json_error('Email inválido');

if ((!is_array($items) || count($items) === 0) && !empty($b['raffleId']) && !empty($b['packId'])) {
    $items = [[
        'raffleId' => trim($b['raffleId']),
        'packId'   => trim($b['packId']),
    ]];
}
if (!is_array($items) || count($items) === 0) json_error('El carrito está vacío');
if (count($items) > 10) json_error('Máximo 10 sorteos por compra');

$pdo = db();
surteados_ensure_flow_order_number_column($pdo);
surteados_ensure_wheel_tables($pdo);
$buyerCommune = surteados_resolve_commune($pdo, $buyerCommuneId, $buyerComuna);
$buyerComuna = $buyerCommune['name'];
$buyerCommuneId = $buyerCommune['id'];

$resolved = [];
$totalAmount = 0;
foreach ($items as $idx => $item) {
    $raffleId = trim($item['raffleId'] ?? '');
    $packId   = trim($item['packId'] ?? '');
    if (!$raffleId || !$packId) json_error("Item #{$idx}: raffleId y packId son requeridos");

    $stmt = $pdo->prepare('SELECT id, title, status, draw_date FROM raffles WHERE id = ?');
    $stmt->execute([$raffleId]);
    $raffle = $stmt->fetch();
    if (!$raffle || $raffle['status'] !== 'active') json_error('El sorteo "' . $raffleId . '" no está disponible');
    if (raffle_sales_closed($raffle['draw_date'] ?? null)) json_error(raffle_closed_sale_message($raffle['draw_date'] ?? null));

    $stmt = $pdo->prepare('SELECT * FROM raffle_packs WHERE id = ? AND raffle_id = ?');
    $stmt->execute([$packId, $raffleId]);
    $pack = $stmt->fetch();
    if (!$pack) json_error('Pack "' . $packId . '" no encontrado para el sorteo "' . $raffleId . '"');

    $resolved[] = ['raffle' => $raffle, 'pack' => $pack];
    $totalAmount += (int)$pack['price'];
}

$discount = null;
$discountAmount = 0;
$finalAmount = $totalAmount;
if ($discountCode !== '') {
    $discount = surteados_find_valid_discount($pdo, $discountCode, $buyerEmail, $totalAmount);
    if (empty($discount['valid'])) json_error($discount['error'] ?? 'Código de descuento inválido.');
    $discountAmount = (int)$discount['discountAmount'];
    $finalAmount = max(0, $totalAmount - $discountAmount);
}
if ($finalAmount <= 0) json_error('El total a pagar debe ser mayor a $0.');

$stmt = $pdo->query(
    "SELECT `key`, `value` FROM settings
      WHERE `key` IN ('flow_api_key','flow_secret_key','flow_environment','site_url')"
);
$flowCfg = [];
foreach ($stmt->fetchAll() as $row) $flowCfg[$row['key']] = $row['value'];

$apiKey    = $flowCfg['flow_api_key']     ?? '';
$secretKey = $flowCfg['flow_secret_key']  ?? '';
$env       = $flowCfg['flow_environment'] ?? 'sandbox';
$siteUrl   = normalize_site_url($flowCfg['site_url'] ?? BASE_URL);
if (!$apiKey || !$secretKey) json_error('Pasarela de pagos no configurada. Configure las credenciales de Flow.cl en el panel de administración.');

$orderId = generate_id('o');
$ticketIds = [];

try {
    $pdo->beginTransaction();

    if ($discountCode !== '' && !surteados_reserve_discount_code($pdo, $discountCode, $buyerEmail, $orderId)) {
        throw new RuntimeException('El código de descuento ya no está disponible.');
    }

    $stmtInsert = $pdo->prepare(
        'INSERT INTO tickets
          (id, raffle_id, buyer_name, buyer_rut, buyer_email, buyer_phone, buyer_address, buyer_comuna, buyer_commune_id,
           pack_id, pack_label, amount, original_amount, discount_code, discount_amount, payment_method, payment_status, flow_order)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    );

    $remainingDiscount = $discountAmount;
    $lastIndex = count($resolved) - 1;
    foreach ($resolved as $idx => $entry) {
        $ticketId = generate_id('t');
        $ticketIds[] = $ticketId;
        $originalAmount = (int)$entry['pack']['price'];
        if ($discountAmount > 0) {
            $lineDiscount = ($idx === $lastIndex)
                ? $remainingDiscount
                : min($originalAmount, (int)floor($discountAmount * ($originalAmount / max(1, $totalAmount))));
            $remainingDiscount -= $lineDiscount;
        } else {
            $lineDiscount = 0;
        }
        $lineAmount = max(0, $originalAmount - $lineDiscount);

        $stmtInsert->execute([
            $ticketId,
            $entry['raffle']['id'],
            $buyerName,
            $buyerRut,
            $buyerEmail,
            $buyerPhone,
            $buyerAddress,
            $buyerComuna,
            $buyerCommuneId,
            $entry['pack']['id'],
            $entry['pack']['label'],
            $lineAmount,
            $originalAmount,
            $discountCode ?: null,
            $lineDiscount,
            'flow',
            'pending',
            $orderId,
        ]);
    }
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    json_error('No se pudo preparar la compra: ' . $e->getMessage(), 500);
}

try {
    $flow = new FlowAPI($apiKey, $secretKey, $env);
    $subject = count($resolved) > 1
        ? ('Imágenes Surteados (' . count($resolved) . ' sorteos)')
        : ('Imágenes ' . $resolved[0]['raffle']['title']);
    $payment = $flow->createPayment([
        'commerceOrder'   => $orderId,
        'subject'         => $subject,
        'currency'        => 'CLP',
        'amount'          => $finalAmount,
        'email'           => $buyerEmail,
        'urlConfirmation' => $siteUrl . '/api/flow_callback.php',
        'urlReturn'       => $siteUrl . '/pago-exitoso.php?orderId=' . rawurlencode($orderId),
        'paymentMethod'   => 9,
    ]);

    if (empty($payment['token'])) {
        $errMsg = $payment['message'] ?? json_encode($payment);
        throw new RuntimeException('Flow no retornó token: ' . $errMsg);
    }

    $flowOrderNumber = surteados_flow_order_number($payment);
    $pdo->prepare('UPDATE tickets SET flow_token = ?, flow_order = ?, flow_order_number = ? WHERE flow_order = ? AND payment_status = ?')
        ->execute([$payment['token'], $orderId, $flowOrderNumber ?: null, $orderId, 'pending']);

    client_session_start();
    $_SESSION['last_flow_order_id'] = $orderId;
    $_SESSION['last_flow_token'] = $payment['token'];
    $_SESSION['last_flow_email'] = $buyerEmail;

    json_ok([
        'redirectUrl' => $payment['url'] . '?token=' . $payment['token'],
        'token'       => $payment['token'],
        'orderId'     => $orderId,
        'tickets'     => $ticketIds,
        'discount'    => $discount ? [
            'code' => $discountCode,
            'amount' => $discountAmount,
            'totalBefore' => $totalAmount,
            'totalAfter' => $finalAmount,
        ] : null,
    ]);
} catch (Exception $e) {
    surteados_release_discount_code($pdo, $orderId);
    $pdo->prepare('DELETE FROM tickets WHERE flow_order = ? AND payment_status = ?')->execute([$orderId, 'pending']);
    json_error('Error al crear pago: ' . $e->getMessage(), 502);
}

function normalize_site_url(string $url): string {
    $url = trim($url);
    if ($url === '') $url = BASE_URL;
    $url = preg_replace('~/(api/flow_callback\.php|pago-exitoso\.php)(/.*)?$~i', '', $url);
    $url = preg_replace('~/api/?$~i', '', $url);
    return rtrim($url, '/');
}