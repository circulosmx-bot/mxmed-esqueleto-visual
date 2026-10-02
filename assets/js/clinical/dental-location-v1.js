// CAT02: study-order location only. FDI names/codes come from one shared JSON authority.
(function () {
  'use strict';
  const types=Object.freeze({
    dental_cbct:'CBCT',dental_panoramic_xray:'NONE',dental_cephalometric_xray:'NONE',
    tmj_comparative_xray:'TMJ',dental_intraoral_scan:'SCAN',
    dental_clinical_photographs:'PHOTO',dental_study_model:'MODEL'
  });
  let authorityPromise;
  const authority=()=>authorityPromise ||= fetch('/assets/data/clinical/dental-fdi-iso3950-v1.json',{
    credentials:'same-origin',headers:{Accept:'application/json'}
  }).then(response=>{if(!response.ok)throw new Error('FDI_UNAVAILABLE');return response.json();})
    .then(data=>{if(data.authority!=='FDI_ISO_3950'||data.contract_version!==1||data.teeth?.length!==52)throw new Error('FDI_INVALID');return data;});
  const labels={LOCALIZED:'Zona localizada',MAXILLARY_ARCH:'Maxilar superior',MANDIBULAR_ARCH:'Mandíbula',
    BOTH_ARCHES:'Ambos maxilares',MAXILLOFACIAL:'Maxilofacial',MAXILLARY:'Maxilar superior',
    MANDIBULAR:'Mandíbula',BOTH:'Ambos maxilares',LATERAL:'Vista lateral',PA:'Vista posteroanterior',
    INTRAORAL:'Intraorales',EXTRAORAL:'Extraorales'};
  const options=(values,chosen)=>values.map(([value,label])=>`<option value="${value}" ${chosen===value?'selected':''}>${label}</option>`).join('');
  const coverageOptions=[['','Selecciona cobertura'],['LOCALIZED','Zona localizada'],['MAXILLARY_ARCH','Maxilar superior'],
    ['MANDIBULAR_ARCH','Mandíbula'],['BOTH_ARCHES','Ambos maxilares'],['MAXILLOFACIAL','Maxilofacial']];
  const archOptions=[['','Selecciona arco'],['MAXILLARY','Maxilar superior'],['MANDIBULAR','Mandíbula'],['BOTH','Ambos maxilares']];
  const safe=value=>String(value||'').replace(/[&<>"']/g,character=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[character]));
  const kindFor=key=>types[key]||null;
  function mount(host,kind,initial,onChange){
    const state={...initial,selected_teeth:[...(initial?.selected_teeth||[])]};
    let viewMode=initial?.dentition_mode||'PERMANENT',teeth=null;
    const selected=new Set(state.selected_teeth);
    const sortCodes=()=>[...selected].sort((a,b)=>Number(a)-Number(b));
    function effectiveMode(){
      if(!teeth||!selected.size)return viewMode;
      const modes=new Set(sortCodes().map(code=>teeth.get(code)?.dentition));
      return modes.size>1||viewMode==='MIXED'?'MIXED':modes.values().next().value||viewMode;
    }
    function location(){
      const base={contract_version:1,numbering_system:'FDI_ISO_3950'};
      if(kind==='CBCT'){
        return {...base,dentition_mode:effectiveMode(),coverage:state.coverage||null,
          selected_teeth:sortCodes(),...(state.anatomical_region?.trim()?{anatomical_region:state.anatomical_region.trim()}:{}),
          ...(state.fov_cm?.trim()?{fov_cm:state.fov_cm.trim()}:{} )};
      }
      if(kind==='SCAN')return {...base,arch:state.arch||null};
      if(kind==='TMJ')return {...base,projection:state.projection||null};
      if(kind==='PHOTO')return {...base,photograph_scope:state.photograph_scope||null};
      if(kind==='MODEL')return state.arch?{...base,arch:state.arch}:null;
      return null;
    }
    function issue(){
      if(kind==='CBCT'){
        if(!state.coverage)return 'Selecciona la cobertura del estudio.';
        if(state.coverage==='LOCALIZED'&&!selected.size&&!state.anatomical_region?.trim())return 'Selecciona una pieza o describe la región localizada.';
        if(state.coverage!=='LOCALIZED'&&(selected.size||state.anatomical_region?.trim()))return 'Las piezas y la región localizada requieren cobertura “Zona localizada”. Limpia esa selección o cambia la cobertura.';
        if(state.fov_cm?.trim()&&!/^(?:[1-9]|[12][0-9]|30)(?:\.[0-9])?x(?:[1-9]|[12][0-9]|30)(?:\.[0-9])?$/.test(state.fov_cm.trim()))return 'Escribe el campo opcional en centímetros, por ejemplo 8x8.';
      }
      if(kind==='SCAN'&&!state.arch)return 'Selecciona el arco del escaneo.';
      if(kind==='TMJ'&&!state.projection)return 'Selecciona la vista de ATM.';
      if(kind==='PHOTO'&&!state.photograph_scope)return 'Selecciona el tipo de fotografías.';
      return '';
    }
    function emit(){
      const warning=host.querySelector('[data-dental-warning]');if(warning)warning.textContent=issue();
      onChange?.(location());
    }
    function toothButton(tooth){
      const selectedNow=selected.has(tooth.code),button=document.createElement('button');
      button.type='button';button.className='dental-tooth';button.dataset.tooth=tooth.code;
      button.setAttribute('aria-pressed',String(selectedNow));
      button.setAttribute('aria-label',`Pieza ${tooth.code}, ${tooth.name_es}, ${selectedNow?'seleccionada':'no seleccionada'}`);
      button.title=`${tooth.code} · ${tooth.name_es}`;
      button.innerHTML='<svg viewBox="0 0 32 36" aria-hidden="true" focusable="false"><path d="M6 4 C9 1 12 4 16 4 C20 4 23 1 26 4 C29 8 27 14 24 19 L23 30 Q21 35 18 28 L16 22 L14 28 Q11 35 9 30 L8 19 C5 14 3 8 6 4 Z" /></svg><span>'+tooth.code+'</span>';
      return button;
    }
    function arch(label,right,left){
      const section=document.createElement('section');section.className='dental-arch';section.setAttribute('aria-label',label);
      const heading=document.createElement('strong');heading.className='dental-arch-label';heading.textContent=label;section.append(heading);
      const lower=label.includes('inferior'),half=right.length,total=right.length+left.length;
      [[right,'Derecha del paciente'],[left,'Izquierda del paciente']].forEach(([group,name],halfIndex)=>{
        const quadrant=document.createElement('div');quadrant.className='dental-quadrant';quadrant.setAttribute('aria-label',name);
        quadrant.style.setProperty('--tooth-count',String(group.length));
        group.forEach((tooth,index)=>{
          const control=toothButton(tooth),distance=Math.abs(halfIndex*half+index-(total-1)/2)/((total-1)/2);
          const rise=Math.round(18*(1-distance*distance));
          control.style.setProperty('--curve',`${lower?rise:18-rise}px`);quadrant.append(control);
        });
        section.append(quadrant);
      });
      return section;
    }
    function drawTeeth(){
      const box=host.querySelector('[data-dental-arches]');if(!box||!teeth)return;
      box.replaceChildren();
      const rows=Object.values(Object.fromEntries([...teeth.values()].map(t=>[t.code,t])));
      const q=n=>rows.filter(t=>t.quadrant===n).sort((a,b)=>n===1||n===4||n===5||n===8?b.position-a.position:a.position-b.position);
      if(viewMode==='PERMANENT'||viewMode==='MIXED'){
        box.append(arch('Arcada superior permanente',q(1),q(2)),arch('Arcada inferior permanente',q(4),q(3)));
      }
      if(viewMode==='DECIDUOUS'||viewMode==='MIXED'){
        box.append(arch('Arcada superior temporal',q(5),q(6)),arch('Arcada inferior temporal',q(8),q(7)));
      }
      const summary=host.querySelector('[data-dental-summary]');const codes=sortCodes();
      summary.replaceChildren();
      if(codes.length){
        const p=document.createElement('p');p.textContent=`Piezas seleccionadas: ${codes.join(', ')}`;summary.append(p);
        const details=document.createElement('details');if(codes.length<=3)details.open=true;
        const label=document.createElement('summary');label.textContent='Ver nombres de las piezas';details.append(label);
        const list=document.createElement('ul');codes.forEach(code=>{const item=document.createElement('li');item.textContent=`${code} · ${teeth.get(code)?.name_es||''}`;list.append(item);});
        details.append(list);summary.append(details);
      }
      const clear=host.querySelector('[data-dental-clear]');if(clear)clear.hidden=!codes.length;
    }
    function render(){
      if(kind==='CBCT'){
        const showLocation=state.coverage==='LOCALIZED'||selected.size>0||!!state.anatomical_region?.trim();
        host.innerHTML=`<section class="dental-location" aria-label="Ubicación del estudio dental">
          <label>Cobertura del Cone Beam<select data-dental-field="coverage">${options(coverageOptions,state.coverage)}</select></label>
          ${showLocation?`<div class="dental-tooth-panel"><div class="dental-mode" role="group" aria-label="Dentición visible">${[['PERMANENT','Permanente'],['DECIDUOUS','Temporal'],['MIXED','Mixta']].map(([mode,name])=>`<button type="button" data-dental-mode="${mode}" aria-pressed="${viewMode===mode}">${name}</button>`).join('')}</div>
            <p class="dental-orientation"><span>← Derecha del paciente</span><span>Izquierda del paciente →</span></p>
            <div class="dental-arches" data-dental-arches role="group" aria-label="Selector gráfico de piezas dentales"></div>
            <div class="dental-selection-summary" data-dental-summary aria-live="polite"></div>
            <button type="button" class="dental-clear" data-dental-clear hidden>Limpiar selección</button>
            <label>Región anatómica, si no eliges piezas<input data-dental-field="anatomical_region" maxlength="120" value="${safe(state.anatomical_region)}" placeholder="Describe la región localizada"></label>
          </div>`:''}
          <label>Campo de visión opcional (cm)<input data-dental-field="fov_cm" maxlength="9" value="${safe(state.fov_cm)}" placeholder="Ej. 8x8"></label>
          <p class="dental-warning" data-dental-warning role="status"></p></section>`;
        drawTeeth();
      }else{
        const field=kind==='TMJ'?`<label>Vista de ATM<select data-dental-field="projection">${options([['','Selecciona vista'],['LATERAL','Lateral'],['PA','Posteroanterior (PA)']],state.projection)}</select></label>`:
          kind==='PHOTO'?`<label>Tipo de fotografías<select data-dental-field="photograph_scope">${options([['','Selecciona tipo'],['INTRAORAL','Intraorales'],['EXTRAORAL','Extraorales'],['BOTH','Intraorales y extraorales']],state.photograph_scope)}</select></label>`:
          `<label>${kind==='MODEL'?'Arco del modelo (opcional)':'Arco del escaneo'}<select data-dental-field="arch">${options(kind==='MODEL'?[['','Sin arco especificado'],...archOptions.slice(1)]:archOptions,state.arch)}</select></label>`;
        host.innerHTML=`<section class="dental-location" aria-label="Parámetros del estudio dental">${field}<p class="dental-warning" data-dental-warning role="status"></p></section>`;
      }
      emit();
    }
    host.addEventListener('click',event=>{
      const tooth=event.target.closest('[data-tooth]');
      if(tooth){const code=tooth.dataset.tooth;selected.has(code)?selected.delete(code):selected.add(code);drawTeeth();host.querySelector(`[data-tooth="${code}"]`)?.focus({preventScroll:true});emit();return;}
      const mode=event.target.closest('[data-dental-mode]');
      if(mode){viewMode=mode.dataset.dentalMode;render();host.querySelector(`[data-dental-mode="${viewMode}"]`)?.focus({preventScroll:true});return;}
      if(event.target.closest('[data-dental-clear]')){selected.clear();drawTeeth();emit();}
    });
    host.addEventListener('change',event=>{const field=event.target.dataset.dentalField;if(!field)return;state[field]=event.target.value;render();host.querySelector(`[data-dental-field="${field}"]`)?.focus({preventScroll:true});});
    host.addEventListener('input',event=>{const field=event.target.dataset.dentalField;if(!field||event.target.tagName==='SELECT')return;state[field]=event.target.value;emit();});
    if(kind==='CBCT'){
      host.textContent='Cargando mapa dental…';
      authority().then(data=>{if(!host.isConnected)return;teeth=new Map(data.teeth.map(t=>[t.code,t]));render();})
        .catch(()=>{if(host.isConnected)host.textContent='No se pudo cargar la numeración dental. Vuelve a abrir la solicitud.';});
    }else render();
    return {location,valid:()=>!!teeth||kind!=='CBCT'?issue()==='':false,issue,kind};
  }
  function summary(location){
    if(!location)return '';
    const parts=[];
    if(location.coverage)parts.push(labels[location.coverage]||'');
    else if(location.arch)parts.push(labels[location.arch]||'');
    if(location.projection)parts.push(labels[location.projection]||'');
    if(location.photograph_scope)parts.push(location.photograph_scope==='BOTH'?'Intraorales y extraorales':labels[location.photograph_scope]||'');
    if(location.selected_teeth?.length)parts.push(`Piezas ${location.selected_teeth.join(', ')}`);
    if(location.anatomical_region)parts.push(`Región: ${location.anatomical_region}`);
    if(location.fov_cm)parts.push(`Campo: ${location.fov_cm} cm`);
    return parts.filter(Boolean).join(' · ');
  }
  function isComplete(kind,location){
    if(kind==='NONE'||kind==='MODEL')return true;
    if(!location||location.contract_version!==1||location.numbering_system!=='FDI_ISO_3950')return false;
    if(kind==='CBCT')return !!location.coverage &&
      (location.coverage!=='LOCALIZED'||!!location.selected_teeth?.length||!!location.anatomical_region) &&
      (location.coverage==='LOCALIZED'||(!location.selected_teeth?.length&&!location.anatomical_region));
    if(kind==='SCAN')return ['MAXILLARY','MANDIBULAR','BOTH'].includes(location.arch);
    if(kind==='TMJ')return ['LATERAL','PA'].includes(location.projection);
    if(kind==='PHOTO')return ['INTRAORAL','EXTRAORAL','BOTH'].includes(location.photograph_scope);
    return true;
  }
  window.mxmedDentalLocationV1=Object.freeze({kindFor,mount,summary,authority,isComplete});
})();
