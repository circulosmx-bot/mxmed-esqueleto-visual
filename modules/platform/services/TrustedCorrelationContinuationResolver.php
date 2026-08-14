<?php
declare(strict_types=1);
namespace Platform\Services;
use Platform\Contracts\{ContextIdPolicy,TrustedContinuationSource,TrustedCorrelationReference};
final class TrustedCorrelationContinuationResolver
{
    public function __construct(private TrustedContinuationSource $source,private ContextIdPolicy $ids,private CorrelatableOperationCatalog $catalog){}
    public function resolve(string $serverOwnedReference):TrustedCorrelationReference
    {
        $row=$this->source->resolveTrustedOperation($serverOwnedReference);if($row===null)throw new \InvalidArgumentException('untrusted_correlation_continuation');
        $operation=(string)($row['operation_type']??'');$this->catalog->assertContinuable($operation);
        return TrustedCorrelationReference::fromResolvedServerState((string)($row['correlation_id']??''),$operation,(string)($row['provenance']??''),$this->ids);
    }
}
