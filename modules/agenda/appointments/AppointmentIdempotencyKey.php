<?php
declare(strict_types=1);

namespace Agenda\Appointments;

final readonly class AppointmentIdempotencyKey
{
    private string $value;

    public function __construct(string $value)
    {
        $this->value = AppointmentValue::safeIdentifier($value, 'invalid_idempotency_key');
    }

    public function value(): string { return $this->value; }
    public function toArray(): array { return ['idempotency_key' => $this->value]; }
}
