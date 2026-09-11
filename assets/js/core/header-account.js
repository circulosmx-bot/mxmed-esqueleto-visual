// Header presentation only. Session termination remains owned by Identity HTTP.
(function () {
  'use strict';
  function initialize() {
    const header = document.querySelector('.mx-global-header');
    if (!header) return;
    const store = window.mxmedStore || {};
    const physician = store.doctorProfile || window.mxmedDoctor || {};
    header.querySelector('.mx-gh-identity-name-text').textContent = physician.full_name || store.doctorName || '';
    const specialtySummary = document.getElementById('fs-esp');
    const syncSpecialty = () => {
      header.querySelector('.mx-gh-identity-role').textContent = physician.specialty || store.doctorSpecialty || specialtySummary?.textContent?.trim() || '';
    };
    syncSpecialty();
    if (specialtySummary) new MutationObserver(syncSpecialty).observe(specialtySummary, { childList: true, characterData: true, subtree: true });
    const button = header.querySelector('[data-header-logout]');
    const status = header.querySelector('.mx-hb-account-status');
    button.addEventListener('click', async function () {
      if (button.disabled) return;
      button.disabled = true;
      status.hidden = true;
      try {
        const sessionResponse = await fetch('/api/identity/index.php/current-session', {
          credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' }
        });
        const session = await sessionResponse.json();
        if (!sessionResponse.ok || session.authenticated !== true || !session.csrf_token) throw new Error('session');
        const response = await fetch('/api/identity/index.php/logout', {
          method: 'POST', credentials: 'same-origin', cache: 'no-store',
          headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': session.csrf_token },
          body: JSON.stringify({ csrf_token: session.csrf_token })
        });
        const result = await response.json();
        if (!response.ok || result.ok !== true || result.state !== 'LOGOUT_SUCCESS') throw new Error('logout');
        window.location.assign('/public/identity/acceso.php');
      } catch (_) {
        status.textContent = 'No fue posible cerrar sesión. Inténtalo de nuevo.';
        status.hidden = false;
        window.bootstrap?.Dropdown.getOrCreateInstance(header.querySelector('#mxHeaderAccount')).show();
      } finally {
        button.disabled = false;
      }
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, { once: true });
  else initialize();
})();
