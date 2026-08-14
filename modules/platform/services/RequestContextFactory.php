<?php
declare(strict_types=1);
namespace Platform\Services;
use Platform\Contracts\{ContextIdPolicy,CorrelationIdProvider,RequestIdProvider,TrustedCorrelationReference,TrustedRequestContext};
final class RequestContextFactory
{
    private const REQUEST_KEYS=['request_id','requestId'];private const CORRELATION_KEYS=['correlation_id','correlationId'];private const SOURCE_KEYS=['source_module','sourceModule','source_route','sourceRoute'];
    public function __construct(private RequestIdProvider $requestIds,private CorrelationIdProvider $correlationIds,private ContextIdPolicy $ids,private CorrelatableOperationCatalog $operations,private SourceModuleCatalog $modules,private CanonicalSourceRoutePolicy $routes){}
    public function newHttp(array $body,array $query,array $headers,string $operation,?string $session,string $module,string $method,string $template,?string $ip,?string $ua):TrustedRequestContext
    {$this->rejectClientAuthority($body,$query,$headers);$this->operations->assertStartable($operation);return $this->create($operation,$session,$module,$this->routes->http($method,$template),$ip,$ua,'HTTP',null);}
    public function continueHttp(array $body,array $query,array $headers,string $operation,TrustedCorrelationReference $ref,?string $session,string $module,string $method,string $template,?string $ip,?string $ua):TrustedRequestContext
    {$this->rejectClientAuthority($body,$query,$headers);$this->operations->assertContinuable($operation);if($ref->operationKey!==$operation)throw new \InvalidArgumentException('unrelated_correlation_reuse');return $this->create($operation,$session,$module,$this->routes->http($method,$template),$ip,$ua,'HTTP',$ref);}
    public function webhook(array $body,array $query,array $headers,string $operation,?TrustedCorrelationReference $ref,string $module,string $method,string $template,?string $ip,?string $ua):TrustedRequestContext
    {$this->rejectClientAuthority($body,$query,$headers);$this->operations->assertStartable($operation);if($ref!==null){$this->operations->assertContinuable($operation);if($ref->operationKey!==$operation)throw new \InvalidArgumentException('unrelated_webhook_correlation');}return $this->create($operation,null,$module,$this->routes->http($method,$template),$ip,$ua,'WEBHOOK',$ref);}
    public function nonHttp(string $namespace,string $name,string $operation,?TrustedCorrelationReference $ref,?string $session,string $module):TrustedRequestContext
    {$this->operations->assertStartable($operation);if($ref!==null){$this->operations->assertContinuable($operation);if($ref->operationKey!==$operation)throw new \InvalidArgumentException('unrelated_non_http_correlation');}return $this->create($operation,$session,$module,$this->routes->nonHttp($namespace,$name),null,null,$namespace==='INTERNAL'?'INTERNAL':$namespace,$ref);}
    private function create(string $operation,?string $session,string $module,string $route,?string $ip,?string $ua,string $boundary,?TrustedCorrelationReference $ref):TrustedRequestContext
    {$this->modules->assertKnown($module);$request=$this->requestIds->serverGeneratedRequestId();$correlation=$ref?->correlationId??$this->correlationIds->serverGeneratedCorrelationId($operation);return TrustedRequestContext::fromTrustedBoundary($request,$correlation,$operation,$session,$module,$route,$ip,$ua,$boundary,$ref?->serverLookupProvenance??'server_generated',$this->ids);}
    private function rejectClientAuthority(array $body,array $query,array $headers):void
    {foreach([$body,$query] as $source)foreach(array_merge(self::REQUEST_KEYS,self::CORRELATION_KEYS,self::SOURCE_KEYS) as $key)if(array_key_exists($key,$source))throw new \InvalidArgumentException('client_context_authority_rejected');foreach(array_keys($headers) as $key)if(in_array(strtolower((string)$key),['x-request-id','x-correlation-id','x-source-module','x-source-route'],true))throw new \InvalidArgumentException('untrusted_context_header_rejected');}
}
