<?php
/** Run once: php api/run_image_labels_migration.php */
$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'CLI';
require __DIR__ . '/config.php';

try {
    $pdo = db();
    $stmt = $pdo->prepare(
        "INSERT INTO settings (`key`, `value`) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)"
    );
    $stmt->execute(['ticket_label', 'imagen']);
    $stmt->execute(['ticket_label_plural', 'imagenes']);
    $stmt->execute(['site_tagline', 'Sorteos online con imágenes numeradas para participar por premios reales']);
    echo "OK: etiquetas y mensaje del sitio actualizados" . PHP_EOL;
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
