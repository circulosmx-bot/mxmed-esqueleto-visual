/* Original Operators panel: one presentation/controller, configured by a server readiness decision. */
(() => {
  'use strict';
  const $=id=>document.getElementById(id);
  const labels={'agenda.appointments.read':'Consultar citas','agenda.appointments.create':'Crear citas','agenda.appointments.reschedule':'Reprogramar citas','agenda.appointments.cancel':'Cancelar citas','agenda.availability.read':'Consultar disponibilidad'};
  const statuses={active:'Activo',suspended:'Suspendido',revoked:'Revocado',pending:'Invitación pendiente'};
  const legacyStatuses={active:'Activo',paused:'Pausado',pending:'Pendiente',archived:'Archivado'};
  const element=(tag,text,cls)=>{const e=document.createElement(tag);if(text!==undefined)e.textContent=text;if(cls)e.className=cls;return e;};
  const expiry=value=>new Date(value.replace(' ','T')+'Z').toLocaleString('es-MX',{dateStyle:'medium',timeStyle:'short'});
  window.mxmedInitializeOperatorsPanel=(ui,service=null)=>{
    if(!$('p-ag-operadores'))return null;
    if(window.mxmedOperatorPanel)return window.mxmedOperatorPanel;
    let overview=null,editing=null,pendingUi=0,overviewSequence=0;
    const expanded=new Set();
    const notify=(text,error=false)=>{const e=$('ag_ops_modal_error');e.textContent=text;e.classList.remove('d-none');e.classList.toggle('mx-ag-ops-modal-error',error);e.classList.toggle('alert',!error);e.classList.toggle('alert-success',!error);};
    const data=(...args)=>service.data(...args);
    const command=(...args)=>{if(!ui.can_manage||!service)throw new Error('La administración de operadores no está habilitada.');return service.command(...args);};
    const refresh=()=>service.refresh();
    const syncBusy=()=>{document.querySelectorAll('#p-ag-operadores button').forEach(b=>b.disabled=pendingUi>0);};
    const run=async fn=>{pendingUi++;syncBusy();try{await fn();}catch(e){notify(e.message,true);}finally{pendingUi--;syncBusy();}};
    const button=(text,fn,cls='btn btn-outline-secondary btn-sm')=>{const b=element('button',text,cls);b.type='button';b.addEventListener('click',()=>run(fn));return b;};
    const bootstrapModal=id=>window.bootstrap.Modal.getOrCreateInstance($(id));
  function accordion(key) {document.querySelectorAll('[data-ops-accordion-item]').forEach(article=>{const active=article.dataset.opsAccordionItem===key;article.classList.toggle('is-active',active);article.querySelector('.mx-ag-ops-accordion-body')?.classList.toggle('d-none',!active);article.querySelector('button')?.setAttribute('aria-expanded',String(active));});}
  function resetEditor(){editing=null;if(!$('ag_ops_email'))return;$('ag_ops_email').value='';$('ag_ops_email').disabled=false;$('ag_ops_role').disabled=false;$('ag_ops_role').value='collaborator';$('ag_ops_form_title').textContent='Invitar operador';$('ag_ops_save_btn').textContent='Enviar invitación';document.querySelectorAll('[data-operator-grant]').forEach(e=>e.checked=false);$('ag_ops_modal_error').classList.add('d-none');accordion('general');}
  function edit(record,kind){editing={record,kind};$('ag_ops_email').value=record.email_normalized||record.alias;$('ag_ops_email').disabled=true;$('ag_ops_role').value=record.role_code;$('ag_ops_role').disabled=true;$('ag_ops_form_title').textContent='Permisos · '+record.alias;$('ag_ops_save_btn').textContent=kind==='invitation'?'Reexpedir oferta':'Guardar permisos';document.querySelectorAll('[data-operator-grant]').forEach(e=>e.checked=record.capabilities.includes(e.dataset.operatorGrant));accordion('permissions');$('ag_ops_form_title').scrollIntoView({block:'center',behavior:'smooth'});}
  function band(record,kind){
    const legacy=kind==='legacy';const revoked=record.status==='revoked';const card=element('article',undefined,'mx-ag-ops-operator-band');card.dataset.operatorId=record.membership_id||record.invitation_id;
    const head=element('button',undefined,'mx-ag-ops-band-head');head.type='button';head.disabled=pendingUi>0;head.setAttribute('aria-expanded','false');
    const avatar=element('span',record.alias.slice(0,2).toUpperCase(),'mx-ag-ops-avatar');avatar.setAttribute('aria-hidden','true');
    const summary=element('span',undefined,'mx-ag-ops-band-summary'),title=element('span',undefined,'mx-ag-ops-band-title');title.append(element('span',legacy?(legacyStatuses[record.status]||'Estado desconocido'):kind==='invitation'?'Invitación pendiente':statuses[record.status]||record.status,'mx-ag-ops-status-pill '+(record.status==='active'?'is-active':'is-paused')),element('strong',record.alias));summary.append(title,element('span',legacy?'Registro anterior del consultorio':record.role_code==='administrator'?'Administrador · 1 plaza':'Operador · 1 plaza','mx-ag-ops-band-contact'));
    head.append(avatar,summary,element('span','expand_more','material-symbols-rounded mx-ag-ops-band-chevron'));
    const detail=element('div',undefined,'mx-ag-ops-band-panel d-none');if(!legacy)detail.append(element('p','Concedidos: '+(record.capabilities.map(k=>labels[k]).join(' · ')||'Ninguno')));
    if(kind==='member')detail.append(element('p','Efectivos: '+(record.effective_capabilities.map(k=>labels[k]).join(' · ')||'Ninguno')));
    else if(!legacy)detail.append(element('p','Vence: '+expiry(record.expires_at)));
    if(legacy)detail.append(element('p','Conservado para consulta. Este registro anterior no confirma un acceso vigente ni permite cambiar permisos.'));
    if(record.grant_status==='review_required')detail.append(element('p','Requiere revisión del nuevo titular.'));
    const actions=element('div',undefined,'mx-ag-ops-card-foot');
    if(!revoked&&!legacy){actions.append(button(kind==='invitation'?'Cambiar oferta':'Editar permisos',()=>edit(record,kind)));
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

    document.querySelectorAll('#p-ag-operadores [data-ops-accordion-toggle]').forEach(b=>b.addEventListener('click',()=>accordion(b.dataset.opsAccordionToggle)));
    $('ag_ops_archived_toggle').addEventListener('click',()=>{const open=$('ag_ops_archived_panel').classList.contains('d-none');$('ag_ops_archived_panel').classList.toggle('d-none',!open);$('ag_ops_archived_toggle').setAttribute('aria-expanded',String(open));});
    const history=element('button','Historial del equipo','btn btn-outline-secondary btn-sm');history.type='button';history.onclick=()=>bootstrapModal('ag_ops_history_modal').show();$('ag_ops_limit_notice').after(history);
    if(ui.service_ready&&ui.can_manage&&service){
      $('ag_ops_add_btn').addEventListener('click',resetEditor);$('ag_ops_form_cancel_btn').addEventListener('click',resetEditor);$('ag_ops_refresh').addEventListener('click',()=>run(refresh));
      $('ag_ops_save_btn').addEventListener('click',()=>run(async()=>{notify('Guardando cambios…');const caps=Array.from(document.querySelectorAll('[data-operator-grant]:checked'),e=>e.dataset.operatorGrant);if(!editing){const email=$('ag_ops_email');if(!email.reportValidity()||!email.value.trim())throw new Error('Ingresa el correo del invitado.');await command('invite',{email:email.value.trim(),role:$('ag_ops_role').value,capabilities:caps});}else{const {record,kind}=editing;await command(kind==='invitation'?'reissue':record.grant_status==='review_required'?'review':'grants',kind==='invitation'?{invitation_id:record.invitation_id,email:record.email_normalized,role:record.role_code,capabilities:caps}:{membership_id:record.membership_id,expected_revision:record.revision,capabilities:caps});}resetEditor();await refresh();notify('Cambio guardado y confirmado.');}));
    }else{
      const legacy=ui.legacy;
      // Do not modify, migrate or treat browser cache as Identity authority.
      let local=[],localNotice='',localCount=0;
      try{const raw=localStorage.getItem('mxmed.agenda.operators.state.v1');if(raw){
        if(raw.length>=524288)throw new Error('cache too large');
        const parsed=JSON.parse(raw),rows=[...(Array.isArray(parsed?.operators)?parsed.operators:[]),...(Array.isArray(parsed?.archived_operators)?parsed.archived_operators:[])];
        localCount=rows.length;
        local=rows.slice(0,500).map(r=>({operator_id:typeof r?.operator_id==='string'?r.operator_id:'',alias:typeof r?.alias==='string'&&r.alias?r.alias:'Registro local',status:typeof r?.status==='string'?r.status:'unknown'}));
        if(localCount>500)localNotice='Se muestran las primeras 500 referencias. El historial completo sigue guardado en este navegador.';
      }}catch(_){localNotice='No pudimos mostrar las referencias de este navegador. Los datos guardados no se han modificado.';}
      // A matching ID, alias or email in unverified storage is not proof of origin.
      // Only server records become primary cards; local references remain discoverable separately.
      const records=(legacy?.records||[]).map(r=>({...r,membership_id:r.source+':'+r.operator_id,alias:r.alias||r.operator_id,capabilities:[]}));
      $('ag_ops_list').replaceChildren(...records.filter(r=>r.status!=='archived').map(r=>band(r,'legacy')));
      const archived=records.filter(r=>r.status==='archived');$('ag_ops_archived_list').replaceChildren(...archived.map(r=>band(r,'legacy')));$('ag_ops_archived_count').textContent=legacy?.read_status==='ready'?String(archived.length):'No disponible';
      $('ag_ops_browser_history').classList.toggle('d-none',!localCount&&!localNotice);
      $('ag_ops_browser_count').textContent=String(localCount);$('ag_ops_browser_notice').textContent=localNotice;
      $('ag_ops_browser_list').replaceChildren(...local.map((r,index)=>{const row=element('li',undefined,'list-group-item px-0 py-1');row.dataset.localReference=String(index);row.dataset.operatorId=r.operator_id;row.append(element('strong',r.alias),document.createTextNode(' · '+(legacyStatuses[r.status]||'Estado desconocido')));return row;}));
      const c=legacy?.counts;
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
