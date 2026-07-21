<?php
declare(strict_types=1);

namespace Agenda\Availability;

final class HolidayClosure
{
    public function __construct(private readonly string $date, private readonly string $name, private readonly bool $active = true)
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, new \DateTimeZone('UTC'));
        if (!$parsed || $parsed->format('Y-m-d') !== $date || trim($name) === '') {
            throw new CanonicalAvailabilityException('invalid_override', 'holiday is invalid');
        }
    }

    public function date(): string { return $this->date; }
    public function name(): string { return $this->name; }
    public function active(): bool { return $this->active; }
    public function toArray(): array { return ['date' => $this->date, 'name' => $this->name, 'active' => $this->active]; }
}
