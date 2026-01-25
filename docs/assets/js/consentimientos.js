// Consentimiento informado (MVP: lista + wizard pasos 1-2)
(function(){
  const root = document.getElementById('t-consent');
  if (!root) return;

  const ctxApi = window.mxmedContext || null;
  const ctxStorageKey = 'mxmed.activeContext';
  const isDemo = window.location.hostname.endsWith('github.io');

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
    prev: root.querySelector('#ci_prev'),
    next: root.querySelector('#ci_next'),
    save: root.querySelector('#ci_save'),
    cancel: root.querySelector('#ci_cancel'),
    pacNombre: root.querySelector('#ci_pac_nombre'),
    pacEdad: root.querySelector('#ci_pac_edad'),
    pacSexo: root.querySelector('#ci_pac_sexo'),
    pacTel: root.querySelector('#ci_pac_tel'),
    pacMail: root.querySelector('#ci_pac_mail'),
    pacDom: root.querySelector('#ci_pac_dom'),
    updatePatient: root.querySelector('#ci_update_patient'),
    template: root.querySelector('#ci_template'),
    procedimiento: root.querySelector('#ci_procedimiento'),
    motivo: root.querySelector('#ci_motivo'),
    objetivo: root.querySelector('#ci_objetivo'),
    templateDesc: root.querySelector('#ci_template_desc')
  };

  const state = {
    step: 1,
    templates: [],
    patient: null,
    patientFields: {},
    initialContact: { telefono: '', correo: '', domicilio: '' },
    masterContact: { telefono: '', correo: '', domicilio: '' },
    masterEmpty: { telefono: true, correo: true, domicilio: true },
    consentId: null,
    folio: ''
  };

  const readStoredContext = () => {
    try { return JSON.parse(localStorage.getItem(ctxStorageKey) || '{}') || {}; } catch (_) { return {}; }
  };
  const getContextSafe = () => (ctxApi?.getContext ? ctxApi.getContext() : (readStoredContext() || {}));
  const isValidPatientId = (id) => !!id && id !== 'anon' && id !== 'paciente';

  const normalize = (str) => (str || '')
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, ' ')
    .trim();

  const api = (() => {
    const demoFetchJson = async (path) => {
      const res = await fetch(path, { method: 'GET', headers: {} });
      const data = await res.json().catch(() => null);
      if (!res.ok) {
        const err = new Error(`HTTP ${res.status}`);
        err.status = res.status;
        throw err;
      }
      return data || {};
    };
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
    const patchWithFallback = async (primaryUrl, fallbackUrl, payload) => {
      try {
        return await fetchJson(primaryUrl, { method: 'PATCH', body: JSON.stringify(payload || {}) });
      } catch (e) {
        if (e?.status === 404 || e?.status === 405) {
          return fetchJson(fallbackUrl, { method: 'PATCH', body: JSON.stringify(payload || {}) });
        }
        throw e;
      }
    };
    return {
      list: (patientId, limit = 20) => {
        if (isDemo) return demoFetchJson('mock/ci-list.json');
        const url = `api/ci/list?patient_id=${encodeURIComponent(String(patientId || ''))}&limit=${encodeURIComponent(String(limit || 20))}`;
        return fetchJson(url, { method: 'GET', headers: {} });
      },
      templates: () => {
        if (isDemo) return demoFetchJson('mock/ci-templates.json');
        return fetchJson('api/ci/templates', { method: 'GET', headers: {} });
      },
      createDraft: (payload) => {
        if (isDemo) return demoFetchJson('mock/ci-draft.json');
        return fetchJson('api/ci/draft', {
          method: 'POST',
          body: JSON.stringify(payload || {})
        });
      },
      saveStep1: (id, payload) => {
        if (isDemo) return demoFetchJson('mock/ci-step1.json');
        return patchWithFallback(
          `api/ci/${encodeURIComponent(String(id))}/step1`,
          `api/ci/step1?id=${encodeURIComponent(String(id))}`,
          payload
        );
      },
      saveStep2: (id, payload) => {
        if (isDemo) return demoFetchJson('mock/ci-step2.json');
        return patchWithFallback(
          `api/ci/${encodeURIComponent(String(id))}/step2`,
          `api/ci/step2?id=${encodeURIComponent(String(id))}`,
          payload
        );
      }
    };
  })();

  const findFieldByLabel = (tab, labelText) => {
    if (!tab) return null;
    const labels = Array.from(tab.querySelectorAll('label.form-label'));
    const label = labels.find(l => normalize(l.textContent).includes(normalize(labelText)));
    if (!label) return null;
    const wrap = label.closest('div');
    return wrap?.querySelector('input.form-control, textarea.form-control, select.form-select') || null;
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
    const patientId = normalize([nombreCompleto, dob, sexoVal].join('|')) || 'anon';
    return { patient_id: patientId, nombre_completo: nombreCompleto, edad, sexo };
  };

  const getDoctor = () => {
    const nombre = document.querySelector('.user-id .name')?.textContent?.trim() || 'Médico';
    const cedula = document.getElementById('ced-prof')?.value?.trim() || '';
    const especialidad = document.getElementById('fs-esp')?.textContent?.trim() || '';
    return {
      medico_id: normalize(nombre) || 'user',
      nombre_completo: nombre,
      cedula_profesional: cedula || '--',
      especialidad: especialidad || '--'
    };
  };

  const getConsultorio = () => {
    const nombre = document.getElementById('cons-titulo')?.value?.trim() || 'Consultorio';
    const calle = document.getElementById('cons-calle')?.value?.trim() || '';
    const numext = document.getElementById('cons-numext')?.value?.trim() || '';
    const numint = document.getElementById('cons-numint')?.value?.trim() || '';
    const piso = document.getElementById('cons-piso')?.value?.trim() || '';
    const tel = document.getElementById('cons-tel1')?.value?.trim() || '';
    const wa = document.getElementById('cons-wa')?.value?.trim() || '';
    const domicilioParts = [calle, numext ? `#${numext}` : '', numint ? `Int ${numint}` : '', piso ? `Piso ${piso}` : ''].filter(Boolean);
    return {
      consultorio_id: normalize([nombre, calle].join('|')) || 'cons',
      nombre,
      domicilio: domicilioParts.join(', '),
      telefono: wa || tel
    };
  };

  const getPatientContact = () => {
    const tab = document.getElementById('t-datos');
    const tel = findFieldByLabel(tab, 'Teléfono Celular') || findFieldByLabel(tab, 'Teléfono');
    const mail = findFieldByLabel(tab, 'Correo electrónico');
    const calle = findFieldByLabel(tab, 'Calle');
    const numext = findFieldByLabel(tab, '# ext') || findFieldByLabel(tab, 'Número Ext');
    const numint = findFieldByLabel(tab, '# int') || findFieldByLabel(tab, 'Número Int');
    const colonia = findFieldByLabel(tab, 'Colonia');
    const municipio = findFieldByLabel(tab, 'Municipio');
    const estado = findFieldByLabel(tab, 'Estado');
    const cp = findFieldByLabel(tab, 'Código Postal');

    const domicilioParts = [
      (calle?.value || '').trim(),
      (numext?.value || '').trim() ? `#${numext.value.trim()}` : '',
      (numint?.value || '').trim() ? `Int ${numint.value.trim()}` : '',
      (colonia?.value || '').trim(),
      (municipio?.value || '').trim(),
      (estado?.value || '').trim(),
      (cp?.value || '').trim() ? `CP ${cp.value.trim()}` : ''
    ].filter(Boolean);

    return {
      telefono: tel?.value?.trim() || '',
      correo: mail?.value?.trim() || '',
      domicilio: domicilioParts.join(', '),
      fields: { tel, mail, calle, numext, numint, colonia, municipio, estado, cp }
    };
  };

  const setPatientContactFields = (values) => {
    const f = state.patientFields;
    if (f.tel && values.telefono !== undefined) {
      f.tel.value = values.telefono || '';
      f.tel.dispatchEvent(new Event('input', { bubbles: true }));
    }
    if (f.mail && values.correo !== undefined) {
      f.mail.value = values.correo || '';
      f.mail.dispatchEvent(new Event('input', { bubbles: true }));
    }
    if (f.calle && values.domicilio !== undefined) {
      f.calle.value = values.domicilio || '';
      f.calle.dispatchEvent(new Event('input', { bubbles: true }));
    }
  };

  const markRequired = (input, required) => {
    if (!input) return;
    input.required = !!required;
    const label = root.querySelector(`label[for="${input.id}"]`);
    if (!label) return;
    const base = label.dataset.baseLabel || label.textContent.replace(/\s*\*$/, '');
    label.dataset.baseLabel = base;
    label.textContent = required ? `${base} *` : base;
  };

  const applyFieldLocks = () => {
    const allowUpdate = !!els.updatePatient?.checked;
    if (els.pacTel) els.pacTel.disabled = !state.masterEmpty.telefono && !allowUpdate;
    if (els.pacMail) els.pacMail.disabled = !state.masterEmpty.correo && !allowUpdate;
    if (els.pacDom) els.pacDom.disabled = !state.masterEmpty.domicilio && !allowUpdate;
  };

  const showWizard = () => {
    if (!state.consentId) return;
    els.wizard?.classList.remove('d-none');
    els.cancel?.classList.remove('d-none');
    state.step = 1;
    showNotice('');
    renderStep();
    resetStep2();
    fillStep1();
    loadTemplates();
  };

  const hideWizard = () => {
    els.wizard?.classList.add('d-none');
    els.cancel?.classList.add('d-none');
    state.step = 1;
    state.consentId = null;
    state.folio = '';
  };

  const renderStep = () => {
    const isStep1 = state.step === 1;
    if (els.step1) els.step1.classList.toggle('d-none', !isStep1);
    if (els.step2) els.step2.classList.toggle('d-none', isStep1);
    if (els.prev) els.prev.disabled = isStep1;
    if (els.next) els.next.classList.toggle('d-none', !isStep1);
    if (els.save) els.save.classList.toggle('d-none', isStep1);
    if (els.stepLabel) els.stepLabel.textContent = `Paso ${state.step} de 2`;
  };

  const resetStep2 = () => {
    if (els.template) els.template.value = '';
    if (els.procedimiento) els.procedimiento.value = '';
    if (els.motivo) els.motivo.value = '';
    if (els.objetivo) els.objetivo.value = '';
    describeTemplate('');
  };

  const fillStep1 = () => {
    const patient = state.patient || getPatient();
    state.patient = patient;
    const contact = getPatientContact();
    state.patientFields = contact.fields || {};

    const master = state.masterContact || { telefono: '', correo: '', domicilio: '' };
    const telVal = master.telefono || contact.telefono || '';
    const mailVal = master.correo || contact.correo || '';
    const domVal = master.domicilio || contact.domicilio || '';

    state.initialContact = {
      telefono: master.telefono || '',
      correo: master.correo || '',
      domicilio: master.domicilio || ''
    };
    state.masterEmpty = {
      telefono: !master.telefono,
      correo: !master.correo,
      domicilio: !master.domicilio
    };

    if (els.pacNombre) els.pacNombre.value = patient.nombre_completo;
    if (els.pacEdad) els.pacEdad.value = patient.edad;
    if (els.pacSexo) els.pacSexo.value = patient.sexo;
    if (els.pacTel) els.pacTel.value = telVal;
    if (els.pacMail) els.pacMail.value = mailVal;
    if (els.pacDom) els.pacDom.value = domVal;

    const hasMaster = !state.masterEmpty.telefono || !state.masterEmpty.correo || !state.masterEmpty.domicilio;
    const toggleWrap = els.updatePatient?.closest('.form-check');
    if (toggleWrap) toggleWrap.classList.toggle('d-none', !hasMaster);
    if (els.updatePatient) els.updatePatient.checked = false;

    markRequired(els.pacTel, state.masterEmpty.telefono);
    markRequired(els.pacMail, state.masterEmpty.correo);
    markRequired(els.pacDom, state.masterEmpty.domicilio);

    applyFieldLocks();
  };

  const renderTemplates = () => {
    if (!els.template) return;
    els.template.innerHTML = '<option value="">Selecciona una plantilla</option>';
    state.templates.forEach(t => {
      const opt = document.createElement('option');
      opt.value = t.template_id;
      opt.textContent = t.nombre || t.template_id;
      els.template.appendChild(opt);
    });
  };

  const describeTemplate = (templateId) => {
    const t = state.templates.find(x => x.template_id === templateId);
    if (!els.templateDesc) return;
    if (!t) {
      els.templateDesc.textContent = 'Selecciona una plantilla para ver sus riesgos, beneficios y alternativas.';
      return;
    }
    const bloques = t.template_json?.bloques || {};
    const riesgos = (bloques.riesgos || []).join(' · ') || '—';
    const beneficios = (bloques.beneficios || []).join(' · ') || '—';
    const alternativas = (bloques.alternativas || []).join(' · ') || '—';
    els.templateDesc.innerHTML = `<strong>${t.nombre}</strong><br>Riesgos: ${riesgos}<br>Beneficios: ${beneficios}<br>Alternativas: ${alternativas}`;
    const sugerido = t.template_json?.procedimiento_sugerido;
    if (sugerido && els.procedimiento && !els.procedimiento.value.trim()) {
      els.procedimiento.value = String(sugerido).trim();
      els.procedimiento.dispatchEvent(new Event('input', { bubbles: true }));
    }
  };

  const loadTemplates = async () => {
    if (state.templates.length) return;
    try {
      const res = await api.templates();
      state.templates = Array.isArray(res.items) ? res.items : [];
      renderTemplates();
    } catch (e) {
      // Silencioso para MVP
    }
  };

  const renderList = (items) => {
    if (!els.list || !els.empty) return;
    els.list.innerHTML = '';
    if (!items.length) {
      els.empty.classList.remove('d-none');
      return;
    }
    els.empty.classList.add('d-none');
    items.forEach(item => {
      const div = document.createElement('div');
      div.className = 'exp-card exp-card--secondary';
      const fecha = item.started_at ? new Date(item.started_at.replace(' ', 'T')) : null;
      const fechaTxt = fecha && !isNaN(fecha) ? fecha.toLocaleString('es-MX') : (item.started_at || '—');
      div.innerHTML = `
        <div class="exp-card-title d-flex align-items-center justify-content-between gap-2">
          <span>${item.folio || 'Sin folio'}</span>
          <span class="badge bg-light text-dark border">${item.estado || 'draft'}</span>
        </div>
        <div class="small text-muted">${fechaTxt}</div>
      `;
      els.list.appendChild(div);
    });
  };

  const loadList = async () => {
    const ctx = getContextSafe();
    const patient = getPatient();
    const pid = ctx?.patient_id || patient.patient_id;
    const ok = isValidPatientId((pid || '').trim());
    if (els.ctxNotice) els.ctxNotice.classList.toggle('d-none', ok);
    if (!ok) {
      renderList([]);
      return;
    }
    try {
      const res = await api.list(pid, 50);
      renderList(Array.isArray(res.items) ? res.items : []);
    } catch (e) {
      renderList([]);
    }
  };

  const validateStep1 = () => {
    const errs = [];
    const tel = (els.pacTel?.value || '').trim();
    const mail = (els.pacMail?.value || '').trim();
    const dom = (els.pacDom?.value || '').trim();
    if (state.masterEmpty.telefono && !tel) errs.push('Teléfono requerido.');
    if (state.masterEmpty.correo && !mail) errs.push('Correo requerido.');
    if (state.masterEmpty.domicilio && !dom) errs.push('Domicilio requerido.');
    if (mail && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(mail)) errs.push('Correo inválido.');
    return errs;
  };

  const validateStep2 = () => {
    const errs = [];
    if (!els.template?.value) errs.push('Selecciona una plantilla.');
    if (!(els.procedimiento?.value || '').trim()) errs.push('Indica el procedimiento.');
    if (!(els.objetivo?.value || '').trim()) errs.push('Indica el objetivo.');
    return errs;
  };

  const showNotice = (msg) => {
    if (!els.notice) return;
    els.notice.textContent = msg;
    els.notice.classList.toggle('d-none', !msg);
  };

  const createDraft = async () => {
    const ctx = getContextSafe();
    const patient = getPatient();
    const pid = ctx?.patient_id || patient.patient_id;
    if (!isValidPatientId((pid || '').trim())) {
      if (els.ctxNotice) els.ctxNotice.classList.remove('d-none');
      showNotice('Selecciona paciente antes de crear el borrador.');
      return;
    }
    const doctor = getDoctor();
    const consultorio = getConsultorio();
    const contact = getPatientContact();
    try {
      const res = await api.createDraft({
        patient_id: pid,
        medico_id: doctor.medico_id,
        consultorio_id: consultorio.consultorio_id,
        origin_type: 'expediente',
        patient: {
          ...patient,
          telefono: contact.telefono,
          correo: contact.correo,
          domicilio: contact.domicilio
        },
        doctor,
        consultorio
      });
      state.consentId = res?.consentimiento_id || null;
      state.folio = res?.folio || '';
      state.masterContact = res?.patient_contact || { telefono: '', correo: '', domicilio: '' };
      state.patient = patient;
      loadList();
      showWizard();
      return res;
    } catch (e) {
      showNotice(e.message || 'No se pudo crear el borrador.');
    }
  };

  const saveStep1 = async () => {
    if (!state.consentId) {
      showNotice('Primero crea un borrador.');
      return;
    }
    const errs = validateStep1();
    if (errs.length) {
      showNotice(errs.join(' '));
      return;
    }
    const patient = state.patient || getPatient();
    const doctor = getDoctor();
    const tel = (els.pacTel?.value || '').trim();
    const mail = (els.pacMail?.value || '').trim();
    const dom = (els.pacDom?.value || '').trim();
    const allowUpdate = !!els.updatePatient?.checked;
    const flags = {
      telefono: state.masterEmpty.telefono ? true : allowUpdate,
      correo: state.masterEmpty.correo ? true : allowUpdate,
      domicilio: state.masterEmpty.domicilio ? true : allowUpdate
    };
    showNotice('');
    try {
      await api.saveStep1(state.consentId, {
        consentimiento_id: state.consentId,
        telefono: tel,
        correo: mail,
        domicilio: dom,
        flags_update_master: flags,
        medico_id: doctor.medico_id,
        patient_snapshot: {
          patient_key: getContextSafe()?.patient_id || patient.patient_id,
          nombre_completo: patient.nombre_completo,
          edad: patient.edad,
          sexo: patient.sexo
        }
      });
      if (flags.telefono || flags.correo || flags.domicilio) {
        setPatientContactFields({ telefono: tel, correo: mail, domicilio: dom });
        state.masterContact = { telefono: tel, correo: mail, domicilio: dom };
        state.masterEmpty = { telefono: !tel, correo: !mail, domicilio: !dom };
      }
      state.step = 2;
      renderStep();
    } catch (e) {
      showNotice(e.message || 'Error al guardar Paso 1.');
    }
  };

  const saveStep2 = async () => {
    if (!state.consentId) {
      showNotice('Primero crea un borrador.');
      return;
    }
    const errs = validateStep2();
    if (errs.length) {
      showNotice(errs.join(' '));
      return;
    }
    showNotice('');
    try {
      await api.saveStep2(state.consentId, {
        consentimiento_id: state.consentId,
        template_id: els.template?.value || '',
        procedimiento: (els.procedimiento?.value || '').trim(),
        motivo: (els.motivo?.value || '').trim(),
        objetivo: (els.objetivo?.value || '').trim()
      });
      loadList();
      hideWizard();
    } catch (e) {
      showNotice(e.message || 'Error al guardar el borrador.');
    }
  };

  if (els.newBtn) els.newBtn.addEventListener('click', () => {
    createDraft();
  });
  if (els.cancel) els.cancel.addEventListener('click', () => {
    hideWizard();
  });
  if (els.next) els.next.addEventListener('click', () => {
    saveStep1();
  });
  if (els.prev) els.prev.addEventListener('click', () => {
    state.step = 1;
    renderStep();
  });
  if (els.save) els.save.addEventListener('click', () => saveStep2());
  if (els.updatePatient) els.updatePatient.addEventListener('change', applyFieldLocks);
  if (els.template) els.template.addEventListener('change', (e) => describeTemplate(e.target.value));

  try {
    window.mxmedContext?.onContextChanged?.(() => loadList());
  } catch (_) {}

  loadList();
})();
