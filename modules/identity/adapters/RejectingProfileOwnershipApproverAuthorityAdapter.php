<?php
declare(strict_types=1);

namespace Identity\Adapters;

use Identity\Contracts\ProfileOwnershipApproverAuthorityPort;
use PDO;

/** Runtime-safe default until a trusted internal governance composition exists. */
final class RejectingProfileOwnershipApproverAuthorityAdapter implements ProfileOwnershipApproverAuthorityPort
{
    public function assertAuthorized(PDO $transaction,string $approverAccountId,string $targetAccountId,string $profileDoctorId,int $credentialVersion):void
    {
        throw new \RuntimeException('profile_ownership_approver_authority_unavailable');
    }
}
