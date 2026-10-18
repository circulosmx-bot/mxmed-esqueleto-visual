<?php
declare(strict_types=1);
namespace Identity\Services;

use Identity\Contracts\{AuthenticatedAccessContext,ProfileMembershipRoleChangeRequest};
use PDO;

/** Composes beneficiary reauthentication and the existing canonical membership writer. */
final class GovernanceRuntime
{
    public function __construct(private PDO $pdo,public readonly R3ProfileGovernanceService $cases,
        public readonly StaffMfaService $mfa,private ProfileOwnershipStepUpIssuanceService $beneficiaryStepUp,
        private ProfileMembershipRoleChangeService $roleChanges,private \Identity\Contracts\Clock $clock=new \Identity\Contracts\SystemClock()){}

    public static function staff(PDO $pdo,GovernanceSecurityContext $security,StaffMfaService $mfa,
        \Identity\Contracts\StaffIdentityReviewPort $reviews,\Identity\Contracts\Clock $clock=new \Identity\Contracts\SystemClock()): \Identity\MasterGovernance\Cases
    {return GovernanceCaseSecurity::staff($pdo,$security,$mfa,$reviews,$clock);}

    /** @param array<string,string|null> $dimensions */
    public function issueBeneficiaryStepUp(AuthenticatedAccessContext $actor,string $caseId,string $email,string $password,array $dimensions):array
    {
        $request=$this->request($actor,$caseId,'pending_stepup_reference');$issued=$this->beneficiaryStepUp->issue($actor,$request,$email,$password,$dimensions);
        return ['step_up_proof'=>$issued->proof,'expires_at'=>$issued->expiresAt];
    }

    public function finalizeInitialOwner(AuthenticatedAccessContext $actor,string $caseId,string $rawStepUpProof):array
    {
        return $this->roleChanges->assignInitialOwner($actor,$this->request($actor,$caseId,$rawStepUpProof));
    }

    private function request(AuthenticatedAccessContext $actor,string $caseId,string $stepUp):ProfileMembershipRoleChangeRequest
    {
        $s=$this->pdo->prepare("SELECT c.operation_id,c.beneficiary_account_id,c.target_membership_id,c.profile_doctor_id,c.status,c.expires_at,a.approval_reference,m.role_code,m.scope_code,m.status membership_status,m.updated_at FROM auth_governance_cases c JOIN auth_profile_ownership_approvals a ON a.operation_id=c.operation_id JOIN auth_account_memberships m ON m.membership_id=c.target_membership_id WHERE c.case_id=? AND c.operation='INITIAL_OWNER'");$s->execute([$caseId]);$r=$s->fetch(PDO::FETCH_ASSOC);
        if(!is_array($r)||$r['status']!=='APPROVED'||$r['beneficiary_account_id']!==$actor->accountId()||new \DateTimeImmutable($r['expires_at'],new \DateTimeZone('UTC'))<=$this->clock->now())throw new \RuntimeException('governance_beneficiary_acceptance_denied');
        $s=$this->pdo->prepare('SELECT security_context FROM auth_governance_cases WHERE case_id=?');$s->execute([$caseId]);
        $binding=json_decode((string)$s->fetchColumn(),true,32,JSON_THROW_ON_ERROR);
        if(($binding['version']??null)!==2||!is_array($binding['membership']??null))throw new \RuntimeException('governance_versioned_approval_required');
        $m=$binding['membership'];
        return new ProfileMembershipRoleChangeRequest($r['operation_id'],$r['target_membership_id'],$r['beneficiary_account_id'],$r['profile_doctor_id'],$m['role_code'],$m['scope_code'],$m['status'],substr((string)$m['updated_at'],0,19),'owner',$r['approval_reference'],$stepUp);
    }
}
