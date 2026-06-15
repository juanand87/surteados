<?php
require __DIR__ . '/config.php';

$pdo = db();
$cols = $pdo->query("SHOW COLUMNS FROM raffles")->fetchAll();
$existing = array_column($cols, 'Field');

if (!in_array('legal_text', $existing, true)) {
    $pdo->exec("ALTER TABLE raffles ADD COLUMN legal_text TEXT NULL AFTER legal_organizer");
    $pdo->exec("UPDATE raffles SET legal_text = legal_organizer WHERE legal_text IS NULL AND legal_organizer IS NOT NULL AND legal_organizer <> ''");
    echo "OK: legal_text agregado\n";
} else {
    echo "OK: legal_text ya existe\n";
}

if (!in_array('legal_url', $existing, true)) {
    $pdo->exec("ALTER TABLE raffles ADD COLUMN legal_url VARCHAR(500) NULL AFTER legal_text");
    echo "OK: legal_url agregado\n";
} else {
    echo "OK: legal_url ya existe\n";
}
