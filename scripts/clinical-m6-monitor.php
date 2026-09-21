<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/api/_lib/clinical_m6_monitoring_analysis.php';

if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI only\n"); exit(2); }

function monitor_json(array $value): never
{
    echo json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
    exit(0);
}

function monitor_since(array $argv): ?int
{
    foreach ($argv as $index => $arg) {
        if ($arg === '--since' && isset($argv[$index + 1])) {
            $epoch = strtotime((string)$argv[$index + 1]);
            if ($epoch === false) throw new InvalidArgumentException('MONITORING_SINCE_INVALID');
            return $epoch;
        }
        if ($arg === '--minutes' && isset($argv[$index + 1]) && preg_match('/^[0-9]+$/D', (string)$argv[$index + 1])) {
            return time() - ((int)$argv[$index + 1] * 60);
        }
    }
    return null;
}

function monitor_safe_name(string $value): string
{
    if (!preg_match('/^[A-Za-z0-9_.-]{1,64}$/D', $value)) throw new InvalidArgumentException('MONITORING_NAME_INVALID');
    return $value;
}

function monitor_option(array $argv, string $name): ?string
{
    foreach($argv as $index=>$arg)if($arg===$name&&isset($argv[$index+1]))return trim((string)$argv[$index+1]);
    return null;
}

function monitor_threshold_authority(array $argv): array
{
    $definitions=clinical_m6_monitoring_rules_load();$path=monitor_option($argv,'--profile');$stage=monitor_option($argv,'--stage');
    if($path===null){return ['rules'=>$definitions,'profile'=>['id'=>'NONE','version'=>null,'hash'=>null,'stage'=>$stage,
        'valid'=>false,'error'=>'MONITORING_THRESHOLD_PROFILE_REQUIRED','active_numeric_rules'=>[]],'rollout_evaluation_valid'=>false];}
    if($stage===null)throw new RuntimeException('MONITORING_THRESHOLD_PROFILE_STAGE_REQUIRED');
    $loaded=clinical_m6_monitoring_profile_load($path,$stage);
    return ['rules'=>clinical_m6_monitoring_rules_apply_profile($definitions,$loaded['profile']),
        'profile'=>$loaded['authority'],'rollout_evaluation_valid'=>true];
}

function monitor_assert_baseline_profile(array $baselineStatus, array $threshold): void
{
    if(!$threshold['rollout_evaluation_valid'])return;
    $baselineProfile=is_array($baselineStatus['threshold_profile']??null)?$baselineStatus['threshold_profile']:[];
    foreach(['id','version','hash','stage'] as $field){
        if(($baselineProfile[$field]??null)!==($threshold['profile'][$field]??null))
            throw new RuntimeException('MONITORING_BASELINE_PROFILE_MISMATCH');
    }
}

function monitor_prune(string $root): int
{
    $cutoff = time() - clinical_m6_observability_retention_days() * 86400;
    $removed = 0;
    foreach (['snapshots','baselines'] as $dir) {
        foreach (glob($root . '/' . $dir . '/*.json') ?: [] as $path) {
            $mtime = filemtime($path);
            if (is_int($mtime) && $mtime < $cutoff && unlink($path)) $removed++;
        }
    }
    return $removed;
}

try {
    $command = strtolower(trim((string)($argv[1] ?? 'status')));
    if ($command === 'init') {
        $root = trim((string)($argv[2] ?? ''));
        monitor_json(['command'=>'init','monitoring'=>clinical_m6_observability_initialize($root),
            'retention_days'=>clinical_m6_observability_retention_days(),'max_bytes'=>clinical_m6_observability_max_bytes()]);
    }
    $root = clinical_m6_observability_root();
    if ($root === null) throw new RuntimeException('MONITORING_ROOT_REQUIRED');
    $since = monitor_since($argv);
    if ($command === 'heartbeat') {
        monitor_json(['command'=>'heartbeat','monitoring'=>clinical_m6_observability_self_health(true),
            'clinical_write'=>false]);
    }
    $threshold = monitor_threshold_authority($argv);
    $events = clinical_m6_observability_read_events($since);
    $summary = clinical_m6_observability_aggregate($events);
    $health = clinical_m6_observability_self_health(false);
    $status = [
        'command'=>$command,'observation_since'=>$since === null ? null : gmdate('c',$since),
        'feature_gate'=>clinical_m6_observability_feature_state(),
        'write_window'=>clinical_m6_observability_write_window_state(),
        'monitoring'=>$health,'summary'=>$summary,
        'threshold_profile'=>$threshold['profile'],'rollout_evaluation_valid'=>$threshold['rollout_evaluation_valid'],
        'rules'=>clinical_m6_monitoring_rules_evaluate($summary,$health,$threshold['rules']),
        'schema_readiness_evidence'=>getenv('MXMED_CLINICAL_SCHEMA_READINESS_EVIDENCE') ?: null,
    ];
    $status['operator_metrics'] = [
        'canonical_v1_requests'=>(int)($summary['request_authority_counts']['CANONICAL_V1'] ?? 0),
        'patient_level_c04_requests'=>(int)($summary['request_authority_counts']['PATIENT_LEVEL_C04'] ?? 0),
        'guarded_legacy_requests'=>(int)($summary['request_authority_counts']['GUARDED_LEGACY'] ?? 0),
        'blocked_requests'=>(int)($summary['request_authority_counts']['BLOCKED'] ?? 0),
        'write_window_blocked_requests'=>(int)($summary['error_counts']['M6_WRITE_WINDOW_BLOCKED'] ?? 0),
        'http_409'=>(int)($summary['status_counts']['409'] ?? 0),
        'http_503'=>(int)($summary['status_counts']['503'] ?? 0),
        'http_500'=>(int)($summary['status_counts']['500'] ?? 0),
    ];
    if (!$threshold['rollout_evaluation_valid'] && $status['rules']['recommended_action']==='CONTINUE') {
        $status['rules']['recommended_action']='HOLD';
        $status['rules']['blocking_identity']='MONITORING_THRESHOLD_PROFILE_REQUIRED';
    }
    if ($command === 'status') monitor_json($status);
    if ($command === 'baseline') {
        $name = monitor_safe_name((string)($argv[2] ?? ('baseline-' . gmdate('YmdHis'))));
        $path = $root . '/baselines/' . $name . '.json';
        if (file_exists($path)) throw new RuntimeException('MONITORING_BASELINE_EXISTS');
        $payload = ['schema'=>'mxmed.m6.baseline.v1','created_at'=>gmdate('c'),'status'=>$status];
        file_put_contents($path,json_encode($payload,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),LOCK_EX);
        chmod($path,0440); monitor_prune($root);
        monitor_json(['command'=>'baseline','path_hash'=>hash('sha256',$path),'baseline'=>$payload]);
    }
    if ($command === 'compare') {
        $name = monitor_safe_name((string)($argv[2] ?? ''));
        $baseline = json_decode((string)file_get_contents($root.'/baselines/'.$name.'.json'),true,512,JSON_THROW_ON_ERROR);
        $baselineStatus = is_array($baseline['status'] ?? null) ? $baseline['status'] : [];
        monitor_assert_baseline_profile($baselineStatus,$threshold);
        $comparison = clinical_m6_monitoring_compare($baselineStatus, $status);
        $rules = clinical_m6_monitoring_rules_evaluate($summary,$health,$threshold['rules'],$comparison);
        if(!$threshold['rollout_evaluation_valid']&&$rules['recommended_action']==='CONTINUE'){
            $rules['recommended_action']='HOLD';$rules['blocking_identity']='MONITORING_THRESHOLD_PROFILE_REQUIRED';
        }
        $legacyDelta = ['event_count'=>$comparison['event_count_delta'],
            'storage_failures'=>$comparison['storage_failures_delta'],
            'unexpected_fallback_count'=>$comparison['unexpected_fallback_count_delta'],
            'parallel_writer_evidence_count'=>$comparison['parallel_writer_evidence_count_delta']];
        monitor_json(['command'=>'compare','baseline'=>$name,
            'baseline_reference'=>['created_at'=>$baseline['created_at']??null,'observation_since'=>$baselineStatus['observation_since']??null],
            'observation_interval'=>['since'=>$status['observation_since'],'through'=>gmdate('c')],
            'current'=>$status,'delta'=>$legacyDelta,'comparison'=>$comparison,
            'rules'=>$rules,'recommended_action'=>$rules['recommended_action']]);
    }
    if ($command === 'snapshot') {
        $name = monitor_safe_name((string)($argv[2] ?? ('snapshot-' . gmdate('YmdHis'))));
        $path = $root.'/snapshots/'.$name.'.json';
        if (file_exists($path)) throw new RuntimeException('MONITORING_SNAPSHOT_EXISTS');
        $snapshotStatus=$status;$snapshotComparison=null;$baselineName=monitor_option($argv,'--baseline');
        if($baselineName!==null){
            $baselineName=monitor_safe_name($baselineName);
            $baseline=json_decode((string)file_get_contents($root.'/baselines/'.$baselineName.'.json'),true,512,JSON_THROW_ON_ERROR);
            monitor_assert_baseline_profile((array)($baseline['status']??[]),$threshold);
            $snapshotComparison=clinical_m6_monitoring_compare((array)($baseline['status']??[]),$status);
            $snapshotStatus['rules']=clinical_m6_monitoring_rules_evaluate($summary,$health,$threshold['rules'],$snapshotComparison);
        }
        $payload=['schema'=>'mxmed.m6.snapshot.v1','created_at'=>gmdate('c'),'baseline'=>$baselineName,
            'comparison'=>$snapshotComparison,'status'=>$snapshotStatus];
        file_put_contents($path,json_encode($payload,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),LOCK_EX);
        chmod($path,0440); monitor_prune($root);
        monitor_json(['command'=>'snapshot','path_hash'=>hash('sha256',$path),'evidence'=>$payload]);
    }
    if ($command === 'decision') {
        if(!$threshold['rollout_evaluation_valid'])throw new RuntimeException('MONITORING_THRESHOLD_PROFILE_REQUIRED');
        $decision = strtoupper(trim((string)($argv[2] ?? '')));
        $reason = clinical_m6_observability_safe_label((string)($argv[3] ?? 'UNSPECIFIED'));
        if (!in_array($decision,['HOLD','ADVANCE','DEACTIVATE','EMERGENCY_HALT'],true)) throw new InvalidArgumentException('MONITORING_DECISION_INVALID');
        clinical_m6_observability_emit('operator_decision',['decision'=>$decision,'reason_code'=>$reason,'outcome'=>'recorded',
            'threshold_profile_id'=>$threshold['profile']['id'],'threshold_profile_version'=>$threshold['profile']['version'],
            'threshold_profile_hash'=>$threshold['profile']['hash']]);
        monitor_json(['command'=>'decision','decision'=>$decision,'reason_code'=>$reason,
            'threshold_profile'=>$threshold['profile'],'feature_gate_changed'=>false]);
    }
    throw new InvalidArgumentException('usage: init ROOT|heartbeat|status|baseline NAME|compare NAME|snapshot NAME [--baseline NAME]|decision ACTION REASON [--profile ABSOLUTE_PATH --stage STAGE] [--since ISO|--minutes N]');
} catch (Throwable $e) {
    fwrite(STDERR, clinical_m6_observability_error_identity($e->getMessage()) . "\n");
    exit(1);
}
