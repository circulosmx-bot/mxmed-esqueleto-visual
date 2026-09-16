/* FISC01. Patient search reuses the canonical PatientsRepository through the
 * authenticated, doctor-scoped billing endpoint. No invoice actions live here. */
(()=>{
  'use strict';
  const pane=document.getElementById('cfdi-pacientes');
  if(!pane)return;
  const $=(selector)=>pane.querySelector(selector);
  const els={search:$('#mx-billing-patient-search'),query:$('#mx-billing-patient-query'),feedback:$('#mx-billing-patient-feedback'),results:$('#mx-billing-patient-results'),area:$('#mx-billing-profile-area'),name:$('#mx-billing-selected-name'),change:$('#mx-billing-change-patient'),add:$('#mx-billing-add-profile'),list:$('#mx-billing-profile-list'),editor:$('#mx-billing-profile-editor'),editorTitle:$('#mx-billing-editor-title'),cancel:$('#mx-billing-cancel-editor'),rfc:$('#mx-billing-rfc'),regime:$('#mx-billing-regime'),use:$('#mx-billing-use')};
  const endpoint='api/billing/patient-profiles.php';
  let csrf='';let catalog=null;let patient=null;let profiles=[];let editingId='';
  const esc=(value)=>String(value??'').replace(/[&<>"']/g,(char)=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
  const message=(value)=>{els.feedback.textContent=value||''};
  const errorText=(error)=>({unauthorized:'Inicia sesión con tu perfil médico para consultar datos de facturación.',patient_scope_denied:'No tienes acceso a este paciente.',billing_profile_not_found:'Estos datos ya no están disponibles. Recarga la sección.',invalid_rfc:'Revisa el formato del RFC.',invalid_fiscal_zip_code:'El código postal fiscal debe tener cinco dígitos.',invalid_fiscal_regime_code:'Selecciona un régimen fiscal válido para este RFC.',invalid_cfdi_use_code:'Selecciona un uso CFDI compatible con el régimen y el RFC.',invalid_billing_email:'Revisa el correo de facturación.',billing_profiles_unavailable:'No se pudieron cargar los datos de facturación. Intenta de nuevo.'})[error?.message]||'No se pudo completar la operación. Revisa los datos e intenta de nuevo.';
  async function request(method,params={},body=null){
    const query=new URLSearchParams(params);
    const queryText=query.toString();
    const url=endpoint+(queryText?'?'+queryText:'');
    const response=await fetch(url,{method,credentials:'same-origin',cache:'no-store',headers:{...(body?{'Content-Type':'application/json'}:{}),...(method!=='GET'?{'X-Billing-Profiles-CSRF':csrf}:{})},body:body?JSON.stringify(body):undefined});
    const data=await response.json().catch(()=>({ok:false,error:'billing_profiles_unavailable'}));
    if(!response.ok||!data.ok)throw new Error(data.error||'billing_profiles_unavailable');
    if(data.data?.csrf_token)csrf=data.data.csrf_token;
    return data.data||{};
  }
  async function loadCatalog(){
    if(catalog)return;
    const data=await request('GET',{action:'catalog'});
    catalog=data.catalog;
    renderCatalogOptions();
  }
  const personKind=()=>{const length=els.rfc.value.trim().length;return length===12?'person_legal':length===13?'person_physical':null};
  const option=(code,label)=>`<option value="${esc(code)}">${esc(code)} — ${esc(label)}</option>`;
  function renderCatalogOptions(wantedRegime=els.regime.value,wantedUse=els.use.value){
    if(!catalog)return;
    const kind=personKind();
    const regimes=catalog.regimes.filter(row=>!kind||row[kind]);
    els.regime.innerHTML='<option value="">Selecciona un régimen</option>'+regimes.map(row=>option(row.code,row.label)).join('');
    if(regimes.some(row=>row.code===wantedRegime))els.regime.value=wantedRegime;
    const regime=els.regime.value;
    const uses=catalog.uses.filter(row=>(!kind||row[kind])&&(!regime||row.allowed_regimes.includes(regime)));
    els.use.innerHTML='<option value="">Selecciona un uso CFDI</option>'+uses.map(row=>option(row.code,row.label)).join('');
    if(uses.some(row=>row.code===wantedUse))els.use.value=wantedUse;
  }
  function renderResults(items){
    els.results.innerHTML=items.map(row=>`<button type="button" class="mx-billing-result" data-patient-id="${esc(row.patient_id)}">${esc(row.display_name)}</button>`).join('');
    if(!items.length)message('No se encontraron pacientes en tu archivo.');
    else message(`${items.length} paciente${items.length===1?'':'s'} encontrado${items.length===1?'':'s'}.`);
  }
  function maskedRfc(value){const rfc=String(value||'');return rfc.length>4?rfc.slice(0,3)+'••••'+rfc.slice(-3):'••••';}
  function label(group,code){return catalog?.[group]?.find(row=>row.code===code)?.label||code;}
  function renderProfiles(){
    if(!profiles.length){els.list.innerHTML='<p class="mx-billing-feedback">Este paciente aún no tiene datos de facturación.</p>';return;}
    els.list.innerHTML=profiles.map(profile=>{
      const id=esc(profile.billing_profile_id);
      const isDefault=Number(profile.is_default)===1;
      return `<article class="mx-billing-card"><div class="mx-billing-card-head"><h5>${esc(profile.alias)}</h5>${isDefault?'<span class="mx-billing-default-badge">PREDETERMINADO</span>':''}</div><p class="mx-billing-receiver">${esc(profile.receiver_legal_name)}</p><p>RFC: ${esc(maskedRfc(profile.rfc))} · CP fiscal: ${esc(profile.fiscal_zip_code)}</p><p>Régimen: ${esc(profile.fiscal_regime_code)} — ${esc(label('regimes',profile.fiscal_regime_code))}</p><p>Uso CFDI: ${esc(profile.default_cfdi_use_code)} — ${esc(label('uses',profile.default_cfdi_use_code))}</p><div class="mx-billing-card-actions"><button type="button" class="btn mx-billing-btn-secondary" data-action="edit" data-id="${id}">Editar</button>${isDefault?'':`<button type="button" class="btn mx-billing-btn-secondary" data-action="default" data-id="${id}">Hacer predeterminado</button>`}<button type="button" class="btn mx-billing-btn-danger" data-action="archive" data-id="${id}">Eliminar</button></div></article>`;
    }).join('');
  }
  async function selectPatient(entry){
    patient=entry;editingId='';els.editor.hidden=true;
    els.name.textContent=entry.display_name;
    els.area.hidden=false;els.results.innerHTML='';message('');
    try{await loadProfiles();pane.dispatchEvent(new CustomEvent('mxmed:billing-patient-selected',{detail:{patient:{...patient},profiles:[...profiles]}}))}catch(error){message(errorText(error));els.area.hidden=true;patient=null}
  }
  async function loadProfiles(){
    const data=await request('GET',{patient_id:patient.patient_id});
    profiles=Array.isArray(data.profiles)?data.profiles:[];
    renderProfiles();
    pane.dispatchEvent(new CustomEvent('mxmed:billing-profiles-updated',{detail:{profiles:[...profiles]}}));
  }
  function openEditor(profile=null){
    editingId=profile?.billing_profile_id||'';
    els.editor.reset();
    els.editorTitle.textContent=profile?'Editar datos de facturación':'Agregar datos de facturación';
    for(const key of ['alias','receiver_legal_name','rfc','fiscal_zip_code','billing_email']){
      els.editor.elements.namedItem(key).value=profile?.[key]||'';
    }
    renderCatalogOptions(profile?.fiscal_regime_code||'',profile?.default_cfdi_use_code||'');
    els.editor.elements.namedItem('is_default').checked=Number(profile?.is_default)===1;
    els.editor.hidden=false;
    els.editor.elements.namedItem('alias').focus();
  }
  els.search.addEventListener('submit',async(event)=>{
    event.preventDefault();if(!els.search.reportValidity())return;
    els.area.hidden=true;patient=null;els.results.innerHTML='';message('Buscando pacientes…');
    try{const data=await request('GET',{action:'search',q:els.query.value.trim()});renderResults(data.patients||[])}catch(error){message(errorText(error))}
  });
  els.results.addEventListener('click',(event)=>{
    const button=event.target.closest('[data-patient-id]');if(!button)return;
    const id=button.dataset.patientId;
    selectPatient({patient_id:id,display_name:button.textContent.trim()});
  });
  els.change.addEventListener('click',()=>{patient=null;els.area.hidden=true;els.results.innerHTML='';message('');pane.dispatchEvent(new CustomEvent('mxmed:billing-patient-cleared'));els.query.focus()});
  els.add.addEventListener('click',()=>openEditor());
  els.cancel.addEventListener('click',()=>{els.editor.hidden=true;editingId=''});
  els.rfc.addEventListener('input',()=>renderCatalogOptions());
  els.regime.addEventListener('change',()=>renderCatalogOptions(els.regime.value,''));
  els.editor.addEventListener('submit',async(event)=>{
    event.preventDefault();if(!patient||!els.editor.reportValidity())return;
    const form=new FormData(els.editor);
    const draft={alias:String(form.get('alias')||''),receiver_legal_name:String(form.get('receiver_legal_name')||''),rfc:String(form.get('rfc')||''),fiscal_zip_code:String(form.get('fiscal_zip_code')||''),fiscal_regime_code:String(form.get('fiscal_regime_code')||''),default_cfdi_use_code:String(form.get('default_cfdi_use_code')||''),billing_email:String(form.get('billing_email')||''),is_default:form.has('is_default')};
    try{
      await request(editingId?'PUT':'POST',{},editingId?{patient_id:patient.patient_id,billing_profile_id:editingId,profile:draft}:{patient_id:patient.patient_id,profile:draft});
      els.editor.hidden=true;editingId='';await loadProfiles();message('Datos de facturación guardados.');
    }catch(error){message(errorText(error))}
  });
  els.list.addEventListener('click',async(event)=>{
    const button=event.target.closest('[data-action][data-id]');if(!button||!patient)return;
    const profile=profiles.find(row=>row.billing_profile_id===button.dataset.id);if(!profile)return;
    if(button.dataset.action==='edit'){openEditor(profile);return}
    if(button.dataset.action==='archive'&&!window.confirm('¿Eliminar estos datos de facturación? Se archivarán y dejarán de aparecer en la lista.'))return;
    try{
      if(button.dataset.action==='default')await request('POST',{}, {action:'set_default',patient_id:patient.patient_id,billing_profile_id:profile.billing_profile_id});
      else if(button.dataset.action==='archive')await request('DELETE',{}, {patient_id:patient.patient_id,billing_profile_id:profile.billing_profile_id});
      await loadProfiles();message(button.dataset.action==='archive'?'Datos de facturación archivados.':'Perfil predeterminado actualizado.');
    }catch(error){message(errorText(error))}
  });
  document.querySelector('[data-bs-target="#cfdi-pacientes"]')?.addEventListener('shown.bs.tab',()=>loadCatalog().catch(error=>message(errorText(error))));
})();
