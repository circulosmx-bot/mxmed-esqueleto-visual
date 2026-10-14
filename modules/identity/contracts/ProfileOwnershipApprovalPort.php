<?php
declare(strict_types=1);

namespace Identity\Contracts;

use PDO;

interface ProfileOwnershipApprovalPort
{
    /** Must fail unless it shares the caller-owned transaction and atomically consumes the approval. */
    public function consume(PDO $transaction, ProfileMembershipRoleChangeRequest $request, string $executorAccountId): ProfileOwnershipApprovalEvidence;
}
