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
    const embedded=options.presentation==='embedded';
    let embeddedGlobal=false,embeddedRows=null,embeddedSearchRows=null,fullOpen=embedded&&!!options.customDraft?.open,openGroup='',rescueExpanded=false,focusPending=!!options.focusStudyKey;
    const doctorId=String(options.doctorId||'').trim();
    const readonly=!!options.readonly;
    let selected=Array.isArray(options.selected)?clone(options.selected):[];
    let presetApplications=Array.isArray(options.presetApplications)?clone(options.presetApplications):[];
    let editingIndex=-1,activeDentalEditor=null;
    const specimen=options.specimenConfig;
    const panels=options.panelConfig?.panels||{},presets=options.presetConfig?.presets||{};
    const pathology=window.mxmedPathologyParametersV1,pathologyAuthority=options.pathologyConfig;
    const pathologyRule=item=>item.type==='canonical'?pathology?.ruleFor(item.key,pathologyAuthority):null;
    const pathologyComplete=item=>!pathologyRule(item)||pathology.isComplete(item.key,item.pathologyParameters,pathologyAuthority);
    const imaging=window.mxmedImagingParametersV1;let imagingAuthority=options.imagingConfig||null;
    const imagingRule=item=>item.type==='canonical'?imaging?.ruleFor(item.key,imagingAuthority):null;
    const imagingComplete=item=>{if(item.type!=='canonical')return true;if(!imagingAuthority)return item.category!=='IMAGEN';return imaging.isComplete(item.key,item.imagingParameters,imagingAuthority);};
    const specimenRule=item=>item.type==='canonical'?specimen?.studies?.[item.key]:null;
    const specimenComplete=item=>{
      const rule=specimenRule(item),value=item.specimenRequirements||{};
      if(!rule)return true;
      return !(rule.specimen_mode==='REQUIRED_SELECTION'&&!value.specimen_type_key
        ||rule.collection_mode==='REQUIRED_SELECTION'&&!value.collection_mode
        ||value.collection_mode==='TIMED'&&!Number.isInteger(value.requested_duration_minutes)
        ||rule.source_site==='REQUIRED_SELECTION'&&!value.source_site_key
        ||rule.source_site_text_required_keys?.includes(value.source_site_key)&&!value.source_site_text?.trim());
    };
    const oxygenComplete=item=>{
      if(item.key!=='arterial_blood_gas')return true;
      const value=item.oxygenContext||{mode:'UNKNOWN'},fio2=value.fio2_percent;
      return (fio2===undefined||(Number(fio2)>21&&Number(fio2)<=100))
        &&(value.mode!=='SUPPLEMENTAL_OXYGEN'||fio2!==undefined||!!value.delivery_device);
    };
    let results=[],offset=0,hasMore=false,request=0,controller=null,timer=null,destroyed=false;
    const dentalScope=Array.isArray(options.dentalScope)?options.dentalScope
      .filter(part=>categories[part.category]&&Array.isArray(part.keys)&&part.keys.length)
      .map(part=>({category:part.category,keys:new Set(part.keys)})):null;
    let globalCatalog=!!options.globalCatalog&&!!dentalScope?.length;
    const labScope=Array.isArray(options.labScope)?options.labScope.filter(part=>categories[part.category]).map(part=>({category:part.category,keys:null})):null;
    let laboratoryAll=options.navigationGroup?.key==='all-laboratory',laboratoryGlobal=false;
    let navigationParts=Array.isArray(options.navigationGroup?.parts)?options.navigationGroup.parts
      .filter(part=>categories[part.category]).map(part=>({category:part.category,
        keys:Array.isArray(part.keys)?new Set(part.keys):null})):null;
    let navigationPart=0,navigationOffset=0;
    host.innerHTML=`<div class="tax03c-composer" data-tax03c-composer>
      <label class="tax03c-search-label">Buscar estudio<input type="search" data-tax03c-search placeholder="Buscar estudio" autocomplete="off" aria-label="Buscar estudio"></label>
      <div class="tax03c-scope-bar"><p class="tax03c-navigation-scope" data-tax03c-navigation-scope></p>
        <button type="button" class="tax03c-scope-link" data-tax03c-global hidden>Buscar en todo el catálogo</button>
        <button type="button" class="tax03c-scope-link" data-tax03c-dental-back hidden>Volver a estudios dentales</button>
        <button type="button" class="tax03c-scope-link" data-tax03c-lab-all hidden>Todos los estudios de laboratorio</button>
        <button type="button" class="tax03c-scope-link" data-tax03c-lab-back hidden>Volver a laboratorio</button></div>
      <div class="tax03c-filter" data-tax03c-filter><button type="button" data-tax03c-all aria-pressed="true">Todas</button><label>Categorías<select data-tax03c-category aria-label="Filtrar estudios por categoría"><option value="">Todas las categorías</option></select></label></div>
      <p class="tax03c-status" data-tax03c-status role="status" aria-live="polite"></p>
      <div class="tax03c-results" data-tax03c-results role="group" aria-label="Resultados del catálogo"></div>
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
    const resultBox=$('[data-tax03c-results]'),selectedBox=options.selectionHost||$('[data-tax03c-selected]');
    let customOpen=$('[data-tax03c-custom-open]');
    if(embedded){$('[data-tax03c-selected]').parentElement.hidden=true;$('[data-tax03c-filter]').hidden=true;$('[data-tax03c-priority]').closest('.tax03c-order-fields').hidden=true;
      const link=document.createElement('a');link.href='#';link.className='ordcomp-custom-link';link.dataset.tax03cCustomOpen='';link.textContent='¿No encuentras el estudio? Agregar estudio no catalogado';customOpen.replaceWith(link);customOpen=link;}
    const custom=$('[data-tax03c-custom]'),customName=$('[data-tax03c-custom-name]');
    const priority=$('[data-tax03c-priority]'),indication=$('[data-tax03c-indication]');
    const navigationScope=$('[data-tax03c-navigation-scope]');
    function renderNavigationScope(){
      if(embedded){
        const finalLeaf=!!options.featuredNavigation?.leaves?.[options.leafId];
        navigationScope.textContent=embeddedGlobal?'Catálogo general':finalLeaf?'':options.navigationGroup?.label||'Catálogo general';
        $('[data-tax03c-global]').hidden=true; // Family search and rescue replace the final selector's global-search shortcut.
        $('[data-tax03c-dental-back]').hidden=!embeddedGlobal;
        $('[data-tax03c-dental-back]').textContent='Volver a '+(options.navigationGroup?.label||'esta familia');
      }else if(dentalScope?.length){
        navigationScope.textContent=globalCatalog?'Catálogo general':navigationParts?.length
          ?`Estudios dentales · ${options.navigationGroup.label}`:'Estudios dentales';
        $('[data-tax03c-global]').hidden=globalCatalog;
        $('[data-tax03c-dental-back]').hidden=!globalCatalog;
        $('[data-tax03c-all]').textContent=globalCatalog?'Todas':'Estudios dentales';
      }else if(labScope?.length){
        navigationScope.textContent=laboratoryGlobal?'Catálogo general':laboratoryAll?'Todos los estudios de laboratorio':`Estudios de laboratorio · ${options.navigationGroup.label}`;
        $('[data-tax03c-global]').hidden=laboratoryGlobal;
        $('[data-tax03c-lab-all]').hidden=laboratoryGlobal||laboratoryAll;
        $('[data-tax03c-lab-back]').hidden=!laboratoryGlobal;
        $('[data-tax03c-all]').textContent=laboratoryGlobal?'Todas':'Estudios de laboratorio';
        $('[data-tax03c-filter]').hidden=!laboratoryGlobal;
      }else navigationScope.textContent=navigationParts?.length?`Explorando ${options.navigationGroup.label}. Puedes buscar en todo el catálogo o cambiar de categoría.`:'';
      navigationScope.hidden=!navigationScope.textContent;
      navigationScope.parentElement.hidden=!navigationScope.textContent&&$('[data-tax03c-global]').hidden&&$('[data-tax03c-dental-back]').hidden&&$('[data-tax03c-lab-all]').hidden&&$('[data-tax03c-lab-back]').hidden;
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
    let customRoute=null;
    if(embedded){
      const label=document.createElement('label');label.textContent='Servicio que realizará el estudio';
      customRoute=document.createElement('select');customRoute.dataset.tax03cCustomRoute='';customRoute.setAttribute('aria-label','Servicio que realizará el estudio');
      customRoute.add(new Option('Selecciona y confirma el servicio',''));
      Object.entries(options.routing.groups).forEach(([key,name])=>customRoute.add(new Option(name,key)));
      label.append(customRoute);custom.prepend(label);
      if(options.customDraft){
        custom.hidden=!options.customDraft.open;customName.value=options.customDraft.name||'';
        $('[data-tax03c-custom-note]').value=options.customDraft.note||'';
        $('[data-tax03c-custom-category]').value=options.customDraft.category||'';
        customRoute.value=options.customDraft.route||'';
      }
    }
    const customDraft=()=>({open:!custom.hidden,name:customName.value,note:$('[data-tax03c-custom-note]').value,category:$('[data-tax03c-custom-category]').value,route:customRoute?.value||''});
    const notify=()=>options.onChange?.(clone(selected),priority.value,indication.value);
    custom.addEventListener('input',()=>options.onDraftChange?.());

    function renderSelected(){
      activeDentalEditor=null;
      selectedBox.replaceChildren();
      if(!selected.length){const p=document.createElement('p');p.textContent='Todavía no has agregado estudios.';selectedBox.append(p);return;}
      const boxes=new Map();
      if(embedded){
        const activeGroups=[...new Set(selected.map(item=>item.type==='canonical'?options.routing.studies[item.key]:item.routingGroup))];
        activeGroups.forEach(key=>{
          const card=document.createElement('details');card.className='ordcomp-prepared-order';card.dataset.orderGroup=key;card.open=activeGroups.length<=3||selected.some((item,i)=>i===editingIndex&&(item.type==='canonical'?options.routing.studies[item.key]:item.routingGroup)===key);
          const service=options.routing.groups[key]||'Servicio pendiente';
          const heading=document.createElement('summary');
          const label=document.createElement('span');label.textContent=service+' ('+selected.filter(item=>(item.type==='canonical'?options.routing.studies[item.key]:item.routingGroup)===key).length+')';heading.append(label);
          if(options.onReviewOrder){
            const review=document.createElement('button');review.type='button';review.className='btn btn-outline-primary btn-sm ordcomp-review-one';review.textContent='Revisar orden';
            review.setAttribute('aria-label',`Revisar orden de ${service}`);
            review.addEventListener('click',event=>{event.preventDefault();event.stopPropagation();options.onReviewOrder(key);});heading.append(review);
          }
          const box=document.createElement('div');card.append(heading,box);selectedBox.append(card);boxes.set(key,box);
        });
      }
      selected.forEach((item,index)=>{
        const row=document.createElement('div');row.className='tax03c-selected-row';
        const copy=document.createElement('span');const name=document.createElement('strong');name.textContent=item.name;
        copy.append(name);
        if(panels[item.key]?.status==='ACTIVE'){
          const type=document.createElement('small');type.textContent='Panel de laboratorio · 1 estudio';copy.append(type);
        }
        if(item.specimenRequirements?.specimen_type_key){
          const specimenName=specimen?.specimen_types?.[item.specimenRequirements.specimen_type_key];
          if(specimenName){const context=document.createElement('small');context.className='specimen-item-summary';context.textContent=specimenName;copy.append(context);}
        }
        if(!embedded){const sub=document.createElement('small');sub.textContent=categories[item.category]||'Otros';copy.append(sub);}
        else if(item.type==='custom'&&item.note){const sub=document.createElement('small');sub.textContent=item.note;copy.append(sub);}
        row.append(copy);
        if(item.key==='arterial_blood_gas'&&!readonly){
          const oxygen=document.createElement('div');oxygen.className='tax03c-oxygen-editor';
          const modeLabel=document.createElement('label');modeLabel.textContent='Oxígeno al solicitar la gasometría';
          const mode=document.createElement('select');mode.setAttribute('aria-label','Contexto de oxígeno');
          [['UNKNOWN','Desconocido'],['ROOM_AIR','Aire ambiente'],['SUPPLEMENTAL_OXYGEN','Oxígeno suplementario']].forEach(([value,label])=>mode.add(new Option(label,value)));
          item.oxygenContext ||= {version:1,mode:'UNKNOWN'};mode.value=item.oxygenContext.mode;
          mode.addEventListener('change',()=>{item.oxygenContext={version:1,mode:mode.value};renderSelected();notify();});modeLabel.append(mode);oxygen.append(modeLabel);
          if(item.oxygenContext.mode==='SUPPLEMENTAL_OXYGEN'){
            const fio2Label=document.createElement('label');fio2Label.textContent='FiO₂ (%) si se conoce';
            const fio2=document.createElement('input');fio2.type='number';fio2.min='21.1';fio2.max='100';fio2.step='0.1';fio2.value=item.oxygenContext.fio2_percent??'';
            fio2.addEventListener('input',()=>{if(fio2.value==='')delete item.oxygenContext.fio2_percent;else item.oxygenContext.fio2_percent=Number(fio2.value);notify();});fio2Label.append(fio2);oxygen.append(fio2Label);
            const deviceLabel=document.createElement('label');deviceLabel.textContent='Dispositivo si se conoce';
            const device=document.createElement('select');device.add(new Option('Selecciona o indica FiO₂',''));
            [['NASAL_CANNULA','Cánula nasal'],['SIMPLE_MASK','Mascarilla simple'],['NON_REBREATHER_MASK','Mascarilla con reservorio'],['HIGH_FLOW','Alto flujo'],['VENTILATOR','Ventilador'],['OTHER_CONTROLLED','Otro dispositivo']].forEach(([value,label])=>device.add(new Option(label,value)));
            device.value=item.oxygenContext.delivery_device||'';
            device.addEventListener('change',()=>{if(device.value)item.oxygenContext.delivery_device=device.value;else delete item.oxygenContext.delivery_device;notify();});deviceLabel.append(device);oxygen.append(deviceLabel);
          }
          row.append(oxygen);
        }
        const dental=window.mxmedDentalLocationV1,kind=item.type==='canonical'?dental?.kindFor(item.key):null;
        if(kind&&kind!=='NONE'&&!readonly){
          const configure=document.createElement('button');configure.type='button';configure.className='btn btn-outline-primary btn-sm dental-config-toggle';
          configure.dataset.tax03cDental=String(index);configure.textContent=editingIndex===index?'Ocultar ubicación':'Configurar ubicación';
          configure.setAttribute('aria-expanded',String(editingIndex===index));row.append(configure);
        }
        if(!readonly){const remove=document.createElement('button');remove.type='button';remove.className=embedded?'btn btn-link btn-sm ordcomp-remove':'btn btn-link btn-sm';remove.textContent=embedded?'×':'Retirar';remove.dataset.tax03cRemove=String(index);remove.setAttribute('aria-label',`Retirar ${item.name}`);row.append(remove);}
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
        const pathologyRequirement=pathologyRule(item);
        if(pathologyRequirement){
          const detail=document.createElement('small');detail.className='pathology-item-summary';
          detail.textContent=pathologyComplete(item)?pathology.summary(item.key,item.pathologyParameters,pathologyAuthority):'Completa los parámetros de patología';
          row.append(detail);
          if(!readonly){
            const configure=document.createElement('button');configure.type='button';configure.className='btn btn-outline-primary btn-sm';
            configure.dataset.tax03cPathology=String(index);configure.textContent=editingIndex===index?'Ocultar parámetros':'Configurar parámetros';
            configure.setAttribute('aria-expanded',String(editingIndex===index));row.append(configure);
            if(editingIndex===index){
              const panel=document.createElement('div');row.append(panel);
              pathology.mount(panel,item.key,pathologyAuthority,item.pathologyParameters,value=>{
                item.pathologyParameters=value;
                detail.textContent=pathologyComplete(item)?pathology.summary(item.key,value,pathologyAuthority):'Completa los parámetros de patología';
                notify();
              });
            }
          }
        }
        const imagingRequirement=imagingRule(item);
        if(imagingRequirement&&Object.keys(imagingRequirement.allowed||{}).length){
          const detail=document.createElement('small');detail.className='imaging-item-summary';
          detail.textContent=imagingComplete(item)?imaging.summary(item.key,item.imagingParameters,imagingAuthority)||'Parámetros opcionales':'Completa los parámetros de imagen';row.append(detail);
          if(!readonly){
            const configure=document.createElement('button');configure.type='button';configure.className='btn btn-outline-primary btn-sm';configure.dataset.tax03cImaging=String(index);
            configure.textContent=editingIndex===index?'Ocultar parámetros':'Configurar parámetros';configure.setAttribute('aria-expanded',String(editingIndex===index));row.append(configure);
            if(editingIndex===index){const panel=document.createElement('div');row.append(panel);
              imaging.mount(panel,item.key,imagingAuthority,item.imagingParameters,value=>{
                item.imagingParameters=value;detail.textContent=imagingComplete(item)?imaging.summary(item.key,value,imagingAuthority):'Completa los parámetros de imagen';notify();
              });}
          }
        }
        const rule=specimenRule(item);
        if(rule&&specimen){
          const configurable=rule.specimen_mode==='REQUIRED_SELECTION'||rule.collection_mode==='REQUIRED_SELECTION'||rule.source_site==='REQUIRED_SELECTION'||rule.source_site==='OPTIONAL_SELECTION';
          if(configurable&&!readonly){
            const configure=document.createElement('button');configure.type='button';configure.className='btn btn-outline-primary btn-sm';configure.dataset.tax03cSpecimen=String(index);
            configure.textContent=editingIndex===index?'Ocultar datos':(!specimenComplete(item)?'Completar datos':item.specimenRequirements?.source_site_key?'Editar sitio':'Añadir sitio (opcional)');configure.setAttribute('aria-expanded',String(editingIndex===index));row.append(configure);
            const state=document.createElement('small');state.className='specimen-item-summary';state.setAttribute('role','status');state.textContent=specimenComplete(item)?'Datos de muestra completos':'Completar datos de muestra';row.append(state);
            if(editingIndex===index){
              const panel=document.createElement('div');panel.className='specimen-editor';panel.setAttribute('aria-label',`Datos de muestra de ${item.name}`);
              const value=item.specimenRequirements ||= {version:1};
              const field=(caption,optionsList,current,onChange,required=false)=>{
                const label=document.createElement('label');label.textContent=caption;const select=document.createElement('select');select.required=required;
                select.add(new Option(required?'Selecciona una opción':'Sin especificar',''));
                optionsList.forEach(([key,text])=>select.add(new Option(text,key)));
                select.value=current||'';const fieldIndex=panel.querySelectorAll('select').length;
                select.addEventListener('change',()=>{onChange(select.value||null);renderSelected();notify();const fields=selectedBox.querySelectorAll('.specimen-editor select');(caption==='Recolección'&&select.value==='TIMED'?fields[fieldIndex+1]:fields[fieldIndex])?.focus({preventScroll:true});});label.append(select);panel.append(label);
              };
              if(rule.specimen_mode==='REQUIRED_SELECTION')field('Muestra',rule.allowed_specimen_type_keys.map(key=>[key,specimen.specimen_types[key]]),value.specimen_type_key,key=>{if(key)value.specimen_type_key=key;else delete value.specimen_type_key;},true);
              if(rule.collection_mode==='REQUIRED_SELECTION')field('Recolección',rule.allowed_collection_modes.map(key=>[key,specimen.collection_modes[key]]),value.collection_mode,key=>{if(key)value.collection_mode=key;else delete value.collection_mode;delete value.requested_duration_minutes;},true);
              if(value.collection_mode==='TIMED')field('Duración solicitada',rule.allowed_duration_minutes.map(minutes=>[String(minutes),`${minutes/60} horas`]),String(value.requested_duration_minutes||''),key=>{if(key)value.requested_duration_minutes=Number(key);else delete value.requested_duration_minutes;},true);
              if(rule.source_site&&rule.source_site!=='NONE')field('Sitio de origen',rule.allowed_source_site_keys.map(key=>[key,specimen.source_sites[key]]),value.source_site_key,key=>{if(key)value.source_site_key=key;else delete value.source_site_key;delete value.source_site_text;},rule.source_site==='REQUIRED_SELECTION');
              if(rule.source_site_text_required_keys?.includes(value.source_site_key)){const label=document.createElement('label');label.textContent=value.source_site_key==='JOINT'?'Articulación':'Describe el sitio';const input=document.createElement('input');input.maxLength=120;input.required=true;input.value=value.source_site_text||'';input.addEventListener('input',()=>{value.source_site_text=input.value;state.textContent=specimenComplete(item)?'Datos de muestra completos':'Completar datos de muestra';notify();});label.append(input);panel.append(label);}
              row.append(panel);
            }
          }
        }
        (embedded?boxes.get(item.type==='canonical'?options.routing.studies[item.key]:item.routingGroup):selectedBox).append(row);
      });
    }
    function renderResults(){
      if(embedded){renderEmbedded();return;}
      resultBox.replaceChildren();
      if(!results.length){if(status.dataset.error!=='true')status.textContent=search.value.trim()?'No encontramos un estudio con ese nombre.':category.value?'No hay estudios catalogados todavía en esta categoría.':'No hay estudios catalogados disponibles.';return;}
      results.forEach(item=>{
        const added=selected.some(row=>row.type==='canonical'&&Number(row.id)===Number(item.study_type_id));
        const row=document.createElement('button');row.type='button';row.className='tax03c-result-row';row.dataset.tax03cId=String(item.study_type_id);
        row.setAttribute('aria-pressed',String(added));row.setAttribute('aria-label',resultLabel(item,added));
        row.disabled=readonly||added;
        const copy=resultCopy(item),mark=document.createElement('span');mark.textContent=added?'✓ Agregado':'Agregar';
        row.append(copy,mark);resultBox.append(row);
      });
    }
    const displayFold=value=>String(value||'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLocaleLowerCase('es').trim();
    const secondaryLabel=item=>{
      const common=String(item.common_display_name||'').trim(),primary=String(item.display_name_es||'').trim();
      if(!common)return '';
      const left=displayFold(primary),right=displayFold(common);
      return left.includes(right)||right.includes(left)?'':common;
    };
    const resultLabel=(item,added)=>`${added?'Agregado':'Agregar'} ${item.display_name_es}${secondaryLabel(item)?`, nombre común: ${secondaryLabel(item)}`:''}`;
    const resultCopy=item=>{
      const copy=document.createElement('span');copy.className='tax03c-result-copy';
      const name=document.createElement('strong');name.textContent=item.display_name_es;copy.append(name);
      const common=secondaryLabel(item);
      if(common){const secondary=document.createElement('small');secondary.className='tax03c-common-name';secondary.textContent=common;copy.append(secondary);}
      const panel=panels[item.study_type_key];
      if(panel?.status==='ACTIVE'){
        const type=document.createElement('small');type.className='tax03c-panel-type';type.textContent='Panel de laboratorio · 1 estudio';copy.append(type);
        if(item.study_type_key==='panel_quimica_6'){
          const components=document.createElement('small');components.className='tax03c-panel-components';components.textContent=panel.components.map(part=>part.label).join(' · ');copy.append(components);
        }
      }
      return copy;
    };
    const presetMatches=needle=>{
      if(!embedded||currentFamily!=='laboratory')return [];
      const query=displayFold(needle);
      return Object.entries(presets).filter(([,rule])=>rule.status==='ACTIVE'&&
        (query?[rule.display_name,...(rule.search_terms||[])].some(term=>displayFold(term).includes(query)||query.split(' ').every(token=>displayFold(term).split(' ').some(word=>word.startsWith(token))))
          :options.leafId===rule.navigation_leaf));
    };
    function renderPresetSection(matches){
      if(!matches.length)return;
      const section=document.createElement('section');section.className='tax03c-preset-section';section.setAttribute('aria-label','Atajos para agregar varios estudios');
      const heading=document.createElement('h5');heading.textContent='PRESETS · AGREGAN ESTUDIOS INDIVIDUALES';section.append(heading);
      matches.forEach(([key,rule])=>{
        const componentRows=rule.component_study_keys.map(component=>embeddedRows?.find(row=>row.study_type_key===component));
        if(componentRows.some(row=>!row))return;
        const already=componentRows.filter(row=>selected.some(item=>item.type==='canonical'&&Number(item.id)===Number(row.study_type_id))).length;
        const row=document.createElement('button');row.type='button';row.className='tax03c-preset-row';row.dataset.tax03cPreset=key;
        const copy=document.createElement('span');copy.className='tax03c-result-copy';
        const name=document.createElement('strong');name.textContent=rule.display_name;
        const composition=document.createElement('small');composition.textContent=componentRows.map(component=>component.display_name_es).join(' · ');
        const kind=document.createElement('small');kind.className='tax03c-preset-type';kind.textContent='Preset · agrega estudios individuales';copy.append(name,composition,kind);
        const action=document.createElement('span');action.textContent=already===componentRows.length?'✓ Todos agregados':`+ Agregar ${componentRows.length-already} ${componentRows.length-already===1?'estudio':'estudios'}${already?` · ${already} ya agregado${already===1?'':'s'}`:''}`;
        row.append(copy,action);row.disabled=readonly||already===componentRows.length;section.append(row);
      });resultBox.append(section);
    }
    const hierarchy=window.mxmedStudyNavigationHierarchyV2;
    const featuredLeaves=options.featuredNavigation?.leaves||{};
    const currentFamily=hierarchy?.root.find(root=>{
      const visit=id=>id===options.leafId||hierarchy.nodes[id]?.children.some(visit);
      return visit(root);
    })||featuredLeaves[options.leafId]?.root_family||'';
    const familyLabel=id=>id==='dental'?'Estudios dentales':({laboratory:'Laboratorio',imaging:'Imagenología',pathology:'Patología y biopsias',functional:'Estudios funcionales',procedures:'Procedimientos diagnósticos'})[id]||hierarchy?.nodes[id]?.label||id;
    const inParts=(item,parts)=>parts.some(part=>part.category===item.category_key&&(!part.keys||part.keys.includes(item.study_type_key)));
    const studyLocation=item=>{
      const dental=Object.entries(featuredLeaves).find(([,leaf])=>leaf.root_family==='dental'&&leaf.study_keys.includes(item.study_type_key));
      if(dental)return {root:'dental',leaf:dental[0],label:dental[1].heading||'Estudios dentales'};
      if(hierarchy)for(const root of hierarchy.root){
        if(!inParts(item,hierarchy.parts(root)))continue;
        const leaf=(function descend(id){
          for(const child of hierarchy.nodes[id]?.children||[]){
            if(inParts(item,hierarchy.parts(child)))return descend(child);
          }
          return id;
        })(root);
        return {root,leaf,label:hierarchy.nodes[leaf]?.label||hierarchy.nodes[root].label};
      }
      return null;
    };
    if(embedded&&options.initialQuery){search.value=String(options.initialQuery);}
    function renderEmbedded(){
      resultBox.replaceChildren();
      if(!embeddedRows)return;
      const leaf=!embeddedGlobal?options.featuredNavigation?.leaves?.[options.leafId]:null;
      const scope=embeddedGlobal?null:navigationParts;
      const needle=search.value.trim();
      const candidates=needle?embeddedSearchRows||[]:embeddedRows;
      results=candidates.filter(item=>needle&&currentFamily?studyLocation(item)?.root===currentFamily:!scope?.length||scope.some(part=>part.category===item.category_key&&(!part.keys||part.keys.has(item.study_type_key))));
      const visiblePresets=presetMatches(needle);
      status.hidden=!!leaf&&!needle;
      status.textContent=needle?`${results.length} ${results.length===1?'estudio encontrado':'estudios encontrados'}${visiblePresets.length?` · ${visiblePresets.length} preset${visiblePresets.length===1?'':'s'}`:''} para esta búsqueda.`:`${results.length} estudios disponibles.`;
      const rowFor=(item,location='')=>{
        const added=selected.some(row=>row.type==='canonical'&&Number(row.id)===Number(item.study_type_id));
        const row=document.createElement('button');row.type='button';row.className='tax03c-result-row';row.dataset.tax03cId=String(item.study_type_id);row.dataset.tax03cKey=item.study_type_key;
        row.setAttribute('aria-pressed',String(added));row.setAttribute('aria-label',resultLabel(item,added)+(location?`, ${location}`:''));
        row.disabled=readonly||added;
        const copy=resultCopy(item),mark=document.createElement('span');
        if(location){const place=document.createElement('small');place.className='tax03c-result-location';place.textContent=location;copy.append(place);}
        mark.textContent=added?'✓ Agregado':'+ Agregar';row.append(copy,mark);return row;
      };
      if(needle){
        renderPresetSection(visiblePresets);
        const sectionName=currentFamily?`Resultados en ${familyLabel(currentFamily)}`:'Resultados del catálogo';
        const same=document.createElement('section');same.className='tax03c-family-results';same.setAttribute('aria-label',sectionName);
        const heading=document.createElement('h5');heading.textContent=sectionName;same.append(heading);
        if(results.length)results.forEach(item=>{
          const location=studyLocation(item);
          same.append(rowFor(item,location?.leaf!==options.leafId?location?.label||'':''));
        });
        else{const empty=document.createElement('p');empty.className='tax03c-family-empty';empty.textContent=`No encontramos coincidencias en ${currentFamily?familyLabel(currentFamily):'el catálogo'}.`;same.append(empty);}
        resultBox.append(same);
        const outside=(embeddedSearchRows||[]).map(item=>({item,location:studyLocation(item)}))
          .filter(({location})=>currentFamily&&location&&location.root!==currentFamily);
        if(outside.length){
          const rescue=document.createElement('section');rescue.className='tax03c-rescue-results';rescue.setAttribute('aria-label','Coincidencias en otras familias');
          const rescueHeading=document.createElement('h5');rescueHeading.textContent='Coincidencias en otras familias';rescue.append(rescueHeading);
          outside.slice(0,rescueExpanded?undefined:6).forEach(({item,location})=>{
            const row=document.createElement('div');row.className='tax03c-rescue-row';
            const copy=resultCopy(item),place=document.createElement('small');place.className='tax03c-result-location';place.textContent=`${familyLabel(location.root)} / ${location.label}`;copy.append(place);
            const added=selected.some(chosen=>chosen.type==='canonical'&&Number(chosen.id)===Number(item.study_type_id));
            const action=document.createElement('button');action.type='button';action.className='tax03c-rescue-action';
            action.dataset.tax03cRescue=item.study_type_key;action.textContent=added?'✓ Agregado':`Ir a ${familyLabel(location.root)}`;
            action.setAttribute('aria-label',added?`${item.display_name_es} ya agregado`:`Ir a ${familyLabel(location.root)} para ${item.display_name_es}`);
            action.disabled=added;row.append(copy,action);rescue.append(row);
          });
          if(outside.length>6&&!rescueExpanded){const more=document.createElement('button');more.type='button';more.className='tax03c-rescue-more';more.dataset.tax03cRescueMore='';more.textContent='Ver más coincidencias';rescue.append(more);}
          resultBox.append(rescue);
        }
        if(leaf?.small){resultBox.append(customOpen,custom);}
        else if(embedded){
          if(!custom.hidden)resultBox.append(customOpen,custom);
          else{const hiddenCustom=document.createElement('div');hiddenCustom.hidden=true;
            hiddenCustom.append(customOpen,custom);resultBox.append(hiddenCustom);}
        }
        return;
      }
      renderPresetSection(visiblePresets);
      if(leaf?.small){
        results.forEach(item=>resultBox.append(rowFor(item)));
        resultBox.append(customOpen,custom);
        return;
      }
      const config=!embeddedGlobal?options.routing.catalog[options.leafId]:null;
      const featured=((leaf?leaf.featured:config?.featured)||[]).slice(0,6).map(key=>results.find(item=>item.study_type_key===key)).filter(Boolean);
      let featuredSection=null;
      if(featured.length){featuredSection=document.createElement('section');featuredSection.className='ordcomp-featured-section';featuredSection.hidden=fullOpen;
        const heading=document.createElement('h5');heading.textContent='COMUNES';featuredSection.append(heading);
        const box=document.createElement('div');box.dataset.ordcompFeatured='';featured.forEach(item=>box.append(rowFor(item)));featuredSection.append(box);resultBox.append(featuredSection);}
      const full=document.createElement('details');full.className='ordcomp-full-catalog';if(!custom.hidden)fullOpen=true;full.open=fullOpen;
      const summary=document.createElement('summary');summary.textContent='Ver catálogo completo';full.append(summary);full.addEventListener('toggle',()=>{fullOpen=full.open;if(featuredSection)featuredSection.hidden=fullOpen;});
      // Discovery uses the accepted hierarchy; operational routing remains independent.
      const covered=new Set(),discovery=[];
      const hierarchy=window.mxmedStudyNavigationHierarchyV2;
      if(!config&&hierarchy){
        Object.values(hierarchy.nodes).filter(node=>!node.children.length).forEach(node=>{
          const keys=results.filter(item=>!covered.has(item.study_type_key)&&hierarchy.parts(node.id).some(part=>part.category===item.category_key&&(!part.keys||part.keys.includes(item.study_type_key)))).map(item=>item.study_type_key);
          if(keys.length){keys.forEach(key=>covered.add(key));discovery.push({label:node.label,keys});}
        });
      }
      Object.entries(categories).forEach(([category,label])=>{
        const keys=results.filter(item=>item.category_key===category&&!covered.has(item.study_type_key)).map(item=>item.study_type_key);
        if(keys.length)discovery.push({label,keys});
      });
      const groups=leaf?.groups||config?.groups||discovery;
      groups.forEach(group=>{
        const items=results.filter(item=>group.keys.includes(item.study_type_key));if(!items.length)return;
        const details=document.createElement('details');details.dataset.catalogGroup=group.label;details.open=openGroup===group.label;
        const title=document.createElement('summary');title.textContent=`${group.label} (${items.length})`;details.append(title);
        items.forEach(item=>details.append(rowFor(item)));
        details.addEventListener('toggle',()=>{if(details.open){openGroup=group.label;full.querySelectorAll('[data-catalog-group]').forEach(other=>{if(other!==details)other.open=false;});}else if(openGroup===group.label)openGroup='';});full.append(details);
      });
      if(embedded)full.append(customOpen,custom);
      resultBox.append(full);
    }
    async function loadEmbedded(){
      if(destroyed)return;
      status.textContent=embeddedRows?'Buscando estudios…':'Cargando catálogo…';
      controller?.abort();controller=new AbortController();const seen=++request;
      try{
        const fetchAll=async query=>{
          const found=[];let offset=0,more=true;
          while(more){
            const params=new URLSearchParams({limit:'100',offset:String(offset)});if(query)params.set('search',query);
            const response=await fetch(`/api/clinical/index.php/doctors/${encodeURIComponent(doctorId)}/study-types?${params}`,{credentials:'same-origin',signal:controller.signal});
            const body=await response.json();if(!response.ok||!body.ok)throw new Error('CATALOG_UNAVAILABLE');
            const rows=body.data.items||[];found.push(...rows);offset+=rows.length;more=body.data.has_more&&rows.length>0;
          }
          return found;
        };
        if(!embeddedRows)embeddedRows=await fetchAll('');
        if(destroyed||seen!==request)return;
        const query=search.value.trim();embeddedSearchRows=query?await fetchAll(query):null;
        if(destroyed||seen!==request)return;renderEmbedded();
        if(focusPending){focusPending=false;
          const target=[...resultBox.querySelectorAll('[data-tax03c-key]')].find(row=>row.dataset.tax03cKey===options.focusStudyKey);
          if(target){target.classList.add('tax03c-navigation-target');target.focus({preventScroll:true});target.scrollIntoView({block:'nearest'});}
        }
      }catch(error){if(error.name!=='AbortError'){status.textContent='No se pudo cargar el catálogo. Vuelve a esta familia para reintentar.';resultBox.replaceChildren();}}
    }
    function renderCategories(rows){
      const current=category.value;category.replaceChildren(new Option('Todas las categorías',''));
      (rows||[]).filter(row=>Number(row.active_count)>0&&
        (!dentalScope?.length||globalCatalog||dentalScope.some(part=>part.category===row.category_key))&&
        (!labScope?.length||laboratoryGlobal||labScope.some(part=>part.category===row.category_key)))
        .forEach(row=>category.add(new Option(categories[row.category_key]||row.label_es,row.category_key)));
      category.value=current;
      $('[data-tax03c-all]').setAttribute('aria-pressed',String(!category.value&&!navigationParts?.length));
    }
    async function load(append=false){
      if(embedded){return loadEmbedded();}
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
        const activeParts=labScope?.length&&!laboratoryGlobal
          ?laboratoryAll?labScope:navigationParts
          :dentalScope?.length&&!globalCatalog
            ?navigationParts?.length&&!search.value.trim()&&!category.value?navigationParts:dentalScope
            :navigationParts?.length&&!search.value.trim()&&!category.value?navigationParts:null;
        if(activeParts?.length){
          const known=new Set(results.map(item=>String(item.study_type_id)));
          const relevant=category.value?activeParts.filter(part=>part.category===category.value):activeParts;
          while(page.length<30&&navigationPart<relevant.length){
            const part=relevant[navigationPart];
            const params=new URLSearchParams({limit:'30',offset:String(navigationOffset),search:search.value.trim(),category:part.category});
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
          hasMore=navigationPart<relevant.length;
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
      if(customRoute&&!customRoute.value){
        const scoped=embeddedGlobal?[]:(embeddedRows||[]).filter(item=>navigationParts?.some(part=>part.category===item.category_key&&(!part.keys||part.keys.has(item.study_type_key))));
        const groups=[...new Set(scoped.map(item=>options.routing.studies[item.study_type_key]))];
        if(groups.length===1)customRoute.value=groups[0];
      }
      customName.focus();}
    function addCustom(){
      const cat=$('[data-tax03c-custom-category]').value,name=customName.value.trim(),note=$('[data-tax03c-custom-note]').value.trim();
      if(!cat||!categories[cat]){$('[data-tax03c-custom-error]').textContent='Selecciona una categoría.';return;}
      if(!name){$('[data-tax03c-custom-error]').textContent='Escribe el nombre del estudio.';customName.focus();return;}
      if(selected.some(row=>row.type==='custom'&&row.category===cat&&row.name.toLocaleLowerCase('es')===name.toLocaleLowerCase('es'))){$('[data-tax03c-custom-error]').textContent='Este estudio ya está agregado.';return;}
      if(selected.length>=100){$('[data-tax03c-custom-error]').textContent='Máximo 100 estudios por orden.';return;}
      if(customRoute&&!customRoute.value){$('[data-tax03c-custom-error]').textContent='Selecciona el servicio que realizará el estudio.';return;}
      selected.push({type:'custom',category:cat,name,note,...(embedded?{routingGroup:customRoute.value,routingConfirmed:true}:{})});custom.hidden=true;customName.value='';$('[data-tax03c-custom-note]').value='';$('[data-tax03c-custom-category]').value='';if(customRoute)customRoute.value='';
      renderSelected();notify();
    }
    const onClick=event=>{
      const target=event.target.closest('button,[data-tax03c-custom-open]');if(!target||readonly)return;
      if(target.dataset.tax03cPreset){
        const key=target.dataset.tax03cPreset,rule=presets[key];if(rule?.status!=='ACTIVE'||!embeddedRows)return;
        const components=rule.component_study_keys.map(component=>embeddedRows.find(row=>row.study_type_key===component));
        if(components.some(component=>!component||options.routing.studies[component.study_type_key]!=='CLINICAL_LAB')){status.textContent='Este preset no está disponible en el catálogo actual.';return;}
        const missing=components.filter(component=>!selected.some(item=>item.type==='canonical'&&Number(item.id)===Number(component.study_type_id)));
        if(selected.length+missing.length>100){status.textContent='Máximo 100 estudios por composición.';return;}
        missing.forEach(component=>selected.push({type:'canonical',id:Number(component.study_type_id),key:component.study_type_key,name:component.display_name_es,category:component.category_key}));
        if(!presetApplications.some(app=>app.preset_key===key))presetApplications.push({preset_key:key,preset_version:rule.preset_version});
        options.onPresetApplications?.(clone(presetApplications));renderSelected();renderResults();notify();
        status.textContent=`${missing.length} ${missing.length===1?'estudio agregado':'estudios agregados'}${components.length-missing.length?` · ${components.length-missing.length} ya incluidos`:''}.`;
        search.focus({preventScroll:true});return;
      }
      if(target.dataset.tax03cRescue){const row=(embeddedSearchRows||[]).find(item=>item.study_type_key===target.dataset.tax03cRescue),location=row&&studyLocation(row);
        if(location)options.onNavigateToStudy?.({rootId:location.root,leafId:location.leaf,query:search.value.trim(),studyKey:row.study_type_key,category:row.category_key});return;}
      if(target.hasAttribute('data-tax03c-rescue-more')){rescueExpanded=true;renderEmbedded();return;}
      if(target.dataset.tax03cId){const row=results.find(item=>String(item.study_type_id)===target.dataset.tax03cId);if(!row)return;
        if(selected.some(item=>item.type==='canonical'&&Number(item.id)===Number(row.study_type_id)))return;
        if(!imagingAuthority&&imaging&&row.category_key==='IMAGEN'){status.textContent='Cargando parámetros de imagen. Intenta de nuevo en un momento.';return;}
        if(selected.length>=100){status.textContent=embedded?'Máximo 100 estudios por composición.':'Máximo 100 estudios por orden.';return;}
        const pathologyRuleForRow=pathology?.ruleFor(row.study_type_key,pathologyAuthority);
        selected.push({type:'canonical',id:Number(row.study_type_id),key:row.study_type_key,name:row.display_name_es,category:row.category_key,
          ...(row.study_type_key==='arterial_blood_gas'?{oxygenContext:{version:1,mode:'UNKNOWN'}}:{}),
          ...(pathologyRuleForRow?{pathologyParameters:pathology.initial(row.study_type_key,pathologyAuthority)}:{}),...(imagingRule({type:'canonical',key:row.study_type_key})?.status==='ACTIVE_REQUIRED'?{imagingParameters:imaging.initial(row.study_type_key,imagingAuthority)}:{})});
        const kind=window.mxmedDentalLocationV1?.kindFor(row.study_type_key),rule=specimen?.studies?.[row.study_type_key];editingIndex=(kind&&kind!=='NONE'||rule?.specimen_mode==='REQUIRED_SELECTION'||rule?.collection_mode==='REQUIRED_SELECTION'||rule?.source_site==='REQUIRED_SELECTION')?selected.length-1:-1;
        if(pathologyRuleForRow)editingIndex=selected.length-1;
        if(imagingRule(selected.at(-1))?.status==='ACTIVE_REQUIRED')editingIndex=selected.length-1;
        const position=results.findIndex(item=>Number(item.study_type_id)===Number(row.study_type_id));
        renderSelected();renderResults();notify();
        const following=results.slice(position+1).find(item=>!selected.some(chosen=>chosen.type==='canonical'&&Number(chosen.id)===Number(item.study_type_id)));
        const next=following&&resultBox.querySelector(`[data-tax03c-id="${following.study_type_id}"]:not(:disabled)`);
        (next||search).focus({preventScroll:true});}
      if(target.dataset.tax03cDental!==undefined){const index=Number(target.dataset.tax03cDental);editingIndex=editingIndex===index?-1:index;activeDentalEditor=null;renderSelected();selectedBox.querySelector(`[data-tax03c-dental="${index}"]`)?.focus({preventScroll:true});}
      if(target.dataset.tax03cSpecimen!==undefined){const index=Number(target.dataset.tax03cSpecimen);editingIndex=editingIndex===index?-1:index;renderSelected();selectedBox.querySelector(`[data-tax03c-specimen="${index}"]`)?.focus({preventScroll:true});}
      if(target.dataset.tax03cPathology!==undefined){const index=Number(target.dataset.tax03cPathology);editingIndex=editingIndex===index?-1:index;renderSelected();selectedBox.querySelector(`[data-tax03c-pathology="${index}"]`)?.focus({preventScroll:true});}
      if(target.dataset.tax03cImaging!==undefined){const index=Number(target.dataset.tax03cImaging);editingIndex=editingIndex===index?-1:index;renderSelected();selectedBox.querySelector(`[data-tax03c-imaging="${index}"]`)?.focus({preventScroll:true});}
      if(target.dataset.tax03cRemove!==undefined){const index=Number(target.dataset.tax03cRemove);selected.splice(index,1);
        presetApplications=presetApplications.filter(app=>presets[app.preset_key]?.component_study_keys.every(key=>selected.some(item=>item.type==='canonical'&&item.key===key)));
        options.onPresetApplications?.(clone(presetApplications));editingIndex=editingIndex===index?-1:editingIndex>index?editingIndex-1:editingIndex;activeDentalEditor=null;renderSelected();renderResults();notify();}
      if(target.hasAttribute('data-tax03c-custom-open')){event.preventDefault();openCustom();}
      if(target.hasAttribute('data-tax03c-custom-cancel')){custom.hidden=true;customName.value='';$('[data-tax03c-custom-note]').value='';$('[data-tax03c-custom-category]').value='';if(customRoute)customRoute.value='';}
      if(target.hasAttribute('data-tax03c-custom-add'))addCustom();
      if(target.hasAttribute('data-tax03c-more')&&hasMore)load(true);
      if(target.hasAttribute('data-tax03c-all')){
        navigationParts=labScope?.length&&!laboratoryGlobal?labScope:null;
        if(labScope?.length&&!laboratoryGlobal)laboratoryAll=true;
        renderNavigationScope();category.value='';target.setAttribute('aria-pressed','true');load();
      }
      if(target.hasAttribute('data-tax03c-lab-all')&&labScope?.length){laboratoryAll=true;laboratoryGlobal=false;navigationParts=labScope;search.value='';category.value='';renderNavigationScope();load();search.focus();}
      if(target.hasAttribute('data-tax03c-lab-back')&&labScope?.length){laboratoryAll=true;laboratoryGlobal=false;navigationParts=labScope;search.value='';category.value='';renderNavigationScope();load();search.focus();}
      if(embedded&&target.hasAttribute('data-tax03c-global')){embeddedGlobal=true;search.value='';renderNavigationScope();load();return;}
      if(embedded&&target.hasAttribute('data-tax03c-dental-back')){embeddedGlobal=false;search.value='';renderNavigationScope();load();return;}
      if(target.hasAttribute('data-tax03c-global')&&labScope?.length){laboratoryGlobal=true;navigationParts=null;search.value='';category.value='';renderNavigationScope();load();search.focus();}
      if(target.hasAttribute('data-tax03c-global')&&dentalScope?.length){globalCatalog=true;navigationParts=null;search.value='';category.value='';renderNavigationScope();load();search.focus();}
      if(target.hasAttribute('data-tax03c-dental-back')&&dentalScope?.length){globalCatalog=false;navigationParts=null;search.value='';category.value='';renderNavigationScope();load();search.focus();}
    };
    host.addEventListener('click',onClick);if(options.selectionHost)selectedBox.addEventListener('click',onClick);
    search.addEventListener('input',()=>{
      clearTimeout(timer);controller?.abort();request++;
      resultBox.replaceChildren();results=[];embeddedSearchRows=null;rescueExpanded=false;status.textContent='Buscando estudios…';
      timer=setTimeout(()=>load(),250);
    });
    category.addEventListener('change',()=>{if(!labScope?.length||laboratoryGlobal)navigationParts=null;renderNavigationScope();$('[data-tax03c-all]').setAttribute('aria-pressed',String(!category.value));load();});
    priority.addEventListener('change',notify);indication.addEventListener('input',notify);
    renderSelected();if(!imagingAuthority&&imaging)imaging.authority().then(config=>{if(destroyed)return;imagingAuthority=config;renderSelected();renderResults();if(readonly)host.querySelectorAll('input,select,textarea,button').forEach(control=>control.disabled=true);}).catch(()=>{if(!destroyed)status.textContent='No se pudieron cargar los parámetros de imagen.';});if(readonly){host.querySelectorAll('input,select,textarea,button').forEach(control=>control.disabled=true);}else load();
    return {
      customDraft,
      selected:()=>clone(selected),priority:()=>priority.value,indication:()=>indication.value,
      orderItems:()=>selected.map(item=>item.type==='canonical'?{study_type_id:item.id,study_type_key:item.key,...(item.dentalLocation?{dental_location:clone(item.dentalLocation)}:{}),...(item.specimenRequirements?{specimen_collection_requirements:clone(item.specimenRequirements)}:{}),...(item.pathologyParameters?{pathology_order_parameters:clone(item.pathologyParameters)}:{}),...(item.imagingParameters?{imaging_order_parameters:clone(item.imagingParameters)}:{}),...(item.key==='arterial_blood_gas'?{lab_arterial_oxygen_context:clone(item.oxygenContext||{version:1,mode:'UNKNOWN'})}:{})}:{study_category:item.category,study_display_name:item.name,...(item.note?{note:item.note}:{}),...(embedded?{custom_routing_confirmed:item.routingConfirmed===true}:{})}),
      valid:()=>selected.length>0&&selected.length<=100&&selected.every(item=>{
        const kind=item.type==='canonical'?window.mxmedDentalLocationV1?.kindFor(item.key):null;
        return (!kind||window.mxmedDentalLocationV1.isComplete(kind,item.dentalLocation))&&specimenComplete(item)&&pathologyComplete(item)&&imagingComplete(item)&&oxygenComplete(item);
      })&&(!activeDentalEditor||activeDentalEditor.valid()),
      validationMessage:()=>selected.length?(selected.some(item=>!oxygenComplete(item))?'Revisa el contexto de oxígeno y la FiO₂ de la gasometría.':selected.some(item=>!imagingComplete(item))?'Completa los parámetros de imagen pendientes antes de continuar.':selected.some(item=>!pathologyComplete(item))?'Completa los parámetros de patología pendientes antes de continuar.':selected.some(item=>!specimenComplete(item))?'Completa los datos de muestra pendientes antes de continuar.':'Configura la ubicación de cada estudio dental pendiente antes de solicitar la orden.'):'Agrega al menos un estudio antes de solicitar la orden.',
      destroy:()=>{destroyed=true;clearTimeout(timer);controller?.abort();host.removeEventListener('click',onClick);if(options.selectionHost)selectedBox.removeEventListener('click',onClick);},
    };
  }
  window.mxmedStudyComposer={mount,title,documentType,orderArea,categories};
})();
