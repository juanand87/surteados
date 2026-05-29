<?php
require __DIR__ . '/api/config.php';

session_name(SESSION_NAME);
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['admin_id'])) {
    header('Location: panel/index.php');
    exit;
}

$settings = get_settings(['site_name', 'site_logo']);
$siteName = $settings['site_name'] ?? 'Surteados';
$siteLogo = $settings['site_logo'] ?? null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tombola Admin - <?= htmlspecialchars($siteName) ?></title>
  <link rel="stylesheet" href="assets/css/styles.css">
  <script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.3/dist/confetti.browser.min.js"></script>
  <style>
    body {
      background:
        radial-gradient(900px 480px at 50% -10%, rgba(124,58,237,.28), transparent 65%),
        radial-gradient(800px 380px at 15% 10%, rgba(245,158,11,.12), transparent 55%),
        #090812;
    }
    .tb-wrap { max-width: 1240px; margin: 0 auto; padding: 92px 16px 32px; }
    .tb-card {
      background: linear-gradient(170deg, rgba(24,20,44,.92), rgba(14,12,29,.95));
      border: 1px solid rgba(170,147,255,.22);
      border-radius: 14px;
      padding: 1rem;
      box-shadow: 0 24px 46px rgba(0,0,0,.32);
    }
    .tb-meta { font-size:.82rem; color:var(--text-muted); }
    .tb-controls {
      display:flex;
      justify-content:space-between;
      gap:.8rem;
      flex-wrap:wrap;
      align-items:end;
    }
    .tb-show-stage {
      margin-top:1rem;
      padding:1.2rem;
      border-radius:16px;
      border:1px solid rgba(255,255,255,.13);
      background: radial-gradient(ellipse at center, rgba(124,58,237,.17), rgba(11,10,20,.92) 72%);
      overflow:hidden;
      position:relative;
      perspective: 1200px;
      min-height: 420px;
    }
    .tb-stage-glow {
      position:absolute;
      inset:auto 50% -180px;
      width:640px;
      height:360px;
      transform: translateX(-50%);
      filter: blur(26px);
      background: radial-gradient(circle, rgba(124,58,237,.3) 0%, rgba(124,58,237,0) 70%);
      pointer-events:none;
    }
    .tb-phase {
      text-align:center;
      margin-bottom:1rem;
    }
    .tb-phase h3 {
      margin:0;
      font-size:1.4rem;
      letter-spacing:.03em;
      color:#f2ecff;
    }
    .tb-phase p {
      margin:.35rem 0 0;
      color:#c5b8ef;
      font-size:.92rem;
    }
    .tb-drum-wrap {
      position:relative;
      width:min(860px, 96%);
      margin: 0 auto;
      height: 220px;
      transform-style: preserve-3d;
    }
    .tb-drum {
      position:absolute;
      inset:0;
      transform-style: preserve-3d;
      transform: rotateX(16deg) rotateY(0deg);
    }
    .tb-ball {
      position:absolute;
      left:50%;
      top:50%;
      width:170px;
      margin-left:-85px;
      margin-top:-30px;
      border:1px solid rgba(255,255,255,.24);
      background: linear-gradient(140deg, rgba(29,26,52,.96), rgba(20,18,37,.94));
      border-radius:999px;
      padding:.5rem .6rem;
      text-align:center;
      font-weight:700;
      color:#f2ebff;
      box-shadow: 0 7px 24px rgba(0,0,0,.38);
      backface-visibility: hidden;
      transform-style: preserve-3d;
    }
    .tb-ball small {
      display:block;
      font-size:.68rem;
      color:#cbbcf2;
      white-space:nowrap;
      overflow:hidden;
      text-overflow:ellipsis;
    }
    .tb-reveal-grid {
      margin-top:1rem;
      display:grid;
      gap:.7rem;
      grid-template-columns: repeat(auto-fit,minmax(140px,1fr));
    }
    .tb-reveal-grid .tb-ball {
      position:relative;
      left:auto;
      top:auto;
      margin:0;
      width:auto;
      transform:none !important;
    }
    .tb-final {
      margin-top:1rem;
      display:flex;
      justify-content:center;
    }
    .tb-winner-card {
      width:min(460px,100%);
      border-radius:18px;
      border:1px solid rgba(245,158,11,.5);
      background: linear-gradient(145deg, rgba(41,30,10,.95), rgba(24,18,8,.96));
      padding:1.1rem;
      text-align:center;
      box-shadow: 0 20px 40px rgba(245,158,11,.2), 0 0 0 1px rgba(245,158,11,.18) inset;
    }
    .tb-winner-card h4 {
      margin:.15rem 0 .35rem;
      color:#fde9bf;
      font-size:1.05rem;
      letter-spacing:.04em;
      text-transform:uppercase;
    }
    .tb-win-number {
      font-size:2.25rem;
      line-height:1;
      letter-spacing:.06em;
      color:#ffd976;
      text-shadow:0 0 20px rgba(255,220,120,.32);
      margin:.45rem 0;
      font-weight:900;
    }
    .tb-badge-live {
      display:inline-flex;
      align-items:center;
      gap:.35rem;
      border-radius:999px;
      border:1px solid rgba(239,68,68,.45);
      padding:.18rem .55rem;
      font-size:.72rem;
      color:#fecaca;
      background: rgba(127,29,29,.38);
      margin-bottom:.5rem;
    }
    .tb-dot {
      width:7px;
      height:7px;
      border-radius:999px;
      background:#ef4444;
      animation: tbdot 1s infinite;
    }
    .tb-status {
      margin-top:.75rem;
      font-size:.95rem;
      color:#f0e9ff;
      text-align:center;
      min-height:1.5rem;
    }
    .tb-actions { display:flex; gap:.5rem; flex-wrap:wrap; }
    .tb-speed {
      display:flex;
      align-items:center;
      gap:.55rem;
      font-size:.8rem;
      color:#cfc1f5;
      margin-top:.5rem;
    }
    @keyframes tbdot { 0%,100%{opacity:.35} 50%{opacity:1} }
    @media (max-width: 760px) {
      .tb-show-stage { min-height: 390px; }
      .tb-drum-wrap { height: 190px; }
      .tb-ball { width:144px; margin-left:-72px; }
      .tb-win-number { font-size:1.85rem; }
    }
  </style>
</head>
<body>
<nav class="navbar" id="navbar" style="background:rgba(10,10,15,0.98);">
  <div class="navbar-inner">
    <a href="index.php" class="navbar-logo">
      <?php if ($siteLogo): ?>
        <img src="<?= htmlspecialchars($siteLogo) ?>" alt="Logo" class="navbar-logo-img">
      <?php else: ?>
        <div class="logo-icon">🎟️</div><span class="brand">Sur<em>tea</em>dos</span>
      <?php endif; ?>
    </a>
    <div class="navbar-actions">
      <a href="panel/dashboard.php" class="btn btn-outline btn-sm">← Panel</a>
      <a href="panel/logout.php" class="btn btn-ghost btn-sm">Salir</a>
    </div>
  </div>
</nav>

<div class="tb-wrap">
  <div class="tb-card" style="margin-bottom:1rem;">
    <div class="tb-controls">
      <div>
        <div class="badge">Tombola oficial</div>
        <h2 style="margin:.4rem 0 0;">Noche de sorteo: imagen ganadora</h2>
        <p class="tb-meta" style="margin:.35rem 0 0;">Proceso real aleatorio: 10 -> 5 -> 3 -> 1. Solo imagenes pagadas.</p>
      </div>
      <div>
        <div class="tb-actions">
        <div>
          <label class="form-label">Sorteo</label>
          <select class="form-control" id="tbRaffle" style="min-width:320px;max-width:520px;"></select>
        </div>
        <button class="btn btn-primary" id="tbStartBtn">Iniciar tombola</button>
        <button class="btn btn-outline" id="tbResetBtn" style="display:none;margin-left:.5rem;opacity:.7;" title="Solo para pruebas">🔓 Resetear</button>
        </div>
        <label class="tb-speed">Ritmo del show
          <input type="range" id="tbDrama" min="1" max="3" value="2">
          <span id="tbDramaLabel">Medio</span>
        </label>
      </div>
    </div>
    <div id="tbInfo" class="tb-meta" style="margin-top:.7rem;"></div>
  </div>

  <div class="tb-show-stage tb-card">
    <div class="tb-stage-glow"></div>
    <div class="tb-phase">
      <div class="tb-badge-live"><span class="tb-dot"></span>EN VIVO</div>
      <h3 id="tbPhaseTitle">Esperando inicio de tómbola</h3>
      <p id="tbPhaseSub">Selecciona un sorteo y presiona iniciar para comenzar el show.</p>
    </div>

    <div class="tb-drum-wrap">
      <div id="tbDrum" class="tb-drum"></div>
    </div>

    <div id="tbRound10" class="tb-reveal-grid"></div>
    <div id="tbRound5" class="tb-reveal-grid"></div>
    <div id="tbRound3" class="tb-reveal-grid"></div>
    <div id="tbWinner" class="tb-final"></div>

    <div id="tbStatus" class="tb-status"></div>
    <p id="tbSaved" class="tb-meta" style="margin-top:.4rem;text-align:center;"></p>
  </div>
</div>

<script>
const tbState = { raffles: [], locked: false };
let drumRotation = 0;

const DRAMA_PRESETS = {
  1: { label: 'Rápido', spin10: 4.2, spin5: 3.8, spin3: 3.4, reveal: 180, pauseA: 320, pauseB: 360, pauseC: 420 },
  2: { label: 'Medio',  spin10: 6.5, spin5: 6.0, spin3: 5.6, reveal: 280, pauseA: 620, pauseB: 700, pauseC: 760 },
  3: { label: 'Épico',  spin10: 8.8, spin5: 8.2, spin3: 7.6, reveal: 390, pauseA: 980, pauseB: 1120, pauseC: 1280 },
};

function esc(s){return String(s).replace(/[&<>\"]/g,m=>({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;"}[m]));}
function sleep(ms){return new Promise(r=>setTimeout(r,ms));}

async function api(path, opts = {}) {
  const { method = 'GET', body } = opts;
  const res = await fetch('/surteados/api' + path, {
    method,
    credentials: 'include',
    headers: { 'Content-Type': 'application/json' },
    body: body ? JSON.stringify(body) : undefined,
  });
  const json = await res.json();
  if (!json.ok) throw new Error(json.error || 'Error inesperado');
  return json.data;
}

function renderBall(item, cls = '') {
  return `<div class="tb-ball ${cls}">#${esc(item.number)}<small>${esc(item.buyer_name || 'Participante')}</small></div>`;
}

function phase(title, sub = '') {
  document.getElementById('tbPhaseTitle').textContent = title;
  document.getElementById('tbPhaseSub').textContent = sub;
}

function status(msg = '') {
  document.getElementById('tbStatus').textContent = msg;
}

function currentDrama() {
  const v = Number(document.getElementById('tbDrama')?.value || 2);
  return DRAMA_PRESETS[v] || DRAMA_PRESETS[2];
}

function setDramaLabel() {
  const v = Number(document.getElementById('tbDrama')?.value || 2);
  const lb = document.getElementById('tbDramaLabel');
  if (lb) lb.textContent = (DRAMA_PRESETS[v] || DRAMA_PRESETS[2]).label;
}

function buildDrum(items) {
  const drum = document.getElementById('tbDrum');
  drum.innerHTML = '';
  if (!items?.length) return;

  const radius = window.innerWidth < 760 ? 185 : 260;
  const step = 360 / items.length;
  items.forEach((it, i) => {
    const card = document.createElement('div');
    card.className = 'tb-ball';
    card.innerHTML = `#${esc(it.number)}<small>${esc(it.buyer_name || 'Participante')}</small>`;
    card.style.transform = `rotateY(${i * step}deg) translateZ(${radius}px)`;
    drum.appendChild(card);
  });
}

async function spinDrum(turns, durationSec) {
  const target = drumRotation + (360 * turns);
  await gsap.to('#tbDrum', {
    rotationY: target,
    duration: durationSec,
    ease: 'power2.inOut'
  });
  drumRotation = target % 360;
}

async function revealGrid(elId, items, perItemDelay) {
  const el = document.getElementById(elId);
  el.innerHTML = '';
  for (const it of items) {
    const html = renderBall(it);
    const wrap = document.createElement('div');
    wrap.innerHTML = html;
    const node = wrap.firstElementChild;
    node.style.opacity = '0';
    node.style.transform = 'translateY(18px) scale(.92)';
    el.appendChild(node);
    gsap.to(node, { opacity: 1, y: 0, scale: 1, duration: .32, ease: 'back.out(1.8)' });
    await sleep(perItemDelay);
  }
}

async function loadRaffles() {
  const data = await api('/tombola.php');
  tbState.raffles = data;
  const sel = document.getElementById('tbRaffle');
  sel.innerHTML = data.map(r => {
    const lock = r.locked ? ' [BLOQUEADO]' : '';
    return `<option value="${r.id}" ${r.locked ? 'disabled' : ''}>${esc(r.title)} | ${r.paid_images} imagenes pagadas${lock}</option>`;
  }).join('') || '<option value="">Sin sorteos</option>';
  renderInfo();
}

function renderInfo() {
  const sel = document.getElementById('tbRaffle');
  const id = sel.value;
  const r = tbState.raffles.find(x => x.id === id);
  const info = document.getElementById('tbInfo');
  const btn = document.getElementById('tbStartBtn');
  const resetBtn = document.getElementById('tbResetBtn');
  if (!r) {
    info.textContent = 'Selecciona un sorteo.';
    btn.disabled = true;
    resetBtn.style.display = 'none';
    return;
  }
  if (r.locked) {
    info.textContent = 'Este sorteo ya tiene ganador. Puedes resetearlo para pruebas.';
    btn.disabled = true;
    resetBtn.style.display = '';
  } else {
    info.textContent = `Listo para sortear. Universo: ${r.paid_images} imagenes pagadas.`;
    btn.disabled = r.paid_images < 1;
    resetBtn.style.display = 'none';
  }
}

async function resetTombola() {
  const raffleId = document.getElementById('tbRaffle').value;
  if (!raffleId) return;
  if (!confirm('¿Borrar el ganador guardado de este sorteo? (Solo para pruebas)')) return;
  const resetBtn = document.getElementById('tbResetBtn');
  resetBtn.disabled = true;
  resetBtn.textContent = 'Reseteando...';
  try {
    await fetch('/surteados/api/tombola.php', {
      method: 'DELETE',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ raffle_id: raffleId })
    });
    await loadRaffles();
  } catch(e) {
    alert('Error al resetear: ' + e.message);
  } finally {
    resetBtn.disabled = false;
    resetBtn.textContent = '🔓 Resetear';
  }
}

async function runTombola() {
  const raffleId = document.getElementById('tbRaffle').value;
  if (!raffleId) return;

  const btn = document.getElementById('tbStartBtn');
  btn.disabled = true;
  btn.textContent = 'Sorteando...';
  const mood = currentDrama();

  document.getElementById('tbRound10').innerHTML = '';
  document.getElementById('tbRound5').innerHTML = '';
  document.getElementById('tbRound3').innerHTML = '';
  document.getElementById('tbWinner').innerHTML = '';
  document.getElementById('tbSaved').textContent = '';
  status('Preparando tómbola...');
  phase('Cargando universo de imagenes', 'Verificando participaciones pagadas y preparando el sorteo.');

  try {
    const result = await api('/tombola.php', { method: 'POST', body: { raffle_id: raffleId } });

    phase('Ronda 1: aparecen 10 imagenes', 'La tómbola gira y selecciona a los primeros clasificados...');
    status(`Universo total: ${result.pool_size} imagenes pagadas`);
    buildDrum(result.round10);
    await spinDrum(8, mood.spin10);
    await revealGrid('tbRound10', result.round10, mood.reveal);

    await sleep(mood.pauseA);
    phase('Ronda 2: quedan 5 semifinalistas', 'La tómbola sigue girando para reducir de 10 a 5.');
    buildDrum(result.round5);
    await spinDrum(9, mood.spin5);
    status('Semifinalistas confirmados.');
    await revealGrid('tbRound5', result.round5, mood.reveal + 40);

    await sleep(mood.pauseB);
    phase('Ronda 3: quedan 3 finalistas', 'Nueva mezcla aleatoria para definir a los tres últimos números.');
    buildDrum(result.round3);
    await spinDrum(10, mood.spin3);
    status('Finalistas confirmados. Iniciando definición del ganador...');
    await revealGrid('tbRound3', result.round3, mood.reveal + 60);

    await sleep(mood.pauseC);
    phase('Gran final', 'Último giro...');
    status('Definiendo imagen ganadora...');
    for (const n of ['3','2','1']) {
      status(`Definiendo imagen ganadora en ${n}...`);
      await sleep(520);
    }

    const w = result.winner;
    document.getElementById('tbWinner').innerHTML = `
      <article class="tb-winner-card">
        <h4>Imagen ganadora</h4>
        <div class="tb-win-number">#${esc(w.number)}</div>
        <div style="font-weight:700;color:#fff;">${esc(w.buyer_name || 'Participante')}</div>
        <div class="tb-meta" style="margin-top:.28rem;">${esc(w.buyer_email || 'Sin correo')}</div>
      </article>`;

    gsap.fromTo('.tb-winner-card',
      { opacity: 0, y: 30, scale: .84, rotateX: -16 },
      { opacity: 1, y: 0, scale: 1, rotateX: 0, duration: .9, ease: 'back.out(1.4)' }
    );

    confetti({ particleCount: 160, spread: 95, origin: { y: 0.65 } });
    setTimeout(() => confetti({ particleCount: 110, spread: 70, origin: { x: 0.2, y: 0.7 } }), 220);
    setTimeout(() => confetti({ particleCount: 110, spread: 70, origin: { x: 0.8, y: 0.7 } }), 240);

    phase('Resultado oficial guardado', 'La tómbola quedó bloqueada para este sorteo.');
    status('Ganador registrado correctamente.');
    document.getElementById('tbSaved').textContent =
      `Ganador guardado automaticamente. Imagen #${result.winner.number}. La tombola quedó bloqueada para este sorteo.`;

    await loadRaffles();
  } catch (e) {
    alert(e.message);
  } finally {
    btn.textContent = 'Iniciar tombola';
    renderInfo();
  }
}

document.getElementById('tbRaffle').addEventListener('change', renderInfo);
document.getElementById('tbStartBtn').addEventListener('click', runTombola);
document.getElementById('tbResetBtn').addEventListener('click', resetTombola);
document.getElementById('tbDrama').addEventListener('input', setDramaLabel);
setDramaLabel();
loadRaffles().catch(e => alert(e.message));
</script>
</body>
</html>
