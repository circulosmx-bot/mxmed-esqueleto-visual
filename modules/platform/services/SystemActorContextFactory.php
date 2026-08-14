<?php
declare(strict_types=1);
namespace Platform\Services;
use Platform\Contracts\TrustedActorContext;
final class SystemActorContextFactory
{
    public function create(string $scope, string $targetType, string $targetId, string $provenance): TrustedActorContext
    {
        return TrustedActorContext::fromTrustedBackend([
            'authenticated_identity_id' => 'system', 'real_actor_type' => 'SYSTEM',
            'real_actor_id' => 'system', 'actor_role' => 'SYSTEM', 'actor_scope' => $scope,
            'target_type' => $targetType, 'target_id' => $targetId,
            'authorization_provenance' => $provenance, 'trust_source' => 'system',
        ]);
    }
}
