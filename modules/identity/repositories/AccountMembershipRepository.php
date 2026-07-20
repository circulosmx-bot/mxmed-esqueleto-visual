<?php
declare(strict_types=1);

namespace Identity\Repositories;

use Identity\Contracts\AccountMembership;
use PDO;
use PDOException;

final class AccountMembershipRepository
{
    public function __construct(private PDO $pdo) {}

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
}
