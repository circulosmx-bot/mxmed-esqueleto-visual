<?php
declare(strict_types=1);
namespace Platform\Contracts;
final readonly class TrustedRequestContext
{
    private function __construct(public string $requestId,public string $correlationId,public string $operationKey,public ?string $sessionId,public string $sourceModule,public string $sourceRoute,public ?string $trustedClientIp,public ?string $trustedRawUserAgent,public string $boundary,public string $correlationProvenance){}
    public static function fromTrustedBoundary(string $requestId,string $correlationId,string $operationKey,?string $sessionId,string $sourceModule,string $sourceRoute,?string $trustedClientIp,?string $trustedRawUserAgent,string $boundary,string $provenance,ContextIdPolicy $policy):self
    {
        $policy->assertRequestId($requestId);$policy->assertCorrelationId($correlationId);
        if($requestId===$correlationId)throw new \InvalidArgumentException('request_correlation_alias_rejected');
        if(!in_array($boundary,['HTTP','WEBHOOK','CLI','JOB','INTERNAL'],true))throw new \InvalidArgumentException('unknown_context_boundary');
        foreach([$operationKey,$sourceModule,$sourceRoute,$provenance] as $v)if($v===''||preg_match('/[\x00-\x1f]/',$v)===1)throw new \InvalidArgumentException('invalid_trusted_request_context');
        return new self($requestId,$correlationId,$operationKey,$sessionId,$sourceModule,$sourceRoute,$trustedClientIp,$trustedRawUserAgent,$boundary,$provenance);
    }
    public function executionId():string{return $this->requestId;}
}
