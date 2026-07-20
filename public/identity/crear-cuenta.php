<?php
declare(strict_types=1);
require_once __DIR__ . '/../../modules/identity/http/CsrfTokenService.php';
$csrf = (new Identity\Http\CsrfTokenService((string)getenv('MXMED_PREVIEW_PEPPER')))->issue();
header('Cache-Control: no-store');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title>Crea tu cuenta · Prototipo UI-3</title>
  <link rel="stylesheet" href="/public/identity/identity-access.css">
</head>
<body data-flow="create">
  <div class="prototype-shell">
    <header class="prototype-header"><a class="prototype-brand" href="index.html" aria-label="México Médico · inicio del prototipo"><img src="../../../assets/mexico-medico.svg" alt="México Médico"></a><span class="prototype-badge">Prototipo UI-3</span></header>
    <main class="prototype-main">
      <section class="prototype-card auth-card" aria-labelledby="create-title">
        <p class="auth-card__eyebrow">Identidad y acceso</p>
        <h1 id="create-title">Crea tu cuenta</h1>
        <p class="auth-card__intro">Comienza a administrar tu presencia en México Médico.</p>
        <div class="state-panel" data-state-panel aria-live="polite"><strong data-state-title></strong><span data-state-copy></span></div>
        <form class="auth-form" method="post" action="/api/identity/index.php/registration-request" data-identity-form data-flow="create">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
          <div class="field"><label for="create-email">Correo electrónico</label><input id="create-email" name="email" type="email" autocomplete="email" required></div>
          <div class="field"><label for="create-password">Contraseña</label><div class="password-wrap"><input id="create-password" name="password" type="password" autocomplete="new-password" required><button class="icon-button" type="button" data-password-toggle aria-controls="create-password" aria-label="Mostrar contraseña">Mostrar</button></div></div>
          <div class="field"><label for="create-confirm">Confirmar contraseña</label><div class="password-wrap"><input id="create-confirm" name="password_confirmation" type="password" autocomplete="new-password" required><button class="icon-button" type="button" data-password-toggle aria-controls="create-confirm" aria-label="Mostrar contraseña">Mostrar</button></div></div>
          <div class="consent-list">
            <div class="consent"><input id="terms" name="terms" type="checkbox" required><label for="terms">He leído y acepto los <a href="#terms">Términos y condiciones</a>.</label></div>
            <div class="consent"><input id="privacy" name="privacy" type="checkbox" required><label for="privacy">He leído y acepto el <a href="#privacy">Aviso de privacidad</a>.</label></div>
          </div>
          <div class="form-actions"><button class="button" type="submit" data-submit>Crear cuenta</button></div>
          <p class="auth-links" data-form-note aria-live="polite"><a href="login.html">¿Ya tienes una cuenta? Inicia sesión</a></p>
        </form>
      </section>
    </main>
    <footer class="prototype-footer"><a href="index.html">Volver al índice del prototipo</a></footer>
  </div>
  <script src="/public/identity/identity-access.js"></script>
</body>
</html>
