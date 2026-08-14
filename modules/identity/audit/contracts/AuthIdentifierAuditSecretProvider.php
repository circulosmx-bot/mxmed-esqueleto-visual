<?php
declare(strict_types=1);

namespace Identity\Audit\Contracts;

interface AuthIdentifierAuditSecretProvider
{
    /** @return array{namespace:string,version:string,secret:string} */
    public function currentAuthIdentifierAuditKey(): array;
}
