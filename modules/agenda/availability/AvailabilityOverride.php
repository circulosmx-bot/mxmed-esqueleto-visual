<?php
declare(strict_types=1);

namespace Agenda\Availability;

final class AvailabilityOverride
{
    private readonly ?AvailabilityTimeWindow $window;

    public function __construct(
        private readonly string $id,
        private readonly string $profileId,
        private readonly string $consultorioId,
        private readonly string $date,
        private readonly string $type,
        ?array $window,
        private readonly bool $fullDay,
        private readonly string $source,
        private readonly bool $active = true
    ) {
        if (!self::safeIdentifier($id) || !self::safeIdentifier($source) || trim($profileId) === '' || trim($consultorioId) === '') {
            throw new CanonicalAvailabilityException('invalid_override', 'override identity and source are required');
        }
        if (!in_array($type, ['open', 'close'], true)) {
            throw new CanonicalAvailabilityException('invalid_override', 'override type is invalid');
        }
        self::validateDate($date);
        if ($fullDay && $window !== null) {
            throw new CanonicalAvailabilityException('invalid_override', 'full-day override cannot have a window');
        }
        if ($window !== null) $this->window = AvailabilityTimeWindow::fromArray($window);
        else $this->window = null;
        if (!$fullDay && $this->window === null) {
            throw new CanonicalAvailabilityException('invalid_override', 'partial override requires a window');
        }
    }

    public function id(): string { return $this->id; }
    public function profileId(): string { return $this->profileId; }
    public function consultorioId(): string { return $this->consultorioId; }
    public function date(): string { return $this->date; }
    public function type(): string { return $this->type; }
    public function window(): ?AvailabilityTimeWindow { return $this->window; }
    public function fullDay(): bool { return $this->fullDay; }
    public function source(): string { return $this->source; }
    public function active(): bool { return $this->active; }
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'profile_id' => $this->profileId,
            'consultorio_id' => $this->consultorioId,
            'date' => $this->date,
            'type' => $this->type,
            'window' => $this->window?->toArray(),
            'full_day' => $this->fullDay,
            'source' => $this->source,
            'active' => $this->active,
        ];
    }

    private static function validateDate(string $value): void
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, new \DateTimeZone('UTC'));
        if (!$parsed || $parsed->format('Y-m-d') !== $value) {
            throw new CanonicalAvailabilityException('invalid_override', 'override date is invalid');
        }
    }

    private static function safeIdentifier(string $value): bool
    {
        return preg_match('/\A[A-Za-z0-9][A-Za-z0-9_.:-]{0,127}\z/D', $value) === 1;
    }
}
