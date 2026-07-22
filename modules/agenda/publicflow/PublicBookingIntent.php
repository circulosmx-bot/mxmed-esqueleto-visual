<?php
declare(strict_types=1);

namespace Agenda\PublicFlow;

use Agenda\Appointments\AppointmentSlotIdentity;

final readonly class PublicBookingIntent
{
    private string $intentId;
    private string $profileId;
    private string $consultorioId;
    private AppointmentSlotIdentity $slot;
    private PublicContactReference $contact;
    private string $createdAt;
    private string $expiresAt;
    private int $policyVersion;
    private string $bindingFingerprint;

    public function __construct(
        string $intentId,
        string $profileId,
        string $consultorioId,
        AppointmentSlotIdentity $slot,
        PublicContactReference $contact,
        string $createdAt,
        string $expiresAt,
        int $policyVersion = PublicAgendaPolicy::VERSION
    ) {
        $this->intentId = PublicAgendaPolicy::identifier($intentId, 'invalid_booking_intent');
        $this->profileId = PublicAgendaPolicy::identifier($profileId, 'invalid_booking_intent');
        $this->consultorioId = PublicAgendaPolicy::identifier($consultorioId, 'invalid_booking_intent');
        if ($slot->profileId() !== $this->profileId || $slot->consultorioId() !== $this->consultorioId) {
            throw new PublicAgendaDomainException('invalid_booking_intent');
        }
        if ($policyVersion !== PublicAgendaPolicy::VERSION) throw new PublicAgendaDomainException('invalid_policy_version');
        $created = PublicAgendaPolicy::timestamp($createdAt);
        $expires = PublicAgendaPolicy::timestamp($expiresAt);
        if ($created >= $expires) throw new PublicAgendaDomainException('invalid_booking_intent');
        $this->slot = $slot;
        $this->contact = $contact;
        $this->createdAt = $created->format('Y-m-d\TH:i:s.uP');
        $this->expiresAt = $expires->format('Y-m-d\TH:i:s.uP');
        $this->policyVersion = $policyVersion;
        $this->bindingFingerprint = PublicAgendaPolicy::digest([
            'intent_id' => $this->intentId,
            'profile_id' => $this->profileId,
            'consultorio_id' => $this->consultorioId,
            'slot_key' => $this->slot->slotKey(),
            'channel' => $this->contact->channel(),
            'contact_reference' => $this->contact->contactReference(),
            'policy_version' => $this->policyVersion,
        ]);
    }

    public function intentId(): string { return $this->intentId; }
    public function profileId(): string { return $this->profileId; }
    public function consultorioId(): string { return $this->consultorioId; }
    public function slot(): AppointmentSlotIdentity { return $this->slot; }
    public function contact(): PublicContactReference { return $this->contact; }
    public function createdAt(): string { return $this->createdAt; }
    public function expiresAt(): string { return $this->expiresAt; }
    public function policyVersion(): int { return $this->policyVersion; }
    public function bindingFingerprint(): string { return $this->bindingFingerprint; }
    public function toArray(): array
    {
        return [
            'intent_id' => $this->intentId,
            'profile_id' => $this->profileId,
            'consultorio_id' => $this->consultorioId,
            'slot' => $this->slot->toArray(),
            'contact' => $this->contact->toArray(),
            'created_at' => $this->createdAt,
            'expires_at' => $this->expiresAt,
            'policy_version' => $this->policyVersion,
            'binding_fingerprint' => $this->bindingFingerprint,
        ];
    }
}
