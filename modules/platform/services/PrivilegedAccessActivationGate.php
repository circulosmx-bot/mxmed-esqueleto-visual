<?php
declare(strict_types=1);

namespace Platform\Services;

use Platform\Contracts\FeatureFlags;
use Platform\Contracts\PrivilegedAccessMode;
use Platform\Contracts\PrivilegedAccessReason;

/** Absolute Gate 6E hard-stop: policy evaluation can never activate runtime access. */
final class PrivilegedAccessActivationGate
{
    /** @param array<string,bool> $requestedFlags @return array<string,mixed> */
    public function evaluate(array $requestedFlags = []): array
    {
        foreach ($requestedFlags as $flag => $value) if (!array_key_exists($flag, FeatureFlags::defaults())) throw new \InvalidArgumentException('unknown_feature_flag');
        return ['feature_flags_default' => FeatureFlags::defaults(), 'requested_flags' => $requestedFlags, 'productive_approval_present' => false, 'runtime_activation_enabled' => false, 'may_activate' => false, 'reason_code' => PrivilegedAccessReason::RUNTIME_ACTIVATION_DISABLED];
    }

    public function mayActivate(string $mode, array $requestedFlags = []): bool
    {
        PrivilegedAccessMode::assertValid($mode);
        $this->evaluate($requestedFlags);
        return false;
    }
}
