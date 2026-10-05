/* Reusable FDI location selector. Consumers supply policy; this module knows no study keys. */
(function(){
  'use strict';
  const root='/assets/data/clinical/';
  let authorityPromise;
  const authority=()=>authorityPromise ||= Promise.all([
    fetch(root+'dental-location-authority-v2.json',{credentials:'same-origin'}).then(r=>{if(!r.ok)throw Error('DENTAL_V2_UNAVAILABLE');return r.json();}),
    fetch(root+'dental-fdi-iso3950-v1.json',{credentials:'same-origin'}).then(r=>{if(!r.ok)throw Error('FDI_UNAVAILABLE');return r.json();}),
  ]).then(([config,fdi])=>{
    if(config.contract_version!==2||config.numbering_system!=='FDI_ISO_3950'||fdi.teeth?.length!==52)throw Error('DENTAL_V2_INVALID');
    return {config,teeth:fdi.teeth};
  }).catch(error=>{authorityPromise=null;throw error;});
  const modeType={SINGLE_TOOTH:'TOOTH_LOCATION',MULTIPLE_TEETH:'TOOTH_LOCATION',QUADRANT:'QUADRANT_LOCATION',ARCH:'ARCH_LOCATION',REGION:'REGION_LOCATION',BILATERAL_REGION:'REGION_LOCATION',TMJ_REGION:'TMJ_LOCATION'};
  const labels={SINGLE_TOOTH:'Una pieza',MULTIPLE_TEETH:'Varias piezas',QUADRANT:'Cuadrante',ARCH:'Arcada',REGION:'Región',BILATERAL_REGION:'Región bilateral',TMJ_REGION:'ATM',
    UPPER_RIGHT:'Superior derecha',UPPER_LEFT:'Superior izquierda',LOWER_LEFT:'Inferior izquierda',LOWER_RIGHT:'Inferior derecha',
    MAXILLARY:'Maxilar',MANDIBULAR:'Mandíbula',BOTH_ARCHES:'Ambas arcadas',ANTERIOR:'Anterior',POSTERIOR:'Posterior',MAXILLOFACIAL:'Maxilofacial',OTHER_SPECIFIED:'Otra región',
    LEFT:'Izquierda',RIGHT:'Derecha',MIDLINE:'Línea media',BILATERAL:'Bilateral'};
  const toothSvg=position=>position<=2?'<svg viewBox="0 0 34 38" aria-hidden="true"><path d="M11 4 Q17 1 23 4 Q27 7 25 15 L22 30 Q19 37 17 28 Q15 37 12 30 L9 15 Q7 7 11 4Z"/></svg>'
    :position===3?'<svg viewBox="0 0 34 38" aria-hidden="true"><path d="M8 13 L17 2 L26 13 L23 29 Q20 37 17 28 Q14 37 11 29Z"/></svg>'
      :'<svg viewBox="0 0 34 38" aria-hidden="true"><path d="M5 8 Q8 2 12 5 Q17 1 21 5 Q27 2 29 9 L26 23 Q25 33 21 29 L18 23 L15 30 Q10 35 9 25Z"/></svg>';
  function mount(host,options={}){
    if(options.authorityVersion!==undefined&&options.authorityVersion!==2)throw Error('DENTAL_V2_VERSION_INVALID');
    const allowed=options.allowedLocationModes||[],dentitions=options.allowedDentitionModes||['PERMANENT','PRIMARY','MIXED'];
    const min=Number(options.minSelection??1),max=Number(options.maxSelection??52),required=options.required!==false;
    let config=null,teeth=[],destroyed=false;
    let dentition=options.value?.dentition_mode||options.dentitionMode||dentitions[0]||'PERMANENT';
    if(dentition==='DECIDUOUS')dentition='PRIMARY';
    let hasDentitionSelection=!!(options.value?.dentition_mode||options.dentitionMode);
    let mode=options.value?.selection_mode||options.initialSelectionMode||allowed[0]||null;
    if(!allowed.includes(mode))mode=allowed[0]||null;
    const selected=new Set(options.value?.tooth_fdi_codes||[]);
    let key=options.value?.quadrant_key||options.value?.arch_key||options.value?.tmj_side||'';
    let region=options.value?.region_key||'',arch=options.value?.arch_key||'',side=options.value?.side_key||'',detail=options.value?.region_detail||'';
    let hasRegionSelection=!!options.value?.region_key;
    let notice='',announcement='';
    const live=document.createElement('p');live.className='odontogram-live';live.setAttribute('aria-live','polite');live.setAttribute('aria-atomic','true');
    const toothByCode=()=>new Map(teeth.map(t=>[t.code,t]));
    const codes=()=>[...selected].sort((a,b)=>Number(a)-Number(b));
    const isTooth=()=>mode==='SINGLE_TOOTH'||mode==='MULTIPLE_TEETH';
    function value(){
      if(!mode)return null;
      const base={contract_version:2,location_type:modeType[mode],selection_mode:mode};
      if(isTooth())return selected.size?{...base,numbering_system:'FDI_ISO_3950',dentition_mode:dentition,tooth_fdi_codes:codes()}:null;
      if(mode==='QUADRANT')return key?{...base,dentition_mode:dentition,quadrant_key:key}:null;
      if(mode==='ARCH')return key?{...base,...(options.requireDentition&&hasDentitionSelection?{dentition_mode:dentition}:{}),arch_key:key}:null;
      if(mode==='TMJ_REGION')return key?{...base,tmj_side:key}:null;
      if(mode==='REGION'||mode==='BILATERAL_REGION'){
        if(!hasRegionSelection||!region||region==='OTHER_SPECIFIED'&&!detail.trim()||
          (region==='ANTERIOR'||region==='POSTERIOR')&&(!arch||mode==='REGION'&&!side||mode==='BILATERAL_REGION'&&!arch))return null;
        const result={...base,region_key:region};
        if(hasDentitionSelection&&dentitions.length&&dentition)result.dentition_mode=dentition;
        if(region!=='MAXILLOFACIAL'){
          if(arch)result.arch_key=arch;
          if(mode==='BILATERAL_REGION')result.side_key='BILATERAL';
          else if(side)result.side_key=side;
        }
        if(region==='OTHER_SPECIFIED')result.region_detail=detail.trim();
        return result;
      }
      return null;
    }
    function issue(){
      const current=value();
      if(!current)return required?'Seleccione la ubicación antes de continuar.':'';
      if(!allowed.includes(mode)||dentitions.length>0&&current.dentition_mode&&!dentitions.includes(current.dentition_mode))return 'Ubicación no permitida para este uso.';
      if(options.requireDentition&&!current.dentition_mode)return 'Seleccione la dentición antes de continuar.';
      if(isTooth()){
        if(selected.size<min||selected.size>max||mode==='SINGLE_TOOTH'&&selected.size!==1)return `Seleccione entre ${min} y ${max} piezas.`;
        const map=toothByCode();
        if(codes().some(code=>!map.has(code)||dentition!=='MIXED'&&(map.get(code).dentition==='DECIDUOUS'?'PRIMARY':'PERMANENT')!==dentition))return 'La pieza no corresponde a la dentición seleccionada.';
      }
      if(mode==='QUADRANT'&&!config.quadrants.includes(key)||mode==='ARCH'&&!(options.allowedArches||config.arches).includes(key)||mode==='TMJ_REGION'&&!config.tmj_sides.includes(key))return 'Ubicación no válida.';
      if((mode==='REGION'||mode==='BILATERAL_REGION')&&(!(options.allowedRegions||config.regions).includes(region)||mode==='BILATERAL_REGION'&&region==='MAXILLOFACIAL'))return 'Región no permitida.';
      if((mode==='REGION'||mode==='BILATERAL_REGION')&&region!=='MAXILLOFACIAL'&&(!(options.allowedArches||config.arches).includes(arch)||!(options.allowedSides||config.sides).includes(current.side_key)))return 'Lado o arcada no permitidos.';
      return '';
    }
    const valid=()=>!!config&&issue()==='';
    const visualOrder=(q1,q2)=>[...q1,...q2];
    function row(order,kind,upper,mixed){
      const lane=document.createElement('div');lane.className='odontogram-lane '+(upper?'odontogram-upper ':'odontogram-lower ')+(kind==='PRIMARY'?'odontogram-primary':'odontogram-permanent');
      lane.setAttribute('aria-label',`${upper?'Maxilar':'Mandíbula'} ${kind==='PRIMARY'?'temporal':'permanente'}`);
      const name=document.createElement('span');name.className='odontogram-lane-name';name.textContent=mixed?(kind==='PRIMARY'?'Temporal':'Permanente'):'';lane.append(name);
      const count=order.length;
      order.forEach((tooth,index)=>{
        const button=document.createElement('button');button.type='button';button.className='odontogram-tooth';button.dataset.fdi=tooth.code;
        const fraction=(index-(count-1)/2)/((count-1)/2),wide=kind==='PRIMARY'?460:720;
        const x=400+fraction*wide/2,arc=Math.pow(Math.abs(fraction),1.8);
        const y=mixed?(upper?(kind==='PRIMARY'?185+20*arc:47+75*arc):(kind==='PRIMARY'?350-35*arc:510-75*arc))
          :(upper?56+100*arc:370-100*arc);
        button.style.left=`${x}px`;button.style.top=`${y}px`;
        button.setAttribute('aria-pressed',String(selected.has(tooth.code)));
        const spokenName=kind==='PRIMARY'?tooth.name_es.replace(/^(incisivo central|incisivo lateral|canino|primer molar|segundo molar)/,'$1 temporal'):tooth.name_es;
        const unavailable=(options.disabledFdiCodes||[]).includes(tooth.code);
        button.setAttribute('aria-label',`Pieza ${tooth.code}, ${spokenName}, ${unavailable?'no aplicable':selected.has(tooth.code)?'seleccionada':'no seleccionada'}`);
        button.title=`${tooth.code} · ${tooth.name_es}`;
        button.disabled=unavailable;
        button.innerHTML=toothSvg(tooth.position)+`<span>${tooth.code}</span>`;
        lane.append(button);
      });
      return lane;
    }
    function optionButtons(items,field){return items.map(item=>`<button type="button" data-odontogram-choice="${item}" data-odontogram-field="${field}" aria-pressed="${key===item}">${labels[item]||item}</button>`).join('');}
    function render(){
      if(!config)return;
      const mixed=dentition==='MIXED';
      if(!live.isConnected)host.replaceChildren(live);
      else host.querySelector('.odontogram-v2')?.remove();
      host.insertAdjacentHTML('afterbegin',`<section class="odontogram-v2" aria-label="Selector dental FDI">
        ${dentitions.length>1&&mode!=='TMJ_REGION'?`<div class="odontogram-segment" role="group" aria-label="Dentición">${dentitions.map(item=>`<button type="button" data-odontogram-dentition="${item}" aria-pressed="${dentition===item&&(!options.requireDentition||hasDentitionSelection)}">${{PERMANENT:'Permanente',PRIMARY:'Temporal',MIXED:'Mixta'}[item]}</button>`).join('')}</div>`:''}
        ${allowed.length>1?`<div class="odontogram-mode-list" role="group" aria-label="Tipo de ubicación">${allowed.map(item=>`<button type="button" data-odontogram-mode="${item}" aria-pressed="${mode===item}">${labels[item]}</button>`).join('')}</div>`:''}
        <p class="odontogram-prompt">${isTooth()?mode==='SINGLE_TOOTH'?'Seleccione la pieza':'Seleccione la pieza o piezas':mode==='TMJ_REGION'?'Seleccione la ATM':mode==='REGION'||mode==='BILATERAL_REGION'?'Seleccione la región':'Seleccione la ubicación'}</p>
        <div class="odontogram-location"></div>
        <div class="odontogram-summary"></div>
        <p class="odontogram-notice" role="status">${notice}</p>
      </section>`);
      const target=host.querySelector('.odontogram-location');
      if(isTooth()){
        const hint=document.createElement('p');hint.className='odontogram-scroll-hint';hint.textContent='Deslice horizontalmente para ver ambas arcadas y sus piezas.';target.append(hint);
        const viewport=document.createElement('div');viewport.className='odontogram-viewport';viewport.setAttribute('aria-label','Arcadas dentales; desplazar horizontalmente si es necesario');
        const stage=document.createElement('div');stage.className='odontogram-stage '+(mixed?'is-mixed':'');
        const q=n=>teeth.filter(t=>t.quadrant===n).sort((a,b)=>[1,4,5,8].includes(n)?b.position-a.position:a.position-b.position);
        const add=(dent,upper,left,right)=>stage.append(row(visualOrder(q(left),q(right)),dent,upper,mixed));
        const upper=document.createElement('strong');upper.className='odontogram-arch-title upper';upper.textContent='Maxilar (superior)';stage.append(upper);
        if(dentition!=='PRIMARY')add('PERMANENT',true,1,2);
        if(dentition!=='PERMANENT')add('PRIMARY',true,5,6);
        const axis=document.createElement('span');axis.className='odontogram-midline';axis.setAttribute('aria-hidden','true');stage.append(axis);
        const orientation=document.createElement('div');orientation.className='odontogram-orientation';orientation.innerHTML='<span>Derecha del paciente</span><span>Izquierda del paciente</span>';stage.append(orientation);
        if(dentition!=='PERMANENT')add('PRIMARY',false,8,7);
        if(dentition!=='PRIMARY')add('PERMANENT',false,4,3);
        const lower=document.createElement('strong');lower.className='odontogram-arch-title lower';lower.textContent='Mandíbula (inferior)';stage.append(lower);
        viewport.append(stage);target.append(viewport);
      }else if(mode==='QUADRANT'||mode==='ARCH'||mode==='TMJ_REGION'){
        const group=document.createElement('div');group.className='odontogram-choices';group.innerHTML=optionButtons(mode==='QUADRANT'?config.quadrants:mode==='ARCH'?(options.allowedArches||config.arches):config.tmj_sides,mode);
        target.append(group);
      }else if(mode==='REGION'||mode==='BILATERAL_REGION'){
        const form=document.createElement('div');form.className='odontogram-region';
        const select=(field,list,chosen)=>`<label>${{region:'Región',arch:'Arcada',side:'Lado'}[field]}<select data-odontogram-select="${field}"><option value="">${{region:'Seleccione la región',arch:'Seleccione la arcada',side:'Seleccione el lado'}[field]}</option>${list.map(item=>`<option value="${item}" ${item===chosen?'selected':''}>${labels[item]}</option>`).join('')}</select></label>`;
        const allowedRegions=options.allowedRegions||config.regions;
        form.innerHTML=select('region',mode==='BILATERAL_REGION'?allowedRegions.filter(item=>item!=='MAXILLOFACIAL'):allowedRegions,region)+(region==='MAXILLOFACIAL'?'':select('arch',options.allowedArches||config.arches,arch)+(mode==='BILATERAL_REGION'?'':select('side',(options.allowedSides||config.sides).filter(item=>item!=='BILATERAL'),side)))+
          (region==='OTHER_SPECIFIED'?`<label>Especifique la región<input data-odontogram-detail maxlength="120" value="${String(detail).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]))}"></label>`:'');
        target.append(form);
      }
      const summary=host.querySelector('.odontogram-summary');const current=value();
      if(isTooth()&&selected.size){
        const title=document.createElement('strong');title.textContent=`Piezas seleccionadas (${selected.size})`;summary.append(title);
        const chips=document.createElement('div');chips.className='odontogram-chips';codes().forEach(code=>{
          const button=document.createElement('button');button.type='button';button.dataset.odontogramRemove=code;button.setAttribute('aria-label',`Retirar pieza ${code}`);button.textContent=`${code} ×`;chips.append(button);
        });summary.append(chips);
      }else if(current){const title=document.createElement('strong');title.textContent='Ubicación seleccionada';const copy=document.createElement('span');copy.textContent=labels[key]||labels[region]||key||region;summary.append(title,copy);}
      if(current){const clear=document.createElement('button');clear.type='button';clear.className='odontogram-clear';clear.dataset.odontogramClear='';clear.textContent='Limpiar selección';summary.append(clear);}
      const buttons=[...host.querySelectorAll('.odontogram-tooth:not(:disabled)')];if(buttons.length)buttons.forEach((button,index)=>button.tabIndex=index===0?0:-1);
    }
    function changed(focusCode){render();live.textContent=announcement;options.onChange?.(value());if(focusCode)host.querySelector(`[data-fdi="${focusCode}"]`)?.focus({preventScroll:true});}
    const click=event=>{
      const target=event.target.closest('button');if(!target||!host.contains(target))return;
      if(target.dataset.odontogramDentition){
        const next=target.dataset.odontogramDentition;
        const incompatible=codes().filter(code=>{
          const tooth=toothByCode().get(code);return next!=='MIXED'&&(tooth?.dentition==='DECIDUOUS'?'PRIMARY':'PERMANENT')!==next;
        });
        if(incompatible.length){notice=`Piezas ${incompatible.join(', ')} fuera de esta dentición. Limpie la selección o permanezca en Mixta.`;render();return;}
        dentition=next;hasDentitionSelection=true;notice='';announcement=`Dentición ${next==='PRIMARY'?'temporal':next==='MIXED'?'mixta':'permanente'}.`;changed();return;
      }
      if(target.dataset.odontogramMode){if(value()){notice='Limpie la selección antes de cambiar el tipo de ubicación.';render();return;}
        mode=target.dataset.odontogramMode;key='';hasRegionSelection=false;notice='';announcement=`Modo ${labels[mode]}.`;changed();return;}
      if(target.dataset.fdi){const code=target.dataset.fdi,was=selected.has(code);if(mode==='SINGLE_TOOTH'){selected.clear();selected.add(code);}else was?selected.delete(code):selected.add(code);
        notice=selected.size>max?`Máximo ${max} piezas.`:'';if(selected.size>max)selected.delete(code);announcement=`Pieza ${code} ${was&&mode!=='SINGLE_TOOTH'?'retirada':'seleccionada'}. ${selected.size} piezas seleccionadas.`;changed(code);return;}
      if(target.dataset.odontogramChoice){key=target.dataset.odontogramChoice;notice='';announcement=`${labels[key]} seleccionado.`;changed();return;}
      if(target.dataset.odontogramRemove){selected.delete(target.dataset.odontogramRemove);notice='';announcement=`Pieza ${target.dataset.odontogramRemove} retirada. ${selected.size} piezas seleccionadas.`;changed();return;}
      if(target.hasAttribute('data-odontogram-clear')){selected.clear();key='';hasRegionSelection=false;notice='';announcement='Selección limpiada.';changed();}
    };
    const change=event=>{const field=event.target.dataset.odontogramSelect;if(!field)return;
      if(field==='region')region=event.target.value;if(field==='arch')arch=event.target.value;if(field==='side')side=event.target.value;
      hasRegionSelection=true;
      notice='';announcement='Ubicación actualizada.';changed();};
    const input=event=>{if(event.target.dataset.odontogramDetail===undefined)return;detail=event.target.value;hasRegionSelection=true;options.onChange?.(value());};
    const keydown=event=>{const current=event.target.closest('.odontogram-tooth');if(!current||!['ArrowLeft','ArrowRight','ArrowUp','ArrowDown'].includes(event.key))return;
      const buttons=[...host.querySelectorAll('.odontogram-tooth:not(:disabled)')];const index=buttons.indexOf(current);if(index<0)return;
      event.preventDefault();const delta=event.key==='ArrowLeft'||event.key==='ArrowUp'?-1:1;const next=buttons[(index+delta+buttons.length)%buttons.length];
      current.tabIndex=-1;next.tabIndex=0;next.focus();};
    host.addEventListener('click',click);host.addEventListener('change',change);host.addEventListener('input',input);host.addEventListener('keydown',keydown);
    host.textContent='Cargando odontograma FDI…';
    const ready=authority().then(data=>{if(destroyed)return;config=data.config;teeth=data.teeth;render();}).catch(()=>{if(!destroyed)host.textContent='No se pudo cargar la autoridad dental.';});
    return {ready,value,valid,issue,dentitionMode:()=>hasDentitionSelection?dentition:null,destroy:()=>{destroyed=true;host.removeEventListener('click',click);host.removeEventListener('change',change);host.removeEventListener('input',input);host.removeEventListener('keydown',keydown);}};
  }
  window.mxmedDentalOdontogramV2=Object.freeze({mount,authority});
})();
