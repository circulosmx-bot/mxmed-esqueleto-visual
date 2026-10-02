// TAX03C: shared catalog-backed order composer. Persistence belongs to the calling surface.
(function () {
  const categories={
    LABORATORIO:'Laboratorio',IMAGEN:'Imagenología',CARDIOVASCULAR:'Cardiovascular',
    OFTALMOLOGIA:'Oftalmología',NEUROFISIOLOGIA:'Neurofisiología',FUNCION_PULMONAR:'Función pulmonar',
    AUDIOLOGIA:'Audiología',DENTAL:'Dental',PATOLOGIA:'Patología',ENDOSCOPIA:'Endoscopía',
    SUENO:'Medicina del sueño',GENETICA:'Genética',OTROS:'Otros'
  };
  const clone=value=>JSON.parse(JSON.stringify(value));
  const title=items=>{
    const value=items.length===1?items[0].name:`Solicitud de estudios (${items.length})`;
    const characters=Array.from(value);
    return characters.length>128?characters.slice(0,127).join('')+'…':value;
  };
  const documentType=items=>items.length&&items.every(item=>item.category==='LABORATORIO')?'lab_order':
    items.length&&items.every(item=>item.category==='IMAGEN')?'imaging_order':'orders';
  const orderArea=items=>documentType(items)==='lab_order'?'Laboratorio':documentType(items)==='imaging_order'?'Imagenología':'Estudios diagnósticos';

  function mount(host,options={}){
    const doctorId=String(options.doctorId||'').trim();
    const readonly=!!options.readonly;
    let selected=Array.isArray(options.selected)?clone(options.selected):[];
    let editingIndex=-1,activeDentalEditor=null;
    let results=[],offset=0,hasMore=false,request=0,controller=null,timer=null,destroyed=false;
    let navigationParts=Array.isArray(options.navigationGroup?.parts)?options.navigationGroup.parts
      .filter(part=>categories[part.category]).map(part=>({category:part.category,
        keys:Array.isArray(part.keys)?new Set(part.keys):null})):null;
    let navigationPart=0,navigationOffset=0;
    host.innerHTML=`<div class="tax03c-composer" data-tax03c-composer>
      <label class="tax03c-search-label">Buscar estudio<input type="search" data-tax03c-search placeholder="Buscar estudio" autocomplete="off" aria-label="Buscar estudio"></label>
      <p class="tax03c-navigation-scope" data-tax03c-navigation-scope></p>
      <div class="tax03c-filter"><button type="button" data-tax03c-all aria-pressed="true">Todas</button><label>Categorías<select data-tax03c-category aria-label="Filtrar estudios por categoría"><option value="">Todas las categorías</option></select></label></div>
      <p class="tax03c-status" data-tax03c-status role="status" aria-live="polite"></p>
      <div class="tax03c-results" data-tax03c-results role="list" aria-label="Resultados del catálogo"></div>
      <button type="button" class="btn btn-outline-primary btn-sm tax03c-more" data-tax03c-more hidden>Mostrar más estudios</button>
      <section class="tax03c-selected" aria-label="Estudios solicitados"><h5>ESTUDIOS SOLICITADOS</h5><div data-tax03c-selected></div></section>
      <button type="button" class="btn btn-outline-primary btn-sm" data-tax03c-custom-open>+ Agregar otro estudio</button>
      <div class="tax03c-custom" data-tax03c-custom hidden>
        <h5>Agregar otro estudio</h5>
        <label>Categoría<select data-tax03c-custom-category aria-label="Categoría del estudio"><option value="">Selecciona una categoría</option></select></label>
        <label>Nombre del estudio<input data-tax03c-custom-name maxlength="255" aria-label="Nombre del estudio"></label>
        <label>Nota adicional<textarea data-tax03c-custom-note maxlength="1000" rows="2" aria-label="Nota adicional"></textarea></label>
        <p data-tax03c-custom-error role="alert"></p>
        <div class="tax03c-custom-actions"><button type="button" class="btn btn-outline-secondary btn-sm" data-tax03c-custom-cancel>Cancelar</button><button type="button" class="btn btn-primary btn-sm" data-tax03c-custom-add>Agregar estudio</button></div>
      </div>
      <div class="tax03c-order-fields"><label>Prioridad<select data-tax03c-priority aria-label="Prioridad de la orden"><option>Rutinaria</option><option>Urgente</option></select></label>
        <label>Indicación clínica<textarea data-tax03c-indication rows="2" maxlength="2000" placeholder="Motivo o diagnóstico presuntivo" aria-label="Indicación clínica"></textarea></label></div>
    </div>`;
    const $=selector=>host.querySelector(selector);
    const search=$('[data-tax03c-search]'),category=$('[data-tax03c-category]'),status=$('[data-tax03c-status]');
    const resultBox=$('[data-tax03c-results]'),selectedBox=$('[data-tax03c-selected]');
    const custom=$('[data-tax03c-custom]'),customName=$('[data-tax03c-custom-name]');
    const priority=$('[data-tax03c-priority]'),indication=$('[data-tax03c-indication]');
    const navigationScope=$('[data-tax03c-navigation-scope]');
    function renderNavigationScope(){
      navigationScope.textContent=navigationParts?.length?`Explorando ${options.navigationGroup.label}. Puedes buscar en todo el catálogo o cambiar de categoría.`:'';
      navigationScope.hidden=!navigationScope.textContent;
    }
    renderNavigationScope();
    priority.value=options.priority==='Urgente'?'Urgente':'Rutinaria';
    indication.value=String(options.indication||'');
    if(options.initialCategory&&categories[options.initialCategory]){
      category.add(new Option(categories[options.initialCategory],options.initialCategory));
      category.value=options.initialCategory;
      $('[data-tax03c-all]').setAttribute('aria-pressed','false');
    }
    Object.entries(categories).forEach(([key,label])=>$('[data-tax03c-custom-category]').add(new Option(label,key)));
    const notify=()=>options.onChange?.(clone(selected),priority.value,indication.value);
    function renderSelected(){
      activeDentalEditor=null;
      selectedBox.replaceChildren();
      if(!selected.length){const p=document.createElement('p');p.textContent='Todavía no has agregado estudios.';selectedBox.append(p);return;}
      selected.forEach((item,index)=>{
        const row=document.createElement('div');row.className='tax03c-selected-row';
        const copy=document.createElement('span');const name=document.createElement('strong');name.textContent=item.name;
        const sub=document.createElement('small');sub.textContent=categories[item.category]||'Otros';copy.append(name,sub);row.append(copy);
        const dental=window.mxmedDentalLocationV1,kind=item.type==='canonical'?dental?.kindFor(item.key):null;
        if(kind&&kind!=='NONE'&&!readonly){
          const configure=document.createElement('button');configure.type='button';configure.className='btn btn-outline-primary btn-sm dental-config-toggle';
          configure.dataset.tax03cDental=String(index);configure.textContent=editingIndex===index?'Ocultar ubicación':'Configurar ubicación';
          configure.setAttribute('aria-expanded',String(editingIndex===index));row.append(configure);
        }
        if(!readonly){const remove=document.createElement('button');remove.type='button';remove.className='btn btn-link btn-sm';remove.textContent='Retirar';remove.dataset.tax03cRemove=String(index);remove.setAttribute('aria-label',`Retirar ${item.name}`);row.append(remove);}
        if(kind&&kind!=='NONE'){
          const summary=document.createElement('small');summary.className='dental-item-summary';
          summary.textContent=dental.summary(item.dentalLocation)||(kind==='MODEL'?'Ubicación opcional':'Ubicación pendiente');row.append(summary);
          if(editingIndex===index&&!readonly){
            const panel=document.createElement('div');panel.className='dental-location-host';row.append(panel);
            activeDentalEditor=dental.mount(panel,kind,item.dentalLocation||null,location=>{
              item.dentalLocation=location;summary.textContent=dental.summary(location)||(kind==='MODEL'?'Ubicación opcional':'Ubicación pendiente');notify();
            });
          }
        }
        selectedBox.append(row);
      });
    }
    function renderResults(){
      resultBox.replaceChildren();
      if(!results.length){if(status.dataset.error!=='true')status.textContent=search.value.trim()?'No encontramos un estudio con ese nombre.':category.value?'No hay estudios catalogados todavía en esta categoría.':'No hay estudios catalogados disponibles.';return;}
      results.forEach(item=>{
        const added=selected.some(row=>row.type==='canonical'&&Number(row.id)===Number(item.study_type_id));
        const row=document.createElement('button');row.type='button';row.className='tax03c-result-row';row.dataset.tax03cId=String(item.study_type_id);
        row.setAttribute('role','listitem');row.setAttribute('aria-pressed',String(added));row.setAttribute('aria-label',`${added?'Agregado':'Agregar'} ${item.display_name_es}, ${categories[item.category_key]||item.category_label_es}`);
        row.disabled=readonly||added;
        const copy=document.createElement('span'),name=document.createElement('strong'),sub=document.createElement('small'),mark=document.createElement('span');
        name.textContent=item.display_name_es;sub.textContent=categories[item.category_key]||item.category_label_es;mark.textContent=added?'Agregado':'Agregar';
        copy.append(name,sub);row.append(copy,mark);resultBox.append(row);
      });
    }
    function renderCategories(rows){
      const current=category.value;category.replaceChildren(new Option('Todas las categorías',''));
      (rows||[]).filter(row=>Number(row.active_count)>0).forEach(row=>category.add(new Option(categories[row.category_key]||row.label_es,row.category_key)));
      category.value=current;
      $('[data-tax03c-all]').setAttribute('aria-pressed',String(!category.value&&!navigationParts?.length));
    }
    async function load(append=false){
      if(destroyed||readonly)return;
      if(!doctorId){status.dataset.error='true';status.textContent='No se pudo confirmar el profesional para consultar el catálogo.';return;}
      if(controller)controller.abort();controller=new AbortController();const seen=++request;
      if(!append){offset=0;navigationPart=0;navigationOffset=0;results=[];renderResults();}
      status.dataset.error='false';status.textContent='Buscando estudios…';
      try{
        const fetchPage=async params=>{
          const response=await fetch(`/api/clinical/index.php/doctors/${encodeURIComponent(doctorId)}/study-types?${params}`,{credentials:'same-origin',headers:{Accept:'application/json'},signal:controller.signal});
          const body=await response.json();if(!response.ok||body?.ok!==true)throw new Error('CATALOG_UNAVAILABLE');
          return body.data||{};
        };
        let page=[],categoriesFromServer=[];
        if(navigationParts?.length&&!search.value.trim()&&!category.value){
          const known=new Set(results.map(item=>String(item.study_type_id)));
          while(page.length<30&&navigationPart<navigationParts.length){
            const part=navigationParts[navigationPart];
            const params=new URLSearchParams({limit:'30',offset:String(navigationOffset),search:'',category:part.category});
            const data=await fetchPage(params);
            if(destroyed||seen!==request)return;
            categoriesFromServer=data.categories||categoriesFromServer;
            const fetched=Array.isArray(data.items)?data.items:[];
            fetched.filter(item=>!part.keys||part.keys.has(item.study_type_key)).forEach(item=>{
              const id=String(item.study_type_id);if(!known.has(id)){known.add(id);page.push(item);}
            });
            if(data.has_more&&fetched.length)navigationOffset+=fetched.length;
            else{navigationPart++;navigationOffset=0;}
          }
          hasMore=navigationPart<navigationParts.length;
        }else{
          const params=new URLSearchParams({limit:'30',offset:String(offset),search:search.value.trim()});
          if(category.value)params.set('category',category.value);
          const data=await fetchPage(params);
          page=Array.isArray(data.items)?data.items:[];
          categoriesFromServer=data.categories||[];
          hasMore=!!data.has_more;
        }
        if(destroyed||seen!==request)return;
        results=append?results.concat(page):page;offset=results.length;
        renderCategories(categoriesFromServer);status.textContent=results.length?`${results.length} estudio(s) disponibles.`:'';renderResults();
        $('[data-tax03c-more]').hidden=!hasMore;
      }catch(error){if(error.name==='AbortError'||destroyed||seen!==request)return;status.dataset.error='true';status.textContent='No se pudo cargar el catálogo. Puedes agregar otro estudio manualmente.';resultBox.replaceChildren();$('[data-tax03c-more]').hidden=true;}
    }
    function openCustom(){custom.hidden=false;customName.value=search.value.trim();$('[data-tax03c-custom-error]').textContent='';
      if(options.initialCategory&&categories[options.initialCategory]&&!$('[data-tax03c-custom-category]').value)$('[data-tax03c-custom-category]').value=options.initialCategory;
      customName.focus();}
    function addCustom(){
      const cat=$('[data-tax03c-custom-category]').value,name=customName.value.trim(),note=$('[data-tax03c-custom-note]').value.trim();
      if(!cat||!categories[cat]){$('[data-tax03c-custom-error]').textContent='Selecciona una categoría.';return;}
      if(!name){$('[data-tax03c-custom-error]').textContent='Escribe el nombre del estudio.';customName.focus();return;}
      if(selected.some(row=>row.type==='custom'&&row.category===cat&&row.name.toLocaleLowerCase('es')===name.toLocaleLowerCase('es'))){$('[data-tax03c-custom-error]').textContent='Este estudio ya está agregado.';return;}
      if(selected.length>=100){$('[data-tax03c-custom-error]').textContent='Máximo 100 estudios por orden.';return;}
      selected.push({type:'custom',category:cat,name,note});custom.hidden=true;customName.value='';$('[data-tax03c-custom-note]').value='';$('[data-tax03c-custom-category]').value='';
      renderSelected();notify();
    }
    host.addEventListener('click',event=>{
      const target=event.target.closest('button');if(!target||readonly)return;
      if(target.dataset.tax03cId){const row=results.find(item=>String(item.study_type_id)===target.dataset.tax03cId);if(!row)return;
        if(selected.some(item=>item.type==='canonical'&&Number(item.id)===Number(row.study_type_id)))return;
        if(selected.length>=100){status.textContent='Máximo 100 estudios por orden.';return;}
        selected.push({type:'canonical',id:Number(row.study_type_id),key:row.study_type_key,name:row.display_name_es,category:row.category_key});
        const kind=window.mxmedDentalLocationV1?.kindFor(row.study_type_key);editingIndex=kind&&kind!=='NONE'?selected.length-1:-1;
        renderSelected();renderResults();notify();}
      if(target.dataset.tax03cDental!==undefined){const index=Number(target.dataset.tax03cDental);editingIndex=editingIndex===index?-1:index;activeDentalEditor=null;renderSelected();selectedBox.querySelector(`[data-tax03c-dental="${index}"]`)?.focus({preventScroll:true});}
      if(target.dataset.tax03cRemove!==undefined){const index=Number(target.dataset.tax03cRemove);selected.splice(index,1);editingIndex=editingIndex===index?-1:editingIndex>index?editingIndex-1:editingIndex;activeDentalEditor=null;renderSelected();renderResults();notify();}
      if(target.hasAttribute('data-tax03c-custom-open'))openCustom();
      if(target.hasAttribute('data-tax03c-custom-cancel'))custom.hidden=true;
      if(target.hasAttribute('data-tax03c-custom-add'))addCustom();
      if(target.hasAttribute('data-tax03c-more')&&hasMore)load(true);
      if(target.hasAttribute('data-tax03c-all')){navigationParts=null;renderNavigationScope();category.value='';target.setAttribute('aria-pressed','true');load();}
    });
    search.addEventListener('input',()=>{clearTimeout(timer);timer=setTimeout(()=>load(),250);});
    category.addEventListener('change',()=>{navigationParts=null;renderNavigationScope();$('[data-tax03c-all]').setAttribute('aria-pressed',String(!category.value));load();});
    priority.addEventListener('change',notify);indication.addEventListener('input',notify);
    renderSelected();if(readonly){host.querySelectorAll('input,select,textarea,button').forEach(control=>control.disabled=true);}else load();
    return {
      selected:()=>clone(selected),priority:()=>priority.value,indication:()=>indication.value,
      orderItems:()=>selected.map(item=>item.type==='canonical'?{study_type_id:item.id,study_type_key:item.key,...(item.dentalLocation?{dental_location:clone(item.dentalLocation)}:{})}:{study_category:item.category,study_display_name:item.name,...(item.note?{note:item.note}:{})}),
      valid:()=>selected.length>0&&selected.length<=100&&selected.every(item=>{
        const kind=item.type==='canonical'?window.mxmedDentalLocationV1?.kindFor(item.key):null;
        return !kind||window.mxmedDentalLocationV1.isComplete(kind,item.dentalLocation);
      })&&(!activeDentalEditor||activeDentalEditor.valid()),
      validationMessage:()=>selected.length?'Configura la ubicación de cada estudio dental pendiente antes de solicitar la orden.':'Agrega al menos un estudio antes de solicitar la orden.',
      destroy:()=>{destroyed=true;clearTimeout(timer);controller?.abort();},
    };
  }
  window.mxmedStudyComposer={mount,title,documentType,orderArea,categories};
})();
