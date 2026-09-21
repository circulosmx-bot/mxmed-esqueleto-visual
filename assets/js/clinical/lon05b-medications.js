// LON05B: explicit patient-level medication actions over the gated LON05A API.
(function () {
  const pane=document.getElementById('p-expediente');
  const root=document.getElementById('lon05b-medications');
  if(!pane||!root)return;
  const $=selector=>root.querySelector(selector);
  const show=(node,visible)=>node.classList.toggle('d-none',!visible);
  const patient=()=>String(pane.dataset.patientId||pane.dataset.activePatientId||'').trim();
  const stateName={ACTIVE_CONFIRMED:'Confirmado como actual',REPORTED_BY_PATIENT:'Referido por el paciente',PRESCRIBED_NOT_CONFIRMED_ACTIVE:'Prescrito, uso actual no confirmado',DISCONTINUED:'Suspendido',COMPLETED:'Tratamiento completado'};
  const provenanceName={EXPLICIT_LONGITUDINAL_ENTRY:'Registro explícito del profesional',PATIENT_REPORTED:'Referido por el paciente',PRESCRIPTION_DERIVED_EXPLICIT_ENTRY:'Agregado explícitamente desde receta',ENCOUNTER_DERIVED_EXPLICIT_ENTRY:'Registrado explícitamente desde consulta'};
  const operationName={CREATE:'Registro explícito',PATIENT_REPORTED_CREATE:'Referido por paciente',PRESCRIPTION_DERIVED_CREATE:'Agregado desde receta',CONFIRM_ACTIVE:'Confirmación de uso actual',UPDATE_REGIMEN:'Cambio de régimen',DISCONTINUE:'Suspensión',COMPLETE:'Tratamiento completado',RECONCILIATION_DECISION:'Decisión de conciliación'};
  const dialog=$('[data-lon05b-dialog]'),reconcileDialog=$('[data-lon05b-reconcile-dialog]');
  const status=$('[data-lon05b-status]'),error=$('[data-lon05b-error]'),groups=$('[data-lon05b-groups]');
  let patientId='',epoch=0,items=[],listVersion=1,editing=null,reconciling=null,returnFocus=null,conflictBlocked=false,reconcileBlocked=false;
  const endpoint=(resource,suffix='')=>`/api/clinical/index.php/patients/${encodeURIComponent(patientId)}/longitudinal/${resource}${suffix}`;
  async function request(resource,suffix='',method='GET',body=null,key='') {
    let response;
    try {response=await fetch(endpoint(resource,suffix),{method,credentials:'same-origin',headers:{Accept:'application/json',...(body?{'Content-Type':'application/json'}:{}),...(key?{'Idempotency-Key':key}:{})},body:body?JSON.stringify(body):undefined});}
    catch(_){throw {code:'NETWORK_ERROR',message:'Sin conexión. Tu borrador permanece.'};}
    const result=await response.json().catch(()=>null);
    if(!response.ok||result?.ok!==true){
      const code=typeof result?.error==='string'?result.error:result?.error?.code||'REQUEST_FAILED';
      const message={STALE_VERSION:'El episodio cambió en otra sesión. Tu borrador permanece.',STALE_LIST_VERSION:'La lista cambió en otra sesión. Tus decisiones permanecen.',IDEMPOTENCY_PAYLOAD_CONFLICT:'Esta solicitud ya se usó con otro contenido. Cierra y abre una acción nueva.',M6_WRITE_WINDOW_BLOCKED:'Las escrituras clínicas están pausadas. Tu borrador permanece.',LON05A_WRITE_DISABLED:'La edición de medicamentos no está habilitada aquí.',TERMINAL_EPISODE:'Este episodio terminó. Registra un episodio nuevo si vuelve a usarse.',FOREIGN_SOURCE:'La receta no pertenece al paciente y profesional autorizados o no está generada.',NOT_FOUND:'Paciente, episodio o receta fuera de tu ámbito autorizado.'}[code]||'No se pudo completar la acción.';
      throw {code,message};
    }
    return result.data;
  }
  function button(label,callback,css='btn btn-outline-primary btn-sm') {const node=document.createElement('button');node.type='button';node.className=css;node.textContent=label;node.addEventListener('click',callback);return node;}
  function datum(value){return String(value??'').trim();}
  function renderItem(item,target){
    const card=document.createElement('article');card.className='lon05b-item';
    const title=document.createElement('h5');title.textContent=item.medication_name;
    const meta=document.createElement('p');meta.className='lon05b-meta';
    const regimen=[item.dose,item.dose_unit,item.route,item.frequency].map(datum).filter(Boolean).join(' · ');
    meta.textContent=`${provenanceName[item.provenance]||'Origen no establecido'}${regimen?` · ${regimen}`:''}${item.source_document_id?' · Receta vinculada':''}${item.ended_at?` · Terminó ${item.ended_at}`:''}`;
    const actions=document.createElement('div');actions.className='lon05b-actions';
    if(['ACTIVE_CONFIRMED','REPORTED_BY_PATIENT','PRESCRIBED_NOT_CONFIRMED_ACTIVE'].includes(item.state)){
      actions.append(button('Editar régimen',event=>open('UPDATE_REGIMEN',item,event.currentTarget)));
      if(item.state!=='ACTIVE_CONFIRMED')actions.append(button('Confirmar como actual',event=>open('CONFIRM_ACTIVE',item,event.currentTarget)));
      actions.append(button('Suspender',event=>open('DISCONTINUE',item,event.currentTarget)));
      if(item.state!=='REPORTED_BY_PATIENT')actions.append(button('Completar tratamiento',event=>open('COMPLETE',item,event.currentTarget)));
    }else actions.append(button('Registrar nuevo episodio',event=>open('CREATE',item,event.currentTarget)));
    const history=document.createElement('div');history.className='lon05b-history d-none';
    actions.append(button('Ver historial',async()=>{
      if(!history.classList.contains('d-none')){show(history,false);return;}
      const sourcePatient=patientId;history.textContent='Cargando historial…';show(history,true);
      try{const data=await request('medications',`/${item.medication_id}/history`);if(patient()!==sourcePatient)return;history.replaceChildren();(data.events||[]).forEach(entry=>{const p=document.createElement('p');p.textContent=`${datum(entry.occurred_at)} · ${operationName[entry.operation]||entry.operation}${entry.reason?` · ${entry.reason}`:''}`;history.append(p);});}
      catch(failure){history.textContent=failure.message;}
    },'btn btn-link btn-sm'));
    card.append(title,meta,actions,history);target.append(card);
  }
  function render(){
    for(const state of Object.keys(stateName)){
      const target=$(`[data-lon05b-${state.toLowerCase().replaceAll('_','-')}]`);target.replaceChildren();
      const matching=items.filter(item=>item.state===state);
      if(matching.length)matching.forEach(item=>renderItem(item,target));
      else{const empty=document.createElement('p');empty.className='lon05b-empty';empty.textContent=state==='ACTIVE_CONFIRMED'?'Sin medicación actual confirmada registrada. Esto no confirma ausencia de uso.':`No hay episodios en estado: ${stateName[state].toLowerCase()}.`;target.append(empty);}
    }
    show(groups,true);
  }
  async function load(){
    const id=patient();
    if(id!==patientId){if(dialog.open)dialog.close();if(reconcileDialog.open)reconcileDialog.close();$('[data-lon05b-reconciliation-history]').replaceChildren();show($('[data-lon05b-reconciliation-history]'),false);}
    patientId=id;const seen=++epoch;show(groups,false);show(error,false);
    if(!id){status.textContent='Selecciona un paciente para ver su medicación.';return;}
    status.textContent='Cargando medicación longitudinal…';
    try{const data=await request('medications');if(seen!==epoch||patient()!==id)return;items=data.items||[];listVersion=Number(data.list_version)||1;render();status.textContent='Medicación longitudinal actualizada.';}
    catch(failure){if(seen!==epoch)return;error.textContent=failure.message;show(error,true);status.textContent='No se pudo cargar la medicación.';}
  }
  function reset(row){
    for(const [name,key] of [['name','medication_name'],['dose','dose'],['unit','dose_unit'],['route','route'],['frequency','frequency']])$(`[data-lon05b-${name}]`).value=row?.[key]||'';
    $('[data-lon05b-started]').value=datum(row?.started_at).replace(' ','T').slice(0,16);
    $('[data-lon05b-reason]').value='';show($('[data-lon05b-conflict]'),false);show($('[data-lon05b-form-error]'),false);
    conflictBlocked=false;$('[data-lon05b-save]').disabled=false;
  }
  function open(operation,row,trigger,source=null){
    if(!patientId||patientId!==patient())return;
    editing={patientId,operation,row,source,key:crypto.randomUUID()};returnFocus=trigger;reset(row);
    const regimen=['CREATE','PATIENT_REPORTED_CREATE','PRESCRIPTION_DERIVED_CREATE','UPDATE_REGIMEN'].includes(operation);
    show($('[data-lon05b-regimen]'),regimen);show($('[data-lon05b-reason-wrap]'),!['CREATE','PATIENT_REPORTED_CREATE','PRESCRIPTION_DERIVED_CREATE'].includes(operation));
    $('[data-lon05b-dialog-title]').textContent=({CREATE:row?'Registrar nuevo episodio':'Agregar medicación confirmada',PATIENT_REPORTED_CREATE:'Agregar medicamento referido por paciente',PRESCRIPTION_DERIVED_CREATE:'Agregar receta como uso no confirmado',UPDATE_REGIMEN:'Editar régimen',CONFIRM_ACTIVE:'Confirmar medicación actual',DISCONTINUE:'Suspender medicamento',COMPLETE:'Completar tratamiento'})[operation];
    $('[data-lon05b-context]').textContent=source?.context||(row?`${row.medication_name} · ${stateName[row.state]}`:'Nuevo episodio longitudinal');
    dialog.showModal();(regimen?$('[data-lon05b-name]'):$('[data-lon05b-reason]')).focus();
  }
  function regimenBody(){const body={medication_name:$('[data-lon05b-name]').value.trim()};for(const [name,key] of [['dose','dose'],['unit','dose_unit'],['route','route'],['frequency','frequency']]){const value=$(`[data-lon05b-${name}]`).value.trim();if(value)body[key]=value;}const started=$('[data-lon05b-started]').value;if(started)body.started_at=started.replace('T',' ')+':00';return body;}
  async function save(event){
    event.preventDefault();if(!editing||editing.patientId!==patient()||conflictBlocked)return;
    const {operation,row,source,key}=editing;const errorNode=$('[data-lon05b-form-error]');let body={},suffix='',method='POST';
    if(['CREATE','PATIENT_REPORTED_CREATE','PRESCRIPTION_DERIVED_CREATE','UPDATE_REGIMEN'].includes(operation)){
      body=regimenBody();if(!body.medication_name){errorNode.textContent='Escribe el nombre del medicamento.';show(errorNode,true);return;}
    }
    if(operation==='PATIENT_REPORTED_CREATE')suffix='/patient-reported';
    else if(operation==='PRESCRIPTION_DERIVED_CREATE'){suffix='/from-prescription';body.source_document_id=source.documentId;body.initial_state='PRESCRIBED_NOT_CONFIRMED_ACTIVE';}
    else if(operation==='UPDATE_REGIMEN'){suffix=`/${row.medication_id}`;method='PATCH';}
    else if(!['CREATE'].includes(operation))suffix=`/${row.medication_id}/${({CONFIRM_ACTIVE:'confirm-active',DISCONTINUE:'discontinue',COMPLETE:'complete'})[operation]}`;
    if(row&&operation!=='CREATE'){body.expected_version=Number(row.row_version);body.reason=$('[data-lon05b-reason]').value.trim();if(!body.reason){errorNode.textContent='Indica un motivo clínico.';show(errorNode,true);return;}}
    if(['CONFIRM_ACTIVE','DISCONTINUE','COMPLETE'].includes(operation)&&!window.confirm(`Confirma la decisión clínica: ${$('[data-lon05b-dialog-title]').textContent}.`))return;
    const saveButton=$('[data-lon05b-save]');saveButton.disabled=true;show(errorNode,false);
    try{await request('medications',suffix,method,body,key);if(patient()!==editing?.patientId)return;dialog.close();await load();}
    catch(failure){errorNode.textContent=failure.message;show(errorNode,true);
      if(failure.code==='STALE_VERSION'||failure.code==='TERMINAL_EPISODE'){
        conflictBlocked=true;show($('[data-lon05b-conflict]'),true);
        try{const latest=await request('medications',`/${row.medication_id}`);$('[data-lon05b-server-state]').textContent=`Servidor: ${latest.item.medication_name} · ${stateName[latest.item.state]} · versión ${latest.item.row_version}. Tu borrador permanece sin cambios.`;}
        catch(_){$('[data-lon05b-server-state]').textContent='No se pudo leer el estado actual. Tu borrador permanece.';}
      }errorNode.focus();
    }finally{saveButton.disabled=conflictBlocked;}
  }
  function reconcileChoices(item){
    const choices=[['','Sin cambio']];
    if(item.state==='REPORTED_BY_PATIENT'||item.state==='PRESCRIBED_NOT_CONFIRMED_ACTIVE')choices.push(['CONFIRM_ACTIVE','Confirmar como actual'],['KEEP_UNCONFIRMED','Mantener sin confirmar'],['UNRESOLVED','Dejar sin resolver']);
    if(!['DISCONTINUED','COMPLETED'].includes(item.state))choices.push(['DISCONTINUE','Suspender']);
    if(['ACTIVE_CONFIRMED','PRESCRIBED_NOT_CONFIRMED_ACTIVE'].includes(item.state))choices.push(['COMPLETE','Completar tratamiento']);
    return choices;
  }
  function paintReconciliation(data){
    const target=$('[data-lon05b-reconcile-items]');target.replaceChildren();
    $('[data-lon05b-reconcile-version]').textContent=`Versión de lista cargada: ${data.list_version}`;
    (data.items||[]).forEach(item=>{
      const row=document.createElement('div');row.className='lon05b-reconcile-row';row.dataset.medicationId=item.medication_id;
      const identity=document.createElement('div'),name=document.createElement('strong'),detail=document.createElement('span');name.textContent=item.medication_name;detail.textContent=stateName[item.state];identity.append(name,detail);
      const controls=document.createElement('div'),select=document.createElement('select');select.setAttribute('aria-label',`Decisión para ${item.medication_name}`);select.dataset.lon05bDecision='';
      reconcileChoices(item).forEach(([value,label])=>select.add(new Option(label,value)));if(reconcileChoices(item).length===1)select.disabled=true;
      const reason=document.createElement('input');reason.dataset.lon05bDecisionReason='';reason.placeholder='Motivo clínico';reason.setAttribute('aria-label',`Motivo para ${item.medication_name}`);reason.classList.add('d-none');
      select.addEventListener('change',()=>show(reason,!!select.value&&!['KEEP_UNCONFIRMED','UNRESOLVED'].includes(select.value)));
      controls.append(select,reason);row.append(identity,controls);target.append(row);
    });
  }
  async function openReconciliation(trigger){
    if(!patientId||patientId!==patient())return;
    const sourcePatient=patientId;
    returnFocus=trigger;$('[data-lon05b-reconcile-items]').textContent='Cargando lista del servidor…';
    $('[data-lon05b-reconcile-note]').value='';$('[data-lon05b-reconcile-add]').value='';show($('[data-lon05b-reconcile-error]'),false);show($('[data-lon05b-reconcile-conflict]'),false);
    reconcileBlocked=false;$('[data-lon05b-reconcile-save]').disabled=false;reconcileDialog.showModal();
    try{const data=await request('medications');if(patient()!==sourcePatient||patientId!==sourcePatient){if(reconcileDialog.open)reconcileDialog.close();return;}reconciling={patientId,listVersion:Number(data.list_version),items:data.items||[],key:crypto.randomUUID()};paintReconciliation(data);$('#lon05b-reconcile-title').focus();}
    catch(failure){const target=$('[data-lon05b-reconcile-error]');target.textContent=failure.message;show(target,true);target.focus();}
  }
  async function saveReconciliation(event){
    event.preventDefault();if(!reconciling||reconciling.patientId!==patient()||reconcileBlocked)return;
    const errorNode=$('[data-lon05b-reconcile-error]'),decisions=[];
    for(const node of root.querySelectorAll('.lon05b-reconcile-row')){
      const action=node.querySelector('[data-lon05b-decision]').value;if(!action)continue;
      const item=reconciling.items.find(row=>String(row.medication_id)===node.dataset.medicationId);
      const body={action,medication_id:Number(item.medication_id),expected_version:Number(item.row_version)};
      const reason=node.querySelector('[data-lon05b-decision-reason]').value.trim();
      if(!reason&&!['KEEP_UNCONFIRMED','UNRESOLVED'].includes(action)){errorNode.textContent=`Indica el motivo clínico para ${item.medication_name}.`;show(errorNode,true);errorNode.focus();return;}
      if(reason)body.reason=reason;decisions.push(body);
    }
    const add=$('[data-lon05b-reconcile-add]').value.trim();if(add)decisions.push({action:'ADD',medication_name:add});
    if(!decisions.length){errorNode.textContent='Selecciona al menos una decisión o agrega un medicamento.';show(errorNode,true);errorNode.focus();return;}
    const body={expected_list_version:reconciling.listVersion,decisions};const note=$('[data-lon05b-reconcile-note]').value.trim();if(note)body.note=note;
    if(!window.confirm('¿Guardar esta conciliación como una sola revisión clínica?'))return;
    const saveButton=$('[data-lon05b-reconcile-save]');saveButton.disabled=true;show(errorNode,false);
    try{await request('medication-reconciliations','','POST',body,reconciling.key);if(patient()!==reconciling?.patientId)return;reconcileDialog.close();await load();}
    catch(failure){errorNode.textContent=failure.message;show(errorNode,true);
      if(failure.code==='STALE_LIST_VERSION'||failure.code==='STALE_VERSION'){
        reconcileBlocked=true;show($('[data-lon05b-reconcile-conflict]'),true);
        try{const latest=await request('medications');$('[data-lon05b-reconcile-server-state]').textContent=`Versión en servidor: ${latest.list_version}. Revisa los cambios antes de reemplazar tus decisiones.`;}
        catch(_){$('[data-lon05b-reconcile-server-state]').textContent='No se pudo leer la lista actual. Tus decisiones permanecen.';}
      }errorNode.focus();
    }finally{saveButton.disabled=reconcileBlocked;}
  }
  $('[data-lon05b-add-reported]').addEventListener('click',event=>open('PATIENT_REPORTED_CREATE',null,event.currentTarget));
  $('[data-lon05b-add-clinician]').addEventListener('click',event=>open('CREATE',null,event.currentTarget));
  $('[data-lon05b-refresh]').addEventListener('click',load);
  $('[data-lon05b-reconcile]').addEventListener('click',event=>openReconciliation(event.currentTarget));
  $('[data-lon05b-cancel]').addEventListener('click',()=>dialog.close());
  $('[data-lon05b-reconcile-cancel]').addEventListener('click',()=>reconcileDialog.close());
  $('[data-lon05b-reload]').addEventListener('click',async()=>{
    if(!editing?.row)return;
    try{const fresh=(await request('medications',`/${editing.row.medication_id}`)).item;editing={...editing,row:fresh,key:crypto.randomUUID()};reset(fresh);$('[data-lon05b-context]').textContent=`${fresh.medication_name} · ${stateName[fresh.state]}`;
      if(['DISCONTINUED','COMPLETED'].includes(fresh.state)&&editing.operation!=='CREATE'){
        const target=$('[data-lon05b-form-error]');target.textContent='Este episodio terminó. Cierra y registra uno nuevo si vuelve a usarse.';show(target,true);conflictBlocked=true;$('[data-lon05b-save]').disabled=true;
      }
    }catch(failure){const target=$('[data-lon05b-form-error]');target.textContent=failure.message;show(target,true);}
  });
  $('[data-lon05b-reconcile-reload]').addEventListener('click',async()=>{
    try{const fresh=await request('medications');reconciling={patientId,listVersion:Number(fresh.list_version),items:fresh.items||[],key:crypto.randomUUID()};paintReconciliation(fresh);$('[data-lon05b-reconcile-add]').value='';$('[data-lon05b-reconcile-note]').value='';show($('[data-lon05b-reconcile-conflict]'),false);show($('[data-lon05b-reconcile-error]'),false);reconcileBlocked=false;$('[data-lon05b-reconcile-save]').disabled=false;}
    catch(failure){const target=$('[data-lon05b-reconcile-error]');target.textContent=failure.message;show(target,true);}
  });
  $('[data-lon05b-history]').addEventListener('click',async()=>{
    const target=$('[data-lon05b-reconciliation-history]');if(!target.classList.contains('d-none')){show(target,false);return;}
    const sourcePatient=patientId;target.textContent='Cargando conciliaciones…';show(target,true);
    try{const data=await request('medication-reconciliations');if(patient()!==sourcePatient)return;target.replaceChildren();if(!(data.reconciliations||[]).length)target.textContent='No hay conciliaciones registradas.';
      for(const row of data.reconciliations||[]){const detail=await request('medication-reconciliations',`/${row.reconciliation_id}`);if(patient()!==sourcePatient)return;const p=document.createElement('p');p.textContent=`${row.created_at} · Profesional ${row.actor_user_id} · Lista ${row.starting_list_version} → ${row.resulting_list_version} · ${(detail.items||[]).map(item=>item.decision).join(', ')}`;target.append(p);}
    }catch(failure){target.textContent=failure.message;}
  });
  dialog.addEventListener('close',()=>{editing=null;(returnFocus?.isConnected&&returnFocus.getClientRects().length?returnFocus:$('#lon05b-title')).focus();returnFocus=null;});
  reconcileDialog.addEventListener('close',()=>{reconciling=null;(returnFocus?.isConnected&&returnFocus.getClientRects().length?returnFocus:$('#lon05b-title')).focus();returnFocus=null;});
  $('[data-lon05b-form]').addEventListener('submit',save);
  $('[data-lon05b-reconcile-form]').addEventListener('submit',saveReconciliation);
  pane.addEventListener('lon05b:prescription-selected',event=>{
    const {documentId,patientId:sourcePatient,title,trigger}=event.detail||{};
    if(String(sourcePatient)!==patient()||!Number.isSafeInteger(documentId)||documentId<1)return;
    const tab=pane.querySelector('[data-bs-target="#t-medicamentos-longitudinal"]');if(tab&&window.bootstrap?.Tab)window.bootstrap.Tab.getOrCreateInstance(tab).show();
    open('PRESCRIPTION_DERIVED_CREATE',null,trigger||event.target,{documentId,context:`Receta guardada: ${title}. El uso actual aún no está confirmado.`});
  });
  for(const name of ['patient:selected','expediente:patient_changed','expediente:patient-changed'])window.addEventListener(name,load);
  pane.querySelector('[data-bs-target="#t-medicamentos-longitudinal"]')?.addEventListener('shown.bs.tab',load);
  new MutationObserver(()=>{if(patient()!==patientId)load();}).observe(pane,{attributes:true,attributeFilter:['data-patient-id','data-active-patient-id']});
  if(patient())load();
})();
