<?php
declare(strict_types=1);

namespace Identity\Contracts;

use PDO;

interface FirstDirectorBootstrapAuditPort
{
    public function append(PDO $transaction,FirstDirectorBootstrapAuthorization $authorization):void;
}
