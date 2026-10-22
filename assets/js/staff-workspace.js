(() => {
  'use strict';
  const $ = id => document.getElementById(id);
  const ROOT = '/api/internal/workspace/', LOGIN = '/public/identity/acceso.php?next=%2Finternal%2Fstaff-workspace%2Findex.php';
  const labels = {DOCTOR_PROFILE_PHOTO:'Foto de perfil',PHYSICIAN_PERSONAL_LOGO:'Logotipo profesional',DOCTOR_GALLERY:'Foto de galería'};
  const statuses = {draft:'Borrador',published:'Publicado',active:'Activo',inactive:'Inactivo',archived:'Archivado',hidden:'No publicado',pending_review:'En revisión',suspended:'Suspendido',removed:'Retirado',DRAFT:'Borrador',PUBLISHED:'Publicado',ACTIVE:'Activo',INACTIVE:'Inactivo',ARCHIVED:'Archivado',HIDDEN:'No publicado',PENDING_REVIEW:'En revisión',SUSPENDED:'Suspendido',REMOVED:'Retirado'};
  let state = null, doctor = null, section = 'summary', item = null, options = {}, busy = false, expired = false;
  let administrative=false, administrativePage={physicians:[],next_after:null};
  const externalRef=id=>typeof id==='string'&&id.includes(':');
  let directoryMode = 'pending', directoryEpoch = 0, contextEpoch = 0, itemEpoch = 0;
  let directoryController = null, contextController = null, itemController = null;
  let reviewUrl = null, proposalUrl = null, reviewLoaded = false, proposalLoaded = false, pollBusy = false, lastQuery = '';
  let requestState = {requests:[],pending_count:0}, selectedRequestId = null, requestDetail = null, decision = null;
  let requestEpoch = 0, historyEpoch = 0, evidenceEpoch = 0;
  let requestController = null, historyController = null, evidenceController = null, evidenceUrl = null, requestCsrf = null;
  let ownershipState = null, ownershipEpoch = 0, pendingState = null, pendingContext = null;
  const uncertainDecisions = new Map(), confirmedRequestUpdates = new Set(), directoryPositions = {pending:0,search:0};
  const requestStates = {SUBMITTED:'Enviada',PENDING_REVIEW:'Pendiente de revisión',UNDER_REVIEW:'En revisión',APPROVED:'Aprobada',REJECTED:'Rechazada',NEEDS_INFORMATION:'Información solicitada',DRAFT:'Borrador',WITHDRAWN:'Retirada',CANCELLED:'Cancelada',OWNERSHIP_PENDING:'Asignación de titular pendiente',COMPLETED:'Completada'};
  const valueLabels = {ready:'Disponible',NEEDS_WORK:'Requiere ajustes',CLAIM_EXISTING_PROFILE:'Reclamo de perfil existente',REQUEST_NEW_PROFILE:'Solicitud de perfil nuevo',collaborator:'Colaborador',administrator:'Administrador',owner:'Titular',internal_operator:'Operador interno',authenticated_account:'Persona solicitante',CONFIRMED:'Confirmada',UNLINKED:'Sin vincular',LINKED:'Vinculada',NOT_LINKED:'Sin vincular',ABSENT:'Sin perfil',HIDDEN_FREE:'Perfil no publicado y gratuito',RESPONSE_RECEIVED:'Respuesta recibida',NOT_APPROVED:'Sin aprobación',free:'Gratuito'};
  const valueLabel = value => valueLabels[value]||requestStates[value]||statuses[value]||labels[value]||value;
  const contextSelected = () => !!(doctor||selectedRequestId);
  const node = (tag, cls, text) => {const el=document.createElement(tag);if(cls)el.className=cls;if(text!==undefined)el.textContent=text;return el;};
  const dateLabel = value => {const m=/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}:\d{2})/.exec(value||'');return m?`${m[3]}/${m[2]}/${m[1]}, ${m[4]}`:'Fecha no disponible';};
  const describe = p => [p.specialty,[p.city,p.state].filter(Boolean).join(', ')].filter(Boolean).join(' · ');
  const mediaPath = (mode, d=doctor, s=item?.submission_id, mutation=false) => {const q=new URLSearchParams({doctor_id:d||'',mode});if(s&&!mutation)q.set('submission_id',s);return ROOT+'media.php?'+q;};
  const readPath = (d=null,query='') => ROOT+'read.php?'+new URLSearchParams({...d?{doctor_id:d}:{},...query?{query}:{} });
  const requestsPath = (mode='read',values={}) => ROOT+'requests.php?'+new URLSearchParams({mode,...Object.fromEntries(Object.entries(values).filter(([,v])=>v!==null&&v!==undefined&&v!==''))});
  const say = message => {$('workspace-feedback').textContent=message;};
  const mediaSay = message => {$('media-feedback').textContent=message;};
  const dirty = () => !!($('replacement-reason')?.value||$('replacement-feedback')?.value||$('corrected-file')?.files?.length||$('request-reason')?.value||$('request-password')?.value||$('request-mfa')?.value||$('request-confirmation')?.checked);
  function leaveDraft() {return !dirty()||window.confirm('Hay cambios sin guardar para este contexto. ¿Descartarlos y continuar?');}
  function resetDecision() {decision=null;$('request-decision-form').reset();$('request-decision-form').hidden=true;}
  function resetDraft() {$('replacement-form').reset();$('replacement-form').hidden=true;$('replacement-count').textContent='0 / 400 caracteres';$('corrected-file').value='';$('save-corrected').hidden=true;resetDecision();}
  function releaseImages() {for(const url of [reviewUrl,proposalUrl])if(url)URL.revokeObjectURL(url);reviewUrl=null;proposalUrl=null;reviewLoaded=false;proposalLoaded=false;if(!$('review-image'))return;$('review-image').removeAttribute('src');$('review-image').hidden=true;$('proposal-image').removeAttribute('src');$('proposal-panel').hidden=true;$('accept-improvement').hidden=true;$('discard-improvement').hidden=true;}
  function clearItem() {itemEpoch++;itemController?.abort();item=null;options={};releaseImages();resetDraft();$('media-detail').hidden=true;$('media-items').querySelectorAll('button').forEach(b=>b.setAttribute('aria-pressed','false'));}
  function clearContext() {administrative=false;operatorEpoch++;$('operators-content').replaceChildren();contextEpoch++;contextController?.abort();clearItem();clearRequest();doctor=null;pendingContext=null;section='summary';$('physician-workspace').hidden=true;$('no-selection').hidden=false;$('close-discovery').hidden=true;$('physician-name').textContent='';$('physician-description').textContent='';$('physician-statuses').replaceChildren();$('summary-facts').replaceChildren();$('media-items').replaceChildren();$('summary-pending').replaceChildren();$('section-tabs').replaceChildren();$('utilities').classList.remove('has-selection');$('search-toggle').hidden=true;$('physician-search-form').hidden=false;closeMore();}
  function sessionFailure(status) {
    expired=true;directoryEpoch++;directoryController?.abort();clearContext();state=null;$('discovery-list').replaceChildren();$('discovery').hidden=true;$('utilities').hidden=true;$('no-selection').hidden=true;$('pending-count').textContent='0';$('session-recovery').hidden=false;
    $('session-message').textContent=status===401?'Tu sesión terminó. Inicia sesión nuevamente para continuar.':'Tu acceso al equipo interno cambió. Vuelve a iniciar sesión o consulta al Director.';
    $('staff-name').textContent='Sesión no disponible';$('staff-role').textContent='';$('staff-logout').hidden=true;say('');
  }
  async function request(url, init={}) {
    const response=await fetch(url,{credentials:'same-origin',cache:'no-store',...init});
    if(response.status===401){sessionFailure(401);throw Object.assign(new Error('session'),{status:401});}
    return response;
  }
  async function json(url, init={}) {const response=await request(url,init);let result;try{result=await response.json();}catch{throw new Error('unavailable');}if(!response.ok||!result.ok)throw Object.assign(new Error('unavailable'),{status:response.status,payload:result});return result;}
  function actor(data) {
    $('staff-name').textContent=data.actor.display_name||data.actor.email||'Cuenta del equipo interno';
    $('staff-role').textContent=({DIRECTOR:'Director',MASTER_ADMIN:'Administrador Master',ADVISOR:'Asesor'}[data.actor.access_level]||data.actor.access_level);
    updatePendingBadge();
  }
  function updatePendingBadge(){
    const count=pendingState?.actionable_count;$('pending-count').textContent=count===undefined?'—':String(count);$('pending-count').setAttribute('aria-label',count===undefined?'Conteo no disponible':`${count} tareas pendientes en tu ámbito de actuación`);
  }
  function updateContext(data) {
    const p=data.physician;
    const developmentContext=$('development-physician-context');if(developmentContext)developmentContext.textContent=p?'Contexto autorizado: '+p.name+' · '+p.doctor_id:'Sin perfil médico seleccionado.';
    if(!doctor&&selectedRequestId){$('context-type').textContent='SOLICITUD DE PERFIL NUEVO';$('physician-name').textContent='Solicitud de perfil nuevo';$('physician-description').textContent=requestDetail?.proposed_profile?.display_name||requestDetail?.label||'Revisión de la solicitud seleccionada';$('physician-statuses').replaceChildren(node('span','badge review-badge',requestStates[requestDetail?.state]||requestDetail?.state||'Solicitud seleccionada'));const n=contextPendingCount();if(n!==null)$('physician-statuses').append(node('span','badge review-badge',`${n} pendientes visibles`));return;}
    if(!p||p.doctor_id!==doctor)return;
    $('context-type').textContent='PERFIL MÉDICO';
    $('physician-name').textContent=p.name;$('physician-description').textContent=describe(p)||'Información profesional disponible en el perfil';
    const badges=$('physician-statuses');badges.replaceChildren();
    if(p.status)badges.append(node('span','badge publication-badge','Perfil: '+(statuses[p.status]||p.status)));
    const pending=contextPendingCount(data);if(pending!==null)badges.append(node('span','badge review-badge',`${pending} ${pending===1?'pendiente visible':'pendientes visibles'}`));
    if(p.plan?.label)badges.append(node('span','badge','Plan: '+p.plan.label));
  }
  function discovery(data) {
    const list=$('discovery-list');list.replaceChildren();$('discovery-eyebrow').textContent=directoryMode==='search'?'RESULTADOS DE BÚSQUEDA':'TRABAJO DISPONIBLE';$('discovery-title').textContent=directoryMode==='search'?'Médicos y solicitudes disponibles':'Pendientes por atender';
    const accessible=requestState.requests||[];
    let entries;
    if(directoryMode==='search'){
      const query=lastQuery.toLocaleLowerCase('es-MX');const profiles=new Map([...(data.physicians||[]),...administrativePage.physicians].map(p=>[p.doctor_id,p]));const applications=[];
      for(const entry of accessible){const name=entry.label||entry.physician_name||entry.proposed_profile?.display_name||'Solicitud de perfil nuevo';if(query&&!name.toLocaleLowerCase('es-MX').includes(query))continue;if(entry.doctor_id){if(!profiles.has(entry.doctor_id))profiles.set(entry.doctor_id,{...entry,name});}else applications.push({...entry,name});}
      entries=[...profiles.values(),...applications];
    }else entries=pendingState?.groups||[];
    const appendCard=(entry,awareness=false)=>{
      const button=node('button','discovery-card');button.type='button';if(entry.doctor_id)button.dataset.doctorId=entry.doctor_id;
      const application=entry.application_id||entry.request_id;if(application&&!entry.doctor_id)button.dataset.requestId=application;
      if(entry.key)button.dataset.groupKey=entry.key;
      const copy=node('span');copy.append(node('strong','',entry.doctor_id?(entry.name||entry.physician_name||entry.label||'Perfil médico'):'Solicitud de perfil nuevo'));
      if(!entry.doctor_id)copy.append(node('span','card-meta',entry.name||entry.label));
      const meta=entry.doctor_id?describe(entry):(requestStates[entry.state]||'');if(meta)copy.append(node('span','card-meta',meta));
      if(entry.sections){
        copy.append(node('span','card-count',`${entry.visible_count} pendientes visibles · ${entry.actionable_count} para atender`));
        const categories=Object.entries(entry.sections).filter(([,v])=>v.visible_count>0).map(([key,v])=>`${key==='images'?'Imágenes':'Solicitudes'}: ${v.visible_count}${v.summary_only?' · acceso restringido':''}`);
        copy.append(node('span','card-meta',categories.join(' / ')));
      }else if(entry.state)copy.append(node('span','card-meta',requestStates[entry.state]||entry.state));
      if(awareness)copy.append(node('span','card-meta','Seguimiento: requiere otra intervención o respuesta.'));
      button.append(copy,node('span','card-action',entry.doctor_id?'Abrir perfil →':'Abrir solicitud →'));
      button.onclick=()=>{directoryPositions[directoryMode]=list.scrollTop;entry.doctor_id?(externalRef(entry.doctor_id)?selectAdministrative(entry.doctor_id):selectPhysician(entry.doctor_id)):selectRequest(application);};list.append(button);
    };
    for(const entry of entries||[])appendCard(entry);
    if(directoryMode==='pending'&&pendingState?.awareness_groups?.length){
      list.append(node('h3','awareness-heading','Otros pendientes visibles para seguimiento'));
      for(const entry of pendingState.awareness_groups)appendCard(entry,true);
    }
    $('discovery-message').textContent=directoryMode==='search'?`${entries.length} resultados disponibles`:pendingState?`${pendingState.actionable_count} tareas por atender en ${pendingState.group_count} perfiles o solicitudes. El círculo rojo cuenta tareas que puedes atender.${pendingState.has_more?' Se muestran los primeros 100 grupos; utiliza la búsqueda para los demás.':''}`:'No fue posible verificar los conteos. Las secciones autorizadas siguen disponibles mediante la búsqueda.';
    if(directoryMode==='search'&&administrativePage.unavailable)$('discovery-message').textContent+=' · La fuente administrativa no está disponible; sus resultados no se muestran.';
    if(directoryMode==='search'&&administrativePage.next_after){const more=node('button','task-row','Más perfiles de la fuente administrativa');more.onclick=async()=>{const epoch=directoryEpoch;try{const r=(await json(requestsPath('admin_search',{query:lastQuery,after:administrativePage.next_after}))).data;if(epoch!==directoryEpoch)return;administrativePage=r;discovery(data);}catch{administrativePage={physicians:[],next_after:null};say('No fue posible consultar la siguiente página.');discovery(data);}};list.append(more);}
    list.setAttribute('aria-busy','false');list.scrollTop=directoryPositions[directoryMode];
  }
  async function loadRequestDirectory(signal) {
    try {const result=await json(requestsPath(),signal?{signal}:{});requestCsrf=result.csrf_token||result.data.csrf_token||null;return result.data;}
    catch(error){if(error.name==='AbortError'||error.status===401)throw error;if(error.status===403)return {requests:[],pending_count:0};return {requests:[],pending_count:0,unavailable:true};}
  }
  async function loadPending(d=null,id=null,signal) {
    try {return (await json(requestsPath('pending',{doctor_id:d,request_id:d?null:id}),signal?{signal}:{})).data;}
    catch(error){if(error.name==='AbortError'||error.status===401)throw error;return null;}
  }
  function acceptPending(value,d=doctor,id=selectedRequestId){
    pendingState=value;const c=value?.context;
    if(selectedRequestId&&!(requestState.capabilities||[]).includes('physician_request_read'))clearRequest();
    pendingContext=c&&((d&&c.doctor_id===d)||(!d&&id&&c.application_id===id))?c:null;
  }
  function contextProfile(data) {
    if(!data.physician&&pendingContext?.doctor_id)data.physician={doctor_id:pendingContext.doctor_id,name:pendingContext.name,specialty:pendingContext.specialty,city:pendingContext.city,state:pendingContext.state,status:pendingContext.status};
    return data;
  }
  async function loadDirectory(query=lastQuery) {
    if(administrative)await refreshAdministrative();
    const epoch=++directoryEpoch;directoryController?.abort();directoryController=new AbortController();$('discovery-list').setAttribute('aria-busy','true');
    try {administrativePage={physicians:[],next_after:null};if(directoryMode==='search'){try{administrativePage=(await json(requestsPath('admin_search',{query}),{signal:directoryController.signal})).data;}catch(e){if(e.name==='AbortError'||e.status===401)throw e;if(e.status!==403)administrativePage.unavailable=true;}}const selectedEpoch=contextEpoch;const [{data},requests,counts,current]=await Promise.all([json(readPath(null,query),{signal:directoryController.signal}),loadRequestDirectory(directoryController.signal),loadPending(administrative?null:doctor,selectedRequestId,directoryController.signal),contextSelected()&&!administrative?mediaContext(doctor,directoryController.signal):Promise.resolve(null)]);if(epoch!==directoryEpoch||selectedEpoch!==contextEpoch||expired)return;requestState=requests;acceptPending(counts);actor(data);if(!data.sections.length&&!requests.requests?.length&&contextSelected()&&!administrative){clearContext();state=data;say('Tus permisos cambiaron. Ya no hay trabajo accesible en este espacio.');}if(requests.unavailable)say(data.sections.length?'No fue posible consultar solicitudes. Puedes continuar con las imágenes disponibles para tu cuenta.':'No fue posible consultar solicitudes. Intenta actualizar en unos momentos.');discovery(data);if(!state)state=data;else if(contextSelected()&&current){
        contextProfile(current);
        if(doctor&&!current.physician&&contextRequests().length)current.physician=state.physician;
        if(doctor&&!current.physician){clearContext();state=data;say('Tu acceso al contexto cambió. Selecciona un perfil disponible.');return;}
        state=current;if(item&&!state.pending_items.some(v=>v.submission_id===item.submission_id))clearItem();renderPanels();renderTabs();
      }}
    catch(error){if(error.name==='AbortError'||epoch!==directoryEpoch)return;if(error.status===403){sessionFailure(403);return;}$('discovery-message').textContent='No fue posible actualizar la lista. Intenta nuevamente.';$('discovery-list').setAttribute('aria-busy','false');}
  }
  function collapseDiscovery() {directoryPositions[directoryMode]=$('discovery-list').scrollTop;$('discovery').hidden=true;$('pending-toggle').setAttribute('aria-expanded','false');$('search-toggle').setAttribute('aria-expanded','false');$('search-toggle').hidden=!contextSelected();$('physician-search-form').hidden=contextSelected();$('utilities').classList.toggle('has-selection',contextSelected());}
  function showDiscovery(mode) {directoryPositions[directoryMode]=$('discovery-list').scrollTop;directoryMode=mode;$('discovery').hidden=false;$('pending-toggle').setAttribute('aria-expanded',mode==='pending'?'true':'false');$('search-toggle').setAttribute('aria-expanded',mode==='search'?'true':'false');$('physician-search-form').hidden=contextSelected()&&mode!=='search';$('close-discovery').hidden=!contextSelected();loadDirectory(mode==='search'?lastQuery:'');}
  async function selectPhysician(id, submission=null) {
    if(busy||!leaveDraft())return;clearContext();doctor=id;const epoch=++contextEpoch;contextController=new AbortController();$('no-selection').hidden=true;say('Cargando perfil…');
    try {
      const [data,counts,requests]=await Promise.all([mediaContext(id,contextController.signal),loadPending(id,null,contextController.signal),loadRequestDirectory(contextController.signal)]);if(epoch!==contextEpoch||doctor!==id||expired)return;requestState=requests;acceptPending(counts,id,null);contextProfile(data);actor(data);
      if(!data.physician||data.physician.doctor_id!==id){clearContext();say('Este perfil ya no está disponible para tu cuenta.');showDiscovery('pending');return;}
      state=data;section=submission?'images':'summary';$('physician-workspace').hidden=false;collapseDiscovery();$('close-discovery').hidden=false;updateContext(data);renderPanels();renderTabs();say('');
      if(submission){const selected=data.pending_items.find(v=>v.submission_id===submission);if(selected)await selectItem(selected,false);else say('El pendiente cambió o ya fue atendido. Se actualizó el perfil.');}
      $('physician-name').focus({preventScroll:true});$('physician-workspace').scrollIntoView({block:'start',behavior:'instant'});
    }catch(error){if(error.name==='AbortError'||epoch!==contextEpoch)return;clearContext();say(error.status===403?'Este perfil ya no está disponible para tu cuenta.':'No fue posible abrir el perfil. Intenta nuevamente.');showDiscovery('pending');}
  }
  async function selectAdministrative(ref) {
    if(busy||!leaveDraft())return;clearContext();doctor=ref;administrative=true;const epoch=++contextEpoch;
    try {const data=(await json(requestsPath('admin_summary',{profile_ref:ref}))).data;if(epoch!==contextEpoch||doctor!==ref||expired)return;
      if(data.physician.doctor_id!==ref)throw new Error('context');state=data;section='summary';pendingContext=null;$('no-selection').hidden=true;$('physician-workspace').hidden=false;collapseDiscovery();renderPanels();renderTabs();say('Consulta administrativa de la fuente original.');
    }catch(e){if(epoch!==contextEpoch)return;clearContext();say('No fue posible confirmar el acceso al perfil de origen.');}
  }
  async function refreshAdministrative(){
    const ref=doctor,epoch=contextEpoch;if(!administrative)return;
    try{const [response,counts]=await Promise.all([json(requestsPath('admin_summary',{profile_ref:ref})),loadPending(null)]);const data=response.data;if(epoch!==contextEpoch||doctor!==ref)return;pendingState=counts;pendingContext=null;updatePendingBadge();if(data.physician.doctor_id!==ref)throw new Error('context');state=data;renderPanels();renderTabs();if(section==='operators')loadOperators();}
    catch(e){if(epoch!==contextEpoch)return;clearContext();say('La fuente o tu autorización ya no están disponibles. Vuelve a buscar un perfil autorizado.');}
  }
  function pendingButton(record) {const button=node('button','task-row');button.type='button';button.dataset.submissionId=record.submission_id;button.dataset.doctorId=doctor;button.append(node('span','',labels[record.purpose]||record.label||'Imagen pendiente'),node('span','','Revisar imagen →'));button.onclick=async()=>{if(busy||!leaveDraft())return;resetDraft();section='images';renderTabs();showSection();await selectItem(record,false);};return button;}
  function renderPanels() {
    updateContext(state);const p=state.physician,facts=$('summary-facts');facts.replaceChildren();const requests=contextRequests();
    for(const [label,value] of [['Estado del perfil',p?(statuses[p.status]||p.status||'Sin estado registrado'):'Sin perfil operativo'],['Pendientes visibles',contextPendingCount()===null?'Sin verificar':String(contextPendingCount())]]){const fact=node('div','fact');fact.append(node('span','',label),node('strong','',value));facts.append(fact);}
    if(administrative){for(const [label,value] of [['Origen',p.origin],['Identificador canónico',p.canonical_id],['Publicación','No verificada por esta proyección'],['Consultorios',p.consultorios.length+(p.consultorios_has_more?' o más':'')]]){const f=node('div','fact');f.append(node('span','',label),node('strong','',String(value)));facts.append(f);}for(const c of p.consultorios)facts.append(node('p','',`${c.titulo||'Consultorio'} · ${[c.municipio,c.estado].filter(Boolean).join(', ')}`));}
    const tasks=$('summary-pending');tasks.replaceChildren();for(const record of state.pending_items)tasks.append(pendingButton(record));for(const record of requests.filter(r=>r.actionable))tasks.append(requestButton(record));if(!tasks.childElementCount)tasks.append(node('p','muted',administrative?'Consulta administrativa independiente. Los pendientes de esta fuente no están integrados.':'No hay pendientes de revisión para este contexto.'));
    const activity=$('activity-list');activity.replaceChildren();$('recorded-activity').hidden=!state.activity?.length;for(const record of state.activity||[])activity.append(node('p','',`${record.label||labels[record.purpose]||'Imagen'} · ${({APPROVED:'Aprobada',NEEDS_WORK:'Otra imagen solicitada',REJECTED:'Rechazada',WITHDRAWN:'Retirada'}[record.status]||record.status)} · ${dateLabel(record.recorded_at)}`));
    const media=$('media-items');media.replaceChildren();$('images-empty').hidden=state.pending_items.length>0;
    for(const record of state.pending_items){const button=node('button','media-item');button.type='button';button.dataset.submissionId=record.submission_id;button.setAttribute('aria-pressed',String(item?.submission_id===record.submission_id));button.append(node('strong','',labels[record.purpose]||'Imagen'),node('span','',dateLabel(record.created_at)));button.onclick=()=>selectItem(record);media.append(button);}
    const published=$('published-list');published.replaceChildren();$('published-media').hidden=!state.published_assets?.length;
    for(const record of state.published_assets||[]){const card=node('div','published-item');if(typeof record.public_url==='string'&&record.public_url){try{const url=new URL(record.public_url,location.origin);if(url.origin===location.origin){const image=node('img');image.src=url.href;image.alt=record.alt_text||labels[record.purpose]||'Imagen publicada';image.loading='lazy';card.append(image);}}catch{/* Invalid public projections are not requested. */}}card.append(node('span','',labels[record.purpose]||'Imagen publicada'));published.append(card);}
    renderRequestList();showSection();
  }
  function showSection() {
    const entry=workspaceSections().find(v=>v.id===section),restricted=entry?.restricted;
    for(const id of ['summary','requests','images','history','operators'])$(id+'-panel').hidden=restricted||section!==id;
    $('restricted-panel').hidden=!restricted;
    if(restricted){$('restricted-title').textContent=entry.label;$('restricted-message').textContent=administrative?'Esta sección no tiene un lector autorizado para el origen seleccionado. No se consulta otro perfil.':['requests','images','history','operators'].includes(section)?'Esta sección requiere autorización para realizar operaciones.':'Pendiente de integración para el Asesor. Esta área aún no permite consultar ni modificar información desde este panel. Las funciones de médico, operador y proveedor conservan sus accesos independientes.';
      const n=sectionCount(section);$('restricted-summary').textContent=n>0?`${n} ${n===1?'pendiente visible':'pendientes visibles'} bajo tu permiso de resumen. Solicita atención a una persona autorizada mediante los canales habituales.`:'No hay un resumen de pendientes disponible para tu cuenta en esta sección.';
    }
    $('work-panel').setAttribute('aria-labelledby','tab-'+section);$('work-panel').dataset.doctorId=doctor||'';$('work-panel').dataset.requestId=selectedRequestId||'';$('work-panel').dataset.section=section;
  }
  const tabCatalog=[['summary','Resumen'],['requests','Solicitudes'],['profile','Perfil'],['accreditation','Acreditación'],['offices','Consultorios'],['services','Servicios y atención'],['images','Imágenes'],['agenda','Agenda'],['operators','Operadores y colaboradores'],['plan','Plan y servicios contratados'],['billing','Facturación y pagos'],['reviews','Opiniones'],['seo','SEO'],['account','Soporte de cuenta'],['history','Historial']];
  function workspaceSections() {
    if(!contextSelected())return [];
    if(administrative)return tabCatalog.map(([id,label])=>({id,label,restricted:id!=='summary'&&!(id==='operators'&&state?.permissions?.operators)}));
    return tabCatalog.map(([id,label])=>({id,label,restricted:id!=='summary'&&!(pendingContext?.permissions?.[id]??(id==='images'?doctor&&state?.sections?.some(t=>t.id==='images'):id==='requests'?contextRequests().length||selectedRequestId:id==='history'?contextRequests().length||state?.sections?.some(t=>t.id==='images'):false))}));
  }
  function sectionCount(id){return pendingContext?.sections?.[id]?.visible_count||0;}

  function closeMore() {$('more-menu').hidden=true;$('more-toggle').setAttribute('aria-expanded','false');}
  function renderTabs() {
    if(!state?.sections)return;const tabs=$('section-tabs'),menu=$('more-menu'),scroll=tabs.scrollLeft;tabs.replaceChildren();menu.replaceChildren();closeMore();
    for(const entry of workspaceSections()){
      const button=node('button','section-tab');button.append(node('span','',entry.label));button.type='button';button.id='tab-'+entry.id;button.dataset.section=entry.id;
      const n=sectionCount(entry.id);if(n)button.append(node('span','tab-count',String(n)));
      if(entry.restricted){button.append(node('span','tab-lock','◇'));button.classList.add('restricted-tab');button.title='Acceso restringido';}
      const label=entry.label+(n?`, ${n} pendientes`:'')+(entry.restricted?', acceso restringido':'');button.setAttribute('aria-label',label);
      button.setAttribute('role','tab');button.setAttribute('aria-selected',String(entry.id===section));button.setAttribute('aria-controls','work-panel');button.tabIndex=entry.id===section?0:-1;button.onclick=()=>switchSection(entry.id);tabs.append(button);
      const option=node('button','',label);option.type='button';option.dataset.section=entry.id;option.setAttribute('role','menuitemradio');option.setAttribute('aria-checked',String(entry.id===section));option.onclick=()=>switchSection(entry.id);menu.append(option);
    }
    tabs.scrollLeft=scroll;$('more-sections').hidden=false;showSection();
  }

  function switchSection(id) {if(busy||id===section){closeMore();return;}if(!leaveDraft())return;resetDraft();section=id;renderTabs();showSection();if(!workspaceSections().find(v=>v.id===id)?.restricted){if(id==='history')loadHistory();if(id==='operators')loadOperators();if(id==='requests'&&selectedRequestId)refreshRequest();}const tab=$('tab-'+id);(tab&&!tab.hidden?tab:$('more-toggle')).focus({preventScroll:true});if(tab){const tabs=$('section-tabs');if(tab.offsetLeft<tabs.scrollLeft)tabs.scrollLeft=tab.offsetLeft;else if(tab.offsetLeft+tab.offsetWidth>tabs.scrollLeft+tabs.clientWidth)tabs.scrollLeft=tab.offsetLeft+tab.offsetWidth-tabs.clientWidth;}}
  function requestType(type) {return /CLAIM/.test(type||'')?'Reclamo de perfil existente':'Solicitud de perfil nuevo';}
  function contextRequests() {return (requestState.requests||[]).filter(r=>doctor?r.doctor_id===doctor:r.request_id===selectedRequestId);}
  function contextPendingCount() {return pendingContext?.visible_count??null;}
  function requestButton(record) {
    const button=node('button','task-row');button.type='button';button.dataset.requestId=record.request_id;
    button.append(node('span','',`${requestType(record.type)} · ${requestStates[record.state]||record.state}`),node('span','','Revisar solicitud →'));
    button.onclick=()=>selectRequest(record.request_id);return button;
  }
  function renderRequestList() {const list=$('request-list');list.replaceChildren();for(const r of contextRequests())list.append(requestButton(r));$('request-empty').hidden=!!list.childElementCount||!!selectedRequestId;}
  function closeEvidence() {
    evidenceEpoch++;evidenceController?.abort();if(evidenceUrl)URL.revokeObjectURL(evidenceUrl);evidenceUrl=null;
    $('evidence-image').removeAttribute('src');$('evidence-image').hidden=true;if($('evidence-dialog').open)$('evidence-dialog').close();
  }
  function clearRequest() {
    requestEpoch++;requestController?.abort();historyEpoch++;historyController?.abort();closeEvidence();
    ownershipEpoch++;ownershipState=null;selectedRequestId=null;requestDetail=null;resetDecision();$('request-detail').hidden=true;$('request-list').replaceChildren();$('request-feedback').textContent='';$('request-outcome').textContent='';$('request-recovery').hidden=true;$('request-ownership').hidden=true;$('history-list').replaceChildren();$('history-message').textContent='';
  }
  async function requestSnapshot(id,signal) {const result=await json(requestsPath('read',{request_id:id}),signal?{signal}:{});if(!result.data?.request||result.data.request.request_id!==id)throw Object.assign(new Error('request'),{status:404});requestCsrf=result.csrf_token||result.data.csrf_token||null;return result.data.request;}
  function requestPhysician(detail) {
    if(!detail.doctor_id)return null;const p=detail.existing_profile||{};
    return {...p,doctor_id:detail.doctor_id,name:p.name||p.display_name||detail.proposed_profile?.display_name||'Perfil médico',status:p.profile_status||p.status,pending_count:0};
  }
  async function mediaContext(d,signal) {
    try {return (await json(readPath(d),{signal})).data;}
    catch(error){if(error.status!==404)throw error;const base=(await json(readPath(),{signal})).data;return {...base,physician:null,pending_items:[],activity:[],published_assets:[],sections:[]};}
  }
  async function selectRequest(id) {
    if(busy||!leaveDraft())return;clearContext();selectedRequestId=id;const epoch=++contextEpoch,revision=++requestEpoch;
    contextController=new AbortController();$('no-selection').hidden=true;say('Cargando solicitud…');
    try {
      const detail=await requestSnapshot(id,contextController.signal);if(epoch!==contextEpoch||revision!==requestEpoch||expired)return;
      const [data,counts]=await Promise.all([mediaContext(detail.doctor_id||null,contextController.signal),loadPending(detail.doctor_id||null,id,contextController.signal)]);if(epoch!==contextEpoch||selectedRequestId!==id||expired)return;
      requestDetail=detail;doctor=detail.doctor_id||null;acceptPending(counts,doctor,id);state=data;if(doctor&&!state.physician)state.physician=requestPhysician(detail);
      section='requests';actor(state);$('physician-workspace').hidden=false;collapseDiscovery();$('close-discovery').hidden=false;
      renderPanels();renderTabs();renderRequest();if(detail.state==='APPROVED')await loadOwnership();if(epoch!==contextEpoch||id!==selectedRequestId)return;say('');$('physician-name').focus({preventScroll:true});$('physician-workspace').scrollIntoView({block:'start',behavior:'instant'});
    }catch(error){if(error.name==='AbortError'||epoch!==contextEpoch)return;clearContext();say(error.status===403?'Tu cuenta ya no puede consultar esta solicitud.':'La solicitud no está disponible. Actualiza la lista antes de continuar.');showDiscovery('pending');}
  }
  function appendFacts(container,title,values) {
    const card=node('section','request-facts');card.append(node('h4','',title));const list=node('dl');
    for(const [label,value] of values){if(value===undefined||value===null||value==='')continue;list.append(node('dt','',label),node('dd','',String(value)));}
    card.append(list);container.append(card);
  }
  function requestActions() {return new Set((requestDetail?.allowed_actions||[]).map(action=>typeof action==='string'?action:action.decision));}
  function renderRequest() {
    const r=requestDetail;if(!r||r.request_id!==selectedRequestId)return;
    $('request-detail').hidden=false;$('request-detail').dataset.requestId=r.request_id;$('request-kind').textContent=requestType(r.type);
    $('request-heading').textContent=r.proposed_profile?.display_name||r.existing_profile?.display_name||r.existing_profile?.name||r.label||requestType(r.type);
    $('request-reference').textContent=`Solicitud ${r.request_id} · Enviada ${dateLabel(r.submitted_at)}`;
    $('request-status').textContent=requestStates[r.state]||r.state;
    const comparison=$('request-comparison');comparison.replaceChildren();
    const p=r.existing_profile||r.proposed_profile||{};
    appendFacts(comparison,r.existing_profile?'Perfil existente':'Información propuesta',[['Nombre del perfil',p.display_name||p.name],['Especialidad',p.specialty],['Cédula profesional',p.professional_license],['Ubicación',p.location||[p.city,p.state].filter(Boolean).join(', ')],['Estado',valueLabel(p.profile_status||p.status)],['Plan',valueLabel(p.plan?.label||p.plan)]]);
    const a=r.applicant||{};appendFacts(comparison,'Persona solicitante',[['Identidad disponible',a.label||a.display_name],['Cuenta vinculada',a.account_id],['Correo',a.email],['Correo verificado',a.email_verified===true?'Sí':a.email_verified===false?'No':'Sin dato disponible']]);
    if(r.existing_profile&&r.proposed_profile)appendFacts(comparison,'Información declarada en la solicitud',[['Nombre declarado',r.proposed_profile.display_name],['Especialidad declarada',r.proposed_profile.specialty],['Cédula declarada',r.proposed_profile.professional_license],['Ubicación declarada',r.proposed_profile.location]]);
    const verification=$('request-verification-facts');verification.replaceChildren();
    if(r.verification?.label)verification.append(node('p','',r.verification.label));
    if(r.evidence_status)verification.append(node('p','muted','Estado de evidencia: '+valueLabel(r.evidence_status)+'.'));
    for(const evidence of r.evidence||[])verification.append(node('p','muted',`${evidence.label||'Evidencia'} · ${evidence.status_label||valueLabel(evidence.status)||'Recibida'}`));
    if(r.has_evidence_access===false)verification.append(node('p','muted','La consulta de documentos requiere un permiso de evidencia específico.'));
    else if(!r.evidence?.length)verification.append(node('p','muted','No hay evidencia disponible para tu cuenta.'));
    const evidenceList=$('request-evidence');evidenceList.replaceChildren();
    for(const evidence of r.evidence||[]){if(!evidence.url)continue;const button=node('button','',`Consultar ${evidence.label||'evidencia'}`);button.type='button';button.dataset.evidenceId=evidence.id;button.onclick=()=>openEvidence(evidence);evidenceList.append(button);}
    const coincidences=$('request-coincidence-list');coincidences.replaceChildren();$('request-coincidences').hidden=!r.warnings?.length;
    for(const warning of r.warnings||[])coincidences.append(node('p','feedback',typeof warning==='string'?warning:warning.label||warning.message||'Posible coincidencia: requiere resolución explícita.'));
    const correspondence=$('request-correspondence-list');correspondence.replaceChildren();$('request-correspondence').hidden=!r.information_history?.length;
    for(const entry of r.information_history||[]){const row=node('article','');row.append(node('p','muted',dateLabel(entry.at||entry.created_at||entry.date)),node('p','',entry.reason||entry.question||entry.message||entry.label||''));if(entry.response)row.append(node('p','',`Respuesta: ${typeof entry.response==='string'?entry.response:entry.response.text}`));correspondence.append(row);}
    const outcome=r.outcome||{};const parts=[];
    if(r.state==='APPROVED')parts.push('Solicitud aprobada.');
    if(outcome.label||outcome.message||outcome.ownership_label)parts.push(outcome.label||outcome.message||outcome.ownership_label);
    else if(outcome.ownership_status)parts.push('Titularidad: '+({ACTIVE:'Activa',OWNERSHIP_ACTIVE:'Activa para la persona solicitante',PENDING:'Pendiente de completar por la persona solicitante',OWNERSHIP_PENDING:'Pendiente de acreditación y aceptación de la persona solicitante',NOT_APPROVED:'Sin aprobación',PENDING_APPLICANT_CONFIRMATION:'Pendiente de confirmación de la persona solicitante',PENDING_ACCREDITATION:'Pendiente de acreditación independiente',NOT_ACTIVATED:'Aún no activada'}[outcome.ownership_status]||outcome.ownership_status)+'.');
    if(outcome.profile_status)parts.push('Perfil: '+(statuses[outcome.profile_status]||outcome.profile_status)+'.');
    if(outcome.recovery_guidance)parts.push(outcome.recovery_guidance);
    if(r.state==='APPROVED'&&!outcome.ownership_status&&!outcome.label&&!outcome.message)parts.push('La aprobación no confirma por sí sola la asignación de titularidad.');
    $('request-outcome').textContent=parts.join(' ');updateContext(state);requestControls();
    $('request-ownership').hidden=r.state!=='APPROVED';
  }
  function requestControls() {
    if(!$('request-detail'))return;const allowed=requestActions(),uncertain=uncertainDecisions.has(selectedRequestId);
    const entries=[['approve-request','APPROVED'],['request-information','NEEDS_INFORMATION'],['reject-request','REJECTED']];
    for(const [id,value] of entries){$(id).hidden=!allowed.has(value);$(id).disabled=busy||uncertain;}
    $('approve-request').textContent=/CLAIM/.test(requestDetail?.type||'')?'Aprobar reclamo':'Aprobar perfil nuevo';
    $('request-recovery').hidden=!uncertain;
    for(const input of $('request-decision-form').querySelectorAll('button,input,textarea'))input.disabled=busy||uncertain;
    for(const button of $('request-evidence').querySelectorAll('button'))button.disabled=busy;
    $('refresh-requests').disabled=busy;$('refresh-history').disabled=busy;$('lookup-request-result').disabled=busy;
    $('continue-ownership').disabled=busy||uncertain;$('refresh-ownership').disabled=busy;
  }
  async function refreshRequest() {
    if(!selectedRequestId||busy||expired)return;const id=selectedRequestId,epoch=contextEpoch,revision=++requestEpoch;
    requestController?.abort();requestController=new AbortController();
    try {const next=await requestSnapshot(id,requestController.signal);if(epoch!==contextEpoch||revision!==requestEpoch||id!==selectedRequestId)return;
      const changed=requestDetail&&(requestDetail.revision_token!==next.revision_token||JSON.stringify(requestDetail.allowed_actions)!==JSON.stringify(next.allowed_actions));
      const ownUpdate=confirmedRequestUpdates.delete(id);
      if(changed){resetDecision();closeEvidence();if(!ownUpdate)$('request-feedback').textContent='La solicitud, su evidencia o tus permisos cambiaron. Revisa la información actual antes de decidir.';}
      requestDetail=next;if(next.doctor_id&&next.doctor_id!==doctor){doctor=next.doctor_id;const data=await mediaContext(doctor,requestController.signal);if(epoch!==contextEpoch||revision!==requestEpoch)return;state=data;if(!state.physician)state.physician=requestPhysician(next);}
      renderRequest();renderPanels();renderTabs();if(next.state==='APPROVED')await loadOwnership();
    }catch(error){if(error.name==='AbortError'||epoch!==contextEpoch||revision!==requestEpoch)return;resetDecision();closeEvidence();requestDetail=null;$('request-detail').hidden=true;if(error.status===403||error.status===404){clearContext();showDiscovery('pending');}say('No fue posible revalidar el acceso a la solicitud. Actualiza tus pendientes.');}
  }
  async function openEvidence(evidence) {
    if(busy||!requestDetail)return;let url;try{url=new URL(evidence.url,location.origin);if(url.origin!==location.origin||url.pathname!==ROOT+'requests.php')throw new Error('evidence');}catch{return;}
    closeEvidence();const revision=++evidenceEpoch,id=selectedRequestId,epoch=contextEpoch;evidenceController=new AbortController();
    $('evidence-title').textContent=evidence.label||'Evidencia de la solicitud';$('evidence-context').textContent=`${$('physician-name').textContent} · Solicitud ${id}`;$('evidence-message').textContent='Cargando evidencia protegida…';$('evidence-dialog').showModal();
    try {const response=await request(url.href,{signal:evidenceController.signal});if(!response.ok)throw new Error('evidence');const mime=(response.headers.get('Content-Type')||'').split(';')[0];if(!['image/jpeg','image/png','image/webp'].includes(mime))throw new Error('type');const blob=await response.blob();if(epoch!==contextEpoch||revision!==evidenceEpoch||id!==selectedRequestId||blob.size>15*1024*1024)return;evidenceUrl=URL.createObjectURL(blob);$('evidence-image').src=evidenceUrl;$('evidence-image').hidden=false;$('evidence-message').textContent='';}
    catch(error){if(error.name!=='AbortError'&&revision===evidenceEpoch)$('evidence-message').textContent='No fue posible consultar esta evidencia. Actualiza la solicitud y comprueba tu acceso.';}
  }
  let operatorEpoch=0;
  const operatorLabels={active:'Activo',pending:'Pendiente',suspended:'Suspendido',revoked:'Baja',accepted:'Aceptada',cancelled:'Revocada',rejected:'Rechazada',expired:'Vencida',review_required:'Revisión del titular pendiente',superseded:'Sustituida',missing:'Sin delegación',owner:'Titular',administrator:'Administrador',collaborator:'Colaborador',invite:'Invitación registrada',accept:'Invitación aceptada',grants:'Permisos actualizados',suspend:'Suspensión',reactivate:'Reactivación',revoke:'Baja',review:'Revisión del titular',rename:'Alias actualizado',owner_review_required:'Cambio de titular: revisión requerida'};
  const operatorDate=value=>{if(!value)return 'Fecha no disponible';const d=new Date(value.replace(' ','T')+'Z');return Number.isNaN(d.getTime())?'Fecha no disponible':new Intl.DateTimeFormat('es-MX',{timeZone:'America/Mexico_City',dateStyle:'short',timeStyle:'short'}).format(d)+' (CDMX)';};
  const operatorFunctions={'agenda.appointments.read':'Consultar citas','agenda.appointments.create':'Crear citas','agenda.appointments.reschedule':'Reprogramar citas','agenda.appointments.cancel':'Cancelar citas','agenda.availability.read':'Consultar disponibilidad'};
  async function loadOperators() {
    const epoch=++operatorEpoch,context=contextEpoch,d=doctor,host=$('operators-content');host.replaceChildren();
    if(!d){$('operators-message').textContent='Selecciona un perfil médico autorizado.';return;}
    $('operators-message').textContent='Consultando relaciones y permisos…';
    try {
      const {data}=await json(administrative?requestsPath('admin_operators',{profile_ref:d}):requestsPath('operators',{doctor_id:d}));
      if(epoch!==operatorEpoch||context!==contextEpoch||doctor!==d||section!=='operators')return;
      if(data.doctor_id!==d)throw new Error('context');
      if(administrative){$('operators-message').textContent='Relaciones canónicas · consulta administrativa. Invitaciones y delegaciones no integradas en esta proyección.';host.append(node('p','',data.owner_state==='confirmed'?'Titular activo confirmado.':'Titular activo no confirmado.'));for(const m of data.members)host.append(node('p','',`${valueLabel(m.role_code)} · ${valueLabel(m.status)} · Cuenta ${valueLabel(m.account_status)} · ${m.contact_verified?'Contacto verificado':'Verificación pendiente'}`));if(!data.members.length)host.append(node('p','','Sin relaciones registradas.'));return;}
      $('operators-message').textContent=data.access_note;
      const card=(title,lines)=>{const el=node('article','history-entry');el.append(node('h3','',title));for(const line of lines)el.append(node('p','',line));host.append(el);};
      const label=x=>operatorLabels[x]||'Estado no reconocido';
      const functions=x=>(x||[]).map(v=>operatorFunctions[v]||'Función fuera del catálogo inicial').join(', ')||'Ninguna';
      card('Titular del perfil',[data.owner?`Cuenta: ${data.owner.account_id}`:'No hay un titular único disponible.',data.owner_state==='confirmed'?'Relación de titular activa y cuenta verificada.':'La titularidad requiere revisión por su flujo gobernado.']);
      card('Dos plazas de colaboradores',[`${data.seats.occupied} ocupadas · ${data.seats.reserved} reservadas · ${data.seats.available} disponibles`,data.seats.consistent?'Administrador es un nivel de permisos dentro de estas dos plazas. El titular y el asesor no consumen una plaza.':'Existe una inconsistencia de cupo; no se habilitan plazas adicionales.']);
      const members=data.members.filter(v=>v.role_code!=='owner');
      if(!members.length)card('Colaboradores',['No hay relaciones de colaboradores registradas para este perfil.']);
      for(const m of members)card(m.alias||'Colaborador sin alias',[`${label(m.role_code)} · ${label(m.status)} · ${m.seat_position?'Posición '+m.seat_position:'Sin posición de presentación'}`,`Cuenta vinculada: ${m.account_id} · ${m.contact_verified?'Contacto verificado':'Verificación pendiente'}`,`Delegación: ${label(m.grant_status)}${m.grant_revision?' · Versión '+m.grant_revision:''}`,`Funciones delegadas: ${functions(m.delegated_functions)}`,m.delegation_current?'Delegación vigente; el acceso final se valida en el módulo correspondiente.':'Esta relación no acredita una delegación actualmente utilizable.',m.next_action]);
      if(!data.invitations.length)card('Invitaciones',['No hay invitaciones registradas para este perfil.']);
      for(const i of data.invitations)card(`Invitación · ${label(i.display_status)}`,[i.alias||'Sin alias registrado',`${label(i.role_code)} · ${i.account_linked?'Cuenta vinculada por aceptación':'Sin vinculación por aceptación'}`,`Registrada: ${operatorDate(i.created_at)} · Vencimiento: ${operatorDate(i.expires_at)}`,...(i.status==='accepted'?[`Aceptada: ${operatorDate(i.closed_at)}`]:[]),`Funciones ofrecidas: ${functions(i.offered_functions)}`,i.next_action]);
      if(data.invitations_has_more)card('Más invitaciones',['Se muestran las 100 invitaciones más recientes.']);
      card('Historial administrativo · hasta 100 eventos recientes',data.events.length?data.events.map(e=>`${operatorDate(e.occurred_at_utc)} · ${label(e.operation)} · ${e.event_id}`):['No hay eventos canónicos de operadores registrados para este perfil.']);
    }catch(error){if(epoch!==operatorEpoch||context!==contextEpoch||doctor!==d)return;host.replaceChildren();$('operators-message').textContent=error.status===403?'Tu permiso o el ámbito del perfil cambió. La consulta ya no está autorizada.':error.status===401?'La sesión terminó. Inicia sesión nuevamente.':'No fue posible consultar las fuentes de operadores. No se interpreta como una lista vacía.';}
  }
  async function loadHistory() {
    if(!contextSelected()||busy||expired)return;const d=doctor,id=selectedRequestId,epoch=contextEpoch,revision=++historyEpoch;historyController?.abort();historyController=new AbortController();$('history-message').textContent='Cargando historial…';$('history-list').replaceChildren();
    try {const result=await json(requestsPath('history',{doctor_id:d,request_id:id}),{signal:historyController.signal});if(epoch!==contextEpoch||revision!==historyEpoch||doctor!==d||selectedRequestId!==id)return;
      const history=result.data?.history||result.data, list=$('history-list');
      for(const entry of history.items||[]){const row=node('article','history-entry');row.dataset.eventId=entry.id;row.append(node('h3','',entry.description||entry.label||'Actividad registrada'),node('p','muted',`${dateLabel(entry.at)} UTC · ${entry.actor?.label||entry.actor?.reference||'Actor registrado'}${entry.actor?.role?' · '+({DIRECTOR:'Director',ADVISOR:'Asesor',MASTER_ADMIN:'Administrador Master'}[entry.actor.role]||valueLabel(entry.actor.role)):''}`));
        if(entry.target)row.append(node('p','muted',[entry.target.doctor_id?'Perfil '+entry.target.doctor_id:'',entry.target.request_id?'Solicitud '+entry.target.request_id:''].filter(Boolean).join(' · ')));
        if(entry.outcome)row.append(node('p','',entry.outcome==='SUCCESS'?'Operación registrada':requestStates[entry.outcome]||entry.outcome));
        if(entry.reason)row.append(node('p','',`Motivo: ${entry.reason}`));
        const labels={previous_status:'Estado anterior',new_status:'Estado posterior',prior_role:'Relación anterior',new_role:'Relación resultante',request_type:'Tipo de solicitud',version:'Versión',before_status:'Estado anterior',after_status:'Estado posterior',request_state:'Estado de solicitud',profile_status:'Estado del perfil',ownership_status:'Titularidad',profile_id:'Perfil resultante',doctor_id:'Perfil',applicant_account_id:'Cuenta solicitante',purpose:'Tipo de imagen'};
        const details=[];for(const [key,value] of Object.entries(entry.details||{})){if(labels[key]&&['string','number','boolean'].includes(typeof value))details.push([labels[key],valueLabel(value)]);}if(details.length)appendFacts(row,'Cambios registrados',details);list.append(row);
      }
      $('history-message').textContent=history.items?.length?(history.has_more?'Se muestran los eventos recientes disponibles.':'Sólo se muestran eventos de los flujos integrados y autorizados.'):'No hay eventos disponibles para este contexto.';
    }catch(error){if(error.name==='AbortError'||epoch!==contextEpoch||revision!==historyEpoch)return;$('history-message').textContent=error.status===403?'Tu cuenta no tiene permiso para consultar este historial.':'No fue posible cargar el historial. Intenta actualizarlo.';}
  }
  function chooseDecision(value) {
    if(busy||!requestDetail||!requestActions().has(value)||uncertainDecisions.has(selectedRequestId)||!leaveDraft())return;
    resetDraft();decision=value;$('request-decision-form').hidden=false;$('request-reauth').hidden=false;
    $('request-password').required=true;$('request-mfa').required=true;
    $('request-reason').hidden=false;$('request-reason-label').hidden=false;
    $('request-reason').required=value!=='APPROVED';$('request-reason').minLength=value==='APPROVED'?0:8;$('request-reason-label').textContent=value==='APPROVED'?'Motivo de aprobación (opcional)':'Motivo de la decisión (mínimo 8 caracteres)';
    const title=value==='APPROVED'?$('approve-request').textContent:value==='REJECTED'?'Rechazar solicitud':'Solicitar información';
    $('request-decision-title').textContent=title;$('confirm-request-decision').textContent='Confirmar: '+title.toLocaleLowerCase('es-MX');
    $('request-decision-explanation').textContent=value==='APPROVED'?'La aprobación se registra para la persona solicitante. La titularidad, acreditación, publicación y plan mantienen sus etapas propias.':value==='REJECTED'?'El rechazo resuelve esta solicitud y conserva el perfil existente y la cuenta solicitante.':'Describe qué información necesita la persona solicitante. La solicitud permanecerá sin resolver.';
    $('request-confirmation-label').textContent=`Confirmo esta decisión para la solicitud ${selectedRequestId} y ${requestDetail.applicant?.label||'su persona solicitante'}.`;
    $('request-reason').focus();requestControls();
  }
  async function requestPost(payload) {const {action,...input}=payload;if(!requestCsrf)throw new Error('csrf');return json(ROOT+'requests.php',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':requestCsrf},body:JSON.stringify({action,input})});}
  function actionResult(response) {return response.data||response;}
  async function ownershipSnapshot(id) {const result=await json(requestsPath('ownership',{request_id:id}));requestCsrf=result.data.csrf_token||requestCsrf;return result.data;}
  function renderOwnership() {
    if(!ownershipState||requestDetail?.state!=='APPROVED')return;const s=ownershipState;
    $('request-ownership').hidden=false;$('continue-ownership').hidden=!(s.can_begin||s.can_challenge);
    $('ownership-message').textContent=s.ownership_activated?'Titularidad activada para la cuenta solicitante.':s.prerequisite_label||({CANONICAL_C4_MEMBERSHIP_AND_OWNER_CASE_REQUIRED:'Faltan la acreditación independiente y la aceptación inicial de la persona solicitante.',REVIEWER_ENDORSEMENT_REQUIRED:'La titularidad requiere tu confirmación para esta solicitud aprobada.',APPLICANT_ACCEPTANCE_REQUIRED:'Tu autorización está registrada. La persona solicitante debe confirmar la titularidad con su propia cuenta.',CASE_RECOVERY_REQUIRED:'El caso de titularidad requiere revisar su estado antes de continuar.'}[s.next_step]||'Consulta el estado de los requisitos para continuar.');
    requestControls();
  }
  async function loadOwnership() {
    if(!selectedRequestId||requestDetail?.state!=='APPROVED'||expired)return;const id=selectedRequestId,epoch=contextEpoch,revision=++ownershipEpoch;
    try {const value=await ownershipSnapshot(id);if(id!==selectedRequestId||epoch!==contextEpoch||revision!==ownershipEpoch)return;ownershipState=value;renderOwnership();}
    catch(error){if(id!==selectedRequestId||epoch!==contextEpoch||revision!==ownershipEpoch)return;ownershipState=null;$('continue-ownership').hidden=true;$('ownership-message').textContent='No fue posible confirmar el estado de titularidad. Consulta el estado antes de continuar.';}
  }
  function chooseOwnership() {
    if(busy||!requestDetail||uncertainDecisions.has(selectedRequestId)||!(ownershipState?.can_begin||ownershipState?.can_challenge)||!leaveDraft())return;
    resetDraft();decision='OWNERSHIP';$('request-decision-form').hidden=false;$('request-reauth').hidden=false;
    $('request-password').required=true;$('request-mfa').required=true;$('request-reason').required=false;$('request-reason').hidden=true;$('request-reason-label').hidden=true;
    $('request-decision-title').textContent='Autorizar titularidad para la persona solicitante';$('request-decision-explanation').textContent='Confirma la autorización del perfil exacto para la cuenta solicitante. La persona solicitante completará su propia aceptación; no se activa publicación ni plan.';
    $('request-confirmation-label').textContent=`Confirmo la autorización de titularidad de esta solicitud ${selectedRequestId} para ${requestDetail.applicant?.label||'su persona solicitante'}.`;
    $('confirm-request-decision').textContent='Autorizar titularidad';$('request-password').focus();requestControls();
  }
  async function saveOwnership() {
    if(busy||decision!=='OWNERSHIP'||!selectedRequestId||!$('request-decision-form').reportValidity())return;
    const id=selectedRequestId,epoch=contextEpoch,password=$('request-password').value,code=$('request-mfa').value;busy=true;controls();$('request-feedback').textContent='Comprobando los requisitos de titularidad…';let sent=false;
    try {
      let status=await ownershipSnapshot(id);if(epoch!==contextEpoch||selectedRequestId!==id)return;
      if(!(status.can_begin||status.can_challenge))throw Object.assign(new Error('authority'),{status:409});
      uncertainDecisions.set(id,{kind:'ownership',case_id:status.case_id});sent=true;
      if(status.can_begin)status=actionResult(await requestPost({action:'owner_begin',request_id:id}));
      const challenge=actionResult(await requestPost({action:'owner_challenge',request_id:id,email:state.actor.email,password}));
      const proof=actionResult(await requestPost({action:'owner_verify',request_id:id,challenge_id:challenge.challenge_id,challenge:challenge.challenge,code}));
      $('request-password').value='';$('request-mfa').value='';
      const result=actionResult(await requestPost({action:'owner_endorse',request_id:id,proof_id:proof.proof_id,proof:proof.proof}));
      if(epoch!==contextEpoch||selectedRequestId!==id)return;ownershipState=result;uncertainDecisions.delete(id);resetDecision();renderOwnership();
      $('request-feedback').textContent='Autorización de titularidad registrada. La persona solicitante debe completar su propia aceptación.';
    }catch(error){if(epoch!==contextEpoch||selectedRequestId!==id||expired)return;$('request-password').value='';$('request-mfa').value='';resetDecision();$('request-feedback').textContent=sent?'No se pudo confirmar la autorización. Consulta su resultado antes de continuar.':'Los requisitos o tu autorización cambiaron. Consulta el estado de titularidad.';}
    finally{busy=false;controls();await refresh();}
  }
  async function saveRequestDecision() {
    if(decision==='OWNERSHIP'){await saveOwnership();return;}
    if(busy||!requestDetail||!decision||uncertainDecisions.has(selectedRequestId)||!$('request-decision-form').reportValidity())return;
    const id=selectedRequestId,epoch=contextEpoch,version=requestDetail.revision_token,chosen=decision;
    const reason=$('request-reason').value.trim(),password=$('request-password').value,code=$('request-mfa').value;
    if(chosen!=='APPROVED'&&reason.length<8)return;busy=true;controls();$('request-feedback').textContent='Validando la confirmación…';let caseId=null,commitSent=false;
    try {
      const fresh=await requestSnapshot(id);if(epoch!==contextEpoch||selectedRequestId!==id)return;
      if(fresh.revision_token!==version||!(fresh.allowed_actions||[]).includes(chosen))throw Object.assign(new Error('changed'),{status:409});
      const begun=actionResult(await requestPost({action:'begin',request_id:id,intake_version:fresh.intake_version,review_version:fresh.review_version,revision_token:version,decision:chosen,reason,password,email:state.actor.email}));
      caseId=begun.case_id;if(!caseId||!begun.challenge_id||!begun.challenge)throw new Error('challenge');
      const verified=actionResult(await requestPost({action:'verify',case_id:caseId,challenge_id:begun.challenge_id,challenge:begun.challenge,code}));
      if(!verified.proof_id||!verified.proof)throw new Error('proof');
      $('request-password').value='';$('request-mfa').value='';
      uncertainDecisions.set(id,{case_id:caseId});commitSent=true;
      await requestPost({action:'decide',case_id:caseId,proof_id:verified.proof_id,proof:verified.proof,confirmed:true});
      if(epoch!==contextEpoch||id!==selectedRequestId)return;uncertainDecisions.delete(id);confirmedRequestUpdates.add(id);resetDecision();
      $('request-feedback').textContent=`Decisión registrada: ${requestStates[chosen]}. Consulta el resultado de la solicitud y su historial.`;
    }catch(error){
      if(epoch!==contextEpoch||id!==selectedRequestId||expired)return;
      if(commitSent){$('request-feedback').textContent='No se pudo confirmar la respuesta de la decisión. Consulta el resultado registrado; no se reenviará la operación.';resetDecision();}
      else if(error.status===409){resetDecision();$('request-feedback').textContent='La solicitud, evidencia o autorización cambió. Revisa la versión actual antes de confirmar.';}
      else {$('request-password').value='';$('request-mfa').value='';$('request-feedback').textContent=error.status===403?'No se autorizó la decisión. Revisa tus permisos y tu confirmación.':'No se pudo completar la confirmación. Revisa tu contraseña y el código vigente antes de continuar.';}
    }finally{busy=false;controls();await refresh();if(id===selectedRequestId)await refreshRequest();}
  }
  async function lookupDecision() {
    const pending=uncertainDecisions.get(selectedRequestId);if(!pending||busy)return;const id=selectedRequestId,epoch=contextEpoch;busy=true;controls();$('request-feedback').textContent='Consultando el resultado registrado…';
    try {
      if(pending.kind==='ownership'){
        const result=await ownershipSnapshot(id);if(epoch!==contextEpoch||id!==selectedRequestId)return;ownershipState=result;
        if(result.ownership_activated||result.case_state==='APPROVED'){uncertainDecisions.delete(id);$('request-feedback').textContent='Se recuperó el resultado de titularidad. Consulta la etapa actual.';}
        else if(result.can_begin||result.can_challenge){uncertainDecisions.delete(id);$('request-feedback').textContent='La autorización de titularidad no está completada. Revisa el estado antes de iniciar otra confirmación.';}
        else $('request-feedback').textContent='La titularidad sigue pendiente de los requisitos indicados. No se reenviará la operación.';
        renderOwnership();return;
      }
      const result=actionResult(await json(requestsPath('result',{case_id:pending.case_id})));if(epoch!==contextEpoch||id!==selectedRequestId)return;
      const value=result.result||result;const confirmed=value.committed===true||['APPROVED','REJECTED','NEEDS_INFORMATION'].includes(value.request_state||value.state);
      if(confirmed){uncertainDecisions.delete(id);confirmedRequestUpdates.add(id);resetDecision();$('request-feedback').textContent='Se recuperó la decisión registrada. Revisa su resultado y el historial.';}
      else if(value.committed===false&&value.status==='NOT_COMMITTED'){uncertainDecisions.delete(id);resetDecision();$('request-feedback').textContent='El servidor confirmó que la decisión no se guardó. Revisa la solicitud antes de iniciar otra confirmación.';}
      else $('request-feedback').textContent='El resultado sigue pendiente de confirmación. No se reenviará la decisión; vuelve a consultar su resultado.';
    }catch(error){if(epoch===contextEpoch&&id===selectedRequestId)$('request-feedback').textContent=error.status===403?'El resultado requiere acceso vigente a la solicitud. Consulta al Director.':'No fue posible confirmar el resultado. La operación no se reenviará.';}
    finally{busy=false;controls();await refresh();if(id===selectedRequestId)await refreshRequest();}
  }
  function controls() {
    if(!$('media-detail'))return;
    const valid=!!item;const allowed=(name)=>valid&&options[name]===true;
    $('download-original').hidden=!allowed('can_download_source');$('request-replacement').hidden=!allowed('can_request_replacement');$('approve-image').hidden=!allowed('can_approve');$('approve-image').disabled=busy||!reviewLoaded;
    $('correction-section').hidden=!allowed('can_upload_corrected');$('improvement-section').hidden=!allowed('can_improve')||item?.purpose!=='PHYSICIAN_PERSONAL_LOGO';
    for(const el of $('media-detail').querySelectorAll('button,input,select,textarea'))el.disabled=busy;
    $('approve-image').disabled=busy||!reviewLoaded;$('accept-improvement').disabled=busy||!proposalLoaded||!reviewLoaded;
    $('staff-logout').disabled=busy;$('refresh-images').disabled=busy;requestControls();
  }
  async function freshOptions(d=doctor, signal) {return json(mediaPath('options',d,null),signal?{signal}:{});}
  async function blobImage(mode, target, d, s, epoch, signal) {
    const response=await request(mediaPath(mode,d,s),{signal});if(!response.ok||response.headers.get('Content-Type')!=='image/webp')throw Object.assign(new Error('image'),{status:response.status});
    const blob=await response.blob();if(blob.size<1||blob.size>153600)throw new Error('image');if(epoch!==itemEpoch||doctor!==d||item?.submission_id!==s)return;
    const url=URL.createObjectURL(blob);if(target==='review-image'){if(reviewUrl)URL.revokeObjectURL(reviewUrl);reviewUrl=url;reviewLoaded=false;$('review-image-message').hidden=true;$('review-image').hidden=false;}else{if(proposalUrl)URL.revokeObjectURL(proposalUrl);proposalUrl=url;proposalLoaded=false;$('proposal-panel').hidden=false;$('accept-improvement').hidden=false;$('discard-improvement').hidden=false;}
    controls();
    $(target).src=url;
  }
  async function selectItem(record, confirm=true) {
    if(busy||confirm&&!leaveDraft())return;clearItem();item=record;const epoch=++itemEpoch,d=doctor,s=record.submission_id;itemController=new AbortController();$('media-detail').hidden=false;$('media-detail').dataset.doctorId=d;$('media-detail').dataset.submissionId=s;$('media-purpose').textContent=labels[record.purpose]||'Imagen';$('media-title').textContent=state.physician.name;$('media-date').textContent='Recibida: '+dateLabel(record.created_at);$('review-image-message').hidden=false;$('review-image-message').textContent='Cargando imagen…';$('review-specs').textContent=record.review?`${record.review.width} × ${record.review.height} px · ${(record.review.byte_size/1024).toLocaleString('es-MX',{maximumFractionDigits:1})} KB · Vista de revisión`:'';mediaSay('');controls();$('media-items').querySelectorAll('button').forEach(b=>b.setAttribute('aria-pressed',String(b.dataset.submissionId===s)));
    try {const result=await freshOptions(d,itemController.signal);if(epoch!==itemEpoch||doctor!==d)return;options=result;controls();await blobImage('review','review-image',d,s,epoch,itemController.signal);if(options.can_improve&&record.purpose==='PHYSICIAN_PERSONAL_LOGO'){try{await blobImage('improvement-preview','proposal-image',d,s,epoch,itemController.signal);}catch{/* An absent proposal is an ordinary state. */}}}
    catch(error){if(error.name==='AbortError'||epoch!==itemEpoch)return;options={};controls();$('review-image-message').hidden=false;$('review-image-message').textContent='La imagen no está disponible. Actualiza para comprobar tu acceso.';await refresh();}
  }
  async function refresh() {
    if(expired||busy||pollBusy)return;pollBusy=true;const epoch=contextEpoch,d=doctor;
    try {
      if(administrative){await refreshAdministrative();return;}
      const [data,requests,counts]=await Promise.all([mediaContext(d),loadRequestDirectory(),loadPending(d,selectedRequestId)]);if(epoch!==contextEpoch||d!==doctor||expired)return;requestState=requests;acceptPending(counts,d,selectedRequestId);contextProfile(data);actor(data);
      if(d&&!data.physician&&selectedRequestId&&requestDetail&&contextRequests().length)data.physician=requestPhysician(requestDetail);
      if(d&&!data.physician){clearContext();state=data;say('Tu acceso o el perfil disponible cambió. La vista se actualizó.');showDiscovery('pending');return;}
      state=data;if(!$('discovery').hidden)discovery(data);if(contextSelected()){$('physician-workspace').hidden=false;renderPanels();renderTabs();if(item){const current=data.pending_items.find(v=>v.submission_id===item.submission_id);if(!current){clearItem();say('El pendiente ya fue atendido o dejó de estar disponible.');}else{const selectedEpoch=itemEpoch;const next=await freshOptions(d);if(epoch!==contextEpoch||doctor!==d||selectedEpoch!==itemEpoch)return;const lostDraft=dirty()&&((options.can_request_replacement&&!next.can_request_replacement)||(options.can_upload_corrected&&!next.can_upload_corrected));options=next;if(lostDraft){resetDraft();mediaSay('Tus permisos cambiaron. Se descartaron los cambios que ya no puedes guardar.');}controls();}}if(selectedRequestId)await refreshRequest();if(section==='history')await loadHistory();if(section==='operators')await loadOperators();}
    }catch(error){if(epoch!==contextEpoch)return;if(error.status===403){sessionFailure(403);return;}if(error.status!==401)say('No fue posible actualizar el acceso. Actualiza antes de continuar.');if(item){options={};controls();}}
    finally{pollBusy=false;}
  }
  async function mutate(mode, capability, payload={}) {
    if(busy||!item)return;const d=doctor,s=item.submission_id,epoch=itemEpoch;busy=true;controls();mediaSay('Guardando…');
    try {
      const current=await freshOptions(d);if(!current[capability]||!current.csrf)throw Object.assign(new Error('denied'),{status:403});
      let body,headers;if(payload instanceof FormData){body=payload;body.set('submission_id',s);body.set('csrf',current.csrf);}else{body=JSON.stringify({submission_id:s,csrf:current.csrf,...payload});headers={'Content-Type':'application/json'};}
      const result=await json(mediaPath(mode,d,null,true),{method:'POST',headers,body});if(epoch!==itemEpoch||d!==doctor)return;
      if(['replacement','approve'].includes(mode)){resetDraft();clearItem();say(mode==='replacement'?'Se registró la solicitud de otra imagen.':'La imagen fue aprobada y publicada.');}
      else if(mode==='corrected'||result.status==='PROPOSAL_ACCEPTED'){resetDraft();releaseImages();await blobImage('review','review-image',d,s,epoch);mediaSay(mode==='corrected'?'Versión corregida guardada para revisión.':'Versión mejorada guardada para revisión. Aún requiere aprobación.');}
      else if(result.status==='PROPOSAL_CREATED'){await blobImage('improvement-preview','proposal-image',d,s,epoch);mediaSay('Compara la propuesta con la versión actual antes de decidir.');}
      else if(['NO_SAFE_IMPROVEMENT','ALREADY_TRANSPARENT'].includes(result.status))mediaSay('No se detectó una mejora automática segura.');
      else if(result.status==='ALREADY_IMPROVED')mediaSay('La versión actual ya contiene esta mejora.');
      else if(result.status==='IMPROVEMENT_INPUT_UNAVAILABLE')mediaSay('Esta imagen necesita una nueva carga o corrección antes de generar una mejora.');
      else {if(proposalUrl)URL.revokeObjectURL(proposalUrl);proposalUrl=null;proposalLoaded=false;$('proposal-panel').hidden=true;$('accept-improvement').hidden=true;$('discard-improvement').hidden=true;mediaSay('Propuesta descartada. Se conserva la versión actual.');}
    }catch(error){if(epoch!==itemEpoch||expired)return;mediaSay(error.status===409?'La solicitud cambió. Se actualizará su estado antes de continuar.':error.status===403?'Tu cuenta ya no tiene permiso para esta acción.':'No fue posible confirmar la acción. Actualiza para revisar el estado antes de intentarlo nuevamente.');if(error.status===409){resetDraft();clearItem();say('El pendiente cambió. Se actualizó la lista.');}}
    finally{busy=false;controls();await refresh();}
  }
  $('physician-search-form').addEventListener('submit',event=>{event.preventDefault();lastQuery=$('physician-search').value.trim();showDiscovery('search');});
  $('search-toggle').onclick=()=>{if(busy)return;if(contextSelected()&&!$('discovery').hidden&&directoryMode==='search'){collapseDiscovery();return;}showDiscovery('search');$('physician-search').focus();};
  $('pending-toggle').onclick=()=>{if(busy)return;if(contextSelected()&&!$('discovery').hidden&&directoryMode==='pending'){collapseDiscovery();return;}showDiscovery('pending');};
  $('close-discovery').onclick=()=>{collapseDiscovery();$('physician-name').focus({preventScroll:true});};
  $('change-physician').onclick=()=>{if(busy)return;showDiscovery('search');$('physician-search').focus();};
  $('refresh-operators').onclick=()=>loadOperators();
  $('refresh-images').onclick=()=>refresh();
  $('refresh-requests').onclick=()=>refresh();$('refresh-history').onclick=()=>loadHistory();
  $('approve-request').onclick=()=>chooseDecision('APPROVED');$('request-information').onclick=()=>chooseDecision('NEEDS_INFORMATION');$('reject-request').onclick=()=>chooseDecision('REJECTED');
  $('request-decision-form').onsubmit=event=>{event.preventDefault();saveRequestDecision();};
  $('cancel-request-decision').onclick=()=>{if(busy||!leaveDraft())return;resetDecision();$('request-heading').focus({preventScroll:true});};
  $('lookup-request-result').onclick=()=>lookupDecision();$('close-evidence').onclick=()=>closeEvidence();
  $('continue-ownership').onclick=()=>chooseOwnership();$('refresh-ownership').onclick=()=>loadOwnership();
  $('evidence-dialog').addEventListener('cancel',event=>{event.preventDefault();closeEvidence();});
  $('more-toggle').onclick=()=>{const open=$('more-menu').hidden;$('more-menu').hidden=!open;$('more-toggle').setAttribute('aria-expanded',String(open));if(open)$('more-menu').querySelector('button')?.focus();};
  $('more-menu').addEventListener('keydown',event=>{const buttons=[...event.currentTarget.querySelectorAll('button')],i=buttons.indexOf(document.activeElement);if(event.key==='Escape'){closeMore();$('more-toggle').focus();event.preventDefault();}else if(['ArrowDown','ArrowUp','Home','End'].includes(event.key)){event.preventDefault();buttons[event.key==='Home'?0:event.key==='End'?buttons.length-1:(i+(event.key==='ArrowDown'?1:-1)+buttons.length)%buttons.length]?.focus();}});
  $('section-tabs').addEventListener('keydown',event=>{if(!['ArrowRight','ArrowLeft','Home','End'].includes(event.key))return;const tabs=workspaceSections(),i=tabs.findIndex(t=>t.id===section);const target=event.key==='Home'?0:event.key==='End'?tabs.length-1:(i+(event.key==='ArrowRight'?1:-1)+tabs.length)%tabs.length;event.preventDefault();switchSection(tabs[target].id);});
  document.addEventListener('click',event=>{if(!$('more-sections').contains(event.target))closeMore();});
  $('request-replacement').onclick=()=>{if(busy)return;if($('corrected-file').files.length){if(!leaveDraft())return;resetDraft();}$('replacement-form').hidden=false;$('replacement-reason').focus();};
  $('cancel-replacement').onclick=()=>{if(busy||!leaveDraft())return;resetDraft();$('request-replacement').focus();};
  $('replacement-feedback').addEventListener('input',()=>{const chars=Array.from($('replacement-feedback').value);if(chars.length>400)$('replacement-feedback').value=chars.slice(0,400).join('');$('replacement-count').textContent=Array.from($('replacement-feedback').value).length+' / 400 caracteres';});
  $('replacement-form').addEventListener('submit',event=>{event.preventDefault();if(!event.currentTarget.reportValidity())return;mutate('replacement','can_request_replacement',{reason_code:$('replacement-reason').value,feedback:$('replacement-feedback').value});});
  $('approve-image').onclick=()=>{if(!busy&&reviewLoaded&&leaveDraft()&&window.confirm('¿Aprobar y publicar esta imagen de '+state.physician.name+'?'))mutate('approve','can_approve');};
  $('corrected-file').onchange=()=>{if($('replacement-reason').value||$('replacement-feedback').value){if(!leaveDraft()){$('corrected-file').value='';}else{$('replacement-form').reset();$('replacement-form').hidden=true;$('replacement-count').textContent='0 / 400 caracteres';}}$('save-corrected').hidden=!$('corrected-file').files.length;};
  $('save-corrected').onclick=()=>{const file=$('corrected-file').files[0];if(!file)return;const form=new FormData();form.set('corrected',file);mutate('corrected','can_upload_corrected',form);};
  $('generate-improvement').onclick=()=>mutate('improve','can_improve');$('accept-improvement').onclick=()=>{if(proposalLoaded&&reviewLoaded&&leaveDraft())mutate('accept-improvement','can_improve');};$('discard-improvement').onclick=()=>mutate('discard-improvement','can_improve');
  $('review-image').addEventListener('load',()=>{if(reviewUrl&&$('review-image').currentSrc===reviewUrl){reviewLoaded=true;controls();}});
  $('review-image').addEventListener('error',()=>{reviewLoaded=false;$('review-image').hidden=true;$('review-image-message').hidden=false;$('review-image-message').textContent='No fue posible mostrar la imagen.';controls();});
  $('proposal-image').addEventListener('load',()=>{if(proposalUrl&&$('proposal-image').currentSrc===proposalUrl){proposalLoaded=true;controls();}});
  $('proposal-image').addEventListener('error',()=>{proposalLoaded=false;mediaSay('No fue posible mostrar la propuesta. No puede aceptarse sin revisarla.');controls();});
  $('download-original').onclick=async()=>{
    if(busy||!item)return;const d=doctor,s=item.submission_id,epoch=itemEpoch;busy=true;controls();mediaSay('Preparando original…');
    try{const response=await request(mediaPath('source',d,s));if(!response.ok)throw Object.assign(new Error('download'),{status:response.status});const file=/filename="([A-Za-z0-9_.-]+)"/.exec(response.headers.get('Content-Disposition')||'')?.[1];if(!file)throw new Error('download');const blob=await response.blob();if(epoch!==itemEpoch||d!==doctor)return;const url=URL.createObjectURL(blob),link=node('a');link.href=url;link.download=file;document.body.append(link);link.click();link.remove();setTimeout(()=>URL.revokeObjectURL(url),1000);mediaSay('Original listo para descargar.');}catch(error){if(epoch===itemEpoch)mediaSay(error.status===403?'No tienes permiso para descargar este original.':'No fue posible descargar el original.');}finally{busy=false;controls();await refresh();}
  };
  $('staff-logout').onclick=async()=>{
    if(busy||!leaveDraft())return;busy=true;controls();say('Cerrando sesión…');
    try{const response=await request('/api/identity/index.php/current-session'),token=await response.json();if(!response.ok||!token.csrf_token)throw new Error('session');const result=await json('/api/identity/index.php/logout',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':token.csrf_token},body:JSON.stringify({csrf_token:token.csrf_token})});if(result.state!=='LOGOUT_SUCCESS')throw new Error('logout');directoryEpoch++;directoryController?.abort();clearContext();expired=true;try{const channel=new BroadcastChannel('mxmed-session');channel.postMessage({type:'logout',at:Date.now()});channel.close();}catch{}document.body.replaceChildren();location.replace(LOGIN);}catch{if(!expired)say('No pudimos confirmar el cierre de sesión. Intenta nuevamente.');}finally{busy=false;controls();}
  };
  window.addEventListener('beforeunload',event=>{if(dirty()){event.preventDefault();event.returnValue='';}});
  window.addEventListener('resize',()=>{if(contextSelected())renderTabs();});
  window.addEventListener('pageshow',async event=>{if(event.persisted){const previous=item?.submission_id;clearItem();$('physician-workspace').hidden=true;$('discovery-list').replaceChildren();expired=false;await refresh();const restored=state?.pending_items?.find(record=>record.submission_id===previous);if(!expired&&doctor&&restored)await selectItem(restored,false);}});
  document.addEventListener('visibilitychange',()=>{if(!document.hidden)refresh();});
  window.addEventListener('pagehide',()=>{directoryController?.abort();contextController?.abort();itemController?.abort();requestController?.abort();historyController?.abort();evidenceController?.abort();releaseImages();if($('evidence-dialog'))closeEvidence();if($('request-password')){$('request-password').value='';$('request-mfa').value='';}});
  setInterval(()=>{if(!document.hidden)refresh();},15000);
  loadDirectory('');
})();
