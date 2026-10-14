(function () {
  'use strict';

  const status = document.querySelector('[data-private-entry-status]');
  const login = document.querySelector('[data-private-entry-login]');
  const privateTarget = '/private-app.php' + window.location.search + window.location.hash;
  const loginTarget = '/public/identity/acceso.php?next=' + encodeURIComponent(privateTarget);

  function showAccessUnavailable(message) {
    if (status) status.textContent = message;
    if (login) {
      login.href = '/public/identity/acceso.php';
      login.hidden = false;
    }
  }

  fetch('/api/identity/index.php/current-session', {
    credentials: 'same-origin',
    cache: 'no-store',
    headers: { Accept: 'application/json' }
  }).then(async (response) => {
    const payload = await response.json().catch(() => ({}));
    if (response.status === 401) {
      window.location.replace(loginTarget);
      return;
    }
    if (!response.ok || payload.authenticated !== true) {
      showAccessUnavailable('No fue posible validar tu sesión. Intenta de nuevo.');
      return;
    }
    if (payload.application_access?.allowed !== true) {
      showAccessUnavailable('Tu cuenta no tiene un perfil médico único y activo para abrir esta aplicación.');
      return;
    }
    window.location.replace(privateTarget);
  }).catch(() => {
    showAccessUnavailable('No fue posible conectar con Identidad. Intenta de nuevo.');
  });
})();
