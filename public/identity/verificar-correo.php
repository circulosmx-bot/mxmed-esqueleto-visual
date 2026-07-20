<?php
declare(strict_types=1);
require_once __DIR__ . '/../../modules/identity/http/CsrfTokenService.php';
$csrf = (new Identity\Http\CsrfTokenService((string)getenv('MXMED_PREVIEW_PEPPER')))->issue();
header('Cache-Control: no-store');
header('Pragma: no-cache');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title>Verifica tu correo electrónico · Prototipo UI-3</title>
  <link rel="stylesheet" href="/public/identity/identity-access.css">
</head>
<body data-flow="verify">
  <div class="prototype-shell">
    <header class="prototype-header"><a class="prototype-brand" href="index.html" aria-label="México Médico · inicio del prototipo"><img src="../../../assets/mexico-medico.svg" alt="México Médico"></a><span class="prototype-badge">Prototipo UI-3</span></header>
    <main class="prototype-main">
      <section class="prototype-card auth-card" aria-labelledby="verify-title">
        <p class="auth-card__eyebrow">Identidad y acceso</p>
        <h1 id="verify-title">Verifica tu correo electrónico</h1>
        <p class="auth-card__intro">Confirma tu correo para activar tu cuenta de forma segura.</p>
        <div class="state-panel" data-state-panel aria-live="polite"><strong data-state-title></strong><span data-state-copy></span></div>
        <form class="auth-form" method="post" action="/api/identity/index.php/email-verification" data-identity-form data-flow="verify">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
          <div class="form-actions"><button class="button" type="submit" data-submit>Verificar correo</button></div>
          <p class="auth-links" data-form-note aria-live="polite"><a href="login.html">Volver a iniciar sesión</a></p>
        </form>
      </section>
    </main>
    <footer class="prototype-footer"><a href="index.html">Volver al índice del prototipo</a></footer>
  </div>
  <script src="/public/identity/identity-access.js"></script>
</body>
</html>
