<?php
/**
 * SURTEADOS - Printable purchased images / PDF view.
 * GET /api/ticket_pdf.php?orderId=xxx&email=xxx
 */
require __DIR__ . '/config.php';
require_once __DIR__ . '/ticket_verification_helper.php';

$orderId = trim((string)($_GET['orderId'] ?? ''));
$email = trim((string)($_GET['email'] ?? ''));

if ($orderId === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo '<p style="font-family:sans-serif;padding:2rem;">Parametros invalidos.</p>';
    exit;
}

$pdo = db();

$colStmt = $pdo->query('SHOW COLUMNS FROM tickets');
$ticketColumns = [];
foreach ($colStmt->fetchAll() as $col) {
    if (!empty($col['Field'])) $ticketColumns[$col['Field']] = true;
}

$dateParts = [];
if (isset($ticketColumns['paid_at'])) $dateParts[] = 't.paid_at';
if (isset($ticketColumns['purchase_date'])) $dateParts[] = 't.purchase_date';
if (isset($ticketColumns['created_at'])) $dateParts[] = 't.created_at';
$dateExpr = $dateParts ? ('COALESCE(' . implode(', ', $dateParts) . ')') : 'NULL';

$stmt = $pdo->prepare(
    "SELECT t.id, t.ticket_numbers, t.pack_label, t.amount, t.flow_order,
            t.buyer_name, t.buyer_email, t.buyer_rut, t.buyer_phone,
            {$dateExpr} AS paid_date,
            r.title AS raffle_title, r.image_url AS raffle_image,
            r.draw_date, r.category
       FROM tickets t
       JOIN raffles r ON r.id = t.raffle_id
      WHERE t.flow_order = ?
        AND t.buyer_email = ?
        AND t.payment_status = 'paid'
      ORDER BY paid_date ASC, t.id ASC"
);
$stmt->execute([$orderId, $email]);
$tickets = $stmt->fetchAll();

if (!$tickets) {
    http_response_code(404);
    echo '<p style="font-family:sans-serif;padding:2rem;">No se encontraron imagenes para este pedido.</p>';
    exit;
}

$cfgRows = $pdo->query(
    "SELECT `key`, `value` FROM settings WHERE `key` IN ('site_name','site_logo','site_url')"
)->fetchAll();
$cfg = [];
foreach ($cfgRows as $row) $cfg[$row['key']] = $row['value'];

$siteNameRaw = $cfg['site_name'] ?? 'Surteados';
$siteName = htmlspecialchars($siteNameRaw, ENT_QUOTES, 'UTF-8');
$siteLogo = trim((string)($cfg['site_logo'] ?? ''));
if ($siteLogo === '') $siteLogo = 'https://surteados.cl/assets/uploads/logo_e277c8485f11615e.png';
$siteLogoSafe = htmlspecialchars($siteLogo, ENT_QUOTES, 'UTF-8');
$siteUrl = surteados_pdf_site_url($cfg['site_url'] ?? BASE_URL);

$ticketItems = [];
foreach ($tickets as $ticket) {
    $numbers = json_decode($ticket['ticket_numbers'] ?? '[]', true) ?? [];
    $count = max(1, count($numbers));
    $unitAmount = $count > 0 ? (int)round((int)$ticket['amount'] / $count) : (int)$ticket['amount'];

    foreach ($numbers as $index => $number) {
        $number = (string)$number;
        $token = surteados_ticket_token($pdo, (string)$ticket['id'], $number, $orderId, $email);
        $verifyUrl = $siteUrl . '/api/verify_ticket.php?token=' . rawurlencode($token);
        $ticketItems[] = [
            'ticket_id' => (string)$ticket['id'],
            'number' => $number,
            'number_label' => surteados_ticket_number_label($number),
            'index' => $index + 1,
            'count' => $count,
            'amount' => $unitAmount,
            'pack_label' => $ticket['pack_label'] ?? '',
            'order_id' => $ticket['flow_order'] ?: $orderId,
            'buyer_name' => $ticket['buyer_name'] ?? '',
            'buyer_email' => $ticket['buyer_email'] ?? '',
            'buyer_rut' => $ticket['buyer_rut'] ?? '',
            'buyer_phone' => $ticket['buyer_phone'] ?? '',
            'raffle_title' => $ticket['raffle_title'] ?? '',
            'raffle_image' => $ticket['raffle_image'] ?? '',
            'draw_date' => $ticket['draw_date'] ?? null,
            'category' => $ticket['category'] ?? '',
            'paid_date' => $ticket['paid_date'] ?? null,
            'verify_url' => $verifyUrl,
            'token_short' => strtoupper(substr(hash('sha256', $token), 0, 12)),
        ];
    }
}

header('Content-Type: text/html; charset=UTF-8');
header('X-Robots-Tag: noindex, nofollow');
?><!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Tickets oficiales - <?= $siteName ?></title>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
  <style>
    * { box-sizing: border-box; }
    body {
      margin: 0;
      padding: 22px 12px;
      font-family: Arial, Helvetica, sans-serif;
      background: #eee7f7;
      color: #fff;
    }
    .print-actions {
      display: flex;
      justify-content: center;
      margin: 0 auto 18px;
    }
    .print-btn {
      border: 0;
      border-radius: 999px;
      padding: 11px 24px;
      background: #ffb000;
      color: #210934;
      font-weight: 900;
      cursor: pointer;
      box-shadow: 0 12px 28px rgba(38,11,75,.18);
    }
    .ticket-page {
      width: min(100%, 1120px);
      min-height: 570px;
      margin: 0 auto 28px;
      padding: 0;
      page-break-after: always;
      break-after: page;
    }
    .ticket {
      min-height: 570px;
      display: grid;
      grid-template-columns: 1fr 280px;
      overflow: hidden;
      border-radius: 18px;
      border: 1px solid rgba(255,255,255,.13);
      background:
        linear-gradient(90deg, rgba(255,255,255,.04), transparent 42%),
        linear-gradient(145deg, #2c0e52 0%, #260b49 48%, #170227 100%);
      box-shadow: inset 0 4px 0 #00b4d8, inset 0 -4px 0 #db2777, 0 26px 70px rgba(25,5,45,.28);
      position: relative;
    }
    .ticket-main { padding: 28px 34px 0; display: grid; grid-template-rows: auto auto 1fr auto; }
    .ticket-side { border-left: 2px dashed rgba(255,255,255,.2); padding: 20px 22px; display: grid; grid-template-rows: auto auto 1fr; gap: 12px; position: relative; }
    .ticket-side::before,
    .ticket-side::after {
      content: "";
      position: absolute;
      left: -9px;
      width: 18px;
      height: 18px;
      border-radius: 50%;
      background: #eee7f7;
    }
    .ticket-side::before { top: 86px; }
    .ticket-side::after { bottom: 86px; }
    .topline { display:flex; align-items:flex-start; justify-content:space-between; gap: 18px; padding-bottom: 18px; border-bottom: 1px solid rgba(255,255,255,.08); }
    .logo { max-width: 120px; max-height: 54px; object-fit: contain; }
    .official { text-align:right; color:rgba(255,255,255,.58); font-size:12px; line-height:1.65; }
    .official strong { display:block; color:#fff; font-size:13px; }
    .label { color:#9b7ab5; text-transform:uppercase; letter-spacing:.24em; font-size:11px; font-weight:800; margin-bottom:8px; }
    .number { font-size:50px; line-height:.95; font-weight:900; letter-spacing:-1px; text-shadow:0 10px 28px rgba(124,58,237,.55); margin: 18px 0 18px; }
    .info-grid { display:grid; grid-template-columns: 1fr 1fr; gap: 18px 26px; margin-bottom: 16px; }
    .info-value { color:#fff; font-weight:800; font-size:16px; }
    .accent { color:#ffb000; }
    .pack-row { display:grid; grid-template-columns: 1fr 1fr; gap:20px; padding:13px 20px; border:1px solid rgba(255,255,255,.12); border-radius:12px; background:rgba(255,255,255,.055); margin: 0 0 18px; }
    .participant { margin-top: 4px; }
    .participant .name { font-size:17px; font-weight:900; margin-bottom:6px; }
    .details { color:#b79ecb; font-size:13px; line-height:1.55; }
    .footer { margin-top:auto; padding:12px 0; color:#8d73a5; font-size:11px; border-top:1px solid rgba(255,255,255,.08); display:flex; justify-content:space-between; gap:20px; }
    .side-head { text-align:right; color:#9b7ab5; font-size:11px; text-transform:uppercase; letter-spacing:.24em; font-weight:900; line-height:1.6; }
    .prize-label { color:#9b7ab5; text-transform:uppercase; letter-spacing:.22em; font-size:10px; font-weight:900; }
    .prize-card { align-self:start; text-align:center; }
    .prize-img { width: 150px; height: 150px; object-fit: cover; border-radius: 16px; border:1px solid rgba(255,255,255,.14); box-shadow:0 18px 34px rgba(0,0,0,.24); background:#3a155d; }
    .prize-title { color:#ffb000; font-size:13px; font-weight:900; line-height:1.3; margin:10px auto 0; max-width:200px; }
    .verify { align-self:end; display:grid; justify-items:center; gap:7px; padding-top:12px; border-top:1px solid rgba(255,255,255,.09); }
    .qr { background:#fff; padding:8px; border-radius:10px; line-height:0; width:132px; height:132px; display:flex; align-items:center; justify-content:center; }
    .qr canvas, .qr img { width:116px !important; height:116px !important; }
    .verify-url { display:none; }
    .side-number { color:#d8c5e9; text-align:center; font-size:18px; font-weight:900; }
    .fallback-img { width:150px;height:150px;border-radius:16px;background:linear-gradient(135deg,#ffb000,#db2777);display:grid;place-items:center;font-size:40px;font-weight:900;color:#260b49;margin:0 auto; }
    @media print {
      @page { size: A4 landscape; margin: 8mm; }
      body { background:#fff; padding:0; }
      .print-actions { display:none !important; }
      .ticket-page { width:100%; min-height: 150mm; margin:0; }
      .ticket { min-height: 150mm; box-shadow: inset 0 4px 0 #00b4d8, inset 0 -4px 0 #db2777; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
      .ticket-side::before, .ticket-side::after { background:#fff; }
    }
  </style>
</head>
<body>
  <div class="print-actions">
    <button class="print-btn" onclick="window.print()">Guardar como PDF / Imprimir</button>
  </div>

  <?php foreach ($ticketItems as $idx => $item):
      $title = htmlspecialchars($item['raffle_title'], ENT_QUOTES, 'UTF-8');
      $label = htmlspecialchars($item['pack_label'], ENT_QUOTES, 'UTF-8');
      $num = htmlspecialchars($item['number_label'], ENT_QUOTES, 'UTF-8');
      $order = htmlspecialchars((string)$item['order_id'], ENT_QUOTES, 'UTF-8');
      $name = htmlspecialchars((string)$item['buyer_name'], ENT_QUOTES, 'UTF-8');
      $mail = htmlspecialchars((string)$item['buyer_email'], ENT_QUOTES, 'UTF-8');
      $rut = htmlspecialchars((string)$item['buyer_rut'], ENT_QUOTES, 'UTF-8');
      $phone = htmlspecialchars((string)$item['buyer_phone'], ENT_QUOTES, 'UTF-8');
      $amt = '$' . number_format((int)$item['amount'], 0, ',', '.') . ' CLP';
      $img = trim((string)$item['raffle_image']);
      $imgSafe = $img !== '' ? htmlspecialchars($img, ENT_QUOTES, 'UTF-8') : '';
      $drawFmt = $item['draw_date'] ? surteados_format_date_es($item['draw_date'], true) : '-';
      $paidFmt = $item['paid_date'] ? surteados_format_date_es($item['paid_date'], true) : '-';
      $category = htmlspecialchars((string)$item['category'], ENT_QUOTES, 'UTF-8');
      $verifyUrl = htmlspecialchars($item['verify_url'], ENT_QUOTES, 'UTF-8');
      $tokenShort = htmlspecialchars($item['token_short'], ENT_QUOTES, 'UTF-8');
  ?>
  <section class="ticket-page">
    <article class="ticket">
      <div class="ticket-main">
        <div class="topline">
          <img class="logo" src="<?= $siteLogoSafe ?>" alt="<?= $siteName ?>">
          <div class="official">
            Imagen oficial
            <strong><?= $siteName ?></strong>
            ID venta: <span class="accent"><?= $order ?></span>
          </div>
        </div>

        <div>
          <div class="number-block">
            <div class="label">Número de imagen</div>
            <div class="number"><?= $num ?></div>
          </div>

          <div class="info-grid">
            <div>
              <div class="label">Sorteo</div>
              <div class="info-value"><?= $title ?></div>
            </div>
            <div>
              <div class="label">Fecha del sorteo</div>
              <div class="info-value"><?= htmlspecialchars($drawFmt, ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <div>
              <div class="label">Categoría</div>
              <div class="info-value accent"><?= $category ?: 'General' ?></div>
            </div>
            <div>
              <div class="label">Imagen del pack</div>
              <div class="info-value"><?= (int)$item['index'] ?> de <?= (int)$item['count'] ?></div>
            </div>
          </div>

          <div class="pack-row">
            <div>
              <div class="label">Pack</div>
              <div class="info-value accent"><?= $label ?></div>
            </div>
            <div>
              <div class="label">Valor pagado</div>
              <div class="info-value accent"><?= htmlspecialchars($amt, ENT_QUOTES, 'UTF-8') ?></div>
            </div>
          </div>

          <div class="participant">
            <div class="label">Participante</div>
            <div class="name"><?= $name ?></div>
            <div class="details">
              Correo: <?= $mail ?><br>
              RUT: <?= $rut !== '' ? $rut : '-' ?> &nbsp; | &nbsp; Teléfono: <?= $phone !== '' ? $phone : '-' ?><br>
              Fecha de compra: <?= htmlspecialchars($paidFmt, ENT_QUOTES, 'UTF-8') ?>
            </div>
          </div>
        </div>

        <div class="footer">
          <span>Este ticket es válido únicamente con registro digital y verificación firmada.</span>
          <span><?= $siteName ?> © <?= date('Y') ?></span>
        </div>
      </div>

      <aside class="ticket-side">
        <div class="side-head">Imagen oficial<br><?= $siteName ?></div>
        <div class="prize-card">
          <div class="prize-label">Premio principal</div>
          <?php if ($imgSafe): ?>
            <img class="prize-img" src="<?= $imgSafe ?>" alt="<?= $title ?>">
          <?php else: ?>
            <div class="fallback-img">S</div>
          <?php endif; ?>
          <div class="prize-title"><?= $title ?></div>
        </div>
        <div class="verify">
          <div class="label">Verificación</div>
          <div class="qr" id="qr-<?= (int)$idx ?>" data-url="<?= $verifyUrl ?>"></div>
          <a class="verify-url" href="<?= $verifyUrl ?>" target="_blank" rel="noopener"><?= $verifyUrl ?></a>
          <div class="label">Código firma</div>
          <div class="side-number"><?= $tokenShort ?></div>
          <div class="label">Número de imagen</div>
          <div class="side-number"><?= $num ?></div>
        </div>
      </aside>
    </article>
  </section>
  <?php endforeach; ?>

  <script>
    function renderQrCodes() {
      document.querySelectorAll('.qr').forEach(function(el) {
        var url = el.getAttribute('data-url') || '';
        if (!url || !window.QRCode) {
          el.textContent = 'QR';
          return;
        }
        el.innerHTML = '';
        new QRCode(el, {
          text: url,
          width: 116,
          height: 116,
          colorDark: '#260b49',
          colorLight: '#ffffff',
          correctLevel: QRCode.CorrectLevel.M
        });
      });
    }
    window.addEventListener('load', function() {
      renderQrCodes();
      setTimeout(function() { window.print(); }, 1200);
    });
  </script>
</body>
</html>
<?php
function surteados_pdf_site_url(string $url): string
{
    $url = trim($url);
    if ($url === '') $url = BASE_URL;
    $url = preg_replace('~/(api/flow_callback\.php|pago-exitoso\.php)(/.*)?$~i', '', $url);
    $url = preg_replace('~/api/?$~i', '', $url);
    return rtrim($url, '/');
}

function surteados_format_date_es(string $date, bool $withTime = false): string
{
    $ts = strtotime($date);
    if (!$ts) return '-';
    $months = [
        1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
        5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
        9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
    ];
    $out = date('d', $ts) . ' de ' . $months[(int)date('n', $ts)] . ' de ' . date('Y', $ts);
    if ($withTime) $out .= ' ' . date('H:i', $ts);
    return $out;
}
