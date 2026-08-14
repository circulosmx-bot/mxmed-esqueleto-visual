<?php
declare(strict_types=1);

namespace Identity\Audit;

use Identity\Audit\Contracts\CanonicalAuditAppendPort;
use Platform\Contracts\CanonicalAuditEventInput;
use Platform\Contracts\TrustedAuditContext;
use Platform\Services\CanonicalAuditWriter;

final class CanonicalAuditWriterAdapter implements CanonicalAuditAppendPort
{
    public function __construct(private CanonicalAuditWriter $writer) {}

    public function append(CanonicalAuditEventInput $input, TrustedAuditContext $context): void
    {
        $this->writer->append($input, $context);
    }
}
