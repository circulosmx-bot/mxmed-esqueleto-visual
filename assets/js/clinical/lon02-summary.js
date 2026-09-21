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
  const antecedentCategories = {PERSONAL_PATHOLOGICAL:'Personales patológicos',PERSONAL_NON_PATHOLOGICAL:'Personales no patológicos',SURGICAL:'Quirúrgicos',FAMILY:'Familiares',HABITS:'Hábitos',VACCINATION:'Vacunación',GYNECOLOGICAL:'Ginecológicos',OTHER:'Otros'};
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
  function goToHistory(resume = false) {
    const tab = pane.querySelector('[data-bs-target="#t-historial-atencion"]');
    if (!tab) return;
    if (window.bootstrap?.Tab) window.bootstrap.Tab.getOrCreateInstance(tab).show();
    else tab.click();
    const target = resume ? pane.querySelector('#m7-workspace [data-m7-resume]:not(.d-none)') || pane.querySelector('#m7-workspace-title') : pane.querySelector('#lon01-title');
    if (target) {
      if (!target.matches('button, a, input, select, textarea')) target.setAttribute('tabindex', '-1');
      requestAnimationFrame(() => { target.focus(); target.scrollIntoView({block:'nearest'}); });
    }
  }
  function render(data) {
    const open = data.open_encounter;
    list('[data-lon02-open]', open ? [open] : [], 'No hay una consulta en curso registrada.', (target, row) => {
      line(target, 'Consulta en curso', `Encuentro canónico · Iniciada ${date(row.date)}`);
      const button = document.createElement('button');
      button.type = 'button'; button.className = 'btn btn-outline-primary btn-sm'; button.textContent = 'Ir a la consulta en curso';
      button.addEventListener('click', () => goToHistory(true)); target.append(button);
    });
    list('[data-lon02-recent]', data.recent_encounters || [], 'No hay consultas canónicas anteriores disponibles.', (target, row) => line(target, `Consulta ${state(row.status)}`, `Encuentro canónico · ${date(row.date)}`));
    list('[data-lon02-measurements]', data.latest_measurements || [], 'No hay mediciones canónicas disponibles.', (target, row) => line(target, `${measure(row.code)}: ${row.code === 'blood_pressure' ? row.value : number(row.value)} ${row.unit || ''}`.trim(), `Registrada ${date(row.date)} · ${source(row.source)} · encuentro canónico${row.has_amendment ? ' · Valor original enmendado; revisar historial' : ''}`));
    list('[data-lon02-pending]', data.pending_orders || [], 'No hay órdenes recientes sin resultado vinculado en el conjunto consultado.', (target, row) => line(target, row.title, `Orden canónica · ${date(row.date)} · resultado vinculado pendiente`));
    list('[data-lon02-late]', data.late_results || [], 'No hay resultados posteriores al cierre en el conjunto consultado.', (target, row) => line(target, row.title, `Resultado canónico recibido después de finalizar · ${date(row.date)}`));
    list('[data-lon02-documents]', data.recent_documents || [], 'No hay documentos canónicos recientes disponibles.', (target, row) => line(target, row.title, `${documentType(row.type)} · ${date(row.date)} · encuentro canónico`));
    content.classList.remove('d-none');
  }
  async function renderLongitudinalAuthority(id, request) {
    const antecedents = $('[data-lon02-antecedents]');
    const allergyTarget = $('[data-lon02-allergies]');
    const problemsTarget = $('[data-lon02-problems]');
    const medicationsTarget = $('[data-lon02-medications]');
    antecedents.textContent = 'Cargando estado revisado…';
    allergyTarget.textContent = 'Cargando estado revisado…';
    problemsTarget.textContent = 'Cargando problemas…';
    medicationsTarget.textContent = 'Cargando medicación…';
    async function read(resource) {
      const response = await fetch(`/api/clinical/index.php/patients/${encodeURIComponent(id)}/longitudinal/${resource}`, {credentials:'same-origin',headers:{Accept:'application/json'}});
      const result = await response.json().catch(() => null);
      if (!response.ok || result?.ok !== true) throw new Error('unavailable');
      return result.data || {};
    }
    const [facts, allergies, problems, medications] = await Promise.allSettled([read('antecedents'),read('allergies'),read('problems'),read('medications')]);
    if (request !== epoch || patient() !== id) return;
    antecedents.replaceChildren();
    if (facts.status !== 'fulfilled') antecedents.textContent = 'Estado no disponible.';
    else {
      const data = facts.value;
      const states = data.knowledge_state_by_category || {};
      const reviewed = Object.entries(states).filter(([,state]) => state === 'REVIEWED_WITH_FACTS');
      const none = Object.entries(states).filter(([,state]) => state === 'CONFIRMED_NONE');
      const unknown = Object.entries(states).filter(([,state]) => state === 'UNKNOWN').length;
      const pending = Object.entries(states).filter(([,state]) => state === 'NEEDS_REVIEW').length;
      reviewed.forEach(([category]) => {
        const current = (data.items || []).filter(row => row.category === category && row.state === 'CURRENT');
        line(antecedents, antecedentCategories[category] || category, current.map(row => row.content).join('; ') || 'Revisión sin dato visible');
      });
      none.forEach(([category]) => line(antecedents, antecedentCategories[category] || category, 'Sin datos conocidos · revisión explícita'));
      if (pending) line(antecedents, 'Revisión pendiente', `${pending} categoría(s) cambiaron desde la última revisión.`);
      if (unknown) line(antecedents, 'Sin revisar', `${unknown} categoría(s) sin confirmación clínica.`);
      if (!reviewed.length && !none.length && !pending && !unknown) antecedents.textContent = 'Estado no establecido.';
    }
    allergyTarget.replaceChildren();
    if (allergies.status !== 'fulfilled') allergyTarget.textContent = 'Estado no disponible.';
    else {
      const data = allergies.value;
      if (data.knowledge_state === 'CONFIRMED_NONE') allergyTarget.textContent = 'Sin alergias conocidas · revisión explícita.';
      else if (data.knowledge_state === 'REVIEWED_WITH_ALLERGIES') {
        const current = (data.items || []).filter(row => row.state === 'CURRENT');
        allergyTarget.textContent = current.length ? current.map(row => row.substance).join('; ') : 'Revisión sin dato visible.';
      } else if (data.knowledge_state === 'NEEDS_REVIEW') allergyTarget.textContent = 'Registro modificado; revisión pendiente.';
      else allergyTarget.textContent = 'Sin revisar. La ausencia de registros no confirma ausencia de alergias.';
    }
    problemsTarget.replaceChildren();
    if (problems.status !== 'fulfilled') problemsTarget.textContent = 'Estado no disponible.';
    else {
      const active = (problems.value.items || []).filter(row => row.status === 'ACTIVE');
      if (active.length) active.forEach(row => line(problemsTarget, row.label, `Activo · actualizado ${date(row.updated_at)}`));
      else problemsTarget.textContent = 'Sin problemas activos registrados. Esto no confirma ausencia clínica; consulta Problemas para ver los inactivos y resueltos.';
    }
    medicationsTarget.replaceChildren();
    if (medications.status !== 'fulfilled') medicationsTarget.textContent = 'Estado no disponible.';
    else {
      const rows = medications.value.items || [];
      const current = rows.filter(row => row.state === 'ACTIVE_CONFIRMED');
      const reported = rows.filter(row => row.state === 'REPORTED_BY_PATIENT');
      const prescribed = rows.filter(row => row.state === 'PRESCRIBED_NOT_CONFIRMED_ACTIVE');
      current.forEach(row => line(medicationsTarget, row.medication_name, 'Uso actual confirmado por el clínico.'));
      reported.forEach(row => line(medicationsTarget, row.medication_name, 'Referido por el paciente; no confirmado como uso actual.'));
      prescribed.forEach(row => line(medicationsTarget, row.medication_name, 'Prescrito; uso actual no confirmado.'));
      if (!current.length && !reported.length && !prescribed.length) medicationsTarget.textContent = 'Sin medicación actual confirmada en el registro longitudinal. Esto no confirma que el paciente no tome medicamentos.';
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
      await renderLongitudinalAuthority(id, request);
    } catch (failure) {
      if (request !== epoch) return;
      error.textContent = failure.message;
      error.classList.remove('d-none');
      status.textContent = 'Resumen no disponible.';
    }
  }
  $('[data-lon02-refresh]').addEventListener('click', load);
  $('[data-lon02-history]').addEventListener('click', () => goToHistory(false));
  for (const name of ['patient:selected', 'expediente:patient_changed', 'expediente:patient-changed']) window.addEventListener(name, load);
  pane.querySelector('[data-bs-target="#t-resumen-longitudinal"]')?.addEventListener('shown.bs.tab', load);
  new MutationObserver(() => { if (patient() !== selected) load(); }).observe(pane, {attributes:true, attributeFilter:['data-patient-id','data-active-patient-id']});
  if (patient()) load();
})();
