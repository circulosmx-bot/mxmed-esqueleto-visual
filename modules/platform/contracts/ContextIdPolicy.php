<?php
declare(strict_types=1);
namespace Platform\Contracts;
interface ContextIdPolicy
{
    public function assertRequestId(string $requestId): void;
    public function assertCorrelationId(string $correlationId): void;
}
