<?php
declare(strict_types=1);

namespace Identity\Contracts;

interface SessionCapabilityAuthorityPort
{
    public function resolve(string $capabilityId, array $context): object;
}
