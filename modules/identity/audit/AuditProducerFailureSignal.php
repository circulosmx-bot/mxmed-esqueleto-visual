<?php
declare(strict_types=1);

namespace Identity\Audit;

use Platform\Contracts\CanonicalAuditEventType;

final readonly class AuditProducerFailureSignal
{
    public function __construct(
        public string $requestId,
        public string $correlationId,
        public string $eventType,
        public string $failureClassification,
    ) {
        CanonicalAuditEventType::assertKnown($eventType);
        if ($requestId === '' || $correlationId === '') throw new \InvalidArgumentException('missing_failure_signal_context');
        if (preg_match('/^[A-Z][A-Z0-9_]{2,63}$/D', $failureClassification) !== 1) {
            throw new \InvalidArgumentException('invalid_failure_classification');
        }
    }

    /** @return array{request_id:string,correlation_id:string,event_type:string,failure_classification:string} */
    public function safePayload(): array
    {
        return ['request_id'=>$this->requestId,'correlation_id'=>$this->correlationId,'event_type'=>$this->eventType,'failure_classification'=>$this->failureClassification];
    }
}
