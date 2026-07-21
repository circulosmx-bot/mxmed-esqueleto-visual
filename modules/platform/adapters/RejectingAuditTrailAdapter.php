<?php
declare(strict_types=1);

namespace Platform\Adapters;

use Platform\Contracts\AuditAvailability;
use Platform\Contracts\AuditEventReference;
use Platform\Contracts\AuditTrailPort;
use Platform\Contracts\AuditWriteResult;

final class RejectingAuditTrailAdapter implements AuditTrailPort
{
    public function availability(): string { return AuditAvailability::AVAILABLE; }
    public function write(AuditEventReference $event): string { return AuditWriteResult::REJECTED; }
}
