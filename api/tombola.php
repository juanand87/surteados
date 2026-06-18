<?php
/** SURTEADOS - Tombola API (admin only) */
require __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];
$pdo = db();

function tombola_ensure_audit_table(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS tombola_audits (
        id VARCHAR(25) PRIMARY KEY,
        raffle_id VARCHAR(25) NOT NULL,
        pool_count INT UNSIGNED NOT NULL DEFAULT 0,
        pool_hash CHAR(64) NOT NULL,
        semifinalists_json LONGTEXT NULL,
        finalists_json LONGTEXT NULL,
        winner_json LONGTEXT NULL,
        algorithm VARCHAR(120) NOT NULL DEFAULT 'Fisher-Yates + random_int',
        result_hash CHAR(64) NULL,
        status ENUM('semifinalists','finalists','completed') NOT NULL DEFAULT 'semifinalists',
        created_by VARCHAR(120) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_raffle (raffle_id),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function tombola_shuffle_secure(array $items): array
{
    $n = count($items);
    for ($i = $n - 1; $i > 0; $i--) {
        $j = random_int(0, $i);
        [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
    }
    return $items;
}

function tombola_hash_payload(array $payload): string
{
    return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function tombola_ticket_pool(PDO $pdo, string $raffleId): array
{
    $ticketsStmt = $pdo->prepare(
        "SELECT id, buyer_name, buyer_email, buyer_comuna, ticket_numbers
           FROM tickets
          WHERE raffle_id = ? AND payment_status = 'paid'"
    );
    $ticketsStmt->execute([$raffleId]);

    $pool = [];
    $seen = [];
    foreach ($ticketsStmt->fetchAll() as $ticket) {
        $numbers = json_decode($ticket['ticket_numbers'] ?? '[]', true);
        if (!is_array($numbers)) continue;
        foreach ($numbers as $number) {
            $num = trim((string)$number);
            if ($num === '' || isset($seen[$num])) continue;
            $seen[$num] = true;
            $pool[] = [
                'number' => $num,
                'buyer_name' => (string)($ticket['buyer_name'] ?? ''),
                'buyer_email' => (string)($ticket['buyer_email'] ?? ''),
                'buyer_comuna' => (string)($ticket['buyer_comuna'] ?? ''),
                'ticket_id' => (string)($ticket['id'] ?? ''),
            ];
        }
    }
    return $pool;
}

function tombola_load_audit(PDO $pdo, string $auditId, string $raffleId): array
{
    $stmt = $pdo->prepare('SELECT * FROM tombola_audits WHERE id = ? AND raffle_id = ? LIMIT 1');
    $stmt->execute([$auditId, $raffleId]);
    $audit = $stmt->fetch();
    if (!$audit) json_error('Acta de tombola no encontrada', 404);
    return $audit;
}

function tombola_decode_list(?string $json, string $label): array
{
    $data = json_decode($json ?? '[]', true);
    if (!is_array($data) || !$data) json_error("No hay {$label} registrados para continuar");
    return $data;
}

tombola_ensure_audit_table($pdo);

if ($method === 'GET') {
    auth_required();

    $rows = $pdo->query(
        "SELECT r.id, r.title, r.status, r.draw_date,
                (SELECT COUNT(*) FROM winners w WHERE w.raffle_id = r.id) AS has_winner,
                (SELECT COUNT(*) FROM tombola_audits a WHERE a.raffle_id = r.id AND a.status = 'completed') AS audits_completed
           FROM raffles r
          ORDER BY r.draw_date DESC, r.created_at DESC"
    )->fetchAll();

    foreach ($rows as &$row) {
        $pool = tombola_ticket_pool($pdo, (string)$row['id']);
        $row['paid_images'] = count($pool);
        $row['has_winner'] = (int)$row['has_winner'] > 0;
        $row['audits_completed'] = (int)$row['audits_completed'];
        $row['locked'] = false;
    }

    json_ok($rows);
}

if ($method === 'POST') {
    auth_required();
    $b = body();
    $raffleId = trim((string)($b['raffle_id'] ?? ''));
    $stage = trim((string)($b['stage'] ?? 'semifinalists'));
    $auditId = trim((string)($b['audit_id'] ?? ''));
    if ($raffleId === '') json_error('raffle_id requerido');

    $raffleStmt = $pdo->prepare('SELECT id, title, draw_date FROM raffles WHERE id = ?');
    $raffleStmt->execute([$raffleId]);
    $raffle = $raffleStmt->fetch();
    if (!$raffle) json_error('Sorteo no encontrado', 404);

    if ($stage === 'semifinalists') {
        $pool = tombola_ticket_pool($pdo, $raffleId);
        if (count($pool) < 1) {
            json_error('No hay imagenes pagadas para este sorteo');
        }

        $shuffled = tombola_shuffle_secure($pool);
        $semifinalists = array_slice($shuffled, 0, min(50, count($shuffled)));
        $auditId = generate_id('ta');
        $poolHash = tombola_hash_payload(array_column($pool, 'number'));
        $resultHash = tombola_hash_payload([
            'audit_id' => $auditId,
            'raffle_id' => $raffleId,
            'pool_hash' => $poolHash,
            'semifinalists' => array_column($semifinalists, 'number'),
            'stage' => 'semifinalists',
        ]);

        $ins = $pdo->prepare(
            'INSERT INTO tombola_audits
               (id, raffle_id, pool_count, pool_hash, semifinalists_json, result_hash, status, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $ins->execute([
            $auditId,
            $raffleId,
            count($pool),
            $poolHash,
            json_encode($semifinalists, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $resultHash,
            'semifinalists',
            $_SESSION['admin_username'] ?? null,
        ]);

        json_ok([
            'audit_id' => $auditId,
            'raffle' => $raffle,
            'pool_size' => count($pool),
            'pool' => $shuffled,
            'semifinalists' => $semifinalists,
            'result_hash' => $resultHash,
            'saved' => true,
            'blocked' => false,
        ]);
    }

    if ($stage === 'finalists') {
        if ($auditId === '') json_error('audit_id requerido');
        $audit = tombola_load_audit($pdo, $auditId, $raffleId);
        $semifinalists = tombola_decode_list($audit['semifinalists_json'] ?? '', 'semifinalistas');
        $shuffled = tombola_shuffle_secure($semifinalists);
        $finalists = array_slice($shuffled, 0, min(5, count($shuffled)));
        $resultHash = tombola_hash_payload([
            'audit_id' => $auditId,
            'raffle_id' => $raffleId,
            'pool_hash' => $audit['pool_hash'],
            'semifinalists' => array_column($semifinalists, 'number'),
            'finalists' => array_column($finalists, 'number'),
            'stage' => 'finalists',
        ]);

        $upd = $pdo->prepare(
            "UPDATE tombola_audits
                SET finalists_json = ?, result_hash = ?, status = 'finalists'
              WHERE id = ?"
        );
        $upd->execute([
            json_encode($finalists, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $resultHash,
            $auditId,
        ]);

        json_ok([
            'audit_id' => $auditId,
            'raffle' => $raffle,
            'semifinalists' => $shuffled,
            'finalists' => $finalists,
            'result_hash' => $resultHash,
            'saved' => true,
            'blocked' => false,
        ]);
    }

    if ($stage === 'winner') {
        if ($auditId === '') json_error('audit_id requerido');
        $audit = tombola_load_audit($pdo, $auditId, $raffleId);
        $semifinalists = tombola_decode_list($audit['semifinalists_json'] ?? '', 'semifinalistas');
        $finalists = tombola_decode_list($audit['finalists_json'] ?? '', 'finalistas');
        $shuffled = tombola_shuffle_secure($finalists);
        $winner = $shuffled[0];

        $prizeStmt = $pdo->prepare('SELECT name FROM raffle_prizes WHERE raffle_id = ? ORDER BY place ASC LIMIT 1');
        $prizeStmt->execute([$raffleId]);
        $prize = (string)($prizeStmt->fetchColumn() ?: ('Ganador de ' . $raffle['title']));

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

        $resultHash = tombola_hash_payload([
            'audit_id' => $auditId,
            'raffle_id' => $raffleId,
            'pool_hash' => $audit['pool_hash'],
            'semifinalists' => array_column($semifinalists, 'number'),
            'finalists' => array_column($finalists, 'number'),
            'winner' => $winner['number'],
            'winner_id' => $winnerId,
            'stage' => 'winner',
        ]);

        $upd = $pdo->prepare(
            "UPDATE tombola_audits
                SET winner_json = ?, result_hash = ?, status = 'completed'
              WHERE id = ?"
        );
        $upd->execute([
            json_encode($winner, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $resultHash,
            $auditId,
        ]);

        json_ok([
            'audit_id' => $auditId,
            'raffle' => $raffle,
            'semifinalists' => $semifinalists,
            'finalists' => $shuffled,
            'winner' => $winner,
            'winner_id' => $winnerId,
            'result_hash' => $resultHash,
            'saved' => true,
            'blocked' => false,
        ]);
    }

    json_error('Etapa de tombola no valida', 400);
}

if ($method === 'DELETE') {
    auth_required();
    $b = body();
    $raffleId = trim((string)($b['raffle_id'] ?? ''));
    if ($raffleId === '') json_error('raffle_id requerido');

    $del = $pdo->prepare('DELETE FROM winners WHERE raffle_id = ?');
    $del->execute([$raffleId]);
    $delAudit = $pdo->prepare('DELETE FROM tombola_audits WHERE raffle_id = ?');
    $delAudit->execute([$raffleId]);
    json_ok(['deleted' => $del->rowCount(), 'audits_deleted' => $delAudit->rowCount(), 'raffle_id' => $raffleId]);
}

json_error('Method not allowed', 405);
