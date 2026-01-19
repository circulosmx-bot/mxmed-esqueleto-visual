// MXMed app bundle
console.info('app.js loaded :: 20251123a');

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



  // Grupo Medico: habilitar/deshabilitar campo segun radios
  (function setupGrupoMedico(){
    const rSi = document.getElementById('cons-grupo-si');
    const rNo = document.getElementById('cons-grupo-no');
    const grp = document.getElementById('cons-grupo-nombre');
    if(!rSi || !rNo || !grp) return;
    const sync = ()=>{
      if(rSi.checked){
        grp.removeAttribute('disabled');
        grp.focus();
      }else{
        grp.setAttribute('disabled','disabled');
      }
    };
    rSi.addEventListener('change', sync);
    rNo.addEventListener('change', sync);
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

// ====== Pacientes: tabs bloqueados hasta capturar nombre y género ======
(function(){
  const pane = document.getElementById('p-expediente');
  if(!pane) return;
  const tabs = Array.from(pane.querySelectorAll('.mm-tabs-row .nav-link'));
  const tabsWrap = pane.querySelector('[data-exp-tabs]');
  if(!tabs.length) return;

  const nameInput = pane.querySelector('[data-pac-nombre]');
  const apellidoPaternoInput = pane.querySelector('[data-pac-apellido-paterno]');
  const apellidoMaternoInput = pane.querySelector('[data-pac-apellido-materno]');
  const genderInputs = Array.from(pane.querySelectorAll('input[name="pac-genero"]'));
  const ginecoItem = pane.querySelector('[data-tab-conditional="gineco"]');
  const ginecoLink = pane.querySelector('[data-tab-key="t-gineco"]');
  const dayError = pane.querySelector('[data-dg-day-error]');
  const genderExtra = pane.querySelector('[data-gen-extra]');
  let lastDayInvalid = false;
  const setGenderAttr = (genero)=>{
    if(genero){ pane.setAttribute('data-exp-gender', genero); }
    else { pane.removeAttribute('data-exp-gender'); }
  };

  const layoutTabs = (showGineco)=>{
    if(!tabsWrap) return;
    const items = Array.from(tabsWrap.querySelectorAll('.nav-item'));
    items.forEach(item=>{
      item.style.flex = '';
      item.style.order = '';
    });
    tabsWrap.classList.toggle('has-gineco', showGineco);
  };

  const toggleTabState = (btn, disabled)=>{
    if(!btn) return;
    btn.classList.toggle('disabled', disabled);
    btn.setAttribute('aria-disabled', disabled ? 'true' : 'false');
    btn.tabIndex = disabled ? -1 : 0;
  };

  const updateGenderExtra = ()=>{
    if(!genderExtra) return;
    const extraInput = genderExtra.querySelector('input');
    const selected = genderInputs.find(inp=>inp.checked);
    if(selected && selected.value==='O'){
      genderExtra.classList.remove('d-none');
      extraInput.removeAttribute('disabled');
      extraInput.focus();
    } else {
      genderExtra.classList.add('d-none');
      extraInput.value = '';
      extraInput.setAttribute('disabled','disabled');
    }
  };

  // Desbloqueo: dejar tabs accesibles (replantear reglas después)
  const basicsReady = ()=> true;

  const showFirstAvailable = ()=>{
    const active = pane.querySelector('.mm-tabs-row .nav-link.active');
    if(active && active.classList.contains('disabled')){
      const first = tabs.find(btn=> !btn.classList.contains('disabled') && btn.closest('.nav-item') && !btn.closest('.nav-item').classList.contains('d-none'));
      if(first){
        try{ new bootstrap.Tab(first).show(); }catch(_){ }
      }
    }
  };

  const computeAge = ()=>{
    const dd = pane.querySelector('[data-dg-dia]');
    const mm = pane.querySelector('[data-dg-mes]');
    const yy = pane.querySelector('[data-dg-anio]');
    const edadLbl = pane.querySelector('[data-dg-edad]');
    const edadOk = pane.querySelector('[data-dg-ok]');
    if(!dd || !mm || !yy || !edadLbl) return;
    const d = Number(dd.value);
    const m = Number(mm.value);
    const y = Number(yy.value);
    const filled = !!(dd.value && mm.value && yy.value);
    const valid = Number.isInteger(d) && Number.isInteger(m) && Number.isInteger(y) && d>=1 && d<=31 && m>=1 && m<=12 && y>=1900;
    const yearField = pane.querySelector('.dg-date-year') || yy.closest('.dg-date-field');
    const daysInMonth = (monthStr, yearStr)=>{
      const mVal = Number(monthStr);
      const yVal = yearStr ? Number(yearStr) : 2001;
      if(!Number.isInteger(mVal) || mVal<1 || mVal>12) return 31;
      return new Date(yVal, mVal, 0).getDate();
    };
    const showDayError = (flag)=>{
      lastDayInvalid = flag;
      if(dayError) dayError.classList.toggle('d-none', !flag);
    };
    const validateDayCombo = ()=>{
      if(!dd || !mm){ showDayError(false); return true; }
      const dayVal = dd.value || '';
      const monthVal = mm.value || '';
      const yearVal = yy?.value || '';
      if(!dayVal || !monthVal){ showDayError(lastDayInvalid); return true; }
      const dNum = Number(dayVal);
      const max = daysInMonth(monthVal, yearVal);
      const okDay = Number.isInteger(dNum) && dNum>=1 && dNum<=max;
      showDayError(!okDay);
      if(!okDay){
        dd.value = '';
        dd.classList.remove('no-caret');
      }
      return okDay;
    };
    const dayValid = validateDayCombo();
    if(dd){
      dd.classList.toggle('no-caret', !!dd.value);
      dd.style.setProperty('--bs-form-select-bg-img','none');
      dd.style.backgroundImage = 'none';
    }
    if(yy){
      yy.classList.toggle('has-value', !!yy.value);
      yy.classList.toggle('no-caret', !!yy.value);
      yy.style.backgroundImage = 'none';
      yy.style.paddingRight = '0.6rem';
    }
    // Barrer spans en campos que no sean año (previene círculos residuales)
    pane.querySelectorAll('.dg-date-field:not(.dg-date-year) span').forEach(el=> el.remove());
    // Limpiar estado visual en campos que no sean Año
    pane.querySelectorAll('.dg-date-field').forEach(field=>{
      if(field !== yearField){
        field.classList.remove('is-valid-date');
      }
    });
    if(!dayValid || !valid){
      edadLbl.textContent = '--';
      if(edadOk) edadOk.style.display = 'none';
      if(yearField) yearField.classList.remove('is-valid-date');
      return;
    }
    const today = new Date();
    const birth = new Date(y, m-1, d);
    let age = today.getFullYear() - birth.getFullYear();
    const hasHadBirthday = (today.getMonth() > birth.getMonth()) || (today.getMonth() === birth.getMonth() && today.getDate() >= birth.getDate());
    if(!hasHadBirthday) age -= 1;
    const ok = age >= 0 && age < 150;
    const showCheck = filled && ok;
    edadLbl.textContent = ok ? (age + ' años') : '--';
    if(edadOk) edadOk.style.display = showCheck ? 'inline-flex' : 'none';
    if(yearField) yearField.classList.toggle('is-valid-date', showCheck);
    // Avisar a cabecera para refrescar edad mostrada
    pane.dispatchEvent(new CustomEvent('pac-age-changed'));
  };

  const normalizeDateChecks = ()=>{
    const yearField = pane.querySelector('.dg-date-year');
    // Eliminar cualquier ícono de check residual
    pane.querySelectorAll('.dg-date-ok').forEach(el=> el.remove());
    if(yearField){
      yearField.querySelectorAll('.material-symbols-outlined, .material-symbols-rounded').forEach(el=> el.remove());
      // Eliminar cualquier nodo adicional distinto al select
      yearField.querySelectorAll(':scope > :not(select)').forEach(el=> el.remove());
      // Eliminar spans de autosave que se agreguen a contenedores padres
      yearField.closest('.save-wrap')?.querySelectorAll('.save-ok')?.forEach(el=> el.remove());
    }
  };

  const bindDOB = ()=>{
    const dd = pane.querySelector('[data-dg-dia]');
    const mm = pane.querySelector('[data-dg-mes]');
    const yy = pane.querySelector('[data-dg-anio]');
    normalizeDateChecks();
    if(dd){
      dd.addEventListener('change', computeAge);
      dd.addEventListener('input', computeAge);
    }
    if(mm){
      mm.addEventListener('change', computeAge);
      mm.addEventListener('input', computeAge);
    }
    if(yy){
      yy.addEventListener('change', computeAge);
      yy.addEventListener('input', computeAge);
    }
    computeAge();
  };

  const syncGineco = (genero, allowNavigate)=>{
    const show = genero === 'F';
    if(ginecoItem){ ginecoItem.classList.toggle('d-none', !show); }
    layoutTabs(show);
    if(!ginecoLink) return;
    if(show && basicsReady()){
      toggleTabState(ginecoLink, false);
    }else{
      toggleTabState(ginecoLink, true);
    }
    if(!show && ginecoLink.classList.contains('active') && allowNavigate){
      const first = tabs[0];
      if(first){ try{ new bootstrap.Tab(first).show(); }catch(_){ } }
    }
  };

  const syncState = (opts={})=>{
    const ready = basicsReady();
    tabs.forEach((btn)=>{
      toggleTabState(btn, false);
    });
    const genero = genderInputs.find(r=>r.checked)?.value || '';
    setGenderAttr(genero);
    syncGineco(genero, opts.allowNavigate);
    updateGenderExtra();
    if(!ready){
      showFirstAvailable();
    }
  };
  nameInput?.addEventListener('input', ()=> syncState({ allowNavigate:true }));
  genderInputs.forEach(r=> r.addEventListener('change', ()=> syncState({ allowNavigate:true })));

  // Refuerzo: asegurar que el click cambie de tab
  const tabLinks = Array.from(document.querySelectorAll('#p-expediente .mm-tabs-row .nav-link'));
  const tabPanes = Array.from(document.querySelectorAll('#p-expediente .tab-content .tab-pane'));
  tabLinks.forEach(btn=>{
    btn.addEventListener('click', (ev)=>{
      const target = btn.getAttribute('data-bs-target');
      if(!target) return;
      ev.preventDefault();
      tabLinks.forEach(b=> b.classList.remove('active'));
      btn.classList.add('active');
      tabPanes.forEach(p=> p.classList.remove('show','active'));
      const paneTarget = pane.querySelector(target);
      if(paneTarget){
        paneTarget.classList.add('show','active');
      }
    });
  });

  syncState();
  layoutTabs(false);
  bindDOB();
})();

// ====== Historia Clinica: registros con chips + modal ======
(function(){
  const pane = document.getElementById('p-expediente');
  if(!pane) return;
  const modalEl = document.getElementById('modalHistItem');
  if(!modalEl) return;

  const titleEl = modalEl.querySelector('[data-hc-title]');
  const yearSel = modalEl.querySelector('[data-hc-year]');
  const detailsInput = modalEl.querySelector('[data-hc-details]');
  const detailsLabel = modalEl.querySelector('[data-hc-details-label]');
  const saveBtn = modalEl.querySelector('[data-hc-save]');
  const deleteBtn = modalEl.querySelector('[data-hc-delete]');

  if(!yearSel || !detailsInput || !saveBtn) return;

  const getLabel = (itemEl)=>{
    const labelEl = itemEl?.querySelector('.hc-chip-head span');
    return (labelEl?.textContent || 'Registro').trim();
  };

  const buildYearOptions = ()=>{
    const now = new Date().getFullYear();
    const opts = [];
    for(let i=0; i<=75; i+=1){
      const y = now - i;
      let label = '';
      if(i === 0){
        label = `${y} (este a\u00f1o)`;
      }else if(i === 1){
        label = `${y} (hace 1 a\u00f1o)`;
      }else if(i === 75){
        label = `${y} o antes (hace 75 a\u00f1os o m\u00e1s)`;
      }else{
        label = `${y} (hace ${i} a\u00f1os)`;
      }
      opts.push({ value: String(y), label });
    }
    return opts;
  };

  const fillYears = ()=>{
    const opts = buildYearOptions();
    yearSel.innerHTML = '<option value=\"\">Selecciona a\u00f1o</option>' + opts.map(o=>`<option value=\"${o.value}\">${o.label}</option>`).join('');
  };

  const makeChipLabel = (year, details)=>{
    const clean = (details || '').trim();
    if(!clean) return year;
    let short = clean;
    if(short.length > 32){
      short = short.slice(0, 32).trim() + '...';
    }
    return `${year} · ${short}`;
  };

  const modal = (window.bootstrap && bootstrap.Modal && bootstrap.Modal.getOrCreateInstance)
    ? bootstrap.Modal.getOrCreateInstance(modalEl)
    : new bootstrap.Modal(modalEl);

  let activeItem = null;
  let activeChip = null;

  const openModal = (itemEl, chipEl)=>{
    activeItem = itemEl;
    activeChip = chipEl || null;
    const label = getLabel(itemEl);
    if(titleEl) titleEl.textContent = (chipEl ? 'Editar ' : 'Agregar ') + label;
    fillYears();

    if(chipEl){
      yearSel.value = chipEl.dataset.year || '';
      detailsInput.value = chipEl.dataset.details || '';
      deleteBtn?.classList.remove('d-none');
    }else{
      yearSel.value = '';
      detailsInput.value = '';
      deleteBtn?.classList.add('d-none');
    }

    const key = itemEl?.getAttribute('data-hc-item') || '';
    const isTransfusion = key === 'transfusiones';
    if(detailsLabel) detailsLabel.textContent = isTransfusion ? 'Motivo' : 'Detalles';
    detailsInput.placeholder = isTransfusion ? 'Motivo' : 'Detalles';

    modal.show();
  };

  pane.querySelectorAll('[data-hc-add]').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      const itemEl = btn.closest('[data-hc-item]');
      if(!itemEl) return;
      const list = itemEl.querySelector('[data-hc-chips]');
      const max = Number(itemEl.getAttribute('data-hc-max') || 10);
      if(list && list.querySelectorAll('.hc-chip').length >= max){
        window.alert('Maximo 10 registros.');
        return;
      }
      openModal(itemEl, null);
    });
  });

  pane.addEventListener('click', (ev)=>{
    const chip = ev.target.closest('.hc-chip');
    if(!chip) return;
    const itemEl = chip.closest('[data-hc-item]');
    if(!itemEl) return;
    openModal(itemEl, chip);
  });

  saveBtn.addEventListener('click', ()=>{
    if(!activeItem) return;
    const year = yearSel.value;
    const details = detailsInput.value.trim();
    if(!year){
      yearSel.focus();
      return;
    }
    const list = activeItem.querySelector('[data-hc-chips]');
    if(!list) return;
    const max = Number(activeItem.getAttribute('data-hc-max') || 10);

    if(activeChip){
      activeChip.dataset.year = year;
      activeChip.dataset.details = details;
      activeChip.textContent = makeChipLabel(year, details);
    }else{
      if(list.querySelectorAll('.hc-chip').length >= max){
        window.alert('Maximo 10 registros.');
        return;
      }
      const chip = document.createElement('button');
      chip.type = 'button';
      chip.className = 'hc-chip';
      chip.dataset.year = year;
      chip.dataset.details = details;
      chip.textContent = makeChipLabel(year, details);
      list.appendChild(chip);
    }
    modal.hide();
  });

  deleteBtn?.addEventListener('click', ()=>{
    if(!activeChip) return;
    const ok = window.confirm('Desea eliminar esta informacion?');
    if(!ok) return;
    activeChip.remove();
    modal.hide();
  });

  modalEl.addEventListener('hidden.bs.modal', ()=>{
    activeItem = null;
    activeChip = null;
  });
})();

// ====== Historia Clinica: vacunas relevantes ======
(function(){
  const pane = document.getElementById('p-expediente');
  if(!pane) return;
  const toggles = Array.from(pane.querySelectorAll('[data-vac-toggle]'));
  if(!toggles.length) return;
  toggles.forEach(chk=>{
    const item = chk.closest('.hc-vac-item');
    const note = item?.querySelector('[data-vac-note]');
    const sync = ()=>{
      if(!note) return;
      if(chk.checked){
        note.removeAttribute('disabled');
      }else{
        note.value = '';
        note.setAttribute('disabled','disabled');
      }
    };
    chk.addEventListener('change', sync);
    sync();
  });
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


// Refuerzo: cierres manuales sin Bootstrap (data-sec-close y collapse)
;(function(){
  const closers = document.querySelectorAll('[data-sec-close]');
  closers.forEach(btn=>{
    btn.addEventListener('click',(ev)=>{
      ev.preventDefault();
      const sel = btn.getAttribute('data-sec-close');
      const panel = sel ? document.querySelector(sel) : btn.closest('.collapse');
      if(!panel) return;
      const inst = window.bootstrap?.Collapse?.getOrCreateInstance(panel);
      if(inst){ inst.hide(); return; }
      panel.classList.remove('show');
      panel.style.display = 'none';
    });
  });
  if(!window.bootstrap?.Collapse){
    document.querySelectorAll('[data-bs-toggle="collapse"][data-bs-target]').forEach(btn=>{
      const sel = btn.getAttribute('data-bs-target');
      const panel = sel ? document.querySelector(sel) : null;
      if(!panel) return;
      btn.addEventListener('click',(ev)=>{
        ev.preventDefault();
        const open = panel.classList.contains('show');
        panel.classList.toggle('show', !open);
        panel.style.display = open ? 'none' : 'block';
      });
    });
  }
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
    const otpInputs = Array.from(panel.querySelectorAll('[data-otp-input]'));
    const submitBtn = panel.querySelector('[data-otp-submit]');
    const resendBtn = panel.querySelector('[data-otp-resend]');
    const countdown = panel.querySelector('[data-otp-countdown]');
    const dismissBtns = panel.querySelectorAll('[data-verify-dismiss]');
    const stepsRoot = panel.querySelector('[data-verify-steps]');
    const stepBlocks = stepsRoot ? stepsRoot.querySelectorAll('[data-step]') : [];
    let timer=null;

    const applyFilled = ()=>{
      otpInputs.forEach(inp=>{
        const has = !!(inp.value||'').trim();
        inp.classList.toggle('filled', has);
      });
    };

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
      otpInputs.forEach(inp=> { inp.value=''; inp.classList.remove('filled','is-valid'); });
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
      const code = otpInputs.map(inp=> (inp.value||'').trim()).join('');
      applyFilled();
      if(submitBtn) submitBtn.disabled = code.length !== otpInputs.length;
    };

    if(summary){
      panel.addEventListener('show.bs.collapse', onShow);
      panel.addEventListener('hidden.bs.collapse', onHidden);
      panel.addEventListener('show.bs.collapse', syncValueFromSummary);
    }

    const focusAt = (pos)=>{
      if(pos < 0 || pos >= otpInputs.length) return;
      otpInputs[pos].focus();
      const len = otpInputs[pos].value.length;
      otpInputs[pos].setSelectionRange(len, len);
    };
    const writeDigits = (startIdx, digits, clearTail=false)=>{
      if(clearTail){
        for(let i=startIdx;i<otpInputs.length;i++){
          otpInputs[i].value = '';
        }
      }
      let pos = startIdx;
      for(const ch of digits){
        if(pos >= otpInputs.length) break;
        otpInputs[pos].value = ch;
        pos++;
      }
      updateOtpState();
      focusAt(Math.min(pos, otpInputs.length-1));
    };

    otpInputs.forEach((inp, idx)=>{
      inp.addEventListener('input', ()=>{
        const digits = (inp.value||'').replace(/[^0-9]/g,'');
        if(!digits){
          inp.value = '';
          inp.classList.remove('filled');
          updateOtpState();
          return;
        }
        if(digits.length > 1){
          writeDigits(idx, digits, true);
          return;
        }
        inp.value = digits;
        updateOtpState();
        if(idx < otpInputs.length-1){
          focusAt(idx+1);
        }
      });

      inp.addEventListener('keydown', (ev)=>{
        if(ev.key === 'Backspace'){
          if(inp.value){
            inp.value = '';
            inp.classList.remove('filled');
            updateOtpState();
          }else if(idx > 0){
            ev.preventDefault();
            otpInputs[idx-1].value = '';
            otpInputs[idx-1].classList.remove('filled');
            updateOtpState();
            focusAt(idx-1);
          }
        }else if(ev.key === 'ArrowLeft' && idx > 0){
          ev.preventDefault();
          focusAt(idx-1);
        }else if(ev.key === 'ArrowRight' && idx < otpInputs.length-1){
          ev.preventDefault();
          focusAt(idx+1);
        }
      });

      inp.addEventListener('paste', (ev)=>{
        const clip = ev.clipboardData?.getData('text') || '';
        const digits = (clip||'').replace(/\D/g,'');
        if(!digits) return;
        ev.preventDefault();
        writeDigits(idx, digits, true);
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

// ===== Sugerencia de Grupo Medico y sincronizacion de logotipo (demo) =====

(function setupGrupoMedicoSuggest(){

  const keyAssoc = 'grupo_MÃƒÂ©dico_assoc';



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

    // Mostrar siempre (antes solo en localhost); se puede desactivar con window.mxHideResetWidget = true;
    if(window.mxHideResetWidget) return;

    // Crear botón flotante solo una vez
    if(document.getElementById('mx-dev-reset')) return;

    const btn = document.createElement('button');
    btn.id = 'mx-dev-reset';
    btn.type = 'button';
    btn.textContent = 'Restablecer';
    btn.setAttribute('aria-label','Restablecer estado');
    Object.assign(btn.style, {
      position:'fixed', top:'16px', bottom:'auto', right:'16px',
      background:'#d81b60', color:'#fff', border:'none',
      padding:'8px 14px', borderRadius:'18px', fontWeight:'600',
      boxShadow:'0 2px 8px rgba(0,0,0,0.2)', cursor:'pointer',
      zIndex:'3000', letterSpacing:'0.2px',
      pointerEvents:'auto'
    });



    const reset = ()=>{

      try{

        // Limpiar claves principales usadas en esta secci?n

        localStorage.removeItem('grupo_MÃƒÂ©dico_assoc');

        localStorage.removeItem('grupo_MÃƒÂ©dico_assoc:decline');

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

      // Reset generico de campos visibles (aplica en cualquier seccion)
      try{
        const trigger = (el, evt)=>{ try{ el.dispatchEvent(new Event(evt, {bubbles:true})); }catch(_){ } };
        const resetOne = (el)=>{
          const tag = (el.tagName||'').toLowerCase();
          const type = (el.getAttribute('type')||'').toLowerCase();
          if(type === 'checkbox' || type === 'radio'){
            el.checked = !!el.defaultChecked;
          }else if(type === 'file'){
            el.value = '';
          }else if(tag === 'select'){
            const defIndex = Array.from(el.options||[]).findIndex(o=> o.defaultSelected);
            el.selectedIndex = defIndex >=0 ? defIndex : 0;
          }else{
            el.value = el.defaultValue || '';
          }
          el.classList.remove('filled','is-valid','is-invalid','was-validated');
          trigger(el,'input');
          trigger(el,'change');
        };
        document.querySelectorAll('input, select, textarea').forEach(resetOne);
        document.querySelectorAll('[data-otp-input]').forEach(inp=> inp.classList.remove('filled','is-valid'));
        document.querySelectorAll('.chip-list').forEach(list=> list.innerHTML='');
        document.querySelectorAll('.save-ok').forEach(ok=> ok.style.display='none');
      }catch(_){ }
      // Reset Seguridad 2FA (UI)
      try{
        const panel = document.getElementById('seg-2fa-panel');
        if(panel){
          panel.classList.remove('show');
          panel.style.display = 'none';
          panel.setAttribute('aria-hidden','true');
        }
        document.querySelectorAll('[data-bs-target=\"#seg-2fa-panel\"]').forEach(btn=>{
          btn.setAttribute('aria-expanded','false');
        });
        const summary = document.querySelector('[data-twofa-summary]');
        if(summary){
          const badge = summary.querySelector('[data-twofa-status]');
          const lbl = summary.querySelector('[data-twofa-method-label]');
          const btnAct = summary.querySelector('[data-twofa-activate]');
          const btnChg = summary.querySelector('[data-twofa-change]');
          const btnOff = summary.querySelector('[data-twofa-disable]');
          if(badge){
            badge.textContent = '2FA inactivo';
            badge.classList.remove('bg-success');
            badge.classList.add('bg-secondary');
          }
          if(lbl) lbl.textContent = 'Selecciona un método para activarlo.';
          if(btnAct) btnAct.classList.remove('d-none');
          if(btnChg) btnChg.classList.add('d-none');
          if(btnOff) btnOff.classList.add('d-none');
        }
        // radio a app y panes
        const radios = document.querySelectorAll('input[name=\"twofa-method\"]');
        radios.forEach(r=> r.checked = r.getAttribute('data-twofa-method') === 'app');
        const panes = document.querySelectorAll('.twofa-pane');
        panes.forEach(p=> p.classList.toggle('d-none', p.getAttribute('data-twofa-view') !== 'app'));
        // limpiar OTPs y botones
        document.querySelectorAll('[data-otp-group]').forEach(g=>{
          g.querySelectorAll('[data-otp-input]').forEach(inp=>{
            inp.value = '';
            inp.classList.remove('filled','is-valid');
          });
        });
        [
          '[data-twofa-confirm-app]',
          '[data-twofa-confirm-sms]',
          '[data-twofa-confirm-wa]',
          '[data-twofa-confirm-call]'
        ].forEach(sel=>{
          const b = document.querySelector(sel);
          if(!b) return;
          b.textContent = 'Confirmar 2FA';
          b.classList.remove('btn-success');
          b.classList.add('btn-primary');
          b.disabled = true;
        });
        const backups = document.querySelector('[data-twofa-backups]');
        if(backups) backups.innerHTML = '';
      }catch(_){ }

      // Reset Estudios Diagnóstico (catálogo/órdenes)
      try{
        if(typeof window.mxResetEstudios === 'function'){
          window.mxResetEstudios();
        }
      }catch(_){ }

    };



    btn.addEventListener('click', reset);

    const mount = ()=>{
      if(document.getElementById('mx-dev-reset')) return;
      const target = document.body || document.documentElement;
      if(!target) return;
      target.appendChild(btn);
    };

    if(document.readyState === 'loading'){
      document.addEventListener('DOMContentLoaded', mount, {once:true});
    }else{
      mount();
    }

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

// ===== OTP UX helper (auto-advance & paste) =====
(function () {
  var groups = document.querySelectorAll('[data-otp-group]');
  if (!groups.length) return;

  function setupGroup(group) {
    var boxes = Array.prototype.slice.call(group.querySelectorAll('[data-otp-input]'));
    if (!boxes.length) return;

    function updateFilled() {
      boxes.forEach(function (box) {
        var has = !!(box.value || '').trim();
        if (has) {
          box.classList.add('filled');
        } else {
          box.classList.remove('filled');
        }
      });
    }
    function focusAt(pos){
      if(pos < 0 || pos >= boxes.length) return;
      var target = boxes[pos];
      target.focus();
      target.setSelectionRange(target.value.length, target.value.length);
    }
    function writeDigits(startIdx, digits, clearTail){
      if(clearTail){
        for(var t=startIdx; t<boxes.length; t+=1){
          boxes[t].value = '';
        }
      }
      var pos = startIdx;
      for(var i=0; i<digits.length && pos<boxes.length; i+=1, pos+=1){
        boxes[pos].value = digits.charAt(i);
      }
      updateFilled();
      focusAt(Math.min(pos, boxes.length - 1));
    }

    boxes.forEach(function (box, index) {
      if (box.__otpBound) return;
      box.__otpBound = true;

      box.addEventListener('input', function () {
        var digits = (box.value || '').replace(/[^0-9]/g, '');
        if(!digits){
          box.value = '';
          updateFilled();
          return;
        }
        if(digits.length > 1){
          writeDigits(index, digits, true);
          return;
        }
        box.value = digits;
        updateFilled();
        if (digits && index < boxes.length - 1) {
          focusAt(index + 1);
        }
      });

      box.addEventListener('keydown', function (ev) {
        if (ev.key === 'Backspace') {
          if (box.value) {
            box.value = '';
            updateFilled();
          } else if (index > 0) {
            ev.preventDefault();
            var prev = boxes[index - 1];
            prev.value = '';
            updateFilled();
            focusAt(index - 1);
          }
          return;
        }

        if (ev.key === 'ArrowLeft' && index > 0) {
          ev.preventDefault();
          focusAt(index - 1);
        } else if (ev.key === 'ArrowRight' && index < boxes.length - 1) {
          ev.preventDefault();
          focusAt(index + 1);
        }
      });

      box.addEventListener('paste', function (ev) {
        var clipboard = ev.clipboardData || window.clipboardData;
        if (!clipboard) return;
        var text = clipboard.getData('text') || '';
        var digits = text.replace(/\D/g, '');
        if (!digits) return;
        ev.preventDefault();
        writeDigits(index, digits, true);
      });
    });

    updateFilled();
  }

  groups.forEach(setupGroup);
})();































// ===== Seguridad: Verificación en dos pasos (2FA) =====
(function(){
  function initTwofa(){
    const panel = document.getElementById('seg-2fa-panel');
    if(!panel || panel.__twofaInit) return;
    panel.__twofaInit = true;

    const methodInputs = panel.querySelectorAll('input[name="twofa-method"]');
    const panes = panel.querySelectorAll('.twofa-pane');
    const secretEl = panel.querySelector('[data-twofa-secret]');
    const qrEl = panel.querySelector('[data-twofa-qr]');
    const backupsEl = panel.querySelector('[data-twofa-backups]');
    const generateBtn = panel.querySelector('[data-twofa-generate]');
    const otpApp = panel.querySelector('[data-twofa-otp-app]');
    const otpSms = panel.querySelector('[data-twofa-otp-sms]');
    const otpWa = panel.querySelector('[data-twofa-otp-wa]');
    const otpCall = panel.querySelector('[data-twofa-otp-call]');
    const confirmAppBtn = panel.querySelector('[data-twofa-confirm-app]');
    const confirmSmsBtn = panel.querySelector('[data-twofa-confirm-sms]');
    const confirmWaBtn = panel.querySelector('[data-twofa-confirm-wa]');
    const confirmCallBtn = panel.querySelector('[data-twofa-confirm-call]');
    const phoneInput = panel.querySelector('[data-twofa-phone]');
    const phoneWa = panel.querySelector('[data-twofa-phone-wa]');
    const phoneCall = panel.querySelector('[data-twofa-phone-call]');
    const sendBtn = panel.querySelector('[data-twofa-send]');
    const sendBtnWa = panel.querySelector('[data-twofa-send-wa]');
    const sendBtnCall = panel.querySelector('[data-twofa-send-call]');
    const sentMsg = panel.querySelector('[data-twofa-sent]');
    const sentMsgWa = panel.querySelector('[data-twofa-sent-wa]');
    const sentMsgCall = panel.querySelector('[data-twofa-sent-call]');
    const triggers = document.querySelectorAll('[data-bs-target="#seg-2fa-panel"]');
    const summary = document.querySelector('[data-twofa-summary]');
    const statusBadge = summary?.querySelector('[data-twofa-status]');
    const methodLabel = summary?.querySelector('[data-twofa-method-label]');
    const btnActivate = summary?.querySelector('[data-twofa-activate]');
    const btnChange = summary?.querySelector('[data-twofa-change]');
    const btnDisable = summary?.querySelector('[data-twofa-disable]');
    let backups = [];
    let twofaActive = false;
    let currentMethod = 'app';
    const methodName = { app:'App autenticadora', sms:'SMS', whatsapp:'WhatsApp', call:'Llamada' };
    const getSelectedMethod = ()=>{
      const checked = Array.from(methodInputs).find(r=> r.checked);
      return checked?.getAttribute('data-twofa-method') || 'app';
    };
        const updateSummary = ()=>{
      if(!summary) return;
      const isOn = !!twofaActive;
      if(statusBadge){
        statusBadge.textContent = isOn ? '2FA activo' : '2FA inactivo';
        statusBadge.classList.toggle('bg-success', isOn);
        statusBadge.classList.toggle('bg-secondary', !isOn);
      }
      if(methodLabel){
        const pretty = methodName[currentMethod] || currentMethod;
        methodLabel.textContent = isOn ? "Metodo: " + pretty : "Selecciona un metodo para activarlo.";
      }
      if(btnActivate) btnActivate.classList.toggle('d-none', isOn);
      if(btnChange) btnChange.classList.toggle('d-none', !isOn);
      if(btnDisable) btnDisable.classList.toggle('d-none', !isOn);
    };

    const randGroup = (len=4)=>{
      const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
      let out = '';
      for(let i=0;i<len;i+=1){
        out += chars.charAt(Math.floor(Math.random()*chars.length));
      }
      return out;
    };
    const newSecret = ()=> `${randGroup()}-${randGroup()}-${randGroup()}`;
    const generateBackups = (n=6)=> Array.from({length:n}, ()=> `${randGroup(4)}-${randGroup(4)}`);

    const renderBackups = ()=>{
      backups = generateBackups();
      if(!backupsEl) return;
      backupsEl.innerHTML = backups.map(code=>`<span class="badge bg-light text-muted border">${code}</span>`).join(' ');
    };

    const setSecret = ()=>{
      const secret = newSecret();
      if(secretEl) secretEl.textContent = secret;
      if(qrEl) qrEl.textContent = 'QR';
    };

    const setMethod = (method)=>{
      const target = method || 'app';
      methodInputs.forEach(r=>{ r.checked = (r.getAttribute('data-twofa-method') === target); });
      panes.forEach(p=>{
        p.classList.toggle('d-none', p.getAttribute('data-twofa-view') !== target);
      });
      const focusOtp = (group)=>{
        const first = group?.querySelector('[data-otp-input]');
        if(first) first.focus();
      };
      if(target === 'app'){
        focusOtp(otpApp);
      }else if(target === 'sms'){
        phoneInput?.focus();
      }else if(target === 'whatsapp'){
        phoneWa?.focus();
      }else if(target === 'call'){
        phoneCall?.focus();
      }
    };

    const getDigits = (group)=>{
      if(!group) return '';
      const boxes = group.querySelectorAll('[data-otp-input]');
      return Array.from(boxes).map(b=> (b.value||'').replace(/\D/g,'')).join('').slice(0,6);
    };

    const syncButtons = ()=>{
      if(confirmAppBtn) confirmAppBtn.disabled = getDigits(otpApp).length !== 6;
      if(confirmSmsBtn) confirmSmsBtn.disabled = getDigits(otpSms).length !== 6;
      if(confirmWaBtn) confirmWaBtn.disabled = getDigits(otpWa).length !== 6;
      if(confirmCallBtn) confirmCallBtn.disabled = getDigits(otpCall).length !== 6;
    };

    [otpApp, otpSms, otpWa, otpCall].forEach(group=>{
      if(!group) return;
      group.addEventListener('input', ()=> setTimeout(syncButtons, 0));
      group.addEventListener('paste', ()=> setTimeout(syncButtons, 30));
    });

    methodInputs.forEach(radio=>{
      radio.addEventListener('change', ()=>{
        const method = radio.getAttribute('data-twofa-method') || 'app';
        setMethod(method);
      });
    });

    generateBtn?.addEventListener('click', (ev)=>{
      ev.preventDefault();
      setSecret();
      renderBackups();
    });

    const bindSender = (btn, phone, msg)=>{
      if(!btn || !phone) return;
      btn.addEventListener('click', (ev)=>{
        ev.preventDefault();
        const val = (phone.value || '').trim();
        if(!val){
          phone.focus();
          return;
        }
        msg?.classList.remove('d-none');
        msg?.classList.add('text-success');
        if(btn.classList.contains('disabled')) return;
        btn.classList.add('disabled');
        btn.setAttribute('aria-disabled','true');
        setTimeout(()=> btn.classList.remove('disabled'), 5000);
      });
    };

    bindSender(sendBtn, phoneInput, sentMsg);
    bindSender(sendBtnWa, phoneWa, sentMsgWa);
    bindSender(sendBtnCall, phoneCall, sentMsgCall);

    const markConfirmed = (btn)=>{
      if(!btn) return;
      btn.textContent = 'Confirmado';
      btn.classList.remove('btn-primary');
      btn.classList.add('btn-success');
      btn.disabled = true;
    };

    confirmAppBtn?.addEventListener('click', (ev)=>{
      ev.preventDefault();
      if(getDigits(otpApp).length !== 6) return;
      markConfirmed(confirmAppBtn);
    });

    confirmAppBtn?.addEventListener('click', (ev)=>{
      ev.preventDefault();
      if(getDigits(otpApp).length !== 6) return;
      markConfirmed(confirmAppBtn);
      currentMethod = getSelectedMethod();
      twofaActive = true;
      updateSummary();
      closePanel();
    });

    confirmSmsBtn?.addEventListener('click', (ev)=>{
      ev.preventDefault();
      if(getDigits(otpSms).length !== 6) return;
      markConfirmed(confirmSmsBtn);
      currentMethod = getSelectedMethod();
      twofaActive = true;
      updateSummary();
      closePanel();
    });

    confirmWaBtn?.addEventListener('click', (ev)=>{
      ev.preventDefault();
      if(getDigits(otpWa).length !== 6) return;
      markConfirmed(confirmWaBtn);
      currentMethod = getSelectedMethod();
      twofaActive = true;
      updateSummary();
      closePanel();
    });

    confirmCallBtn?.addEventListener('click', (ev)=>{
      ev.preventDefault();
      if(getDigits(otpCall).length !== 6) return;
      markConfirmed(confirmCallBtn);
      currentMethod = getSelectedMethod();
      twofaActive = true;
      updateSummary();
      closePanel();
    });

    const openPanel = ()=>{
      panel.classList.add('show');
      panel.style.display = 'block';
      panel.removeAttribute('aria-hidden');
      triggers.forEach(btn=> btn.setAttribute('aria-expanded','true'));
      setMethod(twofaActive ? currentMethod : 'app');
      syncButtons();
    };
    const closePanel = ()=>{
      panel.classList.remove('show');
      panel.style.display = 'none';
      panel.setAttribute('aria-hidden','true');
      triggers.forEach(btn=> btn.setAttribute('aria-expanded','false'));
    };

    triggers.forEach(btn=>{
      btn.addEventListener('click', (ev)=>{
        ev.preventDefault();
        openPanel();
      });
    });

    document.addEventListener('click', (ev)=>{
      const btn = ev.target.closest('[data-bs-target="#seg-2fa-panel"]');
      if(!btn) return;
      ev.preventDefault();
      openPanel();
    });

    panel.querySelectorAll('[data-sec-close]').forEach(btn=>{
      btn.addEventListener('click', (ev)=>{
        ev.preventDefault();
        closePanel();
      });
    });

    btnActivate?.addEventListener('click', (ev)=>{
      ev.preventDefault();
      currentMethod = 'app';
      setMethod('app');
      openPanel();
    });

    btnChange?.addEventListener('click', (ev)=>{
      ev.preventDefault();
      setMethod(currentMethod || 'app');
      openPanel();
    });

    btnDisable?.addEventListener('click', (ev)=>{
      ev.preventDefault();
      twofaActive = false;
      updateSummary();
      closePanel();
    });

    // init defaults
    setSecret();
    renderBackups();
    setMethod('app');
    syncButtons();
    updateSummary();
  }

  if(document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', initTwofa);
  }else{
    initTwofa();
  }
})();


// ==== Biometría remota via QR + control de sesiones (mock UI listo para backend) ====
(function initBiometricAccess(){
  const container = document.querySelector('#p-seguridad');
  const panel = document.querySelector('#bio-panel');
  const startBtn = container?.querySelector('[data-bio-start]');
  const badge = container?.querySelector('[data-bio-badge]');
  const last = container?.querySelector('[data-bio-last]');
  const markTrustedBtn = container?.querySelector('[data-bio-mark-trusted]');
  if(!container || !panel || !startBtn) return;

  const qrBox = panel.querySelector('[data-bio-qr]');
  const sessionEl = panel.querySelector('[data-bio-session]');
  const countdownEl = panel.querySelector('[data-bio-countdown]');
  const statusEl = panel.querySelector('[data-bio-status]');
  const refreshBtn = panel.querySelector('[data-bio-refresh]');
  const trustAsk = panel.querySelector('[data-bio-trustask]');
  const trustCheck = panel.querySelector('#bio-trust-check');
  const trustSave = panel.querySelector('[data-bio-save-trust]');
  let timer = null;
  let secs = 0;
  let trusted = false;

  const setBadge = (active)=>{
    if(!badge) return;
    badge.classList.toggle('bg-success', active);
    badge.classList.toggle('bg-secondary', !active);
    badge.textContent = active ? 'Activo' : 'Inactivo';
  };

  const setStatus = (txt, cls)=>{
    if(!statusEl) return;
    statusEl.textContent = txt;
    statusEl.classList.remove('success','error');
    if(cls) statusEl.classList.add(cls);
  };

  const formatCountdown = ()=>{
    const m = String(Math.floor(secs/60)).padStart(2,'0');
    const s = String(secs%60).padStart(2,'0');
    return `${m}:${s}`;
  };

  const updateCountdown = ()=>{
    if(countdownEl) countdownEl.textContent = formatCountdown();
  };

  const randomSession = ()=> 'BIO-' + Math.random().toString(36).substring(2,7).toUpperCase();

  const renderQR = (id)=>{
    if(qrBox){
      qrBox.textContent = id;
      qrBox.setAttribute('aria-label', `Código para sesión ${id}`);
    }
  };

  const stopTimer = ()=>{
    if(timer) clearInterval(timer);
    timer = null;
  };

  const openPanel = ()=>{
    panel.classList.add('show');
    panel.style.display = 'block';
    panel.removeAttribute('aria-hidden');
  };

  const closePanel = ()=>{
    stopTimer();
    panel.classList.remove('show');
    panel.style.display = 'none';
    panel.setAttribute('aria-hidden','true');
    trustAsk?.classList.add('d-none');
  };

  const simulateApproval = ()=>{
    // Simula confirmación desde el móvil tras 8s si no expiró
    setTimeout(()=>{
      if(secs <= 0) return;
      setStatus('Autenticación confirmada desde tu móvil.', 'success');
      setBadge(true);
      if(last) last.textContent = 'Biometría activa en este equipo.';
      trustAsk?.classList.remove('d-none');
      markTrustedBtn?.removeAttribute('disabled');
      if(trustSave) trustSave.disabled = !(trustCheck?.checked);
    }, 8000);
  };

  const startSession = ()=>{
    stopTimer();
    const sid = randomSession();
    if(sessionEl) sessionEl.textContent = sid;
    renderQR(sid);
    secs = 120;
    updateCountdown();
    setStatus('Esperando autenticación biométrica…');
    timer = setInterval(()=>{
      secs -= 1;
      updateCountdown();
      if(secs <= 0){
        setStatus('El código expiró. Genera uno nuevo.', 'error');
        stopTimer();
      }
    }, 1000);
    simulateApproval();
  };

  startBtn.addEventListener('click', (ev)=>{
    ev.preventDefault();
    openPanel();
    startSession();
  });

  refreshBtn?.addEventListener('click', (ev)=>{
    ev.preventDefault();
    startSession();
  });

  panel.querySelectorAll('[data-bio-close]').forEach(btn=>{
    btn.addEventListener('click', (ev)=>{
      ev.preventDefault();
      closePanel();
    });
  });

  trustCheck?.addEventListener('change', ()=>{
    if(trustSave) trustSave.disabled = !trustCheck.checked;
  });

  trustSave?.addEventListener('click', (ev)=>{
    ev.preventDefault();
    if(!trustCheck?.checked) return;
    trusted = true;
    setStatus('Equipo marcado como confiable por 21 días.', 'success');
    markTrustedBtn?.setAttribute('disabled','disabled');
  });

  markTrustedBtn?.addEventListener('click', (ev)=>{
    ev.preventDefault();
    openPanel();
    startSession();
    trustAsk?.classList.remove('d-none');
    if(trustSave) trustSave.disabled = !trustCheck?.checked;
  });
})();

(function initSessionPanel(){
  const tbody = document.querySelector('[data-sessions-body]');
  if(!tbody) return;
  const refreshBtn = document.querySelector('[data-session-refresh]');
  const closeOthersBtn = document.querySelector('[data-session-close-others]');

  let sessions = [
    {id:'current', device:'Windows • Chrome', location:'Aguascalientes, MX', last:'Ahora', trusted:true, current:true},
    {id:'ios-01', device:'iPhone • Safari', location:'CDMX, MX', last:'Hace 2 h', trusted:true, current:false},
    {id:'and-02', device:'Android • App', location:'Zapopan, MX', last:'Ayer', trusted:false, current:false}
  ];

  const render = ()=>{
    tbody.innerHTML = '';
    sessions.forEach(s=>{
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${s.device}${s.current ? ' <span class=\"badge bg-info text-dark\">Este equipo</span>' : ''}</td>
        <td>${s.location}</td>
        <td>${s.last}</td>
        <td>${s.trusted ? '<span class=\"badge bg-success\">Sí</span>' : '<span class=\"badge bg-secondary\">No</span>'}</td>
        <td class=\"text-end\">
          ${s.current ? '<span class=\"text-muted small\">Activa</span>' : '<button class=\"btn btn-link btn-sm p-0\" data-session-close=\"'+s.id+'\">Cerrar sesión</button>'}
        </td>
      `;
      tbody.appendChild(tr);
    });
  };

  const closeSession = (id)=>{
    sessions = sessions.filter(s=> s.id !== id || s.current);
    render();
  };

  tbody.addEventListener('click', (ev)=>{
    const btn = ev.target.closest('[data-session-close]');
    if(!btn) return;
    ev.preventDefault();
    closeSession(btn.getAttribute('data-session-close'));
  });

  refreshBtn?.addEventListener('click', (ev)=>{
    ev.preventDefault();
    render();
  });

  closeOthersBtn?.addEventListener('click', (ev)=>{
    ev.preventDefault();
    sessions = sessions.filter(s=> s.current);
    render();
  });

  render();
})();
























// ================== SUSCRIPCIÓN (maqueta) ==================
(()=>{
  const pane = document.getElementById('p-suscripcion');
  if(!pane) return;

  const els = {
    planName: pane.querySelector('[data-subp-current-name]'),
    status: pane.querySelector('[data-subp-current-status]'),
    since: pane.querySelector('[data-subp-since]'),
    until: pane.querySelector('[data-subp-until]'),
    autorenew: pane.querySelector('#subp-autorenew'),
    renewBtn: pane.querySelector('[data-subp-renew]'),
    currentTitle: pane.querySelector('[data-subp-current-title]'),
    currentNote: pane.querySelector('[data-subp-current-note]'),
    currentFeat: pane.querySelector('[data-subp-current-features]'),
    currentAlert: pane.querySelector('[data-subp-current-alert]'),
    nextBill: pane.querySelector('[data-subp-next-bill]'),
    renewCTA: pane.querySelector('[data-subp-renew-cta]'),
    catalog: pane.querySelector('[data-subp-catalog]'),
    couponInput: pane.querySelector('[data-subp-coupon-input]'),
    couponApply: pane.querySelector('[data-subp-coupon-apply]'),
    couponMsg: pane.querySelector('[data-subp-coupon-msg]'),
    invoiceBtn: pane.querySelector('[data-subp-invoice]'),
    invoiceHint: pane.querySelector('[data-subp-invoice-hint]'),
    historyBody: pane.querySelector('[data-subp-history]'),
    historyRefresh: pane.querySelector('[data-subp-history-refresh]'),
    billingRadios: pane.querySelectorAll('input[name="subp-billing"]')
  };

  const data = {
    billing: 'monthly',
    current: {
      id: 'optimo',
      name: 'Óptimo',
      since: '22 Dic 2024',
      until: '22 Dic 2025',
      status: 'Activo',
      nextBill: '22 Dic 2025',
      autorenew: true,
      note: 'Tu perfil está en línea / tienes acceso a:',
      alert: 'Tu anualidad vence el 22 Diciembre 2025 · RENUEVA AHORA',
      features: ['Perfil en línea','Agenda','Expediente','Recetas']
    },
    plans: [
      { id:'pro', name:'Profesional', monthly:0, yearly:0, features:['Perfil en línea','Agenda','Expediente','Recetas','Asistente IA'] },
      { id:'optimo', name:'Óptimo', monthly:0, yearly:0, features:['Perfil en línea','Agenda','Expediente','Recetas'] },
      { id:'estandar', name:'Estándar', monthly:0, yearly:0, features:['Perfil en línea','Agenda'] },
      { id:'basico', name:'Básico', monthly:0, yearly:0, features:['Perfil en línea'] }
    ],
    history: [
      {fecha:'2025-06-01', plan:'Óptimo', mov:'Renovación', vig:'22 Dic 2025', est:'Activo', apoyo:'—'},
      {fecha:'2024-12-22', plan:'Estándar', mov:'Upgrade', vig:'22 Dic 2024', est:'Activo', apoyo:'—'}
    ]
  };

  function fmtMoney(n){
    return `$${n.toLocaleString('es-MX')} MXN`;
  }

  function renderCurrent(){
    if(els.planName){
      els.planName.textContent = data.current.name;
      els.planName.setAttribute('data-plan', data.current.id || '');
    }
    if(els.status) els.status.textContent = data.current.status;
    if(els.since) els.since.textContent = data.current.since;
    if(els.until) els.until.textContent = data.current.until;
    if(els.autorenew) els.autorenew.checked = !!data.current.autorenew;
    if(els.currentTitle) els.currentTitle.textContent = `${data.current.name} · Tu plan actual`;
    if(els.currentNote) els.currentNote.textContent = data.current.note || '';
    if(els.nextBill) els.nextBill.textContent = data.current.nextBill || '—';
    if(els.currentFeat){
      els.currentFeat.innerHTML = data.current.features.map(f=>`<li class="subp-feature"><span class="material-symbols-rounded mat-ico" aria-hidden="true">check</span><span>${f}</span></li>`).join('');
    }
    if(els.currentAlert){
      if(data.current.alert){
        els.currentAlert.textContent = data.current.alert;
        els.currentAlert.classList.remove('d-none');
      } else {
        els.currentAlert.classList.add('d-none');
      }
    }
  }

  function renderHistory(){
    if(!els.historyBody) return;
    const today = new Date();
    const ymNow = `${today.getFullYear()}-${String(today.getMonth()+1).padStart(2,'0')}`;
    const hist = [...data.history];
    // Simular una línea del mes en curso con opción de facturar
    if(!hist.some(h => (h.fecha || '').startsWith(ymNow))){
      const iso = today.toISOString().slice(0,10);
      hist.unshift({
        fecha: iso,
        plan: data.current.name || '—',
        mov: 'Renovación',
        vig: data.current.until || iso,
        est: 'Pagado',
        apoyo: '—'
      });
    }
    els.historyBody.innerHTML = hist.map(h=>{
      const isCurrentMonth = (h.fecha || '').startsWith(ymNow);
      const facturaBtn = isCurrentMonth ? `<button class="btn btn-primary btn-sm" type="button">Solicitar factura</button>` : '';
      return `<tr><td>${h.fecha}</td><td>${h.plan}</td><td>${h.mov}</td><td>${h.vig}</td><td>${h.est}</td><td>${h.apoyo}</td><td>${facturaBtn}</td></tr>`;
    }).join('');
  }

  function renderCatalog(){
    if(!els.catalog) return;
    const yearly = data.billing === 'yearly';
    els.catalog.innerHTML = data.plans.map(p=>{
      const price = yearly ? p.yearly : p.monthly;
      const save = yearly ? (p.monthly*12 - p.yearly) : 0;
      const isCurrent = p.id === data.current.id;
      return `<div class="subp-plan ${isCurrent?'current':''}" data-plan="${p.id}">
        ${isCurrent?'<div class="subp-plan-badge">Plan actual</div>':''}
        <div class="subp-plan-title">${p.name}</div>
        <div class="subp-price">${fmtMoney(price)} <small>${yearly?'/ año':'/ mes'}</small></div>
        <div class="mt-2">${p.features.map(f=>`<div class="subp-feature"><span class="material-symbols-rounded mat-ico" aria-hidden="true">check</span><span>${f}</span></div>`).join('')}</div>
        ${save>0?`<div class="subp-save">Ahorra $${save.toLocaleString('es-MX')} al contratar anual</div>`:''}
        <button class="btn ${isCurrent?'btn-outline-primary':'btn-primary'} subp-btn" type="button" data-subp-select="${p.id}">${isCurrent?'Renovar':'Seleccionar'}</button>
      </div>`;
    }).join('');
    els.catalog.querySelectorAll('[data-subp-select]').forEach(btn=>{
      btn.addEventListener('click',()=>{
        const id = btn.getAttribute('data-subp-select');
        console.log('Seleccionar plan', id);
      });
    });
  }

  // Eventos
  els.billingRadios.forEach(r=>{
    r.addEventListener('change', ()=>{
      data.billing = r.value === 'yearly' ? 'yearly' : 'monthly';
      renderCatalog();
    });
  });
  els.renewBtn?.addEventListener('click', ()=>console.log('Renovar plan actual'));
  els.renewCTA?.addEventListener('click', ()=>console.log('Renovar plan actual'));
  els.couponApply?.addEventListener('click', ()=>{
    const code = (els.couponInput?.value||'').trim();
    els.couponMsg.textContent = code ? `Cupón "${code}" aplicado (demo)` : 'Sin cupón aplicado';
  });
  els.invoiceBtn?.addEventListener('click', ()=>{
    els.invoiceHint.textContent = 'Solicitud de factura registrada (demo)';
  });
  els.historyRefresh?.addEventListener('click', ()=>{
    renderHistory();
  });

  renderCurrent();
  renderCatalog();
  renderHistory();
})();

// ====== Estudios: modal catálogo laboratorio ======
(function(){
  if(!window.bootstrap) return;

  const PICK_MAP_LAB = {
    'Biometría hemática (BH / CBC)': ['Biometría hemática (BH / CBC)'],
    'EGO (examen general de orina)': ['EGO (examen general de orina)'],
    'HbA1c': ['HbA1c'],
    'PCR ultrasensible': ['PCR ultrasensible']
  };
  const PACK_MAP_LAB = {
    'QS (Glucosa, Urea, Creatinina)': ['Glucosa','Urea','Creatinina'],
    'Perfil lipídico': ['Colesterol total','HDL','LDL','Triglicéridos','Colesterol no-HDL'],
    'Perfil hepático': ['AST (TGO)','ALT (TGP)','Fosfatasa alcalina (ALP)','GGT','Bilirrubina total','Albúmina','Proteínas totales'],
    'Perfil tiroideo': ['TSH','T4 libre'],
    'Control de diabetes': ['Glucosa','HbA1c','EGO (examen general de orina)','Microalbuminuria'],
    'ETS básico': ['VIH Ag/Ac','VDRL / RPR','HBsAg','VHC (anticuerpos)'],
    'Preop básico': ['Biometría hemática (BH / CBC)','TP / INR','TTPa (aPTT)','Glucosa','Creatinina'],
    'Preop gineco': ['Biometría hemática (BH / CBC)','TP / INR','TTPa (aPTT)','Glucosa','Creatinina','β-hCG','EGO (examen general de orina)'],
    'Preop ortopedia': ['Biometría hemática (BH / CBC)','TP / INR','TTPa (aPTT)','Glucosa','Creatinina','Sodio','Potasio'],
    'Reuma básico': ['ANA','Factor reumatoide','VSG','PCR ultrasensible','Anti-CCP'],
    'Embarazo básico': ['β-hCG','VIH Ag/Ac','VDRL / RPR','HBsAg','Anti-HBs','EGO (examen general de orina)'],
    'Panel viral respiratorio': ['Influenza A','Influenza B','RSV','SARS-CoV-2 (COVID-19)'],
    'TORCH': ['Toxoplasma','Rubéola','CMV','Herpes'],
    'Panel infeccioso PCR': ['SARS-CoV-2 (COVID-19)','Influenza A','Influenza B','RSV']
  };
  const LAB_SEARCH_CONFIG = {
    minChars: 2,
    maxResults: 12,
    boosts: {
      labelPrefix: 3,
      labelContains: 1,
      aliasPrefix: 4,
      aliasContains: 2
    }
  };
  const LAB_SEARCH_META = {
    glucose: { aliases: ['glu','glucosa en sangre'] },
    hba1c: { aliases: ['a1c','hemoglobina glicosilada'] },
    fructosamine: { aliases: ['fructosamina'] },
    ogtt: { aliases: ['curva glucosa','tolerancia a la glucosa','ogtt'] },
    urea: { aliases: ['urea'] },
    creatinine: { aliases: ['creat','creatinina'] },
    egfr: { aliases: ['tfg','egfr'] },
    bun: { aliases: ['bun','nitrogeno ureico'] },
    uric_acid: { aliases: ['urico','ácido úrico'] },
    sodium: { aliases: ['na','sodio'] },
    potassium: { aliases: ['k','potasio'] },
    chloride: { aliases: ['cl','cloro'] },
    calcium: { aliases: ['ca','calcio'] },
    magnesium: { aliases: ['mg','magnesio'] },
    phosphorus: { aliases: ['p','fosforo','fósforo'] },
    chol_total: { aliases: ['col total','colesterol total'] },
    hdl: { aliases: ['hdl','hdl-c'] },
    ldl: { aliases: ['ldl','ldl-c'] },
    triglycerides: { aliases: ['tgs','trigs','trigliceridos','triglicéridos'] },
    non_hdl: { aliases: ['no hdl','col no hdl'] },
    ast: { aliases: ['tgo','ast'] },
    alt: { aliases: ['tgp','alt'] },
    alp: { aliases: ['fosfatasa','alp'] },
    ggt: { aliases: ['ggt'] },
    bilirubin_total: { aliases: ['bt','bilirrubina total'] },
    bilirubin_direct: { aliases: ['bd','bilirrubina directa'] },
    bilirubin_indirect: { aliases: ['bi','bilirrubina indirecta'] },
    albumin: { aliases: ['alb','albumina','albúmina'] },
    total_protein: { aliases: ['pt','proteinas totales','proteínas totales'] },
    amylase: { aliases: ['amilasa'] },
    lipase: { aliases: ['lipasa'] },
    cbc: { aliases: ['bh','cbc','hemograma','biometria hematica','biometría hemática'] },
    hgb_hct: { aliases: ['hb','hcto','hemoglobina','hematocrito'] },
    platelets: { aliases: ['plt','plaquetas'] },
    iron: { aliases: ['fe','hierro'] },
    tibc: { aliases: ['tibc','ctfh'] },
    transferrin_sat: { aliases: ['sat transferrina','saturacion transferrina','saturación transferrina'] },
    ferritin: { aliases: ['ferritina'] },
    pt_inr: { aliases: ['tp','inr'] },
    aptt: { aliases: ['tt pa','aptt','ttpt','ttpa'] },
    fibrinogen: { aliases: ['fibrinogeno','fibrinógeno'] },
    d_dimer: { aliases: ['d dimer','dimero d','dímero d'] },
    tsh: { aliases: ['tsh'] },
    ft4: { aliases: ['t4l','ft4','t4 libre'] },
    ft3: { aliases: ['t3l','ft3','t3 libre'] },
    anti_tpo: { aliases: ['tpo','anti tpo'] },
    anti_tg: { aliases: ['anti tg','anti tiroglobulina'] },
    bhcg: { aliases: ['bhcg','beta hcg','β-hcg'] },
    lh: { aliases: ['lh'] },
    fsh: { aliases: ['fsh'] },
    prolactin: { aliases: ['prl','prolactina'] },
    estradiol: { aliases: ['e2','estradiol'] },
    progesterone: { aliases: ['p4','progesterona'] },
    testosterone_total: { aliases: ['testo total','testosterona total'] },
    testosterone_free: { aliases: ['testo libre','testosterona libre'] },
    crp_hs: { aliases: ['pcr us','pcr-us','hs-crp','pcr ultrasensible'] },
    esr: { aliases: ['vsg','esr','eritrosedimentacion','eritrosedimentación'] },
    ana: { aliases: ['ana'] },
    ena: { aliases: ['ena'] },
    anca: { aliases: ['anca'] },
    rf: { aliases: ['fr','rf','factor reumatoide'] },
    anti_ccp: { aliases: ['ccp','anti ccp'] },
    c3: { aliases: ['c3'] },
    c4: { aliases: ['c4'] },
    vitamin_d: { aliases: ['25-oh','25oh vitamina d','vit d'] },
    vitamin_b12: { aliases: ['b12','vit b12'] },
    folate: { aliases: ['folato','acido folico','ácido fólico'] },
    igg: { aliases: ['igg'] },
    iga: { aliases: ['iga'] },
    igm: { aliases: ['igm'] },
    hiv_ag_ac: { aliases: ['vih','hiv','vih ag/ac','hiv ag/ac'] },
    vdrl_rpr: { aliases: ['vdrl','rpr'] },
    hbsag: { aliases: ['hbsag','hepatitis b s ag'] },
    anti_hbs: { aliases: ['anti hbs','anti-hbs'] },
    anti_hbc: { aliases: ['anti hbc','anti-hbc'] },
    hcv_ab: { aliases: ['vhc','hcv','anti hcv'] },
    toxoplasma: { aliases: ['toxoplasma'] },
    rubella: { aliases: ['rubeola','rubéola','rubella'] },
    cmv: { aliases: ['cmv','citomegalovirus'] },
    herpes: { aliases: ['herpes'] },
    influenza_a: { aliases: ['influenza a','flu a'] },
    influenza_b: { aliases: ['influenza b','flu b'] },
    rsv: { aliases: ['rsv','virus sincitial'] },
    covid19: { aliases: ['covid','covid-19','sars-cov-2'] },
    urine_culture: { aliases: ['urocultivo'] },
    blood_culture: { aliases: ['hemocultivo'] },
    stool_culture: { aliases: ['coprocultivo'] },
    throat_swab: { aliases: ['exudado faringeo','faríngeo'] },
    vaginal_swab: { aliases: ['exudado vaginal'] },
    urinalysis: { aliases: ['ego','urinalisis','urinalysis','examen general de orina'] },
    microalbumin: { aliases: ['microalbumina','microalbuminuria'] },
    fecal_occult_blood: { aliases: ['soh','sangre oculta','sangre oculta en heces'] },
    stool_ova_parasites: { aliases: ['coproparasitoscopico','coproparasitoscópico','parasitos'] },
    factor_v_leiden: { aliases: ['factor v leiden'] },
    protein_c: { aliases: ['proteina c','proteína c'] },
    protein_s: { aliases: ['proteina s','proteína s'] },
    cyp2c19: { aliases: ['cyp2c19'] },
    cyp2d6: { aliases: ['cyp2d6'] }
  };
  const LAB_PACKAGE_META = {
    'QS (Glucosa, Urea, Creatinina)': { aliases: ['qs','bmp','quimica sanguinea','química sanguínea'] },
    'Perfil lipídico': { aliases: ['perfil lipidico','lipid panel','p lipidico'] },
    'Perfil hepático': { aliases: ['perfil hepatico','perfil hepático','pfh','funcion hepatica','función hepática'] },
    'Perfil tiroideo': { aliases: ['perfil tiroideo','tiroides','pt'] },
    'Control de diabetes': { aliases: ['control diabetes','diabetes'] },
    'ETS básico': { aliases: ['ets','its','std'] },
    'Preop básico': { aliases: ['preop','preoperatorio','preop basico'] },
    'Preop gineco': { aliases: ['preop gineco','preoperatorio gineco'] },
    'Preop ortopedia': { aliases: ['preop ortopedia','preoperatorio ortopedia'] },
    'Reuma básico': { aliases: ['reuma','reumatologia','reuma basico'] },
    'Embarazo básico': { aliases: ['embarazo','prenatal'] },
    'Panel viral respiratorio': { aliases: ['panel viral','respiratorio','influenza','covid'] },
    'TORCH': { aliases: ['torch'] },
    'Panel infeccioso PCR': { aliases: ['pcr','panel pcr','panel infeccioso'] }
  };
  const PICK_MAP_GEN = {};
  const PACK_MAP_GEN = {
    'NIPT estándar': ['NIPT (tamiz prenatal no invasivo)'],
    'CMA / Microarray (postnatal)': ['Microarreglo cromosómico (CMA / Microarray)'],
    'Exoma clínico TRIO (WES)': ['Exoma clínico (WES)'],
    'Panel multigénico de cáncer hereditario': ['Panel de cáncer hereditario (multigénico)'],
    'Cardiogenética (miocardiopatías / canalopatías)': ['Panel genético (NGS)'],
    'Neurogenética (epilepsia / neurodesarrollo)': ['Panel genético (NGS)'],
    'Panel tumoral somático (NGS) — FFPE': ['Panel tumoral (NGS) — somático']
  };
  const PACK_FLAG_MAP_GEN = {
    'NIPT estándar': ['context_prenatal','requires_consent','genetic_counseling','sample_blood'],
    'CMA / Microarray (postnatal)': ['context_postnatal','requires_consent','genetic_counseling','sample_blood'],
    'Exoma clínico TRIO (WES)': ['context_postnatal','requires_consent','genetic_counseling','trio','include_cnv','acmg_secondary_findings','sample_blood'],
    'Panel multigénico de cáncer hereditario': ['context_postnatal','requires_consent','genetic_counseling','include_cnv','sample_blood'],
    'Cardiogenética (miocardiopatías / canalopatías)': ['context_postnatal','requires_consent','genetic_counseling','include_cnv','sample_blood'],
    'Neurogenética (epilepsia / neurodesarrollo)': ['context_postnatal','requires_consent','genetic_counseling','include_cnv','sample_blood'],
    'Panel tumoral somático (NGS) — FFPE': ['context_postnatal','requires_consent','sample_ffpe']
  };
  const GENETICS_SEARCH_CONFIG = {
    minChars: 2,
    maxResults: 12,
    boosts: {
      labelPrefix: 3,
      labelContains: 1,
      aliasPrefix: 5,
      aliasContains: 2
    }
  };
  const GENETICS_SEARCH_META = {
    karyotype: { aliases: ['cariotipo','karyotype'] },
    cma_microarray: { aliases: ['cma','microarray','array cgh','acgh','a cgh'] },
    ngs_panel: { aliases: ['panel ngs','panel genes','gene panel','ngs'] },
    wes: { aliases: ['exoma','wes','whole exome'] },
    wgs: { aliases: ['genoma','wgs','whole genome'] },
    targeted_variant: { aliases: ['prueba dirigida','single gene','gen unico','gen único'] },
    nipt: { aliases: ['nipt','prenatal no invasivo','tamiz prenatal'] },
    carrier_screening: { aliases: ['portadores','carrier screening'] },
    hereditary_cancer_germline: { aliases: ['panel cancer','panel cáncer','multicancer','panel multicancer'] },
    brca1_2: { aliases: ['brca','brca1','brca2'] },
    lynch: { aliases: ['lynch','mlh1','msh2','msh6','pms2'] },
    thrombophilia: { aliases: ['trombofilia','factor v leiden','protrombina'] },
    pgx: { aliases: ['pgx','farmacogenomica','farmacogenómica','farmacogenetica','farmacogenética'] },
    somatic_tumor_ngs: { aliases: ['panel tumoral','ngs tumor','somatico','somático','tumor'] }
  };
  const GENETICS_PACKAGE_META = {
    'NIPT estándar': { aliases: ['nipt','tamiz prenatal'] },
    'CMA / Microarray (postnatal)': { aliases: ['cma','microarray','postnatal'] },
    'Exoma clínico TRIO (WES)': { aliases: ['exoma trio','wes trio','trio'] },
    'Panel multigénico de cáncer hereditario': { aliases: ['panel cancer','multicancer'] },
    'Cardiogenética (miocardiopatías / canalopatías)': { aliases: ['cardiogenetica','cardiogenética','miocardiopatia','miocardiopatía','canalopatias','canalopatías','qt largo','brugada'] },
    'Neurogenética (epilepsia / neurodesarrollo)': { aliases: ['neurogenetica','neurogenética','epilepsia','neurodesarrollo','autismo','ataxia'] },
    'Panel tumoral somático (NGS) — FFPE': { aliases: ['panel tumoral','ngs tumoral','ffpe','somatico tumor'] }
  };
  const PICK_MAP_FUNC = {};
  const PACK_MAP_FUNC = {
    'Espirometría pre/post broncodilatador': ['Espirometría'],
    'PFR completo (Espirometría + Volúmenes + DLCO)': ['Pruebas funcionales respiratorias completas (PFR completo)'],
    'PSG diagnóstica': ['Polisomnografía (PSG) diagnóstica'],
    'HSAT (apnea del sueño en casa)': ['Estudio domiciliario de apnea (HSAT)'],
    'EMG + VCN (estudio completo)': ['EMG + Velocidades de conducción nerviosa (VCN)'],
    'EEG rutinario': ['EEG rutinario']
  };
  const PACK_FLAG_MAP_FUNC = {
    'Espirometría pre/post broncodilatador': ['pre_post_bronchodilator'],
    'PFR completo (Espirometría + Volúmenes + DLCO)': [],
    'PSG diagnóstica': ['with_sleep_technologist'],
    'HSAT (apnea del sueño en casa)': [],
    'EMG + VCN (estudio completo)': [],
    'EEG rutinario': []
  };
  const FUNC_SEARCH_CONFIG = {
    minChars: 2,
    maxResults: 12,
    boosts: {
      labelPrefix: 3,
      labelContains: 1,
      aliasPrefix: 5,
      aliasContains: 2
    }
  };
  const FUNC_SEARCH_META = {
    spirometry: { aliases: ['espirometria','spirometry','fev1','fvc','curva flujo volumen'] },
    dlco: { aliases: ['dlco','difusion','diffusing capacity'] },
    plethysmography: { aliases: ['pletismografia','volumenes pulmonares','body plethysmography'] },
    full_pft: { aliases: ['pfr completo','funcion pulmonar completa','full pft'] },
    bronchial_challenge: { aliases: ['reto bronquial','provocacion','metacolina','bronchial challenge'] },
    feno: { aliases: ['feno','oxido nitrico exhalado'] },
    six_min_walk: { aliases: ['6mwt','caminata 6 minutos'] },
    cpet: { aliases: ['cpet','esfuerzo cardiopulmonar','ergospirometria'] },
    overnight_oximetry: { aliases: ['oximetria nocturna','overnight oximetry'] },
    capnography: { aliases: ['capnografia','capnography','etco2'] },
    psg_diagnostic: { aliases: ['psg','polisomnografia','polysomnography'] },
    psg_titration: { aliases: ['titulacion','psg cpap','pap titration'] },
    hsat: { aliases: ['hsat','home sleep apnea test','apnea en casa'] },
    mslt: { aliases: ['mslt','narcolepsia'] },
    mwt: { aliases: ['mwt','vigilancia'] },
    eeg_routine: { aliases: ['eeg','electroencefalograma'] },
    eeg_sleep_deprived: { aliases: ['eeg privacion','sleep deprived eeg'] },
    video_eeg: { aliases: ['video eeg','monitorizacion eeg'] },
    emg_ncs: { aliases: ['emg','vcn','ncs'] },
    repetitive_nerve_stimulation: { aliases: ['estim repetitiva','miastenia'] },
    sfemg: { aliases: ['fibra unica','sfemg'] },
    evoked_visual: { aliases: ['pev','visual evoked'] },
    evoked_auditory_baep: { aliases: ['peat','baep','abr neurologico'] },
    evoked_ssep: { aliases: ['pess','ssep'] },
    audiometry_tonal: { aliases: ['audiometria','tonal'] },
    audiometry_speech: { aliases: ['logoaudiometria','verbal'] },
    tympanometry: { aliases: ['timpanometria','impedanciometria'] },
    otoacoustic_emissions: { aliases: ['oea','otoemisiones'] },
    abr: { aliases: ['abr','peat','baep'] },
    vng: { aliases: ['vng','videonistagmografia'] },
    vemp: { aliases: ['vemp'] },
    tilt_table: { aliases: ['tilt','mesa inclinada','sincope'] },
    holter_bp_24h: { aliases: ['mapa 24','presion 24h','abpm'] },
    ankle_brachial_index: { aliases: ['itb','abi','tobillo brazo'] }
  };
  const FUNC_PACKAGE_META = {
    'Espirometría pre/post broncodilatador': { aliases: ['espirometria pre post','pre post broncodilatador'] },
    'PFR completo (Espirometría + Volúmenes + DLCO)': { aliases: ['pfr completo','funcion pulmonar completa'] },
    'PSG diagnóstica': { aliases: ['psg','polisomnografia diagnostica'] },
    'HSAT (apnea del sueño en casa)': { aliases: ['hsat','apnea en casa'] },
    'EMG + VCN (estudio completo)': { aliases: ['emg vcn','ncs','conduccion nerviosa'] },
    'EEG rutinario': { aliases: ['eeg','electroencefalograma'] }
  };
  const PICK_MAP_IMG = {
    'RX Tórax': ['RX Tórax'],
    'US Abdomen': ['US Abdomen'],
    'TAC Tórax': ['TAC Tórax'],
    'RM Cerebro': ['RM Cerebro'],
    'Mamografía': ['Mamografía']
  };
  const PACK_MAP_IMG = {
    'Control trimestral imagen': ['RX Tórax','US Abdomen','RM Columna (segmento)'],
    'Preop imagen': ['RX Abdomen','US Obstétrico','TAC Abdomen y pelvis'],
    'Gabinete vascular': ['Doppler carotídeo','Doppler venoso MI (TVP)','Doppler arterial MI']
  };
  const PICK_MAP_CARDIO = {};
  const PACK_MAP_CARDIO = {};
  const PICK_MAP_ENDO = {};
  const PACK_MAP_ENDO = {};
  const PICK_MAP_PAT = {
    'Biopsia gastrointestinal': ['Biopsia gastrointestinal'],
    'Pieza quirúrgica (general)': ['Pieza quirúrgica (general)'],
    'PAAF / BACAF (aspiración con aguja fina)': ['PAAF / BACAF (aspiración con aguja fina)'],
    'Revisión de laminillas (segunda opinión)': ['Revisión de laminillas (segunda opinión)']
  };
  const PACK_MAP_PAT = {
    'Pieza oncológica completa (márgenes + IHQ si se requiere)': ['Pieza oncológica (resección)'],
    'Biopsia renal completa (incluye IF si aplica)': ['Biopsia renal'],
    'Transoperatorio (congelación)': ['Pieza quirúrgica (general)']
  };
  const PACK_FLAG_MAP_PAT = {
    'Pieza oncológica completa (márgenes + IHQ si se requiere)': ['margins'],
    'Biopsia renal completa (incluye IF si aplica)': ['immunofluorescence'],
    'Transoperatorio (congelación)': ['frozen_section']
  };
  const modalConfigs = [
    { key:'lab', id:'modalEstudiosLab', accordionId:'estLabAccordion', pickMap:PICK_MAP_LAB, packMap:PACK_MAP_LAB, packFlagMap:{} },
    { key:'imagenologia', id:'modalEstudiosImg', panelSelector:'[data-est-modality-panels]', pickMap:PICK_MAP_IMG, packMap:PACK_MAP_IMG, packFlagMap:{} },
    { key:'cardiologia', id:'modalEstudiosCardio', panelSelector:'[data-est-modality-panels]', pickMap:PICK_MAP_CARDIO, packMap:PACK_MAP_CARDIO, packFlagMap:{} },
    { key:'endoscopia', id:'modalEstudiosEndo', panelSelector:'[data-est-modality-panels]', pickMap:PICK_MAP_ENDO, packMap:PACK_MAP_ENDO, packFlagMap:{} },
    { key:'patologia', id:'modalEstudiosPat', panelSelector:'[data-est-modality-panels]', pickMap:PICK_MAP_PAT, packMap:PACK_MAP_PAT, packFlagMap:PACK_FLAG_MAP_PAT },
    { key:'genetica', id:'modalEstudiosGen', panelSelector:'[data-est-modality-panels]', pickMap:PICK_MAP_GEN, packMap:PACK_MAP_GEN, packFlagMap:PACK_FLAG_MAP_GEN },
    { key:'funcionales', id:'modalEstudiosFunc', panelSelector:'[data-est-modality-panels]', pickMap:PICK_MAP_FUNC, packMap:PACK_MAP_FUNC, packFlagMap:PACK_FLAG_MAP_FUNC }
  ];
  const flagLabels = {
    priority_routine: 'Rutinario',
    priority_urgent: 'Urgente',
    priority_stat: 'Prioridad (STAT)',
    second_opinion: 'Segunda opinión',
    clinicopath_correlation: 'Con correlación clínica',
    diagnostic: 'Diagnóstica',
    therapeutic: 'Terapéutica',
    with_contrast: 'Con contraste',
    without_contrast: 'Sin contraste',
    right: 'Derecha',
    left: 'Izquierda',
    bilateral: 'Bilateral',
    with_biopsy: 'Con biopsia',
    with_polypectomy: 'Con polipectomía',
    with_hemostasis: 'Hemostasia',
    with_dilation: 'Con dilatación',
    with_stent: 'Colocación de stent',
    with_foreign_body: 'Extracción de cuerpo extraño',
    with_variceal_band: 'Ligadura de várices',
    with_injection_therapy: 'Terapia de inyección',
    with_clips: 'Clips / endoclips',
    with_tattoo: 'Tatuaje endoscópico',
    with_chromoendoscopy: 'Cromoendoscopia',
    with_image_enhancement: 'Realce de imagen (NBI/i-Scan)',
    with_report: 'Con interpretación',
    without_report: 'Sin interpretación',
    resting: 'En reposo',
    followup: 'Control / seguimiento',
    fasting: 'Ayuno',
    serial: 'Seriado',
    first_morning: 'Primera muestra de la mañana',
    with_antibiogram: 'Con antibiograma',
    laterality_right: 'Derecha',
    laterality_left: 'Izquierda',
    laterality_bilateral: 'Bilateral',
    contrast_with: 'Con contraste',
    contrast_without: 'Sin contraste',
    contrast_with_without: 'Con y sin contraste',
    rx_pa: 'PA',
    rx_ap: 'AP',
    rx_lateral: 'Lateral',
    rx_pa_lateral: 'PA + lateral',
    rx_oblique: 'Oblicuas',
    rx_portable: 'Portátil',
    rx_weight_bearing: 'Con carga',
    rx_two_views: '2 vistas',
    us_doppler: 'Con Doppler',
    us_color_doppler: 'Doppler color',
    us_arterial: 'Doppler arterial',
    us_venous: 'Doppler venoso',
    us_transvaginal: 'Transvaginal',
    us_transrectal: 'Transrectal',
    us_obstetric: 'Obstétrico',
    ct_angio: 'Angio-TAC',
    ct_phase_arterial: 'Fase arterial',
    ct_phase_venous: 'Fase venosa',
    ct_phase_delayed: 'Fase tardía',
    mr_angio: 'Angio-RM',
    mr_diffusion: 'Difusión (DWI)',
    mammo_screening: 'Tamizaje',
    mammo_diagnostic: 'Diagnóstica',
    mammo_tomo: 'Con tomosíntesis',
    vascular_color_doppler: 'Doppler color',
    vascular_spectral: 'Doppler espectral',
    vascular_arterial: 'Arterial',
    vascular_venous: 'Venoso',
    nm_with_ct: 'Con CT (atenuación)',
    pet_fdg: 'FDG',
    dexa_spine: 'Columna',
    dexa_hip: 'Cadera',
    dexa_whole_body: 'Cuerpo completo',
    with_color_doppler: 'Con Doppler color',
    with_tissue_doppler: 'Con Doppler tisular',
    with_strain: 'Con Strain/GLS',
    bubble_study: 'Estudio con burbujas (shunt)',
    margin_assessment: 'Evaluación de márgenes',
    oriented_margins: 'Márgenes orientados',
    decalcification: 'Con decalcificación',
    add_ihc: 'Agregar IHQ/IHC',
    add_special_stains: 'Agregar tinciones especiales',
    frozen_section: 'Transoperatorio / congelación',
    margins: 'Evaluación de márgenes',
    ihc: 'Con inmunohistoquímica (IHQ)',
    special_stains: 'Con tinciones especiales',
    ihc_single_marker: 'IHQ: marcador único',
    ihc_panel: 'IHQ: panel',
    ihc_breast_panel: 'IHQ: mama (ER/PR/HER2 ± Ki-67)',
    ihc_lung_panel: 'IHQ: pulmón (TTF-1/Napsina/P40)',
    ihc_lymphoma_panel: 'IHQ: linfoma (básico)',
    stain_pas: 'Tinción PAS',
    stain_zn: 'Ziehl-Neelsen (BAAR)',
    stain_gms: 'GMS (hongos)',
    stain_trichrome: 'Tricrómico (Masson)',
    stain_congo: 'Rojo Congo (amiloide)',
    stain_gram: 'Gram',
    intraoperative: 'Transoperatorio',
    with_bal: 'Con lavado broncoalveolar (BAL)',
    with_tbna: 'Con TBNA (aspiración)',
    with_ebus: 'Con EBUS',
    pre_post_bronchodilator: 'Pre / post broncodilatador',
    with_bronchial_challenge: 'Con reto bronquial (provocación)',
    with_exercise: 'Con ejercicio',
    with_sleep_technologist: 'Con técnico (atendido)',
    with_titration: 'Con titulación (CPAP/BiPAP)',
    upper_limbs: 'Miembros superiores',
    lower_limbs: 'Miembros inferiores',
    immunofluorescence: 'Con inmunofluorescencia',
    external_review: 'Revisión externa / segunda opinión',
    block_additional: 'Cortes/bloques adicionales',
    requires_consent: 'Requiere consentimiento informado',
    genetic_counseling: 'Consejería genética (si aplica)',
    context_prenatal: 'Prenatal',
    context_postnatal: 'Postnatal',
    sample_blood: 'Muestra: sangre (EDTA)',
    sample_saliva: 'Muestra: saliva',
    sample_tissue: 'Muestra: tejido fresco',
    sample_ffpe: 'Muestra: bloque/laminilla (FFPE)',
    sample_cvs: 'Muestra: CVS (vellosidades coriónicas)',
    sample_amnio: 'Muestra: amniocentesis',
    trio: 'TRIO (paciente+padre+madre)',
    duo: 'DUO (paciente+1 progenitor)',
    proband_only: 'Solo paciente',
    include_cnv: 'Incluir CNV',
    confirm_sanger: 'Confirmación por Sanger',
    acmg_secondary_findings: 'Consentimiento: hallazgos secundarios (ACMG SF)',
    cascade_testing: 'Familiar (cascade testing)',
    report_reanalysis: 'Reanálisis (si aplica)',
    with_sedation: 'Con sedación',
    without_sedation: 'Sin sedación',
    anesthesia: 'Con anestesia',
    with_doppler: 'Con Doppler',
    rest: 'Reposo',
    stress: 'Esfuerzo',
    adult: 'Adulto',
    pediatric: 'Pediátrico',
    ambulatory: 'Ambulatorio',
    in_hospital: 'Hospitalizado',
    holter_24h: '24 horas',
    holter_48h: '48 horas',
    holter_72h: '72 horas',
    holter_7d: '7 días',
    event_monitor: 'Monitor de eventos',
    exercise_treadmill: 'Banda (treadmill)',
    exercise_bike: 'Bicicleta',
    stress_exercise: 'Estrés por ejercicio',
    stress_pharmacologic: 'Estrés farmacológico',
    pharm_dobutamine: 'Dobutamina',
    pharm_dipyridamole: 'Dipiridamol',
    pharm_adenosine: 'Adenosina',
    mapa_24h: 'MAPA 24h',
    mapa_48h: 'MAPA 48h',
    urgent: 'Urgente'
  };
  const endoscopyFlagOrder = [
    'diagnostic',
    'therapeutic',
    'with_biopsy',
    'with_polypectomy',
    'with_hemostasis',
    'with_dilation',
    'with_stent',
    'with_foreign_body',
    'with_variceal_band',
    'with_injection_therapy',
    'with_clips',
    'with_tattoo',
    'with_chromoendoscopy',
    'with_image_enhancement',
    'with_bal',
    'with_tbna',
    'with_ebus',
    'with_sedation',
    'without_sedation',
    'anesthesia',
    'urgent',
    'followup',
    'adult',
    'pediatric'
  ];
  const pathologyFlagOrder = [
    'urgent',
    'followup',
    'frozen_section',
    'margins',
    'ihc',
    'special_stains',
    'decalcification',
    'immunofluorescence',
    'external_review',
    'block_additional'
  ];
  const cardiologyFlagOrder = [
    'priority_routine',
    'priority_urgent',
    'priority_stat',
    'adult',
    'pediatric',
    'with_report',
    'without_report',
    'resting',
    'holter_24h',
    'holter_48h',
    'holter_72h',
    'holter_7d',
    'event_monitor',
    'with_doppler',
    'with_color_doppler',
    'with_tissue_doppler',
    'with_strain',
    'with_contrast',
    'bubble_study',
    'exercise_treadmill',
    'exercise_bike',
    'stress_exercise',
    'stress_pharmacologic',
    'pharm_dobutamine',
    'pharm_dipyridamole',
    'pharm_adenosine',
    'with_sedation',
    'without_sedation',
    'anesthesia',
    'followup'
  ];
  const imagingFlagOrder = [
    'priority_urgent',
    'followup',
    'laterality_right',
    'laterality_left',
    'laterality_bilateral',
    'rx_two_views',
    'rx_pa',
    'rx_ap',
    'rx_lateral',
    'rx_pa_lateral',
    'rx_oblique',
    'rx_portable',
    'rx_weight_bearing',
    'contrast_with_without',
    'contrast_with',
    'contrast_without',
    'ct_angio',
    'ct_phase_arterial',
    'ct_phase_venous',
    'ct_phase_delayed',
    'us_transvaginal',
    'us_transrectal',
    'us_obstetric',
    'mr_angio',
    'mr_diffusion',
    'mammo_screening',
    'mammo_diagnostic',
    'mammo_tomo',
    'vascular_color_doppler',
    'vascular_spectral',
    'vascular_arterial',
    'vascular_venous',
    'nm_with_ct',
    'pet_fdg',
    'dexa_spine',
    'dexa_hip',
    'dexa_whole_body'
  ];
  const labFlagOrder = [
    'fasting',
    'urgent',
    'followup',
    'serial',
    'first_morning',
    'with_antibiogram'
  ];
  const geneticsFlagOrder = [
    'urgent',
    'followup',
    'requires_consent',
    'genetic_counseling',
    'context_prenatal',
    'context_postnatal',
    'sample_blood',
    'sample_saliva',
    'sample_tissue',
    'sample_ffpe',
    'sample_cvs',
    'sample_amnio',
    'trio',
    'duo',
    'proband_only',
    'include_cnv',
    'confirm_sanger',
    'acmg_secondary_findings',
    'cascade_testing',
    'report_reanalysis'
  ];
  const functionalFlagOrder = [
    'urgent',
    'followup',
    'pre_post_bronchodilator',
    'with_bronchial_challenge',
    'with_exercise',
    'with_sleep_technologist',
    'with_titration',
    'left',
    'right',
    'bilateral',
    'upper_limbs',
    'lower_limbs'
  ];
  const LAB_FLAG_VISIBILITY = {
    generalFlags: [
      'fasting',
      'urgent',
      'followup'
    ],
    applicability: {
      glucose: ['fasting','serial'],
      ogtt: ['fasting','serial'],
      urinalysis: ['first_morning'],
      microalbumin: ['first_morning'],
      urine_culture: ['with_antibiogram'],
      blood_culture: ['with_antibiogram'],
      stool_culture: ['with_antibiogram'],
      throat_swab: ['with_antibiogram'],
      vaginal_swab: ['with_antibiogram']
    }
  };
  const PATHOLOGY_FLAG_VISIBILITY = {
    generalFlags: [
      'urgent',
      'followup'
    ],
    applicability: {
      bx_soft_tissue: ['ihc','special_stains','block_additional'],
      bx_gi: ['ihc','special_stains','block_additional'],
      bx_skin: ['ihc','special_stains','block_additional'],
      bx_breast: ['ihc','special_stains','block_additional'],
      bx_gyn: ['ihc','special_stains','block_additional'],
      bx_urology: ['ihc','special_stains','block_additional'],
      bx_renal: ['immunofluorescence','ihc','special_stains','block_additional'],
      bx_bone: ['decalcification','ihc','special_stains','block_additional'],
      surg_specimen_general: ['margins','frozen_section','ihc','special_stains','block_additional'],
      surg_specimen_onco: ['margins','frozen_section','ihc','special_stains','block_additional'],
      amputation: ['margins','ihc','special_stains','block_additional'],
      slides_review: ['external_review'],
      paraffin_block_review: ['external_review','block_additional'],
      cyto_fna: ['ihc','special_stains','block_additional'],
      cyto_fluids: ['ihc','special_stains','block_additional'],
      cyto_pap: ['followup'],
      cyto_liquid_based: ['followup'],
      autopsy_clinical: ['urgent'],
      autopsy_fetal: ['urgent']
    }
  };
  const GENETICS_FLAG_VISIBILITY = {
    generalFlags: [
      'urgent',
      'followup'
    ],
    applicability: {
      karyotype: [
        'requires_consent',
        'genetic_counseling',
        'context_prenatal',
        'context_postnatal',
        'sample_blood',
        'sample_cvs',
        'sample_amnio',
        'cascade_testing'
      ],
      cma_microarray: [
        'requires_consent',
        'genetic_counseling',
        'context_prenatal',
        'context_postnatal',
        'sample_blood',
        'sample_cvs',
        'sample_amnio',
        'cascade_testing'
      ],
      targeted_variant: [
        'requires_consent',
        'genetic_counseling',
        'context_postnatal',
        'sample_blood',
        'sample_saliva',
        'sample_tissue',
        'cascade_testing',
        'confirm_sanger'
      ],
      ngs_panel: [
        'requires_consent',
        'genetic_counseling',
        'context_postnatal',
        'sample_blood',
        'sample_saliva',
        'sample_tissue',
        'include_cnv',
        'confirm_sanger',
        'cascade_testing'
      ],
      wes: [
        'requires_consent',
        'genetic_counseling',
        'context_postnatal',
        'sample_blood',
        'sample_saliva',
        'sample_tissue',
        'trio',
        'duo',
        'proband_only',
        'include_cnv',
        'acmg_secondary_findings',
        'report_reanalysis'
      ],
      wgs: [
        'requires_consent',
        'genetic_counseling',
        'context_postnatal',
        'sample_blood',
        'sample_saliva',
        'sample_tissue',
        'trio',
        'duo',
        'proband_only',
        'include_cnv',
        'acmg_secondary_findings',
        'report_reanalysis'
      ],
      nipt: [
        'requires_consent',
        'genetic_counseling',
        'context_prenatal',
        'sample_blood'
      ],
      carrier_screening: [
        'requires_consent',
        'genetic_counseling',
        'context_prenatal',
        'context_postnatal',
        'sample_blood',
        'sample_saliva'
      ],
      hereditary_cancer_germline: [
        'requires_consent',
        'genetic_counseling',
        'context_postnatal',
        'sample_blood',
        'sample_saliva',
        'include_cnv',
        'cascade_testing',
        'confirm_sanger'
      ],
      brca1_2: [
        'requires_consent',
        'genetic_counseling',
        'context_postnatal',
        'sample_blood',
        'sample_saliva',
        'include_cnv',
        'cascade_testing',
        'confirm_sanger'
      ],
      lynch: [
        'requires_consent',
        'genetic_counseling',
        'context_postnatal',
        'sample_blood',
        'sample_saliva',
        'include_cnv',
        'cascade_testing',
        'confirm_sanger'
      ],
      thrombophilia: [
        'requires_consent',
        'context_postnatal',
        'sample_blood',
        'cascade_testing',
        'confirm_sanger'
      ],
      pgx: [
        'requires_consent',
        'context_postnatal',
        'sample_blood',
        'sample_saliva'
      ],
      somatic_tumor_ngs: [
        'requires_consent',
        'context_postnatal',
        'sample_ffpe',
        'sample_tissue'
      ]
    }
  };
  const FUNCTIONAL_FLAG_VISIBILITY = {
    generalFlags: [
      'urgent',
      'followup'
    ],
    applicability: {
      spirometry: ['pre_post_bronchodilator'],
      full_pft: ['pre_post_bronchodilator'],
      dlco: [],
      plethysmography: [],
      feno: [],
      six_min_walk: ['with_exercise'],
      cpet: ['with_exercise'],
      bronchial_challenge: ['with_bronchial_challenge'],
      overnight_oximetry: [],
      capnography: [],
      psg_diagnostic: ['with_sleep_technologist'],
      psg_titration: ['with_sleep_technologist','with_titration'],
      hsat: [],
      mslt: ['with_sleep_technologist'],
      mwt: ['with_sleep_technologist'],
      eeg_routine: [],
      eeg_sleep_deprived: [],
      video_eeg: ['with_sleep_technologist'],
      emg_ncs: ['upper_limbs','lower_limbs'],
      repetitive_nerve_stimulation: [],
      sfemg: [],
      evoked_visual: [],
      evoked_auditory_baep: [],
      evoked_ssep: [],
      audiometry_tonal: ['left','right','bilateral'],
      audiometry_speech: ['left','right','bilateral'],
      tympanometry: ['left','right','bilateral'],
      otoacoustic_emissions: ['left','right','bilateral'],
      abr: ['left','right','bilateral'],
      vng: [],
      vemp: [],
      tilt_table: [],
      holter_bp_24h: [],
      ankle_brachial_index: ['left','right','bilateral']
    }
  };
  const CARDIOLOGY_FLAG_VISIBILITY = {
    generalFlags: [
      'priority_routine',
      'priority_urgent',
      'priority_stat',
      'adult',
      'pediatric',
      'with_report',
      'without_report',
      'followup'
    ],
    applicability: {
      ecg_12lead: ['resting'],
      ecg_rhythm_strip: ['resting'],
      holter: ['holter_24h','holter_48h','holter_72h','holter_7d','event_monitor'],
      abpm_mapa: [],
      echo_tte: ['with_doppler','with_color_doppler','with_tissue_doppler','with_contrast','with_strain','bubble_study'],
      echo_tes: ['with_doppler','with_color_doppler','with_contrast','bubble_study','with_sedation','without_sedation','anesthesia'],
      stress_test: ['exercise_treadmill','exercise_bike'],
      stress_echo: [
        'stress_exercise',
        'stress_pharmacologic',
        'exercise_treadmill',
        'exercise_bike',
        'pharm_dobutamine',
        'pharm_dipyridamole',
        'pharm_adenosine',
        'with_doppler',
        'with_contrast'
      ],
      tilt_table: [],
      carotid_doppler: [],
      lower_ext_art_doppler: [],
      lower_ext_venous_doppler: []
    }
  };
  const IMAGING_FLAG_VISIBILITY = {
    generalFlags: [
      'priority_urgent',
      'followup',
      'laterality_right',
      'laterality_left',
      'laterality_bilateral'
    ],
    applicability: {
      rx_chest: ['rx_pa','rx_ap','rx_lateral','rx_pa_lateral','rx_two_views','rx_portable'],
      rx_abdomen: ['rx_ap','rx_lateral','rx_two_views'],
      rx_pelvis: ['rx_ap'],
      rx_cspine: ['rx_ap','rx_lateral','rx_oblique','rx_two_views'],
      rx_lspine: ['rx_ap','rx_lateral','rx_oblique','rx_two_views'],
      rx_shoulder: ['rx_ap','rx_lateral','rx_oblique','rx_two_views'],
      rx_knee: ['rx_ap','rx_lateral','rx_two_views','rx_weight_bearing'],
      rx_ankle: ['rx_ap','rx_lateral','rx_oblique','rx_two_views'],
      rx_hand: ['rx_ap','rx_oblique','rx_two_views'],
      us_abdomen: [],
      us_pelvic: ['us_transvaginal','us_transrectal'],
      us_obstetric_study: ['us_obstetric'],
      us_thyroid: [],
      us_soft_tissue: [],
      us_testicular: [],
      us_renal: [],
      breast_us: [],
      ct_head: ['contrast_with','contrast_without','contrast_with_without'],
      ct_chest: ['contrast_with','contrast_without','contrast_with_without','ct_angio'],
      ct_abdomen_pelvis: ['contrast_with','contrast_without','contrast_with_without','ct_phase_arterial','ct_phase_venous','ct_phase_delayed'],
      ct_uro: ['contrast_with','contrast_without','contrast_with_without','ct_phase_venous','ct_phase_delayed'],
      ct_spine: ['contrast_with','contrast_without','contrast_with_without'],
      ct_extremity: ['contrast_with','contrast_without','contrast_with_without'],
      mr_brain: ['contrast_with','contrast_without','contrast_with_without','mr_diffusion'],
      mr_spine: ['contrast_with','contrast_without','contrast_with_without'],
      mr_knee: ['contrast_with','contrast_without','contrast_with_without'],
      mr_shoulder: ['contrast_with','contrast_without','contrast_with_without'],
      mr_abdomen: ['contrast_with','contrast_without','contrast_with_without'],
      mr_angio_study: ['mr_angio','contrast_with'],
      mammo: ['mammo_screening','mammo_diagnostic','mammo_tomo'],
      vasc_carotid: ['vascular_color_doppler','vascular_spectral'],
      vasc_venous_leg: ['vascular_color_doppler','vascular_spectral','vascular_venous'],
      vasc_arterial_leg: ['vascular_color_doppler','vascular_spectral','vascular_arterial'],
      vasc_upper_ext: ['vascular_color_doppler','vascular_spectral','vascular_arterial','vascular_venous'],
      vasc_abdomen: ['vascular_color_doppler','vascular_spectral','vascular_arterial','vascular_venous'],
      nm_bone_scan: ['nm_with_ct'],
      nm_thyroid_uptake: [],
      pet_ct: ['pet_fdg'],
      dexa: ['dexa_spine','dexa_hip','dexa_whole_body'],
      fluoro_hsg: [],
      fluoro_vcug: [],
      fluoro_ugi: [],
      fluoro_barium_enema: []
    }
  };
  const ENDOSCOPY_FLAG_VISIBILITY = {
    generalFlags: [
      'diagnostic',
      'therapeutic',
      'with_sedation',
      'without_sedation',
      'anesthesia',
      'urgent',
      'followup',
      'adult',
      'pediatric'
    ],
    applicability: {
      egd_eda_base: [
        'with_biopsy',
        'with_hemostasis',
        'with_dilation',
        'with_stent',
        'with_foreign_body',
        'with_variceal_band',
        'with_injection_therapy',
        'with_clips',
        'with_chromoendoscopy',
        'with_image_enhancement'
      ],
      colonoscopy_base: [
        'with_biopsy',
        'with_polypectomy',
        'with_hemostasis',
        'with_dilation',
        'with_stent',
        'with_foreign_body',
        'with_clips',
        'with_tattoo',
        'with_chromoendoscopy',
        'with_image_enhancement'
      ],
      flex_sig_base: [
        'with_biopsy',
        'with_polypectomy',
        'with_hemostasis',
        'with_dilation',
        'with_stent',
        'with_foreign_body',
        'with_clips',
        'with_tattoo',
        'with_chromoendoscopy',
        'with_image_enhancement'
      ],
      anoscopy_base: [
        'with_biopsy',
        'with_hemostasis'
      ],
      proctoscopy_base: [
        'with_biopsy',
        'with_hemostasis'
      ],
      ercp_cpre_base: [
        'with_dilation',
        'with_stent'
      ],
      eus_use_base: [
        'with_biopsy'
      ],
      capsule_base: [],
      enteroscopy_base: [
        'with_biopsy',
        'with_hemostasis',
        'with_dilation',
        'with_stent',
        'with_foreign_body',
        'with_clips',
        'with_tattoo',
        'with_image_enhancement'
      ],
      bronchoscopy_base: [
        'with_biopsy',
        'with_bal',
        'with_tbna',
        'with_foreign_body',
        'with_hemostasis',
        'with_stent'
      ],
      ebus_base: [
        'with_tbna',
        'with_biopsy',
        'with_bal'
      ],
      laryngoscopy_base: [
        'with_biopsy'
      ],
      pleuroscopy_base: [
        'with_biopsy',
        'with_hemostasis'
      ]
    }
  };
  const activeFlags = new Set();
  const itemMetaMap = {};
  const modalityLabelMap = {};
  const rawDelimiter = '|';
  const allCheckboxes = Array.from(document.querySelectorAll('input[type="checkbox"][data-est-item]'));
  const summaryWrap = document.querySelector('[data-est-summary]');
  const summaryCount = document.querySelector('[data-est-count]');
  const summaryEdit = document.querySelector('[data-est-edit]');
  const summaryClear = document.querySelector('[data-est-clear]');
  const summaryContainer = summaryWrap?.closest('.est-summary');
  const orderBlock = document.querySelector('[data-est-order-block]');
  const orderList = document.querySelector('.est-orders-list');
  const areaSelect = orderBlock?.querySelector('[data-est-area-select]');
  const openInputs = Array.from(document.querySelectorAll('[data-est-open-modal]'));
  const orderListInitial = orderList ? orderList.innerHTML : '';
  if(!openInputs.length) return;

  const controllers = [];
  const controllerMap = {};
  let selectionOrder = [];
  let activeInput = null;
  let activeController = null;
  let modalMode = 'add';
  let modalModeController = null;
  const groupLabels = {};
  const itemGroupMap = {};
  const itemOrder = {};
  const groupOrder = [];
  let orderIndex = 0;
  const searchStates = {};
  const searchConfigMap = {
    lab: {
      config: LAB_SEARCH_CONFIG,
      meta: LAB_SEARCH_META,
      packMeta: LAB_PACKAGE_META,
      categoryMode: 'group'
    },
    genetica: {
      config: GENETICS_SEARCH_CONFIG,
      meta: GENETICS_SEARCH_META,
      packMeta: GENETICS_PACKAGE_META,
      categoryMode: 'modality'
    },
    funcionales: {
      config: FUNC_SEARCH_CONFIG,
      meta: FUNC_SEARCH_META,
      packMeta: FUNC_PACKAGE_META,
      categoryMode: 'modality'
    }
  };

  function escapeHtml(str){
    return String(str).replace(/[&<>"']/g, s=>({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[s]));
  }
  function normalizeItems(items){
    const seen = new Set();
    return items.map(i=>i.trim()).filter(Boolean).filter(item=>{
      if(seen.has(item)) return false;
      seen.add(item);
      return true;
    });
  }
  function normalizeSearchText(value){
    return String(value || '')
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/[^a-z0-9]+/g, ' ')
      .trim();
  }
  function parseInputValue(val){
    if(!val) return [];
    return normalizeItems(val.split(','));
  }
  function getInputItems(input){
    const raw = (input?.dataset?.estRaw || '').trim();
    if(raw) return normalizeItems(raw.split(rawDelimiter));
    return parseInputValue(input?.value || '');
  }
  function getItemMeta(item, controllerKey){
    const entry = itemMetaMap[item];
    if(!entry) return null;
    if(controllerKey && entry[controllerKey]) return entry[controllerKey];
    return entry.default || entry[Object.keys(entry)[0]] || null;
  }
  function getItemGroup(item, controllerKey){
    const entry = itemGroupMap[item];
    if(!entry) return null;
    if(controllerKey && entry[controllerKey]) return entry[controllerKey];
    return entry.default || entry[Object.keys(entry)[0]] || null;
  }
  function getOrderedFlags(controllerKey){
    if(controllerKey === 'endoscopia'){
      return endoscopyFlagOrder.filter(id=> activeFlags.has(id)).map(id=> flagLabels[id]).filter(Boolean);
    }
    if(controllerKey === 'patologia'){
      return pathologyFlagOrder.filter(id=> activeFlags.has(id)).map(id=> flagLabels[id]).filter(Boolean);
    }
    if(controllerKey === 'cardiologia'){
      return cardiologyFlagOrder.filter(id=> activeFlags.has(id)).map(id=> flagLabels[id]).filter(Boolean);
    }
    if(controllerKey === 'lab'){
      return labFlagOrder.filter(id=> activeFlags.has(id)).map(id=> flagLabels[id]).filter(Boolean);
    }
    if(controllerKey === 'genetica'){
      return geneticsFlagOrder.filter(id=> activeFlags.has(id)).map(id=> flagLabels[id]).filter(Boolean);
    }
    if(controllerKey === 'funcionales'){
      return functionalFlagOrder.filter(id=> activeFlags.has(id)).map(id=> flagLabels[id]).filter(Boolean);
    }
    if(controllerKey === 'imagenologia'){
      return imagingFlagOrder.filter(id=> activeFlags.has(id)).map(id=> flagLabels[id]).filter(Boolean);
    }
    return Array.from(activeFlags).map(id=> flagLabels[id]).filter(Boolean);
  }
  function buildDisplayName(item, controllerKey){
    const meta = getItemMeta(item, controllerKey);
    let name = item;
    const flags = getOrderedFlags(controllerKey).join(' ');
    if(controllerKey === 'lab' || controllerKey === 'endoscopia' || controllerKey === 'patologia' || controllerKey === 'cardiologia' || controllerKey === 'imagenologia' || controllerKey === 'genetica' || controllerKey === 'funcionales'){
      if(flags) name = `${name} ${flags}`;
      return name.trim();
    }
    if(meta?.modalityLabel){
      name = `${meta.modalityLabel} ${name}`;
      if(flags) name = `${name} ${flags}`;
    }
    return name.trim();
  }
  function setInputItems(input, items, controllerKey){
    if(!input) return;
    input.dataset.estRaw = items.join(rawDelimiter);
    input.value = items.map(item=> buildDisplayName(item, controllerKey)).join(', ');
  }
  function setSelectionOrder(items){
    selectionOrder = normalizeItems(items || []);
  }
  function addToOrder(item){
    if(!item) return;
    if(!selectionOrder.includes(item)) selectionOrder.push(item);
  }
  function addToOrderList(items){
    normalizeItems(items || []).forEach(addToOrder);
  }
  function removeFromOrder(item){
    selectionOrder = selectionOrder.filter(i=>i !== item);
  }
  function setItemChecked(name, checked){
    if(!name) return;
    allCheckboxes.forEach(cb=>{
      if(cb.dataset.estItem === name) cb.checked = checked;
    });
  }
  function resetSelections(){
    allCheckboxes.forEach(cb=>{ cb.checked = false; });
  }
  function getOrderedItems(){
    const checkedSet = new Set();
    allCheckboxes.forEach(cb=>{
      if(cb.checked) checkedSet.add(cb.dataset.estItem);
    });
    selectionOrder = selectionOrder.filter(item=>checkedSet.has(item));
    checkedSet.forEach(item=>{
      if(!selectionOrder.includes(item)) selectionOrder.push(item);
    });
    return selectionOrder.slice();
  }
  function getSearchState(key){
    if(!searchStates[key]){
      searchStates[key] = { index: [], layer: null, items: [], activeIndex: -1 };
    }
    return searchStates[key];
  }
  function buildSearchIndex(controller, cfg){
    if(!controller || !cfg) return [];
    const entries = [];
    const inputs = Array.from(controller.modalEl.querySelectorAll('input[type="checkbox"][data-est-id][data-est-item]'));
    inputs.forEach(cb=>{
      if(cb.disabled) return;
      const id = cb.dataset.estId;
      const label = cb.dataset.estItem;
      if(!id || !label) return;
      const meta = cfg.meta[id] || {};
      const aliases = (meta.aliases || []).slice();
      const groupId = getItemGroup(label, controller.key);
      const groupLabel = groupLabels[groupId] || (controller.key === 'genetica' ? 'Genética' : 'Laboratorio');
      const modalityLabel = getItemMeta(label, controller.key)?.modalityLabel || groupLabel;
      const areaLabel = cfg.categoryMode === 'modality' ? modalityLabel : groupLabel;
      entries.push({
        type: 'test',
        id,
        label,
        areaLabel,
        aliases,
        normLabel: normalizeSearchText(label),
        normAliases: aliases.map(normalizeSearchText).filter(Boolean)
      });
    });
    Object.keys(controller.packMap || {}).forEach(label=>{
      const meta = cfg.packMeta[label] || {};
      const aliases = (meta.aliases || []).slice();
      entries.push({
        type: 'package',
        id: label,
        label,
        areaLabel: 'Paquetes',
        aliases,
        normLabel: normalizeSearchText(label),
        normAliases: aliases.map(normalizeSearchText).filter(Boolean)
      });
    });
    return entries;
  }
  function getSearchMatches(controllerKey, query){
    const cfg = searchConfigMap[controllerKey];
    if(!cfg) return [];
    const term = normalizeSearchText(query);
    if(!term || term.length < cfg.config.minChars) return [];
    const termCompact = term.replace(/\s+/g, '');
    const state = getSearchState(controllerKey);
    const matches = [];
    state.index.forEach(entry=>{
      let score = 0;
      if(entry.normLabel.startsWith(term) || entry.normLabel.replace(/\s+/g, '').startsWith(termCompact)){
        score = Math.max(score, cfg.config.boosts.labelPrefix);
      }else if(entry.normLabel.includes(term)){
        score = Math.max(score, cfg.config.boosts.labelContains);
      }
      entry.normAliases.forEach(alias=>{
        if(alias.startsWith(term) || alias.replace(/\s+/g, '').startsWith(termCompact)){
          score = Math.max(score, cfg.config.boosts.aliasPrefix);
        }else if(alias.includes(term)){
          score = Math.max(score, cfg.config.boosts.aliasContains);
        }
      });
      if(score > 0){
        matches.push({ entry, score });
      }
    });
    matches.sort((a,b)=>{
      if(b.score !== a.score) return b.score - a.score;
      return a.entry.label.localeCompare(b.entry.label);
    });
    return matches.slice(0, cfg.config.maxResults).map(item=> item.entry);
  }
  function highlightSuggest(state, idx){
    state.activeIndex = idx;
    state.items.forEach((item, i)=>{
      if(item.node){
        item.node.classList.toggle('active', i === state.activeIndex);
        if(i === state.activeIndex){
          try{ item.node.scrollIntoView({ block:'nearest' }); }catch(_){}
        }
      }
    });
  }
  function hideSuggest(controllerKey){
    const state = getSearchState(controllerKey);
    if(state.layer){
      state.layer.remove();
      state.layer = null;
    }
    state.items = [];
    state.activeIndex = -1;
  }
  function applySearchSuggestion(entry, controller){
    if(!entry || !controller) return;
    hideSuggest(controller.key);
    if(entry.type === 'package'){
      controller.applyPackage(entry.label);
    }else{
      controller.applyItems([entry.label]);
      renderSelected();
    }
    if(controller.searchInput){
      controller.searchInput.value = '';
      controller.applyFilterChip('todos');
      controller.filterList('');
      controller.searchInput.focus();
    }
  }
  function showSuggest(list, anchor, controller){
    if(!list.length || !anchor) return;
    hideSuggest(controller.key);
    const state = getSearchState(controller.key);
    const rect = anchor.getBoundingClientRect();
    const box = document.createElement('div');
    box.className = 'grp-suggest';
    box.style.left = `${window.scrollX + rect.left}px`;
    box.style.top = `${window.scrollY + rect.bottom + 4}px`;
    box.style.width = `${rect.width}px`;
    state.items = [];
    list.forEach(entry=>{
      const it = document.createElement('div');
      it.className = 'item';
      const nm = document.createElement('div');
      nm.className = 'name';
      nm.textContent = entry.label;
      const ad = document.createElement('div');
      ad.className = 'addr';
      ad.textContent = entry.areaLabel || '';
      it.appendChild(nm);
      it.appendChild(ad);
      it.addEventListener('click', ()=> applySearchSuggestion(entry, controller));
      box.appendChild(it);
      state.items.push({ data: entry, node: it });
    });
    document.body.appendChild(box);
    state.layer = box;
    highlightSuggest(state, 0);
    const handler = (ev)=>{
      if(!state.layer) return;
      if(!box.contains(ev.target) && ev.target !== anchor){
        hideSuggest(controller.key);
        document.removeEventListener('mousedown', handler, true);
      }
    };
    document.addEventListener('mousedown', handler, true);
  }
  function setupTypeahead(controller){
    const cfg = searchConfigMap[controller?.key];
    const input = controller?.searchInput;
    if(!cfg || !input) return;
    const state = getSearchState(controller.key);
    state.index = buildSearchIndex(controller, cfg);
    input.addEventListener('input', ()=>{
      const results = getSearchMatches(controller.key, input.value);
      if(results.length){
        showSuggest(results, input, controller);
      }else{
        hideSuggest(controller.key);
      }
    });
    input.addEventListener('keydown', (ev)=>{
      if(!state.layer || !state.items.length) return;
      if(ev.key === 'ArrowDown'){
        ev.preventDefault();
        const next = (state.activeIndex + 1) % state.items.length;
        highlightSuggest(state, next);
      }else if(ev.key === 'ArrowUp'){
        ev.preventDefault();
        const next = (state.activeIndex - 1 + state.items.length) % state.items.length;
        highlightSuggest(state, next);
      }else if(ev.key === 'Enter'){
        ev.preventDefault();
        const item = state.items[state.activeIndex];
        if(item) applySearchSuggestion(item.data, controller);
      }else if(ev.key === 'Escape'){
        ev.preventDefault();
        hideSuggest(controller.key);
      }
    });
    controller.modalEl?.addEventListener('hidden.bs.modal', ()=> hideSuggest(controller.key));
  }
  function syncCheckboxesFromItems(items){
    const set = new Set(items);
    allCheckboxes.forEach(cb=>{
      cb.checked = set.has(cb.dataset.estItem);
    });
  }
  function ensureAreaGroup(label){
    const clean = (label || '').trim();
    if(!clean) return null;
    const key = `area-${clean.toLowerCase().replace(/[^a-z0-9]+/g, '-')}`;
    if(!groupLabels[key]) groupLabels[key] = clean;
    if(!groupOrder.includes(key)) groupOrder.push(key);
    return key;
  }
  function setAreaSelect(label){
    if(!areaSelect || !label) return;
    const target = label.trim().toLowerCase();
    const option = Array.from(areaSelect.options).find(opt => (opt.textContent || '').trim().toLowerCase() === target);
    if(option) areaSelect.value = option.value;
  }
  function renderSummary(items, controllerKey){
    if(!summaryWrap) return;
    const list = normalizeItems(items || []);
    if(summaryCount) summaryCount.textContent = `(${list.length})`;
    if(!list.length){
      summaryWrap.innerHTML = '<div class="est-summary-empty">Sin selección todavía</div>';
      return;
    }
    const listIndex = {};
    list.forEach((item, idx)=>{ listIndex[item] = idx; });
    const grouped = {};
    list.forEach(item=>{
      const groupId = getItemGroup(item, controllerKey) || 'otros';
      if(!grouped[groupId]) grouped[groupId] = [];
      grouped[groupId].push(item);
    });
    const order = groupOrder.slice();
    if(grouped.otros && !order.includes('otros')) order.push('otros');
    summaryWrap.innerHTML = order.filter(id=> grouped[id]?.length).map(id=>{
      const label = groupLabels[id] || 'Otros';
      const itemsSorted = grouped[id].slice().sort((a,b)=>{
        const oa = itemOrder[a];
        const ob = itemOrder[b];
        if(oa != null || ob != null) return (oa ?? 9999) - (ob ?? 9999);
        return listIndex[a] - listIndex[b];
      });
      return `<div class="est-summary-group"><div class="est-summary-group-ttl"><span class="est-summary-group-chip">${escapeHtml(label)} <span class="est-summary-group-count">${itemsSorted.length}</span></span></div><div class="est-summary-group-list">${itemsSorted.map(item=>{
        const safeBase = escapeHtml(item);
        const display = escapeHtml(buildDisplayName(item, controllerKey));
        return `<div class="est-summary-item"><span>${display}</span><button type="button" class="est-summary-remove" data-est-remove="${safeBase}" aria-label="Quitar ${safeBase}">&times;</button></div>`;
      }).join('')}</div></div>`;
    }).join('');
  }
  function setSelection(items, input, controllerKey){
    const list = normalizeItems(items || []);
    const controller = controllerKey ? controllerMap[controllerKey] : getControllerForArea(areaSelect?.value || '');
    const key = controller?.key || controllerKey;
    setSelectionOrder(list);
    syncCheckboxesFromItems(list);
    controller?.updateFlagVisibility?.();
    setInputItems(input, list, key);
    renderSummary(list, key);
    renderSelected();
  }
  function renderSelected(){
    const items = getOrderedItems();
    controllers.forEach(ctrl=> ctrl.updateFlagVisibility?.());
    controllers.forEach(ctrl=> ctrl.renderSelected(items));
  }
  function syncDuplicates(item, checked){
    allCheckboxes.forEach(cb=>{
      if(cb.dataset.estItem === item) cb.checked = checked;
    });
  }
  function setModalMode(mode, controller){
    modalMode = mode === 'edit' ? 'edit' : 'add';
    modalModeController = controller || null;
    const target = (controller || activeController)?.addBtn;
    if(target){
      target.textContent = modalMode === 'edit' ? 'Actualizar orden' : 'Generar orden';
    }
  }
  function getControllerForArea(area){
    const normalized = (area || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim().toLowerCase();
    const nameMap = {
      laboratorio:'lab',
      imagenologia:'imagenologia',
      'imagenología':'imagenologia',
      cardiologia:'cardiologia',
      'cardiología':'cardiologia',
      endoscopia:'endoscopia',
      'endoscopía':'endoscopia',
      patologia:'patologia',
      'patología':'patologia',
      genetica:'genetica',
      'genética':'genetica',
      funcionales:'funcionales',
      funcional:'funcionales'
    };
    const key = nameMap[normalized] || 'lab';
    return controllerMap[key] || controllerMap.lab;
  }
  function openControllerForInput(input){
    if(!input) return;
    const area = areaSelect?.value || '';
    const controller = getControllerForArea(area);
    if(!controller) return;
    activeInput = input;
    activeController = controller;
    setModalMode('add', controller);
    controller.open(input);
  }
  function scrollToOrderBlock(){
    const target = orderBlock || summaryContainer || summaryWrap || activeInput;
    if(!target) return;
    const rect = target.getBoundingClientRect();
    const top = window.scrollY + rect.top - 90;
    window.scrollTo({ top, behavior: 'smooth' });
    target.classList.add('est-order-focus');
    setTimeout(()=> target.classList.remove('est-order-focus'), 1200);
  }
  function createController(cfg){
    const modalEl = document.getElementById(cfg.id);
    if(!modalEl) return null;
    const modal = new bootstrap.Modal(modalEl);
    const searchInput = modalEl.querySelector('[data-est-lab-search]');
    const groupButtons = Array.from(modalEl.querySelectorAll('[data-est-group]'));
    const modalityButtons = Array.from(modalEl.querySelectorAll('[data-est-modality]'));
    const modalityPanels = Array.from(modalEl.querySelectorAll('[data-est-modality-panel]'));
    const groupPanes = Array.from(modalEl.querySelectorAll('[data-est-group-pane]'));
    const flagButtons = Array.from(modalEl.querySelectorAll('[data-est-flag]'));
    const filterChips = Array.from(modalEl.querySelectorAll('.est-chip-filter'));
    const pickButtons = Array.from(modalEl.querySelectorAll('[data-est-lab-pick]'));
    const packButtons = Array.from(modalEl.querySelectorAll('[data-est-lab-pack]'));
    const favBox = modalEl.querySelector('.est-lab-fav');
    const favFre = modalEl.querySelector('[data-est-fav="frecuentes"]');
    const favPack = modalEl.querySelector('[data-est-fav="paquetes"]');
    const groupCol = modalEl.querySelector('.est-lab-groups')?.closest('.col-md-3');
    const accordionCol = cfg.accordionId ? modalEl.querySelector(`#${cfg.accordionId}`)?.closest('.col-md-9') : null;
    const panelCol = cfg.panelSelector ? modalEl.querySelector(cfg.panelSelector)?.closest('.col-md-9') : null;
    const selectedWrap = modalEl.querySelector('[data-est-selected]');
    const modalCount = modalEl.querySelector('[data-est-modal-count]');
    const addBtn = modalEl.querySelector('.modal-footer .btn-primary');
    const controller = {
      key: cfg.key,
      modal,
      modalEl,
      addBtn,
      groupButtons,
      modalityButtons,
      modalityPanels,
      groupPanes,
      flagButtons,
      filterChips,
      searchInput,
      pickButtons,
      packButtons,
      favBox,
      favFre,
      favPack,
      groupCol,
      accordionCol,
      panelCol,
      selectedWrap,
      modalCount,
      pickMap: cfg.pickMap || {},
      packMap: cfg.packMap || {},
      packFlagMap: cfg.packFlagMap || {}
    };
    const flagButtonMap = flagButtons.reduce((acc, btn)=>{
      const id = btn.dataset.estFlag;
      if(id) acc[id] = btn;
      return acc;
    }, {});
    controller.applyItems = function(items){
      addToOrderList(items);
      items.forEach(item=> setItemChecked(item, true));
    };
    controller.applyPick = function(key){
      const items = controller.pickMap[key] || [];
      controller.applyItems(items);
      renderSelected();
    };
    controller.applyPackage = function(key){
      const items = controller.packMap[key] || [];
      controller.applyItems(items);
      controller.updateFlagVisibility?.();
      const defaults = controller.packFlagMap[key] || [];
      defaults.forEach(flagId=>{
        const flagBtn = flagButtonMap[flagId];
        if(!flagBtn || flagBtn.classList.contains('d-none') || activeFlags.has(flagId)) return;
        flagBtn.click();
      });
      renderSelected();
    };
    controller.applyFilterChip = function(type){
      filterChips.forEach(ch=> ch.classList.toggle('active', ch.dataset.estFilter === type));
      const contentCol = panelCol || accordionCol;
      if(!favBox){
        groupCol?.classList.remove('d-none');
        contentCol?.classList.remove('d-none');
        return;
      }
      favBox.classList.remove('d-none');
      if(type === 'frecuentes'){
        favFre?.classList.remove('d-none');
        favPack?.classList.add('d-none');
        groupCol?.classList.add('d-none');
        contentCol?.classList.add('d-none');
      }else if(type === 'paquetes'){
        favFre?.classList.add('d-none');
        favPack?.classList.remove('d-none');
        groupCol?.classList.add('d-none');
        contentCol?.classList.add('d-none');
      }else{
        favFre?.classList.remove('d-none');
        favPack?.classList.remove('d-none');
        groupCol?.classList.remove('d-none');
        contentCol?.classList.remove('d-none');
      }
    };
    controller.filterList = function(term){
      const query = (term || '').trim().toLowerCase();
      const localCheckboxes = Array.from(modalEl.querySelectorAll('input[type="checkbox"][data-est-item]'));
      const labels = localCheckboxes.map(cb=> cb.closest('label')).filter(Boolean);
      labels.forEach(label=>{
        const text = (label.textContent || '').toLowerCase();
        label.classList.toggle('d-none', query && !text.includes(query));
      });
      modalEl.querySelectorAll('.est-lab-grid').forEach(grid=>{
        const hasVisible = Array.from(grid.querySelectorAll('label')).some(label=>!label.classList.contains('d-none'));
        grid.classList.toggle('d-none', !hasVisible);
        const head = grid.previousElementSibling;
        if(head && head.classList.contains('est-lab-sub')){
          head.classList.toggle('d-none', !hasVisible);
        }
      });
      modalEl.querySelectorAll('.accordion-item').forEach(item=>{
        const hasVisible = item.querySelectorAll('.est-lab-grid label:not(.d-none)').length > 0;
        item.classList.toggle('d-none', !hasVisible);
        if(query){
          const collapse = item.querySelector('.accordion-collapse');
          if(collapse) collapse.classList.add('show');
        }
      });
      groupPanes.forEach(pane=>{
        const hasVisible = pane.querySelectorAll('.est-lab-grid label:not(.d-none)').length > 0;
        pane.classList.toggle('d-none', !hasVisible);
      });
      if(modalityPanels.length){
        if(query){
          modalityPanels.forEach(panel=> panel.classList.remove('d-none'));
        } else if(controller.activeModality){
          modalityPanels.forEach(panel=>{
            panel.classList.toggle('d-none', panel.dataset.estModalityPanel !== controller.activeModality);
          });
        }
      }
      if(query){
        const contentCol = panelCol || accordionCol;
        groupCol?.classList.remove('d-none');
        contentCol?.classList.remove('d-none');
        favBox?.classList.remove('d-none');
        favFre?.classList.remove('d-none');
        favPack?.classList.remove('d-none');
        filterChips.forEach(ch=> ch.classList.remove('active'));
      }
    };
    controller.renderSelected = function(items){
      if(modalCount) modalCount.textContent = `(${items.length})`;
      if(!selectedWrap) return;
      if(!items.length){
        selectedWrap.innerHTML = '<span class="text-muted small">Sin selección</span>';
        return;
      }
      selectedWrap.innerHTML = items.map(item=>{
        const safeBase = escapeHtml(item);
        const display = escapeHtml(buildDisplayName(item, controller.key));
        return `<span class="est-chip">${display}<button type="button" class="est-chip-x" data-est-remove="${safeBase}" aria-label="Quitar ${safeBase}">&times;</button></span>`;
      }).join('');
    };
    controller.updateFlagVisibility = function(){
      if(!flagButtons.length) return;
      const visibilityMap = controller.key === 'lab'
        ? LAB_FLAG_VISIBILITY
        : (controller.key === 'endoscopia'
          ? ENDOSCOPY_FLAG_VISIBILITY
          : (controller.key === 'patologia'
            ? PATHOLOGY_FLAG_VISIBILITY
            : (controller.key === 'genetica'
              ? GENETICS_FLAG_VISIBILITY
              : (controller.key === 'funcionales'
                ? FUNCTIONAL_FLAG_VISIBILITY
                : (controller.key === 'cardiologia'
                  ? CARDIOLOGY_FLAG_VISIBILITY
                  : (controller.key === 'imagenologia' ? IMAGING_FLAG_VISIBILITY : null))))));
      if(!visibilityMap) return;
      const selectedIds = Array.from(modalEl.querySelectorAll('input[type="checkbox"][data-est-id]'))
        .filter(cb=> cb.checked)
        .map(cb=> cb.dataset.estId)
        .filter(Boolean);
      const visibleFlags = new Set(visibilityMap.generalFlags);
      selectedIds.forEach(id=>{
        (visibilityMap.applicability[id] || []).forEach(flag=> visibleFlags.add(flag));
      });
      flagButtons.forEach(btn=>{
        const id = btn.dataset.estFlag;
        const show = visibleFlags.has(id);
        btn.classList.toggle('d-none', !show);
        if(!show && activeFlags.has(id)){
          activeFlags.delete(id);
          btn.classList.remove('active');
        }
      });
    };
    controller.open = function(input){
      activeController = controller;
      activeInput = input;
      resetSelections();
      if(flagButtons.length){
        activeFlags.clear();
        flagButtons.forEach(btn=> btn.classList.remove('active'));
      }
      const parsed = getInputItems(activeInput);
      setSelectionOrder(parsed);
      parsed.forEach(item=> setItemChecked(item, true));
      if(searchInput) searchInput.value = '';
      controller.applyFilterChip('todos');
      controller.filterList('');
      renderSelected();
      modal.show();
    };
    if(modalityButtons.length){
      modalityButtons.forEach(btn=>{
        const id = btn.dataset.estModality;
        if(id && !modalityLabelMap[id]) modalityLabelMap[id] = (btn.textContent || '').trim();
      });
      controller.activeModality = modalityButtons.find(btn=>btn.classList.contains('active'))?.dataset.estModality || modalityButtons[0].dataset.estModality;
      controller.setActiveModality = (id)=>{
        if(!id) return;
        controller.activeModality = id;
        modalityButtons.forEach(btn=> btn.classList.toggle('active', btn.dataset.estModality === id));
        modalityPanels.forEach(panel=> panel.classList.toggle('d-none', panel.dataset.estModalityPanel !== id));
      };
      controller.setActiveModality(controller.activeModality);
      modalityButtons.forEach(btn=>{
        btn.addEventListener('click', ()=> controller.setActiveModality(btn.dataset.estModality));
      });
    }
    if(flagButtons.length){
      const flagGroups = {
        diagnostic_type: ['diagnostic','therapeutic'],
        priority: ['priority_routine','priority_urgent','priority_stat'],
        report_type: ['with_report','without_report'],
        contrast: ['with_contrast','without_contrast'],
        imaging_contrast: ['contrast_with','contrast_without','contrast_with_without'],
        laterality: ['right','left','bilateral'],
        imaging_laterality: ['laterality_right','laterality_left','laterality_bilateral'],
        sedation_type: ['with_sedation','without_sedation'],
        stress_state: ['rest','stress'],
        age_group: ['adult','pediatric'],
        care_setting: ['ambulatory','in_hospital'],
        holter_duration: ['holter_24h','holter_48h','holter_72h','holter_7d'],
        mapa_duration: ['mapa_24h','mapa_48h'],
        exercise_type: ['exercise_treadmill','exercise_bike'],
        stress_type: ['stress_exercise','stress_pharmacologic'],
        pharm_type: ['pharm_dobutamine','pharm_dipyridamole','pharm_adenosine'],
        rx_primary: ['rx_pa','rx_ap','rx_pa_lateral'],
        rx_lateral_combo: ['rx_lateral','rx_pa_lateral'],
        mammo_type: ['mammo_screening','mammo_diagnostic'],
        us_flow: ['us_arterial','us_venous'],
        vascular_flow: ['vascular_arterial','vascular_venous'],
        ct_phase: ['ct_phase_arterial','ct_phase_venous','ct_phase_delayed'],
        dexa_region: ['dexa_spine','dexa_hip','dexa_whole_body'],
        ihc_type: ['ihc_single_marker','ihc_panel','ihc_breast_panel','ihc_lung_panel','ihc_lymphoma_panel'],
        context_timing: ['context_prenatal','context_postnatal'],
        sample_type: ['sample_blood','sample_saliva','sample_tissue','sample_ffpe','sample_cvs','sample_amnio'],
        proband_group: ['trio','duo','proband_only'],
        limb_group: ['upper_limbs','lower_limbs']
      };
      const groupLookup = Object.values(flagGroups).reduce((acc, group)=>{
        group.forEach(id=>{
          if(!acc[id]) acc[id] = [];
          acc[id].push(group);
        });
        return acc;
      }, {});
      flagButtons.forEach(btn=>{
        btn.addEventListener('click', ()=>{
          const id = btn.dataset.estFlag;
          if(!id) return;
          const groups = groupLookup[id] || [];
          if(groups.length){
            const toClear = new Set();
            groups.forEach(group=>{
              group.forEach(flagId=>{
                if(flagId !== id) toClear.add(flagId);
              });
            });
            toClear.forEach(flagId=>{
              activeFlags.delete(flagId);
              flagButtons.forEach(b=>{
                if(b.dataset.estFlag === flagId) b.classList.remove('active');
              });
            });
          }
          if(activeFlags.has(id)){
            activeFlags.delete(id);
            btn.classList.remove('active');
          }else{
            activeFlags.add(id);
            btn.classList.add('active');
          }
          renderSelected();
        });
      });
    }
    pickButtons.forEach(btn=>{
      btn.addEventListener('click', ()=>{
        const key = btn.getAttribute('data-est-lab-pick');
        controller.applyPick(key);
      });
    });
    packButtons.forEach(btn=>{
      btn.addEventListener('click', ()=>{
        const key = btn.getAttribute('data-est-lab-pack');
        controller.applyPackage(key);
      });
    });
    groupButtons.forEach(btn=>{
      btn.addEventListener('click', ()=>{
        groupButtons.forEach(b=> b.classList.remove('active'));
        btn.classList.add('active');
        const group = btn.getAttribute('data-est-group');
        const pane = modalEl.querySelector(`[data-est-group-pane="${group}"]`);
        const collapse = pane?.querySelector('.accordion-collapse');
        if(collapse){ try{ new bootstrap.Collapse(collapse, {toggle:true}); }catch(_){ } }
        pane?.scrollIntoView({behavior:'smooth', block:'start'});
      });
    });
    filterChips.forEach(ch=> ch.addEventListener('click', ()=> controller.applyFilterChip(ch.dataset.estFilter)));
    searchInput?.addEventListener('input', (e)=> controller.filterList(e.target.value));
    selectedWrap?.addEventListener('click', (e)=>{
      const btn = e.target.closest('[data-est-remove]');
      if(!btn) return;
      const item = btn.getAttribute('data-est-remove');
      removeFromOrder(item);
      setItemChecked(item, false);
      renderSelected();
    });
    addBtn?.addEventListener('click', ()=>{
      const items = getOrderedItems();
      const input = activeInput || openInputs[0];
      if(input) setSelection(items, input, controller.key);
      modal.hide();
      setTimeout(scrollToOrderBlock, 200);
    });
    modalEl?.addEventListener('hidden.bs.modal', ()=> setModalMode('add', controller));
    controller.applyFilterChip('todos');
    controller.filterList('');
    return controller;
  }

  modalConfigs.forEach(cfg=>{
    const ctrl = createController(cfg);
    if(ctrl){
      controllers.push(ctrl);
      controllerMap[cfg.key] = ctrl;
    }
  });
  if(!controllers.length) return;

  controllers.forEach(ctrl=>{
    ctrl.groupPanes.forEach(pane=>{
      const id = pane.dataset.estGroupPane;
      if(!id) return;
      const label = pane.dataset.estGroupLabel
        || pane.querySelector('.accordion-header .accordion-button')?.textContent?.trim()
        || pane.querySelector('.est-lab-sub')?.textContent?.trim()
        || id;
      if(!groupLabels[id]) groupLabels[id] = label;
      if(!groupOrder.includes(id)) groupOrder.push(id);
      pane.querySelectorAll('input[type="checkbox"][data-est-item]').forEach(cb=>{
        const name = cb.dataset.estItem;
        if(name){
          if(!itemGroupMap[name]) itemGroupMap[name] = {};
          if(!itemGroupMap[name][ctrl.key]) itemGroupMap[name][ctrl.key] = id;
          if(!itemGroupMap[name].default) itemGroupMap[name].default = id;
        }
        if(name && itemOrder[name] == null) itemOrder[name] = orderIndex++;
        const modalityPanel = cb.closest('[data-est-modality-panel]');
        const modalityId = modalityPanel?.dataset.estModalityPanel;
        if(name && modalityId){
          if(!itemMetaMap[name]) itemMetaMap[name] = {};
          if(!itemMetaMap[name][ctrl.key]){
            const meta = { modality: modalityId, modalityLabel: modalityLabelMap[modalityId] || '' };
            itemMetaMap[name][ctrl.key] = meta;
            if(!itemMetaMap[name].default) itemMetaMap[name].default = meta;
          }
        }
      });
    });
  });
  Object.keys(searchConfigMap).forEach(key=>{
    const controller = controllerMap[key];
    if(controller) setupTypeahead(controller);
  });

  openInputs.forEach(input=>{
    input.addEventListener('focus', ()=> openControllerForInput(input));
    input.addEventListener('click', ()=> openControllerForInput(input));
    input.addEventListener('input', ()=>{
      input.dataset.estRaw = '';
      setSelection(parseInputValue(input.value), input);
    });
  });

  allCheckboxes.forEach(cb=>{
    cb.addEventListener('change', ()=>{
      const item = cb.dataset.estItem;
      const owner = controllers.find(ctrl=> ctrl.modalEl.contains(cb));
      syncDuplicates(item, cb.checked);
      if(cb.checked){
        addToOrder(item);
      }else{
        removeFromOrder(item);
      }
      renderSelected();
      if(owner?.searchInput && owner.searchInput.value.trim()){
        owner.searchInput.value = '';
        owner.applyFilterChip('todos');
        owner.filterList('');
        if(owner?.key && searchConfigMap[owner.key]) hideSuggest(owner.key);
      }
    });
  });

  summaryWrap?.addEventListener('click', (e)=>{
    const btn = e.target.closest('[data-est-remove]');
    if(!btn) return;
    const item = btn.getAttribute('data-est-remove');
    const input = activeInput || openInputs[0];
    const next = getInputItems(input).filter(i=>i !== item);
    setSelection(next, input);
  });
  summaryEdit?.addEventListener('click', ()=>{
    const input = activeInput || openInputs[0];
    if(input) openControllerForInput(input);
  });
  summaryClear?.addEventListener('click', ()=>{
    const input = activeInput || openInputs[0];
    const selected = getOrderedItems();
    if(selected.length){
      const confirmed = window.confirm('¿Limpiar la selección eliminará los estudios marcados y no podrás recuperarlos. ¿Deseas continuar?');
      if(!confirmed) return;
    }
    setSelection([], input);
  });

  orderList?.addEventListener('click', (e)=>{
    const btn = e.target.closest('[data-est-order-delete]');
    if(!btn) return;
    const card = btn.closest('.est-order-card');
    if(!card) return;
    if(window.confirm('¿Eliminar esta orden? Esta acción no se puede deshacer.')){
      card.remove();
    }
  });
  orderList?.addEventListener('click', (e)=>{
    const btn = e.target.closest('[data-est-order-edit]');
    if(!btn) return;
    const card = btn.closest('.est-order-card');
    if(!card) return;
    const items = parseInputValue(card.getAttribute('data-est-order-items') || '');
    const area = (card.getAttribute('data-est-order-area') || '').trim();
    const input = orderBlock?.querySelector('[data-est-open-modal]') || openInputs[0];
    if(!input) return;
    activeInput = input;
    const controller = getControllerForArea(area);
    if(area) setAreaSelect(area);
    if(area && !/laboratorio/i.test(area)){
      const key = ensureAreaGroup(area);
      const controllerKey = controller?.key;
      items.forEach(item=>{
        if(key){
          if(!itemGroupMap[item]) itemGroupMap[item] = {};
          if(controllerKey && !itemGroupMap[item][controllerKey]) itemGroupMap[item][controllerKey] = key;
          if(!itemGroupMap[item].default) itemGroupMap[item].default = key;
        }
        if(itemOrder[item] == null) itemOrder[item] = orderIndex++;
      });
    }
    setSelection(items, input, controller?.key);
    setModalMode('edit', controller);
    controller.open(input);
  });

  window.mxResetEstudios = ()=>{
    try{
      const input = openInputs[0];
      resetSelections();
      if(input){
        const defVal = input.defaultValue || '';
        input.dataset.estRaw = '';
        input.value = defVal;
        setSelection(parseInputValue(defVal), input);
      }
      if(areaSelect) areaSelect.selectedIndex = 0;
      controllers.forEach(ctrl=>{
        if(ctrl.searchInput) ctrl.searchInput.value = '';
        ctrl.applyFilterChip('todos');
        ctrl.filterList('');
        ctrl.flagButtons?.forEach(btn=> btn.classList.remove('active'));
        const inst = window.bootstrap?.Modal?.getInstance ? window.bootstrap.Modal.getInstance(ctrl.modalEl) : null;
        inst?.hide();
      });
      activeFlags.clear();
      if(orderList && orderListInitial){
        orderList.innerHTML = orderListInitial;
      }
      activeController = null;
      activeInput = null;
      modalMode = 'add';
      modalModeController = null;
    }catch(_){ }
  };

  renderSelected();
  if(openInputs[0]) setSelection(getInputItems(openInputs[0]), openInputs[0]);
})();


(function(){
  const desc = document.querySelector('[data-est-section-desc-target]');
  const tabs = Array.from(document.querySelectorAll('.est-section-tab[data-est-section]'));
  const sections = Array.from(document.querySelectorAll('[data-est-section-block]'));
  if(!desc || !tabs.length || !sections.length) return;
  const show = (key)=>{
    if(!key) return;
    const active = tabs.find(tab=> tab.dataset.estSection === key);
    tabs.forEach(tab=> tab.classList.toggle('active', tab === active));
    sections.forEach(section=> section.classList.toggle('d-none', section.dataset.estSectionBlock !== key));
    if(active?.dataset.estSectionDesc){
      desc.textContent = active.dataset.estSectionDesc;
    }
  };
  tabs.forEach(tab=>{
    tab.addEventListener('click', ()=> show(tab.dataset.estSection));
  });
  show(tabs[0].dataset.estSection);
})();
