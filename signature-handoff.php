<?php
declare(strict_types=1);
header('Cache-Control: no-store');header('Referrer-Policy: no-referrer');header('X-Content-Type-Options: nosniff');header("Content-Security-Policy: default-src 'none'; script-src 'self'; style-src 'self'; connect-src 'self'; img-src 'self' data:; frame-ancestors 'none'; base-uri 'none'; form-action 'none'");
if($_GET){http_response_code(403);exit;}
?><!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Firma temporal — México Médico</title><link rel="stylesheet" href="/assets/css/signature-handoff.css"></head>
<body><main id="signature-device"><h1>Firma temporal</h1><p id="signature-device-status" role="status" aria-live="polite">Validando enlace…</p>
<section id="signature-device-editor" hidden><p>Traza tu firma. Puedes usar la pantalla en horizontal.</p><button type="button" id="signature-device-fullscreen">Firmar a pantalla completa</button><canvas id="signature-device-pad" width="1000" height="300" tabindex="0" aria-label="Área para dibujar tu firma"></canvas><div class="signature-device-actions"><button type="button" id="signature-device-clear">Limpiar</button><button type="button" id="signature-device-cancel">Cancelar</button><button type="button" id="signature-device-save" class="primary">Guardar firma</button></div></section>
</main><script src="/assets/js/signature-handoff-device.js"></script></body></html>
