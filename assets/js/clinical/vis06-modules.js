// VIS06: patient-scoped read presentation. Existing callers retain all write authority.
(function () {
  const pane = document.getElementById('p-expediente');
  if (!pane) return;
  const config = {
    orders:{target:'t-estudios',title:'Órdenes y resultados',copy:'Solicitudes y resultados vinculados a su atención de origen.',empty:'No hay órdenes ni resultados registrados.',action:'Nueva orden'},
    documents:{target:'t-consent',title:'Documentos',copy:'Archivos clínicos, informes y versiones. Las órdenes y los resultados tienen su propia sección.',empty:'No hay documentos clínicos registrados.',action:'Crear o adjuntar documento'},
    prescriptions:{target:'t-tratamiento',title:'Recetas',copy:'Historial de prescripciones. Una receta no confirma el uso actual del medicamento.',empty:'No hay recetas registradas.',action:'Emitir receta'}
  };
  const orderTypes = new Set(['order','orders','lab_order','imaging_order','orden_estudio']);
  const resultTypes = new Set(['lab_result','lab_pdf','imaging_result','external_result','external_report']);
  const prescriptionTypes = new Set(['prescription','receta']);
  const administrativeTypes = new Set(['invoice','receipt','factura','cfdi']);
  const selectedPatient = () => String(pane.dataset.patientId || pane.dataset.activePatientId || '').trim();
  const payload = row => { try { const value = typeof row.payload_json === 'string' ? JSON.parse(row.payload_json) : row.payload_json; return value && typeof value === 'object' ? value : {}; } catch (_) { return {}; } };
  const home = row => orderTypes.has(row.document_type) || (resultTypes.has(row.document_type) && (row.document_type !== 'external_report' || relation(row))) ? 'orders' : prescriptionTypes.has(row.document_type) ? 'prescriptions' : 'documents';
  const relation = row => { const p = payload(row); return String(p.related_order_document_uuid || p.related_order_document_id || p.related_document_uuid || p.related_document_id || p.related_order_id || p.context?.related_order_document_uuid || ''); };
  const day = value => { const m=String(value || '').match(/^(\d{4})-(\d{2})-(\d{2})/); return m ? `${m[3]}/${m[2]}/${m[1]}` : 'Sin fecha registrada'; };
  const label = row => ({pdf:'PDF clínico',image:'Imagen clínica',note:'Nota clínica',order:'Orden de estudio',lab_result:'Resultado de laboratorio',imaging_result:'Resultado de imagen',external_report:'Informe externo',prescription:'Receta',receta:'Receta'})[row.document_type] || 'Documento clínico';
  const node = (tag, text, cls='') => {const e=document.createElement(tag);e.textContent=text;e.className=cls;return e;};
  const button = (text, action) => {const b=node('button',text,'btn btn-outline-primary btn-sm');b.type='button';b.addEventListener('click',action);return b;};
  const views = new Map();
  let rows=[], patient='', generation=0;
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
  async function load() {
    const id=selectedPatient(),seen=++generation;
    if(id!==patient)views.forEach(v=>{v.host.classList.remove('vis06-capture-open');v.back.hidden=true;v.search.value='';v.filter.value='';});
    patient=id;rows=[];
    views.forEach(v=>{v.list.replaceChildren();v.detail.hidden=true;v.notice.textContent=id?'Consultando registros…':'Selecciona un paciente.';});
    if(!id)return;
    try {
      const active=await get(`patients/${encodeURIComponent(id)}/encounters/active`);
      const doctor=String(active?.doctor_id || window.mxmedStore?.activeProfessionalContext?.doctor_id || window.mxmedStore?.doctor_id || '').trim();
      if(!doctor)throw new Error('No se pudo confirmar el contexto del profesional.');
      const data=await get(`doctors/${encodeURIComponent(doctor)}/patients/${encodeURIComponent(id)}/documents?limit=200`);
      if(seen!==generation||selectedPatient()!==id)return;
      rows=Array.isArray(data.items) ? data.items : [];views.forEach(render);
    } catch(e) {if(seen===generation)views.forEach(v=>v.notice.textContent=e.message);}
  }
  for(const [kind,settings] of Object.entries(config)) {
    const host=document.getElementById(settings.target);if(!host)continue;
    const module=node('section','','vis06-module');module.setAttribute('aria-label',settings.title);
    const head=node('header','','vis06-head'),copy=node('div');copy.append(node('h3',settings.title),node('p',settings.copy));
    const create=button(settings.action,()=>{
      if(kind==='prescriptions'){host.querySelector('[data-action="tratamiento-alias-open-receta"]')?.click();return;}
      host.classList.add('vis06-capture-open');back.hidden=false;
      if(kind==='orders')host.querySelector('[data-est-section="solicitar"]')?.click();
    });create.className='btn btn-primary';head.append(copy,create);
    const back=button('Volver al listado',()=>{host.classList.remove('vis06-capture-open');back.hidden=true;create.focus();load();});back.hidden=true;
    const controls=node('div','','vis06-controls');const search=node('input');search.type='search';search.placeholder='Buscar por nombre o descripción';search.setAttribute('aria-label',`Buscar en ${settings.title}`);search.className='form-control';
    const filter=node('select','','form-select');filter.setAttribute('aria-label',`Filtrar ${settings.title}`);
    (kind==='orders'?[['','Órdenes y resultados'],['orders','Sólo órdenes'],['results','Sólo resultados']]:[['','Todos los estados'],['generated','Generados'],['signed','Firmados'],['draft','Borradores'],['voided','Anulados']]).forEach(([value,title])=>filter.add(new Option(title,value)));
    controls.append(search,filter,button('Actualizar',load));
    const notice=node('p','','vis06-notice');notice.setAttribute('role','status');notice.setAttribute('aria-live','polite');
    const list=node('div','','vis06-list'),detail=node('section','','vis06-detail');detail.hidden=true;
    module.append(head,back,controls,notice,list,detail);host.prepend(module);host.classList.add('vis06-ready');
    const view={kind,settings,host,back,search,filter,notice,list,detail,lastTrigger:null};views.set(kind,view);
    search.addEventListener('input',()=>render(view));filter.addEventListener('change',()=>render(view));
    if(kind==='documents'){const link=button('Ver órdenes y resultados',()=>pane.querySelector('[data-bs-target="#t-estudios"]')?.click());module.append(link);}
    if(kind==='prescriptions'){const link=button('Consultar medicación actual',()=>pane.querySelector('[data-bs-target="#t-medicamentos-longitudinal"]')?.click());module.append(link);}
    pane.querySelector(`[data-bs-target="#${settings.target}"]`)?.addEventListener('shown.bs.tab',load);
  }
  ['patient:selected','expediente:patient_changed','expediente:patient-changed'].forEach(name=>window.addEventListener(name,load));
  if(selectedPatient())load();
  new MutationObserver(()=>{if(selectedPatient()!==patient)load();}).observe(pane,{attributes:true,attributeFilter:['data-patient-id','data-active-patient-id']});
})();
