<?php
declare(strict_types=1);
namespace Identity\Http;

use Identity\Adapters\{CanonicalFirstDirectorBootstrapAuditAdapter,CanonicalProfileMembershipRoleChangeAuditAdapter,Ed25519ExternalTrustAnchorAdapter,PdoGovernanceCaseFinalizationAdapter,PdoProfileOwnershipApprovalAdapter,PdoProfileOwnershipApproverAuthorityAdapter,PdoProfileOwnershipStepUpAdapter};
use Identity\Repositories\{AccountMembershipRepository,ProfileOwnershipStepUpRepository};
use Identity\Services\{FirstDirectorBootstrapService,GovernanceRuntime,ProfileMembershipRoleChangeService,ProfileOwnershipStepUpIssuanceService,R3ProfileGovernanceService,StaffMfaService};

/** Explicit opt-in composition. Missing configuration disables every privileged route. */
final class GovernanceHttpComposition
{
    private function __construct(public readonly GovernanceRuntime $runtime,public readonly ?Ed25519ExternalTrustAnchorAdapter $trustAnchor,
        public readonly FirstDirectorBootstrapService $bootstrap,public readonly \Identity\Services\GovernanceStaffMfaCeremony $enrollment,
        public readonly \Identity\Services\GovernanceSecurityContext $security){}
    public static function fromIdentity(IdentityHttpComposition $identity,string $action='http',?string $target=null):self
    {
        $flag=$action==='http'?'MXMED_IDENTITY_GOVERNANCE_ENABLED':'MXMED_GOVERNANCE_CEREMONY_ENABLED';
        if(getenv($flag)!=='1')throw new \RuntimeException('identity_governance_disabled');
        $key=(string)getenv('MXMED_STAFF_MFA_ENCRYPTION_KEY');$keyId=(string)getenv('MXMED_STAFF_MFA_KEY_ID');
        $trustKey=(string)getenv('MXMED_GOVERNANCE_TRUST_ANCHOR_PUBLIC_KEY');$organization=(string)getenv('MXMED_GOVERNANCE_ORGANIZATION_ID');
        if($key===''||$keyId===''||$organization==='')throw new \RuntimeException('identity_governance_configuration_required');
        GovernanceDeploymentAuthorization::assertSchema($identity->pdo());
        $deployment=GovernanceDeploymentAuthorization::require($identity->pdo(),$action,$target,$identity->environment());
        (new \Platform\Services\EnvironmentAuditSecretProvider())->currentAuditIpKey();
        $operators=null;
        if(\Identity\Operators\OperatorDeploymentConfiguration::requested())$operators=\Identity\Operators\OperatorRuntimeComposition::fromIdentity($identity)->http->operators;
        $pdo=$identity->pdo();$security=new \Identity\Services\GovernanceSecurityContext($identity->sessions(),$identity->authentication());
        $mfa=new StaffMfaService($pdo,$key,$keyId,security:$security);
        $trust=$trustKey===''?null:new Ed25519ExternalTrustAnchorAdapter($pdo,$trustKey,$organization,$identity->environment());
        $eligibility=new \Identity\Adapters\CanonicalInitialOwnerEligibilityAdapter();
        $audit=new \Identity\Adapters\CanonicalGovernanceAuditAdapter($pdo);
        $bindings=new \Identity\Services\GovernanceCaseSecurity($pdo,$security,$eligibility,$trust,$audit);$authority=new PdoProfileOwnershipApproverAuthorityAdapter($pdo);
        $cases=new R3ProfileGovernanceService($pdo,$mfa,$authority,operators:$operators,security:$bindings);$proofs=new ProfileOwnershipStepUpRepository($pdo);
        $beneficiary=new ProfileOwnershipStepUpIssuanceService($pdo,$identity->authentication(),$proofs);
        $roles=new ProfileMembershipRoleChangeService($pdo,new AccountMembershipRepository($pdo),new PdoProfileOwnershipApprovalAdapter($pdo,$authority),new PdoProfileOwnershipStepUpAdapter($pdo,$proofs),new CanonicalProfileMembershipRoleChangeAuditAdapter($pdo),finalization:new PdoGovernanceCaseFinalizationAdapter($pdo),operators:$operators,eligibility:$eligibility,security:$bindings);
        $runtime=new GovernanceRuntime($pdo,$cases,$mfa,$beneficiary,$roles);
        $bootstrap=new FirstDirectorBootstrapService($pdo,$trust??new \Identity\Adapters\RejectingFirstDirectorBootstrapAuthorizationAdapter(),new CanonicalFirstDirectorBootstrapAuditAdapter($pdo),security:$security);
        return new self($runtime,$trust,$bootstrap,new \Identity\Services\GovernanceStaffMfaCeremony($pdo,$mfa,$security,$audit,approvedRegistrars:$deployment['mfa_registrar_account_ids']??[]),$security);
    }
}
