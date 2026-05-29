<?php
/** One-time migration: add meet_link column to raffles. Safe to run multiple times. */
require __DIR__ . '/config.php';
$pdo = db();
try {
    $pdo->exec('ALTER TABLE raffles ADD COLUMN meet_link VARCHAR(500) DEFAULT NULL');
    echo 'OK: Column meet_link added.';
} catch (PDOException $e) {
    $msg = $e->getMessage();
    if (stripos($msg, 'Duplicate column') !== false || stripos($msg, 'already exists') !== false) {
        echo 'OK: Column already exists.';
    } else {
        http_response_code(500);
        echo 'ERROR: ' . htmlspecialchars($msg);
    }
}
