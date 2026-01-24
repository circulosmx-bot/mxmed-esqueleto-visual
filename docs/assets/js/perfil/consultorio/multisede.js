const MX_HORARIO_DEFAULTS = { a1:'09:00', b1:'14:00', a2:'16:00', b2:'20:00' };
const MX_IS_DEMO = window.location.hostname.endsWith('github.io');
const MX_HORARIO_SLOTS = ['a1','b1','a2','b2'];
function mxApplyHorarioDefault(input, slot){
  if(!input) return;
  const def = MX_HORARIO_DEFAULTS[slot];
  if(!def) return;
  try{ input.setAttribute('placeholder', def); }catch(_){}
  input.addEventListener('focus', ()=>{
    if(!(input.value||'').trim()){
      input.value = def;
      try{
        input.dispatchEvent(new Event('input'));
        input.dispatchEvent(new Event('change'));
      }catch(_){ }
    }
  });
}
function mxFillHorarioDefaults(inputs){
  inputs?.forEach((inp, idx)=>{
    if(!inp) return;
    const slot = MX_HORARIO_SLOTS[idx];
    const def = MX_HORARIO_DEFAULTS[slot];
    if(def && !(inp.value||'').trim()){
      inp.value = def;
      try{
        inp.dispatchEvent(new Event('input'));
        inp.dispatchEvent(new Event('change'));
      }catch(_){ }
    }
  });
}
function mxClearHorarioInputs(inputs){
  inputs?.forEach(inp=>{
    if(!inp) return;
    if((inp.value||'').trim()){
      inp.value = '';
      try{
        inp.dispatchEvent(new Event('input'));
        inp.dispatchEvent(new Event('change'));
      }catch(_){ }
    }
  });
}

(function(){

  // Consultorio: modal para confirmar agregar otro consultorio
  document.getElementById('btn-consul-add')?.addEventListener('click', function(e){
    e.preventDefault();
    const el = document.getElementById('modalConsulAdd');
    if(window.bootstrap && el){ new bootstrap.Modal(el).show(); }
    else { if(window.confirm('?Deseas agregar otro consultorio?')) {/* fallback */} }
  });
  function createSede2IfNeeded(){
    return createConsultorio(2);
  }

  function getConsultorioSlots(){
    const panes = Array.from(document.querySelectorAll('#p-consultorio .tab-pane[id^="sede"]'));
    const ids = [];
    panes.forEach(p=>{
      if(p.dataset.consulPlaceholder === 'true') return;
      const match = /^sede(\d+)$/.exec(p.id);
      if(!match) return;
      const n = parseInt(match[1],10);
      if(!n) return;
      ids.push(n);
    });
    return ids;
  }

  function syncAddTabVisibility(){
    const addBtn = document.getElementById('btn-consul-add');
    if(!addBtn) return;
    const addLi = addBtn.closest('li');
    if(!addLi) return;
    const ids = getConsultorioSlots();
    const atLimit = ids.filter(n=>n>=1).length >= 3;
    addLi.classList.toggle('d-none', atLimit);
    if(atLimit){
      addBtn.setAttribute('aria-hidden','true');
      addBtn.setAttribute('tabindex','-1');
    }else{
      addBtn.removeAttribute('aria-hidden');
      addBtn.removeAttribute('tabindex');
    }
    addBtn.classList.remove('disabled');
    addBtn.removeAttribute('aria-disabled');
    addBtn.title = '';
  }

  // Generalizado: crear consultorio N (2..3)
  function createConsultorio(n){
    if(n < 2) return null; if(n > 3){ alert('Puedes registrar hasta 3 consultorios.'); return null; }
    const nav = document.querySelector('#p-consultorio .mm-tabs-embed');
    const tabContent = document.querySelector('#p-consultorio .tab-content');
    if(!nav || !tabContent) return null;
    let pane = document.getElementById('sede'+n);
    const isPlaceholder = pane?.dataset?.consulPlaceholder === 'true';
    const placeholderRef = isPlaceholder ? pane : null;
    let btn  = document.querySelector(`#p-consultorio [data-bs-target="#sede${n}"]`);
    if(!pane || isPlaceholder){
      const tpl = document.getElementById('sede1'); if(!tpl) return null;
      pane = tpl.cloneNode(true);
      pane.id = 'sede'+n;
      pane.classList.remove('show','active');
      pane.dataset.consulClone = 'true';
      // limpiar inputs solo al clonar
      pane.querySelectorAll('input, textarea, select').forEach(el=>{
        if(el.tagName === 'SELECT'){ el.selectedIndex = 0; }
        else if(el.type === 'checkbox' || el.type === 'radio'){ el.checked = false; }
        else { el.value = ''; }
      });
      // renombrar ids con sufijo n
      const sfx = String(n);
      const ids = ['cp','colonia','mensaje-cp','municipio','estado','cons-grupo-si','cons-grupo-no','cons-grupo-nombre','cons-titulo','cons-calle','cons-numext','cons-numint','cons-piso','cons-tel1','cons-tel2','cons-tel3','cons-wa','cons-wa-sync','cons-urg1','cons-urg2','sched-body','sched-copy-mon','sched-clear','cons-foto','cons-foto-prev','cons-foto-img','cons-map','cons-map-frame','cons-lat','cons-lng'];
      ids.forEach(base=>{ const el = pane.querySelector('#'+base); if(el){ el.id = base + (base==='sched-body'||base==='sched-copy-mon'||base==='sched-clear' ? '-'+sfx : sfx); const lab = pane.querySelector(`label[for="${base}"]`); if(lab) lab.setAttribute('for', el.id); } });
      if(placeholderRef){ placeholderRef.replaceWith(pane); }
      else { tabContent.appendChild(pane); }
    }
    if(!btn){
      const li = document.createElement('li'); li.className='nav-item';
      btn = document.createElement('button'); btn.className='nav-link'; btn.type='button'; btn.setAttribute('data-bs-toggle','pill'); btn.setAttribute('data-bs-target','#sede'+n);
      const ord = (n===2?'SEGUNDO':(n===3?'TERCER':''));
      btn.innerHTML = '<span class="tab-ico material-symbols-rounded" aria-hidden="true">apartment</span><span class="tab-lbl">'+ord+'<br>CONSULTORIO</span>';
      const addLi = document.getElementById('btn-consul-add')?.closest('li'); if(addLi){ nav.insertBefore(li, addLi); } else { nav.appendChild(li); }
      li.appendChild(btn);
    }
    // insertar barra de eliminar en pane secundario
    try{
      if(n>1 && !pane.querySelector('.cons-delbar')){
        const bar = document.createElement('div'); bar.className='cons-delbar';
        const btnDel = document.createElement('button'); btnDel.type='button'; btnDel.className='btn btn-outline-danger btn-sm'; btnDel.id = 'cons-del-'+n; btnDel.textContent='Eliminar este consultorio';
        bar.appendChild(btnDel);
        const firstRow = pane.querySelector('.row.g-3'); if(firstRow) firstRow.prepend(bar); else pane.prepend(bar);
        btnDel.addEventListener('click', ()=>{ openDeleteModal(n); });
      }
    }catch(_){ }

    // activar
    document.querySelectorAll('#p-consultorio .mm-tabs-embed .nav-link').forEach(b=>b.classList.remove('active'));
    document.querySelectorAll('#p-consultorio .tab-pane').forEach(p=>p.classList.remove('show','active'));
    pane.classList.add('show','active'); btn.classList.add('active'); if(window.bootstrap){ new bootstrap.Tab(btn).show(); }
    // inicializaciones
    try{ setupCpAuto({ cp:'cp'+n, colonia:'colonia'+n, msg:'mensaje-cp'+n, mun:'municipio'+n, est:'estado'+n }); }catch(_){ }
    try{ const cp=document.getElementById('cp'+n), col=document.getElementById('colonia'+n); if(cp&&col){ cp.addEventListener('blur', ()=>{ col.focus(); }); } }catch(_){ }
    try{ if(window._mx_phone_bind){ window._mx_phone_bind(pane); } }catch(_){ }
    try{ const wa=document.getElementById('cons-wa'+n), cb=document.getElementById('cons-wa-sync'+n), dg=document.getElementById('dp-whatsapp'); if(cb&&wa){ const fill=()=>{ if(dg){ wa.value=dg.value||''; wa.dispatchEvent(new Event('input')); } }; const toggle=()=>{ if(cb.checked){ wa.disabled=true; wa.placeholder='+52 ...'; fill(); } else { wa.disabled=false; wa.value=''; wa.placeholder='otro numero Whatsapp'; } }; cb.addEventListener('change',toggle); if(dg) dg.addEventListener('input',()=>{ if(cb.checked) fill(); }); toggle(); } }catch(_){ }
    try{ if(window._mx_setupSchedulesFor){ window._mx_setupSchedulesFor(pane,'-'+n); } }catch(_){ }
    try{ const frame=document.getElementById('cons-map-frame'+n); if(frame){ const addr=()=>{ const cp=(document.getElementById('cp'+n)?.value||'').trim(); const col=(document.getElementById('colonia'+n)?.value||'').trim(); const mun=(document.getElementById('municipio'+n)?.value||'').trim(); const edo=(document.getElementById('estado'+n)?.value||'').trim(); const calle=(document.getElementById('cons-calle'+n)?.value||'').trim(); const num=(document.getElementById('cons-numext'+n)?.value||'').trim(); const a=[calle&&(calle+(num?' '+num:'')),col,cp,mun,edo,'M\u00E9xico'].filter(Boolean).join(', '); return a; }; const upd=()=>{ const a=addr(); if(!a) return; const url='https://www.google.com/maps?q='+encodeURIComponent(a)+'&z=17&output=embed'; if(frame.src!==url) frame.src=url; }; ['cp'+n,'colonia'+n,'cons-calle'+n,'cons-numext'+n].forEach(id=>{ const el=document.getElementById(id); if(el){ el.addEventListener('input',upd); el.addEventListener('change',upd);} }); } }catch(_){ }
    try{ if(window.L && typeof L.map==='function'){ (function(){ const mapBox=document.getElementById('cons-map'+n); if(!mapBox) return; const map=L.map(mapBox).setView([21.882,-102.296],13); L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19}).addTo(map); const marker=L.marker([21.882,-102.296],{draggable:true}).addTo(map); const latI=document.getElementById('cons-lat'+n), lngI=document.getElementById('cons-lng'+n); const setLL=(ll)=>{ if(latI) latI.value=ll.lat.toFixed(6); if(lngI) lngI.value=ll.lng.toFixed(6); }; setLL(marker.getLatLng()); marker.on('moveend',(e)=>setLL(e.target.getLatLng())); map.on('click',(e)=>{ marker.setLatLng(e.latlng); setLL(e.latlng); }); })(); } }catch(_){ }
    syncAddTabVisibility();
    return {pane, btn};
  }
  window._mx_createConsultorio = createConsultorio;

  // Eliminar consultorio con confirmaci?n (demo: acepta c?digo 123456 o pass 'codex')
  function openDeleteModal(n){
    const modalEl = document.getElementById('modalConsulDel'); if(!modalEl) return;
    modalEl.setAttribute('data-target-n', String(n));
    // reset inputs
    const code = document.getElementById('del-code'); const pass = document.getElementById('del-pass'); const err = document.getElementById('del-error');
    if(code) code.value=''; if(pass) pass.value=''; if(err) err.style.display='none';
    const rCode = document.getElementById('del-auth-code'); const rPass = document.getElementById('del-auth-pass');
    const divCode = document.getElementById('del-input-code'); const divPass = document.getElementById('del-input-pass');
    function sync(){ if(rPass.checked){ divPass.style.display='block'; divCode.style.display='none'; } else { divPass.style.display='none'; divCode.style.display='block'; } }
    rCode?.addEventListener('change', sync); rPass?.addEventListener('change', sync); sync();
    if(window.bootstrap){ new bootstrap.Modal(modalEl).show(); }
  }
  document.getElementById('modalConsulDelYes')?.addEventListener('click', async ()=>{
    const modalEl = document.getElementById('modalConsulDel'); if(!modalEl) return;
    const n = parseInt(modalEl.getAttribute('data-target-n')||'0',10); if(!n || n===1) return;
    const usePass = document.getElementById('del-auth-pass')?.checked;
    const pass = document.getElementById('del-pass')?.value||'';
    const code = document.getElementById('del-code')?.value||'';
    const err = document.getElementById('del-error');
    async function verify(){
      try{
        if(usePass){
          const url = MX_IS_DEMO ? 'mock/verify-password.json' : './api/verify-password.php';
          const r = await fetch(url, MX_IS_DEMO ? { method:'GET', headers:{} } : { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ password: pass })});
          if(!r.ok) return false; const j = await r.json(); return !!j.ok;
        }else{
          const url = MX_IS_DEMO ? 'mock/verify-sms.json' : './api/verify-sms.php';
          const r = await fetch(url, MX_IS_DEMO ? { method:'GET', headers:{} } : { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ code })});
          if(!r.ok) return false; const j = await r.json(); return !!j.ok;
        }
      }catch(_){
        // Modo pruebas: si hay valor no vac?o, aceptar.
        return usePass ? (pass.trim()!=='') : (code.trim()!=='');
      }
    }
    const ok = await verify();
    if(!ok){ if(err){ err.style.display='block'; } return; }
    // cerrar modal
    if(window.bootstrap){ bootstrap.Modal.getInstance(modalEl)?.hide(); }
    // eliminar pane y tab
    const pane = document.getElementById('sede'+n);
    const btn = document.querySelector(`#p-consultorio [data-bs-target="#sede${n}"]`);
    const li = btn?.closest('li');
    pane?.remove(); li?.remove();
    // reactivar principal
    const btn1 = document.querySelector('#p-consultorio [data-bs-target="#sede1"]');
    const pane1 = document.getElementById('sede1');
    if(btn1 && pane1){ btn1.classList.add('active'); pane1.classList.add('show','active'); if(window.bootstrap){ new bootstrap.Tab(btn1).show(); } }
    // re-habilitar bot?n agregar si estaba bloqueado
    const addBtn=document.getElementById('btn-consul-add'); if(addBtn){ addBtn.classList.remove('disabled'); addBtn.removeAttribute('aria-disabled'); addBtn.title=''; }
    syncAddTabVisibility();
  });
  function nextConsultorioIndex(){
    const ids = getConsultorioSlots();
    for(let i=2;i<=3;i++){
      if(!ids.includes(i)) return i;
    }
    return null;
  }
  document.getElementById('modalConsulAddYes')?.addEventListener('click', function(){
    const el = document.getElementById('modalConsulAdd');
    if(window.bootstrap && el){ bootstrap.Modal.getInstance(el)?.hide(); }
    const next = nextConsultorioIndex();
    if(!next){ syncAddTabVisibility(); return; }
    if(window._mx_createConsultorio) window._mx_createConsultorio(next); else createSede2IfNeeded();
    try{ initAutosave(); }catch(_){ }
  });

  syncAddTabVisibility();

  // ====== CP -> Colonias (SEPOMEX) ======
  // Inicializa auto-llenado para un conjunto de controles
  function setupCpAuto(ids){
    const cp = document.getElementById(ids.cp);
    const sel = document.getElementById(ids.colonia);
    const msg = document.getElementById(ids.msg);
    const mun = document.getElementById(ids.mun);
    const est = document.getElementById(ids.est);
    if(!cp || !sel) return;

    function setMsg(text){
      if(!msg) return;
      if(text){ msg.textContent = text; msg.classList.remove('d-none'); }
      else { msg.textContent = ''; msg.classList.add('d-none'); }
    }

    function fillSelect(options){
      // Limpia y coloca placeholder
      sel.innerHTML = '';
      const base = document.createElement('option'); base.value=''; base.textContent='Selecciona\u2026'; sel.appendChild(base);
      // Agrega colonias
      (options||[]).forEach(name=>{
        const opt = document.createElement('option'); opt.value = name; opt.textContent = name; sel.appendChild(opt);
      });
      const has = !!options && options.length > 0;
      // Habilita/deshabilita de forma expl?cita (prop y atributo)
      if(has){ sel.disabled = false; sel.removeAttribute('disabled'); sel.selectedIndex = 0; }
      else { sel.disabled = true; sel.setAttribute('disabled','disabled'); }
      try{ console.debug('[SEPOMEX] opciones en #'+ids.colonia+':', sel.options.length-1); }catch(_){ }
      // Dispara change por si hay listeners posteriores
      sel.dispatchEvent(new Event('change'));
    }

    async function fetchSepomex(cpVal){
      // Llama a API SEPOMEX; intenta proxy local (PHP), luego directo y luego fallback CORS
      const localDB    = `./sepomex-local.php?cp=${cpVal}`;
      const proxyLocal = `./sepomex-proxy.php?cp=${cpVal}`;
      const direct = `https://api-sepomex.hckdrk.mx/query/info_cp/${cpVal}?type=simplified`;
      const fallback = `https://api.allorigins.win/raw?url=${encodeURIComponent(direct)}`;

      async function doFetch(url, raw){
        // fetch con timeout para no bloquear la UI
        const ctrl = new AbortController();
        const t = setTimeout(()=>ctrl.abort(), 5000); // 5s
        const res = await fetch(url, { cache:'no-store', mode:'cors', signal: ctrl.signal }).finally(()=>clearTimeout(t));
        if(!res.ok) throw new Error('HTTP '+res.status);
        const data = raw ? JSON.parse(await res.text()) : await res.json();
        const r = (data && (data.response || data)) || {};
        // La API documentada expone data.response.settlement (array de strings)
        let colonias = r.settlement || r.settlements || r.colonias || r.asentamientos || r.asentamiento || r.neighborhoods || [];
        let list = [];
        if(Array.isArray(colonias)){
          list = colonias.map(x=> (typeof x === 'string' ? x : (x.nombre || x.name || x.asentamiento || x.settlement || ''))).filter(Boolean);
        }else if(typeof colonias === 'object' && colonias){
          list = Object.values(colonias).map(v=> typeof v === 'string' ? v : (v?.nombre || v?.name || v?.asentamiento || v?.settlement || '')).filter(Boolean);
        }
        // Municipios/estado posibles (top-level o en primer asentamiento)
        const municipio = r.municipio || r.municipality || r.ciudad || r.city || (Array.isArray(colonias) && typeof colonias[0] === 'object' ? (colonias[0].municipio || colonias[0].city || '') : '');
        const estado = r.estado || r.state || r.entidad || r.entity || (Array.isArray(colonias) && typeof colonias[0] === 'object' ? (colonias[0].estado || colonias[0].state || '') : '');
        return { list, municipio, estado };
      }

      // 1) Si hay cache localStorage, ?salo de inmediato y refresca en background
      const cacheKey = 'sepomex_cp_'+cpVal;
      try{
        const cached = JSON.parse(localStorage.getItem(cacheKey)||'null');
        if(cached && Array.isArray(cached.list) && cached.list.length){
          // Disparar refresh en background pero devolver r?pido
          refreshOnline();
          return cached;
        }
      }catch(_){ }

      // 2) Para primera respuesta m?s r?pida: hacer intentos en paralelo y tomar el primero
      async function refreshOnline(){
        // intenta actualizar cache sin bloquear UI
        Promise.race([
          doFetch(localDB,false),
          doFetch(proxyLocal,false),
          doFetch(direct,false),
          doFetch(fallback,true)
        ]).then(r=>{ try{ localStorage.setItem(cacheKey, JSON.stringify(r)); }catch(_){ } }).catch(()=>{});
      }

      try{
        const first = await Promise.race([
          doFetch(localDB,false),
          doFetch(proxyLocal,false),
          doFetch(direct,false),
          doFetch(fallback,true)
        ]);
        try{ localStorage.setItem(cacheKey, JSON.stringify(first)); }catch(_){ }
        return first;
      }catch(e3){
        try{ console.error('[SEPOMEX] todos los intentos en cliente fallaron', e3?.message||e3); }catch(_){ }
        // 3) Fallback local (archivo est?tico para demo)
        try{
          const local = await (await fetch('assets/data/sepomex-fallback.json', {cache:'no-store'})).json();
          const entry = local && local[cpVal];
          if(entry){
            const list = (entry.settlement || entry.colonias || entry.asentamientos || []).slice();
            const municipio = entry.municipio || '';
            const estado = entry.estado || '';
            try{ console.info('[SEPOMEX] usando datos locales de prueba para', cpVal); }catch(_){ }
            return { list, municipio, estado };
          }
        }catch(_e4){ /* sin fallback local */ }
        return { list:[], municipio:'', estado:'' };
      }
    }

    async function onCpChange(){
      const val = (cp.value||'').trim();
      // valida 5 d?gitos
      if(!/^\d{5}$/.test(val)){
        fillSelect([]); setMsg(''); if(mun) mun.value=''; if(est) est.value=''; return;
      }
      setMsg('');
      fillSelect([]); sel.disabled = true;
      const { list, municipio, estado } = await fetchSepomex(val);
      if(list && list.length){
        const uniq = Array.from(new Set(list)).sort((a,b)=>a.localeCompare(b,'es'));
        // Mostrar aviso si proviene de datos locales
        const fromLocal = (await fetch('assets/data/sepomex-fallback.json', {cache:'no-store'}).then(r=>r.json()).then(j=> !!(j && j[val])).catch(()=>false));
        fillSelect(uniq);
        setMsg(fromLocal ? 'Usando datos locales de prueba' : '');
        if(mun) mun.value = municipio||''; if(est) est.value = estado||'';
        try{ console.debug('[SEPOMEX] colonias:', uniq); }catch(_){ }
      }else{
        fillSelect([]); setMsg('C\u00F3digo postal no v\u00E1lido'); if(mun) mun.value=''; if(est) est.value='';
      }
    }

    cp.addEventListener('change', onCpChange);
    cp.addEventListener('input', ()=>{
      // mostrar estado de carga antes de llamar
      const v = (cp.value||'').trim();
      if(/^\d{5}$/.test(v)){
        sel.innerHTML = '<option value="">Buscando colonias.</option>'; sel.disabled = true; onCpChange();
      }
    });
    }

  // Activar en el primer consultorio (IDs base)
  setupCpAuto({ cp:'cp', colonia:'colonia', msg:'mensaje-cp', mun:'municipio', est:'estado' });

  // Si se crea Consultorio 2 din?micamente, renombrar IDs y activar all? tambi?n
  const origCreate = createSede2IfNeeded;
  createSede2IfNeeded = function(){
    const ret = origCreate();
    if(!ret) return ret;
    const pane2 = ret.pane2;
    // Renombrar IDs para evitar duplicados
    const map = [ ['cp','cp2'], ['colonia','colonia2'], ['mensaje-cp','mensaje-cp2'], ['municipio','municipio2'], ['estado','estado2'] ];
    map.forEach(([from,to])=>{ const el = pane2.querySelector('#'+from); if(el){ el.id = to; const label = pane2.querySelector('label[for="'+from+'"]'); if(label) label.setAttribute('for', to); } });
    // Inicializar listeners en el nuevo set
    setupCpAuto({ cp:'cp2', colonia:'colonia2', msg:'mensaje-cp2', mun:'municipio2', est:'estado2' });
    return ret;
  };

  const $$ = (s,c=document)=>Array.from(c.querySelectorAll(s));

  function toggleFotoPrincipalMsg(show){
    const msg = document.getElementById('cons-foto-sync');
    if(!msg) return;
    msg.style.display = show ? 'block' : 'none';
  }

  function confirmFotoPrincipalRemoval(onConfirm, onCancel){
    const modalEl = document.getElementById('modalFotoPrincipalRemove');
    if(!modalEl){
      const ok = confirm('?Deseas quitar esta imagen como foto principal del consultorio?');
      if(ok) onConfirm?.(); else onCancel?.();
      return;
    }
    const yesBtn = document.getElementById('modalFotoPrincipalRemoveYes');
    const modal = window.bootstrap?.Modal?.getOrCreateInstance ? window.bootstrap.Modal.getOrCreateInstance(modalEl) : new bootstrap.Modal(modalEl);
    const cleanup = ()=>{ if(yesBtn) yesBtn.onclick = null; };
    modalEl.addEventListener('hidden.bs.modal', function handler(){
      modalEl.removeEventListener('hidden.bs.modal', handler);
      cleanup();
      onCancel?.();
    }, { once:true });
    if(yesBtn){
      yesBtn.onclick = ()=>{
        cleanup();
        onConfirm?.();
        modal.hide();
      };
    }
    modal.show();
  }

  function confirmLogoManualRemoval(onConfirm, onCancel){
    const modalEl = document.getElementById('modalLogoManualRemove');
    if(!modalEl){
      const ok = confirm('?Deseas quitar esta imagen como Logotipo del grupo m?dico asociado a tu consultorio?');
      if(ok) onConfirm?.(); else onCancel?.();
      return;
    }
    const yesBtn = document.getElementById('modalLogoManualRemoveYes');
    const modal = window.bootstrap?.Modal?.getOrCreateInstance ? window.bootstrap.Modal.getOrCreateInstance(modalEl) : new bootstrap.Modal(modalEl);
    const cleanup = ()=>{ if(yesBtn) yesBtn.onclick = null; };
    modalEl.addEventListener('hidden.bs.modal', function handler(){
      modalEl.removeEventListener('hidden.bs.modal', handler);
      cleanup();
      onCancel?.();
    }, { once:true });
    if(yesBtn){
      yesBtn.onclick = ()=>{
        cleanup();
        onConfirm?.();
        modal.hide();
      };
    }
    modal.show();
  }
  function setupUploadBox(box){
    const input = box.querySelector('.mf-input');
    if(!input) return;
    let prev  = box.querySelector('.mf-prev');
    const previewTarget = box.dataset.previewTarget;
    if((!prev || !prev.querySelector) && previewTarget){
      const external = document.getElementById(previewTarget);
      if(external) prev = external;
    }
    if(!prev) return;
    const img   = prev.querySelector('img');
    if(!img) return;
    const inputId = input.id || '';

    // click-to-upload
    box.addEventListener('click', e=>{
      if(e.target.closest('.mf-qr') || e.target.closest('.mf-choose')) return;
      if(!e.target.closest('input[type=file]')) input.click();
    });
    const chooseBtn = box.querySelector('.mf-choose');
    if(chooseBtn){ chooseBtn.addEventListener('click', ()=>input.click()); }

    // drag & drop
    ['dragenter','dragover'].forEach(evt=>{
      box.addEventListener(evt, e=>{ e.preventDefault(); box.classList.add('dragover'); });
    });
    ['dragleave','drop'].forEach(evt=>{
      box.addEventListener(evt, e=>{ e.preventDefault(); box.classList.remove('dragover'); });
    });
    box.addEventListener('drop', e=>{
      const f = e.dataTransfer?.files?.[0]; if(f) handle(f);
    });

    input.addEventListener('change', ()=>{ const f = input.files?.[0]; if(f) handle(f); });

    function handle(file){
      if(!file.type.startsWith('image/')) return;
      const r = new FileReader();
      r.onload = ev => {
        img.src = ev.target.result;
        prev.removeAttribute('hidden');
        prev.style.display = previewTarget ? 'flex' : 'block';
        const slot = box.closest('.logo-slot');
        if(slot){
          slot.classList.add('show-preview');
          slot.classList.add('has-logo');
        }
        if(box.dataset.type === 'logo'){ box.classList.add('has-logo'); }
        if(inputId === 'cons-logo'){
          const drop = slot?.querySelector('.logo-slot-drop');
          if(drop){ drop.setAttribute('hidden','hidden'); }
          mxSetLogoSource('manual');
          mxToggleLogoManualMsg(true);
          mxToggleLogoSyncMsg(false);
        }
        if(inputId === 'cons-foto'){ toggleFotoPrincipalMsg(true); }
      };
      r.readAsDataURL(file);
    }

    const delBtn = prev.querySelector('.foto-x');
    if(delBtn && inputId === 'cons-foto'){
      delBtn.addEventListener('click', ev=>{
        ev.preventDefault();
        ev.stopPropagation();
        const clearFoto = ()=>{
          img.src = '';
          prev.style.display = 'none';
          prev.setAttribute('hidden','hidden');
          input.value = '';
          toggleFotoPrincipalMsg(false);
        };
        confirmFotoPrincipalRemoval(clearFoto);
      });
    }
    if(delBtn && inputId === 'cons-logo'){
      delBtn.addEventListener('click', ev=>{
        if(mxGetLogoSource() !== 'manual') return;
        ev.preventDefault();
        ev.stopPropagation();
        const clearLogo = ()=>{
          mxResetLogoPreview();
          mxToggleLogoManualMsg(false);
          mxToggleLogoSyncMsg(false);
        };
        confirmLogoManualRemoval(clearLogo);
      });
    }

    // QR (mock)
    const qrBtn = box.querySelector('.mf-qr');
    if(qrBtn){ qrBtn.addEventListener('click', ()=>{
      const el = document.getElementById('modalQR');
      if(window.bootstrap && el){ new bootstrap.Modal(el).show(); }
    }); }
  }

  window.mxSetupUploadBox = setupUploadBox;
  $$('.mf-upload').forEach(setupUploadBox);
  const logoDrop = document.querySelector('#cons-logo-slot .logo-slot-drop');
  if(logoDrop){
    window._mx_logoDropTemplate = logoDrop.outerHTML;
  }
})();

