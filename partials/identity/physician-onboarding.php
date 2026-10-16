<?php
/** Shared original Identity/staff view. $state and $title come from the guarded page renderer. */
$escape=static fn(string $s):string=>htmlspecialchars($s,ENT_QUOTES,'UTF-8');
?>
<!doctype html><html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title><?= $escape($title) ?> · México Médico</title>
<link rel="stylesheet" href="/public/identity/identity-access.css">
<?php if($staff): ?><link rel="stylesheet" href="/assets/css/media-review-inbox.css"><?php endif; ?>
<link rel="stylesheet" href="/assets/css/physician-onboarding.css">
<script src="/assets/js/identity/physician-onboarding.js" defer></script>
</head><body>
<div class="prototype-shell">
<header class="prototype-header"><a class="prototype-brand" href="/private-app.php"><img src="/assets/mexico-medico.svg" alt="México Médico"></a>
<nav class="onboarding-nav" aria-label="Navegación de cuenta"><?php if($staff): ?><a href="/internal/media-review/index.php">Revisión de medios</a><?php else: ?><a href="/private-app.php">Mi aplicación</a><?php endif; ?><button type="button" class="button button--secondary" data-onboarding-logout>Cerrar sesión</button></nav></header>
<main class="onboarding-main" data-onboarding data-view="<?= $staff?'staff':'physician' ?>">
<section class="auth-card onboarding-intro"><p class="auth-card__eyebrow"><?= $staff?'México Médico · Equipo interno':'Mi perfil profesional' ?></p><h1><?= $escape($title) ?></h1>
<p><?= $staff?'La revisión comprueba la relación entre la cuenta solicitante y el perfil profesional.':'La acreditación comprueba tu relación con el perfil profesional.' ?> La designación como titular se realiza después, mediante una autorización independiente.</p>
<p class="onboarding-notice" data-onboarding-status role="status" aria-live="polite"><?= $escape($state['message']) ?></p>
<p data-onboarding-feedback role="alert" aria-live="assertive"></p>
<a href="/public/identity/acceso.php?next=%2Fpublic%2Fidentity%2Fperfil-medico.php" data-onboarding-login hidden>Iniciar sesión nuevamente</a>
<button class="button button--secondary" type="button" data-onboarding-refresh>Actualizar estado</button>
</section>
<script type="application/json" id="physician-onboarding-state"><?= json_encode($state,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR) ?></script>
<?php if(!$staff): ?>
<ol class="onboarding-steps" aria-label="Pasos de acreditación"><li>Cuenta y correo</li><li>Teléfono</li><li>Perfil y documentos</li><li>Revisión</li><li>Confirmación</li><li>Designación de titular</li></ol>
<div class="onboarding-grid">
<section class="auth-card" aria-labelledby="contact-title"><h2 id="contact-title">1. Cuenta y contacto</h2><p>Tu cuenta tiene el correo verificado.</p><p data-phone-status><?= $state['enabled']?'Verifica tu teléfono para continuar.':'La verificación de teléfono estará disponible cuando se habilite el servicio.' ?></p>
<form method="post" action="/api/identity/physician-onboarding.php" data-onboarding-form="phone_issue" class="auth-form"><fieldset disabled><legend>Recibir un código por SMS</legend>
<div class="field"><label for="onboarding-phone">Teléfono con código de país</label><input id="onboarding-phone" name="phone" type="tel" autocomplete="tel" placeholder="+52…" required></div>
<div class="field"><label for="phone-email">Correo de tu cuenta</label><input id="phone-email" name="email" type="email" autocomplete="username" required></div>
<div class="field"><label for="phone-password">Confirma tu contraseña</label><input id="phone-password" name="password" type="password" autocomplete="current-password" required></div>
<button class="button" type="submit">Enviar código</button></fieldset></form>
<form method="post" action="/api/identity/physician-onboarding.php" data-onboarding-form="phone_verify" class="auth-form" hidden><fieldset disabled><legend>Confirmar teléfono</legend><div class="field"><label for="phone-code">Código de seis dígitos</label><input id="phone-code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required></div><button class="button" type="submit">Verificar teléfono</button></fieldset></form>
</section>
<section class="auth-card" aria-labelledby="claim-title"><h2 id="claim-title">2. Tu perfil profesional</h2><p>Indica la referencia exacta del perfil existente que deseas acreditar. Revisa sus datos antes de confirmar; no se elegirá otro perfil por ti.</p>
<form method="post" action="/api/identity/physician-onboarding.php" class="auth-form" data-onboarding-form="profile_lookup"><fieldset disabled><legend>Buscar el perfil exacto</legend><div class="field"><label for="profile-reference">Referencia del perfil existente</label><input id="profile-reference" name="profile_reference" maxlength="64" autocomplete="off" required aria-describedby="profile-reference-help"><small id="profile-reference-help">Usa la referencia del perfil que deseas reclamar. Si no la conoces, solicítala al equipo de México Médico.</small></div><button class="button button--secondary" type="submit">Consultar perfil</button></fieldset></form>
<div class="onboarding-profile" data-profile-preview hidden></div>
<form method="post" action="/api/identity/physician-onboarding.php" class="auth-form" data-onboarding-form="request_create" hidden><fieldset disabled><legend>Confirmar perfil</legend><label class="consent"><input type="checkbox" name="claim_accepted" required> Confirmo que este es el perfil exacto que deseo acreditar.</label><button class="button" type="submit">Preparar solicitud</button></fieldset></form>
<details><summary>¿Necesitas un perfil nuevo o resolver una transferencia?</summary><p>Esos trámites requieren atención del equipo autorizado. Aún no están disponibles aquí. No prepares una solicitud para un perfil diferente.</p><button class="button button--secondary" disabled>Crear perfil nuevo · no disponible</button><button class="button button--secondary" disabled>Transferir perfil · requiere revisión especial</button></details>
</section>
</div>
<?php else: ?>
<section class="auth-card"><h2>Solicitudes de acreditación</h2><p>Consulta únicamente los expedientes para los que tienes autorización. La revisión profesional no asigna un titular.</p><div data-request-list class="onboarding-request-list"></div></section>
<?php endif; ?>
<section class="auth-card" data-request-panel hidden aria-labelledby="request-heading"><h2 id="request-heading">Solicitud profesional</h2><div data-request-summary></div><div data-document-list class="onboarding-document-list"></div>
<?php if(!$staff): ?>
<form method="post" action="/api/identity/physician-onboarding.php" class="auth-form" data-onboarding-form="document_add" hidden><fieldset disabled><legend>3. Documentos para la revisión</legend><p>Archivos privados: PDF, JPG o PNG, máximo 8 MiB cada uno. No se publican en tu perfil.</p>
<div class="field"><label for="evidence-kind">Tipo de documento</label><select id="evidence-kind" name="kind"><option value="OFFICIAL_ID">Identificación oficial</option><option value="PROFESSIONAL_CREDENTIAL">Evidencia de cédula profesional</option></select></div>
<div class="field"><label for="evidence-file">Archivo</label><input id="evidence-file" name="document" type="file" accept="application/pdf,image/jpeg,image/png" required></div><button class="button" type="submit">Guardar documento privado</button></fieldset></form>
<form method="post" action="/api/identity/physician-onboarding.php" data-onboarding-form="request_submit" hidden><fieldset disabled><legend>Enviar a revisión</legend><p>Comprueba que agregaste ambos tipos de documento. Después del envío no podrás sustituirlos en esta solicitud.</p><button class="button" type="submit">Enviar solicitud a revisión</button></fieldset></form>
<section data-confirmation-section hidden><h3>5. Confirmación del médico</h3><p>Revisa el perfil aprobado y confirma con tu propia cuenta. La autorización es de un solo uso y vence en cinco minutos.</p>
<form method="post" action="/api/identity/physician-onboarding.php" class="auth-form" data-onboarding-form="confirmation_issue"><fieldset disabled><legend>Autorizar la confirmación</legend><div class="field"><label for="confirmation-email">Correo de tu cuenta</label><input id="confirmation-email" name="email" type="email" autocomplete="username" required></div><div class="field"><label for="confirmation-password">Confirma tu contraseña</label><input id="confirmation-password" name="password" type="password" autocomplete="current-password" required></div><button class="button button--secondary" type="submit">Autorizar confirmación</button></fieldset></form>
<form method="post" action="/api/identity/physician-onboarding.php" data-onboarding-form="confirmation_accept" hidden><fieldset disabled><legend>Confirmar mi solicitud</legend><label class="consent"><input type="checkbox" name="accepted" required> Confirmo que esta solicitud corresponde a mi cuenta, mi identidad y el perfil indicado.</label><button class="button" type="submit">Confirmar acreditación</button></fieldset></form></section>
<form method="post" action="/api/identity/physician-onboarding.php" data-onboarding-form="complete" hidden><fieldset disabled><legend>Completar acreditación</legend><p>Tu confirmación fue recibida. Falta completar el registro de la acreditación. Si hubo una interrupción, puedes reintentar este paso sin volver a confirmar.</p><button class="button" type="submit">Completar acreditación</button></fieldset></form>
<?php else: ?>
<form method="post" action="/api/identity/physician-onboarding.php" class="auth-form" data-onboarding-form="review_begin" hidden><fieldset disabled><legend>Preparar revisión protegida</legend><div class="field"><label for="review-decision">Decisión</label><select id="review-decision" name="decision"><option value="APPROVED">Aprobar relación profesional</option><option value="REJECTED">Rechazar solicitud</option></select></div><div class="field"><label for="review-reason">Motivo del rechazo</label><select id="review-reason" name="reason"><option value="ACCOUNT_PERSON_MISMATCH">No se acreditó la relación con la cuenta</option><option value="DOCUMENT_IDENTITY_MISMATCH">La identificación no corresponde</option><option value="DOCUMENT_UNREADABLE">El documento no es legible</option><option value="PROFILE_CONFLICT">Se requiere resolver un conflicto de perfil</option></select></div><div class="field"><label for="review-email">Correo de tu cuenta interna</label><input id="review-email" name="email" type="email" autocomplete="username" required></div><div class="field"><label for="review-password">Confirma tu contraseña</label><input id="review-password" name="password" type="password" autocomplete="current-password" required></div><button class="button" type="submit">Iniciar revisión segura</button></fieldset></form>
<form method="post" action="/api/identity/physician-onboarding.php" class="auth-form" data-onboarding-form="review_decide" hidden><fieldset disabled><legend>Resolver el expediente exacto</legend><p>Abre y examina cada documento después de iniciar la revisión. Indica su vencimiento real, si lo tiene; no se impone una renovación anual.</p><div data-document-facts></div><label class="consent"><input type="checkbox" name="relationship_confirmed"> He revisado los documentos y confirmo la relación exacta entre cuenta, persona y perfil.</label><div class="field"><label for="review-mfa">Código de tu autenticador</label><input id="review-mfa" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required></div><button class="button" type="submit">Registrar decisión</button></fieldset></form>
<?php endif; ?>
<p class="onboarding-notice" data-owner-pending hidden>Acreditación completada. La designación como titular sigue pendiente de una operación independiente y autorizada. Este paso no habilita firmas clínicas ni la administración de operadores.</p>
</section>
<?php if($staff): ?><dialog data-evidence-dialog aria-labelledby="evidence-heading"><div class="onboarding-dialog-head"><h2 id="evidence-heading">Documento privado</h2><button type="button" data-evidence-close>Cerrar documento</button></div><p>Uso exclusivo para revisar esta solicitud. El documento no tiene una dirección pública.</p><div data-evidence-content></div></dialog><?php endif; ?>
<noscript><p>Activa JavaScript para utilizar los formularios protegidos. No se ha enviado información.</p></noscript>
</main><footer class="prototype-footer">México Médico · Identidad y acreditación profesional</footer>
</div></body></html>
