// PATH-CAT02B: presentation of the server-owned pathology parameter authority.
(function(){
  'use strict';
  const copy=value=>JSON.parse(JSON.stringify(value));
  const ruleFor=(key,authority)=>authority?.version===1?authority.rules?.[key]:null;
  function initial(key,authority){
    const rule=ruleFor(key,authority);if(!rule)return null;
    const specimen={material_key:rule.materials[0]};
    if(rule.fixed_site)specimen.anatomic_site_key=rule.fixed_site;
    const value={version:1,specimens:[specimen]};
    if(rule.parameter==='profile_version')value.profile_version=authority.breast_ihc_profile.version;
    return value;
  }
  function isComplete(key,value,authority){
    const rule=ruleFor(key,authority);
    if(!rule)return value==null;
    if(!value||value.version!==1||!Array.isArray(value.specimens)||!value.specimens.length||value.specimens.length>rule.max_specimens)return false;
    const seen=new Set();
    for(const part of value.specimens){
      if(!part||!rule.materials.includes(part.material_key))return false;
      if(rule.fixed_site&&part.anatomic_site_key!==rule.fixed_site)return false;
      if(part.anatomic_site_key&&!authority.anatomic_sites[part.anatomic_site_key])return false;
      if((rule.site_required||part.anatomic_site_key==='OTHER_ANATOMICAL_SITE')&&!String(part.anatomic_site_text||'').trim())return false;
      if(rule.description_required&&!String(part.description||'').trim())return false;
      if(part.laterality&&!authority.laterality[part.laterality])return false;
      if(authority.lateralized_site_keys.includes(part.anatomic_site_key)&&['MIDLINE','NOT_APPLICABLE'].includes(part.laterality))return false;
      if(part.laterality==='UNSPECIFIED_IF_ALLOWED'&&!String(part.laterality_unspecified_reason||'').trim())return false;
      const signature=JSON.stringify(part);if(seen.has(signature))return false;seen.add(signature);
    }
    if(rule.parameter==='marker_key'&&!authority.ihc_marker_authority.markers[value.marker_key])return false;
    if(rule.parameter==='stain_key'&&!authority.special_stain_authority.stains[value.stain_key])return false;
    if(rule.parameter==='profile_version'&&value.profile_version!==authority.breast_ihc_profile.version)return false;
    if(rule.parameter==='outside_review'&&(!String(value.source_institution_name||'').trim()||!authority.prior_report_statuses[value.prior_report_status]))return false;
    return true;
  }
  function summary(key,value,authority){
    if(!value)return 'Parámetros pendientes';
    const parts=(value.specimens||[]).map((part,index)=>{
      const site=part.anatomic_site_text||authority.anatomic_sites[part.anatomic_site_key]||'Sitio pendiente';
      return `${index+1}. ${authority.material_classes[part.material_key]||'Material pendiente'} · ${site}${part.laterality?' · '+authority.laterality[part.laterality]:''}`;
    });
    if(value.marker_key)parts.push('Marcador: '+authority.ihc_marker_authority.markers[value.marker_key]);
    if(value.stain_key)parts.push('Tinción: '+authority.special_stain_authority.stains[value.stain_key]);
    if(value.profile_version)parts.push('Perfil: '+authority.breast_ihc_profile.components.join(', '));
    if(value.source_institution_name)parts.push('Origen: '+value.source_institution_name);
    if(value.prior_report_status)parts.push('Informe: '+authority.prior_report_statuses[value.prior_report_status]);
    return parts.join(' · ');
  }
  function mount(host,key,authority,current,onChange){
    const rule=ruleFor(key,authority);if(!rule){host.replaceChildren();return {valid:()=>true};}
    let value=current?copy(current):initial(key,authority);
    const emit=()=>onChange(copy(value));
    const node=(tag,className='')=>{const n=document.createElement(tag);n.className=className;return n;};
    function field(parent,title,currentValue,onInput,{options=null,required=false,maxLength=120}={}){
      const label=node('label');label.textContent=title;
      let control;
      if(options){
        control=node('select');control.add(new Option(required?'Selecciona una opción':'Sin especificar',''));
        for(const [k,v] of options)control.add(new Option(v,k));
        control.value=currentValue||'';
        control.addEventListener('change',()=>onInput(control.value||null));
      }else{
        control=node('input');control.type='text';control.maxLength=maxLength;control.value=currentValue||'';
        control.addEventListener('input',()=>onInput(control.value));
      }
      control.required=required;label.append(control);parent.append(label);return control;
    }
    function draw(){
      host.replaceChildren();host.className='pathology-parameter-host';
      const head=node('div','pathology-parameter-heading');head.textContent='Parámetros de patología';host.append(head);
      value.specimens.forEach((part,index)=>{
        const box=node('fieldset','pathology-specimen');
        const legend=node('legend');legend.textContent=`Muestra ${index+1}`;box.append(legend);
        if(rule.materials.length>1){field(box,'Material previsto',part.material_key,chosen=>{part.material_key=chosen||'';emit();},{options:rule.materials.map(k=>[k,authority.material_classes[k]]),required:true});}
        else{const fixed=node('p','pathology-fixed');fixed.textContent='Material previsto: '+authority.material_classes[part.material_key];box.append(fixed);}
        if(rule.fixed_site){const fixed=node('p','pathology-fixed');fixed.textContent='Sitio: '+authority.anatomic_sites[rule.fixed_site];box.append(fixed);}
        else field(box,'Sitio anatómico (categoría)',part.anatomic_site_key,chosen=>{if(chosen)part.anatomic_site_key=chosen;else delete part.anatomic_site_key;emit();},{options:Object.entries(authority.anatomic_sites)});
        if(rule.site_required||!rule.fixed_site){field(box,'Sitio anatómico (descripción)',part.anatomic_site_text,text=>{part.anatomic_site_text=text;emit();},{required:!!rule.site_required});}
        else field(box,'Detalle del sitio (opcional)',part.anatomic_site_text,text=>{if(text)part.anatomic_site_text=text;else delete part.anatomic_site_text;emit();});
        field(box,'Lateralidad (si aplica)',part.laterality,chosen=>{
          if(chosen)part.laterality=chosen;else delete part.laterality;
          if(chosen!=='UNSPECIFIED_IF_ALLOWED')delete part.laterality_unspecified_reason;
          emit();draw();
        },{options:Object.entries(authority.laterality)});
        if(part.laterality==='UNSPECIFIED_IF_ALLOWED')field(box,'Motivo de lateralidad no especificada',part.laterality_unspecified_reason,text=>{part.laterality_unspecified_reason=text;emit();},{required:true});
        field(box,rule.description_required?'Descripción de la pieza':'Descripción del material (opcional)',part.description,text=>{if(text)part.description=text;else delete part.description;emit();},{required:!!rule.description_required,maxLength:190});
        if(value.specimens.length>1){const remove=node('button','btn btn-link btn-sm pathology-remove');remove.type='button';remove.textContent='Retirar muestra';remove.addEventListener('click',()=>{value.specimens.splice(index,1);emit();draw();});box.append(remove);}
        host.append(box);
      });
      if(rule.max_specimens>1&&value.specimens.length<rule.max_specimens){const add=node('button','btn btn-outline-primary btn-sm pathology-add');add.type='button';add.textContent='+ Agregar muestra';add.addEventListener('click',()=>{const specimen={material_key:rule.materials[0]};if(rule.fixed_site)specimen.anatomic_site_key=rule.fixed_site;value.specimens.push(specimen);emit();draw();host.querySelector('.pathology-specimen:last-of-type input')?.focus({preventScroll:true});});host.append(add);}
      if(rule.parameter==='marker_key')field(host,'Marcador IHQ',value.marker_key,chosen=>{if(chosen)value.marker_key=chosen;else delete value.marker_key;emit();},{options:Object.entries(authority.ihc_marker_authority.markers),required:true});
      if(rule.parameter==='stain_key')field(host,'Tinción especial',value.stain_key,chosen=>{if(chosen)value.stain_key=chosen;else delete value.stain_key;emit();},{options:Object.entries(authority.special_stain_authority.stains),required:true});
      if(rule.parameter==='profile_version'){const panel=node('p','pathology-fixed');panel.textContent='Perfil mamario v'+value.profile_version+': '+authority.breast_ihc_profile.components.join(', ');host.append(panel);}
      if(rule.parameter==='outside_review'){
        field(host,'Institución de origen',value.source_institution_name,text=>{value.source_institution_name=text;emit();},{required:true,maxLength:190});
        field(host,'Informe previo',value.prior_report_status,chosen=>{if(chosen)value.prior_report_status=chosen;else delete value.prior_report_status;emit();},{options:Object.entries(authority.prior_report_statuses),required:true});
      }
      if(rule.handling_notice){const notice=node('p','pathology-handling-notice');notice.textContent=rule.handling_notice;host.append(notice);}
    }
    draw();return {valid:()=>isComplete(key,value,authority),value:()=>copy(value)};
  }
  window.mxmedPathologyParametersV1=Object.freeze({ruleFor,initial,isComplete,summary,mount});
})();
