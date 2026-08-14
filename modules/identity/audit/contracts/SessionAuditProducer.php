<?php
declare(strict_types=1);

namespace Identity\Audit\Contracts;

use Identity\Audit\AuditProducerEmissionResult;
use Identity\Audit\TrustedIdentityId;
use Identity\Contracts\SessionId;
use Platform\Contracts\TrustedActorContext;
use Platform\Contracts\TrustedRequestContext;

interface SessionAuditProducer
{
    public function created(TrustedRequestContext $request, TrustedActorContext $actor, SessionId $sessionId, string $reasonCode): AuditProducerEmissionResult;
    public function rotated(TrustedRequestContext $request, TrustedActorContext $actor, SessionId $sessionId, string $reasonCode): AuditProducerEmissionResult;
    public function revoked(TrustedRequestContext $request, TrustedActorContext $actor, SessionId $sessionId, string $result, string $reasonCode): AuditProducerEmissionResult;
    public function logout(TrustedRequestContext $request, TrustedActorContext $actor, SessionId $sessionId, string $result, string $reasonCode): AuditProducerEmissionResult;
    public function logoutAll(TrustedRequestContext $request, TrustedActorContext $actor, TrustedIdentityId $target, string $result, string $reasonCode): AuditProducerEmissionResult;
}
