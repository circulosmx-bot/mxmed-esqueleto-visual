<?php
declare(strict_types=1);

namespace Identity\Contracts;

use PDO;

interface ProfileMembershipRoleChangeAuditPort
{
    /** Appends ROLE_REVOKED, ROLE_ASSIGNED and PROFILE_OWNERSHIP_ASSIGNED in the caller transaction. */
    public function append(PDO $transaction, ProfileMembershipRoleChangeAuditRecord $record): void;
}
