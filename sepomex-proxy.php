<?php
// Simple proxy para consultar SEPOMEX desde el servidor y evitar CORS en el navegador
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$cp = isset($_GET['cp']) ? preg_replace('/\D/', '', $_GET['cp']) : '';
if (strlen($cp) !== 5) {
  http_response_code(400);
  echo json_encode(['error' => 'invalid_cp']);
  exit;
}

$url = "https://api-sepomex.hckdrk.mx/query/info_cp/{$cp}?type=simplified";

// Intentar con cURL si está disponible
if (function_exists('curl_init')) {
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
  curl_setopt($ch, CURLOPT_TIMEOUT, 15);
  curl_setopt($ch, CURLOPT_USERAGENT, 'mxmed-esqueleto/sepomex-proxy');
  $body = curl_exec($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err  = curl_error($ch);
  curl_close($ch);
  if ($body === false || $code >= 400) {
    http_response_code(502);
    echo json_encode(['error' => 'upstream_error', 'status' => $code, 'detail' => $err]);
    exit;
  }
  echo $body;
  exit;
}

// Fallback a file_get_contents
$ctx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'timeout' => 15,
    'header'  => "User-Agent: mxmed-esqueleto/sepomex-proxy\r\n"
  ]
]);
$body = @file_get_contents($url, false, $ctx);
if ($body === false) {
  http_response_code(502);
  echo json_encode(['error' => 'upstream_unreachable']);
  exit;
}
echo $body;

