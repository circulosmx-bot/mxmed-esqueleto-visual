<?php
declare(strict_types=1);

namespace Identity\Adapters;

use Identity\Contracts\SessionCapabilityAuthorityPort;
use Subscriptions\Services\ExistingCapabilityAuthorityService;

final class ExistingCapabilityAuthorityAdapter implements SessionCapabilityAuthorityPort
{
    public function __construct(private ExistingCapabilityAuthorityService $authority) {}

    public function resolve(string $capabilityId, array $context): object
    {
        return $this->authority->resolve($capabilityId, $context);
    }
}
