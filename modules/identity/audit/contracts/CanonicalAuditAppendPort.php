<?php
declare(strict_types=1);

namespace Identity\Audit\Contracts;

use Platform\Contracts\CanonicalAuditEventInput;
use Platform\Contracts\TrustedAuditContext;

interface CanonicalAuditAppendPort
{
    public function append(CanonicalAuditEventInput $input, TrustedAuditContext $context): void;
}
