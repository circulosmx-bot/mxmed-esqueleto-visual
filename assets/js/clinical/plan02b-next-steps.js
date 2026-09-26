// PLAN02B: explicit UI coordination; all persistence belongs to existing writers.
(function () {
  const root=document.querySelector('[data-plan02b]'), body=document.querySelector('#m7-workspace [data-m7-body]'), pane=document.getElementById('p-expediente');
  if(!root||!body||!pane)return;
  const eligible=['tentative','pending_otp','pending','scheduled','confirmed'];
  const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const uid=()=>crypto.randomUUID(), utc=()=>new Date().toISOString().slice(0,19).replace('T',' ');
  const dateOnly=d=>`${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
  const localDate=s=>new Date(String(s).replace(' ','T'));
  const exact=d=>new Intl.DateTimeFormat('es-MX',{day:'numeric',month:'long',year:'numeric'}).format(localDate(d+'T12:00:00'));
  const appointmentLabel=a=>a?`${new Intl.DateTimeFormat('es-MX',{day:'numeric',month:'short',year:'numeric',hour:'numeric',minute:'2-digit'}).format(localDate(a.start_at))} · ${a.consultorio_name||'Consultorio'}`:'';
  const blank=()=>({orders:[],appointment:null,followup:null,expanded:'',days:10,date:dateOnly(new Date(Date.now()+10*86400000)),consultorio:'',message:''});
  const action=extra=>({id:uid(),key:uid(),state:'DRAFT',event:utc(),payload:null,result:null,error:'',...extra});
  const labels={DRAFT:'Por confirmar',IN_PROGRESS:'Registrando…',SUCCESS:'Registrado',FAILED:'No se completó',BLOCKED_BY_DEPENDENCY:'Pendiente de registrar la cita'};
  let context=null,state=blank(),busy=false,appointments=[],locations=[],slots=[],loading='',epoch=0,guardPromise=null;
  const actions=()=>[...state.orders,state.appointment,state.followup].filter(Boolean);
  const pending=()=>actions().some(a=>a.state!=='SUCCESS');
  const storageKey=()=>context?'mxmed-plan02b:'+context.doctor+':'+context.patient+':'+context.encounter:'';
  function persist(){if(context)try{sessionStorage.setItem(storageKey(),JSON.stringify(state));}catch(_){/* same-tab in-memory state remains */}}
  function edit(a){if(!a||a.state==='SUCCESS')return;a.key=uid();a.event=utc();a.payload=null;a.state='DRAFT';a.error='';persist();}
  function contextNow(){return {patient:String(pane.dataset.patientId||pane.dataset.activePatientId||''),doctor:String(window.mxmedResolveActiveProfessionalContext?.()?.doctor_id||window.mxmedStore?.activeProfessionalContext?.doctor_id||window.mxmedStore?.doctor_id||''),encounter:Number(body.dataset.encounterId),key:body.dataset.encounterKey||''};}
  function sync(){
    const next=contextNow();if(!next.patient||!next.doctor||!next.encounter||!next.key){root.hidden=true;return;}
    if(!context||JSON.stringify(next)!==JSON.stringify(context)){
      persist();context=next;state=blank();appointments=[];locations=[];slots=[];epoch++;
      try{const stored=JSON.parse(sessionStorage.getItem(storageKey()));if(stored&&Array.isArray(stored.orders)){state=stored;actions().forEach(a=>{if(a.state==='IN_PROGRESS'){a.state='FAILED';a.error='Respuesta pendiente. Reintenta para recuperar el registro.';}});}}catch(_){}
      render();
    }
    root.hidden=body.dataset.plan02bSection!=='plan'||body.dataset.encounterState!=='open';
  }
  async function api(path,payload,key){
    let response,value;
    try{response=await fetch(path,{method:payload?'POST':'GET',credentials:'same-origin',headers:{Accept:'application/json',...(payload?{'Content-Type':'application/json','Idempotency-Key':key}:{})},...(payload?{body:JSON.stringify(payload)}:{})});value=await response.json();}catch(_){const e=new Error('No se recibió el resultado. Reintenta sin cambiar la acción para recuperar su registro.');e.code='AMBIGUOUS';throw e;}
    if(!response.ok||value?.ok!==true){const e=new Error(['collision','slot_conflict','slot_unavailable'].includes(value?.error)?'El horario dejó de estar disponible. Selecciona otro horario.':'No se completó esta acción. Revisa los datos y vuelve a intentar.');e.code=typeof value?.error==='string'?value.error:value?.error?.code;throw e;}
    return value.data;
  }
  const chosen=()=>state.appointment?.selection||null;
  function field(label,html){return `<label>${label}${html}</label>`;}
  function status(a){return `<span class="plan02b-state" data-state="${a.state}">${a.state==='SUCCESS'?'✓ ':''}${labels[a.state]}</span>${a.error?`<p class="plan02b-error" role="alert">${esc(a.error)}</p>`:''}`;}
  function render(){
    const a=state.appointment,f=state.followup,disabled=busy?' disabled':'';
    root.innerHTML=`<header><h4>Próximos pasos</h4><p>Prepara las acciones que deseas registrar. Guardar el Plan no las confirma.</p></header>
      <fieldset ${disabled}><legend class="visually-hidden">Preparar próximos pasos</legend>
      <section class="plan02b-card"><div class="plan02b-heading"><h5>Órdenes de estudios</h5><button type="button" class="btn btn-outline-primary btn-sm" data-ns="orders">${state.orders.length?'Revisar órdenes':'Agregar orden'}</button></div>
      <div ${state.expanded==='orders'?'':'hidden'}>${state.orders.map((o,i)=>`<div class="plan02b-order" data-order="${o.id}"><h6>Orden ${i+1}</h6>${status(o)}<fieldset ${o.state==='SUCCESS'?'disabled':''}>${field('Estudio / orden',`<input data-order-field="title" maxlength="160" value="${esc(o.title)}" required>`)}${field('Indicaciones',`<textarea data-order-field="summary" rows="2">${esc(o.summary)}</textarea>`)}${o.state!=='SUCCESS'?'<button type="button" class="btn btn-link" data-ns="remove-order">Eliminar</button>':''}</fieldset></div>`).join('')}<button type="button" class="btn btn-outline-primary btn-sm" data-ns="add-order">Agregar otra orden</button></div></section>
      <section class="plan02b-card"><div class="plan02b-heading"><h5>Próxima cita</h5><button type="button" class="btn btn-outline-primary btn-sm" data-ns="appointment">Elegir o agendar</button></div>
      <div ${state.expanded==='appointment'?'':'hidden'}>${a?`${status(a)}<fieldset ${a.state==='SUCCESS'?'disabled':''}><div class="plan02b-modes">${field('<input type="radio" name="ns-mode" value="existing" '+(a.mode==='existing'?'checked':'')+'> Usar una cita existente','')}${field('<input type="radio" name="ns-mode" value="new" '+(a.mode==='new'?'checked':'')+'> Agendar nueva cita','')}</div>
      ${a.mode==='existing'?field('Cita del paciente',`<select data-ns-existing><option value="">Selecciona una cita futura</option>${appointments.map(x=>`<option value="${esc(x.appointment_id)}" ${a.selection?.appointment_id===x.appointment_id?'selected':''}>${esc(appointmentLabel(x))}</option>`).join('')}</select>`):`<div class="plan02b-date-grid">${field('Dentro de (días)',`<input data-ns-days type="number" min="1" max="365" value="${state.days}">`)}${field('Fecha exacta',`<input data-ns-date type="date" value="${state.date}" min="${dateOnly(new Date())}">`)}${field('Consultorio',`<select data-ns-location><option value="">Selecciona consultorio</option>${locations.map(x=>`<option value="${esc(x.consultorio_id)}" ${state.consultorio===String(x.consultorio_id)?'selected':''}>${esc(x.name||x.nombre||x.label||'Consultorio')}</option>`).join('')}</select>`)}</div><p data-ns-exact>${esc(exact(state.date))}</p><button type="button" class="btn btn-outline-primary btn-sm" data-ns="availability">Buscar horarios</button><div class="plan02b-slots" aria-label="Horarios disponibles">${slots.map((x,i)=>`<button type="button" class="btn ${a.selection?.start_at===x.start_at?'btn-primary':'btn-outline-primary'}" data-ns-slot="${i}" aria-pressed="${a.selection?.start_at===x.start_at}">${esc(x.start_at.slice(11,16))}</button>`).join('')}</div>`}
      ${a.selection?`<p>${esc(appointmentLabel(a.selection))}</p>`:''}<button type="button" class="btn btn-link" data-ns="remove-appointment">No incluir cita</button></fieldset>`:''}<p role="status">${esc(loading)}</p></div></section>
      <section class="plan02b-card"><div class="plan02b-heading"><h5>Seguimiento</h5><button type="button" class="btn btn-outline-primary btn-sm" data-ns="followup">${f?'Revisar seguimiento':'Agregar seguimiento'}</button></div>
      <div ${state.expanded==='followup'?'':'hidden'}>${f?`${status(f)}<fieldset ${f.state==='SUCCESS'?'disabled':''}>${field('Acción clínica',`<input data-ns-title maxlength="500" value="${esc(f.title)}" placeholder="Revisar resultados de los estudios solicitados." required>`)}${chosen()?field('Vincular con próxima cita',`<select data-ns-link><option value="no" ${!f.link?'selected':''}>No vincular</option><option value="yes" ${f.link?'selected':''}>${esc(appointmentLabel(chosen()))}</option></select>`):'<p>No hay una próxima cita seleccionada. Puedes registrar el seguimiento sin vincular.</p>'}
      ${f.link?field(`<input type="checkbox" data-ns-own-due ${f.ownDue||f.due?'checked':''}> Añadir una fecha límite propia`,''):' '}
      <div ${!f.link||f.ownDue||f.due?'':'hidden'}>${field('Fecha límite (opcional)',`<input data-ns-due type="datetime-local" value="${esc(f.due)}">`)}${f.link?'<p>Úsala sólo si esta acción debe completarse antes de la cita.</p>':''}</div><button type="button" class="btn btn-link" data-ns="remove-followup">No incluir seguimiento</button></fieldset>`:''}</div></section>
      </fieldset><div data-ns-review class="plan02b-review" aria-live="polite"></div><p data-ns-message role="status" aria-live="polite">${esc(state.message)}</p><button type="button" class="btn btn-primary" data-ns="confirm" ${busy||!pending()?'disabled':''}>${busy?'Registrando acciones…':'Confirmar acciones'}</button>`;
    summary();persist();
  }
  function summary(){
    const target=root.querySelector('[data-ns-review]');if(!target)return;
    const list=actions();target.hidden=!list.length;
    target.innerHTML=`<h5>${list.length&&!pending()?'Próximos pasos registrados':'Acciones por confirmar'}</h5>${state.orders.length?'<h6>Órdenes de esta consulta</h6>':''}${state.orders.map(o=>`<p>${esc(o.title||'Orden sin título')} · ${o.state==='SUCCESS'?'Orden registrada':labels[o.state]}</p>`).join('')}${state.appointment?`<p>Próxima cita · ${esc(appointmentLabel(chosen())||'Selecciona una cita u horario')} · ${state.appointment.state==='SUCCESS'?(state.appointment.mode==='existing'?'Cita existente seleccionada':'Cita registrada'):labels[state.appointment.state]}</p>`:''}${state.followup?`<p>Seguimiento · ${esc(state.followup.title||'Escribe la acción clínica')} · ${state.followup.state==='SUCCESS'?'Seguimiento registrado':labels[state.followup.state]}${state.followup.link?' · Vinculado a próxima cita':''}${state.followup.due?' · Fecha límite propia: '+esc(state.followup.due.replace('T',' ')):''}</p>`:''}`;
    const button=root.querySelector('[data-ns="confirm"]');if(button)button.disabled=busy||!pending();persist();
  }
  async function readers(mode){
    const current=++epoch,key=storageKey();loading='Consultando…';render();
    try{
      const loc=await api('/api/agenda/index.php/consultorios?doctor_id='+encodeURIComponent(context.doctor));if(current!==epoch||key!==storageKey())return;locations=loc;
      if(mode==='existing'){
        const from=dateOnly(new Date())+' 00:00:00',to=dateOnly(new Date(Date.now()+365*86400000))+' 23:59:59';
        const rows=await api('/api/agenda/index.php/appointments?'+new URLSearchParams({patient_id:context.patient,doctor_id:context.doctor,from,to,limit:'500'}));if(current!==epoch||key!==storageKey())return;
        appointments=rows.filter(x=>String(x.patient_id)===context.patient&&String(x.doctor_id)===context.doctor&&eligible.includes(String(x.status).toLowerCase())&&localDate(x.start_at)>new Date()).map(x=>({...x,consultorio_name:x.consultorio_name||locations.find(l=>String(l.consultorio_id)===String(x.consultorio_id))?.name||'Consultorio'}));
        loading=appointments.length?'':'No hay citas futuras disponibles para vincular.';
      }else loading='Selecciona consultorio y busca horarios; todavía no se ha reservado una cita.';
    }catch(_){loading='No se pudo consultar. Vuelve a intentar.';}if(current===epoch&&key===storageKey())render();
  }
  async function availability(){
    if(!state.consultorio||!state.date){loading='Selecciona fecha y consultorio.';render();return;}
    const current=++epoch,key=storageKey();slots=[];loading='Consultando horarios…';render();
    try{const data=await api('/api/agenda/index.php/availability?'+new URLSearchParams({doctor_id:context.doctor,consultorio_id:state.consultorio,date:state.date}));if(current!==epoch||key!==storageKey())return;slots=data.slots;loading=slots.length?'Elige explícitamente un horario.':'No hay horarios disponibles. Cambia la fecha o el consultorio.';}catch(_){loading='No se pudo consultar la disponibilidad. Vuelve a intentar.';}if(current===epoch&&key===storageKey())render();
  }
  function changeAppointment(){epoch++;const a=state.appointment;if(!a)return;edit(a);a.selection=null;slots=[];if(state.followup?.state!=='SUCCESS')edit(state.followup);}
  async function run(a,path,payload){
    if(a.state==='SUCCESS')return true;
    a.payload ||= payload;a.state='IN_PROGRESS';a.error='';render();
    try{a.result=await api(path,a.payload,a.key);if((path.endsWith('/appointments')&&!a.result?.appointment_id)||(path.endsWith('/documents')&&!a.result?.document_id)||(path.endsWith('/longitudinal/tasks')&&!a.result?.item?.task_id))throw new Error('No se pudo verificar el resultado. Reintenta para recuperar el registro.');a.state='SUCCESS';persist();return true;}catch(e){a.state='FAILED';a.error=e.message;a.code=e.code;persist();return false;}
  }
  async function execute(){
    if(busy||!pending())return;
    const c=contextNow();if(JSON.stringify(c)!==JSON.stringify(context)||body.dataset.encounterState!=='open')return;
    const a=state.appointment,f=state.followup;
    if(state.orders.some(o=>!o.title.trim())||(a&&!a.selection)||(f&&(!f.title.trim()||(f.link&&!a?.selection)))){state.message='Completa las acciones seleccionadas antes de confirmar.';render();return;}
    busy=true;state.message='';render();
    try{
      for(const o of state.orders)await run(o,`/api/clinical/index.php/encounters/${encodeURIComponent(c.key)}/documents`,{document_type:'order',title:o.title.trim(),summary:o.summary.trim(),event_datetime:o.event,payload:{source:'m7_ws04'}});
      if(a&&a.state!=='SUCCESS'){
        if(a.mode==='existing'){
          a.state='IN_PROGRESS';render();try{const row=await api('/api/agenda/index.php/appointments/'+encodeURIComponent(a.selection.appointment_id));if(String(row.patient_id)!==c.patient||String(row.doctor_id)!==c.doctor||!eligible.includes(String(row.status).toLowerCase())||localDate(row.start_at)<=new Date())throw new Error('La cita ya no está disponible para vincular. Selecciona otra.');a.result=row;a.state='SUCCESS';}catch(e){a.state='FAILED';a.error=e.message;}
        }else{
          const ok=await run(a,'/api/agenda/index.php/appointments',{doctor_id:c.doctor,consultorio_id:a.selection.consultorio_id,patient_id:c.patient,start_at:a.selection.start_at,end_at:a.selection.end_at,modality:'in_person',channel_origin:'doctor',created_by_role:'doctor',created_by_id:c.doctor});
          if(!ok&&['collision','slot_conflict','slot_unavailable'].includes(a.code))await availability();
        }
      }
      if(f&&f.state!=='SUCCESS'){
        if(f.link&&a?.state!=='SUCCESS'){f.state='BLOCKED_BY_DEPENDENCY';f.error='El seguimiento vinculado a esa cita todavía no se ha creado.';}
        else await run(f,`/api/clinical/index.php/patients/${encodeURIComponent(c.patient)}/longitudinal/tasks`,{task_type:'FOLLOW_UP',title:f.title.trim(),due_at:f.due?new Date(f.due).toISOString().slice(0,19).replace('T',' '):null,source_encounter_id:c.encounter,appointment_id:f.link?a.result.appointment_id:null});
      }
      state.message=pending()?'Se conservaron las acciones registradas. Revisa las pendientes y confirma de nuevo.':'Próximos pasos registrados. La consulta sigue abierta.';
      window.dispatchEvent(new Event('lon06b:changed'));
    }finally{busy=false;render();}
  }
  root.addEventListener('input',event=>{
    if(busy)return;const e=event.target,o=state.orders.find(x=>x.id===e.closest('[data-order]')?.dataset.order);
    if(o&&e.dataset.orderField){o[e.dataset.orderField]=e.value;edit(o);}
    if(e.matches('[data-ns-days]')&&Number(e.value)>=1&&Number(e.value)<=365){state.days=Number(e.value);const d=new Date();d.setDate(d.getDate()+state.days);state.date=dateOnly(d);changeAppointment();root.querySelector('[data-ns-date]').value=state.date;root.querySelector('[data-ns-exact]').textContent=exact(state.date);root.querySelector('.plan02b-slots').replaceChildren();}
    if(e.matches('[data-ns-title]')){state.followup.title=e.value;edit(state.followup);}
    if(e.matches('[data-ns-due]')){state.followup.due=e.value;edit(state.followup);}
    summary();
  });
  root.addEventListener('change',event=>{
    if(busy)return;const e=event.target,a=state.appointment,f=state.followup;
    if(e.matches('[data-order-field], [data-ns-title], [data-ns-due], [data-ns-days]'))return;
    if(e.name==='ns-mode'){a.mode=e.value;changeAppointment();readers(a.mode);return;}
    if(e.matches('[data-ns-existing]')){edit(a);a.selection=appointments.find(x=>x.appointment_id===e.value)||null;edit(f);}
    if(e.matches('[data-ns-date]')){if(!e.value)return;state.date=e.value;changeAppointment();}
    if(e.matches('[data-ns-location]')){state.consultorio=e.value;changeAppointment();}
    if(e.matches('[data-ns-link]')){f.link=e.value==='yes';if(f.due)f.ownDue=true;edit(f);}
    if(e.matches('[data-ns-own-due]')){f.ownDue=e.checked;if(!e.checked)f.due='';edit(f);}
    render();
  });
  root.addEventListener('click',event=>{
    if(busy)return;const e=event.target.closest('button');if(!e)return;
    if(e.dataset.nsSlot!=null){const x=slots[Number(e.dataset.nsSlot)];if(!x)return;edit(state.appointment);state.appointment.selection={...x,consultorio_id:state.consultorio,consultorio_name:locations.find(l=>String(l.consultorio_id)===state.consultorio)?.name||'Consultorio'};edit(state.followup);render();return;}
    switch(e.dataset.ns){
      case 'orders':if(!state.orders.length)state.orders.push(action({title:'',summary:''}));state.expanded='orders';break;
      case 'add-order':state.orders.push(action({title:'',summary:''}));break;
      case 'remove-order':state.orders=state.orders.filter(o=>o.id!==e.closest('[data-order]').dataset.order||o.state==='SUCCESS');break;
      case 'appointment':state.appointment ||= action({mode:'existing',selection:null});state.expanded='appointment';readers(state.appointment.mode);return;
      case 'remove-appointment':if(state.appointment.state==='SUCCESS')return;state.appointment=null;if(state.followup?.state!=='SUCCESS'&&state.followup){state.followup.link=false;edit(state.followup);}break;
      case 'followup':state.followup ||= action({title:'',due:'',link:false,ownDue:false});state.expanded='followup';break;
      case 'remove-followup':if(state.followup.state!=='SUCCESS')state.followup=null;break;
      case 'availability':availability();return;
      case 'confirm':execute();return;
      default:return;
    }render();
  });
  async function mayLeave(){
    if(busy){state.message='Espera a que termine el registro de las acciones.';render();return false;}
    if(!pending())return true;if(guardPromise)return guardPromise;
    guardPromise=new Promise(resolve=>{
      const dialog=document.createElement('dialog');dialog.className='plan02b-leave';dialog.setAttribute('aria-label','Acciones sin confirmar');dialog.innerHTML='<h4>Tienes acciones sin confirmar.</h4><p>Las acciones registradas se conservarán.</p><div><button type="button" class="btn btn-primary" data-stay>Seguir aquí</button><button type="button" class="btn btn-outline-secondary" data-discard>Descartar acciones y continuar</button></div>';
      document.body.append(dialog);const finish=value=>{dialog.close();dialog.remove();guardPromise=null;resolve(value);};dialog.querySelector('[data-stay]').onclick=()=>finish(false);dialog.oncancel=e=>{e.preventDefault();finish(false);};dialog.querySelector('[data-discard]').onclick=()=>{state.orders=state.orders.filter(o=>o.state==='SUCCESS');if(state.appointment?.state!=='SUCCESS')state.appointment=null;if(state.followup?.state!=='SUCCESS')state.followup=null;state.message='';render();finish(true);};dialog.showModal();dialog.querySelector('[data-stay]').focus();
    });return guardPromise;
  }
  window.mxmedPlanNextSteps={mayLeave,hasPending:pending,isBusy:()=>busy};
  window.addEventListener('beforeunload',event=>{if(pending()||busy){event.preventDefault();event.returnValue='';}});
  new MutationObserver(sync).observe(body,{attributes:true,attributeFilter:['data-encounter-id','data-encounter-key','data-encounter-state','data-plan02b-section']});
  sync();
})();
