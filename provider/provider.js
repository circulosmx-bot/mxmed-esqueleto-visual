(() => {
  'use strict';
  const API = '/api/provider/index.php';
  const content = document.getElementById('portal-content');
  const nav = document.getElementById('module-nav');
  const picker = document.getElementById('organization-picker');
  const single = document.getElementById('single-organization');
  const notice = document.getElementById('notice');
  const titles = { summary:'Resumen', locations:'Sucursales', services:'Servicios', areas:'Áreas de servicio', team:'Equipo', subscription:'Suscripción' };
  const fields = [['branch_name','Nombre de la sucursal'],['street','Calle'],['exterior_number','Número exterior'],['interior_number','Número interior'],['postal_code','Código postal'],['colonia','Colonia'],['municipality','Municipio o alcaldía'],['state_name','Estado'],['phone','Teléfono']];
  const material = fields.map(f => f[0]).filter(f => f !== 'phone');
  const state = { csrf:'', organizations:[], group:'', context:null, locations:[], module:'summary', branch:'', selectedLocation:'', editingLocation:false, selectedOffering:'', offerings:[], areas:[], subscription:null, team:null, invitations:[], catalog:[], categories:[], catalogSearch:'', catalogCategory:'', generation:0, controller:null, dirty:false, snapshot:'', pendingKey:'', pendingFingerprint:'' };
  const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const val = v => v === null || v === undefined || v === '' ? '—' : esc(v);
  const path = (...parts) => `${API}/${parts.map(x => encodeURIComponent(String(x))).join('/')}`;
  const orgPath = (...parts) => path('organizations', state.group, ...parts);
  const fmtDate = v => { if (!v) return '—'; const d = new Date(String(v).replace(' ','T')); return Number.isNaN(d.getTime()) ? esc(v) : new Intl.DateTimeFormat('es-MX',{dateStyle:'medium'}).format(d); };
  const uuid = () => crypto.randomUUID();
  function submissionKey(scope, payload) { const fingerprint=JSON.stringify([state.group,scope,payload]); if(state.pendingFingerprint!==fingerprint){state.pendingFingerprint=fingerprint;state.pendingKey=uuid();} return state.pendingKey; }
  function clearSubmission() { state.pendingKey=''; state.pendingFingerprint=''; }
  const labels = { LABORATORY:'Laboratorio', DIAGNOSTIC_CENTER:'Centro de diagnóstico', CLINIC:'Clínica', HOSPITAL:'Hospital', DENTAL_ORGANIZATION:'Organización dental', OTHER_HEALTHCARE_ORGANIZATION:'Organización de salud', active:'Activo', cancelled:'Cancelado', expired:'Expirado', expiring_soon:'Por vencer', grace_period:'Periodo de gracia', annual:'Anual', monthly:'Mensual', ACTIVE:'Activo', INACTIVE:'Inactivo', VERIFIED:'Verificado', UNVERIFIED:'Pendiente de verificación', REJECTED:'No verificado', PENDING:'Pendiente', ACCEPTED:'Aceptada', REVOKED:'Revocada', EXPIRED:'Expirada', ON_SITE:'En sucursal', HOME_SERVICE:'Servicio a domicilio', MOBILE:'Servicio móvil', owner:'Propietario', administrator:'Administrador', collaborator:'Colaborador' };
  const label = v => labels[v] || esc(v || 'Sin dato');
  const action = name => state.context?.actions?.[name] || {allowed:false, entitlement_required:false};
  const allowed = name => action(name).allowed === true;
  const gated = name => !allowed(name) && action(name).entitlement_required === true;
  const gateText = 'Se requiere un plan de proveedor activo para administrar esta sección.';
  function alert(message, type='success') { notice.textContent = message; notice.className = `notice ${type}`; notice.hidden = false; }
  function clearAlert() { notice.hidden = true; notice.textContent = ''; }
  function errorMessage(error) {
    if (error.name === 'AbortError') return '';
    const code = error.code || '';
    if (error.status === 401) return 'Tu sesión terminó. Inicia sesión para continuar.';
    if (code === 'ENTITLEMENT_REQUIRED') return gateText;
    if (error.status === 403) return 'Tu cuenta no tiene permiso para esta acción.';
    if (error.status === 404) return 'El recurso ya no está disponible para esta organización.';
    if (error.status === 429) return 'Se alcanzó el límite de consultas. Inténtalo más tarde.';
    if (error.status === 409) return 'Este cambio entra en conflicto con la configuración actual. Actualiza la sección e inténtalo de nuevo.';
    if (error.status === 422) return 'Revisa los datos capturados e inténtalo de nuevo.';
    return 'No pudimos completar la solicitud. Inténtalo de nuevo.';
  }
  async function request(url, method='GET', body, signal) {
    const options = {method, credentials:'same-origin', cache:'no-store', headers:{Accept:'application/json'}, signal};
    if (method !== 'GET') { options.headers['Content-Type']='application/json'; options.headers['X-CSRF-Token']=state.csrf; options.body=JSON.stringify(body || {}); }
    const response = await fetch(url, options);
    const result = await response.json().catch(() => ({}));
    if (!response.ok || result.ok === false) { const e = new Error('Request failed'); e.status=response.status; e.code=result.error; throw e; }
    return result.data ?? result;
  }
  function newGeneration() { state.controller?.abort(); state.controller = new AbortController(); return ++state.generation; }
  function guard() { return !state.dirty || window.confirm('Hay cambios sin guardar. ¿Deseas descartarlos?'); }
  window.addEventListener('beforeunload', e => { if (state.dirty) { e.preventDefault(); e.returnValue=''; } });
  function markClean(form) { state.snapshot = form ? JSON.stringify(Object.fromEntries(new FormData(form))) : ''; state.dirty=false; }
  function watchForm(form) { if (!form) return; markClean(form); form.addEventListener('input', () => { state.dirty=JSON.stringify(Object.fromEntries(new FormData(form)))!==state.snapshot; }); form.addEventListener('change', () => { state.dirty=JSON.stringify(Object.fromEntries(new FormData(form)))!==state.snapshot; }); }
  function heading(title, description='', actions='') { return `<div class="content-head"><div><h2>${esc(title)}</h2>${description?`<p>${esc(description)}</p>`:''}</div>${actions}</div>`; }
  function empty(title, copy='', button='') { return `<div class="empty"><strong>${esc(title)}</strong><span>${esc(copy)}</span>${button}</div>`; }
  function gate(name) { return `<div class="gate">${gated(name) ? gateText : 'Tu cuenta no tiene permiso para administrar esta sección.'}</div>`; }
  function setLoading(text='Cargando sección…') { content.innerHTML=`<p class="loading" role="status">${esc(text)}</p>`; }
  function renderNav() {
    const visible = ['summary','locations','services','areas','team','subscription'].filter(k => k === 'summary' || k === 'subscription' || (k === 'team' ? allowed('provider_team_manage') : state.context?.member_role !== 'collaborator'));
    nav.innerHTML = visible.map(k => `<button type="button" data-module="${k}" ${state.module===k?'aria-current="page"':''}>${titles[k]}</button>`).join('');
    nav.hidden = false;
    nav.querySelectorAll('button').forEach(button => button.addEventListener('click', () => switchModule(button.dataset.module)));
  }
  function setIntro() {
    document.getElementById('page-title').textContent=state.context?.display_name || 'Tu organización';
    const status=state.context?.provider_status;
    document.getElementById('status-chips').innerHTML=status ? `<span class="chip ${status.operational_state==='ACTIVE'?'good':'warn'}">${label(status.operational_state)}</span><span class="chip ${status.verification_state==='VERIFIED'?'good':'warn'}">${label(status.verification_state)}</span>` : '';
  }
  async function bootstrap() {
    try {
      const session=await request('/api/identity/index.php/current-session');
      if (!session.authenticated || !session.csrf_token) { location.assign('/acceso?next=%2Fprovider%2F'); return; }
      state.csrf=session.csrf_token;
      const result=await request(path('me','organizations'));
      state.organizations=result.organizations || [];
      if (!state.organizations.length) { nav.hidden=true; single.textContent=''; content.innerHTML=empty('No tienes organizaciones de proveedor disponibles en esta cuenta.','Cuando se te asigne una organización, podrás verla aquí.'); return; }
      if (state.organizations.length > 1) {
        picker.innerHTML=state.organizations.map(o=>`<option value="${esc(o.group_id)}">${esc(o.display_name)}</option>`).join(''); picker.hidden=false; single.hidden=true;
        picker.addEventListener('change',()=>{ if(!guard()){picker.value=state.group;return;} selectOrganization(picker.value); });
      } else { single.textContent=state.organizations[0].display_name; }
      await selectOrganization(state.organizations[0].group_id);
    } catch(e) { alert(errorMessage(e),'error'); content.innerHTML=empty('No pudimos cargar el portal.','Actualiza la página para volver a intentarlo.'); }
  }
  async function selectOrganization(group) {
    const generation=newGeneration(); state.group=group; state.context=null; state.locations=[]; state.offerings=[]; state.areas=[]; state.branch=''; state.selectedLocation=''; state.selectedOffering=''; state.dirty=false; clearSubmission(); state.module='summary'; nav.hidden=true; clearAlert(); setLoading('Cargando organización…');
    try {
      const context=await request(orgPath(), 'GET', undefined, state.controller.signal);
      if (generation!==state.generation) return;
      state.context=context; picker.value=group; setIntro(); renderNav(); await switchModule('summary',true);
    } catch(e) { if (generation!==state.generation || e.name==='AbortError') return; alert(errorMessage(e),'error'); content.innerHTML=empty('No pudimos abrir esta organización.'); }
  }
  async function switchModule(module, force=false) {
    if (!force && !guard()) return;
    const generation=newGeneration(); state.module=module; state.dirty=false; state.selectedLocation=''; state.selectedOffering=''; state.editingLocation=false; clearAlert(); renderNav(); setLoading();
    try {
      if (module==='summary') await loadSummary(generation);
      if (module==='locations') await loadLocations(generation);
      if (module==='services') await loadServices(generation);
      if (module==='areas') await loadAreas(generation);
      if (module==='team') await loadTeam(generation);
      if (module==='subscription') await loadSubscription(generation);
    } catch(e) { if(e.name==='AbortError'||generation!==state.generation)return; alert(errorMessage(e),'error'); content.innerHTML=empty('No pudimos cargar esta sección.','Inténtalo de nuevo.'); }
  }
  async function fetchLocations(generation) {
    if (!allowed('provider_locations_manage')) { state.locations=[]; return; }
    const data=await request(orgPath('locations'),'GET',undefined,state.controller.signal);
    if(generation!==state.generation)return;
    state.locations=data.locations||[];
  }
  async function loadSummary(generation) {
    const reads=[request(orgPath('subscription'),'GET',undefined,state.controller.signal)];
    if(allowed('provider_locations_manage')) reads.push(request(orgPath('locations'),'GET',undefined,state.controller.signal));
    const results=await Promise.allSettled(reads);
    if(generation!==state.generation)return;
    state.subscription=results[0].status==='fulfilled'?results[0].value:null;
    state.locations=results[1]?.status==='fulfilled'?(results[1].value.locations||[]):[];
    const loc=state.locations, offerings=loc.flatMap(x=>x.offerings||[]);
    const status=state.context.provider_status||{};
    const verifiedLoc=loc.filter(x=>x.verification_state==='VERIFIED').length; const pendingLoc=loc.filter(x=>x.verification_state==='UNVERIFIED').length; const rejectedLoc=loc.filter(x=>x.verification_state==='REJECTED').length;
    const verifiedOffer=offerings.filter(x=>x.verification_state==='VERIFIED').length; const pendingOffer=offerings.filter(x=>x.verification_state==='UNVERIFIED').length; const rejectedOffer=offerings.filter(x=>x.verification_state==='REJECTED').length;
    const counts=allowed('provider_locations_manage');
    const attention=[];
    if(counts&&loc.length===0)attention.push('No hay sucursales registradas.');
    if(counts&&pendingLoc>0)attention.push('Hay sucursales pendientes de verificación.');
    if(counts&&offerings.length===0)attention.push('No hay servicios configurados.');
    if(!state.context.commercial_entitled)attention.push('Plan de proveedor no activo.');
    content.innerHTML=heading('Resumen','Estado actual de tu organización y su configuración.')+`<div class="grid">
      <div class="metric"><span class="label">Organización</span><strong>${esc(state.context.display_name)}</strong><small>${label(state.context.organization_type)} · ${label(state.context.member_role)}</small></div>
      <div class="metric"><span class="label">Estado del proveedor</span><strong>${label(status.operational_state)}</strong><small>${label(status.verification_state)}</small></div>
      <div class="metric"><span class="label">Plan / acceso comercial</span><strong>${state.context.commercial_entitled?'Activo':'Sin acceso activo'}</strong><small>${state.subscription?.subscription?'Estado del plan: '+label(state.subscription.subscription.status):'Estado de acceso para administrar secciones'}</small></div>
      <div class="metric"><span class="label">Sucursales</span><strong>${counts?loc.length:'—'}</strong><small>${counts?`${verifiedLoc} verificadas · ${pendingLoc} pendientes${rejectedLoc?' · '+rejectedLoc+' no verificadas':''}`:'Datos disponibles para administradores autorizados'}</small></div>
      <div class="metric"><span class="label">Servicios</span><strong>${counts?offerings.length:'—'}</strong><small>${counts?`${verifiedOffer} verificados · ${pendingOffer} pendientes${rejectedOffer?' · '+rejectedOffer+' no verificados':''}`:'Datos disponibles para administradores autorizados'}</small></div>
      </div>${attention.length?`<div class="attention"><strong>Para revisar</strong>${attention.map(x=>`<p>${esc(x)}</p>`).join('')}</div>`:''}`;
    if(results[0].status==='rejected'||results[1]?.status==='rejected') alert('Algunos datos del resumen no están disponibles en este momento.','warn');
  }
  async function loadLocations(generation) { if(!allowed('provider_locations_manage')){content.innerHTML=heading('Sucursales')+gate('provider_locations_manage');return;} await fetchLocations(generation);if(generation===state.generation)renderLocations(); }
  function address(loc){return [loc.street,loc.exterior_number,loc.interior_number,loc.colonia,loc.municipality,loc.state_name,loc.postal_code].filter(Boolean).join(', ');}
  function renderLocations(){
    content.innerHTML=heading('Sucursales','Administra ubicación y disponibilidad de cada sucursal.','<button type="button" class="button" id="add-location">Agregar sucursal</button>')+(state.locations.length?`<div class="list">${state.locations.map(l=>`<div class="list-row"><div><h3>${esc(l.branch_name)}</h3><p>${esc(address(l))}</p><p>${l.phone?`Tel. ${esc(l.phone)} · `:''}${label(l.operational_state)} · ${label(l.verification_state)} · ${(l.offerings||[]).length} servicio(s)</p></div><div class="row-actions"><button type="button" class="button secondary" data-edit-location="${esc(l.location_uuid)}">Abrir</button><button type="button" class="button quiet" data-toggle-location="${esc(l.location_uuid)}">${l.operational_state==='ACTIVE'?'Desactivar':'Activar'}</button></div></div>`).join('')}</div>`:empty('No hay sucursales registradas.','Agrega una sucursal para configurar sus servicios.'))+`<div id="location-detail"></div>`;
    document.getElementById('add-location').onclick=()=>{if(guard())showLocationForm(null);};
    content.querySelectorAll('[data-edit-location]').forEach(b=>b.onclick=()=>{if(guard())showLocationForm(state.locations.find(l=>l.location_uuid===b.dataset.editLocation));});
    content.querySelectorAll('[data-toggle-location]').forEach(b=>b.onclick=()=>toggleLocation(b.dataset.toggleLocation));
    if(state.selectedLocation)showLocationForm(state.locations.find(l=>l.location_uuid===state.selectedLocation)||null);
  }
  function showLocationForm(loc){
    state.selectedLocation=loc?.location_uuid||'';state.editingLocation=true;
    const target=document.getElementById('location-detail');
    target.innerHTML=`<div class="detail"><h3>${loc?'Editar sucursal':'Nueva sucursal'}</h3><form id="location-form" class="form-grid">${fields.map(([key,title])=>`<div class="field"><label for="loc-${key}">${title}</label><input id="loc-${key}" name="${key}" value="${esc(loc?.[key]||'')}" ${key==='postal_code'?'inputmode="numeric" pattern="[0-9]{5}"':''} ${key==='branch_name'?'required':''}></div>`).join('')}<div class="wide button-row"><button class="button" type="submit">Guardar sucursal</button><button class="button secondary" type="button" id="cancel-location">Cancelar</button></div></form></div>`;
    const form=document.getElementById('location-form');watchForm(form);
    form.onsubmit=async e=>{e.preventDefault();const data=Object.fromEntries(new FormData(form));if(loc?.verification_state==='VERIFIED'&&material.some(key=>String(data[key]||'')!==String(loc[key]||''))&&!confirm('Este cambio requiere una nueva verificación de la sucursal. ¿Deseas continuar?'))return;
      const submit=form.querySelector('[type=submit]');submit.disabled=true;try{const payload={};for(const [key] of fields) if(data[key]!==''||!loc)payload[key]=data[key]||null;if(!loc)payload.submission_key=submissionKey('location-create',payload);await request(loc?orgPath('locations',loc.location_uuid):orgPath('locations'),loc?'PATCH':'POST',payload);clearSubmission();state.dirty=false;state.selectedLocation='';const gen=newGeneration();await fetchLocations(gen);if(gen===state.generation){renderLocations();alert('Sucursal guardada.');}}catch(error){if(error.status===422||error.status===409)clearSubmission();alert(errorMessage(error),'error');}finally{submit.disabled=false;}};
    document.getElementById('cancel-location').onclick=()=>{if(!guard())return;state.dirty=false;state.selectedLocation='';target.innerHTML='';};
    target.scrollIntoView({block:'nearest'});
  }
  async function toggleLocation(id){const loc=state.locations.find(l=>l.location_uuid===id);if(!loc)return;if(!confirm(loc.operational_state==='ACTIVE'?'Desactivar la sucursal la retira de la búsqueda de coincidencias sin borrar su historial. ¿Continuar?':'¿Activar esta sucursal?'))return;try{await request(orgPath('locations',id,'state'),'PATCH',{operational_state:loc.operational_state==='ACTIVE'?'INACTIVE':'ACTIVE'});const gen=newGeneration();await fetchLocations(gen);if(gen===state.generation){renderLocations();alert('Estado de la sucursal actualizado.');}}catch(e){alert(errorMessage(e),'error');}}
  function branchPicker(id='branch-picker'){return `<div class="field"><label for="${id}">Sucursal</label><select id="${id}">${state.locations.map(l=>`<option value="${esc(l.location_uuid)}" ${state.branch===l.location_uuid?'selected':''}>${esc(l.branch_name)}</option>`).join('')}</select></div>`;}
  async function loadServices(generation){if(!allowed('provider_offerings_manage')){content.innerHTML=heading('Servicios')+gate('provider_offerings_manage');return;}await fetchLocations(generation);if(generation!==state.generation)return;if(!state.branch||!state.locations.some(l=>l.location_uuid===state.branch))state.branch=state.locations[0]?.location_uuid||'';await refreshOfferings(generation);}
  async function refreshOfferings(generation){if(!state.branch){renderServices();return;}const data=await request(orgPath('locations',state.branch,'offerings'),'GET',undefined,state.controller.signal);if(generation!==state.generation)return;state.offerings=data.offerings||[];renderServices();}
  function renderServices(){
    content.innerHTML=heading('Servicios','Configura los estudios ofrecidos por cada sucursal.')+(state.locations.length?`<div class="toolbar">${branchPicker()}</div><div class="split"><div class="panel"><h3>Estudios configurados</h3>${state.offerings.length?`<div class="list">${state.offerings.map(o=>`<div class="list-row"><div><h3>${esc(o.display_name_es)}</h3><p>${label(o.service_mode)} · ${label(o.operational_state)} · ${label(o.verification_state)}</p></div><div class="row-actions"><button type="button" class="button secondary" data-edit-offering="${o.study_type_id}">Configurar</button><button type="button" class="button quiet" data-toggle-offering="${o.study_type_id}">${o.operational_state==='ACTIVE'?'Desactivar':'Activar'}</button></div></div>`).join('')}</div>`:empty('No hay servicios configurados.','Busca un estudio del catálogo para agregarlo.')}</div><div class="panel" id="service-detail"><h3>Agregar estudio</h3><div class="toolbar"><div class="field grow"><label for="study-search">Buscar estudio</label><input id="study-search" type="search" value="${esc(state.catalogSearch)}" placeholder="Nombre o sinónimo"></div><div class="field"><label for="study-category">Categoría</label><select id="study-category"><option value="">Todas</option>${state.categories.map(c=>`<option value="${esc(c.category_key)}" ${state.catalogCategory===c.category_key?'selected':''}>${esc(c.label_es)}</option>`).join('')}</select></div></div><div id="catalog-results"><p class="muted">Escribe para buscar en el catálogo clínico.</p></div></div></div>`:empty('No hay sucursales registradas.','Agrega una sucursal antes de configurar servicios.'));
    const bp=document.getElementById('branch-picker');if(bp)bp.onchange=async()=>{if(!guard()){bp.value=state.branch;return;}state.branch=bp.value;state.selectedOffering='';state.catalog=[];const gen=newGeneration();setLoading('Cargando servicios…');try{await refreshOfferings(gen);}catch(e){if(e.name!=='AbortError')alert(errorMessage(e),'error');}};
    content.querySelectorAll('[data-edit-offering]').forEach(b=>b.onclick=()=>{if(guard())showOfferingForm(state.offerings.find(o=>String(o.study_type_id)===b.dataset.editOffering));});
    content.querySelectorAll('[data-toggle-offering]').forEach(b=>b.onclick=()=>toggleOffering(Number(b.dataset.toggleOffering)));
    const search=document.getElementById('study-search'),cat=document.getElementById('study-category');if(search){let timer;search.oninput=()=>{state.catalogSearch=search.value;clearTimeout(timer);timer=setTimeout(searchCatalog,250);};cat.onchange=()=>{state.catalogCategory=cat.value;searchCatalog();};if(state.catalog.length)renderCatalog();}
  }
  async function searchCatalog(){const search=state.catalogSearch.trim(),category=state.catalogCategory;const url=new URL(path('study-types'),location.origin);url.searchParams.set('limit','30');if(search)url.searchParams.set('search',search);if(category)url.searchParams.set('category',category);const target=document.getElementById('catalog-results');if(!target)return;target.innerHTML='<p class="loading">Buscando estudios…</p>';const branch=state.branch;try{const data=await request(url.toString());if(branch!==state.branch||state.module!=='services')return;state.catalog=data.items||[];state.categories=data.categories||[];const categorySelect=document.getElementById('study-category');if(categorySelect&&categorySelect.options.length<=1){categorySelect.innerHTML=`<option value="">Todas</option>${state.categories.map(c=>`<option value="${esc(c.category_key)}">${esc(c.label_es)}</option>`).join('')}`;categorySelect.value=category;}renderCatalog();}catch(e){target.textContent=errorMessage(e);}}
  function renderCatalog(){const target=document.getElementById('catalog-results');if(!target)return;target.innerHTML=state.catalog.length?state.catalog.map(item=>{const configured=state.offerings.some(o=>Number(o.study_type_id)===Number(item.study_type_id));return `<div class="catalog-row"><div><strong>${esc(item.display_name_es)}</strong><br><span>${esc(item.category_label_es)}</span></div>${configured?'<span>Configurado</span>':`<button type="button" class="button secondary" data-add-study="${item.study_type_id}">Agregar</button>`}</div>`;}).join(''):empty('Sin estudios coincidentes.','Prueba otro término o categoría.');target.querySelectorAll('[data-add-study]').forEach(b=>b.onclick=()=>addOffering(Number(b.dataset.addStudy)));}
  async function addOffering(id){if(!allowed('provider_offerings_manage'))return;const button=content.querySelector(`[data-add-study="${id}"]`);if(button)button.disabled=true;const key=submissionKey('offering-create:'+state.branch,{study_type_id:id});try{await request(orgPath('locations',state.branch,'offerings'),'POST',{study_type_id:id,submission_key:key});clearSubmission();const gen=newGeneration();await refreshOfferings(gen);alert('Servicio agregado. Queda pendiente de verificación.');}catch(e){if(e.status===422||e.status===409)clearSubmission();alert(errorMessage(e),'error');if(button)button.disabled=false;}}
  function showOfferingForm(o){if(!o)return;state.selectedOffering=String(o.study_type_id);const target=document.getElementById('service-detail');target.innerHTML=`<h3>${esc(o.display_name_es)}</h3><p class="inline-note">${label(o.verification_state)} · La identidad del estudio no se puede cambiar. Si elegiste otro estudio, desactiva este servicio y agrega el correcto.</p><form id="offering-form"><div class="field"><label for="service-mode">Modalidad del servicio</label><select id="service-mode" name="service_mode">${['ON_SITE','HOME_SERVICE','MOBILE'].map(x=>`<option value="${x}" ${o.service_mode===x?'selected':''}>${label(x)}</option>`).join('')}</select></div><p><label class="check-field"><input name="requires_appointment" type="checkbox" value="1" ${Number(o.requires_appointment)===1?'checked':''}> Requiere cita</label></p><div class="field"><label for="preparation">Indicaciones de preparación</label><textarea id="preparation" name="preparation_instructions">${esc(o.preparation_instructions||'')}</textarea></div><div class="button-row"><button type="submit" class="button">Guardar configuración</button><button type="button" class="button secondary" id="cancel-offering">Cancelar</button></div></form>`;
    const form=document.getElementById('offering-form');watchForm(form);form.onsubmit=async e=>{e.preventDefault();const data={service_mode:form.elements.namedItem('service_mode').value,requires_appointment:form.elements.namedItem('requires_appointment').checked,preparation_instructions:form.elements.namedItem('preparation_instructions').value||null};const submit=form.querySelector('[type=submit]');submit.disabled=true;try{await request(orgPath('locations',state.branch,'offerings',o.study_type_id),'PATCH',data);state.dirty=false;const gen=newGeneration();await refreshOfferings(gen);alert('Configuración guardada.');}catch(err){alert(errorMessage(err),'error');}finally{submit.disabled=false;}};document.getElementById('cancel-offering').onclick=()=>{if(guard()){state.dirty=false;renderServices();}};
  }
  async function toggleOffering(id){const o=state.offerings.find(x=>Number(x.study_type_id)===id);if(!o)return;if(!confirm(o.operational_state==='ACTIVE'?'¿Desactivar este servicio sin borrar su configuración?':'¿Activar este servicio?'))return;try{await request(orgPath('locations',state.branch,'offerings',id,'state'),'PATCH',{operational_state:o.operational_state==='ACTIVE'?'INACTIVE':'ACTIVE'});const gen=newGeneration();await refreshOfferings(gen);alert('Estado del servicio actualizado.');}catch(e){alert(errorMessage(e),'error');}}
  async function loadAreas(generation){if(!allowed('provider_service_areas_manage')){content.innerHTML=heading('Áreas de servicio')+gate('provider_service_areas_manage');return;}await fetchLocations(generation);if(generation!==state.generation)return;if(!state.branch||!state.locations.some(l=>l.location_uuid===state.branch))state.branch=state.locations[0]?.location_uuid||'';await refreshAreas(generation);}
  async function refreshAreas(generation){if(!state.branch){renderAreas();return;}const data=await request(orgPath('locations',state.branch,'offerings'),'GET',undefined,state.controller.signal);if(generation!==state.generation)return;state.offerings=(data.offerings||[]).filter(o=>['HOME_SERVICE','MOBILE'].includes(o.service_mode));if(!state.offerings.some(o=>String(o.study_type_id)===state.selectedOffering))state.selectedOffering=String(state.offerings[0]?.study_type_id||'');if(state.selectedOffering){const result=await request(orgPath('locations',state.branch,'offerings',state.selectedOffering,'service-areas'),'GET',undefined,state.controller.signal);if(generation!==state.generation)return;state.areas=result.service_areas||[];}else state.areas=[];renderAreas();}
  function renderAreas(){content.innerHTML=heading('Áreas de servicio','Las áreas de servicio indican dónde puedes prestar este servicio fuera de la sucursal.')+(state.locations.length?`<div class="toolbar">${branchPicker('area-branch')}<div class="field"><label for="area-offering">Servicio</label><select id="area-offering">${state.offerings.map(o=>`<option value="${o.study_type_id}" ${String(o.study_type_id)===state.selectedOffering?'selected':''}>${esc(o.display_name_es)} · ${label(o.service_mode)}</option>`).join('')}</select></div></div>${state.offerings.length?`<div class="list">${state.areas.length?state.areas.map(a=>`<div class="list-row"><div><h3>Código postal ${esc(a.postal_code)}</h3><p>${label(a.operational_state)} · ${label(a.verification_state)}</p></div><button type="button" class="button quiet" data-toggle-area="${a.service_area_id}">${a.operational_state==='ACTIVE'?'Desactivar':'Activar'}</button></div>`).join(''):empty('Aún no has indicado las zonas donde prestas este servicio.','Agrega un código postal para este servicio.')}</div><form id="area-form" class="toolbar detail"><div class="field"><label for="area-postal">Código postal</label><input id="area-postal" name="postal_code" inputmode="numeric" pattern="[0-9]{5}" maxlength="5" required></div><button type="submit" class="button">Agregar área</button></form><p class="inline-note">El código postal de un área ya creada no puede editarse. Desactívala y agrega una nueva si necesitas corregirla.</p>`:empty('No hay servicios con cobertura fuera de sucursal.','Los servicios en sucursal no requieren áreas. Configura un servicio a domicilio o móvil en Servicios.')}`:empty('No hay sucursales registradas.','Agrega una sucursal para definir cobertura.'));
    const branch=document.getElementById('area-branch');if(branch)branch.onchange=async()=>{if(!guard()){branch.value=state.branch;return;}state.branch=branch.value;state.selectedOffering='';const gen=newGeneration();setLoading();try{await refreshAreas(gen);}catch(e){if(e.name!=='AbortError')alert(errorMessage(e),'error');}};
    const offering=document.getElementById('area-offering');if(offering)offering.onchange=async()=>{if(!guard()){offering.value=state.selectedOffering;return;}state.selectedOffering=offering.value;const gen=newGeneration();setLoading();try{await refreshAreas(gen);}catch(e){if(e.name!=='AbortError')alert(errorMessage(e),'error');}};
    content.querySelectorAll('[data-toggle-area]').forEach(b=>b.onclick=()=>toggleArea(Number(b.dataset.toggleArea)));
    const form=document.getElementById('area-form');if(form){watchForm(form);form.onsubmit=async e=>{e.preventDefault();const button=form.querySelector('[type=submit]');button.disabled=true;const postal=form.elements.namedItem('postal_code').value;const key=submissionKey('area-create:'+state.branch+':'+state.selectedOffering,{postal_code:postal});try{await request(orgPath('locations',state.branch,'offerings',state.selectedOffering,'service-areas'),'POST',{scope_type:'POSTAL_CODE',postal_code:postal,submission_key:key});clearSubmission();state.dirty=false;const gen=newGeneration();await refreshAreas(gen);alert('Área agregada. Queda pendiente de verificación.');}catch(err){if(err.status===422||err.status===409)clearSubmission();alert(errorMessage(err),'error');}finally{button.disabled=false;}};}
  }
  async function toggleArea(id){const a=state.areas.find(x=>Number(x.service_area_id)===id);if(!a)return;if(!confirm(a.operational_state==='ACTIVE'?'¿Desactivar esta área de cobertura?':'¿Activar esta área de cobertura?'))return;try{await request(orgPath('locations',state.branch,'offerings',state.selectedOffering,'service-areas',id,'state'),'PATCH',{operational_state:a.operational_state==='ACTIVE'?'INACTIVE':'ACTIVE'});const gen=newGeneration();await refreshAreas(gen);alert('Estado del área actualizado.');}catch(e){alert(errorMessage(e),'error');}}
  async function loadTeam(generation){if(!allowed('provider_team_manage')){content.innerHTML=heading('Equipo')+gate('provider_team_manage');return;}const [members,invitations]=await Promise.all([request(orgPath('team'),'GET',undefined,state.controller.signal),request(orgPath('invitations'),'GET',undefined,state.controller.signal)]);if(generation!==state.generation)return;state.team=members.members||[];state.invitations=invitations.invitations||[];renderTeam();}
  function renderTeam(){content.innerHTML=heading('Equipo','Gestiona cuentas autorizadas para esta organización.')+`<div class="split"><div class="panel"><h3>Miembros</h3>${state.team.length?`<div class="list">${state.team.map(m=>`<div class="list-row"><div><strong>${esc(m.account_email||m.display_name||'Miembro')}</strong><p>${label(m.role_code||m.role)} · ${label(m.status||m.state)}</p></div>${(m.role_code||m.role)!=='owner'&&(m.status||m.state)==='active'?`<div class="row-actions"><button type="button" class="button quiet" data-member-action="suspend" data-member-id="${esc(m.membership_id)}">Suspender</button><button type="button" class="button danger" data-member-action="revoke" data-member-id="${esc(m.membership_id)}">Revocar</button></div>`:''}</div>`).join('')}</div>`:empty('Sin miembros disponibles.')}</div><div class="panel"><h3>Invitar a una cuenta</h3><form id="invite-form"><div class="field"><label for="invite-email">Correo de la cuenta</label><input id="invite-email" name="email" type="email" autocomplete="off" required></div><div class="field" class="invite-role"><label for="invite-role">Rol</label><select id="invite-role" name="role"><option value="administrator">Administrador</option><option value="collaborator">Colaborador</option></select></div><div class="button-row"><button class="button" type="submit">Revisar invitación</button></div></form><p id="invite-resolution" class="inline-note" role="status"></p></div></div><h3 class="small-title">Invitaciones pendientes</h3>${state.invitations.length?`<div class="list">${state.invitations.map(i=>`<div class="list-row"><div><strong>${esc(i.invitee_email||'Cuenta invitada')}</strong><p>${label(i.intended_role)} · ${label(i.status||i.state)} · Vence ${fmtDate(i.expires_at)}</p></div><button type="button" class="button danger" data-revoke-invite="${esc(i.invitation_uuid)}">Revocar</button></div>`).join('')}</div>`:empty('Sin invitaciones pendientes.')}`;
    content.querySelectorAll('[data-member-action]').forEach(b=>b.onclick=()=>memberAction(b.dataset.memberId,b.dataset.memberAction));
    content.querySelectorAll('[data-revoke-invite]').forEach(b=>b.onclick=()=>revokeInvite(b.dataset.revokeInvite));
    const form=document.getElementById('invite-form');watchForm(form);form.onsubmit=async e=>{e.preventDefault();const email=form.elements.namedItem('email').value.trim();const role=form.elements.namedItem('role').value;const button=form.querySelector('[type=submit]');button.disabled=true;const output=document.getElementById('invite-resolution');output.textContent='Comprobando cuenta…';try{const result=await request(orgPath('invitee-resolution'),'POST',{email});if(result.state!=='FOUND_ELIGIBLE'){output.textContent=inviteResolutionText(result.state);return;}output.textContent='Cuenta disponible para invitación.';if(!confirm(`¿Enviar invitación a ${email} como ${label(role)}?`))return;const key=submissionKey('team-invite',{email,role});await request(orgPath('invitations'),'POST',{invitee_email:email,role,submission_key:key});clearSubmission();state.dirty=false;const gen=newGeneration();await loadTeam(gen);alert('Invitación enviada.');}catch(err){if(err.status===422||err.status===409)clearSubmission();output.textContent=errorMessage(err);if(err.status===429)alert(errorMessage(err),'warn');}finally{button.disabled=false;}};
  }
  function inviteResolutionText(code){return ({SELF_INVITE:'No puedes invitar a tu propia cuenta.',ALREADY_MEMBER:'Esta cuenta ya pertenece al equipo.',PENDING_INVITATION:'Ya existe una invitación pendiente para esta cuenta.',NOT_FOUND_OR_NOT_INVITABLE:'No se puede invitar a esta cuenta.'})[code]||'No se puede invitar a esta cuenta.';}
  async function memberAction(id,verb){if(!id||!['suspend','revoke'].includes(verb))return;if(!confirm(verb==='suspend'?'¿Suspender este miembro?':'¿Revocar el acceso de este miembro?'))return;try{await request(orgPath('members',id,verb),'POST',{});const gen=newGeneration();await loadTeam(gen);alert('Equipo actualizado.');}catch(e){alert(errorMessage(e),'error');}}
  async function revokeInvite(id){if(!confirm('¿Revocar esta invitación pendiente?'))return;try{await request(orgPath('invitations',id,'revoke'),'POST',{});const gen=newGeneration();await loadTeam(gen);alert('Invitación revocada.');}catch(e){alert(errorMessage(e),'error');}}
  async function loadSubscription(generation){const data=await request(orgPath('subscription'),'GET',undefined,state.controller.signal);if(generation!==state.generation)return;state.subscription=data;renderSubscription();}
  function renderSubscription(){const sub=state.subscription?.subscription;const capability=state.context.commercial_entitled;content.innerHTML=heading('Suscripción','Estado comercial de esta organización. Esta sección es de consulta.')+`<div class="grid"><div class="metric"><span class="label">Acceso comercial</span><strong>${capability?'Activo':'Sin acceso activo'}</strong><small>Configuración de sucursales y servicios</small></div>${sub?`<div class="metric"><span class="label">Plan</span><strong>Plan de proveedor</strong><small>${label(sub.billing_period)}</small></div><div class="metric"><span class="label">Estado del plan</span><strong>${label(sub.status)}</strong><small>Desde ${fmtDate(sub.starts_at)}${sub.expires_at?` · Hasta ${fmtDate(sub.expires_at)}`:''}</small></div>`:''}</div>${!capability?`<div class="attention"><p>${gateText}</p><p>La activación comercial no está disponible desde este portal.</p></div>`:''}`;}
  bootstrap();
})();
