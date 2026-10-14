<?php
declare(strict_types=1);

namespace Identity\Adapters;

use Identity\Contracts\ProfileMembershipRoleChangeAuditPort;
use Identity\Contracts\ProfileMembershipRoleChangeAuditRecord;
use Platform\Contracts\CanonicalAuditEventInput;
use Platform\Contracts\TrustedAuditContext;
use Platform\Repositories\JoinedPdoCanonicalAuditTransactionAdapter;
use Platform\Services\AuditV1PhysicalMapper;
use Platform\Services\CanonicalAuditMetadataSanitizer;
use Platform\Services\CanonicalAuditPolicyRegistry;
use Platform\Services\CanonicalAuditSealer;
use Platform\Services\CanonicalAuditSerializer;
use Platform\Services\CanonicalAuditWriter;
use Platform\Services\CoarseAuditUserAgentSummarizer;
use Platform\Services\EnvironmentAuditSecretProvider;
use Platform\Services\HmacSha256AuditIpHasher;
use Platform\Services\RandomAuditUuidProvider;
use Platform\Services\SystemAuditUtcClock;
use Platform\Services\TrustedAuditContextValidator;
use PDO;

/** Writes the accepted three-event contract into the caller-owned transaction. */
final class CanonicalProfileMembershipRoleChangeAuditAdapter implements ProfileMembershipRoleChangeAuditPort
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

    public function append(PDO $transaction,ProfileMembershipRoleChangeAuditRecord $record):void
    {
        if($transaction!==$this->pdo||!$this->pdo->inTransaction())throw new \RuntimeException('profile_role_change_audit_transaction_required');
        $context=TrustedAuditContext::fromServer($record->actorAccountId,'account',$record->priorRole,
            $record->scope,$record->operationId,$record->operationId,$record->sessionId,null,null,
            'IDENTITY','internal/profile-membership/initial-owner');
        $common=['operation_id'=>$record->operationId,'target_account_id'=>$record->targetAccountId,
            'membership_id'=>$record->membershipId,'profile_doctor_id'=>$record->profileDoctorId,
            'prior_role'=>$record->priorRole,'new_role'=>$record->newRole,'scope_code'=>$record->scope,
            'approval_reference'=>$record->approvalReference,'case_reference'=>$record->caseReference,
            'approval_decision_reference'=>$record->approvalDecisionReference,
            'approval_authority_account_id'=>$record->approverAccountId,
            'approval_authority_account_ids'=>$record->approverAccountIds,
            'step_up_reference'=>$record->stepUpReference,'approval_decision'=>$record->approvalDecision,
            'step_up_decision'=>$record->stepUpDecision,'transaction_outcome'=>$record->transactionOutcome];
        $this->writer->append(new CanonicalAuditEventInput('ROLE_REVOKED','SUCCESS','ADMIN_DECISION','account',
            $record->targetAccountId,'account_membership',$record->membershipId,
            $common+['role_code'=>$record->priorRole,'changed_field_names'=>['role_code']]),$context);
        $this->writer->append(new CanonicalAuditEventInput('ROLE_ASSIGNED','SUCCESS','ADMIN_DECISION','account',
            $record->targetAccountId,'account_membership',$record->membershipId,
            $common+['role_code'=>$record->newRole,'changed_field_names'=>['role_code']]),$context);
        $this->writer->append(new CanonicalAuditEventInput('PROFILE_OWNERSHIP_ASSIGNED','SUCCESS','ADMIN_DECISION','account',
            $record->targetAccountId,'profile_doctor',$record->profileDoctorId,
            $common+['ownership_reference'=>$record->membershipId,'assignment_method'=>'INITIAL_OWNERSHIP_ASSIGNMENT']),$context);
    }
}
