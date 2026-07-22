<?php
declare(strict_types=1);

namespace Agenda\PublicFlow;

final readonly class PublicOtpVerificationDecision
{
    public const VERIFIED = 'verified';
    public const REPLAY = 'replay';
    public const INVALID_CODE = 'invalid_code';
    public const LOCKED = 'locked';
    public const EXPIRED = 'expired';
    public const RATE_LIMITED = 'rate_limited';
    public const BINDING_MISMATCH = 'binding_mismatch';
    public const CHALLENGE_MISMATCH = 'challenge_mismatch';
    public const INTENT_MISMATCH = 'intent_mismatch';
    public const ALREADY_CONSUMED = 'already_consumed';
    public const INVALID_STATE = 'invalid_state';
    public const POLICY_VERSION_MISMATCH = 'policy_version_mismatch';
    public const RATE_LIMIT_DECISION_REQUIRED = 'rate_limit_decision_required';
    public const IDEMPOTENCY_CONFLICT = 'idempotency_conflict';

    private function __construct(
        private string $status,
        private int $httpStatus,
        private int $attemptsUsed,
        private PublicOtpChallenge $challenge,
        private ?PublicVerificationGrant $grant,
        private ?PublicAuditEvent $event,
        private ?PublicBookingHandoff $handoff,
        private string $idempotencyKey,
        private string $bindingFingerprint
    ) {}

    public static function create(string $status, int $httpStatus, int $attempts, PublicOtpChallenge $challenge, ?PublicVerificationGrant $grant, ?PublicAuditEvent $event, ?PublicBookingHandoff $handoff, string $idempotencyKey, string $bindingFingerprint): self
    {
        return new self($status, $httpStatus, $attempts, $challenge, $grant, $event, $handoff, $idempotencyKey, $bindingFingerprint);
    }

    public function status(): string { return $this->status; }
    public function reason(): string { return $this->status; }
    public function httpStatus(): int { return $this->httpStatus; }
    public function attemptsUsed(): int { return $this->attemptsUsed; }
    public function challenge(): PublicOtpChallenge { return $this->challenge; }
    public function grant(): ?PublicVerificationGrant { return $this->grant; }
    public function event(): ?PublicAuditEvent { return $this->event; }
    public function handoff(): ?PublicBookingHandoff { return $this->handoff; }
    public function idempotencyKey(): string { return $this->idempotencyKey; }
    public function bindingFingerprint(): string { return $this->bindingFingerprint; }
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'http_status' => $this->httpStatus,
            'attempts_used' => $this->attemptsUsed,
            'challenge_state' => $this->challenge->state(),
            'grant_present' => $this->grant !== null,
            'event_present' => $this->event !== null,
            'handoff_present' => $this->handoff !== null,
        ];
    }
}
