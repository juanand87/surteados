<?php
/**
 * Central image/ticket number allocator.
 * New numbers use raffleNumber-random8digits and stay unique globally.
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

    surteados_ensure_raffle_number_column($pdo);
    surteados_seed_existing_ticket_numbers($pdo);

    $stmt = $pdo->prepare(
        "INSERT INTO ticket_number_sequence (id, next_number) VALUES (1, 1)
         ON DUPLICATE KEY UPDATE next_number = next_number"
    );
    $stmt->execute();
    $ensured = true;
}

function surteados_ensure_raffle_number_column(PDO $pdo): void
{
    $column = $pdo->query("SHOW COLUMNS FROM raffles LIKE 'raffle_number'")->fetch();
    if (!$column) {
        $pdo->exec("ALTER TABLE raffles ADD COLUMN raffle_number INT UNSIGNED NULL AFTER id");
        surteados_backfill_raffle_numbers($pdo);
    }

    $index = $pdo->query("SHOW INDEX FROM raffles WHERE Key_name = 'uq_raffle_number'")->fetch();
    if (!$index) {
        surteados_backfill_raffle_numbers($pdo);
        $pdo->exec("ALTER TABLE raffles ADD UNIQUE KEY uq_raffle_number (raffle_number)");
    }
}

function surteados_backfill_raffle_numbers(PDO $pdo): void
{
    $next = (int)$pdo->query('SELECT COALESCE(MAX(raffle_number), 0) + 1 FROM raffles')->fetchColumn();
    $rows = $pdo->query(
        "SELECT id
           FROM raffles
          WHERE raffle_number IS NULL
          ORDER BY created_at ASC, id ASC"
    )->fetchAll();
    if (!$rows) return;

    $upd = $pdo->prepare('UPDATE raffles SET raffle_number = ? WHERE id = ? AND raffle_number IS NULL');
    foreach ($rows as $row) {
        $upd->execute([$next, $row['id']]);
        $next++;
    }
}

function surteados_get_raffle_number(PDO $pdo, string $raffleId): int
{
    surteados_ensure_raffle_number_column($pdo);

    $stmt = $pdo->prepare('SELECT raffle_number FROM raffles WHERE id = ? FOR UPDATE');
    $stmt->execute([$raffleId]);
    $current = $stmt->fetchColumn();
    if ($current !== false && (int)$current > 0) {
        return (int)$current;
    }

    $exists = $pdo->prepare('SELECT COUNT(*) FROM raffles WHERE id = ?');
    $exists->execute([$raffleId]);
    if ((int)$exists->fetchColumn() < 1) {
        throw new RuntimeException('Sorteo no encontrado para asignar numero de imagen.');
    }

    $upd = $pdo->prepare('UPDATE raffles SET raffle_number = ? WHERE id = ? AND raffle_number IS NULL');
    for ($attempt = 0; $attempt < 10; $attempt++) {
        $next = (int)$pdo->query('SELECT COALESCE(MAX(raffle_number), 0) + 1 FROM raffles')->fetchColumn();
        try {
            $upd->execute([$next, $raffleId]);
            if ($upd->rowCount() > 0) {
                return $next;
            }
        } catch (Throwable $e) {
            // Another request may have taken the same raffle number. Retry with the next max.
        }

        $stmt->execute([$raffleId]);
        $current = $stmt->fetchColumn();
        if ($current !== false && (int)$current > 0) {
            return (int)$current;
        }
    }

    throw new RuntimeException('No se pudo asignar un numero unico al sorteo.');
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

    $raffleNumber = surteados_get_raffle_number($pdo, $raffleId);
    $numbers = [];
    $insert = $pdo->prepare(
        "INSERT INTO ticket_number_registry (number, ticket_id, raffle_id)
         VALUES (?, ?, ?)"
    );

    $attempts = 0;
    $maxAttempts = max(100, $qty * 50);
    while (count($numbers) < $qty) {
        if ($attempts++ > $maxAttempts) {
            throw new RuntimeException('No se pudo generar un numero unico de imagen. Intenta nuevamente.');
        }

        $series = str_pad((string)random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
        $number = $raffleNumber . '-' . $series;
        try {
            $insert->execute([$number, $ticketId, $raffleId]);
            $numbers[] = $number;
        } catch (Throwable $e) {
            // If the random series already exists, try another one.
        }
    }

    return $numbers;
}
