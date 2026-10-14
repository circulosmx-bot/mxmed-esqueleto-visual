<?php
declare(strict_types=1);

namespace Identity\Adapters;

use Identity\Contracts\ProfileMembershipRoleChangeRequest;
use Identity\Contracts\ProfileOwnershipApprovalEvidence;
use Identity\Contracts\ProfileOwnershipApprovalPort;
use PDO;

/** Safe default until an independently governed approval trust anchor is commissioned. */
final class RejectingProfileOwnershipApprovalAdapter implements ProfileOwnershipApprovalPort
{
    public function consume(PDO $transaction, ProfileMembershipRoleChangeRequest $request, string $executorAccountId): ProfileOwnershipApprovalEvidence
    {
        throw new \RuntimeException('profile_ownership_independent_approval_unavailable');
    }
}
