<?php
declare(strict_types=1);
namespace Platform\Contracts;
interface CorrelationIdProvider
{
    public function serverGeneratedCorrelationId(string $operationKey): string;
}
