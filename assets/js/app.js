/**
 * SURTEADOS — Main Application JS
 * Powers index.html and shared UI components.
 */

// ─── Helpers ──────────────────────────────────────────────────────────────────
function escHtml(s) {
  return String(s)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;')
    .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

// ─── Ticket label helpers (configurable from admin settings) ─────────────────
function tLabel()  { return window.SURTEADOS_DATA?.settings?.ticketLabel       ?? 'imagen';  }
function tLabelP() { return window.SURTEADOS_DATA?.settings?.ticketLabelPlural ?? 'imagenes'; }
function tLabelUp() { const l = tLabel(); return l.charAt(0).toUpperCase() + l.slice(1); }

function getRaffleClosureInfo(drawDate) {
  if (!drawDate) return { salesClosed: false, remainingText: '' };
  // Normaliza formato MySQL (YYYY-MM-DD HH:mm:ss) para parse confiable en navegadores.
  const normalized = String(drawDate).trim().replace(' ', 'T');
  const draw = new Date(normalized);
  if (Number.isNaN(draw.getTime())) return { salesClosed: false, remainingText: '' };
  const closesAt = new Date(draw.getTime() - 24 * 60 * 60 * 1000);
  const now = new Date();
  const salesClosed = now >= closesAt;
  if (!salesClosed) return { salesClosed: false, remainingText: '' };

  const diff = Math.max(0, draw.getTime() - now.getTime());
  const days = Math.floor(diff / 86400000);
  const hours = Math.floor((diff % 86400000) / 3600000);
  const minutes = Math.floor((diff % 3600000) / 60000);
  const parts = [];
  if (days > 0) parts.push(`${days}d`);
  if (hours > 0) parts.push(`${hours}h`);
  if (minutes > 0 && parts.length < 2) parts.push(`${minutes}m`);
  return { salesClosed: true, remainingText: parts.join(' ') || 'menos de 1 minuto' };
}

function raffleClosedMessage(drawDate) {
  const info = getRaffleClosureInfo(drawDate);
  if (!info.salesClosed) return '';
  return `Se ha cerrado la compra de ${tLabelP()}, faltan ${info.remainingText} para que puedas ganar.`;
}

// ─── Apply server theme & logo (before any render) ───────────────────────────
(function() {
  const settings = window.SURTEADOS_DATA?.settings;
  const serverTheme = settings?.theme || {};
  if (Object.keys(serverTheme).length) applyTheme(serverTheme);

  // Local picker changes must survive refresh, even when server theme exists.
  let localTheme = null;
  try {
    const raw = localStorage.getItem('surteados_db');
    const parsed = raw ? JSON.parse(raw) : null;
    if (parsed?.settings?.theme && typeof parsed.settings.theme === 'object') {
      localTheme = parsed.settings.theme;
    }
  } catch (_) {}
  if (localTheme) {
    applyTheme({ ...serverTheme, ...localTheme });
  }

  if (settings?.logo) {
    document.querySelectorAll('.navbar-logo').forEach(el => {
      el.innerHTML = `<img src="${escHtml(settings.logo)}" alt="Logo" class="navbar-logo-img">`;
    });
  }
  if (settings?.siteName) {
    document.querySelectorAll('.brand').forEach(el => {
      if (!el.querySelector('em')) el.textContent = settings.siteName;
    });
  }
})();

// ─── Hero Slider ──────────────────────────────────────────────────────────────
(function() {
  const settings = window.SURTEADOS_DATA?.settings;
  if (!settings?.heroSliderEnabled) return;
  const slides = (settings.heroSlides || []).filter(s => s.active !== false);
  if (!slides.length) return;

  const wrap  = document.getElementById('heroSliderWrap');
  const track = document.getElementById('hsTrack');
  const dotsEl= document.getElementById('hsDots');
  const hero  = document.getElementById('hero');
  if (!wrap || !track) return;

  if (hero) hero.hidden = true;
  wrap.classList.remove('hidden');

  function slideStyle(s) {
    if (s.bgType === 'image' && s.bgImage) {
      return `background:url('${escHtml(s.bgImage)}') center/cover no-repeat;`;
    }
    const c1 = s.bgColor1 || 'var(--color-primary-dark)';
    const c2 = s.bgColor2 || '#0d0520';
    return `background:linear-gradient(135deg,${c1},${c2});`;
  }

  track.innerHTML = slides.map((s, i) => `
    <div class="hs-slide" style="${slideStyle(s)}">
      ${s.bgType === 'image' ? '<div class="hs-overlay"></div>' : ''}
      <div class="hs-slide-inner">
        ${s.badge ? `<div class="badge mb-3">${escHtml(s.badge)}</div>` : ''}
        <h1>${escHtml(s.title || '')}</h1>
        ${s.subtitle ? `<p>${escHtml(s.subtitle)}</p>` : ''}
        ${s.ctaLink && s.ctaText ? `<div class="mt-3"><a href="${escHtml(s.ctaLink)}" class="btn btn-primary btn-lg">${escHtml(s.ctaText)}</a></div>` : ''}
      </div>
    </div>`).join('');

  if (dotsEl) {
    dotsEl.innerHTML = slides.map((_, i) =>
      `<button class="hs-dot${i===0?' active':''}" data-idx="${i}" aria-label="Slide ${i+1}"></button>`
    ).join('');
  }

  let current = 0, timer;
  function goTo(idx) {
    current = ((idx % slides.length) + slides.length) % slides.length;
    track.style.transform = `translateX(-${current * 100}%)`;
    dotsEl?.querySelectorAll('.hs-dot').forEach((d, i) => d.classList.toggle('active', i === current));
  }
  function startAuto() {
    clearInterval(timer);
    timer = setInterval(() => goTo(current + 1), 5500);
  }
  document.getElementById('hsPrev')?.addEventListener('click', () => { goTo(current - 1); startAuto(); });
  document.getElementById('hsNext')?.addEventListener('click', () => { goTo(current + 1); startAuto(); });
  dotsEl?.querySelectorAll('.hs-dot').forEach(d =>
    d.addEventListener('click', () => { goTo(+d.dataset.idx); startAuto(); })
  );
  let tx = 0;
  wrap.addEventListener('touchstart', e => { tx = e.touches[0].clientX; }, { passive: true });
  wrap.addEventListener('touchend', e => {
    const dx = e.changedTouches[0].clientX - tx;
    if (Math.abs(dx) > 50) { goTo(current + (dx < 0 ? 1 : -1)); startAuto(); }
  });
  startAuto();
})();

// ─── Navbar scroll effect ─────────────────────────────────────────────────────
(function() {
  const nav = document.getElementById('navbar');
  if (!nav) return;
  window.addEventListener('scroll', () => {
    nav.classList.toggle('scrolled', window.scrollY > 20);
  });

  // Mobile toggle
  const toggle = document.getElementById('mobileToggle');
  const mobileNav = document.getElementById('mobileNav');
  if (toggle && mobileNav) {
    toggle.addEventListener('click', () => {
      mobileNav.classList.toggle('open');
    });

    mobileNav.querySelectorAll('a').forEach(link => {
      link.addEventListener('click', () => {
        mobileNav.classList.remove('open');
      });
    });

    document.addEventListener('click', (event) => {
      if (!mobileNav.classList.contains('open')) return;
      if (mobileNav.contains(event.target) || toggle.contains(event.target)) return;
      mobileNav.classList.remove('open');
    });
  }
})();

// ─── Particles ────────────────────────────────────────────────────────────────
(function() {
  const container = document.getElementById('particles');
  if (!container) return;
  const colors = ['rgba(124,58,237,0.5)', 'rgba(245,158,11,0.4)', 'rgba(59,130,246,0.4)', 'rgba(255,255,255,0.3)'];
  for (let i = 0; i < 20; i++) {
    const p = document.createElement('div');
    p.className = 'particle';
    const size = Math.random() * 6 + 2;
    p.style.cssText = `
      width:${size}px; height:${size}px;
      background:${colors[Math.floor(Math.random()*colors.length)]};
      left:${Math.random()*100}%;
      animation-duration:${Math.random()*15+10}s;
      animation-delay:${Math.random()*15}s;
    `;
    container.appendChild(p);
  }
})();

// ─── Hero Section ────────────────────────────────────────────────────────────
(function() {
  const raffles = db.getRaffles();
  const winners = db.getWinners();

  // Stats
  const statSorteos = document.getElementById('statTotalSorteos');
  const statTickets = document.getElementById('statTicketsVendidos');
  const statGanadores = document.getElementById('statGanadores');

  if (statSorteos) {
    const active = raffles.filter(r => r.status === 'active').length;
    animateCount(statSorteos, 0, active, 800);
  }
  if (statTickets) {
    const total = raffles.reduce((a, r) => a + (r.soldTickets || 0), 0);
    animateCount(statTickets, 0, total, 1200, '+');
  }
  if (statGanadores) {
    animateCount(statGanadores, 0, winners.length + 12, 1000, '+');
  }

  // Featured hero card
  const heroCard = document.getElementById('heroFeaturedCard');
  if (heroCard) {
    const featured = raffles.find(r => r.featured && r.status === 'active') || raffles.find(r => r.status === 'active');
    if (featured) {
      const timeLeft = getTimeLeft(featured.drawDate);

      heroCard.innerHTML = `
        <div class="prize-img-wrap">
          ${featured.image ? `<img src="${featured.image}" alt="${featured.title}">` : 
            `<div style="font-size:6rem;">${featured.imageEmoji || '🎁'}</div>`}
          <span class="prize-badge">📦 PREMIO PRINCIPAL</span>
        </div>
        <div class="hero-card-title">${featured.title}</div>
        <div class="countdown-row" id="heroCountdown">
          <div class="countdown-item"><div class="count-num" id="cd-days">${pad(timeLeft.days)}</div><div class="count-label">Días</div></div>
          <div class="countdown-item"><div class="count-num" id="cd-hours">${pad(timeLeft.hours)}</div><div class="count-label">Horas</div></div>
          <div class="countdown-item"><div class="count-num" id="cd-mins">${pad(timeLeft.minutes)}</div><div class="count-label">Min</div></div>
          <div class="countdown-item"><div class="count-num" id="cd-secs">${pad(timeLeft.seconds)}</div><div class="count-label">Seg</div></div>
        </div>
          <button class="btn btn-accent btn-block btn-lg" style="font-weight:800;" onclick="openPurchaseModal('${featured.id}')">
            🎟️ Comprar ${tLabelUp()} — Desde ${formatPrice(Math.min(...featured.packs.map(p => p.price)))}
        </button>
      `;

      // Countdown
      startCountdown(featured.drawDate, 'cd-days', 'cd-hours', 'cd-mins', 'cd-secs');
    }
  }
})();

// ─── Raffles Grid ────────────────────────────────────────────────────────────
(function() {
  const grid = document.getElementById('rafflesGrid');
  if (!grid) return;

  const raffles = db.getRaffles();
  let currentFilter = 'active';

  const filterBtns = document.querySelectorAll('.tab-filter');
  filterBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      filterBtns.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      currentFilter = btn.dataset.filter;
      renderGrid();
    });
  });

  function renderGrid() {
    const filtered = raffles.filter(r => r.status === currentFilter);
    if (filtered.length === 0) {
      grid.innerHTML = `<div class="empty-state" style="grid-column:1/-1;"><div class="empty-icon">🎟️</div><p>No hay sorteos en esta categoría.</p></div>`;
      return;
    }
    grid.innerHTML = filtered.map(r => buildRaffleCard(r)).join('');
  }

  renderGrid();
})();

// ─── Winners Preview ─────────────────────────────────────────────────────────
(function() {
  const grid = document.getElementById('winnersPreview');
  if (!grid) return;
  const winners = db.getWinners().slice(0, 3);
  if (winners.length === 0) {
    grid.innerHTML = `<div class="empty-state" style="grid-column:1/-1;"><div class="empty-icon">🏆</div><p>Próximamente nuestros ganadores.</p></div>`;
    return;
  }
  grid.innerHTML = winners.map(w => buildWinnerCard(w)).join('');
})();

// ─── Theme Picker ─────────────────────────────────────────────────────────────
(function() {
  const PRESETS = {
    purple:  { primary: '#7c3aed', primaryLight: '#9d5cf6', primaryDark: '#5b21b6', accent: '#f59e0b', accentLight: '#fbbf24', accentDark: '#d97706' },
    blue:    { primary: '#2563eb', primaryLight: '#3b82f6', primaryDark: '#1e40af', accent: '#06b6d4', accentLight: '#22d3ee', accentDark: '#0891b2' },
    emerald: { primary: '#059669', primaryLight: '#10b981', primaryDark: '#065f46', accent: '#f59e0b', accentLight: '#fbbf24', accentDark: '#b45309' },
    rose:    { primary: '#e11d48', primaryLight: '#f43f5e', primaryDark: '#9f1239', accent: '#f59e0b', accentLight: '#fbbf24', accentDark: '#b45309' },
    indigo:  { primary: '#4338ca', primaryLight: '#6366f1', primaryDark: '#3730a3', accent: '#c026d3', accentLight: '#d946ef', accentDark: '#a21caf' },
    teal:    { primary: '#0d9488', primaryLight: '#14b8a6', primaryDark: '#0f766e', accent: '#f97316', accentLight: '#fb923c', accentDark: '#c2410c' }
  };

  const toggle = document.getElementById('themeToggle');
  const panel = document.getElementById('themePanel');
  const presets = document.querySelectorAll('.preset-btn');
  const colorPrimary = document.getElementById('colorPrimary');
  const colorPrimaryHex = document.getElementById('colorPrimaryHex');
  const colorAccent = document.getElementById('colorAccent');
  const colorAccentHex = document.getElementById('colorAccentHex');
  const applyBtn = document.getElementById('applyThemeBtn');
  const resetBtn = document.getElementById('resetThemeBtn');

  if (!toggle) return;

  // Inject extra controls once so all pages get the same advanced theme editor.
  if (panel && !document.getElementById('colorHeader')) {
    const extra = document.createElement('div');
    extra.innerHTML = `
      <div class="color-input-group" style="margin-top:.5rem;"><label>Color header</label><div class="color-input-row"><input type="color" id="colorHeader" value="#621c85"><input type="text" class="form-control" id="colorHeaderHex" value="#621c85"></div></div>
      <div class="color-input-group"><label>Color letras menú</label><div class="color-input-row"><input type="color" id="colorMenuText" value="#f3e8ff"><input type="text" class="form-control" id="colorMenuTextHex" value="#f3e8ff"></div></div>
      <div class="color-input-group"><label>Color fondo opcional</label><div class="color-input-row"><input type="color" id="colorPageBg" value="#621c85"><input type="text" class="form-control" id="colorPageBgHex" value="#621c85"></div></div>
      <label style="display:flex;align-items:center;gap:.45rem;font-size:.78rem;color:var(--text-secondary);margin-top:.35rem;"><input type="checkbox" id="togglePageBg"> Mostrar fondo personalizado</label>
    `;
    const applyButton = document.getElementById('applyThemeBtn');
    if (applyButton && applyButton.parentElement) {
      applyButton.parentElement.insertBefore(extra, applyButton);
    } else {
      panel.appendChild(extra);
    }
  }

  const colorHeader = document.getElementById('colorHeader');
  const colorHeaderHex = document.getElementById('colorHeaderHex');
  const colorMenuText = document.getElementById('colorMenuText');
  const colorMenuTextHex = document.getElementById('colorMenuTextHex');
  const colorPageBg = document.getElementById('colorPageBg');
  const colorPageBgHex = document.getElementById('colorPageBgHex');
  const togglePageBg = document.getElementById('togglePageBg');

  const currentTheme = window.SURTEADOS_DATA?.settings?.theme || db.getSettings()?.theme || {};
  if (colorHeader) colorHeader.value = currentTheme.headerBg || '#621c85';
  if (colorHeaderHex) colorHeaderHex.value = colorHeader?.value || '#621c85';
  if (colorMenuText) colorMenuText.value = currentTheme.menuTextColor || '#f3e8ff';
  if (colorMenuTextHex) colorMenuTextHex.value = colorMenuText?.value || '#f3e8ff';
  if (colorPageBg) colorPageBg.value = currentTheme.pageBgColor || '#621c85';
  if (colorPageBgHex) colorPageBgHex.value = colorPageBg?.value || '#621c85';
  if (togglePageBg) togglePageBg.checked = currentTheme.pageBgEnabled === true || currentTheme.pageBgEnabled === 1 || currentTheme.pageBgEnabled === '1';

  toggle.addEventListener('click', () => panel.classList.toggle('open'));
  document.addEventListener('click', e => {
    const picker = document.getElementById('themePicker');
    if (picker && !picker.contains(e.target)) panel.classList.remove('open');
  });

  presets.forEach(btn => {
    btn.addEventListener('click', () => {
      presets.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      const preset = PRESETS[btn.dataset.preset];
      if (preset) {
        colorPrimary.value = preset.primary;
        colorPrimaryHex.value = preset.primary;
        colorAccent.value = preset.accent;
        colorAccentHex.value = preset.accent;
        const themed = {
          ...preset,
          headerBg: colorHeader?.value || '#621c85',
          menuTextColor: colorMenuText?.value || '#f3e8ff',
          pageBgColor: colorPageBg?.value || '#621c85',
          pageBgEnabled: !!togglePageBg?.checked,
        };
        applyTheme(themed);
        db.saveTheme(themed);
      }
    });
  });

  function syncColor(colorInput, hexInput) {
    colorInput.addEventListener('input', () => { hexInput.value = colorInput.value; });
    hexInput.addEventListener('input', () => {
      if (/^#[0-9a-f]{6}$/i.test(hexInput.value)) colorInput.value = hexInput.value;
    });
  }
  if (colorPrimary) syncColor(colorPrimary, colorPrimaryHex);
  if (colorAccent) syncColor(colorAccent, colorAccentHex);
  if (colorHeader) syncColor(colorHeader, colorHeaderHex);
  if (colorMenuText) syncColor(colorMenuText, colorMenuTextHex);
  if (colorPageBg) syncColor(colorPageBg, colorPageBgHex);

  if (applyBtn) {
    applyBtn.addEventListener('click', () => {
      const p = colorPrimary?.value || '#7c3aed';
      const a = colorAccent?.value || '#f59e0b';
      const theme = {
        primary: p,
        primaryLight: lightenColor(p, 0.2),
        primaryDark: darkenColor(p, 0.2),
        accent: a,
        accentLight: lightenColor(a, 0.15),
        accentDark: darkenColor(a, 0.15),
        headerBg: colorHeader?.value || '#621c85',
        menuTextColor: colorMenuText?.value || '#f3e8ff',
        pageBgColor: colorPageBg?.value || '#621c85',
        pageBgEnabled: !!togglePageBg?.checked,
      };
      applyTheme(theme);
      db.saveTheme(theme);
      showToast('Tema aplicado correctamente', 'success');
    });
  }

  if (resetBtn) {
    resetBtn.addEventListener('click', () => {
      const def = {
        ...PRESETS.purple,
        headerBg: '#621c85',
        menuTextColor: '#f3e8ff',
        pageBgColor: '#621c85',
        pageBgEnabled: false,
      };
      applyTheme(def);
      db.saveTheme(def);
      if (colorPrimary) colorPrimary.value = def.primary;
      if (colorPrimaryHex) colorPrimaryHex.value = def.primary;
      if (colorAccent) colorAccent.value = def.accent;
      if (colorAccentHex) colorAccentHex.value = def.accent;
      if (colorHeader) colorHeader.value = def.headerBg;
      if (colorHeaderHex) colorHeaderHex.value = def.headerBg;
      if (colorMenuText) colorMenuText.value = def.menuTextColor;
      if (colorMenuTextHex) colorMenuTextHex.value = def.menuTextColor;
      if (colorPageBg) colorPageBg.value = def.pageBgColor;
      if (colorPageBgHex) colorPageBgHex.value = def.pageBgColor;
      if (togglePageBg) togglePageBg.checked = false;
      presets.forEach(b => b.classList.toggle('active', b.dataset.preset === 'purple'));
      showToast('Tema restaurado', 'info');
    });
  }
})();

// ─── Cart (multi-raffle) ──────────────────────────────────────────────────────
const CART_KEY = 'surteados_cart';

const _cart = {
  /** @returns {{ raffleId:string, packId:string, raffleTitle:string, packLabel:string, price:number }[]} */
  load() {
    try { return JSON.parse(localStorage.getItem(CART_KEY) || '[]'); } catch { return []; }
  },
  save(items) { localStorage.setItem(CART_KEY, JSON.stringify(items)); },
  clear()     { localStorage.removeItem(CART_KEY); },
  add(raffleId, packId, raffleTitle, packLabel, price, imageUrl = '') {
    const items = this.load();
    // Replace if same raffleId already in cart
    const idx = items.findIndex(i => i.raffleId === raffleId);
    if (idx >= 0) items[idx] = { raffleId, packId, raffleTitle, packLabel, price, imageUrl };
    else          items.push({ raffleId, packId, raffleTitle, packLabel, price, imageUrl });
    this.save(items);
  },
  remove(raffleId) {
    this.save(this.load().filter(i => i.raffleId !== raffleId));
  },
  total() { return this.load().reduce((s, i) => s + i.price, 0); },
  count() { return this.load().length; },
};

let _checkoutFromCart = false;

function renderHowParticipate() {
  const wrap = document.getElementById('howParticipateSection');
  if (!wrap) return;

  const activeRaffles = db.getRaffles().filter(r => r.status === 'active');
  const featured = activeRaffles.find(r => r.featured) || activeRaffles[0];
  const items = _cart.load();

  const titleEl = document.getElementById('hpFeaturedTitle');
  const priceEl = document.getElementById('hpPriceFrom');
  const drawEl = document.getElementById('hpDrawDate');
  const cartEl = document.getElementById('hpCartCount');

  if (titleEl) titleEl.textContent = featured?.title || 'Sin sorteos activos';
  if (priceEl) {
    const min = featured?.packs?.length ? Math.min(...featured.packs.map(p => p.price)) : 0;
    priceEl.textContent = min ? formatPrice(min) : '—';
  }
  if (drawEl) drawEl.textContent = featured?.drawDate ? formatDateTime(featured.drawDate) : 'Próximamente';
  if (cartEl) cartEl.textContent = `${items.length} selección${items.length === 1 ? '' : 'es'}`;

  // Step state: 1 = no selection yet, 2 = has cart selections.
  const currentStep = items.length > 0 ? 2 : 1;
  const steps = wrap.querySelectorAll('.hp-step');
  steps.forEach(step => {
    const num = Number(step.getAttribute('data-step') || '0');
    step.classList.remove('is-done', 'is-active');
    if (num < currentStep) step.classList.add('is-done');
    else if (num === currentStep) step.classList.add('is-active');
  });

  const fill = document.getElementById('hpProgressFill');
  const progressText = document.getElementById('hpProgressText');
  const percent = currentStep === 1 ? 33 : 66;
  if (fill) fill.style.width = `${percent}%`;
  if (progressText) {
    progressText.textContent = currentStep === 1
      ? 'Paso actual: Elige tu primer sorteo para comenzar.'
      : 'Vas muy bien: ya tienes selecciones en tu carrito. Solo falta confirmar y pagar.';
  }
}

function renderCartDrawerPanel() {
  const countEl = document.getElementById('cartCountNav');
  const bodyEl = document.getElementById('cartDrawerBody');
  const totalEl = document.getElementById('cartDrawerTotal');
  const checkoutBtn = document.getElementById('cartGoCheckoutBtn');
  const items = _cart.load();

  if (countEl) countEl.textContent = String(items.length);
  const fabBadgeEl = document.getElementById('cartFabCount');
  if (fabBadgeEl) {
    fabBadgeEl.textContent = String(items.length);
    fabBadgeEl.style.display = items.length > 0 ? 'flex' : 'none';
  }
  if (totalEl) totalEl.textContent = formatPrice(_cart.total());
  renderHowParticipate();

  if (!bodyEl) return;

  if (!items.length) {
    bodyEl.innerHTML = '<p class="text-sm text-muted">Tu carro está vacío.</p>';
    if (checkoutBtn) checkoutBtn.disabled = true;
    return;
  }

  bodyEl.innerHTML = items.map(i => {
    const thumb = i.imageUrl || db.getRaffle(i.raffleId)?.image || '';
    return `
    <div class="cart-line">
      ${thumb ? `<img class="cart-line-thumb" src="${escHtml(thumb)}" alt="${escHtml(i.raffleTitle)}" loading="lazy">` : `<div class="cart-line-thumb cart-line-thumb--empty"></div>`}
      <div class="cart-line-info">
        <div class="cart-line-title">${escHtml(i.raffleTitle)}</div>
        <div class="cart-line-sub">${escHtml(i.packLabel)}</div>
        <div class="cart-line-row">
          <strong class="text-white">${formatPrice(i.price)}</strong>
          <button class="btn btn-ghost btn-sm" onclick="removeFromCartAndRender('${i.raffleId}')">Quitar</button>
        </div>
      </div>
    </div>`;
  }).join('');

  if (checkoutBtn) checkoutBtn.disabled = false;
}

function openCartDrawer() {
  renderCartDrawerPanel();
  const overlay = document.getElementById('cartOverlay');
  const drawer  = document.getElementById('cartDrawer');
  overlay?.classList.add('open');
  drawer?.classList.add('open');
  if (overlay) overlay.style.display = 'block';
  if (drawer) drawer.style.transform = 'translateX(0)';
  document.body.style.overflow = 'hidden';
}

function closeCartDrawer() {
  const overlay = document.getElementById('cartOverlay');
  const drawer  = document.getElementById('cartDrawer');
  overlay?.classList.remove('open');
  drawer?.classList.remove('open');
  if (overlay) overlay.style.display = 'none';
  if (drawer) drawer.style.transform = 'translateX(110%)';
  document.body.style.overflow = '';
}

function removeFromCartAndRender(raffleId) {
  _cart.remove(raffleId);
  renderCartDrawerPanel();
}

function goToCheckoutFromCart() {
  const items = _cart.load();
  if (!items.length) {
    showToast('Tu carro está vacío', 'warning');
    return;
  }
  _checkoutFromCart = true;
  closeCartDrawer();
  updatePurchaseStep(2);
  document.getElementById('modalTitle').textContent = 'Checkout del carrito';
  document.getElementById('purchaseModal')?.classList.add('open');
}

(function initCartUI() {
  renderCartDrawerPanel();
  renderHowParticipate();
})();

// ─── Purchase Modal ───────────────────────────────────────────────────────────
let _purchaseState = { raffleId: null, pack: null, currentStep: 1 };

function openPurchaseModal(raffleId) {
  const raffle = db.getRaffle(raffleId);
  if (!raffle) return;
  if (raffle.status !== 'active') {
    showToast('Este sorteo no está activo actualmente.', 'warning');
    return;
  }
  const closure = getRaffleClosureInfo(raffle.drawDate);
  if (closure.salesClosed) {
    showToast(raffleClosedMessage(raffle.drawDate), 'warning', 6000);
    return;
  }

  _purchaseState = { raffleId, pack: null, currentStep: 1 };

  const modal = document.getElementById('purchaseModal');
  const title = document.getElementById('modalTitle');
  if (title) title.textContent = raffle.title;

  // Render packs
  const packsGrid = document.getElementById('modalPacksGrid');
  if (packsGrid) {
    const sortedPacks = [...raffle.packs].sort((a, b) => (a.qty - b.qty) || (a.price - b.price));
    packsGrid.innerHTML = sortedPacks.map(p => `
      <div class="pack-card${p.bestValue ? ' best-value' : ''}" onclick="selectPack('${p.id}', '${raffleId}')" data-pack="${p.id}">
        <div class="pack-qty">${p.qty}</div>
          <div class="pack-qty-label">${p.qty > 1 ? tLabelP() : tLabel()}</div>
        ${p.originalPrice > p.price ? `<div class="pack-price-original">${formatPrice(p.originalPrice)}</div>` : ''}
        <div class="pack-price">${formatPrice(p.price)}</div>
        ${p.discount ? `<div class="pack-discount">-${p.discount}% OFF</div>` : ''}
      </div>
    `).join('');
  }

  // Restore previously selected pack for this raffle from cart
  const saved = _cart.load().find(i => i.raffleId === raffleId);
  if (saved) {
    const restoredPack = raffle.packs.find(p => p.id === saved.packId);
    if (restoredPack) {
      _purchaseState.pack = restoredPack;
      packsGrid?.querySelectorAll('.pack-card').forEach(c => {
        c.classList.toggle('selected', c.dataset.pack === restoredPack.id);
      });
      const lbl = document.getElementById('selectedPackLabel');
      if (lbl) lbl.textContent = `${restoredPack.label} — ${formatPrice(restoredPack.price)}`;
      const btn = document.getElementById('step1Next');
      if (btn) btn.disabled = false;
      const addMore = document.getElementById('step1AddMore');
      if (addMore) addMore.disabled = false;
    }
  }

  updatePurchaseStep(1);
  modal.classList.add('open');
}

function selectPack(packId, raffleId) {
  const raffle = db.getRaffle(raffleId);
  if (!raffle) return;
  const pack = raffle.packs.find(p => p.id === packId);
  if (!pack) return;

  _purchaseState.pack = pack;
  _purchaseState.raffleId = raffleId;

  document.querySelectorAll('#modalPacksGrid .pack-card').forEach(c => {
    c.classList.toggle('selected', c.dataset.pack === packId);
  });

  const label = document.getElementById('selectedPackLabel');
  if (label) label.textContent = `${pack.label} — ${formatPrice(pack.price)}`;

  const btn = document.getElementById('step1Next');
  if (btn) btn.disabled = false;
  const addMore = document.getElementById('step1AddMore');
  if (addMore) addMore.disabled = false;
}

function updatePurchaseStep(step) {
  _purchaseState.currentStep = step;

  document.querySelectorAll('.step-panel').forEach(el => el.classList.remove('active'));
  const panelId = step === '1b' ? 'step1b' : 'step' + step;
  const panel = document.getElementById(panelId);
  if (panel) panel.classList.add('active');

  // Steps indicator only maps to numeric steps
  const numericStep = step === '1b' ? 1 : step;
  document.querySelectorAll('.purchase-step').forEach(el => {
    const s = parseInt(el.dataset.step);
    el.classList.remove('active', 'done');
    if (s < numericStep) el.classList.add('done');
    else if (s === numericStep) el.classList.add('active');
    if (s < numericStep) el.querySelector('.ps-num').textContent = '✓';
    else el.querySelector('.ps-num').textContent = s;
  });
}

/** Render the "more raffles" grid in step1b */
function renderMoreRaffles() {
  const grid = document.getElementById('moreRafflesGrid');
  if (!grid) return;

  const currentId = _purchaseState.raffleId;
  const raffles   = db.getRaffles().filter(r => r.status === 'active' && r.id !== currentId);
  const cartItems = _cart.load();

  if (raffles.length === 0) {
    grid.innerHTML = `<div style="grid-column:1/-1;text-align:center;color:var(--text-muted);padding:1.5rem;">No hay otros sorteos activos</div>`;
    updateCartBar();
    return;
  }

  grid.innerHTML = raffles.map(r => {
    const inCart   = cartItems.find(i => i.raffleId === r.id);
    const minPrice = r.packs?.length ? Math.min(...r.packs.map(p => p.price)) : 0;
    return `
    <div class="card" style="padding:.7rem;cursor:pointer;transition:all .2s;${inCart ? 'border-color:var(--color-primary);' : ''}"
         onclick="openMoreRaffle('${r.id}')">
      <div style="font-size:.58rem;letter-spacing:1.5px;color:var(--text-muted);text-transform:uppercase;margin-bottom:.2rem;">${r.category}</div>
      <div style="font-weight:700;font-size:.82rem;color:var(--text-inv);line-height:1.3;margin-bottom:.3rem;">${r.title}</div>
      <div style="font-size:.75rem;color:var(--color-accent);font-weight:600;">Desde ${formatPrice(minPrice)}</div>
      ${inCart ? `<div style="font-size:.65rem;color:var(--color-primary);margin-top:.3rem;font-weight:600;">✓ ${inCart.packLabel}</div>` : ''}
    </div>`;
  }).join('');

  updateCartBar();
}

/** Open a secondary raffle pack selector inside step1b */
function openMoreRaffle(raffleId) {
  const raffle = db.getRaffle(raffleId);
  if (!raffle) return;

  const grid = document.getElementById('moreRafflesGrid');
  if (!grid) return;

  const cartItem = _cart.load().find(i => i.raffleId === raffleId);
  const sortedPacks = [...raffle.packs].sort((a, b) => (a.qty - b.qty) || (a.price - b.price));

  grid.innerHTML = `
    <div style="grid-column:1/-1;">
      <button class="btn btn-ghost btn-sm" onclick="renderMoreRaffles()" style="margin-bottom:.75rem;">← Todos los sorteos</button>
      <div style="font-weight:700;color:var(--text-inv);font-size:.95rem;margin-bottom:.75rem;">${raffle.title}</div>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(90px,1fr));gap:.5rem;">
        ${sortedPacks.map(p => `
          <div class="pack-card${p.bestValue ? ' best-value' : ''}${cartItem?.packId === p.id ? ' selected' : ''}"
               style="padding:.55rem .4rem;font-size:.85em;"
               onclick="addRaffleToCart('${raffleId}','${p.id}')"
               data-pack="${p.id}">
            <div class="pack-qty">${p.qty}</div>
            <div class="pack-qty-label">${p.qty > 1 ? tLabelP() : tLabel()}</div>
            ${p.originalPrice > p.price ? `<div class="pack-price-original">${formatPrice(p.originalPrice)}</div>` : ''}
            <div class="pack-price">${formatPrice(p.price)}</div>
            ${p.discount ? `<div class="pack-discount">-${p.discount}%</div>` : ''}
          </div>`).join('')}
      </div>
      ${cartItem ? `<button class="btn btn-ghost btn-sm" style="margin-top:.65rem;color:#ef4444;" onclick="removeRaffleFromCart('${raffleId}')">🗑 Quitar del carrito</button>` : ''}
    </div>`;
}

function addRaffleToCart(raffleId, packId) {
  const raffle = db.getRaffle(raffleId);
  if (!raffle) return;
  const pack = raffle.packs.find(p => p.id === packId);
  if (!pack) return;
  _cart.add(raffleId, packId, raffle.title, pack.label, pack.price, raffle.image || '');
  showToast(`✓ ${raffle.title} agregado al carrito`, 'success', 2000);
  openMoreRaffle(raffleId); // re-render to show selected state
  updateCartBar();
  renderCartDrawerPanel();
}

function removeRaffleFromCart(raffleId) {
  _cart.remove(raffleId);
  renderMoreRaffles();
  renderCartDrawerPanel();
}

function updateCartBar() {
  const countEl = document.getElementById('cartCount');
  const totalEl = document.getElementById('cartTotal');
  const items   = _cart.load();
  // Add current raffle's pack to the count if set
  const currentInCart = _purchaseState.pack
    ? items.find(i => i.raffleId === _purchaseState.raffleId)
    : null;
  const total = _cart.total() + (currentInCart ? 0 : (_purchaseState.pack?.price || 0));
  const count = _cart.count() + (currentInCart ? 0 : (_purchaseState.pack ? 1 : 0));

  if (countEl) countEl.textContent = count;
  if (totalEl) totalEl.textContent = formatPrice(total);
}

(function() {
  const modal = document.getElementById('purchaseModal');
  const modalClose = document.getElementById('modalClose');
  const closeAfterPurchase = document.getElementById('closeAfterPurchase');

  function closeModal() { modal.classList.remove('open'); }
  if (modalClose) modalClose.addEventListener('click', closeModal);
  if (closeAfterPurchase) closeAfterPurchase.addEventListener('click', () => {
    _cart.clear();
    renderCartDrawerPanel();
    _checkoutFromCart = false;
    closeModal();
  });
  modal?.addEventListener('click', e => { if (e.target === modal) closeModal(); });

  // Step 1 → pay directly
  document.getElementById('step1Next')?.addEventListener('click', () => {
    if (!_purchaseState.pack) return;
    // Add current raffle to cart (replaces if already there)
    const raffle = db.getRaffle(_purchaseState.raffleId);
    _cart.add(_purchaseState.raffleId, _purchaseState.pack.id, raffle.title, _purchaseState.pack.label, _purchaseState.pack.price, raffle.image || '');
    renderCartDrawerPanel();
    _checkoutFromCart = false;
    updatePurchaseStep(2);
  });

  // Step 1 → cancel (close modal)
  document.getElementById('step1Cancel')?.addEventListener('click', closeModal);

  // Step 1 → add and continue shopping in sorteos.php
  document.getElementById('step1AddMore')?.addEventListener('click', () => {
    if (!_purchaseState.pack) return;
    const raffle = db.getRaffle(_purchaseState.raffleId);
    _cart.add(_purchaseState.raffleId, _purchaseState.pack.id, raffle.title, _purchaseState.pack.label, _purchaseState.pack.price, raffle.image || '');
    renderCartDrawerPanel();
    showToast('Pack agregado. Te llevamos a ver más sorteos.', 'success', 1800);
    setTimeout(() => {
      window.location.href = '/sorteos.php';
    }, 350);
  });

  // Step 1b → back to step 1
  document.getElementById('step1bBack')?.addEventListener('click', () => {
    updatePurchaseStep(1);
  });

  // Cart bar "Pagar ahora" button (in step1b)
  document.getElementById('cartPayBtn')?.addEventListener('click', () => {
    updatePurchaseStep(2);
  });

  // Step 2 → back
  document.getElementById('step2Back')?.addEventListener('click', () => {
    if (_checkoutFromCart) {
      document.getElementById('purchaseModal')?.classList.remove('open');
      openCartDrawer();
      return;
    }
    updatePurchaseStep(1);
  });

  // Step 2 policies panel inside the same modal
  const policiesLink = document.getElementById('buyerViewPoliciesLink');
  const policiesBackBtn = document.getElementById('buyerPoliciesBackBtn');
  const policiesPanel = document.getElementById('buyerPoliciesPanel');
  const step2 = document.getElementById('step2');
  const step2ReplaceableNodes = step2
    ? Array.from(step2.children).filter((node) => node.id !== 'buyerPoliciesPanel')
    : [];

  function showPoliciesPanel(show) {
    if (!policiesPanel) return;
    policiesPanel.style.display = show ? 'block' : 'none';
    step2ReplaceableNodes.forEach((node) => {
      node.style.display = show ? 'none' : '';
    });
    if (show) {
      policiesPanel.scrollTop = 0;
    }
  }

  policiesLink?.addEventListener('click', (e) => {
    e.preventDefault();
    showPoliciesPanel(true);
  });

  policiesBackBtn?.addEventListener('click', () => {
    showPoliciesPanel(false);
  });

  // RUT formatting helper in form
  const buyerRutEl = document.getElementById('buyerRut');
  buyerRutEl?.addEventListener('blur', () => {
    buyerRutEl.value = formatChileanRut(buyerRutEl.value);
  });

  // Step 2 → 3
  document.getElementById('step2Next')?.addEventListener('click', () => {
    const name         = document.getElementById('buyerName')?.value?.trim();
    const rutInput     = document.getElementById('buyerRut')?.value?.trim();
    const address      = document.getElementById('buyerAddress')?.value?.trim();
    const comuna       = document.getElementById('buyerComuna')?.value?.trim();
    const email        = document.getElementById('buyerEmail')?.value?.trim();
    const emailConfirm = document.getElementById('buyerEmailConfirm')?.value?.trim();
    const termsAccepted = !!document.getElementById('buyerTermsAccepted')?.checked;
    if (!name)  { showToast('Ingresa tu nombre completo', 'warning'); return; }
    if (!rutInput) { showToast('Ingresa tu RUT', 'warning'); return; }
    if (!address) { showToast('Ingresa tu dirección', 'warning'); return; }
    if (!comuna) { showToast('Ingresa tu comuna', 'warning'); return; }
    const rut = formatChileanRut(rutInput);
    if (!isValidChileanRut(rut)) { showToast('Ingresa un RUT chileno válido', 'warning'); return; }
    const rutEl = document.getElementById('buyerRut');
    if (rutEl) rutEl.value = rut;
    if (!email || !/^[^@]+@[^@]+\.[^@]+$/.test(email)) { showToast('Ingresa un correo válido', 'warning'); return; }
    if (email !== emailConfirm) { showToast('Los correos no coinciden', 'error'); return; }
    if (!termsAccepted) { showToast('Debes aceptar las politicas de compra', 'warning'); return; }

    // Build cart summary
    const items = _cart.load();
    const total = _cart.total();

    const summaryEl = document.getElementById('cartSummaryList');
    if (summaryEl) {
      summaryEl.innerHTML = items.map(i => `
        <div class="card" style="padding:.6rem .9rem;margin-bottom:.45rem;display:flex;justify-content:space-between;align-items:center;">
          <div>
            <div style="font-size:.75rem;font-weight:700;color:var(--text-inv);">${i.raffleTitle}</div>
            <div style="font-size:.68rem;color:var(--text-muted);">${i.packLabel}</div>
          </div>
          <div style="font-weight:700;color:var(--color-accent);font-size:.85rem;">${formatPrice(i.price)}</div>
        </div>`).join('');
    }

    const totalEl = document.getElementById('summaryTotal');
    const emailEl = document.getElementById('summaryEmail');
    if (totalEl) totalEl.textContent = formatPrice(total);
    if (emailEl) emailEl.textContent = email;

    updatePurchaseStep(3);
  });

  // Step 3 → back
  document.getElementById('step3Back')?.addEventListener('click', () => updatePurchaseStep(2));

  // Step 3 → Simulate payment (demo only)
  document.getElementById('step3SimulateBtn')?.addEventListener('click', async () => {
    const name  = document.getElementById('buyerName')?.value?.trim() || 'Demo';
    const rut   = formatChileanRut(document.getElementById('buyerRut')?.value?.trim() || '');
    const email = document.getElementById('buyerEmail')?.value?.trim() || 'demo@surteados.cl';
    const phone = document.getElementById('buyerPhone')?.value?.trim() || '';
    const address = document.getElementById('buyerAddress')?.value?.trim() || '';
    const comuna = document.getElementById('buyerComuna')?.value?.trim() || '';
    const items = _cart.load();
    if (!items.length) { showToast('Tu carrito está vacío', 'warning'); return; }
    if (!rut || !isValidChileanRut(rut)) { showToast('Ingresa un RUT chileno válido', 'warning'); return; }
    if (!address || !comuna) { showToast('Completa dirección y comuna', 'warning'); return; }

    const btn = document.getElementById('step3SimulateBtn');
    btn.disabled = true;
      btn.textContent = `⏳ Generando ${tLabelP()}…`;

    try {
      const base = window.location.pathname.replace(/\/index\.php.*|\/$/, '').replace(/\/[^/]+\.php.*/, '');
      const resp = await fetch(base + '/api/simulate_payment.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          items:      items.map(i => ({ raffleId: i.raffleId, packId: i.packId })),
          buyerName:  name,
          buyerRut:   rut,
          buyerEmail: email,
          buyerPhone: phone,
          buyerAddress: address,
          buyerComuna: comuna,
        }),
      });
      const json = await resp.json();
      if (!json.ok) throw new Error(json.error || 'Error en la simulación');

      // Update success step
      const subtitle = document.getElementById('step4Subtitle');
        if (subtitle) subtitle.textContent = `Hola ${name}, tus ${tLabelP()} han sido asignados. Revisa tu correo.`;

      // Update confirm summary
      const confirmEmail  = document.getElementById('confirmEmail');
      const confirmAmount = document.getElementById('confirmAmount');
      if (confirmEmail)  confirmEmail.textContent  = email;
      if (confirmAmount) confirmAmount.textContent = formatPrice(json.data.total);

      // Point download link to PDF ticket page
      const verLink = document.getElementById('verMisTicketsLink');
      if (verLink) {
        const base2 = window.location.pathname.replace(/\/index\.php.*|\/$/, '').replace(/\/[^/]+\.php.*/, '');
        verLink.href = base2 + `/api/ticket_pdf.php?orderId=${encodeURIComponent(json.data.orderId)}&email=${encodeURIComponent(email)}`;
        verLink.textContent = '📄 Ver mis imágenes compradas';
        verLink.target = '_blank';
        verLink.rel = 'noopener';
      }

      _cart.clear();
      renderCartDrawerPanel();
      updatePurchaseStep(4);

      if (json.data.mailSent) showToast('📧 Correo de confirmación enviado', 'success', 3500);
      else showToast(`✅ ${tLabelP()} generados (correo no disponible en local)`, 'success', 3500);

    } catch (err) {
      showToast('❌ ' + err.message, 'error', 6000);
    } finally {
      btn.disabled = false;
      btn.textContent = '⚡ Simular pago exitoso (sólo demo)';
    }
  });

  // Step 3 → Pay
  document.getElementById('step3Pay')?.addEventListener('click', async () => {
    const name   = document.getElementById('buyerName')?.value?.trim();
    const rut    = formatChileanRut(document.getElementById('buyerRut')?.value?.trim() || '');
    const email  = document.getElementById('buyerEmail')?.value?.trim();
    const phone  = document.getElementById('buyerPhone')?.value?.trim() || '';
    const address = document.getElementById('buyerAddress')?.value?.trim() || '';
    const comuna = document.getElementById('buyerComuna')?.value?.trim() || '';
    const method = 'flow';

    if (!rut || !isValidChileanRut(rut)) {
      showToast('Ingresa un RUT chileno válido', 'warning');
      return;
    }
    if (!address || !comuna) {
      showToast('Completa dirección y comuna', 'warning');
      return;
    }

    const payBtn = document.getElementById('step3Pay');
    payBtn.disabled = true;
    payBtn.textContent = '🔄 Procesando…';

    const items = _cart.load();
    if (!items.length) {
      showToast('Tu carrito está vacío', 'warning');
      payBtn.textContent = '🔒 Pagar Ahora';
      payBtn.disabled = false;
      return;
    }

    payBtn.textContent = '🔄 Conectando con Flow.cl…';
    try {
      const base = window.location.pathname.replace(/\/index\.php.*|\/$/, '').replace(/\/[^/]+\.php.*/, '');
      const resp = await fetch(base + '/api/flow.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          items:      items.map(i => ({ raffleId: i.raffleId, packId: i.packId })),
          buyerName:  name,
          buyerRut:   rut,
          buyerEmail: email,
          buyerPhone: phone,
          buyerAddress: address,
          buyerComuna: comuna,
          paymentMethod: method,
        }),
      });

      const json = await resp.json();
      if (!json.ok) throw new Error(json.error || 'Error al crear el pago');

      window.location.href = json.data.redirectUrl;
    } catch (err) {
      showToast('❌ ' + err.message, 'error', 6000);
      payBtn.textContent = '🔒 Pagar Ahora';
      payBtn.disabled = false;
    }
  });
})();

// ─── Shared Build Functions ───────────────────────────────────────────────────

function buildRaffleCard(r) {
  const hasLimit = Number(r.totalTickets) > 0;
  const closure = getRaffleClosureInfo(r.drawDate);
  const canBuy = r.status === 'active' && !closure.salesClosed;
  const minPrice = r.packs?.length ? Math.min(...r.packs.map(p => p.price)) : 0;
  const statusMap = { active: ['En curso', 'status-active'], soon: ['Próximamente', 'status-soon'], ended: ['Finalizado', 'status-ended'] };
  const [statusLabel, statusClass] = statusMap[r.status] || statusMap.ended;

  return `
    <div class="raffle-card${r.featured ? ' featured' : ''}" onclick="${r.status === 'active' ? `openPurchaseModal('${r.id}')` : `window.location='ver-sorteo.php?id=${r.id}'`}">
      <div class="raffle-card-img">
        ${r.image ? `<img src="${r.image}" alt="${r.title}" loading="lazy">` :
          `<div style="font-size:5rem; height:100%; display:flex; align-items:center; justify-content:center; background:var(--bg-card2);">${r.imageEmoji || '🎁'}</div>`}
        <span class="raffle-card-status ${statusClass}">${statusLabel}</span>
          ${hasLimit ? `<span class="raffle-card-tickets">🎟️ ${r.totalTickets.toLocaleString('es-CL')} ${tLabelP()}</span>` : ''}
      </div>
      <div class="raffle-card-body">
        <div class="raffle-card-cat">${r.category}</div>
        <div class="raffle-card-title">${r.title}</div>
        <div class="raffle-card-value">Desde ${formatPrice(minPrice)}</div>
        ${closure.salesClosed ? `<div class="raffle-card-closure">Se ha cerrado la compra de ${tLabelP()}, faltan ${closure.remainingText} para que puedas ganar.</div>` : ''}
        <div class="raffle-card-footer">
          <div class="raffle-card-actions">
            <a href="ver-sorteo.php?id=${r.id}" class="btn btn-ghost btn-sm raffle-card-action" onclick="event.stopPropagation()">
              <span>👁 Ver Sorteo</span>
            </a>
            ${canBuy ? `<button class="btn btn-primary btn-sm raffle-card-action" onclick="event.stopPropagation(); openPurchaseModal('${r.id}')">Comprar ${tLabelUp()}</button>` :
              (r.status === 'active' && closure.salesClosed) ? `<span class="pill pill-amber" style="font-size:.72rem;">Compra cerrada</span>` :
              r.status === 'soon' ? `<span class="pill pill-amber" style="font-size:.72rem;">Próximamente</span>` :
              `<span class="pill pill-gray" style="font-size:.72rem;">Finalizado</span>`}
          </div>
        </div>
      </div>
    </div>
  `;
}

function buildWinnerCard(w) {
  return `
    <div class="winner-card">
      <div class="winner-card-img">
        ${w.image ? `<img src="${w.image}" alt="${w.winnerName}">` : `<div>${w.emoji || '🏆'}</div>`}
      </div>
      <div class="winner-card-body">
        <div class="flex-between mb-1">
          <span class="winner-name">${w.winnerName}</span>
          ${w.verified ? `<span class="verified-badge">✅ Verificado</span>` : ''}
        </div>
        <div class="winner-location">📍 ${w.winnerLocation}</div>
        <div class="winner-prize">${w.prize}</div>
        <div class="winner-date">📅 Sorteo ${formatDate(w.drawDate)}</div>
        <div style="display:flex; gap:.5rem; margin-top:.75rem; flex-wrap:wrap;">
          ${w.videoUrl && w.videoUrl !== '#' ? `<a href="${w.videoUrl}" target="_blank" class="btn btn-ghost btn-sm" onclick="event.stopPropagation()">▶ Ver video</a>` : ''}
          ${w.edition ? `<span class="pill pill-purple">${w.edition}</span>` : ''}
        </div>
      </div>
    </div>
  `;
}

// ─── Countdown ────────────────────────────────────────────────────────────────
function startCountdown(dateStr, daysId, hoursId, minsId, secsId) {
  function update() {
    const t = getTimeLeft(dateStr);
    const dEl = document.getElementById(daysId);
    const hEl = document.getElementById(hoursId);
    const mEl = document.getElementById(minsId);
    const sEl = document.getElementById(secsId);
    if (dEl) dEl.textContent = pad(t.days);
    if (hEl) hEl.textContent = pad(t.hours);
    if (mEl) mEl.textContent = pad(t.minutes);
    if (sEl) sEl.textContent = pad(t.seconds);
  }
  update();
  return setInterval(update, 1000);
}

// ─── Utilities ────────────────────────────────────────────────────────────────
function pad(n) { return String(n).padStart(2, '0'); }

function normalizeChileanRut(v) {
  return String(v || '').toUpperCase().replace(/[^0-9K]/g, '');
}

function formatChileanRut(v) {
  const clean = normalizeChileanRut(v);
  if (clean.length < 2) return clean;
  const body = clean.slice(0, -1);
  const dv = clean.slice(-1);
  const bodyWithDots = body.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  return `${bodyWithDots}-${dv}`;
}

function isValidChileanRut(v) {
  const clean = normalizeChileanRut(v);
  if (clean.length < 2) return false;
  const body = clean.slice(0, -1);
  const dv = clean.slice(-1);
  if (!/^\d+$/.test(body)) return false;

  let sum = 0;
  let mul = 2;
  for (let i = body.length - 1; i >= 0; i--) {
    sum += parseInt(body[i], 10) * mul;
    mul = mul === 7 ? 2 : mul + 1;
  }
  const rem = 11 - (sum % 11);
  const expected = rem === 11 ? '0' : rem === 10 ? 'K' : String(rem);
  return expected === dv;
}

// ─── Mini countdowns on raffle cards ─────────────────────────────────────────
function tickCardCountdowns() {
  document.querySelectorAll('.rd-countdown[data-date]').forEach(el => {
    const t = getTimeLeft(el.dataset.date);
    if (t.days > 0)        el.textContent = `· ${t.days}d ${pad(t.hours)}h`;
    else if (t.hours > 0)  el.textContent = `· ${t.hours}h ${pad(t.minutes)}m`;
    else if (t.minutes > 0) el.textContent = `· ${t.minutes}m`;
    else                   el.textContent = '· hoy';
  });
}
let _cdDebounce = null;
new MutationObserver(() => {
  if (_cdDebounce) return;
  _cdDebounce = setTimeout(() => { _cdDebounce = null; tickCardCountdowns(); }, 250);
}).observe(document.documentElement, { childList: true, subtree: true });
setInterval(tickCardCountdowns, 60000);

function animateCount(el, from, to, duration, suffix = '') {
  const startTime = performance.now();
  let rafId;
  function step(now) {
    const elapsed = now - startTime;
    const progress = Math.min(elapsed / duration, 1);
    const eased = 1 - Math.pow(1 - progress, 3);
    const value = Math.round(from + (to - from) * eased);
    el.textContent = value.toLocaleString('es-CL') + suffix;
    if (progress < 1) rafId = requestAnimationFrame(step);
  }
  rafId = requestAnimationFrame(step);
  return () => cancelAnimationFrame(rafId);
}

// ─── Active nav link ──────────────────────────────────────────────────────────
(function() {
  const page = window.location.pathname.split('/').pop() || 'index.php';
  document.querySelectorAll('.navbar-nav a, .mobile-nav a').forEach(a => {
    const href = a.getAttribute('href');
    const hrefBase = href ? href.replace(/\.(php|html)$/, '') : href;
    const pageBase = page.replace(/\.(php|html)$/, '') || 'index';
    a.classList.toggle('active', hrefBase === pageBase || (pageBase === '' && hrefBase === 'index'));
  });
})();
