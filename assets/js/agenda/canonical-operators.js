/* OP-UI03: bindings for the ORIGINAL private-app panel and shared Agenda.
 * Server composition selects this adapter; client state is never authority. */
(() => {
  'use strict';
  // The physician retains the full original Agenda/clinical shell. Only its
  // existing team panel receives the canonical management command port.
  const serverBootstrap=JSON.parse(document.getElementById('mxmed-private-bootstrap')?.textContent||'{}');
  if(document.body.dataset.operatorComposition!=='canonical'&&serverBootstrap.operators_ui?.service_ready===true){
    const request=async(path,method='GET',body)=>{
      const r=await fetch(path,{method,credentials:'same-origin',cache:'no-store',headers:{'Content-Type':'application/json','X-CSRF-Token':window.mxmedPrivateSession.csrfToken},...(body===undefined?{}:{body:JSON.stringify(body)})});
      const json=await r.json();if(!r.ok||json.ok===false)throw Error(json.message||'No se pudo guardar el cambio.');return json.data??json;
    };
    const roster=async()=>{const state=await request('/api/profile-operators/index.php/bootstrap');window.mxmedOperatorPresentationRoster={presentation_ready:state.presentation_ready,operator_presentations:state.operator_presentations};window.dispatchEvent(new CustomEvent('mxmed:operator-grants'));};
    const attempts=new Map();let panel,reading=false,readAgain=false;
    const command=async(action,input)=>{const key=JSON.stringify({action,input});if(!attempts.has(key))attempts.set(key,crypto.randomUUID());const r=await request('/api/profile-operators/index.php/command','POST',{action,input,command_id:attempts.get(key)});attempts.delete(key);await roster();return r;};
    const refresh=async()=>{
      if(!panel)return;if(reading){readAgain=true;return;}reading=true;
      try{await panel.refresh();await roster();}
      catch(error){if(error.name!=='AbortError'){const notice=document.getElementById('ag_ops_modal_error');notice.textContent='No pudimos actualizar el equipo. Usa Actualizar para intentarlo de nuevo.';notice.classList.remove('d-none');}}
      finally{reading=false;if(readAgain){readAgain=false;void refresh();}}
    };
    // Session generation changes can discard an initial read. Refresh only reads;
    // mutations retain their explicit command id and are never replayed here.
    window.addEventListener('mxmed:session-authenticated',()=>void refresh());
    const mount=()=>{panel=window.mxmedInitializeOperatorsPanel(serverBootstrap.operators_ui,{data:request,command,refresh});void refresh();};
    if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',mount,{once:true});else mount();
    return;
  }
  if (document.body.dataset.operatorComposition !== 'canonical') return;
  const $ = id => document.getElementById(id);
  const bootstrap = JSON.parse($('mxmed-private-bootstrap').textContent);
  let state = bootstrap.operator, offer = null, ready = false, panel = null;
  const labels = {'agenda.appointments.read':'Consultar citas','agenda.appointments.create':'Crear citas','agenda.appointments.reschedule':'Reprogramar citas','agenda.appointments.cancel':'Cancelar citas','agenda.availability.read':'Consultar disponibilidad'};
  let resolveReady,rejectReady;
  const whenReady=new Promise((resolve,reject)=>{resolveReady=resolve;rejectReady=reject;});
  const attempts = new Map(), appointments = new Map();
  const element = (tag, text, cls) => {const e=document.createElement(tag);if(text!==undefined)e.textContent=text;if(cls)e.className=cls;return e;};
  const can = key => state.capabilities[key] === true;
  const notify = (text, error = false, id = 'ag_ops_modal_error') => {const el=$(id)||$('ag_error_box');if(!el)return;el.textContent=text;el.classList.remove('d-none');el.classList.toggle('text-danger',error);if(id==='ag_ops_modal_error'){el.classList.toggle('mx-ag-ops-modal-error',error);el.classList.toggle('alert',!error);el.classList.toggle('alert-success',!error);}};
  async function request(path, method='GET', body, headers={}) {
    let r;
    try {
      r=await fetch(path,{method,credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-Token':window.mxmedPrivateSession.csrfToken,'X-Grant-Revision':String(state.grant_revision),...headers},...(body===undefined?{}:{body:JSON.stringify(body)})});
    } catch(error) {
      // Keep session-generation cancellation intact. Other transport failures are
      // explicit failures for the original Agenda, never a successful empty read.
      if(error?.name==='AbortError')throw error;
      return {ok:false,status:0,json:{ok:false,message:'No se pudo conectar con el servicio. Intenta de nuevo.'}};
    }
    const json=await r.json().catch(()=>({ok:false,message:'Respuesta no disponible.'}));
    return {ok:r.ok&&json.ok!==false,status:r.status,json};
  }
  async function data(path,method='GET',body) {const r=await request(path,method,body);if(!r.ok)throw new Error(r.json.message||'No se pudo completar la acción.');return r.json.data??r.json;}
  async function command(action,input) {
    const fingerprint=JSON.stringify({action,input});if(!attempts.has(fingerprint))attempts.set(fingerprint,crypto.randomUUID());
    const result=await data('/api/profile-operators/index.php/command','POST',{action,input,command_id:attempts.get(fingerprint)});attempts.delete(fingerprint);return result;
  }
  async function run(fn) {try{await fn();}catch(e){notify(e.message,true);}}
  const button=(text,fn,cls='btn btn-outline-secondary btn-sm')=>{const b=element('button',text,cls);b.type='button';b.addEventListener('click',()=>run(fn));return b;};
  const expiry=value=>new Date(value.replace(' ','T')+'Z').toLocaleString('es-MX',{dateStyle:'medium',timeStyle:'short'});
  function showOffer(value){
    offer=value;$('operator-offer-content').replaceChildren(element('h6',offer.physician),element('p',offer.capabilities.map(k=>labels[k]).join(' · ')||'Sin permisos ofrecidos'));
    $('operator-offer-alias-group').classList.toggle('d-none',offer.presentation_ready!==true);$('operator-offer-alias').value=offer.personal_alias||'';$('operator-offer-alias').disabled=offer.presentation_ready!==true||offer.personal_alias!==null;$('operator-offer-error').textContent='';bootstrapModal('operator-offer-modal').show();
  }
  async function inbox(){const list=$('operator-inbox-list');list.replaceChildren();for(const item of state.inbox){
    const card=element('article',undefined,'mx-ag-ops-operator-band p-3');card.append(element('h6',item.display_name),element('p',item.capabilities.map(k=>labels[k]).join(' · ')||'Sin permisos ofrecidos'),element('p','Vence: '+expiry(item.expires_at)));
    if(item.requires_email_link)card.append(element('p','Abre el enlace de tu correo para revisar y responder esta invitación.'));
    else if(item.opened_at===null)card.append(button('Abrir invitación',async()=>showOffer(await data('/api/profile-operators/index.php/open-invitation','POST',{invitation_id:item.invitation_id}))));
    else card.append(element('p','Mensaje abierto. Si cerraste la oferta sin responder, solicita una nueva invitación.'));
    list.append(card);
  }if(!state.inbox.length)list.append(element('p','No tienes invitaciones pendientes.'));}
  async function openEmailOffer(){
    if(!/^#operator-invitation=[A-Za-z0-9_.%~-]{20,512}$/.test(location.hash))return;
    const raw=decodeURIComponent(location.hash.slice('#operator-invitation='.length));history.replaceState(null,'','/private-app.php#p-operator-inbox');
    const split=raw.indexOf('.');if(split<1)return;const invitation_id=raw.slice(0,split),token=raw.slice(split+1);
    const value=await data('/api/profile-operators/index.php/inspect-invitation','POST',{invitation_id,token});window.jumpTo?.('p-operator-inbox');showOffer({...value,token});
  }
  function bootstrapModal(id){return window.bootstrap.Modal.getOrCreateInstance($(id));}
  async function refresh(){state=await data('/api/profile-operators/index.php/bootstrap');ready=true;if(state.owner&&panel)await panel.refresh();await inbox();applyGates();window.dispatchEvent(new CustomEvent('mxmed:operator-grants'));}
  const restrictedSelectors=['[data-ag-slot-action="more"]','[data-ag-work-panel="p-ag-ajustes"]','[data-ag-slot-action*="block"]','[data-ag-slot-action*="waitlist"]','[data-ag-slot-action*="confirm"]','[data-ag-slot-action*="patient"]','[data-ag-day-action*="block"]','[data-ag-block-id]'];
  function applyGates(){
    const hide=(el,hidden)=>{if(!el)return;if(el.hidden!==hidden)el.hidden=hidden;if(hidden&&el.getAttribute('aria-disabled')!=='true')el.setAttribute('aria-disabled','true');if(!hidden&&el.getAttribute('aria-disabled')==='true')el.removeAttribute('aria-disabled');};
    document.querySelectorAll(restrictedSelectors.join(',')).forEach(e=>hide(e,true));
    hide($('ag_granted_create_btn'), !ready || !state.offices?.length || !can('agenda.appointments.create') || can('agenda.availability.read'));
    hide($('ag_proposed_time_fields'),can('agenda.availability.read'));
    hide($('ag_reschedule_proposal_fields'),can('agenda.availability.read'));
    for(const id of ['ag_event_reschedule_month_label','ag_event_reschedule_month_prev','ag_event_reschedule_month_next','ag_event_reschedule_days','ag_event_reschedule_hours']) hide($(id),!can('agenda.availability.read'));
    const hint=$('ag_operator_permission_hint');if(hint){const text=Object.keys(labels).filter(can).map(k=>labels[k]).join(' · ');if(hint.textContent!==text)hint.textContent=text;}

    for(const [selector,cap] of [['#ag_cell_menu_new_appointment, #ag_custom_slot_new_btn, #ag_create_submit_btn, [data-ag-slot-action="new"]','agenda.appointments.create'],['#ag_event_reschedule_btn, [data-ag-event-action="reschedule"]','agenda.appointments.reschedule'],['#ag_event_cancel_btn, [data-ag-event-action="cancel"]','agenda.appointments.cancel']])document.querySelectorAll(selector).forEach(e=>hide(e,!can(cap)));
    document.querySelectorAll('[data-ag-work-action="next-available"]').forEach(e=>hide(e,!can('agenda.availability.read')));
  }
  const api={whenReady,get state(){return state;},can,request,refresh,applyGates,
    agendaApi(toSql){
      const local=value=>{const d=new Date(value);return Number.isNaN(d.getTime())?'':toSql(d);};
      const denied=()=>Promise.resolve({ok:false,status:403,json:{ok:false,message:'No tienes autorización para realizar esta acción. Solicita al médico titular que revise tus permisos.'}});
      async function availability(consultorio_id,date){return data('/api/agenda/index.php/availability?'+new URLSearchParams({consultorio_id,date}));}
      async function derivedSchedule(consultorioId){
        if(!can('agenda.availability.read'))return {ok:false,data:null};
        const anchor=new Date();anchor.setHours(12,0,0,0);const days=[];
        for(let n=0;n<7;n++){const d=new Date(anchor);d.setDate(d.getDate()+n);const date=toSql(d).slice(0,10),projection=await availability(consultorioId,date);days.push({weekday:d.getDay()||7,is_active:1,windows:(projection.windows||[]).map(w=>({start_time:w.start_at.slice(11),end_time:w.end_at.slice(11)}))});}
        return {ok:true,data:{days}};
      }
      return {
        async getConsultorios(){const fresh=await data('/api/profile-operators/index.php/bootstrap');state=fresh;ready=true;return {ok:true,data:state.offices.map(o=>({...o,id:o.consultorio_id,name:o.titulo,nombre:o.titulo,is_active:1}))};},
        async getAppointments({params}){if(!can('agenda.appointments.read'))return {ok:true,data:[],__httpOk:true,__httpStatus:200};const q=new URLSearchParams(params);q.set('from',local(q.get('from')));q.set('to',local(q.get('to')));const r=await request('/api/agenda/index.php/appointments?'+q);for(const a of r.json.data||[])appointments.set(a.appointment_id,a);return {...r.json,__httpOk:r.ok,__httpStatus:r.status};},
        async getAvailability({params}){const q=new URLSearchParams(params);return (await request('/api/agenda/index.php/availability?'+q)).json;},
        async getAvailabilityBlocks({params}){if(!can('agenda.availability.read'))return {ok:true,data:[]};const q=new URLSearchParams(params),from=new Date(q.get('from')||new Date()),to=new Date(q.get('to')||from);const rows=[];for(let i=0;i<32&&from<=to;i++,from.setDate(from.getDate()+1)){const d=await availability(q.get('consultorio_id')||state.offices[0]?.consultorio_id,toSql(from).slice(0,10));rows.push(...d.closed_windows||[]);}return {ok:true,data:rows};},
        getSchedule:({consultorioId})=>derivedSchedule(consultorioId),
        async getAgendaSettings(){return {ok:false,data:null,message:'La configuración de Agenda no forma parte de estos permisos.'};},
        async searchPatients({query}){const fresh=await data('/api/profile-operators/index.php/bootstrap');state=fresh;return {ok:true,data:state.booking_subjects.filter(p=>p.display_name.toLocaleLowerCase().includes(query.toLocaleLowerCase()))};},
        async getPatientsIndex(){await refresh();return {ok:true,data:state.booking_subjects};},
        async getPatientById(id){return {ok:true,data:state.booking_subjects.find(p=>p.patient_id===id)||null};},
        async createAppointment(p,key){return request('/api/agenda/index.php/appointments','POST',Object.fromEntries(['consultorio_id','patient_id','start_at','end_at','modality'].map(k=>[k,p[k]])),{'Idempotency-Key':key});},
        async rescheduleAppointment(id,p){const body=Object.fromEntries(['from_start_at','from_end_at','to_start_at','to_end_at','to_consultorio_id'].filter(k=>p[k]).map(k=>[k,p[k]]));return request('/api/agenda/index.php/appointments/'+encodeURIComponent(id)+'/reschedule','PATCH',body);},
        async cancelAppointment(id){return request('/api/agenda/index.php/appointments/'+encodeURIComponent(id)+'/cancel','POST',{expected_start_at:appointments.get(id)?.start_at||''});},
        saveSchedule:denied,saveAgendaSettings:denied,createAvailabilityBlock:denied,updateAvailabilityBlock:denied,confirmAppointment:denied,noShowAppointment:denied,createWaitlistEntry:denied,assignWaitlistEntry:denied,getAmbiguousPatientReconciliation:denied,resolveAmbiguousPatientReconciliation:denied,cleanupAmbiguousPatientReconciliation:denied,
        async getAppointmentEvents(){return {ok:false,status:403,json:{ok:false}};},async getPatientFlags(){return {ok:false};},async getPatientBehavior(){return {ok:false};}
      };
    }
  };
  window.mxmedCanonicalOperators=api;
  window.resolveDoctorId=()=>state.profile?.id||'';
  window.mxmedResolveActiveProfessionalContext=()=>({doctor_id:state.profile?.id||'',user_id:state.account_id,role:state.owner?'owner':'operator'});
  document.body.dataset.userRole=state.owner?'owner':'operator';
  document.addEventListener('DOMContentLoaded',()=>run(async()=>{
    const name=document.querySelector('.mx-gh-identity-name-text');if(name)name.textContent=state.alias;
    const role=document.querySelector('.mx-gh-identity-role');if(role)role.textContent=state.owner?'Titular · '+state.profile.name:'Operador · '+(state.profile?.name||'Invitaciones');
    document.querySelector('.mx-hb-session-label').textContent='Sesión activa';
    document.querySelector('[data-header-logout]')?.addEventListener('click',()=>window.mxmedPrivateSession.logout());
    panel=window.mxmedInitializeOperatorsPanel(bootstrap.operators_ui,{data,command,refresh,get state(){return state;}});
    for(const action of ['accept','reject'])$('operator-offer-'+action).onclick=()=>run(async()=>{try{await command(action,{invitation_id:offer.invitation_id,token:offer.token,offer_revision:offer.offer_revision,...(action==='accept'&&offer.presentation_ready?{alias:$('operator-offer-alias').value}:{})});offer=null;bootstrapModal('operator-offer-modal').hide();await refresh();if(state.profile)location.assign('/private-app.php?workspace=agenda#p-ag-admin');}catch(e){$('operator-offer-error').textContent=e.message;}});
    $('operator-offer-modal').addEventListener('hidden.bs.modal',()=>{offer=null;run(refresh);});
    const observer=new MutationObserver(applyGates);observer.observe($('p-ag-admin')||$('viewport'),{childList:true,subtree:true});
    document.addEventListener('click',e=>{const b=e.target.closest(restrictedSelectors.join(','));if(b){e.preventDefault();e.stopImmediatePropagation();notify('No tienes autorización para realizar esta acción.',true);}},true);
    try { await refresh();await openEmailOffer();resolveReady(); } catch(error) { rejectReady(error);throw error; }
  }));
  window.addEventListener('focus',()=>run(refresh));
})();
