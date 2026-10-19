(() => {
  'use strict';
  const root = document.querySelector('[data-request-id]');
  if (!root) return;
  const requestId = root.dataset.requestId, $ = id => document.getElementById(id);
  let state, owner, csrf = '', busy = false, uncertain = false;
  const message = text => { $('request-message').textContent = text; };
  const text = (parent, tag, content) => { const node = document.createElement(tag); node.textContent = content; parent.append(node); return node; };
  async function get(mode) {
    const response = await fetch(`/api/internal/workspace/requests.php?mode=${mode}&request_id=${encodeURIComponent(requestId)}`, { credentials: 'same-origin', cache: 'no-store' });
    const body = await response.json();
    if (!response.ok || !body.ok) throw new Error(response.status === 401 ? 'La sesión terminó. Inicia sesión de nuevo.' : 'No se pudo consultar esta solicitud.');
    csrf = body.data.csrf_token || csrf; return body.data;
  }
  async function post(action, input) {
    const response = await fetch('/api/internal/workspace/requests.php', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf }, body: JSON.stringify({ action, input }) });
    const body = await response.json();
    if (!response.ok || !body.ok) throw new Error(response.status === 409 ? 'El estado cambió. Actualiza antes de continuar.' : 'No se pudo completar la acción con la autorización y el estado actuales.');
    return body.data;
  }
  async function refresh() {
    state = await get('applicant');
    $('request-account').textContent = state.email;
    const labels = { PENDING_REVIEW: 'En revisión', NEEDS_INFORMATION: 'Se requiere información', REJECTED: 'Solicitud rechazada', APPROVED: 'Solicitud aprobada' };
    $('request-state').textContent = labels[state.state] || state.state;
    const list = $('request-information'); list.replaceChildren();
    for (const item of state.information_history || []) { text(list, 'h2', item.state === 'REJECTED' ? 'Motivo del rechazo' : 'Información solicitada'); text(list, 'p', item.reason); if (item.response) text(list, 'p', `Tu respuesta: ${item.response}`); }
    $('request-response').hidden = !state.can_respond;
    $('request-ownership').hidden = state.state !== 'APPROVED';
    if (state.state === 'APPROVED') {
      owner = await get('ownership');
      $('request-outcome').textContent = owner.ownership_activated ? 'Tu titularidad está activada.' : 'La aprobación de la solicitud está registrada; la titularidad aún no está activada.';
      $('request-prerequisite').textContent = owner.prerequisite_label || ({ CANONICAL_C4_MEMBERSHIP_AND_OWNER_CASE_REQUIRED: 'La acreditación profesional y el vínculo de tu cuenta deben estar listos antes de la aceptación.', REVIEWER_ENDORSEMENT_REQUIRED: 'El revisor debe confirmar la autorización de titularidad.', APPLICANT_ACCEPTANCE_REQUIRED: 'Puedes aceptar la titularidad con tu cuenta y tu contraseña.', CASE_RECOVERY_REQUIRED: 'La autorización requiere revisión antes de continuar.' }[owner.next_step] || '');
      $('request-prepare').hidden = owner.ownership_activated || owner.case_id != null || owner.can_prepare === false;
      $('request-accept').hidden = owner.next_step !== 'APPLICANT_ACCEPTANCE_REQUIRED' || uncertain;
      if (owner.ownership_activated) uncertain = false;
    } else $('request-outcome').textContent = 'Esta solicitud no activa titularidad, publicación ni planes.';
  }
  async function run(fn) {
    if (busy) return; busy = true;
    for (const button of root.querySelectorAll('button')) button.disabled = true;
    try { await fn(); } catch (error) { message(error.message); }
    finally { busy = false; for (const button of root.querySelectorAll('button')) button.disabled = false; }
  }
  $('request-refresh').addEventListener('click', () => run(refresh));
  $('request-response').addEventListener('submit', event => { event.preventDefault(); run(async () => { await post('respond', { request_id: requestId, resolution_id: state.resolution_id, text: $('request-response-text').value.trim() }); $('request-response-text').value = ''; await refresh(); message('Respuesta registrada.'); }); });
  $('request-prepare').addEventListener('click', () => run(async () => { await post('owner_prepare', { request_id: requestId }); await refresh(); message('Vínculo preparado. El revisor debe autorizar la titularidad.'); }));
  $('request-accept').addEventListener('submit', event => {
    event.preventDefault(); if (uncertain || !$('request-confirm').checked) return;
    const password = $('request-password').value; $('request-password').value = '';
    run(async () => {
      const proof = await post('owner_stepup', { request_id: requestId, email: state.email, password });
      uncertain = true; $('request-accept').hidden = true;
      try { await post('owner_finalize', { request_id: requestId, proof: proof.step_up_proof }); await refresh(); message('Titularidad activada para tu cuenta.'); }
      catch (error) { try { await refresh(); if (owner.ownership_activated) { message('La consulta confirma que tu titularidad está activada.'); return; } } catch (_) {} message('No se pudo confirmar el resultado. Usa Actualizar estado; la activación no se repetirá automáticamente.'); }
    });
  });
  $('request-logout').addEventListener('click', async () => {
    if (busy || (($('request-response-text').value || $('request-password').value) && !window.confirm('Hay información sin enviar. ¿Cerrar sesión y descartarla?'))) return;
    try { const current = await fetch('/api/identity/index.php/current-session', { credentials: 'same-origin', cache: 'no-store' }); const session = await current.json(); if (!current.ok || !session.csrf_token) throw new Error(); const response = await fetch('/api/identity/index.php/logout', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': session.csrf_token }, body: JSON.stringify({ csrf_token: session.csrf_token }) }); const result = await response.json(); if (!response.ok || result.state !== 'LOGOUT_SUCCESS') throw new Error(); window.location.assign('/public/identity/acceso.php'); } catch (_) { message('No se pudo cerrar la sesión. Intenta de nuevo.'); }
  });
  window.addEventListener('beforeunload', event => { if (busy || $('request-response-text').value || $('request-password').value) { event.preventDefault(); event.returnValue = ''; } });
  run(refresh);
})();
