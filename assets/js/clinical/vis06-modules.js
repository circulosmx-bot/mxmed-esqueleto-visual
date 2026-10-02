// VIS06: patient-scoped read presentation. Existing callers retain all write authority.
(function () {
  const pane = document.getElementById('p-expediente');
  if (!pane) return;
  const config = {
    orders:{target:'t-estudios',title:'Órdenes y resultados',copy:'Solicitudes y resultados vinculados a su atención de origen.',empty:'No hay órdenes ni resultados registrados.',action:'Solicitar estudios'},
    documents:{target:'t-consent',title:'Documentos',copy:'Archivos clínicos, informes y versiones. Las órdenes y los resultados tienen su propia sección.',empty:'No hay documentos clínicos registrados.',action:'Crear o adjuntar documento'},
    prescriptions:{target:'t-tratamiento',title:'Recetas',copy:'Historial de prescripciones. Una receta no confirma el uso actual del medicamento.',empty:'No hay recetas registradas.',action:'Emitir receta'}
  };
  const orderTypes = new Set(['order','orders','lab_order','imaging_order','orden_estudio']);
  const resultTypes = new Set(['lab_result','lab_pdf','imaging_result','external_result','external_report','result']);
  const legacyDocumentResultTypes = new Set(['lab_result','lab_pdf','imaging_result','external_result','external_report']);
  const prescriptionTypes = new Set(['prescription','receta']);
  const administrativeTypes = new Set(['invoice','receipt','factura','cfdi']);
  const selectedPatient = () => String(pane.dataset.patientId || pane.dataset.activePatientId || '').trim();
  const payload = row => { try { const value = typeof row.payload_json === 'string' ? JSON.parse(row.payload_json) : row.payload_json; return value && typeof value === 'object' ? value : {}; } catch (_) { return {}; } };
  const home = row => orderTypes.has(row.document_type) || (legacyDocumentResultTypes.has(row.document_type) && (row.document_type !== 'external_report' || relation(row))) ? 'orders' : prescriptionTypes.has(row.document_type) ? 'prescriptions' : 'documents';
  const relation = row => { const p = payload(row); return String(p.related_order_document_uuid || p.related_order_document_id || p.related_document_uuid || p.related_document_id || p.related_order_id || p.context?.related_order_document_uuid || ''); };
  const day = value => { const m=String(value || '').match(/^(\d{4})-(\d{2})-(\d{2})/); return m ? `${m[3]}/${m[2]}/${m[1]}` : 'Sin fecha registrada'; };
  const coverageLabel = state => ({NO_RESULTS:'Sin resultados',PARTIAL_RESULTS:'Algunos estudios tienen resultado',ALL_ITEMS_HAVE_RESULTS:'Todos los estudios tienen resultado',UNKNOWN_COVERAGE:'Cobertura por estudio no especificada',UNKNOWN_LEGACY:'Cobertura por estudio no especificada'})[state] || 'Cobertura por estudio no especificada';
  const itemCoverageLabel = state => ({NO_RESULT:'Sin resultado',RESULT_AVAILABLE:'Resultado disponible',UNKNOWN:'Cobertura no especificada'})[state] || 'Cobertura no especificada';
  const longDay = value => { const m=String(value || '').match(/^(\d{4})-(\d{2})-(\d{2})/); if(!m)return 'Sin fecha registrada';return `${Number(m[3])} ${['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'][Number(m[2])-1]} ${m[1]}`; };
  const label = row => ({pdf:'PDF clínico',image:'Imagen clínica',note:'Nota clínica',order:'Orden de estudio',lab_result:'Resultado de laboratorio',imaging_result:'Resultado de imagen',external_report:'Informe externo',prescription:'Receta',receta:'Receta'})[row.document_type] || 'Documento clínico';
  const projectionLabel = row => ({order:'Orden de estudio',orders:'Orden de estudio',lab_order:'Orden de laboratorio',imaging_order:'Orden de imagen',orden_estudio:'Orden de estudio',lab_result:'Resultado de laboratorio',lab_pdf:'Resultado de laboratorio',imaging_result:'Resultado de imagen',result:'Resultado de estudio',external_result:'Resultado externo',external_report:'Informe externo'})[row.document_type] || label(row);
  const node = (tag, text, cls='') => {const e=document.createElement(tag);e.textContent=text;e.className=cls;return e;};
  const symbol = (name,cls='') => {const icon=node('span',name,`material-symbols-rounded ${cls}`);icon.setAttribute('aria-hidden','true');return icon;};
  const button = (text, action) => {const b=node('button',text,'btn btn-outline-primary btn-sm');b.type='button';b.addEventListener('click',action);return b;};
  const openPortableOrder = uuid => {
    if(!uuid || !professional)return;
    const query=new URLSearchParams({uuid,doctor_id:professional});
    window.open(`/modules/clinical/ui/portable-order.php?${query}`,'_blank','noopener');
  };
  const downloadPortableOrder = uuid => {
    if(!uuid || !professional)return;
    const query=new URLSearchParams({uuid,doctor_id:professional});
    const link=document.createElement('a');
    link.href=`/modules/clinical/ui/portable-order-pdf.php?${query}`;
    link.download='';
    document.body.append(link);link.click();link.remove();
  };
  let orderComposerDialog=null;
  function openGeneralOrder(trigger,initialNavigation=''){
    if(orderComposerDialog)return;
    const patientId=selectedPatient(),doctorId=professional;
    if(!patientId||!doctorId||!window.mxmedStudyComposer){views.get('orders').notice.textContent='Selecciona un paciente y un profesional antes de solicitar estudios.';return;}
    const dialog=document.createElement('dialog');dialog.className='tax03c-dialog';dialog.setAttribute('aria-label','Solicitar estudios');
    dialog.innerHTML='<form><header><h4>Solicitar estudios</h4><button type="button" class="btn btn-link" data-tax03c-close>Volver a categorías</button></header><div data-tax03c-host></div><p data-tax03c-error role="alert"></p><footer><button type="button" class="btn btn-outline-secondary" data-tax03c-close>Cancelar</button><button type="submit" class="btn btn-primary" data-tax03c-submit>Solicitar estudios</button></footer></form>';
    document.body.append(dialog);orderComposerDialog=dialog;
    let attemptKey=crypto.randomUUID(),attemptEvent='';
    const composer=window.mxmedStudyComposer.mount(dialog.querySelector('[data-tax03c-host]'),{
      doctorId,
      ...(typeof initialNavigation==='string'?{initialCategory:initialNavigation}:{navigationGroup:initialNavigation}),
      onChange:()=>{attemptKey=crypto.randomUUID();attemptEvent='';}
    });
    let busy=false;
    const close=(saved=false)=>{
      if(busy)return;
      if(!saved&&(composer.selected().length||composer.indication().trim()||composer.priority()!=='Rutinaria')&&
        !window.confirm('¿Volver a categorías? Los estudios seleccionados no se guardarán.'))return;
      composer.destroy();dialog.close();dialog.remove();orderComposerDialog=null;trigger?.focus({preventScroll:true});
    };
    dialog.querySelectorAll('[data-tax03c-close]').forEach(control=>control.addEventListener('click',()=>close()));
    dialog.addEventListener('cancel',event=>{event.preventDefault();close();});
    dialog.querySelector('form').addEventListener('submit',async event=>{
      event.preventDefault();if(busy)return;
      const error=dialog.querySelector('[data-tax03c-error]');error.textContent='';
      if(!composer.valid()){error.textContent='Agrega al menos un estudio antes de solicitar la orden.';return;}
      if(selectedPatient()!==patientId||professional!==doctorId){error.textContent='El contexto del paciente cambió. Vuelve a abrir la solicitud.';return;}
      busy=true;const submit=dialog.querySelector('[data-tax03c-submit]');submit.disabled=true;submit.textContent='Guardando…';
      const items=composer.selected(),type=window.mxmedStudyComposer.documentType(items),priority=composer.priority(),indication=composer.indication().trim();
      const payload={source:'tax03c_catalog_composer',order_area:window.mxmedStudyComposer.orderArea(items),priority,indication,order_items:composer.orderItems()};
      attemptEvent ||=new Date().toISOString().slice(0,19).replace('T',' ');
      const command={patient_id:patientId,document_type:type,title:window.mxmedStudyComposer.title(items),
        summary:`${items.length} estudio${items.length===1?'':'s'} · ${priority}`,event_datetime:attemptEvent,payload};
      try{
        const response=await fetch(`/api/clinical/index.php/doctors/${encodeURIComponent(doctorId)}/patients/${encodeURIComponent(patientId)}/documents`,{
          method:'POST',credentials:'same-origin',headers:{Accept:'application/json','Content-Type':'application/json','Idempotency-Key':attemptKey},body:JSON.stringify(command)});
        const result=await response.json();if(!response.ok||result?.ok!==true)throw new Error(result?.message||'No se pudo guardar la orden.');
        busy=false;close(true);
        window.dispatchEvent(new CustomEvent('mxmed:clinical-document-created',{detail:{patient_id:patientId,document_type:type,source:'tax03c_catalog_composer'}}));
        setOrderFlow(views.get('orders'),'PENDING');
      }catch(failure){error.textContent=failure?.message||'No se pudo guardar la orden. Intenta de nuevo.';busy=false;submit.disabled=false;submit.textContent='Solicitar estudios';}
    });
    dialog.showModal();dialog.querySelector('[data-tax03c-search]')?.focus();
  }
  const views = new Map();
  let rows=[], patient='', professional='', generation=0, ordersRequest=0, ordersItems=[], ordersCursor=null, ordersHasMore=false, ordersPageLoading=false;
  async function get(path) {
    const response=await fetch(`/api/clinical/index.php/${path}`,{credentials:'same-origin',headers:{Accept:'application/json'}});
    const result=await response.json();
    if(!response.ok || result?.ok !== true) throw new Error('No se pudo consultar la información clínica. Intenta actualizar.');
    return result.data;
  }
  function status(row) {
    if(row.has_successor==1) return 'Reemplazado';
    if(row.status==='voided') return 'Anulado';
    if(row.status==='draft') return 'Borrador';
    if(orderTypes.has(row.document_type)) return rows.some(r=>resultTypes.has(r.document_type) && r.status!=='voided' && (relation(r)===String(row.id)||relation(r)===String(row.document_uuid))) ? 'Resultado recibido' : 'Sin resultado vinculado';
    if(resultTypes.has(row.document_type) && home(row)==='orders') return 'Resultado recibido';
    return ({signed:'Firmado',generated:'Generado'})[row.status] || 'Registrado';
  }
  async function privateRead(row, view) {
    const seen=generation;
    try {
      const response=await fetch(`/api/clinical/index.php/documents/${encodeURIComponent(row.document_uuid)}/binary/ORIGINAL`,{credentials:'same-origin'});
      const type=(response.headers.get('Content-Type')||'').split(';')[0];
      if(!response.ok || !['application/pdf','image/jpeg','image/png','image/webp'].includes(type)) throw new Error();
      const blob=await response.blob();if(seen!==generation || selectedPatient()!==patient) return;
      const url=URL.createObjectURL(blob), a=document.createElement('a');a.href=url;a.target='_blank';a.rel='noopener noreferrer';a.click();setTimeout(()=>URL.revokeObjectURL(url),60000);
    } catch (_) {if(seen===generation)view.notice.textContent='No se pudo abrir el archivo privado autorizado.';}
  }
  function revealRecord(row, view) {
    view.detail.replaceChildren();view.detail.hidden=false;
    view.detail.dataset.document=String(row.id);
    view.detail.append(node('h4',row.title || label(row)),node('p',`${label(row)} · ${day(row.event_datetime)} · ${status(row)}`));
    if(row.summary) view.detail.append(node('p',row.summary));
    const p=payload(row);
    // Display approved human content only; never stringify storage/technical metadata.
    if(typeof p.text==='string')view.detail.append(node('p',p.text));
    if(home(row)==='prescriptions')view.detail.append(node('p','Prescripción registrada; el uso actual se confirma en Medicamentos.'));
    const family=rows.filter(r=>String(r.lineage_root_id || r.id)===String(row.lineage_root_id || row.id));
    if(family.length>1) {
      view.detail.append(node('h5','Historial de versiones'),node('p','El original permanece conservado. Esto es un reemplazo documental, no una enmienda de la consulta.'));
      family.forEach(r=>{const line=node('div','', 'vis06-version');line.append(node('span',`${day(r.event_datetime)} · ${r.title || label(r)} · ${r.has_successor==1?'Reemplazado':'Versión vigente'}`));if(r.has_private_binary==1)line.append(button('Abrir esta versión',()=>privateRead(r,view)));view.detail.append(line);});
    }
    const related=rows.find(r=>String(r.id)===relation(row)||String(r.document_uuid)===relation(row));
    if(related)view.detail.append(node('p',`Orden de origen: ${related.title || 'Orden de estudio'}`));
    const resultRows=orderTypes.has(row.document_type)?rows.filter(r=>relation(r)===String(row.id)||relation(r)===String(row.document_uuid)):[];
    resultRows.forEach(r=>view.detail.append(button(`Ver resultado: ${r.title || 'Resultado'}`,()=>revealRecord(r,view))));
    const close=button('Cerrar detalle',()=>{view.detail.hidden=true;view.lastTrigger?.focus();});view.detail.append(close);
    view.detail.tabIndex=-1;view.detail.focus({preventScroll:true});view.detail.scrollIntoView({block:'nearest'});
  }
  function render(view) {
    if(view.kind==='orders'){renderOrders(view);return;}
    view.list.replaceChildren();view.detail.hidden=true;
    const needle=view.search.value.trim().toLocaleLowerCase('es');
    const items=rows.filter(r=>home(r)===view.kind&&!administrativeTypes.has(r.document_type));
    const filtered=items.filter(r=>(!needle || `${r.title||''} ${r.summary||''} ${label(r)}`.toLocaleLowerCase('es').includes(needle)) && (!view.filter.value || (view.kind==='orders' ? view.filter.value==='orders'?orderTypes.has(r.document_type):resultTypes.has(r.document_type) : r.status===view.filter.value)));
    view.notice.textContent=`${filtered.length} registro(s) visibles${rows.length===200?' · Se muestran hasta 200 registros recientes':''}.`;
    if(!filtered.length){view.list.append(node('p',items.length?'No hay coincidencias con estos filtros.':view.settings.empty,'vis06-empty'));return;}
    filtered.forEach(row=>{
      const card=node('article','','vis06-row');card.dataset.document=String(row.id);
      const main=node('div','','vis06-row-main');main.append(node('h4',row.title || label(row)),node('p',`${label(row)} · ${day(row.event_datetime)} · ${row.encounter_ref_id || row.encounter_id?'Vinculado a consulta':'Archivo del paciente'}`));
      if(row.summary)main.append(node('p',row.summary,'vis06-summary'));
      const badge=node('span',status(row),'vis06-status');main.append(badge);
      if(resultTypes.has(row.document_type)&&row.created_after_final_note==1)main.append(node('p','Resultado recibido después de finalizar','vis06-late'));
      if(row.lineage_root_id && String(row.lineage_root_id)!==String(row.id) && row.has_successor!=1)main.append(node('span','Versión vigente','vis06-status'));
      const actions=node('div','','vis06-actions');const detail=button('Ver detalle',()=>{view.lastTrigger=detail;revealRecord(row,view);});actions.append(detail);
      if(row.has_private_binary==1)actions.append(button('Abrir archivo',()=>privateRead(row,view)));
      if(rows.some(r=>String(r.lineage_root_id || r.id)===String(row.lineage_root_id || row.id)&&String(r.id)!==String(row.id)))actions.append(button('Ver historial',()=>{view.lastTrigger=detail;revealRecord(row,view);}));
      card.append(main,actions);view.list.append(card);
    });
  }
  const orderOrigin = row => ({ENCOUNTER:'Consulta',PATIENT:'Fuera de consulta',APPOINTMENT:'Asociada a cita',HOSPITAL_STAY:'Hospitalización'})[row.source_scope] || '';
  const orderIcon = row => row.document_type==='lab_order'?'science':row.document_type==='imaging_order'?'image':'biotech';
  const orderTitle = row => row.title || row.requested_studies?.[0] || projectionLabel(row);
  const resultCount = count => count===1?'1 resultado':`${count} resultados`;
  const currentFilter = view => view.kind==='orders'?view.filter.dataset.value:view.filter.value;
  const pendingOrder = item => item.kind==='ORDER' && item.order.status!=='voided' &&
    item.order.coverage_state!=='ALL_ITEMS_HAVE_RESULTS';
  function visibleOrderItems(view) {
    const filter=currentFilter(view);
    return ordersItems.filter(item=>{
      if(view.flow==='PENDING')return pendingOrder(item)&&(
        filter==='none' ? item.order.coverage_state==='NO_RESULTS'||item.result_count===0 :
        filter==='partial' ? item.order.coverage_state==='PARTIAL_RESULTS' : true);
      if(view.flow==='HISTORY')return filter==='complete'
        ? item.kind==='ORDER'&&item.order.coverage_state==='ALL_ITEMS_HAVE_RESULTS'
        : item.kind==='RESULT'||item.kind==='STANDALONE_RESULT'||item.kind==='UNRESOLVED_RESULT';
      return true;
    });
  }
  function flowFilters(view,options,selected) {
    view.filter.replaceChildren();view.filter.dataset.value=selected;
    options.forEach(([value,label])=>{
      const control=node('button',label);control.type='button';control.dataset.filter=value;
      control.setAttribute('aria-pressed',String(value===selected));view.filter.append(control);
    });
  }
  function setOrderFlow(view,flow) {
    if(!view)return;
    view.flow=flow;view.module.dataset.orFlow=flow.toLowerCase();ordersRequest++;
    view.home.hidden=flow!=='HOME';view.categoryScreen.hidden=flow!=='CATEGORY';
    view.flowBack.hidden=flow!=='PENDING'&&flow!=='HISTORY';
    view.categoryBack.hidden=flow!=='CATEGORY';
    const heading={HOME:['Órdenes y resultados','Selecciona lo que deseas hacer para este paciente.'],
      CATEGORY:['Generar nueva orden',''],
      PENDING:['Revisar órdenes pendientes','Solicitudes que requieren seguimiento.'],
      HISTORY:['Ver resultados e historial','Resultados registrados y órdenes completas.']}[flow];
    view.headTitle.textContent=heading[0];view.headCopy.textContent=heading[1];view.headCopy.hidden=flow==='CATEGORY';
    view.search.placeholder=flow==='PENDING'?'Buscar orden o estudio':'Buscar resultado u orden';
    view.search.value='';view.selectedListId='';view.selectedRowId='';view.workspace.classList.remove('is-detail-open');
    view.list.replaceChildren();emptyDetail(view,false);view.notice.textContent='';
    if(flow==='PENDING')flowFilters(view,[['pending','TODAS'],['none','SIN RESULTADOS'],['partial','PARCIALES']],'pending');
    if(flow==='HISTORY')flowFilters(view,[['results','RESULTADOS'],['complete','ÓRDENES COMPLETAS']],'results');
    if(flow==='CATEGORY')loadOrderCategories(view);
    if(flow==='PENDING'||flow==='HISTORY')loadOrders();
    fitOrdersViewport(view);
  }
  const navigation=window.mxmedSpecialtyNavigationV1;
  function categoryChoice(view,label,description,icon,action,primary=false) {
    const control=button('',action);control.className=primary?'vis06-category-primary':'vis06-category-secondary';
    control.append(symbol(icon),node('span',label,'vis06-category-label'));
    if(description)control.append(node('small',description));
    control.append(symbol('chevron_right','vis06-category-chevron'));
    return control;
  }
  async function loadOrderCategories(view) {
    const request=++view.categoryRequest,doctor=professional,patientId=selectedPatient();
    view.categoryStatus.textContent='Cargando categorías de estudios…';view.primaryCategories.replaceChildren();view.secondaryCategories.replaceChildren();view.lowerLinks.replaceChildren();
    if(!doctor)return;
    const context=`${doctor}:${patientId}`;view.categoryLoadingFor=context;
    try {
      if(!navigation)throw new Error('NAVIGATION_CONFIG_UNAVAILABLE');
      const profilePath=`/api/profiles/index.php/private/doctor/${encodeURIComponent(doctor)}`;
      const [data,profile]=await Promise.all([
        get(`doctors/${encodeURIComponent(doctor)}/study-types?limit=1&offset=0`),
        fetch(profilePath,{credentials:'same-origin',headers:{Accept:'application/json'}})
          .then(response=>response.ok?response.json():null).then(body=>body?.ok===true?body.data:null).catch(()=>null)
      ]);
      if(request!==view.categoryRequest||view.flow!=='CATEGORY'||doctor!==professional||patientId!==selectedPatient())return;
      const counts=Object.fromEntries((data.categories||[]).map(row=>[row.category_key,Number(row.active_count)||0]));
      const groups=navigation.config.groups;
      groups.primary.filter(group=>navigation.active(group,counts)).forEach(group=>{
        const control=categoryChoice(view,group.label,group.description,group.icon,()=>openGeneralOrder(control,group),true);
        view.primaryCategories.append(control);
      });
      const resolved=window.mxmedReviewClassification?.resolveNavigation(profile||{}) || navigation.resolve(profile||{});
      const shown=new Set();
      resolved.quick.forEach(key=>{
        const group=groups.quick[key];if(!group||shown.has(group.id)||!navigation.active(group,counts))return;
        shown.add(group.id);
        const control=categoryChoice(view,group.label,'',group.icon,()=>openGeneralOrder(control,group));
        view.secondaryCategories.append(control);
      });
      view.secondarySection.hidden=!view.secondaryCategories.children.length;
      groups.lower.filter(group=>navigation.active(group,counts)&&!shown.has(group.id)).forEach(group=>{
        const control=button(group.label,()=>openGeneralOrder(control,group));control.className='vis06-lower-link';view.lowerLinks.append(control);
      });
      const all=button('Todos los estudios',()=>openGeneralOrder(all));all.className='vis06-lower-link';view.lowerLinks.append(all);
      view.categoryStatus.textContent=view.primaryCategories.children.length?'':'No hay estudios activos en el catálogo.';
      view.categoryContext=context;
    }catch(_){if(request===view.categoryRequest)view.categoryStatus.textContent='No se pudieron cargar las categorías. Intenta de nuevo.';}
    finally{if(request===view.categoryRequest){view.categoryLoadingFor='';fitOrdersViewport(view);}}
  }
  function setOrdersFilter(view,value,reload=true) {
    view.filter.dataset.value=value;
    view.filter.querySelectorAll('button').forEach(control=>control.setAttribute('aria-pressed',String(control.dataset.filter===value)));
    if(reload)loadOrders();
  }
  function projectedSection(title,iconName) {
    const section=node('section','','vis06-projected-section');const heading=node('h5','','vis06-section-title');
    heading.append(symbol(iconName),document.createTextNode(title));section.append(heading);return section;
  }
  function fact(labelText,value) {
    const row=node('div','','vis06-fact');row.append(node('dt',labelText),node('dd',value));return row;
  }
  function emptyDetail(view,hasRows) {
    view.detailRequest++;
    view.detail.replaceChildren();view.detail.dataset.document='';view.detail.classList.add('is-placeholder');
    const inner=node('div','','vis06-detail-placeholder');inner.append(symbol('science','vis06-placeholder-icon'),
      node('h4',hasRows?'Selecciona una orden o resultado para ver los detalles.':'Selecciona una orden para ver los detalles.'),
      node('p',hasRows?'El detalle del registro seleccionado aparecerá aquí.':'Cuando solicites un estudio, podrás ver aquí la orden y sus resultados.'));
    view.detail.append(inner);
  }
  function markProjectedSelection(view) {
    view.list.querySelectorAll('[data-or-list-id]').forEach(card=>card.setAttribute('aria-pressed',String(card.dataset.orListId===view.selectedListId)));
  }
  const resultIsHistorical = row => row.result_order_relationship === 'PREDECESSOR_VERSION';
  const exactItemResults = (order,results,study) => results.filter(result=>
    Number(result.result_source_order_document_id)===Number(order.id) &&
    Array.isArray(result.related_order_item_ids) &&
    result.related_order_item_ids.includes(study.order_item_id));
  const hasExactSourceModel = row => Object.prototype.hasOwnProperty.call(row,'result_source_order_document_id');
  const resultSourceRef = row => row.result_source_order_document_uuid || row.result_source_order_document_id ||
    (!hasExactSourceModel(row) ? row.related_order_document_id : null);
  function resultSourceOrder(row,item) {
    const id=Number(row.result_source_order_document_id||(!hasExactSourceModel(row)&&row.related_order_document_id)),uuid=String(row.result_source_order_document_uuid||'');
    const order=item.kind==='ORDER'?item.order:ordersItems.find(candidate=>candidate.kind==='ORDER'&&(
      Number(candidate.order.id)===Number(row.order_lineage_head_document_id)||
      candidate.order.document_uuid===row.order_lineage_head_document_uuid||
      (!hasExactSourceModel(row)&&Number(candidate.order.id)===Number(row.related_order_document_id))))?.order;
    if(!order)return null;
    return [order,...(order.versions||[])].find(version=>Number(version.id)===id||(uuid&&version.document_uuid===uuid))||null;
  }
  async function inspectResultSource(row,trigger,view) {
    const ref=resultSourceRef(row),ownerPatient=selectedPatient(),ownerDoctor=professional;
    if(!ref)return;
    trigger.disabled=true;
    try {
      const response=await get(`doctors/${encodeURIComponent(ownerDoctor)}/documents/${encodeURIComponent(ref)}`);
      const full=response?.document||response;
      if(selectedPatient()!==ownerPatient||professional!==ownerDoctor)return;
      if(String(full?.document_id)!==String(row.result_source_order_document_uuid)||Number(full?.document_db_id)!==Number(row.result_source_order_document_id))throw new Error('La versión de origen cambió.');
      const dialog=document.createElement('dialog');dialog.className='tax03c-dialog vis06-source-dialog';
      dialog.innerHTML='<header><h4>Orden donde se solicitó</h4><button type="button" class="btn btn-link" aria-label="Cerrar">×</button></header><div data-tax03c-host></div><footer><button type="button" class="btn btn-outline-primary">Cerrar</button></footer>';
      const body=dialog.querySelector('[data-tax03c-host]'),payload=full?.content?.payload||{};
      body.append(node('h5',full.title||'Orden de estudios'),node('p',`Versión ${row.result_source_order_version}`));
      const studies=Array.isArray(payload.order_items)&&payload.order_items.length?payload.order_items.map(item=>item.study_display_name):payload.requested_studies||[];
      if(studies.length){const list=node('ul','','vis06-study-list');studies.forEach(name=>list.append(node('li',String(name))));body.append(list);}
      dialog.querySelectorAll('button').forEach(control=>control.addEventListener('click',()=>dialog.close()));
      dialog.addEventListener('close',()=>{dialog.remove();trigger.focus({preventScroll:true});},{once:true});
      document.body.append(dialog);dialog.showModal();dialog.querySelector('header button').focus();
    }catch(_){view.notice.textContent='No se pudo abrir la versión de origen de la orden.';}
    finally{trigger.disabled=false;}
  }
  function selectProjected(row,item,view,listId,trigger=null,returnStudyId=null) {
    const request=++view.detailRequest,seen=generation;
    view.selectedListId=String(listId);view.selectedRowId=String(row.id);view.lastTrigger=trigger||view.lastTrigger;
    markProjectedSelection(view);
    if(trigger)view.workspace.classList.add('is-detail-open');
    view.detail.classList.remove('is-placeholder');view.detail.replaceChildren();view.detail.dataset.document=String(row.id);
    const isOrder=item.kind==='ORDER' && row.id===item.order.id;
    const header=node('header','','vis06-projected-head');
    const iconBox=node('span','','vis06-detail-icon');iconBox.append(symbol(isOrder?orderIcon(row):'description'));
    const intro=node('div','','vis06-projected-intro');intro.append(node('h4',isOrder?orderTitle(row):(row.title||projectionLabel(row))),
      node('p',`${projectionLabel(row)}${isOrder&&orderOrigin(row)?' · '+orderOrigin(row):''}`));
    header.append(iconBox,intro);
    if(isOrder)header.append(node('span',item.result_count?resultCount(item.result_count):'Sin resultados',`vis06-count ${item.result_count?'has-results':''}`));
    if(isOrder&&['generated','signed'].includes(row.status)&&row.generated_at&&Number(row.has_successor)!==1&&row.document_uuid){
      const ownerPatient=selectedPatient(),ownerDoctor=professional;
      get(`doctors/${encodeURIComponent(ownerDoctor)}/portable-orders/${encodeURIComponent(row.document_uuid)}`).then(()=>{
        if(request!==view.detailRequest||selectedPatient()!==ownerPatient||professional!==ownerDoctor)return;
        const actions=node('div','','vis06-portable-actions');
        const print=button('Imprimir',()=>openPortableOrder(row.document_uuid));
        const pdf=button('Descargar PDF',()=>downloadPortableOrder(row.document_uuid));
        print.classList.add('vis06-order-print');pdf.classList.add('vis06-order-print');
        actions.append(print,pdf);header.append(actions);
      }).catch(()=>{});
    }
    const mobileBack=button('Volver a la lista',()=>{
      view.workspace.classList.remove('is-detail-open');
      (view.lastTrigger?.isConnected?view.lastTrigger:view.list.querySelector(`[data-or-list-id="${listId}"]`))?.focus({preventScroll:true});
    });
    mobileBack.classList.add('vis06-mobile-back');view.detail.append(mobileBack,header);
    if(!isOrder&&item.kind==='ORDER'){
      const back=button('Volver a la orden',()=>{
        selectProjected(item.order,item,view,listId);
        const study=returnStudyId?[...view.detail.querySelectorAll('.vis06-study-row')].find(row=>row.dataset.orderItemId===returnStudyId):null;
        study?.scrollIntoView({block:'nearest'});
        (study||view.detail)?.focus({preventScroll:true});
      });
      back.classList.add('vis06-return-order');view.detail.append(back);
    }
    if(isOrder){
      const register=button('REGISTRAR RESULTADO',()=>{
        if(!window.mxmedLinkedResultComposer)return;
        const owner={patientId:selectedPatient(),doctorId:professional,orderRef:row.document_uuid,orderRow:row};
        window.mxmedLinkedResultComposer.open({...owner,trigger:register,
          isCurrent:()=>selectedPatient()===owner.patientId&&professional===owner.doctorId,
          onSaved:loadOrders});
      });
      register.classList.add('vis06-register-result');if(row.status!=='voided'&&Number(row.has_successor)!==1)view.detail.append(register);
      const data=projectedSection('Datos de la orden','assignment');
      const facts=node('dl','','vis06-facts');facts.append(fact('Fecha de emisión',longDay(row.chronology_at)));
      if(orderOrigin(row))facts.append(fact('Origen',orderOrigin(row)));
      data.append(facts);view.detail.append(data);
      const structured=row.order_payload_version===2&&Array.isArray(row.order_items)?row.order_items.filter(item=>typeof item?.study_display_name==='string'&&item.study_display_name.trim()):[];
      const studies=structured.length?structured:(row.requested_studies||[]).filter(value=>typeof value==='string'&&value.trim());
      if(studies.length){const section=projectedSection('Estudios solicitados','science');section.append(node('p',coverageLabel(row.coverage_state),'vis06-coverage-summary'));
        if(structured.length){const list=node('div','','vis06-study-rows');structured.forEach(study=>{
          const studyRow=node('div','','vis06-study-row');studyRow.dataset.orderItemId=study.order_item_id;studyRow.tabIndex=-1;
          const content=node('div','','vis06-study-main');content.append(node('strong',study.study_display_name));
          const meta=node('div','','vis06-study-meta');
          meta.append(node('span',window.mxmedStudyComposer?.categories?.[study.study_category]||'Otra categoría'));
          if(Object.prototype.hasOwnProperty.call(study,'study_type_id')&&study.study_type_id===null&&study.study_type_key===null)meta.append(node('span','Personalizado','vis06-study-custom'));
          content.append(meta);
          if(typeof study.note==='string'&&study.note.trim())content.append(node('p',study.note.trim(),'vis06-study-note'));
          const status=node('div','','vis06-study-status');status.append(node('span',itemCoverageLabel(study.coverage_state),'vis06-item-coverage'));
          const matches=exactItemResults(row,item.results||[],study);
          if(matches.length===1){const result=matches[0],action=button('Ver resultado',()=>selectProjected(result,item,view,listId,action,study.order_item_id));
            action.classList.add('vis06-study-action');action.setAttribute('aria-label',`Ver resultado de ${study.study_display_name}: ${result.title||projectionLabel(result)}`);
            status.append(node('small','1 resultado'),action);
          }else if(matches.length>1){const action=button(resultCount(matches.length),()=>{
              const open=action.getAttribute('aria-expanded')!=='true';action.setAttribute('aria-expanded',String(open));choices.hidden=!open;
            });
            action.classList.add('vis06-study-action');action.setAttribute('aria-label',`Ver ${resultCount(matches.length)} de ${study.study_display_name}`);
            action.setAttribute('aria-expanded','false');
            const choices=node('div','','vis06-study-results');choices.id=`vis06-study-results-${row.id}-${study.order_item_id}`;choices.hidden=true;
            action.setAttribute('aria-controls',choices.id);
            matches.forEach(result=>{const link=button(result.title||projectionLabel(result),()=>selectProjected(result,item,view,listId,link,study.order_item_id));
              link.classList.add('vis06-study-result-link');link.setAttribute('aria-label',`Ver resultado ${result.title||projectionLabel(result)} de ${study.study_display_name}`);
              choices.append(link);});status.append(action);studyRow.append(content,status,choices);list.append(studyRow);return;
          }
          studyRow.append(content,status);list.append(studyRow);
        });section.append(list);}
        else {const list=node('ul','','vis06-study-list');studies.forEach(study=>list.append(node('li',study)));section.append(list);}
        view.detail.append(section);}
      const linked=projectedSection(`Resultados vinculados (${item.result_count})`,'description');
      if(!item.result_count){const empty=node('div','','vis06-no-results');empty.append(symbol('description'),node('strong','Aún no se han recibido resultados para esta orden.'),node('p','Los resultados se mostrarán aquí cuando estén disponibles.'));linked.append(empty);}
      else item.results.forEach(result=>{
        const entry=node('button','','vis06-result-entry');entry.type='button';entry.append(symbol('description'));
        const copy=node('span','','vis06-result-entry-copy');copy.append(node('strong',result.title||projectionLabel(result)),node('small',`Registrado ${longDay(result.created_at||result.chronology_at)}`));
        if(resultIsHistorical(result))copy.append(node('small','Versión anterior','vis06-historical-marker'));
        entry.append(copy,symbol('chevron_right'));
        entry.addEventListener('click',()=>selectProjected(result,item,view,listId,entry));linked.append(entry);
      });
      view.detail.append(linked);
    }else{
      const data=projectedSection('Detalle del resultado','description');
      const facts=node('dl','','vis06-facts');facts.append(fact('Tipo de resultado',projectionLabel(row)),fact('Registrado en expediente',longDay(row.created_at||row.chronology_at)));
      data.append(facts);view.detail.append(data);
      const origin=projectedSection('Origen','link');const standalone=item.kind==='STANDALONE_RESULT'||row.result_origin==='sin_orden';
      if(standalone)origin.append(node('p','Sin orden previa'));
      else if(resultIsHistorical(row)){
        origin.append(node('p','RESULTADO DE UNA VERSIÓN ANTERIOR','vis06-historical-marker'));
        const versions=node('dl','','vis06-facts');
        versions.append(fact('Orden donde se solicitó',`Versión ${row.result_source_order_version||'sin número'}`),
          fact('Versión vigente',`Versión ${row.order_lineage_head_version||'sin número'}`));
        origin.append(versions);
        if(resultSourceRef(row))origin.append(button('Ver orden donde se solicitó',event=>inspectResultSource(row,event.currentTarget,view)));
      }else if(!hasExactSourceModel(row)&&row.related_order_document_id){
        const linked=resultSourceOrder(row,item);
        const text=node('p',linked?`Vinculado a orden: ${orderTitle(linked)}`:'Vinculado a orden');origin.append(text);
        if(!linked)get(`doctors/${encodeURIComponent(professional)}/documents/${encodeURIComponent(row.related_order_document_id)}`).then(response=>{
          if(request!==view.detailRequest||seen!==generation)return;
          const title=String((response?.document||response)?.title||'').trim();
          if(title)text.textContent=`Vinculado a orden: ${title}`;
        }).catch(()=>{});
      }else origin.append(node('p',row.result_source_order_document_id?'Corresponde a esta orden':'Sin orden vinculada'));
      view.detail.append(origin);
      if(row.has_private_binary==1){const file=projectedSection('Archivo / contenido','description');file.append(button('Abrir archivo',()=>privateRead(row,view)));view.detail.append(file);}
      const covered=projectedSection('CORRESPONDE A','science');const ids=Array.isArray(row.related_order_item_ids)?row.related_order_item_ids:[];
      if(!ids.length)covered.append(node('p',standalone?'Este resultado no está asociado a una orden.':'Este resultado no especifica a cuáles estudios de la orden corresponde.'));
      else {
        const source=resultSourceOrder(row,item),sourceRef=resultSourceRef(row);
        const showStudies=studies=>{
          const names=ids.map(id=>studies.find(study=>study.order_item_id===id)?.study_display_name).filter(Boolean);
          if(names.length!==ids.length)return false;
          const list=node('ul','','vis06-study-list');names.forEach(name=>list.append(node('li',name)));
          covered.replaceChildren(covered.querySelector('.vis06-section-title'),list);return true;
        };
        if(!showStudies(source?.order_items||[])){
          if(sourceRef){covered.append(node('p','Cargando estudios vinculados…'));get(`doctors/${encodeURIComponent(professional)}/documents/${encodeURIComponent(sourceRef)}`).then(full=>{
            if(request!==view.detailRequest||seen!==generation)return;
            const studies=(full?.document||full)?.content?.payload?.order_items||[];
            if(!showStudies(studies))covered.replaceChildren(covered.querySelector('.vis06-section-title'),node('p','No se pudieron identificar los estudios de esta versión.'));
          }).catch(()=>{if(request===view.detailRequest&&seen===generation)covered.replaceChildren(covered.querySelector('.vis06-section-title'),node('p','No se pudo cargar el detalle de estudios.'));});}
          else covered.append(node('p','No se pudo identificar la orden donde se solicitaron estos estudios.'));
        }
      }
      view.detail.append(covered);
    }
    if(row.summary){const section=projectedSection(isOrder?'Resumen de la orden':'Resumen del resultado','notes');section.append(node('p',row.summary));view.detail.append(section);}
    if(row.versions?.length>1){const section=projectedSection('Historial de versiones','history');row.versions.forEach(version=>{
      const line=node('div','','vis06-version');line.append(node('span',`${longDay(version.created_at)} · ${version.title} · ${version.id===row.id?'Versión vigente':'Reemplazado'}`));
      if(version.has_private_binary==1)line.append(button('Abrir esta versión',()=>privateRead(version,view)));section.append(line);
    });view.detail.append(section);}
    view.detail.scrollTop=0;
    if(trigger&&matchMedia('(max-width:700px)').matches)view.detail.focus({preventScroll:true});
    get(`doctors/${encodeURIComponent(professional)}/documents/${encodeURIComponent(row.document_uuid)}`).then(full=>{
      if(request!==view.detailRequest||seen!==generation||view.detail.dataset.document!==String(row.id))return;
      const content=full?.content||{},payload=content.payload||{};
      if(isOrder){
        const facts=view.detail.querySelector('.vis06-facts');
        if(typeof payload.indication==='string'&&payload.indication.trim())facts?.append(fact('Indicaciones clínicas',payload.indication.trim()));
        if(typeof payload.priority==='string'&&payload.priority.trim())facts?.append(fact('Prioridad',payload.priority.trim()));
      }else{
        const text=typeof content.rendered_text==='string'&&content.rendered_text.trim()?content.rendered_text.trim():typeof payload.text==='string'?payload.text.trim():'';
        if(text){const section=projectedSection('Contenido','description');section.append(node('p',text));view.detail.append(section);}
        if(typeof payload.observations==='string'&&payload.observations.trim()){const section=projectedSection('Notas','notes');section.append(node('p',payload.observations.trim()));view.detail.append(section);}
      }
    }).catch(()=>{if(request===view.detailRequest&&seen===generation)view.notice.textContent='No se pudo consultar el contenido adicional del documento.';});
  }
  function renderOrders(view,append=false) {
    const priorScroll=append?view.list.scrollTop:0;
    const visibleItems=visibleOrderItems(view);
    view.list.replaceChildren();
    view.notice.textContent=`${visibleItems.length} ${view.flow==='PENDING'||currentFilter(view)==='complete'?'orden(es)':'resultado(s)'} visibles${ordersHasMore?' · Hay más registros disponibles':''}.`;
    if(!visibleItems.length){
      const empty=node('div','','vis06-orders-empty');empty.append(symbol('description','vis06-placeholder-icon'),
        node('strong',view.search.value.trim()?'No hay coincidencias con esta búsqueda.':view.flow==='PENDING'?'No hay órdenes pendientes en estos registros.':'No hay resultados u órdenes completas en estos registros.'));
      if(ordersHasMore)empty.append(node('p','Puede haber más registros en la siguiente página.'));
      view.list.append(empty);view.selectedListId='';view.selectedRowId='';emptyDetail(view,true);
      if(ordersHasMore){const more=button('Mostrar más registros',()=>loadOrders(true));more.classList.add('vis06-more');view.list.append(more);}
      fitOrdersViewport(view);return;
    }
    visibleItems.forEach(item=>{
      const row=item.order||item.result,listId=String(row.id),isOrder=item.kind==='ORDER';
      const card=node('button','','vis06-index-card');card.type='button';card.dataset.orListId=listId;card.setAttribute('aria-pressed','false');
      const iconBox=node('span','','vis06-index-icon');iconBox.append(symbol(isOrder?orderIcon(row):'description'));
      const copy=node('span','','vis06-index-copy');
      if(item.kind==='STANDALONE_RESULT'||row.result_origin==='sin_orden')copy.append(node('small','RESULTADO SIN ORDEN PREVIA','vis06-index-eyebrow'));
      else if(!isOrder&&!resultSourceRef(row))copy.append(node('small','SIN ORDEN VINCULADA','vis06-index-eyebrow'));
      copy.append(node('strong',isOrder?orderTitle(row):(row.title||projectionLabel(row))),node('small',projectionLabel(row)));
      if(isOrder){const origin=orderOrigin(row);copy.append(node('small',`${origin?origin+' · ':''}${longDay(row.chronology_at)}`));
        if(view.flow==='PENDING')copy.append(node('small',coverageLabel(row.coverage_state)));}
      else{if(resultIsHistorical(row))copy.append(node('small','Versión anterior','vis06-historical-marker'));
        else if(row.result_source_order_document_id)copy.append(node('small','Corresponde a esta orden'));
        else if(!hasExactSourceModel(row)&&row.related_order_document_id)copy.append(node('small','Vinculado a orden'));
        copy.append(node('small',`Registrado ${longDay(row.created_at||row.chronology_at)}`));}
      card.append(iconBox,copy);
      if(isOrder)card.append(node('span',item.result_count?resultCount(item.result_count):'Sin resultados',`vis06-count ${item.result_count?'has-results':''}`));
      card.append(symbol('chevron_right','vis06-index-chevron'));
      card.addEventListener('click',()=>selectProjected(row,item,view,listId,card));view.list.append(card);
    });
    if(ordersHasMore){const more=button('Mostrar más registros',()=>loadOrders(true));more.classList.add('vis06-more');view.list.append(more);}
    const previous=visibleItems.find(item=>String((item.order||item.result).id)===view.selectedListId);
    if(previous){
      if(append)markProjectedSelection(view);
      else{
        const current=previous.kind==='ORDER'&&view.selectedRowId!==String(previous.order.id)
          ? previous.results.find(result=>String(result.id)===view.selectedRowId) || previous.order
          : previous.order||previous.result;
        selectProjected(current,previous,view,view.selectedListId);
      }
    }
    else{const first=visibleItems[0];selectProjected(first.order||first.result,first,view,String((first.order||first.result).id));}
    if(append)view.list.scrollTop=priorScroll;
    fitOrdersViewport(view);
  }
  function fitOrdersViewport(view) {
    const desktop=matchMedia('(min-width:701px)').matches;
    if(!view.workspace||!desktop||!view.host.classList.contains('active')){
      view.workspace?.style.removeProperty('--vis06-orders-height');
      if(!desktop&&view.ordersWasDesktop)view.workspace?.classList.remove('is-detail-open');
      view.ordersWasDesktop=desktop;
      return;
    }
    view.ordersWasDesktop=true;
    const footer=document.querySelector('.mm-footer');const footerVisible=footer&&getComputedStyle(footer).display!=='none';
    const footerHeight=footerVisible?footer.getBoundingClientRect().height:0;
    if(view.ordersTrailing===undefined)view.ordersTrailing=footerVisible?Math.max(0,footer.getBoundingClientRect().top-view.workspace.getBoundingClientRect().bottom):24;
    const trailing=view.ordersTrailing;
    const workspaceTop=view.workspace.getBoundingClientRect().top+window.scrollY;
    const available=Math.max(140,Math.floor(window.innerHeight-workspaceTop-footerHeight-trailing));
    view.workspace.style.setProperty('--vis06-orders-height',`${available}px`);
    requestAnimationFrame(()=>view.list.classList.toggle('is-scrollable',view.list.scrollHeight>view.list.clientHeight+1));
  }
  async function loadOrders(append=false) {
    const view=views.get('orders'),id=selectedPatient();if(!view||!id||!professional||!['PENDING','HISTORY'].includes(view.flow))return;
    if(append && (ordersPageLoading || !ordersHasMore || !ordersCursor))return;
    const seen=append?ordersRequest:++ordersRequest;
    if(!append){ordersItems=[];ordersCursor=null;ordersPageLoading=false;view.list.replaceChildren();}
    if(append)ordersPageLoading=true;
    view.notice.textContent='Consultando órdenes y resultados…';
    const filter=view.flow==='PENDING'||currentFilter(view)==='complete'?'orders':'results';
    const query=new URLSearchParams({orders_results_mode:'1',limit:'25',filter,search:view.search.value.trim()});
    if(append&&ordersCursor)query.set('cursor',ordersCursor);
    try{
      const data=await get(`doctors/${encodeURIComponent(professional)}/patients/${encodeURIComponent(id)}/documents?${query}`);
      if(seen!==ordersRequest||selectedPatient()!==id)return;
      if(Array.isArray(data.order_types)){orderTypes.clear();data.order_types.forEach(type=>orderTypes.add(type));}
      if(Array.isArray(data.result_types)){resultTypes.clear();data.result_types.forEach(type=>resultTypes.add(type));}
      ordersItems=append?ordersItems.concat(data.items||[]):(data.items||[]);
      ordersCursor=data.cursor_next;ordersHasMore=!!data.has_more;renderOrders(view,append);
    }catch(e){if(seen===ordersRequest)view.notice.textContent=e.message;}
    finally{if(seen===ordersRequest)ordersPageLoading=false;}
  }
  async function load() {
    const id=selectedPatient(),seen=++generation;
    if(id!==patient)views.forEach(v=>{v.host.classList.remove('vis06-capture-open');v.back.hidden=true;v.create.hidden=false;v.search.value='';if(v.kind==='orders')setOrderFlow(v,'HOME');else v.filter.value='';});
    patient=id;professional='';rows=[];ordersRequest++;ordersItems=[];ordersCursor=null;ordersHasMore=false;ordersPageLoading=false;
    views.forEach(v=>{v.list.replaceChildren();if(v.kind==='orders'){v.selectedListId='';v.selectedRowId='';v.workspace.classList.remove('is-detail-open');emptyDetail(v,false);}else v.detail.hidden=true;v.notice.textContent=id?'Consultando registros…':'Selecciona un paciente.';});
    if(!id)return;
    try {
      const active=await get(`patients/${encodeURIComponent(id)}/encounters/active`);
      const doctor=String(active?.doctor_id || window.mxmedStore?.activeProfessionalContext?.doctor_id || window.mxmedStore?.doctor_id || '').trim();
      if(!doctor)throw new Error('No se pudo confirmar el contexto del profesional.');
      professional=doctor;
      const ordersView=views.get('orders');
      if(ordersView?.flow==='CATEGORY'&&ordersView.categoryContext!==`${doctor}:${id}`&&ordersView.categoryLoadingFor!==`${doctor}:${id}`)loadOrderCategories(ordersView);
      if(['PENDING','HISTORY'].includes(views.get('orders')?.flow))loadOrders();
      const data=await get(`doctors/${encodeURIComponent(doctor)}/patients/${encodeURIComponent(id)}/documents?limit=200`);
      if(seen!==generation||selectedPatient()!==id)return;
      rows=Array.isArray(data.items) ? data.items : [];views.forEach(v=>{if(v.kind!=='orders')render(v);});
    } catch(e) {if(seen===generation)views.forEach(v=>{if(v.kind!=='orders')v.notice.textContent=e.message;});}
  }
  for(const [kind,settings] of Object.entries(config)) {
    const host=document.getElementById(settings.target);if(!host)continue;
    const module=node('section','','vis06-module');module.setAttribute('aria-label',settings.title);
    const head=node('header','','vis06-head'),copy=node('div');copy.append(node('h3',settings.title),node('p',settings.copy));
    const create=button(settings.action,()=>{
      if(kind==='prescriptions'){host.querySelector('[data-action="tratamiento-alias-open-receta"]')?.click();return;}
      if(kind==='orders'){openGeneralOrder(create);return;}
      host.classList.add('vis06-capture-open');back.hidden=false;
    });create.className='btn btn-primary';head.append(copy);if(kind!=='orders')head.append(create);
    const back=button('Volver al listado',()=>{host.classList.remove('vis06-capture-open');back.hidden=true;create.hidden=false;create.focus();load();});back.hidden=true;
    const controls=node('div','','vis06-controls');const search=node('input');search.type='search';search.placeholder=kind==='orders'?'Buscar orden o resultado':'Buscar por nombre o descripción';search.setAttribute('aria-label',`Buscar en ${settings.title}`);search.className='form-control';
    let filter;
    if(kind==='orders'){
      filter=node('div','','vis06-segments');filter.dataset.value='all';filter.setAttribute('role','group');filter.setAttribute('aria-label','Filtrar órdenes y resultados');
      [['all','TODOS'],['orders','ÓRDENES'],['results','RESULTADOS']].forEach(([value,title])=>{
        const control=node('button',title);control.type='button';control.dataset.filter=value;control.setAttribute('aria-pressed',String(value==='all'));filter.append(control);
      });
      const searchWrap=node('label','','vis06-search-wrap');searchWrap.append(symbol('search'),search);
      const refresh=button('',()=>loadOrders());refresh.className='btn btn-outline-primary vis06-refresh';refresh.append(symbol('refresh'));refresh.setAttribute('aria-label','Actualizar órdenes y resultados');refresh.title='Actualizar órdenes y resultados';
      create.classList.add('vis06-create');create.prepend(symbol('add_circle'));controls.append(create,searchWrap,filter,refresh);
    }else{
      filter=node('select','','form-select');filter.setAttribute('aria-label',`Filtrar ${settings.title}`);
      [['','Todos los estados'],['generated','Generados'],['signed','Firmados'],['draft','Borradores'],['voided','Anulados']].forEach(([value,title])=>filter.add(new Option(title,value)));
      controls.append(search,filter,button('Actualizar',load));
    }
    const notice=node('p','','vis06-notice');notice.setAttribute('role','status');notice.setAttribute('aria-live','polite');
    const list=node('div','','vis06-list'),detail=node('section','','vis06-detail');let workspace=null;
    if(kind==='orders'){
      module.classList.add('vis06-orders');workspace=node('div','','vis06-orders-workspace');
      const index=node('div','','vis06-orders-index');index.append(notice,list);detail.tabIndex=-1;workspace.append(index,detail);module.append(head,back,controls,workspace);
    }else{detail.hidden=true;module.append(head,back,controls,notice,list,detail);}
    host.prepend(module);host.classList.add('vis06-ready');
    const view={kind,settings,host,module,headTitle:copy.querySelector('h3'),headCopy:copy.querySelector('p'),create,back,search,filter,notice,list,detail,workspace,lastTrigger:null,selectedListId:'',selectedRowId:'',detailRequest:0};views.set(kind,view);
    if(kind==='orders'){
      view.categoryRequest=0;
      view.flowBack=button('Volver a opciones',()=>setOrderFlow(view,'HOME'));
      view.flowBack.classList.add('vis06-flow-back');
      view.categoryBack=button('Volver a opciones',()=>setOrderFlow(view,'HOME'));
      view.categoryBack.classList.add('vis06-flow-back');
      module.prepend(view.flowBack);head.prepend(view.categoryBack);
      view.home=node('div','','vis06-flow-home');view.home.setAttribute('aria-label','Opciones de órdenes y resultados');
      const options=[
        ['Solicitar estudios','Solicita nuevos estudios para este paciente.','science','Elegir estudios','CATEGORY'],
        ['Revisar órdenes pendientes','Consulta solicitudes sin resultados o con resultados parciales.','assignment','Ver órdenes pendientes','PENDING'],
        ['Ver resultados e historial','Explora resultados previos y órdenes completas.','monitoring','Ver resultados','HISTORY']
      ];
      options.forEach(([title,description,icon,action,flow])=>{
        const card=button('',()=>setOrderFlow(view,flow));card.className='vis06-intent-card';
        card.append(symbol(icon),node('strong',title),node('span',description),node('span',`${action} →`,'vis06-intent-action'));
        view.home.append(card);
      });
      view.categoryScreen=node('div','','vis06-category-screen');
      view.primaryCategories=node('div','','vis06-primary-categories');view.categoryScreen.append(view.primaryCategories);
      view.secondarySection=node('section','','vis06-secondary-section');
      view.secondaryCategories=node('div','','vis06-secondary-categories');view.secondarySection.append(view.secondaryCategories);view.categoryScreen.append(view.secondarySection);
      view.lowerLinks=node('div','','vis06-lower-links');view.categoryScreen.append(view.lowerLinks);
      view.categoryStatus=node('p','','vis06-category-status');view.categoryStatus.setAttribute('role','status');view.categoryScreen.append(view.categoryStatus);
      module.append(view.home,view.categoryScreen);
      let searchTimer;
      search.addEventListener('input',()=>{clearTimeout(searchTimer);searchTimer=setTimeout(()=>loadOrders(),250);});
      filter.addEventListener('click',event=>{const control=event.target.closest('button[data-filter]');if(!control)return;clearTimeout(searchTimer);setOrdersFilter(view,control.dataset.filter);});
      setOrderFlow(view,'HOME');
      window.addEventListener('mxmed:review-classification-changed',()=>{
        if(view.flow==='CATEGORY')loadOrderCategories(view);
      });
    }else{
      search.addEventListener('input',()=>render(view));filter.addEventListener('change',()=>render(view));
    }
    if(kind==='documents'){const link=button('Ver órdenes y resultados',()=>pane.querySelector('[data-bs-target="#t-estudios"]')?.click());module.append(link);}
    if(kind==='prescriptions'){const link=button('Consultar medicación actual',()=>pane.querySelector('[data-bs-target="#t-medicamentos-longitudinal"]')?.click());module.append(link);}
    pane.querySelector(`[data-bs-target="#${settings.target}"]`)?.addEventListener('shown.bs.tab',()=>{
      if(kind==='orders')setOrderFlow(view,'HOME');load();
    });
  }
  ['patient:selected','expediente:patient_changed','expediente:patient-changed'].forEach(name=>window.addEventListener(name,load));
  window.addEventListener('mxmed:clinical-document-created',event=>{
    if(String(event.detail?.patient_id||'')===selectedPatient())load();
  });
  if(selectedPatient())load();
  window.addEventListener('resize',()=>{const view=views.get('orders');if(view)fitOrdersViewport(view);});
  new MutationObserver(()=>{if(selectedPatient()!==patient)load();}).observe(pane,{attributes:true,attributeFilter:['data-patient-id','data-active-patient-id']});
})();
