// M7 WS01 — patient/encounter boundary and read-only consultation shell.
(function(){
  const patientPane = document.getElementById('p-expediente');
  const root = document.getElementById('m7-workspace');
  if(!patientPane || !root) return;
  const status = root.querySelector('[data-m7-status]');
  const errorBox = root.querySelector('[data-m7-error]');
  const body = root.querySelector('[data-m7-body]');
  const context = root.querySelector('[data-m7-context]');
  const history = root.querySelector('[data-m7-history]');
  const historyList = root.querySelector('[data-m7-history-list]');
  const startButton = root.querySelector('[data-m7-start]');
  const resumeButton = root.querySelector('[data-m7-resume]');
  const currentButton = root.querySelector('[data-m7-current]');
  const legacyPanel = root.parentElement.querySelector('.clinical-panel');
  let epoch = 0;
  let active = null;
  let patientId = '';
  let busy = false;

  const selectedPatient = ()=> String(patientPane.dataset.patientId || patientPane.dataset.activePatientId || '').trim();
  const show = (node, visible)=> node?.classList.toggle('d-none', !visible);
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
  function reset(){
    active = null;
    show(legacyPanel, true);
    show(body, false);
    show(history, false);
    show(startButton, false);
    show(resumeButton, false);
    show(currentButton, false);
    show(errorBox, false);
    historyList.replaceChildren();
    status.textContent = 'Selecciona un paciente para consultar su atención.';
  }
  function renderEncounter(encounter, historical){
    const state = String(encounter.status || '').toLowerCase();
    const label = state === 'voided' ? 'Anulada' : state === 'closed' ? 'Finalizada' : 'Activa';
    const when = String(encounter.event_datetime || encounter.encounter_dt || '').trim();
    show(body, true);
    show(currentButton, historical && !!active);
    show(resumeButton, false);
    show(startButton, false);
    context.textContent = `${historical ? 'Consulta histórica' : 'Consulta actual'} · ${label}${when ? ` · ${when}` : ''}${encounter.appointment_id ? ' · Vinculada a cita' : ''}`;
    status.textContent = historical ? 'Consulta histórica de sólo lectura.' : 'Consulta activa de este paciente.';
    body.dataset.encounterKey = String(encounter.encounter_key || '').trim();
    body.dataset.encounterState = state;
  }
  async function selectHistorical(row, seen){
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
      button.textContent = `${String(row.status).toLowerCase() === 'voided' ? 'Anulada' : 'Finalizada'} · ${String(row.encounter_dt || '').trim() || 'Fecha no disponible'}`;
      button.addEventListener('click', ()=> selectHistorical(row, seen));
      historyList.append(button);
    });
  }
  async function refresh(){
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
      status.textContent = active ? 'Hay una consulta activa. Puedes continuarla.' : 'Este paciente no tiene una consulta activa.';
      show(resumeButton, !!active);
      show(startButton, !active);
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
  currentButton.addEventListener('click', ()=>{ if(active) renderEncounter(active, false); });
  ['patient:selected','expediente:patient_changed','expediente:patient-changed'].forEach(name=>{
    window.addEventListener(name, ()=> refresh());
  });
  patientPane.querySelector('[data-bs-target="#t-historial-atencion"]')?.addEventListener('shown.bs.tab', refresh);
  new MutationObserver(()=>{ if(selectedPatient() !== patientId) refresh(); }).observe(patientPane, { attributes:true, attributeFilter:['data-patient-id','data-active-patient-id'] });
  refresh();
})();
