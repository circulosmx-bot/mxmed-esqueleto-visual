// PLAN02B: explicit UI coordination; all persistence belongs to existing writers.
(function () {
  const root=document.querySelector('[data-plan02b]'), body=document.querySelector('#m7-workspace [data-m7-body]'), pane=document.getElementById('p-expediente');
  const collector=document.querySelector('[data-plan02b-collector]'), badge=document.querySelector('[data-plan02b-count]');
  if(!root||!body||!pane||!collector||!badge)return;
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
  let context=null,state=blank(),busy=false,appointments=[],locations=[],slots=[],loading='',epoch=0,guardPromise=null,modal=null,feedbackTimer=null;
  const actions=()=>[...state.orders,state.appointment,state.followup].filter(Boolean);
  const pending=()=>actions().some(a=>a.state!=='SUCCESS');
  const storageKey=()=>context?'mxmed-plan02b:'+context.doctor+':'+context.patient+':'+context.encounter:'';
  function persist(){if(context)try{sessionStorage.setItem(storageKey(),JSON.stringify(state));}catch(_){/* same-tab in-memory state remains */}}
  function edit(a){if(!a||a.state==='SUCCESS')return;a.key=uid();a.event=utc();a.payload=null;a.state='DRAFT';a.error='';a.code='';a.uncertain=false;}
  function contextNow(){return {patient:String(pane.dataset.patientId||pane.dataset.activePatientId||''),doctor:String(window.mxmedResolveActiveProfessionalContext?.()?.doctor_id||window.mxmedStore?.activeProfessionalContext?.doctor_id||window.mxmedStore?.doctor_id||''),encounter:Number(body.dataset.encounterId),key:body.dataset.encounterKey||''};}
  const clone=value=>JSON.parse(JSON.stringify(value));
  const uncertain=a=>!!a && a.state!=='SUCCESS' && (a.uncertain || a.state==='IN_PROGRESS' || (a.state==='FAILED' && a.payload && (a.code==='AMBIGUOUS'||!a.code)));
  const frozen=a=>a?.state==='SUCCESS'||uncertain(a);
  const sameContext=()=>context&&JSON.stringify(contextNow())===JSON.stringify(context);
  const semantic=(kind,a)=>JSON.stringify(kind==='orders'?{title:a.title,summary:a.summary}:kind==='appointment'?{mode:a.mode,selection:a.selection}:{title:a.title,due:a.due,link:a.link,ownDue:a.ownDue});
  function sync(){
    const next=contextNow();
    if(!next.patient||!next.doctor||!next.encounter||!next.key){root.hidden=true;collector.hidden=true;badge.hidden=true;return;}
    if(!context||JSON.stringify(next)!==JSON.stringify(context)){
      if(busy)return; // Supported context transitions are blocked while a writer is active.
      if(modal)closeModal();
      persist();context=next;state=blank();appointments=[];locations=[];slots=[];epoch++;
      try{
        const stored=JSON.parse(sessionStorage.getItem(storageKey()));
        if(stored&&Array.isArray(stored.orders)){
          state={...blank(),...stored};
          actions().forEach(a=>{if(a.state==='IN_PROGRESS'){a.state='FAILED';a.uncertain=true;a.code='AMBIGUOUS';a.error='Respuesta pendiente. Reintenta para recuperar el registro.';}});
        }
      }catch(_){}
      render();
    }
    root.hidden=body.dataset.plan02bSection!=='plan'||body.dataset.encounterState!=='open';
    collector.hidden=body.dataset.plan02bSection!=='documents';
  }
  async function api(path,payload,key){
    let response,value;
    try{response=await fetch(path,{method:payload?'POST':'GET',credentials:'same-origin',headers:{Accept:'application/json',...(payload?{'Content-Type':'application/json','Idempotency-Key':key}:{})},...(payload?{body:JSON.stringify(payload)}:{})});value=await response.json();}catch(_){const e=new Error('No se recibió el resultado. Reintenta sin cambiar la acción para recuperar su registro.');e.code='AMBIGUOUS';throw e;}
    if(!response.ok||value?.ok!==true){const e=new Error(['collision','slot_conflict','slot_unavailable'].includes(value?.error)?'El horario dejó de estar disponible. Selecciona otro horario.':'No se completó esta acción. Revisa los datos y vuelve a intentar.');e.code=response.status>=500?'AMBIGUOUS':typeof value?.error==='string'?value.error:value?.error?.code;throw e;}
    return value.data;
  }
  const chosen=()=>state.appointment?.selection||null;
  function field(label,html){return `<label>${label}${html}</label>`;}
  function status(a){return `<span class="plan02b-state" data-state="${a.state}">${a.state==='SUCCESS'?'✓ ':''}${labels[a.state]}</span>${a.error?`<p class="plan02b-error" role="alert">${esc(a.error)}</p>`:''}`;}
  root.innerHTML='<h4>Próximos pasos</h4><div class="plan02b-action-bar"><button type="button" class="btn btn-outline-primary" data-ns="orders">Órdenes de estudios</button><button type="button" class="btn btn-outline-primary" data-ns="appointment">Próxima cita</button><button type="button" class="btn btn-outline-primary" data-ns="followup">Seguimiento</button></div><p class="plan02b-feedback" role="status" aria-live="polite"></p>';
  function feedback(text){clearTimeout(feedbackTimer);root.querySelector('[role=status]').textContent=text;feedbackTimer=setTimeout(()=>{root.querySelector('[role=status]').textContent='';},5000);}
  function render(){
    const list=actions();badge.hidden=!list.length;badge.textContent=String(list.length);badge.setAttribute('aria-label',`${list.length} elementos añadidos desde Plan`);
    root.querySelectorAll('button').forEach(b=>b.disabled=busy);
    const rows=list.map(a=>{
      const kind=state.orders.includes(a)?'orders':a===state.appointment?'appointment':'followup';
      const type={orders:'Orden de estudios',appointment:'Próxima cita',followup:'Seguimiento'}[kind];
      const title=kind==='appointment'?appointmentLabel(a.selection):a.title;
      const success=kind==='orders'?'Orden registrada':kind==='appointment'?(a.mode==='existing'?'Cita existente seleccionada':'Cita registrada'):'Seguimiento registrado';
      return `<article class="plan02b-prepared" data-prepared="${a.id}"><div><strong>${type}</strong><p>${esc(title||'Preparación pendiente de completar')}</p>${a.state==='SUCCESS'?`<span class="plan02b-state" data-state="SUCCESS">✓ ${success}</span>`:status(a)}${uncertain(a)&&a.state!=='IN_PROGRESS'?'<p role="alert">Resultado pendiente de recuperar. Conservamos el intento; confirma de nuevo antes de editar o retirar.</p>':''}${kind==='followup'?`<p>${a.link?'Vinculado a próxima cita':'Sin vincular a una cita'} · ${a.due?'Fecha límite propia: '+esc(a.due.replace('T',' ')):'Sin fecha límite'}</p>`:''}</div><div class="plan02b-row-actions"><button type="button" class="btn btn-outline-primary btn-sm" data-review="${a.id}" ${busy?'disabled':''}>${frozen(a)?'Ver detalles':'Revisar / Editar'}</button>${!a.payload&&a.state!=='SUCCESS'?`<button type="button" class="btn btn-link btn-sm" data-remove="${a.id}" ${busy?'disabled':''}>Retirar de las acciones</button>`:''}</div></article>`;
    }).join('');
    collector.innerHTML=`<h4>Acciones preparadas desde Plan</h4>${list.length?rows:'<p>Aún no has añadido acciones desde Plan.</p>'}<p data-ns-message role="status" aria-live="polite">${esc(state.message)}</p>${list.length?`<button type="button" class="btn btn-primary" data-ns="confirm" ${busy||!pending()||body.dataset.encounterState!=='open'?'disabled':''}>${busy?'Registrando acciones…':'Confirmar acciones'}</button>`:''}`;
    persist();
  }
  function closeModal(){
    if(!modal)return;const {dialog,trigger}=modal;epoch++;modal=null;dialog.close();dialog.remove();const target=trigger?.isConnected?trigger:trigger?.dataset.review?collector.querySelector(`[data-review="${CSS.escape(trigger.dataset.review)}"]`):null;target?.focus({preventScroll:true});
  }
  function dirtyModal(){return modal&&!modal.readonly&&(semantic(modal.kind,modal.draft)!==modal.baseline||JSON.stringify(modal.search)!==modal.searchBaseline);}
  function requestClose(){
    if(dirtyModal()&&!window.confirm('¿Descartar los cambios de este modal? La preparación anterior se conservará.'))return;
    closeModal();
  }
  function openModal(kind,original,trigger){
    if(busy||modal||!sameContext())return;
    const draft=original?clone(original):action(kind==='orders'?{title:'',summary:''}:kind==='appointment'?{mode:'existing',selection:null}:{title:'',due:'',link:false,ownDue:false});
    const dialog=document.createElement('dialog');dialog.className='plan02b-modal';dialog.setAttribute('aria-labelledby','plan02b-modal-title');
    dialog.innerHTML='<form><header><h4 id="plan02b-modal-title"></h4><button type="button" class="btn btn-link" data-modal-close aria-label="Cerrar">×</button></header><div class="plan02b-modal-content"></div><p data-modal-error role="alert"></p><footer><p data-modal-helper>Se registrará al confirmar las acciones.</p><div><button type="button" class="btn btn-outline-secondary" data-modal-cancel>Cancelar</button><button type="submit" class="btn btn-primary" data-modal-add></button></div></footer></form>';
    const search={days:state.days,date:state.date,consultorio:state.consultorio};
    modal={kind,draft,original,trigger,dialog,search,searchBaseline:JSON.stringify(search),baseline:semantic(kind,draft),readonly:frozen(draft)||body.dataset.encounterState!=='open',context:storageKey()};
    locations=[];appointments=[];slots=[];loading='';document.body.append(dialog);
    dialog.querySelector('h4').textContent={orders:'Preparar orden de estudios',appointment:'Preparar próxima cita',followup:'Preparar seguimiento'}[kind];
    dialog.querySelector('[data-modal-add]').textContent=original?'Actualizar preparación':'Agregar a las acciones';
    dialog.querySelector('[data-modal-add]').hidden=modal.readonly;
    if(modal.readonly){dialog.querySelector('[data-modal-helper]').textContent=uncertain(draft)?'Recupera el resultado desde Confirmar acciones.':'Registro conservado. Detalles de sólo lectura.';dialog.querySelector('[data-modal-cancel]').textContent='Cerrar';}
    dialog.querySelector('[data-modal-close]').onclick=requestClose;
    dialog.querySelector('[data-modal-cancel]').onclick=()=>closeModal(); // Explicit cancel rejects only the local copy.
    dialog.addEventListener('keydown',e=>{
      if(e.key!=='Tab')return;
      const controls=[...dialog.querySelectorAll('button,input,select,textarea,[tabindex]')].filter(x=>!x.matches(':disabled')&&x.tabIndex>=0&&x.getClientRects().length);
      const first=controls[0],last=controls[controls.length-1];
      if(e.shiftKey&&document.activeElement===first){e.preventDefault();last?.focus();}
      else if(!e.shiftKey&&document.activeElement===last){e.preventDefault();first?.focus();}
    });
    dialog.addEventListener('cancel',e=>{e.preventDefault();requestClose();});
    dialog.addEventListener('click',e=>{if(e.target===dialog){const r=dialog.getBoundingClientRect();if(e.clientX<r.left||e.clientX>r.right||e.clientY<r.top||e.clientY>r.bottom)requestClose();}});
    dialog.querySelector('form').addEventListener('submit',acceptModal);
    dialog.addEventListener('input',modalInput);dialog.addEventListener('change',modalChange);
    dialog.addEventListener('click',e=>{
      const button=e.target.closest('button');if(!button||modal?.readonly)return;
      if(button.dataset.ns==='availability')availability();
      if(button.dataset.nsSlot!=null){const slot=slots[Number(button.dataset.nsSlot)];if(!slot)return;modal.draft.selection={...slot,consultorio_id:modal.search.consultorio,consultorio_name:locations.find(l=>String(l.consultorio_id)===modal.search.consultorio)?.name||'Consultorio'};renderModal();}
    });
    renderModal();dialog.showModal();dialog.querySelector('input:not(:disabled),select:not(:disabled),[data-modal-cancel]')?.focus();
    if(kind==='appointment'&&!modal.readonly)readers(draft.mode);
  }
  function renderModal(){
    if(!modal)return;const {kind,draft:a,search:s,dialog,readonly}=modal;
    const active=document.activeElement;const marker=active&&dialog.contains(active)?[...active.attributes].find(x=>x.name.startsWith('data-ns')||x.name==='data-order-field'||x.name==='name'):null;
    const patient=pane.querySelector('[data-clinical-field="patient_name"]')?.textContent||pane.querySelector('[data-role="exp-h-patient-name"]')?.textContent||context.patient;
    let html='';
    if(kind==='orders')html=field('Estudio / orden',`<input data-order-field="title" maxlength="160" value="${esc(a.title)}" required>`)+field('Indicaciones',`<textarea data-order-field="summary" rows="3">${esc(a.summary)}</textarea>`);
    if(kind==='appointment')html=`<p class="plan02b-patient">Paciente de esta consulta: <strong>${esc(patient)}</strong></p><div class="plan02b-modes">${field('<input type="radio" name="ns-mode" value="existing" '+(a.mode==='existing'?'checked':'')+'> Usar una cita existente','')}${field('<input type="radio" name="ns-mode" value="new" '+(a.mode==='new'?'checked':'')+'> Agendar nueva cita','')}</div>${a.mode==='existing'?field('Cita del paciente',`<select data-ns-existing><option value="">Selecciona una cita futura</option>${appointments.map(x=>`<option value="${esc(x.appointment_id)}" ${a.selection?.appointment_id===x.appointment_id?'selected':''}>${esc(appointmentLabel(x))}</option>`).join('')}</select>`):`<div class="plan02b-date-grid">${field('Dentro de (días)',`<input data-ns-days type="number" min="1" max="365" value="${s.days}">`)}${field('Fecha exacta',`<input data-ns-date type="date" value="${s.date}" min="${dateOnly(new Date())}" required>`)}${field('Consultorio',`<select data-ns-location><option value="">Selecciona consultorio</option>${locations.map(x=>`<option value="${esc(x.consultorio_id)}" ${s.consultorio===String(x.consultorio_id)?'selected':''}>${esc(x.name||x.nombre||'Consultorio')}</option>`).join('')}</select>`)}</div><p data-ns-exact>${esc(exact(s.date))}</p><button type="button" class="btn btn-outline-primary" data-ns="availability">Buscar horarios</button><div class="plan02b-slots" aria-label="Horarios disponibles">${slots.map((x,i)=>`<button type="button" class="btn ${a.selection?.start_at===x.start_at?'btn-primary':'btn-outline-primary'}" data-ns-slot="${i}" aria-pressed="${a.selection?.start_at===x.start_at}">${esc(x.start_at.slice(11,16))}</button>`).join('')}</div>`}<p data-slot-selection>${esc(appointmentLabel(a.selection))}${a.selection&&a.mode==='new'&&a.state!=='SUCCESS'?' · Horario seleccionado. Aún no reservado.':''}</p><p role="status">${esc(loading)}</p>`;
    if(kind==='followup')html=`${field('Acción clínica',`<input data-ns-title maxlength="500" value="${esc(a.title)}" required>`)}${chosen()?field('Vincular con próxima cita',`<select data-ns-link><option value="no" ${!a.link?'selected':''}>No vincular</option><option value="yes" ${a.link?'selected':''}>${esc(appointmentLabel(chosen()))}</option></select>`):'<p>No hay una próxima cita preparada. Puedes añadirla después y volver para vincular.</p>'}${a.link?field(`<input type="checkbox" data-ns-own-due ${a.ownDue||a.due?'checked':''}> Añadir una fecha límite propia`,''):''}<div ${!a.link||a.ownDue||a.due?'':'hidden'}>${field('Fecha límite (opcional)',`<input data-ns-due type="datetime-local" value="${esc(a.due)}">`)}${a.link?'<p>Úsala sólo si esta acción debe completarse antes de la cita.</p>':''}</div>`;
    dialog.querySelector('.plan02b-modal-content').innerHTML=`<fieldset ${readonly?'disabled':''}>${html}</fieldset>`;
    if(marker)dialog.querySelector(`[${marker.name}="${CSS.escape(marker.value)}"]${active.type==='radio'?'[value="'+CSS.escape(active.value)+'"]':''}`)?.focus({preventScroll:true});
  }
  function invalidateSlot(){epoch++;modal.draft.selection=null;slots=[];loading='Busca y selecciona un horario para esta fecha y consultorio.';}
  function modalInput(event){
    if(!modal||modal.readonly)return;const e=event.target,a=modal.draft;
    if(e.dataset.orderField)a[e.dataset.orderField]=e.value;
    if(e.matches('[data-ns-title]'))a.title=e.value;
    if(e.matches('[data-ns-due]'))a.due=e.value;
    if(e.matches('[data-ns-days]')&&Number(e.value)>=1&&Number(e.value)<=365){modal.search.days=Number(e.value);const d=new Date();d.setDate(d.getDate()+modal.search.days);modal.search.date=dateOnly(d);invalidateSlot();modal.dialog.querySelector('[data-ns-date]').value=modal.search.date;modal.dialog.querySelector('[data-ns-exact]').textContent=exact(modal.search.date);modal.dialog.querySelector('.plan02b-slots').replaceChildren();modal.dialog.querySelector('[data-slot-selection]').textContent='';}
  }
  function modalChange(event){
    if(!modal||modal.readonly)return;const e=event.target,a=modal.draft;
    if(e.matches('[data-order-field],[data-ns-title],[data-ns-due],[data-ns-days]'))return;
    if(e.name==='ns-mode'){a.mode=e.value;invalidateSlot();readers(a.mode);return;}
    if(e.matches('[data-ns-existing]'))a.selection=appointments.find(x=>x.appointment_id===e.value)||null;
    if(e.matches('[data-ns-date]')){if(!e.value)return;modal.search.date=e.value;invalidateSlot();}
    if(e.matches('[data-ns-location]')){modal.search.consultorio=e.value;invalidateSlot();}
    if(e.matches('[data-ns-link]')){a.link=e.value==='yes';if(a.due)a.ownDue=true;}
    if(e.matches('[data-ns-own-due]')){a.ownDue=e.checked;if(!e.checked)a.due='';}
    renderModal();
  }
  function acceptModal(event){
    event.preventDefault();if(!modal||modal.readonly||!sameContext()||modal.context!==storageKey())return;
    const {kind,draft,original,dialog,search}=modal;
    if((kind==='appointment'&&!draft.selection)||(kind!=='appointment'&&!draft.title.trim())||(kind==='followup'&&draft.link&&!chosen())){dialog.querySelector('[data-modal-error]').textContent='Completa la acción y selecciona una cita u horario cuando corresponda.';return;}
    if(original&&semantic(kind,original)!==semantic(kind,draft))edit(draft);
    if(kind==='orders'){const i=state.orders.findIndex(x=>x.id===draft.id);if(i<0)state.orders.push(draft);else state.orders[i]=draft;}
    if(kind==='appointment'){
      const changed=!original||semantic(kind,original)!==semantic(kind,draft);
      state.appointment=draft;Object.assign(state,search);
      if(changed&&state.followup?.link&&!frozen(state.followup))edit(state.followup);
    }
    if(kind==='followup')state.followup=draft;
    state.message='';render();closeModal();feedback({orders:'Orden añadida a las acciones.',appointment:'Cita añadida a las acciones.',followup:'Seguimiento añadido a las acciones.'}[kind]);
  }
  async function readers(mode){
    if(!modal)return;const current=++epoch,key=storageKey(),target=modal;loading='Consultando…';renderModal();
    try{
      const loc=await api('/api/agenda/index.php/consultorios?doctor_id='+encodeURIComponent(context.doctor));if(current!==epoch||key!==storageKey()||modal!==target||!sameContext())return;locations=loc;
      if(mode==='existing'){
        const from=dateOnly(new Date())+' 00:00:00',to=dateOnly(new Date(Date.now()+365*86400000))+' 23:59:59';
        const rows=await api('/api/agenda/index.php/appointments?'+new URLSearchParams({patient_id:context.patient,doctor_id:context.doctor,from,to,limit:'500'}));if(current!==epoch||key!==storageKey()||modal!==target||!sameContext())return;
        appointments=rows.filter(x=>String(x.patient_id)===context.patient&&String(x.doctor_id)===context.doctor&&eligible.includes(String(x.status).toLowerCase())&&localDate(x.start_at)>new Date()).map(x=>({...x,consultorio_name:x.consultorio_name||locations.find(l=>String(l.consultorio_id)===String(x.consultorio_id))?.name||'Consultorio'}));
        loading=appointments.length?'':'No hay citas futuras disponibles para vincular.';
      }else loading='Selecciona consultorio y busca horarios; todavía no se ha reservado una cita.';
    }catch(_){if(modal===target&&current===epoch&&sameContext())loading='No se pudo consultar. Vuelve a intentar.';}
    if(current===epoch&&key===storageKey()&&modal===target&&sameContext()){renderModal();if(mode==='new'&&['collision','slot_conflict','slot_unavailable'].includes(target.draft.code))availability();}
  }
  async function availability(){
    if(!modal)return;const s=modal.search;
    if(!s.consultorio||!s.date){loading='Selecciona fecha y consultorio.';renderModal();return;}
    const current=++epoch,key=storageKey(),target=modal;slots=[];loading='Consultando horarios…';renderModal();
    try{const data=await api('/api/agenda/index.php/availability?'+new URLSearchParams({doctor_id:context.doctor,consultorio_id:s.consultorio,date:s.date}));if(current!==epoch||key!==storageKey()||modal!==target||!sameContext())return;slots=data.slots;loading=slots.length?'Elige explícitamente un horario.':'No hay horarios disponibles. Cambia la fecha o el consultorio.';}catch(_){if(modal===target&&current===epoch&&sameContext())loading='No se pudo consultar la disponibilidad. Vuelve a intentar.';}
    if(current===epoch&&key===storageKey()&&modal===target&&sameContext())renderModal();
  }
  async function run(a,path,payload){
    if(a.state==='SUCCESS')return true;
    a.payload ||= payload;a.uncertain=true;a.state='IN_PROGRESS';a.error='';render();
    try{a.result=await api(path,a.payload,a.key);if((path.endsWith('/appointments')&&!a.result?.appointment_id)||(path.endsWith('/documents')&&!a.result?.document_id)||(path.endsWith('/longitudinal/tasks')&&!a.result?.item?.task_id))throw Object.assign(new Error('No se pudo verificar el resultado. Reintenta para recuperar el registro.'),{code:'AMBIGUOUS'});a.state='SUCCESS';a.uncertain=false;persist();return true;}catch(e){a.state='FAILED';a.error=e.message;a.code=e.code;a.uncertain=e.code==='AMBIGUOUS';persist();return false;}
  }
  async function execute(){
    if(busy||modal||!pending())return;
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
          await run(a,'/api/agenda/index.php/appointments',{doctor_id:c.doctor,consultorio_id:a.selection.consultorio_id,patient_id:c.patient,start_at:a.selection.start_at,end_at:a.selection.end_at,modality:'in_person',channel_origin:'doctor',created_by_role:'doctor',created_by_id:c.doctor});
          // A failed slot is refreshed when its preparation is reopened.
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
  root.addEventListener('click',event=>{
    const e=event.target.closest('[data-ns]');if(!e)return;
    openModal(e.dataset.ns,e.dataset.ns==='orders'?null:state[e.dataset.ns],e);
  });
  collector.addEventListener('click',event=>{
    const e=event.target.closest('button');if(!e||busy)return;
    if(e.dataset.ns==='confirm'){execute();return;}
    const a=actions().find(x=>x.id===(e.dataset.review||e.dataset.remove));if(!a)return;
    const kind=state.orders.includes(a)?'orders':a===state.appointment?'appointment':'followup';
    if(e.dataset.review){openModal(kind,a,e);return;}
    if(a.payload||a.state==='SUCCESS'||uncertain(a))return;
    if(kind==='appointment'&&state.followup?.link){state.message='Este seguimiento depende de la próxima cita. Revísalo y elige «No vincular» o retira primero su preparación.';render();return;}
    if(kind==='orders')state.orders=state.orders.filter(x=>x!==a);else state[kind]=null;
    state.message='';render();
  });
  async function mayLeave(){
    if(busy){state.message='Espera a que termine el registro de las acciones.';render();return false;}
    if(modal){requestClose();if(modal)return false;}
    if(!pending())return true;if(guardPromise)return guardPromise;
    const unknown=actions().some(uncertain);
    guardPromise=new Promise(resolve=>{
      const trigger=document.activeElement,dialog=document.createElement('dialog');dialog.className='plan02b-leave';dialog.setAttribute('aria-label',unknown?'Resultado pendiente de recuperar':'Acciones sin confirmar');
      dialog.innerHTML=`<h4>${unknown?'Hay un resultado pendiente de recuperar.':'Tienes acciones sin confirmar.'}</h4><p>${unknown?'Vuelve a Documentos / Acciones y confirma de nuevo para recuperar el mismo registro antes de salir o finalizar.':'Las acciones registradas se conservarán.'}</p><div><button type="button" class="btn btn-primary" data-stay>Seguir aquí</button>${unknown?'':'<button type="button" class="btn btn-outline-secondary" data-discard>Descartar acciones y continuar</button>'}</div>`;
      document.body.append(dialog);const finish=value=>{dialog.close();dialog.remove();guardPromise=null;trigger?.focus({preventScroll:true});resolve(value);};dialog.querySelector('[data-stay]').onclick=()=>finish(false);dialog.oncancel=e=>{e.preventDefault();finish(false);};
      const discard=dialog.querySelector('[data-discard]');if(discard)discard.onclick=()=>{state.orders=state.orders.filter(o=>o.state==='SUCCESS');if(state.appointment?.state!=='SUCCESS')state.appointment=null;if(state.followup?.state!=='SUCCESS')state.followup=null;state.message='';render();finish(true);};
      dialog.showModal();dialog.querySelector('[data-stay]').focus();
    });return guardPromise;
  }
  window.mxmedPlanNextSteps={mayLeave,hasPending:()=>pending()||!!dirtyModal(),isBusy:()=>busy};
  window.addEventListener('beforeunload',event=>{if(pending()||busy||dirtyModal()){event.preventDefault();event.returnValue='';}});
  new MutationObserver(sync).observe(body,{attributes:true,attributeFilter:['data-encounter-id','data-encounter-key','data-encounter-state','data-plan02b-section']});
  sync();
})();
