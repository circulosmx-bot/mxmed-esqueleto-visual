<?php
declare(strict_types=1);

namespace Agenda\Availability;

use Agenda\Contracts\ScheduleAvailabilityContract;

final class CanonicalAvailabilityResult
{
    public function __construct(
        private readonly ScheduleAvailabilityContract $contract,
        private readonly CanonicalScheduleVersion $schedule,
        private readonly string $calculationDate,
        private readonly array $windows,
        private readonly array $slots,
        private readonly array $appliedOverrideIds,
        private readonly ?string $appliedHoliday,
        private readonly int $collisionCount
    ) {}

    public function contract(): ScheduleAvailabilityContract { return $this->contract; }
    public function schedule(): CanonicalScheduleVersion { return $this->schedule; }
    public function profileId(): string { return $this->contract->profileId(); }
    public function consultorioId(): string { return $this->contract->consultorioId(); }
    public function timezone(): string { return $this->contract->timezone(); }
    public function windows(): array { return $this->windows; }
    public function slots(): array { return $this->slots; }
    public function appliedOverrideIds(): array { return $this->appliedOverrideIds; }
    public function appliedHoliday(): ?string { return $this->appliedHoliday; }
    public function collisionCount(): int { return $this->collisionCount; }
    public function isReadModel(): bool { return $this->contract->isReadModel(); }
    public function editableAuthority(): bool { return $this->contract->editableAuthority(); }
    public function toArray(): array
    {
        $value = $this->contract->toArray();
        $value['schedule_version'] = $this->schedule->version();
        $value['schedule_version_id'] = $this->schedule->versionId();
        $value['calculation_date'] = $this->calculationDate;
        $value['applied_override_ids'] = $this->appliedOverrideIds;
        $value['applied_holiday'] = $this->appliedHoliday;
        $value['collision_count'] = $this->collisionCount;
        $value['slot_count'] = count($this->slots);
        $value['slots'] = $this->slots;
        return $value;
    }
}
