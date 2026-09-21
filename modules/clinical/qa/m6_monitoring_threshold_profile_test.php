<?php
declare(strict_types=1);
require_once dirname(__DIR__,3).'/api/_lib/clinical_m6_monitoring_analysis.php';
$passed=0;function profile_check(bool $ok,string $name):void{global $passed;if(!$ok)throw new RuntimeException('FAIL '.$name);$passed++;}
function profile_fixture(array $overrides=[]):array{return array_replace_recursive([
 'schema'=>'mxmed.m6.threshold-profile.v1','profile_id'=>'DISPOSABLE_TEST_THRESHOLD','profile_version'=>1,'status'=>'active','rollout_stage'=>'internal_exact_pair',
 'rules'=>[
  ['id'=>'unexpected_error_rate','maximum_rate'=>0.1,'minimum_requests'=>10,'unit'=>'ratio','action'=>'DEACTIVATE'],
  ['id'=>'route_latency','mode'=>'both','maximum_ms'=>250,'maximum_multiplier'=>1.5,'minimum_samples'=>10,'unit'=>'milliseconds','action'=>'HOLD'],
  ['id'=>'visibility','maximum_age_seconds'=>300,'unit'=>'seconds','action'=>'HOLD']]
],$overrides);}
function profile_summary(int $failures,float $latency,int $count=20):array{return ['request_count'=>$count,'unexpected_error_event_count'=>$failures,
 'unexpected_fallback_count'=>0,'parallel_writer_evidence_count'=>0,'error_counts'=>[],'status_counts'=>[],
 'latency'=>['R'=>['count'=>$count,'p95_ms'=>$latency,'avg_ms'=>$latency,'max_ms'=>$latency,'min_ms'=>$latency]]];}
$tmp=sys_get_temp_dir().'/mxmed-profile-'.bin2hex(random_bytes(5)).'.json';
try{
 $profile=profile_fixture();file_put_contents($tmp,json_encode($profile,JSON_THROW_ON_ERROR));
 $one=clinical_m6_monitoring_profile_load($tmp,'internal_exact_pair');
 $two=clinical_m6_monitoring_profile_load($tmp,'internal_exact_pair');
 profile_check($one['authority']['valid']===true,'PROFILE-02 valid');
 profile_check($one['authority']['hash']===$two['authority']['hash'],'PROFILE-08 stable hash');
 $rules=clinical_m6_monitoring_rules_apply_profile(clinical_m6_monitoring_rules_load(),$profile);
 $health=['healthy'=>true,'stale'=>false,'visibility_loss'=>false,'latest_event_at'=>gmdate('c')];
 $healthyComparison=['latency'=>['R'=>['p95_ms'=>['relative_delta'=>0.1]]],'monitoring_health'=>['degraded'=>false]];
 profile_check(clinical_m6_monitoring_rules_evaluate(profile_summary(0,110),$health,$rules,$healthyComparison)['recommended_action']==='CONTINUE','healthy profile');
 profile_check(clinical_m6_monitoring_rules_evaluate(profile_summary(4,110),$health,$rules,$healthyComparison)['recommended_action']==='DEACTIVATE','high error profile');
 $slow=['latency'=>['R'=>['p95_ms'=>['relative_delta'=>4.0]]],'monitoring_health'=>['degraded'=>false]];
 profile_check(clinical_m6_monitoring_rules_evaluate(profile_summary(0,500),$health,$rules,$slow)['recommended_action']==='HOLD','high latency profile');
 $small=clinical_m6_monitoring_rules_evaluate(profile_summary(0,100,2),$health,$rules,$healthyComparison);
 profile_check($small['recommended_action']==='HOLD','minimum sample fail safe');
 $noBaseline=clinical_m6_monitoring_rules_evaluate(profile_summary(0,100),$health,$rules,['latency'=>[],'monitoring_health'=>['degraded'=>false]]);
 profile_check($noBaseline['recommended_action']==='HOLD','baseline insufficient fail safe');
 $canonical=clinical_m6_monitoring_rules_load();$zero=profile_summary(0,100);$zero['unexpected_fallback_count']=1;
 profile_check(clinical_m6_monitoring_rules_evaluate($zero,$health,$canonical)['recommended_action']==='EMERGENCY_HALT','zero tolerance independent');
 $invalids=[];
 $invalids[]=['raw'=>'{'];
 $missingError=profile_fixture();array_shift($missingError['rules']);$invalids[]=$missingError;
 $missingLatency=profile_fixture();array_splice($missingLatency['rules'],1,1);$invalids[]=$missingLatency;
 $missingErrorThreshold=profile_fixture();unset($missingErrorThreshold['rules'][0]['maximum_rate']);$invalids[]=$missingErrorThreshold;
 $missingLatencyThreshold=profile_fixture();unset($missingLatencyThreshold['rules'][1]['maximum_multiplier']);$invalids[]=$missingLatencyThreshold;
 $unknown=profile_fixture();$unknown['rules'][0]['id']='unknown';$invalids[]=$unknown;
 $wrongStage=profile_fixture(['rollout_stage'=>'bounded_pair_set']);$invalids[]=$wrongStage;
 $wrongSchema=profile_fixture(['schema'=>'mxmed.m6.threshold-profile.v2']);$invalids[]=$wrongSchema;
 foreach($invalids as $index=>$invalid){$rejected=false;try{if(isset($invalid['raw']))file_put_contents($tmp,$invalid['raw']);else file_put_contents($tmp,json_encode($invalid,JSON_THROW_ON_ERROR));clinical_m6_monitoring_profile_load($tmp,'internal_exact_pair');}catch(Throwable){$rejected=true;}profile_check($rejected,'PROFILE invalid '.($index+3));}
}finally{if(is_file($tmp))unlink($tmp);}
echo "M6_MONITORING_THRESHOLD_PROFILE_TESTS_PASSED=$passed\n";
