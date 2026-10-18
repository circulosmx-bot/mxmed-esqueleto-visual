<?php declare(strict_types=1);if(empty($teamAuthorized)){http_response_code(403);exit;} ?>
<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Equipo interno y permisos · México Médico</title>
<link rel="stylesheet" href="/assets/css/media-review-inbox.css"><link rel="stylesheet" href="/assets/css/internal-team.css">
<link rel="stylesheet" href="/assets/css/internal-team-commercial.css"><link rel="stylesheet" href="/assets/css/internal-team-admission.css"><link rel="stylesheet" href="/assets/css/internal-team-readonly.css">
<?php if(!empty($staffWorkflowReady)): ?><script src="/assets/js/staff-workflow.js" defer></script><?php endif; ?>
<script src="/assets/js/internal-team-editor.js" defer></script><script src="/assets/js/internal-team-master.js" defer></script><script src="/assets/js/internal-team.js" defer></script></head><body>
<a class="skip-link" href="#advisor-detail" id="skip-detail">Ir al detalle del integrante</a>
<main class="advisor-workspace">
<header class="workspace-header"><div><p class="eyebrow">MÉXICO MÉDICO · EQUIPO INTERNO</p><h1>EQUIPO INTERNO Y PERMISOS</h1><p class="subtitle">Administración de asesores, administradores y funciones autorizadas.</p></div><div class="team-session"><span id="team-role" class="prototype-badge"><?php echo ($teamAuthority['class']??'')==='DIRECTOR'?'Director':'Administrador maestro'; ?></span><button id="team-logout" type="button">Cerrar sesión</button><span id="team-mode" hidden></span></div></header>
<?php if(!empty($staffWorkflowReady)){ require __DIR__.'/staff-workflow.php'; } ?>
<section class="team-introduction"><h2>Staff</h2><p>Revisa el alcance disponible para cada integrante.</p></section>
<p id="feedback" class="feedback" role="status" aria-live="polite">Cargando equipo…</p>
<div class="mobile-navigation" aria-label="Vista del equipo"><button id="show-directory" type="button" aria-pressed="true">Directorio</button><button id="show-detail" type="button" aria-pressed="false">Detalle y permisos</button></div>
<div class="workspace-columns" id="workspace-columns" data-mobile-view="directory">
<aside class="directory panel" id="team-directory" aria-labelledby="directory-heading"><div class="panel-heading"><h2 id="directory-heading">Equipo interno</h2><button id="refresh-team" type="button">Actualizar</button></div>
<div class="category-filter" role="group" aria-label="Categoría del equipo"><button id="filter-masters" type="button" aria-pressed="false" disabled>Administradores maestros</button><button id="filter-advisors" type="button" aria-pressed="true" disabled>Asesores</button></div>
<div id="staff-status-filter"><label for="staff-status" class="search-label">Estado del equipo</label><select id="staff-status"><option value="ACTIVE">Activos</option><option value="SUSPENDED">Suspendidos</option><option value="PENDING">Pendientes</option><option value="REMOVED">Dados de baja</option></select></div>
<p class="directory-note" id="authority-note" hidden></p><label for="advisor-search" class="search-label">Buscar por referencia de cuenta</label><input id="advisor-search" type="search" autocomplete="off" minlength="2" maxlength="64" placeholder="Referencia del equipo">
<div id="directory-content" aria-live="polite" aria-busy="true"></div>
<button class="add-button" id="add-member" type="button" disabled>Agregar integrante</button>
<nav id="team-pagination" class="team-pagination" aria-label="Páginas del equipo"><button id="previous-team" type="button" disabled>Anterior</button><span id="team-page"></span><button id="next-team" type="button" disabled>Siguiente</button></nav>
</aside>
<section class="detail panel" id="advisor-detail" tabindex="-1" aria-labelledby="detail-title"><div class="empty-state detail-empty"><h2 id="detail-title" tabindex="-1">Selecciona un integrante</h2><p>Su información aparecerá cuando termine la consulta autorizada.</p></div></section>
</div>
</main>
<dialog id="confirmation" aria-labelledby="confirmation-title" aria-describedby="confirmation-description">
<h2 id="confirmation-title"></h2><p id="confirmation-description"></p><div id="confirmation-body"></div>
<div class="dialog-actions"><button id="cancel-confirmation" type="button" autofocus>Cancelar</button><button id="accept-confirmation" type="button" class="primary">Confirmar cambios</button></div>
</dialog></body></html>
