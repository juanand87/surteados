-- =============================================================
-- SURTEADOS — MySQL Schema
-- Ejecutar: mysql -u root surteados_db < schema.sql
-- O importar desde phpMyAdmin
-- =============================================================

CREATE DATABASE IF NOT EXISTS surteados_db
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE surteados_db;

-- ── Admin users ───────────────────────────────────────────────
DROP TABLE IF EXISTS admin_users;
CREATE TABLE admin_users (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  username     VARCHAR(50)  NOT NULL UNIQUE,
  password     VARCHAR(255) NOT NULL,
  email        VARCHAR(150),
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── Customer users ────────────────────────────────────────────
DROP TABLE IF EXISTS customer_users;
CREATE TABLE customer_users (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  username     VARCHAR(50)  NOT NULL UNIQUE,
  email        VARCHAR(150) NOT NULL UNIQUE,
  password     VARCHAR(255) NOT NULL,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── Email access codes for Mis Tickets ───────────────────────
DROP TABLE IF EXISTS ticket_access_codes;
CREATE TABLE ticket_access_codes (
  id           BIGINT AUTO_INCREMENT PRIMARY KEY,
  email        VARCHAR(150) NOT NULL,
  code_hash    VARCHAR(255) NOT NULL,
  attempts     TINYINT UNSIGNED DEFAULT 0,
  used_at      DATETIME NULL,
  expires_at   DATETIME NOT NULL,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_email_exp (email, expires_at),
  INDEX idx_used_at (used_at)
) ENGINE=InnoDB;

-- ── Settings (key/value) ─────────────────────────────────────
DROP TABLE IF EXISTS settings;
CREATE TABLE settings (
  `key`       VARCHAR(100) PRIMARY KEY,
  `value`     TEXT,
  updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── Raffles ───────────────────────────────────────────────────
DROP TABLE IF EXISTS raffle_prizes;
DROP TABLE IF EXISTS raffle_packs;
DROP TABLE IF EXISTS tickets;
DROP TABLE IF EXISTS winners;
DROP TABLE IF EXISTS raffles;

CREATE TABLE raffles (
  id                  VARCHAR(25)  PRIMARY KEY,
  title               VARCHAR(200) NOT NULL,
  category            VARCHAR(100) DEFAULT 'General',
  description         TEXT,
  status              ENUM('active','soon','ended') DEFAULT 'soon',
  draw_date           DATETIME,
  image_emoji         VARCHAR(10)  DEFAULT '🎁',
  image_url           VARCHAR(500),
  total_tickets       INT          DEFAULT NULL,
  sold_tickets        INT          DEFAULT 0,
  featured            TINYINT(1)   DEFAULT 0,
  legal_organizer     VARCHAR(200),
  legal_rut           VARCHAR(30),
  legal_notary        VARCHAR(200),
  legal_certificate   VARCHAR(100),
  legal_sales_period  VARCHAR(250),
  meet_link           VARCHAR(500),
  created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_status (status),
  INDEX idx_featured (featured)
) ENGINE=InnoDB;

-- ── Raffle prizes ─────────────────────────────────────────────
CREATE TABLE raffle_prizes (
  id         VARCHAR(25) PRIMARY KEY,
  raffle_id  VARCHAR(25) NOT NULL,
  place      INT         DEFAULT 1,
  name       VARCHAR(200),
  value      BIGINT      DEFAULT 0,
  emoji      VARCHAR(10) DEFAULT '🏆',
  image_url  VARCHAR(500),
  FOREIGN KEY (raffle_id) REFERENCES raffles(id) ON DELETE CASCADE,
  INDEX idx_raffle (raffle_id)
) ENGINE=InnoDB;

-- ── Raffle packs ──────────────────────────────────────────────
CREATE TABLE raffle_packs (
  id             VARCHAR(25) PRIMARY KEY,
  raffle_id      VARCHAR(25) NOT NULL,
  qty            INT         DEFAULT 1,
  price          INT         DEFAULT 0,
  original_price INT         DEFAULT 0,
  label          VARCHAR(100),
  discount       INT         DEFAULT 0,
  best_value     TINYINT(1)  DEFAULT 0,
  FOREIGN KEY (raffle_id) REFERENCES raffles(id) ON DELETE CASCADE,
  INDEX idx_raffle (raffle_id)
) ENGINE=InnoDB;

-- ── Tickets ───────────────────────────────────────────────────
CREATE TABLE tickets (
  id              VARCHAR(25)   PRIMARY KEY,
  raffle_id       VARCHAR(25),
  buyer_name      VARCHAR(150),
  buyer_rut       VARCHAR(30),
  buyer_email     VARCHAR(150),
  buyer_phone     VARCHAR(30),
  buyer_address   VARCHAR(255),
  buyer_comuna    VARCHAR(120),
  pack_id         VARCHAR(25),
  pack_label      VARCHAR(100),
  ticket_numbers  TEXT,          -- JSON array of strings
  amount          INT            DEFAULT 0,
  payment_method  VARCHAR(50)    DEFAULT 'flow',
  payment_status  ENUM('pending','paid','failed','refunded') DEFAULT 'pending',
  flow_token      VARCHAR(255),
  flow_order      VARCHAR(100),
  purchase_date   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (raffle_id) REFERENCES raffles(id) ON DELETE SET NULL,
  INDEX idx_raffle    (raffle_id),
  INDEX idx_email     (buyer_email),
  INDEX idx_flow_tok  (flow_token),
  INDEX idx_status    (payment_status)
) ENGINE=InnoDB;

-- ── Winners ───────────────────────────────────────────────────
CREATE TABLE winners (
  id              VARCHAR(25)  PRIMARY KEY,
  raffle_id       VARCHAR(25),
  raffle_title    VARCHAR(200),
  winner_name     VARCHAR(150),
  winner_location VARCHAR(200),
  prize           VARCHAR(200),
  prize_value     BIGINT       DEFAULT 0,
  draw_date       DATE,
  ticket_number   VARCHAR(25),
  edition         VARCHAR(50),
  emoji           VARCHAR(10)  DEFAULT '🏆',
  verified        TINYINT(1)   DEFAULT 0,
  winner_image_url VARCHAR(500),
  video_url       VARCHAR(500),
  notary_doc      VARCHAR(500),
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── Seed: settings ────────────────────────────────────────────
INSERT INTO settings (`key`, `value`) VALUES
  -- Site
  ('site_name',          'Surteados'),
  ('site_tagline',       'Tu plataforma de rifas digitales más confiable'),
  ('site_email',         'contacto@surteados.cl'),
  ('site_whatsapp',      '+56912345678'),
  ('site_url',           'http://localhost/surteados'),
  -- Theme
  ('theme_primary',      '#7c3aed'),
  ('theme_primary_light','#9d5cf6'),
  ('theme_primary_dark', '#5b21b6'),
  ('theme_accent',       '#f59e0b'),
  ('theme_accent_light', '#fbbf24'),
  ('theme_accent_dark',  '#d97706'),
  -- Social
  ('social_instagram',   '#'),
  ('social_tiktok',      '#'),
  ('social_youtube',     '#'),
  ('social_facebook',    '#'),
  -- SMTP
  ('smtp_host',          ''),
  ('smtp_port',          '587'),
  ('smtp_user',          ''),
  ('smtp_pass',          ''),
  ('smtp_from_name',     'Surteados'),
  ('smtp_from_email',    ''),
  ('smtp_encryption',    'tls'),
  -- Flow.cl
  ('flow_api_key',       ''),
  ('flow_secret_key',    ''),
  ('flow_environment',   'sandbox');

-- ── Seed: raffles ─────────────────────────────────────────────
INSERT INTO raffles VALUES
  ('r001','iPhone 16 Pro Max 256GB','Tecnología',
   'El smartphone más avanzado de Apple. Cámara pro, chip A18 Pro y diseño premium de titanio.',
   'active','2026-05-15 19:00:00','📱',NULL,5000,3241,1,
   'Surteados Chile SpA','77.999.888-1','Carlos Rojas Morales','N° 501-2026',
   '01 de abril al 14 de mayo de 2026',NOW(),NOW()),
  ('r002','PlayStation 5 + 5 Juegos','Gaming',
   'Consola PlayStation 5 edición Digital + 5 juegos a elección.',
   'active','2026-04-30 20:00:00','🎮',NULL,3000,2100,0,
   'Surteados Chile SpA','77.999.888-1','Carlos Rojas Morales','N° 498-2026',
   '10 de marzo al 29 de abril de 2026',NOW(),NOW()),
  ('r003','Viaje a Europa 2 Personas','Viajes',
   'Paquete todo incluido para dos personas a Europa. 10 días en España, Francia e Italia.',
   'soon','2026-07-01 19:00:00','✈️',NULL,8000,0,0,
   'Surteados Chile SpA','77.999.888-1','María López Vega','N° 512-2026',
   '15 de mayo al 30 de junio de 2026',NOW(),NOW());

INSERT INTO raffle_prizes VALUES
  ('pr001','r001',1,'iPhone 16 Pro Max 256GB',1299000,'📱',NULL),
  ('pr002','r001',2,'AirPods Pro 2da Gen',349000,'🎧',NULL),
  ('pr003','r001',3,'Vale de $50.000',50000,'🎁',NULL),
  ('pr004','r002',1,'PlayStation 5 Digital',549000,'🎮',NULL),
  ('pr005','r002',2,'5 Juegos PS5 a elección',150000,'💿',NULL),
  ('pr006','r003',1,'Viaje Europa 2 Personas',4500000,'✈️',NULL),
  ('pr007','r003',2,'Maleta Premium Samsonite',250000,'🧳',NULL),
  ('pr008','r003',3,'Vale Travel Store $100.000',100000,'🎫',NULL);

INSERT INTO raffle_packs VALUES
  ('pk001','r001',1,3000,3000,'1 Ticket',0,0),
  ('pk002','r001',3,7500,9000,'3 Tickets',17,0),
  ('pk003','r001',5,11000,15000,'5 Tickets',27,1),
  ('pk004','r001',10,18000,30000,'10 Tickets',40,0),
  ('pk005','r002',1,2000,2000,'1 Ticket',0,0),
  ('pk006','r002',3,4500,6000,'3 Tickets',25,0),
  ('pk007','r002',5,7000,10000,'5 Tickets',30,1),
  ('pk008','r002',10,12000,20000,'10 Tickets',40,0),
  ('pk009','r003',1,5000,5000,'1 Ticket',0,0),
  ('pk010','r003',3,12000,15000,'3 Tickets',20,0),
  ('pk011','r003',5,18000,25000,'5 Tickets',28,1),
  ('pk012','r003',10,30000,50000,'10 Tickets',40,0);

INSERT INTO winners VALUES
  ('w001','old001','MacBook Pro M3','Roberto Arias','Concepción, Chile',
   'MacBook Pro M3 14"',2199000,'2025-12-20','002847','1ª Edición','💻',1,'#','#',NOW()),
  ('w002','old002','Smart TV Samsung 65"','Valentina Cruz','Santiago, Chile',
   'Smart TV Samsung 65" QLED',899000,'2026-01-15','001203','2ª Edición','📺',1,'#','#',NOW()),
  ('w003','old003','Moto Yamaha MT-03','Diego Fuentes','Valparaíso, Chile',
   'Yamaha MT-03 2025',5500000,'2026-02-28','000456','3ª Edición','🏍️',1,'#','#',NOW());
