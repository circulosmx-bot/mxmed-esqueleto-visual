// ===== Extracted JS blocks from base HTML =====


/* ===== Helpers de navegaciÃ³n rÃ¡pida ===== */
function jumpTo(panelId){
  showPanel(panelId);
  // marca activo el subbotÃ³n correspondiente, si existe
  $('.menu-sub-btn').removeClass('active');
  $('.menu-sub-btn[data-panel="'+panelId+'"]').addClass('active');
  // marcar activo el botÃ³n principal si es un panel directo
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

/* ===== AcordeÃ³n exclusivo (sÃ³lo en grupos con submenÃº) ===== */
function openGroup(group){
  $('.menu-sub').removeClass('open').slideUp(100);
  const $t = $('.menu-sub[data-group="'+group+'"]');
  if($t.length){ $t.addClass('open').slideDown(100); }
  localStorage.setItem('mxmed_menu_group', group);
}

/* Click en menÃº principal */
$('.menu-main').on('click', function(){
  const panel = $(this).data('panel');   // panel directo
  const grp   = $(this).data('group');   // grupo acordeÃ³n

  if(panel){ // sin submenÃº: abrir panel directo
    $('.menu-sub').removeClass('open').slideUp(100);
    showPanel(panel);
    // activar este botÃ³n principal y desactivar los demÃ¡s
    $('.menu-main').removeClass('active');
    $(this).addClass('active');
    localStorage.setItem('mxmed_last_panel', panel);
    localStorage.removeItem('mxmed_menu_group'); // ningÃºn grupo abierto
  }else if(grp){ // con submenÃº (acordeÃ³n)
    const $pane = $('.menu-sub[data-group="'+grp+'"]');
    if($pane.hasClass('open')){
      $pane.removeClass('open').slideUp(100);
      localStorage.removeItem('mxmed_menu_group');
    }else{
      openGroup(grp);
    }
    // al trabajar con submenÃºs, ningÃºn botÃ³n principal queda activo
    $('.menu-main').removeClass('active');
  }
});

/* ActivaciÃ³n de subbotones y panel derecho */
function showPanel(id){
  // Oculta todos los paneles, estÃ©n o no dentro de #viewport
  $('section[id^="p-"]').addClass('d-none');
  // Muestra el panel solicitado
  $('#'+id).removeClass('d-none');
}
$('.menu-sub-btn').on('click', function(){
  $('.menu-sub-btn').removeClass('active');
  $(this).addClass('active');
  // limpiar activos en botones principales cuando se usa submenÃº
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
  // MigraciÃ³n: renombrar p-mensajes -> p-notificaciones si viene de estado previo
  if(lastPanel === 'p-mensajes'){ lastPanel = 'p-notificaciones'; localStorage.setItem('mxmed_last_panel', lastPanel); }
  showPanel(lastPanel);

  // Si Ãºltimo panel pertenece a un grupo, abrirlo
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

  // Si el Ãºltimo panel coincide con un botÃ³n principal directo, marcarlo activo
  const $mainMatch = $('.menu-main[data-panel="'+lastPanel+'"]');
  if($mainMatch.length){ $('.menu-main').removeClass('active'); $mainMatch.addClass('active'); }

  // Restaurar pestaÃ±a interna de InformaciÃ³n Â· Mi Perfil
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
    else { if(window.confirm('Â¿Deseas agregar otro consultorio?')) {/* fallback */} }
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
      // renombrar IDs para evitar duplicados
      const idMap = [
        ['cp','cp2'],['colonia','colonia2'],['mensaje-cp','mensaje-cp2'],['municipio','municipio2'],['estado','estado2'],
        ['cons-grupo-si','cons-grupo-si2'],['cons-grupo-no','cons-grupo-no2'],['cons-grupo-nombre','cons-grupo-nombre2'],['cons-titulo','cons-titulo2'],
        ['cons-calle','cons-calle2'],['cons-numext','cons-numext2'],['cons-numint','cons-numint2'],['cons-piso','cons-piso2'],
        ['cons-tel1','cons-tel12'],['cons-tel2','cons-tel22'],['cons-tel3','cons-tel32'],['cons-wa','cons-wa2'],['cons-wa-sync','cons-wa-sync2'],['cons-urg1','cons-urg12'],['cons-urg2','cons-urg22'],
        ['sched-body','sched-body-2'],['sched-copy-mon','sched-copy-mon-2'],['sched-clear','sched-clear-2'],
        ['cons-foto','cons-foto2'],['cons-foto-prev','cons-foto-prev2'],['cons-foto-img','cons-foto-img2'],
        ['cons-map','cons-map2'],['cons-map-frame','cons-map-frame2'],['cons-lat','cons-lat2'],['cons-lng','cons-lng2']
      ];
      idMap.forEach(([from,to])=>{ const el = pane2.querySelector('#'+from); if(el){ el.id = to; const lab = pane2.querySelector('label[for="'+from+'"]'); if(lab){ lab.setAttribute('for', to);} } });
      tabContent.appendChild(pane2);
    }
    if(!btn2){
      const li = document.createElement('li'); li.className='nav-item';
      const btn = document.createElement('button'); btn.className='nav-link'; btn.type='button';
      btn.setAttribute('data-bs-toggle','pill'); btn.setAttribute('data-bs-target','#sede2');
      btn.innerHTML = '<span class="tab-ico material-symbols-rounded" aria-hidden="true">apartment</span><span class="tab-lbl">SEGUNDO<br>CONSULTORIO</span>';
      const addLi = document.getElementById('btn-consul-add')?.closest('li');
      if(addLi){ nav.insertBefore(li, addLi); } else { nav.appendChild(li); }
      li.appendChild(btn); btn2 = btn;
    }
    // activar pestaÃ±a 2
    document.querySelectorAll('#p-consultorio .mm-tabs-embed .nav-link').forEach(b=>b.classList.remove('active'));
    document.querySelectorAll('#p-consultorio .tab-pane').forEach(p=>p.classList.remove('show','active'));
    pane2.classList.add('show','active');
    btn2.classList.add('active');
    if(window.bootstrap){ new bootstrap.Tab(btn2).show(); }
    // inicializar en pane2
    try{ setupCpAuto({ cp:'cp2', colonia:'colonia2', msg:'mensaje-cp2', mun:'municipio2', est:'estado2' }); }catch(_){ }
    try{ const cp2=document.getElementById('cp2'), col2=document.getElementById('colonia2'); if(cp2&&col2){ cp2.addEventListener('blur', ()=>{ col2.focus(); }); } }catch(_){ }
    try{ const cont=pane2; const phones=cont.querySelectorAll('[data-validate="phone"]'); const okp=v=>{const d=(v||'').replace(/[^0-9]/g,''); return d.length>=7&&d.length<=15;}; phones.forEach(el=>{ const apply=()=>{ const ok=okp(el.value); el.classList.toggle('is-invalid',!ok); el.setCustomValidity(ok?'':'TelÃ©fono invÃ¡lido'); }; el.addEventListener('input',apply); el.addEventListener('blur',apply); apply(); }); }catch(_){ }
    try{ const wa=document.getElementById('cons-wa2'), cb=document.getElementById('cons-wa-sync2'), dg=document.getElementById('dp-whatsapp'); if(cb&&wa){ const fill=()=>{ if(dg){ wa.value=dg.value||''; wa.dispatchEvent(new Event('input')); } }; const toggle=()=>{ if(cb.checked){ wa.disabled=true; wa.placeholder='+52 ...'; fill(); } else { wa.disabled=false; wa.value=''; wa.placeholder='otro numero Whatsapp'; } }; cb.addEventListener('change',toggle); if(dg) dg.addEventListener('input',()=>{ if(cb.checked) fill(); }); toggle(); } }catch(_){ }
    try{ if(window._mx_setupSchedulesFor){ window._mx_setupSchedulesFor(pane2,'-2'); } }catch(_){ }
    try{ const frame=document.getElementById('cons-map-frame2'); if(frame){ const addr=()=>{ const cp=(document.getElementById('cp2')?.value||'').trim(); const col=(document.getElementById('colonia2')?.value||'').trim(); const mun=(document.getElementById('municipio2')?.value||'').trim(); const edo=(document.getElementById('estado2')?.value||'').trim(); const calle=(document.getElementById('cons-calle2')?.value||'').trim(); const num=(document.getElementById('cons-numext2')?.value||'').trim(); const a=[calle&&(calle+(num?' '+num:'')),col,cp,mun,edo,'M\u00E9xico'].filter(Boolean).join(', '); return a; }; const upd=()=>{ const a=addr(); if(!a) return; const url='https://www.google.com/maps?q='+encodeURIComponent(a)+'&z=17&output=embed'; if(frame.src!==url) frame.src=url; }; ['cp2','colonia2','cons-calle2','cons-numext2'].forEach(id=>{ const el=document.getElementById(id); if(el){ el.addEventListener('input',upd); el.addEventListener('change',upd);} }); } }catch(_){ }
    return {pane2, btn2};
  }

  // Generalizado: crear consultorio N (2..3)
  function createConsultorio(n){
    if(n < 2) return null; if(n > 3){ alert('Puedes registrar hasta 3 consultorios.'); return null; }
    const nav = document.querySelector('#p-consultorio .mm-tabs-embed');
    const tabContent = document.querySelector('#p-consultorio .tab-content');
    if(!nav || !tabContent) return null;
    let pane = document.getElementById('sede'+n);
    let btn  = document.querySelector(`#p-consultorio [data-bs-target="#sede${n}"]`);
    if(!pane){
      const tpl = document.getElementById('sede1'); if(!tpl) return null;
      pane = tpl.cloneNode(true); pane.id = 'sede'+n; pane.classList.remove('show','active');
      // limpiar inputs
      pane.querySelectorAll('input, textarea, select').forEach(el=>{
        if(el.tagName === 'SELECT'){ el.selectedIndex = 0; }
        else if(el.type === 'checkbox' || el.type === 'radio'){ el.checked = false; }
        else { el.value = ''; }
      });
      // renombrar ids con sufijo n
      const sfx = String(n);
      const ids = ['cp','colonia','mensaje-cp','municipio','estado','cons-grupo-si','cons-grupo-no','cons-grupo-nombre','cons-titulo','cons-calle','cons-numext','cons-numint','cons-piso','cons-tel1','cons-tel2','cons-tel3','cons-wa','cons-wa-sync','cons-urg1','cons-urg2','sched-body','sched-copy-mon','sched-clear','cons-foto','cons-foto-prev','cons-foto-img','cons-map','cons-map-frame','cons-lat','cons-lng'];
      ids.forEach(base=>{ const el = pane.querySelector('#'+base); if(el){ el.id = base + (base==='sched-body'||base==='sched-copy-mon'||base==='sched-clear' ? '-'+sfx : sfx); const lab = pane.querySelector(`label[for="${base}"]`); if(lab) lab.setAttribute('for', el.id); } });
      tabContent.appendChild(pane);
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
    // deshabilitar botÃ³n agregar si ya existen 3
    const count = document.querySelectorAll('#p-consultorio .tab-pane[id^="sede"]').length;
    if(count >= 3){ const addBtn=document.getElementById('btn-consul-add'); if(addBtn){ addBtn.classList.add('disabled'); addBtn.setAttribute('aria-disabled','true'); addBtn.title='MÃ¡ximo 3 consultorios'; } }
    return {pane, btn};
  }
  window._mx_createConsultorio = createConsultorio;

  // Eliminar consultorio con confirmaciÃ³n (demo: acepta cÃ³digo 123456 o pass 'codex')
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
        // Modo pruebas: si hay valor no vacÃ­o, aceptar.
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
    // re-habilitar botÃ³n agregar si estaba bloqueado
    const addBtn=document.getElementById('btn-consul-add'); if(addBtn){ addBtn.classList.remove('disabled'); addBtn.removeAttribute('aria-disabled'); addBtn.title=''; }
  });
  function nextConsultorioIndex(){
    const panes = Array.from(document.querySelectorAll('#p-consultorio .tab-pane[id^="sede"]'));
    let max = 0; panes.forEach(p=>{ const m = /sede(\d+)/.exec(p.id); const n = m? parseInt(m[1],10) : 0; if(n>max) max=n; });
    return max ? max+1 : 2;
  }
  document.getElementById('modalConsulAddYes')?.addEventListener('click', function(){
    const el = document.getElementById('modalConsulAdd');
    if(window.bootstrap && el){ bootstrap.Modal.getInstance(el)?.hide(); }
    const next = nextConsultorioIndex();
    if(window._mx_createConsultorio) window._mx_createConsultorio(next); else createSede2IfNeeded();
    try{ initAutosave(); }catch(_){ }
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
      // Limpia y coloca placeholder
      sel.innerHTML = '';
      const base = document.createElement('option'); base.value=''; base.textContent='Selecciona\u2026'; sel.appendChild(base);
      // Agrega colonias
      (options||[]).forEach(name=>{
        const opt = document.createElement('option'); opt.value = name; opt.textContent = name; sel.appendChild(opt);
      });
      const has = !!options && options.length > 0;
      // Habilita/deshabilita de forma explÃ­cita (prop y atributo)
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

      // 1) Si hay cache localStorage, Ãºsalo de inmediato y refresca en background
      const cacheKey = 'sepomex_cp_'+cpVal;
      try{
        const cached = JSON.parse(localStorage.getItem(cacheKey)||'null');
        if(cached && Array.isArray(cached.list) && cached.list.length){
          // Disparar refresh en background pero devolver rÃ¡pido
          refreshOnline();
          return cached;
        }
      }catch(_){ }

      // 2) Para primera respuesta mÃ¡s rÃ¡pida: hacer intentos en paralelo y tomar el primero
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
        // 3) Fallback local (archivo estÃ¡tico para demo)
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
      // valida 5 dÃ­gitos
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
        sel.innerHTML = '<option value="">Buscando coloniasâ€¦</option>'; sel.disabled = true; onCpChange();
      }
    });
    }

  // Activar en el primer consultorio (IDs base)
  setupCpAuto({ cp:'cp', colonia:'colonia', msg:'mensaje-cp', mun:'municipio', est:'estado' });

  // Si se crea Consultorio 2 dinÃ¡micamente, renombrar IDs y activar allÃ­ tambiÃ©n
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
    'AlergologÃ­a','AnÃ¡lisis ClÃ­nicos','AnestesiologÃ­a','AngiologÃ­a y CirugÃ­a Vascular','AudiologÃ­a','CardiologÃ­a','CirugÃ­a BariÃ¡trica','CirugÃ­a Cabeza y Cuello','CirugÃ­a Cardiovascular','CirugÃ­a de Columna','CirugÃ­a de Mano','CirugÃ­a de Pie','CirugÃ­a Gastrointestinal','CirugÃ­a General','CirugÃ­a LaparoscÃ³pica','CirugÃ­a Maxilofacial','CirugÃ­a OncolÃ³gica PediÃ¡trica','CirugÃ­a PediÃ¡trica','CirugÃ­a PlÃ¡stica','CirugÃ­a TorÃ¡cica','ColoproctologÃ­a','ColposcopÃ­a','Cuidados Paliativos','Dentista','DermatologÃ­a','DiabetologÃ­a','EndocrinologÃ­a','Endodoncia','Estudios de DiagnÃ³stico','GastroenterologÃ­a','GeriatrÃ­a','GinecologÃ­a y Obstetricia','HematologÃ­a','ImplantologÃ­a Dental','KinesiologÃ­a','Medicina CrÃ­tica','Medicina del Trabajo','Medicina EstÃ©tica','Medicina Familiar','Medicina FÃ­sica y RehabilitaciÃ³n','Medicina General','Medicina Integrada','Medicina Interna','Medicina Nuclear','NefrologÃ­a','NefrologÃ­a PediÃ¡trica','NeumologÃ­a','NeumologÃ­a PediÃ¡trica','NeurocirugÃ­a','NeurologÃ­a','NeurologÃ­a PediÃ¡trica','NutriologÃ­a','OdontologÃ­a','OdontopediatrÃ­a','OftalmologÃ­a','OncologÃ­a','OptometrÃ­a','Ortodoncia','Ortopedia Dental','Ortopedia y TraumatologÃ­a','OtorrinolaringologÃ­a','PatologÃ­a','PediatrÃ­a','PodologÃ­a','ProctologÃ­a','PsicologÃ­a','PsiquiatrÃ­a','RadiologÃ­a e Imagen','ReumatologÃ­a','UrologÃ­a','Otra (especificar)'
  ];

  function buildSelect(el){
    el.innerHTML = '';
    const optEmpty = document.createElement('option'); optEmpty.value=''; optEmpty.textContent='â€”'; el.appendChild(optEmpty);
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
        // La opciÃ³n tomada se marca en todas las persianas
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

  // GÃ©nero: reemplaza "Otro" por "No EspecÃ­fico"
  const gen = document.getElementById('dp-genero');
  if(gen){ Array.from(gen.options).forEach(o=>{ if(/^otro$/i.test(o.textContent.trim())) o.textContent='No EspecÃ­fico'; }); }

  // Remueve campos no requeridos si quedaron (por contenido de etiqueta)
  function removeByLabel(text){
    document.querySelectorAll('.row .form-label').forEach(l=>{
      if(l.textContent && l.textContent.indexOf(text) >= 0){ const col = l.closest('[class^="col-"]'); col?.remove(); }
    });
  }
  ['Domicilio','Ciudad','PaÃ­s','Foto/Avatar','URL sitio personal'].forEach(removeByLabel);

  // Envolver WhatsApp con prefijo ðŸ‡²ðŸ‡½ +52
  const w = document.getElementById('dp-whatsapp');
  if(w && !w.closest('.input-group')){
    const wrap = document.createElement('div'); wrap.className='input-group';
    const span = document.createElement('span'); span.className='input-group-text'; span.textContent='ðŸ‡²ðŸ‡½ +52';
    const col = w.closest('[class^="col-"]');
    col.replaceChildren();
    const lab = document.createElement('label'); lab.className='form-label'; lab.textContent='TelÃ©fono Whatsapp';
    col.appendChild(lab);
    col.appendChild(wrap);
    wrap.appendChild(span);
    w.placeholder='10 dÃ­gitos'; w.maxLength=14; wrap.appendChild(w);
  }

  // ValidaciÃ³n de correo y telÃ©fono (bÃ¡sica) + tooltips
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
    setErrorTooltip(email, 'Ingresa un correo electrÃ³nico vÃ¡lido', (!!email.value && !ok));
  }); }
  if(w){ w.addEventListener('input', ()=>{
    const digits = (w.value||'').replace(/\D+/g,'');
    const ok = digits.length===10 || (digits.startsWith('52') && digits.length===12);
    setErrorTooltip(w, 'Ingresa un nÃºmero de telÃ©fono vÃ¡lido', (!!w.value && !ok));
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
      // excluir campos de bÃºsqueda u opt-out manual
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
        // GÃ©nero: no mostrar check si estÃ¡ sin seleccionar (opciÃ³n placeholder)
        if(ctrl.id==='dp-genero'){
          try{
            hasVal = (ctrl.selectedIndex > 0) && (val !== 'â€”');
          }catch(_){ hasVal = val !== '' && val !== 'â€”'; }
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

// ===== Enfermedades y Tratamientos: inputs con chips (mÃ¡x. 40) =====
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
      setError(tooLong ? 'MÃ¡ximo 40 caracteres. Ej.: "CÃ¡ncer de mama"' : '', tooLong);
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
        const x = document.createElement('button'); x.type='button'; x.className='chip-x'; x.setAttribute('aria-label','Eliminar'); x.textContent='Ã—';
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

// ===== Mi FormaciÃ³n Profesional: resumen + chips (cert, cursos, diplomas, miembro) =====
(function(){
  // Poblar resumen desde localStorage (Datos Generales)
  const fsTitulo = document.getElementById('fs-titulo');
  const fsUni = document.getElementById('fs-uni');
  const fsEsp = document.getElementById('fs-esp');
  if(fsTitulo || fsUni || fsEsp){
    const esp1 = localStorage.getItem('dp:esp-1') || '';
    const uni = localStorage.getItem('dp:uni-prof') || localStorage.getItem('dp:uni-esp') || '';
    if(fsTitulo && esp1){ fsTitulo.textContent = 'MÃ©dico ' + (esp1.includes('CirugÃ­a') ? 'Cirujano' : 'Especialista'); }
    if(fsUni && uni){ fsUni.textContent = uni; }
    if(fsEsp && esp1){ fsEsp.textContent = esp1; }
  }

  // Reutilizar lÃ³gica de chips para mÃºltiples scopes
  function setupChips(scope, lim){
    const input = document.getElementById(scope+'-input');
    const btn   = document.getElementById(scope+'-add');
    const cnt   = document.getElementById(scope+'-count');
    const list  = document.getElementById(scope+'-list');
    if(!input || !btn || !cnt || !list) return;
    function load(){ try { return JSON.parse(localStorage.getItem('chips:'+scope)||'[]'); } catch(e){ return []; } }
    function save(arr){ localStorage.setItem('chips:'+scope, JSON.stringify(arr)); render(); }
    function update(){ const used=(input.value||'').length; const left=Math.max(0, lim-used); cnt.textContent=left+'/'+lim; cnt.style.visibility = left<10 ? 'visible':'hidden'; btn.disabled= used===0 || used>lim; }
    function render(){ list.innerHTML=''; load().forEach((txt,i)=>{ const chip=document.createElement('span'); chip.className='chip'; chip.textContent=txt; const x=document.createElement('button'); x.type='button'; x.className='chip-x'; x.textContent='Ã—'; x.addEventListener('click',()=>{ const a=load(); a.splice(i,1); save(a); }); chip.appendChild(x); list.appendChild(chip); }); }
    btn.addEventListener('click', ()=>{ const v=(input.value||'').trim(); if(!v|| v.length>lim) return; const a=load(); a.push(v); save(a); input.value=''; update(); });
    input.addEventListener('input', update); input.addEventListener('blur', update);
    render(); update();
  }
  setupChips('cert', 50);
  setupChips('cursos', 50);
  setupChips('dipl', 50);
  setupChips('miem', 50);
})();

// ====== Consultorio: horarios, foto preview, mapa (fallback) ======
(function(){
  const body = document.getElementById('sched-body');
  if(body){
    const dias = [
      {k:'mon', lbl:'Lunes'}, {k:'tue', lbl:'Martes'}, {k:'wed', lbl:'MiÃ©rcoles'},
      {k:'thu', lbl:'Jueves'}, {k:'fri', lbl:'Viernes'}, {k:'sat', lbl:'SÃ¡bado'}, {k:'sun', lbl:'Domingo'}
    ];
    const key = 'mxmed_cons_schedules';
    function load(){ try { return JSON.parse(localStorage.getItem(key)||'{}'); } catch(e){ return {}; } }
    function save(v){ localStorage.setItem(key, JSON.stringify(v)); }
    const state = load();
    dias.forEach(d=>{
      const tr = document.createElement('tr');
      tr.innerHTML = `<td>${d.lbl}</td>
      <td><input type="checkbox" class="form-check-input" id="sch-act-${d.k}"></td>
      <td>
        <div class="d-flex align-items-center gap-1">
          <input type="time" class="form-control form-control-sm" id="sch-a1-${d.k}">
          <span>â€“</span>
          <input type="time" class="form-control form-control-sm" id="sch-b1-${d.k}">
        </div>
      </td>
      <td>
        <div class="d-flex align-items-center gap-1">
          <input type="time" class="form-control form-control-sm" id="sch-a2-${d.k}">
          <span>â€“</span>
          <input type="time" class="form-control form-control-sm" id="sch-b2-${d.k}">
        </div>
      </td>`;
      body.appendChild(tr);
      const act = tr.querySelector(`#sch-act-${d.k}`);
      const a1 = tr.querySelector(`#sch-a1-${d.k}`);
      const b1 = tr.querySelector(`#sch-b1-${d.k}`);
      const a2 = tr.querySelector(`#sch-a2-${d.k}`);
      const b2 = tr.querySelector(`#sch-b2-${d.k}`);
      const sv = state[d.k] || {};
      act.checked = !!sv.act; a1.value = sv.a1||''; b1.value = sv.b1||''; a2.value = sv.a2||''; b2.value = sv.b2||'';
      function sync(){ state[d.k] = { act:act.checked, a1:a1.value, b1:b1.value, a2:a2.value, b2:b2.value }; save(state); }
      [act,a1,b1,a2,b2].forEach(el=> el.addEventListener('change', sync));
    });
    document.getElementById('sched-copy-mon')?.addEventListener('click', ()=>{
      const m = state.mon || {}; ['tue','wed','thu','fri','sat','sun'].forEach(k=>{ state[k] = {...m}; }); save(state); location.reload();
    });
    document.getElementById('sched-clear')?.addEventListener('click', ()=>{ localStorage.removeItem(key); location.reload(); });
  }

  const file = document.getElementById('cons-foto');
  if(file){
    file.addEventListener('change', (e)=>{
      const f = e.target.files && e.target.files[0]; if(!f) return;
      const rd = new FileReader(); rd.onload = ev => {
        const img = document.getElementById('cons-foto-img'); const box = document.getElementById('cons-foto-prev');
        if(img && box){ img.src = ev.target.result; box.style.display='block'; }
      }; rd.readAsDataURL(f);
    });
  }

  // Utilidad: construir texto de direcciÃ³n
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
    if(!(window.L && typeof L.map === 'function')) return; // si no hay Leaflet, usamos iframe fallback mÃ¡s abajo
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
      }catch(_){ /* si falla Leaflet en este pane, el iframe fallback lo cubrirÃ¡ */ }
    });
  })();

  // Fallback automÃ¡tico: actualizar iframe de Google Maps con la direcciÃ³n
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
      const dias=[{k:'mon',lbl:'Lunes'},{k:'tue',lbl:'Martes'},{k:'wed',lbl:'MiÃ©rcoles'},{k:'thu',lbl:'Jueves'},{k:'fri',lbl:'Viernes'},{k:'sat',lbl:'SÃ¡bado'},{k:'sun',lbl:'Domingo'}];
      const key='mxmed_cons_schedules'+(keySuffix||'');
      const load=()=>{ try{return JSON.parse(localStorage.getItem(key)||'{}');}catch(e){return{}} };
      const save=v=>localStorage.setItem(key,JSON.stringify(v));
      const state=load();
      dias.forEach(d=>{
        const tr=document.createElement('tr');
        tr.innerHTML=`<td>${d.lbl}</td><td><input type=\"checkbox\" class=\"form-check-input\" id=\"sch-act-${d.k}${keySuffix||''}\"></td><td><div class=\"d-flex align-items-center gap-1\"><input type=\"time\" class=\"form-control form-control-sm\" id=\"sch-a1-${d.k}${keySuffix||''}\"><span>â€“</span><input type=\"time\" class=\"form-control form-control-sm\" id=\"sch-b1-${d.k}${keySuffix||''}\"></div></td><td><div class=\"d-flex align-items-center gap-1\"><input type=\"time\" class=\"form-control form-control-sm\" id=\"sch-a2-${d.k}${keySuffix||''}\"><span>â€“</span><input type=\"time\" class=\"form-control form-control-sm\" id=\"sch-b2-${d.k}${keySuffix||''}\"></div></td>`;
        body.appendChild(tr);
        const act=tr.querySelector(`#sch-act-${d.k}${keySuffix||''}`);
        const a1=tr.querySelector(`#sch-a1-${d.k}${keySuffix||''}`);
        const b1=tr.querySelector(`#sch-b1-${d.k}${keySuffix||''}`);
        const a2=tr.querySelector(`#sch-a2-${d.k}${keySuffix||''}`);
        const b2=tr.querySelector(`#sch-b2-${d.k}${keySuffix||''}`);
        const sv=state[d.k]||{};
        act.checked=!!sv.act; a1.value=sv.a1||''; b1.value=sv.b1||''; a2.value=sv.a2||''; b2.value=sv.b2||'';
        const sync=()=>{ state[d.k]={act:act.checked,a1:a1.value,b1:b1.value,a2:a2.value,b2:b2.value}; save(state); };
        [act,a1,b1,a2,b2].forEach(el=> el.addEventListener('change', sync));
      });
      const copyBtn = container.querySelector('#sched-copy-mon-2');
      const clearBtn= container.querySelector('#sched-clear-2');
      copyBtn?.addEventListener('click', ()=>{ const st=load(); const m=st.mon||{}; ['tue','wed','thu','fri','sat','sun'].forEach(k=>{ st[k]={...m}; }); save(st); location.reload(); });
      clearBtn?.addEventListener('click', ()=>{ localStorage.removeItem(key); location.reload(); });
    };
  })();

  // Auto abrir colonias al tabular desde CP y permitir selecciÃ³n con flechas
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

    // Al tabular desde CP, forzar foco en "Colonia" en blur para ganar a la navegaciÃ³n natural
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
        waited += pollMs; if(waited >= 1500) return; // 1.5s mÃ¡x
        setTimeout(poll, pollMs);
      };
      setTimeout(poll, 0);
    });
    // NavegaciÃ³n con flechas sin desplazar la pÃ¡gina y cierre con Enter/Escape
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

  // Grupo mÃ©dico: habilitar/deshabilitar campo segÃºn radios
  (function setupGrupoMedico(){
    const rSi = document.getElementById('cons-grupo-si');
    const rNo = document.getElementById('cons-grupo-no');
    const grp = document.getElementById('cons-grupo-nombre');
    if(!rSi || !rNo || !grp) return;
    function sync(){ if(rSi.checked){ grp.removeAttribute('disabled'); grp.focus(); } else { /* no limpiar valor para evitar confusión */ grp.setAttribute('disabled','disabled'); } }
    rSi.addEventListener('change', sync); rNo.addEventListener('change', sync);
    sync();
  })();

  // ValidaciÃ³n de telÃ©fonos (MX/E.164): 10 dÃ­gitos nacionales o +52 + 10 dÃ­gitos
  (function setupPhoneValidation(){
    function analyzePhone(val, isLive){
      const s = (val||'').trim();
      if(s === '') return { ok:true };
      // Solo caracteres permitidos durante ediciÃ³n
      if(/[^0-9()+\-\s+]/.test(s)) return { ok:false, reason:'invalid_char' };
      // '+' solo al inicio y mÃ¡ximo 1
      if((s.match(/\+/g)||[]).length > 1 || (s.includes('+') && !s.startsWith('+'))) return { ok:false, reason:'invalid_char' };
      const digits = s.replace(/\D/g,'');
      // Si empieza con +52, objetivo 12 dÃ­gitos (52 + 10 nacionales)
      const hasPlus52 = s.startsWith('+') && digits.startsWith('52');
      const target = hasPlus52 ? 12 : 10;
      if(isLive){
        if(digits.length > target) return { ok:false, reason:'too_long' };
        if(/[^0-9()+\-\s]/.test(s)) return { ok:false, reason:'invalid_char' };
        // Mientras escribe, no marcar corto aÃºn
        return { ok:true };
      } else {
        if(digits.length !== target) return { ok:false, reason: digits.length < target ? 'too_short' : 'too_long' };
        return { ok:true };
      }
    }
    function messageFor(reason){
      switch(reason){
        case 'invalid_char': return 'Solo nÃºmeros y + ( ) -';
        case 'too_short': return 'NÃºmero incompleto (se requieren 10 dÃ­gitos)';
        case 'too_long': return 'Demasiados dÃ­gitos (mÃ¡ximo 10 o +52 + 10)';
        default: return 'TelÃ©fono invÃ¡lido';
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
        el.setCustomValidity('TelÃ©fono invÃ¡lido');
      }
    }
    // Reglas adicionales en vivo: tope de 3 letras y tope de dÃ­gitos
    const _state = new WeakMap(); // { value, letters, digits }

    function countLetters(s){
      const m = (s||'').match(/[A-Za-zÃÃ‰ÃÃ“ÃšÃœÃ‘Ã¡Ã©Ã­Ã³ÃºÃ¼Ã±]/g);
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

      // 1) Aviso cuando llega a 3 letras y bloqueo a partir de la 4Âª
      if(letters >= 3){
        if(b) b.textContent = 'Ingresa solo nÃºmeros';
        if(wrap){ wrap.classList.add('has-error'); if(b) b.style.opacity = '1'; }
        // Si intenta exceder 3 letras, revertir a valor previo
        if(letters > 3){
          el.value = prev.value || '';
          try{ el.setSelectionRange(el.value.length, el.value.length); }catch(_){ }
          _state.set(el, { value: el.value, letters: countLetters(el.value), digits: (el.value.match(/\d/g)||[]).length });
          return; // no continuar, ya mostramos burbuja y revertimos
        }
      }

      // 2) Limitar cantidad de dÃ­gitos en vivo (10 o +52+10)
      if(digits > target){
        if(b) b.textContent = (target === 12 ? 'Demasiados dÃ­gitos (mÃ¡ximo +52 + 10)' : 'Demasiados dÃ­gitos (mÃ¡ximo 10)');
        if(wrap){ wrap.classList.add('has-error'); if(b) b.style.opacity = '1'; }
        el.value = prev.value || '';
        try{ el.setSelectionRange(el.value.length, el.value.length); }catch(_){ }
        _state.set(el, { value: el.value, letters: countLetters(el.value), digits: (el.value.match(/\d/g)||[]).length });
        return;
      }

      // 3) Si comienza a escribir nÃºmeros, ocultar burbuja de letras
      if(letters < 3){
        if(wrap){ wrap.classList.remove('has-error'); if(b) b.style.opacity = '0'; }
      }

      // 4) Aplicar validaciÃ³n estÃ¡ndar en vivo (caracteres permitidos y overflow)
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
    const root = document.querySelector('#sede1'); if(!root) return;
    const labels = ['Nombre de la sede','TelÃ©fono (planes de pago)','DirecciÃ³n','Horario','Notas'];
    labels.forEach(txt=>{
      const el = Array.from(root.querySelectorAll('label.form-label')).find(l=> (l.textContent||'').trim().indexOf(txt)===0);
      if(el){ const wrap = el.closest('[class*="col-"]'); if(wrap) wrap.style.display='none'; }
    });
  })();
})();

// ===== Correcciones rÃ¡pidas de acentos en header (muestra) =====
(function(){
  const t = document.querySelector('.optimo'); if(t) t.textContent = 'Ã“ptimo';
  const n = document.querySelector('.name'); if(n && /Muï¿½oz|MuÃ±oz/.test(n.textContent)) n.textContent = 'Leticia MuÃ±oz Alfaro';
  const img = document.querySelector('.header-top img'); if(img) img.alt = 'M\u00E9xico MÃ©dico';
  if(document.title && document.title.indexOf('MXMed')>=0) document.title = 'MXMed 2025 Â· Perfil MÃ©dico';
})();

// ===== Sugerencia de Grupo Médico y sincronización de logotipo (demo) =====
(function setupGrupoMedicoSuggest(){
  const keyAssoc = 'grupo_medico_assoc';

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
    try{ localStorage.setItem(keyAssoc, JSON.stringify(s)); }catch(_){ }
    applyAssocUI(s);
    modal?.hide();
    const rSi = document.getElementById('cons-grupo-si');
    const grp = document.getElementById('cons-grupo-nombre');
    if(rSi){ rSi.checked = true; rSi.dispatchEvent(new Event('change')); }
    if(grp){ grp.removeAttribute('disabled'); try{ grp.disabled=false; }catch(_){}
      grp.value = s.nombre || ''; grp.classList.add('grp-selected'); grp.dispatchEvent(new Event('input')); }
    // 2b) Actualizar el título del consultorio con "Consultorio <Grupo>"
    const tit = document.getElementById('cons-titulo');
    if(tit){
      tit.value = 'Consultorio ' + (s.nombre || '');
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
  }

  function decline(_s, modal){
    try{ localStorage.setItem(keyAssoc+':decline', JSON.stringify({ when: Date.now(), addr: getAddr() })); }catch(_){ }
    modal?.hide();
  }

  function applyAssocUI(s){
    const img = document.getElementById('cons-logo-img');
    const prev = document.getElementById('cons-logo-prev');
    if(img && prev && s.logo_url){ img.src = s.logo_url; prev.style.display = 'block'; }
    const sync = document.getElementById('cons-logo-sync'); if(sync) sync.style.display = 'block';
    const file = document.getElementById('cons-logo'); if(file) file.setAttribute('disabled','disabled');
    // Bloquear campos clave de dirección cuando hay asociación
    ;['cp','colonia','municipio','estado','cons-calle','cons-numext'].forEach(id=>{
      const el = document.getElementById(id); if(!el) return;
      try{ el.setAttribute('disabled','disabled'); el.disabled = true; }catch(_){ }
    });
    // Control de borrado (X en esquina)
    const del = document.getElementById('cons-logo-del');
    if(del){ del.onclick = ()=>{
      let nombre = 'este grupo';
      try { const cur = JSON.parse(localStorage.getItem(keyAssoc)||'null'); if(cur && cur.nombre) nombre = '"'+cur.nombre+'"'; }catch(_){ }
      if(!confirm('¿Está seguro que desea eliminar este logotipo que vincula su consultorio al '+nombre+'?')) return;
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
    }; }
  }

  function removeAssocUI(){
    const sync = document.getElementById('cons-logo-sync'); if(sync) sync.style.display = 'none';
    const file = document.getElementById('cons-logo'); if(file) file.removeAttribute('disabled');
    const prev = document.getElementById('cons-logo-prev');
    const img  = document.getElementById('cons-logo-img');
    if(prev){ prev.style.display = 'none'; }
    if(img){ img.src=''; }
    const grp = document.getElementById('cons-grupo-nombre'); if(grp){ grp.classList.remove('grp-selected'); }
  }

  // Modal para desvincular grupo (con botones centrados y "Sí desvincular")
  function openUnlinkModal(saved, onConfirm, onCancel){
    const el = document.getElementById('modalGrupoUnlink');
    if(!el){ const ok = confirm('¿Está seguro que desea desvincular su consultorio?'); if(ok) onConfirm?.(); else onCancel?.(); return; }
    const nameEl = el.querySelector('#grp-unlink-name');
    if(nameEl) nameEl.textContent = saved?.nombre || 'este grupo';
    const yesBtn = document.getElementById('modalGrupoUnlinkYes');
    const m = (window.bootstrap && bootstrap.Modal && bootstrap.Modal.getOrCreateInstance) ? bootstrap.Modal.getOrCreateInstance(el) : new bootstrap.Modal(el);
    const cleanup = ()=>{ try{ yesBtn.onclick = null; }catch(_){ } };
    el.addEventListener('hidden.bs.modal', function onHidden(){ el.removeEventListener('hidden.bs.modal', onHidden); cleanup(); onCancel?.(); }, { once:true });
    yesBtn.onclick = ()=>{ cleanup(); onConfirm?.(); m.hide(); };
    m.show();
  }

  let debounceT = null;
  // Inline suggestions layer helpers
  let inlineLayer = null;
  function hideInline(){ if(inlineLayer){ inlineLayer.remove(); inlineLayer=null; } }
  function showInline(arr, anchor){
    hideInline();
    if(!arr || !arr.length || !anchor) return;
    const rect = anchor.getBoundingClientRect();
    const box = document.createElement('div');
    box.className = 'grp-suggest';
    box.style.left = (window.scrollX + rect.left) + 'px';
    box.style.top  = (window.scrollY + rect.bottom + 4) + 'px';
    box.style.width= rect.width + 'px';
    arr.forEach(g=>{
      const it = document.createElement('div'); it.className='item';
      const nm = document.createElement('div'); nm.className='name'; nm.textContent = g.nombre;
      const ad = document.createElement('div'); ad.className='addr'; ad.textContent = g.addr||'';
      it.appendChild(nm); it.appendChild(ad);
      it.addEventListener('click', ()=>{
        // 1) Activar "Sí" y asegurar que el input esté habilitado inmediatamente
        const rSi = document.getElementById('cons-grupo-si');
        if(rSi){ rSi.checked = true; rSi.dispatchEvent(new Event('change')); }
        const grp = document.getElementById('cons-grupo-nombre');
        if(grp){ grp.removeAttribute('disabled'); try{ grp.disabled = false; }catch(_){}
          // 2) Escribir el nombre en bold y disparar autosave al primer clic
          grp.value = g.nombre || '';
          grp.classList.add('grp-selected');
          grp.dispatchEvent(new Event('input'));
        }
        // 2b) Actualizar el título del consultorio con "Consultorio <Grupo>"
        const tit = document.getElementById('cons-titulo');
        if(tit){
          tit.value = 'Consultorio ' + (g.nombre || '');
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
        // 3) Guardar asociación y reflejar logotipo/bloqueos
        try{ localStorage.setItem(keyAssoc, JSON.stringify(g)); }catch(_){ }
        applyAssocUI(g);
        hideInline();
      });
      box.appendChild(it);
    });
    document.body.appendChild(box); inlineLayer = box;
    const handler = (ev)=>{ if(!box.contains(ev.target) && ev.target!==anchor){ hideInline(); document.removeEventListener('mousedown', handler, true); } };
    document.addEventListener('mousedown', handler, true);
  }
  function listMatches(addr){ const s = suggestGroup(addr); if(!s) return []; const alt={ id:'demo-124', nombre:'Grupo '+(addr.col||'Médico Norte'), addr:[addr.col,addr.mun,addr.edo].filter(Boolean).join(', '), logo_url:s.logo_url}; return [s,alt]; }
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
    if(rSi){ rSi.addEventListener('change', ()=>{ if(rSi.checked && grp){ const a=getAddr(); const m=listMatches(a); if(m && m.length){ showInline(m, grp); grp.focus(); } }}); }
    // Si el usuario teclea en el título, deja de ser autogenerado
    const tit = document.getElementById('cons-titulo');
    if(tit){ tit.addEventListener('input', (e)=>{ if(e.isTrusted){ try{ delete tit.dataset.autofill; }catch(_){ tit.removeAttribute('data-autofill'); } } }); }
    if(rNo){ rNo.addEventListener('change', ()=>{
      hideInline();
      if(!rNo.checked) return;
      // Si hay asociación vigente, confirmar desvincular
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

// ===== Ajustes puntuales de textos (sólo secciones específicas) =====
(function fixMxmedTextos(){
  try{
    // 1) Header: "Óptimo"
    const t = document.querySelector('.optimo');
    if(t) t.textContent = '\u00D3ptimo';

    // 2) Horarios: normalizar separadores y días con acentos
    ['#sched-body','#sched-body-2'].forEach(sel=>{
      const cont = document.querySelector(sel);
      if(!cont) return;
      // Separador entre horas
      cont.querySelectorAll('span').forEach(sp=>{
        const s = (sp.textContent||'').trim();
        if(s && s !== '-' && /[^0-9A-Za-z:\-]/.test(s)) sp.textContent = '-';
      });
      // Días con acentos correctos
      const mapDias = {
        'MiǸrcoles':'Mi\u00E9rcoles', 'MiÃ©rcoles':'Mi\u00E9rcoles',
        'Sǭbado':'S\u00E1bado', 'SÃ¡bado':'S\u00E1bado'
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
    // Crear botón flotante sólo una vez
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
        // Limpiar claves principales usadas en esta sección
        localStorage.removeItem('grupo_medico_assoc');
        localStorage.removeItem('grupo_medico_assoc:decline');
        localStorage.removeItem('mxmed_cons_schedules');
        localStorage.removeItem('mxmed_cons_schedules2');
        // Preferencias de navegación (para evitar estados atascados)
        localStorage.removeItem('mxmed_menu_group');
        localStorage.removeItem('mxmed_last_panel');
        localStorage.removeItem('mxmed_info_tab');
      }catch(_){ }

      // Restablecer UI de asociación de grupo
      try{
        const prev = document.getElementById('cons-logo-prev');
        const img  = document.getElementById('cons-logo-img');
        if(prev){ prev.style.display = 'none'; }
        if(img){ img.src = ''; }
        const file = document.getElementById('cons-logo'); if(file){ file.removeAttribute('disabled'); file.value=''; }
        const sync = document.getElementById('cons-logo-sync'); if(sync) sync.style.display = 'none';
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
        // Rehabilitar campos clave de dirección
        ['cp','colonia','municipio','estado','cons-calle','cons-numext'].forEach(id=>{
          const el = document.getElementById(id); if(!el) return;
          el.removeAttribute('disabled');
          try{ el.disabled = false; }catch(_){ }
          if(id==='colonia'){ el.classList.remove('select-open'); el.removeAttribute('size'); }
        });
      }catch(_){ }

      // Intentar re-disparar la lógica de sugerencia si ya hay dirección
      try{
        const ev = new Event('change');
        ['cp','colonia','municipio','estado','cons-calle','cons-numext'].forEach(id=> document.getElementById(id)?.dispatchEvent(ev));
      }catch(_){ }
    };

    btn.addEventListener('click', reset);
    document.body.appendChild(btn);
  }catch(_){ }
})();



