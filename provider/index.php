<?php
declare(strict_types=1);
require_once __DIR__ . '/../modules/identity/http/IdentityHttpComposition.php';
require_once __DIR__ . '/../modules/identity/http/CanonicalHttpSessionResolver.php';

use Identity\Http\IdentityHttpComposition;
use Identity\Http\CanonicalHttpSessionResolver;

header('Cache-Control: no-store');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Content-Security-Policy: default-src \'self\'; script-src \'self\'; style-src \'self\'; img-src \'self\' data:; connect-src \'self\'; frame-ancestors \'none\'; base-uri \'none\'; form-action \'self\'');
try {
    IdentityHttpComposition::registerAutoloader();
    $identity = IdentityHttpComposition::fromProcessEnvironment();
    $actor = (new CanonicalHttpSessionResolver($identity->sessions()))->resolve($_COOKIE);
    if ($actor === null) {
        header('Location: /acceso?next=%2Fprovider%2F', true, 302);
        exit;
    }
} catch (Throwable $error) {
    error_log('PROVIDER_PORTAL_BOOT_ERROR: ' . get_class($error));
    http_response_code(503);
    echo 'El portal de proveedores no está disponible temporalmente.';
    exit;
}
?><!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title>Proveedor · México Médico</title>
  <link rel="stylesheet" href="/assets/css/mxmed-admin-visual-primitives.css">
  <link rel="stylesheet" href="/provider/provider.css">
  <script src="/provider/provider.js" defer></script>
</head>
<body class="mx-admin mx-theme-provider">
  <svg class="provider-icon-sprite" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
    <symbol id="provider-icon-building" viewBox="0 0 24 24"><path d="M4 21V4a1 1 0 0 1 1-1h14a1 1 0 0 1 1 1v17M2 21h20M8 7h2m4 0h2M8 11h2m4 0h2M8 15h2m4 0h2m-6 6v-3h8v3"/></symbol>
    <symbol id="provider-icon-summary" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></symbol>
    <symbol id="provider-icon-team" viewBox="0 0 24 24"><circle cx="9" cy="8" r="3"/><path d="M2.5 20v-2a6.5 6.5 0 0 1 13 0v2H2.5Zm14-14a3 3 0 0 1 0 6m2 2a5 5 0 0 1 3 4.5V20h-4"/></symbol>
    <symbol id="provider-icon-catalog" viewBox="0 0 24 24"><path d="M8 3h8m-6 0v6L4 19a1.5 1.5 0 0 0 1.3 2h13.4a1.5 1.5 0 0 0 1.3-2L14 9V3M7 16h10"/></symbol>
    <symbol id="provider-icon-area" viewBox="0 0 24 24"><path d="M12 21s7-5.8 7-12a7 7 0 1 0-14 0c0 6.2 7 12 7 12Z"/><circle cx="12" cy="9" r="2.5"/></symbol>
    <symbol id="provider-icon-plan" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18M7 15h4"/></symbol>
  </svg>
  <div class="portal-shell">
    <header class="portal-header mx-global-header">
      <div class="portal-header-rail">
        <button id="mobile-nav-toggle" class="mx-gh-toggle" type="button" aria-label="Contraer menú lateral" aria-controls="module-nav" aria-expanded="true" hidden><span aria-hidden="true">☰</span></button>
      </div>
      <div class="portal-header-main mx-gh-left">
        <button id="provider-home" class="mx-gh-brand" type="button" aria-label="Ir al Resumen" title="Ir al Resumen"><img src="/assets/mexico-medico.svg" alt="México Médico"></button>
        <div class="mx-gh-account-summary">
          <div class="mx-gh-identity" aria-label="Identidad de la organización">
            <span class="mx-gh-user-avatar" aria-hidden="true"><svg><use href="#provider-icon-building"/></svg></span>
            <div class="mx-gh-identity-copy">
              <strong id="organization-name-text" class="mx-gh-identity-name-text">Tu organización</strong>
              <div class="mx-gh-identity-meta">
                <span id="organization-role" class="mx-gh-identity-role">Proveedor</span>
                <div class="mx-hb-account">
                  <button id="account-menu-toggle" class="mx-hb-account-trigger" type="button" aria-label="Opciones de cuenta y sesión" aria-expanded="false" aria-controls="account-menu"><span class="mx-hb-session-label">Sesión iniciada</span><span aria-hidden="true">⌄</span></button>
                  <div id="account-menu" class="mx-hb-account-menu" hidden>
                    <button id="account-switch-organization" type="button">Cambiar organización</button>
                    <button id="account-subscription" type="button">Plan y suscripción</button>
                  </div>
                </div>
              </div>
              <div class="mx-gh-organization-switch"><label for="organization-picker">Organización</label><select id="organization-picker" aria-label="Organización actual" hidden></select><span id="single-organization" class="organization-name"></span></div>
            </div>
          </div>
          <button id="provider-plan-entry" class="mx-gh-current-plan" type="button" aria-label="Ver plan y suscripción"><span class="mx-gh-current-plan-label">Tu plan actual</span><span id="provider-plan-name" class="mx-gh-current-plan-name">Por consultar</span></button>
        </div>
      </div>
    </header>
    <main class="portal-main">
      <div class="portal-layout">
        <nav id="module-nav" class="module-nav mm-sidebar" aria-label="Secciones del proveedor" hidden></nav>
        <section class="portal-workspace mm-card" aria-labelledby="page-title">
          <div class="module-title-bar head">
            <header class="mx-panel-subheader mx-panel-subheader--simple">
              <div class="mx-panel-subheader-main"><span id="page-icon" class="mx-panel-subheader-icon" aria-hidden="true"><svg><use href="#provider-icon-summary"/></svg></span><div class="mx-panel-subheader-copy"><h1 id="page-title" class="mx-panel-subheader-title">Resumen</h1><p id="page-subtitle">Estado actual de tu organización.</p></div></div>
              <div id="status-chips" class="status-chips"></div>
            </header>
          </div>
          <div class="module-body body">
            <div id="notice" role="status" aria-live="polite" class="notice" hidden></div>
            <nav id="section-nav" class="section-nav mx-panel-tabs" aria-label="Secciones del módulo" hidden></nav>
            <section id="portal-content" class="content-panel" aria-live="polite"><p class="loading">Cargando organizaciones…</p></section>
          </div>
        </section>
      </div>
    </main>
    <footer class="portal-footer">México Médico · Portal de proveedores</footer>
  </div>
</body>
</html>
