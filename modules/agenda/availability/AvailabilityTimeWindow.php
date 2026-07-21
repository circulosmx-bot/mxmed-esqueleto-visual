<?php
declare(strict_types=1);

namespace Agenda\Availability;

final class AvailabilityTimeWindow
{
    private readonly int $startMinute;
    private readonly int $endMinute;

    public function __construct(private readonly string $start, private readonly string $end)
    {
        $this->startMinute = self::minute($start, false);
        $this->endMinute = self::minute($end, true);
        if ($this->startMinute >= $this->endMinute) {
            throw new CanonicalAvailabilityException('invalid_window', 'window must be ordered');
        }
    }

    public function start(): string { return $this->start; }
    public function end(): string { return $this->end; }
    public function startMinute(): int { return $this->startMinute; }
    public function endMinute(): int { return $this->endMinute; }

    public function toArray(): array
    {
        return ['start' => $this->start, 'end' => $this->end];
    }

    public static function fromArray(array $window): self
    {
        if (!array_key_exists('start', $window) || !array_key_exists('end', $window)
            || !is_string($window['start']) || !is_string($window['end'])) {
            throw new CanonicalAvailabilityException('invalid_window', 'window requires start and end');
        }
        return new self($window['start'], $window['end']);
    }

    private static function minute(string $value, bool $allowEndOfDay): int
    {
        if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value)) {
            if (!$allowEndOfDay || $value !== '24:00') {
                throw new CanonicalAvailabilityException('invalid_window', 'time must be HH:MM');
            }
            return 1440;
        }
        return ((int) substr($value, 0, 2) * 60) + (int) substr($value, 3, 2);
    }

    public static function formatMinute(int $minute): string
    {
        if ($minute < 0 || $minute > 1440) {
            throw new CanonicalAvailabilityException('invalid_window', 'minute is outside day');
        }
        if ($minute === 1440) return '24:00';
        return sprintf('%02d:%02d', intdiv($minute, 60), $minute % 60);
    }
}
