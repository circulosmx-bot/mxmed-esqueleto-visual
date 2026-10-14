<?php
declare(strict_types=1);

namespace Identity\Services;

use Identity\Contracts\AccountStatus;
use Identity\Contracts\AuthenticatedAccessContext;
use Identity\Contracts\Clock;
use Identity\Contracts\MembershipRole;
use Identity\Contracts\ProfileMembershipRoleChangeAuditPort;
use Identity\Contracts\ProfileMembershipRoleChangeAuditRecord;
use Identity\Contracts\ProfileMembershipRoleChangeRequest;
use Identity\Contracts\ProfileOwnershipApprovalEvidence;
use Identity\Contracts\ProfileOwnershipApprovalPort;
use Identity\Contracts\ProfileOwnershipStepUpEvidence;
use Identity\Contracts\ProfileOwnershipStepUpPort;
use Identity\Contracts\SessionState;
use Identity\Contracts\SystemClock;
use Identity\Repositories\AccountMembershipRepository;
use PDO;

/**
 * Backend service foundation for governed initial profile ownership.
 * No HTTP/CLI composition is provided; rejecting adapters are the only runtime defaults.
 */
final class ProfileMembershipRoleChangeService
{
    public function __construct(
        private PDO $pdo,
        private AccountMembershipRepository $memberships,
        private ProfileOwnershipApprovalPort $approvals,
        private ProfileOwnershipStepUpPort $stepUp,
        private ProfileMembershipRoleChangeAuditPort $audit,
        private Clock $clock = new SystemClock(),
    ) {
        if (!$memberships->usesConnection($pdo)) throw new \InvalidArgumentException('profile_role_change_connection_mismatch');
    }

    /** @return array{membership_id:string,account_id:string,profile_doctor_id:string,role_code:string,scope_code:string,status:string} */
    public function assignInitialOwner(AuthenticatedAccessContext $actor, ProfileMembershipRoleChangeRequest $request): array
    {
        if ($this->pdo->inTransaction()) throw new \RuntimeException('profile_role_change_outer_transaction_forbidden');
        $this->assertActor($actor,$request);
        try {
            $this->pdo->beginTransaction();
            $this->lockAndValidateAccounts($actor,$request);
            $this->lockProfile($request->profileDoctorId);
            $membership=$this->memberships->lockProfileMembership($request->membershipId);
            $this->assertMembership($membership,$request);
            $active=$this->memberships->lockActivePhysicianMembershipsForAccount($request->targetAccountId);
            if(count($active)!==1 || (string)$active[0]['membership_id']!==$request->membershipId)
                throw new \RuntimeException('profile_role_change_ambiguous_membership');
            if($this->memberships->lockActiveOwnersForProfile($request->profileDoctorId)!==[])
                throw new \RuntimeException('profile_role_change_owner_exists');

            $approval=$this->approvals->consume($this->pdo,$request,$actor->accountId());
            $this->assertApproval($approval,$request,$actor->accountId());
            $stepUp=$this->stepUp->consume($this->pdo,$request,$actor);
            $this->assertStepUp($stepUp,$request,$actor);
            if(!$this->memberships->transitionProfileRole($request))
                throw new \RuntimeException('profile_role_change_state_conflict');

            $record=new ProfileMembershipRoleChangeAuditRecord(
                $request->operationId,$actor->accountId(),(string)$actor->session()->sessionId(),
                $request->targetAccountId,$request->profileDoctorId,$request->membershipId,
                $request->expectedRole,$request->newRole,$request->expectedScope,
                $approval->approvalReference,$approval->caseReference,$approval->decisionReference,
                $approval->approverAccountId,$stepUp->auditReference,'APPROVED','VERIFIED','SUCCESS',$this->clock->now()
            );
            $this->audit->append($this->pdo,$record);
            if(!$this->pdo->commit())throw new \RuntimeException('profile_role_change_commit_failed');
            return ['membership_id'=>$request->membershipId,'account_id'=>$request->targetAccountId,
                'profile_doctor_id'=>$request->profileDoctorId,'role_code'=>MembershipRole::OWNER,
                'scope_code'=>$request->expectedScope,'status'=>$request->expectedStatus];
        } catch (\Throwable $e) {
            if($this->pdo->inTransaction())$this->pdo->rollBack();
            throw $e;
        }
    }

    private function assertActor(AuthenticatedAccessContext $actor,ProfileMembershipRoleChangeRequest $request):void
    {
        $session=$actor->session();$principal=$actor->principal();
        if($actor->accountId()!==$request->targetAccountId
            ||$session->principal()->accountId()!==$actor->accountId()
            ||$session->state()!==SessionState::ACTIVE
            ||$principal->accountStatus()!==AccountStatus::ACTIVE
            ||$session->expiresAt()<=$this->clock->now())throw new \RuntimeException('profile_role_change_authentication_required');
    }

    private function lockAndValidateAccounts(AuthenticatedAccessContext $actor,ProfileMembershipRoleChangeRequest $request):void
    {
        $ids=array_values(array_unique([$actor->accountId(),$request->targetAccountId]));sort($ids,SORT_STRING);
        foreach($ids as $id){
            $stmt=$this->pdo->prepare('SELECT status,email_verified_at FROM auth_accounts WHERE account_id=? FOR UPDATE');
            $stmt->execute([$id]);$row=$stmt->fetch(PDO::FETCH_ASSOC);
            if(!is_array($row)||$row['status']!==AccountStatus::ACTIVE||$row['email_verified_at']===null)
                throw new \RuntimeException('profile_role_change_account_ineligible');
        }
        $credential=$this->pdo->prepare('SELECT credential_version FROM auth_account_credentials WHERE account_id=? FOR UPDATE');
        $credential->execute([$actor->accountId()]);$version=$credential->fetchColumn();
        if($version===false||(int)$version!==$actor->principal()->credentialVersion())
            throw new \RuntimeException('profile_role_change_credential_version_mismatch');
    }

    private function lockProfile(string $doctorId):void
    {
        $stmt=$this->pdo->prepare('SELECT doctor_id FROM profiles_doctors WHERE doctor_id=? FOR UPDATE');$stmt->execute([$doctorId]);
        if($stmt->fetchColumn()===false)throw new \RuntimeException('profile_role_change_profile_not_found');
    }

    private function assertMembership(?array $membership,ProfileMembershipRoleChangeRequest $request):void
    {
        if(!is_array($membership))throw new \RuntimeException('profile_role_change_membership_not_found');
        $expected=['membership_id'=>$request->membershipId,'account_id'=>$request->targetAccountId,
            'profile_doctor_id'=>$request->profileDoctorId,'entity_group_id'=>null,'role_code'=>$request->expectedRole,
            'scope_code'=>$request->expectedScope,'status'=>$request->expectedStatus,'updated_at'=>$request->expectedUpdatedAt];
        foreach($expected as $key=>$value)if(($membership[$key]??null)!==$value)
            throw new \RuntimeException('profile_role_change_state_conflict');
    }

    private function assertApproval(ProfileOwnershipApprovalEvidence $e,ProfileMembershipRoleChangeRequest $r,string $executor):void
    {
        $now=$this->clock->now();
        if($e->approvalReference!==$r->approvalReference||$e->executorAccountId!==$executor
            ||$e->approverAccountId===$r->targetAccountId||$e->operationId!==$r->operationId
            ||$e->membershipId!==$r->membershipId||$e->targetAccountId!==$r->targetAccountId
            ||$e->profileDoctorId!==$r->profileDoctorId||$e->expectedRole!==$r->expectedRole
            ||$e->expectedScope!==$r->expectedScope||$e->newRole!==$r->newRole
            ||$e->caseReference===''||$e->decisionReference===''||$e->approvedAt>$now||$e->expiresAt<=$now)
            throw new \RuntimeException('profile_role_change_approval_denied');
    }

    private function assertStepUp(ProfileOwnershipStepUpEvidence $e,ProfileMembershipRoleChangeRequest $r,AuthenticatedAccessContext $actor):void
    {
        $now=$this->clock->now();
        if($e->stepUpReference!==$r->stepUpReference||$e->approvalReference!==$r->approvalReference||$e->operationId!==$r->operationId
            ||$e->accountId!==$r->targetAccountId||$e->sessionId!==(string)$actor->session()->sessionId()
            ||$e->credentialVersion!==$actor->principal()->credentialVersion()||$e->membershipId!==$r->membershipId
            ||$e->profileDoctorId!==$r->profileDoctorId||$e->expectedRole!==$r->expectedRole
            ||$e->expectedScope!==$r->expectedScope||$e->newRole!==$r->newRole
            ||$e->verifiedAt>$now||$e->expiresAt<=$now||$e->verifiedAt<$now->modify('-10 minutes'))
            throw new \RuntimeException('profile_role_change_step_up_denied');
    }
}
