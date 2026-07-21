<?php
declare(strict_types=1);

namespace Agenda\Contracts;

final class ScheduleWindow
{
    public function __construct(private string $start, private string $end)
    {
        foreach ([$start, $end] as $time) {
            if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
                throw new \InvalidArgumentException('schedule time must be HH:MM');
            }
        }
        if ($start >= $end) {
            throw new \InvalidArgumentException('schedule window must be ordered');
        }
    }
    public function start(): string { return $this->start; }
    public function end(): string { return $this->end; }
    public function toArray(): array { return ['start' => $this->start, 'end' => $this->end]; }
}

final class ScheduleAvailabilityContract
{
    public const READ_MODEL = 'calculated_read_model';

    public function __construct(
        private string $profileId,
        private string $consultorioId,
        private string $timezone,
        private array $windows,
        private int $durationMinutes,
        private int $gapMinutes,
        private array $overrides,
        private array $holidays,
        private array $collisions,
        private string $effectiveFrom
    ) {
        foreach ([$profileId, $consultorioId, $timezone, $effectiveFrom] as $value) {
            if (trim($value) === '') throw new \InvalidArgumentException('schedule identity is required');
        }
        if ($durationMinutes < 5 || $durationMinutes > 720 || $gapMinutes < 0 || $gapMinutes > 720) {
            throw new \InvalidArgumentException('schedule duration is invalid');
        }
        foreach ($windows as $window) {
            if (!$window instanceof ScheduleWindow) throw new \InvalidArgumentException('windows must be ScheduleWindow values');
        }
        $this->windows = array_values($windows);
        $this->overrides = array_values($overrides);
        $this->holidays = array_values($holidays);
        $this->collisions = array_values($collisions);
    }

    public function profileId(): string { return $this->profileId; }
    public function consultorioId(): string { return $this->consultorioId; }
    public function timezone(): string { return $this->timezone; }
    public function durationMinutes(): int { return $this->durationMinutes; }
    public function gapMinutes(): int { return $this->gapMinutes; }
    public function windows(): array { return $this->windows; }
    public function isReadModel(): bool { return true; }
    public function editableAuthority(): bool { return false; }
    public function toArray(): array
    {
        return [
            'profile_id' => $this->profileId,
            'consultorio_id' => $this->consultorioId,
            'timezone' => $this->timezone,
            'windows' => array_map(static fn(ScheduleWindow $value): array => $value->toArray(), $this->windows),
            'duration_minutes' => $this->durationMinutes,
            'gap_minutes' => $this->gapMinutes,
            'overrides' => $this->overrides,
            'holidays' => $this->holidays,
            'collisions' => $this->collisions,
            'effective_from' => $this->effectiveFrom,
            'mode' => self::READ_MODEL,
            'editable_authority' => false,
        ];
    }
}
