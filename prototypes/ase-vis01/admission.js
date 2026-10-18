/* ASE-ADM01: page-memory fixtures only. No admission, account, storage or media writer. */
(() => {
  'use strict';
  const $=id=>document.getElementById(id);
  const esc=value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const sample='<p class="admission-note"><strong>PROPUESTO · SIN CONECTAR.</strong> Sólo personas y documentos de ejemplo. No acredita identidad, admisión ni seguridad de backend.</p>';
  const records=new Map();
  const existing={aurora:['Aurora','Naranjo'],bruno:['Bruno','Lago'],cora:['Cora','Vela'],diego:['Diego','Roble'],elisa:['Elisa','Monte'],mar:['Mar','Solano'],lucia:['Lucía','Castaño'],tomas:['Tomás','Niebla']};
  const full=c=>[c.given,c.first,c.second].filter(Boolean).join(' ');
  function fixture(id,given,first,category='ADVISOR',scenario='new',path='remote') {
    return {id,given,first,second:'',name:[given,first].join(' '),email:`${id}@example.test`,mobile:'TEL-EJEMPLO',city:'Ciudad de ejemplo',category,scenario,path,inviter:'Dirección ficticia',requested:[],photo:false,doc:'missing',version:1,viewed:0,confirmed:false,ack:false,submitted:false,review:'Sin presentar',admission:'Sin decisión',clarification:'',history:[],reviewed:[],submittedVersions:[],subjectAccount:id,priorStaff:false};
  }
  records.set('remote-demo',fixture('remote-demo','Nora','Albor'));
  const clarification=fixture('clarification-demo','Lina','Sauce','ADVISOR','existing');Object.assign(clarification,{doc:'unreadable',submitted:true,confirmed:true,ack:true,review:'Lista para revisión'});records.set(clarification.id,clarification);
  records.set('master-demo',fixture('master-demo','Elio','Prado','MASTER_ADMIN'));
  const selfCase=fixture('self-demo','Lucía','Castaño','MASTER_ADMIN','existing');selfCase.subjectAccount='lucia';records.set(selfCase.id,selfCase);
  function profileRecord(detail) {
    const id=detail.dataset.memberId;
    if(!records.has(id)) {
      const names=existing[id]||[detail.dataset.memberName,''];
      const c=fixture(id,names[0],names[1],detail.dataset.category,'existing','assisted');c.priorStaff=true;c.photo=id==='aurora';c.name=detail.dataset.memberName;records.set(id,c);
    }
    const c=records.get(id);c.category=detail.dataset.category;return c;
  }
  const role=()=>$('authority-view').value;
  const canPreviewApplicant=c=>!peer() && (role()==='director'||(role()==='master' && c.category==='ADVISOR'));
  const peer=()=>$('admission-peer-view').checked;
  function reviewAllowed(c) {
    if(peer()||c.subjectAccount===(role()==='director'?'director-self':'lucia'))return false;
    if(role()==='director')return c.id!=='director-self';
    return role()==='master' && $('admission-master-review').checked && c.category==='ADVISOR' && c.id!=='lucia';
  }
  function denial(c) {
    if(peer())return 'Vista de asesor: no puedes consultar la evidencia de identidad de otras personas.';
    if(role()!=='director' && c.subjectAccount==='lucia')return 'No puedes aprobar tu propia incorporación.';
    if(c.category==='MASTER_ADMIN' && role()!=='director')return 'La propuesta de Master corresponde exclusivamente al Director.';
    return 'Revisión de documentos no autorizada en este escenario. Gestionar permisos no concede revisar identidad.';
  }
  const portrait=(c,large=false)=>c.photo?`<img src="/portrait-example.svg" alt="Retrato ilustrado de ejemplo, sin persona real" width="${large?64:38}" height="${large?64:38}">`:`<span aria-label="Sin retrato; iniciales ficticias">${esc((c.given[0]||'')+(c.first[0]||''))}</span>`;
  const row=(label,value,attr='')=>`<div><dt>${label}</dt><dd ${attr}>${esc(value)}</dd></div>`;
  function statuses(c) {
    const account=c.priorStaff?'Activa · fixture previa':c.scenario==='existing'?'Existente · autenticación legítima por confirmar':'Nueva · registro Identity pendiente';
    return `<dl class="admission-status-grid">${row('Cuenta y credencial',account,'data-status-account')}${row('Correo',c.scenario==='existing'?'Verificado · estado ficticio del escenario':'Pendiente de verificación','data-status-email')}${row('Móvil','Sin verificar · sin envío SMS','data-status-phone')}${row('Revisión de identidad',c.review,'data-status-review')}${row('Decisión de admisión',c.admission,'data-status-admission')}${row('Relación staff',c.priorStaff?'Fixture previa; no modificada':'No creada','data-status-staff')}${row('Acceso operativo',c.priorStaff?'Fixture previa; consultar indicador canónico del ejemplo':'Sin acceso operativo','data-status-access')}${row('MFA','Pendiente según acción; no configurado aquí','data-status-mfa')}${row('Funciones y condiciones comerciales','Sin activación nueva; ASE-COM01 y D01–D08 intactos','data-status-commercial')}</dl>`;
  }
  const requests=document.createElement('details');requests.id='admission-requests';requests.className='admission-requests panel';$('workspace-columns').before(requests);
  const controls=document.createElement('div');controls.className='admission-review-controls';controls.innerHTML='<label class="ack-row" for="admission-master-review"><input id="admission-master-review" type="checkbox"> Simular autorización específica de revisión/admisión de asesores para Master · propuesta</label><label class="ack-row" for="admission-peer-view"><input id="admission-peer-view" type="checkbox"> Simular consulta de un asesor sin permiso para revisar a sus pares</label><p class="group-note">Escenarios de diseño, independientes de los grants de R2. La capacidad exacta aún requiere aprobación.</p>';$('review-controls-placeholder')?.remove();document.querySelector('.review-controls').append(controls);
  const review=document.createElement('dialog');review.id='admission-review';review.setAttribute('aria-labelledby','admission-review-title');document.body.append(review);
  const applicant=document.createElement('dialog');applicant.id='applicant-preview';applicant.setAttribute('aria-labelledby','applicant-preview-title');document.body.append(applicant);
  const documentView=document.createElement('dialog');documentView.id='admission-document';documentView.setAttribute('aria-labelledby','admission-document-title');document.body.append(documentView);
  const openReview=window.ASE_VIS01_UI.setupDialog(review),openApplicant=window.ASE_VIS01_UI.setupDialog(applicant),openDocument=window.ASE_VIS01_UI.setupDialog(documentView);
  let currentReview=null,currentApplicant=null,returnReview='',returnApplicant='';
  function restoreEntry(dialog,selector){if(dialog.open)return;const target=document.querySelector(selector);if(target&&!target.disabled&&target.getClientRects().length)target.focus();else requests.querySelector('summary')?.focus();}
  review.addEventListener('close',()=>restoreEntry(review,returnReview));applicant.addEventListener('close',()=>restoreEntry(applicant,returnApplicant));
  function renderRequests() {
    const cases=[...records.values()].filter(c=>!c.priorStaff);
    const opened=requests.open;
    requests.innerHTML=`<summary>Solicitudes de incorporación <span class="muted">${cases.length} ejemplos</span></summary>${sample}<p class="admission-note">Una solicitud reúne preparación asistida o finalización remota preautorizada. Aquí no hay invitaciones enviadas, enlaces, tokens ni archivos reales.</p><div class="admission-request-list">${cases.map(c=>`<article class="admission-request"><div><strong>${esc(c.name)}</strong><span>${c.category==='MASTER_ADMIN'?'Master propuesto':'Asesor propuesto'} · ${c.path==='remote'?'remota preautorizada':'asistida'} · ${esc(c.admission==='Sin decisión'?c.review:c.admission)}</span></div><div class="admission-request-actions"><button type="button" data-admission-case="${esc(c.id)}" ${reviewAllowed(c)?'':'disabled'}>Revisar ejemplo</button><button type="button" data-applicant-case="${esc(c.id)}" ${canPreviewApplicant(c)?'':'disabled'}>Vista del solicitante · ejemplo</button></div>${reviewAllowed(c)?'':`<p class="admission-note">${esc(denial(c))}</p>`}</article>`).join('')}</div>`;
    requests.open=opened;
    requests.querySelectorAll('[data-admission-case]').forEach(b=>b.onclick=()=>showReview(records.get(b.dataset.admissionCase),b));
    requests.querySelectorAll('[data-applicant-case]').forEach(b=>b.onclick=()=>showApplicant(records.get(b.dataset.applicantCase),b));
  }
  function decorateDirectory() {
    document.querySelectorAll('[data-member]').forEach(button=>{
      const id=button.dataset.member,names=existing[id];let c=records.get(id);
      if(!c && names){c=fixture(id,...names);c.photo=id==='aurora';}
      if(!c)return;
      const avatar=button.querySelector('.avatar');avatar.innerHTML=portrait(c);avatar.classList.add('admission-thumb');
      button.querySelector('.advisor-name').textContent=c.name;
      let meta=button.querySelector('.admission-directory-meta');if(!meta){meta=document.createElement('span');meta.className='admission-directory-meta';button.querySelector('.advisor-card-copy').append(meta);}meta.textContent=`${names&&['lucia','tomas'].includes(id)?'Master':c.category==='MASTER_ADMIN'?'Master propuesto':'Asesor'} · ${c.priorStaff||names?'revisión interna no evaluada':c.admission==='Sin decisión'?'solicitud pendiente':c.admission}`;
    });
  }
  const personalHTML=c=>`<summary>DATOS PERSONALES</summary>${sample}<dl class="commercial-terms">${row('Nombre(s)',c.given)}${row('Primer Apellido',c.first||'Pendiente de completar')}${row('Segundo Apellido',c.second||'No indicado; si aplica')}${row('Correo / verificación',`${c.email} · ${c.scenario==='existing'?'verificado en el escenario ficticio':'pendiente'}`)}${row('Móvil / verificación','TEL-EJEMPLO · sin verificar')}${row('Ciudad / estado',c.city||'No indicado')}${row('Procedencia de los datos',c.confirmed?'Confirmación del solicitante ficticio registrada en memoria':'Preparados por administrador; confirmación personal pendiente')}${row('Retrato interno',c.photo?'Ilustración local de ejemplo, separada del documento':'Sin retrato; iniciales neutras')}</dl>`;
  function decorateDetail() {
    const detail=$('advisor-detail');if(!$('capabilities-form') || $('admission-personal'))return;
    const c=profileRecord(detail),heading=detail.querySelector('.detail-heading-row>div:first-child');
    const intro=document.createElement('div');intro.className='admission-heading-person';
    const picture=document.createElement('div');picture.className='admission-portrait';picture.innerHTML=portrait(c,true);
    const copy=document.createElement('div');while(heading.firstChild)copy.append(heading.firstChild);intro.append(picture,copy);heading.append(intro);
    const contact=document.createElement('div');contact.className='admission-header-contact';contact.innerHTML=`<p>${esc(c.email)} · TEL-EJEMPLO <span class="muted">(sin verificar)</span></p><p>${esc(c.city||'Ciudad / estado sin indicar')}</p><div class="admission-indicators"><span>Identidad: ${esc(c.review)}</span><span>Acceso: ${esc(c.priorStaff?detail.dataset.stage:'sin acceso operativo')}</span></div><p class="admission-note">Perfil ficticio; los estados de cuenta/verificación no se consultan en Identity.</p>`;heading.append(contact);
    const personal=document.createElement('details');personal.id='admission-personal';personal.className='admission-section';personal.innerHTML=personalHTML(c);
    const identity=document.createElement('details');identity.id='admission-identity';identity.className='admission-section';identity.innerHTML=`<summary>IDENTIDAD Y DOCUMENTACIÓN</summary>${sample}<p class="admission-note">${esc(c.review)}. El directorio no concede lectura de documentos. Una revisión visual no es consulta a un registro oficial ni garantía de autenticidad.</p><button type="button" id="member-admission-review" ${reviewAllowed(c)?'':'disabled'}>Ver revisión interna · ejemplo</button>${reviewAllowed(c)?'':`<p class="admission-note">${esc(denial(c))}</p>`}`;
    const form=$('capabilities-form');form.before(personal,identity);
    const access=document.createElement('details');access.id='admission-access';access.className='admission-access';access.open=true;form.before(access);access.innerHTML='<summary>ACCESO Y FUNCIONES</summary>';access.append(form);
    detail.querySelector('.history>summary').textContent='HISTORIAL';
    $('member-admission-review').onclick=e=>showReview(c,e.currentTarget);
  }
  function refresh() {
    window.ASE_VIS01_PRESENTATION_NAMES=Object.freeze(Object.fromEntries([...records].map(([id,c])=>[id,c.name])));
    renderRequests();decorateDirectory();
    // Update fictional presentation only; the operational permission matrix is retained.
    const detail=$('advisor-detail');const c=records.get(detail.dataset.memberId);
    if(c && $('admission-personal')) {
      const header=detail.querySelector('.admission-header-contact');header.querySelector('.admission-indicators').firstElementChild.textContent=`Identidad: ${c.review}`;
      $('detail-title').textContent=c.name;detail.querySelector('.admission-portrait').innerHTML=portrait(c,true);
      $('admission-personal').innerHTML=personalHTML(c);
      $('admission-identity').querySelector('p.admission-note:nth-of-type(2)').textContent=`${c.review}. El directorio no concede lectura de documentos. Una revisión visual no es consulta oficial ni garantía de autenticidad.`;
      $('member-admission-review').disabled=!reviewAllowed(c);
    }
  }
  function showReview(c,opener) {
    if(!reviewAllowed(c))return;
    returnReview=opener.id?`#${opener.id}`:`[data-admission-case="${c.id}"]`;opener.focus();currentReview=c;renderReview();openReview();$('admission-review-title').focus();
  }
  function rememberSubmitted(c) {
    if(c.submitted && !c.submittedVersions.some(v=>v.version===c.version))c.submittedVersions.push({version:c.version,name:full(c),document:c.doc,photo:c.photo});
  }
  function evidenceHistory(c) {
    rememberSubmitted(c);
    return `<h3>Versiones y comprobaciones del caso · ejemplo</h3><p data-evidence-version="${c.version}">Versión presentada: ${c.version} · ${c.doc==='readable'?'legible en el ejemplo':c.doc==='unreadable'?'ilegible en el ejemplo':'sin documento'}</p><ol class="admission-history">${c.submittedVersions.map(v=>`<li data-submitted-version="${v.version}">Presentada v${v.version} · ${esc(v.name)} · documento ${v.document==='unreadable'?'ilegible':'legible en el ejemplo'}; retrato ${v.photo?'incluido':'sin indicar'}. Metadatos de ejemplo conservados.</li>`).join('')}${c.reviewed.map(v=>`<li data-reviewed-version="${v.version}">Revisada v${v.version} · ${esc(v.name)} · retrato separado ${v.photo?'incluido':'sin indicar'} · ${esc(v.reviewer)} · fecha ficticia de revisión. Método: revisión visual de ejemplo; legibilidad y coherencia declarada. Sin consulta oficial ni garantía de autenticidad.</li>`).join('')}${c.history.map(h=>`<li>${esc(h)}</li>`).join('')||'<li>Aún no hay comprobaciones registradas.</li>'}</ol>`;
  }
  function renderReview() {
    const c=currentReview;if(!reviewAllowed(c)){review.close();return;}
    const ready=c.review!=='Aclaración requerida' && c.admission!=='Rechazada' && c.doc==='readable' && c.submitted && c.confirmed && c.ack;
    review.innerHTML=`<h2 id="admission-review-title" tabindex="-1">Revisión interna · ${esc(c.name)}</h2>${sample}<p class="admission-note">Un revisor humano autorizado es suficiente. Escenario ${role()==='director'?'Director':'Master con autorización de revisión propuesta'}; no es autoridad real. No se requieren biometría, liveness ni doble revisión.</p>${statuses(c)}<h3>Documentación restringida del caso</h3><p>Tipo y emisor: por ratificar. No se capturan identificadores oficiales ni se presume vigencia.</p><p>Documento: ${c.doc==='missing'?'pendiente de aportar':c.doc==='unreadable'?'ilegible · requiere aclaración':'legible sólo en el escenario ficticio'} · versión ${c.version}.</p><button id="review-document" type="button" ${c.doc==='missing'?'disabled':''}>Consultar DOCUMENTO DE EJEMPLO</button><label class="ack-row" for="review-factual"><input id="review-factual" type="checkbox" ${ready?'':'disabled'}> He revisado la versión y comprobado visualmente legibilidad y coherencia de los datos declarados, sólo en el ejemplo.</label><p class="admission-note">No verifica autenticidad documental ni registro gubernamental. La revisión requiere el caso, sujeto y versión exactos; gestionar grants no la autoriza.</p><p class="admission-note" id="review-feedback" role="status" aria-live="polite"></p><div class="admission-actions"><button id="review-clarification" type="button" ${c.submitted?'':'disabled'}>Solicitar aclaración · ejemplo</button><button id="review-complete" type="button" disabled>Completar revisión · ejemplo</button><button id="review-approve" type="button" ${c.review==='Revisión interna completada'&&c.admission!=='Rechazada'?'':'disabled'}>Aprobar admisión · dejar configuración pendiente</button><button id="review-reject" type="button">Rechazar solicitud · ejemplo</button></div><p class="admission-note">Aprobar no crea relación staff, permisos, consentimientos legales, MFA ni condiciones económicas. El acceso queda pendiente de los pasos canónicos y de las políticas aún sin ratificar.</p>${evidenceHistory(c)}<div class="dialog-actions"><button id="close-admission-review" type="button">Cerrar revisión</button></div>`;
    if(review.open)$('admission-review-title').focus();
    $('close-admission-review').onclick=()=>review.close();
    $('review-document').onclick=()=>{if(!reviewAllowed(c))return;documentView.innerHTML=`<h2 id="admission-document-title" tabindex="-1">Documento restringido · ejemplo</h2><div id="evidence-placeholder" class="evidence-placeholder" data-evidence-version="${c.version}"><strong>DOCUMENTO DE EJEMPLO</strong><p>Sin fotografía de identidad, datos personales, números, sellos, firma ni códigos.</p><p>Versión ${c.version} · ${c.doc==='unreadable'?'legibilidad insuficiente en el escenario':'contenido ficticio legible'}</p></div><p class="admission-note">Este marcador no es una identificación oficial. El retrato del equipo es un archivo ilustrado independiente.</p><button id="close-admission-document" type="button">Cerrar documento de ejemplo</button>`;$('review-document').focus();c.viewed=c.version;openDocument();$('admission-document-title').focus();$('close-admission-document').onclick=()=>documentView.close();};
    $('review-factual').onchange=e=>{$('review-complete').disabled=!(e.target.checked && ready && c.viewed===c.version);};
    $('review-clarification').onclick=()=>{if(!reviewAllowed(c))return;c.review='Aclaración requerida';c.admission='Sin decisión';c.clarification='Necesitamos un documento legible y confirmar los datos de esta versión de ejemplo.';c.history.push(`Aclaración solicitada sobre v${c.version}; sin decisión de admisión.`);renderReview();refresh();};
    $('review-complete').onclick=()=>{if(!reviewAllowed(c)||!ready||c.viewed!==c.version||!$('review-factual').checked)return;c.review='Revisión interna completada';if(!c.reviewed.some(v=>v.version===c.version))c.reviewed.push({version:c.version,name:full(c),photo:c.photo,document:c.doc,reviewer:role()==='director'?'Dirección ficticia':'Lucía Castaño · Master ficticia'});c.history.push(`Revisión visual completada para v${c.version}; no activa acceso.`);renderReview();refresh();};
    $('review-approve').onclick=()=>{if(!reviewAllowed(c)||c.review!=='Revisión interna completada'||c.admission==='Rechazada')return;c.admission='Aprobada · configuración personal pendiente';c.history.push('Decisión ficticia de admisión aprobada; cuenta/aceptaciones/MFA/grants y acceso operativo siguen independientes.');renderReview();refresh();};
    $('review-reject').onclick=()=>{if(!reviewAllowed(c))return;c.admission='Rechazada';c.history.push('Decisión de ejemplo: rechazada. El solicitante recibe un mensaje acotado, sin notas internas.');renderReview();refresh();};
  }
  function showApplicant(c,opener) {
    if(!canPreviewApplicant(c))return;
    returnApplicant=`[data-applicant-case="${c.id}"]`;opener.focus();currentApplicant=c;renderApplicant();openApplicant();$('applicant-preview-title').focus();
  }
  function renderApplicant() {
    const c=currentApplicant;let photoDraft=c.photo;
    const applicantVersions=`<h3>Mis versiones · ejemplo</h3><p data-evidence-version="${c.version}">Versión actual: ${c.version}</p><ol class="admission-history">${c.reviewed.map(v=>`<li data-reviewed-version="${v.version}">Versión ${v.version} conservada. Revisión interna anterior; el cambio nuevo requiere otra revisión.</li>`).join('')||'<li>Sin versiones revisadas.</li>'}</ol>`;
    applicant.innerHTML=`<h2 id="applicant-preview-title" tabindex="-1">Mi solicitud de incorporación · ejemplo</h2>${sample}<p class="admission-note">Vista sólo de ${esc(c.name)} y de su solicitud. En el futuro requerirá autenticación legítima y vínculo al caso; un enlace de envío no concede acceso persistente a documentos ni autoridad staff.</p><dl class="commercial-terms">${row('Categoría propuesta · no editable',c.category==='MASTER_ADMIN'?'Administrador maestro · sólo Director':'Asesor')}${row('Preparada por · no editable',c.inviter)}${row('Funciones solicitadas · no editables',c.requested.length?`${c.requested.length} propuestas; ninguna efectiva`:'Ninguna efectiva')}${row('Caso de cuenta',c.scenario==='existing'?'Cuenta existente; autenticación y matching seguro pendientes. No restablece ni fusiona por email.':'Cuenta nueva; Identity deberá gestionar contraseña propia, verificaciones y aceptación legal. No crea una cuenta aquí.')}</dl>${c.admission==='Rechazada'?'<p class="pending-notice" id="applicant-public-outcome">Tu solicitud no fue aprobada. Puedes consultar el procedimiento de revisión que se autorice. No se muestran notas internas ni evidencia de terceros.</p>':c.clarification?`<p class="pending-notice" id="applicant-clarification">${esc(c.clarification)}</p>`:''}<form id="applicant-form" novalidate><div class="admission-fields"><label class="wizard-field" for="applicant-given">Nombre(s) ficticios<input id="applicant-given" autocomplete="off" maxlength="60" value="${esc(c.given)}"></label><label class="wizard-field" for="applicant-first">Primer Apellido ficticio<input id="applicant-first" autocomplete="off" maxlength="60" value="${esc(c.first)}"></label><label class="wizard-field" for="applicant-second">Segundo Apellido · si aplica<input id="applicant-second" autocomplete="off" maxlength="60" value="${esc(c.second)}"></label><label class="wizard-field" for="applicant-mobile">Móvil de ejemplo · sin verificar<input id="applicant-mobile" value="TEL-EJEMPLO" readonly></label></div><p class="admission-note">Correo: ${esc(c.email)} · ${c.scenario==='existing'?'verificado sólo en el escenario ficticio':'pendiente de verificación'}. No se envían email o SMS. No se pide contraseña.</p><div class="admission-photo-example"><div class="admission-portrait" id="applicant-portrait">${portrait(c,true)}</div><div><button id="applicant-photo" type="button">${c.photo?'Retirar ilustración de retrato':'Usar retrato ilustrado de ejemplo'}</button><p class="admission-note">Retrato interno, separado del documento. Sólo fixture incluida; sin archivos propios ni cámara.</p></div></div><label class="wizard-field" for="applicant-document">Estado del DOCUMENTO DE EJEMPLO<select id="applicant-document"><option value="missing">Sin documento</option><option value="unreadable">Documento de ejemplo ilegible</option><option value="readable">Documento de ejemplo legible</option></select></label><p class="admission-note">Catálogo documental, emisor, vigencia y retención pendientes de ratificar. No se carga ningún documento real ni se extrae su fotografía.</p><label class="ack-row" for="applicant-confirm"><input id="applicant-confirm" type="checkbox" ${c.confirmed?'checked':''}> Confirmo mis datos ficticios y su procedencia en este ejemplo.</label><label class="ack-row" for="applicant-ack"><input id="applicant-ack" type="checkbox" ${c.ack?'checked':''}> He leído las indicaciones de esta simulación. No constituye aceptación de términos legales reales.</label><p id="applicant-feedback" class="admission-note" role="status" aria-live="polite"></p><button id="applicant-submit" type="submit" ${c.admission==='Rechazada'?'disabled':''}>Presentar versión para revisión · simulado</button></form><p class="admission-note">Presentar datos o un documento no equivale a revisión aprobada, admisión ni acceso ACTIVE. Las aceptaciones/versiones legales aplicables y el proceso real siguen pendientes.</p>${statuses(c)}${applicantVersions}${c.reviewed.length?'<button id="applicant-material-change" type="button">Preparar cambio material · nueva versión de ejemplo</button>':''}<div class="dialog-actions"><button id="close-applicant-preview" type="button">Cerrar vista del solicitante</button></div>`;
    $('applicant-document').value=c.doc;$('close-applicant-preview').onclick=()=>applicant.close();
    $('applicant-photo').onclick=()=>{photoDraft=!photoDraft;$('applicant-portrait').innerHTML=portrait({...c,photo:photoDraft},true);$('applicant-photo').textContent=photoDraft?'Retirar ilustración de retrato':'Usar retrato ilustrado de ejemplo';};
    $('applicant-form').onsubmit=e=>{
      e.preventDefault();if(c.admission==='Rechazada')return;
      const given=$('applicant-given').value.trim(),first=$('applicant-first').value.trim(),second=$('applicant-second').value.trim(),doc=$('applicant-document').value;
      if(!given||!first||doc==='missing'||!$('applicant-confirm').checked||!$('applicant-ack').checked){$('applicant-feedback').textContent='Completa nombre(s), Primer Apellido, documento de ejemplo y confirmaciones. No se ha presentado una versión.';return;}
      rememberSubmitted(c);if(c.submitted)c.version++;
      Object.assign(c,{given,first,second,doc,photo:photoDraft,name:[given,first,second].filter(Boolean).join(' '),confirmed:true,ack:true,submitted:true,review:'Lista para revisión',admission:'Sin decisión',viewed:0,clarification:''});c.history.push(`Solicitante ficticio confirma y presenta v${c.version}; sin activación de cuenta, staff o funciones.`);rememberSubmitted(c);renderApplicant();refresh();$('applicant-feedback').textContent='Versión presentada sólo en memoria. Revisión y decisión pendientes; sin acceso operativo.';$('applicant-submit').focus();
    };
    if($('applicant-material-change'))$('applicant-material-change').onclick=()=>{rememberSubmitted(c);c.version++;c.review='Revisión nueva requerida';c.admission='Sin decisión';c.confirmed=false;c.ack=false;c.submitted=false;c.viewed=0;c.doc='missing';c.history.push(`Cambio material propuesto v${c.version}; se conserva la revisión anterior y requiere nueva revisión.`);renderApplicant();refresh();$('applicant-preview-title').focus();};
  }
  function authorityChanged() {
    if(documentView.open)documentView.close();if(review.open)review.close();if(applicant.open)applicant.close();refresh();
  }
  $('admission-master-review').onchange=authorityChanged;$('admission-peer-view').onchange=authorityChanged;
  document.addEventListener('ase-vis01-directory-rendered',decorateDirectory);
  document.addEventListener('ase-vis01-detail-rendered',()=>{if(currentReview&&!reviewAllowed(currentReview)){if(documentView.open)documentView.close();if(review.open)review.close();}if(currentApplicant&&!canPreviewApplicant(currentApplicant)&&applicant.open)applicant.close();decorateDetail();refresh();});
  document.addEventListener('ase-vis01-admission-proposed',e=>{const d=e.detail,c=fixture(d.id,d.given,d.first,d.category,d.scenario,d.path);Object.assign(c,d);records.set(c.id,c);c.history.push(`Preparación ${d.path==='remote'?'remota preautorizada propuesta, sin envío':'asistida'} por ${d.inviter}; datos aún no confirmados por el solicitante.`);renderRequests();});
  renderRequests();decorateDirectory();decorateDetail();
})();
