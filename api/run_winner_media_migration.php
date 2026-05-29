<?php
/** Run once: php api/run_winner_media_migration.php */
$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'CLI';
require __DIR__ . '/config.php';

try {
    $pdo = db();

    $check = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'winners' AND COLUMN_NAME = 'winner_image_url'");
    $check->execute();
    $exists = (int)$check->fetchColumn() > 0;

    if (!$exists) {
        $pdo->exec("ALTER TABLE winners ADD COLUMN winner_image_url VARCHAR(500) NULL AFTER verified");
        echo "OK: winners.winner_image_url added" . PHP_EOL;
    } else {
        echo "OK: winners.winner_image_url already exists" . PHP_EOL;
    }
} catch (Throwable $e) {
    echo 'ERROR: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}
