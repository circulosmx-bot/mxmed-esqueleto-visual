<?php
declare(strict_types=1);
namespace Platform\Contracts;
final readonly class TrustedCorrelationReference
{
    private function __construct(public string $correlationId, public string $operationKey, public string $serverLookupProvenance) {}
    public static function fromResolvedServerState(string $correlationId,string $operationKey,string $provenance,ContextIdPolicy $policy):self
    {
        $policy->assertCorrelationId($correlationId);
        foreach([$operationKey,$provenance] as $v)if($v===''||preg_match('/[\x00-\x1f]/',$v)===1)throw new \InvalidArgumentException('invalid_trusted_continuation');
        return new self($correlationId,$operationKey,$provenance);
    }
}
