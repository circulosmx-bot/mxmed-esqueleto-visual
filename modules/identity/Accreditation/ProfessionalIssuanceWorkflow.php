<?php
declare(strict_types=1);
namespace Identity\Accreditation;

use PDO;

/** Server-side handoff from consumed C4 confirmation. No HTTP composition or authority writer. */
final class ProfessionalIssuanceWorkflow
{
    public function __construct(private PDO $pdo) {}
    public function usesConnection(PDO $pdo):bool {return $pdo===$this->pdo;}
    public static function payloadHash(array $r):string
    {
        return hash('sha256',json_encode([$r['request_id'],(int)$r['version'],$r['account_id'],$r['profile_doctor_id']],JSON_THROW_ON_ERROR));
    }
    public function record(PDO $transaction,ProtectedPhysicianEvidencePort $evidence,string $requestId,int $version):string
    {
        if ($transaction!==$this->pdo||!$transaction->inTransaction()) throw new \RuntimeException('physician_workflow_transaction_required');
        $q=$this->pdo->prepare('SELECT account_id,profile_doctor_id FROM auth_physician_evidence_requests WHERE request_id=? FOR UPDATE');
        $q->execute([$requestId]);$r=$q->fetch(PDO::FETCH_ASSOC);
        if (!is_array($r)||!is_string($r['profile_doctor_id'])) throw new \RuntimeException('physician_workflow_evidence_missing');
        $facts=$evidence->requireConfirmed($transaction,$requestId,$version,$r['account_id'],$r['profile_doctor_id']);
        $hash=self::payloadHash($facts);
        $q=$this->pdo->prepare('SELECT * FROM auth_physician_issuance_workflows WHERE command_id=? FOR UPDATE');$q->execute([$requestId]);$old=$q->fetch(PDO::FETCH_ASSOC);
        if (is_array($old)) {
            if (!hash_equals($old['payload_hash'],$hash)) throw new \RuntimeException('physician_command_payload_conflict');
            return $requestId;
        }
        $q=$this->pdo->prepare('INSERT INTO auth_physician_issuance_workflows(command_id,evidence_request_id,evidence_version,account_id,profile_doctor_id,payload_hash,created_at) VALUES(?,?,?,?,?,?,?)');
        $q->execute([$requestId,$requestId,$version,$facts['account_id'],$facts['profile_doctor_id'],$hash,$facts['confirmed_at']]);
        return $requestId;
    }
}
