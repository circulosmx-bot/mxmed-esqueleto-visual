<?php
declare(strict_types=1);
namespace Platform\Services;
use Platform\Contracts\{ContextIdPolicy,TrustedCorrelationReference,TrustedRequestContext};
final class NestedCorrelationPolicy
{
    public function __construct(private ContextIdPolicy $ids,private CorrelatableOperationCatalog $catalog){}
    public function inheritSameLogicalOperation(TrustedRequestContext $parent,string $operation):TrustedCorrelationReference
    {
        $this->catalog->assertContinuable($operation);if($parent->operationKey!==$operation)throw new \InvalidArgumentException('nested_operation_mismatch');
        return TrustedCorrelationReference::fromResolvedServerState($parent->correlationId,$operation,'parent_context',$this->ids);
    }
    public function assertSameOperationCorrelation(TrustedRequestContext $parent,TrustedRequestContext $nested):void{if($parent->operationKey!==$nested->operationKey||$parent->correlationId!==$nested->correlationId)throw new \InvalidArgumentException('same_operation_must_inherit');}
    public function assertIndependentCorrelation(TrustedRequestContext $parent,TrustedRequestContext $independent):void{if($parent->correlationId===$independent->correlationId)throw new \InvalidArgumentException('independent_operation_requires_new_correlation');}
}
