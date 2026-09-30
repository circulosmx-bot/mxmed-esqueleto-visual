<?php
declare(strict_types=1);

require_once __DIR__ . '/clinical_treatments.php';

/** Handles only TRT04 routes; legacy clinical routing remains unchanged. */
function clinical_treatment_route(string $method, array $segments): bool
{
    if (($segments[0] ?? null) !== 'patients' || ($segments[2] ?? null) !== 'treatments') return false;
    $route = implode('/', $segments);
    $context = clinical_require_doctor_context($route);
    if ($context === null) return true;
    $patientId = trim(rawurldecode((string)($segments[1] ?? '')));
    $accountId = trim((string)($_SESSION['account_id'] ?? $context['user_id']));
    try {
        $service = new ClinicalTreatments(clinical_documents_pdo(),$context['doctor_id'],$patientId,$accountId);
        $area = $segments[3] ?? '';
        $token = $segments[4] ?? null;
        $action = $segments[5] ?? null;
        $count = count($segments);
        $id = $token !== null && ctype_digit((string)$token) && (int)$token>0 ? (int)$token : null;
        $body = [];
        if (!in_array($method,['GET','HEAD'],true)) {
            $parsed = clinical_read_json_body();
            if (($parsed['ok'] ?? false) !== true || !is_array($parsed['data'] ?? null) || array_is_list($parsed['data'])) {
                throw new ClinicalTreatmentException('INVALID_JSON_BODY');
            }
            $body = $parsed['data'];
        }
        $key = trim((string)($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? ''));
        $data = null;
        if ($area==='plans') {
            if ($count===4 && $method==='GET') $data=$service->listPlans();
            elseif ($count===4 && $method==='POST') $data=$service->createPlan($body,$key);
            elseif ($count===5 && $id!==null && $method==='GET') $data=$service->getPlan($id);
            elseif ($count===6 && $id!==null && $action==='transition' && $method==='POST') {
                $data=$service->transitionPlan($id,(string)($body['status']??''),(int)($body['expected_version']??0),isset($body['reason'])?(string)$body['reason']:null,$key);
            }
        } elseif ($area==='sessions') {
            if ($count===4 && $method==='GET') $data=$service->listSessions();
            elseif ($count===4 && $method==='POST') $data=$service->createDraft($body,$key);
            elseif ($count===5 && $token==='standalone-history' && $method==='GET') $data=$service->standaloneHistory();
            elseif ($count===5 && $id!==null && $method==='GET') $data=$service->getSession($id);
            elseif ($count===5 && $id!==null && $method==='PATCH') $data=$service->editDraft($id,(int)($body['expected_version']??0),$body);
            elseif ($count===6 && $id!==null && $action==='complete' && $method==='POST') $data=$service->complete($id,(int)($body['expected_version']??0),$body,$key);
            elseif ($count===6 && $id!==null && $action==='void' && $method==='POST') $data=$service->void($id,(int)($body['expected_version']??0),(string)($body['reason']??''),$key);
            elseif ($count===6 && $id!==null && $action==='correct' && $method==='POST') $data=$service->correct($id,(int)($body['expected_version']??0),$body,$key);
        }
        if ($data===null) throw new ClinicalTreatmentException('ROUTE_NOT_FOUND',404);
        $replay=is_array($data)&&array_key_exists('idempotency_replay',$data) ? $data['idempotency_replay'] : false;
        clinical_send_response(['ok'=>true,'data'=>$data,'meta'=>['route'=>$route,'idempotency_replay'=>$replay]],$method==='POST'&&!$replay?201:200);
    } catch (ClinicalTreatmentException $e) {
        clinical_send_response(['ok'=>false,'error'=>$e->codeName,'message'=>$e->codeName,'meta'=>['route'=>$route]],$e->httpStatus);
    } catch (ClinicalIdempotencyException $e) {
        clinical_send_response(['ok'=>false,'error'=>$e->errorCode,'message'=>$e->getMessage(),'meta'=>['route'=>$route]],$e->httpStatus);
    } catch (InvalidArgumentException $e) {
        clinical_send_response(['ok'=>false,'error'=>$e->getMessage(),'message'=>$e->getMessage(),'meta'=>['route'=>$route]],400);
    }
    return true;
}
