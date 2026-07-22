<?php
declare(strict_types=1);

namespace Agenda\PublicFlow;

use Agenda\Appointments\AppointmentSlotIdentity;

final readonly class PublicBookingHandoff
{
    private const TYPES = ['create_pending_otp_appointment', 'confirm_verified_appointment', 'cancel_expired_appointment', 'cancel_locked_appointment', 'cancel_by_public_capability'];
    private const REASONS = ['public_otp_pending', 'public_otp_verified', 'public_otp_expired', 'public_otp_locked', 'public_capability_canceled'];
    private string $handoffId;
    private string $handoffType;
    private string $intentId;
    private ?string $appointmentId;
    private string $profileId;
    private string $consultorioId;
    private string $slotKey;
    private int $expectedLifecycleVersion;
    private ?string $fromState;
    private string $toState;
    private string $reasonCode;
    private ?string $verificationGrantDigest;
    private ?string $cancellationCapabilityDigest;
    private string $correlationId;
    private string $occurredAt;

    public function __construct(string $handoffType, string $intentId, ?string $appointmentId, string $profileId, string $consultorioId, string $slotKey, int $expectedLifecycleVersion, ?string $fromState, string $toState, string $reasonCode, ?string $verificationGrantDigest, ?string $cancellationCapabilityDigest, string $correlationId, string $occurredAt)
    {
        if (!in_array($handoffType, self::TYPES, true) || !in_array($reasonCode, self::REASONS, true)) throw new PublicAgendaDomainException('unauthorized_public_handoff');
        if ($expectedLifecycleVersion !== PublicAgendaPolicy::LIFECYCLE_VERSION || $toState !== 'canceled' && $handoffType !== 'create_pending_otp_appointment' && $toState !== 'confirmed') throw new PublicAgendaDomainException('unauthorized_public_handoff');
        if ($toState === 'pending_otp' && $handoffType !== 'create_pending_otp_appointment') throw new PublicAgendaDomainException('unauthorized_public_handoff');
        if ($toState === 'confirmed' && $handoffType !== 'confirm_verified_appointment') throw new PublicAgendaDomainException('unauthorized_public_handoff');
        if ($fromState !== null && $fromState !== 'pending_otp') throw new PublicAgendaDomainException('unauthorized_public_handoff');
        if ($handoffType === 'create_pending_otp_appointment' && ($toState !== 'pending_otp' || $reasonCode !== 'public_otp_pending')) throw new PublicAgendaDomainException('unauthorized_public_handoff');
        if ($handoffType === 'confirm_verified_appointment' && ($fromState !== 'pending_otp' || $toState !== 'confirmed' || $reasonCode !== 'public_otp_verified' || $verificationGrantDigest === null)) throw new PublicAgendaDomainException('unauthorized_public_handoff');
        if ($handoffType === 'cancel_expired_appointment' && ($fromState !== 'pending_otp' || $reasonCode !== 'public_otp_expired')) throw new PublicAgendaDomainException('unauthorized_public_handoff');
        if ($handoffType === 'cancel_locked_appointment' && ($fromState !== 'pending_otp' || $reasonCode !== 'public_otp_locked')) throw new PublicAgendaDomainException('unauthorized_public_handoff');
        if ($handoffType === 'cancel_by_public_capability' && ($reasonCode !== 'public_capability_canceled' || $cancellationCapabilityDigest === null)) throw new PublicAgendaDomainException('unauthorized_public_handoff');
        $this->handoffType = $handoffType;
        $this->intentId = PublicAgendaPolicy::identifier($intentId, 'unauthorized_public_handoff');
        $this->appointmentId = $appointmentId === null ? null : PublicAgendaPolicy::identifier($appointmentId, 'unauthorized_public_handoff');
        $this->profileId = PublicAgendaPolicy::identifier($profileId, 'unauthorized_public_handoff');
        $this->consultorioId = PublicAgendaPolicy::identifier($consultorioId, 'unauthorized_public_handoff');
        $this->slotKey = PublicAgendaPolicy::identifier($slotKey, 'unauthorized_public_handoff');
        $this->expectedLifecycleVersion = $expectedLifecycleVersion;
        $this->fromState = $fromState;
        $this->toState = $toState;
        $this->reasonCode = $reasonCode;
        $this->verificationGrantDigest = $verificationGrantDigest;
        $this->cancellationCapabilityDigest = $cancellationCapabilityDigest;
        $this->correlationId = PublicAgendaPolicy::identifier($correlationId, 'unauthorized_public_handoff');
        $this->occurredAt = PublicAgendaPolicy::timestamp($occurredAt, 'unauthorized_public_handoff')->format('Y-m-d\TH:i:s.uP');
        $this->handoffId = PublicAgendaPolicy::digest($this->toArrayWithoutId());
    }

    public static function create(PublicBookingIntent $intent, string $operationType, ?string $appointmentId, string|PublicVerificationGrant|null $grantDigest, string|PublicCancellationCapability|null $capabilityDigest, string $correlationId, string $occurredAt): self
    {
        if ($grantDigest instanceof PublicVerificationGrant) {
            if ($grantDigest->intentId() !== $intent->intentId() || $grantDigest->bindingFingerprint() !== $intent->bindingFingerprint()) throw new PublicAgendaDomainException('unauthorized_public_handoff');
            $grantDigest = $grantDigest->grantDigest();
        }
        if ($capabilityDigest instanceof PublicCancellationCapability) {
            if ($capabilityDigest->intentId() !== $intent->intentId() || $capabilityDigest->bindingFingerprint() !== $intent->bindingFingerprint()) throw new PublicAgendaDomainException('unauthorized_public_handoff');
            $capabilityDigest = $capabilityDigest->capabilityDigest();
        }
        foreach ([$grantDigest, $capabilityDigest] as $digest) if ($digest !== null && preg_match('/\A[0-9a-f]{64}\z/D', $digest) !== 1) throw new PublicAgendaDomainException('unauthorized_public_handoff');
        $map = ['pending' => ['create_pending_otp_appointment', null, 'pending_otp', 'public_otp_pending'], 'verified' => ['confirm_verified_appointment', 'pending_otp', 'confirmed', 'public_otp_verified'], 'expired' => ['cancel_expired_appointment', 'pending_otp', 'canceled', 'public_otp_expired'], 'locked' => ['cancel_locked_appointment', 'pending_otp', 'canceled', 'public_otp_locked'], 'capability' => ['cancel_by_public_capability', null, 'canceled', 'public_capability_canceled']];
        if (!isset($map[$operationType])) throw new PublicAgendaDomainException('unauthorized_public_handoff');
        [$type, $from, $to, $reason] = $map[$operationType];
        return new self($type, $intent->intentId(), $appointmentId, $intent->profileId(), $intent->consultorioId(), $intent->slot()->slotKey(), PublicAgendaPolicy::LIFECYCLE_VERSION, $from, $to, $reason, $grantDigest, $capabilityDigest, $correlationId, $occurredAt);
    }

    public function handoffId(): string { return $this->handoffId; }
    public function handoffType(): string { return $this->handoffType; }
    public function reasonCode(): string { return $this->reasonCode; }
    public function toState(): string { return $this->toState; }
    public function toArrayWithoutId(): array
    {
        return ['handoff_type' => $this->handoffType, 'intent_id' => $this->intentId, 'appointment_id' => $this->appointmentId, 'profile_id' => $this->profileId, 'consultorio_id' => $this->consultorioId, 'slot_key' => $this->slotKey, 'expected_lifecycle_version' => $this->expectedLifecycleVersion, 'from_state' => $this->fromState, 'to_state' => $this->toState, 'reason_code' => $this->reasonCode, 'verification_grant_digest' => $this->verificationGrantDigest, 'cancellation_capability_digest' => $this->cancellationCapabilityDigest, 'correlation_id' => $this->correlationId, 'occurred_at' => $this->occurredAt, 'server_authoritative_required' => true];
    }
    public function toArray(): array { return ['handoff_id' => $this->handoffId] + $this->toArrayWithoutId(); }
    public static function types(): array { return self::TYPES; }
    public static function reasons(): array { return self::REASONS; }
}
