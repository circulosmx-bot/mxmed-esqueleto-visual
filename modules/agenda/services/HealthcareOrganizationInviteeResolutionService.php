<?php
declare(strict_types=1);
namespace Agenda\Services;

use Identity\Contracts\AuthenticatedAccessContext;
use Identity\Contracts\IdentityAccount;
use Identity\Repositories\IdentityAccountRepository;
use PDO;
use RuntimeException;

require_once __DIR__.'/HealthcareOrganizationManagementAuthorizationService.php';
require_once __DIR__.'/HealthcareOrganizationTeamService.php';

/** Exact verified-account resolution for an owner of one organization, never a directory search. */
final class HealthcareOrganizationInviteeResolutionService
{
    private HealthcareOrganizationManagementAuthorizationService $policy;
    private IdentityAccountRepository $accounts;
    private HealthcareOrganizationTeamService $team;
    public function __construct(private PDO $pdo)
    {
        $this->policy=new HealthcareOrganizationManagementAuthorizationService($pdo);
        $this->accounts=new IdentityAccountRepository($pdo);
        $this->team=new HealthcareOrganizationTeamService($pdo);
    }

    public function resolve(?AuthenticatedAccessContext $actor,string $groupId,string $email): array
    {
        [$state]=$this->inspect($actor,$groupId,$email);
        return ['state'=>$state];
    }

    public function inviteByEmail(?AuthenticatedAccessContext $actor,string $groupId,string $email,string $role,string $submissionKey): array
    {
        [$state,$id]=$this->inspect($actor,$groupId,$email);
        if (!in_array($state,['FOUND_ELIGIBLE','PENDING_INVITATION'],true) || $id===null)
            return ['state'=>$state,'invitation'=>null];
        $invite=$this->team->invite($actor,$groupId,$id,$role,$submissionKey);
        unset($invite['invitee_account_id']);
        return ['state'=>'INVITATION_CREATED','invitation'=>$invite];
    }

    /** @return array{string,?string} */
    private function inspect(?AuthenticatedAccessContext $actor,string $groupId,string $email): array
    {
        $decision=$this->policy->evaluate($actor,$groupId,HealthcareOrganizationManagementAuthorizationService::TEAM);
        if (!$decision['allowed']) throw new HealthcareOrganizationTeamException('team_management_denied');
        if (strlen($email)>190) throw new \InvalidArgumentException('invalid_email');
        $normalized=IdentityAccount::normalizeEmail($email);
        $this->consumeAttempt($groupId,$actor->accountId());
        $account=$this->accounts->findByNormalizedEmail($normalized);
        // Keep unknown, inactive and unverified identity indistinguishable.
        if (!is_array($account) || $account['status']!=='active' || $account['email_verified_at']===null)
            return ['NOT_FOUND_OR_NOT_INVITABLE',null];
        $id=(string)$account['account_id'];
        if ($id===$actor->accountId()) return ['SELF_INVITE',null];
        $member=$this->pdo->prepare("SELECT 1 FROM auth_account_memberships WHERE account_id=? AND entity_group_id=?
            AND profile_doctor_id IS NULL AND scope_code='organization' AND status IN ('pending','active','suspended') LIMIT 1");
        $member->execute([$id,$groupId]);
        if ($member->fetchColumn()) return ['ALREADY_MEMBER',null];
        $pending=$this->pdo->prepare("SELECT 1 FROM healthcare_organization_membership_invitations
            WHERE invitee_account_id=? AND group_id=? AND status='PENDING' LIMIT 1");
        $pending->execute([$id,$groupId]);
        if ($pending->fetchColumn()) return ['PENDING_INVITATION',$id];
        return ['FOUND_ELIGIBLE',$id];
    }

    private function consumeAttempt(string $groupId,string $ownerId): void
    {
        if ($this->pdo->inTransaction()) throw new RuntimeException('nested_transaction_denied');
        $this->pdo->beginTransaction();
        try {
            $lock=$this->pdo->prepare('SELECT group_id FROM medical_groups WHERE group_id=? FOR UPDATE');
            $lock->execute([$groupId]);
            if (!$lock->fetchColumn()) throw new HealthcareOrganizationTeamException('organization_not_found');
            $count=$this->pdo->prepare('SELECT COUNT(*) FROM healthcare_organization_invitee_resolution_attempts
                WHERE group_id=? AND owner_account_id=? AND created_at>DATE_SUB(NOW(6),INTERVAL 24 HOUR)');
            $count->execute([$groupId,$ownerId]);
            if ((int)$count->fetchColumn()>=20) throw new HealthcareOrganizationTeamException('invitee_resolution_rate_limited');
            $this->pdo->prepare('INSERT INTO healthcare_organization_invitee_resolution_attempts(group_id,owner_account_id)
                VALUES (?,?)')->execute([$groupId,$ownerId]);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }
}
