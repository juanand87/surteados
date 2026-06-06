<?php
require_once 'C:/xampp/htdocs/surteados/api/config.php';
require_once 'C:/xampp/htdocs/surteados/api/data_helper.php';
try {
    $pdo = db();
    $data = getPublicData($pdo);
    echo 'Raffles count: ' . count($data['raffles']) . PHP_EOL;
    foreach($data['raffles'] as $r) {
        echo $r['id'] . ' | ' . $r['status'] . ' | ' . $r['drawDate'] . PHP_EOL;
    }
} catch(Throwable $e) {
    echo 'ERROR: ' . $e->getMessage() . PHP_EOL;
}
