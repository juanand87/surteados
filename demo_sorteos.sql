-- ============================================================
--  SURTEADOS — 6 Sorteos de Demostración
--  Ejecutar en: surteados_db
--  mysql -u root surteados_db < demo_sorteos.sql
-- ============================================================

USE surteados_db;

-- ── RAFFLES ──────────────────────────────────────────────────

INSERT IGNORE INTO raffles
  (id, title, category, description, status, draw_date,
   image_emoji, image_url, total_tickets, sold_tickets, featured)
VALUES
-- 1. iPhone 16 Pro Max
('demo_iphone16pro',
 'iPhone 16 Pro Max 256GB',
 'Tecnología',
 'El smartphone más poderoso de Apple. Chip A18 Pro, cámara de 48 MP con zoom óptico 5x y pantalla Super Retina XDR de 6.9".',
 'active', '2026-07-15 21:00:00',
 '📱', 'http://localhost/surteados/assets/uploads/raffle_0001000000000001.jpg',
 500, 0, 1),

-- 2. Toyota GR86
('demo_toyota_gr86',
 'Toyota GR86 2025',
 'Autos',
 'El deportivo japonés más emocionante. Motor boxer de 2.4 L con 234 HP, tracción trasera y caja manual de 6 velocidades.',
 'active', '2026-08-01 21:00:00',
 '🚗', 'http://localhost/surteados/assets/uploads/raffle_0002000000000002.jpg',
 2000, 0, 1),

-- 3. Samsung Neo QLED 65"
('demo_samsung_qled',
 'Samsung Neo QLED 65" 8K',
 'Electrónica',
 'Televisor de última generación con resolución 8K, tecnología Mini LED Quantum Matrix y sonido Dolby Atmos integrado.',
 'active', '2026-07-20 21:00:00',
 '📺', 'http://localhost/surteados/assets/uploads/raffle_0003000000000003.jpg',
 800, 0, 0),

-- 4. Viaje a Cancún
('demo_viaje_cancun',
 'Viaje a Cancún para 2 — Todo Incluido',
 'Viajes',
 '7 noches en resort 5 estrellas en Cancún, México. Pasajes aéreos, traslados y plan todo incluido para dos personas.',
 'active', '2026-07-30 21:00:00',
 '✈️', 'http://localhost/surteados/assets/uploads/raffle_0004000000000004.jpg',
 1000, 0, 1),

-- 5. MacBook Pro M4 Pro
('demo_macbook_m4pro',
 'MacBook Pro M4 Pro 16"',
 'Tecnología',
 'La laptop profesional más potente de Apple. Chip M4 Pro de 12 núcleos, 24 GB de RAM unificada y 512 GB SSD.',
 'active', '2026-07-25 21:00:00',
 '💻', 'http://localhost/surteados/assets/uploads/raffle_0005000000000005.jpg',
 600, 0, 0),

-- 6. PlayStation 5 Pro + 10 juegos
('demo_ps5_pro_pack',
 'PlayStation 5 Pro + 10 Juegos',
 'Gaming',
 'La consola más potente de Sony con ray tracing mejorado y resolución 8K. Incluye 10 juegos físicos a elección del ganador.',
 'active', '2026-07-10 21:00:00',
 '🎮', 'http://localhost/surteados/assets/uploads/raffle_0006000000000006.jpg',
 700, 0, 0);


-- ── PRIZES ───────────────────────────────────────────────────

INSERT IGNORE INTO raffle_prizes
  (id, raffle_id, place, name, value, emoji, image_url)
VALUES
('prize_iphone16pro_1',  'demo_iphone16pro',  1, 'iPhone 16 Pro Max 256GB',                 1099990, '📱', 'http://localhost/surteados/assets/uploads/raffle_0001000000000001.jpg'),
('prize_toyota_gr86_1',  'demo_toyota_gr86',  1, 'Toyota GR86 2025 (0 km)',               22000000, '🚗', 'http://localhost/surteados/assets/uploads/raffle_0002000000000002.jpg'),
('prize_samsung_qled_1', 'demo_samsung_qled', 1, 'Samsung Neo QLED 65" 8K',               1299990, '📺', 'http://localhost/surteados/assets/uploads/raffle_0003000000000003.jpg'),
('prize_viaje_cancun_1', 'demo_viaje_cancun', 1, 'Viaje a Cancún para 2 — Todo Incluido',  3500000, '✈️', 'http://localhost/surteados/assets/uploads/raffle_0004000000000004.jpg'),
('prize_macbook_m4pro_1','demo_macbook_m4pro', 1, 'MacBook Pro M4 Pro 16"',                2499990, '💻', 'http://localhost/surteados/assets/uploads/raffle_0005000000000005.jpg'),
('prize_ps5pro_1',       'demo_ps5_pro_pack', 1, 'PlayStation 5 Pro + 10 Juegos',           899990, '🎮', 'http://localhost/surteados/assets/uploads/raffle_0006000000000006.jpg');


-- ── PACKS (4 packs por sorteo) ────────────────────────────────
--  best_value = 1 en el pack de 5 (mejor relación precio/ticket)

INSERT IGNORE INTO raffle_packs
  (id, raffle_id, qty, price, original_price, label, discount, best_value)
VALUES
-- iPhone 16 Pro Max  (precio base: $2.500 / ticket)
('pack_iphone16pro_1',  'demo_iphone16pro',  1,  2500,     0, '1 ticket',     0, 0),
('pack_iphone16pro_3',  'demo_iphone16pro',  3,  6000,  7500, '3 tickets',   20, 0),
('pack_iphone16pro_5',  'demo_iphone16pro',  5,  9500, 12500, '5 tickets',   24, 1),
('pack_iphone16pro_10', 'demo_iphone16pro', 10, 16000, 25000, '10 tickets',  36, 0),

-- Toyota GR86  (precio base: $5.000 / ticket)
('pack_toyota_gr86_1',  'demo_toyota_gr86',  1,  5000,     0, '1 ticket',     0, 0),
('pack_toyota_gr86_3',  'demo_toyota_gr86',  3, 12000, 15000, '3 tickets',   20, 0),
('pack_toyota_gr86_5',  'demo_toyota_gr86',  5, 20000, 25000, '5 tickets',   20, 1),
('pack_toyota_gr86_10', 'demo_toyota_gr86', 10, 35000, 50000, '10 tickets',  30, 0),

-- Samsung Neo QLED  (precio base: $2.000 / ticket)
('pack_samsung_qled_1',  'demo_samsung_qled',  1,  2000,     0, '1 ticket',    0, 0),
('pack_samsung_qled_3',  'demo_samsung_qled',  3,  5000,  6000, '3 tickets',  17, 0),
('pack_samsung_qled_5',  'demo_samsung_qled',  5,  8000, 10000, '5 tickets',  20, 1),
('pack_samsung_qled_10', 'demo_samsung_qled', 10, 13000, 20000, '10 tickets', 35, 0),

-- Viaje a Cancún  (precio base: $3.000 / ticket)
('pack_viaje_cancun_1',  'demo_viaje_cancun',  1,  3000,     0, '1 ticket',    0, 0),
('pack_viaje_cancun_3',  'demo_viaje_cancun',  3,  8000,  9000, '3 tickets',  11, 0),
('pack_viaje_cancun_5',  'demo_viaje_cancun',  5, 12000, 15000, '5 tickets',  20, 1),
('pack_viaje_cancun_10', 'demo_viaje_cancun', 10, 20000, 30000, '10 tickets', 33, 0),

-- MacBook Pro M4 Pro  (precio base: $3.500 / ticket)
('pack_macbook_m4pro_1',  'demo_macbook_m4pro',  1,  3500,     0, '1 ticket',    0, 0),
('pack_macbook_m4pro_3',  'demo_macbook_m4pro',  3,  9000, 10500, '3 tickets',  14, 0),
('pack_macbook_m4pro_5',  'demo_macbook_m4pro',  5, 14000, 17500, '5 tickets',  20, 1),
('pack_macbook_m4pro_10', 'demo_macbook_m4pro', 10, 24000, 35000, '10 tickets', 31, 0),

-- PlayStation 5 Pro  (precio base: $2.000 / ticket)
('pack_ps5pro_1',  'demo_ps5_pro_pack',  1,  2000,     0, '1 ticket',    0, 0),
('pack_ps5pro_3',  'demo_ps5_pro_pack',  3,  5500,  6000, '3 tickets',   8, 0),
('pack_ps5pro_5',  'demo_ps5_pro_pack',  5,  8500, 10000, '5 tickets',  15, 1),
('pack_ps5pro_10', 'demo_ps5_pro_pack', 10, 14000, 20000, '10 tickets', 30, 0);
