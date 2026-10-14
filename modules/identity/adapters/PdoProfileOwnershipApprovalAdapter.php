<?php
declare(strict_types=1);

namespace Identity\Adapters;

use Identity\Contracts\Clock;
use Identity\Contracts\ProfileMembershipRoleChangeRequest;
use Identity\Contracts\ProfileOwnershipApprovalEvidence;
use Identity\Contracts\ProfileOwnershipApprovalPort;
use Identity\Contracts\ProfileOwnershipApproverAuthorityPort;
use Identity\Contracts\SystemClock;
use PDO;

/** Consumes only a pre-existing decision created by a separately governed issuer. */
final class PdoProfileOwnershipApprovalAdapter implements ProfileOwnershipApprovalPort
{
    public function __construct(
        private PDO $pdo,
        private ProfileOwnershipApproverAuthorityPort $authority,
        private Clock $clock = new SystemClock(),
    ) {}

    public function consume(PDO $transaction, ProfileMembershipRoleChangeRequest $request, string $executorAccountId): ProfileOwnershipApprovalEvidence
    {
        $this->assertTransaction($transaction);
        $stmt=$this->pdo->prepare('SELECT approval_reference,operation_id,case_reference,decision_reference,
            approver_account_id,executor_account_id,target_account_id,membership_id,profile_doctor_id,
            expected_role,expected_scope,new_role,status,approved_at,expires_at,consumed_at,consumed_operation_id
            FROM auth_profile_ownership_approvals WHERE approval_reference=:reference FOR UPDATE');
        $stmt->execute(['reference'=>$request->approvalReference]);
        $row=$stmt->fetch(PDO::FETCH_ASSOC);
        $now=$this->clock->now();
        if(!is_array($row)||$row['status']!=='APPROVED'||$row['consumed_at']!==null||$row['consumed_operation_id']!==null
            ||self::date((string)$row['expires_at'])<=$now)throw new \RuntimeException('profile_ownership_approval_unavailable');
        $endorsements=$this->pdo->prepare("SELECT endorsement_id,operation_id,case_reference,approver_account_id,
            target_account_id,membership_id,profile_doctor_id,capability,credential_version,status,approved_at,expires_at,
            consumed_at,consumed_operation_id FROM auth_profile_ownership_approval_endorsements
            WHERE approval_reference=:reference ORDER BY endorsement_slot FOR UPDATE");
        $endorsements->execute(['reference'=>$request->approvalReference]);$rows=$endorsements->fetchAll(PDO::FETCH_ASSOC);
        if(count($rows)!==2)throw new \RuntimeException('profile_ownership_dual_approval_unavailable');
        $approvers=[];
        foreach($rows as $endorsement){
            if($endorsement['operation_id']!==$row['operation_id']||$endorsement['case_reference']!==$row['case_reference']
                ||$endorsement['target_account_id']!==$row['target_account_id']||$endorsement['membership_id']!==$row['membership_id']
                ||$endorsement['profile_doctor_id']!==$row['profile_doctor_id']
                ||$endorsement['capability']!==\Identity\Services\InternalCapabilityCatalog::APPROVE_INITIAL_PROFILE_OWNERSHIP
                ||$endorsement['status']!=='APPROVED'||$endorsement['consumed_at']!==null||$endorsement['consumed_operation_id']!==null
                ||self::date((string)$endorsement['expires_at'])<=$now)
                throw new \RuntimeException('profile_ownership_dual_approval_unavailable');
            $approvers[]=(string)$endorsement['approver_account_id'];
            $this->authority->assertAuthorized($this->pdo,(string)$endorsement['approver_account_id'],
                (string)$row['target_account_id'],(string)$row['profile_doctor_id'],(int)$endorsement['credential_version']);
        }
        if(count(array_unique($approvers,SORT_STRING))!==2||!in_array((string)$row['approver_account_id'],$approvers,true))
            throw new \RuntimeException('profile_ownership_dual_approval_unavailable');
        $update=$this->pdo->prepare("UPDATE auth_profile_ownership_approvals
            SET status='CONSUMED',consumed_at=:consumed_at,consumed_operation_id=:operation_id
            WHERE approval_reference=:reference AND status='APPROVED' AND consumed_at IS NULL
              AND consumed_operation_id IS NULL AND expires_at>:now");
        $update->execute(['consumed_at'=>self::sqlTime($now),'operation_id'=>$request->operationId,
            'reference'=>$request->approvalReference,'now'=>self::sqlTime($now)]);
        if($update->rowCount()!==1)throw new \RuntimeException('profile_ownership_approval_replay');
        $consumeEndorsements=$this->pdo->prepare("UPDATE auth_profile_ownership_approval_endorsements
            SET status='CONSUMED',consumed_at=:consumed_at,consumed_operation_id=:operation_id
            WHERE approval_reference=:reference AND status='APPROVED' AND consumed_at IS NULL
              AND consumed_operation_id IS NULL AND expires_at>:now");
        $consumeEndorsements->execute(['consumed_at'=>self::sqlTime($now),'operation_id'=>$request->operationId,
            'reference'=>$request->approvalReference,'now'=>self::sqlTime($now)]);
        if($consumeEndorsements->rowCount()!==2)throw new \RuntimeException('profile_ownership_endorsement_replay');
        return new ProfileOwnershipApprovalEvidence(
            (string)$row['approval_reference'],(string)$row['case_reference'],(string)$row['decision_reference'],
            (string)$row['approver_account_id'],$approvers,(string)$row['executor_account_id'],(string)$row['operation_id'],
            (string)$row['membership_id'],(string)$row['target_account_id'],(string)$row['profile_doctor_id'],
            (string)$row['expected_role'],(string)$row['expected_scope'],(string)$row['new_role'],
            self::date((string)$row['approved_at']),self::date((string)$row['expires_at'])
        );
    }

    private function assertTransaction(PDO $transaction):void
    {
        if($transaction!==$this->pdo||!$this->pdo->inTransaction())throw new \RuntimeException('profile_ownership_approval_transaction_required');
    }
    private static function date(string $value):\DateTimeImmutable{return new \DateTimeImmutable($value,new \DateTimeZone('UTC'));}
    private static function sqlTime(\DateTimeImmutable $value):string{return $value->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');}
}
