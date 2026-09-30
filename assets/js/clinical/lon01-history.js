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
  const detail = $('[data-lon01-detail]');
  const detailTitle = $('[data-lon01-detail-title]');
  const detailMeta = $('[data-lon01-detail-meta]');
  const detailBody = $('[data-lon01-detail-body]');
  const timeline = $('.vis05-timeline');
  const listColumn = $('.lon01-list-column');
  const filters = $('[data-lon01-filters]');
  const filterEmpty = $('[data-lon01-filter-empty]');
  const filterButtons = [...filters.querySelectorAll('[data-lon01-filter]')];
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
  const timelineClock = value => {
    const raw = String(value || '').trim();
    if (!/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(raw)) return null;
    const instant = new Date(`${raw.replace(' ', 'T')}Z`);
    if (Number.isNaN(instant.getTime())) return null;
    const parts = Object.fromEntries(new Intl.DateTimeFormat('en-US', {
      timeZone:'America/Mexico_City', year:'numeric', month:'2-digit', day:'2-digit',
      hour:'2-digit', minute:'2-digit', second:'2-digit', hourCycle:'h23'
    }).formatToParts(instant).map(part => [part.type, part.value]));
    const local = `${parts.year}-${parts.month}-${parts.day} ${parts.hour}:${parts.minute}:${parts.second}`;
    return {local, label:closedDate(local)};
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
  let doctorId = '';
  let detailOrigin = null;
  let selection = 0;
  let autoSelectPending = false;
  let activeFilter = 'all';
  const selectActions = new WeakMap();
  const empty = $('[data-vis05-empty]');
  function selectCard(button) {
    root.querySelectorAll('[aria-controls="lon01-detail"][aria-pressed]').forEach(card => card.setAttribute('aria-pressed', String(card === button)));
    detailOrigin = button;
  }
  function fitTimelineViewport() {
    if (!matchMedia('(min-width:701px)').matches || !root.closest('#t-historial-atencion.active')) {
      root.style.removeProperty('--lon01-viewport-height');
      timeline.classList.remove('is-scrollable');
      return;
    }
    const footer = document.querySelector('.mm-footer');
    const footerHeight = footer && getComputedStyle(footer).display !== 'none' ? footer.getBoundingClientRect().height : 0;
    const timelineTop = listColumn.getBoundingClientRect().top + window.scrollY;
    const available = Math.max(180, Math.floor(window.innerHeight - timelineTop - footerHeight - 32));
    root.style.setProperty('--lon01-viewport-height', `${available}px`);
    root.style.setProperty('--lon01-filter-height', `${filters.classList.contains('d-none') ? 0 : filters.getBoundingClientRect().height + 8}px`);
    requestAnimationFrame(() => timeline.classList.toggle('is-scrollable', timeline.scrollHeight > timeline.clientHeight + 1));
  }
  function revealAutoSelected(button) {
    if (!matchMedia('(min-width:701px)').matches) return;
    const outer = timeline.getBoundingClientRect(), inner = button.getBoundingClientRect();
    if (inner.top < outer.top) timeline.scrollTop += inner.top - outer.top;
    else if (inner.bottom > outer.bottom) timeline.scrollTop += inner.bottom - outer.bottom;
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
  const prescriptionTypes = new Set(['prescription','receta']);
  const orderTypes = new Set(['order','orders','lab_order','imaging_order','orden_estudio']);
  const documentTypeLabel = row => prescriptionTypes.has(row.document_type) ? 'RECETA' : 'ORDEN DE ESTUDIOS';
  function documentDescriptor(row) {
    const count = Number(row.prescription_item_count);
    if (prescriptionTypes.has(row.document_type) && count > 1) return `${count} medicamentos`;
    return String(row.summary || row.title || '').trim();
  }
  function appendDocumentDetail(documentRow, row) {
    const content = documentRow.content || {};
    const payload = content.payload || {};
    const summary = String(content.summary || row.summary || '').trim();
    if (summary) detailBody.append(line('Resumen', summary));
    const rendered = String(content.rendered_text || payload.text || '').trim();
    if (rendered) {
      const body = document.createElement('pre'); body.className = 'lon01-document-text'; body.textContent = rendered; detailBody.append(body);
    } else if (prescriptionTypes.has(row.document_type)) {
      const items = Array.isArray(payload.prescription?.items) ? payload.prescription.items : [];
      group('Medicamentos prescritos', items, (target, item) => {
        const value = [item?.medicamento, item?.dosis, item?.via, item?.frecuencia, item?.duracion, item?.indicaciones]
          .filter(part => typeof part === 'string' && part.trim()).join(' · ');
        if (value) target.append(line('Medicamento', value));
      });
      if (typeof payload.prescription?.observaciones === 'string' && payload.prescription.observaciones.trim())
        detailBody.append(line('Observaciones', payload.prescription.observaciones));
    } else {
      const studies = Array.isArray(payload.requested_studies) ? payload.requested_studies : [];
      group('Estudios solicitados', studies, (target, study) => {
        const name = typeof study === 'string' ? study : typeof study?.name === 'string' ? study.name : '';
        if (name.trim()) target.append(line('Estudio', name));
      });
      if (typeof payload.indication === 'string' && payload.indication.trim()) detailBody.append(line('Indicación', payload.indication));
      if (typeof payload.priority === 'string' && payload.priority.trim()) detailBody.append(line('Prioridad', payload.priority));
    }
    if (!detailBody.children.length) detailBody.append(line('Contenido', 'No hay contenido adicional registrado.'));
  }
  async function openDocument(row, focus = true) {
    const seen = epoch, request = ++selection;
    if (!detail.classList.contains('d-none')) { preserveDetailHeight(); detail.setAttribute('aria-busy', 'true'); }
    else { show(empty, true); empty.textContent = 'Cargando detalle…'; }
    try {
      const data = await get(`doctors/${encodeURIComponent(doctorId)}/documents/${encodeURIComponent(row.document_uuid)}`);
      const item = data?.document;
      if (request !== selection || seen !== epoch || selectedPatient() !== patientId) return;
      if (!item || String(item.context?.patient_id) !== patientId || String(item.document_id) !== String(row.document_uuid)
          || String(item.document_type) !== String(row.document_type)) throw new Error('El documento no corresponde al registro seleccionado.');
      detailBody.replaceChildren();
      detailTitle.textContent = String(item.title || row.title || documentTypeLabel(row));
      detailMeta.textContent = `${documentTypeLabel(row)} · ${utcDate(row.timeline_at)} · ${({draft:'Borrador',generated:'Generada',signed:'Firmada',voided:'Anulada'})[item.status] || 'Registrada'}`;
      detail.dataset.state = prescriptionTypes.has(row.document_type) ? 'prescription' : 'order';
      appendDocumentDetail(item, row);
      reveal(focus); message('');
    } catch (failure) { if (seen === epoch && request === selection) { message(failure.message, true); empty.textContent = 'No se pudo cargar el detalle. Selecciona de nuevo el registro para reintentar.'; } }
    finally { if (seen === epoch && request === selection) detail.removeAttribute('aria-busy'); }
  }
  function openLegacy(row, focus = true) {
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
    reveal(focus);
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
    const choose = focus => { autoSelectPending = false; selectCard(button); action(focus); };
    selectActions.set(button, choose);
    button.addEventListener('click', () => choose(true));
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
    button.dataset.lon01Kind = 'consultations';
    const choose = focus => { autoSelectPending = false; selectCard(button); void openCanonical(row, focus); };
    selectActions.set(button, choose);
    button.addEventListener('click', () => choose(true));
    return {button, venue};
  }
  function openConsultationCard(id) {
    const button = document.createElement('button'); button.type = 'button'; button.className = 'lon01-card is-open-consultation';
    const iconBox = document.createElement('span'); iconBox.className = 'lon01-history-icon-box'; iconBox.setAttribute('aria-hidden', 'true');
    const icon = document.createElement('span'); icon.className = 'material-symbols-rounded lon01-history-icon'; icon.textContent = 'history'; iconBox.append(icon);
    const copy = document.createElement('span'); copy.className = 'lon01-closed-copy';
    const title = document.createElement('strong'); title.textContent = 'CONSULTA EN CURSO';
    const action = document.createElement('span'); action.className = 'lon01-open-action'; action.textContent = 'Volver a consulta';
    copy.append(title, action);
    const chevron = document.createElement('span'); chevron.className = 'material-symbols-rounded lon01-chevron'; chevron.setAttribute('aria-hidden', 'true'); chevron.textContent = 'chevron_right';
    button.append(iconBox, copy, chevron);
    button.dataset.lon01Kind = 'consultations';
    button.addEventListener('click', () => { if (selectedPatient() === id) void window.mxmedM7OpenFromHeader?.(id, 'resume'); });
    return button;
  }
  function documentEventRow(row, clock) {
    const button = document.createElement('button'); button.type = 'button'; button.className = 'lon01-event-row';
    button.setAttribute('aria-pressed', 'false'); button.setAttribute('aria-controls', 'lon01-detail');
    const icon = document.createElement('span'); icon.className = 'material-symbols-rounded lon01-event-icon'; icon.setAttribute('aria-hidden', 'true');
    icon.textContent = prescriptionTypes.has(row.document_type) ? 'prescriptions' : row.document_type === 'imaging_order' ? 'radiology' : 'science';
    const copy = document.createElement('span'); copy.className = 'lon01-event-copy';
    const title = document.createElement('strong'); title.textContent = documentTypeLabel(row);
    const meta = document.createElement('span'); meta.className = 'lon01-event-meta';
    const descriptor = documentDescriptor(row);
    meta.textContent = `${clock.label}${descriptor ? ` · ${descriptor}` : ''}`;
    copy.append(title, meta);
    const chevron = document.createElement('span'); chevron.className = 'material-symbols-rounded lon01-event-chevron'; chevron.setAttribute('aria-hidden', 'true'); chevron.textContent = 'chevron_right';
    button.append(icon, copy, chevron);
    button.dataset.lon01Kind = prescriptionTypes.has(row.document_type) ? 'prescriptions' : 'studies';
    const choose = focus => { autoSelectPending = false; selectCard(button); void openDocument(row, focus); };
    selectActions.set(button, choose);
    button.addEventListener('click', () => choose(true));
    return button;
  }
  function sortedHistory(canonical, documents) {
    const events = [];
    for (const row of canonical) {
      if (String(row.status).toLowerCase() === 'open') continue;
      const key = String(row.encounter_dt || '').trim().replace('T', ' ').slice(0, 19);
      events.push({kind:'encounter', row, key, id:Number(String(row.encounter_key || '').split(':').pop()) || 0});
    }
    for (const row of documents) {
      if (row.timeline_eligible !== true || row.timeline_scope !== 'PATIENT'
          || (!prescriptionTypes.has(row.document_type) && !orderTypes.has(row.document_type))) continue;
      if (String(row.patient_id) !== patientId || !row.document_uuid) continue;
      const clock = timelineClock(row.timeline_at);
      if (clock) events.push({kind:'document', row, clock, key:clock.local, id:Number(row.id) || 0});
    }
    return events.sort((a, b) => b.key.localeCompare(a.key)
      || (a.kind === b.kind ? b.id - a.id || String(b.row.document_uuid || b.row.encounter_key).localeCompare(String(a.row.document_uuid || a.row.encounter_key)) : a.kind === 'encounter' ? -1 : 1));
  }
  async function allHistoryRows(id, seen) {
    const canonical = [], historical = [], canonicalSeen = new Set(), legacySeen = new Set();
    for (let pageOffset = 0; pageOffset <= 10000; pageOffset += 25) {
      const data = await get(`patients/${encodeURIComponent(id)}/longitudinal-history?limit=25&offset=${pageOffset}`);
      if (seen !== epoch || selectedPatient() !== id) return null;
      for (const row of data.canonical || []) if (!canonicalSeen.has(row.encounter_key)) { canonicalSeen.add(row.encounter_key); canonical.push(row); }
      for (const row of data.legacy || []) if (!legacySeen.has(row.entry_id)) { legacySeen.add(row.entry_id); historical.push(row); }
      if (!data.has_more) return {canonical, historical};
    }
    throw new Error('El historial excede el límite de lectura disponible.');
  }
  async function allTimelineDocuments(id, seen) {
    const items = [], cursors = new Set();
    let cursor = '';
    while (true) {
      const data = await get(`doctors/${encodeURIComponent(doctorId)}/patients/${encodeURIComponent(id)}/documents?timeline_mode=1&limit=100${cursor ? `&cursor=${encodeURIComponent(cursor)}` : ''}`);
      if (seen !== epoch || selectedPatient() !== id) return null;
      items.push(...(data.items || []));
      if (!data.has_more) return items;
      cursor = String(data.cursor_next || '');
      if (!cursor || cursors.has(cursor)) throw new Error('La paginación del historial documental no avanzó.');
      cursors.add(cursor);
    }
  }
  function applyFilter(next, changed = true) {
    activeFilter = next;
    for (const button of filterButtons) button.setAttribute('aria-pressed', String(button.dataset.lon01Filter === next));
    const items = [...current.querySelectorAll('[data-lon01-kind]'), ...previous.querySelectorAll('[data-lon01-kind]')];
    for (const item of items) item.hidden = next !== 'all' && item.dataset.lon01Kind !== next;
    const visible = items.filter(item => !item.hidden);
    show(openSection, [...current.children].some(item => !item.hidden));
    if (!previous.children.length) show(previous, next === 'all');
    const emptyLabels = {
      consultations:'Sin consultas registradas.',
      prescriptions:'Sin recetas independientes registradas.',
      studies:'Sin estudios independientes registrados.'
    };
    filterEmpty.textContent = visible.length ? '' : emptyLabels[next] || '';
    show(filterEmpty, next !== 'all' && visible.length === 0);
    if (!changed) return;
    timeline.scrollTop = 0;
    const selectedVisible = detailOrigin?.isConnected && !detailOrigin.hidden;
    if (!selectedVisible) {
      selection++;
      detail.removeAttribute('aria-busy');
      detailOrigin = null;
      detailBody.replaceChildren();
      show(detail, false);
      root.classList.remove('vis05-detail-open');
      empty.textContent = visible.length ? 'Selecciona una consulta o un registro para revisar su detalle de sólo lectura.' : filterEmpty.textContent || 'Selecciona una consulta o un registro para revisar su detalle de sólo lectura.';
      show(empty, true);
      const firstDetail = visible.find(item => selectActions.has(item));
      if (firstDetail) selectActions.get(firstDetail)(false);
    }
    requestAnimationFrame(fitTimelineViewport);
  }
  async function load() {
    const id = selectedPatient();
    epoch++; selection++; detailOrigin = null; autoSelectPending = !!root.closest('#t-historial-atencion.active');
    root.classList.remove('vis05-detail-open'); detail.style.minHeight = ''; detail.removeAttribute('aria-busy');
    show(empty, true); empty.textContent = 'Selecciona una consulta o un registro para revisar su detalle de sólo lectura.';
    patientId = id; doctorId = '';
    current.replaceChildren(); previous.replaceChildren(); legacy.replaceChildren(); detailBody.replaceChildren();
    appointments.clear(); officeNames.clear(); show(openSection, false);
    show(detail, false); show(content, false); show(filters, false); show(filterEmpty, false); show(error, false);
    activeFilter = 'all';
    for (const button of filterButtons) button.setAttribute('aria-pressed', String(button.dataset.lon01Filter === 'all'));
    timeline.scrollTop = 0;
    if (!id) { status.textContent = 'Selecciona un paciente para ver su historial.'; return; }
    const seen = epoch;
    status.textContent = 'Cargando historial…';
    try {
      const [active, history] = await Promise.all([
        get(`patients/${encodeURIComponent(id)}/encounters/active`), allHistoryRows(id, seen)
      ]);
      if (seen !== epoch || selectedPatient() !== id) return;
      doctorId = String(active?.doctor_id || window.mxmedResolveActiveProfessionalContext?.()?.doctor_id
        || window.mxmedStore?.activeProfessionalContext?.doctor_id || window.mxmedStore?.doctor_id || '').trim();
      if (!doctorId) throw new Error('No se pudo confirmar el contexto del profesional para la cronología documental.');
      const documents = await allTimelineDocuments(id, seen);
      if (seen !== epoch || selectedPatient() !== id || !history || !documents) return;
      if (active && active.status === 'open' && String(active.patient_id) === id) current.append(openConsultationCard(id));
      const newestClosed = history.canonical.find(row => String(row.status).toLowerCase() === 'closed');
      let newestClosedButton = null;
      for (const event of sortedHistory(history.canonical, documents)) {
        if (event.kind === 'document') { previous.append(documentEventRow(event.row, event.clock)); continue; }
        const row = event.row;
        const previousCard = String(row.status).toLowerCase() === 'closed' ? previousConsultationCard(row) : null;
        const button = previousCard?.button || card(`Consulta ${state(row.status)}`, date(row.encounter_dt), focus => openCanonical(row, focus), 'is-voided');
        button.dataset.lon01Kind = 'consultations';
        previous.append(button);
        if (previousCard) void appointmentVenue(row, button, previousCard.venue, seen, id);
        if (row.encounter_key === newestClosed?.encounter_key) newestClosedButton = button;
      }
      for (const row of history.historical) {
        legacy.append(card(`Registro histórico${row.status === 'draft' ? ' · Borrador' : ''}`, `${legacyType(row.note_type)} · fecha de consulta no confirmada`, focus => openLegacy(row, focus), 'is-legacy'));
      }
      show(content, true);
      if (!previous.children.length) previous.textContent = 'No hay consultas finalizadas o anuladas.';
      show(filters, true);
      applyFilter('all', false);
      message('');
      fitTimelineViewport();
      if (autoSelectPending && newestClosed && newestClosedButton) {
        autoSelectPending = false;
        selectCard(newestClosedButton);
        void openCanonical(newestClosed, false);
        requestAnimationFrame(() => { if (seen === epoch && newestClosedButton.isConnected) revealAutoSelected(newestClosedButton); });
      }
      autoSelectPending = false;
    } catch (failure) { if (seen === epoch) { autoSelectPending = false; message(failure.message, true); } }
  }
  filters.addEventListener('click', event => {
    const button = event.target.closest('[data-lon01-filter]');
    if (button && button.dataset.lon01Filter !== activeFilter) applyFilter(button.dataset.lon01Filter);
  });
  filters.addEventListener('keydown', event => {
    if (!['ArrowLeft','ArrowRight','Home','End'].includes(event.key)) return;
    const index = filterButtons.indexOf(document.activeElement);
    if (index < 0) return;
    event.preventDefault();
    const next = event.key === 'Home' ? 0 : event.key === 'End' ? filterButtons.length - 1
      : (index + (event.key === 'ArrowRight' ? 1 : -1) + filterButtons.length) % filterButtons.length;
    filterButtons[next].focus({preventScroll:true});
    if (filterButtons[next].dataset.lon01Filter !== activeFilter) applyFilter(filterButtons[next].dataset.lon01Filter);
  });
  $('[data-lon01-refresh]').addEventListener('click', load);
  $('[data-lon01-close]').addEventListener('click', () => { const returnFocus = detailOrigin?.isConnected ? detailOrigin : $('[data-lon01-refresh]'); selection++; detail.removeAttribute('aria-busy'); root.classList.remove('vis05-detail-open'); show(empty, true); empty.textContent = 'Selecciona una consulta o un registro para revisar su detalle de sólo lectura.'; show(detail, false); detailBody.replaceChildren(); root.querySelectorAll('[aria-controls="lon01-detail"][aria-pressed]').forEach(card => card.setAttribute('aria-pressed', 'false')); detailOrigin = null; returnFocus.focus(); });
  for (const name of ['patient:selected', 'expediente:patient_changed', 'expediente:patient-changed']) window.addEventListener(name, load);
  pane.querySelector('[data-bs-target="#t-historial-atencion"]')?.addEventListener('shown.bs.tab', load);
  window.addEventListener('resize', fitTimelineViewport);
  new MutationObserver(() => { if (selectedPatient() !== patientId) load(); }).observe(pane, {attributes:true, attributeFilter:['data-patient-id','data-active-patient-id']});
  detailTitle.setAttribute('tabindex', '-1');
  if (selectedPatient()) load();
})();
