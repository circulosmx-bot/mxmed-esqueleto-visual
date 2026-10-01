<?php
declare(strict_types=1);

use Agenda\Services\HealthcareOrganizationDirectoryService;
use Agenda\Services\HealthcareProviderCoverageService;

require_once __DIR__.'/../../agenda/services/HealthcareOrganizationDirectoryService.php';
require_once __DIR__.'/../../agenda/services/HealthcareProviderCoverageService.php';

$db = getenv('PROV03CB_QA_DB');
if (!is_string($db) || !preg_match('/^prov03cb_qa_[a-f0-9]{12}$/D', $db)) {
    throw new RuntimeException('disposable_db_required');
}
$pdo = new PDO('mysql:host=localhost;dbname='.$db.';charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$directory = new HealthcareOrganizationDirectoryService($pdo);
$coverage = new HealthcareProviderCoverageService($pdo);
$studies = array_map('intval', $pdo->query('SELECT study_type_id FROM clinical_study_types WHERE is_active=1 ORDER BY study_type_id LIMIT 3')->fetchAll(PDO::FETCH_COLUMN));
if (count($studies) !== 3) throw new RuntimeException('study_fixture_missing');
[$a,$b,$c] = $studies;

$pdo->exec("INSERT INTO patients_patients(patient_id,display_name,birthdate) VALUES
    ('p_match','Paciente Secreto QA','1980-01-01'),('p_wrong','Paciente Ajeno QA','1990-01-01')");
$pdo->exec("INSERT INTO patients_doctor_links(link_id,doctor_id,patient_id,status) VALUES
    ('link_match','d_match','p_match','active'),('link_wrong','d_match','p_wrong','active'),
    ('link_other','d_other','p_wrong','active')");
$org = static function (string $id, string $type, bool $eligible = true) use ($pdo,$coverage): void {
    $pdo->prepare('INSERT INTO medical_groups(group_id,organization_type_key,canonical_name,display_name,status,source)
        VALUES(:id,:type,:canonical,:display,:status,:source)')->execute([
            'id'=>$id,'type'=>$type,'canonical'=>$id,'display'=>$id,'status'=>'verified','source'=>'operator_created',
        ]);
    $coverage->initializeProvider($id);
    if ($eligible) {
        $coverage->setProviderOperationalState($id, 'ACTIVE');
        $coverage->setProviderVerification($id, 'VERIFIED', 'operator_qa');
    }
};
$place = static function (string $group, string $name, string $postal, array $offerings,
    bool $verifyLocation = true) use ($directory): array {
    $location = $directory->createLocation($group, ['branch_name'=>$name,'postal_code'=>$postal,
        'street'=>'Calle QA','exterior_number'=>'10','municipality'=>'Municipio QA','state_name'=>'Estado QA']);
    if ($verifyLocation) $directory->setLocationVerification($group, $location['location_uuid'], 'VERIFIED', 'operator_qa');
    foreach ($offerings as $studyId => $mode) {
        $directory->createOffering($group, $location['location_uuid'], (int)$studyId, ['service_mode'=>$mode]);
        $directory->setOfferingVerification($group, $location['location_uuid'], (int)$studyId, 'VERIFIED', 'operator_qa');
    }
    return $location;
};
$org('org_lab','LABORATORY');
$org('org_hospital','HOSPITAL');
$org('org_clinic','CLINIC');
$org('org_unverified','LABORATORY',false);
$org('org_inactive','HOSPITAL');
$coverage->setProviderOperationalState('org_inactive','INACTIVE');
$org('org_rejected','LABORATORY');
$coverage->setProviderVerification('org_rejected','REJECTED','operator_qa');
$pdo->exec("INSERT INTO medical_groups(group_id,organization_type_key,canonical_name,display_name,status,source)
    VALUES('org_missing','LABORATORY','org_missing','org_missing','verified','operator_created')");
// Disposable commercial fixture: all organization eligibility states get the
// same matching product, so the PROV03 operational/verification tests stay distinct.
$pdo->exec("INSERT INTO subscription_plans(plan_code,plan_label,billing_period,duration_days,product_family,source)
    VALUES('provider_qa_internal','Provider QA','annual',365,'PROVIDER_ORGANIZATION','disposable_qa')");
$pdo->exec("INSERT INTO provider_subscription_plan_capabilities(plan_code,billing_period,capability)
    VALUES('provider_qa_internal','annual','provider_matching_participation')");
foreach (['org_lab','org_hospital','org_clinic','org_unverified','org_inactive','org_rejected','org_missing'] as $groupId) {
    $pdo->prepare("INSERT INTO profile_subscriptions
        (subscription_id,entity_type,entity_id,plan_code,contracted_plan_code,effective_plan_code,
         billing_period,starts_at,expires_at,status,source)
        VALUES(UUID(),'provider_organization',?,'provider_qa_internal','provider_qa_internal',
          'provider_qa_internal','annual',DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 DAY),
          DATE_ADD(UTC_TIMESTAMP(),INTERVAL 30 DAY),'active','disposable_qa')")
        ->execute([$groupId]);
}
$full = $place('org_lab','Lab Full','20000',[$a=>'ON_SITE',$b=>'ON_SITE',$c=>'ON_SITE']);
$ab = $place('org_lab','Lab AB','20000',[$a=>'ON_SITE',$b=>'ON_SITE']);
$ac = $place('org_lab','Lab AC','20000',[$a=>'ON_SITE',$c=>'ON_SITE']);
$hospital = $place('org_hospital','Hospital Full','20001',[$a=>'ON_SITE',$b=>'ON_SITE',$c=>'ON_SITE']);
$clinic = $place('org_clinic','Clinic A','20000',[$a=>'ON_SITE']);
$unverifiedOrg = $place('org_unverified','Unverified Org','20000',[$a=>'ON_SITE']);
$inactiveOrg = $place('org_inactive','Inactive Org','20000',[$a=>'ON_SITE']);
$rejectedOrg = $place('org_rejected','Rejected Org','20000',[$a=>'ON_SITE']);
$missingOrg = $place('org_missing','Missing Provider Status','20000',[$a=>'ON_SITE']);
$unverifiedLoc = $place('org_lab','Unverified Location','20000',[$a=>'ON_SITE'],false);
$inactiveLoc = $place('org_lab','Inactive Location','20000',[$a=>'ON_SITE']);
$directory->updateLocation('org_lab',$inactiveLoc['location_uuid'],['operational_state'=>'INACTIVE']);
$rejectedLoc = $place('org_lab','Rejected Location','20000',[$a=>'ON_SITE']);
$directory->setLocationVerification('org_lab',$rejectedLoc['location_uuid'],'REJECTED','operator_qa');
$unverifiedOffering = $place('org_lab','Unverified Offering','20000',[]);
$directory->createOffering('org_lab',$unverifiedOffering['location_uuid'],$a);
$inactiveOffering = $place('org_lab','Inactive Offering','20000',[$a=>'ON_SITE']);
$directory->updateOffering('org_lab',$inactiveOffering['location_uuid'],$a,['operational_state'=>'INACTIVE']);
$rejectedOffering = $place('org_lab','Rejected Offering','20000',[$a=>'ON_SITE']);
$directory->setOfferingVerification('org_lab',$rejectedOffering['location_uuid'],$a,'REJECTED','operator_qa');
$home = $place('org_lab','Home Service','99999',[$a=>'HOME_SERVICE']);
$homeArea = $coverage->createServiceArea('org_lab',$home['location_uuid'],$a,'POSTAL_CODE','20000');
$coverage->setServiceAreaVerification('org_lab',$home['location_uuid'],$a,(int)$homeArea['service_area_id'],'VERIFIED','operator_qa');
$homeUnverifiedArea = $coverage->createServiceArea('org_lab',$home['location_uuid'],$a,'POSTAL_CODE','20001');
$mobile = $place('org_lab','Mobile Service','99999',[$b=>'MOBILE']);
$mobileArea = $coverage->createServiceArea('org_lab',$mobile['location_uuid'],$b,'POSTAL_CODE','20000');
$coverage->setServiceAreaVerification('org_lab',$mobile['location_uuid'],$b,(int)$mobileArea['service_area_id'],'VERIFIED','operator_qa');

$uuid = static function (): string {
    $hex = bin2hex(random_bytes(16));
    return substr($hex,0,8).'-'.substr($hex,8,4).'-4'.substr($hex,13,3).'-8'.substr($hex,17,3).'-'.substr($hex,20,12);
};
$item = static function (int $sequence, ?int $studyId, string $name) use ($uuid): array {
    return ['order_item_id'=>$uuid(),'sequence'=>$sequence,'study_type_id'=>$studyId,
        'study_display_name'=>$name,'study_category'=>'LABORATORIO'];
};
$insertOrder = static function (array $payload) use ($pdo,$uuid): string {
    $documentUuid = $uuid();
    $pdo->prepare('INSERT INTO clinical_documents(document_uuid,document_type,title,version,status,patient_id,
        payload_json,event_datetime,created_at,generated_at,created_by_user_id)
        VALUES(:uuid,:type,:title,1,:status,:patient_id,:payload,NOW(),NOW(),NOW(),:user_id)')->execute([
            'uuid'=>$documentUuid,'type'=>'lab_order','title'=>'Orden QA','status'=>'generated',
            'patient_id'=>'p_match','payload'=>json_encode($payload,JSON_THROW_ON_ERROR),'user_id'=>'u_match',
        ]);
    return $documentUuid;
};
$fullItems = [$item(1,$a,'Estudio A'),$item(2,$b,'Estudio B'),$item(3,$c,'Estudio C')];
$fullOrder = $insertOrder(['order_payload_version'=>2,'order_items'=>$fullItems,
    'requested_studies'=>array_column($fullItems,'study_display_name'),'indication'=>'INDICACION_PRIVADA_QA']);
$customItems = [$item(1,$a,'Estudio A'),$item(2,$b,'Estudio B'),$item(3,null,'Estudio C')];
$customOrder = $insertOrder(['order_payload_version'=>2,'order_items'=>$customItems,
    'requested_studies'=>array_column($customItems,'study_display_name')]);
$legacyOrder = $insertOrder(['requested_studies'=>['Estudio A','Estudio B']]);

echo json_encode(['studies'=>[$a,$b,$c], 'orders'=>['full'=>$fullOrder,'custom'=>$customOrder,'legacy'=>$legacyOrder],
    'item_ids'=>array_column($fullItems,'order_item_id'), 'custom_item_ids'=>array_column($customItems,'order_item_id'),
    'locations'=>['full'=>$full['location_uuid'],'ab'=>$ab['location_uuid'],'ac'=>$ac['location_uuid'],
        'hospital'=>$hospital['location_uuid'],'clinic'=>$clinic['location_uuid'],
        'unverified_org'=>$unverifiedOrg['location_uuid'],'inactive_org'=>$inactiveOrg['location_uuid'],
        'rejected_org'=>$rejectedOrg['location_uuid'],'unverified_loc'=>$unverifiedLoc['location_uuid'],
        'missing_org'=>$missingOrg['location_uuid'],
        'inactive_loc'=>$inactiveLoc['location_uuid'],'rejected_loc'=>$rejectedLoc['location_uuid'],
        'unverified_offering'=>$unverifiedOffering['location_uuid'],'inactive_offering'=>$inactiveOffering['location_uuid'],
        'rejected_offering'=>$rejectedOffering['location_uuid'],'home'=>$home['location_uuid'],
        'mobile'=>$mobile['location_uuid']],
    'home_area_id'=>(int)$homeArea['service_area_id'], 'home_unverified_area_id'=>(int)$homeUnverifiedArea['service_area_id']],
    JSON_THROW_ON_ERROR);
