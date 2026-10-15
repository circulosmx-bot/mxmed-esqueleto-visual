<?php
declare(strict_types=1);
namespace Identity\Contracts;

use PDO;

/** Called inside the governed owner transaction, while the profile row is locked. */
interface ProfileOperatorOwnershipBoundary
{
    public function ownershipChanged(PDO $transaction, string $profileId, AuthenticatedAccessContext $actor, string $correlation): void;
}
