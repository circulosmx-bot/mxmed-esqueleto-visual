<?php
declare(strict_types=1);

namespace Platform\Audit\Db\Contracts;

use Platform\Audit\Db\AuditDatabaseCredential;
use Platform\Audit\Db\AuditDatabaseCredentialRole;

interface AuditDatabaseCredentialProvider
{
    public function credentialFor(AuditDatabaseCredentialRole $role): AuditDatabaseCredential;
}
