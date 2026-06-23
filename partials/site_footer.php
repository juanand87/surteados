<?php
$footerSiteLogo = $siteLogo ?? ($cfg['logo'] ?? null);
$footerTicketLabelP = $ticketLabelP ?? ($cfg['ticketLabelPlural'] ?? 'imagenes');
$footerSocialLinks = $cfg['socialLinks'] ?? [];

if (!$footerSocialLinks && function_exists('get_settings')) {
    $footerSettings = get_settings([
        'social_instagram',
        'social_tiktok',
        'social_youtube',
        'social_facebook',
    ]);
    $footerSocialLinks = [
        'instagram' => $footerSettings['social_instagram'] ?? '',
        'tiktok'    => $footerSettings['social_tiktok']    ?? '',
        'youtube'   => $footerSettings['social_youtube']   ?? '',
        'facebook'  => $footerSettings['social_facebook']  ?? '',
    ];
}

$footerSocialLinks = array_filter(
    $footerSocialLinks,
    static function ($url): bool {
        $url = trim((string)$url);
        if ($url === '' || $url === '#') return false;
        if (!filter_var($url, FILTER_VALIDATE_URL)) return false;
        return in_array(strtolower((string)parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true);
    }
);
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
        <?php if ($footerSocialLinks): ?>
        <div class="social-links" style="margin-top:1rem;" aria-label="Redes sociales">
          <?php if (!empty($footerSocialLinks['instagram'])): ?>
          <a href="<?= htmlspecialchars($footerSocialLinks['instagram']) ?>" class="social-link" title="Instagram" aria-label="Instagram" target="_blank" rel="noopener noreferrer">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5"></rect><circle cx="12" cy="12" r="4"></circle><circle cx="17.5" cy="6.5" r="1" class="social-icon-fill" fill="currentColor" stroke="none"></circle></svg>
          </a>
          <?php endif; ?>
          <?php if (!empty($footerSocialLinks['tiktok'])): ?>
          <a href="<?= htmlspecialchars($footerSocialLinks['tiktok']) ?>" class="social-link" title="TikTok" aria-label="TikTok" target="_blank" rel="noopener noreferrer">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 4v10.2a4.7 4.7 0 1 1-4-4.65v3.1a1.7 1.7 0 1 0 1 1.55V4h3Z"></path><path d="M15 4c.55 2.15 1.8 3.35 4 3.8v3.05c-2.05-.25-3.55-1.05-4.7-2.25"></path></svg>
          </a>
          <?php endif; ?>
          <?php if (!empty($footerSocialLinks['youtube'])): ?>
          <a href="<?= htmlspecialchars($footerSocialLinks['youtube']) ?>" class="social-link" title="YouTube" aria-label="YouTube" target="_blank" rel="noopener noreferrer">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 8.2a2.8 2.8 0 0 0-2-2C17.25 5.7 12 5.7 12 5.7s-5.25 0-7 .5a2.8 2.8 0 0 0-2 2A29 29 0 0 0 2.5 12 29 29 0 0 0 3 15.8a2.8 2.8 0 0 0 2 2c1.75.5 7 .5 7 .5s5.25 0 7-.5a2.8 2.8 0 0 0 2-2 29 29 0 0 0 .5-3.8 29 29 0 0 0-.5-3.8Z"></path><path d="m10 15 5-3-5-3v6Z" class="social-icon-fill" fill="currentColor" stroke="none"></path></svg>
          </a>
          <?php endif; ?>
          <?php if (!empty($footerSocialLinks['facebook'])): ?>
          <a href="<?= htmlspecialchars($footerSocialLinks['facebook']) ?>" class="social-link" title="Facebook" aria-label="Facebook" target="_blank" rel="noopener noreferrer">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 8h3V4h-3c-3.3 0-5 2-5 5v2H6v4h3v7h4v-7h3.5l.5-4h-4V9c0-.7.3-1 1-1Z" class="social-icon-fill" fill="currentColor" stroke="none"></path></svg>
          </a>
          <?php endif; ?>
        </div>
        <?php endif; ?>
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