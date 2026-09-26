// M7 WS04: UI orchestration over the accepted M6 document routes. No local document authority.
(function () {
  window.mxmedM7WS04 = function (root, encounterUrl, selectedPatient) {
    const panel = root.querySelector('[data-m7-documents]');
    if (!panel) return null;
    const dialogs = [];
    const $ = selector => panel.querySelector(selector) || dialogs.map(dialog => dialog.querySelector(selector)).find(Boolean);
    const encounterList = $('[data-m7-encounter-documents]');
    const patientList = $('[data-m7-patient-documents]');
    const state = root.querySelector('[data-m7-doc-state]');
    const uploadForm = $('[data-m7-doc-upload-form]');
    const orderForm = $('[data-m7-order-form]');
    const resultForm = $('[data-m7-result-form]');
    const replaceForm = $('[data-m7-replace-form]');
    const uploadDialog = $('[data-docux-upload]');
    const captureDialog = $('[data-docux-capture]');
    let uploadTrigger = null;
    let session = null;
    const pollInterval = 2500;
    // Like Plan's dialogs, live at body level so workspace form styles do not
    // override the shared clinical modal shell. No clinical context moves with them.
    dialogs.push(uploadDialog, captureDialog); document.body.append(...dialogs);
    let context = null;
    let rows = [];
    let selectedReplacement = null;
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
    function sameContext() {
      const pane = root.closest('#p-expediente');
      const activePatient = pane?.dataset.patientId || pane?.dataset.activePatientId || selectedPatient();
      return !!context && selectedPatient() === context.patientId && activePatient === context.patientId;
    }
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
      if (sameContext() && ['prescription','receta'].includes(String(row.document_type || '').toLowerCase()) && ['generated','signed'].includes(String(row.status || '').toLowerCase()) && row.has_successor != 1) {
        const addMedication = document.createElement('button'); addMedication.type = 'button'; addMedication.className = 'btn btn-outline-primary btn-sm'; addMedication.textContent = 'Agregar a medicación';
        addMedication.addEventListener('click', () => root.dispatchEvent(new CustomEvent('lon05b:prescription-selected', {bubbles:true,detail:{documentId:Number(row.id),patientId:context.patientId,title:row.title || 'Receta',trigger:addMedication}})));
        actions.append(addMedication);
      }
      node.append(actions); return node;
    }
    function paint() {
      encounterList.replaceChildren(); patientList.replaceChildren();
      const encounterRows = rows.filter(row => String(row.encounter_ref_id || row.encounter_id || '') === String(context?.encounterId || ''));
      const patientRows = rows.filter(row => !row.encounter_ref_id && !row.encounter_id);
      for (const [target, items, empty] of [[encounterList, encounterRows, 'Aún no hay documentos registrados en esta consulta.'], [patientList, patientRows, 'Aún no hay documentos del paciente sin consulta.']]) {
        if (!items.length) { const p = document.createElement('p'); p.textContent = empty; target.append(p); }
        else items.forEach(row => target.append(card(row)));
      }
      $('[data-docux-general]').hidden = patientRows.length === 0;
      const orders = encounterRows.filter(row => orderTypes.has(String(row.document_type)));
      const orderSelect = $('[data-m7-result-order]'); const chosenOrder = orderSelect.value; orderSelect.replaceChildren(new Option('Selecciona una orden', ''));
      orders.forEach(row => orderSelect.add(new Option(row.title || 'Orden de estudio', row.document_uuid)));
      if (orders.some(row => row.document_uuid === chosenOrder)) orderSelect.value = chosenOrder;
      const open = context?.status === 'open';
      if (context?.status === 'voided') { selectedReplacement = null; show(replaceForm, false); }
      orderForm.classList.toggle('d-none', !open);
      resultForm.classList.toggle('d-none', context?.status === 'voided' || !orders.length);
      show($('[data-docux-actions]'), open);
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
    function returnFocus(trigger) {
      const target = trigger?.isConnected && trigger.getClientRects().length ? trigger : root.querySelector('[data-m7-section="documents"]');
      target?.focus();
    }
    function modalShell(dialog, close) {
      dialog.querySelector('[data-modal-close]').addEventListener('click', close);
      dialog.addEventListener('cancel', e => { e.preventDefault(); close(); });
      dialog.addEventListener('click', e => { if (e.target === dialog) { const r = dialog.getBoundingClientRect(); if (e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom) close(); } });
      dialog.addEventListener('keydown', e => {
        if (e.key !== 'Tab') return;
        const controls = [...dialog.querySelectorAll('button,input,select,textarea,a[href],[tabindex]')].filter(x => !x.disabled && x.tabIndex >= 0 && x.getClientRects().length);
        const first = controls[0], last = controls[controls.length - 1];
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last?.focus(); }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first?.focus(); }
      });
    }
    function uploadMessage(text, error = false) { const node = $('[data-docux-upload-state]'); node.textContent = text; node.dataset.error = String(error); }
    function fileState() {
      $('[data-docux-file-state]').textContent = $('[data-m7-doc-file]').files?.[0]?.name || 'Arrastra un PDF o imagen aquí';
      $('[data-docux-pick]').textContent = $('[data-m7-doc-file]').files?.length ? 'Cambiar archivo' : 'Seleccionar archivo';
      delete $('[data-docux-dropzone]').dataset.drag;
    }
    function closeUpload(force = false) {
      if (busy && !force) return;
      uploadDialog.close(); uploadDialog.classList.remove('plan02b-modal'); uploadForm.reset(); resetAttempt('upload'); fileState(); uploadMessage('');
      if (!force) returnFocus(uploadTrigger);
    }
    modalShell(uploadDialog, () => closeUpload());
    uploadDialog.querySelector('[data-modal-cancel]').addEventListener('click', () => closeUpload());
    $('[data-docux-attach]').addEventListener('click', e => {
      if (busy || !available() || context.status !== 'open') return;
      uploadTrigger = e.currentTarget; uploadMessage(''); fileState(); uploadDialog.classList.add('plan02b-modal'); uploadDialog.showModal(); $('[data-m7-doc-title]').focus();
    });
    $('[data-docux-pick]').addEventListener('click', () => $('[data-m7-doc-file]').click());
    $('[data-m7-doc-file]').addEventListener('change', () => {
      try { acceptedFile($('[data-m7-doc-file]')); uploadMessage(''); }
      catch (_) { $('[data-m7-doc-file]').value = ''; uploadMessage('Formato no compatible. Selecciona un PDF o una imagen JPG, PNG o WebP.', true); }
      fileState();
    });
    const dropzone = $('[data-docux-dropzone]');
    for (const type of ['dragenter','dragover']) dropzone.addEventListener(type, e => {
      e.preventDefault(); if (busy) return; dropzone.dataset.drag = 'true'; $('[data-docux-file-state]').textContent = 'Suelta el archivo para adjuntarlo';
    });
    dropzone.addEventListener('dragleave', e => { if (!dropzone.contains(e.relatedTarget)) fileState(); });
    dropzone.addEventListener('drop', e => {
      e.preventDefault(); if (busy) return; fileState();
      if (e.dataTransfer?.files.length !== 1) { uploadMessage('Selecciona un solo archivo.', true); return; }
      $('[data-m7-doc-file]').files = e.dataTransfer.files;
      $('[data-m7-doc-file]').dispatchEvent(new Event('change', { bubbles:true }));
    });
    uploadForm.addEventListener('submit', async event => {
      event.preventDefault(); if (busy || !available() || context.status !== 'open') return;
      let file;
      try { file = acceptedFile($('[data-m7-doc-file]')); }
      catch (_) { uploadMessage('Selecciona un PDF o una imagen JPG, PNG o WebP.', true); $('[data-docux-pick]').focus(); return; }
      if (!uploadForm.reportValidity()) return;
      const owner = { ...context };
      const payload = { document_type:file.type === 'application/pdf' ? 'pdf' : 'image', title:$('[data-m7-doc-title]').value.trim(), event_datetime:eventFor('upload'), payload:{ source:'m7_ws04' } };
      if (payload.document_type === 'image') payload.media_tag_key = $('[data-m7-doc-image-tag]').value.trim() || 'clinical_attachment';
      busy = true; const controls = [...uploadForm.querySelectorAll('button,input')]; controls.forEach(x => x.disabled = true); uploadMessage('Guardando documento…');
      try {
        await send(`encounters/${encodeURIComponent(owner.key)}/documents`, multipart(payload, file), attemptFor('upload'), true);
        if (sameContext() && context.key === owner.key && context.patientId === owner.patientId) {
          closeUpload(true); await refresh(); status('Documento guardado', 'saved'); returnFocus(uploadTrigger);
        }
      } catch (_) { if (uploadDialog.open && context?.key === owner.key && sameContext()) uploadMessage('No se pudo guardar el documento.', true); }
      finally { busy = false; controls.forEach(x => x.disabled = false); }
    });
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
    function currentSession(s) { return session === s && captureDialog.open && sameContext() && context.key === s.context.key && context.patientId === s.context.patientId; }
    function stopPolling(s) { if (!s) return; clearTimeout(s.timer); s.timer = null; s.controller?.abort(); s.controller = null; }
    function removeQR() { $('[data-docux-qr-content]').hidden = true; $('[data-docux-qr]').replaceChildren(); $('[data-m7-capture-link]').removeAttribute('href'); }
    function captureMessage(text) { $('[data-m7-capture-state]').textContent = text; }
    function terminal(s, value) {
      s.pending = false; stopPolling(s);
      if (!currentSession(s)) return;
      removeQR(); $('[data-m7-capture-cancel]').hidden = true; $('[data-docux-capture-close]').hidden = false;
      captureMessage(({ uploaded:'✓ Documento recibido', cancelled:'Captura cancelada', expired:'El enlace de captura venció.' })[value] || 'La sesión de captura ya no está disponible.');
      if (value === 'uploaded') refresh();
    }
    function schedule(s) {
      if (currentSession(s) && s.pending && s.token && !s.closing && !document.hidden) s.timer = setTimeout(() => poll(s), pollInterval);
    }
    async function poll(s) {
      if (!currentSession(s) || !s.pending || s.closing || document.hidden) return;
      const controller = new AbortController(); s.controller = controller;
      try {
        const response = await jsonResponse(await fetch(route(`note-capture-tokens/${encodeURIComponent(s.token)}`), { credentials:'same-origin', headers:{ Accept:'application/json' }, signal:controller.signal }));
        if (!currentSession(s) || s.closing || controller.signal.aborted) return;
        if (response.data.status !== 'pending') terminal(s, response.data.status);
        else captureMessage('Esperando captura…');
      } catch (error) { if (error.name !== 'AbortError' && currentSession(s) && !s.closing) captureMessage('No se pudo comprobar la captura. Volveremos a intentarlo.'); }
      finally { if (s.controller === controller) s.controller = null; if (!controller.signal.aborted) schedule(s); }
    }
    function dismissCapture(s, restore = true) {
      stopPolling(s);
      if (session !== s) return;
      removeQR(); captureDialog.close(); captureDialog.classList.remove('plan02b-modal'); session = null;
      if (restore) returnFocus($('[data-m7-capture-start]'));
    }
    async function cancelSession(s) {
      if (!s.token || !s.pending) return;
      // Read first so an already observed upload is never sent to cancel. Races
      // between this read and cancellation use the accepted terminal 409 contract.
      const data = await get(`note-capture-tokens/${encodeURIComponent(s.token)}`);
      if (data.status !== 'pending') { terminal(s, data.status); return; }
      try { await send(`note-capture-tokens/${encodeURIComponent(s.token)}/cancel`, {}, makeKey()); terminal(s, 'cancelled'); }
      catch (error) {
        if (error.code !== 'conflict' && error.code !== '409') throw error;
        const latest = await get(`note-capture-tokens/${encodeURIComponent(s.token)}`);
        if (latest.status === 'pending') throw error;
        terminal(s, latest.status);
      }
    }
    async function endCapture(close = true, contextLost = false) {
      const s = session; if (!s) return;
      if (s.closing) { if (contextLost) dismissCapture(s, false); return; }
      s.closing = true; stopPolling(s); removeQR();
      $('[data-m7-capture-cancel]').disabled = true; captureMessage('Cancelando captura…');
      if (contextLost) dismissCapture(s, false);
      try {
        await s.issuance;
        await cancelSession(s);
        if (close) dismissCapture(s, !contextLost);
      } catch (_) {
        if (session === s) captureMessage('No se pudo cancelar la captura. Intenta cancelar de nuevo antes de cerrar.');
        else status('No se pudo cancelar una captura anterior. Su enlace vencerá automáticamente.', 'failed');
      } finally { s.closing = false; if (session === s) $('[data-m7-capture-cancel]').disabled = false; }
    }
    modalShell(captureDialog, () => endCapture());
    $('[data-m7-capture-cancel]').addEventListener('click', () => endCapture(false));
    $('[data-docux-capture-close]').addEventListener('click', () => endCapture());
    $('[data-docux-copy]').addEventListener('click', async () => {
      const s = session; if (!s || !s.pending || !currentSession(s)) return;
      try { await navigator.clipboard.writeText(s.url); if (currentSession(s)) captureMessage('Enlace copiado. Esperando captura…'); }
      catch (_) { if (currentSession(s)) captureMessage('No se pudo copiar. Usa Abrir enlace de captura.'); }
    });
    $('[data-m7-capture-start]').addEventListener('click', () => {
      if (!sameContext() || context.status !== 'open' || captureBusy || session) return;
      const s = { context:{ ...context }, token:'', url:'', pending:true, closing:false, timer:null, controller:null };
      session = s; captureBusy = true; removeQR(); captureMessage('Preparando captura…');
      $('[data-m7-capture-cancel]').hidden = false; $('[data-m7-capture-cancel]').disabled = false; $('[data-docux-capture-close]').hidden = true;
      captureDialog.classList.add('plan02b-modal'); captureDialog.showModal(); $('[data-m7-capture-cancel]').focus();
      s.issuance = (async () => {
        try {
          const response = await send('note-capture-tokens', { patient_id:s.context.patientId, encounter_key:s.context.key, note_context:'nota_clinica_modal' }, makeKey());
          s.token = String(response.data?.token || ''); if (!s.token) throw new Error();
          s.url = new URL(response.data.mobile_url, location.origin).href;
          if (new URL(s.url).origin !== location.origin) throw new Error();
          if (!currentSession(s) || s.closing) return;
          const qr = $('[data-docux-qr]'); new QRCode(qr, { text:s.url, width:216, height:216, colorDark:'#000000', colorLight:'#ffffff', correctLevel:QRCode.CorrectLevel.M }); qr.removeAttribute('title');
          $('[data-m7-capture-link]').href = s.url; $('[data-docux-qr-content]').hidden = false; captureMessage('Esperando captura…'); schedule(s);
        } catch (_) {
          if (currentSession(s) && !s.closing) {
            captureMessage(s.token ? 'No se pudo mostrar la captura. Cierra esta ventana para cancelarla.' : 'No se pudo confirmar el inicio de la captura. Si se creó un enlace, vencerá automáticamente.'); removeQR();
            if (!s.token) { $('[data-m7-capture-cancel]').hidden = true; $('[data-docux-capture-close]').hidden = false; }
          }
        } finally { captureBusy = false; }
      })();
    });
    document.addEventListener('visibilitychange', () => { const s = session; if (!s) return; stopPolling(s); if (!document.hidden) schedule(s); });
    window.addEventListener('pagehide', () => {
      const s = session; stopPolling(s);
      if (s?.token && s.pending) fetch(route(`note-capture-tokens/${encodeURIComponent(s.token)}/cancel`), { method:'POST', credentials:'same-origin', keepalive:true, headers:{ 'Content-Type':'application/json' }, body:'{}' }).catch(() => {});
    });
    const body = root.querySelector('[data-m7-body]');
    new MutationObserver(() => {
      if (session && (body.dataset.encounterKey !== session.context.key || body.dataset.encounterState !== 'open' || !sameContext() || !root.getClientRects().length)) endCapture(true, true);
    }).observe(root.closest('#p-expediente') || root, { attributes:true, subtree:true, attributeFilter:['data-patient-id','data-active-patient-id','data-encounter-key','data-encounter-state','class'] });
    return {
      select(selected) { show(panel, selected); if (!selected && session) endCapture(true, true); if (selected && context) refresh(); },
      isDirty() { return !!($('[data-m7-doc-title]').value.trim() || $('[data-m7-doc-file]').files?.length || $('[data-m7-order-title]').value.trim() || $('[data-m7-order-summary]').value.trim() || $('[data-m7-result-order]').value || $('[data-m7-result-title]').value.trim() || $('[data-m7-result-provenance]').value.trim() || $('[data-m7-result-file]').files?.length || $('[data-m7-replace-reason]').value.trim() || $('[data-m7-replace-file]').files?.length); },
      isBusy() { return busy || captureBusy; },
      async leaveView() {
        if (busy) return false;
        if (session) await endCapture();
        if (session || captureBusy) return false;
        closeUpload(true); return true;
      },
      load(encounter) { const key = String(encounter.encounter_key || ''); const patientId = String(encounter.patient_id || ''); if (!key || !patientId) return; const changed = context?.key !== key || context?.patientId !== patientId; context = { key, patientId, doctorId:String(encounter.doctor_id || ''), encounterId:String(encounter.encounter_id || ''), status:String(encounter.status || '').toLowerCase(), closedAt:String(encounter.closed_at || '') }; if (changed) { epoch++; if (session) endCapture(true, true); closeUpload(true); rows = []; selectedReplacement = null; attempts.clear(); [uploadForm, orderForm, resultForm, replaceForm].forEach(form => form.reset()); show(replaceForm, false);  } paint(); },
      reset() { epoch++; if (session) endCapture(true, true); closeUpload(true); context = null; rows = []; selectedReplacement = null; attempts.clear(); [uploadForm, orderForm, resultForm, replaceForm].forEach(form => form.reset()); show(panel, false); show(replaceForm, false);  }
    };
  };
})();
