<?php
declare(strict_types=1);
if(!isset($correctionReviewPage)||!($correctionReviewPage['applications']??false)){http_response_code(403);exit;}
?>
<section data-correction-application hidden aria-labelledby="correction-application-title">
<h3 id="correction-application-title">Aplicación del cambio</h3>
<p data-application-state role="status" aria-live="polite">Aceptado, pendiente de aplicar</p>
<p>La aceptación del informe no autoriza cambios por sí sola. Confirma el dato publicado y una propuesta verificada; no se copia la explicación del informante.</p>
<button type="button" data-application-start hidden>Aplicar corrección</button>
<form data-application-begin hidden method="post" action="/api/profiles/internal/correction-review.php" autocomplete="off">
<fieldset><legend>Comparar el dato exacto</legend>
<label for="application-target">Contacto publicado *</label><select id="application-target" required><option value="">Selecciona el contacto exacto</option></select>
<p>Campo: teléfono público del perfil. No se modifica un teléfono de consultorio ni se reasigna una sede.</p>
<p>Valor publicado actual: <strong data-application-current>Selecciona un contacto</strong></p>
<label for="application-proposed">Teléfono propuesto y verificado *</label><input id="application-proposed" name="proposed_value" type="tel" minlength="7" maxlength="40" required>
<label for="application-references">Referencias de verificación independiente *</label><input id="application-references" name="verification_refs" maxlength="480" required aria-describedby="application-reference-help">
<p id="application-reference-help">De una a cinco referencias internas separadas por comas. No enlaces, archivos ni datos clínicos.</p>
<label for="application-reason">Motivo del cambio *</label><textarea id="application-reason" name="reason" minlength="8" maxlength="500" rows="2" required></textarea>
<label class="correction-check"><input name="application_facts_confirmed" type="checkbox" required> Verifiqué este teléfono, el perfil y el recurso exactos con información independiente.</label>
<label for="application-email">Tu correo de acceso *</label><input id="application-email" name="email" type="email" autocomplete="username" required>
<label for="application-password">Confirma tu contraseña *</label><input id="application-password" name="password" type="password" autocomplete="current-password" required>
<button type="submit">Autorizar este cambio</button></fieldset></form>
<form data-application-mfa hidden method="post" action="/api/profiles/internal/correction-review.php" autocomplete="off">
<fieldset><legend>Verificar autorización</legend><p data-application-comparison></p>
<label for="application-code">Código de tu autenticador *</label><input id="application-code" name="code" pattern="[0-9]{6}" inputmode="numeric" maxlength="6" autocomplete="one-time-code" required>
<button type="submit">Verificar autorización</button></fieldset></form>
<div data-application-confirm hidden><p data-application-final-comparison></p><p>Esta autorización corresponde únicamente al cambio mostrado. Todavía no se ha aplicado.</p><button type="button" data-application-execute>Confirmar aplicación</button></div>
<button type="button" data-application-status hidden>Consultar estado antes de reintentar</button>
<p data-application-message role="status" aria-live="polite"></p>
<h3>Historial de aplicación</h3><ol data-application-history></ol>
<details><summary>Campos fuera de esta fase</summary><ul data-application-blocked></ul></details>
</section>
