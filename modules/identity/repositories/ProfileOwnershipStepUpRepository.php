<?php
declare(strict_types=1);

namespace Identity\Repositories;

use Identity\Contracts\ProfileMembershipRoleChangeRequest;
use PDO;

final class ProfileOwnershipStepUpRepository
{
    public function __construct(private PDO $pdo) {}
    public function usesConnection(PDO $pdo):bool{return $this->pdo===$pdo;}

    public function issue(string $stepUpId,string $proofHash,ProfileMembershipRoleChangeRequest $request,string $accountId,
        string $sessionId,int $credentialVersion,\DateTimeImmutable $verifiedAt,\DateTimeImmutable $expiresAt):void
    {
        $stmt=$this->pdo->prepare("INSERT INTO auth_profile_ownership_step_up_proofs
            (step_up_id,proof_hash,approval_reference,operation_id,account_id,session_id,credential_version,
             membership_id,profile_doctor_id,expected_role,expected_scope,new_role,status,verified_at,expires_at)
            VALUES(:step_up_id,:proof_hash,:approval_reference,:operation_id,:account_id,:session_id,:credential_version,
             :membership_id,:profile_doctor_id,:expected_role,:expected_scope,:new_role,'VERIFIED',:verified_at,:expires_at)");
        $stmt->execute(['step_up_id'=>$stepUpId,'proof_hash'=>$proofHash,'approval_reference'=>$request->approvalReference,
            'operation_id'=>$request->operationId,'account_id'=>$accountId,'session_id'=>$sessionId,
            'credential_version'=>$credentialVersion,'membership_id'=>$request->membershipId,
            'profile_doctor_id'=>$request->profileDoctorId,'expected_role'=>$request->expectedRole,
            'expected_scope'=>$request->expectedScope,'new_role'=>$request->newRole,
            'verified_at'=>self::sqlTime($verifiedAt),'expires_at'=>self::sqlTime($expiresAt)]);
    }

    /** @return array<string,mixed>|null */
    public function lockByProofHash(string $proofHash):?array
    {
        $stmt=$this->pdo->prepare('SELECT step_up_id,proof_hash,approval_reference,operation_id,account_id,session_id,
            credential_version,membership_id,profile_doctor_id,expected_role,expected_scope,new_role,status,
            verified_at,expires_at,consumed_at,consumed_operation_id
            FROM auth_profile_ownership_step_up_proofs WHERE proof_hash=:proof_hash FOR UPDATE');
        $stmt->execute(['proof_hash'=>$proofHash]);$row=$stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row)?$row:null;
    }

    public function consume(string $stepUpId,string $operationId,\DateTimeImmutable $now):bool
    {
        $stmt=$this->pdo->prepare("UPDATE auth_profile_ownership_step_up_proofs
            SET status='CONSUMED',consumed_at=:consumed_at,consumed_operation_id=:operation_id
            WHERE step_up_id=:step_up_id AND operation_id=:operation_probe AND status='VERIFIED'
              AND consumed_at IS NULL AND consumed_operation_id IS NULL AND expires_at>:now");
        $time=self::sqlTime($now);$stmt->execute(['consumed_at'=>$time,'operation_id'=>$operationId,
            'step_up_id'=>$stepUpId,'operation_probe'=>$operationId,'now'=>$time]);
        return $stmt->rowCount()===1;
    }
    private static function sqlTime(\DateTimeImmutable $value):string{return $value->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');}
}
