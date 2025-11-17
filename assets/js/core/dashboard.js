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

