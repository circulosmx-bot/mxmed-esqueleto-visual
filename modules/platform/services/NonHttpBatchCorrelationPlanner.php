<?php
declare(strict_types=1);
namespace Platform\Services;
use Platform\Contracts\TrustedRequestContext;
final class NonHttpBatchCorrelationPlanner
{
    public function assertExecutionAlias(TrustedRequestContext $context,string $executionId):void{if($executionId!==$context->requestId)throw new \InvalidArgumentException('execution_id_alias_mismatch');}
    public function assertSeparateExecutions(TrustedRequestContext $a,TrustedRequestContext $b):void{if($a->requestId===$b->requestId)throw new \InvalidArgumentException('request_id_reused_across_executions');}
    public function assertIndependentBusinessUnits(array $contexts):void{$ids=array_map(fn(TrustedRequestContext $c)=>$c->correlationId,$contexts);if(count($ids)!==count(array_unique($ids)))throw new \InvalidArgumentException('independent_batch_units_forced_to_one_correlation');}
}
