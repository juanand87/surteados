<?php
/** SURTEADOS — Raffles API (includes prizes & packs nested operations) */
require __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];

// ── GET ───────────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    $pdo = db();
    $id  = $_GET['id'] ?? '';

    if ($id) {
        // Single raffle with prizes + packs
        $stmt = $pdo->prepare('SELECT * FROM raffles WHERE id = ?');
        $stmt->execute([$id]);
        $raffle = $stmt->fetch();
        if (!$raffle) json_error('Sorteo no encontrado', 404);

        $stmtP = $pdo->prepare('SELECT * FROM raffle_prizes WHERE raffle_id = ? ORDER BY place');
        $stmtP->execute([$id]);
        $raffle['prizes'] = $stmtP->fetchAll();

        $stmtPk = $pdo->prepare('SELECT * FROM raffle_packs WHERE raffle_id = ? ORDER BY id');
        $stmtPk->execute([$id]);
        $raffle['packs'] = $stmtPk->fetchAll();

        json_ok($raffle);
    }

    // All raffles
    $raffles = $pdo->query('SELECT * FROM raffles ORDER BY created_at DESC')->fetchAll();

    foreach ($raffles as &$r) {
        $stmtP = $pdo->prepare('SELECT * FROM raffle_prizes WHERE raffle_id = ? ORDER BY place');
        $stmtP->execute([$r['id']]);
        $r['prizes'] = $stmtP->fetchAll();

        $stmtPk = $pdo->prepare('SELECT * FROM raffle_packs WHERE raffle_id = ? ORDER BY id');
        $stmtPk->execute([$r['id']]);
        $r['packs'] = $stmtPk->fetchAll();
    }

    json_ok($raffles);
}

// ── POST: create raffle ───────────────────────────────────────────────────────
if ($method === 'POST') {
    auth_required();
    $b = body();

    $required = ['title', 'description', 'category'];
    foreach ($required as $f) {
        if (empty($b[$f])) json_error("Campo requerido: $f");
    }
    if (empty($b['draw_date']) && empty($b['end_date'])) {
        json_error('Campo requerido: draw_date');
    }

    $pdo = db();
    $id  = generate_id('r');
    $totalTickets = isset($b['total_tickets']) && $b['total_tickets'] !== '' && (int)$b['total_tickets'] > 0
        ? (int)$b['total_tickets']
        : null;

    $pdo->prepare(
        'INSERT INTO raffles
           (id, title, description, category, status, total_tickets, sold_tickets,
            draw_date, image_url, image_emoji, legal_organizer, featured, meet_link)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
    )->execute([
        $id,
        $b['title'],
        $b['description'] ?? '',
        $b['category'],
        $b['status']       ?? 'soon',
        $totalTickets,
        0,
        $b['draw_date'] ?? ($b['end_date'] ?? null),
        $b['image_url']       ?? '',
        $b['image_emoji']     ?? '🎁',
        $b['legal_text']      ?? '',
        !empty($b['featured']) ? 1 : 0,
        $b['meet_link']       ?? null,
    ]);

    savePrizesAndPacks($pdo, $id, $b['prizes'] ?? [], $b['packs'] ?? []);

    json_ok(['id' => $id]);
}

// ── PUT: update raffle ────────────────────────────────────────────────────────
if ($method === 'PUT') {
    auth_required();
    $b  = body();
    $id = $_GET['id'] ?? $b['id'] ?? '';
    if (!$id) json_error('ID requerido');

    $pdo = db();
    $totalTickets = isset($b['total_tickets']) && $b['total_tickets'] !== '' && (int)$b['total_tickets'] > 0
        ? (int)$b['total_tickets']
        : null;

    // Check exists
    $check = $pdo->prepare('SELECT id FROM raffles WHERE id = ?');
    $check->execute([$id]);
    if (!$check->fetch()) json_error('Sorteo no encontrado', 404);

    $pdo->prepare(
        'UPDATE raffles SET
           title=?, description=?, category=?, status=?, total_tickets=?,
           draw_date=?, image_url=?, image_emoji=?, legal_organizer=?, featured=?,
           meet_link=?
         WHERE id=?'
    )->execute([
        $b['title']        ?? '',
        $b['description']  ?? '',
        $b['category']     ?? '',
        $b['status']       ?? 'soon',
        $totalTickets,
        $b['draw_date'] ?? ($b['end_date'] ?? null),
        $b['image_url']       ?? '',
        $b['image_emoji']     ?? '🎁',
        $b['legal_text']      ?? '',
        !empty($b['featured']) ? 1 : 0,
        $b['meet_link']       ?? null,
        $id,
    ]);

    // Replace prizes and packs
    $pdo->prepare('DELETE FROM raffle_prizes WHERE raffle_id = ?')->execute([$id]);
    $pdo->prepare('DELETE FROM raffle_packs  WHERE raffle_id = ?')->execute([$id]);
    savePrizesAndPacks($pdo, $id, $b['prizes'] ?? [], $b['packs'] ?? []);

    json_ok(['updated' => true]);
}

// ── DELETE ────────────────────────────────────────────────────────────────────
if ($method === 'DELETE') {
    auth_required();
    $id = $_GET['id'] ?? '';
    if (!$id) json_error('ID requerido');

    $pdo = db();
    $pdo->prepare('DELETE FROM raffle_prizes WHERE raffle_id = ?')->execute([$id]);
    $pdo->prepare('DELETE FROM raffle_packs  WHERE raffle_id = ?')->execute([$id]);

    $affected = $pdo->prepare('DELETE FROM raffles WHERE id = ?');
    $affected->execute([$id]);

    if ($affected->rowCount() === 0) json_error('Sorteo no encontrado', 404);
    json_ok(['deleted' => true]);
}

// ── Helper ────────────────────────────────────────────────────────────────────
function savePrizesAndPacks(PDO $pdo, string $raffleId, array $prizes, array $packs): void
{
    if (count($prizes) > 1) {
        $prizes = [reset($prizes)];
    }
    $stmtPrize = $pdo->prepare(
        'INSERT INTO raffle_prizes (id, raffle_id, place, name, value, emoji, image_url)
         VALUES (?,?,?,?,?,?,?)'
    );
    foreach ($prizes as $i => $p) {
        $stmtPrize->execute([
            generate_id('p'),
            $raffleId,
            (int)($p['place'] ?? $i + 1),
            $p['label'] ?? $p['name'] ?? '',
            (int)($p['value'] ?? 0),
            $p['emoji']     ?? '🏆',
            $p['image_url'] ?? '',
        ]);
    }

    $stmtPack = $pdo->prepare(
        'INSERT INTO raffle_packs (id, raffle_id, label, qty, price, original_price, discount, best_value)
         VALUES (?,?,?,?,?,?,?,?)'
    );
    foreach ($packs as $i => $pk) {
        $stmtPack->execute([
            generate_id('pk'),
            $raffleId,
            $pk['label']          ?? '',
            (int)($pk['qty'] ?? $pk['quantity'] ?? 1),
            (int)($pk['price']    ?? 0),
            (int)($pk['original_price'] ?? $pk['originalPrice'] ?? $pk['price'] ?? 0),
            (int)($pk['discount'] ?? 0),
            !empty($pk['best_value']) || !empty($pk['bestValue']) ? 1 : 0,
        ]);
    }
}
