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
<link rel="stylesheet" href="/assets/css/staff-workspace.css">
<script src="/assets/js/staff-workspace.js" defer></script>
</head>
<body>
<a class="skip-link" href="#work-panel">Saltar al área de trabajo</a>
<main class="workspace">
<header class="workspace-header">
<div><p class="eyebrow">MÉXICO MÉDICO · EQUIPO INTERNO</p><h1>GESTIÓN DE PERFILES MÉDICOS</h1><p class="subtitle">Consulta los perfiles y atiende el trabajo disponible para tu cuenta.</p></div>
<div class="staff-session"><div><strong id="staff-name">Cargando sesión…</strong><span id="staff-role"></span></div><button id="staff-logout" type="button">Cerrar sesión</button></div>
</header>
<p id="workspace-feedback" class="feedback" role="status" aria-live="polite"></p>
<section id="session-recovery" class="recovery" hidden><h2>La sesión necesita tu atención</h2><p id="session-message"></p><a class="button-link" href="/public/identity/acceso.php?next=%2Finternal%2Fstaff-workspace%2Findex.php">Iniciar sesión</a></section>
<section id="utilities" class="utilities" aria-label="Buscar y consultar pendientes">
<form id="physician-search-form" role="search"><label for="physician-search">Buscar médico</label><div class="search-row"><input id="physician-search" name="query" type="search" autocomplete="off" placeholder="Nombre del médico" maxlength="100"><button type="submit">Buscar</button></div></form>
<button id="pending-toggle" type="button" class="pending-toggle" aria-expanded="true" aria-controls="discovery"><span>Pendientes</span><span id="pending-count" class="pending-count" aria-label="0 pendientes">0</span></button>
</section>
<section id="discovery" class="discovery" aria-labelledby="discovery-title"><div class="section-heading"><div><p class="eyebrow" id="discovery-eyebrow">TRABAJO DISPONIBLE</p><h2 id="discovery-title" tabindex="-1">Pendientes por atender</h2></div><button id="close-discovery" type="button" hidden>Volver al médico</button></div><p id="discovery-message" class="muted">Cargando pendientes…</p><div id="discovery-list" class="discovery-list" aria-busy="true"></div></section>
<section id="no-selection" class="empty-state"><span class="empty-symbol" aria-hidden="true">⌕</span><h2>Selecciona un médico para comenzar</h2><p>Busca por nombre o abre un pendiente para consultar su perfil y el trabajo autorizado.</p></section>
<section id="physician-workspace" hidden>
<div class="context-sticky">
<header class="physician-context"><div class="physician-identity"><p class="eyebrow">PERFIL MÉDICO</p><h2 id="physician-name" tabindex="-1"></h2><p id="physician-description"></p><div id="physician-statuses" class="badges"></div></div><button id="change-physician" type="button">Cambiar médico</button></header>
<nav id="section-navigation" class="section-navigation" aria-label="Secciones del médico"><div id="section-tabs" role="tablist" aria-label="Secciones del médico"></div><div id="more-sections" class="more-sections" hidden><button id="more-toggle" type="button" aria-haspopup="menu" aria-expanded="false" aria-controls="more-menu">Más <span aria-hidden="true">⌄</span></button><div id="more-menu" class="more-menu" role="menu" aria-label="Más secciones" hidden></div></div></nav>
</div>
<div id="work-panel" class="work-panel" role="tabpanel" tabindex="-1">
<section id="summary-panel" aria-labelledby="summary-title"><div class="section-heading"><h2 id="summary-title">Resumen</h2><span class="muted">Información del perfil seleccionado</span></div><div id="summary-facts" class="summary-facts"></div><h3>Pendientes de este médico</h3><div id="summary-pending" class="task-list"></div><section id="recorded-activity" hidden><h3>Actividad registrada</h3><div id="activity-list" class="activity-list"></div></section></section>
<section id="images-panel" hidden aria-labelledby="images-title"><div class="section-heading"><div><h2 id="images-title">Imágenes</h2><p class="muted">Revisa los archivos recibidos y realiza las acciones autorizadas.</p></div><button id="refresh-images" type="button">Actualizar</button></div><section id="published-media" hidden><h3>Imágenes publicadas</h3><div id="published-list" class="published-list"></div></section><h3>Imágenes pendientes</h3><p id="images-empty" class="muted" hidden>No hay imágenes pendientes para este médico.</p><div class="media-layout"><div id="media-items" class="media-items" aria-label="Imágenes recibidas"></div><article id="media-detail" class="media-detail" hidden aria-labelledby="media-title"><div class="media-heading"><div><p id="media-purpose" class="eyebrow"></p><h3 id="media-title" tabindex="-1">Revisión de imagen</h3><p id="media-date" class="muted"></p></div><span class="badge review-badge">Pendiente de revisión</span></div><div id="image-comparison" class="image-comparison"><div><div class="review-image-wrap"><img id="review-image" alt="" hidden><p id="review-image-message">Cargando imagen…</p></div><p id="review-specs" class="muted"></p></div><div id="proposal-panel" hidden><h4>Propuesta de mejora</h4><div class="review-image-wrap"><img id="proposal-image" alt="Propuesta de mejora del logotipo"></div></div></div><p id="media-feedback" class="feedback" role="status"></p><div id="media-actions" class="action-row"><button id="download-original" type="button" hidden>Descargar original</button><button id="request-replacement" type="button" hidden>Solicitar otra imagen</button><button id="approve-image" class="primary" type="button" hidden>Aprobar y publicar</button></div>
<form id="replacement-form" class="context-form" hidden><h4>Solicitar otra imagen</h4><p class="muted">La indicación quedará vinculada a esta imagen del médico seleccionado.</p><label for="replacement-reason">Motivo</label><select id="replacement-reason" required><option value="">Selecciona un motivo</option><option value="WRONG_MEDIA_TYPE">La imagen no corresponde al tipo solicitado</option><option value="QUALITY_INSUFFICIENT">La calidad de la imagen no es suficiente</option><option value="CONTENT_NOT_APPROPRIATE">La imagen no es adecuada para publicarse</option><option value="OTHER">Se requiere otra imagen</option></select><label for="replacement-feedback">Indicaciones para el usuario (opcional)</label><textarea id="replacement-feedback" rows="4" maxlength="400" aria-describedby="replacement-count"></textarea><p id="replacement-count" class="muted">0 / 400 caracteres</p><div class="action-row"><button id="send-replacement" class="primary" type="submit">Confirmar solicitud</button><button id="cancel-replacement" type="button">Cancelar</button></div></form>
<section id="correction-section" class="context-form" hidden><h4>Versión corregida</h4><label for="corrected-file">Selecciona una imagen corregida</label><input id="corrected-file" type="file" accept="image/jpeg,image/png,image/webp"><button id="save-corrected" type="button" hidden>Guardar versión corregida</button></section>
<section id="improvement-section" class="context-form" hidden><h4>Mejora de logotipo</h4><div class="action-row"><button id="generate-improvement" type="button">Generar propuesta de mejora</button><button id="accept-improvement" type="button" hidden>Usar versión mejorada</button><button id="discard-improvement" type="button" hidden>Descartar propuesta</button></div></section>
</article></div></section>
</div>
</section>
</main>
</body>
</html>
