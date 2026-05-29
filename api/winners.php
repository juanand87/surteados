<?php
/** SURTEADOS — Winners API */
require __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];

// ── GET: list winners (optionally by raffle_id) ────────────────────────────────
if ($method === 'GET') {
    $raffleId = $_GET['raffle_id'] ?? '';
    if ($raffleId) {
        $stmt = db()->prepare(
            'SELECT * FROM winners
              WHERE raffle_id = ?
              ORDER BY draw_date DESC'
        );
        $stmt->execute([$raffleId]);
    } else {
        $stmt = db()->query(
            'SELECT * FROM winners ORDER BY draw_date DESC'
        );
    }
    json_ok($stmt->fetchAll());
}

// ── POST: create winner ────────────────────────────────────────────────────────
if ($method === 'POST') {
    auth_required();
    $b = body();

    $required = ['raffle_id', 'winner_name', 'prize', 'ticket_number', 'draw_date'];
    foreach ($required as $f) {
        if (empty($b[$f]) && empty($b['won_date'])) {
            if ($f === 'draw_date' && !empty($b['won_date'])) continue;
            if ($f !== 'draw_date') json_error("Campo requerido: $f");
        }
    }
    if (empty($b['draw_date']) && empty($b['won_date'])) {
        json_error('Campo requerido: draw_date');
    }

    $videoUrl = trim((string)($b['video_url'] ?? ''));
    if ($videoUrl !== '') {
        if (!filter_var($videoUrl, FILTER_VALIDATE_URL)) {
            json_error('El video debe ser una URL válida de YouTube');
        }
        $parts = parse_url($videoUrl);
        $host = strtolower($parts['host'] ?? '');
        $host = preg_replace('/^www\./', '', $host);
        $allowed = ['youtube.com', 'm.youtube.com', 'youtu.be'];
        if (!in_array($host, $allowed, true)) {
            json_error('El video debe ser un enlace de YouTube válido');
        }
    }

    $drawDate = $b['draw_date'] ?? $b['won_date'];
    $id = generate_id('w');
    db()->prepare(
        'INSERT INTO winners (id, raffle_id, winner_name, prize, ticket_number, draw_date, video_url, winner_image_url)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        $id,
        $b['raffle_id'],
        $b['winner_name'],
        $b['prize'],
        $b['ticket_number'],
        $drawDate,
        $videoUrl !== '' ? $videoUrl : null,
        $b['winner_image_url'] ?? null,
    ]);

    json_ok(['id' => $id]);
}

// ── DELETE: remove winner ──────────────────────────────────────────────────────
if ($method === 'DELETE') {
    auth_required();
    $id = $_GET['id'] ?? '';
    if (!$id) json_error('ID requerido');

    $affected = db()->prepare('DELETE FROM winners WHERE id = ?');
    $affected->execute([$id]);

    if ($affected->rowCount() === 0) json_error('Ganador no encontrado', 404);
    json_ok(['deleted' => true]);
}
