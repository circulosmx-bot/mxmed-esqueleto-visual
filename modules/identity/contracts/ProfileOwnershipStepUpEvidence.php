<?php
declare(strict_types=1);

namespace Identity\Contracts;

/** Fresh authentication evidence; contains references only, never a password or token. */
final readonly class ProfileOwnershipStepUpEvidence
{
    public function __construct(
        public string $stepUpReference,
        public string $auditReference,
        public string $approvalReference,
        public string $operationId,
        public string $accountId,
        public string $sessionId,
        public int $credentialVersion,
        public string $membershipId,
        public string $profileDoctorId,
        public string $expectedRole,
        public string $expectedScope,
        public string $newRole,
        public \DateTimeImmutable $verifiedAt,
        public \DateTimeImmutable $expiresAt,
    ) {
        foreach([$stepUpReference,$auditReference,$approvalReference] as $reference)
            if(preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{7,127}$/D',$reference)!==1)throw new \InvalidArgumentException('invalid_step_up_reference');
        foreach([$accountId,$membershipId,$profileDoctorId] as $identifier)if(preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,63}$/D',$identifier)!==1)throw new \InvalidArgumentException('invalid_step_up_identifier');
        if($sessionId===''||$credentialVersion<1||$expiresAt<=$verifiedAt)throw new \InvalidArgumentException('invalid_step_up_evidence');
    }
}
