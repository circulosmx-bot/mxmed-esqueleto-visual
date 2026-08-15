<?php
declare(strict_types=1);

namespace Platform\Audit\Read\Contracts;

use Platform\Audit\Read\AuditReadCursor;
use Platform\Audit\Read\AuthorizedAuditRead;

/** Bounded repository port. Implementations must use DESC(created_at,event_id) keyset order. */
interface AuditReadRepositoryPort
{
    /**
     * @return list<array<string,mixed>> normalized canonical audit rows, at most $limit rows
     */
    public function fetch(AuthorizedAuditRead $read, ?AuditReadCursor $after, int $limit): array;
}
