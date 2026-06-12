<?php
/** SURTEADOS - Chile regions and communes */
require __DIR__ . '/config.php';
require_once __DIR__ . '/location_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Method not allowed', 405);
}

$pdo = db();
surteados_ensure_locations($pdo);

$regions = $pdo->query(
    'SELECT id, name, roman FROM regions ORDER BY sort_order ASC, name ASC'
)->fetchAll();

$stmt = $pdo->query(
    'SELECT id, region_id, name FROM communes ORDER BY region_id ASC, sort_order ASC, name ASC'
);
$communesByRegion = [];
foreach ($stmt->fetchAll() as $row) {
    $regionId = (int)$row['region_id'];
    if (!isset($communesByRegion[$regionId])) $communesByRegion[$regionId] = [];
    $communesByRegion[$regionId][] = [
        'id' => (int)$row['id'],
        'name' => $row['name'],
    ];
}

$out = [];
foreach ($regions as $region) {
    $regionId = (int)$region['id'];
    $out[] = [
        'id' => $regionId,
        'name' => $region['name'],
        'roman' => $region['roman'],
        'communes' => $communesByRegion[$regionId] ?? [],
    ];
}

json_ok(['regions' => $out]);
