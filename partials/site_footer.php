<?php
$footerSiteLogo = $siteLogo ?? ($cfg['logo'] ?? null);
$footerTicketLabelP = $ticketLabelP ?? ($cfg['ticketLabelPlural'] ?? 'imagenes');
$footerSocialLinks = $cfg['socialLinks'] ?? [];
$footerWhatsapp = trim((string)($cfg['whatsapp'] ?? ''));

if ((!$footerSocialLinks || $footerWhatsapp === '') && function_exists('get_settings')) {
    $footerSettings = get_settings([
        'site_whatsapp',
        'social_instagram',
        'social_tiktok',
        'social_youtube',
        'social_facebook',
    ]);
    if ($footerWhatsapp === '') {
        $footerWhatsapp = trim((string)($footerSettings['site_whatsapp'] ?? ''));
    }
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

$footerWhatsappDigits = preg_replace('/\D+/', '', $footerWhatsapp);
if ($footerWhatsappDigits && strlen($footerWhatsappDigits) === 9 && substr($footerWhatsappDigits, 0, 1) === '9') {
    $footerWhatsappDigits = '56' . $footerWhatsappDigits;
}
$footerWhatsappUrl = $footerWhatsappDigits ? 'https://wa.me/' . $footerWhatsappDigits : '';
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
        <?php if ($footerWhatsappUrl): ?>
        <a href="<?= htmlspecialchars($footerWhatsappUrl) ?>" target="_blank" rel="noopener">&#128172; WhatsApp</a>
        <?php endif; ?>
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

<?php if ($footerWhatsappUrl): ?>
<a class="whatsapp-fab" href="<?= htmlspecialchars($footerWhatsappUrl) ?>" target="_blank" rel="noopener" aria-label="Soporte por WhatsApp" title="Soporte por WhatsApp">
  <svg viewBox="0 0 32 32" aria-hidden="true">
    <path d="M16.01 4.25c-6.38 0-11.57 5.1-11.57 11.38 0 2.01.54 3.97 1.56 5.68L4.34 27.5l6.4-1.62a11.75 11.75 0 0 0 5.27 1.25c6.38 0 11.57-5.1 11.57-11.38S22.39 4.25 16.01 4.25Zm0 20.94c-1.66 0-3.29-.42-4.72-1.22l-.45-.25-3.77.95.98-3.57-.29-.47a9.25 9.25 0 0 1-1.39-4.99c0-5.19 4.33-9.41 9.65-9.41s9.65 4.22 9.65 9.41-4.33 9.55-9.65 9.55Zm5.28-7.12c-.29-.14-1.72-.83-1.98-.93-.27-.1-.46-.14-.66.14-.19.28-.76.93-.93 1.12-.17.19-.34.21-.63.07-.29-.14-1.22-.44-2.32-1.41-.86-.75-1.44-1.68-1.61-1.96-.17-.28-.02-.44.13-.58.13-.13.29-.34.44-.51.15-.17.19-.28.29-.47.1-.19.05-.35-.02-.49-.07-.14-.66-1.55-.9-2.13-.24-.56-.48-.49-.66-.5h-.56c-.19 0-.49.07-.75.35-.26.28-.98.94-.98 2.29 0 1.35 1 2.66 1.14 2.85.15.19 1.97 2.95 4.78 4.14.67.28 1.19.45 1.59.58.67.21 1.28.18 1.77.11.54-.08 1.72-.69 1.96-1.36.24-.67.24-1.24.17-1.36-.07-.12-.27-.19-.56-.33Z"></path>
  </svg>
</a>
<?php endif; ?>

<script src="assets/js/analytics.js?v=<?= (int)@filemtime(__DIR__ . '/../assets/js/analytics.js') ?>"></script>