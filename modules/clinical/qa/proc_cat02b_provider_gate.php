<?php
declare(strict_types=1);

use Agenda\Services\HealthcareOrganizationDirectoryService;
use Agenda\Services\HealthcareProviderCoverageService;
use Agenda\Services\HealthcareStudyProviderMatchingService;

require_once __DIR__.'/../../agenda/services/HealthcareOrganizationDirectoryService.php';
require_once __DIR__.'/../../agenda/services/HealthcareProviderCoverageService.php';
require_once __DIR__.'/../../agenda/services/HealthcareStudyProviderMatchingService.php';

$db=getenv('PROCCAT02B_QA_DB');
if (!is_string($db) || !preg_match('/^proccat02b_qa_[a-f0-9]{8}$/D',$db)) throw new RuntimeException('disposable_db_required');
$pdo=new PDO('mysql:host=localhost;dbname='.$db.';charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$ids=[];
foreach ($pdo->query("SELECT study_type_key,study_type_id FROM clinical_study_types WHERE category_key='PROCEDIMIENTOS_DIAGNOSTICOS'") as $row) $ids[$row['study_type_key']]=(int)$row['study_type_id'];
if (count($ids)!==3) throw new RuntimeException('catalog_fixture_invalid');
$directory=new HealthcareOrganizationDirectoryService($pdo);
$coverage=new HealthcareProviderCoverageService($pdo);
$matcher=new HealthcareStudyProviderMatchingService($pdo);
$pdo->exec("INSERT INTO subscription_plans(plan_code,plan_label,billing_period,duration_days,product_family,source) VALUES('proc_qa_internal','Procedure QA','annual',365,'PROVIDER_ORGANIZATION','disposable_qa')");
$pdo->exec("INSERT INTO provider_subscription_plan_capabilities(plan_code,billing_period,capability) VALUES('proc_qa_internal','annual','provider_matching_participation')");
$locations=[];
foreach ($ids as $key=>$studyId) {
    $group='org_proc_'.$key;
    $pdo->prepare("INSERT INTO medical_groups(group_id,organization_type_key,canonical_name,display_name,status,source) VALUES(?, 'CLINIC', ?, ?, 'verified', 'operator_created')")->execute([$group,$group,$group]);
    $coverage->initializeProvider($group);
    $coverage->setProviderOperationalState($group,'ACTIVE');
    $coverage->setProviderVerification($group,'VERIFIED','operator_qa');
    $pdo->prepare("INSERT INTO profile_subscriptions(subscription_id,entity_type,entity_id,plan_code,contracted_plan_code,effective_plan_code,billing_period,starts_at,expires_at,status,source) VALUES(UUID(),'provider_organization',?,'proc_qa_internal','proc_qa_internal','proc_qa_internal','annual',DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 DAY),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 30 DAY),'active','disposable_qa')")->execute([$group]);
    $loc=$directory->createLocation($group,['branch_name'=>$key,'postal_code'=>'20000','street'=>'Calle QA','exterior_number'=>'10','municipality'=>'Municipio QA','state_name'=>'Estado QA']);
    $directory->setLocationVerification($group,$loc['location_uuid'],'VERIFIED','operator_qa');
    foreach (['HOME_SERVICE','MOBILE'] as $invalid) {
        try { $directory->createOffering($group,$loc['location_uuid'],$studyId,['service_mode'=>$invalid]); throw new RuntimeException('invalid_mode_accepted'); }
        catch (InvalidArgumentException $e) { if ($e->getMessage()!=='diagnostic_procedure_requires_on_site') throw $e; }
    }
    $directory->createOffering($group,$loc['location_uuid'],$studyId,['service_mode'=>'ON_SITE']);
    $directory->setOfferingVerification($group,$loc['location_uuid'],$studyId,'VERIFIED','operator_qa');
    $locations[$key]=$loc['location_uuid'];
    foreach (['HOME_SERVICE','MOBILE'] as $invalid) {
        try { $directory->updateOffering($group,$loc['location_uuid'],$studyId,['service_mode'=>$invalid]); throw new RuntimeException('invalid_mode_accepted'); }
        catch (InvalidArgumentException $e) { if ($e->getMessage()!=='diagnostic_procedure_requires_on_site') throw $e; }
    }
}
$doc=$pdo->query("SELECT document_uuid FROM clinical_documents WHERE patient_id='p_proccat02b' AND title='Diagnóstico ginecológico (2)' ORDER BY id LIMIT 1")->fetchColumn();
if (!is_string($doc)) throw new RuntimeException('gynecology_order_missing');
$result=$matcher->matchOrder('d_proccat02b','p_proccat02b',$doc,1,['MX|CP|20000'],'ON_SITE');
$byLocation=[];
foreach ($result['candidates'] as $candidate) $byLocation[$candidate['location']['location_uuid']]=$candidate['coverage'];
foreach (['colposcopy_diagnostic','hysteroscopy_diagnostic'] as $key) {
    $row=$byLocation[$locations[$key]]??null;
    if (!is_array($row) || $row['matched_item_count']!==1 || $row['total_order_item_count']!==2 || $row['classification']!=='PARTIAL_VERIFIED_COVERAGE') {
        throw new RuntimeException('exact_offering_match_failed_'.$key);
    }
}
if (isset($byLocation[$locations['cystoscopy_diagnostic']])) throw new RuntimeException('cross_group_match');
$home=$matcher->matchOrder('d_proccat02b','p_proccat02b',$doc,1,['MX|CP|20000'],'HOME_SERVICE');
if ($home['candidates']!==[] || $home['summary']['cataloged_item_count']!==0) throw new RuntimeException('offsite_match');
$cystoscopyGroup='org_proc_cystoscopy_diagnostic';
$directory->setOfferingVerification($cystoscopyGroup,$locations['cystoscopy_diagnostic'],$ids['cystoscopy_diagnostic'],'REJECTED','operator_qa');
$cystoDoc=$pdo->query("SELECT document_uuid FROM clinical_documents WHERE patient_id='p_proccat02b' AND title='Cistoscopia diagnóstica' ORDER BY id LIMIT 1")->fetchColumn();
$unverified=$matcher->matchOrder('d_proccat02b','p_proccat02b',$cystoDoc,1,['MX|CP|20000'],'ON_SITE');
if ($unverified['candidates']!==[]) throw new RuntimeException('unverified_offering_match');
echo "QA_EXACT_VERIFIED_PROVIDER_OFFERING_AND_MODE=PASS\n";
