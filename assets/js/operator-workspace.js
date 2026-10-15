(() => {
  'use strict';
  const $ = (id) => document.getElementById(id);
  const labels = {'agenda.appointments.read':'Consultar citas','agenda.appointments.create':'Crear citas','agenda.appointments.reschedule':'Reprogramar citas','agenda.appointments.cancel':'Cancelar citas','agenda.availability.read':'Consultar disponibilidad'};
  const states = {active:'Activo',suspended:'Suspendido',revoked:'Revocado',pending:'Invitación pendiente',review_required:'Revisión del nuevo titular requerida'};
  let csrf='', state=null, overview=null, editing=null, offer=null, rescheduling=null, createAttempt=null;
  const pending = new Map();
  const node = (tag,text,cls) => {const e=document.createElement(tag);if(text!==undefined)e.textContent=text;if(cls)e.className=cls;return e;};
  const message = (text,error=false,target='op02-status') => {$(target).textContent=text;$(target).className=error?'error':'success';};
  const uuid = () => crypto.randomUUID();
  const expiry = value => new Date(value.replace(' ','T')+'Z').toLocaleString('es-MX',{dateStyle:'medium',timeStyle:'short'});
  async function api(path,method='GET',body,extra={}) {
    const response=await fetch(path,{method,credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-Token':csrf,'X-Grant-Revision':String(state?.grant_revision??0),...extra},...(body===undefined?{}:{body:JSON.stringify(body)})});
    const payload=await response.json().catch(()=>({}));
    if(response.status===401){location.assign('/public/identity/acceso.php?state=expired');throw new Error('La sesión venció. Inicia sesión de nuevo.');}
    if(!response.ok||payload.ok===false)throw new Error(payload.message||'No se completó la acción. Intenta de nuevo.');
    return payload.data??payload;
  }
  async function session() {const s=await api('/api/identity/index.php/current-session');csrf=s.csrf_token;}
  async function command(action,input) {
    const key=JSON.stringify({action,input});if(!pending.has(key))pending.set(key,uuid());
    const result=await api('/api/profile-operators/index.php/command','POST',{action,input,command_id:pending.get(key)});pending.delete(key);return result;
  }
  function button(label,fn){const b=node('button',label,'btn btn-outline-primary');b.type='button';b.onclick=()=>run(fn);return b;}
  async function run(fn) {try{await fn();}catch(e){message(e.message,true);}}
  function granted(key){return state?.capabilities[key]===true;}
  function checks(keys=[]) {for(const [key,label] of Object.entries(labels)){const input=$('grant-'+key);if(input)input.checked=keys.includes(key);}}
  function selected() {return Object.keys(labels).filter(key=>$('grant-'+key).checked);}
  function panel(id) {
    if(id==='p-ag-operadores'&&!state?.owner)id='op02-inbox';
    if(id==='p-ag-admin'&&!Object.values(state?.capabilities??{}).some(Boolean))id='op02-inbox';
    for(const section of ['p-ag-operadores','p-ag-admin','op02-inbox'])$(section).hidden=section!==id;
    document.querySelectorAll('[data-panel]').forEach(b=>b.classList.toggle('active',b.dataset.panel===id));
    if(location.hash!==`#${id}`)history.replaceState(null,'',`#${id}`);
  }
  function resetEditor(){editing=null;$('ag_ops_form').reset();checks();$('op02-email').disabled=false;$('op02-email').required=true;$('op02-role').disabled=false;$('ag_ops_form_title').textContent='Invitar operador';$('op02-save').textContent='Enviar invitación local';$('op02-form-status').textContent='';}
  function edit(record,kind){editing={record,kind};checks(record.capabilities);$('op02-email').value=record.email_normalized||'';$('op02-email').disabled=true;$('op02-email').required=false;$('op02-role').value=record.role_code;$('op02-role').disabled=true;$('ag_ops_form_title').textContent=`Permisos · ${record.alias}`;$('op02-save').textContent=kind==='invitation'?'Reexpedir oferta':'Guardar permisos';$('ag_ops_form').scrollIntoView({behavior:'smooth',block:'center'});$('op02-form-status').textContent=kind==='invitation'?'La oferta anterior quedará cancelada. La persona deberá aceptar la nueva.':'La nueva revisión se aplicará en el servidor.';}
  async function reload() {
    state=await api('/api/profile-operators/index.php/bootstrap');$('op02-actor').textContent=state.alias;
    $('op02-owner-nav').hidden=!state.owner;$('op02-agenda-nav').hidden=!Object.values(state.capabilities).some(Boolean);
    $('op02-create').hidden=!granted('agenda.appointments.create');$('op02-load').hidden=!granted('agenda.appointments.read');$('op02-availability').hidden=!granted('agenda.availability.read');
    $('op02-action-locator').hidden=granted('agenda.appointments.read')||(!granted('agenda.appointments.cancel')&&!granted('agenda.appointments.reschedule'));
    $('op02-locator-cancel').hidden=!granted('agenda.appointments.cancel');$('op02-locator-reschedule').hidden=!granted('agenda.appointments.reschedule');
    $('op02-my-permissions').textContent=Object.keys(labels).filter(granted).map(k=>labels[k]).join(' · ')||'No tienes permisos efectivos de Agenda.';
    for(const [id,rows,key,text] of [['op02-office',state.offices,'consultorio_id','titulo'],['op02-patient',state.booking_subjects,'patient_id','display_name']]){
      const current=$(id).value;$(id).replaceChildren(...rows.map(r=>{const option=node('option',r[text]);option.value=r[key];return option;}));if([...$(id).options].some(o=>o.value===current))$(id).value=current;
    }
    $('op02-messages').replaceChildren();
    for(const i of state.inbox){const card=node('article',undefined,'op02-card');card.append(node('h2',i.display_name),node('p',i.capabilities.map(k=>labels[k]).join(' · ')||'Sin permisos ofrecidos'),node('p',`Vigencia: ${expiry(i.expires_at)}`));if(i.opened_at===null)card.append(button('Abrir invitación',()=>openInvitation(i.invitation_id)));else card.append(node('p','Mensaje abierto. Si ya no tienes la oferta visible, solicita una nueva invitación al titular.'));$('op02-messages').append(card);}
    if(!state.inbox.length)$('op02-messages').append(node('p','No tienes invitaciones pendientes.'));
    if(state.owner){$('op02-profile').textContent=state.profile.name;await ownerView();}
    panel(location.hash.slice(1)||(state.owner?'p-ag-operadores':Object.values(state.capabilities).some(Boolean)?'p-ag-admin':'op02-inbox'));
  }
  async function ownerView(){
    try{overview=await api('/api/profile-operators/index.php/overview');}catch(e){for(const id of ['ag_ops_active_value','ag_ops_pending_value','ag_ops_available_value'])$(id).textContent='No disponible';throw e;}
    $('ag_ops_active_value').textContent=overview.occupied;$('ag_ops_pending_value').textContent=overview.reserved;$('ag_ops_available_value').textContent=overview.available;
    $('ag_ops_list').replaceChildren();
    for(const member of overview.members){const card=node('article',undefined,'op02-card');card.dataset.membership=member.membership_id;card.append(node('h3',member.alias),node('span',states[member.status]||member.status,'op02-state'),node('p',`Concedidos: ${member.capabilities.map(k=>labels[k]).join(' · ')||'Ninguno'}`),node('p',`Efectivos: ${member.effective_capabilities.map(k=>labels[k]).join(' · ')||'Ninguno'}`));
      if(member.grant_status==='review_required')card.append(node('p',states.review_required));
      if(member.status!=='revoked'){const actions=node('div',undefined,'op02-actions');actions.append(button('Editar permisos',()=>edit(member,'member')));
        actions.append(button(member.status==='suspended'?'Reactivar':'Suspender',async()=>{await command(member.status==='suspended'?'reactivate':'suspend',{membership_id:member.membership_id,expected_revision:member.revision});await reload();message('Estado actualizado.');}));
        actions.append(button('Revocar',async()=>{if(!confirm('¿Revocar este nombramiento y liberar la plaza?'))return;await command('revoke',{membership_id:member.membership_id,expected_revision:member.revision});await reload();message('Operador revocado.');}));card.append(actions);}
      $('ag_ops_list').append(card);
    }
    for(const i of overview.invitations){const card=node('article',undefined,'op02-card');card.dataset.invitation=i.invitation_id;card.append(node('h3',i.alias),node('span','Invitación pendiente · reserva 1 plaza','op02-state'),node('p',i.capabilities.map(k=>labels[k]).join(' · ')||'Sin permisos ofrecidos'),node('p',`Vence: ${expiry(i.expires_at)}`));const actions=node('div',undefined,'op02-actions');actions.append(button('Cambiar oferta',()=>edit(i,'invitation')),button('Cancelar invitación',async()=>{await command('cancel',{invitation_id:i.invitation_id});await reload();message('Invitación cancelada.');}));card.append(actions);$('ag_ops_list').append(card);}
    if(!overview.members.length&&!overview.invitations.length)$('ag_ops_list').append(node('p','Aún no hay operadores ni invitaciones. Puedes nombrar hasta dos personas.'));
    const events=await api('/api/profile-operators/index.php/audit');$('op02-audit').replaceChildren(...events.map(e=>{const meta=JSON.parse(e.metadata_json).producer_metadata;return node('p',`${e.occurred_at_utc} · ${e.real_actor_reference} · ${meta.operator_action||meta.capability||e.action} · ${e.outcome}`);}));
  }
  async function openInvitation(id){offer=await api('/api/profile-operators/index.php/open-invitation','POST',{invitation_id:id});$('op02-offer').replaceChildren(node('h3',offer.physician),node('p',`Perfil: ${offer.profile_id}`),node('p',offer.capabilities.map(k=>labels[k]).join(' · ')||'Sin permisos ofrecidos'));$('op02-offer-status').textContent='';$('op02-invitation').showModal();}
  async function respond(action){try{await command(action,{invitation_id:offer.invitation_id,token:offer.token,offer_revision:offer.offer_revision});offer=null;$('op02-invitation').close();await reload();message(action==='accept'?'Invitación aceptada. Tus permisos están disponibles.':'Invitación rechazada.');}catch(e){message(e.message,true,'op02-offer-status');}}
  const dt = value=>value.replace('T',' ')+(value.length===16?':00':'');
  const fromSql = value=>value.replace(' ','T').slice(0,16);
  async function loadAppointments(){const date=$('op02-date').value;const next=new Date(`${date}T12:00:00`);next.setDate(next.getDate()+1);const to=next.toISOString().slice(0,10);const rows=await api('/api/agenda/index.php/appointments?'+new URLSearchParams({from:`${date} 00:00:00`,to:`${to} 00:00:00`}));$('op02-appointments').replaceChildren();for(const row of rows){const card=node('article',undefined,'op02-card');card.append(node('h3',`${row.start_at} — ${row.end_at.slice(11,16)}`),node('p',`Cita ${row.appointment_id} · ${row.status}`));const actions=node('div',undefined,'op02-actions');if(granted('agenda.appointments.reschedule')&&!['canceled','cancelled'].includes(row.status))actions.append(button('Reprogramar',()=>prepareReschedule(row)));if(granted('agenda.appointments.cancel')&&!['canceled','cancelled'].includes(row.status))actions.append(button('Cancelar cita',()=>cancelAppointment(row)));card.append(actions);$('op02-appointments').append(card);}if(!rows.length)$('op02-appointments').append(node('p','No hay citas para esta fecha.'));}
  function prepareReschedule(row){rescheduling=row;$('op02-new-start').value=fromSql(row.start_at);$('op02-new-end').value=fromSql(row.end_at);$('op02-reschedule').hidden=false;$('op02-reschedule').scrollIntoView({behavior:'smooth',block:'center'});}
  async function cancelAppointment(row){if(!confirm('¿Cancelar esta cita?'))return;await api(`/api/agenda/index.php/appointments/${encodeURIComponent(row.appointment_id)}/cancel`,'POST',{expected_start_at:row.start_at});message('Cita cancelada.');if(granted('agenda.appointments.read'))await loadAppointments();}
  document.querySelectorAll('[data-panel]').forEach(b=>b.onclick=()=>panel(b.dataset.panel));
  for(const [key,label]of Object.entries(labels)){const l=node('label'),i=document.createElement('input');i.type='checkbox';i.id='grant-'+key;i.name=key;l.append(i,document.createTextNode(label));$('op02-grants').append(l);}
  $('ag_ops_add_btn').onclick=()=>{resetEditor();$('ag_ops_form').scrollIntoView({behavior:'smooth'});$('op02-email').focus();};$('op02-clear').onclick=resetEditor;$('op02-refresh').onclick=()=>run(async()=>{await reload();message('Equipo actualizado.');});
  $('ag_ops_form').onsubmit=e=>{e.preventDefault();run(async()=>{const caps=selected();if(!editing)await command('invite',{email:$('op02-email').value.trim(),role:$('op02-role').value,capabilities:caps});else if(editing.kind==='invitation')await command('reissue',{invitation_id:editing.record.invitation_id,email:editing.record.email_normalized,capabilities:caps,role:editing.record.role_code});else await command(editing.record.grant_status==='review_required'?'review':'grants',{membership_id:editing.record.membership_id,expected_revision:editing.record.revision,capabilities:caps});resetEditor();await reload();message('Cambio guardado y confirmado.','', 'op02-form-status');});};
  $('op02-accept').onclick=()=>respond('accept');$('op02-reject').onclick=()=>respond('reject');$('op02-offer-close').onclick=()=>{offer=null;$('op02-invitation').close();run(reload);};
  $('op02-invitation').addEventListener('cancel',()=>{offer=null;run(reload);});
  $('op02-create').onsubmit=e=>{e.preventDefault();run(async()=>{const body={consultorio_id:$('op02-office').value,patient_id:$('op02-patient').value,start_at:dt($('op02-start').value),end_at:dt($('op02-end').value),modality:'in_person'};const hash=JSON.stringify(body);if(createAttempt?.hash!==hash)createAttempt={hash,key:uuid()};const r=await api('/api/agenda/index.php/appointments','POST',body,{'Idempotency-Key':createAttempt.key});createAttempt=null;message(`Cita creada: ${r.appointment_id}`,false,'op02-create-result');if(granted('agenda.appointments.read'))await loadAppointments();});};
  $('op02-load').onclick=()=>run(loadAppointments);$('op02-availability').onclick=()=>run(async()=>{const data=await api('/api/agenda/index.php/availability?'+new URLSearchParams({consultorio_id:state.offices[0]?.consultorio_id||'',date:$('op02-date').value}));$('op02-availability-result').textContent=`${data.slots?.length??0} horarios disponibles para esta fecha.`;});
  $('op02-reschedule').onsubmit=e=>{e.preventDefault();run(async()=>{await api(`/api/agenda/index.php/appointments/${encodeURIComponent(rescheduling.appointment_id)}/reschedule`,'PATCH',{from_start_at:rescheduling.start_at,from_end_at:rescheduling.end_at,to_start_at:dt($('op02-new-start').value),to_end_at:dt($('op02-new-end').value)});$('op02-reschedule').hidden=true;message('Cita reprogramada.');if(granted('agenda.appointments.read'))await loadAppointments();});};$('op02-reschedule-close').onclick=()=>{$('op02-reschedule').hidden=true;};
  function locator(){return{appointment_id:$('op02-locator-id').value.trim(),start_at:dt($('op02-locator-start').value),end_at:dt($('op02-locator-end').value)};}
  $('op02-locator-reschedule').onclick=()=>prepareReschedule(locator());$('op02-locator-cancel').onclick=()=>run(()=>cancelAppointment(locator()));
  $('op02-logout').onclick=()=>run(async()=>{await api('/api/identity/index.php/logout','POST',{});location.assign('/public/identity/acceso.php?state=logged-out');});
  $('op02-date').value=new Date().toISOString().slice(0,10);
  window.addEventListener('focus',()=>{if(state)run(async()=>{await session();await reload();});});
  run(async()=>{await session();await reload();message('Sesión y permisos vigentes.');});
})();
