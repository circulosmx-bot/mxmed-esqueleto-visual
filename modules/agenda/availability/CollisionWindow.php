<?php
declare(strict_types=1);

namespace Agenda\Availability;

final class CollisionWindow
{
    private readonly AvailabilityTimeWindow $window;

    public function __construct(
        private readonly string $id,
        private readonly string $profileId,
        private readonly string $consultorioId,
        private readonly string $date,
        string $start,
        string $end,
        private readonly string $source,
        private readonly bool $active = true
    ) {
        if (!self::safeIdentifier($id) || !self::safeIdentifier($source) || trim($profileId) === '' || trim($consultorioId) === '') {
            throw new CanonicalAvailabilityException('invalid_collision', 'collision identity and source are required');
        }
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, new \DateTimeZone('UTC'));
        if (!$parsed || $parsed->format('Y-m-d') !== $date) {
            throw new CanonicalAvailabilityException('invalid_collision', 'collision date is invalid');
        }
        try { $this->window = new AvailabilityTimeWindow($start, $end); }
        catch (CanonicalAvailabilityException $error) { throw new CanonicalAvailabilityException('invalid_collision', $error->getMessage()); }
    }

    public function id(): string { return $this->id; }
    public function profileId(): string { return $this->profileId; }
    public function consultorioId(): string { return $this->consultorioId; }
    public function date(): string { return $this->date; }
    public function start(): string { return $this->window->start(); }
    public function end(): string { return $this->window->end(); }
    public function startMinute(): int { return $this->window->startMinute(); }
    public function endMinute(): int { return $this->window->endMinute(); }
    public function source(): string { return $this->source; }
    public function active(): bool { return $this->active; }
    public function toArray(): array
    {
        return ['id' => $this->id, 'profile_id' => $this->profileId, 'consultorio_id' => $this->consultorioId, 'date' => $this->date, 'window' => $this->window->toArray(), 'source' => $this->source, 'active' => $this->active];
    }

    private static function safeIdentifier(string $value): bool
    {
        return preg_match('/\A[A-Za-z0-9][A-Za-z0-9_.:-]{0,127}\z/D', $value) === 1;
    }
}
