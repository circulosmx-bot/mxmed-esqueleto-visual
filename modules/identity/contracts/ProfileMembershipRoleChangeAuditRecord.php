<?php
declare(strict_types=1);

namespace Identity\Contracts;

/** Complete non-secret evidence required for the three canonical audit events. */
final readonly class ProfileMembershipRoleChangeAuditRecord
{
    public function __construct(
        public string $operationId,
        public string $actorAccountId,
        public string $sessionId,
        public string $targetAccountId,
        public string $profileDoctorId,
        public string $membershipId,
        public string $priorRole,
        public string $newRole,
        public string $scope,
        public string $approvalReference,
        public string $caseReference,
        public string $approvalDecisionReference,
        public string $approverAccountId,
        /** @var list<string> */
        public array $approverAccountIds,
        public string $stepUpReference,
        public string $approvalDecision,
        public string $stepUpDecision,
        public string $transactionOutcome,
        public \DateTimeImmutable $occurredAt,
    ) {
        foreach([$operationId,$actorAccountId,$sessionId,$targetAccountId,$profileDoctorId,$membershipId,$priorRole,$newRole,$scope,
            $approvalReference,$caseReference,$approvalDecisionReference,$approverAccountId,$stepUpReference] as $value)
            if($value===''||strlen($value)>191||preg_match('/[\x00-\x1F\x7F]/',$value)===1)throw new \InvalidArgumentException('invalid_profile_role_change_audit_record');
        if($approvalDecision!=='APPROVED'||$stepUpDecision!=='VERIFIED'||$transactionOutcome!=='SUCCESS')throw new \InvalidArgumentException('invalid_profile_role_change_audit_outcome');
        if(count($approverAccountIds)!==2||count(array_unique($approverAccountIds,SORT_STRING))!==2
            ||!in_array($approverAccountId,$approverAccountIds,true))throw new \InvalidArgumentException('invalid_profile_role_change_approvers');
    }
}
