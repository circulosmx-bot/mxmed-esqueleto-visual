<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/api/_lib/clinical_m6_observability.php';

$passed = 0;
function mon_check(bool $condition, string $message): void {
    global $passed; if (!$condition) throw new RuntimeException('FAIL: '.$message); $passed++;
}

$root = sys_get_temp_dir().'/mxmed-m6-monitor-'.bin2hex(random_bytes(6));
putenv('MXMED_CLINICAL_M6_OBSERVABILITY_ROOT='.$root);
putenv('MXMED_CLINICAL_M6_COHORT_MODE=allowlist');
putenv('MXMED_CLINICAL_M6_COHORT_PAIRS=doctor-secret|patient-secret');
putenv('MXMED_CLINICAL_M6_EMERGENCY_OFF=0');
putenv('MXMED_CLINICAL_ENCOUNTER_INTEGRITY_V1=1');
putenv('MXMED_CLINICAL_M6_WORKER_GENERATION=deploy-42');
try {
    $health=clinical_m6_observability_initialize($root);
    mon_check(is_dir($root)&&(($m=fileperms($root))!==false)&&(($m&0007)===0),'private root');
    $correlation=clinical_m6_observability_correlation_id();
    mon_check((bool)preg_match('/^[a-f0-9]{32}$/',$correlation),'opaque correlation');
    clinical_m6_observability_route('C04_PATIENT_DOCUMENT','CREATE_DOCUMENT','PATIENT_LEVEL_C04');
    mon_check(clinical_m6_observability_emit('clinical_writer',['outcome'=>'success','replay'=>false]),'writer emit');
    clinical_m6_observability_storage('STAGING_COMPLETE',true);
    clinical_m6_observability_emit('privacy_probe',['patient_id'=>'patient-secret','token'=>'raw-token-value','outcome'=>'success']);
    clinical_m6_observability_response(['ok'=>true,'error'=>null],201);
    $events=clinical_m6_observability_read_events();
    mon_check(count($events)>=4,'events readable');
    foreach($events as $event) mon_check(($event['correlation_id']??null)===$correlation,'correlation propagated');
    $raw=(string)file_get_contents($root.'/events.ndjson');
    $eventMode=fileperms($root.'/events.ndjson');
    mon_check(is_int($eventMode)&&(($eventMode&0007)===0),'event permissions');
    foreach(['doctor-secret','patient-secret','raw-token-value','patient name','database-password'] as $secret) {
        mon_check(!str_contains($raw,$secret),'privacy '.$secret);
    }
    mon_check(str_contains($raw,hash('sha256',json_encode([true,'allowlist',false,['doctor-secret|patient-secret']],JSON_THROW_ON_ERROR))),'safe config hash');
    $summary=clinical_m6_observability_aggregate($events);
    mon_check(($summary['authority_counts']['PATIENT_LEVEL_C04']??0)>=1,'authority aggregation');
    mon_check(($summary['status_counts']['201']??0)===1,'status aggregation');
    mon_check(($summary['request_authority_counts']['PATIENT_LEVEL_C04']??0)===1,'request authority aggregation');
    mon_check(($summary['latency']['C04_PATIENT_DOCUMENT']['count']??0)===1,'latency aggregation');
    $state=clinical_m6_observability_feature_state();
    mon_check($state['config_valid']&&$state['worker_generation']==='deploy-42','gate status');
    mon_check(!isset($state['pairs'])&&!str_contains(json_encode($state),'patient-secret'),'allowlist hidden');
    $health=clinical_m6_observability_self_health(true);
    mon_check($health['healthy']&&!$health['stale'],'self health heartbeat');
    $health=clinical_m6_observability_self_health(false);
    mon_check(is_string($health['latest_heartbeat_at']??null),'latest heartbeat observable');
    putenv('MXMED_CLINICAL_M6_OBSERVABILITY_ROOT=/nonexistent/mxmed-monitoring-denied');
    mon_check(clinical_m6_observability_emit('clinical_writer',['outcome'=>'failure'])===false,'failure is nonfatal');
} finally {
    putenv('MXMED_CLINICAL_M6_OBSERVABILITY_ROOT='.$root);
    $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach($it as $item){$item->isDir()?rmdir($item->getPathname()):unlink($item->getPathname());}
    rmdir($root);
    foreach(['MXMED_CLINICAL_M6_OBSERVABILITY_ROOT','MXMED_CLINICAL_M6_COHORT_MODE','MXMED_CLINICAL_M6_COHORT_PAIRS',
        'MXMED_CLINICAL_M6_EMERGENCY_OFF','MXMED_CLINICAL_ENCOUNTER_INTEGRITY_V1','MXMED_CLINICAL_M6_WORKER_GENERATION'] as $name) putenv($name);
}
echo "M6_MONITORING_OBSERVABILITY_TESTS_PASSED=$passed\n";
