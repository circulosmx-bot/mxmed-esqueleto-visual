<?php
declare(strict_types=1);
namespace Identity\Adapters;

use Identity\Contracts\{Clock,ProfileMembershipRoleChangeAuditRecord,ProfileMembershipRoleChangeRequest,ProfileRoleChangeFinalizationPort,SystemClock};
use PDO;

final class PdoGovernanceCaseFinalizationAdapter implements ProfileRoleChangeFinalizationPort
{
    public function __construct(private PDO $pdo,private Clock $clock=new SystemClock()){}
    public function finalize(PDO $transaction,ProfileMembershipRoleChangeRequest $request,ProfileMembershipRoleChangeAuditRecord $record):void
    {
        if($transaction!==$this->pdo||!$this->pdo->inTransaction())throw new \RuntimeException('governance_case_transaction_required');
        $now=$this->clock->now()->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
        $s=$this->pdo->prepare("UPDATE auth_governance_cases SET status='CONSUMED',consumed_at=? WHERE operation_id=? AND operation='INITIAL_OWNER' AND beneficiary_account_id=? AND target_membership_id=? AND profile_doctor_id=? AND status='APPROVED' AND expires_at>?");
        $s->execute([$now,$request->operationId,$request->targetAccountId,$request->membershipId,$request->profileDoctorId,$now]);if($s->rowCount()!==1)throw new \RuntimeException('governance_case_finalization_denied');
        $case=$this->pdo->prepare('SELECT case_id FROM auth_governance_cases WHERE operation_id=?');$case->execute([$request->operationId]);$caseId=$case->fetchColumn();
        $e=$this->pdo->prepare("UPDATE auth_governance_case_endorsements SET status='CONSUMED',consumed_at=? WHERE case_id=? AND status='APPROVED'");$e->execute([$now,$caseId]);if($e->rowCount()!==2)throw new \RuntimeException('governance_case_endorsement_finalization_denied');
        $this->event((string)$caseId,'INITIAL_OWNER_CONSUMED',$record->actorAccountId,$request->targetAccountId,$request->profileDoctorId,'APPROVED',$request->operationId);
    }
    private function event(string $case,string $type,string $actor,string $target,string $profile,string $reason,string $evidence):void{$s=$this->pdo->prepare('INSERT INTO auth_governance_events(event_id,case_id,event_type,actor_account_id,target_account_id,profile_doctor_id,reason_code,evidence_hash) VALUES(UUID(),?,?,?,?,?,?,?)');$s->execute([$case,$type,$actor,$target,$profile,$reason,hash('sha256',$evidence)]);}
}
