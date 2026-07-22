<?php
declare(strict_types=1);

namespace Agenda\Appointments;

final class AppointmentDomainException extends \InvalidArgumentException
{
    public function __construct(
        private readonly string $reason,
        string $message = '',
        private readonly int $httpStatus = 409
    ) {
        parent::__construct($message !== '' ? $message : $reason);
    }

    public function reason(): string { return $this->reason; }
    public function httpStatus(): int { return $this->httpStatus; }
}
