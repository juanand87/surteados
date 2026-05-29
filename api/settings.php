<?php
/** SURTEADOS — Settings API */
require __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];

// ── GET: return all settings as key→value object ──────────────────────────────
if ($method === 'GET') {
    $rows = db()->query('SELECT `key`, `value` FROM settings')->fetchAll();
    $data = [];
    foreach ($rows as $r) {
        $data[$r['key']] = $r['value'];
    }
    json_ok($data);
}

// ── POST: bulk-upsert settings ────────────────────────────────────────────────
if ($method === 'POST') {
    auth_required();
    $body = body();
    if (!is_array($body)) {
        json_error('Se esperaba un objeto JSON');
    }

    $pdo  = db();
    $stmt = $pdo->prepare(
        'INSERT INTO settings (`key`, `value`) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)'
    );

    $pdo->beginTransaction();
    try {
        foreach ($body as $key => $value) {
            $key = preg_replace('/[^a-z0-9_]/', '', (string)$key); // sanitize key
            if ($key !== '') {
                $stmt->execute([$key, (string)$value]);
            }
        }
        $pdo->commit();
    } catch (\Throwable $e) {
        $pdo->rollBack();
        json_error('Error al guardar configuración: ' . $e->getMessage(), 500);
    }

    json_ok(['saved' => true]);
}
