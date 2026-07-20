<?php
declare(strict_types=1);

namespace Identity\Adapters;

final class SessionStoreUnavailableException extends \RuntimeException
{
    public function __construct(string $message = 'session_store_unavailable') { parent::__construct($message); }
}
