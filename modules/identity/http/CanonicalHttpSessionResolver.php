<?php
declare(strict_types=1);
namespace Identity\Http;

use Identity\Contracts\AuthenticatedAccessContext;
use Identity\Services\SessionService;

/** Only the canonical opaque cookie is input; SessionService supplies all identity facts. */
final class CanonicalHttpSessionResolver
{
    public function __construct(private SessionService $sessions) {}
    public function resolve(array $cookies): ?AuthenticatedAccessContext
    {
        $token = $cookies['__Host-mxmed_session'] ?? null;
        if (!is_string($token) || $token === '') return null;
        $decision = $this->sessions->validate($token);
        return $decision->allowed() ? $decision->context() : null;
    }
}
