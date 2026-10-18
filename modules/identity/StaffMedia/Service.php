<?php
declare(strict_types=1);
namespace Identity\StaffMedia;
use Identity\Http\CanonicalHttpSessionResolver;
use Identity\StaffAdmission\UnitOfWork;
use PDO;
require_once __DIR__.'/../../media/contracts/PrivateMediaStoragePort.php';
/** Callable foundation only. No HTTP/file selector/reviewer/staff writer. */
final class Service
{
    private UnitOfWork $unit;
    public function __construct(private PDO $pdo,CanonicalHttpSessionResolver $sessions,private Policy $policy,
        private Cipher $cipher,private \Media\Contracts\PrivateMediaStoragePort $storage,private Images $images,private string $stagingRoot,
        private Authority $authority=new Authority())
    {$this->unit=new UnitOfWork($pdo,$sessions);new Storage($stagingRoot,max($policy->maxBytes,$policy->portraitMaxBytes)+40);}
    private function query(string $sql,array $args=[]):\PDOStatement
    {$q=$this->pdo->prepare($sql);$q->execute($args);return $q;}
    private function uuid():string{return (new \Platform\Services\RandomAuditUuidProvider())->generateCanonicalUuid();}
    private function case(array $b):?array
    {return $this->query('SELECT * FROM auth_staff_admission_cases WHERE case_id=? FOR UPDATE',[$b['case_id']])->fetch(PDO::FETCH_ASSOC)?:null;}
    private function allowed($actor,?array $case,string $purpose):bool
    {return $purpose===Policy::PORTRAIT?$this->authority->portrait($actor,$case):$this->authority->evidence($actor,$case);}
    private function denied($actor,array $b,string $operation):array
    {Audit::append($this->pdo,$actor,$b['case_id'],$b['purpose'],$operation,0,$this->uuid(),'DENIED');return ['failure'=>'staff_media_denied'];}
    private function finish(array $r):array
    {if(isset($r['failure']))throw new \RuntimeException($r['failure']);return $r;}
    private function current(string $case,string $purpose):int
    {return (int)$this->query('SELECT COALESCE(MAX(asset_version),0) FROM auth_staff_media_assets WHERE case_id=? AND purpose=?',[$case,$purpose])->fetchColumn();}
    private function outcome(array $a,string $operation):array
    {return ['operation_id'=>$operation,'case_id'=>$a['case_id'],'asset_id'=>$a['asset_id'],'purpose'=>$a['purpose'],'asset_version'=>(int)$a['asset_version'],'case_version_at_capture'=>(int)$a['case_version'],'audit_event_id'=>$a['audit_event_id'],'committed'=>true,'is_current'=>$this->current($a['case_id'],$a['purpose'])===(int)$a['asset_version'],'identity_review'=>'UNAVAILABLE'];}
    private function asset(string $id):?array
    {return $this->query('SELECT * FROM auth_staff_media_assets WHERE asset_id=?',[$id])->fetch(PDO::FETCH_ASSOC)?:null;}
    public function write(array $cookies,array $input,string $bytes):array
    {
        $b=Input::validate('write',$input);
        if(strlen($bytes)<1||strlen($bytes)>$this->policy->maxBytes)throw new \RuntimeException('staff_media_bytes_invalid');
        $digest=$this->policy->digest($b,$bytes);
        return $this->finish($this->unit->run($cookies,function($actor)use($b,$bytes,$digest){
            $case=$this->case($b);if(!$this->allowed($actor,$case,$b['purpose']))return $this->denied($actor,$b,'write');
            $old=$this->query('SELECT * FROM auth_staff_media_operations WHERE actor_account_id=? AND operation_id=?',[$actor->accountId(),$b['operation_id']])->fetch(PDO::FETCH_ASSOC);
            if($old){
                if($old['case_id']!==$b['case_id']||!hash_equals($old['request_digest'],$digest))return $this->denied($actor,$b,'write');
                return $this->outcome($this->asset($old['asset_id']),$b['operation_id']);
            }
            if((int)$case['version']!==$b['expected_case_version']||$this->current($b['case_id'],$b['purpose'])!==$b['expected_asset_version'])throw new \RuntimeException('staff_media_version_conflict');
            $id=$this->policy->assetId($actor->accountId(),$b['case_id'],$b['operation_id']);$key=Storage::key($id);
            if($this->storage->exists($key))throw new \RuntimeException('staff_media_reconcile_required');
            $image=$this->images->process($bytes,$b['purpose'],$b['declared_mime']);
            $a=['asset_id'=>$id,'case_id'=>$b['case_id'],'account_id'=>$actor->accountId(),'purpose'=>$b['purpose'],
                'asset_version'=>$b['expected_asset_version']+1,'previous_version'=>$b['expected_asset_version']?:null,'case_version'=>$case['version'],
                'storage_key'=>$key,'custody_reference'=>$this->policy->custodyReference,'retention_reference'=>$this->policy->retentionReference];
            $sealed=$this->cipher->seal($image['bytes'],$a);
            $a+=['key_id'=>$sealed['key_id'],'cipher_algorithm'=>$sealed['cipher_algorithm'],'ciphertext_hash'=>hash('sha256',$sealed['bytes']),'ciphertext_bytes'=>strlen($sealed['bytes']),
                'mime_type'=>$image['mime_type'],'source_mime'=>$image['source_mime'],'byte_length'=>strlen($image['bytes']),'width'=>$image['width'],'height'=>$image['height'],
                'source_actor_account_id'=>$actor->accountId(),'source_credential_version'=>$actor->principal()->credentialVersion(),
                'recorded_at'=>(new \DateTimeImmutable('now',new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.u')];
            $tmp=tempnam($this->stagingRoot,'cipher-');if(!$tmp)throw new \RuntimeException('staff_media_staging_unavailable');
            try{
                if(!chmod($tmp,0600)||file_put_contents($tmp,$sealed['bytes'])!==strlen($sealed['bytes']))throw new \RuntimeException('staff_media_staging_unavailable');
                $this->storage->storeImmutable($key,$tmp);
            }finally{if(is_file($tmp))unlink($tmp);}
            // Physical encrypted file precedes SQL. Any failure/uncertain commit retains it for result().
            $a['audit_event_id']=Audit::append($this->pdo,$actor,$a['case_id'],$a['purpose'],'write',$a['asset_version'],$b['operation_id']);
            $columns=array_keys($a);$this->query('INSERT INTO auth_staff_media_assets('.implode(',',$columns).') VALUES('.implode(',',array_fill(0,count($a),'?')).')',array_values($a));
            $this->query('INSERT INTO auth_staff_media_operations(actor_account_id,operation_id,case_id,asset_id,request_digest,audit_event_id,recorded_at) VALUES(?,?,?,?,?,?,?)',[$actor->accountId(),$b['operation_id'],$a['case_id'],$id,$digest,$a['audit_event_id'],$a['recorded_at']]);
            return $this->outcome($a,$b['operation_id']);
        }));
    }
    private function bytes(array $a):string
    {
        if($a['cipher_algorithm']!==Cipher::ALGORITHM||$a['storage_key']!==Storage::key($a['asset_id']))throw new \RuntimeException('staff_media_integrity_failed');
        $opened=$this->storage->openReadStream($a['storage_key']);
        try{$bytes=stream_get_contents($opened['stream'],max($this->policy->maxBytes,$this->policy->portraitMaxBytes)+41);}finally{fclose($opened['stream']);}
        if(!is_string($bytes)||strlen($bytes)!==$opened['bytes']||strlen($bytes)!==(int)$a['ciphertext_bytes']||!hash_equals($a['ciphertext_hash'],hash('sha256',$bytes)))throw new \RuntimeException('staff_media_integrity_failed');
        $plain=$this->cipher->open($bytes,$a,$a['key_id']);
        if(strlen($plain)!==(int)$a['byte_length']||(new \finfo(FILEINFO_MIME_TYPE))->buffer($plain)!==$a['mime_type'])throw new \RuntimeException('staff_media_integrity_failed');
        return $plain;
    }
    private function failure(\Throwable $e):string
    {return in_array($e->getMessage(),['staff_media_key_unavailable','staff_media_file_unavailable','staff_media_integrity_failed'],true)?$e->getMessage():'staff_media_unavailable';}
    public function read(array $cookies,array $input):array
    {
        $b=Input::validate('read',$input);
        return $this->finish($this->unit->run($cookies,function($actor)use($b){
            $case=$this->case($b);if(!$this->allowed($actor,$case,$b['purpose']))return $this->denied($actor,$b,'read');
            $a=$this->asset($b['asset_id']);
            if(!$a||$a['case_id']!==$case['case_id']||$a['account_id']!==$actor->accountId()||$a['purpose']!==$b['purpose']||(int)$a['asset_version']!==$b['asset_version']||(int)$case['version']!==$b['expected_case_version'])return $this->denied($actor,$b,'read');
            try{$bytes=$this->bytes($a);}catch(\Throwable $e){Audit::append($this->pdo,$actor,$b['case_id'],$b['purpose'],'read',$b['asset_version'],$this->uuid(),'FAILURE');return ['failure'=>$this->failure($e)];}
            Audit::append($this->pdo,$actor,$b['case_id'],$b['purpose'],'read',$b['asset_version'],$this->uuid());
            return ['bytes'=>$bytes,'mime_type'=>$a['mime_type'],'asset_id'=>$a['asset_id'],'asset_version'=>(int)$a['asset_version'],
                'case_version_at_capture'=>(int)$a['case_version'],'profile_changed_since_capture'=>(int)$case['version']!==(int)$a['case_version'],
                'is_current'=>$this->current($a['case_id'],$a['purpose'])===(int)$a['asset_version'],'identity_review'=>'UNAVAILABLE'];
        }));
    }
    /** Reconcile exact own operation. Read-only classification, never an automatic retry/delete. */
    public function result(array $cookies,array $input):array
    {
        $b=Input::validate('result',$input);
        return $this->finish($this->unit->run($cookies,function($actor)use($b){
            $case=$this->case($b);if(!$this->allowed($actor,$case,$b['purpose']))return $this->denied($actor,$b,'result');
            $op=$this->query('SELECT * FROM auth_staff_media_operations WHERE actor_account_id=? AND operation_id=?',[$actor->accountId(),$b['operation_id']])->fetch(PDO::FETCH_ASSOC);
            if($op){
                $a=$this->asset($op['asset_id']);if($op['case_id']!==$case['case_id']||!$a||$a['purpose']!==$b['purpose']||$a['account_id']!==$actor->accountId())return $this->denied($actor,$b,'result');
                try{$this->bytes($a);$state='COMMITTED_AVAILABLE';}catch(\Throwable $e){$state=match($this->failure($e)){'staff_media_file_unavailable'=>'COMMITTED_FILE_UNAVAILABLE','staff_media_key_unavailable'=>'COMMITTED_KEY_UNAVAILABLE',default=>'COMMITTED_INTEGRITY_UNAVAILABLE'};}
                $r=$this->outcome($a,$b['operation_id'])+['reconciliation'=>$state];
            }else{
                $id=$this->policy->assetId($actor->accountId(),$b['case_id'],$b['operation_id']);
                if($this->asset($id))throw new \RuntimeException('staff_media_reconcile_required');
                $r=['operation_id'=>$b['operation_id'],'case_id'=>$b['case_id'],'committed'=>false,'reconciliation'=>$this->storage->exists(Storage::key($id))?'ORPHAN_ENCRYPTED_RETAINED':'NO_COMMITTED_OPERATION_OR_FILE'];
            }
            Audit::append($this->pdo,$actor,$b['case_id'],$b['purpose'],'result',(int)($r['asset_version']??0),$this->uuid());return $r;
        }));
    }
}
