(() => {
  const drop=document.getElementById('fotos-drop'), grid=document.getElementById('fotos-grid'), input=document.getElementById('fotos-input'), count=document.getElementById('fotos-count'), message=document.getElementById('fotos-msg');
  if(!drop || !grid || !input || !count)return;
  const endpoint='/api/media/gallery.php';
  let csrf='',busy=false;
  input.accept='image/jpeg,image/png,image/webp';
  message?.setAttribute('role','status');
  const notify=text=>{if(message){message.textContent=text;message.classList.toggle('show',Boolean(text));}};
  const render=images=>{
    // Refresh public thumbnails without discarding the inline review candidates.
    grid.querySelectorAll(':scope > .foto-item:not([data-review-candidate])').forEach(item=>item.remove());
    const publicItems=document.createDocumentFragment();
    count.textContent=images.length;
    drop.classList.toggle('has-items',images.length>0);
    document.getElementById('t-info-fotos')?.classList.toggle('has-items',images.length>0);
    count.parentElement?.classList.toggle('max',images.length>=16);
    images.forEach(asset=>{
      const wrap=document.createElement('div');wrap.className='foto-item';
      const img=document.createElement('img');img.src=asset.public_url;img.alt=asset.alt_text || '';
      const remove=document.createElement('button');remove.type='button';remove.className='foto-x';remove.textContent='×';remove.setAttribute('aria-label','Eliminar imagen');
      remove.addEventListener('click',()=>run(()=>request('DELETE',null,asset.media_id)));
      wrap.append(img,remove);publicItems.append(wrap);
    });
    grid.prepend(publicItems);
  };
  async function request(method='GET',body=null,id=''){
    const response=await fetch(endpoint+(id?'?media_id='+encodeURIComponent(id):''),{method,body,credentials:'same-origin',headers:method==='GET'?{}:{'X-Gallery-CSRF':csrf}});
    const result=await response.json();
    if(!response.ok || !result.ok)throw Error(result.message || 'No se pudieron cargar las fotos.');
    csrf=result.data.csrf_token;render(result.data.images);
  }
  async function run(action){
    if(busy)return;busy=true;drop.setAttribute('aria-busy','true');notify('');
    try{await action();}catch(error){notify(error.message);}finally{busy=false;drop.removeAttribute('aria-busy');input.value='';}
  }
  const upload=files=>run(async()=>{
    if(!csrf)await request();
    for(const file of files)await window.mxmedMediaReview.upload('gallery',file);
    notify('Pendiente de enviar');
  });
  drop.addEventListener('click',event=>{if(event.target.closest('.fotos-browse')&&!busy)input.click();});
  input.addEventListener('change',()=>upload(Array.from(input.files || [])));
  drop.addEventListener('dragover',event=>{event.preventDefault();drop.classList.add('dragover');});
  drop.addEventListener('dragleave',()=>drop.classList.remove('dragover'));
  drop.addEventListener('drop',event=>{event.preventDefault();drop.classList.remove('dragover');upload(Array.from(event.dataTransfer?.files || []));});
  document.getElementById('t-info-fotos-tab')?.addEventListener('shown.bs.tab',()=>run(()=>request()));
  run(()=>request());
})();
