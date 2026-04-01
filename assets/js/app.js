// MXMed app bundle
console.info('app.js loaded :: 20251123a');

// P11 single source shim
(function(){
  if(window.__mxmedPatientSourceShimApplied) return;
  window.__mxmedPatientSourceShimApplied = true;

  if(!window.mxmedStore || typeof window.mxmedStore !== 'object'){
    window.mxmedStore = {};
  }

  const syncPatientState = (rawPid)=>{
    const pid = String(rawPid || '').trim();
    const pane = document.getElementById('p-expediente');

    if(pid){
      window.mxmedStore.activePatientId = pid;
      window.mxmedActivePatientId = pid;
      window.__MXMED_ACTIVE_PATIENT_ID = pid;
      if(pane){
        pane.dataset.patientId = pid;
        pane.dataset.activePatientId = pid;
        pane.setAttribute('data-patient-id', pid);
        pane.setAttribute('data-active-patient-id', pid);
      }
    }else{
      window.mxmedStore.activePatientId = '';
      window.mxmedActivePatientId = '';
      window.__MXMED_ACTIVE_PATIENT_ID = '';
      if(pane){
        delete pane.dataset.patientId;
        delete pane.dataset.activePatientId;
        pane.removeAttribute('data-patient-id');
        pane.removeAttribute('data-active-patient-id');
      }
    }

    window.dispatchEvent(new Event('patient:selected'));
    window.dispatchEvent(new Event('expediente:patient_changed'));
    window.dispatchEvent(new Event('expediente:patient-changed'));
  };

  const wrapSetter = (name)=>{
    const current = window[name];
    if(typeof current === 'function' && current.__mxmedP11Wrapped === true){
      return;
    }
    const original = (typeof current === 'function') ? current : null;
    const wrapped = function(pid, opts){
      let result;
      if(original){
        try{
          result = original.call(window, pid, opts);
        }catch(_){}
      }
      if(result === false){
        return false;
      }
      if(result && typeof result.then === 'function'){
        return result.then((ok)=>{
          if(ok === false) return false;
          syncPatientState(pid);
          return ok;
        }).catch((err)=>{
          throw err;
        });
      }
      syncPatientState(pid);
      return result;
    };
    wrapped.__mxmedP11Wrapped = true;
    wrapped.__mxmedP11Original = original;
    window[name] = wrapped;
  };

  const applyWrap = ()=>{
    wrapSetter('setActivePatientId');
    wrapSetter('mxmedSetActivePatientId');
    if(window.mxmedSetActivePatientId !== window.setActivePatientId){
      window.mxmedSetActivePatientId = window.setActivePatientId;
    }
  };

  applyWrap();
  window.setTimeout(applyWrap, 0);
  document.addEventListener('DOMContentLoaded', ()=> window.setTimeout(applyWrap, 0), { once:true });
  console.info('P11 shim active');
})();

// P12 clinical context bridge (encounter_key as single source)
(function(){
  if(window.__mxmedClinicalContextBridgeApplied) return;
  window.__mxmedClinicalContextBridgeApplied = true;

  if(!window.mxmedStore || typeof window.mxmedStore !== 'object'){
    window.mxmedStore = {};
  }

  const cleanValue = (raw)=>{
    const value = String(raw || '').trim();
    return value || null;
  };

  const findExpedientePane = ()=>{
    const byId = document.getElementById('p-expediente');
    if(byId) return byId;

    const candidates = [
      document.querySelector('.mm-card.show[data-patient-id]'),
      document.querySelector('.mm-card.active[data-patient-id]'),
      document.querySelector('[data-active-patient-id]'),
      document.querySelector('[data-patient-id]')
    ];
    for(const node of candidates){
      if(node) return node;
    }
    return null;
  };

  const getActiveEncounterKey = ()=>{
    const fromStore = cleanValue(window.mxmedStore?.activeEncounterKey);
    if(fromStore) return fromStore;

    const pane = findExpedientePane();
    if(!pane) return null;
    return cleanValue(
      pane.dataset?.encounterKey ||
      pane.getAttribute('data-encounter-key') ||
      pane.dataset?.activeEncounterKey ||
      pane.getAttribute('data-active-encounter-key')
    );
  };

  const setEncounterContextOnPane = (encounterKey, patientId)=>{
    const pane = findExpedientePane();
    if(!pane) return false;

    const safeEncounterKey = cleanValue(encounterKey);
    const safePatientId = cleanValue(patientId);
    const setAttrIfChanged = (attrName, value)=>{
      const next = String(value || '');
      const prev = String(pane.getAttribute(attrName) || '').trim();
      if(prev === next) return false;
      pane.setAttribute(attrName, next);
      return true;
    };
    const removeAttrIfPresent = (attrName)=>{
      if(!pane.hasAttribute(attrName)) return false;
      pane.removeAttribute(attrName);
      return true;
    };

    if(safeEncounterKey){
      if(String(pane.dataset.encounterKey || '').trim() !== safeEncounterKey){
        pane.dataset.encounterKey = safeEncounterKey;
      }
      if(String(pane.dataset.activeEncounterKey || '').trim() !== safeEncounterKey){
        pane.dataset.activeEncounterKey = safeEncounterKey;
      }
      setAttrIfChanged('data-encounter-key', safeEncounterKey);
      setAttrIfChanged('data-active-encounter-key', safeEncounterKey);
    }else{
      if('encounterKey' in pane.dataset){
        delete pane.dataset.encounterKey;
      }
      if('activeEncounterKey' in pane.dataset){
        delete pane.dataset.activeEncounterKey;
      }
      removeAttrIfPresent('data-encounter-key');
      removeAttrIfPresent('data-active-encounter-key');
    }

    if(safePatientId){
      if(String(pane.dataset.patientId || '').trim() !== safePatientId){
        pane.dataset.patientId = safePatientId;
      }
      if(String(pane.dataset.activePatientId || '').trim() !== safePatientId){
        pane.dataset.activePatientId = safePatientId;
      }
      setAttrIfChanged('data-patient-id', safePatientId);
      setAttrIfChanged('data-active-patient-id', safePatientId);
    }

    return true;
  };

  window.getActiveEncounterKey = getActiveEncounterKey;
  window.setEncounterContextOnPane = setEncounterContextOnPane;

  let lastEncounterPayload = null;
  let lastEncounterPayloadKey = '';
  let encounterPayloadInFlight = null;

  const loadActiveEncounterPayload = async (encounterKey, opts = {})=>{
    const safeEncounterKey = cleanValue(encounterKey || getActiveEncounterKey());
    const force = opts && opts.force === true;
    if(!safeEncounterKey){
      console.warn('[P13] No hay encounter activo para cargar detalle.');
      return null;
    }
    if(!force && lastEncounterPayload && lastEncounterPayloadKey === safeEncounterKey){
      return lastEncounterPayload;
    }
    if(encounterPayloadInFlight && !force){
      return encounterPayloadInFlight;
    }

    const requestUrl = `/api/clinical/index.php/encounters/${encodeURIComponent(safeEncounterKey)}`;
    encounterPayloadInFlight = fetch(requestUrl, {
      method: 'GET',
      headers: { 'Accept':'application/json' },
      credentials: 'same-origin'
    }).then(async (resp)=>{
      const json = await resp.json().catch(()=> null);
      if(!json || json.ok !== true || !json.data){
        console.warn('[P13] Encounter payload fetch failed', {
          encounter_key: safeEncounterKey,
          status: resp.status
        });
        return null;
      }
      lastEncounterPayload = json;
      lastEncounterPayloadKey = safeEncounterKey;
      console.info('[P13] Encounter payload loaded', {
        encounter_key: safeEncounterKey,
        status: String(json?.data?.status || ''),
        documents: Array.isArray(json?.data?.documents) ? json.data.documents.length : 0
      });
      return json;
    }).catch(()=>{
      console.warn('[P13] Encounter payload request error', { encounter_key: safeEncounterKey });
      return null;
    }).finally(()=>{
      encounterPayloadInFlight = null;
    });

    return encounterPayloadInFlight;
  };

  window.loadActiveEncounterPayload = loadActiveEncounterPayload;

  const syncFromEvent = (eventName, ev)=>{
    const detail = (ev && ev.detail && typeof ev.detail === 'object') ? ev.detail : {};
    const encounterKey = cleanValue(detail.encounter_key || getActiveEncounterKey());
    const patientId = cleanValue(detail.patient_id || window.mxmedStore?.activePatientId);
    if(encounterKey){
      window.mxmedStore.activeEncounterKey = encounterKey;
    }
    if(patientId){
      window.mxmedStore.activePatientId = patientId;
    }
    setEncounterContextOnPane(encounterKey, patientId);
    return { encounterKey, patientId };
  };

  let lastBridgeLog = '';
  const handleEvent = (eventName)=>(ev)=>{
    const synced = syncFromEvent(eventName, ev);
    loadActiveEncounterPayload(synced.encounterKey);
    const signature = `${eventName}|${synced.patientId || ''}|${synced.encounterKey || ''}`;
    if(signature !== lastBridgeLog){
      lastBridgeLog = signature;
      console.info('[P12] context sync', {
        event: eventName,
        patient_id: synced.patientId || null,
        encounter_key: synced.encounterKey || null
      });
    }
  };

  window.addEventListener('encounter:active', handleEvent('encounter:active'));
  window.addEventListener('mxmed:encounter-changed', handleEvent('mxmed:encounter-changed'));

  // Initial best-effort sync for debug visibility.
  const boot = syncFromEvent('bootstrap', { detail:{} });
  if(boot && boot.encounterKey){
    loadActiveEncounterPayload(boot.encounterKey);
  }

  window.mxmedDebug = window.mxmedDebug || {};
  window.mxmedDebug.getEncounterKey = ()=> getActiveEncounterKey();
  window.mxmedDebug.getEncounterPayload = ()=> lastEncounterPayload;
})();

// P14C.1+ multi-active lifecycle instrumentation (frontend only)
(function(){
  if(window.__mxmedEncounterLifecycleApplied) return;
  window.__mxmedEncounterLifecycleApplied = true;

  if(!window.mxmedStore || typeof window.mxmedStore !== 'object'){
    window.mxmedStore = {};
  }

  const nowIso = ()=> new Date().toISOString();
  const clean = (raw)=> {
    const value = String(raw || '').trim();
    return value || '';
  };
  const normalizePatientLabel = (raw)=> String(raw || '').replace(/\s+/g, ' ').trim();
  const isGenericPatientLabel = (label, patientId = '')=>{
    const value = normalizePatientLabel(label);
    if(!value) return true;
    const lower = value.toLowerCase();
    const pid = clean(patientId).toLowerCase();
    if(lower === 'paciente') return true;
    if(/^paciente\b/i.test(value)){
      if(!pid) return true;
      const compact = lower.replace(/\s+/g, '');
      const pidCompact = pid.replace(/\s+/g, '');
      if(compact === `paciente${pidCompact}`) return true;
      if(compact === `pacientep_${pidCompact}`) return true;
      return true;
    }
    return false;
  };
  const ensurePatientLabelCache = ()=>{
    if(!window.mxmedStore.patientLabelById || typeof window.mxmedStore.patientLabelById !== 'object'){
      window.mxmedStore.patientLabelById = {};
    }
    return window.mxmedStore.patientLabelById;
  };
  const ensureEncounterLabelCache = ()=>{
    if(!window.mxmedStore.encounterLabelByKey || typeof window.mxmedStore.encounterLabelByKey !== 'object'){
      window.mxmedStore.encounterLabelByKey = {};
    }
    return window.mxmedStore.encounterLabelByKey;
  };
  const resolveNameFromDom = ()=>{
    const pane = findExpedientePane();
    if(!pane) return '';
    const nombre = clean(pane.querySelector('[data-pac-nombre]')?.value);
    const apPat = clean(pane.querySelector('[data-pac-apellido-paterno]')?.value);
    const apMat = clean(pane.querySelector('[data-pac-apellido-materno]')?.value);
    return normalizePatientLabel([nombre, apPat, apMat].filter(Boolean).join(' '));
  };
  const resolvePatientLabelForEntry = (entry = {}, detail = {})=>{
    const patientId = clean(entry.patient_id || detail.patient_id);
    const encounterKey = clean(entry.encounter_key || detail.encounter_key);
    const directCandidates = [
      detail.patient_label,
      detail.patient_name,
      detail.nombre_completo,
      detail.display_name,
      entry.patient_label,
      entry.patient_name,
      entry.nombre_completo
    ];
    for(const candidate of directCandidates){
      const normalized = normalizePatientLabel(candidate);
      if(!isGenericPatientLabel(normalized, patientId)) return normalized;
    }
    const byEncounter = normalizePatientLabel(ensureEncounterLabelCache()[encounterKey]);
    if(!isGenericPatientLabel(byEncounter, patientId)) return byEncounter;
    const byPatient = normalizePatientLabel(ensurePatientLabelCache()[patientId]);
    if(!isGenericPatientLabel(byPatient, patientId)) return byPatient;
    const draft = window.mxmedStore?.patientIdentityDrafts?.[patientId];
    if(draft && typeof draft === 'object'){
      const draftName = normalizePatientLabel([
        draft.nombre,
        draft.apellido_paterno,
        draft.apellido_materno
      ].map(clean).filter(Boolean).join(' '));
      if(!isGenericPatientLabel(draftName, patientId)) return draftName;
    }
    const currentPid = clean(window.mxmedStore.currentPatientId || window.mxmedStore.activePatientId);
    if(currentPid === patientId){
      const domName = resolveNameFromDom();
      if(!isGenericPatientLabel(domName, patientId)) return domName;
    }
    return '';
  };
  const persistPatientLabelForEntry = (entry = {}, detail = {})=>{
    const patientId = clean(entry.patient_id || detail.patient_id);
    const encounterKey = clean(entry.encounter_key || detail.encounter_key);
    const label = resolvePatientLabelForEntry(entry, detail);
    if(!label) return;
    if(patientId){
      ensurePatientLabelCache()[patientId] = label;
    }
    if(encounterKey){
      ensureEncounterLabelCache()[encounterKey] = label;
    }
    entry.patient_label = label;
  };
  const allowedStatus = new Set([
    'sin_consulta_activa',
    'consulta_activa',
    'consulta_pendiente_cierre',
    'consulta_cerrada'
  ]);
  const findExpedientePane = ()=>{
    const byId = document.getElementById('p-expediente');
    if(byId) return byId;
    return document.querySelector('[data-active-encounter-key], [data-encounter-key]');
  };
  const findP10Bar = ()=> document.getElementById('mm-p10-bar');
  const ensureActiveEncountersMap = ()=>{
    if(!window.mxmedStore.activeEncounters || typeof window.mxmedStore.activeEncounters !== 'object'){
      window.mxmedStore.activeEncounters = {};
    }
    return window.mxmedStore.activeEncounters;
  };
  const detectExistingEncounterKey = ()=>{
    const pane = findExpedientePane();
    const fromPane = clean(
      (pane && (pane.dataset?.activeEncounterKey || pane.getAttribute('data-active-encounter-key')))
      || (pane && (pane.dataset?.encounterKey || pane.getAttribute('data-encounter-key')))
    );
    if(fromPane) return fromPane;
    const p10Bar = findP10Bar();
    const fromP10 = clean(p10Bar && p10Bar.dataset ? p10Bar.dataset.encounterKey : '');
    if(fromP10) return fromP10;
    const fromStore = clean(window.mxmedStore.currentEncounterKey || window.mxmedStore.activeEncounterKey);
    if(fromStore) return fromStore;
    if(typeof window.getActiveEncounterKey === 'function'){
      const fromBridge = clean(window.getActiveEncounterKey());
      if(fromBridge) return fromBridge;
    }
    return '';
  };
  const detectExistingPatientId = ()=>{
    const pane = findExpedientePane();
    const fromPane = clean(
      (pane && (pane.dataset?.patientId || pane.getAttribute('data-patient-id')))
      || (pane && (pane.dataset?.activePatientId || pane.getAttribute('data-active-patient-id')))
    );
    if(fromPane) return fromPane;
    return clean(window.mxmedStore.currentPatientId || window.mxmedStore.activePatientId);
  };
  const pickEncounterForPatient = (patientId)=>{
    const safePatientId = clean(patientId);
    if(!safePatientId) return '';
    const map = ensureActiveEncountersMap();
    const candidates = Object.values(map).filter((entry)=>{
      if(!entry || typeof entry !== 'object') return false;
      const pid = clean(entry.patient_id);
      const status = clean(entry.status);
      return pid === safePatientId && (status === 'consulta_activa' || status === 'consulta_pendiente_cierre');
    });
    if(!candidates.length) return '';
    candidates.sort((a, b)=>{
      const da = clean(a.last_activity_at || a.started_at);
      const db = clean(b.last_activity_at || b.started_at);
      return db.localeCompare(da);
    });
    return clean(candidates[0].encounter_key);
  };
  const syncCurrentContextNodes = ()=>{
    const map = ensureActiveEncountersMap();
    const pane = findExpedientePane();
    const p10Bar = findP10Bar();
    const currentPatientId = clean(window.mxmedStore.currentPatientId || window.mxmedStore.activePatientId);
    let currentEncounterKey = clean(window.mxmedStore.currentEncounterKey);
    const currentEntry = currentEncounterKey ? map[currentEncounterKey] : null;
    const belongsToCurrentPatient = !!(currentEntry && clean(currentEntry.patient_id) === currentPatientId);
    if(!belongsToCurrentPatient){
      currentEncounterKey = pickEncounterForPatient(currentPatientId);
      window.mxmedStore.currentEncounterKey = currentEncounterKey;
    }
    if(pane){
      if(currentEncounterKey){
        pane.dataset.encounterKey = currentEncounterKey;
        pane.dataset.activeEncounterKey = currentEncounterKey;
        pane.setAttribute('data-encounter-key', currentEncounterKey);
        pane.setAttribute('data-active-encounter-key', currentEncounterKey);
      }else{
        delete pane.dataset.encounterKey;
        delete pane.dataset.activeEncounterKey;
        pane.removeAttribute('data-encounter-key');
        pane.removeAttribute('data-active-encounter-key');
      }
    }
    if(p10Bar && p10Bar.dataset){
      p10Bar.dataset.encounterKey = currentEncounterKey || '';
    }
    window.mxmedStore.activeEncounterKey = currentEncounterKey || '';
    return currentEncounterKey;
  };
  const rebuildCompatibilityState = ()=>{
    const map = ensureActiveEncountersMap();
    const currentPatientId = clean(window.mxmedStore.currentPatientId || window.mxmedStore.activePatientId);
    const currentEncounterKey = clean(window.mxmedStore.currentEncounterKey);
    const entry = currentEncounterKey ? map[currentEncounterKey] : null;
    const isCurrentActive = !!(
      entry
      && clean(entry.patient_id) === currentPatientId
      && (clean(entry.status) === 'consulta_activa' || clean(entry.status) === 'consulta_pendiente_cierre')
    );
    const status = isCurrentActive ? clean(entry.status) : 'sin_consulta_activa';
    const compat = {
      patient_id: currentPatientId,
      encounter_key: isCurrentActive ? currentEncounterKey : '',
      status,
      started_at: isCurrentActive ? clean(entry.started_at) : '',
      last_activity_at: isCurrentActive ? clean(entry.last_activity_at || nowIso()) : nowIso(),
      origin: isCurrentActive ? clean(entry.origin) : 'multi_active_bridge',
      pending_reason: isCurrentActive ? clean(entry.pending_reason) : ''
    };
    window.mxmedStore.activeEncounterState = compat;
    return compat;
  };
  const setCurrentPatientContext = (patientId, opts = {})=>{
    const safePatientId = clean(patientId);
    window.mxmedStore.currentPatientId = safePatientId;
    const nextEncounter = pickEncounterForPatient(safePatientId);
    window.mxmedStore.currentEncounterKey = nextEncounter;
    if(opts.sync !== false){
      syncCurrentContextNodes();
      rebuildCompatibilityState();
    }
    return nextEncounter;
  };
  const upsertEncounterEntry = (detail, status)=>{
    const encounterKey = clean(detail.encounter_key);
    if(!encounterKey) return null;
    const map = ensureActiveEncountersMap();
    const prev = (map[encounterKey] && typeof map[encounterKey] === 'object') ? map[encounterKey] : {};
    const next = {
      encounter_key: encounterKey,
      patient_id: clean(detail.patient_id || prev.patient_id || window.mxmedStore.currentPatientId || window.mxmedStore.activePatientId),
      status: clean(status || prev.status || 'consulta_activa'),
      started_at: clean(detail.started_at || prev.started_at || nowIso()),
      last_activity_at: clean(detail.last_activity_at || nowIso()),
      origin: clean(detail.origin || prev.origin),
      pending_reason: clean(detail.pending_reason || (status === 'consulta_pendiente_cierre' ? 'user_finalize_request' : ''))
    };
    if(status === 'consulta_activa' || status === 'consulta_pendiente_cierre'){
      persistPatientLabelForEntry(next, detail);
    }
    map[encounterKey] = next;
    return next;
  };
  const removeEncounterEntry = (encounterKey)=>{
    const map = ensureActiveEncountersMap();
    const key = clean(encounterKey);
    if(!key) return;
    delete map[key];
  };
  const isEncounterActiveStatus = (status)=> status === 'consulta_activa' || status === 'consulta_pendiente_cierre';
  const fetchActiveEncounterKeyForPatient = async (patientId)=>{
    const pid = clean(patientId);
    if(!pid) return '';
    try{
      const resp = await fetch(`/api/clinical/index.php/patients/${encodeURIComponent(pid)}/encounters/active`, {
        method: 'GET',
        headers: { Accept: 'application/json' },
        credentials: 'same-origin'
      });
      const json = await resp.json().catch(()=> null);
      if(!json || json.ok !== true || !json.data || !json.data.encounter_key){
        return '';
      }
      return clean(json.data.encounter_key);
    }catch(_){
      return '';
    }
  };
  const reconcileActiveEncounters = async (opts = {})=>{
    const map = ensureActiveEncountersMap();
    const entries = Object.values(map).filter((entry)=> entry && typeof entry === 'object' && isEncounterActiveStatus(clean(entry.status)));
    const patientIds = Array.from(new Set(entries.map((entry)=> clean(entry.patient_id)).filter(Boolean)));
    if(!patientIds.length){
      syncCurrentContextNodes();
      const compat = rebuildCompatibilityState();
      if(opts.emitEvent !== false){
        try{
          window.dispatchEvent(new CustomEvent('mxmed:encounter-changed', {
            detail: {
              patient_id: clean(window.mxmedStore.currentPatientId || window.mxmedStore.activePatientId),
              encounter_key: clean(window.mxmedStore.currentEncounterKey)
            }
          }));
        }catch(_){}
      }
      return { removed: [], compat };
    }

    const resolved = await Promise.all(patientIds.map(async (pid)=> ({ pid, activeKey: await fetchActiveEncounterKeyForPatient(pid) })));
    const activeByPatient = {};
    resolved.forEach(({ pid, activeKey })=>{ activeByPatient[pid] = clean(activeKey); });

    const removed = [];
    Object.keys(map).forEach((encKey)=>{
      const entry = map[encKey];
      if(!entry || typeof entry !== 'object'){
        delete map[encKey];
        return;
      }
      const entryStatus = clean(entry.status);
      if(!isEncounterActiveStatus(entryStatus)){
        delete map[encKey];
        return;
      }
      const pid = clean(entry.patient_id);
      const activeKey = clean(activeByPatient[pid]);
      const entryKey = clean(entry.encounter_key || encKey);
      if(!pid || !activeKey || entryKey !== activeKey){
        removed.push(entryKey || encKey);
        delete map[encKey];
        return;
      }
      entry.status = 'consulta_activa';
      map[encKey] = entry;
    });

    const currentEncounterKey = clean(window.mxmedStore.currentEncounterKey);
    if(currentEncounterKey && !map[currentEncounterKey]){
      const currentPatientId = clean(window.mxmedStore.currentPatientId || window.mxmedStore.activePatientId);
      window.mxmedStore.currentEncounterKey = pickEncounterForPatient(currentPatientId);
    }

    syncCurrentContextNodes();
    const compat = rebuildCompatibilityState();
    if(opts.emitEvent !== false){
      try{
        window.dispatchEvent(new CustomEvent('mxmed:encounter-changed', {
          detail: {
            patient_id: clean(window.mxmedStore.currentPatientId || window.mxmedStore.activePatientId),
            encounter_key: clean(window.mxmedStore.currentEncounterKey)
          }
        }));
      }catch(_){}
    }
    return { removed, compat };
  };
  const hasAnyActiveEncounter = ()=>{
    const map = ensureActiveEncountersMap();
    return Object.values(map).some((entry)=>{
      if(!entry || typeof entry !== 'object') return false;
      const status = clean(entry.status);
      const encounterKey = clean(entry.encounter_key);
      return !!encounterKey && (status === 'consulta_activa' || status === 'consulta_pendiente_cierre');
    });
  };
  const canStartEncounterForPatient = (patientId, maxActive = 3)=>{
    const pid = clean(patientId);
    const map = ensureActiveEncountersMap();
    const activeEntries = Object.values(map).filter((entry)=>{
      if(!entry || typeof entry !== 'object') return false;
      const status = clean(entry.status);
      const encounterKey = clean(entry.encounter_key);
      return !!encounterKey && (status === 'consulta_activa' || status === 'consulta_pendiente_cierre');
    });
    const hasActiveForPatient = activeEntries.some((entry)=> clean(entry.patient_id) === pid);
    return {
      allowed: hasActiveForPatient || activeEntries.length < maxActive,
      activeCount: activeEntries.length,
      hasActiveForPatient
    };
  };

  const registerEncounterActivity = (activityType, payload = {})=>{
    const detail = (payload && typeof payload === 'object') ? payload : {};
    const map = ensureActiveEncountersMap();
    const encounterKey = clean(
      detail.encounterKey
      || detail.encounter_key
      || window.mxmedStore.currentEncounterKey
      || window.mxmedStore.activeEncounterKey
      || (typeof window.getActiveEncounterKey === 'function' ? window.getActiveEncounterKey() : '')
    );
    if(!encounterKey) return null;
    const entry = map[encounterKey];
    if(!entry || typeof entry !== 'object') return null;

    const lastActivityAt = nowIso();
    const activity = clean(activityType || detail.activityType || detail.activity_type || 'actividad_clinica');
    const patientId = clean(detail.patientId || detail.patient_id || entry.patient_id);
    const source = clean(detail.source || 'frontend');

    entry.last_activity_at = lastActivityAt;
    if(activity){
      entry.last_activity_type = activity;
    }
    if(patientId){
      entry.patient_id = patientId;
    }
    map[encounterKey] = entry;

    if(clean(window.mxmedStore.currentEncounterKey) === encounterKey){
      rebuildCompatibilityState();
    }

    const eventDetail = {
      encounterKey,
      patientId: patientId || clean(entry.patient_id),
      activityType: activity,
      lastActivityAt,
      source
    };
    try{
      window.dispatchEvent(new CustomEvent('mxmed:encounter-activity', { detail: eventDetail }));
    }catch(_){}
    return eventDetail;
  };

  const applyLifecycle = (detailRaw)=>{
    const detail = (detailRaw && typeof detailRaw === 'object') ? detailRaw : {};
    const requestedStatus = clean(detail.status);
    const status = allowedStatus.has(requestedStatus) ? requestedStatus : 'sin_consulta_activa';
    const patientId = clean(detail.patient_id || window.mxmedStore.currentPatientId || window.mxmedStore.activePatientId || detectExistingPatientId());
    const encounterKey = clean(detail.encounter_key);

    const shouldSyncCurrentPatient =
      !!patientId &&
      (
        status === 'consulta_activa'
        || status === 'consulta_pendiente_cierre'
        || !clean(window.mxmedStore.currentPatientId)
      );
    if(shouldSyncCurrentPatient){
      window.mxmedStore.currentPatientId = patientId;
      window.mxmedStore.activePatientId = patientId;
    }

    if(status === 'consulta_activa' || status === 'consulta_pendiente_cierre'){
      const entry = upsertEncounterEntry(detail, status);
      if(entry && clean(entry.patient_id) === clean(window.mxmedStore.currentPatientId)){
        window.mxmedStore.currentEncounterKey = clean(entry.encounter_key);
      }else if(!clean(window.mxmedStore.currentEncounterKey) && entry){
        window.mxmedStore.currentEncounterKey = clean(entry.encounter_key);
      }
    } else if(status === 'consulta_cerrada'){
      const targetKey = encounterKey;
      if(!targetKey){
        syncCurrentContextNodes();
        return rebuildCompatibilityState();
      }
      const map = ensureActiveEncountersMap();
      const closingEntry = (map[targetKey] && typeof map[targetKey] === 'object') ? map[targetKey] : null;
      const closingPatientId = clean(closingEntry?.patient_id || patientId);
      removeEncounterEntry(targetKey);
      if(clean(window.mxmedStore.currentEncounterKey) === clean(targetKey)){
        window.mxmedStore.currentEncounterKey = pickEncounterForPatient(closingPatientId);
      }
    } else if(status === 'sin_consulta_activa'){
      if(encounterKey){
        removeEncounterEntry(encounterKey);
        if(clean(window.mxmedStore.currentEncounterKey) === encounterKey){
          window.mxmedStore.currentEncounterKey = pickEncounterForPatient(patientId || window.mxmedStore.currentPatientId);
        }
      } else if(!clean(window.mxmedStore.currentEncounterKey)){
        window.mxmedStore.currentEncounterKey = pickEncounterForPatient(window.mxmedStore.currentPatientId);
      }
    }

    syncCurrentContextNodes();
    let compat = rebuildCompatibilityState();
    const noCurrentEncounter = !clean(window.mxmedStore.currentEncounterKey);
    if(noCurrentEncounter && !hasAnyActiveEncounter()){
      window.mxmedStore.currentPatientId = '';
      window.mxmedStore.activePatientId = '';
      window.mxmedStore.currentEncounterKey = '';
      window.mxmedStore.activeEncounterKey = '';
      window.mxmedActivePatientId = '';
      window.__MXMED_ACTIVE_PATIENT_ID = '';
      const pane = findExpedientePane();
      if(pane){
        delete pane.dataset.patientId;
        delete pane.dataset.activePatientId;
        pane.removeAttribute('data-patient-id');
        pane.removeAttribute('data-active-patient-id');
      }
      syncCurrentContextNodes();
      compat = rebuildCompatibilityState();
      try{
        window.dispatchEvent(new CustomEvent('mxmed:expediente-neutralize', {
          detail: { reason: 'no_active_encounters' }
        }));
      }catch(_){}
    }
    return compat;
  };

  window.mxmedEmitEncounterLifecycle = function(detail){
    try{
      window.dispatchEvent(new CustomEvent('mxmed:encounter-lifecycle', {
        detail: (detail && typeof detail === 'object') ? detail : {}
      }));
    }catch(_){}
  };
  window.mxmedReconcileActiveEncounters = reconcileActiveEncounters;
  window.mxRegisterEncounterActivity = registerEncounterActivity;
  window.mxmedRegisterEncounterActivity = registerEncounterActivity;
  window.mxmedResolveCurrentEncounterForPatient = pickEncounterForPatient;
  window.mxmedGetOperationalEncounterKeyForPatient = pickEncounterForPatient;
  window.mxmedIsOperationalEncounterForPatient = (patientId, encounterKey = '')=>{
    const pid = clean(patientId);
    if(!pid) return false;
    const key = clean(encounterKey);
    const resolved = key || pickEncounterForPatient(pid);
    if(!resolved) return false;
    const map = ensureActiveEncountersMap();
    const entry = map[resolved];
    if(!entry || typeof entry !== 'object') return false;
    return clean(entry.patient_id) === pid && isEncounterActiveStatus(clean(entry.status));
  };
  window.mxmedSetCurrentPatientContext = setCurrentPatientContext;
  window.mxmedCanStartEncounter = canStartEncounterForPatient;

  window.addEventListener('mxmed:encounter-lifecycle', (ev)=>{
    const detail = (ev && ev.detail && typeof ev.detail === 'object') ? ev.detail : {};
    applyLifecycle(detail);
    const status = clean(detail.status);
    if(status === 'consulta_cerrada' || status === 'sin_consulta_activa'){
      Promise.resolve(reconcileActiveEncounters({
        emitEvent: true,
        reason: `lifecycle_${status}`
      })).catch(()=> null);
    }
  });

  const bridgeLegacyActive = (ev)=>{
    const detail = (ev && ev.detail && typeof ev.detail === 'object') ? ev.detail : {};
    const encounterKey = clean(detail.encounter_key);
    if(!encounterKey) return;
    applyLifecycle({
      patient_id: clean(detail.patient_id || window.mxmedStore.currentPatientId || window.mxmedStore.activePatientId),
      encounter_key: encounterKey,
      status: 'consulta_activa',
      origin: 'legacy_event_bridge',
      last_activity_at: nowIso()
    });
  };
  window.addEventListener('encounter:active', bridgeLegacyActive);
  window.addEventListener('mxmed:encounter-changed', bridgeLegacyActive);

  // Bootstrap: hydrate multi-active structures from existing single-key context.
  ensureActiveEncountersMap();
  const bootPatientId = detectExistingPatientId();
  const bootEncounterKey = detectExistingEncounterKey();
  // No promover encounter activo en bootstrap desde datasets/store residuales.
  // El estado operativo debe entrar por acciones explícitas (lifecycle).
  if(bootPatientId){
    window.mxmedStore.currentPatientId = bootPatientId;
    window.mxmedStore.activePatientId = bootPatientId;
  }
  if(bootEncounterKey){
    window.mxmedStore.currentEncounterKey = bootEncounterKey;
  }else{
    window.mxmedStore.currentEncounterKey = pickEncounterForPatient(window.mxmedStore.currentPatientId);
  }
  syncCurrentContextNodes();
  rebuildCompatibilityState();
})();

// Clinical API fetch auth header shim
(function(){
  if(window.__mxmedClinicalFetchAuthWrapped) return;
  window.__mxmedClinicalFetchAuthWrapped = true;

  const nativeFetch = (typeof window.fetch === 'function') ? window.fetch.bind(window) : null;
  if(!nativeFetch) return;

  const isDemoEnv = ()=>{
    const doctorId = String(
      document.body?.dataset?.doctorId ||
      window.mxmedStore?.doctorId ||
      window.mxmedStore?.doctor_id ||
      window.mxmedDoctor?.doctor_id ||
      ''
    ).trim();
    return doctorId === 'd_demo_01';
  };

  const resolveClinicalUserId = ()=>{
    const fromStore = String(window.mxmedStore?.user_id || '').trim();
    if(fromStore) return fromStore;
    const fromBody = String(document.body?.dataset?.userId || '').trim();
    if(fromBody) return fromBody;
    if(isDemoEnv()) return 'u_demo_01';
    return '';
  };

  const isClinicalUrl = (urlLike)=>{
    const raw = String(urlLike || '').trim();
    if(!raw) return false;
    return raw.indexOf('/api/clinical/index.php') !== -1;
  };

  const hasUserHeader = (headersLike)=>{
    const headers = new Headers(headersLike || undefined);
    return headers.has('X-User-Id') || headers.has('x-user-id');
  };

  window.fetch = function(input, init){
    try{
      const url = input instanceof Request ? input.url : String(input || '');
      if(!isClinicalUrl(url)){
        return nativeFetch(input, init);
      }

      if(input instanceof Request){
        const req = new Request(input, init || {});
        if(hasUserHeader(req.headers)){
          return nativeFetch(req);
        }
        const userId = resolveClinicalUserId();
        if(!userId){
          return nativeFetch(req);
        }
        const reqHeaders = new Headers(req.headers);
        reqHeaders.set('X-User-Id', userId);
        return nativeFetch(new Request(req, { headers: reqHeaders }));
      }

      const initHeaders = new Headers((init && init.headers) ? init.headers : undefined);
      if(hasUserHeader(initHeaders)){
        return nativeFetch(input, init);
      }
      const userId = resolveClinicalUserId();
      if(!userId){
        return nativeFetch(input, init);
      }
      initHeaders.set('X-User-Id', userId);
      return nativeFetch(input, Object.assign({}, init || {}, { headers: initHeaders }));
    }catch(_){
      return nativeFetch(input, init);
    }
  };

  const demoUserId = isDemoEnv() ? resolveClinicalUserId() : '';
  if(demoUserId){
    console.info('Clinical fetch auth header enabled', demoUserId);
  }else{
    console.info('Clinical fetch auth header enabled');
  }
})();

// ====== Consultorio: horarios, foto preview, mapa (fallback) ======

(function(){

  const body = document.getElementById('sched-body');

  if(body){

    const dias = [

      {k:'mon', lbl:'Lunes'}, {k:'tue', lbl:'Martes'}, {k:'wed', lbl:'Mi?rcoles'},

      {k:'thu', lbl:'Jueves'}, {k:'fri', lbl:'Viernes'}, {k:'sat', lbl:'S?bado'}, {k:'sun', lbl:'Domingo'}

    ];

    const key = 'mxmed_cons_schedules';

    const scrollKey = 'mxmed_scroll_sched';

    function load(){ try { return JSON.parse(localStorage.getItem(key)||'{}'); } catch(e){ return {}; } }

    function save(v){ localStorage.setItem(key, JSON.stringify(v)); }

    const state = load();

    const markScroll = ()=>{ try{ localStorage.setItem(scrollKey,'1'); }catch(_){ } };

    const scrollAfterReload = ()=>{

      try{

        if(localStorage.getItem(scrollKey)){

          localStorage.removeItem(scrollKey);

          setTimeout(()=> document.querySelector('.sched-card')?.scrollIntoView({behavior:'smooth', block:'start'}), 200);

        }

      }catch(_){ }

    };

    scrollAfterReload();

    try{ window.mxMarkHorarioScroll = markScroll; }catch(_){ }

    const defaultTimes = { a1:'09:00', b1:'14:00', a2:'16:00', b2:'20:00' };

    function rowDefined(act, inputs){

      if(act?.checked) return true;

      return inputs.some(inp=> (inp.value||'').trim().length>0);

    }

    function hookDefault(input, key){

      if(!input) return;

      const def = defaultTimes[key];

      if(!def) return;

      try{ input.setAttribute('placeholder', def); }catch(_){}

      input.addEventListener('focus', ()=>{

        if(!(input.value||'').trim()){

          input.value = def;

          try{

            input.dispatchEvent(new Event('input'));

            input.dispatchEvent(new Event('change'));

          }catch(_){ }

        }

      });

    }



    dias.forEach(d=>{

      const tr = document.createElement('tr');

      tr.innerHTML = `<td>${d.lbl}</td>

      <td><input type="checkbox" class="form-check-input" id="sch-act-${d.k}"></td>

      <td>

        <div class="d-flex align-items-center gap-1">

          <input type="time" class="form-control form-control-sm" id="sch-a1-${d.k}">

          <span>-</span>

          <input type="time" class="form-control form-control-sm" id="sch-b1-${d.k}">

        </div>

      </td>

      <td>

        <div class="d-flex align-items-center gap-1">

          <input type="time" class="form-control form-control-sm" id="sch-a2-${d.k}">

          <span>-</span>

          <input type="time" class="form-control form-control-sm" id="sch-b2-${d.k}">

        </div>

      </td>`;

      body.appendChild(tr);

      const act = tr.querySelector(`#sch-act-${d.k}`);

      const a1 = tr.querySelector(`#sch-a1-${d.k}`);

      const b1 = tr.querySelector(`#sch-b1-${d.k}`);

      const a2 = tr.querySelector(`#sch-a2-${d.k}`);

      const b2 = tr.querySelector(`#sch-b2-${d.k}`);

      const inputs = [a1,b1,a2,b2];

      const sv = state[d.k] || {};

      hookDefault(a1,'a1'); hookDefault(b1,'b1'); hookDefault(a2,'a2'); hookDefault(b2,'b2');

      act.checked = !!sv.act;

      a1.value = sv.a1 || '';

      b1.value = sv.b1 || '';

      a2.value = sv.a2 || '';

      b2.value = sv.b2 || '';

      const mark = ()=> tr.classList.toggle('sched-defined', rowDefined(act, inputs));

      const fillDefaults = ()=>{

        inputs.forEach((inp, idx)=>{

          if(!inp) return;

          const slot = ['a1','b1','a2','b2'][idx];

          const def = defaultTimes[slot];

          if(def && !(inp.value||'').trim()){

            inp.value = def;

            try{

              inp.dispatchEvent(new Event('input'));

              inp.dispatchEvent(new Event('change'));

            }catch(_){ }

          }

        });

      };

      const clearInputs = ()=>{

        inputs.forEach(inp=>{

          if(!inp) return;

          if((inp.value||'').trim()){

            inp.value = '';

            try{

              inp.dispatchEvent(new Event('input'));

              inp.dispatchEvent(new Event('change'));

            }catch(_){ }

          }

        });

      };

      function sync(){

        state[d.k] = { act:act.checked, a1:a1.value, b1:b1.value, a2:a2.value, b2:b2.value };

        save(state);

        mark();

      }

      act.addEventListener('change', ()=>{

        if(act.checked){

          fillDefaults();

        }else{

          clearInputs();

        }

        sync();

      });

      inputs.forEach(inp=>{

        inp.addEventListener('change', sync);

        inp.addEventListener('input', ()=>{

          if((inp.value||'').trim().length && !act.checked){

            act.checked = true;

          }

          sync();

        });

      });

      if(rowDefined(act, inputs) && !act.checked){

        act.checked = true;

        sync();

      }else{

        mark();

      }

    });

    document.getElementById('sched-copy-mon')?.addEventListener('click', ()=>{

      const m = state.mon || {};

      ['tue','wed','thu','fri'].forEach(k=>{ state[k] = {...m}; });

      save(state); markScroll(); location.reload();

    });

    document.getElementById('sched-clear')?.addEventListener('click', ()=>{ localStorage.removeItem(key); markScroll(); location.reload(); });

  }



  const file = document.getElementById('cons-foto');

  if(file){

    file.addEventListener('change', (e)=>{

      const f = e.target.files && e.target.files[0]; if(!f) return;

      const rd = new FileReader(); rd.onload = ev => {

        const img = document.getElementById('cons-foto-img'); const box = document.getElementById('cons-foto-prev');

        if(img && box){

          img.src = ev.target.result;

          box.style.display='block';

          box.removeAttribute('hidden');

          toggleFotoPrincipalMsg(true);

        }

      }; rd.readAsDataURL(f);

    });

  }


  // Utilidad: construir texto de direcci?n

  function buildAddress(){

    const cp = (document.getElementById('cp')?.value||'').trim();

    const col = (document.getElementById('colonia')?.value||'').trim();

    const mun = (document.getElementById('municipio')?.value||'').trim();

    const edo = (document.getElementById('estado')?.value||'').trim();

    const calle = (document.getElementById('cons-calle')?.value||'').trim();

    const num = (document.getElementById('cons-numext')?.value||'').trim();

    return [calle && (calle + (num? ' ' + num : '')), col, cp, mun, edo, 'M\u00E9xico'].filter(Boolean).join(', ');

  }



(function initMap(){
    if(!(window.L && typeof L.map === 'function')) return; // si no hay Leaflet, usamos iframe fallback m?s abajo

    // Configs para ambos panes

    const panes = [

      { mapId:'cons-map', latId:'cons-lat', lngId:'cons-lng', cp:'cp', col:'colonia', calle:'cons-calle', num:'cons-numext' },

      { mapId:'cons-map2', latId:'cons-lat2', lngId:'cons-lng2', cp:'cp2', col:'colonia2', calle:'cons-calle2', num:'cons-numext2' },

    ];

    const debounce = (fn, ms)=>{ let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn.apply(null,a), ms); } };

    panes.forEach(cfg=>{

      const mapBox = document.getElementById(cfg.mapId);

      if(!mapBox) return;

      try{

        const map = L.map(mapBox).setView([21.882, -102.296], 13);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);

        const marker = L.marker([21.882, -102.296], { draggable:true }).addTo(map);

        const latI = document.getElementById(cfg.latId); const lngI = document.getElementById(cfg.lngId);

        const setLL = (latlng)=>{ if(latI) latI.value = latlng.lat.toFixed(6); if(lngI) lngI.value = latlng.lng.toFixed(6); };

        setLL(marker.getLatLng());

        marker.on('moveend', (e)=> setLL(e.target.getLatLng()));

        map.on('click', (e)=>{ marker.setLatLng(e.latlng); setLL(e.latlng); });



        async function geocode(){

          const cp = (document.getElementById(cfg.cp)?.value||'').trim();

          const col = (document.getElementById(cfg.col)?.value||'').trim();

          const calle = (document.getElementById(cfg.calle)?.value||'').trim();

          const num = (document.getElementById(cfg.num)?.value||'').trim();

          const mun = (document.getElementById(cfg.col==='colonia'?'municipio':'municipio2')?.value||'').trim();

          const edo = (document.getElementById(cfg.col==='colonia'?'estado':'estado2')?.value||'').trim();

          const q = [calle && (calle + (num? ' ' + num : '')), col, cp, mun, edo, 'M\u00E9xico'].filter(Boolean).join(', ');

          if(!q) return;

          try{

            const r = await fetch('./geocode-proxy.php?q='+encodeURIComponent(q));

            if(!r.ok) throw new Error('HTTP '+r.status);

            const json = await r.json();

            const item = Array.isArray(json) ? json[0] : null;

            if(item && item.lat && item.lon){

              const latlng = { lat: parseFloat(item.lat), lng: parseFloat(item.lon) };

              map.setView(latlng, 17); marker.setLatLng(latlng); setLL(latlng);

            }

          }catch(_){ /* silencioso */ }

        }

        const tryGeo = debounce(()=>{

          const cp = (document.getElementById(cfg.cp)?.value||'').trim();

          const col = (document.getElementById(cfg.col)?.value||'').trim();

          const calle = (document.getElementById(cfg.calle)?.value||'').trim();

          const num = (document.getElementById(cfg.num)?.value||'').trim();

          if(/^\d{5}$/.test(cp) && col && calle && num){ geocode(); }

        }, 500);

        [cfg.cp,cfg.col,cfg.calle,cfg.num].forEach(id=>{

          const el = document.getElementById(id);

          if(!el) return;

          el.addEventListener('change', tryGeo);

          el.addEventListener('input', tryGeo);

        });

      }catch(_){ /* si falla Leaflet en este pane, el iframe fallback lo cubrir? */ }

    });

  })();



  // Fallback autom?tico: actualizar iframe de Google Maps con la direcci?n

  (function autoFrameUpdate(){

    const frame = document.getElementById('cons-map-frame');

    if(!frame) return;

    const debounce = (fn, ms)=>{ let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn.apply(null,a), ms); } };

    const update = debounce(()=>{

      const addr = buildAddress();

      if(!addr) return;

      const url = 'https://www.google.com/maps?q='+encodeURIComponent(addr)+'&z=17&output=embed';

      if(frame.src !== url){ frame.src = url; }

    }, 600);

    ['cp','colonia','cons-calle','cons-numext'].forEach(id=>{

      const el = document.getElementById(id); if(!el) return;

      el.addEventListener('input', update); el.addEventListener('change', update);

    });

  })();



  // Helper opcional: construir horarios en un contenedor clonado (pane2)

  (function(){

    if(window._mx_setupSchedulesFor) return;

    window._mx_setupSchedulesFor = function(container, keySuffix){

      const body = container.querySelector('#sched-body-2');

      if(!body) return;

      body.innerHTML='';

      const dias=[{k:'mon',lbl:'Lunes'},{k:'tue',lbl:'Martes'},{k:'wed',lbl:'Mi?rcoles'},{k:'thu',lbl:'Jueves'},{k:'fri',lbl:'Viernes'},{k:'sat',lbl:'Sobado'},{k:'sun',lbl:'Domingo'}];

      const key='mxmed_cons_schedules'+(keySuffix||'');

      const load=()=>{ try{return JSON.parse(localStorage.getItem(key)||'{}');}catch(e){return{}} };

      const save=v=>localStorage.setItem(key,JSON.stringify(v));

      const state=load();

      const rowDefined = (act, inputs)=> act?.checked || inputs.some(inp=> (inp.value||'').trim());

      dias.forEach(d=>{

        const tr=document.createElement('tr');

        tr.innerHTML=`<td>${d.lbl}</td><td><input type="checkbox" class="form-check-input" id="sch-act-${d.k}${keySuffix||''}"></td><td><div class="d-flex align-items-center gap-1"><input type="time" class="form-control form-control-sm" id="sch-a1-${d.k}${keySuffix||''}"><span>a</span><input type="time" class="form-control form-control-sm" id="sch-b1-${d.k}${keySuffix||''}"></div></td><td><div class="d-flex align-items-center gap-1"><input type="time" class="form-control form-control-sm" id="sch-a2-${d.k}${keySuffix||''}"><span>a</span><input type="time" class="form-control form-control-sm" id="sch-b2-${d.k}${keySuffix||''}"></div></td>`;

        body.appendChild(tr);

        const act=tr.querySelector(`#sch-act-${d.k}${keySuffix||''}`);

        const a1=tr.querySelector(`#sch-a1-${d.k}${keySuffix||''}`);

        const b1=tr.querySelector(`#sch-b1-${d.k}${keySuffix||''}`);

        const a2=tr.querySelector(`#sch-a2-${d.k}${keySuffix||''}`);

        const b2=tr.querySelector(`#sch-b2-${d.k}${keySuffix||''}`);

        const inputs=[a1,b1,a2,b2];

        const sv=state[d.k]||{};

        act.checked=!!sv.act;

        a1.value=sv.a1||'09:00';

        b1.value=sv.b1||'14:00';

        a2.value=sv.a2||'16:00';

        b2.value=sv.b2||'20:00';

        const sync=()=>{

          state[d.k]={act:act.checked,a1:a1.value,b1:b1.value,a2:a2.value,b2:b2.value};

          save(state);

          tr.classList.toggle('sched-defined', rowDefined(act, inputs));

        };

        act.addEventListener('change', sync);

        inputs.forEach(el=>{

          el.addEventListener('change', sync);

          el.addEventListener('input', ()=>{

            if((el.value||'').trim() && !act.checked){

              act.checked = true;

            }

            sync();

          });

        });

        if(rowDefined(act, inputs) && !act.checked){

          act.checked = true;

        }

        sync();

      });

      const copyBtn = container.querySelector('#sched-copy-mon-2');

      const clearBtn= container.querySelector('#sched-clear-2');

      copyBtn?.addEventListener('click', ()=>{ const st=load(); const m=st.mon||{}; ['tue','wed','thu','fri'].forEach(k=>{ st[k]={...m}; }); save(st); try{ window.mxMarkHorarioScroll?.(); }catch(_){ } location.reload(); });

      clearBtn?.addEventListener('click', ()=>{ localStorage.removeItem(key); (window.mxMarkHorarioScroll||markScroll)(); location.reload(); });

    };

  })();



  // Auto abrir colonias al tabular desde CP y permitir selecci?n con flechas

  (function setupColoniaAutoOpen(){

    const cp = document.getElementById('cp');

    const sel = document.getElementById('colonia');

    if(!cp || !sel) return;



    function openSelectList(){

      const total = sel.options ? sel.options.length : 0;

      if(total > 1 && !sel.disabled){

        const n = Math.min(Math.max(6, total), 10); // entre 6 y 10 visibles

        sel.setAttribute('size', n);

        sel.classList.add('select-open');

      }

    }

    function closeSelectList(){ sel.removeAttribute('size'); sel.classList.remove('select-open'); }

    function isOpen(){ return sel.hasAttribute('size'); }



    // Al tabular desde CP, forzar foco en "Colonia" en blur para ganar a la navegaci?n natural

    let cpTabbing = false;

    cp.addEventListener('keydown', (e)=>{ if(e.key === 'Tab' && !e.shiftKey){ cpTabbing = true; } });

    cp.addEventListener('keyup', ()=>{ cpTabbing = false; });

    cp.addEventListener('blur', ()=>{

      if(!cpTabbing) return;

      cpTabbing = false;

      const pollMs = 100; let waited = 0;

      // Redirigir foco y abrir lista cuando existan opciones

      const poll = ()=>{

        sel.focus();

        if((sel.options?.length||0) > 1 && !sel.disabled){ openSelectList(); return; }

        waited += pollMs; if(waited >= 1500) return; // 1.5s m?x

        setTimeout(poll, pollMs);

      };

      setTimeout(poll, 0);

    });

    // Navegaci?n con flechas sin desplazar la p?gina y cierre con Enter/Escape

    sel.addEventListener('keydown', (e)=>{

      if(document.activeElement !== sel || !isOpen()) return;

      const total = sel.options?.length || 0; if(total === 0) return;

      let i = sel.selectedIndex < 0 ? 0 : sel.selectedIndex;

      switch(e.key){

        case 'ArrowDown': e.preventDefault(); sel.selectedIndex = Math.min(total-1, i+1); break;

        case 'ArrowUp': e.preventDefault(); sel.selectedIndex = Math.max(0, i-1); break;

        case 'PageDown': e.preventDefault(); sel.selectedIndex = Math.min(total-1, i+5); break;

        case 'PageUp': e.preventDefault(); sel.selectedIndex = Math.max(0, i-5); break;

        case 'Home': e.preventDefault(); sel.selectedIndex = 0; break;

        case 'End': e.preventDefault(); sel.selectedIndex = total-1; break;

        case 'Enter':

          e.preventDefault();

          closeSelectList();

          sel.dispatchEvent(new Event('change'));

          document.getElementById('cons-calle')?.focus();

          break;

        case 'Escape': e.preventDefault(); closeSelectList(); break;

      }

    });

    // Cerrar al perder foco

    sel.addEventListener('blur', closeSelectList);

    // Al cambiar colonia, pasar a Calle

    sel.addEventListener('change', ()=>{ document.getElementById('cons-calle')?.focus(); });

  })();



  // Grupo Medico: habilitar/deshabilitar campo segun radios
  (function setupGrupoMedico(){
    const rSi = document.getElementById('cons-grupo-si');
    const rNo = document.getElementById('cons-grupo-no');
    const grp = document.getElementById('cons-grupo-nombre');
    if(!rSi || !rNo || !grp) return;
    const sync = ()=>{
      if(rSi.checked){
        grp.removeAttribute('disabled');
        grp.focus();
      }else{
        grp.setAttribute('disabled','disabled');
      }
    };
    rSi.addEventListener('change', sync);
    rNo.addEventListener('change', sync);
    sync();
  })();



  // Validaci?n de tel?fonos (MX/E.164): 10 d?gitos nacionales o +52 + 10 d?gitos

  (function setupPhoneValidation(){

    function analyzePhone(val, isLive){

      const s = (val||'').trim();

      if(s === '') return { ok:true };

      // Solo caracteres permitidos durante edici?n

      if(/[^0-9()+\-\s+]/.test(s)) return { ok:false, reason:'invalid_char' };

      // '+' solo al inicio y m?ximo 1

      if((s.match(/\+/g)||[]).length > 1 || (s.includes('+') && !s.startsWith('+'))) return { ok:false, reason:'invalid_char' };

      const digits = s.replace(/\D/g,'');

      // Si empieza con +52, objetivo 12 d?gitos (52 + 10 nacionales)

      const hasPlus52 = s.startsWith('+') && digits.startsWith('52');

      const target = hasPlus52 ? 12 : 10;

      if(isLive){

        if(digits.length > target) return { ok:false, reason:'too_long' };

        if(/[^0-9()+\-\s]/.test(s)) return { ok:false, reason:'invalid_char' };

        // Mientras escribe, no marcar corto a?n

        return { ok:true };

      } else {

        if(digits.length !== target) return { ok:false, reason: digits.length < target ? 'too_short' : 'too_long' };

        return { ok:true };

      }

    }

    function messageFor(reason){

      switch(reason){

        case 'invalid_char': return 'Solo números y + ( ) -';

        case 'too_short': return 'Número incompleto (se requieren 10 dígitos)';

        case 'too_long': return 'Demasiados dígitos (máximo 10 o +52 + 10)';

        default: return 'Teléfono inválido';

      }

    }

    function applyState(el, isLive){

      const res = analyzePhone(el.value, !!isLive);

      const wrap = el.closest('.save-wrap');

      const b = wrap?.querySelector('.err-bubble');

      if(res.ok){

        if(wrap){ wrap.classList.remove('has-error'); if(b) b.style.opacity='0'; }

        else { el.classList.remove('is-invalid'); }

        el.setCustomValidity('');

      }else{

        const msg = messageFor(res.reason);

        if(b) b.textContent = msg;

        if(wrap){ wrap.classList.add('has-error'); if(b) b.style.opacity = '1'; }

        else { el.classList.add('is-invalid'); }

        el.setCustomValidity('Teléfono inválido');

      }

    }

    // Reglas adicionales en vivo: tope de 3 letras y tope de dígitos

    const _state = new WeakMap(); // { value, letters, digits }



    function countLetters(s){

      const m = (s||'').match(/[A-Za-zÁÉÍÓÚÜÑáéíóúüñ]/g);

      return m ? m.length : 0;

    }



    function digitsTargetFor(val){

      const s = (val||'').trim();

      const digits = s.replace(/\D/g,'');

      const hasPlus52 = s.startsWith('+') && digits.startsWith('52');

      return hasPlus52 ? 12 : 10;

    }



    function onLiveInput(el){

      const prev = _state.get(el) || { value: '', letters: 0, digits: 0 };

      const wrap = el.closest('.save-wrap');

      const b = wrap?.querySelector('.err-bubble');



      const val = el.value || '';

      const letters = countLetters(val);

      const digits = (val.match(/\d/g)||[]).length;

      const target = digitsTargetFor(val);



      // 1) Aviso cuando llega a 3 letras y bloqueo a partir de la 4?

      if(letters >= 3){

        if(b) b.textContent = 'Ingresa solo n?meros';

        if(wrap){ wrap.classList.add('has-error'); if(b) b.style.opacity = '1'; }

        // Si intenta exceder 3 letras, revertir a valor previo

        if(letters > 3){

          el.value = prev.value || '';

          try{ el.setSelectionRange(el.value.length, el.value.length); }catch(_){ }

          _state.set(el, { value: el.value, letters: countLetters(el.value), digits: (el.value.match(/\d/g)||[]).length });

          return; // no continuar, ya mostramos burbuja y revertimos

        }

      }



      // 2) Limitar cantidad de d?gitos en vivo (10 o +52+10)

      if(digits > target){

        if(b) b.textContent = (target === 12 ? 'Demasiados d?gitos (m?ximo +52 + 10)' : 'Demasiados d?gitos (m?ximo 10)');

        if(wrap){ wrap.classList.add('has-error'); if(b) b.style.opacity = '1'; }

        el.value = prev.value || '';

        try{ el.setSelectionRange(el.value.length, el.value.length); }catch(_){ }

        _state.set(el, { value: el.value, letters: countLetters(el.value), digits: (el.value.match(/\d/g)||[]).length });

        return;

      }



      // 3) Si comienza a escribir n?meros, ocultar burbuja de letras

      if(letters < 3){

        if(wrap){ wrap.classList.remove('has-error'); if(b) b.style.opacity = '0'; }

      }



      // 4) Aplicar validaci?n est?ndar en vivo (caracteres permitidos y overflow)

      applyState(el, true);



      // 5) Guardar estado actual

      _state.set(el, { value: el.value, letters, digits });

    }



    // Exponer para panes clonados

    window._mx_phone_bind = function(container){

      const scope = container || document;

      const all = Array.from(scope.querySelectorAll('[data-validate="phone"], input[type="tel"]'));

      all.forEach(el=>{

        el.addEventListener('input', ()=>onLiveInput(el));

        el.addEventListener('blur', ()=>applyState(el, false));

        // Estado inicial

        _state.set(el, { value: el.value||'', letters: countLetters(el.value), digits: (el.value||'').replace(/\D/g,'').length });

        applyState(el, true);

      });

    };

    window._mx_phone_bind(document);

  })();



  // WhatsApp consultorio: sincronizar con Datos Generales si se marca la casilla

  (function setupWhatsAppSync(){

    const wa = document.getElementById('cons-wa');

    const syncCb = document.getElementById('cons-wa-sync');

    const dg = document.getElementById('dp-whatsapp');

    if(!wa || !syncCb) return;

    function fillFromDG(){ if(dg){ wa.value = dg.value || ''; wa.dispatchEvent(new Event('input')); } }

    function toggle(){

      if(syncCb.checked){

        wa.setAttribute('disabled','disabled');

        wa.placeholder = '+52 ...';

        fillFromDG();

      } else {

        wa.removeAttribute('disabled');

        wa.value = '';

        wa.placeholder = 'otro numero Whatsapp';

        wa.focus();

      }

    }

    syncCb.addEventListener('change', toggle);

    if(dg){ dg.addEventListener('input', ()=>{ if(syncCb.checked) fillFromDG(); }); }

    // inicial

    toggle();

  })();



  // Ocultar campos antiguos del consultorio para evitar duplicados
  (function hideLegacyFields(){
    const root = document.querySelector("#sede1"); if(!root) return;
    const labels = ["Nombre de la sede","Teléfono (planes de pago)","Dirección","Horario","Notas"];
    labels.forEach(txt=>{
      const el = Array.from(root.querySelectorAll("label.form-label")).find(l=> (l.textContent||"").trim().indexOf(txt)===0);
      if(el){ const wrap = el.closest("[class*='col-']"); if(wrap) wrap.style.display='none'; }
    });
  })();
})();

(() => {
  const section = document.querySelector('[data-exp-section="systems"]');
  if (!section || section.dataset.expSystemsInitialized === '1') return;
  section.dataset.expSystemsInitialized = '1';

  const grid = section.querySelector('.exp-system-grid');
  const toggleOptionalBtn = section.querySelector('[data-exp-action="toggle-optional"]');
  const allNormalBtn = section.querySelector('[data-exp-action="all-normal"]');
  const fillNoExplBtn = section.querySelector('[data-exp-action="fill-no-explorado"]');
  const resumenInput = section.querySelector('#exp_resumen');
  const resumenFlag = section.querySelector('#exp_resumen_editado');
  const resumenResetBtn = section.querySelector('#exp_resumen_reset');
  const hallazgosTextarea = section.querySelector('#exp_hallazgos_relevantes');

  const systemEls = Array.from(section.querySelectorAll('.exp-system[data-system-key]'));
  let optionalSystems = [];

  const specialtyVisibilities = {
    medicina_general: null,
    pediatria: ['estado_general', 'respiratorio', 'cardiovascular', 'abdomen_gastrointestinal', 'piel_tegumentos', 'neurologico'],
    ginecologia_obstetricia: ['estado_general', 'cardiovascular', 'respiratorio', 'abdomen_gastrointestinal', 'genitourinario', 'piel_tegumentos'],
    cardiologia: ['estado_general', 'cardiovascular', 'respiratorio', 'piel_tegumentos']
  };

  const specialty = section.dataset.expSpecialty?.trim() || 'medicina_general';
  const state = {
    manualSummary: false,
    lastAutoSummary: '',
    optionalShown: false,
    bulkUpdate: false
  };

  const markNotesState = (systemEl) => {
    if (!systemEl) return;
    const notes = systemEl.querySelector('.exp-notes');
    if (!notes) return;
    const status = systemEl.querySelector('.exp-seg-input:checked')?.value || 'normal';
    notes.disabled = status !== 'anormal';
  };

  const getSystemTitle = (systemEl) => {
    return systemEl.querySelector('.exp-system-title')?.textContent.trim() || systemEl.dataset.systemKey || '';
  };

  const updateOptionalList = () => {
    optionalSystems = systemEls.filter(el => el.classList.contains('exp-system--optional'));
  };

  const setToggleText = () => {
    if (!toggleOptionalBtn) return;
    toggleOptionalBtn.textContent = state.optionalShown ? 'Ocultar sistemas' : 'Mostrar más sistemas';
  };

  const showOptional = () => {
    grid?.classList.add('exp-show-optional');
    state.optionalShown = true;
    setToggleText();
    updateSummary();
  };

  const hideOptional = () => {
    grid?.classList.remove('exp-show-optional');
    state.optionalShown = false;
    setToggleText();
    updateSummary();
  };

  const applySpecialtyVisibility = () => {
    const visibleKeys = specialtyVisibilities[specialty];
    updateOptionalList();
    if (specialty === 'medicina_general') {
      showOptional();
      return;
    }
    hideOptional();
    if (!visibleKeys) return;
    optionalSystems.forEach(systemEl => {
      const key = systemEl.dataset.systemKey;
      if (visibleKeys.includes(key)) {
        systemEl.classList.remove('exp-system--optional');
      }
    });
    updateOptionalList();
  };

  const isVisible = (systemEl) => {
    if (!systemEl) return false;
    if (!systemEl.classList.contains('exp-system--optional')) return true;
    return state.optionalShown;
  };

  const getSystemsVisible = () => systemEls.filter(isVisible);

  const computeSummaryText = () => {
    const visibleSystems = getSystemsVisible();
    const abnormal = visibleSystems.filter(el => el.querySelector('.exp-seg-input:checked')?.value === 'anormal');
    if (abnormal.length === 0) {
      return 'Exploración por sistemas sin alteraciones relevantes.';
    }
    const titles = abnormal.map(el => getSystemTitle(el)).filter(Boolean);
    const list = titles.slice(0, 3).join(', ');
    const suffix = titles.length > 3 ? ' y otros.' : '.';
    return `Exploración con hallazgos en: ${list}${suffix}`;
  };

  const updateSummary = (force = false) => {
    if (!resumenInput) return;
    const text = computeSummaryText();
    state.lastAutoSummary = text;
    if (!force && state.manualSummary) return;
    state.manualSummary = false;
    resumenInput.value = text;
    if (resumenFlag) resumenFlag.value = '0';
    if (resumenResetBtn) resumenResetBtn.disabled = true;
  };

  const updateSystemStatus = (systemEl, status) => {
    if (!systemEl) return;
    const input = systemEl.querySelector(`.exp-seg-input[value="${status}"]`);
    if (!input) return;
    if (input.checked) {
      markNotesState(systemEl);
      return;
    }
    input.checked = true;
    input.dispatchEvent(new Event('change', { bubbles: true }));
  };

  const handleSystemChange = (systemEl) => {
    if (!state.bulkUpdate) {
      systemEl.dataset.expTouched = '1';
    }
    markNotesState(systemEl);
    updateSummary();
  };

  const markVisibleNormal = () => {
    state.bulkUpdate = true;
    getSystemsVisible()
      .filter(el => el.dataset.expTouched !== '1')
      .forEach(el => updateSystemStatus(el, 'normal'));
    state.bulkUpdate = false;
  };

  const markHiddenOptionalNoExplored = () => {
    if (state.optionalShown) return;
    state.bulkUpdate = true;
    optionalSystems
      .filter(el => el.dataset.expTouched !== '1')
      .forEach(el => updateSystemStatus(el, 'no_explorado'));
    state.bulkUpdate = false;
  };

  const handleSummaryInput = () => {
    if (!resumenInput) return;
    state.manualSummary = true;
    if (resumenFlag) resumenFlag.value = '1';
    if (resumenResetBtn) resumenResetBtn.disabled = false;
  };

  const resetSummary = (event) => {
    event.preventDefault();
    state.manualSummary = false;
    if (resumenFlag) resumenFlag.value = '0';
    updateSummary(true);
  };

  const buildPayload = () => ({
    section: 'exploracion_por_sistemas',
    sistemas: systemEls.map(el => ({
      system_key: el.dataset.systemKey,
      status: el.querySelector('.exp-seg-input:checked')?.value || 'normal',
      notes: el.querySelector('.exp-notes')?.value.trim() || ''
    })),
    hallazgos_relevantes: hallazgosTextarea?.value.trim() || '',
    resumen_auto: state.lastAutoSummary,
    resumen_editado: resumenInput?.value.trim() || '',
    resumen_is_user_edited: state.manualSummary ? 1 : 0
  });

  window.mxExploracionPorSistemasPayload = buildPayload;

  applySpecialtyVisibility();
  systemEls.forEach(markNotesState);
  setToggleText();
  toggleOptionalBtn?.addEventListener('click', () => state.optionalShown ? hideOptional() : showOptional());
  allNormalBtn?.addEventListener('click', markVisibleNormal);
  fillNoExplBtn?.addEventListener('click', markHiddenOptionalNoExplored);
  resumenInput?.addEventListener('input', handleSummaryInput);
  resumenResetBtn?.addEventListener('click', resetSummary);
  section.addEventListener('change', (event) => {
    const input = event.target.closest('.exp-seg-input');
    if (!input) return;
    const systemEl = input.closest('.exp-system');
    if (!systemEl) return;
    handleSystemChange(systemEl);
  });
  updateSummary(true);
})();

// ====== Nota de evolución (NOM-004) ======
(function initNotaEvolucion(){
  const root = document.querySelector('[data-ne-section="nota_evolucion"]');
  if (!root || root.dataset.neInitialized === '1') return;
  root.dataset.neInitialized = '1';

  const els = {
    ambito: root.querySelector('#ne_ambito'),
    refresh: root.querySelector('#ne_refresh'),
    complemento: root.querySelector('#ne_complemento'),
    evolucion: root.querySelector('#ne_evolucion'),
    motivoRO: root.querySelector('#ne_motivo_ro'),
    padecimientoRO: root.querySelector('#ne_padecimiento_ro'),
    vitalsRO: root.querySelector('#ne_vitals_ro'),
    exploracionRO: root.querySelector('#ne_exploracion_ro'),
    studiesRO: root.querySelector('#ne_studies_ro'),
    interp: root.querySelector('#ne_interp'),
    dx: root.querySelector('#ne_dx'),
    pronostico: root.querySelector('#ne_pronostico'),
    pronosticoTxt: root.querySelector('#ne_pronostico_txt'),
    plan: root.querySelector('#ne_plan'),
    generate: root.querySelector('#ne_generate'),
    errors: root.querySelector('#ne_errors'),
    timeline: root.querySelector('#ne_timeline'),

    rxOpen: root.querySelector('#ne_open_rx'),
    rxRO: root.querySelector('#ne_rx_ro'),
    rxGrid: document.getElementById('ne_rx_grid'),
    rxAdd: document.getElementById('ne_rx_add'),
    rxSave: document.getElementById('ne_rx_save'),
    rxFeedback: document.getElementById('ne_rx_feedback'),
    rxOpenDoc: document.getElementById('ne_rx_open_doc'),

    docModal: document.getElementById('modalNotaEvolucion'),
    docModalTitle: document.getElementById('ne_doc_modal_title'),
    docText: document.getElementById('ne_doc_text'),
    docRxLayout: document.getElementById('ne_doc_rx_layout'),
    docNoteCaptureWrap: document.getElementById('ne_doc_note_capture_wrap'),
    docNoteCaptureImg: document.getElementById('ne_doc_note_capture_img'),
    docNoteCaptureLink: document.getElementById('ne_doc_note_capture_link'),
    docCopy: document.getElementById('ne_doc_copy'),
    docPrint: document.getElementById('ne_doc_print')
  };

  const citasModal = document.getElementById('modalCitasClinicas');
  const citasBlock = root.querySelector('#ne_citas_block');
  const citasMotivo = document.getElementById('ne_citas_motivo');
  const citasPadecimiento = document.getElementById('ne_citas_padecimiento');
  const citasSave = document.getElementById('ne_citas_save');

  const storage = {
    keyForPatient: (patientKey) => `mxmed_evolution_notes_v1:${patientKey || 'anon'}`,
    rxKeyForPatient: (patientKey) => `mxmed_rx_draft_v1:${patientKey || 'anon'}`
  };

  const api = (() => {
    const mxmedApiBase = () => {
      const loc = window.location;
      const host = loc.hostname;
      const port = loc.port;
      if (host === '127.0.0.1' || host === 'localhost') {
        if (port === '' || port === '80' || port === '443') {
          return loc.protocol + '//' + host + ':8090';
        }
        return loc.origin;
      }
      return loc.origin;
    };
    const legacyEndpoint = 'api/clinical-documents.php';
    const gatewayDocumentsEndpoint = mxmedApiBase() + '/api/clinical/index.php/documents';
    let mode = 'unknown'; // legacy compat flag (no longer driving persistence order)

    const fetchJson = async (url, options) => {
      const res = await fetch(url, {
        ...(options || {}),
        headers: {
          'Content-Type': 'application/json',
          ...(options?.headers || {})
        }
      });
      const data = await res.json().catch(() => null);
      if (!res.ok) {
        const msg = data?.error || (Array.isArray(data?.errors) ? data.errors.join(' ') : '') || `HTTP ${res.status}`;
        const err = new Error(msg);
        err.status = res.status;
        err.data = data;
        throw err;
      }
      return data;
    };

    const normalizeLimit = (value, fallback = 30) => {
      const n = Number(value);
      if (!Number.isFinite(n) || n <= 0) return fallback;
      return Math.min(200, Math.floor(n));
    };

    const listClinicalDocumentsLegacy = async (legacyPatientId, opts = {}) => {
      const patientId = String(legacyPatientId ?? '');
      const documentType = String(opts.document_type ?? '');
      const hospitalStayId = String(opts.hospital_stay_id ?? '');
      const limit = normalizeLimit(opts.limit, 30);

      let url = `${legacyEndpoint}?action=list&patient_id=${encodeURIComponent(patientId)}&limit=${encodeURIComponent(String(limit))}`;
      if (documentType !== '') {
        url += `&document_type=${encodeURIComponent(documentType)}`;
      }
      if (hospitalStayId !== '') {
        url += `&hospital_stay_id=${encodeURIComponent(hospitalStayId)}`;
      }

      const payload = await fetchJson(url, { method: 'GET', headers: {} });
      return Array.isArray(payload?.items) ? payload.items : [];
    };

    const listClinicalDocumentsGateway = async (canonicalPatientId, opts = {}) => {
      const patientId = String(canonicalPatientId ?? '').trim();
      const documentType = String(opts.document_type ?? '');
      const hospitalStayId = String(opts.hospital_stay_id ?? '');
      const limit = normalizeLimit(opts.limit, 30);

      let url = `${gatewayDocumentsEndpoint}?patient_id=${encodeURIComponent(patientId)}&limit=${encodeURIComponent(String(limit))}`;
      if (documentType !== '') {
        url += `&document_type=${encodeURIComponent(documentType)}`;
      }
      if (hospitalStayId !== '') {
        url += `&hospital_stay_id=${encodeURIComponent(hospitalStayId)}`;
      }

      const payload = await fetchJson(url, { method: 'GET', headers: { Accept: 'application/json' } });
      const items = payload?.data?.items;
      if (payload?.ok === true && Array.isArray(items)) {
        return items;
      }
      throw new Error(payload?.error || 'gateway documents list failed');
    };

    const listClinicalDocuments = async (patient, opts = {}) => {
      const patientObj = (patient && typeof patient === 'object')
        ? patient
        : { patient_id: patient, canonical_patient_id: null };

      const patientIdInput = String(patientObj?.patient_id ?? '').trim();
      const legacyPatientId = String(patientObj?.legacy_patient_id ?? patientIdInput).trim();
      let canonicalPatientId = /^p_/i.test(patientIdInput)
        ? patientIdInput
        : String(patientObj?.canonical_patient_id ?? '').trim();

      // If canonical is not yet available (non-blocking resolver), try a short resolve window.
      const waitWithTimeout = (promise, ms = 400) => {
        return new Promise((resolve) => {
          let settled = false;
          const timer = setTimeout(() => {
            if (settled) return;
            settled = true;
            resolve(null);
          }, ms);

          Promise.resolve(promise)
            .then((v) => {
              if (settled) return;
              settled = true;
              clearTimeout(timer);
              resolve(v ?? null);
            })
            .catch(() => {
              if (settled) return;
              settled = true;
              clearTimeout(timer);
              resolve(null);
            });
        });
      };

      if (canonicalPatientId === '') {
        const identity = window.mxmedIdentity || null;
        if (identity && typeof identity.resolveCanonicalPatientId === 'function') {
          const resolved = await waitWithTimeout(identity.resolveCanonicalPatientId(legacyPatientId), 400);
          if (typeof resolved === 'string' && resolved.trim() !== '') {
            canonicalPatientId = resolved.trim();
            // Keep a soft cache on the patient object (best-effort, no contract change).
            try { patientObj.canonical_patient_id = canonicalPatientId; } catch (_) {}
          }
        }
      }

      const errors = [];
      if (canonicalPatientId !== '') {
        try {
          const items = await listClinicalDocumentsGateway(canonicalPatientId, opts);
          return { items, source: 'gateway' };
        } catch (e) {
          errors.push(e);
          console.info('[P15][nota_evolucion] list fallback -> legacy', {
            reason: 'gateway_failed',
            patient_id: canonicalPatientId
          });
        }
      } else {
        console.info('[P15][nota_evolucion] list fallback -> legacy', {
          reason: 'canonical_unavailable',
          patient_id: legacyPatientId || null
        });
      }

      try {
        const items = await listClinicalDocumentsLegacy(legacyPatientId, opts);
        return { items, source: 'legacy' };
      } catch (e) {
        errors.push(e);
      }

      const mergedMessage = errors.map((e)=> String(e?.message || '').trim()).filter(Boolean).join(' | ');
      throw new Error(mergedMessage || 'No se pudo listar notas de evolución.');
    };

    const listEvolutionNotes = async (patient) => {
      return listClinicalDocuments(patient, {
        document_type: 'nota_evolucion',
        limit: 30
      });
    };

    const saveClinicalDocumentLegacy = async (args) => {
      return fetchJson(`${legacyEndpoint}?action=save`, {
        method: 'POST',
        body: JSON.stringify(args || {})
      });
    };

    const normalizeSavedDocumentResponse = (payload, source = '') => {
      const document = payload?.data?.document ?? payload?.document ?? null;
      if (!document || typeof document !== 'object') {
        throw new Error('invalid save response');
      }
      return { document, source };
    };

    const saveClinicalDocument = async (args) => {
      const requestArgs = (args && typeof args === 'object') ? { ...args } : {};
      const context = (requestArgs.context && typeof requestArgs.context === 'object') ? { ...requestArgs.context } : {};
      const fromBridge = (typeof window.getActiveEncounterKey === 'function')
        ? String(window.getActiveEncounterKey() || '').trim()
        : '';
      const encounterKey = String(context.encounter_key || fromBridge || '').trim();
      if (encounterKey) {
        context.encounter_key = encounterKey;
      } else {
        delete context.encounter_key;
      }
      requestArgs.context = context;
      const contextPatientId = String(context.patient_id ?? '').trim();
      const explicitLegacyPatientId = String(context.legacy_patient_id ?? '').trim();
      const canonicalPatientId = /^p_/i.test(contextPatientId)
        ? contextPatientId
        : await resolveCanonicalPatientIdSafe(contextPatientId).catch(() => null);
      const legacyPatientId = explicitLegacyPatientId || contextPatientId;

      const errors = [];
      if (canonicalPatientId) {
        const gatewayArgs = {
          ...requestArgs,
          context: {
            ...context,
            patient_id: canonicalPatientId,
            legacy_patient_id: legacyPatientId || undefined
          }
        };

        console.debug('SAVE gateway attempt', {
          patient_id: canonicalPatientId,
          legacy_patient_id: legacyPatientId || null,
          source: 'app'
        });

        try {
          const gatewayPayload = await fetchJson(gatewayDocumentsEndpoint, {
            method: 'POST',
            headers: { Accept: 'application/json' },
            body: JSON.stringify(gatewayArgs)
          });
          const normalized = normalizeSavedDocumentResponse(gatewayPayload, 'gateway');
          console.debug('SAVE gateway ok', {
            patient_id: canonicalPatientId,
            source: 'app'
          });
          return normalized;
        } catch (e) {
          errors.push(e);
          console.debug('SAVE fallback legacy', {
            reason: 'gateway_failed',
            source: 'app'
          });
        }
      } else {
        console.debug('SAVE fallback legacy', {
          reason: 'canonical_unavailable',
          source: 'app'
        });
      }

      try {
        const legacyPayload = await saveClinicalDocumentLegacy(requestArgs);
        return normalizeSavedDocumentResponse(legacyPayload, 'legacy');
      } catch (e) {
        errors.push(e);
      }

      const mergedMessage = errors.map((e)=> String(e?.message || '').trim()).filter(Boolean).join(' | ');
      throw new Error(mergedMessage || 'No se pudo guardar nota de evolución.');
    };

    const getClinicalDocument = async (id, opts = {}) => {
      const token = String(id || '').trim();
      if (!token) throw new Error('document token requerido');
      const preferredSource = String(opts.preferredSource || '').trim().toLowerCase();
      const errors = [];

      const tryGateway = async () => {
        const payload = await fetchJson(`${gatewayDocumentsEndpoint}/${encodeURIComponent(token)}`, {
          method: 'GET',
          headers: { Accept: 'application/json' }
        });
        const document = payload?.data?.document ?? null;
        if (!document || typeof document !== 'object') throw new Error('invalid gateway get response');
        return { document, source: 'gateway' };
      };
      const tryLegacy = async () => {
        const payload = await fetchJson(`${legacyEndpoint}?action=get&id=${encodeURIComponent(token)}`, { method: 'GET', headers: {} });
        const document = payload?.data?.document ?? payload?.document ?? null;
        if (!document || typeof document !== 'object') throw new Error('invalid legacy get response');
        return { document, source: 'legacy' };
      };

      if (preferredSource === 'legacy') {
        try { return await tryLegacy(); } catch (e) { errors.push(e); }
        try { return await tryGateway(); } catch (e) { errors.push(e); }
      } else {
        try { return await tryGateway(); } catch (e) { errors.push(e); }
        console.info('[P15][nota_evolucion] detail fallback -> legacy', { reason: 'gateway_failed', token });
        try { return await tryLegacy(); } catch (e) { errors.push(e); }
      }

      const mergedMessage = errors.map((e)=> String(e?.message || '').trim()).filter(Boolean).join(' | ');
      throw new Error(mergedMessage || 'No se pudo obtener detalle de nota.');
    };

    return {
      get mode(){ return mode; },
      set mode(v){ mode = v; },
      listEvolutionNotes,
      saveClinicalDocument,
      getClinicalDocument
    };
  })();

  const normalize = (str) => (str || '')
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, ' ')
    .trim();

  const getIdentityApi = () => window.mxmedIdentity || null;
  const getCanonicalCache = () => {
    if (!window.__mxmed_canonical_cache || typeof window.__mxmed_canonical_cache !== 'object') {
      window.__mxmed_canonical_cache = {};
    }
    return window.__mxmed_canonical_cache;
  };
  const buildLegacyPatientId = (nombreCompleto, dob, sexoVal) => {
    const identity = getIdentityApi();
    if (identity && typeof identity.buildLegacyPatientId === 'function') {
      return identity.buildLegacyPatientId(nombreCompleto, dob, sexoVal, normalize);
    }
    return normalize([nombreCompleto, dob, sexoVal].join('|')) || 'anon';
  };
  const resolveContextPatientId = () => {
    const pane = document.getElementById('p-expediente');
    const candidates = [
      window.mxmedStore && (window.mxmedStore.currentPatientId || window.mxmedStore.activePatientId),
      pane && (pane.dataset?.patientId || pane.getAttribute('data-patient-id')),
      pane && (pane.dataset?.activePatientId || pane.getAttribute('data-active-patient-id')),
      window.mxmedActivePatientId,
      window.__MXMED_ACTIVE_PATIENT_ID
    ];
    for (const raw of candidates) {
      const value = String(raw || '').trim();
      if (/^p_/i.test(value)) return value;
    }
    for (const raw of candidates) {
      const value = String(raw || '').trim();
      if (value) return value;
    }
    return '';
  };
  const resolveCanonicalPatientIdSafe = (legacyPatientId) => {
    const legacy = String(legacyPatientId ?? '').trim();
    if (!legacy || legacy === 'anon') return Promise.resolve(null);

    const cache = getCanonicalCache();
    if (Object.prototype.hasOwnProperty.call(cache, legacy)) {
      return Promise.resolve(cache[legacy] || null);
    }

    const identity = getIdentityApi();
    if (!identity || typeof identity.resolveCanonicalPatientId !== 'function') {
      cache[legacy] = null;
      return Promise.resolve(null);
    }

    return identity.resolveCanonicalPatientId(legacy).then((canonical) => {
      cache[legacy] = canonical || null;
      return cache[legacy];
    }).catch(() => {
      cache[legacy] = null;
      return null;
    });
  };

  const safeText = (v, fallback = 'No registrado') => {
    const t = (v ?? '').toString().trim();
    return t ? t : fallback;
  };

  const getPatient = () => {
    const pane = document.getElementById('p-expediente');
    const nombre = pane?.querySelector('[data-pac-nombre]')?.value?.trim() || '';
    const apPat = pane?.querySelector('[data-pac-apellido-paterno]')?.value?.trim() || '';
    const apMat = pane?.querySelector('[data-pac-apellido-materno]')?.value?.trim() || '';
    const nombreCompleto = [nombre, apPat, apMat].filter(Boolean).join(' ').trim() || 'Paciente';
    const edad = pane?.querySelector('[data-dg-edad]')?.textContent?.trim() || '--';
    const sexoVal = pane?.querySelector('input[name="pac-genero"]:checked')?.value || '';
    const sexo = sexoVal === 'F' ? 'Femenino' : sexoVal === 'M' ? 'Masculino' : sexoVal === 'O' ? 'Otro' : '--';
    const dd = pane?.querySelector('[data-dg-dia]')?.value || '';
    const mm = pane?.querySelector('[data-dg-mes]')?.value || '';
    const yy = pane?.querySelector('[data-dg-anio]')?.value || '';
    const dob = [yy, mm, dd].filter(Boolean).join('-');
    const patientKey = buildLegacyPatientId(nombreCompleto, dob, sexoVal);
    const contextPatientId = resolveContextPatientId();
    const canonicalCache = getCanonicalCache();
    const canonicalPatientId = /^p_/i.test(contextPatientId)
      ? contextPatientId
      : (Object.prototype.hasOwnProperty.call(canonicalCache, patientKey)
        ? (canonicalCache[patientKey] || null)
        : null);

    if (!canonicalPatientId) {
      resolveCanonicalPatientIdSafe(patientKey).catch(() => {});
    }
    const resolvedPatientId = canonicalPatientId || contextPatientId || patientKey;
    if (window.__p15LastResolvedPatientLog !== resolvedPatientId) {
      window.__p15LastResolvedPatientLog = resolvedPatientId;
      console.info('[P15][nota_evolucion] patient_id resolved', {
        patient_id: resolvedPatientId,
        canonical_patient_id: canonicalPatientId || null,
        context_patient_id: contextPatientId || null,
        legacy_patient_id: patientKey
      });
    }

    return {
      patient_id: resolvedPatientId,
      legacy_patient_id: patientKey,
      canonical_patient_id: canonicalPatientId,
      nombre_completo: nombreCompleto,
      edad,
      sexo
    };
  };

  const getDoctor = () => {
    const nombre = document.querySelector('.user-id .name')?.textContent?.trim() || 'Médico';
    const cedula = document.getElementById('ced-prof')?.value?.trim() || '';
    const especialidad = document.getElementById('fs-esp')?.textContent?.trim() || '';
    return {
      user_id: normalize(nombre) || 'user',
      nombre_completo: nombre,
      cedula_profesional: cedula || '--',
      especialidad: especialidad || '--'
    };
  };

  const findTextareaByLabel = (tabId, labelText) => {
    const tab = document.getElementById(tabId);
    if (!tab) return '';
    const labels = Array.from(tab.querySelectorAll('label.form-label'));
    const label = labels.find(l => normalize(l.textContent).includes(normalize(labelText)));
    const wrap = label?.closest('div');
    const ta = wrap?.querySelector('textarea.form-control') || label?.parentElement?.querySelector('textarea.form-control');
    return ta || null;
  };

  const getClinicalCitations = () => {
    const motivo = findTextareaByLabel('t-historia', 'Motivo de la Consulta')?.value?.trim() || '';
    const padecimiento = findTextareaByLabel('t-historia', 'Padecimiento Actual')?.value?.trim() || '';
    return {
      motivo_consulta: motivo,
      padecimiento_actual: padecimiento
    };
  };

  const setClinicalCitations = (motivo, padecimiento) => {
    const motivoTA = findTextareaByLabel('t-historia', 'Motivo de la Consulta');
    const padecimientoTA = findTextareaByLabel('t-historia', 'Padecimiento Actual');
    if (motivoTA) {
      motivoTA.value = (motivo || '').trim();
      motivoTA.dispatchEvent(new Event('input', { bubbles: true }));
    }
    if (padecimientoTA) {
      padecimientoTA.value = (padecimiento || '').trim();
      padecimientoTA.dispatchEvent(new Event('input', { bubbles: true }));
    }
  };

  const numOrNull = (id) => {
    const el = document.getElementById(id);
    if (!el) return null;
    const raw = (el.value ?? '').toString().trim();
    if (!raw) return null;
    const n = Number(raw);
    return Number.isFinite(n) ? n : null;
  };

  const getVitals = () => ({
    ta_sistolica: numOrNull('exp_bp_sys'),
    ta_diastolica: numOrNull('exp_bp_dia'),
    fc: numOrNull('exp_fc_value'),
    fr: numOrNull('exp_fr_value'),
    temperatura: numOrNull('exp_temp_value'),
    spo2: numOrNull('exp_spo2_value'),
    dolor_eva: numOrNull('exp_pain_value')
  });

  const getExploracionSistemas = () => {
    // Preferir la función si existe, pero no depender de ella.
    try {
      if (typeof window.mxExploracionPorSistemasPayload === 'function') {
        const p = window.mxExploracionPorSistemasPayload();
        return {
          resumen_sistemas: (p?.resumen_editado || '').trim(),
          hallazgos_relevantes: (p?.hallazgos_relevantes || '').trim()
        };
      }
    } catch (_) {}

    return {
      resumen_sistemas: document.getElementById('exp_resumen')?.value?.trim() || '',
      hallazgos_relevantes: document.getElementById('exp_hallazgos_relevantes')?.value?.trim() || ''
    };
  };

  const getRecentStudyOrders = () => {
    const orders = Array.from(document.querySelectorAll('.est-order-card[data-est-order-area]'));
    return orders.slice(0, 5).flatMap((card, index) => {
      const area = card.getAttribute('data-est-order-area') || 'Estudios';
      const items = (card.getAttribute('data-est-order-items') || '')
        .split(',')
        .map(s => s.trim())
        .filter(Boolean);
      const meta = card.querySelector('.est-order-meta')?.textContent?.trim() || '';
      const dateMatch = meta.split('·').map(s => s.trim()).pop();
      const fecha = dateMatch || '';
      return items.map((nombre, j) => ({
        order_id: `${area}-${index}-${j}`,
        nombre_estudio: nombre,
        fecha,
        resultado_resumen: '',
        archivo_url: ''
      }));
    });
  };

  const getDiagnosticos = () => {
    // Si existe un módulo externo de diagnósticos, se puede conectar aquí.
    const fromText = (els.dx?.value || '')
      .split('\n')
      .map(s => s.trim())
      .filter(Boolean)
      .map(label => ({ code: '', label }));
    return fromText;
  };

  const getPronostico = () => {
    const v = els.pronostico?.value || '';
    const free = (els.pronosticoTxt?.value || '').trim();
    if (v === 'otro') return free;
    if (v === 'bueno') return 'Bueno';
    if (v === 'reservado') return 'Reservado';
    if (v === 'malo') return 'Malo';
    return '';
  };
  const normalizeNoteCaptureAttachmentEntry = (entry) => {
    if (!entry || typeof entry !== 'object') return null;
    const rawId = String(entry.document_id ?? '').trim();
    const docId = /^\d+$/.test(rawId) ? Number(rawId) : null;
    const docUuid = String(entry.document_uuid ?? '').trim();
    const previewUrl = String(entry.preview_url ?? '').trim();
    const token = String(entry.note_capture_token ?? '').trim();
    const source = String(entry.source ?? 'nota_modal_qr_v1').trim() || 'nota_modal_qr_v1';
    if (!docId && !docUuid && !previewUrl && !token) return null;
    return {
      document_id: docId,
      document_uuid: docUuid || null,
      preview_url: previewUrl || null,
      note_capture_token: token || null,
      source
    };
  };
  const readQrNoteCaptureAttachmentFromModal = () => {
    const modalEl = document.getElementById('modalActividadClinicaNota');
    if (!modalEl) return null;
    return normalizeNoteCaptureAttachmentEntry({
      document_id: modalEl.dataset.noteCaptureDocumentId || null,
      document_uuid: modalEl.dataset.noteCaptureDocumentUuid || null,
      preview_url: modalEl.dataset.noteCapturePreviewUrl || null,
      note_capture_token: modalEl.dataset.noteCaptureToken || null,
      source: 'nota_modal_qr_v1'
    });
  };
  const mergeNoteCaptureAttachments = (currentList, nextEntry) => {
    const out = [];
    const seen = new Set();
    const append = (entry) => {
      const normalized = normalizeNoteCaptureAttachmentEntry(entry);
      if (!normalized) return;
      const key = `${normalized.document_id || ''}|${normalized.document_uuid || ''}|${normalized.note_capture_token || ''}`;
      if (seen.has(key)) return;
      seen.add(key);
      out.push(normalized);
    };
    if (Array.isArray(currentList)) {
      currentList.forEach(append);
    }
    append(nextEntry);
    return out;
  };

  const formatVitalsLine = (sv) => {
    const ta = (sv.ta_sistolica != null && sv.ta_diastolica != null) ? `${sv.ta_sistolica}/${sv.ta_diastolica} mmHg` : 'TA: No registrado';
    const fc = sv.fc != null ? `FC: ${sv.fc} lpm` : 'FC: No registrado';
    const fr = sv.fr != null ? `FR: ${sv.fr} rpm` : 'FR: No registrado';
    const t = sv.temperatura != null ? `Temp: ${sv.temperatura} °C` : 'Temp: No registrado';
    const s = sv.spo2 != null ? `SpO₂: ${sv.spo2}%` : 'SpO₂: No registrado';
    const d = sv.dolor_eva != null ? `Dolor: ${sv.dolor_eva}/10` : 'Dolor: No registrado';
    return [ta, fc, fr, t, s, d].join(' · ');
  };

  const getRxDraft = (patientKey) => {
    try {
      const raw = localStorage.getItem(storage.rxKeyForPatient(patientKey));
      const data = raw ? JSON.parse(raw) : null;
      if (!data || !Array.isArray(data.medicamentos)) return { has_prescription: false, prescription_id: '', medicamentos: [] };
      return {
        has_prescription: data.medicamentos.length > 0,
        prescription_id: data.prescription_id || '',
        medicamentos: data.medicamentos
      };
    } catch (_) {
      return { has_prescription: false, prescription_id: '', medicamentos: [] };
    }
  };

  const setRxDraft = (patientKey, medicamentos) => {
    try {
      localStorage.setItem(storage.rxKeyForPatient(patientKey), JSON.stringify({
        prescription_id: `rx_${Date.now()}`,
        medicamentos
      }));
    } catch (_) {}
  };

  const buildPronosticoObject = () => {
    const preset = (els.pronostico?.value || '').trim();
    const texto = (els.pronosticoTxt?.value || '').trim();
    if (preset === 'bueno' || preset === 'reservado' || preset === 'malo') return { preset, texto: null };
    if (preset === 'otro') return { preset: null, texto: texto || null };
    return { preset: null, texto: null };
  };

  const pronosticoToText = (p) => {
    const preset = p?.preset || '';
    const texto = (p?.texto || '').trim();
    if (texto) return texto;
    if (preset === 'bueno') return 'Bueno';
    if (preset === 'reservado') return 'Reservado';
    if (preset === 'malo') return 'Malo';
    return '';
  };

  window.buildEvolutionNotePayload = function buildEvolutionNotePayload() {
    const now = new Date();
    const citas = getClinicalCitations();
    const signos = getVitals();
    const expl = getExploracionSistemas();
    const estudios = getRecentStudyOrders();
    const dx = getDiagnosticos();
    const patient = getPatient();
    const medico = getDoctor();
    const rx = getRxDraft(patient.patient_id);
    const pronostico = buildPronosticoObject();
    const qrNoteCaptureAttachment = readQrNoteCaptureAttachmentFromModal();

    const temaNota = (els.complemento?.value || '').trim();
    const payloadBase = {
      section_id: 'nota_evolucion',
      standard: 'NOM-004-SSA3-2012',
      contract_version: 1,
      ambito: els.ambito?.value || 'consulta',
      citas_clinicas: {
        motivo_consulta: citas.motivo_consulta || '',
        padecimiento_actual: citas.padecimiento_actual || ''
      },
      tema_nota: temaNota,
      complemento_sintomas: temaNota,
      evolucion_cuadro_clinico: (els.evolucion?.value || '').trim(),
      signos_vitales: signos,
      exploracion_relevante: {
        resumen_sistemas: expl.resumen_sistemas || '',
        hallazgos_relevantes: expl.hallazgos_relevantes || ''
      },
      estudios_relevantes: estudios,
      interpretacion_resultados: (els.interp?.value || '').trim(),
      diagnosticos: dx,
      pronostico,
      plan_indicaciones: (els.plan?.value || '').trim(),
      receta: rx,
      snapshot: {
        paciente: {
          patient_id: patient.patient_id,
          nombre_completo: patient.nombre_completo,
          edad: patient.edad,
          sexo: patient.sexo
        },
        medico: {
          user_id: medico.user_id,
          nombre_completo: medico.nombre_completo,
          cedula_profesional: medico.cedula_profesional,
          especialidad: medico.especialidad
        },
        generated_at: now.toISOString()
      }
    };
    const existingNoteCapture = Array.isArray(payloadBase?.attachments?.note_capture)
      ? payloadBase.attachments.note_capture
      : [];
    const mergedNoteCapture = mergeNoteCaptureAttachments(existingNoteCapture, qrNoteCaptureAttachment);
    if (mergedNoteCapture.length) {
      payloadBase.attachments = {
        ...(payloadBase.attachments && typeof payloadBase.attachments === 'object' ? payloadBase.attachments : {}),
        note_capture: mergedNoteCapture
      };
    }
    return payloadBase;
  };

  window.buildEvolutionNoteRenderedText = function buildEvolutionNoteRenderedText(payload, context) {
    const p = payload || {};
    const dt = new Date();
    const dtStr = dt.toLocaleString('es-MX');

    const snapshot = p.snapshot || {};
    const paciente = snapshot.paciente || {};
    const medico = snapshot.medico || {};
    const citas = p.citas_clinicas || {};
    const sv = p.signos_vitales || {};
    const ex = p.exploracion_relevante || {};
    const dx = Array.isArray(p.diagnosticos) ? p.diagnosticos : [];
    const rx = p.receta || { medicamentos: [] };
    const estudios = Array.isArray(p.estudios_relevantes) ? p.estudios_relevantes : [];

    const lines = [];
    lines.push('NOTA DE EVOLUCIÓN (NOM-004-SSA3-2012)');
    lines.push(`Fecha/Hora: ${dtStr}`);
    lines.push(`Ámbito: ${p.ambito || 'consulta'}`);
    lines.push('');
    lines.push(`Médico responsable: ${medico.nombre_completo || 'No registrado'}`);
    lines.push(`Cédula profesional: ${medico.cedula_profesional || 'No registrado'} · Especialidad: ${medico.especialidad || 'No registrado'}`);
    lines.push('');
    lines.push(`Paciente: ${paciente.nombre_completo || 'No registrado'} · Edad: ${paciente.edad || '--'} · Sexo: ${paciente.sexo || '--'}`);
    lines.push('');
    lines.push('SÍNTOMAS ACTUALES RELEVANTES (cita)');
    lines.push(`Motivo de consulta: ${safeText(citas.motivo_consulta, 'No registrado')}`);
    lines.push(`Padecimiento actual: ${safeText(citas.padecimiento_actual, 'No registrado')}`);
    const temaNota = (p.tema_nota || p.complemento_sintomas || '').trim();
    if (temaNota) {
      lines.push(`Tema de la nota: ${temaNota}`);
    }
    lines.push('');
    lines.push('EVOLUCIÓN / ACTUALIZACIÓN DEL CUADRO CLÍNICO');
    lines.push(safeText(p.evolucion_cuadro_clinico, 'No registrado'));
    lines.push('');
    lines.push('SIGNOS VITALES');
    lines.push(formatVitalsLine(sv));
    lines.push('');
    lines.push('EXPLORACIÓN FÍSICA RELEVANTE');
    lines.push(`Resumen por sistemas: ${safeText(ex.resumen_sistemas, 'No registrado')}`);
    lines.push(`Hallazgos relevantes: ${safeText(ex.hallazgos_relevantes, 'No registrado')}`);
    lines.push('');
    lines.push('RESULTADOS RELEVANTES DE ESTUDIOS AUXILIARES');
    if (!estudios.length) {
      lines.push('No registrado');
    } else {
      estudios.slice(0, 12).forEach(item => {
        const fecha = (item.fecha || '').trim();
        lines.push(`- ${item.nombre_estudio || 'Estudio'}${fecha ? ` (${fecha})` : ''}`);
      });
    }
    if ((p.interpretacion_resultados || '').trim()) {
      lines.push('');
      lines.push('INTERPRETACIÓN CLÍNICA DE RESULTADOS');
      lines.push(p.interpretacion_resultados.trim());
    }
    lines.push('');
    lines.push('DIAGNÓSTICO(S)');
    if (!dx.length) {
      lines.push('No registrado');
    } else {
      dx.forEach(d => lines.push(`- ${(d.label || '').trim() || 'Diagnóstico'}`));
    }
    lines.push('');
    lines.push('PRONÓSTICO');
    lines.push(safeText(pronosticoToText(p.pronostico), 'No registrado'));
    lines.push('');
    lines.push('TRATAMIENTO E INDICACIONES');
    lines.push(safeText(p.plan_indicaciones, 'No registrado'));
    lines.push('');
    lines.push('MEDICAMENTOS (RECETA)');
    if (!Array.isArray(rx.medicamentos) || rx.medicamentos.length === 0) {
      lines.push('Sin receta registrada');
    } else {
      rx.medicamentos.forEach(m => {
        const parts = [
          (m.medicamento || '').trim(),
          (m.dosis || '').trim(),
          (m.via || '').trim(),
          (m.periodicidad || '').trim(),
          (m.duracion || '').trim()
        ].filter(Boolean);
        const base = parts.join(' · ') || 'Medicamento';
        const extra = (m.indicaciones || '').trim();
        lines.push(`- ${base}${extra ? ` (${extra})` : ''}`);
      });
    }
    lines.push('');
    lines.push(`Documento generado: ${dtStr}`);
    return lines.join('\n');
  };

  // Compat: alias anterior (dev)
  window.buildEvolutionNoteDocumentText = function(payload, context) {
    return window.buildEvolutionNoteRenderedText(payload, context);
  };

  const normalizeEvolutionNoteTitle = (raw, fallback = 'Nota de evolución') => {
    const text = String(raw || '').trim();
    if (!text) return fallback;
    return text.replace(/^Nota de evoluci[oó]n\s*[—:-]\s*/i, '').trim() || fallback;
  };

  window.buildEvolutionNoteSummary = function buildEvolutionNoteSummary(payload) {
    const p = payload || {};
    const tema = (p.tema_nota || p.complemento_sintomas || '').trim();
    if (tema) return tema;
    const evo = (p.evolucion_cuadro_clinico || '').trim();
    const plan = (p.plan_indicaciones || '').trim();
    const ex = p.exploracion_relevante || {};
    const exText = `${(ex.resumen_sistemas || '').trim()}\n${(ex.hallazgos_relevantes || '').trim()}`;

    const hasAdjust = /\b(ajust|cambi|modific|increment|aument|disminu|suspende|inicia|iniciar|titul|escalar|rota|cambia)\w*/i.test(plan);
    const text = `${exText}\n${evo}`;
    const spo2Val = Number(p?.signos_vitales?.spo2);
    const spo2Low = Number.isFinite(spo2Val) && spo2Val <= 93;
    const spo2FromText = (() => {
      const m = text.match(/\b(spo2|sp[oó]2|sat|saturaci[oó]n)\s*[:=]?\s*(\d{2,3})\b/i);
      if (!m) return null;
      const n = Number(m[2]);
      return Number.isFinite(n) ? n : null;
    })();
    const spo2LowText = spo2FromText != null && spo2FromText <= 93;
    const respAbn = /\b(disnea|sibilanc\w*|estertor\w*|crepitant\w*|tiraje|cianos\w*|broncoespasm\w*|broncoobstru\w*|hipox\w*|uso\s+de\s+ox[ií]geno|ox[ií]geno\s+(suplementario|terapia)|c[aá]nula\s+nasal|mascarilla|nebuliz\w*|inhalador\w*|wheez\w*)\b/i.test(text);
    const negRespCore = /\b(sin|niega)\s+(disnea|sibilanc\w*|estertor\w*|crepitant\w*|tiraje|cianos\w*|broncoespasm\w*|hipox\w*)\b/i.test(text);
    const negO2 = /\b(sin|niega)\s+(uso\s+de\s+ox[ií]geno|requerimiento\s+de\s+ox[ií]geno|ox[ií]geno)\b/i.test(text);
    const hasResp = (spo2Low || spo2LowText) || (respAbn && !(negRespCore || negO2));
    const noAlt = /\b(sin\s+alter|sin\s+camb|sin\s+noved|establ|evoluci[oó]n\s+favorable)\w*/i.test(evo);

    if (hasResp) return 'Hallazgos respiratorios';
    if (hasAdjust) return 'Ajustes terapéuticos';
    if (noAlt) return 'Sin alteraciones relevantes';
    return 'Nota de evolución';
  };

  window.buildPrescriptionSummary = function buildPrescriptionSummary(payload) {
    const rx = (payload && typeof payload === 'object' && payload.prescription && typeof payload.prescription === 'object')
      ? payload.prescription
      : {};
    const items = Array.isArray(rx.items) ? rx.items : [];
    if (!items.length) return 'Receta médica';
    const visible = items.slice(0, 2).map((item) => {
      const medicamento = String(item?.medicamento || '').trim();
      const dosis = String(item?.dosis || '').trim();
      if (!medicamento) return '';
      return dosis ? `${medicamento} ${dosis}` : medicamento;
    }).filter(Boolean);
    const suffix = items.length > 2 ? ` y ${items.length - 2} más` : '';
    return visible.length ? `${visible.join(', ')}${suffix}` : `Receta médica (${items.length} medicamento${items.length === 1 ? '' : 's'})`;
  };

  const buildPrescriptionRenderedText = (payload, context = {}, actor = {}) => {
    const now = new Date().toLocaleString('es-MX');
    const rx = (payload && typeof payload === 'object' && payload.prescription && typeof payload.prescription === 'object')
      ? payload.prescription
      : {};
    const items = Array.isArray(rx.items) ? rx.items : [];
    const patientName = String(payload?.snapshot?.paciente?.nombre_completo || '').trim();
    const doctorName = String(payload?.snapshot?.medico?.nombre_completo || actor?.nombre_completo || '').trim();
    const lines = [];
    lines.push('RECETA MÉDICA');
    lines.push(`Fecha/Hora: ${now}`);
    if (patientName) lines.push(`Paciente: ${patientName}`);
    if (doctorName) lines.push(`Médico: ${doctorName}`);
    lines.push('');
    lines.push('MEDICAMENTOS');
    if (!items.length) {
      lines.push('Sin medicamentos registrados');
    } else {
      items.forEach((item) => {
        const parts = [
          String(item?.medicamento || '').trim(),
          String(item?.dosis || '').trim(),
          String(item?.via || '').trim(),
          String(item?.frecuencia || item?.periodicidad || '').trim(),
          String(item?.duracion || '').trim()
        ].filter(Boolean);
        const indicaciones = String(item?.indicaciones || '').trim();
        const base = parts.join(' · ') || 'Medicamento';
        lines.push(`- ${base}${indicaciones ? ` (${indicaciones})` : ''}`);
      });
    }
    const observaciones = String(rx.observaciones || '').trim();
    if (observaciones) {
      lines.push('');
      lines.push('OBSERVACIONES');
      lines.push(observaciones);
    }
    if (context?.encounter_key) {
      lines.push('');
      lines.push(`Encounter: ${String(context.encounter_key).trim()}`);
    }
    return lines.join('\n');
  };

  window.buildClinicalDocument = function buildClinicalDocument({ type, context, payload, actor }) {
    const nowIso = new Date().toISOString();
    const docType = (type || '').trim();
    const ctx = context || {};
    const act = actor || {};
    return {
      document_id: `tmp_${Date.now()}`,
      document_type: docType,
      title: docType === 'nota_evolucion'
        ? window.buildEvolutionNoteSummary(payload)
        : (docType === 'prescription' ? 'Receta médica' : (docType || 'Documento clínico')),
      version: 1,
      context: {
        patient_id: ctx.patient_id,
        encounter_key: ctx.encounter_key ?? null,
        encounter_id: ctx.encounter_id ?? null,
        hospital_stay_id: ctx.hospital_stay_id ?? null,
        care_setting: ctx.care_setting || 'consulta',
        service: ctx.service ?? null
      },
      status: 'generated',
      timestamps: {
        created_at: nowIso,
        updated_at: null,
        generated_at: nowIso,
        signed_at: null
      },
      audit: {
        created_by_user_id: act.user_id,
        updated_by_user_id: null
      },
      participants: [
        {
          user_id: act.user_id,
          role: 'medico',
          participation_type: 'responsable',
          signed_at: null
        }
      ],
      content: {
        payload,
        rendered_text: docType === 'prescription' ? buildPrescriptionRenderedText(payload, ctx, act) : null,
        summary: docType === 'nota_evolucion'
          ? window.buildEvolutionNoteSummary(payload)
          : (docType === 'prescription' ? window.buildPrescriptionSummary(payload) : null),
        edited_flag: 0
      },
      ui: {
        event_datetime: nowIso,
        widget_group: 'documentos_clinicos',
        printable: true
      }
    };
  };

  const validate = (payload) => {
    const errors = [];
    if (!String(payload.tema_nota || payload.complemento_sintomas || '').trim()) {
      errors.push('Tema de la nota es obligatorio.');
    }
    if (!payload.ambito) errors.push('Ámbito es obligatorio.');
    if (!payload.evolucion_cuadro_clinico) errors.push('Evolución / actualización del cuadro clínico es obligatoria.');
    if (!Array.isArray(payload.diagnosticos) || payload.diagnosticos.length === 0) errors.push('Diagnóstico(s) es obligatorio.');
    const pron = payload.pronostico || {};
    if (!(pron?.preset || (pron?.texto || '').trim())) errors.push('Pronóstico es obligatorio.');
    if (!payload.plan_indicaciones) errors.push('Tratamiento e indicaciones es obligatorio.');
    return errors;
  };

  const loadNotes = (patientKey) => {
    try {
      const raw = localStorage.getItem(storage.keyForPatient(patientKey));
      const list = raw ? JSON.parse(raw) : [];
      return Array.isArray(list) ? list : [];
    } catch (_) {
      return [];
    }
  };

  const saveNotes = (patientKey, list) => {
    try { localStorage.setItem(storage.keyForPatient(patientKey), JSON.stringify(list)); } catch (_) {}
  };

  const renderRxSummary = (patientKey) => {
    const rx = getRxDraft(patientKey);
    if (!els.rxRO) return;
    if (!rx.has_prescription) {
      els.rxRO.textContent = 'Sin receta registrada';
      return;
    }
    const text = rx.medicamentos.map(m => {
      const parts = [
        (m.medicamento || '').trim(),
        (m.dosis || '').trim(),
        (m.via || '').trim(),
        (m.periodicidad || '').trim(),
        (m.duracion || '').trim()
      ].filter(Boolean).join(' · ');
      return `• ${parts || 'Medicamento'}`;
    }).join('\n');
    els.rxRO.textContent = text;
    els.rxRO.style.whiteSpace = 'pre-wrap';
  };

  const renderReadonly = () => {
    const citas = getClinicalCitations();
    els.motivoRO && (els.motivoRO.textContent = safeText(citas.motivo_consulta));
    els.padecimientoRO && (els.padecimientoRO.textContent = safeText(citas.padecimiento_actual));

    const sv = getVitals();
    els.vitalsRO && (els.vitalsRO.textContent = safeText(formatVitalsLine(sv), 'No registrado'));

    const ex = getExploracionSistemas();
    const exText = [
      `Resumen: ${safeText(ex.resumen_sistemas, 'No registrado')}`,
      `Hallazgos: ${safeText(ex.hallazgos_relevantes, 'No registrado')}`
    ].join('\n');
    if (els.exploracionRO) {
      els.exploracionRO.textContent = exText;
      els.exploracionRO.style.whiteSpace = 'pre-wrap';
    }

    const studies = getRecentStudyOrders();
    if (els.studiesRO) {
      if (!studies.length) {
        els.studiesRO.textContent = 'No registrado';
      } else {
        els.studiesRO.innerHTML = studies.slice(0, 8).map(s => {
          const meta = s.fecha ? `<span class="ne-study-meta">${s.fecha}</span>` : `<span class="ne-study-meta">${s.order_id}</span>`;
          return `<div class="ne-study-item"><div class="ne-study-name">${s.nombre_estudio}</div>${meta}</div>`;
        }).join('');
      }
    }

    renderRxSummary(getPatient().patient_id);
  };

  const openCitasModalIfMissing = () => {
    if (!citasModal) return;
    const citas = getClinicalCitations();
    const missingAny = !citas.motivo_consulta || !citas.padecimiento_actual;
    if (!missingAny) return;
    if (citasMotivo) citasMotivo.value = citas.motivo_consulta || '';
    if (citasPadecimiento) citasPadecimiento.value = citas.padecimiento_actual || '';
    try {
      const modal = bootstrap?.Modal?.getOrCreateInstance ? bootstrap.Modal.getOrCreateInstance(citasModal) : null;
      modal?.show();
      setTimeout(() => citasMotivo?.focus?.(), 50);
    } catch (_) {}
  };

  const bindCitasTwoWaySync = () => {
    const motivoTA = findTextareaByLabel('t-historia', 'Motivo de la Consulta');
    const padecimientoTA = findTextareaByLabel('t-historia', 'Padecimiento Actual');
    if (!motivoTA || !padecimientoTA) return;

    let t = null;
    const sync = () => {
      const citas = getClinicalCitations();
      if (els.motivoRO) els.motivoRO.textContent = safeText(citas.motivo_consulta);
      if (els.padecimientoRO) els.padecimientoRO.textContent = safeText(citas.padecimiento_actual);
    };
    const schedule = () => {
      if (t) window.clearTimeout(t);
      t = window.setTimeout(sync, 120);
    };

    motivoTA.addEventListener('input', schedule);
    padecimientoTA.addEventListener('input', schedule);
  };

  const renderTimeline = () => {
    const patient = getPatient();
    if (!els.timeline) return;

    const renderLocal = () => {
      const list = loadNotes(patient.patient_id);
      if (!list.length) {
        els.timeline.innerHTML = '<div class="text-muted small">Sin notas todavía.</div>';
        return;
      }
      els.timeline.innerHTML = list
        .slice()
        .sort((a, b) => (b.created_at || '').localeCompare(a.created_at || ''))
        .slice(0, 20)
        .map(n => {
          const dt = n.created_at ? new Date(n.created_at).toLocaleString('es-MX') : '';
          const amb = n.ambito_label || '';
          const ttl = normalizeEvolutionNoteTitle(n.summary || n.title, amb || 'Nota de evolución');
          const doc = n.document_text || '';
          return `
            <div class="ne-note-card">
              <div class="ne-note-head">
                <div>
                  <div class="ne-note-ttl">${ttl.replace(/</g, '&lt;')}</div>
                  <div class="ne-note-meta">${dt}</div>
                </div>
              </div>
              <div class="ne-note-actions">
                <button type="button" class="btn btn-outline-primary btn-sm" data-ne-action="view" data-ne-source="local" data-ne-id="${n.id}">Ver</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-ne-action="print" data-ne-source="local" data-ne-id="${n.id}">Imprimir</button>
              </div>
              <textarea class="d-none" data-ne-doc="${n.id}">${doc.replace(/</g, '&lt;')}</textarea>
            </div>
          `;
        })
        .join('');
    };

    els.timeline.innerHTML = '<div class="text-muted small">Cargando…</div>';
    api.listEvolutionNotes(patient)
      .then(({ items, source }) => {
        console.info('[P15][nota_evolucion] list source', { source: String(source || 'unknown') });
        const list = Array.isArray(items) ? items : [];
        if (!list.length) {
          els.timeline.innerHTML = '<div class="text-muted small">Sin notas todavía.</div>';
          return;
        }
        els.timeline.innerHTML = list.map(item => {
          const dt = item.event_datetime ? new Date(item.event_datetime).toLocaleString('es-MX') : '';
          const ttl = normalizeEvolutionNoteTitle(item.summary || item.title, 'Nota de evolución');
          const meta = dt;
          const docToken = String(item.document_uuid || item.document_id || item.id || '').trim();
          return `
            <div class="ne-note-card">
              <div class="ne-note-head">
                <div>
                  <div class="ne-note-ttl">${ttl.replace(/</g, '&lt;')}</div>
                  <div class="ne-note-meta">${(meta || dt).replace(/</g, '&lt;')}</div>
                </div>
              </div>
              <div class="ne-note-actions">
                <button type="button" class="btn btn-outline-primary btn-sm" data-ne-action="view" data-ne-source="${String(source || 'gateway')}" data-ne-id="${docToken}">Ver</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-ne-action="print" data-ne-source="${String(source || 'gateway')}" data-ne-id="${docToken}">Imprimir</button>
              </div>
            </div>
          `;
        }).join('');
      })
      .catch(() => {
        console.info('[P15][nota_evolucion] list fallback -> local', { reason: 'gateway_legacy_failed' });
        renderLocal();
      });
  };

  const resolveNoteCaptureAttachment = (payload) => {
    const attachments = (payload && typeof payload === 'object' && typeof payload.attachments === 'object')
      ? payload.attachments
      : null;
    const list = Array.isArray(attachments?.note_capture) ? attachments.note_capture : [];
    if (!list.length) return null;
    for (const entry of list) {
      if (!entry || typeof entry !== 'object') continue;
      const previewUrl = String(entry.preview_url || '').trim();
      if (!previewUrl) continue;
      return {
        preview_url: previewUrl,
        document_id: String(entry.document_id || '').trim() || null,
        document_uuid: String(entry.document_uuid || '').trim() || null,
        note_capture_token: String(entry.note_capture_token || '').trim() || null
      };
    }
    return null;
  };

  const renderDocNoteCapture = (payload) => {
    if (!els.docNoteCaptureWrap || !els.docNoteCaptureImg || !els.docNoteCaptureLink) return;
    const attachment = resolveNoteCaptureAttachment(payload);
    if (!attachment) {
      els.docNoteCaptureWrap.classList.add('d-none');
      els.docNoteCaptureImg.setAttribute('src', '');
      els.docNoteCaptureLink.setAttribute('href', '#');
      return;
    }
    const rawUrl = String(attachment.preview_url || '').trim();
    const normalizedUrl = /^https?:\/\//i.test(rawUrl)
      ? rawUrl
      : (rawUrl.startsWith('/') ? rawUrl : `/${rawUrl.replace(/^\/+/, '')}`);
    if (!normalizedUrl) {
      els.docNoteCaptureWrap.classList.add('d-none');
      els.docNoteCaptureImg.setAttribute('src', '');
      els.docNoteCaptureLink.setAttribute('href', '#');
      return;
    }
    els.docNoteCaptureImg.setAttribute('src', normalizedUrl);
    els.docNoteCaptureLink.setAttribute('href', normalizedUrl);
    els.docNoteCaptureWrap.classList.remove('d-none');
  };

  const escapeHtml = (value) => String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
  const cleanDocValue = (value) => {
    const text = String(value || '').trim();
    if (!text || text === '--') return '';
    return text;
  };
  const normalizeAssetUrl = (raw) => {
    const text = String(raw || '').trim();
    if (!text) return '';
    if (/^https?:\/\//i.test(text)) return text;
    if (/^data:image\//i.test(text)) return text;
    return text.startsWith('/') ? text : `/${text.replace(/^\/+/, '')}`;
  };
  const formatPrescriptionDate = (raw) => {
    const text = String(raw || '').trim();
    if (!text) return '';
    const dt = new Date(text);
    if (Number.isNaN(dt.getTime())) return cleanDocValue(text);
    return dt.toLocaleDateString('es-MX', { year: 'numeric', month: '2-digit', day: '2-digit' });
  };
  const isPrescriptionPayload = (payload) => {
    const rx = (payload && typeof payload === 'object' && payload.prescription && typeof payload.prescription === 'object')
      ? payload.prescription
      : null;
    return !!rx;
  };
  const resolvePrescriptionConsultorios = (snapshot = {}) => {
    const list = [];
    if (Array.isArray(snapshot.consultorios)) {
      snapshot.consultorios.forEach((item) => {
        if (!item || typeof item !== 'object') return;
        list.push({
          nombre: cleanDocValue(item.nombre || item.name || ''),
          domicilio: cleanDocValue(item.domicilio || item.address || ''),
          telefono: cleanDocValue(item.telefono || item.phone || '')
        });
      });
    }
    if (!list.length && snapshot.consultorio && typeof snapshot.consultorio === 'object') {
      const single = snapshot.consultorio;
      list.push({
        nombre: cleanDocValue(single.nombre || single.name || ''),
        domicilio: cleanDocValue(single.domicilio || single.address || ''),
        telefono: cleanDocValue(single.telefono || single.phone || '')
      });
    }
    return list
      .map((item) => ({
        nombre: cleanDocValue(item.nombre),
        domicilio: cleanDocValue(item.domicilio),
        telefono: cleanDocValue(item.telefono)
      }))
      .filter((item) => item.nombre || item.domicilio || item.telefono)
      .slice(0, 3);
  };
  const renderPrescriptionReadLayout = (payload) => {
    if (!els.docRxLayout) return false;
    if (!isPrescriptionPayload(payload)) return false;
    const rx = payload.prescription || {};
    const snapshot = (payload && typeof payload.snapshot === 'object' && payload.snapshot) ? payload.snapshot : {};
    const paciente = (snapshot && typeof snapshot.paciente === 'object' && snapshot.paciente) ? snapshot.paciente : {};
    const medico = (snapshot && typeof snapshot.medico === 'object' && snapshot.medico) ? snapshot.medico : {};
    const branding = (snapshot && typeof snapshot.branding === 'object' && snapshot.branding) ? snapshot.branding : {};
    const items = Array.isArray(rx.items) ? rx.items : [];
    const doctorLogo = normalizeAssetUrl(branding.doctor_logo_url);
    const groupLogo = normalizeAssetUrl(branding.group_logo_url);
    const doctorName = cleanDocValue(medico.nombre || medico.nombre_completo);
    const doctorSpecialty = cleanDocValue(medico.especialidad);
    const doctorCedula = cleanDocValue(medico.cedula || medico.cedula_profesional);
    const patientName = cleanDocValue(paciente.nombre || paciente.nombre_completo);
    const patientAge = cleanDocValue(paciente.edad);
    const patientSex = cleanDocValue(paciente.sexo);
    const emittedDate = formatPrescriptionDate(snapshot.generated_at || payload.event_datetime || '');
    const observaciones = cleanDocValue(rx.observaciones);
    const consultorios = resolvePrescriptionConsultorios(snapshot);
    const patientMeta = [patientAge ? `Edad: ${patientAge}` : '', patientSex ? `Sexo: ${patientSex}` : ''].filter(Boolean).join(' · ');
    const logosHtml = [doctorLogo, groupLogo].filter(Boolean).map((url, idx) => (
      `<img class="ne-rx-read-logo" src="${escapeHtml(url)}" alt="${idx === 0 ? 'Logo médico' : 'Logo grupo médico'}">`
    )).join('');
    const itemsHtml = items.length
      ? items.map((item, index) => {
        const medicamento = cleanDocValue(item?.medicamento) || `Medicamento ${index + 1}`;
        const lineaDatos = [
          cleanDocValue(item?.dosis) ? `Dosis: ${cleanDocValue(item?.dosis)}` : '',
          cleanDocValue(item?.via) ? `Vía: ${cleanDocValue(item?.via)}` : '',
          cleanDocValue(item?.frecuencia || item?.periodicidad) ? `Frecuencia: ${cleanDocValue(item?.frecuencia || item?.periodicidad)}` : '',
          cleanDocValue(item?.duracion) ? `Duración: ${cleanDocValue(item?.duracion)}` : ''
        ].filter(Boolean).join(' · ');
        const indicaciones = cleanDocValue(item?.indicaciones);
        return `
          <li class="ne-rx-read-item">
            <div class="ne-rx-read-item-title">${escapeHtml(medicamento)}</div>
            ${lineaDatos ? `<div class="ne-rx-read-item-meta">${escapeHtml(lineaDatos)}</div>` : ''}
            ${indicaciones ? `<div class="ne-rx-read-item-note">${escapeHtml(indicaciones)}</div>` : ''}
          </li>
        `;
      }).join('')
      : '<li class="ne-rx-read-item"><div class="ne-rx-read-item-note">Sin medicamentos registrados</div></li>';
    const consultoriosHtml = consultorios.length
      ? consultorios.map((item) => {
        const lines = [item.nombre, item.domicilio, item.telefono].filter(Boolean);
        return `<div class="ne-rx-read-footer-item">${escapeHtml(lines.join(' · '))}</div>`;
      }).join('')
      : '<div class="ne-rx-read-footer-item text-muted">Información de consultorio no disponible.</div>';
    els.docRxLayout.innerHTML = `
      <article class="ne-rx-read-card">
        <header class="ne-rx-read-header">
          ${logosHtml ? `<div class="ne-rx-read-logos">${logosHtml}</div>` : ''}
          <div class="ne-rx-read-doctor">
            ${doctorName ? `<div class="ne-rx-read-doctor-name">${escapeHtml(doctorName)}</div>` : ''}
            ${doctorSpecialty ? `<div class="ne-rx-read-doctor-meta">${escapeHtml(doctorSpecialty)}</div>` : ''}
            ${doctorCedula ? `<div class="ne-rx-read-doctor-meta">Cédula: ${escapeHtml(doctorCedula)}</div>` : ''}
          </div>
        </header>
        <section class="ne-rx-read-patient">
          <div class="ne-rx-read-patient-name">${escapeHtml(patientName || 'Paciente')}</div>
          <div class="ne-rx-read-patient-meta">
            ${patientMeta ? escapeHtml(patientMeta) : ''}
            ${emittedDate ? `${patientMeta ? ' · ' : ''}Fecha: ${escapeHtml(emittedDate)}` : ''}
          </div>
        </section>
        <section class="ne-rx-read-body">
          <div class="ne-rx-read-rp">Rp.</div>
          <ol class="ne-rx-read-list">${itemsHtml}</ol>
        </section>
        ${observaciones ? `
          <section class="ne-rx-read-observaciones">
            <div class="ne-rx-read-section-title">Observaciones</div>
            <div class="ne-rx-read-observaciones-text">${escapeHtml(observaciones)}</div>
          </section>
        ` : ''}
        <section class="ne-rx-read-signature">
          <div class="ne-rx-read-sign-line"></div>
          ${doctorName ? `<div class="ne-rx-read-sign-name">${escapeHtml(doctorName)}</div>` : ''}
          ${doctorCedula ? `<div class="ne-rx-read-sign-meta">Cédula: ${escapeHtml(doctorCedula)}</div>` : ''}
        </section>
        <footer class="ne-rx-read-footer">
          ${consultoriosHtml}
        </footer>
      </article>
    `;
    return true;
  };

  const openDocModal = (text, options = {}) => {
    if (!els.docText || !els.docModal) return;
    els.docText.value = text || '';
    const payload = options?.payload || null;
    const usePrescriptionLayout = renderPrescriptionReadLayout(payload);
    if (els.docModalTitle) {
      els.docModalTitle.textContent = usePrescriptionLayout ? 'Receta médica' : 'Nota de evolución';
    }
    if (els.docRxLayout) {
      els.docRxLayout.classList.toggle('d-none', !usePrescriptionLayout);
    }
    if (els.docText) {
      els.docText.classList.toggle('d-none', usePrescriptionLayout);
    }
    if (usePrescriptionLayout) {
      renderDocNoteCapture(null);
    } else {
      renderDocNoteCapture(payload);
    }
    try {
      const modal = bootstrap?.Modal?.getOrCreateInstance ? bootstrap.Modal.getOrCreateInstance(els.docModal) : null;
      modal?.show();
    } catch (_) {}
  };

  const printText = (text) => {
    const w = window.open('', '_blank');
    if (!w) return;
    w.document.write(`<pre style="white-space:pre-wrap;font-family:ui-monospace,Menlo,Consolas,monospace;font-size:12px">${(text || '').replace(/</g,'&lt;')}</pre>`);
    w.document.close();
    w.focus();
    w.print();
  };

  const syncPronostico = () => {
    const isOther = (els.pronostico?.value || '') === 'otro';
    if (!els.pronosticoTxt) return;
    els.pronosticoTxt.disabled = !isOther;
    if (!isOther) els.pronosticoTxt.value = '';
  };

  const showErrors = (messages) => {
    if (!els.errors) return;
    if (!messages || messages.length === 0) {
      els.errors.classList.add('d-none');
      els.errors.textContent = '';
      return;
    }
    els.errors.classList.remove('d-none');
    els.errors.innerHTML = `<strong>Faltan campos para generar:</strong><ul class="mb-0">${messages.map(m => `<li>${m}</li>`).join('')}</ul>`;
  };

  const generateNote = () => {
    const payload = window.buildEvolutionNotePayload();
    const errs = validate(payload);
    showErrors(errs);
    if (errs.length) return;

    const patient = getPatient();
    const actor = getDoctor();
    const encounterKey = (typeof window.getActiveEncounterKey === 'function')
      ? String(window.getActiveEncounterKey() || '').trim()
      : '';
    const context = {
      patient_id: patient.patient_id,
      canonical_patient_id: patient.canonical_patient_id || null,
      encounter_id: null,
      hospital_stay_id: null,
      care_setting: payload.ambito || 'consulta',
      service: null
    };
    if (encounterKey) {
      context.encounter_key = encounterKey;
    } else {
      console.info('[P15][nota_evolucion] save without encounter', {
        patient_id: patient.patient_id
      });
    }

    const noteCaptureTokenForConsume = String(payload?.attachments?.note_capture?.[0]?.note_capture_token || '').trim();
    const args = { type: 'nota_evolucion', context, payload, actor };
    const consumeQrTokenAfterNoteSave = async (token, document) => {
      const safeToken = String(token || '').trim();
      if (!safeToken) return;
      const noteDocumentId = String(document?.document_db_id ?? document?.id ?? '').trim();
      const noteDocumentUuid = String(document?.document_id ?? document?.document_uuid ?? '').trim();
      if (!noteDocumentId && !noteDocumentUuid) return;
      try {
        const resp = await fetch(`/api/clinical/index.php/note-capture-tokens/${encodeURIComponent(safeToken)}/consume`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json'
          },
          credentials: 'same-origin',
          body: JSON.stringify({
            note_document_id: noteDocumentId || null,
            note_document_uuid: noteDocumentUuid || null
          })
        });
        if (!resp.ok) {
          console.info('[P15][nota_evolucion] consume note capture rejected', {
            token: safeToken,
            status: resp.status
          });
        }
      } catch (consumeError) {
        console.info('[P15][nota_evolucion] consume note capture failed', {
          token: safeToken,
          reason: String(consumeError?.message || 'consume_failed')
        });
      }
    };

    const fallbackToLocal = () => {
      const text = window.buildEvolutionNoteRenderedText(payload, context);
      const patientKey = patient.patient_id || 'anon';
      const list = loadNotes(patientKey);
      const ambLabel = payload.ambito === 'urgencias' ? 'Urgencias' : payload.ambito === 'hospitalizacion' ? 'Hospitalización' : 'Consulta';
      const entry = {
        id: `ne_${Date.now()}`,
        created_at: new Date().toISOString(),
        ambito: payload.ambito,
        ambito_label: ambLabel,
        title: window.buildEvolutionNoteSummary(payload),
        summary: window.buildEvolutionNoteSummary(payload),
        payload,
        document_text: text,
        signed: false
      };
      list.unshift(entry);
      saveNotes(patientKey, list);
      renderTimeline();
      openDocModal(text, { payload });
      try{
        window.mxRegisterEncounterActivity?.('nota_evolucion_guardada_local', {
          encounterKey: context.encounter_key,
          patientId: context.patient_id,
          source: 'nota_evolucion_fallback_local'
        });
      }catch(_){}
    };

    api.saveClinicalDocument(args)
      .then(({ document, source }) => {
        console.info('[P15][nota_evolucion] save source', { source: String(source || 'unknown') });
        renderTimeline();
        openDocModal(document?.content?.rendered_text || '', { payload: document?.content?.payload || null });
        consumeQrTokenAfterNoteSave(noteCaptureTokenForConsume, document);
        try{
          window.mxRegisterEncounterActivity?.('nota_evolucion_guardada', {
            encounterKey: context.encounter_key,
            patientId: context.patient_id,
            source: source === 'legacy' ? 'nota_evolucion_legacy' : 'nota_evolucion_api'
          });
        }catch(_){}
      })
      .catch((e) => {
        console.info('[P15][nota_evolucion] save fallback -> local', { reason: 'gateway_legacy_failed' });
        showErrors([`No se pudo guardar en capa canónica/legacy (${e?.message || 'error'}). Se guardó localmente.`]);
        fallbackToLocal();
      });
  };

  const renderRxModal = () => {
    if (!els.rxGrid) return;
    const ctx = resolveRecetaRuntimeContext();
    const patientKey = ctx.patient_id;
    const rx = getRxDraft(patientKey);
    const meds = Array.isArray(rx.medicamentos) ? rx.medicamentos : [];
    els.rxGrid.innerHTML = meds.map((m, idx) => rxRowHtml(idx, m)).join('') || rxRowHtml(0, {});
    if (els.rxFeedback) {
      els.rxFeedback.classList.add('d-none');
      els.rxFeedback.classList.remove('text-muted', 'text-success', 'text-danger');
      els.rxFeedback.textContent = '';
    }
    setRxOpenDocumentAction('');
  };

  const rxRowHtml = (idx, m) => `
    <div class="ne-rx-row" data-ne-rx-row="${idx}">
      <input class="form-control form-control-sm" placeholder="Medicamento" data-ne-rx="medicamento" value="${(m.medicamento || '').replace(/\"/g,'&quot;')}">
      <input class="form-control form-control-sm" placeholder="Dosis" data-ne-rx="dosis" value="${(m.dosis || '').replace(/\"/g,'&quot;')}">
      <input class="form-control form-control-sm" placeholder="Vía" data-ne-rx="via" value="${(m.via || '').replace(/\"/g,'&quot;')}">
      <input class="form-control form-control-sm" placeholder="Periodicidad" data-ne-rx="periodicidad" value="${(m.periodicidad || '').replace(/\"/g,'&quot;')}">
      <input class="form-control form-control-sm" placeholder="Duración" data-ne-rx="duracion" value="${(m.duracion || '').replace(/\"/g,'&quot;')}">
      <input class="form-control form-control-sm" placeholder="Indicaciones" data-ne-rx="indicaciones" value="${(m.indicaciones || '').replace(/\"/g,'&quot;')}">
      <button type="button" class="btn btn-outline-danger btn-sm ne-rx-del" data-ne-rx-del aria-label="Eliminar">&times;</button>
    </div>
  `;

  const collectRxModal = () => {
    if (!els.rxGrid) return [];
    const rows = Array.from(els.rxGrid.querySelectorAll('.ne-rx-row'));
    return rows.map(r => {
      const get = (k) => r.querySelector(`[data-ne-rx="${k}"]`)?.value?.trim() || '';
      return {
        medicamento: get('medicamento'),
        dosis: get('dosis'),
        via: get('via'),
        periodicidad: get('periodicidad'),
        duracion: get('duracion'),
        indicaciones: get('indicaciones')
      };
    }).filter(m => Object.values(m).some(v => v));
  };
  const setRxFeedback = (message, tone = 'muted') => {
    if (!els.rxFeedback) return;
    const text = String(message || '').trim();
    els.rxFeedback.classList.remove('d-none', 'text-muted', 'text-success', 'text-danger');
    if (!text) {
      els.rxFeedback.classList.add('d-none');
      els.rxFeedback.textContent = '';
      return;
    }
    els.rxFeedback.classList.add(
      tone === 'success' ? 'text-success' : (tone === 'error' ? 'text-danger' : 'text-muted')
    );
    els.rxFeedback.textContent = text;
  };
  const resolveSavedPrescriptionToken = (document) => {
    if (!document || typeof document !== 'object') return '';
    const candidates = [
      document.document_uuid,
      document.document_id,
      document.id,
      document.document_db_id,
      document.uuid
    ];
    for (const token of candidates) {
      const safe = String(token || '').trim();
      if (safe) return safe;
    }
    return '';
  };
  const setRxOpenDocumentAction = (token) => {
    if (!els.rxOpenDoc) return;
    const safeToken = String(token || '').trim();
    if (!safeToken) {
      els.rxOpenDoc.classList.add('d-none');
      els.rxOpenDoc.removeAttribute('data-rx-document-token');
      return;
    }
    els.rxOpenDoc.classList.remove('d-none');
    els.rxOpenDoc.setAttribute('data-rx-document-token', safeToken);
  };
  const resolveRecetaAppointmentId = (patientId, encounterKey) => {
    const bar = document.getElementById('mm-p10-bar');
    const fromBar = String(bar?.dataset?.appointmentId || bar?.getAttribute?.('data-appointment-id') || '').trim();
    if (fromBar) return fromBar;
    const encounters = window.mxmedStore?.activeEncounters;
    if (!encounterKey || !encounters || typeof encounters !== 'object') return '';
    const entry = encounters[encounterKey];
    if (!entry || String(entry.patient_id || '').trim() !== patientId) return '';
    return String(entry.appointment_id || entry.appointmentId || '').trim();
  };
  const normalizeRecetaText = (value) => String(value || '').trim();
  const isDefaultDoctorLabel = (name) => {
    const normalized = normalize(String(name || ''));
    return normalized === 'medico' || normalized === 'doctor' || normalized === '';
  };
  const resolveRecetaRuntimeContext = () => {
    const patient = getPatient();
    const actor = getDoctor();
    const activePatientId = (typeof window.resolveActivePatientId === 'function')
      ? normalizeRecetaText(window.resolveActivePatientId())
      : normalizeRecetaText(window.mxmedStore?.currentPatientId || window.mxmedStore?.activePatientId);
    const hasActivePatientContext = activePatientId !== '';
    const patientId = normalizeRecetaText(activePatientId || patient.patient_id);
    const encounterKey = (typeof window.getActiveEncounterKey === 'function')
      ? normalizeRecetaText(window.getActiveEncounterKey())
      : '';
    const appointmentId = resolveRecetaAppointmentId(patientId, encounterKey);
    const doctorName = normalizeRecetaText(actor.nombre_completo);
    const doctorCedula = normalizeRecetaText(actor.cedula_profesional);
    const doctorEspecialidad = normalizeRecetaText(actor.especialidad);
    return {
      patient,
      actor,
      patient_id: patientId,
      has_active_patient_context: hasActivePatientContext,
      encounter_key: encounterKey,
      appointment_id: appointmentId,
      medico: {
        user_id: normalizeRecetaText(actor.user_id) || 'user',
        nombre: isDefaultDoctorLabel(doctorName) ? '' : doctorName,
        cedula: doctorCedula === '--' ? '' : doctorCedula,
        especialidad: doctorEspecialidad === '--' ? '' : doctorEspecialidad
      }
    };
  };
  const mapValidPrescriptionItems = (meds) => {
    const rows = Array.isArray(meds) ? meds : [];
    return rows
      .map((item) => ({
        medicamento: normalizeRecetaText(item?.medicamento),
        dosis: normalizeRecetaText(item?.dosis),
        via: normalizeRecetaText(item?.via),
        frecuencia: normalizeRecetaText(item?.periodicidad),
        periodicidad: normalizeRecetaText(item?.periodicidad),
        duracion: normalizeRecetaText(item?.duracion),
        indicaciones: normalizeRecetaText(item?.indicaciones)
      }))
      .filter((item) => item.medicamento !== '');
  };
  const validateRecetaBlockingErrors = (ctx, validItems) => {
    const errors = [];
    if (!ctx?.has_active_patient_context || !normalizeRecetaText(ctx?.patient_id)) errors.push('Falta paciente activo.');
    if (!normalizeRecetaText(ctx?.medico?.nombre)) errors.push('Falta nombre del médico.');
    if (!normalizeRecetaText(ctx?.medico?.cedula)) errors.push('Falta cédula profesional.');
    if (!Array.isArray(validItems) || validItems.length < 1) errors.push('Falta al menos un medicamento válido.');
    return errors;
  };
  const buildRecetaSnapshot = (ctx) => {
    const patient = ctx?.patient || {};
    const medico = ctx?.medico || {};
    return {
      paciente: {
        patient_id: normalizeRecetaText(ctx?.patient_id),
        nombre: normalizeRecetaText(patient.nombre_completo),
        nombre_completo: normalizeRecetaText(patient.nombre_completo),
        edad: normalizeRecetaText(patient.edad),
        fecha_nacimiento: null,
        sexo: normalizeRecetaText(patient.sexo)
      },
      medico: {
        user_id: normalizeRecetaText(medico.user_id),
        nombre: normalizeRecetaText(medico.nombre),
        nombre_completo: normalizeRecetaText(medico.nombre),
        cedula: normalizeRecetaText(medico.cedula),
        cedula_profesional: normalizeRecetaText(medico.cedula),
        especialidad: normalizeRecetaText(medico.especialidad)
      },
      consultorio: {
        id: null,
        nombre: null,
        domicilio: null,
        telefono: null
      },
      branding: {
        doctor_logo_url: null,
        group_logo_url: null
      },
      generated_at: new Date().toISOString()
    };
  };
  const refreshHistorialEmbedAfterPrescriptionSave = () => {
    try {
      const iframe = document.getElementById('mm-embed-historial');
      if (!iframe) return;
      const src = String(iframe.getAttribute('src') || '').trim();
      if (!src || src.indexOf('/modules/clinical/ui/historial.php') === -1) return;
      const next = `${src}${src.indexOf('?') !== -1 ? '&' : '?'}host_rx_refresh=${Date.now()}`;
      iframe.setAttribute('src', next);
    } catch (_) {}
  };
  const hideRecetaModal = () => {
    const modalEl = document.getElementById('modalReceta');
    if (!modalEl) return;
    try {
      const modal = bootstrap?.Modal?.getOrCreateInstance ? bootstrap.Modal.getOrCreateInstance(modalEl) : null;
      modal?.hide();
    } catch (_) {}
  };

  // Listeners
  els.refresh?.addEventListener('click', renderReadonly);
  els.pronostico?.addEventListener('change', syncPronostico);
  els.generate?.addEventListener('click', generateNote);

  citasBlock?.addEventListener('click', openCitasModalIfMissing);
  citasBlock?.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      openCitasModalIfMissing();
    }
  });
  citasSave?.addEventListener('click', () => {
    setClinicalCitations(citasMotivo?.value || '', citasPadecimiento?.value || '');
    renderReadonly();
    try{
      window.mxRegisterEncounterActivity?.('nota_evolucion_citas_actualizadas', {
        source: 'nota_evolucion_citas_modal'
      });
    }catch(_){}
    try {
      const modal = bootstrap?.Modal?.getInstance ? bootstrap.Modal.getInstance(citasModal) : null;
      modal?.hide();
    } catch (_) {}
  });

  // Timeline actions
  els.timeline?.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-ne-action]');
    if (!btn) return;
    const id = btn.getAttribute('data-ne-id');
    const src = btn.getAttribute('data-ne-source') || 'local';
    const action = btn.getAttribute('data-ne-action');

    if (src !== 'local') {
      api.getClinicalDocument(id, { preferredSource: src })
        .then(({ document }) => {
          const text = document?.content?.rendered_text || '';
          const payload = document?.content?.payload || null;
          if (action === 'view') openDocModal(text, { payload });
          if (action === 'print') printText(text);
        })
        .catch(() => {});
      return;
    }

    const patientKey = getPatient().patient_id;
    const notes = loadNotes(patientKey);
    const note = notes.find(n => n.id === id);
    if (!note) return;
    if (action === 'view') openDocModal(note.document_text, { payload: note.payload || null });
    if (action === 'print') printText(note.document_text);
  });

  // Doc actions
  els.docCopy?.addEventListener('click', async () => {
    const text = els.docText?.value || '';
    try {
      await navigator.clipboard.writeText(text);
    } catch (_) {
      els.docText?.select?.();
      document.execCommand?.('copy');
    }
  });
  els.docPrint?.addEventListener('click', () => printText(els.docText?.value || ''));

  // Rx modal
  document.getElementById('modalReceta')?.addEventListener('show.bs.modal', renderRxModal);
  els.rxAdd?.addEventListener('click', () => {
    if (!els.rxGrid) return;
    const idx = els.rxGrid.querySelectorAll('.ne-rx-row').length;
    els.rxGrid.insertAdjacentHTML('beforeend', rxRowHtml(idx, {}));
  });
  els.rxGrid?.addEventListener('click', (e) => {
    const del = e.target.closest('[data-ne-rx-del]');
    if (!del) return;
    del.closest('.ne-rx-row')?.remove();
  });
  els.rxSave?.addEventListener('click', async () => {
    const recetaCtx = resolveRecetaRuntimeContext();
    const patientKey = recetaCtx.patient_id;
    const meds = collectRxModal();
    setRxDraft(patientKey, meds);
    renderRxSummary(patientKey);
    const validItems = mapValidPrescriptionItems(meds);
    const blockingErrors = validateRecetaBlockingErrors(recetaCtx, validItems);
    if (blockingErrors.length) {
      setRxFeedback(blockingErrors.join(' '), 'error');
      setRxOpenDocumentAction('');
      return;
    }
    const patient = recetaCtx.patient;
    const actor = recetaCtx.actor;
    const encounterKey = recetaCtx.encounter_key;
    const appointmentId = recetaCtx.appointment_id;
    const payload = {
      contract_version: 1,
      prescription: {
        items: validItems,
        observaciones: ''
      },
      snapshot: buildRecetaSnapshot(recetaCtx)
    };
    const context = {
      patient_id: recetaCtx.patient_id,
      canonical_patient_id: patient.canonical_patient_id || null,
      encounter_id: null,
      hospital_stay_id: null,
      care_setting: 'consulta',
      service: null
    };
    if (encounterKey) context.encounter_key = encounterKey;
    if (appointmentId) context.appointment_id = appointmentId;
    setRxFeedback('Guardando receta clínica…');
    if (els.rxSave) {
      els.rxSave.disabled = true;
      els.rxSave.textContent = 'Guardando...';
    }
    try {
      const { source, document } = await api.saveClinicalDocument({
        type: 'prescription',
        context,
        payload,
        actor
      });
      const savedToken = resolveSavedPrescriptionToken(document);
      setRxFeedback('Receta guardada correctamente.', 'success');
      setRxOpenDocumentAction(savedToken);
      try{
        window.mxRegisterEncounterActivity?.('receta_guardada', {
          encounterKey,
          patientId: patient.patient_id,
          source: source === 'legacy' ? 'receta_api_legacy' : 'receta_api'
        });
      }catch(_){}
      try{
        window.dispatchEvent(new CustomEvent('mxmed:clinical-document-created', {
          detail: {
            patient_id: recetaCtx.patient_id,
            encounter_key: encounterKey || '',
            appointment_id: appointmentId || '',
            document_type: 'prescription',
            source: 'actividad_clinica_receta',
            document_ref: savedToken || ''
          }
        }));
      }catch(_){}
      renderTimeline();
      refreshHistorialEmbedAfterPrescriptionSave();
    } catch (err) {
      setRxFeedback(`No se pudo guardar la receta clínica (${err?.message || 'error'}).`, 'error');
      setRxOpenDocumentAction('');
    } finally {
      if (els.rxSave) {
        els.rxSave.disabled = false;
        els.rxSave.textContent = 'Guardar receta';
      }
    }
    try{
      window.mxRegisterEncounterActivity?.('receta_actualizada', {
        patientId: patientKey,
        source: 'receta_modal_local'
      });
    }catch(_){}
  });
  els.rxOpenDoc?.addEventListener('click', (event) => {
    event.preventDefault();
    const token = String(els.rxOpenDoc?.getAttribute('data-rx-document-token') || '').trim();
    if (!token) return;
    const href = `/modules/clinical/ui/document.php?uuid=${encodeURIComponent(token)}`;
    try {
      window.open(href, '_blank', 'noopener');
    } catch (_) {
      window.location.href = href;
    }
  });
  // Hook pendientes (cuando tengan confirmación de éxito explícita): estudios, documentos adjuntos y procedimientos.

  // Init
  syncPronostico();
  renderReadonly();
  renderTimeline();
  bindCitasTwoWaySync();
})();

// ====== Pacientes: tabs bloqueados hasta capturar nombre y género ======
(function(){
  const pane = document.getElementById('p-expediente');
  if(!pane) return;
  const tabs = Array.from(pane.querySelectorAll('.mm-tabs-row .nav-link'));
  const historialAtencionBtn = pane.querySelector('[data-action="open-historial-atencion"]');
  const tabsWrap = pane.querySelector('[data-exp-tabs]');
  if(!tabs.length) return;

  const nameInput = pane.querySelector('[data-pac-nombre]');
  const apellidoPaternoInput = pane.querySelector('[data-pac-apellido-paterno]');
  const apellidoMaternoInput = pane.querySelector('[data-pac-apellido-materno]');
  const genderInputs = Array.from(pane.querySelectorAll('input[name="pac-genero"]'));
  const ginecoItem = pane.querySelector('[data-tab-conditional="gineco"]');
  const ginecoLink = pane.querySelector('[data-tab-key="t-gineco"]');
  const dayError = pane.querySelector('[data-dg-day-error]');
  const genderExtra = pane.querySelector('[data-gen-extra]');
  const datosTabLink = pane.querySelector('[data-tab-key="t-datos"]');
  const datosTabPane = pane.querySelector('#t-datos');
  const expHeader = pane.querySelector('[data-role="exp-header"]');
  const expHeaderName = pane.querySelector('[data-role="exp-h-patient-name"]');
  const expHeaderAge = pane.querySelector('[data-role="exp-h-age"]');
  const expHeaderSex = pane.querySelector('[data-role="exp-h-sex"]');
  const expHeaderLastDx = pane.querySelector('[data-role="exp-h-last-dx"]');
  const expHeaderActiveWrap = pane.querySelector('[data-role="exp-h-active-wrap"]');
  const expHeaderActiveBadge = pane.querySelector('[data-role="exp-h-active-enc"]');
  const expHeaderOrigin = pane.querySelector('[data-role="exp-h-enc-origin"]');
  const expHeaderStart = pane.querySelector('[data-role="exp-h-enc-start"]');
  const expHeaderNeutral = pane.querySelector('[data-role="exp-h-neutral"]');
  const expHeaderStartBtn = pane.querySelector('[data-role="exp-h-start-enc-btn"]');
  const expHeaderCloseBtn = pane.querySelector('[data-role="exp-h-close-enc-btn"]');
  const expHeaderActiveStrip = pane.querySelector('[data-role="exp-h-active-strip"]');
  const expHeaderActiveStripScroll = pane.querySelector('[data-role="exp-h-active-strip-scroll"]');
  const p10StartBtn = document.querySelector('#mm-p10-bar [data-action="p10-start-encounter"]');
  const p10FinalizeBtn = document.querySelector('#mm-p10-bar [data-action="p10-finalize-encounter"]');
  const p10BarNode = document.getElementById('mm-p10-bar');
  const actividadClinicaModalEl = pane.querySelector('#modalActividadClinica');
  const actividadClinicaNotaModalEl = pane.querySelector('#modalActividadClinicaNota');
  const actividadClinicaNotaQrModalEl = pane.querySelector('#modalActividadClinicaNotaQr');
  const actividadClinicaAdjuntoModalEl = pane.querySelector('#modalActividadClinicaAdjunto');
  const actividadClinicaConsentModalEl = pane.querySelector('#modalActividadClinicaConsent');
  const actividadClinicaLauncherMainEl = pane.querySelector('[data-role="ac-launcher-main"]');
  const actividadClinicaLauncherProcPickerEl = pane.querySelector('[data-role="ac-launcher-proc-picker"]');
  const actividadClinicaLaunchBtns = Array.from(pane.querySelectorAll('[data-action="open-actividad-clinica"]'));
  const actividadClinicaNotaOpenQrBtn = pane.querySelector('[data-action="ac-nota-open-qr-capture"]');
  const actividadClinicaNotaQrStatusEl = pane.querySelector('[data-role="ac-nota-qr-status"]');
  const actividadClinicaNotasCard = pane.querySelector('[data-role="ac-notas-context-card"]');
  const actividadClinicaNotasStatusBadge = pane.querySelector('[data-role="ac-notas-status-badge"]');
  const actividadClinicaNotasStatusText = pane.querySelector('[data-role="ac-notas-status-text"]');
  const actividadClinicaNotasMotivo = pane.querySelector('[data-role="ac-notas-motivo"]');
  const actividadClinicaNotasEncounterMeta = pane.querySelector('[data-role="ac-notas-encounter-meta"]');
  const actividadClinicaNotasActions = pane.querySelector('[data-role="ac-notas-actions"]');
  const tratamientoAliasPanel = pane.querySelector('[data-role="trx-alias-panel"]');
  const ginecoHistoriaSection = pane.querySelector('[data-role="hc-gineco-subsection"]');
  const ginecoAliasPane = pane.querySelector('#t-gineco');
  let headerSyncToken = 0;
  let actividadClinicaContextSyncToken = 0;
  const activeEncounterLookupInFlight = new Map();
  let lastDayInvalid = false;
  actividadClinicaLaunchBtns.forEach((btn)=>{
    btn.removeAttribute('data-bs-toggle');
    btn.removeAttribute('data-bs-target');
  });
  const normalizeExpGender = (genero)=>{
    const raw = String(genero || '').trim();
    if(!raw) return '';
    const norm = raw.toLowerCase();
    if(['f', 'female', 'femenino', 'mujer'].includes(norm)) return 'F';
    if(['m', 'male', 'masculino', 'hombre'].includes(norm)) return 'M';
    if(['o', 'otro', 'other'].includes(norm)) return 'O';
    return raw.toUpperCase();
  };

  const setGenderAttr = (genero)=>{
    const normalized = normalizeExpGender(genero);
    if(normalized){ pane.setAttribute('data-exp-gender', normalized); }
    else { pane.removeAttribute('data-exp-gender'); }
  };
  const sanitizeText = (value)=> String(value || '').replace(/\s+/g, ' ').trim();
  const clinicalTabTargets = {
    datos: '#t-datos',
    historia: '#t-historia',
    historialAtencion: '#t-historial-atencion',
    notas: '#t-notas',
    estudios: '#t-estudios',
    consent: '#t-consent'
  };
  const findClinicalTabTrigger = (target)=>{
    const safeTarget = sanitizeText(target);
    if(!safeTarget) return null;
    return pane.querySelector(`.mm-tabs-row .nav-link[data-bs-target="${safeTarget}"]`);
  };
  const getMotivoTextarea = ()=>{
    const byDataRole = pane.querySelector('[data-pac-motivo-consulta]');
    if(byDataRole){
      byDataRole.dataset.motivoSource = 'data-pac-motivo-consulta';
      return byDataRole;
    }
    const byId = pane.querySelector('#ne_citas_motivo');
    if(byId){
      byId.dataset.motivoSource = 'id:ne_citas_motivo';
      return byId;
    }
    const historiaPane = pane.querySelector('#t-historia');
    if(!historiaPane) return null;
    const fields = Array.from(historiaPane.querySelectorAll('textarea.form-control'));
    for(const field of fields){
      const id = field.getAttribute('id');
      if(id){
        const label = historiaPane.querySelector(`label[for="${id.replace(/"/g, '\\"')}"]`);
        if(label && /motivo\s+de\s+la\s+consulta/i.test(sanitizeText(label.textContent))){
          field.dataset.motivoSource = 'label-for';
          return field;
        }
      }
      const wrap = field.closest('.col-md-4, .col-md-6, .col-md-12, .col-12, div');
      const nearbyLabel = wrap ? wrap.querySelector('label.form-label') : null;
      if(nearbyLabel && /motivo\s+de\s+la\s+consulta/i.test(sanitizeText(nearbyLabel.textContent))){
        field.dataset.motivoSource = 'nearby-label';
        return field;
      }
    }
    return null;
  };
  const readMotivoConsulta = ()=>{
    const motivoField = getMotivoTextarea();
    const directValue = sanitizeText(motivoField?.value || '');
    if(directValue) return directValue;
    const motivoReadonly = sanitizeText(pane.querySelector('#ne_motivo_ro')?.textContent || '');
    if(motivoReadonly && motivoReadonly.toLowerCase() !== 'no registrado') return motivoReadonly;
    return '';
  };
  let motivoPrefillWriteLock = false;
  const ensureMotivoDraftByPatient = ()=>{
    if(!window.mxmedStore || typeof window.mxmedStore !== 'object'){
      window.mxmedStore = {};
    }
    if(!window.mxmedStore.motivoConsultaDraftByPatient || typeof window.mxmedStore.motivoConsultaDraftByPatient !== 'object'){
      window.mxmedStore.motivoConsultaDraftByPatient = {};
    }
    return window.mxmedStore.motivoConsultaDraftByPatient;
  };
  const ensureMotivoPrefillByPatient = ()=>{
    if(!window.mxmedStore || typeof window.mxmedStore !== 'object'){
      window.mxmedStore = {};
    }
    if(!window.mxmedStore.motivoConsultaPrefillByPatient || typeof window.mxmedStore.motivoConsultaPrefillByPatient !== 'object'){
      window.mxmedStore.motivoConsultaPrefillByPatient = {};
    }
    return window.mxmedStore.motivoConsultaPrefillByPatient;
  };
  const readMotivoDraftEntry = (patientId)=>{
    const pid = sanitizeText(patientId);
    if(!pid) return null;
    const map = ensureMotivoDraftByPatient();
    const entry = map[pid];
    if(!entry || typeof entry !== 'object'){
      return null;
    }
    return {
      value: sanitizeText(entry.value),
      source: sanitizeText(entry.source),
      manualTouched: entry.manualTouched === true
    };
  };
  const writeMotivoDraftEntry = (patientId, value, opts = {})=>{
    const pid = sanitizeText(patientId);
    if(!pid) return;
    const map = ensureMotivoDraftByPatient();
    map[pid] = {
      value: sanitizeText(value),
      source: sanitizeText(opts.source || ''),
      manualTouched: opts.manualTouched === true,
      updated_at: new Date().toISOString()
    };
  };
  const clearVisibleMotivoForPatientSwitch = ()=>{
    const field = getMotivoTextarea();
    if(field){
      field.value = '';
      delete field.dataset.motivoPrefillSource;
    }
    const motivoReadonly = pane.querySelector('#ne_motivo_ro');
    if(motivoReadonly){
      motivoReadonly.textContent = '';
    }
  };
  const captureCurrentMotivoDraftForPatient = (patientId)=>{
    const pid = sanitizeText(patientId);
    if(!pid) return;
    const field = getMotivoTextarea();
    if(!field) return;
    const value = sanitizeText(field.value);
    if(!value) return;
    const prev = readMotivoDraftEntry(pid);
    writeMotivoDraftEntry(pid, value, {
      source: 'patient_switch_capture',
      manualTouched: prev?.manualTouched === true
    });
  };
  const applyMotivoDraftForPatient = (patientId)=>{
    const pid = sanitizeText(patientId);
    if(!pid) return false;
    const field = getMotivoTextarea();
    if(!field) return false;
    const draft = readMotivoDraftEntry(pid);
    if(!draft || !draft.value) return false;
    motivoPrefillWriteLock = true;
    try{
      field.value = draft.value;
      field.dispatchEvent(new Event('input', { bubbles:true }));
    }finally{
      motivoPrefillWriteLock = false;
    }
    return true;
  };
  const buildMotivoPrefillSignature = (patientId, encounterContext = {})=>{
    const pid = sanitizeText(patientId);
    const encKey = sanitizeText(encounterContext.encounterKey || encounterContext.encounter_key);
    const apptId = sanitizeText(encounterContext.appointmentId || encounterContext.appointment_id);
    return `${pid}|enc:${encKey}|appt:${apptId}`;
  };
  const markMotivoPrefillAttempt = (patientId, signature)=>{
    const pid = sanitizeText(patientId);
    const sig = sanitizeText(signature);
    if(!pid || !sig) return;
    const map = ensureMotivoPrefillByPatient();
    map[pid] = sig;
  };
  const wasMotivoPrefillAttempted = (patientId, signature)=>{
    const pid = sanitizeText(patientId);
    const sig = sanitizeText(signature);
    if(!pid || !sig) return false;
    const map = ensureMotivoPrefillByPatient();
    return sanitizeText(map[pid]) === sig;
  };
  const resolveAppointmentIdFromEncounterData = (encounterData)=>{
    if(!encounterData || typeof encounterData !== 'object') return '';
    return sanitizeText(
      encounterData.appointment_id ||
      encounterData.appointmentId ||
      (encounterData.context && encounterData.context.appointment_id) ||
      (encounterData.links && encounterData.links.appointment_id)
    );
  };
  const fetchTimelineItemsForMotivoPrefill = async (patientId)=>{
    const pid = sanitizeText(patientId);
    if(!pid) return [];
    try{
      const resp = await fetch(`/api/clinical/index.php/patients/${encodeURIComponent(pid)}/timeline?include=agenda,clinical&limit=50`, {
        method: 'GET',
        headers: buildClinicalHeadersForHeader(),
        credentials: 'same-origin'
      });
      const json = await resp.json().catch(()=> null);
      const items = Array.isArray(json?.data?.items) ? json.data.items : [];
      return items.filter((item)=> item && typeof item === 'object');
    }catch(_){
      return [];
    }
  };
  const pickMotivoFromTimelineItems = (items, encounterContext = {})=>{
    const list = Array.isArray(items) ? items : [];
    const activeAppointmentId = sanitizeText(encounterContext.appointmentId || encounterContext.appointment_id);
    const agendaCandidates = list
      .map((item)=>{
        const agenda = (item && typeof item === 'object' && item.agenda && typeof item.agenda === 'object') ? item.agenda : null;
        if(!agenda) return null;
        const reasonText = sanitizeText(agenda.reason_text);
        if(!reasonText) return null;
        const links = (item.links && typeof item.links === 'object') ? item.links : {};
        const appointmentId = sanitizeText(links.appointment_id || item.appointment_id || agenda.appointment_id);
        const sortDateRaw = sanitizeText(item.sort_datetime || item.event_datetime || agenda.start_at || '');
        const sortMs = Number.isFinite(Date.parse(sortDateRaw)) ? Date.parse(sortDateRaw) : 0;
        return { reasonText, appointmentId, sortMs };
      })
      .filter(Boolean);

    if(activeAppointmentId){
      const byAppointment = agendaCandidates.find((item)=> item.appointmentId === activeAppointmentId);
      if(byAppointment){
        return {
          motivo: byAppointment.reasonText,
          source: 'agenda:encounter_appointment_match',
          appointment_id: byAppointment.appointmentId
        };
      }
    }

    if(!agendaCandidates.length){
      return null;
    }

    agendaCandidates.sort((a, b)=> b.sortMs - a.sortMs);
    const latest = agendaCandidates[0];
    if(!latest || !latest.reasonText){
      return null;
    }
    return {
      motivo: latest.reasonText,
      source: 'agenda:latest_appointment',
      appointment_id: latest.appointmentId || ''
    };
  };
  const resolvePrefillMotivoFromAgenda = async (patientId, encounterContext = {})=>{
    const pid = sanitizeText(patientId);
    if(!pid) return null;
    const context = Object.assign({}, encounterContext || {});
    let encounterKey = sanitizeText(context.encounterKey || context.encounter_key);
    let appointmentId = sanitizeText(context.appointmentId || context.appointment_id);

    if(!encounterKey && typeof fetchActiveEncounterKeyForHeader === 'function'){
      encounterKey = sanitizeText(await fetchActiveEncounterKeyForHeader(pid).catch(()=> ''));
    }
    if(encounterKey && !appointmentId && typeof getEncounterDataForHeader === 'function'){
      const encounterData = await getEncounterDataForHeader(encounterKey).catch(()=> null);
      appointmentId = resolveAppointmentIdFromEncounterData(encounterData);
    }

    const timelineItems = await fetchTimelineItemsForMotivoPrefill(pid);
    const picked = pickMotivoFromTimelineItems(timelineItems, {
      encounterKey,
      appointmentId
    });
    if(!picked || !sanitizeText(picked.motivo)){
      return null;
    }
    return {
      motivo: sanitizeText(picked.motivo),
      source: sanitizeText(picked.source || 'agenda'),
      appointment_id: sanitizeText(picked.appointment_id || appointmentId),
      encounter_key: encounterKey
    };
  };
  const maybeApplyContextualMotivoPrefill = async (patientId, encounterContext = {})=>{
    const pid = sanitizeText(patientId);
    if(!pid) return false;
    const field = getMotivoTextarea();
    if(!field) return false;

    const currentPid = sanitizeText(getActivePatientId());
    if(currentPid && currentPid !== pid) return false;

    const currentValue = sanitizeText(field.value);
    if(currentValue){
      return false;
    }

    const draftEntry = readMotivoDraftEntry(pid);
    if(draftEntry){
      if(draftEntry.manualTouched === true){
        return false;
      }
      if(draftEntry.value){
        motivoPrefillWriteLock = true;
        try{
          field.value = draftEntry.value;
          field.dispatchEvent(new Event('input', { bubbles:true }));
        }finally{
          motivoPrefillWriteLock = false;
        }
        return true;
      }
    }

    const signature = buildMotivoPrefillSignature(pid, encounterContext);
    if(wasMotivoPrefillAttempted(pid, signature)){
      return false;
    }
    markMotivoPrefillAttempt(pid, signature);

    const resolved = await resolvePrefillMotivoFromAgenda(pid, encounterContext).catch(()=> null);
    if(!resolved || !sanitizeText(resolved.motivo)){
      return false;
    }
    if(sanitizeText(field.value)){
      return false;
    }
    if(sanitizeText(getActivePatientId()) !== pid){
      return false;
    }

    motivoPrefillWriteLock = true;
    try{
      field.value = resolved.motivo;
      field.dataset.motivoPrefillSource = resolved.source || 'agenda';
      field.dispatchEvent(new Event('input', { bubbles:true }));
    }finally{
      motivoPrefillWriteLock = false;
    }
    writeMotivoDraftEntry(pid, resolved.motivo, { source: resolved.source || 'agenda', manualTouched: false });
    return true;
  };
  window.mxmedResolvePrefillMotivoFromAgenda = resolvePrefillMotivoFromAgenda;
  const getMinimumPatientProfileState = ()=>{
    const nombre = sanitizeText(pane.querySelector('[data-pac-nombre]')?.value);
    const primerApellido = sanitizeText(pane.querySelector('[data-pac-apellido-paterno]')?.value);
    const checkedGenderInput = pane.querySelector('input[name="pac-genero"]:checked');
    const genero = normalizeExpGender(
      checkedGenderInput?.value || pane.getAttribute('data-exp-gender')
    );
    const diaField = pane.querySelector('[data-dg-dia]');
    const mesField = pane.querySelector('[data-dg-mes]');
    const anioField = pane.querySelector('[data-dg-anio]');
    const dia = sanitizeText(diaField?.value);
    const mes = sanitizeText(mesField?.value);
    const anio = sanitizeText(anioField?.value);
    const motivo = readMotivoConsulta();
    // Motivo de consulta es dato clínico opcional (no bloquea entrada al flujo clínico).
    const complete = !!(nombre && primerApellido && genero && dia && mes && anio);
    return {
      complete,
      nombre,
      primerApellido,
      genero,
      dia,
      mes,
      anio,
      motivo,
      sexoSource: checkedGenderInput ? 'input[name="pac-genero"]:checked' : 'data-exp-gender',
      birthdateSource: [
        diaField ? 'data-dg-dia' : '',
        mesField ? 'data-dg-mes' : '',
        anioField ? 'data-dg-anio' : ''
      ].filter(Boolean).join('|') || 'none',
      motivoSource: String(getMotivoTextarea()?.dataset?.motivoSource || '').trim() || 'none'
    };
  };
  const hasMinimumPatientProfile = ()=>{
    const state = getMinimumPatientProfileState();
    return state.complete;
  };
  const showClinicalTab = (tabTarget, opts = {})=>{
    const btn = findClinicalTabTrigger(tabTarget);
    if(!btn) return false;
    const btnTarget = btn.getAttribute('data-bs-target');
    const safeTarget = sanitizeText(btnTarget);
    if(!safeTarget) return false;
    const BsTab = window.bootstrap && window.bootstrap.Tab;
    if(BsTab && opts.forceFallback !== true){
      try{
        if(typeof BsTab.getOrCreateInstance === 'function'){
          BsTab.getOrCreateInstance(btn).show();
        }else{
          (new BsTab(btn)).show();
        }
        return true;
      }catch(_){}
    }
    tabs.forEach((tabBtn)=>{
      const isCurrent = tabBtn === btn;
      tabBtn.classList.toggle('active', isCurrent);
      tabBtn.setAttribute('aria-selected', isCurrent ? 'true' : 'false');
    });
    const tabPanes = Array.from(pane.querySelectorAll('.tab-content .tab-pane'));
    tabPanes.forEach((tabPane)=> tabPane.classList.remove('show', 'active'));
    const paneTarget = pane.querySelector(safeTarget);
    if(paneTarget){
      paneTarget.classList.add('show', 'active');
      pane.dataset.activeTab = String(safeTarget || '').replace(/^#/, '').trim();
      return true;
    }
    return false;
  };
  const hideActividadClinicaModal = ()=>{
    if(!actividadClinicaModalEl) return;
    const BsModal = window.bootstrap && window.bootstrap.Modal;
    if(!BsModal) return;
    try{
      const modal = typeof BsModal.getOrCreateInstance === 'function'
        ? BsModal.getOrCreateInstance(actividadClinicaModalEl)
        : new BsModal(actividadClinicaModalEl);
      modal.hide();
    }catch(_){}
  };
  const showActividadClinicaModalById = (modalEl)=>{
    if(!modalEl) return false;
    const BsModal = window.bootstrap && window.bootstrap.Modal;
    if(!BsModal) return false;
    try{
      const modal = typeof BsModal.getOrCreateInstance === 'function'
        ? BsModal.getOrCreateInstance(modalEl)
        : new BsModal(modalEl);
      modal.show();
      return true;
    }catch(_){
      return false;
    }
  };
  const setActividadClinicaLauncherView = (view = 'main')=>{
    const safeView = sanitizeText(view).toLowerCase() === 'proc' ? 'proc' : 'main';
    if(actividadClinicaLauncherMainEl){
      actividadClinicaLauncherMainEl.classList.toggle('d-none', safeView !== 'main');
    }
    if(actividadClinicaLauncherProcPickerEl){
      actividadClinicaLauncherProcPickerEl.classList.toggle('d-none', safeView !== 'proc');
    }
  };
  const createActividadClinicaPortalMount = (modalEl, targetSelector, sourceSelector, opts = {})=>{
    if(!modalEl) return null;
    const targetEl = modalEl.querySelector(targetSelector);
    if(!targetEl) return null;
    const forceVisible = opts.forceVisible === true;
    const forceShowActive = opts.forceShowActive === true;
    let activeMount = null;
    const restore = ()=>{
      if(!activeMount || !activeMount.sourceEl || !activeMount.parentEl) return;
      const { sourceEl, parentEl, nextSibling, wasHidden, hadShowClass, hadActiveClass } = activeMount;
      if(nextSibling && nextSibling.parentNode === parentEl){
        parentEl.insertBefore(sourceEl, nextSibling);
      }else{
        parentEl.appendChild(sourceEl);
      }
      if(forceShowActive){
        sourceEl.classList.toggle('show', hadShowClass === true);
        sourceEl.classList.toggle('active', hadActiveClass === true);
      }
      if(wasHidden){
        sourceEl.classList.add('d-none');
      }
      activeMount = null;
    };
    modalEl.addEventListener('hidden.bs.modal', restore);
    const mount = ()=>{
      if(activeMount && activeMount.sourceEl && targetEl.contains(activeMount.sourceEl)){
        return true;
      }
      const sourceEl = pane.querySelector(sourceSelector);
      if(!sourceEl || !sourceEl.parentElement) return false;
      const wasHidden = sourceEl.classList.contains('d-none');
      activeMount = {
        sourceEl,
        parentEl: sourceEl.parentElement,
        nextSibling: sourceEl.nextSibling || null,
        wasHidden,
        hadShowClass: sourceEl.classList.contains('show'),
        hadActiveClass: sourceEl.classList.contains('active')
      };
      if(forceVisible){
        sourceEl.classList.remove('d-none');
      }
      if(forceShowActive){
        sourceEl.classList.add('show', 'active');
      }
      targetEl.appendChild(sourceEl);
      return true;
    };
    return { mount, restore };
  };
  const actividadClinicaNotaPortal = createActividadClinicaPortalMount(
    actividadClinicaNotaModalEl,
    '[data-role="ac-nota-modal-content"]',
    '#t-notas .ne-app[data-ne-section="nota_evolucion"]',
    { forceVisible: true }
  );
  const actividadClinicaAdjuntoPortal = createActividadClinicaPortalMount(
    actividadClinicaAdjuntoModalEl,
    '[data-role="ac-adjunto-modal-content"]',
    '#t-estudios [data-est-section-block="ingresar"]',
    { forceVisible: true }
  );
  const actividadClinicaConsentPortal = createActividadClinicaPortalMount(
    actividadClinicaConsentModalEl,
    '[data-role="ac-consent-modal-content"]',
    '#t-consent',
    { forceShowActive: true }
  );
  let notaClinicaModalTimelineObserver = null;
  const noteCaptureQrState = {
    token: '',
    status: '',
    expiresAt: '',
    uploadedAt: '',
    documentId: '',
    documentUuid: '',
    previewUrl: '',
    mobileUrl: '',
    cancelling: false,
    pollIntervalId: 0,
    pollTimeoutId: 0,
    countdownIntervalId: 0,
    startedAt: 0
  };
  const NOTE_CAPTURE_POLL_INTERVAL_MS = 4000;
  const NOTE_CAPTURE_MAX_DURATION_MS = 90000;
  const noteQrSelectors = {
    qrImage: '[data-role="ac-nota-qr-image"]',
    qrLink: '[data-role="ac-nota-qr-link"]',
    qrState: '[data-role="ac-nota-qr-state"]',
    countdown: '[data-role="ac-nota-qr-countdown"]',
    previewWrap: '[data-role="ac-nota-qr-preview-wrap"]',
    previewImage: '[data-role="ac-nota-qr-preview-image"]'
  };
  const noteQrElements = ()=>{
    if(!actividadClinicaNotaQrModalEl) return null;
    return {
      qrImage: actividadClinicaNotaQrModalEl.querySelector(noteQrSelectors.qrImage),
      qrLink: actividadClinicaNotaQrModalEl.querySelector(noteQrSelectors.qrLink),
      qrState: actividadClinicaNotaQrModalEl.querySelector(noteQrSelectors.qrState),
      countdown: actividadClinicaNotaQrModalEl.querySelector(noteQrSelectors.countdown),
      previewWrap: actividadClinicaNotaQrModalEl.querySelector(noteQrSelectors.previewWrap),
      previewImage: actividadClinicaNotaQrModalEl.querySelector(noteQrSelectors.previewImage)
    };
  };
  const hideNotaQrPreview = ()=>{
    const els = noteQrElements();
    if(!els) return;
    if(els.previewWrap) els.previewWrap.classList.add('d-none');
    if(els.previewImage) els.previewImage.setAttribute('src', '');
  };
  const setNotaQrMainStatus = (message, tone = 'muted')=>{
    if(!actividadClinicaNotaQrStatusEl) return;
    const text = sanitizeText(message);
    actividadClinicaNotaQrStatusEl.classList.remove('text-muted', 'text-success', 'text-danger', 'd-none');
    if(!text || tone !== 'success'){
      actividadClinicaNotaQrStatusEl.textContent = '';
      actividadClinicaNotaQrStatusEl.classList.add('d-none');
      return;
    }
    actividadClinicaNotaQrStatusEl.textContent = text;
    actividadClinicaNotaQrStatusEl.classList.add('text-success');
  };
  const stopNoteCaptureQrPolling = ()=>{
    if(noteCaptureQrState.pollIntervalId){
      window.clearInterval(noteCaptureQrState.pollIntervalId);
      noteCaptureQrState.pollIntervalId = 0;
    }
    if(noteCaptureQrState.pollTimeoutId){
      window.clearTimeout(noteCaptureQrState.pollTimeoutId);
      noteCaptureQrState.pollTimeoutId = 0;
    }
    if(noteCaptureQrState.countdownIntervalId){
      window.clearInterval(noteCaptureQrState.countdownIntervalId);
      noteCaptureQrState.countdownIntervalId = 0;
    }
  };
  const resetNoteCaptureQrState = (preserveMainStatus = false)=>{
    stopNoteCaptureQrPolling();
    noteCaptureQrState.token = '';
    noteCaptureQrState.status = '';
    noteCaptureQrState.expiresAt = '';
    noteCaptureQrState.uploadedAt = '';
    noteCaptureQrState.documentId = '';
    noteCaptureQrState.documentUuid = '';
    noteCaptureQrState.previewUrl = '';
    noteCaptureQrState.mobileUrl = '';
    noteCaptureQrState.cancelling = false;
    noteCaptureQrState.startedAt = 0;
    const els = noteQrElements();
    if(els){
      if(els.qrImage) els.qrImage.setAttribute('src', '');
      if(els.qrLink){
        els.qrLink.setAttribute('href', '#');
        els.qrLink.textContent = '';
      }
      if(els.qrState){
        els.qrState.textContent = 'Pendiente';
        els.qrState.classList.remove('text-success', 'text-danger');
      }
      if(els.countdown) els.countdown.textContent = '';
    }
    hideNotaQrPreview();
    if(!preserveMainStatus){
      setNotaQrMainStatus('', 'muted');
    }
    if(actividadClinicaNotaModalEl){
      delete actividadClinicaNotaModalEl.dataset.noteCaptureDocumentId;
      delete actividadClinicaNotaModalEl.dataset.noteCaptureDocumentUuid;
      delete actividadClinicaNotaModalEl.dataset.noteCapturePreviewUrl;
      delete actividadClinicaNotaModalEl.dataset.noteCaptureToken;
    }
  };
  const updateNoteQrCountdown = ()=>{
    const els = noteQrElements();
    if(!els?.countdown) return;
    const expiresAt = sanitizeText(noteCaptureQrState.expiresAt);
    if(!expiresAt){
      els.countdown.textContent = '';
      return;
    }
    const expiresMs = Date.parse(expiresAt);
    if(Number.isNaN(expiresMs)){
      els.countdown.textContent = '';
      return;
    }
    const remainingSec = Math.max(0, Math.round((expiresMs - Date.now()) / 1000));
    if(noteCaptureQrState.status === 'uploaded'){
      els.countdown.textContent = 'Imagen recibida.';
      return;
    }
    if(noteCaptureQrState.status === 'expired'){
      els.countdown.textContent = 'Token expirado.';
      return;
    }
    els.countdown.textContent = `Expira en ${remainingSec}s`;
  };
  const setNoteCaptureQrModalState = (state, tone = 'muted')=>{
    const els = noteQrElements();
    if(!els?.qrState) return;
    const text = sanitizeText(state) || 'Pendiente';
    els.qrState.textContent = text;
    els.qrState.classList.remove('text-success', 'text-danger');
    if(tone === 'success'){
      els.qrState.classList.add('text-success');
    }else if(tone === 'error'){
      els.qrState.classList.add('text-danger');
    }
  };
  const normalizePreviewUrl = (url)=>{
    const raw = sanitizeText(url);
    if(!raw) return '';
    if(/^https?:\/\//i.test(raw)) return raw;
    if(raw.startsWith('/')) return raw;
    return `/${raw.replace(/^\/+/, '')}`;
  };
  const resolveNoteCaptureContext = ()=>{
    const patientId = sanitizeText(getActivePatientId());
    if(!patientId){
      return { ok: false, error: 'Selecciona un paciente activo antes de usar captura por celular.' };
    }
    const encounterKey = (typeof window.getActiveEncounterKey === 'function')
      ? sanitizeText(window.getActiveEncounterKey())
      : sanitizeText(window.mxmedStore?.currentEncounterKey || window.mxmedStore?.activeEncounterKey);
    return {
      ok: true,
      patientId,
      encounterKey: encounterKey || null
    };
  };
  const persistNoteCaptureDocumentInModal = (data)=>{
    const documentId = sanitizeText(data?.document_id || '');
    const documentUuid = sanitizeText(data?.document_uuid || '');
    const previewUrl = normalizePreviewUrl(data?.preview_url || '');
    const token = sanitizeText(data?.token || noteCaptureQrState.token || '');
    if(actividadClinicaNotaModalEl){
      if(documentId) actividadClinicaNotaModalEl.dataset.noteCaptureDocumentId = documentId;
      if(documentUuid) actividadClinicaNotaModalEl.dataset.noteCaptureDocumentUuid = documentUuid;
      if(previewUrl) actividadClinicaNotaModalEl.dataset.noteCapturePreviewUrl = previewUrl;
      if(token) actividadClinicaNotaModalEl.dataset.noteCaptureToken = token;
    }
    noteCaptureQrState.documentId = documentId;
    noteCaptureQrState.documentUuid = documentUuid;
    noteCaptureQrState.previewUrl = previewUrl;
    if(previewUrl){
      const els = noteQrElements();
      if(els?.previewImage) els.previewImage.setAttribute('src', previewUrl);
      if(els?.previewWrap) els.previewWrap.classList.remove('d-none');
    }
    setNotaQrMainStatus('Imagen recibida desde celular.', 'success');
  };
  const fetchNoteCaptureTokenStatus = async (token)=>{
    const safeToken = sanitizeText(token);
    if(!safeToken){
      throw new Error('Token inválido.');
    }
    const resp = await fetch(`/api/clinical/index.php/note-capture-tokens/${encodeURIComponent(safeToken)}`, {
      method: 'GET',
      headers: { Accept: 'application/json' },
      credentials: 'same-origin'
    });
    const json = await resp.json().catch(()=> null);
    if(!resp.ok || !json || json.ok !== true){
      const message = sanitizeText(json?.message || json?.error?.message || json?.error || `HTTP ${resp.status}`);
      throw new Error(message || 'No se pudo consultar el estado de la captura.');
    }
    return json?.data || {};
  };
  const cancelNoteCaptureTokenIfPending = async (reason = 'user_closed')=>{
    const token = sanitizeText(noteCaptureQrState.token);
    const status = sanitizeText(noteCaptureQrState.status || '').toLowerCase();
    if(!token || (status && status !== 'pending') || noteCaptureQrState.cancelling === true){
      return false;
    }
    noteCaptureQrState.cancelling = true;
    try{
      await fetch(`/api/clinical/index.php/note-capture-tokens/${encodeURIComponent(token)}/cancel`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json'
        },
        credentials: 'same-origin',
        body: JSON.stringify({ reason: sanitizeText(reason) || 'user_closed' })
      });
      noteCaptureQrState.status = 'cancelled';
      return true;
    }catch(_){
      return false;
    }finally{
      noteCaptureQrState.cancelling = false;
    }
  };
  const syncNoteCaptureTokenStatus = async (opts = {})=>{
    const token = sanitizeText(noteCaptureQrState.token);
    if(!token) return;
    if(sanitizeText(noteCaptureQrState.status).toLowerCase() === 'expired') return;
    if(sanitizeText(noteCaptureQrState.status).toLowerCase() === 'cancelled') return;
    try{
      const data = await fetchNoteCaptureTokenStatus(token);
      const status = sanitizeText(data?.status || '').toLowerCase();
      noteCaptureQrState.status = status || 'pending';
      noteCaptureQrState.expiresAt = sanitizeText(data?.expires_at || noteCaptureQrState.expiresAt);
      if(status === 'uploaded'){
        stopNoteCaptureQrPolling();
        setNoteCaptureQrModalState('Imagen recibida', 'success');
        persistNoteCaptureDocumentInModal(data);
        updateNoteQrCountdown();
        return;
      }
      if(status === 'expired'){
        stopNoteCaptureQrPolling();
        setNoteCaptureQrModalState('Expirado', 'error');
        setNotaQrMainStatus('El token QR expiró. Genera uno nuevo para continuar.', 'error');
        updateNoteQrCountdown();
        return;
      }
      if(status === 'cancelled'){
        stopNoteCaptureQrPolling();
        setNoteCaptureQrModalState('Cancelado', 'error');
        setNotaQrMainStatus('La captura por celular fue cancelada.', 'error');
        updateNoteQrCountdown();
        return;
      }
      setNoteCaptureQrModalState('Pendiente');
      updateNoteQrCountdown();
      if(opts.manual === true){
        setNotaQrMainStatus('Aún no se recibe imagen. Sigue pendiente.', 'muted');
      }
    }catch(error){
      const message = sanitizeText(error?.message || 'No se pudo verificar el estado del token.');
      if(opts.manual === true){
        setNotaQrMainStatus(message, 'error');
      }
    }
  };
  const startNoteCaptureTokenPolling = ()=>{
    const token = sanitizeText(noteCaptureQrState.token);
    const status = sanitizeText(noteCaptureQrState.status || '').toLowerCase();
    if(!token || status === 'expired' || status === 'cancelled') return;
    stopNoteCaptureQrPolling();
    noteCaptureQrState.startedAt = Date.now();
    noteCaptureQrState.pollIntervalId = window.setInterval(()=>{
      syncNoteCaptureTokenStatus();
    }, NOTE_CAPTURE_POLL_INTERVAL_MS);
    noteCaptureQrState.countdownIntervalId = window.setInterval(()=>{
      updateNoteQrCountdown();
    }, 1000);
    noteCaptureQrState.pollTimeoutId = window.setTimeout(()=>{
      if(noteCaptureQrState.status === 'uploaded'){
        return;
      }
      stopNoteCaptureQrPolling();
      setNotaQrMainStatus('No se recibió imagen en el tiempo esperado. Puedes verificar manualmente.', 'muted');
      setNoteCaptureQrModalState('Pendiente');
    }, NOTE_CAPTURE_MAX_DURATION_MS);
  };
  const createNoteCaptureToken = async ()=>{
    const context = resolveNoteCaptureContext();
    if(!context.ok){
      throw new Error(context.error || 'No se pudo resolver el contexto del paciente.');
    }
    const body = {
      patient_id: context.patientId,
      encounter_key: context.encounterKey || null,
      note_context: 'nota_clinica_modal',
      expires_in_sec: 900
    };
    const resp = await fetch('/api/clinical/index.php/note-capture-tokens', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json'
      },
      credentials: 'same-origin',
      body: JSON.stringify(body)
    });
    const json = await resp.json().catch(()=> null);
    if(!resp.ok || !json || json.ok !== true){
      const message = sanitizeText(json?.message || json?.error?.message || json?.error || `HTTP ${resp.status}`);
      throw new Error(message || 'No se pudo generar el token para captura móvil.');
    }
    const data = json?.data || {};
    noteCaptureQrState.token = sanitizeText(data?.token || '');
    noteCaptureQrState.status = sanitizeText(data?.status || 'pending').toLowerCase();
    noteCaptureQrState.expiresAt = sanitizeText(data?.expires_at || '');
    noteCaptureQrState.mobileUrl = sanitizeText(data?.mobile_url || '');
    if(!noteCaptureQrState.token){
      throw new Error('El servicio no devolvió token de captura.');
    }
    return data;
  };
  const openNotaCaptureQrModal = async ()=>{
    if(!actividadClinicaNotaQrModalEl){
      setNotaQrMainStatus('No se encontró el modal QR en esta vista.', 'error');
      return;
    }
    if(!window.bootstrap || !window.bootstrap.Modal){
      setNotaQrMainStatus('Bootstrap Modal no está disponible para abrir la captura QR.', 'error');
      return;
    }
    setNotaQrMainStatus('Generando QR para captura desde celular…', 'muted');
    setNoteCaptureQrModalState('Generando…');
    stopNoteCaptureQrPolling();
    await cancelNoteCaptureTokenIfPending('new_token_requested');
    resetNoteCaptureQrState(true);
    try{
      const data = await createNoteCaptureToken();
      const mobileUrl = sanitizeText(data?.mobile_url || '');
      const qrValue = sanitizeText(data?.qr_value || mobileUrl);
      const normalizedQrValue = qrValue.startsWith('http')
        ? qrValue
        : `${window.location.origin}${qrValue.startsWith('/') ? qrValue : `/${qrValue}`}`;
      const qrImageUrl = `https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=${encodeURIComponent(normalizedQrValue)}`;
      const els = noteQrElements();
      if(els?.qrImage) els.qrImage.setAttribute('src', qrImageUrl);
      if(els?.qrLink){
        const href = mobileUrl || qrValue;
        const normalizedHref = href.startsWith('http')
          ? href
          : `${window.location.origin}${href.startsWith('/') ? href : `/${href}`}`;
        els.qrLink.setAttribute('href', normalizedHref);
        els.qrLink.textContent = normalizedHref;
      }
      setNoteCaptureQrModalState('Pendiente');
      setNotaQrMainStatus('Escanea el código QR y sube la imagen desde tu celular.', 'muted');
      updateNoteQrCountdown();
      const modal = (typeof window.bootstrap.Modal.getOrCreateInstance === 'function')
        ? window.bootstrap.Modal.getOrCreateInstance(actividadClinicaNotaQrModalEl)
        : new window.bootstrap.Modal(actividadClinicaNotaQrModalEl);
      modal.show();
      startNoteCaptureTokenPolling();
      try{
        console.info('[mxmed-note-capture] token_created', {
          token: noteCaptureQrState.token,
          expires_at: noteCaptureQrState.expiresAt
        });
      }catch(_){}
    }catch(error){
      setNotaQrMainStatus(sanitizeText(error?.message || 'No se pudo iniciar captura por celular.'), 'error');
      setNoteCaptureQrModalState('Error', 'error');
    }
  };
  const teardownNotaClinicaModalLayoutObserver = ()=>{
    if(notaClinicaModalTimelineObserver){
      notaClinicaModalTimelineObserver.disconnect();
      notaClinicaModalTimelineObserver = null;
    }
  };
  const syncNotaClinicaModalLayoutState = ()=>{
    const notaRoot = actividadClinicaNotaModalEl?.querySelector('[data-ne-section="nota_evolucion"]');
    if(!notaRoot) return;
    const timelineRoot = notaRoot.querySelector('#ne_timeline');
    const hasNotes = !!timelineRoot?.querySelector('.ne-note-card');
    notaRoot.setAttribute('data-ac-modal-has-notes', hasNotes ? '1' : '0');
  };
  const ensureNotaClinicaModalFieldOrder = ()=>{
    const notaRoot = actividadClinicaNotaModalEl?.querySelector('[data-ne-section="nota_evolucion"]');
    if(!notaRoot) return;
    const fieldsRow = notaRoot.querySelector('[data-role="ne-form-card"] .row.g-3.mt-1')
      || notaRoot.querySelector('[data-role="ne-field-tema"]')?.closest('.row');
    if(!fieldsRow) return;
    const temaField = fieldsRow.querySelector('[data-role="ne-field-tema"]');
    const contenidoField = fieldsRow.querySelector('[data-role="ne-field-contenido"]');
    const imageField = fieldsRow.querySelector('[data-role="ne-field-image"]');
    const orderedFields = [temaField, contenidoField, imageField].filter(Boolean);
    if(!orderedFields.length) return;
    const anchor = fieldsRow.firstElementChild;
    for(let i = orderedFields.length - 1; i >= 0; i -= 1){
      fieldsRow.insertBefore(orderedFields[i], anchor);
    }
  };
  const applyNotaClinicaQuickVisibility = ()=>{
    const notaRoot = actividadClinicaNotaModalEl?.querySelector('[data-ne-section="nota_evolucion"]');
    if(!notaRoot) return;
    const fieldsRow = notaRoot.querySelector('[data-role="ne-form-card"] .row.g-3.mt-1')
      || notaRoot.querySelector('[data-role="ne-field-tema"]')?.closest('.row');
    if(!fieldsRow) return;
    const visibleRoles = new Set(['ne-field-tema', 'ne-field-contenido', 'ne-field-image']);
    const fieldNodes = Array.from(fieldsRow.querySelectorAll('[data-role]'));
    fieldNodes.forEach((node)=>{
      const role = sanitizeText(node.getAttribute('data-role'));
      if(!role.startsWith('ne-field-')) return;
      if(!Object.prototype.hasOwnProperty.call(node.dataset, 'acModalOriginalHidden')){
        node.dataset.acModalOriginalHidden = node.classList.contains('d-none') ? '1' : '0';
      }
      if(visibleRoles.has(role)){
        node.classList.remove('d-none');
      }else{
        node.classList.add('d-none');
      }
    });
  };
  const restoreNotaClinicaQuickVisibility = ()=>{
    const notaRoot = pane.querySelector('[data-ne-section="nota_evolucion"]');
    if(!notaRoot) return;
    const fieldsRow = notaRoot.querySelector('[data-role="ne-form-card"] .row.g-3.mt-1')
      || notaRoot.querySelector('[data-role="ne-field-tema"]')?.closest('.row');
    if(!fieldsRow) return;
    const fieldNodes = Array.from(fieldsRow.querySelectorAll('[data-role]'));
    fieldNodes.forEach((node)=>{
      const role = sanitizeText(node.getAttribute('data-role'));
      if(!role.startsWith('ne-field-')) return;
      const originalHidden = node.dataset.acModalOriginalHidden;
      if(originalHidden === '1'){
        node.classList.add('d-none');
      }else{
        node.classList.remove('d-none');
      }
      delete node.dataset.acModalOriginalHidden;
    });
  };
  const setNotaClinicaInnerHeaderHidden = (hidden)=>{
    const notaRoot = hidden
      ? actividadClinicaNotaModalEl?.querySelector('[data-ne-section="nota_evolucion"]')
      : pane.querySelector('[data-ne-section="nota_evolucion"]');
    const innerHeader = notaRoot?.querySelector('[data-role="ne-form-card"] > .exp-card-title');
    if(!innerHeader) return;
    if(hidden){
      innerHeader.dataset.acModalForcedHidden = '1';
      innerHeader.style.display = 'none';
      return;
    }
    if(innerHeader.dataset.acModalForcedHidden === '1'){
      innerHeader.style.removeProperty('display');
      delete innerHeader.dataset.acModalForcedHidden;
    }
  };
  const setupNotaClinicaModalLayoutObserver = ()=>{
    teardownNotaClinicaModalLayoutObserver();
    const notaRoot = actividadClinicaNotaModalEl?.querySelector('[data-ne-section="nota_evolucion"]');
    const timelineRoot = notaRoot?.querySelector('#ne_timeline');
    if(!timelineRoot || typeof MutationObserver !== 'function') return;
    notaClinicaModalTimelineObserver = new MutationObserver(()=>{
      syncNotaClinicaModalLayoutState();
    });
    notaClinicaModalTimelineObserver.observe(timelineRoot, {
      childList: true,
      subtree: true
    });
  };
  actividadClinicaNotaModalEl?.addEventListener('hidden.bs.modal', ()=>{
    teardownNotaClinicaModalLayoutObserver();
    restoreNotaClinicaQuickVisibility();
    setNotaClinicaInnerHeaderHidden(false);
    cancelNoteCaptureTokenIfPending('note_modal_closed');
    resetNoteCaptureQrState();
    const notaRoot = pane.querySelector('[data-ne-section="nota_evolucion"]');
    if(notaRoot){
      notaRoot.removeAttribute('data-ac-modal-mode');
      notaRoot.removeAttribute('data-ac-modal-has-notes');
    }
  });
  actividadClinicaNotaQrModalEl?.addEventListener('hidden.bs.modal', ()=>{
    cancelNoteCaptureTokenIfPending('qr_modal_closed');
    stopNoteCaptureQrPolling();
  });
  const applyNotaClinicaQuickDefaults = ()=>{
    const notaRoot = actividadClinicaNotaModalEl?.querySelector('[data-ne-section="nota_evolucion"]');
    if(!notaRoot) return;
    const dxInput = notaRoot.querySelector('#ne_dx');
    const pronosticoSelect = notaRoot.querySelector('#ne_pronostico');
    const pronosticoTxtInput = notaRoot.querySelector('#ne_pronostico_txt');
    const planInput = notaRoot.querySelector('#ne_plan');

    if(dxInput && !sanitizeText(dxInput.value)){
      dxInput.value = 'No especificado';
    }
    if(pronosticoSelect && !sanitizeText(pronosticoSelect.value)){
      pronosticoSelect.value = 'otro';
      pronosticoSelect.dispatchEvent(new Event('change', { bubbles: true }));
    }
    if(pronosticoTxtInput && !sanitizeText(pronosticoTxtInput.value)){
      pronosticoTxtInput.value = 'No especificado';
    }
    if(planInput && !sanitizeText(planInput.value)){
      planInput.value = 'Pendiente de plan terapéutico.';
    }
  };
  const focusActividadClinicaActions = ()=>{
    if(!actividadClinicaNotasActions) return;
    actividadClinicaNotasActions.scrollIntoView({ behavior:'smooth', block:'center' });
    const firstActionBtn = actividadClinicaNotasActions.querySelector('button[data-action^="actividad-tab-open-"]');
    if(firstActionBtn){
      window.setTimeout(()=> firstActionBtn.focus(), 220);
    }
  };
  const setActividadClinicaActionOrder = (hasActiveEncounter)=>{
    if(!actividadClinicaNotasActions) return;
    const orderWithEncounter = ['nota', 'receta', 'procedimiento', 'adjunto', 'consentimiento'];
    const orderWithoutEncounter = ['receta', 'adjunto', 'nota', 'procedimiento', 'consentimiento'];
    const order = hasActiveEncounter ? orderWithEncounter : orderWithoutEncounter;
    const rank = new Map(order.map((key, index)=> [key, String(index + 1)]));
    const actionButtons = Array.from(actividadClinicaNotasActions.querySelectorAll('[data-ac-kind]'));
    actionButtons.forEach((btn)=>{
      const kind = sanitizeText(btn.getAttribute('data-ac-kind'));
      btn.style.order = rank.get(kind) || '99';
    });
  };
  const renderActividadClinicaContext = async ()=>{
    if(!actividadClinicaNotasCard) return;
    const runToken = ++actividadClinicaContextSyncToken;
    const patientId = sanitizeText(getActivePatientId());
    const setMotivo = (value)=>{
      if(!actividadClinicaNotasMotivo) return;
      const text = sanitizeText(value);
      if(!text){
        actividadClinicaNotasMotivo.textContent = '';
        actividadClinicaNotasMotivo.classList.add('d-none');
        return;
      }
      actividadClinicaNotasMotivo.textContent = `Motivo de consulta: ${text}`;
      actividadClinicaNotasMotivo.classList.remove('d-none');
    };
    const setEncounterMeta = (value)=>{
      if(!actividadClinicaNotasEncounterMeta) return;
      const text = sanitizeText(value);
      if(!text){
        actividadClinicaNotasEncounterMeta.textContent = '';
        actividadClinicaNotasEncounterMeta.classList.add('d-none');
        return;
      }
      actividadClinicaNotasEncounterMeta.textContent = text;
      actividadClinicaNotasEncounterMeta.classList.remove('d-none');
    };
    const setNeutral = (message)=>{
      if(actividadClinicaNotasStatusBadge){
        actividadClinicaNotasStatusBadge.textContent = 'Sin consulta activa';
        actividadClinicaNotasStatusBadge.classList.remove('is-active');
      }
      if(actividadClinicaNotasStatusText){
        actividadClinicaNotasStatusText.textContent = message || 'Puedes registrar información clínica a nivel del expediente del paciente.';
      }
      setEncounterMeta('');
      setActividadClinicaActionOrder(false);
    };

    setMotivo(readMotivoConsulta());
    if(!patientId){
      setNeutral('Selecciona un paciente para registrar actividad clínica.');
      return;
    }

    let resolved = null;
    if(typeof window.resolveActiveEncounterForPatient === 'function'){
      resolved = await window.resolveActiveEncounterForPatient(patientId, {
        source: 'actividad_clinica_tab_context'
      }).catch(()=> null);
    }
    if(runToken !== actividadClinicaContextSyncToken) return;

    const isSuppressedAutoContext = (typeof window.mxmedShouldSuppressAutoEncounterContext === 'function')
      ? window.mxmedShouldSuppressAutoEncounterContext(patientId) === true
      : false;
    const encounterKey = sanitizeText(resolved?.encounterKey || '');
    const appointmentId = sanitizeText(resolved?.appointmentId || '');
    const isOperationalEncounter = (typeof window.mxmedIsOperationalEncounterForPatient === 'function')
      ? window.mxmedIsOperationalEncounterForPatient(patientId, encounterKey) === true
      : false;
    const hasActiveEncounter = !!(
      resolved
      && resolved.ok === true
      && resolved.hasActive === true
      && sanitizeText(resolved.patientId || patientId) === patientId
      && encounterKey
      && isOperationalEncounter
      && !isSuppressedAutoContext
    );
    if(!hasActiveEncounter){
      setNeutral('Puedes registrar información clínica a nivel del expediente del paciente.');
      return;
    }

    if(actividadClinicaNotasStatusBadge){
      actividadClinicaNotasStatusBadge.textContent = 'Consulta activa';
      actividadClinicaNotasStatusBadge.classList.add('is-active');
    }
    if(actividadClinicaNotasStatusText){
      actividadClinicaNotasStatusText.textContent = 'Esta actividad se asociará a la consulta activa actual.';
    }
    const metaParts = [];
    if(encounterKey) metaParts.push(`Encounter: ${encounterKey}`);
    if(appointmentId) metaParts.push(`Cita: ${appointmentId}`);
    setEncounterMeta(metaParts.join(' · '));
    setActividadClinicaActionOrder(true);
  };
  const openNotaClinicaFromActividad = ()=>{
    hideActividadClinicaModal();
    const mounted = actividadClinicaNotaPortal?.mount?.() === true;
    if(!mounted){
      window.alert('No fue posible abrir Nota clínica en este momento.');
      return false;
    }
    const notaRoot = actividadClinicaNotaModalEl?.querySelector('[data-ne-section="nota_evolucion"]');
    if(notaRoot){
      notaRoot.setAttribute('data-ac-modal-mode', 'nota');
    }
    setNotaClinicaInnerHeaderHidden(true);
    applyNotaClinicaQuickVisibility();
    ensureNotaClinicaModalFieldOrder();
    applyNotaClinicaQuickDefaults();
    syncNotaClinicaModalLayoutState();
    setupNotaClinicaModalLayoutObserver();
    resetNoteCaptureQrState();
    const opened = showActividadClinicaModalById(actividadClinicaNotaModalEl);
    if(!opened) return false;
    window.requestAnimationFrame(()=>{
      const focusField = actividadClinicaNotaModalEl?.querySelector('#ne_complemento, #ne_evolucion, #ne_dx');
      focusField?.focus?.();
    });
    try{
      console.info('[mxmed-actividad-clinica] open nota clinica');
    }catch(_){}
    return true;
  };
  const openRecetaFromActividad = ()=>{
    hideActividadClinicaModal();
    const modalEl = document.getElementById('modalReceta');
    const BsModal = window.bootstrap && window.bootstrap.Modal;
    if(!modalEl || !BsModal) return false;
    try{
      // Si el modal vive dentro de un tab oculto (#t-notas), el backdrop se abre
      // pero el modal queda invisible; moverlo a body evita ese bloqueo visual.
      let parent = modalEl.parentElement;
      let insideHiddenPane = false;
      while(parent && parent !== document.body){
        if(parent.classList?.contains('tab-pane') && !parent.classList.contains('show')){
          insideHiddenPane = true;
          break;
        }
        parent = parent.parentElement;
      }
      if(insideHiddenPane && modalEl.parentElement !== document.body){
        document.body.appendChild(modalEl);
      }
    }catch(_){}
    try{
      const modal = (typeof BsModal.getOrCreateInstance === 'function')
        ? BsModal.getOrCreateInstance(modalEl)
        : new BsModal(modalEl);
      modal.show();
    }catch(_){
      return false;
    }
    try{
      console.info('[mxmed-actividad-clinica] open receta');
    }catch(_){}
    return true;
  };
  const openOrdenEstudiosFromActividad = ()=>{
    hideActividadClinicaModal();
    const opened = showClinicalTab(clinicalTabTargets.estudios);
    if(!opened) return false;
    window.requestAnimationFrame(()=>{
      try{
        const studiesPane = pane.querySelector('#t-estudios');
        const solicitarBtn = studiesPane?.querySelector('.est-section-tab[data-est-section="solicitar"]');
        if(solicitarBtn && !solicitarBtn.classList.contains('active')){
          solicitarBtn.click();
        }
        const focusTarget = studiesPane?.querySelector('[data-est-open-modal]');
        focusTarget?.focus?.();
      }catch(_){}
    });
    try{
      console.info('[mxmed-actividad-clinica] open orden estudios');
    }catch(_){}
    return true;
  };
  const openConsentimientoFromActividad = ()=>{
    hideActividadClinicaModal();
    const mounted = actividadClinicaConsentPortal?.mount?.() === true;
    if(!mounted){
      window.alert('No fue posible abrir Consentimiento informado en este momento.');
      return false;
    }
    const opened = showActividadClinicaModalById(actividadClinicaConsentModalEl);
    if(!opened) return false;
    window.requestAnimationFrame(()=>{
      const modalRoot = actividadClinicaConsentModalEl?.querySelector('[data-role="ac-consent-modal-content"]');
      const newBtn = modalRoot?.querySelector('#ci_new_btn');
      const firstCard = modalRoot?.querySelector('#ci_list [data-doc-uuid]');
      (newBtn || firstCard)?.focus?.();
    });
    try{
      console.info('[mxmed-actividad-clinica] open consentimiento');
    }catch(_){}
    return true;
  };
  const openActividadClinicaFromTratamientoAlias = ()=>{
    setActividadClinicaLauncherView('main');
    const opened = showActividadClinicaModalById(actividadClinicaModalEl);
    if(!opened) return false;
    window.requestAnimationFrame(()=>{
      const firstAction = actividadClinicaModalEl?.querySelector('[data-action^="actividad-clinica-open-"]');
      firstAction?.focus?.();
    });
    try{
      console.info('[mxmed-tratamiento-alias] open actividad clinica');
    }catch(_){}
    return true;
  };
  const openRecetaFromTratamientoAlias = ()=>{
    const opened = openRecetaFromActividad();
    if(!opened) return false;
    try{
      console.info('[mxmed-tratamiento-alias] open receta');
    }catch(_){}
    return true;
  };
  const normalizeProcedureKind = (value)=>{
    const kind = sanitizeText(value).toLowerCase();
    if(kind === 'immunization' || kind === 'wound_care' || kind === 'medication_administration' || kind === 'procedure'){
      return kind;
    }
    return 'procedure';
  };
  const triggerProcedimientoFromEmbed = async (preferredType = '')=>{
    const iframe = document.getElementById('mm-embed-historial');
    if(!iframe) return false;
    const modeHistorialBtn = pane.querySelector('#t-historial-atencion [data-embed-mode="historial"]');
    if(modeHistorialBtn) modeHistorialBtn.click();
    const preferred = normalizeProcedureKind(preferredType || 'procedure');
    const findAndClickProcedure = ()=>{
      try{
        const doc = iframe.contentWindow?.document;
        let trigger = null;
        if(preferred === 'immunization'){
          trigger = doc?.querySelector('[data-action="open-immunization-modal"]');
        }else if(preferred === 'wound_care'){
          trigger = doc?.querySelector('[data-action="open-wound-care-modal"]');
        }
        if(!trigger){
          trigger = doc?.querySelector('[data-action="open-generic-procedure-modal"]');
        }
        if(!trigger) return false;
        trigger.click();
        if(preferred !== 'immunization'){
          const typeInput = doc?.querySelector('[data-role="generic-procedure-type"]');
          if(typeInput && typeInput.value !== preferred){
            typeInput.value = preferred;
            typeInput.dispatchEvent(new Event('change', { bubbles: true }));
          }
        }
        return true;
      }catch(_){
        return false;
      }
    };
    if(findAndClickProcedure()) return true;
    for(let attempt = 0; attempt < 10; attempt += 1){
      await new Promise((resolve)=> window.setTimeout(resolve, 120));
      if(findAndClickProcedure()) return true;
    }
    return false;
  };
  const openProcedimientoHostModal = async (preferredType = '')=>{
    const modalEl = document.getElementById('modalActividadClinicaProcedimientoHost');
    if(!modalEl || !window.bootstrap || !window.bootstrap.Modal){
      return false;
    }
    const form = modalEl.querySelector('[data-role="ac-proc-form"]');
    const errorEl = modalEl.querySelector('[data-role="ac-proc-error"]');
    const contextNoteEl = modalEl.querySelector('[data-role="ac-proc-context-note"]');
    const appointmentInput = modalEl.querySelector('[data-role="ac-proc-appointment-id"]');
    const typeInput = modalEl.querySelector('[data-role="ac-proc-type"]');
    if(!form) return false;
    try{
      form.reset();
      if(errorEl){
        errorEl.textContent = '';
        errorEl.classList.add('d-none');
      }
      const patientId = sanitizeText(getActivePatientId());
      if(!patientId){
        if(errorEl){
          errorEl.textContent = 'Selecciona un paciente activo para registrar procedimiento.';
          errorEl.classList.remove('d-none');
        }
        return false;
      }
      if(appointmentInput) appointmentInput.value = '';
      if(contextNoteEl){
        contextNoteEl.textContent = 'Sin appointment vinculado.';
      }
      if(typeof window.resolveActiveEncounterForPatient === 'function'){
        const active = await window.resolveActiveEncounterForPatient(patientId, { source: 'actividad_clinica_procedimiento_host' }).catch(()=> null);
        const appointmentId = sanitizeText(active?.appointmentId || active?.appointment_id || '');
        if(appointmentInput) appointmentInput.value = appointmentId;
        if(contextNoteEl){
          contextNoteEl.textContent = appointmentId
            ? `Se vinculará appointment_id: ${appointmentId}`
            : 'Sin appointment vinculado.';
        }
      }
      const resolvedType = normalizeProcedureKind(preferredType || 'procedure');
      if(typeInput){
        typeInput.value = resolvedType;
        typeInput.dispatchEvent(new Event('change', { bubbles: true }));
      }
      const placeType = modalEl.querySelector('[data-role="ac-proc-place-type"]');
      placeType?.dispatchEvent(new Event('change'));
      const eventInput = modalEl.querySelector('[data-role="ac-proc-event-datetime"]');
      if(eventInput){
        const now = new Date();
        const y = now.getFullYear();
        const m = String(now.getMonth() + 1).padStart(2, '0');
        const d = String(now.getDate()).padStart(2, '0');
        const hh = String(now.getHours()).padStart(2, '0');
        const mm = String(now.getMinutes()).padStart(2, '0');
        eventInput.value = `${y}-${m}-${d}T${hh}:${mm}`;
      }
      const modal = (typeof window.bootstrap.Modal.getOrCreateInstance === 'function')
        ? window.bootstrap.Modal.getOrCreateInstance(modalEl)
        : new window.bootstrap.Modal(modalEl);
      modal.show();
      return true;
    }catch(_){
      return false;
    }
  };
  const openProcedimientoFromActividad = async ()=>{
    hideActividadClinicaModal();
    showClinicalTab(clinicalTabTargets.historialAtencion);
    const openedHost = await openProcedimientoHostModal('procedure');
    if(openedHost){
      try{
        console.info('[mxmed-actividad-clinica] open procedimiento host');
      }catch(_){}
      return true;
    }
    const openedEmbed = await triggerProcedimientoFromEmbed('procedure');
    if(!openedEmbed){
      window.alert('No fue posible abrir Procedimiento en este momento. Intenta abrir Historial y vuelve a intentar.');
      return false;
    }
    try{
      console.info('[mxmed-actividad-clinica] open procedimiento fallback iframe');
    }catch(_){}
    return true;
  };
  const openProcedimientoTipoFromActividad = async (type)=>{
    const normalized = normalizeProcedureKind(type);
    hideActividadClinicaModal();
    showClinicalTab(clinicalTabTargets.historialAtencion);
    const openedHost = await openProcedimientoHostModal(normalized);
    if(openedHost){
      try{
        console.info('[mxmed-actividad-clinica] open procedimiento host by type', { type: normalized });
      }catch(_){}
      return true;
    }
    const openedEmbed = await triggerProcedimientoFromEmbed(normalized);
    if(!openedEmbed){
      window.alert('No fue posible abrir esta opción en este momento. Intenta abrir Historial y vuelve a intentar.');
      return false;
    }
    try{
      console.info('[mxmed-actividad-clinica] open procedimiento fallback iframe by type', { type: normalized });
    }catch(_){}
    return true;
  };
  const pickClinicalEntryTarget = ()=>{
    const preferredTargets = [clinicalTabTargets.historialAtencion, clinicalTabTargets.historia];
    for(const target of preferredTargets){
      const candidate = findClinicalTabTrigger(target);
      const item = candidate?.closest('.nav-item');
      if(candidate && item && !item.classList.contains('d-none')) return target;
    }
    return tabs.find((btn)=>{
      const target = sanitizeText(btn.getAttribute('data-bs-target'));
      const item = btn.closest('.nav-item');
      return target && target !== clinicalTabTargets.datos && item && !item.classList.contains('d-none');
    })?.getAttribute('data-bs-target') || '';
  };
  const applyExpedienteEntryTabRule = (opts = {})=>{
    const context = String(opts.context || 'unknown').trim();
    const allowedContexts = new Set(['boot', 'setActivePatientId', 'explicit_save', 'search_open']);
    if(!allowedContexts.has(context) && opts.force !== true) return false;
    const pid = sanitizeText(getActivePatientId());
    if(!pid) return false;
    const completeProfile = hasMinimumPatientProfile();
    let targetTab = completeProfile
      ? (pickClinicalEntryTarget() || clinicalTabTargets.datos)
      : clinicalTabTargets.datos;
    if(context === 'search_open'){
      targetTab = clinicalTabTargets.datos;
      try{
        console.info('[mxmed-search-open] default tab -> datos-generales', {
          patientId: pid,
          targetTab
        });
      }catch(_){}
    }
    try{
      console.info('[mxmed-profile-gate] apply-entry-tab-rule', {
        context,
        patientId: pid,
        completeProfile,
        targetTab: targetTab || 'none'
      });
    }catch(_){}
    return showClinicalTab(targetTab);
  };
  window.mxmedReadMotivoConsulta = readMotivoConsulta;
  window.mxmedHasMinimumPatientProfile = ()=> hasMinimumPatientProfile();
  window.mxmedApplyExpedienteEntryTabRule = (opts)=> applyExpedienteEntryTabRule(opts);
  actividadClinicaLaunchBtns.forEach((btn)=>{
    btn.addEventListener('click', (event)=>{
      event.preventDefault();
      event.stopPropagation();
      event.stopImmediatePropagation();
      try{
        const BsModal = window.bootstrap && window.bootstrap.Modal;
        if(BsModal && actividadClinicaModalEl){
          setActividadClinicaLauncherView('main');
          const modal = typeof BsModal.getOrCreateInstance === 'function'
            ? BsModal.getOrCreateInstance(actividadClinicaModalEl)
            : new BsModal(actividadClinicaModalEl);
          modal.show();
          try{
            console.info('[mxmed-actividad-clinica] launcher open -> modal');
          }catch(_){}
        }
      }catch(_){}
    }, true);
  });
  actividadClinicaModalEl?.addEventListener('hidden.bs.modal', ()=>{
    setActividadClinicaLauncherView('main');
  });
  actividadClinicaNotaOpenQrBtn?.addEventListener('click', (event)=>{
    event.preventDefault();
    openNotaCaptureQrModal();
  });
  actividadClinicaNotaQrModalEl?.addEventListener('click', (event)=>{
    const copyBtn = event.target.closest('[data-action="ac-nota-qr-copy-link"]');
    if(copyBtn){
      event.preventDefault();
      const linkEl = actividadClinicaNotaQrModalEl.querySelector('[data-role="ac-nota-qr-link"]');
      const href = sanitizeText(linkEl?.getAttribute('href') || '');
      const text = sanitizeText(linkEl?.textContent || href);
      const value = href || text;
      if(!value){
        setNotaQrMainStatus('No hay enlace disponible para copiar todavía.', 'muted');
        return;
      }
      const fallbackCopy = ()=>{
        const temp = document.createElement('textarea');
        temp.value = value;
        temp.setAttribute('readonly', 'readonly');
        temp.style.position = 'absolute';
        temp.style.left = '-9999px';
        document.body.appendChild(temp);
        temp.select();
        try{ document.execCommand('copy'); }catch(_){}
        document.body.removeChild(temp);
      };
      if(navigator.clipboard?.writeText){
        navigator.clipboard.writeText(value).catch(()=> fallbackCopy());
      }else{
        fallbackCopy();
      }
      setNotaQrMainStatus('Enlace copiado. Ábrelo en tu celular para subir la imagen.', 'success');
      return;
    }
    const verifyBtn = event.target.closest('[data-action="ac-nota-qr-verify-now"]');
    if(!verifyBtn) return;
    event.preventDefault();
    syncNoteCaptureTokenStatus({ manual: true });
  });
  actividadClinicaNotasActions?.addEventListener('click', async (event)=>{
    const noteBtn = event.target.closest('[data-action="actividad-tab-open-nota"]');
    if(noteBtn){
      event.preventDefault();
      openNotaClinicaFromActividad();
      const field = pane.querySelector('#ne_complemento, #ne_evolucion, #ne_dx');
      field?.focus?.();
      return;
    }
    const rxBtn = event.target.closest('[data-action="actividad-tab-open-receta"]');
    if(rxBtn){
      event.preventDefault();
      openRecetaFromActividad();
      return;
    }
    const procBtn = event.target.closest('[data-action="actividad-tab-open-procedimiento"]');
    if(procBtn){
      event.preventDefault();
      await openProcedimientoFromActividad();
      return;
    }
    const attachBtn = event.target.closest('[data-action="actividad-tab-open-adjunto"]');
    if(attachBtn){
      event.preventDefault();
      openAdjuntoDocumentoFromActividad();
      return;
    }
    const consentBtn = event.target.closest('[data-action="actividad-tab-open-consent"]');
    if(consentBtn){
      event.preventDefault();
      openConsentimientoFromActividad();
    }
  });
  actividadClinicaModalEl?.addEventListener('click', async (event)=>{
    const procedurePickerBtn = event.target.closest('[data-action="actividad-clinica-open-procedimiento-picker"]');
    if(procedurePickerBtn){
      event.preventDefault();
      setActividadClinicaLauncherView('proc');
      return;
    }
    const procedureBackBtn = event.target.closest('[data-action="actividad-clinica-proc-back"]');
    if(procedureBackBtn){
      event.preventDefault();
      setActividadClinicaLauncherView('main');
      return;
    }
    const noteBtn = event.target.closest('[data-action="actividad-clinica-open-nota"]');
    if(noteBtn){
      event.preventDefault();
      openNotaClinicaFromActividad();
      return;
    }
    const studiesBtn = event.target.closest('[data-action="actividad-clinica-open-estudios"]');
    if(studiesBtn){
      event.preventDefault();
      openOrdenEstudiosFromActividad();
      return;
    }
    const procedureBtn = event.target.closest('[data-action="actividad-clinica-open-procedimiento"]');
    if(procedureBtn){
      event.preventDefault();
      await openProcedimientoFromActividad();
      return;
    }
    const medicationBtn = event.target.closest('[data-action="actividad-clinica-open-medication"]');
    if(medicationBtn){
      event.preventDefault();
      await openProcedimientoTipoFromActividad('medication_administration');
      return;
    }
    const immunizationBtn = event.target.closest('[data-action="actividad-clinica-open-immunization"]');
    if(immunizationBtn){
      event.preventDefault();
      await openProcedimientoTipoFromActividad('immunization');
      return;
    }
    const woundCareBtn = event.target.closest('[data-action="actividad-clinica-open-wound-care"]');
    if(woundCareBtn){
      event.preventDefault();
      await openProcedimientoTipoFromActividad('wound_care');
      return;
    }
    const rxBtn = event.target.closest('[data-action="actividad-clinica-open-receta"]');
    if(rxBtn){
      event.preventDefault();
      openRecetaFromActividad();
      return;
    }
    const consentBtn = event.target.closest('[data-action="actividad-clinica-open-consent"]');
    if(consentBtn){
      event.preventDefault();
      openConsentimientoFromActividad();
      return;
    }
    const attachBtn = event.target.closest('[data-action="actividad-clinica-open-adjunto"]');
    if(attachBtn){
      event.preventDefault();
      openAdjuntoDocumentoFromActividad();
    }
  });
  tratamientoAliasPanel?.addEventListener('click', (event)=>{
    const recetaBtn = event.target.closest('[data-action="tratamiento-alias-open-receta"]');
    if(recetaBtn){
      event.preventDefault();
      openRecetaFromTratamientoAlias();
      return;
    }
    const actividadBtn = event.target.closest('[data-action="tratamiento-alias-open-actividad"]');
    if(actividadBtn){
      event.preventDefault();
      openActividadClinicaFromTratamientoAlias();
    }
  });
  pane.addEventListener('click', (event)=>{
    const gineAliasBtn = event.target.closest('[data-action="gineco-alias-open-historia"]');
    if(!gineAliasBtn) return;
    event.preventDefault();
    const opened = showClinicalTab(clinicalTabTargets.historia);
    if(opened && ginecoHistoriaSection){
      window.requestAnimationFrame(()=>{
        ginecoHistoriaSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
    }
  });

  const layoutTabs = (showGineco)=>{
    if(!tabsWrap) return;
    const items = Array.from(tabsWrap.querySelectorAll('.nav-item'));
    items.forEach(item=>{
      item.style.flex = '';
      item.style.order = '';
    });
    tabsWrap.classList.toggle('has-gineco', showGineco);
    syncTabRowGridColumns();
  };

  const countVisibleTabItems = (rowEl)=>{
    if(!rowEl) return 0;
    const items = Array.from(rowEl.querySelectorAll(':scope > .nav-item'));
    return items.filter((item)=>{
      if(item.classList.contains('d-none')) return false;
      if(item.hasAttribute('hidden')) return false;
      const style = window.getComputedStyle(item);
      return style.display !== 'none' && style.visibility !== 'hidden';
    }).length;
  };

  const syncTabRowGridColumns = ()=>{
    if(!tabsWrap) return;
    const topRow = tabsWrap.querySelector('.mm-tabs-row-top');
    const bottomRow = tabsWrap.querySelector('.mm-tabs-row-bottom');
    const topVisible = Math.max(1, countVisibleTabItems(topRow));
    const bottomVisible = Math.max(1, countVisibleTabItems(bottomRow));
    tabsWrap.style.setProperty('--top-tabs-count', String(topVisible));
    tabsWrap.style.setProperty('--bottom-tabs-count', String(bottomVisible));
    tabsWrap.classList.toggle('is-top-compact', topVisible >= 6);
  };

  const toggleTabState = (btn, disabled)=>{
    if(!btn) return;
    btn.classList.toggle('disabled', disabled);
    btn.setAttribute('aria-disabled', disabled ? 'true' : 'false');
    btn.tabIndex = disabled ? -1 : 0;
  };

  const updateGenderExtra = ()=>{
    if(!genderExtra) return;
    const extraInput = genderExtra.querySelector('input');
    const selected = genderInputs.find(inp=>inp.checked);
    if(selected && selected.value==='O'){
      genderExtra.classList.remove('d-none');
      extraInput.removeAttribute('disabled');
      extraInput.focus();
    } else {
      genderExtra.classList.add('d-none');
      extraInput.value = '';
      extraInput.setAttribute('disabled','disabled');
    }
  };

  // Desbloqueo: dejar tabs accesibles (replantear reglas después)
  const basicsReady = ()=> true;

  const showFirstAvailable = ()=>{
    const active = pane.querySelector('.mm-tabs-row .nav-link.active');
    if(active && active.classList.contains('disabled')){
      const first = tabs.find(btn=> !btn.classList.contains('disabled') && btn.closest('.nav-item') && !btn.closest('.nav-item').classList.contains('d-none'));
      if(first){
        try{ new bootstrap.Tab(first).show(); }catch(_){ }
      }
    }
  };

  const computeAge = ()=>{
    const dd = pane.querySelector('[data-dg-dia]');
    const mm = pane.querySelector('[data-dg-mes]');
    const yy = pane.querySelector('[data-dg-anio]');
    const edadLbl = pane.querySelector('[data-dg-edad]');
    const edadOk = pane.querySelector('[data-dg-ok]');
    if(!dd || !mm || !yy || !edadLbl) return;
    const d = Number(dd.value);
    const m = Number(mm.value);
    const y = Number(yy.value);
    const filled = !!(dd.value && mm.value && yy.value);
    const valid = Number.isInteger(d) && Number.isInteger(m) && Number.isInteger(y) && d>=1 && d<=31 && m>=1 && m<=12 && y>=1900;
    const yearField = pane.querySelector('.dg-date-year') || yy.closest('.dg-date-field');
    const daysInMonth = (monthStr, yearStr)=>{
      const mVal = Number(monthStr);
      const yVal = yearStr ? Number(yearStr) : 2001;
      if(!Number.isInteger(mVal) || mVal<1 || mVal>12) return 31;
      return new Date(yVal, mVal, 0).getDate();
    };
    const showDayError = (flag)=>{
      lastDayInvalid = flag;
      if(dayError) dayError.classList.toggle('d-none', !flag);
    };
    const validateDayCombo = ()=>{
      if(!dd || !mm){ showDayError(false); return true; }
      const dayVal = dd.value || '';
      const monthVal = mm.value || '';
      const yearVal = yy?.value || '';
      if(!dayVal || !monthVal){ showDayError(lastDayInvalid); return true; }
      const dNum = Number(dayVal);
      const max = daysInMonth(monthVal, yearVal);
      const okDay = Number.isInteger(dNum) && dNum>=1 && dNum<=max;
      showDayError(!okDay);
      if(!okDay){
        dd.value = '';
        dd.classList.remove('no-caret');
      }
      return okDay;
    };
    const dayValid = validateDayCombo();
    if(dd){
      dd.classList.toggle('no-caret', !!dd.value);
      dd.style.setProperty('--bs-form-select-bg-img','none');
      dd.style.backgroundImage = 'none';
    }
    if(yy){
      yy.classList.toggle('has-value', !!yy.value);
      yy.classList.toggle('no-caret', !!yy.value);
      yy.style.backgroundImage = 'none';
      yy.style.paddingRight = '0.6rem';
    }
    // Barrer spans en campos que no sean año (previene círculos residuales)
    pane.querySelectorAll('.dg-date-field:not(.dg-date-year) span').forEach(el=> el.remove());
    // Limpiar estado visual en campos que no sean Año
    pane.querySelectorAll('.dg-date-field').forEach(field=>{
      if(field !== yearField){
        field.classList.remove('is-valid-date');
      }
    });
    if(!dayValid || !valid){
      edadLbl.textContent = '--';
      if(edadOk) edadOk.style.display = 'none';
      if(yearField) yearField.classList.remove('is-valid-date');
      return;
    }
    const today = new Date();
    const birth = new Date(y, m-1, d);
    let age = today.getFullYear() - birth.getFullYear();
    const hasHadBirthday = (today.getMonth() > birth.getMonth()) || (today.getMonth() === birth.getMonth() && today.getDate() >= birth.getDate());
    if(!hasHadBirthday) age -= 1;
    const ok = age >= 0 && age < 150;
    const showCheck = filled && ok;
    edadLbl.textContent = ok ? (age + ' años') : '--';
    if(edadOk) edadOk.style.display = showCheck ? 'inline-flex' : 'none';
    if(yearField) yearField.classList.toggle('is-valid-date', showCheck);
    // Avisar a cabecera para refrescar edad mostrada
    pane.dispatchEvent(new CustomEvent('pac-age-changed'));
  };

  const normalizeDateChecks = ()=>{
    const yearField = pane.querySelector('.dg-date-year');
    // Eliminar cualquier ícono de check residual
    pane.querySelectorAll('.dg-date-ok').forEach(el=> el.remove());
    if(yearField){
      yearField.querySelectorAll('.material-symbols-outlined, .material-symbols-rounded').forEach(el=> el.remove());
      // Eliminar cualquier nodo adicional distinto al select
      yearField.querySelectorAll(':scope > :not(select)').forEach(el=> el.remove());
      // Eliminar spans de autosave que se agreguen a contenedores padres
      yearField.closest('.save-wrap')?.querySelectorAll('.save-ok')?.forEach(el=> el.remove());
    }
  };

  const bindDOB = ()=>{
    const dd = pane.querySelector('[data-dg-dia]');
    const mm = pane.querySelector('[data-dg-mes]');
    const yy = pane.querySelector('[data-dg-anio]');
    normalizeDateChecks();
    if(dd){
      dd.addEventListener('change', computeAge);
      dd.addEventListener('input', computeAge);
    }
    if(mm){
      mm.addEventListener('change', computeAge);
      mm.addEventListener('input', computeAge);
    }
    if(yy){
      yy.addEventListener('change', computeAge);
      yy.addEventListener('input', computeAge);
    }
    computeAge();
  };

  const normalizeToken = (str) => (str || '')
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, ' ')
    .trim();

  const isUuidLike = (value) => /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(String(value || '').trim());

  const getCurrentPatientId = ()=>{
    const explicit = String(pane.getAttribute('data-patient-id') || pane.dataset?.patientId || '').trim();
    if(explicit) return explicit;

    const nombre = pane.querySelector('[data-pac-nombre]')?.value?.trim() || '';
    const apPat = pane.querySelector('[data-pac-apellido-paterno]')?.value?.trim() || '';
    const apMat = pane.querySelector('[data-pac-apellido-materno]')?.value?.trim() || '';
    const sexoVal = pane.querySelector('input[name="pac-genero"]:checked')?.value || '';
    const dd = pane.querySelector('[data-dg-dia]')?.value || '';
    const mm = pane.querySelector('[data-dg-mes]')?.value || '';
    const yy = pane.querySelector('[data-dg-anio]')?.value || '';
    const dob = [yy, mm, dd].filter(Boolean).join('-');
    const base = [nombre, apPat, apMat].filter(Boolean).join(' ').trim();
    const identity = window.mxmedIdentity || null;
    if(identity && typeof identity.buildLegacyPatientId === 'function'){
      try{
        const built = String(identity.buildLegacyPatientId(base, dob, sexoVal, normalizeToken) || '').trim();
        if(built) return built;
      }catch(_){}
    }
    return normalizeToken([base, dob, sexoVal].join('|')) || '';
  };

  const openHistorialAtencion = async ()=>{
    const originalId = String(getCurrentPatientId() || '').trim();
    const lowerOriginalId = originalId.toLowerCase();
    const invalidOriginalId = !originalId || lowerOriginalId === 'anon' || lowerOriginalId === 'anonymous' || originalId === '-' || originalId.length < 6;
    if(invalidOriginalId){
      window.alert('Primero selecciona o guarda un paciente para ver su historial de atención.');
      return;
    }

    let finalId = originalId;
    const shouldResolve = !finalId.startsWith('p_') && !isUuidLike(finalId);
    const identity = window.mxmedIdentity || null;
    if(shouldResolve && identity && typeof identity.resolveCanonicalPatientId === 'function'){
      try{
        const resolved = await identity.resolveCanonicalPatientId(finalId);
        if(typeof resolved === 'string' && resolved.trim()){
          finalId = resolved.trim();
        }
      }catch(_){}
    }

    window.location.href = `/modules/clinical/ui/historial.php?patient_id=${encodeURIComponent(finalId)}`;
  };

  const syncGineco = (genero, allowNavigate)=>{
    const isWoman = normalizeExpGender(genero) === 'F';
    const gineLi = pane.querySelector('[data-tab-conditional="gineco"]');
    const gineLinkLocal = gineLi ? gineLi.querySelector('.nav-link') : null;
    // legacy gyn panel may use .gyn-panel instead of #t-gineco
    const ginePane = ginecoAliasPane || pane.querySelector('#t-gineco') || pane.querySelector('.gyn-panel') || pane.querySelector('[data-exp-section="gineco"]');
    const historiaBtn = pane.querySelector('[data-tab-key="t-historia"]');

    if(gineLi){ gineLi.classList.toggle('d-none', !isWoman); }
    if(ginePane){ ginePane.classList.toggle('d-none', !isWoman); }
    if(ginecoHistoriaSection){ ginecoHistoriaSection.classList.toggle('d-none', !isWoman); }
    layoutTabs(isWoman);

    if(gineLinkLocal){
      if(isWoman && basicsReady()){
        toggleTabState(gineLinkLocal, false);
      }else{
        toggleTabState(gineLinkLocal, true);
      }
    }

    if(!isWoman){
      const wasOnGine = !!(gineLinkLocal?.classList.contains('active') || ginePane?.classList.contains('active'));
      gineLinkLocal?.classList.remove('active');
      ginePane?.classList.remove('show','active');
      if(gineLinkLocal?.getAttribute('aria-selected') === 'true'){
        gineLinkLocal.setAttribute('aria-selected', 'false');
      }
      if(wasOnGine && allowNavigate){
        const historiaPane = pane.querySelector('#t-historia');
        if(historiaBtn){ historiaBtn.classList.add('active'); }
        if(historiaPane){
          historiaPane.classList.remove('d-none');
          historiaPane.classList.add('show','active');
        }
        const targetHistoriaBtn = historiaBtn || tabs[0];
        if(targetHistoriaBtn){ try{ new bootstrap.Tab(targetHistoriaBtn).show(); }catch(_){ } }
      }
    }
  };

  const activePatientSessionKey = 'mxmedActivePatientId';
  const getHashPatientId = ()=>{
    const rawHash = String(window.location.hash || '');
    if(!rawHash) return '';
    const hashBody = rawHash.startsWith('#') ? rawHash.slice(1) : rawHash;
    const qIndex = hashBody.indexOf('?');
    const routePart = (qIndex >= 0 ? hashBody.slice(0, qIndex) : hashBody).trim().toLowerCase();
    if(routePart.indexOf('expediente') === -1) return '';
    const queryPart = qIndex >= 0 ? hashBody.slice(qIndex + 1) : '';
    if(!queryPart) return '';
    try{
      const params = new URLSearchParams(queryPart);
      return String(params.get('patient_id') || '').trim();
    }catch(_){
      return '';
    }
  };
  const setHashPatientId = (pid)=>{
    const patientId = String(pid || '').trim();
    if(!patientId) return;
    const rawHash = String(window.location.hash || '');
    const hashBody = rawHash.startsWith('#') ? rawHash.slice(1) : rawHash;
    const qIndex = hashBody.indexOf('?');
    const routePart = (qIndex >= 0 ? hashBody.slice(0, qIndex) : hashBody).trim();
    if(routePart.toLowerCase().indexOf('expediente') === -1) return;
    const queryPart = qIndex >= 0 ? hashBody.slice(qIndex + 1) : '';
    let params;
    try{ params = new URLSearchParams(queryPart); }catch(_){ params = new URLSearchParams(); }
    params.set('patient_id', patientId);
    const nextHash = '#' + routePart + '?' + params.toString();
    try{
      if(window.history && typeof window.history.replaceState === 'function'){
        window.history.replaceState(null, '', window.location.pathname + window.location.search + nextHash);
      }else{
        window.location.hash = nextHash;
      }
    }catch(_){
      window.location.hash = nextHash;
    }
  };
  const clearHashPatientId = ()=>{
    const rawHash = String(window.location.hash || '');
    if(!rawHash) return;
    const hashBody = rawHash.startsWith('#') ? rawHash.slice(1) : rawHash;
    const qIndex = hashBody.indexOf('?');
    const routePart = (qIndex >= 0 ? hashBody.slice(0, qIndex) : hashBody).trim();
    if(routePart.toLowerCase().indexOf('expediente') === -1) return;
    const queryPart = qIndex >= 0 ? hashBody.slice(qIndex + 1) : '';
    let params;
    try{ params = new URLSearchParams(queryPart); }catch(_){ params = new URLSearchParams(); }
    params.delete('patient_id');
    const nextQuery = params.toString();
    const nextHash = nextQuery ? ('#' + routePart + '?' + nextQuery) : ('#' + routePart);
    try{
      if(window.history && typeof window.history.replaceState === 'function'){
        window.history.replaceState(null, '', window.location.pathname + window.location.search + nextHash);
      }else{
        window.location.hash = nextHash;
      }
    }catch(_){
      window.location.hash = nextHash;
    }
  };
  const getSessionPatientId = ()=>{
    try{ return String(window.sessionStorage?.getItem(activePatientSessionKey) || '').trim(); }catch(_){ return ''; }
  };
  const setSessionPatientId = (pid)=>{
    const patientId = String(pid || '').trim();
    if(!patientId) return;
    try{ window.sessionStorage?.setItem(activePatientSessionKey, patientId); }catch(_){}
  };
  const clearSessionPatientId = ()=>{
    try{ window.sessionStorage?.removeItem(activePatientSessionKey); }catch(_){}
  };
  const maybeConfirmActiveEncounterBeforePatientChange = async (nextPatientId)=>{
    const nextPid = String(nextPatientId || '').trim();
    if(!nextPid) return false;
    const currentPid = String(getActivePatientId() || '').trim();
    if(!currentPid || currentPid === nextPid) return true;
    // Multi-active mode: cambiar de paciente no obliga a cerrar consultas previas.
    return true;
  };
  window.maybeConfirmActiveEncounterBeforePatientChange = maybeConfirmActiveEncounterBeforePatientChange;

  const ensurePatientIdentityDrafts = ()=>{
    if(!window.mxmedStore || typeof window.mxmedStore !== 'object'){
      window.mxmedStore = {};
    }
    if(!window.mxmedStore.patientIdentityDrafts || typeof window.mxmedStore.patientIdentityDrafts !== 'object'){
      window.mxmedStore.patientIdentityDrafts = {};
    }
    return window.mxmedStore.patientIdentityDrafts;
  };
  const patientIdentityHydrationCache = new Map();
  const splitDisplayNameToIdentity = (displayName)=>{
    const parts = String(displayName || '').trim().split(/\s+/).filter(Boolean);
    if(!parts.length){
      return { nombre:'', apellido_paterno:'', apellido_materno:'' };
    }
    if(parts.length === 1){
      return { nombre:parts[0], apellido_paterno:'', apellido_materno:'' };
    }
    if(parts.length === 2){
      return { nombre:parts[0], apellido_paterno:parts[1], apellido_materno:'' };
    }
    return {
      nombre: parts[0],
      apellido_paterno: parts[1],
      apellido_materno: parts.slice(2).join(' ')
    };
  };
  const parseBirthdateToDraftParts = (birthdate)=>{
    const raw = String(birthdate || '').trim();
    if(!raw) return { dia:'', mes:'', anio:'' };
    const m = raw.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if(!m) return { dia:'', mes:'', anio:'' };
    return { anio:m[1], mes:m[2], dia:m[3] };
  };
  const fetchPatientIdentityProfile = async (patientId)=>{
    const pid = String(patientId || '').trim();
    if(!pid) return null;
    if(patientIdentityHydrationCache.has(pid)){
      return patientIdentityHydrationCache.get(pid);
    }
    const request = fetch(`/api/patients/index.php/patients/${encodeURIComponent(pid)}`, {
      method: 'GET',
      headers: { Accept: 'application/json' },
      credentials: 'same-origin'
    }).then((resp)=> resp.json().catch(()=> null))
      .then((json)=>{
        if(!json || json.ok !== true || !json.data || typeof json.data !== 'object') return null;
        return json.data;
      })
      .catch(()=> null);
    patientIdentityHydrationCache.set(pid, request);
    return request;
  };
  const hydrateIdentityDraftFromPatientsApi = async (patientId)=>{
    const pid = String(patientId || '').trim();
    if(!pid) return false;
    const drafts = ensurePatientIdentityDrafts();
    if(drafts[pid] && typeof drafts[pid] === 'object'){
      return true;
    }
    const profile = await fetchPatientIdentityProfile(pid);
    if(!profile) return false;
    const displayName = String(profile.display_name || profile.nombre_completo || '').trim();
    const names = splitDisplayNameToIdentity(displayName);
    const birth = parseBirthdateToDraftParts(profile.birthdate);
    const sexo = String(profile.sex || '').trim().toUpperCase();
    const draft = {
      nombre: names.nombre || '',
      apellido_paterno: names.apellido_paterno || '',
      apellido_materno: names.apellido_materno || '',
      sexo: ['F','M','O'].includes(sexo) ? sexo : '',
      dia: birth.dia || '',
      mes: birth.mes || '',
      anio: birth.anio || ''
    };
    const hasData = Object.values(draft).some((v)=> String(v || '').trim() !== '');
    if(!hasData) return false;
    drafts[pid] = draft;
    if(displayName && typeof rememberPatientLabel === 'function'){
      try{ rememberPatientLabel(pid, displayName); }catch(_){}
    }
    return true;
  };
  const openAdjuntarDocumentoFromActividad = ()=>{
    const mounted = actividadClinicaAdjuntoPortal?.mount?.() === true;
    if(!mounted){
      window.alert('No fue posible abrir Adjuntar documento en este momento.');
      return false;
    }
    const openAdjuntoModal = ()=>{
      const opened = showActividadClinicaModalById(actividadClinicaAdjuntoModalEl);
      if(!opened) return false;
      window.setTimeout(()=>{
        const fileInput = actividadClinicaAdjuntoModalEl?.querySelector('[data-role="ac-doc-file"]');
        fileInput?.focus?.();
      }, 80);
      return true;
    };
    const launcherVisible = !!(actividadClinicaModalEl && actividadClinicaModalEl.classList.contains('show'));
    if(launcherVisible){
      const onLauncherHidden = ()=>{
        openAdjuntoModal();
      };
      actividadClinicaModalEl.addEventListener('hidden.bs.modal', onLauncherHidden, { once: true });
      hideActividadClinicaModal();
    }else{
      hideActividadClinicaModal();
      if(!openAdjuntoModal()) return false;
    }
    try{
      console.info('[mxmed-actividad-clinica] open adjuntar documento');
    }catch(_){}
    return true;
  };
  const openAdjuntoDocumentoFromActividad = ()=> openAdjuntarDocumentoFromActividad();

  const setupHostProcedureModal = ()=>{
    const modalEl = document.getElementById('modalActividadClinicaProcedimientoHost');
    if(!modalEl || modalEl.dataset.procHostInit === '1') return;
    modalEl.dataset.procHostInit = '1';

    const errorEl = modalEl.querySelector('[data-role="ac-proc-error"]');
    const submitBtn = modalEl.querySelector('[data-action="ac-proc-submit"]');
    const typeInput = modalEl.querySelector('[data-role="ac-proc-type"]');
    const titleInput = modalEl.querySelector('[data-role="ac-proc-title"]');
    const eventInput = modalEl.querySelector('[data-role="ac-proc-event-datetime"]');
    const notesInput = modalEl.querySelector('[data-role="ac-proc-notes"]');
    const placeTypeInput = modalEl.querySelector('[data-role="ac-proc-place-type"]');
    const placeNameInput = modalEl.querySelector('[data-role="ac-proc-place-name"]');
    const placeSectorInput = modalEl.querySelector('[data-role="ac-proc-place-sector"]');
    const placeNameWrap = modalEl.querySelector('[data-role="ac-proc-place-name-wrap"]');
    const placeSectorWrap = modalEl.querySelector('[data-role="ac-proc-place-sector-wrap"]');
    const appointmentInput = modalEl.querySelector('[data-role="ac-proc-appointment-id"]');
    if(!submitBtn || !typeInput || !titleInput || !eventInput || !placeTypeInput) return;

    const setError = (message)=>{
      const text = sanitizeText(message);
      if(!errorEl) return;
      errorEl.textContent = text;
      errorEl.classList.toggle('d-none', !text);
    };
    const syncPlaceFields = ()=>{
      const placeType = sanitizeText(placeTypeInput.value);
      const needsPlaceName = placeType === 'institucion' || placeType === 'otro';
      const needsSector = placeType === 'institucion';
      if(placeNameWrap) placeNameWrap.classList.toggle('d-none', !needsPlaceName);
      if(placeSectorWrap) placeSectorWrap.classList.toggle('d-none', !needsSector);
      if(!needsPlaceName && placeNameInput) placeNameInput.value = '';
      if(!needsSector && placeSectorInput) placeSectorInput.value = '';
    };
    const normalizeEventDatetime = (value)=>{
      const text = String(value || '').trim();
      if(!text) return '';
      if(/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(text)) return `${text.replace('T', ' ')}:00`;
      if(/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/.test(text)) return text.replace('T', ' ');
      return '';
    };
    const resolveClinicalActorUserId = ()=>{
      const candidates = [
        window.mxmedUserId,
        window.__MXMED_USER_ID,
        window.mxmedStore && window.mxmedStore.user_id,
        document.body && document.body.dataset ? document.body.dataset.userId : '',
        'qa'
      ];
      for(const raw of candidates){
        const value = String(raw || '').trim();
        if(value) return value;
      }
      return 'qa';
    };
    const refreshAfterSave = (patientId, requestType)=>{
      try{
        window.dispatchEvent(new CustomEvent('mxmed:clinical-document-created', {
          detail: {
            patient_id: patientId,
            document_type: requestType,
            source: 'actividad_clinica_procedimiento_host'
          }
        }));
      }catch(_){}
      try{
        const encounterKey = (typeof window.getActiveEncounterKey === 'function')
          ? String(window.getActiveEncounterKey() || '').trim()
          : '';
        window.mxmedRegisterEncounterActivity?.('procedimiento_guardado_host', {
          encounterKey,
          patientId,
          source: 'actividad_clinica_procedimiento_host'
        });
      }catch(_){}
      try{
        const iframe = document.getElementById('mm-embed-historial');
        if(iframe){
          const src = String(iframe.getAttribute('src') || '').trim();
          if(src && src.indexOf('/modules/clinical/ui/historial.php') !== -1){
            const next = `${src}${src.indexOf('?') !== -1 ? '&' : '?'}host_proc_refresh=${Date.now()}`;
            iframe.setAttribute('src', next);
          }
        }
      }catch(_){}
    };
    const buildRequest = ()=>{
      const patientId = sanitizeText(getActivePatientId());
      if(!patientId) return { error: 'patient_id requerido.' };
      const procedureType = sanitizeText(typeInput.value);
      if(!procedureType) return { error: 'Selecciona el tipo de procedimiento.' };
      const title = sanitizeText(titleInput.value);
      if(!title) return { error: 'Ingresa título / nombre.' };
      const eventDatetime = normalizeEventDatetime(eventInput.value);
      if(!eventDatetime) return { error: 'Captura fecha y hora válidas.' };
      const placeType = sanitizeText(placeTypeInput.value);
      if(!placeType) return { error: 'Selecciona lugar de aplicación.' };
      const placeName = sanitizeText(placeNameInput?.value || '');
      if((placeType === 'institucion' || placeType === 'otro') && !placeName){
        return { error: 'Indica ¿cuál? / ¿dónde? para el lugar de aplicación.' };
      }
      const placeSector = sanitizeText(placeSectorInput?.value || '');
      const notes = sanitizeText(notesInput?.value || '');
      const appointmentId = sanitizeText(appointmentInput?.value || '');
      const requestType = (
        procedureType === 'procedure'
        || procedureType === 'immunization'
        || procedureType === 'medication_administration'
        || procedureType === 'wound_care'
      ) ? procedureType : 'procedure';
      const payload = {
        administration: { place_type: placeType },
        item: {
          kind: (requestType === 'medication_administration') ? 'medication' : 'procedure',
          name: title
        }
      };
      if(placeName) payload.administration.place_name = placeName;
      if(placeType === 'institucion' && placeSector) payload.administration.place_sector = placeSector;
      if(notes) payload.notes = notes;

      const context = { patient_id: patientId };
      if(appointmentId) context.appointment_id = appointmentId;
      const encounterKey = (typeof window.getActiveEncounterKey === 'function')
        ? String(window.getActiveEncounterKey() || '').trim()
        : '';
      if(encounterKey) context.encounter_key = encounterKey;

      return {
        error: '',
        patientId,
        requestType,
        body: {
          type: requestType,
          title,
          event_datetime: eventDatetime,
          actor: { user_id: resolveClinicalActorUserId() },
          context,
          payload
        }
      };
    };

    placeTypeInput.addEventListener('change', syncPlaceFields);
    modalEl.addEventListener('hidden.bs.modal', ()=>{
      setError('');
      submitBtn.disabled = false;
      submitBtn.textContent = 'Guardar';
    });
    submitBtn.addEventListener('click', async (event)=>{
      event.preventDefault();
      const prepared = buildRequest();
      if(prepared.error){
        setError(prepared.error);
        return;
      }
      setError('');
      submitBtn.disabled = true;
      submitBtn.textContent = 'Guardando...';
      try{
        console.info('[mxmed-actividad-clinica] host procedure save start', {
          patient_id: prepared.patientId,
          type: prepared.requestType
        });
      }catch(_){}
      try{
        const resp = await fetch('/api/clinical/index.php/documents', {
          method: 'POST',
          headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json'
          },
          body: JSON.stringify(prepared.body),
          credentials: 'same-origin'
        });
        const json = await resp.json().catch(()=> null);
        if(!resp.ok || !json || json.ok !== true){
          const msg = sanitizeText(json?.message || json?.error?.message || json?.error || `HTTP ${resp.status}`) || 'No se pudo registrar el procedimiento.';
          throw new Error(msg);
        }
        const modal = (window.bootstrap && window.bootstrap.Modal && typeof window.bootstrap.Modal.getInstance === 'function')
          ? window.bootstrap.Modal.getInstance(modalEl)
          : null;
        modal?.hide();
        refreshAfterSave(prepared.patientId, prepared.requestType);
        try{
          console.info('[mxmed-actividad-clinica] host procedure save ok', {
            patient_id: prepared.patientId,
            type: prepared.requestType
          });
        }catch(_){}
      }catch(err){
        setError(err?.message || 'No se pudo registrar el procedimiento.');
        submitBtn.disabled = false;
        submitBtn.textContent = 'Guardar';
      }
    });
    syncPlaceFields();
  };
  setupHostProcedureModal();

  const setupConsentimientoCanonicoHost = ()=>{
    const root = pane.querySelector('#t-consent');
    if(!root) return;
    const els = {
      list: root.querySelector('#ci_list'),
      empty: root.querySelector('#ci_empty_state'),
      newBtn: root.querySelector('#ci_new_btn'),
      wizard: root.querySelector('#ci_wizard'),
      notice: root.querySelector('#ci_wizard_notice'),
      ctxNotice: root.querySelector('#ci_context_notice'),
      stepLabel: root.querySelector('#ci_step_label'),
      step1: root.querySelector('#ci_step_1'),
      step2: root.querySelector('#ci_step_2'),
      fullView: root.querySelector('#ci_full_view'),
      modeGuided: root.querySelector('#ci_mode_guided'),
      modeFull: root.querySelector('#ci_mode_full'),
      emitErrors: root.querySelector('#ci_emit_errors'),
      prevTop: root.querySelector('#ci_prev_top'),
      prev: root.querySelector('#ci_prev'),
      next: root.querySelector('#ci_next'),
      save: root.querySelector('#ci_save'),
      emit: root.querySelector('#ci_emit'),
      cancel: root.querySelector('#ci_cancel'),
      pacNombre: root.querySelector('#ci_pac_nombre'),
      pacEdad: root.querySelector('#ci_pac_edad'),
      pacSexo: root.querySelector('#ci_pac_sexo'),
      pacTel: root.querySelector('#ci_pac_tel'),
      pacMail: root.querySelector('#ci_pac_mail'),
      pacDom: root.querySelector('#ci_pac_dom'),
      contactNotice: root.querySelector('#ci_contact_notice'),
      template: root.querySelector('#ci_template'),
      title: root.querySelector('#ci_title'),
      procedimiento: root.querySelector('#ci_procedimiento'),
      motivo: root.querySelector('#ci_motivo'),
      objetivo: root.querySelector('#ci_objetivo'),
      templateDesc: root.querySelector('#ci_template_desc'),
      beneficiosEsperados: root.querySelector('#ci_beneficios_esperados'),
      alternativas: root.querySelector('#ci_alternativas'),
      consecuenciasNoAceptar: root.querySelector('#ci_consecuencias_no_aceptar'),
      autorizacionContingencias: root.querySelector('#ci_aut_contingencias'),
      firmanteTipo: root.querySelector('#ci_firmante_tipo'),
      firmanteNombre: root.querySelector('#ci_firmante_nombre'),
      firmanteParentesco: root.querySelector('#ci_firmante_parentesco'),
      testigo1Nombre: root.querySelector('#ci_testigo_1_nombre'),
      testigo2Nombre: root.querySelector('#ci_testigo_2_nombre'),
      legalConfirm: root.querySelector('#ci_confirm_informed'),
      fullTitle: root.querySelector('#ci_full_title'),
      fullMotivo: root.querySelector('#ci_full_motivo'),
      fullProcedimiento: root.querySelector('#ci_full_procedimiento'),
      fullRiesgos: root.querySelector('#ci_full_riesgos'),
      fullBeneficios: root.querySelector('#ci_full_beneficios'),
      fullAlternativas: root.querySelector('#ci_full_alternativas'),
      fullConsecuencias: root.querySelector('#ci_full_consecuencias'),
      fullObjetivo: root.querySelector('#ci_full_objetivo'),
      fullAutContingencias: root.querySelector('#ci_full_aut_contingencias'),
      fullFirmanteTipo: root.querySelector('#ci_full_firmante_tipo'),
      fullFirmanteNombre: root.querySelector('#ci_full_firmante_nombre'),
      fullFirmanteParentesco: root.querySelector('#ci_full_firmante_parentesco'),
      fullTestigo1Nombre: root.querySelector('#ci_full_testigo_1'),
      fullTestigo2Nombre: root.querySelector('#ci_full_testigo_2'),
      fullLegalConfirm: root.querySelector('#ci_full_confirm_informed'),
      doctorName: root.querySelector('#ci_doctor_name'),
      signatureBlock: root.querySelector('#ci_signature_block'),
      signatureSlotStep2: root.querySelector('#ci_signature_slot_step2'),
      signatureSlotFull: root.querySelector('#ci_signature_slot_full'),
      identityBlock: root.querySelector('#ci_identity_block'),
      identitySlotStep2: root.querySelector('#ci_identity_slot_step2'),
      identitySlotFull: root.querySelector('#ci_identity_slot_full'),
      identityFiles: root.querySelector('#ci_identity_files'),
      identityDocKind: root.querySelector('#ci_identity_doc_kind'),
      identityFilesList: root.querySelector('#ci_identity_files_list'),
      identityQrOpen: root.querySelector('[data-action="ci-identity-open-qr"]'),
      identityQrStatus: root.querySelector('[data-role="ci-identity-qr-status"]'),
      identityQrModal: pane.querySelector('#modalConsentIdentityQr'),
      signatureQrOpen: root.querySelector('[data-action="ci-signature-open-qr"]'),
      signatureQrModal: pane.querySelector('#modalConsentSignatureQr'),
      signatureRemoteStatus: root.querySelector('#ci_signature_remote_status'),
      signatureRemotePreviewWrap: root.querySelector('#ci_signature_remote_preview_wrap'),
      signatureRemotePreviewImage: root.querySelector('#ci_signature_remote_preview_image'),
      signatureRemotePreviewMeta: root.querySelector('#ci_signature_remote_preview_meta'),
      signatureCanvas: root.querySelector('#ci_signature_canvas'),
      signatureApply: root.querySelector('#ci_signature_apply'),
      signatureClear: root.querySelector('#ci_signature_clear'),
      signatureStatus: root.querySelector('#ci_signature_status')
    };
    if(!els.list || !els.empty || !els.newBtn || !els.wizard || !els.next || !els.save) return;

    const state = {
      step: 1,
      draftId: '',
      saving: false,
      mode: 'guided',
      signaturePad: null,
      signatureHasStroke: false,
      remoteSignature: null,
      signaturePreferredSource: '',
      identityFiles: [],
      identityRemoteRefs: [],
      form: {
        title: '',
        motivo: '',
        procedimiento: '',
        riesgos: '',
        objetivo: '',
        beneficios_esperados: '',
        alternativas: '',
        consecuencias_no_aceptar: '',
        autorizacion_contingencias: false,
        firmante_tipo: 'paciente',
        firmante_nombre: '',
        firmante_parentesco: 'self',
        testigo_1_nombre: '',
        testigo_2_nombre: '',
        confirm_informed: false
      },
      templates: [
        { key: 'procedimiento', label: 'Procedimiento invasivo', desc: 'Consentimiento para procedimientos diagnósticos o terapéuticos invasivos.' },
        { key: 'anestesia', label: 'Anestesia / sedación', desc: 'Consentimiento para administración de anestesia o sedación.' },
        { key: 'transfusion', label: 'Transfusión', desc: 'Consentimiento para transfusión de hemoderivados.' },
        { key: 'investigacion', label: 'Investigación clínica', desc: 'Consentimiento para participación en protocolo de investigación.' },
        { key: 'otro', label: 'Otro', desc: 'Consentimiento informado general para procedimiento clínico.' }
      ]
    };
    const CONSENT_IDENTITY_QR_POLL_INTERVAL_MS = 4000;
    const CONSENT_IDENTITY_QR_MAX_DURATION_MS = 90000;
    const CONSENT_SIGNATURE_QR_POLL_INTERVAL_MS = 4000;
    const CONSENT_SIGNATURE_QR_MAX_DURATION_MS = 90000;
    const consentIdentityQrState = {
      token: '',
      status: '',
      expiresAt: '',
      mobileUrl: '',
      pollIntervalId: 0,
      pollTimeoutId: 0,
      countdownIntervalId: 0,
      cancelling: false,
      startedAt: 0
    };
    const consentSignatureQrState = {
      token: '',
      status: '',
      expiresAt: '',
      mobileUrl: '',
      pollIntervalId: 0,
      pollTimeoutId: 0,
      countdownIntervalId: 0,
      cancelling: false,
      startedAt: 0
    };

    const normalize = (str)=> String(str || '')
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/[^a-z0-9]+/g, ' ')
      .trim();

    const setConsentRemoteSignatureStatus = (message = '', tone = 'muted')=>{
      if(!els.signatureRemoteStatus) return;
      const text = sanitizeText(message || '');
      els.signatureRemoteStatus.textContent = text;
      els.signatureRemoteStatus.classList.toggle('d-none', !text);
      els.signatureRemoteStatus.classList.toggle('text-success', tone === 'success');
      els.signatureRemoteStatus.classList.toggle('text-danger', tone === 'error');
      els.signatureRemoteStatus.classList.toggle('text-muted', tone !== 'success' && tone !== 'error');
    };

    const renderConsentRemoteSignaturePreview = ()=>{
      const signature = (state.remoteSignature && typeof state.remoteSignature === 'object') ? state.remoteSignature : null;
      if(!els.signatureRemotePreviewWrap || !els.signatureRemotePreviewImage || !els.signatureRemotePreviewMeta){
        return;
      }
      const imageData = sanitizeText(signature?.image_data || '');
      if(!imageData){
        els.signatureRemotePreviewWrap.classList.add('d-none');
        els.signatureRemotePreviewImage.removeAttribute('src');
        els.signatureRemotePreviewMeta.textContent = '';
        return;
      }
      els.signatureRemotePreviewImage.setAttribute('src', imageData);
      const signerName = sanitizeText(signature?.signer_name || '');
      const signedAt = sanitizeText(signature?.signed_at || '');
      const source = sanitizeText(signature?.source || 'remote_qr');
      const sourceLabel = source === 'remote_qr' ? 'Firma remota' : 'Firma';
      const meta = [signerName, signedAt, sourceLabel].filter(Boolean).join(' · ');
      els.signatureRemotePreviewMeta.textContent = meta;
      els.signatureRemotePreviewWrap.classList.remove('d-none');
    };

    const updateSignatureStatus = ()=>{
      if(!els.signatureStatus) return;
      if(state.signaturePreferredSource === 'remote' && state.remoteSignature){
        els.signatureStatus.textContent = 'Firma remota lista';
        return;
      }
      if(state.signatureHasStroke){
        els.signatureStatus.textContent = 'Firma local capturada';
        return;
      }
      if(state.remoteSignature){
        els.signatureStatus.textContent = 'Firma remota disponible';
        return;
      }
      els.signatureStatus.textContent = 'Sin firma';
    };

    const setConsentSignaturePreferredSource = (source = '')=>{
      const normalized = sanitizeText(source).toLowerCase();
      if(normalized === 'remote' && state.remoteSignature){
        state.signaturePreferredSource = 'remote';
      }else if(normalized === 'local' && state.signatureHasStroke){
        state.signaturePreferredSource = 'local';
      }else if(state.remoteSignature){
        state.signaturePreferredSource = 'remote';
      }else if(state.signatureHasStroke){
        state.signaturePreferredSource = 'local';
      }else{
        state.signaturePreferredSource = '';
      }
      updateSignatureStatus();
    };

    const clearConsentRemoteSignature = ({ keepMessage = false } = {})=>{
      state.remoteSignature = null;
      renderConsentRemoteSignaturePreview();
      if(!keepMessage){
        setConsentRemoteSignatureStatus('');
      }
      if(state.signaturePreferredSource === 'remote'){
        state.signaturePreferredSource = state.signatureHasStroke ? 'local' : '';
      }
      updateSignatureStatus();
    };

    const clearConsentSignaturePad = ()=>{
      const canvas = els.signatureCanvas;
      if(!canvas) return;
      const ctx = canvas.getContext('2d');
      if(!ctx) return;
      ctx.clearRect(0, 0, canvas.width, canvas.height);
      ctx.fillStyle = '#ffffff';
      ctx.fillRect(0, 0, canvas.width, canvas.height);
      state.signatureHasStroke = false;
      if(state.signaturePreferredSource === 'local'){
        state.signaturePreferredSource = state.remoteSignature ? 'remote' : '';
      }
      updateSignatureStatus();
    };

    const syncConsentSignatureCanvasSize = ()=>{
      const canvas = els.signatureCanvas;
      if(!canvas) return;
      const rect = canvas.getBoundingClientRect();
      const width = Math.max(300, Math.floor(rect.width || 0));
      const height = Math.max(160, Math.floor(rect.height || 180));
      const dpr = Math.max(1, window.devicePixelRatio || 1);
      const targetW = Math.floor(width * dpr);
      const targetH = Math.floor(height * dpr);
      if(canvas.width === targetW && canvas.height === targetH){
        return;
      }
      canvas.width = targetW;
      canvas.height = targetH;
      const ctx = canvas.getContext('2d');
      if(!ctx) return;
      ctx.setTransform(1, 0, 0, 1, 0, 0);
      ctx.scale(dpr, dpr);
      ctx.fillStyle = '#ffffff';
      ctx.fillRect(0, 0, width, height);
      ctx.lineCap = 'round';
      ctx.lineJoin = 'round';
      ctx.lineWidth = 2;
      ctx.strokeStyle = '#0f172a';
      state.signatureHasStroke = false;
      if(state.signaturePreferredSource === 'local'){
        state.signaturePreferredSource = state.remoteSignature ? 'remote' : '';
      }
      updateSignatureStatus();
    };

    const initConsentSignaturePad = ()=>{
      const canvas = els.signatureCanvas;
      if(!canvas || canvas.dataset.bound === '1') return;
      syncConsentSignatureCanvasSize();
      const ctx = canvas.getContext('2d');
      if(!ctx) return;
      const readPoint = (event)=>{
        const rect = canvas.getBoundingClientRect();
        return {
          x: (event.clientX - rect.left),
          y: (event.clientY - rect.top)
        };
      };
      let drawing = false;
      const start = (event)=>{
        if(state.saving) return;
        drawing = true;
        const pt = readPoint(event);
        ctx.beginPath();
        ctx.moveTo(pt.x, pt.y);
        state.signatureHasStroke = true;
        state.signaturePreferredSource = 'local';
        updateSignatureStatus();
        event.preventDefault();
      };
      const move = (event)=>{
        if(!drawing) return;
        const pt = readPoint(event);
        ctx.lineTo(pt.x, pt.y);
        ctx.stroke();
        event.preventDefault();
      };
      const end = (event)=>{
        if(!drawing) return;
        drawing = false;
        ctx.closePath();
        event.preventDefault();
      };
      canvas.addEventListener('pointerdown', start);
      canvas.addEventListener('pointermove', move);
      canvas.addEventListener('pointerup', end);
      canvas.addEventListener('pointerleave', end);
      canvas.addEventListener('pointercancel', end);
      canvas.dataset.bound = '1';
      state.signaturePad = { canvas, ctx };
      updateSignatureStatus();
    };

    const exportConsentSignatureData = ()=>{
      const canvas = els.signatureCanvas;
      if(!canvas || !state.signatureHasStroke) return '';
      try{
        return canvas.toDataURL('image/png');
      }catch(_){
        return '';
      }
    };

    const setConsentRemoteSignatureFromToken = (entry = {})=>{
      const imageData = sanitizeText(entry?.image_data || '');
      if(!imageData) return false;
      state.remoteSignature = {
        type: 'drawn',
        source: 'remote_qr',
        role: 'patient_or_representative',
        image_data: imageData,
        signed_at: sanitizeText(entry?.signed_at || formatNowSql()),
        signer_name: sanitizeText(entry?.signer_name || state.form.firmante_nombre || ''),
        token: sanitizeText(entry?.token || consentSignatureQrState.token || '')
      };
      state.signaturePreferredSource = 'remote';
      renderConsentRemoteSignaturePreview();
      updateSignatureStatus();
      return true;
    };

    const getActiveConsentPatientSignature = (nowSql = '')=>{
      const localSignatureData = exportConsentSignatureData();
      const localSignature = localSignatureData ? {
        type: 'drawn',
        role: 'patient_or_representative',
        image_data: localSignatureData,
        signed_at: sanitizeText(nowSql || formatNowSql()),
        signer_name: sanitizeText(state.form.firmante_nombre || ''),
        source: 'local_canvas'
      } : null;
      const remoteSignature = (state.remoteSignature && sanitizeText(state.remoteSignature.image_data || ''))
        ? { ...state.remoteSignature }
        : null;

      if(state.signaturePreferredSource === 'remote' && remoteSignature){
        return remoteSignature;
      }
      if(state.signaturePreferredSource === 'local' && localSignature){
        return localSignature;
      }
      if(remoteSignature){
        return remoteSignature;
      }
      if(localSignature){
        return localSignature;
      }
      return null;
    };

    const mountConsentSignatureBlock = (targetSlot)=>{
      if(!els.signatureBlock || !targetSlot) return;
      if(els.signatureBlock.parentElement !== targetSlot){
        targetSlot.appendChild(els.signatureBlock);
      }
      els.signatureBlock.classList.remove('d-none');
    };

    const mountConsentIdentityBlock = (targetSlot)=>{
      if(!els.identityBlock || !targetSlot) return;
      if(els.identityBlock.parentElement !== targetSlot){
        targetSlot.appendChild(els.identityBlock);
      }
      els.identityBlock.classList.remove('d-none');
    };

    const normalizeConsentIdentityRef = (entry = {})=>{
      if(!entry || typeof entry !== 'object') return null;
      const normalized = {
        document_id: sanitizeText(entry.document_id || entry.id || ''),
        document_uuid: sanitizeText(entry.document_uuid || entry.uuid || ''),
        title: sanitizeText(entry.title || ''),
        document_type: sanitizeText(entry.document_type || ''),
        file_name: sanitizeText(entry.file_name || ''),
        preview_url: sanitizeText(entry.preview_url || ''),
        note_capture_token: sanitizeText(entry.note_capture_token || ''),
        source: sanitizeText(entry.source || '')
      };
      if(!normalized.document_id && !normalized.document_uuid && !normalized.note_capture_token){
        return null;
      }
      return normalized;
    };
    const mergeConsentIdentityRefs = (refs = [])=>{
      const incoming = Array.isArray(refs) ? refs : [];
      if(incoming.length === 0) return;
      const merged = [];
      const seen = new Set();
      const pushUnique = (entry)=>{
        const normalized = normalizeConsentIdentityRef(entry);
        if(!normalized) return;
        const key = `${normalized.document_id}|${normalized.document_uuid}|${normalized.note_capture_token}`;
        if(seen.has(key)) return;
        seen.add(key);
        merged.push(normalized);
      };
      (Array.isArray(state.identityRemoteRefs) ? state.identityRemoteRefs : []).forEach(pushUnique);
      incoming.forEach(pushUnique);
      state.identityRemoteRefs = merged;
    };
    const setConsentIdentityQrStatus = (message = '', tone = 'muted')=>{
      if(!els.identityQrStatus) return;
      const text = sanitizeText(message);
      els.identityQrStatus.textContent = text;
      els.identityQrStatus.classList.toggle('d-none', !text);
      els.identityQrStatus.classList.toggle('text-success', tone === 'success');
      els.identityQrStatus.classList.toggle('text-danger', tone === 'error');
      els.identityQrStatus.classList.toggle('text-muted', tone !== 'success' && tone !== 'error');
    };

    const renderIdentityFilesList = ()=>{
      if(!els.identityFilesList) return;
      const localFiles = Array.isArray(state.identityFiles) ? state.identityFiles : [];
      const remoteRefs = Array.isArray(state.identityRemoteRefs) ? state.identityRemoteRefs : [];
      if(localFiles.length === 0 && remoteRefs.length === 0){
        els.identityFilesList.textContent = 'Sin anexos cargados.';
        return;
      }
      const labels = [];
      localFiles.forEach((file)=>{
        labels.push(`${sanitizeText(file?.name || 'archivo')} (${Math.max(1, Math.round((Number(file?.size || 0) || 0) / 1024))} KB)`);
      });
      remoteRefs.forEach((ref, idx)=>{
        const title = sanitizeText(ref?.title || ref?.file_name || `Captura móvil ${idx + 1}`);
        labels.push(`${title} (captura móvil)`);
      });
      els.identityFilesList.textContent = labels.join(' · ');
    };

    const consentIdentityQrElements = ()=>{
      const modal = els.identityQrModal;
      if(!modal) return null;
      return {
        qrImage: modal.querySelector('[data-role="ci-identity-qr-image"]'),
        qrLink: modal.querySelector('[data-role="ci-identity-qr-link"]'),
        qrState: modal.querySelector('[data-role="ci-identity-qr-state"]'),
        countdown: modal.querySelector('[data-role="ci-identity-qr-countdown"]'),
        previewWrap: modal.querySelector('[data-role="ci-identity-qr-preview-wrap"]'),
        previewImage: modal.querySelector('[data-role="ci-identity-qr-preview-image"]'),
        verifyBtn: modal.querySelector('[data-action="ci-identity-qr-verify-now"]')
      };
    };
    const syncConsentIdentityQrVerifyButton = ()=>{
      const qrEls = consentIdentityQrElements();
      if(!qrEls?.verifyBtn) return;
      const status = sanitizeText(consentIdentityQrState.status || '').toLowerCase();
      const isPending = !status || status === 'pending';
      qrEls.verifyBtn.classList.toggle('d-none', !isPending);
      qrEls.verifyBtn.disabled = !isPending;
    };
    const setConsentIdentityQrModalState = (label = 'Pendiente', tone = 'muted')=>{
      const qrEls = consentIdentityQrElements();
      if(!qrEls?.qrState) return;
      qrEls.qrState.textContent = sanitizeText(label || 'Pendiente');
      qrEls.qrState.classList.remove('text-muted', 'text-success', 'text-danger');
      qrEls.qrState.classList.add(tone === 'success' ? 'text-success' : (tone === 'error' ? 'text-danger' : 'text-muted'));
    };
    const updateConsentIdentityQrCountdown = ()=>{
      const qrEls = consentIdentityQrElements();
      if(!qrEls?.countdown) return;
      const expiresRaw = sanitizeText(consentIdentityQrState.expiresAt || '');
      if(!expiresRaw){
        qrEls.countdown.textContent = '';
        return;
      }
      const expiresTs = Date.parse(expiresRaw);
      if(!Number.isFinite(expiresTs)){
        qrEls.countdown.textContent = '';
        return;
      }
      const remainingMs = Math.max(0, expiresTs - Date.now());
      const totalSeconds = Math.ceil(remainingMs / 1000);
      const mm = String(Math.floor(totalSeconds / 60)).padStart(2, '0');
      const ss = String(totalSeconds % 60).padStart(2, '0');
      qrEls.countdown.textContent = remainingMs <= 0 ? 'Token expirado.' : `Expira en ${mm}:${ss}`;
    };
    const stopConsentIdentityQrPolling = ()=>{
      if(consentIdentityQrState.pollIntervalId){
        window.clearInterval(consentIdentityQrState.pollIntervalId);
        consentIdentityQrState.pollIntervalId = 0;
      }
      if(consentIdentityQrState.pollTimeoutId){
        window.clearTimeout(consentIdentityQrState.pollTimeoutId);
        consentIdentityQrState.pollTimeoutId = 0;
      }
      if(consentIdentityQrState.countdownIntervalId){
        window.clearInterval(consentIdentityQrState.countdownIntervalId);
        consentIdentityQrState.countdownIntervalId = 0;
      }
    };
    const resetConsentIdentityQrState = (preserveMainStatus = false)=>{
      stopConsentIdentityQrPolling();
      consentIdentityQrState.token = '';
      consentIdentityQrState.status = '';
      consentIdentityQrState.expiresAt = '';
      consentIdentityQrState.mobileUrl = '';
      consentIdentityQrState.cancelling = false;
      consentIdentityQrState.startedAt = 0;
      const qrEls = consentIdentityQrElements();
      if(qrEls?.qrImage) qrEls.qrImage.setAttribute('src', '');
      if(qrEls?.qrLink){
        qrEls.qrLink.removeAttribute('href');
        qrEls.qrLink.textContent = '';
      }
      if(qrEls?.previewWrap) qrEls.previewWrap.classList.add('d-none');
      if(qrEls?.previewImage) qrEls.previewImage.removeAttribute('src');
      setConsentIdentityQrModalState('Pendiente');
      updateConsentIdentityQrCountdown();
      syncConsentIdentityQrVerifyButton();
      if(!preserveMainStatus){
        setConsentIdentityQrStatus('');
      }
    };
    const fetchConsentIdentityTokenStatus = async (token)=>{
      const safeToken = sanitizeText(token);
      if(!safeToken) throw new Error('Token inválido.');
      const resp = await fetch(`/api/clinical/index.php/note-capture-tokens/${encodeURIComponent(safeToken)}`, {
        method: 'GET',
        headers: { Accept: 'application/json' },
        credentials: 'same-origin'
      });
      const json = await resp.json().catch(()=> null);
      if(!resp.ok || !json || json.ok !== true){
        const message = sanitizeText(json?.message || json?.error?.message || json?.error || `HTTP ${resp.status}`);
        throw new Error(message || 'No se pudo consultar el estado de la captura.');
      }
      return json?.data || {};
    };
    const cancelConsentIdentityTokenIfPending = async (reason = 'user_closed')=>{
      const token = sanitizeText(consentIdentityQrState.token);
      const status = sanitizeText(consentIdentityQrState.status || '').toLowerCase();
      if(!token || (status && status !== 'pending') || consentIdentityQrState.cancelling){
        return false;
      }
      consentIdentityQrState.cancelling = true;
      try{
        await fetch(`/api/clinical/index.php/note-capture-tokens/${encodeURIComponent(token)}/cancel`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json'
          },
          credentials: 'same-origin',
          body: JSON.stringify({ reason: sanitizeText(reason) || 'user_closed' })
        });
        consentIdentityQrState.status = 'cancelled';
        return true;
      }catch(_){
        return false;
      }finally{
        consentIdentityQrState.cancelling = false;
      }
    };
    const persistConsentIdentityQrRef = (data = {})=>{
      const documentId = sanitizeText(data?.document_id || '');
      const documentUuid = sanitizeText(data?.document_uuid || '');
      const previewUrl = sanitizeText(data?.preview_url || '');
      const token = sanitizeText(consentIdentityQrState.token || data?.token || '');
      if(!documentId && !documentUuid && !token) return;
      const selectedKind = sanitizeText(els.identityDocKind?.value || 'ine').toLowerCase();
      const kindLabelMap = {
        ine: 'Credencial de elector / INE',
        pasaporte: 'Pasaporte',
        otro: 'Documento de identidad'
      };
      const kindLabel = kindLabelMap[selectedKind] || 'Documento de identidad';
      mergeConsentIdentityRefs([{
        document_id: documentId,
        document_uuid: documentUuid,
        title: `Anexo identidad firmante — ${kindLabel}`,
        document_type: 'image',
        preview_url: previewUrl,
        note_capture_token: token,
        source: 'consentimiento_identidad_qr_v1'
      }]);
      renderIdentityFilesList();
      if(previewUrl){
        const qrEls = consentIdentityQrElements();
        if(qrEls?.previewImage) qrEls.previewImage.setAttribute('src', previewUrl);
        if(qrEls?.previewWrap) qrEls.previewWrap.classList.remove('d-none');
      }
      setConsentIdentityQrStatus('Anexo de identidad recibido desde celular. Puedes capturar otro si lo necesitas.', 'success');
    };
    const syncConsentIdentityTokenStatus = async (opts = {})=>{
      const token = sanitizeText(consentIdentityQrState.token);
      if(!token) return;
      const currentStatus = sanitizeText(consentIdentityQrState.status).toLowerCase();
      if(currentStatus === 'expired' || currentStatus === 'cancelled' || currentStatus === 'uploaded') return;
      try{
        const data = await fetchConsentIdentityTokenStatus(token);
        const status = sanitizeText(data?.status || '').toLowerCase();
        consentIdentityQrState.status = status || 'pending';
        consentIdentityQrState.expiresAt = sanitizeText(data?.expires_at || consentIdentityQrState.expiresAt);
        if(status === 'uploaded'){
          stopConsentIdentityQrPolling();
          setConsentIdentityQrModalState('Recibido', 'success');
          persistConsentIdentityQrRef(data);
          updateConsentIdentityQrCountdown();
          syncConsentIdentityQrVerifyButton();
          return;
        }
        if(status === 'expired'){
          stopConsentIdentityQrPolling();
          setConsentIdentityQrModalState('Expirado', 'error');
          setConsentIdentityQrStatus('El token QR expiró. Genera uno nuevo para continuar.', 'error');
          updateConsentIdentityQrCountdown();
          syncConsentIdentityQrVerifyButton();
          return;
        }
        if(status === 'cancelled'){
          stopConsentIdentityQrPolling();
          setConsentIdentityQrModalState('Cancelado', 'error');
          setConsentIdentityQrStatus('La captura de identidad fue cancelada.', 'error');
          updateConsentIdentityQrCountdown();
          syncConsentIdentityQrVerifyButton();
          return;
        }
        setConsentIdentityQrModalState('Pendiente');
        updateConsentIdentityQrCountdown();
        syncConsentIdentityQrVerifyButton();
        if(opts.manual === true){
          setConsentIdentityQrStatus('Aún no se recibe anexo. Sigue pendiente.', 'muted');
        }
      }catch(error){
        if(opts.manual === true){
          setConsentIdentityQrStatus(sanitizeText(error?.message || 'No se pudo verificar estado del token.'), 'error');
        }
      }
    };
    const startConsentIdentityQrPolling = ()=>{
      const token = sanitizeText(consentIdentityQrState.token);
      const status = sanitizeText(consentIdentityQrState.status || '').toLowerCase();
      if(!token || status === 'expired' || status === 'cancelled') return;
      stopConsentIdentityQrPolling();
      consentIdentityQrState.startedAt = Date.now();
      consentIdentityQrState.pollIntervalId = window.setInterval(()=>{
        syncConsentIdentityTokenStatus();
      }, CONSENT_IDENTITY_QR_POLL_INTERVAL_MS);
      consentIdentityQrState.countdownIntervalId = window.setInterval(()=>{
        updateConsentIdentityQrCountdown();
      }, 1000);
      consentIdentityQrState.pollTimeoutId = window.setTimeout(()=>{
        if(consentIdentityQrState.status === 'uploaded') return;
        stopConsentIdentityQrPolling();
        setConsentIdentityQrStatus('No se recibió anexo en el tiempo esperado. Puedes verificar manualmente.', 'muted');
        setConsentIdentityQrModalState('Pendiente');
        syncConsentIdentityQrVerifyButton();
      }, CONSENT_IDENTITY_QR_MAX_DURATION_MS);
    };
    const resolveConsentIdentityCaptureContext = async ()=>{
      const patientId = resolveActivePatientIdForConsent();
      if(!patientId){
        return { ok: false, error: 'Selecciona paciente antes de iniciar captura remota.' };
      }
      let encounterKey = '';
      if(typeof window.resolveActiveEncounterForPatient === 'function'){
        const resolved = await window.resolveActiveEncounterForPatient(patientId, { source: 'consentimiento_identidad_qr' }).catch(()=> null);
        encounterKey = sanitizeText(resolved?.encounterKey || '');
      }else if(typeof window.getActiveEncounterKey === 'function'){
        encounterKey = sanitizeText(window.getActiveEncounterKey() || '');
      }
      if(encounterKey && typeof window.mxmedIsOperationalEncounterForPatient === 'function'){
        const isOperational = window.mxmedIsOperationalEncounterForPatient(patientId, encounterKey) === true;
        if(!isOperational) encounterKey = '';
      }
      return { ok: true, patientId, encounterKey };
    };
    const createConsentIdentityCaptureToken = async ()=>{
      const context = await resolveConsentIdentityCaptureContext();
      if(!context.ok){
        throw new Error(context.error || 'No se pudo resolver el contexto del paciente.');
      }
      const kind = sanitizeText(els.identityDocKind?.value || 'ine').toLowerCase();
      const body = {
        patient_id: context.patientId,
        encounter_key: context.encounterKey || null,
        note_context: `consentimiento_identidad_firmante:${kind || 'ine'}`,
        expires_in_sec: 900
      };
      const resp = await fetch('/api/clinical/index.php/note-capture-tokens', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json'
        },
        credentials: 'same-origin',
        body: JSON.stringify(body)
      });
      const json = await resp.json().catch(()=> null);
      if(!resp.ok || !json || json.ok !== true){
        const message = sanitizeText(json?.message || json?.error?.message || json?.error || `HTTP ${resp.status}`);
        throw new Error(message || 'No se pudo generar el token de captura para identidad.');
      }
      const data = json?.data || {};
      consentIdentityQrState.token = sanitizeText(data?.token || '');
      consentIdentityQrState.status = sanitizeText(data?.status || 'pending').toLowerCase();
      consentIdentityQrState.expiresAt = sanitizeText(data?.expires_at || '');
      consentIdentityQrState.mobileUrl = sanitizeText(data?.mobile_url || '');
      if(!consentIdentityQrState.token){
        throw new Error('El servicio no devolvió token de captura.');
      }
      return data;
    };
    const openConsentIdentityQrModal = async ()=>{
      if(!els.identityQrModal){
        setConsentIdentityQrStatus('No se encontró el modal QR para identidad.', 'error');
        return;
      }
      if(!window.bootstrap || !window.bootstrap.Modal){
        setConsentIdentityQrStatus('Bootstrap Modal no está disponible para abrir captura QR.', 'error');
        return;
      }
      setConsentIdentityQrStatus('Generando QR para captura desde celular…', 'muted');
      setConsentIdentityQrModalState('Generando…');
      stopConsentIdentityQrPolling();
      await cancelConsentIdentityTokenIfPending('new_token_requested');
      resetConsentIdentityQrState(true);
      try{
        const data = await createConsentIdentityCaptureToken();
        const mobileUrl = sanitizeText(data?.mobile_url || '');
        const qrValue = sanitizeText(data?.qr_value || mobileUrl);
        const normalizedQrValue = qrValue.startsWith('http')
          ? qrValue
          : `${window.location.origin}${qrValue.startsWith('/') ? qrValue : `/${qrValue}`}`;
        const qrImageUrl = `https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=${encodeURIComponent(normalizedQrValue)}`;
        const qrEls = consentIdentityQrElements();
        if(qrEls?.qrImage) qrEls.qrImage.setAttribute('src', qrImageUrl);
        if(qrEls?.qrLink){
          const href = mobileUrl || qrValue;
          const normalizedHref = href.startsWith('http')
            ? href
            : `${window.location.origin}${href.startsWith('/') ? href : `/${href}`}`;
          qrEls.qrLink.setAttribute('href', normalizedHref);
          qrEls.qrLink.textContent = normalizedHref;
        }
        setConsentIdentityQrModalState('Pendiente');
        setConsentIdentityQrStatus('Escanea el código QR y sube el documento desde tu celular.', 'muted');
        updateConsentIdentityQrCountdown();
        syncConsentIdentityQrVerifyButton();
        const modal = (typeof window.bootstrap.Modal.getOrCreateInstance === 'function')
          ? window.bootstrap.Modal.getOrCreateInstance(els.identityQrModal)
          : new window.bootstrap.Modal(els.identityQrModal);
        modal.show();
        startConsentIdentityQrPolling();
      }catch(error){
        setConsentIdentityQrStatus(sanitizeText(error?.message || 'No se pudo iniciar captura por celular.'), 'error');
        setConsentIdentityQrModalState('Error', 'error');
      }
    };

    const consentSignatureQrElements = ()=>{
      const modal = els.signatureQrModal;
      if(!modal) return null;
      return {
        qrImage: modal.querySelector('[data-role="ci-signature-qr-image"]'),
        qrLink: modal.querySelector('[data-role="ci-signature-qr-link"]'),
        qrState: modal.querySelector('[data-role="ci-signature-qr-state"]'),
        countdown: modal.querySelector('[data-role="ci-signature-qr-countdown"]'),
        previewWrap: modal.querySelector('[data-role="ci-signature-qr-preview-wrap"]'),
        previewImage: modal.querySelector('[data-role="ci-signature-qr-preview-image"]'),
        verifyBtn: modal.querySelector('[data-action="ci-signature-qr-verify-now"]')
      };
    };
    const syncConsentSignatureQrVerifyButton = ()=>{
      const qrEls = consentSignatureQrElements();
      if(!qrEls?.verifyBtn) return;
      const status = sanitizeText(consentSignatureQrState.status || '').toLowerCase();
      const isPending = !status || status === 'pending';
      qrEls.verifyBtn.classList.toggle('d-none', !isPending);
      qrEls.verifyBtn.disabled = !isPending;
    };
    const setConsentSignatureQrModalState = (label = 'Pendiente', tone = 'muted')=>{
      const qrEls = consentSignatureQrElements();
      if(!qrEls?.qrState) return;
      qrEls.qrState.textContent = sanitizeText(label || 'Pendiente');
      qrEls.qrState.classList.remove('text-muted', 'text-success', 'text-danger');
      qrEls.qrState.classList.add(tone === 'success' ? 'text-success' : (tone === 'error' ? 'text-danger' : 'text-muted'));
    };
    const updateConsentSignatureQrCountdown = ()=>{
      const qrEls = consentSignatureQrElements();
      if(!qrEls?.countdown) return;
      const expiresRaw = sanitizeText(consentSignatureQrState.expiresAt || '');
      if(!expiresRaw){
        qrEls.countdown.textContent = '';
        return;
      }
      const expiresTs = Date.parse(expiresRaw);
      if(!Number.isFinite(expiresTs)){
        qrEls.countdown.textContent = '';
        return;
      }
      const remainingMs = Math.max(0, expiresTs - Date.now());
      const totalSeconds = Math.ceil(remainingMs / 1000);
      const mm = String(Math.floor(totalSeconds / 60)).padStart(2, '0');
      const ss = String(totalSeconds % 60).padStart(2, '0');
      qrEls.countdown.textContent = remainingMs <= 0 ? 'Token expirado.' : `Expira en ${mm}:${ss}`;
    };
    const stopConsentSignatureQrPolling = ()=>{
      if(consentSignatureQrState.pollIntervalId){
        window.clearInterval(consentSignatureQrState.pollIntervalId);
        consentSignatureQrState.pollIntervalId = 0;
      }
      if(consentSignatureQrState.pollTimeoutId){
        window.clearTimeout(consentSignatureQrState.pollTimeoutId);
        consentSignatureQrState.pollTimeoutId = 0;
      }
      if(consentSignatureQrState.countdownIntervalId){
        window.clearInterval(consentSignatureQrState.countdownIntervalId);
        consentSignatureQrState.countdownIntervalId = 0;
      }
    };
    const resetConsentSignatureQrState = (preserveMainStatus = false)=>{
      stopConsentSignatureQrPolling();
      consentSignatureQrState.token = '';
      consentSignatureQrState.status = '';
      consentSignatureQrState.expiresAt = '';
      consentSignatureQrState.mobileUrl = '';
      consentSignatureQrState.cancelling = false;
      consentSignatureQrState.startedAt = 0;
      const qrEls = consentSignatureQrElements();
      if(qrEls?.qrImage) qrEls.qrImage.setAttribute('src', '');
      if(qrEls?.qrLink){
        qrEls.qrLink.removeAttribute('href');
        qrEls.qrLink.textContent = '';
      }
      if(qrEls?.previewWrap) qrEls.previewWrap.classList.add('d-none');
      if(qrEls?.previewImage) qrEls.previewImage.removeAttribute('src');
      setConsentSignatureQrModalState('Pendiente');
      updateConsentSignatureQrCountdown();
      syncConsentSignatureQrVerifyButton();
      if(!preserveMainStatus){
        setConsentRemoteSignatureStatus('');
      }
    };
    const fetchConsentSignatureTokenStatus = async (token)=>{
      const safeToken = sanitizeText(token);
      if(!safeToken) throw new Error('Token inválido.');
      const resp = await fetch(`/api/clinical/index.php/note-capture-tokens/${encodeURIComponent(safeToken)}`, {
        method: 'GET',
        headers: { Accept: 'application/json' },
        credentials: 'same-origin'
      });
      const json = await resp.json().catch(()=> null);
      if(!resp.ok || !json || json.ok !== true){
        const message = sanitizeText(json?.message || json?.error?.message || json?.error || `HTTP ${resp.status}`);
        throw new Error(message || 'No se pudo consultar el estado de la firma.');
      }
      return json?.data || {};
    };
    const cancelConsentSignatureTokenIfPending = async (reason = 'user_closed')=>{
      const token = sanitizeText(consentSignatureQrState.token);
      const status = sanitizeText(consentSignatureQrState.status || '').toLowerCase();
      if(!token || (status && status !== 'pending') || consentSignatureQrState.cancelling){
        return false;
      }
      consentSignatureQrState.cancelling = true;
      try{
        await fetch(`/api/clinical/index.php/note-capture-tokens/${encodeURIComponent(token)}/cancel`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json'
          },
          credentials: 'same-origin',
          body: JSON.stringify({ reason: sanitizeText(reason) || 'user_closed' })
        });
        consentSignatureQrState.status = 'cancelled';
        return true;
      }catch(_){
        return false;
      }finally{
        consentSignatureQrState.cancelling = false;
      }
    };
    const persistConsentRemoteSignature = (data = {})=>{
      const signature = (data?.signature && typeof data.signature === 'object') ? data.signature : null;
      const imageData = sanitizeText(signature?.image_data || '');
      if(!imageData){
        return;
      }
      const signedAt = sanitizeText(signature?.signed_at || formatNowSql());
      const signerName = sanitizeText(signature?.signer_name || state.form.firmante_nombre || '');
      const token = sanitizeText(data?.token || consentSignatureQrState.token || '');
      const applied = setConsentRemoteSignatureFromToken({
        image_data: imageData,
        signed_at: signedAt,
        signer_name: signerName,
        token
      });
      if(!applied){
        return;
      }
      setConsentRemoteSignatureStatus('Firma recibida desde celular. Se usará en esta emisión.', 'success');
      const qrEls = consentSignatureQrElements();
      if(qrEls?.previewImage){
        qrEls.previewImage.setAttribute('src', imageData);
      }
      if(qrEls?.previewWrap){
        qrEls.previewWrap.classList.remove('d-none');
      }
    };
    const syncConsentSignatureTokenStatus = async (opts = {})=>{
      const token = sanitizeText(consentSignatureQrState.token);
      if(!token) return;
      const currentStatus = sanitizeText(consentSignatureQrState.status).toLowerCase();
      if(currentStatus === 'expired' || currentStatus === 'cancelled' || currentStatus === 'uploaded') return;
      try{
        const data = await fetchConsentSignatureTokenStatus(token);
        const status = sanitizeText(data?.status || '').toLowerCase();
        consentSignatureQrState.status = status || 'pending';
        consentSignatureQrState.expiresAt = sanitizeText(data?.expires_at || consentSignatureQrState.expiresAt);
        if(status === 'uploaded' || status === 'consumed'){
          stopConsentSignatureQrPolling();
          setConsentSignatureQrModalState(status === 'consumed' ? 'Completado' : 'Firma recibida', 'success');
          persistConsentRemoteSignature(data);
          updateConsentSignatureQrCountdown();
          syncConsentSignatureQrVerifyButton();
          return;
        }
        if(status === 'expired'){
          stopConsentSignatureQrPolling();
          setConsentSignatureQrModalState('Expirado', 'error');
          setConsentRemoteSignatureStatus('La sesión de firma remota expiró. Genera una nueva.', 'error');
          updateConsentSignatureQrCountdown();
          syncConsentSignatureQrVerifyButton();
          return;
        }
        if(status === 'cancelled'){
          stopConsentSignatureQrPolling();
          setConsentSignatureQrModalState('Cancelado', 'error');
          setConsentRemoteSignatureStatus('La sesión de firma remota fue cancelada.', 'error');
          updateConsentSignatureQrCountdown();
          syncConsentSignatureQrVerifyButton();
          return;
        }
        setConsentSignatureQrModalState('Pendiente');
        updateConsentSignatureQrCountdown();
        syncConsentSignatureQrVerifyButton();
        if(opts.manual === true){
          setConsentRemoteSignatureStatus('Aún no se recibe firma. Sigue pendiente.', 'muted');
        }
      }catch(error){
        if(opts.manual === true){
          setConsentRemoteSignatureStatus(sanitizeText(error?.message || 'No se pudo verificar estado de la firma.'), 'error');
        }
      }
    };
    const startConsentSignatureQrPolling = ()=>{
      const token = sanitizeText(consentSignatureQrState.token);
      const status = sanitizeText(consentSignatureQrState.status || '').toLowerCase();
      if(!token || status === 'expired' || status === 'cancelled') return;
      stopConsentSignatureQrPolling();
      consentSignatureQrState.startedAt = Date.now();
      consentSignatureQrState.pollIntervalId = window.setInterval(()=>{
        syncConsentSignatureTokenStatus();
      }, CONSENT_SIGNATURE_QR_POLL_INTERVAL_MS);
      consentSignatureQrState.countdownIntervalId = window.setInterval(()=>{
        updateConsentSignatureQrCountdown();
      }, 1000);
      consentSignatureQrState.pollTimeoutId = window.setTimeout(()=>{
        if(consentSignatureQrState.status === 'uploaded') return;
        stopConsentSignatureQrPolling();
        setConsentRemoteSignatureStatus('No se recibió firma en el tiempo esperado. Puedes verificar manualmente.', 'muted');
        setConsentSignatureQrModalState('Pendiente');
        syncConsentSignatureQrVerifyButton();
      }, CONSENT_SIGNATURE_QR_MAX_DURATION_MS);
    };
    const createConsentSignatureToken = async ()=>{
      const context = await resolveConsentIdentityCaptureContext();
      if(!context.ok){
        throw new Error(context.error || 'No se pudo resolver el contexto del paciente.');
      }
      const body = {
        patient_id: context.patientId,
        encounter_key: context.encounterKey || null,
        note_context: 'consentimiento_firma_remota',
        expires_in_sec: 900
      };
      const resp = await fetch('/api/clinical/index.php/note-capture-tokens', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json'
        },
        credentials: 'same-origin',
        body: JSON.stringify(body)
      });
      const json = await resp.json().catch(()=> null);
      if(!resp.ok || !json || json.ok !== true){
        const message = sanitizeText(json?.message || json?.error?.message || json?.error || `HTTP ${resp.status}`);
        throw new Error(message || 'No se pudo generar el token de firma remota.');
      }
      const data = json?.data || {};
      consentSignatureQrState.token = sanitizeText(data?.token || '');
      consentSignatureQrState.status = sanitizeText(data?.status || 'pending').toLowerCase();
      consentSignatureQrState.expiresAt = sanitizeText(data?.expires_at || '');
      consentSignatureQrState.mobileUrl = sanitizeText(data?.mobile_url || '');
      if(!consentSignatureQrState.token){
        throw new Error('El servicio no devolvió token de firma remota.');
      }
      return data;
    };
    const openConsentSignatureQrModal = async ()=>{
      if(!els.signatureQrModal){
        setConsentRemoteSignatureStatus('No se encontró el modal QR de firma remota.', 'error');
        return;
      }
      if(!window.bootstrap || !window.bootstrap.Modal){
        setConsentRemoteSignatureStatus('Bootstrap Modal no está disponible para abrir firma remota.', 'error');
        return;
      }
      setConsentRemoteSignatureStatus('Generando QR para firma desde celular…', 'muted');
      setConsentSignatureQrModalState('Generando…');
      stopConsentSignatureQrPolling();
      await cancelConsentSignatureTokenIfPending('new_signature_token_requested');
      resetConsentSignatureQrState(true);
      try{
        const data = await createConsentSignatureToken();
        const mobileUrl = sanitizeText(data?.mobile_url || '');
        const qrValue = sanitizeText(data?.qr_value || mobileUrl);
        const normalizedQrValue = qrValue.startsWith('http')
          ? qrValue
          : `${window.location.origin}${qrValue.startsWith('/') ? qrValue : `/${qrValue}`}`;
        const qrImageUrl = `https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=${encodeURIComponent(normalizedQrValue)}`;
        const qrEls = consentSignatureQrElements();
        if(qrEls?.qrImage) qrEls.qrImage.setAttribute('src', qrImageUrl);
        if(qrEls?.qrLink){
          const href = mobileUrl || qrValue;
          const normalizedHref = href.startsWith('http')
            ? href
            : `${window.location.origin}${href.startsWith('/') ? href : `/${href}`}`;
          qrEls.qrLink.setAttribute('href', normalizedHref);
          qrEls.qrLink.textContent = normalizedHref;
        }
        setConsentSignatureQrModalState('Pendiente');
        setConsentRemoteSignatureStatus('Escanea el código QR y firma desde tu celular.', 'muted');
        updateConsentSignatureQrCountdown();
        syncConsentSignatureQrVerifyButton();
        const modal = (typeof window.bootstrap.Modal.getOrCreateInstance === 'function')
          ? window.bootstrap.Modal.getOrCreateInstance(els.signatureQrModal)
          : new window.bootstrap.Modal(els.signatureQrModal);
        modal.show();
        startConsentSignatureQrPolling();
      }catch(error){
        setConsentRemoteSignatureStatus(sanitizeText(error?.message || 'No se pudo iniciar la firma remota.'), 'error');
        setConsentSignatureQrModalState('Error', 'error');
      }
    };

    const consumeConsentSignatureToken = async (token, noteDocument = {})=>{
      const safeToken = sanitizeText(token || '');
      if(!safeToken) return;
      const noteDocumentId = sanitizeText(noteDocument?.document_db_id || noteDocument?.id || '');
      const noteDocumentUuid = sanitizeText(noteDocument?.document_uuid || noteDocument?.document_id || noteDocument?.uuid || '');
      if(!noteDocumentId && !noteDocumentUuid) return;
      try{
        await fetch(`/api/clinical/index.php/note-capture-tokens/${encodeURIComponent(safeToken)}/consume`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json'
          },
          credentials: 'same-origin',
          body: JSON.stringify({
            note_document_id: noteDocumentId || null,
            note_document_uuid: noteDocumentUuid || null
          })
        });
      }catch(_){}
    };

    const clearConsentIdentityFiles = ()=>{
      state.identityFiles = [];
      state.identityRemoteRefs = [];
      if(els.identityFiles) els.identityFiles.value = '';
      resetConsentIdentityQrState();
      renderIdentityFilesList();
    };

    const inferIdentityDocumentType = (file)=>{
      const mime = sanitizeText(file?.type || '').toLowerCase();
      const name = sanitizeText(file?.name || '').toLowerCase();
      if(mime.includes('pdf') || name.endsWith('.pdf')) return 'pdf';
      if(mime.startsWith('image/') || /\.(png|jpe?g|webp|gif|bmp|heic|heif)$/i.test(name)) return 'image';
      return '';
    };

    const syncFormStateToInputs = ()=>{
      const f = state.form;
      if(els.title) els.title.value = f.title;
      if(els.fullTitle) els.fullTitle.value = f.title;
      if(els.motivo) els.motivo.value = f.motivo;
      if(els.fullMotivo) els.fullMotivo.value = f.motivo;
      if(els.procedimiento) els.procedimiento.value = f.procedimiento;
      if(els.fullProcedimiento) els.fullProcedimiento.value = f.procedimiento;
      if(els.objetivo) els.objetivo.value = f.objetivo;
      if(els.fullObjetivo) els.fullObjetivo.value = f.objetivo;
      if(els.templateDesc) els.templateDesc.textContent = f.riesgos || 'Selecciona una plantilla para ver sus riesgos, beneficios y alternativas.';
      if(els.fullRiesgos) els.fullRiesgos.value = f.riesgos;
      if(els.beneficiosEsperados) els.beneficiosEsperados.value = f.beneficios_esperados;
      if(els.fullBeneficios) els.fullBeneficios.value = f.beneficios_esperados;
      if(els.alternativas) els.alternativas.value = f.alternativas;
      if(els.fullAlternativas) els.fullAlternativas.value = f.alternativas;
      if(els.consecuenciasNoAceptar) els.consecuenciasNoAceptar.value = f.consecuencias_no_aceptar;
      if(els.fullConsecuencias) els.fullConsecuencias.value = f.consecuencias_no_aceptar;
      if(els.autorizacionContingencias) els.autorizacionContingencias.checked = !!f.autorizacion_contingencias;
      if(els.fullAutContingencias) els.fullAutContingencias.checked = !!f.autorizacion_contingencias;
      if(els.firmanteTipo) els.firmanteTipo.value = f.firmante_tipo;
      if(els.fullFirmanteTipo) els.fullFirmanteTipo.value = f.firmante_tipo;
      if(els.firmanteNombre) els.firmanteNombre.value = f.firmante_nombre;
      if(els.fullFirmanteNombre) els.fullFirmanteNombre.value = f.firmante_nombre;
      if(els.firmanteParentesco) els.firmanteParentesco.value = f.firmante_parentesco;
      if(els.fullFirmanteParentesco) els.fullFirmanteParentesco.value = f.firmante_parentesco;
      if(els.testigo1Nombre) els.testigo1Nombre.value = f.testigo_1_nombre;
      if(els.fullTestigo1Nombre) els.fullTestigo1Nombre.value = f.testigo_1_nombre;
      if(els.testigo2Nombre) els.testigo2Nombre.value = f.testigo_2_nombre;
      if(els.fullTestigo2Nombre) els.fullTestigo2Nombre.value = f.testigo_2_nombre;
      if(els.legalConfirm) els.legalConfirm.checked = !!f.confirm_informed;
      if(els.fullLegalConfirm) els.fullLegalConfirm.checked = !!f.confirm_informed;
    };

    const updateConsentFormState = (key, value)=>{
      if(key === 'firmante_tipo' && value === 'paciente'){
        state.form.firmante_parentesco = 'self';
      }
      state.form[key] = value;
      syncFormStateToInputs();
    };

    const consentValidationFieldTargets = {
      patient_id: [],
      title: ['ci_title', 'ci_full_title'],
      procedimiento: ['ci_procedimiento', 'ci_full_procedimiento'],
      firmante_nombre: ['ci_firmante_nombre', 'ci_full_firmante_nombre'],
      firmante_parentesco: ['ci_firmante_parentesco', 'ci_full_firmante_parentesco'],
      confirm_informed: ['ci_confirm_informed', 'ci_full_confirm_informed']
    };

    const escapeHtml = (value)=> String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');

    const clearConsentValidationFeedback = ()=>{
      if(els.emitErrors){
        els.emitErrors.classList.add('d-none');
        els.emitErrors.innerHTML = '';
      }
      const fields = [
        els.title, els.fullTitle, els.procedimiento, els.fullProcedimiento,
        els.legalConfirm, els.fullLegalConfirm,
        els.firmanteNombre, els.fullFirmanteNombre,
        els.firmanteParentesco, els.fullFirmanteParentesco
      ];
      fields.forEach((el)=> el?.classList?.remove('is-invalid'));
    };

    const clearConsentValidationMarksForKey = (fieldKey = '')=>{
      const targets = consentValidationFieldTargets[fieldKey];
      if(!Array.isArray(targets) || targets.length === 0) return;
      targets.forEach((id)=>{
        const node = root.querySelector(`#${id}`);
        node?.classList?.remove('is-invalid');
      });
      const hasAnyInvalid = !!root.querySelector('.is-invalid');
      if(!hasAnyInvalid && els.emitErrors?.classList.contains('d-none') === false){
        els.emitErrors.classList.add('d-none');
        els.emitErrors.innerHTML = '';
      }
    };

    const showConsentValidationFeedback = (messages = [], markTargets = [])=>{
      clearConsentValidationFeedback();
      const list = Array.isArray(messages) ? messages.filter(Boolean) : [];
      if(list.length === 0) return;
      if(els.emitErrors){
        const heading = '<div class="fw-semibold mb-1">No se pudo emitir el consentimiento. Faltan los siguientes datos:</div>';
        const items = `<ul class="mb-0">${list.map((msg)=> `<li>${escapeHtml(msg)}</li>`).join('')}</ul>`;
        els.emitErrors.innerHTML = `${heading}${items}`;
        els.emitErrors.classList.remove('d-none');
      }
      const targetIds = Array.isArray(markTargets) ? markTargets : [];
      let firstInvalidNode = null;
      targetIds.forEach((id)=>{
        const node = root.querySelector(`#${id}`);
        if(node?.classList){
          node.classList.add('is-invalid');
          if(!firstInvalidNode) firstInvalidNode = node;
        }
      });
      if(firstInvalidNode){
        if(typeof firstInvalidNode.focus === 'function'){
          firstInvalidNode.focus({ preventScroll: true });
        }
        firstInvalidNode.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
      }
      els.emitErrors?.scrollIntoView?.({ behavior: 'smooth', block: 'center' });
    };

    const findFieldByLabel = (tab, labelText)=>{
      if(!tab) return null;
      const labels = Array.from(tab.querySelectorAll('label.form-label'));
      const target = labels.find((label)=> normalize(label.textContent).includes(normalize(labelText)));
      if(!target) return null;
      const wrap = target.closest('div');
      return wrap?.querySelector('input.form-control, textarea.form-control, select.form-select') || null;
    };

    const formatNowSql = ()=>{
      const now = new Date();
      const pad2 = (n)=> String(n).padStart(2, '0');
      return `${now.getFullYear()}-${pad2(now.getMonth() + 1)}-${pad2(now.getDate())} ${pad2(now.getHours())}:${pad2(now.getMinutes())}:${pad2(now.getSeconds())}`;
    };

    const resolveClinicalActorUserId = ()=>{
      const candidates = [
        window.mxmedUserId,
        window.__MXMED_USER_ID,
        window.mxmedStore && window.mxmedStore.user_id,
        document.body && document.body.dataset ? document.body.dataset.userId : '',
        'qa'
      ];
      for(const raw of candidates){
        const value = sanitizeText(raw);
        if(value) return value;
      }
      return 'qa';
    };

    const resolveActivePatientIdForConsent = ()=>{
      const fromHelper = (typeof getActivePatientId === 'function') ? sanitizeText(getActivePatientId()) : '';
      if(fromHelper) return fromHelper;
      const fromResolver = (typeof window.resolveActivePatientId === 'function') ? sanitizeText(window.resolveActivePatientId()) : '';
      if(fromResolver) return fromResolver;
      const fromStore = sanitizeText(window.mxmedStore?.currentPatientId || window.mxmedStore?.activePatientId);
      if(fromStore) return fromStore;
      return sanitizeText(pane?.dataset?.patientId || pane?.getAttribute?.('data-patient-id'));
    };

    const readPatientSnapshot = ()=>{
      const nombre = sanitizeText(pane.querySelector('[data-pac-nombre]')?.value);
      const ap1 = sanitizeText(pane.querySelector('[data-pac-apellido-paterno]')?.value);
      const ap2 = sanitizeText(pane.querySelector('[data-pac-apellido-materno]')?.value);
      const fullName = [nombre, ap1, ap2].filter(Boolean).join(' ').trim() || 'Paciente';
      const age = sanitizeText(pane.querySelector('[data-dg-edad]')?.textContent).replace(/^Edad:\s*/i, '') || '--';
      const sexoRaw = sanitizeText(pane.querySelector('input[name="pac-genero"]:checked')?.value);
      const sexo = sexoRaw === 'F' ? 'Femenino' : (sexoRaw === 'M' ? 'Masculino' : (sexoRaw === 'O' ? 'Otro' : '--'));
      return {
        full_name: fullName,
        age,
        sexo
      };
    };

    const readPatientContact = ()=>{
      const datosPane = pane.querySelector('#t-datos');
      const tel = sanitizeText(findFieldByLabel(datosPane, 'telefono celular')?.value || findFieldByLabel(datosPane, 'telefono')?.value || '');
      const mail = sanitizeText(findFieldByLabel(datosPane, 'correo electronico')?.value || '');
      const calle = sanitizeText(findFieldByLabel(datosPane, 'calle')?.value || '');
      const colonia = sanitizeText(findFieldByLabel(datosPane, 'colonia')?.value || '');
      const municipio = sanitizeText(findFieldByLabel(datosPane, 'municipio')?.value || '');
      const estado = sanitizeText(findFieldByLabel(datosPane, 'estado')?.value || '');
      const cp = sanitizeText(findFieldByLabel(datosPane, 'codigo postal')?.value || '');
      const domicilio = [calle, colonia, municipio, estado, cp ? `CP ${cp}` : ''].filter(Boolean).join(', ');
      return {
        telefono: tel,
        correo: mail,
        domicilio
      };
    };

    const showNotice = (msg)=>{
      if(!els.notice) return;
      const text = sanitizeText(msg);
      els.notice.textContent = text;
      els.notice.classList.toggle('d-none', !text);
    };

    const renderStep = ()=>{
      const isFullMode = state.mode === 'full';
      const isStep1 = state.step === 1;
      els.step1?.classList.toggle('d-none', isFullMode || !isStep1);
      els.step2?.classList.toggle('d-none', isFullMode || isStep1);
      els.fullView?.classList.toggle('d-none', !isFullMode);
      els.prevTop?.classList.toggle('d-none', isFullMode || isStep1);
      if(els.modeGuided) els.modeGuided.classList.toggle('active', !isFullMode);
      if(els.modeFull) els.modeFull.classList.toggle('active', isFullMode);

      if(isFullMode){
        if(els.prev) els.prev.disabled = true;
        els.next?.classList.add('d-none');
        els.save?.classList.remove('d-none');
        els.emit?.classList.remove('d-none');
        mountConsentSignatureBlock(els.signatureSlotFull);
        mountConsentIdentityBlock(els.identitySlotFull);
      }else{
        if(els.prev) els.prev.disabled = isStep1;
        els.next?.classList.toggle('d-none', !isStep1);
        els.save?.classList.toggle('d-none', isStep1);
        els.emit?.classList.toggle('d-none', isStep1);
        if(!isStep1){
          mountConsentSignatureBlock(els.signatureSlotStep2);
          mountConsentIdentityBlock(els.identitySlotStep2);
        }
      }

      if(isFullMode || !isStep1){
        initConsentSignaturePad();
        if(!state.signatureHasStroke){
          syncConsentSignatureCanvasSize();
        }
      }
      if(els.stepLabel){
        els.stepLabel.textContent = isFullMode ? 'Vista completa' : `Paso ${state.step} de 2`;
      }
    };

    const renderTemplates = ()=>{
      if(!els.template) return;
      if(els.template.dataset.canonicalReady === '1') return;
      els.template.innerHTML = '<option value="">Selecciona una plantilla</option>';
      state.templates.forEach((tpl)=>{
        const option = document.createElement('option');
        option.value = tpl.key;
        option.textContent = tpl.label;
        els.template.appendChild(option);
      });
      els.template.dataset.canonicalReady = '1';
    };

    const describeTemplate = (key)=>{
      const current = state.templates.find((tpl)=> tpl.key === sanitizeText(key));
      const riskText = current ? current.desc : '';
      state.form.riesgos = riskText;
      syncFormStateToInputs();
    };

    const fillWizardPatientFields = ()=>{
      const patient = readPatientSnapshot();
      const contact = readPatientContact();
      if(els.pacNombre) els.pacNombre.value = patient.full_name;
      if(els.pacEdad) els.pacEdad.value = patient.age;
      if(els.pacSexo) els.pacSexo.value = patient.sexo;
      if(els.pacTel) els.pacTel.value = contact.telefono;
      if(els.pacMail) els.pacMail.value = contact.correo;
      if(els.pacDom) els.pacDom.value = contact.domicilio;
      if(els.contactNotice){
        // TODO(CI): validar contacto obligatorio desde puertas de entrada (alta/cita/agenda), no en consentimiento.
        const missing = [];
        if(!sanitizeText(contact.telefono)) missing.push('teléfono');
        if(!sanitizeText(contact.correo)) missing.push('correo electrónico');
        if(missing.length){
          const detail = missing.length === 2 ? 'teléfono y correo electrónico' : missing[0];
          els.contactNotice.textContent = `Falta completar datos de contacto en la ficha del paciente (${detail}).`;
          els.contactNotice.classList.remove('d-none');
        }else{
          els.contactNotice.textContent = '';
          els.contactNotice.classList.add('d-none');
        }
      }
    };

    const resetWizard = ()=>{
      state.step = 1;
      state.mode = 'guided';
      state.draftId = '';
      showNotice('');
      if(els.template) els.template.value = '';
      state.form = {
        title: '',
        motivo: '',
        procedimiento: '',
        riesgos: '',
        objetivo: '',
        beneficios_esperados: '',
        alternativas: '',
        consecuencias_no_aceptar: '',
        autorizacion_contingencias: false,
        firmante_tipo: 'paciente',
        firmante_nombre: '',
        firmante_parentesco: 'self',
        testigo_1_nombre: '',
        testigo_2_nombre: '',
        confirm_informed: false
      };
      clearConsentValidationFeedback();
      syncFormStateToInputs();
      if(els.doctorName){
        els.doctorName.textContent = sanitizeText(document.querySelector('.user-id .name')?.textContent || 'Médico tratante');
      }
      cancelConsentSignatureTokenIfPending('consent_wizard_reset');
      resetConsentSignatureQrState();
      clearConsentRemoteSignature();
      cancelConsentIdentityTokenIfPending('consent_wizard_reset');
      clearConsentSignaturePad();
      clearConsentIdentityFiles();
      describeTemplate('');
      renderStep();
      els.wizard.classList.add('d-none');
    };

    const startDraft = ()=>{
      const patientId = resolveActivePatientIdForConsent();
      const hasPatient = !!patientId;
      els.ctxNotice?.classList.toggle('d-none', hasPatient);
      if(!hasPatient){
        showNotice('Selecciona paciente antes de crear el borrador.');
        return;
      }
      state.draftId = `cons_draft_${Date.now()}`;
      renderTemplates();
      fillWizardPatientFields();
      syncFormStateToInputs();
      clearConsentValidationFeedback();
      initConsentSignaturePad();
      syncConsentSignatureCanvasSize();
      cancelConsentSignatureTokenIfPending('consent_new_draft');
      resetConsentSignatureQrState();
      clearConsentRemoteSignature();
      clearConsentSignaturePad();
      clearConsentIdentityFiles();
      if(els.doctorName){
        els.doctorName.textContent = sanitizeText(document.querySelector('.user-id .name')?.textContent || 'Médico tratante');
      }
      state.step = 1;
      renderStep();
      showNotice('');
      els.wizard.classList.remove('d-none');
    };

    const openConsentViewer = (documentUuid)=>{
      const uuid = sanitizeText(documentUuid);
      if(!uuid) return false;
      const href = `/modules/clinical/ui/viewer.php?uuid=${encodeURIComponent(uuid)}&embed=1`;
      window.open(href, '_blank', 'noopener');
      return true;
    };

    const renderList = (items)=>{
      els.list.innerHTML = '';
      const list = Array.isArray(items) ? items : [];
      if(!list.length){
        els.empty.classList.remove('d-none');
        return;
      }
      els.empty.classList.add('d-none');
      list.forEach((doc)=>{
        const title = sanitizeText(doc.title || doc.summary || 'Consentimiento informado');
        const summary = sanitizeText(doc.summary || '');
        const dtRaw = sanitizeText(doc.event_datetime || doc.created_at || '');
        const dateText = dtRaw ? dtRaw.replace('T', ' ') : '—';
        const status = sanitizeText(doc.status || doc.payload?.consent?.status || 'draft');
        const uuid = sanitizeText(doc.document_uuid || '');
        const card = document.createElement('div');
        card.className = 'exp-card exp-card--secondary';
        card.setAttribute('role', 'button');
        card.setAttribute('tabindex', '0');
        if(uuid) card.dataset.docUuid = uuid;
        card.innerHTML = `
          <div class="exp-card-title d-flex align-items-center justify-content-between gap-2">
            <span>${title.replace(/</g, '&lt;')}</span>
            <span class="badge bg-light text-dark border">${status.replace(/</g, '&lt;')}</span>
          </div>
          <div class="small text-muted">${dateText.replace(/</g, '&lt;')}</div>
          ${summary ? `<div class="small mt-1">${summary.replace(/</g, '&lt;')}</div>` : ''}
          ${uuid ? '<div class="small mt-2"><span class="text-primary">Abrir detalle</span></div>' : ''}
        `;
        els.list.appendChild(card);
      });
    };

    const listCanonicalConsents = async ()=>{
      const patientId = resolveActivePatientIdForConsent();
      const hasPatient = !!patientId;
      els.ctxNotice?.classList.toggle('d-none', hasPatient);
      if(!hasPatient){
        renderList([]);
        return;
      }
      try{
        const url = `/api/clinical/index.php/documents?patient_id=${encodeURIComponent(patientId)}&document_type=consentimiento_informado&limit=50`;
        const resp = await fetch(url, {
          method: 'GET',
          headers: { Accept: 'application/json' },
          credentials: 'same-origin'
        });
        const json = await resp.json().catch(()=> null);
        const items = Array.isArray(json?.data?.items) ? json.data.items : [];
        const normalized = items.map((item)=>{
          const clinicalDoc = item?.clinical_document && typeof item.clinical_document === 'object' ? item.clinical_document : {};
          const payload = clinicalDoc?.payload && typeof clinicalDoc.payload === 'object' ? clinicalDoc.payload : {};
          return {
            document_uuid: sanitizeText(
              clinicalDoc.document_uuid
              || item?.document_uuid
              || item?.document_id
              || item?.links?.document_uuid
              || item?.id
              || ''
            ),
            title: clinicalDoc.title || item.title || '',
            summary: clinicalDoc.summary || item.summary || '',
            event_datetime: item.event_datetime || item.occurred_at || '',
            status: payload?.consent?.status || 'draft',
            payload
          };
        });
        renderList(normalized);
      }catch(_){
        renderList([]);
      }
    };

    const validateStep2 = (targetStatus = 'draft')=>{
      const missing = [];
      const addMissing = (fieldKey, label)=>{
        missing.push({ fieldKey, label });
      };
      const patientId = resolveActivePatientIdForConsent();
      const titleValue = sanitizeText(state.form.title || '');
      const contentValue = sanitizeText(state.form.procedimiento || '');
      if(targetStatus === 'granted'){
        if(!patientId){
          addMissing('patient_id', 'Paciente activo');
        }
        if(!titleValue){
          addMissing('title', 'Título');
        }
        if(!contentValue){
          addMissing('procedimiento', 'Descripción del procedimiento');
        }
        const signerName = sanitizeText(state.form.firmante_nombre || '');
        const signerType = sanitizeText(state.form.firmante_tipo || 'paciente');
        const signerRelation = sanitizeText(state.form.firmante_parentesco || '');
        if(!signerName){
          addMissing('firmante_nombre', 'Nombre del firmante');
        }
        if(signerType !== 'paciente' && !signerRelation){
          addMissing('firmante_parentesco', 'Relación o parentesco del firmante');
        }
        if(!state.form.confirm_informed){
          addMissing('confirm_informed', 'Confirmación legal');
        }
      }else{
        if(!patientId) addMissing('patient_id', 'Paciente activo');
      }
      const markTargets = Array.from(new Set(
        missing.flatMap((item)=> consentValidationFieldTargets[item.fieldKey] || [])
      ));
      return {
        valid: missing.length === 0,
        missing,
        errors: missing.map((item)=> item.label),
        markTargets
      };
    };

    const buildConsentLegalRenderedText = (data = {})=>{
      const nowSql = sanitizeText(data.nowSql || '');
      const eventDate = nowSql ? nowSql.slice(0, 10) : '';
      const eventTime = nowSql ? nowSql.slice(11, 19) : '';
      const patientName = sanitizeText(data.patientName || 'Paciente');
      const patientAge = sanitizeText(data.patientAge || '');
      const patientSexo = sanitizeText(data.patientSexo || '');
      const patientMeta = [patientAge ? `Edad: ${patientAge}` : '', patientSexo ? `Sexo: ${patientSexo}` : ''].filter(Boolean).join(' · ');
      const consentTitle = sanitizeText(data.consentTitle || 'Consentimiento informado');
      const procedureText = sanitizeText(data.procedureText || '');
      const risks = sanitizeText(data.risks || '');
      const benefits = sanitizeText(data.benefits || '');
      const alternatives = sanitizeText(data.alternatives || '');
      const consequences = sanitizeText(data.consequences || '');
      const contingencias = !!data.contingencies;
      const signerType = sanitizeText(data.signerTypeLabel || 'Paciente');
      const signerName = sanitizeText(data.signerName || patientName || '________________');
      const signerRelation = sanitizeText(data.signerRelation || '');
      const doctorName = sanitizeText(data.doctorName || 'Médico tratante');
      const doctorLicense = sanitizeText(data.doctorLicense || '');
      const witness1 = sanitizeText(data.witness1 || '');
      const witness2 = sanitizeText(data.witness2 || '');
      const objective = sanitizeText(data.objective || '');
      const motivo = sanitizeText(data.motivo || '');

      const lines = [];
      lines.push('MXMed');
      lines.push('CONSENTIMIENTO INFORMADO');
      lines.push('');
      lines.push(`Lugar y fecha: MXMed ${eventDate}${eventTime ? ` ${eventTime}` : ''}`.trim());
      lines.push('');
      lines.push(`Paciente: ${patientName}`);
      if(patientMeta) lines.push(patientMeta);
      lines.push('');
      lines.push(`Título del consentimiento: ${consentTitle}`);
      lines.push('');
      lines.push('Descripción del procedimiento / acto autorizado:');
      lines.push(procedureText || 'Sin descripción registrada.');
      if(motivo){
        lines.push('');
        lines.push(`Motivo clínico: ${motivo}`);
      }
      if(objective){
        lines.push('');
        lines.push(`Objetivo: ${objective}`);
      }
      lines.push('');
      lines.push('Riesgos y beneficios esperados:');
      lines.push(`Riesgos: ${risks || 'Conforme a la plantilla y explicación médica proporcionada.'}`);
      lines.push(`Beneficios esperados: ${benefits || 'Mejoría clínica o apoyo diagnóstico del paciente.'}`);
      lines.push('');
      lines.push('Alternativas:');
      lines.push(alternatives || 'Se explicaron alternativas razonables de manejo clínico.');
      lines.push('');
      lines.push('Consecuencias de no realizar el procedimiento:');
      lines.push(consequences || 'Se explicaron los posibles riesgos de no aceptar el procedimiento.');
      lines.push('');
      lines.push(`Autorización de contingencias y urgencias: ${contingencias ? 'Sí autorizo.' : 'No autorizo.'}`);
      lines.push('');
      lines.push('Declaro que la información fue explicada en lenguaje claro, resolviendo dudas y permitiendo decisión libre e informada.');
      lines.push('');
      lines.push('Firmas:');
      lines.push(`${signerType}: ${signerName}${signerRelation ? ` (${signerRelation})` : ''}`);
      lines.push(`Médico responsable: ${doctorName}${doctorLicense ? ` · Cédula: ${doctorLicense}` : ''}`);
      lines.push(`Testigo 1: ${witness1 || '________________'}`);
      lines.push(`Testigo 2: ${witness2 || '________________'}`);
      return lines.join('\n');
    };

    const buildCanonicalConsentDocument = async (targetStatus = 'draft')=>{
      const patientId = resolveActivePatientIdForConsent();
      if(!patientId) return { error: 'patient_id requerido.' };
      const normalizedStatus = targetStatus === 'granted' ? 'granted' : 'draft';
      const validation = validateStep2(normalizedStatus);
      if(!validation.valid){
        return {
          error: validation.errors.join(' · '),
          errors: validation.errors,
          markTargets: validation.markTargets,
          missing: validation.missing
        };
      }
      const actorUserId = resolveClinicalActorUserId();
      const actorName = sanitizeText(document.querySelector('.user-id .name')?.textContent || 'Médico tratante');
      const nowSql = formatNowSql();
      const consentType = sanitizeText(els.template?.value || 'otro');
      const templateLabel = sanitizeText(els.template?.selectedOptions?.[0]?.textContent || consentType || 'Consentimiento');
      const consentTitleRaw = sanitizeText(state.form.title || '');
      const consentTitle = consentTitleRaw && !/^consentimiento\s+para\s+/i.test(consentTitleRaw)
        ? `Consentimiento para ${consentTitleRaw}`
        : consentTitleRaw;
      const procedimiento = sanitizeText(state.form.procedimiento || '');
      const motivo = sanitizeText(state.form.motivo || '');
      const objetivo = sanitizeText(state.form.objetivo || '');
      const beneficiosEsperados = sanitizeText(state.form.beneficios_esperados || '');
      const alternativas = sanitizeText(state.form.alternativas || '');
      const consecuenciasNoAceptar = sanitizeText(state.form.consecuencias_no_aceptar || '');
      const autorizacionContingencias = !!state.form.autorizacion_contingencias;
      const firmanteTipo = sanitizeText(state.form.firmante_tipo || 'paciente');
      const firmanteNombre = sanitizeText(state.form.firmante_nombre || '');
      const firmanteParentesco = sanitizeText(state.form.firmante_parentesco || (firmanteTipo === 'paciente' ? 'self' : ''));
      const testigo1Nombre = sanitizeText(state.form.testigo_1_nombre || '');
      const testigo2Nombre = sanitizeText(state.form.testigo_2_nombre || '');
      const status = normalizedStatus;
      const titleBase = consentTitle || procedimiento || templateLabel || 'General';
      const title = `Consentimiento informado — ${titleBase}`;
      const summary = `${status} · ${consentTitle || templateLabel || consentType || 'consentimiento'} · ${nowSql.slice(0, 10)}`;
      const patientSnapshot = readPatientSnapshot();
      const contactSnapshot = readPatientContact();
      const contact = {
        telefono: sanitizeText(contactSnapshot.telefono || ''),
        correo: sanitizeText(contactSnapshot.correo || ''),
        domicilio: sanitizeText(els.pacDom?.value || contactSnapshot.domicilio || '')
      };
      let encounterKey = '';
      if(typeof window.getActiveEncounterKey === 'function'){
        encounterKey = sanitizeText(window.getActiveEncounterKey());
      }
      if(encounterKey && typeof window.mxmedIsOperationalEncounterForPatient === 'function'){
        const isOperational = window.mxmedIsOperationalEncounterForPatient(patientId, encounterKey) === true;
        if(!isOperational) encounterKey = '';
      }
      let appointmentId = '';
      if(typeof window.resolveActiveEncounterForPatient === 'function'){
        const resolved = await window.resolveActiveEncounterForPatient(patientId, { source: 'consentimiento_canonico_host' }).catch(()=> null);
        appointmentId = sanitizeText(resolved?.appointmentId || resolved?.appointment_id || '');
      }
      const context = {
        patient_id: patientId,
        care_setting: 'consulta'
      };
      if(encounterKey) context.encounter_key = encounterKey;
      if(appointmentId) context.appointment_id = appointmentId;
      const signerTypeLabelMap = {
        paciente: 'Paciente',
        tutor: 'Tutor',
        representante_legal: 'Representante legal',
        familiar_mas_cercano: 'Familiar más cercano en vínculo'
      };
      const signerTypeLabel = signerTypeLabelMap[firmanteTipo] || 'Paciente';
      const renderedText = buildConsentLegalRenderedText({
        nowSql,
        patientName: patientSnapshot.full_name,
        patientAge: patientSnapshot.age,
        patientSexo: patientSnapshot.sexo,
        consentTitle: consentTitle || templateLabel || 'Consentimiento informado',
        procedureText: procedimiento,
        risks: sanitizeText(state.form.riesgos || ''),
        benefits: beneficiosEsperados,
        alternatives: alternativas,
        consequences: consecuenciasNoAceptar,
        contingencies: autorizacionContingencias,
        signerTypeLabel,
        signerName: firmanteNombre || patientSnapshot.full_name,
        signerRelation: firmanteParentesco,
        doctorName: actorName,
        doctorLicense: sanitizeText(document.getElementById('ced-prof')?.value || ''),
        witness1: testigo1Nombre,
        witness2: testigo2Nombre,
        objective: objetivo,
        motivo
      });
      const patientSignatureCandidate = getActiveConsentPatientSignature(nowSql);
      const patientSignature = patientSignatureCandidate ? {
        ...patientSignatureCandidate,
        signer_name: sanitizeText(patientSignatureCandidate.signer_name || firmanteNombre || patientSnapshot.full_name || '') || null,
        signed_at: sanitizeText(patientSignatureCandidate.signed_at || nowSql) || nowSql
      } : null;
      const signatureSource = sanitizeText(patientSignature?.source || '');
      const signatureMode = signatureSource === 'remote_qr'
        ? 'drawn_remote'
        : (signatureSource === 'local_canvas' ? 'drawn_local' : (status === 'granted' ? 'acknowledged' : 'none'));
      const payload = {
        contract_version: 1,
        status,
        text: renderedText,
        rendered_text: renderedText,
        consent: {
          consent_type: consentType || 'otro',
          document_title: consentTitle || procedimiento || templateLabel || 'Consentimiento informado',
          status,
          granted_at: status === 'granted' ? nowSql : null,
          revoked_at: null
        },
        patient_snapshot: {
          full_name: patientSnapshot.full_name,
          identifier: '',
          contact
        },
        actor_snapshot: {
          user_id: actorUserId,
          full_name: actorName,
          license: sanitizeText(document.getElementById('ced-prof')?.value || '')
        },
        template_snapshot: {
          template_id: consentType || '',
          template_name: templateLabel || '',
          body_text: sanitizeText(state.form.riesgos || '')
        },
        consent_legal: {
          beneficios_esperados: beneficiosEsperados || null,
          alternativas: alternativas || null,
          consecuencias_no_aceptar: consecuenciasNoAceptar || null,
          autorizacion_contingencias: autorizacionContingencias,
          declaracion_lenguaje_claro: true
        },
        firmante: {
          tipo: firmanteTipo || 'paciente',
          nombre: firmanteNombre || patientSnapshot.full_name || null,
          relacion: firmanteParentesco || null,
          parentesco: firmanteParentesco || null
        },
        testigos: [
          { nombre: testigo1Nombre || null },
          { nombre: testigo2Nombre || null }
        ],
        signature_capabilities: {
          local_screen_signature: true,
          remote_qr_signature: true,
          supported_sources: ['local_drawn', 'remote_token']
        },
        legal: {
          risks_explained: status === 'granted',
          alternatives_explained: status === 'granted',
          questions_resolved: status === 'granted',
          voluntary_acceptance: status === 'granted' && !!state.form.confirm_informed
        },
        signatures: {
          patient_signed: status === 'granted' && (!!state.form.confirm_informed || !!patientSignature),
          doctor_signed: status === 'granted',
          witness_signed: false,
          signature_mode: signatureMode,
          patient: patientSignature
        },
        observations: motivo || ''
      };

      return {
        error: '',
        patientId,
        body: {
          type: 'consentimiento_informado',
          document_type: 'consentimiento_informado',
          title,
          summary,
          context,
          payload,
          event_datetime: nowSql,
          actor: { user_id: actorUserId },
          source: 'host_t_consent'
        }
      };
    };

    const uploadConsentIdentityAttachments = async (preparedBody)=>{
      const files = Array.isArray(state.identityFiles) ? state.identityFiles : [];
      const remoteRefs = Array.isArray(state.identityRemoteRefs) ? state.identityRemoteRefs : [];
      if(files.length === 0) return remoteRefs.slice();
      const patientId = sanitizeText(preparedBody?.context?.patient_id || '');
      if(!patientId) return remoteRefs.slice();
      const encounterKey = sanitizeText(preparedBody?.context?.encounter_key || '');
      const appointmentId = sanitizeText(preparedBody?.context?.appointment_id || '');
      const eventDatetime = sanitizeText(preparedBody?.event_datetime || formatNowSql());
      const refs = [];
      for(const file of files){
        const documentType = inferIdentityDocumentType(file);
        if(!documentType) continue;
        const formData = new FormData();
        formData.append('patient_id', patientId);
        formData.append('document_type', documentType);
        formData.append('title', `Anexo identidad firmante — ${sanitizeText(file?.name || 'archivo')}`);
        formData.append('summary', 'Anexo de identidad del firmante');
        formData.append('event_datetime', eventDatetime);
        if(encounterKey) formData.append('encounter_key', encounterKey);
        if(appointmentId) formData.append('appointment_id', appointmentId);
        const payload = {
          source: 'consentimiento_identidad_anexo',
          owner_document_type: 'consentimiento_informado',
          file_name: sanitizeText(file?.name || '')
        };
        formData.append('payload', JSON.stringify(payload));
        if(documentType === 'image'){
          formData.append('media_tag_key', 'identidad_firmante');
          formData.append('media_tag_label', 'Identidad del firmante');
        }
        formData.append('file', file);
        const resp = await fetch('/api/clinical/index.php/documents', {
          method: 'POST',
          headers: { Accept: 'application/json' },
          credentials: 'same-origin',
          body: formData
        });
        const json = await resp.json().catch(()=> null);
        if(!resp.ok || !json || json.ok !== true){
          const message = sanitizeText(json?.message || json?.error?.message || json?.error || `HTTP ${resp.status}`) || 'No se pudo subir anexo de identidad.';
          throw new Error(message);
        }
        const doc = (json?.data?.document && typeof json.data.document === 'object') ? json.data.document : {};
        refs.push({
          document_id: sanitizeText(doc.document_db_id || doc.id || ''),
          document_uuid: sanitizeText(doc.document_uuid || json?.data?.document_uuid || ''),
          title: sanitizeText(doc.title || `Anexo identidad firmante — ${sanitizeText(file?.name || '')}`),
          document_type: sanitizeText(doc.document_type || documentType),
          file_name: sanitizeText(file?.name || ''),
          source: 'consentimiento_identidad_local'
        });
      }
      const merged = [];
      const seen = new Set();
      const pushUnique = (entry)=>{
        const normalized = normalizeConsentIdentityRef(entry);
        if(!normalized) return;
        const key = `${normalized.document_id}|${normalized.document_uuid}|${normalized.note_capture_token}`;
        if(seen.has(key)) return;
        seen.add(key);
        merged.push(normalized);
      };
      remoteRefs.forEach(pushUnique);
      refs.forEach(pushUnique);
      return merged;
    };

    const saveCanonicalConsent = async (targetStatus = 'draft')=>{
      if(state.saving) return;
      const normalizedStatus = targetStatus === 'granted' ? 'granted' : 'draft';
      const prepared = await buildCanonicalConsentDocument(normalizedStatus);
      if(prepared.error){
        if(normalizedStatus === 'granted'){
          showConsentValidationFeedback(prepared.errors || [prepared.error], prepared.markTargets || []);
        }else{
          clearConsentValidationFeedback();
          showNotice(prepared.error);
        }
        return;
      }
      clearConsentValidationFeedback();
      state.saving = true;
      const isEmit = normalizedStatus === 'granted';
      if(els.save){
        els.save.disabled = true;
        if(!isEmit) els.save.textContent = 'Guardando...';
      }
      if(els.emit){
        els.emit.disabled = true;
        if(isEmit) els.emit.textContent = 'Emitiendo...';
      }
      showNotice('');
      try{
        const identityRefs = await uploadConsentIdentityAttachments(prepared.body);
        if(identityRefs.length){
          const payload = (prepared.body.payload && typeof prepared.body.payload === 'object') ? prepared.body.payload : {};
          payload.signer_identity_attachments = identityRefs;
          payload.attachments = payload.attachments && typeof payload.attachments === 'object' ? payload.attachments : {};
          payload.attachments.signer_identity = identityRefs;
          prepared.body.payload = payload;
        }
        const resp = await fetch('/api/clinical/index.php/documents', {
          method: 'POST',
          headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json'
          },
          body: JSON.stringify(prepared.body),
          credentials: 'same-origin'
        });
        const json = await resp.json().catch(()=> null);
        if(!resp.ok || !json || json.ok !== true){
          const msg = sanitizeText(json?.message || json?.error?.message || json?.error || `HTTP ${resp.status}`) || 'No se pudo guardar el consentimiento.';
          throw new Error(msg);
        }
        try{
          console.info('[mxmed-consent] save canonical ok', {
            patient_id: prepared.patientId,
            document_type: 'consentimiento_informado'
          });
        }catch(_){}
        const savedDocument = (json?.data?.document && typeof json.data.document === 'object') ? json.data.document : {};
        const remoteToken = sanitizeText(state.remoteSignature?.token || consentSignatureQrState.token || '');
        const remoteSource = sanitizeText(state.remoteSignature?.source || '');
        if(remoteToken && remoteSource === 'remote_qr'){
          consumeConsentSignatureToken(remoteToken, savedDocument);
        }
        resetWizard();
        listCanonicalConsents();
        try{
          window.dispatchEvent(new CustomEvent('mxmed:clinical-document-created', {
            detail: {
              patient_id: prepared.patientId,
              document_type: 'consentimiento_informado',
              source: 'consentimiento_canonico_host'
            }
          }));
        }catch(_){}
      }catch(err){
        showNotice(sanitizeText(err?.message || 'No se pudo guardar el consentimiento.'));
      }finally{
        state.saving = false;
        if(els.save){
          els.save.disabled = false;
          els.save.textContent = 'Guardar borrador';
        }
        if(els.emit){
          els.emit.disabled = false;
          els.emit.textContent = 'Emitir consentimiento';
        }
      }
    };

    els.newBtn.addEventListener('click', (event)=>{
      event.preventDefault();
      startDraft();
    });
    const bindSharedField = (inputEl, key, options = {})=>{
      if(!inputEl) return;
      const evtName = options.event || ((inputEl.tagName === 'SELECT' || inputEl.type === 'checkbox') ? 'change' : 'input');
      inputEl.addEventListener(evtName, ()=>{
        clearConsentValidationMarksForKey(key);
        const value = inputEl.type === 'checkbox' ? !!inputEl.checked : sanitizeText(inputEl.value || '');
        updateConsentFormState(key, value);
      });
    };
    bindSharedField(els.title, 'title');
    bindSharedField(els.fullTitle, 'title');
    bindSharedField(els.motivo, 'motivo');
    bindSharedField(els.fullMotivo, 'motivo');
    bindSharedField(els.procedimiento, 'procedimiento');
    bindSharedField(els.fullProcedimiento, 'procedimiento');
    bindSharedField(els.objetivo, 'objetivo');
    bindSharedField(els.fullObjetivo, 'objetivo');
    bindSharedField(els.fullRiesgos, 'riesgos');
    bindSharedField(els.beneficiosEsperados, 'beneficios_esperados');
    bindSharedField(els.fullBeneficios, 'beneficios_esperados');
    bindSharedField(els.alternativas, 'alternativas');
    bindSharedField(els.fullAlternativas, 'alternativas');
    bindSharedField(els.consecuenciasNoAceptar, 'consecuencias_no_aceptar');
    bindSharedField(els.fullConsecuencias, 'consecuencias_no_aceptar');
    bindSharedField(els.autorizacionContingencias, 'autorizacion_contingencias');
    bindSharedField(els.fullAutContingencias, 'autorizacion_contingencias');
    bindSharedField(els.firmanteTipo, 'firmante_tipo');
    bindSharedField(els.fullFirmanteTipo, 'firmante_tipo');
    bindSharedField(els.firmanteNombre, 'firmante_nombre');
    bindSharedField(els.fullFirmanteNombre, 'firmante_nombre');
    bindSharedField(els.firmanteParentesco, 'firmante_parentesco');
    bindSharedField(els.fullFirmanteParentesco, 'firmante_parentesco');
    bindSharedField(els.testigo1Nombre, 'testigo_1_nombre');
    bindSharedField(els.fullTestigo1Nombre, 'testigo_1_nombre');
    bindSharedField(els.testigo2Nombre, 'testigo_2_nombre');
    bindSharedField(els.fullTestigo2Nombre, 'testigo_2_nombre');
    bindSharedField(els.legalConfirm, 'confirm_informed');
    bindSharedField(els.fullLegalConfirm, 'confirm_informed');
    els.identityFiles?.addEventListener('change', ()=>{
      const files = Array.from(els.identityFiles?.files || []);
      state.identityFiles = files;
      renderIdentityFilesList();
    });
    els.identityQrOpen?.addEventListener('click', (event)=>{
      event.preventDefault();
      openConsentIdentityQrModal();
    });
    els.identityQrModal?.addEventListener('click', (event)=>{
      const copyBtn = event.target.closest('[data-action="ci-identity-qr-copy-link"]');
      if(copyBtn){
        event.preventDefault();
        const linkEl = els.identityQrModal.querySelector('[data-role="ci-identity-qr-link"]');
        const href = sanitizeText(linkEl?.getAttribute('href') || '');
        const text = sanitizeText(linkEl?.textContent || href);
        const value = href || text;
        if(!value){
          setConsentIdentityQrStatus('No hay enlace disponible para copiar todavía.', 'muted');
          return;
        }
        const fallbackCopy = ()=>{
          const temp = document.createElement('textarea');
          temp.value = value;
          temp.setAttribute('readonly', 'readonly');
          temp.style.position = 'absolute';
          temp.style.left = '-9999px';
          document.body.appendChild(temp);
          temp.select();
          try{ document.execCommand('copy'); }catch(_){}
          document.body.removeChild(temp);
        };
        if(navigator.clipboard?.writeText){
          navigator.clipboard.writeText(value).catch(()=> fallbackCopy());
        }else{
          fallbackCopy();
        }
        setConsentIdentityQrStatus('Enlace copiado. Ábrelo en tu celular para subir el anexo.', 'success');
        return;
      }
      const verifyBtn = event.target.closest('[data-action="ci-identity-qr-verify-now"]');
      if(!verifyBtn) return;
      event.preventDefault();
      syncConsentIdentityTokenStatus({ manual: true });
    });
    els.identityQrModal?.addEventListener('hidden.bs.modal', ()=>{
      cancelConsentIdentityTokenIfPending('consent_identity_qr_closed');
      stopConsentIdentityQrPolling();
    });
    els.signatureQrOpen?.addEventListener('click', (event)=>{
      event.preventDefault();
      openConsentSignatureQrModal();
    });
    els.signatureQrModal?.addEventListener('click', (event)=>{
      const copyBtn = event.target.closest('[data-action="ci-signature-qr-copy-link"]');
      if(copyBtn){
        event.preventDefault();
        const linkEl = els.signatureQrModal.querySelector('[data-role="ci-signature-qr-link"]');
        const href = sanitizeText(linkEl?.getAttribute('href') || '');
        const text = sanitizeText(linkEl?.textContent || href);
        const value = href || text;
        if(!value){
          setConsentRemoteSignatureStatus('No hay enlace disponible para copiar todavía.', 'muted');
          return;
        }
        const fallbackCopy = ()=>{
          const temp = document.createElement('textarea');
          temp.value = value;
          temp.setAttribute('readonly', 'readonly');
          temp.style.position = 'absolute';
          temp.style.left = '-9999px';
          document.body.appendChild(temp);
          temp.select();
          try{ document.execCommand('copy'); }catch(_){}
          document.body.removeChild(temp);
        };
        if(navigator.clipboard?.writeText){
          navigator.clipboard.writeText(value).catch(()=> fallbackCopy());
        }else{
          fallbackCopy();
        }
        setConsentRemoteSignatureStatus('Enlace copiado. Ábrelo en tu celular para firmar.', 'success');
        return;
      }
      const verifyBtn = event.target.closest('[data-action="ci-signature-qr-verify-now"]');
      if(!verifyBtn) return;
      event.preventDefault();
      syncConsentSignatureTokenStatus({ manual: true });
    });
    els.signatureQrModal?.addEventListener('hidden.bs.modal', ()=>{
      cancelConsentSignatureTokenIfPending('consent_signature_qr_closed');
      stopConsentSignatureQrPolling();
    });

    els.modeGuided?.addEventListener('click', (event)=>{
      event.preventDefault();
      state.mode = 'guided';
      renderStep();
    });
    els.modeFull?.addEventListener('click', (event)=>{
      event.preventDefault();
      state.mode = 'full';
      renderStep();
    });
    els.cancel?.addEventListener('click', (event)=>{
      event.preventDefault();
      resetWizard();
    });
    els.next?.addEventListener('click', (event)=>{
      event.preventDefault();
      state.step = 2;
      renderStep();
      describeTemplate(els.template?.value || '');
    });
    els.prev?.addEventListener('click', (event)=>{
      event.preventDefault();
      state.step = 1;
      renderStep();
    });
    els.prevTop?.addEventListener('click', (event)=>{
      event.preventDefault();
      state.step = 1;
      renderStep();
    });
    els.save?.addEventListener('click', (event)=>{
      event.preventDefault();
      saveCanonicalConsent('draft');
    });
    els.emit?.addEventListener('click', (event)=>{
      event.preventDefault();
      saveCanonicalConsent('granted');
    });
    els.template?.addEventListener('change', (event)=>{
      describeTemplate(event?.target?.value || '');
    });
    els.signatureClear?.addEventListener('click', (event)=>{
      event.preventDefault();
      clearConsentSignaturePad();
      setConsentSignaturePreferredSource(state.remoteSignature ? 'remote' : '');
    });
    els.signatureApply?.addEventListener('click', (event)=>{
      event.preventDefault();
      const signature = exportConsentSignatureData();
      if(!signature){
        if(state.remoteSignature){
          setConsentSignaturePreferredSource('remote');
          showNotice('Se mantiene la firma remota recibida.');
        }else{
          showNotice('Captura la firma en pantalla antes de aplicarla.');
        }
        return;
      }
      setConsentSignaturePreferredSource('local');
      setConsentRemoteSignatureStatus(state.remoteSignature ? 'Se usará la firma local aplicada.' : '', 'muted');
      showNotice('Firma aplicada para esta emisión.');
      updateSignatureStatus();
    });
    root.addEventListener('click', (event)=>{
      const clearRemoteBtn = event.target.closest('[data-action="ci-signature-clear-remote"]');
      if(!clearRemoteBtn) return;
      event.preventDefault();
      const hadRemote = !!state.remoteSignature;
      clearConsentRemoteSignature();
      setConsentSignaturePreferredSource(state.signatureHasStroke ? 'local' : '');
      if(hadRemote){
        setConsentRemoteSignatureStatus('Firma remota eliminada de esta emisión.', 'muted');
      }
    });
    window.addEventListener('resize', ()=>{
      if(state.mode === 'full' || state.step === 2){
        syncConsentSignatureCanvasSize();
      }
    });
    els.list.addEventListener('click', (event)=>{
      const card = event.target.closest('[data-doc-uuid]');
      if(!card) return;
      event.preventDefault();
      openConsentViewer(card.getAttribute('data-doc-uuid'));
    });
    els.list.addEventListener('keydown', (event)=>{
      if(event.key !== 'Enter' && event.key !== ' ') return;
      const card = event.target.closest('[data-doc-uuid]');
      if(!card) return;
      event.preventDefault();
      openConsentViewer(card.getAttribute('data-doc-uuid'));
    });

    window.addEventListener('expediente:patient-changed', ()=>{ listCanonicalConsents(); });
    window.addEventListener('mxmed:encounter-context-changed', ()=>{ listCanonicalConsents(); });
    pane.addEventListener('click', (event)=>{
      const tabBtn = event.target.closest('.nav-link[data-bs-target="#t-consent"]');
      if(!tabBtn) return;
      window.setTimeout(()=>{ listCanonicalConsents(); }, 80);
    });

    renderTemplates();
    initConsentSignaturePad();
    renderConsentRemoteSignaturePreview();
    setConsentRemoteSignatureStatus('');
    updateSignatureStatus();
    renderIdentityFilesList();
    renderStep();
    listCanonicalConsents();
  };
  setupConsentimientoCanonicoHost();

  const readExpedienteIdentityDraftFromDom = ()=>{
    return {
      nombre: String(nameInput?.value || '').trim(),
      apellido_paterno: String(apellidoPaternoInput?.value || '').trim(),
      apellido_materno: String(apellidoMaternoInput?.value || '').trim(),
      sexo: String(genderInputs.find((inp)=> inp.checked)?.value || '').trim(),
      dia: String(pane.querySelector('[data-dg-dia]')?.value || '').trim(),
      mes: String(pane.querySelector('[data-dg-mes]')?.value || '').trim(),
      anio: String(pane.querySelector('[data-dg-anio]')?.value || '').trim()
    };
  };

  const captureExpedienteIdentityDraft = (patientId)=>{
    const pid = String(patientId || '').trim();
    if(!pid) return;
    const draft = readExpedienteIdentityDraftFromDom();
    const hasData = Object.values(draft).some((val)=> String(val || '').trim() !== '');
    const drafts = ensurePatientIdentityDrafts();
    if(!hasData){
      delete drafts[pid];
      return;
    }
    drafts[pid] = draft;
    const fullName = [
      String(draft.nombre || '').trim(),
      String(draft.apellido_paterno || '').trim(),
      String(draft.apellido_materno || '').trim()
    ].filter(Boolean).join(' ').trim();
    if(fullName && fullName.toLowerCase() !== 'paciente'){
      try{
        rememberPatientLabel(pid, fullName);
        const currentPid = String(window.mxmedStore?.currentPatientId || window.mxmedStore?.activePatientId || '').trim();
        const currentEncounterKey = String(window.mxmedStore?.currentEncounterKey || '').trim();
        if(currentPid === pid && currentEncounterKey){
          rememberEncounterLabel(currentEncounterKey, fullName);
          const entry = window.mxmedStore?.activeEncounters?.[currentEncounterKey];
          if(entry && typeof entry === 'object'){
            entry.patient_label = fullName;
          }
        }
      }catch(_){}
    }
  };

  const applyExpedienteIdentityDraft = (patientId)=>{
    const pid = String(patientId || '').trim();
    if(!pid) return false;
    const drafts = ensurePatientIdentityDrafts();
    const draft = drafts[pid];
    if(!draft || typeof draft !== 'object') return false;

    if(nameInput) nameInput.value = String(draft.nombre || '');
    if(apellidoPaternoInput) apellidoPaternoInput.value = String(draft.apellido_paterno || '');
    if(apellidoMaternoInput) apellidoMaternoInput.value = String(draft.apellido_materno || '');

    const draftSexo = String(draft.sexo || '').trim();
    genderInputs.forEach((inp)=>{ inp.checked = !!draftSexo && inp.value === draftSexo; });

    const dd = pane.querySelector('[data-dg-dia]');
    const mm = pane.querySelector('[data-dg-mes]');
    const yy = pane.querySelector('[data-dg-anio]');
    if(dd) dd.value = String(draft.dia || '');
    if(mm) mm.value = String(draft.mes || '');
    if(yy) yy.value = String(draft.anio || '');

    updateGenderExtra();
    computeAge();
    pane.dispatchEvent(new CustomEvent('pac-age-changed'));
    return true;
  };

  const resetExpedienteIdentityFields = ()=>{
    if(nameInput) nameInput.value = '';
    if(apellidoPaternoInput) apellidoPaternoInput.value = '';
    if(apellidoMaternoInput) apellidoMaternoInput.value = '';
    genderInputs.forEach((inp)=>{ inp.checked = false; });
    const extraInput = genderExtra?.querySelector('input');
    if(extraInput){
      extraInput.value = '';
      extraInput.setAttribute('disabled', 'disabled');
    }
    const dd = pane.querySelector('[data-dg-dia]');
    const mm = pane.querySelector('[data-dg-mes]');
    const yy = pane.querySelector('[data-dg-anio]');
    if(dd) dd.value = '';
    if(mm) mm.value = '';
    if(yy) yy.value = '';
    const edadLbl = pane.querySelector('[data-dg-edad]');
    if(edadLbl) edadLbl.textContent = '--';
    const edadOk = pane.querySelector('[data-dg-ok]');
    if(edadOk) edadOk.style.display = 'none';
    pane.removeAttribute('data-exp-gender');

    delete pane.dataset.lastDxText;
    delete pane.dataset.lastDxDate;
    delete pane.dataset.lastDiagnosis;
    delete pane.dataset.lastDiagnosisDate;
    pane.removeAttribute('data-last-dx-text');
    pane.removeAttribute('data-last-dx-date');
    pane.removeAttribute('data-last-diagnosis');
    pane.removeAttribute('data-last-diagnosis-date');

    updateGenderExtra();
    computeAge();
    pane.dispatchEvent(new CustomEvent('pac-age-changed'));
  };

  const setActivePatientId = async (pid, opts = {})=>{
    const next = String(pid || '').trim();
    if(!next) return false;
    const current = String(getActivePatientId() || '').trim();
    const source = String(opts.source || '').trim();
    const isSearchOpen = source === 'search_open';
    const shouldSuppressAutoEncounterContext = opts.suppressEncounterAutoContext === true;
    const setSearchOpenSuppression = (enabled)=>{
      if(!shouldSuppressAutoEncounterContext) return;
      if(enabled){
        pane.dataset.encounterAutoContextMode = 'suppressed';
        pane.dataset.encounterAutoContextPatientId = next;
      }else{
        delete pane.dataset.encounterAutoContextMode;
        delete pane.dataset.encounterAutoContextPatientId;
      }
    };
    const maybeConfirmUnsavedNewPatientDraftExit = (reason = '')=>{
      if(opts.skipUnsavedNewPatientConfirm === true) return true;
      if(typeof window.canLeaveUnsavedNewPatientFlow === 'function'){
        return window.canLeaveUnsavedNewPatientFlow(reason || 'patient_change');
      }
      if(typeof window.mxmedHasUnsavedNewPatientDraft !== 'function') return true;
      let hasUnsaved = false;
      try{
        hasUnsaved = window.mxmedHasUnsavedNewPatientDraft() === true;
      }catch(_){
        hasUnsaved = false;
      }
      if(!hasUnsaved) return true;
      const allow = window.confirm('Hay datos sin guardar. ¿Deseas salir y perderlos?');
      if(allow && typeof window.mxmedClearNewPatientEntryDirty === 'function'){
        try{ window.mxmedClearNewPatientEntryDirty({ reason }); }catch(_){}
      }
      return allow;
    };
    if(isSearchOpen){
      console.info('[mxmed-search-open] neutral context start', { patient_id: next, current_patient_id: current || null });
      setSearchOpenSuppression(true);
    }
    if(current === next){
      if(isSearchOpen){
        if(window.mxmedStore && typeof window.mxmedStore === 'object'){
          window.mxmedStore.currentEncounterKey = '';
          window.mxmedStore.activeEncounterKey = '';
        }
        if(typeof window.setEncounterContextOnPane === 'function'){
          // En search_open no mover paciente aquí; solo limpiar encounter para evitar estado operativo heredado.
          try{ window.setEncounterContextOnPane('', ''); }catch(_){}
        }
        const p10Bar = document.getElementById('mm-p10-bar');
        if(p10Bar && p10Bar.dataset){
          p10Bar.dataset.encounterKey = '';
        }
        clearVisibleMotivoForPatientSwitch();
        let appliedFromDraft = applyExpedienteIdentityDraft(next);
        if(!appliedFromDraft){
          const hydrated = await hydrateIdentityDraftFromPatientsApi(next).catch(()=> false);
          if(hydrated){
            appliedFromDraft = applyExpedienteIdentityDraft(next);
          }
        }
        applyMotivoDraftForPatient(next);
        const prefillEncounterContext = { encounter_key: '' };
        maybeApplyContextualMotivoPrefill(next, prefillEncounterContext).catch(()=> null);
        console.info('[mxmed-search-open] patient hydration ready', { patient_id: next, hydrated: appliedFromDraft === true });
        applyPatientGate();
        if(opts.applyEntryRule !== false){
          applyExpedienteEntryTabRule({ context: 'search_open' });
        }
        window.__mxmedHeaderSyncOrigin = 'search_open_same_patient';
        syncExpedienteHeaderContext();
        console.info('[mxmed-search-open] neutral context complete', { patient_id: next });
        return true;
      }
      if(shouldSuppressAutoEncounterContext){
        setSearchOpenSuppression(true);
      }else{
        setSearchOpenSuppression(false);
      }
      applyPatientGate();
      return true;
    }
    if(next !== current && !maybeConfirmUnsavedNewPatientDraftExit('patient_change')){
      if(isSearchOpen){
        setSearchOpenSuppression(false);
      }
      return false;
    }
    if(opts.skipActiveEncounterConfirm !== true){
      const allowed = await maybeConfirmActiveEncounterBeforePatientChange(next);
      if(!allowed){
        if(isSearchOpen){
          setSearchOpenSuppression(false);
        }
        return false;
      }
    }
    if(current && current !== next){
      captureExpedienteIdentityDraft(current);
      captureCurrentMotivoDraftForPatient(current);
    }
    // Limpiar contexto visible de encounter del paciente saliente antes de hidratar el entrante.
    if(window.mxmedStore && typeof window.mxmedStore === 'object'){
      window.mxmedStore.currentEncounterKey = '';
      window.mxmedStore.activeEncounterKey = '';
    }
    if(typeof window.setEncounterContextOnPane === 'function'){
      // Evitar mutar patient_id del pane antes de hidratar identidad del entrante.
      try{ window.setEncounterContextOnPane('', ''); }catch(_){}
    }
    const p10Bar = document.getElementById('mm-p10-bar');
    if(p10Bar && p10Bar.dataset){
      p10Bar.dataset.encounterKey = '';
    }
    delete pane.dataset.newEntryMode;
    pane.removeAttribute('data-new-entry-mode');
    resetExpedienteIdentityFields();
    clearVisibleMotivoForPatientSwitch();
    let appliedFromDraft = applyExpedienteIdentityDraft(next);
    if(!appliedFromDraft){
      const hydrated = await hydrateIdentityDraftFromPatientsApi(next).catch(()=> false);
      if(hydrated){
        appliedFromDraft = applyExpedienteIdentityDraft(next);
      }
    }
    const nextGender = genderInputs.find(inp => inp.checked)?.value || pane.getAttribute('data-exp-gender') || '';
    setGenderAttr(nextGender);
    syncGineco(nextGender, false);
    pane.dataset.patientId = next;
    pane.dataset.activePatientId = next;
    pane.setAttribute('data-patient-id', next);
    pane.setAttribute('data-active-patient-id', next);
    window.mxmedActivePatientId = next;
    window.__MXMED_ACTIVE_PATIENT_ID = next;
    if(window.mxmedStore && typeof window.mxmedStore === 'object'){
      window.mxmedStore.activePatientId = next;
      window.mxmedStore.currentPatientId = next;
      if(typeof window.mxmedResolveCurrentEncounterForPatient === 'function'){
        window.mxmedStore.currentEncounterKey = window.mxmedResolveCurrentEncounterForPatient(next) || '';
      }
    }
    if(typeof window.mxmedSetCurrentPatientContext === 'function'){
      window.mxmedSetCurrentPatientContext(next, { sync:true });
    }
    if(shouldSuppressAutoEncounterContext){
      setSearchOpenSuppression(true);
      console.info('[mxmed-search-open] suppress auto encounter context', { patient_id: next });
    }else{
      setSearchOpenSuppression(false);
    }
    applyMotivoDraftForPatient(next);
    const prefillEncounterContext = {
      encounter_key: String(window.mxmedStore?.currentEncounterKey || '').trim()
    };
    maybeApplyContextualMotivoPrefill(next, prefillEncounterContext).catch(()=> null);
    if(isSearchOpen){
      console.info('[mxmed-search-open] patient hydration ready', { patient_id: next, hydrated_from_draft_or_api: appliedFromDraft === true });
    }
    captureExpedienteIdentityDraft(next);
    setSessionPatientId(next);
    setHashPatientId(next);
    if(opts.source !== 'hashchange' && opts.emitEvent === true){
      window.dispatchEvent(new Event('patient:selected'));
    }
    applyPatientGate();
    if(opts.applyEntryRule !== false){
      applyExpedienteEntryTabRule({ context: isSearchOpen ? 'search_open' : 'setActivePatientId' });
    }
    if(isSearchOpen){
      console.info('[mxmed-search-open] neutral context complete', { patient_id: next });
    }
    return true;
  };
  window.setActivePatientId = setActivePatientId;
  window.mxmedSetActivePatientId = setActivePatientId;
  window.mxmedShouldSuppressAutoEncounterContext = (patientId)=>{
    const pid = String(patientId || '').trim();
    if(!pid) return false;
    return String(pane.dataset?.encounterAutoContextMode || '').trim() === 'suppressed'
      && String(pane.dataset?.encounterAutoContextPatientId || '').trim() === pid;
  };
  window.mxmedClearSuppressedAutoEncounterContext = (patientId = '')=>{
    const pid = String(patientId || '').trim();
    const flaggedPid = String(pane.dataset?.encounterAutoContextPatientId || '').trim();
    if(pid && flaggedPid && pid !== flaggedPid) return false;
    if(String(pane.dataset?.encounterAutoContextMode || '').trim() !== 'suppressed') return false;
    delete pane.dataset.encounterAutoContextMode;
    delete pane.dataset.encounterAutoContextPatientId;
    return true;
  };

  const isInNewPatientEntryFlowView = ()=>{
    const expPane = document.getElementById('p-expediente');
    if(!expPane) return false;
    const inNewEntryMode = String(expPane.dataset?.newEntryMode || expPane.getAttribute('data-new-entry-mode') || '').trim() === '1';
    if(!inNewEntryMode) return false;
    return !expPane.classList.contains('d-none');
  };
  const canLeaveUnsavedNewPatientFlow = (reason = 'panel_navigation', toPanel = '')=>{
    if(!isInNewPatientEntryFlowView()) return true;
    const targetPanel = String(toPanel || '').trim();
    if(targetPanel === 'p-expediente') return true;
    if(typeof window.mxmedHasUnsavedNewPatientDraft !== 'function') return true;
    let hasUnsaved = false;
    try{
      hasUnsaved = window.mxmedHasUnsavedNewPatientDraft() === true;
    }catch(_){
      hasUnsaved = false;
    }
    if(!hasUnsaved) return true;
    const allow = window.confirm('Hay datos sin guardar. ¿Deseas salir y perderlos?');
    if(allow && typeof window.mxmedClearNewPatientEntryDirty === 'function'){
      try{ window.mxmedClearNewPatientEntryDirty({ reason }); }catch(_){}
    }
    return allow;
  };
  window.canLeaveUnsavedNewPatientFlow = canLeaveUnsavedNewPatientFlow;
  window.addEventListener('beforeunload', (ev)=>{
    if(typeof window.mxmedHasUnsavedNewPatientDraft !== 'function') return;
    let hasUnsaved = false;
    try{
      hasUnsaved = window.mxmedHasUnsavedNewPatientDraft() === true;
    }catch(_){
      hasUnsaved = false;
    }
    if(!hasUnsaved) return;
    ev.preventDefault();
    ev.returnValue = '';
  });
  document.addEventListener('click', (ev)=>{
    const navBtn = ev.target.closest('.menu-main[data-panel], .menu-main[data-group], .menu-sub-btn[data-panel]');
    if(!navBtn) return;
    const targetPanel = String(navBtn.getAttribute('data-panel') || '').trim();
    const targetGroup = String(navBtn.getAttribute('data-group') || '').trim();
    const resolveTargetPanelFromNav = ()=>{
      if(targetPanel) return targetPanel;
      if(!targetGroup) return '';
      const firstSub = document.querySelector(`.menu-sub[data-group="${targetGroup}"] .menu-sub-btn[data-panel]`);
      return String(firstSub?.getAttribute('data-panel') || '').trim();
    };
    const resolvedTargetPanel = resolveTargetPanelFromNav();
    if(resolvedTargetPanel === 'p-expediente') return;
    if(!targetPanel && !targetGroup) return;
    if(targetGroup && navBtn.classList.contains('active')){
      // Click para colapsar grupo activo: no abandona panel.
      return;
    }
    if(canLeaveUnsavedNewPatientFlow(targetGroup ? `menu_group_${targetGroup}` : 'panel_navigation', resolvedTargetPanel)) return;
    ev.preventDefault();
    ev.stopPropagation();
    if(typeof ev.stopImmediatePropagation === 'function'){
      ev.stopImmediatePropagation();
    }
  }, true);
  const wrapJumpToWithUnsavedGuard = ()=>{
    if(typeof window.jumpTo !== 'function') return;
    if(window.jumpTo.__mxmedUnsavedGuardWrapped === true) return;
    const originalJumpTo = window.jumpTo;
    const wrappedJumpTo = function(panelId){
      const nextPanel = String(panelId || '').trim();
      if(nextPanel && !canLeaveUnsavedNewPatientFlow('jump_to_panel', nextPanel)){
        return false;
      }
      return originalJumpTo.apply(window, arguments);
    };
    wrappedJumpTo.__mxmedUnsavedGuardWrapped = true;
    wrappedJumpTo.__mxmedUnsavedGuardOriginal = originalJumpTo;
    window.jumpTo = wrappedJumpTo;
  };
  wrapJumpToWithUnsavedGuard();
  window.setTimeout(wrapJumpToWithUnsavedGuard, 0);

  function getActivePatientId(){
    const fromPane = String(pane.dataset?.patientId || pane.getAttribute('data-patient-id') || '').trim();
    if(fromPane) return fromPane;

    const fromPaneActive = String(pane.dataset?.activePatientId || pane.getAttribute('data-active-patient-id') || '').trim();
    if(fromPaneActive) return fromPaneActive;

    const fromHash = getHashPatientId();
    if(fromHash) return fromHash;

    const fromSession = getSessionPatientId();
    if(fromSession) return fromSession;

    const fallback = [
      window.mxmedActivePatientId,
      window.__MXMED_ACTIVE_PATIENT_ID,
      window.mxmedStore && window.mxmedStore.activePatientId
    ];
    for(const raw of fallback){
      const value = String(raw || '').trim();
      if(value) return value;
    }
    return null;
  }
  window.resolveActivePatientId = getActivePatientId;
  window.mxmedResolveActivePatientId = getActivePatientId;

  const firstNonEmpty = (...values)=>{
    for(const raw of values){
      const value = String(raw ?? '').trim();
      if(value) return value;
    }
    return '';
  };

  const ensurePatientLabelCache = ()=>{
    if(!window.mxmedStore || typeof window.mxmedStore !== 'object'){
      window.mxmedStore = {};
    }
    if(!window.mxmedStore.patientLabelById || typeof window.mxmedStore.patientLabelById !== 'object'){
      window.mxmedStore.patientLabelById = {};
    }
    return window.mxmedStore.patientLabelById;
  };

  const rememberPatientLabel = (patientId, label)=>{
    const pid = String(patientId || '').trim();
    const safeLabel = String(label || '').trim();
    if(!pid || !safeLabel || safeLabel.toLowerCase() === 'paciente') return;
    const cache = ensurePatientLabelCache();
    cache[pid] = safeLabel;
  };
  window.mxmedRememberPatientLabel = rememberPatientLabel;
  const ensureEncounterLabelCache = ()=>{
    if(!window.mxmedStore || typeof window.mxmedStore !== 'object'){
      window.mxmedStore = {};
    }
    if(!window.mxmedStore.encounterLabelByKey || typeof window.mxmedStore.encounterLabelByKey !== 'object'){
      window.mxmedStore.encounterLabelByKey = {};
    }
    return window.mxmedStore.encounterLabelByKey;
  };
  const rememberEncounterLabel = (encounterKey, label)=>{
    const key = String(encounterKey || '').trim();
    const safeLabel = String(label || '').trim();
    if(!key || !safeLabel || safeLabel.toLowerCase() === 'paciente') return;
    const cache = ensureEncounterLabelCache();
    cache[key] = safeLabel;
  };

  const escapeHtml = (value)=> String(value || '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');

  const shortPatientLabel = (value)=>{
    const cleanLabel = String(value || '').replace(/\s+/g, ' ').trim();
    if(!cleanLabel) return '';
    const parts = cleanLabel.split(' ');
    if(parts.length >= 2){
      return `${parts[0]} ${parts[1]}`;
    }
    return parts[0];
  };
  const isGenericChipLabel = (label, patientId = '')=>{
    const value = String(label || '').replace(/\s+/g, ' ').trim();
    if(!value) return true;
    const lower = value.toLowerCase();
    const pid = String(patientId || '').trim().toLowerCase();
    if(lower === 'paciente') return true;
    if(/^paciente\b/.test(lower)) return true;
    if(pid && lower === pid) return true;
    return false;
  };

  const getPatientLabelForEncounter = (entry, opts = {})=>{
    const patientId = String(entry?.patient_id || '').trim();
    const encounterKey = String(entry?.encounter_key || '').trim();
    const currentFullName = String(opts.currentFullName || '').trim();
    const isCurrent = opts.isCurrent === true;
    const currentPatientId = String(opts.currentPatientId || '').trim();
    const entryLabel = String(entry?.patient_label || '').trim();
    if(entryLabel && !isGenericChipLabel(entryLabel, patientId)){
      if(encounterKey) rememberEncounterLabel(encounterKey, entryLabel);
      if(patientId) rememberPatientLabel(patientId, entryLabel);
      return entryLabel;
    }
    const cache = ensurePatientLabelCache();
    const encCache = ensureEncounterLabelCache();
    if(encounterKey && encCache[encounterKey]){
      const stable = String(encCache[encounterKey] || '').trim();
      if(stable && !isGenericChipLabel(stable, patientId)){
        entry.patient_label = stable;
        return stable;
      }
    }
    if(patientId && cache[patientId]){
      const stable = String(cache[patientId] || '').trim();
      if(stable && !isGenericChipLabel(stable, patientId)){
        if(encounterKey) rememberEncounterLabel(encounterKey, stable);
        entry.patient_label = stable;
        return stable;
      }
    }
    const draft = (currentPatientId && currentPatientId === patientId)
      ? ensurePatientIdentityDrafts()[patientId]
      : null;
    if(draft && typeof draft === 'object'){
      const draftName = [
        String(draft.nombre || '').trim(),
        String(draft.apellido_paterno || '').trim(),
        String(draft.apellido_materno || '').trim()
      ].filter(Boolean).join(' ').trim();
      if(draftName && !isGenericChipLabel(draftName, patientId)){
        rememberPatientLabel(patientId, draftName);
        if(encounterKey) rememberEncounterLabel(encounterKey, draftName);
        entry.patient_label = draftName;
        return draftName;
      }
    }
    const embeddedName = String(entry?.patient_name || entry?.patient_label || entry?.nombre_completo || '').trim();
    if(embeddedName && !isGenericChipLabel(embeddedName, patientId)){
      if(patientId) rememberPatientLabel(patientId, embeddedName);
      if(encounterKey) rememberEncounterLabel(encounterKey, embeddedName);
      entry.patient_label = embeddedName;
      return embeddedName;
    }
    if(isCurrent && currentFullName && currentFullName.toLowerCase() !== 'paciente'){
      // Persistencia segura: solo para el chip actual (patient_id + encounter_key actual).
      if(!isGenericChipLabel(currentFullName, patientId)){
        if(patientId) rememberPatientLabel(patientId, currentFullName);
        if(encounterKey) rememberEncounterLabel(encounterKey, currentFullName);
        entry.patient_label = currentFullName;
        return currentFullName;
      }
    }
    return '';
  };

  const renderActiveEncounterStrip = (opts = {})=>{
    if(!expHeaderActiveStrip || !expHeaderActiveStripScroll) return;
    if(String(pane.dataset?.newEntryMode || '').trim() === '1'){
      expHeaderActiveStrip.classList.add('d-none');
      expHeaderActiveStripScroll.innerHTML = '';
      const staleTitle = expHeaderActiveStrip.querySelector('.exp-h-active-strip-title');
      if(staleTitle) staleTitle.remove();
      return;
    }
    const store = (window.mxmedStore && typeof window.mxmedStore === 'object') ? window.mxmedStore : null;
    const activeMap = (store && store.activeEncounters && typeof store.activeEncounters === 'object')
      ? store.activeEncounters
      : {};
    const currentPatientId = String(store?.currentPatientId || store?.activePatientId || getActivePatientId() || '').trim();
    const currentEncounterKey = String(store?.currentEncounterKey || '').trim();
    const currentFullName = String(opts.currentFullName || '').trim();
    const entries = Object.values(activeMap).filter((entry)=>{
      if(!entry || typeof entry !== 'object') return false;
      const key = String(entry.encounter_key || '').trim();
      const pid = String(entry.patient_id || '').trim();
      const status = String(entry.status || '').trim();
      return !!key && !!pid && (status === 'consulta_activa' || status === 'consulta_pendiente_cierre');
    });

    if(!entries.length){
      expHeaderActiveStrip.classList.add('d-none');
      expHeaderActiveStripScroll.innerHTML = '';
      const staleTitle = expHeaderActiveStrip.querySelector('.exp-h-active-strip-title');
      if(staleTitle) staleTitle.remove();
      return;
    }
    // Mantener orden estable de apertura (orden de inserción en activeEncounters).

    const chips = entries.map((entry)=>{
      const encounterKey = String(entry.encounter_key || '').trim();
      const patientId = String(entry.patient_id || '').trim();
      const isCurrent = patientId && encounterKey && patientId === currentPatientId && encounterKey === currentEncounterKey;
      const stateLabel = isCurrent ? 'Actual' : 'Activa';
      const resolvedLabel = getPatientLabelForEncounter(entry, { isCurrent, currentFullName, currentPatientId });
      const label = shortPatientLabel(resolvedLabel);
      if(!label || isGenericChipLabel(label, patientId)) return '';
      const chipClass = isCurrent ? 'is-current' : '';
      const currentAttr = isCurrent ? ' aria-current="true"' : '';
      return `
        <button type="button" class="exp-h-active-chip ${chipClass}"${currentAttr} data-action="exp-switch-active-enc" data-pid="${escapeHtml(patientId)}" data-encounter-key="${escapeHtml(encounterKey)}">
          <span class="exp-h-active-chip-name">${escapeHtml(label)}</span>
          <span class="exp-h-active-chip-state">${stateLabel}</span>
        </button>
      `;
    }).filter(Boolean);
    if(!chips.length){
      expHeaderActiveStrip.classList.add('d-none');
      expHeaderActiveStripScroll.innerHTML = '';
      const staleTitle = expHeaderActiveStrip.querySelector('.exp-h-active-strip-title');
      if(staleTitle) staleTitle.remove();
      return;
    }
    let stripTitle = expHeaderActiveStrip.querySelector('.exp-h-active-strip-title');
    if(!stripTitle){
      stripTitle = document.createElement('span');
      stripTitle.className = 'exp-h-active-strip-title';
      expHeaderActiveStrip.prepend(stripTitle);
    }
    stripTitle.textContent = `CONSULTAS ACTIVAS · ${chips.length}`;
    expHeaderActiveStrip.classList.remove('d-none');
    expHeaderActiveStripScroll.innerHTML = chips.join('');
  };

  const toUserSex = (value)=>{
    const raw = String(value || '').trim().toUpperCase();
    if(raw === 'F') return 'Femenino';
    if(raw === 'M') return 'Masculino';
    if(raw === 'O') return 'Otro';
    return '--';
  };

  const mapEncounterOriginLabel = (value)=>{
    const raw = String(value || '').trim();
    if(!raw) return '';
    const key = raw.toLowerCase();
    const map = {
      agenda: 'Agenda',
      appointment: 'Cita programada',
      cita: 'Cita programada',
      walkin: 'Sin cita',
      walk_in: 'Sin cita',
      'walk-in': 'Sin cita',
      urgencias: 'Urgencias',
      emergency: 'Urgencias',
      referencia: 'Referencia',
      referral: 'Referencia',
      app: 'Aplicación',
      web: 'Portal web'
    };
    return map[key] || raw.replace(/[_-]+/g, ' ');
  };

  const resolveClinicalUserIdForHeader = ()=>{
    const candidates = [
      window.mxmedUserId,
      window.__MXMED_USER_ID,
      window.mxmedStore && window.mxmedStore.user_id,
      document.body && document.body.dataset ? document.body.dataset.userId : ''
    ];
    for(const raw of candidates){
      const value = String(raw || '').trim();
      if(value) return value;
    }
    return '';
  };

  const buildClinicalHeadersForHeader = (extraHeaders = {})=>{
    const headers = { Accept: 'application/json' };
    const userId = resolveClinicalUserIdForHeader();
    if(userId){
      headers['X-User-Id'] = userId;
    }
    Object.keys(extraHeaders).forEach((key)=>{ headers[key] = extraHeaders[key]; });
    return headers;
  };

  const resolveActiveEncounterForPatient = async (patientId, opts = {})=>{
    const safePatientId = String(patientId || '').trim();
    const force = !!opts.force;
    const source = String(opts.source || '').trim();
    if(!safePatientId){
      return {
        ok: false,
        hasActive: false,
        patientId: '',
        encounterKey: '',
        appointmentId: '',
        status: '',
        errorMessage: 'patient_id requerido'
      };
    }
    if(!force && activeEncounterLookupInFlight.has(safePatientId)){
      return activeEncounterLookupInFlight.get(safePatientId);
    }
    const request = fetch(`/api/clinical/index.php/patients/${encodeURIComponent(safePatientId)}/encounters/active`, {
      method: 'GET',
      headers: buildClinicalHeadersForHeader(),
      credentials: 'same-origin'
    }).then((resp)=> resp.json().catch(()=> null))
      .then((json)=>{
        const encounterKey = String(json?.data?.encounter_key || '').trim();
        return {
          ok: !!(json && json.ok === true),
          hasActive: !!encounterKey,
          patientId: safePatientId,
          encounterKey,
          appointmentId: String(json?.data?.appointment_id || '').trim(),
          status: String(json?.data?.status || '').trim(),
          errorMessage: (!json || json.ok === true)
            ? ''
            : String(json?.message || json?.error || '').trim(),
          source
        };
      })
      .catch((err)=>{
        return {
          ok: false,
          hasActive: false,
          patientId: safePatientId,
          encounterKey: '',
          appointmentId: '',
          status: '',
          errorMessage: String(err?.message || 'No se pudo consultar la consulta activa.').trim(),
          source
        };
      })
      .finally(()=>{
        activeEncounterLookupInFlight.delete(safePatientId);
      });
    activeEncounterLookupInFlight.set(safePatientId, request);
    return request;
  };
  window.resolveActiveEncounterForPatient = resolveActiveEncounterForPatient;

  const formatEncounterStartHour = (value)=>{
    const raw = String(value || '').trim();
    if(!raw) return '';
    const parsed = new Date(raw);
    if(Number.isNaN(parsed.getTime())) return '';
    try{
      return new Intl.DateTimeFormat('es-MX', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: false
      }).format(parsed);
    }catch(_){
      const hh = String(parsed.getHours()).padStart(2, '0');
      const mm = String(parsed.getMinutes()).padStart(2, '0');
      return `${hh}:${mm}`;
    }
  };

  const getEncounterDataForHeader = async (encounterKey)=>{
    const safeEncounterKey = String(encounterKey || '').trim();
    if(!safeEncounterKey) return null;
    if(typeof window.loadActiveEncounterPayload === 'function'){
      const payload = await window.loadActiveEncounterPayload(safeEncounterKey).catch(()=> null);
      if(payload && payload.data && typeof payload.data === 'object'){
        return payload.data;
      }
    }
    try{
      const resp = await fetch(`/api/clinical/index.php/encounters/${encodeURIComponent(safeEncounterKey)}`, {
        method: 'GET',
        headers: buildClinicalHeadersForHeader(),
        credentials: 'same-origin'
      });
      const json = await resp.json().catch(()=> null);
      if(json && json.ok === true && json.data && typeof json.data === 'object'){
        return json.data;
      }
    }catch(_){}
    return null;
  };

  const fetchActiveEncounterKeyForHeader = async (patientId)=>{
    const safePatientId = String(patientId || '').trim();
    if(!safePatientId) return '';
    const resolved = await resolveActiveEncounterForPatient(safePatientId, { source: 'header' }).catch(()=> null);
    return String(resolved?.encounterKey || '').trim();
  };
  const getCanonicalEncounterState = ()=>{
    const store = (window.mxmedStore && typeof window.mxmedStore === 'object') ? window.mxmedStore : null;
    if(!store) return null;
    const activeMap = (store.activeEncounters && typeof store.activeEncounters === 'object')
      ? store.activeEncounters
      : {};
    const patientId = String(store.currentPatientId || store.activePatientId || '').trim();
    let encounterKey = String(store.currentEncounterKey || '').trim();
    let entry = encounterKey ? activeMap[encounterKey] : null;
    if(!entry || String(entry.patient_id || '').trim() !== patientId){
      if(typeof window.mxmedResolveCurrentEncounterForPatient === 'function'){
        encounterKey = String(window.mxmedResolveCurrentEncounterForPatient(patientId) || '').trim();
        entry = encounterKey ? activeMap[encounterKey] : null;
      }
    }
    if(!entry){
      const compat = (store.activeEncounterState && typeof store.activeEncounterState === 'object')
        ? store.activeEncounterState
        : null;
      if(!compat) return null;
      return {
        status: String(compat.status || '').trim(),
        encounter_key: String(compat.encounter_key || '').trim(),
        origin: String(compat.origin || '').trim(),
        started_at: String(compat.started_at || '').trim()
      };
    }
    return {
      status: String(entry.status || '').trim(),
      encounter_key: String(entry.encounter_key || '').trim(),
      origin: String(entry.origin || '').trim(),
      started_at: String(entry.started_at || '').trim()
    };
  };

  const resolveEncounterKeyForHeader = async (patientId)=>{
    if(!String(patientId || '').trim()) return '';
    const fetched = await fetchActiveEncounterKeyForHeader(patientId);
    return fetched;
  };

  const reconcileActiveEntriesForPatient = (patientId, activeEncounterKey)=>{
    const pid = String(patientId || '').trim();
    if(!pid) return false;
    const store = (window.mxmedStore && typeof window.mxmedStore === 'object') ? window.mxmedStore : null;
    if(!store || !store.activeEncounters || typeof store.activeEncounters !== 'object') return false;
    const map = store.activeEncounters;
    const activeKey = String(activeEncounterKey || '').trim();
    const hasEntriesForPid = Object.values(map).some((entry)=>{
      if(!entry || typeof entry !== 'object') return false;
      return String(entry.patient_id || '').trim() === pid;
    });
    // Estado estable: paciente sin encounter activo real y sin entries en mapa.
    // Evita reconciliaciones/eventos repetitivos en cascada.
    if(!activeKey && !hasEntriesForPid){
      return false;
    }
    let changed = false;

    Object.keys(map).forEach((key)=>{
      const entry = map[key];
      if(!entry || typeof entry !== 'object'){
        delete map[key];
        changed = true;
        return;
      }
      const entryPid = String(entry.patient_id || '').trim();
      if(entryPid !== pid) return;
      const entryKey = String(entry.encounter_key || key || '').trim();
      if(!activeKey || entryKey !== activeKey){
        delete map[key];
        changed = true;
      }
    });

    // No promover nuevas entries desde lookup pasivo.
    // Solo mantener/actualizar entries ya operativas para este paciente.
    if(activeKey){
      const prev = (map[activeKey] && typeof map[activeKey] === 'object') ? map[activeKey] : null;
      if(prev && String(prev.patient_id || '').trim() === pid){
        const next = Object.assign({}, prev, {
          encounter_key: activeKey,
          patient_id: pid,
          status: 'consulta_activa',
          last_activity_at: String(prev.last_activity_at || new Date().toISOString()).trim()
        });
        map[activeKey] = next;
        if(
          String(prev.encounter_key || '').trim() !== String(next.encounter_key || '').trim()
          || String(prev.patient_id || '').trim() !== String(next.patient_id || '').trim()
          || String(prev.status || '').trim() !== String(next.status || '').trim()
        ){
          changed = true;
        }
      }
    }

    if(changed && window.mxmedStore && typeof window.mxmedStore === 'object'){
      const currentPid = String(window.mxmedStore.currentPatientId || window.mxmedStore.activePatientId || '').trim();
      if(currentPid && currentPid === pid){
        window.mxmedStore.currentEncounterKey = activeKey || '';
        window.mxmedStore.activeEncounterKey = activeKey || '';
      }
      try{
        window.dispatchEvent(new CustomEvent('mxmed:encounter-changed', {
          detail: {
            patient_id: currentPid || pid,
            encounter_key: String(window.mxmedStore.currentEncounterKey || '').trim()
          }
        }));
      }catch(_){}
    }

    return changed;
  };
  window.mxmedReconcileActiveEntriesForPatient = reconcileActiveEntriesForPatient;
  window.mxmedHasActiveEntriesForPatient = (patientId)=>{
    const pid = String(patientId || '').trim();
    if(!pid) return false;
    const store = (window.mxmedStore && typeof window.mxmedStore === 'object') ? window.mxmedStore : null;
    if(!store || !store.activeEncounters || typeof store.activeEncounters !== 'object') return false;
    return Object.values(store.activeEncounters).some((entry)=>{
      if(!entry || typeof entry !== 'object') return false;
      return String(entry.patient_id || '').trim() === pid;
    });
  };
  const shouldLogSearchOpenAutoEncounterBlocked = (patientId, encounterKey)=>{
    const pid = String(patientId || '').trim();
    const eKey = String(encounterKey || '').trim();
    if(!pid || !eKey) return false;
    const now = Date.now();
    const state = window.__mxmedAutoEncounterBlockedLogState && typeof window.__mxmedAutoEncounterBlockedLogState === 'object'
      ? window.__mxmedAutoEncounterBlockedLogState
      : (window.__mxmedAutoEncounterBlockedLogState = { key: '', ts: 0 });
    const key = `${pid}::${eKey}`;
    if(state.key === key && (now - Number(state.ts || 0)) < 2500){
      return false;
    }
    state.key = key;
    state.ts = now;
    return true;
  };
  const syncExpedienteHeaderContext = async ()=>{
    if(!expHeader) return;
    if(pane.classList.contains('d-none')) return;
    const runToken = ++headerSyncToken;
    const setNodeText = (node, value)=>{
      if(!node) return;
      const next = String(value || '');
      if(node.textContent !== next){
        node.textContent = next;
      }
    };
    const toggleNodeHidden = (node, hidden)=>{
      if(!node) return;
      node.classList.toggle('d-none', !!hidden);
    };

    // 1) Identidad fija del paciente (siempre visible cuando hay contexto)
    const patientId = String(getActivePatientId() || '').trim();
    const nombre = pane.querySelector('[data-pac-nombre]')?.value?.trim() || '';
    const apPat = pane.querySelector('[data-pac-apellido-paterno]')?.value?.trim() || '';
    const apMat = pane.querySelector('[data-pac-apellido-materno]')?.value?.trim() || '';
    const fullName = [nombre, apPat, apMat].filter(Boolean).join(' ').trim() || 'Paciente';
    const ageText = pane.querySelector('[data-dg-edad]')?.textContent?.trim() || '--';
    const sexoVal = firstNonEmpty(
      pane.querySelector('input[name="pac-genero"]:checked')?.value || '',
      pane.getAttribute('data-exp-gender')
    );
    const sexText = toUserSex(sexoVal);
    setNodeText(expHeaderName, fullName);
    setNodeText(expHeaderAge, ageText || '--');
    setNodeText(expHeaderSex, sexText);

    // 2) Contexto clínico opcional (motivo)
    const motivoConsulta = readMotivoConsulta();
    const lastDxLabel = motivoConsulta ? `Motivo de consulta: ${motivoConsulta}` : '';
    if(expHeaderLastDx){
      setNodeText(expHeaderLastDx, lastDxLabel);
      toggleNodeHidden(expHeaderLastDx, !lastDxLabel);
    }
    renderActividadClinicaContext();

    // 3) Estado clínico (consulta activa)
    const syncOrigin = String(window.__mxmedHeaderSyncOrigin || 'direct').trim() || 'direct';
    const resolvedEncounterKey = await resolveEncounterKeyForHeader(patientId);
    const suppressAutoEncounterContext = (typeof window.mxmedShouldSuppressAutoEncounterContext === 'function')
      ? window.mxmedShouldSuppressAutoEncounterContext(patientId) === true
      : false;
    if(suppressAutoEncounterContext && resolvedEncounterKey && shouldLogSearchOpenAutoEncounterBlocked(patientId, resolvedEncounterKey)){
      if (window.__MXMED_DEBUG__ === true) {
        console.info('[mxmed-search-open] auto encounter blocked', {
          patient_id: patientId,
          encounter_key: resolvedEncounterKey
        });
      }
    }
    const isOperationalEncounter = (typeof window.mxmedIsOperationalEncounterForPatient === 'function')
      ? window.mxmedIsOperationalEncounterForPatient(patientId, resolvedEncounterKey) === true
      : false;
    const encounterKey = (suppressAutoEncounterContext || !isOperationalEncounter) ? '' : resolvedEncounterKey;
    if(runToken !== headerSyncToken) return;
    const hasPatientContext = !!patientId;
    const hasActiveEncounter = !!encounterKey;
    const hasEntriesForPatient = (typeof window.mxmedHasActiveEntriesForPatient === 'function')
      ? window.mxmedHasActiveEntriesForPatient(patientId)
      : null;
    const isStableClosedFromEncounterChangedEvent =
      syncOrigin === 'event:mxmed:encounter-changed'
      && !hasActiveEncounter
      && hasEntriesForPatient === false;
    if(isStableClosedFromEncounterChangedEvent){
      // Estado cerrado estable: mantener strip en sincronía inmediata
      // aunque no haya más trabajo de reconciliación para este paciente.
      renderActiveEncounterStrip({ currentFullName: fullName });
      return;
    }
    const reconciledCurrentPatient = reconcileActiveEntriesForPatient(patientId, encounterKey);

    if(reconciledCurrentPatient && window.mxmedStore && typeof window.mxmedStore === 'object'){
      const currentPid = String(window.mxmedStore.currentPatientId || window.mxmedStore.activePatientId || '').trim();
      if(currentPid && currentPid === patientId){
        window.mxmedStore.currentEncounterKey = hasActiveEncounter ? encounterKey : '';
      }
    }

    if(!hasActiveEncounter && typeof window.setEncounterContextOnPane === 'function'){
      try{ window.setEncounterContextOnPane('', patientId); }catch(_){}
    }
    if(!hasActiveEncounter && window.mxmedStore && typeof window.mxmedStore === 'object'){
      if(String(window.mxmedStore.currentPatientId || window.mxmedStore.activePatientId || '').trim() === patientId){
        window.mxmedStore.currentEncounterKey = '';
        window.mxmedStore.activeEncounterKey = '';
      }
    }

    toggleNodeHidden(expHeaderActiveWrap, !hasActiveEncounter);
    toggleNodeHidden(expHeaderActiveBadge, !hasActiveEncounter);
    toggleNodeHidden(expHeaderNeutral, hasActiveEncounter || !hasPatientContext);
    toggleNodeHidden(expHeaderStartBtn, hasActiveEncounter || !hasPatientContext);
    toggleNodeHidden(expHeaderCloseBtn, !hasActiveEncounter);

    // 4) Multi-activo (strip actual; conservar sin rediseño)
    renderActiveEncounterStrip({ currentFullName: fullName });

    if(!hasActiveEncounter){
      if(expHeaderOrigin){
        setNodeText(expHeaderOrigin, '');
        toggleNodeHidden(expHeaderOrigin, true);
      }
      if(expHeaderStart){
        setNodeText(expHeaderStart, '');
        toggleNodeHidden(expHeaderStart, true);
      }
      return;
    }

    let originText = '';
    let startText = '';
    const encounterData = await getEncounterDataForHeader(encounterKey);
    if(runToken !== headerSyncToken) return;
    if(encounterData){
      const originRaw = firstNonEmpty(
        encounterData.origin,
        encounterData.source,
        encounterData.encounter_origin,
        encounterData.appointment_origin,
        encounterData.encounter_source,
        encounterData.channel,
        encounterData.encounter_type
      );
      const mappedOrigin = mapEncounterOriginLabel(originRaw);
      if(mappedOrigin){
        originText = `Origen: ${mappedOrigin}`;
      }
      const startRaw = firstNonEmpty(
        encounterData.started_at,
        encounterData.start_at,
        encounterData.encounter_dt,
        encounterData.opened_at,
        encounterData.created_at
      );
      const startHour = formatEncounterStartHour(startRaw);
      if(startHour){
        startText = `Inicio: ${startHour}`;
      }
    }

    if(expHeaderOrigin){
      setNodeText(expHeaderOrigin, originText);
      toggleNodeHidden(expHeaderOrigin, !originText);
    }
    if(expHeaderStart){
      setNodeText(expHeaderStart, startText);
      toggleNodeHidden(expHeaderStart, !startText);
    }
  };
  const setCurrentEncounterForPatient = (patientId, encounterKey, opts = {})=>{
    const pid = String(patientId || '').trim();
    const eKey = String(encounterKey || '').trim();
    if(!pid){
      return { changed: false, patientId: '', encounterKey: '' };
    }
    const store = (window.mxmedStore && typeof window.mxmedStore === 'object') ? window.mxmedStore : null;
    if(!store){
      return { changed: false, patientId: pid, encounterKey: eKey };
    }

    const prevPatientId = String(store.currentPatientId || store.activePatientId || '').trim();
    const prevEncounterKey = String(store.currentEncounterKey || store.activeEncounterKey || '').trim();
    const changed = prevPatientId !== pid || prevEncounterKey !== eKey;

    store.currentPatientId = pid;
    store.activePatientId = pid;
    store.currentEncounterKey = eKey;
    store.activeEncounterKey = eKey;
    if(typeof window.setEncounterContextOnPane === 'function'){
      try{ window.setEncounterContextOnPane(eKey, pid); }catch(_){}
    }
    if(eKey && typeof window.mxmedClearSuppressedAutoEncounterContext === 'function'){
      window.mxmedClearSuppressedAutoEncounterContext(pid);
    }
    const p10Bar = document.getElementById('mm-p10-bar');
    if(p10Bar && p10Bar.dataset){
      p10Bar.dataset.encounterKey = eKey || '';
    }

    if(!changed){
      return { changed: false, patientId: pid, encounterKey: eKey };
    }

    const source = String(opts.source || '').trim();
    if(opts.skipEvents !== true){
      const detail = { patientId: pid, encounterKey: eKey, source };
      try{
        window.dispatchEvent(new CustomEvent('mxmed:encounter-context-changed', { detail }));
      }catch(_){}
      try{
        window.dispatchEvent(new CustomEvent('mxmed:encounter-changed', {
          detail: { patient_id: pid, encounter_key: eKey, source }
        }));
      }catch(_){}
    }else if(opts.syncHeader !== false){
      window.__mxmedHeaderSyncOrigin = source ? `setCurrentEncounterForPatient:${source}` : 'setCurrentEncounterForPatient';
      try{ syncExpedienteHeaderContext(); }catch(_){}
    }

    return { changed: true, patientId: pid, encounterKey: eKey };
  };
  window.setCurrentEncounterForPatient = setCurrentEncounterForPatient;
  window.mxmedSetCurrentEncounterForPatient = setCurrentEncounterForPatient;

  const applyPatientGate = ()=>{
    const gateOn = String(getActivePatientId() || '').trim() !== '';
    const isFemaleExp = normalizeExpGender(pane.getAttribute('data-exp-gender')) === 'F';
    const panes = Array.from(pane.querySelectorAll('.tab-content .tab-pane'));
    const nonDatosLinks = tabs.filter(btn => btn.getAttribute('data-tab-key') !== 't-datos');
    const nonDatosPanes = panes.filter(p => p.id !== 't-datos');

    if (!gateOn) {
      nonDatosLinks.forEach(btn => btn.closest('.nav-item')?.classList.add('d-none'));
      nonDatosPanes.forEach(p => {
        p.classList.add('d-none');
        p.classList.remove('show', 'active');
      });
      tabs.forEach(btn => btn.classList.remove('active'));
      datosTabLink?.classList.add('active');
      panes.forEach(p => p.classList.remove('show', 'active'));
      if (datosTabPane) {
        datosTabPane.classList.remove('d-none');
        datosTabPane.classList.add('show', 'active');
      }
      syncTabRowGridColumns();
      return;
    }

    nonDatosLinks.forEach(btn => {
      const item = btn.closest('.nav-item');
      if (!item) return;
      if (item.hasAttribute('data-tab-technical')) {
        item.classList.add('d-none');
        const technicalTarget = sanitizeText(btn.getAttribute('data-bs-target'));
        const technicalPane = technicalTarget ? pane.querySelector(technicalTarget) : null;
        if (technicalPane) {
          technicalPane.classList.add('d-none');
          technicalPane.classList.remove('show', 'active');
        }
        btn.classList.remove('active');
        btn.setAttribute('aria-selected', 'false');
        return;
      }
      const conditional = String(item.getAttribute('data-tab-conditional') || '').trim();
      const shouldShow = conditional !== 'gineco' || isFemaleExp;
      item.classList.toggle('d-none', !shouldShow);
    });
    nonDatosPanes.forEach(p => {
      const isGinePane = p.id === 't-gineco' || p.matches('.gyn-panel,[data-exp-section="gineco"]');
      if(isGinePane && !isFemaleExp){
        p.classList.add('d-none');
        p.classList.remove('show', 'active');
        return;
      }
      p.classList.remove('d-none');
    });
    const hasActiveVisiblePane = panes.some((p)=> p.classList.contains('active') && !p.classList.contains('d-none'));
    if(!hasActiveVisiblePane && datosTabPane){
      tabs.forEach((btn)=>{
        const isDatos = btn === datosTabLink;
        btn.classList.toggle('active', isDatos);
        btn.setAttribute('aria-selected', isDatos ? 'true' : 'false');
      });
      panes.forEach((p)=> p.classList.remove('show', 'active'));
      datosTabPane.classList.remove('d-none');
      datosTabPane.classList.add('show', 'active');
    }
    syncTabRowGridColumns();
  };

  const syncState = (opts={})=>{
    const ready = basicsReady();
    tabs.forEach((btn)=>{
      toggleTabState(btn, false);
    });
    const genero = genderInputs.find(r=>r.checked)?.value || '';
    setGenderAttr(genero);
    syncGineco(genero, opts.allowNavigate);
    updateGenderExtra();
    applyPatientGate();
    syncExpedienteHeaderContext();
    if(!ready){
      showFirstAvailable();
    }
  };
  nameInput?.addEventListener('input', ()=> syncState({ allowNavigate:true }));
  genderInputs.forEach(r=> r.addEventListener('change', ()=>{
    const selected = genderInputs.find(inp => inp.checked)?.value || '';
    syncGineco(selected, true);
    syncState({ allowNavigate:true });
  }));

  const getTabIdFromTarget = (target)=> String(target || '').replace(/^#/, '').trim();
  const activateWithBootstrap = (btn)=>{
    const BsTab = window.bootstrap && window.bootstrap.Tab;
    if(!BsTab) return false;
    try{
      if(typeof BsTab.getOrCreateInstance === 'function'){
        BsTab.getOrCreateInstance(btn).show();
      }else{
        (new BsTab(btn)).show();
      }
      return true;
    }catch(_err){
      return false;
    }
  };

  // Refuerzo: asegurar que el click cambie de tab
  const tabLinks = Array.from(document.querySelectorAll('#p-expediente .mm-tabs-row .nav-link'));
  const tabPanes = Array.from(document.querySelectorAll('#p-expediente .tab-content .tab-pane'));
  const activateTabPaneManually = (btn, target)=>{
    if(!target) return;
    tabLinks.forEach((b)=>{
      const isCurrent = b === btn;
      b.classList.toggle('active', isCurrent);
      b.setAttribute('aria-selected', isCurrent ? 'true' : 'false');
    });
    tabPanes.forEach((p)=> p.classList.remove('show','active'));
    const paneTarget = pane.querySelector(target);
    if(paneTarget){
      paneTarget.classList.add('show','active');
    }
    pane.dataset.activeTab = getTabIdFromTarget(target);
  };
  tabLinks.forEach((btn)=>{
    btn.addEventListener('shown.bs.tab', ()=>{
      const target = btn.getAttribute('data-bs-target');
      if(!target) return;
      pane.dataset.activeTab = getTabIdFromTarget(target);
    });
  });
  tabLinks.forEach(btn=>{
    btn.addEventListener('click', ()=>{
      const target = btn.getAttribute('data-bs-target');
      if(!target) return;
      const isBottomRow = !!btn.closest('.mm-tabs-row-bottom');
      const bootstrapped = activateWithBootstrap(btn);
      if(!isBottomRow && bootstrapped) return;
      activateTabPaneManually(btn, target);
    });
  });

  historialAtencionBtn?.addEventListener('click', (ev)=>{
    ev.preventDefault();
    openHistorialAtencion();
  });

  if(!pane.__patientGateInit){
    const handlePatientGateChange = ()=>{
      const pid = String(getActivePatientId() || '').trim();
      if(!pid){
        syncState({ allowNavigate:true });
        return;
      }
      setActivePatientId(pid, { emitEvent:false, skipActiveEncounterConfirm:true });
      syncState({ allowNavigate:true });
    };
    ['patient:selected', 'expediente:patient_changed', 'expediente:patient-changed'].forEach((evtName)=>{
      window.addEventListener(evtName, handlePatientGateChange);
    });
    const onHashChange = ()=>{
      const pid = String(getHashPatientId() || '').trim();
      if(!pid) return;
      setActivePatientId(pid, { source:'hashchange', emitEvent:false, skipActiveEncounterConfirm:true });
      syncState({ allowNavigate:true });
    };
    window.addEventListener('hashchange', onHashChange);
    const patientAttrObserver = new MutationObserver(handlePatientGateChange);
    patientAttrObserver.observe(pane, { attributes:true, attributeFilter:['data-patient-id', 'data-active-patient-id'] });
    pane.__patientGateInit = true;
  }

  if(expHeaderCloseBtn){
    expHeaderCloseBtn.addEventListener('click', ()=>{
      if(!p10FinalizeBtn || p10FinalizeBtn.disabled) return;
      p10FinalizeBtn.click();
    });
  }
  if(expHeaderStartBtn){
    expHeaderStartBtn.addEventListener('click', ()=>{
      if(!p10StartBtn || p10StartBtn.disabled) return;
      p10StartBtn.click();
    });
  }
  if(expHeaderActiveStripScroll){
    expHeaderActiveStripScroll.addEventListener('click', async (ev)=>{
      const chip = ev.target.closest('[data-action="exp-switch-active-enc"]');
      if(!chip) return;
      const targetPatientId = String(chip.getAttribute('data-pid') || '').trim();
      const targetEncounterKey = String(chip.getAttribute('data-encounter-key') || '').trim();
      if(!targetPatientId || !targetEncounterKey) return;

      let changed = true;
      if(typeof window.setActivePatientId === 'function'){
        changed = await window.setActivePatientId(targetPatientId, { emitEvent:true, skipActiveEncounterConfirm:true });
      }else if(typeof window.mxmedSetActivePatientId === 'function'){
        changed = await window.mxmedSetActivePatientId(targetPatientId, { emitEvent:true, skipActiveEncounterConfirm:true });
      }
      if(changed === false) return;
      if(typeof window.setCurrentEncounterForPatient === 'function'){
        window.setCurrentEncounterForPatient(targetPatientId, targetEncounterKey, { source: 'active_strip_select' });
      }else{
        if(window.mxmedStore && typeof window.mxmedStore === 'object'){
          window.mxmedStore.currentPatientId = targetPatientId;
          window.mxmedStore.currentEncounterKey = targetEncounterKey;
        }
        if(typeof window.setEncounterContextOnPane === 'function'){
          window.setEncounterContextOnPane(targetEncounterKey, targetPatientId);
        }
      }
      if(typeof window.mxmedEmitEncounterLifecycle === 'function'){
        window.mxmedEmitEncounterLifecycle({
          patient_id: targetPatientId,
          encounter_key: targetEncounterKey,
          status: 'consulta_activa',
          origin: 'active_strip_select',
          last_activity_at: new Date().toISOString()
        });
      }
      if(typeof jumpTo === 'function'){
        jumpTo('p-expediente');
      }
    });
  }
  const headerInputs = [
    nameInput,
    apellidoPaternoInput,
    apellidoMaternoInput,
    pane.querySelector('[data-dg-dia]'),
    pane.querySelector('[data-dg-mes]'),
    pane.querySelector('[data-dg-anio]')
  ].filter(Boolean);
  const motivoConsultaInput = getMotivoTextarea();
  const persistIdentityDraft = ()=>{
    const activePid = String(getActivePatientId() || '').trim();
    if(!activePid) return;
    captureExpedienteIdentityDraft(activePid);
  };
  headerInputs.forEach((el)=>{
    el.addEventListener('input', ()=>{
      window.__mxmedHeaderSyncOrigin = 'header_input';
      syncExpedienteHeaderContext();
    });
    el.addEventListener('change', ()=>{
      window.__mxmedHeaderSyncOrigin = 'header_change';
      syncExpedienteHeaderContext();
    });
    el.addEventListener('input', persistIdentityDraft);
    el.addEventListener('change', persistIdentityDraft);
  });
  genderInputs.forEach((el)=>{
    el.addEventListener('change', ()=>{
      window.__mxmedHeaderSyncOrigin = 'gender_change';
      syncExpedienteHeaderContext();
    });
    el.addEventListener('change', persistIdentityDraft);
  });
  pane.addEventListener('pac-age-changed', ()=>{
    window.__mxmedHeaderSyncOrigin = 'pac-age-changed';
    syncExpedienteHeaderContext();
  });
  if(motivoConsultaInput){
    ['input', 'change'].forEach((evtName)=>{
      motivoConsultaInput.addEventListener(evtName, (ev)=>{
        const pid = sanitizeText(getActivePatientId());
        if(pid){
          const value = sanitizeText(motivoConsultaInput.value);
          const isManual = !motivoPrefillWriteLock && ev?.isTrusted === true;
          writeMotivoDraftEntry(pid, value, {
            source: isManual ? 'manual' : 'programmatic',
            manualTouched: isManual
          });
        }
        window.__mxmedHeaderSyncOrigin = 'motivo_consulta_change';
        syncExpedienteHeaderContext();
      });
    });
  }
  window.addEventListener('mxmed:encounter-lifecycle', (ev)=>{
    const detail = (ev && ev.detail && typeof ev.detail === 'object') ? ev.detail : {};
    const status = sanitizeText(detail.status);
    if(status !== 'consulta_cerrada' && status !== 'sin_consulta_activa') return;
    const patientId = sanitizeText(detail.patient_id || getActivePatientId());
    if(!patientId) return;
    const fullName = [
      sanitizeText(nameInput?.value),
      sanitizeText(apellidoPaternoInput?.value),
      sanitizeText(apellidoMaternoInput?.value)
    ].filter(Boolean).join(' ').trim() || 'Paciente';
    const refreshClosedState = (activeEncounterKey = '')=>{
      if(typeof window.mxmedReconcileActiveEntriesForPatient === 'function'){
        try{
          window.mxmedReconcileActiveEntriesForPatient(patientId, sanitizeText(activeEncounterKey));
        }catch(_){}
      }
      renderActiveEncounterStrip({ currentFullName: fullName });
      window.__mxmedHeaderSyncOrigin = 'event:mxmed:encounter-lifecycle:closed';
      syncExpedienteHeaderContext();
      renderActividadClinicaContext();
    };
    if(typeof window.resolveActiveEncounterForPatient === 'function'){
      Promise.resolve(
        window.resolveActiveEncounterForPatient(patientId, {
          force: true,
          source: 'encounter_lifecycle_closed_refresh'
        })
      ).then((resolved)=>{
        const activeKey = (resolved && resolved.ok === true && resolved.hasActive)
          ? sanitizeText(resolved.encounterKey)
          : '';
        refreshClosedState(activeKey);
      }).catch(()=>{
        refreshClosedState('');
      });
      return;
    }
    refreshClosedState('');
  });
  ['encounter:active', 'mxmed:encounter-changed', 'mxmed:encounter-lifecycle', 'mxmed:encounter-activity', 'patient:selected', 'expediente:patient_changed', 'expediente:patient-changed']
    .forEach((evtName)=> window.addEventListener(evtName, ()=>{
      window.__mxmedHeaderSyncOrigin = `event:${evtName}`;
      syncExpedienteHeaderContext();
      renderActividadClinicaContext();
    }));
  window.addEventListener('mxmed:expediente-neutralize', ()=>{
    resetExpedienteIdentityFields();
    clearSessionPatientId();
    clearHashPatientId();
    delete pane.dataset.patientId;
    delete pane.dataset.activePatientId;
    pane.removeAttribute('data-patient-id');
    pane.removeAttribute('data-active-patient-id');
    if(window.mxmedStore && typeof window.mxmedStore === 'object'){
      window.mxmedStore.activePatientId = '';
      window.mxmedStore.currentPatientId = '';
    }
    window.mxmedActivePatientId = '';
    window.__MXMED_ACTIVE_PATIENT_ID = '';
    applyPatientGate();
    window.__mxmedHeaderSyncOrigin = 'event:mxmed:expediente-neutralize';
    syncExpedienteHeaderContext();
    renderActividadClinicaContext();
  });
  const newPatientMenuBtn = document.querySelector('.menu-sub[data-group="pacientes"] .menu-sub-btn[data-panel="p-expediente"]');
  if(newPatientMenuBtn){
    newPatientMenuBtn.addEventListener('click', ()=>{
      pane.dataset.newEntryMode = '1';
      pane.setAttribute('data-new-entry-mode', '1');
      try{
        window.dispatchEvent(new CustomEvent('mxmed:expediente-neutralize', {
          detail: { reason: 'new_patient_entry' }
        }));
      }catch(_){}
      const targetDatosBtn = pane.querySelector('[data-tab-key="t-datos"]');
      if(targetDatosBtn){
        window.setTimeout(()=>{ activateWithBootstrap(targetDatosBtn); }, 0);
      }
    });
  }
  const headerEncounterObserver = new MutationObserver(syncExpedienteHeaderContext);
  headerEncounterObserver.observe(pane, {
    attributes:true,
    attributeFilter:['data-encounter-key', 'data-active-encounter-key', 'data-patient-id', 'data-active-patient-id']
  });

  const selectedGenero = genderInputs.find(inp => inp.checked)?.value || '';
  syncGineco(selectedGenero, false);
  syncState();
  layoutTabs(false);
  bindDOB();
  syncExpedienteHeaderContext();
  renderActividadClinicaContext();
  applyExpedienteEntryTabRule({ context: 'boot' });
  const bootPatientId = sanitizeText(getActivePatientId());
  if(bootPatientId){
    maybeApplyContextualMotivoPrefill(bootPatientId, { encounter_key: String(window.mxmedStore?.currentEncounterKey || '').trim() }).catch(()=> null);
  }
})();

// ====== Historia Clinica: registros con chips + modal ======
(function(){
  const pane = document.getElementById('p-expediente');
  if(!pane) return;
  const modalEl = document.getElementById('modalHistItem');
  if(!modalEl) return;

  const titleEl = modalEl.querySelector('[data-hc-title]');
  const yearSel = modalEl.querySelector('[data-hc-year]');
  const detailsInput = modalEl.querySelector('[data-hc-details]');
  const detailsLabel = modalEl.querySelector('[data-hc-details-label]');
  const saveBtn = modalEl.querySelector('[data-hc-save]');
  const deleteBtn = modalEl.querySelector('[data-hc-delete]');

  if(!yearSel || !detailsInput || !saveBtn) return;

  const getLabel = (itemEl)=>{
    const labelEl = itemEl?.querySelector('.hc-chip-head span');
    return (labelEl?.textContent || 'Registro').trim();
  };

  const buildYearOptions = ()=>{
    const now = new Date().getFullYear();
    const opts = [];
    for(let i=0; i<=75; i+=1){
      const y = now - i;
      let label = '';
      if(i === 0){
        label = `${y} (este a\u00f1o)`;
      }else if(i === 1){
        label = `${y} (hace 1 a\u00f1o)`;
      }else if(i === 75){
        label = `${y} o antes (hace 75 a\u00f1os o m\u00e1s)`;
      }else{
        label = `${y} (hace ${i} a\u00f1os)`;
      }
      opts.push({ value: String(y), label });
    }
    return opts;
  };

  const fillYears = ()=>{
    const opts = buildYearOptions();
    yearSel.innerHTML = '<option value=\"\">Selecciona a\u00f1o</option>' + opts.map(o=>`<option value=\"${o.value}\">${o.label}</option>`).join('');
  };

  const makeChipLabel = (year, details)=>{
    const clean = (details || '').trim();
    if(!clean) return year;
    let short = clean;
    if(short.length > 32){
      short = short.slice(0, 32).trim() + '...';
    }
    return `${year} · ${short}`;
  };

  const modal = (window.bootstrap && bootstrap.Modal && bootstrap.Modal.getOrCreateInstance)
    ? bootstrap.Modal.getOrCreateInstance(modalEl)
    : new bootstrap.Modal(modalEl);

  let activeItem = null;
  let activeChip = null;

  const openModal = (itemEl, chipEl)=>{
    activeItem = itemEl;
    activeChip = chipEl || null;
    const label = getLabel(itemEl);
    if(titleEl) titleEl.textContent = (chipEl ? 'Editar ' : 'Agregar ') + label;
    fillYears();

    if(chipEl){
      yearSel.value = chipEl.dataset.year || '';
      detailsInput.value = chipEl.dataset.details || '';
      deleteBtn?.classList.remove('d-none');
    }else{
      yearSel.value = '';
      detailsInput.value = '';
      deleteBtn?.classList.add('d-none');
    }

    const key = itemEl?.getAttribute('data-hc-item') || '';
    const isTransfusion = key === 'transfusiones';
    if(detailsLabel) detailsLabel.textContent = isTransfusion ? 'Motivo' : 'Detalles';
    detailsInput.placeholder = isTransfusion ? 'Motivo' : 'Detalles';

    modal.show();
  };

  pane.querySelectorAll('[data-hc-add]').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      const itemEl = btn.closest('[data-hc-item]');
      if(!itemEl) return;
      const list = itemEl.querySelector('[data-hc-chips]');
      const max = Number(itemEl.getAttribute('data-hc-max') || 10);
      if(list && list.querySelectorAll('.hc-chip').length >= max){
        window.alert('Maximo 10 registros.');
        return;
      }
      openModal(itemEl, null);
    });
  });

  pane.addEventListener('click', (ev)=>{
    const chip = ev.target.closest('.hc-chip');
    if(!chip) return;
    const itemEl = chip.closest('[data-hc-item]');
    if(!itemEl) return;
    openModal(itemEl, chip);
  });

  saveBtn.addEventListener('click', ()=>{
    if(!activeItem) return;
    const year = yearSel.value;
    const details = detailsInput.value.trim();
    if(!year){
      yearSel.focus();
      return;
    }
    const list = activeItem.querySelector('[data-hc-chips]');
    if(!list) return;
    const max = Number(activeItem.getAttribute('data-hc-max') || 10);

    if(activeChip){
      activeChip.dataset.year = year;
      activeChip.dataset.details = details;
      activeChip.textContent = makeChipLabel(year, details);
    }else{
      if(list.querySelectorAll('.hc-chip').length >= max){
        window.alert('Maximo 10 registros.');
        return;
      }
      const chip = document.createElement('button');
      chip.type = 'button';
      chip.className = 'hc-chip';
      chip.dataset.year = year;
      chip.dataset.details = details;
      chip.textContent = makeChipLabel(year, details);
      list.appendChild(chip);
    }
    modal.hide();
  });

  deleteBtn?.addEventListener('click', ()=>{
    if(!activeChip) return;
    const ok = window.confirm('Desea eliminar esta informacion?');
    if(!ok) return;
    activeChip.remove();
    modal.hide();
  });

  modalEl.addEventListener('hidden.bs.modal', ()=>{
    activeItem = null;
    activeChip = null;
  });
})();

// ====== Historia Clinica: vacunas relevantes ======
(function(){
  const pane = document.getElementById('p-expediente');
  if(!pane) return;
  const toggles = Array.from(pane.querySelectorAll('[data-vac-toggle]'));
  if(!toggles.length) return;
  toggles.forEach(chk=>{
    const item = chk.closest('.hc-vac-item');
    const note = item?.querySelector('[data-vac-note]');
    const sync = ()=>{
      if(!note) return;
      if(chk.checked){
        note.removeAttribute('disabled');
      }else{
        note.value = '';
        note.setAttribute('disabled','disabled');
      }
    };
    chk.addEventListener('change', sync);
    sync();
  });
})();
// ====== Seguridad: checklist compacto de contraseña ======
(function(){
  const panel = document.getElementById('pwd-change-panel');
  if(!panel) return;
  const summary = document.getElementById('pwd-summary');
  const newInput = panel.querySelector('[data-pwd-new]');
  const confirmInput = panel.querySelector('[data-pwd-confirm]');
  const submitBtn = panel.querySelector('[data-pwd-submit]');
  const matchHint = panel.querySelector('[data-pwd-match-hint]');
  const dismissBtns = panel.querySelectorAll('[data-verify-dismiss]');
  if(summary){
    panel.addEventListener('show.bs.collapse', ()=> summary.classList.add('d-none'));
    panel.addEventListener('hidden.bs.collapse', ()=>{
      summary.classList.remove('d-none');
      resetForm();
    });
  }
  if(!newInput) return;
  const iconFor = (chip, met)=>{
    const ico = chip.querySelector('.material-symbols-rounded');
    if(ico) ico.textContent = met ? 'check_circle' : 'cancel';
  };
  const tests = {
    length: (val)=> val.length >= 8,
    upper: (val)=> /[A-ZÁÉÍÓÚÜÑ]/.test(val),
    number: (val)=> /\d/.test(val),
    symbol: (val)=> /[^A-Za-z0-9]/.test(val),
  };
  const runChecks = (val)=>{
    let allMet = true;
    Object.entries(tests).forEach(([key, fn])=>{
      const chip = panel.querySelector(`.pwd-chip[data-check="${key}"]`);
      if(!chip) return;
      const met = fn(val);
      chip.classList.toggle('met', met);
      iconFor(chip, met);
      if(!met) allMet = false;
    });
    return allMet;
  };
  const resetForm = ()=>{
    newInput.value = '';
    if(confirmInput) confirmInput.value = '';
    runChecks('');
    if(matchHint) matchHint.classList.add('d-none');
    if(submitBtn) submitBtn.disabled = true;
  };
  const syncState = ()=>{
    const pwd = newInput.value || '';
    const confirm = confirmInput?.value || '';
    const checksOk = runChecks(pwd);
    const match = confirmInput ? (pwd.length > 0 && pwd === confirm) : true;
    if(matchHint){
      matchHint.classList.toggle('d-none', match || confirm.length===0);
    }
    if(submitBtn){
      submitBtn.disabled = !(checksOk && match);
    }
  };
  const hidePanel = ()=>{
    const collapse = window.bootstrap && window.bootstrap.Collapse ? window.bootstrap.Collapse.getOrCreateInstance(panel) : null;
    if(collapse){
      collapse.hide();
    }else{
      panel.classList.remove('show');
      if(summary) summary.classList.remove('d-none');
      resetForm();
    }
  };
  dismissBtns.forEach(btn=>{
    btn.addEventListener('click', (ev)=>{
      ev.preventDefault();
      hidePanel();
    });
  });
  newInput.addEventListener('input', syncState);
  confirmInput?.addEventListener('input', syncState);
  panel.addEventListener('shown.bs.collapse', syncState);
  syncState();
})();


// Refuerzo: cierres manuales sin Bootstrap (data-sec-close y collapse)
;(function(){
  const closers = document.querySelectorAll('[data-sec-close]');
  closers.forEach(btn=>{
    btn.addEventListener('click',(ev)=>{
      ev.preventDefault();
      const sel = btn.getAttribute('data-sec-close');
      const panel = sel ? document.querySelector(sel) : btn.closest('.collapse');
      if(!panel) return;
      const inst = window.bootstrap?.Collapse?.getOrCreateInstance(panel);
      if(inst){ inst.hide(); return; }
      panel.classList.remove('show');
      panel.style.display = 'none';
    });
  });
  if(!window.bootstrap?.Collapse){
    document.querySelectorAll('[data-bs-toggle="collapse"][data-bs-target]').forEach(btn=>{
      const sel = btn.getAttribute('data-bs-target');
      const panel = sel ? document.querySelector(sel) : null;
      if(!panel) return;
      btn.addEventListener('click',(ev)=>{
        ev.preventDefault();
        const open = panel.classList.contains('show');
        panel.classList.toggle('show', !open);
        panel.style.display = open ? 'none' : 'block';
      });
    });
  }
})();

// ====== Seguridad: validación Teléfono/E-mail ======
(function(){
  const panels = document.querySelectorAll('[data-verify-panel]');
  if(!panels.length) return;

  const getCollapse = (panel)=>{
    if(window.bootstrap && window.bootstrap.Collapse){
      return window.bootstrap.Collapse.getOrCreateInstance(panel);
    }
    return null;
  };

  const formatTime = (secs)=>{
    const m = String(Math.floor(secs/60)).padStart(2,'0');
    const s = String(secs%60).padStart(2,'0');
    return `${m}:${s}`;
  };

  panels.forEach(panel=>{
    const summarySelector = panel.getAttribute('data-summary');
    const summary = summarySelector ? document.querySelector(summarySelector) : null;
    const otpInputs = Array.from(panel.querySelectorAll('[data-otp-input]'));
    const submitBtn = panel.querySelector('[data-otp-submit]');
    const resendBtn = panel.querySelector('[data-otp-resend]');
    const countdown = panel.querySelector('[data-otp-countdown]');
    const dismissBtns = panel.querySelectorAll('[data-verify-dismiss]');
    const stepsRoot = panel.querySelector('[data-verify-steps]');
    const stepBlocks = stepsRoot ? stepsRoot.querySelectorAll('[data-step]') : [];
    let timer=null;

    const applyFilled = ()=>{
      otpInputs.forEach(inp=>{
        const has = !!(inp.value||'').trim();
        inp.classList.toggle('filled', has);
      });
    };

    const syncValueFromSummary = ()=>{
      if(!summary) return;
      const src = summary.querySelector('input[type="tel"], input[type="email"]');
      const target = panel.querySelector('[data-verify-value]');
      if(src && target){
        target.value = src.value || '';
        target.placeholder = src.getAttribute('placeholder') || target.placeholder;
      }
    };

    const clearTimer = ()=>{ if(timer){ clearInterval(timer); timer=null; } };
    const resetPanel = ()=>{
      otpInputs.forEach(inp=> { inp.value=''; inp.classList.remove('filled','is-valid'); });
      if(submitBtn) submitBtn.disabled = true;
      if(resendBtn){
        resendBtn.disabled = false;
        resendBtn.classList.remove('disabled');
      }
      clearTimer();
      if(countdown) countdown.textContent = '01:00';
      if(stepBlocks.length) setStep('method');
    };
    const setStep = (name)=>{
      if(!stepBlocks.length) return;
      stepBlocks.forEach(block=>{
        block.classList.toggle('d-none', block.getAttribute('data-step') !== name);
      });
      if(name === 'otp'){
        otpInputs[0]?.focus();
        startTimer();
        updateOtpState();
      }else{
        clearTimer();
        if(countdown) countdown.textContent = '01:00';
      }
    };
    const onShow = ()=> summary?.classList.add('d-none');
    const onHidden = ()=>{
      summary?.classList.remove('d-none');
      resetPanel();
    };
    const onShown = ()=>{
      otpInputs[0]?.focus();
      startTimer();
      updateOtpState();
    };
    const hidePanel = ()=>{
      const collapse = getCollapse(panel);
      if(collapse){
        collapse.hide();
      }else{
        onHidden();
        panel.classList.remove('show');
      }
    };
    const showPanel = ()=>{
      const collapse = getCollapse(panel);
      if(collapse){
        syncValueFromSummary();
        collapse.show();
      }else{
        onShow();
        syncValueFromSummary();
        panel.classList.add('show');
        onShown();
      }
    };
    const startTimer = ()=>{
      if(!countdown) return;
      clearTimer();
      let remaining = 60;
      countdown.textContent = formatTime(remaining);
      timer = setInterval(()=>{
        remaining -=1;
        countdown.textContent = formatTime(Math.max(remaining,0));
        if(remaining <= 0){
          clearTimer();
        }
      },1000);
    };
    const updateOtpState = ()=>{
      const code = otpInputs.map(inp=> (inp.value||'').trim()).join('');
      applyFilled();
      if(submitBtn) submitBtn.disabled = code.length !== otpInputs.length;
    };

    if(summary){
      panel.addEventListener('show.bs.collapse', onShow);
      panel.addEventListener('hidden.bs.collapse', onHidden);
      panel.addEventListener('show.bs.collapse', syncValueFromSummary);
    }

    const focusAt = (pos)=>{
      if(pos < 0 || pos >= otpInputs.length) return;
      otpInputs[pos].focus();
      const len = otpInputs[pos].value.length;
      otpInputs[pos].setSelectionRange(len, len);
    };
    const writeDigits = (startIdx, digits, clearTail=false)=>{
      if(clearTail){
        for(let i=startIdx;i<otpInputs.length;i++){
          otpInputs[i].value = '';
        }
      }
      let pos = startIdx;
      for(const ch of digits){
        if(pos >= otpInputs.length) break;
        otpInputs[pos].value = ch;
        pos++;
      }
      updateOtpState();
      focusAt(Math.min(pos, otpInputs.length-1));
    };

    otpInputs.forEach((inp, idx)=>{
      inp.addEventListener('input', ()=>{
        const digits = (inp.value||'').replace(/[^0-9]/g,'');
        if(!digits){
          inp.value = '';
          inp.classList.remove('filled');
          updateOtpState();
          return;
        }
        if(digits.length > 1){
          writeDigits(idx, digits, true);
          return;
        }
        inp.value = digits;
        updateOtpState();
        if(idx < otpInputs.length-1){
          focusAt(idx+1);
        }
      });

      inp.addEventListener('keydown', (ev)=>{
        if(ev.key === 'Backspace'){
          if(inp.value){
            inp.value = '';
            inp.classList.remove('filled');
            updateOtpState();
          }else if(idx > 0){
            ev.preventDefault();
            otpInputs[idx-1].value = '';
            otpInputs[idx-1].classList.remove('filled');
            updateOtpState();
            focusAt(idx-1);
          }
        }else if(ev.key === 'ArrowLeft' && idx > 0){
          ev.preventDefault();
          focusAt(idx-1);
        }else if(ev.key === 'ArrowRight' && idx < otpInputs.length-1){
          ev.preventDefault();
          focusAt(idx+1);
        }
      });

      inp.addEventListener('paste', (ev)=>{
        const clip = ev.clipboardData?.getData('text') || '';
        const digits = (clip||'').replace(/\D/g,'');
        if(!digits) return;
        ev.preventDefault();
        writeDigits(idx, digits, true);
      });
    });

    resendBtn?.addEventListener('click', (ev)=>{
      ev.preventDefault();
      resendBtn.disabled = true;
      resendBtn.classList.add('disabled');
      startTimer();
      setTimeout(()=>{
        resendBtn.disabled = false;
        resendBtn.classList.remove('disabled');
      }, 60000);
    });

    dismissBtns.forEach(btn=>{
      btn.addEventListener('click', (ev)=>{
        ev.preventDefault();
        hidePanel();
      });
    });

    panel.addEventListener('shown.bs.collapse', onShown);
    panel.__verifyShow = showPanel;

    // Step navigation
    panel.querySelectorAll('[data-verify-next]').forEach(btn=>{
      btn.addEventListener('click', (ev)=>{
        ev.preventDefault();
        const target = btn.getAttribute('data-verify-next');
        if(target === 'done' && submitBtn && submitBtn.disabled) return;
        setStep(target || 'method');
      });
    });
    panel.querySelectorAll('[data-verify-set]').forEach(btn=>{
      btn.addEventListener('click', (ev)=>{
        ev.preventDefault();
        const target = btn.getAttribute('data-verify-set') || 'method';
        setStep(target);
      });
    });

    // Initialize default step
    setStep('method');
  });

  const triggers = document.querySelectorAll('[data-verify-trigger]');
  const validateTriggerValue = (btn)=>{
    const inputSel = btn.getAttribute('data-verify-input');
    const type = btn.getAttribute('data-verify-type');
    const hintSel = btn.getAttribute('data-verify-hint');
    const inputEl = inputSel ? document.querySelector(inputSel) : null;
    const hintEl = hintSel ? document.querySelector(hintSel) : null;
    let ok = true;
    const value = (inputEl?.value || '').trim();
    if(type === 'phone'){
      const digits = value.replace(/\D/g,'');
      ok = digits.length === 10;
    }
    if(hintEl){
      hintEl.classList.toggle('d-none', ok);
    }
    if(!ok){
      inputEl?.focus();
    }
    return ok;
  };

  triggers.forEach(btn=>{
    btn.addEventListener('click', (ev)=>{
      ev.preventDefault();
      if(!validateTriggerValue(btn)) return;
      const targetSel = btn.getAttribute('data-verify-trigger');
      const panel = targetSel ? document.querySelector(targetSel) : null;
      if(!panel) return;
      if(typeof panel.__verifyShow === 'function'){
        panel.__verifyShow();
      }
    });
  });
})();

// ===== Sugerencia de Grupo Medico y sincronizacion de logotipo (demo) =====

(function setupGrupoMedicoSuggest(){

  const keyAssoc = 'grupo_MÃƒÂ©dico_assoc';



  function getAddr(){

    return {

      cp: (document.getElementById('cp')?.value||'').trim(),

      col: (document.getElementById('colonia')?.value||'').trim(),

      mun: (document.getElementById('municipio')?.value||'').trim(),

      edo: (document.getElementById('estado')?.value||'').trim(),

      calle: (document.getElementById('cons-calle')?.value||'').trim(),

      numext: (document.getElementById('cons-numext')?.value||'').trim()

    };

  }



  function suggestGroup(addr){

    const hasCore = addr.cp && addr.col && addr.mun && addr.edo;

    if(!hasCore) return null;

    const logo = 'data:image/svg+xml;utf8,'+

      '<svg xmlns="http://www.w3.org/2000/svg" width="240" height="240">'+

      '<rect width="100%" height="100%" fill="%2300ADC1"/>'+

      '<text x="50%" y="55%" font-size="96" text-anchor="middle" fill="white" font-family="Arial">GM</text>'+

      '</svg>';

    return {

      id: 'demo-123',

      nombre: 'Grupo M\u00E9dico Central',

      addr: [addr.col, addr.mun, addr.edo].filter(Boolean).join(', '),

      logo_url: logo

    };

  }



  function showModal(s){

    const el = document.getElementById('modalGrupoSuggest'); if(!el) return;

    el.querySelector('#grp-name').textContent = s.nombre || 'Grupo Médico';

    el.querySelector('#grp-addr').textContent = s.addr || '';

    const m = (window.bootstrap && bootstrap.Modal && bootstrap.Modal.getOrCreateInstance) ? bootstrap.Modal.getOrCreateInstance(el) : new bootstrap.Modal(el);

    // Evitar reentradas mientras el modal est visible

    window._mx_suggestBusy = true;

    const onHidden = ()=>{

      el.removeEventListener('hidden.bs.modal', onHidden);

      window._mx_suggestBusy = false;

      try{

        document.body.classList.remove('modal-open');

        document.body.style.overflow = '';

        document.body.style.removeProperty('padding-right');

        document.querySelectorAll('.modal-backdrop').forEach(b=>b.parentNode && b.parentNode.removeChild(b));

      }catch(_){ }

    };

    el.addEventListener('hidden.bs.modal', onHidden);

    const btnSi = document.getElementById('modalGrupoSi');

    const btnNo = document.getElementById('modalGrupoNo');

    if(btnSi) btnSi.onclick = ()=>{ accept(s, m); setTimeout(onHidden, 120); };

    if(btnNo) btnNo.onclick = ()=>{ decline(s, m); setTimeout(onHidden, 120); };

    m.show();

  }



  function accept(s, modal){

    applyGroupSelection(s);

    modal?.hide();

  }



  function decline(_s, modal){

    try{ localStorage.setItem(keyAssoc+':decline', JSON.stringify({ when: Date.now(), addr: getAddr() })); }catch(_){ }

    modal?.hide();

  }



  function applyAssocUI(s){

    const img = document.getElementById('cons-logo-img');

    const prev = document.getElementById('cons-logo-prev');

    const slot = document.getElementById('cons-logo-slot');

    const uploadLogo = document.querySelector('.mf-upload[data-type="logo"]');

    if(img && s.logo_url){

      img.src = s.logo_url;

    }

    if(prev){

      prev.removeAttribute('hidden');

      prev.style.display = 'flex';

    }

    if(slot){

      slot.classList.add('show-preview');

      slot.classList.add('has-logo');

      mxSetLogoSource('assoc');

      const drop = slot.querySelector('.logo-slot-drop');

      if(drop){ drop.setAttribute('hidden','hidden'); }

    }

    if(uploadLogo){

      uploadLogo.classList.add('has-logo');

      const ghost = uploadLogo.querySelector('.mf-ghost');

      if(ghost){

        ghost.style.display = 'none';

        ghost.setAttribute('aria-hidden','true');

      }

    }

    mxToggleLogoSyncMsg(true);

    mxToggleLogoManualMsg(false);

    const file = document.getElementById('cons-logo'); if(file) file.setAttribute('disabled','disabled');

    // Bloquear campos clave de direcci?n cuando hay asociaci?n

    ;['cp','colonia','municipio','estado'].forEach(id=>{

      const el = document.getElementById(id); if(!el) return;

      try{ el.setAttribute('disabled','disabled'); el.disabled = true; }catch(_){ }

    });

    // Control de borrado (X en esquina)

    const del = document.getElementById('cons-logo-del');

    if(del){ del.onclick = (ev)=>{

      ev.stopPropagation();

      let current = s;

      try{

        const stored = JSON.parse(localStorage.getItem(keyAssoc)||'null');

        if(stored) current = stored;

      }catch(_){ }

      openUnlinkModal(current, ()=>{

        try{ localStorage.removeItem(keyAssoc); localStorage.removeItem(keyAssoc+':decline'); }catch(_){ }

        removeAssocUI();

        const rNo = document.getElementById('cons-grupo-no');

        const rSi = document.getElementById('cons-grupo-si');

        const grp = document.getElementById('cons-grupo-nombre');

        if(rNo){ rNo.checked = true; rNo.dispatchEvent(new Event('change')); }

        if(rSi){ rSi.checked = false; }

        if(grp){ grp.value=''; grp.setAttribute('disabled','disabled'); }

        ['cp','colonia','municipio','estado','cons-calle','cons-numext'].forEach(id=>{

          const el = document.getElementById(id); if(!el) return;

          el.removeAttribute('disabled');

          if('disabled' in el) try{ el.disabled = false; }catch(_){ }

          if(id==='colonia'){ el.classList.remove('select-open'); el.removeAttribute('size'); }

        });

      }, ()=>{});

    }; }

  }



  function removeAssocUI(){

    mxResetLogoPreview();

    mxToggleLogoSyncMsg(false);

    mxToggleLogoManualMsg(false);

    const grp = document.getElementById('cons-grupo-nombre'); if(grp){ grp.classList.remove('grp-selected'); }

  }



  // Modal para desvincular grupo (con botones centrados y "S? desvincular")

  function openUnlinkModal(saved, onConfirm, onCancel){

    const el = document.getElementById('modalGrupoUnlinkLogo');

    if(!el){ const ok = confirm('?Est? seguro que desea desvincular su consultorio?'); if(ok) onConfirm?.(); else onCancel?.(); return; }

    const nameEl = el.querySelector('#grp-unlink-logo-name');

    if(nameEl) nameEl.textContent = saved?.nombre || 'este grupo';

    const yesBtn = document.getElementById('modalGrupoUnlinkLogoYes');

    const m = (window.bootstrap && bootstrap.Modal && bootstrap.Modal.getOrCreateInstance) ? bootstrap.Modal.getOrCreateInstance(el) : new bootstrap.Modal(el);

    const cleanup = ()=>{ try{ yesBtn.onclick = null; }catch(_){ } };

    el.addEventListener('hidden.bs.modal', function onHidden(){ el.removeEventListener('hidden.bs.modal', onHidden); cleanup(); onCancel?.(); }, { once:true });

    yesBtn.onclick = ()=>{ cleanup(); onConfirm?.(); m.hide(); };

    m.show();

  }



  let debounceT = null;

  let suppressGroupChange = false;

  let inlineLayer = null;

  let inlineItems = [];

  let inlineIndex = -1;

  let inlineKeyHandler = null;



  function highlightInline(idx){

    inlineIndex = idx;

    inlineItems.forEach((item, i)=>{

      if(item.node){

        item.node.classList.toggle('active', i === inlineIndex);

        if(i === inlineIndex){

          try{ item.node.scrollIntoView({block:'nearest'}); }catch(_){}

        }

      }

    });

  }



  function hideInline(){

    if(inlineLayer){

      inlineLayer.remove();

      inlineLayer = null;

    }

    inlineItems = [];

    inlineIndex = -1;

    if(inlineKeyHandler){

      document.removeEventListener('keydown', inlineKeyHandler, true);

      inlineKeyHandler = null;

    }

  }



  function showInline(arr, anchor){

    hideInline();

    if(!arr || !arr.length || !anchor) return;

    const rect = anchor.getBoundingClientRect();

    const box = document.createElement('div');

    box.className = 'grp-suggest';

    box.style.left = (window.scrollX + rect.left) + 'px';

    box.style.top  = (window.scrollY + rect.bottom + 4) + 'px';

    box.style.width= rect.width + 'px';

    inlineItems = [];

    arr.forEach(g=>{

      const it = document.createElement('div'); it.className='item';

      const nm = document.createElement('div'); nm.className='name'; nm.textContent = g.nombre;

      const ad = document.createElement('div'); ad.className='addr'; ad.textContent = g.addr||'';

      it.appendChild(nm); it.appendChild(ad);

      it.addEventListener('click', ()=>{

        hideInline();

        applyGroupSelection(g);

      });

      box.appendChild(it);

      inlineItems.push({ data:g, node:it });

    });

    document.body.appendChild(box);

    inlineLayer = box;

    highlightInline(0);

    const handler = (ev)=>{ if(!box.contains(ev.target) && ev.target!==anchor){ hideInline(); document.removeEventListener('mousedown', handler, true); } };

    document.addEventListener('mousedown', handler, true);

    inlineKeyHandler = (ev)=>{

      if(!inlineLayer || !inlineItems.length) return;

      if(ev.key === 'ArrowDown'){

        ev.preventDefault();

        const next = (inlineIndex + 1) % inlineItems.length;

        highlightInline(next);

      }else if(ev.key === 'ArrowUp'){

        ev.preventDefault();

        const next = (inlineIndex - 1 + inlineItems.length) % inlineItems.length;

        highlightInline(next);

      }else if(ev.key === 'Enter'){

        ev.preventDefault();

        const item = inlineItems[inlineIndex];

        if(item){

          hideInline();

          applyGroupSelection(item.data);

        }

      }else if(ev.key === 'Escape'){

        ev.preventDefault();

        hideInline();

      }

    };

    document.addEventListener('keydown', inlineKeyHandler, true);

  }



  function buildDemoLogo(text, color){

    const svg =

      `<svg xmlns="http://www.w3.org/2000/svg" width="240" height="240">`+

      `<rect width="100%" height="100%" rx="28" fill="${color}"/>`+

      `<text x="50%" y="55%" font-size="72" text-anchor="middle" fill="white" font-family="Arial,Helvetica,sans-serif">${text}</text>`+

      `</svg>`;
    return 'data:image/svg+xml;utf8,' + encodeURIComponent(svg);
  }
  const DEMO_GROUPS = [
    {
      id:'grp-star',
      nombre:'Star M\u00e9dica',
      calle:'Av. Aguascalientes',
      numext:'1420',
      addr:'Aguascalientes Centro',
      logo_url: 'assets/img/star medica.svg'
    },
    {
      id:'grp-san-juan',
      nombre:'M\u00e9dica San Juan',
      calle:'Adolfo L\u00f3pez Mateos',
      numext:'892',
      addr:'Zona Centro',
      logo_url: 'assets/img/medica san juan.png'
    }
  ];


  function listMatches(addr){

    if(addr && addr.col){

      // Priorizar grupo dependiendo de la colonia (demostraci?n simple)

      if(/centro/i.test(addr.col)){

        return DEMO_GROUPS;

      }

    }

    return DEMO_GROUPS;

  }



  function fillConsultorioTitle(name){

    const tit = document.getElementById('cons-titulo');

    if(!tit) return;

    tit.value = 'Consultorio ' + (name || '');

    tit.dataset.autofill = '1';

    tit.dispatchEvent(new Event('input'));

    tit.dispatchEvent(new Event('change'));

    requestAnimationFrame(()=>{

      try{

        tit.focus();

        if(typeof tit.setSelectionRange === 'function'){

          const L = tit.value.length; tit.setSelectionRange(L, L);

        }

      }catch(_){ }

      setTimeout(()=>{ try{ if(document.activeElement === tit) tit.blur(); }catch(_){ } }, 1200);

    });

  }



  function applyGroupSelection(group){

    if(!group) return;

    hideInline();

    const rSi = document.getElementById('cons-grupo-si');

    if(rSi){

      suppressGroupChange = true;

      rSi.checked = true;

      rSi.dispatchEvent(new Event('change'));

      suppressGroupChange = false;

    }

    const grp = document.getElementById('cons-grupo-nombre');

    if(grp){

      grp.removeAttribute('disabled'); try{ grp.disabled = false; }catch(_){}

      grp.value = group.nombre || '';

      grp.classList.add('grp-selected');

      grp.dispatchEvent(new Event('input'));

    }

    const calle = document.getElementById('cons-calle');

    if(calle){

      calle.value = group.calle || '';

      calle.dispatchEvent(new Event('input'));

      calle.dispatchEvent(new Event('change'));

    }

    const numext = document.getElementById('cons-numext');

    if(numext){

      numext.value = group.numext || '';

      numext.dispatchEvent(new Event('input'));

      numext.dispatchEvent(new Event('change'));

    }

    fillConsultorioTitle(group.nombre);

    try{ localStorage.setItem(keyAssoc, JSON.stringify(group)); }catch(_){ }

    applyAssocUI(group);

  }

  function onInputsChange(){

    clearTimeout(debounceT);

    debounceT = setTimeout(()=>{

      if(window._mx_suggestBusy) return;

      const a = getAddr();

      const matches = listMatches(a);

      try{ window._mx_lastGroupMatches = matches; }catch(_){ }

      const rSi = document.getElementById('cons-grupo-si');

      const grp = document.getElementById('cons-grupo-nombre');

      if(rSi && rSi.checked && grp === document.activeElement){ if(matches && matches.length){ showInline(matches, grp); } }

    }, 400);

  }



  function init(){

    try{

      const saved = JSON.parse(localStorage.getItem(keyAssoc)||'null');

      if(saved) applyAssocUI(saved);

    }catch(_){ }

    ;['cp','colonia','municipio','estado','cons-calle','cons-numext'].forEach(id=>{

      const el = document.getElementById(id);

      if(el){ el.addEventListener('change', onInputsChange); el.addEventListener('blur', onInputsChange); }

    });

    const rSi = document.getElementById('cons-grupo-si');

    const rNo = document.getElementById('cons-grupo-no');

    const grp = document.getElementById('cons-grupo-nombre');

    if(grp){

      grp.addEventListener('focus', ()=>{ if(!(rSi && rSi.checked)) return; const a=getAddr(); const m=listMatches(a); if(m && m.length){ showInline(m, grp); } });

      grp.addEventListener('input', ()=> hideInline());

      grp.addEventListener('blur', ()=> setTimeout(hideInline, 150));

    }

    if(rSi){ rSi.addEventListener('change', ()=>{ if(suppressGroupChange) return; if(rSi.checked && grp){ const a=getAddr(); const m=listMatches(a); if(m && m.length){ showInline(m, grp); grp.focus(); } }}); }

    // Si el usuario teclea en el t?tulo, deja de ser autogenerado

    const tit = document.getElementById('cons-titulo');

    if(tit){ tit.addEventListener('input', (e)=>{ if(e.isTrusted){ try{ delete tit.dataset.autofill; }catch(_){ tit.removeAttribute('data-autofill'); } } }); }

    if(rNo){ rNo.addEventListener('change', ()=>{

      hideInline();

      if(!rNo.checked) return;

      // Si hay asociaci?n vigente, confirmar desvincular

      let saved=null; try{ saved = JSON.parse(localStorage.getItem(keyAssoc)||'null'); }catch(_){ saved=null; }

      if(saved){

        openUnlinkModal(saved, ()=>{

          try{ localStorage.removeItem(keyAssoc); localStorage.removeItem(keyAssoc+':decline'); }catch(_){ }

          removeAssocUI();

          if(grp){ grp.value=''; grp.setAttribute('disabled','disabled'); }

          ['cp','colonia','municipio','estado','cons-calle','cons-numext'].forEach(id=>{

            const el=document.getElementById(id); if(!el) return; el.removeAttribute('disabled'); try{ el.disabled=false; }catch(_){ }

            if(id==='colonia'){ el.classList.remove('select-open'); el.removeAttribute('size'); }

          });

        }, ()=>{ rSi.checked=true; rSi.dispatchEvent(new Event('change')); });

        return;

      }

    }); }

  }



  if(document.readyState === 'loading'){

    document.addEventListener('DOMContentLoaded', init);

  }else{ init(); }

})();



// ===== Ajustes puntuales de textos (s?lo secciones espec?ficas) =====

(function fixMxmedTextos(){

  try{

    // 1) Header: "Óptimo"

    const t = document.querySelector('.optimo');

    if(t) t.textContent = '\u00D3ptimo';



    // 2) Horarios: normalizar separadores y d?as con acentos

    ['#sched-body','#sched-body-2'].forEach(sel=>{

      const cont = document.querySelector(sel);

      if(!cont) return;

      // Separador entre horas

      cont.querySelectorAll('span').forEach(sp=>{

        const s = (sp.textContent||'').trim();

        if(s && s !== '-' && /[^0-9A-Za-z:\-]/.test(s)) sp.textContent = '-';

      });

      // D?as con acentos correctos

      const mapDias = {

        'Mi?rcoles':'Mi\u00E9rcoles', 'Mi?rcoles':'Mi\u00E9rcoles',

        'Sobado':'S\u00E1bado', 'S?bado':'S\u00E1bado'

      };

      cont.querySelectorAll('tr td:first-child').forEach(td=>{

        const raw = (td.textContent||'').trim();

        if(mapDias[raw]) td.textContent = mapDias[raw];

      });

    });

  }catch(_){ }

})();





// ===== Grupo Médico: asegurarnos de limpieza de overlay al cerrar =====

(function ensureGrupoModalCleanup(){

  function cleanup(){

    try{

      document.body.classList.remove('modal-open');

      document.querySelectorAll('.modal-backdrop').forEach(b=>b.parentNode?.removeChild(b));

    }catch(_){ }

  }

  const el = document.getElementById('modalGrupoSuggest');

  if(!el) return;

  el.addEventListener('hidden.bs.modal', cleanup);

  document.getElementById('modalGrupoNo')?.addEventListener('click', ()=>{

    const inst = window.bootstrap?.Modal?.getInstance ? window.bootstrap.Modal.getInstance(el) : null;

    inst?.hide();

    setTimeout(cleanup, 50);

  });

})();



// ===== Widget flotante: Reiniciar estado (demo local) =====

(function addResetWidget(){

  try{

    // Mostrar siempre (antes solo en localhost); se puede desactivar con window.mxHideResetWidget = true;
    if(window.mxHideResetWidget) return;

    // Crear botón flotante solo una vez
    if(document.getElementById('mx-dev-reset')) return;

    const btn = document.createElement('button');
    btn.id = 'mx-dev-reset';
    btn.type = 'button';
    btn.textContent = 'Restablecer';
    btn.setAttribute('aria-label','Restablecer estado');
    Object.assign(btn.style, {
      position:'fixed', top:'16px', bottom:'auto', right:'16px',
      background:'#d81b60', color:'#fff', border:'none',
      padding:'8px 14px', borderRadius:'18px', fontWeight:'600',
      boxShadow:'0 2px 8px rgba(0,0,0,0.2)', cursor:'pointer',
      zIndex:'3000', letterSpacing:'0.2px',
      pointerEvents:'auto'
    });



    const reset = ()=>{

      try{

        // Limpiar claves principales usadas en esta secci?n

        localStorage.removeItem('grupo_MÃƒÂ©dico_assoc');

        localStorage.removeItem('grupo_MÃƒÂ©dico_assoc:decline');

        localStorage.removeItem('mxmed_cons_schedules');

        localStorage.removeItem('mxmed_cons_schedules2');

        // Preferencias de navegaci?n (para evitar estados atascados)

        localStorage.removeItem('mxmed_menu_group');

        localStorage.removeItem('mxmed_last_panel');

        localStorage.removeItem('mxmed_info_tab');

      }catch(_){ }



      // Restablecer UI de asociaci?n de grupo

      try{

        const resetHorariosCampos = ()=>{

          try{

            document.querySelectorAll('.sched-table tr').forEach(tr=> tr.classList.remove('sched-defined'));

            document.querySelectorAll('input[id^="sch-act-"]').forEach(el=>{

              el.checked = false;

              try{ el.dispatchEvent(new Event('change')); }catch(_){ }

            });

            ['a1','b1','a2','b2'].forEach(slot=>{

              document.querySelectorAll(`input[id^="sch-${slot}-"]`).forEach(inp=>{

                inp.value = '';

                try{

                  inp.dispatchEvent(new Event('input'));

                  inp.dispatchEvent(new Event('change'));

                }catch(_){ }

              });

            });

          }catch(_){ }

        };

        resetHorariosCampos();

        mxResetLogoPreview();

        mxToggleLogoSyncMsg(false);

        mxToggleLogoManualMsg(false);

        const fotoPrev = document.getElementById('cons-foto-prev');

        const fotoImg = document.getElementById('cons-foto-img');

        const fotoInput = document.getElementById('cons-foto');

        const fotoMsg = document.getElementById('cons-foto-sync');

        if(fotoPrev){

          fotoPrev.style.display = 'none';

          fotoPrev.setAttribute('hidden','hidden');

        }

        if(fotoImg){ fotoImg.src = ''; }

        if(fotoInput){ fotoInput.value = ''; }

        if(fotoMsg){ fotoMsg.style.display = 'none'; }

        const rNo = document.getElementById('cons-grupo-no');

        const rSi = document.getElementById('cons-grupo-si');

        const grp = document.getElementById('cons-grupo-nombre');

        if(rNo){ rNo.checked = true; rNo.dispatchEvent(new Event('change')); }

        if(rSi){ rSi.checked = false; }

        if(grp){ grp.value=''; grp.classList.remove('grp-selected'); grp.setAttribute('disabled','disabled'); }

        const tit = document.getElementById('cons-titulo');

        if(tit){

          tit.value = '';

          tit.removeAttribute('data-autofill');

          tit.dispatchEvent(new Event('input'));

          tit.dispatchEvent(new Event('change'));

        }

        // Rehabilitar campos clave de direcci?n

        ['cp','colonia','municipio','estado','cons-calle','cons-numext'].forEach(id=>{

          const el = document.getElementById(id); if(!el) return;

          el.removeAttribute('disabled');

          try{ el.disabled = false; }catch(_){ }

          if(id==='colonia'){ el.classList.remove('select-open'); el.removeAttribute('size'); }

        });

      }catch(_){ }



      // Intentar re-disparar la l?gica de sugerencia si ya hay direcci?n

      try{

        const ev = new Event('change');

        ['cp','colonia','municipio','estado','cons-calle','cons-numext'].forEach(id=> document.getElementById(id)?.dispatchEvent(ev));

      }catch(_){ }

      // Reset generico de campos visibles (aplica en cualquier seccion)
      try{
        const trigger = (el, evt)=>{ try{ el.dispatchEvent(new Event(evt, {bubbles:true})); }catch(_){ } };
        const resetOne = (el)=>{
          const tag = (el.tagName||'').toLowerCase();
          const type = (el.getAttribute('type')||'').toLowerCase();
          if(type === 'checkbox' || type === 'radio'){
            el.checked = !!el.defaultChecked;
          }else if(type === 'file'){
            el.value = '';
          }else if(tag === 'select'){
            const defIndex = Array.from(el.options||[]).findIndex(o=> o.defaultSelected);
            el.selectedIndex = defIndex >=0 ? defIndex : 0;
          }else{
            el.value = el.defaultValue || '';
          }
          el.classList.remove('filled','is-valid','is-invalid','was-validated');
          trigger(el,'input');
          trigger(el,'change');
        };
        document.querySelectorAll('input, select, textarea').forEach(resetOne);
        document.querySelectorAll('[data-otp-input]').forEach(inp=> inp.classList.remove('filled','is-valid'));
        document.querySelectorAll('.chip-list').forEach(list=> list.innerHTML='');
        document.querySelectorAll('.save-ok').forEach(ok=> ok.style.display='none');
      }catch(_){ }
      // Reset Seguridad 2FA (UI)
      try{
        const panel = document.getElementById('seg-2fa-panel');
        if(panel){
          panel.classList.remove('show');
          panel.style.display = 'none';
          panel.setAttribute('aria-hidden','true');
        }
        document.querySelectorAll('[data-bs-target=\"#seg-2fa-panel\"]').forEach(btn=>{
          btn.setAttribute('aria-expanded','false');
        });
        const summary = document.querySelector('[data-twofa-summary]');
        if(summary){
          const badge = summary.querySelector('[data-twofa-status]');
          const lbl = summary.querySelector('[data-twofa-method-label]');
          const btnAct = summary.querySelector('[data-twofa-activate]');
          const btnChg = summary.querySelector('[data-twofa-change]');
          const btnOff = summary.querySelector('[data-twofa-disable]');
          if(badge){
            badge.textContent = '2FA inactivo';
            badge.classList.remove('bg-success');
            badge.classList.add('bg-secondary');
          }
          if(lbl) lbl.textContent = 'Selecciona un método para activarlo.';
          if(btnAct) btnAct.classList.remove('d-none');
          if(btnChg) btnChg.classList.add('d-none');
          if(btnOff) btnOff.classList.add('d-none');
        }
        // radio a app y panes
        const radios = document.querySelectorAll('input[name=\"twofa-method\"]');
        radios.forEach(r=> r.checked = r.getAttribute('data-twofa-method') === 'app');
        const panes = document.querySelectorAll('.twofa-pane');
        panes.forEach(p=> p.classList.toggle('d-none', p.getAttribute('data-twofa-view') !== 'app'));
        // limpiar OTPs y botones
        document.querySelectorAll('[data-otp-group]').forEach(g=>{
          g.querySelectorAll('[data-otp-input]').forEach(inp=>{
            inp.value = '';
            inp.classList.remove('filled','is-valid');
          });
        });
        [
          '[data-twofa-confirm-app]',
          '[data-twofa-confirm-sms]',
          '[data-twofa-confirm-wa]',
          '[data-twofa-confirm-call]'
        ].forEach(sel=>{
          const b = document.querySelector(sel);
          if(!b) return;
          b.textContent = 'Confirmar 2FA';
          b.classList.remove('btn-success');
          b.classList.add('btn-primary');
          b.disabled = true;
        });
        const backups = document.querySelector('[data-twofa-backups]');
        if(backups) backups.innerHTML = '';
      }catch(_){ }

      // Reset Estudios Diagnóstico (catálogo/órdenes)
      try{
        if(typeof window.mxResetEstudios === 'function'){
          window.mxResetEstudios();
        }
      }catch(_){ }

    };



    btn.addEventListener('click', reset);

    const mount = ()=>{
      if(document.getElementById('mx-dev-reset')) return;
      const target = document.body || document.documentElement;
      if(!target) return;
      target.appendChild(btn);
    };

    if(document.readyState === 'loading'){
      document.addEventListener('DOMContentLoaded', mount, {once:true});
    }else{
      mount();
    }

  }catch(_){ }

})();







function mxGetLogoSlot(){

  return document.getElementById('cons-logo-slot');

}

window._mx_logoDropTemplate = window._mx_logoDropTemplate || '';

function mxSetLogoSource(mode){

  const slot = mxGetLogoSlot();

  if(!slot){

    return;

  }

  if(mode){

    slot.dataset.logoSource = mode;

  }else{

    delete slot.dataset.logoSource;

  }

}

function mxGetLogoSource(){

  return mxGetLogoSlot()?.dataset.logoSource || '';

}

function mxToggleLogoSyncMsg(show){

  const msg = document.getElementById('cons-logo-sync');

  if(msg) msg.style.display = show ? 'block' : 'none';

}

function mxToggleLogoManualMsg(show){

  const msg = document.getElementById('cons-logo-manual');

  if(msg) msg.style.display = show ? 'block' : 'none';

}



function mxRebuildLogoDrop(){

  const slot = mxGetLogoSlot();

  if(!slot) return null;

  let tpl = window._mx_logoDropTemplate;

  if(!tpl){

    const existing = slot.querySelector('.logo-slot-drop');

    if(existing) return existing;

    return null;

  }

  const wrapper = document.createElement('div');

  wrapper.innerHTML = tpl.trim();

  const fresh = wrapper.firstElementChild;

  const prev = document.getElementById('cons-logo-prev');

  if(prev){

    slot.insertBefore(fresh, prev);

  }else{

    slot.appendChild(fresh);

  }

  if(typeof window.mxSetupUploadBox === 'function'){

    window.mxSetupUploadBox(fresh);

  }

  return fresh;

}



function mxResetLogoPreview(){

  const prev = document.getElementById('cons-logo-prev');

  const img  = document.getElementById('cons-logo-img');

  const slot = mxGetLogoSlot();

  let drop = slot?.querySelector('.logo-slot-drop');

  if(prev){

    prev.style.display = 'none';

    prev.setAttribute('hidden','hidden');

  }

  if(img){ img.src = ''; }

  if(slot){

    slot.classList.remove('show-preview');

    slot.classList.remove('has-logo');

    delete slot.dataset.logoSource;

  }

  if(drop){

    drop.remove();

    drop = null;

  }

  drop = drop || mxRebuildLogoDrop();

  if(drop){

    drop.removeAttribute('hidden');

    drop.style.removeProperty('display');

  }

  const uploadLogo = document.querySelector('.mf-upload[data-type="logo"]');

  if(uploadLogo){

    uploadLogo.classList.remove('has-logo');

    const ghost = uploadLogo.querySelector('.mf-ghost');

    if(ghost){

      ghost.style.display = '';

      ghost.removeAttribute('aria-hidden');

    }

  }

  const file = document.getElementById('cons-logo');

  if(file){

    file.removeAttribute('disabled');

    file.disabled = false;

    file.value = '';

  }

  mxSetLogoSource('');

}



























// Controlar visibilidad de 'Copiar lunes a todos'

(function(){

  function update(){

    const start=document.getElementById('sch-a1-mon');

    const end=document.getElementById('sch-b1-mon');

    const ready=!!((start?.value||'').trim() && (end?.value||'').trim());

    ['sched-copy-mon','sched-copy-mon-2'].forEach(id=>{

      const el=document.getElementById(id);

      if(!el) return;

      el.classList.toggle('d-none', !ready);

    });

  }

  ['input','change'].forEach(evt=>{

    document.addEventListener(evt, e=>{

      if(e.target && (e.target.id==='sch-a1-mon' || e.target.id==='sch-b1-mon')) update();

    }, true);

  });

  update();

})();

// ===== OTP UX helper (auto-advance & paste) =====
(function () {
  var groups = document.querySelectorAll('[data-otp-group]');
  if (!groups.length) return;

  function setupGroup(group) {
    var boxes = Array.prototype.slice.call(group.querySelectorAll('[data-otp-input]'));
    if (!boxes.length) return;

    function updateFilled() {
      boxes.forEach(function (box) {
        var has = !!(box.value || '').trim();
        if (has) {
          box.classList.add('filled');
        } else {
          box.classList.remove('filled');
        }
      });
    }
    function focusAt(pos){
      if(pos < 0 || pos >= boxes.length) return;
      var target = boxes[pos];
      target.focus();
      target.setSelectionRange(target.value.length, target.value.length);
    }
    function writeDigits(startIdx, digits, clearTail){
      if(clearTail){
        for(var t=startIdx; t<boxes.length; t+=1){
          boxes[t].value = '';
        }
      }
      var pos = startIdx;
      for(var i=0; i<digits.length && pos<boxes.length; i+=1, pos+=1){
        boxes[pos].value = digits.charAt(i);
      }
      updateFilled();
      focusAt(Math.min(pos, boxes.length - 1));
    }

    boxes.forEach(function (box, index) {
      if (box.__otpBound) return;
      box.__otpBound = true;

      box.addEventListener('input', function () {
        var digits = (box.value || '').replace(/[^0-9]/g, '');
        if(!digits){
          box.value = '';
          updateFilled();
          return;
        }
        if(digits.length > 1){
          writeDigits(index, digits, true);
          return;
        }
        box.value = digits;
        updateFilled();
        if (digits && index < boxes.length - 1) {
          focusAt(index + 1);
        }
      });

      box.addEventListener('keydown', function (ev) {
        if (ev.key === 'Backspace') {
          if (box.value) {
            box.value = '';
            updateFilled();
          } else if (index > 0) {
            ev.preventDefault();
            var prev = boxes[index - 1];
            prev.value = '';
            updateFilled();
            focusAt(index - 1);
          }
          return;
        }

        if (ev.key === 'ArrowLeft' && index > 0) {
          ev.preventDefault();
          focusAt(index - 1);
        } else if (ev.key === 'ArrowRight' && index < boxes.length - 1) {
          ev.preventDefault();
          focusAt(index + 1);
        }
      });

      box.addEventListener('paste', function (ev) {
        var clipboard = ev.clipboardData || window.clipboardData;
        if (!clipboard) return;
        var text = clipboard.getData('text') || '';
        var digits = text.replace(/\D/g, '');
        if (!digits) return;
        ev.preventDefault();
        writeDigits(index, digits, true);
      });
    });

    updateFilled();
  }

  groups.forEach(setupGroup);
})();































// ===== Seguridad: Verificación en dos pasos (2FA) =====
(function(){
  function initTwofa(){
    const panel = document.getElementById('seg-2fa-panel');
    if(!panel || panel.__twofaInit) return;
    panel.__twofaInit = true;

    const methodInputs = panel.querySelectorAll('input[name="twofa-method"]');
    const panes = panel.querySelectorAll('.twofa-pane');
    const secretEl = panel.querySelector('[data-twofa-secret]');
    const qrEl = panel.querySelector('[data-twofa-qr]');
    const backupsEl = panel.querySelector('[data-twofa-backups]');
    const generateBtn = panel.querySelector('[data-twofa-generate]');
    const otpApp = panel.querySelector('[data-twofa-otp-app]');
    const otpSms = panel.querySelector('[data-twofa-otp-sms]');
    const otpWa = panel.querySelector('[data-twofa-otp-wa]');
    const otpCall = panel.querySelector('[data-twofa-otp-call]');
    const confirmAppBtn = panel.querySelector('[data-twofa-confirm-app]');
    const confirmSmsBtn = panel.querySelector('[data-twofa-confirm-sms]');
    const confirmWaBtn = panel.querySelector('[data-twofa-confirm-wa]');
    const confirmCallBtn = panel.querySelector('[data-twofa-confirm-call]');
    const phoneInput = panel.querySelector('[data-twofa-phone]');
    const phoneWa = panel.querySelector('[data-twofa-phone-wa]');
    const phoneCall = panel.querySelector('[data-twofa-phone-call]');
    const sendBtn = panel.querySelector('[data-twofa-send]');
    const sendBtnWa = panel.querySelector('[data-twofa-send-wa]');
    const sendBtnCall = panel.querySelector('[data-twofa-send-call]');
    const sentMsg = panel.querySelector('[data-twofa-sent]');
    const sentMsgWa = panel.querySelector('[data-twofa-sent-wa]');
    const sentMsgCall = panel.querySelector('[data-twofa-sent-call]');
    const triggers = document.querySelectorAll('[data-bs-target="#seg-2fa-panel"]');
    const summary = document.querySelector('[data-twofa-summary]');
    const statusBadge = summary?.querySelector('[data-twofa-status]');
    const methodLabel = summary?.querySelector('[data-twofa-method-label]');
    const btnActivate = summary?.querySelector('[data-twofa-activate]');
    const btnChange = summary?.querySelector('[data-twofa-change]');
    const btnDisable = summary?.querySelector('[data-twofa-disable]');
    let backups = [];
    let twofaActive = false;
    let currentMethod = 'app';
    const methodName = { app:'App autenticadora', sms:'SMS', whatsapp:'WhatsApp', call:'Llamada' };
    const getSelectedMethod = ()=>{
      const checked = Array.from(methodInputs).find(r=> r.checked);
      return checked?.getAttribute('data-twofa-method') || 'app';
    };
        const updateSummary = ()=>{
      if(!summary) return;
      const isOn = !!twofaActive;
      if(statusBadge){
        statusBadge.textContent = isOn ? '2FA activo' : '2FA inactivo';
        statusBadge.classList.toggle('bg-success', isOn);
        statusBadge.classList.toggle('bg-secondary', !isOn);
      }
      if(methodLabel){
        const pretty = methodName[currentMethod] || currentMethod;
        methodLabel.textContent = isOn ? "Metodo: " + pretty : "Selecciona un metodo para activarlo.";
      }
      if(btnActivate) btnActivate.classList.toggle('d-none', isOn);
      if(btnChange) btnChange.classList.toggle('d-none', !isOn);
      if(btnDisable) btnDisable.classList.toggle('d-none', !isOn);
    };

    const randGroup = (len=4)=>{
      const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
      let out = '';
      for(let i=0;i<len;i+=1){
        out += chars.charAt(Math.floor(Math.random()*chars.length));
      }
      return out;
    };
    const newSecret = ()=> `${randGroup()}-${randGroup()}-${randGroup()}`;
    const generateBackups = (n=6)=> Array.from({length:n}, ()=> `${randGroup(4)}-${randGroup(4)}`);

    const renderBackups = ()=>{
      backups = generateBackups();
      if(!backupsEl) return;
      backupsEl.innerHTML = backups.map(code=>`<span class="badge bg-light text-muted border">${code}</span>`).join(' ');
    };

    const setSecret = ()=>{
      const secret = newSecret();
      if(secretEl) secretEl.textContent = secret;
      if(qrEl) qrEl.textContent = 'QR';
    };

    const setMethod = (method)=>{
      const target = method || 'app';
      methodInputs.forEach(r=>{ r.checked = (r.getAttribute('data-twofa-method') === target); });
      panes.forEach(p=>{
        p.classList.toggle('d-none', p.getAttribute('data-twofa-view') !== target);
      });
      const focusOtp = (group)=>{
        const first = group?.querySelector('[data-otp-input]');
        if(first) first.focus();
      };
      if(target === 'app'){
        focusOtp(otpApp);
      }else if(target === 'sms'){
        phoneInput?.focus();
      }else if(target === 'whatsapp'){
        phoneWa?.focus();
      }else if(target === 'call'){
        phoneCall?.focus();
      }
    };

    const getDigits = (group)=>{
      if(!group) return '';
      const boxes = group.querySelectorAll('[data-otp-input]');
      return Array.from(boxes).map(b=> (b.value||'').replace(/\D/g,'')).join('').slice(0,6);
    };

    const syncButtons = ()=>{
      if(confirmAppBtn) confirmAppBtn.disabled = getDigits(otpApp).length !== 6;
      if(confirmSmsBtn) confirmSmsBtn.disabled = getDigits(otpSms).length !== 6;
      if(confirmWaBtn) confirmWaBtn.disabled = getDigits(otpWa).length !== 6;
      if(confirmCallBtn) confirmCallBtn.disabled = getDigits(otpCall).length !== 6;
    };

    [otpApp, otpSms, otpWa, otpCall].forEach(group=>{
      if(!group) return;
      group.addEventListener('input', ()=> setTimeout(syncButtons, 0));
      group.addEventListener('paste', ()=> setTimeout(syncButtons, 30));
    });

    methodInputs.forEach(radio=>{
      radio.addEventListener('change', ()=>{
        const method = radio.getAttribute('data-twofa-method') || 'app';
        setMethod(method);
      });
    });

    generateBtn?.addEventListener('click', (ev)=>{
      ev.preventDefault();
      setSecret();
      renderBackups();
    });

    const bindSender = (btn, phone, msg)=>{
      if(!btn || !phone) return;
      btn.addEventListener('click', (ev)=>{
        ev.preventDefault();
        const val = (phone.value || '').trim();
        if(!val){
          phone.focus();
          return;
        }
        msg?.classList.remove('d-none');
        msg?.classList.add('text-success');
        if(btn.classList.contains('disabled')) return;
        btn.classList.add('disabled');
        btn.setAttribute('aria-disabled','true');
        setTimeout(()=> btn.classList.remove('disabled'), 5000);
      });
    };

    bindSender(sendBtn, phoneInput, sentMsg);
    bindSender(sendBtnWa, phoneWa, sentMsgWa);
    bindSender(sendBtnCall, phoneCall, sentMsgCall);

    const markConfirmed = (btn)=>{
      if(!btn) return;
      btn.textContent = 'Confirmado';
      btn.classList.remove('btn-primary');
      btn.classList.add('btn-success');
      btn.disabled = true;
    };

    confirmAppBtn?.addEventListener('click', (ev)=>{
      ev.preventDefault();
      if(getDigits(otpApp).length !== 6) return;
      markConfirmed(confirmAppBtn);
    });

    confirmAppBtn?.addEventListener('click', (ev)=>{
      ev.preventDefault();
      if(getDigits(otpApp).length !== 6) return;
      markConfirmed(confirmAppBtn);
      currentMethod = getSelectedMethod();
      twofaActive = true;
      updateSummary();
      closePanel();
    });

    confirmSmsBtn?.addEventListener('click', (ev)=>{
      ev.preventDefault();
      if(getDigits(otpSms).length !== 6) return;
      markConfirmed(confirmSmsBtn);
      currentMethod = getSelectedMethod();
      twofaActive = true;
      updateSummary();
      closePanel();
    });

    confirmWaBtn?.addEventListener('click', (ev)=>{
      ev.preventDefault();
      if(getDigits(otpWa).length !== 6) return;
      markConfirmed(confirmWaBtn);
      currentMethod = getSelectedMethod();
      twofaActive = true;
      updateSummary();
      closePanel();
    });

    confirmCallBtn?.addEventListener('click', (ev)=>{
      ev.preventDefault();
      if(getDigits(otpCall).length !== 6) return;
      markConfirmed(confirmCallBtn);
      currentMethod = getSelectedMethod();
      twofaActive = true;
      updateSummary();
      closePanel();
    });

    const openPanel = ()=>{
      panel.classList.add('show');
      panel.style.display = 'block';
      panel.removeAttribute('aria-hidden');
      triggers.forEach(btn=> btn.setAttribute('aria-expanded','true'));
      setMethod(twofaActive ? currentMethod : 'app');
      syncButtons();
    };
    const closePanel = ()=>{
      panel.classList.remove('show');
      panel.style.display = 'none';
      panel.setAttribute('aria-hidden','true');
      triggers.forEach(btn=> btn.setAttribute('aria-expanded','false'));
    };

    triggers.forEach(btn=>{
      btn.addEventListener('click', (ev)=>{
        ev.preventDefault();
        openPanel();
      });
    });

    document.addEventListener('click', (ev)=>{
      const btn = ev.target.closest('[data-bs-target="#seg-2fa-panel"]');
      if(!btn) return;
      ev.preventDefault();
      openPanel();
    });

    panel.querySelectorAll('[data-sec-close]').forEach(btn=>{
      btn.addEventListener('click', (ev)=>{
        ev.preventDefault();
        closePanel();
      });
    });

    btnActivate?.addEventListener('click', (ev)=>{
      ev.preventDefault();
      currentMethod = 'app';
      setMethod('app');
      openPanel();
    });

    btnChange?.addEventListener('click', (ev)=>{
      ev.preventDefault();
      setMethod(currentMethod || 'app');
      openPanel();
    });

    btnDisable?.addEventListener('click', (ev)=>{
      ev.preventDefault();
      twofaActive = false;
      updateSummary();
      closePanel();
    });

    // init defaults
    setSecret();
    renderBackups();
    setMethod('app');
    syncButtons();
    updateSummary();
  }

  if(document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', initTwofa);
  }else{
    initTwofa();
  }
})();


// ==== Biometría remota via QR + control de sesiones (mock UI listo para backend) ====
(function initBiometricAccess(){
  const container = document.querySelector('#p-seguridad');
  const panel = document.querySelector('#bio-panel');
  const startBtn = container?.querySelector('[data-bio-start]');
  const badge = container?.querySelector('[data-bio-badge]');
  const last = container?.querySelector('[data-bio-last]');
  const markTrustedBtn = container?.querySelector('[data-bio-mark-trusted]');
  if(!container || !panel || !startBtn) return;

  const qrBox = panel.querySelector('[data-bio-qr]');
  const sessionEl = panel.querySelector('[data-bio-session]');
  const countdownEl = panel.querySelector('[data-bio-countdown]');
  const statusEl = panel.querySelector('[data-bio-status]');
  const refreshBtn = panel.querySelector('[data-bio-refresh]');
  const trustAsk = panel.querySelector('[data-bio-trustask]');
  const trustCheck = panel.querySelector('#bio-trust-check');
  const trustSave = panel.querySelector('[data-bio-save-trust]');
  let timer = null;
  let secs = 0;
  let trusted = false;

  const setBadge = (active)=>{
    if(!badge) return;
    badge.classList.toggle('bg-success', active);
    badge.classList.toggle('bg-secondary', !active);
    badge.textContent = active ? 'Activo' : 'Inactivo';
  };

  const setStatus = (txt, cls)=>{
    if(!statusEl) return;
    statusEl.textContent = txt;
    statusEl.classList.remove('success','error');
    if(cls) statusEl.classList.add(cls);
  };

  const formatCountdown = ()=>{
    const m = String(Math.floor(secs/60)).padStart(2,'0');
    const s = String(secs%60).padStart(2,'0');
    return `${m}:${s}`;
  };

  const updateCountdown = ()=>{
    if(countdownEl) countdownEl.textContent = formatCountdown();
  };

  const randomSession = ()=> 'BIO-' + Math.random().toString(36).substring(2,7).toUpperCase();

  const renderQR = (id)=>{
    if(qrBox){
      qrBox.textContent = id;
      qrBox.setAttribute('aria-label', `Código para sesión ${id}`);
    }
  };

  const stopTimer = ()=>{
    if(timer) clearInterval(timer);
    timer = null;
  };

  const openPanel = ()=>{
    panel.classList.add('show');
    panel.style.display = 'block';
    panel.removeAttribute('aria-hidden');
  };

  const closePanel = ()=>{
    stopTimer();
    panel.classList.remove('show');
    panel.style.display = 'none';
    panel.setAttribute('aria-hidden','true');
    trustAsk?.classList.add('d-none');
  };

  const simulateApproval = ()=>{
    // Simula confirmación desde el móvil tras 8s si no expiró
    setTimeout(()=>{
      if(secs <= 0) return;
      setStatus('Autenticación confirmada desde tu móvil.', 'success');
      setBadge(true);
      if(last) last.textContent = 'Biometría activa en este equipo.';
      trustAsk?.classList.remove('d-none');
      markTrustedBtn?.removeAttribute('disabled');
      if(trustSave) trustSave.disabled = !(trustCheck?.checked);
    }, 8000);
  };

  const startSession = ()=>{
    stopTimer();
    const sid = randomSession();
    if(sessionEl) sessionEl.textContent = sid;
    renderQR(sid);
    secs = 120;
    updateCountdown();
    setStatus('Esperando autenticación biométrica…');
    timer = setInterval(()=>{
      secs -= 1;
      updateCountdown();
      if(secs <= 0){
        setStatus('El código expiró. Genera uno nuevo.', 'error');
        stopTimer();
      }
    }, 1000);
    simulateApproval();
  };

  startBtn.addEventListener('click', (ev)=>{
    ev.preventDefault();
    openPanel();
    startSession();
  });

  refreshBtn?.addEventListener('click', (ev)=>{
    ev.preventDefault();
    startSession();
  });

  panel.querySelectorAll('[data-bio-close]').forEach(btn=>{
    btn.addEventListener('click', (ev)=>{
      ev.preventDefault();
      closePanel();
    });
  });

  trustCheck?.addEventListener('change', ()=>{
    if(trustSave) trustSave.disabled = !trustCheck.checked;
  });

  trustSave?.addEventListener('click', (ev)=>{
    ev.preventDefault();
    if(!trustCheck?.checked) return;
    trusted = true;
    setStatus('Equipo marcado como confiable por 21 días.', 'success');
    markTrustedBtn?.setAttribute('disabled','disabled');
  });

  markTrustedBtn?.addEventListener('click', (ev)=>{
    ev.preventDefault();
    openPanel();
    startSession();
    trustAsk?.classList.remove('d-none');
    if(trustSave) trustSave.disabled = !trustCheck?.checked;
  });
})();

(function initSessionPanel(){
  const tbody = document.querySelector('[data-sessions-body]');
  if(!tbody) return;
  const refreshBtn = document.querySelector('[data-session-refresh]');
  const closeOthersBtn = document.querySelector('[data-session-close-others]');

  let sessions = [
    {id:'current', device:'Windows • Chrome', location:'Aguascalientes, MX', last:'Ahora', trusted:true, current:true},
    {id:'ios-01', device:'iPhone • Safari', location:'CDMX, MX', last:'Hace 2 h', trusted:true, current:false},
    {id:'and-02', device:'Android • App', location:'Zapopan, MX', last:'Ayer', trusted:false, current:false}
  ];

  const render = ()=>{
    tbody.innerHTML = '';
    sessions.forEach(s=>{
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${s.device}${s.current ? ' <span class=\"badge bg-info text-dark\">Este equipo</span>' : ''}</td>
        <td>${s.location}</td>
        <td>${s.last}</td>
        <td>${s.trusted ? '<span class=\"badge bg-success\">Sí</span>' : '<span class=\"badge bg-secondary\">No</span>'}</td>
        <td class=\"text-end\">
          ${s.current ? '<span class=\"text-muted small\">Activa</span>' : '<button class=\"btn btn-link btn-sm p-0\" data-session-close=\"'+s.id+'\">Cerrar sesión</button>'}
        </td>
      `;
      tbody.appendChild(tr);
    });
  };

  const closeSession = (id)=>{
    sessions = sessions.filter(s=> s.id !== id || s.current);
    render();
  };

  tbody.addEventListener('click', (ev)=>{
    const btn = ev.target.closest('[data-session-close]');
    if(!btn) return;
    ev.preventDefault();
    closeSession(btn.getAttribute('data-session-close'));
  });

  refreshBtn?.addEventListener('click', (ev)=>{
    ev.preventDefault();
    render();
  });

  closeOthersBtn?.addEventListener('click', (ev)=>{
    ev.preventDefault();
    sessions = sessions.filter(s=> s.current);
    render();
  });

  render();
})();
























// ================== SUSCRIPCIÓN (maqueta) ==================
(()=>{
  const pane = document.getElementById('p-suscripcion');
  if(!pane) return;

  const els = {
    planName: pane.querySelector('[data-subp-current-name]'),
    status: pane.querySelector('[data-subp-current-status]'),
    since: pane.querySelector('[data-subp-since]'),
    until: pane.querySelector('[data-subp-until]'),
    autorenew: pane.querySelector('#subp-autorenew'),
    renewBtn: pane.querySelector('[data-subp-renew]'),
    currentTitle: pane.querySelector('[data-subp-current-title]'),
    currentNote: pane.querySelector('[data-subp-current-note]'),
    currentFeat: pane.querySelector('[data-subp-current-features]'),
    currentAlert: pane.querySelector('[data-subp-current-alert]'),
    nextBill: pane.querySelector('[data-subp-next-bill]'),
    renewCTA: pane.querySelector('[data-subp-renew-cta]'),
    catalog: pane.querySelector('[data-subp-catalog]'),
    couponInput: pane.querySelector('[data-subp-coupon-input]'),
    couponApply: pane.querySelector('[data-subp-coupon-apply]'),
    couponMsg: pane.querySelector('[data-subp-coupon-msg]'),
    invoiceBtn: pane.querySelector('[data-subp-invoice]'),
    invoiceHint: pane.querySelector('[data-subp-invoice-hint]'),
    historyBody: pane.querySelector('[data-subp-history]'),
    historyRefresh: pane.querySelector('[data-subp-history-refresh]'),
    billingRadios: pane.querySelectorAll('input[name="subp-billing"]')
  };

  const data = {
    billing: 'monthly',
    current: {
      id: 'optimo',
      name: 'Óptimo',
      since: '22 Dic 2024',
      until: '22 Dic 2025',
      status: 'Activo',
      nextBill: '22 Dic 2025',
      autorenew: true,
      note: 'Tu perfil está en línea / tienes acceso a:',
      alert: 'Tu anualidad vence el 22 Diciembre 2025 · RENUEVA AHORA',
      features: ['Perfil en línea','Agenda','Expediente','Recetas']
    },
    plans: [
      { id:'pro', name:'Profesional', monthly:0, yearly:0, features:['Perfil en línea','Agenda','Expediente','Recetas','Asistente IA'] },
      { id:'optimo', name:'Óptimo', monthly:0, yearly:0, features:['Perfil en línea','Agenda','Expediente','Recetas'] },
      { id:'estandar', name:'Estándar', monthly:0, yearly:0, features:['Perfil en línea','Agenda'] },
      { id:'basico', name:'Básico', monthly:0, yearly:0, features:['Perfil en línea'] }
    ],
    history: [
      {fecha:'2025-06-01', plan:'Óptimo', mov:'Renovación', vig:'22 Dic 2025', est:'Activo', apoyo:'—'},
      {fecha:'2024-12-22', plan:'Estándar', mov:'Upgrade', vig:'22 Dic 2024', est:'Activo', apoyo:'—'}
    ]
  };

  function fmtMoney(n){
    return `$${n.toLocaleString('es-MX')} MXN`;
  }

  function renderCurrent(){
    if(els.planName){
      els.planName.textContent = data.current.name;
      els.planName.setAttribute('data-plan', data.current.id || '');
    }
    if(els.status) els.status.textContent = data.current.status;
    if(els.since) els.since.textContent = data.current.since;
    if(els.until) els.until.textContent = data.current.until;
    if(els.autorenew) els.autorenew.checked = !!data.current.autorenew;
    if(els.currentTitle) els.currentTitle.textContent = `${data.current.name} · Tu plan actual`;
    if(els.currentNote) els.currentNote.textContent = data.current.note || '';
    if(els.nextBill) els.nextBill.textContent = data.current.nextBill || '—';
    if(els.currentFeat){
      els.currentFeat.innerHTML = data.current.features.map(f=>`<li class="subp-feature"><span class="material-symbols-rounded mat-ico" aria-hidden="true">check</span><span>${f}</span></li>`).join('');
    }
    if(els.currentAlert){
      if(data.current.alert){
        els.currentAlert.textContent = data.current.alert;
        els.currentAlert.classList.remove('d-none');
      } else {
        els.currentAlert.classList.add('d-none');
      }
    }
  }

  function renderHistory(){
    if(!els.historyBody) return;
    const today = new Date();
    const ymNow = `${today.getFullYear()}-${String(today.getMonth()+1).padStart(2,'0')}`;
    const hist = [...data.history];
    // Simular una línea del mes en curso con opción de facturar
    if(!hist.some(h => (h.fecha || '').startsWith(ymNow))){
      const iso = today.toISOString().slice(0,10);
      hist.unshift({
        fecha: iso,
        plan: data.current.name || '—',
        mov: 'Renovación',
        vig: data.current.until || iso,
        est: 'Pagado',
        apoyo: '—'
      });
    }
    els.historyBody.innerHTML = hist.map(h=>{
      const isCurrentMonth = (h.fecha || '').startsWith(ymNow);
      const facturaBtn = isCurrentMonth ? `<button class="btn btn-primary btn-sm" type="button">Solicitar factura</button>` : '';
      return `<tr><td>${h.fecha}</td><td>${h.plan}</td><td>${h.mov}</td><td>${h.vig}</td><td>${h.est}</td><td>${h.apoyo}</td><td>${facturaBtn}</td></tr>`;
    }).join('');
  }

  function renderCatalog(){
    if(!els.catalog) return;
    const yearly = data.billing === 'yearly';
    els.catalog.innerHTML = data.plans.map(p=>{
      const price = yearly ? p.yearly : p.monthly;
      const save = yearly ? (p.monthly*12 - p.yearly) : 0;
      const isCurrent = p.id === data.current.id;
      return `<div class="subp-plan ${isCurrent?'current':''}" data-plan="${p.id}">
        ${isCurrent?'<div class="subp-plan-badge">Plan actual</div>':''}
        <div class="subp-plan-title">${p.name}</div>
        <div class="subp-price">${fmtMoney(price)} <small>${yearly?'/ año':'/ mes'}</small></div>
        <div class="mt-2">${p.features.map(f=>`<div class="subp-feature"><span class="material-symbols-rounded mat-ico" aria-hidden="true">check</span><span>${f}</span></div>`).join('')}</div>
        ${save>0?`<div class="subp-save">Ahorra $${save.toLocaleString('es-MX')} al contratar anual</div>`:''}
        <button class="btn ${isCurrent?'btn-outline-primary':'btn-primary'} subp-btn" type="button" data-subp-select="${p.id}">${isCurrent?'Renovar':'Seleccionar'}</button>
      </div>`;
    }).join('');
    els.catalog.querySelectorAll('[data-subp-select]').forEach(btn=>{
      btn.addEventListener('click',()=>{
        const id = btn.getAttribute('data-subp-select');
        console.log('Seleccionar plan', id);
      });
    });
  }

  // Eventos
  els.billingRadios.forEach(r=>{
    r.addEventListener('change', ()=>{
      data.billing = r.value === 'yearly' ? 'yearly' : 'monthly';
      renderCatalog();
    });
  });
  els.renewBtn?.addEventListener('click', ()=>console.log('Renovar plan actual'));
  els.renewCTA?.addEventListener('click', ()=>console.log('Renovar plan actual'));
  els.couponApply?.addEventListener('click', ()=>{
    const code = (els.couponInput?.value||'').trim();
    els.couponMsg.textContent = code ? `Cupón "${code}" aplicado (demo)` : 'Sin cupón aplicado';
  });
  els.invoiceBtn?.addEventListener('click', ()=>{
    els.invoiceHint.textContent = 'Solicitud de factura registrada (demo)';
  });
  els.historyRefresh?.addEventListener('click', ()=>{
    renderHistory();
  });

  renderCurrent();
  renderCatalog();
  renderHistory();
})();

// ====== Estudios: modal catálogo laboratorio ======
(function(){
  if(!window.bootstrap) return;
  const studiesPane = document.getElementById('t-estudios');
  if(!studiesPane) return;
  if(studiesPane.dataset.estudiosOrdersInit === '1') return;
  studiesPane.dataset.estudiosOrdersInit = '1';

  const PICK_MAP_LAB = {
    'Biometría hemática (BH / CBC)': ['Biometría hemática (BH / CBC)'],
    'EGO (examen general de orina)': ['EGO (examen general de orina)'],
    'HbA1c': ['HbA1c'],
    'PCR ultrasensible': ['PCR ultrasensible']
  };
  const PACK_MAP_LAB = {
    'QS (Glucosa, Urea, Creatinina)': ['Glucosa','Urea','Creatinina'],
    'Perfil lipídico': ['Colesterol total','HDL','LDL','Triglicéridos','Colesterol no-HDL'],
    'Perfil hepático': ['AST (TGO)','ALT (TGP)','Fosfatasa alcalina (ALP)','GGT','Bilirrubina total','Albúmina','Proteínas totales'],
    'Perfil tiroideo': ['TSH','T4 libre'],
    'Control de diabetes': ['Glucosa','HbA1c','EGO (examen general de orina)','Microalbuminuria'],
    'ETS básico': ['VIH Ag/Ac','VDRL / RPR','HBsAg','VHC (anticuerpos)'],
    'Preop básico': ['Biometría hemática (BH / CBC)','TP / INR','TTPa (aPTT)','Glucosa','Creatinina'],
    'Preop gineco': ['Biometría hemática (BH / CBC)','TP / INR','TTPa (aPTT)','Glucosa','Creatinina','β-hCG','EGO (examen general de orina)'],
    'Preop ortopedia': ['Biometría hemática (BH / CBC)','TP / INR','TTPa (aPTT)','Glucosa','Creatinina','Sodio','Potasio'],
    'Reuma básico': ['ANA','Factor reumatoide','VSG','PCR ultrasensible','Anti-CCP'],
    'Embarazo básico': ['β-hCG','VIH Ag/Ac','VDRL / RPR','HBsAg','Anti-HBs','EGO (examen general de orina)'],
    'Panel viral respiratorio': ['Influenza A','Influenza B','RSV','SARS-CoV-2 (COVID-19)'],
    'TORCH': ['Toxoplasma','Rubéola','CMV','Herpes'],
    'Panel infeccioso PCR': ['SARS-CoV-2 (COVID-19)','Influenza A','Influenza B','RSV']
  };
  const LAB_SEARCH_CONFIG = {
    minChars: 2,
    maxResults: 12,
    boosts: {
      labelPrefix: 3,
      labelContains: 1,
      aliasPrefix: 4,
      aliasContains: 2
    }
  };
  const LAB_SEARCH_META = {
    glucose: { aliases: ['glu','glucosa en sangre'] },
    hba1c: { aliases: ['a1c','hemoglobina glicosilada'] },
    fructosamine: { aliases: ['fructosamina'] },
    ogtt: { aliases: ['curva glucosa','tolerancia a la glucosa','ogtt'] },
    urea: { aliases: ['urea'] },
    creatinine: { aliases: ['creat','creatinina'] },
    egfr: { aliases: ['tfg','egfr'] },
    bun: { aliases: ['bun','nitrogeno ureico'] },
    uric_acid: { aliases: ['urico','ácido úrico'] },
    sodium: { aliases: ['na','sodio'] },
    potassium: { aliases: ['k','potasio'] },
    chloride: { aliases: ['cl','cloro'] },
    calcium: { aliases: ['ca','calcio'] },
    magnesium: { aliases: ['mg','magnesio'] },
    phosphorus: { aliases: ['p','fosforo','fósforo'] },
    chol_total: { aliases: ['col total','colesterol total'] },
    hdl: { aliases: ['hdl','hdl-c'] },
    ldl: { aliases: ['ldl','ldl-c'] },
    triglycerides: { aliases: ['tgs','trigs','trigliceridos','triglicéridos'] },
    non_hdl: { aliases: ['no hdl','col no hdl'] },
    ast: { aliases: ['tgo','ast'] },
    alt: { aliases: ['tgp','alt'] },
    alp: { aliases: ['fosfatasa','alp'] },
    ggt: { aliases: ['ggt'] },
    bilirubin_total: { aliases: ['bt','bilirrubina total'] },
    bilirubin_direct: { aliases: ['bd','bilirrubina directa'] },
    bilirubin_indirect: { aliases: ['bi','bilirrubina indirecta'] },
    albumin: { aliases: ['alb','albumina','albúmina'] },
    total_protein: { aliases: ['pt','proteinas totales','proteínas totales'] },
    amylase: { aliases: ['amilasa'] },
    lipase: { aliases: ['lipasa'] },
    cbc: { aliases: ['bh','cbc','hemograma','biometria hematica','biometría hemática'] },
    hgb_hct: { aliases: ['hb','hcto','hemoglobina','hematocrito'] },
    platelets: { aliases: ['plt','plaquetas'] },
    iron: { aliases: ['fe','hierro'] },
    tibc: { aliases: ['tibc','ctfh'] },
    transferrin_sat: { aliases: ['sat transferrina','saturacion transferrina','saturación transferrina'] },
    ferritin: { aliases: ['ferritina'] },
    pt_inr: { aliases: ['tp','inr'] },
    aptt: { aliases: ['tt pa','aptt','ttpt','ttpa'] },
    fibrinogen: { aliases: ['fibrinogeno','fibrinógeno'] },
    d_dimer: { aliases: ['d dimer','dimero d','dímero d'] },
    tsh: { aliases: ['tsh'] },
    ft4: { aliases: ['t4l','ft4','t4 libre'] },
    ft3: { aliases: ['t3l','ft3','t3 libre'] },
    anti_tpo: { aliases: ['tpo','anti tpo'] },
    anti_tg: { aliases: ['anti tg','anti tiroglobulina'] },
    bhcg: { aliases: ['bhcg','beta hcg','β-hcg'] },
    lh: { aliases: ['lh'] },
    fsh: { aliases: ['fsh'] },
    prolactin: { aliases: ['prl','prolactina'] },
    estradiol: { aliases: ['e2','estradiol'] },
    progesterone: { aliases: ['p4','progesterona'] },
    testosterone_total: { aliases: ['testo total','testosterona total'] },
    testosterone_free: { aliases: ['testo libre','testosterona libre'] },
    crp_hs: { aliases: ['pcr us','pcr-us','hs-crp','pcr ultrasensible'] },
    esr: { aliases: ['vsg','esr','eritrosedimentacion','eritrosedimentación'] },
    ana: { aliases: ['ana'] },
    ena: { aliases: ['ena'] },
    anca: { aliases: ['anca'] },
    rf: { aliases: ['fr','rf','factor reumatoide'] },
    anti_ccp: { aliases: ['ccp','anti ccp'] },
    c3: { aliases: ['c3'] },
    c4: { aliases: ['c4'] },
    vitamin_d: { aliases: ['25-oh','25oh vitamina d','vit d'] },
    vitamin_b12: { aliases: ['b12','vit b12'] },
    folate: { aliases: ['folato','acido folico','ácido fólico'] },
    igg: { aliases: ['igg'] },
    iga: { aliases: ['iga'] },
    igm: { aliases: ['igm'] },
    hiv_ag_ac: { aliases: ['vih','hiv','vih ag/ac','hiv ag/ac'] },
    vdrl_rpr: { aliases: ['vdrl','rpr'] },
    hbsag: { aliases: ['hbsag','hepatitis b s ag'] },
    anti_hbs: { aliases: ['anti hbs','anti-hbs'] },
    anti_hbc: { aliases: ['anti hbc','anti-hbc'] },
    hcv_ab: { aliases: ['vhc','hcv','anti hcv'] },
    toxoplasma: { aliases: ['toxoplasma'] },
    rubella: { aliases: ['rubeola','rubéola','rubella'] },
    cmv: { aliases: ['cmv','citomegalovirus'] },
    herpes: { aliases: ['herpes'] },
    influenza_a: { aliases: ['influenza a','flu a'] },
    influenza_b: { aliases: ['influenza b','flu b'] },
    rsv: { aliases: ['rsv','virus sincitial'] },
    covid19: { aliases: ['covid','covid-19','sars-cov-2'] },
    urine_culture: { aliases: ['urocultivo'] },
    blood_culture: { aliases: ['hemocultivo'] },
    stool_culture: { aliases: ['coprocultivo'] },
    throat_swab: { aliases: ['exudado faringeo','faríngeo'] },
    vaginal_swab: { aliases: ['exudado vaginal'] },
    urinalysis: { aliases: ['ego','urinalisis','urinalysis','examen general de orina'] },
    microalbumin: { aliases: ['microalbumina','microalbuminuria'] },
    fecal_occult_blood: { aliases: ['soh','sangre oculta','sangre oculta en heces'] },
    stool_ova_parasites: { aliases: ['coproparasitoscopico','coproparasitoscópico','parasitos'] },
    factor_v_leiden: { aliases: ['factor v leiden'] },
    protein_c: { aliases: ['proteina c','proteína c'] },
    protein_s: { aliases: ['proteina s','proteína s'] },
    cyp2c19: { aliases: ['cyp2c19'] },
    cyp2d6: { aliases: ['cyp2d6'] }
  };
  const LAB_PACKAGE_META = {
    'QS (Glucosa, Urea, Creatinina)': { aliases: ['qs','bmp','quimica sanguinea','química sanguínea'] },
    'Perfil lipídico': { aliases: ['perfil lipidico','lipid panel','p lipidico'] },
    'Perfil hepático': { aliases: ['perfil hepatico','perfil hepático','pfh','funcion hepatica','función hepática'] },
    'Perfil tiroideo': { aliases: ['perfil tiroideo','tiroides','pt'] },
    'Control de diabetes': { aliases: ['control diabetes','diabetes'] },
    'ETS básico': { aliases: ['ets','its','std'] },
    'Preop básico': { aliases: ['preop','preoperatorio','preop basico'] },
    'Preop gineco': { aliases: ['preop gineco','preoperatorio gineco'] },
    'Preop ortopedia': { aliases: ['preop ortopedia','preoperatorio ortopedia'] },
    'Reuma básico': { aliases: ['reuma','reumatologia','reuma basico'] },
    'Embarazo básico': { aliases: ['embarazo','prenatal'] },
    'Panel viral respiratorio': { aliases: ['panel viral','respiratorio','influenza','covid'] },
    'TORCH': { aliases: ['torch'] },
    'Panel infeccioso PCR': { aliases: ['pcr','panel pcr','panel infeccioso'] }
  };
  const PICK_MAP_GEN = {};
  const PACK_MAP_GEN = {
    'NIPT estándar': ['NIPT (tamiz prenatal no invasivo)'],
    'CMA / Microarray (postnatal)': ['Microarreglo cromosómico (CMA / Microarray)'],
    'Exoma clínico TRIO (WES)': ['Exoma clínico (WES)'],
    'Panel multigénico de cáncer hereditario': ['Panel de cáncer hereditario (multigénico)'],
    'Cardiogenética (miocardiopatías / canalopatías)': ['Panel genético (NGS)'],
    'Neurogenética (epilepsia / neurodesarrollo)': ['Panel genético (NGS)'],
    'Panel tumoral somático (NGS) — FFPE': ['Panel tumoral (NGS) — somático']
  };
  const PACK_FLAG_MAP_GEN = {
    'NIPT estándar': ['context_prenatal','requires_consent','genetic_counseling','sample_blood'],
    'CMA / Microarray (postnatal)': ['context_postnatal','requires_consent','genetic_counseling','sample_blood'],
    'Exoma clínico TRIO (WES)': ['context_postnatal','requires_consent','genetic_counseling','trio','include_cnv','acmg_secondary_findings','sample_blood'],
    'Panel multigénico de cáncer hereditario': ['context_postnatal','requires_consent','genetic_counseling','include_cnv','sample_blood'],
    'Cardiogenética (miocardiopatías / canalopatías)': ['context_postnatal','requires_consent','genetic_counseling','include_cnv','sample_blood'],
    'Neurogenética (epilepsia / neurodesarrollo)': ['context_postnatal','requires_consent','genetic_counseling','include_cnv','sample_blood'],
    'Panel tumoral somático (NGS) — FFPE': ['context_postnatal','requires_consent','sample_ffpe']
  };
  const GENETICS_SEARCH_CONFIG = {
    minChars: 2,
    maxResults: 12,
    boosts: {
      labelPrefix: 3,
      labelContains: 1,
      aliasPrefix: 5,
      aliasContains: 2
    }
  };
  const GENETICS_SEARCH_META = {
    karyotype: { aliases: ['cariotipo','karyotype'] },
    cma_microarray: { aliases: ['cma','microarray','array cgh','acgh','a cgh'] },
    ngs_panel: { aliases: ['panel ngs','panel genes','gene panel','ngs'] },
    wes: { aliases: ['exoma','wes','whole exome'] },
    wgs: { aliases: ['genoma','wgs','whole genome'] },
    targeted_variant: { aliases: ['prueba dirigida','single gene','gen unico','gen único'] },
    nipt: { aliases: ['nipt','prenatal no invasivo','tamiz prenatal'] },
    carrier_screening: { aliases: ['portadores','carrier screening'] },
    hereditary_cancer_germline: { aliases: ['panel cancer','panel cáncer','multicancer','panel multicancer'] },
    brca1_2: { aliases: ['brca','brca1','brca2'] },
    lynch: { aliases: ['lynch','mlh1','msh2','msh6','pms2'] },
    thrombophilia: { aliases: ['trombofilia','factor v leiden','protrombina'] },
    pgx: { aliases: ['pgx','farmacogenomica','farmacogenómica','farmacogenetica','farmacogenética'] },
    somatic_tumor_ngs: { aliases: ['panel tumoral','ngs tumor','somatico','somático','tumor'] }
  };
  const GENETICS_PACKAGE_META = {
    'NIPT estándar': { aliases: ['nipt','tamiz prenatal'] },
    'CMA / Microarray (postnatal)': { aliases: ['cma','microarray','postnatal'] },
    'Exoma clínico TRIO (WES)': { aliases: ['exoma trio','wes trio','trio'] },
    'Panel multigénico de cáncer hereditario': { aliases: ['panel cancer','multicancer'] },
    'Cardiogenética (miocardiopatías / canalopatías)': { aliases: ['cardiogenetica','cardiogenética','miocardiopatia','miocardiopatía','canalopatias','canalopatías','qt largo','brugada'] },
    'Neurogenética (epilepsia / neurodesarrollo)': { aliases: ['neurogenetica','neurogenética','epilepsia','neurodesarrollo','autismo','ataxia'] },
    'Panel tumoral somático (NGS) — FFPE': { aliases: ['panel tumoral','ngs tumoral','ffpe','somatico tumor'] }
  };
  const PICK_MAP_FUNC = {};
  const PACK_MAP_FUNC = {
    'Espirometría pre/post broncodilatador': ['Espirometría'],
    'PFR completo (Espirometría + Volúmenes + DLCO)': ['Pruebas funcionales respiratorias completas (PFR completo)'],
    'PSG diagnóstica': ['Polisomnografía (PSG) diagnóstica'],
    'HSAT (apnea del sueño en casa)': ['Estudio domiciliario de apnea (HSAT)'],
    'EMG + VCN (estudio completo)': ['EMG + Velocidades de conducción nerviosa (VCN)'],
    'EEG rutinario': ['EEG rutinario']
  };
  const PACK_FLAG_MAP_FUNC = {
    'Espirometría pre/post broncodilatador': ['pre_post_bronchodilator'],
    'PFR completo (Espirometría + Volúmenes + DLCO)': [],
    'PSG diagnóstica': ['with_sleep_technologist'],
    'HSAT (apnea del sueño en casa)': [],
    'EMG + VCN (estudio completo)': [],
    'EEG rutinario': []
  };
  const FUNC_SEARCH_CONFIG = {
    minChars: 2,
    maxResults: 12,
    boosts: {
      labelPrefix: 3,
      labelContains: 1,
      aliasPrefix: 5,
      aliasContains: 2
    }
  };
  const FUNC_SEARCH_META = {
    spirometry: { aliases: ['espirometria','spirometry','fev1','fvc','curva flujo volumen'] },
    dlco: { aliases: ['dlco','difusion','diffusing capacity'] },
    plethysmography: { aliases: ['pletismografia','volumenes pulmonares','body plethysmography'] },
    full_pft: { aliases: ['pfr completo','funcion pulmonar completa','full pft'] },
    bronchial_challenge: { aliases: ['reto bronquial','provocacion','metacolina','bronchial challenge'] },
    feno: { aliases: ['feno','oxido nitrico exhalado'] },
    six_min_walk: { aliases: ['6mwt','caminata 6 minutos'] },
    cpet: { aliases: ['cpet','esfuerzo cardiopulmonar','ergospirometria'] },
    overnight_oximetry: { aliases: ['oximetria nocturna','overnight oximetry'] },
    capnography: { aliases: ['capnografia','capnography','etco2'] },
    psg_diagnostic: { aliases: ['psg','polisomnografia','polysomnography'] },
    psg_titration: { aliases: ['titulacion','psg cpap','pap titration'] },
    hsat: { aliases: ['hsat','home sleep apnea test','apnea en casa'] },
    mslt: { aliases: ['mslt','narcolepsia'] },
    mwt: { aliases: ['mwt','vigilancia'] },
    eeg_routine: { aliases: ['eeg','electroencefalograma'] },
    eeg_sleep_deprived: { aliases: ['eeg privacion','sleep deprived eeg'] },
    video_eeg: { aliases: ['video eeg','monitorizacion eeg'] },
    emg_ncs: { aliases: ['emg','vcn','ncs'] },
    repetitive_nerve_stimulation: { aliases: ['estim repetitiva','miastenia'] },
    sfemg: { aliases: ['fibra unica','sfemg'] },
    evoked_visual: { aliases: ['pev','visual evoked'] },
    evoked_auditory_baep: { aliases: ['peat','baep','abr neurologico'] },
    evoked_ssep: { aliases: ['pess','ssep'] },
    audiometry_tonal: { aliases: ['audiometria','tonal'] },
    audiometry_speech: { aliases: ['logoaudiometria','verbal'] },
    tympanometry: { aliases: ['timpanometria','impedanciometria'] },
    otoacoustic_emissions: { aliases: ['oea','otoemisiones'] },
    abr: { aliases: ['abr','peat','baep'] },
    vng: { aliases: ['vng','videonistagmografia'] },
    vemp: { aliases: ['vemp'] },
    tilt_table: { aliases: ['tilt','mesa inclinada','sincope'] },
    holter_bp_24h: { aliases: ['mapa 24','presion 24h','abpm'] },
    ankle_brachial_index: { aliases: ['itb','abi','tobillo brazo'] }
  };
  const FUNC_PACKAGE_META = {
    'Espirometría pre/post broncodilatador': { aliases: ['espirometria pre post','pre post broncodilatador'] },
    'PFR completo (Espirometría + Volúmenes + DLCO)': { aliases: ['pfr completo','funcion pulmonar completa'] },
    'PSG diagnóstica': { aliases: ['psg','polisomnografia diagnostica'] },
    'HSAT (apnea del sueño en casa)': { aliases: ['hsat','apnea en casa'] },
    'EMG + VCN (estudio completo)': { aliases: ['emg vcn','ncs','conduccion nerviosa'] },
    'EEG rutinario': { aliases: ['eeg','electroencefalograma'] }
  };
  const PICK_MAP_IMG = {
    'RX Tórax': ['RX Tórax'],
    'US Abdomen': ['US Abdomen'],
    'TAC Tórax': ['TAC Tórax'],
    'RM Cerebro': ['RM Cerebro'],
    'Mamografía': ['Mamografía']
  };
  const PACK_MAP_IMG = {
    'Control trimestral imagen': ['RX Tórax','US Abdomen','RM Columna (segmento)'],
    'Preop imagen': ['RX Abdomen','US Obstétrico','TAC Abdomen y pelvis'],
    'Gabinete vascular': ['Doppler carotídeo','Doppler venoso MI (TVP)','Doppler arterial MI']
  };
  const PICK_MAP_CARDIO = {};
  const PACK_MAP_CARDIO = {};
  const PICK_MAP_ENDO = {};
  const PACK_MAP_ENDO = {};
  const PICK_MAP_PAT = {
    'Biopsia gastrointestinal': ['Biopsia gastrointestinal'],
    'Pieza quirúrgica (general)': ['Pieza quirúrgica (general)'],
    'PAAF / BACAF (aspiración con aguja fina)': ['PAAF / BACAF (aspiración con aguja fina)'],
    'Revisión de laminillas (segunda opinión)': ['Revisión de laminillas (segunda opinión)']
  };
  const PACK_MAP_PAT = {
    'Pieza oncológica completa (márgenes + IHQ si se requiere)': ['Pieza oncológica (resección)'],
    'Biopsia renal completa (incluye IF si aplica)': ['Biopsia renal'],
    'Transoperatorio (congelación)': ['Pieza quirúrgica (general)']
  };
  const PACK_FLAG_MAP_PAT = {
    'Pieza oncológica completa (márgenes + IHQ si se requiere)': ['margins'],
    'Biopsia renal completa (incluye IF si aplica)': ['immunofluorescence'],
    'Transoperatorio (congelación)': ['frozen_section']
  };
  const modalConfigs = [
    { key:'lab', id:'modalEstudiosLab', accordionId:'estLabAccordion', pickMap:PICK_MAP_LAB, packMap:PACK_MAP_LAB, packFlagMap:{} },
    { key:'imagenologia', id:'modalEstudiosImg', panelSelector:'[data-est-modality-panels]', pickMap:PICK_MAP_IMG, packMap:PACK_MAP_IMG, packFlagMap:{} },
    { key:'cardiologia', id:'modalEstudiosCardio', panelSelector:'[data-est-modality-panels]', pickMap:PICK_MAP_CARDIO, packMap:PACK_MAP_CARDIO, packFlagMap:{} },
    { key:'endoscopia', id:'modalEstudiosEndo', panelSelector:'[data-est-modality-panels]', pickMap:PICK_MAP_ENDO, packMap:PACK_MAP_ENDO, packFlagMap:{} },
    { key:'patologia', id:'modalEstudiosPat', panelSelector:'[data-est-modality-panels]', pickMap:PICK_MAP_PAT, packMap:PACK_MAP_PAT, packFlagMap:PACK_FLAG_MAP_PAT },
    { key:'genetica', id:'modalEstudiosGen', panelSelector:'[data-est-modality-panels]', pickMap:PICK_MAP_GEN, packMap:PACK_MAP_GEN, packFlagMap:PACK_FLAG_MAP_GEN },
    { key:'funcionales', id:'modalEstudiosFunc', panelSelector:'[data-est-modality-panels]', pickMap:PICK_MAP_FUNC, packMap:PACK_MAP_FUNC, packFlagMap:PACK_FLAG_MAP_FUNC }
  ];
  const flagLabels = {
    priority_routine: 'Rutinario',
    priority_urgent: 'Urgente',
    priority_stat: 'Prioridad (STAT)',
    second_opinion: 'Segunda opinión',
    clinicopath_correlation: 'Con correlación clínica',
    diagnostic: 'Diagnóstica',
    therapeutic: 'Terapéutica',
    with_contrast: 'Con contraste',
    without_contrast: 'Sin contraste',
    right: 'Derecha',
    left: 'Izquierda',
    bilateral: 'Bilateral',
    with_biopsy: 'Con biopsia',
    with_polypectomy: 'Con polipectomía',
    with_hemostasis: 'Hemostasia',
    with_dilation: 'Con dilatación',
    with_stent: 'Colocación de stent',
    with_foreign_body: 'Extracción de cuerpo extraño',
    with_variceal_band: 'Ligadura de várices',
    with_injection_therapy: 'Terapia de inyección',
    with_clips: 'Clips / endoclips',
    with_tattoo: 'Tatuaje endoscópico',
    with_chromoendoscopy: 'Cromoendoscopia',
    with_image_enhancement: 'Realce de imagen (NBI/i-Scan)',
    with_report: 'Con interpretación',
    without_report: 'Sin interpretación',
    resting: 'En reposo',
    followup: 'Control / seguimiento',
    fasting: 'Ayuno',
    serial: 'Seriado',
    first_morning: 'Primera muestra de la mañana',
    with_antibiogram: 'Con antibiograma',
    laterality_right: 'Derecha',
    laterality_left: 'Izquierda',
    laterality_bilateral: 'Bilateral',
    contrast_with: 'Con contraste',
    contrast_without: 'Sin contraste',
    contrast_with_without: 'Con y sin contraste',
    rx_pa: 'PA',
    rx_ap: 'AP',
    rx_lateral: 'Lateral',
    rx_pa_lateral: 'PA + lateral',
    rx_oblique: 'Oblicuas',
    rx_portable: 'Portátil',
    rx_weight_bearing: 'Con carga',
    rx_two_views: '2 vistas',
    us_doppler: 'Con Doppler',
    us_color_doppler: 'Doppler color',
    us_arterial: 'Doppler arterial',
    us_venous: 'Doppler venoso',
    us_transvaginal: 'Transvaginal',
    us_transrectal: 'Transrectal',
    us_obstetric: 'Obstétrico',
    ct_angio: 'Angio-TAC',
    ct_phase_arterial: 'Fase arterial',
    ct_phase_venous: 'Fase venosa',
    ct_phase_delayed: 'Fase tardía',
    mr_angio: 'Angio-RM',
    mr_diffusion: 'Difusión (DWI)',
    mammo_screening: 'Tamizaje',
    mammo_diagnostic: 'Diagnóstica',
    mammo_tomo: 'Con tomosíntesis',
    vascular_color_doppler: 'Doppler color',
    vascular_spectral: 'Doppler espectral',
    vascular_arterial: 'Arterial',
    vascular_venous: 'Venoso',
    nm_with_ct: 'Con CT (atenuación)',
    pet_fdg: 'FDG',
    dexa_spine: 'Columna',
    dexa_hip: 'Cadera',
    dexa_whole_body: 'Cuerpo completo',
    with_color_doppler: 'Con Doppler color',
    with_tissue_doppler: 'Con Doppler tisular',
    with_strain: 'Con Strain/GLS',
    bubble_study: 'Estudio con burbujas (shunt)',
    margin_assessment: 'Evaluación de márgenes',
    oriented_margins: 'Márgenes orientados',
    decalcification: 'Con decalcificación',
    add_ihc: 'Agregar IHQ/IHC',
    add_special_stains: 'Agregar tinciones especiales',
    frozen_section: 'Transoperatorio / congelación',
    margins: 'Evaluación de márgenes',
    ihc: 'Con inmunohistoquímica (IHQ)',
    special_stains: 'Con tinciones especiales',
    ihc_single_marker: 'IHQ: marcador único',
    ihc_panel: 'IHQ: panel',
    ihc_breast_panel: 'IHQ: mama (ER/PR/HER2 ± Ki-67)',
    ihc_lung_panel: 'IHQ: pulmón (TTF-1/Napsina/P40)',
    ihc_lymphoma_panel: 'IHQ: linfoma (básico)',
    stain_pas: 'Tinción PAS',
    stain_zn: 'Ziehl-Neelsen (BAAR)',
    stain_gms: 'GMS (hongos)',
    stain_trichrome: 'Tricrómico (Masson)',
    stain_congo: 'Rojo Congo (amiloide)',
    stain_gram: 'Gram',
    intraoperative: 'Transoperatorio',
    with_bal: 'Con lavado broncoalveolar (BAL)',
    with_tbna: 'Con TBNA (aspiración)',
    with_ebus: 'Con EBUS',
    pre_post_bronchodilator: 'Pre / post broncodilatador',
    with_bronchial_challenge: 'Con reto bronquial (provocación)',
    with_exercise: 'Con ejercicio',
    with_sleep_technologist: 'Con técnico (atendido)',
    with_titration: 'Con titulación (CPAP/BiPAP)',
    upper_limbs: 'Miembros superiores',
    lower_limbs: 'Miembros inferiores',
    immunofluorescence: 'Con inmunofluorescencia',
    external_review: 'Revisión externa / segunda opinión',
    block_additional: 'Cortes/bloques adicionales',
    requires_consent: 'Requiere consentimiento informado',
    genetic_counseling: 'Consejería genética (si aplica)',
    context_prenatal: 'Prenatal',
    context_postnatal: 'Postnatal',
    sample_blood: 'Muestra: sangre (EDTA)',
    sample_saliva: 'Muestra: saliva',
    sample_tissue: 'Muestra: tejido fresco',
    sample_ffpe: 'Muestra: bloque/laminilla (FFPE)',
    sample_cvs: 'Muestra: CVS (vellosidades coriónicas)',
    sample_amnio: 'Muestra: amniocentesis',
    trio: 'TRIO (paciente+padre+madre)',
    duo: 'DUO (paciente+1 progenitor)',
    proband_only: 'Solo paciente',
    include_cnv: 'Incluir CNV',
    confirm_sanger: 'Confirmación por Sanger',
    acmg_secondary_findings: 'Consentimiento: hallazgos secundarios (ACMG SF)',
    cascade_testing: 'Familiar (cascade testing)',
    report_reanalysis: 'Reanálisis (si aplica)',
    with_sedation: 'Con sedación',
    without_sedation: 'Sin sedación',
    anesthesia: 'Con anestesia',
    with_doppler: 'Con Doppler',
    rest: 'Reposo',
    stress: 'Esfuerzo',
    adult: 'Adulto',
    pediatric: 'Pediátrico',
    ambulatory: 'Ambulatorio',
    in_hospital: 'Hospitalizado',
    holter_24h: '24 horas',
    holter_48h: '48 horas',
    holter_72h: '72 horas',
    holter_7d: '7 días',
    event_monitor: 'Monitor de eventos',
    exercise_treadmill: 'Banda (treadmill)',
    exercise_bike: 'Bicicleta',
    stress_exercise: 'Estrés por ejercicio',
    stress_pharmacologic: 'Estrés farmacológico',
    pharm_dobutamine: 'Dobutamina',
    pharm_dipyridamole: 'Dipiridamol',
    pharm_adenosine: 'Adenosina',
    mapa_24h: 'MAPA 24h',
    mapa_48h: 'MAPA 48h',
    urgent: 'Urgente'
  };
  const endoscopyFlagOrder = [
    'diagnostic',
    'therapeutic',
    'with_biopsy',
    'with_polypectomy',
    'with_hemostasis',
    'with_dilation',
    'with_stent',
    'with_foreign_body',
    'with_variceal_band',
    'with_injection_therapy',
    'with_clips',
    'with_tattoo',
    'with_chromoendoscopy',
    'with_image_enhancement',
    'with_bal',
    'with_tbna',
    'with_ebus',
    'with_sedation',
    'without_sedation',
    'anesthesia',
    'urgent',
    'followup',
    'adult',
    'pediatric'
  ];
  const pathologyFlagOrder = [
    'urgent',
    'followup',
    'frozen_section',
    'margins',
    'ihc',
    'special_stains',
    'decalcification',
    'immunofluorescence',
    'external_review',
    'block_additional'
  ];
  const cardiologyFlagOrder = [
    'priority_routine',
    'priority_urgent',
    'priority_stat',
    'adult',
    'pediatric',
    'with_report',
    'without_report',
    'resting',
    'holter_24h',
    'holter_48h',
    'holter_72h',
    'holter_7d',
    'event_monitor',
    'with_doppler',
    'with_color_doppler',
    'with_tissue_doppler',
    'with_strain',
    'with_contrast',
    'bubble_study',
    'exercise_treadmill',
    'exercise_bike',
    'stress_exercise',
    'stress_pharmacologic',
    'pharm_dobutamine',
    'pharm_dipyridamole',
    'pharm_adenosine',
    'with_sedation',
    'without_sedation',
    'anesthesia',
    'followup'
  ];
  const imagingFlagOrder = [
    'priority_urgent',
    'followup',
    'laterality_right',
    'laterality_left',
    'laterality_bilateral',
    'rx_two_views',
    'rx_pa',
    'rx_ap',
    'rx_lateral',
    'rx_pa_lateral',
    'rx_oblique',
    'rx_portable',
    'rx_weight_bearing',
    'contrast_with_without',
    'contrast_with',
    'contrast_without',
    'ct_angio',
    'ct_phase_arterial',
    'ct_phase_venous',
    'ct_phase_delayed',
    'us_transvaginal',
    'us_transrectal',
    'us_obstetric',
    'mr_angio',
    'mr_diffusion',
    'mammo_screening',
    'mammo_diagnostic',
    'mammo_tomo',
    'vascular_color_doppler',
    'vascular_spectral',
    'vascular_arterial',
    'vascular_venous',
    'nm_with_ct',
    'pet_fdg',
    'dexa_spine',
    'dexa_hip',
    'dexa_whole_body'
  ];
  const labFlagOrder = [
    'fasting',
    'urgent',
    'followup',
    'serial',
    'first_morning',
    'with_antibiogram'
  ];
  const geneticsFlagOrder = [
    'urgent',
    'followup',
    'requires_consent',
    'genetic_counseling',
    'context_prenatal',
    'context_postnatal',
    'sample_blood',
    'sample_saliva',
    'sample_tissue',
    'sample_ffpe',
    'sample_cvs',
    'sample_amnio',
    'trio',
    'duo',
    'proband_only',
    'include_cnv',
    'confirm_sanger',
    'acmg_secondary_findings',
    'cascade_testing',
    'report_reanalysis'
  ];
  const functionalFlagOrder = [
    'urgent',
    'followup',
    'pre_post_bronchodilator',
    'with_bronchial_challenge',
    'with_exercise',
    'with_sleep_technologist',
    'with_titration',
    'left',
    'right',
    'bilateral',
    'upper_limbs',
    'lower_limbs'
  ];
  const LAB_FLAG_VISIBILITY = {
    generalFlags: [
      'fasting',
      'urgent',
      'followup'
    ],
    applicability: {
      glucose: ['fasting','serial'],
      ogtt: ['fasting','serial'],
      urinalysis: ['first_morning'],
      microalbumin: ['first_morning'],
      urine_culture: ['with_antibiogram'],
      blood_culture: ['with_antibiogram'],
      stool_culture: ['with_antibiogram'],
      throat_swab: ['with_antibiogram'],
      vaginal_swab: ['with_antibiogram']
    }
  };
  const PATHOLOGY_FLAG_VISIBILITY = {
    generalFlags: [
      'urgent',
      'followup'
    ],
    applicability: {
      bx_soft_tissue: ['ihc','special_stains','block_additional'],
      bx_gi: ['ihc','special_stains','block_additional'],
      bx_skin: ['ihc','special_stains','block_additional'],
      bx_breast: ['ihc','special_stains','block_additional'],
      bx_gyn: ['ihc','special_stains','block_additional'],
      bx_urology: ['ihc','special_stains','block_additional'],
      bx_renal: ['immunofluorescence','ihc','special_stains','block_additional'],
      bx_bone: ['decalcification','ihc','special_stains','block_additional'],
      surg_specimen_general: ['margins','frozen_section','ihc','special_stains','block_additional'],
      surg_specimen_onco: ['margins','frozen_section','ihc','special_stains','block_additional'],
      amputation: ['margins','ihc','special_stains','block_additional'],
      slides_review: ['external_review'],
      paraffin_block_review: ['external_review','block_additional'],
      cyto_fna: ['ihc','special_stains','block_additional'],
      cyto_fluids: ['ihc','special_stains','block_additional'],
      cyto_pap: ['followup'],
      cyto_liquid_based: ['followup'],
      autopsy_clinical: ['urgent'],
      autopsy_fetal: ['urgent']
    }
  };
  const GENETICS_FLAG_VISIBILITY = {
    generalFlags: [
      'urgent',
      'followup'
    ],
    applicability: {
      karyotype: [
        'requires_consent',
        'genetic_counseling',
        'context_prenatal',
        'context_postnatal',
        'sample_blood',
        'sample_cvs',
        'sample_amnio',
        'cascade_testing'
      ],
      cma_microarray: [
        'requires_consent',
        'genetic_counseling',
        'context_prenatal',
        'context_postnatal',
        'sample_blood',
        'sample_cvs',
        'sample_amnio',
        'cascade_testing'
      ],
      targeted_variant: [
        'requires_consent',
        'genetic_counseling',
        'context_postnatal',
        'sample_blood',
        'sample_saliva',
        'sample_tissue',
        'cascade_testing',
        'confirm_sanger'
      ],
      ngs_panel: [
        'requires_consent',
        'genetic_counseling',
        'context_postnatal',
        'sample_blood',
        'sample_saliva',
        'sample_tissue',
        'include_cnv',
        'confirm_sanger',
        'cascade_testing'
      ],
      wes: [
        'requires_consent',
        'genetic_counseling',
        'context_postnatal',
        'sample_blood',
        'sample_saliva',
        'sample_tissue',
        'trio',
        'duo',
        'proband_only',
        'include_cnv',
        'acmg_secondary_findings',
        'report_reanalysis'
      ],
      wgs: [
        'requires_consent',
        'genetic_counseling',
        'context_postnatal',
        'sample_blood',
        'sample_saliva',
        'sample_tissue',
        'trio',
        'duo',
        'proband_only',
        'include_cnv',
        'acmg_secondary_findings',
        'report_reanalysis'
      ],
      nipt: [
        'requires_consent',
        'genetic_counseling',
        'context_prenatal',
        'sample_blood'
      ],
      carrier_screening: [
        'requires_consent',
        'genetic_counseling',
        'context_prenatal',
        'context_postnatal',
        'sample_blood',
        'sample_saliva'
      ],
      hereditary_cancer_germline: [
        'requires_consent',
        'genetic_counseling',
        'context_postnatal',
        'sample_blood',
        'sample_saliva',
        'include_cnv',
        'cascade_testing',
        'confirm_sanger'
      ],
      brca1_2: [
        'requires_consent',
        'genetic_counseling',
        'context_postnatal',
        'sample_blood',
        'sample_saliva',
        'include_cnv',
        'cascade_testing',
        'confirm_sanger'
      ],
      lynch: [
        'requires_consent',
        'genetic_counseling',
        'context_postnatal',
        'sample_blood',
        'sample_saliva',
        'include_cnv',
        'cascade_testing',
        'confirm_sanger'
      ],
      thrombophilia: [
        'requires_consent',
        'context_postnatal',
        'sample_blood',
        'cascade_testing',
        'confirm_sanger'
      ],
      pgx: [
        'requires_consent',
        'context_postnatal',
        'sample_blood',
        'sample_saliva'
      ],
      somatic_tumor_ngs: [
        'requires_consent',
        'context_postnatal',
        'sample_ffpe',
        'sample_tissue'
      ]
    }
  };
  const FUNCTIONAL_FLAG_VISIBILITY = {
    generalFlags: [
      'urgent',
      'followup'
    ],
    applicability: {
      spirometry: ['pre_post_bronchodilator'],
      full_pft: ['pre_post_bronchodilator'],
      dlco: [],
      plethysmography: [],
      feno: [],
      six_min_walk: ['with_exercise'],
      cpet: ['with_exercise'],
      bronchial_challenge: ['with_bronchial_challenge'],
      overnight_oximetry: [],
      capnography: [],
      psg_diagnostic: ['with_sleep_technologist'],
      psg_titration: ['with_sleep_technologist','with_titration'],
      hsat: [],
      mslt: ['with_sleep_technologist'],
      mwt: ['with_sleep_technologist'],
      eeg_routine: [],
      eeg_sleep_deprived: [],
      video_eeg: ['with_sleep_technologist'],
      emg_ncs: ['upper_limbs','lower_limbs'],
      repetitive_nerve_stimulation: [],
      sfemg: [],
      evoked_visual: [],
      evoked_auditory_baep: [],
      evoked_ssep: [],
      audiometry_tonal: ['left','right','bilateral'],
      audiometry_speech: ['left','right','bilateral'],
      tympanometry: ['left','right','bilateral'],
      otoacoustic_emissions: ['left','right','bilateral'],
      abr: ['left','right','bilateral'],
      vng: [],
      vemp: [],
      tilt_table: [],
      holter_bp_24h: [],
      ankle_brachial_index: ['left','right','bilateral']
    }
  };
  const CARDIOLOGY_FLAG_VISIBILITY = {
    generalFlags: [
      'priority_routine',
      'priority_urgent',
      'priority_stat',
      'adult',
      'pediatric',
      'with_report',
      'without_report',
      'followup'
    ],
    applicability: {
      ecg_12lead: ['resting'],
      ecg_rhythm_strip: ['resting'],
      holter: ['holter_24h','holter_48h','holter_72h','holter_7d','event_monitor'],
      abpm_mapa: [],
      echo_tte: ['with_doppler','with_color_doppler','with_tissue_doppler','with_contrast','with_strain','bubble_study'],
      echo_tes: ['with_doppler','with_color_doppler','with_contrast','bubble_study','with_sedation','without_sedation','anesthesia'],
      stress_test: ['exercise_treadmill','exercise_bike'],
      stress_echo: [
        'stress_exercise',
        'stress_pharmacologic',
        'exercise_treadmill',
        'exercise_bike',
        'pharm_dobutamine',
        'pharm_dipyridamole',
        'pharm_adenosine',
        'with_doppler',
        'with_contrast'
      ],
      tilt_table: [],
      carotid_doppler: [],
      lower_ext_art_doppler: [],
      lower_ext_venous_doppler: []
    }
  };
  const IMAGING_FLAG_VISIBILITY = {
    generalFlags: [
      'priority_urgent',
      'followup',
      'laterality_right',
      'laterality_left',
      'laterality_bilateral'
    ],
    applicability: {
      rx_chest: ['rx_pa','rx_ap','rx_lateral','rx_pa_lateral','rx_two_views','rx_portable'],
      rx_abdomen: ['rx_ap','rx_lateral','rx_two_views'],
      rx_pelvis: ['rx_ap'],
      rx_cspine: ['rx_ap','rx_lateral','rx_oblique','rx_two_views'],
      rx_lspine: ['rx_ap','rx_lateral','rx_oblique','rx_two_views'],
      rx_shoulder: ['rx_ap','rx_lateral','rx_oblique','rx_two_views'],
      rx_knee: ['rx_ap','rx_lateral','rx_two_views','rx_weight_bearing'],
      rx_ankle: ['rx_ap','rx_lateral','rx_oblique','rx_two_views'],
      rx_hand: ['rx_ap','rx_oblique','rx_two_views'],
      us_abdomen: [],
      us_pelvic: ['us_transvaginal','us_transrectal'],
      us_obstetric_study: ['us_obstetric'],
      us_thyroid: [],
      us_soft_tissue: [],
      us_testicular: [],
      us_renal: [],
      breast_us: [],
      ct_head: ['contrast_with','contrast_without','contrast_with_without'],
      ct_chest: ['contrast_with','contrast_without','contrast_with_without','ct_angio'],
      ct_abdomen_pelvis: ['contrast_with','contrast_without','contrast_with_without','ct_phase_arterial','ct_phase_venous','ct_phase_delayed'],
      ct_uro: ['contrast_with','contrast_without','contrast_with_without','ct_phase_venous','ct_phase_delayed'],
      ct_spine: ['contrast_with','contrast_without','contrast_with_without'],
      ct_extremity: ['contrast_with','contrast_without','contrast_with_without'],
      mr_brain: ['contrast_with','contrast_without','contrast_with_without','mr_diffusion'],
      mr_spine: ['contrast_with','contrast_without','contrast_with_without'],
      mr_knee: ['contrast_with','contrast_without','contrast_with_without'],
      mr_shoulder: ['contrast_with','contrast_without','contrast_with_without'],
      mr_abdomen: ['contrast_with','contrast_without','contrast_with_without'],
      mr_angio_study: ['mr_angio','contrast_with'],
      mammo: ['mammo_screening','mammo_diagnostic','mammo_tomo'],
      vasc_carotid: ['vascular_color_doppler','vascular_spectral'],
      vasc_venous_leg: ['vascular_color_doppler','vascular_spectral','vascular_venous'],
      vasc_arterial_leg: ['vascular_color_doppler','vascular_spectral','vascular_arterial'],
      vasc_upper_ext: ['vascular_color_doppler','vascular_spectral','vascular_arterial','vascular_venous'],
      vasc_abdomen: ['vascular_color_doppler','vascular_spectral','vascular_arterial','vascular_venous'],
      nm_bone_scan: ['nm_with_ct'],
      nm_thyroid_uptake: [],
      pet_ct: ['pet_fdg'],
      dexa: ['dexa_spine','dexa_hip','dexa_whole_body'],
      fluoro_hsg: [],
      fluoro_vcug: [],
      fluoro_ugi: [],
      fluoro_barium_enema: []
    }
  };
  const ENDOSCOPY_FLAG_VISIBILITY = {
    generalFlags: [
      'diagnostic',
      'therapeutic',
      'with_sedation',
      'without_sedation',
      'anesthesia',
      'urgent',
      'followup',
      'adult',
      'pediatric'
    ],
    applicability: {
      egd_eda_base: [
        'with_biopsy',
        'with_hemostasis',
        'with_dilation',
        'with_stent',
        'with_foreign_body',
        'with_variceal_band',
        'with_injection_therapy',
        'with_clips',
        'with_chromoendoscopy',
        'with_image_enhancement'
      ],
      colonoscopy_base: [
        'with_biopsy',
        'with_polypectomy',
        'with_hemostasis',
        'with_dilation',
        'with_stent',
        'with_foreign_body',
        'with_clips',
        'with_tattoo',
        'with_chromoendoscopy',
        'with_image_enhancement'
      ],
      flex_sig_base: [
        'with_biopsy',
        'with_polypectomy',
        'with_hemostasis',
        'with_dilation',
        'with_stent',
        'with_foreign_body',
        'with_clips',
        'with_tattoo',
        'with_chromoendoscopy',
        'with_image_enhancement'
      ],
      anoscopy_base: [
        'with_biopsy',
        'with_hemostasis'
      ],
      proctoscopy_base: [
        'with_biopsy',
        'with_hemostasis'
      ],
      ercp_cpre_base: [
        'with_dilation',
        'with_stent'
      ],
      eus_use_base: [
        'with_biopsy'
      ],
      capsule_base: [],
      enteroscopy_base: [
        'with_biopsy',
        'with_hemostasis',
        'with_dilation',
        'with_stent',
        'with_foreign_body',
        'with_clips',
        'with_tattoo',
        'with_image_enhancement'
      ],
      bronchoscopy_base: [
        'with_biopsy',
        'with_bal',
        'with_tbna',
        'with_foreign_body',
        'with_hemostasis',
        'with_stent'
      ],
      ebus_base: [
        'with_tbna',
        'with_biopsy',
        'with_bal'
      ],
      laryngoscopy_base: [
        'with_biopsy'
      ],
      pleuroscopy_base: [
        'with_biopsy',
        'with_hemostasis'
      ]
    }
  };
  const activeFlags = new Set();
  const itemMetaMap = {};
  const modalityLabelMap = {};
  const rawDelimiter = '|';
  const allCheckboxes = Array.from(document.querySelectorAll('input[type="checkbox"][data-est-item]'));
  const summaryWrap = document.querySelector('[data-est-summary]');
  const summaryCount = document.querySelector('[data-est-count]');
  const summaryEdit = document.querySelector('[data-est-edit]');
  const summaryClear = document.querySelector('[data-est-clear]');
  const summaryContainer = summaryWrap?.closest('.est-summary');
  const orderBlock = document.querySelector('[data-est-order-block]');
  const orderList = document.querySelector('.est-orders-list');
  const areaSelect = orderBlock?.querySelector('[data-est-area-select]');
  const prioritySelect = orderBlock?.querySelector('[data-role="ac-order-priority"]');
  const indicationTextarea = orderBlock?.querySelector('[data-role="ac-order-indication"]');
  const orderFeedbackEl = orderBlock?.querySelector('[data-role="ac-order-feedback"]');
  const openInputs = Array.from(document.querySelectorAll('[data-est-open-modal]'));
  if(!openInputs.length) return;

  const controllers = [];
  const controllerMap = {};
  let selectionOrder = [];
  let activeInput = null;
  let activeController = null;
  let canonicalOrderSubmitting = false;
  const lastCreatedOrderRef = { id: '', uuid: '' };
  const orderSubmitLock = (window.__mxmedOrderSubmitLock = window.__mxmedOrderSubmitLock || { signature: '', ts: 0 });
  const orderReplacementState = {
    active: false,
    sourceRef: '',
    sourceId: '',
    sourceUuid: '',
    reason: ''
  };
  let modalMode = 'add';
  let modalModeController = null;
  const groupLabels = {};
  const itemGroupMap = {};
  const itemOrder = {};
  const groupOrder = [];
  let orderIndex = 0;
  const searchStates = {};
  const searchConfigMap = {
    lab: {
      config: LAB_SEARCH_CONFIG,
      meta: LAB_SEARCH_META,
      packMeta: LAB_PACKAGE_META,
      categoryMode: 'group'
    },
    genetica: {
      config: GENETICS_SEARCH_CONFIG,
      meta: GENETICS_SEARCH_META,
      packMeta: GENETICS_PACKAGE_META,
      categoryMode: 'modality'
    },
    funcionales: {
      config: FUNC_SEARCH_CONFIG,
      meta: FUNC_SEARCH_META,
      packMeta: FUNC_PACKAGE_META,
      categoryMode: 'modality'
    }
  };

  function escapeHtml(str){
    return String(str).replace(/[&<>"']/g, s=>({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[s]));
  }
  function clean(value){
    return String(value || '').trim();
  }
  function setOrderFeedback(message, tone = 'muted'){
    if(!orderFeedbackEl) return;
    const text = clean(message);
    orderFeedbackEl.classList.remove('d-none', 'text-muted', 'text-success', 'text-danger');
    if(!text){
      orderFeedbackEl.classList.add('d-none');
      orderFeedbackEl.textContent = '';
      return;
    }
    orderFeedbackEl.classList.add(
      tone === 'success' ? 'text-success' : (tone === 'error' ? 'text-danger' : 'text-muted')
    );
    orderFeedbackEl.textContent = text;
  }
  function nowSqlDateTime(){
    const d = new Date();
    const pad = (n)=> String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
  }
  function prettyDate(value){
    const raw = clean(value);
    const dt = raw ? new Date(raw.replace(' ', 'T')) : new Date();
    if(Number.isNaN(dt.getTime())) return raw || '';
    const months = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    return `${String(dt.getDate()).padStart(2, '0')} ${months[dt.getMonth()]} ${dt.getFullYear()}`;
  }
  function resolveOrderPatientId(){
    const isCanonicalPatientId = (value)=> /^p_[a-z0-9]+$/i.test(clean(value));
    const fromResolver = (typeof window.resolveActivePatientId === 'function') ? clean(window.resolveActivePatientId()) : '';
    if(isCanonicalPatientId(fromResolver)) return fromResolver;
    const fromStore = clean(window.mxmedStore?.currentPatientId || window.mxmedStore?.activePatientId);
    if(isCanonicalPatientId(fromStore)) return fromStore;
    const pane = document.getElementById('p-expediente');
    const fromPane = clean(pane?.dataset?.patientId || pane?.getAttribute?.('data-patient-id'));
    if(isCanonicalPatientId(fromPane)) return fromPane;
    return '';
  }
  function resolveOrderEncounterKey(){
    if(typeof window.getActiveEncounterKey === 'function'){
      const fromGetter = clean(window.getActiveEncounterKey());
      if(fromGetter) return fromGetter;
    }
    return clean(window.mxmedStore?.currentEncounterKey || window.mxmedStore?.activeEncounterKey);
  }
  function resolveOrderAppointmentId(patientId, encounterKey){
    const bar = document.getElementById('mm-p10-bar');
    const fromBar = clean(bar?.dataset?.appointmentId || bar?.getAttribute?.('data-appointment-id'));
    if(fromBar) return fromBar;
    const encounters = window.mxmedStore?.activeEncounters;
    if(encounterKey && encounters && typeof encounters === 'object'){
      const entry = encounters[encounterKey];
      if(entry && clean(entry.patient_id) === patientId){
        return clean(entry.appointment_id || entry.appointmentId);
      }
    }
    return '';
  }
  function resolveClinicalActorUserId(){
    const candidates = [
      window.mxmedUser?.user_id,
      window.mxmedAuth?.user_id,
      window.mxmedStore?.currentUserId,
      window.mxmedStore?.userId,
      document.body?.dataset?.userId
    ];
    for(const value of candidates){
      const safe = clean(value);
      if(safe) return safe;
    }
    return 'u_demo_01';
  }
  function resolveOrderDocumentType(controllerKey, area){
    const key = clean(controllerKey).toLowerCase();
    const label = clean(area).toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    if(key === 'lab' || label === 'laboratorio') return 'lab_order';
    if(key === 'imagenologia' || label === 'imagenologia') return 'imaging_order';
    return '';
  }
  function resolveClinicalDocumentIcon(documentType){
    const type = clean(documentType).toLowerCase();
    const map = {
      lab_order: 'science',
      lab_result: 'science',
      imaging_order: 'radiology',
      imaging_result: 'radiology',
      result: 'monitoring',
      prescription: 'medication',
      recipe: 'medication',
      rx: 'medication',
      receta: 'medication',
      clinical_note: 'description',
      note: 'description',
      nota_evolucion: 'description',
      medical_note: 'description',
      evolution_note: 'description',
      consent_document: 'fact_check',
      consentimiento_informado: 'fact_check'
    };
    return map[type] || '';
  }
  window.resolveClinicalDocumentIcon = resolveClinicalDocumentIcon;
  const CLINICAL_DIAGNOSTIC_SVG_ICON_MAP = Object.freeze({
    lab_order: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 4v7l-4 7a2 2 0 0 0 1.8 3h8.4A2 2 0 0 0 18 18l-4-7V4"/><path d="M8 4h8"/><path d="M9 14h6"/></svg>',
    lab_result: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 4v7l-4 7a2 2 0 0 0 1.8 3h8.4A2 2 0 0 0 18 18l-4-7V4"/><path d="M8 4h8"/><path d="M9 14h6"/></svg>',
    result: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 4h10v6l-3 3v5a2 2 0 0 1-4 0v-5L7 10V4z"/><path d="M9 4v2"/><path d="M15 4v2"/></svg>'
  });
  function resolveEstudiosTabImagingIconMarkup(){
    const tab = document.querySelector('#p-expediente [data-tab-key="t-estudios"] .tab-ico')
      || document.querySelector('[data-tab-key="t-estudios"] .tab-ico');
    if(tab){
      return tab.outerHTML;
    }
    return '<span class="tab-ico material-symbols-outlined" aria-hidden="true">radiology</span>';
  }
  function resolveClinicalDocumentSvgIcon(documentType, area = ''){
    const type = clean(documentType).toLowerCase();
    if(type === 'imaging_order' || type === 'imaging_result'){
      return resolveEstudiosTabImagingIconMarkup();
    }
    if(type && CLINICAL_DIAGNOSTIC_SVG_ICON_MAP[type]){
      return CLINICAL_DIAGNOSTIC_SVG_ICON_MAP[type];
    }
    const label = clean(area).toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    if(label === 'laboratorio') return CLINICAL_DIAGNOSTIC_SVG_ICON_MAP.lab_order;
    if(label === 'imagenologia') return resolveEstudiosTabImagingIconMarkup();
    return CLINICAL_DIAGNOSTIC_SVG_ICON_MAP.result;
  }
  window.resolveClinicalDocumentSvgIcon = resolveClinicalDocumentSvgIcon;
  function inferOrderColor(area){
    const label = clean(area).toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    if(label === 'laboratorio') return 'est-order--lab';
    return 'est-order--img';
  }
  function isCanonicalOrderCard(card){
    if(!card) return false;
    const hasUuid = clean(card.getAttribute('data-document-uuid') || card.getAttribute('data-est-document-uuid'));
    const hasId = clean(card.getAttribute('data-document-id') || card.getAttribute('data-est-document-id'));
    return hasUuid !== '' || hasId !== '' || card.getAttribute('data-est-readonly') === '1';
  }
  function formatOrderActionLabel(card){
    return isCanonicalOrderCard(card) ? 'Ver' : 'Ver/Editar';
  }
  function traceOrder(eventName, payload){
    try{
      console.info(`[DOC-ORD-1A] ${eventName}`, payload);
    }catch(_){}
  }
  const orderPayloadCache = new Map();
  const orderPayloadFetchInFlight = new Map();
  const orderDocumentDetailCache = new Map();
  const orderDocumentDetailFetchInFlight = new Map();
  function parseMaybeJson(value){
    if(value == null) return null;
    if(typeof value === 'object') return value;
    const raw = clean(value);
    if(!raw) return null;
    try{
      return JSON.parse(raw);
    }catch(_){
      return null;
    }
  }
  function normalizePreviewList(values){
    const out = [];
    const seen = new Set();
    (Array.isArray(values) ? values : []).forEach((value)=>{
      const item = clean(value);
      if(!item) return;
      const key = item.toLowerCase();
      if(seen.has(key)) return;
      seen.add(key);
      out.push(item);
    });
    return out;
  }
  function extractNamedList(values){
    if(!Array.isArray(values)) return [];
    const out = [];
    values.forEach((entry)=>{
      if(typeof entry === 'string'){
        out.push(clean(entry));
        return;
      }
      if(!entry || typeof entry !== 'object') return;
      out.push(clean(
        entry.label
        || entry.name
        || entry.title
        || entry.text
        || entry.study_name
        || entry.item_name
        || entry.preset_name
        || entry.profile_name
      ));
    });
    return normalizePreviewList(out);
  }
  function extractPresetNamesFromPayload(payload){
    if(!payload || typeof payload !== 'object') return [];
    const keys = [
      'requested_packages',
      'packages',
      'package_names',
      'requested_profiles',
      'profiles',
      'profile_names',
      'requested_presets',
      'presets',
      'preset_names',
      'requested_panels',
      'panels',
      'panel_names'
    ];
    const names = [];
    keys.forEach((key)=>{
      names.push(...extractNamedList(payload[key]));
    });
    return normalizePreviewList(names);
  }
  function extractDiagnosticItemsFromPayload(payload){
    if(!payload || typeof payload !== 'object') return [];
    const keys = [
      'requested_studies',
      'requested_items',
      'selected_studies',
      'selected_items',
      'studies',
      'items',
      'tests'
    ];
    const names = [];
    keys.forEach((key)=>{
      names.push(...extractNamedList(payload[key]));
    });
    return normalizePreviewList(names);
  }
  function resolveDiagnosticTypeLabel(docType){
    const type = clean(docType).toLowerCase();
    if(type === 'lab_order') return 'Orden de laboratorio';
    if(type === 'imaging_order') return 'Orden de imagen';
    if(type === 'lab_result') return 'Resultado de laboratorio';
    if(type === 'imaging_result') return 'Resultado de imagen';
    return 'Orden diagnóstica';
  }
  function resolveDiagnosticFamilyTitle(docType){
    const type = clean(docType).toLowerCase();
    if(type === 'lab_order' || type === 'lab_result' || type === 'lab_pdf') return 'Estudio de laboratorio';
    if(type === 'imaging_order' || type === 'imaging_result') return 'Estudio de imagen';
    return 'Estudio diagnóstico';
  }
  function buildDiagnosticOrderPreview(row, payload){
    const docType = clean(row?.document_type).toLowerCase();
    const displayTitle = resolveDiagnosticTypeLabel(docType);
    const summaryText = clean(row?.summary);
    const dateText = prettyDate(clean(row?.event_datetime) || nowSqlDateTime()) || '';
    const presets = extractPresetNamesFromPayload(payload);
    const items = extractDiagnosticItemsFromPayload(payload);
    let studiesPreview = '';
    let studiesComplement = '';
    if(presets.length){
      const visible = presets.slice(0, 2);
      studiesPreview = visible.join(' · ');
      const extra = presets.length - visible.length;
      if(extra > 0){
        studiesComplement = `y ${extra} estudios más`;
      }
    }else if(items.length){
      const visible = items.slice(0, 3);
      studiesPreview = visible.join(', ');
      const extra = items.length - visible.length;
      if(extra > 0){
        studiesComplement = `y ${extra} estudios más`;
      }
    }
    return {
      displayTitle,
      summary: summaryText || '',
      studiesPreview: studiesPreview || '',
      studiesComplement: studiesComplement || '',
      metaText: dateText || ''
    };
  }
  function resolveOrderAreaLabel(docType, payload){
    const payloadArea = clean(
      payload?.order_area
      || payload?.area
      || payload?.context?.order_area
      || payload?.context?.area
    );
    if(payloadArea) return payloadArea;
    const type = clean(docType).toLowerCase();
    if(type === 'lab_order') return 'Laboratorio';
    if(type === 'imaging_order') return 'Imagenología';
    return 'Diagnóstico';
  }
  function resolveOrderPriorityLabel(payload){
    const direct = clean(payload?.priority || payload?.context?.priority || '');
    if(direct) return direct;
    const flags = Array.isArray(payload?.flags) ? payload.flags.map(clean).filter(Boolean) : [];
    if(flags.some((flag)=> /urgent|urgente|stat/i.test(flag))) return 'Urgente';
    if(flags.some((flag)=> /routine|rutinaria|routine/i.test(flag))) return 'Rutinaria';
    return '';
  }
  function resolveDiagnosticOrderLifecycle(payload){
    const data = (payload && typeof payload === 'object') ? payload : {};
    const sourceStatus = clean(data.status || '').toLowerCase();
    const replacedById = clean(data.replaced_by_document_id || '');
    const replacedByUuid = clean(data.replaced_by_document_uuid || '');
    const replacementSourceId = clean(data.replacement_source_document_id || '');
    const replacementSourceUuid = clean(data.replacement_source_document_uuid || '');
    const status = sourceStatus || ((replacedById || replacedByUuid) ? 'replaced' : 'active');
    return {
      status,
      replacedByRef: replacedByUuid || replacedById,
      replacementSourceRef: replacementSourceUuid || replacementSourceId,
      replacementReason: clean(data.replacement_reason || ''),
      replacementAt: clean(data.replacement_at || '')
    };
  }
  function clearOrderReplacementState(){
    orderReplacementState.active = false;
    orderReplacementState.sourceRef = '';
    orderReplacementState.sourceId = '';
    orderReplacementState.sourceUuid = '';
    orderReplacementState.reason = '';
  }
  function normalizeClinicalAssetUrl(rawUrl){
    const value = clean(rawUrl);
    if(!value) return '';
    if(/^https?:\/\//i.test(value) || value.startsWith('data:') || value.startsWith('blob:')){
      return value;
    }
    if(value.startsWith('/')){
      return value;
    }
    return `/${value.replace(/^\/+/, '')}`;
  }
  function resolveClinicalFileFromPayload(payload){
    const data = (payload && typeof payload === 'object') ? payload : {};
    const file = (data.file && typeof data.file === 'object') ? data.file : null;
    const altFile = (!file && data.attachment && typeof data.attachment === 'object') ? data.attachment : null;
    const target = file || altFile;
    const filesList = Array.isArray(data.files) ? data.files : [];
    if(!target && !filesList.length) return null;
    const fromList = (!target && filesList.length && typeof filesList[0] === 'object') ? filesList[0] : null;
    const effective = target || fromList;
    if(!effective) return null;
    const renderMode = clean(data.render_mode || effective.render_mode || '');
    const original = (effective.original && typeof effective.original === 'object') ? effective.original : {};
    const processed = (effective.processed && typeof effective.processed === 'object') ? effective.processed : {};
    const optimized = (effective.optimized && typeof effective.optimized === 'object') ? effective.optimized : {};
    const thumb = (effective.thumb && typeof effective.thumb === 'object') ? effective.thumb : {};
    const mime = clean(
      original.mime
      || processed.mime
      || optimized.mime
      || thumb.mime
      || effective.mime
      || data.mime
      || ''
    );
    const urlRaw = clean(
      processed.url
      || original.url
      || optimized.url
      || thumb.url
      || effective.url
      || processed.path
      || original.path
      || optimized.path
      || thumb.path
      || effective.path
      || ''
    );
    const url = normalizeClinicalAssetUrl(urlRaw);
    const filename = clean(original.filename || effective.filename || '');
    if(!url) return null;
    let sourceField = 'unknown';
    if(clean(processed.url || processed.path)) sourceField = 'payload.file.processed';
    else if(clean(original.url || original.path)) sourceField = 'payload.file.original';
    else if(clean(optimized.url || optimized.path)) sourceField = 'payload.file.optimized';
    else if(clean(thumb.url || thumb.path)) sourceField = 'payload.file.thumb';
    else if(clean(effective.url || effective.path)) sourceField = 'payload.file';
    else if(fromList) sourceField = 'payload.files[0]';
    return {
      renderMode: renderMode || (mime === 'application/pdf' ? 'pdf' : 'image'),
      mime,
      url,
      filename,
      sourceField
    };
  }
  function getOrderDetailModalRefs(){
    const BsModal = window.bootstrap && window.bootstrap.Modal;
    if(!BsModal) return null;
    let modalEl = document.getElementById('modalEstOrderDetail');
    if(!modalEl){
      modalEl = document.createElement('div');
      modalEl.className = 'modal fade';
      modalEl.id = 'modalEstOrderDetail';
      modalEl.setAttribute('tabindex', '-1');
      modalEl.setAttribute('aria-hidden', 'true');
      modalEl.innerHTML = `
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title" data-est-order-detail-title>Detalle de orden diagnóstica</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body" data-est-order-detail-body></div>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary btn-sm" data-est-order-print-disabled disabled>Imprimir (próximamente)</button>
              <button type="button" class="btn btn-primary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
          </div>
        </div>
      `;
      document.body.appendChild(modalEl);
    }
    const titleEl = modalEl.querySelector('[data-est-order-detail-title]');
    const bodyEl = modalEl.querySelector('[data-est-order-detail-body]');
    const printBtn = modalEl.querySelector('[data-est-order-print-disabled]');
    if(printBtn && !printBtn.dataset.bound){
      printBtn.dataset.bound = '1';
      printBtn.addEventListener('click', ()=>{
        setOrderFeedback('La impresión de órdenes diagnósticas se habilitará en una fase posterior.', 'muted');
      });
    }
    const modal = (typeof BsModal.getOrCreateInstance === 'function')
      ? BsModal.getOrCreateInstance(modalEl)
      : new BsModal(modalEl);
    return { modal, modalEl, titleEl, bodyEl };
  }
  function renderOrderDetailState(refs, mode, model = {}){
    if(!refs || !refs.bodyEl || !refs.titleEl) return;
    if(mode === 'loading'){
      refs.titleEl.textContent = 'Detalle de orden diagnóstica';
      refs.bodyEl.innerHTML = '<div class="text-muted">Cargando orden…</div>';
      return;
    }
    if(mode === 'error'){
      refs.titleEl.textContent = 'Detalle de orden diagnóstica';
      refs.bodyEl.innerHTML = `<div class="alert alert-warning mb-0">${escapeHtml(clean(model.message || 'No se pudo consultar el documento.'))}</div>`;
      return;
    }
    const typeLabel = clean(model.typeLabel || 'Orden diagnóstica');
    refs.titleEl.textContent = typeLabel;
    const dateText = clean(model.dateText || '—') || '—';
    const areaText = clean(model.area || '') || '—';
    const priorityText = clean(model.priority || '') || '—';
    const indicationText = clean(model.indication || '');
    const summaryText = clean(model.summary || '');
    const studies = Array.isArray(model.studies) ? model.studies.filter(Boolean) : [];
    const packages = Array.isArray(model.packages) ? model.packages.filter(Boolean) : [];
    const file = model.file && typeof model.file === 'object' ? model.file : null;
    refs.bodyEl.innerHTML = `
      <div class="est-order-detail">
        <div class="est-order-detail-meta" data-order-detail-meta>
          <span><strong>Fecha:</strong> ${escapeHtml(dateText)}</span>
          <span><strong>Área:</strong> ${escapeHtml(areaText)}</span>
          <span><strong>Prioridad:</strong> ${escapeHtml(priorityText)}</span>
        </div>
        <div class="est-order-detail-summary" data-order-detail-summary>${escapeHtml(summaryText || 'Sin resumen clínico registrado.')}</div>
        <div class="est-order-detail-section" data-order-detail-section="indicacion">
          <div class="est-order-detail-label">Indicación clínica</div>
          <div class="est-order-detail-text">${escapeHtml(indicationText || 'Sin indicación clínica registrada.')}</div>
        </div>
        <div class="est-order-detail-section" data-order-detail-section="paquetes">
          <div class="est-order-detail-label">Perfiles / paquetes</div>
          ${packages.length
            ? `<div class="est-order-detail-chips">${packages.map((name)=> `<span class="est-order-detail-chip">${escapeHtml(name)}</span>`).join('')}</div>`
            : '<div class="text-muted small">Sin paquetes o perfiles explícitos.</div>'}
        </div>
        <div class="est-order-detail-section" data-order-detail-section="estudios">
          <div class="est-order-detail-label">Estudios solicitados</div>
          ${studies.length
            ? `<ul class="est-order-detail-list" data-order-detail-items>${studies.map((item)=> `<li>${escapeHtml(item)}</li>`).join('')}</ul>`
            : '<div class="text-muted small">Esta orden no trae estudios detallados en el payload.</div>'}
        </div>
      </div>
    `;
    const detailRoot = refs.bodyEl.querySelector('.est-order-detail');
    let renderTag = '';
    if(detailRoot && file && clean(file.url)){
      const section = document.createElement('div');
      section.className = 'est-order-detail-section';
      section.setAttribute('data-order-detail-section', 'archivo');
      const label = document.createElement('div');
      label.className = 'est-order-detail-label';
      label.textContent = 'Archivo adjunto';
      section.appendChild(label);
      const hint = document.createElement('div');
      hint.className = 'small text-muted mb-1';
      hint.textContent = clean(file.filename || file.url);
      section.appendChild(hint);
      const mode = clean(file.renderMode || '').toLowerCase();
      const isPdf = mode === 'pdf' || /pdf/i.test(clean(file.mime || '')) || /\.pdf(?:$|\?)/i.test(clean(file.url || ''));
      if(isPdf){
        const frame = document.createElement('iframe');
        frame.src = file.url;
        frame.title = 'Resultado PDF';
        frame.style.width = '100%';
        frame.style.height = '320px';
        frame.style.border = '1px solid #d8e6ee';
        frame.style.borderRadius = '8px';
        frame.style.background = '#fff';
        section.appendChild(frame);
        renderTag = 'iframe';
      }else{
        const img = document.createElement('img');
        img.src = file.url;
        img.alt = 'Resultado clínico';
        img.className = 'img-fluid rounded border';
        img.style.maxHeight = '320px';
        img.style.objectFit = 'contain';
        section.appendChild(img);
        renderTag = 'img';
      }
      detailRoot.appendChild(section);
    }
    const isResultDoc = isDiagnosticResultDocumentType(model.documentType);
    const relatedOrderRef = clean(model.relatedOrderRef || '');
    if(isResultDoc && relatedOrderRef && detailRoot){
      const relatedSection = document.createElement('div');
      relatedSection.className = 'est-order-detail-section';
      relatedSection.setAttribute('data-order-detail-section', 'related-order');
      const relatedLabel = document.createElement('div');
      relatedLabel.className = 'est-order-detail-label';
      relatedLabel.textContent = 'Acción secundaria';
      relatedSection.appendChild(relatedLabel);
      const relatedBtn = document.createElement('button');
      relatedBtn.type = 'button';
      relatedBtn.className = 'btn btn-outline-primary btn-sm';
      relatedBtn.setAttribute('data-order-detail-open-related-order', relatedOrderRef);
      relatedBtn.textContent = 'Ver orden original';
      relatedSection.appendChild(relatedBtn);
      detailRoot.appendChild(relatedSection);
    }
    traceOrder('detail_file_rendered', {
      container: '#modalEstOrderDetail [data-est-order-detail-body]',
      inserted: !!renderTag,
      render_tag: renderTag || 'none',
      file_url: clean(file?.url || ''),
      render_mode: clean(file?.renderMode || ''),
      image_count: refs.bodyEl.querySelectorAll('img').length,
      iframe_count: refs.bodyEl.querySelectorAll('iframe').length,
      related_order_ref: relatedOrderRef
    });
  }
  async function fetchOrderDocumentDetail(docRef, opts = {}){
    const safeRef = clean(docRef);
    if(!safeRef){
      throw new Error('Documento no disponible.');
    }
    if(opts.force !== true && orderDocumentDetailCache.has(safeRef)){
      return orderDocumentDetailCache.get(safeRef);
    }
    if(opts.force !== true && orderDocumentDetailFetchInFlight.has(safeRef)){
      return orderDocumentDetailFetchInFlight.get(safeRef);
    }
    const request = (async ()=>{
      try{
        const resp = await fetch(`/api/clinical/index.php/documents/${encodeURIComponent(safeRef)}`, {
          method: 'GET',
          headers: { Accept: 'application/json' },
          credentials: 'same-origin'
        });
        const json = await resp.json().catch(()=> null);
        if(!resp.ok || !json || json.ok !== true){
          const message = clean(json?.message || json?.error?.message || json?.error || `HTTP ${resp.status}`) || 'No se pudo consultar el documento.';
          throw new Error(message);
        }
        const doc = (json?.data && typeof json.data === 'object' && json.data.document)
          ? json.data.document
          : (json?.data || {});
        const payload = parseMaybeJson(doc?.content?.payload)
          || parseMaybeJson(doc?.payload)
          || {};
        const normalizedPayload = (payload && typeof payload === 'object') ? payload : {};
        const detail = {
          id: clean(doc?.document_db_id || doc?.id || ''),
          uuid: clean(doc?.document_uuid || doc?.document_id || ''),
          documentType: clean(doc?.document_type || doc?.type || ''),
          title: clean(doc?.title || ''),
          summary: clean(doc?.summary || doc?.content?.summary || ''),
          context: (doc?.context && typeof doc.context === 'object') ? doc.context : {},
          eventDatetime: clean(
            doc?.ui?.event_datetime
            || doc?.event_datetime
            || doc?.timestamps?.created_at
            || doc?.created_at
            || ''
          ),
          payload: normalizedPayload
        };
        const keys = [safeRef, detail.id, detail.uuid].filter(Boolean);
        keys.forEach((key)=> orderDocumentDetailCache.set(key, detail));
        if(detail.id){
          orderPayloadCache.set(detail.id, normalizedPayload);
        }
        if(detail.uuid){
          orderPayloadCache.set(detail.uuid, normalizedPayload);
        }
        return detail;
      }finally{
        orderDocumentDetailFetchInFlight.delete(safeRef);
      }
    })();
    orderDocumentDetailFetchInFlight.set(safeRef, request);
    return request;
  }
  async function openOrderDetailModal(docRef){
    const refs = getOrderDetailModalRefs();
    if(!refs || !refs.modal){
      setOrderFeedback('No se pudo abrir el visor interno en este entorno.', 'error');
      return;
    }
    renderOrderDetailState(refs, 'loading');
    refs.modal.show();
    try{
      const detail = await fetchOrderDocumentDetail(docRef);
      const payload = (detail && typeof detail.payload === 'object') ? detail.payload : {};
      const model = {
        typeLabel: resolveDiagnosticFamilyTitle(detail.documentType),
        area: resolveOrderAreaLabel(detail.documentType, payload),
        dateText: prettyDate(detail.eventDatetime) || detail.eventDatetime || '—',
        priority: resolveOrderPriorityLabel(payload),
        indication: clean(payload?.indication || ''),
        summary: clean(detail.summary || ''),
        studies: extractDiagnosticItemsFromPayload(payload),
        packages: extractPresetNamesFromPayload(payload),
        file: resolveClinicalFileFromPayload(payload),
        documentType: clean(detail.documentType || ''),
        relatedOrderRef: resolveRelatedOrderRefFromPayload(payload)
      };
      traceOrder('detail_file_resolved', {
        document_ref: clean(docRef),
        document_id: clean(detail.id),
        document_uuid: clean(detail.uuid),
        document_type: clean(detail.documentType),
        file_source: clean(model?.file?.sourceField || ''),
        file_url: clean(model?.file?.url || ''),
        render_mode: clean(model?.file?.renderMode || ''),
        mime: clean(model?.file?.mime || '')
      });
      traceOrder('detail_loaded', {
        document_ref: clean(docRef),
        document_id: clean(detail.id),
        document_uuid: clean(detail.uuid),
        document_type: clean(detail.documentType),
        studies_count: Array.isArray(model.studies) ? model.studies.length : 0,
        packages_count: Array.isArray(model.packages) ? model.packages.length : 0
      });
      renderOrderDetailState(refs, 'ready', model);
    }catch(err){
      renderOrderDetailState(refs, 'error', {
        message: clean(err?.message || 'No se pudo consultar el documento.')
      });
    }
  }
  window.mxmedOpenDiagnosticDocumentDetail = (docRef, _opts = {})=> openOrderDetailModal(docRef);
  function resolveResultDocumentTypeFromOrder(orderDocumentType){
    const type = clean(orderDocumentType).toLowerCase();
    if(type === 'lab_order') return 'lab_result';
    if(type === 'imaging_order') return 'imaging_result';
    return 'result';
  }
  function isDiagnosticResultDocumentType(documentType){
    const type = clean(documentType).toLowerCase();
    return type === 'lab_result' || type === 'imaging_result' || type === 'result' || type === 'lab_pdf';
  }
  function resolveRelatedOrderRefFromPayload(payload){
    const safe = (payload && typeof payload === 'object') ? payload : {};
    const byUuid = clean(
      safe?.related_order_document_uuid
      || safe?.context?.related_order_document_uuid
      || safe?.related_document_uuid
      || safe?.context?.related_document_uuid
      || ''
    );
    if(byUuid) return byUuid;
    return clean(
      safe?.related_order_document_id
      || safe?.context?.related_order_document_id
      || safe?.related_document_id
      || safe?.context?.related_document_id
      || safe?.related_order_id
      || ''
    );
  }
  function buildResultSummary(orderDetail, studies){
    const resultType = resolveResultDocumentTypeFromOrder(orderDetail?.documentType);
    const prefix = resultType === 'lab_result' ? 'Resultado de laboratorio' : (resultType === 'imaging_result' ? 'Resultado de imagen' : 'Resultado de estudio');
    const count = Array.isArray(studies) ? studies.length : 0;
    return `${prefix} · ${count > 0 ? `${count} estudio${count === 1 ? '' : 's'}` : 'sin detalle de estudios'}`;
  }
  function getOrderResultModalRefs(){
    const BsModal = window.bootstrap && window.bootstrap.Modal;
    if(!BsModal) return null;
    let modalEl = document.getElementById('modalEstOrderResult');
    if(!modalEl){
      modalEl = document.createElement('div');
      modalEl.className = 'modal fade';
      modalEl.id = 'modalEstOrderResult';
      modalEl.setAttribute('tabindex', '-1');
      modalEl.setAttribute('aria-hidden', 'true');
      modalEl.innerHTML = `
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Ingresar resultado diagnóstico</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
              <div class="est-order-detail mb-3" data-order-result-context></div>
              <div class="mb-3">
                <label class="form-label">Archivo del resultado (PDF o imagen)</label>
                <input type="file" class="form-control" accept="application/pdf,.pdf,image/*" data-order-result-file>
              </div>
              <div class="mb-0">
                <label class="form-label">Observaciones (opcional)</label>
                <textarea class="form-control" rows="3" data-order-result-notes placeholder="Comentario clínico breve del resultado"></textarea>
              </div>
              <div class="small mt-2 d-none" data-order-result-feedback></div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
              <button type="button" class="btn btn-primary btn-sm" data-order-result-save>Guardar resultado</button>
            </div>
          </div>
        </div>
      `;
      document.body.appendChild(modalEl);
    }
    const refs = {
      modalEl,
      modal: (typeof BsModal.getOrCreateInstance === 'function') ? BsModal.getOrCreateInstance(modalEl) : new BsModal(modalEl),
      contextEl: modalEl.querySelector('[data-order-result-context]'),
      fileInput: modalEl.querySelector('[data-order-result-file]'),
      notesInput: modalEl.querySelector('[data-order-result-notes]'),
      feedbackEl: modalEl.querySelector('[data-order-result-feedback]'),
      saveBtn: modalEl.querySelector('[data-order-result-save]')
    };
    if(refs.saveBtn && !refs.saveBtn.dataset.bound){
      refs.saveBtn.dataset.bound = '1';
      refs.saveBtn.addEventListener('click', ()=> saveOrderResultFromModal());
    }
    return refs;
  }
  const orderResultModalState = {
    orderRef: '',
    orderDetail: null,
    resultRef: '',
    saving: false
  };
  function setOrderResultFeedback(refs, message, tone = 'muted'){
    if(!refs?.feedbackEl) return;
    const text = clean(message);
    refs.feedbackEl.classList.remove('d-none', 'text-muted', 'text-success', 'text-danger');
    if(!text){
      refs.feedbackEl.classList.add('d-none');
      refs.feedbackEl.textContent = '';
      return;
    }
    refs.feedbackEl.classList.add(
      tone === 'success' ? 'text-success' : (tone === 'error' ? 'text-danger' : 'text-muted')
    );
    refs.feedbackEl.textContent = text;
  }
  function renderOrderResultContext(refs, orderDetail){
    if(!refs?.contextEl) return;
    if(!orderDetail){
      refs.contextEl.innerHTML = '<div class="text-muted">No se pudo cargar el contexto de la orden.</div>';
      return;
    }
    const payload = (orderDetail.payload && typeof orderDetail.payload === 'object') ? orderDetail.payload : {};
    const studies = extractDiagnosticItemsFromPayload(payload);
    const area = resolveOrderAreaLabel(orderDetail.documentType, payload);
    const priority = resolveOrderPriorityLabel(payload) || '—';
    const dateText = prettyDate(orderDetail.eventDatetime) || orderDetail.eventDatetime || '—';
    refs.contextEl.innerHTML = `
      <div class="est-order-detail-meta">
        <span><strong>Orden:</strong> ${escapeHtml(resolveDiagnosticTypeLabel(orderDetail.documentType))}</span>
        <span><strong>Fecha:</strong> ${escapeHtml(dateText)}</span>
        <span><strong>Área:</strong> ${escapeHtml(area || '—')}</span>
        <span><strong>Prioridad:</strong> ${escapeHtml(priority)}</span>
      </div>
      <div class="est-order-detail-section">
        <div class="est-order-detail-label">Estudios solicitados</div>
        ${studies.length
          ? `<ul class="est-order-detail-list">${studies.map((item)=> `<li>${escapeHtml(item)}</li>`).join('')}</ul>`
          : '<div class="text-muted small">Sin estudios detallados en el payload de la orden.</div>'}
      </div>
    `;
  }
  async function openOrderResultModal(orderRef, opts = {}){
    const refs = getOrderResultModalRefs();
    if(!refs?.modal){
      setOrderFeedback('No se pudo abrir el ingreso de resultados en este entorno.', 'error');
      return;
    }
    orderResultModalState.orderRef = clean(orderRef);
    orderResultModalState.orderDetail = null;
    orderResultModalState.resultRef = clean(opts?.resultRef || '');
    if(refs.fileInput) refs.fileInput.value = '';
    if(refs.notesInput) refs.notesInput.value = '';
    setOrderResultFeedback(refs, 'Cargando contexto de la orden…');
    refs.saveBtn && (refs.saveBtn.disabled = true);
    refs.modal.show();
    try{
      const detail = await fetchOrderDocumentDetail(orderRef);
      orderResultModalState.orderDetail = detail;
      renderOrderResultContext(refs, detail);
      setOrderResultFeedback(refs, '');
      refs.saveBtn && (refs.saveBtn.disabled = false);
    }catch(err){
      renderOrderResultContext(refs, null);
      setOrderResultFeedback(refs, clean(err?.message || 'No se pudo cargar la orden.'), 'error');
      refs.saveBtn && (refs.saveBtn.disabled = true);
    }
  }
  async function saveOrderResultFromModal(){
    const refs = getOrderResultModalRefs();
    if(!refs || orderResultModalState.saving) return;
    const detail = orderResultModalState.orderDetail;
    if(!detail){
      setOrderResultFeedback(refs, 'No hay orden activa para guardar el resultado.', 'error');
      return;
    }
    const file = refs.fileInput?.files && refs.fileInput.files[0] ? refs.fileInput.files[0] : null;
    if(!file){
      setOrderResultFeedback(refs, 'Selecciona un archivo PDF o imagen.', 'error');
      return;
    }
    const payload = (detail.payload && typeof detail.payload === 'object') ? detail.payload : {};
    const patientId = clean(detail?.context?.patient_id || resolveOrderPatientId());
    if(!patientId){
      setOrderResultFeedback(refs, 'No se pudo resolver el paciente activo para guardar el resultado.', 'error');
      return;
    }
    const studies = extractDiagnosticItemsFromPayload(payload);
    const resultDocumentType = resolveResultDocumentTypeFromOrder(detail.documentType);
    const observations = clean(refs.notesInput?.value || '');
    const payloadData = {
      source: 'estudios_host_resultado',
      related_order_document_id: clean(detail.id || ''),
      related_order_document_uuid: clean(detail.uuid || ''),
      related_document_id: clean(detail.uuid || detail.id || ''),
      order_document_type: clean(detail.documentType || ''),
      order_area: resolveOrderAreaLabel(detail.documentType, payload),
      requested_studies: studies,
      selection_count: studies.length,
      indication: clean(payload?.indication || ''),
      observations: observations || null,
      result_file_name: clean(file.name || '')
    };
    const formData = new FormData();
    formData.append('patient_id', patientId);
    formData.append('document_type', resultDocumentType);
    formData.append('summary', buildResultSummary(detail, studies));
    formData.append('event_datetime', nowSqlDateTime());
    formData.append('payload', JSON.stringify(payloadData));
    formData.append('file', file);
    orderResultModalState.saving = true;
    if(refs.saveBtn) refs.saveBtn.disabled = true;
    setOrderResultFeedback(refs, 'Guardando resultado canónico…');
    try{
      const resp = await fetch('/api/clinical/index.php/documents', {
        method: 'POST',
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
        body: formData
      });
      const json = await resp.json().catch(()=> null);
      if(!resp.ok || !json || json.ok !== true){
        const message = clean(json?.message || json?.error?.message || json?.error || `HTTP ${resp.status}`) || 'No se pudo guardar el resultado.';
        throw new Error(message);
      }
      const resultRef = clean(
        json?.data?.document?.document_uuid
        || json?.data?.document_uuid
        || json?.data?.document?.document_db_id
        || json?.data?.document?.id
        || ''
      );
      setOrderResultFeedback(refs, 'Resultado guardado correctamente.', 'success');
      traceOrder('result_saved', {
        order_document_id: clean(detail.id || ''),
        order_document_uuid: clean(detail.uuid || ''),
        result_document_ref: resultRef,
        result_document_type: resultDocumentType
      });
      try{
        window.dispatchEvent(new CustomEvent('mxmed:clinical-document-created', {
          detail: {
            patient_id: patientId,
            document_type: resultDocumentType,
            source: 'estudios_host_resultado',
            related_order_document_id: clean(detail.id || ''),
            related_order_document_uuid: clean(detail.uuid || '')
          }
        }));
      }catch(_){}
      requestRefreshCanonicalOrdersList();
      setTimeout(()=> refs.modal.hide(), 250);
    }catch(err){
      setOrderResultFeedback(refs, clean(err?.message || 'No se pudo guardar el resultado.'), 'error');
    }finally{
      orderResultModalState.saving = false;
      if(refs.saveBtn) refs.saveBtn.disabled = false;
    }
  }
  async function fetchOrderPayloadById(docId){
    const safeId = clean(docId);
    if(!safeId) return null;
    if(orderPayloadCache.has(safeId)){
      return orderPayloadCache.get(safeId);
    }
    if(orderPayloadFetchInFlight.has(safeId)){
      return orderPayloadFetchInFlight.get(safeId);
    }
    const request = (async ()=>{
      try{
        const detail = await fetchOrderDocumentDetail(safeId);
        const payload = (detail && typeof detail.payload === 'object') ? detail.payload : {};
        orderPayloadCache.set(safeId, payload);
        return payload;
      }catch(_){
        return null;
      }finally{
        orderPayloadFetchInFlight.delete(safeId);
      }
    })();
    orderPayloadFetchInFlight.set(safeId, request);
    return request;
  }
  async function hydrateOrderRowsPayload(rows){
    const safeRows = Array.isArray(rows) ? rows : [];
    await Promise.all(safeRows.map(async (row)=>{
      const docId = clean(row?.id);
      const docUuid = clean(row?.document_uuid || row?.document_id || '');
      const detail = await fetchOrderDocumentDetail(docId || docUuid).catch(()=> null);
      if(detail && typeof detail === 'object'){
        row.__docPayload = (detail.payload && typeof detail.payload === 'object') ? detail.payload : {};
        row.__docUuid = clean(detail.uuid || docUuid);
        row.__docId = clean(detail.id || docId);
        return;
      }
      const cacheKey = docId || docUuid;
      if(cacheKey && orderPayloadCache.has(cacheKey)){
        row.__docPayload = orderPayloadCache.get(cacheKey);
      }else{
        const payload = await fetchOrderPayloadById(docId || docUuid);
        row.__docPayload = (payload && typeof payload === 'object') ? payload : {};
      }
      row.__docUuid = docUuid;
      row.__docId = docId;
    }));
    return safeRows;
  }
  function prependOrderCard(order, opts = {}){
    if(!orderList || !order || !Array.isArray(order.items) || !order.items.length) return;
    const area = clean(order.area) || 'Estudios';
    const card = document.createElement('div');
    card.className = `est-order-card ${inferOrderColor(area)}`;
    card.setAttribute('data-est-order-area', area);
    card.setAttribute('data-est-order-items', order.items.join(', '));
    if(order.documentUuid){
      card.setAttribute('data-document-uuid', order.documentUuid);
      card.setAttribute('data-est-document-uuid', order.documentUuid);
    }
    if(order.documentId){
      card.setAttribute('data-document-id', String(order.documentId));
      card.setAttribute('data-est-document-id', String(order.documentId));
    }
    const resultRef = clean(order.resultRef || '');
    if(resultRef){
      card.setAttribute('data-result-document-ref', resultRef);
    }
    const orderStatus = clean(order.orderStatus || 'active').toLowerCase() || 'active';
    card.setAttribute('data-order-status', orderStatus);
    if(order.documentType){
      card.setAttribute('data-document-type', clean(order.documentType).toLowerCase());
    }
    const replacedByRef = clean(order.replacedByRef || '');
    const replacementSourceRef = clean(order.replacementSourceRef || '');
    if(replacedByRef){
      card.setAttribute('data-replaced-by-document-ref', replacedByRef);
    }
    if(replacementSourceRef){
      card.setAttribute('data-replacement-source-document-ref', replacementSourceRef);
    }
    const eventDatetime = clean(order.eventDatetime) || nowSqlDateTime();
    card.setAttribute('data-event-datetime', eventDatetime);
    if(order.readOnly){
      card.setAttribute('data-est-readonly', '1');
    }
    const selectionCountRaw = Number(order.selectionCount);
    const selectionCount = Number.isFinite(selectionCountRaw) && selectionCountRaw > 0
      ? Math.round(selectionCountRaw)
      : order.items.length;
    const summaryText = clean(order.summary || '');
    const studiesPreview = clean(order.studiesPreview || order.items[0] || '');
    const studiesComplement = clean(order.studiesComplement || '');
    const meta = clean(order.metaText || '') || `${selectionCount} estudios · ${prettyDate(eventDatetime) || prettyDate(nowSqlDateTime())}`;
    const title = clean(order.displayTitle || '') || `${area} · ${clean(order.indication) || 'Orden clínica'}`;
    const orderIconSvg = resolveClinicalDocumentSvgIcon(order.documentType, area);
    if(summaryText){
      card.setAttribute('data-summary', summaryText);
    }
    if(studiesPreview){
      card.setAttribute('data-studies-preview', studiesPreview);
    }
    if(studiesComplement){
      card.setAttribute('data-studies-complement', studiesComplement);
    }
    card.innerHTML = `
      <div class="est-order-head">
        <div class="est-order-title"><span class="est-order-ico est-order-ico-svg" aria-hidden="true">${orderIconSvg}</span><span>${escapeHtml(title)}</span></div>
      </div>
      ${order.readOnly ? '' : '<button type="button" class="est-order-del" aria-label="Eliminar orden" data-est-order-delete>&times;</button>'}
      ${summaryText ? `<div class="est-order-summary">${escapeHtml(summaryText)}</div>` : ''}
      ${studiesPreview ? `<div class="est-order-preview">${escapeHtml(studiesPreview)}</div>` : ''}
      ${studiesComplement ? `<div class="est-order-complement">${escapeHtml(studiesComplement)}</div>` : ''}
      <div class="est-order-meta">${escapeHtml(meta)}</div>
      ${order.readOnly && orderStatus === 'replaced'
        ? '<div class="est-order-result-state has-result">Orden reemplazada</div><div class="text-muted small">Esta orden fue sustituida por una nueva versión.</div>'
        : ''}
      ${order.readOnly && orderStatus !== 'replaced' ? `<div class="est-order-result-state ${order.hasResult ? 'has-result' : 'no-result'}">${order.hasResult ? 'Resultado cargado' : 'Sin resultado cargado'}</div>` : ''}
      ${order.priority ? `<div class="est-order-tags"><span>${escapeHtml(order.priority)}</span></div>` : ''}
      <div class="est-order-actions">
        ${order.readOnly ? '' : `<button type="button" class="btn btn-outline-primary btn-sm" data-est-order-edit>${formatOrderActionLabel(card)}</button>`}
        ${order.readOnly && orderStatus === 'active' && !order.hasResult ? '<button type="button" class="btn btn-outline-success btn-sm" data-est-order-upload-result>Ingresar resultado</button>' : ''}
        ${order.readOnly && orderStatus === 'active' && !order.hasResult ? '<button type="button" class="btn btn-outline-warning btn-sm" data-est-order-replace>Reemplazar orden</button>' : ''}
        ${order.readOnly && order.hasResult ? '<button type="button" class="btn btn-outline-secondary btn-sm" data-est-order-view-result>Ver resultado</button>' : ''}
        ${order.readOnly && orderStatus === 'replaced' && replacedByRef ? '<button type="button" class="btn btn-outline-secondary btn-sm" data-est-order-view-replaced-by>Ver orden reemplazante</button>' : ''}
        ${order.readOnly && orderStatus === 'active' && replacementSourceRef ? '<button type="button" class="btn btn-outline-secondary btn-sm" data-est-order-view-replacement-source>Ver orden previa</button>' : ''}
        <button class="btn btn-outline-secondary btn-sm">Imprimir</button>
        <button class="btn btn-outline-secondary btn-sm">Compartir</button>
      </div>
    `;
    if(opts.position === 'append'){
      orderList.appendChild(card);
    }else{
      orderList.prepend(card);
    }
  }
  let refreshOrdersInFlight = null;
  let refreshOrdersTimer = null;
  let lastListRefreshSignature = '';
  let lastOrderResultsIndex = new Map();
  async function fetchCanonicalOrderDocuments(patientId){
    const safePatientId = clean(patientId);
    if(!safePatientId) return [];
    const url = `/api/clinical/index.php/documents?patient_id=${encodeURIComponent(safePatientId)}&limit=80`;
    const resp = await fetch(url, {
      method: 'GET',
      headers: { Accept: 'application/json' },
      credentials: 'same-origin'
    });
    const json = await resp.json().catch(()=> null);
    if(!resp.ok || !json || json.ok !== true){
      throw new Error(clean(json?.message || json?.error || `HTTP ${resp.status}`) || 'No se pudieron consultar órdenes.');
    }
    const allItems = Array.isArray(json?.data?.items) ? json.data.items : [];
    const allowedTypes = new Set(['lab_order', 'imaging_order']);
    const filteredItems = allItems.filter((row)=> allowedTypes.has(clean(row?.document_type).toLowerCase()));
    const deduped = [];
    const seen = new Set();
    filteredItems.forEach((row)=>{
      const key = clean(row?.document_uuid || row?.document_id || row?.id || '');
      if(!key) return;
      if(seen.has(key)) return;
      seen.add(key);
      deduped.push(row);
    });
    return deduped.sort((a, b)=>{
      const ad = clean(a?.event_datetime);
      const bd = clean(b?.event_datetime);
      const byDatetime = bd.localeCompare(ad);
      if(byDatetime !== 0) return byDatetime;
      const ai = Number(clean(a?.id || 0));
      const bi = Number(clean(b?.id || 0));
      return bi - ai;
    });
  }
  async function fetchCanonicalResultDocuments(patientId){
    const safePatientId = clean(patientId);
    if(!safePatientId) return [];
    const url = `/api/clinical/index.php/documents?patient_id=${encodeURIComponent(safePatientId)}&limit=120`;
    const resp = await fetch(url, {
      method: 'GET',
      headers: { Accept: 'application/json' },
      credentials: 'same-origin'
    });
    const json = await resp.json().catch(()=> null);
    if(!resp.ok || !json || json.ok !== true){
      throw new Error(clean(json?.message || json?.error || `HTTP ${resp.status}`) || 'No se pudieron consultar resultados.');
    }
    const allItems = Array.isArray(json?.data?.items) ? json.data.items : [];
    const allowedTypes = new Set(['lab_result', 'imaging_result', 'result', 'lab_pdf']);
    const filteredItems = allItems.filter((row)=> allowedTypes.has(clean(row?.document_type).toLowerCase()));
    return filteredItems.sort((a, b)=>{
      const ad = clean(a?.event_datetime);
      const bd = clean(b?.event_datetime);
      const byDatetime = bd.localeCompare(ad);
      if(byDatetime !== 0) return byDatetime;
      const ai = Number(clean(a?.id || 0));
      const bi = Number(clean(b?.id || 0));
      return bi - ai;
    });
  }
  function extractRelatedOrderRefsFromPayload(payload){
    const refs = [
      clean(payload?.related_order_document_id),
      clean(payload?.related_order_document_uuid),
      clean(payload?.related_document_id),
      clean(payload?.related_document_uuid),
      clean(payload?.related_order_id),
      clean(payload?.context?.related_order_document_id),
      clean(payload?.context?.related_order_document_uuid),
      clean(payload?.context?.related_document_id),
      clean(payload?.context?.related_document_uuid)
    ].filter(Boolean);
    return Array.from(new Set(refs));
  }
  async function buildOrderResultsIndex(patientId){
    const map = new Map();
    const rows = await fetchCanonicalResultDocuments(patientId);
    await Promise.all(rows.map(async (row)=>{
      const ref = clean(row?.id || row?.document_uuid || row?.document_id);
      if(!ref) return;
      try{
        const detail = await fetchOrderDocumentDetail(ref);
        const payload = (detail && typeof detail.payload === 'object') ? detail.payload : {};
        const relatedRefs = extractRelatedOrderRefsFromPayload(payload);
        if(!relatedRefs.length) return;
        const resultInfo = {
          id: clean(detail?.id || row?.id || ''),
          uuid: clean(detail?.uuid || row?.document_uuid || row?.document_id || ''),
          documentType: clean(detail?.documentType || row?.document_type || ''),
          eventDatetime: clean(detail?.eventDatetime || row?.event_datetime || '')
        };
        relatedRefs.forEach((relatedRef)=>{
          if(!map.has(relatedRef)){
            map.set(relatedRef, resultInfo);
          }
        });
      }catch(_){}
    }));
    return map;
  }
  function resolveDocRefMatch(row, ref){
    if(!row || !ref) return false;
    const rowId = clean(row?.id);
    const rowUuid = clean(row?.document_uuid || row?.document_id || '');
    const refId = clean(ref?.id);
    const refUuid = clean(ref?.uuid);
    if(refId && rowId === refId) return true;
    if(refUuid && rowUuid === refUuid) return true;
    return false;
  }
  function syncCanonicalOrderCardsFromDocuments(rows, resultsIndex = new Map()){
    if(!orderList || !Array.isArray(rows)) return;
    orderList.innerHTML = '';
    const newestRow = rows.find((row)=> resolveDocRefMatch(row, lastCreatedOrderRef)) || null;
    const historicalRows = newestRow ? rows.filter((row)=> !resolveDocRefMatch(row, lastCreatedOrderRef)) : rows.slice();
    const historicalLimit = 5;
    const visibleHistoricalRows = historicalRows.slice(0, historicalLimit);

    const renderRow = (row)=>{
      const docId = clean(row?.id || row?.__docId);
      const docType = clean(row?.document_type).toLowerCase();
      const docUuid = clean(row?.document_uuid || row?.document_id || row?.__docUuid || '');
      if(!docId && !docUuid) return;
      if(docType !== 'lab_order' && docType !== 'imaging_order') return;
      const area = docType === 'lab_order' ? 'Laboratorio' : 'Imagenología';
      const summary = clean(row?.summary);
      const countMatch = summary.match(/^(\d+)\s+estudios?/i);
      const parsedSelectionCount = countMatch ? Number(countMatch[1]) : 0;
      const payload = (row && typeof row.__docPayload === 'object') ? row.__docPayload : {};
      const lifecycle = resolveDiagnosticOrderLifecycle(payload);
      const preview = buildDiagnosticOrderPreview(row, payload);
      const relatedResult = resultsIndex.get(docId) || resultsIndex.get(docUuid) || null;
      prependOrderCard({
        area,
        items: [summary || clean(row?.title) || 'Orden clínica'],
        selectionCount: parsedSelectionCount,
        summary: preview.summary || summary || '',
        displayTitle: preview.displayTitle,
        studiesPreview: preview.studiesPreview || clean(row?.title) || summary || '',
        studiesComplement: preview.studiesComplement || '',
        metaText: preview.metaText || '',
        eventDatetime: clean(row?.event_datetime) || nowSqlDateTime(),
        indication: clean(row?.title),
        priority: '',
        documentType: docType,
        documentId: docId,
        documentUuid: docUuid,
        hasResult: !!relatedResult,
        resultRef: clean(relatedResult?.uuid || relatedResult?.id || ''),
        orderStatus: lifecycle.status,
        replacedByRef: lifecycle.replacedByRef,
        replacementSourceRef: lifecycle.replacementSourceRef,
        readOnly: true
      }, { position: 'append' });
    };

    if(newestRow){
      renderRow(newestRow);
      const firstHistorical = visibleHistoricalRows[0] || null;
      if(firstHistorical){
        const divider = document.createElement('div');
        divider.className = 'est-orders-divider';
        divider.textContent = 'Historicas';
        orderList.appendChild(divider);
      }
    }
    visibleHistoricalRows.forEach(renderRow);
    if(historicalRows.length > historicalLimit){
      const note = document.createElement('div');
      note.className = 'text-muted small mt-2';
      note.textContent = 'Mostrando historico reciente. Consulta el historial completo en Historial de Atencion.';
      orderList.appendChild(note);
    }
  }
  async function refreshCanonicalOrdersList(){
    if(refreshOrdersInFlight) return refreshOrdersInFlight;
    refreshOrdersInFlight = (async ()=>{
    try{
      const patientId = resolveOrderPatientId();
      if(!orderList) return;
      orderList.innerHTML = '';
      if(!patientId){
        setOrderFeedback('Selecciona un paciente para consultar órdenes generadas.', 'muted');
        return;
      }
      const rows = await fetchCanonicalOrderDocuments(patientId);
      await hydrateOrderRowsPayload(rows);
      let resultsIndex = new Map();
      try{
        resultsIndex = await buildOrderResultsIndex(patientId);
      }catch(_){
        resultsIndex = new Map();
      }
      lastOrderResultsIndex = resultsIndex;
      syncCanonicalOrderCardsFromDocuments(rows, resultsIndex);
      const signature = JSON.stringify(rows.slice(0, 10).map((row)=> `${clean(row?.id)}|${clean(row?.event_datetime)}|${clean(row?.document_type)}`));
      if(signature !== lastListRefreshSignature){
        lastListRefreshSignature = signature;
        traceOrder('list_refresh', {
        patient_id: patientId,
        count: rows.length,
        top3: rows.slice(0, 3).map((row)=> ({
          id: clean(row?.id),
          document_uuid: clean(row?.document_uuid || row?.document_id || ''),
          document_type: clean(row?.document_type),
          event_datetime: clean(row?.event_datetime),
          title: clean(row?.title),
          summary: clean(row?.summary)
        }))
        });
      }
      if(lastCreatedOrderRef.id || lastCreatedOrderRef.uuid){
        orderList.querySelectorAll('.est-order-card').forEach((el)=> el.classList.remove('is-latest-created'));
        const card = Array.from(orderList.querySelectorAll('.est-order-card')).find((el)=>{
          const idRef = clean(el.getAttribute('data-document-id') || el.getAttribute('data-est-document-id'));
          const uuidRef = clean(el.getAttribute('data-document-uuid') || el.getAttribute('data-est-document-uuid'));
          return (lastCreatedOrderRef.id && idRef === lastCreatedOrderRef.id)
            || (lastCreatedOrderRef.uuid && uuidRef === lastCreatedOrderRef.uuid);
        });
        if(card){
          card.classList.add('is-latest-created');
          card.classList.add('est-order-focus', 'is-new-document');
          setTimeout(()=> {
            card.classList.remove('est-order-focus', 'is-new-document');
          }, 2800);
          try{
            card.scrollIntoView({ behavior: 'smooth', block: 'center' });
          }catch(_){}
        }
      }
    }catch(err){
      setOrderFeedback(clean(err?.message || ''), 'muted');
    }
    })();
    try{
      return await refreshOrdersInFlight;
    }finally{
      refreshOrdersInFlight = null;
    }
  }
  function requestRefreshCanonicalOrdersList(){
    if(refreshOrdersTimer){
      window.clearTimeout(refreshOrdersTimer);
    }
    refreshOrdersTimer = window.setTimeout(()=>{
      refreshOrdersTimer = null;
      refreshCanonicalOrdersList();
    }, 80);
  }
  function resolveResultRefForOrderRefs(orderId, orderUuid, rawResultRef = ''){
    const safeOrderId = clean(orderId);
    const safeOrderUuid = clean(orderUuid);
    const safeRawResultRef = clean(rawResultRef);
    const orderRef = safeOrderUuid || safeOrderId;
    if(safeRawResultRef && (!orderRef || safeRawResultRef !== orderRef)){
      return safeRawResultRef;
    }
    if(lastOrderResultsIndex && typeof lastOrderResultsIndex.get === 'function'){
      const related = (safeOrderId && lastOrderResultsIndex.get(safeOrderId))
        || (safeOrderUuid && lastOrderResultsIndex.get(safeOrderUuid))
        || null;
      if(related){
        const fallbackRef = clean(related.uuid || related.id || '');
        if(fallbackRef && (!orderRef || fallbackRef !== orderRef)){
          return fallbackRef;
        }
      }
    }
    return '';
  }
  function resolveResultRefForOrderCard(card){
    if(!card) return '';
    const rawResultRef = clean(card.getAttribute('data-result-document-ref') || '');
    const orderId = clean(card.getAttribute('data-document-id') || card.getAttribute('data-est-document-id') || '');
    const orderUuid = clean(card.getAttribute('data-document-uuid') || card.getAttribute('data-est-document-uuid') || '');
    return resolveResultRefForOrderRefs(orderId, orderUuid, rawResultRef);
  }
  function buildOrderSubmitSignature(params){
    const items = Array.isArray(params?.items) ? params.items.map(clean).filter(Boolean).sort() : [];
    const flags = Array.isArray(params?.flags) ? params.flags.map(clean).filter(Boolean).sort() : [];
    return JSON.stringify({
      patientId: clean(params?.patientId),
      documentType: clean(params?.documentType),
      area: clean(params?.area),
      priority: clean(params?.priority),
      indication: clean(params?.indication),
      replacementSourceRef: clean(params?.replacementSourceRef),
      items,
      flags
    });
  }
  async function saveCanonicalStudyOrder(params){
    const items = normalizeItems(Array.isArray(params?.items) ? params.items.map(clean).filter(Boolean) : []);
    if(!items.length){
      setOrderFeedback('Selecciona al menos un estudio para generar la orden.', 'error');
      return { ok: false, reason: 'empty_selection' };
    }
    const area = clean(params?.area || areaSelect?.value || '');
    const documentType = resolveOrderDocumentType(params?.controllerKey, area);
    if(!documentType){
      setOrderFeedback(`La persistencia canónica para ${area || 'esta área'} se habilitará en una fase posterior.`, 'muted');
      return { ok: false, reason: 'unsupported_area' };
    }
    const patientId = resolveOrderPatientId();
    if(!patientId){
      setOrderFeedback('Selecciona un paciente activo antes de generar la orden.', 'error');
      return { ok: false, reason: 'missing_patient' };
    }
    const encounterKey = resolveOrderEncounterKey();
    const appointmentId = resolveOrderAppointmentId(patientId, encounterKey);
    const eventDatetime = nowSqlDateTime();
    const priority = clean(prioritySelect?.value || '');
    const indication = clean(indicationTextarea?.value || '');
    const title = documentType === 'lab_order' ? 'Orden de laboratorio' : 'Orden de imagen';
    const summaryParts = [];
    summaryParts.push(`${items.length} estudio${items.length === 1 ? '' : 's'}`);
    if(priority) summaryParts.push(priority);
    if(indication) summaryParts.push(indication);
    const summary = summaryParts.join(' · ');
    const flags = getOrderedFlags(params?.controllerKey);
    const replacementRef = clean(params?.replacement?.sourceRef || '');
    const replacementReason = clean(params?.replacement?.reason || '');
    const isReplacementMode = replacementRef !== '';
    traceOrder('save_submit', {
      patient_id: patientId,
      encounter_key: encounterKey,
      appointment_id: appointmentId,
      controller_key: clean(params?.controllerKey),
      area: area,
      document_type: documentType,
      mode: isReplacementMode ? 'replacement' : 'create',
      replacement_source_ref: replacementRef,
      priority: priority,
      indication: indication,
      selection_count: items.length,
      requested_studies: items.slice()
    });

    const payloadData = {
      source: 'estudios_host_solicitar',
      order_area: area || null,
      priority: priority || null,
      indication: indication || null,
      requested_studies: items,
      flags: flags,
      selection_count: items.length,
      status: 'active'
    };
    const signature = buildOrderSubmitSignature({
      patientId,
      documentType,
      area,
      priority,
      indication,
      replacementSourceRef: replacementRef,
      items,
      flags
    });
    const nowTs = Date.now();
    if(orderSubmitLock.signature === signature && (nowTs - Number(orderSubmitLock.ts || 0)) < 4000){
      setOrderFeedback('Solicitud duplicada detectada. Se ignoró un guardado repetido.', 'muted');
      return { ok: false, reason: 'duplicate_submit_suppressed' };
    }
    orderSubmitLock.signature = signature;
    orderSubmitLock.ts = nowTs;
    const formData = new FormData();
    formData.append('patient_id', patientId);
    formData.append('document_type', documentType);
    formData.append('title', title);
    formData.append('summary', summary);
    formData.append('event_datetime', eventDatetime);
    formData.append('payload', JSON.stringify(payloadData));
    if(encounterKey) formData.append('encounter_key', encounterKey);
    if(appointmentId) formData.append('appointment_id', appointmentId);

    setOrderFeedback(isReplacementMode ? 'Guardando orden de reemplazo…' : 'Guardando orden canónica…');
    try{
      let resp;
      if(isReplacementMode){
        const body = {
          document_type: documentType,
          order_area: area || null,
          priority: priority || null,
          indication: indication || null,
          requested_studies: items,
          flags: flags,
          replacement_reason: replacementReason || null,
          summary_override: summary,
          title_override: title,
          event_datetime: eventDatetime
        };
        resp = await fetch(`/api/clinical/index.php/documents/${encodeURIComponent(replacementRef)}/replace`, {
          method: 'POST',
          headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json'
          },
          credentials: 'same-origin',
          body: JSON.stringify(body)
        });
      }else{
        resp = await fetch('/api/clinical/index.php/documents', {
          method: 'POST',
          headers: {
            Accept: 'application/json'
          },
          credentials: 'same-origin',
          body: formData
        });
      }
      const json = await resp.json().catch(()=> null);
      if(!resp.ok || !json || json.ok !== true){
        const message = clean(json?.message || json?.error?.message || json?.error || `HTTP ${resp.status}`) || 'No se pudo guardar la orden.';
        throw new Error(message);
      }
      const documentUuid = clean(
        json?.data?.replacement_document?.document_id
        || json?.data?.replacement_document_uuid
        || json?.data?.document?.document_uuid
        || json?.data?.document?.document_id
        || json?.data?.document_uuid
        || json?.data?.document_id
        || ''
      );
      const documentDbId = clean(
        json?.data?.replacement_document?.document_db_id
        || json?.data?.replacement_document_id
        || json?.data?.document?.document_db_id
        || json?.data?.document?.id
        || ''
      );
      lastCreatedOrderRef.id = documentDbId || '';
      lastCreatedOrderRef.uuid = documentUuid || '';
      traceOrder('save_response', {
        ok: true,
        document_db_id: documentDbId,
        document_uuid: documentUuid,
        title: clean(json?.data?.document?.title),
        summary: clean(json?.data?.document?.content?.summary),
        event_datetime: clean(json?.data?.document?.ui?.event_datetime)
      });
      await refreshCanonicalOrdersList();
      setOrderFeedback(isReplacementMode ? 'Orden reemplazada y nueva versión guardada.' : 'Orden canónica guardada correctamente.', 'success');
      if(isReplacementMode){
        clearOrderReplacementState();
      }
      try{
        window.dispatchEvent(new CustomEvent('mxmed:clinical-document-created', {
          detail: {
            patient_id: patientId,
            encounter_key: encounterKey || '',
            appointment_id: appointmentId || '',
            document_type: documentType,
            document_uuid: documentUuid || '',
            source: isReplacementMode ? 'estudios_host_replace' : 'estudios_host_solicitar'
          }
        }));
      }catch(_){}
      return { ok: true, documentType, documentUuid, patientId, encounterKey, appointmentId };
    }catch(err){
      orderSubmitLock.signature = '';
      orderSubmitLock.ts = 0;
      traceOrder('save_response', {
        ok: false,
        error: clean(err?.message || 'request_failed')
      });
      setOrderFeedback(clean(err?.message || 'No se pudo guardar la orden canónica.'), 'error');
      return { ok: false, reason: 'request_failed' };
    }
  }
  function normalizeItems(items){
    const seen = new Set();
    return items.map(i=>i.trim()).filter(Boolean).filter(item=>{
      if(seen.has(item)) return false;
      seen.add(item);
      return true;
    });
  }
  function normalizeSearchText(value){
    return String(value || '')
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/[^a-z0-9]+/g, ' ')
      .trim();
  }
  function parseInputValue(val){
    if(!val) return [];
    return normalizeItems(val.split(','));
  }
  function getInputItems(input){
    const raw = (input?.dataset?.estRaw || '').trim();
    if(raw) return normalizeItems(raw.split(rawDelimiter));
    return parseInputValue(input?.value || '');
  }
  function getItemMeta(item, controllerKey){
    const entry = itemMetaMap[item];
    if(!entry) return null;
    if(controllerKey && entry[controllerKey]) return entry[controllerKey];
    return entry.default || entry[Object.keys(entry)[0]] || null;
  }
  function getItemGroup(item, controllerKey){
    const entry = itemGroupMap[item];
    if(!entry) return null;
    if(controllerKey && entry[controllerKey]) return entry[controllerKey];
    return entry.default || entry[Object.keys(entry)[0]] || null;
  }
  function getOrderedFlags(controllerKey){
    if(controllerKey === 'endoscopia'){
      return endoscopyFlagOrder.filter(id=> activeFlags.has(id)).map(id=> flagLabels[id]).filter(Boolean);
    }
    if(controllerKey === 'patologia'){
      return pathologyFlagOrder.filter(id=> activeFlags.has(id)).map(id=> flagLabels[id]).filter(Boolean);
    }
    if(controllerKey === 'cardiologia'){
      return cardiologyFlagOrder.filter(id=> activeFlags.has(id)).map(id=> flagLabels[id]).filter(Boolean);
    }
    if(controllerKey === 'lab'){
      return labFlagOrder.filter(id=> activeFlags.has(id)).map(id=> flagLabels[id]).filter(Boolean);
    }
    if(controllerKey === 'genetica'){
      return geneticsFlagOrder.filter(id=> activeFlags.has(id)).map(id=> flagLabels[id]).filter(Boolean);
    }
    if(controllerKey === 'funcionales'){
      return functionalFlagOrder.filter(id=> activeFlags.has(id)).map(id=> flagLabels[id]).filter(Boolean);
    }
    if(controllerKey === 'imagenologia'){
      return imagingFlagOrder.filter(id=> activeFlags.has(id)).map(id=> flagLabels[id]).filter(Boolean);
    }
    return Array.from(activeFlags).map(id=> flagLabels[id]).filter(Boolean);
  }
  function buildDisplayName(item, controllerKey){
    const meta = getItemMeta(item, controllerKey);
    let name = item;
    const flags = getOrderedFlags(controllerKey).join(' ');
    if(controllerKey === 'lab' || controllerKey === 'endoscopia' || controllerKey === 'patologia' || controllerKey === 'cardiologia' || controllerKey === 'imagenologia' || controllerKey === 'genetica' || controllerKey === 'funcionales'){
      if(flags) name = `${name} ${flags}`;
      return name.trim();
    }
    if(meta?.modalityLabel){
      name = `${meta.modalityLabel} ${name}`;
      if(flags) name = `${name} ${flags}`;
    }
    return name.trim();
  }
  function setInputItems(input, items, controllerKey){
    if(!input) return;
    input.dataset.estRaw = items.join(rawDelimiter);
    input.value = items.map(item=> buildDisplayName(item, controllerKey)).join(', ');
  }
  function setSelectionOrder(items){
    selectionOrder = normalizeItems(items || []);
  }
  function addToOrder(item){
    if(!item) return;
    if(!selectionOrder.includes(item)) selectionOrder.push(item);
  }
  function addToOrderList(items){
    normalizeItems(items || []).forEach(addToOrder);
  }
  function removeFromOrder(item){
    selectionOrder = selectionOrder.filter(i=>i !== item);
  }
  function setItemChecked(name, checked){
    if(!name) return;
    allCheckboxes.forEach(cb=>{
      if(cb.dataset.estItem === name) cb.checked = checked;
    });
  }
  function resetSelections(){
    allCheckboxes.forEach(cb=>{ cb.checked = false; });
  }
  function getOrderedItems(scopeController = null){
    const scopeCheckboxes = scopeController?.modalEl
      ? Array.from(scopeController.modalEl.querySelectorAll('input[type="checkbox"][data-est-item]'))
      : allCheckboxes;
    const checkedSet = new Set();
    scopeCheckboxes.forEach(cb=>{
      if(cb.checked) checkedSet.add(cb.dataset.estItem);
    });
    selectionOrder = selectionOrder.filter(item=>checkedSet.has(item));
    checkedSet.forEach(item=>{
      if(!selectionOrder.includes(item)) selectionOrder.push(item);
    });
    return selectionOrder.slice();
  }
  function getSearchState(key){
    if(!searchStates[key]){
      searchStates[key] = { index: [], layer: null, items: [], activeIndex: -1 };
    }
    return searchStates[key];
  }
  function buildSearchIndex(controller, cfg){
    if(!controller || !cfg) return [];
    const entries = [];
    const inputs = Array.from(controller.modalEl.querySelectorAll('input[type="checkbox"][data-est-id][data-est-item]'));
    inputs.forEach(cb=>{
      if(cb.disabled) return;
      const id = cb.dataset.estId;
      const label = cb.dataset.estItem;
      if(!id || !label) return;
      const meta = cfg.meta[id] || {};
      const aliases = (meta.aliases || []).slice();
      const groupId = getItemGroup(label, controller.key);
      const groupLabel = groupLabels[groupId] || (controller.key === 'genetica' ? 'Genética' : 'Laboratorio');
      const modalityLabel = getItemMeta(label, controller.key)?.modalityLabel || groupLabel;
      const areaLabel = cfg.categoryMode === 'modality' ? modalityLabel : groupLabel;
      entries.push({
        type: 'test',
        id,
        label,
        areaLabel,
        aliases,
        normLabel: normalizeSearchText(label),
        normAliases: aliases.map(normalizeSearchText).filter(Boolean)
      });
    });
    Object.keys(controller.packMap || {}).forEach(label=>{
      const meta = cfg.packMeta[label] || {};
      const aliases = (meta.aliases || []).slice();
      entries.push({
        type: 'package',
        id: label,
        label,
        areaLabel: 'Paquetes',
        aliases,
        normLabel: normalizeSearchText(label),
        normAliases: aliases.map(normalizeSearchText).filter(Boolean)
      });
    });
    return entries;
  }
  function getSearchMatches(controllerKey, query){
    const cfg = searchConfigMap[controllerKey];
    if(!cfg) return [];
    const term = normalizeSearchText(query);
    if(!term || term.length < cfg.config.minChars) return [];
    const termCompact = term.replace(/\s+/g, '');
    const state = getSearchState(controllerKey);
    const matches = [];
    state.index.forEach(entry=>{
      let score = 0;
      if(entry.normLabel.startsWith(term) || entry.normLabel.replace(/\s+/g, '').startsWith(termCompact)){
        score = Math.max(score, cfg.config.boosts.labelPrefix);
      }else if(entry.normLabel.includes(term)){
        score = Math.max(score, cfg.config.boosts.labelContains);
      }
      entry.normAliases.forEach(alias=>{
        if(alias.startsWith(term) || alias.replace(/\s+/g, '').startsWith(termCompact)){
          score = Math.max(score, cfg.config.boosts.aliasPrefix);
        }else if(alias.includes(term)){
          score = Math.max(score, cfg.config.boosts.aliasContains);
        }
      });
      if(score > 0){
        matches.push({ entry, score });
      }
    });
    matches.sort((a,b)=>{
      if(b.score !== a.score) return b.score - a.score;
      return a.entry.label.localeCompare(b.entry.label);
    });
    return matches.slice(0, cfg.config.maxResults).map(item=> item.entry);
  }
  function highlightSuggest(state, idx){
    state.activeIndex = idx;
    state.items.forEach((item, i)=>{
      if(item.node){
        item.node.classList.toggle('active', i === state.activeIndex);
        if(i === state.activeIndex){
          try{ item.node.scrollIntoView({ block:'nearest' }); }catch(_){}
        }
      }
    });
  }
  function hideSuggest(controllerKey){
    const state = getSearchState(controllerKey);
    if(state.layer){
      state.layer.remove();
      state.layer = null;
    }
    state.items = [];
    state.activeIndex = -1;
  }
  function applySearchSuggestion(entry, controller){
    if(!entry || !controller) return;
    hideSuggest(controller.key);
    if(entry.type === 'package'){
      controller.applyPackage(entry.label);
    }else{
      controller.applyItems([entry.label]);
      renderSelected();
    }
    if(controller.searchInput){
      controller.searchInput.value = '';
      controller.applyFilterChip('todos');
      controller.filterList('');
      controller.searchInput.focus();
    }
  }
  function showSuggest(list, anchor, controller){
    if(!list.length || !anchor) return;
    hideSuggest(controller.key);
    const state = getSearchState(controller.key);
    const rect = anchor.getBoundingClientRect();
    const box = document.createElement('div');
    box.className = 'grp-suggest';
    box.style.left = `${window.scrollX + rect.left}px`;
    box.style.top = `${window.scrollY + rect.bottom + 4}px`;
    box.style.width = `${rect.width}px`;
    state.items = [];
    list.forEach(entry=>{
      const it = document.createElement('div');
      it.className = 'item';
      const nm = document.createElement('div');
      nm.className = 'name';
      nm.textContent = entry.label;
      const ad = document.createElement('div');
      ad.className = 'addr';
      ad.textContent = entry.areaLabel || '';
      it.appendChild(nm);
      it.appendChild(ad);
      it.addEventListener('mousedown', (ev)=>{
        ev.preventDefault();
        ev.stopPropagation();
        applySearchSuggestion(entry, controller);
      });
      box.appendChild(it);
      state.items.push({ data: entry, node: it });
    });
    document.body.appendChild(box);
    state.layer = box;
    highlightSuggest(state, 0);
    const handler = (ev)=>{
      if(!state.layer) return;
      if(!box.contains(ev.target) && ev.target !== anchor){
        hideSuggest(controller.key);
        document.removeEventListener('mousedown', handler, true);
      }
    };
    document.addEventListener('mousedown', handler, true);
  }
  function setupTypeahead(controller){
    const cfg = searchConfigMap[controller?.key];
    const input = controller?.searchInput;
    if(!cfg || !input) return;
    const state = getSearchState(controller.key);
    state.index = buildSearchIndex(controller, cfg);
    input.addEventListener('input', ()=>{
      const results = getSearchMatches(controller.key, input.value);
      if(results.length){
        showSuggest(results, input, controller);
      }else{
        hideSuggest(controller.key);
      }
    });
    input.addEventListener('keydown', (ev)=>{
      if(!state.layer || !state.items.length) return;
      if(ev.key === 'ArrowDown'){
        ev.preventDefault();
        const next = (state.activeIndex + 1) % state.items.length;
        highlightSuggest(state, next);
      }else if(ev.key === 'ArrowUp'){
        ev.preventDefault();
        const next = (state.activeIndex - 1 + state.items.length) % state.items.length;
        highlightSuggest(state, next);
      }else if(ev.key === 'Enter'){
        ev.preventDefault();
        const item = state.items[state.activeIndex];
        if(item) applySearchSuggestion(item.data, controller);
      }else if(ev.key === 'Escape'){
        ev.preventDefault();
        hideSuggest(controller.key);
      }
    });
    controller.modalEl?.addEventListener('hidden.bs.modal', ()=> hideSuggest(controller.key));
  }
  function syncCheckboxesFromItems(items){
    const set = new Set(items);
    allCheckboxes.forEach(cb=>{
      cb.checked = set.has(cb.dataset.estItem);
    });
  }
  function ensureAreaGroup(label){
    const clean = (label || '').trim();
    if(!clean) return null;
    const key = `area-${clean.toLowerCase().replace(/[^a-z0-9]+/g, '-')}`;
    if(!groupLabels[key]) groupLabels[key] = clean;
    if(!groupOrder.includes(key)) groupOrder.push(key);
    return key;
  }
  function setAreaSelect(label){
    if(!areaSelect || !label) return;
    const target = label.trim().toLowerCase();
    const option = Array.from(areaSelect.options).find(opt => (opt.textContent || '').trim().toLowerCase() === target);
    if(option) areaSelect.value = option.value;
  }
  function renderSummary(items, controllerKey){
    if(!summaryWrap) return;
    const list = normalizeItems(items || []);
    if(summaryCount) summaryCount.textContent = `(${list.length})`;
    if(!list.length){
      summaryWrap.innerHTML = '<div class="est-summary-empty">Sin selección todavía</div>';
      return;
    }
    const listIndex = {};
    list.forEach((item, idx)=>{ listIndex[item] = idx; });
    const grouped = {};
    list.forEach(item=>{
      const groupId = getItemGroup(item, controllerKey) || 'otros';
      if(!grouped[groupId]) grouped[groupId] = [];
      grouped[groupId].push(item);
    });
    const order = groupOrder.slice();
    if(grouped.otros && !order.includes('otros')) order.push('otros');
    summaryWrap.innerHTML = order.filter(id=> grouped[id]?.length).map(id=>{
      const label = groupLabels[id] || 'Otros';
      const itemsSorted = grouped[id].slice().sort((a,b)=>{
        const oa = itemOrder[a];
        const ob = itemOrder[b];
        if(oa != null || ob != null) return (oa ?? 9999) - (ob ?? 9999);
        return listIndex[a] - listIndex[b];
      });
      return `<div class="est-summary-group"><div class="est-summary-group-ttl"><span class="est-summary-group-chip">${escapeHtml(label)} <span class="est-summary-group-count">${itemsSorted.length}</span></span></div><div class="est-summary-group-list">${itemsSorted.map(item=>{
        const safeBase = escapeHtml(item);
        const display = escapeHtml(buildDisplayName(item, controllerKey));
        return `<div class="est-summary-item"><span>${display}</span><button type="button" class="est-summary-remove" data-est-remove="${safeBase}" aria-label="Quitar ${safeBase}">&times;</button></div>`;
      }).join('')}</div></div>`;
    }).join('');
  }
  function setSelection(items, input, controllerKey){
    const list = normalizeItems(items || []);
    const controller = controllerKey ? controllerMap[controllerKey] : getControllerForArea(areaSelect?.value || '');
    const key = controller?.key || controllerKey;
    setSelectionOrder(list);
    syncCheckboxesFromItems(list);
    controller?.updateFlagVisibility?.();
    setInputItems(input, list, key);
    renderSummary(list, key);
    renderSelected();
  }
  function renderSelected(){
    const items = getOrderedItems();
    controllers.forEach(ctrl=> ctrl.updateFlagVisibility?.());
    controllers.forEach(ctrl=> ctrl.renderSelected(items));
  }
  function syncDuplicates(item, checked){
    allCheckboxes.forEach(cb=>{
      if(cb.dataset.estItem === item) cb.checked = checked;
    });
  }
  function setModalMode(mode, controller){
    modalMode = mode === 'edit' ? 'edit' : 'add';
    modalModeController = controller || null;
    const target = (controller || activeController)?.addBtn;
    if(target){
      if(orderReplacementState.active){
        target.textContent = 'Guardar reemplazo';
      }else{
        target.textContent = modalMode === 'edit' ? 'Actualizar orden' : 'Generar orden';
      }
    }
  }
  function getControllerForArea(area){
    const normalized = (area || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim().toLowerCase();
    const nameMap = {
      laboratorio:'lab',
      imagenologia:'imagenologia',
      'imagenología':'imagenologia',
      cardiologia:'cardiologia',
      'cardiología':'cardiologia',
      endoscopia:'endoscopia',
      'endoscopía':'endoscopia',
      patologia:'patologia',
      'patología':'patologia',
      genetica:'genetica',
      'genética':'genetica',
      funcionales:'funcionales',
      funcional:'funcionales'
    };
    const key = nameMap[normalized] || 'lab';
    return controllerMap[key] || controllerMap.lab;
  }
  function openControllerForInput(input){
    if(!input) return;
    const area = areaSelect?.value || '';
    const controller = getControllerForArea(area);
    if(!controller) return;
    activeInput = input;
    activeController = controller;
    setModalMode('add', controller);
    controller.open(input);
  }
  function scrollToOrderBlock(){
    const target = orderBlock || summaryContainer || summaryWrap || activeInput;
    if(!target) return;
    const rect = target.getBoundingClientRect();
    const top = window.scrollY + rect.top - 90;
    window.scrollTo({ top, behavior: 'smooth' });
    target.classList.add('est-order-focus');
    setTimeout(()=> target.classList.remove('est-order-focus'), 1200);
  }
  function setPrioritySelectValue(value){
    if(!prioritySelect) return;
    const wanted = clean(value);
    if(!wanted){
      prioritySelect.value = '';
      return;
    }
    const match = Array.from(prioritySelect.options).find((opt)=> clean(opt.value || opt.textContent || '').toLowerCase() === wanted.toLowerCase());
    if(match){
      prioritySelect.value = match.value;
    }else{
      prioritySelect.value = '';
    }
  }
  async function startOrderReplacementFromCard(card){
    if(!card) return;
    const status = clean(card.getAttribute('data-order-status') || '').toLowerCase();
    if(status === 'replaced'){
      setOrderFeedback('Esta orden ya fue reemplazada y no puede reemplazarse de nuevo en esta fase.', 'muted');
      return;
    }
    const hasResult = card.querySelector('[data-est-order-view-result]') != null
      || clean(card.getAttribute('data-result-document-ref') || '') !== '';
    if(hasResult){
      setOrderFeedback('No se puede reemplazar una orden con resultado cargado.', 'muted');
      return;
    }
    const docUuid = clean(card.getAttribute('data-document-uuid') || card.getAttribute('data-est-document-uuid'));
    const docId = clean(card.getAttribute('data-document-id') || card.getAttribute('data-est-document-id'));
    const docRef = docUuid || docId;
    if(!docRef){
      setOrderFeedback('No se pudo resolver la orden para reemplazo.', 'error');
      return;
    }
    try{
      const detail = await fetchOrderDocumentDetail(docRef);
      const payload = (detail && typeof detail.payload === 'object') ? detail.payload : {};
      const studies = extractDiagnosticItemsFromPayload(payload);
      if(!studies.length){
        setOrderFeedback('La orden original no contiene estudios para precargar.', 'error');
        return;
      }
      const area = resolveOrderAreaLabel(detail.documentType, payload);
      const controller = getControllerForArea(area);
      if(!controller){
        setOrderFeedback('No se pudo abrir el selector para reemplazo en este entorno.', 'error');
        return;
      }
      setAreaSelect(area);
      const input = activeInput || openInputs[0] || orderBlock?.querySelector('[data-est-open-modal]');
      if(!input){
        setOrderFeedback('No se encontró el input de selección para reemplazar la orden.', 'error');
        return;
      }
      activeInput = input;
      activeController = controller;
      clearOrderReplacementState();
      orderReplacementState.active = true;
      orderReplacementState.sourceRef = docRef;
      orderReplacementState.sourceId = clean(detail.id || '');
      orderReplacementState.sourceUuid = clean(detail.uuid || '');
      orderReplacementState.reason = '';
      setSelection(studies, input, controller.key);
      setPrioritySelectValue(payload?.priority || '');
      if(indicationTextarea){
        indicationTextarea.value = clean(payload?.indication || '');
      }
      setModalMode('add', controller);
      controller.open(input);
      setOrderFeedback('Reemplazo activo: ajusta la orden y guarda la nueva versión.', 'muted');
    }catch(err){
      setOrderFeedback(clean(err?.message || 'No se pudo preparar el reemplazo de la orden.'), 'error');
    }
  }
  async function startOrderReplacementByRef(orderRef){
    const docRef = clean(orderRef);
    if(!docRef){
      setOrderFeedback('No se pudo resolver la orden para reemplazo.', 'error');
      return;
    }
    try{
      const detail = await fetchOrderDocumentDetail(docRef);
      const payload = (detail && typeof detail.payload === 'object') ? detail.payload : {};
      const lifecycle = resolveDiagnosticOrderLifecycle(payload);
      if(clean(lifecycle.status || '').toLowerCase() === 'replaced'){
        setOrderFeedback('Esta orden ya fue reemplazada y no puede reemplazarse de nuevo en esta fase.', 'muted');
        return;
      }
      const patientId = resolveOrderPatientId();
      if(patientId){
        try{
          if(!lastOrderResultsIndex || typeof lastOrderResultsIndex.get !== 'function' || lastOrderResultsIndex.size === 0){
            lastOrderResultsIndex = await buildOrderResultsIndex(patientId);
          }
        }catch(_){}
      }
      const hasResultRef = resolveResultRefForOrderRefs(clean(detail.id || ''), clean(detail.uuid || ''), '');
      if(hasResultRef){
        setOrderFeedback('No se puede reemplazar una orden con resultado cargado.', 'muted');
        return;
      }
      const studies = extractDiagnosticItemsFromPayload(payload);
      if(!studies.length){
        setOrderFeedback('La orden original no contiene estudios para precargar.', 'error');
        return;
      }
      const area = resolveOrderAreaLabel(detail.documentType, payload);
      const controller = getControllerForArea(area);
      if(!controller){
        setOrderFeedback('No se pudo abrir el selector para reemplazo en este entorno.', 'error');
        return;
      }
      setAreaSelect(area);
      const input = activeInput || openInputs[0] || orderBlock?.querySelector('[data-est-open-modal]');
      if(!input){
        setOrderFeedback('No se encontró el input de selección para reemplazar la orden.', 'error');
        return;
      }
      activeInput = input;
      activeController = controller;
      clearOrderReplacementState();
      orderReplacementState.active = true;
      orderReplacementState.sourceRef = docRef;
      orderReplacementState.sourceId = clean(detail.id || '');
      orderReplacementState.sourceUuid = clean(detail.uuid || '');
      orderReplacementState.reason = '';
      setSelection(studies, input, controller.key);
      setPrioritySelectValue(payload?.priority || '');
      if(indicationTextarea){
        indicationTextarea.value = clean(payload?.indication || '');
      }
      setModalMode('add', controller);
      controller.open(input);
      setOrderFeedback('Reemplazo activo: ajusta la orden y guarda la nueva versión.', 'muted');
    }catch(err){
      setOrderFeedback(clean(err?.message || 'No se pudo preparar el reemplazo de la orden.'), 'error');
    }
  }
  function ensureEstudiosWorkbenchForDiagnosticAction(){
    try{
      if(typeof showClinicalTab === 'function'){
        showClinicalTab(clinicalTabTargets.estudios);
      }
    }catch(_){}
  }
  window.mxmedOpenDiagnosticOrderResultModal = (orderRef, _opts = {})=>{
    ensureEstudiosWorkbenchForDiagnosticAction();
    return openOrderResultModal(orderRef);
  };
  window.mxmedStartDiagnosticOrderReplacement = (orderRef, _opts = {})=>{
    ensureEstudiosWorkbenchForDiagnosticAction();
    return startOrderReplacementByRef(orderRef);
  };
  function createController(cfg){
    const modalEl = document.getElementById(cfg.id);
    if(!modalEl) return null;
    const modal = new bootstrap.Modal(modalEl);
    const searchInput = modalEl.querySelector('[data-est-lab-search]');
    const groupButtons = Array.from(modalEl.querySelectorAll('[data-est-group]'));
    const modalityButtons = Array.from(modalEl.querySelectorAll('[data-est-modality]'));
    const modalityPanels = Array.from(modalEl.querySelectorAll('[data-est-modality-panel]'));
    const groupPanes = Array.from(modalEl.querySelectorAll('[data-est-group-pane]'));
    const flagButtons = Array.from(modalEl.querySelectorAll('[data-est-flag]'));
    const filterChips = Array.from(modalEl.querySelectorAll('.est-chip-filter'));
    const pickButtons = Array.from(modalEl.querySelectorAll('[data-est-lab-pick]'));
    const packButtons = Array.from(modalEl.querySelectorAll('[data-est-lab-pack]'));
    const favBox = modalEl.querySelector('.est-lab-fav');
    const favFre = modalEl.querySelector('[data-est-fav="frecuentes"]');
    const favPack = modalEl.querySelector('[data-est-fav="paquetes"]');
    const groupCol = modalEl.querySelector('.est-lab-groups')?.closest('.col-md-3');
    const accordionCol = cfg.accordionId ? modalEl.querySelector(`#${cfg.accordionId}`)?.closest('.col-md-9') : null;
    const panelCol = cfg.panelSelector ? modalEl.querySelector(cfg.panelSelector)?.closest('.col-md-9') : null;
    const selectedWrap = modalEl.querySelector('[data-est-selected]');
    const selectedPanel = selectedWrap?.closest('.est-selected-panel');
    const modalCount = modalEl.querySelector('[data-est-modal-count]');
    const addBtn = modalEl.querySelector('.modal-footer .btn-primary');
    let clearSelectionBtn = selectedPanel?.querySelector('[data-action="est-clear-selection-modal"]');
    if(selectedPanel && !clearSelectionBtn){
      clearSelectionBtn = document.createElement('button');
      clearSelectionBtn.type = 'button';
      clearSelectionBtn.className = 'btn btn-outline-secondary btn-sm mt-2';
      clearSelectionBtn.setAttribute('data-action', 'est-clear-selection-modal');
      clearSelectionBtn.textContent = 'Limpiar selección';
      selectedPanel.appendChild(clearSelectionBtn);
    }
    const controller = {
      key: cfg.key,
      modal,
      modalEl,
      addBtn,
      groupButtons,
      modalityButtons,
      modalityPanels,
      groupPanes,
      flagButtons,
      filterChips,
      searchInput,
      pickButtons,
      packButtons,
      favBox,
      favFre,
      favPack,
      groupCol,
      accordionCol,
      panelCol,
      selectedWrap,
      clearSelectionBtn,
      modalCount,
      pickMap: cfg.pickMap || {},
      packMap: cfg.packMap || {},
      packFlagMap: cfg.packFlagMap || {}
    };
    const flagButtonMap = flagButtons.reduce((acc, btn)=>{
      const id = btn.dataset.estFlag;
      if(id) acc[id] = btn;
      return acc;
    }, {});
    controller.applyItems = function(items){
      addToOrderList(items);
      items.forEach(item=> setItemChecked(item, true));
    };
    controller.applyPick = function(key){
      const items = controller.pickMap[key] || [];
      controller.applyItems(items);
      renderSelected();
    };
    controller.applyPackage = function(key){
      const items = controller.packMap[key] || [];
      controller.applyItems(items);
      controller.updateFlagVisibility?.();
      const defaults = controller.packFlagMap[key] || [];
      defaults.forEach(flagId=>{
        const flagBtn = flagButtonMap[flagId];
        if(!flagBtn || flagBtn.classList.contains('d-none') || activeFlags.has(flagId)) return;
        flagBtn.click();
      });
      renderSelected();
    };
    controller.applyFilterChip = function(type){
      filterChips.forEach(ch=> ch.classList.toggle('active', ch.dataset.estFilter === type));
      const contentCol = panelCol || accordionCol;
      if(!favBox){
        groupCol?.classList.remove('d-none');
        contentCol?.classList.remove('d-none');
        return;
      }
      favBox.classList.remove('d-none');
      if(type === 'frecuentes'){
        favFre?.classList.remove('d-none');
        favPack?.classList.add('d-none');
        groupCol?.classList.add('d-none');
        contentCol?.classList.add('d-none');
      }else if(type === 'paquetes'){
        favFre?.classList.add('d-none');
        favPack?.classList.remove('d-none');
        groupCol?.classList.add('d-none');
        contentCol?.classList.add('d-none');
      }else{
        favFre?.classList.remove('d-none');
        favPack?.classList.remove('d-none');
        groupCol?.classList.remove('d-none');
        contentCol?.classList.remove('d-none');
      }
    };
    controller.filterList = function(term){
      const query = (term || '').trim().toLowerCase();
      const localCheckboxes = Array.from(modalEl.querySelectorAll('input[type="checkbox"][data-est-item]'));
      const labels = localCheckboxes.map(cb=> cb.closest('label')).filter(Boolean);
      labels.forEach(label=>{
        const text = (label.textContent || '').toLowerCase();
        label.classList.toggle('d-none', query && !text.includes(query));
      });
      modalEl.querySelectorAll('.est-lab-grid').forEach(grid=>{
        const hasVisible = Array.from(grid.querySelectorAll('label')).some(label=>!label.classList.contains('d-none'));
        grid.classList.toggle('d-none', !hasVisible);
        const head = grid.previousElementSibling;
        if(head && head.classList.contains('est-lab-sub')){
          head.classList.toggle('d-none', !hasVisible);
        }
      });
      modalEl.querySelectorAll('.accordion-item').forEach(item=>{
        const hasVisible = item.querySelectorAll('.est-lab-grid label:not(.d-none)').length > 0;
        item.classList.toggle('d-none', !hasVisible);
        if(query){
          const collapse = item.querySelector('.accordion-collapse');
          if(collapse) collapse.classList.add('show');
        }
      });
      groupPanes.forEach(pane=>{
        const hasVisible = pane.querySelectorAll('.est-lab-grid label:not(.d-none)').length > 0;
        pane.classList.toggle('d-none', !hasVisible);
      });
      if(modalityPanels.length){
        if(query){
          modalityPanels.forEach(panel=> panel.classList.remove('d-none'));
        } else if(controller.activeModality){
          modalityPanels.forEach(panel=>{
            panel.classList.toggle('d-none', panel.dataset.estModalityPanel !== controller.activeModality);
          });
        }
      }
      if(query){
        const contentCol = panelCol || accordionCol;
        groupCol?.classList.remove('d-none');
        contentCol?.classList.remove('d-none');
        favBox?.classList.remove('d-none');
        favFre?.classList.remove('d-none');
        favPack?.classList.remove('d-none');
        filterChips.forEach(ch=> ch.classList.remove('active'));
      }
    };
    controller.renderSelected = function(items){
      const hasSelection = Array.isArray(items) && items.length > 0;
      if(clearSelectionBtn){
        clearSelectionBtn.classList.toggle('d-none', !hasSelection);
        clearSelectionBtn.disabled = !hasSelection;
      }
      if(addBtn && !canonicalOrderSubmitting){
        addBtn.disabled = !hasSelection;
      }
      if(modalCount) modalCount.textContent = `(${items.length})`;
      if(!selectedWrap) return;
      if(!items.length){
        selectedWrap.innerHTML = '<span class="text-muted small">Sin selección</span>';
        return;
      }
      selectedWrap.innerHTML = items.map(item=>{
        const safeBase = escapeHtml(item);
        const display = escapeHtml(buildDisplayName(item, controller.key));
        return `<span class="est-chip">${display}<button type="button" class="est-chip-x" data-est-remove="${safeBase}" aria-label="Quitar ${safeBase}">&times;</button></span>`;
      }).join('');
    };
    controller.updateFlagVisibility = function(){
      if(!flagButtons.length) return;
      const visibilityMap = controller.key === 'lab'
        ? LAB_FLAG_VISIBILITY
        : (controller.key === 'endoscopia'
          ? ENDOSCOPY_FLAG_VISIBILITY
          : (controller.key === 'patologia'
            ? PATHOLOGY_FLAG_VISIBILITY
            : (controller.key === 'genetica'
              ? GENETICS_FLAG_VISIBILITY
              : (controller.key === 'funcionales'
                ? FUNCTIONAL_FLAG_VISIBILITY
                : (controller.key === 'cardiologia'
                  ? CARDIOLOGY_FLAG_VISIBILITY
                  : (controller.key === 'imagenologia' ? IMAGING_FLAG_VISIBILITY : null))))));
      if(!visibilityMap) return;
      const selectedIds = Array.from(modalEl.querySelectorAll('input[type="checkbox"][data-est-id]'))
        .filter(cb=> cb.checked)
        .map(cb=> cb.dataset.estId)
        .filter(Boolean);
      const visibleFlags = new Set(visibilityMap.generalFlags);
      selectedIds.forEach(id=>{
        (visibilityMap.applicability[id] || []).forEach(flag=> visibleFlags.add(flag));
      });
      flagButtons.forEach(btn=>{
        const id = btn.dataset.estFlag;
        const show = visibleFlags.has(id);
        btn.classList.toggle('d-none', !show);
        if(!show && activeFlags.has(id)){
          activeFlags.delete(id);
          btn.classList.remove('active');
        }
      });
    };
    controller.open = function(input){
      activeController = controller;
      activeInput = input;
      resetSelections();
      if(flagButtons.length){
        activeFlags.clear();
        flagButtons.forEach(btn=> btn.classList.remove('active'));
      }
      const parsed = getInputItems(activeInput);
      setSelectionOrder(parsed);
      parsed.forEach(item=> setItemChecked(item, true));
      if(searchInput) searchInput.value = '';
      controller.applyFilterChip('todos');
      controller.filterList('');
      renderSelected();
      modal.show();
    };
    if(modalityButtons.length){
      modalityButtons.forEach(btn=>{
        const id = btn.dataset.estModality;
        if(id && !modalityLabelMap[id]) modalityLabelMap[id] = (btn.textContent || '').trim();
      });
      controller.activeModality = modalityButtons.find(btn=>btn.classList.contains('active'))?.dataset.estModality || modalityButtons[0].dataset.estModality;
      controller.setActiveModality = (id)=>{
        if(!id) return;
        controller.activeModality = id;
        modalityButtons.forEach(btn=> btn.classList.toggle('active', btn.dataset.estModality === id));
        modalityPanels.forEach(panel=> panel.classList.toggle('d-none', panel.dataset.estModalityPanel !== id));
      };
      controller.setActiveModality(controller.activeModality);
      modalityButtons.forEach(btn=>{
        btn.addEventListener('click', ()=> controller.setActiveModality(btn.dataset.estModality));
      });
    }
    if(flagButtons.length){
      const flagGroups = {
        diagnostic_type: ['diagnostic','therapeutic'],
        priority: ['priority_routine','priority_urgent','priority_stat'],
        report_type: ['with_report','without_report'],
        contrast: ['with_contrast','without_contrast'],
        imaging_contrast: ['contrast_with','contrast_without','contrast_with_without'],
        laterality: ['right','left','bilateral'],
        imaging_laterality: ['laterality_right','laterality_left','laterality_bilateral'],
        sedation_type: ['with_sedation','without_sedation'],
        stress_state: ['rest','stress'],
        age_group: ['adult','pediatric'],
        care_setting: ['ambulatory','in_hospital'],
        holter_duration: ['holter_24h','holter_48h','holter_72h','holter_7d'],
        mapa_duration: ['mapa_24h','mapa_48h'],
        exercise_type: ['exercise_treadmill','exercise_bike'],
        stress_type: ['stress_exercise','stress_pharmacologic'],
        pharm_type: ['pharm_dobutamine','pharm_dipyridamole','pharm_adenosine'],
        rx_primary: ['rx_pa','rx_ap','rx_pa_lateral'],
        rx_lateral_combo: ['rx_lateral','rx_pa_lateral'],
        mammo_type: ['mammo_screening','mammo_diagnostic'],
        us_flow: ['us_arterial','us_venous'],
        vascular_flow: ['vascular_arterial','vascular_venous'],
        ct_phase: ['ct_phase_arterial','ct_phase_venous','ct_phase_delayed'],
        dexa_region: ['dexa_spine','dexa_hip','dexa_whole_body'],
        ihc_type: ['ihc_single_marker','ihc_panel','ihc_breast_panel','ihc_lung_panel','ihc_lymphoma_panel'],
        context_timing: ['context_prenatal','context_postnatal'],
        sample_type: ['sample_blood','sample_saliva','sample_tissue','sample_ffpe','sample_cvs','sample_amnio'],
        proband_group: ['trio','duo','proband_only'],
        limb_group: ['upper_limbs','lower_limbs']
      };
      const groupLookup = Object.values(flagGroups).reduce((acc, group)=>{
        group.forEach(id=>{
          if(!acc[id]) acc[id] = [];
          acc[id].push(group);
        });
        return acc;
      }, {});
      flagButtons.forEach(btn=>{
        btn.addEventListener('click', ()=>{
          const id = btn.dataset.estFlag;
          if(!id) return;
          const groups = groupLookup[id] || [];
          if(groups.length){
            const toClear = new Set();
            groups.forEach(group=>{
              group.forEach(flagId=>{
                if(flagId !== id) toClear.add(flagId);
              });
            });
            toClear.forEach(flagId=>{
              activeFlags.delete(flagId);
              flagButtons.forEach(b=>{
                if(b.dataset.estFlag === flagId) b.classList.remove('active');
              });
            });
          }
          if(activeFlags.has(id)){
            activeFlags.delete(id);
            btn.classList.remove('active');
          }else{
            activeFlags.add(id);
            btn.classList.add('active');
          }
          renderSelected();
        });
      });
    }
    pickButtons.forEach(btn=>{
      btn.addEventListener('click', ()=>{
        const key = btn.getAttribute('data-est-lab-pick');
        controller.applyPick(key);
      });
    });
    packButtons.forEach(btn=>{
      btn.addEventListener('click', ()=>{
        const key = btn.getAttribute('data-est-lab-pack');
        controller.applyPackage(key);
      });
    });
    groupButtons.forEach(btn=>{
      btn.addEventListener('click', ()=>{
        groupButtons.forEach(b=> b.classList.remove('active'));
        btn.classList.add('active');
        const group = btn.getAttribute('data-est-group');
        const pane = modalEl.querySelector(`[data-est-group-pane="${group}"]`);
        const collapse = pane?.querySelector('.accordion-collapse');
        if(collapse){ try{ new bootstrap.Collapse(collapse, {toggle:true}); }catch(_){ } }
        pane?.scrollIntoView({behavior:'smooth', block:'start'});
      });
    });
    filterChips.forEach(ch=> ch.addEventListener('click', ()=> controller.applyFilterChip(ch.dataset.estFilter)));
    searchInput?.addEventListener('input', (e)=> controller.filterList(e.target.value));
    selectedWrap?.addEventListener('click', (e)=>{
      const btn = e.target.closest('[data-est-remove]');
      if(!btn) return;
      const item = btn.getAttribute('data-est-remove');
      removeFromOrder(item);
      setItemChecked(item, false);
      renderSelected();
    });
    clearSelectionBtn?.addEventListener('click', ()=>{
      const selected = getOrderedItems(controller);
      if(!selected.length){
        setOrderFeedback('No hay estudios seleccionados para limpiar.', 'muted');
        return;
      }
      const confirmed = window.confirm('¿Seguro que deseas limpiar todos los estudios seleccionados?');
      if(!confirmed) return;
      const input = activeInput || openInputs[0];
      setSelection([], input, controller.key);
      activeFlags.clear();
      controller.flagButtons?.forEach((btn)=> btn.classList.remove('active'));
      controller.updateFlagVisibility?.();
      setOrderFeedback('Selección limpiada.', 'muted');
    });
    addBtn?.addEventListener('click', ()=>{
      const items = getOrderedItems(controller);
      if(!items.length){
        setOrderFeedback('Selecciona al menos un estudio para generar la orden.', 'error');
        return;
      }
      const input = activeInput || openInputs[0];
      if(input) setSelection(items, input, controller.key);
      const controllerAreaMap = {
        lab: 'Laboratorio',
        imagenologia: 'Imagenología'
      };
      const run = async ()=>{
        if(canonicalOrderSubmitting) return;
        let savedOk = false;
        canonicalOrderSubmitting = true;
        if(addBtn){
          addBtn.disabled = true;
        }
        try{
          const saveResult = await saveCanonicalStudyOrder({
            items,
            controllerKey: controller.key,
            area: controllerAreaMap[controller.key] || areaSelect?.value || '',
            replacement: orderReplacementState.active ? {
              sourceRef: orderReplacementState.sourceRef,
              reason: orderReplacementState.reason
            } : null
          });
          savedOk = saveResult && saveResult.ok === true;
          if(savedOk){
            if(modalMode !== 'edit'){
              const nextInput = activeInput || openInputs[0];
              if(nextInput){
                setSelection([], nextInput, controller.key);
              }else{
                setSelectionOrder([]);
                resetSelections();
                renderSelected();
              }
              activeFlags.clear();
              controller.flagButtons?.forEach((btn)=> btn.classList.remove('active'));
              controller.updateFlagVisibility?.();
              if(controller.searchInput){
                controller.searchInput.value = '';
                controller.applyFilterChip('todos');
                controller.filterList('');
                if(searchConfigMap[controller.key]) hideSuggest(controller.key);
              }
            }
            modal.hide();
            setTimeout(scrollToOrderBlock, 200);
          }
        }finally{
          canonicalOrderSubmitting = false;
          renderSelected();
          if(!savedOk){
            controller.updateFlagVisibility?.();
          }
        }
      };
      run();
    });
    modalEl?.addEventListener('hidden.bs.modal', ()=>{
      clearOrderReplacementState();
      setModalMode('add', controller);
    });
    controller.applyFilterChip('todos');
    controller.filterList('');
    return controller;
  }

  modalConfigs.forEach(cfg=>{
    const ctrl = createController(cfg);
    if(ctrl){
      controllers.push(ctrl);
      controllerMap[cfg.key] = ctrl;
    }
  });
  if(!controllers.length) return;

  controllers.forEach(ctrl=>{
    ctrl.groupPanes.forEach(pane=>{
      const id = pane.dataset.estGroupPane;
      if(!id) return;
      const label = pane.dataset.estGroupLabel
        || pane.querySelector('.accordion-header .accordion-button')?.textContent?.trim()
        || pane.querySelector('.est-lab-sub')?.textContent?.trim()
        || id;
      if(!groupLabels[id]) groupLabels[id] = label;
      if(!groupOrder.includes(id)) groupOrder.push(id);
      pane.querySelectorAll('input[type="checkbox"][data-est-item]').forEach(cb=>{
        const name = cb.dataset.estItem;
        if(name){
          if(!itemGroupMap[name]) itemGroupMap[name] = {};
          if(!itemGroupMap[name][ctrl.key]) itemGroupMap[name][ctrl.key] = id;
          if(!itemGroupMap[name].default) itemGroupMap[name].default = id;
        }
        if(name && itemOrder[name] == null) itemOrder[name] = orderIndex++;
        const modalityPanel = cb.closest('[data-est-modality-panel]');
        const modalityId = modalityPanel?.dataset.estModalityPanel;
        if(name && modalityId){
          if(!itemMetaMap[name]) itemMetaMap[name] = {};
          if(!itemMetaMap[name][ctrl.key]){
            const meta = { modality: modalityId, modalityLabel: modalityLabelMap[modalityId] || '' };
            itemMetaMap[name][ctrl.key] = meta;
            if(!itemMetaMap[name].default) itemMetaMap[name].default = meta;
          }
        }
      });
    });
  });
  Object.keys(searchConfigMap).forEach(key=>{
    const controller = controllerMap[key];
    if(controller) setupTypeahead(controller);
  });

  openInputs.forEach(input=>{
    input.addEventListener('focus', ()=> openControllerForInput(input));
    input.addEventListener('click', ()=> openControllerForInput(input));
    input.addEventListener('input', ()=>{
      input.dataset.estRaw = '';
      setSelection(parseInputValue(input.value), input);
    });
  });

  allCheckboxes.forEach(cb=>{
    cb.addEventListener('change', ()=>{
      const item = cb.dataset.estItem;
      const owner = controllers.find(ctrl=> ctrl.modalEl.contains(cb));
      syncDuplicates(item, cb.checked);
      if(cb.checked){
        addToOrder(item);
      }else{
        removeFromOrder(item);
      }
      renderSelected();
      if(owner?.searchInput && owner.searchInput.value.trim()){
        owner.searchInput.value = '';
        owner.applyFilterChip('todos');
        owner.filterList('');
        if(owner?.key && searchConfigMap[owner.key]) hideSuggest(owner.key);
      }
    });
  });

  summaryWrap?.addEventListener('click', (e)=>{
    const btn = e.target.closest('[data-est-remove]');
    if(!btn) return;
    const item = btn.getAttribute('data-est-remove');
    const input = activeInput || openInputs[0];
    const next = getInputItems(input).filter(i=>i !== item);
    setSelection(next, input);
  });
  summaryEdit?.addEventListener('click', ()=>{
    const input = activeInput || openInputs[0];
    if(input) openControllerForInput(input);
  });
  summaryClear?.addEventListener('click', ()=>{
    const input = activeInput || openInputs[0];
    const selected = getOrderedItems();
    if(selected.length){
      const confirmed = window.confirm('¿Limpiar la selección eliminará los estudios marcados y no podrás recuperarlos. ¿Deseas continuar?');
      if(!confirmed) return;
    }
    setSelection([], input);
    setOrderFeedback('');
  });
  areaSelect?.addEventListener('change', ()=>{
    const input = activeInput || openInputs[0];
    setSelection([], input);
    activeFlags.clear();
    controllers.forEach(ctrl=>{
      ctrl.flagButtons?.forEach((btn)=> btn.classList.remove('active'));
      ctrl.updateFlagVisibility?.();
    });
    setOrderFeedback('');
  });

  orderList?.addEventListener('click', (e)=>{
    const btn = e.target.closest('[data-est-order-upload-result]');
    if(!btn) return;
    const card = btn.closest('.est-order-card');
    if(!card) return;
    const docUuid = clean(card.getAttribute('data-document-uuid') || card.getAttribute('data-est-document-uuid'));
    const docId = clean(card.getAttribute('data-document-id') || card.getAttribute('data-est-document-id'));
    const docRef = docUuid || docId;
    if(!docRef){
      setOrderFeedback('No se pudo resolver la orden para ingresar resultado.', 'error');
      return;
    }
    const resultRef = clean(card.getAttribute('data-result-document-ref') || '');
    openOrderResultModal(docRef, { resultRef });
  });
  orderList?.addEventListener('click', (e)=>{
    const btn = e.target.closest('[data-est-order-replace]');
    if(!btn) return;
    const card = btn.closest('.est-order-card');
    if(!card) return;
    startOrderReplacementFromCard(card);
  });
  orderList?.addEventListener('click', (e)=>{
    const btn = e.target.closest('[data-est-order-view-result]');
    if(!btn) return;
    const card = btn.closest('.est-order-card');
    if(!card) return;
    const resultRef = resolveResultRefForOrderCard(card);
    if(!resultRef){
      setOrderFeedback('Esta orden no tiene resultado asociado todavía.', 'muted');
      return;
    }
    openOrderDetailModal(resultRef);
  });
  orderList?.addEventListener('click', (e)=>{
    const btn = e.target.closest('[data-est-order-view-replaced-by]');
    if(!btn) return;
    const card = btn.closest('.est-order-card');
    if(!card) return;
    const ref = clean(card.getAttribute('data-replaced-by-document-ref') || '');
    if(!ref){
      setOrderFeedback('No se encontró la orden reemplazante.', 'muted');
      return;
    }
    openOrderDetailModal(ref);
  });
  orderList?.addEventListener('click', (e)=>{
    const btn = e.target.closest('[data-est-order-view-replacement-source]');
    if(!btn) return;
    const card = btn.closest('.est-order-card');
    if(!card) return;
    const ref = clean(card.getAttribute('data-replacement-source-document-ref') || '');
    if(!ref){
      setOrderFeedback('No se encontró la orden previa asociada.', 'muted');
      return;
    }
    openOrderDetailModal(ref);
  });
  document.addEventListener('click', (e)=>{
    const btn = e.target.closest('[data-order-detail-open-related-order]');
    if(!btn) return;
    const orderRef = clean(btn.getAttribute('data-order-detail-open-related-order') || '');
    if(!orderRef){
      setOrderFeedback('No se pudo resolver la orden original asociada.', 'muted');
      return;
    }
    openOrderDetailModal(orderRef);
  });
  orderList?.addEventListener('click', (e)=>{
    const btn = e.target.closest('[data-est-order-delete]');
    if(!btn) return;
    const card = btn.closest('.est-order-card');
    if(!card) return;
    if(isCanonicalOrderCard(card)){
      setOrderFeedback('La eliminación de órdenes canónicas no está disponible en esta fase.', 'muted');
      return;
    }
    if(window.confirm('¿Eliminar esta orden? Esta acción no se puede deshacer.')){
      card.remove();
    }
  });
  orderList?.addEventListener('click', (e)=>{
    const btn = e.target.closest('[data-est-order-edit]');
    if(!btn) return;
    const card = btn.closest('.est-order-card');
    if(!card) return;
    if(isCanonicalOrderCard(card)){
      const docUuid = clean(card.getAttribute('data-document-uuid') || card.getAttribute('data-est-document-uuid'));
      const docId = clean(card.getAttribute('data-document-id') || card.getAttribute('data-est-document-id'));
      const docRef = docUuid || docId;
      if(docRef){
        openOrderDetailModal(docRef);
      }else{
        setOrderFeedback('No se encontró identificador para consultar el detalle de la orden.', 'muted');
      }
      return;
    }
    const items = parseInputValue(card.getAttribute('data-est-order-items') || '');
    const area = (card.getAttribute('data-est-order-area') || '').trim();
    const input = orderBlock?.querySelector('[data-est-open-modal]') || openInputs[0];
    if(!input) return;
    activeInput = input;
    const controller = getControllerForArea(area);
    if(area) setAreaSelect(area);
    if(area && !/laboratorio/i.test(area)){
      const key = ensureAreaGroup(area);
      const controllerKey = controller?.key;
      items.forEach(item=>{
        if(key){
          if(!itemGroupMap[item]) itemGroupMap[item] = {};
          if(controllerKey && !itemGroupMap[item][controllerKey]) itemGroupMap[item][controllerKey] = key;
          if(!itemGroupMap[item].default) itemGroupMap[item].default = key;
        }
        if(itemOrder[item] == null) itemOrder[item] = orderIndex++;
      });
    }
    setSelection(items, input, controller?.key);
    setModalMode('edit', controller);
    controller.open(input);
  });

  window.mxResetEstudios = ()=>{
    try{
      const input = openInputs[0];
      resetSelections();
      if(input){
        const defVal = input.defaultValue || '';
        input.dataset.estRaw = '';
        input.value = defVal;
        setSelection(parseInputValue(defVal), input);
      }
      if(areaSelect) areaSelect.selectedIndex = 0;
      controllers.forEach(ctrl=>{
        if(ctrl.searchInput) ctrl.searchInput.value = '';
        ctrl.applyFilterChip('todos');
        ctrl.filterList('');
        ctrl.flagButtons?.forEach(btn=> btn.classList.remove('active'));
        const inst = window.bootstrap?.Modal?.getInstance ? window.bootstrap.Modal.getInstance(ctrl.modalEl) : null;
        inst?.hide();
      });
      activeFlags.clear();
      if(orderList){
        orderList.innerHTML = '';
      }
      activeController = null;
      activeInput = null;
      modalMode = 'add';
      modalModeController = null;
      requestRefreshCanonicalOrdersList();
    }catch(_){ }
  };

  renderSelected();
  if(openInputs[0]) setSelection(getInputItems(openInputs[0]), openInputs[0]);
  requestRefreshCanonicalOrdersList();
  window.addEventListener('expediente:patient_changed', requestRefreshCanonicalOrdersList);
  window.addEventListener('expediente:patient-changed', requestRefreshCanonicalOrdersList);
  window.addEventListener('patient:selected', requestRefreshCanonicalOrdersList);
})();


(function(){
  const desc = document.querySelector('[data-est-section-desc-target]');
  const tabs = Array.from(document.querySelectorAll('.est-section-tab[data-est-section]'));
  const sections = Array.from(document.querySelectorAll('[data-est-section-block]'));
  if(!desc || !tabs.length || !sections.length) return;
  const show = (key)=>{
    if(!key) return;
    const active = tabs.find(tab=> tab.dataset.estSection === key);
    tabs.forEach(tab=> tab.classList.toggle('active', tab === active));
    sections.forEach(section=> section.classList.toggle('d-none', section.dataset.estSectionBlock !== key));
    if(active?.dataset.estSectionDesc){
      desc.textContent = active.dataset.estSectionDesc;
    }
  };
  tabs.forEach(tab=>{
    tab.addEventListener('click', ()=> show(tab.dataset.estSection));
  });
  show(tabs[0].dataset.estSection);
})();

(function initActividadClinicaCanonicalUpload(){
  const studiesPane = document.getElementById('t-estudios');
  if(!studiesPane || studiesPane.dataset.acDocUploadInit === '1') return;
  studiesPane.dataset.acDocUploadInit = '1';

  const fileInput = studiesPane.querySelector('[data-role="ac-doc-file"]');
  const titleInput = studiesPane.querySelector('[data-role="ac-doc-title"]');
  const summaryInput = studiesPane.querySelector('[data-role="ac-doc-summary"]');
  const eventDatetimeInput = studiesPane.querySelector('[data-role="ac-doc-event-datetime"]');
  const mediaTagSelect = studiesPane.querySelector('[data-role="ac-doc-media-tag"]');
  const noteCaptureInput = studiesPane.querySelector('[data-role="ac-doc-note-capture"]');
  const intentQuestionEl = studiesPane.querySelector('[data-role="ac-doc-intent-question"]');
  const intentPicker = studiesPane.querySelector('[data-role="ac-doc-intent-picker"]');
  const intentButtons = Array.from(studiesPane.querySelectorAll('[data-ac-doc-intent-btn]'));
  const categoryInput = studiesPane.querySelector('[data-role="ac-doc-category"]');
  const categoryButtons = Array.from(studiesPane.querySelectorAll('[data-ac-doc-category-btn]'));
  const categoryOtherInput = studiesPane.querySelector('[data-role="ac-doc-category-other-input"]');
  const previewWrap = studiesPane.querySelector('[data-role="ac-doc-preview"]');
  const previewBody = studiesPane.querySelector('[data-role="ac-doc-preview-body"]');
  const wizardRoot = studiesPane.querySelector('[data-role="ac-doc-wizard"]');
  const wizardPanels = Array.from(studiesPane.querySelectorAll('[data-ac-doc-step-panel]'));
  const wizardStepIndicator = studiesPane.querySelector('[data-role="ac-doc-wiz-step-indicator"]');
  const wizardPrevBtn = studiesPane.querySelector('[data-action="ac-doc-wiz-prev"]');
  const wizardNextBtn = studiesPane.querySelector('[data-action="ac-doc-wiz-next"]');
  const wizardToggleFullBtn = studiesPane.querySelector('[data-action="ac-doc-wiz-toggle-full"]');
  const wizardContainer = studiesPane.querySelector('[data-est-section-block="ingresar"]');
  const adjuntoModalEl = document.getElementById('modalActividadClinicaAdjunto');
  const dropFile = studiesPane.querySelector('[data-role="ac-doc-drop-file"]');
  const dropQr = studiesPane.querySelector('[data-role="ac-doc-drop-qr"]');
  const dropText = studiesPane.querySelector('[data-role="ac-doc-drop-text"]');
  const saveBtn = studiesPane.querySelector('[data-action="ac-doc-upload-save"]');
  const feedbackEl = studiesPane.querySelector('[data-role="ac-doc-upload-feedback"]');
  const ingresarSectionBtn = studiesPane.querySelector('.est-section-tab[data-est-section="ingresar"]');
  if(!fileInput || !saveBtn || !feedbackEl) return;

  const clean = (value)=> String(value || '').trim();
  let previewObjectUrl = '';
  let previewRenderKey = '';
  const setFeedback = (message, tone = 'muted')=>{
    const text = clean(message);
    const classes = ['text-muted', 'text-success', 'text-danger'];
    feedbackEl.classList.remove(...classes);
    if(!text){
      feedbackEl.classList.add('d-none');
      feedbackEl.textContent = '';
      return;
    }
    feedbackEl.classList.remove('d-none');
    feedbackEl.classList.add(
      tone === 'success' ? 'text-success' : (tone === 'error' ? 'text-danger' : 'text-muted')
    );
    feedbackEl.textContent = text;
  };
  const inferDocumentTypeFromFile = (file)=>{
    if(!file) return '';
    const mime = clean(file.type).toLowerCase();
    const name = clean(file.name).toLowerCase();
    if(mime === 'application/pdf' || name.endsWith('.pdf')) return 'pdf';
    if(mime.startsWith('image/')) return 'image';
    return '';
  };
  const toSqlDatetime = (inputValue)=>{
    const raw = clean(inputValue);
    if(!raw) return '';
    const normalized = raw.replace('T', ' ');
    if(/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}$/.test(normalized)) return `${normalized}:00`;
    if(/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}$/.test(normalized)) return normalized;
    return '';
  };
  const resolveActivePatientIdForUpload = ()=>{
    const fromResolver = (typeof window.resolveActivePatientId === 'function')
      ? clean(window.resolveActivePatientId())
      : '';
    if(fromResolver) return fromResolver;
    const fromStore = clean(window.mxmedStore?.currentPatientId || window.mxmedStore?.activePatientId);
    if(fromStore) return fromStore;
    const expPane = document.getElementById('p-expediente');
    return clean(expPane?.dataset?.patientId || expPane?.getAttribute?.('data-patient-id'));
  };
  const resolveOptionalEncounterKeyForUpload = ()=>{
    if(typeof window.getActiveEncounterKey === 'function'){
      return clean(window.getActiveEncounterKey());
    }
    return clean(window.mxmedStore?.currentEncounterKey || window.mxmedStore?.activeEncounterKey);
  };
  const resolveActivePatientNameForWizard = ()=>{
    const fromHeader = clean(document.querySelector('#p-expediente [data-role="exp-h-patient-name"]')?.textContent || '');
    if(fromHeader && fromHeader.toLowerCase() !== 'paciente') return fromHeader;
    const expPane = document.getElementById('p-expediente');
    return [
      clean(expPane?.querySelector('[data-pac-nombre]')?.value || ''),
      clean(expPane?.querySelector('[data-pac-apellido-paterno]')?.value || ''),
      clean(expPane?.querySelector('[data-pac-apellido-materno]')?.value || '')
    ].filter(Boolean).join(' ').trim();
  };
  const refreshIntentQuestion = ()=>{
    if(!intentQuestionEl) return;
    const patientName = resolveActivePatientNameForWizard();
    intentQuestionEl.textContent = patientName
      ? `¿Qué quieres anexar al archivo de ${patientName}?`
      : '¿Qué quieres anexar al archivo de este paciente?';
  };
  const setDocIntent = (intent, opts = {})=>{
    const normalized = clean(intent).toLowerCase();
    const targetIntent = (normalized === 'foto' || normalized === 'nota') ? normalized : 'archivo';
    if(intentPicker){
      intentPicker.dataset.acDocIntent = targetIntent;
      intentButtons.forEach((btn)=>{
        btn.classList.toggle('is-active', clean(btn.getAttribute('data-ac-doc-intent-btn')).toLowerCase() === targetIntent);
      });
    }
    [dropFile, dropQr, dropText].forEach((node)=>{
      node?.classList.remove('is-intent-active', 'is-intent-related');
    });
    let targetBlock = dropFile;
    let focusNode = null;
    if(targetIntent === 'foto'){
      targetBlock = dropQr || dropFile;
      if(dropQr) dropQr.classList.add('is-intent-active');
      if(dropFile) dropFile.classList.add('is-intent-related');
      focusNode = fileInput;
    }else if(targetIntent === 'nota'){
      targetBlock = dropText || null;
      if(dropText) dropText.classList.add('is-intent-active');
      focusNode = noteCaptureInput || null;
    }else{
      targetBlock = dropFile || null;
      if(dropFile) dropFile.classList.add('is-intent-active');
      if(dropQr) dropQr.classList.add('is-intent-related');
      focusNode = fileInput;
    }
    if(opts.focus === true){
      try{ targetBlock?.scrollIntoView?.({ block:'nearest', behavior:'smooth' }); }catch(_){}
      try{ focusNode?.focus?.(); }catch(_){}
    }
  };
  const setDocCategory = (category, opts = {})=>{
    const normalized = clean(category).toLowerCase();
    const allowed = new Set([
      'estudio_resultado',
      'documento_externo',
      'evidencia_clinica',
      'receta_previa',
      'consentimiento_formato',
      'bitacora_hospitalaria',
      'otro'
    ]);
    const isOtherValue = normalized === 'otro' || normalized.indexOf('otro:') === 0;
    const canonicalValue = isOtherValue ? 'otro' : (allowed.has(normalized) ? normalized : '');
    if(categoryInput){
      const rawOther = clean(categoryOtherInput?.value || '');
      categoryInput.value = (canonicalValue === 'otro' && rawOther)
        ? `otro:${rawOther}`
        : canonicalValue;
    }
    categoryButtons.forEach((btn)=>{
      const btnValue = clean(btn.getAttribute('data-ac-doc-category-btn')).toLowerCase();
      btn.classList.toggle('is-active', btnValue === canonicalValue);
    });
    const otherBtn = categoryButtons.find((btn)=> clean(btn.getAttribute('data-ac-doc-category-btn')).toLowerCase() === 'otro');
    if(otherBtn && categoryOtherInput){
      const showOtherInput = canonicalValue === 'otro';
      otherBtn.classList.toggle('d-none', showOtherInput);
      categoryOtherInput.classList.toggle('d-none', !showOtherInput);
      if(showOtherInput && opts.focusOther === true){
        try{ categoryOtherInput.focus(); }catch(_){}
      }
    }
  };
  const resolveCategorySelection = ()=>{
    const raw = clean(categoryInput?.value || '');
    if(raw === '') return { key: '', label: '' };
    if(raw.indexOf('otro:') === 0){
      const custom = clean(raw.slice(5));
      return { key: 'otro', label: custom || 'Otro' };
    }
    const key = raw;
    const btn = categoryButtons.find((node)=> clean(node.getAttribute('data-ac-doc-category-btn')).toLowerCase() === key);
    const label = clean(btn?.textContent || key);
    return { key, label };
  };
  const clearPreviewObjectUrl = ()=>{
    if(!previewObjectUrl) return;
    try{ URL.revokeObjectURL(previewObjectUrl); }catch(_){}
    previewObjectUrl = '';
  };
  const renderDocumentPreview = ()=>{
    if(!previewBody) return;
    const intent = resolveCurrentIntent();
    const noteText = clean(noteCaptureInput?.value || '');
    const file = fileInput?.files && fileInput.files[0] ? fileInput.files[0] : null;
    const fileSignature = file
      ? [clean(file.name), String(file.size || 0), String(file.lastModified || 0), clean(file.type)].join('|')
      : '';
    const nextKey = [intent, fileSignature, noteText].join('::');
    if(nextKey === previewRenderKey){
      return;
    }
    previewRenderKey = nextKey;
    clearPreviewObjectUrl();
    previewBody.innerHTML = '';
    if(file){
      const isImage = String(file.type || '').toLowerCase().startsWith('image/');
      if(isImage){
        previewObjectUrl = URL.createObjectURL(file);
        const img = document.createElement('img');
        img.className = 'est-doc-preview-thumb';
        img.alt = 'Vista previa del archivo';
        img.src = previewObjectUrl;
        previewBody.appendChild(img);
      }else{
        const block = document.createElement('div');
        block.className = 'est-doc-preview-file';
        const title = document.createElement('strong');
        title.textContent = clean(file.name || 'Archivo adjunto');
        const meta = document.createElement('span');
        const mime = clean(file.type || 'documento');
        meta.textContent = mime.toUpperCase();
        block.appendChild(title);
        block.appendChild(meta);
        previewBody.appendChild(block);
      }
      return;
    }
    if(intent === 'nota' || noteText !== ''){
      const text = noteText !== '' ? noteText : 'Nota preparada sin contenido aún.';
      const span = document.createElement('span');
      span.textContent = text.length > 160 ? `${text.slice(0, 160)}…` : text;
      previewBody.appendChild(span);
      return;
    }
    const empty = document.createElement('span');
    empty.textContent = 'Aún no hay contenido preparado.';
    previewBody.appendChild(empty);
  };
  const WIZARD_MAX_STEP = 4;
  let wizardStep = 1;
  let wizardMode = 'guided';
  const resolveCurrentIntent = ()=> clean(intentPicker?.dataset?.acDocIntent || 'archivo').toLowerCase();
  const syncCaptureStepForIntent = ()=>{
    const intent = resolveCurrentIntent();
    const isFileFlow = intent === 'archivo' || intent === 'foto';
    const isNota = intent === 'nota';
    if(dropFile){
      dropFile.classList.toggle('d-none', isNota);
    }
    if(dropQr){
      dropQr.classList.toggle('d-none', !isFileFlow);
    }
    if(dropText){
      dropText.classList.toggle('d-none', !isNota);
    }
  };
  const renderWizard = (opts = {})=>{
    const focus = opts.focus === true;
    if(!wizardRoot || !wizardPanels.length) return;
    wizardRoot.dataset.acDocWizardMode = 'guided';
    if(wizardContainer) wizardContainer.dataset.acDocWizardMode = 'guided';
    wizardStep = Math.max(1, Math.min(WIZARD_MAX_STEP, Number(wizardStep) || 1));
    wizardPanels.forEach((panel)=>{
      const panelStep = Number(panel.getAttribute('data-ac-doc-step-panel') || 0);
      panel.classList.toggle('d-none', panelStep !== wizardStep);
    });
    if(wizardStepIndicator){
      wizardStepIndicator.textContent = `Paso ${wizardStep} de ${WIZARD_MAX_STEP}`;
    }
    if(wizardPrevBtn) wizardPrevBtn.classList.toggle('d-none', wizardStep <= 1);
    if(wizardNextBtn){
      const hideNext = wizardStep <= 1 || wizardStep >= WIZARD_MAX_STEP;
      wizardNextBtn.classList.toggle('d-none', hideNext);
    }
    if(saveBtn) saveBtn.classList.toggle('d-none', wizardStep < 4);
    if(wizardStep === 2){
      syncCaptureStepForIntent();
      setDocIntent(intentPicker?.dataset?.acDocIntent || 'archivo', { focus });
    }else if(wizardStep === 4){
      renderDocumentPreview();
    }else if(focus){
      const panel = wizardPanels.find((node)=> Number(node.getAttribute('data-ac-doc-step-panel') || 0) === wizardStep);
      const focusable = panel?.querySelector('button, input, select, textarea');
      try{ focusable?.focus?.(); }catch(_){}
    }
  };
  if(intentPicker){
    intentPicker.addEventListener('click', (event)=>{
      const btn = event.target.closest('[data-ac-doc-intent-btn]');
      if(!btn) return;
      event.preventDefault();
      const shouldFocusCapture = wizardStep >= 2;
      setDocIntent(btn.getAttribute('data-ac-doc-intent-btn'), { focus: shouldFocusCapture });
      syncCaptureStepForIntent();
      if(wizardMode === 'guided' && wizardStep === 1){
        wizardStep = 2;
        renderWizard({ focus: true });
      }
      renderDocumentPreview();
    });
    setDocIntent(intentPicker.dataset.acDocIntent || 'archivo');
    syncCaptureStepForIntent();
  }
  if(categoryButtons.length){
    categoryButtons.forEach((btn)=>{
      btn.addEventListener('click', (event)=>{
        event.preventDefault();
        const selected = clean(btn.getAttribute('data-ac-doc-category-btn')).toLowerCase();
        setDocCategory(selected, { focusOther: selected === 'otro' });
        renderDocumentPreview();
      });
    });
  }
  if(categoryOtherInput){
    categoryOtherInput.addEventListener('input', ()=>{
      if(!categoryInput) return;
      const custom = clean(categoryOtherInput.value);
      categoryInput.value = custom ? `otro:${custom}` : 'otro';
      renderDocumentPreview();
    });
    categoryOtherInput.addEventListener('blur', ()=>{
      const custom = clean(categoryOtherInput.value);
      if(!custom){
        setDocCategory('otro');
      }
    });
  }
  if(wizardPrevBtn){
    wizardPrevBtn.addEventListener('click', (event)=>{
      event.preventDefault();
      wizardStep = Math.max(1, wizardStep - 1);
      renderWizard({ focus: true });
    });
  }
  if(wizardNextBtn){
    wizardNextBtn.addEventListener('click', (event)=>{
      event.preventDefault();
      wizardStep = Math.min(WIZARD_MAX_STEP, wizardStep + 1);
      renderWizard({ focus: true });
    });
  }
  if(adjuntoModalEl){
    adjuntoModalEl.addEventListener('shown.bs.modal', ()=>{
      wizardMode = 'guided';
      wizardStep = 1;
      refreshIntentQuestion();
      renderWizard({ focus: false });
      setDocIntent(intentPicker?.dataset?.acDocIntent || 'archivo');
      syncCaptureStepForIntent();
      renderDocumentPreview();
    });
    adjuntoModalEl.addEventListener('hidden.bs.modal', ()=>{
      clearPreviewObjectUrl();
      previewRenderKey = '';
    });
  }
  if(!clean(categoryInput?.value || '')){
    setDocCategory('estudio_resultado');
  }
  refreshIntentQuestion();
  renderDocumentPreview();
  renderWizard({ focus: false });

  const uploadCanonicalDocument = async ()=>{
    const patientId = resolveActivePatientIdForUpload();
    if(!patientId){
      setFeedback('Selecciona un paciente activo antes de adjuntar un documento.', 'error');
      return;
    }
    const documentTitle = clean(titleInput?.value || '');
    if(!documentTitle){
      setFeedback('Ingresa un nombre corto para el documento.', 'error');
      if(wizardMode === 'guided'){
        wizardStep = WIZARD_MAX_STEP;
        renderWizard({ focus: true });
      }
      try{ titleInput?.focus?.(); }catch(_){}
      return;
    }
    const file = fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
    if(!file){
      setFeedback('Selecciona un archivo (imagen o PDF).', 'error');
      return;
    }
    const documentType = inferDocumentTypeFromFile(file);
    if(!documentType){
      setFeedback('Formato no compatible. Usa imagen o PDF.', 'error');
      return;
    }
    const noteCaptureText = clean(noteCaptureInput?.value || '');
    const summaryText = clean(summaryInput?.value || '');
    const summary = [noteCaptureText, summaryText].filter(Boolean).join(' · ');
    const eventDatetime = toSqlDatetime(eventDatetimeInput?.value || '');
    const encounterKey = resolveOptionalEncounterKeyForUpload();
    const mediaTagKey = clean(mediaTagSelect?.value || 'evidencia_clinica');
    const mediaTagLabel = clean(mediaTagSelect?.selectedOptions?.[0]?.textContent || 'Evidencia clínica');
    const categorySelection = resolveCategorySelection();

    const payload = {
      patient_id: patientId,
      document_type: documentType,
      title: documentTitle,
      payload: {
        source: 'actividad_clinica_host',
        filename: clean(file.name || ''),
        title: documentTitle
      }
    };
    if(summary) payload.summary = summary;
    if(eventDatetime) payload.event_datetime = eventDatetime;
    if(encounterKey) payload.encounter_key = encounterKey;
    if(documentType === 'image'){
      payload.media_tag_key = mediaTagKey || 'evidencia_clinica';
      payload.media_tag_label = mediaTagLabel || 'Evidencia clínica';
      payload.payload.media_tag_key = payload.media_tag_key;
      payload.payload.media_tag_label = payload.media_tag_label;
    }
    if(categorySelection.key){
      payload.payload.document_category_key = categorySelection.key;
    }
    if(categorySelection.label){
      payload.payload.document_category_label = categorySelection.label;
    }

    const formData = new FormData();
    Object.keys(payload).forEach((key)=>{
      const value = payload[key];
      if(value == null || value === '') return;
      if(key === 'payload'){
        formData.append(key, JSON.stringify(value));
      }else{
        formData.append(key, String(value));
      }
    });
    formData.append('file', file);

    saveBtn.disabled = true;
    setFeedback('Guardando documento clínico…');
    try{
      console.info('[mxmed-actividad-clinica] upload start', {
        patient_id: patientId,
        encounter_key: encounterKey || null,
        document_type: documentType
      });
    }catch(_){}

    try{
      const resp = await fetch('/api/clinical/index.php/documents', {
        method: 'POST',
        body: formData,
        headers: { Accept: 'application/json' },
        credentials: 'same-origin'
      });
      const json = await resp.json().catch(()=> null);
      if(!resp.ok || !json || json.ok !== true){
        const message = clean(json?.message || json?.error?.message || json?.error || `HTTP ${resp.status}`) || 'No se pudo guardar el documento.';
        throw new Error(message);
      }

      setFeedback('Documento adjuntado correctamente.', 'success');
      try{
        console.info('[mxmed-actividad-clinica] upload success', {
          patient_id: patientId,
          encounter_key: encounterKey || null,
          document_type: documentType,
          document_uuid: clean(json?.data?.document?.document_uuid || '')
        });
      }catch(_){}

      fileInput.value = '';
      if(titleInput) titleInput.value = '';
      if(noteCaptureInput) noteCaptureInput.value = '';
      if(summaryInput) summaryInput.value = '';
      if(eventDatetimeInput) eventDatetimeInput.value = '';
      clearPreviewObjectUrl();
      renderDocumentPreview();

      try{
        window.mxmedRegisterEncounterActivity?.('documento_clinico_adjunto', {
          encounterKey: encounterKey || '',
          patientId,
          source: 'actividad_clinica_adjuntar_documento'
        });
      }catch(_){}
      try{
        window.dispatchEvent(new CustomEvent('mxmed:clinical-document-created', {
          detail: {
            patient_id: patientId,
            encounter_key: encounterKey || '',
            document_type: documentType,
            source: 'actividad_clinica_adjuntar_documento'
          }
        }));
      }catch(_){}
      try{
        const iframe = document.getElementById('mm-embed-historial');
        if(iframe){
          const src = String(iframe.getAttribute('src') || '').trim();
          if(src && src.indexOf('/modules/clinical/ui/historial.php') !== -1){
            const next = `${src}${src.indexOf('?') !== -1 ? '&' : '?'}host_doc_refresh=${Date.now()}`;
            iframe.setAttribute('src', next);
          }
        }
      }catch(_){}
      try{
        const modal = (window.bootstrap && window.bootstrap.Modal && typeof window.bootstrap.Modal.getInstance === 'function')
          ? window.bootstrap.Modal.getInstance(adjuntoModalEl)
          : null;
        modal?.hide();
      }catch(_){}
    }catch(err){
      setFeedback(String(err?.message || 'No se pudo adjuntar el documento.'), 'error');
      try{
        console.info('[mxmed-actividad-clinica] upload error', {
          patient_id: patientId,
          encounter_key: encounterKey || null,
          reason: String(err?.message || 'upload_failed')
        });
      }catch(_){}
    }finally{
      saveBtn.disabled = false;
    }
  };

  saveBtn.addEventListener('click', (event)=>{
    event.preventDefault();
    uploadCanonicalDocument();
  });
  fileInput.addEventListener('change', ()=>{
    const file = fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
    if(!file){
      setFeedback('');
      return;
    }
    const docType = inferDocumentTypeFromFile(file);
    if(!docType){
      setFeedback('Formato no compatible. Usa imagen o PDF.', 'error');
      return;
    }
    setFeedback(`Archivo seleccionado: ${file.name} (${docType.toUpperCase()})`);
    renderDocumentPreview();
    if(ingresarSectionBtn && !ingresarSectionBtn.classList.contains('active')){
      ingresarSectionBtn.click();
    }
  });
  noteCaptureInput?.addEventListener('input', renderDocumentPreview);
})();

(function(){
  const container = document.querySelector('#t-exploracion');
  if(!container) return;

  container.addEventListener('click', (event)=>{
    const preset = event.target.closest('[data-exp-target]');
    if(preset){
      const target = preset.dataset.expTarget ? document.getElementById(preset.dataset.expTarget) : null;
      if(target){
        target.value = preset.dataset.expValue ?? '';
        target.dispatchEvent(new Event('input', { bubbles:true }));
        target.focus();
      }
      return;
    }
    const bpPreset = event.target.closest('[data-exp-bp-sys]');
    if(bpPreset){
      const sys = document.getElementById('exp_bp_sys');
      const dia = document.getElementById('exp_bp_dia');
      const display = document.getElementById('exp_bp_display');
      const sysVal = bpPreset.dataset.expBpSys;
      const diaVal = bpPreset.dataset.expBpDia;
      if(sys && sysVal){
        sys.value = sysVal;
        sys.dispatchEvent(new Event('input', { bubbles:true }));
      }
      if(dia && diaVal){
        dia.value = diaVal;
        dia.dispatchEvent(new Event('input', { bubbles:true }));
      }
      if(display && sysVal && diaVal){
        display.value = `${sysVal}/${diaVal}`;
      }
      sys?.focus();
    }
  });

  const weight = container.querySelector('#exp_weight');
  const height = container.querySelector('#exp_height');
  const bmi = container.querySelector('#exp_bmi');
  const bmiIndicator = container.querySelector('#exp_bmi_state');
  const bpDisplay = container.querySelector('#exp_bp_display');
  const bpSys = container.querySelector('#exp_bp_sys');
  const bpDia = container.querySelector('#exp_bp_dia');
  const parseBpDisplay = ()=>{
    if(!bpDisplay || !bpSys || !bpDia) return;
    const match = bpDisplay.value.match(/(\d{1,3})\s*\/\s*(\d{1,3})/);
    if(!match) return;
    const [, sysVal, diaVal] = match;
    bpSys.value = sysVal;
    bpDia.value = diaVal;
    bpSys.dispatchEvent(new Event('input', { bubbles:true }));
    bpDia.dispatchEvent(new Event('input', { bubbles:true }));
  };
  bpDisplay?.addEventListener('blur', parseBpDisplay);
  bpDisplay?.addEventListener('change', parseBpDisplay);
  const updateIndicator = (value)=>{
    if(!bmiIndicator) return;
    bmiIndicator.textContent = 'Sin datos';
    bmiIndicator.className = 'exp-bmi-pill exp-bmi-pill--neutral';
    const numeric = parseFloat(value);
    if(!numeric) return;
    let label = 'Normal';
    let cls = 'exp-bmi-pill--normal';
    if(numeric < 18.5){
      label = 'Bajo peso';
      cls = 'exp-bmi-pill--underweight';
    }else if(numeric < 25){
      label = 'Normal';
      cls = 'exp-bmi-pill--normal';
    }else if(numeric < 30){
      label = 'Sobrepeso';
      cls = 'exp-bmi-pill--overweight';
    }else{
      label = 'Obesidad';
      cls = 'exp-bmi-pill--obese';
    }
    bmiIndicator.textContent = label;
    bmiIndicator.className = `exp-bmi-pill ${cls}`;
  };
  if(weight && height && bmi){
    const calc = ()=>{
      const w = parseFloat(weight.value);
      const h = parseFloat(height.value);
      if(!w || !h){
        bmi.value = '';
        updateIndicator('');
        return;
      }
      const meters = h / 100;
      if(meters <= 0){
        bmi.value = '';
        updateIndicator('');
        return;
      }
      const result = w / (meters * meters);
      bmi.value = Number.isFinite(result) ? result.toFixed(1) : '';
      updateIndicator(bmi.value);
    };
    weight.addEventListener('input', calc);
    height.addEventListener('input', calc);
    calc();
  }
})();

// ====== Pacientes: buscar en archivo ======
(function(){
  const pane = document.getElementById('p-pac-archivo');
  if(!pane) return;

  const qEl = document.getElementById('mm-pac-archivo-q');
  const filterEl = document.getElementById('mm-pac-archivo-filter');
  const searchBtn = document.getElementById('mm-pac-archivo-search');
  const msgEl = document.getElementById('mm-pac-archivo-msg');
  const tbodyEl = document.getElementById('mm-pac-archivo-tbody');
  if(!qEl || !filterEl || !searchBtn || !msgEl || !tbodyEl) return;

  function resolveDoctorId(){
    const cand = [
      window.mxmedDoctor?.doctor_id,
      window.mxmedDoctor?.id,
      window.mxmedStore?.doctorId,
      window.mxmedStore?.doctor_id,
      document.body?.dataset?.doctorId,
    ];
    for(const v of cand){
      const s = String(v || '').trim();
      if(s) return s;
    }
    return '';
  }

  function resolvePatientsSearchUrl(){
    const docId = resolveDoctorId();
    if(docId){
      return `/api/patients/index.php/doctors/${encodeURIComponent(docId)}/patients`;
    }
    return '/api/patients/index.php/patients';
  }
  window.resolveDoctorId = resolveDoctorId;
  window.resolvePatientsSearchUrl = resolvePatientsSearchUrl;
  let debounceTimer = null;
  let cachedList = null;
  let cachedAt = 0;
  let cachingPromise = null;
  window.mxmedInvalidatePatientsIndexCache = ()=>{
    cachedList = null;
    cachedAt = 0;
    cachingPromise = null;
  };

  const hideMsg = ()=>{
    msgEl.classList.add('d-none');
    msgEl.textContent = '';
  };

  const showMsg = (text, type='info')=>{
    msgEl.className = `alert alert-${type} mt-3`;
    msgEl.textContent = text;
  };

  const clearResults = ()=>{
    tbodyEl.innerHTML = '';
  };

  const normalizeList = (payload)=>{
    if(!payload || payload.ok !== true) return [];
    const raw = Array.isArray(payload.data)
      ? payload.data
      : (Array.isArray(payload.data?.items) ? payload.data.items : []);
    const list = raw.filter(item => item && typeof item === 'object');
    return list.map((item)=>{
      const patientId = String(item.patient_id || item.id || item.patientId || '').trim();
      const fullName = String(item.nombre_completo || item.display_name || item.name || '').trim();
      const curp = String(item.curp || '').trim();
      return {
        patient_id: patientId,
        nombre_completo: fullName,
        curp
      };
    }).filter(item => item.patient_id !== '');
  };

  const norm = (value)=>{
    const text = String(value || '').toLowerCase().trim();
    if(!text) return '';
    return text
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/\s+/g, ' ')
      .trim();
  };

  const matchesPatient = (patient, qn)=>{
    const name = norm(patient.nombre_completo || patient.display_name || patient.name || '');
    const curp = norm(patient.curp || '');
    const pid = norm(patient.patient_id || '');
    return name.includes(qn) || curp.includes(qn) || pid.includes(qn);
  };

  const fetchPatientsIndex = async ({ force = false } = {})=>{
    if(cachedList && !force){
      return cachedList;
    }
    if(cachingPromise){
      return await cachingPromise;
    }
    const base = resolvePatientsSearchUrl();
    const url = new URL(base, window.location.origin);
    url.searchParams.set('limit', '200');
    cachingPromise = fetch(url.toString(), {
      method: 'GET',
      headers: { 'Accept': 'application/json' },
      credentials: 'same-origin'
    }).then(async (resp)=>{
      const json = await resp.json().catch(()=> null);
      if(!json || json.ok !== true){
        throw new Error(String((json && (json.message || json.error)) || 'No se pudo cargar el índice de pacientes.'));
      }
      const normalized = normalizeList(json);
      cachedList = normalized;
      cachedAt = Date.now();
      return cachedList;
    }).finally(()=>{
      cachingPromise = null;
    });
    return await cachingPromise;
  };

  const renderResults = (list)=>{
    if(!Array.isArray(list) || !list.length){
      clearResults();
      showMsg('Sin resultados', 'info');
      return;
    }
    hideMsg();
    tbodyEl.innerHTML = list.map((item)=>{
      const pid = String(item.patient_id || '').trim();
      const name = String(item.nombre_completo || item.display_name || pid || 'Paciente').trim();
      const curp = String(item.curp || '').trim();
      return `
        <tr>
          <td>${name.replace(/</g,'&lt;').replace(/>/g,'&gt;')}</td>
          <td>${(curp || '-').replace(/</g,'&lt;').replace(/>/g,'&gt;')}</td>
          <td class="text-end">
            <button type="button" class="btn btn-outline-primary btn-sm" data-pid="${pid.replace(/"/g,'&quot;')}" data-pname="${name.replace(/"/g,'&quot;')}">Abrir expediente</button>
          </td>
        </tr>
      `;
    }).join('');
  };

  const doSearch = async ()=>{
    const q = String(qEl.value || '').trim();
    const filterVal = String(filterEl.value || '').trim();

    if(q.length < 2){
      clearResults();
      showMsg('Escribe al menos 2 caracteres', 'info');
      return;
    }

    const qn = norm(q);
    searchBtn.disabled = true;
    searchBtn.textContent = 'Buscando…';
    try{
      const list = await fetchPatientsIndex();
      // TODO: aplicar `filterVal` cuando el índice exponga señales (recent/inactive) de forma consistente.
      void filterVal;
      const filtered = list.filter((patient)=> matchesPatient(patient, qn));
      renderResults(filtered);
    }catch(err){
      clearResults();
      showMsg(String(err?.message || 'Error de red al buscar pacientes.'), 'danger');
    }finally{
      searchBtn.disabled = false;
      searchBtn.textContent = 'Buscar';
    }
  };

  const formatDateTime = (date = new Date())=>{
    const d = (date instanceof Date) ? date : new Date(date);
    const pad2 = (n)=> String(n).padStart(2, '0');
    const y = d.getFullYear();
    const m = pad2(d.getMonth() + 1);
    const day = pad2(d.getDate());
    const hh = pad2(d.getHours());
    const mm = pad2(d.getMinutes());
    const ss = pad2(d.getSeconds());
    return `${y}-${m}-${day} ${hh}:${mm}:${ss}`;
  };

  const ensureActiveEncounter = async (pid)=>{
    const safePid = String(pid || '').trim();
    if(!safePid) return null;
    try{
      const activeUrl = `/api/clinical/index.php/patients/${encodeURIComponent(safePid)}/encounters/active`;
      const activeResp = await fetch(activeUrl, {
        method: 'GET',
        headers: { 'Accept':'application/json' },
        credentials: 'same-origin'
      });
      const activeJson = await activeResp.json().catch(()=> null);
      if(activeJson?.ok !== true){
        console.warn('[P11] ensureActiveEncounter active lookup failed');
        return null;
      }
      let encounterKey = String(activeJson?.data?.encounter_key || activeJson?.encounter_key || '').trim();

      if(!encounterKey && activeJson?.data === null){
        if(typeof window.mxmedCanStartEncounter === 'function'){
          const gate = window.mxmedCanStartEncounter(safePid, 3);
          if(gate && gate.allowed === false){
            window.alert('Ya tienes 3 consultas activas. Cierra una consulta antes de iniciar otra.');
            return null;
          }
        }
        const createUrl = `/api/clinical/index.php/patients/${encodeURIComponent(safePid)}/encounters`;
        const createResp = await fetch(createUrl, {
          method: 'POST',
          headers: { 'Content-Type':'application/json', 'Accept':'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify({
            status: 'open',
            encounter_dt: formatDateTime()
          })
        });
        const createJson = await createResp.json().catch(()=> null);
        if(createJson?.ok !== true){
          console.warn('[P11] ensureActiveEncounter create failed');
          return null;
        }
        encounterKey = String(createJson?.data?.encounter_key || createJson?.encounter_key || '').trim();
      }

      if(!encounterKey){
        return null;
      }

      if(window.mxmedStore && typeof window.mxmedStore === 'object'){
        window.mxmedStore.activeEncounterKey = encounterKey;
      }
      if(typeof window.setEncounterContextOnPane === 'function'){
        window.setEncounterContextOnPane(encounterKey, safePid);
      }

      const detail = { patient_id: safePid, encounter_key: encounterKey };
      window.dispatchEvent(new CustomEvent('encounter:active', { detail }));
      window.dispatchEvent(new CustomEvent('mxmed:encounter-changed', { detail }));
      if(typeof window.mxmedEmitEncounterLifecycle === 'function'){
        window.mxmedEmitEncounterLifecycle({
          patient_id: safePid,
          encounter_key: encounterKey,
          status: 'consulta_activa',
          origin: 'ensure_active_encounter',
          last_activity_at: new Date().toISOString()
        });
      }
      return encounterKey;
    }catch(_){
      console.warn('[P11] ensureActiveEncounter failed');
      return null;
    }
  };

  const scheduleSearch = ()=>{
    if(debounceTimer){
      window.clearTimeout(debounceTimer);
    }
    debounceTimer = window.setTimeout(doSearch, 300);
  };

  qEl.addEventListener('input', scheduleSearch);
  qEl.addEventListener('keydown', (ev)=>{
    if(ev.key === 'Enter'){
      ev.preventDefault();
      doSearch();
    }
  });
  searchBtn.addEventListener('click', doSearch);
  tbodyEl.addEventListener('click', async (ev)=>{
    const btn = ev.target.closest('[data-pid]');
    if(!btn) return;
    const pid = String(btn.getAttribute('data-pid') || '').trim();
    const pname = String(btn.getAttribute('data-pname') || '').trim();
    if(!pid) return;
    if(typeof window.mxmedRememberPatientLabel === 'function'){
      window.mxmedRememberPatientLabel(pid, pname);
    }
    let changed = true;
    if(typeof window.setActivePatientId === 'function'){
      changed = await window.setActivePatientId(pid, { emitEvent:true, source:'search_open', suppressEncounterAutoContext:true });
    }else if(typeof window.mxmedSetActivePatientId === 'function'){
      changed = await window.mxmedSetActivePatientId(pid, { emitEvent:true, source:'search_open', suppressEncounterAutoContext:true });
    }
    if(changed === false){
      return;
    }
    if(typeof jumpTo === 'function'){
      jumpTo('p-expediente');
      window.requestAnimationFrame(()=>{
        const expPane = document.getElementById('p-expediente');
        if(!expPane || expPane.classList.contains('d-none')) return;
        window.__mxmedHeaderSyncOrigin = 'search_open_post_jump';
        window.dispatchEvent(new Event('patient:selected'));
      });
    }
    const openOrigin = String(btn.getAttribute('data-open-origin') || 'search_general').trim().toLowerCase();
    if(openOrigin === 'clinical_explicit'){
      ensureActiveEncounter(pid).catch(()=> null);
    }
  });
})();
