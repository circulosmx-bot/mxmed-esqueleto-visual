<?php
declare(strict_types=1);

namespace Identity\Adapters;

use Identity\Contracts\AuthenticatedAccessContext;
use Identity\Contracts\ProfileMembershipRoleChangeRequest;
use Identity\Contracts\ProfileOwnershipStepUpEvidence;
use Identity\Contracts\ProfileOwnershipStepUpPort;
use PDO;

/** Safe default until operation-bound fresh-authentication evidence is available. */
final class RejectingProfileOwnershipStepUpAdapter implements ProfileOwnershipStepUpPort
{
    public function consume(PDO $transaction, ProfileMembershipRoleChangeRequest $request, AuthenticatedAccessContext $actor): ProfileOwnershipStepUpEvidence
    {
        throw new \RuntimeException('profile_ownership_step_up_unavailable');
    }
}
