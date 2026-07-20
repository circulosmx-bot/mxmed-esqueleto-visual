(function () {
  'use strict';

  const allowedStates = new Set(['default', 'loading', 'error', 'success', 'expired']);
  const messages = {
    login: { loading: ['Procesando acceso', 'Estado visual provisional de carga.'], error: ['No pudimos iniciar sesión', 'Revisa tu correo electrónico y contraseña e inténtalo de nuevo.'], success: ['Acceso listo para revisión', 'La sesión se inició correctamente.'] },
    create: { error: ['Revisa los datos de tu cuenta', 'Completa los campos requeridos y los consentimientos para continuar.'], success: ['Solicitud de cuenta enviada', 'Revisa tu correo para continuar con la verificación.'] },
    verify: { loading: ['Verificando correo', 'Estado visual provisional de carga.'], error: ['No pudimos verificar tu correo', 'El enlace puede ser inválido o haber expirado.'], success: ['Correo verificado', 'Tu cuenta quedó lista para continuar.'], expired: ['El enlace ya no está disponible', 'Solicita un nuevo enlace para continuar.'] },
    recover: { error: ['Revisa tu correo electrónico', 'Ingresa un correo válido para continuar.'], success: ['Instrucciones enviadas', 'Si existe una cuenta asociada, recibirás instrucciones para recuperar el acceso.'] },
    reset: { error: ['Revisa tu nueva contraseña', 'Las contraseñas deben coincidir y cumplir los requisitos indicados.'], success: ['Contraseña actualizada', 'Tu contraseña quedó actualizada.'], expired: ['El enlace ya no está disponible', 'Solicita nuevas instrucciones para establecer una contraseña.'] },
  };

  function flowState(flow, state) {
    const panel = document.querySelector('[data-state-panel]');
    const message = messages[flow] && messages[flow][state];
    if (!panel || !message) return;
    panel.className = 'state-panel';
    panel.classList.add(state === 'success' ? 'state-panel--success' : state === 'error' || state === 'expired' ? 'state-panel--error' : 'state-panel--loading');
    panel.dataset.visible = 'true';
    panel.querySelector('[data-state-title]').textContent = message[0];
    panel.querySelector('[data-state-copy]').textContent = message[1];
    panel.setAttribute('role', state === 'error' || state === 'expired' ? 'alert' : 'status');
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
      const submit = form.querySelector('[data-submit]');
      if (submit) { submit.disabled = true; submit.setAttribute('aria-busy', 'true'); }
      flowState(flow, 'loading');
      try {
        const csrf = value(form, 'csrf_token');
        const response = await fetch(form.action, { method: 'POST', credentials: 'same-origin', cache: 'no-store', headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': csrf }, body: JSON.stringify(payloadFor(flow, form)) });
        const result = await response.json().catch(() => ({}));
        const state = response.ok && result.ok === true ? 'success' : 'error';
        flowState(flow, state);
        const note = form.querySelector('[data-form-note]');
        if (note) note.textContent = state === 'success' ? 'La acción se procesó correctamente.' : 'La acción no pudo completarse.';
        if (state === 'success' && (flow === 'verify' || flow === 'reset')) window.history.replaceState({}, document.title, window.location.pathname);
      } catch (_) {
        flowState(flow, 'error');
      } finally {
        if (submit) { submit.disabled = false; submit.removeAttribute('aria-busy'); }
      }
    });
  });
})();
