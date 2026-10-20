<?php
declare(strict_types=1);
try{
 require_once __DIR__.'/ase-adm02-workflow-runtime.php';
 if(getenv('MXMED_STAFF_CONTROL04')!=='1')throw new RuntimeException('staff_control04_owned_required');
 $staffControl04Services=staffWorkflowOwnedServices();
 $path=parse_url($_SERVER['REQUEST_URI']??'',PHP_URL_PATH);
 if($path==='/development/interfaces.php'){
  \Identity\Team\Http::headers();
  echo '<!doctype html><html lang="es"><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Índice de interfaces · México Médico</title><link rel="stylesheet" href="/assets/css/staff-development-index.css"></head><body>';
  require __DIR__.'/../internal/development/interfaces.php';echo '</body></html>';return true;
 }
 if(in_array($path,['/internal/staff-workspace/index.php','/internal/media-review/index.php','/staff-admission.php','/public/identity/acceso.php'],true)){
  ob_start();require __DIR__.'/../internal/development/interfaces.php';$index=ob_get_clean();
  ob_start(static function($html)use($index){if(!str_contains($html,'<body'))return $html;$html=str_replace('</head>','<link rel="stylesheet" href="/assets/css/staff-development-index.css"></head>',$html);return preg_replace('/(<body[^>]*>)/','$1'.$index,$html,1);});
 }
 return require __DIR__.'/ase-advisor-workspace02-router.php';
}catch(Throwable $e){http_response_code(503);header('Cache-Control: no-store');echo '{"error":"isolated_service_unavailable"}';return true;}
