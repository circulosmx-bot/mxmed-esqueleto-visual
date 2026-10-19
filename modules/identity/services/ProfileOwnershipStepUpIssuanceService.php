<?php
declare(strict_types=1);

namespace Identity\Services;

use Identity\Contracts\AccountStatus;
use Identity\Contracts\AuthenticatedAccessContext;
use Identity\Contracts\Clock;
use Identity\Contracts\ProfileMembershipRoleChangeRequest;
use Identity\Contracts\ProfileOwnershipStepUpIssuance;
use Identity\Contracts\SessionState;
use Identity\Contracts\SystemClock;
use Identity\Repositories\ProfileOwnershipStepUpRepository;
use PDO;

/**
 * Backend-only issuance boundary. No HTTP/CLI/runtime composition is provided by QA02B.
 * A future adapter must enforce HTTPS, same-origin CSRF and an authorized approval workflow.
 */
final class ProfileOwnershipStepUpIssuanceService
{
    public function __construct(
        private PDO $pdo,
        private CredentialAuthenticationService $credentials,
        private ProfileOwnershipStepUpRepository $proofs,
        private Clock $clock=new SystemClock(),
        private ?\Identity\Contracts\CaseScopedProfileOwnershipAuthorityPort $scopedAuthority=null,
    ) {if(!$proofs->usesConnection($pdo))throw new \InvalidArgumentException('profile_ownership_step_up_connection_mismatch');}

    /** @param array<string,string|null> $rateLimitDimensions */
    public function issue(AuthenticatedAccessContext $actor,ProfileMembershipRoleChangeRequest $request,string $email,
        string $password,array $rateLimitDimensions):ProfileOwnershipStepUpIssuance
    {
        if($this->pdo->inTransaction())throw new \RuntimeException('profile_ownership_step_up_outer_transaction_forbidden');
        $now=$this->clock->now();$session=$actor->session();
        if($actor->accountId()!==$request->targetAccountId||$session->state()!==SessionState::ACTIVE
            ||$session->expiresAt()<=$now||$actor->principal()->accountStatus()!==AccountStatus::ACTIVE)
            throw new \RuntimeException('profile_ownership_step_up_authentication_required');
        $decision=$this->credentials->authenticate($email,$password,$rateLimitDimensions);
        $candidate=$decision->candidate();
        if(!$decision->isAllowed()||$candidate===null||$candidate->accountId()!==$actor->accountId()
            ||$candidate->credentialVersion()!==$actor->principal()->credentialVersion())
            throw new \RuntimeException('profile_ownership_step_up_credential_denied');
        try{
            $this->pdo->beginTransaction();
            $approval=$this->pdo->prepare("SELECT approver_account_id,case_reference,executor_account_id,target_account_id,membership_id,
                profile_doctor_id,expected_role,expected_scope,new_role,status,expires_at
                FROM auth_profile_ownership_approvals WHERE approval_reference=:approval_reference
                AND operation_id=:operation_id FOR SHARE");
            $approval->execute(['approval_reference'=>$request->approvalReference,'operation_id'=>$request->operationId]);
            $row=$approval->fetch(PDO::FETCH_ASSOC);
            if(!is_array($row)||$row['status']!=='APPROVED'||$row['approver_account_id']===$request->targetAccountId
                ||$row['executor_account_id']!==$actor->accountId()||$row['target_account_id']!==$request->targetAccountId
                ||$row['membership_id']!==$request->membershipId||$row['profile_doctor_id']!==$request->profileDoctorId
                ||$row['expected_role']!==$request->expectedRole||$row['expected_scope']!==$request->expectedScope
                ||$row['new_role']!==$request->newRole||new \DateTimeImmutable((string)$row['expires_at'],new \DateTimeZone('UTC'))<=$now)
                throw new \RuntimeException('profile_ownership_step_up_approval_denied');
            $capability=InternalCapabilityCatalog::APPROVE_INITIAL_PROFILE_OWNERSHIP;
            if($this->scopedAuthority!==null){
                $case=$this->pdo->prepare('SELECT * FROM auth_governance_cases WHERE case_id=? FOR UPDATE');$case->execute([$row['case_reference']]);
                $this->scopedAuthority->assertCase($case->fetch(PDO::FETCH_ASSOC)?:[]);$capability=$this->scopedAuthority->approvalCapability();
            }
            $dual=$this->pdo->prepare("SELECT COUNT(DISTINCT approver_account_id) FROM auth_profile_ownership_approval_endorsements
                WHERE approval_reference=:approval_reference AND operation_id=:operation_id AND case_reference=:case_reference
                  AND target_account_id=:target_account_id AND membership_id=:membership_id AND profile_doctor_id=:profile_doctor_id
                  AND capability=:capability AND status='APPROVED' AND consumed_at IS NULL
                  AND consumed_operation_id IS NULL AND expires_at>:now");
            $dual->execute(['approval_reference'=>$request->approvalReference,'operation_id'=>$request->operationId,
                'case_reference'=>(string)$row['case_reference'],'target_account_id'=>$request->targetAccountId,
                'membership_id'=>$request->membershipId,'profile_doctor_id'=>$request->profileDoctorId,
                'capability'=>$capability,'now'=>$now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.u')]);
            if((int)$dual->fetchColumn()!==PhysicianOwnershipPolicy::approvalCount($this->pdo,$request->operationId,(string)$row['case_reference']))throw new \RuntimeException('profile_ownership_step_up_approval_count_required');
            $proof='stepup_'.OneTimeTokenCodec::issue();$stepUpId=OneTimeTokenCodec::id('stepup_');$expires=$now->modify('+5 minutes');
            $this->proofs->issue($stepUpId,OneTimeTokenCodec::hash($proof),$request,$actor->accountId(),
                (string)$session->sessionId(),$actor->principal()->credentialVersion(),$now,$expires);
            if(!$this->pdo->commit())throw new \RuntimeException('profile_ownership_step_up_commit_failed');
            return new ProfileOwnershipStepUpIssuance($proof,$stepUpId,$expires);
        }catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }
}
