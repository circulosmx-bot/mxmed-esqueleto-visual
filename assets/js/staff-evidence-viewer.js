/* Presentation only. The caller supplies an exact, receipt-confirmed canonical READ. */
(() => {
'use strict';
const $=id=>document.getElementById(id),dialog=$('staff-evidence');
if(!dialog)return;
const el=(tag,text,cls)=>{const n=document.createElement(tag);if(text)n.textContent=text;if(cls)n.className=cls;return n;};
const type=d=>({INE:'INE',PASSPORT:'Pasaporte'}[d.kind]||'Documento de identidad — Tipo no especificado');
const label=(page,index)=>({FRONT:'Anverso',BACK:'Reverso',BIOGRAPHIC:'Página biográfica'}[page.side]||(Number.isInteger(page.page_number)&&page.page_number>0?'Página '+page.page_number:'Imagen '+(index+1)));
function clear(){
 for(const img of dialog.querySelectorAll('img'))img.removeAttribute('src');
 $('protected-case-pages').replaceChildren();$('staff-evidence-context').replaceChildren();$('staff-evidence-next').textContent='';
}
function render(context,pages){
 clear();
 const documents=typeof context.set.manifest==='string'?JSON.parse(context.set.manifest):context.set.manifest;
 if(!Array.isArray(documents)||!pages.length||pages.some(p=>!documents.some(d=>d.document_id===p.document_id)))throw new Error('La evidencia no corresponde al conjunto consultado.');
 const header=$('staff-evidence-context');header.append(el('h3',context.name,'evidence-applicant'));
 const category={ADVISOR:'Asesor',MASTER_ADMIN:'Administrador maestro'}[context.category]||'Categoría no especificada';
 const status=context.reviewCurrent?({ACCEPT:'Evidencia aceptada',CORRECTIONS:'Correcciones solicitadas',REJECT:'Evidencia rechazada'}[context.review?.decision]||'Estado de revisión no disponible'):'Pendiente de revisión';
 header.append(el('p',category+' · '+status,'evidence-status'),el('p','Conjunto de evidencia · versión '+context.set.sequence_number+' · datos del caso v'+context.set.case_version,'evidence-version'));
 if(context.review&&!context.reviewCurrent)header.append(el('p','La revisión anterior no acredita este conjunto vigente.','evidence-version'));
 const groups=$('protected-case-pages');
 for(const [index,doc] of documents.entries()){
  const items=pages.filter(p=>p.document_id===doc.document_id);if(!items.length)continue;
  const group=el('section',null,'evidence-document'),heading=el('h3',type(doc));group.append(heading,el('p','Documento '+(index+1)+(doc.document_version?' · versión '+doc.document_version:''),'evidence-version'));
  const grid=el('div',null,'evidence-page-grid'),large=el('section',null,'evidence-enlarged');large.hidden=true;
  const caption=el('h4'),image=el('img'),back=el('button','Volver a las imágenes');back.type='button';large.append(caption,back,image);
  items.forEach((page,i)=>{
   const title=label(page,i),figure=el('figure'),button=el('button',null,'evidence-preview'),img=el('img');button.type='button';button.setAttribute('aria-label','Ampliar '+title+' · '+type(doc));
   img.alt=type(doc)+' · '+title;img.src='data:'+page.mime_type+';base64,'+page.base64;button.append(img);figure.append(button,el('figcaption',title),el('span','Selecciona la imagen para ampliarla.','evidence-version'));
   button.onclick=()=>{caption.textContent=type(doc)+' · '+title;image.alt=img.alt;image.src=img.src;grid.hidden=true;large.hidden=false;back.focus({preventScroll:true});large.scrollIntoView({block:'nearest'});back.onclick=()=>{image.removeAttribute('src');large.hidden=true;grid.hidden=false;button.focus({preventScroll:true});};};grid.append(figure);
  });group.append(grid,large);groups.append(group);
 }
 const eligible=context.actions.filter(a=>['ACCEPT','CORRECTIONS','REJECT','ADMIT'].includes(a));
 const names={ACCEPT:'Aceptar evidencia y concluir revisión',CORRECTIONS:'Solicitar correcciones',REJECT:'Rechazar evidencia',ADMIT:'Aprobar admisión'};
 $('staff-evidence-next').textContent=(eligible.length?'Al cerrar, puedes volver al caso para: '+eligible.map(a=>names[a]).join('; ')+'. ':'Cierra para volver al estado actual del caso. ')+'Consultar documentos no acepta la evidencia ni admite al integrante. Cada acción protegida conserva su verificación independiente.';
}
window.staffEvidenceViewer={render,clear};
dialog.addEventListener('close',clear);
window.addEventListener('pagehide',clear);
})();
