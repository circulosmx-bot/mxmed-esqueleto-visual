<?php
declare(strict_types=1);
namespace Identity\Contracts;

use PDO;

interface ProfileRoleChangeFinalizationPort
{
    public function finalize(PDO $transaction,ProfileMembershipRoleChangeRequest $request,ProfileMembershipRoleChangeAuditRecord $record):void;
}
