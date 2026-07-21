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
