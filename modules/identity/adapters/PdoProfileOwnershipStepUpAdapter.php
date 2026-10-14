<?php
declare(strict_types=1);

namespace Identity\Adapters;

use Identity\Contracts\AuthenticatedAccessContext;
use Identity\Contracts\Clock;
use Identity\Contracts\ProfileMembershipRoleChangeRequest;
use Identity\Contracts\ProfileOwnershipStepUpEvidence;
use Identity\Contracts\ProfileOwnershipStepUpPort;
use Identity\Contracts\SystemClock;
use Identity\Repositories\ProfileOwnershipStepUpRepository;
use Identity\Services\OneTimeTokenCodec;
use PDO;

final class PdoProfileOwnershipStepUpAdapter implements ProfileOwnershipStepUpPort
{
    public function __construct(private PDO $pdo,private ProfileOwnershipStepUpRepository $proofs,private Clock $clock=new SystemClock())
    {if(!$proofs->usesConnection($pdo))throw new \InvalidArgumentException('profile_ownership_step_up_connection_mismatch');}

    public function consume(PDO $transaction,ProfileMembershipRoleChangeRequest $request,AuthenticatedAccessContext $actor):ProfileOwnershipStepUpEvidence
    {
        if($transaction!==$this->pdo||!$this->pdo->inTransaction())throw new \RuntimeException('profile_ownership_step_up_transaction_required');
        $row=$this->proofs->lockByProofHash(OneTimeTokenCodec::hash($request->stepUpReference));$now=$this->clock->now();
        if(!is_array($row)||$row['status']!=='VERIFIED'||$row['consumed_at']!==null||$row['consumed_operation_id']!==null
            ||self::date((string)$row['expires_at'])<=$now)throw new \RuntimeException('profile_ownership_step_up_unavailable');
        if(!$this->proofs->consume((string)$row['step_up_id'],$request->operationId,$now))
            throw new \RuntimeException('profile_ownership_step_up_replay');
        return new ProfileOwnershipStepUpEvidence($request->stepUpReference,(string)$row['step_up_id'],
            (string)$row['approval_reference'],(string)$row['operation_id'],(string)$row['account_id'],
            (string)$row['session_id'],(int)$row['credential_version'],(string)$row['membership_id'],
            (string)$row['profile_doctor_id'],(string)$row['expected_role'],(string)$row['expected_scope'],
            (string)$row['new_role'],self::date((string)$row['verified_at']),self::date((string)$row['expires_at']));
    }
    private static function date(string $value):\DateTimeImmutable{return new \DateTimeImmutable($value,new \DateTimeZone('UTC'));}
}
