<?php
declare(strict_types=1);

namespace Identity\Audit;

use Identity\Audit\Contracts\SessionAuditProducer;
use Identity\Contracts\SessionId;
use Platform\Contracts\CanonicalAuditEventInput;
use Platform\Contracts\TrustedActorContext;
use Platform\Contracts\TrustedRequestContext;

final class CanonicalSessionAuditProducer implements SessionAuditProducer
{
    public function __construct(private BoundedBestEffortAuditEmitter $emitter) {}

    public function created(TrustedRequestContext $request, TrustedActorContext $actor, SessionId $sessionId, string $reasonCode): AuditProducerEmissionResult
    { return $this->sessionEvent('AUTH_SESSION_CREATED','SUCCESS',$reasonCode,$request,$actor,$sessionId); }

    public function rotated(TrustedRequestContext $request, TrustedActorContext $actor, SessionId $sessionId, string $reasonCode): AuditProducerEmissionResult
    { return $this->sessionEvent('AUTH_SESSION_ROTATED','SUCCESS',$reasonCode,$request,$actor,$sessionId); }

    public function revoked(TrustedRequestContext $request, TrustedActorContext $actor, SessionId $sessionId, string $result, string $reasonCode): AuditProducerEmissionResult
    { return $this->sessionEvent('AUTH_SESSION_REVOKED',$result,$reasonCode,$request,$actor,$sessionId); }

    public function logout(TrustedRequestContext $request, TrustedActorContext $actor, SessionId $sessionId, string $result, string $reasonCode): AuditProducerEmissionResult
    { return $this->sessionEvent('AUTH_LOGOUT',$result,$reasonCode,$request,$actor,$sessionId); }

    public function logoutAll(TrustedRequestContext $request, TrustedActorContext $actor, TrustedIdentityId $target, string $result, string $reasonCode): AuditProducerEmissionResult
    {
        $input = new CanonicalAuditEventInput('AUTH_LOGOUT_ALL',$result,$reasonCode,$actor->effectiveEntityType,$actor->effectiveEntityId,'ACCOUNT',$target->value,[]);
        return $this->emitter->emit($input,$request,$actor);
    }

    private function sessionEvent(string $type,string $result,string $reason,TrustedRequestContext $request,TrustedActorContext $actor,SessionId $sessionId): AuditProducerEmissionResult
    {
        $input = new CanonicalAuditEventInput($type,$result,$reason,$actor->effectiveEntityType,$actor->effectiveEntityId,'SESSION',$sessionId->value(),[]);
        return $this->emitter->emit($input,$request,$actor);
    }
}
