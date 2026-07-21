<?php
declare(strict_types=1);

namespace Agenda\Availability;

final class CanonicalAvailabilityException extends \InvalidArgumentException
{
    public function __construct(private readonly string $reason, string $message = '')
    {
        parent::__construct($message !== '' ? $message : $reason);
    }

    public function reason(): string
    {
        return $this->reason;
    }
}
