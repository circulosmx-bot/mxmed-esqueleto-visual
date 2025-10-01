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
    box.querySelector('.mf-choose').addEventListener('click', ()=>input.click());

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
    box.querySelector('.mf-qr').addEventListener('click', ()=>{
      const el = document.getElementById('modalQR');
      if(window.bootstrap && el){ new bootstrap.Modal(el).show(); }
    });
  });
})();
