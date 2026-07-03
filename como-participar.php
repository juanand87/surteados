<?php
require_once __DIR__ . '/api/config.php';
require_once __DIR__ . '/api/data_helper.php';
header('Content-Type: text/html; charset=UTF-8');
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
$ticketLabel  = $cfg['ticketLabel']       ?? 'imagen';
$ticketLabelP = $cfg['ticketLabelPlural'] ?? 'imagenes';
?><!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>¿Cómo participar? — Surteados</title>
  <meta name="description" content="Aprende a participar en los sorteos de Surteados. Es fácil, rápido y completamente seguro en solo 3 pasos.">
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

<!-- NAVBAR -->
<nav class="navbar" id="navbar">
  <div class="navbar-inner">
    <a href="index.php" class="navbar-logo">
      <?php if ($siteLogo): ?>
        <img src="<?= htmlspecialchars($siteLogo) ?>" alt="Logo" class="navbar-logo-img">
      <?php else: ?>
        <div class="logo-icon">&#127903;&#65039;</div>
        <span class="brand">Sur<em>tea</em>dos</span>
      <?php endif; ?>
    </a>
    <nav class="navbar-nav">
      <a href="index.php">Inicio</a>
      <a href="sorteos.php">Sorteos</a>
      <a href="como-participar.php" class="active">¿Cómo participar?</a>
      <a href="ganadores.php">Ganadores</a>
        <a href="mis-imagenes.php">Mis im&aacute;genes</a>
    </nav>
    <div class="navbar-actions">
      <a href="mis-imagenes.php#login" class="btn btn-outline btn-sm">Iniciar sesión</a>
      <a href="mis-imagenes.php#register" class="btn btn-primary btn-sm">Registrarse</a>
      <button class="cart-chip-btn" id="cartOpenBtn" onclick="openCartDrawer()">&#128722; Carro <span class="cart-chip-count" id="cartCountNav">0</span></button>
    </div>
    <button class="navbar-mobile-toggle" id="mobileToggle" aria-label="Menú">
      <span></span><span></span><span></span>
    </button>
  </div>
  <div class="mobile-nav" id="mobileNav">
    <a href="index.php">&#127968; Inicio</a>
    <a href="sorteos.php">&#127903;&#65039; Sorteos</a>
    <a href="como-participar.php" class="active">&#129517; ¿Cómo participar?</a>
    <a href="ganadores.php">&#127942; Ganadores</a>
      <a href="mis-imagenes.php">&#127915; Mis im&aacute;genes</a>
    <a href="panel/">&#9881;&#65039; Admin</a>
  </div>
</nav>

<div style="padding-top:68px;">

  <!-- PAGE HEADER -->
  <div class="page-header">
    <div class="container text-center">
      <div class="badge" style="margin:0 auto 1rem;display:inline-flex;">&#129517; Guía rápida</div>
      <h1>¿Cómo <span class="text-gradient">Participar?</span></h1>
      <p style="max-width:540px;margin:.75rem auto 0;color:var(--text-secondary);">Todo lo que necesitas saber: desde elegir tu sorteo hasta recibir tu número y conocer el resultado.</p>
    </div>
  </div>

  <!-- LIVE INFO BAR -->
  <section class="section how-participate" id="howParticipateSection" style="padding:1.75rem 0;">
    <div class="container">
      <div class="how-participate-live">
        <div class="hp-live-item">
          <span class="hp-live-label">Sorteo destacado ahora</span>
          <strong id="hpFeaturedTitle">Cargando...</strong>
        </div>
        <div class="hp-live-item">
          <span class="hp-live-label">Precio desde</span>
          <strong id="hpPriceFrom">—</strong>
        </div>
        <div class="hp-live-item">
          <span class="hp-live-label">Próximo sorteo</span>
          <strong id="hpDrawDate">—</strong>
        </div>
        <div class="hp-live-item">
          <span class="hp-live-label">Tu carrito</span>
          <strong id="hpCartCount">0 selecciones</strong>
        </div>
      </div>
    </div>
  </section>

  <!-- STEPS -->
  <section class="section container">
    <div class="section-header">
      <div class="badge">&#128203; Paso a paso</div>
      <h2>En <span class="text-gradient">3 pasos</span> estás participando</h2>
      <p>Todo el proceso tarda menos de 2 minutos. Sin registros ni contraseñas.</p>
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
          <p>Explora los sorteos activos. Cada uno muestra el premio, precio, cuántos <?= htmlspecialchars($ticketLabelP) ?> quedan y cuándo se realiza. Elige el que más te motive y selecciona tu pack.</p>
          <ul class="cp-tips">
            <li>&#128230; Packs con más <?= htmlspecialchars($ticketLabelP) ?> = más números = más posibilidades de ganar.</li>
            <li>&#127991;&#65039; Puedes agregar varios sorteos al carrito y pagar todo junto.</li>
            <li>&#9200; Los cupos son limitados — cuando se agota, se cierra la venta.</li>
          </ul>
          <a href="sorteos.php" class="btn btn-primary btn-sm cp-step-cta">Ver sorteos activos →</a>
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
          <p>Solo necesitas tu nombre y correo electrónico. El pago se procesa con <strong>Flow.cl</strong>, plataforma 100% segura. Acepta múltiples medios de pago.</p>
          <ul class="cp-tips">
            <li>&#128274; Transacción cifrada — tus datos nunca se almacenan en texto plano.</li>
            <li>&#128179; Webpay, Khipu, tarjeta de crédito/débito y transferencia.</li>
            <li>&#128231; Escribe bien tu correo — ahí recibirás tus <?= htmlspecialchars($ticketLabelP) ?> al instante.</li>
          </ul>
        </div>
      </div>

      <!-- Step 3 -->
      <div class="cp-step cp-step-last" data-step="3">
        <div class="cp-step-left">
          <div class="cp-step-num cp-num-green">3</div>
        </div>
        <div class="cp-step-body">
          <span class="cp-step-chip">¡Listo!</span>
          <h3>Recibe tus <?= htmlspecialchars($ticketLabelP) ?> y espera el sorteo en vivo</h3>
          <p>En segundos llega a tu correo la confirmación con tus número(s) asignados al azar. El día del sorteo se transmite en vivo y el resultado se publica en el sitio y redes sociales.</p>
          <ul class="cp-tips">
            <li>&#127915; Cada número es único — no se repiten dentro de un mismo sorteo.</li>
            <li>&#128250; El sorteo se realiza en directo con presencia notarial.</li>
            <li>&#127942; Si ganas, te avisamos por email y publicamos el resultado públicamente.</li>
          </ul>
          <div style="display:flex;gap:.75rem;flex-wrap:wrap;margin-top:.75rem;">
            <a href="sorteos.php" class="btn btn-accent btn-sm">Comprar ahora &#127903;&#65039;</a>
            <a href="mis-imagenes.php" class="btn btn-outline btn-sm">Ver mis <?= htmlspecialchars($ticketLabelP) ?></a>
          </div>
        </div>
      </div>

    </div>
  </section>

  <!-- FAQ -->
  <section class="section" style="background:var(--bg-surface);border-top:1px solid var(--border);border-bottom:1px solid var(--border);">
    <div class="container">
      <div class="section-header">
        <div class="badge">&#10067; Preguntas frecuentes</div>
        <h2>Todo lo que <span class="text-gradient">necesitas saber</span></h2>
      </div>

      <div class="faq-list">

        <details class="faq-item">
          <summary>¿Es legal participar en los sorteos de Surteados?</summary>
          <p>Sí. Todos nuestros sorteos están declarados ante notario y cumplen con la normativa chilena vigente para concursos y sorteos. Publicamos las bases legales de cada sorteo antes del inicio de la venta.</p>
        </details>

        <details class="faq-item">
          <summary>¿Cómo sé que el sorteo es transparente y no está arreglado?</summary>
          <p>El sorteo se realiza en vivo con transmisión pública, usando un sistema de selección aleatoria verificable. El acta notarial y el listado completo de participantes quedan disponibles después del sorteo para cualquier auditoría.</p>
        </details>

        <details class="faq-item">
          <summary>¿Necesito crear una cuenta para participar?</summary>
          <p>No es obligatorio. Puedes comprar solo con tus datos de contacto. Para revisar tus <?= htmlspecialchars($ticketLabelP) ?>, puedes ingresar en <a href="mis-imagenes.php">Mis im&aacute;genes</a> con un código enviado a tu correo o registrar tus datos.</p>
        </details>

        <details class="faq-item">
          <summary>¿Qué pasa si no recibo el correo con mis <?= htmlspecialchars($ticketLabelP) ?>?</summary>
          <p>Primero revisa la carpeta de spam o correo no deseado. Si tampoco está ahí, entra en <a href="mis-imagenes.php">Mis im&aacute;genes</a>, solicita un código de acceso a tu correo o inicia sesión con tu cuenta. Si el problema persiste, escríbenos a <a href="mailto:contacto@surteados.cl">contacto@surteados.cl</a>.</p>
        </details>

        <details class="faq-item">
          <summary>¿Puedo participar en más de un sorteo a la vez?</summary>
          <p>Sí. Puedes agregar varios sorteos al carrito y completar el pago en una sola transacción. Cada sorteo te asignará sus propios números de forma independiente.</p>
        </details>

        <details class="faq-item">
          <summary>¿Puedo pedir un reembolso si me arrepiento?</summary>
          <p>Una vez emitida la imagen numerada y asignado el número, no es posible hacer devoluciones, ya que el cupo queda reservado a tu nombre. Te recomendamos leer las bases legales antes de comprar.</p>
        </details>

        <details class="faq-item">
          <summary>¿Cómo me entero si gané?</summary>
          <p>Te notificamos directamente al correo con el que compraste. Además, publicamos el resultado en nuestras redes sociales y en la sección <a href="ganadores.php">Ganadores</a> del sitio, con video del sorteo incluido.</p>
        </details>

        <details class="faq-item">
          <summary>¿Puedo participar desde fuera de Chile?</summary>
          <p>Los premios físicos actualmente solo se entregan dentro de Chile. Las compras se procesan en pesos chilenos (CLP). Si tienes dudas, escríbenos antes de comprar.</p>
        </details>

      </div>
    </div>
  </section>

  <!-- CTA -->
  <section class="section container">
    <div class="cta-banner">
      <div class="badge" style="display:inline-flex;margin-bottom:1rem;">&#127903;&#65039; ¡Es tu turno!</div>
      <h2>Ya sabes todo.<br><span class="text-gradient">¡Empieza ahora!</span></h2>
      <p style="color:var(--text-secondary);max-width:440px;margin:0 auto 1.75rem;">Elige tu sorteo, selecciona tu pack y en menos de 2 minutos estarás participando por un premio increíble.</p>
      <a href="sorteos.php" class="btn btn-accent btn-lg">&#127903;&#65039; Ver Sorteos Activos</a>
    </div>
  </section>

</div><!-- /padding-top wrapper -->

<!-- FOOTER -->
<?php include __DIR__ . '/partials/site_footer.php'; ?>

<div class="cart-overlay" id="cartOverlay" onclick="closeCartDrawer()" style="display:none;position:fixed;inset:0;background:rgba(10,10,15,.55);z-index:1000;"></div>
<aside class="cart-drawer" id="cartDrawer" style="position:fixed;top:0;right:0;width:min(380px,94vw);height:100vh;z-index:1001;background:var(--bg-card);border-left:1px solid var(--border-strong);box-shadow:var(--shadow-lg);transform:translateX(110%);transition:transform .22s ease;display:flex;flex-direction:column;">
  <div class="cart-drawer-head">
    <strong>&#128722; Tu carro</strong>
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
<script src="assets/js/data.js"></script>
<script src="assets/js/app.js"></script>
<button class="cart-fab" onclick="openCartDrawer()" title="Ver carro">
  <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
  <span class="cart-fab-badge" id="cartFabCount"></span>
</button>
</body>
</html>
