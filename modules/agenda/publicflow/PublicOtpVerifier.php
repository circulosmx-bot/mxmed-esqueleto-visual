<?php
declare(strict_types=1);

namespace Agenda\PublicFlow;

final readonly class PublicOtpVerifier
{
    public function verify(PublicOtpVerificationCommand $command, PublicOtpChallenge $challenge, PublicBookingIntent $intent, ?PublicOtpVerificationDecision $prior = null): PublicOtpVerificationDecision
    {
        if ($intent->policyVersion() !== PublicAgendaPolicy::VERSION || $challenge->policyVersion() !== PublicAgendaPolicy::VERSION) {
            return $this->decision(PublicOtpVerificationDecision::POLICY_VERSION_MISMATCH, 409, $challenge, null, null, null, $command, $intent);
        }
        $rate = $command->rateLimitDecision();
        if ($rate === null) return $this->decision(PublicOtpVerificationDecision::RATE_LIMIT_DECISION_REQUIRED, 409, $challenge, null, null, null, $command, $intent);
        if (!$rate->allowed()) return $this->decision(PublicOtpVerificationDecision::RATE_LIMITED, 429, $challenge, null, null, null, $command, $intent);
        if ($command->challengeId() !== $challenge->challengeId()) return $this->decision(PublicOtpVerificationDecision::CHALLENGE_MISMATCH, 409, $challenge, null, null, null, $command, $intent);
        if ($command->intentId() !== $intent->intentId() || $challenge->intentId() !== $intent->intentId()) return $this->decision(PublicOtpVerificationDecision::INTENT_MISMATCH, 409, $challenge, null, null, null, $command, $intent);
        if ($prior !== null && $prior->idempotencyKey() === $command->idempotencyKey() && $prior->bindingFingerprint() !== $command->bindingFingerprint()) {
            return $this->decision(PublicOtpVerificationDecision::IDEMPOTENCY_CONFLICT, 409, $challenge, null, null, null, $command, $intent);
        }
        if ($command->bindingFingerprint() !== $intent->bindingFingerprint() || $challenge->bindingFingerprint() !== $intent->bindingFingerprint()) return $this->decision(PublicOtpVerificationDecision::BINDING_MISMATCH, 409, $challenge, null, null, null, $command, $intent);

        if ($prior !== null && $prior->idempotencyKey() === $command->idempotencyKey()) {
            if ($prior->bindingFingerprint() !== $command->bindingFingerprint() || $prior->challenge()->challengeId() !== $command->challengeId() || $prior->challenge()->intentId() !== $command->intentId()) {
                return $this->decision(PublicOtpVerificationDecision::IDEMPOTENCY_CONFLICT, 409, $challenge, null, null, null, $command, $intent);
            }
            if (in_array($prior->status(), [PublicOtpVerificationDecision::VERIFIED, PublicOtpVerificationDecision::REPLAY], true)) {
                return PublicOtpVerificationDecision::create(PublicOtpVerificationDecision::REPLAY, 200, $prior->attemptsUsed(), $prior->challenge(), $prior->grant(), null, null, $command->idempotencyKey(), $command->bindingFingerprint());
            }
        }

        if ($challenge->state() === 'consumed') return $this->decision(PublicOtpVerificationDecision::ALREADY_CONSUMED, 409, $challenge, null, null, null, $command, $intent);
        if ($challenge->state() === 'locked') return $this->decision(PublicOtpVerificationDecision::LOCKED, 429, $challenge, null, null, null, $command, $intent);
        if ($challenge->state() === 'expired') return $this->decision(PublicOtpVerificationDecision::EXPIRED, 409, $challenge, null, null, null, $command, $intent);
        if ($challenge->state() !== 'pending') return $this->decision(PublicOtpVerificationDecision::INVALID_STATE, 409, $challenge, null, null, null, $command, $intent);

        $occurred = PublicAgendaPolicy::timestamp($command->occurredAt(), 'invalid_otp');
        $expires = PublicAgendaPolicy::timestamp($challenge->expiresAt(), 'challenge_expired');
        if ($occurred >= $expires) {
            $next = $challenge->withAttempt($challenge->attemptsUsed(), 'expired');
            $event = $this->event('public_otp_expired', 'expired', true, $command, $intent, $next->attemptsUsed());
            $handoff = PublicBookingHandoff::create($intent, 'expired', null, null, null, $command->correlationId(), $command->occurredAt());
            return $this->decision(PublicOtpVerificationDecision::EXPIRED, 409, $next, null, $event, $handoff, $command, $intent);
        }
        if ($challenge->attemptsUsed() >= $challenge->maxAttempts()) {
            $next = $challenge->withAttempt($challenge->maxAttempts(), 'locked');
            $event = $this->event('public_otp_locked', 'locked', true, $command, $intent, $next->attemptsUsed());
            $handoff = PublicBookingHandoff::create($intent, 'locked', null, null, null, $command->correlationId(), $command->occurredAt());
            return $this->decision(PublicOtpVerificationDecision::LOCKED, 429, $next, null, $event, $handoff, $command, $intent);
        }

        if (!password_verify($command->otp(), $challenge->credentialHash())) {
            $attempts = $challenge->attemptsUsed() + 1;
            $locked = $attempts >= $challenge->maxAttempts();
            $next = $challenge->withAttempt($attempts, $locked ? 'locked' : 'pending');
            $event = $this->event($locked ? 'public_otp_locked' : 'public_otp_attempt_rejected', $locked ? 'locked' : 'invalid_code', $locked, $command, $intent, $attempts);
            $handoff = $locked ? PublicBookingHandoff::create($intent, 'locked', null, null, null, $command->correlationId(), $command->occurredAt()) : null;
            return $this->decision($locked ? PublicOtpVerificationDecision::LOCKED : PublicOtpVerificationDecision::INVALID_CODE, $locked ? 429 : 422, $next, null, $event, $handoff, $command, $intent);
        }

        $grant = PublicVerificationGrant::issue($challenge, $intent, $command->occurredAt());
        $next = $challenge->verified($command->occurredAt(), $grant->grantDigest());
        $event = $this->event('public_otp_verified', 'verified', false, $command, $intent, $next->attemptsUsed());
        $handoff = PublicBookingHandoff::create($intent, 'verified', null, $grant->grantDigest(), null, $command->correlationId(), $command->occurredAt());
        return $this->decision(PublicOtpVerificationDecision::VERIFIED, 200, $next, $grant, $event, $handoff, $command, $intent);
    }

    private function decision(string $status, int $httpStatus, PublicOtpChallenge $challenge, ?PublicVerificationGrant $grant, ?PublicAuditEvent $event, ?PublicBookingHandoff $handoff, PublicOtpVerificationCommand $command, PublicBookingIntent $intent): PublicOtpVerificationDecision
    {
        return PublicOtpVerificationDecision::create($status, $httpStatus, $challenge->attemptsUsed(), $challenge, $grant, $event, $handoff, $command->idempotencyKey(), $intent->bindingFingerprint());
    }

    private function event(string $type, string $outcome, bool $terminal, PublicOtpVerificationCommand $command, PublicBookingIntent $intent, int $attempts): PublicAuditEvent
    {
        return new PublicAuditEvent($type, $intent->intentId(), $command->challengeId(), $command->operationId(), $command->correlationId(), $outcome, $intent->contact()->channel(), PublicAgendaPolicy::VERSION, $command->occurredAt(), $attempts, $terminal);
    }
}
