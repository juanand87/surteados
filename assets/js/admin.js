/**
 * SURTEADOS — Admin Panel JS
 */

// ─── Sidebar navigation ───────────────────────────────────────────────────────
(function() {
  const nav = document.getElementById('navbar');
  if (nav) window.addEventListener('scroll', () => nav.classList.toggle('scrolled', window.scrollY > 20));

  const toggle = document.getElementById('mobileToggle');
  const mobileNav = document.getElementById('mobileNav');
  if (toggle && mobileNav) toggle.addEventListener('click', () => mobileNav.classList.toggle('open'));

  document.querySelectorAll('.admin-nav-item[data-section]').forEach(item => {
    item.addEventListener('click', () => {
      document.querySelectorAll('.admin-nav-item').forEach(i => i.classList.remove('active'));
      item.classList.add('active');
      const section = item.dataset.section;
      document.querySelectorAll('.admin-section').forEach(s => s.classList.remove('active'));
      const target = document.getElementById('sec-' + section);
      if (target) target.classList.add('active');
      renderSection(section);
    });
  });

  renderSection('dashboard');
})();

function renderSection(name) {
  switch(name) {
    case 'dashboard': renderDashboard(); break;
    case 'sorteos': renderSorteos(); break;
    case 'tickets': renderTickets(); break;
    case 'ganadores': renderGanadores(); break;
    case 'settings': renderSettings(); break;
    case 'theme': renderThemeAdmin(); break;
  }
}

// ─── Dashboard ────────────────────────────────────────────────────────────────
function renderDashboard() {
  const raffles = db.getRaffles();
  const tickets = db.getTickets();
  const winners = db.getWinners();
  const totalRevenue = tickets.reduce((a, t) => a + (t.amount || 0), 0);
  const totalTicketsSold = tickets.reduce((a, t) => a + (t.ticketNumbers?.length || 1), 0);

  const statsEl = document.getElementById('dashStats');
  if (statsEl) {
    statsEl.innerHTML = [
      { label: 'Sorteos activos', value: raffles.filter(r => r.status === 'active').length, sub: raffles.length + ' total', icon: '🎟️' },
      { label: 'Tickets vendidos', value: totalTicketsSold.toLocaleString('es-CL'), sub: tickets.length + ' transacciones', icon: '🎫' },
      { label: 'Ingresos totales', value: formatPrice(totalRevenue), sub: 'en ventas', icon: '💰' },
      { label: 'Ganadores', value: winners.length, sub: 'premios entregados', icon: '🏆' }
    ].map(s => `
      <div class="admin-stat-card">
        <div class="stat-label">${s.icon} ${s.label}</div>
        <div class="stat-value">${s.value}</div>
        <div class="stat-sub">${s.sub}</div>
      </div>
    `).join('');
  }

  const recentEl = document.getElementById('dashRecentTickets');
  if (recentEl) {
    const recent = [...tickets].reverse().slice(0, 8);
    if (recent.length === 0) {
      recentEl.innerHTML = `<tr><td colspan="4" class="text-center text-muted" style="padding:1.5rem;">Sin ventas aún</td></tr>`;
    } else {
      recentEl.innerHTML = recent.map(t => {
        const raffle = db.getRaffle(t.raffleId);
        return `
          <tr>
            <td class="text-white">${t.buyerName}</td>
            <td>${raffle?.title || t.raffleId}</td>
            <td class="text-accent">${formatPrice(t.amount)}</td>
            <td>${formatDate(t.purchaseDate)}</td>
          </tr>`;
      }).join('');
    }
  }

  const progressEl = document.getElementById('dashRaffleProgress');
  if (progressEl) {
    if (raffles.length === 0) {
      progressEl.innerHTML = `<div class="empty-state"><div class="empty-icon">🎟️</div><p>No hay sorteos creados.</p></div>`;
    } else {
      progressEl.innerHTML = raffles.map(r => {
        const pct = percentage(r.soldTickets, r.totalTickets);
        const statusMap = { active: 'pill-green', soon: 'pill-amber', ended: 'pill-gray' };
        return `
          <div style="margin-bottom:1.25rem;">
            <div class="flex-between mb-1">
              <span style="font-size:.875rem; font-weight:600; color:var(--text-inv); display:flex; align-items:center; gap:.4rem;">
                ${r.imageEmoji || '🎁'} ${r.title}
              </span>
              <span class="pill ${statusMap[r.status] || 'pill-gray'}" style="font-size:.68rem;">${r.status === 'active' ? 'Activo' : r.status === 'soon' ? 'Pronto' : 'Terminado'}</span>
            </div>
            <div class="progress-bar" style="margin-bottom:.3rem;">
              <div class="progress-fill" style="width:${pct}%; transition:width .5s;"></div>
            </div>
            <div style="font-size:.75rem; color:var(--text-muted);">${r.soldTickets.toLocaleString('es-CL')} / ${r.totalTickets.toLocaleString('es-CL')} tickets (${pct}%)</div>
          </div>`;
      }).join('');
    }
  }
}

// ─── Sorteos (Raffles) ────────────────────────────────────────────────────────
function renderSorteos() {
  const tbody = document.getElementById('sorteosTable');
  if (!tbody) return;
  const raffles = db.getRaffles();
  if (raffles.length === 0) {
    tbody.innerHTML = `<tr><td colspan="7" class="text-center" style="padding:2rem; color:var(--text-muted);">No hay sorteos. Crea el primero.</td></tr>`;
    return;
  }
  const statusMap = { active: ['✅ Activo', 'pill-green'], soon: ['⏳ Próximo', 'pill-amber'], ended: ['⚫ Finalizado', 'pill-gray'] };
  tbody.innerHTML = raffles.map(r => {
    const [statusLabel, statusClass] = statusMap[r.status] || statusMap.ended;
    const pct = percentage(r.soldTickets, r.totalTickets);
    return `
      <tr>
        <td>
          <div style="display:flex; align-items:center; gap:.75rem;">
            <span style="font-size:1.5rem;">${r.imageEmoji || '🎁'}</span>
            <div>
              <div style="font-weight:600; color:var(--text-inv);">${r.title}</div>
              <div style="font-size:.75rem; color:var(--text-muted);">${r.id}</div>
            </div>
          </div>
        </td>
        <td>${r.category}</td>
        <td><span class="pill ${statusClass}" style="font-size:.72rem;">${statusLabel}</span></td>
        <td>
          <div style="font-size:.8rem;">${r.soldTickets.toLocaleString('es-CL')} / ${r.totalTickets.toLocaleString('es-CL')}</div>
          <div class="progress-bar" style="height:4px; margin-top:.25rem; width:100px;">
            <div class="progress-fill" style="width:${pct}%;"></div>
          </div>
        </td>
        <td>${r.prizes?.length || 0} premios</td>
        <td style="font-size:.82rem;">${r.drawDate ? formatDate(r.drawDate) : '—'}</td>
        <td>
          <div class="actions-cell">
            <button class="btn btn-ghost btn-sm" onclick="openRaffleModal('${r.id}')">✏️ Editar</button>
            <button class="btn btn-danger btn-sm" onclick="deleteRaffle('${r.id}')">🗑️</button>
          </div>
        </td>
      </tr>`;
  }).join('');
}

function deleteRaffle(id) {
  const raffle = db.getRaffle(id);
  if (!raffle) return;
  if (!confirm(`¿Eliminar el sorteo "${raffle.title}"? También se eliminarán sus tickets.`)) return;
  db.deleteRaffle(id);
  showToast('Sorteo eliminado', 'success');
  renderSorteos();
  renderDashboard();
}

// ─── Tickets ──────────────────────────────────────────────────────────────────
function renderTickets() {
  const tbody = document.getElementById('ticketsTable');
  const filterSelect = document.getElementById('ticketsFilter');
  const searchInput = document.getElementById('ticketsSearch');
  const countEl = document.getElementById('ticketsCount');
  if (!tbody) return;

  // Populate filter
  const raffles = db.getRaffles();
  if (filterSelect && filterSelect.options.length <= 1) {
    raffles.forEach(r => {
      const opt = document.createElement('option');
      opt.value = r.id;
      opt.textContent = r.title;
      filterSelect.appendChild(opt);
    });
  }

  function render() {
    let tickets = db.getTickets();
    const selectedRaffle = filterSelect?.value;
    const search = searchInput?.value.toLowerCase().trim();
    if (selectedRaffle && selectedRaffle !== 'all') tickets = tickets.filter(t => t.raffleId === selectedRaffle);
    if (search) tickets = tickets.filter(t =>
      t.buyerName.toLowerCase().includes(search) ||
      t.buyerEmail.toLowerCase().includes(search) ||
      (t.ticketNumbers || [t.number]).some(n => n.includes(search))
    );

    if (countEl) countEl.textContent = tickets.length + ' registros';

    if (tickets.length === 0) {
      tbody.innerHTML = `<tr><td colspan="7" class="text-center" style="padding:2rem; color:var(--text-muted);">Sin resultados</td></tr>`;
      return;
    }

    const payIcons = { webpay: '💳', khipu: '🏦', flow: '🔄', transfer: '📤' };
    tbody.innerHTML = [...tickets].reverse().map(t => {
      const raffle = db.getRaffle(t.raffleId);
      const nums = t.ticketNumbers || [t.number];
      return `
        <tr>
          <td>${nums.map(n => `<span class="ticket-num-badge">${n}</span>`).join(' ')}</td>
          <td>
            <div style="font-weight:600; color:var(--text-inv);">${t.buyerName}</div>
            <div style="font-size:.75rem; color:var(--text-muted);">${t.buyerEmail}</div>
          </td>
          <td>${raffle?.title || t.raffleId}</td>
          <td>${t.pack}</td>
          <td style="color:var(--color-accent); font-weight:600;">${formatPrice(t.amount)}</td>
          <td style="font-size:.8rem;">${formatDate(t.purchaseDate)}</td>
          <td>${payIcons[t.paymentMethod] || '💰'} ${t.paymentMethod || '—'}</td>
        </tr>`;
    }).join('');
  }

  render();
  filterSelect?.addEventListener('change', render);
  searchInput?.addEventListener('input', render);
}

// ─── Ganadores ────────────────────────────────────────────────────────────────
function renderGanadores() {
  const tbody = document.getElementById('ganadoresTable');
  if (!tbody) return;
  const winners = db.getWinners();
  if (winners.length === 0) {
    tbody.innerHTML = `<tr><td colspan="6" class="text-center" style="padding:2rem; color:var(--text-muted);">No hay ganadores registrados.</td></tr>`;
    return;
  }
  tbody.innerHTML = winners.map(w => `
    <tr>
      <td>
        <div style="display:flex; align-items:center; gap:.5rem;">
          <span>${w.emoji || '🏆'}</span>
          <div>
            <div style="font-weight:600; color:var(--text-inv);">${w.winnerName}</div>
            <div style="font-size:.75rem; color:var(--text-muted);">${w.winnerLocation}</div>
          </div>
        </div>
      </td>
      <td>${w.raffleTitle}</td>
      <td>${w.prize}</td>
      <td style="color:var(--color-accent);">${formatPrice(w.prizeValue)}</td>
      <td>${formatDate(w.drawDate)}</td>
      <td>
        <div class="actions-cell">
          <button class="btn btn-danger btn-sm" onclick="deleteWinner('${w.id}')">🗑️</button>
        </div>
      </td>
    </tr>`).join('');
}

function deleteWinner(id) {
  if (!confirm('¿Eliminar este ganador?')) return;
  db.deleteWinner(id);
  showToast('Ganador eliminado', 'success');
  renderGanadores();
}

// ─── Settings ─────────────────────────────────────────────────────────────────
function renderSettings() {
  const settings = db.getSettings();
  const fields = ['siteName', 'tagline', 'email', 'whatsapp'];
  fields.forEach(f => {
    const el = document.getElementById('s_' + f);
    if (el) el.value = settings[f] || '';
  });
  if (settings.socialLinks) {
    ['instagram','tiktok','youtube','facebook'].forEach(s => {
      const el = document.getElementById('s_' + s);
      if (el) el.value = settings.socialLinks[s] || '';
    });
  }

  document.getElementById('settingsForm')?.addEventListener('submit', e => {
    e.preventDefault();
    const updated = { ...db.getSettings() };
    fields.forEach(f => { updated[f] = document.getElementById('s_' + f)?.value || ''; });
    updated.socialLinks = {};
    ['instagram','tiktok','youtube','facebook'].forEach(s => {
      updated.socialLinks[s] = document.getElementById('s_' + s)?.value || '';
    });
    db.saveSettings(updated);
    showToast('Configuración guardada', 'success');
  });
}

// ─── Theme Admin ──────────────────────────────────────────────────────────────
const PRESETS = {
  purple:  { name: '🟣 Púrpura & Ámbar', primary: '#7c3aed', primaryLight: '#9d5cf6', primaryDark: '#5b21b6', accent: '#f59e0b', accentLight: '#fbbf24', accentDark: '#d97706' },
  blue:    { name: '🔵 Azul & Cyan',      primary: '#2563eb', primaryLight: '#3b82f6', primaryDark: '#1e40af', accent: '#06b6d4', accentLight: '#22d3ee', accentDark: '#0891b2' },
  emerald: { name: '🟢 Esmeralda & Oro',  primary: '#059669', primaryLight: '#10b981', primaryDark: '#065f46', accent: '#f59e0b', accentLight: '#fbbf24', accentDark: '#b45309' },
  rose:    { name: '🌹 Rosa & Ámbar',     primary: '#e11d48', primaryLight: '#f43f5e', primaryDark: '#9f1239', accent: '#f59e0b', accentLight: '#fbbf24', accentDark: '#b45309' },
  indigo:  { name: '💜 Índigo & Magenta', primary: '#4338ca', primaryLight: '#6366f1', primaryDark: '#3730a3', accent: '#c026d3', accentLight: '#d946ef', accentDark: '#a21caf' },
  teal:    { name: '🩵 Teal & Naranja',   primary: '#0d9488', primaryLight: '#14b8a6', primaryDark: '#0f766e', accent: '#f97316', accentLight: '#fb923c', accentDark: '#c2410c' }
};

function renderThemeAdmin() {
  const presetsEl = document.getElementById('adminPresets');
  if (presetsEl && presetsEl.children.length === 0) {
    Object.entries(PRESETS).forEach(([key, preset]) => {
      const btn = document.createElement('button');
      btn.className = 'btn btn-ghost';
      btn.style.justifyContent = 'flex-start';
      btn.style.gap = '1rem';
      btn.innerHTML = `
        <div style="width:32px; height:32px; border-radius:50%; background:linear-gradient(135deg, ${preset.primary}, ${preset.accent}); flex-shrink:0;"></div>
        <span>${preset.name}</span>`;
      btn.addEventListener('click', () => {
        applyTheme(preset); db.saveTheme(preset);
        syncAdminColorInputs(preset.primary, preset.accent);
        showToast('Tema "' + preset.name + '" aplicado', 'success');
      });
      presetsEl.appendChild(btn);
    });
  }

  const cprimary = document.getElementById('adminColorPrimary');
  const cprimaryHex = document.getElementById('adminColorPrimaryHex');
  const caccent = document.getElementById('adminColorAccent');
  const caccentHex = document.getElementById('adminColorAccentHex');

  // Sync with current theme
  const currentTheme = db.getSettings().theme;
  if (currentTheme) {
    if (cprimary) cprimary.value = currentTheme.primary || '#7c3aed';
    if (cprimaryHex) cprimaryHex.value = currentTheme.primary || '#7c3aed';
    if (caccent) caccent.value = currentTheme.accent || '#f59e0b';
    if (caccentHex) caccentHex.value = currentTheme.accent || '#f59e0b';
  }

  function syncInputs(colorInput, hexInput) {
    colorInput?.addEventListener('input', () => { if (hexInput) hexInput.value = colorInput.value; });
    hexInput?.addEventListener('input', () => { if (/^#[0-9a-f]{6}$/i.test(hexInput.value) && colorInput) colorInput.value = hexInput.value; });
  }
  syncInputs(cprimary, cprimaryHex);
  syncInputs(caccent, caccentHex);

  document.getElementById('adminApplyTheme')?.addEventListener('click', () => {
    const p = cprimary?.value || '#7c3aed';
    const a = caccent?.value || '#f59e0b';
    const theme = {
      primary: p, primaryLight: lightenColor(p), primaryDark: darkenColor(p),
      accent: a,  accentLight: lightenColor(a, 0.15),  accentDark: darkenColor(a)
    };
    applyTheme(theme); db.saveTheme(theme);
    showToast('Tema aplicado y guardado', 'success');
  });

  document.getElementById('adminResetTheme')?.addEventListener('click', () => {
    const def = PRESETS.purple;
    applyTheme(def); db.saveTheme(def);
    syncAdminColorInputs(def.primary, def.accent);
    showToast('Tema restaurado', 'info');
  });
}

function syncAdminColorInputs(primary, accent) {
  const cp = document.getElementById('adminColorPrimary');
  const cph = document.getElementById('adminColorPrimaryHex');
  const ca = document.getElementById('adminColorAccent');
  const cah = document.getElementById('adminColorAccentHex');
  if (cp) cp.value = primary;
  if (cph) cph.value = primary;
  if (ca) ca.value = accent;
  if (cah) cah.value = accent;
}

// ─── Raffle Modal ─────────────────────────────────────────────────────────────
let _editingRaffleId = null;
let _tempPrizes = [];
let _tempPacks = [];

function openRaffleModal(id = null) {
  _editingRaffleId = id;
  const modal = document.getElementById('raffleModal');
  const title = document.getElementById('raffleModalTitle');

  if (id) {
    const r = db.getRaffle(id);
    if (!r) return;
    title.textContent = '✏️ Editar Sorteo';
    document.getElementById('rf_title').value = r.title || '';
    document.getElementById('rf_category').value = r.category || 'Tecnología';
    document.getElementById('rf_description').value = r.description || '';
    document.getElementById('rf_status').value = r.status || 'active';
    document.getElementById('rf_drawDate').value = r.drawDate ? r.drawDate.slice(0,16) : '';
    document.getElementById('rf_totalTickets').value = r.totalTickets || '';
    document.getElementById('rf_emoji').value = r.imageEmoji || '';
    document.getElementById('rf_featured').checked = r.featured || false;
    document.getElementById('rf_organizer').value = r.legalInfo?.organizer || '';
    document.getElementById('rf_rut').value = r.legalInfo?.rut || '';
    document.getElementById('rf_notary').value = r.legalInfo?.notary || '';
    document.getElementById('rf_certificate').value = r.legalInfo?.certificate || '';
    document.getElementById('rf_salesPeriod').value = r.legalInfo?.salesPeriod || '';
    _tempPrizes = JSON.parse(JSON.stringify(r.prizes || []));
    _tempPacks = JSON.parse(JSON.stringify(r.packs || []));
  } else {
    title.textContent = '+ Nuevo Sorteo';
    document.getElementById('rf_title').value = '';
    document.getElementById('rf_category').value = 'Tecnología';
    document.getElementById('rf_description').value = '';
    document.getElementById('rf_status').value = 'active';
    document.getElementById('rf_drawDate').value = '';
    document.getElementById('rf_totalTickets').value = '';
    document.getElementById('rf_emoji').value = '🎁';
    document.getElementById('rf_featured').checked = false;
    document.getElementById('rf_organizer').value = 'Surteados Chile SpA';
    document.getElementById('rf_rut').value = '77.999.888-1';
    document.getElementById('rf_notary').value = '';
    document.getElementById('rf_certificate').value = '';
    document.getElementById('rf_salesPeriod').value = '';
    _tempPrizes = [{ id: generateId('pr'), place: 1, name: '', value: 0, emoji: '🏆', image: null }];
    _tempPacks = [
      { id: generateId('pk'), qty: 1, price: 0, originalPrice: 0, label: '1 Ticket', discount: 0 },
      { id: generateId('pk'), qty: 3, price: 0, originalPrice: 0, label: '3 Tickets', discount: 0 },
      { id: generateId('pk'), qty: 5, price: 0, originalPrice: 0, label: '5 Tickets', discount: 0, bestValue: true }
    ];
  }

  switchRaffleTab('info');
  renderPrizes(); renderPacks();
  modal.classList.add('open');
}

function closeRaffleModal() {
  document.getElementById('raffleModal').classList.remove('open');
}

function switchRaffleTab(tab) {
  ['info', 'prizes', 'packs', 'legal'].forEach(t => {
    const panel = document.getElementById(`rtab-${t}-panel`);
    const btn = document.getElementById(`rtab-${t}`);
    if (panel) panel.classList.toggle('hidden', t !== tab);
    if (btn) {
      btn.className = t === tab ? 'btn btn-primary btn-sm' : 'btn btn-ghost btn-sm';
      btn.style.flex = '1';
    }
  });
}

function renderPrizes() {
  const container = document.getElementById('prizesContainer');
  if (!container) return;
  container.innerHTML = _tempPrizes.map((p, i) => `
    <div style="background:var(--bg-base); border:1px solid var(--border); border-radius:var(--radius-md); padding:1rem; margin-bottom:.75rem;" id="prize-row-${i}">
      <div class="flex-between mb-2">
        <span style="font-size:.8rem; font-weight:700; color:var(--color-accent);">${i === 0 ? '🥇 1er Premio' : i === 1 ? '🥈 2do Premio' : i === 2 ? '🥉 3er Premio' : `${i+1}° Premio`}</span>
        <button class="btn btn-danger btn-sm" onclick="removePrize(${i})" style="padding:.2rem .5rem; font-size:.75rem;">✕</button>
      </div>
      <div class="form-row">
        <div class="form-group" style="margin:0;"><label class="form-label">Nombre del premio</label><input type="text" class="form-control" value="${p.name}" oninput="_tempPrizes[${i}].name=this.value" placeholder="iPhone 16 Pro Max"></div>
        <div class="form-group" style="margin:0;"><label class="form-label">Valor ($)</label><input type="number" class="form-control" value="${p.value}" oninput="_tempPrizes[${i}].value=Number(this.value)" placeholder="1299000"></div>
        <div class="form-group" style="margin:0;"><label class="form-label">Emoji</label><input type="text" class="form-control" value="${p.emoji||'🎁'}" oninput="_tempPrizes[${i}].emoji=this.value" maxlength="2" style="font-size:1.5rem; text-align:center;"></div>
      </div>
    </div>
  `).join('');
}

function addPrizeRow() {
  _tempPrizes.push({ id: generateId('pr'), place: _tempPrizes.length + 1, name: '', value: 0, emoji: '🎁', image: null });
  renderPrizes();
}

function removePrize(i) {
  _tempPrizes.splice(i, 1);
  _tempPrizes.forEach((p, idx) => p.place = idx + 1);
  renderPrizes();
}

function renderPacks() {
  const container = document.getElementById('packsContainer');
  if (!container) return;
  container.innerHTML = _tempPacks.map((p, i) => `
    <div style="background:var(--bg-base); border:1px solid var(--border); border-radius:var(--radius-md); padding:1rem; margin-bottom:.75rem;">
      <div class="flex-between mb-2">
        <span style="font-size:.8rem; font-weight:700; color:var(--color-primary-light);">Pack ${i+1}</span>
        <button class="btn btn-danger btn-sm" onclick="removePack(${i})" style="padding:.2rem .5rem; font-size:.75rem;">✕</button>
      </div>
      <div style="display:grid; grid-template-columns:1fr 1fr 1fr 1fr; gap:.5rem; flex-wrap:wrap;">
        <div class="form-group" style="margin:0;"><label class="form-label">Cantidad tickets</label><input type="number" class="form-control" value="${p.qty}" oninput="_tempPacks[${i}].qty=Number(this.value)" min="1"></div>
        <div class="form-group" style="margin:0;"><label class="form-label">Precio ($)</label><input type="number" class="form-control" value="${p.price}" oninput="_tempPacks[${i}].price=Number(this.value)"></div>
        <div class="form-group" style="margin:0;"><label class="form-label">Precio original ($)</label><input type="number" class="form-control" value="${p.originalPrice||p.price}" oninput="_tempPacks[${i}].originalPrice=Number(this.value)"></div>
        <div class="form-group" style="margin:0;"><label class="form-label">Descuento (%)</label><input type="number" class="form-control" value="${p.discount||0}" oninput="_tempPacks[${i}].discount=Number(this.value)" min="0" max="99"></div>
      </div>
      <div class="form-row" style="margin-top:.5rem;">
        <div class="form-group" style="margin:0;"><label class="form-label">Etiqueta</label><input type="text" class="form-control" value="${p.label||''}" oninput="_tempPacks[${i}].label=this.value" placeholder="3 Tickets"></div>
        <div class="form-group" style="margin:0; display:flex; align-items:center; gap:.5rem; padding-top:1.5rem;">
          <input type="checkbox" ${p.bestValue ? 'checked' : ''} onchange="_tempPacks[${i}].bestValue=this.checked" style="accent-color:var(--color-accent); width:16px; height:16px;">
          <label class="form-label" style="margin:0; cursor:pointer;">Mejor valor</label>
        </div>
      </div>
    </div>
  `).join('');
}

function addPackRow() {
  _tempPacks.push({ id: generateId('pk'), qty: 1, price: 0, originalPrice: 0, label: '', discount: 0, bestValue: false });
  renderPacks();
}

function removePack(i) {
  _tempPacks.splice(i, 1);
  renderPacks();
}

function saveRaffle() {
  const title = document.getElementById('rf_title')?.value?.trim();
  if (!title) { showToast('El título del sorteo es obligatorio', 'warning'); return; }

  const raffle = {
    id: _editingRaffleId || generateId('r'),
    title,
    category: document.getElementById('rf_category')?.value || 'Otros',
    description: document.getElementById('rf_description')?.value || '',
    status: document.getElementById('rf_status')?.value || 'active',
    drawDate: document.getElementById('rf_drawDate')?.value || '',
    totalTickets: parseInt(document.getElementById('rf_totalTickets')?.value) || 1000,
    soldTickets: _editingRaffleId ? (db.getRaffle(_editingRaffleId)?.soldTickets || 0) : 0,
    imageEmoji: document.getElementById('rf_emoji')?.value || '🎁',
    image: null,
    featured: document.getElementById('rf_featured')?.checked || false,
    prizes: _tempPrizes,
    packs: _tempPacks,
    legalInfo: {
      organizer: document.getElementById('rf_organizer')?.value || '',
      rut: document.getElementById('rf_rut')?.value || '',
      notary: document.getElementById('rf_notary')?.value || '',
      certificate: document.getElementById('rf_certificate')?.value || '',
      salesPeriod: document.getElementById('rf_salesPeriod')?.value || ''
    }
  };

  db.saveRaffle(raffle);
  closeRaffleModal();
  showToast(_editingRaffleId ? 'Sorteo actualizado' : 'Sorteo creado', 'success');
  renderSorteos();
  renderDashboard();
}

// ─── Winner Modal ─────────────────────────────────────────────────────────────
function openWinnerModal() {
  document.getElementById('wn_name').value = '';
  document.getElementById('wn_location').value = '';
  document.getElementById('wn_prize').value = '';
  document.getElementById('wn_prizeValue').value = '';
  document.getElementById('wn_raffleTitle').value = '';
  document.getElementById('wn_drawDate').value = '';
  document.getElementById('wn_ticketNumber').value = '';
  document.getElementById('wn_emoji').value = '🏆';
  document.getElementById('wn_edition').value = '';
  document.getElementById('wn_videoUrl').value = '';
  document.getElementById('wn_verified').checked = true;
  document.getElementById('winnerModal').classList.add('open');
}

function closeWinnerModal() {
  document.getElementById('winnerModal').classList.remove('open');
}

function saveWinner() {
  const name = document.getElementById('wn_name')?.value?.trim();
  if (!name) { showToast('El nombre del ganador es obligatorio', 'warning'); return; }
  const winner = {
    id: generateId('w'),
    winnerName: name,
    winnerLocation: document.getElementById('wn_location')?.value || '',
    prize: document.getElementById('wn_prize')?.value || '',
    prizeValue: parseInt(document.getElementById('wn_prizeValue')?.value) || 0,
    raffleTitle: document.getElementById('wn_raffleTitle')?.value || '',
    drawDate: document.getElementById('wn_drawDate')?.value || new Date().toISOString().slice(0,10),
    ticketNumber: document.getElementById('wn_ticketNumber')?.value || '',
    emoji: document.getElementById('wn_emoji')?.value || '🏆',
    edition: document.getElementById('wn_edition')?.value || '',
    videoUrl: document.getElementById('wn_videoUrl')?.value || '#',
    notaryDoc: '#',
    verified: document.getElementById('wn_verified')?.checked || false
  };
  db.addWinner(winner);
  closeWinnerModal();
  showToast('Ganador agregado correctamente', 'success');
  renderGanadores();
  renderDashboard();
}

// ─── Modal close on backdrop click ───────────────────────────────────────────
['raffleModal', 'winnerModal'].forEach(id => {
  const el = document.getElementById(id);
  el?.addEventListener('click', e => { if (e.target === el) el.classList.remove('open'); });
});

// ─── Helpers (duplicated for standalone admin page) ───────────────────────────
function formatPrice(n) { return '$' + Number(n).toLocaleString('es-CL'); }
function formatDate(dateStr) {
  const d = new Date(dateStr);
  return d.toLocaleDateString('es-CL', { day: '2-digit', month: 'long', year: 'numeric' });
}
function percentage(sold, total) { if (!total) return 0; return Math.min(100, Math.round((sold/total)*100)); }
