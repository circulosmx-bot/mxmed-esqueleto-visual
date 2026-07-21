<?php
declare(strict_types=1);

namespace Agenda\Availability;

final class CanonicalScheduleVersion
{
    private readonly array $windows;

    public function __construct(
        private readonly string $versionId,
        private readonly int $version,
        private readonly string $profileId,
        private readonly string $consultorioId,
        private readonly string $timezone,
        private readonly string $effectiveFrom,
        private readonly ?string $effectiveUntil,
        private readonly int $durationMinutes,
        private readonly int $gapMinutes,
        array $windows
    ) {
        if (trim($versionId) === '' || trim($profileId) === '' || trim($consultorioId) === '') {
            throw new CanonicalAvailabilityException('canonical_schedule_missing', 'schedule identity is required');
        }
        if ($version < 1) {
            throw new CanonicalAvailabilityException('canonical_schedule_missing', 'version must be positive');
        }
        try { new \DateTimeZone($timezone); } catch (\Exception) {
            throw new CanonicalAvailabilityException('invalid_timezone', 'timezone must be IANA');
        }
        if (!in_array($timezone, \DateTimeZone::listIdentifiers(), true)) {
            throw new CanonicalAvailabilityException('invalid_timezone', 'timezone must be an IANA identifier');
        }
        self::date($effectiveFrom, 'invalid_effective_range');
        if ($effectiveUntil !== null) {
            self::date($effectiveUntil, 'invalid_effective_range');
            if ($effectiveUntil <= $effectiveFrom) {
                throw new CanonicalAvailabilityException('invalid_effective_range', 'effective range is not ordered');
            }
        }
        if ($durationMinutes < 5 || $durationMinutes > 720) {
            throw new CanonicalAvailabilityException('invalid_duration', 'duration is outside 5..720');
        }
        if ($gapMinutes < 0 || $gapMinutes > 720) {
            throw new CanonicalAvailabilityException('invalid_gap', 'gap is outside 0..720');
        }
        $normalized = [];
        foreach ($windows as $window) {
            if (!$window instanceof WeeklyScheduleWindow) {
                throw new CanonicalAvailabilityException('invalid_window', 'weekly windows are required');
            }
            $normalized[] = $window;
        }
        usort($normalized, static fn(WeeklyScheduleWindow $a, WeeklyScheduleWindow $b): int =>
            [$a->weekday(), $a->startMinute(), $a->endMinute()] <=> [$b->weekday(), $b->startMinute(), $b->endMinute()]);
        $lastEndByDay = [];
        foreach ($normalized as $window) {
            $day = $window->weekday();
            if (isset($lastEndByDay[$day]) && $window->startMinute() < $lastEndByDay[$day]) {
                throw new CanonicalAvailabilityException('overlapping_windows', 'weekly windows overlap');
            }
            $lastEndByDay[$day] = $window->endMinute();
        }
        $this->windows = $normalized;
    }

    public function versionId(): string { return $this->versionId; }
    public function version(): int { return $this->version; }
    public function profileId(): string { return $this->profileId; }
    public function consultorioId(): string { return $this->consultorioId; }
    public function timezone(): string { return $this->timezone; }
    public function effectiveFrom(): string { return $this->effectiveFrom; }
    public function effectiveUntil(): ?string { return $this->effectiveUntil; }
    public function durationMinutes(): int { return $this->durationMinutes; }
    public function gapMinutes(): int { return $this->gapMinutes; }
    public function windows(): array { return $this->windows; }

    public function toArray(): array
    {
        return [
            'version_id' => $this->versionId,
            'version' => $this->version,
            'profile_id' => $this->profileId,
            'consultorio_id' => $this->consultorioId,
            'timezone' => $this->timezone,
            'effective_from' => $this->effectiveFrom,
            'effective_until' => $this->effectiveUntil,
            'duration_minutes' => $this->durationMinutes,
            'gap_minutes' => $this->gapMinutes,
            'windows' => array_map(static fn(WeeklyScheduleWindow $v): array => $v->toArray(), $this->windows),
        ];
    }

    private static function date(string $value, string $reason): void
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, new \DateTimeZone('UTC'));
        $errors = \DateTimeImmutable::getLastErrors();
        if (!$parsed || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $parsed->format('Y-m-d') !== $value) {
            throw new CanonicalAvailabilityException($reason, 'date must be YYYY-MM-DD');
        }
    }
}
