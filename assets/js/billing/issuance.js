/* FISC02B: provider-neutral CFDI draft composer. No browser-side fiscal authority. */
(()=>{
  'use strict';
  const pane=document.getElementById('cfdi-crear');
  const patientPane=document.getElementById('cfdi-pacientes');
  if(!pane||!patientPane)return;
  const esc=value=>String(value??'').replace(/[&<>"']/g,ch=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
  const option=(value,label)=>`<option value="${esc(value)}">${esc(label)}</option>`;
  pane.innerHTML=`<section class="mx-issuance" aria-label="Crear factura CFDI">
    <div class="mx-issuance-head"><div><h3>CREAR FACTURA</h3><p>Prepara y revisa un borrador CFDI 4.0 antes de emitirlo.</p></div><span class="mx-issuance-gate">Timbrado pendiente de PAC autorizado</span></div>
    <label class="mx-issuance-resume">Borradores guardados<select id="mx-issuance-saved-drafts" class="form-select"><option value="">Crear nuevo borrador</option></select></label>
    <p id="mx-issuance-feedback" role="status" aria-live="polite"></p>
    <div class="mx-issuance-columns">
      <section class="mx-issuance-card"><h4>Paciente y receptor</h4><p id="mx-issuance-patient-name">Ningún paciente seleccionado.</p><button id="mx-issuance-pick-patient" class="btn mx-billing-btn-secondary" type="button">Buscar paciente en mi archivo</button><label for="mx-issuance-receiver">Datos de facturación del receptor</label><select id="mx-issuance-receiver" class="form-select"><option value="">Selecciona un paciente</option></select><p id="mx-issuance-receiver-help" class="mx-issuance-help">Los datos fiscales se administran en Pacientes → Datos de facturación.</p></section>
      <section class="mx-issuance-card"><div class="mx-issuance-card-head"><h4>Emisor fiscal</h4><button id="mx-issuance-toggle-issuer" class="btn mx-billing-btn-secondary" type="button" aria-expanded="false">+ Agregar emisor</button></div><label for="mx-issuance-issuer">Perfil emisor</label><select id="mx-issuance-issuer" class="form-select"><option value="">Sin emisor configurado</option></select><p class="mx-issuance-help">El emisor puede ser distinto del nombre público del médico.</p><div id="mx-issuance-issuer-actions" class="mx-issuance-actions"></div>
        <form id="mx-issuance-issuer-editor" hidden><div class="mx-issuance-fields"><label>Alias<input name="alias" class="form-control" maxlength="80" required></label><label>Nombre o razón social fiscal<input name="issuer_legal_name" class="form-control" maxlength="254" required></label><label>RFC<input name="rfc" class="form-control" maxlength="13" required></label><label>Régimen fiscal<select name="fiscal_regime_code" class="form-select" required></select></label><label>Código postal de expedición<input name="expedition_postal_code" class="form-control" inputmode="numeric" pattern="[0-9]{5}" maxlength="5" required></label><label class="mx-issuance-check"><input name="is_default" type="checkbox"> Predeterminado</label></div><div class="mx-issuance-actions"><button class="btn mx-billing-btn-primary" type="submit">Guardar emisor</button><button id="mx-issuance-cancel-issuer" class="btn mx-billing-btn-secondary" type="button">Cancelar</button></div></form>
        <div id="mx-issuance-csd-area" hidden><h5>Certificado de Sello Digital</h5><p id="mx-issuance-csd-status" class="mx-issuance-help"></p><form id="mx-issuance-csd-form" class="mx-issuance-fields"><label>Archivo .cer<input name="certificate" class="form-control" type="file" accept=".cer" required></label><label>Archivo .key<input name="private_key" class="form-control" type="file" accept=".key" required></label><label>Contraseña de llave<input name="password" class="form-control" type="password" autocomplete="new-password" required></label><button class="btn mx-billing-btn-secondary" type="submit">Registrar CSD privado</button></form><p class="mx-issuance-help">El registro técnico no confirma por sí solo que el certificado sea un CSD autorizado para timbrar.</p></div>
      </section>
    </div>
    <form id="mx-issuance-draft-form">
      <section class="mx-issuance-card"><h4>Datos CFDI</h4><div class="mx-issuance-fields"><label>Uso CFDI<select id="mx-issuance-use" class="form-select" required></select></label><label>Método de pago<select id="mx-issuance-method" class="form-select" required></select></label><label>Forma de pago<select id="mx-issuance-form" class="form-select" required></select></label><label>Moneda<select id="mx-issuance-currency" class="form-select" required></select></label><label>Serie (opcional)<input id="mx-issuance-series" class="form-control" maxlength="25"></label><label>Folio interno (opcional)<input id="mx-issuance-folio" class="form-control" maxlength="40"></label></div></section>
      <section class="mx-issuance-card"><div class="mx-issuance-card-head"><h4>Conceptos</h4><button id="mx-issuance-add-item" class="btn mx-billing-btn-secondary" type="button">+ Agregar concepto</button></div><div id="mx-issuance-items"></div></section>
      <section class="mx-issuance-card"><h4>Totales calculados por el servidor</h4><div id="mx-issuance-totals" class="mx-issuance-totals">Guarda el borrador para calcular los importes definitivos.</div><div class="mx-issuance-actions"><button class="btn mx-billing-btn-primary" type="submit">Guardar borrador y revisar</button><button id="mx-issuance-new-draft" class="btn mx-billing-btn-secondary" type="button">Nuevo borrador</button></div></section>
    </form>
    <section id="mx-issuance-preview" class="mx-issuance-card" hidden><h4>Vista previa antes de certificar</h4><div id="mx-issuance-preview-body"></div><label class="mx-issuance-check"><input id="mx-issuance-confirm" type="checkbox"> Confirmo que revisé emisor, receptor, conceptos, importes y datos CFDI.</label><div class="mx-issuance-actions"><button id="mx-issuance-stamp" class="btn mx-billing-btn-primary" type="button" disabled>Timbrar CFDI</button><span>Disponible sólo después de seleccionar y validar un PAC autorizado.</span></div></section>
  </section>`;
  const $=selector=>pane.querySelector(selector);
  const els={feedback:$('#mx-issuance-feedback'),patientName:$('#mx-issuance-patient-name'),receiver:$('#mx-issuance-receiver'),receiverHelp:$('#mx-issuance-receiver-help'),issuer:$('#mx-issuance-issuer'),issuerActions:$('#mx-issuance-issuer-actions'),issuerEditor:$('#mx-issuance-issuer-editor'),issuerToggle:$('#mx-issuance-toggle-issuer'),csdArea:$('#mx-issuance-csd-area'),csdStatus:$('#mx-issuance-csd-status'),csdForm:$('#mx-issuance-csd-form'),draftForm:$('#mx-issuance-draft-form'),items:$('#mx-issuance-items'),totals:$('#mx-issuance-totals'),preview:$('#mx-issuance-preview'),previewBody:$('#mx-issuance-preview-body')};
  const api='api/billing/issuance.php';
  let csrf='',sat=null,fiscal=null,issuers=[],savedDrafts=[],patient=null,profiles=[],draft=null,editingIssuer='',csdRegistrationAvailable=false;
  const message=text=>{els.feedback.textContent=text||''};
  const validationText=code=>({active_verified_csd_required:'Falta verificar un CSD vigente para el emisor.',tax_rate_catalog_unverified:'La tasa o cuota requiere cotejo con el catálogo fiscal vigente antes de timbrar.',sat_catalog_effective_dates_unverified:'Falta cotejar la vigencia de las claves SAT de los conceptos.',tax_object_rule_unverified:'La regla de este objeto de impuesto requiere cotejo fiscal.',currency_precision_catalog_unverified:'La precisión de esta moneda requiere cotejo fiscal antes de timbrar.',issuer_profile_not_found:'Selecciona un emisor activo.',billing_profile_not_found:'Selecciona datos de facturación vigentes.',patient_scope_denied:'El paciente ya no pertenece a tu archivo.',payment_method_form_conflict:'Método y forma de pago incompatibles.',invoice_items_required:'Agrega al menos un concepto.'})[code]||code;
  async function request(method,action,body=null){
    const query=method==='GET'?`?${new URLSearchParams({action,...(body||{})})}`:'';
    const response=await fetch(api+query,{method,credentials:'same-origin',cache:'no-store',headers:{...(method!=='GET'?{'X-Billing-Issuance-CSRF':csrf,'Content-Type':'application/json'}:{})},body:method==='GET'?undefined:JSON.stringify({action,...body})});
    const result=await response.json().catch(()=>({ok:false,error:'billing_issuance_unavailable'}));
    if(!response.ok||!result.ok)throw new Error(result.error||'billing_issuance_unavailable');
    if(result.data?.csrf_token)csrf=result.data.csrf_token;
    return result.data||{};
  }
  function showPatientPane(){document.querySelector('[data-bs-target="#cfdi-pacientes"]')?.click();patientPane.querySelector('#mx-billing-patient-query')?.focus()}
  function renderReceiver(){
    els.patientName.textContent=patient?.display_name||'Ningún paciente seleccionado.';
    els.receiver.innerHTML='<option value="">Selecciona datos de facturación</option>'+profiles.map(row=>option(row.billing_profile_id,`${row.alias} · ${row.receiver_legal_name} · ${row.rfc}`)).join('');
    const preferred=profiles.find(row=>Number(row.is_default)===1)||profiles[0];if(preferred)els.receiver.value=preferred.billing_profile_id;
    els.receiverHelp.textContent=profiles.length?'Selecciona los datos fiscales que se incluirán en esta factura.':'Este paciente aún no tiene datos de facturación. Agrégalos en Pacientes → Datos de facturación.';
  }
  function renderIssuers(selected=''){
    els.issuer.innerHTML='<option value="">Selecciona un emisor</option>'+issuers.map(row=>option(row.issuer_profile_id,`${row.alias} · ${row.issuer_legal_name} · ${row.rfc}`)).join('');
    els.issuer.value=selected||issuers.find(row=>Number(row.is_default)===1)?.issuer_profile_id||issuers[0]?.issuer_profile_id||'';
    const current=issuers.find(row=>row.issuer_profile_id===els.issuer.value);
    els.issuerActions.innerHTML=current?`<button type="button" data-issuer-action="edit" class="btn mx-billing-btn-secondary">Editar</button>${Number(current.is_default)===1?'':`<button type="button" data-issuer-action="default" class="btn mx-billing-btn-secondary">Hacer predeterminado</button>`}<button type="button" data-issuer-action="archive" class="btn mx-billing-btn-danger">Archivar</button>`:'';
    els.csdArea.hidden=!current;
    if(current){els.csdForm.hidden=!csdRegistrationAvailable;loadCsd(current.issuer_profile_id);}
  }
  async function loadCsd(id){
    try{const result=await request('GET','issuer_csd',{issuer_id:id});if(els.issuer.value!==id)return;
      els.csdStatus.textContent=(result.credentials.length?result.credentials.map(c=>`Serie ${c.certificate_serial} · ${c.certificate_type} · vence ${c.valid_to}`).join(' | '):'No hay CSD registrado para este emisor.')+(csdRegistrationAvailable?'':' El registro requiere configurar la clave de cifrado privada del servidor.');
    }catch(error){els.csdStatus.textContent='No se pudo consultar el CSD.'}
  }
  function setOptions(select,rows,placeholder){select.innerHTML=option('',placeholder)+rows.map(row=>option(row.code,row.label||row.code)).join('')}
  function renderCatalog(){
    setOptions($('#mx-issuance-use'),fiscal.uses,'Selecciona uso CFDI');
    setOptions($('#mx-issuance-method'),sat.payment_methods.map(code=>({code})),'Selecciona método');
    setOptions($('#mx-issuance-form'),sat.payment_forms.map(code=>({code})),'Selecciona forma');
    setOptions($('#mx-issuance-currency'),sat.currencies.map(code=>({code})),'Selecciona moneda');
    $('#mx-issuance-currency').value='MXN';
    $('#mx-issuance-method').value='PUE';
    const regime=els.issuerEditor.elements.namedItem('fiscal_regime_code');setOptions(regime,fiscal.regimes,'Selecciona régimen');
  }
  function itemMarkup(item={}){
    return `<article class="mx-issuance-item"><div class="mx-issuance-card-head"><h5>Concepto</h5><button class="btn mx-billing-btn-danger mx-issuance-remove-item" type="button">Quitar</button></div><div class="mx-issuance-fields">
      <label>Clave SAT producto/servicio<input name="product_service_code" class="form-control" value="${esc(item.product_service_code||'')}" inputmode="numeric" pattern="[0-9]{8}" maxlength="8" required></label>
      <label class="mx-issuance-wide">Descripción<input name="description" class="form-control" value="${esc(item.description||'')}" maxlength="1000" required></label>
      <label>Cantidad<input name="quantity" class="form-control" value="${esc(item.quantity||'1')}" inputmode="decimal" pattern="[0-9]+(\.[0-9]{1,6})?" required></label>
      <label>Clave de unidad SAT<input name="unit_code" class="form-control" value="${esc(item.unit_code||'')}" maxlength="3" required></label>
      <label>Valor unitario<input name="unit_value" class="form-control" value="${esc(item.unit_value||'')}" inputmode="decimal" pattern="[0-9]+(\.[0-9]{1,6})?" required></label>
      <label>Descuento<input name="discount" class="form-control" value="${esc(item.discount||'0')}" inputmode="decimal" pattern="[0-9]+(\.[0-9]{1,6})?"></label>
      <label>Objeto de impuesto<select name="tax_object_code" class="form-select">${sat.tax_objects.map(code=>option(code,code)).join('')}</select></label>
      </div><div class="mx-issuance-taxes"><div class="mx-issuance-card-head"><h6>Impuestos del concepto</h6><button class="btn mx-billing-btn-secondary mx-issuance-add-tax" type="button">+ Agregar impuesto</button></div><div class="mx-issuance-tax-list"></div></div></article>`;
  }
  function taxMarkup(tax={}){return `<div class="mx-issuance-tax"><label>Tipo<select name="direction" class="form-select">${option('TRANSFER','Traslado')}${option('WITHHOLD','Retención')}</select></label><label>Impuesto<select name="tax_code" class="form-select">${option('001','001 · ISR')}${option('002','002 · IVA')}${option('003','003 · IEPS')}</select></label><label>Factor<select name="factor_code" class="form-select">${option('Exento','Exento')}${option('Tasa','Tasa')}${option('Cuota','Cuota')}</select></label><label>Tasa/cuota<input name="rate" class="form-control" value="${esc(tax.rate||'')}" inputmode="decimal" placeholder="0.160000"></label><button type="button" class="btn mx-billing-btn-danger mx-issuance-remove-tax">Quitar</button></div>`}
  function addItem(item={}){
    els.items.insertAdjacentHTML('beforeend',itemMarkup(item));const card=els.items.lastElementChild;
    card.querySelector('[name="tax_object_code"]').value=item.tax_object_code||'01';
    for(const tax of item.taxes||[]){card.querySelector('.mx-issuance-tax-list').insertAdjacentHTML('beforeend',taxMarkup(tax));const row=card.querySelector('.mx-issuance-tax-list').lastElementChild;for(const key of ['direction','tax_code','factor_code'])row.querySelector(`[name="${key}"]`).value=tax[key];}
  }
  function readDraft(){
    if(!patient)throw new Error('Selecciona un paciente.');
    if(!els.receiver.value)throw new Error('Selecciona datos de facturación del receptor.');
    if(!els.issuer.value)throw new Error('Configura y selecciona un emisor fiscal.');
    const items=[...els.items.children].map(card=>({product_service_code:card.querySelector('[name="product_service_code"]').value.trim(),description:card.querySelector('[name="description"]').value.trim(),quantity:card.querySelector('[name="quantity"]').value.trim(),unit_code:card.querySelector('[name="unit_code"]').value.trim(),unit_value:card.querySelector('[name="unit_value"]').value.trim(),discount:card.querySelector('[name="discount"]').value.trim()||'0',tax_object_code:card.querySelector('[name="tax_object_code"]').value,taxes:[...card.querySelectorAll('.mx-issuance-tax')].map(row=>({direction:row.querySelector('[name="direction"]').value,tax_code:row.querySelector('[name="tax_code"]').value,factor_code:row.querySelector('[name="factor_code"]').value,rate:row.querySelector('[name="factor_code"]').value==='Exento'?null:row.querySelector('[name="rate"]').value.trim()}))}));
    return {patient_id:patient.patient_id,billing_profile_id:els.receiver.value,issuer_profile_id:els.issuer.value,currency_code:$('#mx-issuance-currency').value,cfdi_use_code:$('#mx-issuance-use').value,payment_method_code:$('#mx-issuance-method').value,payment_form_code:$('#mx-issuance-form').value,series:$('#mx-issuance-series').value.trim(),internal_folio:$('#mx-issuance-folio').value.trim(),items,...(draft?{revision:Number(draft.revision)}:{})};
  }
  function totalsView(row){els.totals.innerHTML=`<span>Subtotal: <strong>${esc(row.subtotal)} ${esc(row.currency_code)}</strong></span><span>Descuento: <strong>${esc(row.discount)}</strong></span><span>Impuestos netos: <strong>${esc(row.tax_total)}</strong></span><span>Total: <strong>${esc(row.total)}</strong></span>`}
  function renderSavedDrafts(selected=''){$('#mx-issuance-saved-drafts').innerHTML='<option value="">Crear nuevo borrador</option>'+savedDrafts.map(row=>option(row.draft_id,`${row.patient_display_name} · ${row.state} · ${row.total} · ${row.updated_at}`)).join('');$('#mx-issuance-saved-drafts').value=selected}
  async function loadDraft(id){
    const info=savedDrafts.find(row=>row.draft_id===id);if(!info)return;
    const loaded=await request('GET','draft',{draft_id:id});draft=loaded.draft;
    patient={patient_id:draft.patient_id,display_name:info.patient_display_name};
    const response=await fetch(`api/billing/patient-profiles.php?${new URLSearchParams({patient_id:draft.patient_id})}`,{credentials:'same-origin',cache:'no-store'});
    const profileData=await response.json();profiles=profileData?.ok?profileData.data.profiles:[];renderReceiver();
    els.receiver.value=draft.billing_profile_id;renderIssuers(draft.issuer_profile_id);
    for(const [selector,value] of [['#mx-issuance-use',draft.cfdi_use_code],['#mx-issuance-method',draft.payment_method_code],['#mx-issuance-form',draft.payment_form_code],['#mx-issuance-currency',draft.currency_code],['#mx-issuance-series',draft.series||''],['#mx-issuance-folio',draft.internal_folio||'']])$(selector).value=value;
    els.items.innerHTML='';draft.items.forEach(addItem);totalsView(draft);await preview();
  }
  async function preview(){if(!draft)return;const data=await request('GET','preview',{draft_id:draft.draft_id});
    const d=data.draft,issuer=data.issuer,receiver=data.receiver;
    const errors=data.validation.errors.map(row=>`<li>${esc(validationText(row.code))}</li>`).join('');
    els.previewBody.innerHTML=`<dl><dt>Emisor</dt><dd>${esc(issuer?.issuer_legal_name||'No disponible')} · ${esc(issuer?.rfc||'')}</dd><dt>Receptor</dt><dd>${esc(receiver?.receiver_legal_name||'No disponible')} · ${esc(receiver?.rfc||'')}</dd><dt>Datos CFDI</dt><dd>Uso ${esc(d.cfdi_use_code)} · Método ${esc(d.payment_method_code)} · Forma ${esc(d.payment_form_code)} · Moneda ${esc(d.currency_code)}</dd><dt>Conceptos</dt><dd><ol>${d.items.map(item=>`<li>${esc(item.description)} · ${esc(item.product_service_code)} · ${esc(item.quantity)} × ${esc(item.unit_value)} = ${esc(item.line_total)}</li>`).join('')}</ol></dd><dt>Total</dt><dd>${esc(d.total)} ${esc(d.currency_code)}</dd></dl>${errors?`<div class="mx-issuance-validation"><strong>Validación pendiente:</strong><ul>${errors}</ul></div>`:''}`;
    els.preview.hidden=false;
  }
  async function bootstrap(){try{const result=await request('GET','bootstrap');issuers=result.issuers;savedDrafts=result.drafts;sat=result.sat;fiscal=result.fiscal;csdRegistrationAvailable=result.csd_registration_available===true;renderCatalog();renderIssuers();renderSavedDrafts();if(!els.items.children.length)addItem();message('Borradores disponibles. El timbrado requiere un PAC autorizado y CSD verificado.')}catch(error){message('No se pudo cargar el compositor de facturas. Recarga la página.');console.error('Billing issuance bootstrap failed',error.message)}}
  $('#mx-issuance-pick-patient').addEventListener('click',showPatientPane);
  patientPane.addEventListener('mxmed:billing-patient-selected',event=>{patient=event.detail.patient;profiles=event.detail.profiles||[];renderReceiver();document.querySelector('[data-bs-target="#cfdi-crear"]')?.click();message(profiles.length?'Paciente seleccionado.':'Agrega datos de facturación para este paciente en la pestaña Pacientes.');});
  patientPane.addEventListener('mxmed:billing-profiles-updated',event=>{if(patient){profiles=event.detail.profiles||[];renderReceiver()}});
  $('#mx-issuance-add-item').addEventListener('click',()=>addItem());
  els.items.addEventListener('click',event=>{const target=event.target;if(target.closest('.mx-issuance-remove-item')){if(els.items.children.length>1)target.closest('.mx-issuance-item').remove();return;}if(target.closest('.mx-issuance-add-tax'))target.closest('.mx-issuance-item').querySelector('.mx-issuance-tax-list').insertAdjacentHTML('beforeend',taxMarkup());if(target.closest('.mx-issuance-remove-tax'))target.closest('.mx-issuance-tax').remove();});
  $('#mx-issuance-saved-drafts').addEventListener('change',event=>{if(!event.target.value){draft=null;els.preview.hidden=true;return;}loadDraft(event.target.value).catch(error=>message(error.message));});
  els.issuer.addEventListener('change',()=>renderIssuers(els.issuer.value));
  els.issuerToggle.addEventListener('click',()=>{editingIssuer='';els.issuerEditor.reset();els.issuerEditor.hidden=!els.issuerEditor.hidden;els.issuerToggle.setAttribute('aria-expanded',String(!els.issuerEditor.hidden));if(!els.issuerEditor.hidden)els.issuerEditor.elements.namedItem('alias').focus()});
  $('#mx-issuance-cancel-issuer').addEventListener('click',()=>{els.issuerEditor.hidden=true;els.issuerToggle.setAttribute('aria-expanded','false')});
  els.issuerActions.addEventListener('click',async event=>{const action=event.target.dataset.issuerAction;if(!action)return;const issuer=issuers.find(row=>row.issuer_profile_id===els.issuer.value);if(!issuer)return;
    if(action==='edit'){editingIssuer=issuer.issuer_profile_id;for(const key of ['alias','issuer_legal_name','rfc','fiscal_regime_code','expedition_postal_code'])els.issuerEditor.elements.namedItem(key).value=issuer[key];els.issuerEditor.elements.namedItem('is_default').checked=Number(issuer.is_default)===1;els.issuerEditor.hidden=false;els.issuerToggle.setAttribute('aria-expanded','true');return;}
    if(action==='archive'&&!confirm('¿Archivar este emisor? Los borradores existentes conservarán su referencia.'))return;
    try{await request(action==='archive'?'DELETE':'POST',action==='archive'?'archive_issuer':'default_issuer',{issuer_id:issuer.issuer_profile_id});const data=await request('GET','bootstrap');issuers=data.issuers;renderIssuers();message(action==='archive'?'Emisor archivado.':'Emisor predeterminado actualizado.')}catch(error){message(error.message)}
  });
  els.issuerEditor.addEventListener('submit',async event=>{event.preventDefault();if(!els.issuerEditor.reportValidity())return;const form=new FormData(els.issuerEditor);const profile=Object.fromEntries(['alias','issuer_legal_name','rfc','fiscal_regime_code','expedition_postal_code'].map(key=>[key,String(form.get(key)||'').trim()]));profile.is_default=form.has('is_default');
    try{const data=await request(editingIssuer?'PUT':'POST',editingIssuer?'update_issuer':'create_issuer',{...(editingIssuer?{issuer_id:editingIssuer}:{}),profile});const id=data.issuer.issuer_profile_id;issuers=(await request('GET','bootstrap')).issuers;renderIssuers(id);els.issuerEditor.hidden=true;els.issuerToggle.setAttribute('aria-expanded','false');message('Emisor guardado.')}catch(error){message(error.message)}
  });
  els.csdForm.addEventListener('submit',async event=>{event.preventDefault();if(!els.csdForm.reportValidity()||!els.issuer.value)return;const form=new FormData(els.csdForm);form.set('action','register_csd');form.set('issuer_id',els.issuer.value);
    try{const response=await fetch(api,{method:'POST',credentials:'same-origin',cache:'no-store',headers:{'X-Billing-Issuance-CSRF':csrf},body:form});const result=await response.json();if(!response.ok||!result.ok)throw new Error(result.error||'csd_registration_failed');els.csdForm.reset();await loadCsd(els.issuer.value);message('Certificado registrado en almacenamiento privado. Su tipo CSD requiere verificación antes de timbrar.')}catch(error){message(error.message)}
  });
  els.draftForm.addEventListener('submit',async event=>{event.preventDefault();if(!els.draftForm.reportValidity())return;
    try{const payload=readDraft();const result=await request(draft?'PUT':'POST',draft?'update_draft':'create_draft',{...(draft?{draft_id:draft.draft_id}:{}),draft:payload});draft=result.draft;totalsView(draft);savedDrafts=(await request('GET','bootstrap')).drafts;renderSavedDrafts(draft.draft_id);await preview();message('Borrador guardado. Revisa la vista previa y las validaciones pendientes.')}catch(error){message(error.message)}
  });
  $('#mx-issuance-new-draft').addEventListener('click',()=>{draft=null;$('#mx-issuance-saved-drafts').value='';els.preview.hidden=true;els.totals.textContent='Guarda el borrador para calcular los importes definitivos.';message('Nuevo borrador listo.');});
  bootstrap();
})();
