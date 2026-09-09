(()=>{
 'use strict';
 const byId=id=>document.getElementById(id),cards=byId('pending-cards'),message=byId('inbox-message'),dialog=byId('review-detail');
 const labels={DOCTOR_PROFILE_PHOTO:'Foto de perfil'};
 let offset=0,nextOffset=null,opener=null;
 const imageUrl=item=>'/api/internal/media-review/review-image.php?submission_id='+encodeURIComponent(item.submission_id);
 // DB dates have no timezone annotation: preserve their recorded wall-clock value.
 const dateLabel=value=>{const match=/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}:\d{2})/.exec(value||'');return match?`${match[3]}/${match[2]}/${match[1]}, ${match[4]}`:'Fecha no disponible';};
 const specs=item=>`${item.review.width} × ${item.review.height} px · ${(item.review.byte_size/1024).toLocaleString('es-MX',{maximumFractionDigits:1})} KB · WebP`;
 function node(tag,className,text){const el=document.createElement(tag);if(className)el.className=className;if(text!==undefined)el.textContent=text;return el;}
 function openDetail(item,button){
  opener=button;byId('detail-title').textContent=item.owner_display_name;
  byId('detail-date').textContent='Recibida: '+dateLabel(item.created_at);byId('detail-specs').textContent=specs(item);
  const image=byId('detail-image');image.hidden=false;byId('detail-unavailable').hidden=true;
  image.alt='Foto de perfil enviada por '+item.owner_display_name;image.src=imageUrl(item);
  dialog.showModal();byId('close-detail').focus();
 }
 byId('close-detail').addEventListener('click',()=>dialog.close());
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
 load();
})();
