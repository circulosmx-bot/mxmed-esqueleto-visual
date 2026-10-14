<?php
declare(strict_types=1);

namespace Identity\Repositories;

use Identity\Contracts\AccountMembership;
use Identity\Contracts\ProfileMembershipRoleChangeRequest;
use PDO;
use PDOException;

final class AccountMembershipRepository
{
    public function __construct(private PDO $pdo) {}

    public function usesConnection(PDO $pdo): bool { return $this->pdo === $pdo; }

    public function create(AccountMembership $membership): void
    {
        $reference = $membership->reference();
        $profileId = $reference->isProfile() ? $reference->targetId() : null;
        $groupId = $reference->isOrganization() ? $reference->targetId() : null;
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO auth_account_memberships (
                    membership_id, account_id, profile_doctor_id, entity_group_id,
                    role_code, scope_code, status, assignment_source
                 ) VALUES (
                    :membership_id, :account_id, :profile_doctor_id, :entity_group_id,
                    :role_code, :scope_code, :status, :assignment_source
                 )'
            );
            $stmt->execute([
                ':membership_id' => $membership->membershipId(),
                ':account_id' => $membership->accountId(),
                ':profile_doctor_id' => $profileId,
                ':entity_group_id' => $groupId,
                ':role_code' => $membership->role(),
                ':scope_code' => $membership->scope(),
                ':status' => $membership->status(),
                ':assignment_source' => $membership->source(),
            ]);
        } catch (PDOException $e) {
            throw new \RuntimeException('identity_membership_create_failed', 0, $e);
        }
    }

    /** Canonical lifecycle transition used to remove authority without deleting history. */
    public function revoke(string $membershipId, string $accountId): bool
    {
        foreach ([$membershipId, $accountId] as $identifier) {
            if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{1,63}$/', trim($identifier)) !== 1) {
                throw new \InvalidArgumentException('invalid_membership_identifier');
            }
        }
        try {
            $stmt=$this->pdo->prepare("UPDATE auth_account_memberships SET status='revoked', updated_at=CURRENT_TIMESTAMP
                WHERE membership_id=:membership_id AND account_id=:account_id AND status='active'");
            $stmt->execute(['membership_id'=>$membershipId,'account_id'=>$accountId]);
            return $stmt->rowCount()===1;
        } catch (PDOException $e) {
            throw new \RuntimeException('identity_membership_revoke_failed',0,$e);
        }
    }

    /** @return list<array<string, mixed>> */
    public function activeForAccount(string $accountId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT membership_id, account_id, profile_doctor_id, entity_group_id, role_code, scope_code, status
             FROM auth_account_memberships WHERE account_id = :account_id AND status = 'active' ORDER BY membership_id"
        );
        $stmt->execute([':account_id' => $accountId]);
        return $stmt->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function lockProfileMembership(string $membershipId): ?array
    {
        $stmt=$this->pdo->prepare('SELECT membership_id,account_id,profile_doctor_id,entity_group_id,role_code,scope_code,status,assignment_source,updated_at
            FROM auth_account_memberships WHERE membership_id=:membership_id FOR UPDATE');
        $stmt->execute(['membership_id'=>$membershipId]);
        $row=$stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row)?$row:null;
    }

    /** @return list<array<string,mixed>> */
    public function lockActivePhysicianMembershipsForAccount(string $accountId): array
    {
        $stmt=$this->pdo->prepare("SELECT membership_id,profile_doctor_id,role_code,scope_code,status
            FROM auth_account_memberships WHERE account_id=:account_id AND profile_doctor_id IS NOT NULL
            AND status='active' ORDER BY membership_id FOR UPDATE");
        $stmt->execute(['account_id'=>$accountId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array<string,mixed>> */
    public function lockActiveOwnersForProfile(string $doctorId): array
    {
        $stmt=$this->pdo->prepare("SELECT membership_id,account_id FROM auth_account_memberships
            WHERE profile_doctor_id=:doctor_id AND entity_group_id IS NULL AND role_code='owner'
            AND status='active' ORDER BY membership_id FOR UPDATE");
        $stmt->execute(['doctor_id'=>$doctorId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function transitionProfileRole(ProfileMembershipRoleChangeRequest $request): bool
    {
        $stmt=$this->pdo->prepare('UPDATE auth_account_memberships SET role_code=:new_role,updated_at=CURRENT_TIMESTAMP
            WHERE membership_id=:membership_id AND account_id=:account_id AND profile_doctor_id=:doctor_id
            AND entity_group_id IS NULL AND role_code=:expected_role AND scope_code=:expected_scope
            AND status=:expected_status AND updated_at=:expected_updated_at');
        $stmt->execute([
            'new_role'=>$request->newRole,'membership_id'=>$request->membershipId,
            'account_id'=>$request->targetAccountId,'doctor_id'=>$request->profileDoctorId,
            'expected_role'=>$request->expectedRole,'expected_scope'=>$request->expectedScope,
            'expected_status'=>$request->expectedStatus,'expected_updated_at'=>$request->expectedUpdatedAt,
        ]);
        return $stmt->rowCount()===1;
    }
}
