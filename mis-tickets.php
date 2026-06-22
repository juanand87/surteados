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
$ticketLabel  = $cfg['ticketLabel']       ?? 'imagen';
$ticketLabelP = $cfg['ticketLabelPlural'] ?? 'imagenes';
?><!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Mis imágenes — Surteados</title>
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
      <a href="ganadores.php">Ganadores</a>
      <a href="mis-imagenes.php" class="active">Mis imágenes</a>
    </nav>
    <div class="navbar-actions">
      <a href="sorteos.php" class="btn btn-primary btn-sm">Participar 🎟️</a>
    </div>
    <button class="navbar-mobile-toggle" id="mobileToggle"><span></span><span></span><span></span></button>
  </div>
  <div class="mobile-nav" id="mobileNav">
    <a href="index.php">🏠 Inicio</a>
    <a href="sorteos.php">🎟️ Sorteos</a>
    <a href="como-participar.php">🧭 ¿Cómo participar?</a>
    <a href="ganadores.php">🏆 Ganadores</a>
    <a href="mis-imagenes.php">🎫 Mis imágenes</a>
  </div>
</nav>

<div style="padding-top:68px;">
  <div class="page-header">
    <div class="container text-center">
      <div class="badge" style="margin:0 auto 1rem; display:inline-flex;">🎫 Mis imágenes</div>
      <h1>Consultar <span class="text-gradient">Mis imágenes</span></h1>
      <p style="max-width:640px; margin:.75rem auto 0;">Para proteger tu información, accede con un código enviado a tu correo o con tu cuenta existente.</p>
    </div>
  </div>

  <section class="section container account-page-container">
    <!-- Auth form -->
    <div class="card mb-4" id="authCard" style="padding:2rem;">
      <h3 class="text-white mb-2">🔐 Acceder a mis <?= htmlspecialchars($ticketLabelP) ?></h3>
      <p class="mb-3">Elige tu método de acceso:</p>

      <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1rem;">
        <button class="btn btn-ghost btn-sm" id="tabCode">Código por correo</button>
        <button class="btn btn-ghost btn-sm" id="tabLogin">Iniciar sesión</button>
        <button class="btn btn-ghost btn-sm" id="tabRegister">Registrarme</button>
      </div>

      <div id="panelCode">
        <div id="codeRequestStep">
          <div class="form-group">
            <label class="form-label">Correo electrónico de compra *</label>
            <input type="email" id="authEmail" class="form-control" placeholder="tu@correo.com" autocomplete="email">
          </div>
          <button class="btn btn-primary" id="sendCodeBtn">📧 Enviar código</button>
          <p class="form-hint mt-1" id="codeHint">Te enviaremos un código temporal al correo asociado a tu compra.</p>
        </div>

        <div id="codeVerifyStep" class="hidden">
          <div class="empty-state" style="padding:1.5rem;">
            <div class="empty-icon">📬</div>
            <h3 class="text-white mb-1">Revisa tu correo</h3>
            <p id="codeSentText">Enviamos un código de acceso. Escríbelo aquí para ver tus imágenes compradas.</p>
            <div style="display:flex;gap:.6rem;flex-wrap:wrap;align-items:center;justify-content:center;margin-top:1rem;">
              <input type="text" id="authCode" class="form-control" placeholder="Código de 6 dígitos" maxlength="6" style="max-width:220px;" inputmode="numeric">
              <button class="btn btn-accent" id="verifyCodeBtn">✅ Verificar código</button>
            </div>
            <button type="button" class="btn btn-ghost btn-sm mt-2" id="changeEmailBtn">Usar otro correo</button>
          </div>
        </div>
      </div>

      <div id="panelLogin" class="hidden">
        <button type="button" class="btn btn-outline btn-block mb-3 google-auth-btn" id="googleLoginBtn"><span class="google-g">G</span> Continuar con Google</button>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Usuario o correo *</label><input type="text" id="loginIdentifier" class="form-control" placeholder="usuario o correo"></div>
          <div class="form-group"><label class="form-label">Contraseña *</label><input type="password" id="loginPassword" class="form-control" placeholder="********"></div>
        </div>
        <button class="btn btn-primary" id="loginBtn">Ingresar</button>
      </div>

      <div id="panelRegister" class="hidden">
        <div id="registerFormStep">
          <button type="button" class="btn btn-outline btn-block mb-3 google-auth-btn" id="googleRegisterBtn"><span class="google-g">G</span> Registrarme con Google</button>
          <div class="form-row">
            <div class="form-group"><label class="form-label">Nombre completo *</label><input type="text" id="regFullName" class="form-control" placeholder="Juan Pérez"></div>
            <div class="form-group"><label class="form-label">Teléfono *</label><input type="tel" id="regPhone" class="form-control" placeholder="+56 9 1234 5678"></div>
          </div>
          <div class="form-row">
            <div class="form-group"><label class="form-label">Dirección *</label><input type="text" id="regAddress" class="form-control" placeholder="Av. Siempre Viva 123"></div>
            <div class="form-group">
              <label class="form-label">Región *</label>
              <select class="form-control" id="buyerRegion" required>
                <option value="">Selecciona tu región</option>
              </select>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Comuna / ciudad *</label>
            <select class="form-control" id="buyerComuna" required disabled>
              <option value="">Primero selecciona una región</option>
            </select>
          </div>
          <div class="form-group"><label class="form-label">RUT *</label><input type="text" id="regRut" class="form-control" placeholder="12.345.678-5" autocomplete="off"><p class="form-hint">🇨🇱 Ingresa tu RUT chileno válido.</p></div>
          <div class="form-row">
            <div class="form-group"><label class="form-label">Correo electrónico *</label><input type="email" id="regEmail" class="form-control" placeholder="tu@correo.com"></div>
            <div class="form-group"><label class="form-label">Repetir correo electrónico *</label><input type="email" id="regEmail2" class="form-control" placeholder="tu@correo.com"></div>
          </div>
          <div class="form-row">
            <div class="form-group"><label class="form-label">Clave *</label><input type="password" id="regPassword" class="form-control" placeholder="Mínimo 8 caracteres" autocomplete="new-password"></div>
            <div class="form-group"><label class="form-label">Repetir clave *</label><input type="password" id="regPassword2" class="form-control" placeholder="Repite la clave" autocomplete="new-password"></div>
          </div>
          <div class="form-group">
            <label class="form-label">Captcha *</label>
            <div style="display:flex;gap:.6rem;align-items:center;flex-wrap:wrap;">
              <span class="pill pill-purple" id="regCaptchaQuestion">Cargando...</span>
              <input type="number" id="regCaptcha" class="form-control" placeholder="Respuesta" style="max-width:160px;">
              <button type="button" class="btn btn-ghost btn-sm" id="refreshCaptchaBtn">Cambiar</button>
            </div>
          </div>
          <button class="btn btn-primary" id="registerBtn">Crear cuenta</button>
        </div>

        <div id="registerVerifyStep" class="hidden">
          <div class="empty-state" style="padding:1.5rem;">
            <div class="empty-icon">✉️</div>
            <h3 class="text-white mb-1">Confirma tu correo</h3>
            <p id="registerVerifyText">Te enviamos un código para activar tu cuenta.</p>
            <div style="display:flex;gap:.6rem;flex-wrap:wrap;align-items:center;justify-content:center;margin-top:1rem;">
              <input type="text" id="registerCode" class="form-control" placeholder="Código de 6 dígitos" maxlength="6" style="max-width:220px;" inputmode="numeric">
              <button class="btn btn-accent" id="verifyRegisterBtn">Activar cuenta</button>
            </div>
            <button type="button" class="btn btn-ghost btn-sm mt-2" id="editRegisterBtn">Editar mis datos</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Results -->
    <div id="resultsSection" class="hidden">
      <div class="card mb-4" style="padding:1.25rem;">
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1rem;">
          <button class="btn btn-primary btn-sm account-tab-btn" data-account-tab="datos">Mis datos</button>
          <button class="btn btn-ghost btn-sm account-tab-btn" data-account-tab="imagenes">Mis imágenes</button>
          <button class="btn btn-ghost btn-sm account-tab-btn" data-account-tab="sorteos">Sorteos</button>
        </div>

        <div id="accountDatosPanel">
          <div class="flex-between mb-2">
            <h3 class="text-white">Mis datos</h3>
            <button class="btn btn-outline btn-sm" id="editProfileBtn">Editar datos</button>
          </div>
          <p class="form-hint mb-3 hidden" id="profileMissingHint">Completa tus datos para participar más rápido en próximos sorteos.</p>
          <div class="grid-2" id="accountProfileGrid"></div>

          <form id="accountProfileForm" class="hidden" style="margin-top:1rem;">
            <div class="form-row">
              <div class="form-group"><label class="form-label">Nombre completo *</label><input type="text" id="accFullName" class="form-control"></div>
              <div class="form-group"><label class="form-label">Teléfono *</label><input type="tel" id="accPhone" class="form-control"></div>
            </div>
            <div class="form-row">
              <div class="form-group"><label class="form-label">Dirección *</label><input type="text" id="accAddress" class="form-control"></div>
              <div class="form-group"><label class="form-label">RUT *</label><input type="text" id="accRut" class="form-control" placeholder="12.345.678-5"></div>
            </div>
            <div class="form-row">
              <div class="form-group"><label class="form-label">Región *</label><select class="form-control" id="accRegion"><option value="">Selecciona tu región</option></select></div>
              <div class="form-group"><label class="form-label">Comuna / ciudad *</label><select class="form-control" id="accComuna" disabled><option value="">Primero selecciona una región</option></select></div>
            </div>
            <div style="display:flex;gap:.6rem;flex-wrap:wrap;">
              <button type="submit" class="btn btn-primary" id="saveProfileBtn">Guardar datos</button>
              <button type="button" class="btn btn-ghost" id="cancelProfileBtn">Cancelar</button>
            </div>
          </form>
        </div>
      </div>

      <div id="accountImagenesPanel">
      <div class="flex-between mb-3">
        <h3 class="text-white" id="resultsTitle">Tus <?= htmlspecialchars($ticketLabelP) ?></h3>
        <div style="display:flex;gap:.55rem;align-items:center;flex-wrap:wrap;justify-content:flex-end;">
          <span class="pill pill-purple" id="resultsCount">0 <?= htmlspecialchars($ticketLabelP) ?></span>
          <button class="btn btn-ghost btn-sm" id="logoutBtn">Cerrar sesión</button>
        </div>
      </div>

      <!-- Grouped by raffle -->
      <div id="ticketsList"></div>
      </div>

      <div id="accountSorteosPanel" class="hidden">
        <div class="flex-between mb-3">
          <h3 class="text-white">Sorteos</h3>
          <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
            <button class="btn btn-primary btn-sm raffle-filter-btn" data-filter="active">Vigentes</button>
            <button class="btn btn-ghost btn-sm raffle-filter-btn" data-filter="all">Todos</button>
          </div>
        </div>
        <div class="raffle-grid" id="accountRafflesGrid"></div>
      </div>
    </div>

    <!-- Not found -->
    <div id="notFoundSection" class="hidden">
      <div class="empty-state">
        <div class="empty-icon">😕</div>
        <h3 class="text-white mb-1">No encontramos <?= htmlspecialchars($ticketLabelP) ?></h3>
        <p>No hay <?= htmlspecialchars($ticketLabelP) ?> registrados con ese correo. Verifica que sea el mismo que usaste al comprar.</p>
        <a href="sorteos.php" class="btn btn-primary mt-3">Comprar mi primer <?= htmlspecialchars($ticketLabel) ?> 🎟️</a>
      </div>
    </div>

    <!-- Tip cards -->
    <div id="tipsSection" class="mt-4">
      <div class="grid-3">
        <div class="card text-center" style="padding:1.5rem;">
          <div style="font-size:2rem; margin-bottom:.75rem;">📧</div>
          <h4 class="text-white mb-1"><?= htmlspecialchars(ucfirst($ticketLabel)) ?> por correo</h4>
          <p class="text-sm">Después de tu compra recibes un email con tus <?= htmlspecialchars($ticketLabelP) ?> digitales.</p>
        </div>
        <div class="card text-center" style="padding:1.5rem;">
          <div style="font-size:2rem; margin-bottom:.75rem;">🔢</div>
          <h4 class="text-white mb-1">Número único</h4>
          <p class="text-sm">Cada <?= htmlspecialchars($ticketLabel) ?> tiene un número de 6 dígitos asignado al azar.</p>
        </div>
        <div class="card text-center" style="padding:1.5rem;">
          <div style="font-size:2rem; margin-bottom:.75rem;">📺</div>
          <h4 class="text-white mb-1">Sorteo en vivo</h4>
          <p class="text-sm">El sorteo se transmite en vivo. Conéctate y descubre si ganaste.</p>
        </div>
      </div>
    </div>
  </section>
</div>

<?php include __DIR__ . '/partials/site_footer.php'; ?>

<div id="toast-container" class="toast-container"></div>
<script>window.SURTEADOS_DATA = <?= $initData ?>;</script>
<script src="assets/js/data.js"></script>
<script src="assets/js/app.js"></script>
<script>
(function() {
  const tabCode = document.getElementById('tabCode');
  const tabLogin = document.getElementById('tabLogin');
  const tabRegister = document.getElementById('tabRegister');
  const panelCode = document.getElementById('panelCode');
  const panelLogin = document.getElementById('panelLogin');
  const panelRegister = document.getElementById('panelRegister');
  const authCard = document.getElementById('authCard');

  const authEmail = document.getElementById('authEmail');
  const authCode = document.getElementById('authCode');
  const sendCodeBtn = document.getElementById('sendCodeBtn');
  const verifyCodeBtn = document.getElementById('verifyCodeBtn');
  const codeHint = document.getElementById('codeHint');
  const codeRequestStep = document.getElementById('codeRequestStep');
  const codeVerifyStep = document.getElementById('codeVerifyStep');
  const codeSentText = document.getElementById('codeSentText');
  const changeEmailBtn = document.getElementById('changeEmailBtn');

  const loginIdentifier = document.getElementById('loginIdentifier');
  const loginPassword = document.getElementById('loginPassword');
  const loginBtn = document.getElementById('loginBtn');
  const googleLoginBtn = document.getElementById('googleLoginBtn');
  const googleRegisterBtn = document.getElementById('googleRegisterBtn');

  const regFullName = document.getElementById('regFullName');
  const regPhone = document.getElementById('regPhone');
  const regAddress = document.getElementById('regAddress');
  const regRut = document.getElementById('regRut');
  const regEmail = document.getElementById('regEmail');
  const regEmail2 = document.getElementById('regEmail2');
  const regPassword = document.getElementById('regPassword');
  const regPassword2 = document.getElementById('regPassword2');
  const regCaptcha = document.getElementById('regCaptcha');
  const regCaptchaQuestion = document.getElementById('regCaptchaQuestion');
  const refreshCaptchaBtn = document.getElementById('refreshCaptchaBtn');
  const registerBtn = document.getElementById('registerBtn');
  const registerFormStep = document.getElementById('registerFormStep');
  const registerVerifyStep = document.getElementById('registerVerifyStep');
  const registerVerifyText = document.getElementById('registerVerifyText');
  const registerCode = document.getElementById('registerCode');
  const verifyRegisterBtn = document.getElementById('verifyRegisterBtn');
  const editRegisterBtn = document.getElementById('editRegisterBtn');

  const logoutBtn = document.getElementById('logoutBtn');
  const resultsSection = document.getElementById('resultsSection');
  const notFoundSection = document.getElementById('notFoundSection');
  const ticketsList = document.getElementById('ticketsList');
  const resultsTitle = document.getElementById('resultsTitle');
  const resultsCount = document.getElementById('resultsCount');
  const tipsSection = document.getElementById('tipsSection');
  const accountProfileGrid = document.getElementById('accountProfileGrid');
  const accountDatosPanel = document.getElementById('accountDatosPanel');
  const accountImagenesPanel = document.getElementById('accountImagenesPanel');
  const accountSorteosPanel = document.getElementById('accountSorteosPanel');
  const accountTabButtons = Array.from(document.querySelectorAll('.account-tab-btn'));
  const profileMissingHint = document.getElementById('profileMissingHint');
  const editProfileBtn = document.getElementById('editProfileBtn');
  const accountProfileForm = document.getElementById('accountProfileForm');
  const cancelProfileBtn = document.getElementById('cancelProfileBtn');
  const saveProfileBtn = document.getElementById('saveProfileBtn');
  const accFullName = document.getElementById('accFullName');
  const accPhone = document.getElementById('accPhone');
  const accAddress = document.getElementById('accAddress');
  const accRut = document.getElementById('accRut');
  const accRegion = document.getElementById('accRegion');
  const accComuna = document.getElementById('accComuna');
  const accountRafflesGrid = document.getElementById('accountRafflesGrid');
  const raffleFilterButtons = Array.from(document.querySelectorAll('.raffle-filter-btn'));

  let authState = null;
  let pendingCodeEmail = '';
  let pendingRegisterEmail = '';
  let lastTicketCount = 0;
  let accountRaffleFilter = 'active';

  function cleanRut(value) {
    return String(value || '').replace(/[^0-9kK]/g, '').toUpperCase();
  }

  function formatRut(value) {
    const rut = cleanRut(value);
    if (rut.length <= 1) return rut;
    const body = rut.slice(0, -1);
    const dv = rut.slice(-1);
    return body.replace(/\B(?=(\d{3})+(?!\d))/g, '.') + '-' + dv;
  }

  function isValidRut(value) {
    const rut = cleanRut(value);
    if (rut.length < 2) return false;
    const body = rut.slice(0, -1);
    const dv = rut.slice(-1);
    if (!/^\d+$/.test(body)) return false;
    let sum = 0;
    let multiplier = 2;
    for (let i = body.length - 1; i >= 0; i--) {
      sum += Number(body[i]) * multiplier;
      multiplier = multiplier === 7 ? 2 : multiplier + 1;
    }
    const expected = 11 - (sum % 11);
    const expectedDv = expected === 11 ? '0' : expected === 10 ? 'K' : String(expected);
    return dv === expectedDv;
  }

  function startGoogleAuth() {
    window.location.href = 'api/google_auth.php?action=start';
  }

  function setTab(tab) {
    panelCode.classList.toggle('hidden', tab !== 'code');
    panelLogin.classList.toggle('hidden', tab !== 'login');
    panelRegister.classList.toggle('hidden', tab !== 'register');

    tabCode.classList.toggle('btn-primary', tab === 'code');
    tabCode.classList.toggle('btn-ghost', tab !== 'code');
    tabLogin.classList.toggle('btn-primary', tab === 'login');
    tabLogin.classList.toggle('btn-ghost', tab !== 'login');
    tabRegister.classList.toggle('btn-primary', tab === 'register');
    tabRegister.classList.toggle('btn-ghost', tab !== 'register');
    if (tab === 'register') {
      if (!pendingRegisterEmail) showRegisterFormStep();
      loadCaptcha();
    }
  }

  async function authApi(action, payload = {}, method = 'POST') {
    const resp = await fetch('api/customer_auth.php?action=' + encodeURIComponent(action), {
      method,
      headers: { 'Content-Type': 'application/json' },
      body: method === 'POST' ? JSON.stringify(payload) : undefined,
    });
    const text = await resp.text();
    let json;
    try {
      json = JSON.parse(text);
    } catch (_) {
      throw new Error('El servidor no devolvió JSON válido. Revisa la ruta o el error PHP del servidor.');
    }
    if (!json.ok) throw new Error(json.error || 'Error de autenticación');
    return json.data;
  }

  async function readJsonResponse(resp, fallbackMessage) {
    const text = await resp.text();
    let json;
    try {
      json = JSON.parse(text);
    } catch (_) {
      throw new Error(fallbackMessage || 'El servidor no devolvió una respuesta válida.');
    }
    if (!json.ok) throw new Error(json.error || fallbackMessage || 'No se pudo completar la acción');
    return json.data;
  }

  function showCodeRequestStep() {
    codeRequestStep?.classList.remove('hidden');
    codeVerifyStep?.classList.add('hidden');
    if (authCode) authCode.value = '';
  }

  function showCodeVerifyStep(email) {
    pendingCodeEmail = email;
    codeRequestStep?.classList.add('hidden');
    codeVerifyStep?.classList.remove('hidden');
    if (codeSentText) {
      codeSentText.textContent = `Te enviamos un código temporal a ${email}. Ingresa los 6 dígitos para acceder a tus imágenes compradas.`;
    }
    setTimeout(() => authCode?.focus(), 80);
  }

  function showRegisterFormStep() {
    registerFormStep?.classList.remove('hidden');
    registerVerifyStep?.classList.add('hidden');
    if (registerCode) registerCode.value = '';
  }

  function showRegisterVerifyStep(email) {
    pendingRegisterEmail = email;
    registerFormStep?.classList.add('hidden');
    registerVerifyStep?.classList.remove('hidden');
    if (registerVerifyText) {
      registerVerifyText.textContent = `Enviamos un codigo de verificacion a ${email}. Ingresa los 6 digitos para activar tu cuenta.`;
    }
    setTimeout(() => registerCode?.focus(), 80);
  }

  function renderAccountProfile(user) {
    if (!accountProfileGrid) return;
    const missing = !user.fullName || !user.phone || !user.address || !user.rut || !user.comuna;
    profileMissingHint?.classList.toggle('hidden', !missing);
    const rows = [
      ['Nombre', user.fullName || 'No informado'],
      ['Correo', user.email || 'No informado'],
      ['RUT', user.rut || 'No informado'],
      ['Teléfono', user.phone || 'No informado'],
      ['Dirección', user.address || 'No informada'],
      ['Comuna', user.comuna || 'No informada'],
      ['Ingreso', user.authProvider === 'google' ? 'Google' : 'Correo y clave'],
      ['Verificación', user.emailVerifiedAt ? 'Correo verificado' : 'Pendiente'],
    ];
    accountProfileGrid.innerHTML = rows.map(([label, value]) => `
      <div class="card" style="padding:1rem;background:rgba(255,255,255,.04);">
        <div class="text-sm" style="color:var(--text-muted);font-weight:700;">${escHtml(label)}</div>
        <div style="color:var(--text-inv);font-weight:800;margin-top:.25rem;word-break:break-word;">${escHtml(value)}</div>
      </div>
    `).join('');
  }

  async function fillAccountLocationSelectors(user = {}) {
    if (!accRegion || !accComuna) return;
    const regions = await loadChileLocations();
    accRegion.innerHTML = '<option value="">Selecciona tu región</option>' + regions.map(r =>
      `<option value="${escHtml(r.id)}">${escHtml(r.name)}</option>`
    ).join('');

    let matchedRegion = '';
    if (user.communeId) {
      const found = regions.find(r => (r.communes || []).some(c => String(c.id) === String(user.communeId)));
      if (found) matchedRegion = String(found.id);
    } else if (user.comuna) {
      const found = regions.find(r => (r.communes || []).some(c => c.name === user.comuna));
      if (found) matchedRegion = String(found.id);
    }
    if (matchedRegion) {
      accRegion.value = matchedRegion;
      fillAccountCommuneSelect(matchedRegion, user.communeId, user.comuna);
    } else {
      accComuna.innerHTML = '<option value="">Primero selecciona una región</option>';
      accComuna.disabled = true;
    }
  }

  function fillAccountCommuneSelect(regionId, selectedId = '', selectedName = '') {
    if (!accComuna) return;
    const region = (_locationsCache || []).find(r => String(r.id) === String(regionId));
    if (!region) {
      accComuna.innerHTML = '<option value="">Primero selecciona una región</option>';
      accComuna.disabled = true;
      return;
    }
    accComuna.innerHTML = '<option value="">Selecciona tu comuna</option>' + region.communes.map(c =>
      `<option value="${escHtml(c.id)}" data-name="${escHtml(c.name)}">${escHtml(c.name)}</option>`
    ).join('');
    accComuna.disabled = false;
    if (selectedId) accComuna.value = selectedId;
    if (!accComuna.value && selectedName) {
      const opt = Array.from(accComuna.options).find(o => o.dataset.name === selectedName);
      if (opt) accComuna.value = opt.value;
    }
  }

  async function showProfileForm(force = false) {
    if (!authState) return;
    if (accFullName) accFullName.value = authState.fullName || '';
    if (accPhone) accPhone.value = authState.phone || '';
    if (accAddress) accAddress.value = authState.address || '';
    if (accRut) accRut.value = authState.rut || '';
    await fillAccountLocationSelectors(authState);
    accountProfileForm?.classList.remove('hidden');
    if (force) editProfileBtn?.classList.add('hidden');
  }

  function hideProfileForm() {
    accountProfileForm?.classList.add('hidden');
    editProfileBtn?.classList.remove('hidden');
  }

  function selectedAccountCommunePayload() {
    const selected = accComuna?.selectedOptions?.[0];
    return {
      id: accComuna?.value?.trim() || '',
      name: selected?.dataset?.name || selected?.textContent?.trim() || '',
    };
  }

  function setAccountTab(tab) {
    const normalized = ['datos', 'imagenes', 'sorteos'].includes(tab) ? tab : 'datos';
    const showDatos = normalized === 'datos';
    const showImagenes = normalized === 'imagenes';
    const showSorteos = normalized === 'sorteos';
    accountDatosPanel?.classList.toggle('hidden', !showDatos);
    accountImagenesPanel?.classList.toggle('hidden', !showImagenes);
    accountSorteosPanel?.classList.toggle('hidden', !showSorteos);
    if (lastTicketCount === 0) {
      notFoundSection?.classList.toggle('hidden', !showImagenes);
    }
    accountTabButtons.forEach(btn => {
      const active = btn.dataset.accountTab === normalized;
      btn.classList.toggle('btn-primary', active);
      btn.classList.toggle('btn-ghost', !active);
    });
    if (showSorteos) renderAccountRaffles();
    history.replaceState(null, '', '#' + normalized);
  }

  function renderAccountRaffles() {
    if (!accountRafflesGrid) return;
    const raffles = (window.SURTEADOS_DATA?.raffles || []).slice().sort((a, b) => {
      const rank = { active: 0, soon: 1, ended: 2 };
      return (rank[a.status] ?? 9) - (rank[b.status] ?? 9);
    });
    const list = accountRaffleFilter === 'active' ? raffles.filter(r => r.status === 'active' || r.status === 'soon') : raffles;
    accountRafflesGrid.innerHTML = list.map(r => {
      const statusMap = { active: 'Vigente', soon: 'Próximamente', ended: 'Finalizado' };
      const statusClass = r.status === 'active' ? 'active' : r.status === 'soon' ? 'soon' : 'ended';
      const img = r.image || r.image_url || '';
      const minPack = (r.packs || []).slice().sort((a, b) => Number(a.price || 0) - Number(b.price || 0))[0];
      return `
        <div class="raffle-card">
          <div class="raffle-card-img">
            ${img ? `<img src="${escHtml(img)}" alt="${escHtml(r.title)}">` : `<span style="font-size:3rem;">${escHtml(r.imageEmoji || '🎁')}</span>`}
            <span class="raffle-card-status ${statusClass}">${statusMap[r.status] || 'Sorteo'}</span>
          </div>
          <div class="raffle-card-body">
            <div class="raffle-card-cat">${escHtml(r.category || 'General')}</div>
            <div class="raffle-card-title">${escHtml(r.title || 'Sorteo')}</div>
            <div class="raffle-card-value">${minPack ? `Desde ${formatPrice(minPack.price)}` : ''}</div>
            <div class="raffle-card-actions">
              <a class="btn btn-ghost btn-sm raffle-card-action" href="ver-sorteo.php?id=${encodeURIComponent(r.id)}">Ver sorteo</a>
              ${r.status === 'active' ? `<a class="btn btn-primary btn-sm raffle-card-action" href="ver-sorteo.php?id=${encodeURIComponent(r.id)}#comprar">Comprar imagen</a>` : ''}
            </div>
          </div>
        </div>
      `;
    }).join('') || '<div class="empty-state"><h3 class="text-white">No hay sorteos para mostrar</h3></div>';
  }

  async function loadCaptcha() {
    if (!regCaptchaQuestion) return;
    try {
      const data = await authApi('captcha', {}, 'GET');
      regCaptchaQuestion.textContent = data.question || 'Captcha';
      if (regCaptcha) regCaptcha.value = '';
    } catch (e) {
      regCaptchaQuestion.textContent = 'No disponible';
    }
  }

  async function loadMyTickets() {
    const resp = await fetch('api/tickets.php?my=1');
    const data = await readJsonResponse(resp, 'No se pudieron cargar tus imágenes compradas.');

    const tickets = (data || []).map(t => ({
      id: t.id,
      raffleId: t.raffle_id,
      ticketNumbers: Array.isArray(t.ticket_numbers) ? t.ticket_numbers : (() => { try { return JSON.parse(t.ticket_numbers || '[]'); } catch(e) { return []; } })(),
      pack: t.pack_label,
      purchaseDate: t.purchase_date,
      amount: t.amount,
      paymentStatus: t.payment_status,
    }));
    renderTickets(tickets, authState?.email || '');
  }

  function renderTickets(tickets, email) {
    lastTicketCount = tickets.length;
    resultsSection.classList.add('hidden');
    notFoundSection.classList.add('hidden');
    tipsSection.classList.add('hidden');

    if (tickets.length === 0) {
      resultsSection.classList.remove('hidden');
      notFoundSection.classList.remove('hidden');
      return;
    }

    const byRaffle = tickets.reduce((acc, t) => {
      const key = t.raffleId;
      if (!acc[key]) acc[key] = { raffle: db.getRaffle(t.raffleId), tickets: [] };
      acc[key].tickets.push(t);
      return acc;
    }, {});

    const totalTicketNums = tickets.reduce((a, t) => a + (t.ticketNumbers?.length || 1), 0);
    resultsTitle.textContent = `${tLabelUp()} de ${email}`;
    resultsCount.textContent = `${totalTicketNums} ${totalTicketNums !== 1 ? tLabelP() : tLabel()}`;

    window._ticketRegistry = {};
    let _tkIdx = 0;

    ticketsList.innerHTML = Object.values(byRaffle).map(({ raffle, tickets: rTickets }) => {
      const r = raffle || { title: 'Sorteo desconocido', status: 'ended', imageEmoji: '🎁', drawDate: '' };
      const raffleStatusMap = { active: ['En curso', '#a78bfa'], soon: ['Próximamente', '#f59e0b'], ended: ['Finalizado', '#6b7280'] };
      const [raffleLabel, raffleColor] = raffleStatusMap[r.status] || raffleStatusMap.ended;

      const payStatusMap = {
        paid:      ['✅ Pagado',    'pill-green'],
        pending:   ['⏳ Pendiente', 'pill-amber'],
        failed:    ['❌ Fallido',   'pill-red'],
        refunded:  ['↩️ Reembolso', 'pill-gray'],
      };
      const allPayStatuses = [...new Set(rTickets.map(t => t.paymentStatus))];
      const dominantPay = allPayStatuses.includes('paid') ? 'paid' : (allPayStatuses[0] || 'pending');
      const [payLabel, payClass] = payStatusMap[dominantPay] || ['Desconocido', 'pill-gray'];

      const allNumbers = rTickets.flatMap(t => t.ticketNumbers || [t.number]);

      return `
        <div class="card mb-3" style="padding:0; overflow:hidden;">
          <div style="background:linear-gradient(135deg, rgba(124,58,237,0.12), rgba(245,158,11,0.06)); padding:1.25rem; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:1rem;">
            <div style="font-size:2.5rem;">${r.imageEmoji || '🎁'}</div>
            <div style="flex:1;">
              <div style="font-weight:700; color:var(--text-inv); font-size:1rem;">${r.title}</div>
              ${r.drawDate ? `<div style="font-size:.8rem; color:var(--text-muted);">📅 Sorteo: ${formatDateTime(r.drawDate)} &nbsp;·&nbsp; <span style="color:${raffleColor}">${raffleLabel}</span></div>` : `<div style="font-size:.8rem;color:${raffleColor}">${raffleLabel}</div>`}
            </div>
            <span class="pill ${payClass}">${payLabel}</span>
          </div>
          <div style="padding:1.25rem;">
            <p class="text-sm mb-2" style="color:var(--text-muted);">Tus números para este sorteo:</p>
            <div style="display:flex; flex-wrap:wrap; gap:.5rem; margin-bottom:1rem;">
              ${allNumbers.map(num => {
                const ownerTicket = rTickets.find(t => (t.ticketNumbers||[]).includes(num)) || rTickets[0];
                const key = _tkIdx++;
                window._ticketRegistry[key] = {
                  num,
                  raffleTitle: r.title,
                  raffleEmoji: r.imageEmoji || '🎁',
                  raffleImage: r.image || '',
                  drawDate: r.drawDate || '',
                  pack: ownerTicket.pack,
                  amount: ownerTicket.amount,
                  purchaseDate: ownerTicket.purchaseDate,
                  payStatus: ownerTicket.paymentStatus,
                  buyerEmail: email,
                };
                return `<span class="ticket-num-badge ticket-num-clickable" style="cursor:pointer;" onclick="openTicketModal(${key})">${num}</span>`;
              }).join('')}
            </div>
            <div style="display:flex; gap:1rem; flex-wrap:wrap;">
              ${rTickets.map(t => `
                <div style="font-size:.78rem; color:var(--text-muted);">
                  🛒 ${t.pack} &nbsp;|&nbsp; 💰 ${formatPrice(t.amount)} &nbsp;|&nbsp; 📅 ${formatDate(t.purchaseDate)}
                </div>
              `).join('')}
            </div>
          </div>
        </div>
      `;
    }).join('');

    resultsSection.classList.remove('hidden');
  }

  async function refreshSessionAndData() {
    try {
      const s = await authApi('session', {}, 'GET');
      authState = s.authenticated ? s : null;
      if (authState) {
        authCard?.classList.add('hidden');
        renderAccountProfile(authState);
        await loadMyTickets();
        const hash = (window.location.hash || '').replace('#', '').toLowerCase();
        setAccountTab(hash === 'imagenes' || hash === 'sorteos' ? hash : 'datos');
        if (!authState.fullName || !authState.phone || !authState.address || !authState.rut || !authState.comuna) {
          await showProfileForm(true);
        }
      } else {
        authCard?.classList.remove('hidden');
        resultsSection.classList.add('hidden');
        notFoundSection.classList.add('hidden');
        tipsSection.classList.remove('hidden');
      }
    } catch (e) {
      showToast(e.message || 'No se pudo validar la sesión', 'error');
    }
  }

  tabCode?.addEventListener('click', () => setTab('code'));
  tabLogin?.addEventListener('click', () => setTab('login'));
  tabRegister?.addEventListener('click', () => setTab('register'));
  accountTabButtons.forEach(btn => btn.addEventListener('click', () => setAccountTab(btn.dataset.accountTab || 'datos')));
  raffleFilterButtons.forEach(btn => btn.addEventListener('click', () => {
    accountRaffleFilter = btn.dataset.filter || 'active';
    raffleFilterButtons.forEach(b => {
      const active = b === btn;
      b.classList.toggle('btn-primary', active);
      b.classList.toggle('btn-ghost', !active);
    });
    renderAccountRaffles();
  }));
  editProfileBtn?.addEventListener('click', () => showProfileForm(false));
  cancelProfileBtn?.addEventListener('click', hideProfileForm);
  accRegion?.addEventListener('change', () => fillAccountCommuneSelect(accRegion.value));
  accRut?.addEventListener('blur', () => { accRut.value = formatRut(accRut.value); });

  accountProfileForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const fullName = accFullName.value.trim();
    const phone = accPhone.value.trim();
    const address = accAddress.value.trim();
    const rut = accRut.value.trim();
    const comuna = selectedAccountCommunePayload();
    if (!fullName || !phone || !address || !rut || !comuna.id || !comuna.name) {
      showToast('Completa todos tus datos', 'warning');
      return;
    }
    if (!isValidRut(rut)) {
      showToast('Ingresa un RUT chileno válido', 'warning');
      return;
    }
    saveProfileBtn.disabled = true;
    saveProfileBtn.textContent = 'Guardando...';
    try {
      const data = await authApi('update_profile', {
        fullName,
        phone,
        address,
        rut,
        buyerComuna: comuna.name,
        buyerCommuneId: comuna.id,
      });
      authState = { ...authState, ...data };
      renderAccountProfile(authState);
      const customerMenuName = document.querySelector('.customer-menu-name');
      if (customerMenuName) customerMenuName.textContent = authState.fullName || authState.email || 'Mis datos';
      hideProfileForm();
      showToast('Datos actualizados', 'success');
    } catch (e) {
      showToast(e.message, 'error');
    } finally {
      saveProfileBtn.disabled = false;
      saveProfileBtn.textContent = 'Guardar datos';
    }
  });
  refreshCaptchaBtn?.addEventListener('click', loadCaptcha);
  googleLoginBtn?.addEventListener('click', startGoogleAuth);
  googleRegisterBtn?.addEventListener('click', startGoogleAuth);
  regRut?.addEventListener('blur', () => {
    regRut.value = formatRut(regRut.value);
  });
  editRegisterBtn?.addEventListener('click', () => {
    pendingRegisterEmail = '';
    showRegisterFormStep();
    loadCaptcha();
  });
  changeEmailBtn?.addEventListener('click', () => {
    pendingCodeEmail = '';
    showCodeRequestStep();
  });

  sendCodeBtn?.addEventListener('click', async () => {
    const email = authEmail.value.trim();
    if (!email || !/^[^@]+@[^@]+\.[^@]+$/.test(email)) {
      showToast('Ingresa un correo válido', 'warning');
      return;
    }

    sendCodeBtn.disabled = true;
    sendCodeBtn.textContent = 'Enviando...';
    try {
      const data = await authApi('request_code', { email });
      codeHint.textContent = data.message || 'Código enviado. Revisa tu correo.';
      if (data.dev_code) codeHint.textContent += ` (DEV: ${data.dev_code})`;
      showToast(data.sent ? 'Código enviado' : (data.message || 'No se pudo enviar el correo'), data.sent ? 'success' : 'error');
      if (data.sent || data.dev_code) showCodeVerifyStep(email);
    } catch (e) {
      showToast(e.message, 'error');
    } finally {
      sendCodeBtn.disabled = false;
      sendCodeBtn.textContent = '📧 Enviar código';
    }
  });

  verifyCodeBtn?.addEventListener('click', async () => {
    const email = pendingCodeEmail || authEmail.value.trim();
    const code = authCode.value.trim();
    if (!email || !code) {
      showToast('Completa correo y código', 'warning');
      return;
    }
    try {
      await authApi('verify_code', { email, code });
      showToast('Acceso concedido', 'success');
      await refreshSessionAndData();
    } catch (e) {
      showToast(e.message, 'error');
    }
  });

  loginBtn?.addEventListener('click', async () => {
    const identifier = loginIdentifier.value.trim();
    const password = loginPassword.value;
    if (!identifier || !password) {
      showToast('Completa usuario/correo y contraseña', 'warning');
      return;
    }
    try {
      await authApi('login', { identifier, password });
      showToast('Sesión iniciada', 'success');
      await refreshSessionAndData();
    } catch (e) {
      showToast(e.message, 'error');
    }
  });

  registerBtn?.addEventListener('click', async () => {
    const fullName = regFullName.value.trim();
    const phone = regPhone.value.trim();
    const address = regAddress.value.trim();
    const rut = regRut.value.trim();
    const email = regEmail.value.trim();
    const email2 = regEmail2.value.trim();
    const password = regPassword.value;
    const password2 = regPassword2.value;
    const captcha = regCaptcha.value.trim();
    const comuna = typeof selectedCommunePayload === 'function' ? selectedCommunePayload() : null;

    if (!fullName || !phone || !address || !rut || !email || !email2 || !password || !password2 || !captcha || !comuna?.id || !comuna?.name) {
      showToast('Completa todos los campos del registro', 'warning');
      return;
    }
    if (!isValidRut(rut)) {
      showToast('Ingresa un RUT chileno válido', 'warning');
      regRut.focus();
      return;
    }
    if (email.toLowerCase() !== email2.toLowerCase()) {
      showToast('Los correos no coinciden', 'warning');
      return;
    }
    if (password.length < 8) {
      showToast('La clave debe tener al menos 8 caracteres', 'warning');
      return;
    }
    if (password !== password2) {
      showToast('Las claves no coinciden', 'warning');
      return;
    }
    try {
      const data = await authApi('register', {
        fullName,
        phone,
        address,
        rut,
        email,
        emailConfirm: email2,
        password,
        passwordConfirm: password2,
        captcha,
        buyerComuna: comuna.name,
        buyerCommuneId: comuna.id,
      });
      if (data.dev_code) showToast(`Código DEV: ${data.dev_code}`, 'warning');
      showToast(data.sent ? 'Te enviamos un código para activar tu cuenta' : (data.message || 'Cuenta pendiente de verificación'), data.sent ? 'success' : 'error');
      if (data.sent || data.dev_code) {
        showRegisterVerifyStep(email);
      } else {
        await loadCaptcha();
      }
    } catch (e) {
      showToast(e.message, 'error');
      await loadCaptcha();
    }
  });

  verifyRegisterBtn?.addEventListener('click', async () => {
    const email = pendingRegisterEmail || regEmail.value.trim();
    const code = registerCode.value.trim();
    if (!email || !code) {
      showToast('Completa el código recibido por correo', 'warning');
      return;
    }
    verifyRegisterBtn.disabled = true;
    verifyRegisterBtn.textContent = 'Activando...';
    try {
      await authApi('verify_register', { email, code });
      showToast('Cuenta verificada. Sesión iniciada', 'success');
      pendingRegisterEmail = '';
      await refreshSessionAndData();
    } catch (e) {
      showToast(e.message, 'error');
    } finally {
      verifyRegisterBtn.disabled = false;
      verifyRegisterBtn.textContent = 'Activar cuenta';
    }
  });

  logoutBtn?.addEventListener('click', async () => {
    try {
      await authApi('logout', {});
      authState = null;
      ticketsList.innerHTML = '';
      showToast('Sesión cerrada', 'success');
      authCard?.classList.remove('hidden');
      document.querySelector('.customer-menu')?.remove();
      resultsSection.classList.add('hidden');
      notFoundSection.classList.add('hidden');
      tipsSection.classList.remove('hidden');
    } catch (e) {
      showToast(e.message, 'error');
    }
  });

  const initialHashTab = (window.location.hash || '').replace('#', '').toLowerCase();
  if (initialHashTab === 'login' || initialHashTab === 'register' || initialHashTab === 'code') {
    setTab(initialHashTab);
  } else {
    setTab('code');
  }
  const urlEmail = new URLSearchParams(window.location.search).get('email');
  if (urlEmail && authEmail) authEmail.value = urlEmail;
  const authError = new URLSearchParams(window.location.search).get('auth_error');
  if (authError) showToast(authError, 'error');
  refreshSessionAndData();
})();

// ── Floating ticket modal ─────────────────────────────────────────────────────
function openTicketModal(key) {
  const d = window._ticketRegistry[key];
  if (!d) return;
  const payMap = { paid:'✅ Pagado', pending:'⏳ Pendiente', failed:'❌ Fallido', refunded:'↩️ Reembolso' };
  const el = document.getElementById('ticketFloatModal');
  el.querySelector('#tfm-emoji').textContent  = d.raffleEmoji;
  el.querySelector('#tfm-title').textContent  = d.raffleTitle;
  el.querySelector('#tfm-num').textContent    = d.num;
  el.querySelector('#tfm-pack').textContent   = d.pack || '—';
  el.querySelector('#tfm-amount').textContent = formatPrice(d.amount);
  el.querySelector('#tfm-date').textContent   = formatDate(d.purchaseDate);
  el.querySelector('#tfm-draw').textContent   = d.drawDate ? formatDateTime(d.drawDate) : '—';
  el.querySelector('#tfm-pay').textContent    = payMap[d.payStatus] || d.payStatus;
  el.querySelector('#tfm-email').textContent  = d.buyerEmail;
  const imgEl = el.querySelector('#tfm-img');
  const emojiEl = el.querySelector('#tfm-emoji');
  if (d.raffleImage) {
    imgEl.src = d.raffleImage;
    imgEl.style.display = 'block';
    emojiEl.style.display = 'none';
  } else {
    imgEl.style.display = 'none';
    emojiEl.style.display = 'block';
    emojiEl.textContent = d.raffleEmoji;
  }
  el.style.display = 'flex';
  document.body.style.overflow = 'hidden';
}
function closeTicketModal() {
  document.getElementById('ticketFloatModal').style.display = 'none';
  document.body.style.overflow = '';
}
</script>

<!-- Floating Ticket Modal -->
<div id="ticketFloatModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(5,2,20,.75);backdrop-filter:blur(6px);align-items:center;justify-content:center;padding:1rem;" onclick="if(event.target===this)closeTicketModal()">
  <div style="background:#140b30;border:1px solid rgba(124,58,237,.4);border-radius:20px;max-width:360px;width:100%;overflow:hidden;box-shadow:0 25px 60px rgba(0,0,0,.6);">
    <!-- Header -->
    <div style="background:linear-gradient(135deg,#4c1d95,#7c3aed);padding:1.5rem;text-align:center;position:relative;">
      <button onclick="closeTicketModal()" style="position:absolute;top:.75rem;right:.75rem;background:rgba(255,255,255,.15);border:none;color:#fff;width:28px;height:28px;border-radius:50%;cursor:pointer;font-size:1rem;line-height:1;">✕</button>
      <img id="tfm-img" src="" alt="" style="width:100%;height:160px;object-fit:cover;display:none;margin-bottom:.75rem;border-radius:10px;">
      <div id="tfm-emoji" style="font-size:3rem;margin-bottom:.4rem;"></div>
      <div id="tfm-title" style="font-weight:800;color:#fff;font-size:1rem;line-height:1.3;"></div>
    </div>
    <!-- Ticket number spotlight -->
    <div style="background:linear-gradient(135deg,rgba(124,58,237,.2),rgba(219,39,119,.15));border-bottom:1px solid rgba(124,58,237,.3);padding:1.25rem;text-align:center;">
      <div style="font-size:.7rem;letter-spacing:.12em;color:#a78bfa;text-transform:uppercase;margin-bottom:.35rem;">Número de ticket</div>
      <div id="tfm-num" style="font-size:2.2rem;font-weight:900;color:#fff;letter-spacing:.18em;font-family:monospace;"></div>
    </div>
    <!-- Details -->
    <div style="padding:1.25rem;display:flex;flex-direction:column;gap:.6rem;">
      <div style="display:flex;justify-content:space-between;font-size:.82rem;">
        <span style="color:#a0a0b0;">Pack</span>
        <span id="tfm-pack" style="color:#e2e8f0;font-weight:600;"></span>
      </div>
      <div style="display:flex;justify-content:space-between;font-size:.82rem;">
        <span style="color:#a0a0b0;">Monto pagado</span>
        <span id="tfm-amount" style="color:#f59e0b;font-weight:700;"></span>
      </div>
      <div style="display:flex;justify-content:space-between;font-size:.82rem;">
        <span style="color:#a0a0b0;">Fecha de compra</span>
        <span id="tfm-date" style="color:#e2e8f0;"></span>
      </div>
      <div style="display:flex;justify-content:space-between;font-size:.82rem;">
        <span style="color:#a0a0b0;">Fecha del sorteo</span>
        <span id="tfm-draw" style="color:#e2e8f0;"></span>
      </div>
      <div style="display:flex;justify-content:space-between;font-size:.82rem;">
        <span style="color:#a0a0b0;">Estado</span>
        <span id="tfm-pay" style="font-weight:600;"></span>
      </div>
      <div style="border-top:1px solid rgba(124,58,237,.2);padding-top:.6rem;display:flex;justify-content:space-between;font-size:.75rem;">
        <span style="color:#6b7280;">📧</span>
        <span id="tfm-email" style="color:#6b7280;"></span>
      </div>
    </div>
    <!-- Footer -->
    <div style="padding:.75rem 1.25rem 1.25rem;text-align:center;">
      <button onclick="closeTicketModal()" class="btn btn-ghost btn-block" style="font-size:.85rem;">Cerrar</button>
    </div>
  </div>
</div>

</body>
</html>
