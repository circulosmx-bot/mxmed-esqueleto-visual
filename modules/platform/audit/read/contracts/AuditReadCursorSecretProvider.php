<?php
declare(strict_types=1);

namespace Platform\Audit\Read\Contracts;

interface AuditReadCursorSecretProvider
{
    /** @return array{version:string,secret:string} */
    public function currentAuditReadCursorKey(): array;
}
