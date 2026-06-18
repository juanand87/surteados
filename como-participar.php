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
  <title>Â¿CÃ³mo participar? â€” Surteados</title>
  <meta name="description" content="Aprende a participar en los sorteos de Surteados. Es fÃ¡cil, rÃ¡pido y completamente seguro en solo 3 pasos.">
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

<!-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• NAVBAR â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
<nav class="navbar" id="navbar">
  <div class="navbar-inner">
    <a href="index.php" class="navbar-logo">
      <?php if ($siteLogo): ?>
        <img src="<?= htmlspecialchars($siteLogo) ?>" alt="Logo" class="navbar-logo-img">
      <?php else: ?>
        <div class="logo-icon">ðŸŽŸï¸</div>
        <span class="brand">Sur<em>tea</em>dos</span>
      <?php endif; ?>
    </a>
    <nav class="navbar-nav">
      <a href="index.php">Inicio</a>
      <a href="sorteos.php">Sorteos</a>
      <a href="como-participar.php" class="active">Â¿CÃ³mo participar?</a>
      <a href="ganadores.php">Ganadores</a>
        <a href="mis-imagenes.php">Mis im&aacute;genes</a>
    </nav>
    <div class="navbar-actions">
      <a href="mis-imagenes.php#login" class="btn btn-outline btn-sm">Iniciar sesiÃ³n</a>
      <a href="mis-imagenes.php#register" class="btn btn-primary btn-sm">Registrarse</a>
      <button class="cart-chip-btn" id="cartOpenBtn" onclick="openCartDrawer()">ðŸ›’ Carro <span class="cart-chip-count" id="cartCountNav">0</span></button>
    </div>
    <button class="navbar-mobile-toggle" id="mobileToggle" aria-label="MenÃº">
      <span></span><span></span><span></span>
    </button>
  </div>
  <div class="mobile-nav" id="mobileNav">
    <a href="index.php">ðŸ  Inicio</a>
    <a href="sorteos.php">ðŸŽŸï¸ Sorteos</a>
    <a href="como-participar.php" class="active">ðŸ§­ Â¿CÃ³mo participar?</a>
    <a href="ganadores.php">ðŸ† Ganadores</a>
      <a href="mis-imagenes.php">ðŸŽ« Mis im&aacute;genes</a>
    <a href="panel/">âš™ï¸ Admin</a>
  </div>
</nav>

<div style="padding-top:68px;">

  <!-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• PAGE HEADER â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
  <div class="page-header">
    <div class="container text-center">
      <div class="badge" style="margin:0 auto 1rem;display:inline-flex;">ðŸ§­ GuÃ­a rÃ¡pida</div>
      <h1>Â¿CÃ³mo <span class="text-gradient">Participar?</span></h1>
      <p style="max-width:540px;margin:.75rem auto 0;color:var(--text-secondary);">Todo lo que necesitas saber: desde elegir tu sorte hasta recibir tu nÃºmero y conocer el resultado.</p>
    </div>
  </div>

  <!-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• LIVE INFO BAR â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
  <section class="section how-participate" id="howParticipateSection" style="padding:1.75rem 0;">
    <div class="container">
      <div class="how-participate-live">
        <div class="hp-live-item">
          <span class="hp-live-label">Sorteo destacado ahora</span>
          <strong id="hpFeaturedTitle">Cargando...</strong>
        </div>
        <div class="hp-live-item">
          <span class="hp-live-label">Precio desde</span>
          <strong id="hpPriceFrom">â€”</strong>
        </div>
        <div class="hp-live-item">
          <span class="hp-live-label">PrÃ³ximo sorteo</span>
          <strong id="hpDrawDate">â€”</strong>
        </div>
        <div class="hp-live-item">
          <span class="hp-live-label">Tu carrito</span>
          <strong id="hpCartCount">0 selecciones</strong>
        </div>
      </div>
    </div>
  </section>

  <!-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• STEPS â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
  <section class="section container">
    <div class="section-header">
      <div class="badge">ðŸ“‹ Paso a paso</div>
      <h2>En <span class="text-gradient">3 pasos</span> estÃ¡s participando</h2>
      <p>Todo el proceso tarda menos de 2 minutos. Sin registros ni contraseÃ±as.</p>
    </div>

    <div class="cp-steps">

      <!-- Step 1 -->
      <div class="cp-step" data-step="1">
        <div class="cp-step-left">
          <div class="cp-step-num">1</div>
          <div class="cp-step-line"></div>
        </div>
        <div class="cp-step-body">
          <span class="cp-step-chip">Elegir</span>
          <h3>Elige tu sorteo y pack de <?= htmlspecialchars($ticketLabelP) ?></h3>
          <p>Explora los sorteos activos. Cada uno muestra el premio, precio, cuÃ¡ntos <?= htmlspecialchars($ticketLabelP) ?> quedan y cuÃ¡ndo se realiza. Elige el que mÃ¡s te motive y selecciona tu pack.</p>
          <ul class="cp-tips">
            <li>ðŸ“¦ Packs con mÃ¡s <?= htmlspecialchars($ticketLabelP) ?> = mÃ¡s nÃºmeros = mÃ¡s posibilidades de ganar.</li>
            <li>ðŸ·ï¸ Puedes agregar varios sorteos al carrito y pagar todo junto.</li>
            <li>â° Los cupos son limitados â€” cuando se agota, se cierra la venta.</li>
          </ul>
          <a href="sorteos.php" class="btn btn-primary btn-sm cp-step-cta">Ver sorteos activos â†’</a>
        </div>
      </div>

      <!-- Step 2 -->
      <div class="cp-step" data-step="2">
        <div class="cp-step-left">
          <div class="cp-step-num cp-num-accent">2</div>
          <div class="cp-step-line"></div>
        </div>
        <div class="cp-step-body">
          <span class="cp-step-chip">Pagar</span>
          <h3>Ingresa tus datos y paga de forma segura</h3>
          <p>Solo necesitas tu nombre y correo electrÃ³nico. El pago se procesa con <strong>Flow.cl</strong>, plataforma 100% segura. Acepta mÃºltiples medios de pago.</p>
          <ul class="cp-tips">
            <li>ðŸ”’ TransacciÃ³n cifrada â€” tus datos nunca se almacenan en texto plano.</li>
            <li>ðŸ’³ Webpay, Khipu, tarjeta de crÃ©dito/dÃ©bito y transferencia.</li>
            <li>ðŸ“§ Escribe bien tu correo â€” ahÃ­ recibirÃ¡s tus <?= htmlspecialchars($ticketLabelP) ?> al instante.</li>
          </ul>
        </div>
      </div>

      <!-- Step 3 -->
      <div class="cp-step cp-step-last" data-step="3">
        <div class="cp-step-left">
          <div class="cp-step-num cp-num-green">3</div>
        </div>
        <div class="cp-step-body">
          <span class="cp-step-chip">Â¡Listo!</span>
          <h3>Recibe tus <?= htmlspecialchars($ticketLabelP) ?> y espera el sorteo en vivo</h3>
          <p>En segundos llega a tu correo la confirmaciÃ³n con tus nÃºmero(s) asignados al azar. El dÃ­a del sorteo se transmite en vivo y el resultado se publica en el sitio y redes sociales.</p>
          <ul class="cp-tips">
            <li>ðŸŽ« Cada nÃºmero es Ãºnico â€” no se repiten dentro de un mismo sorteo.</li>
            <li>ðŸ“º El sorteo se realiza en directo con presencia notarial.</li>
            <li>ðŸ† Si ganas, te avisamos por email y publicamos el resultado pÃºblicamente.</li>
          </ul>
          <div style="display:flex;gap:.75rem;flex-wrap:wrap;margin-top:.75rem;">
            <a href="sorteos.php" class="btn btn-accent btn-sm">Comprar ahora ðŸŽŸï¸</a>
            <a href="mis-imagenes.php" class="btn btn-outline btn-sm">Ver mis <?= htmlspecialchars($ticketLabelP) ?></a>
          </div>
        </div>
      </div>

    </div>
  </section>

  <!-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• FAQ â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
  <section class="section" style="background:var(--bg-surface);border-top:1px solid var(--border);border-bottom:1px solid var(--border);">
    <div class="container">
      <div class="section-header">
        <div class="badge">â“ Preguntas frecuentes</div>
        <h2>Todo lo que <span class="text-gradient">necesitas saber</span></h2>
      </div>

      <div class="faq-list">

        <details class="faq-item">
          <summary>Â¿Es legal participar en los sorteos de Surteados?</summary>
          <p>SÃ­. Todos nuestros sorteos estÃ¡n declarados ante notario y cumplen con la normativa chilena vigente para concursos y rifas. Publicamos las bases legales de cada sorteo antes del inicio de la venta.</p>
        </details>

        <details class="faq-item">
          <summary>Â¿CÃ³mo sÃ© que el sorteo es transparente y no estÃ¡ arreglado?</summary>
          <p>El sorteo se realiza en vivo con transmisiÃ³n pÃºblica, usando un sistema de selecciÃ³n aleatoria verificable. El acta notarial y el listado completo de participantes quedan disponibles despuÃ©s del sorteo para cualquier auditorÃ­a.</p>
        </details>

        <details class="faq-item">
          <summary>Â¿Necesito crear una cuenta para participar?</summary>
          <p>No es obligatorio. Puedes comprar solo con tus datos de contacto. Para revisar tus <?= htmlspecialchars($ticketLabelP) ?>, puedes ingresar en <a href="mis-imagenes.php">Mis im&aacute;genes</a> con un cÃ³digo enviado a tu correo o registrar tus datos.</p>
        </details>

        <details class="faq-item">
          <summary>Â¿QuÃ© pasa si no recibo el correo con mis <?= htmlspecialchars($ticketLabelP) ?>?</summary>
          <p>Primero revisa la carpeta de spam o correo no deseado. Si tampoco estÃ¡ ahÃ­, entra en <a href="mis-imagenes.php">Mis im&aacute;genes</a>, solicita un cÃ³digo de acceso a tu correo o inicia sesiÃ³n con tu cuenta. Si el problema persiste, escrÃ­benos a <a href="mailto:contacto@surteados.cl">contacto@surteados.cl</a>.</p>
        </details>

        <details class="faq-item">
          <summary>Â¿Puedo participar en mÃ¡s de un sorteo a la vez?</summary>
          <p>SÃ­. Puedes agregar varios sorteos al carrito y completar el pago en una sola transacciÃ³n. Cada sorteo te asignarÃ¡ sus propios nÃºmeros de forma independiente.</p>
        </details>

        <details class="faq-item">
          <summary>Â¿Puedo pedir un reembolso si me arrepiento?</summary>
          <p>Una vez emitido el <?= htmlspecialchars($ticketLabel) ?> y asignado el nÃºmero, no es posible hacer devoluciones, ya que el cupo queda reservado a tu nombre. Te recomendamos leer las bases legales antes de comprar.</p>
        </details>

        <details class="faq-item">
          <summary>Â¿CÃ³mo me entero si ganÃ©?</summary>
          <p>Te notificamos directamente al correo con el que compraste. AdemÃ¡s, publicamos el resultado en nuestras redes sociales y en la secciÃ³n <a href="ganadores.php">Ganadores</a> del sitio, con video del sorteo incluido.</p>
        </details>

        <details class="faq-item">
          <summary>Â¿Puedo participar desde fuera de Chile?</summary>
          <p>Los premios fÃ­sicos actualmente solo se entregan dentro de Chile. Las compras se procesan en pesos chilenos (CLP). Si tienes dudas, escrÃ­benos antes de comprar.</p>
        </details>

      </div>
    </div>
  </section>

  <!-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• CTA â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
  <section class="section container">
    <div class="cta-banner">
      <div class="badge" style="display:inline-flex;margin-bottom:1rem;">ðŸŽŸï¸ Â¡Es tu turno!</div>
      <h2>Ya sabes todo.<br><span class="text-gradient">Â¡Empieza ahora!</span></h2>
      <p style="color:var(--text-secondary);max-width:440px;margin:0 auto 1.75rem;">Elige tu sorteo, selecciona tu pack y en menos de 2 minutos estarÃ¡s participando por un premio increÃ­ble.</p>
      <a href="sorteos.php" class="btn btn-accent btn-lg">ðŸŽŸï¸ Ver Sorteos Activos</a>
    </div>
  </section>

</div><!-- /padding-top wrapper -->

<!-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• FOOTER â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
<?php include __DIR__ . '/partials/site_footer.php'; ?>

<div class="cart-overlay" id="cartOverlay" onclick="closeCartDrawer()" style="display:none;position:fixed;inset:0;background:rgba(10,10,15,.55);z-index:1000;"></div>
<aside class="cart-drawer" id="cartDrawer" style="position:fixed;top:0;right:0;width:min(380px,94vw);height:100vh;z-index:1001;background:var(--bg-card);border-left:1px solid var(--border-strong);box-shadow:var(--shadow-lg);transform:translateX(110%);transition:transform .22s ease;display:flex;flex-direction:column;">
  <div class="cart-drawer-head">
    <strong>ðŸ›’ Tu carro</strong>
    <button class="btn btn-ghost btn-sm" onclick="closeCartDrawer()">Cerrar</button>
  </div>
  <div class="cart-drawer-body" id="cartDrawerBody">
    <p class="text-sm text-muted">Tu carro estÃ¡ vacÃ­o.</p>
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
  <div class="theme-toggle" id="themeToggle" title="Personalizar colores">ðŸŽ¨</div>
  <div class="theme-panel" id="themePanel">
    <h4>ðŸŽ¨ Personalizar Tema</h4>
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
<button class="cart-fab" onclick="openCartDrawer()" title="Ver carro">
  <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
  <span class="cart-fab-badge" id="cartFabCount"></span>
</button>
</body>
</html>
