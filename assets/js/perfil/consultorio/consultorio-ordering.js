(function(){
  'use strict';
  const start=()=>{
    const root=document.getElementById('p-consultorio');
    const nav=root?.querySelector('.mm-tabs-embed');
    const status=root?.querySelector('[data-cons-order-status]');
    const saveButton=root?.querySelector('[data-cons-save-order]');
    if(!root||!nav||!status||!saveButton)return;

    let doctorId='';
    let fingerprint='';
    let configured=false;
    let serverOrder=[];
    let busy=false;
    let armedId='';
    let dragId='';
    let dropId='';
    let dropAfter=false;
    let touchState=null;
    let suppressClickUntil=0;

    const clean=value=>String(value??'').trim();
    const idFromButton=button=>clean(button?.getAttribute('data-bs-target')||'').replace(/^#sede/,'');
    const itemFromId=id=>nav.querySelector(`[data-bs-target="#sede${CSS.escape(clean(id))}"]`)?.closest('.nav-item')||null;
    const officeItems=()=>Array.from(nav.querySelectorAll('.nav-item')).filter(item=>
      !item.hidden && !!item.querySelector('.nav-link[data-bs-target^="#sede"]'));
    const currentOrder=()=>officeItems().map(item=>idFromButton(item.querySelector('.nav-link'))).filter(Boolean);
    const addItem=()=>root.querySelector('#btn-consul-add')?.closest('.nav-item')||null;

    const ensureHandle=item=>{
      if(!(item instanceof HTMLElement))return;
      item.setAttribute('data-consultorio-tab-item','');
      item.draggable=false;
      const button=item.querySelector('.nav-link[data-bs-target^="#sede"]');
      if(!button)return;
      const id=idFromButton(button);
      let handle=item.querySelector('.cons-tab-drag-handle');
      if(!handle){
        handle=document.createElement('span');
        handle.className='cons-tab-drag-handle material-symbols-rounded';
        handle.textContent='drag_indicator';
        item.appendChild(handle);
      }
      handle.setAttribute('role','button');
      handle.tabIndex=0;
      handle.setAttribute('aria-label',`Mover ${button.querySelector('.tab-lbl')?.textContent?.replace(/\s+/g,' ').trim()||`consultorio ${id}`}`);
      handle.setAttribute('title','Arrastra o usa las flechas izquierda y derecha');
      handle.setAttribute('aria-describedby','cons-order-help');
    };

    const decorate=()=>officeItems().forEach(ensureHandle);
    const arrange=ids=>{
      const anchor=addItem();
      ids.forEach(id=>{
        const item=itemFromId(id);
        if(item)nav.insertBefore(item,anchor);
      });
      updatePresentation();
    };
    const clearDropMarkers=()=>officeItems().forEach(item=>item.classList.remove('cons-tab-drop-before','cons-tab-drop-after','cons-tab-dragging'));
    const updatePresentation=()=>{
      decorate();
      const items=officeItems();
      items.forEach((item,index)=>{
        item.classList.toggle('cons-tab-primary',configured&&index===0);
        const button=item.querySelector('.nav-link');
        let badge=item.querySelector('.cons-tab-primary-badge');
        if(configured&&index===0){
          if(!badge){badge=document.createElement('span');badge.className='cons-tab-primary-badge';badge.textContent='Principal';button?.appendChild(badge);}
        }else badge?.remove();
        const handle=item.querySelector('.cons-tab-drag-handle');
        if(handle&&button)handle.setAttribute('aria-label',`Mover ${button.querySelector('.tab-lbl')?.textContent?.replace(/\s+/g,' ').trim()||`consultorio ${index+1}`}, posición ${index+1} de ${items.length}`);
      });
      const strong=status.querySelector('strong');
      const help=status.querySelector('#cons-order-help');
      if(strong)strong.textContent=configured?'Orden confirmado.':'Orden de consultorios sin confirmar.';
      if(help)help.textContent=configured
        ?'La primera pestaña es el consultorio principal. Arrastra o usa las flechas para cambiar el orden.'
        :'Guarda este orden o mueve una pestaña para establecer el consultorio principal.';
      status.classList.toggle('is-configured',configured);
      saveButton.hidden=configured;
      saveButton.disabled=busy;
    };
    const showMessage=(message,tone='')=>{
      let note=status.querySelector('[data-cons-order-message]');
      if(!note){note=document.createElement('span');note.setAttribute('data-cons-order-message','');status.querySelector('div')?.appendChild(note);}
      note.textContent=message;
      note.dataset.tone=tone;
    };

    const persist=async ids=>{
      ids=Array.from(ids||[]).map(clean).filter(Boolean);
      if(root.dataset.consLoadState !== 'READY') return false;
      if(busy||!doctorId||!fingerprint||ids.length!==officeItems().length)return false;
      const previous=serverOrder.slice();
      busy=true;saveButton.disabled=true;showMessage('Guardando orden…');
      try{
        const response=await fetch('/api/agenda/index.php/consultorios/reorder',{
          method:'PUT',credentials:'same-origin',headers:{'Accept':'application/json','Content-Type':'application/json'},
          body:JSON.stringify({doctor_id:doctorId,consultorio_ids:ids,expected_order_fingerprint:fingerprint})
        });
        const json=await response.json().catch(()=>null);
        if(!response.ok||json?.ok!==true){
          arrange(previous);
          showMessage(response.status===403?'Tu rol permite consultar sedes, pero se requiere propietario o administrador para cambiar el orden.':'No se guardó el orden. Recarga las sedes e inténtalo nuevamente.','error');
          return false;
        }
        const data=json.data||{};
        serverOrder=Array.isArray(data.presentation_order)?data.presentation_order.map(clean):ids;
        fingerprint=clean(data.presentation_order_fingerprint);
        configured=data.presentation_order_configured===true;
        arrange(serverOrder);
        showMessage('Orden guardado. La primera sede es ahora el consultorio principal.','success');
        root.dispatchEvent(new CustomEvent('mx:consultorio-order-saved',{detail:{order:serverOrder.slice(),fingerprint,configured}}));
        return true;
      }catch(_){
        arrange(previous);
        showMessage('No se pudo conectar para guardar el orden.','error');
        return false;
      }finally{busy=false;updatePresentation();}
    };
    const moveRelative=(sourceId,targetId,after=false)=>{
      const ids=currentOrder();
      const from=ids.indexOf(clean(sourceId));
      const target=ids.indexOf(clean(targetId));
      if(from<0||target<0||from===target)return;
      const [moved]=ids.splice(from,1);
      let insertion=ids.indexOf(clean(targetId))+(after?1:0);
      insertion=Math.max(0,Math.min(insertion,ids.length));
      ids.splice(insertion,0,moved);
      arrange(ids);
      persist(ids);
    };
    const moveByKeyboard=(id,direction)=>{
      const ids=currentOrder();
      const index=ids.indexOf(clean(id));
      const next=index+direction;
      if(index<0||next<0||next>=ids.length)return;
      [ids[index],ids[next]]=[ids[next],ids[index]];
      arrange(ids);
      persist(ids).then(()=>itemFromId(id)?.querySelector('.cons-tab-drag-handle')?.focus());
    };

    root.addEventListener('mx:consultorios-hydrated',event=>{
      const detail=event.detail||{};
      doctorId=clean(detail.doctor_id);
      fingerprint=clean(detail.presentation_order_fingerprint);
      configured=detail.presentation_order_configured===true;
      serverOrder=Array.isArray(detail.presentation_order)?detail.presentation_order.map(clean).filter(Boolean):currentOrder();
      arrange(serverOrder);
      showMessage('');
    });
    root.addEventListener('mx:consultorio-pane-created',()=>{
      if(root.dataset.consLoadState !== 'READY') return;
      window.setTimeout(()=>{configured=false;decorate();updatePresentation();},0);
    });
    saveButton.addEventListener('click',()=>persist(currentOrder()));

    nav.addEventListener('pointerdown',event=>{
      const handle=event.target instanceof Element?event.target.closest('.cons-tab-drag-handle'):null;
      const item=handle?.closest('.nav-item');
      const id=idFromButton(item?.querySelector('.nav-link'));
      if(!handle||!item||!id)return;
      event.stopPropagation();
      armedId=id;
      if(event.pointerType==='touch'||event.pointerType==='pen'){
        touchState={pointerId:event.pointerId,id,startX:event.clientX,startY:event.clientY,moved:false};
        handle.setPointerCapture?.(event.pointerId);
      }else item.draggable=true;
    });
    nav.addEventListener('dragstart',event=>{
      const item=event.target instanceof Element?event.target.closest('.nav-item'):null;
      const id=idFromButton(item?.querySelector('.nav-link'));
      if(!item||!id||id!==armedId){event.preventDefault();return;}
      dragId=id;item.classList.add('cons-tab-dragging');
      event.dataTransfer.effectAllowed='move';event.dataTransfer.setData('text/plain',id);
    });
    nav.addEventListener('dragover',event=>{
      if(!dragId)return;
      const item=event.target instanceof Element?event.target.closest('[data-consultorio-tab-item]'):null;
      if(!item)return;
      event.preventDefault();clearDropMarkers();itemFromId(dragId)?.classList.add('cons-tab-dragging');
      dropId=idFromButton(item.querySelector('.nav-link'));
      dropAfter=event.clientX>item.getBoundingClientRect().left+item.getBoundingClientRect().width/2;
      item.classList.add(dropAfter?'cons-tab-drop-after':'cons-tab-drop-before');
    });
    nav.addEventListener('drop',event=>{event.preventDefault();if(dragId&&dropId)moveRelative(dragId,dropId,dropAfter);suppressClickUntil=Date.now()+350;clearDropMarkers();dragId='';dropId='';armedId='';});
    nav.addEventListener('dragend',()=>{officeItems().forEach(item=>item.draggable=false);clearDropMarkers();dragId='';dropId='';armedId='';});
    nav.addEventListener('pointermove',event=>{
      if(!touchState||event.pointerId!==touchState.pointerId)return;
      if(Math.hypot(event.clientX-touchState.startX,event.clientY-touchState.startY)<8&&!touchState.moved)return;
      touchState.moved=true;event.preventDefault();
      const target=document.elementFromPoint(event.clientX,event.clientY)?.closest?.('[data-consultorio-tab-item]');
      clearDropMarkers();itemFromId(touchState.id)?.classList.add('cons-tab-dragging');
      if(target){dropId=idFromButton(target.querySelector('.nav-link'));dropAfter=event.clientX>target.getBoundingClientRect().left+target.getBoundingClientRect().width/2;target.classList.add(dropAfter?'cons-tab-drop-after':'cons-tab-drop-before');}
    });
    const finishTouch=event=>{
      if(!touchState||event.pointerId!==touchState.pointerId)return;
      if(touchState.moved&&dropId)moveRelative(touchState.id,dropId,dropAfter);
      if(touchState.moved)suppressClickUntil=Date.now()+350;
      touchState=null;dropId='';dropAfter=false;armedId='';clearDropMarkers();
    };
    nav.addEventListener('pointerup',finishTouch);nav.addEventListener('pointercancel',finishTouch);
    nav.addEventListener('keydown',event=>{
      const handle=event.target instanceof Element?event.target.closest('.cons-tab-drag-handle'):null;
      if(!handle)return;
      const id=idFromButton(handle.closest('.nav-item')?.querySelector('.nav-link'));
      if(event.key==='ArrowLeft'||event.key==='ArrowRight'){
        event.preventDefault();event.stopPropagation();moveByKeyboard(id,event.key==='ArrowLeft'?-1:1);
      }
    });
    nav.addEventListener('click',event=>{
      if(event.target instanceof Element&&event.target.closest('.cons-tab-drag-handle')){event.preventDefault();event.stopPropagation();return;}
      if(Date.now()<suppressClickUntil){event.preventDefault();event.stopPropagation();}
    },true);

    decorate();updatePresentation();
    window.mxmedConsultorioOrdering={currentOrder,persist,arrange};
  };
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',start,{once:true});else start();
})();
