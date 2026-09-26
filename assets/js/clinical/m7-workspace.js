// M7 WS01–WS05 — encounter boundary, structured content, documents, and terminal actions.
(function(){
  const patientPane = document.getElementById('p-expediente');
  const root = document.getElementById('m7-workspace');
  if(!patientPane || !root) return;
  const workspaceTitle = root.querySelector('#m7-workspace-title');
  const status = root.querySelector('[data-m7-status]');
  const errorBox = root.querySelector('[data-m7-error]');
  const body = root.querySelector('[data-m7-body]');
  const context = root.querySelector('[data-m7-context]');
  const history = root.querySelector('[data-m7-history]');
  const historyList = root.querySelector('[data-m7-history-list]');
  const startButton = root.querySelector('[data-m7-start]');
  const resumeButton = root.querySelector('[data-m7-resume]');
  const currentButton = root.querySelector('[data-m7-current]');
  const legacyPanel = patientPane.querySelector('#t-historial-atencion > .clinical-panel');
  const editor = root.querySelector('[data-m7-editor]');
  const editorState = root.querySelector('[data-m7-editor-state]');
  const editorText = root.querySelector('[data-m7-editor-text]');
  const sectionDraftCue = root.querySelector('[data-vis30-section-draft]');
  const sectionDraftRecover = root.querySelector('[data-vis30-section-recover]');
  const sectionDraftDiscard = root.querySelector('[data-vis30-section-discard]');
  const editorSave = root.querySelector('[data-m7-editor-save]');
  const promoteProblem = root.querySelector('[data-lon04b-m7-promote]');
  editorText?.addEventListener('input',()=>{if(promoteProblem && selectedSection==='assessment') promoteProblem.classList.toggle('d-none',editorText.value!==sectionBaseline);});
  const conflictBox = root.querySelector('[data-m7-conflict]');
  const conflictTitle = root.querySelector('[data-m7-conflict-title]');
  const conflictMessage = root.querySelector('[data-m7-conflict-message]');
  const conflictDraft = root.querySelector('[data-m7-conflict-draft]');
  const conflictServer = root.querySelector('[data-m7-conflict-server]');
  const conflictReload = root.querySelector('[data-m7-conflict-reload]');
  const conflictUseDraft = root.querySelector('[data-m7-conflict-use-draft]');
  const sectionButtons = [...root.querySelectorAll('[data-m7-section]')];
  const sectionTypes = { reason:'reason_evolution', measurements:'measurements', exam:'physical_exam', assessment:'assessment', plan:'plan', documents:'documents', finalize:'finalize' };
  let epoch = 0;
  let active = null;
  let patientId = '';
  let busy = false;
  let sectionEpoch = 0;
  let selectedSection = 'reason_evolution';
  let loadedSections = {};
  let sectionMode = 'none';
  let sectionBusy = false;
  let sectionConflict = false;
  let sectionBaseline = '';
  let sectionVersion = null;
  let transitionBusy = false;
  let primaryTabBypass = false;
  let authorizedPatientChange = '';
  let appointmentHeaderEpoch = 0;
  let editorStateTimer = 0;
  let activeSectionDraftKey = '';
  const localDrafts = new Map();
  const ws03 = window.mxmedM7WS03?.(root, encounterUrlForWs03, ()=>patientId, ()=>{
    active = null;
    show(currentButton, false);
    show(resumeButton, false);
  });
  const ws04 = window.mxmedM7WS04?.(root, encounterUrlForWs03, ()=>patientId);
  const ws05 = window.mxmedM7WS05?.(root, encounterUrlForWs03, ()=>patientId, hasAnyUnsaved, detail=>{
    active = null;
    renderEncounter(detail, true);
    window.dispatchEvent(new Event('m7:encounter-state-changed'));
    show(currentButton, false);
    show(resumeButton, false);
    const seen = epoch;
    get(`/api/clinical/index.php/patients/${encodeURIComponent(patientId)}/encounters?limit=20`)
      .then(rows=>{ if(seen === epoch) renderHistory(rows, seen); })
      .catch(()=>{ if(seen === epoch) renderHistory([detail], seen); });
  });
  function encounterUrlForWs03(key){ return `/api/clinical/index.php/encounters/${encodeURIComponent(key)}`; }

  const selectedPatient = ()=> String(patientPane.dataset.patientId || patientPane.dataset.activePatientId || '').trim();
  const show = (node, visible)=> node?.classList.toggle('d-none', !visible);
  function setEditorState(message = '', { transient = false } = {}){
    clearTimeout(editorStateTimer);
    editorStateTimer = 0;
    editorState.textContent = String(message || '');
    editorState.hidden = !editorState.textContent;
    if(transient && editorState.textContent){
      const expected = editorState.textContent;
      editorStateTimer = window.setTimeout(()=>{
        editorStateTimer = 0;
        if(editorState.textContent === expected && !sectionBusy && !sectionConflict && !isDirty()){
          editorState.textContent = '';
          editorState.hidden = true;
        }
      }, 2000);
    }
  }
  function parseLocalClinicalDateTime(value){
    const safe = String(value || '').trim();
    const match = safe.match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?/);
    if(!match) return null;
    const date = new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]), Number(match[4]), Number(match[5]), Number(match[6] || 0));
    return Number.isNaN(date.getTime()) ? null : date;
  }
  function formatClinicalTime(value){
    const date = parseLocalClinicalDateTime(value);
    if(!date) return '';
    const hour = date.getHours();
    return `${hour % 12 || 12}:${String(date.getMinutes()).padStart(2,'0')} ${hour < 12 ? 'a. m.' : 'p. m.'}`;
  }
  function appointmentOriginLabel(appointment){
    const channel = String(appointment?.channel_origin || '').trim().toLowerCase();
    if(['public_agenda','agenda_public_profile','public_profile'].includes(channel)) return 'Reserva en línea';
    if(['call_center','call-center'].includes(channel)) return 'Call Center';
    return '';
  }
  function setCurrentEncounterHeader(detail){
    const safe = String(detail || '').trim();
    status.textContent = safe;
  }
  function setWorkspaceTitle(state){
    workspaceTitle.textContent = String(state || '').toLowerCase() === 'open' ? 'CONSULTA EN CURSO' : 'CONSULTA ACTUAL';
  }
  async function hydrateLinkedAppointmentHeader(encounter, token){
    const appointmentId = String(encounter?.appointment_id || '').trim();
    if(!appointmentId) return;
    try {
      const appointment = await get(`/api/agenda/index.php/appointments/${encodeURIComponent(appointmentId)}`);
      if(token !== appointmentHeaderEpoch || body.dataset.encounterKey !== String(encounter.encounter_key || '').trim()) return;
      const appointmentPatient = String(appointment?.patient_id || '').trim();
      if(appointmentPatient && appointmentPatient !== String(encounter.patient_id || patientId).trim()) return;
      const scheduledTime = formatClinicalTime(appointment?.start_at);
      if(!scheduledTime) return;
      const origin = appointmentOriginLabel(appointment);
      const detail = `Cita ${scheduledTime}${origin ? ` · ${origin}` : ''}`;
      setCurrentEncounterHeader(detail);
      context.textContent = detail;
    } catch (_) { /* No encounter timestamp may substitute for a missing appointment time. */ }
  }
  const errorCode = (value)=> typeof value === 'string' ? value : String(value?.code || '');
  const messages = {
    SCHEMA_NOT_READY:'El expediente clínico no está listo. Vuelve a intentarlo después de verificar el sistema.',
    M6_WRITE_WINDOW_BLOCKED:'Las escrituras clínicas están pausadas. No se inició ninguna consulta.',
    M6_LEGACY_WRITE_BLOCKED:'Esta acción antigua está bloqueada. No se inició ninguna consulta.',
    IDEMPOTENCY_KEY_REUSED:'El intento de inicio cambió. Comprueba la consulta activa antes de reintentar.',
    DOCUMENT_CONTEXT_MISMATCH:'El documento no corresponde a esta consulta.',
    ENCOUNTER_TERMINAL:'Esta consulta ya terminó; vuelve a cargar su estado.',
    VERSION_CONFLICT:'La consulta cambió en otra sesión. Vuelve a cargar antes de continuar.',
    not_found:'No se encontró la consulta en el contexto autorizado.',
    forbidden:'No tienes acceso a esta consulta.'
  };
  function fail(reason){
    const code = errorCode(reason?.code || reason?.error || '');
    errorBox.textContent = messages[code] || reason?.message || 'No se pudo cargar la consulta. Vuelve a intentarlo.';
    show(errorBox, true);
  }
  async function get(url, withMeta = false){
    const response = await fetch(url, { credentials:'same-origin', headers:{ Accept:'application/json' } });
    const result = await response.json().catch(()=> null);
    if(!response.ok || result?.ok !== true){
      const failure = new Error(result?.error?.message || result?.message || 'No se pudo cargar la consulta.');
      failure.code = errorCode(result?.error) || String(response.status);
      throw failure;
    }
    return withMeta ? { data:result.data, meta:result.meta || {} } : result.data;
  }
  const encounterUrl = key=> `/api/clinical/index.php/encounters/${encodeURIComponent(key)}`;
  const draftKey = (key, type)=> `mxmed.m7.ws02.draft:${key}:${type}`;
  function readDraft(key, type){
    const id = draftKey(key, type);
    if(localDrafts.has(id)) return localDrafts.get(id);
    try { return sessionStorage.getItem(id); } catch (_) { return null; }
  }
  function rememberDraft(key, type, value){
    if(!key || !type) return;
    const id = draftKey(key, type);
    localDrafts.set(id, value);
    try { sessionStorage.setItem(id, value); } catch (_) { /* In-memory draft remains available. */ }
  }
  function clearDraft(key, type){
    const id = draftKey(key, type);
    localDrafts.delete(id);
    try { sessionStorage.removeItem(id); } catch (_) { /* In-memory state is already clear. */ }
  }
  function isDirty(options){
    if(selectedSection === 'finalize') return !!ws05?.isDirty(options);
    if(selectedSection === 'documents') return !!body.dataset.encounterKey && !!ws04?.isDirty();
    return sectionMode === 'open' && !!body.dataset.encounterKey &&
      (selectedSection === 'measurements' || selectedSection === 'physical_exam' ? !!ws03?.isDirty() : editorText.value !== sectionBaseline);
  }
  function hasAnyUnsaved(options){
    if(window.mxmedPlanNextSteps?.hasPending() || window.mxmedPlanNextSteps?.isBusy()) return true;
    const key = body.dataset.encounterKey || '';
    if(!key || isDirty(options) || ws03?.isDirty() || ws04?.isDirty() || ws03?.isBusy() || ws04?.isBusy() || sectionBusy) return true;
    for(const type of ['reason_evolution','assessment','plan']){
      const draft = readDraft(key,type);
      const saved = String(loadedSections[type]?.narrative_text || '');
      if(draft !== null && draft !== saved) return true;
    }
    return !!ws03?.hasSavedDrafts?.();
  }
  function protectNavigation(){
    if(!isDirty()) return true;
    if(selectedSection === 'finalize') return window.confirm('Hay texto de corrección o anulación sin enviar. ¿Deseas continuar?');
    if(selectedSection === 'documents') return window.confirm('Hay una acción clínica sin guardar. ¿Deseas salir y descartar la selección local?');
    if(selectedSection === 'measurements' || selectedSection === 'physical_exam') ws03?.remember();
    else rememberDraft(body.dataset.encounterKey, selectedSection, editorText.value);
    return window.confirm('Tienes cambios clínicos sin guardar. Se conservará tu borrador en esta pestaña. ¿Deseas continuar?');
  }
  function eligibleCaptureIsDirty(){
    return ['reason_evolution','measurements','physical_exam','assessment','plan'].includes(selectedSection) && isDirty();
  }
  async function saveEligibleCapture(){
    if(!eligibleCaptureIsDirty()) return true;
    if(transitionBusy || sectionBusy || ws03?.isBusy()) return false;
    transitionBusy = true;
    sectionButtons.forEach(button=>button.disabled = true);
    try {
      if(selectedSection === 'measurements' || selectedSection === 'physical_exam') return (await ws03?.saveSelected?.()) === true;
      return (await saveSection()) === true;
    } finally {
      transitionBusy = false;
      sectionButtons.forEach(button=>{ if(sectionTypes[button.dataset.m7Section]) button.disabled = false; });
    }
  }
  function patientDisplayName(){
    return String(patientPane.querySelector('.vis02-patient-name, [data-clinical-field="patient_name"]')?.textContent || 'este paciente').trim();
  }
  function ensureLeaveDialog(){
    let modal = document.getElementById('m7-open-consultation-leave-modal');
    if(modal) return modal;
    modal = document.createElement('div');
    modal.id = 'm7-open-consultation-leave-modal';
    modal.className = 'modal fade';
    modal.tabIndex = -1;
    modal.setAttribute('aria-labelledby','m7-open-consultation-leave-title');
    modal.setAttribute('aria-describedby','m7-open-consultation-leave-copy');
    modal.setAttribute('aria-hidden','true');
    modal.innerHTML = `<div class="modal-dialog modal-dialog-centered"><div class="modal-content">
      <div class="modal-header"><h2 class="modal-title fs-5" id="m7-open-consultation-leave-title">Consulta en curso</h2></div>
      <div class="modal-body"><p id="m7-open-consultation-leave-copy"></p><p class="mb-0">¿Qué deseas hacer?</p></div>
      <div class="modal-footer m7-leave-intent-actions">
        <button type="button" class="btn btn-outline-secondary" data-m7-leave-choice="continue">Continuar aquí</button>
        <button type="button" class="btn btn-outline-primary" data-m7-leave-choice="finalize">Ir a finalizar consulta</button>
        <button type="button" class="btn btn-primary" data-m7-leave-choice="keep-open">Salir y mantener consulta en curso</button>
      </div></div></div>`;
    document.body.append(modal);
    return modal;
  }
  function chooseExternalLeaveIntent(){
    const modal = ensureLeaveDialog();
    modal.querySelector('#m7-open-consultation-leave-copy').textContent = `La consulta de ${patientDisplayName()} permanecerá abierta.`;
    return new Promise(resolve=>{
      const instance = window.bootstrap?.Modal.getOrCreateInstance(modal,{backdrop:'static',keyboard:false});
      let settled = false;
      const finish = choice=>{
        if(settled) return;
        settled = true;
        modal.removeEventListener('click', onClick);
        instance?.hide();
        resolve(choice);
      };
      const onClick = event=>{
        const button = event.target.closest('[data-m7-leave-choice]');
        if(button) finish(button.dataset.m7LeaveChoice);
      };
      modal.addEventListener('click', onClick);
      instance?.show();
    });
  }
  async function mayLeaveCurrentPatientContext(options = {}){
    if(window.mxmedPlanNextSteps && !(await window.mxmedPlanNextSteps.mayLeave())) return false;
    const currentPatientId = selectedPatient();
    if(options.reason === 'change_patient' && authorizedPatientChange === currentPatientId){ authorizedPatientChange = ''; return true; }
    if(!active || String(active.status || '').toLowerCase() !== 'open') return true;
    if(String(active.patient_id || currentPatientId).trim() !== currentPatientId) return true;
    const activeKey = String(active.encounter_key || '').trim();
    if(!activeKey || String(body.dataset.encounterKey || '').trim() !== activeKey) return true;
    if((selectedSection === 'documents' || selectedSection === 'finalize') && isDirty() && !protectNavigation()) return false;
    const choice = await chooseExternalLeaveIntent();
    if(choice === 'continue') return false;
    if(choice === 'finalize'){
      if(eligibleCaptureIsDirty() && !(await saveEligibleCapture())) return false;
      await openFinalizationStep();
      return false;
    }
    if(choice !== 'keep-open') return false;
    if(eligibleCaptureIsDirty() && !(await saveEligibleCapture())) return false;
    if(options.reason === 'change_patient_landing') authorizedPatientChange = currentPatientId;
    return true;
  }
  function sectionRow(type){ return loadedSections[type] || null; }
  function paintSection(){
    body.dataset.plan02bSection = selectedSection;
    const ws03Selected = selectedSection === 'measurements' || selectedSection === 'physical_exam';
    const ws04Selected = selectedSection === 'documents';
    const ws05Selected = selectedSection === 'finalize';
    show(editor, !ws03Selected && !ws04Selected && !ws05Selected);
    ws03?.select(selectedSection);
    ws04?.select(ws04Selected);
    ws05?.select(ws05Selected);
    if(ws03Selected || ws04Selected || ws05Selected){
      sectionButtons.forEach(button=>button.setAttribute('aria-current', sectionTypes[button.dataset.m7Section] === selectedSection ? 'true' : 'false'));
      return;
    }
    const key = body.dataset.encounterKey || '';
    const row = sectionRow(selectedSection);
    if(promoteProblem){promoteProblem.dataset.sectionId = row?.section_id || '';promoteProblem.dataset.encounterId = body.dataset.encounterId || '';}
    sectionVersion = row ? Number(row.row_version) : null;
    sectionBaseline = row ? String(row.narrative_text || '') : '';
    const draftId = draftKey(key, selectedSection);
    let draft = readDraft(key, selectedSection);
    if(draft !== null && draft === sectionBaseline){clearDraft(key,selectedSection);draft=null;if(activeSectionDraftKey===draftId)activeSectionDraftKey='';}
    const passiveDraft = draft !== null && activeSectionDraftKey !== draftId;
    editorText.value = passiveDraft || draft === null ? sectionBaseline : draft;
    show(sectionDraftCue,passiveDraft);
    sectionDraftRecover.disabled=sectionMode!=='open';
    sectionDraftDiscard.disabled=sectionMode!=='open';
    show(promoteProblem, selectedSection === 'assessment' && !!row?.section_id && !!sectionBaseline.trim() && editorText.value === sectionBaseline);
    editorText.readOnly = sectionMode !== 'open' || sectionConflict || sectionBusy || passiveDraft;
    editorSave.disabled = sectionMode !== 'open' || sectionBusy || sectionConflict || passiveDraft || !isDirty();
    show(editorSave, sectionMode === 'open');
    setEditorState(sectionMode !== 'open' ? 'Sólo lectura' : sectionConflict ? 'Conflicto: revisa ambas versiones' : isDirty() ? 'Cambios sin guardar' : '');
    sectionButtons.forEach(button=>button.setAttribute('aria-current', sectionTypes[button.dataset.m7Section] === selectedSection ? 'true' : 'false'));
  }
  function setConflict(title, message, draft, server){
    sectionConflict = true;
    conflictTitle.textContent = title;
    conflictMessage.textContent = message;
    conflictDraft.value = draft;
    conflictServer.value = server;
    conflictUseDraft.disabled = true;
    show(conflictBox, true);
    paintSection();
  }
  async function loadSections(encounter){
    const seen = ++sectionEpoch;
    const key = String(encounter.encounter_key || '').trim();
    const state = String(encounter.status || '').toLowerCase();
    sectionMode = state === 'open' ? 'open' : 'terminal';
    sectionConflict = false;
    loadedSections = {};
    show(conflictBox, false);
    show(editor, true);
    editorState.textContent = 'Cargando contenido de esta consulta…';
    editorText.readOnly = true;
    editorSave.disabled = true;
    sectionButtons.forEach(button=>{ if(sectionTypes[button.dataset.m7Section]) button.disabled = false; });
    try {
      const detail = Object.prototype.hasOwnProperty.call(encounter, 'sections') && Object.prototype.hasOwnProperty.call(encounter, 'observations')
        ? encounter : await get(encounterUrl(key));
      if(seen !== sectionEpoch || key !== body.dataset.encounterKey || String(detail.patient_id || '') !== patientId) return;
      body.dataset.encounterId = String(detail.encounter_id || body.dataset.encounterId || '').trim();
      loadedSections = detail.sections && typeof detail.sections === 'object' ? detail.sections : {};
      sectionMode = String(detail.status || '').toLowerCase() === 'open' ? 'open' : 'terminal';
      ws03?.load(detail, key, sectionMode);
      ws04?.load(detail);
      ws05?.load(detail);
      paintSection();
    } catch (error) {
      if(seen !== sectionEpoch) return;
      sectionMode = 'error';
      setEditorState(errorCode(error?.code) === 'SCHEMA_NOT_READY'
        ? 'El esquema clínico no está listo. No se cargó esta sección.'
        : 'No se pudo cargar esta sección. Vuelve a abrir la consulta.');
      editorText.readOnly = true;
      editorSave.disabled = true;
    }
  }
  async function reloadAfterConflict(){
    const key = body.dataset.encounterKey;
    const type = selectedSection;
    const draft = conflictDraft.value;
    conflictReload.disabled = true;
    try {
      const detail = await get(encounterUrl(key));
      if(key !== body.dataset.encounterKey || type !== selectedSection || String(detail.patient_id || '') !== patientId) return;
      loadedSections = detail.sections || {};
      if(String(detail.status || '').toLowerCase() !== 'open'){
        sectionMode = 'terminal';
        conflictMessage.textContent = 'La consulta terminó. Tu borrador sigue disponible para copiarlo; no se puede guardar aquí.';
        paintSection();
        return;
      }
      sectionMode = 'open';
      sectionConflict = false;
      clearDraft(key, type);
      if(activeSectionDraftKey===draftKey(key,type))activeSectionDraftKey='';
      paintSection();
      conflictDraft.value = draft;
      conflictServer.value = editorText.value;
      conflictMessage.textContent = 'Revisa la versión guardada y tu borrador. Puedes copiar cambios o elegir tu borrador; después deberás guardar deliberadamente.';
      conflictUseDraft.disabled = false;
      show(conflictBox, true);
    } catch (_) {
      conflictMessage.textContent = 'No se pudo cargar la versión actual. Tu borrador permanece disponible; inténtalo de nuevo.';
    } finally { conflictReload.disabled = false; }
  }
  async function saveSection(){
    const key = body.dataset.encounterKey;
    const type = selectedSection;
    if(sectionBusy || sectionConflict || sectionMode !== 'open' || !key) return false;
    if(!isDirty()) return true;
    const draft = editorText.value;
    const expectedVersion = sectionVersion;
    sectionBusy = true;
    editorText.readOnly = true;
    editorSave.disabled = true;
    setEditorState('Guardando…');
    const data = { payload_schema_version:1, payload:{}, narrative_text:draft };
    if(expectedVersion !== null) data.row_version = expectedVersion;
    try {
      const response = await fetch(`${encounterUrl(key)}/sections/${encodeURIComponent(type)}`, {
        method:'PUT', credentials:'same-origin', headers:{ Accept:'application/json', 'Content-Type':'application/json' },
        body:JSON.stringify(data)
      });
      const result = await response.json().catch(()=>null);
      const code = errorCode(result?.error) || String(response.status);
      if(key !== body.dataset.encounterKey || type !== selectedSection) return;
      if(!response.ok || result?.ok !== true){
        if(code === 'VERSION_CONFLICT'){
          rememberDraft(key, type, draft);
          let server = '';
          try {
            const detail = await get(encounterUrl(key));
            server = String(detail?.sections?.[type]?.narrative_text || '');
          } catch (_) { /* Preserve local text even when the competing version cannot load. */ }
          setConflict('Otra persona actualizó esta sección.', 'Tu borrador no se guardó. Revisa ambas versiones antes de decidir.', draft, server);
        } else if(code === 'ENCOUNTER_TERMINAL' || code === 'ENCOUNTER_CLOSED' || code === 'ENCOUNTER_VOIDED'){
          rememberDraft(key, type, draft);
          setConflict('Esta consulta ya terminó.', 'El guardado fue rechazado. Tu borrador sigue disponible para copiarlo; no se reabrirá la consulta.', draft, '');
          sectionMode = 'terminal';
          try {
            const detail = await get(encounterUrl(key));
            loadedSections = detail.sections || {};
            conflictServer.value = String(loadedSections[type]?.narrative_text || '');
            const state = String(detail.status || '').toLowerCase();
            setWorkspaceTitle(state);
            body.dataset.encounterState = state;
            context.textContent = `Consulta histórica · ${state === 'voided' ? 'Anulada' : 'Finalizada'}`;
            status.textContent = 'Consulta histórica de sólo lectura.';
            active = null;
            show(currentButton, false);
          } catch (_) {}
          paintSection();
        } else {
          rememberDraft(key, type, draft);
          if(code === 'DOCUMENT_CONTEXT_MISMATCH') sectionMode = 'error';
          setEditorState(code === 'M6_WRITE_WINDOW_BLOCKED' ? 'Guardado temporalmente pausado; tu borrador se conserva.'
            : code === 'SCHEMA_NOT_READY' ? 'El esquema clínico no está listo; tu borrador se conserva.'
            : code === 'DOCUMENT_CONTEXT_MISMATCH' ? 'El contexto de la consulta cambió. Vuelve a abrirla antes de guardar.'
            : 'No se guardó la sección. Tu borrador se conserva.');
        }
        return false;
      }
      const row = result.data || {};
      loadedSections[type] = {
        section_id:Number(row.section_id), section_type:type, narrative_text:String(row.narrative_text || draft), row_version:Number(row.row_version),
        updated_at:String(row.updated_at || ''), payload_schema_version:Number(row.payload_schema_version || 1)
      };
      clearDraft(key, type);
      if(activeSectionDraftKey===draftKey(key,type))activeSectionDraftKey='';
      paintSection();
      setEditorState('Guardado', { transient:true });
      return true;
    } catch (_) {
      rememberDraft(key, type, draft);
      setEditorState('Sin conexión. Tu borrador se conserva; inténtalo de nuevo.');
      return false;
    } finally {
      sectionBusy = false;
      if(key === body.dataset.encounterKey && type === selectedSection){
        editorText.readOnly = sectionMode !== 'open' || sectionConflict || !sectionDraftCue.classList.contains('d-none');
        if(!sectionConflict) editorSave.disabled = sectionMode !== 'open' || !isDirty();
      }
    }
  }
  function reset(){
    appointmentHeaderEpoch++;
    activeSectionDraftKey = '';
    setEditorState('');
    sectionEpoch++;
    ws03?.reset();
    ws04?.reset();
    ws05?.reset();
    sectionMode = 'none';
    sectionConflict = false;
    active = null;
    show(legacyPanel, true);
    show(body, false);
    delete body.dataset.encounterKey;
    delete body.dataset.encounterId;
    delete body.dataset.encounterState;
    show(history, false);
    show(startButton, false);
    show(resumeButton, false);
    show(currentButton, false);
    show(errorBox, false);
    show(editor, false);
    show(conflictBox, false);
    sectionButtons.forEach(button=>{ button.disabled = true; button.removeAttribute('aria-current'); });
    historyList.replaceChildren();
    setWorkspaceTitle('');
    status.textContent = 'Selecciona un paciente para consultar su atención.';
  }
  function renderEncounter(encounter, historical){
    const headerToken = ++appointmentHeaderEpoch;
    const state = String(encounter.status || '').toLowerCase();
    setWorkspaceTitle(state);
    const label = state === 'voided' ? 'Anulada' : state === 'closed' ? 'Finalizada' : 'En curso';
    const when = String(encounter.event_datetime || encounter.encounter_dt || '').trim();
    show(body, true);
    show(currentButton, historical && !!active);
    show(resumeButton, false);
    show(startButton, false);
    const startedTime = formatClinicalTime(when);
    const fallbackDetail = historical ? `${label}${when ? ` · ${when}` : ''}` : encounter.appointment_id ? '' : startedTime ? `Iniciada ${startedTime}` : '';
    const contextLine = fallbackDetail;
    context.textContent = contextLine;
    if(historical) status.textContent = `· Sólo lectura${contextLine ? ` · ${contextLine}` : ''}`;
    else setCurrentEncounterHeader(fallbackDetail);
    body.dataset.encounterKey = String(encounter.encounter_key || '').trim();
    body.dataset.encounterId = String(encounter.encounter_id || '').trim();
    body.dataset.encounterState = state;
    loadSections(encounter);
    if(!historical && encounter.appointment_id) void hydrateLinkedAppointmentHeader(encounter, headerToken);
  }
  async function selectHistorical(row, seen){
    if(!protectNavigation()) return;
    const key = String(row.encounter_key || (row.encounter_id ? `enc:${row.encounter_id}` : '')).trim();
    if(!key) return;
    show(errorBox, false);
    try{
      const detail = await get(`/api/clinical/index.php/encounters/${encodeURIComponent(key)}`);
      if(seen !== epoch || String(detail?.patient_id || '') !== patientId) throw new Error('El contexto de la consulta cambió. Vuelve a cargar.');
      if(!['closed','voided'].includes(String(detail.status || '').toLowerCase())) throw new Error('Esta consulta ya no es histórica. Vuelve a cargar.');
      renderEncounter(detail, true);
    }catch(error){ if(seen === epoch) fail(error); }
  }
  function renderHistory(rows, seen){
    historyList.replaceChildren();
    const past = (Array.isArray(rows) ? rows : []).filter(row=> ['closed','voided'].includes(String(row?.status || '').toLowerCase()));
    show(history, past.length > 0);
    past.forEach(row=>{
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'm7-workspace-history-item';
      button.textContent = `${String(row.status).toLowerCase() === 'voided' ? 'Anulada' : 'Finalizada'} · ${String(row.encounter_dt || row.event_datetime || '').trim() || 'Fecha no disponible'}`;
      button.addEventListener('click', ()=> selectHistorical(row, seen));
      historyList.append(button);
    });
  }
  async function refresh({ openExisting = false } = {}){
    const hadDraft = isDirty();
    if(hadDraft){
      if(selectedSection === 'documents') { /* Form controls remain local until navigation completes. */ }
      else if(selectedSection === 'measurements' || selectedSection === 'physical_exam') ws03?.remember();
      else rememberDraft(body.dataset.encounterKey, selectedSection, editorText.value);
    }
    const seen = ++epoch;
    patientId = selectedPatient();
    reset();
    show(root, true);
    if(!patientId) return;
    show(legacyPanel, false); // Fail closed while the exact-pair route is unresolved.
    status.textContent = 'Consultando las atenciones de este paciente…';
    try{
      const base = `/api/clinical/index.php/patients/${encodeURIComponent(patientId)}/encounters`;
      const snapshot = await get(`${base}/active`, true);
      if(seen !== epoch || selectedPatient() !== patientId) return;
      if(snapshot.meta.integrity_v1 !== true){
        show(root, false); // Existing non-M7 clinical UI keeps its prior authority.
        show(legacyPanel, true);
        return;
      }
      show(legacyPanel, false); // No parallel legacy current-consultation writer in M7.
      const current = snapshot.data;
      active = current?.encounter_key ? current : null;
      if(active && openExisting) renderEncounter(active, false);
      else {
        status.textContent = active ? 'Hay una consulta activa. Puedes continuarla.' : 'Este paciente no tiene una consulta activa.';
        show(resumeButton, !!active);
        show(startButton, !active);
      }
      if(hadDraft){
        errorBox.textContent = 'Tu borrador sin guardar se conserva en esta pestaña para la consulta de origen.';
        show(errorBox, true);
      }
      try{
        const rows = await get(`${base}?limit=20`);
        if(seen === epoch) renderHistory(rows, seen);
      }catch(error){ if(seen === epoch) fail(error); }
    }catch(error){
      if(seen === epoch){ status.textContent = 'No se pudo comprobar el estado de la consulta.'; fail(error); }
    }
  }
  startButton.addEventListener('click', async ()=>{
    if(busy || !patientId || selectedPatient() !== patientId) return;
    busy = true;
    startButton.disabled = true;
    show(errorBox, false);
    const seen = epoch;
    try{
      if(typeof window.mxmedExplicitStartEncounter !== 'function') throw new Error('El inicio de consulta no está disponible.');
      const result = await window.mxmedExplicitStartEncounter(patientId, { requireV1:true });
      if(seen !== epoch || selectedPatient() !== patientId) return;
      active = result;
      renderEncounter(result, false);
      window.dispatchEvent(new CustomEvent('m7:encounter-started', { detail:{ patient_id:patientId, encounter_key:result.encounter_key } }));
    }catch(error){ if(seen === epoch) fail(error); }
    finally{ busy = false; startButton.disabled = false; }
  });
  resumeButton.addEventListener('click', ()=>{ if(active) renderEncounter(active, false); });
  currentButton.addEventListener('click', ()=>{ if(active && protectNavigation()) renderEncounter(active, false); });
  async function transitionToSection(type){
    if(window.mxmedPlanNextSteps?.isBusy()) return false;
    if(!type || type === selectedSection || transitionBusy || sectionBusy || ws03?.isBusy() || ws04?.isBusy() || ws05?.isBusy()) return false;
    if(selectedSection === 'measurements' && !(await ws03.resolvePendingNavigation())) return false;
    if(eligibleCaptureIsDirty()){
      if(!(await saveEligibleCapture())) return false;
    } else if(!protectNavigation()) return false;
    selectedSection = type;
    sectionConflict = false;
    show(conflictBox, false);
    paintSection();
    return true;
  }
  sectionButtons.forEach(button=>button.addEventListener('click', async ()=>{
    const type = sectionTypes[button.dataset.m7Section];
    if(!type || button.disabled) return;
    await transitionToSection(type);
  }));
  editorText.addEventListener('input', ()=>{
    if(sectionMode !== 'open') return;
    activeSectionDraftKey=draftKey(body.dataset.encounterKey,selectedSection);
    rememberDraft(body.dataset.encounterKey, selectedSection, editorText.value);
    setEditorState(isDirty() ? 'Cambios sin guardar' : '');
    editorSave.disabled = sectionBusy || sectionConflict || !isDirty();
  });
  editorSave.addEventListener('click', saveSection);
  sectionDraftRecover.addEventListener('click',()=>{
    const key=body.dataset.encounterKey||'', draft=readDraft(key,selectedSection);
    if(sectionMode!=='open'||draft===null||sectionDraftCue.classList.contains('d-none'))return;
    activeSectionDraftKey=draftKey(key,selectedSection);
    paintSection();editorText.focus();
  });
  sectionDraftDiscard.addEventListener('click',()=>{
    const key=body.dataset.encounterKey||'';
    if(sectionMode!=='open'||sectionDraftCue.classList.contains('d-none'))return;
    clearDraft(key,selectedSection);activeSectionDraftKey='';paintSection();
  });
  conflictReload.addEventListener('click', reloadAfterConflict);
  conflictUseDraft.addEventListener('click', ()=>{
    if(sectionConflict || sectionMode !== 'open' || conflictUseDraft.disabled) return;
    editorText.value = conflictDraft.value;
    activeSectionDraftKey=draftKey(body.dataset.encounterKey,selectedSection);
    rememberDraft(body.dataset.encounterKey, selectedSection, editorText.value);
    paintSection();
    editorText.focus();
  });
  window.addEventListener('beforeunload', event=>{
    if(!isDirty()) return;
    if(selectedSection === 'documents') { /* The browser owns the selected File until the page closes. */ }
    else if(selectedSection === 'measurements' || selectedSection === 'physical_exam') ws03?.remember();
    else rememberDraft(body.dataset.encounterKey, selectedSection, editorText.value);
    event.preventDefault();
    event.returnValue = '';
  });
  const workspaceTab = patientPane.querySelector('[data-bs-target="#t-consulta-actual"]');
  let pendingEntryIntent = '';
  let workspaceEntryPromise = Promise.resolve();
  async function openFinalizationStep(){
    if(!active || String(active.status || '').toLowerCase() !== 'open') return false;
    if(!workspaceTab?.classList.contains('active')){
      pendingEntryIntent = 'finalize';
      window.bootstrap?.Tab.getOrCreateInstance(workspaceTab).show();
      await Promise.resolve();
      await workspaceEntryPromise;
      return selectedSection === 'finalize' && workspaceTab.classList.contains('active');
    }
    const button = root.querySelector('[data-m7-section="finalize"]');
    if(!button || button.disabled) return false;
    if(body.dataset.encounterKey !== String(active.encounter_key || '') || body.dataset.encounterState !== 'open'){
      if(eligibleCaptureIsDirty()){
        if(!(await saveEligibleCapture())) return false;
      } else if(!protectNavigation()) return false;
      renderEncounter(active, false);
    }
    if(selectedSection !== 'finalize' && !(await transitionToSection('finalize'))) return false;
    window.requestAnimationFrame(()=>{
      const panel = root.querySelector('[data-m7-terminal]');
      panel?.scrollIntoView({behavior:'smooth', block:'start'});
      root.querySelector('[data-m7-finalize]')?.focus({preventScroll:true});
    });
    return true;
  }
  async function enterCurrentConsultation(intent = ''){
    const id = selectedPatient();
    await refresh({ openExisting:true });
    if(selectedPatient() !== id || !workspaceTab?.classList.contains('active') || root.classList.contains('d-none')) return;
    if(!active && intent === 'start' && !startButton.classList.contains('d-none')) startButton.click();
    if(active && intent === 'finalize') await openFinalizationStep();
  }
  ['patient:selected','expediente:patient_changed','expediente:patient-changed'].forEach(name=>{
    window.addEventListener(name, ()=> workspaceTab?.classList.contains('active') ? enterCurrentConsultation() : refresh());
  });
  // Header CTA reuses the workspace's explicit command and resume handlers.
  window.mxmedM7OpenFromHeader = async (id, intent) => {
    if (selectedPatient() !== id || !workspaceTab) return;
    if (workspaceTab.classList.contains('active') && eligibleCaptureIsDirty() && !(await saveEligibleCapture())) return;
    if (workspaceTab.classList.contains('active') && !eligibleCaptureIsDirty() && !protectNavigation()) return;
    if (workspaceTab.classList.contains('active')) return enterCurrentConsultation(intent);
    pendingEntryIntent = intent;
    window.bootstrap?.Tab.getOrCreateInstance(workspaceTab).show();
    if (!workspaceTab.classList.contains('active')) return; // dirty guard declined
    await workspaceEntryPromise;
  };
  window.mxmedM7OpenFinalizationFromHeader = async id => {
    if(selectedPatient() !== id || !workspaceTab) return false;
    if(workspaceTab.classList.contains('active')) return await openFinalizationStep();
    pendingEntryIntent = 'finalize';
    window.bootstrap?.Tab.getOrCreateInstance(workspaceTab).show();
    if(!workspaceTab.classList.contains('active')) return false;
    await workspaceEntryPromise;
    return selectedSection === 'finalize';
  };
  // VIS19: save eligible capture before transitions and use one deliberate OPEN-leave decision.
  window.mxmedM7MayLeaveCurrentPatientContext = mayLeaveCurrentPatientContext;
  window.mxmedM7RequestLeaveCurrentPatientContext = mayLeaveCurrentPatientContext;
  workspaceTab?.addEventListener('shown.bs.tab', ()=>{
    const intent = pendingEntryIntent;
    pendingEntryIntent = '';
    workspaceEntryPromise = enterCurrentConsultation(intent);
  });
  // VIS32 exits only the view. Existing writers and terminal guards remain authoritative.
  const exitButton = root.querySelector('[data-m7-exit]');
  let viewExitBusy = false, viewExitBypass = false;
  const lastRecordTab = new Map();
  const recordTab = target => patientPane.querySelector(`.vis01-primary-navigation .nav-item:not([hidden]):not(.d-none) [data-bs-target="${target}"]:not([disabled])`);
  document.addEventListener('shown.bs.tab', event => {
    if (event.target.closest('#p-expediente .vis01-primary-navigation .nav-item:not([hidden])')) lastRecordTab.set(selectedPatient(), event.target.dataset.bsTarget);
  });
  exitButton.addEventListener('click', async () => {
    if(viewExitBusy || busy || transitionBusy || sectionBusy || ws03?.isBusy() || ws04?.isBusy() || ws05?.isBusy()) return;
    const id = selectedPatient(), key = body.dataset.encounterKey;
    viewExitBusy = true; exitButton.disabled = true;
    try {
      if(window.mxmedPlanNextSteps && !window.mxmedPlanNextSteps.mayLeaveView()) return;
      if(eligibleCaptureIsDirty()) { if(!(await saveEligibleCapture())) return; }
      else if(!protectNavigation()) return;
      if(ws04 && !(await ws04.leaveView())) return;
      if(selectedPatient() !== id || body.dataset.encounterKey !== key) return;
      const target = recordTab(lastRecordTab.get(id)) || recordTab('#t-resumen-longitudinal');
      if(!target) return;
      viewExitBypass = true;
      window.bootstrap?.Tab.getOrCreateInstance(target).show();
      viewExitBypass = false;
      if(target.classList.contains('active')) requestAnimationFrame(() => { if(selectedPatient() === id && target.classList.contains('active')) { target.focus({preventScroll:true}); patientPane.querySelector('.exp-hdr')?.scrollIntoView({block:'start'}); } });
    } finally { viewExitBypass = false; viewExitBusy = false; exitButton.disabled = false; }
  });
  let nextStepsLeaveBypass = false;
  workspaceTab?.addEventListener('hide.bs.tab', event=>{
    if(viewExitBypass) return;
    if(!nextStepsLeaveBypass && (window.mxmedPlanNextSteps?.hasPending() || window.mxmedPlanNextSteps?.isBusy())){
      event.preventDefault();
      const destination = event.relatedTarget;
      void window.mxmedPlanNextSteps.mayLeave().then(allowed=>{
        if(!allowed || !destination) return;
        nextStepsLeaveBypass = true;
        window.bootstrap?.Tab.getOrCreateInstance(destination).show();
      });
      return;
    }
    nextStepsLeaveBypass = false;
    if(primaryTabBypass){ primaryTabBypass = false; return; }
    if(eligibleCaptureIsDirty()){
      event.preventDefault();
      const destination = event.relatedTarget;
      void saveEligibleCapture().then(saved=>{
        if(!saved || !destination) return;
        primaryTabBypass = true;
        window.bootstrap?.Tab.getOrCreateInstance(destination).show();
      });
      return;
    }
    if(!protectNavigation()) event.preventDefault();
  });
  new MutationObserver(()=>{ if(selectedPatient() !== patientId) workspaceTab?.classList.contains('active') ? enterCurrentConsultation() : refresh(); }).observe(patientPane, { attributes:true, attributeFilter:['data-patient-id','data-active-patient-id'] });
  refresh();
})();
