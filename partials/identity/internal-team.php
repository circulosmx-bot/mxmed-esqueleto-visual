<?php declare(strict_types=1);if(empty($teamAuthorized)){http_response_code(403);exit;} ?>
<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Equipo interno y permisos · México Médico</title>
<link rel="stylesheet" href="/assets/css/media-review-inbox.css"><link rel="stylesheet" href="/assets/css/internal-team.css">
<link rel="stylesheet" href="/assets/css/internal-team-commercial.css"><link rel="stylesheet" href="/assets/css/internal-team-admission.css"><link rel="stylesheet" href="/assets/css/internal-team-readonly.css">
<script src="/assets/js/internal-team-editor.js" defer></script><script src="/assets/js/internal-team.js" defer></script></head><body>
<a class="skip-link" href="#advisor-detail" id="skip-detail">Ir al detalle del integrante</a>
<main class="advisor-workspace">
<header class="workspace-header"><div><p class="eyebrow">México Médico · Equipo interno</p><h1>EQUIPO INTERNO Y PERMISOS</h1><p class="subtitle">Administración de asesores, administradores y funciones autorizadas.</p></div><span id="team-mode" class="prototype-badge">SÓLO CONSULTA</span></header>
<nav class="team-navigation" aria-label="Equipo interno"><a href="/internal/media-review/index.php">Revisión de medios</a><a href="/internal/media-review/index.php?section=acreditacion">Acreditación profesional</a><a href="/internal/media-review/index.php?section=correcciones">Informes de perfiles</a><a href="/internal/media-review/index.php?section=equipo" aria-current="page">Equipo y permisos</a></nav>
<p class="preview-notice">Consulta autorizada del equipo interno. Los permisos editables requieren autoridad vigente y confirmación. Incorporación y documentos siguen sin conectar.</p>
<details class="admission-requests panel" id="admission-requests"><summary>Solicitudes de incorporación <span class="muted">No conectado</span></summary><p class="admission-note">La preparación asistida y la finalización remota preautorizada aún no están conectadas. No se envían invitaciones ni se reciben documentos.</p><ol class="wizard-steps"><li>Persona</li><li>Categoría</li><li>Permisos</li><li>Revisión</li></ol><button type="button" disabled>Preparar solicitud</button><button type="button" disabled>Finalización remota</button></details>
<p id="feedback" class="feedback" role="status" aria-live="polite">Cargando equipo…</p>
<div class="mobile-navigation" aria-label="Vista del equipo"><button id="show-directory" type="button" aria-pressed="true">Directorio</button><button id="show-detail" type="button" aria-pressed="false">Detalle y permisos</button></div>
<div class="workspace-columns" id="workspace-columns" data-mobile-view="directory">
<aside class="directory panel" id="team-directory" aria-labelledby="directory-heading"><div class="panel-heading"><h2 id="directory-heading">Equipo interno</h2><button id="refresh-team" type="button">Actualizar</button></div>
<div class="category-filter" role="group" aria-label="Categoría del equipo"><button id="filter-advisors" type="button" aria-pressed="true" disabled>Asesores</button><button id="filter-masters" type="button" aria-pressed="false" disabled>Administradores maestros</button></div>
<p class="directory-note" id="authority-note"></p><label for="advisor-search" class="search-label">Buscar por referencia de cuenta</label><input id="advisor-search" type="search" autocomplete="off" minlength="2" maxlength="64" placeholder="Referencia del equipo">
<p class="directory-note">Búsqueda limitada a las relaciones staff autorizadas; no consulta correos ni cuentas ajenas al directorio.</p><div id="directory-content" aria-live="polite" aria-busy="true"></div>
<nav id="team-pagination" class="team-pagination" aria-label="Páginas del equipo"><button id="previous-team" type="button" disabled>Anterior</button><span id="team-page"></span><button id="next-team" type="button" disabled>Siguiente</button></nav>
<button class="add-button" id="add-member" type="button" disabled>+ AGREGAR INTEGRANTE</button><p class="directory-note">Incorporación pendiente de conexión. No hay un cupo arbitrario de asesores; las dos plazas de operadores por médico pertenecen a otra función.</p></aside>
<section class="detail panel" id="advisor-detail" tabindex="-1" aria-labelledby="detail-title"><div class="empty-state detail-empty"><h2 id="detail-title" tabindex="-1">Selecciona un integrante</h2><p>Su información aparecerá cuando termine la consulta autorizada.</p></div></section>
</div><footer class="preview-footer"><p>Diseño R2, comercial y de incorporación aprobado. Integración aislada; sin activación operacional.</p></footer>
</main>
<dialog id="confirmation" aria-labelledby="confirmation-title" aria-describedby="confirmation-description">
<h2 id="confirmation-title"></h2><p id="confirmation-description"></p><div id="confirmation-body"></div>
<div class="dialog-actions"><button id="cancel-confirmation" type="button" autofocus>Cancelar</button><button id="accept-confirmation" type="button" class="primary">Confirmar cambios</button></div>
</dialog></body></html>
