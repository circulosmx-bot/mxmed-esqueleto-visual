<?php
declare(strict_types=1);

namespace Identity\Adapters;

use Identity\Contracts\{FirstDirectorBootstrapAuditPort,FirstDirectorBootstrapAuthorization};
use Platform\Contracts\{CanonicalAuditEventInput,TrustedAuditContext};
use Platform\Repositories\JoinedPdoCanonicalAuditTransactionAdapter;
use Platform\Services\{AuditV1PhysicalMapper,CanonicalAuditMetadataSanitizer,CanonicalAuditPolicyRegistry,
    CanonicalAuditSealer,CanonicalAuditSerializer,CanonicalAuditWriter,CoarseAuditUserAgentSummarizer,
    EnvironmentAuditSecretProvider,HmacSha256AuditIpHasher,RandomAuditUuidProvider,SystemAuditUtcClock,
    TrustedAuditContextValidator};
use PDO;

final class CanonicalFirstDirectorBootstrapAuditAdapter implements FirstDirectorBootstrapAuditPort
{
    private CanonicalAuditWriter $writer;
    public function __construct(private PDO $pdo,?CanonicalAuditWriter $writer=null)
    {
        $this->writer=$writer??new CanonicalAuditWriter(CanonicalAuditPolicyRegistry::canonical(),
            new CanonicalAuditMetadataSanitizer(),new TrustedAuditContextValidator(),new RandomAuditUuidProvider(),
            new SystemAuditUtcClock(),new HmacSha256AuditIpHasher(new EnvironmentAuditSecretProvider()),
            new CoarseAuditUserAgentSummarizer(),new JoinedPdoCanonicalAuditTransactionAdapter($pdo),
            new CanonicalAuditSealer(new CanonicalAuditSerializer()),new AuditV1PhysicalMapper());
    }
    public function append(PDO $transaction,FirstDirectorBootstrapAuthorization $a):void
    {
        if($transaction!==$this->pdo||!$this->pdo->inTransaction())throw new \RuntimeException('first_director_audit_transaction_required');
        $context=TrustedAuditContext::fromServer($a->ratifierAccountIds[0],'governance','deployment_operator',
            'platform_internal_staff',$a->operationId,$a->operationId,$a->ratifierSessionReference,null,null,'IDENTITY','internal-governance/first-director');
        $this->writer->append(new CanonicalAuditEventInput('INTERNAL_GOVERNANCE_CHANGED','SUCCESS','ADMIN_DECISION',
            'account',$a->ratifierAccountIds[0],'internal_staff',$a->targetAccountId,[
                'operation'=>'bootstrap_first_director','target_account_id'=>$a->targetAccountId,
                'capability'=>null,'before'=>null,'after'=>[
                    'governance_class'=>'DIRECTOR','status'=>'ACTIVE','case_reference'=>$a->caseReference,
                    'trust_anchor_reference'=>$a->trustAnchorReference,'approval_references_by_ratifier'=>$a->approvalReferencesByRatifier,
                    'ratifier_account_ids'=>$a->ratifierAccountIds,'risk_level'=>'R3','mfa_verified'=>true,
                    'reauthentication_verified'=>true,
                    'accepting_account_id'=>$a->acceptingAccountId,'accepting_session_id'=>$a->acceptingSessionId,
                    'accepting_credential_version'=>$a->acceptingCredentialVersion,
                    'accepting_reauthenticated_at'=>$a->acceptingReauthenticatedAt,
                ],
            ]),$context);
    }
}
