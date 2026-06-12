/**
 * SURTEADOS — Admin Panel JS (API-driven)
 * Replaces localStorage admin.js with async fetch calls.
 */

const API_BASE = window.API_BASE || '/surteados/api';

/* ── Fetch helper ─────────────────────────────────────────────────────────── */
async function api(endpoint, { method = 'GET', body, params } = {}) {
  let url = `${API_BASE}${endpoint}`;
  if (params) url += '?' + new URLSearchParams(params);
  const opts = { method, credentials: 'include' };
  if (body) {
    opts.headers = { 'Content-Type': 'application/json' };
    opts.body = JSON.stringify(body);
  }
  const resp = await fetch(url, opts);
  const json = await resp.json();
  if (!json.ok) throw new Error(json.error || 'Error en la petición');
  return json.data;
}

/* ── Toast ────────────────────────────────────────────────────────────────── */
function showToast(msg, type = 'success') {
  const container = document.getElementById('toast-container');
  const t = document.createElement('div');
  t.className = `toast toast-${type}`;
  t.textContent = msg;
  container.appendChild(t);
  setTimeout(() => t.classList.add('show'), 10);
  setTimeout(() => { t.classList.remove('show'); setTimeout(() => t.remove(), 300); }, 3500);
}

/* ── Formatting helpers ───────────────────────────────────────────────────── */
function fmtCLP(n) {
  return '$' + Number(n).toLocaleString('es-CL');
}
function fmtDate(d) {
  if (!d) return '—';
  return new Date(d).toLocaleDateString('es-CL', { day: '2-digit', month: 'short', year: 'numeric' });
}
function paymentBadge(s) {
  const map = { paid: ['pill-green', '✅ Pagado'], pending: ['pill-yellow', '⏳ Pendiente'], failed: ['pill-red', '❌ Fallido'], refunded: ['pill-gray', '↩ Devuelto'] };
  const [cls, txt] = map[s] || ['pill-gray', s];
  return `<span class="pill ${cls}">${txt}</span>`;
}
function statusBadge(s) {
  const map = { active: ['pill-green', '🟢 Activo'], soon: ['pill-yellow', '🟡 Próximo'], draft: ['pill-gray', '⚪ Borrador'], ended: ['pill-gray', '⚫ Finalizado'] };
  const [cls, txt] = map[s] || ['pill-gray', s];
  return `<span class="pill ${cls}">${txt}</span>`;
}

/* ══════════════════════════════════════════════════════════════════════════ */
/*  THEME UTILITY                                                            */
/* ══════════════════════════════════════════════════════════════════════════ */
const lightenHex = (hex, p) => {
  const [r,g,b] = hex.match(/[\da-f]{2}/gi).map(v => parseInt(v,16));
  return '#' + [r,g,b].map(c => Math.min(255,Math.round(c+(255-c)*p)).toString(16).padStart(2,'0')).join('');
};
const darkenHex = (hex, p) => {
  const [r,g,b] = hex.match(/[\da-f]{2}/gi).map(v => parseInt(v,16));
  return '#' + [r,g,b].map(c => Math.max(0,Math.round(c*(1-p))).toString(16).padStart(2,'0')).join('');
};
function applyColors(primary, accent) {
  document.documentElement.style.setProperty('--color-primary',       primary);
  document.documentElement.style.setProperty('--color-primary-light', lightenHex(primary, 0.22));
  document.documentElement.style.setProperty('--color-primary-dark',  darkenHex(primary, 0.22));
  document.documentElement.style.setProperty('--color-accent',        accent);
  document.documentElement.style.setProperty('--color-accent-light',  lightenHex(accent, 0.2));
  document.documentElement.style.setProperty('--color-accent-dark',   darkenHex(accent, 0.2));
  localStorage.setItem('surteados_theme', JSON.stringify({ primary, accent }));
  // sync color pickers if they exist
  ['Primary','Accent'].forEach(cap => {
    const v = cap === 'Primary' ? primary : accent;
    const col = document.getElementById(`diColor${cap}`);
    const hex = document.getElementById(`diColor${cap}Hex`);
    if (col) col.value = v;
    if (hex) hex.value = v;
  });
}

/* ══════════════════════════════════════════════════════════════════════════ */
/*  NAVIGATION                                                               */
/* ══════════════════════════════════════════════════════════════════════════ */
function activateSection(name) {
  document.querySelectorAll('.admin-section').forEach(s => s.classList.remove('active'));
  document.querySelectorAll('.admin-nav-item[data-section]').forEach(n => n.classList.remove('active'));
  const sec = document.getElementById(`sec-${name}`);
  const nav = document.querySelector(`.admin-nav-item[data-section="${name}"]`);
  if (sec) sec.classList.add('active');
  if (nav) nav.classList.add('active');
}

async function renderSection(name) {
  activateSection(name);
  const renderers = {
    dashboard: renderDashboard,
    sorteos:   renderSorteos,
    tickets:   renderTickets,
    ganadores: renderGanadores,
    settings:  renderSettings,
    diseno:    renderDiseno,
    smtp:      renderSmtp,
    flow:          renderFlow,
    ticketformat:  renderTicketFormat,
  };
  if (renderers[name]) {
    try { await renderers[name](); }
    catch (e) { showToast('Error: ' + e.message, 'error'); }
  }
}

document.querySelectorAll('.admin-nav-item[data-section]').forEach(item => {
  item.addEventListener('click', () => renderSection(item.dataset.section));
});

/* ══════════════════════════════════════════════════════════════════════════ */
/*  DASHBOARD                                                                */
/* ══════════════════════════════════════════════════════════════════════════ */
async function renderDashboard() {
  const [raffles, tickets, winners] = await Promise.all([
    api('/raffles.php'),
    api('/tickets.php'),
    api('/winners.php'),
  ]);

  const paid     = tickets.filter(t => t.payment_status === 'paid');
  const revenue  = paid.reduce((s, t) => s + Number(t.amount), 0);

  document.getElementById('dashStats').innerHTML = `
    <div class="stat-card"><div class="stat-number">${raffles.length}</div><div class="stat-label">Sorteos activos</div></div>
    <div class="stat-card"><div class="stat-number">${paid.length}</div><div class="stat-label">Tickets vendidos</div></div>
    <div class="stat-card"><div class="stat-number">${fmtCLP(revenue)}</div><div class="stat-label">Ingresos totales</div></div>
    <div class="stat-card"><div class="stat-number">${winners.length}</div><div class="stat-label">Ganadores</div></div>
  `;

  // Recent tickets
  const recent = paid.slice(0, 8);
  document.getElementById('dashRecentTickets').innerHTML = recent.length
    ? recent.map(t => `<tr>
        <td>${t.buyer_name}</td>
        <td>${t.raffle_title || '—'}</td>
        <td>${fmtCLP(t.amount)}</td>
        <td>${fmtDate(t.created_at)}</td>
      </tr>`).join('')
    : '<tr><td colspan="4" style="opacity:.5; text-align:center;">Sin ventas aún</td></tr>';

  // Raffle progress bars
  document.getElementById('dashRaffleProgress').innerHTML = raffles.map(r => {
    const hasLimit = Number(r.total_tickets) > 0;
    const pct = hasLimit ? Math.round(r.sold_tickets / r.total_tickets * 100) : 0;
    return `<div style="margin-bottom:1rem;">
      <div style="display:flex; justify-content:space-between; margin-bottom:.25rem; font-size:.85rem;">
        <span>${r.title}</span><span>${hasLimit ? `${r.sold_tickets} / ${r.total_tickets}` : `${r.sold_tickets} / ilimitado`}</span>
      </div>
      ${hasLimit ? `<div style="background:var(--bg-base); border-radius:999px; height:8px;"><div style="background:var(--color-primary); border-radius:999px; width:${pct}%; height:100%; transition:width .5s;"></div></div>` : ''}
    </div>`;
  }).join('') || '<p style="opacity:.5;">Sin sorteos</p>';
}

/* ══════════════════════════════════════════════════════════════════════════ */
/*  SORTEOS                                                                  */
/* ══════════════════════════════════════════════════════════════════════════ */
async function renderSorteos() {
  const raffles = await api('/raffles.php');
  const tbody   = document.getElementById('sorteosTable');
  tbody.innerHTML = raffles.map(r => `
    <tr>
      <td><strong>${r.title}</strong></td>
      <td>${r.category}</td>
      <td>${statusBadge(r.status)}</td>
      <td>${Number(r.total_tickets) > 0 ? `${r.sold_tickets} / ${r.total_tickets}` : `${r.sold_tickets} / ilimitado`}</td>
      <td>${(r.prizes || [])[0]?.name || (r.prizes || [])[0]?.label || '—'}</td>
      <td>${fmtDate(r.draw_date || r.end_date)}</td>
      <td>
        <button class="btn btn-ghost btn-sm" onclick="editRaffle('${r.id}')">✏️</button>
        <button class="btn btn-ghost btn-sm" style="color:#ef4444" onclick="deleteRaffle('${r.id}','${r.title.replace(/'/g,"\\'").replace(/"/g,'&quot;')}')">🗑️</button>
      </td>
    </tr>`).join('') || '<tr><td colspan="7" style="opacity:.5; text-align:center;">Sin sorteos</td></tr>';
}

async function deleteRaffle(id, title) {
  if (!confirm(`¿Eliminar el sorteo "${title}"? Esta acción no se puede deshacer.`)) return;
  try {
    await api('/raffles.php', { method: 'DELETE', params: { id } });
    showToast('Sorteo eliminado');
    await renderSorteos();
    await renderDashboard();
  } catch (e) { showToast(e.message, 'error'); }
}

/* ── Raffle modal ─────────────────────────────────────────────────────────── */
let _editingRaffleId = null;
let _packs  = [];

function openRaffleModal() {
  _editingRaffleId = null;
  _packs  = [];
  document.getElementById('raffleModalTitle').textContent = 'Nuevo Sorteo';
  ['rf_title','rf_description','rf_legalText','rf_meetLink','rf_prizeName','rf_prizeEmoji'].forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
  document.getElementById('rf_category').value  = 'Tecnología';
  document.getElementById('rf_status').value    = 'active';
  document.getElementById('rf_drawDate').value  = '';
  document.getElementById('rf_totalTickets').value = '';
  document.getElementById('rf_featured').checked   = false;
  clearRaffleImage();
  clearRafflePrizeImage();
  switchRaffleTab('info');
  renderPackRows();
  document.getElementById('raffleModal').classList.add('open');
}

async function editRaffle(id) {
  try {
    const r = await api('/raffles.php', { params: { id } });
    _editingRaffleId = r.id;
    _packs  = (r.packs  || []).map(p => ({ ...p }));
    const prize = (r.prizes || [])[0] || {};

    document.getElementById('raffleModalTitle').textContent = 'Editar Sorteo';
    document.getElementById('rf_title').value        = r.title        || '';
    document.getElementById('rf_description').value  = r.description  || '';
    document.getElementById('rf_category').value     = r.category     || 'Tecnología';
    document.getElementById('rf_status').value       = r.status       || 'active';
    document.getElementById('rf_totalTickets').value = r.total_tickets || '';
    document.getElementById('rf_featured').checked   = !!r.featured;
    document.getElementById('rf_legalText').value    = r.legal_text   || '';
    document.getElementById('rf_prizeName').value    = prize.name || prize.label || '';
    document.getElementById('rf_prizeEmoji').value   = prize.emoji || '🏆';
    document.getElementById('rf_prizeImageUrl').value = prize.image_url || '';
    document.getElementById('rf_meetLink')?.setAttribute('value', r.meet_link || '');
    if (document.getElementById('rf_meetLink')) document.getElementById('rf_meetLink').value = r.meet_link || '';
    document.getElementById('rf_imageUrl').value     = r.image_url    || '';
    const _rPrev = document.getElementById('rf_imagePreview');
    if (_rPrev) _rPrev.innerHTML = r.image_url
      ? `<img src="${r.image_url}" style="width:100%;height:100%;object-fit:cover;">`
      : (r.image_emoji || '🎁');
    const _pPrev = document.getElementById('rf_prizeImagePreview');
    if (_pPrev) _pPrev.innerHTML = prize.image_url
      ? `<img src="${prize.image_url}" style="width:100%;height:100%;object-fit:cover;">`
      : (prize.emoji || '🏆');

    if (r.draw_date) {
      const d = new Date(r.draw_date);
      const local = new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0,16);
      document.getElementById('rf_drawDate').value = local;
    }

    switchRaffleTab('info');
    renderPackRows();
    document.getElementById('raffleModal').classList.add('open');
  } catch (e) { showToast(e.message, 'error'); }
}

function closeRaffleModal() {
  document.getElementById('raffleModal').classList.remove('open');
}

async function handleRaffleImageUpload(input) {
  if (!input.files[0]) return;
  const fd = new FormData();
  fd.append('file', input.files[0]);
  fd.append('type', 'raffle');
  try {
    const resp = await fetch(`${API_BASE}/upload.php`, { method: 'POST', credentials: 'include', body: fd });
    const json = await resp.json();
    if (!json.ok) throw new Error(json.error);
    document.getElementById('rf_imageUrl').value = json.data.url;
    const preview = document.getElementById('rf_imagePreview');
    preview.innerHTML = `<img src="${json.data.url}" style="width:100%;height:100%;object-fit:cover;">`;
    showToast('Imagen del sorteo cargada ✅');
  } catch(e) { showToast(e.message, 'error'); }
}

function clearRaffleImage() {
  document.getElementById('rf_imageUrl').value = '';
  document.getElementById('rf_imagePreview').innerHTML = '🎁';
  const fi = document.getElementById('rf_imageFile');
  if (fi) fi.value = '';
}

async function handleRafflePrizeImageUpload(input) {
  if (!input.files[0]) return;
  const fd = new FormData();
  fd.append('file', input.files[0]);
  fd.append('type', 'prize');
  try {
    const resp = await fetch(`${API_BASE}/upload.php`, { method: 'POST', credentials: 'include', body: fd });
    const json = await resp.json();
    if (!json.ok) throw new Error(json.error);
    document.getElementById('rf_prizeImageUrl').value = json.data.url;
    const preview = document.getElementById('rf_prizeImagePreview');
    preview.innerHTML = `<img src="${json.data.url}" style="width:100%;height:100%;object-fit:cover;">`;
    showToast('Imagen del premio cargada ✅');
  } catch(e) { showToast(e.message, 'error'); }
}

function clearRafflePrizeImage() {
  const emoji = document.getElementById('rf_prizeEmoji')?.value?.trim() || '🏆';
  document.getElementById('rf_prizeImageUrl').value = '';
  document.getElementById('rf_prizeImagePreview').innerHTML = emoji;
  const fi = document.getElementById('rf_prizeImageFile');
  if (fi) fi.value = '';
}

async function saveRaffle() {
  const title = document.getElementById('rf_title').value.trim();
  if (!title) { showToast('El título es obligatorio', 'error'); return; }
  const prizeName = document.getElementById('rf_prizeName').value.trim();
  if (!prizeName) { showToast('Debes ingresar el premio del sorteo', 'error'); return; }
  const prizeEmoji = document.getElementById('rf_prizeEmoji').value.trim() || '🏆';
  const prizeImageUrl = document.getElementById('rf_prizeImageUrl').value.trim();

  const rawTotalTickets = document.getElementById('rf_totalTickets').value;
  const parsedTotalTickets = Number(rawTotalTickets);
  const totalTickets = rawTotalTickets === '' || !Number.isFinite(parsedTotalTickets) || parsedTotalTickets <= 0
    ? null
    : Math.round(parsedTotalTickets);

  const data = {
    id:            _editingRaffleId,
    title,
    description:   document.getElementById('rf_description').value.trim(),
    category:      document.getElementById('rf_category').value,
    status:        document.getElementById('rf_status').value,
    total_tickets: totalTickets,
    draw_date:     document.getElementById('rf_drawDate').value,
    featured:      document.getElementById('rf_featured').checked,
    image_url:     document.getElementById('rf_imageUrl').value,
    legal_text:    document.getElementById('rf_legalText').value.trim(),
    meet_link:     (document.getElementById('rf_meetLink')?.value || '').trim() || null,
    prizes: [{
      place: 1,
      label: prizeName,
      name: prizeName,
      emoji: prizeEmoji,
      image_url: prizeImageUrl,
    }],
    packs:  _packs,
  };

  try {
    await api('/raffles.php', { method: _editingRaffleId ? 'PUT' : 'POST', body: data });
    showToast(_editingRaffleId ? 'Sorteo actualizado ✅' : 'Sorteo creado ✅');
    closeRaffleModal();
    await renderSorteos();
    await renderDashboard();
  } catch (e) { showToast(e.message, 'error'); }
}

function switchRaffleTab(tab) {
  ['info','packs','legal'].forEach(t => {
    document.getElementById(`rtab-${t}`)?.classList.toggle('btn-primary', t === tab);
    document.getElementById(`rtab-${t}`)?.classList.toggle('btn-ghost',   t !== tab);
    const panel = document.getElementById(`rtab-${t}-panel`);
    if (panel) panel.classList.toggle('hidden', t !== tab);
  });
}

/* Packs */
function renderPackRows() {
  document.getElementById('packsContainer').innerHTML = _packs.map((p, i) => `
    <div class="form-row" style="align-items:flex-end; gap:.5rem;">
      <div class="form-group" style="flex:2;"><label class="form-label">Etiqueta</label>
        <input type="text" class="form-control" value="${p.label || ''}" placeholder="Pack 3 tickets" oninput="_packs[${i}].label=this.value"></div>
      <div class="form-group" style="flex:1;"><label class="form-label">Cantidad</label>
        <input type="number" class="form-control" value="${p.qty || p.quantity || 1}" min="1" onchange="_packs[${i}].qty=parseInt(this.value)"></div>
      <div class="form-group" style="flex:1;"><label class="form-label">Precio (CLP)</label>
        <input type="number" class="form-control" value="${p.price || 0}" min="0" onchange="_packs[${i}].price=parseInt(this.value)"></div>
      <div class="form-group" style="flex:1;"><label class="form-label">Precio original</label>
        <input type="number" class="form-control" value="${p.original_price || p.originalPrice || 0}" min="0" onchange="_packs[${i}].original_price=parseInt(this.value)"></div>
      <button class="btn btn-ghost btn-sm" style="color:#ef4444; margin-bottom:.25rem;" onclick="_packs.splice(${i},1); renderPackRows()">✕</button>
    </div>`).join('') || '<p style="opacity:.5; font-size:.85rem; padding:.5rem 0;">Sin packs. Agrega uno abajo.</p>';
}

function addPackRow() {
  _packs.push({ label: '', quantity: 1, price: 0 });
  renderPackRows();
}

/* ══════════════════════════════════════════════════════════════════════════ */
/*  TICKETS                                                                  */
/* ══════════════════════════════════════════════════════════════════════════ */
let _allTickets = [];

async function renderTickets() {
  const [tickets, raffles] = await Promise.all([
    api('/tickets.php'),
    api('/raffles.php'),
  ]);
  _allTickets = tickets;

  // Populate filter dropdown
  const filter = document.getElementById('ticketsFilter');
  filter.innerHTML = '<option value="">Todos los sorteos</option>' +
    raffles.map(r => `<option value="${r.id}">${r.title}</option>`).join('');
  filter.addEventListener('change', filterTickets);

  renderTicketRows(tickets);
}

function filterTickets() {
  const raffleId = document.getElementById('ticketsFilter').value;
  const search   = document.getElementById('ticketsSearch').value.toLowerCase();
  let rows = _allTickets;
  if (raffleId) rows = rows.filter(t => t.raffle_id === raffleId);
  if (search)   rows = rows.filter(t =>
    (t.buyer_name  || '').toLowerCase().includes(search) ||
    (t.buyer_email || '').toLowerCase().includes(search)
  );
  renderTicketRows(rows);
}

function renderTicketRows(list) {
  document.getElementById('ticketsCount').textContent = `${list.length} registros`;
  document.getElementById('ticketsTable').innerHTML = list.map(t => {
    const nums = Array.isArray(t.ticket_numbers) ? t.ticket_numbers : [];
    return `<tr>
      <td style="font-family:monospace; font-size:.8rem;">${nums.join(', ') || '—'}</td>
      <td><div>${t.buyer_name}</div><small style="opacity:.6;">${t.buyer_email}</small></td>
      <td>${t.raffle_title || '—'}</td>
      <td>${t.pack_label || '—'}</td>
      <td>${fmtCLP(t.amount)}</td>
      <td>${fmtDate(t.created_at)}</td>
      <td>${paymentBadge(t.payment_status)}</td>
      <td><button class="btn btn-ghost btn-sm" title="Generar PDF de tickets" onclick="generateTicketsPDF('${t.id}')">🖨️ PDF</button></td>
    </tr>`;
  }).join('') || '<tr><td colspan="8" style="opacity:.5; text-align:center;">Sin tickets</td></tr>';
}

/* ══════════════════════════════════════════════════════════════════════════ */
/*  GANADORES                                                                */
/* ══════════════════════════════════════════════════════════════════════════ */
async function renderGanadores() {
  const winners = await api('/winners.php');
  document.getElementById('ganadoresTable').innerHTML = winners.map(w => `
    <tr>
      <td>${w.winner_name}</td>
      <td>${w.raffle_title || w.raffle_id}</td>
      <td>${w.prize}</td>
      <td style="font-family:monospace;">${w.ticket_number}</td>
      <td>${fmtDate(w.draw_date)}</td>
      <td>
        <button class="btn btn-ghost btn-sm" style="color:#ef4444" onclick="deleteWinner('${w.id}','${w.winner_name.replace(/'/g,"\\'")}')">🗑️</button>
      </td>
    </tr>`).join('') || '<tr><td colspan="6" style="opacity:.5; text-align:center;">Sin ganadores</td></tr>';
}

async function deleteWinner(id, name) {
  if (!confirm(`¿Eliminar al ganador "${name}"?`)) return;
  try {
    await api('/winners.php', { method: 'DELETE', params: { id } });
    showToast('Ganador eliminado');
    await renderGanadores();
  } catch (e) { showToast(e.message, 'error'); }
}

/* Winner modal */
function openWinnerModal() {
  ['wn_name','wn_email','wn_prize','wn_ticketNumber','wn_wonDate','wn_videoUrl'].forEach(id => {
    const el = document.getElementById(id); if (el) el.value = '';
  });
  const wnImg = document.getElementById('wn_imageUrl');
  if (wnImg) wnImg.value = '';
  const wnPrev = document.getElementById('wn_imagePreview');
  if (wnPrev) wnPrev.innerHTML = '👤';
  const wnFile = document.getElementById('wn_imageFile');
  if (wnFile) wnFile.value = '';
  document.getElementById('wn_raffleId').value = '';
  populateWinnerRaffleSelect();
  document.getElementById('winnerModal').classList.add('open');
}
function closeWinnerModal() {
  document.getElementById('winnerModal').classList.remove('open');
}

async function populateWinnerRaffleSelect() {
  try {
    const raffles = await api('/raffles.php');
    const sel = document.getElementById('wn_raffleId');
    sel.innerHTML = '<option value="">— selecciona un sorteo —</option>' +
      raffles.map(r => `<option value="${r.id}">${r.title}</option>`).join('');
  } catch (_) {}
}

async function saveWinner() {
  const name    = document.getElementById('wn_name').value.trim();
  const raffleId = document.getElementById('wn_raffleId').value;
  const prize   = document.getElementById('wn_prize').value.trim();
  const ticket  = document.getElementById('wn_ticketNumber').value.trim();
  const wonDate = document.getElementById('wn_wonDate').value;
  const videoUrl = document.getElementById('wn_videoUrl').value.trim();
  const winnerImageUrl = document.getElementById('wn_imageUrl').value.trim();

  if (!name || !raffleId || !prize || !ticket || !wonDate) {
    showToast('Completa todos los campos obligatorios', 'error');
    return;
  }
  if (videoUrl) {
    try {
      const u = new URL(videoUrl);
      const host = u.hostname.replace(/^www\./, '').toLowerCase();
      const okHost = host === 'youtube.com' || host === 'm.youtube.com' || host === 'youtu.be';
      if (!okHost) {
        showToast('El video debe ser un enlace de YouTube válido', 'error');
        return;
      }
    } catch (_) {
      showToast('El video debe ser una URL válida de YouTube', 'error');
      return;
    }
  }

  try {
    await api('/winners.php', {
      method: 'POST',
      body: {
        raffle_id:     raffleId,
        winner_name:   name,
        prize,
        ticket_number: ticket,
        draw_date:     wonDate,
        video_url:     videoUrl || null,
        winner_image_url: winnerImageUrl || null,
      },
    });
    showToast('Ganador guardado ✅');
    closeWinnerModal();
    await renderGanadores();
  } catch (e) { showToast(e.message, 'error'); }
}

async function handleWinnerImageUpload(input) {
  if (!input.files[0]) return;
  const fd = new FormData();
  fd.append('file', input.files[0]);
  fd.append('type', 'winner');
  try {
    const resp = await fetch(`${API_BASE}/upload.php`, { method: 'POST', credentials: 'include', body: fd });
    const json = await resp.json();
    if (!json.ok) throw new Error(json.error);
    const url = json.data.url;
    document.getElementById('wn_imageUrl').value = url;
    const preview = document.getElementById('wn_imagePreview');
    if (preview) preview.innerHTML = `<img src="${url}" style="width:100%;height:100%;object-fit:cover;">`;
    showToast('Imagen del ganador cargada ✅');
  } catch (e) { showToast(e.message, 'error'); }
}

function clearWinnerImage() {
  const input = document.getElementById('wn_imageUrl');
  if (input) input.value = '';
  const preview = document.getElementById('wn_imagePreview');
  if (preview) preview.innerHTML = '👤';
  const file = document.getElementById('wn_imageFile');
  if (file) file.value = '';
}

/* ══════════════════════════════════════════════════════════════════════════ */
/*  DISEÑO (logo, tema, slider)                                                */
/* ══════════════════════════════════════════════════════════════════════════ */
const DI_PRESETS = [
  { name: 'Púrpura',     desc: 'Default',   primary: '#7c3aed', accent: '#f59e0b' },
  { name: 'Azul',        desc: 'Marino',    primary: '#1d4ed8', accent: '#f97316' },
  { name: 'Esmeralda',   desc: 'Verde',     primary: '#059669', accent: '#eab308' },
  { name: 'Fucsia',      desc: 'Rosa',      primary: '#db2777', accent: '#06b6d4' },
  { name: 'Naranja',     desc: 'Vibrante',  primary: '#ea580c', accent: '#8b5cf6' },
  { name: 'Teal',        desc: '& Coral',   primary: '#0d9488', accent: '#f43f5e' },
  { name: 'Índigo',      desc: 'Profundo',  primary: '#4338ca', accent: '#fb923c' },
  { name: 'Rojo',        desc: 'Intenso',   primary: '#dc2626', accent: '#22d3ee' },
];

let _slides = []; // in-memory slides array

async function renderDiseno() {
  const s = await api('/settings.php');

  // ── Logo ──────────────────────────────────────────────────────────────────
  const preview = document.getElementById('logoPreview');
  if (preview && s.site_logo) {
    preview.innerHTML = `<img src="${s.site_logo}" alt="Logo" style="width:100%;height:100%;object-fit:contain;border-radius:inherit;">`;
  }
  const logoInput = document.getElementById('logoFileInput');
  if (logoInput) {
    logoInput.onchange = async function () {
      if (!this.files[0]) return;
      const fd = new FormData();
      fd.append('file', this.files[0]);
      fd.append('type', 'logo');
      try {
        const resp = await fetch(`${API_BASE}/upload.php`, { method: 'POST', credentials: 'include', body: fd });
        const json = await resp.json();
        if (!json.ok) throw new Error(json.error);
        preview.innerHTML = `<img src="${json.data.url}" alt="Logo" style="width:100%;height:100%;object-fit:contain;border-radius:inherit;">`;
        showToast('Logo actualizado ✅');
      } catch (e) { showToast(e.message, 'error'); }
    };
  }
  const btnDelLogo = document.getElementById('deleteLogo');
  if (btnDelLogo) btnDelLogo.onclick = async () => {
    try {
      await api('/settings.php', { method: 'POST', body: { site_logo: '' } });
      if (preview) preview.innerHTML = '🎟️';
      showToast('Logo eliminado');
    } catch (e) { showToast(e.message, 'error'); }
  };

  // ── Tema ──────────────────────────────────────────────────────────────────
  const curPrimary = s.theme_primary || '#7c3aed';
  const curAccent  = s.theme_accent  || '#f59e0b';
  document.getElementById('diColorPrimary').value    = curPrimary;
  document.getElementById('diColorPrimaryHex').value = curPrimary;
  document.getElementById('diColorAccent').value     = curAccent;
  document.getElementById('diColorAccentHex').value  = curAccent;
  applyColors(curPrimary, curAccent);

  const activePrimary = curPrimary.toLowerCase();
  document.getElementById('diPresets').innerHTML = `
  <div class="di-presets-grid">
    ${DI_PRESETS.map(p => `
    <button class="di-preset-card${p.primary === activePrimary ? ' active' : ''}"
      onclick="diApplyPreset('${p.primary}','${p.accent}')" title="${p.name} ${p.desc}">
      <div class="di-preset-swatch">
        <div style="flex:1;background:${p.primary};"></div>
        <div style="flex:1;background:${p.accent};"></div>
      </div>
      <div class="di-preset-label">${p.name}<span>${p.desc}</span></div>
    </button>`).join('')}
  </div>`;

  ['Primary','Accent'].forEach(cap => {
    const col = document.getElementById(`diColor${cap}`);
    const hex = document.getElementById(`diColor${cap}Hex`);
    col?.addEventListener('input', () => { hex.value = col.value; applyColors(
      document.getElementById('diColorPrimary').value,
      document.getElementById('diColorAccent').value
    ); });
    hex?.addEventListener('input', () => { if (/^#[0-9a-f]{6}$/i.test(hex.value)) { col.value = hex.value; applyColors(
      document.getElementById('diColorPrimary').value,
      document.getElementById('diColorAccent').value
    ); } });
  });

  const btnApply = document.getElementById('diApplyTheme');
  const btnReset  = document.getElementById('diResetTheme');

  if (btnApply) btnApply.onclick = async () => {
    const p = document.getElementById('diColorPrimary').value;
    const a = document.getElementById('diColorAccent').value;
    btnApply.disabled = true;
    btnApply.textContent = 'Guardando…';
    try {
      await saveThemeToDb(p, a);
      showToast('Tema guardado y aplicado ✅');
    } catch (e) {
      showToast('Error al guardar: ' + e.message, 'error');
    } finally {
      btnApply.disabled = false;
      btnApply.textContent = '✅ Guardar tema';
    }
  };

  if (btnReset) btnReset.onclick = async () => {
    btnReset.disabled = true;
    try {
      await saveThemeToDb('#7c3aed', '#f59e0b');
      showToast('Tema restaurado');
    } catch (e) {
      showToast('Error al restaurar: ' + e.message, 'error');
    } finally {
      btnReset.disabled = false;
    }
  };

  // ── Slider ────────────────────────────────────────────────────────────────
  const chk = document.getElementById('sliderEnabled');
  if (chk) {
    chk.checked = s.hero_slider_enabled === '1';
    chk.addEventListener('change', async () => {
      await api('/settings.php', { method: 'POST', body: { hero_slider_enabled: chk.checked ? '1' : '0' } });
      showToast(chk.checked ? 'Slider activado' : 'Slider desactivado');
    });
  }

  try {
    _slides = JSON.parse(s.hero_slides || '[]');
  } catch (_) {
    _slides = [];
  }
  if (!Array.isArray(_slides)) _slides = [];
  renderSlidesList();

  // Slide modal listeners
  document.querySelectorAll('input[name="sl_bgType"]').forEach(r => {
    r.addEventListener('change', () => {
      document.getElementById('sl_gradientFields').classList.toggle('hidden', r.value === 'image');
      document.getElementById('sl_imageFields').classList.toggle('hidden', r.value !== 'image');
    });
  });
  ['sl_color1','sl_color2'].forEach(id => {
    const col = document.getElementById(id);
    const hex = document.getElementById(id + 'Hex');
    col?.addEventListener('input', () => { if(hex) hex.value = col.value; });
    hex?.addEventListener('input', () => { if(/^#[0-9a-f]{6}$/i.test(hex.value)) col.value = hex.value; });
  });
}

function diApplyPreset(primary, accent) {
  applyColors(primary, accent);
  // update active state on cards
  document.querySelectorAll('.di-preset-card').forEach(c => {
    c.classList.toggle('active', c.getAttribute('onclick').includes(primary));
  });
}

async function saveThemeToDb(primary, accent) {
  await api('/settings.php', { method: 'POST', body: {
    theme_primary:       primary,
    theme_primary_light: lightenHex(primary, 0.22),
    theme_primary_dark:  darkenHex(primary, 0.22),
    theme_accent:        accent,
    theme_accent_light:  lightenHex(accent, 0.2),
    theme_accent_dark:   darkenHex(accent, 0.2),
  }});
  applyColors(primary, accent);
}

function renderSlidesList() {
  const list = document.getElementById('slidesList');
  if (!list) return;
  if (!_slides.length) {
    list.innerHTML = '<p class="text-sm" style="color:var(--text-secondary);">No hay diapositivas. Agrega la primera.</p>';
    return;
  }
  list.innerHTML = _slides.map((s, i) => {
    const bg = s.bgImage ? `url('${s.bgImage}') center/cover` :
               `linear-gradient(135deg,${s.bgColor1||'#1a0a2e'},${s.bgColor2||'#0d0520'})`;
    return `
      <div class="slide-preview-card">
        <div class="slide-preview-thumb" style="background:${bg};"></div>
        <div class="slide-preview-title">${s.title || 'Imagen sin texto'}</div>
        <span class="pill ${s.active !== false ? 'pill-green' : 'pill-gray'}" style="font-size:.7rem;">${s.active !== false ? 'Activa' : 'Inactiva'}</span>
        <div class="slide-preview-actions">
          <button class="btn btn-ghost btn-sm" onclick="openSlideModal(${i})">Editar</button>
          <button class="btn btn-ghost btn-sm" style="color:#f87171;" onclick="deleteSlide(${i})">Eliminar</button>
        </div>
      </div>`;
  }).join('');
}

function openSlideModal(idx) {
  const s = idx !== undefined ? _slides[idx] : null;
  document.getElementById('sl_id').value      = idx !== undefined ? idx : '';
  document.getElementById('sl_title').value   = s?.title    || '';
  document.getElementById('sl_subtitle').value= s?.subtitle || '';
  document.getElementById('sl_badge').value   = s?.badge    || '';
  document.getElementById('sl_ctaText').value = s?.ctaText  || '';
  document.getElementById('sl_ctaLink').value = s?.ctaLink  || 'sorteos.php';
  document.getElementById('sl_active').checked= s?.active !== false;
  const bgType = 'image';
  document.querySelector(`input[name="sl_bgType"][value="${bgType}"]`).checked = true;
  document.getElementById('sl_gradientFields').classList.toggle('hidden', bgType === 'image');
  document.getElementById('sl_imageFields').classList.toggle('hidden', bgType !== 'image');
  document.getElementById('sl_color1').value    = s?.bgColor1 || '#1a0a2e';
  document.getElementById('sl_color1Hex').value = s?.bgColor1 || '#1a0a2e';
  document.getElementById('sl_color2').value    = s?.bgColor2 || '#0d0520';
  document.getElementById('sl_color2Hex').value = s?.bgColor2 || '#0d0520';
  document.getElementById('sl_bgImage').value   = s?.bgImage  || '';
  updateSlideImagePreview(s?.bgImage || '');
  const fileInput = document.getElementById('sl_imageFile');
  if (fileInput) fileInput.value = '';
  document.getElementById('slideModalTitle').textContent = s ? 'Editar diapositiva' : 'Nueva diapositiva';
  document.getElementById('slideModal').classList.add('open');
}

function closeSlideModal() {
  document.getElementById('slideModal').classList.remove('open');
}

async function saveSlide() {
  const idxStr = document.getElementById('sl_id').value;
  const idx    = idxStr !== '' ? parseInt(idxStr) : -1;
  const bgType = 'image';
  const slide  = {
    id:       idx >= 0 ? _slides[idx].id : 'sl_' + Date.now().toString(36),
    title:    document.getElementById('sl_title').value.trim(),
    subtitle: document.getElementById('sl_subtitle').value.trim(),
    badge:    document.getElementById('sl_badge').value.trim(),
    ctaText:  document.getElementById('sl_ctaText').value.trim(),
    ctaLink:  document.getElementById('sl_ctaLink').value.trim(),
    bgType,
    bgColor1: document.getElementById('sl_color1').value,
    bgColor2: document.getElementById('sl_color2').value,
    bgImage:  document.getElementById('sl_bgImage').value.trim(),
    active:   document.getElementById('sl_active').checked,
  };
  if (!slide.bgImage) { showToast('Sube una imagen para el slide', 'error'); return; }
  if (idx < 0 && _slides.length >= 6) { showToast('Puedes crear hasta 6 diapositivas', 'error'); return; }
  if (idx >= 0) _slides[idx] = slide; else _slides.push(slide);
  await api('/settings.php', { method: 'POST', body: { hero_slides: JSON.stringify(_slides) } });
  closeSlideModal();
  renderSlidesList();
  showToast('Diapositiva guardada ✅');
}

async function deleteSlide(idx) {
  if (!confirm('¿Eliminar esta diapositiva?')) return;
  _slides.splice(idx, 1);
  await api('/settings.php', { method: 'POST', body: { hero_slides: JSON.stringify(_slides) } });
  renderSlidesList();
  showToast('Diapositiva eliminada');
}

/* ══════════════════════════════════════════════════════════════════════════ */
/*  SETTINGS                                                                 */
/* ══════════════════════════════════════════════════════════════════════════ */
function updateSlideImagePreview(url) {
  const preview = document.getElementById('sl_imagePreview');
  if (!preview) return;
  preview.innerHTML = url
    ? `<img src="${url}" alt="Slide" style="width:100%;height:100%;object-fit:cover;">`
    : 'Sin imagen';
}

async function handleSlideImageUpload(input) {
  if (!input.files[0]) return;
  const fd = new FormData();
  fd.append('file', input.files[0]);
  fd.append('type', 'slide');
  try {
    const resp = await fetch(`${API_BASE}/upload.php`, { method: 'POST', credentials: 'include', body: fd });
    const json = await resp.json();
    if (!json.ok) throw new Error(json.error);
    document.getElementById('sl_bgImage').value = json.data.url;
    const imageRadio = document.querySelector('input[name="sl_bgType"][value="image"]');
    if (imageRadio) imageRadio.checked = true;
    document.getElementById('sl_gradientFields')?.classList.add('hidden');
    document.getElementById('sl_imageFields')?.classList.remove('hidden');
    updateSlideImagePreview(json.data.url);
    showToast('Imagen del slide cargada');
  } catch (e) {
    showToast(e.message, 'error');
  }
}

function clearSlideImage() {
  document.getElementById('sl_bgImage').value = '';
  const fileInput = document.getElementById('sl_imageFile');
  if (fileInput) fileInput.value = '';
  updateSlideImagePreview('');
}

function setupDevelopmentModeSwitch(settings = {}) {
  const sw = document.getElementById('developmentModeSwitch');
  if (!sw || sw.dataset.ready === '1') return;
  const syncSwitchUi = () => {
    const track = document.getElementById('developmentModeTrack');
    const knob = document.getElementById('developmentModeKnob');
    if (track) track.style.background = sw.checked ? 'var(--color-accent)' : 'rgba(255,255,255,.18)';
    if (knob) knob.style.transform = sw.checked ? 'translateX(16px)' : 'translateX(0)';
  };
  sw.dataset.ready = '1';
  sw.checked = settings.development_mode_enabled === '1' || settings.development_mode_enabled === 1;
  syncSwitchUi();
  sw.addEventListener('change', async () => {
    syncSwitchUi();
    sw.disabled = true;
    try {
      await api('/settings.php', {
        method: 'POST',
        body: { development_mode_enabled: sw.checked ? '1' : '0' },
      });
      showToast(sw.checked ? 'Modo Próximamente activado' : 'Modo Próximamente desactivado');
    } catch (e) {
      sw.checked = !sw.checked;
      syncSwitchUi();
      showToast(e.message, 'error');
    } finally {
      sw.disabled = false;
    }
  });
}

async function renderSettings() {
  try {
    const s = await api('/settings.php');
    const form = document.getElementById('settingsForm');
    if (!form) return;
    Object.entries(s).forEach(([k, v]) => {
      const el = form.querySelector(`[name="${k}"]`);
      if (el) el.value = v;
    });
  } catch (e) { showToast(e.message, 'error'); }
}

async function saveSettingsForm(e) {
  e.preventDefault();
  const data = {};
  new FormData(e.target).forEach((v, k) => { data[k] = v; });
  if (data.site_url) data.site_url = normalizeSiteUrlForFlow(data.site_url);
  try {
    await api('/settings.php', { method: 'POST', body: data });
    showToast('Configuración guardada ✅');
  } catch (err) { showToast(err.message, 'error'); }
}

async function testSmtpEmail() {
  const form = document.getElementById('smtpForm');
  const emailEl = document.getElementById('smtpTestEmail');
  const btn = document.getElementById('smtpTestBtn');
  const testEmail = emailEl?.value?.trim() || '';
  if (!form) return;
  if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(testEmail)) {
    showToast('Ingresa un correo de prueba valido', 'warning');
    emailEl?.focus();
    return;
  }

  const data = { test_email: testEmail };
  new FormData(form).forEach((v, k) => { data[k] = v; });

  const prevText = btn?.textContent;
  if (btn) {
    btn.disabled = true;
    btn.textContent = 'Enviando...';
  }
  try {
    await api('/smtp_test.php', { method: 'POST', body: data });
    showToast('Correo de prueba enviado. Revisa la bandeja de entrada.');
  } catch (err) {
    showToast(err.message, 'error');
  } finally {
    if (btn) {
      btn.disabled = false;
      btn.textContent = prevText || 'Probar SMTP';
    }
  }
}

/* ══════════════════════════════════════════════════════════════════════════ */
/*  SMTP                                                                     */
/* ══════════════════════════════════════════════════════════════════════════ */
async function renderSmtp() {
  try {
    const s    = await api('/settings.php');
    const form = document.getElementById('smtpForm');
    if (!form) return;
    ['smtp_host','smtp_port','smtp_user','smtp_pass','smtp_from_name','smtp_from_email','smtp_encryption'].forEach(k => {
      const el = form.querySelector(`[name="${k}"]`);
      if (el && s[k] !== undefined) el.value = s[k];
    });
  } catch (e) { showToast(e.message, 'error'); }
}

async function saveSmtpForm(e) {
  e.preventDefault();
  const data = {};
  new FormData(e.target).forEach((v, k) => { data[k] = v; });
  if (data.site_url) data.site_url = normalizeSiteUrlForFlow(data.site_url);
  try {
    await api('/settings.php', { method: 'POST', body: data });
    showToast('Configuración SMTP guardada ✅');
  } catch (err) { showToast(err.message, 'error'); }
}

/* ══════════════════════════════════════════════════════════════════════════ */
/*  FLOW.CL                                                                  */
/* ══════════════════════════════════════════════════════════════════════════ */
async function renderFlow() {
  try {
    const s    = await api('/settings.php');
    const form = document.getElementById('flowForm');
    if (!form) return;
    ['flow_environment','flow_api_key','flow_secret_key','site_url'].forEach(k => {
      const el = form.querySelector(`[name="${k}"]`);
      if (el && s[k] !== undefined) el.value = s[k];
    });
    updateCallbackPreview(s.site_url || '');

    form.querySelector('[name="site_url"]')?.addEventListener('input', function () {
      updateCallbackPreview(this.value);
    });
  } catch (e) { showToast(e.message, 'error'); }
}

function updateCallbackPreview(siteUrl) {
  const preview = document.getElementById('flowCallbackPreview');
  if (!preview) return;
  const cleanUrl = normalizeSiteUrlForFlow(siteUrl);
  const url = cleanUrl ? cleanUrl + '/api/flow_callback.php' : '�';
  preview.textContent = `Callback URL: ${url}`;
}

function normalizeSiteUrlForFlow(siteUrl) {
  return String(siteUrl || '')
    .trim()
    .replace(/\/(api\/flow_callback\.php|pago-exitoso\.php)(\/.*)?$/i, '')
    .replace(/\/api\/?$/i, '')
    .replace(/\/$/, '');
}

async function saveFlowForm(e) {
  e.preventDefault();
  const data = {};
  new FormData(e.target).forEach((v, k) => { data[k] = v; });
  if (data.site_url) data.site_url = normalizeSiteUrlForFlow(data.site_url);
  try {
    await api('/settings.php', { method: 'POST', body: data });
    showToast('Configuración Flow.cl guardada ✅');
  } catch (err) { showToast(err.message, 'error'); }
}

/* ══════════════════════════════════════════════════════════════════════════ */
/*  INIT                                                                     */
/* ══════════════════════════════════════════════════════════════════════════ */
(async function init() {
  // Load theme from DB on admin panel
  try {
    const s = await api('/settings.php');
    if (s.theme_primary) applyColors(s.theme_primary, s.theme_accent || '#f59e0b');
    setupDevelopmentModeSwitch(s);
  } catch(e) {
    const saved = JSON.parse(localStorage.getItem('surteados_theme') || '{}');
    if (saved.primary) applyColors(saved.primary, saved.accent);
    setupDevelopmentModeSwitch();
  }

  // Mobile sidebar toggle
  document.getElementById('mobileToggle')?.addEventListener('click', () => {
    document.getElementById('mobileNav').classList.toggle('active');
  });

  await renderSection('dashboard');
})();

/* ══════════════════════════════════════════════════════════════════════════ */
/*  TICKET FORMAT PREVIEW                                                        */
/* ══════════════════════════════════════════════════════════════════════════ */
let _tpSettings = {};
let _tpRaffles  = [];

async function renderTicketFormat() {
  try {
    const [s, raffles] = await Promise.all([
      api('/settings.php'),
      api('/raffles.php'),
    ]);
    _tpSettings = s;
    _tpRaffles  = raffles;

    const sel = document.getElementById('tpRaffleSelect');
    sel.innerHTML = raffles.map(r =>
      `<option value="${r.id}">${r.title}</option>`
    ).join('') || '<option value="">Sin sorteos aún</option>';

    updateTicketPreview();
  } catch(e) { showToast(e.message, 'error'); }
}

function updateTicketPreview() {
  const raffleId = document.getElementById('tpRaffleSelect')?.value;
  const raffle   = _tpRaffles.find(r => r.id === raffleId) || _tpRaffles[0];
  const area     = document.getElementById('tpPreviewArea');
  if (!raffle || !area) return;

  const s        = _tpSettings;
  const siteUrl  = (s.site_url  || 'http://localhost/surteados').replace(/\/$/, '');
  const siteName = s.site_name  || 'Surteados';

  // Sample ticket data
  const ticket = {
    id:          'T-PREV2026',
    number:      '003.421',
    buyerName:   'Juan Pérez Rodríguez',
    buyerEmail:  'juan.perez@correo.cl',
    buyerPhone:  '+56 9 8765 4321',
    packLabel:   (raffle.packs?.[2]?.label)  || (raffle.packs?.[0]?.label)  || '5 Tickets',
    amount:      (raffle.packs?.[2]?.price)  || (raffle.packs?.[0]?.price)  || 11000,
    purchaseDate:'15 de abril, 2026',
  };

  const verifyUrl  = `${siteUrl}/verificar/${ticket.number.replace(/\./g,'')}`;
  const prize1     = raffle.prizes?.[0]?.name || raffle.prizes?.[0]?.label || 'Premio principal';
  const prize1img  = raffle.prizes?.[0]?.image_url || '';
  const drawDate   = raffle.draw_date
    ? new Date(raffle.draw_date).toLocaleDateString('es-CL', { day:'2-digit', month:'long', year:'numeric' })
    : 'Por definir';

  const logoHtml = s.site_logo
    ? `<img src="${s.site_logo}" style="height:30px;max-width:130px;object-fit:contain;">`
    : `<span style="font-weight:900;font-size:1.05rem;letter-spacing:-.5px;color:#fff;">${siteName}</span>`;

  const prize1imgHtml = prize1img
    ? `<img src="${prize1img}" style="width:42px;height:42px;object-fit:cover;border-radius:7px;flex-shrink:0;">`
    : '';

  const perfs = Array(9).fill(
    `<div style="width:11px;height:11px;background:var(--bg-card,#0f0a1a);border-radius:50%;margin:5px 0;"></div>`
  ).join('');

  area.innerHTML = `
  <div id="ticketCard" style="
    max-width:760px;
    background:linear-gradient(135deg,#1a0a2e 0%,#2d1158 55%,#1a0a2e 100%);
    border-radius:16px;
    overflow:hidden;
    box-shadow:0 24px 64px rgba(0,0,0,.55);
    font-family:'Inter',sans-serif;
    color:#fff;
    position:relative;
  ">
    <div style="height:4px;background:linear-gradient(90deg,var(--color-primary,#7c3aed),var(--color-accent,#f59e0b),var(--color-primary,#7c3aed));"></div>
    <!-- Header -->
    <div style="padding:.9rem 1.5rem;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid rgba(255,255,255,.07);">
      <div>${logoHtml}</div>
      <div style="text-align:right;">
        <div style="font-size:.56rem;letter-spacing:2.5px;color:rgba(255,255,255,.38);text-transform:uppercase;">🎫 Ticket Oficial</div>
        <div style="font-size:.72rem;color:rgba(255,255,255,.55);">${siteName}</div>
        <div style="font-size:.58rem;color:rgba(255,255,255,.32);margin-top:.2rem;letter-spacing:.5px;">ID Venta: <strong style="color:rgba(255,255,255,.62);">${ticket.id}</strong></div>
      </div>
    </div>
    <!-- Body -->
    <div style="display:flex;">
      <!-- Left: main info -->
      <div style="flex:1;padding:1.35rem 1.5rem;">
        <div style="margin-bottom:1.1rem;">
          <div style="font-size:.54rem;letter-spacing:2.5px;color:rgba(255,255,255,.33);text-transform:uppercase;margin-bottom:.15rem;">N° de Ticket</div>
          <div style="font-size:2.4rem;font-weight:900;letter-spacing:-1.5px;line-height:1;color:#fff;text-shadow:0 0 30px rgba(124,58,237,.6);">#${ticket.number}</div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.5rem 1.25rem;margin-bottom:.85rem;">
          <div>
            <div style="font-size:.54rem;letter-spacing:2px;color:rgba(255,255,255,.33);text-transform:uppercase;margin-bottom:.12rem;">Sorteo</div>
            <div style="font-size:.82rem;font-weight:700;line-height:1.3;">${raffle.title}</div>
          </div>
          <div>
            <div style="font-size:.54rem;letter-spacing:2px;color:rgba(255,255,255,.33);text-transform:uppercase;margin-bottom:.12rem;">Fecha del sorteo</div>
            <div style="font-size:.78rem;">${drawDate}</div>
          </div>
          <div>
            <div style="font-size:.54rem;letter-spacing:2px;color:rgba(255,255,255,.33);text-transform:uppercase;margin-bottom:.12rem;">Premios</div>
            <div style="font-size:.78rem;color:#fbbf24;font-weight:600;">${prize1}</div>
          </div>
          <div>
            <div style="font-size:.54rem;letter-spacing:2px;color:rgba(255,255,255,.33);text-transform:uppercase;margin-bottom:.12rem;">Categoría</div>
            <div style="font-size:.78rem;">${raffle.category || '—'}</div>
          </div>
        </div>
        <div style="display:flex;gap:1rem;background:rgba(255,255,255,.05);border-radius:10px;padding:.6rem 1rem;margin-bottom:.85rem;border:1px solid rgba(255,255,255,.08);">
          <div>
            <div style="font-size:.54rem;letter-spacing:2px;color:rgba(255,255,255,.33);text-transform:uppercase;margin-bottom:.1rem;">Pack</div>
            <div style="font-size:.82rem;color:#fbbf24;font-weight:600;">${ticket.packLabel}</div>
          </div>
          <div style="width:1px;background:rgba(255,255,255,.1);"></div>
          <div>
            <div style="font-size:.54rem;letter-spacing:2px;color:rgba(255,255,255,.33);text-transform:uppercase;margin-bottom:.1rem;">Valor pagado</div>
            <div style="font-size:.92rem;font-weight:800;color:#fbbf24;">$${Number(ticket.amount).toLocaleString('es-CL')} CLP</div>
          </div>
        </div>
        <div>
          <div style="font-size:.54rem;letter-spacing:2px;color:rgba(255,255,255,.33);text-transform:uppercase;margin-bottom:.35rem;">Participante</div>
          <div style="font-size:.86rem;font-weight:600;margin-bottom:.12rem;">${ticket.buyerName}</div>
          <div style="font-size:.75rem;color:rgba(255,255,255,.48);">${ticket.buyerEmail} &nbsp;&middot;&nbsp; ${ticket.buyerPhone}</div>
          <div style="font-size:.7rem;color:rgba(255,255,255,.32);margin-top:.15rem;">Fecha de compra: ${ticket.purchaseDate}</div>
        </div>
      </div>
      <!-- Stub right -->
      <div style="width:190px;flex-shrink:0;border-left:2px dashed rgba(255,255,255,.13);padding:1.25rem 1rem;display:flex;flex-direction:column;align-items:center;gap:.6rem;background:rgba(0,0,0,.18);position:relative;">
        <div style="position:absolute;top:0;left:-7px;display:flex;flex-direction:column;">${perfs}</div>
        <!-- Prize image -->
        <div style="font-size:.53rem;letter-spacing:2px;color:rgba(255,255,255,.33);text-transform:uppercase;align-self:flex-start;">Premio principal</div>
        ${prize1img
          ? `<div style="width:148px;height:148px;border-radius:12px;overflow:hidden;border:2px solid rgba(255,255,255,.12);flex-shrink:0;"><img src="${prize1img}" style="width:100%;height:100%;object-fit:cover;"></div>`
          : `<div style="width:148px;height:148px;border-radius:12px;background:rgba(255,255,255,.04);border:2px dashed rgba(255,255,255,.12);display:flex;align-items:center;justify-content:center;font-size:3.2rem;">🏆</div>`
        }
        <div style="font-size:.7rem;color:#fbbf24;font-weight:600;text-align:center;line-height:1.3;max-width:148px;">${prize1}</div>
        <!-- Divider -->
        <div style="width:100%;height:1px;background:rgba(255,255,255,.08);margin:.1rem 0;"></div>
        <!-- QR -->
        <div style="font-size:.53rem;letter-spacing:2px;color:rgba(255,255,255,.33);text-transform:uppercase;">Verificación</div>
        <div id="tp_qr_canvas" style="background:#fff;padding:6px;border-radius:8px;line-height:0;"></div>
        <div style="font-size:.54rem;color:rgba(255,255,255,.3);text-align:center;line-height:1.4;word-break:break-all;max-width:148px;">${verifyUrl}</div>
        <!-- Ticket number -->
        <div style="margin-top:auto;text-align:center;border-top:1px solid rgba(255,255,255,.07);padding-top:.5rem;width:100%;">
          <div style="font-size:.53rem;color:rgba(255,255,255,.28);text-transform:uppercase;letter-spacing:1px;margin-bottom:.2rem;">N° Ticket</div>
          <div style="font-size:1rem;font-weight:900;color:rgba(255,255,255,.52);letter-spacing:-.5px;">#${ticket.number}</div>
        </div>
      </div>
    </div>
    <!-- Footer -->
    <div style="padding:.6rem 1.5rem;border-top:1px solid rgba(255,255,255,.06);display:flex;justify-content:space-between;align-items:center;background:rgba(0,0,0,.22);">
      <div style="font-size:.62rem;color:rgba(255,255,255,.27);line-height:1.5;max-width:480px;">Este ticket es válido únicamente con registro digital. Consérvalo para presentarlo en caso de ser ganador. Participación sujeta a bases legales.</div>
      <div style="font-size:.62rem;color:rgba(255,255,255,.27);white-space:nowrap;margin-left:1rem;">${siteName} © ${new Date().getFullYear()}</div>
    </div>
    <div style="height:3px;background:linear-gradient(90deg,var(--color-accent,#f59e0b),var(--color-primary,#7c3aed));"></div>
  </div>`;

  // Generate QR code
  const qrEl = document.getElementById('tp_qr_canvas');
  if (qrEl) {
    qrEl.innerHTML = '';
    if (window.QRCode) {
      new QRCode(qrEl, {
        text: verifyUrl,
        width: 110,
        height: 110,
        colorDark: '#1a0a2e',
        colorLight: '#ffffff',
        correctLevel: QRCode.CorrectLevel.M,
      });
    } else {
      qrEl.innerHTML = '<div style="width:110px;height:110px;display:flex;align-items:center;justify-content:center;background:#e5e7eb;color:#6b7280;font-size:.7rem;">QR</div>';
    }
  }
}

function printTicketPreview() {
  const card = document.getElementById('ticketCard');
  if (!card) return;
  const w = window.open('', '_blank', 'width=900,height=600');
  w.document.write(`<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Ticket Preview</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>@page{margin:15mm}body{background:#f0f0f0;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:2rem;box-sizing:border-box;font-family:'Inter',sans-serif;}@media print{body{background:#fff;padding:0;}}</style>
  </head><body>${card.outerHTML}<script>window.onload=()=>window.print();<\/script></body></html>`);
  w.document.close();
}

/* ══════════════════════════════════════════════════════════════════════════ */
/*  MULTI-TICKET PDF GENERATOR                                               */
/* ══════════════════════════════════════════════════════════════════════════ */
async function generateTicketsPDF(ticketId) {
  try {
    showToast('Generando PDF…');
    const [t, s] = await Promise.all([
      api('/tickets.php', { params: { id: ticketId } }),
      api('/settings.php'),
    ]);

    const nums      = Array.isArray(t.ticket_numbers) ? t.ticket_numbers : [];
    if (!nums.length) { showToast('Este ticket no tiene números asignados', 'error'); return; }

    const siteUrl   = (s.site_url  || 'http://localhost/surteados').replace(/\/$/, '');
    const siteName  = s.site_name  || 'Surteados';
    const logoHtml  = s.site_logo
      ? `<img src="${s.site_logo}" style="height:28px;max-width:120px;object-fit:contain;">`
      : `<span style="font-weight:900;font-size:1rem;letter-spacing:-.5px;color:#fff;">${siteName}</span>`;

    const prize1    = t.prizes?.[0]?.name || t.prizes?.[0]?.label || 'Premio principal';
    const prize1img = t.prizes?.[0]?.image_url || '';
    const drawDate  = t.draw_date
      ? new Date(t.draw_date).toLocaleDateString('es-CL', { day:'2-digit', month:'long', year:'numeric' })
      : 'Por definir';
    const buyDate   = t.purchase_date || t.created_at
      ? new Date(t.purchase_date || t.created_at).toLocaleDateString('es-CL', { day:'2-digit', month:'long', year:'numeric' })
      : '—';
    const perUnit   = nums.length > 0 ? Math.round(Number(t.amount) / nums.length) : Number(t.amount);

    const prizeRightHtml = prize1img
      ? `<div style="width:130px;height:130px;border-radius:10px;overflow:hidden;border:2px solid rgba(255,255,255,.15);margin-bottom:.4rem;"><img src="${prize1img}" style="width:100%;height:100%;object-fit:cover;"></div>`
      : `<div style="width:130px;height:130px;border-radius:10px;background:rgba(255,255,255,.04);border:2px dashed rgba(255,255,255,.15);display:flex;align-items:center;justify-content:center;font-size:2.8rem;margin-bottom:.4rem;">🏆</div>`;

    const perfs = Array(8).fill(
      `<div style="width:10px;height:10px;background:#f0f0f0;border-radius:50%;margin:4px 0;flex-shrink:0;"></div>`
    ).join('');

    // Build one ticket card per number — QR injected after load via JS
    const ticketCards = nums.map((num, idx) => {
      const verifyUrl = `${siteUrl}/verificar/${String(num).replace(/[^0-9]/g,'')}`;
      return `
      <div class="ticket-card" data-qr-url="${verifyUrl}" data-qr-idx="${idx}" style="
        display:flex;
        max-width:760px;
        width:100%;
        background:linear-gradient(135deg,#1a0a2e 0%,#2d1158 55%,#1a0a2e 100%);
        border-radius:14px;
        overflow:hidden;
        box-shadow:0 16px 48px rgba(0,0,0,.55);
        font-family:'Inter',sans-serif;
        color:#fff;
        position:relative;
        page-break-inside:avoid;
        break-inside:avoid;
      ">
        <!-- left accent bar -->
        <div style="width:4px;background:linear-gradient(180deg,#7c3aed,#f59e0b,#7c3aed);flex-shrink:0;"></div>
        <!-- main body -->
        <div style="flex:1;display:flex;flex-direction:column;min-width:0;">
          <!-- header -->
          <div style="padding:.65rem 1.25rem;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid rgba(255,255,255,.07);">
            <div>${logoHtml}</div>
            <div style="text-align:right;">
              <div style="font-size:.5rem;letter-spacing:2.5px;color:rgba(255,255,255,.35);text-transform:uppercase;">🎫 Ticket Oficial</div>
              <div style="font-size:.52rem;color:rgba(255,255,255,.3);margin-top:.18rem;letter-spacing:.5px;">ID Venta: <strong style="color:rgba(255,255,255,.6);">${ticketId}</strong></div>
            </div>
          </div>
          <!-- body row -->
          <div style="display:flex;flex:1;">
            <!-- left: info -->
            <div style="flex:1;padding:1rem 1.25rem;min-width:0;">
              <div style="margin-bottom:.75rem;">
                <div style="font-size:.48rem;letter-spacing:2.5px;color:rgba(255,255,255,.32);text-transform:uppercase;margin-bottom:.1rem;">N° de Ticket</div>
                <div style="font-size:2.1rem;font-weight:900;letter-spacing:-1.5px;line-height:1;color:#fff;text-shadow:0 0 24px rgba(124,58,237,.7);">#${String(num).padStart(6,'0')}</div>
              </div>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:.4rem .9rem;margin-bottom:.65rem;font-size:.72rem;">
                <div>
                  <div style="font-size:.47rem;letter-spacing:2px;color:rgba(255,255,255,.3);text-transform:uppercase;margin-bottom:.08rem;">Sorteo</div>
                  <div style="font-weight:700;line-height:1.25;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${t.raffle_title || '—'}</div>
                </div>
                <div>
                  <div style="font-size:.47rem;letter-spacing:2px;color:rgba(255,255,255,.3);text-transform:uppercase;margin-bottom:.08rem;">Fecha sorteo</div>
                  <div>${drawDate}</div>
                </div>
                <div>
                  <div style="font-size:.47rem;letter-spacing:2px;color:rgba(255,255,255,.3);text-transform:uppercase;margin-bottom:.08rem;">Premio</div>
                  <div style="color:#fbbf24;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${prize1}</div>
                </div>
                <div>
                  <div style="font-size:.47rem;letter-spacing:2px;color:rgba(255,255,255,.3);text-transform:uppercase;margin-bottom:.08rem;">Categoría</div>
                  <div>${t.category || '—'}</div>
                </div>
              </div>
              <div style="display:flex;gap:.6rem;background:rgba(255,255,255,.05);border-radius:8px;padding:.45rem .75rem;margin-bottom:.65rem;border:1px solid rgba(255,255,255,.08);font-size:.72rem;">
                <div>
                  <div style="font-size:.47rem;letter-spacing:2px;color:rgba(255,255,255,.3);text-transform:uppercase;margin-bottom:.08rem;">Pack</div>
                  <div style="color:#fbbf24;font-weight:600;">${t.pack_label || '—'}</div>
                </div>
                <div style="width:1px;background:rgba(255,255,255,.1);"></div>
                <div>
                  <div style="font-size:.47rem;letter-spacing:2px;color:rgba(255,255,255,.3);text-transform:uppercase;margin-bottom:.08rem;">Valor ticket</div>
                  <div style="font-weight:800;color:#fbbf24;">$${Number(perUnit).toLocaleString('es-CL')} CLP</div>
                </div>
              </div>
              <div style="font-size:.7rem;">
                <div style="font-size:.47rem;letter-spacing:2px;color:rgba(255,255,255,.3);text-transform:uppercase;margin-bottom:.3rem;">Participante</div>
                <div style="font-weight:600;margin-bottom:.08rem;">${t.buyer_name}</div>
                <div style="color:rgba(255,255,255,.45);font-size:.65rem;">${t.buyer_email}${t.buyer_phone ? ' · ' + t.buyer_phone : ''}</div>
                <div style="color:rgba(255,255,255,.28);font-size:.6rem;margin-top:.1rem;">Compra: ${buyDate}</div>
              </div>
            </div>
            <!-- right stub: prize image + QR -->
            <div style="width:170px;flex-shrink:0;border-left:2px dashed rgba(255,255,255,.12);padding:1rem .85rem;display:flex;flex-direction:column;align-items:center;gap:.45rem;background:rgba(0,0,0,.2);position:relative;">
              <div style="position:absolute;top:0;left:-6px;display:flex;flex-direction:column;">${perfs}</div>
              <div style="font-size:.47rem;letter-spacing:2px;color:rgba(255,255,255,.3);text-transform:uppercase;align-self:flex-start;">Premio</div>
              ${prizeRightHtml}
              <div style="font-size:.63rem;color:#fbbf24;font-weight:600;text-align:center;line-height:1.3;max-width:130px;">${prize1}</div>
              <div style="width:100%;height:1px;background:rgba(255,255,255,.07);"></div>
              <div style="font-size:.47rem;letter-spacing:2px;color:rgba(255,255,255,.3);text-transform:uppercase;">Verificación</div>
              <div id="qr-${idx}" style="background:#fff;padding:5px;border-radius:7px;line-height:0;"></div>
              <div style="font-size:.48rem;color:rgba(255,255,255,.28);text-align:center;line-height:1.35;word-break:break-all;max-width:130px;">${verifyUrl}</div>
              <div style="margin-top:auto;text-align:center;border-top:1px solid rgba(255,255,255,.07);padding-top:.4rem;width:100%;">
                <div style="font-size:.47rem;color:rgba(255,255,255,.25);text-transform:uppercase;letter-spacing:1px;margin-bottom:.15rem;">N° Ticket</div>
                <div style="font-size:.9rem;font-weight:900;color:rgba(255,255,255,.45);letter-spacing:-.5px;">#${String(num).padStart(6,'0')}</div>
              </div>
            </div>
          </div>
          <!-- footer -->
          <div style="padding:.4rem 1.25rem;border-top:1px solid rgba(255,255,255,.06);display:flex;justify-content:space-between;align-items:center;background:rgba(0,0,0,.2);">
            <div style="font-size:.55rem;color:rgba(255,255,255,.22);line-height:1.45;max-width:480px;">Ticket válido únicamente con registro digital. Ticket ${idx+1} de ${nums.length}. Participación sujeta a bases legales.</div>
            <div style="font-size:.55rem;color:rgba(255,255,255,.22);white-space:nowrap;margin-left:.75rem;">${siteName} © ${new Date().getFullYear()}</div>
          </div>
        </div>
        <!-- right accent bar -->
        <div style="width:4px;background:linear-gradient(180deg,#f59e0b,#7c3aed,#f59e0b);flex-shrink:0;"></div>
      </div>`;
    }).join('<div style="height:1.5rem;"></div>');

    const html = `<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Tickets PDF — ${t.raffle_title || 'Sorteo'} — ${t.buyer_name}</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"><\/script>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      background: #f0f0f0;
      font-family: 'Inter', sans-serif;
      padding: 2rem;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 0;
    }
    @media print {
      body { background: #fff; padding: 10mm; }
      @page { margin: 10mm; size: A4 portrait; }
    }
    .header-info {
      width: 100%;
      max-width: 760px;
      margin-bottom: 1.25rem;
      background: #fff;
      border-radius: 10px;
      padding: .75rem 1.25rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: .78rem;
      color: #475569;
      border: 1px solid #e2e8f0;
    }
    @media print { .header-info { display: none; } }
  </style>
</head>
<body>
  <div class="header-info">
    <span>🎫 <strong>${nums.length} ticket${nums.length > 1 ? 's' : ''}</strong> — ${t.raffle_title || '—'}</span>
    <span>Comprador: <strong>${t.buyer_name}</strong></span>
    <button onclick="window.print()" style="padding:.35rem .9rem;background:#7c3aed;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:.75rem;font-weight:600;">🖨️ Imprimir / Guardar PDF</button>
  </div>
  ${ticketCards}
  <script>
    window.addEventListener('load', function() {
      document.querySelectorAll('[id^="qr-"]').forEach(function(el) {
        const card = el.closest('.ticket-card');
        const url  = card ? card.dataset.qrUrl : '';
        if (!url || !window.QRCode) return;
        new QRCode(el, {
          text: url,
          width: 100, height: 100,
          colorDark: '#1a0a2e',
          colorLight: '#ffffff',
          correctLevel: QRCode.CorrectLevel.M,
        });
      });
    });
  <\/script>
</body>
</html>`;

    const w = window.open('', '_blank', 'width=960,height=800');
    w.document.write(html);
    w.document.close();
  } catch(e) { showToast('Error generando PDF: ' + e.message, 'error'); }
}
