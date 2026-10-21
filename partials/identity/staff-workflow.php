<?php declare(strict_types=1);if(empty($staffWorkflowReady)){http_response_code(503);exit;}
if(!empty($staffWorkflowApplicant)){require __DIR__.'/staff-applicant.php';return;} ?>
<?php
$staffReviewHelp='';
if((getenv('MXMED_STAFF_CONTROL07')==='1'||getenv('MXMED_STAFF_CONTROL06_R2')==='1')&&($teamAuthority['class']??'')==='DIRECTOR'){
 $reviewOwned=realpath((string)getenv('ASE_INT02A_OWNED'));
 $reviewLauncher=realpath(__DIR__.'/../../scripts/'.(getenv('MXMED_STAFF_DOC_VIS02')==='1'?'staff-doc-vis02':(getenv('MXMED_STAFF_DOC_VIS01')==='1'?'staff-doc-vis01':(getenv('MXMED_STAFF_CONTROL07')==='1'?'staff-control07':'staff-control06-r2'))).'-review.py');
 if($reviewOwned&&$reviewLauncher){
  $reviewCommand='python3 '.escapeshellarg($reviewLauncher).' '.escapeshellarg($reviewOwned).' ';
  $staffReviewHelp='<div class="synthetic-review-help"><p>En esta prueba sintética, obtén el código MFA vigente con el comando local indicado en la guía de revisión.</p><details><summary>Ver comandos de esta sesión de revisión</summary><p>Contraseña sintética del Director, al portapapeles:</p><code>'.htmlspecialchars($reviewCommand.'credentials-director',ENT_QUOTES,'UTF-8').'</code><p>Código MFA actual, al portapapeles:</p><code>'.htmlspecialchars($reviewCommand.'mfa-director',ENT_QUOTES,'UTF-8').'</code><p>Ejecuta el comando en tu terminal local. No compartas contraseñas ni códigos. El código debe estar vigente y no haberse usado.</p></details></div>';
 }
}
?>
<template id="staff-review-help"><?= $staffReviewHelp ?></template>

<p id="wf-status" role="status" aria-live="polite"></p><button id="review-reconcile" type="button" hidden>Consultar resultado de revisión pendiente</button>
<dialog id="onboarding" aria-labelledby="onboarding-title" aria-describedby="onboarding-description">
<h2 id="onboarding-title" tabindex="-1">Agregar integrante</h2><p id="onboarding-description">Prepara la incorporación. La persona confirmará sus datos y presentará sus documentos desde su propia cuenta.</p>
<ol class="wizard-steps" aria-label="Pasos de incorporación"><li>Persona</li><li>Categoría</li><li>Permisos</li><li>Revisión</li></ol>
<div id="onboarding-content"></div><p id="onboarding-feedback" role="status" aria-live="polite"></p>
<button id="check-onboarding" type="button" hidden>Consultar estado de preparación</button>
<div class="dialog-actions"><button id="cancel-onboarding" type="button">Cerrar</button><button id="save-onboarding" type="button">Guardar borrador</button><button id="previous-onboarding" type="button">Anterior</button><button id="next-onboarding" type="button" class="primary">Continuar</button></div>
</dialog>
<div id="staff-case-parking" hidden><section id="staff-case" aria-labelledby="staff-case-title"><h2 id="staff-case-title" tabindex="-1">Solicitud de incorporación</h2><div id="staff-case-content"></div><p id="staff-case-status" role="status" aria-live="polite"></p></section></div>
<dialog id="staff-evidence" aria-labelledby="staff-evidence-title"><h2 id="staff-evidence-title">DOCUMENTACIÓN DE IDENTIDAD DEL INTEGRANTE</h2><div id="staff-evidence-context"></div>
<?php if(getenv('MXMED_STAFF_DOC_VIS01')==='1'&&getenv('MXMED_PREVIEW_EXPLICIT')==='1'&&!empty($reviewOwned)): ?><aside class="evidence-synthetic"><strong>DOCUMENTACIÓN SINTÉTICA DE PRUEBA</strong><p>Estas imágenes son artificiales y se utilizan exclusivamente para comprobar el proceso de revisión.</p></aside><?php endif; ?>
<div id="protected-case-pages"></div><p id="staff-evidence-next"></p><div class="dialog-actions"><button id="evidence-back" type="button" hidden>Volver a los documentos</button><button id="close-staff-evidence" type="button" aria-describedby="evidence-close-help">Cerrar documentos</button><span id="evidence-close-help">Cerrar sin registrar revisión</span><button id="ack-staff-evidence" type="button" class="primary">Confirmar revisión de documentos</button></div></dialog>
<dialog id="staff-profile" aria-labelledby="staff-profile-title"><h2 id="staff-profile-title">Editar datos personales</h2><p>Se conservará una nueva versión preparada por Dirección. La persona deberá confirmar los cambios; la revisión de identidad anterior puede requerir actualización. Esto no cambia la cuenta, las credenciales ni los permisos.</p><div id="staff-profile-content"></div><p id="staff-profile-status" role="status"></p><div class="dialog-actions"><button id="cancel-staff-profile" type="button">Cancelar</button><button id="save-staff-profile" type="button" class="primary">Guardar nueva versión</button></div></dialog>
<dialog id="staff-stepup" aria-labelledby="staff-stepup-title"><h2 id="staff-stepup-title"></h2><p id="staff-stepup-target"></p><p id="staff-stepup-account"></p><p id="staff-stepup-explanation"></p><p id="staff-corrections-description" hidden>Indica qué información o documento debe corregir la persona antes de continuar con su incorporación.</p><p id="staff-corrections-channel" hidden>La persona podrá consultar estas indicaciones desde su solicitud de incorporación. No se envía correo ni notificación externa.</p><?= $staffReviewHelp ?>
<label class="wizard-field" id="staff-reason-label" hidden><span id="staff-reason-caption">Motivo o corrección solicitada</span><textarea id="staff-reason" maxlength="500" rows="3"></textarea></label>
<div id="staff-password-stage"><label class="wizard-field"><span id="staff-password-label">Contraseña de tu cuenta</span><input id="staff-password" type="password" autocomplete="current-password"></label><button id="staff-challenge" type="button" class="primary">Continuar al código MFA</button></div>
<div id="staff-code-stage" hidden><label class="wizard-field">Código del autenticador<input id="staff-code" inputmode="numeric" maxlength="6" autocomplete="one-time-code"></label><button id="staff-execute" type="button" class="primary">Verificar y ejecutar</button></div>
<p id="staff-stepup-expiry" hidden></p><p id="staff-stepup-status" role="status" aria-live="polite"></p><div class="dialog-actions"><button id="cancel-staff-stepup" type="button">Cancelar</button></div></dialog>
