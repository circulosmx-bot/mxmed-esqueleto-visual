<?php
declare(strict_types=1);

namespace Agenda\Availability;

final class AvailabilityCalculationRequest
{
    private readonly array $versions;
    private readonly array $overrides;
    private readonly array $holidays;
    private readonly array $collisions;

    public function __construct(
        private readonly string $profileId,
        private readonly string $consultorioId,
        private readonly string $targetDate,
        array $versions,
        array $overrides = [],
        array $holidays = [],
        array $collisions = []
    ) {
        if (trim($profileId) === '') throw new CanonicalAvailabilityException('profile_mismatch', 'profile is required');
        if (trim($consultorioId) === '') throw new CanonicalAvailabilityException('consultorio_mismatch', 'consultorio is required');
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $targetDate, new \DateTimeZone('UTC'));
        if (!$parsed || $parsed->format('Y-m-d') !== $targetDate) throw new CanonicalAvailabilityException('invalid_effective_range', 'target date is invalid');
        foreach ($versions as $value) if (!$value instanceof CanonicalScheduleVersion) throw new CanonicalAvailabilityException('canonical_schedule_missing', 'versions are required');
        foreach ($overrides as $value) if (!$value instanceof AvailabilityOverride) throw new CanonicalAvailabilityException('invalid_override', 'overrides are typed');
        foreach ($holidays as $value) if (!$value instanceof HolidayClosure) throw new CanonicalAvailabilityException('invalid_override', 'holidays are typed');
        foreach ($collisions as $value) if (!$value instanceof CollisionWindow) throw new CanonicalAvailabilityException('invalid_collision', 'collisions are typed');
        usort($versions, static fn(CanonicalScheduleVersion $a, CanonicalScheduleVersion $b): int => [
            $a->profileId(), $a->consultorioId(), $a->effectiveFrom(), $a->effectiveUntil() ?? '', $a->version(), $a->versionId()
        ] <=> [
            $b->profileId(), $b->consultorioId(), $b->effectiveFrom(), $b->effectiveUntil() ?? '', $b->version(), $b->versionId()
        ]);
        usort($overrides, static fn(AvailabilityOverride $a, AvailabilityOverride $b): int => [
            $a->profileId(), $a->consultorioId(), $a->date(), $a->type() === 'close' ? 0 : 1,
            $a->fullDay() ? 1 : 0, $a->window()?->start() ?? '', $a->window()?->end() ?? '', $a->id(), $a->source(), $a->active() ? 1 : 0
        ] <=> [
            $b->profileId(), $b->consultorioId(), $b->date(), $b->type() === 'close' ? 0 : 1,
            $b->fullDay() ? 1 : 0, $b->window()?->start() ?? '', $b->window()?->end() ?? '', $b->id(), $b->source(), $b->active() ? 1 : 0
        ]);
        usort($holidays, static fn(HolidayClosure $a, HolidayClosure $b): int => [$a->date(), $a->name(), $a->active() ? 1 : 0] <=> [$b->date(), $b->name(), $b->active() ? 1 : 0]);
        usort($collisions, static fn(CollisionWindow $a, CollisionWindow $b): int => [
            $a->profileId(), $a->consultorioId(), $a->date(), $a->start(), $a->end(), $a->id(), $a->source(), $a->active() ? 1 : 0
        ] <=> [
            $b->profileId(), $b->consultorioId(), $b->date(), $b->start(), $b->end(), $b->id(), $b->source(), $b->active() ? 1 : 0
        ]);
        $this->versions = array_values($versions);
        $this->overrides = array_values($overrides);
        $this->holidays = array_values($holidays);
        $this->collisions = array_values($collisions);
    }

    public function profileId(): string { return $this->profileId; }
    public function consultorioId(): string { return $this->consultorioId; }
    public function targetDate(): string { return $this->targetDate; }
    public function versions(): array { return $this->versions; }
    public function overrides(): array { return $this->overrides; }
    public function holidays(): array { return $this->holidays; }
    public function collisions(): array { return $this->collisions; }
}
