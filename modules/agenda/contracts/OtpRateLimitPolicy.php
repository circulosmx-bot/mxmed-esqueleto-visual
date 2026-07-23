<?php
declare(strict_types=1);

namespace Agenda\Contracts;

final class OtpRateLimitPolicy
{
    private const PARAMETER_KEYS = ['dimensions', 'lock_seconds', 'max_attempts', 'window_seconds'];
    private const DIMENSIONS = ['challenge', 'ip_digest', 'contact_digest', 'profile'];

    public function approvedParametersPresent(array $parameters): bool
    {
        $keys = array_keys($parameters);
        sort($keys, SORT_STRING);
        if ($keys !== self::PARAMETER_KEYS) {
            return false;
        }
        foreach (['max_attempts', 'window_seconds', 'lock_seconds'] as $key) {
            if (!is_int($parameters[$key]) || $parameters[$key] <= 0) {
                return false;
            }
        }
        return is_array($parameters['dimensions'])
            && array_values($parameters['dimensions']) === self::DIMENSIONS;
    }

    public function evaluate(array $parameters, array $observation): OtpRateLimitDecision
    {
        if (!$this->approvedParametersPresent($parameters)
            || array_keys($observation) !== ['attempts']
            || !is_int($observation['attempts'])
            || $observation['attempts'] < 0) {
            return new OtpRateLimitDecision(false, 'rate_limit_policy_unconfigured', 503);
        }
        if ($observation['attempts'] >= $parameters['max_attempts']) {
            return new OtpRateLimitDecision(false, 'rate_limited', 429);
        }
        return new OtpRateLimitDecision(true, 'eligible', 200);
    }
}

final class OtpRateLimitDecision
{
    public function __construct(
        private readonly bool $allowed,
        private readonly string $reason,
        private readonly int $httpStatus
    ) {}

    public function allowed(): bool
    {
        return $this->allowed;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }

    public function toArray(): array
    {
        return [
            'allowed' => $this->allowed,
            'reason' => $this->reason,
            'http_status' => $this->httpStatus,
        ];
    }
}
