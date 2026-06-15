<?php
require __DIR__ . '/../api/config.php';
admin_session_start();
$_SESSION = [];
session_destroy();
header('Location: index.php');
exit;
