// AGF02 keeps follow-ups outside calendar slots and uses the existing longitudinal writer.
(function () {
  const panel=document.getElementById('p-ag-admin');
  const root=document.getElementById('agf01-followups');
  if(!panel||!root)return;
  const status=root.querySelector('[data-agf01-status]');
  const groups=root.querySelector('[data-agf01-groups]');
  const feedback=root.querySelector('[data-agf02-feedback]');
  const dialog=document.getElementById('agf02-dialog');
  const form=dialog?.querySelector('[data-agf02-form]');
  const reason=dialog?.querySelector('[data-agf02-reason]');
  const error=dialog?.querySelector('[data-agf02-error]');
  const confirm=dialog?.querySelector('[data-agf02-confirm]');
  const keys=['overdue','today','upcoming','no_due'];
  let generation=0,action=null,returnFocus=null;

  function message(value){feedback.textContent=value;feedback.classList.toggle('d-none',!value);}
  function actionButton(label,kind,row,style){
    const button=document.createElement('button');button.type='button';button.className=`agf02-action ${style}`;
    button.textContent=label;button.setAttribute('aria-label',`${label}: ${row.title} · ${row.patient_name}`);
    button.addEventListener('click',()=>kind==='open'?openPatient(row):openConfirmation(kind,row,button));
    return button;
  }

  async function openPatient(row){
    message('');
    if(window.mxmedIsOperatorRole?.()===true){message('Tu rol no permite abrir el expediente clínico.');return;}
    const setPatient=window.setActivePatientId||window.mxmedSetActivePatientId;
    const navigate=window.jumpTo||window.showPanel;
    if(typeof setPatient!=='function'||typeof navigate!=='function'){message('No se pudo abrir el expediente del paciente.');return;}
    try{
      const selected=await setPatient(String(row.patient_id||''),{emitEvent:true,source:'search_open',suppressEncounterAutoContext:true,applyEntryRule:false});
      if(selected===false||navigate('p-expediente')===false){message('No se pudo abrir el expediente del paciente.');return;}
      window.requestAnimationFrame(()=>document.getElementById('p-expediente')?.querySelector('[data-vis01-open="#t-tareas-longitudinal"]')?.click());
    }catch(_){message('No se pudo abrir el expediente del paciente.');}
  }

  function openConfirmation(kind,row,trigger){
    if(!dialog||!Number.isSafeInteger(Number(row.task_id))||Number(row.row_version)<1)return;
    message('');returnFocus=trigger;
    action={kind,row,key:crypto.randomUUID()};
    const resolving=kind==='resolve';
    dialog.querySelector('[data-agf02-title]').textContent=resolving?'Resolver seguimiento':'Cancelar seguimiento';
    dialog.querySelector('[data-agf02-description]').textContent=resolving?'¿Confirmas que esta acción ya fue atendida?':'Este seguimiento dejará de aparecer como pendiente. Su registro se conservará en el expediente.';
    dialog.querySelector('[data-agf02-back]').textContent=resolving?'Cancelar':'Volver';
    confirm.textContent=resolving?'Resolver':'Cancelar seguimiento';
    confirm.classList.toggle('btn-danger',!resolving);confirm.classList.toggle('btn-primary',resolving);
    reason.value='';error.textContent='';error.classList.add('d-none');confirm.disabled=false;
    dialog.showModal();reason.focus();
  }

  async function submitAction(event){
    event.preventDefault();if(!action||confirm.disabled)return;
    const clinicalReason=reason.value.trim();
    if(!clinicalReason){error.textContent='Indica brevemente el motivo clínico.';error.classList.remove('d-none');reason.focus();return;}
    const {kind,row,key}=action;
    confirm.disabled=true;dialog.setAttribute('aria-busy','true');error.classList.add('d-none');
    try{
      const endpoint=`/api/clinical/index.php/patients/${encodeURIComponent(row.patient_id)}/longitudinal/tasks/${encodeURIComponent(row.task_id)}/${kind}`;
      const response=await fetch(endpoint,{method:'POST',credentials:'same-origin',headers:{Accept:'application/json','Content-Type':'application/json','Idempotency-Key':key},body:JSON.stringify({expected_version:Number(row.row_version),reason:clinicalReason})});
      const payload=await response.json().catch(()=>null);
      if(!response.ok||payload?.ok!==true){
        const failure=new Error('MUTATION_FAILED');failure.code=typeof payload?.error==='string'?payload.error:payload?.error?.code;
        throw failure;
      }
      dialog.close();await load();message(kind==='resolve'?'Seguimiento resuelto.':'Seguimiento cancelado.');
      panel.querySelector('#ag_refresh_btn')?.focus({preventScroll:true});
    }catch(failure){
      if(['STALE_VERSION','TERMINAL_TASK','NOT_FOUND'].includes(failure.code)){
        dialog.close();await load();message('El seguimiento cambió en otra sesión. La lista se actualizó.');
      }else{
        error.textContent='No se pudo actualizar el seguimiento. Intenta nuevamente.';error.classList.remove('d-none');error.focus();
      }
    }finally{confirm.disabled=false;dialog.removeAttribute('aria-busy');}
  }

  function renderItem(row,target){
    const item=document.createElement('article');item.className='agf01-item';
    const patient=document.createElement('strong');patient.className='agf01-patient';patient.textContent=String(row.patient_name||'Paciente');
    const action=document.createElement('p');action.className='agf01-action';action.textContent=String(row.title||'');
    item.append(patient,action);
    if(row.due_display){
      const due=document.createElement('p');due.className='agf01-detail';
      due.textContent=`Fecha límite · ${row.due_display} · ${row.derived_due_state==='OVERDUE'?'Vencido':'Pendiente'}`;
      item.append(due);
    }
    if(row.linked_appointment_display){
      const linked=document.createElement('p');linked.className='agf01-detail agf01-linked';linked.textContent=String(row.linked_appointment_display);
      item.append(linked);
    }
    if(dialog&&row.patient_id&&window.mxmedIsOperatorRole?.()!==true){
      const actions=document.createElement('div');actions.className='agf02-actions';
      actions.append(actionButton('Abrir expediente','open',row,'agf02-action--open'));
      if(Number.isSafeInteger(Number(row.task_id))&&Number(row.row_version)>0){
        actions.append(actionButton('Resolver','resolve',row,'agf02-action--resolve'),actionButton('Cancelar','cancel',row,'agf02-action--cancel'));
      }
      item.append(actions);
    }
    target.append(item);
  }

  async function load(){
    if(panel.classList.contains('d-none'))return;
    const current=++generation;
    root.setAttribute('aria-busy','true');
    status.classList.remove('d-none');status.textContent='Cargando seguimientos…';
    try{
      const response=await fetch('/api/clinical/index.php/longitudinal/follow-ups/agenda',{credentials:'same-origin',headers:{Accept:'application/json'}});
      const payload=await response.json();
      if(!response.ok||payload?.ok!==true||!payload.data?.groups)throw new Error('FOLLOW_UP_READ_FAILED');
      if(current!==generation)return;
      let count=0;
      for(const key of keys){
        const group=root.querySelector(`[data-agf01-group="${key}"]`);
        const target=root.querySelector(`[data-agf01-items="${key}"]`);
        const rows=payload.data.groups[key];
        if(!Array.isArray(rows))throw new Error('INVALID_FOLLOW_UP_GROUP');
        target.replaceChildren();
        rows.forEach(row=>renderItem(row,target));
        group.classList.toggle('d-none',rows.length===0);
        count+=rows.length;
      }
      if(count){status.classList.add('d-none');groups.classList.remove('d-none');}
      else{status.textContent='No hay seguimientos pendientes.';}
    }catch(_){
      if(current!==generation)return;
      groups.classList.add('d-none');status.classList.remove('d-none');status.textContent='No se pudieron cargar los seguimientos.';
    }finally{if(current===generation)root.removeAttribute('aria-busy');}
  }

  panel.querySelector('#ag_refresh_btn')?.addEventListener('click',load);
  window.addEventListener('lon06b:changed',load);
  dialog?.querySelector('[data-agf02-back]')?.addEventListener('click',()=>dialog.close());
  reason?.addEventListener('input',()=>{if(action)action.key=crypto.randomUUID();});
  form?.addEventListener('submit',submitAction);
  dialog?.addEventListener('close',()=>{action=null;(returnFocus?.isConnected?returnFocus:panel.querySelector('#ag_refresh_btn'))?.focus({preventScroll:true});returnFocus=null;});
  new MutationObserver(()=>{if(!panel.classList.contains('d-none'))load();}).observe(panel,{attributes:true,attributeFilter:['class']});
  if(!panel.classList.contains('d-none'))load();
})();
