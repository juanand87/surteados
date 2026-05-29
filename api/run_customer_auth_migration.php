<?php
/** Run once: php api/run_customer_auth_migration.php */
$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'CLI';
require __DIR__ . '/config.php';

try {
    $pdo = db();

    $pdo->exec("CREATE TABLE IF NOT EXISTS customer_users (
      id           INT AUTO_INCREMENT PRIMARY KEY,
      username     VARCHAR(50)  NOT NULL UNIQUE,
      email        VARCHAR(150) NOT NULL UNIQUE,
      password     VARCHAR(255) NOT NULL,
      created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS ticket_access_codes (
      id           BIGINT AUTO_INCREMENT PRIMARY KEY,
      email        VARCHAR(150) NOT NULL,
      code_hash    VARCHAR(255) NOT NULL,
      attempts     TINYINT UNSIGNED DEFAULT 0,
      used_at      DATETIME NULL,
      expires_at   DATETIME NOT NULL,
      created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_email_exp (email, expires_at),
      INDEX idx_used_at (used_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    echo "OK: customer_users and ticket_access_codes are ready." . PHP_EOL;
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
