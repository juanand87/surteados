<?php
$footerSiteLogo = $siteLogo ?? ($cfg['logo'] ?? null);
$footerTicketLabelP = $ticketLabelP ?? ($cfg['ticketLabelPlural'] ?? 'imagenes');
?>
<footer class="footer">
  <div class="container">
    <div class="footer-grid">
      <div class="footer-brand">
        <a href="index.php" class="navbar-logo" style="margin-bottom:.75rem; display:inline-flex;">
          <?php if ($footerSiteLogo): ?>
            <img src="<?= htmlspecialchars($footerSiteLogo) ?>" alt="Logo" class="navbar-logo-img">
          <?php else: ?>
            <div class="logo-icon">&#127903;&#65039;</div>
            <span class="brand">Sur<em>tea</em>dos</span>
          <?php endif; ?>
        </a>
        <p>Plataforma chilena, sorteo de imagenes digitales, seguros y transparentes. Premios reales con procesos claros y auditables. Bases ante notario.</p>
        <div class="social-links" style="margin-top:1rem;">
          <a href="#" class="social-link" title="Instagram">&#128248;</a>
          <a href="#" class="social-link" title="TikTok">&#127925;</a>
          <a href="#" class="social-link" title="YouTube">&#9654;&#65039;</a>
          <a href="#" class="social-link" title="Facebook">&#128101;</a>
        </div>
      </div>
      <div class="footer-col">
        <h5>Sorteos</h5>
        <a href="sorteos.php">Sorteos activos</a>
        <a href="sorteos.php?filter=soon">Pr&oacute;ximamente</a>
        <a href="ganadores.php">Ganadores</a>
        <a href="mis-imagenes.php">Recuperar <?= htmlspecialchars($footerTicketLabelP) ?></a>
      </div>
      <div class="footer-col">
        <h5>Informaci&oacute;n</h5>
        <a href="como-participar.php">&iquest;C&oacute;mo participar?</a>
        <a href="index.php#beneficios">Beneficios</a>
        <a href="https://surteados.cl/documentos/Bases_Condiciones.pdf" target="_blank" rel="noopener">Bases legales</a>
        <a href="#">Pol&iacute;tica de privacidad</a>
      </div>
      <div class="footer-col">
        <h5>Contacto</h5>
        <a href="mailto:contacto@surteados.cl">&#128231; contacto@surteados.cl</a>
        <a href="#">&#128172; WhatsApp</a>
        <a href="panel/">&#9881;&#65039; Admin</a>
      </div>
    </div>
    <div class="footer-bottom">
      <p>&copy; 2026 Surteados. Todos los derechos reservados.</p>
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

<script src="assets/js/analytics.js?v=<?= (int)@filemtime(__DIR__ . '/../assets/js/analytics.js') ?>"></script>