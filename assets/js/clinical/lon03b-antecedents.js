// LON03B: patient-level longitudinal UI. Never starts an encounter or writes legacy drafts.
(function () {
  const pane = document.getElementById('p-expediente');
  const root = document.getElementById('lon03b-antecedents');
  if (!pane || !root) return;
  const $ = selector => root.querySelector(selector);
  const categories = [
    ['PERSONAL_PATHOLOGICAL', 'Personales patológicos'],
    ['PERSONAL_NON_PATHOLOGICAL', 'Personales no patológicos'],
    ['SURGICAL', 'Quirúrgicos'], ['FAMILY', 'Familiares'],
    ['HABITS', 'Hábitos'], ['VACCINATION', 'Vacunación'],
    ['GYNECOLOGICAL', 'Ginecológicos'], ['OTHER', 'Otros']
  ];
  const categoryName = Object.fromEntries(categories);
  const provenanceName = {
    EXPLICIT_LONGITUDINAL_ENTRY: 'Registrado longitudinalmente',
    PATIENT_REPORTED: 'Reportado por el paciente',
    ENCOUNTER_DERIVED_EXPLICIT_PROMOTION: 'Promovido explícitamente desde una consulta',
    LEGACY_IMPORTED_CONFIRMED: 'Registro anterior confirmado',
    EXTERNAL_SOURCE: 'Fuente externa verificada'
  };
  const reviewName = {
    UNKNOWN: 'Sin revisar', NEEDS_REVIEW: 'Revisión pendiente',
    CONFIRMED_NONE: 'Sin datos conocidos, confirmado',
    REVIEWED_WITH_FACTS: 'Datos revisados', REVIEWED_WITH_ALLERGIES: 'Alergias revisadas'
  };
  const getPatient = () => String(pane.dataset.patientId || pane.dataset.activePatientId || '').trim();
  const show = (node, visible) => node.classList.toggle('d-none', !visible);
  const text = (value, fallback = 'No disponible') => String(value ?? '').trim() || fallback;
  const date = value => text(value, 'Fecha no disponible').replace('T', ' ');
  const status = $('[data-lon03b-status]');
  const error = $('[data-lon03b-error]');
  const content = $('[data-lon03b-content]');
  const dialog = $('[data-lon03b-dialog]');
  const form = $('[data-lon03b-form]');
  let patientId = '';
  let epoch = 0;
  let facts = [];
  let reviews = [];
  let allergies = [];
  let allergyReview = null;
  let editing = null;
  let returnFocus = null;
  const reviewRequests = new Map();

  function endpoint(resource, id = '') {
    const suffix = id ? `/${String(id).split('/').map(encodeURIComponent).join('/')}` : '';
    return `/api/clinical/index.php/patients/${encodeURIComponent(patientId)}/longitudinal/${resource}${suffix}`;
  }
  async function request(resource, {method = 'GET', id = '', body = null, key = null} = {}) {
    const headers = {Accept: 'application/json'};
    if (body !== null) headers['Content-Type'] = 'application/json';
    if (key) headers['Idempotency-Key'] = key;
    let response;
    try {
      response = await fetch(endpoint(resource, id), {method, credentials: 'same-origin', headers, body: body === null ? undefined : JSON.stringify(body)});
    } catch (_) {
      throw {code: 'NETWORK_ERROR', message: 'No se pudo conectar. Tu borrador se conserva; puedes reintentar.'};
    }
    const result = await response.json().catch(() => null);
    if (!response.ok || result?.ok !== true) {
      const code = typeof result?.error === 'string' ? result.error : result?.error?.code || 'REQUEST_FAILED';
      throw {code, message: code === 'STALE_VERSION' ? 'Otro profesional modificó este registro.' :
        code === 'IDEMPOTENCY_PAYLOAD_CONFLICT' ? 'Esta solicitud ya se usó con otro contenido. Cierra y vuelve a abrir el formulario para iniciar un cambio nuevo.' :
        code === 'M6_WRITE_WINDOW_BLOCKED' ? 'Las escrituras clínicas están pausadas. Tu borrador se conserva.' :
        code === 'LON03A_WRITE_DISABLED' ? 'La edición longitudinal aún no está habilitada en este entorno. Tu borrador se conserva.' :
        code === 'NOT_FOUND' ? 'Este paciente o registro no está disponible en tu ámbito.' :
        text(result?.message, 'No se pudo completar la solicitud.')};
    }
    return result.data;
  }
  function button(label, action, className = 'btn btn-outline-primary btn-sm') {
    const item = document.createElement('button'); item.type = 'button'; item.className = className; item.textContent = label;
    item.addEventListener('click', action); return item;
  }
  function meta(row, isAllergy = false) {
    const parts = [provenanceName[row.provenance] || 'Origen no establecido', `Actualizado ${date(row.updated_at)}`];
    if (isAllergy) parts.push(row.validation_state === 'CLINICIAN_REVIEWED' ? 'Revisada por médico' : 'Reportada sin validación clínica');
    if (row.state === 'INACTIVE') parts.push('Inactivo');
    return parts.join(' · ');
  }
  async function history(resource, row, target) {
    if (!target.classList.contains('d-none')) {show(target, false); return;}
    target.textContent = 'Cargando cambios…'; show(target, true);
    try {
      const id = row.fact_id || row.allergy_id;
      const data = await request(resource, {id: `${id}/history`});
      target.replaceChildren();
      for (const event of data.events || []) {
        const line = document.createElement('p');
        line.textContent = `${date(event.occurred_at)} · ${event.operation === 'CREATE' ? 'Registro inicial' : 'Corrección'}${event.reason ? ` · ${event.reason}` : ''}`;
        target.append(line);
      }
    } catch (failure) {target.textContent = failure.message;}
  }
  function renderFact(row, host) {
    const card = document.createElement('article'); card.className = 'lon03b-item';
    const value = document.createElement('p'); value.className = 'lon03b-item-value'; value.textContent = row.content;
    const detail = document.createElement('p'); detail.className = 'lon03b-item-meta'; detail.textContent = meta(row);
    const actions = document.createElement('div'); actions.className = 'lon03b-item-actions';
    actions.append(button('Editar', event => openForm('antecedents', row.category, row, event.currentTarget)));
    const historyBox = document.createElement('div'); historyBox.className = 'lon03b-history d-none';
    actions.append(button('Ver cambios', () => history('antecedents', row, historyBox), 'btn btn-link btn-sm'));
    card.append(value, detail, actions, historyBox); host.append(card);
  }
  function renderCategories() {
    const host = $('[data-lon03b-categories]'); host.replaceChildren();
    const byCategory = Object.fromEntries(categories.map(([key]) => [key, []]));
    facts.forEach(row => {if (byCategory[row.category]) byCategory[row.category].push(row);});
    categories.forEach(([key, label]) => {
      const review = reviews.find(row => row.category === key);
      const block = document.createElement('section'); block.className = 'lon03b-category';
      const heading = document.createElement('div'); heading.className = 'lon03b-section-head';
      const name = document.createElement('h4'); name.textContent = label;
      const badge = document.createElement('span'); badge.className = 'lon03b-review-state';
      badge.textContent = reviewName[review?.review_state || 'UNKNOWN'];
      if (review?.reviewed_at) badge.title = `Última revisión: ${date(review.reviewed_at)}`;
      heading.append(name, badge);
      const list = document.createElement('div'); list.className = 'lon03b-list';
      const rows = byCategory[key];
      if (!rows.length) {const empty = document.createElement('p'); empty.className = 'lon03b-empty'; empty.textContent = 'No hay datos registrados; esto no confirma ausencia.'; list.append(empty);}
      else rows.forEach(row => renderFact(row, list));
      const actions = document.createElement('div'); actions.className = 'lon03b-category-actions';
      actions.append(button('Agregar dato', event => openForm('antecedents', key, null, event.currentTarget)));
      const hasCurrent = rows.some(row => row.state === 'CURRENT');
      const reviewLabel = hasCurrent ? 'Confirmar revisión de datos' : 'Confirmar sin datos conocidos';
      if (hasCurrent || !rows.length) actions.append(button(reviewLabel, () => reviewCategory(key, hasCurrent ? 'REVIEWED_WITH_FACTS' : 'CONFIRMED_NONE', review)));
      block.append(heading, list, actions); host.append(block);
    });
  }
  function renderAllergies() {
    const host = $('[data-lon03b-allergies]'); host.replaceChildren();
    const state = document.createElement('p'); state.className = 'lon03b-allergy-state';
    const code = allergyReview?.review_state || 'UNKNOWN';
    state.textContent = code === 'CONFIRMED_NONE' ? 'Sin alergias conocidas · revisión explícita' : reviewName[code];
    if (allergyReview?.reviewed_at) state.title = `Última revisión: ${date(allergyReview.reviewed_at)}`;
    host.append(state);
    if (!allergies.length) {const empty = document.createElement('p');empty.className = 'lon03b-empty';empty.textContent = 'No hay alergias registradas; esto no confirma ausencia.';host.append(empty);}
    allergies.forEach(row => {
      const card = document.createElement('article'); card.className = 'lon03b-item';
      const value = document.createElement('p'); value.className = 'lon03b-item-value'; value.textContent = row.substance;
      const reaction = document.createElement('p'); reaction.textContent = `Reacción: ${text(row.reaction, 'No conocida')}`;
      const detail = document.createElement('p'); detail.className = 'lon03b-item-meta'; detail.textContent = meta(row, true);
      const actions = document.createElement('div'); actions.className = 'lon03b-item-actions';
      actions.append(button('Editar', event => openForm('allergies', '', row, event.currentTarget)));
      const historyBox = document.createElement('div'); historyBox.className = 'lon03b-history d-none';
      actions.append(button('Ver cambios', () => history('allergies', row, historyBox), 'btn btn-link btn-sm'));
      card.append(value, reaction, detail, actions, historyBox); host.append(card);
    });
    const hasCurrent = allergies.some(row => row.state === 'CURRENT');
    if (hasCurrent || !allergies.length) host.append(button(hasCurrent ? 'Confirmar revisión de alergias' : 'Confirmar sin alergias conocidas',
      () => reviewCategory(null, hasCurrent ? 'REVIEWED_WITH_ALLERGIES' : 'CONFIRMED_NONE', allergyReview)));
  }
  function render() {renderCategories();renderAllergies();show(content, true);}
  async function load() {
    const id = getPatient();
    if (dialog.open && editing && editing.patientId !== id) closeForm();
    if (patientId !== id) reviewRequests.clear();
    patientId = id; const requestEpoch = ++epoch;
    show(content, false); show(error, false);
    if (!id) {status.textContent = 'Selecciona un paciente para ver sus antecedentes.';return;}
    status.textContent = 'Cargando antecedentes y alergias…';
    try {
      const [antecedentData, allergyData] = await Promise.all([request('antecedents'), request('allergies')]);
      if (requestEpoch !== epoch || getPatient() !== id) return;
      facts = antecedentData.items || []; reviews = antecedentData.reviews || [];
      allergies = allergyData.items || []; allergyReview = allergyData.review || null;
      render(); status.textContent = 'Datos longitudinales actualizados.';
    } catch (failure) {
      if (requestEpoch !== epoch) return;
      error.textContent = failure.message; show(error, true); status.textContent = 'No se pudieron cargar los datos longitudinales.';
    }
  }
  function resetForm(row) {
    $('[data-lon03b-fact-content]').value = row?.content || '';
    $('[data-lon03b-substance]').value = row?.substance || '';
    $('[data-lon03b-reaction]').value = row?.reaction || '';
    $('[data-lon03b-validation]').value = row?.validation_state || 'REPORTED';
    $('[data-lon03b-state]').value = row?.state || 'CURRENT';
    $('[data-lon03b-provenance]').value = row?.provenance === 'PATIENT_REPORTED' ? 'PATIENT_REPORTED' : 'EXPLICIT_LONGITUDINAL_ENTRY';
    $('[data-lon03b-reason]').value = '';
    show($('[data-lon03b-conflict]'), false);show($('[data-lon03b-form-error]'), false);
  }
  function openForm(resource, category, row, trigger) {
    if (!patientId || patientId !== getPatient()) return;
    returnFocus = trigger; editing = {resource, category, row, patientId, key: crypto.randomUUID()};
    resetForm(row);
    show($('[data-lon03b-fact-fields]'), resource === 'antecedents');
    show($('[data-lon03b-allergy-fields]'), resource === 'allergies');
    show($('[data-lon03b-reason-wrap]'), !!row);
    show($('[data-lon03b-provenance-wrap]'), !row);
    $('[data-lon03b-dialog-title]').textContent = `${row ? 'Editar' : 'Agregar'} ${resource === 'allergies' ? 'alergia' : 'antecedente'}`;
    $('[data-lon03b-dialog-context]').textContent = resource === 'antecedents' ? categoryName[category] :
      (row ? provenanceName[row.provenance] || 'Origen no establecido' : 'Alergia longitudinal');
    dialog.showModal();
    (resource === 'antecedents' ? $('[data-lon03b-fact-content]') : $('[data-lon03b-substance]')).focus();
  }
  function closeForm() {dialog.close();}
  async function saveForm(event) {
    event.preventDefault();
    if (!editing || editing.patientId !== getPatient()) return;
    const {resource, category, row, key} = editing;
    const body = {
      state: $('[data-lon03b-state]').value,
      provenance: row?.provenance || $('[data-lon03b-provenance]').value
    };
    if (row?.source_type) {body.source_type = row.source_type;body.source_id = row.source_id;}
    if (resource === 'antecedents') {body.category = category;body.content = $('[data-lon03b-fact-content]').value.trim();}
    else {body.substance = $('[data-lon03b-substance]').value.trim();body.reaction = $('[data-lon03b-reaction]').value.trim() || null;body.validation_state = $('[data-lon03b-validation]').value;}
    if (row) {body.expected_version = Number(row.row_version);body.reason = $('[data-lon03b-reason]').value.trim();}
    const formError = $('[data-lon03b-form-error]');
    if ((resource === 'antecedents' && !body.content) || (resource === 'allergies' && !body.substance) || (row && !body.reason)) {
      formError.textContent = 'Completa el dato y, si es una corrección, su motivo.';show(formError, true);return;
    }
    const save = $('[data-lon03b-save]');save.disabled = true;show(formError, false);
    try {
      await request(resource, {method: row ? 'PATCH' : 'POST', id: row ? (row.fact_id || row.allergy_id) : '', body, key});
      closeForm();await load();
    } catch (failure) {
      if (failure.code === 'STALE_VERSION') show($('[data-lon03b-conflict]'), true);
      formError.textContent = failure.message;show(formError, true);
      formError.focus?.();
    } finally {save.disabled = false;}
  }
  async function reviewCategory(category, reviewState, row) {
    if (!patientId || patientId !== getPatient()) return;
    const resource = category ? 'antecedent-reviews' : 'allergy-reviews';
    const isNone = reviewState === 'CONFIRMED_NONE';
    if (!window.confirm(isNone ? 'Confirma que revisaste esta categoría y que no hay datos conocidos.' : 'Confirma que revisaste los datos registrados.')) return;
    const body = category ? {category, review_state: reviewState} : {review_state: reviewState};
    if (row) {body.expected_version = Number(row.row_version);body.reason = 'Revisión clínica explícita';}
    const requestId = `${patientId}:${resource}:${category || 'allergies'}`;
    const serialized = JSON.stringify(body);
    const pending = reviewRequests.get(requestId);
    const key = pending?.key || crypto.randomUUID();
    if (!pending) reviewRequests.set(requestId, {key, serialized});
    if (pending && pending.serialized !== serialized) {
      error.textContent = 'La revisión pendiente cambió. Actualiza los datos antes de iniciar otra solicitud.';
      show(error, true);return;
    }
    try {
      await request(resource, {method: row ? 'PATCH' : 'POST', id: row ? row.review_id : '', body, key});
      reviewRequests.delete(requestId);
      await load();
    } catch (failure) {
      error.textContent = failure.code === 'STALE_VERSION' ? 'Otro profesional cambió esta revisión. Actualiza los datos antes de decidir de nuevo.' : failure.message;
      show(error, true);error.scrollIntoView({block:'nearest'});
    }
  }
  $('[data-lon03b-refresh]').addEventListener('click', () => {reviewRequests.clear();load();});
  $('[data-lon03b-add-allergy]').addEventListener('click', event => openForm('allergies', '', null, event.currentTarget));
  $('[data-lon03b-cancel]').addEventListener('click', closeForm);
  $('[data-lon03b-reload]').addEventListener('click', async () => {
    if (!editing?.row) return;
    try {
      const id = editing.row.fact_id || editing.row.allergy_id;
      const fresh = (await request(editing.resource, {id})).item;
      editing = {...editing, row: fresh, key: crypto.randomUUID()};
      resetForm(fresh);
      await load();
    } catch (failure) {const target = $('[data-lon03b-form-error]');target.textContent = failure.message;show(target, true);}
  });
  dialog.addEventListener('close', () => {editing = null;(returnFocus?.isConnected ? returnFocus : $('#lon03b-title')).focus();returnFocus = null;});
  form.addEventListener('submit', saveForm);
  for (const name of ['patient:selected','expediente:patient_changed','expediente:patient-changed']) window.addEventListener(name, load);
  pane.querySelector('[data-bs-target="#t-antecedentes-longitudinal"]')?.addEventListener('shown.bs.tab', load);
  new MutationObserver(() => {if (getPatient() !== patientId) {if (dialog.open) closeForm();load();}})
    .observe(pane, {attributes:true, attributeFilter:['data-patient-id','data-active-patient-id']});
  if (getPatient()) load();
})();
