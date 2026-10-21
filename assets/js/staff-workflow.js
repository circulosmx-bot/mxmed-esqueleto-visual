/* Approved four-step preparation; canonical authority and operation receipts remain server-owned. */
(() => {
'use strict';
const $=id=>document.getElementById(id),uid=()=>crypto.randomUUID(),esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const labels={given_names:'Nombre(s)',first_surname:'Primer apellido',second_surname:'Segundo apellido',operation_state:'Estado',operation_city:'Ciudad / municipio'};
const actions={READ:'Consultar evidencia',ACK:'Confirmar revisión de documentos',CORRECTIONS:'Solicitar correcciones',ACCEPT:'Aceptar evidencia y concluir revisión',REJECT:'Rechazar evidencia',ADMIT:'Aprobar admisión'};
let actor=null,caseEpoch=0,mountedCase=null,preparationDirty=false;
let enhanced=false,locations=[],intentionData=null,simulationData=null,pollTimer=null,pollEpoch=0;
let documentHost=null;
let csrf='',director=false,busy=false,wizard=null,step=0,caseData=null,caseView=null,caseInvitation=null,intent=null,recoveryKey='',recovered=null,reviewPending=null;
const name=c=>Object.keys(labels).slice(0,3).map(k=>c.profile?.[k]).filter(Boolean).join(' ');
const say=m=>{$('wf-status').textContent=m;},changed=()=>setTimeout(()=>window.dispatchEvent(new Event('staff-workflow-changed')),0);
async function api(action,input={}){
 // Refresh the existing CSRF token before an explicit action, never retry a mutation.
 if(action!=='bootstrap')await api('bootstrap');
 const controller=new AbortController(),timer=setTimeout(()=>controller.abort(),20000);
 try{const r=await fetch('/api/internal/staff-admission/workflow.php',{signal:controller.signal,method:'POST',credentials:'same-origin',cache:'no-store',headers:{'Content-Type':'application/json','X-CSRF-Token':csrf},body:JSON.stringify({action,input})});const b=await r.json();
 if(r.status===401){window.teamSessionExpired?.();}
 if(!r.ok){const e=new Error(b.error==='synthetic_recipient_required'?'Usa un correo de prueba terminado en @example.test.':r.status===400?'Revisa los datos de la solicitud.':r.status===401?'Tu sesión terminó. Inicia sesión de nuevo mediante Identity.':r.status===403?'Tu cuenta no tiene autorización para esta operación.':r.status===409?'El estado cambió. Consulta la versión actual antes de continuar.':'No se pudo confirmar el resultado. Consulta el estado antes de repetir.');e.status=r.status;throw e;}if(action==='bootstrap'){csrf=b.csrf_token;actor=b.actor;}return b;
 }catch(e){if(e.status)throw e;throw new Error('No pudimos confirmar la respuesta. Consulta el estado.');}finally{clearTimeout(timer);}
}
function fields(prefix,profile){return `<div class="admission-fields">${Object.entries(labels).map(([k,l])=>`<label class="wizard-field">${l}<input id="${prefix}-${k}" maxlength="120" value="${esc(profile[k]||'')}"></label>`).join('')}</div>`;}
async function geography(prefix,values){
 if(!enhanced)return;
 const state=$(prefix+'-operation_state'),city=$(prefix+'-operation_city');
 for(const old of [state,city]){const select=document.createElement('select');select.id=old.id;select.dataset.geoLoading='true';old.replaceWith(select);}
 const a=$(state.id),b=$(city.id);a.disabled=b.disabled=true;
 try{if(!locations.length)locations=(await api('geography')).locations;
 const states=[...new Set(locations.map(r=>r.state_key))];a.replaceChildren(new Option(states.length?'Selecciona estado':'Catálogo no disponible',''));
 states.forEach(v=>a.add(new Option(v,v)));a.value=values.operation_state||'';
 const cities=value=>{b.replaceChildren(new Option('Selecciona ciudad / municipio',''));locations.filter(r=>r.state_key===a.value).forEach(r=>b.add(new Option(r.city_key,r.city_key)));b.value=value||'';b.disabled=busy||!a.value;};
 delete a.dataset.geoLoading;delete b.dataset.geoLoading;cities(values.operation_city);a.disabled=busy||!states.length;a.onchange=()=>{cities('');if(wizard)wizard.dirty=true;};
 }catch(_){a.replaceChildren(new Option('Catálogo no disponible',''));b.replaceChildren(new Option('No se pudo consultar',''));$('onboarding-feedback').textContent='No se pudo cargar el catálogo. Cierra y vuelve a abrir la preparación.';}
}
function profile(prefix){return Object.fromEntries(Object.keys(labels).map(k=>[k,$(prefix+'-'+k).value.trim()]));}
function show(id){const d=$(id);d.showModal();const heading=d.querySelector('h2');if(heading){heading.tabIndex=-1;heading.focus({preventScroll:true});}d.querySelector('.team-dialog-body')?.scrollTo(0,0);}
function controls(){for(const id of ['staff-execute'])$(id).disabled=busy;for(const e of document.querySelectorAll('#edit-preparation select'))e.disabled=busy||e.dataset.geoLoading==='true'||e.id==='edit-operation_city'&&!$('edit-operation_state')?.value;if(!wizard)return;const locked=busy||!!wizard.pending&&wizard.pending.state!=='NONCOMMIT';for(const id of ['save-onboarding','next-onboarding','previous-onboarding'])$(id).disabled=locked;$('onboarding-content').querySelectorAll('input,select').forEach(e=>{e.disabled=locked||e.dataset.geoLoading==='true'||e.tagName==='SELECT'&&!['new-channel','new-image-mode','new-avatar','new-operation_state','new-operation_city'].includes(e.id)||e.id==='new-operation_city'&&!$('new-operation_state')?.value||e.id==='new-channel'&&!!wizard.case_id;});$('check-onboarding').hidden=!wizard.pending;$('check-onboarding').disabled=busy;}
async function work(status,fn){if(busy)return;busy=true;$('staff-case-content').inert=true;controls();try{await fn();}catch(e){$(status).textContent=e.message;if(documentHost&&status==='staff-case-status'){const note=documentHost.querySelector('[data-document-status]');if(note)note.textContent=e.message;}}finally{busy=false;$('staff-case-content').inert=false;if(!$('staff-stepup').open){intent=null;$('staff-reason').value='';}controls();}}
for(const id of ['onboarding','staff-profile','staff-stepup'])$(id).addEventListener('cancel',e=>{e.preventDefault();close(id);});

function close(id){
 if(id==='onboarding'){
  if(!wizard?.pending&&wizard?.dirty&&!confirm('¿Descartar los cambios sin guardar? El borrador guardado se conserva.'))return;
  const pending=!!wizard?.pending;$(id).close();$('add-member').focus();
  if(pending||busy)say('Preparación conservada. Agregar integrante permite consultar el resultado sin reenviar la operación.');else wizard=null;
  return;
 }
 if(id==='staff-profile'){window.staffPersonal?.dismiss();return;}
 if(busy){$(id).close();say('Operación en curso. Consulta el caso antes de iniciar otra acción; no se reenviará al cerrar.');return;}
 if(id==='staff-stepup'){if($('staff-reason').value&&!confirm('¿Descartar el motivo sin enviar?'))return;intent=null;$('staff-reason').value='';}
 $(id).close();
}
$('cancel-onboarding').onclick=()=>close('onboarding');$('close-staff-evidence').onclick=()=>$('staff-evidence').close();$('cancel-staff-profile').onclick=()=>close('staff-profile');$('cancel-staff-stepup').onclick=()=>close('staff-stepup');
function remember(){
 // Only non-secret operation/case identifiers survive a reload. Never profile/email/credentials/tokens.
 try{if(wizard?.pending)sessionStorage.setItem(recoveryKey,JSON.stringify({operation_id:wizard.pending.operation_id,action:wizard.pending.action,case_id:wizard.case_id,image:{mode:wizard.image.mode,avatar:wizard.image.avatar,uploaded:wizard.image.uploaded}}));else sessionStorage.removeItem(recoveryKey);}catch(_){}
}
function start(c=null){
 if(!(wizard?.pending||busy&&wizard)){wizard={profile:c?.profile||{},email:'',channel:c?.channel||'ASSISTED',case_id:c?.case_id||'',version:c?.version||1,operation:uid(),recipient:uid(),invitationOperation:uid(),image:{mode:recovered?.image?.mode||c?.profile?.presentation||'PLACEHOLDER',avatar:recovered?.image?.avatar||(['NEUTRAL','FEMININE','MASCULINE'].includes(c?.profile?.avatar)?c.profile.avatar:'NEUTRAL'),file:null,preview:'',uploaded:recovered?.image?.uploaded||c?.profile?.presentation==='PORTRAIT'},dirty:false,done:false,pending:recovered};recovered=null;step=0;}
 renderWizard();show('onboarding');
 if(c?.profile?.presentation==='PORTRAIT')loadPreparedPortrait(wizard);
 if(wizard.pending)$('onboarding-feedback').textContent='Hay una operación pendiente de comprobar. Consulta su estado; no se reenviará al abrir.';
}
function capture(){if(step===0){wizard.profile=profile('new');wizard.email=$('new-email').value.trim();wizard.channel=$('new-channel').value;}}
function valid(){if(enhanced&&step===0&&$('new-operation_state')?.dataset.geoLoading==='true'){$('onboarding-feedback').textContent='Espera a que esté disponible el catálogo antes de guardar.';return false;}if(enhanced&&!!wizard.profile.operation_state!==!!wizard.profile.operation_city){$('onboarding-feedback').textContent='Selecciona estado y ciudad / municipio, o deja ambos sin seleccionar.';return false;}if(!wizard.profile.given_names||!wizard.profile.first_surname){$('onboarding-feedback').textContent='Completa nombre y primer apellido.';return false;}if(wizard.email&&!/^[^\s@]+@example\.test$/i.test(wizard.email)){$('onboarding-feedback').textContent='En esta demostración usa un correo de prueba terminado en @example.test. No se envía correo externo.';return false;}return true;}
function renderWizard(){
 $('onboarding-title').textContent=`Agregar integrante · paso ${step+1} de 4`;$('onboarding-feedback').textContent='';document.querySelectorAll('#onboarding .wizard-steps li').forEach((e,i)=>{if(i===step)e.setAttribute('aria-current','step');else e.removeAttribute('aria-current');});
 $('previous-onboarding').hidden=step===0||wizard.done;$('save-onboarding').hidden=wizard.done;$('next-onboarding').hidden=wizard.done;$('next-onboarding').textContent=step===3?'Preparar invitación':'Continuar';
 if(step===0)$('onboarding-content').innerHTML=fields('new',wizard.profile)+`<label class="wizard-field">Correo del destinatario<input id="new-email" type="email" maxlength="190" autocomplete="off" aria-describedby="synthetic-email-help" value="${esc(wizard.email)}"></label><p id="synthetic-email-help">Usa @example.test. La invitación se prepara sólo dentro de esta demostración; no se envía correo externo.</p><label class="wizard-field">Vía de preparación<select id="new-channel"><option value="ASSISTED">Asistida por administrador</option><option value="REMOTE_PREAUTHORIZED">Remota preautorizada</option></select></label>`;
 if(step===1)$('onboarding-content').innerHTML='<label class="wizard-field">Categoría interna<select disabled><option>Asesor</option></select></label><p>La incorporación inicia como Asesor. La designación Master requiere su procedimiento independiente.</p>';
 if(step===2)$('onboarding-content').innerHTML='<h3>Permisos iniciales</h3><p>Guarda la preparación para seleccionar facultades pendientes en la solicitud. La admisión no concede permisos automáticamente; después requiere confirmación explícita de cada permiso.</p><p>La persona confirmará sus propios datos y documentos; la revisión de identidad es un paso posterior independiente.</p>';
 if(step===3)$('onboarding-content').innerHTML=`<dl class="onboarding-summary"><dt>Persona</dt><dd>${esc(name({profile:wizard.profile}))}</dd><dt>Destino de la invitación</dt><dd>${esc(wizard.email||'Pendiente de completar')}</dd><dt>Ciudad / estado</dt><dd>${esc([wizard.profile.operation_city,wizard.profile.operation_state].filter(Boolean).join(', ')||'Sin información')}</dd><dt>Categoría</dt><dd>Asesor</dd><dt>Permisos</dt><dd>Sin concesiones automáticas</dd><dt>Vía</dt><dd>${wizard.channel==='ASSISTED'?'Asistida':'Remota preautorizada'}</dd></dl><p>Se prepara una solicitud y su invitación. No se activa acceso al equipo.</p>`;
 if(step===3)$('onboarding-content').insertAdjacentHTML('beforeend',imageSummary());
 if(wizard.done){$('onboarding-content').insertAdjacentHTML('beforeend','<button id="open-prepared-case" type="button">Ver solicitud preparada</button>');$('open-prepared-case').onclick=()=>{const id=wizard.case_id;close('onboarding');openCase(id);};}
 if(step===0){geography('new',wizard.profile);renderImage();$('new-channel').value=wizard.channel;$('onboarding-content').oninput=()=>{wizard.dirty=true;};}controls();
}

const portraitPurpose='INTERNAL_PRESENTATION_PORTRAIT';
function imagePreview(){const i=wizard.image;return i.mode==='AVATAR'?window.staffPersonal.avatar(i.avatar):i.mode==='PORTRAIT'&&i.preview?`<img class="personal-portrait" alt="Vista previa de fotografía seleccionada" src="${i.preview}">`:'<p>Sin imagen seleccionada.</p>';}
async function loadPreparedPortrait(target){try{const m=await api('media_status',{case_id:target.case_id,purpose:portraitPurpose});if(!m.asset_id)return;const r=await api('portrait',{case_id:target.case_id,purpose:portraitPurpose,expected_case_version:m.case_version,asset_id:m.asset_id,asset_version:m.version});if(wizard!==target||target.image.file)return;target.image.preview=`data:${r.mime_type};base64,${r.base64}`;if($('new-image-preview'))$('new-image-preview').innerHTML=imagePreview();}catch(_){if(wizard===target)$('onboarding-feedback').textContent='La fotografía guardada no está disponible para consulta en este estado.';}}
function imageSummary(){return '<h3>Imagen de presentación</h3>'+imagePreview()+'<p>La imagen de presentación no es evidencia de identidad. Se guarda en la solicitud; no activa acceso al equipo.</p>';}
function renderImage(){
 const i=wizard.image;$('onboarding-content').insertAdjacentHTML('beforeend',`<h3>Imagen de presentación</h3><label class="wizard-field">Presentación<select id="new-image-mode"><option value="PLACEHOLDER">Sin imagen</option><option value="AVATAR">Elegir avatar</option><option value="PORTRAIT">Subir fotografía</option></select></label><label class="wizard-field" id="new-avatar-area">Avatar<select id="new-avatar"><option value="NEUTRAL">Neutral</option><option value="FEMININE">Femenino</option><option value="MASCULINE">Masculino</option></select></label><label class="wizard-field" id="new-photo-area">Fotografía privada<input id="new-photo" type="file" accept="image/png,image/jpeg"></label><div id="new-image-preview"></div><p id="new-image-help">Opcional e independiente del género. PNG o JPEG ficticio, máximo 64 KiB, 512 px por lado y 65,536 píxeles. La selección permanece en esta ventana hasta guardar el caso; no es un documento de identidad.</p>`);
 $('new-image-mode').value=i.mode;$('new-avatar').value=i.avatar;
 const refresh=()=>{$('new-avatar-area').hidden=i.mode!=='AVATAR';$('new-photo-area').hidden=i.mode!=='PORTRAIT';$('new-image-preview').innerHTML=imagePreview();};refresh();
 $('new-image-mode').onchange=()=>{i.mode=$('new-image-mode').value;wizard.dirty=true;refresh();};
 $('new-avatar').onchange=()=>{i.avatar=$('new-avatar').value;wizard.dirty=true;refresh();};
 $('new-photo').onchange=async()=>{const f=$('new-photo').files[0];if(!f)return;try{
 if(!['image/png','image/jpeg'].includes(f.type)||f.size>65536)throw new Error('Selecciona PNG o JPEG de hasta 64 KiB.');
 const url=await new Promise((resolve,reject)=>{const r=new FileReader();r.onload=()=>resolve(r.result);r.onerror=reject;r.readAsDataURL(f);});
 const img=new Image();img.src=url;await img.decode();if(img.width>512||img.height>512||img.width*img.height>65536)throw new Error('La fotografía supera las dimensiones permitidas.');
 i.file=f;i.preview=url;i.uploaded=false;wizard.dirty=true;refresh();$('onboarding-feedback').textContent='Fotografía seleccionada, aún no guardada.';
 }catch(e){$('new-photo').value='';$('onboarding-feedback').textContent=e.message||'No se pudo leer la fotografía.';}};
}
async function reconcileImage(){
 const p=wizard.pending,r=await api('media_result',{case_id:wizard.case_id||p.case_id,purpose:portraitPurpose,operation_id:p.operation_id});
 if(r.operation_id!==p.operation_id||r.case_id!==(wizard.case_id||p.case_id))throw new Error('El resultado no corresponde a esta fotografía.');
 if(r.committed&&r.reconciliation==='COMMITTED_AVAILABLE'){wizard.case_id=r.case_id;wizard.image.mode='PORTRAIT';wizard.image.uploaded=true;wizard.pending=null;remember();$('onboarding-feedback').textContent='Fotografía privada confirmada. Continúa para guardar su presentación.';return true;}
 if(!r.committed&&r.reconciliation==='NO_COMMITTED_OPERATION_OR_FILE'){p.state='NONCOMMIT';$('onboarding-feedback').textContent='No se guardó la fotografía. Puedes volver a seleccionarla; se conserva el identificador.';return false;}
 throw new Error('La fotografía requiere conciliación. No repitas la carga.');
}
async function persistImage(){
 const i=wizard.image;if(wizard.pending&&wizard.pending.state!=='NONCOMMIT')return false;
 if(i.mode==='PORTRAIT'&&!i.uploaded){
  if(!i.file)throw new Error('Selecciona una fotografía antes de guardar esta presentación.');
  const c=await api('case',{case_id:wizard.case_id}),m=await api('media_status',{case_id:wizard.case_id,purpose:portraitPurpose});
  wizard.pending={action:'upload',operation_id:wizard.pending?.operation_id||uid(),case_id:wizard.case_id,state:'PENDING'};remember();controls();
  try{const r=await api('upload',{metadata:{operation_id:wizard.pending.operation_id,case_id:wizard.case_id,expected_case_version:c.version,expected_asset_version:m.version,purpose:portraitPurpose,declared_mime:i.file.type},base64:i.preview.split(',')[1]});
   if(!r.committed||r.operation_id!==wizard.pending.operation_id||r.case_id!==wizard.case_id)throw new Error('Carga pendiente de confirmar.');
   i.uploaded=true;wizard.pending=null;remember();
  }catch(e){wizard.pending.state='UNKNOWN';throw e;}
 }
 const c=await api('case',{case_id:wizard.case_id}),patch={presentation:i.mode,avatar:i.mode==='AVATAR'?i.avatar:(c.profile.avatar||'NONE')};
 if(Object.entries(patch).some(([k,v])=>c.profile[k]!==v)){const r=await mutate('update',{case_id:c.case_id,expected_version:c.version,operation_id:uid(),changes:patch});draftResult(r);}else wizard.version=c.version;
 return true;
}

function draftResult(r){wizard.case_id=r.case_id;wizard.version=r.version;wizard.operation=uid();wizard.dirty=false;wizard.pending=null;remember();changed();}
function invitationResult(r){
 wizard.done=true;wizard.dirty=false;wizard.pending=null;remember();step=3;renderWizard();
 const state=r.invitation_state||r.state,expired=state==='ACTIVE'&&r.expires_at&&Date.parse(r.expires_at.replace(' ','T')+'Z')<=Date.now();
 $('onboarding-feedback').textContent=state!=='ACTIVE'||expired?'La solicitud ya tiene una invitación '+(expired?'vencida':({CONSUMED:'aceptada',REVOKED:'revocada',REPLACED:'reemplazada',EXPIRED:'vencida'}[state]||'registrada'))+'. Consulta el caso; no se creó una invitación duplicada.':r.delivery_state==='DELIVERY_CONFIRMED'?'Invitación preparada en el entorno sintético. Pendiente de vinculación y confirmación personal; sin correo externo ni acceso al equipo.':'La invitación ya está registrada, pero su preparación de entrega no está confirmada. No prepares otra invitación. Consulta la solicitud.';
 say('Solicitud registrada. Disponible en Pendientes.');changed();
}
async function reconcile(){
 const pending=wizard.pending;if(!pending)return true;
 if(pending.action==='upload')return reconcileImage();
 const result=await api(pending.action==='issue'?'invitation_result':'case_result',{operation_id:pending.operation_id});
 if(result.operation_id!==pending.operation_id||!(result.committed===true||result.committed===false&&result.outcome==='NOT_RECORDED'))throw new Error('La consulta no confirmó un resultado válido. Puedes volver a consultar el estado.');
 if(result.committed){if(result.command!==pending.action||!/^adm_[a-f0-9]{32}$/.test(result.case_id)||!Number.isInteger(result.version))throw new Error('El recibo no corresponde a esta preparación. Consulta nuevamente.');if(pending.action==='issue'){wizard.case_id=result.case_id;await api('case',{case_id:result.case_id});invitationResult(result);}else{const c=await api('case',{case_id:result.case_id});draftResult(result);wizard.profile=c.profile;wizard.version=c.version;wizard.channel=c.channel;renderWizard();$('onboarding-feedback').textContent='Borrador guardado confirmado. Puedes continuar; no se volvió a crear.';}return true;}
 if(pending.case_id||wizard.case_id){wizard.case_id=pending.case_id||wizard.case_id;const c=await api('case',{case_id:wizard.case_id});wizard.version=c.version;
  if(pending.action==='issue'){const state=await api('invitation_status',{case_id:wizard.case_id});if(state.invitation){invitationResult(state.invitation);return true;}}
 }
 pending.state='NONCOMMIT';if(pending.action==='issue')wizard.invitationOperation=pending.operation_id;else wizard.operation=pending.operation_id;
 $('onboarding-feedback').textContent='La consulta no encontró una operación confirmada. Puedes corregir y volver a intentar explícitamente; se conserva su identificador para impedir duplicados.';controls();return false;
}
async function mutate(action,input){
 wizard.pending={action,operation_id:input.operation_id,case_id:input.case_id||'',input,state:'PENDING'};remember();controls();
 try{const result=await api(action,input);if(result.committed!==true||result.operation_id!==input.operation_id||result.command!==action||!/^adm_[a-f0-9]{32}$/.test(result.case_id)||!Number.isInteger(result.version))throw new Error('La respuesta no acredita el resultado de esta operación.');return result;}catch(e){wizard.pending.state='UNKNOWN';$('onboarding-feedback').textContent=e.message+' Consulta el estado para continuar con seguridad.';throw new Error($('onboarding-feedback').textContent);}
}
async function saveDraft(){capture();if(!valid())return false;if(wizard.pending){const mediaPending=wizard.pending.action==='upload';await reconcile();if(wizard.pending?.state!=='NONCOMMIT')return false;if(mediaPending)return true;}
 if(wizard.case_id&&!wizard.dirty&&(!wizard.pending||wizard.pending.action==='issue'))return true;
 const input={operation_id:wizard.operation,...Object.fromEntries(Object.keys(labels).map(k=>[k,wizard.profile[k]||'']))};
 const result=wizard.case_id?await mutate('update',{...input,case_id:wizard.case_id,expected_version:wizard.version}):await mutate('prepare',{...input,recipient_reference:wizard.recipient,channel:wizard.channel,proposed_category:'ADVISOR'});
 draftResult(result);return true;
}
$('check-onboarding').onclick=()=>work('onboarding-feedback',reconcile);
$('previous-onboarding').onclick=()=>{if(busy)return;capture();step--;renderWizard();};
$('save-onboarding').onclick=()=>work('onboarding-feedback',async()=>{if(await saveDraft()&&await persistImage())$('onboarding-feedback').textContent='Borrador guardado. Puedes continuar ahora o retomar el caso desde Pendientes.';});
$('next-onboarding').onclick=()=>work('onboarding-feedback',async()=>{
 capture();if(!valid())return;if(step<3){step++;renderWizard();return;}
 if(!wizard.email){$('onboarding-feedback').textContent='Completa el correo del destinatario en Persona.';return;}
 if(wizard.pending){await reconcile();if(wizard.done||wizard.pending?.state!=='NONCOMMIT')return;}
 if(wizard.case_id){const existing=await api('invitation_status',{case_id:wizard.case_id});if(existing.invitation){invitationResult(existing.invitation);return;}}
 if((!wizard.case_id||wizard.dirty)&&!await saveDraft())return;
 if(!await persistImage())return;
 const state=await api('invitation_status',{case_id:wizard.case_id});
 if(state.invitation){invitationResult(state.invitation);return;}
 if(state.account_bound||state.case_state!=='PREPARING'||state.version!==wizard.version)throw new Error('La solicitud cambió o ya está vinculada. Consulta su estado desde Pendientes.');
 const result=await mutate('issue',{operation_id:wizard.invitationOperation,case_id:wizard.case_id,expected_version:wizard.version,recipient_email:wizard.email});invitationResult(result);
});
const futureAreas=['Perfil','Acreditación','Consultorios','Servicios y atención','Agenda','Operadores y colaboradores','Plan y servicios contratados','Facturación y pagos','Opiniones','SEO','Soporte de cuenta'];
const facultyCopy={
 media_review_read:['Consultar imágenes pendientes','Lectura de imágenes y solicitudes dentro del perfil autorizado. No permite aprobar ni modificar.'],
 media_review_approve:['Aprobar una imagen','Decisión sobre una imagen revisada: publica el derivado permitido. No publica el perfil ni cambia su plan.'],
 media_review_source_download:['Descargar imagen original','Lectura y descarga privada de un original autorizado, con registro de auditoría.'],
 media_review_corrected_upload:['Subir imagen corregida','Modificación: guarda una versión corregida y prepara su revisión. No la aprueba automáticamente.'],
 media_review_improve:['Proponer mejora de logotipo','Modificación limitada a propuestas privadas del logotipo seleccionado; aprobación independiente.'],
 media_review_request_replacement:['Solicitar otra imagen','Decisión limitada a pedir reemplazo de la imagen seleccionada; conserva su historial.'],
 physician_pending_summary_read:['Consultar conteos de pendientes','Sólo lectura de conteos para médicos con seguimiento ya autorizado. No abre documentos ni amplía el acceso.'],
 physician_request_read:['Consultar solicitudes de perfil','Lectura de reclamos y solicitudes de perfil nuevo. No abre evidencia protegida ni permite resolver.'],
 physician_request_evidence_read:['Consultar documentos de una solicitud','Lectura privada del expediente exacto; requiere también consultar solicitudes. No permite decidir.'],
 physician_claim_resolve:['Resolver reclamo de perfil gratuito','Decisión sobre un perfil gratuito sin propietario. Requiere acceso a solicitudes, revisión y contraseña/MFA independientes; no transfiere propiedad.'],
 physician_new_profile_resolve:['Resolver solicitud de perfil nuevo','Decisión sobre la solicitud exacta, con contraseña/MFA. La aprobación crea sólo el perfil permitido; no lo publica ni activa servicios.']
};
function detachCase(){
 documentHost=null;window.staffEvidenceViewer?.clear();clearTimeout(pollTimer);pollEpoch++;caseEpoch++;mountedCase=null;preparationDirty=false;
 $('staff-case-parking').append($('staff-case'));
}
async function loadCase(id){
 clearTimeout(pollTimer);pollEpoch++;const epoch=++caseEpoch;
 const data=await api('case',{case_id:id});
 if(data.state==='REVOKED'){if(epoch!==caseEpoch||mountedCase!==id)return;caseData=data;preparationDirty=false;$('detail-title').textContent=name(data);document.querySelector('#advisor-detail .detail-header .status').textContent='Incorporación cancelada';$('staff-case-content').innerHTML='<p>Incorporación pendiente cancelada. La invitación ya no se puede aceptar; la cuenta y el historial se conservan.</p>';return;}
 const view=await api('view',{case_id:id});
 if(documentHost){if(epoch!==caseEpoch||mountedCase!==id)return;caseData=data;caseView=view;preparationDirty=false;renderDocumentation();return;}
 const invitation=director?(await api('invitation_status',{case_id:id})).invitation:null;
 const intentions=enhanced&&director?await api('intentions',{case_id:id}):null;
 const simulation=enhanced&&director?await api('simulation_status',{case_id:id}):null;
 if(epoch!==caseEpoch||mountedCase!==id)return;
 caseData=data;caseView=view;caseInvitation=invitation;intentionData=intentions;simulationData=simulation;preparationDirty=false;if(documentHost)renderDocumentation();else renderCase();
 if(['QUEUED','RUNNING'].includes(simulationData?.state))scheduleProgress(id,pollEpoch);
}
function invitationLabel(){
 if(!caseInvitation)return 'Sin invitación registrada';
 if(caseData.state==='REVOKED')return 'Invitación invalidada por cancelación';
 if(caseInvitation.state==='CONSUMED')return 'Invitación aceptada';
 if(caseInvitation.state!=='ACTIVE')return {REVOKED:'Invitación revocada',REPLACED:'Invitación reemplazada',EXPIRED:'Invitación vencida'}[caseInvitation.state]||'Consultar estado de invitación';
 if(caseInvitation.case_version!==undefined&&Number(caseInvitation.case_version)!==caseData.version)return 'La preparación cambió; requiere una nueva invitación autorizada';
 if(!caseInvitation.usable)return caseData.state==='PREPARING'&&!caseData.account_bound?'Invitación vencida — requiere una nueva emisión autorizada':'Invitación no utilizable; consulta su estado';
 return 'Invitación registrada — Pendiente de aceptación';
}
function invitationDetails(){
 if(!caseInvitation)return '<p>No se ha emitido una invitación.</p>';
 const i=caseInvitation,date=v=>{if(!v)return 'Sin fecha registrada';const d=new Date(v.replace(' ','T')+'Z');return Number.isFinite(d.getTime())?new Intl.DateTimeFormat('es-MX',{dateStyle:'long',timeStyle:'short',timeZone:'America/Mexico_City'}).format(d)+' (Ciudad de México)':'Fecha no disponible';};
 const state=i.state==='CONSUMED'?'ACEPTADA':i.state==='REVOKED'?'REVOCADA':i.state==='REPLACED'?'REEMPLAZADA':i.state==='EXPIRED'||i.expired?'VENCIDA':'PREPARADA / REGISTRADA';
 return '<dl class="onboarding-summary"><dt>Estado</dt><dd>'+state+'</dd><dt>Preparación / emisión</dt><dd>'+esc(date(i.issued_at))+'</dd><dt>Entrega</dt><dd>'+esc(i.delivery_state==='DELIVERY_CONFIRMED'?'Preparación local sintética confirmada'+(i.delivered_at?': '+date(i.delivered_at):'; sin fecha registrada')+'. No equivale a envío a la persona.':'Sin entrega confirmada.')+'</dd><dt>Vencimiento del enlace</dt><dd>'+esc(date(i.expires_at))+(i.state==='CONSUMED'?'; ya aceptado, no cambia su estado.':'')+'</dd><dt>Aceptación autenticada</dt><dd>'+esc(i.state==='CONSUMED'&&i.consumed_account_id?'Confirmada por la cuenta destinataria: '+date(i.consumed_at):'Pendiente; no hay aceptación registrada.')+'</dd></dl><p>Sin correo ni notificación externa en esta prueba.</p><small>Referencia técnica: '+esc(i.invitation_id)+'</small>';
}
function renderIntentions(){
 if(!intentionData)return;
 const data=intentionData,id=caseData.case_id,version=caseData.version;
 $('staff-case-content').insertAdjacentHTML('beforeend',`<details class="admission-section" id="prepared-faculties"><summary>Configurar facultades previstas</summary><h3>FACULTADES DISPONIBLES</h3><p>Marca facultades previstas. Guardarlas no concede acceso. ${data.admitted?'La admisión está registrada; confirma cada permiso de forma independiente.':'El solicitante todavía no puede utilizarlas.'}</p><p>Configuración v${data.version}. ${data.prepared_case_version&&data.prepared_case_version!==version?'Los datos personales cambiaron: revisa y guarda de nuevo antes de confirmar permisos.':''}</p><div id="pending-grants">${data.catalog.map(c=>{const copy=facultyCopy[c.key]||[c.label,c.description];return `<label class="capability-row"><input type="checkbox" data-intention="${esc(c.key)}" ${data.capabilities.includes(c.key)?'checked':''}><span>${esc(copy[0])} · ${data.effective_capabilities?.includes(c.key)?'Permiso activo':'Facultad prevista'}<small>${esc(copy[1])}</small></span></label>`;}).join('')}</div><button id="save-intentions" type="button">Guardar facultades previstas</button><h3>FUNCIONES PENDIENTES DE INTEGRACIÓN</h3><p>Estas once áreas todavía no están disponibles para el Asesor desde su panel y no se pueden seleccionar aquí: ${futureAreas.map(esc).join(', ')}.</p></details>`);
 $('save-intentions').onclick=()=>work('staff-case-status',async()=>{const capabilities=Array.from(document.querySelectorAll('[data-intention]:checked')).map(e=>e.dataset.intention);await api('prepare_intentions',{case_id:id,expected_case_version:version,expected_version:data.version,capabilities,operation_id:uid()});await loadCase(id);$('prepared-faculties').open=true;$('staff-case-status').textContent='Facultades previstas guardadas. No se concedió acceso.';});
 if(data.admitted&&data.prepared_case_version===version){
  const box=document.createElement('div');box.className='member-actions';for(const cap of data.capabilities){if(data.effective_capabilities?.includes(cap)||data.confirmed_capabilities?.includes(cap))continue;const c=data.catalog.find(c=>c.key===cap);if(!c)continue;const button=document.createElement('button');button.type='button';button.textContent='Confirmar permiso: '+(facultyCopy[cap]?.[0]||c.label);button.dataset.activateIntention=cap;button.onclick=()=>{if(confirm('¿Activar este permiso para la cuenta ya admitida? '+(facultyCopy[cap]?.[0]||c.label)))work('staff-case-status',async()=>{await api('activate_intention',{case_id:id,expected_case_version:version,expected_version:data.version,capability:cap});await loadCase(id);$('prepared-faculties').open=true;$('staff-case-status').textContent='Permiso activo confirmado. Consulta Acceso y funciones.';changed();});};box.append(button);}$('prepared-faculties').append(box);
 }
}
const simulationStages=[['requested','Simulación solicitada'],['identity','Cuenta Identity del solicitante preparada'],['accepted','Invitación aceptada'],['confirmed','Información del solicitante confirmada'],['evidence','Evidencia sintética presentada'],['review','Lista para revisión del Director']];
function paintProgress(data){
 const box=$('simulation-progress');if(!box)return;
 box.innerHTML='<p role="status" id="simulation-message">'+esc(data.message||'No se ha solicitado una simulación.')+'</p><ol>'+simulationStages.map(([key,label])=>'<li data-stage="'+key+'" data-complete="'+(data.stages?.includes(key)?'true':'false')+'">'+(data.stages?.includes(key)?'✓ ':'Pendiente: ')+esc(label)+'</li>').join('')+'</ol>';
 box.dataset.state=data.state;
 if(data.operation_id)box.insertAdjacentHTML('beforeend','<small>Operación: '+esc(data.operation_id)+'</small>');
}
function scheduleProgress(id,epoch){clearTimeout(pollTimer);pollTimer=setTimeout(()=>pollProgress(id,epoch),1500);}
async function pollProgress(id,epoch){
 if(epoch!==pollEpoch||mountedCase!==id||caseData.case_id!==id)return;
 if(document.hidden||busy||preparationDirty||$('staff-stepup').open){scheduleProgress(id,epoch);return;}
 try{const r=await api('simulation_status',{case_id:id});if(epoch!==pollEpoch||mountedCase!==id)return;simulationData=r;paintProgress(r);
 if(['QUEUED','RUNNING'].includes(r.state)){scheduleProgress(id,epoch);return;}
 await work('staff-case-status',async()=>{await loadCase(id);$('staff-case-status').textContent=r.message||'Estado consultado.';});
 }catch(e){if(epoch===pollEpoch&&$('simulation-message'))$('simulation-message').textContent=e.message+' Usa Consultar progreso; no se solicitó otra simulación.';}
}
function renderCase(){
 const id=caseData.case_id,version=caseData.version,available=caseView.available_actions;
 const primary=caseView.admitted?null:available.includes('ADMIT')?'ADMIT':available.includes('ACCEPT')?'ACCEPT':available.includes('READ')?'READ':null;
 const headline=caseView.review_current&&caseView.review?.decision==='CORRECTIONS'?'Correcciones solicitadas — Pendiente de nueva entrega':caseView.admitted?'Admisión registrada':primary==='ADMIT'?'Evidencia aceptada — Aprobar admisión':primary?(simulationData?.state==='REVIEW_REQUIRED'?'Simulación completada — Lista para revisión del Director':'Lista para revisión del Director'):'Pendiente de completar incorporación';
 const remaining=!caseData.account_bound?'La persona debe aceptar su invitación con una cuenta Identity y confirmar sus datos.':caseData.state!=='PERSON_CONFIRMED'?'La persona debe confirmar la versión vigente de sus datos.':!caseView.sets.length?'La persona debe presentar su evidencia de identidad.':'La evidencia o revisión debe corresponder a los datos vigentes.';
 $('staff-case-title').textContent=name(caseData)||'Solicitud de incorporación';
 $('detail-title').textContent=name(caseData)||'Solicitud de incorporación';
 document.querySelector('#advisor-detail .detail-header .status').textContent=caseView.admitted?'Admisión registrada':caseData.state==='REVOKED'?'Incorporación cancelada':'Pendiente de admisión';
 $('staff-case-content').innerHTML=`<section aria-label="Estado de incorporación"><h3 id="admission-next-state">${esc(headline)}</h3>${!primary&&!caseView.admitted?'<p>'+esc(remaining)+'</p>':''}<div id="admission-next-action"></div><div id="simulation-progress" aria-live="polite"></div><dl class="onboarding-summary"><dt>Categoría prevista</dt><dd>${esc(caseData.proposed_category==='ADVISOR'?'Asesor':caseData.proposed_category)}</dd><dt>Invitación</dt><dd id="invitation-state">${esc(invitationLabel())}</dd><dt>Cuenta Identity</dt><dd>${caseData.account_bound?'Vinculada':'Sin vincular'}</dd><dt>Confirmación personal</dt><dd>${caseData.state==='PERSON_CONFIRMED'?'Datos vigentes confirmados':'Pendiente'}</dd><dt>Evidencia de identidad</dt><dd>${caseView.sets.length?'Presentada; '+(caseView.review_current?'revisión vigente':'pendiente de revisión vigente'):'No presentada'}</dd><dt>Examen de documentos</dt><dd id="document-ack-status">${caseView.document_ack_current?'Documentos revisados':'Sin confirmación del conjunto vigente'}</dd><dt>Resultado de revisión</dt><dd>${esc(caseView.review?({ACCEPT:'Aceptada',REJECT:'Rechazada',CORRECTIONS:'Correcciones solicitadas'}[caseView.review.decision]||'Registrada'):'Sin revisión')}${caseView.review&&!caseView.review_current?' (antecedente; datos actuales pendientes)':''}</dd><dt>Facultades previstas</dt><dd>${intentionData?.capabilities.length||0}; no equivalen a permisos activos</dd><dt>Permisos activos</dt><dd>${intentionData?.effective_capabilities.length||0}</dd><dt>Admisión final</dt><dd>${caseView.admitted?'Registrada':'Pendiente; requiere aprobación independiente'}</dd></dl></section>`;
 if(primary){const label=primary==='READ'?'Revisar incorporación':actions[primary];$('admission-next-action').innerHTML='<button class="primary" type="button" data-review-action="'+primary+'">'+label+'</button><p>La operación se autoriza con tu sesión vigente de Director.</p>';}
 else if(!caseView.admitted&&enhanced&&simulationData?.state==='NOT_REQUESTED'&&!caseData.account_bound&&caseInvitation?.usable){
  $('admission-next-action').innerHTML='<button class="primary" type="button" id="simulate-onboarding">Simular incorporación</button><p>Usa una cuenta y evidencia ficticias. Después deberás revisar y aprobar la admisión.</p>';
  $('simulate-onboarding').onclick=()=>{paintProgress({state:'REQUESTING',message:'Solicitando simulación… No cierres hasta recibir el estado.',stages:[]});work('staff-case-status',async()=>{try{simulationData=await api('simulate',{case_id:id});await loadCase(id);}catch(e){$('simulate-onboarding')?.setAttribute('disabled','');throw e;}});};
 }
 if(enhanced&&!caseView.admitted){
  if(simulationData?.state!=='NOT_REQUESTED')paintProgress(simulationData);
  const check=document.createElement('button');check.type='button';check.id='check-simulation';check.textContent='Consultar progreso';check.onclick=()=>work('staff-case-status',async()=>{await loadCase(id);$('staff-case-status').textContent=simulationData.message||'No hay una simulación solicitada.';});$('simulation-progress').after(check);
 }
 if(caseView.review){const review=caseView.review;$('staff-case-content').insertAdjacentHTML('beforeend','<section id="correction-history"><h3>'+esc(review.decision==='CORRECTIONS'?'Correcciones solicitadas':'Antecedente de revisión')+'</h3><p>'+esc(review.reason)+'</p><p>'+(caseView.review_current?'Corresponde al conjunto vigente.':'La nueva entrega requiere revisión independiente; se conserva el antecedente.')+'</p><small>Cuenta revisora: '+esc(review.reviewer_account_id)+' · Auditoría: '+esc(review.audit_event_id)+'</small></section>');}
 renderIntentions();
 $('staff-case-content').insertAdjacentHTML('beforeend','<details class="admission-section" open><summary>DOCUMENTACIÓN</summary><div id="case-documentation"></div></details>');renderDocumentation($('case-documentation'));
 $('staff-case-content').insertAdjacentHTML('beforeend',`<details class="admission-section" id="invitation-details"><summary>Consultar invitación</summary><p>${esc(invitationLabel())}</p>${invitationDetails()}</details>`);
 if(!caseData.account_bound&&!caseView.admitted){
  $('staff-case-content').insertAdjacentHTML('beforeend','<details class="admission-section" id="edit-preparation"><summary>Editar preparación</summary><p>Guarda sólo datos. No crea ni reenvía invitaciones. Si ya existe una, el cambio de versión exige una nueva emisión autorizada por separado.</p>'+fields('edit',caseData.profile)+'<button type="button" id="save-preparation-edit">Guardar datos preparados</button></details>');geography('edit',caseData.profile);
  $('save-preparation-edit').onclick=()=>work('staff-case-status',async()=>{if($('edit-operation_state').dataset.geoLoading==='true')throw new Error('Espera a que cargue el catálogo.');await api('update',{case_id:id,expected_version:version,operation_id:uid(),changes:profile('edit')});await loadCase(id);$('staff-case-status').textContent='Datos guardados. No se creó ni reenvió una invitación.';changed();});
  if(!caseInvitation){const button=document.createElement('button');button.type='button';button.textContent='Preparar invitación';button.onclick=()=>{const c=caseData;start(c);};$('admission-next-action').append(button);}
  else if(!caseInvitation.usable&&['ACTIVE','EXPIRED','REVOKED'].includes(caseInvitation.state)){
   $('invitation-details').insertAdjacentHTML('beforeend','<label class="wizard-field">Correo de prueba del destinatario<input id="reissue-email" type="email" autocomplete="off"></label><button type="button" id="reissue-invitation">Emitir nueva invitación</button><p>Reemplaza la anterior con autorización explícita; no cambia la cuenta ni activa acceso.</p>');
   $('reissue-invitation').onclick=()=>{if(confirm('¿Reemplazar la invitación anterior para el destinatario indicado? El enlace anterior dejará de servir.'))work('staff-case-status',async()=>{await api('reissue',{case_id:id,expected_version:version,operation_id:uid(),invitation_id:caseInvitation.invitation_id,recipient_email:$('reissue-email').value.trim()});await loadCase(id);$('staff-case-status').textContent='Nueva invitación registrada; pendiente de aceptación.';});};
  }
 }
 const secondary=available.filter(key=>key!==primary&&key!=='ADMIT'&&key!=='ACK');
 if(secondary.length){$('staff-case-content').insertAdjacentHTML('beforeend','<details class="admission-section"><summary>Otras acciones de revisión disponibles</summary><div class="member-actions">'+secondary.map(key=>'<button type="button" data-review-action="'+key+'">'+actions[key]+'</button>').join('')+'</div></details>');}
 if(!caseView.admitted){$('staff-case-content').insertAdjacentHTML('beforeend','<details class="admission-section"><summary>Otras opciones</summary><button id="revoke-preparation" type="button">Cancelar incorporación</button><p>Se cancelará esta incorporación sin eliminar los datos y antecedentes registrados de la persona.</p></details>');$('revoke-preparation').onclick=()=>{if(confirm('¿Cancelar esta incorporación pendiente? Su invitación ya no podrá aceptarse. Se conservan la cuenta Identity, los documentos y el historial. No se da de baja a personal activo.'))work('staff-case-status',async()=>{await api('revoke',{case_id:id,expected_version:version,operation_id:uid()});clearTimeout(pollTimer);pollEpoch++;changed();say('Incorporación pendiente cancelada; invitación inutilizable e historial conservado.');});};}
 $('staff-case-content').querySelectorAll('[data-review-action]').forEach(button=>button.onclick=()=>begin(button.dataset.reviewAction));
}
function documentDate(value){if(!value)return 'Sin fecha registrada';const d=new Date(value.replace(' ','T')+'Z');return Number.isFinite(d.getTime())?d.toLocaleString('es-MX',{timeZone:'America/Mexico_City'})+' (Ciudad de México)':'Sin fecha registrada';}
function renderDocumentation(host=documentHost){
 if(!host)return;
 const sets=caseView.sets,latest=sets[0];
 const card=(set,historical)=>{
  const docs=JSON.parse(set.manifest),r=set.review,a=set.acknowledgement;
  return '<article data-document-set="'+esc(set.set_id)+'"><h4>Conjunto '+esc(set.sequence_number)+(historical?' · Histórico':' · Última entrega')+'</h4><p>Presentado: '+esc(documentDate(set.created_at))+'</p><ul>'+docs.map(d=>'<li>'+esc(d.kind==='INE'?'INE':'Pasaporte')+' · versión '+esc(d.document_version)+' · '+d.pages.map(p=>esc({FRONT:'Anverso',BACK:'Reverso',BIOGRAPHIC:'Página biográfica'}[p.side])).join(', ')+'<br>Motivo registrado: '+esc(d.justification)+'</li>').join('')+'</ul><p>'+esc(r?({ACCEPT:'Evidencia aceptada',REJECT:'Evidencia rechazada',CORRECTIONS:'Correcciones solicitadas'}[r.decision]):'Pendiente de revisión')+(r?' · Cuenta revisora: '+esc(r.reviewer_account_id)+' · '+esc(documentDate(r.created_at)):'')+'</p>'+(a?'<p>Documentos revisados · '+esc(a.reviewer_account_id)+' · '+esc(documentDate(a.created_at))+'</p>':'')+(!historical&&set.set_id!==caseView.current_set_id?'<p>Hay cambios posteriores; esta entrega no acredita la versión actual.</p>':'')+'<button type="button" data-consult-set="'+esc(set.set_id)+'">Consultar documentos'+(historical?' históricos':'')+'</button></article>';
 };
 host.innerHTML='<p data-document-status role="status" aria-live="polite"></p><h3>DOCUMENTACIÓN ACTUAL</h3>'+(latest?card(latest,false):'<p>Sin documentos presentados.</p>')+'<p>La consulta está protegida por tu sesión y autorización vigentes de Director. No acepta evidencia ni concede permisos.</p><h3>HISTORIAL DOCUMENTAL</h3>'+(sets.length>1?sets.slice(1).map(s=>card(s,true)).join(''):'<p>Sin versiones anteriores.</p>')+'<h3>Actualizar documentación</h3><p>La persona titular debe ingresar con su propia cuenta a <a href="/staff-admission.php" target="_blank" rel="noopener">su documentación</a> y presentar la sustitución, corrección de imagen o documento adicional justificado. No requiere otra admisión. El Director no puede cargar documentos en su nombre.</p>';

 host.querySelectorAll('[data-consult-set]').forEach(b=>{b.disabled=!caseView.can_consult;b.onclick=()=>begin('READ',b.dataset.consultSet);});
 if(documentHost&&caseView.admitted){const area=document.createElement('div');area.className='member-actions';for(const action of caseView.available_actions.filter(a=>['ACCEPT','REJECT','CORRECTIONS'].includes(a))){const b=document.createElement('button');b.type='button';b.textContent=actions[action];b.dataset.reviewAction=action;b.onclick=()=>begin(action);area.append(b);}host.append(area);}
}
async function documents(id,host){
 documentHost=host;mountedCase=id;host.textContent='Cargando documentación autorizada…';
 try{await loadCase(id);}catch(e){host.textContent=e.message;}
}
async function mountCase(id){
 documentHost=null;mountedCase=id;$('advisor-detail').innerHTML='<header class="detail-header"><p class="eyebrow">Incorporación de integrante</p><h2 id="detail-title" tabindex="-1">Cargando solicitud…</h2><span class="status">Pendiente de admisión</span></header>';
 $('advisor-detail').append($('staff-case'));$('staff-case-title').hidden=true;$('staff-case-content').replaceChildren();$('staff-case-status').textContent='Cargando el caso autorizado…';
 try{await loadCase(id);if(mountedCase===id)$('staff-case-status').textContent='';}catch(e){if(mountedCase===id)$('staff-case-status').textContent=e.message;}
}
async function openCase(id){return window.staffSelectCase?.(id);}
async function begin(action,expectedSet=null){if(reviewPending){say('Consulta primero el resultado pendiente. No se repetirá la operación.');return;}await work('staff-case-status',async()=>{
 await loadCase(caseData.case_id);if(action!=='READ'&&expectedSet&&caseView.current_set_id!==expectedSet)throw new Error('La evidencia cambió; consulta el conjunto vigente antes de confirmar su revisión.');if(!(action==='READ'?caseView.can_consult:caseView.available_actions.includes(action)))throw new Error('La acción ya no está disponible. Revisa el estado actual.');
 intent={action,case_id:caseData.case_id,set_id:action==='READ'?(expectedSet||caseView.sets[0]?.set_id):caseView.current_set_id,expected_version:caseData.version,expected_review_id:caseView.review?.review_id||null};const role=actor?.class==='DIRECTOR'?'Director':actor?.class==='MASTER_ADMIN'?'Administrador maestro':'usuario autenticado';
 $('staff-stepup-title').textContent=action==='CORRECTIONS'?'Solicitar corrección de documentos':actions[action];
 $('staff-corrections-description').hidden=action!=='CORRECTIONS';$('staff-corrections-channel').hidden=action!=='CORRECTIONS';$('staff-reason-caption').textContent=action==='CORRECTIONS'?'¿Qué necesita corregir?':'Motivo';
 $('staff-stepup-target').textContent=actions[action]+' de '+name(caseData)+' · versión '+caseData.version;
 $('staff-stepup-account').textContent='Cuenta de '+role+': '+(actor?.account_reference||'Referencia no disponible');
 $('staff-stepup-explanation').textContent=action==='ADMIT'?'Se activará la relación de Asesor de esta cuenta. No se otorgarán permisos operativos; cada permiso requiere una acción independiente.':action==='ACK'?'Confirma que examinaste este conjunto exacto de documentos. Esto no acepta la evidencia ni aprueba la admisión.':action==='ACCEPT'?'Se registrará la aceptación de esta evidencia. La admisión y los permisos permanecen separados.':'Se registrará esta decisión para la evidencia seleccionada.';
 $('staff-reason-label').hidden=!['CORRECTIONS','REJECT'].includes(action);$('staff-reason').value='';$('staff-execute').textContent=actions[action];$('staff-execute').hidden=false;$('staff-stepup-status').textContent='';
 if(action==='READ')await executeReview();else show('staff-stepup');
 });}
$('staff-execute').onclick=()=>work('staff-stepup-status',executeReview);
async function executeReview(){
 const reason=['CORRECTIONS','REJECT'].includes(intent.action)?$('staff-reason').value.trim():actions[intent.action];
 if(reason.length<8)throw new Error('Describe el motivo con al menos ocho caracteres.');
 const authorized=await api('authorize',{...intent,reason});intent={...intent,...authorized};
 const evidenceSet=intent.action==='READ'?structuredClone(caseView.sets.find(s=>s.set_id===intent.set_id)):null;const evidenceCaseVersion=caseData.version;
 const body={intent_id:intent.intent_id},action=intent.action,id=intent.case_id;
 // Never resend a mutation after an uncertain response; reconcile its exact receipt.
 $('staff-execute').hidden=true;reviewPending={case_id:id,intent_id:intent.intent_id,action};rememberReview();intent=null;
 const result=await api(action==='READ'?'evidence':action==='ACK'?'acknowledge':action==='ADMIT'?'admit':'decide',action==='ADMIT'?{case_id:id,...body}:body);
 if(action==='READ'&&(!Array.isArray(result.pages)||!result.pages.length||result.pages.some(p=>typeof p.base64!=='string'||!['image/png','image/jpeg'].includes(p.mime_type))))throw new Error('La respuesta documental no es válida. Consulta el resultado antes de continuar.');
 const receipt=await api('review_result',{case_id:id,intent_id:reviewPending.intent_id});if(receipt.committed!==true||receipt.intent_id!==reviewPending.intent_id||receipt.case_id!==id||receipt.action!==action)throw new Error('No se confirmó la intención exacta. Consulta su resultado.');
 reviewPending=null;rememberReview();try{await loadCase(id);}catch(_){throw new Error('Operación confirmada; no pudimos actualizar la consulta. Cierra y consulta el caso antes de repetir acciones.');}$('staff-stepup').close();$('staff-case-status').textContent=actions[action]+': operación confirmada.';
 if(action==='READ'){
  if(!evidenceSet||caseData.case_id!==id||caseData.version!==evidenceCaseVersion||result.case_id!==id||result.set_id!==evidenceSet.set_id||result.case_version!==Number(evidenceSet.case_version))throw new Error('El caso o la evidencia cambió. Consulta el estado antes de abrir documentos de nuevo.');
  window.staffEvidenceViewer.render({name:name(caseData),category:caseData.proposed_category,set:evidenceSet,review:evidenceSet.review,reviewCurrent:!!evidenceSet.review,ackCurrent:!!evidenceSet.acknowledgement,historical:evidenceSet.set_id!==caseView.current_set_id,actions:evidenceSet.set_id===caseView.current_set_id?caseView.available_actions:[]},result.pages);$('ack-staff-evidence').onclick=()=>{$('staff-evidence').close();begin('ACK',evidenceSet.set_id);};show('staff-evidence');
 }else changed();
}
function rememberReview(){try{if(reviewPending)sessionStorage.setItem(recoveryKey+':review',JSON.stringify(reviewPending));else sessionStorage.removeItem(recoveryKey+':review');}catch(_){}$('review-reconcile').hidden=!reviewPending;}
$('review-reconcile').onclick=()=>work('wf-status',async()=>{if(!reviewPending)return;const r=await api('review_result',{case_id:reviewPending.case_id,intent_id:reviewPending.intent_id});if(r.intent_id!==reviewPending.intent_id||r.case_id!==reviewPending.case_id||r.action!==reviewPending.action)throw new Error('La respuesta no corresponde a esta revisión.');if(r.committed===true){reviewPending=null;rememberReview();say('Operación confirmada. Consulta nuevamente el caso; no se volvió a ejecutar.');changed();}else if(r.outcome==='NOT_CONSUMED'&&r.expired===true){reviewPending=null;rememberReview();say('La intención venció sin ejecutar. Revisa el caso antes de iniciar otra acción con nueva verificación.');}else say('La intención aún no tiene un resultado confirmado. Puedes volver a consultar; no se enviará otra ejecución.');});
window.staffWorkflowDirectory={
 mount:mountCase,documents,detach:detachCase,canLeave(){if(busy||$('staff-stepup').open||$('staff-evidence').open){say('Finaliza la operación actual antes de cambiar de integrante.');return false;}return !preparationDirty||confirm('¿Descartar los cambios de preparación o facultades sin guardar?');},
 async pending(q='',page=1){if(!director)throw new Error('pending_denied');return api('cases',{q,page,page_size:20,pending:true});},open:openCase,
 async profile(id){return window.staffPersonal.open(id);}
};
$('staff-case-content').addEventListener('input',()=>{preparationDirty=true;});
window.staffWorkflowApi=api;
api('bootstrap').then(b=>{csrf=b.csrf_token;director=b.director;enhanced=b.staff_control04===true;recoveryKey='staff-preparation-operation:'+b.account_id;try{recovered=JSON.parse(sessionStorage.getItem(recoveryKey));reviewPending=JSON.parse(sessionStorage.getItem(recoveryKey+':review'));}catch(_){}rememberReview();if(director){$('add-member').disabled=false;$('add-member').onclick=()=>{start();};}}).catch(e=>say(e.message));
})();
