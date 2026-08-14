<?php
declare(strict_types=1);

namespace Identity\Audit\Contracts;

use Identity\Audit\AuditProducerFailureSignal;

interface AuditProducerFailureSignalPort
{
    public function signal(AuditProducerFailureSignal $signal): void;
}
