<?php
declare(strict_types=1);
require_once __DIR__.'/../services/PublicProfileClaimEligibility.php';
require_once __DIR__.'/../services/PublicEnrollmentPresentation.php';
use Profiles\Services\{PublicProfileClaimEligibility,PublicEnrollmentPresentation};
$checks=0;
function enrollmentCheck(bool $pass,string $label):void {global $checks;if(!$pass)throw new RuntimeException($label);$checks++;}
$plan=['source'=>'profiles_doctors','plan_code'=>'free'];$ownerless=['source_ready'=>true,'has_active_owner'=>false];
$claim=PublicProfileClaimEligibility::build('fixture-1',$plan,true,$ownerless);
enrollmentCheck($claim['eligible']&&$claim['submission_available']===false,'public navigation never enables submissions');
foreach ([['source_ready'=>false,'has_active_owner'=>false],['source_ready'=>true,'has_active_owner'=>true],[]] as $state)
    enrollmentCheck(!PublicProfileClaimEligibility::build('fixture-1',$plan,true,$state)['eligible'],'owner source fails closed');
foreach (['basic','standard','optimum','professional','unknown',''] as $code)
    enrollmentCheck(!PublicProfileClaimEligibility::build('fixture-1',['source'=>'profiles_doctors','plan_code'=>$code],true,$ownerless)['eligible'],'only stored free is eligible');
enrollmentCheck(!PublicProfileClaimEligibility::build('fixture-1',['source'=>'default_free','plan_code'=>null],true,$ownerless)['eligible'],'missing plan is not authority');
enrollmentCheck(!PublicProfileClaimEligibility::build('fixture-1',$plan,false,$ownerless)['eligible'],'hidden profile has no claim navigation');
enrollmentCheck(!PublicProfileClaimEligibility::build('invalid/id',$plan,true,$ownerless)['eligible'],'invalid ID fails closed');
$fixture=['ok'=>true,'data'=>['profile'=>['doctor_id'=>'fixture-1','is_public'=>true],
    'claim'=>['claim_allowed'=>true,'claim_url'=>$claim['url']],
    'identity'=>['display_name'=>'Profesional de prueba','professional_designation'=>'Especialidad de prueba']]];
$lookups=0;$lookup=function(string $id)use(&$lookups,$fixture):array{$lookups++;return $fixture;};
$context=PublicEnrollmentPresentation::resolve(['entry'=>'claim','profile'=>'fixture-1'],$lookup);
enrollmentCheck($context['mode']==='claim'&&$context['display_name']==='Profesional de prueba','exact current public context resolves');
enrollmentCheck($context['origin_saved']===false&&$context['presentation_only']===true,'no persisted origin is claimed');
enrollmentCheck($context['return_url']==='/profiles/doctor.php?doctor_id=fixture-1','existing original route preserved as display return');
enrollmentCheck(PublicEnrollmentPresentation::resolve(['entry'=>'claim','profile'=>'different-profile'],$lookup)['mode']==='unavailable','response cannot substitute profile');
foreach ([['entry'=>'claim','profile'=>['fixture-1']],['entry'=>'claim','profile'=>'../secret'],['entry'=>['claim']],['entry'=>'join','profile'=>'fixture-1']] as $query)
    enrollmentCheck(PublicEnrollmentPresentation::resolve($query,$lookup)['mode']==='unavailable','malformed or mixed origin unavailable');
$before=$lookups;
enrollmentCheck(PublicEnrollmentPresentation::resolve(['entry'=>'join'],$lookup)['mode']==='join'&&$lookups===$before,'general enrollment requires no profile');
enrollmentCheck(PublicEnrollmentPresentation::resolve([],$lookup)===null,'ordinary resume stays canonical');
$blocked=$fixture;$blocked['data']['claim']['claim_allowed']=false;
enrollmentCheck(PublicEnrollmentPresentation::resolve(['entry'=>'claim','profile'=>'fixture-1'],fn()=>$blocked)['mode']==='unavailable','current owned or ineligible profile denies context');
echo 'PublicEnrollmentPresentationTest PASS '.$checks." assertions\n";
