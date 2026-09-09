(()=>{
 'use strict';
 const byId=id=>document.getElementById(id),cards=byId('pending-cards'),message=byId('inbox-message'),dialog=byId('review-detail');
 const labels={DOCTOR_PROFILE_PHOTO:'Foto de perfil'};
 let offset=0,nextOffset=null,opener=null,selected=null,canApprove=false,busy=false;
 const imageUrl=item=>'/api/internal/media-review/review-image.php?submission_id='+encodeURIComponent(item.submission_id);
 // DB dates have no timezone annotation: preserve their recorded wall-clock value.
 const dateLabel=value=>{const match=/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}:\d{2})/.exec(value||'');return match?`${match[3]}/${match[2]}/${match[1]}, ${match[4]}`:'Fecha no disponible';};
 const specs=item=>`${item.review.width} × ${item.review.height} px · ${(item.review.byte_size/1024).toLocaleString('es-MX',{maximumFractionDigits:1})} KB · WebP`;
 function node(tag,className,text){const el=document.createElement(tag);if(className)el.className=className;if(text!==undefined)el.textContent=text;return el;}
 function openDetail(item,button){
  selected=item;byId('approval-confirmation').hidden=true;byId('approval-message').textContent='';byId('approve-photo').hidden=!canApprove;
  opener=button;byId('detail-title').textContent=item.owner_display_name;
  byId('detail-date').textContent='Recibida: '+dateLabel(item.created_at);byId('detail-specs').textContent=specs(item);
  const image=byId('detail-image');image.hidden=false;byId('detail-unavailable').hidden=true;
  image.alt='Foto de perfil enviada por '+item.owner_display_name;image.src=imageUrl(item);
  dialog.showModal();byId('close-detail').focus();
 }
 byId('close-detail').addEventListener('click',()=>{if(!busy)dialog.close();});
 dialog.addEventListener('cancel',event=>{if(busy)event.preventDefault();});
 dialog.addEventListener('close',()=>{byId('detail-image').removeAttribute('src');opener?.focus();});
 byId('detail-image').addEventListener('error',()=>{byId('detail-image').hidden=true;byId('detail-unavailable').hidden=false;});
 function render(item){
  const card=node('article','card'),thumb=node('div','review-thumb'),image=node('img');
  image.alt='Foto de perfil enviada por '+item.owner_display_name;image.loading='lazy';image.decoding='async';image.width=item.review.width;image.height=item.review.height;
  image.addEventListener('error',()=>{image.hidden=true;thumb.append(node('p','', 'Imagen no disponible.'));},{once:true});image.src=imageUrl(item);thumb.append(image);
  const body=node('div','card-body'),button=node('button','', 'Ver imagen');button.type='button';button.setAttribute('aria-label','Ver imagen de '+item.owner_display_name);button.addEventListener('click',()=>openDetail(item,button));
  body.append(node('h3','',item.owner_display_name),node('p','purpose',labels[item.purpose]||'Imagen'),node('p','status-label','Pendiente de revisión'),node('p','received','Recibida: '+dateLabel(item.created_at)),node('p','specs',specs(item)),button);card.append(thumb,body);cards.append(card);
 }
 async function load(){
  cards.replaceChildren();cards.setAttribute('aria-busy','true');message.hidden=false;message.textContent='Cargando medios pendientes…';byId('page-count').textContent='';document.querySelector('.pagination').hidden=true;
  try{
   const response=await fetch('/api/internal/media-review/pending.php?limit=25&offset='+offset,{credentials:'same-origin',cache:'no-store'});
   if(!response.ok)throw new Error('unavailable');const result=await response.json();if(!result.ok||!Array.isArray(result.data?.items))throw new Error('unavailable');
   const {items,pagination}=result.data;items.forEach(render);byId('page-count').textContent=`${items.length} en esta página`;
   message.hidden=items.length>0;message.textContent='No hay medios pendientes de revisión.';
   nextOffset=pagination.next_offset;byId('previous-page').disabled=offset===0;byId('next-page').disabled=!pagination.has_more;byId('page-number').textContent='Página '+(Math.floor(offset/25)+1);
   document.querySelector('.pagination').hidden=offset===0&&!pagination.has_more;
  }catch{message.hidden=false;message.textContent='No fue posible cargar los medios pendientes.';}
  finally{cards.setAttribute('aria-busy','false');}
 }
 byId('previous-page').addEventListener('click',()=>{offset=Math.max(0,offset-25);load();});
 byId('next-page').addEventListener('click',()=>{if(nextOffset!==null){offset=nextOffset;load();}});
 async function approvalOptions(){
  const response=await fetch('/api/internal/media-review/approval-options.php',{credentials:'same-origin',cache:'no-store'});
  if(!response.ok)throw new Error('unavailable');return response.json();
 }
 byId('approve-photo').addEventListener('click',()=>{byId('approval-confirmation').hidden=false;byId('approve-photo').hidden=true;byId('cancel-approval').focus();});
 byId('cancel-approval').addEventListener('click',()=>{if(busy)return;byId('approval-confirmation').hidden=true;byId('approve-photo').hidden=false;byId('approve-photo').focus();});
 byId('confirm-approval').addEventListener('click',async()=>{
  if(busy||!selected)return;busy=true;const item=selected;
  for(const id of ['confirm-approval','cancel-approval','close-detail'])byId(id).disabled=true;
  byId('confirm-approval').textContent='Publicando…';byId('approval-confirmation').setAttribute('aria-busy','true');byId('approval-message').textContent='Publicando foto…';
  try{
   // Refresh the short-lived session-bound token; POST rechecks all authority.
   const options=await approvalOptions();if(!options.ok||!options.can_approve||!options.csrf)throw new Error('unavailable');
   const response=await fetch('/api/internal/media-review/approve.php',{method:'POST',credentials:'same-origin',cache:'no-store',headers:{'Content-Type':'application/json'},body:JSON.stringify({submission_id:item.submission_id,csrf:options.csrf})});
   if(response.status===409){dialog.close();await load();message.hidden=false;message.textContent='Esta solicitud ya fue procesada. La lista se actualizó.';message.tabIndex=-1;message.focus();return;}
   const result=await response.json();if(!response.ok||!result.ok)throw new Error('unavailable');
   dialog.close();await load();message.hidden=false;message.textContent='Foto aprobada y publicada.';message.tabIndex=-1;message.focus();
  }catch{byId('approval-message').textContent='No fue posible aprobar la foto. Intenta de nuevo.';}
  finally{busy=false;for(const id of ['confirm-approval','cancel-approval','close-detail'])byId(id).disabled=false;byId('confirm-approval').textContent='Aprobar';byId('approval-confirmation').setAttribute('aria-busy','false');}
 });
 approvalOptions().then(options=>{canApprove=options.ok&&options.can_approve===true;if(dialog.open)byId('approve-photo').hidden=!canApprove;}).catch(()=>{canApprove=false;});
 load();
})();
