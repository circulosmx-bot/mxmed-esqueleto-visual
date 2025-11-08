// ===== Extracted JS blocks from base HTML =====


/* ===== Helpers de navegación rápida ===== */
function jumpTo(panelId){
  showPanel(panelId);
  // marca activo el subbotón correspondiente, si existe
  $('.menu-sub-btn').removeClass('active');
  $('.menu-sub-btn[data-panel="'+panelId+'"]').addClass('active');
  // marcar activo el botón principal si es un panel directo
  $('.menu-main').removeClass('active');
  var $main = $('.menu-main[data-panel="'+panelId+'"]');
  if($main.length){ $main.addClass('active'); }
  localStorage.setItem('mxmed_last_panel', panelId);
}
function selectInfoTab(selector){
  const btn = document.querySelector('[data-bs-target="'+selector+'"]');
  if(btn){ new bootstrap.Tab(btn).show(); localStorage.setItem('mxmed_info_tab', selector); }
}
function selectPaqTab(selector){
  const btn = document.querySelector('[data-bs-target="'+selector+'"]');
  if(btn){ new bootstrap.Tab(btn).show(); }
}

/* ===== Acordeón exclusivo (sólo en grupos con submenú) ===== */
function openGroup(group){
  $('.menu-sub').removeClass('open').slideUp(100);
  const $t = $('.menu-sub[data-group="'+group+'"]');
  if($t.length){ $t.addClass('open').slideDown(100); }
  localStorage.setItem('mxmed_menu_group', group);
}

/* Click en menú principal */
$('.menu-main').on('click', function(){
  const panel = $(this).data('panel');   // panel directo
  const grp   = $(this).data('group');   // grupo acordeón

  if(panel){ // sin submenú: abrir panel directo
    $('.menu-sub').removeClass('open').slideUp(100);
    showPanel(panel);
    // activar este botón principal y desactivar los demás
    $('.menu-main').removeClass('active');
    $(this).addClass('active');
    localStorage.setItem('mxmed_last_panel', panel);
    localStorage.removeItem('mxmed_menu_group'); // ningún grupo abierto
  }else if(grp){ // con submenú (acordeón)
    const $pane = $('.menu-sub[data-group="'+grp+'"]');
    if($pane.hasClass('open')){
      $pane.removeClass('open').slideUp(100);
      localStorage.removeItem('mxmed_menu_group');
    }else{
      openGroup(grp);
    }
    // al trabajar con submenús, ningún botón principal queda activo
    $('.menu-main').removeClass('active');
  }
});

/* Activación de subbotones y panel derecho */
function showPanel(id){
  // Oculta todos los paneles, estén o no dentro de #viewport
  $('section[id^="p-"]').addClass('d-none');
  // Muestra el panel solicitado
  $('#'+id).removeClass('d-none');
}
$('.menu-sub-btn').on('click', function(){
  $('.menu-sub-btn').removeClass('active');
  $(this).addClass('active');
  // limpiar activos en botones principales cuando se usa submenú
  $('.menu-main').removeClass('active');
  const id = $(this).data('panel');
  if(id) showPanel(id);
  const grp = $(this).closest('.menu-sub').data('group');
  localStorage.setItem('mxmed_btn_'+grp, id);
  localStorage.setItem('mxmed_last_panel', id);
});

/* ===== Restaurar estado previo ===== */
$(function(){
  // Por defecto, mostrar RESUMEN
  let lastPanel = localStorage.getItem('mxmed_last_panel') || 'p-resumen';
  // Migración: renombrar p-mensajes -> p-notificaciones si viene de estado previo
  if(lastPanel === 'p-mensajes'){ lastPanel = 'p-notificaciones'; localStorage.setItem('mxmed_last_panel', lastPanel); }
  showPanel(lastPanel);

  // Si último panel pertenece a un grupo, abrirlo
  const groups = ['perfil','agenda','pacientes'];
  let opened = false;
  for(const g of groups){
    const p = localStorage.getItem('mxmed_btn_'+g);
    if(p && p === lastPanel){
      openGroup(g);
      $('.menu-sub-btn[data-panel="'+p+'"]').addClass('active');
      opened = true; break;
    }
  }
  if(!opened){
    $('.menu-sub').removeClass('open').hide();
  }

  // Si el último panel coincide con un botón principal directo, marcarlo activo
  const $mainMatch = $('.menu-main[data-panel="'+lastPanel+'"]');
  if($mainMatch.length){ $('.menu-main').removeClass('active'); $mainMatch.addClass('active'); }

  // Restaurar pestaña interna de Información · Mi Perfil
  const lastInfoTab = localStorage.getItem('mxmed_info_tab') || '#t-datos';
  const tabTrigger = document.querySelector(`[data-bs-target="${lastInfoTab}"]`);
  if(tabTrigger){ new bootstrap.Tab(tabTrigger).show(); }
});

/* === Nudge: sincroniza porcentaje y tareas sugeridas === */
(function(){
  var secciones = window.mxm_sec || { datos:58, prof:88, serv:0, enf:100, consul:50 };
  var vals = Object.values(secciones);
  var total = Math.round(vals.reduce((a,b)=>a+b,0) / vals.length);

  var CIRC = 2 * Math.PI * 42;
  var p = document.getElementById('nudgeProgress');
  if(p){
    p.setAttribute('stroke-dasharray', CIRC.toFixed(2));
    var off = CIRC * (1 - total/100);
    p.setAttribute('stroke-dashoffset', off.toFixed(2));
  }
  var lbl = document.getElementById('nudgeLabel');
  if(lbl){ lbl.textContent = total + '%'; }

  var list = document.getElementById('nudgeList');
  if(list){
    var items = [];
    if(secciones.consul < 100) items.push('Especificar consultorio');
    if(secciones.prof < 100) items.push('Indicar especialidades');
    if(secciones.serv < 100) items.push('Agregar servicios principales');
    if(secciones.datos < 100) items.push('Completar datos generales');
    list.innerHTML = items.slice(0,3).map(t => '<li>'+t+'</li>').join('');
  }

  var cta = document.getElementById('nudgeCTA');
  if(cta){
    cta.addEventListener('click', function(e){
      e.preventDefault();
      var minKey = Object.keys(secciones).sort((a,b)=>secciones[a]-secciones[b])[0];
      if(minKey === 'serv'){ openGroup('perfil'); jumpTo('p-info'); selectInfoTab('#t-servicios'); }
      else if(minKey === 'consul'){ openGroup('perfil'); jumpTo('p-consultorio'); }
      else { openGroup('perfil'); jumpTo('p-info'); selectInfoTab('#t-datos'); }
      window.scrollTo({top:0, behavior:'smooth'});
    });
  }

  var cls = document.getElementById('nudgeClose');
  if(cls){
    cls.addEventListener('click', function(){ this.closest('.nudge-card').remove(); });
  }
})();



(function(){
  const total = 59;
  const C = 2 * Math.PI * 42;
  const ring = document.getElementById('ringProgress');
  if(ring){
    ring.setAttribute('stroke-dasharray', C.toFixed(2));
    ring.setAttribute('stroke-dashoffset', (C * (1 - total/100)).toFixed(2));
  }
  const label = document.getElementById('donutLabel');
  if(label){ label.textContent = total + '%'; }

  function openModal(){
    const el = document.getElementById('modalCompletitud');
    if(window.bootstrap && el){ new bootstrap.Modal(el).show(); }
  }
  document.getElementById('donutTrigger')?.addEventListener('click', openModal);
  document.querySelector('.resumen-donut-caption-title')?.addEventListener('click', openModal);
  document.getElementById('btnCompletar')?.addEventListener('click', openModal);
})();



(function(){
  // Consultorio: modal para confirmar agregar otro consultorio
  document.getElementById('btn-consul-add')?.addEventListener('click', function(e){
    e.preventDefault();
    const el = document.getElementById('modalConsulAdd');
    if(window.bootstrap && el){ new bootstrap.Modal(el).show(); }
    else { if(window.confirm('¿Deseas agregar otro consultorio?')) {/* fallback */} }
  });
  function createSede2IfNeeded(){
    const nav = document.querySelector('#p-consultorio .mm-tabs-embed');
    const tabContent = document.querySelector('#p-consultorio .tab-content');
    if(!nav || !tabContent) return null;
    let pane2 = document.getElementById('sede2');
    let btn2 = document.querySelector('#p-consultorio [data-bs-target="#sede2"]');
    if(!pane2){
      const pane1 = document.getElementById('sede1');
      if(!pane1) return null;
      pane2 = pane1.cloneNode(true);
      pane2.id = 'sede2';
      pane2.classList.remove('show','active');
      // limpiar campos de formulario clonados
      pane2.querySelectorAll('input, textarea, select').forEach(el=>{
        if(el.tagName === 'SELECT'){ el.selectedIndex = 0; }
        else if(el.type === 'checkbox' || el.type === 'radio'){ el.checked = false; }
        else { el.value = ''; }
      });
      tabContent.appendChild(pane2);
    }
    if(!btn2){
      const li = document.createElement('li'); li.className='nav-item';
      const btn = document.createElement('button'); btn.className='nav-link'; btn.type='button';
      btn.setAttribute('data-bs-toggle','pill'); btn.setAttribute('data-bs-target','#sede2');
      btn.innerHTML = '<span class="tab-ico material-symbols-rounded" aria-hidden="true">apartment</span><span class="tab-lbl">CONSULTORIO 2</span>';
      const addLi = document.getElementById('btn-consul-add')?.closest('li');
      if(addLi){ nav.insertBefore(li, addLi); } else { nav.appendChild(li); }
      li.appendChild(btn); btn2 = btn;
    }
    // activar pestaña 2
    document.querySelectorAll('#p-consultorio .mm-tabs-embed .nav-link').forEach(b=>b.classList.remove('active'));
    document.querySelectorAll('#p-consultorio .tab-pane').forEach(p=>p.classList.remove('show','active'));
    pane2.classList.add('show','active');
    btn2.classList.add('active');
    if(window.bootstrap){ new bootstrap.Tab(btn2).show(); }
    return {pane2, btn2};
  }
  document.getElementById('modalConsulAddYes')?.addEventListener('click', function(){
    const el = document.getElementById('modalConsulAdd');
    if(window.bootstrap && el){ bootstrap.Modal.getInstance(el)?.hide(); }
    createSede2IfNeeded();
  });

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
      sel.innerHTML = '';
      const base = document.createElement('option'); base.value=''; base.textContent='Selecciona…'; sel.appendChild(base);
      (options||[]).forEach(name=>{
        const opt = document.createElement('option'); opt.value = name; opt.textContent = name; sel.appendChild(opt);
      });
      sel.disabled = !options || options.length === 0;
      if(!sel.disabled && sel.options.length>1) sel.selectedIndex = 1;
    }

    async function fetchSepomex(cpVal){
      // Llama a API SEPOMEX; intenta proxy local (PHP), luego directo y luego fallback CORS
      const proxyLocal = `./sepomex-proxy.php?cp=${cpVal}`;
      const direct = `https://api-sepomex.hckdrk.mx/query/info_cp/${cpVal}?type=simplified`;
      const fallback = `https://api.allorigins.win/raw?url=${encodeURIComponent(direct)}`;

      async function doFetch(url, raw){
        const res = await fetch(url, { cache:'no-store', mode:'cors' });
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

      try{
        // 1) Proxy local (evita CORS y oculta token si aplica)
        return await doFetch(proxyLocal, false);
      }catch(e1){
        try{ console.warn('[SEPOMEX] proxy local falló, probando directo', e1?.message||e1); }catch(_){ }
        try{
          // 2) Directo
          return await doFetch(direct, false);
        }catch(e2){
          try{ console.warn('[SEPOMEX] directo falló, intentando fallback', e2?.message||e2); }catch(_){ }
          try{ // 3) AllOrigins
            return await doFetch(fallback, true);
          }catch(e3){
          try{ console.error('[SEPOMEX] fallback también falló', e3?.message||e3); }catch(_){ }
            // 4) Fallback local (archivo estático para demo)
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
      }
    }

    async function onCpChange(){
      const val = (cp.value||'').trim();
      // valida 5 dígitos
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
        fillSelect(uniq); setMsg(fromLocal ? 'Usando datos locales de prueba' : ''); if(mun) mun.value = municipio||''; if(est) est.value = estado||'';
      }else{
        fillSelect([]); setMsg('Código postal no válido'); if(mun) mun.value=''; if(est) est.value='';
      }
    }

    cp.addEventListener('change', onCpChange);
    cp.addEventListener('input', ()=>{
      // mostrar estado de carga antes de llamar
      const v = (cp.value||'').trim();
      if(/^\d{5}$/.test(v)){
        sel.innerHTML = '<option value="">Buscando colonias…</option>'; sel.disabled = true; onCpChange();
      }
    });
    }

  // Activar en el primer consultorio (IDs base)
  setupCpAuto({ cp:'cp', colonia:'colonia', msg:'mensaje-cp', mun:'municipio', est:'estado' });

  // Si se crea Consultorio 2 dinámicamente, renombrar IDs y activar allí también
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
  $$('.mf-upload').forEach(box=>{
    const input = box.querySelector('.mf-input');
    const prev  = box.querySelector('.mf-prev');
    const img   = prev.querySelector('img');

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
      r.onload = ev => { img.src = ev.target.result; prev.style.display='block'; };
      r.readAsDataURL(file);
    }

    // QR (mock)
    const qrBtn = box.querySelector('.mf-qr');
    if(qrBtn){ qrBtn.addEventListener('click', ()=>{
      const el = document.getElementById('modalQR');
      if(window.bootstrap && el){ new bootstrap.Modal(el).show(); }
    }); }
  });
})();

// ===== Datos Personales: especialidades y validaciones =====
(function(){
  const T = [
    'Alergología','Análisis Clínicos','Anestesiología','Angiología y Cirugía Vascular','Audiología','Cardiología','Cirugía Bariátrica','Cirugía Cabeza y Cuello','Cirugía Cardiovascular','Cirugía de Columna','Cirugía de Mano','Cirugía de Pie','Cirugía Gastrointestinal','Cirugía General','Cirugía Laparoscópica','Cirugía Maxilofacial','Cirugía Oncológica Pediátrica','Cirugía Pediátrica','Cirugía Plástica','Cirugía Torácica','Coloproctología','Colposcopía','Cuidados Paliativos','Dentista','Dermatología','Diabetología','Endocrinología','Endodoncia','Estudios de Diagnóstico','Gastroenterología','Geriatría','Ginecología y Obstetricia','Hematología','Implantología Dental','Kinesiología','Medicina Crítica','Medicina del Trabajo','Medicina Estética','Medicina Familiar','Medicina Física y Rehabilitación','Medicina General','Medicina Integrada','Medicina Interna','Medicina Nuclear','Nefrología','Nefrología Pediátrica','Neumología','Neumología Pediátrica','Neurocirugía','Neurología','Neurología Pediátrica','Nutriología','Odontología','Odontopediatría','Oftalmología','Oncología','Optometría','Ortodoncia','Ortopedia Dental','Ortopedia y Traumatología','Otorrinolaringología','Patología','Pediatría','Podología','Proctología','Psicología','Psiquiatría','Radiología e Imagen','Reumatología','Urología','Otra (especificar)'
  ];

  function buildSelect(el){
    el.innerHTML = '';
    const optEmpty = document.createElement('option'); optEmpty.value=''; optEmpty.textContent='—'; el.appendChild(optEmpty);
    for(const t of T){ const o=document.createElement('option'); o.value=t; o.textContent=t; el.appendChild(o); }
  }

  function syncDuplicates(){
    const selects = Array.from(document.querySelectorAll('.esp-select'));
    const vals = selects.map(s=>s.value).filter(v=>v && !v.startsWith('Otra'));
    selects.forEach(s=>{
      // Resalte visual en el select que tiene valor
      if(s.value){ s.classList.add('picked'); } else { s.classList.remove('picked'); }
      Array.from(s.options).forEach(o=>{
        if(!o.value || o.value.startsWith('Otra')){ o.disabled=false; o.classList.remove('taken'); return; }
        const isTaken = vals.includes(o.value);
        // La opción tomada se marca en todas las persianas
        o.classList.toggle('taken', isTaken);
        // Deshabilitar en persianas distintas a la que la tiene seleccionada
        o.disabled = isTaken && s.value !== o.value;
      });
    });
  }

  function toggleOtra(){
    const wrap = document.getElementById('esp-otra-wrap');
    const s3 = document.getElementById('esp-3');
    if(!wrap || !s3) return;
    if(s3.value && s3.value.startsWith('Otra')) wrap.classList.remove('d-none');
    else wrap.classList.add('d-none');
  }

  // Insertar selects si existen anclas de correo en la vista de Datos
  const correo = document.getElementById('dp-correo');
  const row = correo?.closest('.row');
  if(row){
    ['esp-1','esp-2','esp-3'].forEach(id=>{
      const col = document.createElement('div'); col.className='col-md-4';
      const lab = document.createElement('label'); lab.className='form-label';
      lab.textContent = id==='esp-1' ? 'Especialidad Principal' : id==='esp-2' ? 'Especialidad Secundaria' : 'Otra Especialidad';
      const sel = document.createElement('select'); sel.className='form-select esp-select'; sel.id=id;
      col.appendChild(lab); col.appendChild(sel);
      row.insertBefore(col, correo.closest('.col-md-6'));
      buildSelect(sel);
      sel.addEventListener('change', ()=>{ syncDuplicates(); toggleOtra(); });
    });
    const wrap = document.createElement('div'); wrap.className='col-md-12 d-none'; wrap.id='esp-otra-wrap';
    const lab = document.createElement('label'); lab.className='form-label'; lab.textContent='Especifica otra especialidad';
    const inp = document.createElement('input'); inp.className='form-control'; inp.id='esp-otra'; inp.placeholder='Escribe la especialidad';
    wrap.appendChild(lab); wrap.appendChild(inp);
    row.insertBefore(wrap, correo.closest('.col-md-6'));
    syncDuplicates(); toggleOtra();
  }

  // Género: reemplaza "Otro" por "No Específico"
  const gen = document.getElementById('dp-genero');
  if(gen){ Array.from(gen.options).forEach(o=>{ if(/^otro$/i.test(o.textContent.trim())) o.textContent='No Específico'; }); }

  // Remueve campos no requeridos si quedaron (por contenido de etiqueta)
  function removeByLabel(text){
    document.querySelectorAll('.row .form-label').forEach(l=>{
      if(l.textContent && l.textContent.indexOf(text) >= 0){ const col = l.closest('[class^="col-"]'); col?.remove(); }
    });
  }
  ['Domicilio','Ciudad','País','Foto/Avatar','URL sitio personal'].forEach(removeByLabel);

  // Envolver WhatsApp con prefijo 🇲🇽 +52
  const w = document.getElementById('dp-whatsapp');
  if(w && !w.closest('.input-group')){
    const wrap = document.createElement('div'); wrap.className='input-group';
    const span = document.createElement('span'); span.className='input-group-text'; span.textContent='🇲🇽 +52';
    const col = w.closest('[class^="col-"]');
    col.replaceChildren();
    const lab = document.createElement('label'); lab.className='form-label'; lab.textContent='Teléfono Whatsapp';
    col.appendChild(lab);
    col.appendChild(wrap);
    wrap.appendChild(span);
    w.placeholder='10 dígitos'; w.maxLength=14; wrap.appendChild(w);
  }

  // Validación de correo y teléfono (básica) + tooltips
  const email = document.getElementById('dp-correo');
  // No usamos tooltip Bootstrap; renderizamos una burbuja propia dentro de save-wrap

  function setErrorTooltip(el, msg, isError){
    const col = el.closest('.save-wrap') || ensureSaveMark(el);
    if(!col) return;
    let bub = col.querySelector(':scope > .err-bubble');
    if(isError){
      el.classList.add('is-invalid');
      el.classList.remove('is-valid');
      col.classList.add('has-error');
      if(!bub){ bub = document.createElement('div'); bub.className='err-bubble'; col.appendChild(bub); }
      bub.textContent = msg;
    } else {
      el.classList.remove('is-invalid');
      el.classList.remove('is-valid');
      col.classList.remove('has-error');
      if(bub){ bub.remove(); }
    }
  }

  if(email){ email.type='email'; email.addEventListener('blur', ()=>{
    const ok = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim());
    setErrorTooltip(email, 'Ingresa un correo electrónico válido', (!!email.value && !ok));
  }); }
  if(w){ w.addEventListener('input', ()=>{
    const digits = (w.value||'').replace(/\D+/g,'');
    const ok = digits.length===10 || (digits.startsWith('52') && digits.length===12);
    setErrorTooltip(w, 'Ingresa un número de teléfono válido', (!!w.value && !ok));
  }); }

  // Autosave + check verde
  function ensureSaveMark(ctrl){
    const col = ctrl.closest('[class^="col-"]') || ctrl.parentElement;
    if(!col) return null;
    col.classList.add('save-wrap');
    // Determinar host (input-group o el propio input) para posicionar dentro del campo
    let host = ctrl.closest('.input-group');
    if(!host){
      if(!ctrl.parentElement.classList.contains('save-field')){
        const field = document.createElement('div'); field.className='save-field';
        ctrl.parentElement.insertBefore(field, ctrl);
        field.appendChild(ctrl);
        host = field;
      } else { host = ctrl.parentElement; }
    } else {
      host.classList.add('save-field');
    }
    // Crear/ubicar marca dentro del host
    let mark = host.querySelector(':scope > .save-ok');
    if(!mark){
      mark = document.createElement('span');
      mark.className = 'save-ok';
      mark.innerHTML = '<span class="material-symbols-outlined" aria-hidden="true">check_small</span>';
      host.appendChild(mark);
    }
    return col;
  }

  function initAutosave(){
    document.querySelectorAll('#viewport input.form-control, #viewport select.form-select, #viewport textarea.form-control').forEach(ctrl=>{
      if(ctrl.type==='file') return;
      // excluir campos de búsqueda u opt-out manual
      if(ctrl.type==='search' || ctrl.classList.contains('no-check') || ctrl.dataset.noCheck==='1') return;
      if(!ctrl.id){ ctrl.id = 'dp_auto_' + Math.random().toString(36).slice(2,8); }
      const col = ensureSaveMark(ctrl);
      const key = 'dp:'+ctrl.id;
      const saved = localStorage.getItem(key);
      if(saved!==null) ctrl.value = saved;
      const maybeMark = ()=>{
        const val = (ctrl.value||'').trim();
        let validByType = true;
        if(ctrl.id==='dp-correo'){
          validByType = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val);
        } else if(ctrl.id==='dp-whatsapp'){
          const digits = val.replace(/\D+/g,'');
          validByType = digits.length===10 || (digits.startsWith('52') && digits.length===12);
        }
        const invalid = ctrl.classList.contains('is-invalid') || !validByType;
        const hasVal = val !== '';
        if(col){ col.classList.toggle('saved', hasVal && !invalid); }
      };
      maybeMark();
      ctrl.addEventListener('change', ()=>{ localStorage.setItem(key, ctrl.value); maybeMark(); });
      ctrl.addEventListener('blur', ()=>{ localStorage.setItem(key, ctrl.value); maybeMark(); });
    });
  }
  initAutosave();
})();

// ===== Enfermedades y Tratamientos: inputs con chips (máx. 40) =====
(function(){
  const LIM = 40;
  function load(scope){ try { return JSON.parse(localStorage.getItem('chips:'+scope)||'[]'); } catch(e){ return []; } }
  function save(scope, arr){ localStorage.setItem('chips:'+scope, JSON.stringify(arr)); document.dispatchEvent(new CustomEvent('chips:refresh', {detail:{scope}})); }
  function setup(scope){
    const input = document.getElementById(scope+'-input');
    const btn   = document.getElementById(scope+'-add');
    const cnt   = document.getElementById(scope+'-count');
    const list  = document.getElementById(scope+'-list');
    if(!input || !btn || !cnt || !list) return;

    render();

    function updateCount(){
      const used = (input.value||'').length;
      const left = Math.max(0, LIM - used);
      cnt.textContent = left+"/"+LIM;
      const tooLong = used> LIM;
      setError(tooLong ? 'Máximo 40 caracteres. Ej.: "Cáncer de mama"' : '', tooLong);
      btn.disabled = tooLong || used===0;
      cnt.style.visibility = left < 10 ? 'visible' : 'hidden';
    }

    function setError(msg, isErr){
      // reusa burbuja propia
      let col = input.closest('.save-wrap');
      if(!col){ col = input.parentElement; }
      let bub = col.querySelector(':scope > .err-bubble');
      if(isErr){
        if(!bub){ bub = document.createElement('div'); bub.className='err-bubble'; col.appendChild(bub); }
        bub.textContent = msg;
        input.classList.add('is-invalid'); col.classList.add('has-error');
      }else{
        if(bub) bub.remove(); input.classList.remove('is-invalid'); col.classList.remove('has-error');
      }
    }

    function render(){
      const items = load(scope);
      list.innerHTML='';
      items.forEach((txt, i)=>{
        const chip = document.createElement('span'); chip.className='chip'; chip.textContent = txt;
        const x = document.createElement('button'); x.type='button'; x.className='chip-x'; x.setAttribute('aria-label','Eliminar'); x.textContent='×';
        x.addEventListener('click', ()=>{ const a=load(scope); a.splice(i,1); save(scope,a); render(); });
        chip.appendChild(x); list.appendChild(chip);
      });
      if(items.length>2){
        const link = document.createElement('a');
        link.href = '#';
        link.className = 'chip-sort-link';
        link.dataset.scope = scope;
        link.textContent = 'cambia el orden';
        list.appendChild(link);
      }
    }

    btn.addEventListener('click', ()=>{
      const val = (input.value||'').trim();
      if(!val || val.length> LIM){ updateCount(); return; }
      const a = load(scope); a.push(val); save(scope,a); render();
      input.value=''; updateCount();
    });
    input.addEventListener('input', updateCount);
    input.addEventListener('blur', updateCount);
    updateCount();
  }

  setup('enf');
  setup('trt');

  // Modal de ordenamiento
  const sortModalEl = document.getElementById('modalSortChips');
  const sortListEl = document.getElementById('sort-list');
  const sortSaveBtn = document.getElementById('sort-save');
  let sortScope = null; let temp = [];

  function renderSort(){
    sortListEl.innerHTML='';
    temp.forEach((t,i)=>{
      const li = document.createElement('li'); li.className='sort-item'; li.draggable=true; li.dataset.index=i;
      const h = document.createElement('span'); h.className='material-symbols-outlined sort-handle'; h.textContent='drag_indicator';
      const tx = document.createElement('span'); tx.textContent = t; tx.style.flex='1 1 auto';
      li.appendChild(h); li.appendChild(tx); sortListEl.appendChild(li);
    });
  }

  function bindDnD(){
    let dragIdx=null;
    sortListEl.addEventListener('dragstart', e=>{ const li=e.target.closest('.sort-item'); if(li){ dragIdx=+li.dataset.index; li.classList.add('dragging'); Array.from(sortListEl.querySelectorAll('.sort-item')).forEach(el=>{ if(el!==li) el.classList.add('dimmed'); }); e.dataTransfer.effectAllowed='move'; }});
    sortListEl.addEventListener('dragover', e=>{ e.preventDefault(); });
    sortListEl.addEventListener('drop', e=>{ e.preventDefault(); const li=e.target.closest('.sort-item'); if(li&& dragIdx!=null){ const dropIdx=+li.dataset.index; const it=temp.splice(dragIdx,1)[0]; temp.splice(dropIdx,0,it); renderSort(); bindDnD(); }});
    sortListEl.addEventListener('dragend', ()=>{ Array.from(sortListEl.children).forEach(el=>{ el.classList.remove('dragging'); el.classList.remove('dimmed'); }); });
  }

  document.addEventListener('click', (e)=>{
    const a = e.target.closest('.chip-sort-link');
    if(!a) return;
    e.preventDefault();
    sortScope = a.dataset.scope;
    temp = load(sortScope).slice();
    renderSort(); bindDnD();
    if(window.bootstrap && sortModalEl){ new bootstrap.Modal(sortModalEl).show(); }
  });

  sortSaveBtn?.addEventListener('click', ()=>{
    if(sortScope){ save(sortScope, temp); renderSort(); document.dispatchEvent(new Event('chips:refresh')); }
    const m = bootstrap.Modal.getInstance(sortModalEl); m?.hide();
  });

  document.addEventListener('chips:refresh', (ev)=>{
    // refrescar ambas listas al cambiar orden
    setup('enf'); setup('trt');
  });
})();

// ===== Servicios Principales: contador 50/50 por campo =====
(function(){
  const LIM = 50;
  ['srv1','srv2','srv3','srv4'].forEach(id=>{
    const input = document.getElementById(id);
    const cnt = document.getElementById(id+'-count');
    if(!input || !cnt) return;
    function update(){
      const used = (input.value||'').length;
      const left = Math.max(0, LIM - used);
      cnt.textContent = left + '/' + LIM;
      cnt.style.visibility = left < 10 ? 'visible' : 'hidden';
    }
    input.addEventListener('input', update);
    input.addEventListener('blur', update);
    update();
  });
})();

// ===== Mi Formación Profesional: resumen + chips (cert, cursos, diplomas, miembro) =====
(function(){
  // Poblar resumen desde localStorage (Datos Generales)
  const fsTitulo = document.getElementById('fs-titulo');
  const fsUni = document.getElementById('fs-uni');
  const fsEsp = document.getElementById('fs-esp');
  if(fsTitulo || fsUni || fsEsp){
    const esp1 = localStorage.getItem('dp:esp-1') || '';
    const uni = localStorage.getItem('dp:uni-prof') || localStorage.getItem('dp:uni-esp') || '';
    if(fsTitulo && esp1){ fsTitulo.textContent = 'Médico ' + (esp1.includes('Cirugía') ? 'Cirujano' : 'Especialista'); }
    if(fsUni && uni){ fsUni.textContent = uni; }
    if(fsEsp && esp1){ fsEsp.textContent = esp1; }
  }

  // Reutilizar lógica de chips para múltiples scopes
  function setupChips(scope, lim){
    const input = document.getElementById(scope+'-input');
    const btn   = document.getElementById(scope+'-add');
    const cnt   = document.getElementById(scope+'-count');
    const list  = document.getElementById(scope+'-list');
    if(!input || !btn || !cnt || !list) return;
    function load(){ try { return JSON.parse(localStorage.getItem('chips:'+scope)||'[]'); } catch(e){ return []; } }
    function save(arr){ localStorage.setItem('chips:'+scope, JSON.stringify(arr)); render(); }
    function update(){ const used=(input.value||'').length; const left=Math.max(0, lim-used); cnt.textContent=left+'/'+lim; cnt.style.visibility = left<10 ? 'visible':'hidden'; btn.disabled= used===0 || used>lim; }
    function render(){ list.innerHTML=''; load().forEach((txt,i)=>{ const chip=document.createElement('span'); chip.className='chip'; chip.textContent=txt; const x=document.createElement('button'); x.type='button'; x.className='chip-x'; x.textContent='×'; x.addEventListener('click',()=>{ const a=load(); a.splice(i,1); save(a); }); chip.appendChild(x); list.appendChild(chip); }); }
    btn.addEventListener('click', ()=>{ const v=(input.value||'').trim(); if(!v|| v.length>lim) return; const a=load(); a.push(v); save(a); input.value=''; update(); });
    input.addEventListener('input', update); input.addEventListener('blur', update);
    render(); update();
  }
  setupChips('cert', 50);
  setupChips('cursos', 50);
  setupChips('dipl', 50);
  setupChips('miem', 50);
})();

// ===== Correcciones rápidas de acentos en header (muestra) =====
(function(){
  const t = document.querySelector('.optimo'); if(t) t.textContent = 'Óptimo';
  const n = document.querySelector('.name'); if(n && /Mu�oz|Muñoz/.test(n.textContent)) n.textContent = 'Leticia Muñoz Alfaro';
  const img = document.querySelector('.header-top img'); if(img) img.alt = 'México Médico';
  if(document.title && document.title.indexOf('MXMed')>=0) document.title = 'MXMed 2025 · Perfil Médico';
})();
