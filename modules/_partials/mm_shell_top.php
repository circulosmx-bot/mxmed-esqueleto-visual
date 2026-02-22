<?php
$pageTitle = isset($pageTitle) && is_string($pageTitle) && trim($pageTitle) !== ''
    ? trim($pageTitle)
    : 'MXMed';
$extraHead = isset($extraHead) && is_string($extraHead) ? $extraHead : '';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<?php echo $extraHead; ?>
</head>
<body>
<div class="header-top">
  <div class="wrap page">
    <img src="/assets/mexico-medico.svg" alt="México Médico">
  </div>
</div>
<div class="header-mid">
  <div class="wrap page">
    <div class="hm-left">
      <span class="activo">activo</span>
      <span class="modo">modo</span>
      <span class="optimo">Óptimo</span>
    </div>
    <div class="user-id">
      <span class="chk" aria-hidden="true">
        <svg viewBox="0 0 24 24" focusable="false">
          <path d="M5 12.5l4.8 4.8L19 8" fill="none" stroke="white" stroke-width="3.6" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </span>
      <span class="name">MXMed</span>
    </div>
  </div>
</div>
<div class="header-bottom">
  <div class="wrap page">
    <div class="hb-left">
      <span class="vigencia">Panel clínico interno</span>
    </div>
    <div class="hb-right">
      <a class="logout" href="/index.html"><i class="bi bi-house"></i> Inicio</a>
    </div>
  </div>
</div>
<div class="mm-wrap">
  <div class="mm-grid page">
    <main class="mm-main">
