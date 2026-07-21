<?php
declare(strict_types=1);

namespace Agenda\Availability;

final class WeeklyScheduleWindow
{
    private readonly AvailabilityTimeWindow $window;

    public function __construct(private readonly int $weekday, string $start, string $end)
    {
        if ($weekday < 1 || $weekday > 7) {
            throw new CanonicalAvailabilityException('invalid_weekday', 'weekday must be 1 through 7');
        }
        $this->window = new AvailabilityTimeWindow($start, $end);
    }

    public function weekday(): int { return $this->weekday; }
    public function start(): string { return $this->window->start(); }
    public function end(): string { return $this->window->end(); }
    public function startMinute(): int { return $this->window->startMinute(); }
    public function endMinute(): int { return $this->window->endMinute(); }
    public function toArray(): array
    {
        return ['weekday' => $this->weekday, 'start' => $this->start(), 'end' => $this->end()];
    }
}
