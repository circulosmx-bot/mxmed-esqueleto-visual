<?php if(!empty($state['professional_sources_enabled'])): ?>
<section class="auth-card" data-source-panel hidden><h2>Fuentes profesionales</h2>
<p>Identidad profesional y cédula profesional se verifican antes de continuar la acreditación. Los documentos son privados y no asignan titularidad.</p>
<p data-source-status role="status" aria-live="polite"></p><p data-source-feedback role="alert"></p>
<button type="button" class="button button--secondary" data-source-refresh>Actualizar verificación</button>
<div data-source-files class="onboarding-document-list"></div>
<?php if($staff): ?>
<form class="auth-form" data-source-form="begin"><fieldset disabled><legend>Verificación de fuentes profesionales</legend>
<div class="field"><label for="source-action">Acción sobre este expediente</label><select id="source-action" name="source_action"><option value="COLLECT">Solicitar documentación por verificar</option><option value="AUTHORIZE">Registrar verificación realizada</option><option value="RECOVER">Reautorizar operación interrumpida</option><option value="REVOKE">Retirar autorización de fuentes</option></select></div>
<div data-source-facts hidden><p>Abre ambos documentos. Comprueba las fuentes genuinas mediante el procedimiento indicado. Una carga o cédula declarada no prueba identidad. No existe una consulta automática a un registro externo.</p>
<?php foreach(['given_names'=>'Nombre o nombres verificados','first_surname'=>'Primer Apellido verificado','second_surname'=>'Segundo Apellido, si corresponde','license_number'=>'Texto original de la cédula verificada','professional_area_label'=>'Área profesional verificada','institution_name'=>'Institución emisora verificada','identity_source'=>'Fuente confiable de identidad','identity_reference'=>'Referencia de comprobación de identidad','credential_source'=>'Fuente confiable de cédula','credential_reference'=>'Referencia de comprobación de cédula','verified_at'=>'Fecha real de comprobación (UTC: AAAA-MM-DD HH:MM:SS)'] as $name=>$label): ?>
<div class="field"><label for="source-<?= $name ?>"><?= $label ?></label><input id="source-<?= $name ?>" name="<?= $name ?>" maxlength="<?= in_array($name,['first_surname','second_surname'],true)?120:($name==='license_number'?64:190) ?>" <?= $name==='second_surname'?'':'required' ?>></div>
<?php endforeach; ?>
<?php foreach(['identity'=>'identidad','credential'=>'cédula'] as $key=>$label): ?><div class="field"><label for="source-<?= $key ?>-procedure">Procedimiento de comprobación de <?= $label ?></label><select id="source-<?= $key ?>-procedure" name="<?= $key ?>_procedure"><option value="OFFICIAL_REGISTRY_HUMAN_CHECK">Consulta humana de registro oficial</option><option value="DIRECT_ISSUING_INSTITUTION_CONFIRMATION">Confirmación directa de la institución emisora</option></select></div><?php endforeach; ?>
<label><input type="checkbox" name="relationship_confirmed" required> Revisé la evidencia y comprobé personalmente las fuentes y su relación con este solicitante y perfil.</label></div>
<div class="field"><label for="source-email">Correo de tu cuenta interna</label><input id="source-email" name="email" type="email" autocomplete="username" required></div>
<div class="field"><label for="source-password">Confirma tu contraseña</label><input id="source-password" name="password" type="password" autocomplete="current-password" required></div>
<button type="submit" class="button">Iniciar verificación protegida</button></fieldset></form>
<form class="auth-form" data-source-form="confirm" hidden><fieldset disabled><legend>Confirmar el expediente exacto</legend><p>Esta acción requiere tu autenticador y conserva la evidencia y su historia.</p><div class="field"><label for="source-mfa">Código de tu autenticador</label><input id="source-mfa" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required></div><button type="submit" class="button">Confirmar verificación</button></fieldset></form>
<button class="button" type="button" data-source-execute hidden>Completar registro de fuentes verificadas</button>
<dialog data-source-dialog><h3>Documento privado por verificar</h3><button class="button button--secondary" type="button" data-source-close>Cerrar documento</button><div data-source-preview></div></dialog>
<?php else: ?>
<form class="auth-form" data-source-form="upload"><fieldset disabled><legend>Documentación por verificar</legend><p>Envía cada documento una vez. Si se acepta, se conservará por referencia para la acreditación; cargarlo no significa que haya sido verificado.</p>
<div class="field"><label for="source-kind">Tipo de documento</label><select id="source-kind" name="kind"><option value="OFFICIAL_ID">Identificación oficial</option><option value="PROFESSIONAL_CREDENTIAL">Evidencia de cédula profesional</option></select></div>
<div class="field"><label for="source-file">Documento privado (PDF, JPG o PNG; máximo 8 MiB)</label><input id="source-file" name="document" type="file" accept="application/pdf,image/jpeg,image/png" required></div>
<div class="field"><label for="source-upload-email">Correo de tu cuenta</label><input id="source-upload-email" name="email" type="email" autocomplete="username" required></div>
<div class="field"><label for="source-upload-password">Confirma tu contraseña</label><input id="source-upload-password" name="password" type="password" autocomplete="current-password" required></div><button type="submit" class="button">Guardar documento privado</button></fieldset></form>
<?php endif; ?>
</section>
<?php endif; ?>
