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
  <title>Surteados — Rifas digitales confiables</title>
  <meta name="description" content="La plataforma chilena de rifas digitales más transparente y segura. Compra tu <?= htmlspecialchars($ticketLabel) ?>, recíbelo al instante y participa del sorteo en vivo.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/styles.css">
  <style>
    /* Page-specific extras */
    .hero-floating-ticket {
      position: absolute;
      background: rgba(124,58,237,0.08);
      border: 1px solid rgba(124,58,237,0.15);
      border-radius: 12px;
      padding: 0.75rem 1rem;
      font-size: 0.75rem;
      backdrop-filter: blur(4px);
      animation: float-anim 4s ease-in-out infinite;
    }
    .hero-floating-ticket:nth-child(2) { animation-delay: -1.5s; }
    .hero-floating-ticket:nth-child(3) { animation-delay: -3s; }
    @keyframes float-anim {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(-12px); }
    }
    .tab-filters {
      display: flex;
      gap: 0.5rem;
      flex-wrap: wrap;
      margin-bottom: 2rem;
    }
    .tab-filter {
      padding: 0.5rem 1.25rem;
      border-radius: 9999px;
      font-size: 0.875rem;
      font-weight: 600;
      border: 1px solid var(--border);
      color: var(--text-secondary);
      cursor: pointer;
      transition: all 0.2s;
    }
    .tab-filter:hover { border-color: var(--border-strong); color: var(--text-primary); }
    .tab-filter.active {
      background: var(--color-primary);
      border-color: var(--color-primary);
      color: white;
      box-shadow: 0 4px 16px rgba(124,58,237,0.3);
    }
    .section-cta {
      text-align: center;
      margin-top: 2.5rem;
    }

    /* CTA Banner */
    .cta-banner {
      background: linear-gradient(135deg, rgba(124,58,237,0.15), rgba(245,158,11,0.08));
      border: 1px solid rgba(124,58,237,0.2);
      border-radius: var(--radius-xl);
      padding: 3rem 2rem;
      text-align: center;
      position: relative;
      overflow: hidden;
    }
    .cta-banner::before {
      content: '';
      position: absolute;
      top: -50%;
      left: -10%;
      width: 300px;
      height: 300px;
      background: radial-gradient(circle, rgba(124,58,237,0.15), transparent 70%);
      pointer-events: none;
    }
    .cta-banner::after {
      content: '';
      position: absolute;
      bottom: -50%;
      right: -10%;
      width: 250px;
      height: 250px;
      background: radial-gradient(circle, rgba(245,158,11,0.12), transparent 70%);
      pointer-events: none;
    }
    .cta-banner h2, .cta-banner p, .cta-banner .btn { position: relative; z-index: 1; }
    .cta-banner h2 { margin-bottom: 0.75rem; }
    .cta-banner p { margin-bottom: 1.75rem; }
  </style>
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

<!-- ═══════════════════════════════ NAVBAR ═══════════════════════════════ -->
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
      <a href="index.php" class="active">Inicio</a>
      <a href="sorteos.php">Sorteos</a>
      <a href="como-participar.php">¿Cómo participar?</a>
      <a href="ganadores.php">Ganadores</a>
        <a href="mis-tickets.php">Mis <?= htmlspecialchars(ucfirst($ticketLabelP)) ?></a>
    </nav>
    <div class="navbar-actions">
        <a href="mis-tickets.php" class="btn btn-outline btn-sm">Mis <?= htmlspecialchars(ucfirst($ticketLabelP)) ?></a>
      <a href="sorteos.php" class="btn btn-primary btn-sm">Participar 🎟️</a>
      <button class="cart-chip-btn" id="cartOpenBtn" onclick="openCartDrawer()">🛒 Carro <span class="cart-chip-count" id="cartCountNav">0</span></button>
    </div>
    <button class="navbar-mobile-toggle" id="mobileToggle" aria-label="Menú">
      <span></span><span></span><span></span>
    </button>
  </div>
  <div class="mobile-nav" id="mobileNav">
    <a href="index.php">🏠 Inicio</a>
    <a href="sorteos.php">🎟️ Sorteos</a>
    <a href="como-participar.php">🧭 ¿Cómo participar?</a>
    <a href="ganadores.php">🏆 Ganadores</a>
      <a href="mis-tickets.php">🎫 Mis <?= htmlspecialchars(ucfirst($ticketLabelP)) ?></a>
    <a href="panel/">⚙️ Admin</a>
  </div>
</nav>

<!-- ═══════════════════════════ HERO SLIDER ═══════════════════════════ -->
<div class="hero-slider-wrap hidden" id="heroSliderWrap">
  <div class="hs-track" id="hsTrack"></div>
  <div class="hs-dots" id="hsDots"></div>
  <button class="hs-arrow hs-prev" id="hsPrev" aria-label="Anterior">&#10094;</button>
  <button class="hs-arrow hs-next" id="hsNext" aria-label="Siguiente">&#10095;</button>
</div>

<!-- ═══════════════════════════════ HERO ═══════════════════════════════ -->
<section class="hero" id="hero">
  <div class="hero-bg"></div>
  <div class="hero-grid-lines"></div>
  <div class="particles" id="particles"></div>

  <div class="hero-content">
    <div class="hero-left">
      <div class="eyebrow">
        <span class="dot"></span>
        3 sorteos activos ahora mismo
      </div>
      <h1>Gana premios <span class="text-gradient">increíbles</span> con tu <?= htmlspecialchars($ticketLabel) ?> digital</h1>
      <p>Compra tu <?= htmlspecialchars($ticketLabel) ?> en segundos, recíbelo al instante en tu correo y participa del sorteo en vivo. 100% transparente y notariado.</p>
      <div class="hero-actions">
        <a href="sorteos.php" class="btn btn-primary btn-lg">🎟️ Ver Sorteos Activos</a>
        <a href="ganadores.php" class="btn btn-outline btn-lg">Ver Ganadores</a>
      </div>
      <div class="hero-stats" id="heroStats">
        <div class="hero-stat">
          <div class="num" id="statTotalSorteos">—</div>
          <div class="label">Sorteos activos</div>
        </div>
        <div class="hero-stat">
          <div class="num" id="statTicketsVendidos">—</div>
            <div class="label"><?= htmlspecialchars(ucfirst($ticketLabelP)) ?> vendidos</div>
        </div>
        <div class="hero-stat">
          <div class="num" id="statGanadores">—</div>
          <div class="label">Ganadores felices</div>
        </div>
        <div class="hero-stat">
          <div class="num">100%</div>
          <div class="label">Notariado</div>
        </div>
      </div>
    </div>
    <div class="hero-right">
      <div class="hero-card" id="heroFeaturedCard">
        <!-- Filled by JS -->
      </div>
    </div>
  </div>
</section>

<!-- ═══════════════════════════ TRUST BAND ═══════════════════════════ -->
<div class="trust-band">
  <div class="trust-band-inner" id="trustBand">
    <div class="trust-item"><span class="icon">✅</span> Sorteos Notariados</div>
    <div class="trust-item"><span class="icon">🔒</span> Pago 100% Seguro</div>
      <div class="trust-item"><span class="icon">⚡</span> <?= htmlspecialchars(ucfirst($ticketLabel)) ?> al Instante</div>
    <div class="trust-item"><span class="icon">📺</span> Sorteo en Vivo</div>
    <div class="trust-item"><span class="icon">🏆</span> Premio Garantizado</div>
    <div class="trust-item"><span class="icon">🇨🇱</span> Empresa Chilena</div>
    <div class="trust-item"><span class="icon">🎲</span> Números al Azar</div>
    <div class="trust-item"><span class="icon">📧</span> Soporte por Email</div>
    <!-- Duplicate for infinite scroll -->
    <div class="trust-item"><span class="icon">✅</span> Sorteos Notariados</div>
    <div class="trust-item"><span class="icon">🔒</span> Pago 100% Seguro</div>
      <div class="trust-item"><span class="icon">⚡</span> <?= htmlspecialchars(ucfirst($ticketLabel)) ?> al Instante</div>
    <div class="trust-item"><span class="icon">📺</span> Sorteo en Vivo</div>
    <div class="trust-item"><span class="icon">🏆</span> Premio Garantizado</div>
    <div class="trust-item"><span class="icon">🇨🇱</span> Empresa Chilena</div>
    <div class="trust-item"><span class="icon">🎲</span> Números al Azar</div>
    <div class="trust-item"><span class="icon">📧</span> Soporte por Email</div>
  </div>
</div>

<!-- ═══════════════════════════ ACTIVE RAFFLES ═══════════════════════════ -->
<section class="section container">
  <div class="section-header">
    <div class="badge">🎟️ Sorteos</div>
    <h2>Sorteos <span class="text-gradient">Activos</span></h2>
    <p>Elige el sorteo que más te guste y consigue tu <?= htmlspecialchars($ticketLabel) ?> antes que se agoten.</p>
  </div>

  <div class="tab-filters">
    <button class="tab-filter active" data-filter="active">En curso</button>
    <button class="tab-filter" data-filter="soon">Próximamente</button>
    <button class="tab-filter" data-filter="ended">Finalizados</button>
  </div>

  <div class="grid-3" id="rafflesGrid">
    <!-- Filled by JS -->
  </div>

  <div class="section-cta">
    <a href="sorteos.php" class="btn btn-outline">Ver todos los sorteos →</a>
  </div>
</section>

<!-- ═══════════════════════════ BENEFITS ═══════════════════════════ -->
<section class="section container">
  <div class="section-header">
    <div class="badge">⭐ Por qué elegirnos</div>
    <h2>Tu seguridad es <span class="text-gradient">nuestra prioridad</span></h2>
  </div>
  <div class="benefits-grid">
    <div class="benefit-item">
      <div class="benefit-icon">📜</div>
      <div>
        <h4>Sorteo Notariado</h4>
        <p>Certificación legal ante notario público en cada sorteo. Todo es auditable.</p>
      </div>
    </div>
    <div class="benefit-item">
      <div class="benefit-icon">📺</div>
      <div>
        <h4>Transmisión en Vivo</h4>
        <p>Mira el sorteo en directo desde cualquier lugar. Transparencia total.</p>
      </div>
    </div>
    <div class="benefit-item">
      <div class="benefit-icon">⚡</div>
      <div>
        <h4><?= htmlspecialchars(ucfirst($ticketLabel)) ?> Digital Inmediato</h4>
        <p>Recibe tu <?= htmlspecialchars($ticketLabel) ?> al instante por correo electrónico con número asignado al azar.</p>
      </div>
    </div>
    <div class="benefit-item">
      <div class="benefit-icon">🔒</div>
      <div>
        <h4>Pago Seguro</h4>
        <p>Transacciones protegidas y encriptadas. Aceptamos Webpay, Khipu y transferencia.</p>
      </div>
    </div>
    <div class="benefit-item">
      <div class="benefit-icon">🎁</div>
      <div>
        <h4>Premio Garantizado</h4>
        <p>El premio se entrega sin importar la cantidad de <?= htmlspecialchars($ticketLabelP) ?> vendidos. Siempre hay un ganador.</p>
      </div>
    </div>
    <div class="benefit-item">
      <div class="benefit-icon">🎲</div>
      <div>
        <h4>Números al Azar</h4>
        <p>Los números se asignan de forma completamente aleatoria y sin posibilidad de manipulación.</p>
      </div>
    </div>
  </div>
</section>

<!-- ═══════════════════════════ WINNERS PREVIEW ═══════════════════════════ -->
<section class="section" style="background: var(--bg-surface); border-top: 1px solid var(--border); border-bottom: 1px solid var(--border);">
  <div class="container">
    <div class="section-header">
      <div class="badge">🏆 Ganadores</div>
      <h2>Ganadores <span class="text-gradient">recientes</span></h2>
      <p>Personas reales que ganaron con Surteados. Todo verificado y documentado.</p>
    </div>
    <div class="grid-3" id="winnersPreview">
      <!-- Filled by JS -->
    </div>
    <div class="section-cta">
      <a href="ganadores.php" class="btn btn-outline">Ver todos los ganadores →</a>
    </div>
  </div>
</section>

<!-- ═══════════════════════════ CTA BANNER ═══════════════════════════ -->
<section class="section container">
  <div class="cta-banner">
    <div class="badge" style="margin: 0 auto 1rem;">🎟️ Participa Ahora</div>
    <h2>¿Listo para ganar?</h2>
    <p>Únete a miles de participantes. Tu próximo premio puede estar a un <?= htmlspecialchars($ticketLabel) ?> de distancia.</p>
    <a href="sorteos.php" class="btn btn-accent btn-lg">🎟️ Ver Sorteos Disponibles</a>
  </div>
</section>

<!-- ═══════════════════════════ FOOTER ═══════════════════════════ -->
<footer class="footer">
  <div class="container">
    <div class="footer-grid">
      <div class="footer-brand">
        <a href="index.php" class="navbar-logo" style="margin-bottom:.75rem; display:inline-flex;">
          <div class="logo-icon">🎟️</div>
          <span class="brand">Sur<em>tea</em>dos</span>
        </a>
        <p>Plataforma chilena de rifas digitales, seguros y transparentes. Premios reales con procesos claros y auditables.</p>
        <div class="social-links" style="margin-top:1rem;">
          <a href="#" class="social-link" title="Instagram">📸</a>
          <a href="#" class="social-link" title="TikTok">🎵</a>
          <a href="#" class="social-link" title="YouTube">▶️</a>
          <a href="#" class="social-link" title="Facebook">👥</a>
        </div>
      </div>
      <div class="footer-col">
        <h5>Sorteos</h5>
        <a href="sorteos.php">Sorteos activos</a>
        <a href="sorteos.php?filter=soon">Próximamente</a>
        <a href="ganadores.php">Ganadores</a>
        <a href="mis-tickets.php">Recuperar <?= htmlspecialchars($ticketLabelP) ?></a>
      </div>
      <div class="footer-col">
        <h5>Información</h5>
        <a href="como-participar.php">¿Cómo participar?</a>
        <a href="#beneficios">Beneficios</a>
        <a href="#">Bases legales</a>
        <a href="#">Política de privacidad</a>
      </div>
      <div class="footer-col">
        <h5>Contacto</h5>
        <a href="mailto:contacto@surteados.cl">📧 contacto@surteados.cl</a>
        <a href="#">💬 WhatsApp</a>
        <a href="panel/">⚙️ Admin</a>
      </div>
    </div>
    <div class="footer-bottom">
      <p>© 2026 Surteados. Todos los derechos reservados.</p>
      <div class="payment-methods">
        <span style="font-size:.78rem; color:var(--text-muted); margin-right:.25rem;">Medios de pago:</span>
        <span class="payment-badge">Webpay</span>
        <span class="payment-badge">Khipu</span>
        <span class="payment-badge">Flow</span>
        <span class="payment-badge">Transfer</span>
      </div>
    </div>
  </div>
</footer>

<!-- ═══════════════════════════ THEME PICKER ═══════════════════════════ -->
<div class="theme-picker" id="themePicker">
  <div class="theme-toggle" id="themeToggle" title="Personalizar colores">🎨</div>
  <div class="theme-panel" id="themePanel">
    <h4>🎨 Personalizar Tema</h4>
    <p class="text-xs text-muted mb-2">Presets:</p>
    <div class="theme-presets" id="themePresets">
      <button class="preset-btn active" data-preset="purple" style="background:linear-gradient(135deg,#7c3aed,#f59e0b);" title="Púrpura & Ámbar"></button>
      <button class="preset-btn" data-preset="blue" style="background:linear-gradient(135deg,#2563eb,#06b6d4);" title="Azul & Cyan"></button>
      <button class="preset-btn" data-preset="emerald" style="background:linear-gradient(135deg,#059669,#fbbf24);" title="Esmeralda & Oro"></button>
      <button class="preset-btn" data-preset="rose" style="background:linear-gradient(135deg,#e11d48,#f59e0b);" title="Rosa & Ámbar"></button>
      <button class="preset-btn" data-preset="indigo" style="background:linear-gradient(135deg,#4338ca,#c026d3);" title="Índigo & Magenta"></button>
      <button class="preset-btn" data-preset="teal" style="background:linear-gradient(135deg,#0d9488,#f97316);" title="Teal & Naranja"></button>
    </div>
    <div class="color-input-group">
      <label>Color primario</label>
      <div class="color-input-row">
        <input type="color" id="colorPrimary" value="#7c3aed">
        <input type="text" class="form-control" id="colorPrimaryHex" value="#7c3aed">
      </div>
    </div>
    <div class="color-input-group">
      <label>Color acento</label>
      <div class="color-input-row">
        <input type="color" id="colorAccent" value="#f59e0b">
        <input type="text" class="form-control" id="colorAccentHex" value="#f59e0b">
      </div>
    </div>
    <button class="btn btn-primary btn-sm btn-block mt-2" id="applyThemeBtn">Aplicar Tema</button>
    <button class="btn btn-ghost btn-sm btn-block mt-1" id="resetThemeBtn">Restaurar</button>
  </div>
</div>

<!-- ═══════════════════════════ PURCHASE MODAL ═══════════════════════════ -->
<div class="modal-backdrop" id="purchaseModal">
  <div class="modal">
    <div class="modal-top-bar"></div>
    <div class="modal-header">
      <span class="modal-title" id="modalTitle">Comprar <?= htmlspecialchars(ucfirst($ticketLabelP)) ?></span>
      <button class="modal-close" id="modalClose">✕</button>
    </div>
    <div class="modal-body">
      <!-- Steps indicator -->
      <div class="purchase-steps mb-3">
        <div class="purchase-step active" data-step="1">
          <div class="ps-num">1</div>
          <div class="ps-label">Pack</div>
        </div>
        <div class="purchase-step" data-step="2">
          <div class="ps-num">2</div>
          <div class="ps-label">Datos</div>
        </div>
        <div class="purchase-step" data-step="3">
          <div class="ps-num">3</div>
          <div class="ps-label">Pago</div>
        </div>
        <div class="purchase-step" data-step="4">
          <div class="ps-num">4</div>
          <div class="ps-label">¡Listo!</div>
        </div>
      </div>

      <!-- Step 1: Select Pack -->
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

      <!-- Step 1b: Browse more raffles (cart) -->
      <div class="step-panel" id="step1b">
        <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1rem;">
          <button class="btn btn-ghost btn-sm" id="step1bBack">← Volver</button>
          <h4 class="text-white" style="margin:0;">Agregar más sorteos</h4>
        </div>
        <!-- Cart bar -->
        <div id="cartBar" style="background:rgba(124,58,237,.18);border:1px solid rgba(124,58,237,.35);border-radius:.7rem;padding:.6rem 1rem;margin-bottom:1rem;display:flex;align-items:center;justify-content:space-between;gap:.5rem;">
          <div style="font-size:.82rem;color:var(--text-inv);"><span id="cartCount">0</span> sorteo(s) · <strong id="cartTotal" style="color:var(--color-accent);">$0</strong></div>
          <button class="btn btn-accent btn-sm" id="cartPayBtn" style="font-weight:800;">🔒 Pagar ahora</button>
        </div>
        <!-- Other raffles grid -->
        <div id="moreRafflesGrid" style="display:grid;grid-template-columns:1fr 1fr;gap:.65rem;max-height:340px;overflow-y:auto;"></div>
      </div>

      <!-- Step 2: Buyer info -->
      <div class="step-panel" id="step2">
        <h4 class="text-white mb-2">Tus datos</h4>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Nombre completo *</label>
            <input type="text" class="form-control" id="buyerName" placeholder="Juan Pérez" required>
          </div>
          <div class="form-group">
            <label class="form-label">Teléfono</label>
            <input type="tel" class="form-control" id="buyerPhone" placeholder="+56 9 1234 5678">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Dirección *</label>
            <input type="text" class="form-control" id="buyerAddress" placeholder="Av. Siempre Viva 123" required>
          </div>
          <div class="form-group">
            <label class="form-label">Comuna *</label>
            <input type="text" class="form-control" id="buyerComuna" placeholder="Santiago" required>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">RUT *</label>
          <input type="text" class="form-control" id="buyerRut" placeholder="12.345.678-5" autocomplete="off" required>
          <p class="form-hint">🇨🇱 Ingresa tu RUT chileno válido.</p>
        </div>
        <div class="form-group">
          <label class="form-label">Correo electrónico *</label>
          <input type="email" class="form-control" id="buyerEmail" placeholder="tu@correo.com" required>
          <p class="form-hint">📧 Aquí recibirás tus <?= htmlspecialchars($ticketLabelP) ?> digitales</p>
        </div>
        <div class="form-group">
          <label class="form-label">Confirmar correo *</label>
          <input type="email" class="form-control" id="buyerEmailConfirm" placeholder="tu@correo.com" required>
        </div>
        <div class="separator"></div>
        <div class="flex gap-2">
          <button class="btn btn-ghost" id="step2Back">← Volver</button>
          <button class="btn btn-primary" style="flex:1" id="step2Next">Continuar →</button>
        </div>
      </div>

      <!-- Step 3: Payment -->
      <div class="step-panel" id="step3">
        <h4 class="text-white mb-2">Resumen de compra</h4>
        <!-- Dynamic cart summary (filled by JS) -->
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
          <label class="card" style="cursor:pointer; padding:.875rem; display:flex; align-items:center; gap:.75rem; transition:all .2s; border-color:var(--color-primary);" id="pmFlow">
            <input type="radio" name="payMethod" value="flow" checked style="accent-color:var(--color-primary);">
            <div>
              <div style="font-weight:700; color:var(--text-inv); font-size:.875rem;">Flow</div>
              <div style="font-size:.75rem; color:var(--text-muted);">Pago único para todo el carrito</div>
            </div>
          </label>
        </div>
        <div class="separator"></div>
        <div class="flex gap-2">
          <button class="btn btn-ghost" id="step3Back">← Volver</button>
          <button class="btn btn-accent" style="flex:1; font-weight:800; font-size:1rem;" id="step3Pay">
            🔒 Pagar Ahora
          </button>
        </div>
        <button id="step3SimulateBtn" style="width:100%;margin-top:.55rem;padding:.5rem;background:rgba(255,200,0,.1);border:1px dashed rgba(255,200,0,.4);border-radius:.6rem;color:#f5c842;font-size:.78rem;cursor:pointer;">
          ⚡ Simular pago exitoso (sólo demo)
        </button>
        <p class="text-xs text-muted text-center mt-1">Transacción segura y encriptada</p>
      </div>

      <!-- Step 4: Success -->
      <div class="step-panel" id="step4">
        <div class="text-center" style="padding: 1rem 0;">
          <div style="font-size:4rem; margin-bottom:1rem; animation: float-anim 2s ease-in-out infinite;">🎉</div>
          <h3 class="text-white mb-2">¡Compra exitosa!</h3>
          <p id="step4Subtitle" class="mb-3">Tus <?= htmlspecialchars($ticketLabelP) ?> han sido asignados. Revisa tu correo.</p>

          <!-- Ticket detail moved to PDF page (filled by JS on link click) -->
          <div id="purchasedTickets" style="display:none;"></div>

          <div class="card" style="padding:1rem; text-align:left; margin-bottom:1.5rem;">
            <div class="flex-between mb-1">
              <span class="text-sm text-muted">📧 Enviado a:</span>
              <span class="text-sm text-white font-bold" id="confirmEmail">—</span>
            </div>
            <div class="flex-between">
              <span class="text-sm text-muted">💰 Total pagado:</span>
              <span class="text-sm text-accent font-bold" id="confirmAmount">—</span>
            </div>
          </div>

          <a href="#" id="verMisTicketsLink" class="btn btn-primary btn-block mb-2" target="_blank" rel="noopener">📄 Ver mis imágenes compradas</a>
          <button class="btn btn-ghost btn-block" id="closeAfterPurchase">Cerrar</button>
        </div>
      </div>
    </div>
  </div>
</div>

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

<div id="toast-container" class="toast-container"></div>

<script>window.SURTEADOS_DATA = <?= $initData ?>;</script>
<script src="assets/js/data.js?v=<?= filemtime(__DIR__.'/assets/js/data.js') ?>"></script>
<script src="assets/js/app.js?v=<?= filemtime(__DIR__.'/assets/js/app.js') ?>"></script>
<button class="cart-fab" onclick="openCartDrawer()" title="Ver carro">
  <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
  <span class="cart-fab-badge" id="cartFabCount"></span>
</button>
</body>
</html>
