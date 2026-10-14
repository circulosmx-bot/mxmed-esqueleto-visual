<?php
declare(strict_types=1);

namespace Identity\Contracts;

/** Trusted projection returned by an independent, transaction-joined approval authority. */
final readonly class ProfileOwnershipApprovalEvidence
{
    public function __construct(
        public string $approvalReference,
        public string $caseReference,
        public string $decisionReference,
        public string $approverAccountId,
        /** @var list<string> */
        public array $approverAccountIds,
        public string $executorAccountId,
        public string $operationId,
        public string $membershipId,
        public string $targetAccountId,
        public string $profileDoctorId,
        public string $expectedRole,
        public string $expectedScope,
        public string $newRole,
        public \DateTimeImmutable $approvedAt,
        public \DateTimeImmutable $expiresAt,
    ) {
        foreach ([$approvalReference,$caseReference,$decisionReference] as $reference) {
            if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{7,127}$/D',$reference)!==1) throw new \InvalidArgumentException('invalid_approval_evidence_reference');
        }
        foreach ([$approverAccountId,$executorAccountId,$targetAccountId,$membershipId,$profileDoctorId] as $identifier) {
            if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,63}$/D',$identifier)!==1) throw new \InvalidArgumentException('invalid_approval_evidence_identifier');
        }
        if(count($approverAccountIds)!==2||count(array_unique($approverAccountIds,SORT_STRING))!==2
            ||!in_array($approverAccountId,$approverAccountIds,true)||in_array($targetAccountId,$approverAccountIds,true))
            throw new \InvalidArgumentException('invalid_approval_evidence_authorities');
        foreach($approverAccountIds as $identifier)if(preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,63}$/D',$identifier)!==1)
            throw new \InvalidArgumentException('invalid_approval_evidence_identifier');
        if($expiresAt<=$approvedAt)throw new \InvalidArgumentException('invalid_approval_evidence_window');
    }
}
