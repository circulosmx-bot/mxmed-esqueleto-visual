const MX_HORARIO_DEFAULTS = { a1:'09:00', b1:'14:00', a2:'16:00', b2:'20:00' };
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
  const resolveActiveDoctorId = ()=>{
    const candidates = [
      (typeof window.resolveDoctorId === 'function' ? window.resolveDoctorId() : ''),
      window.mxmedStore?.doctorId,
      window.mxmedStore?.doctor_id,
      window.mxmedStore?.doctorProfile?.doctor_id,
      window.mxmedDoctor?.doctor_id,
      window.mxmedDoctor?.id,
      document.body?.dataset?.doctorId
    ];
    for(const candidate of candidates){
      const doctorId = String(candidate || '').trim();
      if(doctorId) return doctorId;
    }
    return '';
  };
  const normalizeGroupLogoUrl = (raw)=>{
    const text = String(raw || '').trim();
    if(!text) return '';
    if(/^https?:\/\//i.test(text) || /^data:image\//i.test(text) || /^blob:/i.test(text)) return text;
    return text.startsWith('/') ? text : `/${text.replace(/^\/+/, '')}`;
  };
  const persistGroupLogoUrl = (raw)=>{
    if(typeof window.mxPersistGroupLogoUrl === 'function'){
      return window.mxPersistGroupLogoUrl(raw, { clear: !normalizeGroupLogoUrl(raw) });
    }
    const value = normalizeGroupLogoUrl(raw);
    try{
      if(!window.mxmedStore || typeof window.mxmedStore !== 'object'){
        window.mxmedStore = {};
      }
      window.mxmedStore.group_logo_url = value;
      window.mxmedStore.groupLogoUrl = value;
      window.mxmedStore.group_logo_doctor_id = resolveActiveDoctorId();
      window.mxmedStore.group_logo_storage_key = '';
      if(window.mxmedStore.doctorProfile && typeof window.mxmedStore.doctorProfile === 'object'){
        window.mxmedStore.doctorProfile.group_logo_url = value;
      }
    }catch(_){ }
    return value;
  };
  const readPersistedGroupLogoUrl = ()=>{
    if(typeof window.mxReadPersistedGroupLogoUrl === 'function'){
      return normalizeGroupLogoUrl(window.mxReadPersistedGroupLogoUrl() || '');
    }
    const activeDoctorId = resolveActiveDoctorId();
    const storeDoctorId = String(window.mxmedStore?.group_logo_doctor_id || '').trim();
    const fromStore = normalizeGroupLogoUrl(window.mxmedStore?.group_logo_url || window.mxmedStore?.groupLogoUrl || '');
    if(fromStore && activeDoctorId && storeDoctorId && activeDoctorId === storeDoctorId){
      return fromStore;
    }
    return '';
  };
  const hydrateGroupLogoPreview = ()=>{
    const saved = readPersistedGroupLogoUrl();
    if(!saved){
      const activeSource = typeof window.mxGetLogoSource === 'function'
        ? String(window.mxGetLogoSource() || '')
        : '';
      if(activeSource === 'manual' && typeof window.mxResetLogoPreview === 'function'){
        window.mxResetLogoPreview();
      }
      return;
    }
    const img = document.getElementById('cons-logo-img');
    const prev = document.getElementById('cons-logo-prev');
    const slot = document.getElementById('cons-logo-slot');
    if(!img) return;
    if(!(img.getAttribute('src') || '').trim()){
      img.src = saved;
    }
    if(prev){
      prev.removeAttribute('hidden');
      prev.style.display = 'flex';
    }
    if(slot){
      slot.classList.add('show-preview', 'has-logo');
      const drop = slot.querySelector('.logo-slot-drop');
      if(drop){ drop.setAttribute('hidden','hidden'); }
    }
    try{ window.mxSetLogoSource?.('manual'); }catch(_){ }
    try{ window.mxToggleLogoManualMsg?.(true); }catch(_){ }
    try{ window.mxToggleLogoSyncMsg?.(false); }catch(_){ }
    persistGroupLogoUrl(saved);
  };

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
      const ids = ['cp','colonia','mensaje-cp','municipio','estado','cons-grupo-si','cons-grupo-no','cons-grupo-nombre','cons-titulo','cons-base-name-hint','cons-calle','cons-numext','cons-numint','cons-piso','cons-tel1','cons-tel2','cons-tel3','cons-wa','cons-wa-sync','cons-urg1','cons-urg2','sched-body','sched-copy-mon','sched-clear','cons-foto','cons-foto-prev','cons-foto-img','cons-map','cons-map-frame','cons-lat','cons-lng'];
      ids.forEach(base=>{ const el = pane.querySelector('#'+base); if(el){ el.id = base + (base==='sched-body'||base==='sched-copy-mon'||base==='sched-clear' ? '-'+sfx : sfx); const lab = pane.querySelector(`label[for="${base}"]`); if(lab) lab.setAttribute('for', el.id); } });
      const groupNo = pane.querySelector(`#cons-grupo-no${sfx}`);
      const groupYes = pane.querySelector(`#cons-grupo-si${sfx}`);
      const groupName = pane.querySelector(`#cons-grupo-nombre${sfx}`);
      const groupNameWrap = groupName?.closest('[data-cons-group-name-wrap]') || groupName?.closest('[class*="col-"]');
      const groupRadioName = `cons-grupo-${sfx}`;
      if(groupNo) groupNo.name = groupRadioName;
      if(groupYes) groupYes.name = groupRadioName;
      if(groupNo) groupNo.checked = true;
      if(groupYes) groupYes.checked = false;
      if(groupNameWrap) groupNameWrap.classList.add('d-none');
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
    try{ bindConsultorioMapByIndex(n); }catch(_){ }
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
          const r = await fetch('./api/verify-password.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ password: pass })});
          if(!r.ok) return false; const j = await r.json(); return !!j.ok;
        }else{
          const r = await fetch('./api/verify-sms.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ code })});
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

    function fillSelect(options, meta = {}){
      // Limpia y coloca placeholder
      sel.innerHTML = '';
      const base = document.createElement('option'); base.value=''; base.textContent='Selecciona\u2026'; sel.appendChild(base);
      // Agrega colonias
      (options||[]).forEach(name=>{
        const opt = document.createElement('option'); opt.value = name; opt.textContent = name; sel.appendChild(opt);
      });
      const has = !!options && options.length > 0;
      sel.dataset.municipio = String(meta.municipio || '').trim();
      sel.dataset.estado = String(meta.estado || '').trim();
      // Habilita/deshabilita de forma expl?cita (prop y atributo)
      if(has){ sel.disabled = false; sel.removeAttribute('disabled'); sel.selectedIndex = 0; }
      else { sel.disabled = true; sel.setAttribute('disabled','disabled'); }
      try{ console.debug('[SEPOMEX] opciones en #'+ids.colonia+':', sel.options.length-1); }catch(_){ }
      // Dispara change por si hay listeners posteriores
      sel.dispatchEvent(new Event('change'));
    }

    async function fetchSepomex(cpVal){
      // Fuente oficial: backend catalogado en BD (api/catalog).
      const primary = `/api/catalog/cp/${encodeURIComponent(cpVal)}`;
      const compat = `/api/catalog/index.php/cp/${encodeURIComponent(cpVal)}`;
      const cacheKey = `catalog_cp_${cpVal}`;

      async function doFetch(url){
        const ctrl = new AbortController();
        const t = setTimeout(()=>ctrl.abort(), 6000);
        const res = await fetch(url, { cache:'no-store', credentials:'same-origin', signal: ctrl.signal }).finally(()=>clearTimeout(t));
        if(!res.ok) throw new Error('HTTP '+res.status);
        const data = await res.json();
        if(!data || data.ok !== true){
          throw new Error(String(data?.message || data?.error || 'catalog_error'));
        }
        const list = Array.isArray(data.colonias) ? data.colonias.map((x)=> String(x || '').trim()).filter(Boolean) : [];
        const municipio = String(data.municipio || '').trim();
        const estado = String(data.estado || '').trim();
        return { list, municipio, estado };
      }

      try{
        const cached = JSON.parse(localStorage.getItem(cacheKey)||'null');
        if(cached && Array.isArray(cached.list) && cached.list.length){
          Promise.race([doFetch(primary), doFetch(compat)])
            .then((fresh)=>{ try{ localStorage.setItem(cacheKey, JSON.stringify(fresh)); }catch(_){ } })
            .catch(()=>{});
          return cached;
        }
      }catch(_){ }

      try{
        const first = await Promise.race([doFetch(primary), doFetch(compat)]);
        try{ localStorage.setItem(cacheKey, JSON.stringify(first)); }catch(_){ }
        return first;
      }catch(err){
        try{ console.error('[CATALOG_CP] lookup fail', err?.message||err); }catch(_){ }
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
        fillSelect(uniq, { municipio, estado });
        setMsg('');
        if(mun) mun.value = municipio||''; if(est) est.value = estado||'';
        try{ console.debug('[SEPOMEX] colonias:', uniq); }catch(_){ }
      }else{
        fillSelect([]); setMsg('C\u00F3digo postal sin colonias registradas'); if(mun) mun.value=''; if(est) est.value='';
      }
    }

    cp.addEventListener('change', onCpChange);
    sel.addEventListener('change', ()=>{
      if(mun && sel.dataset.municipio){ mun.value = sel.dataset.municipio; }
      if(est && sel.dataset.estado){ est.value = sel.dataset.estado; }
    });
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

  window.__mxmedConsultorioMapManaged = true;

  const consultorioMapRefreshers = new Map();
  const consultorioMapInvalidators = new Map();
  const consultorioMapLastAddress = new Map();
  const consultorioGeoStateByIndex = new Map();

  function toFiniteNumber(value){
    if(value === null || value === undefined || value === '') return null;
    const n = Number(value);
    return Number.isFinite(n) ? n : null;
  }

  function normalizeGeocodeSource(value){
    const raw = String(value || '').trim().toLowerCase();
    if(raw === 'manual_adjusted') return 'manual_adjusted';
    if(raw === 'auto_geocoded') return 'auto_geocoded';
    if(raw === 'device') return 'device';
    return '';
  }

  function getConsultorioGeoState(index = 1){
    const key = String(Number(index || 1) || 1);
    return consultorioGeoStateByIndex.get(key) || {};
  }

  function setConsultorioGeoState(index = 1, patch = {}){
    const key = String(Number(index || 1) || 1);
    const current = getConsultorioGeoState(key);
    const next = { ...current, ...patch };
    consultorioGeoStateByIndex.set(key, next);
    return next;
  }

  function resolveConsultorioMapIdsByIndex(index = 1){
    const n = Number(index || 1);
    const suffix = n > 1 ? String(n) : '';
    return {
      index: n,
      cp: `cp${suffix}`,
      colonia: `colonia${suffix}`,
      municipio: `municipio${suffix}`,
      estado: `estado${suffix}`,
      calle: `cons-calle${suffix}`,
      num_ext: `cons-numext${suffix}`,
      num_int: `cons-numint${suffix}`,
      frame: `cons-map-frame${suffix}`
    };
  }

  function buildConsultorioAddressByIds(ids){
    const read = (id)=> (document.getElementById(id)?.value || '').trim();
    const calle = read(ids.calle);
    const numExt = read(ids.num_ext);
    const numInt = read(ids.num_int);
    const colonia = read(ids.colonia);
    const cp = read(ids.cp);
    const municipio = read(ids.municipio);
    const estado = read(ids.estado);
    const street = [calle, numExt, numInt ? `Int ${numInt}` : ''].filter(Boolean).join(' ').trim();
    return [street, colonia, cp, municipio, estado, 'México'].filter(Boolean).join(', ');
  }

  function normalizeGeoText(raw){
    return String(raw || '')
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .toLowerCase()
      .trim();
  }

  function getConsultorioAddressPartsByIds(ids){
    const read = (id)=> String(document.getElementById(id)?.value || '').trim();
    return {
      cp: read(ids.cp),
      colonia: read(ids.colonia),
      municipio: read(ids.municipio),
      estado: read(ids.estado),
      calle: read(ids.calle),
      num_ext: read(ids.num_ext),
      num_int: read(ids.num_int)
    };
  }

  function resolveConsultorioContextFallback(parts = {}){
    const estado = normalizeGeoText(parts.estado || '');
    const municipio = normalizeGeoText(parts.municipio || '');
    // No usar CDMX por default cuando no hay contexto.
    // Preferir fallback contextual por estado/municipio, si no existe usar centro neutro de México.
    if(estado.includes('aguascalientes') || municipio.includes('aguascalientes')){
      return { lat: 21.882, lng: -102.296, zoom: 13, label: 'aguascalientes_context' };
    }
    if(estado.includes('ciudad de mexico') || estado.includes('cdmx') || estado.includes('distrito federal')){
      return { lat: 19.4326, lng: -99.1332, zoom: 12, label: 'cdmx_context' };
    }
    return { lat: 23.6345, lng: -102.5528, zoom: 6, label: 'mx_neutral' };
  }

  function setConsultorioMapFrameByAddress(frame, address, zoom = 13){
    if(!frame) return;
    const safeAddress = String(address || '').trim();
    if(!safeAddress) return;
    const url = `https://www.google.com/maps?q=${encodeURIComponent(safeAddress)}&z=${encodeURIComponent(String(zoom))}&output=embed`;
    if(frame.src !== url){
      frame.src = url;
    }
  }

  function setConsultorioMapFrameByLatLng(frame, lat, lng, zoom = 17){
    if(!frame) return;
    if(!Number.isFinite(lat) || !Number.isFinite(lng)) return;
    const point = `${lat.toFixed(6)},${lng.toFixed(6)}`;
    const url = `https://www.google.com/maps?q=${encodeURIComponent(point)}&z=${encodeURIComponent(String(zoom))}&output=embed`;
    if(frame.src !== url){
      frame.src = url;
    }
  }

  function buildGoogleMapEmbedUrlByAddress(address, zoom = 15){
    const safeAddress = String(address || '').trim();
    if(!safeAddress) return '';
    return `https://www.google.com/maps?q=${encodeURIComponent(safeAddress)}&z=${encodeURIComponent(String(zoom))}&output=embed`;
  }

  function buildGoogleMapEmbedUrlByLatLng(lat, lng, zoom = 17){
    if(!Number.isFinite(lat) || !Number.isFinite(lng)) return '';
    const point = `${Number(lat).toFixed(6)},${Number(lng).toFixed(6)}`;
    return `https://www.google.com/maps?q=${encodeURIComponent(point)}&z=${encodeURIComponent(String(zoom))}&output=embed`;
  }

  function ensureConsultorioMapStatusNode(frame){
    if(!frame) return null;
    const host = frame.closest('.col-12');
    if(!host) return null;
    const selector = `[data-map-geocode-status-for="${frame.id}"]`;
    let node = host.querySelector(selector);
    if(node) return node;
    node = document.createElement('div');
    node.className = 'form-text d-none';
    node.setAttribute('data-map-geocode-status-for', frame.id);
    host.appendChild(node);
    return node;
  }

  function ensureConsultorioMapAddressDebugNode(frame){
    if(!frame) return null;
    const host = frame.closest('.col-12');
    if(!host) return null;
    const selector = `[data-map-address-debug-for="${frame.id}"]`;
    let node = host.querySelector(selector);
    if(node) return node;
    node = document.createElement('div');
    node.className = 'form-text text-muted';
    node.setAttribute('data-map-address-debug-for', frame.id);
    node.textContent = '';
    host.appendChild(node);
    return node;
  }

  function ensureConsultorioMapControls(frame, onConfirm, onRecalculate){
    if(!frame) return null;
    const host = frame.closest('.col-12');
    if(!host) return null;
    const selector = `[data-map-controls-for="${frame.id}"]`;
    let controls = host.querySelector(selector);
    if(!controls){
      controls = document.createElement('div');
      controls.className = 'd-flex flex-wrap align-items-center justify-content-between gap-2 mt-2';
      controls.setAttribute('data-map-controls-for', frame.id);
      const helper = document.createElement('div');
      helper.className = 'form-text m-0';
      helper.setAttribute('data-map-helper-for', frame.id);
      helper.textContent = 'Si la ubicación no es exacta, arrastra el pin al punto correcto y confirma la ubicación.';
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'btn btn-outline-primary btn-sm';
      btn.setAttribute('data-map-confirm-for', frame.id);
      btn.textContent = 'Confirmar ubicación';
      const recalcBtn = document.createElement('button');
      recalcBtn.type = 'button';
      recalcBtn.className = 'btn btn-outline-secondary btn-sm d-none';
      recalcBtn.setAttribute('data-map-recalc-for', frame.id);
      recalcBtn.textContent = 'Recalcular ubicación';
      const actions = document.createElement('div');
      actions.className = 'd-flex flex-wrap align-items-center gap-2';
      actions.appendChild(recalcBtn);
      actions.appendChild(btn);
      controls.appendChild(helper);
      controls.appendChild(actions);
      host.appendChild(controls);
    }
    const button = controls.querySelector(`[data-map-confirm-for="${frame.id}"]`);
    const recalcButton = controls.querySelector(`[data-map-recalc-for="${frame.id}"]`);
    if(button && typeof onConfirm === 'function'){
      button.onclick = onConfirm;
    }
    if(recalcButton && typeof onRecalculate === 'function'){
      recalcButton.onclick = onRecalculate;
    }
    return { controls, button, recalcButton };
  }

  function buildGeocodeQueriesByIds(ids){
    const read = (id)=> String(document.getElementById(id)?.value || '').trim();
    const calle = read(ids.calle);
    const numExt = read(ids.num_ext);
    const colonia = read(ids.colonia);
    const cp = read(ids.cp);
    const municipio = read(ids.municipio);
    const estado = read(ids.estado);
    const calleNum = [calle, numExt].filter(Boolean).join(' ').trim();
    const out = [];
    const pushQuery = (parts, required = [])=>{
      if(!required.some((v)=> String(v || '').trim() !== '')) return;
      const query = parts.map((v)=> String(v || '').trim()).filter(Boolean).join(', ');
      if(query !== '' && !out.includes(query)){
        out.push(query);
      }
    };
    pushQuery([calleNum, colonia, cp, municipio, estado, 'México'], [calleNum]);              // 1
    pushQuery([calle, colonia, cp, municipio, estado, 'México'], [calle]);                    // 2
    pushQuery([colonia, cp, municipio, estado, 'México'], [colonia, cp]);                     // 3
    pushQuery([cp, municipio, estado, 'México'], [cp]);                                        // 4
    return out;
  }

  async function geocodeConsultorioAddress(address, ids = {}){
    const qPrimary = String(address || '').trim();
    const queryList = buildGeocodeQueriesByIds(ids);
    if(qPrimary && !queryList.includes(qPrimary)){
      queryList.unshift(qPrimary);
    }
    if(!queryList.length){
      return null;
    }
    const expectedParts = getConsultorioAddressPartsByIds(ids);
    const expectedState = normalizeGeoText(expectedParts.estado || '');
    const expectedMunicipio = normalizeGeoText(expectedParts.municipio || '');
    const expectedCp = String(expectedParts.cp || '').trim();
    const expectedColonia = normalizeGeoText(expectedParts.colonia || '');
    const expectedStreet = normalizeGeoText([expectedParts.calle || '', expectedParts.num_ext || ''].filter(Boolean).join(' ').trim());

    const classifyCandidate = (item = {}, query = '', endpoint = '', requestUrl = '')=>{
      const lat = Number(item?.lat);
      const lng = Number(item?.lon);
      if(!Number.isFinite(lat) || !Number.isFinite(lng)) return null;
      const addr = (item && typeof item.address === 'object') ? item.address : {};
      const displayName = String(item?.display_name || '').trim();
      const typeRaw = String(item?.type || '').trim().toLowerCase();
      const classRaw = String(item?.class || '').trim().toLowerCase();
      const importance = Number(item?.importance);
      const hasPostcode = !!String(addr?.postcode || '').trim();
      const hasColonia = [
        addr?.suburb,
        addr?.neighbourhood,
        addr?.quarter,
        addr?.city_district,
        addr?.residential,
        addr?.hamlet
      ].some((v)=> String(v || '').trim() !== '');
      const hasStreet = [
        addr?.road,
        addr?.pedestrian,
        addr?.footway,
        addr?.path,
        addr?.house_number
      ].some((v)=> String(v || '').trim() !== '');
      const hasHouseNumber = String(addr?.house_number || '').trim() !== '';
      const genericType = ['city', 'municipality', 'state', 'county', 'province', 'town', 'village', 'administrative'];
      const isGeneric = !hasStreet && !hasColonia && !hasPostcode
        && (genericType.includes(typeRaw) || classRaw === 'boundary' || classRaw === 'place');
      const isPrecise = (hasColonia || hasPostcode) && !isGeneric;
      let score = 0;
      if(hasColonia) score += 30;
      if(hasPostcode) score += 25;
      if(hasStreet) score += 35;
      if(hasHouseNumber) score += 20;
      if(Number.isFinite(importance)) score += Math.round(Math.max(0, Math.min(1, importance)) * 10);
      const displayNorm = normalizeGeoText(displayName);
      const stateNorm = normalizeGeoText(addr?.state || addr?.state_district || '');
      const cityNorm = normalizeGeoText(
        addr?.city || addr?.town || addr?.village || addr?.municipality || addr?.county || addr?.city_district || ''
      );
      const suburbNorm = normalizeGeoText(
        addr?.suburb || addr?.neighbourhood || addr?.quarter || addr?.residential || ''
      );
      const postcodeRaw = String(addr?.postcode || '').trim();
      const haystack = [displayNorm, stateNorm, cityNorm, suburbNorm].join(' ');
      const matchesState = expectedState ? haystack.includes(expectedState) : null;
      const matchesMunicipio = expectedMunicipio ? haystack.includes(expectedMunicipio) : null;
      const matchesCp = expectedCp ? (postcodeRaw === expectedCp || displayNorm.includes(expectedCp)) : null;
      const matchesColonia = expectedColonia ? haystack.includes(expectedColonia) : null;
      const matchesStreet = expectedStreet ? haystack.includes(normalizeGeoText(expectedParts.calle || '')) : null;
      let contextScore = 0;
      if(matchesState === true) contextScore += 60;
      if(matchesState === false) contextScore -= 120;
      if(matchesMunicipio === true) contextScore += 35;
      if(matchesMunicipio === false) contextScore -= 45;
      if(matchesCp === true) contextScore += 40;
      if(matchesCp === false) contextScore -= 18;
      if(matchesColonia === true) contextScore += 25;
      if(matchesStreet === true) contextScore += 18;
      score += contextScore;
      const contextConflict = (
        (expectedState !== '' && matchesState === false)
        || (expectedState === '' && expectedCp !== '' && matchesCp === false)
      );
      return {
        lat,
        lng,
        display_name: displayName,
        importance: Number.isFinite(importance) ? importance : null,
        type: typeRaw,
        class: classRaw,
        has_colonia: hasColonia,
        has_cp: hasPostcode,
        has_street: hasStreet,
        has_house_number: hasHouseNumber,
        is_precise: isPrecise,
        is_generic: isGeneric,
        score,
        context_score: contextScore,
        context_conflict: contextConflict,
        matches_state: matchesState,
        matches_municipio: matchesMunicipio,
        matches_cp: matchesCp,
        matches_colonia: matchesColonia,
        matches_street: matchesStreet,
        query,
        endpoint,
        request_url: requestUrl,
        raw: item
      };
    };

    const allCandidates = [];
    const pushCandidatesFromResponse = (data, query, endpoint, requestUrl)=>{
      const list = Array.isArray(data) ? data : [];
      list.forEach((item)=>{
        const candidate = classifyCandidate(item, query, endpoint, requestUrl);
        if(candidate) allCandidates.push(candidate);
      });
      const first = allCandidates[allCandidates.length - list.length] || null;
      try{
        console.warn('CONSULTORIO MAP GEOCODE RESULT DEBUG:', {
          action: 'response',
          direction_sent: query,
          endpoint,
          request_url: requestUrl,
          response: data,
          lat_lng: first ? { lat: first.lat, lng: first.lng } : null,
          display_name: first?.display_name || '',
          importance: first?.importance ?? null,
          type: first?.type || '',
          class: first?.class || '',
          contains_colonia: !!first?.has_colonia,
          contains_cp: !!first?.has_cp,
          contains_street: !!first?.has_street
        });
      }catch(_){ }
      return list.length > 0;
    };

    const fetchJson = async (url, headers = {})=>{
      const ctrl = new AbortController();
      const timeout = window.setTimeout(()=> ctrl.abort(), 9000);
      try{
        const resp = await fetch(url, {
          method: 'GET',
          headers: { 'Accept': 'application/json', ...headers },
          signal: ctrl.signal
        });
        if(!resp.ok) return null;
        return await resp.json().catch(()=> null);
      }catch(_){
        return null;
      }finally{
        window.clearTimeout(timeout);
      }
    };

    for(const query of queryList){
      const proxyUrl = `./geocode-proxy.php?q=${encodeURIComponent(query)}`;
      const proxyData = await fetchJson(proxyUrl);
      const gotProxy = pushCandidatesFromResponse(proxyData, query, 'proxy', proxyUrl);
      if(!gotProxy){
        const params = new URLSearchParams({
          q: query,
          format: 'jsonv2',
          addressdetails: '1',
          limit: '5',
          countrycodes: 'mx'
        });
        const directUrl = `https://nominatim.openstreetmap.org/search?${params.toString()}`;
        const directData = await fetchJson(directUrl, {
          'Accept-Language': 'es-MX,es;q=0.9,en;q=0.8'
        });
        pushCandidatesFromResponse(directData, query, 'direct', directUrl);
      }
    }

    if(!allCandidates.length){
      try{
        console.warn('CONSULTORIO MAP GEOCODE RESULT DEBUG:', {
          action: 'no_candidates',
          directions_sent: queryList
        });
      }catch(_){ }
      return null;
    }

    const contextFiltered = allCandidates.filter((c)=> !c.context_conflict);
    const usable = contextFiltered.length ? contextFiltered : allCandidates;
    const precise = usable.filter((c)=> c.is_precise).sort((a, b)=> b.score - a.score);
    const generic = usable.filter((c)=> !c.is_precise).sort((a, b)=> b.score - a.score);
    const chosen = precise[0] || generic[0];
    if(!chosen){
      return null;
    }
    if(chosen.context_conflict){
      try{
        console.warn('CONSULTORIO MAP GEOCODE RESULT DEBUG:', {
          action: 'selected_conflict_discarded',
          expected: {
            estado: expectedParts.estado || '',
            municipio: expectedParts.municipio || '',
            cp: expectedParts.cp || ''
          },
          selected: {
            display_name: chosen?.display_name || '',
            matches_state: chosen?.matches_state,
            matches_municipio: chosen?.matches_municipio
          }
        });
      }catch(_){ }
      return null;
    }
    try{
      console.warn('CONSULTORIO MAP GEOCODE RESULT DEBUG:', {
        action: 'selected',
        directions_sent: queryList,
        expected: {
          estado: expectedParts.estado || '',
          municipio: expectedParts.municipio || '',
          cp: expectedParts.cp || '',
          colonia: expectedParts.colonia || ''
        },
        selected: {
          lat: chosen?.lat,
          lng: chosen?.lng,
          display_name: chosen?.display_name || '',
          importance: chosen?.importance ?? null,
          type: chosen?.type || '',
          class: chosen?.class || '',
          contains_colonia: !!chosen?.has_colonia,
          contains_cp: !!chosen?.has_cp,
          contains_street: !!chosen?.has_street,
          is_precise: !!chosen?.is_precise,
          is_generic: !!chosen?.is_generic,
          score: chosen?.score ?? null,
          context_score: chosen?.context_score ?? null,
          context_conflict: !!chosen?.context_conflict,
          matches_state: chosen?.matches_state,
          matches_municipio: chosen?.matches_municipio,
          matches_cp: chosen?.matches_cp,
          matches_colonia: chosen?.matches_colonia,
          endpoint: chosen?.endpoint || '',
          request_url: chosen?.request_url || '',
          direction_sent: chosen?.query || ''
        }
      });
    }catch(_){ }

    if(!chosen) return null;
    return {
      lat: chosen.lat,
      lng: chosen.lng,
      precise: !!chosen.is_precise,
      fallback: !chosen.is_precise,
      display_name: chosen.display_name || '',
      importance: chosen.importance,
      type: chosen.type || '',
      class: chosen.class || '',
      has_colonia: !!chosen.has_colonia,
      has_cp: !!chosen.has_cp,
      has_street: !!chosen.has_street,
      endpoint: chosen.endpoint || '',
      query: chosen.query || '',
      request_url: chosen.request_url || ''
    };
  }

  function bindConsultorioMapByIndex(index = 1){
    const ids = resolveConsultorioMapIdsByIndex(index);
    const frame = document.getElementById(ids.frame);
    if(!frame) return;
    if(frame.dataset.mapGeocodeBound === '1'){
      try{
        console.warn('CONSULTORIO MAP ZOOM DEBUG:', {
          action: 'bind.skip_already_bound',
          map_id: ids.frame,
          source: 'bind'
        });
      }catch(_){ }
      return;
    }
    frame.dataset.mapGeocodeBound = '1';
    const state = setConsultorioGeoState(ids.index, { index: ids.index });
    const statusNode = ensureConsultorioMapStatusNode(frame);
    const addressDebugNode = ensureConsultorioMapAddressDebugNode(frame);
    const showStatus = (text = '', type = 'info')=>{
      if(!statusNode) return;
      const safe = String(text || '').trim();
      statusNode.textContent = safe;
      statusNode.classList.remove('d-none', 'text-warning', 'text-success', 'text-muted');
      if(!safe){
        statusNode.classList.add('d-none');
        return;
      }
      if(type === 'warning') statusNode.classList.add('text-warning');
      else if(type === 'success') statusNode.classList.add('text-success');
      else statusNode.classList.add('text-muted');
    };
    const readAddressParts = ()=>{
      const parts = getConsultorioAddressPartsByIds(ids);
      return {
        cp: parts.cp,
        colonia: parts.colonia,
        municipio: parts.municipio,
        estado: parts.estado,
        calle: parts.calle,
        num_ext: parts.num_ext
      };
    };
    const setAddressDebugUI = (payload = {})=>{
      if(!addressDebugNode) return;
      const safeAddress = String(payload.address || '').trim();
      if(!safeAddress){
        addressDebugNode.textContent = '';
        return;
      }
      const reason = String(payload.reason || '').trim();
      const cp = String(payload.cp || '').trim();
      const colonia = String(payload.colonia || '').trim();
      const municipio = String(payload.municipio || '').trim();
      const estado = String(payload.estado || '').trim();
      const calle = String(payload.calle || '').trim();
      const numExt = String(payload.num_ext || '').trim();
      addressDebugNode.textContent = `Dirección enviada (${reason || 'map'}): ${safeAddress} · CP:${cp} · Colonia:${colonia} · Municipio:${municipio} · Estado:${estado} · Calle:${calle} ${numExt}`.trim();
    };
    const logAddressDebug = (reason = 'unknown', address = '')=>{
      try{
        const addr = readAddressParts();
        const finalAddress = String(address || buildConsultorioAddressByIds(ids) || '').trim();
        console.warn('CONSULTORIO MAP ADDRESS DEBUG:', {
          consultorio_id: String(ids.index),
          sede_index: Number(ids.index || 1),
          field_ids: {
            cp: ids.cp,
            colonia: ids.colonia,
            municipio: ids.municipio,
            estado: ids.estado,
            calle: ids.calle,
            num_ext: ids.num_ext
          },
          cp: addr.cp,
          colonia: addr.colonia,
          municipio: addr.municipio,
          estado: addr.estado,
          calle: addr.calle,
          num_ext: addr.num_ext,
          address_final: finalAddress,
          reason
        });
        setAddressDebugUI({
          reason,
          address: finalAddress,
          ...addr
        });
      }catch(_){ }
    };
    const logMapSourceDebug = (reason = 'unknown', extra = {})=>{
      try{
        const addr = readAddressParts();
        const geo = getConsultorioGeoState(ids.index);
        console.warn('CONSULTORIO MAP SOURCE DEBUG:', {
          consultorio_id: String(ids.index),
          sede_index: Number(ids.index || 1),
          ...addr,
          lat: toFiniteNumber(geo.lat),
          lng: toFiniteNumber(geo.lng),
          pending_lat: toFiniteNumber(geo.pending_lat),
          pending_lng: toFiniteNumber(geo.pending_lng),
          geocode_source: normalizeGeocodeSource(geo.source || ''),
          manual_confirmed: !!geo.manual_confirmed,
          manual_dirty: !!geo.manual_dirty,
          requires_recalc: !!geo.requires_recalc,
          reason,
          ...extra
        });
      }catch(_){ }
    };

    let leafletMap = null;
    let leafletMarker = null;
    const ratio = frame.parentElement;
    const mapKey = String(ids.index);
    let geocodeSeq = 0;
    let renderProbeTimer = 0;
    let lastRenderProbeStartedAt = 0;
    let lastViewState = { lat: null, lng: null, zoom: 13 };
    const clearLastViewState = ()=>{
      lastViewState = { lat: null, lng: null, zoom: 13 };
    };
    const isGeocodeInFlight = ()=> !!getConsultorioGeoState(ids.index)?.geocode_inflight;
    const isNoisyProbeSource = (source = '')=>{
      const raw = String(source || '').toLowerCase();
      return raw.includes('resize_observer')
        || raw.includes('window_resize')
        || raw.includes('mutation');
    };
    const shouldSkipSetView = (lat, lng, zoom)=>{
      if(!(leafletMap && typeof leafletMap.getCenter === 'function' && typeof leafletMap.getZoom === 'function')){
        return false;
      }
      const center = leafletMap.getCenter();
      const currentZoom = leafletMap.getZoom();
      const z = Number.isFinite(Number(zoom)) ? Number(zoom) : currentZoom;
      if(!center || !Number.isFinite(center.lat) || !Number.isFinite(center.lng)) return false;
      const sameLat = Math.abs(Number(center.lat) - Number(lat)) < 0.000005;
      const sameLng = Math.abs(Number(center.lng) - Number(lng)) < 0.000005;
      const sameZoom = Number(currentZoom) === Number(z);
      return sameLat && sameLng && sameZoom;
    };
    const getRenderViewportState = ()=>{
      const rect = ratio?.getBoundingClientRect?.() || { width: 0, height: 0 };
      const paneEl = frame.closest('.tab-pane');
      const consultorioPanel = document.getElementById('p-consultorio');
      const paneVisible = !paneEl || paneEl.classList.contains('show') || paneEl.classList.contains('active');
      const panelVisible = !consultorioPanel || !consultorioPanel.classList.contains('d-none');
      const hasSize = Number(rect.width || 0) > 24 && Number(rect.height || 0) > 24;
      return {
        pane_visible: paneVisible,
        panel_visible: panelVisible,
        width: Number(rect.width || 0),
        height: Number(rect.height || 0),
        has_size: hasSize,
        can_render: paneVisible && panelVisible && hasSize
      };
    };
    const logRenderDebug = (payload = {})=>{
      try{
        console.warn('CONSULTORIO MAP RENDER DEBUG:', {
          map_id: ids.frame,
          ...payload
        });
      }catch(_){ }
    };
    const logZoomDebug = (payload = {})=>{
      try{
        const mapZoom = (leafletMap && typeof leafletMap.getZoom === 'function')
          ? leafletMap.getZoom()
          : null;
        console.warn('CONSULTORIO MAP ZOOM DEBUG:', {
          map_id: ids.frame,
          ...payload,
          map_zoom_now: mapZoom
        });
      }catch(_){ }
    };
    const logSetView = (lat, lng, zoom, source, extra = {})=>{
      if(!(leafletMap && typeof leafletMap.setView === 'function')) return;
      const before = (typeof leafletMap.getZoom === 'function') ? leafletMap.getZoom() : null;
      if(shouldSkipSetView(lat, lng, zoom)){
        logZoomDebug({
          action: 'setView.skip_same_target',
          source,
          lat,
          lng,
          zoom_requested: zoom,
          zoom_before: before,
          ...extra
        });
        return;
      }
      logZoomDebug({
        action: 'setView.before',
        source,
        lat,
        lng,
        zoom_requested: zoom,
        zoom_before: before,
        ...extra
      });
      try{
        leafletMap.setView([lat, lng], zoom, { animate: true });
      }catch(_){ }
      const after = (typeof leafletMap.getZoom === 'function') ? leafletMap.getZoom() : null;
      logZoomDebug({
        action: 'setView.after',
        source,
        lat,
        lng,
        zoom_requested: zoom,
        zoom_after: after,
        ...extra
      });
    };
    const logSetZoom = (zoom, source, extra = {})=>{
      if(!(leafletMap && typeof leafletMap.setZoom === 'function')) return;
      const before = (typeof leafletMap.getZoom === 'function') ? leafletMap.getZoom() : null;
      logZoomDebug({
        action: 'setZoom.before',
        source,
        zoom_requested: zoom,
        zoom_before: before,
        ...extra
      });
      try{
        leafletMap.setZoom(zoom, { animate: false });
      }catch(_){ }
      const after = (typeof leafletMap.getZoom === 'function') ? leafletMap.getZoom() : null;
      logZoomDebug({
        action: 'setZoom.after',
        source,
        zoom_requested: zoom,
        zoom_after: after,
        ...extra
      });
    };
    const runForcedPostRenderZoom = (lat, lng, source = 'unknown')=>{
      if(!(leafletMap && Number.isFinite(lat) && Number.isFinite(lng))) return;
      logZoomDebug({
        action: 'force_zoom.sequence.start',
        source,
        lat,
        lng,
        zoom_requested: 17
      });
      const before = (typeof leafletMap.getZoom === 'function') ? leafletMap.getZoom() : null;
      logZoomDebug({
        action: 'invalidateSize.before',
        source: `${source}:force`,
        zoom_before: before
      });
      try{ leafletMap.invalidateSize(false); }catch(_){ }
      const afterInvalidate = (typeof leafletMap.getZoom === 'function') ? leafletMap.getZoom() : null;
      logZoomDebug({
        action: 'invalidateSize.after',
        source: `${source}:force`,
        zoom_after: afterInvalidate
      });
      if(typeof leafletMap.setView === 'function'){
        const zoomBefore = (typeof leafletMap.getZoom === 'function') ? leafletMap.getZoom() : null;
        logZoomDebug({
          action: 'setView.before',
          source: `${source}:force`,
          lat,
          lng,
          zoom_requested: 17,
          zoom_before: zoomBefore
        });
        try{ leafletMap.setView([lat, lng], 17, { animate: false }); }catch(_){ }
        const zoomAfter = (typeof leafletMap.getZoom === 'function') ? leafletMap.getZoom() : null;
        logZoomDebug({
          action: 'setView.after',
          source: `${source}:force`,
          lat,
          lng,
          zoom_requested: 17,
          zoom_after: zoomAfter
        });
      }
      logSetZoom(17, `${source}:force`);
      window.setTimeout(()=>{
        if(!(leafletMap && typeof leafletMap.setView === 'function')) return;
        const zBefore = (typeof leafletMap.getZoom === 'function') ? leafletMap.getZoom() : null;
        logZoomDebug({
          action: 'setView.before',
          source: `${source}:force_timeout_250`,
          lat,
          lng,
          zoom_requested: 17,
          zoom_before: zBefore
        });
        try{ leafletMap.setView([lat, lng], 17, { animate: false }); }catch(_){ }
        const zAfter = (typeof leafletMap.getZoom === 'function') ? leafletMap.getZoom() : null;
        logZoomDebug({
          action: 'setView.after',
          source: `${source}:force_timeout_250`,
          lat,
          lng,
          zoom_requested: 17,
          zoom_after: zAfter
        });
      }, 250);
    };
    const leafletReady = !!(window.L && typeof window.L.map === 'function' && ratio);
    const reapplyLastView = (delay = 150)=>{
      if(!(leafletMap && typeof leafletMap.setView === 'function')) return;
      if(isGeocodeInFlight()){
        logZoomDebug({
          action: 'setView.skip_geocode_inflight',
          source: 'reapply_after_invalidate'
        });
        return;
      }
      if(!Number.isFinite(lastViewState.lat) || !Number.isFinite(lastViewState.lng)) return;
      const zoom = Number.isFinite(lastViewState.zoom) ? lastViewState.zoom : 17;
      if(shouldSkipSetView(lastViewState.lat, lastViewState.lng, zoom)){
        logZoomDebug({
          action: 'setView.skip_same_target',
          source: 'reapply_after_invalidate',
          lat: lastViewState.lat,
          lng: lastViewState.lng,
          zoom_requested: zoom
        });
        return;
      }
      window.setTimeout(()=>{
        const before = (typeof leafletMap.getZoom === 'function') ? leafletMap.getZoom() : null;
        logZoomDebug({
          action: 'setView.before',
          source: 'reapply_after_invalidate',
          lat: lastViewState.lat,
          lng: lastViewState.lng,
          zoom_requested: zoom,
          zoom_before: before
        });
        try{
          leafletMap.setView([lastViewState.lat, lastViewState.lng], zoom, { animate: false });
        }catch(_){ }
        const after = (typeof leafletMap.getZoom === 'function') ? leafletMap.getZoom() : null;
        logZoomDebug({
          action: 'setView.after',
          source: 'reapply_after_invalidate',
          lat: lastViewState.lat,
          lng: lastViewState.lng,
          zoom_requested: zoom,
          zoom_after: after
        });
      }, Math.max(0, Number(delay || 0)));
    };
    const safeInvalidate = (source = 'unknown')=>{
      if(!(leafletMap && typeof leafletMap.invalidateSize === 'function')) return;
      const before = (typeof leafletMap.getZoom === 'function') ? leafletMap.getZoom() : null;
      logZoomDebug({
        action: 'invalidateSize.before',
        source,
        zoom_before: before
      });
      try{
        window.requestAnimationFrame(()=>{
          try{ leafletMap.invalidateSize(false); }catch(_){ }
          const zoomAfterRaf = (typeof leafletMap.getZoom === 'function') ? leafletMap.getZoom() : null;
          logZoomDebug({
            action: 'invalidateSize.after_raf',
            source,
            zoom_after: zoomAfterRaf
          });
        });
      }catch(_){ }
      window.setTimeout(()=>{
        try{ leafletMap.invalidateSize(false); }catch(_){ }
        const zoomAfter120 = (typeof leafletMap.getZoom === 'function') ? leafletMap.getZoom() : null;
        logZoomDebug({
          action: 'invalidateSize.after_120',
          source,
          zoom_after: zoomAfter120
        });
      }, 120);
      window.setTimeout(()=>{
        try{ leafletMap.invalidateSize(false); }catch(_){ }
        const zoomAfter260 = (typeof leafletMap.getZoom === 'function') ? leafletMap.getZoom() : null;
        logZoomDebug({
          action: 'invalidateSize.after_260',
          source,
          zoom_after: zoomAfter260
        });
      }, 260);
      if(isGeocodeInFlight()){
        logZoomDebug({
          action: 'reapply.skip_geocode_inflight',
          source
        });
      }else{
        reapplyLastView(150);
      }
    };
    const scheduleRenderProbe = (source = 'unknown', maxAttempts = 10)=>{
      if(isNoisyProbeSource(source)){
        const now = Date.now();
        if(now - lastRenderProbeStartedAt < 320){
          return;
        }
        lastRenderProbeStartedAt = now;
      }
      if(renderProbeTimer){
        window.clearTimeout(renderProbeTimer);
        renderProbeTimer = 0;
      }
      let attempts = 0;
      const run = ()=>{
        attempts += 1;
        const state = getRenderViewportState();
        logRenderDebug({
          action: 'probe',
          source,
          attempt: attempts,
          ...state
        });
        if(leafletMap && state.can_render){
          safeInvalidate(`${source}:visible`);
          if(!isNoisyProbeSource(source)){
            window.setTimeout(()=> safeInvalidate(`${source}:visible_120`), 120);
            window.setTimeout(()=> safeInvalidate(`${source}:visible_260`), 260);
          }
          renderProbeTimer = 0;
          return;
        }
        if(attempts >= Math.max(1, Number(maxAttempts || 1))){
          renderProbeTimer = 0;
          return;
        }
        renderProbeTimer = window.setTimeout(run, 140);
      };
      run();
    };
    if(leafletReady){
      let mapNode = ratio.querySelector(`[data-map-leaflet-for="${frame.id}"]`);
      if(!mapNode){
        mapNode = document.createElement('div');
        mapNode.setAttribute('data-map-leaflet-for', frame.id);
        mapNode.style.width = '100%';
        mapNode.style.height = '100%';
        mapNode.style.position = 'absolute';
        mapNode.style.top = '0';
        mapNode.style.left = '0';
        mapNode.style.zIndex = '2';
        ratio.appendChild(mapNode);
      }
      frame.style.position = 'absolute';
      frame.style.top = '0';
      frame.style.left = '0';
      frame.style.zIndex = '1';
      try{
        if(mapNode._mxLeafletMap && mapNode._mxLeafletMarker){
          leafletMap = mapNode._mxLeafletMap;
          leafletMarker = mapNode._mxLeafletMarker;
          logZoomDebug({
            action: 'map.reused',
            source: 'init',
            zoom_after: (typeof leafletMap.getZoom === 'function') ? leafletMap.getZoom() : null
          });
        }else{
          leafletMap = window.L.map(mapNode, { zoomControl: true }).setView([21.882, -102.296], 13);
          window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(leafletMap);
          leafletMarker = window.L.marker([21.882, -102.296], { draggable: true }).addTo(leafletMap);
          mapNode._mxLeafletMap = leafletMap;
          mapNode._mxLeafletMarker = leafletMarker;
          logZoomDebug({
            action: 'map.created',
            source: 'init',
            lat: 21.882,
            lng: -102.296,
            zoom_requested: 13,
            zoom_after: (typeof leafletMap.getZoom === 'function') ? leafletMap.getZoom() : null
          });
        }
        frame.classList.add('d-none');
        scheduleRenderProbe('init');
      }catch(err){
        leafletMap = null;
        leafletMarker = null;
        frame.classList.remove('d-none');
        logZoomDebug({
          action: 'map.init_failed',
          source: 'init',
        });
        logRenderDebug({
          action: 'map.init_failed',
          source: 'init',
          error: String(err?.message || err || 'unknown')
        });
      }
    }

    const applyMapPoint = (lat, lng, zoom = 17, source = 'unknown')=>{
      if(!Number.isFinite(lat) || !Number.isFinite(lng)) return;
      lastViewState = { lat, lng, zoom };
      if(leafletMap && leafletMarker){
        const point = [lat, lng];
        leafletMarker.setLatLng(point);
        logSetView(lat, lng, zoom, source, { marker_updated: true });
        safeInvalidate(`apply:${source}`);
      }
      setConsultorioMapFrameByLatLng(frame, lat, lng, zoom);
    };

    const isAddressCompleteForGeocode = ()=> buildGeocodeQueriesByIds(ids).length > 0;
    const syncPublicMapSnapshot = (geoOverride = null)=>{
      const geo = (geoOverride && typeof geoOverride === 'object')
        ? geoOverride
        : getConsultorioGeoState(ids.index);
      const lat = toFiniteNumber(geo?.lat);
      const lng = toFiniteNumber(geo?.lng);
      const source = normalizeGeocodeSource(geo?.source || '');
      const address = buildConsultorioAddressByIds(ids);
      const hasConfirmedCoordinates = (
        Number.isFinite(lat)
        && Number.isFinite(lng)
        && source === 'manual_adjusted'
      );
      const confirmedIframeUrl = hasConfirmedCoordinates
        ? buildGoogleMapEmbedUrlByLatLng(lat, lng, 17)
        : '';
      const fallbackIframeUrl = hasConfirmedCoordinates
        ? ''
        : buildGoogleMapEmbedUrlByAddress(address, 15);
      const snapshot = {
        consultorio_index: Number(ids.index || 1),
        address_compact: address,
        lat: Number.isFinite(lat) ? Number(lat.toFixed(7)) : null,
        lng: Number.isFinite(lng) ? Number(lng.toFixed(7)) : null,
        geocode_source: source,
        geocode_updated_at: String(geo?.geocode_updated_at || '').trim() || null,
        has_confirmed_coordinates: !!hasConfirmedCoordinates,
        public_map_iframe_url: confirmedIframeUrl || fallbackIframeUrl || '',
        public_map_source: hasConfirmedCoordinates
          ? 'coordinates_confirmed'
          : (fallbackIframeUrl ? 'address_fallback' : 'none'),
      };
      try{
        if(!window.mxmedStore || typeof window.mxmedStore !== 'object'){
          window.mxmedStore = {};
        }
        if(!window.mxmedStore.consultorio_public_maps || typeof window.mxmedStore.consultorio_public_maps !== 'object'){
          window.mxmedStore.consultorio_public_maps = {};
        }
        const key = String(Number(ids.index || 1));
        window.mxmedStore.consultorio_public_maps[key] = snapshot;
        if(key === '1'){
          window.mxmedStore.public_map_iframe_url = snapshot.public_map_iframe_url;
          window.mxmedStore.public_map_source = snapshot.public_map_source;
        }
        if(window.mxmedStore.doctorProfile && typeof window.mxmedStore.doctorProfile === 'object'){
          const profile = window.mxmedStore.doctorProfile;
          profile.consultorio_map = snapshot;
          profile.public_map_iframe_url = snapshot.public_map_iframe_url;
          profile.public_map_source = snapshot.public_map_source;
          if(hasConfirmedCoordinates){
            if(!profile.address || typeof profile.address !== 'object'){
              profile.address = {};
            }
            profile.address.lat = snapshot.lat;
            profile.address.lng = snapshot.lng;
            profile.address.geocode_source = source;
            profile.address.geocode_updated_at = snapshot.geocode_updated_at;
          }
        }
      }catch(_){ }
    };
    const controls = ensureConsultorioMapControls(frame, ()=>{
      const current = getConsultorioGeoState(ids.index);
      let lat = toFiniteNumber(current.pending_lat);
      let lng = toFiniteNumber(current.pending_lng);
      if(!Number.isFinite(lat) || !Number.isFinite(lng)){
        lat = toFiniteNumber(current.lat);
        lng = toFiniteNumber(current.lng);
      }
      if(!Number.isFinite(lat) || !Number.isFinite(lng)){
        showStatus('No hay una ubicación para confirmar todavía.', 'warning');
        logMapSourceDebug('confirm_without_point');
        return;
      }
      const addressNow = buildConsultorioAddressByIds(ids);
      applyMapPoint(lat, lng, 17, 'confirm');
      setConsultorioGeoState(ids.index, {
        lat,
        lng,
        source: 'manual_adjusted',
        geocode_updated_at: new Date().toISOString(),
        manual_confirmed: true,
        manual_dirty: false,
        confirmed_address: addressNow,
        pending_lat: null,
        pending_lng: null,
      });
      syncPublicMapSnapshot({
        ...getConsultorioGeoState(ids.index),
        lat,
        lng,
        source: 'manual_adjusted',
      });
      showStatus('Ubicación ajustada manualmente.', 'success');
      logMapSourceDebug('confirm_manual_point', {
        lat_used: lat,
        lng_used: lng,
        chosen_by: 'manual'
      });
      const trigger = document.getElementById(ids.calle) || document.getElementById(ids.cp);
      if(trigger){
        try{ trigger.dispatchEvent(new Event('input', { bubbles:true })); }catch(_){ }
      }
    }, ()=>{
      const addressNow = buildConsultorioAddressByIds(ids);
      clearLastViewState();
      setConsultorioGeoState(ids.index, {
        lat: null,
        lng: null,
        source: '',
        requires_recalc: false,
        manual_confirmed: false,
        manual_dirty: false,
        pending_lat: null,
        pending_lng: null,
        confirmed_address: '',
        geocode_fallback: false,
        geocode_inflight: true,
      });
      logMapSourceDebug('recalculate_click', {
        address: addressNow,
        chosen_by: 'address'
      });
      resolveAndUpdate({ force: true }).catch(()=> null);
    });
    const confirmBtn = controls?.button || null;
    const recalcBtn = controls?.recalcButton || null;
    const refreshActionButtons = ()=>{
      const current = getConsultorioGeoState(ids.index);
      const hasAddress = buildConsultorioAddressByIds(ids) !== '';
      if(confirmBtn){
        confirmBtn.disabled = !(
          Number.isFinite(toFiniteNumber(current.pending_lat))
          && Number.isFinite(toFiniteNumber(current.pending_lng))
        );
      }
      if(recalcBtn){
        const shouldShowRecalc = !!(
          hasAddress && (
            current.requires_recalc
            || current.manual_confirmed
            || current.geocode_fallback
            || !Number.isFinite(toFiniteNumber(current.lat))
            || !Number.isFinite(toFiniteNumber(current.lng))
          )
        );
        recalcBtn.classList.toggle('d-none', !shouldShowRecalc);
      }
    };

    const markManualDirty = (lat, lng)=>{
      applyMapPoint(lat, lng, 18, 'manual');
      setConsultorioGeoState(ids.index, {
        pending_lat: lat,
        pending_lng: lng,
        manual_dirty: true,
        requires_recalc: false,
      });
      showStatus('Ubicación ajustada manualmente. Presiona “Confirmar ubicación” para guardar.', 'success');
      logMapSourceDebug('manual_drag', {
        lat_used: lat,
        lng_used: lng,
        chosen_by: 'manual'
      });
      refreshActionButtons();
    };

    if(leafletMarker){
      leafletMarker.on('dragend', ()=>{
        const ll = leafletMarker.getLatLng();
        if(!ll) return;
        markManualDirty(Number(ll.lat), Number(ll.lng));
      });
      leafletMap.on('click', (evt)=>{
        const ll = evt?.latlng;
        if(!ll) return;
        leafletMarker.setLatLng(ll);
        markManualDirty(Number(ll.lat), Number(ll.lng));
      });
    }

    const resolveAndUpdate = async ({ force = false } = {})=>{
      const address = buildConsultorioAddressByIds(ids);
      const current = getConsultorioGeoState(ids.index);
      logAddressDebug('resolve_start', address);
      logMapSourceDebug('resolve_enter', {
        address,
        force: !!force
      });
      if(address === ''){
        showStatus('', 'info');
        consultorioMapLastAddress.delete(String(ids.index));
        setConsultorioGeoState(ids.index, { geocode_inflight: false });
        syncPublicMapSnapshot();
        logMapSourceDebug('resolve_skip_empty_address');
        refreshActionButtons();
        return;
      }
      const prevAddress = consultorioMapLastAddress.get(String(ids.index));
      if(prevAddress === address && !current.manual_dirty && force !== true){
        setConsultorioGeoState(ids.index, { geocode_inflight: false });
        logMapSourceDebug('resolve_skip_same_address', { address });
        return;
      }
      consultorioMapLastAddress.set(String(ids.index), address);

      if(current.manual_confirmed && force !== true){
        if(current.confirmed_address !== address){
          setConsultorioGeoState(ids.index, {
            requires_recalc: true,
            geocode_inflight: false,
          });
          showStatus('La dirección cambió. Se conserva la ubicación manual hasta que pulses “Recalcular ubicación”.', 'warning');
          logMapSourceDebug('resolve_address_changed_manual_locked', {
            address,
            chosen_by: 'manual_locked'
          });
          refreshActionButtons();
          return;
        }
      }

      if(current.manual_confirmed && current.confirmed_address === address
        && Number.isFinite(current.lat) && Number.isFinite(current.lng)){
        applyMapPoint(Number(current.lat), Number(current.lng), 17, 'manual_saved');
        setConsultorioGeoState(ids.index, { geocode_inflight: false });
        showStatus('Ubicación ajustada manualmente.', 'success');
        logMapSourceDebug('resolve_use_manual_saved', {
          lat_used: Number(current.lat),
          lng_used: Number(current.lng),
          chosen_by: 'saved'
        });
        refreshActionButtons();
        return;
      }

      if(!isAddressCompleteForGeocode()){
        showStatus('Completa al menos calle/colonia o código postal para recalcular la ubicación.', 'info');
        setConsultorioGeoState(ids.index, { geocode_inflight: false });
        logMapSourceDebug('resolve_skip_incomplete_address', { address });
        refreshActionButtons();
        return;
      }

      const requestId = ++geocodeSeq;
      setConsultorioGeoState(ids.index, { geocode_inflight: true });
      logAddressDebug('recalculate_request', address);
      logMapSourceDebug('resolve_geocode_request', { address, request_id: requestId });
      const geo = await geocodeConsultorioAddress(address, ids);
      if(requestId !== geocodeSeq){
        logMapSourceDebug('resolve_geocode_stale_response', { request_id: requestId });
        return;
      }
      if(geo){
        if(geo.precise){
          setConsultorioGeoState(ids.index, {
            lat: geo.lat,
            lng: geo.lng,
            source: 'auto_geocoded',
            manual_dirty: false,
            manual_confirmed: false,
            confirmed_address: '',
            pending_lat: null,
            pending_lng: null,
            requires_recalc: false,
            geocode_fallback: false,
            geocode_inflight: false,
          });
          syncPublicMapSnapshot({
            ...getConsultorioGeoState(ids.index),
            lat: geo.lat,
            lng: geo.lng,
            source: 'auto_geocoded',
          });
          applyMapPoint(geo.lat, geo.lng, 17, 'geocode');
          runForcedPostRenderZoom(geo.lat, geo.lng, 'geocode');
          showStatus('', 'info');
          logMapSourceDebug('resolve_geocode_precise', {
            lat_used: geo.lat,
            lng_used: geo.lng,
            geocode_source: 'auto_geocoded',
            chosen_by: 'address',
            endpoint: geo.endpoint || '',
            query: geo.query || ''
          });
          refreshActionButtons();
          return;
        }
        // Resultado genérico (ciudad/municipio/estado): usar solo como referencia visual.
        setConsultorioGeoState(ids.index, {
          lat: geo.lat,
          lng: geo.lng,
          source: 'auto_geocoded',
          manual_dirty: false,
          manual_confirmed: false,
          confirmed_address: '',
          pending_lat: null,
          pending_lng: null,
          requires_recalc: false,
          geocode_fallback: true,
          geocode_inflight: false,
        });
        syncPublicMapSnapshot({
          ...getConsultorioGeoState(ids.index),
          lat: geo.lat,
          lng: geo.lng,
          source: 'auto_geocoded',
        });
        applyMapPoint(geo.lat, geo.lng, 13, 'geocode_fallback_generic');
        showStatus('No pudimos ubicar exactamente esta dirección. Ajusta el pin manualmente en el mapa.', 'warning');
        logMapSourceDebug('resolve_geocode_generic_fallback', {
          lat_used: geo.lat,
          lng_used: geo.lng,
          chosen_by: 'fallback',
          endpoint: geo.endpoint || '',
          query: geo.query || ''
        });
        refreshActionButtons();
        return;
      }

      if(!leafletMap){
        logZoomDebug({
          action: 'fallback.iframe_set_address',
          source: 'fallback',
          zoom_requested: 13
        });
        setConsultorioMapFrameByAddress(frame, address, 13);
      }
      if(current.manual_confirmed && Number.isFinite(current.lat) && Number.isFinite(current.lng)){
        applyMapPoint(Number(current.lat), Number(current.lng), 17, 'manual_restore_on_geocode_fail');
        setConsultorioGeoState(ids.index, { geocode_inflight: false });
        syncPublicMapSnapshot();
        showStatus('Ubicación ajustada manualmente.', 'success');
        logMapSourceDebug('resolve_geocode_fail_keep_manual', {
          lat_used: Number(current.lat),
          lng_used: Number(current.lng),
          chosen_by: 'manual'
        });
        refreshActionButtons();
        return;
      }
      const fallbackPoint = resolveConsultorioContextFallback(readAddressParts());
      setConsultorioGeoState(ids.index, {
        lat: fallbackPoint.lat,
        lng: fallbackPoint.lng,
        source: '',
        geocode_fallback: true,
        geocode_inflight: false,
        manual_dirty: false,
        manual_confirmed: false,
      });
      syncPublicMapSnapshot({
        ...getConsultorioGeoState(ids.index),
        lat: Number(fallbackPoint.lat),
        lng: Number(fallbackPoint.lng),
        source: '',
      });
      applyMapPoint(
        Number(fallbackPoint.lat),
        Number(fallbackPoint.lng),
        Number(fallbackPoint.zoom || 13),
        `geocode_fail_context_${fallbackPoint.label || 'fallback'}`
      );
      showStatus('No pudimos ubicar exactamente esta dirección. Puedes ajustar los datos o usar ubicación manual.', 'warning');
      logMapSourceDebug('resolve_geocode_fail_no_point', {
        chosen_by: 'fallback',
        lat_used: Number(fallbackPoint.lat),
        lng_used: Number(fallbackPoint.lng),
        fallback_label: fallbackPoint.label || ''
      });
      refreshActionButtons();
    };

    const debounce = (fn, ms = 700)=>{
      let timer = 0;
      return (...args)=>{
        window.clearTimeout(timer);
        timer = window.setTimeout(()=> fn(...args), ms);
      };
    };

    const debouncedResolve = debounce(resolveAndUpdate, 750);
    consultorioMapRefreshers.set(String(ids.index), debouncedResolve);

    [ids.cp, ids.colonia, ids.municipio, ids.estado, ids.calle, ids.num_ext, ids.num_int].forEach((id)=>{
      const field = document.getElementById(id);
      if(!field) return;
      field.addEventListener('input', ()=>{
        const addrNow = buildConsultorioAddressByIds(ids);
        const cur = getConsultorioGeoState(ids.index);
        if(cur.confirmed_address && cur.confirmed_address !== addrNow){
          setConsultorioGeoState(ids.index, {
            requires_recalc: !!cur.manual_confirmed,
          });
          logMapSourceDebug('address_input_mark_recalc', {
            address: addrNow,
            chosen_by: cur.manual_confirmed ? 'manual_locked' : 'address'
          });
        }else if(cur.confirmed_address && cur.confirmed_address === addrNow){
          setConsultorioGeoState(ids.index, {
            requires_recalc: false,
          });
        }
        debouncedResolve();
      });
      field.addEventListener('change', debouncedResolve);
    });

    const pane = frame.closest('.tab-pane');
    if(pane && pane.id){
      const tabTriggers = Array.from(document.querySelectorAll(`[data-bs-target="#${pane.id}"], [href="#${pane.id}"]`));
      tabTriggers.forEach((el)=>{
        if(!el || el.dataset.mapInvalidateBound === '1') return;
        el.dataset.mapInvalidateBound = '1';
        el.addEventListener('shown.bs.tab', ()=>{
          safeInvalidate('tab_shown');
          scheduleRenderProbe('tab_shown', 6);
        });
      });
    }
    window.addEventListener('resize', ()=>{
      safeInvalidate('window_resize');
      scheduleRenderProbe('window_resize', 4);
    }, { passive: true });
    if(typeof window.ResizeObserver === 'function' && ratio && !ratio._mxMapResizeObserverBound){
      ratio._mxMapResizeObserverBound = '1';
      const resizeObserver = new window.ResizeObserver(()=>{
        scheduleRenderProbe('resize_observer', 4);
      });
      try{ resizeObserver.observe(ratio); }catch(_){ }
    }
    const consultorioPanel = document.getElementById('p-consultorio');
    if(typeof window.MutationObserver === 'function' && consultorioPanel && !consultorioPanel._mxMapMutationObserverBound){
      consultorioPanel._mxMapMutationObserverBound = '1';
      const panelObserver = new window.MutationObserver(()=>{
        scheduleRenderProbe('consultorio_panel_mutation', 8);
      });
      try{
        panelObserver.observe(consultorioPanel, { attributes: true, attributeFilter: ['class', 'style'] });
      }catch(_){ }
    }
    if(typeof window.MutationObserver === 'function' && pane && !pane._mxMapMutationObserverBound){
      pane._mxMapMutationObserverBound = '1';
      const paneObserver = new window.MutationObserver(()=>{
        scheduleRenderProbe('consultorio_pane_mutation', 8);
      });
      try{
        paneObserver.observe(pane, { attributes: true, attributeFilter: ['class', 'style'] });
      }catch(_){ }
    }

    const latStored = toFiniteNumber(state.lat);
    const lngStored = toFiniteNumber(state.lng);
    if(Number.isFinite(latStored) && Number.isFinite(lngStored)){
      applyMapPoint(latStored, lngStored, 17, 'saved');
      syncPublicMapSnapshot({
        ...state,
        lat: latStored,
        lng: lngStored,
      });
      logMapSourceDebug('bind_apply_saved_point', {
        lat_used: latStored,
        lng_used: lngStored,
        chosen_by: 'saved'
      });
      if(normalizeGeocodeSource(state.source) === 'manual_adjusted'){
        showStatus('Ubicación ajustada manualmente.', 'success');
      }
    }else{
      syncPublicMapSnapshot({
        ...state,
        lat: null,
        lng: null,
      });
      logMapSourceDebug('bind_no_saved_point', {
        chosen_by: 'fallback'
      });
    }
    refreshActionButtons();
    consultorioMapInvalidators.set(mapKey, safeInvalidate);
    scheduleRenderProbe('bind_complete', 8);
    const hasManualConfirmedSavedPoint = (
      Number.isFinite(latStored)
      && Number.isFinite(lngStored)
      && normalizeGeocodeSource(state.source) === 'manual_adjusted'
    );
    window.setTimeout(()=>{
      scheduleRenderProbe('bind_timeout_120', 8);
      if(!hasManualConfirmedSavedPoint){
        debouncedResolve();
      }
    }, 120);
  }

  function refreshConsultorioMapByIndex(index = 1, delay = 120){
    const ids = resolveConsultorioMapIdsByIndex(index);
    const frame = document.getElementById(ids.frame);
    if(!frame) return;
    if(frame.dataset.mapGeocodeBound !== '1'){
      bindConsultorioMapByIndex(index);
    }
    const invalidate = consultorioMapInvalidators.get(String(Number(index || 1)));
    if(typeof invalidate === 'function'){
      invalidate('refresh');
    }
    const state = getConsultorioGeoState(ids.index);
    const latStored = toFiniteNumber(state.lat);
    const lngStored = toFiniteNumber(state.lng);
    const hasManualConfirmedSavedPoint = (
      Number.isFinite(latStored)
      && Number.isFinite(lngStored)
      && normalizeGeocodeSource(state.source) === 'manual_adjusted'
    );
    const runner = consultorioMapRefreshers.get(String(Number(index || 1)));
    if(typeof runner !== 'function') return;
    if(hasManualConfirmedSavedPoint) return;
    window.setTimeout(()=>{ runner(); }, Math.max(0, Number(delay || 0)));
  }

  // Si se crea Consultorio 2 din?micamente, renombrar IDs y activar all? tambi?n
  const origCreate = createSede2IfNeeded;
  createSede2IfNeeded = function(){
    const ret = origCreate();
    if(!ret) return ret;
    const pane2 = ret.pane || ret.pane2;
    if(!pane2) return ret;
    // Renombrar IDs para evitar duplicados
    const map = [ ['cp','cp2'], ['colonia','colonia2'], ['mensaje-cp','mensaje-cp2'], ['municipio','municipio2'], ['estado','estado2'] ];
    map.forEach(([from,to])=>{ const el = pane2.querySelector('#'+from); if(el){ el.id = to; const label = pane2.querySelector('label[for="'+from+'"]'); if(label) label.setAttribute('for', to); } });
    // Inicializar listeners en el nuevo set
    setupCpAuto({ cp:'cp2', colonia:'colonia2', msg:'mensaje-cp2', mun:'municipio2', est:'estado2' });
    bindConsultorioMapByIndex(2);
    return ret;
  };
  bindConsultorioMapByIndex(1);

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
          try{
            if(typeof window.mxPersistGroupLogoUrl === 'function'){
              window.mxPersistGroupLogoUrl(img.src);
            }else{
              persistGroupLogoUrl(img.src);
            }
          }catch(_){ }
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
          try{
            if(typeof window.mxPersistGroupLogoUrl === 'function'){
              window.mxPersistGroupLogoUrl('', { clear: true });
            }else{
              persistGroupLogoUrl('');
            }
          }catch(_){ }
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

    if(inputId === 'cons-logo'){
      try{
        const current = (img.getAttribute('src') || '').trim();
        if(current){
          if(typeof window.mxPersistGroupLogoUrl === 'function'){
            window.mxPersistGroupLogoUrl(current);
          }else{
            persistGroupLogoUrl(current);
          }
        }
      }catch(_){ }
    }
  }

  window.mxSetupUploadBox = setupUploadBox;
  $$('.mf-upload').forEach(setupUploadBox);
  const logoDrop = document.querySelector('#cons-logo-slot .logo-slot-drop');
  if(logoDrop){
    window._mx_logoDropTemplate = logoDrop.outerHTML;
  }
  hydrateGroupLogoPreview();
  window.__mxmedConsultorioGroupManaged = true;

  // ===== Persistencia real de datos generales de Consultorio (backend) =====
  (function setupConsultorioPersistence(){
    const root = document.getElementById('p-consultorio');
    if(!root) return;

    let hydrateInProgress = false;
    const saveTimers = new Map();
    const hydratedRows = new Set();
    const groupStateByPane = new Map();
    const groupSearchTimers = new Map();
    const groupSearchRequestSeq = new Map();
    let groupSuggestLayer = null;
    let groupSuggestItems = [];
    let groupSuggestIndex = -1;
    let groupSuggestKeyHandler = null;
    let groupSuggestCloseHandler = null;
    let groupSuggestPaneKey = '';

    const clean = (value)=> String(value ?? '').trim();
    const resolveDoctorId = ()=> clean(resolveActiveDoctorId());
    const getPaneByIndex = (idx)=> document.getElementById(`sede${idx}`);
    const parsePaneIndex = (pane)=>{
      const m = /^sede(\d+)$/.exec(String(pane?.id || ''));
      return m ? Number(m[1]) : 1;
    };
    const getWhatsappField = (pane)=>{
      return getField(pane, 'input[id^="cons-wa"]');
    };
    const paneExists = (idx)=> !!getPaneByIndex(idx);
    const ensurePane = (idx)=>{
      if(idx <= 1) return getPaneByIndex(1);
      if(!paneExists(idx) && typeof window._mx_createConsultorio === 'function'){
        window._mx_createConsultorio(idx);
      }
      return getPaneByIndex(idx);
    };
    const getField = (pane, selector)=> pane?.querySelector(selector);
    const logConsultorioGroupDebug = (label = '', pane = null, extra = {})=>{
      try{
        if(!pane) return;
        const paneId = String(pane.id || '');
        const visibleInputs = Array.from(pane.querySelectorAll('input[id^="cons-titulo"]'));
        const groupInputs = Array.from(pane.querySelectorAll('input[id^="cons-grupo-nombre"]'));
        const visibleLabels = Array.from(pane.querySelectorAll('label[for^="cons-titulo"]'));
        const groupLabels = Array.from(pane.querySelectorAll('label[for^="cons-grupo-nombre"]'));
        const groupYes = getField(pane, 'input[id^="cons-grupo-si"]');
        const groupNo = getField(pane, 'input[id^="cons-grupo-no"]');
        const groupInput = groupInputs[0] || null;
        const visibleInput = visibleInputs[0] || null;
        const groupWrap = groupInput?.closest('[data-cons-group-name-wrap]') || groupInput?.closest('[class*="col-"]');
        const visibleWrap = visibleInput?.closest('[data-cons-visible-name-wrap]') || visibleInput?.closest('[class*="col-"]');
        const selected = groupYes?.checked ? 'si' : (groupNo?.checked ? 'no' : 'none');
        const groupDisplay = groupWrap ? window.getComputedStyle(groupWrap).display : '';
        const visibleDisplay = visibleWrap ? window.getComputedStyle(visibleWrap).display : '';
        console.warn('CONSULTORIO GROUP DEBUG:', label, {
          pane_id: paneId,
          selected_value: selected,
          visible_input_id: String(visibleInput?.id || ''),
          visible_label_for: String(visibleLabels[0]?.getAttribute('for') || ''),
          visible_label_text: clean(visibleLabels[0]?.textContent || ''),
          visible_input_matches: visibleInputs.length,
          visible_label_matches: visibleLabels.length,
          group_input_id: String(groupInput?.id || ''),
          group_label_for: String(groupLabels[0]?.getAttribute('for') || ''),
          group_label_text: clean(groupLabels[0]?.textContent || ''),
          group_input_matches: groupInputs.length,
          group_label_matches: groupLabels.length,
          group_wrap_id: String(groupWrap?.id || ''),
          group_wrap_class: String(groupWrap?.className || ''),
          group_wrap_display: groupDisplay,
          visible_wrap_id: String(visibleWrap?.id || ''),
          visible_wrap_class: String(visibleWrap?.className || ''),
          visible_wrap_display: visibleDisplay,
          ...extra,
        });
      }catch(_){ }
    };
    const setValue = (el, value)=>{
      if(!el) return;
      const next = clean(value);
      if(el.value !== next){
        el.value = next;
      }
    };
    const readPreviewUrl = (pane, selector)=>{
      const img = pane?.querySelector(selector);
      const src = clean(img?.getAttribute('src') || img?.src || '');
      if(!src) return '';
      if(/^data:image\//i.test(src)) return src;
      try{
        const u = new URL(src, window.location.origin);
        return u.href;
      }catch(_){
        return src;
      }
    };
    const applyPreview = (pane, selector, srcValue)=>{
      const src = clean(srcValue);
      if(!src) return;
      const img = pane?.querySelector(selector);
      if(!img) return;
      img.src = src;
      const preview = img.closest('[hidden], .logo-slot-preview, .mf-prev');
      if(preview){
        preview.removeAttribute('hidden');
        preview.style.display = 'flex';
      }
      const slot = img.closest('.logo-slot, .foto-slot');
      if(slot){
        slot.classList.add('show-preview', 'has-logo');
      }
      const drop = slot?.querySelector('.logo-slot-drop');
      if(drop){
        drop.setAttribute('hidden', 'hidden');
      }
    };
    const getPaneKey = (paneOrIndex)=>{
      if(typeof paneOrIndex === 'number'){
        return String(paneOrIndex || 1);
      }
      if(typeof paneOrIndex === 'string'){
        const raw = clean(paneOrIndex);
        if(/^\d+$/.test(raw)){
          return String(Number(raw) || 1);
        }
        const match = /^sede(\d+)$/.exec(raw);
        if(match){
          return String(Number(match[1]) || 1);
        }
      }
      const idx = parsePaneIndex(paneOrIndex);
      return String(idx || 1);
    };
    const getPaneGroupState = (paneOrIndex)=>{
      return groupStateByPane.get(getPaneKey(paneOrIndex)) || {};
    };
    const setPaneGroupState = (paneOrIndex, patch = {})=>{
      const key = getPaneKey(paneOrIndex);
      const current = groupStateByPane.get(key) || {};
      const next = { ...current, ...patch };
      groupStateByPane.set(key, next);
      return next;
    };
    const clearPaneGroupState = (paneOrIndex)=>{
      groupStateByPane.delete(getPaneKey(paneOrIndex));
    };
    const normalizeGroupStatusLabel = (rawStatus)=>{
      const status = clean(rawStatus).toLowerCase();
      if(status === 'verified') return 'verificado';
      if(status === 'pending') return 'pendiente de revisión';
      if(status === 'rejected') return 'rechazado';
      if(status === 'merged') return 'fusionado';
      return '';
    };
    const closeGroupSuggest = ()=>{
      if(groupSuggestLayer){
        groupSuggestLayer.remove();
        groupSuggestLayer = null;
      }
      groupSuggestItems = [];
      groupSuggestIndex = -1;
      groupSuggestPaneKey = '';
      if(groupSuggestKeyHandler){
        document.removeEventListener('keydown', groupSuggestKeyHandler, true);
        groupSuggestKeyHandler = null;
      }
      if(groupSuggestCloseHandler){
        document.removeEventListener('mousedown', groupSuggestCloseHandler, true);
        groupSuggestCloseHandler = null;
      }
    };
    const highlightGroupSuggest = (idx)=>{
      groupSuggestIndex = idx;
      groupSuggestItems.forEach((entry, i)=>{
        const node = entry?.node;
        if(!node) return;
        node.classList.toggle('active', i === groupSuggestIndex);
        if(i === groupSuggestIndex){
          try{ node.scrollIntoView({ block:'nearest' }); }catch(_){ }
        }
      });
    };
    const renderGroupSuggest = ({ pane, anchor, items })=>{
      closeGroupSuggest();
      if(!pane || !anchor || !Array.isArray(items) || !items.length) return;
      const rect = anchor.getBoundingClientRect();
      const box = document.createElement('div');
      box.className = 'grp-suggest';
      box.style.left = `${window.scrollX + rect.left}px`;
      box.style.top = `${window.scrollY + rect.bottom + 4}px`;
      box.style.width = `${rect.width}px`;

      groupSuggestPaneKey = getPaneKey(pane);
      groupSuggestItems = [];

      items.forEach((item)=>{
        const row = document.createElement('div');
        row.className = 'item';
        row.style.display = 'flex';
        row.style.alignItems = 'center';
        row.style.gap = '8px';

        if(clean(item.logo_url)){
          const img = document.createElement('img');
          img.src = clean(item.logo_url);
          img.alt = '';
          img.width = 22;
          img.height = 22;
          img.style.objectFit = 'cover';
          img.style.borderRadius = '4px';
          row.appendChild(img);
        }

        const body = document.createElement('div');
        body.style.minWidth = '0';
        const name = document.createElement('div');
        name.className = 'name';
        name.textContent = item.nombre;
        const meta = document.createElement('div');
        meta.className = 'addr';
        const statusLabel = normalizeGroupStatusLabel(item.status);
        meta.textContent = statusLabel || 'verificado';
        body.appendChild(name);
        body.appendChild(meta);
        row.appendChild(body);

        row.addEventListener('click', ()=>{
          closeGroupSuggest();
          selectExistingGroup(pane, item).catch(()=> null);
        });

        box.appendChild(row);
        groupSuggestItems.push({ data: item, node: row });
      });

      document.body.appendChild(box);
      groupSuggestLayer = box;
      highlightGroupSuggest(0);

      groupSuggestCloseHandler = (event)=>{
        const target = event.target;
        if(!target) return;
        if(box.contains(target) || target === anchor) return;
        closeGroupSuggest();
      };
      document.addEventListener('mousedown', groupSuggestCloseHandler, true);

      groupSuggestKeyHandler = (event)=>{
        if(!groupSuggestLayer || !groupSuggestItems.length) return;
        if(getPaneKey(pane) !== groupSuggestPaneKey) return;
        if(event.key === 'ArrowDown'){
          event.preventDefault();
          highlightGroupSuggest((groupSuggestIndex + 1) % groupSuggestItems.length);
        }else if(event.key === 'ArrowUp'){
          event.preventDefault();
          highlightGroupSuggest((groupSuggestIndex - 1 + groupSuggestItems.length) % groupSuggestItems.length);
        }else if(event.key === 'Enter'){
          event.preventDefault();
          const item = groupSuggestItems[groupSuggestIndex];
          if(item?.data){
            closeGroupSuggest();
            selectExistingGroup(pane, item.data).catch(()=> null);
          }
        }else if(event.key === 'Escape'){
          event.preventDefault();
          closeGroupSuggest();
        }
      };
      document.addEventListener('keydown', groupSuggestKeyHandler, true);
    };
    const syncConsultorioNameModel = (pane, options = {})=>{
      if(!pane) return;
      const shouldAutofill = options.autofill === true;
      const paneGroupState = getPaneGroupState(pane);
      const groupYes = getField(pane, 'input[id^="cons-grupo-si"]');
      const groupNameInput = getField(pane, 'input[id^="cons-grupo-nombre"]');
      const groupNameWrap = groupNameInput?.closest('[data-cons-group-name-wrap]') || groupNameInput?.closest('[class*="col-"]');
      const visibleNameInput = getField(pane, 'input[id^="cons-titulo"]');
      const baseNameHint = getField(pane, '[id^="cons-base-name-hint"]');
      const hasGroup = !!(groupYes && groupYes.checked);
      const baseName = clean(groupNameInput?.value || '');

      if(groupNameInput){
        if(hasGroup){
          if(groupNameWrap) groupNameWrap.classList.remove('d-none');
          groupNameInput.removeAttribute('disabled');
          groupNameInput.removeAttribute('readonly');
          groupNameInput.removeAttribute('aria-readonly');
        }else{
          if(groupNameWrap) groupNameWrap.classList.add('d-none');
          groupNameInput.setAttribute('disabled', 'disabled');
          groupNameInput.removeAttribute('readonly');
          groupNameInput.removeAttribute('aria-readonly');
        }
      }
      logConsultorioGroupDebug('syncConsultorioNameModel', pane, {
        has_group: hasGroup,
        base_name: baseName,
      });

      if(baseNameHint){
        if(hasGroup && baseName !== ''){
          const statusLabel = normalizeGroupStatusLabel(paneGroupState.status || '');
          baseNameHint.textContent = statusLabel
            ? `Pertenece a: ${baseName} · ${statusLabel}`
            : `Pertenece a: ${baseName}`;
          baseNameHint.classList.remove('d-none');
        }else{
          baseNameHint.textContent = '';
          baseNameHint.classList.add('d-none');
        }
      }

      if(shouldAutofill && hasGroup && baseName !== '' && visibleNameInput){
        const currentVisibleName = clean(visibleNameInput.value || '');
        if(currentVisibleName === ''){
          visibleNameInput.value = baseName;
          try{
            visibleNameInput.dispatchEvent(new Event('input'));
            visibleNameInput.dispatchEvent(new Event('change'));
          }catch(_){ }
        }
      }
    };
    const collectPanePayload = (idx)=>{
      const pane = ensurePane(idx);
      if(!pane) return null;
      const visibleName = clean(getField(pane, '[id^="cons-titulo"]')?.value || '');
      const groupYes = getField(pane, 'input[id^="cons-grupo-si"]');
      const hasGroup = !!groupYes?.checked;
      const baseName = hasGroup ? clean(getField(pane, '[id^="cons-grupo-nombre"]')?.value || '') : '';
      const paneGroupState = getPaneGroupState(idx);
      const tel1 = clean(getField(pane, '[id^="cons-tel1"]')?.value || '');
      const tel2 = clean(getField(pane, '[id^="cons-tel2"]')?.value || '');
      const tel3 = clean(getField(pane, '[id^="cons-tel3"]')?.value || '');
      const urg1 = clean(getField(pane, '[id^="cons-urg1"]')?.value || '');
      const urg2 = clean(getField(pane, '[id^="cons-urg2"]')?.value || '');
      const payload = {
        doctor_id: resolveDoctorId(),
        consultorio_id: String(idx),
        group_id: hasGroup ? clean(paneGroupState.group_id || '') : '',
        titulo: visibleName,
        nombre_visible: visibleName,
        grupo_nombre: baseName,
        nombre_base: baseName,
        calle: clean(getField(pane, '[id^="cons-calle"]')?.value || ''),
        num_ext: clean(getField(pane, '[id^="cons-numext"]')?.value || ''),
        num_int: clean(getField(pane, '[id^="cons-numint"]')?.value || ''),
        cp: clean(getField(pane, '[id^="cp"]')?.value || ''),
        colonia: clean(getField(pane, '[id^="colonia"]')?.value || ''),
        municipio: clean(getField(pane, '[id^="municipio"]')?.value || ''),
        estado: clean(getField(pane, '[id^="estado"]')?.value || ''),
        telefonos: [tel1, tel2, tel3].filter(Boolean),
        whatsapp: clean(getWhatsappField(pane)?.value || ''),
        urgencias: [urg1, urg2].filter(Boolean),
        logo_url: readPreviewUrl(pane, '.logo-slot-preview img, .mf-upload[data-type="logo"] .mf-prev img'),
        foto_url: readPreviewUrl(pane, '.foto-slot .mf-prev img')
      };
      const geo = getConsultorioGeoState(idx);
      const lat = toFiniteNumber(geo.lat);
      const lng = toFiniteNumber(geo.lng);
      const source = normalizeGeocodeSource(geo.source);
      // Persistir coordenadas solo cuando el usuario confirmó explícitamente la ubicación.
      // Evita guardar como ubicación real un punto automático genérico (centro de ciudad).
      if(Number.isFinite(lat) && Number.isFinite(lng) && source === 'manual_adjusted'){
        payload.lat = Number(lat.toFixed(7));
        payload.lng = Number(lng.toFixed(7));
        payload.geocode_source = source;
      }
      return payload;
    };
    const hasMeaningfulData = (payload)=>{
      if(!payload) return false;
      return [
        payload.titulo,
        payload.grupo_nombre,
        payload.calle,
        payload.num_ext,
        payload.num_int,
        payload.cp,
        payload.colonia,
        payload.municipio,
        payload.estado,
        payload.whatsapp,
        payload.logo_url,
        payload.foto_url,
        ...(Array.isArray(payload.telefonos) ? payload.telefonos : []),
        ...(Array.isArray(payload.urgencias) ? payload.urgencias : [])
      ].some((value)=> clean(value) !== '');
    };

    const apiGetConsultorios = async (doctorId)=>{
      const resp = await fetch(`/api/agenda/index.php/consultorios?doctor_id=${encodeURIComponent(doctorId)}`, {
        method: 'GET',
        headers: { 'Accept': 'application/json' },
        credentials: 'same-origin'
      });
      return resp.json().catch(()=> null);
    };
    const apiSaveConsultorio = async (payload)=>{
      const resp = await fetch('/api/agenda/index.php/consultorios', {
        method: 'PUT',
        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(payload || {})
      });
      return {
        ok: resp.ok,
        status: resp.status,
        json: await resp.json().catch(()=> null)
      };
    };
    const apiSearchMedicalGroups = async ({ doctorId, q, cp, colonia, limit = 8 } = {})=>{
      const params = new URLSearchParams();
      if(clean(doctorId)) params.set('doctor_id', clean(doctorId));
      if(clean(q)) params.set('q', clean(q));
      if(clean(cp)) params.set('cp', clean(cp));
      if(clean(colonia)) params.set('colonia', clean(colonia));
      params.set('limit', String(limit));
      const resp = await fetch(`/api/agenda/index.php/medical-groups/search?${params.toString()}`, {
        method: 'GET',
        headers: { 'Accept': 'application/json' },
        credentials: 'same-origin'
      });
      return {
        ok: resp.ok,
        status: resp.status,
        json: await resp.json().catch(()=> null)
      };
    };
    const apiJoinMedicalGroup = async (groupId, payload = {})=>{
      const safeGroupId = encodeURIComponent(clean(groupId));
      const resp = await fetch(`/api/agenda/index.php/medical-groups/${safeGroupId}/join`, {
        method: 'POST',
        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(payload || {})
      });
      return {
        ok: resp.ok,
        status: resp.status,
        json: await resp.json().catch(()=> null)
      };
    };
    const apiCreateMedicalGroup = async (payload = {})=>{
      const resp = await fetch('/api/agenda/index.php/medical-groups', {
        method: 'POST',
        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(payload || {})
      });
      return {
        ok: resp.ok,
        status: resp.status,
        json: await resp.json().catch(()=> null)
      };
    };
    const requestMedicalGroupsSuggestions = async (pane, query = '')=>{
      if(!pane) return [];
      const idx = parsePaneIndex(pane);
      const paneKey = getPaneKey(idx);
      const doctorId = resolveDoctorId();
      if(!doctorId) return [];

      const cp = clean(getField(pane, '[id^="cp"]')?.value || '');
      const colonia = clean(getField(pane, '[id^="colonia"]')?.value || '');
      const seq = (groupSearchRequestSeq.get(paneKey) || 0) + 1;
      groupSearchRequestSeq.set(paneKey, seq);

      const result = await apiSearchMedicalGroups({
        doctorId,
        q: clean(query),
        cp,
        colonia,
        limit: 8
      });
      if(groupSearchRequestSeq.get(paneKey) !== seq) return [];
      const rows = Array.isArray(result?.json?.data) ? result.json.data : [];
      const mapped = rows.map((row)=>({
        group_id: clean(row?.group_id || ''),
        display_name: clean(row?.display_name || ''),
        canonical_name: clean(row?.canonical_name || ''),
        logo_url: clean(row?.logo_url_approved || ''),
        status: clean(row?.status || 'verified').toLowerCase()
      })).filter((item)=> item.group_id && item.display_name);
      mapped.sort((a, b)=>{
        const pa = a.status === 'verified' ? 0 : 1;
        const pb = b.status === 'verified' ? 0 : 1;
        if(pa !== pb) return pa - pb;
        return a.display_name.localeCompare(b.display_name, 'es', { sensitivity:'base' });
      });
      return mapped;
    };
    const queueGroupSearch = (pane, query = '', delay = 280)=>{
      if(!pane) return;
      const idx = parsePaneIndex(pane);
      const key = getPaneKey(idx);
      const timer = groupSearchTimers.get(key);
      if(timer){
        window.clearTimeout(timer);
      }
      const next = window.setTimeout(async ()=>{
        const groupYes = getField(pane, 'input[id^="cons-grupo-si"]');
        const input = getField(pane, 'input[id^="cons-grupo-nombre"]');
        if(!groupYes?.checked || !input){
          if(groupSuggestPaneKey === key) closeGroupSuggest();
          return;
        }
        try{
          const suggestions = await requestMedicalGroupsSuggestions(pane, query);
          if(!suggestions.length){
            if(groupSuggestPaneKey === key) closeGroupSuggest();
            return;
          }
          renderGroupSuggest({
            pane,
            anchor: input,
            items: suggestions.map((item)=>({
              id: item.group_id,
              nombre: item.display_name,
              logo_url: item.logo_url,
              status: item.status
            }))
          });
        }catch(_){
          if(groupSuggestPaneKey === key) closeGroupSuggest();
        }
      }, delay);
      groupSearchTimers.set(key, next);
    };
    const selectExistingGroup = async (pane, option)=>{
      if(!pane) return;
      const idx = parsePaneIndex(pane);
      const doctorId = resolveDoctorId();
      const consultorioId = String(idx);
      const groupId = clean(option?.id || option?.group_id || '');
      if(!doctorId || !groupId) return;

      const visibleInput = getField(pane, 'input[id^="cons-titulo"]');
      const groupInput = getField(pane, 'input[id^="cons-grupo-nombre"]');
      const groupYes = getField(pane, 'input[id^="cons-grupo-si"]');
      const currentVisibleName = clean(visibleInput?.value || '');
      const displayName = clean(option?.nombre || option?.display_name || '');

      if(groupYes){
        groupYes.checked = true;
        try{ groupYes.dispatchEvent(new Event('change')); }catch(_){ }
      }
      if(groupInput && displayName){
        groupInput.removeAttribute('disabled');
        groupInput.value = displayName;
        try{
          groupInput.dispatchEvent(new Event('input'));
          groupInput.dispatchEvent(new Event('change'));
        }catch(_){ }
      }

      const payload = {
        doctor_id: doctorId,
        consultorio_id: consultorioId
      };
      if(currentVisibleName){
        payload.display_name_override = currentVisibleName;
      }
      const result = await apiJoinMedicalGroup(groupId, payload);
      if(!result?.ok || result?.json?.ok !== true){
        return;
      }
      const group = result?.json?.data?.group;
      const consultorio = result?.json?.data?.consultorio;
      const status = clean(group?.status || option?.status || 'verified') || 'verified';
      const logoUrl = clean(group?.logo_url_approved || option?.logo_url || '');
      const finalName = clean(group?.display_name || displayName);
      setPaneGroupState(idx, {
        group_id: clean(group?.group_id || groupId),
        status,
        selected: true,
        submitted_group_name: '',
        display_name: finalName
      });

      if(visibleInput && !currentVisibleName && finalName){
        visibleInput.value = finalName;
        try{
          visibleInput.dispatchEvent(new Event('input'));
          visibleInput.dispatchEvent(new Event('change'));
        }catch(_){ }
      }
      if(groupInput && finalName){
        groupInput.value = finalName;
      }
      if(logoUrl){
        applyPreview(pane, '.logo-slot-preview img, .mf-upload[data-type="logo"] .mf-prev img', logoUrl);
      }
      if(consultorio && typeof consultorio === 'object'){
        applyRowToPane(consultorio);
      }else{
        syncConsultorioNameModel(pane, { autofill: true });
      }
    };
    const ensurePendingGroupForPane = async (idx, payload)=>{
      const pane = ensurePane(idx);
      if(!pane || !payload) return;
      const groupYes = getField(pane, 'input[id^="cons-grupo-si"]');
      const groupNameInput = getField(pane, '[id^="cons-grupo-nombre"]');
      const baseName = clean(groupNameInput?.value || '');
      if(!groupYes?.checked || !baseName){
        clearPaneGroupState(idx);
        return;
      }
      if(groupNameInput && document.activeElement === groupNameInput){
        return;
      }

      const current = getPaneGroupState(idx);
      if(current?.group_id && current?.selected){
        return;
      }
      if(current?.group_id && clean(current.submitted_group_name || '') === baseName){
        payload.group_id = current.group_id;
        return;
      }

      const body = {
        doctor_id: payload.doctor_id,
        consultorio_id: payload.consultorio_id,
        submitted_group_name: baseName
      };
      if(clean(payload.logo_url || '')){
        body.submitted_logo_url = clean(payload.logo_url || '');
      }
      if(clean(payload.nombre_visible || '')){
        body.display_name_override = clean(payload.nombre_visible || '');
      }

      const result = await apiCreateMedicalGroup(body);
      if(!result?.ok || result?.json?.ok !== true){
        return;
      }
      const group = result?.json?.data?.group || {};
      const consultorio = result?.json?.data?.consultorio || null;
      const groupId = clean(group.group_id || '');
      const status = clean(group.status || 'pending') || 'pending';
      setPaneGroupState(idx, {
        group_id: groupId,
        status,
        selected: false,
        submitted_group_name: baseName,
        display_name: clean(group.display_name || baseName)
      });
      if(groupId){
        payload.group_id = groupId;
      }
      if(consultorio && typeof consultorio === 'object'){
        applyRowToPane(consultorio);
      }else{
        syncConsultorioNameModel(pane, { autofill: true });
      }
    };

    const applyRowToPane = (row)=>{
      const idx = Number(clean(row?.consultorio_id || row?.id || '1')) || 1;
      const pane = ensurePane(idx);
      if(!pane) return;
      const baseName = row?.nombre_base || row?.grupo_nombre || '';
      const groupId = clean(row?.group_id || '');
      const visibleName = row?.nombre_visible || row?.titulo || row?.name || '';
      setValue(getField(pane, '[id^="cons-titulo"]'), visibleName);
      setValue(getField(pane, '[id^="cons-grupo-nombre"]'), baseName);
      if(groupId || clean(baseName)){
        setPaneGroupState(idx, {
          group_id: groupId,
          selected: !!groupId,
          display_name: clean(baseName || ''),
          status: clean(row?.group_status || getPaneGroupState(idx).status || '')
        });
      }else{
        clearPaneGroupState(idx);
      }
      const groupYes = getField(pane, 'input[id^="cons-grupo-si"]');
      const groupNo = getField(pane, 'input[id^="cons-grupo-no"]');
      if(groupYes && groupNo){
        const hasGroupData = (groupId !== '') || (clean(baseName) !== '');
        groupYes.checked = hasGroupData;
        groupNo.checked = !hasGroupData;
        logConsultorioGroupDebug('applyRowToPane.radioState', pane, {
          row_group_id: groupId,
          row_base_name: clean(baseName),
          resolved_has_group_data: hasGroupData,
        });
      }
      setValue(getField(pane, '[id^="cons-calle"]'), row?.calle || '');
      setValue(getField(pane, '[id^="cons-numext"]'), row?.num_ext || '');
      setValue(getField(pane, '[id^="cons-numint"]'), row?.num_int || '');
      setValue(getField(pane, '[id^="cp"]'), row?.cp || '');
      setValue(getField(pane, '[id^="municipio"]'), row?.municipio || '');
      setValue(getField(pane, '[id^="estado"]'), row?.estado || '');
      const coloniaEl = getField(pane, '[id^="colonia"]');
      if(coloniaEl && clean(row?.colonia || '')){
        if(coloniaEl.tagName === 'SELECT'){
          const val = clean(row.colonia);
          const exists = Array.from(coloniaEl.options || []).some((opt)=> clean(opt.value) === val);
          if(!exists){
            const option = document.createElement('option');
            option.value = val;
            option.textContent = val;
            coloniaEl.appendChild(option);
          }
          coloniaEl.value = val;
        } else {
          coloniaEl.value = clean(row.colonia);
        }
      }
      const tels = Array.isArray(row?.telefonos) ? row.telefonos : [];
      setValue(getField(pane, '[id^="cons-tel1"]'), tels[0] || '');
      setValue(getField(pane, '[id^="cons-tel2"]'), tels[1] || '');
      setValue(getField(pane, '[id^="cons-tel3"]'), tels[2] || '');
      setValue(getWhatsappField(pane), row?.whatsapp || '');
      const urgs = Array.isArray(row?.urgencias) ? row.urgencias : [];
      setValue(getField(pane, '[id^="cons-urg1"]'), urgs[0] || '');
      setValue(getField(pane, '[id^="cons-urg2"]'), urgs[1] || '');
      applyPreview(pane, '.logo-slot-preview img, .mf-upload[data-type="logo"] .mf-prev img', row?.logo_url || '');
      applyPreview(pane, '.foto-slot .mf-prev img', row?.foto_url || '');
      const geoLat = toFiniteNumber(row?.lat);
      const geoLng = toFiniteNumber(row?.lng);
      const geoSource = normalizeGeocodeSource(row?.geocode_source || '');
      const ids = resolveConsultorioMapIdsByIndex(idx);
      const resolvedAddress = buildConsultorioAddressByIds(ids);
      if(Number.isFinite(geoLat) && Number.isFinite(geoLng)){
        setConsultorioGeoState(idx, {
          lat: geoLat,
          lng: geoLng,
          source: geoSource || 'auto_geocoded',
          geocode_updated_at: clean(row?.geocode_updated_at || ''),
          persisted_lat: geoLat,
          persisted_lng: geoLng,
          persisted_source: geoSource || 'auto_geocoded',
          manual_confirmed: geoSource === 'manual_adjusted',
          confirmed_address: geoSource === 'manual_adjusted' ? resolvedAddress : '',
          manual_dirty: false,
          pending_lat: null,
          pending_lng: null,
        });
      }else{
        setConsultorioGeoState(idx, {
          lat: null,
          lng: null,
          source: '',
          geocode_updated_at: '',
          persisted_lat: null,
          persisted_lng: null,
          persisted_source: '',
          manual_confirmed: false,
          confirmed_address: '',
          manual_dirty: false,
          pending_lat: null,
          pending_lng: null,
        });
      }
      syncConsultorioNameModel(pane, { autofill: false });
      refreshConsultorioMapByIndex(idx, 90);
    };

    const savePaneNow = async (idx)=>{
      const payload = collectPanePayload(idx);
      if(!payload || !payload.doctor_id) return;
      const pane = ensurePane(idx);
      const hasGroup = !!getField(pane, 'input[id^="cons-grupo-si"]')?.checked;
      if(!hasGroup){
        clearPaneGroupState(idx);
        payload.group_id = '';
        payload.grupo_nombre = '';
        payload.nombre_base = '';
      }else{
        await ensurePendingGroupForPane(idx, payload);
      }
      if(!hasMeaningfulData(payload) && hydratedRows.has(String(idx))) return;
      const result = await apiSaveConsultorio(payload);
      if(result?.ok && result?.json?.ok){
        hydratedRows.add(String(idx));
        const row = result?.json?.data;
        if(row && typeof row === 'object'){
          applyRowToPane(row);
        }
      }
    };
    const queueSavePane = (idx, delay = 420)=>{
      if(hydrateInProgress) return;
      const key = String(idx || 1);
      if(saveTimers.has(key)){
        window.clearTimeout(saveTimers.get(key));
      }
      const timer = window.setTimeout(()=>{
        savePaneNow(Number(key)).catch(()=> null);
      }, delay);
      saveTimers.set(key, timer);
    };

    const hydrateFromBackend = async ()=>{
      const doctorId = resolveDoctorId();
      if(!doctorId) return;
      hydrateInProgress = true;
      try{
        const json = await apiGetConsultorios(doctorId);
        const rows = Array.isArray(json?.data) ? json.data : [];
        rows.forEach((row)=>{
          const cid = clean(row?.consultorio_id || row?.id || '');
          if(cid) hydratedRows.add(cid);
          applyRowToPane(row);
        });
        const paneIds = getConsultorioSlots();
        paneIds.forEach((idx)=>{
          const key = String(idx);
          if(hydratedRows.has(key)) return;
          const draft = collectPanePayload(idx);
          if(hasMeaningfulData(draft)){
            queueSavePane(idx, 80);
          }
        });
      }catch(_){
        // Mantener UX operativa aunque backend no responda.
      }finally{
        hydrateInProgress = false;
      }
    };

    root.addEventListener('input', (event)=>{
      const target = event.target;
      if(!(target instanceof HTMLElement)) return;
      const pane = target.closest('.tab-pane[id^="sede"]');
      if(!pane) return;
      if(!target.matches('input, select, textarea')) return;
      const targetId = clean(target.id || '');
      if(targetId.startsWith('cons-grupo-si') || targetId.startsWith('cons-grupo-no')){
        logConsultorioGroupDebug('input.group.radio.skipAutosave', pane, {
          target_id: targetId,
          target_checked: (target instanceof HTMLInputElement) ? !!target.checked : false,
        });
        return;
      }
      if(targetId.startsWith('cons-grupo-nombre')){
        const idx = parsePaneIndex(pane);
        const typed = clean(target.value || '');
        const current = getPaneGroupState(idx);
        if(current.group_id && clean(current.display_name || current.submitted_group_name || '') !== typed){
          setPaneGroupState(idx, {
            group_id: '',
            selected: false,
            status: '',
            submitted_group_name: typed,
            display_name: typed
          });
        }
        syncConsultorioNameModel(pane, { autofill: true });
        queueGroupSearch(pane, typed, 240);
      }
      const idx = parsePaneIndex(pane);
      queueSavePane(idx);
    }, true);

    root.addEventListener('change', (event)=>{
      const target = event.target;
      if(!(target instanceof HTMLElement)) return;
      const pane = target.closest('.tab-pane[id^="sede"]');
      if(!pane) return;
      const targetId = clean(target.id || '');
      if(
        targetId.startsWith('cons-grupo-si')
        || targetId.startsWith('cons-grupo-no')
        || targetId.startsWith('cons-grupo-nombre')
      ){
        const idx = parsePaneIndex(pane);
        logConsultorioGroupDebug('change.group.before', pane, {
          target_id: targetId,
          target_value: clean((target instanceof HTMLInputElement) ? target.value : ''),
          target_checked: (target instanceof HTMLInputElement) ? !!target.checked : false,
        });
        if(targetId.startsWith('cons-grupo-no') && target instanceof HTMLInputElement && target.checked){
          clearPaneGroupState(idx);
          closeGroupSuggest();
        }
        if(targetId.startsWith('cons-grupo-si') && target instanceof HTMLInputElement && target.checked){
          const groupInput = getField(pane, 'input[id^="cons-grupo-nombre"]');
          queueGroupSearch(pane, clean(groupInput?.value || ''), 120);
        }
        syncConsultorioNameModel(pane, {
          autofill: targetId.startsWith('cons-grupo-si') || targetId.startsWith('cons-grupo-nombre')
        });
        logConsultorioGroupDebug('change.group.after', pane, {
          target_id: targetId,
          target_checked: (target instanceof HTMLInputElement) ? !!target.checked : false,
        });
        if(targetId.startsWith('cons-grupo-si') && target instanceof HTMLInputElement && target.checked){
          const groupInputValue = clean(getField(pane, 'input[id^="cons-grupo-nombre"]')?.value || '');
          const state = getPaneGroupState(idx);
          const hasPersistableGroup = (groupInputValue !== '') || (clean(state.group_id || '') !== '');
          if(!hasPersistableGroup){
            logConsultorioGroupDebug('change.group.skipAutosaveForYes', pane, {
              reason: 'group_pending_input',
            });
            return;
          }
        }
      }
      const idx = parsePaneIndex(pane);
      queueSavePane(idx, 120);
    }, true);
    root.addEventListener('focusout', (event)=>{
      const target = event.target;
      if(!(target instanceof HTMLElement)) return;
      const targetId = clean(target.id || '');
      if(!targetId.startsWith('cons-grupo-nombre')) return;
      window.setTimeout(()=>{ closeGroupSuggest(); }, 120);
    }, true);

    root.addEventListener('click', (event)=>{
      const target = event.target;
      if(!(target instanceof HTMLElement)) return;
      if(!target.closest('.foto-x')) return;
      const pane = target.closest('.tab-pane[id^="sede"]');
      if(!pane) return;
      const idx = parsePaneIndex(pane);
      queueSavePane(idx, 220);
    }, true);

    root.querySelectorAll('.tab-pane[id^="sede"]').forEach((pane)=>{
      syncConsultorioNameModel(pane, { autofill: false });
    });

    const boot = ()=>{ hydrateFromBackend().catch(()=> null); };
    if(document.readyState === 'loading'){
      document.addEventListener('DOMContentLoaded', ()=>{ window.setTimeout(boot, 60); }, { once:true });
    }else{
      window.setTimeout(boot, 60);
    }
  })();
})();
