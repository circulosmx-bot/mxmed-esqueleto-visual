<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/api/_lib/clinical_m6_observability.php';

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

function monitor_rules(array $summary, array $health): array
{
    $path = dirname(__DIR__) . '/modules/clinical/monitoring/m6_monitoring_rules.json';
    $authority = json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    $results = [];
    $rank = array_flip($authority['action_precedence']);
    $recommended = 'CONTINUE';
    foreach ($authority['rules'] as $rule) {
        $pass = true; $observed = null; $configured = true;
        switch ($rule['type']) {
            case 'zero':
                $observed = (int)($summary[$rule['metric']] ?? 0); $pass = $observed === 0; break;
            case 'error_zero':
                $observed = (int)($summary['error_counts'][$rule['error']] ?? 0); $pass = $observed === 0; break;
            case 'freshness':
                $observed = (bool)($health['stale'] ?? true); $pass = !$observed; break;
            case 'health':
                $observed = (bool)($health['visibility_loss'] ?? true); $pass = !$observed; break;
            case 'count':
                $observed = (int)($summary['status_counts'][(string)$rule['status']] ?? 0);
                $pass = $observed <= (int)$rule['maximum']; break;
            case 'latency':
                if ($rule['maximum_ms'] === null) { $configured = false; $pass = true; $observed = 'THRESHOLD_REQUIRES_ACCEPTANCE'; }
                else {
                    $observed = max(array_map(static fn(array $v): int => (int)$v['p95_ms'], $summary['latency'] ?: [['p95_ms'=>0]]));
                    $pass = $observed <= (int)$rule['maximum_ms'];
                }
                break;
            default: throw new RuntimeException('MONITORING_RULE_TYPE_INVALID');
        }
        if (!$pass && ($rank[$rule['action']] ?? 0) > ($rank[$recommended] ?? 0)) $recommended = $rule['action'];
        $results[] = ['id'=>$rule['id'],'pass'=>$pass,'configured'=>$configured,'observed'=>$observed,'action'=>$rule['action']];
    }
    return ['results'=>$results,'recommended_action'=>$recommended,'authority_version'=>$authority['version']];
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
    $events = clinical_m6_observability_read_events($since);
    $summary = clinical_m6_observability_aggregate($events);
    $health = clinical_m6_observability_self_health(false);
    $status = [
        'command'=>$command,'observation_since'=>$since === null ? null : gmdate('c',$since),
        'feature_gate'=>clinical_m6_observability_feature_state(),
        'write_window'=>clinical_m6_observability_write_window_state(),
        'monitoring'=>$health,'summary'=>$summary,
        'rules'=>monitor_rules($summary,$health),
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
        $base = $baseline['status']['summary'] ?? [];
        monitor_json(['command'=>'compare','baseline'=>$name,'current'=>$status,
            'delta'=>['event_count'=>$summary['event_count']-(int)($base['event_count']??0),
                'storage_failures'=>$summary['storage_failures']-(int)($base['storage_failures']??0),
                'unexpected_fallback_count'=>$summary['unexpected_fallback_count']-(int)($base['unexpected_fallback_count']??0),
                'parallel_writer_evidence_count'=>$summary['parallel_writer_evidence_count']-(int)($base['parallel_writer_evidence_count']??0)]]);
    }
    if ($command === 'snapshot') {
        $name = monitor_safe_name((string)($argv[2] ?? ('snapshot-' . gmdate('YmdHis'))));
        $path = $root.'/snapshots/'.$name.'.json';
        if (file_exists($path)) throw new RuntimeException('MONITORING_SNAPSHOT_EXISTS');
        $payload=['schema'=>'mxmed.m6.snapshot.v1','created_at'=>gmdate('c'),'status'=>$status];
        file_put_contents($path,json_encode($payload,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),LOCK_EX);
        chmod($path,0440); monitor_prune($root);
        monitor_json(['command'=>'snapshot','path_hash'=>hash('sha256',$path),'evidence'=>$payload]);
    }
    if ($command === 'decision') {
        $decision = strtoupper(trim((string)($argv[2] ?? '')));
        $reason = clinical_m6_observability_safe_label((string)($argv[3] ?? 'UNSPECIFIED'));
        if (!in_array($decision,['HOLD','ADVANCE','DEACTIVATE','EMERGENCY_HALT'],true)) throw new InvalidArgumentException('MONITORING_DECISION_INVALID');
        clinical_m6_observability_emit('operator_decision',['decision'=>$decision,'reason_code'=>$reason,'outcome'=>'recorded']);
        monitor_json(['command'=>'decision','decision'=>$decision,'reason_code'=>$reason,'feature_gate_changed'=>false]);
    }
    throw new InvalidArgumentException('usage: init ROOT|heartbeat|status [--since ISO|--minutes N]|baseline NAME|compare NAME [window]|snapshot NAME|decision ACTION REASON');
} catch (Throwable $e) {
    fwrite(STDERR, clinical_m6_observability_error_identity($e->getMessage()) . "\n");
    exit(1);
}
