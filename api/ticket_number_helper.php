<?php
/**
 * Central sequential image/ticket number allocator.
 * Numbers are unique globally across all raffles and all payment methods.
 */

function surteados_ensure_ticket_number_tables(PDO $pdo): void
{
    static $ensured = false;
    if ($ensured) return;

    $pdo->exec("CREATE TABLE IF NOT EXISTS ticket_number_sequence (
        id TINYINT UNSIGNED PRIMARY KEY,
        next_number BIGINT UNSIGNED NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS ticket_number_registry (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        number VARCHAR(30) NOT NULL,
        ticket_id VARCHAR(25) NULL,
        raffle_id VARCHAR(25) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_ticket_number (number),
        INDEX idx_ticket_id (ticket_id),
        INDEX idx_raffle_id (raffle_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    surteados_seed_existing_ticket_numbers($pdo);

    $max = (int)$pdo->query('SELECT COALESCE(MAX(CAST(number AS UNSIGNED)), 0) FROM ticket_number_registry')->fetchColumn();
    $next = max(1, $max + 1);
    $stmt = $pdo->prepare(
        "INSERT INTO ticket_number_sequence (id, next_number) VALUES (1, ?)
         ON DUPLICATE KEY UPDATE next_number = GREATEST(next_number, VALUES(next_number))"
    );
    $stmt->execute([$next]);
    $ensured = true;
}

function surteados_seed_existing_ticket_numbers(PDO $pdo): void
{
    static $seeded = false;
    if ($seeded) return;
    $seeded = true;

    $stmt = $pdo->query(
        "SELECT id, raffle_id, ticket_numbers
           FROM tickets
          WHERE ticket_numbers IS NOT NULL
            AND ticket_numbers <> ''"
    );
    $ins = $pdo->prepare(
        "INSERT IGNORE INTO ticket_number_registry (number, ticket_id, raffle_id)
         VALUES (?, ?, ?)"
    );
    foreach ($stmt->fetchAll() as $row) {
        $numbers = json_decode($row['ticket_numbers'] ?? '[]', true) ?: [];
        foreach ($numbers as $number) {
            $number = trim((string)$number);
            if ($number === '') continue;
            $ins->execute([$number, $row['id'] ?? null, $row['raffle_id'] ?? null]);
        }
    }
}

function surteados_allocate_ticket_numbers(PDO $pdo, string $ticketId, string $raffleId, int $qty): array
{
    if ($qty < 1) return [];
    surteados_ensure_ticket_number_tables($pdo);

    $seq = $pdo->query('SELECT next_number FROM ticket_number_sequence WHERE id = 1 FOR UPDATE')->fetch();
    $next = max(1, (int)($seq['next_number'] ?? 1));
    $numbers = [];
    $insert = $pdo->prepare(
        "INSERT INTO ticket_number_registry (number, ticket_id, raffle_id)
         VALUES (?, ?, ?)"
    );

    while (count($numbers) < $qty) {
        $number = str_pad((string)$next, 6, '0', STR_PAD_LEFT);
        $next++;
        try {
            $insert->execute([$number, $ticketId, $raffleId]);
            $numbers[] = $number;
        } catch (Throwable $e) {
            // In case legacy data already used this number, skip and keep moving.
        }
    }

    $upd = $pdo->prepare('UPDATE ticket_number_sequence SET next_number = ? WHERE id = 1');
    $upd->execute([$next]);
    return $numbers;
}
