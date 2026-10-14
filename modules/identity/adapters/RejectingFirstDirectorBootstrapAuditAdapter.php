<?php
declare(strict_types=1);

namespace Identity\Adapters;

use Identity\Contracts\{FirstDirectorBootstrapAuditPort,FirstDirectorBootstrapAuthorization};
use PDO;

final class RejectingFirstDirectorBootstrapAuditAdapter implements FirstDirectorBootstrapAuditPort
{
    public function append(PDO $transaction,FirstDirectorBootstrapAuthorization $authorization):void
    {
        throw new \RuntimeException('first_director_audit_unavailable');
    }
}
