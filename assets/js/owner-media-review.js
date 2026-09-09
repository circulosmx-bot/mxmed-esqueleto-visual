(() => {
  const purposes={photo:['profile-photo-review-candidate.php','X-Profile-Photo-Candidate-CSRF','Foto de perfil','mxpi-photo-input'],logo:['physician-logo-review-candidate.php','X-Physician-Logo-Candidate-CSRF','Logotipo profesional',null],gallery:['gallery-review-candidate.php','X-Gallery-Review-Candidate-CSRF','Imagen de galería','fotos-input']};
  const keys={DOCTOR_PROFILE_PHOTO:'photo',PHYSICIAN_PERSONAL_LOGO:'logo',DOCTOR_GALLERY:'gallery'};
  const panels=[];let busy=false;
  async function json(url,options={}){const r=await fetch('/api/media/'+url,{credentials:'same-origin',...options});const v=await r.json();if(!r.ok||!v.ok)throw Error(v.error==='gallery_limit_reached'?'Puedes tener hasta 16 imágenes públicas y pendientes.':'No se pudo completar la acción. Intenta nuevamente.');return v.data;}
  async function candidate(key,method,body){const [route,header]=purposes[key];const current=await json(route);return json(route,{method,headers:{[header]:current.csrf_token,...(typeof body==='string'?{'Content-Type':'application/json'}:{})},body});}
  async function upload(key,file){const form=new FormData();form.append('image',file);await candidate(key,'POST',form);await refresh();}
  const element=(tag,text,cls='')=>{const e=document.createElement(tag);e.textContent=text;e.className=cls;return e;};
  async function refresh(){
    if(!panels.length)return;
    try{const [owner,batch]=await Promise.all([json('owner-review.php'),json('review-batch-submit.php')]);
      for(const panel of panels){panel.replaceChildren(element('h3','Imágenes en revisión','h6'),element('p','Tus imágenes públicas siguen visibles hasta que se aprueben los cambios.','small text-muted'));
        if(!owner.items.length)panel.append(element('p','No tienes imágenes pendientes.','small'));
        for(const item of owner.items){const key=keys[item.purpose];if(!key)continue;const row=element('div','','border rounded p-2 mb-2');const image=document.createElement('img');image.src=item.preview_url;image.alt=purposes[key][2];image.width=72;image.height=72;image.style.objectFit='contain';row.append(image,element('strong',purposes[key][2],'ms-2'),element('p',({OPEN:'Pendiente de enviar',SUBMITTED:'Enviado a revisión',NEEDS_WORK:'Necesita cambios'})[item.state],'small mb-1'));
          if(item.state==='NEEDS_WORK'){row.append(element('p','Motivo: '+item.reason,'small'));if(item.feedback)row.append(element('p',item.feedback,'small'));const replace=element('button','Reemplazar imagen','btn btn-sm btn-outline-primary');replace.type='button';replace.onclick=()=>{const input=key==='logo'?document.querySelector('#mx-dg-media-card [data-profile-logo-upload] input[type=file]'):document.getElementById(purposes[key][3]);input?.click();};row.append(replace);}
          else{const withdraw=element('button','Retirar imagen','btn btn-sm btn-outline-secondary');withdraw.type='button';withdraw.onclick=()=>perform(async()=>{await candidate(key,'DELETE',key==='gallery'?JSON.stringify({submission_id:item.id}):undefined);await refresh();});row.append(withdraw);}
          panel.append(row);
        }
        if(batch.can_submit_now){const submit=element('button','Enviar a revisión','btn btn-primary');submit.type='button';submit.onclick=()=>perform(async()=>{await json('review-batch-submit.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({csrf:batch.csrf})});await refresh();});panel.append(submit);}
      }
    }catch(e){for(const panel of panels)panel.textContent='No se pudieron cargar las imágenes pendientes. Vuelve a abrir esta sección.';}
  }
  async function perform(action){if(busy)return;busy=true;panels.forEach(p=>p.querySelectorAll('button').forEach(b=>b.disabled=true));try{await action();}catch(e){panels.forEach(p=>p.append(element('p',e.message,'text-danger')));}finally{busy=false;panels.forEach(p=>p.querySelectorAll('button').forEach(b=>b.disabled=false));}}
  window.mxmedMediaReview={upload,refresh};
  for(const anchor of ['fotos-drop','mx-dg-media-card']){const el=document.getElementById(anchor);if(el){const panel=document.createElement('section');panel.className='mx-owner-media-review my-3';panel.setAttribute('aria-live','polite');el.after(panel);panels.push(panel);}}
  document.addEventListener('shown.bs.tab',refresh);window.addEventListener('focus',refresh);refresh();
})();
