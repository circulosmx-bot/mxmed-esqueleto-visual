<?php
declare(strict_types=1);
// CLI renderer for synthetic original-template QA. No runtime switch or public route.
if (PHP_SAPI!=='cli'||getenv('MXMED_PUBLIC_ENROLLMENT_SYNTHETIC_QA')!=='1')exit(1);
$root=dirname(__DIR__,3);
require_once $root.'/modules/profiles/services/PublicProfilePlanCapabilities.php';
require_once $root.'/modules/profiles/services/PublicProfileClaimEligibility.php';
require_once $root.'/modules/profiles/services/PublicEnrollmentPresentation.php';
function publicEnrollmentFixture(string $id):array {
    $allowed=in_array($id,['synthetic-free','synthetic-owned','synthetic-paid'],true);
    if(!$allowed)return ['ok'=>false,'data'=>null,'error'=>'profile_not_found'];
    $plan=$id==='synthetic-paid'?'optimum':'free';
    $claim=\Profiles\Services\PublicProfileClaimEligibility::build($id,['source'=>'profiles_doctors','plan_code'=>$plan],true,
        ['source_ready'=>true,'has_active_owner'=>$id==='synthetic-owned']);
    $contract=\Profiles\Services\PublicProfilePlanCapabilities::build($plan,['has_public_profile'=>true,
        'ownership_source_ready'=>true,'profile_is_administered'=>$id==='synthetic-owned','claim_source_ready'=>$claim['eligible']]);
    return ['ok'=>true,'data'=>$contract+[
        'profile'=>['doctor_id'=>$id,'is_public'=>true,'status'=>'active'],
        'identity'=>['display_name'=>'Profesional Sintética de Prueba','prefix'=>'Dra.','professional_designation'=>'Especialidad sintética'],
        'professional'=>['professional_license'=>'SYNTHETIC-NOT-A-LICENSE','specialty_primary'=>'Especialidad sintética'],
        'specialties'=>[['name_es'=>'Especialidad sintética']],
        'consultorios'=>[['consultorio_id'=>'synthetic-office','name'=>'Consultorio de prueba','is_public'=>true,'is_active'=>true,'city'=>'Ciudad de prueba','state'=>'Estado de prueba']],
        'seo'=>[],'feature_flags'=>[]
    ]+['claim'=>array_replace($contract['claim'],['claim_url'=>$claim['url']])]];
}
// Replace the inherited claim contract explicitly, since array-union preserves left keys.
function publicEnrollmentPayload(string $id):array {
    $dto=publicEnrollmentFixture($id);
    if(($dto['ok']??false)===true){$eligible=$id==='synthetic-free';$dto['data']['claim']['claim_url']=$eligible?'/public/identity/perfil-medico.php?entry=claim&profile='.$id:null;}
    return $dto;
}
$path=$argv[1]??'';parse_str($argv[2]??'',$_GET);
if($path==='/fixture-dto'){echo json_encode(publicEnrollmentPayload((string)($_GET['profile']??'')),JSON_THROW_ON_ERROR);exit;}
if($path==='/profiles/doctor.php'){$_SERVER['HTTP_HOST']=(string)parse_url((string)getenv('MXMED_PROFILES_API_BASE'),PHP_URL_HOST);require $root.$path;exit;}
if($path==='/profiles/listing.php'){
    $publicDiscoveryResponse=['ok'=>true,'data'=>['items'=>[]],'meta'=>['filters'=>['state'=>'Estado de prueba','city'=>'Ciudad de prueba','specialty'=>'Especialidad sintética'],'pagination'=>['page'=>1,'page_size'=>20,'total_count'=>0,'total_pages'=>0]]];
    require $root.$path;exit;
}
if($path==='/public/identity/perfil-medico.php'){
    $entry=\Profiles\Services\PublicEnrollmentPresentation::resolve($_GET,publicEnrollmentPayload(...));
    if($entry===null)throw new RuntimeException('synthetic_public_context_required');
    $state=['enabled'=>false,'staff'=>false,'email_verified'=>false,'phone_verified'=>false,'requests'=>[],'request'=>null,
        'message'=>$entry['message'],'public_entry'=>$entry,'csrf_token'=>'','expires_at'=>0];
    $staff=false;$title=$entry['title'];require $root.'/partials/identity/physician-onboarding.php';exit;
}
exit(1);
