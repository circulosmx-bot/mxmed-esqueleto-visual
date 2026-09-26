// LON06B: patient-level UI; the LON06A API is the only task writer.
(function () {
  const pane=document.getElementById('p-expediente'), root=document.getElementById('lon06b-tasks');
  if (!pane || !root) return;
  const $=selector=>root.querySelector(selector);
  const show=(node,visible)=>node.classList.toggle('d-none',!visible);
  const patient=()=>String(pane.dataset.patientId||pane.dataset.activePatientId||'').trim();
  const dialog=$('[data-lon06b-dialog]'),form=$('[data-lon06b-form]');
  const stateName={OPEN:'Abierta',RESOLVED:'Resuelta',CANCELED:'Cancelada'};
  const typeName={CLINICAL_ACTION:'Tarea clínica',FOLLOW_UP:'Seguimiento'};
  const actionName={CREATE:'Creada',UPDATE:'Editada',RESOLVE:'Resuelta',CANCEL:'Cancelada'};
  let patientId='',epoch=0,items=[],editing=null,returnFocus=null,conflictBlocked=false;
  const endpoint=(suffix='')=>`/api/clinical/index.php/patients/${encodeURIComponent(patientId)}/longitudinal/tasks${suffix}`;
  const date=value=>value?`${value} UTC`:'Sin fecha límite';
  const overdue=row=>row.derived_due_state==='OVERDUE';
  async function request(suffix='',method='GET',body=null,key='') {
    let response;
    try {response=await fetch(endpoint(suffix),{method,credentials:'same-origin',headers:{Accept:'application/json',...(body?{'Content-Type':'application/json'}:{}),...(key?{'Idempotency-Key':key}:{})},body:body?JSON.stringify(body):undefined});}
    catch (_) {throw {code:'NETWORK_ERROR',message:'No hay conexión. Tu borrador se conserva.'};}
    const result=await response.json().catch(()=>null);
    if (!response.ok||result?.ok!==true) {
      const code=typeof result?.error==='string'?result.error:result?.error?.code||'REQUEST_FAILED';
      const message={STALE_VERSION:'La tarea cambió en otra sesión. Tu borrador se conserva.',TERMINAL_TASK:'La tarea ya está cerrada. Tu borrador se conserva.',IDEMPOTENCY_PAYLOAD_CONFLICT:'Esta solicitud ya se usó con otro contenido. Cierra y abre una acción nueva.',M6_WRITE_WINDOW_BLOCKED:'Las escrituras clínicas están pausadas. Tu borrador se conserva.',LON06A_WRITE_DISABLED:'La edición de tareas no está habilitada en este entorno.',FOREIGN_SOURCE:'La consulta de origen no pertenece a este paciente.',FOREIGN_APPOINTMENT:'La cita no pertenece a este paciente.',INELIGIBLE_APPOINTMENT:'La cita ya no está disponible para vincular. Elige otra o crea el seguimiento sin cita.',NOT_FOUND:'Paciente o tarea fuera de tu ámbito autorizado.'}[code]||`No se pudo completar la acción (${code}).`;
      throw {code,message};
    }
    return result.data;
  }
  function button(label,callback,style='btn btn-outline-primary btn-sm') {const node=document.createElement('button');node.type='button';node.className=style;node.textContent=label;node.addEventListener('click',callback);return node;}
  async function history(row,target) {
    if (target.dataset.loaded==='true') {target.hidden=!target.hidden;return;}
    try {
      const data=await request(`/${row.task_id}/history`);
      target.replaceChildren();
      for(const event of data.events||[]) {
        const before=event.before_json?JSON.parse(event.before_json):null,after=JSON.parse(event.after_json||'{}');
        const changes=[];
        if(before&&before.appointment_id!==after.appointment_id)changes.push(after.appointment_id?'Cita vinculada':'Cita desvinculada');
        if(before&&before.due_at!==after.due_at)changes.push(`Fecha límite: ${date(after.due_at)}`);
        const line=document.createElement('p');line.textContent=`${actionName[event.operation]||event.operation} · ${date(event.occurred_at)} · actor ${event.actor_user_id}${event.reason?` · ${event.reason}`:''}${changes.length?` · ${changes.join(' · ')}`:''}`;target.append(line);
      }
      if (!target.childNodes.length) target.textContent='Sin eventos disponibles.';
      target.dataset.loaded='true';target.hidden=false;
    } catch(failure) {target.textContent=failure.message;target.hidden=false;}
  }
  function renderItem(row,target) {
    const card=document.createElement('article');card.className='lon06b-item';
    const heading=document.createElement('h5');heading.textContent=`${typeName[row.task_type]||row.task_type} · ${row.title}`;
    const meta=document.createElement('p');meta.className='lon06b-meta';
    meta.textContent=`${row.task_type==='FOLLOW_UP'?({PENDING:'Pendiente',OVERDUE:'Vencido',RESOLVED:'Resuelto',CANCELED:'Cancelado'}[row.derived_due_state]||stateName[row.state]):stateName[row.state]||row.state} · ${date(row.due_at)} · ${row.source_encounter_id?`Consulta ${row.source_encounter_id}`:'Registro longitudinal'} · ${row.appointment_id?'Cita vinculada':'Sin cita vinculada'} · versión ${row.row_version}`;
    if (overdue(row)&&row.task_type!=='FOLLOW_UP') {const badge=document.createElement('p');badge.className='lon06b-overdue';badge.textContent='Vencida';card.append(badge);}
    const actions=document.createElement('div');actions.className='lon06b-item-actions';
    if(row.state==='OPEN') {actions.append(button('Editar',event=>open('UPDATE',row,event.currentTarget)),button('Resolver',event=>open('RESOLVE',row,event.currentTarget)),button('Cancelar tarea',event=>open('CANCEL',row,event.currentTarget),'btn btn-outline-danger btn-sm'));}
    const detail=document.createElement('div');detail.className='lon06b-history';detail.hidden=true;
    actions.append(button('Ver historial',()=>history(row,detail),'btn btn-outline-secondary btn-sm'));
    card.append(heading,meta,actions,detail);target.append(card);
  }
  async function load() {
    const id=patient();if(dialog.open&&editing?.patientId!==id)dialog.close();patientId=id;const requestEpoch=++epoch;
    show($('[data-lon06b-groups]'),false);show($('[data-lon06b-error]'),false);
    if (!id) {$('[data-lon06b-status]').textContent='Selecciona un paciente para ver sus tareas.';return;}
    $('[data-lon06b-status]').textContent='Cargando tareas…';
    try {
      const data=await request();if(requestEpoch!==epoch||id!==patient())return;
      items=data.items||[];
      for(const [selector,state] of [['[data-lon06b-open]','OPEN'],['[data-lon06b-terminal]','TERMINAL']]) {
        const target=$(selector);target.replaceChildren();const rows=items.filter(row=>state==='OPEN'?row.state==='OPEN':row.state!=='OPEN');
        rows.forEach(row=>renderItem(row,target));if(!rows.length){const empty=document.createElement('p');empty.className='lon06b-empty';empty.textContent=state==='OPEN'?'No hay tareas abiertas registradas. Esto no confirma ausencia de necesidades clínicas.':'No hay tareas resueltas o canceladas.';target.append(empty);}
      }
      show($('[data-lon06b-groups]'),true);$('[data-lon06b-status]').textContent='Tareas longitudinales actualizadas.';
    } catch(failure) {if(requestEpoch!==epoch)return;$('[data-lon06b-error]').textContent=failure.message;show($('[data-lon06b-error]'),true);$('[data-lon06b-status]').textContent='No se pudieron cargar las tareas.';}
  }
  function localDate(utc) {if(!utc)return '';const parsed=new Date(utc.replace(' ','T')+'Z');const local=new Date(parsed.getTime()-parsed.getTimezoneOffset()*60000);return local.toISOString().slice(0,16);}
  function utcDate(local) {if(!local)return null;const parsed=new Date(local);return Number.isNaN(parsed.getTime())?null:parsed.toISOString().slice(0,19).replace('T',' ');}
  function reset(row) {
    $('[data-lon06b-title]').value=row?.title||'';$('[data-lon06b-due]').value=localDate(row?.due_at);$('[data-lon06b-appointment]').value=row?.appointment_id||'';$('[data-lon06b-follow-up-appointment-select]').value='';$('[data-lon06b-reason]').value='';
    show($('[data-lon06b-conflict]'),false);show($('[data-lon06b-form-error]'),false);conflictBlocked=false;$('[data-lon06b-save]').disabled=false;
  }
  function parseAppointmentTime(value) {
    const match=String(value||'').match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?/);
    if(!match)return null;
    const parsed=new Date(Number(match[1]),Number(match[2])-1,Number(match[3]),Number(match[4]),Number(match[5]),Number(match[6]||0));
    return Number.isNaN(parsed.getTime())?null:parsed;
  }
  function appointmentLabel(row) {
    const start=parseAppointmentTime(row.start_at);if(!start)return '';
    const day=new Intl.DateTimeFormat('es-MX',{day:'2-digit',month:'short',year:'numeric'}).format(start).replace(/\./g,'');
    const hour=start.getHours(),time=`${hour%12||12}:${String(start.getMinutes()).padStart(2,'0')} ${hour<12?'a. m.':'p. m.'}`;
    const location=String(row.consultorio_name||'').trim();
    return `${day} · ${time}${location?` · ${location}`:''}`;
  }
  async function loadFollowUpAppointments(targetEditing,selectedId='') {
    const select=$('[data-lon06b-follow-up-appointment-select]'),state=$('[data-lon06b-appointment-state]');
    const addOption=(value,label)=>{const option=document.createElement('option');option.value=value;option.textContent=label;select.append(option);};
    select.replaceChildren();addOption('','Cargando citas…');select.disabled=true;state.textContent='';
    const from=new Date(),to=new Date(from);to.setDate(to.getDate()+365);
    const localParam=value=>`${value.getFullYear()}-${String(value.getMonth()+1).padStart(2,'0')}-${String(value.getDate()).padStart(2,'0')} ${String(value.getHours()).padStart(2,'0')}:${String(value.getMinutes()).padStart(2,'0')}:${String(value.getSeconds()).padStart(2,'0')}`;
    try {
      const query=new URLSearchParams({from:localParam(from),to:localParam(to),patient_id:targetEditing.patientId,limit:'500'});
      const response=await fetch(`/api/agenda/index.php/appointments?${query}`,{credentials:'same-origin',headers:{Accept:'application/json'}});
      const result=await response.json().catch(()=>null);
      if(!response.ok||result?.ok!==true||!Array.isArray(result.data))throw new Error('lookup_failed');
      if(editing!==targetEditing||targetEditing.patientId!==patient())return;
      const eligible=result.data.filter(row=>String(row.patient_id||'')===targetEditing.patientId&&String(row.appointment_id||'')&&parseAppointmentTime(row.start_at)>new Date()&&['tentative','pending_otp','pending','scheduled','confirmed'].includes(String(row.status||'').toLowerCase())).sort((a,b)=>parseAppointmentTime(a.start_at)-parseAppointmentTime(b.start_at));
      targetEditing.appointments=new Map(eligible.map(row=>[String(row.appointment_id),row]));
      select.replaceChildren();addOption('','Selecciona una cita futura del paciente');
      for(const row of eligible)addOption(String(row.appointment_id),appointmentLabel(row));
      if(selectedId&&targetEditing.appointments.has(String(selectedId)))select.value=String(selectedId);
      select.disabled=false;
      if(!eligible.length)state.textContent='No hay citas futuras disponibles para vincular.';
    } catch (_) {
      if(editing!==targetEditing||targetEditing.patientId!==patient())return;
      targetEditing.appointments=new Map();select.replaceChildren();addOption('','Selecciona una cita futura del paciente');select.disabled=false;
      state.textContent='No se pudieron cargar las citas. Puedes crear el seguimiento sin vincular una cita.';
    }
  }
  function open(operation,row,trigger,context={}) {
    if(!patientId||patientId!==patient())return;
    const followUpCreate=operation==='CREATE'&&context.type==='FOLLOW_UP';
    editing={patientId,operation,row,context,key:crypto.randomUUID(),followUpCreate,appointments:new Map()};returnFocus=trigger;reset(row);
    const entry=operation==='CREATE'||operation==='UPDATE';show($('[data-lon06b-editable]'),entry);show($('[data-lon06b-reason-wrap]'),operation!=='CREATE');
    show($('[data-lon06b-appointment-raw]'),!followUpCreate);show($('[data-lon06b-follow-up-appointment]'),followUpCreate);
    $('[data-lon06b-title]').placeholder=followUpCreate?'Ej. Revisar resultados de laboratorio en una semana':'';
    $('[data-lon06b-due-label]').textContent=followUpCreate?'Fecha límite (opcional)':'Fecha límite, si aplica';
    $('[data-lon06b-due-hint]').textContent=followUpCreate?'Si no eliges una fecha, el seguimiento seguirá pendiente sin vencimiento.':'Sin fecha límite no se considera vencida.';
    $('[data-lon06b-dialog-title]').textContent=({CREATE:context.type==='FOLLOW_UP'?'Registra una acción para seguimiento':'Crear tarea clínica',UPDATE:'Editar tarea',RESOLVE:'Resolver tarea',CANCEL:'Cancelar tarea'})[operation];
    $('[data-lon06b-context]').textContent=followUpCreate?'':row?`${typeName[row.task_type]} · ${row.title} · ${stateName[row.state]}`:context.encounterId?`Creación explícita desde consulta ${context.encounterId}. La consulta no cambiará.`:'Nueva acción clínica longitudinal.';
    show($('[data-lon06b-context]'),!followUpCreate);
    $('[data-lon06b-cancel]').textContent=followUpCreate?'Cancelar':'Seguir revisando';$('[data-lon06b-save]').textContent=followUpCreate?'Crear seguimiento':'Confirmar';
    dialog.showModal();(entry?$('[data-lon06b-title]'):$('[data-lon06b-reason]')).focus();
    if(followUpCreate)loadFollowUpAppointments(editing);
  }
  async function save(event) {
    event.preventDefault();if(!editing||editing.patientId!==patient()||conflictBlocked)return;
    const {operation,row,context,key}=editing,error=$('[data-lon06b-form-error]');let body={},suffix='',method='POST';
    if(operation==='CREATE'||operation==='UPDATE') {
      body.title=$('[data-lon06b-title]').value.trim();body.due_at=utcDate($('[data-lon06b-due]').value);body.appointment_id=(editing.followUpCreate? $('[data-lon06b-follow-up-appointment-select]').value:$('[data-lon06b-appointment]').value).trim()||null;
      if(!body.title||($('[data-lon06b-due]').value&&!body.due_at)){error.textContent='Escribe la acción clínica y una fecha válida si la necesitas.';show(error,true);return;}
      if(editing.followUpCreate&&body.appointment_id){
        const listed=editing.appointments?.get(body.appointment_id);
        if(!listed){error.textContent='La cita seleccionada ya no está disponible. Actualiza la selección o crea el seguimiento sin vincular una cita.';show(error,true);return;}
        try {
          const check=await fetch(`/api/agenda/index.php/appointments/${encodeURIComponent(body.appointment_id)}`,{credentials:'same-origin',headers:{Accept:'application/json'}}),verified=await check.json().catch(()=>null);
          if(editing.patientId!==patient())return;
          const appointment=verified?.data;
          if(!check.ok||verified?.ok!==true||String(appointment?.patient_id||'')!==editing.patientId||!parseAppointmentTime(appointment?.start_at)||parseAppointmentTime(appointment.start_at)<=new Date()||!['tentative','pending_otp','pending','scheduled','confirmed'].includes(String(appointment?.status||'').toLowerCase()))throw new Error('invalid');
        } catch (_) {error.textContent='La cita seleccionada ya no es válida para este paciente. Actualiza la selección o crea el seguimiento sin vincular una cita.';show(error,true);return;}
      }
    }
    if(operation==='CREATE'){body.task_type=context.type||'CLINICAL_ACTION';if(context.encounterId)body.source_encounter_id=context.encounterId;}
    else {body.expected_version=Number(row.row_version);body.reason=$('[data-lon06b-reason]').value.trim();if(!body.reason){error.textContent='Indica un motivo clínico para esta acción.';show(error,true);return;}suffix=`/${row.task_id}${operation==='UPDATE'?'':`/${operation.toLowerCase()}`}`;if(operation==='UPDATE')method='PATCH';}
    if(['RESOLVE','CANCEL'].includes(operation)&&!window.confirm(`Confirma: ${$('[data-lon06b-dialog-title]').textContent}.`))return;
    const submit=$('[data-lon06b-save]');submit.disabled=true;show(error,false);
    try{await request(suffix,method,body,key);dialog.close();await load();window.dispatchEvent(new Event('lon06b:changed'));}
    catch(failure){error.textContent=failure.message;show(error,true);if(['STALE_VERSION','TERMINAL_TASK'].includes(failure.code)){conflictBlocked=true;show($('[data-lon06b-conflict]'),true);try{const fresh=(await request(`/${row.task_id}`)).item;$('[data-lon06b-server-state]').textContent=`Servidor: ${fresh.title} · ${stateName[fresh.state]} · versión ${fresh.row_version}.`; }catch(_){$('[data-lon06b-server-state]').textContent='Estado actual no disponible.';}}error.focus();}
    finally{submit.disabled=conflictBlocked;}
  }
  $('[data-lon06b-add-task]').addEventListener('click',event=>open('CREATE',null,event.currentTarget,{type:'CLINICAL_ACTION'}));
  $('[data-lon06b-add-follow-up]').addEventListener('click',event=>open('CREATE',null,event.currentTarget,{type:'FOLLOW_UP'}));
  $('[data-lon06b-refresh]').addEventListener('click',load);
  $('[data-lon06b-cancel]').addEventListener('click',()=>dialog.close());
  $('[data-lon06b-reload]').addEventListener('click',async()=>{if(!editing?.row)return;try{const fresh=(await request(`/${editing.row.task_id}`)).item;editing={...editing,row:fresh,key:crypto.randomUUID()};reset(fresh);$('[data-lon06b-context]').textContent=`${typeName[fresh.task_type]} · ${fresh.title} · ${stateName[fresh.state]}`;if(fresh.state!=='OPEN'){conflictBlocked=true;$('[data-lon06b-save]').disabled=true;const node=$('[data-lon06b-form-error]');node.textContent='Esta tarea ya es terminal. Cierra el formulario y crea una nueva si hace falta.';show(node,true);}}catch(failure){const node=$('[data-lon06b-form-error]');node.textContent=failure.message;show(node,true);}});
  dialog.addEventListener('close',()=>{editing=null;(returnFocus?.isConnected&&returnFocus.getClientRects().length?returnFocus:$('#lon06b-title')).focus();returnFocus=null;});
  form.addEventListener('submit',save);
  pane.querySelector('[data-lon06b-m7-follow-up]')?.addEventListener('click',event=>{const id=Number(pane.querySelector('#m7-workspace [data-m7-body]')?.dataset.encounterId);if(!Number.isSafeInteger(id)||id<1){$('[data-lon06b-error]').textContent='Abre una consulta canónica para vincularla como origen.';show($('[data-lon06b-error]'),true);return;}const tab=pane.querySelector('[data-bs-target="#t-tareas-longitudinal"]');if(tab&&window.bootstrap?.Tab)window.bootstrap.Tab.getOrCreateInstance(tab).show();open('CREATE',null,event.currentTarget,{type:'FOLLOW_UP',encounterId:id});});
  for(const name of ['patient:selected','expediente:patient_changed','expediente:patient-changed'])window.addEventListener(name,load);
  pane.querySelector('[data-bs-target="#t-tareas-longitudinal"]')?.addEventListener('shown.bs.tab',load);
  new MutationObserver(()=>{if(patient()!==patientId)load();}).observe(pane,{attributes:true,attributeFilter:['data-patient-id','data-active-patient-id']});
  if(patient())load();
})();
