// LON02: read-only patient summary. No encounter command or clinical writer.
(function () {
  const pane = document.getElementById('p-expediente');
  const root = document.getElementById('lon02-summary');
  if (!pane || !root) return;
  const $ = selector => root.querySelector(selector);
  const status = $('[data-lon02-status]');
  const error = $('[data-lon02-error]');
  const content = $('[data-lon02-content]');
  const patient = () => String(pane.dataset.patientId || pane.dataset.activePatientId || '').trim();
  const date = value => String(value || '').trim().replace('T', ' ') || 'Fecha no disponible';
  const state = value => ({open:'En curso', closed:'Finalizada', voided:'Anulada'})[String(value || '').toLowerCase()] || 'Estado no disponible';
  const source = value => ({direct_measurement:'Medición directa', patient_report:'Referido por paciente', import:'Importación'})[String(value || '')] || 'Fuente no disponible';
  const measure = value => ({blood_pressure:'Presión arterial',heart_rate:'Frecuencia cardiaca',respiratory_rate:'Frecuencia respiratoria',temperature:'Temperatura',oxygen_saturation:'Saturación de oxígeno',pain:'Dolor',weight:'Peso',height:'Estatura',waist:'Cintura'})[String(value || '')] || String(value || 'Medición');
  const documentType = value => ({order:'Orden',orders:'Orden',lab_order:'Orden de laboratorio',imaging_order:'Orden de imagen',lab_result:'Resultado de laboratorio',lab_pdf:'Resultado de laboratorio',imaging_result:'Resultado de imagen',external_result:'Resultado externo',external_report:'Reporte externo',prescription:'Receta',receta:'Receta',pdf:'Documento PDF'})[String(value || '')] || 'Documento';
  const number = value => Number.isFinite(Number(value)) ? String(Number(value)) : String(value ?? 'Sin valor');

  let selected = '';
  let epoch = 0;

  function line(target, title, detail) {
    const row = document.createElement('p');
    const strong = document.createElement('strong');
    strong.textContent = title;
    const small = document.createElement('span');
    small.textContent = detail;
    row.append(strong, small);
    target.append(row);
  }
  function list(selector, rows, empty, render) {
    const target = $(selector);
    target.replaceChildren();
    if (!rows.length) { target.textContent = empty; return; }
    rows.forEach(row => render(target, row));
  }
  function goToHistory() {
    const tab = pane.querySelector('[data-bs-target="#t-historial-atencion"]');
    if (!tab) return;
    window.bootstrap?.Tab.getOrCreateInstance(tab).show();
    const title = pane.querySelector('#lon01-title');
    if (title && tab.classList.contains('active')) {
      title.setAttribute('tabindex', '-1');
      requestAnimationFrame(() => { title.focus(); title.scrollIntoView({block:'nearest'}); });
    }
  }
  function compact(selector, rows, empty, render, limit = 2) {
    list(selector, rows.slice(0, limit), empty, render);
    if (rows.length > limit) {
      const more = document.createElement('span'); more.className = 'lon02-note';
      more.textContent = `+ ${rows.length - limit} más`; $(selector).append(more);
    }
  }
  function measurementRows(rows) {
    const output = [];
    const consumed = new Set();
    rows.forEach((row, index) => {
      if (consumed.has(index)) return;
      const observationKey = row.observation_key || row.observation_id || row.measurement_event_key || '';
      if (row.code === 'blood_pressure' && ['systolic', 'diastolic'].includes(row.component) && observationKey) {
        const opposite = row.component === 'systolic' ? 'diastolic' : 'systolic';
        const pairIndex = rows.findIndex((candidate, candidateIndex) => candidateIndex !== index
          && !consumed.has(candidateIndex)
          && candidate.code === 'blood_pressure'
          && candidate.component === opposite
          && (candidate.observation_key || candidate.observation_id || candidate.measurement_event_key || '') === observationKey
          && candidate.date === row.date
          && candidate.source === row.source
          && candidate.unit === row.unit
          && candidate.provenance === row.provenance
          && candidate.effective_at_authority === row.effective_at_authority
          && Boolean(candidate.has_amendment) === Boolean(row.has_amendment));
        if (pairIndex !== -1) {
          const systolic = row.component === 'systolic' ? row : rows[pairIndex];
          const diastolic = row.component === 'diastolic' ? row : rows[pairIndex];
          consumed.add(index);
          consumed.add(pairIndex);
          output.push({...systolic, component:null, value:`${number(systolic.value)} / ${number(diastolic.value)}`});
          return;
        }
      }
      output.push(row);
    });
    return output;
  }
  function measurementLine(target, row) {
    const item = document.createElement('div'); item.className = 'lon02-measurement-row';
    const label = document.createElement('span'); label.className = 'lon02-measurement-label';
    label.textContent = `${measure(row.code)}${row.component ? ` ${row.component === 'systolic' ? 'sistólica' : 'diastólica'}` : ''}`;
    const value = document.createElement('strong'); value.className = 'lon02-measurement-value';
    value.textContent = `${number(row.value)} ${row.unit || ''}`.trim();
    item.append(label, value); target.append(item);
  }
  function renderMeasurements(rows) {
    const target = $('[data-lon02-measurements]');
    target.replaceChildren();
    const normalized = measurementRows(rows);
    if (!normalized.length) { target.textContent = 'Sin mediciones comparables en los últimos 12 meses.'; return; }
    const groups = new Map();
    normalized.forEach(row => {
      const key = [row.date, row.source, Boolean(row.has_amendment), row.encounter_key, row.provenance, row.effective_at_authority].join('|');
      if (!groups.has(key)) groups.set(key, []);
      groups.get(key).push(row);
    });
    groups.forEach(groupRows => {
      const group = document.createElement('div'); group.className = 'lon02-measurement-group';
      groupRows.forEach(row => measurementLine(group, row));
      const row = groupRows[0];
      const meta = document.createElement('small'); meta.className = 'lon02-measurement-meta';
      meta.textContent = `${date(row.date)} UTC · ${source(row.source)}${row.has_amendment ? ' · Con enmienda' : ''}`;
      group.append(meta); target.append(group);
    });
  }
  function render(data) {
    const recent = data.recent_encounters || [];
    // LON02 supplies a bounded, descending canonical history; never infer a visit from Agenda.
    list('[data-lon02-recent]', recent.slice(0, 2), 'Sin consultas anteriores en el resumen disponible.', (target, row) => line(target, date(row.date), `Consulta ${state(row.status)} · Encuentro canónico`));
    // Already selected by LON07B latest-comparable authority. Do not re-sort observations.
    renderMeasurements(data.latest_measurements || []);
    const late = data.late_results || [];
    const isSame = (a, b) => a.encounter_key === b.encounter_key && a.date === b.date && a.title === b.title;
    const resultTypes = ['lab_result','lab_pdf','imaging_result','external_result','external_report','result'];
    const results = [
      ...(data.pending_orders || []).map(row => ({...row, context:'Orden sin resultado vinculado'})),
      ...late.map(row => ({...row, context:'Recibido después del cierre'})),
      ...(data.recent_documents || []).filter(row => resultTypes.includes(String(row.type || '').trim().toLowerCase()) && !late.some(item => isSame(item, row))).map(row => ({...row, context:documentType(row.type)}))
    ];
    compact('[data-lon02-results]', results, 'Sin órdenes pendientes ni resultados en el resumen disponible.', (target, row) => line(target, row.title, `${row.context} · ${date(row.date)}`), 3);
    content.classList.remove('d-none');
  }
  async function renderAppointment(id, request) {
    const target = $('[data-lon02-appointment]'); target.textContent = 'Consultando Agenda…';
    try {
      // Reuse the accepted patient-archive projection of Agenda's next eligible appointment.
      // Match the immutable patient ID, never a name match alone; no backend contract changes.
      const doctor = String(window.mxmedStore?.activeProfessionalContext?.doctor_id || window.mxmedStore?.doctor_id || '').trim();
      if (!doctor) throw new Error('unavailable');
      const read = async url => {
        const response = await fetch(url, {credentials:'same-origin', headers:{Accept:'application/json'}});
        const result = await response.json();
        if (!response.ok || result?.ok !== true) throw new Error('unavailable');
        return result;
      };
      const profile = await read(`/api/patients/index.php/patients/${encodeURIComponent(id)}`);
      const name = profile.data?.display_name;
      if (!name) throw new Error('unavailable');
      const query = new URLSearchParams({view:'archive',q:name,limit:'100'});
      const archive = await read(`/api/patients/index.php/doctors/${encodeURIComponent(doctor)}/patients?${query}`);
      if (request !== epoch || patient() !== id) return;
      const item = archive.data?.items?.find(row => String(row.patient_id) === id);
      if (!item || !Object.hasOwn(item, 'next_appointment_at')) throw new Error('unavailable');
      target.replaceChildren();
      if (item.next_appointment_at) line(target, date(item.next_appointment_at), 'Hora de Agenda · Ciudad de México');
      else target.textContent = 'Sin próxima cita registrada en Agenda.';
    } catch (_) {
      if (request === epoch && patient() === id) target.textContent = 'Próxima cita no disponible.';
    }
  }
  async function renderLongitudinalAuthority(id, request) {
    const allergyTarget = $('[data-lon02-allergies]');
    const problemsTarget = $('[data-lon02-problems]');
    const tasksTarget = $('[data-lon02-tasks]');
    allergyTarget.textContent = 'Cargando estado revisado…';
    problemsTarget.textContent = 'Cargando problemas…';
    tasksTarget.textContent = 'Cargando tareas…';
    async function read(resource) {
      const response = await fetch(`/api/clinical/index.php/patients/${encodeURIComponent(id)}/longitudinal/${resource}`, {credentials:'same-origin',headers:{Accept:'application/json'}});
      const result = await response.json().catch(() => null);
      if (!response.ok || result?.ok !== true) throw new Error('unavailable');
      return result.data || {};
    }
    const [allergies, problems, tasks] = await Promise.allSettled([read('allergies'),read('problems'),read('tasks')]);
    if (request !== epoch || patient() !== id) return;
    allergyTarget.replaceChildren();
    if (allergies.status !== 'fulfilled') allergyTarget.textContent = 'Estado no disponible.';
    else {
      const data = allergies.value;
      if (data.knowledge_state === 'CONFIRMED_NONE') allergyTarget.textContent = 'Sin alergias conocidas · revisión explícita.';
      else if (data.knowledge_state === 'REVIEWED_WITH_ALLERGIES') {
        const current = (data.items || []).filter(row => row.state === 'CURRENT');
        compact('[data-lon02-allergies]', current, 'Revisión sin dato visible.', (target, row) => line(target, row.substance, [row.reaction, row.validation_state === 'CLINICIAN_REVIEWED' ? '' : 'Reportada; sin validación clínica'].filter(Boolean).join(' · ')));
      } else if (data.knowledge_state === 'NEEDS_REVIEW') allergyTarget.textContent = 'Registro modificado; revisión pendiente.';
      else allergyTarget.textContent = 'Sin revisar. La ausencia de registros no confirma ausencia de alergias.';
    }
    problemsTarget.replaceChildren();
    if (problems.status !== 'fulfilled') problemsTarget.textContent = 'Estado no disponible.';
    else {
      const active = (problems.value.items || []).filter(row => row.status === 'ACTIVE');
      if (active.length) compact('[data-lon02-problems]', active, '', (target, row) => line(target, row.label, 'Activo'));
      else problemsTarget.textContent = 'Sin problemas activos registrados. No confirma ausencia clínica.';
    }
    tasksTarget.replaceChildren();
    if (tasks.status !== 'fulfilled') tasksTarget.textContent = 'Estado no disponible.';
    else {
      const open = (tasks.value.items || []).filter(row => row.state === 'OPEN');
      compact('[data-lon02-tasks]', open, 'Sin tareas abiertas registradas.', (target, row) => {
        const overdue = row.due_at && Date.parse(row.due_at.replace(' ', 'T') + 'Z') < Date.now();
        line(target, row.title, `${row.task_type === 'FOLLOW_UP' ? 'Seguimiento' : 'Tarea clínica'} · ${row.due_at ? `Límite ${row.due_at} UTC${overdue ? ' · Vencida' : ''}` : 'Sin fecha límite'}`);
      });
    }
  }
  async function load() {
    const id = patient();
    selected = id;
    const request = ++epoch;
    content.classList.add('d-none');
    error.classList.add('d-none');
    if (!id) { status.textContent = 'Selecciona un paciente para ver su resumen.'; return; }
    status.textContent = 'Cargando resumen…';
    try {
      const response = await fetch(`/api/clinical/index.php/patients/${encodeURIComponent(id)}/longitudinal-summary`, {credentials:'same-origin', headers:{Accept:'application/json'}});
      const result = await response.json().catch(() => null);
      if (!response.ok || result?.ok !== true) throw new Error(result?.error?.message || 'No se pudo cargar el resumen.');
      if (request !== epoch || patient() !== id) return;
      render(result.data || {});
      status.textContent = 'Resumen de sólo lectura actualizado.';
      await Promise.all([renderLongitudinalAuthority(id, request), renderAppointment(id, request)]);
    } catch (failure) {
      if (request !== epoch) return;
      error.textContent = failure.message;
      error.classList.remove('d-none');
      status.textContent = 'Resumen no disponible.';
    }
  }
  $('[data-lon02-refresh]').addEventListener('click', load);
  $('[data-lon02-history]').addEventListener('click', () => goToHistory());
  for (const name of ['patient:selected', 'expediente:patient_changed', 'expediente:patient-changed']) window.addEventListener(name, load);
  pane.querySelector('[data-bs-target="#t-resumen-longitudinal"]')?.addEventListener('shown.bs.tab', load);
  new MutationObserver(() => { if (patient() !== selected) load(); }).observe(pane, {attributes:true, attributeFilter:['data-patient-id','data-active-patient-id']});
  if (patient()) load();
})();
