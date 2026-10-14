// Header presentation uses the canonical private-session context.
(function () {
  'use strict';
  function initialize() {
    const header = document.querySelector('.mx-global-header');
    if (!header) return;
    const context = window.mxmedPrivateSession?.context || {};
    const professional = context.professional || {};
    header.querySelector('.mx-gh-identity-name-text').textContent = professional.display_name || '';
    const specialtySummary = document.getElementById('fs-esp');
    const syncSpecialty = () => {
      header.querySelector('.mx-gh-identity-role').textContent = specialtySummary?.textContent?.trim() || professional.role || '';
    };
    syncSpecialty();
    if (specialtySummary) new MutationObserver(syncSpecialty).observe(specialtySummary, { childList: true, characterData: true, subtree: true });
    // This existing Admin preview is populated only by profile-photo.php current().
    // Never observe the separate candidate/review previews.
    const currentPreview = document.querySelector('#mxpi-photo-preview img');
    const avatar = header.querySelector('.mx-gh-user-avatar');
    const photo = document.createElement('img');
    photo.className = 'mx-hb-current-photo';
    photo.alt = '';
    photo.hidden = true;
    avatar.append(photo);
    const fallback = () => { photo.hidden = true; avatar.classList.remove('has-current-photo'); };
    photo.addEventListener('error', fallback);
    photo.addEventListener('load', () => { photo.hidden = false; avatar.classList.add('has-current-photo'); });
    const syncPhoto = () => {
      const source = currentPreview?.getAttribute('src') || '';
      // Current public media identifiers only; excludes private routes, data URLs and originals.
      const valid = /^\/api\/media\/index\.php\/public\/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(source);
      if (!valid) { fallback(); photo.removeAttribute('src'); return; }
      if (photo.getAttribute('src') !== source) { fallback(); photo.src = source; }
    };
    if (currentPreview) new MutationObserver(syncPhoto).observe(currentPreview, { attributes: true, attributeFilter: ['src'] });
    syncPhoto();
    const button = header.querySelector('[data-header-logout]');
    const status = header.querySelector('.mx-hb-account-status');
    button.addEventListener('click', async function () {
      if (button.disabled) return;
      button.disabled = true;
      status.hidden = true;
      try {
        if (!window.mxmedPrivateSession || typeof window.mxmedPrivateSession.logout !== 'function') throw new Error('session_manager');
        const success = await window.mxmedPrivateSession.logout();
        if (!success) throw new Error('logout');
      } catch (_) {
        status.textContent = 'No fue posible cerrar sesión. Inténtalo de nuevo.';
        status.hidden = false;
        window.bootstrap?.Dropdown.getOrCreateInstance(header.querySelector('#mxHeaderAccount')).show();
      }
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, { once: true });
  else initialize();
})();
