// Recetas panel (UI + draft + generate) usable in both side panel y tab de expediente
(function(){
  const hostPanel = document.getElementById('p-pac-recetas');
  const hostTab = document.getElementById('t-tratamiento');
  const hosts = [hostPanel, hostTab].filter(Boolean);
  if (!hosts.length) return;

  const isDemo = window.location.hostname.endsWith('github.io');

  const ctxApi = window.mxmedContext || null;
  const ctxStorageKey = 'mxmed.activeContext';
  let root = null;

  const readStoredContext = () => {
    try { return JSON.parse(localStorage.getItem(ctxStorageKey) || '{}') || {}; } catch (_) { return {}; }
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

  const normalize = (str) => (str || '')
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, ' ')
    .trim();

  const isValidPatientId = (id) => !!id && id !== 'anon' && id !== 'paciente';

  const demoConsultorios = [
    { id: 'c-001', nombre: 'Consultorio Centro', domicilio: 'Av. Reforma 123, Col. Centro', telefono: '55 1234 5678' },
    { id: 'c-002', nombre: 'Clinica Norte', domicilio: 'Calle Norte 45, Col. Industrial', telefono: '55 2345 6789' },
    { id: 'c-003', nombre: 'Consultorio Sur', domicilio: 'Av. Insurgentes 900, Col. Del Valle', telefono: '55 3456 7890' }
  ];

  const medCatalog = [
    'Paracetamol 500 mg','Paracetamol 750 mg','Ibuprofeno 400 mg','Ibuprofeno 600 mg','Naproxeno 550 mg',
    'Diclofenaco 50 mg','Ketorolaco 10 mg','Acido acetilsalicilico 100 mg','Omeprazol 20 mg','Pantoprazol 40 mg',
    'Amoxicilina 500 mg','Amoxicilina/Acido clavulanico 875/125 mg','Azitromicina 500 mg','Claritromicina 500 mg','Cefalexina 500 mg',
    'Ceftriaxona 1 g','Metronidazol 500 mg','Ciprofloxacino 500 mg','Levofloxacino 500 mg','Doxiciclina 100 mg',
    'Loratadina 10 mg','Cetirizina 10 mg','Desloratadina 5 mg','Fexofenadina 120 mg','Salbutamol inhalador',
    'Budesonida inhalador','Beclometasona inhalador','Montelukast 10 mg','Prednisona 5 mg','Prednisona 20 mg',
    'Metformina 850 mg','Metformina 1000 mg','Glibenclamida 5 mg','Insulina NPH','Insulina glargina',
    'Enalapril 10 mg','Losartan 50 mg','Amlodipino 5 mg','Hidroclorotiazida 25 mg','Atorvastatina 20 mg',
    'Rosuvastatina 10 mg','Warfarina 5 mg','Apixaban 5 mg','Rivaroxaban 20 mg','Dabigatran 150 mg',
    'Heparina 5000 UI','Clopidogrel 75 mg','Tramadol 50 mg','Gabapentina 300 mg','Pregabalina 75 mg',
    'Sertralina 50 mg','Fluoxetina 20 mg','Escitalopram 10 mg','Alprazolam 0.5 mg','Clonazepam 0.5 mg'
  ];

  const interactionRules = [
    {
      id: 'nsaid_anticoag',
      groupA: ['ibuprofeno','diclofenaco','naproxeno','ketorolaco','acido acetilsalicilico'],
      groupB: ['warfarina','apixaban','rivaroxaban','dabigatran','heparina','acenocumarol'],
      warning: 'Aumenta riesgo de sangrado (AINE + anticoagulante).'
    },
    {
      id: 'ssri_tramadol',
      groupA: ['sertralina','fluoxetina','escitalopram'],
      groupB: ['tramadol'],
      warning: 'Riesgo de sindrome serotoninergico (ISRS + tramadol).'
    }
  ];

  const state = {
    loaded: false,
    draft: null,
    generated: null,
    warnings: []
  };

  const qs = (sel) => root?.querySelector(sel);

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
    const patientId = normalize([nombreCompleto, dob, sexoVal].join('|')) || 'anon';
    return { patient_id: patientId, nombre_completo: nombreCompleto, edad, sexo };
  };

  const getDoctor = () => {
    const nombre = document.querySelector('.user-id .name')?.textContent?.trim() || 'Medico';
    const cedula = document.getElementById('ced-prof')?.value?.trim() || '';
    const especialidad = document.getElementById('fs-esp')?.textContent?.trim() || '';
    return { user_id: normalize(nombre) || 'user', nombre_completo: nombre, cedula_profesional: cedula || '--', especialidad: especialidad || '--' };
  };

  const getDraftKey = (patientId) => `mxmed_rx_draft_${patientId || 'anon'}`;
  const getLegacyDraftKey = (patientId) => `mxmed_rx_draft_v1:${patientId || 'anon'}`;

  const loadDraft = () => {
    const ctx = getContextSafe();
    const pid = ctx?.patient_id || getPatient().patient_id;
    if (!pid) return null;
    try {
      const raw = localStorage.getItem(getDraftKey(pid));
      return raw ? JSON.parse(raw) : null;
    } catch (_) {
      return null;
    }
  };

  const saveDraft = (draft) => {
    const ctx = getContextSafe();
    const pid = ctx?.patient_id || getPatient().patient_id;
    if (!pid) return;
    try { localStorage.setItem(getDraftKey(pid), JSON.stringify(draft)); } catch (_) {}
    try {
      const meds = Array.isArray(draft?.rx?.items) ? draft.rx.items : [];
      localStorage.setItem(getLegacyDraftKey(pid), JSON.stringify({
        prescription_id: draft?.rx?.folio || `rx_${Date.now()}`,
        medicamentos: meds
      }));
    } catch (_) {}
  };

  const emptyDraft = () => ({
    rx: {
      items: [],
      indicaciones_generales: '',
      include_diagnosticos: false,
      diagnosticos: [],
      signature: { mode: 'fisica', image: null },
      consultorio: demoConsultorios[0],
      interactions: { enabled: false, warnings: [] },
      folio: '',
      qr_enabled: false
    },
    snapshot: {}
  });

  const buildFolio = () => {
    const dt = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    const date = `${dt.getFullYear()}${pad(dt.getMonth()+1)}${pad(dt.getDate())}`;
    const time = `${pad(dt.getHours())}${pad(dt.getMinutes())}`;
    const rnd = String(Math.floor(Math.random() * 9000) + 1000);
    return `RX-${date}-${time}-${rnd}`;
  };

  const renderCatalog = () => {
    const list = qs('#rx_med_catalog');
    if (!list) return;
    list.innerHTML = medCatalog.map(m => `<option value="${m}"></option>`).join('');
  };

  const renderConsultorios = () => {
    const sel = qs('#rx_consultorio');
    const meta = qs('#rx_consultorio_meta');
    if (!sel || !meta) return;
    sel.innerHTML = demoConsultorios.map(c => `<option value="${c.id}">${c.nombre}</option>`).join('');
    sel.addEventListener('change', () => {
      const c = demoConsultorios.find(x => x.id === sel.value) || demoConsultorios[0];
      meta.textContent = `${c.domicilio} · ${c.telefono}`;
    });
    sel.value = demoConsultorios[0]?.id || '';
    meta.textContent = `${demoConsultorios[0].domicilio} · ${demoConsultorios[0].telefono}`;
  };

  const rowHtml = (idx, item = {}) => {
    const val = (k) => (item[k] || '').toString().replace(/"/g, '&quot;');
    return `
      <tr data-rx-row="${idx}">
        <td>
          <div class="input-group input-group-sm">
            <input class="form-control rx-med" list="rx_med_catalog" placeholder="Medicamento" value="${val('medicamento')}">
            <span class="input-group-text rx-warn d-none" title="Interaccion">⚠️</span>
          </div>
        </td>
        <td><input class="form-control form-control-sm" placeholder="Dosis" value="${val('dosis')}"></td>
        <td><input class="form-control form-control-sm" placeholder="Via" value="${val('via')}"></td>
        <td><input class="form-control form-control-sm" placeholder="Periodicidad" value="${val('periodicidad')}"></td>
        <td><input class="form-control form-control-sm" placeholder="Duracion" value="${val('duracion')}"></td>
        <td><input class="form-control form-control-sm" placeholder="Indicaciones" value="${val('indicaciones')}"></td>
        <td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger" data-rx-del>&times;</button></td>
      </tr>
    `;
  };

  const renderRows = (items) => {
    const tbody = qs('#rx_rows');
    if (!tbody) return;
    const rows = Array.isArray(items) && items.length ? items : [{}];
    tbody.innerHTML = rows.map((it, idx) => rowHtml(idx, it)).join('');
  };

  const collectItems = () => {
    const tbody = qs('#rx_rows');
    if (!tbody) return [];
    const rows = Array.from(tbody.querySelectorAll('tr[data-rx-row]'));
    return rows.map((r) => {
      const cells = r.querySelectorAll('input.form-control');
      return {
        medicamento: (cells[0]?.value || '').trim(),
        dosis: (cells[1]?.value || '').trim(),
        via: (cells[2]?.value || '').trim(),
        periodicidad: (cells[3]?.value || '').trim(),
        duracion: (cells[4]?.value || '').trim(),
        indicaciones: (cells[5]?.value || '').trim()
      };
    }).filter((m) => Object.values(m).some(v => v));
  };

  const buildPayload = () => {
    const ctx = getContextSafe();
    const patient = getPatient();
    const doctor = getDoctor();
    const consultorioId = qs('#rx_consultorio')?.value || demoConsultorios[0].id;
    const consultorio = demoConsultorios.find(c => c.id === consultorioId) || demoConsultorios[0];
    const includeDx = !!qs('#rx_include_dx')?.checked;
    const dxLines = (qs('#rx_dx')?.value || '').split('\n').map(s => s.trim()).filter(Boolean);
    const signatureMode = (qs('#rx_firma_mode')?.value || 'fisica').trim();
    const sigImg = qs('#rx_firma_preview')?.dataset?.rxData || null;
    const qrEnabled = !!qs('#rx_qr_enabled')?.checked;
    const items = collectItems();
    const indic = (qs('#rx_indicaciones')?.value || '').trim();
    const folio = (qs('#rx_folio')?.textContent || '').trim() || buildFolio();

    return {
      section_id: 'receta',
      standard: 'interno',
      contract_version: 1,
      ambito: ctx?.care_setting || 'consulta',
      rx: {
        items,
        indicaciones_generales: indic,
        include_diagnosticos: includeDx,
        diagnosticos: dxLines.map(label => ({ code: null, label })),
        signature: { mode: signatureMode, image: signatureMode === 'digital' ? sigImg : null },
        consultorio,
        interactions: { enabled: state.warnings.length > 0, warnings: state.warnings.slice() },
        folio,
        qr_enabled: qrEnabled
      },
      snapshot: {
        paciente: patient,
        medico: doctor,
        consultorio,
        generated_at: new Date().toISOString()
      }
    };
  };

  const buildRenderedText = (payload) => {
    const p = payload || {};
    const rx = p.rx || {};
    const snap = p.snapshot || {};
    const pac = snap.paciente || {};
    const med = snap.medico || {};
    const cons = rx.consultorio || snap.consultorio || {};
    const dt = new Date();
    const dtStr = dt.toLocaleString('es-MX');
    const lines = [];

    lines.push('RECETA MEDICA');
    lines.push(`Fecha/Hora: ${dtStr}`);
    if (rx.folio) lines.push(`Folio: ${rx.folio}`);
    lines.push('');
    lines.push(`Medico: ${med.nombre_completo || 'No registrado'}`);
    lines.push(`Cedula: ${med.cedula_profesional || 'No registrado'} · Especialidad: ${med.especialidad || 'No registrado'}`);
    if (cons.nombre) lines.push(`Consultorio: ${cons.nombre}`);
    if (cons.domicilio || cons.telefono) lines.push(`Domicilio: ${cons.domicilio || '--'} · Tel: ${cons.telefono || '--'}`);
    lines.push('');
    lines.push(`Paciente: ${pac.nombre_completo || 'No registrado'} · Edad: ${pac.edad || '--'} · Sexo: ${pac.sexo || '--'}`);
    lines.push('');
    lines.push('MEDICAMENTOS');
    const items = Array.isArray(rx.items) ? rx.items : [];
    if (!items.length) {
      lines.push('No registrado');
    } else {
      items.forEach((m) => {
        const parts = [m.medicamento, m.dosis, m.via, m.periodicidad, m.duracion].filter(Boolean);
        const base = parts.join(' · ') || 'Medicamento';
        lines.push(`- ${base}${m.indicaciones ? ` (${m.indicaciones})` : ''}`);
      });
    }
    lines.push('');
    lines.push('INDICACIONES GENERALES');
    lines.push(rx.indicaciones_generales || 'No registrado');

    if (rx.include_diagnosticos) {
      lines.push('');
      lines.push('DIAGNOSTICO');
      const dx = Array.isArray(rx.diagnosticos) ? rx.diagnosticos : [];
      if (!dx.length) lines.push('No registrado');
      else dx.forEach((d) => lines.push(`- ${d.label || 'Diagnostico'}`));
    }

    lines.push('');
    if (rx.signature?.mode === 'digital' && rx.signature?.image) {
      lines.push('Firma digitalizada: adjunta');
    } else {
      lines.push('Firma: ______________________________');
    }

    if (rx.qr_enabled) {
      lines.push('');
      lines.push('[QR]');
    }

    return lines.join('\n');
  };

  const updateHeader = () => {
    const ctx = getContextSafe();
    const pid = ctx?.patient_id || '';
    const patient = getPatient();
    const doctor = getDoctor();
    const pText = `${patient.nombre_completo} · Edad: ${patient.edad} · Sexo: ${patient.sexo}`;
    const dText = `${doctor.nombre_completo} · Cedula: ${doctor.cedula_profesional} · ${doctor.especialidad}`;
    const boxP = qs('#rx_patient');
    const boxD = qs('#rx_doctor');
    if (boxP) boxP.textContent = pText;
    if (boxD) boxD.textContent = dText;
    const notice = qs('#rx_context_notice');
    if (notice) notice.classList.toggle('d-none', isValidPatientId(pid));
  };

  const updateStatus = (status) => {
    const st = qs('#rx_status');
    const folio = qs('#rx_folio');
    const dt = qs('#rx_datetime');
    if (!st || !folio || !dt) return;
    const now = new Date();
    if (status === 'generated') {
      st.textContent = 'Generada';
      st.className = 'badge text-bg-success';
      folio.textContent = folio.textContent || buildFolio();
      dt.textContent = now.toLocaleString('es-MX');
    } else {
      st.textContent = 'Borrador';
      st.className = 'badge text-bg-secondary';
      if (!folio.textContent) folio.textContent = buildFolio();
      if (!dt.textContent) dt.textContent = now.toLocaleString('es-MX');
    }
  };

  const analyzeInteractions = () => {
    const items = collectItems();
    const names = items.map((m) => normalize(m.medicamento));
    const warnIndices = new Set();
    const warnings = [];

    interactionRules.forEach((rule) => {
      const hasA = names.some(n => rule.groupA.some(k => n.includes(k)));
      const hasB = names.some(n => rule.groupB.some(k => n.includes(k)));
      if (hasA && hasB) {
        warnings.push(rule.warning);
        names.forEach((n, idx) => {
          if (rule.groupA.some(k => n.includes(k)) || rule.groupB.some(k => n.includes(k))) warnIndices.add(idx);
        });
      }
    });

    const rows = Array.from(qs('#rx_rows')?.querySelectorAll('tr[data-rx-row]') || []);
    rows.forEach((r, idx) => {
      const icon = r.querySelector('.rx-warn');
      if (!icon) return;
      icon.classList.toggle('d-none', !warnIndices.has(idx));
    });

    state.warnings = warnings.slice();
    const box = qs('#rx_interactions');
    if (box) {
      if (!warnings.length) box.textContent = 'Sin advertencias detectadas.';
      else box.innerHTML = `<ul class="mb-0">${warnings.map(w => `<li>${w}</li>`).join('')}</ul>`;
    }
  };

  const applyDraftToUI = (draft) => {
    const d = draft || emptyDraft();
    const rx = d.rx || {};
    const items = Array.isArray(rx.items) ? rx.items : [];
    const consultorioId = rx.consultorio?.id || demoConsultorios[0].id;
    const sel = qs('#rx_consultorio');
    if (sel) sel.value = consultorioId;
    const meta = qs('#rx_consultorio_meta');
    const c = demoConsultorios.find(x => x.id === consultorioId) || demoConsultorios[0];
    if (meta) meta.textContent = `${c.domicilio} · ${c.telefono}`;

    renderRows(items);

    const incDx = qs('#rx_include_dx');
    const dxBlock = qs('#rx_dx_block');
    const dxTa = qs('#rx_dx');
    if (incDx) incDx.checked = !!rx.include_diagnosticos;
    if (dxBlock) dxBlock.classList.toggle('d-none', !rx.include_diagnosticos);
    if (dxTa) dxTa.value = (rx.diagnosticos || []).map(d => d.label || '').filter(Boolean).join('\n');

    const indic = qs('#rx_indicaciones');
    if (indic) indic.value = rx.indicaciones_generales || '';

    const sigSel = qs('#rx_firma_mode');
    const sigWrap = qs('#rx_firma_digital');
    const sigPrev = qs('#rx_firma_preview');
    const sigFile = qs('#rx_firma_file');
    if (sigSel) sigSel.value = rx.signature?.mode || 'fisica';
    if (sigWrap) sigWrap.classList.toggle('d-none', sigSel?.value !== 'digital');
    if (sigPrev) {
      if (rx.signature?.image) {
        sigPrev.src = rx.signature.image;
        sigPrev.dataset.rxData = rx.signature.image;
        sigPrev.classList.remove('d-none');
      } else {
        sigPrev.classList.add('d-none');
        sigPrev.src = '';
        sigPrev.dataset.rxData = '';
      }
    }
    if (sigFile) sigFile.value = '';

    const qr = qs('#rx_qr_enabled');
    if (qr) qr.checked = !!rx.qr_enabled;

    const folio = qs('#rx_folio');
    if (folio) folio.textContent = rx.folio || buildFolio();

    updateStatus(d.generated ? 'generated' : 'draft');
  };

  const saveCurrentDraft = () => {
    const payload = buildPayload();
    const draft = {
      rx: payload.rx,
      snapshot: payload.snapshot,
      generated: false,
      updated_at: new Date().toISOString()
    };
    saveDraft(draft);
    state.draft = draft;
    updateStatus('draft');
  };

  const showPreview = () => {
    const payload = buildPayload();
    const text = buildRenderedText(payload);
    const ta = qs('#rx_preview_text');
    if (ta) ta.value = text;
    try { bootstrap?.Modal?.getOrCreateInstance(qs('#modalRecetaPreview'))?.show(); } catch (_) {}
  };

  const printPreview = () => {
    const payload = buildPayload();
    const text = buildRenderedText(payload);
    const w = window.open('', '_blank');
    if (!w) return;
    w.document.write(`<pre style="white-space:pre-wrap;font-family:ui-monospace,Menlo,Consolas,monospace;font-size:12px">${text.replace(/</g, '&lt;')}</pre>`);
    w.document.close(); w.focus(); w.print();
  };

  const generatePrescription = async () => {
    const ctx = getContextSafe();
    const patient = getPatient();
    const payload = buildPayload();
    if (!isValidPatientId(ctx?.patient_id || patient.patient_id)) {
      updateHeader();
      return;
    }
    const context = {
      patient_id: ctx?.patient_id || patient.patient_id,
      encounter_id: ctx?.encounter_id || null,
      hospital_stay_id: ctx?.hospital_stay_id || null,
      care_setting: ctx?.care_setting || 'consulta',
      service: ctx?.service || null
    };
    const actor = getDoctor();
    try {
      const url = isDemo ? 'mock/prescription-generate.json' : 'api/prescription-generate.php';
      const res = await fetch(url, isDemo ? { method: 'GET', headers: {} } : {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ context, actor, payload })
      });
      const data = await res.json().catch(() => null);
      if (!res.ok || !data?.ok) throw new Error(data?.error || 'Error');
      state.generated = { id: data.document_id, uuid: data.document_uuid };
      updateStatus('generated');
      saveCurrentDraft();
    } catch (e) {
      const box = qs('#rx_errors');
      if (box) {
        box.classList.remove('d-none');
        box.textContent = `No se pudo generar: ${e?.message || 'error'}`;
      }
    }
  };

  const syncContextFromPatient = () => {
    const patient = getPatient();
    const pid = isValidPatientId(patient.patient_id) ? patient.patient_id : null;
    setContextSafe({ patient_id: pid, care_setting: 'consulta' });
    updateHeader();
  };

  const bindPatientInputs = () => {
    const pane = document.getElementById('p-expediente');
    if (!pane) return;
    const inputs = [
      pane.querySelector('[data-pac-nombre]'),
      pane.querySelector('[data-pac-apellido-paterno]'),
      pane.querySelector('[data-pac-apellido-materno]'),
      pane.querySelector('[data-dg-dia]'),
      pane.querySelector('[data-dg-mes]'),
      pane.querySelector('[data-dg-anio]')
    ].filter(Boolean);
    inputs.forEach(inp => {
      inp.addEventListener('input', syncContextFromPatient);
      inp.addEventListener('change', syncContextFromPatient);
    });
    pane.querySelectorAll('input[name="pac-genero"]').forEach(inp => {
      inp.addEventListener('change', syncContextFromPatient);
    });
  };

  const bindUI = () => {
    const addBtn = qs('#rx_add_row');
    const rows = qs('#rx_rows');
    const includeDx = qs('#rx_include_dx');
    const dxBlock = qs('#rx_dx_block');
    const sigMode = qs('#rx_firma_mode');
    const sigWrap = qs('#rx_firma_digital');
    const sigFile = qs('#rx_firma_file');
    const sigPrev = qs('#rx_firma_preview');

    addBtn?.addEventListener('click', () => {
      const items = collectItems();
      items.push({});
      renderRows(items);
    });

    rows?.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-rx-del]');
      if (!btn) return;
      btn.closest('tr')?.remove();
    });

    includeDx?.addEventListener('change', () => {
      if (dxBlock) dxBlock.classList.toggle('d-none', !includeDx.checked);
    });

    sigMode?.addEventListener('change', () => {
      if (sigWrap) sigWrap.classList.toggle('d-none', sigMode.value !== 'digital');
      if (sigMode.value !== 'digital' && sigPrev) {
        sigPrev.classList.add('d-none');
        sigPrev.src = '';
        sigPrev.dataset.rxData = '';
      }
    });

    sigFile?.addEventListener('change', () => {
      const file = sigFile.files?.[0];
      if (!file || !sigPrev) return;
      const reader = new FileReader();
      reader.onload = () => {
        sigPrev.src = reader.result || '';
        sigPrev.dataset.rxData = reader.result || '';
        sigPrev.classList.remove('d-none');
      };
      reader.readAsDataURL(file);
    });

    qs('#rx_save_draft')?.addEventListener('click', saveCurrentDraft);
    qs('#rx_preview')?.addEventListener('click', showPreview);
    qs('#rx_generate')?.addEventListener('click', generatePrescription);
    qs('#rx_print')?.addEventListener('click', printPreview);
    qs('#rx_analyze')?.addEventListener('click', analyzeInteractions);

    if (ctxApi?.onContextChanged) ctxApi.onContextChanged(updateHeader);
    else document.addEventListener('mxmed:context:changed', updateHeader);
  };

  const loadPartial = async () => {
    if (root) return;
    const res = await fetch('assets/partials/recetas.html', { cache: 'no-store' }).catch(() => null);
    const html = res ? await res.text().catch(() => '') : '';
    root = document.createElement('div');
    root.innerHTML = html || '<div class="alert alert-danger">No se pudo cargar el panel de recetas.</div>';
  };

  const moveToHost = (host) => {
    if (!host || !root) return;
    if (root.parentElement === host) return;
    host.innerHTML = '';
    host.appendChild(root);
  };

  const chooseInitialHost = () => {
    const visible = hosts.find(h => h && !h.classList.contains('d-none') && (h.classList.contains('show') || h.classList.contains('active')));
    return visible || hosts[0];
  };

  const mount = async (targetHost) => {
    if (state.loaded) {
      moveToHost(targetHost || chooseInitialHost());
      return;
    }
    await loadPartial();
    moveToHost(targetHost || chooseInitialHost());
    state.loaded = true;

    renderCatalog();
    renderConsultorios();
    bindUI();
    bindPatientInputs();

    const draft = loadDraft();
    state.draft = draft || emptyDraft();
    applyDraftToUI(state.draft);
    syncContextFromPatient();
    updateHeader();
  };

  const watchNavigation = () => {
    document.addEventListener('click', (e) => {
      const btnPanel = e.target.closest('[data-panel="p-pac-recetas"]');
      if (btnPanel) mount(hostPanel);
    });
    document.addEventListener('shown.bs.tab', (e) => {
      const tgt = e.target?.getAttribute('data-bs-target') || '';
      if (tgt === '#t-tratamiento') mount(hostTab);
    });
  };

  watchNavigation();
  mount(chooseInitialHost());
})();
