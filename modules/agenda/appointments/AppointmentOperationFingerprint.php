<?php
declare(strict_types=1);

namespace Agenda\Appointments;

final readonly class AppointmentOperationFingerprint
{
    private string $value;

    private function __construct(string $value)
    {
        if (preg_match('/\A[a-f0-9]{64}\z/D', $value) !== 1) throw new AppointmentDomainException('idempotency_conflict');
        $this->value = $value;
    }

    public static function fromCommand(AppointmentTransitionCommand $command): self
    {
        return new self(AppointmentValue::digest($command->fingerprintFields()));
    }

    public static function fromDigest(string $value): self { return new self($value); }
    public function value(): string { return $this->value; }
    public function algorithm(): string { return 'sha256'; }
}
