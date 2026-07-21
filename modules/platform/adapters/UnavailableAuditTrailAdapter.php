<?php
declare(strict_types=1);

namespace Platform\Adapters;

use Platform\Contracts\AuditAvailability;
use Platform\Contracts\AuditEventReference;
use Platform\Contracts\AuditTrailPort;
use Platform\Contracts\AuditWriteResult;

final class UnavailableAuditTrailAdapter implements AuditTrailPort
{
    public function availability(): string { return AuditAvailability::UNAVAILABLE; }
    public function write(AuditEventReference $event): string { return AuditWriteResult::UNAVAILABLE; }
}
