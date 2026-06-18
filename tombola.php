<?php
require __DIR__ . '/api/config.php';

admin_session_start();
if (empty($_SESSION['admin_id'])) {
    header('Location: panel/index.php?redirect=' . rawurlencode('../tombola.php'));
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
    .tb-wrap { max-width: 1240px; margin: 0 auto; padding: 76px 16px 28px; }
    .tb-card {
      background: linear-gradient(170deg, rgba(24,20,44,.92), rgba(14,12,29,.95));
      border: 1px solid rgba(170,147,255,.22);
      border-radius: 14px;
      padding: .8rem;
      box-shadow: 0 24px 46px rgba(0,0,0,.32);
    }
    .tb-meta { font-size:.82rem; color:var(--text-muted); }
    .tb-controls {
      display:flex;
      justify-content:space-between;
      gap:.65rem;
      flex-wrap:wrap;
      align-items:end;
    }
    .tb-show-stage {
      margin-top:.65rem;
      padding:.9rem;
      border-radius:16px;
      border:1px solid rgba(255,255,255,.13);
      background: radial-gradient(ellipse at center, rgba(124,58,237,.17), rgba(11,10,20,.92) 72%);
      overflow:hidden;
      position:relative;
      perspective: 1200px;
      min-height: 320px;
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
      margin-bottom:.55rem;
    }
    .tb-phase h3 {
      margin:0;
      font-size:1.15rem;
      letter-spacing:.03em;
      color:#f2ecff;
    }
    .tb-phase p {
      margin:.35rem 0 0;
      color:#c5b8ef;
      font-size:.82rem;
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
      margin-top:.55rem;
      display:grid;
      gap:.55rem;
      grid-template-columns: repeat(auto-fit,minmax(132px,1fr));
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
    .tb-flow-grid {
      margin-top: 1rem;
      display: grid;
      grid-template-columns: repeat(8, minmax(0, 1fr));
      gap: .45rem;
      height: 386px;
      min-height: 386px;
      max-height: 386px;
      overflow: hidden;
      mask-image: linear-gradient(to bottom, transparent, #000 9%, #000 91%, transparent);
    }
    .tb-flow-grid:empty,
    .tb-final-column:empty,
    .tb-reveal-grid:empty {
      display: none;
      min-height: 0;
      margin: 0;
    }
    .tb-flow-column { display:flex; flex-direction:column; gap:.45rem; will-change:transform; }
    .tb-flow-item {
      border: 1px solid rgba(255,255,255,.18);
      background: rgba(255,255,255,.07);
      border-radius: 10px;
      padding: .42rem .45rem;
      min-height: 58px;
      color: #f8f4ff;
      font-weight: 800;
      font-size: .8rem;
      text-align: center;
      box-shadow: 0 8px 18px rgba(0,0,0,.22);
      transition: background .28s ease, color .28s ease, border-color .28s ease, box-shadow .28s ease, transform .28s ease;
    }
    .tb-flow-item.selected {
      background: radial-gradient(circle at 50% 35%, #fff7a8 0%, #facc15 54%, #eab308 100%);
      border-color: #fde047;
      color: #111827;
      box-shadow: 0 0 0 2px rgba(250,204,21,.6), 0 0 32px rgba(250,204,21,.78), 0 14px 28px rgba(0,0,0,.32);
      transform: scale(1.04);
    }
    .tb-flow-item.selected small {
      color: #1f2937;
    }
    .tb-flow-prize,
    .tb-ball-prize {
      width: 30px;
      height: 30px;
      border-radius: 8px;
      object-fit: cover;
      display: block;
      margin: 0 auto .22rem;
      border: 1px solid rgba(255,255,255,.2);
      background: rgba(255,255,255,.08);
    }
    .tb-flow-item small {
      display:block;
      margin-top:.12rem;
      color:#cbbcf2;
      font-weight:600;
      font-size:.62rem;
      white-space:nowrap;
      overflow:hidden;
      text-overflow:ellipsis;
    }
    .tb-flow-grid.is-spinning .tb-flow-column {
      animation: tbColumnSpin var(--tb-spin-duration, 7s) linear infinite;
      transform: translateY(0);
    }
    .tb-flow-grid.is-spinning .tb-flow-column:nth-child(2n) { --tb-spin-duration: 7.6s; }
    .tb-flow-grid.is-spinning .tb-flow-column:nth-child(3n) { --tb-spin-duration: 8.2s; }
    @keyframes tbColumnSpin {
      from { transform: translateY(0); }
      to { transform: translateY(-50%); }
    }
    .tb-final-column {
      width:min(520px,100%);
      margin:1rem auto 0;
      max-height:330px;
      overflow:hidden;
      mask-image: linear-gradient(to bottom, transparent, #000 10%, #000 90%, transparent);
    }
    .tb-flow-track { display:flex; flex-direction:column; gap:.55rem; will-change:transform; }
    .tb-final-column .tb-flow-item {
      font-size:1rem;
      min-height:50px;
      display:flex;
      flex-direction:column;
      justify-content:center;
    }
    .tb-result-title {
      margin:1rem 0 .35rem;
      color:#fef3c7;
      text-align:center;
      font-weight:900;
      letter-spacing:.06em;
      text-transform:uppercase;
    }
    .tb-stop-zone {
      display:flex;
      justify-content:center;
      margin-top:.85rem;
    }
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
      .tb-flow-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
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
        <div class="logo-icon">ðŸŽŸï¸</div><span class="brand">Sur<em>tea</em>dos</span>
      <?php endif; ?>
    </a>
    <div class="navbar-actions">
      <a href="panel/dashboard.php" class="btn btn-outline btn-sm">â† Panel</a>
      <a href="panel/logout.php" class="btn btn-ghost btn-sm">Salir</a>
    </div>
  </div>
</nav>

<div class="tb-wrap">
  <div class="tb-card" style="margin-bottom:.65rem;">
    <div class="tb-controls">
      <div>
        <div class="badge">Tombola oficial</div>
        <h2 style="margin:.25rem 0 0;font-size:clamp(1.25rem,2.4vw,1.8rem);line-height:1.1;">Tombola: imagen ganadora</h2>
        <p class="tb-meta" style="margin:.25rem 0 0;">Proceso aleatorio: giro continuo -> detener -> 10 imagenes preseleccionadas.</p>
      </div>
      <div>
        <div class="tb-actions">
        <div>
          <label class="form-label">Sorteo</label>
          <select class="form-control" id="tbRaffle" style="min-width:260px;max-width:480px;padding:.55rem .75rem;"></select>
        </div>
        <button class="btn btn-primary" id="tbStartBtn">Iniciar Tombola</button>
        <button class="btn btn-accent" id="tbSelect5Btn" style="display:none;">Seleccionar 5</button>
        <button class="btn btn-primary" id="tbWinnerBtn" style="display:none;">Definir ganador</button>
        <button class="btn btn-outline" id="tbResetBtn" style="display:none;margin-left:.5rem;opacity:.7;" title="Solo para pruebas">ðŸ”“ Resetear</button>
        </div>
        <label class="tb-speed">Ritmo
          <input type="range" id="tbDrama" min="1" max="3" value="2">
          <span id="tbDramaLabel">Medio</span>
        </label>
      </div>
    </div>
    <div id="tbInfo" class="tb-meta" style="margin-top:.45rem;"></div>
  </div>

  <div class="tb-show-stage tb-card">
    <div class="tb-stage-glow"></div>
    <div class="tb-phase">
      <div class="tb-badge-live"><span class="tb-dot"></span>EN VIVO</div>
      <h3 id="tbPhaseTitle">Esperando inicio de tÃ³mbola</h3>
      <p id="tbPhaseSub">Selecciona un sorteo y presiona iniciar para comenzar el show.</p>
    </div>

    <div id="tbUniverseFlow" class="tb-flow-grid"></div>
    <div id="tbRound50" class="tb-reveal-grid"></div>
    <div id="tbFinalFlow" class="tb-final-column"></div>
    <div id="tbRound5" class="tb-reveal-grid"></div>
    <div id="tbWinner" class="tb-final"></div>

    <div class="tb-stop-zone">
      <button class="btn btn-accent btn-lg" id="tbStopBtn" style="display:none;">Detener y seleccionar (10)</button>
    </div>
    <div id="tbStatus" class="tb-status"></div>
    <p id="tbSaved" class="tb-meta" style="margin-top:.4rem;text-align:center;"></p>
  </div>
</div>

<script>
const tbState = {
  raffles: [],
  auditId: '',
  selectedRaffleId: '',
  pool: [],
  semifinalists: [],
  finalists: [],
  winner: null,
  spinTimer: null,
  spinning: false,
};

const API_BASE = new URL('api', window.location.href).pathname.replace(/\/?$/, '/');
const DRAMA_PRESETS = {
  1: { label: 'Rapido', scroll: 5.4, finalScroll: 4.4, reveal: 55, pause: 180 },
  2: { label: 'Medio', scroll: 8.2, finalScroll: 6.2, reveal: 85, pause: 300 },
  3: { label: 'Epico', scroll: 11.5, finalScroll: 8.4, reveal: 120, pause: 460 },
};

function esc(s){return String(s ?? '').replace(/[&<>\"]/g,m=>({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;"}[m]));}
function sleep(ms){return new Promise(r=>setTimeout(r,ms));}
function tweenTo(target, vars){return new Promise(resolve => gsap.to(target, { ...vars, onComplete: resolve }));}
function shuffleVisual(items){
  const out = [...items];
  for(let i = out.length - 1; i > 0; i--){
    const j = Math.floor(Math.random() * (i + 1));
    [out[i], out[j]] = [out[j], out[i]];
  }
  return out;
}

async function api(path, opts = {}) {
  const { method = 'GET', body } = opts;
  const res = await fetch(API_BASE + path.replace(/^\//, ''), {
    method,
    credentials: 'include',
    headers: { 'Content-Type': 'application/json' },
    body: body ? JSON.stringify(body) : undefined,
  });
  const text = await res.text();
  let json;
  try {
    json = JSON.parse(text);
  } catch (e) {
    if (res.status === 401 || text.includes('Acceso Administrador')) {
      window.location.href = 'panel/index.php?redirect=' + encodeURIComponent('../tombola.php');
      return;
    }
    throw new Error('Respuesta no valida del servidor. Revisa la sesion de administrador o el log PHP.');
  }
  if (!json.ok) {
    if (res.status === 401) {
      window.location.href = 'panel/index.php?redirect=' + encodeURIComponent('../tombola.php');
      return;
    }
    throw new Error(json.error || 'Error inesperado');
  }
  return json.data;
}

function itemHtml(item){
  const img = item.prize_image ? `<img class="tb-flow-prize" src="${esc(item.prize_image)}" alt="">` : '';
  return `<div class="tb-flow-item" data-number="${esc(item.number)}">${img}${esc(item.number)}<small>${esc(item.buyer_name || 'Participante')}</small></div>`;
}

function splitColumns(items, columns) {
  const buckets = Array.from({ length: columns }, () => []);
  items.forEach((item, idx) => buckets[idx % columns].push(item));
  return buckets;
}

function renderFlowGrid(items, columns = 8, repeat = 1) {
  const el = document.getElementById('tbUniverseFlow');
  const buckets = splitColumns(items, columns);
  el.innerHTML = buckets.map(bucket => {
    const repeated = Array.from({ length: repeat }, () => bucket).flat();
    return `<div class="tb-flow-column">${repeated.map(itemHtml).join('')}</div>`;
  }).join('');
}

function renderFixedBoard(items) {
  const pool = shuffleVisual(items || []);
  const frame = [];
  if (!pool.length) {
    document.getElementById('tbUniverseFlow').innerHTML = '';
    return;
  }
  while (frame.length < 48) {
    frame.push(pool[frame.length % pool.length]);
  }
  renderFlowGrid(frame, 8, 1);
}

function renderSpinningBoard(items) {
  const source = shuffleVisual(items || []);
  const frame = [];
  if (!source.length) {
    document.getElementById('tbUniverseFlow').innerHTML = '';
    return;
  }
  while (frame.length < Math.max(96, source.length * 2)) {
    frame.push(source[frame.length % source.length]);
  }
  renderFlowGrid(frame, 8, 2);
  document.getElementById('tbUniverseFlow').classList.add('is-spinning');
}

function renderSelectionBoard(pool, selected) {
  const selectedNumbers = new Set(selected.map(item => String(item.number)));
  const fillers = shuffleVisual(pool.filter(item => !selectedNumbers.has(String(item.number))));
  const frame = [...selected];
  let i = 0;
  const fillSource = fillers.length ? fillers : selected;
  while (frame.length < 48 && fillSource.length) {
    frame.push(fillSource[i % fillSource.length]);
    i++;
  }
  renderFlowGrid(shuffleVisual(frame), 8, 1);
}

async function highlightSelected10(items) {
  const nodes = [...document.querySelectorAll('#tbUniverseFlow .tb-flow-item')];
  for (const item of items) {
    const node = nodes.find(el => el.dataset.number === String(item.number));
    if (node) {
      node.classList.add('selected');
      gsap.fromTo(node, { scale: 1.18 }, { scale: 1.04, duration: .45, ease: 'elastic.out(1,.55)' });
    }
    status(`Imagen seleccionada ${items.indexOf(item) + 1} de ${items.length}: ${item.number}`);
    await sleep(1000);
  }
}

function renderColumn(items, repeat = 1) {
  const el = document.getElementById('tbFinalFlow');
  const repeated = Array.from({ length: repeat }, () => items).flat();
  el.innerHTML = `<div class="tb-flow-track">${repeated.map(itemHtml).join('')}</div>`;
}

async function playUniverseFlow(items, cycles = 10) {
  const mood = currentDrama();
  const source = shuffleVisual(items.length ? items : []);
  phase(`Universo completo: 10 pasadas`, 'Todas las imagenes pagadas avanzan en orden aleatorio y continuo.');
  status(`Recorriendo ${source.length} imagenes pagadas en 8 columnas.`);
  renderFlowGrid(source, 8, cycles);
  const columns = [...document.querySelectorAll('#tbUniverseFlow .tb-flow-column')];
  const distance = Math.max(360, document.getElementById('tbUniverseFlow').scrollHeight - document.getElementById('tbUniverseFlow').clientHeight + 180);
  gsap.set(columns, { y: 0 });
  await Promise.all(columns.map((col, idx) => tweenTo(col, {
    y: -distance,
    duration: mood.scroll + (idx % 3) * .45,
    ease: 'power1.inOut'
  })));
  await sleep(mood.pause);
}

async function playFinalFlow(items, cycles = 10, label = 'semifinalistas') {
  const mood = currentDrama();
  const source = shuffleVisual(items);
  phase(`${label}: 10 pasadas`, 'Las imagenes avanzan de forma vertical y continua.');
  status(`Recorriendo ${source.length} imagenes ${label}.`);
  renderColumn(source, cycles);
  const el = document.getElementById('tbFinalFlow');
  const track = el.querySelector('.tb-flow-track');
  const distance = Math.max(320, track.scrollHeight - el.clientHeight + 140);
  gsap.set(track, { y: 0 });
  await tweenTo(track, { y: -distance, duration: mood.finalScroll, ease: 'power1.inOut' });
  gsap.set(track, { y: 0 });
  await sleep(mood.pause);
}

async function revealGrid(elId, items, perItemDelay) {
  const el = document.getElementById(elId);
  el.innerHTML = '';
  for (const it of items) {
    const wrap = document.createElement('div');
    const img = it.prize_image ? `<img class="tb-ball-prize" src="${esc(it.prize_image)}" alt="">` : '';
    wrap.innerHTML = `<div class="tb-ball">${img}${esc(it.number)}<small>${esc(it.buyer_name || 'Participante')}</small></div>`;
    const node = wrap.firstElementChild;
    node.style.opacity = '0';
    node.style.transform = 'translateY(18px) scale(.92)';
    el.appendChild(node);
    gsap.to(node, { opacity: 1, y: 0, scale: 1, duration: .32, ease: 'back.out(1.8)' });
    await sleep(perItemDelay);
  }
}

function phase(title, sub = '') {
  document.getElementById('tbPhaseTitle').textContent = title;
  document.getElementById('tbPhaseSub').textContent = sub;
}
function status(msg = '') { document.getElementById('tbStatus').textContent = msg; }
function currentDrama() {
  const v = Number(document.getElementById('tbDrama')?.value || 2);
  return DRAMA_PRESETS[v] || DRAMA_PRESETS[2];
}
function setDramaLabel() {
  const v = Number(document.getElementById('tbDrama')?.value || 2);
  const lb = document.getElementById('tbDramaLabel');
  if (lb) lb.textContent = (DRAMA_PRESETS[v] || DRAMA_PRESETS[2]).label;
}

function setButtons(stage = 'initial') {
  const startBtn = document.getElementById('tbStartBtn');
  const select5Btn = document.getElementById('tbSelect5Btn');
  const winnerBtn = document.getElementById('tbWinnerBtn');
  const stopBtn = document.getElementById('tbStopBtn');
  const raffle = tbState.raffles.find(x => x.id === document.getElementById('tbRaffle').value);
  const canStart = !!raffle && raffle.paid_images > 0;
  startBtn.style.display = stage === 'initial' ? '' : 'none';
  stopBtn.style.display = stage === 'spinning' ? '' : 'none';
  select5Btn.style.display = 'none';
  winnerBtn.style.display = 'none';
  startBtn.disabled = !canStart;
  select5Btn.disabled = false;
  winnerBtn.disabled = false;
}

async function loadRaffles() {
  const data = await api('/tombola.php');
  tbState.raffles = data || [];
  const sel = document.getElementById('tbRaffle');
  sel.innerHTML = tbState.raffles.map(r => {
    const previous = r.has_winner ? ' | ganador registrado' : '';
    return `<option value="${esc(r.id)}">${esc(r.title)} | ${r.paid_images} imagenes pagadas${previous}</option>`;
  }).join('') || '<option value="">Sin sorteos</option>';
  renderInfo();
}

function renderInfo() {
  const sel = document.getElementById('tbRaffle');
  const id = sel.value;
  const r = tbState.raffles.find(x => x.id === id);
  const info = document.getElementById('tbInfo');
  const resetBtn = document.getElementById('tbResetBtn');
  if (!r) {
    info.textContent = 'Selecciona un sorteo.';
    setButtons('initial');
    resetBtn.style.display = 'none';
    return;
  }
  tbState.selectedRaffleId = id;
  const suffix = r.has_winner ? ' Ya existe un ganador registrado, pero la tombola no se bloqueara por ahora.' : '';
  info.textContent = `Listo para sortear. Universo: ${r.paid_images} imagenes pagadas.${suffix}`;
  resetBtn.style.display = r.has_winner || r.audits_completed > 0 ? '' : 'none';
  clearStage(false);
  setButtons('initial');
}

function clearStage(clearState = true) {
  if (tbState.spinTimer) {
    clearInterval(tbState.spinTimer);
    tbState.spinTimer = null;
  }
  tbState.spinning = false;
  document.getElementById('tbUniverseFlow')?.classList.remove('is-spinning');
  document.getElementById('tbUniverseFlow').innerHTML = '';
  document.getElementById('tbRound50').innerHTML = '';
  document.getElementById('tbFinalFlow').innerHTML = '';
  document.getElementById('tbRound5').innerHTML = '';
  document.getElementById('tbWinner').innerHTML = '';
  document.getElementById('tbSaved').textContent = '';
  status('');
  phase('Esperando inicio de tombola', 'Selecciona un sorteo y presiona Iniciar Tombola para comenzar.');
  if (clearState) {
    tbState.auditId = '';
    tbState.pool = [];
    tbState.semifinalists = [];
    tbState.finalists = [];
    tbState.winner = null;
  }
}

async function resetTombola() {
  const raffleId = document.getElementById('tbRaffle').value;
  if (!raffleId) return;
  if (!confirm('¿Borrar ganadores y actas guardadas de este sorteo? (Solo para pruebas)')) return;
  const resetBtn = document.getElementById('tbResetBtn');
  resetBtn.disabled = true;
  resetBtn.textContent = 'Reseteando...';
  try {
    await fetch(API_BASE + 'tombola.php', {
      method: 'DELETE',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ raffle_id: raffleId })
    });
    clearStage();
    await loadRaffles();
  } catch(e) {
    alert('Error al resetear: ' + e.message);
  } finally {
    resetBtn.disabled = false;
    resetBtn.textContent = '🔓 Resetear';
  }
}

async function startInfiniteTombola() {
  const raffleId = document.getElementById('tbRaffle').value;
  if (!raffleId) return;
  const btn = document.getElementById('tbStartBtn');
  btn.disabled = true;
  btn.textContent = 'Iniciando...';
  clearStage();
  try {
    phase('Tombola en movimiento', 'Las imagenes pagadas avanzan continuamente en orden aleatorio.');
    status('Consultando imagenes pagadas...');
    const result = await api('/tombola.php', { method: 'POST', body: { raffle_id: raffleId, stage: 'semifinalists' } });
    tbState.auditId = result.audit_id;
    tbState.pool = result.pool || [];
    tbState.semifinalists = result.semifinalists || [];
    tbState.spinning = true;
    renderSpinningBoard(tbState.pool);
    status(`Girando ${result.pool_size} imagenes pagadas. Presiona detener para seleccionar 10.`);
    document.getElementById('tbSaved').textContent = `Acta digital preparada: ${result.audit_id}. La seleccion se revelara al detener.`;
    setButtons('spinning');
  } catch (e) {
    alert(e.message);
    setButtons('initial');
  } finally {
    btn.disabled = false;
    btn.textContent = 'Iniciar Tombola';
  }
}

async function stopAndSelect10() {
  if (!tbState.spinning || !tbState.semifinalists.length) return;
  const stopBtn = document.getElementById('tbStopBtn');
  stopBtn.disabled = true;
  stopBtn.textContent = 'Deteniendo...';
  try {
    if (tbState.spinTimer) {
      clearInterval(tbState.spinTimer);
      tbState.spinTimer = null;
    }
    tbState.spinning = false;
    document.getElementById('tbUniverseFlow').classList.remove('is-spinning');
    phase('Deteniendo tombola', 'Se congelan las imagenes y se encienden las 10 seleccionadas.');
    renderSelectionBoard(tbState.pool, tbState.semifinalists);
    status('Preparando iluminacion de las 10 imagenes seleccionadas...');
    await sleep(650);
    await highlightSelected10(tbState.semifinalists);
    phase('10 imagenes seleccionadas', 'Estas imagenes quedan preseleccionadas para la siguiente etapa.');
    status(`Acta digital: ${tbState.auditId}`);
    document.getElementById('tbSaved').textContent = `Seleccionadas ${tbState.semifinalists.length} imagenes desde ${tbState.pool.length} imagenes pagadas.`;
    setButtons('completed');
  } finally {
    stopBtn.disabled = false;
    stopBtn.textContent = 'Detener y seleccionar (10)';
  }
}

async function selectFinalists5() {
  const raffleId = document.getElementById('tbRaffle').value;
  if (!raffleId || !tbState.auditId) return;
  const btn = document.getElementById('tbSelect5Btn');
  btn.disabled = true;
  btn.textContent = 'Seleccionando...';
  try {
    const result = await api('/tombola.php', { method: 'POST', body: { raffle_id: raffleId, stage: 'finalists', audit_id: tbState.auditId } });
    tbState.semifinalists = result.semifinalists || tbState.semifinalists;
    tbState.finalists = result.finalists || [];
    document.getElementById('tbRound50').innerHTML = '';
    await playFinalFlow(tbState.semifinalists, 10, 'semifinalistas');
    document.getElementById('tbFinalFlow').innerHTML = '';
    phase('5 finalistas seleccionadas', 'Estas imagenes pasan a la definicion final.');
    status(`Hash actualizado: ${result.result_hash}`);
    await revealGrid('tbRound5', tbState.finalists, currentDrama().reveal + 60);
    document.getElementById('tbSaved').textContent = `Seleccionadas ${tbState.finalists.length} imagenes finalistas desde las 50 semifinalistas.`;
    setButtons('finalists');
  } catch (e) {
    alert(e.message);
    setButtons('semifinalists');
  } finally {
    btn.disabled = false;
    btn.textContent = 'Seleccionar 5';
  }
}

async function selectWinner() {
  const raffleId = document.getElementById('tbRaffle').value;
  if (!raffleId || !tbState.auditId) return;
  const btn = document.getElementById('tbWinnerBtn');
  btn.disabled = true;
  btn.textContent = 'Definiendo...';
  try {
    const result = await api('/tombola.php', { method: 'POST', body: { raffle_id: raffleId, stage: 'winner', audit_id: tbState.auditId } });
    tbState.finalists = result.finalists || tbState.finalists;
    tbState.winner = result.winner;
    document.getElementById('tbRound5').innerHTML = '';
    await playFinalFlow(tbState.finalists, 10, 'finalistas');
    document.getElementById('tbFinalFlow').innerHTML = '';
    const w = tbState.winner;
    phase('Resultado oficial guardado', 'El ganador quedo registrado. El sorteo sigue disponible por ahora.');
    status(`Acta digital: ${result.audit_id} | Hash final: ${result.result_hash}`);
    document.getElementById('tbWinner').innerHTML = `
      <article class="tb-winner-card">
        <h4>Imagen ganadora</h4>
        <div class="tb-win-number">${esc(w.number)}</div>
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
    document.getElementById('tbSaved').textContent = `Ganador guardado automaticamente. Imagen ${w.number}. Acta ${result.audit_id}.`;
    setButtons('completed');
    await loadRaffles();
  } catch (e) {
    alert(e.message);
    setButtons('finalists');
  } finally {
    btn.disabled = false;
    btn.textContent = 'Definir ganador';
  }
}

document.getElementById('tbRaffle').addEventListener('change', renderInfo);
document.getElementById('tbStartBtn').addEventListener('click', startInfiniteTombola);
document.getElementById('tbStopBtn').addEventListener('click', stopAndSelect10);
document.getElementById('tbSelect5Btn').addEventListener('click', selectFinalists5);
document.getElementById('tbWinnerBtn').addEventListener('click', selectWinner);
document.getElementById('tbResetBtn').addEventListener('click', resetTombola);
document.getElementById('tbDrama').addEventListener('input', setDramaLabel);
setDramaLabel();
loadRaffles().catch(e => alert(e.message));
</script>
</body>
</html>

