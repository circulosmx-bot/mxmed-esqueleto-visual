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
    document.querySelectorAll('input.form-control, select.form-select, textarea.form-control').forEach(ctrl=>{
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
        let hasVal = val !== '';
        // Género: no mostrar check si está sin seleccionar (opción placeholder)
        if(ctrl.id==='dp-genero'){
          try{
            hasVal = (ctrl.selectedIndex > 0) && (val !== '—');
          }catch(_){ hasVal = val !== '' && val !== '—'; }
        }
        if(col){ col.classList.toggle('saved', hasVal && !invalid); }
      };
      maybeMark();
      ctrl.addEventListener('input', ()=>{ maybeMark(); });
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
