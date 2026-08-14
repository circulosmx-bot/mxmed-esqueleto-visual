<?php
declare(strict_types=1);

namespace Identity\Audit;

use Identity\Audit\Contracts\IdentityAuditProducer;
use Platform\Contracts\CanonicalAuditEventInput;
use Platform\Contracts\TrustedActorContext;
use Platform\Contracts\TrustedRequestContext;

final class CanonicalIdentityAuditProducer implements IdentityAuditProducer
{
    public function __construct(
        private BoundedBestEffortAuditEmitter $emitter,
        private HmacSha256AuthIdentifierAuditHasher $identifierHasher,
        private IdentityAuditReasonResolver $reasons,
    ) {}

    public function registrationRequested(TrustedRequestContext $request, string $canonicalAuthIdentifier, string $result, string $reasonCode): AuditProducerEmissionResult
    {
        $target = $this->identifierHasher->hashCanonicalIdentifier($canonicalAuthIdentifier);
        return $this->preauthEvent('AUTH_REGISTRATION_REQUESTED',$result,$reasonCode,$target->targetType(),$target->targetId(),$request);
    }

    public function emailVerificationSent(TrustedRequestContext $request, TrustedIdentityId $target, bool $adapterAcceptedForSend, string $reasonCode): AuditProducerEmissionResult
    {
        if (!$adapterAcceptedForSend) throw new \LogicException('notification_adapter_acceptance_required_before_sent_event');
        return $this->preauthEvent('AUTH_EMAIL_VERIFICATION_SENT','SUCCESS',$reasonCode,'ACCOUNT',$target->value,$request);
    }

    public function emailVerified(TrustedRequestContext $request, VerifiedAccountId $verifiedAccount, string $reasonCode): AuditProducerEmissionResult
    {
        $target = TrustedIdentityId::fromAuthoritativeOutcome($verifiedAccount->value);
        $actor = TrustedActorContext::fromTrustedBackend([
            'authenticated_identity_id'=>$verifiedAccount->value,'real_actor_type'=>'ACCOUNT','real_actor_id'=>$verifiedAccount->value,
            'actor_role'=>'ACCOUNT_OWNER','actor_scope'=>'SELF','target_type'=>'ACCOUNT','target_id'=>$verifiedAccount->value,
            'authorization_provenance'=>'validated_email_verification_token','trust_source'=>'backend_trusted',
        ]);
        return $this->event('AUTH_EMAIL_VERIFIED','SUCCESS',$reasonCode,$target,[],$request,$actor);
    }

    public function loginSucceeded(TrustedRequestContext $request, TrustedActorContext $actor, TrustedIdentityId $target, string $reasonCode): AuditProducerEmissionResult
    {
        return $this->event('AUTH_LOGIN_SUCCEEDED','SUCCESS',$reasonCode,$target,[],$request,$actor);
    }

    public function loginFailed(TrustedRequestContext $request, string $canonicalAuthIdentifier, string $domainReason): AuditProducerEmissionResult
    {
        $target = $this->identifierHasher->hashCanonicalIdentifier($canonicalAuthIdentifier);
        $resolved = $this->reasons->loginFailure($domainReason);
        $actor = TrustedActorContext::fromTrustedBackend([
            'actor_role'=>'UNKNOWN','actor_scope'=>'PRE_AUTH','target_type'=>$target->targetType(),'target_id'=>$target->targetId(),
            'authorization_provenance'=>'backend_authentication_failure','authentication_failure'=>true,'trust_source'=>'backend_trusted',
        ]);
        $input = new CanonicalAuditEventInput('AUTH_LOGIN_FAILED',$resolved['result'],$resolved['reason_code'],null,null,$target->targetType(),$target->targetId(),[]);
        return $this->emitter->emit($input,$request,$actor);
    }

    public function passwordRecoveryRequested(TrustedRequestContext $request, string $canonicalAuthIdentifier, string $result, string $reasonCode): AuditProducerEmissionResult
    {
        $target = $this->identifierHasher->hashCanonicalIdentifier($canonicalAuthIdentifier);
        return $this->preauthEvent('AUTH_PASSWORD_RECOVERY_REQUESTED',$result,$reasonCode,$target->targetType(),$target->targetId(),$request);
    }

    public function passwordResetSucceeded(TrustedRequestContext $request, TrustedActorContext $actor, TrustedIdentityId $target, string $reasonCode): AuditProducerEmissionResult
    {
        return $this->event('AUTH_PASSWORD_RESET_SUCCEEDED','SUCCESS',$reasonCode,$target,[],$request,$actor);
    }

    public function passwordChanged(TrustedRequestContext $request, TrustedActorContext $actor, TrustedIdentityId $target, PasswordChangedFieldSet $fields, string $reasonCode): AuditProducerEmissionResult
    {
        return $this->event('AUTH_PASSWORD_CHANGED','SUCCESS',$reasonCode,$target,['changed_field_names'=>$fields->names],$request,$actor);
    }

    private function event(string $type,string $result,string $reason,TrustedIdentityId $target,array $metadata,TrustedRequestContext $request,TrustedActorContext $actor): AuditProducerEmissionResult
    {
        $input = new CanonicalAuditEventInput($type,$result,$reason,$actor->effectiveEntityType,$actor->effectiveEntityId,'ACCOUNT',$target->value,$metadata);
        return $this->emitter->emit($input,$request,$actor);
    }

    private function preauthEvent(string $type,string $result,string $reason,string $targetType,string $targetId,TrustedRequestContext $request): AuditProducerEmissionResult
    {
        $input = new CanonicalAuditEventInput($type,$result,$reason,null,null,$targetType,$targetId,[]);
        $actor = PreauthActorOptionalContext::normalUnknown($targetType,$targetId);
        return $this->emitter->emitActorOptionalPreauth($input,$request,$actor);
    }
}
