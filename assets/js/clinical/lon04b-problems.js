// LON04B: explicit patient-level problem actions over the gated LON04A API.
(function () {
  const pane = document.getElementById('p-expediente');
  const root = document.getElementById('lon04b-problems');
  if (!pane || !root) return;
  const $ = selector => root.querySelector(selector);
  const show = (node, visible) => node.classList.toggle('d-none', !visible);
  const patient = () => String(pane.dataset.patientId || pane.dataset.activePatientId || '').trim();
  const endpoint = (suffix = '') => `/api/clinical/index.php/patients/${encodeURIComponent(patientId)}/longitudinal/problems${suffix}`;
  const statusName = {ACTIVE:'Activo',INACTIVE:'Inactivo',RESOLVED:'Resuelto'};
  const provenanceName = {EXPLICIT_LONGITUDINAL_ENTRY:'Registrado por el profesional',PATIENT_REPORTED:'Reportado por el paciente',ENCOUNTER_DERIVED_EXPLICIT_PROMOTION:'Promovido explícitamente desde una consulta'};
  const operationName = {CREATE:'Creado',UPDATE:'Corregido',MARK_INACTIVE:'Marcado inactivo',ACTIVATE:'Activado',RESOLVE:'Resuelto',REACTIVATE:'Reactivado',EXPLICIT_PROMOTION_FROM_ENCOUNTER:'Promovido desde consulta'};
  const dialog = $('[data-lon04b-dialog]');
  const form = $('[data-lon04b-form]');
  const status = $('[data-lon04b-status]');
  const error = $('[data-lon04b-error]');
  const groups = $('[data-lon04b-groups]');
  let patientId = '';
  let epoch = 0;
  let items = [];
  let editing = null;
  let returnFocus = null;
  let conflictBlocked = false;

  async function request(suffix = '', method = 'GET', body = null, key = '') {
    let response;
    try {
      response = await fetch(endpoint(suffix), {method,credentials:'same-origin',headers:{Accept:'application/json',...(body ? {'Content-Type':'application/json'} : {}),...(key ? {'Idempotency-Key':key} : {})},body:body ? JSON.stringify(body) : undefined});
    } catch (_) {throw {code:'NETWORK_ERROR',message:'No hay conexión. Tu borrador se conserva.'};}
    const result = await response.json().catch(() => null);
    if (!response.ok || result?.ok !== true) {
      const code = typeof result?.error === 'string' ? result.error : result?.error?.code || 'REQUEST_FAILED';
      const message = {
        STALE_VERSION:'El problema cambió en otra sesión. Tu borrador se conserva.',
        IDEMPOTENCY_PAYLOAD_CONFLICT:'Esta solicitud ya se usó con otro contenido. Cierra y abre una acción nueva.',
        M6_WRITE_WINDOW_BLOCKED:'Las escrituras clínicas están pausadas. Tu borrador se conserva.',
        LON04A_WRITE_DISABLED:'La edición de problemas no está habilitada en este entorno. Tu borrador se conserva.',
        FOREIGN_SOURCE:'La valoración no pertenece al contexto autorizado o no contiene evidencia guardada.',
        INVALID_TRANSITION:'El estado cambió o esta transición ya no corresponde.',
        NOT_FOUND:'Paciente o problema fuera de tu ámbito autorizado.'
      }[code] || 'No se pudo completar la acción.';
      throw {code,message};
    }
    return result.data;
  }
  function button(label, callback, className='btn btn-outline-primary btn-sm') {
    const node=document.createElement('button');node.type='button';node.className=className;node.textContent=label;node.addEventListener('click',callback);return node;
  }
  function datum(value, fallback='No disponible') {return String(value ?? '').trim() || fallback;}
  function renderItem(item, target) {
    const card=document.createElement('article');card.className='lon04b-item';
    const title=document.createElement('h5');title.textContent=item.label;
    const details=document.createElement('p');details.className='lon04b-meta';
    details.textContent=`${provenanceName[item.provenance] || 'Origen no establecido'} · Actualizado ${datum(item.updated_at)}${item.onset_date ? ` · Inicio ${item.onset_date}` : ''}`;
    const actions=document.createElement('div');actions.className='lon04b-actions';
    actions.append(button('Editar', event => open('UPDATE',item,event.currentTarget)));
    if (item.status==='ACTIVE') {
      actions.append(button('Marcar inactivo', event => open('MARK_INACTIVE',item,event.currentTarget)));
      actions.append(button('Resolver', event => open('RESOLVE',item,event.currentTarget)));
    } else if (item.status==='INACTIVE') {
      actions.append(button('Activar', event => open('ACTIVATE',item,event.currentTarget)));
      actions.append(button('Resolver', event => open('RESOLVE',item,event.currentTarget)));
    } else if (item.status==='RESOLVED') actions.append(button('Reactivar', event => open('REACTIVATE',item,event.currentTarget)));
    const history=document.createElement('div');history.className='lon04b-history d-none';
    actions.append(button('Ver historial',async () => {
      if (!history.classList.contains('d-none')) {show(history,false);return;}
      history.textContent='Cargando historial…';show(history,true);
      try {
        const data=await request(`/${item.problem_id}/history`);
        history.replaceChildren();
        (data.events || []).forEach(event => {
          const entry=document.createElement('p');
          entry.textContent=`${datum(event.occurred_at)} · ${operationName[event.operation] || event.operation}${event.reason ? ` · ${event.reason}` : ''}`;
          history.append(entry);
        });
      } catch (failure) {history.textContent=failure.message;}
    },'btn btn-link btn-sm'));
    card.append(title,details,actions,history);target.append(card);
  }
  function render() {
    for (const [state,selector] of [['ACTIVE','[data-lon04b-active]'],['INACTIVE','[data-lon04b-inactive]'],['RESOLVED','[data-lon04b-resolved]']]) {
      const target=$(selector);target.replaceChildren();
      const matching=items.filter(item => item.status===state);
      if (matching.length) matching.forEach(item => renderItem(item,target));
      else {const empty=document.createElement('p');empty.className='lon04b-empty';empty.textContent=state==='ACTIVE' ? 'No hay problemas activos registrados. Esto no confirma ausencia clínica.' : `No hay problemas ${state==='INACTIVE'?'inactivos':'resueltos'} registrados.`;target.append(empty);}
    }
    show(groups,true);
  }
  async function load() {
    const id=patient();
    if (dialog.open && editing && editing.patientId!==id) dialog.close();
    patientId=id;const seen=++epoch;
    show(groups,false);show(error,false);
    if (!id) {status.textContent='Selecciona un paciente para ver sus problemas.';return;}
    status.textContent='Cargando problemas longitudinales…';
    try {
      const data=await request();
      if (seen!==epoch || patient()!==id) return;
      items=data.items || [];render();status.textContent='Problemas longitudinales actualizados.';
    } catch (failure) {
      if (seen!==epoch) return;
      error.textContent=failure.message;show(error,true);status.textContent='No se pudieron cargar los problemas.';
    }
  }
  function reset(row) {
    $('[data-lon04b-label]').value=row?.label || '';
    $('[data-lon04b-code-system]').value=row?.code_system || '';
    $('[data-lon04b-code-value]').value=row?.code_value || '';
    $('[data-lon04b-onset]').value=row?.onset_date || '';
    $('[data-lon04b-provenance]').value='EXPLICIT_LONGITUDINAL_ENTRY';
    $('[data-lon04b-reason]').value='';
    show($('[data-lon04b-conflict]'),false);show($('[data-lon04b-form-error]'),false);
    conflictBlocked=false;$('[data-lon04b-save]').disabled=false;
  }
  function open(operation,row,trigger,source=null) {
    if (!patientId || patientId!==patient()) return;
    editing={patientId,operation,row,source,key:crypto.randomUUID()};returnFocus=trigger;
    reset(row);
    show($('[data-lon04b-identity]'),['CREATE','UPDATE','EXPLICIT_PROMOTION_FROM_ENCOUNTER'].includes(operation));
    show($('[data-lon04b-provenance-wrap]'),operation==='CREATE');
    show($('[data-lon04b-reason-wrap]'),!['CREATE','EXPLICIT_PROMOTION_FROM_ENCOUNTER'].includes(operation));
    $('[data-lon04b-dialog-title]').textContent=({CREATE:'Agregar problema',UPDATE:'Editar problema',EXPLICIT_PROMOTION_FROM_ENCOUNTER:'Promover valoración a problema',MARK_INACTIVE:'Marcar problema inactivo',RESOLVE:'Resolver problema',ACTIVATE:'Activar problema',REACTIVATE:'Reactivar problema'})[operation];
    $('[data-lon04b-context]').textContent=source?.context || (row ? `${row.label} · Estado actual: ${statusName[row.status]}` : 'Nuevo problema longitudinal');
    dialog.showModal();
    (['CREATE','UPDATE','EXPLICIT_PROMOTION_FROM_ENCOUNTER'].includes(operation) ? $('[data-lon04b-label]') : $('[data-lon04b-reason]')).focus();
  }
  async function save(event) {
    event.preventDefault();
    if (!editing || editing.patientId!==patient() || conflictBlocked) return;
    const {operation,row,source,key}=editing;
    const errorNode=$('[data-lon04b-form-error]');
    let body={};let suffix='';let method='POST';
    if (['CREATE','UPDATE','EXPLICIT_PROMOTION_FROM_ENCOUNTER'].includes(operation)) {
      body.label=$('[data-lon04b-label]').value.trim();
      const system=$('[data-lon04b-code-system]').value.trim(),value=$('[data-lon04b-code-value]').value.trim();
      if (system || value) {body.code_system=system;body.code_value=value;}
      const onset=$('[data-lon04b-onset]').value;if(onset) body.onset_date=onset;
      if (!body.label || (!!system !== !!value)) {errorNode.textContent='Escribe el problema y, si usas código, completa sistema y valor.';show(errorNode,true);return;}
    }
    if (operation==='CREATE') body.provenance=$('[data-lon04b-provenance]').value;
    else if (operation==='EXPLICIT_PROMOTION_FROM_ENCOUNTER') {body.source_encounter_id=source.encounterId;body.source_section_id=source.sectionId;suffix='/promotions';}
    else {
      body.expected_version=Number(row.row_version);
      body.reason=$('[data-lon04b-reason]').value.trim();
      if (!body.reason) {errorNode.textContent='Indica un motivo clínico para esta acción.';show(errorNode,true);return;}
      suffix=`/${row.problem_id}${operation==='UPDATE'?'':`/${({MARK_INACTIVE:'mark-inactive',RESOLVE:'resolve',ACTIVATE:'activate',REACTIVATE:'reactivate'})[operation]}`}`;
      if (operation==='UPDATE') method='PATCH';
    }
    if (['RESOLVE','MARK_INACTIVE','ACTIVATE','REACTIVATE'].includes(operation) && !window.confirm(`Confirma la decisión clínica: ${$('[data-lon04b-dialog-title]').textContent}.`)) return;
    const saveButton=$('[data-lon04b-save]');saveButton.disabled=true;show(errorNode,false);
    try {await request(suffix,method,body,key);dialog.close();await load();}
    catch (failure) {
      errorNode.textContent=failure.message;show(errorNode,true);
      if (failure.code==='STALE_VERSION' || failure.code==='INVALID_TRANSITION') {
        conflictBlocked=true;show($('[data-lon04b-conflict]'),true);
        try {const latest=await request(`/${row.problem_id}`);$('[data-lon04b-server-state]').textContent=`Servidor: ${latest.item.label} · ${statusName[latest.item.status]} · versión ${latest.item.row_version}. Tu borrador permanece sin cambios.`;}
        catch (_) {$('[data-lon04b-server-state]').textContent='No se pudo consultar el estado actual. Tu borrador permanece sin cambios.';}
      }
      errorNode.focus();
    } finally {saveButton.disabled=conflictBlocked;}
  }
  $('[data-lon04b-add]').addEventListener('click',event => open('CREATE',null,event.currentTarget));
  $('[data-lon04b-refresh]').addEventListener('click',load);
  $('[data-lon04b-cancel]').addEventListener('click',() => dialog.close());
  $('[data-lon04b-reload]').addEventListener('click',async () => {
    if (!editing?.row) return;
    try {
      const fresh=(await request(`/${editing.row.problem_id}`)).item;
      editing={...editing,row:fresh,key:crypto.randomUUID()};reset(fresh);
      $('[data-lon04b-context]').textContent=`${fresh.label} · Estado actual: ${statusName[fresh.status]}`;
      const operation=editing.operation;
      const stillAllowed=operation==='UPDATE' ||
        (operation==='RESOLVE' && ['ACTIVE','INACTIVE'].includes(fresh.status)) ||
        (operation==='MARK_INACTIVE' && fresh.status==='ACTIVE') ||
        (operation==='ACTIVATE' && fresh.status==='INACTIVE') ||
        (operation==='REACTIVATE' && fresh.status==='RESOLVED');
      if (!stillAllowed) {
        const target=$('[data-lon04b-form-error]');
        target.textContent='Esta acción ya no corresponde al estado actual. Cierra el formulario y elige una acción nueva.';
        show(target,true);conflictBlocked=true;$('[data-lon04b-save]').disabled=true;
      }
    }
    catch (failure) {const target=$('[data-lon04b-form-error]');target.textContent=failure.message;show(target,true);}
  });
  dialog.addEventListener('close',() => {editing=null;(returnFocus?.isConnected && returnFocus.getClientRects().length ? returnFocus : $('#lon04b-title')).focus();returnFocus=null;});
  form.addEventListener('submit',save);
  const promote=pane.querySelector('[data-lon04b-m7-promote]');
  promote?.addEventListener('click',event => {
    const buttonNode=event.currentTarget;
    const encounterId=Number(buttonNode.dataset.encounterId),sectionId=Number(buttonNode.dataset.sectionId);
    const m7=pane.querySelector('#m7-workspace');
    const context=String(m7?.querySelector('[data-m7-context]')?.textContent || '').trim();
    const excerpt=String(m7?.querySelector('[data-m7-editor-text]')?.value || '').trim();
    if (!Number.isSafeInteger(encounterId) || encounterId<1 || !Number.isSafeInteger(sectionId) || sectionId<1 || !excerpt) return;
    const tab=pane.querySelector('[data-bs-target="#t-problemas-longitudinal"]');
    if (tab && window.bootstrap?.Tab) window.bootstrap.Tab.getOrCreateInstance(tab).show();
    open('EXPLICIT_PROMOTION_FROM_ENCOUNTER',null,buttonNode,{encounterId,sectionId,context:`${context} · Valoración guardada: ${excerpt.slice(0,180)}`});
  });
  for (const name of ['patient:selected','expediente:patient_changed','expediente:patient-changed']) window.addEventListener(name,load);
  pane.querySelector('[data-bs-target="#t-problemas-longitudinal"]')?.addEventListener('shown.bs.tab',load);
  new MutationObserver(() => {if (patient()!==patientId) load();}).observe(pane,{attributes:true,attributeFilter:['data-patient-id','data-active-patient-id']});
  if (patient()) load();
})();
