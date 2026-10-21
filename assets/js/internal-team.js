/* Canonical team projection; draft and individual transport use the permission editor. */
(() => {
  'use strict';
  const $=id=>document.getElementById(id),esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const personName=m=>m.personal_profile?[m.personal_profile.given_names,m.personal_profile.first_surname,m.personal_profile.second_surname].filter(Boolean).join(' '):'Datos personales no disponibles';
  const staffStatus=m=>({ACTIVE:'Activo',SUSPENDED:'Suspendido',REMOVED:'Baja definitiva'}[m.staff_status]||'Estado no disponible');
  const groups=[['requests','Solicitudes de perfiles médicos',['physician_requests']],['accreditation','Acreditación profesional',['physician_accreditation','professional_sources']],['media','Revisión de medios',['media_review']],['corrections','Correcciones de perfiles',['public_profile_corrections','public_profile_correction_application']],['profiles','Administración de perfiles',['profile_administration']],['providers','Organizaciones y proveedores',['provider_claim','provider_commercial']]];
  const future=new Set(['profile_create','profile_curate','profile_publication_manage','profile_support_assist']);
  let category='ADVISOR',status='ACTIVE',page=1,q='',authority=null,catalog={},selected=null,selectedCase=null,listEpoch=0,detailEpoch=0,searchTimer;
  const say=message=>{$('feedback').textContent=message;};
  const mobile=view=>{$('workspace-columns').dataset.mobileView=view;$('show-directory').setAttribute('aria-pressed',String(view==='directory'));$('show-detail').setAttribute('aria-pressed',String(view==='detail'));};
  function empty(message){window.staffWorkflowDirectory?.detach();selectedCase=null;$('advisor-detail').innerHTML=`<div class="empty-state detail-empty"><h2 id="detail-title" tabindex="-1">${esc(message)}</h2><p>Selecciona un integrante o una solicitud en el directorio.</p></div>`;}
  async function read(params){
    const controller=new AbortController(),timer=setTimeout(()=>controller.abort(),15000);
    try{const response=await fetch('/api/internal/team/read.php?'+new URLSearchParams(params),{signal:controller.signal,method:'GET',credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'}});
    if(!response.ok){const e=new Error('read_failed');e.status=response.status;throw e;}return await response.json();}finally{clearTimeout(timer);}
  }
  const editor=window.createTeamPermissionEditor({read,onData:data=>{
    authority=data.authority;catalog=data.catalog;selected=data.items[0];renderDetail();masterFlow.adopt(data);
    const card=[...document.querySelectorAll('[data-member]')].find(b=>b.dataset.member===selected.account_reference);
    if(card&&category==='ADVISOR'&&selected.staff_status!==status){card.closest('li').remove();if(!document.querySelector('[data-member]'))$('directory-content').innerHTML='<p class="empty-state">No hay integrantes en este estado. Actualiza para consultar nuevamente.</p>';say('Estado actualizado. El integrante se consulta desde el filtro correspondiente.');}
    else if(card){const status=card.querySelector('.status');status.textContent=staffStatus(selected);status.classList.toggle('suspended',selected.staff_status!=='ACTIVE');card.querySelector('.advisor-meta span:last-child').textContent=`Asesor · ${selected.active_operational_grant_count} permisos ${authority.can_read_masters?'operativos':'en tu ámbito'}`;}
  }});
  const masterFlow=window.createTeamMasterFlow({editor,read,refresh:async(target,nextCategory)=>{category=nextCategory;page=1;await load();await select(target,true);}});
  async function navigate(action){if(window.staffWorkflowDirectory&&!window.staffWorkflowDirectory.canLeave())return;clearTimeout(searchTimer);if(masterFlow.active){say('Finaliza o consulta el resultado de la operación de gobierno antes de continuar.');$('advisor-search').value=q;$('staff-status').value=status;return;}if(await editor.canLeave())await action();else $('advisor-search').value=q;if($('staff-status')){$('staff-status').value=status;syncStatus();}}
  function failure(error){
    editor.reset();
    authority=null;selected=null;detailEpoch++;$('directory-content').innerHTML='';empty('Consulta no disponible');$('filter-masters').disabled=true;$('filter-advisors').disabled=true;$('previous-team').disabled=true;$('next-team').disabled=true;
    say(error.status===401?'Tu sesión no está disponible. Inicia sesión mediante Identity.':error.status===403?'La consulta del equipo requiere autorización vigente.':'No pudimos consultar el equipo. Puedes intentar de nuevo.');
    const button=document.createElement('button');button.type='button';button.id='retry-team';button.textContent='Reintentar consulta';button.onclick=load;$('directory-content').append(button);
  }
  async function load(restore=null){
    editor.reset();const epoch=++listEpoch;detailEpoch++;const automaticDetailEpoch=detailEpoch;selected=null;empty('Selecciona un integrante');$('directory-content').inert=true;$('directory-content').setAttribute('aria-busy','true');$('advisor-detail').setAttribute('aria-busy','true');say('Cargando equipo…');$('filter-advisors').setAttribute('aria-pressed',String(category==='ADVISOR'));$('filter-masters').setAttribute('aria-pressed',String(category==='MASTER_ADMIN'));
    $('previous-team').disabled=true;$('next-team').disabled=true;
    try{
      $('staff-status-filter').hidden=false;$('staff-status').value=status;$('advisor-search').disabled=false;syncStatus();$('clear-staff-search').hidden=!q;
      if(category==='ADVISOR'&&status==='PENDING'){
        const data=await window.staffWorkflowDirectory.pending(q,page);if(epoch!==listEpoch)return;const items=data.items;
        $('directory-content').innerHTML=items.length?`<ul class="advisor-list">${items.map(c=>`<li><button type="button" class="advisor-card" data-case="${esc(c.case_id)}"><span class="advisor-card-copy"><span class="advisor-name">${esc(c.display_name||'Solicitud pendiente')}</span><span class="team-reference">${esc(c.case_id)}</span><span class="status">${esc({DRAFT:'Preparación',PERSON_CONFIRMED:'Datos confirmados',PREPARED:'Preparación',AWAITING_PERSON:'Pendiente del solicitante'}[c.state]||'Pendiente de admisión')}</span></span></button></li>`).join('')}</ul>`:'<p class="empty-state">No hay solicitudes pendientes.</p>';
        $('directory-content').querySelectorAll('[data-case]').forEach(b=>b.onclick=()=>navigate(()=>selectCase(b.dataset.case,true)));
        $('team-page').textContent='Página '+data.page;$('previous-team').disabled=page===1;$('next-team').disabled=!data.has_more;empty('Solicitudes pendientes de admisión');say('Estos casos todavía no son integrantes activos. Selecciona uno para continuar su incorporación.');if(restore?.case)await selectCase(restore.case,false);return;
      }
      const data=await read({category,status,page:String(page),page_size:'20',q});if(epoch!==listEpoch)return;
      authority=data.authority;catalog=data.catalog;$('filter-advisors').disabled=false;$('staff-status').querySelector('[value="PENDING"]').disabled=category!=='ADVISOR'||!authority.can_read_masters||!window.staffWorkflowDirectory;$('filter-masters').disabled=!authority.can_read_masters;syncStatus();
      $('filter-advisors').setAttribute('aria-pressed',String(category==='ADVISOR'));$('filter-masters').setAttribute('aria-pressed',String(category==='MASTER_ADMIN'));
      $('authority-note').textContent=authority.class==='DIRECTOR'?'Administración de Dirección. Selecciona un integrante para gestionar su acceso y sus permisos.':'Vista Master: sólo asesores. Los permisos y conteos se limitan a tus capacidades delegadas vigentes; no revelan otras asignaciones.';
      let directoryHtml=data.items.length?`<ul class="advisor-list">${data.items.map(m=>`<li><button type="button" class="advisor-card" data-member="${esc(m.account_reference)}" data-admission-case="${esc(m.admission_case_id||'')}" aria-pressed="false"><span class="avatar team-neutral" aria-hidden="true">○</span><span class="advisor-card-copy"><span class="advisor-name">${esc(personName(m))}</span><span class="team-reference">Cuenta: ${esc(m.account_reference)}</span><span class="advisor-meta"><span class="status ${m.staff_status==='ACTIVE'?'':'suspended'}">${staffStatus(m)}</span><span>${m.governance_class==='MASTER_ADMIN'?'Master':'Asesor'} · ${m.active_operational_grant_count} permisos ${authority.can_read_masters?'operativos':'en tu ámbito'}</span></span></span></button></li>`).join('')}</ul>`:'<div class="empty-state"><strong>No hay integrantes en este estado.</strong></div>';
      $('team-page').textContent=`Página ${data.page}`;$('previous-team').disabled=page===1;$('next-team').disabled=!data.has_more;say(data.items.length?'':'No hay integrantes en este estado.');
      if(category==='ADVISOR'&&status==='ACTIVE'&&authority.can_read_masters&&window.staffWorkflowDirectory){
        const pending=await window.staffWorkflowDirectory.pending(q,1);if(epoch!==listEpoch)return;
        if(pending.items.length){directoryHtml='<ul class="advisor-list" aria-label="Integrantes en preparación o revisión">'+pending.items.map(c=>`<li><button type="button" class="advisor-card" data-case="${esc(c.case_id)}"><span class="advisor-card-copy"><span class="advisor-name">${esc(c.display_name)}</span><span class="status">${c.state==='PERSON_CONFIRMED'?'Revisión pendiente':'Preparación'}</span></span></button></li>`).join('')+'</ul>'+directoryHtml;}
      }
      $('directory-content').innerHTML=directoryHtml;
      $('directory-content').querySelectorAll('[data-member]').forEach(b=>b.onclick=()=>navigate(()=>select(b.dataset.member,true)));
      $('directory-content').querySelectorAll('[data-case]').forEach(b=>b.onclick=()=>navigate(()=>selectCase(b.dataset.case,true)));
      $('directory-content').inert=false;$('directory-content').setAttribute('aria-busy','false');
      if(automaticDetailEpoch!==detailEpoch)return; // A click made during directory loading takes precedence.
      if(restore?.case){const admitted=data.items.find(m=>m.admission_case_id===restore.case);if(admitted)await select(admitted.account_reference,false);else await selectCase(restore.case,false);}else if(restore?.account)await select(restore.account,false);else if(data.items.length)await select(data.items[0].account_reference,false);
    }catch(error){if(epoch===listEpoch)failure(error);}finally{if(epoch===listEpoch){$('directory-content').inert=false;$('directory-content').setAttribute('aria-busy','false');if(automaticDetailEpoch===detailEpoch)$('advisor-detail').setAttribute('aria-busy','false');}}
  }
  async function select(reference,focus){
    editor.reset();selected=null;empty('Cargando integrante…');highlight(reference,null);$('advisor-detail').setAttribute('aria-busy','true');const epoch=++detailEpoch,listing=listEpoch;
    try{const data=await read({category,target:reference});if(epoch!==detailEpoch||listing!==listEpoch)return;
      authority=data.authority;catalog=data.catalog;selected=data.items[0];renderDetail();editor.adopt(data);masterFlow.adopt(data);highlight(reference,null);
      if(focus){mobile('detail');$('detail-title').focus({preventScroll:true});}
    }catch(error){if(epoch===detailEpoch)failure(error);}finally{if(epoch===detailEpoch)$('advisor-detail').setAttribute('aria-busy','false');}
  }
  function highlight(reference,id){document.querySelectorAll('[data-member],[data-case]').forEach(b=>b.setAttribute('aria-pressed',String(id?(b.dataset.case===id||b.dataset.admissionCase===id):b.dataset.member===reference)));}
  async function selectCase(id,focus){
    editor.reset();const epoch=++detailEpoch;empty('Cargando solicitud…');$('advisor-detail').setAttribute('aria-busy','true');selected=null;selectedCase=id;highlight(null,id);
    await window.staffWorkflowDirectory.mount(id);
    if(epoch===detailEpoch)$('advisor-detail').setAttribute('aria-busy','false');
    if(epoch===detailEpoch&&selectedCase===id&&focus){mobile('detail');$('detail-title').focus({preventScroll:true});}
  }
  window.staffSelectCase=id=>navigate(()=>selectCase(id,true));
  function matrix(prefix,chosen,delegation=false){
    return groups.map(([id,title,domains])=>{
      const definitions=Object.values(catalog).filter(c=>c.kind==='operational'&&c.delegable&&domains.includes(c.domain));
      const visible=definitions.filter(c=>authority.can_read_masters||authority.delegations.includes(c.key));const count=visible.filter(c=>chosen.includes(c.key)).length;
      return `<section class="family" data-family="${id}"><div class="family-heading"><label class="family-select"><input type="checkbox" disabled data-group-count="${count}" data-group-total="${definitions.length}" ${count===definitions.length?'checked':''}><span><span class="family-title">${title}</span><span class="family-count">${count} de ${visible.length} ${delegation?'ámbitos delegados':'permisos vigentes'} visibles</span><span class="family-limit">${delegation?'Cada clave requiere autorización individual.':'Sólo consulta.'}${visible.length!==definitions.length?' Otras asignaciones no se exponen.':''}</span></span></label><button type="button" class="family-toggle" aria-expanded="false" aria-controls="${prefix}-${id}" data-expand="${prefix}-${id}">Expandir</button></div><div class="family-body" id="${prefix}-${id}" hidden>${definitions.map(c=>{
        const exposed=visible.some(v=>v.key===c.key),assigned=exposed&&chosen.includes(c.key);
        const availability=!exposed?'Dato fuera de tu ámbito delegado':future.has(c.key)?'Función aún no disponible':c.key.startsWith('public_profile_correction')&&selected.governance_class==='ADVISOR'?'Reservada a Dirección y administradores maestros':'El permiso se aplica según el ámbito de la función';
        return `<label class="capability-row"><input type="checkbox" disabled data-capability="${esc(c.key)}" data-exposed="${exposed}" ${assigned?'checked':''}><span class="capability-text"><span class="capability-label">${esc(c.label)}</span><span class="capability-description">${esc(c.description)}</span><span class="availability">${esc(availability)}</span><span class="capability-description">${delegation?'Delegación de gestión; no concede uso':exposed?(assigned?'Permiso vigente registrado':'Sin permiso vigente registrado'):'Sin información sobre el permiso'}. ${delegation?'Cada cambio requiere confirmación y MFA independiente.':(selected.editable_capabilities||[]).includes(c.key)?'Cambios sujetos a confirmación.':'Sólo consulta.'}</span></span></label>`;
      }).join('')}</div></section>`;
    }).join('');
  }
  function history(m){
    const h=m.history;if(!h||h.state==='UNAVAILABLE')return '<p>El historial no está disponible en este momento.</p>';
    if(!h.items.length)return '<p>No hay acciones registradas en el historial disponible.</p>';
    return '<p class="history-note">'+(h.has_more?'Últimas 100 acciones. ':'')+'Admisión, administración del equipo y actividad autorizada registrada.</p><ol class="team-history">'+h.items.map(e=>`<li data-event="${esc(e.id)}"><time datetime="${esc(e.at)}">${esc(new Date(e.at).toLocaleString('es-MX'))}</time><p><strong>${esc(e.description)}</strong> · ${e.direction==='BY_MEMBER'?'Realizada por el integrante':e.direction==='AUTHORIZED_BY_MEMBER'?'Activación del solicitante autorizada por el integrante':'Acción administrativa sobre el integrante'}</p><p>${esc(e.resource)} · ${esc(e.outcome==='SUCCESS'?'Confirmada':e.outcome==='FAILURE'?'Fallida':e.outcome)}</p></li>`).join('')+'</ol>';
  }
  function renderDetail(){
    const m=selected,master=m.governance_class==='MASTER_ADMIN';
    $('advisor-detail').innerHTML=`<header class="detail-header"><div class="detail-heading-row"><div><p class="eyebrow">${master?'Administrador maestro':'Asesor'}</p><h2 id="detail-title" tabindex="-1">${esc(personName(m))}</h2><span class="status ${m.staff_status==='ACTIVE'?'':'suspended'}">${staffStatus(m)}</span><p class="team-reference">Cuenta: ${esc(m.account_reference)}</p></div><div class="member-actions"><button id="lifecycle" type="button" class="lifecycle" disabled>${m.staff_status==='SUSPENDED'?'REACTIVAR':'SUSPENDER'} ${master?'MASTER':'ASESOR'}</button><button id="remove-member" type="button" class="lifecycle" disabled>DAR DE BAJA DEL EQUIPO</button></div></div></header>
      ${!master&&m.staff_status!=='ACTIVE'?`<p class="authority-note">${m.staff_status==='SUSPENDED'?'Este integrante está suspendido. Sus permisos no pueden modificarse mientras permanezca suspendido.':'Este integrante fue dado de baja. Sus permisos no pueden modificarse.'} <button type="button" id="return-active">Ver asesores activos</button></p>`:''}<details class="admission-section" id="personal-data"><summary>DATOS PERSONALES</summary>${window.staffPersonal?window.staffPersonal.summary(m):'<p>Información personal no disponible en tu ámbito.</p>'}${m.personal_editable?'<button type="button" id="member-edit">Editar datos personales</button>':''}</details>
      <details class="admission-section" id="identity-evidence"><summary>DOCUMENTACIÓN</summary>${m.admission_case_id?'<div id="member-documentation"></div>':'<p>No hay un expediente documental autorizado conectado a este registro.</p>'}</details>
      <details class="admission-access" open id="access-functions"><summary>ACCESO Y FUNCIONES</summary><div id="capabilities-form"><div class="matrix" id="operational-matrix"><h3 class="matrix-title">Permisos para usar funciones</h3>${matrix('use',m.grants)}
</div>
      ${master&&authority.can_read_masters?`<details class="master-governance"><summary>Gestión y delegación para asesores</summary><label class="ack-row"><input id="master-management" type="checkbox" disabled ${m.management_granted?'checked':''}> Permiso vigente de administración de asesores</label><p>Usar una función y delegarla son hechos separados. Cada cambio exige una confirmación y autorización individual.</p><div id="delegation-matrix">${matrix('delegate',m.master_delegations||[],true)}</div></details>`:''}
      <div class="save-area"><div class="save-row"><p class="unsaved" id="pending-permissions">Sólo consulta.</p><div class="save-buttons"><button id="revert-permissions" type="button" disabled>Revertir</button><button id="save-permissions" type="button" class="primary" disabled>GUARDAR PERMISOS</button></div></div><div id="permission-results" class="pending-notice" aria-live="polite" hidden></div></div></div></details>
      ${m.commercial_activity?.sales_assigned===true?'<details class="commercial-conditions" id="commercial-conditions"><summary>CONDICIONES COMERCIALES</summary><p>Condiciones por cliente no disponibles.</p></details>':''}
      <details class="reserved" id="member-limits"><summary>GOBIERNO RESERVADO Y LÍMITES</summary><dl class="commercial-terms"><div><dt>Acceso al equipo</dt><dd>${staffStatus(m)}</dd></div><div><dt>Cuenta</dt><dd>${esc({active:'Activa',disabled:'Deshabilitada',locked:'Bloqueada'}[m.account_status]||m.account_status)}</dd></div><div><dt>Funciones autorizadas</dt><dd>${m.active_operational_grant_count} permisos ${authority.can_read_masters?'vigentes':'visibles en tu ámbito'}</dd></div></dl><p>${m.staff_status==='REMOVED'?'La baja es definitiva; el historial se conserva.':m.staff_status==='SUSPENDED'?'El acceso staff está suspendido.':m.account_status!=='active'?'La cuenta no permite acceso activo.':'Las funciones disponibles dependen de los permisos vigentes.'}</p><p>Metas, reportes comerciales y disputas: sin registro conectado.</p>${authority.can_read_masters?`<button id="designation" class="lifecycle" type="button" disabled>${master?'RETIRAR DESIGNACIÓN MASTER':'PROPONER COMO MASTER'}</button><p id="master-status" class="authority-note" role="status" aria-live="polite"></p>`:'<p id="master-status" hidden></p>'}</details>
      <details class="commercial-conditions" id="member-clients"><summary>CLIENTES</summary><p>${master?'No aplica a esta categoría.':'La atribución de clientes a este asesor aún no está conectada.'}</p></details>
      <details class="history" id="member-history"><summary>HISTORIAL</summary>${history(m)}</details>`;
    document.querySelectorAll('[data-group-count]').forEach(e=>{e.indeterminate=Number(e.dataset.groupCount)>0&&e.dataset.groupCount!==e.dataset.groupTotal;e.setAttribute('aria-checked',e.indeterminate?'mixed':String(e.checked));});
    document.querySelectorAll('[data-exposed="false"]').forEach(e=>{e.indeterminate=true;e.setAttribute('aria-checked','mixed');});
    $('advisor-detail').querySelectorAll('[data-expand]').forEach(button=>button.onclick=()=>{const body=$(button.dataset.expand);body.hidden=!body.hidden;button.setAttribute('aria-expanded',String(!body.hidden));button.textContent=body.hidden?'Expandir':'Contraer';});
    window.staffPersonal?.loadPortrait(m);
    if($('member-edit'))$('member-edit').onclick=()=>navigate(()=>window.staffWorkflowDirectory.profile(m.admission_case_id));
    if(m.admission_case_id)window.staffWorkflowDirectory.documents(m.admission_case_id,$('member-documentation'));
    if($('return-active'))$('return-active').onclick=()=>navigate(()=>{status='ACTIVE';page=1;q='';$('advisor-search').value='';return load();});
    editor.mount();masterFlow.mount();
  }
  function syncStatus(){window.teamSelects?.sync($('staff-status'));}
  $('staff-status').onchange=e=>{const next=e.target.value;navigate(()=>{status=next;page=1;return load();});};
  $('filter-advisors').onclick=()=>navigate(()=>{category='ADVISOR';status='ACTIVE';page=1;q='';$('advisor-search').value='';return load();});
  $('filter-masters').onclick=()=>{if(authority?.can_read_masters)navigate(()=>{category='MASTER_ADMIN';status='ACTIVE';page=1;q='';$('advisor-search').value='';return load();});};
  $('clear-staff-search').onclick=()=>navigate(()=>{q='';page=1;$('advisor-search').value='';$('advisor-search').focus();return load();});
  $('advisor-search').oninput=e=>{$('clear-staff-search').hidden=!e.target.value;clearTimeout(searchTimer);const value=e.target.value.trim();if(value!==''&&!/^[\p{L}\p{N} ._:\x27-]{1,64}$/u.test(value)){say('Escribe un nombre o una referencia válida, hasta 64 caracteres.');return;}searchTimer=setTimeout(()=>navigate(()=>{q=value;page=1;return load();}),300);};
  $('previous-team').onclick=()=>{if(page>1)navigate(()=>{page--;return load();});};
  $('next-team').onclick=()=>navigate(()=>{page++;return load();});$('refresh-team').onclick=()=>navigate(load);
  document.querySelectorAll('.team-navigation a').forEach(link=>link.onclick=e=>{if(!e.ctrlKey&&!e.metaKey&&!e.shiftKey&&!e.altKey){e.preventDefault();navigate(()=>location.assign(link.href));}});
  $('show-directory').onclick=()=>{mobile('directory');$('advisor-search').focus();};$('show-detail').onclick=()=>{mobile('detail');$('detail-title').focus({preventScroll:true});};$('skip-detail').onclick=()=>mobile('detail');
  $('team-logout').onclick=()=>navigate(async()=>{
    const button=$('team-logout');button.disabled=true;
    try {
      const c=window.staffWorkflowDirectory?await fetch('/api/internal/staff-admission/workflow.php',{method:'POST',credentials:'same-origin',cache:'no-store',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'bootstrap',input:{}})}):await fetch('/api/identity/index.php/current-session',{credentials:'same-origin',cache:'no-store'});const token=await c.json();if(!c.ok||!token.csrf_token)throw new Error();
      const r=await fetch('/api/identity/index.php/logout',{method:'POST',credentials:'same-origin',cache:'no-store',headers:{'Content-Type':'application/json','X-CSRF-Token':token.csrf_token},body:JSON.stringify({csrf_token:token.csrf_token})});const result=await r.json();
      if(!r.ok||result.ok!==true||result.state!=='LOGOUT_SUCCESS')throw new Error();
      const message={type:'logout',at:Date.now()};try{const channel=new BroadcastChannel('mxmed-session');channel.postMessage(message);channel.close();localStorage.setItem('mxmed.session.signal',JSON.stringify(message));}catch(_){}
      listEpoch++;detailEpoch++;selected=null;authority=null;document.body.replaceChildren();location.replace('/public/identity/acceso.php?next='+encodeURIComponent('/internal/media-review/index.php?section=equipo'));
    }catch(_){say('No pudimos confirmar el cierre de sesión. Intenta nuevamente.');button.disabled=false;}
  });
  window.staffWorkflowRefresh=()=>{const restore=selectedCase?{case:selectedCase}:{account:selected?.account_reference},personalOpen=$('personal-data')?.open,documentsOpen=$('identity-evidence')?.open,openSections=[...document.querySelectorAll('#staff-case details[open][id]')].map(e=>e.id),message=$('staff-case-status')?.textContent;return navigate(async()=>{await load(restore);if(documentsOpen&&$('identity-evidence'))$('identity-evidence').open=true;if(personalOpen&&$('personal-data'))$('personal-data').open=true;if(restore.case&&$('staff-case-status')){$('staff-case-status').textContent=message||'';openSections.forEach(id=>{if($(id))$(id).open=true;});}});};
  window.addEventListener('staff-workflow-changed',()=>window.staffWorkflowRefresh());
  load();
})();
