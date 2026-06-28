<?php
require __DIR__ . '/config.php';
require_once __DIR__ . '/wheel_helper.php';

try {
    surteados_ensure_wheel_tables(db());
    echo "OK: wheel_prizes, wheel_spins, discount_codes and ticket discount columns are ready." . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: " . $e->getMessage() . PHP_EOL);
    exit(1);
}