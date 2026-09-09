(()=>{
 'use strict';
 const byId=id=>document.getElementById(id),cards=byId('pending-cards'),message=byId('inbox-message'),dialog=byId('review-detail');
 const labels={DOCTOR_PROFILE_PHOTO:'Foto de perfil',PHYSICIAN_PERSONAL_LOGO:'Logotipo profesional'};
 let offset=0,nextOffset=null,opener=null,selected=null,canApprove=false,busy=false,canDownload=false,canCorrect=false,reviewLoaded=false;
 let canImprove=false,proposalUrl=null,proposalLoaded=false,proposalEpoch=0;
 const improvementIds=['generate-improvement','accept-improvement','discard-improvement'];
 const reviewObjectUrls=new Map();
 const reviewEndpoint=item=>'/api/internal/media-review/review-image.php?submission_id='+encodeURIComponent(item.submission_id);
 const imageUrl=item=>reviewObjectUrls.get(item.submission_id)||reviewEndpoint(item);
 // DB dates have no timezone annotation: preserve their recorded wall-clock value.
 const dateLabel=value=>{const match=/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}:\d{2})/.exec(value||'');return match?`${match[3]}/${match[2]}/${match[1]}, ${match[4]}`:'Fecha no disponible';};
 const specs=item=>`${item.review.width} × ${item.review.height} px · ${(item.review.byte_size/1024).toLocaleString('es-MX',{maximumFractionDigits:1})} KB · WebP`;
 function node(tag,className,text){const el=document.createElement(tag);if(className)el.className=className;if(text!==undefined)el.textContent=text;return el;}
 function openDetail(item,button){
  clearProposal();byId('improvement-message').textContent='';
  reviewLoaded=false;byId('approve-photo').disabled=true;byId('corrected-file').value='';byId('corrected-filename').textContent='';byId('submit-corrected').hidden=true;byId('intervention-message').textContent='';
  byId('detail-purpose').textContent=labels[item.purpose]||'Imagen';byId('approval-question').textContent=item.purpose==='PHYSICIAN_PERSONAL_LOGO'?'¿Aprobar este logotipo profesional?':'¿Aprobar esta foto de perfil?';
  selected=item;byId('approval-confirmation').hidden=true;byId('approval-message').textContent='';byId('approve-photo').hidden=!canApprove;
  opener=button;byId('detail-title').textContent=item.owner_display_name;
  byId('detail-date').textContent='Recibida: '+dateLabel(item.created_at);byId('detail-specs').textContent=specs(item);
  const image=byId('detail-image');image.hidden=false;byId('detail-unavailable').hidden=true;
  image.alt=(labels[item.purpose]||'Imagen')+' de '+item.owner_display_name;image.src=imageUrl(item);
  interventionControls();dialog.showModal();byId('close-detail').focus();if(canImprove&&item.purpose==='PHYSICIAN_PERSONAL_LOGO')loadProposal(item,false).catch(()=>{});
 }
 byId('close-detail').addEventListener('click',()=>{if(!busy)dialog.close();});
 dialog.addEventListener('cancel',event=>{if(busy)event.preventDefault();});
 dialog.addEventListener('close',()=>{clearProposal();byId('detail-image').removeAttribute('src');if(opener?.isConnected)opener.focus();else cards.querySelector('button')?.focus();});
 byId('detail-image').addEventListener('load',()=>{if(byId('detail-image').hidden||!byId('detail-image').getAttribute('src'))return;reviewLoaded=true;byId('approve-photo').disabled=busy;byId('accept-improvement').disabled=busy||!proposalLoaded;});
 byId('detail-image').addEventListener('error',()=>{reviewLoaded=false;byId('approve-photo').disabled=true;byId('accept-improvement').disabled=true;byId('detail-image').hidden=true;byId('detail-unavailable').hidden=false;});
 function render(item){
  const card=node('article','card'),thumb=node('div','review-thumb'),image=node('img');
  image.alt=(labels[item.purpose]||'Imagen')+' de '+item.owner_display_name;image.loading='lazy';image.decoding='async';image.width=item.review.width;image.height=item.review.height;
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
  if(busy||!selected||!reviewLoaded)return;busy=true;const item=selected;
  for(const id of ['confirm-approval','cancel-approval','close-detail','download-source','choose-corrected','submit-corrected',...improvementIds])byId(id).disabled=true;
  byId('confirm-approval').textContent='Publicando…';byId('approval-confirmation').setAttribute('aria-busy','true');byId('approval-message').textContent=item.purpose==='PHYSICIAN_PERSONAL_LOGO'?'Publicando logotipo…':'Publicando foto…';
  try{
   // Refresh the short-lived session-bound token; POST rechecks all authority.
   const options=await approvalOptions();if(!options.ok||!options.can_approve||!options.csrf)throw new Error('unavailable');
   const response=await fetch('/api/internal/media-review/'+(item.purpose==='PHYSICIAN_PERSONAL_LOGO'?'approve-logo.php':'approve.php'),{method:'POST',credentials:'same-origin',cache:'no-store',headers:{'Content-Type':'application/json'},body:JSON.stringify({submission_id:item.submission_id,csrf:options.csrf})});
   if(response.status===409){dialog.close();await load();message.hidden=false;message.textContent='Esta solicitud ya fue procesada. La lista se actualizó.';message.tabIndex=-1;message.focus();return;}
   const result=await response.json();if(!response.ok||!result.ok)throw new Error('unavailable');
   dialog.close();await load();message.hidden=false;message.textContent=item.purpose==='PHYSICIAN_PERSONAL_LOGO'?'Logotipo aprobado y publicado.':'Foto aprobada y publicada.';message.tabIndex=-1;message.focus();
  }catch{byId('approval-message').textContent='No fue posible aprobar la imagen. Intenta de nuevo.';}
  finally{busy=false;for(const id of ['confirm-approval','cancel-approval','close-detail','download-source','choose-corrected','submit-corrected',...improvementIds])byId(id).disabled=false;byId('confirm-approval').textContent='Aprobar';byId('approval-confirmation').setAttribute('aria-busy','false');}
 });
 function interventionControls(){
  byId('logo-improvement').hidden=!canImprove||selected?.purpose!=='PHYSICIAN_PERSONAL_LOGO';
  byId('design-intervention').hidden=!canDownload&&!canCorrect;byId('download-source').hidden=!canDownload;byId('choose-corrected').hidden=!canCorrect;
 }
 function interventionBusy(value){
  busy=value;for(const id of ['download-source','choose-corrected','submit-corrected','close-detail','confirm-approval','cancel-approval',...improvementIds])byId(id).disabled=value;
  byId('approve-photo').disabled=value||!reviewLoaded;byId('design-intervention').setAttribute('aria-busy',String(value));
 }
 byId('choose-corrected').addEventListener('click',()=>{if(!busy)byId('corrected-file').click();});
 byId('corrected-file').addEventListener('change',()=>{
  byId('intervention-message').textContent='';const file=byId('corrected-file').files[0];byId('corrected-filename').textContent=file?file.name:'';byId('submit-corrected').hidden=!file;
  if(file)byId('submit-corrected').focus();
 });
 byId('download-source').addEventListener('click',async()=>{
  if(busy||!selected)return;interventionBusy(true);byId('intervention-message').textContent='Preparando original…';
  try{
   const response=await fetch('/api/internal/media-review/source-download.php?submission_id='+encodeURIComponent(selected.submission_id),{credentials:'same-origin',cache:'no-store'});
   if(!response.ok)throw new Error('unavailable');
   const filename=/filename="([a-z0-9.-]+)"/i.exec(response.headers.get('Content-Disposition')||'')?.[1];if(!filename)throw new Error('unavailable');
   const url=URL.createObjectURL(await response.blob()),a=document.createElement('a');a.href=url;a.download=filename;document.body.append(a);a.click();a.remove();setTimeout(()=>URL.revokeObjectURL(url),1000);
   byId('intervention-message').textContent='Original listo para descargar.';
  }catch{byId('intervention-message').textContent='No fue posible descargar el original.';}finally{interventionBusy(false);}
 });
 byId('submit-corrected').addEventListener('click',async()=>{
  const file=byId('corrected-file').files[0];if(busy||!selected||!file)return;
  let saved=false;interventionBusy(true);byId('approval-confirmation').hidden=true;byId('approve-photo').hidden=!canApprove;byId('intervention-message').textContent='Procesando versión corregida…';byId('intervention-message').scrollIntoView({block:'nearest'});
  try{
   const options=await approvalOptions();if(!options.ok||!options.can_upload_corrected||!options.csrf)throw new Error('unavailable');
   const form=new FormData();form.append('submission_id',selected.submission_id);form.append('csrf',options.csrf);form.append('corrected',file);
   const response=await fetch('/api/internal/media-review/corrected.php',{method:'POST',credentials:'same-origin',cache:'no-store',body:form});
   if(response.status===409){dialog.close();await load();message.hidden=false;message.textContent='Esta solicitud ya fue procesada. La lista se actualizó.';return;}
   const result=await response.json();if(!response.ok||!result.ok)throw new Error('unavailable');
   clearProposal();saved=true;selected={...selected,review:result.review};reviewLoaded=false;byId('approve-photo').disabled=true;
   byId('detail-specs').textContent=specs(selected);byId('detail-image').removeAttribute('src');byId('detail-image').hidden=true;byId('detail-unavailable').hidden=true;
   // A new Blob URL prevents reuse of the browser's decoded image for an unchanged endpoint URL.
   const fresh=await fetch(reviewEndpoint(selected),{credentials:'same-origin',cache:'no-store'});
   if(!fresh.ok||fresh.headers.get('Content-Type')!=='image/webp')throw new Error('review-unavailable');
   const preview=await fresh.blob();if(preview.size<1||preview.size>153600)throw new Error('review-unavailable');
   const previousUrl=reviewObjectUrls.get(selected.submission_id),newUrl=URL.createObjectURL(preview);
   reviewObjectUrls.set(selected.submission_id,newUrl);byId('detail-image').hidden=false;byId('detail-unavailable').hidden=true;byId('detail-image').src=newUrl;
   if(previousUrl)URL.revokeObjectURL(previousUrl);
   byId('corrected-file').value='';byId('corrected-filename').textContent='';byId('submit-corrected').hidden=true;
   await load();byId('intervention-message').textContent='Versión corregida lista para revisión.';
   byId('detail-image').tabIndex=-1;byId('detail-image').focus();byId('detail-image').scrollIntoView({block:'nearest'});
  }catch{if(saved){reviewLoaded=false;byId('detail-image').hidden=true;byId('detail-unavailable').hidden=false;}byId('intervention-message').textContent=saved?'Versión corregida guardada. Recarga la página para revisar la imagen.':'No fue posible guardar la versión corregida. Intenta de nuevo.';}finally{interventionBusy(false);}
 });

 function clearProposal(){proposalEpoch++;proposalLoaded=false;byId('accept-improvement').disabled=true;byId('proposal-actions').hidden=true;byId('proposal-panel').hidden=true;byId('current-version-label').hidden=true;byId('proposal-image').removeAttribute('src');if(proposalUrl)URL.revokeObjectURL(proposalUrl);proposalUrl=null;}
 byId('proposal-image').addEventListener('load',()=>{if(!proposalUrl||byId('proposal-image').currentSrc!==proposalUrl)return;proposalLoaded=true;byId('accept-improvement').disabled=busy||!reviewLoaded;});
 byId('proposal-image').addEventListener('error',()=>{clearProposal();byId('improvement-message').textContent='No fue posible cargar la propuesta.';});
 async function loadProposal(item,required){
  const epoch=proposalEpoch;
  const response=await fetch('/api/internal/media-review/improvement-preview.php?submission_id='+encodeURIComponent(item.submission_id),{credentials:'same-origin',cache:'no-store'});
  if(!response.ok){if(required)throw new Error('proposal-unavailable');return;}
  if(response.headers.get('Content-Type')!=='image/webp')throw new Error('proposal-unavailable');const blob=await response.blob();if(blob.size<1||blob.size>153600)throw new Error('proposal-unavailable');
  if(!dialog.open||selected?.submission_id!==item.submission_id||epoch!==proposalEpoch)return;
  clearProposal();proposalUrl=URL.createObjectURL(blob);byId('proposal-image').src=proposalUrl;byId('proposal-panel').hidden=false;byId('current-version-label').hidden=false;byId('proposal-actions').hidden=false;
 }
 async function freshReview(item){
  reviewLoaded=false;byId('approve-photo').disabled=true;byId('accept-improvement').disabled=true;byId('detail-image').hidden=true;byId('detail-image').removeAttribute('src');
  const response=await fetch(reviewEndpoint(item),{credentials:'same-origin',cache:'no-store'});if(!response.ok||response.headers.get('Content-Type')!=='image/webp')throw new Error('review-unavailable');
  const blob=await response.blob();if(blob.size<1||blob.size>153600)throw new Error('review-unavailable');const old=reviewObjectUrls.get(item.submission_id),url=URL.createObjectURL(blob);reviewObjectUrls.set(item.submission_id,url);byId('detail-image').src=url;byId('detail-image').hidden=false;byId('detail-unavailable').hidden=true;if(old)URL.revokeObjectURL(old);
 }
 for(const [id,route] of [['generate-improvement','improve-logo.php'],['accept-improvement','accept-improvement.php'],['discard-improvement','discard-improvement.php']])byId(id).addEventListener('click',async()=>{
  if(busy||!selected||!canImprove||selected.purpose!=='PHYSICIAN_PERSONAL_LOGO'||(id==='accept-improvement'&&(!proposalLoaded||!reviewLoaded)))return;
  const item=selected;if(id==='generate-improvement')clearProposal();interventionBusy(true);byId('improvement-message').textContent=id==='generate-improvement'?'Analizando el fondo exterior…':'Guardando decisión…';byId('improvement-message').scrollIntoView({block:'nearest'});
  try{
   const options=await approvalOptions();if(!options.ok||!options.can_improve||!options.csrf)throw new Error('denied');
   const response=await fetch('/api/internal/media-review/'+route,{method:'POST',credentials:'same-origin',cache:'no-store',headers:{'Content-Type':'application/json'},body:JSON.stringify({submission_id:item.submission_id,csrf:options.csrf})});
   if(response.status===409){clearProposal();byId('improvement-message').textContent='La solicitud cambió. Recarga para revisar la versión actual.';reviewLoaded=false;byId('detail-image').hidden=true;return;}
   const result=await response.json();if(!response.ok||!result.ok)throw new Error('unavailable');
   if(result.status==='IMPROVEMENT_INPUT_UNAVAILABLE'){clearProposal();byId('improvement-message').textContent='Este candidato requiere una nueva carga o corrección antes de generar una mejora.';return;}
   if(result.status==='ALREADY_IMPROVED'){clearProposal();byId('improvement-message').textContent='La versión actual ya contiene esta mejora.';return;}
   if(['NO_SAFE_IMPROVEMENT','ALREADY_TRANSPARENT'].includes(result.status)){clearProposal();byId('improvement-message').textContent='No se detectó una mejora automática segura.';return;}
   if(result.status==='PROPOSAL_CREATED'){await freshReview(item);await loadProposal(item,true);byId('improvement-message').textContent='Compara ambas versiones antes de decidir.';byId('improvement-comparison').scrollIntoView({block:'start'});}
   else if(result.status==='PROPOSAL_ACCEPTED'){clearProposal();selected={...item,review:result.review};byId('detail-specs').textContent=specs(selected);await freshReview(selected);await load();byId('improvement-message').textContent='Versión mejorada lista para revisión. Aún requiere aprobación.';}
   else{clearProposal();byId('improvement-message').textContent='Propuesta descartada. La versión actual se conserva.';}
  }catch{clearProposal();reviewLoaded=false;byId('detail-image').hidden=true;byId('detail-unavailable').hidden=false;byId('improvement-message').textContent='No fue posible completar la acción. Recarga para revisar el estado actual.';}
  finally{interventionBusy(false);byId('accept-improvement').disabled=!proposalLoaded||!reviewLoaded;}
 });

 approvalOptions().then(options=>{canApprove=options.ok&&options.can_approve===true;canDownload=options.ok&&options.can_download_source===true;canCorrect=options.ok&&options.can_upload_corrected===true;canImprove=options.ok&&options.can_improve===true;interventionControls();if(dialog.open){byId('approve-photo').hidden=!canApprove;if(canImprove&&selected?.purpose==='PHYSICIAN_PERSONAL_LOGO')loadProposal(selected,false).catch(()=>{});}}).catch(()=>{canApprove=false;canDownload=false;canCorrect=false;interventionControls();});
 window.addEventListener('pagehide',event=>{if(event.persisted)return;for(const url of reviewObjectUrls.values())URL.revokeObjectURL(url);reviewObjectUrls.clear();});
 load();
})();
