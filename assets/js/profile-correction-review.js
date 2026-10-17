'use strict';
(() => {
  const root = document.querySelector('[data-correction-review]');
  if (!root) return;
  const find = selector => root.querySelector(selector);
  const queue = find('[data-report-queue]');
  const detail = find('[data-report-detail]');
  const begin = find('[data-review-begin]');
  const decide = find('[data-review-decide]');
  const message = find('[data-decision-message]');
  const states = {PENDING_REVIEW:'Pendiente de revisión', NEEDS_CLARIFICATION:'Requiere aclaración', REJECTED:'Informe rechazado', ACCEPTED_FOR_APPLICATION:'Informe aceptado; cambio pendiente de aplicación'};
  let selected = null;
  let challenge = null;
  let busy = false;
  let page = 0;
  function clearPrivate() {
    for (const selector of ['[data-original-profile]','[data-category]','[data-explanation]','[data-relation]','[data-contact]','[data-report-status]','[data-review-history]']) find(selector).replaceChildren();
    find('[data-profile-link]').removeAttribute('href'); begin.reset(); decide.reset();
  }
  async function api(action, data = {}) {
    const response = await fetch('/api/profiles/internal/correction-review.php', {
      method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/json'},
      body:JSON.stringify({action, csrf_token:root.dataset.csrf, ...data})
    });
    const result = await response.json();
    if (!response.ok || result.ok !== true) throw new Error('No pudimos confirmar la operación. Comprueba tu acceso y actualiza el informe antes de continuar.');
    return result.data;
  }
  function resetChallenge() {
    challenge = null; decide.hidden = true; decide.reset(); begin.hidden = false;
    begin.querySelector('fieldset').disabled = false;
  }
  async function refresh() {
    const status = find('[data-review-message]');
    try {
      const data = await api('queue', {page}); queue.replaceChildren();
      for (const r of data.reports) {
        const card = document.createElement('article'); card.className = 'card';
        const body = document.createElement('div'); body.className = 'card-body';
        const title = document.createElement('h3'); title.textContent = r.display_name;
        const info = document.createElement('p'); info.textContent = states[r.status] || 'Estado pendiente de consulta';
        const button = document.createElement('button'); button.type = 'button'; button.textContent = 'Revisar informe'; button.dataset.reportId = r.report_id;
        button.addEventListener('click', () => open(r.report_id)); body.append(title, info, button); card.append(body); queue.append(card);
      }
      status.textContent = data.reports.length ? 'Los informes pendientes de revisión se muestran primero.' : 'No hay informes en esta página.';
      find('[data-page-number]').textContent = 'Página ' + (page + 1);
      find('[data-previous]').disabled = page === 0; find('[data-next]').disabled = !data.has_more;
    } catch (error) { queue.replaceChildren(); status.textContent = error.message; detail.hidden = true; selected = null; resetChallenge(); clearPrivate(); }
  }
  async function open(id, focus = true) {
    if (busy) return;
    busy = true;
    selected = null; detail.hidden = true; clearPrivate(); resetChallenge();
    try {
      const r = await api('open', {report_id:id}); selected = r;
      find('[data-original-profile]').textContent = 'Perfil informado: ' + r.profile_name;
      find('[data-profile-link]').href = r.original_route;
      find('[data-report-status]').textContent = states[r.status];
      find('[data-category]').textContent = r.payload.category_label;
      find('[data-explanation]').textContent = r.payload.explanation;
      find('[data-relation]').textContent = 'Relación declarada: ' + (r.payload.relation_label || 'No indicada');
      find('[data-contact]').textContent = 'Correo privado: ' + (r.payload.contact || 'No proporcionado');
      const history = find('[data-review-history]'); history.replaceChildren();
      for (const h of r.history) {
        const item = document.createElement('li'); item.textContent = `${states[h.status]} · ${h.at}\n${h.decision.decision_note}\nReferencias: ${h.decision.evidence_refs.join(', ') || 'Sin referencias'}`; history.append(item);
      }
      if (!r.history.length) { const item = document.createElement('li'); item.textContent = 'Sin decisiones anteriores.'; history.append(item); }
      begin.hidden = ['REJECTED','ACCEPTED_FOR_APPLICATION'].includes(r.status);
      message.textContent = ''; detail.hidden = false;
      if (focus) find('#correction-detail-title').focus();
    } catch (error) { find('[data-review-message]').textContent = error.message; }
    finally { busy = false; }
  }
  begin.addEventListener('submit', async event => {
    event.preventDefault(); if (busy || !selected || !begin.reportValidity()) return;
    const data = Object.fromEntries(new FormData(begin));
    const decision = {status:data.status, reason:data.reason, decision_note:data.decision_note,
      evidence_refs:data.evidence_refs.split(',').map(x => x.trim()).filter(Boolean), facts_confirmed:begin.elements.facts_confirmed.checked};
    busy = true; begin.querySelector('fieldset').disabled = true;
    try {
      challenge = await api('begin', {report_id:selected.report_id, version:selected.version, decision, email:data.email, password:data.password});
      begin.elements.password.value = ''; begin.hidden = true; decide.hidden = false;
      find('[data-frozen-decision]').textContent = states[decision.status];
      message.textContent = 'Confirma con tu autenticador. El cambio al perfil sigue pendiente de aplicación.';
      decide.elements.code.focus();
    } catch (error) { message.textContent = error.message; begin.querySelector('fieldset').disabled = false; }
    finally { begin.elements.password.value = ''; busy = false; }
  });
  decide.addEventListener('submit', async event => {
    event.preventDefault(); if (busy || !selected || !challenge || !decide.reportValidity()) return;
    busy = true; decide.querySelector('fieldset').disabled = true;
    try {
      const data = await api('decide', {...challenge, code:decide.elements.code.value});
      busy = false; resetChallenge(); await open(selected.report_id, false); await refresh();
      message.textContent = data.status === 'ACCEPTED_FOR_APPLICATION'
        ? 'Informe aceptado. El cambio al perfil sigue pendiente de una aplicación autorizada.'
        : data.status === 'NEEDS_CLARIFICATION'
          ? 'Necesidad de aclaración registrada. No se ha enviado un mensaje automáticamente.'
          : 'Revisión registrada. El perfil no ha sido modificado.';
    } catch (error) { message.textContent = error.message; }
    finally { busy = false; decide.querySelector('fieldset').disabled = false; decide.elements.code.value = ''; }
  });
  find('[data-edit-decision]').addEventListener('click', resetChallenge);
  find('[data-refresh]').addEventListener('click', () => { if (!busy) { detail.hidden = true; selected = null; resetChallenge(); refresh(); } });
  for (const [selector,delta] of [['[data-previous]',-1],['[data-next]',1]]) find(selector).addEventListener('click', () => { if (!busy) {page += delta; detail.hidden = true; selected = null; clearPrivate(); resetChallenge(); refresh();} });
  window.addEventListener('pagehide', () => { clearPrivate(); queue.replaceChildren(); detail.hidden = true; selected = null; challenge = null; });
  refresh();
})();
