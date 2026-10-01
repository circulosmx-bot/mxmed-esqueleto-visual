<?php
declare(strict_types=1);

namespace Identity\Contracts;

interface OrganizationTypeLookupPort
{
    public function typeForGroup(string $groupId): ?string;
}
