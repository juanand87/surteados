<?php
require __DIR__ . '/config.php';

$pdo = db();
surteados_ensure_flow_order_number_column($pdo);

echo "OK: columna flow_order_number disponible\n";
