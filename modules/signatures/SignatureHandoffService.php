<?php
declare(strict_types=1);
namespace Signatures;
require_once __DIR__.'/PhysicianSignatureService.php';
use Media\Services\MediaReviewAudit;
use Platform\Contracts\{CanonicalAuditEventInput,TrustedAuditContext};
use Platform\Services\RandomAuditUuidProvider;
final class SignatureHandoffService
{
    public const TTL_SECONDS=300;
    private const PURPOSE='PHYSICIAN_SIGNATURE_HANDOFF';
    public function __construct(private \PDO $pdo,private PhysicianSignatureService $signatures){}
    private function audit(string $action,array $row,TrustedAuditContext $context): void
    {
        MediaReviewAudit::writer($this->pdo)->append(new CanonicalAuditEventInput('PHYSICIAN_SIGNATURE_HANDOFF_'.$action,'SUCCESS','USER_REQUEST','doctor',$row['doctor_id'],'signature_handoff',$row['id'],['handoff_id'=>$row['id']]),$context);
    }
    private function lockOwner(string $doctor,TrustedAuditContext $context): void
    {
        if($context->actorType!=='account'||$context->actorRole!=='doctor'||$context->actorScope!=='signature:'.$doctor||!$context->sessionId||!$context->actorIdentityId)throw new \RuntimeException('handoff_owner_denied');
        if($this->pdo->inTransaction())throw new \RuntimeException('handoff_outer_transaction_forbidden');
        $this->pdo->beginTransaction();$s=$this->pdo->prepare('SELECT doctor_id FROM profiles_doctors WHERE doctor_id=? FOR UPDATE');$s->execute([$doctor]);if(!$s->fetchColumn())throw new \RuntimeException('handoff_owner_denied');
    }
    public function create(string $doctor,TrustedAuditContext $context): array
    {
        $token=rtrim(strtr(base64_encode(random_bytes(32)),'+/','-_'),'=');$id=(new RandomAuditUuidProvider())->generateCanonicalUuid();
        try{
            $this->lockOwner($doctor,$context);
            $s=$this->pdo->prepare('SELECT id FROM physician_signature_handoffs WHERE doctor_id=? AND purpose=? AND status=\'PENDING\' AND expires_at>UTC_TIMESTAMP(6) FOR UPDATE');$s->execute([$doctor,self::PURPOSE]);
            foreach($s->fetchAll(\PDO::FETCH_COLUMN)as$previous)$this->audit('INVALIDATED',['id'=>$previous,'doctor_id'=>$doctor],$context);
            $s=$this->pdo->prepare('UPDATE physician_signature_handoffs SET expires_at=UTC_TIMESTAMP(6) WHERE doctor_id=? AND purpose=? AND status=\'PENDING\' AND expires_at>UTC_TIMESTAMP(6)');$s->execute([$doctor,self::PURPOSE]);
            $s=$this->pdo->prepare('INSERT INTO physician_signature_handoffs(id,token_hash,doctor_id,created_by_account_id,purpose,expires_at) VALUES(?,?,?,?,?,DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 300 SECOND))');$s->execute([$id,hash('sha256',$token),$doctor,$context->actorIdentityId,self::PURPOSE]);
            $this->audit('CREATED',['id'=>$id,'doctor_id'=>$doctor],$context);
            // Bounded opportunistic retention; audit history is not deleted.
            $this->pdo->exec('DELETE FROM physician_signature_handoffs WHERE expires_at<DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 1 DAY) LIMIT 100');
            $this->pdo->commit();
        }catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
        return ['id'=>$id,'token'=>$token,'ttl_seconds'=>self::TTL_SECONDS];
    }
    private function pending(string $token,bool $lock=false): array
    {
        if(preg_match('/^[A-Za-z0-9_-]{43}$/D',$token)!==1)throw new \RuntimeException('handoff_denied');
        $s=$this->pdo->prepare('SELECT *,expires_at>UTC_TIMESTAMP(6) AS valid_time FROM physician_signature_handoffs WHERE token_hash=?'.($lock?' FOR UPDATE':''));$s->execute([hash('sha256',$token)]);$r=$s->fetch(\PDO::FETCH_ASSOC);
        if(!$r||$r['purpose']!==self::PURPOSE||$r['status']!=='PENDING'||!(int)$r['valid_time'])throw new \RuntimeException('handoff_denied');
        return $r;
    }
    public function validate(string $token): array {$this->pending($token);return ['status'=>'PENDING'];}
    public function status(string $id,string $doctor,TrustedAuditContext $context): array
    {
        if($context->actorType!=='account'||$context->actorRole!=='doctor'||$context->actorScope!=='signature:'.$doctor||!$context->sessionId)throw new \RuntimeException('handoff_owner_denied');
        $s=$this->pdo->prepare('SELECT status,expires_at>UTC_TIMESTAMP(6) AS valid_time FROM physician_signature_handoffs WHERE id=? AND doctor_id=? AND created_by_account_id=? AND purpose=?');$s->execute([$id,$doctor,$context->actorIdentityId,self::PURPOSE]);$r=$s->fetch(\PDO::FETCH_ASSOC);
        if(!$r)throw new \RuntimeException('handoff_owner_denied');
        return ['status'=>$r['status']==='COMPLETED'?'COMPLETED':((int)$r['valid_time']?'PENDING':'EXPIRED')];
    }
    public function cancel(string $id,string $doctor,TrustedAuditContext $context): void
    {
        try{$this->lockOwner($doctor,$context);$s=$this->pdo->prepare('SELECT id,doctor_id,status,expires_at>UTC_TIMESTAMP(6) AS valid_time FROM physician_signature_handoffs WHERE id=? AND doctor_id=? AND created_by_account_id=? AND purpose=? FOR UPDATE');$s->execute([$id,$doctor,$context->actorIdentityId,self::PURPOSE]);$r=$s->fetch(\PDO::FETCH_ASSOC);if(!$r)throw new \RuntimeException('handoff_owner_denied');
            if($r['status']==='PENDING'&&(int)$r['valid_time']){$s=$this->pdo->prepare('UPDATE physician_signature_handoffs SET expires_at=UTC_TIMESTAMP(6) WHERE id=?');$s->execute([$id]);$this->audit('INVALIDATED',$r,$context);}$this->pdo->commit();
        }catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }
    public function complete(string $token,string $image): void
    {
        $row=$this->pending($token);$uuid=new RandomAuditUuidProvider();
        // Delegation derives exclusively from the persisted, authenticated creator binding.
        $context=TrustedAuditContext::fromServer($row['created_by_account_id'],'account','doctor','signature:'.$row['doctor_id'],$uuid->generateCanonicalUuid(),$uuid->generateCanonicalUuid(),'handoff:'.$row['id'],null,null,'PROFILE','/api/media/signature-handoff-device.php');
        $this->signatures->saveAtomically($row['doctor_id'],$image,$context,
            function()use($token,$row):void{$locked=$this->pending($token,true);if($locked['id']!==$row['id']||$locked['doctor_id']!==$row['doctor_id']||$locked['created_by_account_id']!==$row['created_by_account_id'])throw new \RuntimeException('handoff_denied');},
            function()use($row,$context):void{$s=$this->pdo->prepare('UPDATE physician_signature_handoffs SET status=\'COMPLETED\',completed_at=UTC_TIMESTAMP(6) WHERE id=? AND status=\'PENDING\' AND expires_at>UTC_TIMESTAMP(6)');$s->execute([$row['id']]);if($s->rowCount()!==1)throw new \RuntimeException('handoff_denied');$this->audit('COMPLETED',$row,$context);}
        );
    }
}
