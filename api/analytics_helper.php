<?php
/** Shared storage helpers for first-party analytics. */

function surteados_ensure_analytics_tables(PDO $pdo): void
{
    static $ready = false;
    if ($ready) return;

    try {
        $versionStmt = $pdo->prepare("SELECT `value` FROM settings WHERE `key` = 'analytics_schema_version' LIMIT 1");
        $versionStmt->execute();
        if ((string)$versionStmt->fetchColumn() === '1') {
            $ready = true;
            return;
        }
    } catch (Throwable $e) {
        // Continue with table creation on fresh installations.
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS analytics_sessions (
      session_id VARCHAR(64) PRIMARY KEY,
      visitor_id VARCHAR(64) NOT NULL,
      entry_path VARCHAR(255) NOT NULL DEFAULT '/',
      referrer VARCHAR(500) NULL,
      utm_source VARCHAR(100) NULL,
      utm_medium VARCHAR(100) NULL,
      utm_campaign VARCHAR(150) NULL,
      device_type VARCHAR(20) NOT NULL DEFAULT 'desktop',
      ip_hash CHAR(64) NULL,
      page_views INT UNSIGNED NOT NULL DEFAULT 0,
      active_seconds INT UNSIGNED NOT NULL DEFAULT 0,
      converted TINYINT(1) NOT NULL DEFAULT 0,
      first_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_analytics_sessions_first (first_seen_at),
      INDEX idx_analytics_sessions_last (last_seen_at),
      INDEX idx_analytics_sessions_visitor (visitor_id),
      INDEX idx_analytics_sessions_device (device_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS analytics_page_views (
      view_id VARCHAR(64) PRIMARY KEY,
      session_id VARCHAR(64) NOT NULL,
      visitor_id VARCHAR(64) NOT NULL,
      path VARCHAR(255) NOT NULL,
      raffle_id VARCHAR(25) NULL,
      active_seconds INT UNSIGNED NOT NULL DEFAULT 0,
      started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_analytics_views_session (session_id),
      INDEX idx_analytics_views_path (path),
      INDEX idx_analytics_views_raffle (raffle_id),
      INDEX idx_analytics_views_started (started_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS analytics_events (
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      session_id VARCHAR(64) NOT NULL,
      visitor_id VARCHAR(64) NOT NULL,
      event_type VARCHAR(50) NOT NULL,
      raffle_id VARCHAR(25) NULL,
      cart_id VARCHAR(64) NULL,
      step TINYINT UNSIGNED NULL,
      metadata TEXT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_analytics_events_type (event_type),
      INDEX idx_analytics_events_session (session_id),
      INDEX idx_analytics_events_raffle (raffle_id),
      INDEX idx_analytics_events_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS analytics_carts (
      cart_id VARCHAR(64) PRIMARY KEY,
      session_id VARCHAR(64) NOT NULL,
      visitor_id VARCHAR(64) NOT NULL,
      status ENUM('active','abandoned','converted','cleared') NOT NULL DEFAULT 'active',
      has_contact TINYINT(1) NOT NULL DEFAULT 0,
      buyer_name VARCHAR(180) NULL,
      buyer_email VARCHAR(180) NULL,
      buyer_phone VARCHAR(50) NULL,
      items_json LONGTEXT NULL,
      item_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
      total_amount INT UNSIGNED NOT NULL DEFAULT 0,
      current_step TINYINT UNSIGNED NOT NULL DEFAULT 1,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      abandoned_at DATETIME NULL,
      converted_at DATETIME NULL,
      INDEX idx_analytics_carts_status (status),
      INDEX idx_analytics_carts_updated (updated_at),
      INDEX idx_analytics_carts_session (session_id),
      INDEX idx_analytics_carts_email (buyer_email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->prepare("INSERT INTO settings (`key`,`value`) VALUES ('analytics_schema_version','1') ON DUPLICATE KEY UPDATE `value`='1'")->execute();
    $ready = true;
}

function surteados_analytics_id(mixed $value): string
{
    $value = trim((string)$value);
    return preg_match('/^[A-Za-z0-9_-]{8,64}$/', $value) ? $value : '';
}

function surteados_analytics_text(mixed $value, int $max): string
{
    $value = trim(strip_tags((string)$value));
    return mb_substr($value, 0, $max);
}

function surteados_analytics_ip_hash(): string
{
    $ip = trim((string)($_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? ''));
    return $ip === '' ? '' : hash('sha256', $ip . '|' . DB_NAME . '|' . SESSION_NAME);
}