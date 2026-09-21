// M7 WS04: UI orchestration over the accepted M6 document routes. No local document authority.
(function () {
  window.mxmedM7WS04 = function (root, encounterUrl, selectedPatient) {
    const panel = root.querySelector('[data-m7-documents]');
    if (!panel) return null;
    const $ = selector => panel.querySelector(selector);
    const encounterList = $('[data-m7-encounter-documents]');
    const patientList = $('[data-m7-patient-documents]');
    const state = $('[data-m7-doc-state]');
    const uploadForm = $('[data-m7-doc-upload-form]');
    const orderForm = $('[data-m7-order-form]');
    const resultForm = $('[data-m7-result-form]');
    const replaceForm = $('[data-m7-replace-form]');
    const capture = $('[data-m7-capture]');
    let context = null;
    let rows = [];
    let selectedReplacement = null;
    let token = '';
    let busy = false;
    let captureBusy = false;
    let epoch = 0;
    const attempts = new Map();
    const resultTypes = new Set(['lab_result', 'lab_pdf', 'imaging_result', 'external_result', 'external_report']);
    const orderTypes = new Set(['order', 'orders', 'lab_order', 'imaging_order', 'orden_estudio']);
    const show = (node, visible) => node?.classList.toggle('d-none', !visible);
    const utc = () => new Date().toISOString().slice(0, 19).replace('T', ' ');
    const route = path => `/api/clinical/index.php/${path}`;
    const codeOf = value => typeof value === 'string' ? value : String(value?.code || '');
    function makeKey() {
      if (globalThis.crypto?.randomUUID) return globalThis.crypto.randomUUID();
      const bytes = new Uint8Array(16);
      if (globalThis.crypto?.getRandomValues) globalThis.crypto.getRandomValues(bytes);
      else for (let i = 0; i < bytes.length; i++) bytes[i] = Math.floor(Math.random() * 256);
      bytes[6] = (bytes[6] & 15) | 64; bytes[8] = (bytes[8] & 63) | 128;
      const hex = Array.from(bytes, byte => byte.toString(16).padStart(2, '0')).join('');
      return `${hex.slice(0,8)}-${hex.slice(8,12)}-${hex.slice(12,16)}-${hex.slice(16,20)}-${hex.slice(20)}`;
    }
    const errorMessage = code => ({
      IDEMPOTENCY_KEY_REUSED:'Conflicto: este intento ya se usó con contenido distinto. Revisa la acción antes de iniciar otra.',
      DOCUMENT_CONTEXT_MISMATCH:'El documento no corresponde a esta consulta. No se guardó en otro contexto.',
      M6_WRITE_WINDOW_BLOCKED:'Las escrituras clínicas están pausadas. Conserva tu selección y vuelve a intentar cuando se reanuden.',
      SCHEMA_NOT_READY:'El esquema clínico no está listo. No se intentó repararlo.',
      ENCOUNTER_TERMINAL:'Esta consulta ya terminó. Sólo se admiten resultados tardíos vinculados a su orden.',
      ENCOUNTER_VOIDED:'La consulta anulada es sólo de lectura.',
      DOCUMENT_ALREADY_SUPERSEDED:'Otra versión ya reemplazó este documento. Actualiza el historial.',
      DOCUMENT_NOT_FOUND:'El documento no está disponible en este contexto.'
    })[code] || 'No se completó la acción clínica. Revisa el estado y vuelve a intentar.';
    function status(text, kind = '') { state.textContent = text; state.dataset.state = kind; }
    async function jsonResponse(response) {
      const value = await response.json().catch(() => null);
      if (!response.ok || value?.ok !== true) {
        const error = new Error(errorMessage(codeOf(value?.error) || String(response.status)));
        error.code = codeOf(value?.error) || String(response.status);
        throw error;
      }
      return value;
    }
    async function get(path) { return (await jsonResponse(await fetch(route(path), { credentials:'same-origin', headers:{ Accept:'application/json' } }))).data; }
    async function send(path, payload, attempt, multipart = false) {
      const headers = { Accept:'application/json', 'Idempotency-Key':attempt };
      if (!multipart) headers['Content-Type'] = 'application/json';
      return jsonResponse(await fetch(route(path), { method:'POST', credentials:'same-origin', headers, body:multipart ? payload : JSON.stringify(payload) }));
    }
    function attemptFor(kind) { if (!attempts.has(kind)) attempts.set(kind, { key:makeKey(), eventTime:utc() }); return attempts.get(kind).key; }
    function eventFor(kind) { attemptFor(kind); return attempts.get(kind).eventTime; }
    function resetAttempt(kind) { attempts.delete(kind); }
    function bindAttempt(form, kind) { form.addEventListener('input', () => resetAttempt(kind)); form.addEventListener('change', () => resetAttempt(kind)); }
    bindAttempt(uploadForm, 'upload'); bindAttempt(orderForm, 'order'); bindAttempt(resultForm, 'result'); bindAttempt(replaceForm, 'replace');
    for (const input of [$('[data-m7-doc-file]'), $('[data-m7-result-file]'), $('[data-m7-replace-file]')]) {
      input.addEventListener('change', () => { if (input.files?.length) status('Archivo seleccionado; aún no está guardado.', 'selected'); });
    }
    function sameContext() { return !!context && selectedPatient() === context.patientId; }
    function available() { return sameContext() && context.status !== 'voided'; }
    function acceptedFile(input) { const file = input.files?.[0]; if (!file || !['application/pdf','image/jpeg','image/png','image/webp'].includes(file.type)) throw new Error('Selecciona un PDF o una imagen JPG, PNG o WebP.'); return file; }
    function multipart(payload, file) { const form = new FormData(); Object.entries(payload).forEach(([key, value]) => form.append(key, key === 'payload' || key === 'replacement' ? JSON.stringify(value) : value)); form.append('file', file); return form; }
    function payloadOf(row) { try { return typeof row.payload_json === 'string' ? JSON.parse(row.payload_json || '{}') : row.payload_json || {}; } catch (_) { return {}; } }
    function relatedOrder(row) { const payload = payloadOf(row); return String(payload.related_order_document_uuid || payload.related_order_document_id || payload.related_document_uuid || payload.related_document_id || payload.related_order_id || payload.context?.related_order_document_uuid || ''); }
    function documentLabel(row) { return ({ pdf:'PDF clínico', image:'Imagen clínica', order:'Orden de estudio', lab_result:'Resultado de laboratorio', imaging_result:'Resultado de imagen', external_report:'Informe externo', prescription:'Receta', receta:'Receta' })[row.document_type] || String(row.document_type || 'Documento clínico').replaceAll('_', ' '); }
    function card(row, group) {
      const node = document.createElement('article'); node.className = 'm7-doc-card';
      const title = document.createElement('strong'); title.textContent = row.title || documentLabel(row);
      const statusLabel = ({ signed:'Firmado', generated:'Generado', draft:'Borrador', voided:'Anulado' })[String(row.status || '').toLowerCase()] || 'Registrado';
      const meta = document.createElement('p'); meta.textContent = `${documentLabel(row)} · ${String(row.event_datetime || '').replace('T',' ') || 'Sin fecha'} · ${statusLabel}`;
      node.append(title, meta);
      if (row.has_successor == 1) { const badge = document.createElement('span'); badge.className = 'm7-doc-badge'; badge.textContent = 'Reemplazado · versión anterior'; node.append(badge); }
      else if (rows.some(item => String(item.lineage_root_id) === String(row.lineage_root_id) && String(item.id) !== String(row.id))) { const badge = document.createElement('span'); badge.className = 'm7-doc-badge'; badge.textContent = 'Versión vigente'; node.append(badge); }
      if (context?.status === 'closed' && row.created_after_final_note == 1) {
        const isReplacement = String(row.lineage_root_id) !== String(row.id);
        if (isReplacement || resultTypes.has(String(row.document_type))) {
          const badge = document.createElement('span'); badge.className = 'm7-doc-badge m7-doc-late';
          badge.textContent = isReplacement ? 'Reemplazo documental posterior al cierre' : 'Resultado recibido después de finalizar la consulta';
          node.append(badge);
        }
      }
      const family = rows.filter(item => String(item.lineage_root_id) === String(row.lineage_root_id));
      if (family.length > 1) { const lineage = document.createElement('p'); lineage.textContent = `Historial: ${family.length} versiones. La original permanece disponible.`; node.append(lineage); }
      if (resultTypes.has(String(row.document_type))) { const order = rows.find(item => String(item.document_uuid) === relatedOrder(row) || String(item.id) === relatedOrder(row)); if (order) { const relation = document.createElement('p'); relation.textContent = `Resultado de: ${order.title || 'orden de estudio'}`; node.append(relation); } }
      const actions = document.createElement('div'); actions.className = 'm7-doc-card-actions';
      if (row.has_private_binary == 1) { const read = document.createElement('button'); read.type = 'button'; read.className = 'btn btn-outline-primary btn-sm'; read.textContent = 'Abrir archivo'; read.addEventListener('click', () => privateRead(row)); actions.append(read); }
      if (available() && row.has_successor != 1 && payloadOf(row).auto_generated !== true) { const replace = document.createElement('button'); replace.type = 'button'; replace.className = 'btn btn-outline-secondary btn-sm'; replace.textContent = 'Reemplazar'; replace.addEventListener('click', () => { selectedReplacement = row; $('[data-m7-replace-target]').textContent = row.title || documentLabel(row); show(replaceForm, true); replaceForm.scrollIntoView({ block:'nearest' }); }); actions.append(replace); }
      node.append(actions); return node;
    }
    function paint() {
      encounterList.replaceChildren(); patientList.replaceChildren();
      const encounterRows = rows.filter(row => String(row.encounter_ref_id || row.encounter_id || '') === String(context?.encounterId || ''));
      const patientRows = rows.filter(row => !row.encounter_ref_id && !row.encounter_id);
      for (const [target, items, empty] of [[encounterList, encounterRows, 'Aún no hay documentos de esta consulta.'], [patientList, patientRows, 'Aún no hay documentos del paciente sin consulta.']]) {
        if (!items.length) { const p = document.createElement('p'); p.textContent = empty; target.append(p); }
        else items.forEach(row => target.append(card(row)));
      }
      const orders = encounterRows.filter(row => orderTypes.has(String(row.document_type)));
      const orderSelect = $('[data-m7-result-order]'); const chosenOrder = orderSelect.value; orderSelect.replaceChildren(new Option('Selecciona una orden', ''));
      orders.forEach(row => orderSelect.add(new Option(row.title || 'Orden de estudio', row.document_uuid)));
      if (orders.some(row => row.document_uuid === chosenOrder)) orderSelect.value = chosenOrder;
      const open = context?.status === 'open';
      if (context?.status === 'voided') { selectedReplacement = null; show(replaceForm, false); }
      orderForm.classList.toggle('d-none', !open);
      resultForm.classList.toggle('d-none', context?.status === 'voided' || !orders.length);
      show(capture, open);
      uploadForm.classList.toggle('d-none', !open);
    }
    async function refresh() {
      if (!sameContext()) return;
      const seen = ++epoch; status('Cargando documentos…');
      try {
        const detail = await get(`encounters/${encodeURIComponent(context.key)}`);
        if (seen !== epoch || !sameContext() || String(detail.patient_id) !== context.patientId) return;
        context.status = String(detail.status || '').toLowerCase(); context.closedAt = String(detail.closed_at || '');
        const data = await get(`doctors/${encodeURIComponent(context.doctorId)}/patients/${encodeURIComponent(context.patientId)}/documents?limit=200`);
        if (seen !== epoch || !sameContext()) return;
        rows = Array.isArray(data.items) ? data.items : []; paint(); status(`${rows.length} documento(s) disponibles.`, 'saved');
      } catch (error) { if (seen === epoch) status(errorMessage(error.code), 'failed'); }
    }
    async function execute(kind, form, fn) {
      if (busy || !available()) return;
      busy = true; const controls = [...form.querySelectorAll('button, input, select, textarea')]; controls.forEach(control => control.disabled = true);
      status(kind === 'upload' || kind === 'result' || kind === 'replace' ? 'Subiendo archivo… aún no está guardado.' : 'Creando orden…', 'uploading');
      try { await fn(); resetAttempt(kind); form.reset(); if (kind === 'replace') { selectedReplacement = null; show(replaceForm, false); } await refresh(); status('Guardado en el expediente clínico.', 'saved'); }
      catch (error) { status(error.message || errorMessage(error.code), error.code === 'IDEMPOTENCY_KEY_REUSED' ? 'conflict' : error.code === 'M6_WRITE_WINDOW_BLOCKED' ? 'blocked' : 'failed'); }
      finally { busy = false; controls.forEach(control => control.disabled = false); paint(); }
    }
    uploadForm.addEventListener('submit', event => { event.preventDefault(); execute('upload', uploadForm, async () => {
      const file = acceptedFile($('[data-m7-doc-file]')); if (context.status !== 'open') throw new Error('Esta consulta no admite adjuntos nuevos.');
      const payload = { document_type:file.type === 'application/pdf' ? 'pdf' : 'image', title:$('[data-m7-doc-title]').value.trim(), event_datetime:eventFor('upload'), payload:{ source:'m7_ws04' } };
      if (payload.document_type === 'image') payload.media_tag_key = $('[data-m7-doc-image-tag]').value.trim() || 'clinical_attachment';
      await send(`encounters/${encodeURIComponent(context.key)}/documents`, multipart(payload, file), attemptFor('upload'), true);
    }); });
    orderForm.addEventListener('submit', event => { event.preventDefault(); execute('order', orderForm, async () => {
      if (context.status !== 'open') throw new Error('No se pueden crear órdenes en esta consulta.');
      await send(`encounters/${encodeURIComponent(context.key)}/documents`, { document_type:'order', title:$('[data-m7-order-title]').value.trim(), summary:$('[data-m7-order-summary]').value.trim(), event_datetime:eventFor('order'), payload:{ source:'m7_ws04' } }, attemptFor('order'));
    }); });
    resultForm.addEventListener('submit', event => { event.preventDefault(); execute('result', resultForm, async () => {
      const file = acceptedFile($('[data-m7-result-file]')); const order = $('[data-m7-result-order]').value;
      if (!rows.some(row => row.document_uuid === order && String(row.encounter_ref_id || row.encounter_id || '') === String(context.encounterId) && orderTypes.has(String(row.document_type)))) throw new Error('Selecciona una orden de esta consulta.');
      const provenance = $('[data-m7-result-provenance]').value.trim();
      const payload = { document_type:$('[data-m7-result-type]').value, title:$('[data-m7-result-title]').value.trim(), event_datetime:eventFor('result'), provenance, payload:{ related_order_document_uuid:order, provenance, source:'m7_ws04' } };
      await send(`encounters/${encodeURIComponent(context.key)}/documents`, multipart(payload, file), attemptFor('result'), true);
    }); });
    replaceForm.addEventListener('submit', event => { event.preventDefault(); execute('replace', replaceForm, async () => {
      if (!selectedReplacement || !rows.some(row => row.document_uuid === selectedReplacement.document_uuid && row.has_successor != 1)) throw new Error('Actualiza el historial antes de reemplazar.');
      const file = acceptedFile($('[data-m7-replace-file]')); const original = selectedReplacement;
      const orderRef = relatedOrder(original);
      const newContent = { source:'m7_ws04_replacement' };
      if (resultTypes.has(String(original.document_type)) && orderRef) newContent.related_order_document_uuid = orderRef;
      const payload = { reason:$('[data-m7-replace-reason]').value.trim(), replacement:{ document_type:original.document_type, title:original.title, summary:original.summary || '', event_datetime:eventFor('replace'), payload:newContent } };
      await send(`documents/${encodeURIComponent(original.document_uuid)}/amendments`, multipart(payload, file), attemptFor('replace'), true);
    }); });
    async function privateRead(row) {
      if (!sameContext()) return;
      try { const response = await fetch(route(`documents/${encodeURIComponent(row.document_uuid)}/binary/ORIGINAL`), { credentials:'same-origin' }); if (!response.ok || !['application/pdf','image/jpeg','image/png','image/webp'].includes((response.headers.get('Content-Type') || '').split(';')[0].trim())) throw new Error(); const blob = await response.blob(); const url = URL.createObjectURL(blob); const anchor = document.createElement('a'); anchor.href = url; anchor.target = '_blank'; anchor.rel = 'noopener noreferrer'; anchor.click(); setTimeout(() => URL.revokeObjectURL(url), 60000); }
      catch (_) { status('No se pudo abrir el archivo privado autorizado.', 'failed'); }
    }
    $('[data-m7-doc-refresh]').addEventListener('click', refresh);
    $('[data-m7-replace-cancel]').addEventListener('click', () => { selectedReplacement = null; show(replaceForm, false); replaceForm.reset(); resetAttempt('replace'); });
    $('[data-m7-capture-start]').addEventListener('click', async () => {
      if (!sameContext() || context.status !== 'open' || captureBusy || token) return;
      captureBusy = true; $('[data-m7-capture-start]').disabled = true;
      try { const response = await send('note-capture-tokens', { patient_id:context.patientId, encounter_key:context.key, note_context:'nota_clinica_modal' }, makeKey()); token = String(response.data?.token || ''); if (!token) throw new Error(); const link = $('[data-m7-capture-link]'); link.href = new URL(response.data.mobile_url, location.origin).href; show($('[data-m7-capture-session]'), true); $('[data-m7-capture-state]').textContent = 'Enlace pendiente de captura.'; }
      catch (_) { status('No se pudo iniciar la captura.', 'failed'); }
      finally { captureBusy = false; $('[data-m7-capture-start]').disabled = false; }
    });
    $('[data-m7-capture-check]').addEventListener('click', async () => {
      if (!token || !sameContext() || captureBusy) return;
      try { const data = await get(`note-capture-tokens/${encodeURIComponent(token)}`); const value = String(data.status || ''); $('[data-m7-capture-state]').textContent = value === 'uploaded' ? 'Captura guardada.' : value === 'pending' ? 'Captura pendiente.' : `Captura ${value}; no hay nuevo documento guardado.`; if (value === 'uploaded') { token = ''; show($('[data-m7-capture-session]'), false); await refresh(); } }
      catch (_) { status('No se pudo comprobar la captura.', 'failed'); }
    });
    $('[data-m7-capture-cancel]').addEventListener('click', async () => {
      if (!token || !sameContext() || captureBusy) return;
      captureBusy = true; $('[data-m7-capture-cancel]').disabled = true;
      try { await send(`note-capture-tokens/${encodeURIComponent(token)}/cancel`, {}, makeKey()); token = ''; show($('[data-m7-capture-session]'), false); status('Enlace cancelado.'); }
      catch (_) { status('No se pudo cancelar el enlace.', 'failed'); }
      finally { captureBusy = false; $('[data-m7-capture-cancel]').disabled = false; }
    });
    return {
      select(selected) { show(panel, selected); if (selected && context) refresh(); },
      isDirty() { return !!($('[data-m7-doc-title]').value.trim() || $('[data-m7-doc-file]').files?.length || $('[data-m7-order-title]').value.trim() || $('[data-m7-order-summary]').value.trim() || $('[data-m7-result-order]').value || $('[data-m7-result-title]').value.trim() || $('[data-m7-result-provenance]').value.trim() || $('[data-m7-result-file]').files?.length || $('[data-m7-replace-reason]').value.trim() || $('[data-m7-replace-file]').files?.length); },
      isBusy() { return busy || captureBusy; },
      load(encounter) { const key = String(encounter.encounter_key || ''); const patientId = String(encounter.patient_id || ''); if (!key || !patientId) return; const changed = context?.key !== key || context?.patientId !== patientId; context = { key, patientId, doctorId:String(encounter.doctor_id || ''), encounterId:String(encounter.encounter_id || ''), status:String(encounter.status || '').toLowerCase(), closedAt:String(encounter.closed_at || '') }; if (changed) { rows = []; token = ''; selectedReplacement = null; attempts.clear(); [uploadForm, orderForm, resultForm, replaceForm].forEach(form => form.reset()); show(replaceForm, false); show($('[data-m7-capture-session]'), false); } paint(); },
      reset() { epoch++; context = null; rows = []; token = ''; selectedReplacement = null; attempts.clear(); [uploadForm, orderForm, resultForm, replaceForm].forEach(form => form.reset()); show(panel, false); show(replaceForm, false); show($('[data-m7-capture-session]'), false); }
    };
  };
})();
