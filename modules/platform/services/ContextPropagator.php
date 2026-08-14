<?php
declare(strict_types=1);
namespace Platform\Services;
use Platform\Contracts\{ExternalCorrelationEnvelope,TrustedRequestContext};
final class ContextPropagator
{
    public function toInternalLayer(TrustedRequestContext $context): TrustedRequestContext { return $context; }
    public function forFailure(TrustedRequestContext $context): TrustedRequestContext { return $context; }
    public function toExternalAdapter(TrustedRequestContext $context, string $provider, ?string $providerRequestReference): ExternalCorrelationEnvelope
    {
        return new ExternalCorrelationEnvelope($context->correlationId, $context->requestId,
            $provider, $providerRequestReference);
    }
}
