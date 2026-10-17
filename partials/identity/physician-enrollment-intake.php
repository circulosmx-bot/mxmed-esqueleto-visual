<?php
/** Original onboarding layout; exclusively the supplied enrollment composition. */
$enrollment=$state['enrollment'];$app=$enrollment['application'];
$return=$enrollment['return_path']??'/public/identity/perfil-medico.php?enrollment=resume';
$login='/public/identity/acceso.php?next='.rawurlencode($return);
$register='/public/identity/crear-cuenta.php?next='.rawurlencode($return);
?>
<ol class="onboarding-steps" aria-label="Pasos de incorporación"><li>Inicio de solicitud</li><li>Cuenta y correo</li><li>Información profesional</li><li>Verificación y revisión pendientes</li></ol>
<div class="onboarding-grid" data-enrollment-panel>
<section class="auth-card"><h2><?= ($app['initial_origin']??($enrollment['mode']==='claim'?'CLAIM_EXISTING_PROFILE':'REQUEST_NEW_PROFILE'))==='CLAIM_EXISTING_PROFILE'?'El perfil que quieres administrar':'Forma parte de México Médico' ?></h2>
<?php if($enrollment['profile_name']!==null): ?><div class="onboarding-profile" data-origin-profile><h3><?= $escape($enrollment['profile_name']) ?></h3></div><p>Este perfil se conserva como el origen de tu solicitud. No tienes que buscarlo de nuevo.</p><?php else: ?><p>Tu solicitud comienza sin un perfil previo. Las posibles coincidencias requerirán revisión; no se creará ni asignará un perfil automáticamente.</p><?php endif; ?>
<?php if($app===null): ?><p>Comienza para conservar este recorrido mientras verificas tu cuenta. Todavía no has enviado información profesional.</p><button class="button" type="button" data-enrollment-start>Comenzar solicitud</button>
<?php elseif(!$enrollment['authenticated']): ?><p>El inicio de tu solicitud se ha conservado temporalmente. Usa tu cuenta personal para continuar. Iniciar sesión no concede acceso médico.</p><p class="auth-links"><a href="<?= $escape($login) ?>" data-enrollment-login>Iniciar sesión y continuar</a><a href="<?= $escape($register) ?>" data-enrollment-register>Crear mi cuenta personal</a></p>
<?php elseif(!$enrollment['bound']): ?><p>Confirma que deseas continuar esta solicitud con la cuenta personal que acabas de identificar.</p><button class="button" type="button" data-enrollment-bind>Continuar esta solicitud con mi cuenta</button>
<?php else: ?><p>Esta solicitud está asociada a tu cuenta personal. Puedes retomarla después de iniciar sesión.</p><p data-enrollment-summary><?= $app['state']==='SUBMITTED'?'Información inicial enviada. Los siguientes pasos de verificación y revisión están pendientes.':'Solicitud pendiente de completar información inicial.' ?></p><?php endif; ?>
<p><a href="<?= $escape($app['original_public_path']??'/profiles/listing.php') ?>">Volver al perfil o al directorio</a></p>
<p>No se ha aprobado una acreditación ni asignado titularidad. La verificación de teléfono, documentos privados y revisión profesional son pasos posteriores; su recepción sigue pendiente de habilitación. Puedes permanecer en el plan Gratuito.</p>
</section>
<section class="auth-card"><h2>Información profesional inicial</h2><p>Estos datos se guardan de forma privada como información declarada; no se consideran verificados ni se publican.</p>
<form class="auth-form" data-enrollment-intake method="post" action="/api/identity/physician-enrollment.php"><fieldset <?= !$enrollment['bound']||!$enrollment['authenticated']||($app['state']??'')==='SUBMITTED'?'disabled':'' ?>><legend>Datos de tu solicitud</legend>
<?php foreach(['given_names'=>['Nombre o nombres',120],'first_surname'=>['Primer Apellido',120],'second_surname'=>['Segundo Apellido (opcional)',120],'professional_license'=>['Cédula profesional declarada',64],'specialty'=>['Especialidad',190],'state'=>['Estado',120],'city'=>['Ciudad o municipio',120]] as $field=>[$label,$limit]): ?>
<div class="field"><label for="enrollment-<?= $escape($field) ?>"><?= $escape($label) ?></label><input id="enrollment-<?= $escape($field) ?>" name="<?= $escape($field) ?>" maxlength="<?= $limit ?>" value="<?= $escape((string)($app['intake'][$field]??'')) ?>" autocomplete="off"></div>
<?php endforeach; ?>
<div class="form-actions"><button class="button button--secondary" type="submit" name="intent" value="save">Guardar información</button><button class="button" type="submit" name="intent" value="submit">Enviar información inicial</button></div>
</fieldset></form>
<p>No adjuntes documentos ni información clínica aquí. Después de enviar, esta versión se conserva sin cambios; no equivale a una aprobación profesional.</p>
</section></div>
