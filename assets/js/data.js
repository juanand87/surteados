/**
 * SURTEADOS — Data Layer
 * Manages all raffle data in localStorage with sensible defaults.
 */

const DB_KEY = 'surteados_db';

// ─── Default Seed Data ────────────────────────────────────────────────────────

const SEED = {
  raffles: [
    {
      id: 'r001',
      title: 'iPhone 16 Pro Max 256GB',
      category: 'Tecnología',
      description: 'El smartphone más avanzado de Apple. Cámara pro, chip A18 Pro y diseño premium de titanio. Incluye AirPods Pro de regalo.',
      status: 'active',  // active | soon | ended
      drawDate: '2026-05-15T19:00:00',
      image: null,
      imageEmoji: '📱',
      totalTickets: 5000,
      soldTickets: 3241,
      packs: [
        { id: 'p1', qty: 1, price: 3000, originalPrice: 3000, label: '1 Ticket' },
        { id: 'p2', qty: 3, price: 7500, originalPrice: 9000, label: '3 Tickets', discount: 17 },
        { id: 'p3', qty: 5, price: 11000, originalPrice: 15000, label: '5 Tickets', discount: 27, bestValue: true },
        { id: 'p4', qty: 10, price: 18000, originalPrice: 30000, label: '10 Tickets', discount: 40 }
      ],
      prizes: [
        { id: 'pr1', place: 1, name: 'iPhone 16 Pro Max 256GB', value: 1299000, emoji: '📱', image: null },
        { id: 'pr2', place: 2, name: 'AirPods Pro 2da Gen', value: 349000, emoji: '🎧', image: null },
        { id: 'pr3', place: 3, name: 'Vale de $50.000', value: 50000, emoji: '🎁', image: null }
      ],
      legalInfo: {
        organizer: 'Surteados Chile SpA',
        rut: '77.999.888-1',
        notary: 'Carlos Rojas Morales',
        certificate: 'N° 501-2026',
        salesPeriod: '01 de abril al 14 de mayo de 2026'
      },
      featured: true
    },
    {
      id: 'r002',
      title: 'PlayStation 5 + 5 Juegos',
      category: 'Gaming',
      description: 'Consola PlayStation 5 edición Digital + 5 juegos a elección. El regalo gamer definitivo. ¡Participa y gana!',
      status: 'active',
      drawDate: '2026-04-30T20:00:00',
      image: null,
      imageEmoji: '🎮',
      totalTickets: 3000,
      soldTickets: 2100,
      packs: [
        { id: 'p1', qty: 1, price: 2000, originalPrice: 2000, label: '1 Ticket' },
        { id: 'p2', qty: 3, price: 4500, originalPrice: 6000, label: '3 Tickets', discount: 25 },
        { id: 'p3', qty: 5, price: 7000, originalPrice: 10000, label: '5 Tickets', discount: 30, bestValue: true },
        { id: 'p4', qty: 10, price: 12000, originalPrice: 20000, label: '10 Tickets', discount: 40 }
      ],
      prizes: [
        { id: 'pr1', place: 1, name: 'PlayStation 5 Digital', value: 549000, emoji: '🎮', image: null },
        { id: 'pr2', place: 2, name: '5 Juegos PS5 a elección', value: 150000, emoji: '💿', image: null }
      ],
      legalInfo: {
        organizer: 'Surteados Chile SpA',
        rut: '77.999.888-1',
        notary: 'Carlos Rojas Morales',
        certificate: 'N° 498-2026',
        salesPeriod: '10 de marzo al 29 de abril de 2026'
      },
      featured: false
    },
    {
      id: 'r003',
      title: 'Viaje a Europa 2 Personas',
      category: 'Viajes',
      description: 'Paquete de viaje todo incluido para dos personas a Europa. 10 días visitando España, Francia e Italia. Vuelos + hotel + seguro de viaje.',
      status: 'soon',
      drawDate: '2026-07-01T19:00:00',
      image: null,
      imageEmoji: '✈️',
      totalTickets: 8000,
      soldTickets: 0,
      packs: [
        { id: 'p1', qty: 1, price: 5000, originalPrice: 5000, label: '1 Ticket' },
        { id: 'p2', qty: 3, price: 12000, originalPrice: 15000, label: '3 Tickets', discount: 20 },
        { id: 'p3', qty: 5, price: 18000, originalPrice: 25000, label: '5 Tickets', discount: 28, bestValue: true },
        { id: 'p4', qty: 10, price: 30000, originalPrice: 50000, label: '10 Tickets', discount: 40 }
      ],
      prizes: [
        { id: 'pr1', place: 1, name: 'Viaje Europa 2 Personas', value: 4500000, emoji: '✈️', image: null },
        { id: 'pr2', place: 2, name: 'Maleta Premium Samsonite', value: 250000, emoji: '🧳', image: null },
        { id: 'pr3', place: 3, name: 'Vale Travel Store $100.000', value: 100000, emoji: '🎫', image: null }
      ],
      legalInfo: {
        organizer: 'Surteados Chile SpA',
        rut: '77.999.888-1',
        notary: 'María López Vega',
        certificate: 'N° 512-2026',
        salesPeriod: '15 de mayo al 30 de junio de 2026'
      },
      featured: false
    }
  ],

  tickets: [
    { id: 't001', raffleId: 'r001', number: '003421', buyerName: 'María González', buyerEmail: 'maria@example.com', buyerPhone: '+56912345678', purchaseDate: '2026-04-01T10:23:00', pack: '5 Tickets', amount: 11000, packId: 'p3' },
    { id: 't002', raffleId: 'r001', number: '001087', buyerName: 'Carlos Soto', buyerEmail: 'carlos@example.com', buyerPhone: '+56978123456', purchaseDate: '2026-04-02T15:05:00', pack: '1 Ticket', amount: 3000, packId: 'p1' },
    { id: 't003', raffleId: 'r001', number: '004502', buyerName: 'Ana Ramírez', buyerEmail: 'ana@example.com', buyerPhone: '+56956789012', purchaseDate: '2026-04-03T09:15:00', pack: '3 Tickets', amount: 7500, packId: 'p2' },
    { id: 't004', raffleId: 'r002', number: '002310', buyerName: 'Pedro Muñoz', buyerEmail: 'pedro@example.com', buyerPhone: '+56934567890', purchaseDate: '2026-03-20T11:40:00', pack: '10 Tickets', amount: 12000, packId: 'p4' },
    { id: 't005', raffleId: 'r002', number: '000872', buyerName: 'Sofía Herrera', buyerEmail: 'sofia@example.com', buyerPhone: '+56923456789', purchaseDate: '2026-03-21T14:30:00', pack: '5 Tickets', amount: 7000, packId: 'p3' }
  ],

  winners: [
    {
      id: 'w001',
      raffleId: 'old001',
      raffleTitle: 'MacBook Pro M3',
      winnerName: 'Roberto Arias',
      winnerLocation: 'Concepción, Chile',
      prize: 'MacBook Pro M3 14"',
      prizeValue: 2199000,
      drawDate: '2025-12-20',
      ticketNumber: '002847',
      verified: true,
      edition: '1ª Edición',
      emoji: '💻',
      videoUrl: '#',
      notaryDoc: '#'
    },
    {
      id: 'w002',
      raffleId: 'old002',
      raffleTitle: 'Smart TV Samsung 65"',
      winnerName: 'Valentina Cruz',
      winnerLocation: 'Santiago, Chile',
      prize: 'Smart TV Samsung 65" QLED',
      prizeValue: 899000,
      drawDate: '2026-01-15',
      ticketNumber: '001203',
      verified: true,
      edition: '2ª Edición',
      emoji: '📺',
      videoUrl: '#',
      notaryDoc: '#'
    },
    {
      id: 'w003',
      raffleId: 'old003',
      raffleTitle: 'Moto Yamaha MT-03',
      winnerName: 'Diego Fuentes',
      winnerLocation: 'Valparaíso, Chile',
      prize: 'Yamaha MT-03 2025',
      prizeValue: 5500000,
      drawDate: '2026-02-28',
      ticketNumber: '000456',
      verified: true,
      edition: '3ª Edición',
      emoji: '🏍️',
      videoUrl: '#',
      notaryDoc: '#'
    }
  ],

  settings: {
    siteName: 'Surteados',
    tagline: 'Tu plataforma de rifas digitales más confiable',
    email: 'contacto@surteados.cl',
    whatsapp: '+56912345678',
    theme: {
      primary: '#7c3aed',
      primaryLight: '#9d5cf6',
      primaryDark: '#5b21b6',
      accent: '#f59e0b',
      accentLight: '#fbbf24',
      accentDark: '#d97706',
      headerBg: '#621c85',
      menuTextColor: '#f3e8ff',
      pageBgColor: '#621c85',
      pageBgEnabled: false
    },
    socialLinks: {
      instagram: '#',
      tiktok: '#',
      youtube: '#',
      facebook: '#'
    }
  }
};

// ─── DB Class ─────────────────────────────────────────────────────────────────

class SurteadosDB {
  constructor() {
    if (window.SURTEADOS_DATA) {
      this._serverData = window.SURTEADOS_DATA;
    } else {
      this._serverData = null;
      this._ensureDB();
    }
  }

  _ensureDB() {
    if (!localStorage.getItem(DB_KEY)) {
      localStorage.setItem(DB_KEY, JSON.stringify(SEED));
    }
  }

  _get() {
    return JSON.parse(localStorage.getItem(DB_KEY) || '{}');
  }

  _set(data) {
    localStorage.setItem(DB_KEY, JSON.stringify(data));
  }

  reset() {
    localStorage.setItem(DB_KEY, JSON.stringify(SEED));
  }

  // ─── Raffles ───────────────────────────────────────────────────────────────

  getRaffles() {
    if (this._serverData) return this._serverData.raffles || [];
    return this._get().raffles || [];
  }

  getRaffle(id) {
    return this.getRaffles().find(r => String(r.id) === String(id)) || null;
  }

  saveRaffle(raffle) {
    const db = this._get();
    const idx = db.raffles.findIndex(r => r.id === raffle.id);
    if (idx >= 0) {
      db.raffles[idx] = raffle;
    } else {
      db.raffles.push(raffle);
    }
    this._set(db);
    return raffle;
  }

  deleteRaffle(id) {
    const db = this._get();
    db.raffles = db.raffles.filter(r => r.id !== id);
    db.tickets = db.tickets.filter(t => t.raffleId !== id);
    this._set(db);
  }

  // ─── Tickets ───────────────────────────────────────────────────────────────

  getTickets(raffleId = null) {
    const tickets = this._get().tickets || [];
    return raffleId ? tickets.filter(t => t.raffleId === raffleId) : tickets;
  }

  getTicketsByEmail(email) {
    return this.getTickets().filter(t => t.buyerEmail.toLowerCase() === email.toLowerCase().trim());
  }

  addTicket(ticket) {
    const db = this._get();
    db.tickets.push(ticket);
    // Update sold count
    const rIdx = db.raffles.findIndex(r => r.id === ticket.raffleId);
    if (rIdx >= 0) {
      db.raffles[rIdx].soldTickets = (db.raffles[rIdx].soldTickets || 0) + ticket.ticketNumbers.length;
    }
    this._set(db);
    return ticket;
  }

  // ─── Winners ───────────────────────────────────────────────────────────────

  getWinners() {
    if (this._serverData) return this._serverData.winners || [];
    return this._get().winners || [];
  }

  addWinner(winner) {
    const db = this._get();
    db.winners.push(winner);
    this._set(db);
    return winner;
  }

  deleteWinner(id) {
    const db = this._get();
    db.winners = db.winners.filter(w => w.id !== id);
    this._set(db);
  }

  // ─── Settings ──────────────────────────────────────────────────────────────

  getSettings() {
    return this._get().settings || SEED.settings;
  }

  saveSettings(settings) {
    const db = this._get();
    if (!db.settings || typeof db.settings !== 'object') db.settings = {};
    db.settings = { ...db.settings, ...settings };
    this._set(db);
  }

  saveTheme(theme) {
    const db = this._get();
    if (!db.settings || typeof db.settings !== 'object') db.settings = {};
    if (!db.settings.theme || typeof db.settings.theme !== 'object') db.settings.theme = {};
    db.settings.theme = { ...db.settings.theme, ...theme };
    this._set(db);
  }
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

function generateId(prefix = 'id') {
  return prefix + Date.now().toString(36) + Math.random().toString(36).slice(2, 6);
}

function generateTicketNumber(existing = []) {
  let num;
  do {
    num = String(Math.floor(Math.random() * 999999)).padStart(6, '0');
  } while (existing.includes(num));
  return num;
}

function formatPrice(n) {
  return '$' + Number(n).toLocaleString('es-CL');
}

function formatDate(dateStr) {
  const d = new Date(dateStr);
  return d.toLocaleDateString('es-CL', { day: '2-digit', month: 'long', year: 'numeric' });
}

function formatDateTime(dateStr) {
  const d = new Date(dateStr);
  return d.toLocaleDateString('es-CL', { day: '2-digit', month: 'long', year: 'numeric' })
    + ' a las '
    + d.toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit' }) + ' hrs';
}

function getTimeLeft(dateStr) {
  const now = new Date();
  const target = new Date(dateStr);
  const diff = target - now;
  if (diff <= 0) return { days: 0, hours: 0, minutes: 0, seconds: 0 };
  const days    = Math.floor(diff / 86400000);
  const hours   = Math.floor((diff % 86400000) / 3600000);
  const minutes = Math.floor((diff % 3600000) / 60000);
  const seconds = Math.floor((diff % 60000) / 1000);
  return { days, hours, minutes, seconds };
}

function percentage(sold, total) {
  if (!total) return 0;
  return Math.min(100, Math.round((sold / total) * 100));
}

function applyTheme(theme) {
  const r = document.documentElement.style;
  if (theme.primary)      r.setProperty('--color-primary',       theme.primary);
  if (theme.primaryLight) r.setProperty('--color-primary-light', theme.primaryLight);
  if (theme.primaryDark)  r.setProperty('--color-primary-dark',  theme.primaryDark);
  if (theme.accent)       r.setProperty('--color-accent',        theme.accent);
  if (theme.accentLight)  r.setProperty('--color-accent-light',  theme.accentLight);
  if (theme.accentDark)   r.setProperty('--color-accent-dark',   theme.accentDark);
  if (theme.headerBg) {
    r.setProperty('--nav-bg', theme.headerBg);
    r.setProperty('--nav-bg-scrolled', theme.headerBg);
    r.setProperty('--header-accent', theme.headerBg);
  }
  if (theme.menuTextColor) r.setProperty('--nav-menu-text', theme.menuTextColor);
  if (theme.pageBgColor) r.setProperty('--page-bg-custom', theme.pageBgColor);

  if (Object.prototype.hasOwnProperty.call(theme, 'pageBgEnabled')) {
    const enabled = theme.pageBgEnabled === true || theme.pageBgEnabled === 1 || theme.pageBgEnabled === '1';
    document.body.classList.toggle('theme-bg-enabled', enabled);
  }
}

// Lighten/darken hex color
function lightenColor(hex, amount = 0.2) {
  const num = parseInt(hex.replace('#',''), 16);
  const r = Math.min(255, Math.floor((num >> 16) + (255 - (num >> 16)) * amount));
  const g = Math.min(255, Math.floor(((num >> 8) & 0xff) + (255 - ((num >> 8) & 0xff)) * amount));
  const b = Math.min(255, Math.floor((num & 0xff) + (255 - (num & 0xff)) * amount));
  return '#' + [r,g,b].map(x => x.toString(16).padStart(2,'0')).join('');
}
function darkenColor(hex, amount = 0.2) {
  const num = parseInt(hex.replace('#',''), 16);
  const r = Math.max(0, Math.floor((num >> 16) * (1 - amount)));
  const g = Math.max(0, Math.floor(((num >> 8) & 0xff) * (1 - amount)));
  const b = Math.max(0, Math.floor((num & 0xff) * (1 - amount)));
  return '#' + [r,g,b].map(x => x.toString(16).padStart(2,'0')).join('');
}

// Global DB instance
const db = new SurteadosDB();

// Apply saved theme on load
(function() {
  const settings = db.getSettings();
  if (settings?.theme) applyTheme(settings.theme);
})();

// ─── Toast Notification ───────────────────────────────────────────────────────

function showToast(msg, type = 'info', duration = 4000) {
  let container = document.getElementById('toast-container');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toast-container';
    container.className = 'toast-container';
    document.body.appendChild(container);
  }
  const icons = { info: 'ℹ️', success: '✅', error: '❌', warning: '⚠️' };
  const toast = document.createElement('div');
  toast.className = `toast ${type}`;
  toast.innerHTML = `
    <span class="toast-icon">${icons[type] || 'ℹ️'}</span>
    <span class="toast-msg">${msg}</span>
    <span class="toast-close" onclick="this.parentElement.remove()">✕</span>`;
  container.appendChild(toast);
  setTimeout(() => {
    toast.style.animation = 'slide-out 0.3s ease forwards';
    setTimeout(() => toast.remove(), 300);
  }, duration);
}
