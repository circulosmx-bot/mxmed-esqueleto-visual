<?php
declare(strict_types=1);
require_once __DIR__.'/../../modules/media/services/AdvisorWorkspaceService.php';
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
header('Cross-Origin-Resource-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' blob:; connect-src 'self'; object-src 'none'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'");
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {http_response_code(405);header('Allow: GET');exit;}
try {
    \Media\Services\AdvisorWorkspaceService::fromCookies($_COOKIE);
} catch (Throwable $error) {
    if ($error->getMessage()==='workspace_login_required') {
        header('Location: /public/identity/acceso.php?next=%2Finternal%2Fstaff-workspace%2Findex.php',true,302);exit;
    }
    $denied=$error->getMessage()==='workspace_staff_required';http_response_code($denied?403:503);
    echo '<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Gestión de perfiles médicos · México Médico</title><link rel="stylesheet" href="/assets/css/staff-workspace.css"><main class="workspace"><h1>Gestión de perfiles médicos</h1><p>'.($denied?'Tu cuenta no tiene acceso activo al equipo interno.':'No fue posible abrir el espacio de trabajo. Intenta nuevamente.').'</p><a href="/public/identity/acceso.php?next=%2Finternal%2Fstaff-workspace%2Findex.php">Iniciar sesión</a></main></html>';exit;
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Gestión de perfiles médicos · México Médico</title>
<link rel="stylesheet" href="/assets/css/staff-workspace.css?v=<?= substr(hash_file('sha256',__DIR__.'/../../assets/css/staff-workspace.css'),0,16) ?>">
<link rel="stylesheet" href="/assets/css/staff-platform-header.css">
<script src="/assets/js/staff-workspace.js?v=<?= substr(hash_file('sha256',__DIR__.'/../../assets/js/staff-workspace.js'),0,16) ?>" defer></script>
<script src="/assets/js/staff-capability-reviews.js" defer></script>
</head>
<body>
<a class="skip-link" href="#work-panel">Saltar al área de trabajo</a>
<main class="workspace">
<header class="workspace-header staff-platform-header">
<div class="team-brand"><img src="/assets/mexico-medico.svg" alt="México Médico" width="112" height="48"><div><h1>GESTIÓN DE PERFILES MÉDICOS</h1></div></div>
<div class="team-session"><div class="team-identity"><svg class="team-avatar" viewBox="0 0 72 72" aria-hidden="true" focusable="false"><rect width="72" height="72" rx="8" fill="#edf4f4"/><circle cx="36" cy="27" r="12" fill="#88a4aa"/><path d="M13 66v-7a23 23 0 0146 0v7" fill="#436b76"/></svg><div class="team-identity-copy"><strong id="staff-name">Cargando sesión…</strong><span id="staff-role"></span></div></div><button id="staff-logout" type="button">Cerrar sesión</button></div>
</header>
<p id="workspace-feedback" class="feedback" role="status" aria-live="polite"></p>
<section id="session-recovery" class="recovery" hidden><h2>La sesión necesita tu atención</h2><p id="session-message"></p><a class="button-link" href="/public/identity/acceso.php?next=%2Finternal%2Fstaff-workspace%2Findex.php">Iniciar sesión</a></section>
<nav id="governed-reviews" class="action-row" aria-label="Revisiones autorizadas" hidden><a id="professional-reviews" href="/internal/media-review/index.php?section=acreditacion" hidden>Fuentes y acreditación profesional</a><button id="provider-reviews" type="button" hidden>Claims de organizaciones</button></nav>
<section id="provider-review-panel" class="context-form" hidden><h2>Claims de organizaciones proveedoras</h2><p>Revisión del primer titular de una organización. No modifica la titularidad de médicos, su acreditación ni sus planes.</p><button id="close-provider-reviews" type="button">Volver a médicos</button><div id="provider-claims"></div><p id="provider-feedback" role="status"></p></section>
<section id="utilities" class="utilities" aria-label="Buscar y consultar pendientes">
<button id="search-toggle" type="button" aria-expanded="true" aria-controls="physician-search-form discovery" hidden>Buscar médico o solicitud</button>
<form id="physician-search-form" role="search"><label for="physician-search">Buscar médico</label><div class="search-row"><input id="physician-search" name="query" type="search" autocomplete="off" placeholder="Nombre del médico" maxlength="100"><button type="submit">Buscar</button></div></form>
<button id="pending-toggle" type="button" class="pending-toggle" aria-expanded="true" aria-controls="discovery"><span>Pendientes</span><span id="pending-count" class="pending-count" aria-label="0 pendientes">0</span></button>
</section>
<section id="discovery" class="discovery" aria-labelledby="discovery-title"><div class="section-heading"><div><p class="eyebrow" id="discovery-eyebrow">TRABAJO DISPONIBLE</p><h2 id="discovery-title" tabindex="-1">Pendientes por atender</h2></div><button id="close-discovery" type="button" hidden>Volver al contexto seleccionado</button></div><p id="discovery-message" class="muted">Cargando pendientes…</p><div id="discovery-list" class="discovery-list" aria-busy="true" tabindex="-1"></div></section>
<section id="no-selection" class="empty-state"><span class="empty-symbol" aria-hidden="true">⌕</span><h2>Selecciona un médico para comenzar</h2><p>Busca por nombre o abre un pendiente para consultar su perfil y el trabajo autorizado.</p></section>
<section id="physician-workspace" hidden>
<div class="context-sticky">
<header class="physician-context"><div class="physician-identity"><div class="physician-avatar-wrap"><svg id="physician-avatar" role="img" aria-label="Avatar profesional neutral" viewBox="0 0 64 64"><circle cx="32" cy="22" r="11" fill="currentColor"/><path d="M10 60v-7a22 22 0 0 1 44 0v7" fill="currentColor"/></svg><img id="physician-portrait" alt="Retrato publicado del perfil seleccionado" hidden></div><div><p id="context-type" class="eyebrow">PERFIL MÉDICO</p><h2 id="physician-name" tabindex="-1"></h2><p id="physician-description"></p><div id="physician-statuses" class="badges"></div></div></div><button id="change-physician" type="button">Cambiar selección</button></header>
<nav id="section-navigation" class="section-navigation" aria-label="Secciones del médico"><div id="section-tabs" role="tablist" aria-label="Secciones del médico"></div><div id="more-sections" class="more-sections" hidden><button id="more-toggle" type="button" aria-haspopup="menu" aria-expanded="false" aria-controls="more-menu">Secciones <span aria-hidden="true">⌄</span></button><div id="more-menu" class="more-menu" role="menu" aria-label="Más secciones" hidden></div></div></nav>
</div>
<div id="work-panel" class="work-panel" role="tabpanel" tabindex="-1">
<section id="restricted-panel" hidden><h2 id="restricted-title"></h2><p id="restricted-message" class="muted"></p><p id="restricted-summary" class="feedback"></p></section>
<section id="summary-panel" aria-labelledby="summary-title"><div class="section-heading"><h2 id="summary-title">Resumen</h2><span class="muted">Información del perfil seleccionado</span></div><div id="summary-facts" class="summary-facts"></div><h3>Pendientes de este médico</h3><div id="summary-pending" class="task-list"></div><section id="recorded-activity" hidden><h3>Actividad registrada</h3><div id="activity-list" class="activity-list"></div></section></section>
<section id="requests-panel" hidden aria-labelledby="requests-title">
<div class="section-heading"><div><h2 id="requests-title">Solicitudes</h2><p class="muted">Revisa el perfil y la persona solicitante antes de resolver.</p></div><button id="refresh-requests" type="button">Actualizar</button></div>
<div id="request-list" class="task-list"></div><p id="request-empty" class="muted" hidden>No hay solicitudes disponibles para este contexto.</p>
<article id="request-detail" hidden aria-labelledby="request-heading">
<div class="section-heading request-heading"><div><p id="request-kind" class="eyebrow"></p><h3 id="request-heading" tabindex="-1"></h3><p id="request-reference" class="muted"></p></div><span id="request-status" class="badge review-badge"></span></div>
<div id="request-comparison" class="request-comparison"></div>
<section id="request-verification"><h3>Verificación y evidencia</h3><div id="request-verification-facts"></div><div id="request-evidence" class="action-row"></div></section>
<section id="request-coincidences" hidden><h3>Posibles coincidencias</h3><div id="request-coincidence-list"></div></section>
<section id="request-correspondence" hidden><h3>Información solicitada y respuestas</h3><div id="request-correspondence-list" class="activity-list"></div></section>
<p id="request-outcome" class="feedback" role="status"></p><p id="request-feedback" class="feedback" role="status" aria-live="polite"></p>
<section id="request-ownership" class="context-form" hidden><h3>Titularidad del perfil</h3><p id="ownership-message" class="muted" role="status"></p><div class="action-row"><button id="continue-ownership" type="button" hidden>Continuar titularidad</button><button id="refresh-ownership" type="button">Consultar estado</button></div></section>
<div id="request-actions" class="action-row"><button id="approve-request" class="primary" type="button" hidden>Aprobar reclamo</button><button id="request-information" type="button" hidden>Solicitar información</button><button id="reject-request" type="button" hidden>Rechazar solicitud</button></div>
<form id="request-decision-form" class="context-form" hidden>
<h3 id="request-decision-title"></h3><p id="request-decision-explanation" class="muted"></p>
<label id="request-reason-label" for="request-reason">Motivo de la decisión</label><textarea id="request-reason" rows="3" maxlength="500" required></textarea>
<div id="request-reauth" hidden><label for="request-password">Tu contraseña</label><input id="request-password" type="password" autocomplete="current-password"><label for="request-mfa">Código de tu autenticador</label><input id="request-mfa" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}"></div>
<label class="confirmation-check"><input id="request-confirmation" type="checkbox" required><span id="request-confirmation-label">Confirmo la decisión para esta solicitud y esta persona.</span></label>
<div class="action-row"><button id="confirm-request-decision" class="primary" type="submit">Confirmar decisión</button><button id="cancel-request-decision" type="button">Cancelar</button></div>
</form>
<div id="request-recovery" class="recovery" hidden><h3>Comprobar el resultado</h3><p>La respuesta no permitió confirmar el resultado. Consulta la decisión registrada antes de realizar otra acción.</p><button id="lookup-request-result" type="button">Consultar resultado</button></div>
</article>
</section>
<section id="images-panel" hidden aria-labelledby="images-title"><div class="section-heading"><div><h2 id="images-title">Imágenes</h2><p class="muted">Revisa los archivos recibidos y realiza las acciones autorizadas.</p></div><button id="refresh-images" type="button">Actualizar</button></div><section id="published-media" hidden><h3>Imágenes publicadas</h3><div id="published-list" class="published-list"></div></section><h3>Imágenes pendientes</h3><p id="images-empty" class="muted" hidden>No hay imágenes pendientes para este médico.</p><div class="media-layout"><div id="media-items" class="media-items" aria-label="Imágenes recibidas"></div><article id="media-detail" class="media-detail" hidden aria-labelledby="media-title"><div class="media-heading"><div><p id="media-purpose" class="eyebrow"></p><h3 id="media-title" tabindex="-1">Revisión de imagen</h3><p id="media-date" class="muted"></p></div><span class="badge review-badge">Pendiente de revisión</span></div><div id="image-comparison" class="image-comparison"><div><div class="review-image-wrap"><img id="review-image" alt="" hidden><p id="review-image-message">Cargando imagen…</p></div><p id="review-specs" class="muted"></p></div><div id="proposal-panel" hidden><h4>Propuesta de mejora</h4><div class="review-image-wrap"><img id="proposal-image" alt="Propuesta de mejora del logotipo"></div></div></div><p id="media-feedback" class="feedback" role="status"></p><div id="media-actions" class="action-row"><button id="download-original" type="button" hidden>Descargar original</button><button id="request-replacement" type="button" hidden>Solicitar otra imagen</button><button id="approve-image" class="primary" type="button" hidden>Aprobar y publicar</button></div>
<form id="replacement-form" class="context-form" hidden><h4>Solicitar otra imagen</h4><p class="muted">La indicación quedará vinculada a esta imagen del médico seleccionado.</p><label for="replacement-reason">Motivo</label><select id="replacement-reason" required><option value="">Selecciona un motivo</option><option value="WRONG_MEDIA_TYPE">La imagen no corresponde al tipo solicitado</option><option value="QUALITY_INSUFFICIENT">La calidad de la imagen no es suficiente</option><option value="CONTENT_NOT_APPROPRIATE">La imagen no es adecuada para publicarse</option><option value="OTHER">Se requiere otra imagen</option></select><label for="replacement-feedback">Indicaciones para el usuario (opcional)</label><textarea id="replacement-feedback" rows="4" maxlength="400" aria-describedby="replacement-count"></textarea><p id="replacement-count" class="muted">0 / 400 caracteres</p><div class="action-row"><button id="send-replacement" class="primary" type="submit">Confirmar solicitud</button><button id="cancel-replacement" type="button">Cancelar</button></div></form>
<section id="correction-section" class="context-form" hidden><h4>Versión corregida</h4><label for="corrected-file">Selecciona una imagen corregida</label><input id="corrected-file" type="file" accept="image/jpeg,image/png,image/webp"><button id="save-corrected" type="button" hidden>Guardar versión corregida</button></section>
<section id="improvement-section" class="context-form" hidden><h4>Mejora de logotipo</h4><div class="action-row"><button id="generate-improvement" type="button">Generar propuesta de mejora</button><button id="accept-improvement" type="button" hidden>Usar versión mejorada</button><button id="discard-improvement" type="button" hidden>Descartar propuesta</button></div></section>
</article></div></section>
<section id="offices-panel" hidden><h2>Consultorios</h2><p id="offices-message" role="status"></p><div id="offices-content" class="summary-grid"></div></section>
<section id="operators-panel" hidden aria-labelledby="operators-title"><div class="section-heading"><h2 id="operators-title">Operadores y colaboradores</h2><button id="refresh-operators" type="button">Actualizar</button></div><p id="operators-message" class="muted" role="status"></p><div id="operators-content" class="history-list"></div></section>
<section id="history-panel" hidden aria-labelledby="history-title"><div class="section-heading"><div><h2 id="history-title">Historial</h2><p class="muted">Decisiones de solicitudes y acciones de imágenes registradas para este contexto.</p></div><button id="refresh-history" type="button">Actualizar</button></div><p id="history-message" class="muted" role="status"></p><div id="history-list" class="history-list"></div></section>
</div>
</section>
</main>
<dialog id="evidence-dialog" class="evidence-dialog" aria-labelledby="evidence-title"><div class="evidence-shell"><header><div><h2 id="evidence-title">Evidencia de la solicitud</h2><p id="evidence-context" class="muted"></p></div><button id="close-evidence" type="button">Cerrar</button></header><div class="evidence-body"><p id="evidence-message" role="status"></p><img id="evidence-image" alt="Evidencia de la solicitud seleccionada" hidden></div></div></dialog>
</body>
</html>
