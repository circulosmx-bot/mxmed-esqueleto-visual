<?php
declare(strict_types=1);

namespace Platform\Contracts;

interface AuditTrailPort
{
    public function availability(): string;
    public function write(AuditEventReference $event): string;
}
