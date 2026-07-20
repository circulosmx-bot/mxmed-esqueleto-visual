<?php
declare(strict_types=1);

namespace Identity\Contracts;

interface SessionAccountStatePort
{
    /** @return array{status:string,credential_version:int}|null */
    public function current(string $accountId): ?array;
}
