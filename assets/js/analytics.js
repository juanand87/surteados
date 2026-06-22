(function () {
  'use strict';

  const scriptUrl = document.currentScript?.src || window.location.href;
  const endpoint = new URL('../../api/analytics.php', scriptUrl).href;
  const VISITOR_KEY = 'surteados_analytics_visitor';
  const SESSION_KEY = 'surteados_analytics_session';
  const SESSION_LAST_KEY = 'surteados_analytics_session_last';
  const CART_KEY = 'surteados_analytics_cart';
  const SESSION_TIMEOUT = 30 * 60 * 1000;

  function makeId(prefix) {
    const raw = window.crypto?.randomUUID?.() || `${Date.now().toString(36)}_${Math.random().toString(36).slice(2)}`;
    return `${prefix}_${raw.replace(/[^A-Za-z0-9_-]/g, '')}`.slice(0, 64);
  }

  function storedId(storage, key, prefix) {
    let value = storage.getItem(key) || '';
    if (!/^[A-Za-z0-9_-]{8,64}$/.test(value)) {
      value = makeId(prefix);
      storage.setItem(key, value);
    }
    return value;
  }

  const visitorId = storedId(localStorage, VISITOR_KEY, 'v');
  const previousActivity = Number(sessionStorage.getItem(SESSION_LAST_KEY) || 0);
  if (!previousActivity || Date.now() - previousActivity > SESSION_TIMEOUT) {
    sessionStorage.removeItem(SESSION_KEY);
  }
  const sessionId = storedId(sessionStorage, SESSION_KEY, 's');
  const viewId = makeId('p');
  sessionStorage.setItem(SESSION_LAST_KEY, String(Date.now()));

  function cartId(create = true) {
    let value = localStorage.getItem(CART_KEY) || '';
    if (create && !/^[A-Za-z0-9_-]{8,64}$/.test(value)) {
      value = makeId('c');
      localStorage.setItem(CART_KEY, value);
    }
    return value;
  }

  function raffleIdFromPage() {
    if (!/ver-sorteo\.php$/i.test(window.location.pathname)) return '';
    return new URLSearchParams(window.location.search).get('id') || '';
  }

  function deviceType() {
    if (window.matchMedia('(max-width: 768px)').matches) return 'mobile';
    if (window.matchMedia('(max-width: 1024px)').matches) return 'tablet';
    return 'desktop';
  }

  async function send(payload, useBeacon = false) {
    const body = JSON.stringify({ sessionId, visitorId, ...payload });
    if (useBeacon && navigator.sendBeacon) {
      navigator.sendBeacon(endpoint, new Blob([body], { type: 'application/json' }));
      return;
    }
    try {
      await fetch(endpoint, {
        method: 'POST',
        credentials: 'same-origin',
        keepalive: true,
        headers: { 'Content-Type': 'application/json' },
        body,
      });
    } catch (_) {
      // Analytics must never interrupt the purchase experience.
    }
  }

  const params = new URLSearchParams(window.location.search);
  send({
    action: 'page_start',
    viewId,
    path: window.location.pathname,
    raffleId: raffleIdFromPage(),
    referrer: document.referrer || '',
    utmSource: params.get('utm_source') || '',
    utmMedium: params.get('utm_medium') || '',
    utmCampaign: params.get('utm_campaign') || '',
    deviceType: deviceType(),
  });

  let lastInteraction = Date.now();
  ['pointerdown', 'keydown', 'scroll', 'touchstart'].forEach((name) => {
    window.addEventListener(name, () => { lastInteraction = Date.now(); }, { passive: true });
  });

  function heartbeat(useBeacon = false) {
    if ((!useBeacon && document.hidden) || Date.now() - lastInteraction > 60000) return;
    sessionStorage.setItem(SESSION_LAST_KEY, String(Date.now()));
    send({ action: 'heartbeat', viewId, seconds: 15 }, useBeacon);
  }
  setInterval(() => heartbeat(false), 15000);
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) heartbeat(true);
    else lastInteraction = Date.now();
  });
  window.addEventListener('pagehide', () => heartbeat(true));

  window.SurteadosAnalytics = {
    event(eventType, data = {}) {
      return send({
        action: 'event',
        eventType,
        raffleId: data.raffleId || '',
        cartId: data.cartId || cartId(false),
        step: data.step || null,
        metadata: data.metadata || {},
      });
    },
    syncCart(data = {}) {
      const currentCartId = cartId(true);
      const request = send({
        action: 'cart',
        cartId: currentCartId,
        items: Array.isArray(data.items) ? data.items : [],
        buyerName: data.buyerName || '',
        buyerEmail: data.buyerEmail || '',
        buyerPhone: data.buyerPhone || '',
        step: data.step || 1,
        status: data.status || 'active',
      });
      if (data.status === 'cleared' || data.status === 'converted') {
        localStorage.removeItem(CART_KEY);
      }
      return request;
    },
    markConverted(metadata = {}) {
      const currentCartId = cartId(false);
      send({ action: 'event', eventType: 'purchase_completed', cartId: currentCartId, metadata });
      if (currentCartId) localStorage.removeItem(CART_KEY);
    },
    getCartId() { return cartId(true); },
  };

  if (document.body?.dataset?.analyticsPaymentStatus === 'paid') {
    window.SurteadosAnalytics.markConverted({ source: 'payment_return' });
  }
})();