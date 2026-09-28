// Synthetic composition and reuse simulation, injected solely by the guarded Director router.
// Real clinical writes are blocked; simulated reuse/void stay in this browser session only.
(() => {
  const params=new URLSearchParams(location.search);
  if(!['127.0.0.1','localhost','[::1]'].includes(location.hostname)
    || params.get('review_step2_visual')!=='r4'
    || params.get('review_patient')!=='plan02ux'
    || params.get('review_encounter')!=='open')return;
  const patientId='p_plan02ux_review',encounterId=1016;
  const count=Math.max(0,Math.min(9,Number(params.get('review_current_values'))||0));
  const catalog=[
    ['blood_pressure','mmHg',null,128,76],['heart_rate','bpm',72],
    ['respiratory_rate','rpm',18],['temperature','°C',37],
    ['oxygen_saturation','%',98],['pain','score',3],
    ['weight','kg',78.2],['height','cm',180],['waist','cm',100]
  ];
  const row=(item,index,prior=false)=>({
    observation_id:(prior?98000:99000)+index,encounter_id:prior?1014:encounterId,
    encounter_key:prior?'enc:1014':'enc:1016',code:item[0],unit:item[1],
    value_numeric:item[2]??null,systolic_mm_hg:item[3]??null,diastolic_mm_hg:item[4]??null,
    source:'direct_measurement',effective_at:prior?'2026-09-20 18:00:00':'2026-09-27 20:22:00',
    recorded_at:prior?'2026-09-20 18:00:00':'2026-09-27 20:22:00',
    effective_at_authority:'EXPLICIT_EFFECTIVE_TIME',
    provenance_json:JSON.stringify(prior?{}:{capture_time_mode:'SERVER_AT_SAVE'}),
    invalidated_at:null,row_version:1
  });
  // Catalog keys/units mirror the canonical Step 2 catalog; these are review examples,
  // never demographic defaults or clinical reference ranges.
  const priorRows=[
    ['blood_pressure','mmHg',null,110,70],['heart_rate','bpm',72],
    ['respiratory_rate','rpm',16],['temperature','°C',36.7],
    ['oxygen_saturation','%',98],['pain','score',0],
    ['weight','kg',68],['height','cm',162],['waist','cm',82]
  ].map((item,index)=>row(item,index,true));
  const storageId=`mxmed.director.step2.r4:${patientId}:${encounterId}:${count}`;
  let simulated=[];
  try{simulated=JSON.parse(sessionStorage.getItem(storageId)||'[]');}catch(_){}
  let voided=[];
  try{voided=JSON.parse(sessionStorage.getItem(storageId+':voids')||'[]');}catch(_){}
  const currentRows=[...catalog.slice(0,count).map((item,index)=>row(item,index)),...simulated.map(item=>item.row)]
    .map(item=>voided.find(saved=>saved.observation_id===item.observation_id)||item);
  currentRows.forEach(item=>{item.provenance=JSON.parse(item.provenance_json||'{}');});
  const originalFetch=window.fetch.bind(window);
  const json=(payload,status=200)=>new Response(JSON.stringify(payload),{status,headers:{'Content-Type':'application/json','Cache-Control':'no-store'}});
  window.fetch=async(input,init)=>{
    const url=new URL(typeof input==='string'?input:input.url,location.href);
    const method=String(init?.method||input?.method||'GET').toUpperCase();
    if(url.origin!==location.origin)return originalFetch(input,init);
    const voidMatch=decodeURIComponent(url.pathname).match(new RegExp(`^/api/clinical/index.php/encounters/enc:${encounterId}/observations/(\\d+)/void$`));
    if(method==='POST'&&voidMatch){
      const command=JSON.parse(init.body||'{}'),target=currentRows.find(item=>item.observation_id===Number(voidMatch[1]));
      if(command.patient_id!==patientId)return json({ok:false,error:{code:'PATIENT_CONTEXT_MISMATCH'}},409);
      if(!target)return json({ok:false,error:{code:'OBSERVATION_NOT_FOUND'}},404);
      if(!Number.isInteger(command.row_version)||command.row_version!==target.row_version||target.invalidated_at)
        return json({ok:false,error:{code:'VERSION_CONFLICT'}},409);
      Object.assign(target,{invalidated_at:new Date().toISOString().slice(0,19).replace('T',' '),
        invalidated_by_user_id:'director-review-browser',invalidation_reason:'Captura errónea',row_version:target.row_version+1});
      voided.push({...target});
      try{sessionStorage.setItem(storageId+':voids',JSON.stringify(voided));}catch(_){}
      return json({ok:true,data:target});
    }
    if(method==='POST'&&decodeURIComponent(url.pathname)===`/api/clinical/index.php/encounters/enc:${encounterId}/observations/reuse`){
      const command=JSON.parse(init.body||'{}'),source=priorRows.find(item=>item.observation_id===command.source_observation_id);
      const key=new Headers(init.headers).get('Idempotency-Key'),replay=simulated.find(item=>item.key===key);
      if(replay)return json({ok:true,data:replay.row},200);
      if(!source)return json({ok:false,error:{code:'PRIOR_OBSERVATION_NOT_REUSABLE'}},409);
      if(currentRows.some(item=>!item.invalidated_at&&item.code===source.code))return json({ok:false,error:{code:'MEASUREMENT_TYPE_ALREADY_PRESENT'}},409);
      const recorded=new Date().toISOString().slice(0,19).replace('T',' ');
      const provenance={reuse_mode:'PRIOR_OBSERVATION',source_observation_id:source.observation_id,source_encounter_id:source.encounter_id,source_encounter_key:source.encounter_key,source_effective_at:source.effective_at,source_recorded_at:source.recorded_at,reused_at:recorded,source_provenance:{}};
      const reused={...source,observation_id:99500+simulated.length,encounter_id:encounterId,encounter_key:`enc:${encounterId}`,recorded_at:recorded,provenance,provenance_json:JSON.stringify(provenance)};
      simulated.push({key,row:reused});currentRows.push(reused);
      try{sessionStorage.setItem(storageId,JSON.stringify(simulated));}catch(_){}
      return json({ok:true,data:reused},201);
    }
    if((url.pathname.startsWith('/api/clinical/')||url.pathname.startsWith('/api/agenda/'))
      &&method!=='GET'&&url.pathname!=='/api/clinical/index.php/patient-id/resolve')
      return json({ok:false,error:{code:'DIRECTOR_VISUAL_REVIEW_READ_ONLY',message:'Esta vista de revisión es de sólo lectura.'}},403);
    if(method==='GET'&&url.pathname===`/api/clinical/index.php/patients/${patientId}/longitudinal/measurements`
      &&url.searchParams.get('view')==='prior'&&url.searchParams.get('exclude_encounter_id')===String(encounterId))
      return json({ok:true,data:{items:priorRows}});
    const response=await originalFetch(input,init);
    if(method!=='GET'||!response.ok||url.pathname!=='/api/clinical/index.php/encounters/enc%3A1016')return response;
    const payload=await response.clone().json().catch(()=>null);
    if(payload?.ok!==true||payload?.data?.patient_id!==patientId)return response;
    payload.data.observations=currentRows.filter(item=>!item.invalidated_at);
    payload.data.invalidated_observations=currentRows.filter(item=>item.invalidated_at);
    return json(payload,response.status);
  };
  window.mxmedDirectorStep2VisualFixture=Object.freeze({patientId,encounterId,readOnly:true,syntheticPreviousValues:true,simulatedReuseOnly:true,simulatedVoidOnly:true,currentCount:count,syntheticTypeCount:priorRows.length});
  window.addEventListener('load',()=>{
    const openStep=()=>{
      const step=document.querySelector('[data-m7-section="measurements"]');
      if(document.querySelector('[data-m7-body]')?.dataset.encounterId===String(encounterId)&&step&&!step.disabled
        &&document.querySelectorAll('[data-vis29-prior] .vis29-reading').length===priorRows.length){
        step.click();return true;
      }
      return false;
    };
    const observer=new MutationObserver(()=>{if(openStep())observer.disconnect();});
    if(!openStep())observer.observe(document.body,{subtree:true,childList:true,attributes:true,attributeFilter:['data-encounter-id','disabled']});
  });
})();
