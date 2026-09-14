(() => {
  const box=document.getElementById('mxpi-photo-control');
  if(!box)return;
  const input=document.getElementById('mxpi-photo-input'), select=document.getElementById('mxpi-photo-select'), remove=document.getElementById('mxpi-photo-remove'), preview=document.getElementById('mxpi-photo-preview'), status=document.getElementById('mxpi-photo-status');
  const endpoint='/api/media/profile-photo.php';
  const targetModal=document.getElementById('mxpi-photo-target-modal');
  let token='',photo=null,busy=false,uploadTarget=null,choosing=false;
  const currentCandidate=()=>{
    const row=box.querySelector('[data-review-candidate="photo"]');
    return row?{id:row.dataset.reviewId,state:row.querySelector('[data-review-state]').dataset.reviewState,label:row.querySelector('[data-review-state]').textContent}:null;
  };
  const updateActionLabel=()=>{
    const label=select.querySelector('span');
    const candidate=currentCandidate();
    if(label)label.textContent=photo||candidate?'Cambiar foto':'Agregar foto';
    remove.hidden=!photo&&(!candidate||candidate.state==='NEEDS_WORK');
  };
  new MutationObserver(updateActionLabel).observe(box,{childList:true});
  const genericAvatar=()=>{
    const value=String(document.body?.dataset?.profileGender||'').trim().toLowerCase();
    if(['f','female','feminine','mujer','femenino'].includes(value))return '/assets/img/doctors/avatars/dr-female.png';
    return '/assets/img/doctors/avatars/dr-male.png';
  };
  const render=()=>{
    const image=preview.querySelector('img');
    preview.hidden=false;
    image.src=photo?.public_url||genericAvatar();
    image.alt=photo?'Fotografía de perfil':'Imagen genérica de perfil médico';
    image.dataset.avatarKind=photo?'public':'generic';
    updateActionLabel();
  };
  async function request(method='GET',body=null){
    const response=await fetch(endpoint,{method,body,credentials:'same-origin',headers:method==='GET'?{}:{'X-Profile-Photo-CSRF':token}});
    const result=await response.json();
    if(!response.ok||!result.ok)throw Error(result.message||'No se pudo guardar la fotografía. Intenta nuevamente.');
    token=result.data.csrf_token;photo=result.data.photo;render();
  }
  async function candidateRequest(method='GET',csrf=''){
    const response=await fetch('/api/media/profile-photo-review-candidate.php',{method,credentials:'same-origin',headers:method==='GET'?{}:{'X-Profile-Photo-Candidate-CSRF':csrf}});
    const result=await response.json();
    if(!response.ok||!result.ok)throw Error('No se pudo completar la acción sobre la foto pendiente. Intenta nuevamente.');
    return result.data;
  }
  async function chooseTarget(action){
    if(busy||choosing)return null;
    const candidate=currentCandidate();
    const context={target:photo?'approved':'candidate',approvedId:photo?.media_id||null,candidateId:candidate?.id||null};
    if(!photo||!candidate)return context;
    if(document.querySelector('.modal.show,.modal-backdrop'))return null;
    choosing=true;
    const approvedChoice=targetModal.querySelector('[data-photo-target="approved"]');
    const candidateChoice=targetModal.querySelector('[data-photo-target="candidate"]');
    const stateLabel=candidate.label;
    targetModal.querySelector('.modal-title').textContent=action==='change'?'¿Qué foto quieres cambiar?':'¿Qué foto quieres eliminar?';
    candidateChoice.querySelector('strong').textContent=candidate.state==='OPEN'?'Foto pendiente de enviar':candidate.state==='NEEDS_WORK'?'Foto que requiere cambios':'Foto en revisión';
    candidateChoice.querySelector('[data-photo-target-state]').textContent=stateLabel;
    candidateChoice.querySelector('[data-photo-target-copy]').textContent=candidate.state==='NEEDS_WORK'?'Es la fotografía para la que se solicitaron cambios.':'Es la nueva fotografía pendiente de aprobación.';
    const candidateUnavailable=action==='delete'&&candidate.state==='NEEDS_WORK';
    targetModal.querySelector('[data-photo-target-note]').textContent=action==='change'?'El cambio requiere revisión y sustituye la propuesta actual; la foto publicada permanece visible.':candidateUnavailable?'La versión que requiere cambios admite reemplazo, pero no retirada.':'Se eliminará sólo la versión que elijas.';
    return new Promise(resolve=>{
      let selected=null;
      const modal=bootstrap.Modal.getOrCreateInstance(targetModal);
      const selectChoice=event=>{selected=event.currentTarget.dataset.photoTarget;approvedChoice.disabled=candidateChoice.disabled=true;modal.hide();};
      const focusChoice=()=>{approvedChoice.disabled=false;candidateChoice.disabled=candidateUnavailable;approvedChoice.focus();};
      const finish=()=>{
        approvedChoice.removeEventListener('click',selectChoice);candidateChoice.removeEventListener('click',selectChoice);
        targetModal.removeEventListener('shown.bs.modal',focusChoice);
        choosing=false;(action==='change'?select:remove).focus();
        resolve(selected?{...context,target:selected}:null);
      };
      approvedChoice.disabled=candidateChoice.disabled=true;
      approvedChoice.addEventListener('click',selectChoice);candidateChoice.addEventListener('click',selectChoice);
      targetModal.addEventListener('shown.bs.modal',focusChoice,{once:true});
      targetModal.addEventListener('hidden.bs.modal',finish,{once:true});
      modal.show();
    });
  }
  async function validateTarget(context,action){
    await request();
    const candidate=await candidateRequest();
    const id=candidate.candidate?.submission_id||null;
    if((context.target==='approved'&&(photo?.media_id||null)!==context.approvedId)||id!==context.candidateId||
      (action==='delete'&&context.target==='candidate'&&candidate.candidate?.review_status!=='PENDING_REVIEW')){
      await window.mxmedMediaReview.refresh();
      throw Error('Las fotos cambiaron. Revisa las versiones actuales y vuelve a elegir.');
    }
    return candidate.csrf_token;
  }
  async function run(action){
    if(busy)return;
    busy=true;box.setAttribute('aria-busy','true');input.disabled=select.disabled=remove.disabled=true;
    try{await action();}catch(e){render();status.textContent=e.message;}
    finally{busy=false;box.removeAttribute('aria-busy');input.disabled=select.disabled=remove.disabled=false;input.value='';}
  }
  async function upload(file,context=null){
    if(!file)return;
    context=context||await chooseTarget('change');
    if(!context)return;
    await run(async()=>{
      if(!['image/jpeg','image/png','image/webp'].includes(file.type))throw Error('Selecciona una imagen JPG, PNG o WebP.');
      if(file.size>10485760)throw Error('La fotografía supera el máximo de 10 MiB.');
      await validateTarget(context,'change');
      status.textContent='Subiendo…';
      await window.mxmedMediaReview.upload('photo',file);
      status.textContent='Pendiente de enviar';
    });
  }
  select.addEventListener('click',async()=>{
    uploadTarget=await chooseTarget('change');
    if(uploadTarget)input.click();
  });
  input.addEventListener('change',()=>{const context=uploadTarget;uploadTarget=null;upload(input.files?.[0],context);});
  box.addEventListener('dragover',e=>e.preventDefault());
  box.addEventListener('drop',e=>{e.preventDefault();upload(e.dataTransfer?.files?.[0]);});
  remove.addEventListener('click',async()=>{
    const context=await chooseTarget('delete');
    if(!context)return;
    run(async()=>{
      const csrf=await validateTarget(context,'delete');
      status.textContent='Eliminando…';
      if(context.target==='approved'){await request('DELETE');status.textContent='Foto publicada eliminada';}
      else{await candidateRequest('DELETE',csrf);await window.mxmedMediaReview.refresh();status.textContent='Foto pendiente retirada';}
    });
  });
  window.addEventListener('mxmed:profile-identity-hydrated',()=>{if(!photo)render();});
  document.getElementById('t-info-datos-tab')?.addEventListener('shown.bs.tab',()=>run(()=>request()));
  run(()=>request());
})();
