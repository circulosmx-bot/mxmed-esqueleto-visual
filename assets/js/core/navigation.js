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

function activateFirstSub(group){
  const first = document.querySelector(`.menu-sub[data-group="${group}"] .menu-sub-btn[data-panel]`);
  if(!first) return;
  const panelId = first.getAttribute('data-panel');
  $('.menu-sub-btn').removeClass('active');
  first.classList.add('active');
  showPanel(panelId);
  localStorage.setItem('mxmed_btn_'+group, panelId);
  localStorage.setItem('mxmed_last_panel', panelId);
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
      $('.menu-main').removeClass('active');
      localStorage.removeItem('mxmed_menu_group');
    }else{
      openGroup(grp);
      activateFirstSub(grp);
      $('.menu-main').removeClass('active');
      $(this).addClass('active');
    }
  }
});

/* Activación de subbotones y panel derecho */
function showPanel(id){
  // Oculta todos los paneles, est1n o no dentro de #viewport
  $("section[id^=\"p-\"]").addClass('d-none');
  // Muestra el panel solicitado
  const pane = document.getElementById(id);
  if(pane){
    const vp = document.getElementById('viewport');
    const forcePanels = ['p-suscripcion','p-expediente','p-pac-archivo','p-pac-recetas','p-facturacion','p-paquetes','p-Notificaciones'];
    if(forcePanels.includes(id) && vp && pane.parentElement !== vp){
      vp.appendChild(pane);
    }
    pane.classList.remove('d-none');
    if(forcePanels.includes(id)){
      pane.style.display = 'block';
      pane.style.visibility = 'visible';
      pane.style.opacity = '1';
    } else {
      pane.style.display = '';
      pane.style.visibility = '';
      pane.style.opacity = '';
    }
    const tabs = pane.querySelectorAll('.nav-tabs [data-bs-toggle="tab"]');
    const activeTab = pane.querySelector('.nav-tabs .active');
    if(tabs.length && !activeTab){
      try{ new bootstrap.Tab(tabs[0]).show(); }catch(_){ }
    }
  }else{
    $('#'+id).removeClass('d-none');
  }
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
  // Forzar mostrar Actividad (RESUMEN) al recargar
  let lastPanel = 'p-resumen';
  localStorage.setItem('mxmed_last_panel', lastPanel);
  localStorage.removeItem('mxmed_menu_group');
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
  const $mainMatch = $('.menu-main[data-panel="'+lastPanel+'"]').first();
  if($mainMatch.length){ $('.menu-main').removeClass('active'); $mainMatch.addClass('active'); }

  // Restaurar pestaña interna de Información – Mi Perfil
  const lastInfoTab = localStorage.getItem('mxmed_info_tab') || '#t-datos';
  const tabTrigger = document.querySelector(`[data-bs-target="${lastInfoTab}"]`);
  if(tabTrigger){ new bootstrap.Tab(tabTrigger).show(); }
});

// Bloqueo de tabs deshabilitados en Expediente (no avanzar si faltan datos base)
$(function(){
  document.querySelectorAll('#p-expediente .mm-tabs-embed .nav-link').forEach(btn=>{
    btn.addEventListener('click', (ev)=>{
      if(btn.classList.contains('disabled')){
        ev.preventDefault();
        ev.stopPropagation();
      }
    });
  });
});

// Refuerzo: reubicar Suscripción en #viewport si quedó anidado
$(function(){
  const pane = document.getElementById('p-suscripcion');
  const vp = document.getElementById('viewport');
  if(pane && vp && pane.parentElement !== vp){
    vp.appendChild(pane);
  }
});
// Título dinámico en Pacientes según tab activa
(function(){
  const head = document.querySelector('#p-expediente .head h5');
  if (!head) return;
  const iconHTML = head.querySelector('.material-symbols-rounded')?.outerHTML || '';
  const prefix = '<span class="exp-prefix">Expediente Médico \u0007 </span>';
  const labels = {
    "t-datos": "Datos Generales",
    "t-historia": "Historia Clínica",
    "t-gineco": "Antecedentes Gineco-obstétricos",
    "t-exploracion": "Exploración Física",
    "t-estudios": "Estudios Diagnóstico",
    "t-tratamiento": "Tratamiento Recetas",
    "t-notas": "Notas Evolución",
    "t-manejo": "Manejo Hospitalario",
    "t-consent": "Consentimiento Informado",
    "t-archivo": "Archivo"
  };
  const nameSpan = document.querySelector('[data-paciente-nombre]');
  const nameInput = document.querySelector('[data-pac-nombre]');
  const apellidoP = document.querySelector('[data-pac-apellido-paterno]');
  const apellidoM = document.querySelector('[data-pac-apellido-materno]');
  const getPatientName = ()=>{
    const parts = [
      (nameInput?.value || '').trim(),
      (apellidoP?.value || '').trim(),
      (apellidoM?.value || '').trim()
    ].filter(Boolean);
    if(parts.length) return parts.join(' ');
    const fromSpan = (nameSpan?.textContent || '').trim();
    return fromSpan;
  };
  const setTitle = (id) => {
    if (!labels[id]) return;
    const patientName = getPatientName();
    const nameBadge = patientName ? `<div class="exp-name-badge">${patientName}</div>` : '';
    const nameRow = nameBadge ? `<div class="exp-name-row">${nameBadge}</div>` : '';
    head.innerHTML = `<div class="exp-title-row">${iconHTML}${prefix}<span class="exp-title">${labels[id]}</span></div>${nameRow}`;
  };
  document.querySelectorAll('#p-expediente .mm-tabs-row .nav-link').forEach(btn => {
    btn.addEventListener('shown.bs.tab', () => {
      const target = btn.getAttribute('data-bs-target') || '';
      setTitle(target.replace('#',''));
    });
  });
  const wireInputs = [nameInput, apellidoP, apellidoM].filter(Boolean);
  if(wireInputs.length){
    const onNameChange = ()=>{
      const active = document.querySelector('#p-expediente .mm-tabs-row .nav-link.active');
      const current = active?.getAttribute('data-bs-target')?.replace('#','') || 't-historia';
      setTitle(current);
    };
    wireInputs.forEach(inp=> inp.addEventListener('input', onNameChange));
  }
  const active = document.querySelector('#p-expediente .mm-tabs-row .nav-link.active');
  const initial = active?.getAttribute('data-bs-target')?.replace('#','') || 't-historia';
  setTitle(initial);
})();
