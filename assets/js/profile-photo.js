(() => {
  const box=document.getElementById('mxpi-photo-control');
  if(!box)return;
  const input=document.getElementById('mxpi-photo-input'), select=document.getElementById('mxpi-photo-select'), remove=document.getElementById('mxpi-photo-remove'), preview=document.getElementById('mxpi-photo-preview'), status=document.getElementById('mxpi-photo-status');
  const endpoint='/api/media/profile-photo.php';
  let token='',photo=null,busy=false;
  const render=()=>{
    preview.hidden=!photo;remove.hidden=!photo;
    if(photo)preview.querySelector('img').src=photo.public_url;
    else preview.querySelector('img').removeAttribute('src');
    select.textContent=photo?'Cambiar foto':'Seleccionar foto';
  };
  async function request(method='GET',body=null){
    const response=await fetch(endpoint,{method,body,credentials:'same-origin',headers:method==='GET'?{}:{'X-Profile-Photo-CSRF':token}});
    const result=await response.json();
    if(!response.ok||!result.ok)throw Error(result.message||'No se pudo guardar la fotografía. Intenta nuevamente.');
    token=result.data.csrf_token;photo=result.data.photo;render();
  }
  async function run(action){
    if(busy)return;
    busy=true;box.setAttribute('aria-busy','true');input.disabled=select.disabled=remove.disabled=true;
    try{await action();}catch(e){render();status.textContent=e.message;}
    finally{busy=false;box.removeAttribute('aria-busy');input.disabled=select.disabled=remove.disabled=false;input.value='';}
  }
  async function upload(file){
    if(!file)return;
    await run(async()=>{
      if(!['image/jpeg','image/png','image/webp'].includes(file.type))throw Error('Selecciona una imagen JPG, PNG o WebP.');
      if(file.size>10485760)throw Error('La fotografía supera el máximo de 10 MiB.');
      if(!token)await request();
      const url=URL.createObjectURL(file);
      preview.hidden=false;preview.querySelector('img').src=url;status.textContent='Subiendo…';
      try{const form=new FormData();form.append('image',file);await request('POST',form);status.textContent='Guardada';}
      finally{URL.revokeObjectURL(url);}
    });
  }
  select.addEventListener('click',()=>input.click());
  input.addEventListener('change',()=>upload(input.files?.[0]));
  box.addEventListener('dragover',e=>e.preventDefault());
  box.addEventListener('drop',e=>{e.preventDefault();upload(e.dataTransfer?.files?.[0]);});
  remove.addEventListener('click',()=>run(async()=>{status.textContent='Eliminando…';await request('DELETE');status.textContent='Foto eliminada';}));
  document.getElementById('t-info-datos-tab')?.addEventListener('shown.bs.tab',()=>run(()=>request()));
  run(()=>request());
})();
