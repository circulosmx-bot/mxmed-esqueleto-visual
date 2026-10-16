<?php
declare(strict_types=1);
namespace Identity\Http;

use Identity\Adapters\{CanonicalFirstDirectorBootstrapAuditAdapter,CanonicalProfileMembershipRoleChangeAuditAdapter,Ed25519ExternalTrustAnchorAdapter,PdoGovernanceCaseFinalizationAdapter,PdoProfileOwnershipApprovalAdapter,PdoProfileOwnershipApproverAuthorityAdapter,PdoProfileOwnershipStepUpAdapter};
use Identity\Repositories\{AccountMembershipRepository,ProfileOwnershipStepUpRepository};
use Identity\Services\{FirstDirectorBootstrapService,GovernanceRuntime,ProfileMembershipRoleChangeService,ProfileOwnershipStepUpIssuanceService,R3ProfileGovernanceService,StaffMfaService};

/** Explicit opt-in composition. Missing configuration disables every privileged route. */
final class GovernanceHttpComposition
{
    private function __construct(public readonly GovernanceRuntime $runtime,public readonly Ed25519ExternalTrustAnchorAdapter $trustAnchor,
        public readonly FirstDirectorBootstrapService $bootstrap){}
    public static function fromIdentity(IdentityHttpComposition $identity):self
    {
        if(getenv('MXMED_IDENTITY_GOVERNANCE_ENABLED')!=='1')throw new \RuntimeException('identity_governance_disabled');
        $key=(string)getenv('MXMED_STAFF_MFA_ENCRYPTION_KEY');$keyId=(string)getenv('MXMED_STAFF_MFA_KEY_ID');
        $trustKey=(string)getenv('MXMED_GOVERNANCE_TRUST_ANCHOR_PUBLIC_KEY');$organization=(string)getenv('MXMED_GOVERNANCE_ORGANIZATION_ID');
        if($key===''||$keyId===''||$trustKey===''||$organization==='')throw new \RuntimeException('identity_governance_configuration_required');
        $operators=null;
        if(\Identity\Operators\OperatorDeploymentConfiguration::requested())$operators=\Identity\Operators\OperatorRuntimeComposition::fromIdentity($identity)->http->operators;
        $pdo=$identity->pdo();$mfa=new StaffMfaService($pdo,$key,$keyId);$authority=new PdoProfileOwnershipApproverAuthorityAdapter($pdo);
        $cases=new R3ProfileGovernanceService($pdo,$mfa,$authority,operators:$operators);$proofs=new ProfileOwnershipStepUpRepository($pdo);
        $beneficiary=new ProfileOwnershipStepUpIssuanceService($pdo,$identity->authentication(),$proofs);
        $roles=new ProfileMembershipRoleChangeService($pdo,new AccountMembershipRepository($pdo),new PdoProfileOwnershipApprovalAdapter($pdo,$authority),new PdoProfileOwnershipStepUpAdapter($pdo,$proofs),new CanonicalProfileMembershipRoleChangeAuditAdapter($pdo),finalization:new PdoGovernanceCaseFinalizationAdapter($pdo),operators:$operators);
        $runtime=new GovernanceRuntime($pdo,$cases,$mfa,$beneficiary,$roles);
        $trust=new Ed25519ExternalTrustAnchorAdapter($pdo,$trustKey,$organization,$identity->environment());
        $bootstrap=new FirstDirectorBootstrapService($pdo,$trust,new CanonicalFirstDirectorBootstrapAuditAdapter($pdo));
        return new self($runtime,$trust,$bootstrap);
    }
}
