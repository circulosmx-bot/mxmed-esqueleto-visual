<?php
declare(strict_types=1);

namespace Identity\Contracts;

use PDO;

interface FirstDirectorBootstrapAuthorizationPort
{
    /** Must atomically consume one externally ratified, operation-bound ceremony authorization. */
    public function consume(PDO $transaction,string $operationId,string $targetAccountId):FirstDirectorBootstrapAuthorization;
}
