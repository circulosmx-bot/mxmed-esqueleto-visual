<?php
declare(strict_types=1);

namespace Platform\Audit;

use Platform\Contracts\AuditEventScopePolicy;
use Platform\Contracts\CanonicalAuditEventType;
use Platform\Contracts\TrustedRequestContext;
use Platform\Services\CorrelatableOperationCatalog;
use Platform\Services\SourceModuleCatalog;

/** MP01F-only projection over the published canonical catalogs. */
final class Mp01fEventScopePolicy implements AuditEventScopePolicy
{
    public function __construct(
        private CorrelatableOperationCatalog $operations,
        private SourceModuleCatalog $modules,
    ) {}

    /** @return list<string> */
    public function eventTypes(): array
    {
        $events = array_slice(CanonicalAuditEventType::all(), 13);
        if (count($events) !== 15) {
            throw new \LogicException('invalid_mp01f_canonical_scope');
        }
        return $events;
    }

    public function assertEvent(string $eventType): void
    {
        if (!in_array($eventType, $this->eventTypes(), true)) {
            throw new \InvalidArgumentException('event_outside_mp01f_scope');
        }
    }

    public function assertRequestMatches(string $eventType, TrustedRequestContext $request): void
    {
        $this->assertEvent($eventType);
        if ($request->operationKey !== $this->operations->operationForEvent($eventType)) {
            throw new \InvalidArgumentException('mp01f_operation_mismatch');
        }
        if ($request->sourceModule !== $this->modules->moduleForEvent($eventType)) {
            throw new \InvalidArgumentException('mp01f_source_module_mismatch');
        }
    }

    /** @return array<string,array{operation:string,source_module:string}> */
    public function projection(): array
    {
        $projection = [];
        foreach ($this->eventTypes() as $eventType) {
            $projection[$eventType] = [
                'operation' => $this->operations->operationForEvent($eventType),
                'source_module' => $this->modules->moduleForEvent($eventType),
            ];
        }
        return $projection;
    }
}
