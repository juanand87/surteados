<?php
/** Run once from terminal: php api/run_analytics_migration.php */
$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'CLI';
require __DIR__ . '/config.php';
require_once __DIR__ . '/analytics_helper.php';

try {
    surteados_ensure_analytics_tables(db());
    echo "OK: analytics tables are ready." . PHP_EOL;
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . PHP_EOL;
    exit(1);
}