<?php
declare(strict_types=1);

namespace Identity\Adapters;

use Identity\Contracts\{FirstDirectorBootstrapAuthorization,FirstDirectorBootstrapAuthorizationPort};
use PDO;

/** Deliberate runtime default: no flag, request body or local fixture can establish the trust anchor. */
final class RejectingFirstDirectorBootstrapAuthorizationAdapter implements FirstDirectorBootstrapAuthorizationPort
{
    public function consume(PDO $transaction,string $operationId,string $targetAccountId):FirstDirectorBootstrapAuthorization
    {
        throw new \RuntimeException('first_director_trust_anchor_unavailable');
    }
}
