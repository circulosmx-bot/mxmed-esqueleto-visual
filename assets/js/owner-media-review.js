(() => {
  'use strict';

  const purposes = {
    photo: ['profile-photo-review-candidate.php', 'X-Profile-Photo-Candidate-CSRF', 'Foto de perfil', 'mxpi-photo-input'],
    logo: ['physician-logo-review-candidate.php', 'X-Physician-Logo-Candidate-CSRF', 'Logotipo profesional', null],
    gallery: ['gallery-review-candidate.php', 'X-Gallery-Review-Candidate-CSRF', 'Imagen de galería', 'fotos-input']
  };
  const keys = {DOCTOR_PROFILE_PHOTO: 'photo', PHYSICIAN_PERSONAL_LOGO: 'logo', DOCTOR_GALLERY: 'gallery'};
  const labels = {OPEN: 'Pendiente de enviar', SUBMITTED: 'En revisión', NEEDS_WORK: 'Requiere cambios'};
  const hosts = {
    photo: document.getElementById('mxpi-photo-control'),
    logo: document.querySelector('#mx-dg-media-card [data-profile-logo-upload]'),
    gallery: document.getElementById('fotos-grid')
  };
  const areas = [
    {root: document.querySelector('#mx-dg-media-card .mx-dg-card-head'), keys: ['photo', 'logo']},
    {root: document.getElementById('fotos-drop'), keys: ['gallery']}
  ].filter(area => area.root);
  let busy = false, refreshSequence = 0, announcement, lastAnnouncement = '';
  const element = (tag, text = '', cls = '') => {
    const node = document.createElement(tag);
    node.textContent = text;
    node.className = cls;
    return node;
  };
  const owned = node => {node.dataset.mediaReviewUi = ''; return node;};
  const areaFor = key => areas.find(area => area.keys.includes(key));

  function clearContent() {
    document.querySelectorAll('[data-media-review-ui]:not(.mx-media-review-announcement)').forEach(node => node.remove());
  }
  function clearAll() {
    clearContent();
    announcement?.remove();
    announcement = null;
    lastAnnouncement = '';
  }
  function announce(text) {
    if (!announcement) {
      announcement = owned(element('span', '', 'visually-hidden mx-media-review-announcement'));
      announcement.setAttribute('role', 'status');
      announcement.setAttribute('aria-live', 'polite');
      (document.getElementById('p-info') || areas[0].root).append(announcement);
    }
    if (text !== lastAnnouncement) announcement.textContent = text;
    lastAnnouncement = text;
  }

  async function json(url, options = {}) {
    let response;
    try {
      response = await fetch('/api/media/' + url, {credentials: 'same-origin', ...options});
    } catch (_) {
      const error = Error('No pudimos comunicarnos con el servicio de imágenes.');
      error.kind = 'network';
      throw error;
    }
    const value = await response.json().catch(() => null);
    if (!response.ok || !value?.ok) {
      const code = String(value?.error || '').trim();
      const error = Error(code === 'gallery_limit_reached' ? 'Puedes tener hasta 16 imágenes públicas y pendientes.' : 'No se pudo completar la acción. Intenta nuevamente.');
      error.status = response.status;
      error.code = code;
      error.kind = response.status === 401 ? 'session' : response.status === 403 ? 'permission' : 'service';
      throw error;
    }
    return value.data;
  }
  async function candidate(key, method, body) {
    const [route, header] = purposes[key];
    const current = await json(route);
    return json(route, {method, headers: {[header]: current.csrf_token, ...(typeof body === 'string' ? {'Content-Type': 'application/json'} : {})}, body});
  }
  async function upload(key, file) {
    const form = new FormData();
    form.append('image', file);
    await candidate(key, 'POST', form);
    await refresh();
  }

  function feedback(area, error, actionFailure = false) {
    if (!area) return;
    area.root.querySelector('.mx-media-review-feedback')?.remove();
    const message = actionFailure ? error.message : error?.kind === 'permission'
      ? 'Tu sesión no tiene acceso a las imágenes pendientes.'
      : 'No pudimos consultar tus imágenes pendientes.';
    const row = owned(element('div', '', 'mx-media-review-feedback'));
    row.append(element('span', message));
    if (error?.kind !== 'permission') {
      const retry = element('button', 'Reintentar', 'btn btn-sm btn-outline-secondary');
      retry.type = 'button';
      retry.onclick = refresh;
      row.append(retry);
    }
    area.root.append(row);
    announce(message);
  }
  function updateBusyButtons() {
    document.querySelectorAll('[data-media-review-ui] button').forEach(button => {
      button.disabled = busy || button.dataset.reviewBlocked === 'true';
    });
  }
  async function perform(action, area) {
    if (busy) return;
    busy = true;
    updateBusyButtons();
    try {
      await action();
    } catch (error) {
      if (error?.kind === 'session') clearAll();
      else feedback(area, error, true);
    } finally {
      busy = false;
      updateBusyButtons();
    }
  }

  function renderCandidate(item, key) {
    const row = owned(element('div', '', 'mx-media-review-candidate' + (key === 'gallery' ? ' foto-item' : '')));
    row.dataset.reviewCandidate = key;
    row.dataset.reviewId = item.id;
    row.dataset.reviewPurpose = item.purpose;
    row.setAttribute('role', 'group');
    row.setAttribute('aria-label', purposes[key][2] + ': ' + labels[item.state]);
    row.append(element('span', 'Cambio propuesto', 'mx-media-review-caption'));
    const thumbnail = element('div', '', 'mx-media-review-thumbnail');
    const image = document.createElement('img');
    image.src = item.preview_url;
    image.alt = purposes[key][2] + ': cambio propuesto';
    image.width = 88;
    image.height = 88;
    const badge = element('span', labels[item.state], 'mx-media-review-badge');
    badge.dataset.reviewState = item.state;
    thumbnail.append(image, badge);
    row.append(thumbnail);

    if (item.state === 'NEEDS_WORK') {
      const details = element('details', '', 'mx-media-review-observations');
      details.append(element('summary', 'Ver observaciones'));
      details.append(element('p', 'Motivo: ' + (item.reason || 'Se requiere otra imagen.')));
      if (item.feedback) details.append(element('p', item.feedback));
      row.append(details);
      const replace = element('button', 'Reemplazar imagen', 'btn btn-sm btn-outline-primary');
      replace.type = 'button';
      replace.onclick = () => {
        const input = key === 'logo' ? document.querySelector('#mx-dg-media-card [data-profile-logo-upload] input[type=file]') : document.getElementById(purposes[key][3]);
        input?.click();
      };
      row.append(replace);
    } else {
      const withdraw = element('button', 'Retirar imagen', 'btn btn-sm btn-outline-secondary');
      withdraw.type = 'button';
      withdraw.onclick = () => perform(async () => {
        await candidate(key, 'DELETE', key === 'gallery' ? JSON.stringify({submission_id: item.id}) : undefined);
        await refresh();
      }, areaFor(key));
      row.append(withdraw);
    }
    hosts[key].append(row);
  }

  async function refresh() {
    if (!areas.length) return;
    const sequence = ++refreshSequence;
    try {
      const [owner, batch] = await Promise.all([json('owner-review.php'), json('review-batch-submit.php')]);
      if (sequence !== refreshSequence) return;
      if (!Array.isArray(owner?.items) || !batch) throw Error('invalid_review_response');
      clearContent();
      const items = owner.items.filter(item => keys[item.purpose] && hosts[keys[item.purpose]]);
      if (items.some(item => !labels[item.state])) throw Error('invalid_review_state');
      if (!items.length) {clearAll(); return;}
      items.forEach(item => renderCandidate(item, keys[item.purpose]));
      const openCount = items.filter(item => item.state === 'OPEN').length;
      for (const area of areas) {
        if (!items.some(item => item.state === 'OPEN' && area.keys.includes(keys[item.purpose]))) continue;
        const row = owned(element('div', '', 'mx-media-review-batch'));
        row.append(element('span', openCount === 1 ? '1 cambio pendiente' : `${openCount} cambios pendientes`));
        const submit = element('button', 'Enviar a revisión', 'btn btn-sm btn-primary');
        submit.type = 'button';
        submit.dataset.reviewBlocked = String(!batch.can_submit_now);
        submit.disabled = !batch.can_submit_now;
        submit.onclick = () => perform(async () => {
          await json('review-batch-submit.php', {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({csrf: batch.csrf})});
          await refresh();
        }, area);
        row.append(submit);
        area.root.append(row);
      }
      const counts = Object.keys(labels).map(state => {
        const count = items.filter(item => item.state === state).length;
        return count ? `${count}: ${labels[state]}` : '';
      }).filter(Boolean).join('. ');
      announce(counts);
      updateBusyButtons();
    } catch (error) {
      if (sequence !== refreshSequence) return;
      clearAll();
      if (error?.kind !== 'session') areas.forEach(area => feedback(area, error));
    }
  }

  window.mxmedMediaReview = {upload, refresh};
  document.addEventListener('shown.bs.tab', refresh);
  window.addEventListener('focus', refresh);
  refresh();
})();
