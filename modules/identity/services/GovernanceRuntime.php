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
        private ProfileMembershipRoleChangeService $roleChanges){}

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
        if(!is_array($r)||$r['status']!=='APPROVED'||$r['beneficiary_account_id']!==$actor->accountId()||new \DateTimeImmutable($r['expires_at'],new \DateTimeZone('UTC'))<=new \DateTimeImmutable('now',new \DateTimeZone('UTC')))throw new \RuntimeException('governance_beneficiary_acceptance_denied');
        return new ProfileMembershipRoleChangeRequest($r['operation_id'],$r['target_membership_id'],$r['beneficiary_account_id'],$r['profile_doctor_id'],$r['role_code'],$r['scope_code'],$r['membership_status'],substr((string)$r['updated_at'],0,19),'owner',$r['approval_reference'],$stepUp);
    }
}
