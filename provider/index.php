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
  <link rel="stylesheet" href="/provider/provider.css">
  <script src="/provider/provider.js" defer></script>
</head>
<body>
  <div class="portal-shell">
    <header class="portal-header">
      <div class="brand"><img src="/assets/mexico-medico.svg" alt="México Médico"><span class="brand-divider" aria-hidden="true"></span><span class="brand-context">Proveedor</span></div>
      <div class="header-right"><label for="organization-picker">Organización</label><select id="organization-picker" aria-label="Organización actual" hidden></select><span id="single-organization" class="organization-name"></span></div>
    </header>
    <main class="portal-main">
      <div class="page-intro"><div><p class="eyebrow">PORTAL DE PROVEEDORES</p><h1 id="page-title">Tu organización</h1><p id="page-subtitle">Consulta y administra la configuración disponible para tu cuenta.</p></div><div id="status-chips" class="status-chips"></div></div>
      <div id="notice" role="status" aria-live="polite" class="notice" hidden></div>
      <nav id="module-nav" class="module-nav" aria-label="Secciones del proveedor" hidden></nav>
      <section id="portal-content" class="content-panel" aria-live="polite"><p class="loading">Cargando organizaciones…</p></section>
    </main>
    <footer class="portal-footer">México Médico · Portal de proveedores</footer>
  </div>
</body>
</html>
