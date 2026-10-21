/* Compact button/listbox pattern already used by the team status filter.
 * Native selects remain the value/event source; the popover stays in the dialog's top layer. */
(() => {
  'use strict';
  const entries=new Map();let active=null,sequence=0,search='',searchAt=0,dismissedBackdrop=false;
  const options=e=>[...e.list.querySelectorAll('[role=option]:not(:disabled)')];
  function close(focus=false){if(!active)return;const e=active;active=null;e.list.hidePopover();e.button.setAttribute('aria-expanded','false');if(focus&&e.button.isConnected)e.button.focus({preventScroll:true});}
  function sync(select){const e=entries.get(select);if(!e)return;const value=select.selectedOptions[0]?.textContent||'Seleccionar';e.button.textContent=value+' ▾';e.button.setAttribute('aria-label',e.name+': '+value);e.button.disabled=select.disabled;if(active===e&&select.disabled)close();}
  function position(e){
    const v=visualViewport,b=e.button.getBoundingClientRect(),left=v?.offsetLeft||0,top=v?.offsetTop||0,w=v?.width||innerWidth,h=v?.height||innerHeight,gap=6;
    if(b.bottom<top||b.top>top+h){close();return;}
    const below=top+h-b.bottom-gap,above=b.top-top-gap,up=below<Math.min(240,e.list.scrollHeight+2)&&above>below;
    const height=Math.max(0,Math.min(240,up?above:below));
    e.list.style.width=Math.min(Math.max(b.width,180),w-16)+'px';e.list.style.maxHeight=height+'px';
    e.list.style.left=Math.max(left+8,Math.min(b.left,left+w-e.list.getBoundingClientRect().width-8))+'px';
    e.list.style.top=(up?Math.max(top+8,b.top-gap-e.list.getBoundingClientRect().height):b.bottom+gap)+'px';e.list.dataset.direction=up?'above':'below';
  }
  const normalize=value=>value.normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLocaleLowerCase('es');
  const printable=e=>e.key.length===1&&!e.ctrlKey&&!e.metaKey&&!e.altKey&&e.key!==' ';
  function typeahead(e,key){
    search=(Date.now()-searchAt>900?'':search)+normalize(key);searchAt=Date.now();
    const a=options(e),match=a.find(b=>normalize(b.textContent).startsWith(search));
    e.notice.textContent=match?'Coincidencia: '+match.textContent:'Sin coincidencias para «'+search+'». Sigue escribiendo o espera para buscar de nuevo.';
    e.list.dataset.noResults=String(!match);
    position(e);if(match){match.focus({preventScroll:true});match.scrollIntoView({block:'nearest'});}else{e.list.focus({preventScroll:true});e.notice.scrollIntoView({block:'nearest'});}
  }
  function open(e){
    if(e.select.disabled)return;if(active===e){close(true);return;}close();search='';
    e.list.replaceChildren(...[...e.select.options].map(o=>{const b=document.createElement('button');b.type='button';b.role='option';b.tabIndex=-1;b.disabled=o.disabled;b.textContent=o.textContent;b.setAttribute('aria-selected',String(o.selected));
      b.onclick=event=>{event.preventDefault();if(b.disabled)return;e.select.value=o.value;close(true);sync(e.select);e.select.dispatchEvent(new Event('input',{bubbles:true}));e.select.dispatchEvent(new Event('change',{bubbles:true}));};return b;}));
    e.notice=document.createElement('p');e.notice.setAttribute('role','status');e.notice.setAttribute('aria-live','polite');e.notice.className='team-select-search-status';e.list.append(e.notice);
    (e.button.closest('dialog')||document.body).append(e.list);e.list.showPopover();active=e;e.button.setAttribute('aria-expanded','true');position(e);e.anchor=e.button.getBoundingClientRect();
    const selected=e.list.querySelector('[aria-selected=true]:not(:disabled)')||options(e)[0];selected?.focus({preventScroll:true});selected?.scrollIntoView({block:'nearest'});
  }
  function enhance(select){
    if(entries.has(select)||select.multiple||select.size>1)return;
    const label=select.labels?.[0],name=select.dataset.label||[...(label?.childNodes||[])].filter(n=>n.nodeType===3).map(n=>n.textContent.trim()).join(' ')||'Seleccionar';
    const button=document.createElement('button'),list=document.createElement('div');button.type='button';button.className='team-select-trigger';button.id=select.dataset.triggerId||select.id+'-trigger';
    list.id='team-select-options-'+(++sequence);list.className='team-select-options';list.setAttribute('popover','manual');list.role='listbox';list.tabIndex=-1;list.setAttribute('aria-label',name);
    button.setAttribute('aria-label',name);button.setAttribute('aria-haspopup','listbox');button.setAttribute('aria-expanded','false');button.setAttribute('aria-controls',list.id);
    select.hidden=true;select.tabIndex=-1;select.setAttribute('aria-hidden','true');select.after(button);if(label?.htmlFor===select.id)label.htmlFor=button.id;
    const e={select,button,list,name};entries.set(select,e);button.onclick=event=>{event.preventDefault();open(e);};button.onkeydown=event=>{if(['ArrowDown','ArrowUp','Home','End'].includes(event.key)){event.preventDefault();open(e);if(['End','ArrowUp'].includes(event.key))options(e).at(-1)?.focus();}else if(printable(event)){event.preventDefault();open(e);typeahead(e,event.key);}};
    list.onkeydown=event=>{const a=options(e),i=a.indexOf(document.activeElement);if(event.key==='Escape'){event.preventDefault();event.stopPropagation();close(true);return;}
      if(event.key==='Tab'){close(true);return;}
      if(['ArrowDown','ArrowUp','Home','End'].includes(event.key)){event.preventDefault();event.stopPropagation();a[event.key==='Home'?0:event.key==='End'?a.length-1:(i+(event.key==='ArrowDown'?1:-1)+a.length)%a.length]?.focus();}
      else if(printable(event)){event.preventDefault();typeahead(e,event.key);}else if(event.key==='Enter'&&document.activeElement===e.list){event.preventDefault();}
    };select.addEventListener('change',()=>sync(select));sync(select);
  }
  function scan(){for(const [s,e] of entries)if(!s.isConnected){if(active===e)close();e.list.remove();entries.delete(s);}document.querySelectorAll('.advisor-workspace select,dialog select').forEach(enhance);}
  new MutationObserver(changes=>{let scanNeeded=false;for(const m of changes){if(m.type==='childList'&&[...m.addedNodes,...m.removedNodes].some(n=>n.nodeType===1&&(n.matches('select')||n.querySelector('select'))))scanNeeded=true;
      if(m.target.matches?.('select,option'))sync(m.target.closest('select'));}if(scanNeeded)scan();}).observe(document.body,{subtree:true,childList:true,attributes:true,attributeFilter:['disabled','selected']});
  document.addEventListener('pointerdown',e=>{dismissedBackdrop=false;if(active&&!active.list.contains(e.target)&&!active.button.contains(e.target)){
    const dialog=active.button.closest('dialog'),box=dialog?.getBoundingClientRect();dismissedBackdrop=!!box&&(e.clientX<box.left||e.clientX>box.right||e.clientY<box.top||e.clientY>box.bottom);
    close(dismissedBackdrop);if(dismissedBackdrop){e.preventDefault();e.stopImmediatePropagation();}
  }},true);
  document.addEventListener('click',e=>{if(dismissedBackdrop){dismissedBackdrop=false;e.preventDefault();e.stopImmediatePropagation();}},true);
  // Handle popup keys before the dialog's Tab trap or Escape dismissal.
  document.addEventListener('keydown',e=>{if(active&&e.key==='Escape'){e.preventDefault();e.stopImmediatePropagation();close(true);}else if(active&&e.key==='Tab'){close(true);}},true);
  document.addEventListener('close',()=>close(),true);document.addEventListener('scroll',e=>{if(active&&!active.list.contains(e.target)){const b=active.button.getBoundingClientRect(),a=active.anchor;if(!a||Math.abs(b.top-a.top)>1||Math.abs(b.left-a.left)>1)close();}},true);
  window.addEventListener('resize',()=>{if(active)position(active);});visualViewport?.addEventListener('resize',()=>{if(active)position(active);});
  window.teamSelects={sync,close};scan();
})();
