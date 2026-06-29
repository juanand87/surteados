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
function appBasePath() { return window.location.pathname.replace(/\/index\.php.*|\/$/, '').replace(/\/[^/]+\.php.*/, ''); }

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

let _locationsCache = null;
async function loadChileLocations() {
  if (_locationsCache) return _locationsCache;
  const base = window.location.pathname.replace(/\/index\.php.*|\/$/, '').replace(/\/[^/]+\.php.*/, '');
  const resp = await fetch(base + '/api/locations.php', { credentials: 'same-origin' });
  const json = await resp.json();
  if (!json.ok) throw new Error(json.error || 'No se pudieron cargar regiones y comunas');
  _locationsCache = json.data?.regions || [];
  return _locationsCache;
}

function fillRegionSelect(regions) {
  const regionEl = document.getElementById('buyerRegion');
  if (!regionEl) return;
  const current = regionEl.value;
  regionEl.innerHTML = '<option value="">Selecciona tu región</option>' + regions.map(r =>
    `<option value="${escHtml(r.id)}">${escHtml(r.name)}</option>`
  ).join('');
  if (current) regionEl.value = current;
}

function fillCommuneSelect(regionId) {
  const communeEl = document.getElementById('buyerComuna');
  if (!communeEl) return;
  const region = (_locationsCache || []).find(r => String(r.id) === String(regionId));
  if (!region) {
    communeEl.innerHTML = '<option value="">Primero selecciona una región</option>';
    communeEl.disabled = true;
    return;
  }
  communeEl.innerHTML = '<option value="">Selecciona tu comuna</option>' + region.communes.map(c =>
    `<option value="${escHtml(c.id)}" data-name="${escHtml(c.name)}">${escHtml(c.name)}</option>`
  ).join('');
  communeEl.disabled = false;
}

async function setupLocationSelectors() {
  const regionEl = document.getElementById('buyerRegion');
  const communeEl = document.getElementById('buyerComuna');
  if (!regionEl || !communeEl) return;
  try {
    const regions = await loadChileLocations();
    fillRegionSelect(regions);
    regionEl.addEventListener('change', () => fillCommuneSelect(regionEl.value));
  } catch (err) {
    showToast(err.message, 'error');
  }
}

function selectedCommunePayload() {
  const communeEl = document.getElementById('buyerComuna');
  const selected = communeEl?.selectedOptions?.[0];
  return {
    id: communeEl?.value?.trim() || '',
    name: selected?.dataset?.name || selected?.textContent?.trim() || '',
  };
}

async function loadCustomerSession() {
  try {
    const resp = await fetch('api/customer_auth.php?action=session', { credentials: 'same-origin' });
    const text = await resp.text();
    const json = JSON.parse(text);
    return json.ok ? json.data : { authenticated: false };
  } catch (_) {
    return { authenticated: false };
  }
}

function setupCustomerNav() {
  const actions = document.querySelector('.navbar-actions');
  if (!actions || actions.dataset.customerNavReady === '1') return;
  actions.dataset.customerNavReady = '1';

  loadCustomerSession().then(session => {
    if (!session?.authenticated) return;
    actions.querySelectorAll('a[href*="mis-imagenes.php#login"], a[href*="mis-imagenes.php#register"]').forEach(el => el.remove());
    if (actions.querySelector('.customer-menu')) return;

    const label = session.fullName || session.username || session.email || 'Mis datos';
    const wrap = document.createElement('div');
    wrap.className = 'customer-menu';
    wrap.innerHTML = `
      <button type="button" class="btn btn-outline btn-sm customer-menu-btn" aria-expanded="false">&#128100; Mis datos</button>
      <div class="customer-menu-panel" role="menu">
        <div class="customer-menu-name">${escHtml(label)}</div>
        <a href="mis-imagenes.php#datos" role="menuitem">Mis datos</a>
        <a href="mis-imagenes.php#imagenes" role="menuitem">Mis im&aacute;genes</a>
        <a href="mis-imagenes.php#sorteos" role="menuitem">Sorteos</a>
        <button type="button" class="customer-menu-logout" role="menuitem">Cerrar sesión</button>
      </div>
    `;
    actions.insertBefore(wrap, actions.firstChild);

    const btn = wrap.querySelector('.customer-menu-btn');
    btn?.addEventListener('click', (event) => {
      event.stopPropagation();
      wrap.classList.toggle('open');
      btn.setAttribute('aria-expanded', wrap.classList.contains('open') ? 'true' : 'false');
    });
    document.addEventListener('click', () => {
      wrap.classList.remove('open');
      btn?.setAttribute('aria-expanded', 'false');
    });
    wrap.querySelector('.customer-menu-logout')?.addEventListener('click', async (event) => {
      event.preventDefault();
      event.stopPropagation();
      try {
        await fetch('api/customer_auth.php?action=logout', {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json' },
          body: '{}',
        });
      } finally {
        window.location.href = 'mis-imagenes.php#login';
      }
    });
  });
}

document.addEventListener('DOMContentLoaded', setupCustomerNav);

// ─── Welcome Wheel ───────────────────────────────────────────────────────────
function isHomePageForWheel() {
  const page = window.location.pathname.split('/').pop() || 'index.php';
  return page === '' || page === 'index.php' || page === 'index.html';
}

const WHEEL_PALETTE = [
  ['#ec4899', '#be185d'],
  ['#06b6d4', '#0e7490'],
  ['#f59e0b', '#b45309'],
  ['#22c55e', '#15803d'],
  ['#8b5cf6', '#6d28d9'],
  ['#ef4444', '#b91c1c'],
];

function wheelPrizeColors(prize, index) {
  const fallback = WHEEL_PALETTE[index % WHEEL_PALETTE.length];
  const usesDefault = !prize.color1 || prize.color1 === '#7c3aed';
  return usesDefault ? fallback : [prize.color1, prize.color2 || fallback[1]];
}

function wheelSliceBackground(prizes) {
  if (!prizes.length) return '';
  const step = 360 / prizes.length;
  const offset = step / -2;
  const slices = prizes.map((p, i) => {
    const [startColor, endColor] = wheelPrizeColors(p, i);
    const from = i * step;
    const to = (i + 1) * step;
    return `${startColor} ${from}deg ${from + step * 0.52}deg, ${endColor} ${from + step * 0.52}deg ${to}deg`;
  }).join(', ');
  return `from ${offset}deg, ${slices}`;
}

function wheelLabelsHtml(prizes) {
  if (!prizes.length) return '';
  const step = 360 / prizes.length;
  return prizes.map((p, i) => {
    const angle = i * step;
    const title = escHtml(String(p.title || '').replace(/\s+/g, ' ').trim());
    return `<div class="wheel-label" style="--label-angle:${angle}deg;"><span>${title}</span></div>`;
  }).join('');
}

async function initWelcomeWheel() {
  const forceWheel = new URLSearchParams(window.location.search).has('ruleta');
  if (!forceWheel && !isHomePageForWheel()) return;
  if (!forceWheel && localStorage.getItem('surteados_wheel_dismissed') === '1') return;
  try {
    const resp = await fetch(appBasePath() + '/api/wheel.php', { credentials: 'same-origin' });
    const json = await resp.json();
    if (!json.ok || !json.data?.enabled || !Array.isArray(json.data.prizes) || !json.data.prizes.length) return;
    const prizes = json.data.prizes;
    const gate = document.createElement('div');
    gate.className = 'wheel-gate';
    gate.innerHTML = `
      <div class="wheel-gate-card">
        <div class="wheel-gate-intro">
          <div class="wheel-gate-kicker">Bienvenida Surteados</div>
          <h2 class="wheel-gate-title">¿Quieres participar por descuentos y premios?</h2>
          <p class="wheel-gate-text">Ingresa tu correo, gira la ruleta y descubre tu premio de bienvenida. Si ganas un descuento, el código llegará a tu correo y podrás usarlo una sola vez en tu compra.</p>
          <div class="wheel-gate-actions">
            <button class="btn btn-primary" id="wheelYesBtn">Sí, girar ruleta</button>
            <button class="btn btn-ghost" id="wheelNoBtn">No, entrar al sitio</button>
          </div>
        </div>
        <div class="wheel-gate-play" id="wheelPlay" style="display:none;">
          <div class="wheel-stage">
            <div class="wheel-pointer"></div>
            <div class="wheel-disc" id="wheelDisc" style="background:conic-gradient(${wheelSliceBackground(prizes)});"></div>
            <div class="wheel-labels" id="wheelLabels">${wheelLabelsHtml(prizes)}</div>
          </div>
          <div class="wheel-email-box" id="wheelEmailBox">
            <input type="email" class="form-control" id="wheelEmailInput" placeholder="tu@correo.com">
            <button class="btn btn-accent" id="wheelSpinBtn" style="font-weight:900;">Lanzar ruleta</button>
            <p class="form-hint" style="text-align:center;color:rgba(255,255,255,.62);">Solo puedes participar una vez por correo.</p>
          </div>
          <div class="wheel-result" id="wheelResult"></div>
        </div>
      </div>`;
    document.body.appendChild(gate);

    const closeGate = () => { localStorage.setItem('surteados_wheel_dismissed', '1'); gate.remove(); };
    gate.querySelector('#wheelNoBtn')?.addEventListener('click', closeGate);
    gate.querySelector('#wheelYesBtn')?.addEventListener('click', () => {
      gate.querySelector('.wheel-gate-card')?.classList.add('is-playing');
      gate.querySelector('.wheel-gate-intro').style.display = 'none';
      gate.querySelector('#wheelPlay').style.display = 'flex';
    });

    gate.querySelector('#wheelSpinBtn')?.addEventListener('click', async () => {
      const emailEl = gate.querySelector('#wheelEmailInput');
      const btn = gate.querySelector('#wheelSpinBtn');
      const email = emailEl?.value?.trim() || '';
      if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)) { showToast('Ingresa un correo válido', 'warning'); return; }
      btn.disabled = true;
      btn.textContent = 'Girando...';
      const disc = gate.querySelector('#wheelDisc');
      const labels = gate.querySelector('#wheelLabels');
      const waitingTarget = 360 * 24 + Math.floor(Math.random() * 360);
      if (disc) {
        disc.classList.add('is-spinning');
        disc.style.transition = 'transform 18s cubic-bezier(.08,.72,.18,1)';
        disc.style.transform = `rotate(${waitingTarget}deg)`;
        if (labels) {
          labels.style.transition = 'transform 18s cubic-bezier(.08,.72,.18,1)';
          labels.style.transform = `rotate(${waitingTarget}deg)`;
          labels.style.setProperty('--wheel-rotation', `${waitingTarget}deg`);
        }
      }
      try {
        const resp = await fetch(appBasePath() + '/api/wheel.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'spin', email }),
        });
        const json = await resp.json();
        if (!json.ok) throw new Error(json.error || 'No se pudo girar la ruleta.');
        const prize = json.data.prize;
        const idx = Math.max(0, prizes.findIndex(p => p.id === prize.id));
        const step = 360 / prizes.length;
        const prizeCenter = idx * step;
        const desiredRotation = prizeCenter % 360;
        const currentRotation = ((waitingTarget % 360) + 360) % 360;
        const finalDelta = (desiredRotation - currentRotation + 360) % 360;
        const target = waitingTarget + 360 * 3 + finalDelta;
        if (disc) {
          disc.style.transition = 'transform 5.2s cubic-bezier(.12,.72,.14,1)';
          disc.style.transform = `rotate(${target}deg)`;
          if (labels) {
            labels.style.transition = 'transform 5.2s cubic-bezier(.12,.72,.14,1)';
            labels.style.transform = `rotate(${target}deg)`;
            labels.style.setProperty('--wheel-rotation', `${target}deg`);
          }
        }
        setTimeout(() => {
          disc?.classList.remove('is-spinning');
          gate.querySelector('#wheelEmailBox').style.display = 'none';
          const result = gate.querySelector('#wheelResult');
          result.style.display = 'block';
          result.innerHTML = `<h3 style="margin:0 0 .4rem;">Ganaste</h3><strong>${escHtml(prize.title)}</strong><p style="color:rgba(255,255,255,.72);line-height:1.6;margin:.7rem 0 1rem;">${json.data.hasCode ? 'El código fue enviado a tu correo. Guárdalo, es único y solo podrás usarlo una vez.' : 'Te enviamos el detalle a tu correo.'}</p><button class="btn btn-primary" id="wheelCloseAfter">Entrar al sitio</button>`;
          result.querySelector('#wheelCloseAfter')?.addEventListener('click', closeGate);
          if (!json.data.mailSent) showToast('Premio generado, pero no se pudo enviar el correo: ' + (json.data.mailError || 'revisa SMTP'), 'warning', 7000);
        }, 5400);
      } catch (err) {
        showToast(err.message, 'error', 6500);
        if (disc) {
          disc.classList.remove('is-spinning');
          disc.style.transition = 'transform .35s ease';
          disc.style.transform = 'rotate(0deg)';
          if (labels) {
            labels.style.transition = 'transform .35s ease';
            labels.style.transform = 'rotate(0deg)';
            labels.style.setProperty('--wheel-rotation', '0deg');
          }
        }
        btn.disabled = false;
        btn.textContent = 'Lanzar ruleta';
      }
    });
  } catch (_) {}
}

document.addEventListener('DOMContentLoaded', initWelcomeWheel);

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
  document.querySelectorAll('.hero-slider-wrap').forEach((wrap) => {
    const track = wrap.querySelector('.hs-track');
    const dotsEl = wrap.querySelector('.hs-dots');
    if (!track) return;

    const slides = Array.from(track.querySelectorAll('.hs-slide'));
    if (!slides.length) {
      wrap.classList.add('hidden');
      return;
    }

    wrap.classList.remove('hidden');
    let current = 0;
    let timer;
    const isMobileSlider = wrap.classList.contains('hero-slider-mobile');

    function syncMobileHeight() {
      if (!isMobileSlider) return;
      const activeSlide = slides[current];
      const image = activeSlide?.querySelector('.hs-mobile-image');
      if (!image) return;
      const applyHeight = () => {
        if (slides[current] !== activeSlide || !image.naturalWidth) return;
        const height = wrap.clientWidth * (image.naturalHeight / image.naturalWidth);
        wrap.style.height = `${Math.round(height)}px`;
      };
      if (image.complete) applyHeight();
      else image.addEventListener('load', applyHeight, { once: true });
    }

    function goTo(idx) {
      current = ((idx % slides.length) + slides.length) % slides.length;
      track.style.transform = `translateX(-${current * 100}%)`;
      dotsEl?.querySelectorAll('.hs-dot').forEach((dot, i) => {
        dot.classList.toggle('active', i === current);
      });
      syncMobileHeight();
    }

    function startAuto() {
      clearInterval(timer);
      if (slides.length > 1) timer = setInterval(() => goTo(current + 1), 5500);
    }

    wrap.querySelector('.hs-prev')?.addEventListener('click', () => {
      goTo(current - 1);
      startAuto();
    });
    wrap.querySelector('.hs-next')?.addEventListener('click', () => {
      goTo(current + 1);
      startAuto();
    });
    dotsEl?.querySelectorAll('.hs-dot').forEach((dot) => {
      dot.addEventListener('click', () => {
        goTo(Number(dot.dataset.idx));
        startAuto();
      });
    });

    let touchX = 0;
    wrap.addEventListener('touchstart', (event) => {
      touchX = event.touches[0].clientX;
    }, { passive: true });
    wrap.addEventListener('touchend', (event) => {
      const delta = event.changedTouches[0].clientX - touchX;
      if (Math.abs(delta) > 50) {
        goTo(current + (delta < 0 ? 1 : -1));
        startAuto();
      }
    });

    if (isMobileSlider) {
      window.addEventListener('resize', syncMobileHeight);
    }
    goTo(0);
    startAuto();
  });
})();
// Home raffle carousel
(function() {
  const carousel = document.querySelector('.home-raffle-carousel');
  const prev = document.getElementById('homeRafflePrev');
  const next = document.getElementById('homeRaffleNext');
  if (!carousel || !prev || !next) return;

  function move(dir) {
    const firstCard = carousel.querySelector('.home-raffle-slide');
    const step = firstCard ? firstCard.getBoundingClientRect().width + 16 : 320;
    carousel.scrollBy({ left: dir * step, behavior: 'smooth' });
  }

  prev.addEventListener('click', () => move(-1));
  next.addEventListener('click', () => move(1));
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
            `<div style="font-size:6rem;">${featured.imageEmoji || '&#127873;'}</div>`}
          <span class="prize-badge">&#128230; PREMIO PRINCIPAL</span>
        </div>
        <div class="hero-card-title">${featured.title}</div>
        <div class="countdown-row" id="heroCountdown">
          <div class="countdown-item"><div class="count-num" id="cd-days">${pad(timeLeft.days)}</div><div class="count-label">D\u00edas</div></div>
          <div class="countdown-item"><div class="count-num" id="cd-hours">${pad(timeLeft.hours)}</div><div class="count-label">Horas</div></div>
          <div class="countdown-item"><div class="count-num" id="cd-mins">${pad(timeLeft.minutes)}</div><div class="count-label">Min</div></div>
          <div class="countdown-item"><div class="count-num" id="cd-secs">${pad(timeLeft.seconds)}</div><div class="count-label">Seg</div></div>
        </div>
          <button class="btn btn-accent btn-block btn-lg" style="font-weight:800;" onclick="openPurchaseModal('${featured.id}')">
            &#127915; Comprar ${tLabelUp()} - Desde ${formatPrice(Math.min(...featured.packs.map(p => p.price)))}
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

// ─── Cart (multi-raffle) ──────────────────────────────────────────────────────
const CART_KEY = 'surteados_cart';

const _cart = {
  /** @returns {{ raffleId:string, packId:string, raffleTitle:string, packLabel:string, price:number }[]} */
  load() {
    try { return JSON.parse(localStorage.getItem(CART_KEY) || '[]'); } catch { return []; }
  },
  save(items) {
    localStorage.setItem(CART_KEY, JSON.stringify(items));
    setTimeout(() => syncAnalyticsCart(items.length ? 'active' : 'cleared'), 0);
  },
  clear() {
    localStorage.removeItem(CART_KEY);
    setTimeout(() => syncAnalyticsCart('cleared'), 0);
  },
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

function analyticsBuyerData() {
  return {
    buyerName: document.getElementById('buyerName')?.value?.trim() || '',
    buyerEmail: document.getElementById('buyerEmail')?.value?.trim() || '',
    buyerPhone: document.getElementById('buyerPhone')?.value?.trim() || '',
  };
}

function syncAnalyticsCart(status = 'active', explicitStep = null) {
  const analytics = window.SurteadosAnalytics;
  if (!analytics) return;
  analytics.syncCart({
    items: _cart.load(),
    ...analyticsBuyerData(),
    step: explicitStep || Number(_purchaseState?.currentStep || 1),
    status,
  });
}

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
let _discountState = null;
let _customerSessionCache = null;

async function getCustomerSessionCached() {
  if (_customerSessionCache) return _customerSessionCache;
  _customerSessionCache = await loadCustomerSession();
  return _customerSessionCache;
}

function fillBuyerValue(id, value) {
  const el = document.getElementById(id);
  if (!el || !value || el.value.trim()) return;
  el.value = value;
}

async function prefillBuyerDataFromSession() {
  const session = await getCustomerSessionCached();
  if (!session?.authenticated) return;

  fillBuyerValue('buyerName', session.fullName || session.username || '');
  fillBuyerValue('buyerPhone', session.phone || '');
  fillBuyerValue('buyerAddress', session.address || '');
  fillBuyerValue('buyerRut', formatChileanRut(session.rut || ''));
  fillBuyerValue('buyerEmail', session.email || '');
  fillBuyerValue('buyerEmailConfirm', session.email || '');
  syncAnalyticsCart('active', 2);

  if (!session.communeId && !session.comuna) return;
  const regionEl = document.getElementById('buyerRegion');
  const communeEl = document.getElementById('buyerComuna');
  if (!regionEl || !communeEl || communeEl.value) return;

  try {
    const regions = await loadChileLocations();
    fillRegionSelect(regions);
    const matchedRegion = regions.find(r => (r.communes || []).some(c =>
      String(c.id) === String(session.communeId || '') || c.name === session.comuna
    ));
    if (!matchedRegion) return;
    regionEl.value = matchedRegion.id;
    fillCommuneSelect(matchedRegion.id);
    const matchedCommune = (matchedRegion.communes || []).find(c =>
      String(c.id) === String(session.communeId || '') || c.name === session.comuna
    );
    if (matchedCommune) communeEl.value = matchedCommune.id;
  } catch (_) {}
}

function openPurchaseModal(raffleId, initialPackId = null) {
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
  resetDiscountState();
  window.SurteadosAnalytics?.event('purchase_open', { raffleId, step: 1 });

  const modal = document.getElementById('purchaseModal');
  const title = document.getElementById('modalTitle');
  if (title) title.textContent = raffle.title;

  // Render packs
  const packsGrid = document.getElementById('modalPacksGrid');
  if (packsGrid) {
    const sortedPacks = [...raffle.packs].sort((a, b) => (a.qty - b.qty) || (a.price - b.price));
    packsGrid.innerHTML = sortedPacks.map(p => `
      <div class="pack-card" onclick="selectPack('${p.id}', '${raffleId}')" data-pack="${p.id}">
        <div class="pack-qty">${p.qty}</div>
          <div class="pack-qty-label">${p.qty > 1 ? tLabelP() : tLabel()}</div>
        ${p.originalPrice > p.price ? `<div class="pack-price-original">${formatPrice(p.originalPrice)}</div>` : ''}
        <div class="pack-price">${formatPrice(p.price)}</div>
        ${p.discount ? `<div class="pack-discount">-${p.discount}% OFF</div>` : ''}
      </div>
    `).join('');
  }

  const saved = _cart.load().find(i => i.raffleId === raffleId);
  const initialPack = initialPackId
    ? raffle.packs.find(p => p.id === initialPackId)
    : (saved ? raffle.packs.find(p => p.id === saved.packId) : null);

  const lbl = document.getElementById('selectedPackLabel');
  if (lbl) lbl.textContent = 'Ninguno';
  const btn = document.getElementById('step1Next');
  if (btn) btn.disabled = true;
  const addMore = document.getElementById('step1AddMore');
  if (addMore) addMore.disabled = true;

  if (initialPack) {
    selectPack(initialPack.id, raffleId);
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
  window.SurteadosAnalytics?.event('pack_selected', { raffleId, step: 1, metadata: { packId, price: pack.price } });

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

  if (step === 2) {
    prefillBuyerDataFromSession();
    window.SurteadosAnalytics?.event('details_started', { raffleId: _purchaseState.raffleId, step: 2 });
  }
  syncAnalyticsCart('active', Number(numericStep));
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
          <div class="pack-card${cartItem?.packId === p.id ? ' selected' : ''}"
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

function resetDiscountState() {
  _discountState = null;
  const input = document.getElementById('discountCodeInput');
  const feedback = document.getElementById('discountFeedback');
  if (input) input.value = '';
  if (feedback) { feedback.style.display = 'none'; feedback.textContent = ''; feedback.style.color = ''; }
}

function checkoutItemsPayload() {
  return _cart.load().map(i => ({ raffleId: i.raffleId, packId: i.packId }));
}

function updateSummaryTotals() {
  const total = _cart.total();
  const totalEl = document.getElementById('summaryTotal');
  const feedback = document.getElementById('discountFeedback');
  if (totalEl) totalEl.textContent = formatPrice(_discountState?.totalAfter ?? total);
  if (feedback && _discountState) {
    feedback.style.display = 'block';
    feedback.style.color = '#059669';
    feedback.textContent = `Descuento aplicado: ${formatPrice(_discountState.discountAmount)}. Total anterior: ${formatPrice(_discountState.totalBefore)}.`;
  }
}

async function applyDiscountCode() {
  const input = document.getElementById('discountCodeInput');
  const btn = document.getElementById('discountApplyBtn');
  const feedback = document.getElementById('discountFeedback');
  const code = input?.value?.trim().toUpperCase() || '';
  const email = document.getElementById('buyerEmail')?.value?.trim() || '';
  if (!code) { showToast('Ingresa tu código de descuento', 'warning'); return; }
  if (!email) { showToast('Primero ingresa tu correo', 'warning'); return; }
  if (btn) { btn.disabled = true; btn.textContent = 'Validando...'; }
  try {
    const resp = await fetch(appBasePath() + '/api/discount.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ code, buyerEmail: email, items: checkoutItemsPayload() }),
    });
    const json = await resp.json();
    if (!json.ok) throw new Error(json.error || 'Código inválido');
    _discountState = json.data;
    if (input) input.value = json.data.code || code;
    updateSummaryTotals();
    showToast('Código aplicado');
  } catch (err) {
    _discountState = null;
    updateSummaryTotals();
    if (feedback) { feedback.style.display = 'block'; feedback.style.color = '#dc2626'; feedback.textContent = err.message; }
    showToast(err.message, 'error');
  } finally {
    if (btn) { btn.disabled = false; btn.textContent = 'Aplicar'; }
  }
}

(function() {
  const modal = document.getElementById('purchaseModal');
  const modalClose = document.getElementById('modalClose');
  const closeAfterPurchase = document.getElementById('closeAfterPurchase');
  setupLocationSelectors();

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

  let analyticsContactTimer;
  ['buyerName', 'buyerEmail', 'buyerPhone'].forEach((id) => {
    document.getElementById(id)?.addEventListener('input', () => {
      clearTimeout(analyticsContactTimer);
      analyticsContactTimer = setTimeout(() => syncAnalyticsCart('active', 2), 500);
    });
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
    const comuna       = selectedCommunePayload();
    const email        = document.getElementById('buyerEmail')?.value?.trim();
    const emailConfirm = document.getElementById('buyerEmailConfirm')?.value?.trim();
    const termsAccepted = !!document.getElementById('buyerTermsAccepted')?.checked;
    if (!name)  { showToast('Ingresa tu nombre completo', 'warning'); return; }
    if (!rutInput) { showToast('Ingresa tu RUT', 'warning'); return; }
    if (!address) { showToast('Ingresa tu dirección', 'warning'); return; }
    if (!comuna.id) { showToast('Selecciona tu comuna', 'warning'); return; }
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
    _discountState = null;

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
    resetDiscountState();
    updateSummaryTotals();

    window.SurteadosAnalytics?.event('details_completed', { raffleId: _purchaseState.raffleId, step: 2 });
    syncAnalyticsCart('active', 3);
    updatePurchaseStep(3);
  });

  // Step 3 → back
  document.getElementById('step3Back')?.addEventListener('click', () => updatePurchaseStep(2));

  function resetFlowPayButton() {
    const payBtn = document.getElementById('step3Pay');
    if (!payBtn) return;
    payBtn.disabled = false;
    payBtn.textContent = '🔒 Pagar Ahora';
  }

  window.addEventListener('pageshow', resetFlowPayButton);
  document.addEventListener('visibilitychange', () => {
    if (!document.hidden) resetFlowPayButton();
  });

  // Step 3 - Pay
  document.getElementById('step3Pay')?.addEventListener('click', async () => {
    const name   = document.getElementById('buyerName')?.value?.trim();
    const rut    = formatChileanRut(document.getElementById('buyerRut')?.value?.trim() || '');
    const email  = document.getElementById('buyerEmail')?.value?.trim();
    const phone  = document.getElementById('buyerPhone')?.value?.trim() || '';
    const address = document.getElementById('buyerAddress')?.value?.trim() || '';
    const comuna = selectedCommunePayload();
    const method = 'flow';

    if (!rut || !isValidChileanRut(rut)) {
      showToast('Ingresa un RUT chileno válido', 'warning');
      return;
    }
    if (!address || !comuna.id) {
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
    window.SurteadosAnalytics?.event('payment_started', { raffleId: _purchaseState.raffleId, step: 3 });
    syncAnalyticsCart('active', 3);
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
          buyerComuna: comuna.name,
          buyerCommuneId: comuna.id,
          paymentMethod: method,
          discountCode: _discountState?.code || '',
        }),
      });

      const json = await resp.json();
      if (!json.ok) throw new Error(json.error || 'Error al crear el pago');

      window.SurteadosAnalytics?.event('payment_redirect', { raffleId: _purchaseState.raffleId, step: 3 });
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
