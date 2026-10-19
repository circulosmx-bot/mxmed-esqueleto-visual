<?php declare(strict_types=1);if(empty($staffWorkflowReady)){http_response_code(503);exit;}
if(!empty($staffWorkflowApplicant)){require __DIR__.'/staff-applicant.php';return;} ?>
<p id="wf-status" role="status" aria-live="polite"></p>
<dialog id="onboarding" aria-labelledby="onboarding-title" aria-describedby="onboarding-description">
<h2 id="onboarding-title" tabindex="-1">Agregar integrante</h2><p id="onboarding-description">Prepara la incorporación. La persona confirmará sus datos y presentará sus documentos desde su propia cuenta.</p>
<ol class="wizard-steps" aria-label="Pasos de incorporación"><li>Persona</li><li>Categoría</li><li>Permisos</li><li>Revisión</li></ol>
<div id="onboarding-content"></div><p id="onboarding-feedback" role="status" aria-live="polite"></p>
<button id="check-onboarding" type="button" hidden>Consultar estado de preparación</button>
<div class="dialog-actions"><button id="cancel-onboarding" type="button">Cerrar</button><button id="save-onboarding" type="button">Guardar borrador</button><button id="previous-onboarding" type="button">Anterior</button><button id="next-onboarding" type="button" class="primary">Continuar</button></div>
</dialog>
<dialog id="staff-case" aria-labelledby="staff-case-title"><h2 id="staff-case-title" tabindex="-1">Solicitud de incorporación</h2><div id="staff-case-content"></div><p id="staff-case-status" role="status" aria-live="polite"></p><div class="dialog-actions"><button id="close-staff-case" type="button">Cerrar</button></div></dialog>
<dialog id="staff-profile" aria-labelledby="staff-profile-title"><h2 id="staff-profile-title">Editar datos personales</h2><p>Se conservará una nueva versión preparada por Dirección. La persona deberá confirmar los cambios; la revisión de identidad anterior puede requerir actualización. Esto no cambia la cuenta, las credenciales ni los permisos.</p><div id="staff-profile-content"></div><p id="staff-profile-status" role="status"></p><div class="dialog-actions"><button id="cancel-staff-profile" type="button">Cancelar</button><button id="save-staff-profile" type="button" class="primary">Guardar nueva versión</button></div></dialog>
<dialog id="staff-stepup" aria-labelledby="staff-stepup-title"><h2 id="staff-stepup-title"></h2><p id="staff-stepup-target"></p>
<label class="wizard-field" id="staff-reason-label" hidden>Motivo o corrección solicitada<textarea id="staff-reason" maxlength="500" rows="3"></textarea></label>
<div id="staff-password-stage"><label class="wizard-field">Contraseña actual<input id="staff-password" type="password" autocomplete="current-password"></label><button id="staff-challenge" type="button" class="primary">Confirmar acción y continuar</button></div>
<div id="staff-code-stage" hidden><label class="wizard-field">Código del autenticador<input id="staff-code" inputmode="numeric" maxlength="6" autocomplete="one-time-code"></label><button id="staff-execute" type="button" class="primary">Verificar y ejecutar</button></div>
<p id="staff-stepup-status" role="status" aria-live="polite"></p><div class="dialog-actions"><button id="cancel-staff-stepup" type="button">Cancelar</button></div></dialog>
