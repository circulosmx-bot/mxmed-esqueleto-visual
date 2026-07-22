<?php
declare(strict_types=1);

namespace Agenda\Appointments;

final readonly class AppointmentSlotIdentity
{
    private string $profileId;
    private string $consultorioId;
    private string $timezone;
    private string $startAt;
    private string $endAt;
    private string $startInstant;
    private string $endInstant;

    public function __construct(string $profileId, string $consultorioId, string $timezone, string $startAt, string $endAt)
    {
        $this->profileId = AppointmentValue::scopedIdentity($profileId, 'invalid_slot');
        $this->consultorioId = AppointmentValue::scopedIdentity($consultorioId, 'invalid_slot');
        $zone = AppointmentValue::timezone($timezone);
        $start = AppointmentValue::timestamp($startAt, 'invalid_slot');
        $end = AppointmentValue::timestamp($endAt, 'invalid_slot');
        if ($start->format('P') !== $start->setTimezone($zone)->format('P')
            || $end->format('P') !== $end->setTimezone($zone)->format('P')) {
            throw new AppointmentDomainException('invalid_slot', 'timestamp offset must match the canonical timezone');
        }
        if ($start >= $end) throw new AppointmentDomainException('invalid_slot', 'slot must be ordered');
        $this->timezone = $timezone;
        $this->startAt = $start->setTimezone($zone)->format('Y-m-d\TH:i:s.uP');
        $this->endAt = $end->setTimezone($zone)->format('Y-m-d\TH:i:s.uP');
        $utc = new \DateTimeZone('UTC');
        $this->startInstant = $start->setTimezone($utc)->format('Y-m-d\TH:i:s.u\Z');
        $this->endInstant = $end->setTimezone($utc)->format('Y-m-d\TH:i:s.u\Z');
    }

    public function profileId(): string { return $this->profileId; }
    public function consultorioId(): string { return $this->consultorioId; }
    public function timezone(): string { return $this->timezone; }
    public function startAt(): string { return $this->startAt; }
    public function endAt(): string { return $this->endAt; }
    public function startInstant(): string { return $this->startInstant; }
    public function endInstant(): string { return $this->endInstant; }
    public function intervalModel(): string { return 'half_open'; }

    public function overlaps(self $other): bool
    {
        if ($this->profileId !== $other->profileId || $this->consultorioId !== $other->consultorioId) return false;
        return $this->startInstant < $other->endInstant && $other->startInstant < $this->endInstant;
    }

    public function slotKey(): string
    {
        return 'slot:' . AppointmentValue::digest($this->keyFields());
    }

    public function lockScope(): string
    {
        return 'slot-lock:' . AppointmentValue::digest(['profile_id' => $this->profileId, 'consultorio_id' => $this->consultorioId]);
    }

    public function uniqueClaimKey(): string
    {
        return 'slot-claim:' . AppointmentValue::digest([
            'profile_id' => $this->profileId,
            'consultorio_id' => $this->consultorioId,
            'start_instant' => $this->startInstant,
            'end_instant' => $this->endInstant,
        ]);
    }

    public function keyFields(): array
    {
        return [
            'profile_id' => $this->profileId,
            'consultorio_id' => $this->consultorioId,
            'timezone' => $this->timezone,
            'start_instant' => $this->startInstant,
            'end_instant' => $this->endInstant,
        ];
    }

    public function toArray(): array
    {
        return $this->keyFields() + [
            'start_at' => $this->startAt,
            'end_at' => $this->endAt,
            'interval_model' => 'half_open',
            'slot_key' => $this->slotKey(),
            'lock_scope' => $this->lockScope(),
            'unique_claim_key' => $this->uniqueClaimKey(),
        ];
    }
}
