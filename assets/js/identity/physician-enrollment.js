(() => {
  'use strict';
  const boot=document.getElementById('physician-onboarding-state');
  if(!boot)return;
  const state=JSON.parse(boot.textContent||'{}'),e=state.enrollment;
  if(!e)return;
  boot.textContent='';
  const root=document.querySelector('[data-onboarding]'),feedback=root.querySelector('[data-onboarding-feedback]');
  let busy=false,locked=false,retry=null;
  const clear=()=>{locked=true;e.csrf_token='';e.ticket=null;retry=null;if(e.application?.intake)e.application.intake={};root.querySelectorAll('[data-enrollment-intake] input').forEach(input=>{input.value='';input.removeAttribute('value');});root.querySelectorAll('fieldset,button').forEach(control=>control.disabled=true);};
  const send=async(action,extra={})=>{
    const response=await fetch('/api/identity/physician-enrollment.php',{method:'POST',credentials:'same-origin',cache:'no-store',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-Token':e.csrf_token},body:JSON.stringify({action,csrf_token:e.csrf_token,...extra})});
    const body=await response.json().catch(()=>({}));
    if(!response.ok||body.ok!==true){if([401,403].includes(response.status))clear();throw Error(body.message||'No pudimos confirmar la operación. Actualiza el estado.');}
    return body.data;
  };
  const run=async(fn)=>{if(busy||locked)return;busy=true;feedback.textContent='Procesando…';try{await fn();}catch(error){feedback.textContent=error.message;}finally{busy=false;}};
  root.querySelector('[data-onboarding-refresh]').addEventListener('click',()=>window.location.reload());
  root.querySelector('[data-enrollment-start]')?.addEventListener('click',()=>run(async()=>{const data=await send('start',{ticket:e.ticket});window.location.assign(data.return_path);}));
  root.querySelector('[data-enrollment-bind]')?.addEventListener('click',()=>run(async()=>{await send('bind',{application_id:e.application.application_id});window.location.reload();}));
  document.querySelector('[data-onboarding-logout]')?.addEventListener('click',()=>run(async()=>{
    const response=await fetch('/api/identity/index.php/logout',{method:'POST',credentials:'same-origin',cache:'no-store',headers:{'Content-Type':'application/json','X-CSRF-Token':e.csrf_token},body:JSON.stringify({csrf_token:e.csrf_token})});
    const result=await response.json().catch(()=>({}));if(!response.ok||result.ok!==true||result.state!=='LOGOUT_SUCCESS')throw Error('No pudimos confirmar el cierre de sesión.');
    const signal={type:'logout',at:Date.now()};try{const channel=new BroadcastChannel('mxmed-session');channel.postMessage(signal);channel.close();localStorage.setItem('mxmed.session.signal',JSON.stringify(signal));}catch(_){}
    clear();window.location.assign('/public/identity/acceso.php?next='+encodeURIComponent(e.return_path||'/public/identity/perfil-medico.php?enrollment=resume'));
  }));
  root.querySelector('[data-enrollment-intake]')?.addEventListener('submit',event=>{
    event.preventDefault();const form=event.currentTarget,action=event.submitter?.value==='submit'?'submit':'save';
    run(async()=>{const intake=Object.fromEntries([...form.querySelectorAll('input[name]')].map(input=>[input.name,input.value]));const serialized=JSON.stringify([action,intake]);
      // Keep the command only in this live request context for ambiguous-response retry.
      if(!retry||retry.serialized!==serialized)retry={serialized,command:crypto.randomUUID()};
      const result=await send(action,{application_id:e.application.application_id,expected_version:e.application.version,command_id:retry.command,intake});
      e.application.version=result.version;retry=null;feedback.textContent=action==='submit'?'Información inicial enviada. Los siguientes pasos de verificación y revisión están pendientes.':'Información guardada en tu solicitud pendiente.';
      if(action==='submit')form.querySelector('fieldset').disabled=true;
    });
  });
  if(e.authenticated){
    const signal=value=>{if(['logout','logged-out','expired','revoked','authenticated'].includes(value?.type)){clear();feedback.textContent='La sesión cambió. Inicia sesión o actualiza la página para retomar tu solicitud.';}};
    try{const channel=new BroadcastChannel('mxmed-session');channel.onmessage=event=>signal(event.data);}catch(_){}
    window.addEventListener('storage',event=>{if(event.key==='mxmed.session.signal'){try{signal(JSON.parse(event.newValue));}catch(_){}}});
    window.addEventListener('pagehide',clear);window.addEventListener('pageshow',event=>{if(event.persisted){clear();feedback.textContent='Actualiza la página para retomar tu solicitud de forma segura.';}});
    let checking=false;
    const observe=async()=>{if(checking||locked)return;checking=true;try{const response=await fetch('/api/identity/index.php/current-session',{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'}});const dto=await response.json();if(!response.ok||dto.authenticated!==true){clear();feedback.textContent='Tu sesión terminó. Inicia sesión para retomar la solicitud.';}}catch(_){clear();feedback.textContent='No pudimos validar tu sesión. Inicia sesión para continuar de forma segura.';}finally{checking=false;}};
    window.addEventListener('focus',observe);document.addEventListener('visibilitychange',()=>{if(!document.hidden)observe();});setInterval(observe,60000);
    const remaining=state.expires_at*1000-Date.now();if(remaining>0)setTimeout(()=>{clear();feedback.textContent='Tu sesión terminó. Inicia sesión para retomar la solicitud.';},Math.min(remaining,2147483647));
  }
})();
