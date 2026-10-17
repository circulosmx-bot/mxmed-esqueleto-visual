<?php
declare(strict_types=1);
if(!isset($correctionReviewPage)){http_response_code(403);exit;}
$escape=static fn(string $s):string=>htmlspecialchars($s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
?>
<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Informes de perfiles · México Médico</title>
<link rel="stylesheet" href="/assets/css/media-review-inbox.css">
<link rel="stylesheet" href="/assets/css/profile-correction-review.css">
<?php if($correctionReviewPage['applications']??false): ?><script src="/assets/js/profile-correction-application.js" defer></script><?php endif; ?>
<script src="/assets/js/profile-correction-review.js" defer></script>
</head>
<body><main class="inbox" data-correction-review data-csrf="<?= $escape($correctionReviewPage['csrf']) ?>">
<header class="inbox-header"><p class="eyebrow">México Médico · Equipo interno</p><h1>Informes de perfiles</h1><p>Revisa la información recibida antes de decidir. Aceptar un informe deja cualquier cambio al perfil pendiente de aplicación autorizada.</p></header>
<nav aria-label="Equipo interno"><a href="/internal/media-review/index.php">Revisión de medios</a> · <a href="/internal/media-review/index.php?section=acreditacion">Acreditación profesional</a></nav>
<section aria-labelledby="correction-queue-title"><div class="section-heading"><h2 id="correction-queue-title">Informes recibidos</h2><button type="button" data-refresh>Actualizar</button></div>
<p data-review-message role="status" aria-live="polite">Cargando informes…</p><div class="cards" data-report-queue></div>
<nav class="pagination" aria-label="Páginas de informes"><button type="button" data-previous disabled>Anterior</button><span data-page-number></span><button type="button" data-next disabled>Siguiente</button></nav></section>
<section data-report-detail hidden aria-labelledby="correction-detail-title" class="correction-detail">
<h2 id="correction-detail-title" tabindex="-1">Revisión del informe</h2>
<p data-original-profile></p><a data-profile-link target="_blank" rel="noopener">Consultar perfil original</a>
<p class="status-label" data-report-status></p><p data-category></p>
<h3>Explicación recibida</h3><p data-explanation class="correction-private-text"></p>
<p data-relation></p><p data-contact></p>
<p>La relación es declarada por quien informa. No acredita identidad ni autoridad. Un aviso de retiro o fallecimiento, incluso aceptado como informe, no modifica publicación, cuentas ni accesos.</p>
<h3>Historial de revisión</h3><ol data-review-history></ol>
<form data-review-begin method="post" action="/api/profiles/internal/correction-review.php" autocomplete="off">
<fieldset><legend>Registrar revisión humana</legend>
<label for="correction-decision">Resultado</label><select id="correction-decision" name="status" required>
<option value="NEEDS_CLARIFICATION">Solicitar aclaración</option><option value="REJECTED">Rechazar informe</option><option value="ACCEPTED_FOR_APPLICATION">Aceptar informe; no aplicar cambio</option></select>
<label for="correction-reason">Motivo</label><select id="correction-reason" name="reason" required>
<?php foreach(\Profiles\Corrections\ReportCatalog::REASONS as $key=>$label): ?><option value="<?= $escape($key) ?>"><?= $escape($label) ?></option><?php endforeach; ?>
</select>
<label for="correction-refs">Referencias privadas de evidencia o decisión</label><input id="correction-refs" name="evidence_refs" maxlength="480" aria-describedby="correction-refs-help">
<p id="correction-refs-help">Hasta cinco identificadores de expediente interno separados por comas. No enlaces, documentos ni datos sensibles. Al aceptar, se requiere al menos una referencia.</p>
<label for="correction-note">Nota de revisión *</label><textarea id="correction-note" name="decision_note" minlength="8" maxlength="500" rows="3" required></textarea>
<label class="correction-check"><input type="checkbox" name="facts_confirmed"> Contrasté los hechos y sus referencias. Obligatorio para aceptar el informe.</label>
<label for="correction-review-email">Tu correo de acceso *</label><input id="correction-review-email" name="email" type="email" autocomplete="username" required>
<label for="correction-review-password">Confirma tu contraseña *</label><input id="correction-review-password" name="password" type="password" autocomplete="current-password" required>
<button type="submit">Continuar a verificación de seguridad</button></fieldset>
</form>
<form data-review-decide method="post" action="/api/profiles/internal/correction-review.php" hidden autocomplete="off"><fieldset><legend>Confirmar esta decisión</legend>
<p data-frozen-decision></p><p>La decisión corresponde únicamente a este informe y versión. No aplica cambios al perfil.</p>
<label for="correction-mfa">Código de tu autenticador *</label><input id="correction-mfa" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required>
<div class="approval-actions"><button type="submit">Confirmar revisión</button><button type="button" data-edit-decision>Volver a la decisión</button></div></fieldset></form>
<p data-decision-message role="status" aria-live="polite"></p>
<?php if($correctionReviewPage['applications']??false)require __DIR__.'/correction-application.php'; ?>
</section>
</main></body></html>
