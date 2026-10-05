// FUNC-CAT02B: compact physician-intent controls; the server owns validation.
(function(){
  'use strict';
  const copy=value=>JSON.parse(JSON.stringify(value));
  let pending;
  const authority=()=>pending ||= fetch('/modules/clinical/catalog/functional_order_parameters_v1.json',{credentials:'same-origin'})
    .then(response=>{if(!response.ok)throw Error('FUNCTIONAL_AUTHORITY_UNAVAILABLE');return response.json();})
    .then(config=>{if(config.version!==1||config.contract!=='functional_order_parameters'||!config.rules)throw Error('FUNCTIONAL_AUTHORITY_INVALID');return config;})
    .catch(error=>{pending=null;throw error;});
  const ruleFor=(key,config)=>config?.rules?.[key]||null;
  const initial=(key,config)=>{
    const rule=ruleFor(key,config);
    if(!rule||rule.status!=='ACTIVE_REQUIRED')return null;
    return {version:1,...(key==='esophageal_ph_monitoring'?{duration:'24_HOURS'}:{})};
  };
  function isComplete(key,value,config){
    const rule=ruleFor(key,config);
    if(!rule)return value==null;
    if(value==null)return rule.status!=='ACTIVE_REQUIRED';
    if(value.version!==1)return false;
    const fields=Object.keys(value).filter(field=>field!=='version');
    if(!fields.length||fields.some(field=>!rule.allowed[field]||!rule.allowed[field].includes(value[field])))return false;
    if((rule.required||[]).some(field=>!Object.hasOwn(value,field)))return false;
    if(['emg_ncs','evoked_ssep'].includes(key)&&Boolean(value.body_site)!==Boolean(value.side))return false;
    return true;
  }
  function summary(key,value,config){
    if(!value)return '';
    return Object.entries(config.field_labels).filter(([field])=>Object.hasOwn(value,field))
      .map(([field,label])=>`${label}: ${config.value_labels[field]?.[value[field]]||value[field]}`).join(' · ');
  }
  function mount(host,key,config,current,onChange){
    const rule=ruleFor(key,config);if(!rule||!Object.keys(rule.allowed||{}).length)return;
    host.className='functional-parameter-panel';host.setAttribute('aria-label',`Parámetros funcionales de ${key}`);
    const value=copy(current||initial(key,config)||{version:1});
    Object.entries(rule.allowed).forEach(([field,choices])=>{
      const required=(rule.required||[]).includes(field);
      const label=document.createElement('label');label.className='functional-parameter-field';
      label.append(document.createTextNode(config.field_labels[field]+(required?' *':'')));
      const select=document.createElement('select');select.dataset.functionalField=field;select.required=required;
      select.add(new Option(required?'Selecciona una opción':'Sin especificar',''));
      choices.forEach(choice=>select.add(new Option(config.value_labels[field]?.[choice]||choice,choice)));
      select.value=value[field]||'';
      select.addEventListener('change',()=>{
        if(select.value)value[field]=select.value;else delete value[field];
        onChange(Object.keys(value).length===1?null:copy(value));
      });
      label.append(select);host.append(label);
    });
    return host;
  }
  window.mxmedFunctionalParametersV1={authority,ruleFor,initial,isComplete,summary,mount};
})();
