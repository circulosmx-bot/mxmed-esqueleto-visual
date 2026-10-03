// PLAN02B: explicit UI coordination; all persistence belongs to existing writers.
(function () {
  const root=document.querySelector('[data-plan02b]'), body=document.querySelector('#m7-workspace [data-m7-body]'), pane=document.getElementById('p-expediente');
  const collector=document.querySelector('[data-plan02b-collector]'), badge=document.querySelector('[data-plan02b-count]');
  if(!root||!body||!pane||!collector||!badge)return;
  const confirmButton=document.createElement('button');confirmButton.type='button';confirmButton.className='btn btn-outline-primary';confirmButton.dataset.ns='confirm';
  const confirmSlot=document.querySelector('[data-plan-confirm-slot]');
  const eligible=['tentative','pending_otp','pending','scheduled','confirmed'];
  const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const uid=()=>crypto.randomUUID(), utc=()=>new Date().toISOString().slice(0,19).replace('T',' ');
  const dateOnly=d=>`${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
  const localDate=s=>new Date(String(s).replace(' ','T'));
  const exact=d=>new Intl.DateTimeFormat('es-MX',{day:'numeric',month:'long',year:'numeric'}).format(localDate(d+'T12:00:00'));
  const appointmentLabel=a=>a?`${new Intl.DateTimeFormat('es-MX',{day:'numeric',month:'short',year:'numeric',hour:'numeric',minute:'2-digit'}).format(localDate(a.start_at))} · ${a.consultorio_name||'Consultorio'}`:'';
  const blank=()=>({orders:[],orderReviews:[],prescription:null,appointment:null,followup:null,expanded:'',days:10,date:dateOnly(new Date(Date.now()+10*86400000)),consultorio:'',message:''});
  const medication=()=>({medicamento:'',dosis:'',via:'',frecuencia:'',duracion:'',indicaciones:''});
  const action=extra=>({id:uid(),key:uid(),state:'DRAFT',event:utc(),payload:null,result:null,error:'',...extra});
  const labels={DRAFT:'Por registrar',IN_PROGRESS:'Registrando…',SUCCESS:'Registrado',FAILED:'Pendiente de reintento',BLOCKED_BY_DEPENDENCY:'Pendiente de otra indicación'};
  let context=null,state=blank(),busy=false,appointments=[],locations=[],slots=[],loading='',epoch=0,guardPromise=null,modal=null,feedbackTimer=null,summaryEpoch=0,summaryKey='',canonicalDocs=[],verified=new Map(),summaryLoading=false,summaryFailed=false;
  const actions=()=>[...state.orders,state.prescription,state.appointment,...state.orderReviews,state.followup].filter(Boolean);
  const unresolved=()=>actions().filter(a=>a.state!=='SUCCESS');
  const pending=()=>unresolved().length>0;
  const storageKey=()=>context?'mxmed-plan02b:'+context.doctor+':'+context.patient+':'+context.encounter:'';
  function persist(){if(context)try{sessionStorage.setItem(storageKey(),JSON.stringify(state));}catch(_){/* same-tab in-memory state remains */}}
  function edit(a){if(!a||a.state==='SUCCESS')return;a.key=uid();a.event=utc();a.payload=null;a.state='DRAFT';a.error='';a.code='';a.uncertain=false;}
  function contextNow(){return {patient:String(pane.dataset.patientId||pane.dataset.activePatientId||''),doctor:String(window.mxmedResolveActiveProfessionalContext?.()?.doctor_id||window.mxmedStore?.activeProfessionalContext?.doctor_id||window.mxmedStore?.doctor_id||''),encounter:Number(body.dataset.encounterId),key:body.dataset.encounterKey||''};}
  const clone=value=>JSON.parse(JSON.stringify(value));
  const uncertain=a=>!!a && a.state!=='SUCCESS' && (a.uncertain || a.state==='IN_PROGRESS' || (a.state==='FAILED' && a.payload && (a.code==='AMBIGUOUS'||!a.code)));
  const frozen=a=>a?.state==='SUCCESS'||uncertain(a);
  const sameContext=()=>context&&JSON.stringify(contextNow())===JSON.stringify(context);
  const semantic=(kind,a)=>JSON.stringify(kind==='orders'?{items:a.items||[],priority:a.priority,indication:a.indication,review:a.review||{mode:'none'}}:kind==='prescription'?{items:a.items,observaciones:a.observaciones}:kind==='appointment'?{mode:a.mode,selection:a.selection}:{title:a.title,due:a.due,link:a.link,ownDue:a.ownDue});
  function sync(){
    const next=contextNow();
    if(!next.patient||!next.doctor||!next.encounter||!next.key){root.hidden=true;collector.hidden=true;badge.hidden=true;summaryEpoch++;summaryKey='';clearSummary();return;}
    if(!context||JSON.stringify(next)!==JSON.stringify(context)){
      if(busy)return; // Supported context transitions are blocked while a writer is active.
      if(modal)closeModal();
      persist();context=next;state=blank();appointments=[];locations=[];slots=[];epoch++;summaryEpoch++;clearSummary();summaryKey='';
      try{
        const stored=JSON.parse(sessionStorage.getItem(storageKey()));
        if(stored&&Array.isArray(stored.orders)){
          state={...blank(),...stored};
          actions().forEach(a=>{if(a.state==='IN_PROGRESS'){a.state='FAILED';a.uncertain=true;a.code='AMBIGUOUS';a.error='Respuesta pendiente. Reintenta para recuperar el registro.';}});
        }
      }catch(_){}
    }
    // A same-encounter resume may have temporarily hidden the badge during reload.
    render();
    root.hidden=body.dataset.plan02bSection!=='plan'||body.dataset.encounterState!=='open';
    collector.hidden=body.dataset.plan02bSection!=='finalize';
    const current=storageKey()+':'+body.dataset.plan02bSection;
    if(current!==summaryKey){summaryKey=current;if(body.dataset.plan02bSection==='finalize')refreshSummary();}
  }
  async function api(path,payload,key){
    let response,value;
    try{response=await fetch(path,{method:payload?'POST':'GET',credentials:'same-origin',headers:{Accept:'application/json',...(payload?{'Content-Type':'application/json','Idempotency-Key':key}:{})},...(payload?{body:JSON.stringify(payload)}:{})});value=await response.json();}catch(_){const e=new Error('No se recibió el resultado. Reintenta sin cambiar la acción para recuperar su registro.');e.code='AMBIGUOUS';throw e;}
    if(!response.ok||value?.ok!==true){const e=new Error(['collision','slot_conflict','slot_unavailable'].includes(value?.error)?'El horario dejó de estar disponible. Selecciona otro horario.':'No se completó esta acción. Revisa los datos y vuelve a intentar.');e.code=response.status>=500?'AMBIGUOUS':typeof value?.error==='string'?value.error:value?.error?.code;throw e;}
    return value.data;
  }
  const chosen=()=>state.appointment?.selection||null;
  function field(label,html){return `<label>${label}${html}</label>`;}
  root.innerHTML='<h4>Agregar al plan</h4><div class="plan02b-action-bar"><button type="button" class="btn btn-outline-primary" data-ns="orders"><span class="material-symbols-rounded plan02b-action-icon" aria-hidden="true">science</span><strong class="plan02b-action-title">Orden de estudios</strong><span class="material-symbols-rounded plan02b-action-chevron" aria-hidden="true">chevron_right</span></button><button type="button" class="btn btn-outline-primary" data-ns="prescription"><span class="material-symbols-rounded plan02b-action-icon" aria-hidden="true">description</span><strong class="plan02b-action-title">Receta</strong><span class="material-symbols-rounded plan02b-action-chevron" aria-hidden="true">chevron_right</span></button><button type="button" class="btn btn-outline-primary" data-ns="appointment"><span class="material-symbols-rounded plan02b-action-icon" aria-hidden="true">calendar_month</span><strong class="plan02b-action-title">Próxima cita</strong><span class="material-symbols-rounded plan02b-action-chevron" aria-hidden="true">chevron_right</span></button><button type="button" class="btn btn-outline-primary" data-ns="followup"><span class="material-symbols-rounded plan02b-action-icon" aria-hidden="true">person_add</span><strong class="plan02b-action-title">Seguimiento</strong><span class="material-symbols-rounded plan02b-action-chevron" aria-hidden="true">chevron_right</span></button></div><p class="plan02b-feedback" role="status" aria-live="polite"></p>';
  function feedback(text){clearTimeout(feedbackTimer);root.querySelector('[role=status]').textContent=text;feedbackTimer=setTimeout(()=>{root.querySelector('[role=status]').textContent='';},5000);}
  function clearSummary(){canonicalDocs=[];verified.clear();summaryLoading=false;summaryFailed=false;document.querySelector('[data-review-documents]').hidden=true;document.querySelector('[data-review-document-list]').replaceChildren();}
  const kindOf=a=>state.orders.includes(a)?'orders':a===state.prescription?'prescription':a===state.appointment?'appointment':'followup';
  function summaryCard(a){
    const kind=kindOf(a),ok=verified.get(a.id),registered=a.state==='SUCCESS'&&!!ok;
    const type={orders:'Orden de estudio',prescription:'Receta',appointment:'Próxima cita',followup:a.derivedFromOrder?'Revisión del resultado':'Seguimiento'}[kind];
    let rxItems=a.items||[];if(registered&&kind==='prescription'){try{const payload=typeof ok.payload_json==='string'?JSON.parse(ok.payload_json):ok.payload_json;rxItems=payload?.prescription?.items||rxItems;}catch(_){}}
    const title=kind==='appointment'?appointmentLabel(registered?ok:a.selection):kind==='prescription'?`${rxItems.length} medicamento${rxItems.length===1?'':'s'}`:registered?ok.title:a.title;
    const stateLabel=a.state==='SUCCESS'?(registered?(kind==='appointment'?(eligible.includes(String(ok.status).toLowerCase())?'Agendada':'Cita '+String(ok.status)):kind==='orders'||kind==='prescription'?'Registrada':({OPEN:'Pendiente',RESOLVED:'Resuelto',CANCELLED:'Cancelado'}[ok.state]||'Registrado')):'Verificación pendiente'):labels[a.state];
    const due=registered&&kind==='followup'?ok.due_at:a.due;
    const extra=kind==='followup'?(a.reviewMode==='days'?`En ${a.reviewDays} días · `:'')+(a.link?'En la próxima cita':due?due.slice(0,10):'Sin fecha límite'):'';
    return `<article class="plan02b-prepared flow-consequence" data-prepared="${a.id}" data-action-state="${a.state}"><span class="material-symbols-rounded" aria-hidden="true">${{orders:'description',prescription:'medication',appointment:'event_available',followup:'person'}[kind]}</span><div><strong>${type}</strong><p>${esc(title||'Preparación pendiente de completar')}</p>${extra?`<small>${esc(extra)}</small>`:''}<span class="plan02b-state" data-state="${registered?'SUCCESS':a.state==='SUCCESS'?'VERIFYING':a.state}">${stateLabel}</span>${a.error?`<p class="plan02b-error" role="alert">${esc(a.error)}</p>`:''}${uncertain(a)&&a.state!=='IN_PROGRESS'?'<small>Confirma de nuevo para recuperar el mismo registro.</small>':''}</div>${a.state==='SUCCESS'?'':`<div class="plan02b-row-actions"><button type="button" class="btn btn-outline-primary btn-sm" data-review="${a.id}" ${busy?'disabled':''}>${uncertain(a)?'Ver detalles':'Revisar'}</button>${!uncertain(a)&&(!a.payload||kind==='prescription')?`<button type="button" class="btn btn-link btn-sm" data-remove="${a.id}" ${busy?'disabled':''}>Retirar</button>`:''}</div>`}</article>`;
  }
  function render(){
    const list=unresolved();badge.hidden=!list.length;badge.textContent=String(list.length);badge.setAttribute('aria-label',`${list.length} indicaciones requieren revisión en Finalizar`);
    root.querySelectorAll('button').forEach(b=>b.disabled=busy);
    collector.innerHTML=`<h4>Indicaciones y próximos pasos</h4><div class="flow-consequences">${actions().map(summaryCard).join('')||'<p>No hay indicaciones preparadas.</p>'}</div><div class="flow-confirm-row"><p data-ns-message role="status" aria-live="polite">${esc(state.message|| (summaryFailed?'No se pudo verificar el resumen. Actualiza la revisión.':summaryLoading?'Verificando registros…':''))}</p></div>`;
    confirmSlot.replaceChildren();
    if(list.length){confirmButton.disabled=busy||body.dataset.encounterState!=='open';confirmButton.textContent=busy?'Registrando…':'Confirmar indicaciones y próximos pasos';(matchMedia('(min-width:1200px)').matches?confirmSlot:collector.querySelector('.flow-confirm-row')).append(confirmButton);}
    const final=document.querySelector('[data-m7-finalize]');
    if(final){final.disabled=busy||pending();final.title=pending()?'Confirma las indicaciones pendientes o retíralas en Plan antes de finalizar.':'';}
    persist();window.dispatchEvent(new Event('mxmed:plan-preparations-changed'));
  }
  async function refreshSummary(){
    if(!sameContext())return;
    const seen=++summaryEpoch,c={...context};summaryLoading=true;summaryFailed=false;render();
    const valid=()=>seen===summaryEpoch&&sameContext()&&context.key===c.key;
    try{
      const data=await api(`/api/clinical/index.php/doctors/${encodeURIComponent(c.doctor)}/patients/${encodeURIComponent(c.patient)}/documents?limit=200`);
      if(!valid())return;
      canonicalDocs=(data.items||[]).filter(d=>String(d.encounter_ref_id||d.encounter_id)===String(c.encounter));verified.clear();
      for(const a of actions().filter(a=>a.state==='SUCCESS')){
        const kind=kindOf(a);
        if(kind==='orders'||kind==='prescription'){
          const doc=canonicalDocs.find(d=>String(d.document_uuid)===String(a.result?.document_uuid)||String(d.id)===String(a.result?.document_id));if(doc)verified.set(a.id,doc);
        }else{
          try{
            const value=kind==='appointment'?await api('/api/agenda/index.php/appointments/'+encodeURIComponent(a.result.appointment_id)):await api(`/api/clinical/index.php/patients/${encodeURIComponent(c.patient)}/longitudinal/tasks/${encodeURIComponent(a.result?.item?.task_id)}`);
            if(!valid())return;const row=kind==='appointment'?value:value.item;
            if(row&&String(row.patient_id)===c.patient&&String(row.doctor_id)===c.doctor&&(kind==='appointment'||Number(row.source_encounter_id)===c.encounter))verified.set(a.id,row);
          }catch(_){summaryFailed=true;}
        }
      }
      if(!valid())return;
      const area=document.querySelector('[data-review-documents]'),list=document.querySelector('[data-review-document-list]');
      const docs=canonicalDocs.filter(d=>['order','orders','lab_order','imaging_order','orden_estudio','prescription','receta'].includes(d.document_type)&&['generated','signed'].includes(d.status)&&d.has_successor!=1);
      area.hidden=!docs.length;list.replaceChildren();
      docs.slice(0,3).forEach(d=>{const b=document.createElement('button');b.type='button';b.className='btn btn-outline-primary btn-sm';b.textContent=d.title||'Documento clínico';b.title=b.textContent;b.onclick=()=>window.dispatchEvent(new CustomEvent('mxmed:review-document',{detail:{document:d,encounterKey:c.key}}));list.append(b);});
      if(docs.length>3){const b=document.createElement('button');b.type='button';b.className='btn btn-link btn-sm';b.textContent=`Ver ${docs.length} documentos`;b.onclick=()=>document.querySelector('[data-review-docs]').click();list.append(b);}
    }catch(_){if(valid()){summaryFailed=true;verified.clear();}}
    finally{if(valid()){summaryLoading=false;render();}}
  }
  function closeModal(){
    if(!modal)return;const {dialog,trigger}=modal;modal.composer?.destroy();epoch++;modal=null;dialog.close();dialog.remove();const target=trigger?.isConnected?trigger:trigger?.dataset.review?collector.querySelector(`[data-review="${CSS.escape(trigger.dataset.review)}"]`):null;target?.focus({preventScroll:true});
  }
  function dirtyModal(){return modal&&!modal.readonly&&(semantic(modal.kind,modal.draft)!==modal.baseline||JSON.stringify(modal.search)!==modal.searchBaseline);}
  function requestClose(){
    if(dirtyModal()&&!window.confirm('¿Descartar los cambios de este modal? La preparación anterior se conservará.'))return;
    closeModal();
  }
  function openModal(kind,original,trigger){
    if(busy||modal||!sameContext())return;
    const draft=original?clone(original):action(kind==='orders'?{title:'',summary:'',items:[],priority:'Rutinaria',indication:'',review:{mode:'none',days:10,date:''}}:kind==='prescription'?{items:[medication()],observaciones:''}:kind==='appointment'?{mode:'existing',selection:null}:{title:'',due:'',link:false,ownDue:false});
    const dialog=document.createElement('dialog');dialog.className='plan02b-modal';dialog.setAttribute('aria-labelledby','plan02b-modal-title');
    dialog.innerHTML='<form><header><h4 id="plan02b-modal-title"></h4><button type="button" class="btn btn-link" data-modal-close aria-label="Cerrar">×</button></header><div class="plan02b-modal-content"></div><p data-modal-error role="alert"></p><footer><p data-modal-helper>Se registrará al confirmar las acciones.</p><div><button type="button" class="btn btn-outline-secondary" data-modal-cancel>Cancelar</button><button type="submit" class="btn btn-primary" data-modal-add></button></div></footer></form>';
    const search={days:state.days,date:state.date,consultorio:state.consultorio};
    modal={kind,draft,original,trigger,dialog,search,searchBaseline:JSON.stringify(search),baseline:semantic(kind,draft),readonly:frozen(draft)||(kind==='orders'&&state.orderReviews.some(f=>f.derivedFromOrder===draft.id&&frozen(f)))||(kind==='appointment'&&[...state.orderReviews,state.followup].some(f=>f?.link&&frozen(f)))||body.dataset.encounterState!=='open',context:storageKey()};
    locations=[];appointments=[];slots=[];loading='';document.body.append(dialog);
    dialog.querySelector('h4').textContent={orders:'Preparar orden de estudios',prescription:'Preparar receta',appointment:'Preparar próxima cita',followup:'Preparar seguimiento'}[kind];
    dialog.querySelector('[data-modal-add]').textContent=original?'Actualizar preparación':'Agregar a las acciones';
    dialog.querySelector('[data-modal-add]').hidden=modal.readonly;
    if(modal.readonly){dialog.querySelector('[data-modal-helper]').textContent=uncertain(draft)?'Recupera el registro al confirmar las indicaciones en Revisar y finalizar.':'Registro conservado. Detalles de sólo lectura.';dialog.querySelector('[data-modal-cancel]').textContent='Cerrar';}
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
      if(kind==='prescription'&&button.dataset.rxAdd!=null){modal.draft.items.push(medication());renderModal();dialog.querySelector(`[data-rx-row="${modal.draft.items.length-1}"] [data-rx-field="medicamento"]`)?.focus();}
      if(kind==='prescription'&&button.dataset.rxRemove!=null&&modal.draft.items.length>1){const index=Number(button.dataset.rxRemove);if(!Number.isInteger(index)||index<0||index>=modal.draft.items.length)return;modal.draft.items.splice(index,1);renderModal();dialog.querySelector(`[data-rx-row="${Math.min(index,modal.draft.items.length-1)}"] [data-rx-field="medicamento"]`)?.focus();}
      if(button.dataset.nsSlot!=null){const slot=slots[Number(button.dataset.nsSlot)];if(!slot)return;modal.draft.selection={...slot,consultorio_id:modal.search.consultorio,consultorio_name:locations.find(l=>String(l.consultorio_id)===modal.search.consultorio)?.name||'Consultorio'};renderModal();}
    });
    renderModal();dialog.showModal();dialog.querySelector('input:not(:disabled),select:not(:disabled),[data-modal-cancel]')?.focus();
    if(kind==='appointment'&&!modal.readonly)readers(draft.mode);
  }
  function patientDisplayName(){
    if(!sameContext())return '';
    // The established keyed cache cannot borrow a previous patient's header during navigation.
    const names=[window.mxmedStore?.patientLabelById?.[context.patient]];
    return names.map(x=>String(x||'').trim()).find(x=>x&&x!==context.patient&&!/^(paciente|no registrado|sin nombre|paciente sin nombre)$/i.test(x)&&!/^p_[a-z0-9_]+$/i.test(x))||'';
  }
  function renderModal(){
    if(!modal)return;const {kind,draft:a,search:s,dialog,readonly}=modal;
    const active=document.activeElement;const marker=active&&dialog.contains(active)?[...active.attributes].find(x=>x.name.startsWith('data-ns')||x.name==='data-order-field'||x.name==='name'):null;
    const patient=patientDisplayName();
    let html='';
    if(kind==='orders'){
      const review=a.review||{mode:'none',days:10,date:''};
      html=`<div data-tax03c-host></div><section class="plan02b-order-review"><h5>Revisión del resultado</h5>${field('Seguimiento',`<select data-order-review="mode"><option value="none" ${review.mode==='none'?'selected':''}>Sin seguimiento programado</option><option value="days" ${review.mode==='days'?'selected':''}>En días</option><option value="date" ${review.mode==='date'?'selected':''}>Fecha específica</option><option value="appointment" ${review.mode==='appointment'?'selected':''} ${chosen()?'':'disabled'}>En la próxima cita</option></select>`)}${review.mode==='days'?field('En (días)',`<input data-order-review="days" type="number" min="1" max="365" value="${review.days||10}" required>`):review.mode==='date'?field('Fecha específica',`<input data-order-review="date" type="date" min="${dateOnly(new Date())}" value="${esc(review.date)}" required>`):review.mode==='appointment'?`<p>${esc(appointmentLabel(chosen()))}</p>`:''}<p>${chosen()?'Se preparará un seguimiento separado de la orden.':'Para revisar en la próxima cita, selecciona o prepara primero una próxima cita en Plan.'}</p></section>`;
    }
    if(kind==='prescription')html=`${patient?`<p class="plan02b-patient">Paciente: <strong>${esc(patient)}</strong></p>`:''}<div class="plan02b-rx-rows">${(a.items||[]).map((item,index)=>`<section class="plan02b-rx-row" data-rx-row="${index}" aria-label="Medicamento ${index+1}"><header><h5>Medicamento ${index+1}</h5>${a.items.length>1&&!readonly?`<button type="button" class="btn btn-link btn-sm" data-rx-remove="${index}" aria-label="Retirar medicamento ${index+1}">Retirar</button>`:''}</header><div class="plan02b-rx-fields">${[['medicamento','Medicamento'],['dosis','Dosis'],['via','Vía'],['frecuencia','Periodicidad'],['duracion','Duración'],['indicaciones','Indicaciones']].map(([key,label])=>field(label,`<input data-rx-field="${key}" data-rx-index="${index}" maxlength="500" value="${esc(item[key]||'')}" ${key==='medicamento'?'required':''}>`)).join('')}</div></section>`).join('')}</div>${readonly?'':'<button type="button" class="btn btn-outline-primary btn-sm" data-rx-add>Agregar medicamento</button>'}${field('Indicaciones generales (opcional)',`<textarea data-rx-observaciones rows="2" maxlength="2000">${esc(a.observaciones||'')}</textarea>`)}`;
    if(kind==='appointment')html=`${patient?`<p class="plan02b-patient">Paciente: <strong>${esc(patient)}</strong></p>`:''}<div class="plan02b-modes">${field('<input type="radio" name="ns-mode" value="existing" '+(a.mode==='existing'?'checked':'')+'> Usar una cita existente','')}${field('<input type="radio" name="ns-mode" value="new" '+(a.mode==='new'?'checked':'')+'> Agendar nueva cita','')}</div>${a.mode==='existing'?field('Cita del paciente',`<select data-ns-existing><option value="">Selecciona una cita futura</option>${appointments.map(x=>`<option value="${esc(x.appointment_id)}" ${a.selection?.appointment_id===x.appointment_id?'selected':''}>${esc(appointmentLabel(x))}</option>`).join('')}</select>`):`<div class="plan02b-date-grid">${field('Dentro de (días)',`<input data-ns-days type="number" min="1" max="365" value="${s.days}">`)}${field('Fecha exacta',`<input data-ns-date type="date" value="${s.date}" min="${dateOnly(new Date())}" required>`)}${field('Consultorio',`<select data-ns-location><option value="">Selecciona consultorio</option>${locations.map(x=>`<option value="${esc(x.consultorio_id)}" ${s.consultorio===String(x.consultorio_id)?'selected':''}>${esc(x.name||x.nombre||'Consultorio')}</option>`).join('')}</select>`)}</div><p data-ns-exact>${esc(exact(s.date))}</p><button type="button" class="btn btn-outline-primary" data-ns="availability">Buscar horarios</button><div class="plan02b-slots" aria-label="Horarios disponibles">${slots.map((x,i)=>`<button type="button" class="btn ${a.selection?.start_at===x.start_at?'btn-primary':'btn-outline-primary'}" data-ns-slot="${i}" aria-pressed="${a.selection?.start_at===x.start_at}">${esc(x.start_at.slice(11,16))}</button>`).join('')}</div>`}<p data-slot-selection>${esc(appointmentLabel(a.selection))}</p><p role="status">${a.selection&&a.mode==='new'&&a.state!=='SUCCESS'?'Horario seleccionado. Aún no reservado. Se reservará al confirmar las acciones.':esc(loading)}</p>`;
    if(kind==='followup')html=`${field('Acción clínica',`<input data-ns-title maxlength="500" value="${esc(a.title)}" required>`)}${chosen()?`<p class="plan02b-appointment-context"><strong>Próxima cita:</strong> ${esc(appointmentLabel(chosen()))}</p>`+field('Vincular con esta cita',`<select data-ns-link><option value="no" ${!a.link?'selected':''}>No vincular</option><option value="yes" ${a.link?'selected':''}>Vincular</option></select>`):'<p>No hay una próxima cita preparada. Puedes añadirla después y volver para vincular.</p>'}${a.link?field(`<input type="checkbox" data-ns-own-due ${a.ownDue||a.due?'checked':''}> Añadir una fecha límite propia`,''):''}<div ${!a.link||a.ownDue||a.due?'':'hidden'}>${field('Fecha límite (opcional)',`<input data-ns-due type="datetime-local" value="${esc(a.due)}">`)}${a.link?'<p>Úsala sólo si esta acción debe completarse antes de la cita.</p>':''}</div>`;
    modal.composer?.destroy();
    dialog.querySelector('.plan02b-modal-content').innerHTML=`<fieldset ${readonly?'disabled':''}>${html}</fieldset>`;
    if(kind==='orders'&&window.mxmedStudyComposer){
      modal.composer=window.mxmedStudyComposer.mount(dialog.querySelector('[data-tax03c-host]'),{
        doctorId:context.doctor,selected:a.items||[],priority:a.priority,indication:a.indication||a.summary||'',readonly,
        onChange:(items,priority,indication)=>{a.items=items;a.priority=priority;a.indication=indication;a.summary=indication;a.title=window.mxmedStudyComposer.title(items);}
      });
    }
    if(marker)dialog.querySelector(`[${marker.name}="${CSS.escape(marker.value)}"]${active.type==='radio'?'[value="'+CSS.escape(active.value)+'"]':''}`)?.focus({preventScroll:true});
  }
  function invalidateSlot(){epoch++;modal.draft.selection=null;slots=[];loading='Busca y selecciona un horario para esta fecha y consultorio.';}
  function modalInput(event){
    if(!modal||modal.readonly)return;const e=event.target,a=modal.draft;
    if(e.dataset.orderField)a[e.dataset.orderField]=e.value;
    if(modal.kind==='prescription'&&e.dataset.rxField){const item=a.items?.[Number(e.dataset.rxIndex)];if(item&&Object.hasOwn(item,e.dataset.rxField))item[e.dataset.rxField]=e.value;}
    if(modal.kind==='prescription'&&e.matches('[data-rx-observaciones]'))a.observaciones=e.value;
    if(e.matches('[data-order-review]:not(select)')){a.review||={mode:'none',days:10,date:''};a.review[e.dataset.orderReview]=e.value;}
    if(e.matches('[data-ns-title]'))a.title=e.value;
    if(e.matches('[data-ns-due]'))a.due=e.value;
    if(e.matches('[data-ns-days]')&&Number(e.value)>=1&&Number(e.value)<=365){modal.search.days=Number(e.value);const d=new Date();d.setDate(d.getDate()+modal.search.days);modal.search.date=dateOnly(d);invalidateSlot();modal.dialog.querySelector('[data-ns-date]').value=modal.search.date;modal.dialog.querySelector('[data-ns-exact]').textContent=exact(modal.search.date);modal.dialog.querySelector('.plan02b-slots').replaceChildren();modal.dialog.querySelector('[data-slot-selection]').textContent='';modal.dialog.querySelector('.plan02b-modal-content [role=status]').textContent=loading;}
  }
  function modalChange(event){
    if(!modal||modal.readonly)return;const e=event.target,a=modal.draft;
    if(e.closest('[data-tax03c-composer]')||e.matches('[data-order-field],[data-ns-title],[data-ns-due],[data-ns-days],[data-rx-field],[data-rx-observaciones]'))return;
    if(e.matches('select[data-order-review]')){a.review||={mode:'none',days:10,date:''};a.review.mode=e.value;renderModal();return;}
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
    if(kind==='prescription'){
      if(!Array.isArray(draft.items)||!draft.items.length||draft.items.some(item=>!String(item.medicamento||'').trim())){
        dialog.querySelector('[data-modal-error]').textContent='Escribe el nombre de cada medicamento antes de agregar la receta.';return;
      }
      draft.items=draft.items.map(item=>Object.fromEntries(Object.entries(item).map(([key,value])=>[key,String(value||'').trim()])));
      draft.observaciones=String(draft.observaciones||'').trim();
    }else if((kind==='appointment'&&!draft.selection)||(kind==='orders'&&!modal.composer?.valid())||(kind!=='appointment'&&kind!=='orders'&&!draft.title.trim())||(kind==='followup'&&draft.link&&!chosen())){dialog.querySelector('[data-modal-error]').textContent=kind==='orders'?modal.composer?.validationMessage():'Completa la acción y selecciona una cita u horario cuando corresponda.';return;}
    if(kind==='orders'){
      draft.items=modal.composer.selected();draft.priority=modal.composer.priority();draft.indication=modal.composer.indication().trim();draft.summary=draft.indication;draft.title=window.mxmedStudyComposer.title(draft.items);
      const review=draft.review||{mode:'none'};
      if((review.mode==='days'&&(!Number.isInteger(Number(review.days))||Number(review.days)<1||Number(review.days)>365))||(review.mode==='date'&&(!review.date||review.date<dateOnly(new Date())))||(review.mode==='appointment'&&!chosen())){dialog.querySelector('[data-modal-error]').textContent='Completa cuándo revisar el resultado o selecciona primero una próxima cita.';return;}
    }
    if(original&&semantic(kind,original)!==semantic(kind,draft))edit(draft);
    if(kind==='orders'){
      const i=state.orders.findIndex(x=>x.id===draft.id);if(i<0)state.orders.push(draft);else state.orders[i]=draft;
      prepareOrderReview(draft);
    }
    if(kind==='prescription')state.prescription=draft;
    if(kind==='appointment'){
      const changed=!original||semantic(kind,original)!==semantic(kind,draft);
      state.appointment=draft;Object.assign(state,search);
      if(changed)[...state.orderReviews,state.followup].filter(f=>f?.link&&!frozen(f)).forEach(edit);
    }
    if(kind==='followup'){const i=state.orderReviews.findIndex(f=>f.id===draft.id);if(i>=0)state.orderReviews[i]=draft;else state.followup=draft;}
    state.message='';render();closeModal();feedback({orders:'Orden añadida a las acciones.',prescription:'Receta añadida a las acciones.',appointment:'Cita añadida a las acciones.',followup:'Seguimiento añadido a las acciones.'}[kind]);
  }
  function prepareOrderReview(order){
    const review=order.review||{mode:'none'},old=state.orderReviews.find(f=>f.derivedFromOrder===order.id);
    if(frozen(old))return;
    if(review.mode==='none'){state.orderReviews=state.orderReviews.filter(f=>f!==old);return;}
    const due=new Date();
    if(review.mode==='days'){due.setDate(due.getDate()+Number(review.days));due.setHours(12,0,0,0);}
    const task=old||action({derivedFromOrder:order.id});
    edit(task);Object.assign(task,{title:'Revisar resultado de '+order.title.trim(),due:review.mode==='days'?dateOnly(due)+'T12:00':review.mode==='date'?review.date+'T12:00':'',link:review.mode==='appointment',ownDue:false,reviewMode:review.mode,reviewDays:Number(review.days)||10});
    if(!old)state.orderReviews.push(task);
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
    try{const data=await api('/api/agenda/index.php/availability?'+new URLSearchParams({doctor_id:context.doctor,consultorio_id:s.consultorio,date:s.date}));if(current!==epoch||key!==storageKey()||modal!==target||!sameContext())return;slots=data.slots;loading=slots.length?'Elige un horario disponible.':'No hay horarios disponibles. Cambia la fecha o el consultorio.';}catch(_){if(modal===target&&current===epoch&&sameContext())loading='No se pudo consultar la disponibilidad. Vuelve a intentar.';}
    if(current===epoch&&key===storageKey()&&modal===target&&sameContext())renderModal();
  }
  async function run(a,path,payload){
    if(a.state==='SUCCESS')return true;
    a.payload ||= payload;a.uncertain=true;a.state='IN_PROGRESS';a.error='';render();
    try{a.result=await api(path,a.payload,a.key);if((path.endsWith('/appointments')&&!a.result?.appointment_id)||(path.endsWith('/documents')&&!a.result?.document_id)||(path.endsWith('/longitudinal/tasks')&&!a.result?.item?.task_id))throw Object.assign(new Error('No se pudo verificar el resultado. Reintenta para recuperar el registro.'),{code:'AMBIGUOUS'});a.state='SUCCESS';a.uncertain=false;persist();return true;}catch(e){a.state='FAILED';a.error=e.message;a.code=e.code;a.uncertain=e.code==='AMBIGUOUS';persist();return false;}
  }
  async function execute(){
    if(busy||modal||!pending()||body.dataset.plan02bSection!=='finalize')return;
    const c=contextNow();if(JSON.stringify(c)!==JSON.stringify(context)||body.dataset.encounterState!=='open')return;
    const a=state.appointment,f=state.followup,r=state.prescription;
    if(state.orders.some(o=>o.state!=='SUCCESS'&&(!Array.isArray(o.items)||!o.items.length||o.items.length>100))||(r&&(!r.items?.length||r.items.some(item=>!String(item.medicamento||'').trim())))||(a&&!a.selection)||(f&&(!f.title.trim()||(f.link&&!a?.selection)))){state.message='Completa las acciones seleccionadas antes de confirmar.';render();return;}
    busy=true;state.message='';render();
    try{
      for(const o of state.orders){
        if(o.state==='SUCCESS')continue;
        const type=window.mxmedStudyComposer.documentType(o.items);
        const orderItems=o.items.map(item=>item.type==='canonical'?{study_type_id:item.id,study_type_key:item.key,...(item.dentalLocation?{dental_location:item.dentalLocation}:{})}:{study_category:item.category,study_display_name:item.name,...(item.note?{note:item.note}:{})});
        await run(o,`/api/clinical/index.php/encounters/${encodeURIComponent(c.key)}/documents`,{
          document_type:type,title:o.title.trim(),summary:Array.from(o.summary||'').slice(0,512).join(''),event_datetime:o.event,
          payload:{source:'m7_ws04',order_area:window.mxmedStudyComposer.orderArea(o.items),priority:o.priority||'Rutinaria',indication:o.indication||'',order_items:orderItems}
        });
      }
      if(r&&r.state!=='SUCCESS'){
        const scope=window.mxmedPrescriptionRuntimeScope?.(c.patient);
        if(scope?.mode!=='consultation'||scope.patient_id!==c.patient||scope.encounter_key!==c.key){
          r.state='FAILED';r.code='CONTEXT_CHANGED';r.error='La consulta activa cambió. Vuelve a esta consulta antes de reintentar.';
        }else{
          const saved=await run(r,`/api/clinical/index.php/encounters/${encodeURIComponent(c.key)}/documents`,{
            document_type:'prescription',title:'Receta médica',event_datetime:r.event,context:{patient_id:c.patient},
            payload:{contract_version:1,prescription:{items:r.items,observaciones:r.observaciones||''}}
          });
          if(saved)window.dispatchEvent(new CustomEvent('mxmed:clinical-document-created',{detail:{patient_id:c.patient,encounter_key:c.key,document_type:'prescription',document_ref:r.result?.document_uuid||r.result?.document_id||''}}));
        }
      }
      if(a&&a.state!=='SUCCESS'){
        if(a.mode==='existing'){
          a.state='IN_PROGRESS';render();try{const row=await api('/api/agenda/index.php/appointments/'+encodeURIComponent(a.selection.appointment_id));if(String(row.patient_id)!==c.patient||String(row.doctor_id)!==c.doctor||!eligible.includes(String(row.status).toLowerCase())||localDate(row.start_at)<=new Date())throw new Error('La cita ya no está disponible para vincular. Selecciona otra.');a.result=row;a.state='SUCCESS';}catch(e){a.state='FAILED';a.error=e.message;}
        }else{
          await run(a,'/api/agenda/index.php/appointments',{doctor_id:c.doctor,consultorio_id:a.selection.consultorio_id,patient_id:c.patient,start_at:a.selection.start_at,end_at:a.selection.end_at,modality:'in_person',channel_origin:'doctor',created_by_role:'doctor',created_by_id:c.doctor});
          // A failed slot is refreshed when its preparation is reopened.
        }
      }
      for(const f of [...state.orderReviews,state.followup].filter(Boolean)){
        if(f.state==='SUCCESS')continue;
        const order=f.derivedFromOrder&&state.orders.find(o=>o.id===f.derivedFromOrder);
        if(f.derivedFromOrder&&order?.state!=='SUCCESS'){f.state='BLOCKED_BY_DEPENDENCY';f.error='Registra primero la orden para preparar su revisión.';continue;}
        if(f.link&&a?.state!=='SUCCESS'){f.state='BLOCKED_BY_DEPENDENCY';f.error='El seguimiento vinculado a esa cita todavía no se ha creado.';}
        else await run(f,`/api/clinical/index.php/patients/${encodeURIComponent(c.patient)}/longitudinal/tasks`,{task_type:'FOLLOW_UP',title:f.title.trim(),due_at:f.due?new Date(f.due).toISOString().slice(0,19).replace('T',' '):null,source_encounter_id:c.encounter,appointment_id:f.link?a.result.appointment_id:null});
      }
      state.message=pending()?'Se conservaron las acciones registradas. Revisa las pendientes y confirma de nuevo.':'Próximos pasos registrados. La consulta sigue abierta.';
      window.dispatchEvent(new Event('lon06b:changed'));
    }finally{busy=false;render();await refreshSummary();}
  }
  confirmButton.onclick=()=>execute();
  matchMedia('(min-width:1200px)').addEventListener('change',()=>render());
  root.addEventListener('click',event=>{
    const e=event.target.closest('[data-ns]');if(!e)return;
    openModal(e.dataset.ns,e.dataset.ns==='orders'?null:state[e.dataset.ns],e);
  });
  collector.addEventListener('click',event=>{
    const e=event.target.closest('button');if(!e||busy)return;
    if(e.dataset.ns==='confirm')return;
    const a=actions().find(x=>x.id===(e.dataset.review||e.dataset.remove));if(!a)return;
    const kind=state.orders.includes(a)?'orders':a===state.prescription?'prescription':a===state.appointment?'appointment':'followup';
    if(e.dataset.review){openModal(kind,a,e);return;}
    if((a.payload&&kind!=='prescription')||a.state==='SUCCESS'||uncertain(a))return;
    if(kind==='appointment'&&[...state.orderReviews,state.followup].some(f=>f?.link)){state.message='Este seguimiento depende de la próxima cita. Revísalo y elige «No vincular» o retira primero su preparación.';render();return;}
    if(kind==='orders'){
      if(state.orderReviews.some(f=>f.derivedFromOrder===a.id&&frozen(f)))return;
      state.orders=state.orders.filter(x=>x!==a);state.orderReviews=state.orderReviews.filter(f=>f.derivedFromOrder!==a.id);
    }else if(kind==='followup'&&a.derivedFromOrder){
      state.orderReviews=state.orderReviews.filter(f=>f!==a);const order=state.orders.find(o=>o.id===a.derivedFromOrder);if(order)order.review={mode:'none'};
    }else state[kind]=null;
    state.message='';render();
  });
  // Dedicated-view exit preserves encounter-scoped preparations, including retries.
  // Patient changes and terminal actions continue to use the original mayLeave guard.
  function mayLeaveView(){
    if(busy || actions().some(a=>a.state==='IN_PROGRESS')) return false;
    if(modal){requestClose();if(modal)return false;}
    persist();return true;
  }
  async function mayLeave(){
    if(window.mxmedPatientWorkspaceNavigationGuard)
      return window.mxmedPatientWorkspaceNavigationGuard.request('leave-plan-preparations');
    if(busy){state.message='Espera a que termine el registro de las acciones.';render();return false;}
    if(modal){requestClose();if(modal)return false;}
    if(!pending())return true;if(guardPromise)return guardPromise;
    const unknown=actions().some(uncertain);
    guardPromise=new Promise(resolve=>{
      const trigger=document.activeElement,dialog=document.createElement('dialog');dialog.className='plan02b-leave';dialog.setAttribute('aria-label',unknown?'Resultado pendiente de recuperar':'Acciones sin confirmar');
      dialog.innerHTML=`<h4>${unknown?'Hay un resultado pendiente de recuperar.':'Tienes acciones sin confirmar.'}</h4><p>${unknown?'Vuelve a Revisar y finalizar y confirma de nuevo para recuperar el mismo registro antes de salir o finalizar.':'Las acciones registradas se conservarán.'}</p><div><button type="button" class="btn btn-primary" data-stay>Seguir aquí</button>${unknown?'':'<button type="button" class="btn btn-outline-secondary" data-discard>Descartar acciones y continuar</button>'}</div>`;
      document.body.append(dialog);const finish=value=>{dialog.close();dialog.remove();guardPromise=null;trigger?.focus({preventScroll:true});resolve(value);};dialog.querySelector('[data-stay]').onclick=()=>finish(false);dialog.oncancel=e=>{e.preventDefault();finish(false);};
      const discard=dialog.querySelector('[data-discard]');if(discard)discard.onclick=()=>{state.orders=state.orders.filter(o=>o.state==='SUCCESS');state.orderReviews=state.orderReviews.filter(f=>f.state==='SUCCESS');if(state.prescription?.state!=='SUCCESS')state.prescription=null;if(state.appointment?.state!=='SUCCESS')state.appointment=null;if(state.followup?.state!=='SUCCESS')state.followup=null;state.message='';render();finish(true);};
      dialog.showModal();dialog.querySelector('[data-stay]').focus();
    });return guardPromise;
  }
  document.querySelector('[data-review-plan]').onclick=()=>document.querySelector('.m7-workspace-sections [data-m7-section="plan"]').click();
  document.querySelector('[data-review-docs]').onclick=()=>document.querySelector('.m7-workspace-sections [data-m7-section="documents"]').click();
  document.querySelector('[data-m7-terminal-refresh]').addEventListener('click',refreshSummary);
  window.mxmedPlanNextSteps={mayLeave,mayLeaveView,hasPending:()=>pending()||!!dirtyModal(),isBusy:()=>busy};
  window.mxmedPatientWorkspaceNavigationGuard?.register({
    id:'plan02b-preparations',getContextCopy:()=>state.prescription&&!state.orders.some(a=>a.state!=='SUCCESS')?'prescription':state.orders.some(a=>a.state!=='SUCCESS')&&!state.prescription?'order':'generic',
    isInProgress:()=>pending()||!!dirtyModal(),isSaving:()=>busy,
    risksDestination:destination=>!String(destination).startsWith('#t-')||!!dirtyModal(),
    canDiscard:()=>!actions().some(uncertain),
    onBlocked:()=>{state.message='Hay un registro pendiente de recuperar. Revisa la acción antes de salir.';render();},
    discard:()=>{
      closeModal();
      state.orders=state.orders.filter(a=>a.state==='SUCCESS');
      state.orderReviews=state.orderReviews.filter(a=>a.state==='SUCCESS');
      if(state.prescription?.state!=='SUCCESS')state.prescription=null;
      if(state.appointment?.state!=='SUCCESS')state.appointment=null;
      if(state.followup?.state!=='SUCCESS')state.followup=null;
      state.message='';render();
    }
  });
  window.addEventListener('beforeunload',event=>{if(pending()||busy||dirtyModal()){event.preventDefault();event.returnValue='';}});
  new MutationObserver(sync).observe(body,{attributes:true,attributeFilter:['data-encounter-id','data-encounter-key','data-encounter-state','data-plan02b-section']});
  sync();
})();
