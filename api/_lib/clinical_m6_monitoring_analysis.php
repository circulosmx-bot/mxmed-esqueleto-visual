<?php
declare(strict_types=1);

require_once __DIR__ . '/clinical_m6_observability.php';

function clinical_m6_monitoring_rules_load(?string $path = null): array
{
    $path ??= dirname(__DIR__, 2) . '/modules/clinical/monitoring/m6_monitoring_rules.json';
    $rules = json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    clinical_m6_monitoring_rules_validate($rules);
    return $rules;
}

function clinical_m6_monitoring_rules_validate(array $authority): void
{
    if (!is_int($authority['version'] ?? null) || !is_array($authority['rules'] ?? null)) {
        throw new RuntimeException('MONITORING_RULE_CONFIG_INVALID');
    }
    $allowedActions = ['CONTINUE','HOLD','DEACTIVATE','EMERGENCY_HALT','ESCALATE_SAFE_RETURN'];
    if (($authority['action_precedence'] ?? null) !== $allowedActions) {
        throw new RuntimeException('MONITORING_RULE_ACTIONS_INVALID');
    }
    $ids = [];
    foreach ($authority['rules'] as $rule) {
        if (!is_array($rule) || !preg_match('/^[a-z][a-z0-9_]{1,63}$/D', (string)($rule['id'] ?? ''))
            || isset($ids[$rule['id']]) || !in_array($rule['action'] ?? null, $allowedActions, true)) {
            throw new RuntimeException('MONITORING_RULE_CONFIG_INVALID');
        }
        $ids[$rule['id']] = true;
        $enabled = $rule['enabled'] ?? true;
        if (!is_bool($enabled)) throw new RuntimeException('MONITORING_RULE_CONFIG_INVALID');
        $type = $rule['type'] ?? null;
        if (!in_array($type, ['zero','error_zero','count','rate','latency','freshness','health'], true)) {
            throw new RuntimeException('MONITORING_RULE_TYPE_INVALID');
        }
        if (!$enabled) continue;
        if ($type === 'zero' && !is_string($rule['metric'] ?? null)) throw new RuntimeException('MONITORING_RULE_OPERAND_REQUIRED');
        if ($type === 'error_zero' && !is_string($rule['error'] ?? null)) throw new RuntimeException('MONITORING_RULE_OPERAND_REQUIRED');
        if ($type === 'count' && (!isset($rule['status']) || !is_int($rule['maximum'] ?? null) || $rule['maximum'] < 0)) {
            throw new RuntimeException('MONITORING_RULE_THRESHOLD_INVALID');
        }
        if ($type === 'rate' && (!is_string($rule['metric'] ?? null) || !is_numeric($rule['maximum_rate'] ?? null)
            || $rule['maximum_rate'] < 0 || $rule['maximum_rate'] > 1 || ($rule['unit'] ?? null) !== 'ratio')) {
            throw new RuntimeException('MONITORING_RULE_THRESHOLD_INVALID');
        }
        if ($type === 'latency') {
            $mode = $rule['mode'] ?? null;
            if (($rule['unit'] ?? null) !== 'milliseconds' || !in_array($mode, ['absolute','baseline_relative','both'], true)) {
                throw new RuntimeException('MONITORING_RULE_THRESHOLD_INVALID');
            }
            if (in_array($mode, ['absolute','both'], true) && (!is_numeric($rule['maximum_ms'] ?? null) || $rule['maximum_ms'] < 0)) {
                throw new RuntimeException('MONITORING_RULE_THRESHOLD_INVALID');
            }
            if (in_array($mode, ['baseline_relative','both'], true) && (!is_numeric($rule['maximum_multiplier'] ?? null) || $rule['maximum_multiplier'] < 1)) {
                throw new RuntimeException('MONITORING_RULE_THRESHOLD_INVALID');
            }
        }
        if ($type === 'freshness' && (!is_int($rule['maximum_age_seconds'] ?? null) || $rule['maximum_age_seconds'] < 1)) {
            throw new RuntimeException('MONITORING_RULE_THRESHOLD_INVALID');
        }
    }
}

function clinical_m6_monitoring_canonicalize(mixed $value): mixed
{
    if (!is_array($value)) return $value;
    if (array_is_list($value)) return array_map('clinical_m6_monitoring_canonicalize', $value);
    ksort($value, SORT_STRING);
    foreach ($value as $key => $item) $value[$key] = clinical_m6_monitoring_canonicalize($item);
    return $value;
}

function clinical_m6_monitoring_profile_load(string $path, string $stage): array
{
    if ($path === '' || $path[0] !== DIRECTORY_SEPARATOR || !is_file($path) || is_link($path) || !is_readable($path)) {
        throw new RuntimeException('MONITORING_THRESHOLD_PROFILE_INVALID');
    }
    $profile = json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($profile)) throw new RuntimeException('MONITORING_THRESHOLD_PROFILE_INVALID');
    clinical_m6_monitoring_profile_validate($profile, $stage);
    $canonical = json_encode(clinical_m6_monitoring_canonicalize($profile), JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    return ['profile'=>$profile,'authority'=>['id'=>$profile['profile_id'],'version'=>$profile['profile_version'],
        'hash'=>hash('sha256',$canonical),'stage'=>$stage,'valid'=>true,
        'active_numeric_rules'=>array_values(array_map(static fn(array $rule):string=>(string)$rule['id'],$profile['rules']))]];
}

function clinical_m6_monitoring_profile_validate(array $profile, string $stage): void
{
    $top = ['schema','profile_id','profile_version','status','rollout_stage','rules'];
    if (array_diff(array_keys($profile),$top)!==[] || ($profile['schema']??null)!=='mxmed.m6.threshold-profile.v1'
        || !preg_match('/^[A-Z0-9_.-]{3,64}$/D',(string)($profile['profile_id']??''))
        || !is_int($profile['profile_version']??null) || $profile['profile_version']<1 || ($profile['status']??null)!=='active'
        || !in_array($profile['rollout_stage']??null,['internal_exact_pair','bounded_pair_set','broader_pair_set','any'],true)
        || !is_array($profile['rules']??null) || !array_is_list($profile['rules'])) {
        throw new RuntimeException('MONITORING_THRESHOLD_PROFILE_INVALID');
    }
    if (!in_array($stage,['internal_exact_pair','bounded_pair_set','broader_pair_set'],true)
        || ($profile['rollout_stage']!=='any' && $profile['rollout_stage']!==$stage)) {
        throw new RuntimeException('MONITORING_THRESHOLD_PROFILE_STAGE_MISMATCH');
    }
    $allowedActions=['HOLD','DEACTIVATE','EMERGENCY_HALT'];$seen=[];
    foreach($profile['rules'] as $rule){
        if(!is_array($rule)||!is_string($rule['id']??null)||isset($seen[$rule['id']])
            || !in_array($rule['action']??null,$allowedActions,true))throw new RuntimeException('MONITORING_THRESHOLD_PROFILE_INVALID');
        $seen[$rule['id']]=true;$id=$rule['id'];
        if($id==='unexpected_error_rate'){
            $allowed=['id','maximum_rate','minimum_requests','unit','action'];
            if(array_diff(array_keys($rule),$allowed)!==[]||($rule['unit']??null)!=='ratio'||!is_numeric($rule['maximum_rate']??null)
                ||$rule['maximum_rate']<0||$rule['maximum_rate']>1||!is_int($rule['minimum_requests']??null)||$rule['minimum_requests']<1)
                throw new RuntimeException('MONITORING_THRESHOLD_PROFILE_ERROR_RATE_INVALID');
        }elseif($id==='route_latency'){
            $allowed=['id','mode','maximum_ms','maximum_multiplier','minimum_samples','unit','action'];$mode=$rule['mode']??null;
            if(array_diff(array_keys($rule),$allowed)!==[]||($rule['unit']??null)!=='milliseconds'||!in_array($mode,['absolute','baseline_relative','both'],true)
                ||!is_int($rule['minimum_samples']??null)||$rule['minimum_samples']<1
                ||(in_array($mode,['absolute','both'],true)&&(!is_numeric($rule['maximum_ms']??null)||$rule['maximum_ms']<0))
                ||(in_array($mode,['baseline_relative','both'],true)&&(!is_numeric($rule['maximum_multiplier']??null)||$rule['maximum_multiplier']<1)))
                throw new RuntimeException('MONITORING_THRESHOLD_PROFILE_LATENCY_INVALID');
        }elseif($id==='visibility'){
            $allowed=['id','maximum_age_seconds','unit','action'];
            if(array_diff(array_keys($rule),$allowed)!==[]||($rule['unit']??null)!=='seconds'||!is_int($rule['maximum_age_seconds']??null)||$rule['maximum_age_seconds']<1)
                throw new RuntimeException('MONITORING_THRESHOLD_PROFILE_FRESHNESS_INVALID');
        }else throw new RuntimeException('MONITORING_THRESHOLD_PROFILE_RULE_UNKNOWN');
    }
    foreach(['unexpected_error_rate','route_latency','visibility'] as $required)if(!isset($seen[$required]))throw new RuntimeException('MONITORING_THRESHOLD_PROFILE_REQUIRED_RULE_MISSING');
}

function clinical_m6_monitoring_rules_apply_profile(array $authority, array $profile): array
{
    $overrides=[];foreach($profile['rules'] as $rule)$overrides[$rule['id']]=$rule;
    foreach($authority['rules'] as &$definition){
        $id=$definition['id'];if(!isset($overrides[$id]))continue;
        $definition=array_replace($definition,$overrides[$id],['enabled'=>true]);
    }unset($definition);
    clinical_m6_monitoring_rules_validate($authority);
    return $authority;
}

function clinical_m6_monitoring_rate(int $errors, int $requests): ?float
{
    return $requests === 0 ? null : round($errors / $requests, 8);
}

function clinical_m6_monitoring_relative_delta(float|int|null $baseline, float|int|null $observation): ?float
{
    if ($baseline === null || $observation === null || (float)$baseline === 0.0) return null;
    return round(((float)$observation - (float)$baseline) / (float)$baseline, 8);
}

function clinical_m6_monitoring_compare(array $baseline, array $observation): array
{
    $baseSummary = $baseline['summary'] ?? [];
    $obsSummary = $observation['summary'] ?? [];
    $baseRequests = (int)($baseSummary['request_count'] ?? 0);
    $obsRequests = (int)($obsSummary['request_count'] ?? 0);
    $baseErrors = (int)($baseSummary['unexpected_error_event_count'] ?? ($baseSummary['unexpected_failure_count'] ?? 0));
    $obsErrors = (int)($obsSummary['unexpected_error_event_count'] ?? ($obsSummary['unexpected_failure_count'] ?? 0));
    $baseRate = clinical_m6_monitoring_rate($baseErrors, $baseRequests);
    $obsRate = clinical_m6_monitoring_rate($obsErrors, $obsRequests);
    $authorities = array_unique(array_merge(array_keys($baseSummary['request_authority_stats'] ?? []),
        array_keys($obsSummary['request_authority_stats'] ?? [])));
    sort($authorities, SORT_STRING);
    $authorityComparison = [];
    foreach ($authorities as $authority) {
        $base = $baseSummary['request_authority_stats'][$authority] ?? [];
        $obs = $obsSummary['request_authority_stats'][$authority] ?? [];
        $br = (int)($base['requests'] ?? 0); $or = (int)($obs['requests'] ?? 0);
        $be = (int)($base['unexpected_failures'] ?? 0); $oe = (int)($obs['unexpected_failures'] ?? 0);
        $authorityComparison[$authority] = ['baseline_requests'=>$br,'observation_requests'=>$or,
            'baseline_unexpected_errors'=>$be,'observation_unexpected_errors'=>$oe,
            'baseline_error_rate'=>clinical_m6_monitoring_rate($be,$br),'observation_error_rate'=>clinical_m6_monitoring_rate($oe,$or)];
    }
    $families = array_unique(array_merge(array_keys($baseSummary['latency'] ?? []), array_keys($obsSummary['latency'] ?? [])));
    sort($families, SORT_STRING); $latency = [];
    foreach ($families as $family) {
        $b = $baseSummary['latency'][$family] ?? []; $o = $obsSummary['latency'][$family] ?? [];
        $latency[$family] = [];
        foreach (['avg_ms','max_ms','p95_ms'] as $metric) {
            $bv = isset($b[$metric]) ? (float)$b[$metric] : null; $ov = isset($o[$metric]) ? (float)$o[$metric] : null;
            $latency[$family][$metric] = ['baseline'=>$bv,'observation'=>$ov,
                'absolute_delta_ms'=>$bv === null || $ov === null ? null : round($ov-$bv,2),
                'relative_delta'=>clinical_m6_monitoring_relative_delta($bv,$ov)];
        }
    }
    $baseHealth = $baseline['monitoring'] ?? []; $obsHealth = $observation['monitoring'] ?? [];
    $health = ['baseline_healthy'=>(bool)($baseHealth['healthy']??false),'observation_healthy'=>(bool)($obsHealth['healthy']??false),
        'baseline_stale'=>(bool)($baseHealth['stale']??true),'observation_stale'=>(bool)($obsHealth['stale']??true),
        'baseline_visibility_loss'=>(bool)($baseHealth['visibility_loss']??true),
        'observation_visibility_loss'=>(bool)($obsHealth['visibility_loss']??true),
        'parser_health_lost'=>(bool)($baseHealth['aggregation_parseable']??false) && !(bool)($obsHealth['aggregation_parseable']??false),
        'feature_gate_source_lost'=>(bool)($baseHealth['feature_gate_source_readable']??false) && !(bool)($obsHealth['feature_gate_source_readable']??false),
        'write_window_source_lost'=>(bool)($baseHealth['write_window_source_readable']??false) && !(bool)($obsHealth['write_window_source_readable']??false)];
    $health['degraded'] = ($health['baseline_healthy'] && !$health['observation_healthy']) || $health['observation_stale']
        || $health['observation_visibility_loss'] || $health['parser_health_lost'] || $health['feature_gate_source_lost']
        || $health['write_window_source_lost'];
    return [
        'event_count_delta'=>(int)($obsSummary['event_count']??0)-(int)($baseSummary['event_count']??0),
        'storage_failures_delta'=>(int)($obsSummary['storage_failures']??0)-(int)($baseSummary['storage_failures']??0),
        'unexpected_fallback_count_delta'=>(int)($obsSummary['unexpected_fallback_count']??0)-(int)($baseSummary['unexpected_fallback_count']??0),
        'parallel_writer_evidence_count_delta'=>(int)($obsSummary['parallel_writer_evidence_count']??0)-(int)($baseSummary['parallel_writer_evidence_count']??0),
        'expected_protection_count_delta'=>(int)($obsSummary['expected_protection_count']??0)-(int)($baseSummary['expected_protection_count']??0),
        'unexpected_error_rate'=>['baseline_request_count'=>$baseRequests,'observation_request_count'=>$obsRequests,
            'baseline_unexpected_error_count'=>$baseErrors,'observation_unexpected_error_count'=>$obsErrors,
            'baseline_rate'=>$baseRate,'observation_rate'=>$obsRate,
            'absolute_delta'=>$baseRate===null||$obsRate===null?null:round($obsRate-$baseRate,8),
            'relative_delta'=>clinical_m6_monitoring_relative_delta($baseRate,$obsRate)],
        'authority_error_rates'=>$authorityComparison,'latency'=>$latency,'monitoring_health'=>$health,
    ];
}

function clinical_m6_monitoring_rules_evaluate(array $summary, array $health, array $authority, ?array $comparison = null): array
{
    clinical_m6_monitoring_rules_validate($authority);
    $rank = array_flip($authority['action_precedence']); $recommended = 'CONTINUE'; $results = [];
    foreach ($authority['rules'] as $rule) {
        $enabled = $rule['enabled'] ?? true;
        if (!$enabled) { $results[]=['id'=>$rule['id'],'enabled'=>false,'pass'=>null,'observed'=>'INACTIVE_PENDING_ACCEPTANCE','action'=>$rule['action']]; continue; }
        $pass=true; $observed=null; $reason=null; $effectiveAction=$rule['action'];
        switch ($rule['type']) {
            case 'zero': $observed=(int)($summary[$rule['metric']]??0); $pass=$observed===0; break;
            case 'error_zero': $observed=(int)($summary['error_counts'][$rule['error']]??0); $pass=$observed===0; break;
            case 'count': $observed=(int)($summary['status_counts'][(string)$rule['status']]??0); $pass=$observed<=$rule['maximum']; break;
            case 'rate':
                $observed=clinical_m6_monitoring_rate((int)($summary[$rule['metric']]??0),(int)($summary['request_count']??0));
                if((int)($summary['request_count']??0)<(int)($rule['minimum_requests']??1)){$pass=false;$reason='MINIMUM_SAMPLE_NOT_MET';$effectiveAction='HOLD';}
                else $pass=$observed!==null&&$observed<=(float)$rule['maximum_rate']; break;
            case 'latency':
                $latencyRows=$summary['latency']??[];$sampleCount=array_sum(array_map(static fn(array $v):int=>(int)($v['count']??0),$latencyRows));
                $values=array_column($latencyRows,'p95_ms'); $absolute=$values===[]?null:(float)max($values);$observed=['p95_ms'=>$absolute];
                if($sampleCount<(int)($rule['minimum_samples']??1)){$pass=false;$reason='MINIMUM_SAMPLE_NOT_MET';$effectiveAction='HOLD';break;}
                $absolutePass=!in_array($rule['mode'],['absolute','both'],true)||($absolute!==null&&$absolute<=(float)$rule['maximum_ms']);
                $relativePass=true;
                if(in_array($rule['mode'],['baseline_relative','both'],true)){
                    $ratios=[]; foreach(($comparison['latency']??[]) as $v) { $ratio=$v['p95_ms']['relative_delta']??null; if($ratio!==null)$ratios[]=1+$ratio; }
                    $observed['baseline_multiplier']=$ratios===[]?null:max($ratios);
                    if($ratios===[]||count($ratios)!==count($latencyRows)){$relativePass=false;$reason='BASELINE_NOT_SUFFICIENT_FOR_ACTIVE_RULE';$effectiveAction='HOLD';}
                    else $relativePass=$observed['baseline_multiplier']<=(float)$rule['maximum_multiplier'];
                }
                $pass=$absolutePass&&$relativePass;break;
            case 'freshness':
                $latest=is_string($health['latest_event_at']??null)?strtotime($health['latest_event_at']):false;
                $observed=$latest===false?null:max(0,time()-$latest);
                $pass=$observed!==null&&$observed<=(int)$rule['maximum_age_seconds']&&!(bool)($health['stale']??true);break;
            case 'health': $observed=(bool)($health['visibility_loss']??true)||(($comparison['monitoring_health']['degraded']??false)===true); $pass=!$observed; break;
        }
        if(!$pass && ($rank[$effectiveAction]??0)>($rank[$recommended]??0))$recommended=$effectiveAction;
        $results[]=['id'=>$rule['id'],'enabled'=>true,'pass'=>$pass,'observed'=>$observed,'reason'=>$reason,'action'=>$effectiveAction];
    }
    return ['results'=>$results,'recommended_action'=>$recommended,'authority_version'=>$authority['version']];
}
