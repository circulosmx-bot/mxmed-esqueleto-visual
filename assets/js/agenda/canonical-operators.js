/* OP-UI03: bindings for the ORIGINAL private-app panel and shared Agenda.
 * Server composition selects this adapter; client state is never authority. */
(() => {
  'use strict';
  if (document.body.dataset.operatorComposition !== 'canonical') return;
  const $ = id => document.getElementById(id);
  const bootstrap = JSON.parse($('mxmed-private-bootstrap').textContent);
  let state = bootstrap.operator, overview = null, editing = null, offer = null, ready = false;
  const labels = {'agenda.appointments.read':'Consultar citas','agenda.appointments.create':'Crear citas','agenda.appointments.reschedule':'Reprogramar citas','agenda.appointments.cancel':'Cancelar citas','agenda.availability.read':'Consultar disponibilidad'};
  const statuses = {active:'Activo',suspended:'Suspendido',revoked:'Revocado',pending:'Invitación pendiente'};
  let resolveReady,rejectReady, pendingUi=0, overviewSequence=0;
  const whenReady=new Promise((resolve,reject)=>{resolveReady=resolve;rejectReady=reject;});
  const attempts = new Map(), appointments = new Map(), expanded = new Set();
  const element = (tag, text, cls) => {const e=document.createElement(tag);if(text!==undefined)e.textContent=text;if(cls)e.className=cls;return e;};
  const can = key => state.capabilities[key] === true;
  const notify = (text, error = false, id = 'ag_ops_modal_error') => {const el=$(id)||$('ag_error_box');if(!el)return;el.textContent=text;el.classList.remove('d-none');el.classList.toggle('text-danger',error);if(id==='ag_ops_modal_error'){el.classList.toggle('mx-ag-ops-modal-error',error);el.classList.toggle('alert',!error);el.classList.toggle('alert-success',!error);}};
  async function request(path, method='GET', body, headers={}) {
    const r=await fetch(path,{method,credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-Token':window.mxmedPrivateSession.csrfToken,'X-Grant-Revision':String(state.grant_revision),...headers},...(body===undefined?{}:{body:JSON.stringify(body)})});
    const json=await r.json().catch(()=>({ok:false,message:'Respuesta no disponible.'}));
    return {ok:r.ok&&json.ok!==false,status:r.status,json};
  }
  async function data(path,method='GET',body) {const r=await request(path,method,body);if(!r.ok)throw new Error(r.json.message||'No se pudo completar la acción.');return r.json.data??r.json;}
  async function command(action,input) {
    const fingerprint=JSON.stringify({action,input});if(!attempts.has(fingerprint))attempts.set(fingerprint,crypto.randomUUID());
    const result=await data('/api/profile-operators/index.php/command','POST',{action,input,command_id:attempts.get(fingerprint)});attempts.delete(fingerprint);return result;
  }
  function syncBusy(){document.querySelectorAll('#p-ag-operadores button').forEach(b=>b.disabled=pendingUi>0);}
  async function run(fn) {pendingUi++;syncBusy();try{await fn();}catch(e){notify(e.message,true);}finally{pendingUi--;syncBusy();}}
  const button=(text,fn,cls='btn btn-outline-secondary btn-sm')=>{const b=element('button',text,cls);b.type='button';b.addEventListener('click',()=>run(fn));return b;};
  const expiry=value=>new Date(value.replace(' ','T')+'Z').toLocaleString('es-MX',{dateStyle:'medium',timeStyle:'short'});
  function accordion(key) {document.querySelectorAll('[data-ops-accordion-item]').forEach(article=>{const active=article.dataset.opsAccordionItem===key;article.classList.toggle('is-active',active);article.querySelector('.mx-ag-ops-accordion-body')?.classList.toggle('d-none',!active);article.querySelector('button')?.setAttribute('aria-expanded',String(active));});}
  function resetEditor(){editing=null;if(!$('ag_ops_email'))return;$('ag_ops_email').value='';$('ag_ops_email').disabled=false;$('ag_ops_role').disabled=false;$('ag_ops_role').value='collaborator';$('ag_ops_form_title').textContent='Invitar operador';$('ag_ops_save_btn').textContent='Enviar invitación local';document.querySelectorAll('[data-operator-grant]').forEach(e=>e.checked=false);$('ag_ops_modal_error').classList.add('d-none');accordion('general');}
  function edit(record,kind){editing={record,kind};$('ag_ops_email').value=record.email_normalized||record.alias;$('ag_ops_email').disabled=true;$('ag_ops_role').value=record.role_code;$('ag_ops_role').disabled=true;$('ag_ops_form_title').textContent='Permisos · '+record.alias;$('ag_ops_save_btn').textContent=kind==='invitation'?'Reexpedir oferta':'Guardar permisos';document.querySelectorAll('[data-operator-grant]').forEach(e=>e.checked=record.capabilities.includes(e.dataset.operatorGrant));accordion('permissions');$('ag_ops_form_title').scrollIntoView({block:'center',behavior:'smooth'});}
  function band(record,kind){
    const revoked=record.status==='revoked';const card=element('article',undefined,'mx-ag-ops-operator-band');card.dataset.operatorId=record.membership_id||record.invitation_id;
    const head=element('button',undefined,'mx-ag-ops-band-head');head.type='button';head.disabled=pendingUi>0;head.setAttribute('aria-expanded','false');
    const avatar=element('span',record.alias.slice(0,2).toUpperCase(),'mx-ag-ops-avatar');avatar.setAttribute('aria-hidden','true');
    const summary=element('span',undefined,'mx-ag-ops-band-summary'),title=element('span',undefined,'mx-ag-ops-band-title');title.append(element('span',kind==='invitation'?'Invitación pendiente':statuses[record.status]||record.status,'mx-ag-ops-status-pill '+(record.status==='active'?'is-active':'is-paused')),element('strong',record.alias));summary.append(title,element('span',record.role_code==='administrator'?'Administrador · 1 plaza':'Operador · 1 plaza','mx-ag-ops-band-contact'));
    head.append(avatar,summary,element('span','expand_more','material-symbols-rounded mx-ag-ops-band-chevron'));
    const detail=element('div',undefined,'mx-ag-ops-band-panel d-none');detail.append(element('p','Concedidos: '+(record.capabilities.map(k=>labels[k]).join(' · ')||'Ninguno')));
    if(kind==='member')detail.append(element('p','Efectivos: '+(record.effective_capabilities.map(k=>labels[k]).join(' · ')||'Ninguno')));
    else detail.append(element('p','Vence: '+expiry(record.expires_at)));
    if(record.grant_status==='review_required')detail.append(element('p','Requiere revisión del nuevo titular.'));
    const actions=element('div',undefined,'mx-ag-ops-card-foot');
    if(!revoked){actions.append(button(kind==='invitation'?'Cambiar oferta':'Editar permisos',()=>edit(record,kind)));
      if(kind==='member'){actions.append(button(record.status==='suspended'?'Reactivar':'Suspender',async()=>{await command(record.status==='suspended'?'reactivate':'suspend',{membership_id:record.membership_id,expected_revision:record.revision});await refresh();notify('Estado actualizado.');}));actions.append(button('Revocar',async()=>{if(!confirm('¿Revocar el nombramiento y liberar la plaza?'))return;await command('revoke',{membership_id:record.membership_id,expected_revision:record.revision});await refresh();},'btn btn-outline-danger btn-sm'));}
      else actions.append(button('Cancelar invitación',async()=>{await command('cancel',{invitation_id:record.invitation_id});await refresh();}));
    }
    detail.append(actions);if(expanded.has(card.dataset.operatorId)){detail.classList.remove('d-none');card.classList.add('is-open');head.setAttribute('aria-expanded','true');}head.onclick=()=>{const open=detail.classList.contains('d-none');if(open)expanded.add(card.dataset.operatorId);else expanded.delete(card.dataset.operatorId);detail.classList.toggle('d-none',!open);card.classList.toggle('is-open',open);head.setAttribute('aria-expanded',String(open));};card.append(head,detail);return card;
  }
  async function ownerView(){
    const sequence=++overviewSequence;
    try{const fresh=await data('/api/profile-operators/index.php/overview');if(sequence!==overviewSequence)return;overview=fresh;}catch(e){for(const id of ['ag_ops_active_value','ag_ops_available_value','ag_ops_pending_value'])$(id).textContent='No disponible';throw e;}
    $('ag_ops_active_value').textContent=overview.occupied+'/2';$('ag_ops_available_value').textContent=overview.available;$('ag_ops_pending_value').textContent=overview.reserved;
    $('ag_ops_list').replaceChildren(...overview.members.filter(m=>m.status!=='revoked').map(m=>band(m,'member')),...overview.invitations.map(i=>band(i,'invitation')));
    const archived=overview.members.filter(m=>m.status==='revoked');$('ag_ops_archived_count').textContent=archived.length;$('ag_ops_archived_list').replaceChildren(...archived.map(m=>band(m,'member')));
    $('ag_ops_limit_notice').textContent=overview.available?'El titular no ocupa plaza. Puedes invitar hasta completar dos operadores.':'Las dos plazas están ocupadas o reservadas. Suspender conserva la plaza; revocar la libera.';
    // Seat authority is server-confirmed, including the conflict response to an invitation race.
    syncBusy();
    const events=await data('/api/profile-operators/index.php/audit');$('ag_ops_history_list').replaceChildren(...events.map(e=>{const metadata=JSON.parse(e.metadata_json).producer_metadata;return element('p',e.occurred_at_utc+' · '+e.real_actor_reference+' · '+(metadata.operator_action||metadata.capability||e.action)+' · '+e.outcome);}));
  }
  async function inbox(){const list=$('operator-inbox-list');list.replaceChildren();for(const item of state.inbox){const card=element('article',undefined,'mx-ag-ops-operator-band p-3');card.append(element('h6',item.display_name),element('p',item.capabilities.map(k=>labels[k]).join(' · ')||'Sin permisos ofrecidos'),element('p','Vence: '+expiry(item.expires_at)));if(item.opened_at===null)card.append(button('Abrir invitación',async()=>{offer=await data('/api/profile-operators/index.php/open-invitation','POST',{invitation_id:item.invitation_id});$('operator-offer-content').replaceChildren(element('h6',offer.physician),element('p',offer.capabilities.map(k=>labels[k]).join(' · ')||'Sin permisos ofrecidos'));$('operator-offer-error').textContent='';bootstrapModal('operator-offer-modal').show();}));else card.append(element('p','Mensaje abierto. Si cerraste la oferta sin responder, solicita una nueva invitación.'));list.append(card);}if(!state.inbox.length)list.append(element('p','No tienes invitaciones pendientes.'));}
  function bootstrapModal(id){return window.bootstrap.Modal.getOrCreateInstance($(id));}
  async function refresh(){state=await data('/api/profile-operators/index.php/bootstrap');ready=true;if(state.owner&&$('ag_ops_list'))await ownerView();await inbox();applyGates();window.dispatchEvent(new CustomEvent('mxmed:operator-grants'));}
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
    document.querySelectorAll('[data-ops-accordion-toggle]').forEach(b=>b.onclick=()=>accordion(b.dataset.opsAccordionToggle));
    $('ag_ops_archived_toggle')?.addEventListener('click',()=>{const open=$('ag_ops_archived_panel').classList.contains('d-none');$('ag_ops_archived_panel').classList.toggle('d-none',!open);$('ag_ops_archived_toggle').setAttribute('aria-expanded',String(open));});
    $('ag_ops_add_btn')?.addEventListener('click',resetEditor);$('ag_ops_form_cancel_btn')?.addEventListener('click',resetEditor);$('ag_ops_refresh')?.addEventListener('click',()=>run(refresh));
    $('ag_ops_save_btn')?.addEventListener('click',()=>run(async()=>{notify('Guardando cambios…');const caps=Array.from(document.querySelectorAll('[data-operator-grant]:checked'),e=>e.dataset.operatorGrant);if(!editing){const email=$('ag_ops_email');if(!email.reportValidity()||!email.value.trim())throw new Error('Ingresa el correo del invitado.');await command('invite',{email:email.value.trim(),role:$('ag_ops_role').value,capabilities:caps});}else{const {record,kind}=editing;await command(kind==='invitation'?'reissue':record.grant_status==='review_required'?'review':'grants',kind==='invitation'?{invitation_id:record.invitation_id,email:record.email_normalized,role:record.role_code,capabilities:caps}:{membership_id:record.membership_id,expected_revision:record.revision,capabilities:caps});}resetEditor();await refresh();notify('Cambio guardado y confirmado.');}));
    for(const action of ['accept','reject'])$('operator-offer-'+action).onclick=()=>run(async()=>{try{await command(action,{invitation_id:offer.invitation_id,token:offer.token,offer_revision:offer.offer_revision});offer=null;bootstrapModal('operator-offer-modal').hide();await refresh();if(state.profile)location.assign('/private-app.php?workspace=agenda#p-ag-admin');}catch(e){$('operator-offer-error').textContent=e.message;}});
    $('operator-offer-modal').addEventListener('hidden.bs.modal',()=>{offer=null;run(refresh);});
    if(state.owner){const history=button('Historial del equipo',()=>bootstrapModal('ag_ops_history_modal').show());$('ag_ops_limit_notice').after(history);}
    const observer=new MutationObserver(applyGates);observer.observe($('p-ag-admin')||$('viewport'),{childList:true,subtree:true});
    document.addEventListener('click',e=>{const b=e.target.closest(restrictedSelectors.join(','));if(b){e.preventDefault();e.stopImmediatePropagation();notify('No tienes autorización para realizar esta acción.',true);}},true);
    try { await refresh();resolveReady(); } catch(error) { rejectReady(error);throw error; }
  }));
  window.addEventListener('focus',()=>run(refresh));
})();
