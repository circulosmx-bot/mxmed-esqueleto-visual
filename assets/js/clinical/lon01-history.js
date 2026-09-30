// LON01: patient-level, read-only history. Never invokes encounter commands.
(function () {
  const pane = document.getElementById('p-expediente');
  const root = document.getElementById('lon01-history');
  if (!pane || !root) return;
  const $ = selector => root.querySelector(selector);
  const status = $('[data-lon01-status]');
  const error = $('[data-lon01-error]');
  const content = $('[data-lon01-content]');
  const current = $('[data-lon01-current]');
  const openSection = $('[data-lon01-open-section]');
  const previous = $('[data-lon01-previous]');
  const legacy = $('[data-lon01-legacy]');
  const more = $('[data-lon01-more]');
  const detail = $('[data-lon01-detail]');
  const detailTitle = $('[data-lon01-detail-title]');
  const detailMeta = $('[data-lon01-detail-meta]');
  const detailBody = $('[data-lon01-detail-body]');
  const selectedPatient = () => String(pane.dataset.patientId || pane.dataset.activePatientId || '').trim();
  const show = (node, visible) => node.classList.toggle('d-none', !visible);
  const date = value => {
    const raw = String(value || '').trim();
    const parts = raw.match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}:\d{2})/);
    const months = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
    return parts && months[Number(parts[2]) - 1] ? `${Number(parts[3])} ${months[Number(parts[2]) - 1]} ${parts[1]} · ${parts[4]}` : raw || 'Fecha no disponible';
  };
  const closedDate = value => {
    const parts = String(value || '').trim().match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T]|$)/);
    const months = ['ENERO','FEBRERO','MARZO','ABRIL','MAYO','JUNIO','JULIO','AGOSTO','SEPTIEMBRE','OCTUBRE','NOVIEMBRE','DICIEMBRE'];
    return parts && months[Number(parts[2]) - 1] ? `${Number(parts[3])} ${months[Number(parts[2]) - 1]} ${parts[1]}` : 'FECHA NO DISPONIBLE';
  };
  // Terminal encounter timestamps are stored in UTC without an API offset.
  // Agenda's existing product timezone is America/Mexico_City. Encounter start
  // and observation effective times may be local wall times, so they keep date().
  const utcDate = value => {
    const raw = String(value || '').trim();
    if (!/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(raw)) return date(raw);
    const instant = new Date(`${raw.replace(' ', 'T')}Z`);
    if (Number.isNaN(instant.getTime())) return date(raw);
    const parts = Object.fromEntries(new Intl.DateTimeFormat('en-US', {
      timeZone:'America/Mexico_City', year:'numeric', month:'2-digit', day:'2-digit',
      hour:'2-digit', minute:'2-digit', hourCycle:'h23'
    }).formatToParts(instant).map(part => [part.type, part.value]));
    const months = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
    return `${Number(parts.day)} ${months[Number(parts.month) - 1]} ${parts.year} · ${parts.hour}:${parts.minute}`;
  };
  const number = value => {
    const raw = String(value ?? 'Sin valor');
    const match = raw.match(/^(-?\d+)(?:\.(\d+))?$/);
    if (!match || !match[2]) return raw;
    const fraction = match[2].replace(/0+$/, '');
    return fraction ? `${match[1]}.${fraction}` : match[1];
  };
  const state = value => ({open:'En curso', closed:'Finalizada', voided:'Anulada'})[String(value || '').toLowerCase()] || 'Estado no disponible';
  const legacyType = value => ({historia_clinica:'Historia clínica anterior', exploracion_fisica:'Exploración física anterior'})[String(value || '')] || 'Registro clínico anterior';
  let patientId = '';
  let epoch = 0;
  let offset = 0;
  let paging = false;
  let detailOrigin = null;
  let selection = 0;
  let autoSelectPending = false;
  let fallbackOpen = null;
  const empty = $('[data-vis05-empty]');
  function selectCard(button) {
    root.querySelectorAll('.lon01-card').forEach(card => card.setAttribute('aria-pressed', String(card === button)));
    detailOrigin = button;
  }
  function preserveDetailHeight() {
    if (detail.classList.contains('d-none') || !matchMedia('(min-width:701px)').matches) return;
    detail.style.minHeight = `${Math.max(detail.getBoundingClientRect().height, parseFloat(detail.style.minHeight) || 0)}px`;
  }
  function reveal(focus = true) {
    show(empty, false); show(detail, true); root.classList.add('vis05-detail-open');
    if (focus) {
      detailTitle.focus({preventScroll:true});
      if (matchMedia('(max-width:700px)').matches) detail.scrollIntoView({block:'start'});
    }
  }
  const seenCanonical = new Set();
  const seenLegacy = new Set();
  const officeNames = new Map();
  const appointments = new Map();
  async function appointmentVenue(row, button, venue, seen, id) {
    const appointmentId = String(row.appointment_id || '').trim();
    if (!appointmentId) return;
    try {
      if (!appointments.has(appointmentId)) appointments.set(appointmentId, fetch(`/api/agenda/index.php/appointments/${encodeURIComponent(appointmentId)}`, {credentials:'same-origin', headers:{Accept:'application/json'}}).then(response => response.json()).then(result => result?.ok === true ? result.data : null));
      const appointment = await appointments.get(appointmentId);
      if (seen !== epoch || selectedPatient() !== id || !button.isConnected || String(appointment?.patient_id || '') !== id) return;
      const appointmentName = String(appointment.consultorio_name || '').trim();
      if (appointmentName) { venue.textContent = appointmentName; return; }
      const officeId = String(appointment.consultorio_id || '').trim();
      const doctorId = String(appointment.doctor_id || '').trim();
      if (!officeId || !doctorId) return;
      if (!officeNames.has(doctorId)) officeNames.set(doctorId, fetch(`/api/agenda/index.php/consultorios?doctor_id=${encodeURIComponent(doctorId)}`, {credentials:'same-origin', headers:{Accept:'application/json'}}).then(response => response.json()).then(result => result?.ok === true && Array.isArray(result.data) ? result.data : []));
      const offices = await officeNames.get(doctorId);
      if (seen !== epoch || selectedPatient() !== id || !button.isConnected) return;
      const office = offices.find(item => String(item.consultorio_id || '') === officeId && String(item.doctor_id || '') === doctorId);
      const name = String(office?.name || '').trim();
      if (name) venue.textContent = name;
    } catch (_) { /* No verified venue: leave the third line empty. */ }
  }

  async function get(path) {
    const response = await fetch(`/api/clinical/index.php/${path}`, { credentials:'same-origin', headers:{Accept:'application/json'} });
    const result = await response.json().catch(() => null);
    if (!response.ok || result?.ok !== true) throw new Error(result?.error?.message || result?.message || 'No se pudo cargar el historial.');
    return result.data;
  }
  function message(text, isError = false) { error.textContent = text; show(error, isError); if (!isError) status.textContent = text; }
  function line(label, value) {
    const row = document.createElement('p');
    const strong = document.createElement('strong'); strong.textContent = `${label}: `;
    row.append(strong, document.createTextNode(String(value ?? 'Sin dato')));
    return row;
  }
  function group(title, rows, render) {
    if (!rows.length) return;
    const section = document.createElement('section');
    const heading = document.createElement('h5'); heading.textContent = title; section.append(heading);
    rows.forEach(row => render(section, row)); detailBody.append(section);
  }
  function appendObject(target, value) {
    const pre = document.createElement('pre');
    pre.textContent = typeof value === 'string' ? value : JSON.stringify(value, null, 2);
    target.append(pre);
  }
  async function openPrivateBinary(documentRow) {
    const seen = epoch;
    try {
      const response = await fetch(`/api/clinical/index.php/documents/${encodeURIComponent(documentRow.document_uuid)}/binary/ORIGINAL`, {credentials:'same-origin'});
      const type = (response.headers.get('Content-Type') || '').split(';')[0].trim();
      if (!response.ok || !['application/pdf','image/jpeg','image/png','image/webp'].includes(type)) throw new Error();
      const blob = await response.blob();
      if (seen !== epoch || selectedPatient() !== patientId) return;
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a'); link.href = url; link.target = '_blank'; link.rel = 'noopener noreferrer'; link.click();
      setTimeout(() => URL.revokeObjectURL(url), 60000);
    } catch (_) { message('No se pudo abrir el archivo privado autorizado.', true); }
  }
  async function openCanonical(row, focus = true) {
    const seen = epoch, request = ++selection;
    const hasDetail = !detail.classList.contains('d-none');
    if (hasDetail) {
      preserveDetailHeight();
      detail.setAttribute('aria-busy', 'true');
    } else {
      show(empty, true); empty.textContent = 'Cargando detalle…';
    }
    const key = String(row.encounter_key || '');
    try {
      const item = await get(`encounters/${encodeURIComponent(key)}`);
      if (request !== selection || seen !== epoch || selectedPatient() !== patientId || String(item.patient_id) !== patientId) return;
      let documentRows = item.documents || [];
      try {
        const listed = await get(`doctors/${encodeURIComponent(item.doctor_id)}/patients/${encodeURIComponent(patientId)}/documents?limit=200`);
        if (request !== selection || seen !== epoch || selectedPatient() !== patientId) return;
        const associated = (listed.items || []).filter(doc => String(doc.encounter_ref_id || doc.encounter_id || '') === String(item.encounter_id));
        const metadata = new Map(associated.map(doc => [String(doc.document_uuid), doc]));
        documentRows = documentRows.map(doc => ({...doc, ...(metadata.get(String(doc.document_uuid)) || {})}));
      } catch (_) { /* The encounter's authorized document summary remains visible. */ }
      if (request !== selection || seen !== epoch || selectedPatient() !== patientId) return;
      detailBody.replaceChildren();
      detailTitle.textContent = `Consulta ${state(item.status)}`;
      detailMeta.textContent = `${date(item.event_datetime)}${item.closed_at ? ` · Finalizada ${utcDate(item.closed_at)}` : ''}`;
      detail.dataset.state = item.status;
      if (item.status === 'voided') detailBody.append(line('Anulación', `${utcDate(item.voided_at)} · ${item.void_reason || 'Motivo no disponible'}`));
      const sections = item.sections || {};
      group('Contenido de la consulta', Object.entries({reason_evolution:'Motivo / Evolución', assessment:'Valoración', plan:'Plan', physical_exam:'Exploración'}).filter(([key]) => sections[key]), (target, [key, label]) => {
        const block = document.createElement('div'); block.className = key === 'reason_evolution' ? 'vis05-narrative' : 'vis05-section';
        block.append(line(label, sections[key].narrative_text || (key === 'physical_exam' ? 'Exploración registrada' : 'Sin texto')));
        if (key === 'physical_exam' && sections[key].payload?.systems) {
          const names = {general:'General',cardiovascular:'Cardiovascular',respiratory:'Respiratorio',abdomen:'Abdomen',neurological:'Neurológico',musculoskeletal:'Musculoesquelético',skin:'Piel'};
          for (const [system, value] of Object.entries(sections[key].payload.systems)) block.append(line(names[system] || system, `${({NORMAL:'Normal',ABNORMAL:'Hallazgo registrado',NOT_REVIEWED:'No revisado'})[value.state] || value.state || 'Sin dato'}${value.finding ? ` · ${value.finding}` : ''}`));
        }
        target.append(block);
      });
      group('SIGNOS VITALES DE ESTA CONSULTA', item.observations || [], (target, observation) => {
        target.append(line(({blood_pressure:'Presión arterial',weight:'Peso',height:'Talla',temperature:'Temperatura',heart_rate:'Frecuencia cardíaca',respiratory_rate:'Frecuencia respiratoria',oxygen_saturation:'Saturación de oxígeno',pain:'Dolor'})[observation.code] || String(observation.code || 'Medición'), `${observation.systolic_mm_hg != null ? `${number(observation.systolic_mm_hg)} / ${number(observation.diastolic_mm_hg)}` : number(observation.value_numeric)} ${observation.unit || ''} · ${date(observation.effective_at)} · ${({direct_measurement:'Medición directa',patient_report:'Informado por el paciente',import:'Importado'})[observation.source] || observation.source || 'Origen no disponible'}`));
      });
      group('Documentos y resultados', documentRows, (target, documentRow) => {
        const label = documentRow.title || documentRow.document_type || 'Documento';
        const late = Number(documentRow.created_after_final_note) === 1;
        const replaced = Number(documentRow.has_successor) === 1;
        const replacement = documentRow.lineage_root_id && String(documentRow.lineage_root_id) !== String(documentRow.id);
        const kind = ['lab_result','lab_pdf','imaging_result','external_result','external_report'].includes(String(documentRow.document_type)) ? 'Resultado' : 'Documento';
        const timing = kind === 'Resultado' && item.status === 'closed' && documentRow.created_after_final_note != null
          ? late ? ' · Resultado recibido después de finalizar' : ' · Disponible al finalizar' : '';
        target.append(line(label, `${kind} · ${date(documentRow.event_datetime)}${timing}${replaced ? ' · Versión reemplazada' : replacement ? ' · Reemplazo documental' : ''}`));
        if (Number(documentRow.has_private_binary) === 1) {
          const read = document.createElement('button'); read.type = 'button'; read.className = 'btn btn-outline-primary btn-sm'; read.textContent = 'Abrir archivo privado';
          read.addEventListener('click', () => openPrivateBinary(documentRow)); target.append(read);
        }
      });
      group('Enmiendas del encuentro', item.amendments || [], (target, amendment) => {
        target.append(line('Enmienda posterior; el original permanece conservado', `${utcDate(amendment.amended_at)} · ${amendment.reason || 'Sin motivo disponible'}`));
        if (amendment.correction?.text || amendment.correction?.narrative_text) target.append(line('Adenda', amendment.correction.text || amendment.correction.narrative_text));
        else appendObject(target, amendment.correction || {});
      });
      if (!detailBody.children.length) detailBody.append(line('Contenido', 'No hay contenido clínico registrado en esta consulta.'));
      reveal(focus);
      message('');
    } catch (failure) { if (seen === epoch && request === selection) { message(failure.message, true); empty.textContent = 'No se pudo cargar el detalle. Selecciona de nuevo el registro para reintentar.'; } }
    finally { if (seen === epoch && request === selection) detail.removeAttribute('aria-busy'); }
  }
  function openLegacy(row) {
    message('');
    preserveDetailHeight();
    detail.removeAttribute('aria-busy');
    selection++; detail.dataset.state = 'legacy';
    detailBody.replaceChildren();
    detailTitle.textContent = `Registro histórico${row.status === 'draft' ? ' · Borrador' : ''}`;
    detailMeta.textContent = `Fuente: registro histórico · ${legacyType(row.note_type)} · fecha exacta de consulta no confirmada`;
    for (const [key, label] of Object.entries({subjective:'Subjetivo', objective:'Objetivo', assessment:'Valoración registrada', plan:'Plan registrado'})) {
      if (row[key]) detailBody.append(line(label, row[key]));
    }
    if (row.payload && Object.keys(row.payload).length) {
      const heading = document.createElement('h5'); heading.textContent = 'Datos históricos tal como se guardaron';
      detailBody.append(heading); appendObject(detailBody, row.payload);
    }
    if (!detailBody.children.length) detailBody.append(line('Contenido', 'Este registro no contiene información visible.'));
    reveal();
  }
  function card(label, subline, action, className = '') {
    const button = document.createElement('button'); button.type = 'button'; button.className = `lon01-card ${className}`;
    const title = document.createElement('strong'); title.textContent = label;
    const meta = document.createElement('span'); meta.textContent = subline;
    button.setAttribute('aria-pressed', 'false');
    button.setAttribute('aria-controls', 'lon01-detail');
    button.append(title, meta);
    if (className === 'is-current') {
      const badge = document.createElement('span'); badge.className = 'lon01-state'; badge.textContent = 'EN CURSO';
      button.append(badge);
    }
    const chevron = document.createElement('span'); chevron.className = 'material-symbols-rounded lon01-chevron'; chevron.setAttribute('aria-hidden', 'true'); chevron.textContent = 'chevron_right';
    button.append(chevron);
    button.addEventListener('click', () => { autoSelectPending = false; selectCard(button); action(); });
    return button;
  }
  function previousConsultationCard(row) {
    const button = document.createElement('button'); button.type = 'button'; button.className = 'lon01-card is-closed';
    button.setAttribute('aria-pressed', 'false'); button.setAttribute('aria-controls', 'lon01-detail');
    const iconBox = document.createElement('span'); iconBox.className = 'lon01-history-icon-box'; iconBox.setAttribute('aria-hidden', 'true');
    const icon = document.createElement('span'); icon.className = 'material-symbols-rounded lon01-history-icon'; icon.textContent = 'history'; iconBox.append(icon);
    const copy = document.createElement('span'); copy.className = 'lon01-closed-copy';
    const title = document.createElement('strong'); title.textContent = 'CONSULTA ANTERIOR';
    const when = document.createElement('span'); when.className = 'lon01-closed-date'; when.textContent = closedDate(row.encounter_dt);
    const venue = document.createElement('span'); venue.className = 'lon01-closed-venue';
    copy.append(title, when, venue);
    const chevron = document.createElement('span'); chevron.className = 'material-symbols-rounded lon01-chevron'; chevron.setAttribute('aria-hidden', 'true'); chevron.textContent = 'chevron_right';
    button.append(iconBox, copy, chevron);
    button.addEventListener('click', () => { autoSelectPending = false; selectCard(button); openCanonical(row); });
    return {button, venue};
  }
  async function load(reset = false) {
    if (paging && !reset) return;
    const id = selectedPatient();
    if (reset) {
      epoch++; selection++; detailOrigin = null; autoSelectPending = !!root.closest('#t-historial-atencion.active'); fallbackOpen = null; root.classList.remove('vis05-detail-open'); detail.style.minHeight = ''; detail.removeAttribute('aria-busy'); show(empty, true); empty.textContent = 'Selecciona una consulta o un registro para revisar su detalle de sólo lectura.'; paging = false; patientId = id; offset = 0; seenCanonical.clear(); seenLegacy.clear();
      current.replaceChildren(); previous.replaceChildren(); legacy.replaceChildren(); detailBody.replaceChildren();
      appointments.clear(); officeNames.clear(); show(openSection, false);
      show(detail, false); show(content, false); show(more, false); show(error, false);
    }
    if (!id) { status.textContent = 'Selecciona un paciente para ver su historial.'; return; }
    paging = true; more.disabled = true;
    const seen = epoch;
    let loadNextForSelection = false;
    status.textContent = 'Cargando historial…';
    try {
      const data = await get(`patients/${encodeURIComponent(id)}/longitudinal-history?limit=25&offset=${offset}`);
      if (seen !== epoch || selectedPatient() !== id) return;
      if (offset > 0) for (const target of [current, previous, legacy]) if (!target.children.length) target.replaceChildren();
      let newestClosed = null;
      for (const row of data.canonical || []) {
        if (seenCanonical.has(row.encounter_key)) continue;
        seenCanonical.add(row.encounter_key);
        const isOpen = String(row.status).toLowerCase() === 'open';
        const closed = String(row.status).toLowerCase() === 'closed';
        const previousCard = closed ? previousConsultationCard(row) : null;
        const button = previousCard?.button || card(`Consulta ${state(row.status)}`, date(row.encounter_dt), () => openCanonical(row), isOpen ? 'is-current' : 'is-voided');
        (isOpen ? current : previous).append(button);
        if (previousCard) void appointmentVenue(row, button, previousCard.venue, seen, id);
        if (isOpen && !fallbackOpen) fallbackOpen = {button, row};
        if (row.status === 'closed' && !newestClosed) newestClosed = {button, row};
      }
      for (const row of data.legacy || []) {
        if (seenLegacy.has(row.entry_id)) continue;
        seenLegacy.add(row.entry_id);
        legacy.append(card(`Registro histórico${row.status === 'draft' ? ' · Borrador' : ''}`, `${legacyType(row.note_type)} · fecha de consulta no confirmada`, () => openLegacy(row), 'is-legacy'));
      }
      offset += 25;
      show(content, true); show(more, !!data.has_more);
      show(openSection, current.children.length > 0);
      if (!previous.children.length) previous.textContent = 'No hay consultas finalizadas o anuladas.';
      if (!legacy.children.length) legacy.textContent = 'No hay registros anteriores.';
      message('');
      if (autoSelectPending && newestClosed) {
        autoSelectPending = false;
        selectCard(newestClosed.button);
        void openCanonical(newestClosed.row, false);
      } else if (autoSelectPending && (data.canonical || []).length === 25 && data.has_more && offset <= 10000) {
        loadNextForSelection = true;
      } else if (autoSelectPending) {
        autoSelectPending = false;
        if (fallbackOpen) {
          selectCard(fallbackOpen.button);
          void openCanonical(fallbackOpen.row, false);
        }
      }
    } catch (failure) { if (seen === epoch) { autoSelectPending = false; message(failure.message, true); } }
    finally {
      if (seen === epoch) {
        paging = false; more.disabled = false;
        if (loadNextForSelection && autoSelectPending) void load();
      }
    }
  }
  $('[data-lon01-refresh]').addEventListener('click', () => load(true));
  more.addEventListener('click', () => load());
  $('[data-lon01-close]').addEventListener('click', () => { selection++; detail.removeAttribute('aria-busy'); root.classList.remove('vis05-detail-open'); show(empty, true); empty.textContent = 'Selecciona una consulta o un registro para revisar su detalle de sólo lectura.'; show(detail, false); detailBody.replaceChildren(); (detailOrigin?.isConnected ? detailOrigin : $('[data-lon01-refresh]')).focus(); });
  for (const name of ['patient:selected', 'expediente:patient_changed', 'expediente:patient-changed']) window.addEventListener(name, () => load(true));
  pane.querySelector('[data-bs-target="#t-historial-atencion"]')?.addEventListener('shown.bs.tab', () => load(true));
  new MutationObserver(() => { if (selectedPatient() !== patientId) load(true); }).observe(pane, {attributes:true, attributeFilter:['data-patient-id','data-active-patient-id']});
  detailTitle.setAttribute('tabindex', '-1');
  if (selectedPatient()) load(true);
})();
