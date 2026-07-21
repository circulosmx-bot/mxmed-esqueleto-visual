<?php
declare(strict_types=1);

namespace Agenda\Contracts;

final class OtpDecision
{
    public function __construct(private bool $allowed, private string $reason, private int $httpStatus) {}
    public function allowed(): bool { return $this->allowed; }
    public function reason(): string { return $this->reason; }
    public function httpStatus(): int { return $this->httpStatus; }
}

final class PublicOtpPolicy
{
    public const HASH_ONLY = true;
    public function __construct(private int $expiresSeconds = 600, private int $maxAttempts = 5, private array $rateLimitDimensions = ['ip', 'contact', 'doctor'], private bool $antiEnumeration = true, private bool $qaIsolated = true)
    {
        if ($expiresSeconds < 1 || $maxAttempts < 1 || $rateLimitDimensions === []) throw new \InvalidArgumentException('OTP policy is incomplete');
        $this->rateLimitDimensions = array_values(array_unique(array_map('strval', $rateLimitDimensions)));
    }
    public function expiresSeconds(): int { return $this->expiresSeconds; }
    public function maxAttempts(): int { return $this->maxAttempts; }
    public function hashOnly(): bool { return self::HASH_ONLY; }
    public function antiEnumeration(): bool { return $this->antiEnumeration; }
    public function rateLimitDimensions(): array { return $this->rateLimitDimensions; }
    public function qaIsolated(): bool { return $this->qaIsolated; }
    public function verifyState(bool $expired, int $attempts, bool $consumed): OtpDecision
    {
        if ($consumed) return new OtpDecision(false, 'replay_denied', 409);
        if ($expired) return new OtpDecision(false, 'expired', 409);
        if ($attempts >= $this->maxAttempts) return new OtpDecision(false, 'attempt_limit', 429);
        return new OtpDecision(true, 'eligible', 200);
    }
    public function toArray(): array
    {
        return ['hash_only' => true, 'expires_seconds' => $this->expiresSeconds, 'max_attempts' => $this->maxAttempts, 'rate_limit_dimensions' => $this->rateLimitDimensions, 'anti_enumeration' => $this->antiEnumeration, 'qa_isolated' => $this->qaIsolated, 'payload_minimized' => true];
    }
}
