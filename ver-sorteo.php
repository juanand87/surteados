<?php
require_once __DIR__ . '/api/config.php';
require_once __DIR__ . '/api/data_helper.php';

$id = trim($_GET['id'] ?? '');
if (!preg_match('/^[a-z0-9_-]{1,60}$/i', $id)) {
    header('Location: sorteos.php');
    exit;
}

try {
    $pdo     = db();
    $allData = getPublicData($pdo);
} catch (Throwable $e) {
    $allData = ['raffles' => [], 'winners' => [], 'settings' => []];
}

$raffle = null;
foreach ($allData['raffles'] as $r) {
    if ($r['id'] === $id) { $raffle = $r; break; }
}
if (!$raffle) { header('Location: sorteos.php'); exit; }

$initData     = json_encode($allData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$cfg          = $allData['settings'] ?? [];
$theme        = $cfg['theme'] ?? [];
$siteLogo     = $cfg['logo'] ?? null;
$ticketLabel  = $cfg['ticketLabel']       ?? 'ticket';
$ticketLabelP = $cfg['ticketLabelPlural'] ?? 'tickets';
$siteName     = $cfg['siteName']          ?? 'Surteados';

// Raffle data shortcuts
$status      = $raffle['status'] ?? 'soon';
$drawDate    = $raffle['drawDate'] ?? null;
$salesClosed = false;
$salesClosedMsg = '';
if ($drawDate) {
  $drawTs = strtotime($drawDate);
  if ($drawTs) {
    $closeTs = $drawTs - 86400;
    if (time() >= $closeTs && $status === 'active') {
      $salesClosed = true;
      $remaining = max(0, $drawTs - time());
      $d = (int)floor($remaining / 86400);
      $h = (int)floor(($remaining % 86400) / 3600);
      $m = (int)floor(($remaining % 3600) / 60);
      $parts = [];
      if ($d > 0) $parts[] = $d . 'd';
      if ($h > 0) $parts[] = $h . 'h';
      if ($m > 0 && count($parts) < 2) $parts[] = $m . 'm';
      if (!$parts) $parts[] = 'menos de 1 minuto';
      $salesClosedMsg = 'Se ha cerrado la compra de ' . htmlspecialchars($ticketLabelP) . ', faltan ' . implode(' ', $parts) . ' para que puedas ganar.';
    }
  }
}
$legalText   = $raffle['legalInfo']['organizer']   ?? '';
$legalRut    = $raffle['legalInfo']['rut']         ?? '';
$legalNotary = $raffle['legalInfo']['notary']      ?? '';
$legalCert   = $raffle['legalInfo']['certificate'] ?? '';
$legalPeriod = $raffle['legalInfo']['salesPeriod'] ?? '';
$meetLink    = $raffle['meetLink'] ?? '';
$hasLimit    = !empty($raffle['totalTickets']) && (int)$raffle['totalTickets'] > 0;

$pct = $hasLimit
    ? min(100, (int)round($raffle['soldTickets'] / $raffle['totalTickets'] * 100))
    : 0;

$minPrice = 0;
if (!empty($raffle['packs'])) {
    $prices   = array_column($raffle['packs'], 'price');
    $minPrice = $prices ? min($prices) : 0;
}

$statusLabels = ['active' => 'En curso', 'soon' => 'Próximamente', 'ended' => 'Finalizado'];
$statusEmoji  = ['active' => '🟢',       'soon' => '🟡',            'ended' => '🔴'];

// Format draw date nicely
$drawDateHuman = '';
if ($drawDate) {
    $ts     = strtotime($drawDate);
    $months = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
    $drawDateHuman = date('j', $ts) . ' de ' . ($months[(int)date('n', $ts)] ?? '') . ' de ' . date('Y', $ts) . ' a las ' . date('H:i', $ts) . ' hrs';
}

// Sort packs by price desc for display
$sortedPacks = $raffle['packs'] ?? [];
usort($sortedPacks, fn($a, $b) => $b['price'] - $a['price']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($raffle['title']) ?> — <?= htmlspecialchars($siteName) ?></title>
  <meta name="description" content="<?= htmlspecialchars(mb_substr($raffle['description'] ?? '', 0, 160)) ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/styles.css">
<?php if (!empty($theme['primary'])): ?>
<style>:root{
  --color-primary:<?= htmlspecialchars($theme['primary']) ?>;
  --color-primary-light:<?= htmlspecialchars($theme['primaryLight'] ?? '#9d5cf6') ?>;
  --color-primary-dark:<?= htmlspecialchars($theme['primaryDark'] ?? '#5b21b6') ?>;
  --color-accent:<?= htmlspecialchars($theme['accent'] ?? '#f59e0b') ?>;
  --color-accent-light:<?= htmlspecialchars($theme['accentLight'] ?? '#fbbf24') ?>;
  --color-accent-dark:<?= htmlspecialchars($theme['accentDark'] ?? '#d97706') ?>;
}</style>
<?php endif; ?>
<style>
.vs-hero-bg {
  position: absolute; inset: 0;
  background-size: cover; background-position: center;
  opacity: .13; filter: blur(10px); transform: scale(1.08);
}
.vs-hero-overlay {
  position: absolute; inset: 0;
  background: linear-gradient(to right, var(--bg-base) 0%, transparent 55%, var(--bg-base) 100%);
}
.vs-countdown-box {
  background: rgba(124,58,237,.14);
  border: 1px solid rgba(124,58,237,.3);
  border-radius: 1rem;
  padding: 1.1rem 1.4rem;
  text-align: center;
  flex-shrink: 0;
}
.vs-action-bar {
  background: var(--bg-card);
  border-bottom: 1px solid var(--border);
  padding: .75rem 0;
  position: sticky;
  top: 68px;
  z-index: 50;
}
.vs-stat-card { padding: 1.1rem; text-align: center; }
.vs-stat-num  { font-size: 1.75rem; font-weight: 800; }
.vs-stat-lbl  { font-size: .78rem; color: var(--text-muted); margin-top: .1rem; }
</style>
</head>
<body>

<!-- ─── Navbar ─────────────────────────────────────────────────── -->
<nav class="navbar" id="navbar">
  <div class="navbar-inner">
    <a href="index.php" class="navbar-logo">
      <?php if ($siteLogo): ?>
        <img src="<?= htmlspecialchars($siteLogo) ?>" alt="Logo" class="navbar-logo-img">
      <?php else: ?>
        <div class="logo-icon">🎟️</div>
        <span class="brand">Sur<em>tea</em>dos</span>
      <?php endif; ?>
    </a>
    <nav class="navbar-nav">
      <a href="index.php">Inicio</a>
      <a href="sorteos.php" class="active">Sorteos</a>
      <a href="como-participar.php">¿Cómo participar?</a>
      <a href="ganadores.php">Ganadores</a>
      <a href="mis-tickets.php">Mis <?= htmlspecialchars(ucfirst($ticketLabelP)) ?></a>
    </nav>
    <div class="navbar-actions">
      <a href="mis-tickets.php" class="btn btn-outline btn-sm">Mis <?= htmlspecialchars(ucfirst($ticketLabelP)) ?></a>
      <a href="sorteos.php" class="btn btn-primary btn-sm">Participar 🎟️</a>
      <button class="cart-chip-btn" id="cartOpenBtn" onclick="openCartDrawer()">🛒 Carro <span class="cart-chip-count" id="cartCountNav">0</span></button>
    </div>
    <button class="navbar-mobile-toggle" id="mobileToggle">
      <span></span><span></span><span></span>
    </button>
  </div>
  <div class="mobile-nav" id="mobileNav">
    <a href="index.php">🏠 Inicio</a>
    <a href="sorteos.php">🎟️ Sorteos</a>
    <a href="como-participar.php">🧭 ¿Cómo participar?</a>
    <a href="ganadores.php">🏆 Ganadores</a>
    <a href="mis-tickets.php">🎫 Mis <?= htmlspecialchars(ucfirst($ticketLabelP)) ?></a>
  </div>
</nav>

<div style="padding-top:68px;">

  <!-- ─── Hero ───────────────────────────────────────────────────── -->
  <div style="position:relative;background:var(--bg-card);border-bottom:1px solid var(--border);overflow:hidden;min-height:300px;display:flex;align-items:center;">
    <?php if ($raffle['image']): ?>
    <div class="vs-hero-bg" style="background-image:url('<?= htmlspecialchars($raffle['image']) ?>');"></div>
    <div class="vs-hero-overlay"></div>
    <?php endif; ?>

    <div class="container" style="position:relative;z-index:1;display:flex;align-items:center;gap:2rem;padding:2.5rem 1rem;flex-wrap:wrap;">

      <!-- Thumbnail -->
      <div style="width:160px;height:160px;border-radius:.9rem;overflow:hidden;flex-shrink:0;background:var(--bg-card2);border:1px solid var(--border-strong);display:flex;align-items:center;justify-content:center;">
        <?php if ($raffle['image']): ?>
          <img src="<?= htmlspecialchars($raffle['image']) ?>" alt="<?= htmlspecialchars($raffle['title']) ?>" style="width:100%;height:100%;object-fit:cover;">
        <?php else: ?>
          <div style="font-size:4.5rem;"><?= htmlspecialchars($raffle['imageEmoji'] ?? '🎁') ?></div>
        <?php endif; ?>
      </div>

      <!-- Info -->
      <div style="flex:1;min-width:220px;">
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:.65rem;">
          <span class="badge"><?= htmlspecialchars($raffle['category']) ?></span>
          <span class="pill <?= $status === 'active' ? 'pill-green' : ($status === 'soon' ? 'pill-amber' : 'pill-gray') ?>">
            <?= $statusEmoji[$status] ?? '⚪' ?> <?= htmlspecialchars($statusLabels[$status] ?? $status) ?>
          </span>
        </div>

        <h1 style="font-size:clamp(1.3rem,3.5vw,2rem);margin:0 0 .5rem;line-height:1.2;"><?= htmlspecialchars($raffle['title']) ?></h1>

        <?php if (!empty($raffle['prizes'][0])): $p1 = $raffle['prizes'][0]; ?>
        <div style="font-size:.95rem;font-weight:700;color:var(--color-accent);margin-bottom:.75rem;">
          🏆 <?= htmlspecialchars($p1['name'] ?? '') ?>
        </div>
        <?php endif; ?>

        <?php if ($drawDateHuman): ?>
        <div style="display:flex;align-items:center;gap:.4rem;font-size:.875rem;color:var(--text-muted);">
          <span><?= $status === 'ended' ? '📅 Sorteo realizado:' : '📅 Fecha del sorteo:' ?></span>
          <strong style="color:var(--text-inv);"><?= htmlspecialchars($drawDateHuman) ?></strong>
        </div>
        <?php endif; ?>
      </div>

      <!-- Countdown -->
      <?php if ($drawDate && $status !== 'ended'): ?>
      <div class="vs-countdown-box">
        <div style="font-size:.68rem;text-transform:uppercase;letter-spacing:.07em;color:var(--text-muted);margin-bottom:.65rem;">Tiempo restante</div>
        <div class="countdown-row">
          <div class="countdown-item"><div class="count-num" id="vs-cd-days">--</div><div class="count-label">Días</div></div>
          <div class="countdown-item"><div class="count-num" id="vs-cd-hours">--</div><div class="count-label">Horas</div></div>
          <div class="countdown-item"><div class="count-num" id="vs-cd-mins">--</div><div class="count-label">Min</div></div>
          <div class="countdown-item"><div class="count-num" id="vs-cd-secs">--</div><div class="count-label">Seg</div></div>
        </div>
      </div>
      <?php endif; ?>

    </div>
  </div>

  <!-- ─── Sticky Action Bar ───────────────────────────────────────── -->
  <?php if ($status === 'active' || $meetLink): ?>
  <div class="vs-action-bar">
    <div class="container" style="display:flex;justify-content:space-between;align-items:center;gap:.75rem;flex-wrap:wrap;">
      <div>
        <?php if ($minPrice > 0): ?>
        <span style="font-size:.82rem;color:var(--text-muted);">Desde&nbsp;</span>
        <strong style="font-size:1.2rem;color:var(--color-primary);">$<?= number_format($minPrice, 0, ',', '.') ?></strong>
        <?php endif; ?>
      </div>
      <div style="display:flex;gap:.65rem;align-items:center;flex-wrap:wrap;justify-content:flex-end;">
        <?php if ($salesClosedMsg): ?>
        <div style="font-size:.78rem;color:#fbbf24;max-width:380px;text-align:right;"><?= $salesClosedMsg ?></div>
        <?php endif; ?>
        <?php if ($meetLink): ?>
        <a href="<?= htmlspecialchars($meetLink) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-ghost" style="display:inline-flex;align-items:center;gap:.35rem;">
          📹 <?= $status === 'ended' ? 'Ver grabación' : 'Ver transmisión' ?>
        </a>
        <?php endif; ?>
        <?php if ($status === 'active' && !$salesClosed): ?>
        <button class="btn btn-primary" onclick="openPurchaseModal('<?= htmlspecialchars($raffle['id']) ?>')" style="font-weight:800;">
          Comprar <?= htmlspecialchars(ucfirst($ticketLabel)) ?>
        </button>
        <?php elseif ($status === 'active' && $salesClosed): ?>
        <span class="pill pill-amber" style="font-size:.78rem;">Compra cerrada</span>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- ─── Main Content ──────────────────────────────────────────── -->
  <section class="section">
    <div class="container" style="max-width:820px;">

      <!-- Description -->
      <?php if (!empty($raffle['description'])): ?>
      <div class="card mb-4" style="padding:1.6rem;">
        <h3 class="text-white mb-2">📝 Descripción</h3>
        <p style="line-height:1.75;white-space:pre-wrap;color:var(--text-muted);"><?= htmlspecialchars($raffle['description']) ?></p>
      </div>
      <?php endif; ?>

      <!-- Stats Grid -->
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:.85rem;margin-bottom:1rem;">
        <div class="card vs-stat-card">
          <div class="vs-stat-num" style="color:var(--color-primary);"><?= $hasLimit ? number_format($raffle['totalTickets'], 0, ',', '.') : '∞' ?></div>
          <div class="vs-stat-lbl"><?= $hasLimit ? ('Total ' . htmlspecialchars($ticketLabelP)) : 'Sin límite' ?></div>
        </div>
        <div class="card vs-stat-card">
          <div class="vs-stat-num" style="color:var(--color-accent);"><?= number_format($raffle['soldTickets'], 0, ',', '.') ?></div>
          <div class="vs-stat-lbl"><?= htmlspecialchars(ucfirst($ticketLabelP)) ?> vendidos</div>
        </div>
        <?php if ($hasLimit): ?>
          <div class="card vs-stat-card">
            <div class="vs-stat-num" style="color:var(--color-primary-light);"><?= $pct ?>%</div>
            <div class="vs-stat-lbl">Completado</div>
          </div>
          <div class="card vs-stat-card">
            <div class="vs-stat-num" style="color:var(--text-inv);"><?= number_format(max(0, $raffle['totalTickets'] - $raffle['soldTickets']), 0, ',', '.') ?></div>
            <div class="vs-stat-lbl">Disponibles</div>
          </div>
        <?php endif; ?>
      </div>

      <!-- Progress Bar -->
      <?php if ($hasLimit): ?>
      <div class="card mb-4" style="padding:1.1rem 1.4rem;">
        <div style="display:flex;justify-content:space-between;margin-bottom:.45rem;">
          <span style="font-size:.82rem;color:var(--text-muted);"><?= htmlspecialchars(ucfirst($ticketLabelP)) ?> vendidos</span>
          <span style="font-size:.82rem;font-weight:700;color:var(--text-inv);"><?= $pct ?>%</span>
        </div>
        <div class="progress-bar">
          <div class="progress-fill" style="width:<?= $pct ?>%;transition:width .6s ease;"></div>
        </div>
      </div>
      <?php endif; ?>

      <!-- Prizes -->
      <?php if (!empty($raffle['prizes'])): ?>
      <div class="card mb-4" style="padding:1.6rem;">
        <h3 class="text-white mb-3">🏆 Premios</h3>
        <div style="display:flex;flex-direction:column;gap:.7rem;">
          <?php foreach ($raffle['prizes'] as $prize): ?>
          <div style="display:flex;align-items:center;gap:.9rem;padding:.75rem;background:rgba(124,58,237,.1);border-radius:.75rem;border:1px solid rgba(124,58,237,.2);">
            <?php if (!empty($prize['image'])): ?>
              <img src="<?= htmlspecialchars($prize['image']) ?>" alt="" style="width:52px;height:52px;border-radius:.5rem;object-fit:cover;flex-shrink:0;">
            <?php else: ?>
              <div style="width:52px;height:52px;border-radius:.5rem;background:var(--bg-card2);display:flex;align-items:center;justify-content:center;font-size:1.8rem;flex-shrink:0;"><?= htmlspecialchars($prize['emoji'] ?? '🏆') ?></div>
            <?php endif; ?>
            <div style="flex:1;">
              <div style="font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;color:var(--color-primary-light);">Lugar N°<?= (int)$prize['place'] ?></div>
              <div style="font-weight:700;color:var(--text-inv);font-size:.97rem;"><?= htmlspecialchars($prize['name'] ?? '') ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- Packs (only if active) -->
      <?php if (!empty($sortedPacks) && $status === 'active'): ?>
      <div class="card mb-4" style="padding:1.6rem;">
        <h3 class="text-white mb-1">🎟️ Packs disponibles</h3>
        <p style="font-size:.83rem;color:var(--text-muted);margin-bottom:1.1rem;">Elige el pack que más te acomode y participa ahora.</p>
        <div class="packs-grid">
          <?php foreach ($sortedPacks as $pack): ?>
          <div class="pack-card<?= !empty($pack['bestValue']) ? ' best-value' : '' ?>"
               onclick="openPurchaseModal('<?= htmlspecialchars($raffle['id']) ?>')"
               style="cursor:pointer;">
            <div class="pack-qty"><?= (int)$pack['qty'] ?></div>
            <div class="pack-qty-label"><?= (int)$pack['qty'] === 1 ? htmlspecialchars($ticketLabel) : htmlspecialchars($ticketLabelP) ?></div>
            <?php if (!empty($pack['originalPrice']) && $pack['originalPrice'] > $pack['price']): ?>
            <div class="pack-price-original">$<?= number_format((int)$pack['originalPrice'], 0, ',', '.') ?></div>
            <?php endif; ?>
            <div class="pack-price">$<?= number_format((int)$pack['price'], 0, ',', '.') ?></div>
            <?php if (!empty($pack['discount'])): ?>
            <div class="pack-discount">-<?= (int)$pack['discount'] ?>% OFF</div>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
        <div style="text-align:center;margin-top:1.25rem;">
          <button class="btn btn-primary btn-lg" onclick="openPurchaseModal('<?= htmlspecialchars($raffle['id']) ?>')" style="font-weight:800;">
            🎟️ Comprar ahora
          </button>
        </div>
      </div>
      <?php endif; ?>

      <!-- Meet Link -->
      <?php if ($meetLink): ?>
      <div class="card mb-4" style="padding:1.6rem;background:linear-gradient(135deg,rgba(34,197,94,.07),rgba(16,185,129,.05));border-color:rgba(34,197,94,.25);">
        <h3 class="text-white mb-1">📹 <?= $status === 'ended' ? 'Grabación del sorteo' : 'Transmisión en vivo' ?></h3>
        <p style="font-size:.88rem;color:var(--text-muted);margin-bottom:1.1rem;">
          <?php if ($status === 'ended'): ?>
            El sorteo ya se realizó. Puedes ver la grabación haciendo clic en el botón.
          <?php elseif ($status === 'active'): ?>
            El sorteo se transmite en vivo. Haz clic para unirte y seguir todo en tiempo real.
          <?php else: ?>
            Cuando comience el sorteo, podrás verlo en vivo en este enlace.
          <?php endif; ?>
        </p>
        <a href="<?= htmlspecialchars($meetLink) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-primary" style="display:inline-flex;align-items:center;gap:.4rem;font-weight:700;">
          <?= $status === 'ended' ? '▶️ Ver grabación' : '📹 Unirse a la transmisión' ?>
        </a>
        <p style="font-size:.7rem;color:var(--text-muted);margin-top:.55rem;word-break:break-all;opacity:.7;"><?= htmlspecialchars($meetLink) ?></p>
      </div>
      <?php endif; ?>

      <!-- Legal Section -->
      <?php if ($legalText || $legalRut || $legalNotary || $legalCert || $legalPeriod): ?>
      <div class="card mb-4" style="padding:1.6rem;">
        <h3 class="text-white mb-1">📜 Información Legal</h3>
        <p style="font-size:.81rem;color:var(--text-muted);margin-bottom:1.1rem;">Aspectos legales y bases de este sorteo.</p>

        <?php if ($legalRut || $legalNotary || $legalCert || $legalPeriod): ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:.65rem;margin-bottom:1.1rem;">
          <?php if ($legalRut): ?>
          <div style="background:rgba(0,0,0,.22);border-radius:.55rem;padding:.75rem;">
            <div style="font-size:.68rem;text-transform:uppercase;letter-spacing:.05em;opacity:.55;margin-bottom:.2rem;">RUT organizador</div>
            <div style="font-weight:600;color:var(--text-inv);font-size:.9rem;"><?= htmlspecialchars($legalRut) ?></div>
          </div>
          <?php endif; ?>
          <?php if ($legalNotary): ?>
          <div style="background:rgba(0,0,0,.22);border-radius:.55rem;padding:.75rem;">
            <div style="font-size:.68rem;text-transform:uppercase;letter-spacing:.05em;opacity:.55;margin-bottom:.2rem;">Notario</div>
            <div style="font-weight:600;color:var(--text-inv);font-size:.9rem;"><?= htmlspecialchars($legalNotary) ?></div>
          </div>
          <?php endif; ?>
          <?php if ($legalCert): ?>
          <div style="background:rgba(0,0,0,.22);border-radius:.55rem;padding:.75rem;">
            <div style="font-size:.68rem;text-transform:uppercase;letter-spacing:.05em;opacity:.55;margin-bottom:.2rem;">Certificado</div>
            <div style="font-weight:600;color:var(--text-inv);font-size:.9rem;"><?= htmlspecialchars($legalCert) ?></div>
          </div>
          <?php endif; ?>
          <?php if ($legalPeriod): ?>
          <div style="background:rgba(0,0,0,.22);border-radius:.55rem;padding:.75rem;">
            <div style="font-size:.68rem;text-transform:uppercase;letter-spacing:.05em;opacity:.55;margin-bottom:.2rem;">Período de ventas</div>
            <div style="font-weight:600;color:var(--text-inv);font-size:.9rem;"><?= htmlspecialchars($legalPeriod) ?></div>
          </div>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($legalText): ?>
        <details>
          <summary style="cursor:pointer;font-size:.88rem;font-weight:600;color:var(--color-primary-light);user-select:none;padding:.4rem 0;list-style:none;display:flex;align-items:center;gap:.4rem;">
            <span style="font-size:.8em;">▶</span> Ver bases completas del sorteo
          </summary>
          <div style="margin-top:.85rem;padding:1.1rem;background:rgba(0,0,0,.28);border-radius:.65rem;white-space:pre-wrap;font-size:.83rem;line-height:1.75;color:var(--text-muted);border:1px solid var(--border);">
            <?= htmlspecialchars($legalText) ?>
          </div>
        </details>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- Back -->
      <div style="text-align:center;padding:1.5rem 0 2.5rem;">
        <a href="sorteos.php" class="btn btn-ghost">← Ver todos los sorteos</a>
      </div>

    </div>
  </section>

</div><!-- /padding-top wrapper -->

<!-- ─── Purchase Modal ──────────────────────────────────────────── -->
<div class="modal-backdrop" id="purchaseModal">
  <div class="modal">
    <div class="modal-top-bar"></div>
    <div class="modal-header">
      <span class="modal-title" id="modalTitle">Comprar <?= htmlspecialchars(ucfirst($ticketLabelP)) ?></span>
      <button class="modal-close" id="modalClose">✕</button>
    </div>
    <div class="modal-body">
      <div class="purchase-steps mb-3">
        <div class="purchase-step active" data-step="1"><div class="ps-num">1</div><div class="ps-label">Pack</div></div>
        <div class="purchase-step" data-step="2"><div class="ps-num">2</div><div class="ps-label">Datos</div></div>
        <div class="purchase-step" data-step="3"><div class="ps-num">3</div><div class="ps-label">Pago</div></div>
        <div class="purchase-step" data-step="4"><div class="ps-num">4</div><div class="ps-label">¡Listo!</div></div>
      </div>
      <div class="step-panel active" id="step1">
        <h4 class="text-white mb-2">Elige tu pack</h4>
        <div id="modalPacksGrid" class="packs-grid mb-3"></div>
        <div class="separator"></div>
        <div class="flex-between mb-3">
          <span class="text-sm text-muted">Pack seleccionado:</span>
          <span class="text-sm font-bold text-white" id="selectedPackLabel">Ninguno</span>
        </div>
        <div style="display:flex;flex-direction:column;gap:.6rem;">
          <button class="btn btn-primary" id="step1Next" disabled>Continuar con el pago →</button>
          <button class="btn btn-ghost" id="step1AddMore" disabled title="Agregar al carro y ver más sorteos">🛒 Seleccionar y ver más sorteos</button>
          <button class="btn btn-cancel" id="step1Cancel">Cancelar</button>
        </div>
      </div>
      <div class="step-panel" id="step1b">
        <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1rem;">
          <button class="btn btn-ghost btn-sm" id="step1bBack">← Volver</button>
          <h4 class="text-white" style="margin:0;">Agregar más sorteos</h4>
        </div>
        <div id="cartBar" style="background:rgba(124,58,237,.18);border:1px solid rgba(124,58,237,.35);border-radius:.7rem;padding:.6rem 1rem;margin-bottom:1rem;display:flex;align-items:center;justify-content:space-between;gap:.5rem;">
          <div style="font-size:.82rem;color:var(--text-inv);"><span id="cartCount">0</span> sorteo(s) · <strong id="cartTotal" style="color:var(--color-accent);">$0</strong></div>
          <button class="btn btn-accent btn-sm" id="cartPayBtn" style="font-weight:800;">🔒 Pagar ahora</button>
        </div>
        <div id="moreRafflesGrid" style="display:grid;grid-template-columns:1fr 1fr;gap:.65rem;max-height:340px;overflow-y:auto;"></div>
      </div>
      <div class="step-panel" id="step2">
        <h4 class="text-white mb-2">Tus datos</h4>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Nombre completo *</label><input type="text" class="form-control" id="buyerName" placeholder="Juan Pérez"></div>
          <div class="form-group"><label class="form-label">Teléfono</label><input type="tel" class="form-control" id="buyerPhone" placeholder="+56 9 1234 5678"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Dirección *</label><input type="text" class="form-control" id="buyerAddress" placeholder="Av. Siempre Viva 123"></div>
          <div class="form-group"><label class="form-label">Comuna *</label><input type="text" class="form-control" id="buyerComuna" placeholder="Santiago"></div>
        </div>
        <div class="form-group"><label class="form-label">RUT *</label><input type="text" class="form-control" id="buyerRut" placeholder="12.345.678-5" autocomplete="off"><p class="form-hint">🇨🇱 Ingresa tu RUT chileno válido.</p></div>
        <div class="form-group"><label class="form-label">Correo electrónico *</label><input type="email" class="form-control" id="buyerEmail" placeholder="tu@correo.com"><p class="form-hint">📧 Aquí recibirás tus <?= htmlspecialchars($ticketLabelP) ?> digitales</p></div>
        <div class="form-group"><label class="form-label">Confirmar correo *</label><input type="email" class="form-control" id="buyerEmailConfirm" placeholder="tu@correo.com"></div>
        <div class="separator"></div>
        <div class="flex gap-2">
          <button class="btn btn-ghost" id="step2Back">← Volver</button>
          <button class="btn btn-primary" style="flex:1" id="step2Next">Continuar →</button>
        </div>
      </div>
      <div class="step-panel" id="step3">
        <h4 class="text-white mb-2">Resumen de compra</h4>
        <div id="cartSummaryList" class="mb-3"></div>
        <div class="card mb-3" style="padding:.75rem 1rem;">
          <div class="flex-between">
            <span class="font-bold text-white">Total a pagar:</span>
            <span class="font-bold text-accent" id="summaryTotal" style="font-size:1.2rem;">—</span>
          </div>
          <div class="flex-between mt-1">
            <span class="text-sm text-muted">📧 Enviado a:</span>
            <span class="text-sm" id="summaryEmail">—</span>
          </div>
        </div>
        <h4 class="text-white mb-2">Método de pago</h4>
        <div style="margin-bottom:1.25rem;" id="paymentMethods">
          <label class="card" style="cursor:pointer;padding:.875rem;display:flex;align-items:center;gap:.75rem;border-color:var(--color-primary);"><input type="radio" name="payMethod" value="flow" checked style="accent-color:var(--color-primary);"><div><div style="font-weight:700;color:var(--text-inv);font-size:.875rem;">Flow</div><div style="font-size:.75rem;color:var(--text-muted);">Pago único para todo el carrito</div></div></label>
        </div>
        <div class="separator"></div>
        <div class="flex gap-2">
          <button class="btn btn-ghost" id="step3Back">← Volver</button>
          <button class="btn btn-accent" style="flex:1;font-weight:800;font-size:1rem;" id="step3Pay">🔒 Pagar Ahora</button>
        </div>
        <button id="step3SimulateBtn" style="width:100%;margin-top:.55rem;padding:.5rem;background:rgba(255,200,0,.1);border:1px dashed rgba(255,200,0,.4);border-radius:.6rem;color:#f5c842;font-size:.78rem;cursor:pointer;">
          ⚡ Simular pago exitoso (sólo demo)
        </button>
        <p class="text-xs text-muted text-center mt-1">Transacción segura y encriptada</p>
      </div>
      <div class="step-panel" id="step4">
        <div class="text-center" style="padding:1rem 0;">
          <div style="font-size:4rem;margin-bottom:1rem;">🎉</div>
          <h3 class="text-white mb-2">¡Compra exitosa!</h3>
          <p id="step4Subtitle" class="mb-3">Tus <?= htmlspecialchars($ticketLabelP) ?> han sido asignados. Revisa tu correo.</p>
          <div id="purchasedTickets" style="margin-bottom:1.5rem;text-align:left;"></div>
          <div class="card" style="padding:1rem;text-align:left;margin-bottom:1.5rem;">
            <div class="flex-between mb-1"><span class="text-sm text-muted">📧 Enviado a:</span><span class="text-sm text-white font-bold" id="confirmEmail">—</span></div>
            <div class="flex-between"><span class="text-sm text-muted">💰 Total pagado:</span><span class="text-sm text-accent font-bold" id="confirmAmount">—</span></div>
          </div>
          <a href="mis-tickets.php" id="verMisTicketsLink" class="btn btn-primary btn-block mb-2">Ver mis <?= htmlspecialchars($ticketLabelP) ?></a>
          <button class="btn btn-ghost btn-block" id="closeAfterPurchase">Cerrar</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ─── Cart Drawer ───────────────────────────────────────────────── -->
<div class="cart-overlay" id="cartOverlay" onclick="closeCartDrawer()" style="display:none;position:fixed;inset:0;background:rgba(10,10,15,.55);z-index:1000;"></div>
<aside class="cart-drawer" id="cartDrawer" style="position:fixed;top:0;right:0;width:min(380px,94vw);height:100vh;z-index:1001;background:var(--bg-card);border-left:1px solid var(--border-strong);box-shadow:var(--shadow-lg);transform:translateX(110%);transition:transform .22s ease;display:flex;flex-direction:column;">
  <div class="cart-drawer-head">
    <strong>🛒 Tu carro</strong>
    <button class="btn btn-ghost btn-sm" onclick="closeCartDrawer()">Cerrar</button>
  </div>
  <div class="cart-drawer-body" id="cartDrawerBody">
    <p class="text-sm text-muted">Tu carro está vacío.</p>
  </div>
  <div class="cart-drawer-foot">
    <div class="flex-between mb-2">
      <span class="text-sm text-muted">Total</span>
      <strong class="text-white" id="cartDrawerTotal">$0</strong>
    </div>
    <button class="btn btn-primary btn-block" id="cartGoCheckoutBtn" onclick="goToCheckoutFromCart()">Ir al pago</button>
  </div>
</aside>

<!-- ─── Theme Picker ──────────────────────────────────────────────── -->
<div class="theme-picker" id="themePicker">
  <div class="theme-toggle" id="themeToggle" title="Personalizar colores">🎨</div>
  <div class="theme-panel" id="themePanel">
    <h4>🎨 Personalizar Tema</h4>
    <p class="text-xs text-muted mb-2">Presets:</p>
    <div class="theme-presets" id="themePresets">
      <button class="preset-btn active" data-preset="purple" style="background:linear-gradient(135deg,#7c3aed,#f59e0b);"></button>
      <button class="preset-btn" data-preset="blue"    style="background:linear-gradient(135deg,#2563eb,#06b6d4);"></button>
      <button class="preset-btn" data-preset="emerald" style="background:linear-gradient(135deg,#059669,#fbbf24);"></button>
      <button class="preset-btn" data-preset="rose"    style="background:linear-gradient(135deg,#e11d48,#f59e0b);"></button>
      <button class="preset-btn" data-preset="indigo"  style="background:linear-gradient(135deg,#4338ca,#c026d3);"></button>
      <button class="preset-btn" data-preset="teal"    style="background:linear-gradient(135deg,#0d9488,#f97316);"></button>
    </div>
    <div class="color-input-group"><label>Color primario</label><div class="color-input-row"><input type="color" id="colorPrimary" value="#7c3aed"><input type="text" class="form-control" id="colorPrimaryHex" value="#7c3aed"></div></div>
    <div class="color-input-group"><label>Color acento</label><div class="color-input-row"><input type="color" id="colorAccent" value="#f59e0b"><input type="text" class="form-control" id="colorAccentHex" value="#f59e0b"></div></div>
    <button class="btn btn-primary btn-sm btn-block mt-2" id="applyThemeBtn">Aplicar Tema</button>
    <button class="btn btn-ghost btn-sm btn-block mt-1" id="resetThemeBtn">Restaurar</button>
  </div>
</div>

<div id="toast-container" class="toast-container"></div>

<!-- ─── Scripts ───────────────────────────────────────────────────── -->
<script>window.SURTEADOS_DATA = <?= $initData ?>;</script>
<script src="assets/js/data.js"></script>
<script src="assets/js/app.js"></script>
<?php if ($drawDate && $status !== 'ended'): ?>
<script>startCountdown('<?= addslashes($drawDate) ?>', 'vs-cd-days', 'vs-cd-hours', 'vs-cd-mins', 'vs-cd-secs');</script>
<?php endif; ?>

<button class="cart-fab" onclick="openCartDrawer()" title="Ver carro">
  <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
  <span class="cart-fab-badge" id="cartFabCount"></span>
</button>
</body>
</html>
