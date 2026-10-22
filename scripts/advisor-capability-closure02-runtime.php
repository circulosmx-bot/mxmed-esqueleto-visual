<?php
declare(strict_types=1);
require_once __DIR__.'/ase-advisor-workspace02-runtime.php';
/** Explicit disposable injection: same original services and HTTP/page renderer. */
function closure02Services():array
{
    $s=workspace02OwnedServices();$o=realpath((string)getenv('ASE_INT02A_OWNED'));
    $file=$o.'/closure-scope.json';
    if(is_link($file)||!is_file($file)||(fileperms($file)&0077)!==0)throw new RuntimeException('closure_scope_required');
    $cohort=json_decode(file_get_contents($file),true,16,JSON_THROW_ON_ERROR);
    $scope=new Identity\Operational\WorkflowScope($cohort);
    $a=new Identity\Accreditation\EvidenceAuthority($s['identity']->pdo(),$s['security'],scope:$scope);
    $enrollment=new Identity\Accreditation\PublicEnrollmentService($a,$s['cipher'],file_get_contents($o.'/workspace02-proof-key'));
    $composition=new Identity\Accreditation\PhysicianAdmissionComposition($a->pdo,$s['security'],$s['cipher'],$s['storage'],$s['mfa'],scope:$scope);
    $sources=new Identity\ProfessionalSources\SourceProvisioningService($enrollment,$s['cipher'],$s['storage'],$s['mfa']);
    $review=new Identity\Accreditation\ReviewedEnrollmentService($enrollment,$s['mfa'],$composition->projection,true);
    require_once __DIR__.'/../modules/identity/tests/support/C4UiSyntheticSms.php';
    $phone=new Identity\Accreditation\PhoneVerificationService($a,$s['cipher'],new Identity\Accreditation\EvidenceAudit($a->pdo),new C4UiSyntheticSms());
    $s['onboarding']=new Identity\Accreditation\PhysicianOnboardingHttp($s['identity'],$composition,$phone,$review,$scope,$sources);
    $s['review_scope']=$scope;
    $s['provider_claims']=new Identity\PhysicianRequests\ProviderClaimReview($a->pdo,$s['security'],$cohort['provider_claims']??[]);
    return $s;
}
