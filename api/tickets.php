<?php
/** SURTEADOS — Tickets API */
require __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];

// ── GET: list / search tickets ─────────────────────────────────────────────────
if ($method === 'GET') {
    $pdo    = db();
    $where  = [];
    $params = [];

    // Single ticket with full raffle+prizes data
    if (!empty($_GET['id'])) {
        $stmt = $pdo->prepare(
            'SELECT t.*, r.title AS raffle_title, r.draw_date, r.category,
                    r.image_url AS raffle_image_url, r.image_emoji
               FROM tickets t
               LEFT JOIN raffles r ON r.id = t.raffle_id
              WHERE t.id = ?'
        );
        $stmt->execute([$_GET['id']]);
        $ticket = $stmt->fetch();
        if (!$ticket) json_error('Ticket no encontrado', 404);
        $ticket['ticket_numbers'] = json_decode($ticket['ticket_numbers'] ?? '[]', true) ?? [];

        // Fetch prizes for the raffle
        $stmtP = $pdo->prepare('SELECT * FROM raffle_prizes WHERE raffle_id = ? ORDER BY place');
        $stmtP->execute([$ticket['raffle_id']]);
        $ticket['prizes'] = $stmtP->fetchAll();

        json_ok($ticket);
    }

    if (!empty($_GET['raffle_id'])) {
        $where[]  = 't.raffle_id = ?';
        $params[] = $_GET['raffle_id'];
    }
    if (!empty($_GET['my'])) {
        $where[]  = 't.buyer_email = ?';
        $params[] = client_auth_required();
    }
    if (!empty($_GET['email'])) {
        auth_required();
        $where[]  = 't.buyer_email = ?';
        $params[] = $_GET['email'];
    }
    if (!empty($_GET['flow_token'])) {
        $where[]  = 't.flow_token = ?';
        $params[] = $_GET['flow_token'];
    }
    if (!empty($_GET['status'])) {
        $where[]  = 't.payment_status = ?';
        $params[] = $_GET['status'];
    }

    $sql  = 'SELECT t.*, r.title AS raffle_title
               FROM tickets t
               LEFT JOIN raffles r ON r.id = t.raffle_id';
    if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
    $sql .= ' ORDER BY t.purchase_date DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $tickets = $stmt->fetchAll();

    foreach ($tickets as &$ticket) {
        $ticket['ticket_numbers'] = json_decode($ticket['ticket_numbers'] ?? '[]', true) ?? [];
    }

    json_ok($tickets);
}

// ── POST: create ticket (admin) ────────────────────────────────────────────────
if ($method === 'POST') {
    auth_required();
    $b = body();

    $required = ['raffle_id', 'buyer_name', 'buyer_email', 'amount'];
    foreach ($required as $f) {
        if (empty($b[$f])) json_error("Campo requerido: $f");
    }

    $id = generate_id('t');
    $qty = (int)($b['quantity'] ?? 1);
    $numbers = [];

    $pdo = db();

    // Generate unique ticket numbers
    $existingStmt = $pdo->prepare(
        "SELECT ticket_numbers FROM tickets WHERE raffle_id = ? AND payment_status = 'paid'"
    );
    $existingStmt->execute([$b['raffle_id']]);
    $existing = [];
    foreach ($existingStmt->fetchAll() as $row) {
        $nums = json_decode($row['ticket_numbers'], true) ?? [];
        $existing = array_merge($existing, $nums);
    }
    $existingSet = array_flip($existing);

    $attempts = 0;
    while (count($numbers) < $qty && $attempts < 50000) {
        $num = str_pad(rand(1, 99999), 6, '0', STR_PAD_LEFT);
        if (!isset($existingSet[$num]) && !in_array($num, $numbers)) {
            $numbers[] = $num;
        }
        $attempts++;
    }

    $pdo->prepare(
        'INSERT INTO tickets
           (id, raffle_id, buyer_name, buyer_rut, buyer_email, buyer_phone, buyer_address, buyer_comuna, pack_id, pack_label, amount,
            payment_method, payment_status, ticket_numbers)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    )->execute([
        $id,
        $b['raffle_id'],
        $b['buyer_name'],
        $b['buyer_rut']   ?? '',
        $b['buyer_email'],
        $b['buyer_phone'] ?? '',
        $b['buyer_address'] ?? '',
        $b['buyer_comuna']  ?? '',
        $b['pack_id']     ?? '',
        $b['pack_label']  ?? '',
        $b['amount'],
        $b['payment_method']  ?? 'admin',
        $b['payment_status']  ?? 'paid',
        json_encode($numbers),
    ]);

    // Update sold tickets count
    $pdo->prepare('UPDATE raffles SET sold_tickets = sold_tickets + ? WHERE id = ?')
        ->execute([$qty, $b['raffle_id']]);

    json_ok(['id' => $id, 'ticket_numbers' => $numbers]);
}

// ── PUT: update ticket status ──────────────────────────────────────────────────
if ($method === 'PUT') {
    auth_required();
    $b  = body();
    $id = $_GET['id'] ?? $b['id'] ?? '';
    if (!$id) json_error('ID requerido');

    $allowed = ['pending', 'paid', 'failed', 'refunded'];
    $status  = $b['payment_status'] ?? '';
    if (!in_array($status, $allowed)) json_error('Estado inválido');

    $affected = db()->prepare('UPDATE tickets SET payment_status = ? WHERE id = ?');
    $affected->execute([$status, $id]);

    if ($affected->rowCount() === 0) json_error('Ticket no encontrado', 404);
    json_ok(['updated' => true]);
}
