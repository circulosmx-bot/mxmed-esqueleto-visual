// AGF01 reads canonical clinical follow-ups alongside Agenda. It never adds calendar events.
(function () {
  const panel=document.getElementById('p-ag-admin');
  const root=document.getElementById('agf01-followups');
  if(!panel||!root)return;
  const status=root.querySelector('[data-agf01-status]');
  const groups=root.querySelector('[data-agf01-groups]');
  const keys=['overdue','today','upcoming','no_due'];
  let generation=0;

  function renderItem(row,target){
    const item=document.createElement('article');item.className='agf01-item';
    const patient=document.createElement('strong');patient.className='agf01-patient';patient.textContent=String(row.patient_name||'Paciente');
    const action=document.createElement('p');action.className='agf01-action';action.textContent=String(row.title||'');
    item.append(patient,action);
    if(row.due_display){
      const due=document.createElement('p');due.className='agf01-detail';
      due.textContent=`Fecha límite · ${row.due_display} · ${row.derived_due_state==='OVERDUE'?'Vencido':'Pendiente'}`;
      item.append(due);
    }
    if(row.linked_appointment_display){
      const linked=document.createElement('p');linked.className='agf01-detail agf01-linked';linked.textContent=String(row.linked_appointment_display);
      item.append(linked);
    }
    target.append(item);
  }

  async function load(){
    if(panel.classList.contains('d-none'))return;
    const current=++generation;
    root.setAttribute('aria-busy','true');
    groups.classList.add('d-none');status.classList.remove('d-none');status.textContent='Cargando seguimientos…';
    try{
      const response=await fetch('/api/clinical/index.php/longitudinal/follow-ups/agenda',{credentials:'same-origin',headers:{Accept:'application/json'}});
      const payload=await response.json();
      if(!response.ok||payload?.ok!==true||!payload.data?.groups)throw new Error('FOLLOW_UP_READ_FAILED');
      if(current!==generation)return;
      let count=0;
      for(const key of keys){
        const group=root.querySelector(`[data-agf01-group="${key}"]`);
        const target=root.querySelector(`[data-agf01-items="${key}"]`);
        const rows=payload.data.groups[key];
        if(!Array.isArray(rows))throw new Error('INVALID_FOLLOW_UP_GROUP');
        target.replaceChildren();
        rows.forEach(row=>renderItem(row,target));
        group.classList.toggle('d-none',rows.length===0);
        count+=rows.length;
      }
      if(count){status.classList.add('d-none');groups.classList.remove('d-none');}
      else{status.textContent='No hay seguimientos pendientes.';}
    }catch(_){
      if(current!==generation)return;
      groups.classList.add('d-none');status.classList.remove('d-none');status.textContent='No se pudieron cargar los seguimientos.';
    }finally{if(current===generation)root.removeAttribute('aria-busy');}
  }

  panel.querySelector('#ag_refresh_btn')?.addEventListener('click',load);
  window.addEventListener('lon06b:changed',load);
  new MutationObserver(()=>{if(!panel.classList.contains('d-none'))load();}).observe(panel,{attributes:true,attributeFilter:['class']});
  if(!panel.classList.contains('d-none'))load();
})();
