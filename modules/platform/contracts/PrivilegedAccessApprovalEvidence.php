<?php
declare(strict_types=1);

namespace Platform\Contracts;

final readonly class PrivilegedAccessApprovalEvidence
{
    private string $approvalReference;
    private ActorReference $approverActor;
    private string $approvedMode;
    private string $approvedCaseReference;
    private string $approvedAtUtc;

    public function __construct(
        string $approvalReference,
        ActorReference $approverActor,
        string $approvedMode,
        string $approvedCaseReference,
        string $approvedAtUtc
    ) {
        $this->approvalReference = (new SafeIdentifier($approvalReference))->value();
        $this->approverActor = $approverActor;
        $this->approvedMode = PrivilegedAccessMode::assertValid($approvedMode);
        $this->approvedCaseReference = (new SafeIdentifier($approvedCaseReference))->value();
        if (trim($approvedAtUtc) === '') throw new \InvalidArgumentException('approval_timestamp_required');
        $this->approvedAtUtc = trim($approvedAtUtc);
    }
    public function approvalReference(): string { return $this->approvalReference; }
    public function approverActor(): ActorReference { return $this->approverActor; }
    public function approvedMode(): string { return $this->approvedMode; }
    public function approvedCaseReference(): string { return $this->approvedCaseReference; }
    public function approvedAtUtc(): string { return $this->approvedAtUtc; }
}
