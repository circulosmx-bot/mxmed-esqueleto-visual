<?php declare(strict_types=1);if(empty($staffWorkflowReady)){http_response_code(503);exit;} ?>
<?php if(!empty($staffWorkflowApplicant)): ?><link rel="stylesheet" href="/assets/css/media-review-inbox.css"><?php endif; ?>
<details class="admission-requests panel" id="admission-requests" <?php if(!empty($staffWorkflowApplicant))echo 'open'; ?>>
<summary>Solicitudes de incorporación</summary>
<ol class="wizard-steps"><li>Persona</li><li>Documentos</li><li>Revisión</li><li>Admisión</li></ol>
<p id="wf-status" role="status" aria-live="polite">Validando sesión…</p>
<label class="wizard-field">Solicitud <select id="wf-cases"><option value="">Seleccionar</option></select></label><button id="wf-refresh" type="button">Actualizar</button>
<details class="admission-section" open><summary>DATOS PERSONALES</summary>
<div class="admission-fields"><label class="wizard-field">Nombre(s) <input id="wf-given_names" maxlength="120"></label><label class="wizard-field">Primer apellido <input id="wf-first_surname" maxlength="120"></label><label class="wizard-field">Segundo apellido <input id="wf-second_surname" maxlength="120"></label>
<label class="wizard-field">Estado <input id="wf-operation_state" maxlength="120"></label><label class="wizard-field">Ciudad <input id="wf-operation_city" maxlength="120"></label>
</div><div id="wf-applicant" hidden><button id="wf-save" type="button">Guardar correcciones</button><button id="wf-confirm" type="button">Confirmar mis datos</button>
<details><summary>Aceptar invitación</summary><label class="wizard-field">Solicitud <input id="wf-inv-case"></label><label class="wizard-field">Invitación <input id="wf-inv-id"></label><label class="wizard-field">Código privado <input id="wf-token" type="password" autocomplete="off"></label><button id="wf-accept-invite" type="button">Vincular mi cuenta</button></details></div>
</details>
<details class="admission-section" open><summary>IDENTIDAD Y DOCUMENTACIÓN</summary>
<div id="wf-upload" hidden><label class="wizard-field">Documento <select id="wf-kind"><option value="INE">INE: anverso y reverso</option><option value="PASSPORT">Pasaporte mexicano: página biográfica</option></select></label>
<label class="wizard-field">Justificación <input id="wf-justification" maxlength="300" value="Verificación inicial de identidad"></label>
<label class="wizard-field">Primera cara <input id="wf-front" type="file" accept="image/jpeg,image/png"></label><label class="wizard-field">Reverso INE <input id="wf-back" type="file" accept="image/jpeg,image/png"></label>
<button id="wf-add-document" type="button">Añadir documento al envío</button><ul id="wf-documents"></ul>
<label class="wizard-field">Retrato opcional <input id="wf-portrait" type="file" accept="image/jpeg,image/png"></label><button id="wf-upload-portrait" type="button">Guardar retrato privado</button>
<p id="wf-legal">La confirmación corresponde a la versión de datos y documentos presentada.</p><label class="ack-row"><input id="wf-consent" type="checkbox">Confirmo personalmente esta versión de datos y documentos.</label><button id="wf-submit" type="button">Enviar a revisión</button></div>
<p id="wf-review-state"></p><div id="wf-pages"></div>

</details><p>La admisión crea sólo la relación Advisor. Los permisos se conceden individualmente en Acceso y funciones; nombrar Master requiere su flujo independiente.</p>
</details>
