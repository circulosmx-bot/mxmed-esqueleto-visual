<?php
declare(strict_types=1);
use Agenda\Services\HealthcareOrganizationDirectoryService;
use Agenda\Services\HealthcareProviderCoverageService;
use Agenda\Services\HealthcareStudyProviderMatchingService;
require_once __DIR__.'/../../agenda/services/HealthcareOrganizationDirectoryService.php';
require_once __DIR__.'/../../agenda/services/HealthcareProviderCoverageService.php';
require_once __DIR__.'/../../agenda/services/HealthcareStudyProviderMatchingService.php';
$db=getenv('FUNCCAT02B_QA_DB');
if (!is_string($db)||!preg_match('/^funccat02b_qa_[a-f0-9]{8}$/D',$db)) throw new RuntimeException('disposable_db_required');
$pdo=new PDO('mysql:host=localhost;dbname='.$db.';charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$ids=[];
foreach ($pdo->query("SELECT study_type_key,study_type_id FROM clinical_study_types WHERE category_key='FUNCION_DIGESTIVA'") as $row) $ids[$row['study_type_key']]=(int)$row['study_type_id'];
if (count($ids)!==2) throw new RuntimeException('catalog_fixture_invalid');
$directory=new HealthcareOrganizationDirectoryService($pdo);$coverage=new HealthcareProviderCoverageService($pdo);$matcher=new HealthcareStudyProviderMatchingService($pdo);
$pdo->exec("INSERT INTO subscription_plans(plan_code,plan_label,billing_period,duration_days,product_family,source) VALUES('func_qa_internal','Functional QA','annual',365,'PROVIDER_ORGANIZATION','disposable_qa')");
$pdo->exec("INSERT INTO provider_subscription_plan_capabilities(plan_code,billing_period,capability) VALUES('func_qa_internal','annual','provider_matching_participation')");
$locations=[];
foreach ($ids as $key=>$studyId) {
    $group='org_func_'.$key;
    $pdo->prepare("INSERT INTO medical_groups(group_id,organization_type_key,canonical_name,display_name,status,source) VALUES(?, 'CLINIC', ?, ?, 'verified', 'operator_created')")->execute([$group,$group,$group]);
    $coverage->initializeProvider($group);$coverage->setProviderOperationalState($group,'ACTIVE');$coverage->setProviderVerification($group,'VERIFIED','operator_qa');
    $pdo->prepare("INSERT INTO profile_subscriptions(subscription_id,entity_type,entity_id,plan_code,contracted_plan_code,effective_plan_code,billing_period,starts_at,expires_at,status,source) VALUES(UUID(),'provider_organization',?,'func_qa_internal','func_qa_internal','func_qa_internal','annual',DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 DAY),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 30 DAY),'active','disposable_qa')")->execute([$group]);
    $location=$directory->createLocation($group,['branch_name'=>$key,'postal_code'=>'20000','street'=>'Calle QA','exterior_number'=>'10','municipality'=>'Municipio QA','state_name'=>'Estado QA']);
    $directory->setLocationVerification($group,$location['location_uuid'],'VERIFIED','operator_qa');
    $directory->createOffering($group,$location['location_uuid'],$studyId,['service_mode'=>'ON_SITE']);
    $directory->setOfferingVerification($group,$location['location_uuid'],$studyId,'VERIFIED','operator_qa');
    $locations[$key]=$location['location_uuid'];
}
$stmt=$pdo->query("SELECT document_uuid,payload_json FROM clinical_documents WHERE patient_id='p_funccat02b' AND title='Fisiología gastrointestinal (2)' ORDER BY id LIMIT 1");
$doc=$stmt->fetch(PDO::FETCH_ASSOC);if (!$doc) throw new RuntimeException('GI_ORDER_MISSING');
$items=json_decode($doc['payload_json'],true,512,JSON_THROW_ON_ERROR)['order_items'];
$result=$matcher->matchOrder('d_funccat02b','p_funccat02b',$doc['document_uuid'],1,['MX|CP|20000'],'ON_SITE');
$byLocation=[];foreach ($result['candidates'] as $candidate) $byLocation[$candidate['location']['location_uuid']]=$candidate['coverage'];
foreach ($ids as $key=>$id) {
    $row=$byLocation[$locations[$key]]??null;
    if (!is_array($row)||$row['matched_item_count']!==1||$row['classification']!=='PARTIAL_VERIFIED_COVERAGE'
        ||$row['parameter_capability_status']!=='UNVERIFIED'||count($row['parameter_unverified_order_item_ids'])!==1) throw new RuntimeException('exact_study_or_parameter_disclosure_failed_'.$key);
    $matched=array_values(array_filter($items,static fn(array $item):bool=>$item['study_type_key']===$key))[0];
    if ($row['parameter_unverified_order_item_ids']!==[$matched['order_item_id']]) throw new RuntimeException('parameter_item_identity_mismatch_'.$key);
}
echo "QA_EXACT_STUDY_MATCH_NO_CROSS_CAPABILITY_PARAMETER_UNVERIFIED=PASS\n";
