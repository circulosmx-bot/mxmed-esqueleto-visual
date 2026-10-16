<?php
declare(strict_types=1);
require_once __DIR__.'/../../modules/identity/http/IdentityHttpComposition.php';
require_once __DIR__.'/../../modules/identity/http/CanonicalClinicalIdentityResolver.php';

function clinical_identity_authority(string $capabilityId):array
{
    static $cache=[];if(isset($cache[$capabilityId]))return $cache[$capabilityId];
    try{$r=\Identity\Http\CanonicalClinicalIdentityResolver::fromProcessEnvironment()->resolve($_COOKIE,$capabilityId);
        return $cache[$capabilityId]=['context'=>$r->context?->toArray(),'reason'=>$r->reason,'http_status'=>$r->httpStatus];
    }catch(\Throwable){return $cache[$capabilityId]=['context'=>null,'reason'=>'authority_unavailable','http_status'=>503];}
}

/** Legacy authority is confined to isolated CLI harnesses outside normal-local. */
function clinical_isolated_qa_authority():?array
{
    if(PHP_SAPI!=='cli'||getenv('MXMED_NORMAL_LOCAL_IDENTITY_EXPLICIT')==='1')return null;
    if(session_status()===PHP_SESSION_NONE)@session_start();
    $doctorId=trim((string)($_SESSION['doctor_id']??$_SESSION['active_doctor_id']??$_SESSION['mxmed_doctor_id']??''));
    $userId=trim((string)($_SESSION['user_id']??$_SESSION['mxmed_user_id']??$_SESSION['auth_user_id']??$_SESSION['actor_user_id']??''));
    return $doctorId!==''&&$userId!==''?['doctor_id'=>$doctorId,'account_id'=>$userId,'membership_id'=>'isolated_qa','role_code'=>'collaborator','scope_code'=>'profile','session_id'=>'isolated_qa']:null;
}

function clinical_request_identity(string $capabilityId):array
{
    $qa=clinical_isolated_qa_authority();
    if($qa!==null)return ['context'=>$qa,'reason'=>'isolated_qa','http_status'=>200];
    if(PHP_SAPI==='cli'&&getenv('MXMED_NORMAL_LOCAL_IDENTITY_EXPLICIT')!=='1')
        return ['context'=>null,'reason'=>'authentication required','http_status'=>401];
    return clinical_identity_authority($capabilityId);
}

/** The existing canonical resolver, shared by standalone legacy HTTP entries. */
function clinical_legacy_http_identity():?array
{
    if(PHP_SAPI==='cli'&&!\Identity\Http\IdentityHttpComposition::canonicalRuntimeRequested())return null;
    $authority=clinical_request_identity('clinical_record');
    if(!is_array($authority['context']))mxmed_json_response(['ok'=>false,'error'=>'authentication_required'],(int)$authority['http_status']);
    return $authority['context'];
}

function clinical_legacy_patient_scope(PDO $pdo,?array $identity,string $patientId):void
{
    if($identity!==null&&!clinical_has_active_doctor_patient_link($pdo,$identity['doctor_id'],$patientId))
        mxmed_json_response(['ok'=>false,'error'=>'not_found'],404);
}

function clinical_has_active_doctor_patient_link(PDO $pdo, string $doctorId, string $patientId): bool
{
    $safeDoctorId = trim($doctorId);
    $safePatientId = trim($patientId);
    if ($safeDoctorId === '' || $safePatientId === '') {
        return false;
    }

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM patients_doctor_links
        WHERE doctor_id = :doctor_id
          AND patient_id = :patient_id
          AND status = 'active'
    ");
    $stmt->execute([
        ':doctor_id' => $safeDoctorId,
        ':patient_id' => $safePatientId,
    ]);
    return (int)$stmt->fetchColumn() > 0;
}
