<?php
declare(strict_types=1);

namespace Identity\Audit\Contracts;

use Identity\Audit\AuditProducerEmissionResult;
use Identity\Audit\PasswordChangedFieldSet;
use Identity\Audit\TrustedIdentityId;
use Identity\Audit\VerifiedAccountId;
use Platform\Contracts\TrustedActorContext;
use Platform\Contracts\TrustedRequestContext;

interface IdentityAuditProducer
{
    public function registrationRequested(TrustedRequestContext $request, string $canonicalAuthIdentifier, string $result, string $reasonCode): AuditProducerEmissionResult;
    public function emailVerificationSent(TrustedRequestContext $request, TrustedIdentityId $target, bool $adapterAcceptedForSend, string $reasonCode): AuditProducerEmissionResult;
    public function emailVerified(TrustedRequestContext $request, VerifiedAccountId $verifiedAccount, string $reasonCode): AuditProducerEmissionResult;
    public function loginSucceeded(TrustedRequestContext $request, TrustedActorContext $actor, TrustedIdentityId $target, string $reasonCode): AuditProducerEmissionResult;
    public function loginFailed(TrustedRequestContext $request, string $canonicalAuthIdentifier, string $domainReason): AuditProducerEmissionResult;
    public function passwordRecoveryRequested(TrustedRequestContext $request, string $canonicalAuthIdentifier, string $result, string $reasonCode): AuditProducerEmissionResult;
    public function passwordResetSucceeded(TrustedRequestContext $request, TrustedActorContext $actor, TrustedIdentityId $target, string $reasonCode): AuditProducerEmissionResult;
    public function passwordChanged(TrustedRequestContext $request, TrustedActorContext $actor, TrustedIdentityId $target, PasswordChangedFieldSet $fields, string $reasonCode): AuditProducerEmissionResult;
}
