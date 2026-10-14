<?php
declare(strict_types=1);

namespace Identity\Contracts;

use PDO;

interface ProfileOwnershipStepUpPort
{
    /** Must fail unless it shares the caller-owned transaction and atomically consumes fresh proof. */
    public function consume(PDO $transaction, ProfileMembershipRoleChangeRequest $request, AuthenticatedAccessContext $actor): ProfileOwnershipStepUpEvidence;
}
