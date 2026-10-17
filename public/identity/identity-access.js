(function () {
  'use strict';

  const allowedStates = new Set(['default', 'loading', 'error', 'success', 'expired', 'logged-out', 'logout-error']);
  const messages = {
    login: {
      loading: ['Procesando acceso', 'Validando tu cuenta y perfil profesional.'],
      error: ['No pudimos iniciar sesión', 'Revisa tu correo electrónico y contraseña e inténtalo de nuevo.'],
      success: ['Acceso listo', 'La sesión se inició correctamente.'],
      expired: ['Tu sesión ha finalizado', 'Inicia sesión nuevamente para continuar.'],
      'logged-out': ['Sesión cerrada', 'Tu sesión se cerró correctamente.'],
      'logout-error': ['No pudimos confirmar el cierre', 'Inicia sesión nuevamente o vuelve a intentarlo.']
    },
    create: { error: ['Revisa los datos de tu cuenta', 'Completa los campos requeridos y los consentimientos para continuar.'], success: ['Solicitud de cuenta enviada', 'Revisa tu correo para continuar con la verificación.'] },
    verify: { loading: ['Verificando correo', 'Estado visual provisional de carga.'], error: ['No pudimos verificar tu correo', 'El enlace puede ser inválido o haber expirado.'], success: ['Correo verificado', 'Tu cuenta quedó lista para continuar.'], expired: ['El enlace ya no está disponible', 'Solicita un nuevo enlace para continuar.'] },
    recover: { error: ['Revisa tu correo electrónico', 'Ingresa un correo válido para continuar.'], success: ['Instrucciones enviadas', 'Si existe una cuenta asociada, recibirás instrucciones para recuperar el acceso.'] },
    reset: { error: ['Revisa tu nueva contraseña', 'Las contraseñas deben coincidir y cumplir los requisitos indicados.'], success: ['Contraseña actualizada', 'Tu contraseña quedó actualizada.'], expired: ['El enlace ya no está disponible', 'Solicita nuevas instrucciones para establecer una contraseña.'] },
  };

  function flowState(flow, state, detail) {
    const panel = document.querySelector('[data-state-panel]');
    const message = messages[flow] && messages[flow][state];
    if (!panel || !message) return;
    panel.className = 'state-panel';
    panel.classList.add(state === 'success' || state === 'logged-out' ? 'state-panel--success' : state === 'error' || state === 'expired' || state === 'logout-error' ? 'state-panel--error' : 'state-panel--loading');
    panel.dataset.visible = 'true';
    panel.querySelector('[data-state-title]').textContent = message[0];
    panel.querySelector('[data-state-copy]').textContent = detail || message[1];
    panel.setAttribute('role', state === 'error' || state === 'expired' || state === 'logout-error' ? 'alert' : 'status');
  }

  function setInitialState(flow) {
    const requested = new URLSearchParams(window.location.search).get('state') || 'default';
    const state = allowedStates.has(requested) ? requested : 'default';
    if (state !== 'default') flowState(flow, state);
    if (state === 'loading') {
      const submit = document.querySelector('[data-submit]');
      if (submit) { submit.disabled = true; submit.setAttribute('aria-busy', 'true'); }
    }
  }

  function value(form, name) {
    const field = form.elements.namedItem(name);
    return field && typeof field.value === 'string' ? field.value : '';
  }

  function boolValue(form, name) {
    const field = form.elements.namedItem(name);
    return !!(field && field.checked);
  }

  function passwordValidationMessage(form, compareEmail) {
    const consent = 'Debes aceptar los Términos y condiciones y el Aviso de privacidad para continuar.';
    const email = form.elements.namedItem('email');
    const password = value(form, 'password');
    const confirmation = value(form, 'password_confirmation');
    const minimum = Number(form.dataset.passwordMin);
    const maximum = Number(form.dataset.passwordMax);
    const passwordLength = Array.from(password).length;
    if (compareEmail && (!email || email.value.trim() === '' || email.validity.typeMismatch)) return 'Revisa el correo electrónico y la contraseña.';
    if (passwordLength < minimum) return 'La contraseña debe tener al menos 12 caracteres.';
    if (passwordLength > maximum) return 'La contraseña no puede tener más de 64 caracteres.';
    if (compareEmail && password.toLowerCase() === email.value.trim().toLowerCase()) return 'La contraseña debe ser distinta de tu correo electrónico.';
    if (password !== confirmation) return 'Las contraseñas no coinciden.';
    if (compareEmail && (!boolValue(form, 'terms') || !boolValue(form, 'privacy'))) return consent;
    return '';
  }

  function payloadFor(flow, form) {
    const payload = { csrf_token: value(form, 'csrf_token') };
    if (flow === 'login') {
      payload.email = value(form, 'email');
      payload.password = value(form, 'password');
    } else if (flow === 'create') {
      payload.email = value(form, 'email');
      payload.password = value(form, 'password');
      payload.password_confirmation = value(form, 'password_confirmation');
      payload.terms_accepted = boolValue(form, 'terms');
      payload.privacy_notice_accepted = boolValue(form, 'privacy');
      payload.terms_version = 'v2';
      payload.privacy_notice_version = 'v2';
      const enrollmentReturn=document.body.dataset.enrollmentReturn;
      if(enrollmentReturn)payload.enrollment_return=enrollmentReturn;
    } else if (flow === 'verify') {
      payload.token = new URLSearchParams(window.location.search).get('token') || '';
    } else if (flow === 'recover') {
      payload.email = value(form, 'email');
    } else if (flow === 'reset') {
      payload.token = new URLSearchParams(window.location.search).get('token') || '';
      payload.password = value(form, 'password');
      payload.password_confirmation = value(form, 'password_confirmation');
    }
    return payload;
  }

  function safePrivateDestination() {
    if(/^#operator-invitation=[A-Za-z0-9_.%~-]{20,512}$/.test(location.hash))return '/private-app.php'+location.hash;
    const raw = new URLSearchParams(window.location.search).get('next') || '/private-app.php';
    try {
      const parsed = new URL(raw, window.location.origin);
      if (parsed.origin !== window.location.origin) return '/private-app.php';
      const allowed = parsed.pathname === '/' || parsed.pathname === '/index.html'
        || parsed.pathname === '/private-app.php' || parsed.pathname === '/provider/'
        || parsed.pathname === '/public/identity/perfil-medico.php'
        || (parsed.pathname === '/internal/media-review/index.php' && parsed.search === '?section=acreditacion')
        || (parsed.pathname === '/internal/media-review/index.php' && parsed.search === '?section=correcciones' && !parsed.hash);
      return allowed ? parsed.pathname + parsed.search + parsed.hash : '/private-app.php';
    } catch (_) {
      return '/private-app.php';
    }
  }

  async function routeAfterLogin() {
    const response = await fetch('/api/identity/index.php/current-session', {
      credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' }
    });
    const session = await response.json().catch(() => ({}));
    if (!response.ok || session.authenticated !== true) throw new Error('session_validation_failed');
    const destination = safePrivateDestination();
    const onboarding = destination.startsWith('/public/identity/perfil-medico.php') || destination === '/internal/media-review/index.php?section=acreditacion';
    const correctionReview = destination === '/internal/media-review/index.php?section=correcciones';
    if (destination !== '/provider/' && !onboarding && !correctionReview && session.application_access?.allowed !== true) {
      throw new Error('professional_access_required');
    }
    const signal = JSON.stringify({ type: 'authenticated', at: Date.now() });
    try {
      const channel = new BroadcastChannel('mxmed-session');
      channel.postMessage(JSON.parse(signal));
      channel.close();
    } catch (_) {}
    try { window.localStorage.setItem('mxmed.session.signal', signal); } catch (_) {}
    window.location.replace(destination === '/' || destination === '/index.html' ? '/private-app.php' : destination);
  }

  document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
      const input = document.getElementById(button.getAttribute('aria-controls'));
      if (!input) return;
      const visible = input.type === 'text';
      input.type = visible ? 'password' : 'text';
      button.textContent = visible ? 'Mostrar' : 'Ocultar';
      button.setAttribute('aria-label', visible ? 'Mostrar contraseña' : 'Ocultar contraseña');
    });
  });

  document.querySelectorAll('[data-identity-form]').forEach((form) => {
    const flow = form.dataset.flow || '';
    setInitialState(flow);
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const validationMessage = flow === 'create' ? passwordValidationMessage(form, true) : (flow === 'reset' ? passwordValidationMessage(form, false) : '');
      if (validationMessage) {
        flowState(flow, 'error', validationMessage);
        const note = form.querySelector('[data-form-note]');
        if (note) note.textContent = validationMessage;
        return;
      }
      const submit = form.querySelector('[data-submit]');
      if (submit) { submit.disabled = true; submit.setAttribute('aria-busy', 'true'); }
      flowState(flow, 'loading');
      try {
        const csrf = value(form, 'csrf_token');
        const response = await fetch(form.action, { method: 'POST', credentials: 'same-origin', cache: 'no-store', headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': csrf }, body: JSON.stringify(payloadFor(flow, form)) });
        const result = await response.json().catch(() => ({}));
        const state = response.ok && result.ok === true ? 'success' : 'error';
        const safeDetail = response.status === 422 && typeof result.message === 'string' ? result.message : '';
        flowState(flow, state, safeDetail);
        const note = form.querySelector('[data-form-note]');
        if (note) note.textContent = state === 'success' ? 'La acción se procesó correctamente.' : (safeDetail || 'La acción no pudo completarse.');
        if (state === 'success' && flow === 'login') {
          try {
            await routeAfterLogin();
          } catch (error) {
            const detail = error.message === 'professional_access_required'
              ? 'Tu cuenta no tiene un perfil profesional único y activo para abrir esta aplicación.'
              : 'La sesión inició, pero no fue posible validar el acceso a la aplicación.';
            flowState(flow, 'error', detail);
            if (note) note.textContent = detail;
          }
          return;
        }
        if (state === 'success' && (flow === 'verify' || flow === 'reset')) window.history.replaceState({}, document.title, window.location.pathname);
        if (state === 'success' && (flow === 'create' || flow === 'verify')) {
          const next = document.querySelector('[data-onboarding-next]');
          if (next) next.hidden = false;
        }
      } catch (_) {
        flowState(flow, 'error');
      } finally {
        if (submit) { submit.disabled = false; submit.removeAttribute('aria-busy'); }
      }
    });
  });
})();
