<?php
declare(strict_types=1);

namespace Agenda\PublicFlow;

final class PublicAgendaDomainException extends \InvalidArgumentException
{
    public function __construct(private readonly string $errorCode, string $message = '', private readonly int $httpStatus = 409)
    {
        parent::__construct($message !== '' ? $message : $errorCode);
    }

    public function code(): string { return $this->errorCode; }
    public function reason(): string { return $this->errorCode; }
    public function httpStatus(): int { return $this->httpStatus; }
}
