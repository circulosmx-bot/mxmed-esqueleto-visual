/* R43A: session-scoped continuation is received only after a single-use page upload. */
window.startClinicalMultipageCapture = async function(initialToken) {
  'use strict';
  const $=id=>document.getElementById(id), api='/api/clinical/index.php/';
  let token=initialToken, session=null, bearer='', selected=null, busy=false, preview='', urls=[];
  const storageKey='mxmed-mobile-session:'+initialToken;
  try { const saved=JSON.parse(sessionStorage.getItem(storageKey)||'null');if(saved){session=saved.session;bearer=saved.bearer;token='';} } catch(_){}
  $('captureLegacyMetadata').hidden=true;$('captureFile').accept='image/jpeg,image/png,image/webp';
  $('captureChooseFile').textContent='Seleccionar imagen';$('captureSubmit').textContent='Enviar página';
  const review=document.createElement('section');review.setAttribute('aria-label','Páginas del documento');
  const list=document.createElement('div'),more=document.createElement('button'),finish=document.createElement('button');
  more.type=finish.type='button';more.textContent='Agregar otra página';finish.textContent='Finalizar documento';
  more.className='btn-secondary';more.style.margin='12px 0';review.append(list,more,finish);$('captureMsg').before(review);review.hidden=true;
  function message(text,error=false){$('captureMsg').textContent=text;$('captureMsg').className=error?'msg err':'msg ok';}
  function controls(){
    const open=!session||session.status==='OPEN';
    $('captureTakePhoto').disabled=$('captureChooseFile').disabled=busy||!token||!open;
    $('captureSubmit').disabled=busy||!selected||!token||!open;
    more.disabled=busy||!open;finish.disabled=busy||!open||!session?.page_count;
    list.querySelectorAll('button').forEach(n=>n.disabled=busy||!open);
  }
  async function request(path,body,continuation=true){
    const headers={Accept:'application/json'};if(continuation&&bearer)headers['X-Capture-Continuation']=bearer;
    if(body!==undefined&&!(body instanceof FormData))headers['Content-Type']='application/json';
    const response=await fetch(api+path,{method:body===undefined?'GET':'POST',headers,credentials:'omit',body:body===undefined?undefined:body instanceof FormData?body:JSON.stringify(body)});
    const json=await response.json();if(!response.ok||!json.ok)throw new Error(json.error||'CAPTURE_OPERATION_FAILED');return json.data;
  }
  function save(){try{sessionStorage.setItem(storageKey,JSON.stringify({session,bearer}));}catch(_) {}}
  async function render(){
    urls.forEach(URL.revokeObjectURL);urls=[];list.replaceChildren();review.hidden=!session?.pages;
    $('captureClassification').hidden=false;$('captureClassification').textContent='Tipo de documento: '+session.classification.label;
    for(const page of session.pages||[]){
      const row=document.createElement('article');row.style.cssText='display:flex;align-items:center;gap:8px;margin:12px 0;flex-wrap:wrap;border-bottom:1px solid #bdd8e4;padding:8px 0';
      const img=document.createElement('img');img.alt='Página '+page.page_number;img.width=64;img.height=80;img.style.objectFit='contain';
      const text=document.createElement('strong');text.textContent='Página '+page.page_number;
      row.append(img,text);
      const index=session.pages.indexOf(page);
      for(const [label,offset] of [['Subir',-1],['Bajar',1]]){
        const button=document.createElement('button');button.type='button';button.textContent=label;button.className='btn-secondary';button.style.width='auto';
        button.hidden=index+offset<0||index+offset>=session.pages.length||session.status!=='OPEN';
        button.onclick=()=>run(async()=>{const ids=session.pages.map(p=>p.page_uuid);[ids[index],ids[index+offset]]=[ids[index+offset],ids[index]];session=await request('mobile-capture-sessions/'+session.session_uuid+'/reorder',{pages:ids});save();await render();message('Orden actualizado.');});row.append(button);
      }
      const remove=document.createElement('button');remove.type='button';remove.textContent='Quitar';remove.className='btn-secondary';remove.style.width='auto';remove.hidden=session.status!=='OPEN';
      remove.onclick=()=>run(async()=>{session=await request('mobile-capture-sessions/'+session.session_uuid+'/remove',{page_uuid:page.page_uuid});save();await render();message('Página retirada.');});row.append(remove);list.append(row);
      try{const response=await fetch(page.thumbnail_url,{credentials:'omit',headers:{'X-Capture-Continuation':bearer}});if(response.ok){const url=URL.createObjectURL(await response.blob());urls.push(url);img.src=url;}}catch(_){}
    }
    if(session.status==='COMPLETED'){$('captureForm').hidden=true;more.hidden=finish.hidden=true;message('Documento enviado · '+session.page_count+' páginas. Puedes cerrar esta ventana.');}
    else if(session.status!=='OPEN'){$('captureForm').hidden=true;message('La captura ya no está disponible.',true);}
    controls();
  }
  async function run(action){if(busy)return;busy=true;controls();try{await action();}catch(error){message(({
    CAPTURE_TOKEN_USED:'Esta página ya se envió. Abre el enlace nuevo desde la computadora si no aparece aquí.',
    CAPTURE_TOKEN_EXPIRED:'El código venció. Genera un enlace nuevo desde la computadora.',
    CAPTURE_METADATA_IMMUTABLE:'El tipo de documento se elige en la computadora.',
    UPLOAD_TOO_LARGE:'La imagen supera 25 MB. Selecciona otra imagen.'
  })[error.message]||'No se completó la acción. Conserva esta ventana y vuelve a intentar.',true);}finally{busy=false;controls();}}
  function choose(file){
    selected=null;if(preview)URL.revokeObjectURL(preview);preview='';$('capturePreviewWrap').style.display='none';
    if(!file){controls();return;}
    if(!['image/jpeg','image/png','image/webp'].includes(file.type)||file.size>25*1024*1024){message('Selecciona una imagen JPG, PNG o WebP de hasta 25 MB.',true);controls();return;}
    selected=file;preview=URL.createObjectURL(file);$('capturePreview').src=preview;$('capturePreviewWrap').style.display='block';$('captureFileName').textContent=file.name;controls();
  }
  $('captureTakePhoto').onclick=()=>$('captureCamera').click();$('captureChooseFile').onclick=()=>$('captureFile').click();
  $('captureCamera').onchange=()=>choose($('captureCamera').files[0]);$('captureFile').onchange=()=>choose($('captureFile').files[0]);
  $('captureForm').onsubmit=event=>{event.preventDefault();if(!selected)return;run(async()=>{
    message('Enviando y preparando la página…');const data=new FormData();data.append('file',selected);
    session=await request('note-capture-tokens/'+token+'/upload',data,true);bearer=session.continuation;delete session.continuation;token='';choose(null);save();await render();message('Página recibida. Agrega otra página o finaliza el documento.');
  });};
  more.onclick=()=>run(async()=>{const data=await request('mobile-capture-sessions/'+session.session_uuid+'/pages',{});token=data.token;session=data;save();await render();$('captureTitle').textContent='Página '+data.page_number;message('Toma la siguiente foto.');$('captureTakePhoto').focus();});
  finish.onclick=()=>run(async()=>{session=await request('mobile-capture-sessions/'+session.session_uuid+'/finalize',{});token='';save();await render();});
  await run(async()=>{
    if(session&&bearer){session=await request('mobile-capture-sessions/'+session.session_uuid);save();await render();}
    else{const data=await request('note-capture-tokens/'+token+'/mobile-context',undefined,false);session={...data,status:'OPEN'};$('captureTitle').textContent='Página '+data.page_number;$('captureClassification').hidden=false;$('captureClassification').textContent='Tipo de documento: '+data.classification.label;message('Toma una foto legible de la página.');}
  });
};
