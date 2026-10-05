/* Dental study adapter for the reusable selector. Historical V1 values remain readable and unchanged until edited. */
(function(){
  'use strict';
  const legacy=window.mxmedDentalLocationV1;
  const selector=window.mxmedDentalOdontogramV2;
  if(!legacy||!selector)return;
  const policies={CBCT:'dental_cbct',TMJ:'tmj_comparative_xray',SCAN:'dental_intraoral_scan',MODEL:'dental_study_model',
    PERIAPICAL:'dental_periapical_xray',BITEWING:'dental_bitewing_xray',OCCLUSAL:'dental_occlusal_xray'};
  const kinds={dental_periapical_xray:'PERIAPICAL',dental_bitewing_xray:'BITEWING',dental_occlusal_xray:'OCCLUSAL'};
  const coverageFor=value=>{
    if(value.location_type==='TOOTH_LOCATION'||value.location_type==='QUADRANT_LOCATION')return 'LOCALIZED';
    if(value.location_type==='ARCH_LOCATION')return {MAXILLARY:'MAXILLARY_ARCH',MANDIBULAR:'MANDIBULAR_ARCH',BOTH_ARCHES:'BOTH_ARCHES'}[value.arch_key];
    if(value.location_type==='TMJ_LOCATION')return 'TMJ';
    return value.region_key==='MAXILLOFACIAL'?'MAXILLOFACIAL':'LOCALIZED';
  };
  function initialV2(kind,value){
    if(!value||value.contract_version===2)return value;
    if(kind==='CBCT'){
      const base={contract_version:2};
      if(value.selected_teeth?.length)return {...base,location_type:'TOOTH_LOCATION',selection_mode:value.selected_teeth.length===1?'SINGLE_TOOTH':'MULTIPLE_TEETH',numbering_system:'FDI_ISO_3950',dentition_mode:value.dentition_mode==='DECIDUOUS'?'PRIMARY':value.dentition_mode||'PERMANENT',tooth_fdi_codes:value.selected_teeth};
      if(value.coverage==='LOCALIZED'&&value.anatomical_region)return {...base,location_type:'REGION_LOCATION',selection_mode:'REGION',region_key:'OTHER_SPECIFIED',region_detail:value.anatomical_region,
        ...(value.dentition_mode?{dentition_mode:value.dentition_mode==='DECIDUOUS'?'PRIMARY':value.dentition_mode}:{}),
        ...(value.arch?{arch_key:value.arch==='BOTH'?'BOTH_ARCHES':value.arch}:{})};
      if(value.coverage==='MAXILLOFACIAL')return {...base,location_type:'REGION_LOCATION',selection_mode:'REGION',region_key:'MAXILLOFACIAL'};
      if(value.coverage!=='LOCALIZED')return {...base,location_type:'ARCH_LOCATION',selection_mode:'ARCH',arch_key:value.arch==='MANDIBULAR'?'MANDIBULAR':value.arch==='BOTH'?'BOTH_ARCHES':'MAXILLARY'};
    }
    if((kind==='SCAN'||kind==='MODEL')&&value.arch)return {contract_version:2,location_type:'ARCH_LOCATION',selection_mode:'ARCH',arch_key:value.arch==='BOTH'?'BOTH_ARCHES':value.arch};
    return null;
  }
  function summary(value){
    if(!value||value.contract_version!==2)return legacy.summary(value);
    const labels={MAXILLARY:'Maxilar superior',MANDIBULAR:'Mandíbula',BOTH_ARCHES:'Ambas arcadas',UPPER_RIGHT:'Superior derecha',UPPER_LEFT:'Superior izquierda',LOWER_LEFT:'Inferior izquierda',LOWER_RIGHT:'Inferior derecha',LEFT:'Izquierda',RIGHT:'Derecha',BILATERAL:'Bilateral',ANTERIOR:'Anterior',POSTERIOR:'Posterior',MAXILLOFACIAL:'Maxilofacial',OTHER_SPECIFIED:'Otra región'};
    const parts=[];
    if(value.tooth_fdi_codes)parts.push('Piezas '+value.tooth_fdi_codes.join(', '));
    if(value.quadrant_key)parts.push('Cuadrante '+labels[value.quadrant_key]);
    if(value.arch_key)parts.push(labels[value.arch_key]);
    if(value.region_key)parts.push('Región '+(value.region_detail||labels[value.region_key]));
    if(value.tmj_side)parts.push('ATM '+labels[value.tmj_side]);
    if(value.projection)parts.push('Vista '+(value.projection==='PA'?'posteroanterior':'lateral'));
    if(value.fov_cm)parts.push('Campo '+value.fov_cm+' cm');
    return parts.join(' · ');
  }
  function isComplete(kind,value){
    if(kind==='CBCT'&&value?.fov_cm&&!/^(?:[1-9]|[12][0-9]|30)(?:\.[0-9])?x(?:[1-9]|[12][0-9]|30)(?:\.[0-9])?$/.test(value.fov_cm))return false;
    if(!value||value.contract_version!==2)return legacy.isComplete(kind,value);
    if(kind==='CBCT')return !!value.coverage&&!!value.location_type&&
      (value.location_type!=='TOOTH_LOCATION'||!!value.tooth_fdi_codes?.length)&&
      (value.location_type!=='REGION_LOCATION'||!!value.region_key);
    if(kind==='TMJ')return !!value.tmj_side&&['LATERAL','PA'].includes(value.projection);
    if(kind==='SCAN')return !!value.arch_key;
    if(kind==='MODEL')return !value||!!value.arch_key;
    if(kind==='PERIAPICAL')return value.location_type==='TOOTH_LOCATION'&&value.tooth_fdi_codes?.length>=1&&value.tooth_fdi_codes.length<=8&&!!value.dentition_mode;
    if(kind==='BITEWING')return value.location_type==='REGION_LOCATION'&&value.region_key==='POSTERIOR'&&value.arch_key==='BOTH_ARCHES'&&['LEFT','RIGHT','BILATERAL'].includes(value.side_key)&&!!value.dentition_mode;
    if(kind==='OCCLUSAL')return value.location_type==='ARCH_LOCATION'&&['MAXILLARY','MANDIBULAR'].includes(value.arch_key)&&!!value.dentition_mode;
    return legacy.isComplete(kind,value);
  }
  function mount(host,kind,initial,onChange){
    if(!policies[kind])return legacy.mount(host,kind,initial,onChange);
    let current=initial||null,inner=null,loaded=false,destroyed=false,dirty=false;
    const issue=()=>{
      if(!loaded)return 'Cargando autoridad dental…';
      if(kind==='CBCT'&&fov&&!/^(?:[1-9]|[12][0-9]|30)(?:\.[0-9])?x(?:[1-9]|[12][0-9]|30)(?:\.[0-9])?$/.test(fov))return 'Campo de visión inválido. Ejemplo: 8x8.';
      if(!dirty&&initial?.contract_version===1)return '';
      return inner?.issue()||(kind==='TMJ'&&!projection?'Seleccione la vista de ATM.':'');
    };
    let fov=initial?.fov_cm||'',projection=initial?.projection||'';
    const emit=value=>{
      dirty=true;
      if(value){
        current={...value,...(kind==='CBCT'?{coverage:coverageFor(value),...(fov?{fov_cm:fov}:{})}:{}),...(kind==='TMJ'?{projection}: {})};
      }else current=null;
      onChange?.(current);
      const warning=host.querySelector('[data-dental-v2-warning]');if(warning)warning.textContent=issue();
    };
    host.textContent='Cargando autoridad dental…';
    Promise.all([selector.authority(),fetch('/assets/data/clinical/dental-study-location-policies-v1.json',{credentials:'same-origin'})
      .then(response=>response.ok?response.json():Promise.reject(new Error('DENTAL_STUDY_POLICY_UNAVAILABLE')))])
      .then(([{config},studyPolicies])=>{
      if(destroyed)return;
      if(studyPolicies.contract_version!==1||studyPolicies.location_authority_version!==2)throw Error('DENTAL_STUDY_POLICY_INVALID');
      const policy=studyPolicies.study_policies[policies[kind]]||config.study_policies[policies[kind]];
      if(!policy)throw Error('DENTAL_STUDY_POLICY_MISSING');
      const initialLocation=initialV2(kind,initial);
      host.innerHTML=`<div class="dental-location-aux"></div><div data-dental-v2-selector></div><p data-dental-v2-warning role="status"></p>`;
      const aux=host.querySelector('.dental-location-aux');
      if(kind==='CBCT'){
        const label=document.createElement('label');label.textContent='Campo de visión opcional (cm)';const input=document.createElement('input');input.placeholder='Ej. 8x8';input.maxLength=9;input.value=fov;
        input.addEventListener('input',()=>{fov=input.value.trim();if(current?.contract_version===2)emit(inner?.value());else if(current?.contract_version===1){current={...current,...(fov?{fov_cm:fov}:{})};if(!fov)delete current.fov_cm;onChange?.(current);}host.querySelector('[data-dental-v2-warning]').textContent=issue();});label.append(input);aux.append(label);
      }
      if(kind==='TMJ'){
        const label=document.createElement('label');label.textContent='Vista de ATM';const select=document.createElement('select');[['','Seleccione vista'],['LATERAL','Lateral'],['PA','Posteroanterior (PA)']].forEach(([value,name])=>select.add(new Option(name,value)));
        select.value=projection;select.addEventListener('change',()=>{projection=select.value;
          if(current?.contract_version===1&&!dirty){current={...current,projection};onChange?.(current);}
          else if(inner?.value())emit(inner.value());
          host.querySelector('[data-dental-v2-warning]').textContent=issue();
        });label.append(select);aux.append(label);
      }
      inner=selector.mount(host.querySelector('[data-dental-v2-selector]'),{
        authorityVersion:2,allowedDentitionModes:policy.allowed_dentition_modes,
        allowedLocationModes:policy.allowed_location_modes,minSelection:policy.min_selection,maxSelection:policy.max_selection,
        allowedRegions:policy.allowed_regions,allowedArches:policy.allowed_arches,allowedSides:policy.allowed_sides,
        requireDentition:!!policy.required_fields?.includes('dentition_mode'),
        required:policy.required,value:initialLocation,onChange:emit,
      });
      inner.ready.then(()=>{if(!destroyed){loaded=true;host.querySelector('[data-dental-v2-warning]').textContent=issue();}});
      if(initial?.contract_version===1&&initial.selected_teeth?.length&&initial.anatomical_region){
        const note=document.createElement('p');note.className='odontogram-notice';note.textContent='La ubicación histórica combina piezas y región. Al editar, elija una ubicación V2; la orden anterior conserva su snapshot V1.';host.prepend(note);
      }
    }).catch(()=>{if(!destroyed)host.textContent='No se pudo cargar la autoridad dental.';});
    return {location:()=>current,valid:()=>loaded&&(!dirty&&initial?.contract_version===1?legacy.isComplete(kind,current):!!inner?.valid())&&issue()==='',issue,kind,
      dentitionMode:()=>inner?.dentitionMode?.()||null,
      destroy:()=>{destroyed=true;inner?.destroy();}};
  }
  window.mxmedDentalLocation=Object.freeze({kindFor:key=>kinds[key]||legacy.kindFor(key),mount,summary,isComplete});
})();
