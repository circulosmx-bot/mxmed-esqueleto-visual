// RES02A: one linked-result authoring surface for General and Consultation.
(function () {
  const base = '/api/clinical/index.php/';
  const orderTypes = new Set(['order', 'orders', 'lab_order', 'imaging_order', 'orden_estudio']);
  const categories = {
    LABORATORIO:'Laboratorio', IMAGEN:'Imagenología', CARDIOVASCULAR:'Cardiovascular',
    OFTALMOLOGIA:'Oftalmología', NEUROFISIOLOGIA:'Neurofisiología', FUNCION_PULMONAR:'Función pulmonar',
    AUDIOLOGIA:'Audiología', DENTAL:'Dental', PATOLOGIA:'Patología', ENDOSCOPIA:'Endoscopía', PROCEDIMIENTOS_DIAGNOSTICOS:'Procedimientos diagnósticos',
    SUENO:'Medicina del sueño', GENETICA:'Genética', OTROS:'Otros'
  };
  const allowedMime = new Set(['application/pdf', 'image/jpeg', 'image/png', 'image/webp']);
  const utc = () => new Date().toISOString().slice(0, 19).replace('T', ' ');
  const date = value => { const match = String(value || '').match(/^(\d{4})-(\d{2})-(\d{2})/); return match ? `${match[3]}/${match[2]}/${match[1]}` : 'Sin fecha'; };
  const el = (tag, text, className = '') => { const node = document.createElement(tag); node.textContent = text; node.className = className; return node; };
  const response = async request => { const body = await request.json().catch(() => null); if (!request.ok || body?.ok !== true) throw new Error(String(body?.message || body?.error?.message || body?.error?.code || 'No se completó la acción.')); return body.data; };
  const refs = value => String(value || '').trim();
  const resultType = (order, items, general) => {
    if (general) return order.document_type === 'lab_order' ? 'lab_result' : order.document_type === 'imaging_order' ? 'imaging_result' : 'result';
    if (items.every(item => item.study_category === 'LABORATORIO')) return 'lab_result';
    if (items.every(item => item.study_category === 'IMAGEN')) return 'imaging_result';
    return 'result';
  };
  let dialog, form, context, coverage, orderPicker, title, provenance, file, feedback, saveButton;
  let active = null, order = null, availableItems = [], loading = 0, busy = false, attempt = null, titleSuggested = '';
  const saveWaiters = [];
  const settleSave = () => { saveWaiters.splice(0).forEach(resolve => resolve()); };
  function node(selector) { return dialog.querySelector(selector); }
  function message(value) { feedback.textContent = value; }
  function resetAttempt() { attempt = null; }
  function hasDraft() {
    return !!(active && (file.files?.length || provenance.value.trim() || title.value.trim() !== titleSuggested ||
      coverage.dataset.touched === 'true' || orderPicker.querySelector('select')?.value));
  }
  function close(force = false, discard = false) {
    if (!dialog || !active) return;
    if (busy && !force) return;
    if (!force && hasDraft() && !discard) {
      const guard = window.mxmedPatientWorkspaceNavigationGuard;
      if (guard) void guard.request('result-composer-close', () => close(true), active.trigger || dialog, ['res02a-result-composer']);
      else if (window.confirm('¿Descartar los cambios de este resultado?')) close(true);
      return;
    }
    const trigger = active?.trigger;
    active = null; order = null; availableItems = []; loading++; resetAttempt(); form.reset(); context.replaceChildren(); coverage.replaceChildren(); message('');
    if (dialog.open) dialog.close();
    if (trigger?.isConnected) { trigger.setAttribute('aria-expanded','false'); trigger.focus({preventScroll:true}); }
  }
  function setup() {
    if (dialog) return;
    dialog = document.createElement('dialog'); dialog.id = 'res02a-linked-result'; dialog.className = 'res02a-dialog'; dialog.setAttribute('aria-labelledby', 'res02a-title');
    dialog.innerHTML = `<form><header><h4 id="res02a-title">Registrar resultado</h4><button type="button" data-close aria-label="Cerrar">×</button></header><div class="res02a-body"><div data-order-picker></div><div data-context aria-live="polite"></div><fieldset data-coverage><legend>ESTE RESULTADO CORRESPONDE A</legend></fieldset><label>Título<input data-title required maxlength="160"></label><label>Procedencia<input data-provenance required maxlength="160" placeholder="Ej. Laboratorio que realizó el estudio"></label><label>Archivo PDF o imagen<input data-file type="file" accept="application/pdf,image/jpeg,image/png,image/webp" required></label><small>PDF, JPG, PNG o WebP. Un archivo por resultado.</small><p data-feedback role="status" aria-live="polite"></p></div><footer><button type="button" data-close class="btn btn-outline-secondary">Cancelar</button><button type="submit" class="btn btn-primary" data-save>Guardar resultado</button></footer></form>`;
    document.body.append(dialog); form = dialog.querySelector('form'); context = node('[data-context]'); coverage = node('[data-coverage]'); orderPicker = node('[data-order-picker]'); title = node('[data-title]'); provenance = node('[data-provenance]'); file = node('[data-file]'); feedback = node('[data-feedback]'); saveButton = node('[data-save]');
    dialog.querySelectorAll('[data-close]').forEach(control => control.addEventListener('click', () => close()));
    dialog.addEventListener('cancel', event => { event.preventDefault(); close(); });
    form.addEventListener('input', resetAttempt); form.addEventListener('change', resetAttempt);
    form.addEventListener('submit', submit);
    window.mxmedPatientWorkspaceNavigationGuard?.register({id:'res02a-result-composer',copy:'result',
      isInProgress:hasDraft,isSaving:()=>!!active&&busy,
      whenSettled:()=>new Promise(resolve=>saveWaiters.push(resolve)),
      discard:()=>close(true)});
  }
  function chosen() { return [...coverage.querySelectorAll('input[data-item]:checked')].map(input => availableItems.find(item => item.order_item_id === input.value)).filter(Boolean); }
  function generalSelected() { return !!coverage.querySelector('input[data-general]:checked'); }
  function updateTitle() {
    const items = chosen();
    const suggestion = generalSelected() || items.length !== 1 ? 'Resultado de estudios' : items[0].study_display_name;
    if (!title.value.trim() || title.value === titleSuggested) title.value = suggestion.slice(0, 160);
    titleSuggested = suggestion.slice(0, 160);
  }
  function renderCoverage(payload) {
    coverage.replaceChildren(el('legend', 'ESTE RESULTADO CORRESPONDE A'));
    availableItems = Number(payload.order_payload_version) === 2 && Array.isArray(payload.order_items)
      ? payload.order_items.filter(item => typeof item.order_item_id === 'string' && typeof item.study_display_name === 'string') : [];
    const general = document.createElement('label'); general.className = 'res02a-choice';
    const generalInput = document.createElement('input'); generalInput.type = 'radio'; generalInput.name = 'res02a-general'; generalInput.dataset.general = 'true';
    const generalText = el('span', 'RESULTADO GENERAL DE LA ORDEN'); generalText.append(el('small', 'No especificar estudios'));
    general.append(generalInput, generalText);
    const itemsHost = el('div', '', 'res02a-items');
    if (availableItems.length) {
      availableItems.forEach(item => {
        const label = document.createElement('label'); label.className = 'res02a-choice';
        const input = document.createElement('input'); input.type = 'checkbox'; input.dataset.item = 'true'; input.value = item.order_item_id;
        input.checked = availableItems.length === 1;
        const text = el('span', item.study_display_name); text.append(el('small', categories[item.study_category] || 'Otra categoría'));
        label.append(input, text); itemsHost.append(label);
        input.addEventListener('change', () => { coverage.dataset.touched = 'true'; if (input.checked) generalInput.checked = false; updateTitle(); });
      });
    } else {
      generalInput.checked = true;
      const legacy = Array.isArray(payload.requested_studies) ? payload.requested_studies.filter(value => typeof value === 'string') : [];
      if (legacy.length) { const list = el('ul', '', 'res02a-legacy'); legacy.forEach(value => list.append(el('li', value))); itemsHost.append(list); }
      itemsHost.append(el('p', 'Esta orden no tiene identificadores de estudio; la cobertura quedará sin especificar.'));
    }
    generalInput.addEventListener('change', () => { coverage.dataset.touched = 'true'; if (generalInput.checked) itemsHost.querySelectorAll('input[data-item]').forEach(input => { input.checked = false; }); updateTitle(); });
    coverage.append(itemsHost, general); updateTitle();
  }
  async function selectOrder(ref) {
    const session = active, request = ++loading; order = null; availableItems = []; context.textContent = 'Cargando orden…'; coverage.replaceChildren(); saveButton.disabled = true;
    if (!ref) { context.textContent = 'Selecciona una orden de esta consulta.'; return; }
    try {
      const data = await response(await fetch(`${base}doctors/${encodeURIComponent(session.doctorId)}/documents/${encodeURIComponent(ref)}`, {credentials:'same-origin', headers:{Accept:'application/json'}}));
      if (session !== active || request !== loading) return;
      const doc = data?.document || data;
      const payload = doc?.content?.payload || doc?.payload || {};
      const uuid = refs(doc?.document_uuid || doc?.document_id);
      const docPatient = refs(doc?.context?.patient_id);
      const docEncounter = refs(doc?.context?.encounter_id);
      if (!uuid || !orderTypes.has(refs(doc.document_type)) || docPatient !== session.patientId || refs(doc.status) === 'voided') throw new Error('La orden no pertenece al contexto autorizado.');
      if (session.encounterId && docEncounter !== session.encounterId) throw new Error('Selecciona una orden de esta consulta.');
      if (session.orderRef && uuid !== session.orderRef) throw new Error('La orden seleccionada cambió. Actualiza el listado.');
      if (session.orderRow && (refs(session.orderRow.document_uuid) !== uuid || Number(session.orderRow.has_successor) === 1)) throw new Error('La orden ya no es la versión vigente.');
      order = {document_uuid:uuid, document_type:doc.document_type, title:doc.title || 'Orden de estudio', patient_id:docPatient, encounter_id:docEncounter, appointment_id:refs(doc?.context?.appointment_id), payload};
      const origin = docEncounter ? 'Consulta' : refs(doc?.context?.hospital_stay_id) ? 'Hospitalización' : order.appointment_id ? 'Cita' : 'Expediente del paciente';
      context.replaceChildren(el('strong', order.title), el('p', `Fecha de emisión: ${date(doc?.timestamps?.generated_at || doc?.event_datetime)} · Origen: ${origin} · ${Array.isArray(payload.order_items) ? payload.order_items.length : Array.isArray(payload.requested_studies) ? payload.requested_studies.length : 0} estudio(s)`));
      renderCoverage(payload); saveButton.disabled = false;
    } catch (error) { if (session === active && request === loading) { context.textContent = ''; message(error.message); } }
  }
  async function submit(event) {
    event.preventDefault(); if (busy || !active || !order) return;
    if (!active.isCurrent()) { message('El paciente o la consulta cambió. Vuelve a abrir el resultado.'); return; }
    const selected = chosen(), ambiguous = generalSelected();
    if (!ambiguous && !selected.length) { message('Selecciona al menos un estudio o indica que no se especifican estudios.'); coverage.querySelector('input')?.focus(); return; }
    if (!form.reportValidity()) return;
    const binary = file.files?.[0];
    if (!binary || !allowedMime.has(binary.type) || binary.size > 26214400) { message('Selecciona un PDF, JPG, PNG o WebP de hasta 25 MiB.'); file.focus(); return; }
    const type = resultType(order, selected, ambiguous);
    if (order.encounter_id && active.encounterId && order.encounter_id !== active.encounterId) { message('La orden ya no corresponde a esta consulta.'); return; }
    const payload = {related_order_document_uuid:order.document_uuid, provenance:provenance.value.trim(), source:'res02a_linked_result'};
    if (!ambiguous) payload.related_order_item_ids = selected.map(item => item.order_item_id);
    const body = new FormData(); body.append('document_type', type); body.append('title', title.value.trim()); body.append('event_datetime', (attempt ||= {key:crypto.randomUUID(), time:utc()}).time); body.append('provenance', provenance.value.trim()); body.append('payload', JSON.stringify(payload)); body.append('file', binary);
    const url = order.encounter_id ? `${base}encounters/${encodeURIComponent('enc:' + order.encounter_id)}/documents` : `${base}doctors/${encodeURIComponent(active.doctorId)}/patients/${encodeURIComponent(active.patientId)}/documents`;
    busy = true; saveButton.disabled = true; message('Guardando resultado…');
    try {
      await response(await fetch(url, {method:'POST', credentials:'same-origin', headers:{Accept:'application/json', 'Idempotency-Key':attempt.key}, body}));
      const onSaved = active.onSaved; close(true); await onSaved?.();
    } catch (error) { message(error.message); }
    finally { busy = false; settleSave(); if (active) saveButton.disabled = false; }
  }
  function open(options) {
    setup(); if (dialog.open || busy) return;
    active = {...options, patientId:refs(options.patientId), doctorId:refs(options.doctorId), encounterId:refs(options.encounterId), orderRef:refs(options.orderRef), isCurrent:options.isCurrent || (() => true)};
    if (!active.patientId || !active.doctorId) { active = null; return; }
    if (active.trigger?.isConnected) { active.trigger.setAttribute('aria-haspopup','dialog'); active.trigger.setAttribute('aria-controls',dialog.id); active.trigger.setAttribute('aria-expanded','true'); }
    titleSuggested = ''; form.reset(); message(''); context.replaceChildren(); coverage.replaceChildren();delete coverage.dataset.touched; orderPicker.replaceChildren();
    if (!active.orderRef) {
      const label = el('label', 'Orden relacionada'); const select = document.createElement('select'); select.required = true; select.append(new Option('Selecciona una orden', ''));
      (active.orders || []).forEach(item => select.add(new Option(item.title || 'Orden de estudio', item.document_uuid)));
      select.addEventListener('change', () => selectOrder(select.value)); label.append(select); orderPicker.append(label);
      context.textContent = active.orders?.length ? 'Selecciona una orden de esta consulta.' : 'No hay órdenes disponibles en esta consulta.';
      saveButton.disabled = true;
    } else selectOrder(active.orderRef);
    dialog.showModal(); (orderPicker.querySelector('select') || node('[data-close]')).focus();
  }
  window.mxmedLinkedResultComposer = {open, isOpen:() => !!dialog?.open, close};
})();
