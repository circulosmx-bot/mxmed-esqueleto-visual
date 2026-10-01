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
    const replaceForm = $('[data-m7-replace-form]');
    const uploadDialog = $('[data-docux-upload]');
    const captureDialog = $('[data-docux-capture]');
    const readerDialog = $('[data-doc-reader]');
    let readerMode='patient',readerTrigger=null;
    let uploadTrigger = null;
    let session = null;
    const pollInterval = 2500;
    // Like Plan's dialogs, live at body level so workspace form styles do not
    // override the shared clinical modal shell. No clinical context moves with them.
    dialogs.push(uploadDialog, captureDialog, readerDialog); document.body.append(...dialogs);
    let context = null;
    let rows = [];
    let selectedReplacement = null;
    let busy = false;
    let captureBusy = false;
    let epoch = 0;
    const attempts = new Map();
    const resultTypes = new Set(['lab_result', 'lab_pdf', 'imaging_result', 'external_result', 'external_report', 'result']);
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
    bindAttempt(uploadForm, 'upload'); bindAttempt(replaceForm, 'replace');
    for (const input of [$('[data-m7-doc-file]'), $('[data-m7-replace-file]')]) {
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
    function logicalDocuments(items) {
      const seen=new Set();return items.filter(row=>{const payload=payloadOf(row);if(!payload.capture_session_uuid||!payload.media_bundle_id)return true;if(seen.has(payload.media_bundle_id))return false;seen.add(payload.media_bundle_id);return true;});
    }
    function openBundle(row){const payload=payloadOf(row);window.open('/modules/clinical/ui/viewer.php?bundle_id='+encodeURIComponent(payload.media_bundle_id)+'&patient_id='+encodeURIComponent(context.patientId),'_blank','noopener');}
    function documentLabel(row) { const captured=payloadOf(row);if(captured.capture_session_uuid)return captured.media_page_count+' páginas';if(captured.source==='step6_mobile_capture_r4'&&captured.capture_classification_label)return String(captured.capture_classification_label);if(row.document_type==='pdf'&&row.has_private_binary!=1)return 'Documento clínico';return ({ pdf:'PDF clínico', image:'Imagen clínica', order:'Orden de estudio', lab_result:'Resultado de laboratorio', imaging_result:'Resultado de imagen', external_report:'Informe externo', prescription:'Receta', receta:'Receta' })[row.document_type] || String(row.document_type || 'Documento clínico').replaceAll('_', ' '); }
    function card(row) {
      const node = document.createElement('article'); node.className = 'm7-doc-card';node.dataset.document=row.document_uuid;
      const icon=document.createElement('span');icon.className='material-symbols-rounded';icon.setAttribute('aria-hidden','true');icon.textContent='description';node.append(icon);
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
      if (row.has_private_binary == 1) { const read = document.createElement('button'); read.type = 'button'; read.className = 'btn btn-outline-primary btn-sm'; read.textContent = 'Abrir archivo'; read.addEventListener('click', () => payloadOf(row).capture_session_uuid?openBundle(row):privateRead(row)); actions.append(read); }
      if (!payloadOf(row).capture_session_uuid && available() && row.has_successor != 1 && payloadOf(row).auto_generated !== true) { const replace = document.createElement('button'); replace.type = 'button'; replace.className = 'btn btn-outline-secondary btn-sm'; replace.textContent = 'Reemplazar'; replace.addEventListener('click', () => { closeReader();selectedReplacement = row; $('[data-m7-replace-target]').textContent = row.title || documentLabel(row); show(replaceForm, true); replaceForm.scrollIntoView({ block:'nearest' }); }); actions.append(replace); }
      if (sameContext() && ['prescription','receta'].includes(String(row.document_type || '').toLowerCase()) && ['generated','signed'].includes(String(row.status || '').toLowerCase()) && row.has_successor != 1) {
        const addMedication = document.createElement('button'); addMedication.type = 'button'; addMedication.className = 'btn btn-outline-primary btn-sm'; addMedication.textContent = 'Agregar a medicación';
        addMedication.addEventListener('click', () => root.dispatchEvent(new CustomEvent('lon05b:prescription-selected', {bubbles:true,detail:{documentId:Number(row.id),patientId:context.patientId,title:row.title || 'Receta',trigger:addMedication}})));
        actions.append(addMedication);
      }
      node.append(actions); return node;
    }
    function previewCard(row){
      const node=document.createElement('button');node.type='button';node.className='m7-doc-card flow-doc-preview';
      const icon=document.createElement('span');icon.className='material-symbols-rounded';icon.setAttribute('aria-hidden','true');icon.textContent='description';
      const title=document.createElement('strong');title.textContent=row.title||documentLabel(row);
      const meta=document.createElement('p');const stateLabel=({signed:'Firmado',generated:'Generado',draft:'Borrador',voided:'Anulado'})[String(row.status||'').toLowerCase()]||'Registrado';
      meta.textContent=documentLabel(row)+' · '+stateLabel+(row.has_successor==1?' · Reemplazado':'');
      node.title=title.textContent+' · '+String(row.event_datetime||'').replace('T',' ');node.setAttribute('aria-label',`${title.textContent}. ${meta.textContent}. Ver detalles`);
      node.append(icon,title,meta);node.onclick=()=>{if(payloadOf(row).capture_session_uuid){openBundle(row);return;}readDocuments('encounter',node);[...patientList.children].find(card=>card.dataset.document===row.document_uuid)?.scrollIntoView({block:'nearest'});};return node;
    }
    function paint() {
      encounterList.replaceChildren(); patientList.replaceChildren();
      const encounterRows = logicalDocuments(rows.filter(row => String(row.encounter_ref_id || row.encounter_id || '') === String(context?.encounterId || '')));
      const preview=encounterRows.filter(row=>row.has_successor!=1).slice(0,3);
      if (!preview.length) {const p=document.createElement('p');p.textContent='Aún no hay documentos registrados en esta consulta.';encounterList.append(p);}
      else preview.forEach(row=>encounterList.append(previewCard(row)));
      $('[data-doc-count]').textContent=encounterRows.length?`(${encounterRows.length})`:'';
      $('[data-doc-patient-total]').textContent=rows.length?`El paciente tiene ${logicalDocuments(rows).length} documentos en su expediente.`:'Sin documentos registrados en el expediente.';
      $('[data-doc-all]').hidden=!encounterRows.length;
      const items=readerMode==='encounter'?encounterRows:logicalDocuments(rows);
      if(!items.length)patientList.textContent='Aún no hay documentos registrados.';
      else items.forEach(row=>patientList.append(card(row)));
      $('[data-doc-reader] h4').textContent=readerMode==='encounter'?'Documentos de esta consulta':'Documentos del paciente';
      const open = context?.status === 'open';
      if (context?.status === 'voided') { selectedReplacement = null; show(replaceForm, false); }
      const targets={upload:uploadDialog,capture:captureDialog};
      panel.querySelectorAll('[data-doc-tool]').forEach(button=>{button.setAttribute('aria-expanded',String(button.dataset.docTool==='result'?!!window.mxmedLinkedResultComposer?.isOpen():targets[button.dataset.docTool].open));button.disabled=busy||context?.status==='voided'||(button.dataset.docTool!=='result'&&!open);});
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
      status(kind === 'upload' || kind === 'replace' ? 'Subiendo archivo… aún no está guardado.' : 'Creando orden…', 'uploading');
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
        const controls = [...dialog.querySelectorAll('button,input,select,textarea,a[href],[tabindex]')].filter(x => !x.matches(':disabled') && x.tabIndex >= 0 && x.getClientRects().length);
        if(!controls.length)return;
        // Safari may omit buttons from its native Tab order. Keep every move in
        // the modal's visible control order, including its actions.
        const index=controls.indexOf(document.activeElement);
        const next=e.shiftKey?(index<=0?controls.length-1:index-1):(index<0||index===controls.length-1?0:index+1);
        e.preventDefault();controls[next].focus();
      });
    }
    function closeReader(){readerDialog.close();readerDialog.classList.remove('plan02b-modal');}
    modalShell(readerDialog,()=>{closeReader();returnFocus(readerTrigger);});
    $('[data-doc-reader-close]').onclick=()=>{closeReader();returnFocus(readerTrigger);};
    function readDocuments(mode,trigger){if(busy||session)return;readerMode=mode;readerTrigger=trigger;paint();readerDialog.classList.add('plan02b-modal');readerDialog.showModal();readerDialog.querySelector('[data-modal-close]').focus();}
    $('[data-doc-all]').onclick=e=>readDocuments('encounter',e.currentTarget);
    $('[data-doc-patient]').onclick=e=>readDocuments('patient',e.currentTarget);
    const actionOpen=()=>uploadDialog.open||captureDialog.open||!!window.mxmedLinkedResultComposer?.isOpen();
    function meaningful(form){return [...form.querySelectorAll('input:not([type=hidden]),select')].some(n=>n.type==='file'?n.files?.length:!!n.value.trim());}
    function allowDiscard(form){return !meaningful(form)||window.confirm('¿Descartar los cambios de este modal? El documento no se ha guardado.');}
    panel.querySelector('[data-doc-tool="result"]').onclick=e=>{
      if(busy||captureBusy||actionOpen()||!available()||!window.mxmedLinkedResultComposer)return;
      const owner={...context};
      const orders=rows.filter(row=>String(row.encounter_ref_id||row.encounter_id||'')===owner.encounterId && orderTypes.has(String(row.document_type))&&row.has_successor!=1&&row.status!=='voided');
      window.mxmedLinkedResultComposer.open({patientId:owner.patientId,doctorId:owner.doctorId,encounterId:owner.encounterId,
        orders,trigger:e.currentTarget,isCurrent:()=>sameContext()&&context?.key===owner.key&&context?.status!=='voided',onSaved:refresh});
    };
    window.addEventListener('mxmed:review-document',async event=>{
      if(event.detail?.encounterKey!==context?.key)return;
      const row=event.detail.document;
      if(row.has_private_binary==1){privateRead(row);return;}
      document.querySelector('.m7-workspace-sections [data-m7-section="documents"]').click();
      await refresh();
      if(event.detail.encounterKey!==context?.key)return;
      readDocuments('encounter',$('[data-doc-patient]'));
      [...patientList.children].find(card=>card.dataset.document===row.document_uuid)?.scrollIntoView({block:'nearest'});
    });
    function uploadMessage(text, error = false) { const node = $('[data-docux-upload-state]'); node.textContent = text; node.dataset.error = String(error); }
    function fileState() {
      const input=$('[data-m7-doc-file]');
      $('[data-docux-file-state]').textContent=input.files?.[0]?.name||'Arrastra un PDF o imagen aquí';
      $('[data-docux-pick]').textContent=input.files?.length?'Cambiar archivo':'Seleccionar archivo';
      delete $('[data-docux-dropzone]').dataset.drag;
    }
    function closeUpload(force = false,discard=false) {
      if (!force&&(busy||(!discard&&!allowDiscard(uploadForm)))) return;
      uploadDialog.close(); uploadDialog.classList.remove('plan02b-modal'); uploadForm.reset(); resetAttempt('upload'); fileState(); uploadMessage('');
      paint();
      if (!force) returnFocus(uploadTrigger);
    }
    modalShell(uploadDialog, () => closeUpload());
    uploadDialog.querySelector('[data-modal-cancel]').addEventListener('click', () => closeUpload(false,true));
    $('[data-docux-attach]').addEventListener('click', e => {
      if (busy || captureBusy || actionOpen() || !available() || context.status !== 'open') return;
      uploadTrigger = e.currentTarget; uploadMessage(''); fileState(); uploadDialog.classList.add('plan02b-modal'); uploadDialog.showModal();paint(); $('[data-m7-doc-title]').focus();
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
      finally { busy = false; controls.forEach(x => x.disabled = false);paint(); }
    });
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
    function currentSession(s) { return session===s && captureDialog.open && sameContext() && context.key===s.context.key; }
    function stopPolling(s) { if(s){clearTimeout(s.timer);s.timer=null;} }
    function captureMessage(text) { $('[data-m7-capture-state]').textContent=text; }
    function removeQR() { $('[data-docux-qr-content]').hidden=true;$('[data-docux-qr]').replaceChildren();$('[data-m7-capture-link]').removeAttribute('href'); }
    function drawQR(s,data) {
      s.token=data.token;s.url=data.mobile_url;s.qrPageUuid=data.page_uuid;removeQR();
      new QRCode($('[data-docux-qr]'),{text:s.url,width:216,height:216,colorDark:'#000000',colorLight:'#ffffff',correctLevel:QRCode.CorrectLevel.M});
      $('[data-docux-qr]').removeAttribute('title');$('[data-docux-qr-content]').hidden=false;$('[data-docux-capture-guide]').hidden=false;
      $('[data-m7-capture-link]').href=s.url;$('[data-docux-capture-expiry]').textContent='Código de un solo uso · página '+data.page_number;
    }
    async function renderCapture(s,data) {
      if(!currentSession(s))return;
      s.status=data.status;s.pending=data.status==='OPEN';s.data=data;
      const pageList=$('[data-docux-capture-pages]');
      const signature=JSON.stringify([data.status,data.pages]);
      if(s.pageSignature!==signature){
        s.pageSignature=signature;pageList.replaceChildren();
        for(const page of data.pages||[]){
          const row=document.createElement('article'),img=document.createElement('img'),label=document.createElement('strong');
          img.src=page.thumbnail_url;img.alt='Página '+page.page_number;label.textContent='Página '+page.page_number;row.append(img,label);
          const index=data.pages.indexOf(page);
          for(const [text,offset] of [['Subir',-1],['Bajar',1]]){
            const button=document.createElement('button');button.type='button';button.className='btn btn-link';button.textContent=text;
            button.hidden=!s.pending||index+offset<0||index+offset>=data.pages.length;
            button.onclick=()=>captureCommand(s,'reorder',{pages:data.pages.map(p=>p.page_uuid).map((id,i)=>i===index?data.pages[index+offset].page_uuid:i===index+offset?page.page_uuid:id)});row.append(button);
          }
          const remove=document.createElement('button');remove.type='button';remove.className='btn btn-link';remove.textContent='Quitar';remove.hidden=!s.pending;remove.onclick=()=>captureCommand(s,'remove',{page_uuid:page.page_uuid});row.append(remove);pageList.append(row);
        }
      }
      pageList.hidden=!data.page_count;
      $('[data-docux-capture-finalize]').hidden=!s.pending||!data.page_count;
      $('[data-docux-capture-renew]').hidden=!s.pending;
      $('[data-m7-capture-cancel]').hidden=!s.pending;
      if(data.pages?.some(page=>page.page_uuid===s.qrPageUuid)){removeQR();$('[data-docux-capture-guide]').hidden=true;}
      if(data.status==='COMPLETED'){
        stopPolling(s);removeQR();$('[data-docux-capture-guide]').hidden=true;$('[data-docux-capture-change]').hidden=true;
        captureMessage('Documento recibido · '+data.page_count+' páginas');
        if(!s.refreshed){s.refreshed=true;await refresh();}
      }else if(!s.pending){stopPolling(s);removeQR();captureMessage(data.status==='EXPIRED'?'La sesión de captura venció.':'Captura cancelada.');}
      else captureMessage(data.page_count?data.page_count+' páginas recibidas. Puedes ordenar, quitar o finalizar el documento.':'Esperando la primera página…');
    }
    async function captureCommand(s,action,body={}){
      if(!currentSession(s)||captureBusy||!s.id)return;
      captureBusy=true;stopPolling(s);
      try {const result=await send('mobile-capture-sessions/'+s.id+'/'+action,body,makeKey());await renderCapture(s,result.data);}
      catch(_){if(currentSession(s))captureMessage('No se completó la acción. Actualiza el estado e intenta de nuevo.');}
      finally{captureBusy=false;schedule(s);}
    }
    function schedule(s){stopPolling(s);if(currentSession(s)&&s.pending&&!s.closing&&!document.hidden)s.timer=setTimeout(()=>poll(s),pollInterval);}
    async function poll(s){
      if(!currentSession(s)||!s.id||s.closing)return;
      try{await renderCapture(s,await get('mobile-capture-sessions/'+s.id));}
      catch(_){if(currentSession(s))captureMessage('No se pudo comprobar la captura. Volveremos a intentarlo.');}
      finally{schedule(s);}
    }
    async function endCapture(close=true,contextLost=false){
      const s=session;if(!s||s.closing)return;
      s.closing=true;stopPolling(s);
      try{
        await s.issuance;
        if(s.id&&s.pending){
          let data;
          try{data=(await send('mobile-capture-sessions/'+s.id+'/cancel',{},makeKey())).data;}
          catch(error){data=await get('mobile-capture-sessions/'+s.id);if(data.status==='OPEN')throw error;}
          s.pending=false;await renderCapture(s,data);
        }
        if(close){captureDialog.close();captureDialog.classList.remove('plan02b-modal');session=null;removeQR();paint();if(!contextLost)returnFocus($('[data-m7-capture-start]'));}
      }catch(_){if(currentSession(s))captureMessage('No se pudo cancelar. Intenta de nuevo antes de cerrar.');}
      finally{s.closing=false;schedule(s);}
    }
    async function generateCapture(s){
      if(!currentSession(s)||captureBusy||!s.classification)return;
      captureBusy=true;stopPolling(s);captureMessage('Preparando el código…');
      s.issuance=(async()=>{
        try{
          if(!s.id){const created=await send('mobile-capture-sessions',{patient_id:s.context.patientId,encounter_key:s.context.key,classification_key:s.classification.id,title:$('[data-docux-capture-title-input]').value.trim()},makeKey());s.id=created.data.session_uuid;s.pending=true;}
          const response=await send('mobile-capture-sessions/'+s.id+'/pages',{},makeKey());
          if(!currentSession(s)||s.closing)return;
          await renderCapture(s,response.data);drawQR(s,response.data);
          $('[data-docux-capture-selection]').hidden=true;$('[data-docux-capture-selected]').hidden=false;
          $('[data-docux-capture-label]').textContent=s.classification.label;$('[data-docux-capture-change]').hidden=false;
          $('[data-docux-capture-generate]').hidden=true;captureMessage('Escanea el código para enviar una página.');
        }catch(_){if(currentSession(s))captureMessage('No se pudo preparar el código. Intenta de nuevo.');}
        finally{captureBusy=false;schedule(s);}
      })();await s.issuance;
    }
    modalShell(captureDialog,()=>endCapture());
    $('[data-docux-capture-close]').onclick=()=>endCapture();$('[data-m7-capture-cancel]').onclick=()=>endCapture(false);
    $('[data-docux-capture-finalize]').onclick=()=>captureCommand(session,'finalize');
    $('[data-docux-capture-generate]').onclick=()=>generateCapture(session);$('[data-docux-capture-renew]').onclick=()=>generateCapture(session);
    $('[data-docux-capture-change]').onclick=async()=>{const s=session;await endCapture(false);if(currentSession(s)&&!s.pending){s.id='';s.pageSignature='';$('[data-docux-capture-pages]').replaceChildren();$('[data-docux-capture-selection]').hidden=false;$('[data-docux-capture-selected]').hidden=true;$('[data-docux-capture-generate]').hidden=false;$('[data-docux-capture-guide]').hidden=true;}};
    $('[data-docux-copy]').onclick=async()=>{try{if(session?.url)await navigator.clipboard.writeText(session.url);}catch(_){captureMessage('Usa Abrir enlace para acceder al código.');}};
    $('[data-m7-capture-start]').addEventListener('click',async()=>{
      if(busy||actionOpen()||!sameContext()||context.status!=='open'||captureBusy||session)return;
      const s={context:{...context},id:'',classification:null,pending:false,closing:false,timer:null};session=s;
      captureDialog.classList.add('plan02b-modal');captureDialog.showModal();removeQR();
      $('[data-docux-capture-selection]').hidden=false;$('[data-docux-capture-selected]').hidden=true;
      $('[data-docux-capture-guide]').hidden=true;$('[data-docux-received]').hidden=true;$('[data-docux-capture-pages]').replaceChildren();
      for(const selector of ['[data-m7-capture-cancel]','[data-docux-capture-renew]','[data-docux-capture-finalize]'])$(selector).hidden=true;
      $('[data-docux-capture-title-input]').value='';$('[data-docux-capture-generate]').hidden=false;$('[data-docux-capture-generate]').disabled=true;
      $('[data-docux-capture-options]').replaceChildren();captureMessage('Selecciona el tipo de documento antes de generar el código.');paint();
      try{
        const catalog=await get('mobile-capture-sessions/classifications');if(!currentSession(s))return;
        for(const item of catalog.items||[]){const label=document.createElement('label'),input=document.createElement('input'),text=document.createElement('span');
          input.type='radio';input.name='capture-classification';input.value=item.id;input.required=true;text.textContent=item.label;label.append(input,text);$('[data-docux-capture-options]').append(label);
          input.onchange=()=>{if(currentSession(s)&&input.checked){s.classification=item;$('[data-docux-capture-generate]').disabled=false;}};
        }
        $('[data-docux-capture-options] input')?.focus();
      }catch(_){captureMessage('No se pudieron cargar los tipos de documento.');}
    });
    document.addEventListener('visibilitychange',()=>{if(session)schedule(session);});
    window.addEventListener('pagehide',()=>{const s=session;stopPolling(s);if(s?.id&&s.pending)fetch(route('mobile-capture-sessions/'+s.id+'/cancel'),{method:'POST',credentials:'same-origin',keepalive:true,headers:{'Content-Type':'application/json'},body:'{}'}).catch(()=>{});});
    const body = root.querySelector('[data-m7-body]');
    new MutationObserver(() => {
      if (session && (body.dataset.encounterKey !== session.context.key || body.dataset.encounterState !== 'open' || !sameContext() || !root.getClientRects().length)) endCapture(true, true);
    }).observe(root.closest('#p-expediente') || root, { attributes:true, subtree:true, attributeFilter:['data-patient-id','data-active-patient-id','data-encounter-key','data-encounter-state','class'] });
    return {
      select(selected) { show(panel, selected); if (!selected && session) endCapture(true, true); if (selected && context) refresh(); },
      isDirty() { return !!($('[data-m7-doc-title]').value.trim() || $('[data-m7-doc-file]').files?.length || $('[data-m7-replace-reason]').value.trim() || $('[data-m7-replace-file]').files?.length); },
      isBusy() { return busy || captureBusy || !!window.mxmedLinkedResultComposer?.isOpen(); },
      async leaveView() {
        if (busy) return false;
        if (session) await endCapture();
        if (session || captureBusy) return false;
        if(uploadDialog.open){closeUpload();if(uploadDialog.open)return false;}if(window.mxmedLinkedResultComposer?.isOpen())return false;closeReader();return true;
      },
      load(encounter) { const key = String(encounter.encounter_key || ''); const patientId = String(encounter.patient_id || ''); if (!key || !patientId) return; const changed = context?.key !== key || context?.patientId !== patientId; context = { key, patientId, doctorId:String(encounter.doctor_id || ''), encounterId:String(encounter.encounter_id || ''), status:String(encounter.status || '').toLowerCase(), closedAt:String(encounter.closed_at || '') }; if (changed) { epoch++;closeReader();window.mxmedLinkedResultComposer?.close(true); if (session) endCapture(true, true); closeUpload(true); rows = []; selectedReplacement = null; attempts.clear(); [uploadForm, replaceForm].forEach(form => form.reset()); show(replaceForm, false);  } paint(); },
      reset() { epoch++;closeReader();window.mxmedLinkedResultComposer?.close(true); if (session) endCapture(true, true); closeUpload(true); context = null; rows = []; selectedReplacement = null; attempts.clear(); [uploadForm, replaceForm].forEach(form => form.reset()); show(panel, false); show(replaceForm, false);  }
    };
  };
})();
