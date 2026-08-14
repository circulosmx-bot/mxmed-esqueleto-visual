<?php
declare(strict_types=1);
namespace Platform\Contracts;
interface TrustedContinuationSource
{
    /** @return array{correlation_id:string,operation_type:string,provenance:string}|null */
    public function resolveTrustedOperation(string $serverOwnedReference): ?array;
}
