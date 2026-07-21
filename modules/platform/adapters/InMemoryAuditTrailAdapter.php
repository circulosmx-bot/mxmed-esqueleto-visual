<?php
declare(strict_types=1);

namespace Platform\Adapters;

use Platform\Contracts\AuditAvailability;
use Platform\Contracts\AuditEventReference;
use Platform\Contracts\AuditTrailPort;
use Platform\Contracts\AuditWriteResult;

final class InMemoryAuditTrailAdapter implements AuditTrailPort
{
    /** @var list<AuditEventReference> */
    private array $events = [];
    public function availability(): string { return AuditAvailability::AVAILABLE; }
    public function write(AuditEventReference $event): string { $this->events[] = $event; return AuditWriteResult::ACCEPTED; }
    /** @return list<AuditEventReference> */
    public function events(): array { return $this->events; }
}
