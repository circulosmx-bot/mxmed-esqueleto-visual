<?php
declare(strict_types=1);

namespace Agenda\PublicFlow;

final readonly class PublicOtpVerificationCommand
{
    private string $operationId;
    private string $idempotencyKey;
    private string $correlationId;
    private string $challengeId;
    private string $intentId;
    private string $bindingFingerprint;
    private string $otp;
    private string $occurredAt;
    private ?PublicRateLimitDecision $rateLimitDecision;

    public function __construct(
        string $operationId,
        string $idempotencyKey,
        string $correlationId,
        string $challengeId,
        string $intentId,
        string $bindingFingerprint,
        string $otp,
        string $occurredAt,
        ?PublicRateLimitDecision $rateLimitDecision
    ) {
        $this->operationId = PublicAgendaPolicy::identifier($operationId, 'invalid_otp');
        $this->idempotencyKey = PublicAgendaPolicy::identifier($idempotencyKey, 'invalid_otp');
        $this->correlationId = PublicAgendaPolicy::identifier($correlationId, 'invalid_otp');
        $this->challengeId = PublicAgendaPolicy::identifier($challengeId, 'invalid_otp');
        $this->intentId = PublicAgendaPolicy::identifier($intentId, 'invalid_otp');
        if (preg_match('/\A[0-9a-f]{64}\z/D', $bindingFingerprint) !== 1) throw new PublicAgendaDomainException('invalid_binding_fingerprint');
        if (preg_match('/\A\d{6}\z/D', $otp) !== 1) throw new PublicAgendaDomainException('invalid_otp');
        $this->occurredAt = PublicAgendaPolicy::timestamp($occurredAt, 'invalid_otp')->format('Y-m-d\TH:i:s.uP');
        $this->bindingFingerprint = $bindingFingerprint;
        $this->otp = $otp;
        $this->rateLimitDecision = $rateLimitDecision;
    }

    public function operationId(): string { return $this->operationId; }
    public function idempotencyKey(): string { return $this->idempotencyKey; }
    public function correlationId(): string { return $this->correlationId; }
    public function challengeId(): string { return $this->challengeId; }
    public function intentId(): string { return $this->intentId; }
    public function bindingFingerprint(): string { return $this->bindingFingerprint; }
    public function otp(): string { return $this->otp; }
    public function occurredAt(): string { return $this->occurredAt; }
    public function rateLimitDecision(): ?PublicRateLimitDecision { return $this->rateLimitDecision; }
    public function actorSource(): string { return 'public_flow'; }
    public function toArray(): array
    {
        return [
            'operation_id' => $this->operationId,
            'idempotency_key' => $this->idempotencyKey,
            'correlation_id' => $this->correlationId,
            'challenge_id' => $this->challengeId,
            'intent_id' => $this->intentId,
            'binding_fingerprint' => $this->bindingFingerprint,
            'actor_source' => 'public_flow',
            'occurred_at' => $this->occurredAt,
            'rate_limit_decision_present' => $this->rateLimitDecision !== null,
        ];
    }
}
