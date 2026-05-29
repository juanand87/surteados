<?php
require_once __DIR__ . '/api/config.php';
require_once __DIR__ . '/api/data_helper.php';
try {
    $pdo     = db();
    $allData = getPublicData($pdo);
} catch (Throwable $e) {
    $allData = ['raffles' => [], 'winners' => [], 'settings' => []];
}
$initData = json_encode($allData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$cfg      = $allData['settings'] ?? [];
$theme    = $cfg['theme'] ?? [];
$siteLogo = $cfg['logo'] ?? null;
$ticketLabel  = $cfg['ticketLabel']       ?? 'ticket';
$ticketLabelP = $cfg['ticketLabelPlural'] ?? 'tickets';
?><!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Ganadores — Surteados</title>
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
</head>
<body>

<nav class="navbar" id="navbar">
  <div class="navbar-inner">
    <a href="index.php" class="navbar-logo"><?php if ($siteLogo): ?><img src="<?= htmlspecialchars($siteLogo) ?>" alt="Logo" class="navbar-logo-img"><?php else: ?><div class="logo-icon">🎟️</div><span class="brand">Sur<em>tea</em>dos</span><?php endif; ?></a>
    <nav class="navbar-nav">
      <a href="index.php">Inicio</a>
      <a href="sorteos.php">Sorteos</a>
      <a href="como-participar.php">¿Cómo participar?</a>
      <a href="ganadores.php" class="active">Ganadores</a>
        <a href="mis-tickets.php">Mis <?= htmlspecialchars(ucfirst($ticketLabelP)) ?></a>
    </nav>
    <div class="navbar-actions">
        <a href="mis-tickets.php" class="btn btn-outline btn-sm">Mis <?= htmlspecialchars(ucfirst($ticketLabelP)) ?></a>
      <a href="sorteos.php" class="btn btn-primary btn-sm">Participar 🎟️</a>
    </div>
    <button class="navbar-mobile-toggle" id="mobileToggle"><span></span><span></span><span></span></button>
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
  <div class="page-header">
    <div class="container text-center">
      <div class="badge" style="margin:0 auto 1rem; display:inline-flex;">🏆 Ganadores</div>
      <h1>Nuestros <span class="text-gradient">Ganadores</span></h1>
      <p style="max-width:560px; margin:.75rem auto 0;">Personas reales que ganaron con Surteados. Todo el proceso es transparente, notariado y verificable.</p>
    </div>
  </div>

  <!-- Stats -->
  <div class="trust-band">
    <div class="container">
      <div style="display:flex; gap:3rem; justify-content:center; padding:.5rem 0; flex-wrap:wrap;">
        <div class="trust-item">🏆 <span id="statWinners">0</span> ganadores felices</div>
        <div class="trust-item">📜 <span id="statRaffles">0</span> sorteos notariados</div>
        <div class="trust-item">✅ 100% verificado</div>
      </div>
    </div>
  </div>

  <section class="section container">
    <div id="winnersGrid" class="grid-3"></div>

    <!-- Empty state (if no winners) -->
    <div id="winnersEmpty" class="empty-state hidden">
      <div class="empty-icon">🏆</div>
      <h3 class="text-white">Próximamente</h3>
      <p>Cuando se realice el primer sorteo, los ganadores aparecerán aquí.</p>
    </div>
  </section>

  <!-- CTA -->
  <section class="section-sm container">
    <div style="background:linear-gradient(135deg, rgba(124,58,237,0.12), rgba(245,158,11,0.06)); border:1px solid rgba(124,58,237,0.2); border-radius:var(--radius-xl); padding:2.5rem; text-align:center;">
      <h2 class="mb-2">¿Quieres ser el próximo ganador?</h2>
      <p class="mb-3">Únete a miles de participantes. Desde solo unos miles de pesos tienes la oportunidad de ganar.</p>
      <a href="sorteos.php" class="btn btn-primary btn-lg">🎟️ Ver Sorteos Activos</a>
    </div>
  </section>
</div>

<!-- Footer -->
<footer class="footer">
  <div class="container">
    <div class="footer-bottom">
      <p>© 2026 Surteados. Todos los derechos reservados.</p>
      <div class="social-links">
        <a href="#" class="social-link">📸</a>
        <a href="#" class="social-link">🎵</a>
        <a href="#" class="social-link">▶️</a>
      </div>
    </div>
  </div>
</footer>

<div class="theme-picker" id="themePicker">
  <div class="theme-toggle" id="themeToggle">🎨</div>
  <div class="theme-panel" id="themePanel">
    <h4>🎨 Personalizar Tema</h4>
    <div class="theme-presets" id="themePresets">
      <button class="preset-btn active" data-preset="purple" style="background:linear-gradient(135deg,#7c3aed,#f59e0b);"></button>
      <button class="preset-btn" data-preset="blue" style="background:linear-gradient(135deg,#2563eb,#06b6d4);"></button>
      <button class="preset-btn" data-preset="emerald" style="background:linear-gradient(135deg,#059669,#fbbf24);"></button>
      <button class="preset-btn" data-preset="rose" style="background:linear-gradient(135deg,#e11d48,#f59e0b);"></button>
      <button class="preset-btn" data-preset="indigo" style="background:linear-gradient(135deg,#4338ca,#c026d3);"></button>
      <button class="preset-btn" data-preset="teal" style="background:linear-gradient(135deg,#0d9488,#f97316);"></button>
    </div>
    <div class="color-input-group"><label>Color primario</label><div class="color-input-row"><input type="color" id="colorPrimary" value="#7c3aed"><input type="text" class="form-control" id="colorPrimaryHex" value="#7c3aed"></div></div>
    <div class="color-input-group"><label>Color acento</label><div class="color-input-row"><input type="color" id="colorAccent" value="#f59e0b"><input type="text" class="form-control" id="colorAccentHex" value="#f59e0b"></div></div>
    <button class="btn btn-primary btn-sm btn-block mt-2" id="applyThemeBtn">Aplicar</button>
    <button class="btn btn-ghost btn-sm btn-block mt-1" id="resetThemeBtn">Restaurar</button>
  </div>
</div>

<div id="toast-container" class="toast-container"></div>
<script>window.SURTEADOS_DATA = <?= $initData ?>;</script>
<script src="assets/js/data.js"></script>
<script src="assets/js/app.js"></script>
<script>
  (function() {
    function toYoutubeEmbed(url) {
      if (!url || url === '#') return null;
      try {
        const u = new URL(url);
        const host = u.hostname.replace(/^www\./, '').toLowerCase();
        let id = '';
        if (host === 'youtu.be') {
          id = u.pathname.replace(/^\//, '').split('/')[0];
        } else if (host === 'youtube.com' || host === 'm.youtube.com') {
          if (u.pathname === '/watch') id = u.searchParams.get('v') || '';
          else if (u.pathname.startsWith('/embed/')) id = u.pathname.split('/embed/')[1].split('/')[0];
          else if (u.pathname.startsWith('/shorts/')) id = u.pathname.split('/shorts/')[1].split('/')[0];
        }
        return id ? `https://www.youtube.com/embed/${id}` : null;
      } catch (_) { return null; }
    }

    const winners = db.getWinners();
    const grid = document.getElementById('winnersGrid');
    const empty = document.getElementById('winnersEmpty');

    // Stats
    document.getElementById('statWinners').textContent = winners.length;
    document.getElementById('statRaffles').textContent = winners.length;

    if (winners.length === 0) {
      grid.classList.add('hidden');
      empty.classList.remove('hidden');
      return;
    }

    grid.innerHTML = winners.map(w => `
      <div class="winner-card">
        <div class="winner-card-img">
          ${toYoutubeEmbed(w.videoUrl)
            ? `<iframe src="${toYoutubeEmbed(w.videoUrl)}" title="Video ganador ${escHtml(w.winnerName || '')}" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen style="width:100%;height:100%;border:0;border-radius:.85rem;"></iframe>`
            : (w.image ? `<img src="${w.image}" alt="${w.winnerName}">` : `<div style="font-size:5rem;">${w.emoji || '🏆'}</div>`)
          }
        </div>
        <div class="winner-card-body">
          <div class="flex-between mb-1">
            <span class="winner-name" style="display:inline-flex;align-items:center;gap:.5rem;">
              ${w.image ? `<img src="${w.image}" alt="${escHtml(w.winnerName || 'Ganador')}" style="width:28px;height:28px;border-radius:999px;object-fit:cover;border:1px solid var(--border-strong);">` : `<span style="width:28px;height:28px;border-radius:999px;display:inline-flex;align-items:center;justify-content:center;background:var(--bg-card2);border:1px solid var(--border);font-size:.9rem;">👤</span>`}
              <span>${w.winnerName}</span>
            </span>
            ${w.verified ? `<span class="verified-badge">✅ Verificado</span>` : ''}
          </div>
          <div class="winner-location">📍 ${w.winnerLocation}</div>
          <div class="winner-prize">🏆 ${w.prize}</div>
          <div class="winner-date">📅 Sorteo ${formatDate(w.drawDate)}</div>
          ${w.ticketNumber ? `<div class="winner-date">🎟️ <?= htmlspecialchars(ucfirst($ticketLabel)) ?> ganador: <strong style="color:var(--color-primary-light)">${w.ticketNumber}</strong></div>` : ''}
          <div style="display:flex; gap:.5rem; margin-top:.75rem; flex-wrap:wrap; align-items:center;">
            ${w.edition ? `<span class="pill pill-purple">${w.edition}</span>` : ''}
            ${w.videoUrl && w.videoUrl !== '#' ? `<a href="${w.videoUrl}" target="_blank" class="btn btn-ghost btn-sm">▶ Ver video</a>` : ''}
            ${w.notaryDoc && w.notaryDoc !== '#' ? `<a href="${w.notaryDoc}" target="_blank" class="btn btn-ghost btn-sm">📜 Acta</a>` : ''}
          </div>
        </div>
      </div>
    `).join('');
  })();
</script>
</body>
</html>
