(function(){
  'use strict';
  let token='',active=false,busy=false,drawing=false,ink=false,generation=0;
  const editor=document.getElementById('signature-device-editor'),status=document.getElementById('signature-device-status'),pad=document.getElementById('signature-device-pad'),ctx=pad.getContext('2d');
  ctx.strokeStyle='#123f56';ctx.lineWidth=3;ctx.lineCap='round';ctx.lineJoin='round';
  function point(event){const r=pad.getBoundingClientRect();return [(event.clientX-r.left)*pad.width/r.width,(event.clientY-r.top)*pad.height/r.height];}
  function clear(){ctx.clearRect(0,0,pad.width,pad.height);ink=false;drawing=false;}
  pad.addEventListener('pointerdown',event=>{if(!active||busy||event.button>0)return;event.preventDefault();pad.setPointerCapture(event.pointerId);drawing=true;const [x,y]=point(event);ctx.beginPath();ctx.moveTo(x,y);});
  pad.addEventListener('pointermove',event=>{if(!drawing||busy)return;const samples=event.getCoalescedEvents?.();for(const sample of samples?.length?samples:[event]){const [x,y]=point(sample);ctx.lineTo(x,y);ctx.stroke();ink=true;}});
  for(const type of ['pointerup','pointercancel','lostpointercapture'])pad.addEventListener(type,()=>{drawing=false;});
  async function request(bearer,image){const response=await fetch('/api/media/signature-handoff-device.php',{method:'POST',credentials:'omit',cache:'no-store',referrerPolicy:'no-referrer',headers:{'Content-Type':'application/json'},body:JSON.stringify(image?{token:bearer,image_data:image}:{token:bearer})});const json=await response.json();if(!response.ok||json.ok!==true){const error=Error(response.status===410?'El enlace venció o ya fue utilizado. Solicita un nuevo código.':'No fue posible guardar la firma. Intenta nuevamente.');error.consumed=response.status===410;throw error;}return json.data;}
  async function exitFullscreen(){if(document.fullscreenElement)try{await document.exitFullscreen();}catch{}}
  function finish(message){generation++;active=false;token='';drawing=false;editor.hidden=true;clear();status.textContent=message;exitFullscreen();}
  document.getElementById('signature-device-clear').addEventListener('click',clear);
  // Local cancel leaves the server session pending only until its original TTL.
  document.getElementById('signature-device-cancel').addEventListener('click',()=>finish('Firma cancelada; tu firma vigente no cambió.'));
  document.getElementById('signature-device-fullscreen').addEventListener('click',async()=>{try{if(!editor.requestFullscreen)throw Error();await editor.requestFullscreen();}catch{editor.classList.add('fullscreen-fallback');pad.scrollIntoView({block:'center'});}pad.focus();});
  document.getElementById('signature-device-save').addEventListener('click',async()=>{if(!active||busy)return;if(!ink){status.textContent='Dibuja tu firma antes de guardarla.';return;}const ticket=generation;busy=true;drawing=false;editor.querySelectorAll('button').forEach(b=>b.disabled=true);status.textContent='Guardando firma…';try{await request(token,pad.toDataURL('image/png'));if(ticket!==generation)return;finish('Firma guardada. Puedes volver a tu computadora.');}catch(error){if(ticket!==generation)return;if(error.consumed)finish(error.message);else status.textContent=error.message;}finally{busy=false;editor.querySelectorAll('button').forEach(b=>b.disabled=false);}});
  async function load(){
    const ticket=++generation;const bearer=location.hash.slice(1);token=bearer;active=false;drawing=false;clear();editor.hidden=true;editor.classList.remove('fullscreen-fallback');status.textContent='Validando enlace…';history.replaceState(null,'',location.pathname);
    if(!/^[A-Za-z0-9_-]{43}$/.test(bearer)){finish('Enlace no válido. Solicita un nuevo código.');return;}
    try{await request(bearer);if(ticket!==generation)return;active=true;editor.hidden=false;status.textContent='El enlace permite guardar una sola firma durante cinco minutos.';}catch(error){if(ticket===generation)finish(error.message);}
  }
  window.addEventListener('hashchange',load);
  load();
})();
