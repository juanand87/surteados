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
    .tb-flow-grid {
      margin-top: 1rem;
      display: grid;
      grid-template-columns: repeat(8, minmax(0, 1fr));
      gap: .45rem;
      min-height: 300px;
      max-height: 420px;
      overflow: hidden;
      mask-image: linear-gradient(to bottom, transparent, #000 9%, #000 91%, transparent);
    }
    .tb-flow-column { display:flex; flex-direction:column; gap:.45rem; will-change:transform; }
    .tb-flow-item {
      border: 1px solid rgba(255,255,255,.18);
      background: rgba(255,255,255,.07);
      border-radius: 10px;
      padding: .42rem .45rem;
      min-height: 44px;
      color: #f8f4ff;
      font-weight: 800;
      font-size: .8rem;
      text-align: center;
      box-shadow: 0 8px 18px rgba(0,0,0,.22);
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
    .tb-final-column {
      width:min(520px,100%);
      margin:1rem auto 0;
      display:flex;
      flex-direction:column;
      gap:.55rem;
      max-height:420px;
      overflow:hidden;
      mask-image: linear-gradient(to bottom, transparent, #000 10%, #000 90%, transparent);
    }
    .tb-final-column .tb-flow-item {
      font-size:1rem;
      min-height:54px;
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
        <button class="btn btn-primary" id="tbStartBtn">Seleccionar 50</button>
        <button class="btn btn-accent" id="tbSelect5Btn" style="display:none;">Seleccionar 5</button>
        <button class="btn btn-primary" id="tbWinnerBtn" style="display:none;">Definir ganador</button>
        <button class="btn btn-outline" id="tbResetBtn" style="display:none;margin-left:.5rem;opacity:.7;" title="Solo para pruebas">ðŸ”“ Resetear</button>
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
      <h3 id="tbPhaseTitle">Esperando inicio de tÃ³mbola</h3>
      <p id="tbPhaseSub">Selecciona un sorteo y presiona iniciar para comenzar el show.</p>
    </div>

    <div id="tbUniverseFlow" class="tb-flow-grid"></div>
    <div id="tbRound50" class="tb-reveal-grid"></div>
    <div id="tbFinalFlow" class="tb-final-column"></div>
    <div id="tbRound5" class="tb-reveal-grid"></div>
    <div id="tbWinner" class="tb-final"></div>

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
};

const API_BASE = new URL('api', window.location.href).pathname.replace(/\/?$/, '/');
const DRAMA_PRESETS = {
  1: { label: 'Rapido', pageDelay: 120, reveal: 80, pause: 220 },
  2: { label: 'Medio', pageDelay: 210, reveal: 130, pause: 420 },
  3: { label: 'Epico', pageDelay: 320, reveal: 190, pause: 680 },
};

function esc(s){return String(s ?? '').replace(/[&<>\"]/g,m=>({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;"}[m]));}
function sleep(ms){return new Promise(r=>setTimeout(r,ms));}
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
  return `<div class="tb-flow-item">${esc(item.number)}<small>${esc(item.buyer_name || 'Participante')}</small></div>`;
}

function renderFlowGrid(items, columns = 8) {
  const el = document.getElementById('tbUniverseFlow');
  const buckets = Array.from({ length: columns }, () => []);
  items.forEach((item, idx) => buckets[idx % columns].push(item));
  el.innerHTML = buckets.map(bucket => `<div class="tb-flow-column">${bucket.map(itemHtml).join('')}</div>`).join('');
  el.querySelectorAll('.tb-flow-column').forEach((col, idx) => {
    col.style.transform = `translateY(${idx % 2 ? '-18px' : '18px'})`;
    gsap.to(col, { y: idx % 2 ? 18 : -18, duration: .42, ease: 'power1.inOut' });
  });
}

function renderColumn(items) {
  const el = document.getElementById('tbFinalFlow');
  el.innerHTML = items.map(itemHtml).join('');
  gsap.fromTo('#tbFinalFlow .tb-flow-item',
    { opacity: .25, y: 32 },
    { opacity: 1, y: 0, duration: .34, stagger: .025, ease: 'power2.out' }
  );
}

async function playUniverseFlow(items, cycles = 10) {
  const mood = currentDrama();
  const source = items.length ? items : [];
  for (let cycle = 1; cycle <= cycles; cycle++) {
    phase(`Pasada ${cycle} de ${cycles}: universo completo`, 'Las imagenes pagadas pasan en orden aleatorio, en bloques de 50.');
    const shuffled = shuffleVisual(source);
    for (let i = 0; i < shuffled.length; i += 50) {
      const chunk = shuffled.slice(i, i + 50);
      renderFlowGrid(chunk, 8);
      status(`Mostrando imagenes ${i + 1} a ${Math.min(i + 50, shuffled.length)} de ${shuffled.length}`);
      await sleep(mood.pageDelay);
    }
  }
}

async function playFinalFlow(items, cycles = 10, label = 'semifinalistas') {
  const mood = currentDrama();
  for (let cycle = 1; cycle <= cycles; cycle++) {
    phase(`Pasada ${cycle} de ${cycles}: ${label}`, 'Las imagenes vuelven a pasar en orden aleatorio.');
    renderColumn(shuffleVisual(items));
    status(`Mezclando ${items.length} imagenes ${label}.`);
    await sleep(Math.max(520, mood.pageDelay * Math.min(items.length, 8)));
  }
}

async function revealGrid(elId, items, perItemDelay) {
  const el = document.getElementById(elId);
  el.innerHTML = '';
  for (const it of items) {
    const wrap = document.createElement('div');
    wrap.innerHTML = `<div class="tb-ball">${esc(it.number)}<small>${esc(it.buyer_name || 'Participante')}</small></div>`;
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
  const raffle = tbState.raffles.find(x => x.id === document.getElementById('tbRaffle').value);
  const canStart = !!raffle && raffle.paid_images > 0;
  startBtn.style.display = stage === 'initial' ? '' : 'none';
  select5Btn.style.display = stage === 'semifinalists' ? '' : 'none';
  winnerBtn.style.display = stage === 'finalists' ? '' : 'none';
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
  document.getElementById('tbUniverseFlow').innerHTML = '';
  document.getElementById('tbRound50').innerHTML = '';
  document.getElementById('tbFinalFlow').innerHTML = '';
  document.getElementById('tbRound5').innerHTML = '';
  document.getElementById('tbWinner').innerHTML = '';
  document.getElementById('tbSaved').textContent = '';
  status('');
  phase('Esperando inicio de tombola', 'Selecciona un sorteo y presiona seleccionar 50 para comenzar.');
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

async function selectSemifinalists50() {
  const raffleId = document.getElementById('tbRaffle').value;
  if (!raffleId) return;
  const btn = document.getElementById('tbStartBtn');
  btn.disabled = true;
  btn.textContent = 'Seleccionando...';
  clearStage();
  try {
    phase('Preparando universo de imagenes', 'El servidor esta seleccionando 50 semifinalistas con aleatoriedad segura.');
    status('Consultando imagenes pagadas...');
    const result = await api('/tombola.php', { method: 'POST', body: { raffle_id: raffleId, stage: 'semifinalists' } });
    tbState.auditId = result.audit_id;
    tbState.pool = result.pool || [];
    tbState.semifinalists = result.semifinalists || [];
    await playUniverseFlow(tbState.pool, 10);
    document.getElementById('tbUniverseFlow').innerHTML = '';
    phase('50 semifinalistas seleccionadas', 'Estas imagenes pasan a la tombola de semifinalistas.');
    status(`Acta digital: ${result.audit_id} | Hash: ${result.result_hash}`);
    await revealGrid('tbRound50', tbState.semifinalists, currentDrama().reveal);
    document.getElementById('tbSaved').textContent = `Seleccionadas ${tbState.semifinalists.length} imagenes semifinalistas desde ${result.pool_size} imagenes pagadas.`;
    setButtons('semifinalists');
  } catch (e) {
    alert(e.message);
    setButtons('initial');
  } finally {
    btn.disabled = false;
    btn.textContent = 'Seleccionar 50';
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
document.getElementById('tbStartBtn').addEventListener('click', selectSemifinalists50);
document.getElementById('tbSelect5Btn').addEventListener('click', selectFinalists5);
document.getElementById('tbWinnerBtn').addEventListener('click', selectWinner);
document.getElementById('tbResetBtn').addEventListener('click', resetTombola);
document.getElementById('tbDrama').addEventListener('input', setDramaLabel);
setDramaLabel();
loadRaffles().catch(e => alert(e.message));
</script>
</body>
</html>

