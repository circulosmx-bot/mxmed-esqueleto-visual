(function () {
  'use strict';

  const LOGIN_ROUTE = '/public/identity/acceso.php';
  const SESSION_ENDPOINT = '/api/identity/index.php/current-session';
  const LOGOUT_ENDPOINT = '/api/identity/index.php/logout';
  const CHANNEL_NAME = 'mxmed-session';
  const SIGNAL_KEY = 'mxmed.session.signal';
  const EXPIRED_MESSAGE = 'Tu sesión ha finalizado. Inicia sesión nuevamente para continuar.';
  const SENSITIVE_EXACT = new Set([
    'mxmed.activeContext', 'mxmed_last_panel', 'mxmed_btn_pacientes',
    'mxmed.qa.realEntity', 'mxmed.qa.classification.v1', 'mxmed.dev.rbac.role',
    'mxmedActivePatientId', 'mxmed:consentimiento:draft:v1',
    'mxmed:consentimiento:draft:ignored:v1', 'mxmed.agenda.operators.state.v1',
    'mxmed.agenda.operators.create_draft.v1'
  ]);
  const SENSITIVE_PREFIXES = [
    'dp:', 'mxmed_rx_draft_v1:', 'mxmed_evolution_notes_v1:',
    'mxmed_rx_doctor_memory_v1:', 'mxmed.m7.', 'mxmed-plan02b:',
    'mxmed.agenda.', 'mxmed_appointment_', 'mxmed.operator.'
  ];

  const nativeFetch = window.fetch.bind(window);
  const controllers = new Set();
  const xhrs = new Set();
  let generation = 0;
  let locked = false;
  let expiryTimer = 0;
  let channel = null;
  let confirmationFlight = null;
  let sessionMarker = '';

  function readBootstrap() {
    try {
      return JSON.parse(document.getElementById('mxmed-private-bootstrap')?.textContent || '{}');
    } catch (_) {
      return {};
    }
  }

  const manager = {
    state: 'checking',
    context: readBootstrap(),
    csrfToken: '',
    validate,
    logout,
    lock,
    clearSensitiveState,
    endpoints: Object.freeze({ session: SESSION_ENDPOINT, logout: LOGOUT_ENDPOINT })
  };
  manager.csrfToken = String(manager.context.csrf_token || '');
  window.mxmedPrivateSession = manager;

  function canonicalContext(context) {
    const account = context?.account || {};
    const professional = context?.professional || {};
    const doctorId = String(professional.doctor_id || '');
    const accountId = String(account.id || '');
    const displayName = String(professional.display_name || '');
    window.mxmedStore = window.mxmedStore && typeof window.mxmedStore === 'object' ? window.mxmedStore : {};
    Object.assign(window.mxmedStore, {
      doctor_id: doctorId, doctorId,
      user_id: accountId, userId: accountId,
      doctorName: displayName,
      capabilities: Object.assign({}, context?.capabilities || {})
    });
    window.mxmedStore.doctorProfile = Object.assign({}, window.mxmedStore.doctorProfile || {}, {
      doctor_id: doctorId, user_id: accountId, full_name: displayName
    });
    window.mxmedStore.activeProfessionalContext = {
      doctor_id: doctorId,
      user_id: accountId,
      role: String(professional.role || ''),
      scope: String(professional.scope || '')
    };
    window.mxmedDoctor = {
      doctor_id: doctorId,
      user_id: accountId,
      full_name: displayName,
      role: String(professional.role || ''),
      scope: String(professional.scope || '')
    };
  }

  canonicalContext(manager.context);

  function isSensitiveKey(key) {
    return SENSITIVE_EXACT.has(key) || SENSITIVE_PREFIXES.some((prefix) => key.startsWith(prefix));
  }

  function clearStorage(storage) {
    if (!storage) return;
    const keys = [];
    for (let index = 0; index < storage.length; index += 1) {
      const key = storage.key(index);
      if (key && isSensitiveKey(key)) keys.push(key);
    }
    keys.forEach((key) => storage.removeItem(key));
  }

  function clearSensitiveState() {
    try { clearStorage(window.localStorage); } catch (_) {}
    try { clearStorage(window.sessionStorage); } catch (_) {}
    window.mxmedStore = {};
    window.mxmedDoctor = {};
    window.dispatchEvent(new CustomEvent('mxmed:private-state-cleared'));
  }

  function abortPending() {
    generation += 1;
    controllers.forEach((controller) => controller.abort());
    controllers.clear();
    xhrs.forEach((xhr) => { try { xhr.abort(); } catch (_) {} });
    xhrs.clear();
  }

  function showLock(message, allowRetry) {
    let overlay = document.getElementById('mxmed-session-lock');
    if (!overlay) {
      overlay = document.createElement('div');
      overlay.id = 'mxmed-session-lock';
      overlay.className = 'mxmed-session-lock';
      overlay.setAttribute('role', 'alert');
      overlay.innerHTML = '<div class="mxmed-session-lock__card"><h1>Sesión de México Médico</h1><p data-session-lock-message></p><button type="button" data-session-retry hidden>Intentar de nuevo</button></div>';
      (document.body || document.documentElement).appendChild(overlay);
    }
    overlay.querySelector('[data-session-lock-message]').textContent = message;
    const retry = overlay.querySelector('[data-session-retry]');
    retry.hidden = !allowRetry;
    retry.onclick = allowRetry ? () => window.location.reload() : null;
    Array.from(document.body?.children || []).forEach((element) => {
      if (element !== overlay) element.inert = true;
    });
    overlay.inert = false;
    document.documentElement.classList.remove('mxmed-auth-checking');
  }

  function safeNext() {
    return '/private-app.php' + window.location.search + window.location.hash;
  }

  function loginUrl(state) {
    return LOGIN_ROUTE + '?next=' + encodeURIComponent(safeNext()) + (state ? '&state=' + encodeURIComponent(state) : '');
  }

  function broadcast(type) {
    const signal = { type, at: Date.now() };
    try { channel?.postMessage(signal); } catch (_) {}
    try { window.localStorage.setItem(SIGNAL_KEY, JSON.stringify(signal)); } catch (_) {}
  }

  function lock(reason, options) {
    if (locked) return;
    locked = true;
    manager.state = 'locked';
    window.clearTimeout(expiryTimer);
    abortPending();
    clearSensitiveState();
    document.documentElement.classList.add('mxmed-auth-checking');
    const opts = options || {};
    showLock(opts.message || EXPIRED_MESSAGE, opts.retry === true);
    if (opts.broadcast !== false) broadcast(reason || 'expired');
    if (opts.redirect !== false) {
      window.setTimeout(() => window.location.replace(loginUrl(opts.state || 'expired')), 900);
    }
  }

  function lockUnavailable(message) {
    if (locked) return;
    locked = true;
    manager.state = 'unavailable';
    window.clearTimeout(expiryTimer);
    abortPending();
    clearSensitiveState();
    document.documentElement.classList.add('mxmed-auth-checking');
    showLock(message || 'No fue posible validar tu sesión. Inténtalo de nuevo.', true);
  }

  function scheduleExpiry(context) {
    window.clearTimeout(expiryTimer);
    const expiries = [context?.session?.idle_expires_at, context?.session?.absolute_expires_at]
      .map((value) => Date.parse(String(value || '')))
      .filter(Number.isFinite);
    if (!expiries.length) return;
    const delay = Math.max(0, Math.min(...expiries) - Date.now() + 250);
    expiryTimer = window.setTimeout(() => validate('expiry'), Math.min(delay, 2147483647));
  }

  function applyCapabilities(context) {
    const capabilities = context?.capabilities || {};
    document.querySelectorAll('[data-required-capability]').forEach((element) => {
      const allowed = capabilities[element.dataset.requiredCapability] === true;
      element.hidden = !allowed;
      element.setAttribute('aria-disabled', allowed ? 'false' : 'true');
    });
    const canonicalNavigation = [
      { selectors: ['[data-panel="p-expediente"]', '#p-expediente'], requires: ['patients', 'clinical_record'] },
      { selectors: ['[data-panel="p-pac-recetas"]', '#p-pac-recetas'], requires: ['patients', 'prescriptions'] }
    ];
    canonicalNavigation.forEach((rule) => {
      const allowed = rule.requires.every((capability) => capabilities[capability] === true);
      document.querySelectorAll(rule.selectors.join(',')).forEach((element) => {
        element.hidden = !allowed;
        element.setAttribute('aria-disabled', allowed ? 'false' : 'true');
      });
    });
    const sessionLabel = document.querySelector('.mx-hb-session-label');
    if (sessionLabel) sessionLabel.textContent = 'Sesión iniciada';
  }

  function hasAuthenticatedEnvelope(payload) {
    return payload?.authenticated === true
      && payload?.state === 'AUTHENTICATED'
      && payload?.account_status === 'active'
      && String(payload?.csrf_token || '') !== '';
  }

  function isAuthenticatedPayload(payload) {
    const context = payload?.application_access?.context;
    return hasAuthenticatedEnvelope(payload)
      && payload?.application_access?.allowed === true
      && context && typeof context === 'object'
      && context.account && typeof context.account === 'object'
      && context.professional && typeof context.professional === 'object';
  }

  function applyAuthenticated(payload) {
    const marker = String(payload.application_access.context.account.id || '') + ':' + String(payload.csrf_token || '');
    if (sessionMarker && marker !== sessionMarker) generation += 1;
    sessionMarker = marker;
    manager.context = payload.application_access.context;
    manager.csrfToken = String(payload.csrf_token || '');
    manager.state = 'authenticated';
    canonicalContext(manager.context);
    applyCapabilities(manager.context);
    scheduleExpiry(manager.context);
    document.documentElement.classList.remove('mxmed-auth-checking');
    window.dispatchEvent(new CustomEvent('mxmed:session-authenticated', { detail: manager.context }));
    return true;
  }

  function confirmCanonicalSession() {
    const requestGeneration = generation;
    if (confirmationFlight?.generation === requestGeneration) return confirmationFlight.promise;
    const promise = (async () => {
      const response = await nativeFetch(SESSION_ENDPOINT, {
        credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' }
      }).catch(() => null);
      if (!response) return { kind: 'unavailable', generation: requestGeneration };
      const payload = await response.json().catch(() => null);
      if (response.status === 401) return { kind: 'expired', generation: requestGeneration };
      if (response.status === 403) return { kind: 'forbidden', generation: requestGeneration };
      if (!response.ok) return { kind: 'unavailable', generation: requestGeneration };
      if (hasAuthenticatedEnvelope(payload) && payload?.application_access?.allowed !== true) {
        return { kind: 'forbidden', generation: requestGeneration };
      }
      if (!isAuthenticatedPayload(payload)) return { kind: 'unavailable', generation: requestGeneration };
      return { kind: 'authenticated', generation: requestGeneration, payload };
    })();
    confirmationFlight = { generation: requestGeneration, promise };
    promise.finally(() => {
      if (confirmationFlight?.promise === promise) confirmationFlight = null;
    });
    return promise;
  }

  async function reconcileCanonicalSession(reason, options) {
    if (locked) return false;
    const result = await confirmCanonicalSession();
    if (result.generation !== generation || locked) return false;
    if (result.kind === 'authenticated') return applyAuthenticated(result.payload);
    if (result.kind === 'expired') {
      lock(reason === 'logout' ? 'logout' : 'expired', {
        state: reason === 'logout' ? 'logged-out' : 'expired',
        broadcast: options?.broadcast !== false
      });
      return false;
    }
    if (result.kind === 'forbidden') {
      lock('access-denied', {
        message: 'Tu cuenta no tiene acceso vigente a esta aplicación.',
        redirect: false,
        broadcast: false
      });
      return false;
    }
    lockUnavailable();
    return false;
  }

  async function validate(reason) {
    return reconcileCanonicalSession(reason || 'validation', { broadcast: reason !== 'bootstrap' });
  }

  async function handleResourceUnauthorized(requestGeneration) {
    if (locked || requestGeneration !== generation) return;
    await reconcileCanonicalSession('expired', { broadcast: true });
  }

  function isPrivateApi(input) {
    try {
      const raw = typeof input === 'string' ? input : input.url;
      const url = new URL(raw, window.location.href);
      return url.origin === window.location.origin && url.pathname.startsWith('/api/') && url.pathname !== SESSION_ENDPOINT;
    } catch (_) {
      return false;
    }
  }

  window.fetch = async function guardedFetch(input, init) {
    if (!isPrivateApi(input)) return nativeFetch(input, init);
    if (locked) return Promise.reject(new DOMException('Private session is locked', 'AbortError'));
    const requestGeneration = generation;
    const controller = new AbortController();
    const externalSignal = init?.signal;
    if (externalSignal) {
      if (externalSignal.aborted) controller.abort();
      else externalSignal.addEventListener('abort', () => controller.abort(), { once: true });
    }
    controllers.add(controller);
    try {
      const guardedInit = Object.assign({}, init || {}, { signal: controller.signal });
      try {
        const raw = typeof input === 'string' ? input : input.url;
        const url = new URL(raw, window.location.href);
        const method = String(guardedInit.method || (typeof input !== 'string' ? input.method : 'GET') || 'GET').toUpperCase();
        if (url.origin === window.location.origin && url.pathname.startsWith('/api/agenda/') && !['GET', 'HEAD'].includes(method)) {
          const headers = new Headers(guardedInit.headers || (typeof input !== 'string' ? input.headers : undefined));
          headers.set('X-CSRF-Token', manager.csrfToken);
          guardedInit.headers = headers;
        }
      } catch (_) {}
      const response = await nativeFetch(input, guardedInit);
      if (requestGeneration !== generation || locked) throw new DOMException('Stale private response', 'AbortError');
      if (response.status === 401) await handleResourceUnauthorized(requestGeneration);
      if (response.status === 403) window.dispatchEvent(new CustomEvent('mxmed:authorization-denied', { detail: { input } }));
      return response;
    } finally {
      controllers.delete(controller);
    }
  };

  const nativeXhrOpen = XMLHttpRequest.prototype.open;
  const nativeXhrSend = XMLHttpRequest.prototype.send;
  XMLHttpRequest.prototype.open = function guardedOpen(method, url) {
    this.__mxmedPrivateApi = isPrivateApi(url);
    this.__mxmedGeneration = generation;
    return nativeXhrOpen.apply(this, arguments);
  };
  XMLHttpRequest.prototype.send = function guardedSend() {
    if (!this.__mxmedPrivateApi) return nativeXhrSend.apply(this, arguments);
    if (locked) { this.abort(); return; }
    xhrs.add(this);
    this.addEventListener('loadend', () => {
      xhrs.delete(this);
      if (locked) return;
      if (this.status === 401) void handleResourceUnauthorized(this.__mxmedGeneration);
      if (this.status === 403) window.dispatchEvent(new CustomEvent('mxmed:authorization-denied', { detail: { transport: 'xhr' } }));
    }, { once: true });
    return nativeXhrSend.apply(this, arguments);
  };

  async function logout() {
    if (locked || manager.state !== 'authenticated') return false;
    locked = true;
    manager.state = 'logging-out';
    abortPending();
    document.documentElement.classList.add('mxmed-auth-checking');
    showLock('Cerrando tu sesión…', false);
    try {
      const response = await nativeFetch(LOGOUT_ENDPOINT, {
        method: 'POST', credentials: 'same-origin', cache: 'no-store',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': manager.csrfToken },
        body: JSON.stringify({ csrf_token: manager.csrfToken })
      });
      const result = await response.json().catch(() => ({}));
      if (!response.ok || result.ok !== true || result.state !== 'LOGOUT_SUCCESS') throw new Error('logout_failed');
      clearSensitiveState();
      broadcast('logout');
      window.location.replace(LOGIN_ROUTE + '?state=logged-out');
      return true;
    } catch (_) {
      manager.state = 'logout-error';
      showLock('No fue posible cerrar sesión. Tu acceso permanece bloqueado en esta pestaña; inténtalo de nuevo.', true);
      return false;
    }
  }

  async function reconcileSignal(signal) {
    if (!signal || locked) return;
    if (signal.type === 'authenticated') {
      generation += 1;
      await validate('authenticated-signal');
      return;
    }
    if (signal.type === 'logout' || signal.type === 'expired') {
      await reconcileCanonicalSession(signal.type, { broadcast: false });
    }
  }

  try {
    channel = new BroadcastChannel(CHANNEL_NAME);
    channel.addEventListener('message', (event) => { void reconcileSignal(event.data); });
  } catch (_) {}
  window.addEventListener('storage', (event) => {
    if (event.key !== SIGNAL_KEY || !event.newValue) return;
    try {
      const signal = JSON.parse(event.newValue);
      void reconcileSignal(signal);
    } catch (_) {}
  });
  window.addEventListener('focus', () => validate('focus'));
  document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') validate('visible'); });
  window.addEventListener('pagehide', () => document.documentElement.classList.add('mxmed-auth-checking'));
  window.addEventListener('pageshow', () => validate('pageshow'));

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => validate('bootstrap'), { once: true });
  else validate('bootstrap');
})();
