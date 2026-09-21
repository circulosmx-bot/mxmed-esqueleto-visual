<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/api/_lib/clinical_m6_monitoring_analysis.php';

$passed=0;
function repair_check(bool $ok,string $name):void{global $passed;if(!$ok)throw new RuntimeException('FAIL '.$name);$passed++;}
function repair_status(array $summary,array $health=[]):array{return ['summary'=>$summary,'monitoring'=>array_replace([
    'healthy'=>true,'stale'=>false,'visibility_loss'=>false,'aggregation_parseable'=>true,
    'feature_gate_source_readable'=>true,'write_window_source_readable'=>true],$health)];}
function repair_summary(int $requests,int $failures,float $latency,string $authority='CANONICAL_V1'):array{return [
    'event_count'=>$requests,'request_count'=>$requests,'unexpected_failure_count'=>$failures,'unexpected_error_event_count'=>$failures,'expected_protection_count'=>0,
    'storage_failures'=>0,'unexpected_fallback_count'=>0,'parallel_writer_evidence_count'=>0,'error_counts'=>[],
    'status_counts'=>[],'request_authority_stats'=>[$authority=>['requests'=>$requests,'unexpected_failures'=>$failures,'expected_protections'=>0]],
    'latency'=>['TEST_ROUTE'=>['count'=>$requests,'min_ms'=>$latency,'max_ms'=>$latency,'avg_ms'=>$latency,'p95_ms'=>$latency]]];}
function repair_rules(array $rules):array{return ['version'=>99,'profile'=>'DISPOSABLE_TEST_THRESHOLD','rules'=>$rules,
    'action_precedence'=>['CONTINUE','HOLD','DEACTIVATE','EMERGENCY_HALT','ESCALATE_SAFE_RETURN']];}

$healthyBase=repair_status(repair_summary(10,0,100));
$healthyObs=repair_status(repair_summary(10,0,105));
$same=clinical_m6_monitoring_compare($healthyBase,$healthyObs);
repair_check($same['unexpected_error_rate']['absolute_delta']===0.0,'BASELINE-ERR-01');
repair_check($same['monitoring_health']['degraded']===false,'BASELINE-HEALTH-01');

$badObs=repair_status(repair_summary(10,2,160));
$degraded=clinical_m6_monitoring_compare($healthyBase,$badObs);
repair_check($degraded['unexpected_error_rate']['observation_rate']===0.2&&$degraded['unexpected_error_rate']['absolute_delta']===0.2,'BASELINE-ERR-02');
repair_check(($degraded['authority_error_rates']['CANONICAL_V1']['observation_error_rate']??null)===0.2,'route aware rate');
$storageObs=$badObs;$storageObs['summary']['unexpected_error_event_count']=3;$storageObs['summary']['storage_failures']=1;
repair_check(clinical_m6_monitoring_compare($healthyBase,$storageObs)['unexpected_error_rate']['observation_rate']===0.3,'storage failure in error rate');
repair_check(($same['latency']['TEST_ROUTE']['p95_ms']['relative_delta']??null)===0.05,'BASELINE-LAT-01');
repair_check(($degraded['latency']['TEST_ROUTE']['p95_ms']['relative_delta']??null)===0.6,'BASELINE-LAT-02');

$lost=repair_status(repair_summary(10,0,100),['healthy'=>false,'stale'=>true,'visibility_loss'=>true,'aggregation_parseable'=>false]);
$healthComparison=clinical_m6_monitoring_compare($healthyBase,$lost);
repair_check($healthComparison['monitoring_health']['degraded']===true,'BASELINE-HEALTH-02');

$zeroRules=repair_rules([['id'=>'zero_test','enabled'=>true,'type'=>'zero','metric'=>'unexpected_fallback_count','action'=>'EMERGENCY_HALT']]);
$zeroPass=clinical_m6_monitoring_rules_evaluate(repair_summary(1,0,1),$healthyObs['monitoring'],$zeroRules);
repair_check($zeroPass['recommended_action']==='CONTINUE','RULE-ZERO-01');
$zeroSummary=repair_summary(1,0,1);$zeroSummary['unexpected_fallback_count']=1;
$zeroFail=clinical_m6_monitoring_rules_evaluate($zeroSummary,$healthyObs['monitoring'],$zeroRules);
repair_check($zeroFail['recommended_action']==='EMERGENCY_HALT','RULE-ZERO-02');

$rateRules=repair_rules([['id'=>'rate_test','enabled'=>true,'type'=>'rate','metric'=>'unexpected_failure_count','maximum_rate'=>0.1,'unit'=>'ratio','action'=>'DEACTIVATE']]);
repair_check(clinical_m6_monitoring_rules_evaluate(repair_summary(10,2,1),$healthyObs['monitoring'],$rateRules)['recommended_action']==='DEACTIVATE','COUNT_OR_RATE');
$absoluteLatency=repair_rules([['id'=>'latency_absolute','enabled'=>true,'type'=>'latency','mode'=>'absolute','maximum_ms'=>120,'unit'=>'milliseconds','action'=>'HOLD']]);
repair_check(clinical_m6_monitoring_rules_evaluate(repair_summary(10,0,100),$healthyObs['monitoring'],$absoluteLatency)['recommended_action']==='CONTINUE','LATENCY absolute pass');
repair_check(clinical_m6_monitoring_rules_evaluate(repair_summary(10,0,160),$healthyObs['monitoring'],$absoluteLatency)['recommended_action']==='HOLD','LATENCY absolute crossing');
$relativeLatency=repair_rules([['id'=>'latency_relative','enabled'=>true,'type'=>'latency','mode'=>'baseline_relative','maximum_multiplier'=>1.2,'unit'=>'milliseconds','action'=>'HOLD']]);
repair_check(clinical_m6_monitoring_rules_evaluate($badObs['summary'],$badObs['monitoring'],$relativeLatency,$degraded)['recommended_action']==='HOLD','LATENCY relative crossing');
$healthRules=repair_rules([['id'=>'health_test','enabled'=>true,'type'=>'health','action'=>'EMERGENCY_HALT']]);
repair_check(clinical_m6_monitoring_rules_evaluate($lost['summary'],$lost['monitoring'],$healthRules,$healthComparison)['recommended_action']==='EMERGENCY_HALT','FRESHNESS_OR_SELF_HEALTH');

foreach([
    repair_rules([['id'=>'bad_latency','enabled'=>true,'type'=>'latency','mode'=>'absolute','maximum_ms'=>null,'unit'=>'milliseconds','action'=>'HOLD']]),
    repair_rules([['id'=>'bad_rate','enabled'=>true,'type'=>'rate','metric'=>'unexpected_failure_count','maximum_rate'=>null,'unit'=>'ratio','action'=>'HOLD']]),
    repair_rules([['id'=>'bad_unknown','enabled'=>true,'type'=>'unknown','action'=>'HOLD']]),
] as $invalid){$rejected=false;try{clinical_m6_monitoring_rules_validate($invalid);}catch(Throwable){$rejected=true;}repair_check($rejected,'RULE-CONFIG-01');}

repair_check(clinical_m6_monitoring_rate(0,0)===null,'zero request safe');
echo "M6_MONITORING_BASELINE_RULE_TESTS_PASSED=$passed\n";
