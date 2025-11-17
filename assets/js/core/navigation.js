/* ===== Helpers de navegaci?n r?pida ===== */
function jumpTo(panelId){
  showPanel(panelId);
  // marca activo el subbot?n correspondiente, si existe
  $('.menu-sub-btn').removeClass('active');
  $('.menu-sub-btn[data-panel="'+panelId+'"]').addClass('active');
  // marcar activo el bot?n principal si es un panel directo
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

/* ===== Acorde?n exclusivo (s?lo en grupos con submen?) ===== */
function openGroup(group){
  $('.menu-sub').removeClass('open').slideUp(100);
  const $t = $('.menu-sub[data-group="'+group+'"]');
  if($t.length){ $t.addClass('open').slideDown(100); }
  localStorage.setItem('mxmed_menu_group', group);
}

/* Click en men? principal */
$('.menu-main').on('click', function(){
  const panel = $(this).data('panel');   // panel directo
  const grp   = $(this).data('group');   // grupo acorde?n

  if(panel){ // sin submen?: abrir panel directo
    $('.menu-sub').removeClass('open').slideUp(100);
    showPanel(panel);
    // activar este bot?n principal y desactivar los dem?s
    $('.menu-main').removeClass('active');
    $(this).addClass('active');
    localStorage.setItem('mxmed_last_panel', panel);
    localStorage.removeItem('mxmed_menu_group'); // ning?n grupo abierto
  }else if(grp){ // con submen? (acorde?n)
    const $pane = $('.menu-sub[data-group="'+grp+'"]');
    if($pane.hasClass('open')){
      $pane.removeClass('open').slideUp(100);
      localStorage.removeItem('mxmed_menu_group');
    }else{
      openGroup(grp);
    }
    // al trabajar con submen?s, ning?n bot?n principal queda activo
    $('.menu-main').removeClass('active');
  }
});

/* Activaci?n de subbotones y panel derecho */
function showPanel(id){
  // Oculta todos los paneles, est?n o no dentro de #viewport
  $('section[id^="p-"]').addClass('d-none');
  // Muestra el panel solicitado
  $('#'+id).removeClass('d-none');
}
$('.menu-sub-btn').on('click', function(){
  $('.menu-sub-btn').removeClass('active');
  $(this).addClass('active');
  // limpiar activos en botones principales cuando se usa submen?
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
  // Migraci?n: renombrar p-mensajes -> p-notificaciones si viene de estado previo
  if(lastPanel === 'p-mensajes'){ lastPanel = 'p-notificaciones'; localStorage.setItem('mxmed_last_panel', lastPanel); }
  showPanel(lastPanel);

  // Si ?ltimo panel pertenece a un grupo, abrirlo
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

  // Si el ?ltimo panel coincide con un bot?n principal directo, marcarlo activo
  const $mainMatch = $('.menu-main[data-panel="'+lastPanel+'"]');
  if($mainMatch.length){ $('.menu-main').removeClass('active'); $mainMatch.addClass('active'); }

  // Restaurar pesta?a interna de Informaci?n ? Mi Perfil
  const lastInfoTab = localStorage.getItem('mxmed_info_tab') || '#t-datos';
  const tabTrigger = document.querySelector(`[data-bs-target="${lastInfoTab}"]`);
  if(tabTrigger){ new bootstrap.Tab(tabTrigger).show(); }
});



