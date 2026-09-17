/* FISC-UX01: fiscal issuer and CSD settings, using the existing FISC02B authority. */
(()=>{
  'use strict';
  const pane=document.getElementById('cfdi-perfil-fiscal');
  if(!pane)return;
  const esc=value=>String(value??'').replace(/[&<>"']/g,ch=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
  const api='api/billing/issuance.php';
  pane.innerHTML=`<section class="mx-fiscal-profile" aria-label="Perfil fiscal">
    <div class="mx-fiscal-head"><div><h3>PERFIL FISCAL</h3><p>Configura los datos con los que emitirás tus facturas.</p></div><button id="mx-fiscal-add" class="btn mx-billing-btn-primary" type="button">+ Agregar emisor</button></div>
    <p id="mx-fiscal-feedback" role="status" aria-live="polite"></p>
    <div id="mx-fiscal-list" class="mx-fiscal-list"></div>
    <form id="mx-fiscal-editor" class="mx-fiscal-editor" hidden><h4 id="mx-fiscal-editor-title">DATOS DEL EMISOR</h4><div class="mx-fiscal-fields">
      <div class="mx-fiscal-field"><span class="mx-fiscal-label"><label for="mx-fiscal-alias-input">Alias</label> <button class="mx-billing-help-button" type="button" aria-label="Ayuda sobre el alias" aria-expanded="false" aria-controls="mx-fiscal-alias-help" data-billing-help>ⓘ</button></span><div id="mx-fiscal-alias-help" class="mx-billing-help" hidden>Es un nombre interno para que identifiques fácilmente este emisor. No aparecerá en la factura.<br>Ejemplos: Personal, Consultorio, Clínica, Sociedad.</div><input id="mx-fiscal-alias-input" name="alias" class="form-control" maxlength="80" placeholder="Ej. Personal" required></div>
      <label>Nombre o razón social fiscal<input name="issuer_legal_name" class="form-control" maxlength="254" required></label>
      <label>RFC<input name="rfc" class="form-control" maxlength="13" required autocapitalize="characters"></label>
      <label>Régimen fiscal<select name="fiscal_regime_code" class="form-select" required></select></label>
      <label>Código postal de expedición<input name="expedition_postal_code" class="form-control" inputmode="numeric" pattern="[0-9]{5}" maxlength="5" required></label>
      <label class="mx-fiscal-check"><input name="is_default" type="checkbox"> Predeterminado</label>
    </div><div class="mx-fiscal-actions"><button id="mx-fiscal-save-issuer" class="btn mx-billing-btn-primary" type="submit">Guardar y continuar</button><button id="mx-fiscal-cancel-editor" class="btn mx-billing-btn-secondary" type="button">Cancelar</button></div></form>
    <section id="mx-fiscal-csd-panel" class="mx-fiscal-editor" hidden><div class="mx-fiscal-card-head"><h4 id="mx-fiscal-csd-title" tabindex="-1">CERTIFICADO DE SELLO DIGITAL</h4><button id="mx-fiscal-close-csd" class="btn mx-billing-btn-secondary" type="button">Cerrar</button></div><p class="mx-fiscal-help">Necesitas un Certificado de Sello Digital vigente para emitir facturas.</p><p id="mx-fiscal-csd-status" class="mx-fiscal-help"></p>
      <form id="mx-fiscal-csd-form"><div class="mx-fiscal-fields"><label>Archivo .cer<input name="certificate" class="form-control" type="file" accept=".cer" required></label><label>Archivo .key<input name="private_key" class="form-control" type="file" accept=".key" required></label><label>Contraseña de la llave<input name="password" class="form-control" type="password" autocomplete="new-password" required></label></div><div class="mx-fiscal-actions"><button class="btn mx-billing-btn-primary" type="submit">Guardar certificado</button><button id="mx-fiscal-skip-csd" class="btn mx-billing-btn-secondary" type="button">Continuar a preferencias</button></div></form><p id="mx-fiscal-csd-availability" class="mx-fiscal-help"></p>
    </section>
    <form id="mx-fiscal-preferences" class="mx-fiscal-editor" hidden><div class="mx-fiscal-card-head"><h4 id="mx-fiscal-preferences-title" tabindex="-1">PREFERENCIAS DE FACTURACIÓN</h4><button id="mx-fiscal-close-preferences" class="btn mx-billing-btn-secondary" type="button">Cerrar</button></div><div class="mx-fiscal-fields"><label class="mx-fiscal-wide">Descripción predeterminada<input name="default_concept_description" class="form-control" maxlength="1000" required><span class="mx-fiscal-help">Se usará como propuesta al crear una factura. Podrás modificarla en cada factura.</span></label><label>Precio habitual (opcional)<input name="habitual_unit_price" class="form-control" inputmode="decimal" pattern="[0-9]+(\\.[0-9]{1,6})?" placeholder="Ej. 1200.00"></label></div><fieldset class="mx-fiscal-logo-choice"><legend>PRESENTACIÓN DE FACTURA</legend><label><input name="invoice_logo_mode" type="radio" value="PROFESSIONAL_LOGO"> Usar mi logotipo profesional</label><label><input name="invoice_logo_mode" type="radio" value="NONE" checked> Sin logotipo</label><p id="mx-fiscal-logo-note" class="mx-fiscal-help"></p></fieldset><div class="mx-fiscal-actions"><button class="btn mx-billing-btn-primary" type="submit">Guardar preferencias</button></div></form>
  </section>`;
  const $=selector=>pane.querySelector(selector);
  const els={list:$('#mx-fiscal-list'),feedback:$('#mx-fiscal-feedback'),editor:$('#mx-fiscal-editor'),editorTitle:$('#mx-fiscal-editor-title'),csdPanel:$('#mx-fiscal-csd-panel'),csdTitle:$('#mx-fiscal-csd-title'),csdStatus:$('#mx-fiscal-csd-status'),csdForm:$('#mx-fiscal-csd-form'),csdAvailability:$('#mx-fiscal-csd-availability'),preferences:$('#mx-fiscal-preferences')};
  let csrf='',issuers=[],regimes=[],issuerPreferences={},suggestion='Servicios profesionales',logoAvailable=false,editingId='',csdIssuerId='',preferencesIssuerId='',csdAvailable=false,loadVersion=0;
  const message=value=>{els.feedback.textContent=value||''};
  async function request(method,action,body={}){
    const response=await fetch(api+(method==='GET'?`?${new URLSearchParams({action,...body})}`:''),{method,credentials:'same-origin',cache:'no-store',headers:method==='GET'?{}:{'Content-Type':'application/json','X-Billing-Issuance-CSRF':csrf},body:method==='GET'?undefined:JSON.stringify({action,...body})});
    const result=await response.json().catch(()=>({ok:false,error:'profile_unavailable'}));
    if(!response.ok||!result.ok)throw new Error(result.error||'profile_unavailable');
    if(result.data?.csrf_token)csrf=result.data.csrf_token;
    return result.data||{};
  }
  function certificateStatus(credentials){
    const now=Date.now();
    const time=value=>Date.parse(String(value||'').replace(' ','T')+'Z');
    const withinValidity=row=>time(row.valid_from)<=now&&time(row.valid_to)>now;
    const valid=credentials.find(row=>row.certificate_type==='CSD_VERIFIED'&&withinValidity(row));
    const current=valid||credentials.find(row=>time(row.valid_to)>now)||credentials[0];
    if(!current)return {label:'No configurado',expires:''};
    const expiry=time(current.valid_to);
    const label=!Number.isFinite(expiry)?'Estado no disponible':expiry<=now?'Vencido':current.certificate_type==='CSD_VERIFIED'?(withinValidity(current)?'Activo':'Aún no vigente'):'Registrado · pendiente de validación';
    const expires=Number.isFinite(expiry)?`Vence: ${new Intl.DateTimeFormat('es-MX',{timeZone:'UTC',day:'2-digit',month:'2-digit',year:'numeric'}).format(new Date(expiry))}`:'';
    return {label,expires};
  }
  async function loadCsdStatus(id,version){
    const target=[...els.list.querySelectorAll('[data-issuer-card]')].find(card=>card.dataset.issuerId===id);
    if(!target)return;
    try{
      const data=await request('GET','issuer_csd',{issuer_id:id});
      if(version!==loadVersion||!target.isConnected)return;
      const status=certificateStatus(data.credentials);
      target.querySelector('[data-csd-status]').textContent=status.label;
      target.querySelector('[data-csd-expiry]').textContent=status.expires;
      target.querySelector('[data-issuer-action="certificate"]').textContent=data.credentials.length?'Actualizar certificado':'Configurar certificado';
      if(csdIssuerId===id)els.csdStatus.textContent=`Estado: ${status.label}${status.expires?' · '+status.expires:''}`;
    }catch(error){if(version===loadVersion&&target.isConnected)target.querySelector('[data-csd-status]').textContent='Estado no disponible';}
  }
  function render(){
    const version=++loadVersion;
    $('#mx-fiscal-add').hidden=!issuers.length;
    if(!issuers.length){els.list.innerHTML='<div class="mx-fiscal-empty"><h4>Aún no has configurado un perfil fiscal.</h4><p>Agrega los datos del emisor que utilizarás para facturar.</p><button class="btn mx-billing-btn-primary" type="button" data-empty-add>Configurar perfil fiscal</button></div>';return;}
    const names=new Map(regimes.map(row=>[row.code,row.label]));
    els.list.innerHTML=issuers.map(row=>`<article class="mx-fiscal-card" data-issuer-card data-issuer-id="${esc(row.issuer_profile_id)}"><div class="mx-fiscal-card-head"><div><span class="mx-fiscal-alias">${esc(row.alias)}</span><h4>${esc(row.issuer_legal_name)}</h4></div>${Number(row.is_default)===1?'<span class="mx-fiscal-default">Predeterminado</span>':''}</div><dl><div><dt>RFC</dt><dd>${esc(row.rfc)}</dd></div><div><dt>Régimen fiscal</dt><dd>${esc(row.fiscal_regime_code)}${names.has(row.fiscal_regime_code)?' · '+esc(names.get(row.fiscal_regime_code)):''}</dd></div><div><dt>Código postal de expedición</dt><dd>${esc(row.expedition_postal_code)}</dd></div><div><dt>Certificado de Sello Digital</dt><dd><span data-csd-status>Consultando certificado…</span><span class="mx-fiscal-expiry" data-csd-expiry></span></dd></div></dl><div class="mx-fiscal-actions"><button class="btn mx-billing-btn-secondary" type="button" data-issuer-action="edit">Editar</button>${Number(row.is_default)===1?'':'<button class="btn mx-billing-btn-secondary" type="button" data-issuer-action="default">Hacer predeterminado</button>'}<button class="btn mx-billing-btn-secondary" type="button" data-issuer-action="certificate">Configurar certificado</button><button class="btn mx-billing-btn-secondary" type="button" data-issuer-action="preferences">Preferencias</button><button class="btn mx-billing-btn-danger" type="button" data-issuer-action="archive">Archivar</button></div></article>`).join('');
    issuers.forEach(row=>loadCsdStatus(row.issuer_profile_id,version));
  }
  async function refresh(announce=false){
    const data=await request('GET','bootstrap');issuers=data.issuers;regimes=data.fiscal.regimes;issuerPreferences=data.issuer_preferences||{};suggestion=data.default_concept_suggestion||'Servicios profesionales';logoAvailable=data.professional_logo_available===true;csdAvailable=data.csd_registration_available===true;
    const select=els.editor.elements.namedItem('fiscal_regime_code');select.innerHTML='<option value="">Selecciona régimen</option>'+regimes.map(row=>`<option value="${esc(row.code)}">${esc(row.label||row.code)}</option>`).join('');
    render();if(announce)document.dispatchEvent(new Event('mxmed:billing-issuers-updated'));
  }
  function openEditor(id=''){
    const issuer=issuers.find(row=>row.issuer_profile_id===id);editingId=issuer?.issuer_profile_id||'';els.csdPanel.hidden=true;els.preferences.hidden=true;els.editor.reset();
    els.editorTitle.textContent='DATOS DEL EMISOR';$('#mx-fiscal-save-issuer').textContent=issuer?'Guardar cambios':'Guardar y continuar';
    if(issuer){for(const key of ['alias','issuer_legal_name','rfc','fiscal_regime_code','expedition_postal_code'])els.editor.elements.namedItem(key).value=issuer[key];els.editor.elements.namedItem('is_default').checked=Number(issuer.is_default)===1;}
    els.editor.hidden=false;els.editor.elements.namedItem('alias').focus();
  }
  function openCsd(id){
    const issuer=issuers.find(row=>row.issuer_profile_id===id);if(!issuer)return;
    csdIssuerId=id;els.editor.hidden=true;els.preferences.hidden=true;els.csdForm.reset();els.csdTitle.textContent='CERTIFICADO DE SELLO DIGITAL';
    els.csdStatus.textContent='Consultando certificado…';els.csdPanel.hidden=false;
    els.csdForm.querySelector('button[type="submit"]').disabled=!csdAvailable;
    els.csdAvailability.textContent=csdAvailable?'El certificado registrado requiere validación antes de emitir facturas.':'La configuración de certificados no está disponible temporalmente.';
    loadCsdStatus(id,loadVersion);els.csdPanel.scrollIntoView({block:'nearest'});els.csdTitle.focus();
  }
  function openPreferences(id){
    if(!issuers.some(row=>row.issuer_profile_id===id))return;
    preferencesIssuerId=id;els.editor.hidden=true;els.csdPanel.hidden=true;els.preferences.reset();
    const saved=issuerPreferences[id];els.preferences.elements.namedItem('default_concept_description').value=saved?.default_concept_description||suggestion;
    els.preferences.elements.namedItem('habitual_unit_price').value=saved?.habitual_unit_price??'';
    els.preferences.querySelector(`[name="invoice_logo_mode"][value="${saved?.invoice_logo_mode==='PROFESSIONAL_LOGO'?'PROFESSIONAL_LOGO':'NONE'}"]`).checked=true;
    els.preferences.querySelector('[value="PROFESSIONAL_LOGO"]').disabled=!logoAvailable;
    $('#mx-fiscal-logo-note').innerHTML=logoAvailable?'Se usará el logotipo profesional aprobado que esté vigente al representar la factura.':'No tienes un logotipo profesional disponible. <button type="button" class="mx-fiscal-link" data-go-logo>Administrar logotipo en Información personal</button>';
    els.preferences.hidden=false;els.preferences.scrollIntoView({block:'nearest'});$('#mx-fiscal-preferences-title').focus();
  }
  $('#mx-fiscal-add').addEventListener('click',()=>openEditor());
  els.list.addEventListener('click',async event=>{
    if(event.target.closest('[data-empty-add]')){openEditor();return;}
    const button=event.target.closest('[data-issuer-action]');const id=button?.closest('[data-issuer-card]')?.dataset.issuerId;if(!id)return;
    const action=button.dataset.issuerAction;
    if(action==='edit'){openEditor(id);return;}
    if(action==='certificate'){openCsd(id);return;}
    if(action==='preferences'){openPreferences(id);return;}
    if(action==='archive'&&!confirm('¿Archivar este emisor? Los borradores existentes conservarán su referencia.'))return;
    try{await request(action==='archive'?'DELETE':'POST',action==='archive'?'archive_issuer':'default_issuer',{issuer_id:id});await refresh(true);els.editor.hidden=true;els.csdPanel.hidden=true;message(action==='archive'?'Emisor archivado.':'Emisor predeterminado actualizado.')}catch(error){message(error.message)}
  });
  $('#mx-fiscal-cancel-editor').addEventListener('click',()=>{els.editor.hidden=true});
  $('#mx-fiscal-close-csd').addEventListener('click',()=>{els.csdPanel.hidden=true;csdIssuerId=''});
  $('#mx-fiscal-skip-csd').addEventListener('click',()=>openPreferences(csdIssuerId));
  $('#mx-fiscal-close-preferences').addEventListener('click',()=>{els.preferences.hidden=true;preferencesIssuerId=''});
  els.preferences.addEventListener('click',event=>{if(event.target.closest('[data-go-logo]')){document.querySelector('[data-panel="p-info"]')?.click();document.querySelector('#t-info-datos-tab')?.click();}});
  els.preferences.addEventListener('submit',async event=>{event.preventDefault();if(!preferencesIssuerId||!els.preferences.reportValidity())return;const form=new FormData(els.preferences);try{await request('PUT','save_issuer_preferences',{issuer_id:preferencesIssuerId,preferences:{default_concept_description:String(form.get('default_concept_description')||'').trim(),habitual_unit_price:String(form.get('habitual_unit_price')||'').trim()||null,invoice_logo_mode:String(form.get('invoice_logo_mode')||'NONE')}});await refresh(true);message('Preferencias guardadas para este emisor.');els.preferences.hidden=true;}catch(error){message(error.message)}});
  els.editor.addEventListener('submit',async event=>{
    event.preventDefault();if(!els.editor.reportValidity())return;
    const form=new FormData(els.editor);const profile=Object.fromEntries(['alias','issuer_legal_name','rfc','fiscal_regime_code','expedition_postal_code'].map(key=>[key,String(form.get(key)||'').trim()]));profile.is_default=form.has('is_default');
    try{const wasEditing=Boolean(editingId);const saved=await request(wasEditing?'PUT':'POST',wasEditing?'update_issuer':'create_issuer',{...(wasEditing?{issuer_id:editingId}:{}),profile});await refresh(true);els.editor.hidden=true;message(wasEditing?'Emisor guardado.':'Emisor guardado. Continúa con el certificado.');if(!wasEditing)openCsd(saved.issuer.issuer_profile_id);}catch(error){message(error.message)}
  });
  els.csdForm.addEventListener('submit',async event=>{
    event.preventDefault();if(!csdIssuerId||!csdAvailable||!els.csdForm.reportValidity())return;
    const form=new FormData(els.csdForm);form.set('action','register_csd');form.set('issuer_id',csdIssuerId);
    try{const selected=csdIssuerId;const response=await fetch(api,{method:'POST',credentials:'same-origin',cache:'no-store',headers:{'X-Billing-Issuance-CSRF':csrf},body:form});const result=await response.json();if(!response.ok||!result.ok)throw new Error(result.error||'certificate_registration_failed');els.csdForm.reset();await refresh();message('Certificado registrado · pendiente de validación.');openPreferences(selected);}
    catch(error){message(error.message)}
  });
  document.querySelector('[data-bs-target="#cfdi-perfil-fiscal"]')?.addEventListener('shown.bs.tab',()=>refresh().catch(()=>message('No se pudo cargar el perfil fiscal.')));
  const closeHelp=()=>document.querySelectorAll('.mx-billing-help-button').forEach(button=>{button.setAttribute('aria-expanded','false');document.getElementById(button.getAttribute('aria-controls'))?.setAttribute('hidden','');});
  document.addEventListener('focusin',event=>{const button=event.target.closest?.('.mx-billing-help-button');if(button){closeHelp();button.setAttribute('aria-expanded','true');document.getElementById(button.getAttribute('aria-controls'))?.removeAttribute('hidden');}});
  document.addEventListener('click',event=>{const button=event.target.closest?.('.mx-billing-help-button');if(button){closeHelp();button.setAttribute('aria-expanded','true');document.getElementById(button.getAttribute('aria-controls'))?.removeAttribute('hidden');return;}if(!event.target.closest?.('.mx-billing-help'))closeHelp();});
  document.addEventListener('keydown',event=>{if(event.key==='Escape')closeHelp();});
})();
