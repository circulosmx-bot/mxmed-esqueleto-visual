// ORD-COMP01: presentation and batch orchestration. TAX03C owns selection/parameters.
(function(){
  'use strict';
  const el=(tag,text='',className='')=>{const n=document.createElement(tag);n.textContent=text;n.className=className;return n;};
  const button=(text,action)=>{const n=el('button',text,'btn btn-outline-primary btn-sm');n.type='button';n.addEventListener('click',action);return n;};
  let routingPromise;
  const routing=()=>routingPromise ||= fetch('/modules/clinical/catalog/study_order_routing_v1.json',{credentials:'same-origin'})
    .then(r=>{if(!r.ok)throw new Error('ROUTING_UNAVAILABLE');return r.json();}).catch(e=>{routingPromise=null;throw e;});
  let featuredPromise;
  const featuredNavigation=()=>featuredPromise ||= fetch('/modules/clinical/catalog/study_featured_navigation_v1.json',{credentials:'same-origin'})
    .then(r=>{if(!r.ok)throw new Error('FEATURED_NAVIGATION_UNAVAILABLE');return r.json();}).catch(e=>{featuredPromise=null;throw e;});
  function mount(host,options){
    let config=null,specimenConfig=null,pathologyConfig=null,featuredConfig=null,selected=[],customDraft={},metadata={},composer=null,context=null,lastScope=null,epoch=0;
    let busy=false,attempt=null,uncertain=false,issued=false,reviewReturnFocus=null,reviewScroll=null;const waiters=[];
    const workspace=el('div','','ordcomp-workspace'),catalog=el('section','','ordcomp-catalog'),aside=el('aside','','ordcomp-summary');
    const selector=el('div');catalog.setAttribute('aria-label','Selección de estudios');catalog.append(selector);
    const count=el('p','','ordcomp-count'),selection=el('div','','ordcomp-selection'),error=el('p','','ordcomp-error');error.setAttribute('role','alert');
    const addOther=button('+ Agregar estudios',()=>chooser.hidden?openChooser():closeChooser(true));addOther.classList.add('ordcomp-add-other');
    const chooser=el('section','','ordcomp-add-chooser');chooser.id='ordcomp-add-chooser';chooser.hidden=true;chooser.setAttribute('role','region');chooser.setAttribute('aria-label','Elegir dónde agregar estudios');
    addOther.setAttribute('aria-controls',chooser.id);addOther.setAttribute('aria-expanded','false');
    const reviewAll=button('Revisar todas las órdenes',()=>review('',reviewAll));reviewAll.classList.add('ordcomp-review-all');reviewAll.hidden=true;
    aside.setAttribute('aria-label','Órdenes en preparación');count.setAttribute('role','status');
    aside.append(el('h4','ÓRDENES EN PREPARACIÓN'),count,selection,addOther,chooser,error,reviewAll);workspace.append(catalog,aside);
    const mobile=el('div','','ordcomp-mobile-bar'),mobileCount=el('span'),toggle=button('Ver órdenes',()=>{
      host.classList.toggle('ordcomp-show-summary');const show=host.classList.contains('ordcomp-show-summary');
      toggle.textContent=show?'Volver al catálogo':'Ver órdenes';toggle.setAttribute('aria-expanded',String(show));
      (show?aside:catalog).scrollIntoView({block:'start',behavior:'smooth'});
    });toggle.setAttribute('aria-expanded','false');mobile.append(mobileCount,toggle);
    const reviewBox=el('dialog','','ordcomp-review'),reviewContent=el('div','','ordcomp-review-content');
    reviewBox.hidden=true;reviewBox.setAttribute('aria-modal','true');reviewBox.append(reviewContent);
    host.classList.add('ordcomp');host.hidden=true;host.append(workspace,reviewBox,mobile);
    function closeReview(restoreFocus=true){
      if(busy||uncertain||issued)return false;
      if(reviewBox.open)reviewBox.close();reviewBox.hidden=true;
      if(restoreFocus&&reviewReturnFocus?.isConnected&&!reviewReturnFocus.hidden)reviewReturnFocus.focus({preventScroll:true});
      if(reviewScroll)window.scrollTo({left:reviewScroll.x,top:reviewScroll.y,behavior:'instant'});
      reviewReturnFocus=null;reviewScroll=null;return true;
    }
    reviewBox.addEventListener('cancel',event=>{event.preventDefault();closeReview();});
    reviewBox.addEventListener('click',event=>{if(event.target===reviewBox)closeReview();});
    reviewBox.addEventListener('keydown',event=>{
      if(event.key!=='Tab')return;
      const controls=[...reviewBox.querySelectorAll('button,a[href],input,select,textarea,[tabindex]:not([tabindex="-1"])')]
        .filter(control=>!control.disabled&&control.getClientRects().length);
      if(!controls.length){event.preventDefault();return;}
      const index=controls.indexOf(document.activeElement),last=controls.length-1;
      if(event.shiftKey&&index<=0){event.preventDefault();controls[last].focus();}
      else if(!event.shiftKey&&(index<0||index===last)){event.preventDefault();controls[0].focus();}
    });
    const groupKey=item=>item.type==='canonical'?config?.studies[item.key]:item.routingGroup;
    const groups=()=>{
      const grouped=new Map();selected.forEach((item,index)=>{const key=groupKey(item);if(!grouped.has(key))grouped.set(key,[]);grouped.get(key).push({item,index});});return grouped;
    };
    const sync=()=>{if(composer){selected=composer.selected();customDraft=composer.customDraft();}};
    function closeChooser(restoreFocus=false){chooser.hidden=true;chooser.replaceChildren();addOther.hidden=false;addOther.setAttribute('aria-expanded','false');if(restoreFocus)addOther.focus({preventScroll:true});}
    function openChooser(){
      const nav=options.addStudiesContext?.()||{},familyId=nav.currentFamilyId||'';
      const sameLabel={laboratory:'Más estudios de Laboratorio',imaging:'Más estudios de Imagenología',pathology:'Más estudios de Patología y biopsias',functional:'Más estudios funcionales',procedures:'Más procedimientos diagnósticos',dental:'Más estudios dentales'}[familyId];
      chooser.replaceChildren(el('h5','¿Qué desea agregar?'));
      const choose=id=>{closeChooser();options.onAddStudiesDestination?.(id);};
      if(sameLabel){const current=button('+ '+sameLabel,()=>choose(familyId));current.classList.add('ordcomp-add-current');chooser.append(current);}
      const others=(nav.families||[]).filter(family=>family.id!==familyId);
      if(others.length){chooser.append(el('p',familyId==='dental'?'Otra opción':'Otro tipo de estudio','ordcomp-add-caption'));others.forEach(family=>chooser.append(button(family.label,()=>choose(family.id))));}
      chooser.append(button('Cerrar',()=>closeChooser(true)));
      addOther.hidden=true;chooser.hidden=false;addOther.setAttribute('aria-expanded','true');chooser.querySelector('button')?.focus({preventScroll:true});
    }
    function counts(){
      const n=groups().size,m=selected.length;count.textContent=`${n} ${n===1?'orden':'órdenes'} · ${m} ${m===1?'estudio seleccionado':'estudios seleccionados'}`;
      mobileCount.textContent=`${m} ${m===1?'estudio':'estudios'} · ${n} ${n===1?'orden':'órdenes'}`;
      reviewAll.hidden=n<=1;
      options.onChange?.({selected:structuredClone(selected),orders:n,studies:m});
    }
    function reset(){
      epoch++;composer?.destroy();composer=null;selected=[];customDraft={};metadata={};context=null;attempt=null;uncertain=false;issued=false;lastScope=null;
      if(reviewBox.open)reviewBox.close();reviewBox.hidden=true;reviewReturnFocus=null;reviewScroll=null;
      closeChooser();host.hidden=true;selector.replaceChildren();selection.replaceChildren();reviewContent.replaceChildren();workspace.hidden=false;mobile.hidden=false;error.textContent='';counts();
    }
    const dirty=()=>{
      sync();return !issued&&(!!selected.length||!!customDraft.name?.trim()||!!customDraft.note?.trim()||!!customDraft.category||!!customDraft.route);
    };
    window.mxmedPatientWorkspaceNavigationGuard?.register({id:'ordcomp01-composition',copy:'order',isInProgress:dirty,
      isSaving:()=>busy,whenSettled:()=>new Promise(resolve=>waiters.push(resolve)),canDiscard:()=>!uncertain,
      onBlocked:()=>{error.textContent='Confirma el resultado de la emisión con Reintentar antes de salir.';},discard:reset});
    async function open(scope={}){
      if(busy||uncertain)return false;
      const current=options.context();if(!current.doctor||!current.patient)return false;
      if(context&&(context.patient!==current.patient||context.doctor!==current.doctor))reset();
      context=current;const seen=++epoch;sync();composer?.destroy();composer=null;
      closeChooser();
      if(reviewBox.open)reviewBox.close();reviewBox.hidden=true;reviewReturnFocus=null;reviewScroll=null;
      host.hidden=false;workspace.hidden=false;mobile.hidden=false;issued=false;lastScope=scope;
      host.classList.remove('ordcomp-show-summary');toggle.textContent='Ver órdenes';toggle.setAttribute('aria-expanded','false');
      selector.textContent='Cargando estudios…';
      try{
        [config,specimenConfig,pathologyConfig,featuredConfig]=await Promise.all([routing(),fetch('/modules/clinical/catalog/study_specimen_requirements_v1.json',{credentials:'same-origin'}).then(r=>{if(!r.ok)throw new Error('SPECIMEN_CONFIG_UNAVAILABLE');return r.json();}),fetch('/modules/clinical/catalog/pathology_order_parameters_v1.json',{credentials:'same-origin'}).then(r=>{if(!r.ok)throw new Error('PATHOLOGY_CONFIG_UNAVAILABLE');return r.json();}),featuredNavigation()]);if(epoch!==seen)return false;
        options.onHeading?.(featuredConfig.leaves?.[scope.id]?.heading||scope.label||'Catálogo general');
        composer=window.mxmedStudyComposer.mount(selector,{doctorId:current.doctor,presentation:'embedded',routing:config,
          navigationGroup:{label:scope.label||'Catálogo general',parts:scope.parts||[]},leafId:scope.id||'',featuredNavigation:featuredConfig,selectionHost:selection,specimenConfig,pathologyConfig,
          initialQuery:scope.searchQuery||'',focusStudyKey:scope.focusStudyKey||'',onNavigateToStudy:destination=>options.onNavigateToStudy?.(destination),
          initialCategory:scope.parts?.length===1?scope.parts[0].category:'',selected,customDraft,
          onChange:items=>{selected=items;attempt=null;error.textContent='';counts();},onDraftChange:()=>options.onDirty?.(),onReviewOrder:key=>review(key)});
        counts();selector.querySelector('input')?.focus({preventScroll:true});return true;
      }catch(_){selector.textContent='No se pudo preparar la selección. Vuelve a esta familia para reintentar.';return true;}
    }
    function hide(){if(busy||uncertain)return false;sync();closeReview(false);closeChooser();host.hidden=true;return true;}
    function review(focusKey='',origin=null){
      sync();error.textContent='';
      const grouped=groups(),single=grouped.size===1,focused=focusKey&&grouped.has(focusKey);
      if(!grouped.size)return;
      const reviewValidation=(!focused||single)&&!composer?.valid()?composer?.validationMessage()||'Completa los datos de los estudios antes de generar la orden.':'';
      const routingValidation=(focused?[focusKey]:[...grouped.keys()]).some(key=>!config.groups[key])?'Falta confirmar el servicio de un estudio.':'';
      if(!reviewBox.open){reviewReturnFocus=origin||document.activeElement;reviewScroll={x:window.scrollX,y:window.scrollY};}
      reviewContent.replaceChildren();reviewBox.hidden=false;
      const n=grouped.size,reviewed=focused?[[focusKey,grouped.get(focusKey)]]:[...grouped],heading=el('h4',focused?`Revisar orden de ${config.groups[focusKey]||'servicio pendiente'}`:`Revisar ${n} órdenes`);heading.id='ordcomp-review-title';heading.tabIndex=-1;
      reviewBox.removeAttribute('aria-label');
      reviewBox.setAttribute('aria-labelledby',heading.id);
      const close=button('×',()=>closeReview());close.classList.add('ordcomp-review-close');close.setAttribute('aria-label','Cerrar revisión');
      const edit=button('Volver a seleccionar estudios',()=>closeReview());
      const header=el('div','','ordcomp-review-header');header.append(heading,close);
      reviewContent.append(header,el('p',focused&&n>1?'Las demás órdenes permanecen en preparación.':'Cada servicio generará una orden independiente.'),edit);
      if(reviewValidation||routingValidation){const warning=el('p',reviewValidation||routingValidation,'ordcomp-error');warning.setAttribute('role','alert');reviewContent.append(warning);}
      const cards=new Map();
      for(const [key,items] of reviewed){
        const service=config.groups[key]||'Servicio pendiente',card=el('section','','ordcomp-review-order');card.dataset.reviewGroup=key;card.append(el('h5',service));
        const list=el('ul');items.forEach(({item})=>{const li=el('li',item.name+(item.type==='custom'?' · Estudio personalizado':''));
          if(item.dentalLocation)li.append(el('small',window.mxmedDentalLocationV1.summary(item.dentalLocation)));
          if(item.specimenRequirements){const request=item.specimenRequirements,detail=[(request.specimen_type_key&&specimenConfig.specimen_types[request.specimen_type_key]),(request.collection_mode==='TIMED'&&`${request.requested_duration_minutes/60} horas`),(request.source_site_text&&`Sitio: ${request.source_site_text}`)].filter(Boolean).join(' · ');if(detail)li.append(el('small',detail));}
          if(item.pathologyParameters)li.append(el('small',window.mxmedPathologyParametersV1.summary(item.key,item.pathologyParameters,pathologyConfig)));
          list.append(li);});card.append(list);
        const meta=metadata[key] ||= {priority:'Rutinaria',indication:''};
        const pl=el('fieldset','','ordcomp-priority');pl.append(el('legend','Prioridad'));
        ['Rutinaria','Urgente'].forEach(value=>{
          const label=el('label'),radio=el('input');radio.type='radio';radio.name=`ordcomp-priority-${key}`;radio.value=value;radio.checked=meta.priority===value;
          radio.addEventListener('change',()=>{if(radio.checked){meta.priority=value;attempt=null;}});
          label.append(radio,document.createTextNode(value));pl.append(label);
        });
        if(!['Rutinaria','Urgente'].includes(meta.priority)){const unsupported=el('p',`Prioridad previa no compatible con esta composición: ${meta.priority}. Selecciona Rutinaria o Urgente para continuar.`,'ordcomp-error');unsupported.setAttribute('role','alert');pl.append(unsupported);}
        const il=el('label','Indicación clínica'),indication=el('textarea');indication.rows=2;indication.maxLength=2000;indication.value=meta.indication;
        indication.setAttribute('aria-label',`Indicación · ${service}`);indication.addEventListener('input',()=>{meta.indication=indication.value;attempt=null;});il.append(indication);
        const issue=el('p','','ordcomp-error');issue.setAttribute('role','alert');card.append(pl,il,issue);reviewContent.append(card);cards.set(key,{card,issue});
      }
      if(!reviewBox.open)reviewBox.showModal();
      if(focused&&n>1){const all=button('Revisar todas las órdenes',()=>review());all.classList.add('ordcomp-review-all');reviewContent.append(all);heading.focus({preventScroll:true});return;}
      const failure=el('p','','ordcomp-error');failure.setAttribute('role','alert');
      const submitLabel=n===1?'Generar orden':'Generar todas las órdenes';
      const submit=button(submitLabel,async()=>{
        if(busy)return;
        if(!composer?.valid()){failure.textContent=composer?.validationMessage()||'Completa los datos de los estudios antes de generar la orden.';return;}
        if([...groups().keys()].some(key=>!config.groups[key])){failure.textContent='Falta confirmar el servicio de un estudio.';return;}
        if([...groups().keys()].some(key=>!['Rutinaria','Urgente'].includes(metadata[key]?.priority))){failure.textContent='Selecciona Rutinaria o Urgente para cada orden.';return;}
        if(options.context().patient!==context.patient||options.context().doctor!==context.doctor){failure.textContent='Cambió el paciente. Abre de nuevo la composición.';return;}
        if(!attempt){
          const inputs=composer.orderItems();attempt={order_composition_batch_uuid:crypto.randomUUID(),order_routing_version:config.version,
            orders:[...groups()].map(([key,items])=>({order_routing_group_key:key,...metadata[key],order_items:items.map(({index})=>inputs[index])}))};
        }
        busy=true;failure.textContent='Emitiendo órdenes…';cards.forEach(({issue})=>issue.textContent='');
        reviewBox.querySelectorAll('button,input,textarea,select').forEach(c=>c.disabled=true);
        try{
          const response=await fetch(`/api/clinical/index.php/doctors/${encodeURIComponent(context.doctor)}/patients/${encodeURIComponent(context.patient)}/orders/batch`,{
            method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json',Accept:'application/json','Idempotency-Key':attempt.order_composition_batch_uuid},body:JSON.stringify(attempt)});
          const result=await response.json();
          if(!response.ok||!result.ok){
            // A definitive validation/auth rejection permits editing. Ambiguous outcomes require exact retry.
            uncertain=!(response.status>=400&&response.status<500&&response.status!==409);
            const key=result.order_routing_group_key;
            if(cards.has(key))cards.get(key).issue.textContent='Revisa esta orden: '+errorMessage(result.message);
            throw new Error(errorMessage(result.message));
          }
          uncertain=false;issued=true;showSuccess(result.data);
          options.onIssued?.(context.patient);
        }catch(e){
          if(e instanceof TypeError||e instanceof SyntaxError)uncertain=true;
          failure.textContent=uncertain?'No se pudo confirmar la emisión. Reintentar recuperará las mismas órdenes sin duplicarlas.':e.message;
          submit.textContent=uncertain?'Reintentar emisión':submitLabel;
          reviewBox.querySelectorAll('button,input,textarea,select').forEach(c=>c.disabled=uncertain);submit.disabled=false;
          if(!uncertain)attempt=null;
        }finally{busy=false;waiters.splice(0).forEach(resolve=>resolve());}
      });submit.classList.add('btn-primary');reviewContent.append(failure,submit);heading.focus({preventScroll:true});
    }
    function errorMessage(code){return ({STUDY_ROUTING_MISMATCH:'El servicio no corresponde a uno de los estudios.',CUSTOM_ROUTING_CONFIRMATION_REQUIRED:'Confirma el servicio del estudio personalizado.',STUDY_TYPE_INVALID:'Un estudio ya no está disponible. Retíralo y vuelve a seleccionarlo.',STUDY_DUPLICATE:'El estudio está repetido.',ORDER_INDICATION_INVALID:'Revisa la indicación clínica.',ORDER_PRIORITY_INVALID:'Revisa la prioridad.'})[code]||'No se pudo emitir la composición. Revisa los estudios y sus parámetros.';}
    function showSuccess(data){
      workspace.hidden=true;mobile.hidden=true;reviewContent.replaceChildren(el('h4','Órdenes generadas'));reviewBox.removeAttribute('aria-labelledby');reviewBox.setAttribute('aria-label','Órdenes generadas');const grouped=groups();
      for(const doc of data.orders){
        const key=doc.order_routing_group_key,items=grouped.get(key)||[],card=el('article','','ordcomp-issued-order');
        card.append(el('h5',config.groups[key]),el('p',`${items.length} estudios · Emitida`));
        const list=el('ul');items.forEach(({item})=>list.append(el('li',item.name)));card.append(list);
        card.append(el('small',`Referencia: ${doc.document_uuid}`));
        const query=new URLSearchParams({uuid:doc.document_uuid,doctor_id:context.doctor});
        const print=el('a','Imprimir','btn btn-outline-primary btn-sm');print.href='/modules/clinical/ui/portable-order.php?'+query;print.target='_blank';print.rel='noopener';
        const pdf=el('a','Descargar PDF','btn btn-outline-primary btn-sm');pdf.href='/modules/clinical/ui/portable-order-pdf.php?'+query;pdf.target='_blank';pdf.rel='noopener';card.append(print,pdf);reviewContent.append(card);
      }
      selected=[];customDraft={};composer?.destroy();composer=null;counts();
      reviewContent.append(button('Volver a Estudios de diagnóstico',()=>{reset();options.onDone?.();}));
    }
    return {open,hide,reset,dirty,visible:()=>!host.hidden,locked:()=>busy||uncertain,issued:()=>issued,back:()=>{
      if(busy||uncertain)return false;
      if(reviewBox.open&&!issued){closeReview();return false;}
      return hide();
    }};
  }
  window.mxmedOrderCompositionV1={mount,routing};
})();
