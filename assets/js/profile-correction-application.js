'use strict';
(() => {
  const root = document.querySelector('[data-correction-review]');
  const panel = root?.querySelector('[data-correction-application]');
  if (!panel) return;
  const find = selector => panel.querySelector(selector);
  const begin = find('[data-application-begin]');
  const mfa = find('[data-application-mfa]');
  const target = find('#application-target');
  const message = find('[data-application-message]');
  const labels = {REQUESTED:'Cambio propuesto; autorización pendiente',AUTHORIZED:'Cambio autorizado, pendiente de aplicar',
    APPLY_STARTED:'Aplicación sin confirmar; consulta el estado',APPLIED:'Corrección aplicada',NO_CHANGE:'El dato ya coincide; no se realizó una nueva modificación',
    RETRYABLE_FAILURE:'Aplicación no confirmada',CONFLICT:'Los datos cambiaron; requiere una nueva comparación',RECONCILIATION_REQUIRED:'Requiere conciliación; no se confirma una aplicación',RECONCILED_RETRYABLE:'No hay una aplicación confirmada; se puede reintentar con esta autorización vigente'};
  let data = null;
  let command = null;
  let challenge = null;
  let comparison = '';
  let busy = false;
  function clear() {
    data = null; command = null; challenge = null; comparison = ''; panel.hidden = true;
    begin.reset(); mfa.reset(); target.replaceChildren(new Option('Selecciona el contacto exacto',''));
    for (const selector of ['[data-application-current]','[data-application-comparison]','[data-application-final-comparison]','[data-application-message]','[data-application-history]','[data-application-blocked]']) find(selector).replaceChildren();
    begin.hidden = true; mfa.hidden = true; find('[data-application-confirm]').hidden = true;
    find('[data-application-status]').hidden = true; find('[data-application-start]').hidden = true;
  }
  function lock(value) {
    busy = value; root.dataset.applicationBusy = value ? '1' : '0';
    for (const el of panel.querySelectorAll('button')) el.disabled = value;
    for (const el of panel.querySelectorAll('fieldset')) el.disabled = value;
  }
  async function api(action, payload) {
    const response = await fetch('/api/profiles/internal/correction-review.php', {method:'POST',credentials:'same-origin',
      headers:{'Content-Type':'application/json'},body:JSON.stringify({action,csrf_token:root.dataset.csrf,...payload})});
    const result = await response.json();
    if (!response.ok || result.ok !== true) throw new Error(response.status === 409
      ? 'Los datos o el comando cambiaron. Actualiza el informe y vuelve a comparar y autorizar.'
      : 'No se confirmó la operación. Consulta su estado antes de reintentar; puede requerir una nueva autorización.');
    return result.data;
  }
  function show(next) {
    clear(); if (!next?.enabled) return; data = next; panel.hidden = false;
    const state = next.result?.status;
    find('[data-application-state]').textContent = state ? labels[state] : 'Aceptado, pendiente de aplicar';
    if (next.reason) find('[data-application-state]').textContent = {
      APPLICATION_PERMISSION_REQUIRED:'La aplicación requiere un permiso interno específico. La aceptación no concede ese permiso.',
      REPORT_NOT_ELIGIBLE:'Este informe no admite aplicación ordinaria; conserva su resultado de revisión.',
      NO_SUPPORTED_PUBLISHED_TARGET:'No hay un teléfono público elegible para este cambio. No se habilitan otros campos.'
    }[next.reason] || 'Aplicación no disponible.';
    for (const t of next.targets) target.add(new Option(`${t.label}: ${t.current_value}`,t.contact_point_id));
    for (const h of next.history) { const item = document.createElement('li'); item.textContent = `${labels[h.state] || 'Estado pendiente de consulta'} · ${h.created_at}`; find('[data-application-history]').append(item); }
    if (!next.history.length) { const item = document.createElement('li'); item.textContent = 'Sin aplicaciones anteriores.'; find('[data-application-history]').append(item); }
    for (const reason of Object.values(next.blocked_families)) { const item = document.createElement('li'); item.textContent = reason; find('[data-application-blocked]').append(item); }
    find('[data-application-start]').hidden = !next.can_apply;
    if (next.pending_command && !next.result && next.can_apply) {command = next.pending_command;find('[data-application-status]').hidden = false;}
  }
  function frozen(text) {comparison = text;find('[data-application-comparison]').textContent = text;find('[data-application-final-comparison]').textContent = text;}
  find('[data-application-start]').addEventListener('click', () => {
    if (busy) return;begin.hidden = false;mfa.hidden = true;find('[data-application-confirm]').hidden = true;
    begin.reset();command = null;challenge = null;message.textContent = '';target.focus();
  });
  target.addEventListener('change', () => {
    const t = data?.targets.find(t => t.contact_point_id === target.value);
    find('[data-application-current]').textContent = t?.current_value || 'Selecciona un contacto';
  });
  begin.addEventListener('submit', async event => {
    event.preventDefault();if (busy || !data || !begin.reportValidity()) return;
    const t = data.targets.find(t => t.contact_point_id === target.value);if (!t) return;
    const input = Object.fromEntries(new FormData(begin));
    const request = {command_id:crypto.randomUUID(),report_id:data.report_id,report_version:data.report_version,profile_id:data.profile_id,
      field_family:data.field_family,target:{contact_point_id:t.contact_point_id,consultorio_id:null},expected_version:t.expected_version,
      proposed_value:input.proposed_value,verification_refs:input.verification_refs.split(',').map(x => x.trim()).filter(Boolean),facts_confirmed:begin.elements.application_facts_confirmed.checked,reason:input.reason};
    lock(true);
    try {
      const result = await api('application_begin',{command:request,email:input.email,password:input.password});
      command = result.command_id;challenge = result;frozen(`${t.label}. Publicado: ${t.current_value}. Propuesta verificada: ${input.proposed_value}.`);
      begin.hidden = true;mfa.hidden = false;find('[data-application-status]').hidden = false;
      find('[data-application-state]').textContent = labels.REQUESTED;message.textContent = 'Confirma con tu autenticador. El dato publicado no ha cambiado.';
    } catch (error) {message.textContent = error.message;}
    finally {begin.elements.password.value = '';lock(false);}
  });
  mfa.addEventListener('submit', async event => {
    event.preventDefault();if (busy || !command || !challenge || !mfa.reportValidity()) return;lock(true);
    try {
      await api('application_authorize',{command_id:command,challenge_id:challenge.challenge_id,challenge:challenge.challenge,code:mfa.elements.code.value});
      mfa.hidden = true;find('[data-application-confirm]').hidden = false;find('[data-application-state]').textContent = labels.AUTHORIZED;
      message.textContent = 'Autorización verificada. Revisa la comparación y confirma la aplicación; el dato aún no cambia.';
    } catch (error) {message.textContent = error.message;}
    finally {mfa.elements.code.value = '';lock(false);}
  });
  async function finished(result) {
    if (!['APPLIED','NO_CHANGE'].includes(result.state)) return false;
    lock(false);root.dispatchEvent(new CustomEvent('mxmed:correction-application-updated'));
    find('[data-application-state]').textContent = labels[result.state];message.textContent = labels[result.state];return true;
  }
  find('[data-application-execute]').addEventListener('click', async () => {
    if (busy || !command) return;lock(true);find('[data-application-confirm]').hidden = true;
    try {const result = await api('application_execute',{command_id:command});await finished(result);}
    catch (error) {message.textContent = error.message;find('[data-application-status]').hidden = false;}
    finally {lock(false);}
  });
  find('[data-application-status]').addEventListener('click', async () => {
    if (busy || !command) return;lock(true);
    try {
      const result = await api('application_status',{command_id:command});if (await finished(result)) return;
      find('[data-application-state]').textContent = labels[result.state] || 'Resultado pendiente de consulta';
      if (result.comparison) frozen(`Publicado al comparar: ${result.comparison.before}. Propuesta verificada: ${result.comparison.proposed}.`);
      find('[data-application-confirm]').hidden = !result.retry_available;
      if (result.state === 'REQUESTED' && result.challenge) {challenge = result;begin.hidden = true;mfa.hidden = false;}
      message.textContent = result.retry_available ? 'La aplicación no está confirmada. Puedes reintentar este comando exacto con su autorización vigente.' : 'Si no puedes continuar, actualiza el informe y realiza una nueva comparación y autorización.';
    } catch (error) {message.textContent = error.message;}
    finally {lock(false);}
  });
  window.mxmedCorrectionApplication = {show,clear};
  window.addEventListener('pagehide',clear);
})();
