<?php
declare(strict_types=1);

/** Opt-in local Director route only. No patient/observation/database writes. */
function vitalref01_review_route(string $path): bool
{
    if (!in_array($path,['/__director_vitalref01_context','/__director_vitalref01_review.js'],true)) return false;
    require_once __DIR__.'/plan02br2_review_context.php';
    plan02br2_review_context(); // Loopback, disposable DB, established cohort guard.
    header('Cache-Control: private, no-store');
    if ($path==='/__director_vitalref01_review.js') {
        header('Content-Type: application/javascript; charset=utf-8');
        readfile(__DIR__.'/vitalref01_review.js');
        return true;
    }
    if (session_status()!==PHP_SESSION_ACTIVE) session_start();
    $authorized=($_SESSION['doctor_id'] ?? '')==='1';
    session_write_close();
    header('Content-Type: application/json; charset=utf-8');
    $context=(string)($_GET['context'] ?? '');
    if (!$authorized || ($_SERVER['REQUEST_METHOD'] ?? '')!=='GET' || !in_array($context,['child','adolescent','infant','missing'],true) || count($_GET)!==1) {
        http_response_code(404);echo json_encode(['ok'=>false,'data'=>null]);return true;
    }
    require_once __DIR__.'/../../../api/_lib/clinical_vital_references.php';
    $today=new DateTimeImmutable('today',new DateTimeZone('America/Mexico_City'));
    $dob=['child'=>$today->modify('-8 years')->format('Y-m-d'),'adolescent'=>$today->modify('-13 years')->format('Y-m-d'),'infant'=>$today->format('Y-m-d'),'missing'=>null][$context];
    echo json_encode(['ok'=>true,'data'=>clinical_vital_references_resolve($dob,$today),'meta'=>['review_only'=>true,'context'=>$context]],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    return true;
}
