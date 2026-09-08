(() => {
  const dialog=document.querySelector('[data-gallery-dialog]'), trigger=document.querySelector('[data-gallery-open]');
  if(!dialog || !trigger)return;
  const thumbs=Array.from(dialog.querySelectorAll('[data-gallery-thumb]'));
  const main=dialog.querySelector('[data-gallery-main]'), previous=dialog.querySelector('[data-gallery-prev]'), next=dialog.querySelector('[data-gallery-next]'), error=dialog.querySelector('[data-gallery-error]');
  let index=0,scroll=0,previousOverflow='';
  function show(value){
    index=(value+thumbs.length)%thumbs.length;
    const image=thumbs[index].querySelector('img');error.hidden=true;
    main.src=image.src;main.alt=image.alt;
    dialog.querySelector('[data-gallery-index]').textContent=(index+1)+' / '+thumbs.length;
    thumbs.forEach((button,i)=>button.setAttribute('aria-pressed',String(i===index)));
    const strip=thumbs[index].parentElement, item=thumbs[index];
    strip.scrollLeft=Math.max(0,item.offsetLeft-strip.offsetLeft-(strip.clientWidth-item.clientWidth)/2);
  }
  main.addEventListener('error',()=>{error.hidden=false;});
  previous.hidden=next.hidden=thumbs.length<2;
  trigger.addEventListener('click',()=>{
    scroll=window.scrollY;previousOverflow=document.body.style.overflow;
    dialog.showModal();document.body.style.overflow='hidden';show(0);
    dialog.querySelector('[data-gallery-close]').focus({preventScroll:true});
  });
  dialog.querySelector('[data-gallery-close]').addEventListener('click',()=>dialog.close());
  dialog.addEventListener('click',event=>{if(event.target===dialog){const r=dialog.getBoundingClientRect();if(event.clientX<r.left||event.clientX>r.right||event.clientY<r.top||event.clientY>r.bottom)dialog.close();}});
  dialog.addEventListener('close',()=>{document.body.style.overflow=previousOverflow;window.scrollTo({top:scroll,behavior:'instant'});trigger.focus({preventScroll:true});});
  previous.addEventListener('click',()=>show(index-1));next.addEventListener('click',()=>show(index+1));
  thumbs.forEach((button,i)=>button.addEventListener('click',()=>show(i)));
  dialog.addEventListener('keydown',event=>{if(event.key==='ArrowLeft'||event.key==='ArrowRight'){event.preventDefault();show(index+(event.key==='ArrowRight'?1:-1));}});
})();
