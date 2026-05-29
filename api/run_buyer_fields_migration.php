<?php
/** Run once: php api/run_buyer_fields_migration.php */
$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'CLI';
require __DIR__ . '/config.php';

$pdo = db();

function addColumnIfMissing(PDO $pdo, string $table, string $column, string $definition): void {
    $sql = "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$table, $column]);
    $exists = (int)$stmt->fetchColumn() > 0;
    if (!$exists) {
        $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
        echo "OK: {$table}.{$column} added" . PHP_EOL;
    } else {
        echo "OK: {$table}.{$column} already exists" . PHP_EOL;
    }
}

try {
    addColumnIfMissing($pdo, 'tickets', 'buyer_rut', 'VARCHAR(30) NULL AFTER buyer_name');
    addColumnIfMissing($pdo, 'tickets', 'buyer_address', 'VARCHAR(255) NULL AFTER buyer_phone');
    addColumnIfMissing($pdo, 'tickets', 'buyer_comuna', 'VARCHAR(120) NULL AFTER buyer_address');

    $pdo->exec('ALTER TABLE raffles MODIFY total_tickets INT NULL DEFAULT NULL');
    echo "OK: raffles.total_tickets now supports NULL (ilimitado)" . PHP_EOL;
} catch (Throwable $e) {
    echo 'ERROR: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}
