<?php
require_once __DIR__ . '/api/config.php';
require_once __DIR__ . '/api/data_helper.php';
$pdo = db();
$allData = getPublicData($pdo);
$active = array_values(array_filter($allData['raffles'], fn($r) => ($r['status'] ?? '') === 'active'));
foreach($active as $r){
  $id = $r['id'] ?? '';
  $title = $r['title'] ?? '';
  $draw = $r['drawDate'] ?? '';
  $packsCount = is_array($r['packs'] ?? null) ? count($r['packs']) : -1;
  echo $id . " | " . $title . " | draw=" . $draw . " | packs=" . $packsCount . PHP_EOL;
}
