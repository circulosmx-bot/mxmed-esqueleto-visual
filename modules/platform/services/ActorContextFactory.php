<?php
declare(strict_types=1);
namespace Platform\Services;
use Platform\Contracts\TrustedActorContext;
final class ActorContextFactory
{
    private const ACTOR_KEYS = ['actor', 'actor_id', 'actor_role', 'real_actor', 'effective_actor', 'impersonation', 'break_glass'];
    public function fromBackend(array $trustedBackend, array $body = [], array $query = [], array $headers = []): TrustedActorContext
    {
        foreach ([$body, $query] as $source) {
            foreach (self::ACTOR_KEYS as $key) {
                if (array_key_exists($key, $source)) throw new \InvalidArgumentException('untrusted_actor_source');
            }
        }
        foreach (array_keys($headers) as $key) {
            if (str_starts_with(strtolower((string)$key), 'x-actor-')) {
                throw new \InvalidArgumentException('untrusted_actor_header');
            }
        }
        $trustedBackend['trust_source'] = $trustedBackend['trust_source'] ?? 'backend_trusted';
        return TrustedActorContext::fromTrustedBackend($trustedBackend);
    }
}
