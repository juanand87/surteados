<?php
/** Run once or safely repeat: php api/run_ticket_numbers_migration.php */
require __DIR__ . '/config.php';
require_once __DIR__ . '/ticket_number_helper.php';

try {
    $pdo = db();
    surteados_ensure_ticket_number_tables($pdo);
    surteados_backfill_raffle_numbers($pdo);
    $registered = (int)$pdo->query('SELECT COUNT(*) FROM ticket_number_registry')->fetchColumn();
    $raffles = (int)$pdo->query('SELECT COUNT(*) FROM raffles WHERE raffle_number IS NOT NULL')->fetchColumn();
    echo "OK: numeros registrados={$registered}, sorteos numerados={$raffles}" . PHP_EOL;
} catch (Throwable $e) {
    http_response_code(500);
    echo 'ERROR: ' . $e->getMessage() . PHP_EOL;
}
