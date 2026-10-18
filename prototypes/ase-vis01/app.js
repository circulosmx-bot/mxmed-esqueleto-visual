/* ASE-VIS01 R2: synthetic page-memory presentation only. No Identity, API or storage calls. */
(() => {
  'use strict';
  const catalog = Object.values(window.ASE_VIS01_CATALOG);
  const operational = catalog.filter(c => c.kind === 'operational' && c.delegable);
  const future = new Set(['profile_create','profile_curate','profile_publication_manage','profile_support_assist']);
  const restricted = new Set(['public_profile_correction_review','public_profile_correction_apply']);
  const groups = [
    {id:'accreditation', title:'Acreditación profesional', domains:['physician_accreditation','professional_sources'], note:'Revisión y fuentes son permisos independientes. Su uso requiere los controles de cada flujo.'},
    {id:'media', title:'Revisión de medios', domains:['media_review'], note:'Incluye acciones sensibles: descargar originales privados y aprobar imágenes públicas. Revisa cada cambio antes de guardar.'},
    {id:'corrections', title:'Correcciones de perfiles', domains:['public_profile_corrections','public_profile_correction_application'], note:'Flujo existente para Dirección y Master. Su uso por asesores aún no está habilitado.'},
    {id:'profiles', title:'Administración de perfiles', domains:['profile_administration'], note:'Cuatro permisos definidos, con flujo genérico pendiente de conectar. Sólo lectura.'},
    {id:'providers', title:'Organizaciones y proveedores', domains:['provider_claim','provider_commercial'], note:'Servicios existentes para organizaciones. La pantalla interna aún está pendiente.'}
  ];
  const descriptions = {
    media_review_read:'Consultar las imágenes recibidas para revisión.',
    media_review_approve:'Aprobar explícitamente una imagen para su publicación.',
    media_review_source_download:'Descargar el archivo original privado con registro de la acción.',
    media_review_corrected_upload:'Cargar una corrección y preparar una nueva vista para revisión.',
    media_review_improve:'Preparar propuestas privadas de mejora de un logotipo.',
    media_review_request_replacement:'Pedir al usuario una nueva imagen e indicar el motivo.',
    profile_create:'Crear un perfil bajo un caso autorizado, sin asignar su titularidad.',
    profile_curate:'Corregir información profesional en un perfil y caso específicos.',
    profile_publication_manage:'Gestionar la visibilidad pública sin cambiar titularidad ni plan.',
    profile_support_assist:'Asistir de forma identificada, sin suplantar al médico.',
    professional_source_provision:'Revisar y verificar evidencia profesional de un expediente específico.',
    physician_accreditation_review:'Revisar identidad, credenciales y vínculo con el perfil médico.',
    public_profile_correction_review:'Revisar informes sobre un perfil sin aplicar cambios.',
    public_profile_correction_apply:'Aplicar una corrección previamente autorizada en el perfil exacto.',
    provider_claim_review:'Revisar la solicitud de vinculación de una organización proveedora.',
    provider_subscription_activate:'Gestionar una suscripción organizacional. Esta acción no acredita un pago.'
  };
  const $ = id => document.getElementById(id);
  const esc = value => String(value).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const labelFor = key => window.ASE_VIS01_CATALOG[key].label;
  const keysFor = group => operational.filter(c=>group.domains.includes(c.domain)).map(c=>c.key);
  const fixture = (id,name,status,grants,category='ADVISOR',manage=false,delegations=[]) => ({id,name,status,stage:status,category,manage,grants:new Set(grants),delegations:new Set(delegations),version:1,events:[]});
  const members = [
    fixture('aurora','Aurora Naranjo','ACTIVE',['media_review_read']),
    fixture('bruno','Bruno Lago','ACTIVE',['media_review_read','physician_accreditation_review','professional_source_provision']),
    fixture('cora','Cora Vela','ACTIVE',operational.map(c=>c.key)),
    fixture('diego','Diego Roble','ACTIVE',[]),
    fixture('elisa','Elisa Monte','SUSPENDED',['media_review_read','media_review_approve']),
    fixture('mar','Mar Solano','ACTIVE',['media_review_read','media_review_source_download']),
    fixture('lucia','Lucía Castaño','ACTIVE',['media_review_read','media_review_source_download'],'MASTER_ADMIN',true,['media_review_read','media_review_approve','physician_accreditation_review']),
    fixture('tomas','Tomás Niebla','SUSPENDED',['professional_source_provision'],'MASTER_ADMIN',false,['media_review_read'])
  ];
  const stages = {ACTIVE:'Activo',SUSPENDED:'Suspendido',PENDING_INVITATION:'Invitación pendiente',PENDING_IDENTITY_VERIFICATION:'Verificación de identidad pendiente',PENDING_AUTHORIZATION:'Autorización pendiente',PENDING_MFA_SETUP:'Configuración MFA pendiente'};
  let selected=members[0], mode='director', category='ADVISOR', directoryState='ready', query='', stale=false;
  let draft, wizard=null, wizardStep=0, sequence=0;
  const copy = member => ({grants:new Set(member.grants),delegations:new Set(member.delegations),manage:member.manage});
  const resetDraft = () => { draft=copy(selected); stale=false; };
  resetDraft();
  const deltaSet = (next,previous) => ({add:[...next].filter(k=>!previous.has(k)),remove:[...previous].filter(k=>!next.has(k))});
  const changes = () => ({use:deltaSet(draft.grants,selected.grants),delegate:deltaSet(draft.delegations,selected.delegations),management:draft.manage!==selected.manage});
  const dirty = () => {const d=changes();return d.use.add.length+d.use.remove.length+d.delegate.add.length+d.delegate.remove.length+Number(d.management)>0;};
  const active = member => member.status==='ACTIVE' && member.stage==='ACTIVE';
  const actor = () => members.find(m=>m.id==='lucia');
  const canManage = () => mode==='director' || (mode==='master' && active(actor()) && actor().manage && selected.category==='ADVISOR' && selected.id!==actor().id);
  function available(key,targetCategory) {return !future.has(key) && !(targetCategory==='ADVISOR' && restricted.has(key));}
  function editable(key,kind='use') {
    return active(selected) && !stale && canManage() && available(key,kind==='delegate'?'ADVISOR':selected.category) && (kind==='delegate'?mode==='director':mode==='director'||actor().delegations.has(key));
  }
  const say = message => { $('feedback').textContent=message; };
  const stateLabel = member => stages[member.stage] || 'En revisión';
  const status = member => `<span class="status ${active(member)?'': 'suspended'}">${stateLabel(member)}</span>`;
  const categoryLabel = value => value==='MASTER_ADMIN'?'Administrador maestro':'Asesor';
  function focusDetail() { $('detail-title')?.focus(); }
  function mobileView(view) {
    $('workspace-columns').dataset.mobileView=view;
    $('show-directory').setAttribute('aria-pressed',String(view==='directory'));
    $('show-detail').setAttribute('aria-pressed',String(view==='detail'));
  }
  function setupDialog(dialog) {
    let previousFocus=null;
    dialog.addEventListener('keydown',event=>{
      if(event.key!=='Tab')return;
      const controls=[...dialog.querySelectorAll('button,input,select,[tabindex="0"]')].filter(e=>!e.disabled && !e.hidden && e.getClientRects().length);
      if(!controls.length)return;
      event.preventDefault();const index=controls.indexOf(document.activeElement);
      controls[(index<0?(event.shiftKey?controls.length-1:0):(index+(event.shiftKey?-1:1)+controls.length)%controls.length)].focus();
    });
    dialog.addEventListener('close',()=>{
      if(dialog.open)return;
      if(previousFocus?.isConnected && previousFocus.getClientRects().length)previousFocus.focus();
      else if(stale && $('conflict-banner'))$('conflict-banner').focus();
      else focusDetail();
    });
    return ()=>{previousFocus=document.activeElement;dialog.showModal();};
  }
  const confirmation=$('confirmation'), showConfirmation=setupDialog(confirmation), onboarding=$('onboarding'), showOnboarding=setupDialog(onboarding);
  let pendingAction=null;
  function confirm(title,description,body,accept,action,privileged=false) {
    $('confirmation-title').textContent=title; $('confirmation-description').textContent=description;
    $('confirmation-body').innerHTML=body+(privileged?'<label class="ack-row" for="privileged-ack"><input id="privileged-ack" type="checkbox"> Confirmo expresamente esta propuesta privilegiada. La operación real requiere un contrato de seguridad y verificación reforzada aún pendiente.</label>':'');
    $('accept-confirmation').textContent=accept; $('accept-confirmation').disabled=privileged;
    pendingAction=action; showConfirmation();
    if(privileged)$('privileged-ack').onchange=e=>{$('accept-confirmation').disabled=!e.target.checked;};
  }
  function transition(action) {
    if(!dirty()){action();return;}
    confirm('¿Descartar los cambios pendientes?','Puedes seguir editando o descartar la propuesta para continuar. No se ha guardado ningún cambio.','','Descartar y continuar',action);
  }
  function choose(member) {transition(()=>{selected=member;resetDraft();say('');render();mobileView('detail');focusDetail();});}
  function filter(next) {
    if(next==='MASTER_ADMIN' && mode!=='director')return;
    transition(()=>{category=next;query='';$('advisor-search').value='';selected=members.find(m=>m.category===category)||selected;resetDraft();render();mobileView('directory');});
  }
  function renderDirectory() {
    const list=members.filter(m=>m.category===category);
    $('directory-count').textContent=directoryState==='ready'?`${list.length} ficticios`:'';
    $('filter-advisors').setAttribute('aria-pressed',String(category==='ADVISOR'));
    $('filter-masters').setAttribute('aria-pressed',String(category==='MASTER_ADMIN'));
    $('filter-masters').disabled=mode!=='director';
    $('category-hint').textContent=mode==='director'?'El Director conserva la raíz y no aparece como integrante editable.':'Sólo puedes administrar asesores dentro de tu autorización y delegación. No puedes nombrar Masters ni administrarte a ti mismo.';
    $('add-member').disabled=mode==='missing' || (mode==='master' && (!actor().manage || !active(actor())));
    const content=$('directory-content');content.setAttribute('aria-busy',String(directoryState==='loading'));
    if(directoryState!=='ready') {
      content.innerHTML=directoryState==='loading'?'<div class="empty-state" role="status"><strong>Cargando equipo…</strong>Ejemplo de consulta en curso.</div>':directoryState==='error'?'<div class="empty-state" role="alert"><strong>No pudimos consultar el directorio.</strong><button id="retry-directory" type="button">Reintentar</button></div>':'<div class="empty-state"><strong>Aún no hay integrantes.</strong>La incorporación reúne los pasos de identidad y autorización.</div>';
      if($('retry-directory'))$('retry-directory').onclick=()=>{directoryState='ready';$('directory-state').value='ready';render();};return;
    }
    const normal=value=>value.normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase();
    const visible=list.filter(m=>normal(m.name).includes(normal(query)));
    content.innerHTML=visible.length?`<ul class="advisor-list">${visible.map(m=>`<li><button type="button" class="advisor-card" data-member="${m.id}" aria-pressed="${selected.id===m.id}"><span class="avatar" aria-hidden="true">${m.name.split(' ').map(n=>n[0]).join('')}</span><span class="advisor-card-copy"><span class="advisor-name">${esc(m.name)}</span><span class="advisor-meta">${status(m)}<span>${m.grants.size} ${m.grants.size===1?'permiso asignado':'permisos asignados'}${m.requested?` · ${m.requested.size} propuestos`:''}</span></span></span>${selected.id===m.id?'<span class="selection-mark" aria-hidden="true">✓</span>':''}</button></li>`).join('')}</ul>`:list.length?'<div class="empty-state"><strong>Sin resultados.</strong>Prueba con otro nombre.</div>':'<div class="empty-state"><strong>Aún no hay integrantes de esta categoría.</strong></div>';
    content.querySelectorAll('[data-member]').forEach(button=>button.onclick=()=>choose(members.find(m=>m.id===button.dataset.member)));
  }
  function reason(key,kind,targetCategory=selected.category) {
    if(future.has(key))return 'Sin flujo conectado · sólo lectura';
    if(restricted.has(key) && (kind==='delegate'||targetCategory==='ADVISOR'))return 'Uso por asesores no habilitado';
    if(!active(selected))return 'Sin autorización operativa activa · estado conservado';
    if(mode==='missing')return 'Sin autorización de gestión';
    if(mode!=='director' && !actor().delegations.has(key))return 'Fuera de tu delegación · se conserva';
    return key.startsWith('provider_')?'Servicio existente · pantalla pendiente':'';
  }
  function familyHTML(prefix,kind,chosen,isEditable,reasonFor) {
    return groups.map(group=>{
      const id=`${prefix}-${group.id}`,keys=keysFor(group);
      return `<section class="family" data-family="${group.id}" data-kind="${kind}"><div class="family-heading"><label class="family-select" for="${id}-select"><input type="checkbox" id="${id}-select" data-family-select="${group.id}" data-kind="${kind}"><span><span class="family-title">${group.title}</span><span class="family-count" id="${id}-count"></span><span class="family-limit" id="${id}-limit"></span></span></label><button class="family-toggle" type="button" aria-expanded="false" aria-controls="${id}-body" data-expand="${id}-body" aria-label="Expandir ${group.title}">Expandir</button></div><div id="${id}-body" class="family-body" hidden><p class="group-note">${group.note}</p>${keys.map(key=>`<label class="capability-row" for="${id}-${key}"><input type="checkbox" id="${id}-${key}" data-capability="${key}" data-kind="${kind}" ${chosen.has(key)?'checked':''} ${isEditable(key)?'':'disabled'} aria-describedby="${id}-${key}-description"><span class="capability-text"><span class="capability-label">${esc(labelFor(key))}</span><span class="capability-description" id="${id}-${key}-description">${esc(descriptions[key])}</span>${reasonFor(key)?`<span class="availability">${esc(reasonFor(key))}</span>`:''}</span></label>`).join('')}</div></section>`;
    }).join('');
  }
  function bindFamilies(container,chosenFor,isEditable,onChange) {
    container.querySelectorAll('[data-expand]').forEach(button=>button.onclick=()=>{
      const body=$(button.dataset.expand);body.hidden=!body.hidden;button.setAttribute('aria-expanded',String(!body.hidden));button.textContent=body.hidden?'Expandir':'Contraer';button.setAttribute('aria-label',`${body.hidden?'Expandir':'Contraer'} ${groups.find(g=>g.id===button.closest('[data-family]').dataset.family).title}`);
    });
    container.querySelectorAll('[data-capability]').forEach(input=>input.onchange=()=>{
      const key=input.dataset.capability,kind=input.dataset.kind,chosen=chosenFor(kind);
      if(isEditable(key,kind)){if(input.checked)chosen.add(key);else chosen.delete(key);}else input.checked=chosen.has(key);
      refreshFamilies(container,chosenFor,isEditable);onChange();
    });
    container.querySelectorAll('[data-family-select]').forEach(input=>input.onchange=()=>{
      const kind=input.dataset.kind,chosen=chosenFor(kind),keys=keysFor(groups.find(g=>g.id===input.dataset.familySelect)).filter(k=>isEditable(k,kind));
      const remove=keys.length>0 && keys.every(key=>chosen.has(key));
      keys.forEach(key=>remove?chosen.delete(key):chosen.add(key));
      container.querySelectorAll(`[data-capability][data-kind="${kind}"]`).forEach(control=>{control.checked=chosen.has(control.dataset.capability);});
      refreshFamilies(container,chosenFor,isEditable);onChange();
    });
    refreshFamilies(container,chosenFor,isEditable);
  }
  function refreshFamilies(container,chosenFor,isEditable) {
    container.querySelectorAll('[data-family-select]').forEach(input=>{
      const group=groups.find(g=>g.id===input.dataset.familySelect),kind=input.dataset.kind,chosen=chosenFor(kind),keys=keysFor(group),eligible=keys.filter(k=>isEditable(k,kind));
      const count=keys.filter(k=>chosen.has(k)).length,eligibleSelected=eligible.filter(k=>chosen.has(k)).length;
      input.checked=count===keys.length;input.indeterminate=count>0 && count<keys.length;input.disabled=!eligible.length;
      input.setAttribute('aria-checked',input.indeterminate?'mixed':String(input.checked));
      const row=input.closest('.family'),label=row.querySelector('.family-count'),limit=row.querySelector('.family-limit');
      label.textContent=`${count} de ${keys.length} ${kind==='delegate'?'ámbitos seleccionados':'permisos seleccionados'} · ${eligibleSelected} de ${eligible.length} editables seleccionados`;
      const reasons=[...new Set([...row.querySelectorAll('[data-capability]:disabled')].map(control=>control.closest('label').querySelector('.availability')?.textContent||'Fuera del alcance editable'))];
      limit.textContent=keys.length-eligible.length?`${keys.length-eligible.length} excluidos; se conservan. ${reasons.join('; ')}.`:'';
      input.setAttribute('aria-label',`${eligible.length && eligibleSelected===eligible.length?'Deseleccionar':'Seleccionar'} sólo permisos elegibles de ${group.title}. ${label.textContent}. ${limit.textContent}`);
    });
  }
  function renderDetail() {
    const detail=$('advisor-detail');
    if(directoryState!=='ready' || selected.category!==category){detail.innerHTML='<div class="empty-state detail-empty"><h2 id="detail-title" tabindex="-1">Selecciona un integrante</h2><p>Su información aparecerá cuando el directorio esté disponible.</p></div>';return;}
    const master=selected.category==='MASTER_ADMIN',pending=!['ACTIVE','SUSPENDED'].includes(selected.stage);
    detail.innerHTML=`<header class="detail-header"><div class="detail-heading-row"><div><p class="eyebrow">${categoryLabel(selected.category)} · persona ficticia</p><h2 id="detail-title" tabindex="-1">${esc(selected.name)}</h2>${status(selected)}<p class="detail-subtitle"><span id="selected-count">${draft.grants.size}</span> <span id="count-noun">${draft.grants.size===1?'permiso operativo':'permisos operativos'}</span> <span id="count-label">${draft.grants.size===1?'asignado':'asignados'}</span> · simulación</p></div><div class="member-actions"><button id="lifecycle" class="lifecycle" type="button" ${pending?'disabled':''}>${selected.status==='SUSPENDED'?'REACTIVAR':'SUSPENDER'} ${master?'MASTER':'ASESOR'}</button>${mode==='director'?`<button id="designation" class="lifecycle" type="button">${master?'RETIRAR DESIGNACIÓN MASTER':'PROPONER COMO MASTER'}</button>`:''}</div></div><p class="authority-note">${mode==='director'?'Vista Director: permisos de uso, gestión y delegación son decisiones separadas.':mode==='missing'?'Este Master no tiene autorización de gestión. Todos los permisos permanecen en consulta.':'Vista Master: sólo puedes administrar asesores y las capacidades expresamente delegadas. No puedes autoadministrarte ni nombrar Masters.'}${selected.status==='SUSPENDED'?' La suspensión bloquea uso y conserva permisos e historial.':''}</p>${pending?`<div class="pending-notice"><strong>${stateLabel(selected)} · simulación</strong><p>${esc(selected.pendingMessage||'La propuesta no es una autorización operativa activa.')}</p>${selected.requested?`<p>${selected.requested.size} permisos solicitados; cero nuevos permisos efectivos. Falta validar identidad, autoridad y los pasos de seguridad aplicables.</p>`:''}</div>`:''}</header>
    <div id="conflict-banner" class="conflict-banner" role="alert" tabindex="-1" ${stale?'':'hidden'}><p><strong>La información cambió durante la edición.</strong> La propuesta no se aplicó. Actualiza antes de continuar.</p><button id="refresh-conflict" type="button">Actualizar y descartar cambios</button></div>
    <form id="capabilities-form"><div class="matrix" id="operational-matrix"><h3 class="matrix-title">Permisos para usar funciones</h3><p class="group-note">Asignación, uso efectivo y disponibilidad del flujo son distintos. Seleccionar una familia sólo modifica sus permisos editables.</p>${familyHTML('use','use',draft.grants,key=>editable(key),key=>reason(key,'use'))}</div>
    ${master?`<details class="master-governance"><summary>Gestión y delegación para asesores</summary><p>Usar una función no autoriza concederla. Sólo el Director configura esta autoridad.</p><label class="ack-row" for="manage-advisors"><input id="manage-advisors" type="checkbox" ${draft.manage?'checked':''} ${mode==='director'&&active(selected)&&!stale?'':'disabled'}> Autorizar administración de asesores</label><p class="group-note">Sin esta autorización, los ámbitos delegados no permiten gestionar asesores.</p><div id="delegation-matrix">${familyHTML('delegate','delegate',draft.delegations,key=>editable(key,'delegate'),key=>reason(key,'delegate'))}</div></details>`:''}
    <div class="save-area"><div class="save-row"><p id="unsaved" class="unsaved" role="status" aria-live="polite"></p><div class="save-buttons"><button id="revert" type="button" class="secondary">Revertir</button><button id="save-permissions" class="primary" type="submit">GUARDAR PERMISOS</button></div></div><p class="save-explanation">Guardado explícito y simulado. La aplicación real deberá confirmar cada cambio individual.</p></div></form>
    <details class="reserved"><summary>Gobierno reservado y límites</summary><p>Nombrar o retirar Masters corresponde sólo al Director. La aprobación de titularidad inicial y recuperación extraordinaria quedan fuera de esta pantalla. Ninguna familia crea un permiso genérico.</p></details><details class="history"><summary>Historial · ejemplo ficticio</summary><p class="history-note">La integración futura consultará auditoría canónica; aquí no se lee ningún registro real.</p><ol id="history-list"></ol></details>`;
    bindFamilies($('capabilities-form'),kind=>kind==='delegate'?draft.delegations:draft.grants,editable,()=>{say('');updatePending();});
    if($('manage-advisors'))$('manage-advisors').onchange=e=>{draft.manage=e.target.checked;updatePending();};
    $('capabilities-form').onsubmit=e=>{e.preventDefault();savePreview();};
    $('revert').onclick=()=>{resetDraft();say('Propuesta revertida; estado simulado guardado conservado.');renderDetail();focusDetail();};
    $('lifecycle').onclick=lifecyclePreview;
    if($('designation'))$('designation').onclick=designationPreview;
    $('refresh-conflict').onclick=()=>{resetDraft();say('Estado ficticio actualizado. Puedes revisar una nueva propuesta.');render();focusDetail();};
    $('history-list').innerHTML=[...selected.events,'Integrante registrado.','Permiso concedido: Consultar revisión de medios.','Permiso revocado: Aprobar medios.','Integrante suspendido; permisos conservados.','Integrante reactivado.'].map((text,i)=>`<li><time>Ejemplo ${i+1} · Dirección ficticia</time>${esc(text)}</li>`).join('');
    updatePending();
  }
  function updatePending() {
    if(!$('unsaved'))return;
    const d=changes(),count=d.use.add.length+d.use.remove.length+d.delegate.add.length+d.delegate.remove.length+Number(d.management);
    $('unsaved').textContent=count?`${count} cambios sin guardar`:'Sin cambios pendientes';$('unsaved').classList.toggle('dirty',count>0);
    $('selected-count').textContent=draft.grants.size;$('count-noun').textContent=draft.grants.size===1?'permiso operativo':'permisos operativos';$('count-label').textContent=count?(draft.grants.size===1?'propuesto':'propuestos'):(draft.grants.size===1?'asignado':'asignados');
    $('save-permissions').disabled=!count || !canManage() || !active(selected) || stale;
    $('revert').disabled=!count || stale;
    $('lifecycle').disabled=!canManage() || dirty() || stale || !['ACTIVE','SUSPENDED'].includes(selected.stage);
    if($('designation'))$('designation').disabled=dirty() || stale || (selected.category==='ADVISOR' && !active(selected));
  }
  const listChanges=(title,keys)=>`<section class="change-list"><h3>${title} (${keys.length})</h3>${keys.length?`<ul>${keys.map(k=>`<li>${esc(labelFor(k))}</li>`).join('')}</ul>`:'<p>Ninguno.</p>'}</section>`;
  function savePreview() {
    if(!dirty() || !canManage() || !active(selected) || stale)return;
    const d=changes(),version=selected.version;
    confirm('Confirmar propuesta de permisos',`Cambios individuales para ${selected.name}. Sólo se simulan en memoria. Descargar originales privados y aprobar medios son acciones sensibles.`,listChanges('Conceder uso',d.use.add)+listChanges('Revocar uso',d.use.remove)+(selected.category==='MASTER_ADMIN'?listChanges('Delegar administración',d.delegate.add)+listChanges('Retirar delegación',d.delegate.remove)+(d.management?`<p>${draft.manage?'Autorizar':'Retirar'} administración de asesores.</p>`:''):''),'Confirmar simulación',()=>{
      if($('simulate-conflict').checked){selected.version++;$('simulate-conflict').checked=false;}
      if(selected.version!==version){stale=true;say('Conflicto simulado: no se aplicó tu propuesta.');renderDetail();return;}
      [['Permiso concedido',d.use.add],['Permiso revocado',d.use.remove],['Delegación concedida',d.delegate.add],['Delegación retirada',d.delegate.remove]].forEach(([verb,keys])=>keys.forEach(k=>selected.events.unshift(`${verb}: ${labelFor(k)}.`)));
      if(d.management)selected.events.unshift(`${draft.manage?'Autorizada':'Retirada'} administración de asesores.`);
      selected.grants=new Set(draft.grants);selected.delegations=new Set(draft.delegations);selected.manage=draft.manage;selected.version++;
      say(`Simulación guardada. Concedidos: ${d.use.add.length}. Revocados: ${d.use.remove.length}. Delegaciones añadidas: ${d.delegate.add.length}; retiradas: ${d.delegate.remove.length}. No hubo cambios reales.`);render();
    });
  }
  function lifecyclePreview() {
    if(!canManage() || dirty() || stale || !['ACTIVE','SUSPENDED'].includes(selected.stage))return;
    const suspend=selected.status==='ACTIVE';
    confirm(suspend?'¿Suspender al integrante?':'¿Reactivar al integrante?',`${selected.name}. ${suspend?'La suspensión bloquea futuras acciones y conserva todos sus permisos, delegaciones e historial. No elimina la cuenta.':'La reactivación conserva los permisos y delegaciones guardados. Cada uso requiere sus controles propios.'} Sólo es una simulación.`,'',suspend?'Simular suspensión':'Simular reactivación',()=>{selected.status=suspend?'SUSPENDED':'ACTIVE';selected.stage=selected.status;selected.version++;selected.events.unshift(suspend?'Integrante suspendido.':'Integrante reactivado.');say(suspend?'Suspensión simulada; permisos e historial conservados.':'Reactivación simulada; no se modificó una cuenta real.');render();});
  }
  function designationPreview() {
    if(mode!=='director' || dirty() || stale)return;
    const remove=selected.category==='MASTER_ADMIN';
    confirm(remove?'Retirar designación de Master':'Proponer designación de Master',remove?'La simulación conserva los permisos operativos y el historial. Retira la autoridad de gestión y todas las delegaciones; la persona pasa a Asesor.':'Sólo el Director puede proponer esta designación. No concede permisos operativos, gestión ni delegaciones automáticamente. Antes de activarla se requiere un contrato aprobado de verificación reforzada.', '',remove?'Simular retiro de designación':'Confirmar propuesta privilegiada',()=>{
      selected.category=remove?'ADVISOR':'MASTER_ADMIN';selected.manage=false;selected.delegations.clear();selected.stage=remove?(selected.status||selected.stage):'PENDING_AUTHORIZATION';selected.version++;
      selected.events.unshift(remove?'Retirada designación Master; gestión y delegaciones revocadas.':'Designación Master propuesta; autorización reforzada pendiente.');category=selected.category;resetDraft();render();say(remove?'Retiro de designación simulado. Permisos operativos conservados; gestión y delegaciones retiradas.':'Propuesta Master simulada, pendiente de autorización reforzada. No es una activación operativa.');focusDetail();
    },true);
  }
  // This is one administrative journey, not a registration/invitation writer or transaction.
  const cases={existing:['Cuenta activa existente','PENDING_AUTHORIZATION','La cuenta ficticia existente se reutilizaría tras una coincidencia autorizada. Falta autorizar la relación y los permisos.'],new:['Requiere invitación nueva','PENDING_INVITATION','Invitación pendiente. No se envió ningún mensaje. La persona deberá crear su contraseña, aceptar documentos legales y verificar su correo.'],verification:['Verificación pendiente','PENDING_IDENTITY_VERIFICATION','La verificación de identidad/correo sigue pendiente. No hay autorización operativa.'],mfa:['Configuración MFA pendiente','PENDING_MFA_SETUP','MFA pendiente mediante ceremonia canónica con registrador independiente; aquí no se configura ningún factor.'],duplicate:['Posible duplicado','PENDING_AUTHORIZATION','Posible duplicado ficticio. No se crea otra entrada; requiere conciliación autorizada.'],expired:['Invitación vencida','PENDING_INVITATION','La invitación ficticia ha vencido. No se envió otra. Requiere revisión y reemisión autorizada.'],partial:['Fallo parcial de incorporación','PENDING_AUTHORIZATION','Resultado parcial simulado: autorización no confirmada. Actualizar y reconciliar cada paso antes de reintentar; sin repetición automática.']};
  function wizardEligible(key,kind='use') {return available(key,kind==='delegate'?'ADVISOR':wizard.category) && (mode==='director' || (kind==='use' && mode==='master' && active(actor()) && actor().manage && actor().delegations.has(key)));}
  function openWizard() {
    if($('add-member').disabled)return;
    transition(()=>{wizard={name:'Nora Cedro',email:'nora.cedro@example.test',category:'ADVISOR',scenario:'new',grants:new Set(),delegations:new Set(),manage:false,ack:false};wizardStep=0;renderWizard();showOnboarding();});
  }
  function renderWizard() {
    const content=$('onboarding-content');$('onboarding-feedback').textContent='';
    [...document.querySelectorAll('.wizard-steps li')].forEach((li,i)=>{if(i===wizardStep)li.setAttribute('aria-current','step');else li.removeAttribute('aria-current');});
    $('previous-onboarding').hidden=wizardStep===0;
    $('next-onboarding').textContent=wizardStep===3?'Confirmar solicitud simulada':'Continuar';
    $('next-onboarding').disabled=false;
    if(wizardStep===0){
      content.innerHTML=`<label class="wizard-field" for="member-name">Nombre completo ficticio<input id="member-name" autocomplete="off" maxlength="80" value="${esc(wizard.name)}"></label><label class="wizard-field" for="member-email">Correo ficticio<input id="member-email" type="email" autocomplete="off" maxlength="120" value="${esc(wizard.email)}" aria-describedby="fictional-email-hint"></label><p id="fictional-email-hint" class="group-note">Usa exclusivamente el dominio example.test. Nunca se consulta si una cuenta real existe.</p><label class="wizard-field" for="identity-case">Situación ficticia para revisar<select id="identity-case">${Object.entries(cases).map(([key,value])=>`<option value="${key}" ${wizard.scenario===key?'selected':''}>${value[0]}</option>`).join('')}</select></label>`;
      $('member-name').oninput=e=>{wizard.name=e.target.value;};$('member-email').oninput=e=>{wizard.email=e.target.value;};$('identity-case').onchange=e=>{wizard.scenario=e.target.value;};
    }else if(wizardStep===1){
      content.innerHTML=`<label class="wizard-field" for="member-category">Categoría interna<select id="member-category"><option value="ADVISOR">Asesor</option><option value="MASTER_ADMIN" ${mode==='director'?'':'disabled'}>Administrador maestro · sólo Director</option></select></label><p>Una sola incorporación guía identidad, categoría y permisos; la persona define su propia contraseña y aceptación legal. El administrador no las sustituye.</p><p class="pending-notice">Designar un Master requiere confirmación explícita del Director y un futuro contrato de seguridad reforzada. Esta pantalla no lo implementa.</p>`;
      $('member-category').value=wizard.category;$('member-category').onchange=e=>{wizard.category=mode==='director'?e.target.value:'ADVISOR';wizard.ack=false;wizard.grants.forEach(key=>{if(!wizardEligible(key))wizard.grants.delete(key);});if(wizard.category!=='MASTER_ADMIN'){wizard.manage=false;wizard.delegations.clear();}};
    }else if(wizardStep===2){
      content.innerHTML=`<h3>Permisos iniciales propuestos</h3><p class="group-note">Todos son solicitudes pendientes, sin autorización activa. Cada cambio será individual.</p><div id="onboarding-use">${familyHTML('onboard-use','use',wizard.grants,k=>wizardEligible(k),k=>future.has(k)?'Sin flujo conectado':restricted.has(k)&&wizard.category==='ADVISOR'?'Uso por asesores no habilitado':!wizardEligible(k)?'Fuera de tu delegación':'')}</div>${wizard.category==='MASTER_ADMIN'?`<details class="wizard-delegation"><summary>Administración de asesores y ámbitos delegados</summary><label class="ack-row" for="onboard-manage"><input id="onboard-manage" type="checkbox" ${wizard.manage?'checked':''}> Proponer autorización para administrar asesores</label><p class="group-note">Separada de sus permisos de uso. Sólo el Director puede configurarla.</p><div id="onboarding-delegate">${familyHTML('onboard-delegate','delegate',wizard.delegations,k=>wizardEligible(k,'delegate'),k=>wizardEligible(k,'delegate')?'':'Uso por asesores no habilitado o flujo pendiente')}</div></details>`:''}`;
      bindFamilies(content,kind=>kind==='delegate'?wizard.delegations:wizard.grants,wizardEligible,()=>{});
      if($('onboard-manage'))$('onboard-manage').onchange=e=>{wizard.manage=e.target.checked;};
    }else{
      const stage=cases[wizard.scenario];
      content.innerHTML=`<dl class="onboarding-summary"><dt>Persona ficticia</dt><dd>${esc(wizard.name)} · ${esc(wizard.email)}</dd><dt>Categoría propuesta</dt><dd>${categoryLabel(wizard.category)}</dd><dt>Estado pendiente propuesto</dt><dd>${stages[stage[1]]}</dd></dl>${listChanges('Permisos de uso solicitados',[...wizard.grants])}${wizard.category==='MASTER_ADMIN'?listChanges('Ámbitos delegados solicitados',[...wizard.delegations])+`<p>Gestión de asesores: ${wizard.manage?'solicitada':'no solicitada'}.</p>`:''}<p class="pending-notice">${stage[2]} Los pasos son independientes; una solicitud no activa a la persona ni confirma todos los permisos.</p>${wizard.category==='MASTER_ADMIN'?'<label class="ack-row" for="master-confirm"><input id="master-confirm" type="checkbox"> Como Director, confirmo expresamente la propuesta Master. Su autorización reforzada y ceremonia de seguridad siguen pendientes.</label>':''}`;
      if($('master-confirm')){$('master-confirm').checked=wizard.ack;$('next-onboarding').disabled=!wizard.ack;$('master-confirm').onchange=e=>{wizard.ack=e.target.checked;$('next-onboarding').disabled=!wizard.ack;};}
    }
    $('onboarding-title').textContent=`Agregar integrante · paso ${wizardStep+1} de 4`;
  }
  function nextWizard() {
    if(wizardStep===0 && (!wizard.name.trim() || !/^[^\s@]+@example\.test$/i.test(wizard.email))){$('onboarding-feedback').textContent='Escribe un nombre ficticio y un correo del dominio example.test.';return;}
    if(wizardStep<3){wizardStep++;renderWizard();$('onboarding-title').focus();return;}
    if(wizard.category==='MASTER_ADMIN' && (mode!=='director' || !wizard.ack))return;
    if(wizard.scenario==='duplicate'){$('onboarding-feedback').textContent=cases.duplicate[2];return;}
    const entry=fixture(`proposed-${++sequence}`,wizard.name.trim(),null,[],wizard.category);
    entry.stage=cases[wizard.scenario][1];entry.pendingMessage=cases[wizard.scenario][2];entry.requested=new Set(wizard.grants);entry.proposedDelegations=new Set(wizard.delegations);entry.proposedManagement=wizard.manage;entry.events=['Incorporación propuesta; sin activación ni entrega de mensajes.'];
    members.push(entry);selected=entry;category=entry.category;query='';$('advisor-search').value='';resetDraft();render();mobileView('detail');onboarding.close();say(`Solicitud simulada: ${stateLabel(entry)}. No se creó una cuenta, no se envió una invitación y no se concedieron permisos reales.`);wizard=null;focusDetail();
  }
  function render(){renderDirectory();renderDetail();}
  $('filter-advisors').onclick=()=>filter('ADVISOR');$('filter-masters').onclick=()=>filter('MASTER_ADMIN');
  $('advisor-search').oninput=e=>{query=e.target.value;renderDirectory();};
  $('show-directory').onclick=()=>{mobileView('directory');$('advisor-search').focus();};$('show-detail').onclick=()=>{mobileView('detail');focusDetail();};
  $('skip-detail').onclick=()=>mobileView('detail');
  $('authority-view').onchange=e=>{const next=e.target.value;$('authority-view').value=mode;transition(()=>{mode=next;$('authority-view').value=mode;category='ADVISOR';selected=members.find(m=>m.category==='ADVISOR');resetDraft();query='';$('advisor-search').value='';render();mobileView('directory');});};
  $('directory-state').onchange=e=>{const next=e.target.value;$('directory-state').value=directoryState;transition(()=>{directoryState=next;$('directory-state').value=next;resetDraft();render();});};
  $('add-member').onclick=openWizard;$('next-onboarding').onclick=nextWizard;$('previous-onboarding').onclick=()=>{wizardStep--;renderWizard();$('onboarding-title').focus();};$('cancel-onboarding').onclick=()=>{wizard=null;onboarding.close();};
  $('cancel-confirmation').onclick=()=>{pendingAction=null;confirmation.close();};
  $('accept-confirmation').onclick=()=>{if($('accept-confirmation').disabled)return;const action=pendingAction;pendingAction=null;confirmation.close();action?.();};
  onboarding.addEventListener('close',()=>{if(!onboarding.open)wizard=null;});
  confirmation.addEventListener('close',()=>{if(!confirmation.open)pendingAction=null;});
  window.addEventListener('beforeunload',e=>{if(dirty() || wizard){e.preventDefault();e.returnValue='';}});
  render();
})();
