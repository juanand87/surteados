<?php
/** Run once or safely repeat: php api/run_locations_migration.php */
require __DIR__ . '/config.php';
require_once __DIR__ . '/location_helper.php';

try {
    $pdo = db();
    surteados_ensure_locations($pdo);

    $regions = (int)$pdo->query('SELECT COUNT(*) FROM regions')->fetchColumn();
    $communes = (int)$pdo->query('SELECT COUNT(*) FROM communes')->fetchColumn();

    echo "OK: regiones={$regions}, comunas={$communes}, tickets.buyer_commune_id listo" . PHP_EOL;
} catch (Throwable $e) {
    http_response_code(500);
    echo 'ERROR: ' . $e->getMessage() . PHP_EOL;
}
