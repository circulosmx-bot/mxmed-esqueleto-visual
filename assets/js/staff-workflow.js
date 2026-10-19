/* Approved four-step preparation; canonical authority and operation receipts remain server-owned. */
(() => {
'use strict';
const $=id=>document.getElementById(id),uid=()=>crypto.randomUUID(),esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const labels={given_names:'Nombre(s)',first_surname:'Primer apellido',second_surname:'Segundo apellido',operation_state:'Estado',operation_city:'Ciudad'};
const actions={READ:'Consultar evidencia',CORRECTIONS:'Solicitar correcciones',ACCEPT:'Aceptar evidencia y concluir revisión',REJECT:'Rechazar evidencia',ADMIT:'Aprobar admisión'};
let csrf='',director=false,busy=false,wizard=null,step=0,caseData=null,caseView=null,caseInvitation=null,intent=null,recoveryKey='',recovered=null,reviewPending=null;
const name=c=>Object.keys(labels).slice(0,3).map(k=>c.profile?.[k]).filter(Boolean).join(' ');
const say=m=>{$('wf-status').textContent=m;},changed=()=>window.dispatchEvent(new Event('staff-workflow-changed'));
async function api(action,input={}){
 const controller=new AbortController(),timer=setTimeout(()=>controller.abort(),20000);
 try{const r=await fetch('/api/internal/staff-admission/workflow.php',{signal:controller.signal,method:'POST',credentials:'same-origin',cache:'no-store',headers:{'Content-Type':'application/json','X-CSRF-Token':csrf},body:JSON.stringify({action,input})});const b=await r.json();
 if(!r.ok){const e=new Error(b.error==='synthetic_recipient_required'?'Usa un correo de prueba terminado en @example.test.':r.status===400?'Revisa los datos de la solicitud.':r.status===401||r.status===403?'Tu sesión o la política vigente no autoriza esta operación.':r.status===409?'El estado cambió. Consulta la versión actual antes de continuar.':'No se pudo confirmar el resultado. Consulta el estado antes de repetir.');e.status=r.status;throw e;}return b;
 }catch(e){if(e.status)throw e;throw new Error('No pudimos confirmar la respuesta. Consulta el estado.');}finally{clearTimeout(timer);}
}
function fields(prefix,profile){return `<div class="admission-fields">${Object.entries(labels).map(([k,l])=>`<label class="wizard-field">${l}<input id="${prefix}-${k}" maxlength="120" value="${esc(profile[k]||'')}"></label>`).join('')}</div>`;}
function profile(prefix){return Object.fromEntries(Object.keys(labels).map(k=>[k,$(prefix+'-'+k).value.trim()]));}
function show(id){$(id).showModal();$(id).querySelector('input,button')?.focus();}
function controls(){if(!wizard)return;const locked=busy||!!wizard.pending&&wizard.pending.state!=='NONCOMMIT';for(const id of ['save-onboarding','next-onboarding','previous-onboarding'])$(id).disabled=locked;$('onboarding-content').querySelectorAll('input,select').forEach(e=>{e.disabled=locked||e.tagName==='SELECT'&&e.id!=='new-channel'||e.id==='new-channel'&&!!wizard.case_id;});$('check-onboarding').hidden=!wizard.pending;$('check-onboarding').disabled=busy;}
async function work(status,fn){if(busy)return;busy=true;controls();try{await fn();}catch(e){$(status).textContent=e.message;}finally{busy=false;if(!$('staff-stepup').open){intent=null;$('staff-password').value='';$('staff-code').value='';$('staff-reason').value='';}controls();}}
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
 try{if(wizard?.pending)sessionStorage.setItem(recoveryKey,JSON.stringify({operation_id:wizard.pending.operation_id,action:wizard.pending.action,case_id:wizard.case_id}));else sessionStorage.removeItem(recoveryKey);}catch(_){}
}
function start(c=null){
 if(!(wizard?.pending||busy&&wizard)){wizard={profile:c?.profile||{},email:'',channel:c?.channel||'ASSISTED',case_id:c?.case_id||'',version:c?.version||1,operation:uid(),recipient:uid(),invitationOperation:uid(),dirty:false,done:false,pending:recovered};recovered=null;step=0;}
 renderWizard();show('onboarding');
 if(wizard.pending)$('onboarding-feedback').textContent='Hay una operación pendiente de comprobar. Consulta su estado; no se reenviará al abrir.';
}
function capture(){if(step===0){wizard.profile=profile('new');wizard.email=$('new-email').value.trim();wizard.channel=$('new-channel').value;}}
function valid(){if(!wizard.profile.given_names||!wizard.profile.first_surname){$('onboarding-feedback').textContent='Completa nombre y primer apellido.';return false;}if(wizard.email&&!/^[^\s@]+@example\.test$/i.test(wizard.email)){$('onboarding-feedback').textContent='En esta demostración usa un correo de prueba terminado en @example.test. No se envía correo externo.';return false;}return true;}
function renderWizard(){
 $('onboarding-title').textContent=`Agregar integrante · paso ${step+1} de 4`;$('onboarding-feedback').textContent='';document.querySelectorAll('#onboarding .wizard-steps li').forEach((e,i)=>{if(i===step)e.setAttribute('aria-current','step');else e.removeAttribute('aria-current');});
 $('previous-onboarding').hidden=step===0||wizard.done;$('save-onboarding').hidden=wizard.done;$('next-onboarding').hidden=wizard.done;$('next-onboarding').textContent=step===3?'Preparar invitación':'Continuar';
 if(step===0)$('onboarding-content').innerHTML=fields('new',wizard.profile)+`<label class="wizard-field">Correo del destinatario<input id="new-email" type="email" maxlength="190" autocomplete="off" aria-describedby="synthetic-email-help" value="${esc(wizard.email)}"></label><p id="synthetic-email-help">Usa @example.test. La invitación se prepara sólo dentro de esta demostración; no se envía correo externo.</p><label class="wizard-field">Vía de preparación<select id="new-channel"><option value="ASSISTED">Asistida por administrador</option><option value="REMOTE_PREAUTHORIZED">Remota preautorizada</option></select></label>`;
 if(step===1)$('onboarding-content').innerHTML='<label class="wizard-field">Categoría interna<select disabled><option>Asesor</option></select></label><p>La incorporación inicia como Asesor. La designación Master requiere su procedimiento independiente.</p>';
 if(step===2)$('onboarding-content').innerHTML='<h3>Permisos iniciales</h3><p>La admisión no concede permisos automáticamente. Después de aprobarla, configura los permisos individuales en Acceso y funciones.</p><p>La persona confirmará sus propios datos y documentos; la revisión de identidad es un paso posterior independiente.</p>';
 if(step===3)$('onboarding-content').innerHTML=`<dl class="onboarding-summary"><dt>Persona</dt><dd>${esc(name({profile:wizard.profile}))}</dd><dt>Destino de la invitación</dt><dd>${esc(wizard.email||'Pendiente de completar')}</dd><dt>Ciudad / estado</dt><dd>${esc([wizard.profile.operation_city,wizard.profile.operation_state].filter(Boolean).join(', ')||'Sin información')}</dd><dt>Categoría</dt><dd>Asesor</dd><dt>Permisos</dt><dd>Sin concesiones automáticas</dd><dt>Vía</dt><dd>${wizard.channel==='ASSISTED'?'Asistida':'Remota preautorizada'}</dd></dl><p>Se prepara una solicitud y su invitación. No se activa acceso al equipo.</p>`;
 if(wizard.done){$('onboarding-content').insertAdjacentHTML('beforeend','<button id="open-prepared-case" type="button">Ver solicitud preparada</button>');$('open-prepared-case').onclick=()=>{const id=wizard.case_id;close('onboarding');openCase(id);};}
 if(step===0){$('new-channel').value=wizard.channel;$('onboarding-content').oninput=()=>{wizard.dirty=true;};}controls();
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
async function saveDraft(){capture();if(!valid())return false;if(wizard.pending){await reconcile();if(wizard.pending?.state!=='NONCOMMIT')return false;}
 if(wizard.case_id&&!wizard.dirty&&(!wizard.pending||wizard.pending.action==='issue'))return true;
 const input={operation_id:wizard.operation,...wizard.profile};
 const result=wizard.case_id?await mutate('update',{...input,case_id:wizard.case_id,expected_version:wizard.version}):await mutate('prepare',{...input,recipient_reference:wizard.recipient,channel:wizard.channel,proposed_category:'ADVISOR'});
 draftResult(result);return true;
}
$('check-onboarding').onclick=()=>work('onboarding-feedback',reconcile);
$('previous-onboarding').onclick=()=>{if(busy)return;capture();step--;renderWizard();};
$('save-onboarding').onclick=()=>work('onboarding-feedback',async()=>{if(await saveDraft())$('onboarding-feedback').textContent='Borrador guardado. Puedes continuar ahora o retomar el caso desde Pendientes.';});
$('next-onboarding').onclick=()=>work('onboarding-feedback',async()=>{
 capture();if(!valid())return;if(step<3){step++;renderWizard();return;}
 if(!wizard.email){$('onboarding-feedback').textContent='Completa el correo del destinatario en Persona.';return;}
 if(wizard.pending){await reconcile();if(wizard.done||wizard.pending?.state!=='NONCOMMIT')return;}
 if(wizard.case_id){const existing=await api('invitation_status',{case_id:wizard.case_id});if(existing.invitation){invitationResult(existing.invitation);return;}}
 if((!wizard.case_id||wizard.dirty)&&!await saveDraft())return;
 const state=await api('invitation_status',{case_id:wizard.case_id});
 if(state.invitation){invitationResult(state.invitation);return;}
 if(state.account_bound||state.case_state!=='PREPARING'||state.version!==wizard.version)throw new Error('La solicitud cambió o ya está vinculada. Consulta su estado desde Pendientes.');
 const result=await mutate('issue',{operation_id:wizard.invitationOperation,case_id:wizard.case_id,expected_version:wizard.version,recipient_email:wizard.email});invitationResult(result);
});
async function loadCase(id){caseData=await api('case',{case_id:id});caseView=await api('view',{case_id:id});caseInvitation=director?(await api('invitation_status',{case_id:id})).invitation:null;renderCase();}
function renderCase(){
 $('staff-case-title').textContent=name(caseData)||'Solicitud de incorporación';
 const status=caseView.admitted?(caseView.review_current?'Admisión registrada.':'Admisión registrada; los cambios requieren nueva confirmación personal.'):(caseView.review?({ACCEPT:'Evidencia aceptada',REJECT:'Evidencia rechazada',CORRECTIONS:'Correcciones solicitadas'}[caseView.review.decision]||'Revisión registrada')+(caseView.review_current?'':' · La versión cambió'):caseData.account_bound?'Pendiente de confirmación o revisión':'Pendiente de vinculación de cuenta');
 $('staff-case-content').innerHTML=`<p>${esc(status)}</p>${caseInvitation?`<p>Invitación: ${esc({ACTIVE:'registrada',CONSUMED:'consumida',REVOKED:'revocada',EXPIRED:'vencida',REPLACED:'reemplazada'}[caseInvitation.state]||'registrada')}. ${caseInvitation.delivery_state==='DELIVERY_CONFIRMED'?'Preparada sólo en el entorno sintético, sin correo externo.':'Preparación de entrega pendiente de confirmación; no crear otra invitación.'}</p>`:''}${caseView.review?`<p>${esc(caseView.review.reason)}</p>`:''}<p>Versión personal ${caseData.version} · ${esc({ADMIN_PREPARED:'Preparada por Dirección',APPLICANT_CONFIRMED:'Confirmada por la persona',APPLICANT_CORRECTED:'Corregida por la persona',INTENTION_REVOKED:'Solicitud revocada'}[caseData.profile.provenance]||'Versión registrada')}. ${caseData.account_bound?'Cuenta vinculada.':'Cuenta aún no vinculada.'}</p><dl class="onboarding-summary">${Object.entries(labels).map(([k,l])=>`<dt>${l}</dt><dd>${esc(caseData.profile[k]||'Sin información')}</dd>`).join('')}</dl><details class="admission-section" open><summary>IDENTIDAD Y DOCUMENTACIÓN</summary><p>${caseView.sets.length?'Conjunto documental '+esc(caseView.sets[0].sequence_number)+' presentado. El acceso a las imágenes requiere verificación.':'Aún no se han presentado documentos.'}</p><div class="member-actions">${Object.entries(actions).map(([key,label])=>`<button type="button" data-review-action="${key}" ${caseView.available_actions.includes(key)?'':'disabled'}>${label}</button>`).join('')}</div><p>Consultar evidencia requiere verificación. Para aceptar debes haberla consultado en esta sesión; aprobar la admisión requiere una revisión positiva vigente.</p><div id="case-pages"></div></details>${!caseData.account_bound&&!caseView.admitted?'<button id="resume-preparation" type="button">Continuar preparación</button>':''}`;
 $('staff-case-content').querySelectorAll('[data-review-action]').forEach(b=>b.onclick=()=>begin(b.dataset.reviewAction));
 if($('resume-preparation'))$('resume-preparation').onclick=()=>{const c=caseData;close('staff-case');start(c);};
}
async function openCase(id){await work('wf-status',async()=>{await loadCase(id);$('staff-case-status').textContent='';show('staff-case');});}
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
api('bootstrap').then(b=>{csrf=b.csrf_token;director=b.director;recoveryKey='staff-preparation-operation:'+b.account_id;try{recovered=JSON.parse(sessionStorage.getItem(recoveryKey));reviewPending=JSON.parse(sessionStorage.getItem(recoveryKey+':review'));}catch(_){}rememberReview();if(director){$('add-member').disabled=false;$('add-member').onclick=()=>{start();};}}).catch(e=>say(e.message));
})();
