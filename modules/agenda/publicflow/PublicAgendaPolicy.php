<?php
declare(strict_types=1);

namespace Agenda\PublicFlow;

final readonly class PublicRateLimitDecision
{
    public function __construct(private bool $allowed, private string $decisionId = 'server-rate-limit-decision')
    {
        if ($decisionId === '' || preg_match('/[\x00-\x1F\x7F]/', $decisionId) === 1) {
            throw new PublicAgendaDomainException('rate_limit_decision_required');
        }
    }

    public function allowed(): bool { return $this->allowed; }
    public function decisionId(): string { return $this->decisionId; }
}

final readonly class PublicAgendaPolicy
{
    public const CONTRACT_ID = 'pg03-public-agenda-otp-privacy';
    public const VERSION = 1;
    public const LIFECYCLE_ID = 'pg03-appointment-lifecycle';
    public const LIFECYCLE_VERSION = 1;
    public const OTP_DIGITS = 6;
    public const OTP_TTL_SECONDS = 600;
    public const OTP_MAX_ATTEMPTS = 5;

    private const CHANNELS = ['sms', 'email'];
    private const OTP_STATES = ['pending', 'verified', 'expired', 'locked', 'consumed'];

    public function contractId(): string { return self::CONTRACT_ID; }
    public function version(): int { return self::VERSION; }
    public function lifecycleId(): string { return self::LIFECYCLE_ID; }
    public function lifecycleVersion(): int { return self::LIFECYCLE_VERSION; }
    public function channels(): array { return self::CHANNELS; }
    public function otpDigits(): int { return self::OTP_DIGITS; }
    public function otpTtlSeconds(): int { return self::OTP_TTL_SECONDS; }
    public function otpMaxAttempts(): int { return self::OTP_MAX_ATTEMPTS; }
    public function activeChallengePerIntent(): int { return 1; }
    public function rawOtpPersisted(): bool { return false; }
    public function rawOtpInResponses(): bool { return false; }
    public function rawOtpInLogs(): bool { return false; }
    public function rawOtpInEvents(): bool { return false; }
    public function canonicalDebugOtp(): bool { return false; }
    public function replayIdempotent(): bool { return true; }
    public function grantSingleUse(): bool { return true; }
    public function rateLimitDecisionRequired(): bool { return true; }
    public function missingRateLimitFailsClosed(): bool { return true; }
    public function states(): array { return self::OTP_STATES; }
    public function publicFlowAuthoritative(): bool { return false; }
    public function serverAuthoritativeHandoffRequired(): bool { return true; }
    public function clinicalEncounter(): bool { return false; }
    public function patientIdentityResolutionDeferred(): bool { return true; }
    public function toArray(): array
    {
        return [
            'contract_id' => self::CONTRACT_ID,
            'version' => self::VERSION,
            'lifecycle_dependency' => self::LIFECYCLE_ID,
            'lifecycle_version' => self::LIFECYCLE_VERSION,
            'channels' => self::CHANNELS,
            'otp_digits' => self::OTP_DIGITS,
            'otp_ttl_seconds' => self::OTP_TTL_SECONDS,
            'otp_max_attempts' => self::OTP_MAX_ATTEMPTS,
            'active_challenge_per_intent' => 1,
            'raw_otp_persisted' => false,
            'raw_otp_response' => false,
            'raw_otp_logs' => false,
            'raw_otp_events' => false,
            'canonical_debug_otp' => false,
            'replay_idempotent' => true,
            'grant_single_use' => true,
            'rate_limit_decision_required' => true,
            'missing_rate_limit_fail_closed' => true,
            'states' => self::OTP_STATES,
            'public_flow_authoritative' => false,
            'server_authoritative_handoff_required' => true,
            'clinical_encounter' => false,
            'patient_identity_resolution_deferred' => true,
        ];
    }

    public function rateLimitDecision(bool $allowed, string $decisionId = 'server-rate-limit-decision'): PublicRateLimitDecision
    {
        return new PublicRateLimitDecision($allowed, $decisionId);
    }

    public static function identifier(string $value, string $code): string
    {
        $normalized = trim($value);
        if ($normalized === '' || strlen($normalized) > 255 || preg_match('/[\x00-\x1F\x7F]/', $normalized) === 1) {
            throw new PublicAgendaDomainException($code);
        }
        return $normalized;
    }

    public static function timestamp(string $value, string $code = 'invalid_booking_intent'): \DateTimeImmutable
    {
        if (preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})\z/D', $value) !== 1) {
            throw new PublicAgendaDomainException($code);
        }
        try { return new \DateTimeImmutable($value); }
        catch (\Exception) { throw new PublicAgendaDomainException($code); }
    }

    public static function canonical(array $value): string
    {
        try { return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR); }
        catch (\Throwable) { throw new PublicAgendaDomainException('invalid_binding_fingerprint'); }
    }

    public static function digest(array $value): string { return hash('sha256', self::canonical($value)); }
    public static function isChannel(string $channel): bool { return in_array($channel, self::CHANNELS, true); }
    public static function isState(string $state): bool { return in_array($state, self::OTP_STATES, true); }
}
