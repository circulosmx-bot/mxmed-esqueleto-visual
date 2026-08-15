<?php
declare(strict_types=1);

namespace Platform\Audit\Contracts;

use Identity\Audit\AuditProducerEmissionResult;
use Platform\Audit\AuthoritativeAuditOutcome;
use Platform\Contracts\TrustedActorContext;
use Platform\Contracts\TrustedRequestContext;

/** Dormant MP01F handoff; productive wiring remains outside this candidate. */
interface Mp01fAuditProducer
{
    public function emit(
        AuthoritativeAuditOutcome $outcome,
        TrustedRequestContext $request,
        TrustedActorContext $actor,
    ): AuditProducerEmissionResult;
}
