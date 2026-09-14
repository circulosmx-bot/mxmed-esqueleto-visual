<?php
declare(strict_types=1);
namespace Signatures;
require_once __DIR__.'/SignatureImage.php';
require_once __DIR__.'/../media/services/MediaReviewAudit.php';
use Media\Contracts\PrivateMediaStoragePort;
use Media\Services\MediaReviewAudit;
use Platform\Contracts\{CanonicalAuditEventInput,TrustedAuditContext};
final class PhysicianSignatureService
{
    public function __construct(private \PDO $pdo,private PrivateMediaStoragePort $storage){}
    private static function uuid(): string { $b=random_bytes(16);$b[6]=chr((ord($b[6])&15)|64);$b[8]=chr((ord($b[8])&63)|128);$h=bin2hex($b);return substr($h,0,8).'-'.substr($h,8,4).'-'.substr($h,12,4).'-'.substr($h,16,4).'-'.substr($h,20); }
    private function row(string $doctor): ?array { $s=$this->pdo->prepare('SELECT * FROM physician_signatures WHERE doctor_id=?');$s->execute([$doctor]);return $s->fetch(\PDO::FETCH_ASSOC)?:null; }
    public function current(string $doctor): ?array
    {
        $r=$this->row($doctor);if(!$r)return null;
        $stored=$this->storage->openReadStream($r['storage_key']);
        try{$bytes=stream_get_contents($stored['stream'],SignatureImage::MAX_OUTPUT_BYTES+1);}finally{fclose($stored['stream']);}
        if(strlen($bytes)!==(int)$r['byte_size'] || !hash_equals($r['checksum_sha256'],hash('sha256',$bytes)))throw new \RuntimeException('signature_integrity_failed');
        return ['image_data'=>'data:image/png;base64,'.base64_encode($bytes),'updated_at'=>$r['updated_at']];
    }
    private function begin(string $doctor,TrustedAuditContext $context): void
    {
        if($context->actorType!=='account' || $context->actorRole!=='doctor' || $context->actorScope!=='signature:'.$doctor || !$context->sessionId)throw new \RuntimeException('signature_ownership_denied');
        if($this->pdo->inTransaction())throw new \RuntimeException('signature_outer_transaction_forbidden');
        $this->pdo->beginTransaction();
        $s=$this->pdo->prepare('SELECT doctor_id FROM profiles_doctors WHERE doctor_id=? FOR UPDATE');$s->execute([$doctor]);if(!$s->fetchColumn())throw new \RuntimeException('signature_doctor_not_found');
    }
    private function audit(string $doctor,string $action,?array $old,?string $asset,TrustedAuditContext $context): void
    {
        MediaReviewAudit::writer($this->pdo)->append(new CanonicalAuditEventInput('PHYSICIAN_SIGNATURE_'.$action,'SUCCESS','USER_REQUEST','doctor',$doctor,'physician_signature',$doctor,
            ['asset_id'=>$asset,'previous_asset_id'=>$old['asset_id']??null]),$context);
    }
    public function save(string $doctor,string $data,TrustedAuditContext $context): void
    {
        $this->saveAtomically($doctor,$data,$context);
    }
    /** Internal composition hooks join the canonical save transaction; never request callbacks. */
    public function saveAtomically(string $doctor,string $data,TrustedAuditContext $context,?callable $lockAndAuthorize=null,?callable $complete=null): void
    {
        $image=SignatureImage::normalize($data);$asset=self::uuid();$key='private/signatures/'.hash('sha256',$doctor).'/'.$asset.'.png';$tmp=tempnam(sys_get_temp_dir(),'mxmed-signature-');$old=null;$stored=false;$committed=false;
        try {
            if($tmp===false || file_put_contents($tmp,$image['bytes'])!==strlen($image['bytes']))throw new \RuntimeException('signature_temp_failed');
            chmod($tmp,0600);$this->begin($doctor,$context);if($lockAndAuthorize)$lockAndAuthorize();$old=$this->row($doctor);
            $this->storage->storeImmutable($key,$tmp);$stored=true;
            $s=$this->pdo->prepare('INSERT INTO physician_signatures (doctor_id,asset_id,storage_key,checksum_sha256,byte_size,width,height) VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE asset_id=VALUES(asset_id),storage_key=VALUES(storage_key),checksum_sha256=VALUES(checksum_sha256),byte_size=VALUES(byte_size),width=VALUES(width),height=VALUES(height),updated_at=CURRENT_TIMESTAMP(6)');
            $s->execute([$doctor,$asset,$key,hash('sha256',$image['bytes']),strlen($image['bytes']),$image['width'],$image['height']]);
            $this->audit($doctor,$old?'REPLACED':'CREATED',$old,$asset,$context);if($complete)$complete();$this->pdo->commit();$committed=true;
        }catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();if($stored&&!$committed)$this->cleanup($key);throw $e;}
        finally{if(is_string($tmp)&&is_file($tmp))unlink($tmp);}
        if($old)$this->cleanup($old['storage_key']);
    }
    public function delete(string $doctor,TrustedAuditContext $context): void
    {
        try{$this->begin($doctor,$context);$old=$this->row($doctor);if($old){$s=$this->pdo->prepare('DELETE FROM physician_signatures WHERE doctor_id=?');$s->execute([$doctor]);$this->audit($doctor,'DELETED',$old,null,$context);}$this->pdo->commit();}
        catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
        if($old)$this->cleanup($old['storage_key']);
    }
    private function cleanup(string $key): void {try{$this->storage->delete($key);}catch(\Throwable){error_log('signature_asset_cleanup_failed');}}
}
