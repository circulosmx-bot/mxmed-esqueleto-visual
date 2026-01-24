(function(){
  const MAX = 21;
  const drop = document.getElementById('fotos-drop');
  const grid = document.getElementById('fotos-grid');
  const input = document.getElementById('fotos-input');
  const countEl = document.getElementById('fotos-count');
  const msgEl = document.getElementById('fotos-msg');
  if(!drop || !grid || !input || !countEl) return;

  function load(){ try { return JSON.parse(localStorage.getItem('mxmed_fotos')||'[]'); } catch(e){ return []; } }
  function save(arr){ localStorage.setItem('mxmed_fotos', JSON.stringify(arr)); updateCount(arr); }
  function updateCount(arr){
    const n = (arr||load()).length;
    countEl.textContent = n;
    if(n >= MAX) countEl.parentElement?.classList.add('max');
    else countEl.parentElement?.classList.remove('max');
  }

  let hideMsgTimer = null;

  function showMsg(text){
    if(!msgEl) return;
    msgEl.textContent = text;
    msgEl.classList.add('show');
    drop.classList.add('error');
    if(hideMsgTimer) clearTimeout(hideMsgTimer);
    hideMsgTimer = setTimeout(()=>{ msgEl.classList.remove('show'); drop.classList.remove('error'); }, 3200);
  }

  function render(){
    const items = load(); grid.innerHTML=''; drop.classList.toggle('has-items', items.length>0); document.getElementById('t-info-fotos')?.classList.toggle('has-items', items.length>0);
    items.forEach((it,idx)=>{
      const wrap = document.createElement('div'); wrap.className='foto-item';
      const img = document.createElement('img'); img.src = it.data; img.alt = 'foto '+(idx+1);
      const x = document.createElement('button'); x.type='button'; x.className='foto-x'; x.innerHTML='&times;'; x.title='Eliminar';
      x.addEventListener('click', ()=>{ const arr=load(); arr.splice(idx,1); save(arr); render(); });
      wrap.appendChild(img); wrap.appendChild(x); grid.appendChild(wrap);
    });
    updateCount(items);
  }

  function addFiles(files){
    const arr = load();
    const remaining = Math.max(0, MAX - arr.length);
    if((files?.length||0) > remaining){
      const extra = (files?.length||0) - remaining;
      showMsg(`Límite ${MAX} alcanzado. Solo puedes agregar ${remaining} más (omitidas ${extra}).`);
    }
    for(const f of files){
      if(!f || !f.type || !f.type.startsWith('image/')) continue;
      if(arr.length >= MAX){ updateCount(arr); break; }
      const reader = new FileReader();
      reader.onload = (e)=>{
        if(arr.length >= MAX){ updateCount(arr); showMsg(`Límite ${MAX} alcanzado.`); return; }
        arr.push({ data: e.target.result });
        save(arr); render();
      };
      reader.readAsDataURL(f);
    }
  }

  drop.addEventListener('click', (e)=>{
    const btn = e.target.closest('.fotos-browse');
    if(btn){ input.click(); }
  });
  input.addEventListener('change', (e)=>{ addFiles(Array.from(e.target.files||[])); input.value=''; });

  drop.addEventListener('dragover', (e)=>{ e.preventDefault(); drop.classList.add('dragover'); });
  drop.addEventListener('dragleave', ()=> drop.classList.remove('dragover'));
  drop.addEventListener('drop', (e)=>{ e.preventDefault(); drop.classList.remove('dragover'); const files = e.dataTransfer?.files ? Array.from(e.dataTransfer.files) : []; addFiles(files); });

  render();
})();
