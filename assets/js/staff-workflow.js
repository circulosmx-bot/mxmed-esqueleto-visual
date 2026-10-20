/* Approved four-step preparation; canonical authority and operation receipts remain server-owned. */
(() => {
'use strict';
const $=id=>document.getElementById(id),uid=()=>crypto.randomUUID(),esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const labels={given_names:'Nombre(s)',first_surname:'Primer apellido',second_surname:'Segundo apellido',operation_state:'Estado',operation_city:'Ciudad / municipio'};
const actions={READ:'Consultar evidencia',CORRECTIONS:'Solicitar correcciones',ACCEPT:'Aceptar evidencia y concluir revisión',REJECT:'Rechazar evidencia',ADMIT:'Aprobar admisión'};
let enhanced=false,locations=[],intentionData=null;
let csrf='',director=false,busy=false,wizard=null,step=0,caseData=null,caseView=null,caseInvitation=null,intent=null,recoveryKey='',recovered=null,reviewPending=null;
const name=c=>Object.keys(labels).slice(0,3).map(k=>c.profile?.[k]).filter(Boolean).join(' ');
const say=m=>{$('wf-status').textContent=m;},changed=()=>window.dispatchEvent(new Event('staff-workflow-changed'));
async function api(action,input={}){
 // Refresh the existing CSRF token before an explicit action, never retry a mutation.
 if(action!=='bootstrap')await api('bootstrap');
 const controller=new AbortController(),timer=setTimeout(()=>controller.abort(),20000);
 try{const r=await fetch('/api/internal/staff-admission/workflow.php',{signal:controller.signal,method:'POST',credentials:'same-origin',cache:'no-store',headers:{'Content-Type':'application/json','X-CSRF-Token':csrf},body:JSON.stringify({action,input})});const b=await r.json();
 if(r.status===401){window.teamSessionExpired?.();}
 if(!r.ok){const e=new Error(b.error==='synthetic_recipient_required'?'Usa un correo de prueba terminado en @example.test.':r.status===400?'Revisa los datos de la solicitud.':r.status===401?'Tu sesión terminó. Inicia sesión de nuevo mediante Identity.':r.status===403?'Tu cuenta no tiene autorización para esta operación.':r.status===409?'El estado cambió. Consulta la versión actual antes de continuar.':'No se pudo confirmar el resultado. Consulta el estado antes de repetir.');e.status=r.status;throw e;}if(action==='bootstrap')csrf=b.csrf_token;return b;
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
function controls(){if(!wizard)return;const locked=busy||!!wizard.pending&&wizard.pending.state!=='NONCOMMIT';for(const id of ['save-onboarding','next-onboarding','previous-onboarding'])$(id).disabled=locked;$('onboarding-content').querySelectorAll('input,select').forEach(e=>{e.disabled=locked||e.dataset.geoLoading==='true'||e.tagName==='SELECT'&&!['new-channel','new-image-mode','new-avatar','new-operation_state','new-operation_city'].includes(e.id)||e.id==='new-operation_city'&&!$('new-operation_state')?.value||e.id==='new-channel'&&!!wizard.case_id;});$('check-onboarding').hidden=!wizard.pending;$('check-onboarding').disabled=busy;}
async function work(status,fn){if(busy)return;busy=true;$('staff-case-content').inert=true;controls();try{await fn();}catch(e){$(status).textContent=e.message;}finally{busy=false;$('staff-case-content').inert=false;if(!$('staff-stepup').open){intent=null;$('staff-password').value='';$('staff-code').value='';$('staff-reason').value='';}controls();}}
for(const id of ['onboarding','staff-case','staff-profile','staff-stepup'])$(id).addEventListener('cancel',e=>{e.preventDefault();close(id);});

function close(id){
 if(id==='onboarding'){
  if(!wizard?.pending&&wizard?.dirty&&!confirm('¿Descartar los cambios sin guardar? El borrador guardado se conserva.'))return;
  const pending=!!wizard?.pending;$(id).close();$('add-member').focus();
  if(pending||busy)say('Preparación conservada. Agregar integrante permite consultar el resultado sin reenviar la operación.');else wizard=null;
  return;
 }
 if(id==='staff-profile'){window.staffPersonal?.dismiss();return;}
 if(busy){$(id).close();say('Operación en curso. Consulta el caso antes de iniciar otra acción; no se reenviará al cerrar.');return;}
 if(id==='staff-stepup'){if($('staff-reason').value&&!confirm('¿Descartar el motivo sin enviar?'))return;intent=null;$('staff-password').value='';$('staff-code').value='';$('staff-reason').value='';}
 if(id==='staff-case')$('staff-case-content').replaceChildren();$(id).close();
}
$('cancel-onboarding').onclick=()=>close('onboarding');$('close-staff-case').onclick=()=>close('staff-case');$('cancel-staff-profile').onclick=()=>close('staff-profile');$('cancel-staff-stepup').onclick=()=>close('staff-stepup');
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
 const state=r.invitation_state||r.state,expired=r.expires_at&&Date.parse(r.expires_at.replace(' ','T')+'Z')<=Date.now();
 $('onboarding-feedback').textContent=state!=='ACTIVE'||expired?'La solicitud ya tiene una invitación '+(expired?'vencida':({CONSUMED:'consumida',REVOKED:'revocada',REPLACED:'reemplazada'}[state]||'registrada'))+'. Consulta el caso; no se creó una invitación duplicada.':r.delivery_state==='DELIVERY_CONFIRMED'?'Invitación preparada en el entorno sintético. Pendiente de vinculación y confirmación personal; sin correo externo ni acceso al equipo.':'La invitación ya está registrada, pero su preparación de entrega no está confirmada. No prepares otra invitación. Consulta la solicitud.';
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
async function loadCase(id){caseData=await api('case',{case_id:id});caseView=await api('view',{case_id:id});caseInvitation=director?(await api('invitation_status',{case_id:id})).invitation:null;intentionData=enhanced&&director?await api('intentions',{case_id:id}):null;renderCase();}
function renderIntentions(){
 if(!intentionData)return;
 const data=intentionData,id=caseData.case_id,version=caseData.version;
 $('staff-case-content').insertAdjacentHTML('beforeend',`<details class="admission-section" open><summary>Facultades preparadas · pendientes</summary><p>No conceden acceso. ${data.admitted?'La admisión está registrada; confirma cada facultad en Acceso y funciones con la revisión vigente.':'La persona aún debe completar su incorporación.'}</p><p>Configuración v${data.version} · versión personal preparada ${data.prepared_case_version??'ninguna'}. ${data.prepared_case_version&&data.prepared_case_version!==version?'La versión personal cambió: revisa la selección.':''}</p><div id="pending-grants">${data.catalog.map(c=>`<label class="capability-row"><input type="checkbox" data-intention="${esc(c.key)}" ${data.capabilities.includes(c.key)?'checked':''}><span>${esc(c.label)}${data.effective_capabilities?.includes(c.key)?' · permiso efectivo vigente':''}<small>${esc(c.description)}</small></span></label>`).join('')}</div><button id="save-intentions" type="button">Guardar facultades pendientes</button></details>`);
 $('save-intentions').onclick=()=>work('staff-case-status',async()=>{const capabilities=Array.from(document.querySelectorAll('[data-intention]:checked')).map(e=>e.dataset.intention);await api('prepare_intentions',{case_id:id,expected_case_version:version,expected_version:data.version,capabilities,operation_id:uid()});await loadCase(id);$('staff-case-status').textContent='Configuración pendiente guardada. No se concedió acceso.';});
 if(data.admitted&&data.prepared_case_version===version){
  const box=document.createElement('div');box.className='member-actions';for(const cap of data.capabilities){if(data.effective_capabilities?.includes(cap)||data.confirmed_capabilities?.includes(cap))continue;const c=data.catalog.find(c=>c.key===cap);if(!c)continue;const button=document.createElement('button');button.type='button';button.textContent='Confirmar permiso: '+c.label;button.dataset.activateIntention=cap;button.onclick=()=>{if(confirm('¿Conceder este permiso a la cuenta admitida? '+c.label))work('staff-case-status',async()=>{await api('activate_intention',{case_id:id,expected_case_version:version,expected_version:data.version,capability:cap});button.disabled=true;$('staff-case-status').textContent='Operación confirmada. Consulta Acceso y funciones para verificar los permisos efectivos.';changed();});};box.append(button);}$('staff-case-content').append(box);
 }
 if(!caseView.admitted){const sim=document.createElement('button');sim.type='button';sim.id='simulate-onboarding';sim.textContent='Simular incorporación';sim.onclick=()=>work('staff-case-status',async()=>{let r=await api('simulate',{case_id:id});$('staff-case-status').textContent=r.message;const end=Date.now()+120000;while(['QUEUED','RUNNING'].includes(r.state)&&Date.now()<end&&$('staff-case').open&&caseData.case_id===id){await new Promise(resolve=>setTimeout(resolve,1500));r=await api('simulate',{case_id:id});$('staff-case-status').textContent=r.message;}if(r.state==='REVIEW_REQUIRED'&&$('staff-case').open&&caseData.case_id===id){await loadCase(id);$('staff-case-status').textContent=r.message;}});$('staff-case-content').append(sim);const hint=document.createElement('p');hint.textContent='Completa el recorrido del solicitante con una cuenta ficticia de prueba. Consulta de evidencia, revisión y admisión siguen requiriendo tus acciones y MFA.';$('staff-case-content').append(hint);}
 if(!caseView.admitted){$('staff-case-content').insertAdjacentHTML('beforeend','<button id="revoke-preparation" type="button">Revocar incorporación pendiente</button>');$('revoke-preparation').onclick=()=>{if(confirm('¿Revocar esta incorporación pendiente conservando su historial?'))work('staff-case-status',async()=>{await api('revoke',{case_id:id,expected_version:version,operation_id:uid()});$('staff-case').close();changed();say('Incorporación revocada. No se activaron permisos.');});};}
}
function renderCase(){
 $('staff-case-title').textContent=name(caseData)||'Solicitud de incorporación';
 const status=caseView.admitted?(caseView.review_current?'Admisión registrada.':'Admisión registrada; los cambios requieren nueva confirmación personal.'):(caseView.review?({ACCEPT:'Evidencia aceptada',REJECT:'Evidencia rechazada',CORRECTIONS:'Correcciones solicitadas'}[caseView.review.decision]||'Revisión registrada')+(caseView.review_current?'':' · La versión cambió'):caseData.account_bound?'Pendiente de confirmación o revisión':'Pendiente de vinculación de cuenta');
 $('staff-case-content').innerHTML=`<p>${esc(status)}</p><p>Solicitud: <strong>${esc(caseData.case_id)}</strong></p>${caseInvitation?`<p>Invitación: ${esc({ACTIVE:'registrada',CONSUMED:'consumida',REVOKED:'revocada',EXPIRED:'vencida',REPLACED:'reemplazada'}[caseInvitation.state]||'registrada')}. ${caseInvitation.delivery_state==='DELIVERY_CONFIRMED'?'Preparada sólo en el entorno sintético, sin correo externo.':'Preparación de entrega pendiente de confirmación; no crear otra invitación.'}</p>`:''}${caseView.review?`<p>Último resultado: ${esc({ACCEPT:'Evidencia aceptada',REJECT:'Evidencia rechazada',CORRECTIONS:'Correcciones solicitadas'}[caseView.review.decision]||'Revisión registrada')} · ${esc(caseView.review.created_at)}. ${esc(caseView.review.reason)}</p>`:'<p>No hay una revisión de identidad registrada para este caso.</p>'}<p>Versión personal ${caseData.version} · ${esc({ADMIN_PREPARED:'Preparada por Dirección',APPLICANT_CONFIRMED:'Confirmada por la persona',APPLICANT_CORRECTED:'Corregida por la persona',INTENTION_REVOKED:'Solicitud revocada'}[caseData.profile.provenance]||'Versión registrada')}. ${caseData.account_bound?'Cuenta vinculada.':'Cuenta aún no vinculada.'}</p><dl class="onboarding-summary">${Object.entries(labels).map(([k,l])=>`<dt>${l}</dt><dd>${esc(caseData.profile[k]||'Sin información')}</dd>`).join('')}</dl><details class="admission-section" open><summary>IDENTIDAD Y DOCUMENTACIÓN</summary><p>${caseView.sets.length?'Conjunto documental '+esc(caseView.sets[0].sequence_number)+' presentado. El acceso a las imágenes requiere verificación.':'Aún no se han presentado documentos.'}</p>${caseView.sets.length?'<ul>'+caseView.sets.map(set=>'<li>Conjunto '+esc(set.sequence_number)+' · versión personal '+esc(set.case_version)+' · referencia '+esc(set.set_id)+'</li>').join('')+'</ul>':'<p>No hay un conjunto de evidencia presentado para consultar.</p>'}${!caseView.available_actions.includes('READ')&&caseView.sets.length?'<p>La evidencia presentada no corresponde a una versión personal confirmada y vigente. La consulta de imágenes está bloqueada; la revisión registrada se conserva como antecedente.</p>':''}<div class="member-actions">${Object.entries(actions).map(([key,label])=>`<button type="button" data-review-action="${key}" ${caseView.available_actions.includes(key)?'':'disabled'}>${label}</button>`).join('')}</div><p>Consultar evidencia requiere verificación. Para aceptar debes haberla consultado en esta sesión; aprobar la admisión requiere una revisión positiva vigente.</p><div id="case-pages"></div></details>${!caseData.account_bound&&!caseView.admitted?'<button id="resume-preparation" type="button">Continuar preparación</button>':''}`;
 renderIntentions();
 $('staff-case-content').querySelectorAll('[data-review-action]').forEach(b=>b.onclick=()=>begin(b.dataset.reviewAction));
 if($('resume-preparation'))$('resume-preparation').onclick=()=>{const c=caseData;close('staff-case');start(c);};
}
async function openCase(id){if(busy){say('Espera el resultado de la operación en curso.');return;}$('staff-case-title').textContent='Revisión de identidad';$('staff-case-content').replaceChildren();$('staff-case-status').textContent='Cargando el caso autorizado…';show('staff-case');await work('staff-case-status',async()=>{await loadCase(id);$('staff-case-status').textContent='';});}
async function begin(action){if(reviewPending){say('Consulta primero el resultado pendiente. No se repetirá la operación.');return;}await work('staff-case-status',async()=>{
 await loadCase(caseData.case_id);if(!caseView.available_actions.includes(action))throw new Error('La acción ya no está disponible. Revisa el estado actual.');
 intent={action,case_id:caseData.case_id,set_id:caseView.sets[0].set_id};$('staff-stepup-title').textContent=actions[action];$('staff-stepup-target').textContent=name(caseData)+' · versión '+caseData.version;
 $('staff-reason-label').hidden=!['CORRECTIONS','REJECT'].includes(action);$('staff-reason').value='';$('staff-password').value='';$('staff-code').value='';$('staff-password-stage').hidden=false;$('staff-code-stage').hidden=true;$('staff-stepup-status').textContent='Confirma esta acción con tu cuenta actual.';show('staff-stepup');
 });}
$('staff-challenge').onclick=()=>work('staff-stepup-status',async()=>{
 const password=$('staff-password').value;$('staff-password').value='';const reason=['CORRECTIONS','REJECT'].includes(intent.action)?$('staff-reason').value.trim():actions[intent.action];
 if(reason.length<8)throw new Error('Describe el motivo con al menos ocho caracteres.');
 const ch=await api('challenge',{case_id:intent.case_id,set_id:intent.set_id,action:intent.action,reason,password});intent={...intent,...ch};$('staff-password-stage').hidden=true;$('staff-code-stage').hidden=false;$('staff-stepup-status').textContent='Introduce un código vigente para esta acción.';$('staff-code').focus();
});
$('staff-execute').onclick=()=>work('staff-stepup-status',async()=>{
 const code=$('staff-code').value;$('staff-code').value='';const proof=await api('verify',{challenge_id:intent.challenge_id,challenge:intent.challenge,code});const body={intent_id:intent.intent_id,proof_id:proof.proof_id,proof:proof.proof},action=intent.action,id=intent.case_id;
 // Never automatically reuse a proof after an uncertain response.
 $('staff-code-stage').hidden=true;reviewPending={case_id:id,intent_id:intent.intent_id,action};rememberReview();intent=null;
 const result=await api(action==='READ'?'evidence':action==='ADMIT'?'admit':'decide',action==='ADMIT'?{case_id:id,...body}:body);
 if(action==='READ'&&(!Array.isArray(result.pages)||!result.pages.length||result.pages.some(p=>typeof p.base64!=='string'||!['image/png','image/jpeg'].includes(p.mime_type))))throw new Error('La respuesta documental no es válida. Consulta el resultado antes de continuar.');
 const receipt=await api('review_result',{case_id:id,intent_id:reviewPending.intent_id});if(receipt.committed!==true||receipt.intent_id!==reviewPending.intent_id||receipt.case_id!==id||receipt.action!==action)throw new Error('No se confirmó la intención exacta. Consulta su resultado.');
 reviewPending=null;rememberReview();try{await loadCase(id);}catch(_){throw new Error('Operación confirmada; no pudimos actualizar la consulta. Cierra y consulta el caso antes de repetir acciones.');}$('staff-stepup').close();$('staff-case-status').textContent=actions[action]+': operación confirmada.';
 if(action==='READ'){for(const page of result.pages){const img=document.createElement('img');img.alt=`Documento ${page.document_id}, ${page.side}`;img.src=`data:${page.mime_type};base64,${page.base64}`;img.width=320;img.style.maxWidth='100%';$('case-pages').append(img);}}else changed();
});
function rememberReview(){try{if(reviewPending)sessionStorage.setItem(recoveryKey+':review',JSON.stringify(reviewPending));else sessionStorage.removeItem(recoveryKey+':review');}catch(_){}$('review-reconcile').hidden=!reviewPending;}
$('review-reconcile').onclick=()=>work('wf-status',async()=>{if(!reviewPending)return;const r=await api('review_result',{case_id:reviewPending.case_id,intent_id:reviewPending.intent_id});if(r.intent_id!==reviewPending.intent_id||r.case_id!==reviewPending.case_id||r.action!==reviewPending.action)throw new Error('La respuesta no corresponde a esta revisión.');if(r.committed===true){reviewPending=null;rememberReview();say('Operación confirmada. Consulta nuevamente el caso; no se volvió a ejecutar.');changed();}else if(r.outcome==='NOT_CONSUMED'&&r.expired===true){reviewPending=null;rememberReview();say('La intención venció sin ejecutar. Revisa el caso antes de iniciar otra acción con nueva verificación.');}else say('La intención aún no tiene un resultado confirmado. Puedes volver a consultar; no se reutilizará la prueba ni se enviará otra ejecución.');});
window.staffWorkflowDirectory={
 async pending(q='',page=1){if(!director)throw new Error('pending_denied');return api('cases',{q,page,page_size:20,pending:true});},open:openCase,
 async profile(id){return window.staffPersonal.open(id);}
};
window.staffWorkflowApi=api;
api('bootstrap').then(b=>{csrf=b.csrf_token;director=b.director;enhanced=b.staff_control04===true;recoveryKey='staff-preparation-operation:'+b.account_id;try{recovered=JSON.parse(sessionStorage.getItem(recoveryKey));reviewPending=JSON.parse(sessionStorage.getItem(recoveryKey+':review'));}catch(_){}rememberReview();if(director){$('add-member').disabled=false;$('add-member').onclick=()=>{start();};}}).catch(e=>say(e.message));
})();
