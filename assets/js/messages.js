// MENSAJES: render, badge y acciones mínimas (aislado)
(function(){
  const elBadge = document.getElementById('badgeMensajes');
  const listEl  = document.getElementById('msgList');
  if(!elBadge && !listEl) return;

  const seed = [
    {id:'ms1', title:'Vigencia de tu Plan Óptimo', text:'La vigencia de tu Plan Óptimo finaliza el 18 de Diciembre.', actions:[{label:'Renueva ahora', act:'renovar'}]},
    {id:'ms2', title:'Nueva reseña recibida', text:'Recibiste una reseña de tu paciente Arnulfo Valdéz Salazar\n*Si no la respondes será publicada automáticamente en 48 horas', actions:[{label:'Publicar', act:'publicar'},{label:'Archivar', act:'archivar'},{label:'Responder', act:'responder'}]},
    {id:'ms3', title:'Nueva cita agendada', text:'Fecha: 2025-12-01 10:30\nPaciente: Juan Pérez\nOrigen: vía perfil web', actions:[{label:'Ver', act:'ver-cita'}]},
    {id:'ms4', title:'Aseguradora Inbursa', text:'Aseguradora Inbursa te incluyó en su plantilla de Médicos Afiliados', actions:[{label:'Aceptar vinculación', act:'aceptar'},{label:'Rechazar', act:'rechazar'},{label:'Solicitar contacto', act:'contacto-aseguradora'}]}
  ];

  function getRead(){ try { return JSON.parse(localStorage.getItem('mxmed_msgs_read')||'[]'); } catch(e){ return []; } }
  function setRead(arr){ localStorage.setItem('mxmed_msgs_read', JSON.stringify(arr)); }
  function unreadCount(){ const read=new Set(getRead()); return seed.filter(m=>!read.has(m.id)).length; }

  function updateBadge(){
    if(!elBadge) return;
    const n = unreadCount();
    if(n>0){ elBadge.textContent = String(n); elBadge.classList.add('show'); }
    else { elBadge.textContent = '0'; elBadge.classList.remove('show'); }
  }

  function render(){
    if(!listEl) return;
    const read = new Set(getRead());
    listEl.innerHTML = '';
    seed.forEach(m=>{
      const wrap = document.createElement('div');
      wrap.className = 'msg-card' + (read.has(m.id)? '' : ' unread');
      wrap.dataset.id = m.id;

      const head = document.createElement('div'); head.className='msg-head';
      const ico = document.createElement('span'); ico.className='material-symbols-rounded'; ico.setAttribute('aria-hidden','true'); ico.textContent = 'notification_important';
      const ttl = document.createElement('span'); ttl.textContent = m.title;
      head.appendChild(ico); head.appendChild(ttl);

      const txt = document.createElement('div'); txt.className='msg-text'; txt.innerHTML = (m.text||'').replace(/\n/g,'<br>');

      wrap.appendChild(head); wrap.appendChild(txt);

      if(m.img){ const media=document.createElement('div'); media.className='msg-media'; const im=document.createElement('img'); im.src=m.img; im.alt=''; media.appendChild(im); wrap.appendChild(media); }

      const actions = document.createElement('div'); actions.className='msg-actions';
      (m.actions||[]).forEach(a=>{ const b=document.createElement('button'); b.type='button'; b.className='btn btn-outline-primary btn-sm'; b.textContent=a.label; b.dataset.act=a.act; b.dataset.id=m.id; actions.appendChild(b); });
      if((m.actions||[]).length===0){ const b=document.createElement('button'); b.type='button'; b.className='btn btn-outline-secondary btn-sm'; b.textContent='Marcar como leído'; b.dataset.act='leer'; b.dataset.id=m.id; actions.appendChild(b); }

      wrap.appendChild(actions);
      listEl.appendChild(wrap);
    });
  }

  function markRead(id){
    const read = new Set(getRead());
    read.add(id);
    setRead(Array.from(read));
    const card = listEl?.querySelector('.msg-card[data-id="'+id+'"]');
    if(card){ card.classList.remove('unread'); }
    updateBadge();
  }

  document.getElementById('msgList')?.addEventListener('click', function(e){
    const btn = e.target.closest('button[data-act]'); if(!btn) return;
    const act = btn.dataset.act; const id = btn.dataset.id;
    switch(act){
      case 'renovar': typeof jumpTo==='function' && jumpTo('p-suscripcion'); break;
      case 'ver-cita': typeof jumpTo==='function' && jumpTo('p-ag-admin'); break;
      default: break;
    }
    markRead(id);
  });

  // Inicializar
  render();
  updateBadge();
})();
