
// ====== Consultorio: horarios, foto preview, mapa (fallback) ======
(function(){
  const body = document.getElementById('sched-body');
  if(body){
    const dias = [
      {k:'mon', lbl:'Lunes'}, {k:'tue', lbl:'Martes'}, {k:'wed', lbl:'Mi?rcoles'},
      {k:'thu', lbl:'Jueves'}, {k:'fri', lbl:'Viernes'}, {k:'sat', lbl:'S?bado'}, {k:'sun', lbl:'Domingo'}
    ];
    const key = 'mxmed_cons_schedules';
    const scrollKey = 'mxmed_scroll_sched';
    function load(){ try { return JSON.parse(localStorage.getItem(key)||'{}'); } catch(e){ return {}; } }
    function save(v){ localStorage.setItem(key, JSON.stringify(v)); }
    const state = load();
    const markScroll = ()=>{ try{ localStorage.setItem(scrollKey,'1'); }catch(_){ } };
    const scrollAfterReload = ()=>{
      try{
        if(localStorage.getItem(scrollKey)){
          localStorage.removeItem(scrollKey);
          setTimeout(()=> document.querySelector('.sched-card')?.scrollIntoView({behavior:'smooth', block:'start'}), 200);
        }
      }catch(_){ }
    };
    scrollAfterReload();
    try{ window.mxMarkHorarioScroll = markScroll; }catch(_){ }
    const defaultTimes = { a1:'09:00', b1:'14:00', a2:'16:00', b2:'20:00' };
    function rowDefined(act, inputs){
      if(act?.checked) return true;
      return inputs.some(inp=> (inp.value||'').trim().length>0);
    }
    function hookDefault(input, key){
      if(!input) return;
      const def = defaultTimes[key];
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

    dias.forEach(d=>{
      const tr = document.createElement('tr');
      tr.innerHTML = `<td>${d.lbl}</td>
      <td><input type="checkbox" class="form-check-input" id="sch-act-${d.k}"></td>
      <td>
        <div class="d-flex align-items-center gap-1">
          <input type="time" class="form-control form-control-sm" id="sch-a1-${d.k}">
          <span>-</span>
          <input type="time" class="form-control form-control-sm" id="sch-b1-${d.k}">
        </div>
      </td>
      <td>
        <div class="d-flex align-items-center gap-1">
          <input type="time" class="form-control form-control-sm" id="sch-a2-${d.k}">
          <span>-</span>
          <input type="time" class="form-control form-control-sm" id="sch-b2-${d.k}">
        </div>
      </td>`;
      body.appendChild(tr);
      const act = tr.querySelector(`#sch-act-${d.k}`);
      const a1 = tr.querySelector(`#sch-a1-${d.k}`);
      const b1 = tr.querySelector(`#sch-b1-${d.k}`);
      const a2 = tr.querySelector(`#sch-a2-${d.k}`);
      const b2 = tr.querySelector(`#sch-b2-${d.k}`);
      const inputs = [a1,b1,a2,b2];
      const sv = state[d.k] || {};
      hookDefault(a1,'a1'); hookDefault(b1,'b1'); hookDefault(a2,'a2'); hookDefault(b2,'b2');
      act.checked = !!sv.act;
      a1.value = sv.a1 || '';
      b1.value = sv.b1 || '';
      a2.value = sv.a2 || '';
      b2.value = sv.b2 || '';
      const mark = ()=> tr.classList.toggle('sched-defined', rowDefined(act, inputs));
      const fillDefaults = ()=>{
        inputs.forEach((inp, idx)=>{
          if(!inp) return;
          const slot = ['a1','b1','a2','b2'][idx];
          const def = defaultTimes[slot];
          if(def && !(inp.value||'').trim()){
            inp.value = def;
            try{
              inp.dispatchEvent(new Event('input'));
              inp.dispatchEvent(new Event('change'));
            }catch(_){ }
          }
        });
      };
      const clearInputs = ()=>{
        inputs.forEach(inp=>{
          if(!inp) return;
          if((inp.value||'').trim()){
            inp.value = '';
            try{
              inp.dispatchEvent(new Event('input'));
              inp.dispatchEvent(new Event('change'));
            }catch(_){ }
          }
        });
      };
      function sync(){
        state[d.k] = { act:act.checked, a1:a1.value, b1:b1.value, a2:a2.value, b2:b2.value };
        save(state);
        mark();
      }
      act.addEventListener('change', ()=>{
        if(act.checked){
          fillDefaults();
        }else{
          clearInputs();
        }
        sync();
      });
      inputs.forEach(inp=>{
        inp.addEventListener('change', sync);
        inp.addEventListener('input', ()=>{
          if((inp.value||'').trim().length && !act.checked){
            act.checked = true;
          }
          sync();
        });
      });
      if(rowDefined(act, inputs) && !act.checked){
        act.checked = true;
        sync();
      }else{
        mark();
      }
    });
    document.getElementById('sched-copy-mon')?.addEventListener('click', ()=>{
      const m = state.mon || {};
      ['tue','wed','thu','fri'].forEach(k=>{ state[k] = {...m}; });
      save(state); markScroll(); location.reload();
    });
    document.getElementById('sched-clear')?.addEventListener('click', ()=>{ localStorage.removeItem(key); markScroll(); location.reload(); });
  }

  const file = document.getElementById('cons-foto');
  if(file){
    file.addEventListener('change', (e)=>{
      const f = e.target.files && e.target.files[0]; if(!f) return;
      const rd = new FileReader(); rd.onload = ev => {
        const img = document.getElementById('cons-foto-img'); const box = document.getElementById('cons-foto-prev');
        if(img && box){
          img.src = ev.target.result;
          box.style.display='block';
          box.removeAttribute('hidden');
          toggleFotoPrincipalMsg(true);
        }
      }; rd.readAsDataURL(f);
    });
  }

  // Utilidad: construir texto de direcci?n
  function buildAddress(){
    const cp = (document.getElementById('cp')?.value||'').trim();
    const col = (document.getElementById('colonia')?.value||'').trim();
    const mun = (document.getElementById('municipio')?.value||'').trim();
    const edo = (document.getElementById('estado')?.value||'').trim();
    const calle = (document.getElementById('cons-calle')?.value||'').trim();
    const num = (document.getElementById('cons-numext')?.value||'').trim();
    return [calle && (calle + (num? ' ' + num : '')), col, cp, mun, edo, 'M\u00E9xico'].filter(Boolean).join(', ');
  }

(function initMap(){
    if(!(window.L && typeof L.map === 'function')) return; // si no hay Leaflet, usamos iframe fallback m?s abajo
    // Configs para ambos panes
    const panes = [
      { mapId:'cons-map', latId:'cons-lat', lngId:'cons-lng', cp:'cp', col:'colonia', calle:'cons-calle', num:'cons-numext' },
      { mapId:'cons-map2', latId:'cons-lat2', lngId:'cons-lng2', cp:'cp2', col:'colonia2', calle:'cons-calle2', num:'cons-numext2' },
    ];
    const debounce = (fn, ms)=>{ let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn.apply(null,a), ms); } };
    panes.forEach(cfg=>{
      const mapBox = document.getElementById(cfg.mapId);
      if(!mapBox) return;
      try{
        const map = L.map(mapBox).setView([21.882, -102.296], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);
        const marker = L.marker([21.882, -102.296], { draggable:true }).addTo(map);
        const latI = document.getElementById(cfg.latId); const lngI = document.getElementById(cfg.lngId);
        const setLL = (latlng)=>{ if(latI) latI.value = latlng.lat.toFixed(6); if(lngI) lngI.value = latlng.lng.toFixed(6); };
        setLL(marker.getLatLng());
        marker.on('moveend', (e)=> setLL(e.target.getLatLng()));
        map.on('click', (e)=>{ marker.setLatLng(e.latlng); setLL(e.latlng); });

        async function geocode(){
          const cp = (document.getElementById(cfg.cp)?.value||'').trim();
          const col = (document.getElementById(cfg.col)?.value||'').trim();
          const calle = (document.getElementById(cfg.calle)?.value||'').trim();
          const num = (document.getElementById(cfg.num)?.value||'').trim();
          const mun = (document.getElementById(cfg.col==='colonia'?'municipio':'municipio2')?.value||'').trim();
          const edo = (document.getElementById(cfg.col==='colonia'?'estado':'estado2')?.value||'').trim();
          const q = [calle && (calle + (num? ' ' + num : '')), col, cp, mun, edo, 'M\u00E9xico'].filter(Boolean).join(', ');
          if(!q) return;
          try{
            const r = await fetch('./geocode-proxy.php?q='+encodeURIComponent(q));
            if(!r.ok) throw new Error('HTTP '+r.status);
            const json = await r.json();
            const item = Array.isArray(json) ? json[0] : null;
            if(item && item.lat && item.lon){
              const latlng = { lat: parseFloat(item.lat), lng: parseFloat(item.lon) };
              map.setView(latlng, 17); marker.setLatLng(latlng); setLL(latlng);
            }
          }catch(_){ /* silencioso */ }
        }
        const tryGeo = debounce(()=>{
          const cp = (document.getElementById(cfg.cp)?.value||'').trim();
          const col = (document.getElementById(cfg.col)?.value||'').trim();
          const calle = (document.getElementById(cfg.calle)?.value||'').trim();
          const num = (document.getElementById(cfg.num)?.value||'').trim();
          if(/^\d{5}$/.test(cp) && col && calle && num){ geocode(); }
        }, 500);
        [cfg.cp,cfg.col,cfg.calle,cfg.num].forEach(id=>{
          const el = document.getElementById(id);
          if(!el) return;
          el.addEventListener('change', tryGeo);
          el.addEventListener('input', tryGeo);
        });
      }catch(_){ /* si falla Leaflet en este pane, el iframe fallback lo cubrir? */ }
    });
  })();

  // Fallback autom?tico: actualizar iframe de Google Maps con la direcci?n
  (function autoFrameUpdate(){
    const frame = document.getElementById('cons-map-frame');
    if(!frame) return;
    const debounce = (fn, ms)=>{ let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn.apply(null,a), ms); } };
    const update = debounce(()=>{
      const addr = buildAddress();
      if(!addr) return;
      const url = 'https://www.google.com/maps?q='+encodeURIComponent(addr)+'&z=17&output=embed';
      if(frame.src !== url){ frame.src = url; }
    }, 600);
    ['cp','colonia','cons-calle','cons-numext'].forEach(id=>{
      const el = document.getElementById(id); if(!el) return;
      el.addEventListener('input', update); el.addEventListener('change', update);
    });
  })();

  // Helper opcional: construir horarios en un contenedor clonado (pane2)
  (function(){
    if(window._mx_setupSchedulesFor) return;
    window._mx_setupSchedulesFor = function(container, keySuffix){
      const body = container.querySelector('#sched-body-2');
      if(!body) return;
      body.innerHTML='';
      const dias=[{k:'mon',lbl:'Lunes'},{k:'tue',lbl:'Martes'},{k:'wed',lbl:'Mi?rcoles'},{k:'thu',lbl:'Jueves'},{k:'fri',lbl:'Viernes'},{k:'sat',lbl:'Sobado'},{k:'sun',lbl:'Domingo'}];
      const key='mxmed_cons_schedules'+(keySuffix||'');
      const load=()=>{ try{return JSON.parse(localStorage.getItem(key)||'{}');}catch(e){return{}} };
      const save=v=>localStorage.setItem(key,JSON.stringify(v));
      const state=load();
      const rowDefined = (act, inputs)=> act?.checked || inputs.some(inp=> (inp.value||'').trim());
      dias.forEach(d=>{
        const tr=document.createElement('tr');
        tr.innerHTML=`<td>${d.lbl}</td><td><input type="checkbox" class="form-check-input" id="sch-act-${d.k}${keySuffix||''}"></td><td><div class="d-flex align-items-center gap-1"><input type="time" class="form-control form-control-sm" id="sch-a1-${d.k}${keySuffix||''}"><span>a</span><input type="time" class="form-control form-control-sm" id="sch-b1-${d.k}${keySuffix||''}"></div></td><td><div class="d-flex align-items-center gap-1"><input type="time" class="form-control form-control-sm" id="sch-a2-${d.k}${keySuffix||''}"><span>a</span><input type="time" class="form-control form-control-sm" id="sch-b2-${d.k}${keySuffix||''}"></div></td>`;
        body.appendChild(tr);
        const act=tr.querySelector(`#sch-act-${d.k}${keySuffix||''}`);
        const a1=tr.querySelector(`#sch-a1-${d.k}${keySuffix||''}`);
        const b1=tr.querySelector(`#sch-b1-${d.k}${keySuffix||''}`);
        const a2=tr.querySelector(`#sch-a2-${d.k}${keySuffix||''}`);
        const b2=tr.querySelector(`#sch-b2-${d.k}${keySuffix||''}`);
        const inputs=[a1,b1,a2,b2];
        const sv=state[d.k]||{};
        act.checked=!!sv.act;
        a1.value=sv.a1||'09:00';
        b1.value=sv.b1||'14:00';
        a2.value=sv.a2||'16:00';
        b2.value=sv.b2||'20:00';
        const sync=()=>{
          state[d.k]={act:act.checked,a1:a1.value,b1:b1.value,a2:a2.value,b2:b2.value};
          save(state);
          tr.classList.toggle('sched-defined', rowDefined(act, inputs));
        };
        act.addEventListener('change', sync);
        inputs.forEach(el=>{
          el.addEventListener('change', sync);
          el.addEventListener('input', ()=>{
            if((el.value||'').trim() && !act.checked){
              act.checked = true;
            }
            sync();
          });
        });
        if(rowDefined(act, inputs) && !act.checked){
          act.checked = true;
        }
        sync();
      });
      const copyBtn = container.querySelector('#sched-copy-mon-2');
      const clearBtn= container.querySelector('#sched-clear-2');
      copyBtn?.addEventListener('click', ()=>{ const st=load(); const m=st.mon||{}; ['tue','wed','thu','fri'].forEach(k=>{ st[k]={...m}; }); save(st); try{ window.mxMarkHorarioScroll?.(); }catch(_){ } location.reload(); });
      clearBtn?.addEventListener('click', ()=>{ localStorage.removeItem(key); (window.mxMarkHorarioScroll||markScroll)(); location.reload(); });
    };
  })();

  // Auto abrir colonias al tabular desde CP y permitir selecci?n con flechas
  (function setupColoniaAutoOpen(){
    const cp = document.getElementById('cp');
    const sel = document.getElementById('colonia');
    if(!cp || !sel) return;

    function openSelectList(){
      const total = sel.options ? sel.options.length : 0;
      if(total > 1 && !sel.disabled){
        const n = Math.min(Math.max(6, total), 10); // entre 6 y 10 visibles
        sel.setAttribute('size', n);
        sel.classList.add('select-open');
      }
    }
    function closeSelectList(){ sel.removeAttribute('size'); sel.classList.remove('select-open'); }
    function isOpen(){ return sel.hasAttribute('size'); }

    // Al tabular desde CP, forzar foco en "Colonia" en blur para ganar a la navegaci?n natural
    let cpTabbing = false;
    cp.addEventListener('keydown', (e)=>{ if(e.key === 'Tab' && !e.shiftKey){ cpTabbing = true; } });
    cp.addEventListener('keyup', ()=>{ cpTabbing = false; });
    cp.addEventListener('blur', ()=>{
      if(!cpTabbing) return;
      cpTabbing = false;
      const pollMs = 100; let waited = 0;
      // Redirigir foco y abrir lista cuando existan opciones
      const poll = ()=>{
        sel.focus();
        if((sel.options?.length||0) > 1 && !sel.disabled){ openSelectList(); return; }
        waited += pollMs; if(waited >= 1500) return; // 1.5s m?x
        setTimeout(poll, pollMs);
      };
      setTimeout(poll, 0);
    });
    // Navegaci?n con flechas sin desplazar la p?gina y cierre con Enter/Escape
    sel.addEventListener('keydown', (e)=>{
      if(document.activeElement !== sel || !isOpen()) return;
      const total = sel.options?.length || 0; if(total === 0) return;
      let i = sel.selectedIndex < 0 ? 0 : sel.selectedIndex;
      switch(e.key){
        case 'ArrowDown': e.preventDefault(); sel.selectedIndex = Math.min(total-1, i+1); break;
        case 'ArrowUp': e.preventDefault(); sel.selectedIndex = Math.max(0, i-1); break;
        case 'PageDown': e.preventDefault(); sel.selectedIndex = Math.min(total-1, i+5); break;
        case 'PageUp': e.preventDefault(); sel.selectedIndex = Math.max(0, i-5); break;
        case 'Home': e.preventDefault(); sel.selectedIndex = 0; break;
        case 'End': e.preventDefault(); sel.selectedIndex = total-1; break;
        case 'Enter':
          e.preventDefault();
          closeSelectList();
          sel.dispatchEvent(new Event('change'));
          document.getElementById('cons-calle')?.focus();
          break;
        case 'Escape': e.preventDefault(); closeSelectList(); break;
      }
    });
    // Cerrar al perder foco
    sel.addEventListener('blur', closeSelectList);
    // Al cambiar colonia, pasar a Calle
    sel.addEventListener('change', ()=>{ document.getElementById('cons-calle')?.focus(); });
  })();

  // Grupo Médico: habilitar/deshabilitar campo seg?n radios
  (function setupGrupoMédico(){
    const rSi = document.getElementById('cons-grupo-si');
    const rNo = document.getElementById('cons-grupo-no');
    const grp = document.getElementById('cons-grupo-nombre');
    if(!rSi || !rNo || !grp) return;
    function sync(){ if(rSi.checked){ grp.removeAttribute('disabled'); grp.focus(); } else { /* no limpiar valor para evitar confusi?n */ grp.setAttribute('disabled','disabled'); } }
    rSi.addEventListener('change', sync); rNo.addEventListener('change', sync);
    sync();
  })();

  // Validaci?n de tel?fonos (MX/E.164): 10 d?gitos nacionales o +52 + 10 d?gitos
  (function setupPhoneValidation(){
    function analyzePhone(val, isLive){
      const s = (val||'').trim();
      if(s === '') return { ok:true };
      // Solo caracteres permitidos durante edici?n
      if(/[^0-9()+\-\s+]/.test(s)) return { ok:false, reason:'invalid_char' };
      // '+' solo al inicio y m?ximo 1
      if((s.match(/\+/g)||[]).length > 1 || (s.includes('+') && !s.startsWith('+'))) return { ok:false, reason:'invalid_char' };
      const digits = s.replace(/\D/g,'');
      // Si empieza con +52, objetivo 12 d?gitos (52 + 10 nacionales)
      const hasPlus52 = s.startsWith('+') && digits.startsWith('52');
      const target = hasPlus52 ? 12 : 10;
      if(isLive){
        if(digits.length > target) return { ok:false, reason:'too_long' };
        if(/[^0-9()+\-\s]/.test(s)) return { ok:false, reason:'invalid_char' };
        // Mientras escribe, no marcar corto a?n
        return { ok:true };
      } else {
        if(digits.length !== target) return { ok:false, reason: digits.length < target ? 'too_short' : 'too_long' };
        return { ok:true };
      }
    }
    function messageFor(reason){
      switch(reason){
        case 'invalid_char': return 'Solo números y + ( ) -';
        case 'too_short': return 'Número incompleto (se requieren 10 dígitos)';
        case 'too_long': return 'Demasiados dígitos (máximo 10 o +52 + 10)';
        default: return 'Teléfono inválido';
      }
    }
    function applyState(el, isLive){
      const res = analyzePhone(el.value, !!isLive);
      const wrap = el.closest('.save-wrap');
      const b = wrap?.querySelector('.err-bubble');
      if(res.ok){
        if(wrap){ wrap.classList.remove('has-error'); if(b) b.style.opacity='0'; }
        else { el.classList.remove('is-invalid'); }
        el.setCustomValidity('');
      }else{
        const msg = messageFor(res.reason);
        if(b) b.textContent = msg;
        if(wrap){ wrap.classList.add('has-error'); if(b) b.style.opacity = '1'; }
        else { el.classList.add('is-invalid'); }
        el.setCustomValidity('Teléfono inválido');
      }
    }
    // Reglas adicionales en vivo: tope de 3 letras y tope de dígitos
    const _state = new WeakMap(); // { value, letters, digits }

    function countLetters(s){
      const m = (s||'').match(/[A-Za-zÁÉÍÓÚÜÑáéíóúüñ]/g);
      return m ? m.length : 0;
    }

    function digitsTargetFor(val){
      const s = (val||'').trim();
      const digits = s.replace(/\D/g,'');
      const hasPlus52 = s.startsWith('+') && digits.startsWith('52');
      return hasPlus52 ? 12 : 10;
    }

    function onLiveInput(el){
      const prev = _state.get(el) || { value: '', letters: 0, digits: 0 };
      const wrap = el.closest('.save-wrap');
      const b = wrap?.querySelector('.err-bubble');

      const val = el.value || '';
      const letters = countLetters(val);
      const digits = (val.match(/\d/g)||[]).length;
      const target = digitsTargetFor(val);

      // 1) Aviso cuando llega a 3 letras y bloqueo a partir de la 4?
      if(letters >= 3){
        if(b) b.textContent = 'Ingresa solo n?meros';
        if(wrap){ wrap.classList.add('has-error'); if(b) b.style.opacity = '1'; }
        // Si intenta exceder 3 letras, revertir a valor previo
        if(letters > 3){
          el.value = prev.value || '';
          try{ el.setSelectionRange(el.value.length, el.value.length); }catch(_){ }
          _state.set(el, { value: el.value, letters: countLetters(el.value), digits: (el.value.match(/\d/g)||[]).length });
          return; // no continuar, ya mostramos burbuja y revertimos
        }
      }

      // 2) Limitar cantidad de d?gitos en vivo (10 o +52+10)
      if(digits > target){
        if(b) b.textContent = (target === 12 ? 'Demasiados d?gitos (m?ximo +52 + 10)' : 'Demasiados d?gitos (m?ximo 10)');
        if(wrap){ wrap.classList.add('has-error'); if(b) b.style.opacity = '1'; }
        el.value = prev.value || '';
        try{ el.setSelectionRange(el.value.length, el.value.length); }catch(_){ }
        _state.set(el, { value: el.value, letters: countLetters(el.value), digits: (el.value.match(/\d/g)||[]).length });
        return;
      }

      // 3) Si comienza a escribir n?meros, ocultar burbuja de letras
      if(letters < 3){
        if(wrap){ wrap.classList.remove('has-error'); if(b) b.style.opacity = '0'; }
      }

      // 4) Aplicar validaci?n est?ndar en vivo (caracteres permitidos y overflow)
      applyState(el, true);

      // 5) Guardar estado actual
      _state.set(el, { value: el.value, letters, digits });
    }

    // Exponer para panes clonados
    window._mx_phone_bind = function(container){
      const scope = container || document;
      const all = Array.from(scope.querySelectorAll('[data-validate="phone"], input[type="tel"]'));
      all.forEach(el=>{
        el.addEventListener('input', ()=>onLiveInput(el));
        el.addEventListener('blur', ()=>applyState(el, false));
        // Estado inicial
        _state.set(el, { value: el.value||'', letters: countLetters(el.value), digits: (el.value||'').replace(/\D/g,'').length });
        applyState(el, true);
      });
    };
    window._mx_phone_bind(document);
  })();

  // WhatsApp consultorio: sincronizar con Datos Generales si se marca la casilla
  (function setupWhatsAppSync(){
    const wa = document.getElementById('cons-wa');
    const syncCb = document.getElementById('cons-wa-sync');
    const dg = document.getElementById('dp-whatsapp');
    if(!wa || !syncCb) return;
    function fillFromDG(){ if(dg){ wa.value = dg.value || ''; wa.dispatchEvent(new Event('input')); } }
    function toggle(){
      if(syncCb.checked){
        wa.setAttribute('disabled','disabled');
        wa.placeholder = '+52 ...';
        fillFromDG();
      } else {
        wa.removeAttribute('disabled');
        wa.value = '';
        wa.placeholder = 'otro numero Whatsapp';
        wa.focus();
      }
    }
    syncCb.addEventListener('change', toggle);
    if(dg){ dg.addEventListener('input', ()=>{ if(syncCb.checked) fillFromDG(); }); }
    // inicial
    toggle();
  })();

  // Ocultar campos antiguos del consultorio para evitar duplicados
  (function hideLegacyFields(){
    const root = document.querySelector("#sede1"); if(!root) return;
    const labels = ["Nombre de la sede","Teléfono (planes de pago)","Dirección","Horario","Notas"];
    labels.forEach(txt=>{
      const el = Array.from(root.querySelectorAll("label.form-label")).find(l=> (l.textContent||"").trim().indexOf(txt)===0);
      if(el){ const wrap = el.closest("[class*='col-']"); if(wrap) wrap.style.display='none'; }
    });
  })();
})();

// ====== Seguridad: checklist compacto de contraseña ======
(function(){
  const panel = document.getElementById('pwd-change-panel');
  if(!panel) return;
  const summary = document.getElementById('pwd-summary');
  const newInput = panel.querySelector('[data-pwd-new]');
  const confirmInput = panel.querySelector('[data-pwd-confirm]');
  const submitBtn = panel.querySelector('[data-pwd-submit]');
  const matchHint = panel.querySelector('[data-pwd-match-hint]');
  const dismissBtns = panel.querySelectorAll('[data-verify-dismiss]');
  if(summary){
    panel.addEventListener('show.bs.collapse', ()=> summary.classList.add('d-none'));
    panel.addEventListener('hidden.bs.collapse', ()=>{
      summary.classList.remove('d-none');
      resetForm();
    });
  }
  if(!newInput) return;
  const iconFor = (chip, met)=>{
    const ico = chip.querySelector('.material-symbols-rounded');
    if(ico) ico.textContent = met ? 'check_circle' : 'cancel';
  };
  const tests = {
    length: (val)=> val.length >= 8,
    upper: (val)=> /[A-ZÁÉÍÓÚÜÑ]/.test(val),
    number: (val)=> /\d/.test(val),
    symbol: (val)=> /[^A-Za-z0-9]/.test(val),
  };
  const runChecks = (val)=>{
    let allMet = true;
    Object.entries(tests).forEach(([key, fn])=>{
      const chip = panel.querySelector(`.pwd-chip[data-check="${key}"]`);
      if(!chip) return;
      const met = fn(val);
      chip.classList.toggle('met', met);
      iconFor(chip, met);
      if(!met) allMet = false;
    });
    return allMet;
  };
  const resetForm = ()=>{
    newInput.value = '';
    if(confirmInput) confirmInput.value = '';
    runChecks('');
    if(matchHint) matchHint.classList.add('d-none');
    if(submitBtn) submitBtn.disabled = true;
  };
  const syncState = ()=>{
    const pwd = newInput.value || '';
    const confirm = confirmInput?.value || '';
    const checksOk = runChecks(pwd);
    const match = confirmInput ? (pwd.length > 0 && pwd === confirm) : true;
    if(matchHint){
      matchHint.classList.toggle('d-none', match || confirm.length===0);
    }
    if(submitBtn){
      submitBtn.disabled = !(checksOk && match);
    }
  };
  const hidePanel = ()=>{
    const collapse = window.bootstrap && window.bootstrap.Collapse ? window.bootstrap.Collapse.getOrCreateInstance(panel) : null;
    if(collapse){
      collapse.hide();
    }else{
      panel.classList.remove('show');
      if(summary) summary.classList.remove('d-none');
      resetForm();
    }
  };
  dismissBtns.forEach(btn=>{
    btn.addEventListener('click', (ev)=>{
      ev.preventDefault();
      hidePanel();
    });
  });
  newInput.addEventListener('input', syncState);
  confirmInput?.addEventListener('input', syncState);
  panel.addEventListener('shown.bs.collapse', syncState);
  syncState();
})();

// ====== Seguridad: validación Teléfono/E-mail ======
(function(){
  const panels = document.querySelectorAll('[data-verify-panel]');
  if(!panels.length) return;

  const getCollapse = (panel)=>{
    if(window.bootstrap && window.bootstrap.Collapse){
      return window.bootstrap.Collapse.getOrCreateInstance(panel);
    }
    return null;
  };

  const formatTime = (secs)=>{
    const m = String(Math.floor(secs/60)).padStart(2,'0');
    const s = String(secs%60).padStart(2,'0');
    return `${m}:${s}`;
  };

  panels.forEach(panel=>{
    const summarySelector = panel.getAttribute('data-summary');
    const summary = summarySelector ? document.querySelector(summarySelector) : null;
    const otpInputs = panel.querySelectorAll('[data-otp-input]');
    const submitBtn = panel.querySelector('[data-otp-submit]');
    const resendBtn = panel.querySelector('[data-otp-resend]');
    const countdown = panel.querySelector('[data-otp-countdown]');
    const dismissBtns = panel.querySelectorAll('[data-verify-dismiss]');
    const stepsRoot = panel.querySelector('[data-verify-steps]');
    const stepBlocks = stepsRoot ? stepsRoot.querySelectorAll('[data-step]') : [];
    let timer=null;

    const syncValueFromSummary = ()=>{
      if(!summary) return;
      const src = summary.querySelector('input[type="tel"], input[type="email"]');
      const target = panel.querySelector('[data-verify-value]');
      if(src && target){
        target.value = src.value || '';
        target.placeholder = src.getAttribute('placeholder') || target.placeholder;
      }
    };

    const clearTimer = ()=>{ if(timer){ clearInterval(timer); timer=null; } };
    const resetPanel = ()=>{
      otpInputs.forEach(inp=> inp.value='');
      if(submitBtn) submitBtn.disabled = true;
      if(resendBtn){
        resendBtn.disabled = false;
        resendBtn.classList.remove('disabled');
      }
      clearTimer();
      if(countdown) countdown.textContent = '01:00';
      if(stepBlocks.length) setStep('method');
    };
    const setStep = (name)=>{
      if(!stepBlocks.length) return;
      stepBlocks.forEach(block=>{
        block.classList.toggle('d-none', block.getAttribute('data-step') !== name);
      });
      if(name === 'otp'){
        otpInputs[0]?.focus();
        startTimer();
        updateOtpState();
      }else{
        clearTimer();
        if(countdown) countdown.textContent = '01:00';
      }
    };
    const onShow = ()=> summary?.classList.add('d-none');
    const onHidden = ()=>{
      summary?.classList.remove('d-none');
      resetPanel();
    };
    const onShown = ()=>{
      otpInputs[0]?.focus();
      startTimer();
      updateOtpState();
    };
    const hidePanel = ()=>{
      const collapse = getCollapse(panel);
      if(collapse){
        collapse.hide();
      }else{
        onHidden();
        panel.classList.remove('show');
      }
    };
    const showPanel = ()=>{
      const collapse = getCollapse(panel);
      if(collapse){
        syncValueFromSummary();
        collapse.show();
      }else{
        onShow();
        syncValueFromSummary();
        panel.classList.add('show');
        onShown();
      }
    };
    const startTimer = ()=>{
      if(!countdown) return;
      clearTimer();
      let remaining = 60;
      countdown.textContent = formatTime(remaining);
      timer = setInterval(()=>{
        remaining -=1;
        countdown.textContent = formatTime(Math.max(remaining,0));
        if(remaining <= 0){
          clearTimer();
        }
      },1000);
    };
    const updateOtpState = ()=>{
      const code = Array.from(otpInputs).map(inp=> (inp.value||'').trim()).join('');
      if(submitBtn) submitBtn.disabled = code.length !== otpInputs.length;
    };

    if(summary){
      panel.addEventListener('show.bs.collapse', onShow);
      panel.addEventListener('hidden.bs.collapse', onHidden);
      panel.addEventListener('show.bs.collapse', syncValueFromSummary);
    }

    otpInputs.forEach((inp, idx)=>{
      inp.addEventListener('input', ()=>{
        inp.value = inp.value.replace(/[^0-9]/g,'').slice(0,1);
        inp.classList.toggle('filled', !!inp.value);
        if(inp.value && otpInputs[idx+1]){
          otpInputs[idx+1].focus();
        }
        updateOtpState();
      });
      inp.addEventListener('keydown', (ev)=>{
        if(ev.key === 'Backspace' && !inp.value && otpInputs[idx-1]){
          otpInputs[idx-1].focus();
        }
      });
    });

    resendBtn?.addEventListener('click', (ev)=>{
      ev.preventDefault();
      resendBtn.disabled = true;
      resendBtn.classList.add('disabled');
      startTimer();
      setTimeout(()=>{
        resendBtn.disabled = false;
        resendBtn.classList.remove('disabled');
      }, 60000);
    });

    dismissBtns.forEach(btn=>{
      btn.addEventListener('click', (ev)=>{
        ev.preventDefault();
        hidePanel();
      });
    });

    panel.addEventListener('shown.bs.collapse', onShown);
    panel.__verifyShow = showPanel;

    // Step navigation
    panel.querySelectorAll('[data-verify-next]').forEach(btn=>{
      btn.addEventListener('click', (ev)=>{
        ev.preventDefault();
        const target = btn.getAttribute('data-verify-next');
        if(target === 'done' && submitBtn && submitBtn.disabled) return;
        setStep(target || 'method');
      });
    });
    panel.querySelectorAll('[data-verify-set]').forEach(btn=>{
      btn.addEventListener('click', (ev)=>{
        ev.preventDefault();
        const target = btn.getAttribute('data-verify-set') || 'method';
        setStep(target);
      });
    });

    // Initialize default step
    setStep('method');
  });

  const triggers = document.querySelectorAll('[data-verify-trigger]');
  const validateTriggerValue = (btn)=>{
    const inputSel = btn.getAttribute('data-verify-input');
    const type = btn.getAttribute('data-verify-type');
    const hintSel = btn.getAttribute('data-verify-hint');
    const inputEl = inputSel ? document.querySelector(inputSel) : null;
    const hintEl = hintSel ? document.querySelector(hintSel) : null;
    let ok = true;
    const value = (inputEl?.value || '').trim();
    if(type === 'phone'){
      const digits = value.replace(/\D/g,'');
      ok = digits.length === 10;
    }
    if(hintEl){
      hintEl.classList.toggle('d-none', ok);
    }
    if(!ok){
      inputEl?.focus();
    }
    return ok;
  };

  triggers.forEach(btn=>{
    btn.addEventListener('click', (ev)=>{
      ev.preventDefault();
      if(!validateTriggerValue(btn)) return;
      const targetSel = btn.getAttribute('data-verify-trigger');
      const panel = targetSel ? document.querySelector(targetSel) : null;
      if(!panel) return;
      if(typeof panel.__verifyShow === 'function'){
        panel.__verifyShow();
      }
    });
  });
})();

// ===== Correcciones rápidas de acentos en header (muestra) =====
(function(){
  const t = document.querySelector('.optimo'); if(t) t.textContent = 'Óptimo';
  const n = document.querySelector('.name'); if(n && /Muñoz|Mu�oz/.test(n.textContent)) n.textContent = 'Leticia Muñoz Alfaro';
  const img = document.querySelector('.header-top img'); if(img) img.alt = 'México Médico';
  if(document.title && document.title.indexOf('MXMed')>=0) document.title = 'MXMed 2025 · Perfil Médico';
})();

// ===== Sugerencia de Grupo Médico y sincronización de logotipo (demo) =====
(function setupGrupoMédicoSuggest(){
  const keyAssoc = 'grupo_Médico_assoc';

  function getAddr(){
    return {
      cp: (document.getElementById('cp')?.value||'').trim(),
      col: (document.getElementById('colonia')?.value||'').trim(),
      mun: (document.getElementById('municipio')?.value||'').trim(),
      edo: (document.getElementById('estado')?.value||'').trim(),
      calle: (document.getElementById('cons-calle')?.value||'').trim(),
      numext: (document.getElementById('cons-numext')?.value||'').trim()
    };
  }

  function suggestGroup(addr){
    const hasCore = addr.cp && addr.col && addr.mun && addr.edo;
    if(!hasCore) return null;
    const logo = 'data:image/svg+xml;utf8,'+
      '<svg xmlns="http://www.w3.org/2000/svg" width="240" height="240">'+
      '<rect width="100%" height="100%" fill="%2300ADC1"/>'+
      '<text x="50%" y="55%" font-size="96" text-anchor="middle" fill="white" font-family="Arial">GM</text>'+
      '</svg>';
    return {
      id: 'demo-123',
      nombre: 'Grupo M\u00E9dico Central',
      addr: [addr.col, addr.mun, addr.edo].filter(Boolean).join(', '),
      logo_url: logo
    };
  }

  function showModal(s){
    const el = document.getElementById('modalGrupoSuggest'); if(!el) return;
    el.querySelector('#grp-name').textContent = s.nombre || 'Grupo Médico';
    el.querySelector('#grp-addr').textContent = s.addr || '';
    const m = (window.bootstrap && bootstrap.Modal && bootstrap.Modal.getOrCreateInstance) ? bootstrap.Modal.getOrCreateInstance(el) : new bootstrap.Modal(el);
    // Evitar reentradas mientras el modal est visible
    window._mx_suggestBusy = true;
    const onHidden = ()=>{
      el.removeEventListener('hidden.bs.modal', onHidden);
      window._mx_suggestBusy = false;
      try{
        document.body.classList.remove('modal-open');
        document.body.style.overflow = '';
        document.body.style.removeProperty('padding-right');
        document.querySelectorAll('.modal-backdrop').forEach(b=>b.parentNode && b.parentNode.removeChild(b));
      }catch(_){ }
    };
    el.addEventListener('hidden.bs.modal', onHidden);
    const btnSi = document.getElementById('modalGrupoSi');
    const btnNo = document.getElementById('modalGrupoNo');
    if(btnSi) btnSi.onclick = ()=>{ accept(s, m); setTimeout(onHidden, 120); };
    if(btnNo) btnNo.onclick = ()=>{ decline(s, m); setTimeout(onHidden, 120); };
    m.show();
  }

  function accept(s, modal){
    applyGroupSelection(s);
    modal?.hide();
  }

  function decline(_s, modal){
    try{ localStorage.setItem(keyAssoc+':decline', JSON.stringify({ when: Date.now(), addr: getAddr() })); }catch(_){ }
    modal?.hide();
  }

  function applyAssocUI(s){
    const img = document.getElementById('cons-logo-img');
    const prev = document.getElementById('cons-logo-prev');
    const slot = document.getElementById('cons-logo-slot');
    const uploadLogo = document.querySelector('.mf-upload[data-type="logo"]');
    if(img && s.logo_url){
      img.src = s.logo_url;
    }
    if(prev){
      prev.removeAttribute('hidden');
      prev.style.display = 'flex';
    }
    if(slot){
      slot.classList.add('show-preview');
      slot.classList.add('has-logo');
      mxSetLogoSource('assoc');
      const drop = slot.querySelector('.logo-slot-drop');
      if(drop){ drop.setAttribute('hidden','hidden'); }
    }
    if(uploadLogo){
      uploadLogo.classList.add('has-logo');
      const ghost = uploadLogo.querySelector('.mf-ghost');
      if(ghost){
        ghost.style.display = 'none';
        ghost.setAttribute('aria-hidden','true');
      }
    }
    mxToggleLogoSyncMsg(true);
    mxToggleLogoManualMsg(false);
    const file = document.getElementById('cons-logo'); if(file) file.setAttribute('disabled','disabled');
    // Bloquear campos clave de direcci?n cuando hay asociaci?n
    ;['cp','colonia','municipio','estado'].forEach(id=>{
      const el = document.getElementById(id); if(!el) return;
      try{ el.setAttribute('disabled','disabled'); el.disabled = true; }catch(_){ }
    });
    // Control de borrado (X en esquina)
    const del = document.getElementById('cons-logo-del');
    if(del){ del.onclick = (ev)=>{
      ev.stopPropagation();
      let current = s;
      try{
        const stored = JSON.parse(localStorage.getItem(keyAssoc)||'null');
        if(stored) current = stored;
      }catch(_){ }
      openUnlinkModal(current, ()=>{
        try{ localStorage.removeItem(keyAssoc); localStorage.removeItem(keyAssoc+':decline'); }catch(_){ }
        removeAssocUI();
        const rNo = document.getElementById('cons-grupo-no');
        const rSi = document.getElementById('cons-grupo-si');
        const grp = document.getElementById('cons-grupo-nombre');
        if(rNo){ rNo.checked = true; rNo.dispatchEvent(new Event('change')); }
        if(rSi){ rSi.checked = false; }
        if(grp){ grp.value=''; grp.setAttribute('disabled','disabled'); }
        ['cp','colonia','municipio','estado','cons-calle','cons-numext'].forEach(id=>{
          const el = document.getElementById(id); if(!el) return;
          el.removeAttribute('disabled');
          if('disabled' in el) try{ el.disabled = false; }catch(_){ }
          if(id==='colonia'){ el.classList.remove('select-open'); el.removeAttribute('size'); }
        });
      }, ()=>{});
    }; }
  }

  function removeAssocUI(){
    mxResetLogoPreview();
    mxToggleLogoSyncMsg(false);
    mxToggleLogoManualMsg(false);
    const grp = document.getElementById('cons-grupo-nombre'); if(grp){ grp.classList.remove('grp-selected'); }
  }

  // Modal para desvincular grupo (con botones centrados y "S? desvincular")
  function openUnlinkModal(saved, onConfirm, onCancel){
    const el = document.getElementById('modalGrupoUnlinkLogo');
    if(!el){ const ok = confirm('?Est? seguro que desea desvincular su consultorio?'); if(ok) onConfirm?.(); else onCancel?.(); return; }
    const nameEl = el.querySelector('#grp-unlink-logo-name');
    if(nameEl) nameEl.textContent = saved?.nombre || 'este grupo';
    const yesBtn = document.getElementById('modalGrupoUnlinkLogoYes');
    const m = (window.bootstrap && bootstrap.Modal && bootstrap.Modal.getOrCreateInstance) ? bootstrap.Modal.getOrCreateInstance(el) : new bootstrap.Modal(el);
    const cleanup = ()=>{ try{ yesBtn.onclick = null; }catch(_){ } };
    el.addEventListener('hidden.bs.modal', function onHidden(){ el.removeEventListener('hidden.bs.modal', onHidden); cleanup(); onCancel?.(); }, { once:true });
    yesBtn.onclick = ()=>{ cleanup(); onConfirm?.(); m.hide(); };
    m.show();
  }

  let debounceT = null;
  let suppressGroupChange = false;
  let inlineLayer = null;
  let inlineItems = [];
  let inlineIndex = -1;
  let inlineKeyHandler = null;

  function highlightInline(idx){
    inlineIndex = idx;
    inlineItems.forEach((item, i)=>{
      if(item.node){
        item.node.classList.toggle('active', i === inlineIndex);
        if(i === inlineIndex){
          try{ item.node.scrollIntoView({block:'nearest'}); }catch(_){}
        }
      }
    });
  }

  function hideInline(){
    if(inlineLayer){
      inlineLayer.remove();
      inlineLayer = null;
    }
    inlineItems = [];
    inlineIndex = -1;
    if(inlineKeyHandler){
      document.removeEventListener('keydown', inlineKeyHandler, true);
      inlineKeyHandler = null;
    }
  }

  function showInline(arr, anchor){
    hideInline();
    if(!arr || !arr.length || !anchor) return;
    const rect = anchor.getBoundingClientRect();
    const box = document.createElement('div');
    box.className = 'grp-suggest';
    box.style.left = (window.scrollX + rect.left) + 'px';
    box.style.top  = (window.scrollY + rect.bottom + 4) + 'px';
    box.style.width= rect.width + 'px';
    inlineItems = [];
    arr.forEach(g=>{
      const it = document.createElement('div'); it.className='item';
      const nm = document.createElement('div'); nm.className='name'; nm.textContent = g.nombre;
      const ad = document.createElement('div'); ad.className='addr'; ad.textContent = g.addr||'';
      it.appendChild(nm); it.appendChild(ad);
      it.addEventListener('click', ()=>{
        hideInline();
        applyGroupSelection(g);
      });
      box.appendChild(it);
      inlineItems.push({ data:g, node:it });
    });
    document.body.appendChild(box);
    inlineLayer = box;
    highlightInline(0);
    const handler = (ev)=>{ if(!box.contains(ev.target) && ev.target!==anchor){ hideInline(); document.removeEventListener('mousedown', handler, true); } };
    document.addEventListener('mousedown', handler, true);
    inlineKeyHandler = (ev)=>{
      if(!inlineLayer || !inlineItems.length) return;
      if(ev.key === 'ArrowDown'){
        ev.preventDefault();
        const next = (inlineIndex + 1) % inlineItems.length;
        highlightInline(next);
      }else if(ev.key === 'ArrowUp'){
        ev.preventDefault();
        const next = (inlineIndex - 1 + inlineItems.length) % inlineItems.length;
        highlightInline(next);
      }else if(ev.key === 'Enter'){
        ev.preventDefault();
        const item = inlineItems[inlineIndex];
        if(item){
          hideInline();
          applyGroupSelection(item.data);
        }
      }else if(ev.key === 'Escape'){
        ev.preventDefault();
        hideInline();
      }
    };
    document.addEventListener('keydown', inlineKeyHandler, true);
  }

  function buildDemoLogo(text, color){
    const svg =
      `<svg xmlns="http://www.w3.org/2000/svg" width="240" height="240">`+
      `<rect width="100%" height="100%" rx="28" fill="${color}"/>`+
      `<text x="50%" y="55%" font-size="72" text-anchor="middle" fill="white" font-family="Arial,Helvetica,sans-serif">${text}</text>`+
      `</svg>`;
    return 'data:image/svg+xml;utf8,' + encodeURIComponent(svg);
  }
  const DEMO_GROUPS = [
    {
      id:'grp-star',
      nombre:'Star M\u00e9dica',
      calle:'Av. Aguascalientes',
      numext:'1420',
      addr:'Aguascalientes Centro',
      logo_url: 'assets/img/star medica.svg'
    },
    {
      id:'grp-san-juan',
      nombre:'M\u00e9dica San Juan',
      calle:'Adolfo L\u00f3pez Mateos',
      numext:'892',
      addr:'Zona Centro',
      logo_url: 'assets/img/medica san juan.png'
    }
  ];

  function listMatches(addr){
    if(addr && addr.col){
      // Priorizar grupo dependiendo de la colonia (demostraci?n simple)
      if(/centro/i.test(addr.col)){
        return DEMO_GROUPS;
      }
    }
    return DEMO_GROUPS;
  }

  function fillConsultorioTitle(name){
    const tit = document.getElementById('cons-titulo');
    if(!tit) return;
    tit.value = 'Consultorio ' + (name || '');
    tit.dataset.autofill = '1';
    tit.dispatchEvent(new Event('input'));
    tit.dispatchEvent(new Event('change'));
    requestAnimationFrame(()=>{
      try{
        tit.focus();
        if(typeof tit.setSelectionRange === 'function'){
          const L = tit.value.length; tit.setSelectionRange(L, L);
        }
      }catch(_){ }
      setTimeout(()=>{ try{ if(document.activeElement === tit) tit.blur(); }catch(_){ } }, 1200);
    });
  }

  function applyGroupSelection(group){
    if(!group) return;
    hideInline();
    const rSi = document.getElementById('cons-grupo-si');
    if(rSi){
      suppressGroupChange = true;
      rSi.checked = true;
      rSi.dispatchEvent(new Event('change'));
      suppressGroupChange = false;
    }
    const grp = document.getElementById('cons-grupo-nombre');
    if(grp){
      grp.removeAttribute('disabled'); try{ grp.disabled = false; }catch(_){}
      grp.value = group.nombre || '';
      grp.classList.add('grp-selected');
      grp.dispatchEvent(new Event('input'));
    }
    const calle = document.getElementById('cons-calle');
    if(calle){
      calle.value = group.calle || '';
      calle.dispatchEvent(new Event('input'));
      calle.dispatchEvent(new Event('change'));
    }
    const numext = document.getElementById('cons-numext');
    if(numext){
      numext.value = group.numext || '';
      numext.dispatchEvent(new Event('input'));
      numext.dispatchEvent(new Event('change'));
    }
    fillConsultorioTitle(group.nombre);
    try{ localStorage.setItem(keyAssoc, JSON.stringify(group)); }catch(_){ }
    applyAssocUI(group);
  }
  function onInputsChange(){
    clearTimeout(debounceT);
    debounceT = setTimeout(()=>{
      if(window._mx_suggestBusy) return;
      const a = getAddr();
      const matches = listMatches(a);
      try{ window._mx_lastGroupMatches = matches; }catch(_){ }
      const rSi = document.getElementById('cons-grupo-si');
      const grp = document.getElementById('cons-grupo-nombre');
      if(rSi && rSi.checked && grp === document.activeElement){ if(matches && matches.length){ showInline(matches, grp); } }
    }, 400);
  }

  function init(){
    try{
      const saved = JSON.parse(localStorage.getItem(keyAssoc)||'null');
      if(saved) applyAssocUI(saved);
    }catch(_){ }
    ;['cp','colonia','municipio','estado','cons-calle','cons-numext'].forEach(id=>{
      const el = document.getElementById(id);
      if(el){ el.addEventListener('change', onInputsChange); el.addEventListener('blur', onInputsChange); }
    });
    const rSi = document.getElementById('cons-grupo-si');
    const rNo = document.getElementById('cons-grupo-no');
    const grp = document.getElementById('cons-grupo-nombre');
    if(grp){
      grp.addEventListener('focus', ()=>{ if(!(rSi && rSi.checked)) return; const a=getAddr(); const m=listMatches(a); if(m && m.length){ showInline(m, grp); } });
      grp.addEventListener('input', ()=> hideInline());
      grp.addEventListener('blur', ()=> setTimeout(hideInline, 150));
    }
    if(rSi){ rSi.addEventListener('change', ()=>{ if(suppressGroupChange) return; if(rSi.checked && grp){ const a=getAddr(); const m=listMatches(a); if(m && m.length){ showInline(m, grp); grp.focus(); } }}); }
    // Si el usuario teclea en el t?tulo, deja de ser autogenerado
    const tit = document.getElementById('cons-titulo');
    if(tit){ tit.addEventListener('input', (e)=>{ if(e.isTrusted){ try{ delete tit.dataset.autofill; }catch(_){ tit.removeAttribute('data-autofill'); } } }); }
    if(rNo){ rNo.addEventListener('change', ()=>{
      hideInline();
      if(!rNo.checked) return;
      // Si hay asociaci?n vigente, confirmar desvincular
      let saved=null; try{ saved = JSON.parse(localStorage.getItem(keyAssoc)||'null'); }catch(_){ saved=null; }
      if(saved){
        openUnlinkModal(saved, ()=>{
          try{ localStorage.removeItem(keyAssoc); localStorage.removeItem(keyAssoc+':decline'); }catch(_){ }
          removeAssocUI();
          if(grp){ grp.value=''; grp.setAttribute('disabled','disabled'); }
          ['cp','colonia','municipio','estado','cons-calle','cons-numext'].forEach(id=>{
            const el=document.getElementById(id); if(!el) return; el.removeAttribute('disabled'); try{ el.disabled=false; }catch(_){ }
            if(id==='colonia'){ el.classList.remove('select-open'); el.removeAttribute('size'); }
          });
        }, ()=>{ rSi.checked=true; rSi.dispatchEvent(new Event('change')); });
        return;
      }
    }); }
  }

  if(document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', init);
  }else{ init(); }
})();

// ===== Ajustes puntuales de textos (s?lo secciones espec?ficas) =====
(function fixMxmedTextos(){
  try{
    // 1) Header: "Óptimo"
    const t = document.querySelector('.optimo');
    if(t) t.textContent = '\u00D3ptimo';

    // 2) Horarios: normalizar separadores y d?as con acentos
    ['#sched-body','#sched-body-2'].forEach(sel=>{
      const cont = document.querySelector(sel);
      if(!cont) return;
      // Separador entre horas
      cont.querySelectorAll('span').forEach(sp=>{
        const s = (sp.textContent||'').trim();
        if(s && s !== '-' && /[^0-9A-Za-z:\-]/.test(s)) sp.textContent = '-';
      });
      // D?as con acentos correctos
      const mapDias = {
        'Mi?rcoles':'Mi\u00E9rcoles', 'Mi?rcoles':'Mi\u00E9rcoles',
        'Sobado':'S\u00E1bado', 'S?bado':'S\u00E1bado'
      };
      cont.querySelectorAll('tr td:first-child').forEach(td=>{
        const raw = (td.textContent||'').trim();
        if(mapDias[raw]) td.textContent = mapDias[raw];
      });
    });
  }catch(_){ }
})();


// ===== Grupo Médico: asegurarnos de limpieza de overlay al cerrar =====
(function ensureGrupoModalCleanup(){
  function cleanup(){
    try{
      document.body.classList.remove('modal-open');
      document.querySelectorAll('.modal-backdrop').forEach(b=>b.parentNode?.removeChild(b));
    }catch(_){ }
  }
  const el = document.getElementById('modalGrupoSuggest');
  if(!el) return;
  el.addEventListener('hidden.bs.modal', cleanup);
  document.getElementById('modalGrupoNo')?.addEventListener('click', ()=>{
    const inst = window.bootstrap?.Modal?.getInstance ? window.bootstrap.Modal.getInstance(el) : null;
    inst?.hide();
    setTimeout(cleanup, 50);
  });
})();

// ===== Widget flotante: Reiniciar estado (demo local) =====
(function addResetWidget(){
  try{
    // Mostrar solo en entorno local (localhost/127.0.0.1/::1 o file:)
    const isLocal = (location.protocol === 'file:') || /^(localhost|127\.0\.0\.1|::1)$/i.test(location.hostname||'');
    if(!isLocal) return;
    // Crear bot?n flotante s?lo una vez
    if(document.getElementById('mx-dev-reset')) return;
    const btn = document.createElement('button');
    btn.id = 'mx-dev-reset';
    btn.type = 'button';
    btn.textContent = 'Reiniciar estado';
    btn.setAttribute('aria-label','Reiniciar estado local');
    Object.assign(btn.style, {
      position:'fixed', top:'10px', right:'12px',
      background:'#d81b60', color:'#fff', border:'none',
      padding:'8px 14px', borderRadius:'18px', fontWeight:'600',
      boxShadow:'0 2px 8px rgba(0,0,0,0.2)', cursor:'pointer',
      zIndex:'1080', letterSpacing:'0.2px'
    });

    const reset = ()=>{
      try{
        // Limpiar claves principales usadas en esta secci?n
        localStorage.removeItem('grupo_Médico_assoc');
        localStorage.removeItem('grupo_Médico_assoc:decline');
        localStorage.removeItem('mxmed_cons_schedules');
        localStorage.removeItem('mxmed_cons_schedules2');
        // Preferencias de navegaci?n (para evitar estados atascados)
        localStorage.removeItem('mxmed_menu_group');
        localStorage.removeItem('mxmed_last_panel');
        localStorage.removeItem('mxmed_info_tab');
      }catch(_){ }

      // Restablecer UI de asociaci?n de grupo
      try{
        const resetHorariosCampos = ()=>{
          try{
            document.querySelectorAll('.sched-table tr').forEach(tr=> tr.classList.remove('sched-defined'));
            document.querySelectorAll('input[id^="sch-act-"]').forEach(el=>{
              el.checked = false;
              try{ el.dispatchEvent(new Event('change')); }catch(_){ }
            });
            ['a1','b1','a2','b2'].forEach(slot=>{
              document.querySelectorAll(`input[id^="sch-${slot}-"]`).forEach(inp=>{
                inp.value = '';
                try{
                  inp.dispatchEvent(new Event('input'));
                  inp.dispatchEvent(new Event('change'));
                }catch(_){ }
              });
            });
          }catch(_){ }
        };
        resetHorariosCampos();
        mxResetLogoPreview();
        mxToggleLogoSyncMsg(false);
        mxToggleLogoManualMsg(false);
        const fotoPrev = document.getElementById('cons-foto-prev');
        const fotoImg = document.getElementById('cons-foto-img');
        const fotoInput = document.getElementById('cons-foto');
        const fotoMsg = document.getElementById('cons-foto-sync');
        if(fotoPrev){
          fotoPrev.style.display = 'none';
          fotoPrev.setAttribute('hidden','hidden');
        }
        if(fotoImg){ fotoImg.src = ''; }
        if(fotoInput){ fotoInput.value = ''; }
        if(fotoMsg){ fotoMsg.style.display = 'none'; }
        const rNo = document.getElementById('cons-grupo-no');
        const rSi = document.getElementById('cons-grupo-si');
        const grp = document.getElementById('cons-grupo-nombre');
        if(rNo){ rNo.checked = true; rNo.dispatchEvent(new Event('change')); }
        if(rSi){ rSi.checked = false; }
        if(grp){ grp.value=''; grp.classList.remove('grp-selected'); grp.setAttribute('disabled','disabled'); }
        const tit = document.getElementById('cons-titulo');
        if(tit){
          tit.value = '';
          tit.removeAttribute('data-autofill');
          tit.dispatchEvent(new Event('input'));
          tit.dispatchEvent(new Event('change'));
        }
        // Rehabilitar campos clave de direcci?n
        ['cp','colonia','municipio','estado','cons-calle','cons-numext'].forEach(id=>{
          const el = document.getElementById(id); if(!el) return;
          el.removeAttribute('disabled');
          try{ el.disabled = false; }catch(_){ }
          if(id==='colonia'){ el.classList.remove('select-open'); el.removeAttribute('size'); }
        });
      }catch(_){ }

      // Intentar re-disparar la l?gica de sugerencia si ya hay direcci?n
      try{
        const ev = new Event('change');
        ['cp','colonia','municipio','estado','cons-calle','cons-numext'].forEach(id=> document.getElementById(id)?.dispatchEvent(ev));
      }catch(_){ }
    };

    btn.addEventListener('click', reset);
    document.body.appendChild(btn);
  }catch(_){ }
})();



function mxGetLogoSlot(){
  return document.getElementById('cons-logo-slot');
}
window._mx_logoDropTemplate = window._mx_logoDropTemplate || '';
function mxSetLogoSource(mode){
  const slot = mxGetLogoSlot();
  if(!slot){
    return;
  }
  if(mode){
    slot.dataset.logoSource = mode;
  }else{
    delete slot.dataset.logoSource;
  }
}
function mxGetLogoSource(){
  return mxGetLogoSlot()?.dataset.logoSource || '';
}
function mxToggleLogoSyncMsg(show){
  const msg = document.getElementById('cons-logo-sync');
  if(msg) msg.style.display = show ? 'block' : 'none';
}
function mxToggleLogoManualMsg(show){
  const msg = document.getElementById('cons-logo-manual');
  if(msg) msg.style.display = show ? 'block' : 'none';
}

function mxRebuildLogoDrop(){
  const slot = mxGetLogoSlot();
  if(!slot) return null;
  let tpl = window._mx_logoDropTemplate;
  if(!tpl){
    const existing = slot.querySelector('.logo-slot-drop');
    if(existing) return existing;
    return null;
  }
  const wrapper = document.createElement('div');
  wrapper.innerHTML = tpl.trim();
  const fresh = wrapper.firstElementChild;
  const prev = document.getElementById('cons-logo-prev');
  if(prev){
    slot.insertBefore(fresh, prev);
  }else{
    slot.appendChild(fresh);
  }
  if(typeof window.mxSetupUploadBox === 'function'){
    window.mxSetupUploadBox(fresh);
  }
  return fresh;
}

function mxResetLogoPreview(){
  const prev = document.getElementById('cons-logo-prev');
  const img  = document.getElementById('cons-logo-img');
  const slot = mxGetLogoSlot();
  let drop = slot?.querySelector('.logo-slot-drop');
  if(prev){
    prev.style.display = 'none';
    prev.setAttribute('hidden','hidden');
  }
  if(img){ img.src = ''; }
  if(slot){
    slot.classList.remove('show-preview');
    slot.classList.remove('has-logo');
    delete slot.dataset.logoSource;
  }
  if(drop){
    drop.remove();
    drop = null;
  }
  drop = drop || mxRebuildLogoDrop();
  if(drop){
    drop.removeAttribute('hidden');
    drop.style.removeProperty('display');
  }
  const uploadLogo = document.querySelector('.mf-upload[data-type="logo"]');
  if(uploadLogo){
    uploadLogo.classList.remove('has-logo');
    const ghost = uploadLogo.querySelector('.mf-ghost');
    if(ghost){
      ghost.style.display = '';
      ghost.removeAttribute('aria-hidden');
    }
  }
  const file = document.getElementById('cons-logo');
  if(file){
    file.removeAttribute('disabled');
    file.disabled = false;
    file.value = '';
  }
  mxSetLogoSource('');
}













// Controlar visibilidad de 'Copiar lunes a todos'
(function(){
  function update(){
    const start=document.getElementById('sch-a1-mon');
    const end=document.getElementById('sch-b1-mon');
    const ready=!!((start?.value||'').trim() && (end?.value||'').trim());
    ['sched-copy-mon','sched-copy-mon-2'].forEach(id=>{
      const el=document.getElementById(id);
      if(!el) return;
      el.classList.toggle('d-none', !ready);
    });
  }
  ['input','change'].forEach(evt=>{
    document.addEventListener(evt, e=>{
      if(e.target && (e.target.id==='sch-a1-mon' || e.target.id==='sch-b1-mon')) update();
    }, true);
  });
  update();
})();























