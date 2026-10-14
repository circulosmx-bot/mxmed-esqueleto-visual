<?php
declare(strict_types=1);

namespace Identity\Contracts;

use PDO;

/** Revalidates server-derived staff authority inside the ownership transaction. */
interface ProfileOwnershipApproverAuthorityPort
{
    public function assertAuthorized(
        PDO $transaction,
        string $approverAccountId,
        string $targetAccountId,
        string $profileDoctorId,
        int $credentialVersion,
    ): void;
}
