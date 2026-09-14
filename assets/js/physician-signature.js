// SIG03A: canonical account-scoped signature, never browser signature promotion.
(function(){
  'use strict';
  const endpoint='/api/media/physician-signature.php';
  let image='',csrf='',owner='',generation=0;
  async function request(method='GET',data){
    const ticket=++generation;
    const options={method,credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'}};
    if(method!=='GET')options.headers['X-Signature-CSRF']=csrf;
    if(data){options.headers['Content-Type']='application/json';options.body=JSON.stringify({image_data:data});}
    try {
    const response=await fetch(endpoint,options);const json=await response.json();
    if(!response.ok||json.ok!==true)throw Error(json.message||'No fue posible cargar la firma de tu cuenta.');
    if(ticket!==generation)return;
    owner=json.data.owner_scope;csrf=json.data.csrf_token;image=json.data.signature?.image_data||'';
    document.dispatchEvent(new Event('mxmed:signature-changed'));
    }catch(error){if(method==='GET'&&ticket===generation){image='';owner='';csrf='';document.dispatchEvent(new Event('mxmed:signature-changed'));}throw error;}
  }
  const authority=Object.freeze({read:()=>owner?image:'',refresh:()=>request(),save:async data=>{await request();await request('POST',data);},delete:async()=>{await request();await request('DELETE');}});
  window.mxmedPhysicianSignature=authority;
  const card=document.getElementById('dg-signature-card');if(!card)return;
  const pad=card.querySelector('#dg-signature-pad'),preview=card.querySelector('#dg-signature-preview'),editor=card.querySelector('#dg-signature-editor'),status=card.querySelector('#dg-signature-feedback');
  const context=pad.getContext('2d');let drawing=false,ink=false,busy=false;
  function clear(){context.clearRect(0,0,pad.width,pad.height);ink=false;}
  function render(){const current=authority.read();preview.hidden=!current;if(current)preview.src=current;else preview.removeAttribute('src');card.querySelector('#dg-signature-change [data-dg-button-label]').textContent=current?'Cambiar firma':'Crear firma';card.querySelector('#dg-signature-delete').hidden=!current;}
  function point(event){const r=pad.getBoundingClientRect();return [(event.clientX-r.left)*pad.width/r.width,(event.clientY-r.top)*pad.height/r.height];}
  pad.addEventListener('pointerdown',event=>{if(busy||event.button>0)return;event.preventDefault();pad.setPointerCapture(event.pointerId);drawing=true;const [x,y]=point(event);context.beginPath();context.moveTo(x,y);});
  pad.addEventListener('pointermove',event=>{if(!drawing)return;const samples=event.getCoalescedEvents?.();for(const sample of samples?.length?samples:[event]){const [x,y]=point(sample);context.lineTo(x,y);context.stroke();ink=true;}});
  for(const type of ['pointerup','pointercancel','lostpointercapture'])pad.addEventListener(type,()=>{drawing=false;});
  context.strokeStyle='#123f56';context.lineWidth=3;context.lineCap='round';context.lineJoin='round';
  card.querySelector('#dg-signature-change').addEventListener('click',()=>{clear();editor.hidden=false;pad.focus();});
  card.querySelector('#dg-signature-clear').addEventListener('click',clear);
  card.querySelector('#dg-signature-cancel').addEventListener('click',()=>{drawing=false;editor.hidden=true;clear();card.querySelector('#dg-signature-change').focus();});
  async function perform(action){if(busy)return;busy=true;card.querySelectorAll('button').forEach(b=>b.disabled=true);status.textContent='Guardando firma…';try{await action();editor.hidden=true;status.textContent='Firma actualizada.';}catch(error){status.textContent=error.message;}finally{busy=false;card.querySelectorAll('button').forEach(b=>b.disabled=false);render();}}
  card.querySelector('#dg-signature-save').addEventListener('click',()=>{if(!ink){status.textContent='Dibuja tu firma antes de guardarla.';return;}perform(()=>authority.save(pad.toDataURL('image/png')));});
  card.querySelector('#dg-signature-delete').addEventListener('click',()=>{if(confirm('¿Eliminar la firma registrada en tu cuenta?'))perform(()=>authority.delete());});
  document.addEventListener('mxmed:signature-changed',render);
  document.getElementById('t-info-datos-tab')?.addEventListener('shown.bs.tab',()=>authority.refresh().catch(error=>{status.textContent=error.message;}));
  render();authority.refresh().catch(error=>{status.textContent=error.message;});
})();
