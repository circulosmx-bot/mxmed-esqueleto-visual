// IMG-CAT02B: contextual physician intent; JSON authority and server validation own the rules.
(function(){
  'use strict';
  const copy=value=>JSON.parse(JSON.stringify(value));
  let pending;
  const authority=()=>pending ||= fetch('/modules/clinical/catalog/imaging_order_parameters_v1.json',{credentials:'same-origin'})
    .then(response=>{if(!response.ok)throw new Error('IMAGING_AUTHORITY_UNAVAILABLE');return response.json();})
    .then(config=>{if(config.version!==1||config.contract!=='imaging_order_parameters')throw new Error('IMAGING_AUTHORITY_INVALID');return config;})
    .catch(error=>{pending=null;throw error;});
  const ruleFor=(key,config)=>config?.rules?.[key]||null;
  const initial=(key,config)=>{
    const rule=ruleFor(key,config);if(!rule||rule.status!=='ACTIVE_REQUIRED')return null;
    const value={version:1,...copy(rule.fixed||{})};
    if(rule.allowed.xray_view_preset?.length)value.xray_view_preset=rule.allowed.xray_view_preset[0];
    return value;
  };
  function isComplete(key,value,config){
    const rule=ruleFor(key,config);if(!rule)return value==null;
    if(value==null)return rule.status!=='ACTIVE_REQUIRED';
    if(value.version!==1)return false;
    const fields=Object.keys(value).filter(field=>field!=='version');if(!fields.length)return false;
    if(fields.some(field=>!rule.allowed[field]))return false;
    if((rule.required||[]).some(field=>!Object.hasOwn(value,field)))return false;
    if(value.xray_views&&value.xray_view_preset)return false;
    for(const field of fields){
      const choices=rule.allowed[field],selected=value[field];
      if(Array.isArray(selected)){if(!selected.length||selected.length!==new Set(selected).size||selected.some(item=>!choices.includes(item)))return false;}
      else if(!choices.includes(selected))return false;
      if(Object.hasOwn(rule.fixed||{},field)&&rule.fixed[field]!==selected)return false;
    }
    if(value.contrast_routes&&!value.contrast_intent)return false;
    const route={WITHOUT_CONTRAST:[],WITH_IV_CONTRAST:['IV'],WITH_AND_WITHOUT_IV_CONTRAST:['IV'],WITH_ORAL_CONTRAST:['ORAL'],WITH_IV_AND_ORAL_CONTRAST:['IV','ORAL']}[value.contrast_intent];
    if(value.contrast_routes&&(!route||value.contrast_routes.some(item=>!route.includes(item))||route.some(item=>!value.contrast_routes.includes(item))))return false;
    return true;
  }
  function summary(key,value,config){
    if(!value)return '';
    return Object.entries(config.field_labels).filter(([field])=>Object.hasOwn(value,field)).map(([field,label])=>{
      const group={contrast_routes:'contrast_route',xray_views:'xray_view',dxa_sites:'dxa_site'}[field]||field;
      const values=Array.isArray(value[field])?value[field]:[value[field]];
      return `${label}: ${values.map(item=>config.value_labels[group]?.[item]||item).join(', ')}`;
    }).join(' · ');
  }
  function mount(host,key,config,current,onChange){
    const rule=ruleFor(key,config);if(!rule)return;
    host.className='imaging-parameter-panel';host.setAttribute('aria-label','Parámetros de imagen');
    const value=copy(current||{version:1,...(rule.fixed||{})});
    if(!current&&rule.allowed.xray_view_preset?.length)value.xray_view_preset=rule.allowed.xray_view_preset[0];
    const primary=new Set(['laterality','contrast_intent','vascular_territory','breast_purpose','breast_tomosynthesis_relation','physician_protocol','xray_view_preset']);
    const extra=document.createElement('details');extra.className='imaging-parameter-extra';
    const extraTitle=document.createElement('summary');extraTitle.textContent='Opciones adicionales';extra.append(extraTitle);
    const makeField=(field,choices)=>{
      const label=document.createElement('label');label.className='imaging-parameter-field';
      const required=(rule.required||[]).includes(field);label.append(document.createTextNode(config.field_labels[field]+(required?' *':'')));
      const group={contrast_routes:'contrast_route',xray_views:'xray_view',dxa_sites:'dxa_site'}[field]||field;
      const labels=config.value_labels[group]||{};
      const list=config.list_fields.includes(field);
      if(Object.hasOwn(rule.fixed||{},field)){
        const fixed=document.createElement('small');fixed.textContent=labels[rule.fixed[field]]||rule.fixed[field];label.append(fixed);
      }else if(list){
        const box=document.createElement('span');box.className='imaging-parameter-options';box.dataset.imagingList=field;
        choices.forEach(choice=>{const option=document.createElement('label'),input=document.createElement('input');input.type='checkbox';input.checked=(value[field]||[]).includes(choice);
          input.addEventListener('change',()=>{const selected=new Set(value[field]||[]);if(input.checked)selected.add(choice);else selected.delete(choice);
            if(selected.size)value[field]=[...selected];else delete value[field];
            if(field==='xray_views'){delete value.xray_view_preset;const preset=host.querySelector('[data-imaging-field="xray_view_preset"]');if(preset)preset.value='';}
            onChange(copy(value));});option.append(input,document.createTextNode(labels[choice]||choice));box.append(option);});label.append(box);
      }else{
        const select=document.createElement('select');select.dataset.imagingField=field;select.required=required;select.add(new Option(required?'Selecciona una opción':'Sin especificar',''));
        choices.forEach(choice=>select.add(new Option(labels[choice]||choice,choice)));select.value=value[field]||'';
        select.addEventListener('change',()=>{if(select.value)value[field]=select.value;else delete value[field];
          if(field==='xray_view_preset'&&select.value){delete value.xray_views;host.querySelectorAll('[data-imaging-list="xray_views"] input:checked').forEach(input=>{input.checked=false;});}
          if(field==='contrast_intent'){
            delete value.contrast_routes;
            host.querySelectorAll('[data-imaging-list="contrast_routes"] input:checked').forEach(input=>{input.checked=false;});
          }
          onChange(copy(value));});label.append(select);
      }
      return label;
    };
    Object.entries(rule.allowed).forEach(([field,choices])=>{
      if(!choices.length)return;
      const element=makeField(field,choices);
      (primary.has(field)||rule.required.includes(field)?host:extra).append(element);
    });
    if(extra.children.length>1)host.append(extra);
    return host;
  }
  window.mxmedImagingParametersV1={authority,ruleFor,initial,isComplete,summary,mount};
})();
