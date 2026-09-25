// LON07C: read-only longitudinal measurements. The server owns series and latest authority.
(function () {
  const pane = document.getElementById('p-expediente');
  const root = document.getElementById('lon07c-measurements');
  if (!pane || !root) return;
  const $ = selector => root.querySelector(selector);
  const patient = () => String(pane.dataset.patientId || pane.dataset.activePatientId || '').trim();
  const names = {blood_pressure:'Presión arterial',heart_rate:'Frecuencia cardiaca',respiratory_rate:'Frecuencia respiratoria',temperature:'Temperatura',oxygen_saturation:'Saturación de oxígeno',pain:'Dolor',weight:'Peso',height:'Estatura',waist:'Cintura'};
  const sources = {direct_measurement:'Medición directa',patient_report:'Referido por paciente',import:'Importación'};
  const authority = {EXPLICIT_EFFECTIVE_TIME:'Hora de medición confirmada',CAPTURE_TIME_FALLBACK:'Hora de medición no confirmada',UNKNOWN_LEGACY:'Hora de medición desconocida'};
  const status=$('[data-lon07c-status]'), error=$('[data-lon07c-error]'), content=$('[data-lon07c-content]');
  const selector=$('[data-lon07c-series]'), from=$('[data-lon07c-from]'), to=$('[data-lon07c-to]');
  let epoch=0, pointEpoch=0, pointBusy=false, historyBusy=false, selectedPatient='', series=[], pointRows=[], historyRows=[], pointCursors=[], historyCursor=null;
  const fmt=value=>value==null?'Sin valor':String(Number(value));
  const label=entry=>`${names[entry.code]||entry.code}${entry.component==='systolic'?' · sistólica':entry.component==='diastolic'?' · diastólica':''} · ${entry.unit} · ${sources[entry.source]||entry.source}`;
  const value=row=>row.code==='blood_pressure'?`${fmt(row.systolic_mm_hg)}/${fmt(row.diastolic_mm_hg)}`:fmt(row.value_numeric);
  const pageLimit=()=>window.matchMedia('(max-width:500px)').matches?'20':'50';
  const range=()=>from.value&&to.value?{from:`${from.value} 00:00:00`,to:`${to.value} 23:59:59`}:{};
  const endpoint=id=>`/api/clinical/index.php/patients/${encodeURIComponent(id)}/longitudinal/measurements`;
  function showError(message){error.textContent=message;error.classList.remove('d-none');}
  async function read(id,params={}){
    const query=new URLSearchParams(params);
    const response=await fetch(`${endpoint(id)}${query.size?'?'+query:''}`,{credentials:'same-origin',headers:{Accept:'application/json'}});
    const result=await response.json().catch(()=>null);
    if(!response.ok||result?.ok!==true)throw new Error(result?.error?.message||'No se pudieron cargar las mediciones.');
    return result.data||{};
  }
  function selectedEntries(){
    const first=series.find(entry=>entry.series_key===selector.value);
    if(!first)return [];
    if(first.code!=='blood_pressure')return [first];
    return series.filter(entry=>entry.code==='blood_pressure'&&entry.unit===first.unit&&entry.source===first.source);
  }
  function renderLatest(){
    const target=$('[data-lon07c-latest]');target.replaceChildren();
    const entries=selectedEntries();
    if(!entries.length){target.textContent='Sin mediciones comparables disponibles.';return;}
    for(const entry of entries){const row=entry.latest_comparable_observation;
      const item=document.createElement('p');const strong=document.createElement('strong');strong.textContent=`${entry.component==='systolic'?'Sistólica · ':entry.component==='diastolic'?'Diastólica · ':''}${fmt(row.value_numeric)} ${entry.unit}`;
      const detail=document.createElement('span');detail.textContent=`${row.effective_at} UTC · ${sources[entry.source]||entry.source}${row.encounter_has_amendment?' · Consulta con enmienda':''}`;
      item.append(strong,detail);target.append(item);
    }
  }
  function pointParams(entry,cursor){return {view:'points',code:entry.code,unit:entry.unit,source:entry.source,...(entry.component?{component:entry.component}:{}),...range(),...(cursor?{cursor}:{}),limit:pageLimit()};}
  async function loadPoints(more=false){
    if(more&&pointBusy)return;
    const id=patient(), ticket=epoch, entries=selectedEntries();
    if(!more){pointEpoch++;pointRows=[];pointCursors=entries.map(()=>null);}
    const pointTicket=pointEpoch;pointBusy=true;$('[data-lon07c-more-points]').disabled=true;
    if(!entries.length){pointBusy=false;$('[data-lon07c-more-points]').disabled=false;renderPoints();return;}
    try{
      const replies=await Promise.all(entries.map((entry,index)=>more&&pointCursors[index]===null?null:read(id,pointParams(entry,pointCursors[index]))));
      if(ticket!==epoch||pointTicket!==pointEpoch||id!==patient())return;
      replies.forEach((reply,index)=>{if(!reply)return;pointRows.push(...(reply.items||[]));pointCursors[index]=reply.next_cursor||null;});
      renderPoints();
    }finally{if(pointTicket===pointEpoch){pointBusy=false;$('[data-lon07c-more-points]').disabled=false;}}
  }
  function renderPoints(){
    const body=$('[data-lon07c-points-body]');body.replaceChildren();
    const rows=[...pointRows].sort((a,b)=>b.effective_at.localeCompare(a.effective_at)||b.observation_id-a.observation_id||String(a.component).localeCompare(String(b.component)));
    if(!rows.length){const tr=document.createElement('tr');const td=document.createElement('td');td.colSpan=3;td.textContent='Sin lecturas comparables en este período.';tr.append(td);body.append(tr);}
    for(const row of rows){const tr=document.createElement('tr');for(const [index,text] of [`${row.effective_at} UTC`,`${row.component==='systolic'?'Sistólica · ':row.component==='diastolic'?'Diastólica · ':''}${fmt(row.value_numeric)} ${row.unit}`,sources[row.source]||row.source].entries()){const td=document.createElement('td');td.textContent=text;td.dataset.label=['Fecha','Valor','Fuente'][index];tr.append(td);}body.append(tr);}
    $('[data-lon07c-more-points]').classList.toggle('d-none',!pointCursors.some(Boolean));
    renderChart(rows);
  }
  function renderChart(rows){
    const target=$('[data-lon07c-chart]');target.replaceChildren();
    if(!rows.length){target.textContent='Sin puntos comparables para graficar.';return;}
    const ordered=[...rows].reverse(), nums=ordered.map(row=>Number(row.value_numeric)).filter(Number.isFinite), times=ordered.map(row=>Date.parse(row.effective_at.replace(' ','T')+'Z'));
    if(!nums.length){target.textContent='Sin puntos comparables para graficar.';return;}
    const low=Math.min(...nums),high=Math.max(...nums),start=Math.min(...times),end=Math.max(...times);
    const svg=document.createElementNS('http://www.w3.org/2000/svg','svg');svg.setAttribute('viewBox','0 0 620 230');svg.setAttribute('role','img');svg.setAttribute('aria-label',`Gráfico de ${ordered.length} lecturas registradas; los valores exactos están en la tabla anterior.`);
    const line=(x1,y1,x2,y2)=>{const el=document.createElementNS(svg.namespaceURI,'line');for(const [k,v] of Object.entries({x1,y1,x2,y2}))el.setAttribute(k,String(v));el.setAttribute('stroke','#bedde3');svg.append(el);};
    line(42,184,606,184);line(42,18,42,184);
    ordered.forEach(row=>{const time=Date.parse(row.effective_at.replace(' ','T')+'Z'),n=Number(row.value_numeric);if(!Number.isFinite(n)||!Number.isFinite(time))return;
      const dot=document.createElementNS(svg.namespaceURI,'circle');dot.setAttribute('cx',String(end===start?324:42+(time-start)/(end-start)*564));dot.setAttribute('cy',String(high===low?101:184-(n-low)/(high-low)*166));dot.setAttribute('r','5');dot.setAttribute('fill',row.component==='diastolic'?'#306b9b':'#06aeb8');dot.setAttribute('stroke','#fff');dot.setAttribute('stroke-width','1.5');svg.append(dot);
    });target.append(svg);
    const note=document.createElement('p');note.textContent=`${ordered.length} lecturas de la página cargada · ${ordered[0].effective_at} a ${ordered.at(-1).effective_at} UTC${ordered.some(row=>row.component)?' · Turquesa: sistólica; azul: diastólica':''}`;target.append(note);
  }
  function renderHistory(){
    const body=$('[data-lon07c-history-body]');body.replaceChildren();
    if(!historyRows.length){const tr=document.createElement('tr');const td=document.createElement('td');td.colSpan=5;td.textContent='Sin mediciones registradas en este período.';tr.append(td);body.append(tr);}
    for(const row of historyRows){const tr=document.createElement('tr');const values=[names[row.code]||row.code,`${value(row)} ${row.unit}`,`${row.effective_at} UTC`,sources[row.source]||row.source,row.invalidated_at?`Invalidada · ${row.invalidation_reason} · ${row.invalidated_at} UTC · ${row.invalidated_by_user_id}`:row.trend_eligible?'Comparable':`${authority[row.effective_at_authority]||'Registro histórico'} · Sólo historial`];
      values.forEach((text,index)=>{const td=document.createElement('td');td.textContent=text;td.dataset.label=['Medición','Valor','Fecha','Origen','Estado'][index];if(index===4&&!row.trend_eligible)td.className='lon07c-history-only';tr.append(td);});body.append(tr);
    }
    $('[data-lon07c-more-history]').classList.toggle('d-none',!historyCursor);
  }
  async function loadHistory(more=false){
    if(more&&historyBusy)return;
    const id=patient(),ticket=epoch;
    if(!more){historyRows=[];historyCursor=null;}
    historyBusy=true;$('[data-lon07c-more-history]').disabled=true;
    try{
      const data=await read(id,{view:'history',...range(),limit:pageLimit(),...(more&&historyCursor?{cursor:historyCursor}:{})});
      if(ticket!==epoch||id!==patient())return;
      historyRows.push(...(data.items||[]));historyCursor=data.next_cursor||null;renderHistory();
    }finally{if(ticket===epoch){historyBusy=false;$('[data-lon07c-more-history]').disabled=false;}}
  }
  async function load(){
    const id=patient(),ticket=++epoch;selectedPatient=id;error.classList.add('d-none');content.classList.add('d-none');
    if(!id){status.textContent='Selecciona un paciente para ver sus mediciones.';return;}
    status.textContent='Cargando mediciones…';
    try{
      const result=await read(id,{view:'series',...range()});if(ticket!==epoch||id!==patient())return;
      series=result.series||[];selector.replaceChildren();
      const shown=new Set();for(const entry of series){if(entry.code==='blood_pressure'&&entry.component==='diastolic')continue;if(shown.has(entry.series_key))continue;shown.add(entry.series_key);const option=document.createElement('option');option.value=entry.series_key;option.textContent=entry.code==='blood_pressure'?`Presión arterial · ${entry.unit} · ${sources[entry.source]||entry.source}`:label(entry);selector.append(option);}
      if(!from.value)from.value=result.from.slice(0,10);if(!to.value)to.value=result.to.slice(0,10);
      content.classList.remove('d-none');renderLatest();await Promise.all([loadPoints(),loadHistory()]);
      if(ticket!==epoch)return;status.textContent=series.length?'Lecturas comparables e historial disponibles.':'Sin mediciones comparables disponibles. El historial puede contener otros registros.';
    }catch(failure){if(ticket!==epoch)return;showError(failure.message);status.textContent='Mediciones no disponibles.';}
  }
  selector.addEventListener('change',()=>{renderLatest();loadPoints().catch(failure=>showError(failure.message));});
  $('[data-lon07c-refresh]').addEventListener('click',load);
  $('[data-lon07c-apply]').addEventListener('click',()=>{if(from.value&&to.value&&from.value>to.value){showError('La fecha inicial debe ser anterior a la final.');return;}load();});
  $('[data-lon07c-more-points]').addEventListener('click',()=>loadPoints(true).catch(failure=>showError(failure.message)));
  $('[data-lon07c-more-history]').addEventListener('click',()=>loadHistory(true).catch(failure=>showError(failure.message)));
  for(const name of ['patient:selected','expediente:patient_changed','expediente:patient-changed'])window.addEventListener(name,()=>{from.value='';to.value='';load();});
  pane.querySelector('[data-bs-target="#t-mediciones-longitudinal"]')?.addEventListener('shown.bs.tab',load);
  new MutationObserver(()=>{if(patient()!==selectedPatient){from.value='';to.value='';load();}}).observe(pane,{attributes:true,attributeFilter:['data-patient-id','data-active-patient-id']});
})();
