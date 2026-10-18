/* Approved four-step preparation, contextual case review and versioned member editing.
 * All authority, identity, grants, step-up and persistence remain server-owned. */
(() => {
'use strict';
const $=id=>document.getElementById(id),uid=()=>crypto.randomUUID(),esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const labels={given_names:'Nombre(s)',first_surname:'Primer apellido',second_surname:'Segundo apellido',operation_state:'Estado',operation_city:'Ciudad'};
const actions={READ:'Consultar evidencia',CORRECTIONS:'Solicitar correcciones',ACCEPT:'Aceptar evidencia y concluir revisión',REJECT:'Rechazar evidencia',ADMIT:'Aprobar admisión'};
let csrf='',director=false,busy=false,wizard=null,step=0,caseData=null,caseView=null,profileCase=null,intent=null;
const name=c=>Object.keys(labels).slice(0,3).map(k=>c.profile?.[k]).filter(Boolean).join(' ');
const say=m=>{$('wf-status').textContent=m;},changed=()=>window.dispatchEvent(new Event('staff-workflow-changed'));
async function api(action,input={}){const r=await fetch('/api/internal/staff-admission/workflow.php',{method:'POST',credentials:'same-origin',cache:'no-store',headers:{'Content-Type':'application/json','X-CSRF-Token':csrf},body:JSON.stringify({action,input})});const b=await r.json();if(!r.ok)throw new Error(r.status===409?'El estado cambió. Consulta la versión actual antes de continuar.':'La acción no se confirmó. Consulta el estado antes de repetirla.');return b;}
function fields(prefix,profile){return `<div class="admission-fields">${Object.entries(labels).map(([k,l])=>`<label class="wizard-field">${l}<input id="${prefix}-${k}" maxlength="120" value="${esc(profile[k]||'')}"></label>`).join('')}</div>`;}
function profile(prefix){return Object.fromEntries(Object.keys(labels).map(k=>[k,$(prefix+'-'+k).value.trim()]));}
function show(id){$(id).showModal();$(id).querySelector('input,button')?.focus();}
async function work(status,fn){if(busy)return;busy=true;try{await fn();}catch(e){$(status).textContent=e.message;}finally{busy=false;}}
for(const id of ['onboarding','staff-case','staff-profile','staff-stepup'])$(id).addEventListener('cancel',e=>{e.preventDefault();if(!busy)close(id);});
function close(id){if(busy)return;if(id==='onboarding'&&wizard?.dirty&&!confirm('¿Cerrar la preparación? Los cambios sin guardar se descartarán.'))return;if(id==='staff-stepup'){intent=null;$('staff-password').value='';$('staff-code').value='';$('staff-reason').value='';}if(id==='staff-case')$('staff-case-content').replaceChildren();$(id).close();}
$('cancel-onboarding').onclick=()=>close('onboarding');$('close-staff-case').onclick=()=>close('staff-case');$('cancel-staff-profile').onclick=()=>close('staff-profile');$('cancel-staff-stepup').onclick=()=>close('staff-stepup');
function start(c=null){wizard={profile:c?.profile||{},email:'',channel:c?.channel||'ASSISTED',case_id:c?.case_id||'',version:c?.version||1,operation:uid(),recipient:uid(),invitationOperation:uid(),dirty:false,done:false};step=0;renderWizard();show('onboarding');}
function capture(){if(step===0){wizard.profile=profile('new');wizard.email=$('new-email').value.trim();wizard.channel=$('new-channel').value;}}
function valid(){if(!wizard.profile.given_names||!wizard.profile.first_surname){$('onboarding-feedback').textContent='Completa nombre y primer apellido.';return false;}if(wizard.email&&!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(wizard.email)){$('onboarding-feedback').textContent='Revisa el correo del destinatario.';return false;}return true;}
function renderWizard(){
 $('onboarding-title').textContent=`Agregar integrante · paso ${step+1} de 4`;$('onboarding-feedback').textContent='';document.querySelectorAll('#onboarding .wizard-steps li').forEach((e,i)=>{if(i===step)e.setAttribute('aria-current','step');else e.removeAttribute('aria-current');});
 $('previous-onboarding').hidden=step===0||wizard.done;$('save-onboarding').hidden=wizard.done;$('next-onboarding').hidden=wizard.done;$('next-onboarding').textContent=step===3?'Preparar invitación':'Continuar';
 if(step===0)$('onboarding-content').innerHTML=fields('new',wizard.profile)+`<label class="wizard-field">Correo del destinatario<input id="new-email" type="email" autocomplete="off" value="${esc(wizard.email)}"></label><label class="wizard-field">Vía de preparación<select id="new-channel"><option value="ASSISTED">Asistida por administrador</option><option value="REMOTE_PREAUTHORIZED">Remota preautorizada</option></select></label>`;
 if(step===1)$('onboarding-content').innerHTML='<label class="wizard-field">Categoría interna<select disabled><option>Asesor</option></select></label><p>La incorporación inicia como Asesor. La designación Master requiere su procedimiento independiente.</p>';
 if(step===2)$('onboarding-content').innerHTML='<h3>Permisos iniciales</h3><p>La admisión no concede permisos automáticamente. Después de aprobarla, configura los permisos individuales en Acceso y funciones.</p><p>La persona confirmará sus propios datos y documentos; la revisión de identidad es un paso posterior independiente.</p>';
 if(step===3)$('onboarding-content').innerHTML=`<dl class="onboarding-summary"><dt>Persona</dt><dd>${esc(name({profile:wizard.profile}))}</dd><dt>Destino de la invitación</dt><dd>${esc(wizard.email||'Pendiente de completar')}</dd><dt>Ciudad / estado</dt><dd>${esc([wizard.profile.operation_city,wizard.profile.operation_state].filter(Boolean).join(', ')||'Sin información')}</dd><dt>Categoría</dt><dd>Asesor</dd><dt>Permisos</dt><dd>Sin concesiones automáticas</dd><dt>Vía</dt><dd>${wizard.channel==='ASSISTED'?'Asistida':'Remota preautorizada'}</dd></dl><p>Se prepara una solicitud y su invitación. No se activa acceso al equipo.</p>`;
 if(wizard.done){$('onboarding-content').insertAdjacentHTML('beforeend','<button id="open-prepared-case" type="button">Ver solicitud preparada</button>');$('open-prepared-case').onclick=()=>{const id=wizard.case_id;close('onboarding');openCase(id);};}
 if(step===0){$('new-channel').value=wizard.channel;$('new-channel').disabled=!!wizard.case_id;$('onboarding-content').oninput=()=>{wizard.dirty=true;};}
}
async function saveDraft(){capture();if(!valid())return false;if(wizard.case_id&&!wizard.dirty)return true;
 const input={operation_id:wizard.operation,...wizard.profile};
 const result=wizard.case_id?await api('update',{...input,case_id:wizard.case_id,expected_version:wizard.version}):await api('prepare',{...input,recipient_reference:wizard.recipient,channel:wizard.channel,proposed_category:'ADVISOR'});
 wizard.case_id=result.case_id;wizard.version=result.version;wizard.operation=uid();wizard.dirty=false;changed();return true;
}
$('previous-onboarding').onclick=()=>{if(busy)return;capture();step--;renderWizard();};
$('save-onboarding').onclick=()=>work('onboarding-feedback',async()=>{if(await saveDraft())$('onboarding-feedback').textContent='Borrador guardado. El correo se utiliza al preparar la invitación; puedes continuar ahora o retomar el caso desde Pendientes.';});
$('next-onboarding').onclick=()=>work('onboarding-feedback',async()=>{
 capture();if(!valid())return;if(step<3){step++;renderWizard();return;}
 if(!wizard.email){$('onboarding-feedback').textContent='Completa el correo del destinatario en Persona.';return;}
 if((!wizard.case_id||wizard.dirty)&&!await saveDraft())return;
 await api('issue',{operation_id:wizard.invitationOperation,case_id:wizard.case_id,expected_version:wizard.version,recipient_email:wizard.email});wizard.done=true;wizard.dirty=false;renderWizard();$('onboarding-feedback').textContent='Invitación preparada. La solicitud está pendiente de vinculación y confirmación personal. No se ha concedido acceso al equipo.';say('Solicitud preparada. Continúa desde Pendientes.');changed();
});
async function loadCase(id){caseData=await api('case',{case_id:id});caseView=await api('view',{case_id:id});renderCase();}
function renderCase(){
 $('staff-case-title').textContent=name(caseData)||'Solicitud de incorporación';
 const status=caseView.admitted?(caseView.review_current?'Admisión registrada.':'Admisión registrada; los cambios requieren nueva confirmación personal.'):(caseView.review?({ACCEPT:'Evidencia aceptada',REJECT:'Evidencia rechazada',CORRECTIONS:'Correcciones solicitadas'}[caseView.review.decision]||'Revisión registrada')+(caseView.review_current?'':' · La versión cambió'):caseData.account_bound?'Pendiente de confirmación o revisión':'Pendiente de vinculación de cuenta');
 $('staff-case-content').innerHTML=`<p>${esc(status)}</p>${caseView.review?`<p>${esc(caseView.review.reason)}</p>`:''}<p>Versión personal ${caseData.version} · ${esc({ADMIN_PREPARED:'Preparada por Dirección',APPLICANT_CONFIRMED:'Confirmada por la persona',APPLICANT_CORRECTED:'Corregida por la persona',INTENTION_REVOKED:'Solicitud revocada'}[caseData.profile.provenance]||'Versión registrada')}. ${caseData.account_bound?'Cuenta vinculada.':'Cuenta aún no vinculada.'}</p><dl class="onboarding-summary">${Object.entries(labels).map(([k,l])=>`<dt>${l}</dt><dd>${esc(caseData.profile[k]||'Sin información')}</dd>`).join('')}</dl><details class="admission-section" open><summary>IDENTIDAD Y DOCUMENTACIÓN</summary><p>${caseView.sets.length?'Conjunto documental '+esc(caseView.sets[0].sequence_number)+' presentado. El acceso a las imágenes requiere verificación.':'Aún no se han presentado documentos.'}</p><div class="member-actions">${Object.entries(actions).map(([key,label])=>`<button type="button" data-review-action="${key}" ${caseView.available_actions.includes(key)?'':'disabled'}>${label}</button>`).join('')}</div><p>Consultar evidencia requiere verificación. Para aceptar debes haberla consultado en esta sesión; aprobar la admisión requiere una revisión positiva vigente.</p><div id="case-pages"></div></details>${!caseData.account_bound&&!caseView.admitted?'<button id="resume-preparation" type="button">Continuar preparación</button>':''}`;
 $('staff-case-content').querySelectorAll('[data-review-action]').forEach(b=>b.onclick=()=>begin(b.dataset.reviewAction));
 if($('resume-preparation'))$('resume-preparation').onclick=()=>{const c=caseData;close('staff-case');start(c);};
}
async function openCase(id){await work('wf-status',async()=>{await loadCase(id);$('staff-case-status').textContent='';show('staff-case');});}
async function begin(action){await work('staff-case-status',async()=>{
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
 $('staff-code-stage').hidden=true;intent=null;
 const result=await api(action==='READ'?'evidence':action==='ADMIT'?'admit':'decide',action==='ADMIT'?{case_id:id,...body}:body);
 try{await loadCase(id);}catch(_){throw new Error('Operación confirmada; no pudimos actualizar la consulta. Cierra y consulta el caso antes de repetir acciones.');}$('staff-stepup').close();$('staff-case-status').textContent=actions[action]+': operación confirmada.';
 if(action==='READ'){for(const page of result.pages){const img=document.createElement('img');img.alt=`Documento ${page.document_id}, ${page.side}`;img.src=`data:${page.mime_type};base64,${page.base64}`;img.width=320;img.style.maxWidth='100%';$('case-pages').append(img);}}else changed();
});
window.staffWorkflowDirectory={
 async pending(){if(!director)throw new Error('pending_denied');return (await api('cases')).items.filter(c=>!Number(c.admitted)&&c.state!=='REVOKED');},open:openCase,
 async profile(id){await work('wf-status',async()=>{profileCase=await api('case',{case_id:id});$('staff-profile-content').innerHTML=fields('edit',profileCase.profile);$('staff-profile-status').textContent='';show('staff-profile');});}
};
$('save-staff-profile').onclick=()=>work('staff-profile-status',async()=>{const values=profile('edit');if(!values.given_names||!values.first_surname)throw new Error('Completa nombre y primer apellido.');await api('update',{operation_id:uid(),case_id:profileCase.case_id,expected_version:profileCase.version,...values});$('staff-profile').close();say('Nueva versión personal guardada. Requiere confirmación de la persona.');changed();});
api('bootstrap').then(b=>{csrf=b.csrf_token;director=b.director;if(director){$('add-member').disabled=false;$('add-member').onclick=()=>{if(!busy)start();};}}).catch(e=>say(e.message));
})();
