<?php
/** SURTEADOS — Protected Admin Dashboard */
require __DIR__ . '/../api/config.php';

session_name(SESSION_NAME);
if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$adminUser = htmlspecialchars($_SESSION['admin_username'] ?? 'Admin');
$apiBase   = BASE_URL . '/api';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Panel Admin — Surteados</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/styles.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
</head>
<body>

<!-- ADMIN NAVBAR -->
<nav class="navbar" id="navbar" style="background:rgba(10,10,15,0.98);">
  <div class="navbar-inner">
    <a href="../index.php" class="navbar-logo">
      <div class="logo-icon">🎟️</div>
      <span class="brand">Sur<em>tea</em>dos</span>
    </a>
    <div style="display:flex; align-items:center; gap:.5rem;">
      <span class="pill pill-purple" style="font-size:.7rem;">Panel Admin</span>
      <span style="font-size:.8rem; color:rgba(255,255,255,.6);">👤 <?= $adminUser ?></span>
    </div>
    <div class="navbar-actions">
      <a href="../index.php" class="btn btn-outline btn-sm">← Ver sitio</a>
      <a href="logout.php" class="btn btn-ghost btn-sm">Salir</a>
    </div>
    <button class="navbar-mobile-toggle" id="mobileToggle"><span></span><span></span><span></span></button>
  </div>
  <div class="mobile-nav" id="mobileNav">
    <a href="../index.php">← Ver sitio</a>
    <a href="logout.php">Cerrar sesión</a>
  </div>
</nav>

<div class="admin-layout">

  <!-- SIDEBAR -->
  <aside class="admin-sidebar">
    <div class="admin-sidebar-section">
      <p>Principal</p>
      <div class="admin-nav-item active" data-section="dashboard"><span class="icon">📊</span> Dashboard</div>
      <div class="admin-nav-item" data-section="sorteos"><span class="icon">🎟️</span> Sorteos</div>
      <div class="admin-nav-item" data-section="tickets"><span class="icon">🎫</span> Tickets vendidos</div>
      <div class="admin-nav-item" data-section="ganadores"><span class="icon">🏆</span> Ganadores</div>
    </div>
    <div class="admin-sidebar-section">
      <p>Configuración</p>
      <div class="admin-nav-item" data-section="settings"><span class="icon">⚙️</span> Ajustes del sitio</div>
      <div class="admin-nav-item" data-section="diseno"><span class="icon">🖼️</span> Diseño</div>
      <div class="admin-nav-item" data-section="ticketformat"><span class="icon">🎫</span> Formato de ticket</div>
    </div>
    <div class="admin-sidebar-section">
      <p>Integraciones</p>
      <div class="admin-nav-item" data-section="smtp"><span class="icon">📧</span> Correo SMTP</div>
      <div class="admin-nav-item" data-section="flow"><span class="icon">💳</span> Flow.cl Pagos</div>
    </div>
    <div class="admin-sidebar-section" style="margin-top:auto; padding-top:2rem;">
      <label class="dev-mode-toggle" style="display:flex;align-items:center;justify-content:space-between;gap:.75rem;padding:.75rem 1rem;margin-bottom:.75rem;border:1px solid rgba(255,255,255,.08);border-radius:10px;background:rgba(255,255,255,.04);color:rgba(255,255,255,.78);font-size:.78rem;cursor:pointer;">
        <span style="line-height:1.25;">Sitio en desarrollo</span>
        <span style="position:relative;display:inline-flex;width:38px;height:22px;flex-shrink:0;">
          <input type="checkbox" id="developmentModeSwitch" style="position:absolute;inset:0;opacity:0;cursor:pointer;">
          <span id="developmentModeTrack" style="position:absolute;inset:0;border-radius:999px;background:rgba(255,255,255,.18);transition:background .2s;"></span>
          <span id="developmentModeKnob" style="position:absolute;top:3px;left:3px;width:16px;height:16px;border-radius:999px;background:#fff;box-shadow:0 2px 8px rgba(0,0,0,.28);transition:transform .2s;"></span>
        </span>
      </label>
      <a href="logout.php" class="admin-nav-item" style="color:#f87171; text-decoration:none;">
        <span class="icon">🚪</span> Cerrar sesión
      </a>
    </div>
  </aside>

  <!-- MAIN CONTENT -->
  <main class="admin-main">

    <!-- ═══ DASHBOARD ═══ -->
    <div class="admin-section active" id="sec-dashboard">
      <div class="admin-header">
        <h2>📊 Dashboard</h2>
        <span class="text-sm text-muted">Bienvenido al panel de control</span>
      </div>
      <div class="admin-stat-cards" id="dashStats"></div>
      <div style="display:grid; grid-template-columns:1fr 1fr; gap:1.5rem;">
        <div class="table-container">
          <div class="table-header"><h3>🎫 Últimas compras</h3></div>
          <table class="admin-table">
            <thead><tr><th>Comprador</th><th>Sorteo</th><th>Monto</th><th>Fecha</th></tr></thead>
            <tbody id="dashRecentTickets"></tbody>
          </table>
        </div>
        <div class="table-container">
          <div class="table-header"><h3>🎟️ Estado sorteos</h3></div>
          <div style="padding:1rem;" id="dashRaffleProgress"></div>
        </div>
      </div>
    </div>

    <!-- ═══ SORTEOS ═══ -->
    <div class="admin-section" id="sec-sorteos">
      <div class="admin-header">
        <h2>🎟️ Gestión de Sorteos</h2>
        <button class="btn btn-primary btn-sm" onclick="openRaffleModal()">+ Nuevo Sorteo</button>
      </div>
      <div class="table-container">
        <table class="admin-table">
          <thead><tr><th>Sorteo</th><th>Categoría</th><th>Estado</th><th>Tickets</th><th>Premio</th><th>Fecha sorteo</th><th>Acciones</th></tr></thead>
          <tbody id="sorteosTable"></tbody>
        </table>
      </div>
    </div>

    <!-- ═══ TICKETS ═══ -->
    <div class="admin-section" id="sec-tickets">
      <div class="admin-header">
        <h2>🎫 Tickets Vendidos</h2>
        <div style="display:flex; gap:.5rem; align-items:center;">
          <select class="form-control" id="ticketsFilter" style="width:200px; padding:.45rem .75rem;">
            <option value="">Todos los sorteos</option>
          </select>
          <input type="text" class="form-control" id="ticketsSearch" placeholder="Buscar..." style="width:180px; padding:.45rem .75rem;" oninput="filterTickets()">
        </div>
      </div>
      <div class="table-container">
        <div class="table-header flex-between">
          <h3>Listado de tickets</h3>
          <span class="pill pill-purple" id="ticketsCount">0 registros</span>
        </div>
        <table class="admin-table">
          <thead><tr><th>Ticket(s)</th><th>Comprador</th><th>Sorteo</th><th>Pack</th><th>Monto</th><th>Fecha</th><th>Pago</th><th></th></tr></thead>
          <tbody id="ticketsTable"></tbody>
        </table>
      </div>
    </div>

    <!-- ═══ GANADORES ═══ -->
    <div class="admin-section" id="sec-ganadores">
      <div class="admin-header">
        <h2>🏆 Gestión de Ganadores</h2>
        <button class="btn btn-primary btn-sm" onclick="openWinnerModal()">+ Agregar Ganador</button>
      </div>
      <div class="table-container">
        <table class="admin-table">
          <thead><tr><th>Ganador</th><th>Sorteo</th><th>Premio</th><th>N° Ticket</th><th>Fecha</th><th>Acciones</th></tr></thead>
          <tbody id="ganadoresTable"></tbody>
        </table>
      </div>
    </div>

    <!-- ═══ SETTINGS ═══ -->
    <div class="admin-section" id="sec-settings">
      <div class="admin-header"><h2>⚙️ Ajustes del Sitio</h2></div>
      <div class="card" style="padding:2rem; max-width:640px;">
        <form id="settingsForm" onsubmit="saveSettingsForm(event)">
          <div class="form-group">
            <label class="form-label">Nombre del sitio</label>
            <input type="text" class="form-control" name="site_name">
          </div>
          <div class="form-group">
            <label class="form-label">Tagline</label>
            <input type="text" class="form-control" name="site_tagline">
          </div>
          <div class="form-group">
            <label class="form-label">Email de contacto</label>
            <input type="email" class="form-control" name="site_email">
          </div>
          <div class="form-group">
            <label class="form-label">WhatsApp</label>
            <input type="text" class="form-control" name="site_whatsapp">
          </div>
          <div class="form-group">
            <label class="form-label">URL del sitio</label>
            <input type="url" class="form-control" name="site_url" placeholder="https://tusitio.cl">
          </div>
          <h4 style="margin:1.25rem 0 .5rem; font-size:.95rem;">🏷️ Nomenclatura</h4>
          <p class="form-hint mb-2">Define cómo se llama el elemento que el participante recibe. Usa minúsculas. Ej: <em>ticket</em>, <em>código</em>, <em>número</em>.</p>
          <div class="form-row">
            <div class="form-group"><label class="form-label">Nombre singular</label><input type="text" class="form-control" name="ticket_label" placeholder="ticket"></div>
            <div class="form-group"><label class="form-label">Nombre plural</label><input type="text" class="form-control" name="ticket_label_plural" placeholder="tickets"></div>
          </div>
          <h4 style="margin:1.25rem 0 .5rem; font-size:.95rem;">Redes Sociales</h4>
          <div class="form-row">
            <div class="form-group"><label class="form-label">Instagram</label><input type="url" class="form-control" name="social_instagram"></div>
            <div class="form-group"><label class="form-label">TikTok</label><input type="url" class="form-control" name="social_tiktok"></div>
            <div class="form-group"><label class="form-label">YouTube</label><input type="url" class="form-control" name="social_youtube"></div>
            <div class="form-group"><label class="form-label">Facebook</label><input type="url" class="form-control" name="social_facebook"></div>
          </div>
          <button type="submit" class="btn btn-primary">Guardar cambios</button>
        </form>
      </div>
    </div>

    <!-- ═══ DISEÑO ═══ -->
    <div class="admin-section" id="sec-diseno">
      <div class="admin-header"><h2>🖼️ Configuración de Diseño</h2></div>

      <!-- Logo -->
      <div class="card" style="padding:1.5rem; max-width:640px; margin-bottom:1.5rem;">
        <h4 style="margin-bottom:1rem;">Logo del sitio</h4>
        <div style="display:flex; align-items:center; gap:1.5rem; flex-wrap:wrap;">
          <div id="logoPreview" style="width:80px;height:80px;background:linear-gradient(135deg,var(--color-primary),var(--color-accent));border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:2rem;overflow:hidden;flex-shrink:0;">🎟️</div>
          <div style="flex:1;min-width:200px;">
            <input type="file" id="logoFileInput" accept="image/*" style="display:none">
            <button class="btn btn-primary btn-sm" onclick="document.getElementById('logoFileInput').click()">📁 Subir logo</button>
            <button class="btn btn-ghost btn-sm" id="deleteLogo" style="margin-left:.5rem;">🗑️ Eliminar</button>
            <p class="form-hint" style="margin-top:.5rem;">PNG, JPG, WebP, SVG — máx. 5MB. Ideal: imagen cuadrada.</p>
          </div>
        </div>
      </div>

      <!-- Tema de colores -->
      <div style="max-width:900px;margin-bottom:1.5rem;">
        <div class="card" style="padding:1.5rem;margin-bottom:1.5rem;">
          <h4 style="margin-bottom:1rem;">🎨 Presets de color</h4>
          <div id="diPresets"></div>
        </div>
        <div class="card" style="padding:1.5rem;">
          <h4 style="margin-bottom:1rem;">✏️ Personalizar colores</h4>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;align-items:start;">
            <div>
              <div class="color-input-group">
                <label>Color primario</label>
                <div class="color-input-row"><input type="color" id="diColorPrimary" value="#7c3aed"><input type="text" class="form-control" id="diColorPrimaryHex" value="#7c3aed"></div>
              </div>
              <div class="color-input-group">
                <label>Color acento</label>
                <div class="color-input-row"><input type="color" id="diColorAccent" value="#f59e0b"><input type="text" class="form-control" id="diColorAccentHex" value="#f59e0b"></div>
              </div>
            </div>
            <div style="padding:1rem;background:var(--bg-base);border-radius:var(--radius-md);text-align:center;">
              <p style="font-size:.75rem;color:var(--text-secondary);margin-bottom:.75rem;">Vista previa</p>
              <div style="display:flex;gap:.5rem;justify-content:center;flex-wrap:wrap;">
                <button class="btn btn-primary btn-sm">Primario</button>
                <button class="btn btn-accent btn-sm">Acento</button>
                <span class="pill pill-purple">Badge</span>
              </div>
            </div>
          </div>
          <div style="display:flex;gap:.5rem;margin-top:1.25rem;">
            <button class="btn btn-primary" style="flex:1" id="diApplyTheme">✅ Guardar tema</button>
            <button class="btn btn-ghost" id="diResetTheme">Restaurar</button>
          </div>
        </div>
      </div>

      <!-- Hero Slider -->
      <div class="card" style="padding:1.5rem;max-width:900px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.75rem;">
          <h4>🖼️ Slider principal (portada)</h4>
          <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;">
            <input type="checkbox" id="sliderEnabled" style="accent-color:var(--color-primary);width:16px;height:16px;">
            <span class="text-sm">Activar slider</span>
          </label>
        </div>
        <p style="font-size:.85rem;color:var(--text-secondary);margin-bottom:1rem;">Reemplaza el banner principal de la portada. Puedes crear hasta 6 diapositivas con imagen, colores y botón.</p>
        <div id="slidesList" style="display:flex;flex-direction:column;gap:.75rem;margin-bottom:1rem;"></div>
        <button class="btn btn-ghost btn-sm" onclick="openSlideModal()">+ Agregar diapositiva</button>
      </div>
    </div>

    <!-- ═══ TICKET FORMAT ═══ -->
    <div class="admin-section" id="sec-ticketformat">
      <div class="admin-header">
        <h2>🎫 Formato de Ticket</h2>
        <span class="text-sm" style="color:var(--text-secondary);">Vista previa — cómo se verá el ticket del participante</span>
      </div>
      <div class="card" style="padding:1rem 1.5rem; max-width:780px; margin-bottom:1.5rem; display:flex; align-items:center; gap:1rem; flex-wrap:wrap;">
        <div style="flex:1; min-width:200px;">
          <label class="form-label" style="margin-bottom:.25rem;">Sorteo de muestra</label>
          <select class="form-control" id="tpRaffleSelect" onchange="updateTicketPreview()"><option>Cargando...</option></select>
        </div>
        <button class="btn btn-ghost btn-sm" style="margin-top:1.25rem;" onclick="printTicketPreview()">🖨️ Imprimir / PDF</button>
      </div>
      <div id="tpPreviewArea" style="max-width:780px;"></div>
      <p style="font-size:.8rem;color:var(--text-secondary);max-width:780px;margin-top:1rem;padding:.75rem 1rem;background:var(--bg-base);border-radius:8px;border:1px solid var(--border);">
        💡 Vista previa con datos de ejemplo. El ticket real se genera automáticamente con los datos reales del comprador al confirmar el pago.
      </p>
    </div>

    <!-- ═══ SMTP ═══ -->
    <div class="admin-section" id="sec-smtp">
      <div class="admin-header"><h2>📧 Configuración SMTP</h2></div>
      <div class="card" style="padding:2rem; max-width:640px;">
        <p style="font-size:.85rem; color:var(--text-secondary); margin-bottom:1.5rem;">
          Configura el servidor de correo saliente para confirmaciones de compra y notificaciones.
        </p>
        <form id="smtpForm" onsubmit="saveSmtpForm(event)">
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Servidor SMTP</label>
              <input type="text" class="form-control" name="smtp_host" placeholder="smtp.gmail.com">
            </div>
            <div class="form-group">
              <label class="form-label">Puerto</label>
              <input type="number" class="form-control" name="smtp_port" placeholder="587">
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Usuario / Email</label>
              <input type="text" class="form-control" name="smtp_user" placeholder="tu@correo.cl">
            </div>
            <div class="form-group">
              <label class="form-label">Contraseña</label>
              <input type="password" class="form-control" name="smtp_pass" autocomplete="new-password">
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Nombre remitente</label>
              <input type="text" class="form-control" name="smtp_from_name" placeholder="Surteados Chile">
            </div>
            <div class="form-group">
              <label class="form-label">Email remitente</label>
              <input type="email" class="form-control" name="smtp_from_email" placeholder="no-reply@surteados.cl">
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Cifrado</label>
            <select class="form-control" name="smtp_encryption">
              <option value="tls">TLS (recomendado)</option>
              <option value="ssl">SSL</option>
              <option value="none">Ninguno</option>
            </select>
          </div>
          <button type="submit" class="btn btn-primary">Guardar configuración SMTP</button>
        </form>
        <div style="margin-top:1.5rem;padding-top:1.25rem;border-top:1px solid var(--border);">
          <label class="form-label">Enviar correo de prueba</label>
          <div style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;">
            <input type="email" class="form-control" id="smtpTestEmail" placeholder="correo@ejemplo.cl" style="flex:1;min-width:220px;">
            <button type="button" class="btn btn-outline" id="smtpTestBtn" onclick="testSmtpEmail()">Probar SMTP</button>
          </div>
          <p class="form-hint" style="margin-top:.5rem;">Usa los datos escritos arriba. Puedes probar antes o despues de guardar.</p>
        </div>
      </div>
    </div>

    <!-- ═══ FLOW.CL ═══ -->
    <div class="admin-section" id="sec-flow">
      <div class="admin-header"><h2>💳 Configuración Flow.cl</h2></div>
      <div class="card" style="padding:2rem; max-width:640px;">
        <p style="font-size:.85rem; color:var(--text-secondary); margin-bottom:1.5rem;">
          Integración con <strong>Flow.cl</strong> para procesar pagos online. Obtén tus credenciales en
          <a href="https://www.flow.cl" target="_blank" rel="noopener">flow.cl</a>.
        </p>
        <form id="flowForm" onsubmit="saveFlowForm(event)">
          <div class="form-group">
            <label class="form-label">Entorno</label>
            <select class="form-control" name="flow_environment">
              <option value="sandbox">🧪 Sandbox (pruebas)</option>
              <option value="production">🚀 Producción (real)</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">API Key</label>
            <input type="text" class="form-control" name="flow_api_key" placeholder="Tu API Key de Flow.cl">
          </div>
          <div class="form-group">
            <label class="form-label">Secret Key</label>
            <input type="password" class="form-control" name="flow_secret_key" autocomplete="new-password" placeholder="Tu Secret Key de Flow.cl">
          </div>
          <div class="form-group">
            <label class="form-label">URL del sitio (para callbacks)</label>
            <input type="url" class="form-control" name="site_url" placeholder="https://tusitio.cl/surteados">
            <small style="color:var(--text-secondary); font-size:.78rem; margin-top:.25rem; display:block;" id="flowCallbackPreview"></small>
          </div>
          <button type="submit" class="btn btn-primary">Guardar configuración Flow.cl</button>
        </form>
      </div>
    </div>

  </main>
</div>

<!-- ═══ RAFFLE MODAL ═══ -->
<div class="modal-backdrop" id="raffleModal">
  <div class="modal" style="max-width:700px;">
    <div class="modal-top-bar"></div>
    <div class="modal-header">
      <span class="modal-title" id="raffleModalTitle">Nuevo Sorteo</span>
      <button class="modal-close" onclick="closeRaffleModal()">✕</button>
    </div>
    <div class="modal-body">
      <div style="display:flex; gap:.5rem; margin-bottom:1.5rem; background:var(--bg-base); border-radius:var(--radius-md); padding:.25rem;">
        <button class="btn btn-primary btn-sm" style="flex:1;" id="rtab-info" onclick="switchRaffleTab('info')">📋 Info</button>
        <button class="btn btn-ghost btn-sm"   style="flex:1;" id="rtab-packs" onclick="switchRaffleTab('packs')">🎟️ Packs</button>
        <button class="btn btn-ghost btn-sm"   style="flex:1;" id="rtab-legal" onclick="switchRaffleTab('legal')">📜 Legal</button>
      </div>

      <!-- Info -->
      <div id="rtab-info-panel">
        <div class="form-row">
          <div class="form-group"><label class="form-label">Título del sorteo *</label><input type="text" class="form-control" id="rf_title" placeholder="iPhone 16 Pro Max..."></div>
          <div class="form-group"><label class="form-label">Categoría</label>
            <select class="form-control" id="rf_category">
              <option>Tecnología</option><option>Gaming</option><option>Viajes</option><option>Vehículos</option><option>Efectivo</option><option>Hogar</option><option>Otros</option>
            </select>
          </div>
        </div>
        <div class="form-group"><label class="form-label">Descripción</label><textarea class="form-control" id="rf_description" rows="3" placeholder="Describe el sorteo..."></textarea></div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Estado</label>
            <select class="form-control" id="rf_status">
              <option value="active">🟢 Activo</option>
              <option value="soon">🟡 Próximamente</option>
              <option value="ended">⚫ Finalizado</option>
            </select>
          </div>
          <div class="form-group"><label class="form-label">Fecha del sorteo</label><input type="datetime-local" class="form-control" id="rf_drawDate"></div>
        </div>
        <div class="form-group">
          <label class="form-label">📹 Enlace de transmisión <span style="font-weight:400;opacity:.6;">(Meet, YouTube Live, etc.)</span></label>
          <input type="url" class="form-control" id="rf_meetLink" placeholder="https://meet.google.com/abc-defg-hij">
          <p class="form-hint">Se mostrará en la página del sorteo como botón para ver en vivo.</p>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Total de tickets</label><input type="number" class="form-control" id="rf_totalTickets" placeholder="Déjalo vacío para ilimitado" min="0"><p class="form-hint">Si no indicas un total, el sorteo quedará sin límite de tickets.</p></div>
          <div class="form-group">
            <label class="form-label">Imagen del sorteo</label>
            <div style="display:flex; align-items:center; gap:.75rem;">
              <div id="rf_imagePreview" style="width:52px;height:52px;border-radius:8px;border:1px dashed var(--border);display:flex;align-items:center;justify-content:center;font-size:1.8rem;overflow:hidden;background:var(--bg-base);flex-shrink:0;">🎁</div>
              <div style="display:flex; flex-direction:column; gap:.3rem;">
                <input type="hidden" id="rf_imageUrl">
                <input type="file" id="rf_imageFile" accept="image/*" style="display:none;" onchange="handleRaffleImageUpload(this)">
                <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('rf_imageFile').click()">📷 Subir imagen</button>
                <button type="button" class="btn btn-ghost btn-sm" style="color:#ef4444; font-size:.75rem;" onclick="clearRaffleImage()">✕ Quitar</button>
              </div>
            </div>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Premio *</label><input type="text" class="form-control" id="rf_prizeName" placeholder="iPhone 16 Pro Max"></div>
          <div class="form-group"><label class="form-label">Emoji (opcional)</label><input type="text" class="form-control" id="rf_prizeEmoji" placeholder="🏆" maxlength="4"></div>
        </div>
        <div class="form-group">
          <label class="form-label">Imagen del premio</label>
          <div style="display:flex; align-items:center; gap:.75rem;">
            <div id="rf_prizeImagePreview" style="width:52px;height:52px;border-radius:8px;border:1px dashed var(--border);display:flex;align-items:center;justify-content:center;font-size:1.8rem;overflow:hidden;background:var(--bg-base);flex-shrink:0;">🏆</div>
            <div style="display:flex; flex-direction:column; gap:.3rem;">
              <input type="hidden" id="rf_prizeImageUrl">
              <input type="file" id="rf_prizeImageFile" accept="image/*" style="display:none;" onchange="handleRafflePrizeImageUpload(this)">
              <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('rf_prizeImageFile').click()">📷 Subir imagen</button>
              <button type="button" class="btn btn-ghost btn-sm" style="color:#ef4444; font-size:.75rem;" onclick="clearRafflePrizeImage()">✕ Quitar</button>
            </div>
          </div>
        </div>
        <div class="form-group">
          <label style="display:flex; align-items:center; gap:.5rem; cursor:pointer;">
            <input type="checkbox" id="rf_featured" style="accent-color:var(--color-primary); width:16px; height:16px;">
            <span class="text-sm">Mostrar como sorteo destacado en la portada</span>
          </label>
        </div>
      </div>

      <!-- Packs -->
      <div id="rtab-packs-panel" class="hidden">
        <div id="packsContainer"></div>
        <button class="btn btn-ghost btn-sm" style="margin-top:.5rem;" onclick="addPackRow()">+ Agregar pack</button>
      </div>

      <!-- Legal -->
      <div id="rtab-legal-panel" class="hidden">
        <div class="form-group"><label class="form-label">Texto legal / bases</label>
          <textarea class="form-control" id="rf_legalText" rows="6" placeholder="Ingresa las bases legales del sorteo..."></textarea>
        </div>
      </div>

      <div class="separator"></div>
      <div style="display:flex; gap:.5rem; justify-content:flex-end;">
        <button class="btn btn-ghost" onclick="closeRaffleModal()">Cancelar</button>
        <button class="btn btn-primary" id="saveRaffleBtn" onclick="saveRaffle()">💾 Guardar Sorteo</button>
      </div>
    </div>
  </div>
</div>

<!-- ═══ WINNER MODAL ═══ -->
<div class="modal-backdrop" id="winnerModal">
  <div class="modal">
    <div class="modal-top-bar"></div>
    <div class="modal-header">
      <span class="modal-title" id="winnerModalTitle">Agregar Ganador</span>
      <button class="modal-close" onclick="closeWinnerModal()">✕</button>
    </div>
    <div class="modal-body">
      <div class="form-group">
        <label class="form-label">Sorteo *</label>
        <select class="form-control" id="wn_raffleId">
          <option value="">— selecciona un sorteo —</option>
        </select>
      </div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Nombre del ganador *</label><input type="text" class="form-control" id="wn_name" placeholder="Juan Pérez"></div>
        <div class="form-group"><label class="form-label">Email del ganador</label><input type="email" class="form-control" id="wn_email" placeholder="juan@correo.cl"></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Premio ganado *</label><input type="text" class="form-control" id="wn_prize" placeholder="iPhone 16 Pro Max"></div>
        <div class="form-group"><label class="form-label">Número de ticket *</label><input type="text" class="form-control" id="wn_ticketNumber" placeholder="003421"></div>
      </div>
      <div class="form-group"><label class="form-label">Video de YouTube (principal)</label><input type="url" class="form-control" id="wn_videoUrl" placeholder="https://www.youtube.com/watch?v=..."><p class="form-hint">Este video se mostrará como contenido principal en la página de ganadores.</p></div>
      <div class="form-group">
        <label class="form-label">Foto del ganador (pequeña)</label>
        <div style="display:flex; align-items:center; gap:.75rem;">
          <div id="wn_imagePreview" style="width:46px;height:46px;border-radius:999px;border:1px dashed var(--border);display:flex;align-items:center;justify-content:center;font-size:1.2rem;overflow:hidden;background:var(--bg-base);flex-shrink:0;">👤</div>
          <div style="display:flex; flex-direction:column; gap:.3rem;">
            <input type="hidden" id="wn_imageUrl">
            <input type="file" id="wn_imageFile" accept="image/*" style="display:none;" onchange="handleWinnerImageUpload(this)">
            <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('wn_imageFile').click()">📷 Subir imagen</button>
            <button type="button" class="btn btn-ghost btn-sm" style="color:#ef4444; font-size:.75rem;" onclick="clearWinnerImage()">✕ Quitar</button>
          </div>
        </div>
      </div>
      <div class="form-group"><label class="form-label">Fecha del sorteo *</label><input type="date" class="form-control" id="wn_wonDate"></div>
      <div class="separator"></div>
      <div style="display:flex; gap:.5rem;">
        <button class="btn btn-ghost" onclick="closeWinnerModal()">Cancelar</button>
        <button class="btn btn-primary" style="flex:1" onclick="saveWinner()">💾 Guardar Ganador</button>
      </div>
    </div>
  </div>
</div>

<div id="toast-container" class="toast-container"></div>

<!-- ═══ SLIDE MODAL ═══ -->
<div class="modal-backdrop" id="slideModal">
  <div class="modal" style="max-width:580px;">
    <div class="modal-top-bar"></div>
    <div class="modal-header">
      <span class="modal-title" id="slideModalTitle">Nueva diapositiva</span>
      <button class="modal-close" onclick="closeSlideModal()">✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="sl_id">
      <div class="form-group">
        <label class="form-label">Imagen del slide *</label>
        <div style="display:flex;align-items:center;gap:.9rem;flex-wrap:wrap;">
          <div id="sl_imagePreview" style="width:150px;height:78px;border-radius:10px;border:1px dashed var(--border);display:flex;align-items:center;justify-content:center;overflow:hidden;background:var(--bg-base);color:var(--text-secondary);font-size:.78rem;flex-shrink:0;">Sin imagen</div>
          <div style="display:flex;flex-direction:column;gap:.4rem;min-width:190px;">
            <input type="file" id="sl_imageFile" accept="image/*" style="display:none;" onchange="handleSlideImageUpload(this)">
            <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('sl_imageFile').click()">Subir imagen</button>
            <button type="button" class="btn btn-ghost btn-sm" style="color:#ef4444;" onclick="clearSlideImage()">Quitar imagen</button>
          </div>
        </div>
        <p class="form-hint">Recomendado: imagen horizontal, 1920x700 px o similar. El texto del slide es opcional.</p>
      </div>
      <div class="form-group">
        <label class="form-label">Título opcional</label>
        <input type="text" class="form-control" id="sl_title" placeholder="Texto principal sobre la imagen">
      </div>
      <div class="form-group">
        <label class="form-label">Subtítulo</label>
        <input type="text" class="form-control" id="sl_subtitle" placeholder="Solo X tickets disponibles...">
      </div>
      <div class="form-group">
        <label class="form-label">Badge (opcional)</label>
        <input type="text" class="form-control" id="sl_badge" placeholder="🔥 ACTIVO">
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Texto del botón</label>
          <input type="text" class="form-control" id="sl_ctaText" placeholder="Ver sorteo">
        </div>
        <div class="form-group">
          <label class="form-label">Enlace del botón</label>
          <input type="text" class="form-control" id="sl_ctaLink" placeholder="sorteos.php">
        </div>
      </div>
      <div style="display:none;">
        <label style="display:flex; align-items:center; gap:.4rem; cursor:pointer; font-size:.875rem;">
          <input type="radio" name="sl_bgType" value="gradient"> Degradado
        </label>
        <label style="display:flex; align-items:center; gap:.4rem; cursor:pointer; font-size:.875rem;">
          <input type="radio" name="sl_bgType" value="image" checked> Imagen
        </label>
      </div>
      <div id="sl_gradientFields" class="form-row hidden">
        <div class="form-group">
          <label class="form-label">Color inicial</label>
          <div class="color-input-row"><input type="color" id="sl_color1" value="#1a0a2e"><input type="text" class="form-control" id="sl_color1Hex" value="#1a0a2e"></div>
        </div>
        <div class="form-group">
          <label class="form-label">Color final</label>
          <div class="color-input-row"><input type="color" id="sl_color2" value="#0d0520"><input type="text" class="form-control" id="sl_color2Hex" value="#0d0520"></div>
        </div>
      </div>
      <div id="sl_imageFields" class="hidden form-group">
        <label class="form-label">URL de imagen de fondo</label>
        <input type="url" class="form-control" id="sl_bgImage" placeholder="https://... o /surteados/assets/uploads/...">
      </div>
      <label style="display:flex; align-items:center; gap:.5rem; cursor:pointer; margin-top:.5rem;">
        <input type="checkbox" id="sl_active" checked style="accent-color:var(--color-primary); width:16px; height:16px;">
        <span class="text-sm">Diapositiva activa (visible)</span>
      </label>
      <div class="separator"></div>
      <div style="display:flex; gap:.5rem; justify-content:flex-end;">
        <button class="btn btn-ghost" onclick="closeSlideModal()">Cancelar</button>
        <button class="btn btn-primary" onclick="saveSlide()">💾 Guardar</button>
      </div>
    </div>
  </div>
</div>

<script>window.API_BASE = '<?= $apiBase ?>';</script>
<script src="../assets/js/admin-panel.js"></script>
</body>
</html>
