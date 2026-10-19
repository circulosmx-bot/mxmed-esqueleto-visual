(() => {
  'use strict';
  const $ = id => document.getElementById(id);
  const ROOT = '/api/internal/workspace/', LOGIN = '/public/identity/acceso.php?next=%2Finternal%2Fstaff-workspace%2Findex.php';
  const labels = {DOCTOR_PROFILE_PHOTO:'Foto de perfil',PHYSICIAN_PERSONAL_LOGO:'Logotipo profesional',DOCTOR_GALLERY:'Foto de galería'};
  const statuses = {draft:'Borrador',published:'Publicado',active:'Activo',inactive:'Inactivo',archived:'Archivado',hidden:'No publicado',pending_review:'En revisión',suspended:'Suspendido',removed:'Retirado',DRAFT:'Borrador',PUBLISHED:'Publicado',ACTIVE:'Activo',INACTIVE:'Inactivo',ARCHIVED:'Archivado',HIDDEN:'No publicado',PENDING_REVIEW:'En revisión',SUSPENDED:'Suspendido',REMOVED:'Retirado'};
  let state = null, doctor = null, section = 'summary', item = null, options = {}, busy = false, expired = false;
  let directoryMode = 'pending', directoryEpoch = 0, contextEpoch = 0, itemEpoch = 0;
  let directoryController = null, contextController = null, itemController = null;
  let reviewUrl = null, proposalUrl = null, reviewLoaded = false, proposalLoaded = false, pollBusy = false, lastQuery = '';
  const node = (tag, cls, text) => {const el=document.createElement(tag);if(cls)el.className=cls;if(text!==undefined)el.textContent=text;return el;};
  const dateLabel = value => {const m=/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}:\d{2})/.exec(value||'');return m?`${m[3]}/${m[2]}/${m[1]}, ${m[4]}`:'Fecha no disponible';};
  const describe = p => [p.specialty,[p.city,p.state].filter(Boolean).join(', ')].filter(Boolean).join(' · ');
  const mediaPath = (mode, d=doctor, s=item?.submission_id, mutation=false) => {const q=new URLSearchParams({doctor_id:d||'',mode});if(s&&!mutation)q.set('submission_id',s);return ROOT+'media.php?'+q;};
  const readPath = (d=null,query='') => ROOT+'read.php?'+new URLSearchParams({...d?{doctor_id:d}:{},...query?{query}:{} });
  const say = message => {$('workspace-feedback').textContent=message;};
  const mediaSay = message => {$('media-feedback').textContent=message;};
  const dirty = () => !!($('replacement-reason')?.value||$('replacement-feedback')?.value||$('corrected-file')?.files?.length);
  function leaveDraft() {return !dirty()||window.confirm('Hay cambios sin guardar para este médico. ¿Descartarlos y continuar?');}
  function resetDraft() {$('replacement-form').reset();$('replacement-form').hidden=true;$('replacement-count').textContent='0 / 400 caracteres';$('corrected-file').value='';$('save-corrected').hidden=true;}
  function releaseImages() {for(const url of [reviewUrl,proposalUrl])if(url)URL.revokeObjectURL(url);reviewUrl=null;proposalUrl=null;reviewLoaded=false;proposalLoaded=false;if(!$('review-image'))return;$('review-image').removeAttribute('src');$('review-image').hidden=true;$('proposal-image').removeAttribute('src');$('proposal-panel').hidden=true;$('accept-improvement').hidden=true;$('discard-improvement').hidden=true;}
  function clearItem() {itemEpoch++;itemController?.abort();item=null;options={};releaseImages();resetDraft();$('media-detail').hidden=true;$('media-items').querySelectorAll('button').forEach(b=>b.setAttribute('aria-pressed','false'));}
  function clearContext() {contextEpoch++;contextController?.abort();clearItem();doctor=null;section='summary';$('physician-workspace').hidden=true;$('no-selection').hidden=false;$('close-discovery').hidden=true;$('physician-name').textContent='';$('media-items').replaceChildren();$('summary-pending').replaceChildren();$('section-tabs').replaceChildren();closeMore();}
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
  async function json(url, init={}) {const response=await request(url,init);let result;try{result=await response.json();}catch{throw new Error('unavailable');}if(!response.ok||!result.ok)throw Object.assign(new Error('unavailable'),{status:response.status});return result;}
  function actor(data) {
    $('staff-name').textContent=data.actor.display_name||data.actor.email||'Cuenta del equipo interno';
    $('staff-role').textContent=({DIRECTOR:'Director',MASTER_ADMIN:'Administrador Master',ADVISOR:'Asesor'}[data.actor.access_level]||data.actor.access_level);
    const count=Math.max(0,Number(data.count)||0);$('pending-count').textContent=String(count);$('pending-count').setAttribute('aria-label',`${count} pendientes`);
  }
  function updateContext(data) {
    const p=data.physician;if(!p||p.doctor_id!==doctor)return;
    $('physician-name').textContent=p.name;$('physician-description').textContent=describe(p)||'Información profesional disponible en el perfil';
    const badges=$('physician-statuses');badges.replaceChildren();
    if(p.status)badges.append(node('span','badge publication-badge','Perfil: '+(statuses[p.status]||p.status)));
    if(p.pending_count>0)badges.append(node('span','badge review-badge',`${p.pending_count} ${p.pending_count===1?'pendiente':'pendientes'} de revisión`));
    if(p.plan?.label)badges.append(node('span','badge','Plan: '+p.plan.label));
  }
  function discovery(data) {
    const list=$('discovery-list');list.replaceChildren();$('discovery-eyebrow').textContent=directoryMode==='search'?'RESULTADOS DE BÚSQUEDA':'TRABAJO DISPONIBLE';$('discovery-title').textContent=directoryMode==='search'?'Médicos disponibles':'Pendientes por atender';
    const entries=directoryMode==='search'?data.physicians:data.pending;
    for(const entry of entries||[]) {
      const button=node('button','discovery-card');button.type='button';button.dataset.doctorId=entry.doctor_id;if(entry.submission_id)button.dataset.submissionId=entry.submission_id;
      const copy=node('span');copy.append(node('strong','',entry.name||entry.physician_name||'Perfil médico'));
      copy.append(node('span','card-meta',directoryMode==='search'?(describe(entry)||`${entry.pending_count} pendientes`):(entry.label||labels[entry.purpose]||'Imagen pendiente')));
      button.append(copy,node('span','card-action',directoryMode==='search'?'Abrir perfil →':'Revisar →'));
      button.addEventListener('click',()=>selectPhysician(entry.doctor_id,entry.submission_id||null));list.append(button);
    }
    $('discovery-message').textContent=entries?.length?(directoryMode==='search'?`${entries.length} ${entries.length===1?'perfil disponible':'perfiles disponibles'}`:(data.pending_has_more?'Se muestran los primeros pendientes accesibles. Busca por nombre para abrir otro médico.':'Sólo se muestra el trabajo accesible para tu cuenta.')):(data.sections?.length?(directoryMode==='search'?'No se encontraron médicos con ese nombre en tu ámbito autorizado.':'No hay trabajo pendiente para tu cuenta.'):'Tu cuenta no tiene funciones disponibles en este espacio. Consulta al Director para revisar tus permisos.');
    list.setAttribute('aria-busy','false');
  }
  async function loadDirectory(query=lastQuery) {
    const epoch=++directoryEpoch;directoryController?.abort();directoryController=new AbortController();$('discovery-list').setAttribute('aria-busy','true');
    try {const {data}=await json(readPath(null,query),{signal:directoryController.signal});if(epoch!==directoryEpoch||expired)return;actor(data);if(!data.sections.length&&doctor){clearContext();state=data;say('Tus permisos cambiaron. Ya no hay trabajo accesible en este espacio.');}discovery(data);if(!state)state=data;}
    catch(error){if(error.name==='AbortError'||epoch!==directoryEpoch)return;if(error.status===403){sessionFailure(403);return;}$('discovery-message').textContent='No fue posible actualizar la lista. Intenta nuevamente.';$('discovery-list').setAttribute('aria-busy','false');}
  }
  function showDiscovery(mode) {directoryMode=mode;$('discovery').hidden=false;$('pending-toggle').setAttribute('aria-expanded',mode==='pending'?'true':'false');$('close-discovery').hidden=!doctor;loadDirectory(mode==='search'?lastQuery:'');}
  async function selectPhysician(id, submission=null) {
    if(busy||!leaveDraft())return;clearContext();doctor=id;const epoch=++contextEpoch;contextController=new AbortController();$('no-selection').hidden=true;say('Cargando perfil…');
    try {
      const {data}=await json(readPath(id),{signal:contextController.signal});if(epoch!==contextEpoch||doctor!==id||expired)return;actor(data);
      if(!data.physician||data.physician.doctor_id!==id){clearContext();say('Este perfil ya no está disponible para tu cuenta.');showDiscovery('pending');return;}
      state=data;section=submission?'images':'summary';$('physician-workspace').hidden=false;$('discovery').hidden=true;$('pending-toggle').setAttribute('aria-expanded','false');$('close-discovery').hidden=false;updateContext(data);renderPanels();renderTabs();say('');
      if(submission){const selected=data.pending_items.find(v=>v.submission_id===submission);if(selected)await selectItem(selected,false);else say('El pendiente cambió o ya fue atendido. Se actualizó el perfil.');}
      $('physician-name').focus({preventScroll:true});$('physician-workspace').scrollIntoView({block:'start',behavior:'instant'});
    }catch(error){if(error.name==='AbortError'||epoch!==contextEpoch)return;clearContext();say(error.status===403?'Este perfil ya no está disponible para tu cuenta.':'No fue posible abrir el perfil. Intenta nuevamente.');showDiscovery('pending');}
  }
  function pendingButton(record) {const button=node('button','task-row');button.type='button';button.dataset.submissionId=record.submission_id;button.dataset.doctorId=doctor;button.append(node('span','',labels[record.purpose]||record.label||'Imagen pendiente'),node('span','','Revisar imagen →'));button.onclick=async()=>{if(busy||!leaveDraft())return;resetDraft();section='images';renderTabs();showSection();await selectItem(record,false);};return button;}
  function renderPanels() {
    updateContext(state);const p=state.physician,facts=$('summary-facts');facts.replaceChildren();
    for(const [label,value] of [['Estado del perfil',statuses[p.status]||p.status||'Sin estado registrado'],['Pendientes de revisión',String(p.pending_count??state.pending_items.length)]]){const fact=node('div','fact');fact.append(node('span','',label),node('strong','',value));facts.append(fact);}
    const tasks=$('summary-pending');tasks.replaceChildren();for(const record of state.pending_items)tasks.append(pendingButton(record));if(!state.pending_items.length)tasks.append(node('p','muted','No hay pendientes de revisión para este médico.'));
    const activity=$('activity-list');activity.replaceChildren();$('recorded-activity').hidden=!state.activity?.length;for(const record of state.activity||[])activity.append(node('p','',`${record.label||labels[record.purpose]||'Imagen'} · ${({APPROVED:'Aprobada',NEEDS_WORK:'Otra imagen solicitada',REJECTED:'Rechazada',WITHDRAWN:'Retirada'}[record.status]||record.status)} · ${dateLabel(record.recorded_at)}`));
    const media=$('media-items');media.replaceChildren();$('images-empty').hidden=state.pending_items.length>0;
    for(const record of state.pending_items){const button=node('button','media-item');button.type='button';button.dataset.submissionId=record.submission_id;button.setAttribute('aria-pressed',String(item?.submission_id===record.submission_id));button.append(node('strong','',labels[record.purpose]||'Imagen'),node('span','',dateLabel(record.created_at)));button.onclick=()=>selectItem(record);media.append(button);}
    const published=$('published-list');published.replaceChildren();$('published-media').hidden=!state.published_assets?.length;
    for(const record of state.published_assets||[]){const card=node('div','published-item');if(typeof record.public_url==='string'&&record.public_url){try{const url=new URL(record.public_url,location.origin);if(url.origin===location.origin){const image=node('img');image.src=url.href;image.alt=record.alt_text||labels[record.purpose]||'Imagen publicada';image.loading='lazy';card.append(image);}}catch{/* Invalid public projections are not requested. */}}card.append(node('span','',labels[record.purpose]||'Imagen publicada'));published.append(card);}
    showSection();
  }
  function showSection() {$('summary-panel').hidden=section!=='summary';$('images-panel').hidden=section!=='images';$('work-panel').setAttribute('aria-labelledby','tab-'+section);$('work-panel').dataset.doctorId=doctor||'';$('work-panel').dataset.section=section;}
  function closeMore() {$('more-menu').hidden=true;$('more-toggle').setAttribute('aria-expanded','false');}
  function renderTabs() {
    if(!state?.sections)return;const tabs=$('section-tabs'),menu=$('more-menu');tabs.replaceChildren();menu.replaceChildren();closeMore();
    const available=state.sections;if(!available.some(t=>t.id===section))section=available[0]?.id||'summary';
    for(const entry of available){const button=node('button','section-tab',entry.label);button.type='button';button.id='tab-'+entry.id;button.dataset.section=entry.id;button.setAttribute('role','tab');button.setAttribute('aria-selected',String(entry.id===section));button.setAttribute('aria-controls','work-panel');button.tabIndex=entry.id===section?0:-1;button.onclick=()=>switchSection(entry.id);tabs.append(button);}
    const compact=matchMedia('(max-width:520px)').matches;const width=$('section-navigation').clientWidth,buttons=[...tabs.children];let used=0;const total=buttons.reduce((sum,b)=>sum+b.getBoundingClientRect().width,0);const overflow=compact&&buttons.length>1||total>width;
    const hidden=[];for(const button of buttons){const w=button.getBoundingClientRect().width;const hide=compact?button.dataset.section!==section:overflow&&used+w>width-90;if(hide)hidden.push(button);else used+=w;}
    for(const button of hidden){button.hidden=true;const option=node('button','',button.textContent);option.type='button';option.dataset.section=button.dataset.section;option.setAttribute('role','menuitemradio');option.setAttribute('aria-checked',String(button.dataset.section===section));option.onclick=()=>switchSection(button.dataset.section);menu.append(option);}
    $('more-sections').hidden=hidden.length===0;showSection();
  }
  function switchSection(id) {if(busy||id===section){closeMore();return;}if(!leaveDraft())return;resetDraft();section=id;renderTabs();showSection();const tab=$('tab-'+id);(tab&&!tab.hidden?tab:$('more-toggle')).focus({preventScroll:true});}
  function controls() {
    if(!$('media-detail'))return;
    const valid=!!item;const allowed=(name)=>valid&&options[name]===true;
    $('download-original').hidden=!allowed('can_download_source');$('request-replacement').hidden=!allowed('can_request_replacement');$('approve-image').hidden=!allowed('can_approve');$('approve-image').disabled=busy||!reviewLoaded;
    $('correction-section').hidden=!allowed('can_upload_corrected');$('improvement-section').hidden=!allowed('can_improve')||item?.purpose!=='PHYSICIAN_PERSONAL_LOGO';
    for(const el of $('media-detail').querySelectorAll('button,input,select,textarea'))el.disabled=busy;
    $('approve-image').disabled=busy||!reviewLoaded;$('accept-improvement').disabled=busy||!proposalLoaded||!reviewLoaded;
    $('staff-logout').disabled=busy;$('refresh-images').disabled=busy;
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
      const {data}=await json(readPath(d,directoryMode==='search'?lastQuery:''));if(epoch!==contextEpoch||d!==doctor||expired)return;actor(data);
      if(d&&!data.physician){clearContext();state=data;say('Tu acceso o el perfil disponible cambió. La vista se actualizó.');showDiscovery('pending');return;}
      const tabsChanged=JSON.stringify(state?.sections)!==JSON.stringify(data.sections);state=data;if(!$('discovery').hidden)discovery(data);if(d){$('physician-workspace').hidden=false;renderPanels();if(tabsChanged)renderTabs();if(item){const current=data.pending_items.find(v=>v.submission_id===item.submission_id);if(!current){clearItem();say('El pendiente ya fue atendido o dejó de estar disponible.');}else{const selectedEpoch=itemEpoch;const next=await freshOptions(d);if(epoch!==contextEpoch||doctor!==d||selectedEpoch!==itemEpoch)return;const lostDraft=dirty()&&((options.can_request_replacement&&!next.can_request_replacement)||(options.can_upload_corrected&&!next.can_upload_corrected));options=next;if(lostDraft){resetDraft();mediaSay('Tus permisos cambiaron. Se descartaron los cambios que ya no puedes guardar.');}controls();}}}
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
  $('pending-toggle').onclick=()=>{if(busy)return;showDiscovery('pending');};
  $('close-discovery').onclick=()=>{$('discovery').hidden=true;$('pending-toggle').setAttribute('aria-expanded','false');$('physician-name').focus({preventScroll:true});};
  $('change-physician').onclick=()=>{if(busy)return;showDiscovery('search');$('physician-search').focus();};
  $('refresh-images').onclick=()=>refresh();
  $('more-toggle').onclick=()=>{const open=$('more-menu').hidden;$('more-menu').hidden=!open;$('more-toggle').setAttribute('aria-expanded',String(open));if(open)$('more-menu').querySelector('button')?.focus();};
  $('more-menu').addEventListener('keydown',event=>{const buttons=[...event.currentTarget.querySelectorAll('button')],i=buttons.indexOf(document.activeElement);if(event.key==='Escape'){closeMore();$('more-toggle').focus();event.preventDefault();}else if(['ArrowDown','ArrowUp','Home','End'].includes(event.key)){event.preventDefault();buttons[event.key==='Home'?0:event.key==='End'?buttons.length-1:(i+(event.key==='ArrowDown'?1:-1)+buttons.length)%buttons.length]?.focus();}});
  $('section-tabs').addEventListener('keydown',event=>{if(!['ArrowRight','ArrowLeft','Home','End'].includes(event.key))return;const tabs=state.sections,i=tabs.findIndex(t=>t.id===section);const target=event.key==='Home'?0:event.key==='End'?tabs.length-1:(i+(event.key==='ArrowRight'?1:-1)+tabs.length)%tabs.length;event.preventDefault();switchSection(tabs[target].id);});
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
  window.addEventListener('resize',()=>{if(doctor)renderTabs();});
  window.addEventListener('pageshow',async event=>{if(event.persisted){const previous=item?.submission_id;clearItem();$('physician-workspace').hidden=true;$('discovery-list').replaceChildren();expired=false;await refresh();const restored=state?.pending_items?.find(record=>record.submission_id===previous);if(!expired&&doctor&&restored)await selectItem(restored,false);}});
  document.addEventListener('visibilitychange',()=>{if(!document.hidden)refresh();});
  window.addEventListener('pagehide',()=>{directoryController?.abort();contextController?.abort();itemController?.abort();releaseImages();});
  setInterval(()=>{if(!document.hidden)refresh();},15000);
  loadDirectory('');
})();
