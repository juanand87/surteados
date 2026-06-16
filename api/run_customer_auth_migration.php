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
      full_name    VARCHAR(180) NULL,
      phone        VARCHAR(40) NULL,
      address      VARCHAR(255) NULL,
      commune_id   INT NULL,
      comuna       VARCHAR(120) NULL,
      rut          VARCHAR(30) NULL,
      status       ENUM('pending','active','blocked') NOT NULL DEFAULT 'pending',
      email_verified_at DATETIME NULL,
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

    $pdo->exec("CREATE TABLE IF NOT EXISTS customer_email_verifications (
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

    $cols = $pdo->query("SHOW COLUMNS FROM customer_users")->fetchAll();
    $existing = array_column($cols, 'Field');
    $adds = [
        'full_name' => "ALTER TABLE customer_users ADD COLUMN full_name VARCHAR(180) NULL AFTER email",
        'phone' => "ALTER TABLE customer_users ADD COLUMN phone VARCHAR(40) NULL AFTER full_name",
        'address' => "ALTER TABLE customer_users ADD COLUMN address VARCHAR(255) NULL AFTER phone",
        'commune_id' => "ALTER TABLE customer_users ADD COLUMN commune_id INT NULL AFTER address",
        'comuna' => "ALTER TABLE customer_users ADD COLUMN comuna VARCHAR(120) NULL AFTER commune_id",
        'rut' => "ALTER TABLE customer_users ADD COLUMN rut VARCHAR(30) NULL AFTER comuna",
        'status' => "ALTER TABLE customer_users ADD COLUMN status ENUM('pending','active','blocked') NOT NULL DEFAULT 'pending' AFTER rut",
        'email_verified_at' => "ALTER TABLE customer_users ADD COLUMN email_verified_at DATETIME NULL AFTER status",
    ];
    $addedStatus = false;
    foreach ($adds as $field => $sql) {
        if (!in_array($field, $existing, true)) {
            $pdo->exec($sql);
            if ($field === 'status') {
                $addedStatus = true;
            }
            echo "OK: {$field} agregado" . PHP_EOL;
        }
    }
    if ($addedStatus) {
        $pdo->exec("UPDATE customer_users SET status = 'active', email_verified_at = COALESCE(email_verified_at, created_at, NOW()) WHERE email_verified_at IS NULL");
        echo "OK: cuentas existentes marcadas como verificadas" . PHP_EOL;
    }

    echo "OK: customer auth tables are ready." . PHP_EOL;
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
