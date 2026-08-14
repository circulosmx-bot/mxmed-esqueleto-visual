<?php
declare(strict_types=1);
namespace Platform\Contracts;
final readonly class ExternalCorrelationEnvelope
{
    public function __construct(
        public string $internalCorrelationId,
        public string $requestId,
        public string $provider,
        public ?string $providerRequestReference,
    ) {
        if ($internalCorrelationId === '' || $requestId === '' || $provider === '') {
            throw new \InvalidArgumentException('invalid_external_correlation_envelope');
        }
    }
}
