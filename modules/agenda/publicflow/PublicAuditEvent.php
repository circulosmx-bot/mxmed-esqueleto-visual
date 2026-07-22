<?php
declare(strict_types=1);

namespace Agenda\PublicFlow;

final readonly class PublicAuditEvent
{
    private const TYPES = [
        'public_otp_challenge_issued',
        'public_otp_attempt_rejected',
        'public_otp_verified',
        'public_otp_locked',
        'public_otp_expired',
        'public_otp_consumed',
        'public_booking_handoff_requested',
        'public_booking_cancellation_requested',
    ];

    private string $eventId;
    private string $eventType;
    private string $intentIdDigest;
    private string $challengeIdDigest;
    private string $operationId;
    private string $correlationId;
    private string $outcomeCode;
    private string $channel;
    private int $policyVersion;
    private string $occurredAt;
    private int $attemptsUsed;
    private bool $terminal;

    public function __construct(string $eventType, string $intentId, string $challengeId, string $operationId, string $correlationId, string $outcomeCode, string $channel, int $policyVersion, string $occurredAt, int $attemptsUsed, bool $terminal)
    {
        if (!in_array($eventType, self::TYPES, true)) throw new PublicAgendaDomainException('invalid_challenge_state');
        if (!PublicAgendaPolicy::isChannel($channel)) throw new PublicAgendaDomainException('invalid_channel');
        if ($policyVersion !== PublicAgendaPolicy::VERSION || $attemptsUsed < 0 || $attemptsUsed > PublicAgendaPolicy::OTP_MAX_ATTEMPTS) throw new PublicAgendaDomainException('invalid_challenge_state');
        $intent = PublicAgendaPolicy::identifier($intentId, 'invalid_challenge_state');
        $challenge = PublicAgendaPolicy::identifier($challengeId, 'invalid_challenge_state');
        $operation = PublicAgendaPolicy::identifier($operationId, 'invalid_challenge_state');
        $correlation = PublicAgendaPolicy::identifier($correlationId, 'invalid_challenge_state');
        $outcome = PublicAgendaPolicy::identifier($outcomeCode, 'invalid_challenge_state');
        $when = PublicAgendaPolicy::timestamp($occurredAt, 'invalid_challenge_state');
        $this->eventType = $eventType;
        $this->intentIdDigest = PublicAgendaPolicy::digest(['intent_id' => $intent]);
        $this->challengeIdDigest = PublicAgendaPolicy::digest(['challenge_id' => $challenge]);
        $this->operationId = $operation;
        $this->correlationId = $correlation;
        $this->outcomeCode = $outcome;
        $this->channel = $channel;
        $this->policyVersion = $policyVersion;
        $this->occurredAt = $when->format('Y-m-d\TH:i:s.uP');
        $this->attemptsUsed = $attemptsUsed;
        $this->terminal = $terminal;
        $this->eventId = PublicAgendaPolicy::digest($this->toArrayWithoutId());
    }

    public function eventId(): string { return $this->eventId; }
    public function eventType(): string { return $this->eventType; }
    public function outcomeCode(): string { return $this->outcomeCode; }
    public function terminal(): bool { return $this->terminal; }
    public function toArrayWithoutId(): array
    {
        return ['event_type' => $this->eventType, 'intent_id_digest' => $this->intentIdDigest, 'challenge_id_digest' => $this->challengeIdDigest, 'operation_id' => $this->operationId, 'correlation_id' => $this->correlationId, 'outcome_code' => $this->outcomeCode, 'channel' => $this->channel, 'policy_version' => $this->policyVersion, 'occurred_at' => $this->occurredAt, 'attempts_used' => $this->attemptsUsed, 'terminal' => $this->terminal];
    }
    public function toArray(): array { return ['event_id' => $this->eventId] + $this->toArrayWithoutId(); }
    public static function types(): array { return self::TYPES; }
}
