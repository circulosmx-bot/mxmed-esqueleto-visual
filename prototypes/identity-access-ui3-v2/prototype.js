(function () {
  'use strict';

  const params = new URLSearchParams(window.location.search);
  const requestedState = params.get('state') || 'default';
  const allowedStates = new Set(['default', 'loading', 'error', 'success', 'expired']);
  const state = allowedStates.has(requestedState) ? requestedState : 'default';
  const flow = document.body.dataset.flow || 'index';

  const messages = {
    login: {
      loading: ['Procesando acceso', 'Estado visual provisional de carga.'],
      error: ['No pudimos iniciar sesión', 'Revisa tu correo electrónico y contraseña e inténtalo de nuevo.'],
      success: ['Acceso listo para revisión', 'Este prototipo no inicia una sesión real.'],
    },
    create: {
      error: ['Revisa los datos de tu cuenta', 'Completa los campos requeridos y los consentimientos para continuar.'],
      success: ['Solicitud de cuenta enviada', 'Este prototipo sólo muestra el estado visual de confirmación.'],
    },
    verify: {
      loading: ['Verificando correo', 'Estado visual provisional de carga.'],
      error: ['No pudimos verificar tu correo', 'El enlace puede ser inválido o haber expirado.'],
      success: ['Correo verificado', 'Este prototipo sólo muestra el estado visual de confirmación.'],
      expired: ['El enlace ya no está disponible', 'Solicita un nuevo enlace para continuar.'],
    },
    recover: {
      error: ['Revisa tu correo electrónico', 'Ingresa un correo válido para continuar.'],
      success: ['Instrucciones enviadas', 'Si existe una cuenta asociada, recibirás instrucciones para recuperar el acceso.'],
    },
    reset: {
      error: ['Revisa tu nueva contraseña', 'Las contraseñas deben coincidir y cumplir los requisitos indicados.'],
      success: ['Contraseña actualizada', 'Este prototipo sólo muestra el estado visual de confirmación.'],
      expired: ['El enlace ya no está disponible', 'Solicita nuevas instrucciones para establecer una contraseña.'],
    },
  };

  const message = messages[flow] && messages[flow][state];
  const panel = document.querySelector('[data-state-panel]');
  if (panel && message) {
    panel.dataset.visible = 'true';
    panel.classList.add(state === 'success' ? 'state-panel--success' : state === 'error' || state === 'expired' ? 'state-panel--error' : 'state-panel--loading');
    panel.querySelector('[data-state-title]').textContent = message[0];
    panel.querySelector('[data-state-copy]').textContent = message[1];
    panel.setAttribute('role', state === 'error' || state === 'expired' ? 'alert' : 'status');
  }

  if (state === 'loading') {
    const submit = document.querySelector('[data-submit]');
    if (submit) { submit.disabled = true; submit.setAttribute('aria-busy', 'true'); }
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

  document.querySelectorAll('form[data-prototype-form]').forEach((form) => {
    form.addEventListener('submit', (event) => {
      event.preventDefault();
      // Deliberately no network, persistence, or value logging in this prototype.
      const live = document.querySelector('[data-form-note]');
      if (live) live.textContent = 'La acción está simulada para revisión visual.';
    });
  });
})();
