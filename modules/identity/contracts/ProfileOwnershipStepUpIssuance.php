<?php
declare(strict_types=1);

namespace Identity\Contracts;

/** Returned once to an approved HTTPS adapter; the clear proof is never persisted. */
final readonly class ProfileOwnershipStepUpIssuance
{
    public function __construct(
        public string $proof,
        public string $auditReference,
        public \DateTimeImmutable $expiresAt,
    ) {
        if(preg_match('/^[A-Za-z0-9_-]{40,64}$/D',$proof)!==1
            ||preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{7,127}$/D',$auditReference)!==1)
            throw new \InvalidArgumentException('invalid_profile_ownership_step_up_issuance');
    }
}
