// Manejo hospitalario (Bootstrap 5 + estilos existentes)
(function () {
  const tab = document.getElementById('t-manejo');
  if (!tab || tab.dataset.mhInitialized === '1') return;
  tab.dataset.mhInitialized = '1';

  const CSS = `.mh-ro{background:#f8fafc}.mh-timeline{display:grid;gap:10px}.mh-doc-card{background:#fff;border:1px solid #cfe9f0;border-radius:12px;padding:10px;box-shadow:0 6px 14px rgba(0,0,0,.04)}.mh-doc-ttl{font-weight:800;color:#005275;line-height:1.2}.mh-doc-meta{font-size:.82rem;color:#6c757d;margin-top:2px}.mh-doc-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}.mh-events{display:flex;flex-wrap:wrap;gap:8px}.mh-ev-chip{border:1px solid #cfe9f0;border-radius:999px;padding:4px 10px;background:#f5fbff;color:#003152;font-size:.85rem}.mh-panel-card .card-body{border-radius:12px}`;
  const TEMPLATE_ID = 'tpl-manejo-hospitalario';

  const ensureCss = () => {
    if (document.getElementById('mh-css')) return;
    const s = document.createElement('style');
    s.id = 'mh-css';
    s.textContent = CSS;
    document.head.appendChild(s);
  };
  const getTemplateFragment = () => {
    const tpl = document.getElementById(TEMPLATE_ID);
    return tpl ? document.importNode(tpl.content, true) : null;
  };

  const qs = (sel, root = tab) => root.querySelector(sel);
  const esc = (s) => String(s ?? '').replace(/</g, '&lt;');
  const enc = (s) => encodeURIComponent(String(s ?? ''));
  const num = (v, def = 0) => {
    const n = Number(String(v ?? '').trim());
    return Number.isFinite(n) ? n : def;
  };
  const numOrNull = (v) => {
    const t = String(v ?? '').trim();
    if (!t) return null;
    const n = Number(t);
    return Number.isFinite(n) ? n : null;
  };
  const normalize = (str) => (str || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9]+/g, ' ').trim();

  const isDemo = window.location.hostname.endsWith('github.io');
  const demoFetchJson = async (path) => {
    const res = await fetch(path, { method: 'GET', headers: {} });
    const data = await res.json().catch(() => null);
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return data || {};
  };
  const demoRoute = (url) => {
    if (url.includes('hospital-stays.php?action=current')) return demoFetchJson('mock/hospital-stays-current.json');
    if (url.includes('hospital-stays.php?action=start')) return demoFetchJson('mock/hospital-stays-start.json');
    if (url.includes('hospital-stays.php?action=close')) return demoFetchJson('mock/hospital-stays-close.json');
    if (url.includes('clinical-documents.php?action=list')) return demoFetchJson('mock/clinical-documents-list-hosp.json');
    if (url.includes('clinical-documents.php?action=get')) return demoFetchJson('mock/clinical-documents-get-hosp.json');
    if (url.includes('clinical-documents.php?action=save')) return demoFetchJson('mock/clinical-documents-save-hosp.json');
    return Promise.resolve({ ok: true });
  };
  const api = {
    async j(url, opt) {
      if (isDemo) return demoRoute(url);
      const res = await fetch(url, { ...(opt || {}), headers: { 'Content-Type': 'application/json', ...(opt?.headers || {}) } });
      const data = await res.json().catch(() => null);
      if (!res.ok) throw new Error(data?.error || (Array.isArray(data?.errors) ? data.errors.join(' ') : '') || `HTTP ${res.status}`);
      return data;
    },
    currentStay: (pid) => api.j(`api/hospital-stays.php?action=current&patient_id=${enc(pid)}`, { method: 'GET', headers: {} }),
    startStay: (body) => api.j('api/hospital-stays.php?action=start', { method: 'POST', body: JSON.stringify(body || {}) }),
    closeStay: (body) => api.j('api/hospital-stays.php?action=close', { method: 'POST', body: JSON.stringify(body || {}) }),
    saveDoc: (body) => api.j('api/clinical-documents.php?action=save', { method: 'POST', body: JSON.stringify(body || {}) }),
    listDocs: (pid, sid) => api.j(`api/clinical-documents.php?action=list&patient_id=${enc(pid)}&hospital_stay_id=${enc(sid)}&limit=50`, { method: 'GET', headers: {} }),
    getDoc: (id) => api.j(`api/clinical-documents.php?action=get&id=${enc(id)}`, { method: 'GET', headers: {} }),
  };

  const getPatient = () => {
    const pane = document.getElementById('p-expediente');
    const n = pane?.querySelector('[data-pac-nombre]')?.value?.trim() || '';
    const ap = pane?.querySelector('[data-pac-apellido-paterno]')?.value?.trim() || '';
    const am = pane?.querySelector('[data-pac-apellido-materno]')?.value?.trim() || '';
    const nombre = [n, ap, am].filter(Boolean).join(' ').trim() || 'Paciente';
    const dd = pane?.querySelector('[data-dg-dia]')?.value || '';
    const mm = pane?.querySelector('[data-dg-mes]')?.value || '';
    const yy = pane?.querySelector('[data-dg-anio]')?.value || '';
    const dob = [yy, mm, dd].filter(Boolean).join('-');
    const edad = pane?.querySelector('[data-dg-edad]')?.textContent?.trim() || '--';
    const sexoVal = pane?.querySelector('input[name=\"pac-genero\"]:checked')?.value || '';
    const sexo = sexoVal === 'F' ? 'Femenino' : sexoVal === 'M' ? 'Masculino' : sexoVal === 'O' ? 'Otro' : '--';
    const patient_id = normalize([nombre, dob, sexoVal].join('|')) || 'anon';
    return { patient_id, nombre_completo: nombre, edad, sexo };
  };
  const getDoctor = () => {
    const nombre = document.querySelector('.user-id .name')?.textContent?.trim() || 'Médico';
    const cedula = document.getElementById('ced-prof')?.value?.trim() || '';
    const especialidad = document.getElementById('fs-esp')?.textContent?.trim() || '';
    return { user_id: normalize(nombre) || 'user', nombre_completo: nombre, cedula_profesional: cedula || '--', especialidad: especialidad || '--' };
  };
  const getRxDraft = (patientKey) => {
    try {
      const raw = localStorage.getItem(`mxmed_rx_draft_v1:${patientKey || 'anon'}`);
      const data = raw ? JSON.parse(raw) : null;
      if (!data || !Array.isArray(data.medicamentos)) return { has_prescription: false, prescription_id: '', medicamentos: [] };
      return { has_prescription: data.medicamentos.length > 0, prescription_id: data.prescription_id || '', medicamentos: data.medicamentos };
    } catch (_) { return { has_prescription: false, prescription_id: '', medicamentos: [] }; }
  };

  const ctxApi = window.mxmedContext || null;
  const ctxStorageKey = 'mxmed.activeContext';
  const readStoredContext = () => {
    try {
      const raw = localStorage.getItem(ctxStorageKey);
      return raw ? JSON.parse(raw) : {};
    } catch (_) {
      return {};
    }
  };
  const getContextSafe = () => (ctxApi?.getContext ? ctxApi.getContext() : (readStoredContext() || {}));
  const setContextSafe = (partial) => {
    if (ctxApi?.setContext) return ctxApi.setContext(partial || {});
    const prev = readStoredContext() || {};
    const next = { ...prev };
    Object.keys(partial || {}).forEach((k) => {
      const val = partial[k] === '' ? null : partial[k];
      next[k] = val;
    });
    try { localStorage.setItem(ctxStorageKey, JSON.stringify(next)); } catch (_) {}
    try { document.dispatchEvent(new CustomEvent('mxmed:context:changed', { detail: { ...next } })); } catch (_) {}
    return next;
  };
  const isValidPatientId = (id) => !!id && id !== 'anon' && id !== 'paciente';
  const ensureContextNotice = () => {
    let box = qs('#mh_context_notice');
    if (!box) return null;
    return box;
  };
  const updateContextNotice = () => {
    const ctx = getContextSafe();
    const ok = isValidPatientId((ctx?.patient_id || '').trim());
    const box = ensureContextNotice();
    if (!box) return;
    box.classList.toggle('d-none', ok);
  };

  const state = { loaded: false, stay: null, events: [] };
  const showDemoModeNotice = () => {
    const el = qs('#mh_demo_notice');
    if (!el) return;
    if (isDemo) {
      el.textContent = 'Modo demostración: datos simulados / selecciona paciente.';
      el.classList.remove('d-none');
    } else {
      el.classList.add('d-none');
    }
  };
  const showErr = (msgs) => {
    if (isDemo && msgs && msgs.length) {
      showDemoModeNotice();
    }
    if (isDemo) return;
    const el = qs('#mh_errors');
    if (!el) return;
    if (!msgs || !msgs.length) { el.classList.add('d-none'); el.textContent = ''; return; }
    el.classList.remove('d-none');
    el.innerHTML = `<strong>Error:</strong><ul class="mb-0">${msgs.map(m => `<li>${esc(m)}</li>`).join('')}</ul>`;
  };

  const printText = (text) => {
    const w = window.open('', '_blank');
    if (!w) return;
    w.document.write(`<pre style="white-space:pre-wrap;font-family:ui-monospace,Menlo,Consolas,monospace;font-size:12px">${esc(text)}</pre>`);
    w.document.close(); w.focus(); w.print();
  };
  const openDoc = (text) => {
    const ta = qs('#mh_doc_text');
    if (!ta) return;
    ta.value = text || '';
    const modalEl = qs('#modalMhDocument');
    if (!modalEl) return;
    ta.value = text || '';
    try { bootstrap?.Modal?.getOrCreateInstance(modalEl)?.show(); } catch (_) {}
  };

  const balance = () => {
    const inTotal = num(qs('#mh_in_iv')?.value) + num(qs('#mh_in_vo')?.value) + num(qs('#mh_in_otros')?.value);
    const outTotal = num(qs('#mh_out_diuresis')?.value) + num(qs('#mh_out_evacu')?.value) + num(qs('#mh_out_dren')?.value) + num(qs('#mh_out_otros')?.value);
    const bal = inTotal - outTotal;
    const b = qs('#mh_balance_badge');
    if (b) {
      b.textContent = `${bal} ml`;
      b.className = 'badge ' + (bal > 50 ? 'text-bg-success' : bal < -50 ? 'text-bg-danger' : 'text-bg-secondary');
    }
    return { ingresos_total_ml: inTotal, egresos_total_ml: outTotal, balance_ml: bal };
  };

  const renderStay = () => {
    const badge = qs('#mh_stay_badge'), meta = qs('#mh_stay_meta'), det = qs('#mh_stay_details'), closeBtn = qs('#mh_close_btn');
    if (!badge || !meta || !det || !closeBtn) return;
    if (!state.stay) {
      badge.className = 'badge text-bg-secondary'; badge.textContent = 'Sin episodio activo';
      meta.textContent = 'Para generar documentos intrahospitalarios, inicia una hospitalización.';
      det.textContent = '';
      closeBtn.classList.add('d-none');
      qs('#mh_timeline') && (qs('#mh_timeline').innerHTML = '<div class="text-muted small">Sin episodio activo.</div>');
      return;
    }
    badge.className = 'badge text-bg-success'; badge.textContent = 'Hospitalización activa';
    meta.textContent = `${state.stay.service || 'Hospitalización'} · Ingreso: ${(state.stay.started_at ? new Date(state.stay.started_at).toLocaleString('es-MX') : '')}`;
    det.textContent = [state.stay.room ? `Hab. ${state.stay.room}` : '', state.stay.bed ? `Cama ${state.stay.bed}` : ''].filter(Boolean).join(' · ');
    closeBtn.classList.remove('d-none');
  };

  const renderRx = () => {
    const patient = getPatient();
    const rx = getRxDraft(patient.patient_id);
    const box = qs('#mh_rx_ro');
    if (!box) return;
    if (!rx.has_prescription) { box.textContent = 'Sin receta registrada'; return; }
    box.textContent = rx.medicamentos.map(m => `• ${[(m.medicamento||'').trim(),(m.dosis||'').trim(),(m.via||'').trim(),(m.periodicidad||'').trim(),(m.duracion||'').trim()].filter(Boolean).join(' · ') || 'Medicamento'}`).join('\\n');
    box.style.whiteSpace = 'pre-wrap';
  };

  const renderEvents = () => {
    const box = qs('#mh_events_list');
    if (!box) return;
    if (!state.events.length) { box.innerHTML = '<div class="text-muted small">Sin eventos capturados en este panel.</div>'; return; }
    box.innerHTML = state.events.map(ev => `<span class="mh-ev-chip" title="${esc(ev.descripcion||'')}">${esc(ev.label||ev.type||'Evento')}${ev.descripcion ? `: ${esc(ev.descripcion)}` : ''}</span>`).join('');
  };

  const renderTimeline = async () => {
    const box = qs('#mh_timeline');
    if (!box) return;
    if (!state.stay?.id) { box.innerHTML = '<div class="text-muted small">Sin episodio activo.</div>'; return; }
    box.innerHTML = '<div class="text-muted small">Cargando…</div>';
    const patient = getPatient();
    try {
      const { items } = await api.listDocs(patient.patient_id, state.stay.id);
      const list = Array.isArray(items) ? items : [];
      if (!list.length) { box.innerHTML = '<div class="text-muted small">Sin documentos en este episodio.</div>'; return; }
      const label = (t) => t === 'nota_evolucion_hosp' ? 'Nota intrahospitalaria' : t === 'hoja_indicaciones' ? 'Hoja de indicaciones' : (t || 'Documento');
      box.innerHTML = list.map(it => {
        const dt = it.event_datetime ? new Date(it.event_datetime).toLocaleString('es-MX') : '';
        const ttl = (it.summary || '').trim() || label(it.document_type);
        const meta = [dt, (it.doctor_name || '').trim()].filter(Boolean).join(' · ');
        return `<div class="mh-doc-card"><div class="mh-doc-ttl">${esc(ttl)}</div><div class="mh-doc-meta">${esc(meta)}</div><div class="mh-doc-actions"><button type="button" class="btn btn-outline-primary btn-sm" data-mh-action="view" data-mh-id="${it.id}">Ver</button><button type="button" class="btn btn-outline-secondary btn-sm" data-mh-action="print" data-mh-id="${it.id}">Imprimir</button></div></div>`;
      }).join('');
    } catch (e) {
      box.innerHTML = `<div class="text-danger small">No se pudo cargar el timeline (${esc(e?.message || e)}).</div>`;
    }
  };

  const syncContextFromState = () => {
    if (!ctxApi?.setContext) return;
    const patient = getPatient();
    const pid = isValidPatientId(patient.patient_id) ? patient.patient_id : null;
    const stayId = state.stay?.id ? String(state.stay.id) : null;
    const care = stayId ? 'hospitalizacion' : (pid ? 'consulta' : null);
    setContextSafe({
      patient_id: pid,
      hospital_stay_id: stayId,
      care_setting: care,
      service: state.stay?.service || null
    });
  };

  const refresh = async () => {
    showErr([]);
    renderRx(); balance(); renderEvents();
    const patient = getPatient();
    try {
      const res = await api.currentStay(patient.patient_id);
      state.stay = res?.stay || null;
      renderStay();
      syncContextFromState();
      updateContextNotice();
      await renderTimeline();
    } catch (e) {
      state.stay = null;
      renderStay();
      syncContextFromState();
      updateContextNotice();
      showErr([e?.message || String(e)]);
    }
  };

  const buildContext = () => {
    const patient = getPatient();
    return { patient_id: patient.patient_id, encounter_id: null, hospital_stay_id: String(state.stay?.id || ''), care_setting: 'hospitalizacion', service: state.stay?.service || null };
  };

  const buildPron = () => {
    const preset = (qs('#mh_pronostico')?.value || '').trim();
    const texto = (qs('#mh_pronostico_txt')?.value || '').trim();
    if (preset === 'bueno' || preset === 'reservado' || preset === 'malo') return { preset, texto: null };
    if (preset === 'otro') return { preset: null, texto: texto || null };
    return { preset: null, texto: null };
  };

  const buildHospNotePayload = () => {
    const patient = getPatient();
    const doctor = getDoctor();
    const rx = getRxDraft(patient.patient_id);
    const b = balance();

    const diagnosticos = (qs('#mh_dx')?.value || '').split('\\n').map(s => s.trim()).filter(Boolean).map(label => ({ code: null, label }));

    return {
      section_id: 'nota_evolucion_hosp',
      standard: 'NOM-004-SSA3-2012',
      contract_version: 1,
      ambito: 'hospitalizacion',
      estado_actual: {
        estado_general: (qs('#mh_estado_general')?.value || '').trim(),
        conciencia: (qs('#mh_conciencia')?.value || '').trim(),
        dolor_eva: numOrNull(qs('#mh_dolor_eva')?.value),
        soporte: {
          oxigeno: !!qs('#mh_sup_o2')?.checked,
          oxigeno_tipo: (qs('#mh_o2_tipo')?.value || '').trim(),
          oxigeno_flujo: (qs('#mh_o2_flujo')?.value || '').trim(),
          oxigeno_fio2: (qs('#mh_o2_fio2')?.value || '').trim(),
          oxigeno_notas: (qs('#mh_o2_notas')?.value || '').trim(),
          via_venosa: !!qs('#mh_sup_vv')?.checked,
          foley: !!qs('#mh_sup_foley')?.checked,
          sng: !!qs('#mh_sup_sng')?.checked,
          drenajes: !!qs('#mh_sup_drains')?.checked,
          drenajes_txt: (qs('#mh_drains_txt')?.value || '').trim()
        }
      },
      signos_vitales: {
        ta_sistolica: numOrNull(qs('#mh_vitals_sys')?.value),
        ta_diastolica: numOrNull(qs('#mh_vitals_dia')?.value),
        fc: numOrNull(qs('#mh_vitals_fc')?.value),
        fr: numOrNull(qs('#mh_vitals_fr')?.value),
        temperatura: numOrNull(qs('#mh_vitals_temp')?.value),
        spo2: numOrNull(qs('#mh_vitals_spo2')?.value),
        dolor_eva: numOrNull(qs('#mh_dolor_eva')?.value),
      },
      balance_hidrico: {
        ingresos_iv_ml: num(qs('#mh_in_iv')?.value),
        ingresos_vo_ml: num(qs('#mh_in_vo')?.value),
        ingresos_otros_ml: num(qs('#mh_in_otros')?.value),
        ingresos_notas: (qs('#mh_in_notas')?.value || '').trim(),
        egresos_diuresis_ml: num(qs('#mh_out_diuresis')?.value),
        egresos_evacuaciones_ml: num(qs('#mh_out_evacu')?.value),
        egresos_drenajes_ml: num(qs('#mh_out_dren')?.value),
        egresos_otros_ml: num(qs('#mh_out_otros')?.value),
        egresos_notas: (qs('#mh_out_notas')?.value || '').trim(),
        ingresos_total_ml: b.ingresos_total_ml,
        egresos_total_ml: b.egresos_total_ml,
        balance_ml: b.balance_ml,
        notas: [ (qs('#mh_in_notas')?.value || '').trim(), (qs('#mh_out_notas')?.value || '').trim() ].filter(Boolean).join(' | ')
      },
      exploracion_relevante: {
        resumen_sistemas: (qs('#mh_expl_resumen')?.value || '').trim(),
        hallazgos_relevantes: (qs('#mh_hallazgos')?.value || '').trim()
      },
      evolucion_diaria: {
        nota: (qs('#mh_evo_nota')?.value || '').trim(),
        hallazgos: (qs('#mh_hallazgos')?.value || '').trim()
      },
      diagnosticos,
      pronostico: buildPron(),
      plan_indicaciones: (qs('#mh_plan')?.value || '').trim(),
      receta: rx,
      eventos: state.events.slice(),
      snapshot: {
        paciente: patient,
        medico: doctor,
        hospital_stay: state.stay ? { id: state.stay.id, service: state.stay.service, room: state.stay.room, bed: state.stay.bed, started_at: state.stay.started_at } : null,
        generated_at: new Date().toISOString()
      }
    };
  };

  const buildOrdersPayload = () => {
    const patient = getPatient();
    const doctor = getDoctor();
    const rx = getRxDraft(patient.patient_id);
    const dietaPreset = (qs('#mh_dieta')?.value || '').trim();
    const dietaTxt = (qs('#mh_dieta_txt')?.value || '').trim();
    const dieta = dietaPreset === 'otra' ? { preset: null, texto: dietaTxt } : { preset: dietaPreset || null, texto: null };
    return {
      section_id: 'hoja_indicaciones',
      ambito: 'hospitalizacion',
      contract_version: 1,
      dieta,
      soluciones_iv: (qs('#mh_sol_iv')?.value || '').trim(),
      ordenes_estudios: (qs('#mh_estudios_txt')?.value || '').trim(),
      cuidados_enfermeria: (qs('#mh_enfermeria_txt')?.value || '').trim(),
      interconsultas: (qs('#mh_inter_txt')?.value || '').trim(),
      receta: rx,
      snapshot: {
        paciente: patient,
        medico: doctor,
        hospital_stay: state.stay ? { id: state.stay.id, service: state.stay.service, room: state.stay.room, bed: state.stay.bed, started_at: state.stay.started_at } : null,
        generated_at: new Date().toISOString()
      }
    };
  };

  const bind = () => {
    if (ctxApi?.onContextChanged) ctxApi.onContextChanged(updateContextNotice);
    else document.addEventListener('mxmed:context:changed', updateContextNotice);

    qs('#mh_refresh_btn')?.addEventListener('click', refresh);

    qs('#mh_start_service')?.addEventListener('change', () => {
      const other = (qs('#mh_start_service')?.value || '') === 'Otro';
      const el = qs('#mh_start_service_other'); if (!el) return;
      el.style.display = other ? '' : 'none'; if (!other) el.value = '';
    });
    qs('#mh_start_btn')?.addEventListener('click', () => {
      const doc = getDoctor();
      const a = qs('#mh_start_attending'); if (a) a.value = doc.user_id;
    });
    qs('#mh_start_save')?.addEventListener('click', async () => {
      showErr([]);
      const patient = getPatient();
      const doc = getDoctor();
      const sel = (qs('#mh_start_service')?.value || '').trim();
      const service = sel === 'Otro' ? (qs('#mh_start_service_other')?.value || '').trim() : sel;
      const body = {
        patient_id: patient.patient_id,
        service: service || null,
        room: (qs('#mh_start_room')?.value || '').trim() || null,
        bed: (qs('#mh_start_bed')?.value || '').trim() || null,
        attending_user_id: (qs('#mh_start_attending')?.value || '').trim() || doc.user_id,
        admission_diagnosis: (qs('#mh_start_dx')?.value || '').trim() || null,
        admission_reason: (qs('#mh_start_reason')?.value || '').trim() || null,
      };
      try {
        const res = await api.startStay(body);
        state.stay = res?.stay || null;
        renderStay();
        await renderTimeline();
        try { bootstrap?.Modal?.getInstance(qs('#modalMhStart'))?.hide(); } catch (_) {}
      } catch (e) { showErr([e?.message || String(e)]); }
    });
    qs('#mh_close_confirm')?.addEventListener('click', async () => {
      showErr([]);
      const patient = getPatient();
      try {
        await api.closeStay({ patient_id: patient.patient_id, hospital_stay_id: state.stay?.id });
        state.stay = null;
        renderStay();
        await renderTimeline();
        try { bootstrap?.Modal?.getInstance(qs('#modalMhClose'))?.hide(); } catch (_) {}
      } catch (e) { showErr([e?.message || String(e)]); }
    });

    qs('#mh_sup_o2')?.addEventListener('change', () => {
      const on = !!qs('#mh_sup_o2')?.checked;
      const b = qs('#mh_o2_block'); if (b) b.style.display = on ? '' : 'none';
      if (!on) ['#mh_o2_tipo','#mh_o2_flujo','#mh_o2_fio2','#mh_o2_notas'].forEach(s => { const i = qs(s); if (i) i.value = ''; });
    });
    qs('#mh_sup_drains')?.addEventListener('change', () => {
      const on = !!qs('#mh_sup_drains')?.checked;
      const b = qs('#mh_drains_block'); if (b) b.style.display = on ? '' : 'none';
      if (!on) { const i = qs('#mh_drains_txt'); if (i) i.value = ''; }
    });

    qs('#mh_vitals_from_expl')?.addEventListener('click', () => {
      const set = (a, b) => { const el = qs(a); const v = document.getElementById(b)?.value ?? ''; if (el) el.value = String(v ?? '').trim(); };
      set('#mh_vitals_sys','exp_bp_sys'); set('#mh_vitals_dia','exp_bp_dia'); set('#mh_vitals_fc','exp_fc_value'); set('#mh_vitals_fr','exp_fr_value'); set('#mh_vitals_temp','exp_temp_value'); set('#mh_vitals_spo2','exp_spo2_value');
    });

    ['#mh_in_iv','#mh_in_vo','#mh_in_otros','#mh_out_diuresis','#mh_out_evacu','#mh_out_dren','#mh_out_otros'].forEach(s => qs(s)?.addEventListener('input', balance));

    document.getElementById('modalReceta')?.addEventListener('hidden.bs.modal', renderRx);

    qs('#mh_generate_orders')?.addEventListener('click', async () => {
      showErr([]);
      if (!state.stay?.id) { showErr(['No hay episodio activo. Inicia hospitalización para generar hoja de indicaciones.']); return; }
      const actor = getDoctor();
      const payload = buildOrdersPayload();
      const context = buildContext();
      try {
        const { document } = await api.saveDoc({ type: 'hoja_indicaciones', context, payload, actor });
        await renderTimeline();
        openDoc(document?.content?.rendered_text || '');
      } catch (e) { showErr([e?.message || String(e)]); }
    });

    qs('#mh_generate_note')?.addEventListener('click', async () => {
      showErr([]);
      if (!state.stay?.id) { showErr(['No hay episodio activo. Inicia hospitalización para generar nota.']); return; }
      const payload = buildHospNotePayload();
      const errs = [];
      if (!payload.evolucion_diaria?.nota) errs.push('Evolución/nota diaria es obligatoria.');
      if (!Array.isArray(payload.diagnosticos) || payload.diagnosticos.length === 0) errs.push('Diagnósticos activos es obligatorio.');
      const pr = payload.pronostico || {}; if (!(pr.preset || String(pr.texto || '').trim())) errs.push('Pronóstico es obligatorio.');
      if (!payload.plan_indicaciones) errs.push('Plan es obligatorio.');
      if (errs.length) { showErr(errs); return; }
      const actor = getDoctor();
      const context = buildContext();
      try {
        const { document } = await api.saveDoc({ type: 'nota_evolucion_hosp', context, payload, actor });
        await renderTimeline();
        openDoc(document?.content?.rendered_text || '');
      } catch (e) { showErr([e?.message || String(e)]); }
    });

    qs('#mh_event_save')?.addEventListener('click', async () => {
      const type = (qs('#mh_event_type')?.value || '').trim() || 'incidente';
      const desc = (qs('#mh_event_desc')?.value || '').trim();
      const labelMap = { fiebre:'Fiebre', hipotension:'Hipotensión', caida:'Caída', traslado:'Traslado', procedimiento:'Procedimiento', reaccion:'Reacción', incidente:'Incidente', otro:'Evento' };
      const label = labelMap[type] || 'Evento';
      if (desc || label) state.events.unshift({ type, label, descripcion: desc });
      if (qs('#mh_event_desc')) qs('#mh_event_desc').value = '';
      renderEvents();
      if (!state.stay?.id) return;
      const actor = getDoctor();
      const context = buildContext();
      const payload = { section_id:'evento', ambito:'hospitalizacion', event:{ type, label, descripcion: desc }, snapshot:{ paciente:getPatient(), medico:getDoctor(), hospital_stay:{ id: state.stay.id, service: state.stay.service, room: state.stay.room, bed: state.stay.bed, started_at: state.stay.started_at }, generated_at: new Date().toISOString() } };
      try { await api.saveDoc({ type: 'incidente', context, payload, actor }); await renderTimeline(); } catch (_) {}
    });

    qs('#mh_timeline')?.addEventListener('click', async (ev) => {
      const btn = ev.target.closest('[data-mh-action]'); if (!btn) return;
      const action = btn.getAttribute('data-mh-action'); const id = btn.getAttribute('data-mh-id'); if (!id) return;
      try {
        const { document } = await api.getDoc(id);
        const text = document?.content?.rendered_text || '';
        if (action === 'view') openDoc(text);
        if (action === 'print') printText(text);
      } catch (e) { showErr([e?.message || String(e)]); }
    });

    qs('#mh_doc_copy')?.addEventListener('click', async () => { try { await navigator.clipboard.writeText(qs('#mh_doc_text')?.value || ''); } catch (_) {} });
    qs('#mh_doc_print')?.addEventListener('click', () => printText(qs('#mh_doc_text')?.value || ''));
  };

  const mount = async () => {
    if (state.loaded) return;
    ensureCss();
    const fragment = getTemplateFragment();
    if (!fragment) {
      tab.innerHTML = '<div class="alert alert-danger">Plantilla de Manejo hospitalario no disponible.</div>';
      return;
    }
    tab.innerHTML = '';
    tab.appendChild(fragment);
    state.loaded = true;
    showDemoModeNotice();
    bind();
    await refresh();
  };

  const tabBtn = document.querySelector('[data-bs-target="#t-manejo"]');
  tabBtn?.addEventListener('shown.bs.tab', mount);
  if (tab.classList.contains('show') || tab.classList.contains('active')) mount();
})();
