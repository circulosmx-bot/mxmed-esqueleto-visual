<?php
declare(strict_types=1);
namespace Identity\Accreditation;

use Identity\Contracts\AuthenticatedAccessContext;
use PDO;

/** Initial unverified intent only. No professional/evidence/membership/owner writer. */
final class PublicEnrollmentService
{
    public const VERSION='PUB-ENR02.1';
    public const TTL=1800;
    private EvidenceAudit $audit;
    public function __construct(public readonly EvidenceAuthority $a,private EvidenceCipher $cipher,private string $proofKey)
    {
        if(strlen($proofKey)<32)throw new \RuntimeException('enrollment_private_key_required');
        $this->audit=new EvidenceAudit($a->pdo);
    }
    private function digest(string $purpose,string $value):string{return hash_hmac('sha256','PUB-ENR02:'.$purpose.':'.$value,$this->proofKey);}
    public static function opaque():string{return bin2hex(random_bytes(32));}
    public function browserDigest(string $proof):string
    {
        if(!preg_match('/^[a-f0-9]{64}$/D',$proof))throw new \RuntimeException('enrollment_proof_denied');
        return $this->digest('browser',$proof);
    }
    /** The public selector is re-resolved here and again inside every claim mutation. */
    public function claim(string $id):array
    {
        $this->a->scope?->profile($id);
        if($this->a->query('SELECT doctor_id FROM profiles_doctors WHERE doctor_id=?',[$id])->fetchColumn()!==$id)throw new \RuntimeException('enrollment_claim_unavailable');
        require_once dirname(__DIR__,2).'/profiles/controllers/PublicProfileController.php';
        $dto=(new \Profiles\Controllers\PublicProfileController(new \Profiles\Repositories\PublicProfileRepository($this->a->pdo)))->showByDoctorId($id);
        if(($dto['ok']??false)!==true||($dto['data']['profile']['doctor_id']??null)!==$id||($dto['data']['claim']['claim_allowed']??false)!==true)
            throw new \RuntimeException('enrollment_claim_unavailable');
        // Conflict sources must exist; missing migrations are not proof of no conflict.
        foreach(['auth_account_professional_bindings','auth_physician_evidence_requests'] as $table){
            if($this->a->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?",[$table])->fetchColumn()!=1)
                throw new \RuntimeException('enrollment_claim_unavailable');
        }
        if($this->a->query('SELECT binding_id FROM auth_account_professional_bindings WHERE profile_doctor_id=? LIMIT 1',[$id])->fetchColumn()!==false
            ||$this->a->query("SELECT request_id FROM auth_physician_evidence_requests WHERE profile_doctor_id=? AND reviewed_version IS NOT NULL LIMIT 1",[$id])->fetchColumn()!==false)
            throw new \RuntimeException('enrollment_claim_unavailable');
        return $dto['data'];
    }
    public function ticket(string $type,?string $profile,string $route,string $browser):string
    {
        $this->a->scope?->workflow('public_enrollment');$this->a->scope?->profile($profile);
        if($type==='CLAIM_EXISTING_PROFILE'){$this->claim((string)$profile);$route='/profiles/doctor.php?doctor_id='.rawurlencode((string)$profile);}
        elseif($type!=='REQUEST_NEW_PROFILE'||$profile!==null)throw new \InvalidArgumentException('enrollment_input_invalid');
        if(!self::publicRoute($route))throw new \InvalidArgumentException('enrollment_input_invalid');
        $json=json_encode(['type'=>$type,'profile'=>$profile,'route'=>$route,'browser'=>$this->browserDigest($browser),
            'nonce'=>self::opaque(),'expires'=>$this->a->clock->now()->getTimestamp()+self::TTL,'version'=>self::VERSION],JSON_THROW_ON_ERROR);
        $body=rtrim(strtr(base64_encode($json),'+/','-_'),'=');return $body.'.'.$this->digest('ticket',$body);
    }
    public static function publicRoute(string $route):bool
    {
        if(strlen($route)>1024||preg_match('/[\x00-\x20\x7f#]/',$route)||!str_starts_with($route,'/')||str_starts_with($route,'//'))return false;
        $p=parse_url($route);if(!is_array($p)||isset($p['host'])||isset($p['scheme']))return false;
        if(!in_array($p['path']??'',['/profiles/doctor.php','/profiles/listing.php','/public/identity/perfil-medico.php'],true))return false;
        parse_str($p['query']??'',$q);
        foreach($q as $key=>$value)if(!in_array($key,['doctor_id','state','city','specialty','entry'],true)||!is_string($value)||strlen($value)>190)return false;
        return true;
    }
    private function decode(string $ticket,string $browser):array
    {
        if(strlen($ticket)>4096)throw new \RuntimeException('enrollment_proof_denied');
        $parts=explode('.',$ticket);
        if(count($parts)!==2||!hash_equals($this->digest('ticket',$parts[0]),$parts[1]))throw new \RuntimeException('enrollment_proof_denied');
        $r=json_decode((string)base64_decode(strtr($parts[0],'-_','+/'),true),true,16,JSON_THROW_ON_ERROR);
        if(!is_array($r)||($r['version']??null)!==self::VERSION||!hash_equals($this->browserDigest($browser),(string)($r['browser']??'')))throw new \RuntimeException('enrollment_proof_denied');
        if(($r['expires']??0)<=$this->a->clock->now()->getTimestamp()||$r['expires']>$this->a->clock->now()->getTimestamp()+self::TTL)throw new \RuntimeException('enrollment_expired');
        return $r;
    }
    /** A deterministic PRF over the random ticket nonce permits lost-response retries.
     * Only digests are stored. The handle is never a session/authentication token. */
    public function start(string $ticket,string $browser):array
    {
        $this->a->scope?->workflow('public_enrollment');
        $t=$this->decode($ticket,$browser);$this->a->scope?->profile($t['profile']);
        $start=$this->digest('start',$ticket);$handle=$this->digest('handle',$t['nonce'].':'.$t['browser']);
        $p=$this->a->pdo;if($p->inTransaction())throw new \RuntimeException('enrollment_transaction_required');
        try{$p->beginTransaction();
            $r=$this->a->query('SELECT * FROM auth_physician_enrollment_origins WHERE start_digest=? FOR UPDATE',[$start])->fetch(PDO::FETCH_ASSOC);
            if(!is_array($r)){
                if((int)$this->a->query('SELECT COUNT(*) FROM auth_physician_enrollment_origins WHERE browser_digest=? AND created_at>?',[$t['browser'],$this->a->sqlTime($this->a->clock->now()->modify('-1 hour'))])->fetchColumn()>=8)throw new \RuntimeException('enrollment_try_later');
                $claim=null;
                if($t['type']==='CLAIM_EXISTING_PROFILE'){$this->a->query('SELECT doctor_id FROM profiles_doctors WHERE doctor_id=? FOR UPDATE',[$t['profile']]);$claim=$this->claim($t['profile']);}
                $id=$this->a->uuid();$provenance=json_encode(['read_model'=>self::VERSION,'eligibility'=>'REVALIDATED','source_type'=>$t['type'],
                    'profile_projection_hash'=>$claim===null?null:hash('sha256',json_encode([$claim['profile'],$claim['plan'],$claim['claim']],JSON_THROW_ON_ERROR))],JSON_THROW_ON_ERROR);
                $this->a->query('INSERT INTO auth_physician_enrollment_origins(origin_id,handle_digest,browser_digest,start_digest,initial_origin,original_profile_doctor_id,original_public_path,source_provenance,created_at,expires_at) VALUES(?,?,?,?,?,?,?,?,?,?)',
                    [$id,$this->digest('handle_storage',$handle),$t['browser'],$start,$t['type'],$t['profile'],$t['route'],$provenance,$this->a->sqlTime(),$this->a->sqlTime((new \DateTimeImmutable('@'.$t['expires'])))]);
                $r=$this->row($id);
            }
            if($r['status']!=='PRESENTED')throw new \RuntimeException('enrollment_origin_already_used');
            $p->commit();return ['application_id'=>$r['origin_id'],'handle'=>$handle,'expires_at'=>$r['expires_at']];
        }catch(\Throwable $e){if($p->inTransaction())$p->rollBack();throw $e;}
    }
    private function row(string $id):array
    {
        if(!preg_match('/^[a-f0-9-]{36}$/D',$id))throw new \RuntimeException('enrollment_not_found');
        $r=$this->a->query('SELECT * FROM auth_physician_enrollment_origins WHERE origin_id=? FOR UPDATE',[$id])->fetch(PDO::FETCH_ASSOC);
        if(!is_array($r)||$r['origin_id']!==$id)throw new \RuntimeException('enrollment_not_found');
        $this->a->scope?->profile($r['original_profile_doctor_id']);
        if($r['account_id']!==null)$this->a->scope?->account($r['account_id']);return $r;
    }
    private function proof(array $r,string $handle,string $browser):void
    {
        if(!hash_equals($r['handle_digest'],$this->digest('handle_storage',$handle))||!hash_equals($r['browser_digest'],$this->browserDigest($browser)))throw new \RuntimeException('enrollment_proof_denied');
        if($r['status']==='PRESENTED'&&new \DateTimeImmutable($r['expires_at'],new \DateTimeZone('UTC'))<=$this->a->clock->now())throw new \RuntimeException('enrollment_expired');
    }
    public function bind(AuthenticatedAccessContext $actor,string $id,string $handle,string $browser):array
    {
        return $this->a->transaction($actor,function()use($actor,$id,$handle,$browser){
            $this->a->applicant($actor->accountId());$r=$this->row($id);$this->proof($r,$handle,$browser);
            if($r['account_id']!==null){if($r['account_id']!==$actor->accountId())throw new \RuntimeException('enrollment_account_denied');return $this->projection($r);}
            if($r['original_profile_doctor_id']!==null){$this->a->query('SELECT doctor_id FROM profiles_doctors WHERE doctor_id=? FOR UPDATE',[$r['original_profile_doctor_id']]);$this->claim($r['original_profile_doctor_id']);}
            $event=$this->audit->enrollment($actor,$id,'ORIGIN_BOUND',0,'PRESENTED','DRAFT');
            $this->a->query("UPDATE auth_physician_enrollment_origins SET account_id=?,status='BOUND',bound_at=?,revision=revision+1,audit_reference=? WHERE origin_id=? AND account_id IS NULL",[$actor->accountId(),$this->a->sqlTime(),$event,$id]);
            return $this->projection($this->row($id));
        });
    }
    public function snapshot(AuthenticatedAccessContext $actor,string $id):array
    {
        return $this->a->transaction($actor,function()use($actor,$id){$this->a->applicant($actor->accountId());$r=$this->row($id);$this->owned($r,$actor);return $this->projection($r,true);});
    }
    public function preview(string $id,string $handle,string $browser):array
    {
        $p=$this->a->pdo;try{$p->beginTransaction();$r=$this->row($id);$this->proof($r,$handle,$browser);$result=$this->projection($r);$p->rollBack();return $result;}catch(\Throwable $e){if($p->inTransaction())$p->rollBack();throw $e;}
    }
    public function latest(AuthenticatedAccessContext $actor):?string
    {
        return $this->a->transaction($actor,function()use($actor){$this->a->applicant($actor->accountId());$id=$this->a->query("SELECT origin_id FROM auth_physician_enrollment_origins WHERE account_id=? ORDER BY bound_at DESC,origin_id DESC LIMIT 1",[$actor->accountId()])->fetchColumn();return is_string($id)?$id:null;});
    }
    private function owned(array $r,AuthenticatedAccessContext $actor):void
    {if($r['account_id']!==$actor->accountId()||$r['status']!=='BOUND')throw new \RuntimeException('enrollment_account_denied');}
    private function projection(array $r,bool $private=false):array
    {
        $result=['application_id'=>$r['origin_id'],'initial_origin'=>$r['initial_origin'],'original_profile_id'=>$r['original_profile_doctor_id'],
            'original_public_path'=>$r['original_public_path'],'bound'=>$r['status']==='BOUND','version'=>(int)$r['intake_version'],'state'=>$r['intake_state'],
            'pending'=>true,'medical_access'=>false,'matching_available'=>false,'resolution_available'=>false];
        if($private&&$r['intake_version']>0){$d=$this->a->query('SELECT * FROM auth_physician_enrollment_intake_revisions WHERE origin_id=? AND intake_version=?',[$r['origin_id'],$r['intake_version']])->fetch(PDO::FETCH_ASSOC);
            if(!is_array($d)||$d['account_id']!==$r['account_id'])throw new \RuntimeException('enrollment_private_integrity_failed');
            $result['intake']=json_decode($this->cipher->open($d['intake_ciphertext'],$this->scope($r,(int)$r['intake_version']),$d['key_id']),true,16,JSON_THROW_ON_ERROR);
        }return $result;
    }
    private function scope(array $r,int $version):string{return 'enrollment:'.$r['origin_id'].':'.$r['account_id'].':'.$version;}
    /** Private review discovery only; no HTTP applicant action and no resolution writer. */
    public function reviewIntakeInTransaction(AuthenticatedAccessContext $reviewer,string $id,int $version):array
    {
        if(!$this->a->pdo->inTransaction())throw new \RuntimeException('enrollment_review_transaction_required');
        $r=$this->row($id);
        if($r['account_id']===null||$r['intake_state']!=='SUBMITTED'||(int)$r['intake_version']!==$version)throw new \RuntimeException('enrollment_version_conflict');
        $this->a->reviewer($reviewer->accountId(),$r['account_id'],$reviewer->principal()->credentialVersion());
        $this->a->applicant($r['account_id']);
        return ['origin'=>$r,'intake'=>$this->projection($r,true)['intake']];
    }
    public function reviewCandidates(AuthenticatedAccessContext $reviewer,string $id,int $version):array
    {
        return $this->a->transaction($reviewer,function()use($reviewer,$id,$version){
            $r=$this->row($id);if($r['account_id']===null||$r['intake_state']!=='SUBMITTED'||(int)$r['intake_version']!==$version)throw new \RuntimeException('enrollment_version_conflict');
            $this->a->reviewer($reviewer->accountId(),$r['account_id'],$reviewer->principal()->credentialVersion());
            $data=$this->projection($r,true)['intake'];
            $c=ProfessionalMatchCandidates::find($this->a,$data['professional_license'],$data['given_names'],$data['first_surname'],$data['specialty'],$data['state'],$data['city']);
            $this->audit->enrollment($reviewer,$id,'MATCH_CANDIDATES_READ',$version,'SUBMITTED','SUBMITTED');
            return $c+['original_profile_id'=>$r['original_profile_doctor_id'],'initial_origin'=>$r['initial_origin'],'intake_version'=>$version,'resolution_available'=>false];
        });
    }
    private function validate(array $input,bool $submit):array
    {
        $limits=['given_names'=>120,'first_surname'=>120,'second_surname'=>120,'professional_license'=>64,'specialty'=>190,'state'=>120,'city'=>120];
        if(array_diff(array_keys($input),array_keys($limits))!==[])throw new \InvalidArgumentException('enrollment_input_invalid');
        $out=[];foreach($limits as $key=>$max){$v=$input[$key]??'';if(!is_string($v)||!mb_check_encoding($v,'UTF-8')||mb_strlen($v)>$max||preg_match('/[\x00-\x1f\x7f<>]/u',$v))throw new \InvalidArgumentException('enrollment_input_invalid');$out[$key]=trim($v);}
        if($submit)foreach(['given_names','first_surname','professional_license','specialty','state','city'] as $key)if($out[$key]==='')throw new \InvalidArgumentException('enrollment_required_information');
        return $out;
    }
    public function save(AuthenticatedAccessContext $actor,string $id,int $expected,string $command,array $input,bool $submit=false):array
    {
        if($expected<0||!preg_match('/^[a-f0-9-]{36}$/D',$command))throw new \InvalidArgumentException('enrollment_input_invalid');
        $data=$this->validate($input,$submit);$digest=$this->cipher->digest('enrollment-command',json_encode([$id,$actor->accountId(),$expected,$submit,$data],JSON_THROW_ON_ERROR));
        return $this->a->transaction($actor,function()use($actor,$id,$expected,$command,$data,$digest,$submit){
            $this->a->applicant($actor->accountId());$r=$this->row($id);$this->owned($r,$actor);
            $prior=$this->a->query('SELECT * FROM auth_physician_enrollment_commands WHERE origin_id=? AND command_id=?',[$id,$command])->fetch(PDO::FETCH_ASSOC);
            if(is_array($prior)){if(!hash_equals($prior['input_digest'],$digest))throw new \RuntimeException('enrollment_command_conflict');return ['version'=>(int)$prior['result_version'],'state'=>$prior['result_state'],'replayed'=>true];}
            if((int)$r['intake_version']!==$expected)throw new \RuntimeException('enrollment_version_conflict');
            if($r['intake_state']!=='DRAFT')throw new \RuntimeException('enrollment_submitted_immutable');
            if($r['original_profile_doctor_id']!==null){$this->a->query('SELECT doctor_id FROM profiles_doctors WHERE doctor_id=? FOR UPDATE',[$r['original_profile_doctor_id']]);$this->claim($r['original_profile_doctor_id']);}
            $version=$expected+1;$state=$submit?'SUBMITTED':'DRAFT';$sealed=$this->cipher->seal(json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$this->scope($r,$version));
            $event=$this->audit->enrollment($actor,$id,$submit?'INTAKE_SUBMITTED':'INTAKE_SAVED',$version,'DRAFT',$state);
            $this->a->query('INSERT INTO auth_physician_enrollment_intake_revisions(origin_id,account_id,intake_version,submission_state,intake_ciphertext,key_id,retention_policy_reference,audit_reference,created_at) VALUES(?,?,?,?,?,?,?,?,?)',
                [$id,$actor->accountId(),$version,$state,$sealed['bytes'],$sealed['key_id'],$this->cipher->retentionPolicyReference,$event,$this->a->sqlTime()]);
            $updated=$this->a->query('UPDATE auth_physician_enrollment_origins SET intake_version=?,intake_state=?,revision=revision+1,audit_reference=? WHERE origin_id=? AND intake_version=?',[$version,$state,$event,$id,$expected]);
            if($updated->rowCount()!==1)throw new \RuntimeException('enrollment_version_conflict');
            $this->a->query('INSERT INTO auth_physician_enrollment_commands(origin_id,account_id,command_id,input_digest,result_version,result_state,created_at) VALUES(?,?,?,?,?,?,?)',[$id,$actor->accountId(),$command,$digest,$version,$state,$this->a->sqlTime()]);
            return ['version'=>$version,'state'=>$state,'replayed'=>false];
        });
    }
}
