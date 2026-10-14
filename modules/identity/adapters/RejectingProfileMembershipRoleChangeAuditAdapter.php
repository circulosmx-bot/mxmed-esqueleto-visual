<?php
declare(strict_types=1);

namespace Identity\Adapters;

use Identity\Contracts\ProfileMembershipRoleChangeAuditPort;
use Identity\Contracts\ProfileMembershipRoleChangeAuditRecord;
use PDO;

/** Safe default until the canonical audit adapter can preserve all governed evidence. */
final class RejectingProfileMembershipRoleChangeAuditAdapter implements ProfileMembershipRoleChangeAuditPort
{
    public function append(PDO $transaction, ProfileMembershipRoleChangeAuditRecord $record): void
    {
        throw new \RuntimeException('profile_role_change_audit_adapter_unavailable');
    }
}
