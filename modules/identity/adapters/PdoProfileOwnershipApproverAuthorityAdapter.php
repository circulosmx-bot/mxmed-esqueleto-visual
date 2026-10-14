<?php
declare(strict_types=1);

namespace Identity\Adapters;

use Identity\Contracts\ProfileOwnershipApproverAuthorityPort;
use Identity\Services\InternalCapabilityCatalog;
use PDO;

/** Exact, uncached consumption-time authority check joined to the role-change transaction. */
final class PdoProfileOwnershipApproverAuthorityAdapter implements ProfileOwnershipApproverAuthorityPort
{
    public function __construct(private PDO $pdo) {}

    public function assertAuthorized(PDO $transaction,string $approverAccountId,string $targetAccountId,string $profileDoctorId,int $credentialVersion):void
    {
        if($transaction!==$this->pdo||!$this->pdo->inTransaction())
            throw new \RuntimeException('profile_ownership_approver_authority_transaction_required');
        if($approverAccountId===''||$approverAccountId===$targetAccountId||$profileDoctorId===''||$credentialVersion<1)
            throw new \RuntimeException('profile_ownership_approver_authority_denied');

        $stmt=$this->pdo->prepare("SELECT s.governance_class
            FROM auth_accounts a
            JOIN auth_account_credentials c ON c.account_id=a.account_id AND c.credential_version=:credential_version
            JOIN internal_staff s ON s.account_id=a.account_id AND s.status='ACTIVE'
            JOIN internal_operator_grants g ON g.account_id=a.account_id
              AND g.capability=:capability AND g.status='ACTIVE' AND g.revoked_at IS NULL
            WHERE a.account_id=:account_id AND a.status='active' AND a.email_verified_at IS NOT NULL
              AND s.governance_class IN ('DIRECTOR','MASTER_ADMIN')
            FOR UPDATE");
        $stmt->execute(['capability'=>InternalCapabilityCatalog::APPROVE_INITIAL_PROFILE_OWNERSHIP,
            'account_id'=>$approverAccountId,'credential_version'=>$credentialVersion]);
        if($stmt->fetchColumn()===false)throw new \RuntimeException('profile_ownership_approver_authority_denied');
    }
}
