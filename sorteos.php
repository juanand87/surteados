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
  <title>Sorteos — Surteados</title>
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
      <a href="mis-tickets.php#login" class="btn btn-outline btn-sm">Iniciar sesión</a>
      <a href="mis-tickets.php#register" class="btn btn-primary btn-sm">Registrarse</a>
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
  <div class="page-header">
    <div class="container text-center">
      <div class="badge" style="margin: 0 auto 1rem; display:inline-flex;">🎟️ Sorteos</div>
      <h1>Todos los <span class="text-gradient">Sorteos</span></h1>
      <p style="max-width:520px; margin: .75rem auto 0;">Explora todos nuestros sorteos activos, próximos y finalizados.</p>
    </div>
  </div>

  <section class="section container">
    <!-- Search & filters -->
    <div style="display:flex; gap:1rem; align-items:center; flex-wrap:wrap; margin-bottom:2rem;">
      <div style="flex:1; min-width:200px; position:relative;">
        <span style="position:absolute; left:.875rem; top:50%; transform:translateY(-50%); font-size:1rem;">🔍</span>
        <input type="text" id="searchInput" class="form-control" placeholder="Buscar sorteo..." style="padding-left:2.5rem;">
      </div>
      <div class="tab-filters" style="margin:0;">
        <button class="tab-filter active" data-filter="all">Todos</button>
        <button class="tab-filter" data-filter="active">Activos</button>
        <button class="tab-filter" data-filter="soon">Próximos</button>
        <button class="tab-filter" data-filter="ended">Finalizados</button>
      </div>
    </div>

    <!-- Raffles grid -->
    <div class="grid-3" id="sorteosList"></div>
  </section>
</div>

<!-- PURCHASE MODAL (reused from index) -->
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
        <div class="form-group" id="buyerTermsWrap">
          <label class="form-label" style="display:flex;gap:.55rem;align-items:flex-start;line-height:1.4;">
            <input type="checkbox" id="buyerTermsAccepted" style="margin-top:.2rem;accent-color:var(--color-primary);">
            <span>
              Estoy de acuerdo con las politicas de compra.
              <a href="#" id="buyerViewPoliciesLink" style="margin-left:.2rem;">(Ver politicas)</a>
            </span>
          </label>
        </div>
        <div class="card" id="buyerPoliciesPanel" style="display:none;padding:.85rem 1rem;margin-bottom:.75rem;max-height:340px;overflow:auto;">
          <h5 style="margin:0 0 .5rem;color:var(--text-inv);font-size:.92rem;">Politicas de compra (demo)</h5>
          <p class="text-sm" style="margin:0 0 .55rem;">Las compras en Surteados son digitales y la confirmacion se envia al correo ingresado por el cliente en el proceso de pago.</p>
          <p class="text-sm" style="margin:0 0 .55rem;">Es responsabilidad del comprador revisar que nombre, correo, RUT y demas datos esten correctos antes de confirmar el pago.</p>
          <p class="text-sm" style="margin:0 0 .55rem;">Una vez aprobado el pago, la asignacion de numeros se realiza de forma automatica y no puede modificarse manualmente.</p>
          <p class="text-sm" style="margin:0 0 .55rem;">Las compras confirmadas no son anulables ni transferibles, salvo en los casos exigidos por la normativa aplicable.</p>
          <p class="text-sm" style="margin:0 0 .55rem;">Si no recibes el correo de confirmacion, revisa spam o correo no deseado y luego contactanos para asistencia.</p>
          <p class="text-sm" style="margin:0 0 .55rem;">El participante acepta que este proceso corresponde a productos digitales y comprende los plazos y condiciones del sorteo publicado.</p>
          <p class="text-sm" style="margin:0 0 .8rem;">Para dudas de soporte, puedes escribir a nuestro canal de contacto y te ayudaremos a validar tu compra.</p>
          <div style="position:sticky;bottom:0;padding-top:.5rem;margin-top:.35rem;background:var(--bg-card);border-top:1px solid rgba(255,255,255,.14);">
            <button type="button" class="btn btn-sm" id="buyerPoliciesBackBtn" style="width:100%;font-weight:800;border:1px solid var(--color-primary);background:rgba(124,58,237,.2);color:var(--text-inv);">← Volver al pago</button>
          </div>
        </div>
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
          <label class="card" style="cursor:pointer; padding:.875rem; display:flex; align-items:center; gap:.75rem; border-color:var(--color-primary);"><input type="radio" name="payMethod" value="flow" checked style="accent-color:var(--color-primary);"><div><div style="font-weight:700; color:var(--text-inv); font-size:.875rem;">Flow</div><div style="font-size:.75rem; color:var(--text-muted);">Pago único para todo el carrito</div></div></label>
        </div>
        <div class="separator"></div>
        <div class="flex gap-2">
          <button class="btn btn-ghost" id="step3Back">← Volver</button>
          <button class="btn btn-accent" style="flex:1; font-weight:800; font-size:1rem;" id="step3Pay">🔒 Pagar Ahora</button>
        </div>
        <button id="step3SimulateBtn" style="width:100%;margin-top:.55rem;padding:.5rem;background:rgba(255,200,0,.1);border:1px dashed rgba(255,200,0,.4);border-radius:.6rem;color:#f5c842;font-size:.78rem;cursor:pointer;">
          ⚡ Simular pago exitoso (sólo demo)
        </button>
        <p class="text-xs text-muted text-center mt-1">Transacción segura y encriptada</p>
      </div>
      <div class="step-panel" id="step4">
        <div class="text-center" style="padding:1rem 0;">
          <div style="font-size:4rem; margin-bottom:1rem;">🎉</div>
          <h3 class="text-white mb-2">¡Compra exitosa!</h3>
          <p id="step4Subtitle" class="mb-3">Tus <?= htmlspecialchars($ticketLabelP) ?> han sido asignados. Revisa tu correo.</p>
          <div id="purchasedTickets" style="display:none;"></div>
          <div class="card" style="padding:1rem; text-align:left; margin-bottom:1.5rem;">
            <div class="flex-between mb-1"><span class="text-sm text-muted">📧 Enviado a:</span><span class="text-sm text-white font-bold" id="confirmEmail">—</span></div>
            <div class="flex-between"><span class="text-sm text-muted">💰 Total pagado:</span><span class="text-sm text-accent font-bold" id="confirmAmount">—</span></div>
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

<div class="theme-picker" id="themePicker">
  <div class="theme-toggle" id="themeToggle" title="Personalizar colores">🎨</div>
  <div class="theme-panel" id="themePanel">
    <h4>🎨 Personalizar Tema</h4>
    <p class="text-xs text-muted mb-2">Presets:</p>
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
    <button class="btn btn-primary btn-sm btn-block mt-2" id="applyThemeBtn">Aplicar Tema</button>
    <button class="btn btn-ghost btn-sm btn-block mt-1" id="resetThemeBtn">Restaurar</button>
  </div>
</div>

<div id="toast-container" class="toast-container"></div>
<script>window.SURTEADOS_DATA = <?= $initData ?>;</script>
<script src="assets/js/data.js"></script>
<script src="assets/js/app.js"></script>
<script>
  // Render sorteos
  (function() {
    const list = document.getElementById('sorteosList');
    const searchInput = document.getElementById('searchInput');
    const filterBtns = document.querySelectorAll('.tab-filter');
    let filterStatus = 'all';
    let searchQuery = '';

    function render() {
      let raffles = db.getRaffles();
      if (filterStatus !== 'all') raffles = raffles.filter(r => r.status === filterStatus);
      if (searchQuery) raffles = raffles.filter(r => r.title.toLowerCase().includes(searchQuery) || r.category.toLowerCase().includes(searchQuery));
      if (raffles.length === 0) {
        list.innerHTML = `<div class="empty-state" style="grid-column:1/-1;"><div class="empty-icon">🎟️</div><p>No se encontraron sorteos.</p></div>`;
        return;
      }
      list.innerHTML = raffles.map(r => buildRaffleCard(r)).join('');
      setTimeout(() => {
        list.querySelectorAll('.progress-fill[data-pct]').forEach(el => el.style.width = el.dataset.pct + '%');
      }, 100);
    }

    filterBtns.forEach(btn => {
      btn.addEventListener('click', () => {
        filterBtns.forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        filterStatus = btn.dataset.filter;
        render();
      });
    });

    searchInput?.addEventListener('input', () => {
      searchQuery = searchInput.value.toLowerCase().trim();
      render();
    });

    render();
  })();
</script>
<button class="cart-fab" onclick="openCartDrawer()" title="Ver carro">
  <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
  <span class="cart-fab-badge" id="cartFabCount"></span>
</button>
</body>
</html>
