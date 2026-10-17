(() => {
  'use strict';
  const boot = document.getElementById('physician-onboarding-state');
  if (!boot) return;
  const initial = JSON.parse(boot.textContent), panel = document.querySelector('[data-source-panel]');
  if (!panel) return;
  const staff = initial.staff, csrf = initial.csrf_token || initial.enrollment?.csrf_token;
  let state = initial.professional_sources, application = state?.application_id, challenge = null, busy = false, locked = false, epoch = 0, queuedRefresh = false;
  const blobs = new Set(), q = s => panel.querySelector(s), form = n => q(`[data-source-form="${n}"]`);
  const copy = { NOT_REQUESTED: 'Verificación pendiente. El equipo autorizado puede solicitar la documentación.', COLLECTING: 'Documentación por verificar. La identidad profesional y la cédula aún no están verificadas.', FROZEN: 'La verificación humana fue autorizada. Falta guardar las fuentes verificadas.', COMPLETE: 'Verificación realizada. La misma solicitud puede continuar con la revisión del perfil y la acreditación.', REVOKED: 'Requiere revisión adicional. La autorización dejó de estar vigente.', NEEDS_INTERVENTION: 'Requiere revisión adicional. Las fuentes cambiaron; no se confirma una nueva verificación.' };
  function clearPreview() { q('[data-source-dialog]')?.close(); q('[data-source-preview]')?.replaceChildren(); blobs.forEach(u => URL.revokeObjectURL(u)); blobs.clear(); }
  async function request(action, data = {}, binary = false) {
    const started = epoch, r = await fetch('/api/identity/physician-onboarding.php', { method: 'POST', credentials: 'same-origin', cache: 'no-store', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf }, body: JSON.stringify({ action, view: staff ? 'staff' : 'physician', csrf_token: csrf, ...data }) });
    if (started !== epoch || locked) throw new Error('Actualiza el expediente antes de continuar.');
    if (r.status === 401 || r.status === 403) { locked = true; challenge = null; clearPreview(); panel.querySelectorAll('input[type=password]').forEach(x => { x.value = ''; }); }
    if (!r.ok) { if (r.status === 409) challenge = null; throw new Error(r.status === 401 ? 'Inicia sesión nuevamente para continuar.' : 'No pudimos autorizar o confirmar la operación. Actualiza el estado antes de reintentar.'); }
    if (binary) return r.blob();
    const v = await r.json(); if (v.ok !== true) throw new Error('No se confirmó la operación.'); return v.data;
  }
  function render() {
    panel.hidden = !application || state?.application_id !== application || state?.available !== true;
    if (panel.hidden) return;
    q('[data-source-status]').textContent = copy[state.state] || 'Requiere revisión adicional.';
    panel.querySelectorAll('fieldset').forEach(x => { x.disabled = busy || locked; });
    if (form('upload')) { form('upload').hidden = state.state !== 'COLLECTING'; for (const o of form('upload').elements.kind.options) o.disabled = state.files.some(f => f.kind === o.value); }
    if (form('begin')) { form('begin').hidden = !!challenge || ['COMPLETE','REVOKED','NEEDS_INTERVENTION'].includes(state.state); form('confirm').hidden = !challenge; }
    if (q('[data-source-execute]')) { q('[data-source-execute]').hidden = state.state !== 'FROZEN'; q('[data-source-execute]').disabled = busy || locked; }
    panel.querySelectorAll('[data-source-form][hidden] fieldset').forEach(x => { x.disabled = true; });
    const list = q('[data-source-files]'); list.replaceChildren();
    for (const f of state.files || []) { const el = document.createElement(staff ? 'button' : 'p'); el.textContent = f.kind === 'OFFICIAL_ID' ? 'Identidad profesional · documento privado' : 'Cédula profesional · documento privado'; if (staff) { el.type = 'button'; el.className = 'button button--secondary'; el.disabled = busy || locked; el.addEventListener('click', () => run(async () => { const blob = await request('source_document', { application_id: application, file_id: f.file_id }, true); clearPreview(); const url = URL.createObjectURL(blob); blobs.add(url); const view = document.createElement(blob.type === 'application/pdf' ? 'iframe' : 'img'); view.src = url; view.title = 'Evidencia privada del expediente'; view.style.maxWidth = '100%'; if (view.tagName === 'IFRAME') view.setAttribute('sandbox',''); q('[data-source-preview]').append(view); q('[data-source-dialog]').showModal(); })); } list.append(el); }
  }
  async function refresh() { challenge = null; clearPreview(); state = await request('source_status', { application_id: application }); render(); }
  async function run(fn) { if (busy || locked) return; busy = true; q('[data-source-feedback]').textContent = ''; render(); try { await fn(); } catch (e) { q('[data-source-feedback]').textContent = e.message; } finally { busy = false; render(); if (queuedRefresh && !locked) { queuedRefresh = false; run(refresh); } } }
  q('[data-source-refresh]').addEventListener('click', () => run(refresh));
  q('[data-source-close]')?.addEventListener('click', clearPreview);
  q('[data-source-execute]')?.addEventListener('click', () => run(async () => { await request('source_execute', { application_id: application }); await refresh(); document.dispatchEvent(new Event('mxmed:professional-sources-changed')); }));
  function actionFields() { const verify = form('begin').elements.source_action.value === 'AUTHORIZE'; q('[data-source-facts]').hidden = !verify; q('[data-source-facts]').querySelectorAll('input,select').forEach(x => { x.disabled = !verify; }); }
  form('begin')?.elements.source_action.addEventListener('change', actionFields);
  form('begin')?.addEventListener('reset', () => queueMicrotask(actionFields));
  form('begin')?.addEventListener('submit', e => { e.preventDefault(); run(async () => { const f = form('begin'), facts = {}; if (f.elements.source_action.value === 'AUTHORIZE') { for (const el of q('[data-source-facts]').querySelectorAll('input,select')) facts[el.name] = el.type === 'checkbox' ? el.checked : el.value; facts.verification_outcome = 'VERIFIED'; } challenge = await request('source_begin', { application_id: application, source_action: f.elements.source_action.value, facts, email: f.elements.email.value, password: f.elements.password.value }); f.elements.password.value = ''; }); });
  form('confirm')?.addEventListener('submit', e => { e.preventDefault(); run(async () => { const f = form('confirm'), proof = await request('source_verify', { case_id: challenge.case_id, challenge_id: challenge.challenge_id, challenge: challenge.challenge, code: f.elements.code.value }); await request('source_decide', { case_id: challenge.case_id, proof_id: proof.proof_id, proof: proof.proof }); f.reset(); await refresh(); }); });
  form('upload')?.addEventListener('submit', e => { e.preventDefault(); run(async () => { const f = form('upload'), file = f.elements.document.files[0]; if (!file || file.size > 8 * 1024 * 1024) throw new Error('Selecciona un PDF, JPG o PNG de hasta 8 MiB.'); const data = await new Promise((resolve,reject) => { const reader = new FileReader(); reader.onload = () => resolve(reader.result.split(',')[1]); reader.onerror = reject; reader.readAsDataURL(file); }); await request('source_upload', { application_id: application, version: state.version, kind: f.elements.kind.value, content_base64: data, email: f.elements.email.value, password: f.elements.password.value }); f.reset(); await refresh(); }); });
  document.addEventListener('mxmed:professional-sources-context', e => { if (e.detail.applicationId === application) return; epoch++; challenge = null; clearPreview(); state = null; application = e.detail.applicationId; form('begin')?.reset(); form('confirm')?.reset(); if (form('begin')) actionFields(); render(); if (application) { if (busy) queuedRefresh = true; else run(refresh); } });
  window.addEventListener('pagehide', () => { locked = true; epoch++; clearPreview(); });
  if (form('begin')) actionFields(); render();
})();
