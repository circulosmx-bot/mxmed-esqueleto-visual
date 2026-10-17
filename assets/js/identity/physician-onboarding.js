(() => {
  'use strict';
  const root = document.querySelector('[data-onboarding]');
  if (!root) return;
  const boot = document.getElementById('physician-onboarding-state');
  if (JSON.parse(boot.textContent).enrollment) return;
  const presentation = JSON.parse(boot.textContent).public_entry;
  if (presentation) {
    // Read-only public context never enters the authenticated request adapter.
    boot.textContent = '';
    root.querySelectorAll('fieldset').forEach(fieldset => { fieldset.disabled = true; });
    root.querySelector('[data-onboarding-refresh]').addEventListener('click', () => window.location.reload());
    return;
  }
  const endpoint = '/api/identity/physician-onboarding.php';
  let state = JSON.parse(document.getElementById('physician-onboarding-state').textContent);
  const initial = { staff: state.staff, expires_at: state.expires_at };
  let csrf = state.csrf_token, selected = state.request?.request_id || null;
  document.getElementById('physician-onboarding-state').textContent = '';
  let busy = false, locked = false, otp = null, confirmation = null, review = null, profile = null, enrollmentReview = null;
  let selectedEnrollment = state.enrollment_review?.application?.application_id || state.enrollment_application?.application_id || null;
  let epoch = 0, deadline = initial.expires_at * 1000, expiryTimer;
  const urls = new Set();
  const q = s => root.querySelector(s);
  const forms = action => q(`[data-onboarding-form="${action}"]`);
  const statusCopy = {
    DRAFT: 'Solicitud en preparación. Agrega los documentos y envíala a revisión.',
    SUBMITTED: 'Solicitud enviada. Está pendiente de revisión por el equipo autorizado.',
    APPROVED: 'La revisión fue aprobada. Falta tu confirmación personal.',
    CONFIRMED: 'Tu confirmación fue recibida. Completa el registro de la acreditación.',
    REJECTED: 'La solicitud fue rechazada. Revisa las indicaciones antes de preparar otra solicitud o solicita atención al equipo.',
    SUSPENDED: 'La solicitud está suspendida y requiere atención del equipo autorizado.',
    REVOKED: 'La solicitud dejó de estar vigente. No puede utilizarse para obtener autorización.',
    EXPIRED: 'La evidencia venció. Solicita al equipo una nueva revisión.',
    ACTION_REQUIRED: 'La acreditación requiere atención. La evidencia o autorización dejó de estar vigente.'
  };
  const reasons = {
    ACCOUNT_PERSON_MISMATCH: 'No se acreditó la relación entre la cuenta y la persona.',
    DOCUMENT_IDENTITY_MISMATCH: 'La identificación no corresponde a la solicitud.',
    DOCUMENT_UNREADABLE: 'El documento no se puede leer correctamente.',
    PROFILE_CONFLICT: 'El perfil requiere revisión especial.'
  };
  const kindLabel = kind => kind === 'OFFICIAL_ID' ? 'Identificación oficial' : 'Evidencia de cédula profesional';
  const statusLabel = status => initial.staff && status === 'APPROVED' ? 'La revisión fue aprobada. Falta la confirmación del médico solicitante.'
    : initial.staff && status === 'CONFIRMED' ? 'El médico confirmó su solicitud. Falta completar la acreditación.'
    : initial.staff && status === 'DRAFT' ? 'El médico está preparando su solicitud.'
    : statusCopy[status] || 'Esta solicitud requiere atención del equipo autorizado.';
  function feedback(text) { q('[data-onboarding-feedback]').textContent = text; }
  function add(parent, tag, text) { const node = document.createElement(tag); node.textContent = text; parent.append(node); return node; }
  function revokePreviews() {
    const dialog = q('[data-evidence-dialog]');
    if (dialog?.open) dialog.close();
    q('[data-evidence-content]')?.replaceChildren();
    urls.forEach(url => URL.revokeObjectURL(url)); urls.clear();
  }
  function clearSecrets() {
    otp = confirmation = review = profile = enrollmentReview = null; revokePreviews();
    root.querySelectorAll('form').forEach(form => form.reset());
  }
  function lock(message) {
    epoch++; locked = true; clearTimeout(expiryTimer); clearSecrets(); selected = null;
    state = { enabled: false, staff: initial.staff, requests: [], request: null, message };
    render(); q('[data-onboarding-login]').hidden = false; feedback(message);
  }
  async function request(action, data = {}, binary = false) {
    const started = epoch;
    const response = await fetch(endpoint, { method: 'POST', credentials: 'same-origin', cache: 'no-store',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf, Accept: binary ? '*/*' : 'application/json' },
      body: JSON.stringify({ action, view: initial.staff ? 'staff' : 'physician', csrf_token: csrf, ...data }) });
    if (started !== epoch || locked) throw new Error('Por seguridad, inicia sesión nuevamente para continuar.');
    const expires = Number(response.headers.get('X-MXMED-Session-Expires'));
    if (response.ok && expires > 0) { deadline = expires * 1000; armDeadline(); }
    if (binary && response.ok) {
      const blob = await response.blob();
      if (started !== epoch || locked) throw new Error('Por seguridad, inicia sesión nuevamente para continuar.');
      return blob;
    }
    const result = await response.json().catch(() => ({}));
    if (started !== epoch || locked) throw new Error('Por seguridad, inicia sesión nuevamente para continuar.');
    if (!response.ok || result.ok !== true) {
      const message = typeof result.message === 'string' ? result.message : 'No pudimos confirmar la operación. Actualiza el estado antes de reintentar.';
      if (response.status === 401 || response.status === 403) lock(message);
      if (response.status === 409) { confirmation = review = null; revokePreviews(); }
      throw new Error(message);
    }
    return result.data;
  }
  async function refresh() {
    clearSecrets();
    const next = await request('snapshot', { ...(selected ? { request_id: selected } : {}), ...(selectedEnrollment ? { application_id: selectedEnrollment } : {}) });
    state = next; locked = false; selected = state.request?.request_id || null; render();
  }
  async function run(fn) {
    if (busy || locked) return;
    busy = true; feedback(''); render(); root.setAttribute('aria-busy', 'true');
    try { await fn(); } catch (e) { feedback(e.message || 'No pudimos confirmar la operación. Actualiza el estado antes de reintentar.'); }
    finally { busy = false; root.removeAttribute('aria-busy'); render(); }
  }
  function enabled(action, available, visible = true) {
    const form = forms(action); if (!form) return;
    form.hidden = !visible;
    form.querySelector('fieldset').disabled = !state.enabled || !available || busy || locked;
  }
  function scope() { return { request_id: state.request.request_id, version: state.request.version }; }
  function render() {
    renderEnrollment();
    const r = state.request, supported = !!r?.supported;
    q('[data-onboarding-status]').textContent = state.message;
    q('[data-onboarding-refresh]').disabled = busy || locked;
    if (q('[data-phone-status]')) q('[data-phone-status]').textContent = !state.enabled ? 'La verificación de teléfono estará disponible cuando se habilite el servicio.' : state.phone_verified ? 'Teléfono verificado.' : 'Verifica tu teléfono para continuar.';
    enabled('phone_issue', !state.phone_verified, !state.phone_verified);
    enabled('phone_verify', !!otp, !!otp);
    const queue = q('[data-request-list]');
    if (queue) { queue.replaceChildren(); if (!state.enabled) add(queue, 'p', 'La revisión de acreditaciones está pendiente de habilitación.');
      else if (!state.requests.length) add(queue, 'p', 'No hay solicitudes disponibles para tu revisión.');
      state.requests.forEach(item => { const button = add(queue, 'button', `${item.display_name} · ${statusLabel(item.status)}`); button.type = 'button'; button.dataset.requestId = item.request_id; button.className = 'button button--secondary'; button.disabled = busy || locked;
        button.addEventListener('click', () => run(async () => { selected = item.request_id; await refresh(); })); }); }
    q('[data-request-panel]').hidden = !r;
    const summary = q('[data-request-summary]'); summary.replaceChildren();
    if (r) { add(summary, 'h3', r.display_name); add(summary, 'p', r.professional_name); add(summary, 'p', r.professional_area);
      add(summary, 'p', r.completed ? 'Acreditación completada.' : statusLabel(r.status));
      if (reasons[r.review_reason]) add(summary, 'p', reasons[r.review_reason]);
      if (!supported) add(summary, 'p', 'Este trámite requiere un procedimiento especial. No se puede aprobar desde este formulario.'); }
    const documents = q('[data-document-list]'); documents.replaceChildren();
    (r?.documents || []).forEach(file => { const card = add(documents, 'div', kindLabel(file.kind));
      if (initial.staff) { const button = add(card, 'button', 'Abrir documento privado'); button.type = 'button'; button.className = 'button button--secondary'; button.disabled = busy || locked;
        button.addEventListener('click', () => run(async () => { const blob = await request('document', { ...scope(), file_id: file.file_id }, true); revokePreviews();
          const url = URL.createObjectURL(blob); urls.add(url); const target = q('[data-evidence-content]');
          const element = document.createElement(blob.type.startsWith('image/') ? 'img' : 'iframe'); element.src = url;
          if (element.tagName === 'IMG') element.alt = kindLabel(file.kind); else { element.title = kindLabel(file.kind); element.setAttribute('sandbox', ''); }
          target.append(element); q('[data-evidence-dialog]').showModal(); })); }
    });
    const draft = supported && r.status === 'DRAFT';
    enabled('document_add', draft, draft);
    enabled('request_submit', draft && new Set((r?.documents || []).map(file => file.kind)).size === 2, draft);
    const confirmSection = q('[data-confirmation-section]'); if (confirmSection) confirmSection.hidden = !(supported && r.status === 'APPROVED');
    enabled('confirmation_issue', supported && r.status === 'APPROVED');
    enabled('confirmation_accept', !!confirmation, !!confirmation);
    enabled('complete', supported && r.status === 'CONFIRMED' && !r.completed, supported && r.status === 'CONFIRMED' && !r.completed);
    enabled('review_begin', supported && r.status === 'SUBMITTED', supported && r.status === 'SUBMITTED' && !review);
    enabled('review_decide', !!review, !!review);
    // Retain reviewer-entered source dates while opening exact evidence objects.
    const facts = q('[data-document-facts]');
    if (facts && facts.dataset.request !== r?.request_id) { facts.replaceChildren(); facts.dataset.request = r?.request_id || '';
      (r?.documents || []).forEach(file => { const div = add(facts, 'div', ''); div.className = 'field'; const label = add(div, 'label', `${kindLabel(file.kind)} · Vencimiento real (opcional)`);
        const input = document.createElement('input'); input.type = 'datetime-local'; input.dataset.fileId = file.file_id; input.id = `source-date-${file.file_id}`; label.htmlFor = input.id; div.append(input); }); }
    q('[data-owner-pending]').hidden = !r?.completed;
  }
  function renderEnrollment() {
    const panel = state.enrollment_review, app = panel?.application;
    const queue = q('[data-enrollment-review-list]'), summary = q('[data-enrollment-review-summary]');
    if (queue) {
      queue.replaceChildren(); summary.replaceChildren();
      (panel?.queue || []).forEach(item => {
        const button = add(queue, 'button', item.label); button.type = 'button'; button.dataset.enrollmentApplicationId = item.application_id; button.className = 'button button--secondary'; button.disabled = busy || locked;
        button.addEventListener('click', () => run(async () => { selectedEnrollment = item.application_id; await refresh(); }));
      });
      if (app) {
        add(summary, 'h3', app.origin_label); if (app.target_name) add(summary, 'p', `Perfil propuesto para revisión: ${app.target_name}`);
        add(summary, 'p', `Información declarada por el solicitante: ${app.declared_name}. ${app.declared_specialty}. ${app.declared_location}.`);
        const labels = { READY_FOR_REVIEW: 'El perfil tiene fuentes profesionales verificadas. La aprobación requiere tu revisión explícita.', PENDING_SOURCES: 'La solicitud sigue pendiente de fuentes profesionales verificadas.', PENDING_NEW_PROFILE: 'No encontramos un perfil correspondiente. La creación de perfil requiere otro trámite autorizado.', CONFLICT: 'El caso requiere revisión especial. No se cambiará el perfil ni su titular.' };
        add(summary, 'p', app.state === 'APPROVED' ? (app.resolution_current === true ? 'La correspondencia del perfil ya fue revisada. La acreditación conserva su propio estado y sus controles.' : 'La resolución requiere atención: cambiaron sus fuentes o su autorización.') : labels[app.gate] || 'Pendiente de revisión.');
        add(summary, 'p', { APPROVED: 'Revisión de perfil aprobada. La acreditación todavía requiere sus propios pasos.', REJECTED: 'Solicitud rechazada.', REVOKED: 'La resolución dejó de estar vigente.' }[app.state] || 'Solicitud pendiente.');
        const select = q('#enrollment-review-decision'), key = `${app.application_id}:${app.review_version}:${app.gate}`;
        if (select.dataset.selection !== key) {
          select.replaceChildren(); select.dataset.selection = key;
          const options = app.state === 'APPROVED' ? [['REVOKED', 'Retirar resolución por revisión de seguridad']] : [[app.gate === 'READY_FOR_REVIEW' ? 'APPROVED' : app.gate, app.gate === 'READY_FOR_REVIEW' ? 'Aprobar correspondencia del perfil' : 'Conservar pendiente o en revisión especial'], ['NEEDS_INFORMATION', 'Solicitar información adicional'], ['PENDING_REVIEW', 'Conservar pendiente de revisión'], ['REJECTED', 'Rechazar solicitud']];
          options.forEach(([value, label]) => { const option = add(select, 'option', label); option.value = value; });
        }
      }
    }
    const active = !!app && !['REJECTED', 'REVOKED'].includes(app.state);
    enabled('enrollment_review_begin', active && !enrollmentReview, active && !enrollmentReview);
    enabled('enrollment_review_decide', !!enrollmentReview, !!enrollmentReview);
    const continuation = state.enrollment_application;
    if (q('[data-enrollment-continuation-status]')) {
      const copy = { ACCREDITATION_COMPLETED: 'Tu acreditación está completada. La designación como titular sigue pendiente de una autorización independiente.', ACCREDITATION_DRAFT: 'Completa los documentos de tu acreditación.', IN_REVIEW: 'Tu solicitud está en revisión.', APPROVED_PENDING_CONFIRMATION: 'Aprobada, pendiente de tu confirmación.', CONFIRMED: 'Confirmación recibida. Completa el registro de tu acreditación.' };
      q('[data-enrollment-continuation-status]').textContent = copy[continuation?.state] || (continuation?.handoff_available ? 'La revisión del perfil está aprobada. Completa contacto, documentos y revisión profesional.' : 'Solicitud pendiente de revisión o información adicional.');
    }
    enabled('enrollment_handoff', !!continuation?.handoff_available && state.phone_verified && !state.request, !!continuation && !state.request);
  }
  root.querySelectorAll('[data-onboarding-form]').forEach(form => form.addEventListener('submit', event => {
    event.preventDefault(); const action = form.dataset.onboardingForm;
    const values = new FormData(form), fields = Object.fromEntries(values.entries());
    run(async () => {
      let data = {};
      if (action === 'enrollment_review_begin') {
        const app = state.enrollment_review.application;
        enrollmentReview = await request(action, { application_id: app.application_id, intake_version: app.intake_version, review_version: app.review_version, ...fields });
        form.reset(); feedback('Confirma la decisión con tu autenticador.'); return;
      }
      if (action === 'enrollment_review_decide') {
        const proof = await request('enrollment_review_verify', { case_id: enrollmentReview.case_id, challenge_id: enrollmentReview.challenge_id, challenge: enrollmentReview.challenge, code: fields.code });
        await request(action, { case_id: enrollmentReview.case_id, proof_id: proof.proof_id, proof: proof.proof, relationship_confirmed: values.has('relationship_confirmed') });
        await refresh(); return;
      }
      if (action === 'enrollment_handoff') {
        const result = await request(action, { application_id: state.enrollment_application.application_id }); selected = result.request_id; await refresh(); return;
      }
      if (action === 'phone_issue') { otp = await request(action, fields); form.reset(); forms('phone_verify').hidden = false; feedback('El envío del código fue confirmado. Revisa tu teléfono.'); return; }
      if (action === 'phone_verify') { await request(action, { ...otp, code: fields.code }); await refresh(); return; }
      if (action === 'document_add') {
        const file = values.get('document'); if (!(file instanceof File) || !file.size || file.size > 8 * 1024 * 1024) throw new Error('Selecciona un archivo PDF, JPG o PNG de hasta 8 MiB.');
        const content = await new Promise((resolve, reject) => { const reader = new FileReader(); reader.onload = () => resolve(String(reader.result).split(',')[1]); reader.onerror = reject; reader.readAsDataURL(file); });
        data = { ...scope(), kind: fields.kind, content_base64: content };
      } else if (action === 'confirmation_issue') {
        confirmation = await request(action, { ...scope(), ...fields }); form.reset(); feedback('Autorización recibida. Confirma la solicitud antes de cinco minutos.'); return;
      } else if (action === 'confirmation_accept') { data = { ...scope(), confirmation: confirmation.confirmation, accepted: values.has('accepted') }; }
      else if (action === 'review_begin') {
        review = await request(action, { ...scope(), ...fields, reason: fields.decision === 'APPROVED' ? 'DOCUMENT_RELATIONSHIP_VERIFIED' : fields.reason });
        form.reset(); feedback('Revisión iniciada. Abre los documentos exactos y confirma con tu autenticador.'); return;
      } else if (action === 'review_decide') {
        const proof = await request('review_verify', { ...scope(), challenge_id: review.challenge_id, challenge: review.challenge, code: fields.code });
        const facts = {}; q('[data-document-facts]').querySelectorAll('input').forEach(input => { facts[input.dataset.fileId] = input.value ? new Date(input.value).toISOString() : null; });
        data = { ...scope(), proof_id: proof.proof_id, proof: proof.proof, relationship_confirmed: values.has('relationship_confirmed'), document_facts: facts };
      } else data = scope();
      await request(action, data); await refresh();
    });
  }));
  q('[data-onboarding-refresh]').addEventListener('click', () => state.enabled ? run(refresh) : window.location.reload());
  q('[data-evidence-close]')?.addEventListener('click', revokePreviews);
  q('[data-evidence-dialog]')?.addEventListener('cancel', revokePreviews);
  document.querySelector('[data-onboarding-logout]').addEventListener('click', () => run(async () => {
    revokePreviews();
    const response = await fetch('/api/identity/index.php/logout', { method: 'POST', credentials: 'same-origin', cache: 'no-store', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf }, body: JSON.stringify({ csrf_token: csrf }) });
    const result = await response.json().catch(() => ({}));
    if (!response.ok || result.ok !== true || result.state !== 'LOGOUT_SUCCESS') throw new Error('No pudimos confirmar el cierre de sesión.');
    const message = { type: 'logout', at: Date.now() };
    try { const channel = new BroadcastChannel('mxmed-session'); channel.postMessage(message); channel.close(); localStorage.setItem('mxmed.session.signal', JSON.stringify(message)); } catch (_) {}
    lock('Tu sesión se cerró. Los documentos privados se retiraron de esta página.');
  }));
  function signal(value) { if (['logged-out', 'logout', 'expired', 'revoked'].includes(value?.type)) lock('Por seguridad, inicia sesión nuevamente para continuar.'); }
  try { const channel = new BroadcastChannel('mxmed-session'); channel.onmessage = event => signal(event.data); } catch (_) {}
  window.addEventListener('storage', event => { if (event.key === 'mxmed.session.signal') { try { signal(JSON.parse(event.newValue)); } catch (_) {} } });
  window.addEventListener('pagehide', clearSecrets);
  window.addEventListener('pageshow', event => { if (event.persisted) lock('Actualiza la página e inicia sesión nuevamente para continuar.'); });
  function armDeadline() {
    clearTimeout(expiryTimer); const wait = deadline - Date.now();
    if (wait <= 0) void checkDeadline();
    else expiryTimer = setTimeout(checkDeadline, wait);
  }
  async function checkDeadline() {
    if (locked) return; const started = epoch;
    try {
      // A single observation at the deadline handles activity in another tab;
      // the existing endpoint never extends the idle window.
      const result = await fetch('/api/identity/index.php/current-session', { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' } });
      const payload = await result.json(); if (started !== epoch || locked) return;
      const expires = Date.parse(payload.idle_expires_at);
      if (result.ok && payload.authenticated === true && expires > Date.now()) { csrf = payload.csrf_token; deadline = expires; armDeadline(); return; }
      lock(result.status === 401 ? 'Por seguridad, inicia sesión nuevamente para continuar.' : 'No pudimos validar la sesión. Inicia sesión nuevamente para continuar.');
    } catch (_) { if (started === epoch) lock('No pudimos validar la sesión. Inicia sesión nuevamente para continuar.'); }
  }
  armDeadline();
  render();
})();
