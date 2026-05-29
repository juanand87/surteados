<?php
require __DIR__ . '/../api/config.php';
session_name(SESSION_NAME);
if (session_status() === PHP_SESSION_NONE) session_start();
$_SESSION = [];
session_destroy();
header('Location: index.php');
exit;
