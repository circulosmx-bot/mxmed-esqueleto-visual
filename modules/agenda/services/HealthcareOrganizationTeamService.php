<?php
declare(strict_types=1);

namespace Agenda\Services;

use Identity\Contracts\AuthenticatedAccessContext;
use PDO;
use PDOException;
use RuntimeException;

require_once __DIR__.'/HealthcareOrganizationManagementAuthorizationService.php';

final class HealthcareOrganizationTeamException extends RuntimeException
{
    public function __construct(public readonly string $reason) { parent::__construct($reason); }
}

/** Trusted service only; no public invitation or management HTTP route in PROV04C. */
final class HealthcareOrganizationTeamService
{
    public const DEFAULT_TTL_DAYS = 7;
    private HealthcareOrganizationManagementAuthorizationService $policy;

    public function __construct(private PDO $pdo)
    {
        $this->policy=new HealthcareOrganizationManagementAuthorizationService($pdo);
    }

    public function invite(?AuthenticatedAccessContext $actor,string $groupId,string $inviteeAccountId,
        string $role,string $submissionKey): array
    {
        $inviter=$this->actorId($actor);
        if (!in_array($role,['administrator','collaborator'],true)) throw new HealthcareOrganizationTeamException('invalid_invitation_role');
        if (!preg_match('/^[A-Za-z0-9._:-]{8,128}$/D',$submissionKey)) throw new HealthcareOrganizationTeamException('invalid_submission_key');
        if ($inviter===$inviteeAccountId) throw new HealthcareOrganizationTeamException('self_invitation_denied');
        $hash=hash('sha256',json_encode([$groupId,$inviteeAccountId,$role],JSON_THROW_ON_ERROR));
        return $this->transaction(function () use ($actor,$inviter,$groupId,$inviteeAccountId,$role,$submissionKey,$hash): array {
            $this->lockOrganization($groupId);
            $this->requireTeam($actor,$groupId);
            $this->lockVerifiedAccount($inviteeAccountId);
            $replay=$this->pdo->prepare('SELECT * FROM healthcare_organization_membership_invitations WHERE inviter_account_id=? AND submission_key=?');
            $replay->execute([$inviter,$submissionKey]);
            $existing=$replay->fetch(PDO::FETCH_ASSOC);
            if (is_array($existing)) {
                if (!hash_equals($existing['request_hash'],$hash)) throw new HealthcareOrganizationTeamException('idempotency_conflict');
                return $this->projection($existing);
            }
            $member=$this->membershipForAccount($groupId,$inviteeAccountId,true);
            if ($member!==null && $member['status']!=='revoked') throw new HealthcareOrganizationTeamException('membership_already_exists');
            $uuid=self::uuid();
            try {
                $this->pdo->prepare("INSERT INTO healthcare_organization_membership_invitations
                    (invitation_uuid,group_id,inviter_account_id,invitee_account_id,intended_role,status,submission_key,request_hash,expires_at)
                    VALUES (?,?,?,?,?,'PENDING',?,?,DATE_ADD(NOW(6),INTERVAL 7 DAY))")
                    ->execute([$uuid,$groupId,$inviter,$inviteeAccountId,$role,$submissionKey,$hash]);
            } catch (PDOException $e) {
                if ($e->getCode()==='23000') throw new HealthcareOrganizationTeamException('pending_invitation_exists');
                throw $e;
            }
            $this->event($uuid,$groupId,$inviter,$role,'INVITED',null,'PENDING');
            return $this->projection($this->invitation($uuid,false));
        });
    }

    public function accept(?AuthenticatedAccessContext $actor,string $invitationUuid): array
    {
        $accountId=$this->actorId($actor);
        $result=$this->transaction(function () use ($accountId,$invitationUuid): array {
            $inv=$this->invitation($invitationUuid,true);
            if ($inv['invitee_account_id']!==$accountId) throw new HealthcareOrganizationTeamException('invitation_not_found');
            $this->lockVerifiedAccount($accountId);
            if ($inv['status']==='ACCEPTED') {
                $member=$this->membershipForAccount($inv['group_id'],$accountId,false);
                if ($member!==null && $member['membership_id']===$inv['membership_id']
                    && $member['role_code']===$inv['intended_role'] && $member['status']==='active') return $this->projection($inv);
                throw new HealthcareOrganizationTeamException('accepted_membership_inactive');
            }
            if ($inv['status']!=='PENDING') throw new HealthcareOrganizationTeamException('invitation_terminal');
            if (strtotime($inv['expires_at'])<=time()) {
                $this->pdo->prepare("UPDATE healthcare_organization_membership_invitations SET status='EXPIRED' WHERE invitation_uuid=? AND status='PENDING'")
                    ->execute([$invitationUuid]);
                $this->event($invitationUuid,$inv['group_id'],$accountId,$inv['intended_role'],'EXPIRED','PENDING','EXPIRED');
                return ['expired'=>true];
            }
            if (!in_array($inv['intended_role'],['administrator','collaborator'],true)) throw new HealthcareOrganizationTeamException('invalid_invitation_role');
            $this->lockOrganization($inv['group_id']);
            $member=$this->membershipForAccount($inv['group_id'],$accountId,true);
            if ($member!==null) {
                if ($member['role_code']!==$inv['intended_role'] || $member['status']!=='active')
                    throw new HealthcareOrganizationTeamException('membership_conflict');
                $membershipId=$member['membership_id'];
            } else {
                $membershipId=self::uuid();
                $this->pdo->prepare("INSERT INTO auth_account_memberships
                    (membership_id,account_id,entity_group_id,role_code,scope_code,status,assignment_source)
                    VALUES (?,?,?,?,'organization','active','provider_invitation_acceptance')")
                    ->execute([$membershipId,$accountId,$inv['group_id'],$inv['intended_role']]);
            }
            $this->pdo->prepare("UPDATE healthcare_organization_membership_invitations
                SET status='ACCEPTED',accepted_at=NOW(6),accepted_by_account_id=?,membership_id=?
                WHERE invitation_uuid=? AND status='PENDING'")->execute([$accountId,$membershipId,$invitationUuid]);
            $this->event($invitationUuid,$inv['group_id'],$accountId,$inv['intended_role'],'ACCEPTED','PENDING','ACCEPTED');
            return $this->projection($this->invitation($invitationUuid,false));
        });
        if (isset($result['expired'])) throw new HealthcareOrganizationTeamException('invitation_expired');
        return $result;
    }

    public function revokeInvitation(?AuthenticatedAccessContext $actor,string $invitationUuid): array
    {
        $accountId=$this->actorId($actor);
        return $this->transaction(function () use ($actor,$accountId,$invitationUuid): array {
            $inv=$this->invitation($invitationUuid,true);
            $this->requireTeam($actor,$inv['group_id']);
            if ($inv['status']!=='PENDING') throw new HealthcareOrganizationTeamException('invitation_terminal');
            $this->pdo->prepare("UPDATE healthcare_organization_membership_invitations SET status='REVOKED',revoked_at=NOW(6)
                WHERE invitation_uuid=? AND status='PENDING'")->execute([$invitationUuid]);
            $this->event($invitationUuid,$inv['group_id'],$accountId,$inv['intended_role'],'REVOKED','PENDING','REVOKED');
            return $this->projection($this->invitation($invitationUuid,false));
        });
    }

    public function readOwnInvitation(?AuthenticatedAccessContext $actor,string $invitationUuid): array
    {
        $accountId=$this->actorId($actor);
        $this->locklessVerifiedAccount($accountId);
        $inv=$this->invitation($invitationUuid,false);
        if ($inv['invitee_account_id']!==$accountId) throw new HealthcareOrganizationTeamException('invitation_not_found');
        return $this->projection($inv);
    }

    public function readOwnMembership(?AuthenticatedAccessContext $actor,string $groupId): ?array
    {
        $accountId=$this->actorId($actor);
        $this->locklessVerifiedAccount($accountId);
        $member=$this->membershipForAccount($groupId,$accountId,false);
        return $member===null?null:['membership_id'=>$member['membership_id'],'group_id'=>$groupId,
            'role_code'=>$member['role_code'],'status'=>$member['status']];
    }

    /** Owner-only projections; private email and credential fields never leave this service. */
    public function listMembers(?AuthenticatedAccessContext $actor,string $groupId): array
    {
        $this->requireTeam($actor,$groupId);
        $stmt=$this->pdo->prepare("SELECT membership_id,account_id,role_code,status
            FROM auth_account_memberships WHERE entity_group_id=? AND profile_doctor_id IS NULL
            AND scope_code='organization' AND assignment_source IN
            ('provider_claim_approval','provider_invitation_acceptance')
            ORDER BY created_at,membership_id");
        $stmt->execute([$groupId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listPendingInvitations(?AuthenticatedAccessContext $actor,string $groupId): array
    {
        $this->requireTeam($actor,$groupId);
        $stmt=$this->pdo->prepare("SELECT invitation_uuid,group_id,invitee_account_id,intended_role,status,
            issued_at,expires_at FROM healthcare_organization_membership_invitations
            WHERE group_id=? AND status='PENDING' AND expires_at>NOW(6) ORDER BY issued_at,invitation_uuid");
        $stmt->execute([$groupId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listOwnPendingInvitations(?AuthenticatedAccessContext $actor): array
    {
        $accountId=$this->actorId($actor);
        $this->locklessVerifiedAccount($accountId);
        $stmt=$this->pdo->prepare("SELECT i.invitation_uuid,i.group_id,g.display_name AS organization_name,
            i.intended_role,i.status,i.issued_at,i.expires_at
            FROM healthcare_organization_membership_invitations i
            JOIN medical_groups g ON g.group_id=i.group_id
            WHERE i.invitee_account_id=? AND i.status='PENDING' AND i.expires_at>NOW(6)
            ORDER BY i.issued_at,i.invitation_uuid");
        $stmt->execute([$accountId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function suspendMember(?AuthenticatedAccessContext $actor,string $groupId,string $membershipId): array
    { return $this->changeMember($actor,$groupId,$membershipId,'SUSPENDED'); }

    public function revokeMember(?AuthenticatedAccessContext $actor,string $groupId,string $membershipId): array
    { return $this->changeMember($actor,$groupId,$membershipId,'REVOKED'); }

    private function changeMember(?AuthenticatedAccessContext $actor,string $groupId,string $membershipId,string $action): array
    {
        $accountId=$this->actorId($actor);
        return $this->transaction(function () use ($actor,$accountId,$groupId,$membershipId,$action): array {
            $this->lockOrganization($groupId);
            $this->requireTeam($actor,$groupId);
            $stmt=$this->pdo->prepare("SELECT * FROM auth_account_memberships WHERE membership_id=? AND entity_group_id=?
                AND scope_code='organization' AND profile_doctor_id IS NULL FOR UPDATE");
            $stmt->execute([$membershipId,$groupId]);
            $member=$stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($member) || !in_array($member['role_code'],['administrator','collaborator'],true))
                throw new HealthcareOrganizationTeamException('member_not_manageable');
            if ($member['status']!=='active' && !($action==='REVOKED' && $member['status']==='suspended'))
                throw new HealthcareOrganizationTeamException('member_state_conflict');
            $new=strtolower($action);
            $this->pdo->prepare("UPDATE auth_account_memberships SET status=?,revoked_at=IF(?='revoked',NOW(),revoked_at)
                WHERE membership_id=?")->execute([$new,$new,$membershipId]);
            $this->pdo->prepare('INSERT INTO healthcare_organization_member_action_events
                (group_id,membership_id,actor_account_id,action,previous_status,new_status) VALUES (?,?,?,?,?,?)')
                ->execute([$groupId,$membershipId,$accountId,$action,$member['status'],$new]);
            return ['membership_id'=>$membershipId,'group_id'=>$groupId,'role_code'=>$member['role_code'],'status'=>$new];
        });
    }

    private function requireTeam(?AuthenticatedAccessContext $actor,string $groupId): void
    {
        if (!$this->policy->evaluate($actor,$groupId,HealthcareOrganizationManagementAuthorizationService::TEAM)['allowed'])
            throw new HealthcareOrganizationTeamException('team_management_denied');
    }

    private function actorId(?AuthenticatedAccessContext $actor): string
    {
        if ($actor===null || $actor->principal()->accountStatus()!=='active' || $actor->session()->state()!=='active'
            || $actor->session()->expiresAt()<=new \DateTimeImmutable('now')
            || $actor->session()->principal()->accountId()!==$actor->accountId())
            throw new HealthcareOrganizationTeamException('authentication_required');
        return $actor->accountId();
    }

    private function lockVerifiedAccount(string $id): void
    {
        $stmt=$this->pdo->prepare('SELECT status,email_verified_at FROM auth_accounts WHERE account_id=? FOR UPDATE');
        $stmt->execute([$id]);
        $row=$stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row) || $row['status']!=='active' || $row['email_verified_at']===null)
            throw new HealthcareOrganizationTeamException('verified_account_required');
    }

    private function locklessVerifiedAccount(string $id): void
    {
        $stmt=$this->pdo->prepare("SELECT 1 FROM auth_accounts WHERE account_id=? AND status='active' AND email_verified_at IS NOT NULL");
        $stmt->execute([$id]);
        if (!$stmt->fetchColumn()) throw new HealthcareOrganizationTeamException('verified_account_required');
    }

    private function lockOrganization(string $id): void
    {
        $stmt=$this->pdo->prepare('SELECT group_id FROM medical_groups WHERE group_id=? FOR UPDATE');
        $stmt->execute([$id]);
        if (!$stmt->fetchColumn()) throw new HealthcareOrganizationTeamException('organization_not_found');
    }

    private function membershipForAccount(string $groupId,string $accountId,bool $lock): ?array
    {
        $stmt=$this->pdo->prepare("SELECT membership_id,role_code,status FROM auth_account_memberships
            WHERE entity_group_id=? AND account_id=? AND profile_doctor_id IS NULL AND scope_code='organization'
              AND status IN ('pending','active','suspended')".($lock?' FOR UPDATE':''));
        $stmt->execute([$groupId,$accountId]);
        $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows)>1) throw new HealthcareOrganizationTeamException('membership_conflict');
        return $rows[0]??null;
    }

    private function invitation(string $uuid,bool $lock): array
    {
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/iD',$uuid))
            throw new HealthcareOrganizationTeamException('invitation_not_found');
        $stmt=$this->pdo->prepare('SELECT * FROM healthcare_organization_membership_invitations WHERE invitation_uuid=?'.($lock?' FOR UPDATE':''));
        $stmt->execute([$uuid]);
        $row=$stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) throw new HealthcareOrganizationTeamException('invitation_not_found');
        return $row;
    }

    private function event(string $uuid,string $groupId,string $actor,string $role,string $action,?string $before,string $after): void
    {
        $this->pdo->prepare('INSERT INTO healthcare_organization_membership_invitation_events
            (invitation_uuid,group_id,actor_account_id,intended_role,action,previous_status,new_status)
            VALUES (?,?,?,?,?,?,?)')->execute([$uuid,$groupId,$actor,$role,$action,$before,$after]);
    }

    private function projection(array $row): array
    {
        return ['invitation_uuid'=>$row['invitation_uuid'],'group_id'=>$row['group_id'],
            'invitee_account_id'=>$row['invitee_account_id'],'intended_role'=>$row['intended_role'],
            'status'=>$row['status'],'issued_at'=>$row['issued_at'],'expires_at'=>$row['expires_at'],
            'accepted_at'=>$row['accepted_at'],'membership_id'=>$row['membership_id']];
    }

    private function transaction(callable $operation): array
    {
        if ($this->pdo->inTransaction()) throw new HealthcareOrganizationTeamException('nested_transaction_denied');
        $this->pdo->beginTransaction();
        try { $result=$operation(); $this->pdo->commit(); return $result; }
        catch (\Throwable $e) { if ($this->pdo->inTransaction()) $this->pdo->rollBack(); throw $e; }
    }

    private static function uuid(): string
    {
        $bytes=random_bytes(16);
        $bytes[6]=chr((ord($bytes[6])&0x0f)|0x40);
        $bytes[8]=chr((ord($bytes[8])&0x3f)|0x80);
        $hex=bin2hex($bytes);
        return substr($hex,0,8).'-'.substr($hex,8,4).'-'.substr($hex,12,4).'-'.substr($hex,16,4).'-'.substr($hex,20);
    }
}
