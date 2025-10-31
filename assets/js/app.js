// ===== Extracted JS blocks from base HTML =====


/* ===== Helpers de navegación rápida ===== */
function jumpTo(panelId){
  showPanel(panelId);
  // marca activo el subbotón correspondiente, si existe
  $('.menu-sub-btn').removeClass('active');
  $('.menu-sub-btn[data-panel="'+panelId+'"]').addClass('active');
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
  }
});

/* Activación de subbotones y panel derecho */
function showPanel(id){
  $('#viewport > section').addClass('d-none');
  $('#'+id).removeClass('d-none');
}
$('.menu-sub-btn').on('click', function(){
  $('.menu-sub-btn').removeClass('active');
  $(this).addClass('active');
  const id = $(this).data('panel');
  if(id) showPanel(id);
  const grp = $(this).closest('.menu-sub').data('group');
  localStorage.setItem('mxmed_btn_'+grp, id);
  localStorage.setItem('mxmed_last_panel', id);
});

/* ===== Restaurar estado previo ===== */
$(function(){
  // Por defecto, mostrar RESUMEN
  const lastPanel = localStorage.getItem('mxmed_last_panel') || 'p-resumen';
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
      // Resalte visual solo en la elegida
      if(s.value){ s.classList.add('picked'); } else { s.classList.remove('picked'); }
      Array.from(s.options).forEach(o=>{
        if(!o.value || o.value.startsWith('Otra')) return o.disabled=false;
        o.disabled = vals.includes(o.value) && s.value !== o.value;
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
    let mark = col.querySelector(':scope > .save-ok');
    if(!mark){
      mark = document.createElement('span');
      mark.className = 'save-ok';
      mark.innerHTML = '<i class="bi bi-check2-circle"></i>';
      col.appendChild(mark);
    }
    return col;
  }

  function initAutosave(){
    document.querySelectorAll('#t-datos input.form-control, #t-datos select.form-select').forEach(ctrl=>{
      if(ctrl.type==='file') return;
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

// ===== Correcciones rápidas de acentos en header (muestra) =====
(function(){
  const t = document.querySelector('.optimo'); if(t) t.textContent = 'Óptimo';
  const n = document.querySelector('.name'); if(n && /Mu�oz|Muñoz/.test(n.textContent)) n.textContent = 'Leticia Muñoz Alfaro';
  const img = document.querySelector('.header-top img'); if(img) img.alt = 'México Médico';
  if(document.title && document.title.indexOf('MXMed')>=0) document.title = 'MXMed 2025 · Perfil Médico';
})();
