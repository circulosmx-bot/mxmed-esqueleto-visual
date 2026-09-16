/* FISC02A: authenticated archive UI. Patient selection remains owned by FISC01. */
(()=>{
  'use strict';
  const pane=document.getElementById('cfdi-pacientes');
  const globalPane=document.getElementById('cfdi-listar');
  if(!pane||!globalPane)return;
  const $=(id)=>document.getElementById(id);
  const els={dataTab:$('mx-billing-tab-data'),invoiceTab:$('mx-billing-tab-invoices'),dataPanel:$('mx-billing-data-panel'),invoicePanel:$('mx-billing-invoices-panel'),patientList:$('mx-invoice-patient-list'),patientFeedback:$('mx-invoice-patient-feedback'),patientDetail:$('mx-invoice-patient-detail'),globalList:$('mx-invoice-global-list'),globalFeedback:$('mx-invoice-global-feedback'),globalDetail:$('mx-invoice-global-detail'),filters:$('mx-invoice-filters'),patientFilter:$('mx-invoice-filter-patient'),patientFilterId:$('mx-invoice-filter-patient-id'),patientResults:$('mx-invoice-patient-results'),form:$('mx-invoice-import-form'),preview:$('mx-invoice-preview'),profile:$('mx-invoice-profile')};
  const endpoint='api/billing/invoices.php';
  let csrf='';let patient=null;let profiles=[];let pending=null;let searchTimer=0;
  const esc=(value)=>String(value??'').replace(/[&<>"']/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
  const status=(value)=>({UNKNOWN:'Sin verificar',VERIFIED_VALID:'Vigente verificada',VERIFIED_CANCELED:'Cancelada verificada'})[value]||'Sin verificar';
  const money=(value,code)=>{const amount=Number(value);return Number.isFinite(amount)?new Intl.NumberFormat('es-MX',{style:'currency',currency:/^[A-Z]{3}$/.test(code)?code:'MXN'}).format(amount):String(value||'');};
  const errorText=(error)=>({unauthorized:'Inicia sesión como médico para consultar facturas.',patient_scope_denied:'No tienes acceso a este paciente.',billing_profile_scope_denied:'El perfil de facturación seleccionado no corresponde a este paciente.',invoice_already_imported:'Esta factura ya está importada para tu cuenta.',invoice_preview_changed:'Los archivos cambiaron. Revisa el XML de nuevo.',invalid_cfdi_xml:'El XML no es un CFDI válido.',unsupported_cfdi:'Sólo se admiten CFDI 4.0 de ingreso timbrados.',invalid_invoice_pdf:'El PDF no es válido.',invoice_document_size_invalid:'El documento supera el límite de 5 MiB o está vacío.',invoice_not_found:'La factura no está disponible.',invoice_archive_unavailable:'No se pudo consultar el archivo de facturas.'})[error?.message]||'No se pudo completar la operación. Revisa los archivos e intenta de nuevo.';
  async function request(params={},options={}){
    const query=new URLSearchParams(params);
    const response=await fetch(endpoint+(query.toString()?'?'+query:''),{credentials:'same-origin',cache:'no-store',...options,headers:{...(options.method==='POST'?{'X-Billing-Invoices-CSRF':csrf}:{}),...(options.headers||{})}});
    const body=await response.json().catch(()=>({ok:false,error:'invoice_archive_unavailable'}));
    if(!response.ok||!body.ok)throw new Error(body.error||'invoice_archive_unavailable');
    return body.data||{};
  }
  async function context(){if(!csrf)csrf=(await request({action:'context'})).csrf_token;}
  function setTab(showInvoices){
    els.dataTab.setAttribute('aria-selected',String(!showInvoices));els.invoiceTab.setAttribute('aria-selected',String(showInvoices));
    els.dataPanel.hidden=showInvoices;els.invoicePanel.hidden=!showInvoices;
    if(showInvoices&&patient)loadPatient().catch(error=>els.patientFeedback.textContent=errorText(error));
  }
  function renderRows(rows,container){
    container.innerHTML=rows.length?rows.map(row=>{
      const id=esc(row.invoice_id),pid=esc(row.patient_id),uuid=esc(row.cfdi_uuid);
      const folio=[row.series,row.folio].filter(Boolean).join('-')||uuid.slice(0,8);
      return `<article class="mx-invoice-row"><div class="mx-invoice-row-main"><strong>${esc(row.issued_at?.slice(0,10)||'')}</strong><span>${esc(row.patient_name||'')}</span><span>${esc(row.receiver_legal_name_snapshot||'')}</span><span>Folio ${esc(folio)} · UUID ${uuid.slice(0,8)}…</span><strong>${esc(money(row.total,row.currency_code))}</strong><span class="mx-invoice-status">${esc(status(row.status))}</span></div><div class="mx-invoice-actions"><button class="btn mx-billing-btn-secondary" type="button" data-invoice-action="detail" data-invoice-id="${id}" data-patient-id="${pid}">Ver detalles</button><button class="btn mx-billing-btn-secondary" type="button" data-invoice-action="xml" data-invoice-id="${id}" data-patient-id="${pid}">Descargar XML</button>${Number(row.has_pdf)?`<button class="btn mx-billing-btn-secondary" type="button" data-invoice-action="pdf" data-invoice-id="${id}" data-patient-id="${pid}">Descargar PDF</button>`:''}</div></article>`;
    }).join(''):'<p class="mx-invoice-empty">No hay facturas para mostrar.</p>';
  }
  async function loadPatient(){
    if(!patient)return;
    const selectedId=patient.patient_id;
    els.patientFeedback.textContent='Cargando facturas…';
    const data=await request({action:'list',patient_id:selectedId});
    if(!patient||patient.patient_id!==selectedId) return;
    renderRows(data.invoices||[],els.patientList);
    els.patientFeedback.textContent='';
  }
  async function loadGlobal(){
    els.globalFeedback.textContent='Cargando facturas…';
    const params={};
    for(const [key,value] of new FormData(els.filters))if(String(value).trim())params[key]=String(value).trim();
    if(els.patientFilterId.value)params.patient_id=els.patientFilterId.value;
    await context();
    const data=await request({},{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'search',filters:params})});
    renderRows(data.invoices||[],els.globalList);els.globalFeedback.textContent='';
  }
  function detailMarkup(row){
    const fields=[['Paciente',row.patient_name],['Receptor',row.receiver_legal_name_snapshot],['RFC',row.receiver_rfc_snapshot],['Régimen fiscal',row.receiver_regime_code_snapshot],['CP fiscal',row.receiver_fiscal_zip_snapshot],['Uso CFDI',row.cfdi_use_code_snapshot],['Fecha de emisión',row.issued_at],['UUID',row.cfdi_uuid],['Serie / folio',[row.series,row.folio].filter(Boolean).join(' / ')||'—'],['Subtotal',money(row.subtotal,row.currency_code)],['Descuento',row.discount===null?'—':money(row.discount,row.currency_code)],['Impuestos',money(row.tax_total,row.currency_code)],['Total',money(row.total,row.currency_code)],['Estado',status(row.status)]];
    return `<div class="mx-invoice-detail-head"><h4>Detalle de factura</h4><button type="button" class="btn mx-billing-btn-secondary" data-invoice-action="close-detail">Cerrar</button></div><dl>${fields.map(([label,value])=>`<div><dt>${esc(label)}</dt><dd>${esc(value)}</dd></div>`).join('')}</dl><p>El estado de una importación histórica no está verificado ante el SAT.</p><div class="mx-invoice-actions"><button type="button" class="btn mx-billing-btn-secondary" data-invoice-action="xml" data-invoice-id="${esc(row.invoice_id)}" data-patient-id="${esc(row.patient_id)}">Descargar XML</button>${row.pdf_sha256?`<button type="button" class="btn mx-billing-btn-secondary" data-invoice-action="pdf" data-invoice-id="${esc(row.invoice_id)}" data-patient-id="${esc(row.patient_id)}">Descargar PDF</button>`:''}</div>`;
  }
  async function handleListClick(event,detailTarget){
    const button=event.target.closest('[data-invoice-action]');if(!button)return;
    const action=button.dataset.invoiceAction;
    if(action==='close-detail'){detailTarget.hidden=true;detailTarget.innerHTML='';return;}
    if(!button.dataset.invoiceId||!button.dataset.patientId)return;
    if(action==='detail'){
      try{const data=await request({action:'detail',invoice_id:button.dataset.invoiceId,patient_id:button.dataset.patientId});detailTarget.innerHTML=detailMarkup(data.invoice);detailTarget.hidden=false;detailTarget.scrollIntoView({block:'nearest'});}catch(error){detailTarget.textContent=errorText(error);detailTarget.hidden=false;}
    }else if(action==='xml'||action==='pdf'){
      const link=document.createElement('a');link.href=endpoint+'?'+new URLSearchParams({action:'download',invoice_id:button.dataset.invoiceId,patient_id:button.dataset.patientId,kind:action});link.download='';document.body.append(link);link.click();link.remove();
    }
  }
  function resetPreview(){pending=null;els.preview.hidden=true;els.preview.innerHTML='';}
  function renderPreview(row){
    const items=[['Receptor',row.receiver_legal_name_snapshot],['RFC',row.receiver_rfc_snapshot],['Fecha',row.issued_at],['UUID',row.cfdi_uuid],['Subtotal',money(row.subtotal,row.currency_code)],['Impuestos',money(row.tax_total,row.currency_code)],['Total',money(row.total,row.currency_code)]];
    els.preview.innerHTML=`<h5>Confirma la factura histórica</h5><dl>${items.map(([label,value])=>`<div><dt>${esc(label)}</dt><dd>${esc(value)}</dd></div>`).join('')}</dl><p>Se guardará con estado «Sin verificar». El receptor fiscal permanecerá como copia histórica.</p><div class="mx-invoice-actions"><button class="btn mx-billing-btn-primary" type="button" id="mx-invoice-confirm">Confirmar importación</button><button class="btn mx-billing-btn-secondary" type="button" id="mx-invoice-review-cancel">Cancelar</button></div>`;
    els.preview.hidden=false;
  }
  function importData(action){const form=new FormData(els.form);form.set('action',action);form.set('patient_id',patient.patient_id);return form;}
  function profileOptions(){els.profile.innerHTML='<option value="">Sin vínculo a perfil</option>'+profiles.map(p=>`<option value="${esc(p.billing_profile_id)}">${esc(p.alias)}</option>`).join('');}
  els.dataTab.addEventListener('click',()=>setTab(false));els.invoiceTab.addEventListener('click',()=>setTab(true));
  pane.addEventListener('mxmed:billing-patient-selected',event=>{patient=event.detail.patient;profiles=event.detail.profiles||[];profileOptions();setTab(false);resetPreview();els.patientDetail.hidden=true;els.form.hidden=true;});
  pane.addEventListener('mxmed:billing-patient-cleared',()=>{patient=null;profiles=[];resetPreview();els.form.hidden=true;els.patientList.innerHTML='';els.patientDetail.hidden=true;setTab(false);});
  pane.addEventListener('mxmed:billing-profiles-updated',event=>{profiles=event.detail.profiles||[];profileOptions();});
  $('mx-invoice-open-import').addEventListener('click',()=>{if(!patient)return;els.form.hidden=false;els.form.reset();resetPreview();els.form.querySelector('input[type=file]').focus();});
  $('mx-invoice-cancel-import').addEventListener('click',()=>{els.form.hidden=true;resetPreview();});
  els.form.addEventListener('change',resetPreview);
  els.form.addEventListener('submit',async event=>{
    event.preventDefault();if(!patient||!els.form.reportValidity())return;
    els.patientFeedback.textContent='Validando XML…';
    try{await context();const data=await request({},{method:'POST',body:importData('preview')});pending=data.preview;renderPreview(pending);els.patientFeedback.textContent='';}
    catch(error){els.patientFeedback.textContent=errorText(error);resetPreview();}
  });
  els.preview.addEventListener('click',async event=>{
    if(event.target.closest('#mx-invoice-review-cancel')){resetPreview();return;}
    const button=event.target.closest('#mx-invoice-confirm');if(!button||!pending||!patient)return;
    button.disabled=true;els.patientFeedback.textContent='Guardando factura…';
    try{const form=importData('import');form.set('confirmed_xml_sha256',pending.xml_sha256);if(pending.pdf_sha256)form.set('confirmed_pdf_sha256',pending.pdf_sha256);await request({},{method:'POST',body:form});els.form.hidden=true;els.form.reset();resetPreview();els.patientFeedback.textContent='Factura histórica guardada.';await loadPatient();}
    catch(error){els.patientFeedback.textContent=errorText(error);button.disabled=false;}
  });
  els.patientList.addEventListener('click',event=>handleListClick(event,els.patientDetail));els.patientDetail.addEventListener('click',event=>handleListClick(event,els.patientDetail));
  els.globalList.addEventListener('click',event=>handleListClick(event,els.globalDetail));els.globalDetail.addEventListener('click',event=>handleListClick(event,els.globalDetail));
  els.filters.addEventListener('submit',event=>{event.preventDefault();loadGlobal().catch(error=>els.globalFeedback.textContent=errorText(error));});
  els.patientFilter.addEventListener('input',()=>{els.patientFilterId.value='';els.patientResults.hidden=true;clearTimeout(searchTimer);const q=els.patientFilter.value.trim();if(q.length<2)return;searchTimer=setTimeout(async()=>{try{const response=await fetch('api/billing/patient-profiles.php?'+new URLSearchParams({action:'search',q}),{credentials:'same-origin',cache:'no-store'});const data=await response.json();if(q!==els.patientFilter.value.trim())return;els.patientResults.innerHTML=(data.data?.patients||[]).map(row=>`<button type="button" data-patient-id="${esc(row.patient_id)}">${esc(row.display_name)}</button>`).join('');els.patientResults.hidden=!els.patientResults.childElementCount;}catch(_){els.patientResults.hidden=true;}},250);});
  els.patientResults.addEventListener('click',event=>{const button=event.target.closest('[data-patient-id]');if(!button)return;els.patientFilterId.value=button.dataset.patientId;els.patientFilter.value=button.textContent.trim();els.patientResults.hidden=true;loadGlobal().catch(error=>els.globalFeedback.textContent=errorText(error));});
  document.querySelector('[data-bs-target="#cfdi-listar"]')?.addEventListener('shown.bs.tab',()=>loadGlobal().catch(error=>els.globalFeedback.textContent=errorText(error)));
})();
