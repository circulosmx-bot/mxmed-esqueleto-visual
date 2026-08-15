<?php
declare(strict_types=1);

namespace Platform\Audit\Read;

/** Minimized response plus a durable audit-of-read intent; this class emits nothing. */
final readonly class AuditReadPage
{
    /** @param list<array<string,mixed>> $items @param array<string,mixed> $accessAuditIntent */
    public function __construct(
        public array $items,
        public ?string $nextCursor,
        public array $accessAuditIntent,
        public string $historyAvailability,
    ) {
        if (count($items) > AuditReadQuery::MAX_PAGE_SIZE) {
            throw new \InvalidArgumentException('unbounded_audit_read_page');
        }
        if (($accessAuditIntent['emission_active'] ?? null) !== false) {
            throw new \LogicException('audit_read_access_emission_must_remain_dormant');
        }
        if (!in_array($historyAvailability, ['AVAILABLE', 'RETENTION_BOUNDARY_OR_UNAVAILABLE'], true)) {
            throw new \InvalidArgumentException('invalid_audit_history_availability');
        }
    }
}
