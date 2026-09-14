/* IP01A: a transient draft backed only by the doctor-self grouped API. */
(function(){
  'use strict';
  const panel=document.getElementById('t-info-profesional');if(!panel)return;
  const types={cert:'CERTIFICATION',cursos:'COURSE',dipl:'DIPLOMA',miem:'MEMBERSHIP',enf:'DISEASE',trt:'TREATMENT'};
  const allTypes=[...Object.values(types),'SERVICE'];
  const endpoint='/api/profiles/professional-information.php';
  const summary=document.getElementById('professional-summary'),feedback=document.getElementById('professional-information-feedback');
  const services=[1,2,3,4].map(n=>document.getElementById('srv'+n));
  let draft={public_professional_summary:'',items:Object.fromEntries(allTypes.map(t=>[t,[]]))};
  let baseline=null,csrf='',loaded=false,busy=false,sortScope=null,sorted=[],dragIndex=null;
  const clone=value=>JSON.parse(JSON.stringify(value));
  function snapshot(){return {public_professional_summary:summary.value.replace(/\r\n?/g,'\n').trim(),items:Object.fromEntries(allTypes.map(t=>[t,t==='SERVICE'?services.map(i=>i.value.trim()).filter(Boolean):draft.items[t].map(v=>v.trim())]))};}
  function dirty(){return loaded&&JSON.stringify(snapshot())!==JSON.stringify(baseline);}
  function active(){return !document.getElementById('p-info').classList.contains('d-none')&&panel.classList.contains('active');}
  function announce(activity=false){document.dispatchEvent(new CustomEvent('mxmed:professional-draft',{detail:{activity}}));}
  function message(text,error=false){feedback.textContent=text;feedback.classList.toggle('text-danger',error);feedback.classList.toggle('text-muted',!error);}
  function updateControls(){
    panel.querySelectorAll('.chip-add,#professional-summary,.chip-input,.srv-input,.chip-x').forEach(c=>c.disabled=busy||!loaded);
    for(const [scope,type] of Object.entries(types)){
      const input=document.getElementById(scope+'-input'),limit=Number(input.maxLength),left=limit-input.value.length;
      const count=document.getElementById(scope+'-count');count.textContent=left+'/'+limit;count.style.visibility=left<10?'visible':'hidden';
      document.getElementById(scope+'-add').disabled=busy||!loaded||!input.value.trim()||left<0;
    }
    services.forEach((input,n)=>{const left=50-input.value.length,c=document.getElementById('srv'+(n+1)+'-count');c.textContent=left+'/50';c.style.visibility=left<10?'visible':'hidden';});
  }
  function renderList(scope){
    const list=document.getElementById(scope+'-list'),values=draft.items[types[scope]];list.replaceChildren();
    values.forEach((value,index)=>{
      const chip=document.createElement('span');chip.className='chip';chip.append(document.createTextNode(value));
      const remove=document.createElement('button');remove.type='button';remove.className='chip-x';remove.textContent='×';remove.setAttribute('aria-label','Eliminar '+value);
      remove.addEventListener('click',()=>{if(busy)return;values.splice(index,1);renderList(scope);updateControls();announce(true);});chip.append(remove);list.append(chip);
    });
    if(['enf','trt'].includes(scope)&&values.length>2){
      const order=document.createElement('a');order.href='#';order.className='chip-sort-link';order.textContent='cambia el orden';
      order.addEventListener('click',event=>{event.preventDefault();if(busy)return;sortScope=scope;sorted=[...values];renderSort();bootstrap.Modal.getOrCreateInstance(document.getElementById('modalSortChips')).show();});list.append(order);
    }
  }
  function render(){
    summary.value=draft.public_professional_summary;
    services.forEach((input,n)=>input.value=draft.items.SERVICE[n]||'');
    Object.keys(types).forEach(scope=>{document.getElementById(scope+'-input').value='';renderList(scope);});updateControls();
  }
  function renderSort(){
    const host=document.getElementById('sort-list');host.replaceChildren();
    sorted.forEach((text,index)=>{
      const li=document.createElement('li');li.className='sort-item';li.draggable=true;li.dataset.index=index;
      const handle=document.createElement('span');handle.className='material-symbols-outlined sort-handle';handle.textContent='drag_indicator';handle.setAttribute('aria-hidden','true');
      const label=document.createElement('span');label.textContent=text;label.style.flex='1';li.append(handle,label);
      // Keyboard and touch alternatives supplement the existing drag workflow.
      for(const [delta,name] of [[-1,'Subir'],[1,'Bajar']]){const button=document.createElement('button');button.type='button';button.className='btn btn-sm btn-outline-secondary';button.textContent=delta<0?'↑':'↓';button.setAttribute('aria-label',name+' '+text);button.disabled=index+delta<0||index+delta>=sorted.length;button.addEventListener('click',()=>{[sorted[index],sorted[index+delta]]=[sorted[index+delta],sorted[index]];renderSort();host.children[index+delta]?.querySelector('button:not(:disabled)')?.focus();});li.append(button);}
      li.addEventListener('dragstart',event=>{dragIndex=index;event.dataTransfer.effectAllowed='move';});
      li.addEventListener('dragover',event=>event.preventDefault());
      li.addEventListener('drop',event=>{event.preventDefault();if(dragIndex===null)return;const [value]=sorted.splice(dragIndex,1);sorted.splice(index,0,value);dragIndex=null;renderSort();});li.addEventListener('dragend',()=>dragIndex=null);host.append(li);
    });
  }
  document.getElementById('sort-save')?.addEventListener('click',()=>{if(sortScope&&!busy){draft.items[types[sortScope]]=[...sorted];renderList(sortScope);updateControls();announce(true);}bootstrap.Modal.getInstance(document.getElementById('modalSortChips'))?.hide();});
  // All entry gestures update only the draft. Synchronous clearing makes blur + click idempotent.
  function commitChipInput(scope,input){
    const value=input.value.trim();
    if(busy||!loaded||!value||value.length>input.maxLength||!input.checkValidity())return;
    const values=draft.items[types[scope]];
    if(!values.some(existing=>existing.trim()===value)){values.push(value);renderList(scope);}
    input.value='';updateControls();announce(true);
  }
  for(const [scope,type] of Object.entries(types)){
    const input=document.getElementById(scope+'-input');
    input.addEventListener('input',()=>{updateControls();announce(true);});
    document.getElementById(scope+'-add').addEventListener('click',()=>commitChipInput(scope,input));
    input.addEventListener('blur',()=>commitChipInput(scope,input));
    input.addEventListener('keydown',event=>{
      if(event.key==='Enter'&&!event.isComposing){event.preventDefault();commitChipInput(scope,input);}
    });
  }
  [...services,summary].forEach(input=>input.addEventListener('input',()=>{updateControls();announce(true);}));
  async function request(method='GET',body){
    const response=await fetch(endpoint,{method,credentials:'same-origin',cache:'no-store',headers:method==='GET'?{}:{'Content-Type':'application/json','X-Professional-Information-CSRF':csrf},...(body?{body:JSON.stringify(body)}:{})});
    const result=await response.json();if(!response.ok||!result.ok)throw Error(result.message||'No fue posible completar la operación. Conserva tus cambios e intenta nuevamente.');
    csrf=result.data.csrf_token;return result.data.professional_information;
  }
  async function load(){if(busy||loaded)return;busy=true;updateControls();message('Cargando información profesional…');
    try{draft=await request();loaded=true;render();baseline=snapshot();message('');}catch(error){message(error.message,true);}finally{busy=false;updateControls();announce();}}
  async function save(){if(!loaded||busy)return false;busy=true;updateControls();announce();message('Guardando…');
    try{const current=snapshot();draft=await request('PUT',current);render();baseline=snapshot();message('Información profesional guardada.');return true;}catch(error){message(error.message,true);return false;}finally{busy=false;updateControls();announce();}}
  function discard(){if(!baseline||busy)return;draft=clone(baseline);render();message('');announce();}
  window.mxmedProfessionalInformation={active,dirty,save,discard,load,get loaded(){return loaded;},get busy(){return busy;},get error(){return feedback.textContent;}};
  document.getElementById('t-info-formacion-tab').addEventListener('shown.bs.tab',()=>{load();announce();});
  updateControls();load();
})();
