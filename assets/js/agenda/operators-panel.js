/* Original Operators panel: one presentation/controller, configured by a server readiness decision. */
(() => {
  'use strict';
  const $=id=>document.getElementById(id);
  const labels={'agenda.appointments.read':'Consultar citas','agenda.appointments.create':'Crear citas','agenda.appointments.reschedule':'Reprogramar citas','agenda.appointments.cancel':'Cancelar citas','agenda.availability.read':'Consultar disponibilidad'};
  const statuses={active:'Activo',suspended:'Suspendido',revoked:'Revocado',pending:'Invitación pendiente',expired:'Invitación vencida',cancelled:'Invitación cancelada',rejected:'Invitación rechazada'};
  const legacyStatuses={active:'Activo',paused:'Pausado',pending:'Pendiente',archived:'Archivado'};
  const element=(tag,text,cls)=>{const e=document.createElement(tag);if(text!==undefined)e.textContent=text;if(cls)e.className=cls;return e;};
  const expiry=value=>{
    if(typeof value!=='string'||!value)return 'No disponible';
    const iso=value.replace(' ','T'),date=new Date(iso+(/(?:Z|[+-]\d{2}:\d{2})$/i.test(iso)?'':'Z'));
    return Number.isFinite(date.getTime())?date.toLocaleString('es-MX',{dateStyle:'medium',timeStyle:'short'}):'No disponible';
  };
  const maskEmail=value=>{const [name,domain]=String(value||'').split('@');return name&&domain?Array.from(name)[0]+'•••@'+domain:'Contacto no disponible';};
  const initials=value=>{
    const name=String(value||'').normalize('NFC');
    const parts=typeof Intl.Segmenter==='function'?Array.from(new Intl.Segmenter('es',{granularity:'grapheme'}).segment(name),s=>s.segment):Array.from(name);
    return parts.slice(0,2).join('').toLocaleUpperCase('es')||'?';
  };
  window.mxmedInitializeOperatorsPanel=(ui,service=null)=>{
    if(!$('p-ag-operadores'))return null;
    if(window.mxmedOperatorPanel)return window.mxmedOperatorPanel;
    let overview=null,editing=null,pendingUi=0,overviewSequence=0,detailSequence=0,auditEvents=[],auditReady=false;
    const aliasReady=ui.service_ready&&ui.can_manage&&ui.presentation_ready===true&&!!service;
    const expanded=new Set();
    const notify=(text,error=false)=>{const e=$('ag_ops_modal_error');e.textContent=text;e.classList.remove('d-none');e.classList.toggle('mx-ag-ops-modal-error',error);e.classList.toggle('alert',!error);e.classList.toggle('alert-success',!error);};
    const data=(...args)=>service.data(...args);
    const command=(...args)=>{if(!ui.can_manage||!service)throw new Error('La administración de operadores no está habilitada.');return service.command(...args);};
    const refresh=()=>service.refresh();
    const syncBusy=()=>{document.querySelectorAll('#p-ag-operadores button').forEach(b=>b.disabled=pendingUi>0||b.hasAttribute('data-ops-unavailable'));};
    const run=async fn=>{pendingUi++;syncBusy();try{await fn();}catch(e){notify(e.message,true);}finally{pendingUi--;syncBusy();}};
    const button=(text,fn,cls='btn btn-outline-secondary btn-sm')=>{const b=element('button',text,cls);b.type='button';b.addEventListener('click',()=>run(fn));return b;};
    const bootstrapModal=id=>window.bootstrap.Modal.getOrCreateInstance($(id));
  function accordion(key) {document.querySelectorAll('[data-ops-accordion-item]').forEach(article=>{const active=article.dataset.opsAccordionItem===key;article.classList.toggle('is-active',active);article.querySelector('.mx-ag-ops-accordion-body')?.classList.toggle('d-none',!active);article.querySelector('button')?.setAttribute('aria-expanded',String(active));});}
  function resetEditor(){editing=null;if(!$('ag_ops_email'))return;$('ag_ops_alias').value='';$('ag_ops_alias').disabled=!aliasReady;$('ag_ops_email').value='';$('ag_ops_email').disabled=false;$('ag_ops_role').disabled=false;$('ag_ops_role').value='collaborator';$('ag_ops_form_title').textContent='Invitar operador';$('ag_ops_save_btn').textContent='Enviar invitación';document.querySelectorAll('[data-operator-grant]').forEach(e=>{e.checked=false;e.disabled=false;});$('ag_ops_modal_error').classList.add('d-none');accordion('general');}
  function edit(record,kind){editing={record,kind};$('ag_ops_alias').value=record.personal_alias||'';$('ag_ops_alias').disabled=!aliasReady||kind==='member';$('ag_ops_email').value=record.email_normalized||record.alias;$('ag_ops_email').disabled=true;$('ag_ops_role').value=record.role_code;$('ag_ops_role').disabled=true;$('ag_ops_form_title').textContent='Permisos · '+record.alias;$('ag_ops_save_btn').textContent=kind==='invitation'?'Reexpedir oferta':'Guardar permisos';document.querySelectorAll('[data-operator-grant]').forEach(e=>{e.checked=record.capabilities.includes(e.dataset.operatorGrant);e.disabled=kind==='alias';});if(kind==='alias'){$('ag_ops_form_title').textContent='Alias del operador';$('ag_ops_save_btn').textContent='Guardar alias';$('ag_ops_identity_readiness').open=true;}accordion(kind==='alias'?'general':'permissions');$('ag_ops_form_title').scrollIntoView({block:'center',behavior:'smooth'});}
  function showHistory(record=null,kind=null){
    const events=(kind==='legacy'?(ui.legacy?.audit||[]):auditEvents).filter(e=>{
      if(!record)return true;
      if(kind==='legacy')return e.operator_id===record.operator_id;
      try{const m=JSON.parse(e.metadata_json).producer_metadata;return kind==='invitation'?m.invitation_id===record.invitation_id:m.membership_id===record.membership_id;}catch(_){return false;}
    });
    $('ag_ops_history_operator_name').textContent=record?(kind==='invitation'?maskEmail(record.email_normalized):record.alias):'Equipo';
    $('ag_ops_history_count').textContent=events.length+' acciones';
    $('ag_ops_history_list').replaceChildren(...events.map(e=>{
      if(e.action_label)return element('p',e.at+' · '+e.action_label);
      let metadata={};try{metadata=JSON.parse(e.metadata_json).producer_metadata||{};}catch(_){}
      // Retain the historical actor reference; never substitute a current seat occupant's alias.
      return element('p',e.occurred_at_utc+' · '+e.real_actor_reference+' · '+(metadata.operator_action||metadata.capability||e.action)+' · '+e.outcome);
    }));
    if(!events.length)$('ag_ops_history_list').append(element('p',auditReady?'No hay acciones disponibles para esta referencia en el historial consultado.':'El historial no está disponible por ahora.'));
    bootstrapModal('ag_ops_history_modal').show();
  }
  function band(record,kind){
    const legacy=kind==='legacy',invitation=kind==='invitation';
    // Only the canonical presentation confirms a team alias; an invitation contact does not.
    const name=legacy?record.alias:record.personal_alias||(invitation?maskEmail(record.email_normalized):'Operador sin alias');
    const position=record.presentation_version===1&&[1,2].includes(record.seat_position)?record.seat_position:null;
    const status=record.status||(invitation?'pending':'unknown'),revoked=status==='revoked';
    const card=element('article',undefined,'mx-ag-ops-operator-band');card.dataset.operatorId=record.membership_id||record.invitation_id;
    const head=element('button',undefined,'mx-ag-ops-band-head');head.type='button';head.disabled=pendingUi>0;head.setAttribute('aria-expanded','false');
    const avatar=element('span',initials(invitation?'?':name),'mx-ag-ops-avatar');avatar.setAttribute('aria-hidden','true');if(position)avatar.style.borderColor='var(--agenda-origin-operator-0'+position+')';
    const summary=element('span',undefined,'mx-ag-ops-band-summary'),title=element('span',undefined,'mx-ag-ops-band-title');title.append(element('span',legacy?(legacyStatuses[status]||'Estado desconocido'):statuses[status]||'Estado desconocido','mx-ag-ops-status-pill '+(status==='active'?'is-active':'is-paused')),element('strong',name));summary.append(title,element('span',legacy?'Registro anterior del consultorio':invitation?'Contacto invitado · aún no es integrante':(record.role_code==='administrator'?'Administrador':'Operador')+(revoked?' · histórico':' · 1 plaza'),'mx-ag-ops-band-contact'));
    head.append(avatar,summary,element('span','expand_more','material-symbols-rounded mx-ag-ops-band-chevron'));
    const detail=element('div',undefined,'mx-ag-ops-band-panel d-none');detail.id='ag_ops_profile_'+(++detailSequence);head.setAttribute('aria-controls',detail.id);
    const facts=element('dl',undefined,'row small mb-0');
    const fact=(label,value)=>{const dd=element('dd',undefined,'col-sm-7 mb-1 text-break');if(value instanceof Node)dd.append(value);else dd.textContent=value;facts.append(element('dt',label,'col-sm-5 mb-1'),dd);};
    fact(legacy?'Alias histórico':'Alias de equipo',legacy?record.alias:record.personal_alias||'Pendiente de definir');
    fact('Fotografía','Se muestran iniciales; la foto privada aún no está habilitada.');
    const seat=element('span',undefined,'mx-ag-origin-item');if(position){seat.append(element('span',undefined,'mx-ag-origin-dot mx-ag-origin-dot--operator-0'+position),document.createTextNode('Operador 0'+position+(record.presentation_released?' · asignación histórica':'')));}else seat.textContent='Asignación no disponible en esta consulta.';fact('Posición y color',seat);
    if(!legacy){
      fact('Tipo de integrante',record.role_code==='administrator'?'Administrador':'Operador');
      fact('Estado',statuses[status]||'No disponible');
      if(invitation){
        fact('Contacto invitado',maskEmail(record.email_normalized));
        fact('Invitación',expiry(record.created_at));fact('Vencimiento',expiry(record.expires_at));
        if(record.delivery_state)fact('Entrega',({queued:'Pendiente de envío',retry:'Pendiente de reintento',dispatching:'En proceso',provider_accepted:'Aceptado por el proveedor',failed:'No enviado',uncertain:'Requiere revisión',retired:'Retirado'})[record.delivery_state]||'No disponible');
        fact('Cuenta y contacto','La invitación no confirma una cuenta ni su aceptación.');
      }else{
        fact('Contacto verificado',typeof record.contact_verified==='boolean'?(record.contact_verified?'Sí':'No'):'No disponible');
        fact('Cuenta',({active:'Activa',disabled:'Deshabilitada',pending_verification:'Pendiente de verificación'})[record.account_status]||'No disponible');
        fact('Invitación',expiry(record.invited_at));fact('Aceptación',expiry(record.accepted_at));
      }
      fact('Último acceso','No disponible en esta consulta.');
    }
    detail.append(facts);
    if(!legacy)detail.append(element('p',(invitation?'Permisos ofrecidos: ':'Concedidos: ')+(record.capabilities.map(k=>labels[k]||'Permiso no disponible').join(' · ')||'Ninguno'),'small mb-0'));
    if(kind==='member')detail.append(element('p','Efectivos: '+(record.effective_capabilities.map(k=>labels[k]||'Permiso no disponible').join(' · ')||'Ninguno'),'small mb-0'));
    if(legacy)detail.append(element('p','Conservado para consulta. Este registro anterior no confirma un acceso vigente ni permite cambiar permisos.'));
    if(record.grant_status==='review_required')detail.append(element('p','Requiere revisión del nuevo titular.'));
    const actions=element('div',undefined,'mx-ag-ops-card-foot');
    if(!legacy)detail.append(element('p',invitation?(status==='pending'?'Reserva una plaza hasta su vencimiento. Aún no tiene acceso.':'Esta invitación ya no reserva una plaza.'):revoked?'Fuera del equipo. Su historial se conserva y la plaza está libre.':status==='suspended'?'Acceso pausado. Conserva su plaza y no puede utilizar los permisos.':'Los permisos efectivos dependen del estado actual de la cuenta, del nombramiento y del plan.','small mb-0'));
    if(ui.can_manage&&service&&!legacy){
      const future=element('details',undefined,'small');future.append(element('summary',aliasReady?'Fotografía · pendiente de habilitación':'Alias y fotografía · pendientes de habilitación'));
      for(const text of [...(aliasReady?[]:['Editar alias']),'Cambiar foto','Quitar foto']){const b=element('button',text,'btn btn-outline-secondary btn-sm me-1 mt-1');b.type='button';b.disabled=true;b.setAttribute('data-ops-unavailable','');future.append(b);}
      if(!revoked&&!invitation)detail.append(future);
    }
    if(aliasReady&&!legacy&&!invitation&&status==='active'&&record.presentation_id)actions.append(button('Editar alias',()=>edit(record,'alias')));
    if(ui.can_manage&&service&&!revoked&&!legacy&&(kind==='member'&&['active','suspended'].includes(status)||invitation&&status==='pending')){actions.append(button(invitation?'Cambiar oferta / reexpedir':'Editar permisos',()=>edit(record,kind)));
      if(kind==='member'){actions.append(button(record.status==='suspended'?'Reactivar':'Suspender',async()=>{await command(record.status==='suspended'?'reactivate':'suspend',{membership_id:record.membership_id,expected_revision:record.revision});await refresh();notify('Estado actualizado.');}));actions.append(button('Revocar',async()=>{if(!confirm('¿Revocar el nombramiento y liberar la plaza?'))return;await command('revoke',{membership_id:record.membership_id,expected_revision:record.revision});await refresh();},'btn btn-outline-danger btn-sm'));}
      else actions.append(button('Cancelar invitación',async()=>{await command('cancel',{invitation_id:record.invitation_id});await refresh();}));
    }
    const history=element('button','Ver historial','btn btn-outline-secondary btn-sm');history.type='button';history.onclick=()=>showHistory(record,kind);actions.append(history);
    detail.append(actions);if(expanded.has(card.dataset.operatorId)){detail.classList.remove('d-none');card.classList.add('is-open');head.setAttribute('aria-expanded','true');}head.onclick=()=>{const open=detail.classList.contains('d-none');if(open)expanded.add(card.dataset.operatorId);else expanded.delete(card.dataset.operatorId);detail.classList.toggle('d-none',!open);card.classList.toggle('is-open',open);head.setAttribute('aria-expanded',String(open));};card.append(head,detail);return card;
  }
  function renderBrowserReferences(){
      let local=[],localNotice='',localCount=0;
      try{const raw=localStorage.getItem('mxmed.agenda.operators.state.v1');if(raw){
        if(raw.length>=524288)throw new Error('cache too large');
        const parsed=JSON.parse(raw),rows=[...(Array.isArray(parsed?.operators)?parsed.operators:[]),...(Array.isArray(parsed?.archived_operators)?parsed.archived_operators:[])];
        localCount=rows.length;
        local=rows.slice(0,500).map(r=>({operator_id:typeof r?.operator_id==='string'?r.operator_id:'',alias:typeof r?.alias==='string'&&r.alias?r.alias:'Registro local',status:typeof r?.status==='string'?r.status:'unknown'}));
        if(localCount>500)localNotice='Se muestran las primeras 500 referencias. El historial completo sigue guardado en este navegador.';
      }}catch(_){localNotice='No pudimos mostrar las referencias de este navegador. Los datos guardados no se han modificado.';}
      $('ag_ops_browser_history').classList.toggle('d-none',!localCount&&!localNotice);
      $('ag_ops_browser_count').textContent=String(localCount);$('ag_ops_browser_notice').textContent=localNotice;
      $('ag_ops_browser_list').replaceChildren(...local.map((r,index)=>{const row=element('li',undefined,'list-group-item px-0 py-1');row.dataset.localReference=String(index);row.dataset.operatorId=r.operator_id;row.append(element('strong',r.alias),document.createTextNode(' · '+(legacyStatuses[r.status]||'Estado desconocido')));return row;}));
  }
  function renderDeployedLegacy(){
    if(!ui.legacy)return;
    const holder=$('ag_ops_legacy_summary');holder.replaceChildren();
    const archive=element('details',undefined,'border rounded p-2 mt-2');archive.id='ag_ops_deployed_legacy';archive.append(element('summary','Registros anteriores · sólo consulta'));
    archive.append(element('p','Estos registros anteriores se conservan para consulta. No confirman una cuenta vigente ni ocupan plazas del equipo.','small'));
    const records=ui.legacy.records||[];
    for(const r of records)archive.append(band({...r,membership_id:r.source+':'+r.operator_id,capabilities:[]},'legacy'));
    if(ui.legacy.read_status!=='ready')archive.append(element('p','No pudimos consultar el historial anterior.'));
    holder.append(archive);
  }
  async function ownerView(){
    const sequence=++overviewSequence;
    try{const fresh=await data('/api/profile-operators/index.php/overview');if(sequence!==overviewSequence)return;overview=fresh;}catch(e){$('ag_ops_empty_team').classList.add('d-none');for(const id of ['ag_ops_active_value','ag_ops_available_value','ag_ops_pending_value'])$(id).textContent='No disponible';throw e;}
    $('ag_ops_active_value').textContent=(overview.occupied+overview.reserved)+'/2';$('ag_ops_available_value').textContent=overview.available;$('ag_ops_pending_value').textContent=overview.reserved;
    $('ag_ops_empty_team').classList.toggle('d-none',overview.occupied!==0||overview.reserved!==0);
    $('ag_ops_list').replaceChildren(...overview.members.filter(m=>m.status!=='revoked').map(m=>band(m,'member')),...overview.invitations.map(i=>band(i,'invitation')));
    const archived=overview.members.filter(m=>m.status==='revoked');$('ag_ops_archived_count').textContent=archived.length;$('ag_ops_archived_list').replaceChildren(...archived.map(m=>band(m,'member')));
    $('ag_ops_limit_notice').textContent=overview.available?'El titular no ocupa plaza. Puedes invitar hasta completar dos operadores.':'Las dos plazas están ocupadas o reservadas. Suspender conserva la plaza; revocar la libera.';
    renderDeployedLegacy();
    // Seat authority is server-confirmed, including the conflict response to an invitation race.
    syncBusy();
    auditReady=false;auditEvents=await data('/api/profile-operators/index.php/audit');auditReady=true;
  }

    document.querySelectorAll('#p-ag-operadores [data-ops-accordion-toggle]').forEach(b=>b.addEventListener('click',()=>accordion(b.dataset.opsAccordionToggle)));
    $('ag_ops_archived_toggle').addEventListener('click',()=>{const open=$('ag_ops_archived_panel').classList.contains('d-none');$('ag_ops_archived_panel').classList.toggle('d-none',!open);$('ag_ops_archived_toggle').setAttribute('aria-expanded',String(open));});
    const history=element('button','Historial del equipo','btn btn-outline-secondary btn-sm');history.type='button';history.onclick=()=>showHistory();$('ag_ops_limit_notice').after(history);
    for(const type of ['dragover','drop'])$('ag_ops_identity_readiness').addEventListener(type,event=>event.preventDefault());
    renderBrowserReferences();
    if(ui.service_ready&&ui.can_manage&&service){
      $('ag_ops_empty_invite').addEventListener('click',()=>{resetEditor();$('ag_ops_email').focus();});
      $('ag_ops_add_btn').addEventListener('click',resetEditor);$('ag_ops_form_cancel_btn').addEventListener('click',resetEditor);$('ag_ops_refresh').addEventListener('click',()=>run(refresh));
      $('ag_ops_save_btn').addEventListener('click',()=>run(async()=>{notify('Guardando cambios…');const caps=Array.from(document.querySelectorAll('[data-operator-grant]:checked'),e=>e.dataset.operatorGrant);if(editing?.kind==='alias'){await command('rename',{membership_id:editing.record.membership_id,expected_presentation_revision:editing.record.presentation_revision,alias:$('ag_ops_alias').value});}else if(!editing){const email=$('ag_ops_email');if(!email.reportValidity()||!email.value.trim())throw new Error('Ingresa el correo del invitado.');await command('invite',{email:email.value.trim(),role:$('ag_ops_role').value,capabilities:caps,...(aliasReady?{alias:$('ag_ops_alias').value}:{})});}else{const {record,kind}=editing;await command(kind==='invitation'?'reissue':record.grant_status==='review_required'?'review':'grants',kind==='invitation'?{invitation_id:record.invitation_id,email:record.email_normalized,role:record.role_code,capabilities:caps,...(aliasReady?{alias:$('ag_ops_alias').value}:{})}:{membership_id:record.membership_id,expected_revision:record.revision,capabilities:caps});}resetEditor();await refresh();notify('Cambio guardado y confirmado.');}));
    }else{
      const legacy=ui.legacy;
      // Do not modify, migrate or treat browser cache as Identity authority.
      // A matching ID, alias or email in unverified storage is not proof of origin.
      // Only server records become primary cards; local references remain discoverable separately.
      const records=(legacy?.records||[]).map(r=>({...r,membership_id:r.source+':'+r.operator_id,alias:r.alias||r.operator_id,capabilities:[]}));
      $('ag_ops_list').replaceChildren(...records.filter(r=>r.status!=='archived').map(r=>band(r,'legacy')));
      const archived=records.filter(r=>r.status==='archived');$('ag_ops_archived_list').replaceChildren(...archived.map(r=>band(r,'legacy')));$('ag_ops_archived_count').textContent=legacy?.read_status==='ready'?String(archived.length):'No disponible';
      const c=legacy?.counts;
      auditEvents=legacy?.audit||[];auditReady=legacy?.read_status==='ready';
      $('ag_ops_legacy_summary').textContent=c?`Registros anteriores del consultorio: ${c.active} activos, ${c.paused} pausados, ${c.pending} pendientes y ${c.archived} archivados.`:'No pudimos consultar los registros anteriores del consultorio. Intenta de nuevo más tarde.';
      $('ag_ops_limit_notice').textContent='El límite es de dos operadores además del titular. El cupo y las invitaciones estarán disponibles cuando se habilite la administración. Los registros anteriores no confirman plazas ocupadas.';
      $('ag_ops_history_list').replaceChildren(...(legacy?.audit||[]).map(e=>element('p',e.at+' · '+e.action_label+' · '+(legacyStatuses[records.find(r=>r.operator_id===e.operator_id)?.status]||'Registro anterior'))));
      if(!legacy?.audit?.length)$('ag_ops_history_list').append(element('p','Sin historial disponible para consulta.'));
      $('ag_ops_form_cancel_btn').disabled=true;
    }
    const panel={refresh:()=>ui.service_ready&&ui.can_manage&&service?ownerView():Promise.reject(new Error('Servicio de operadores no habilitado.'))};window.mxmedOperatorPanel=panel;return panel;
  };
  const mount=()=>{const node=$('mxmed-private-bootstrap');if(!node)return;const ui=JSON.parse(node.textContent).operators_ui;if(ui&&!ui.service_ready)window.mxmedInitializeOperatorsPanel(ui);};
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',mount,{once:true});else mount();
})();
