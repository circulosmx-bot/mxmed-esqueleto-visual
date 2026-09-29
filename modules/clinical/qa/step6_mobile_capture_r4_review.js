// Director-only visual states. The local review router injects this file explicitly.
// All capture writes are simulated in memory; no bearer or document is persisted.
(() => {
  const mode=new URLSearchParams(location.search).get('review_capture_r4');
  if(!['selection','qr','waiting','received','expired','change','mobile'].includes(mode))return;
  const original=window.fetch.bind(window),catalog=new Map();
  let classification={id:'clinical_image',label:'Imagen clínica',document_type:'image',icon:'image',mime_types:['image/jpeg','image/png','image/webp']};
  let token='r4-visual-only-0',status='pending',issued=0;
  const events=[];window.mxmedCaptureR4Review={mode,events,synthetic:true};
  const json=(data,status=200)=>new Response(JSON.stringify({ok:status<400,data}),{status,headers:{'Content-Type':'application/json'}});
  const expires=()=>new Date(Date.now()+(mode==='expired'?-1000:900000)).toISOString();
  window.fetch=async(input,options={})=>{
    const url=new URL(typeof input==='string'?input:input.url,location.origin),path=url.pathname,method=options.method||'GET';
    if(path.endsWith('/note-capture-tokens/classifications')){
      const response=await original(input,options);const data=await response.clone().json();for(const item of data.data?.items||[])catalog.set(item.id,item);return response;
    }
    if(path.endsWith('/note-capture-tokens')&&method==='POST'){
      const body=JSON.parse(options.body);classification=catalog.get(body.capture_classification)||classification;token='r4-visual-only-'+(++issued);status='pending';events.push('issued');
      return json({token,status,classification,expires_at:expires(),mobile_url:new URL('/public/note-capture.html?review_capture_r4=mobile&token='+token,location.origin).href},201);
    }
    if(path.includes('/note-capture-tokens/r4-visual-only-')){
      if(path.endsWith('/mobile-context'))return json({status,expires_at:expires(),classification});
      if(path.endsWith('/upload')){events.push('upload-simulated');status='uploaded';return json({status,uploaded_at:new Date().toISOString()},201);}
      if(path.endsWith('/cancel')){events.push('cancelled');status='cancelled';return json({status});}
      if(mode==='received')status='uploaded';if(mode==='expired')status='expired';
      return json({status,expires_at:expires(),document_uuid:'r4-visual-document',uploaded_at:new Date().toISOString()});
    }
    if(mode==='received'&&/\/doctors\/[^/]+\/patients\/[^/]+\/documents$/.test(path)){
      const response=await original(input,options),body=await response.json();
      if(Array.isArray(body.data?.items))body.data.items.unshift({id:'r4-visual-document',document_uuid:'r4-visual-document',document_type:'image',title:'Imagen clínica de demostración',patient_id:'p_plan02ux_review',encounter_ref_id:1016,status:'generated',event_datetime:new Date().toISOString(),has_private_binary:0,has_successor:0,payload_json:JSON.stringify({source:'step6_mobile_capture_r4',capture_classification:'clinical_image',capture_classification_label:'Imagen clínica',auto_generated:true})});
      return new Response(JSON.stringify(body),{status:response.status,headers:{'Content-Type':'application/json'}});
    }
    return original(input,options);
  };
  document.addEventListener('DOMContentLoaded',()=>{
    document.title='Revisión visual R4 · '+document.title;
    const note=document.createElement('div');note.textContent='Revisión visual · sin guardar documentos';note.style.cssText='position:fixed;top:3px;left:50%;transform:translateX(-50%);z-index:10000;background:#fff;color:#07536e;font:11px system-ui;padding:2px 8px;border-radius:4px;pointer-events:none';document.body.append(note);
    if(mode==='mobile')return;
    let attempts=0;const wait=setInterval(()=>{
      const launch=document.querySelector('[data-m7-capture-start]');
      if(++attempts>150){clearInterval(wait);return;}
      if(!launch||launch.disabled||!launch.getClientRects().length||document.querySelector('[data-m7-section="documents"]')?.getAttribute('aria-current')!=='true')return;
      clearInterval(wait);launch.click();
      if(mode==='selection')return;
      let checks=0;const ready=setInterval(()=>{
        const radio=document.querySelector('input[name="capture-classification"][value="clinical_image"]');
        if(++checks>100){clearInterval(ready);return;}if(!radio)return;
        clearInterval(ready);radio.click();document.querySelector('[data-docux-capture-generate]').click();
        if(mode==='change')setTimeout(()=>document.querySelector('[data-docux-capture-change]').click(),400);
      },100);
    },100);
  });
})();
