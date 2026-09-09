<?php
declare(strict_types=1);
require_once __DIR__.'/../../modules/media/http/MediaReviewHttpContext.php';
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
header('Cross-Origin-Resource-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' blob:; connect-src 'self'; object-src 'none'; base-uri 'none'; frame-ancestors 'none'");
try {
    \Media\Services\MediaReviewAuthority::requireRead(\Media\Http\MediaReviewHttpContext::fromRequest($_COOKIE,$_SERVER));
} catch (Throwable) {
    http_response_code(403);echo '<!doctype html><html lang="es"><meta charset="utf-8"><title>Acceso restringido</title><p>Acceso restringido.</p></html>';exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {http_response_code(405);header('Allow: GET');exit;}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Revisión de medios · México Médico</title>
<link rel="stylesheet" href="/assets/css/media-review-inbox.css">
<script src="/assets/js/media-review-inbox.js" defer></script>
</head>
<body>
<main class="inbox">
<header class="inbox-header"><p class="eyebrow">México Médico · Equipo interno</p><h1>Revisión de medios</h1><p>Consulta las imágenes recibidas para revisión.</p></header>
<section aria-labelledby="pending-title">
<div class="section-heading"><h2 id="pending-title">Pendientes de revisión</h2><span id="page-count" aria-live="polite"></span></div>
<p id="inbox-message" role="status">Cargando medios pendientes…</p>
<div id="pending-cards" class="cards" aria-busy="true"></div>
<nav class="pagination" aria-label="Páginas de medios pendientes" hidden>
<button id="previous-page" type="button">Anterior</button><span id="page-number"></span><button id="next-page" type="button">Siguiente</button>
</nav>
</section>
</main>
<dialog id="review-detail" aria-labelledby="detail-title">
<div class="detail-heading"><div><p class="eyebrow" id="detail-purpose"></p><h2 id="detail-title"></h2></div><button id="close-detail" type="button" aria-label="Cerrar imagen">Cerrar</button></div>
<div id="improvement-comparison">
<div><p id="current-version-label" class="eyebrow" hidden>Versión actual</p><div class="detail-image-wrap"><img id="detail-image" alt=""><p id="detail-unavailable" hidden>Imagen no disponible.</p></div></div>
<div id="proposal-panel" hidden><p class="eyebrow">Propuesta</p><div class="detail-image-wrap"><img id="proposal-image" alt="Propuesta de logotipo con fondo transparente"></div></div>
</div>
<p id="detail-date"></p><p id="detail-specs"></p><p class="status-label">Pendiente de revisión</p>
<button id="approve-photo" type="button" hidden>Aprobar</button>
<section id="approval-confirmation" aria-labelledby="approval-question" hidden>
<h3 id="approval-question">¿Aprobar esta foto de perfil?</h3>
<div class="approval-actions"><button id="cancel-approval" type="button">Cancelar</button><button id="confirm-approval" type="button">Aprobar</button></div>
</section>
<p id="approval-message" role="status"></p>
<section id="design-intervention" aria-labelledby="design-title" hidden>
<h3 id="design-title">Intervención de diseño</h3>
<div class="approval-actions"><button id="download-source" type="button" hidden>Descargar original</button><button id="choose-corrected" type="button" hidden>Subir versión corregida</button></div>
<input id="corrected-file" type="file" accept="image/jpeg,image/png,image/webp" hidden>
<p id="corrected-filename"></p>
<button id="submit-corrected" type="button" hidden>Guardar versión corregida</button>
<p id="intervention-message" role="status"></p>
</section>
<section id="logo-improvement" hidden aria-label="Mejora de logotipo">
<button id="generate-improvement" type="button">Mejorar automáticamente</button>
<div id="proposal-actions" class="approval-actions" hidden><button id="discard-improvement" type="button">Descartar propuesta</button><button id="accept-improvement" type="button" disabled>Usar versión mejorada</button></div>
<p id="improvement-message" role="status"></p>
</section>
</dialog>
</body></html>
