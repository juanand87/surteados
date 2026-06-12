<?php
/** SURTEADOS — Flow.cl payment initiation (single or multi-item cart) */
require __DIR__ . '/config.php';
require __DIR__ . '/FlowAPI.php';
require_once __DIR__ . '/location_helper.php';

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
$items      = $b['items'] ?? [];

if (!$buyerName || !$buyerEmail) {
    json_error('Datos incompletos: se requiere nombre y email');
}
if (!$buyerAddress || !$buyerComuna) {
    json_error('Datos incompletos: se requiere dirección y comuna');
}

if (!filter_var($buyerEmail, FILTER_VALIDATE_EMAIL)) {
    json_error('Email inválido');
}

// Backward compatibility: allow legacy single raffle payload
if ((!is_array($items) || count($items) === 0) && !empty($b['raffleId']) && !empty($b['packId'])) {
    $items = [[
        'raffleId' => trim($b['raffleId']),
        'packId'   => trim($b['packId']),
    ]];
}

if (!is_array($items) || count($items) === 0) {
    json_error('El carrito está vacío');
}

if (count($items) > 10) {
    json_error('Máximo 10 sorteos por compra');
}

$pdo = db();
$buyerCommune = surteados_resolve_commune($pdo, $buyerCommuneId, $buyerComuna);
$buyerComuna = $buyerCommune['name'];
$buyerCommuneId = $buyerCommune['id'];

// Resolve cart items
$resolved = [];
$totalAmount = 0;
foreach ($items as $idx => $item) {
    $raffleId = trim($item['raffleId'] ?? '');
    $packId   = trim($item['packId'] ?? '');
    if (!$raffleId || !$packId) {
        json_error("Item #{$idx}: raffleId y packId son requeridos");
    }

    $stmt = $pdo->prepare('SELECT id, title, status, draw_date FROM raffles WHERE id = ?');
    $stmt->execute([$raffleId]);
    $raffle = $stmt->fetch();
    if (!$raffle || $raffle['status'] !== 'active') {
        json_error('El sorteo "' . $raffleId . '" no está disponible');
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

    $resolved[] = ['raffle' => $raffle, 'pack' => $pack];
    $totalAmount += (int)$pack['price'];
}

// Load Flow.cl credentials from settings
$stmt = $pdo->query(
    "SELECT `key`, `value` FROM settings
      WHERE `key` IN ('flow_api_key','flow_secret_key','flow_environment','site_url')"
);
$flowCfg = [];
foreach ($stmt->fetchAll() as $row) {
    $flowCfg[$row['key']] = $row['value'];
}

$apiKey    = $flowCfg['flow_api_key']     ?? '';
$secretKey = $flowCfg['flow_secret_key']  ?? '';
$env       = $flowCfg['flow_environment'] ?? 'sandbox';
$siteUrl   = normalize_site_url($flowCfg['site_url'] ?? BASE_URL);

if (!$apiKey || !$secretKey) {
    json_error('Pasarela de pagos no configurada. Configure las credenciales de Flow.cl en el panel de administración.');
}

// Create pending tickets for the full cart under one order
$orderId = generate_id('o');
$ticketIds = [];

try {
    $pdo->beginTransaction();
    $stmtInsert = $pdo->prepare(
        'INSERT INTO tickets
              (id, raffle_id, buyer_name, buyer_rut, buyer_email, buyer_phone, buyer_address, buyer_comuna, buyer_commune_id,
            pack_id, pack_label, amount, payment_method, payment_status, flow_order)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    );

    foreach ($resolved as $entry) {
        $ticketId = generate_id('t');
        $ticketIds[] = $ticketId;
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
            $entry['pack']['price'],
            'flow',
            'pending',
            $orderId,
        ]);
    }
    $pdo->commit();
} catch (\Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_error('No se pudo preparar la compra: ' . $e->getMessage(), 500);
}

// Call Flow.cl API
try {
    $flow    = new FlowAPI($apiKey, $secretKey, $env);
    $subject = count($resolved) > 1
        ? ('Imágenes Surteados (' . count($resolved) . ' sorteos)')
        : ('Imágenes ' . $resolved[0]['raffle']['title']);
    $payment = $flow->createPayment([
        'commerceOrder'   => $orderId,
        'subject'         => $subject,
        'currency'        => 'CLP',
        'amount'          => $totalAmount,
        'email'           => $buyerEmail,
        'urlConfirmation' => $siteUrl . '/api/flow_callback.php',
        'urlReturn'       => $siteUrl . '/pago-exitoso.php?orderId=' . rawurlencode($orderId),
        'paymentMethod'   => 9,
    ]);

    if (empty($payment['token'])) {
        $errMsg = $payment['message'] ?? json_encode($payment);
        throw new RuntimeException('Flow no retornó token: ' . $errMsg);
    }

    // Save flow references for all pending tickets in this order
    $pdo->prepare('UPDATE tickets SET flow_token = ?, flow_order = ? WHERE flow_order = ? AND payment_status = ?')
        ->execute([$payment['token'], $orderId, $orderId, 'pending']);

    client_session_start();
    $_SESSION['last_flow_order_id'] = $orderId;
    $_SESSION['last_flow_token'] = $payment['token'];
    $_SESSION['last_flow_email'] = $buyerEmail;

    json_ok([
        'redirectUrl' => $payment['url'] . '?token=' . $payment['token'],
        'token'       => $payment['token'],
        'orderId'     => $orderId,
        'tickets'     => $ticketIds,
    ]);
} catch (\Exception $e) {
    // Rollback pending order so it doesn't pollute the DB
    $pdo->prepare('DELETE FROM tickets WHERE flow_order = ? AND payment_status = ?')
        ->execute([$orderId, 'pending']);
    json_error('Error al crear pago: ' . $e->getMessage(), 502);
}

function normalize_site_url(string $url): string {
    $url = trim($url);
    if ($url === '') {
        $url = BASE_URL;
    }

    $url = preg_replace('~/(api/flow_callback\.php|pago-exitoso\.php)(/.*)?$~i', '', $url);
    $url = preg_replace('~/api/?$~i', '', $url);
    return rtrim($url, '/');
}
