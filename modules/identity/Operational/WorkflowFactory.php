<?php
declare(strict_types=1);
namespace Identity\Operational;
use Identity\Accreditation\{EvidenceAuthority,EvidenceAudit,EvidenceCipher,PhysicianAdmissionComposition,PhoneVerificationService,PublicEnrollmentService,PublicEnrollmentHttp,ReviewedEnrollmentService,PhysicianOnboardingHttp};
use Identity\Http\IdentityHttpComposition;
use Identity\Services\{GovernanceSecurityContext,StaffMfaService};
use Profiles\Corrections\{ReportService,ReportCipher,ReportRatePolicy,ReportReviewService,ReportHttp,ApplicationService,ApplicationAuthority};

/** One composition of the existing writers. No keys/providers synthesized here. */
final class WorkflowFactory
{
    public static function create(IdentityHttpComposition $identity,string $authorization,string $trust,array $secrets,?\Agenda\Contracts\OtpProviderPort $sms):array
    {
        $a=ActivationGuard::verify($identity,$authorization,$trust,$secrets);$scope=new WorkflowScope($a);
        // No privileged service is built until ALL selected dependencies have passed.
        $root=$secrets['storage_root']??'';
        if(!is_string($root)||realpath($root)!==$root||!is_dir($root)||(fileperms($root)&0077)!==0||is_link($root)||str_starts_with($root,DeploymentPackage::root().'/')
            ||($a['storage_root']??null)!==$root)throw new \RuntimeException('act02_private_storage_required');
        if($sms===null||!$sms->configured())throw new \RuntimeException('act02_real_sms_adapter_pending');
        $class=new \ReflectionClass($sms);$file=$class->getFileName();
        if($a['mode']!=='synthetic'&&(!in_array($class->getName(),SmsIntegration::APPROVED_ADAPTERS,true)||($a['sms_provider']??null)!==$sms->providerId()
            ||!is_string($file)||($a['sms_source_sha256']??null)!==hash_file('sha256',$file)))throw new \RuntimeException('act02_sms_not_approved');
        foreach(['evidence_key','report_key'] as $name)if(strlen($secrets[$name])!==32)throw new \RuntimeException('act02_cipher_key_length');
        require_once DeploymentPackage::root().'/modules/profiles/Corrections/bootstrap.php';
        require_once DeploymentPackage::root().'/modules/media/storage/LocalPersistentPrivateMediaStorage.php';
        $p=$identity->pdo();$security=new GovernanceSecurityContext($identity->sessions(),$identity->authentication());
        $mfa=new StaffMfaService($p,base64_encode($secrets['mfa_key']),$secrets['mfa_key_id'],security:$security);
        $cipher=new EvidenceCipher($secrets['evidence_key_id'],[$secrets['evidence_key_id']=>$secrets['evidence_key']],$a['policies']['retention']);
        $storage=new \Media\Storage\LocalPersistentPrivateMediaStorage($root);
        $admission=new PhysicianAdmissionComposition($p,$security,$cipher,$storage,$mfa,scope:$scope);
        $authority=new EvidenceAuthority($p,$security,scope:$scope);
        $enrollment=new PublicEnrollmentService($authority,$cipher,$secrets['proof_key']);
        $sources=in_array('professional_source_provisioning',$a['workflows'],true)?new \Identity\ProfessionalSources\SourceProvisioningService($enrollment,$cipher,$storage,$mfa):null;
        $review=new ReviewedEnrollmentService($enrollment,$mfa,$admission->projection,$sources!==null);
        $phone=new PhoneVerificationService($authority,$cipher,new EvidenceAudit($p),$sms);
        $reportCipher=new ReportCipher($secrets['report_key_id'],[$secrets['report_key_id']=>$secrets['report_key']],$a['policies']['retention']);
        $reports=new ReportService($p,$reportCipher,$identity->csrf(),new ReportRatePolicy($p,$reportCipher,browserLimit:$a['rate_limits']['browser'],peerLimit:$a['rate_limits']['peer'],profileLimit:$a['rate_limits']['profile']),scope:$scope);
        $reportReview=new ReportReviewService($reports,$security,$mfa);
        return ['enrollment'=>new PublicEnrollmentHttp($identity,$enrollment,$secrets['transport_key'],$review),
            'onboarding'=>new PhysicianOnboardingHttp($identity,$admission,$phone,$review,$scope,$sources),
            'reports'=>new ReportHttp($identity,$reports,$reportReview,$secrets['transport_key'],
                in_array('correction_apply',$a['workflows'],true)?new ApplicationService(new ApplicationAuthority($reports,$security),$mfa):null),
            'sources'=>$sources,'scope'=>$scope];
    }
}
