(function(){
  'use strict';
  const modal=document.getElementById('dg-signature-handoff-modal'),open=document.getElementById('dg-signature-handoff-open');if(!modal||!open)return;
  const qr=document.getElementById('dg-signature-handoff-qr'),status=document.getElementById('dg-signature-handoff-status'),expiry=document.getElementById('dg-signature-handoff-expiry');
  const endpoint='/api/media/signature-handoff.php',interval=2500;
  let active=false,id='',csrf='',deadline=0,timer=null,watchdog=null,controller=null,generation=0;
  async function request(method='GET',body=null,sessionId=''){
    const options={method,credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'}};
    if(method!=='GET'){options.headers['Content-Type']='application/json';options.headers['X-Signature-Handoff-CSRF']=csrf;options.body=JSON.stringify(body||{});}
    const abort=new AbortController();if(method==='GET'&&sessionId)controller=abort;options.signal=abort.signal;
    const timeout=setTimeout(()=>abort.abort(),10000);
    try{const response=await fetch(endpoint+(sessionId?'?id='+encodeURIComponent(sessionId):''),options);const json=await response.json();if(!response.ok||json.ok!==true){const error=Error('No fue posible consultar el código. Genera uno nuevo.');error.denied=[401,403,410].includes(response.status);throw error;}return json.data;}finally{clearTimeout(timeout);if(controller===abort)controller=null;}
  }
  function stop(){active=false;clearTimeout(timer);clearTimeout(watchdog);controller?.abort();controller=null;qr.replaceChildren();expiry.textContent='';}
  async function poll(ticket){
    if(!active||ticket!==generation)return;
    if(Date.now()>=deadline){stop();status.textContent='El código venció. Cierra esta ventana y genera uno nuevo.';return;}
    expiry.textContent='Vence en '+Math.max(0,Math.ceil((deadline-Date.now())/1000))+' segundos.';
    try{
      const result=await request('GET',null,id);if(!active||ticket!==generation)return;
      if(result.status==='COMPLETED'){stop();status.textContent='Firma guardada. La vista previa está actualizada.';await window.mxmedPhysicianSignature.refresh();return;}
      if(result.status!=='PENDING'){stop();status.textContent='El código venció o fue cancelado. Genera uno nuevo.';return;}
    }catch(error){if(!active||ticket!==generation)return;if(error.denied){stop();status.textContent=error.message;return;}status.textContent='Esperando conexión para confirmar la firma…';}
    if(active&&ticket===generation)timer=setTimeout(()=>poll(ticket),interval);
  }
  modal.addEventListener('shown.bs.modal',async()=>{
    const ticket=++generation;active=true;id='';qr.replaceChildren();status.textContent='Preparando código…';open.disabled=true;
    try{
      const init=await request();csrf=init.csrf_token;if(!active||ticket!==generation)return;
      const result=await request('POST',{});
      if(!active||ticket!==generation){request('DELETE',{id:result.id}).catch(()=>{});return;}
      id=result.id;deadline=Date.now()+Math.min(300,result.ttl_seconds)*1000;
      new QRCode(qr,{text:result.url,width:240,height:240,colorDark:'#123f54',colorLight:'#ffffff',correctLevel:QRCode.CorrectLevel.M});
      status.textContent='Esperando tu firma…';expiry.textContent='Vence en '+Math.min(300,result.ttl_seconds)+' segundos.';
      watchdog=setTimeout(()=>{if(active&&ticket===generation){stop();status.textContent='El código venció. Cierra esta ventana y genera uno nuevo.';}},Math.max(0,deadline-Date.now()));
      timer=setTimeout(()=>poll(ticket),interval);
    }catch(error){if(active&&ticket===generation){stop();status.textContent=error.message;}}
    finally{open.disabled=false;}
  });
  modal.addEventListener('hide.bs.modal',()=>{const previous=id,pending=active;generation++;stop();id='';if(previous&&pending)request('DELETE',{id:previous}).catch(()=>{});open.focus();});
  open.addEventListener('click',()=>bootstrap.Modal.getOrCreateInstance(modal).show());
  window.addEventListener('pagehide',()=>{generation++;stop();});
})();
