<?php
// Proxy simple para geocodificar con Nominatim (OSM)
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
if($q === ''){ http_response_code(400); echo json_encode(['error'=>'missing_query']); exit; }

$params = http_build_query([
  'format' => 'jsonv2',
  'q' => $q,
  'addressdetails' => 1,
  'limit' => 1,
  'countrycodes' => 'mx',
]);
$url = 'https://nominatim.openstreetmap.org/search?' . $params;

if(function_exists('curl_init')){
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
  curl_setopt($ch, CURLOPT_TIMEOUT, 15);
  curl_setopt($ch, CURLOPT_USERAGENT, 'mxmed-esqueleto/geo-proxy (+https://github.com/circulosmx-bot)');
  $res = curl_exec($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err = curl_error($ch);
  curl_close($ch);
  if($res === false || $code >= 400){ http_response_code(502); echo json_encode(['error'=>'upstream_error','status'=>$code,'detail'=>$err]); exit; }
  echo $res; exit;
}

$ctx = stream_context_create([
  'http' => [ 'method'=>'GET', 'timeout'=>15, 'header'=>"User-Agent: mxmed-esqueleto/geo-proxy\r\n" ]
]);
$res = @file_get_contents($url, false, $ctx);
if($res === false){ http_response_code(502); echo json_encode(['error'=>'upstream_unreachable']); exit; }
echo $res;

