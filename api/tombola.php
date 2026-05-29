<?php
/** SURTEADOS — Tombola API (admin only) */
require __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];
$pdo = db();

function shuffle_secure(array $items): array {
    $n = count($items);
    for ($i = $n - 1; $i > 0; $i--) {
        $j = random_int(0, $i);
        $tmp = $items[$i];
        $items[$i] = $items[$j];
        $items[$j] = $tmp;
    }
    return $items;
}

if ($method === 'GET') {
    auth_required();

    $rows = $pdo->query(
        "SELECT r.id, r.title, r.status, r.draw_date,
                (SELECT COUNT(*) FROM winners w WHERE w.raffle_id = r.id) AS has_winner,
                (SELECT COUNT(*) FROM tickets t WHERE t.raffle_id = r.id AND t.payment_status = 'paid') AS paid_orders
           FROM raffles r
          ORDER BY r.draw_date DESC, r.created_at DESC"
    )->fetchAll();

    $countStmt = $pdo->prepare("SELECT ticket_numbers FROM tickets WHERE raffle_id = ? AND payment_status = 'paid'");
    foreach ($rows as &$row) {
        $countStmt->execute([$row['id']]);
        $images = 0;
        foreach ($countStmt->fetchAll() as $t) {
            $nums = json_decode($t['ticket_numbers'] ?? '[]', true);
            if (is_array($nums)) $images += count($nums);
        }
        $row['paid_images'] = $images;
        $row['locked'] = (int)$row['has_winner'] > 0;
    }

    json_ok($rows);
}

if ($method === 'POST') {
    auth_required();
    $b = body();
    $raffleId = trim((string)($b['raffle_id'] ?? ''));
    if ($raffleId === '') json_error('raffle_id requerido');

    $checkWinner = $pdo->prepare('SELECT id FROM winners WHERE raffle_id = ? LIMIT 1');
    $checkWinner->execute([$raffleId]);
    if ($checkWinner->fetch()) {
        json_error('Este sorteo ya tiene ganador y la tómbola está bloqueada');
    }

    $raffleStmt = $pdo->prepare('SELECT id, title, draw_date FROM raffles WHERE id = ?');
    $raffleStmt->execute([$raffleId]);
    $raffle = $raffleStmt->fetch();
    if (!$raffle) json_error('Sorteo no encontrado', 404);

    $prizeStmt = $pdo->prepare('SELECT name FROM raffle_prizes WHERE raffle_id = ? ORDER BY place ASC LIMIT 1');
    $prizeStmt->execute([$raffleId]);
    $prize = (string)($prizeStmt->fetchColumn() ?: ('Ganador de ' . $raffle['title']));

    $ticketsStmt = $pdo->prepare(
        "SELECT id, buyer_name, buyer_email, buyer_comuna, ticket_numbers
           FROM tickets
          WHERE raffle_id = ? AND payment_status = 'paid'"
    );
    $ticketsStmt->execute([$raffleId]);

    $pool = [];
    foreach ($ticketsStmt->fetchAll() as $ticket) {
        $numbers = json_decode($ticket['ticket_numbers'] ?? '[]', true);
        if (!is_array($numbers)) continue;
        foreach ($numbers as $number) {
            $num = trim((string)$number);
            if ($num === '') continue;
            $pool[] = [
                'number' => $num,
                'buyer_name' => (string)($ticket['buyer_name'] ?? ''),
                'buyer_email' => (string)($ticket['buyer_email'] ?? ''),
                'buyer_comuna' => (string)($ticket['buyer_comuna'] ?? ''),
                'ticket_id' => (string)($ticket['id'] ?? ''),
            ];
        }
    }

    if (count($pool) < 1) {
        json_error('No hay imagenes pagadas para este sorteo');
    }

    $pool = shuffle_secure($pool);
    $top10 = array_slice($pool, 0, min(10, count($pool)));
    $top10 = shuffle_secure($top10);
    $top5 = array_slice($top10, 0, min(5, count($top10)));
    $top5 = shuffle_secure($top5);
    $top3 = array_slice($top5, 0, min(3, count($top5)));
    $top3 = shuffle_secure($top3);
    $winner = $top3[random_int(0, count($top3) - 1)];

    $winnerId = generate_id('w');
    $drawDate = date('Y-m-d');

    $pdo->prepare(
        'INSERT INTO winners
           (id, raffle_id, raffle_title, winner_name, winner_location, prize, ticket_number, draw_date, verified)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)'
    )->execute([
        $winnerId,
        $raffleId,
        $raffle['title'],
        $winner['buyer_name'] !== '' ? $winner['buyer_name'] : 'Ganador',
        $winner['buyer_comuna'] !== '' ? $winner['buyer_comuna'] : null,
        $prize,
        $winner['number'],
        $drawDate,
    ]);

    json_ok([
        'raffle' => [
            'id' => $raffle['id'],
            'title' => $raffle['title'],
            'draw_date' => $raffle['draw_date'],
        ],
        'pool_size' => count($pool),
        'round10' => $top10,
        'round5' => $top5,
        'round3' => $top3,
        'winner' => $winner,
        'winner_id' => $winnerId,
        'saved' => true,
        'blocked' => true,
    ]);
}

if ($method === 'DELETE') {
    auth_required();
    $b = body();
    $raffleId = trim((string)($b['raffle_id'] ?? ''));
    if ($raffleId === '') json_error('raffle_id requerido');

    $del = $pdo->prepare('DELETE FROM winners WHERE raffle_id = ?');
    $del->execute([$raffleId]);
    json_ok(['deleted' => $del->rowCount(), 'raffle_id' => $raffleId]);
}

json_error('Method not allowed', 405);
