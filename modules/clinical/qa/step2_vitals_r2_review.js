// Read-only synthetic composition, injected solely by the guarded Director router.
(() => {
  const params=new URLSearchParams(location.search);
  if(!['127.0.0.1','localhost','[::1]'].includes(location.hostname)
    || params.get('review_step2_visual')!=='r2'
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
  const priorRows=[['blood_pressure','mmHg',null,120,80],['temperature','°C',36.5],['weight','kg',78],['oxygen_saturation','%',98]].map((item,index)=>row(item,index,true));
  const currentRows=catalog.slice(0,count).map((item,index)=>row(item,index));
  const originalFetch=window.fetch.bind(window);
  const json=(payload,status=200)=>new Response(JSON.stringify(payload),{status,headers:{'Content-Type':'application/json','Cache-Control':'no-store'}});
  window.fetch=async(input,init)=>{
    const url=new URL(typeof input==='string'?input:input.url,location.href);
    const method=String(init?.method||input?.method||'GET').toUpperCase();
    if(url.origin!==location.origin)return originalFetch(input,init);
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
    payload.data.observations=currentRows;
    return json(payload,response.status);
  };
  window.mxmedDirectorStep2VisualFixture=Object.freeze({patientId,encounterId,readOnly:true,syntheticPreviousValues:true,currentCount:count});
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
