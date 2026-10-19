<?php
declare(strict_types=1);
namespace Identity\StaffWorkflow;
use PDO;
use Identity\StaffAdmission\{UnitOfWork,Audit};
use Identity\StaffMedia\{Policy,Cipher,Storage};
use Identity\Services\{StaffMfaService,GovernanceSecurityContext};
use Identity\Http\CanonicalHttpSessionResolver;
/** Integration of admission, private assets, canonical MFA and governance; no alternate staff writer. */
final class Service
{
    private UnitOfWork $unit;
    public function __construct(private PDO $p, private CanonicalHttpSessionResolver $sessions,
        private StaffMfaService $mfa,private GovernanceSecurityContext $security,
        private Cipher $cipher,private Storage $storage,private string $legalReference)
    {if(!$mfa->usesConnection($p)||!preg_match('/^[A-Za-z0-9_.:-]{8,128}$/D',$legalReference))throw new \RuntimeException('workflow_configuration_required');$this->unit=new UnitOfWork($p,$sessions);}
    public function usesConnection(PDO $p):bool{return $p===$this->p;}
    public function query(string $sql,array $args=[]):\PDOStatement{$q=$this->p->prepare($sql);$q->execute($args);return $q;}
    private function uuid():string{return (new \Platform\Services\RandomAuditUuidProvider())->generateCanonicalUuid();}
    private function row(string $sql,array $a=[]):array{return $this->query($sql,$a)->fetch(PDO::FETCH_ASSOC)?:throw new \RuntimeException('workflow_denied');}
    public function case(string $id):array{return $this->row('SELECT * FROM auth_staff_admission_cases WHERE case_id=? FOR UPDATE',[$id]);}
    public function director($actor,array $case):void
    {
        $s=$this->row('SELECT * FROM internal_staff WHERE account_id=? FOR UPDATE',[$actor->accountId()]);
        if($s['status']!=='ACTIVE'||$s['governance_class']!=='DIRECTOR'||$case['bound_account_id']===$actor->accountId()||$case['state']==='REVOKED')throw new \RuntimeException('workflow_denied');
    }
    private function own($actor,array $c):void
    {if(!(new \Identity\StaffMedia\Authority())->evidence($actor,$c))throw new \RuntimeException('workflow_denied');}
    private function audit($actor,array $c,string $operation,string $id):string
    {return Audit::append($this->p,$actor,$c['case_id'],$operation,$id,(int)$c['version'],'STAFF_WORKFLOW');}
    public function watermark(string $id):int{return (int)$this->query('SELECT COALESCE(MAX(asset_version),0) FROM auth_staff_media_assets WHERE case_id=? AND purpose=?',[$id,Policy::EVIDENCE])->fetchColumn();}
    public function currentSet(array $c):array
    {
        $s=$this->row('SELECT * FROM auth_staff_document_sets WHERE case_id=? ORDER BY sequence_number DESC LIMIT 1',[$c['case_id']]);
        if($c['state']!=='PERSON_CONFIRMED'||(int)$s['case_version']!==(int)$c['version']||(int)$s['media_watermark']!==$this->watermark($c['case_id'])||$s['actor_account_id']!==$c['bound_account_id']||$s['legal_reference']!==$this->legalReference)throw new \RuntimeException('workflow_stale');
        $account=$this->row('SELECT a.status,a.email_verified_at,k.credential_version FROM auth_accounts a JOIN auth_account_credentials k USING(account_id) WHERE account_id=?',[$c['bound_account_id']]);
        if($account['status']!=='active'||$account['email_verified_at']===null||(int)$account['credential_version']!==(int)$c['binding_credential_version'])throw new \RuntimeException('workflow_binding_invalid');
        return $s;
    }
    public function submit(array $cookies,array $b):array
    {
        self::keys($b,['case_id','expected_version','operation_id','documents','legal_reference','accepted']);
        if(($b['accepted']??null)!==true||($b['legal_reference']??null)!==$this->legalReference||!is_array($b['documents']))throw new \RuntimeException('workflow_acceptance_required');
        $id='set_'.str_replace('-','',self::uuidInput($b['operation_id']));
        return $this->unit->run($cookies,function($actor)use($b,$id){
            $c=$this->case($b['case_id']);$this->own($actor,$c);
            if($c['state']!=='PERSON_CONFIRMED'||(int)$c['version']!==$b['expected_version']||$this->query('SELECT 1 FROM auth_staff_admission_completions WHERE case_id=?',[$c['case_id']])->fetchColumn())throw new \RuntimeException('workflow_stale');
            $previous=[];foreach($this->query('SELECT manifest FROM auth_staff_document_sets WHERE case_id=? ORDER BY sequence_number',[$c['case_id']])->fetchAll(PDO::FETCH_COLUMN) as $history)foreach(json_decode($history,true) as $d)$previous[$d['document_id']]=$d;
            $manifest=[];$assets=[];$docs=[];
            if(count($b['documents'])<1||count($b['documents'])>4)throw new \RuntimeException('workflow_invalid'); // technical envelope, not production policy
            foreach($b['documents'] as $d){
                self::keys($d,['document_id','kind','justification','pages']);self::uuidInput($d['document_id']);
                if(isset($docs[$d['document_id']])||!in_array($d['kind'],['INE','PASSPORT'],true)||!is_string($d['justification'])||strlen(trim($d['justification']))<8||strlen($d['justification'])>300||!is_array($d['pages'])||count($d['pages'])<1||count($d['pages'])>2)throw new \RuntimeException('workflow_invalid');
                $docs[$d['document_id']]=true;$sides=[];
                foreach($d['pages'] as $page){
                    self::keys($page,['side','asset_id']);
                    if(!in_array($page['side'],['FRONT','BACK','BIOGRAPHIC'],true)||isset($sides[$page['side']])||isset($assets[$page['asset_id']]))throw new \RuntimeException('workflow_invalid');
                    $a=$this->row('SELECT * FROM auth_staff_media_assets WHERE asset_id=?',[$page['asset_id']]);
                    if($a['case_id']!==$c['case_id']||$a['account_id']!==$actor->accountId()||$a['purpose']!==Policy::EVIDENCE||(int)$a['case_version']!==(int)$c['version'])throw new \RuntimeException('workflow_denied');
                    $sides[$page['side']]=true;$assets[$page['asset_id']]=true;
                }
                if(($d['kind']==='INE'&&array_keys($sides)!==['FRONT','BACK'])||($d['kind']==='PASSPORT'&&array_keys($sides)!==['BIOGRAPHIC']))throw new \RuntimeException('workflow_document_incomplete');
                $prior=$previous[$d['document_id']]??null;
                if($prior&&$prior['kind']!==$d['kind'])throw new \RuntimeException('workflow_invalid');
                $d['document_version']=$prior?(int)$prior['document_version']+(($prior['pages']===$d['pages']&&$prior['justification']===$d['justification'])?0:1):1;
                $manifest[]=$d;
            }
            $json=json_encode($manifest,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);$hash=hash('sha256',$json);
            $old=$this->query('SELECT * FROM auth_staff_document_sets WHERE set_id=?',[$id])->fetch(PDO::FETCH_ASSOC);
            if($old){if($old['case_id']!==$c['case_id']||$old['actor_account_id']!==$actor->accountId()||!hash_equals($old['manifest_hash'],$hash)||(int)$old['case_version']!==(int)$c['version'])throw new \RuntimeException('workflow_conflict');return ['set_id'=>$id,'submitted'=>true];}
            $seq=1+(int)$this->query('SELECT COALESCE(MAX(sequence_number),0) FROM auth_staff_document_sets WHERE case_id=?',[$c['case_id']])->fetchColumn();
            $this->query('INSERT INTO auth_staff_document_sets(set_id,case_id,case_version,sequence_number,actor_account_id,manifest,manifest_hash,media_watermark,legal_reference) VALUES(?,?,?,?,?,?,?,?,?)',[$id,$c['case_id'],$c['version'],$seq,$actor->accountId(),$json,$hash,$this->watermark($c['case_id']),$this->legalReference]);
            $this->audit($actor,$c,'evidence_submit',$b['operation_id']);return ['set_id'=>$id,'submitted'=>true,'sequence'=>$seq];
        });
    }
    /** Enrich only already-authorized Director directory rows; never evidence bytes. */
    public function team(array $cookies,array $input):array
    {
        $data=(new \Identity\Team\ReadService($this->p,$this->sessions,true))->read($cookies,$input);
        if(!$data['authority']['can_read_masters'])return $data;
        return $this->unit->run($cookies,function($a)use($data,$input){
            foreach($data['items'] as &$item){
                $id=$this->query('SELECT case_id FROM auth_staff_admission_completions WHERE account_id=?',[$item['account_reference']])->fetchColumn();if(!$id)continue;
                $c=$this->case($id);$this->director($a,$c);
                $p=$this->row('SELECT given_names,first_surname,second_surname,operation_state,operation_city FROM auth_staff_admission_versions WHERE case_id=? AND version=?',[$id,$c['version']]);
                $manifest=$this->query('SELECT manifest FROM auth_staff_document_sets WHERE case_id=? ORDER BY sequence_number DESC LIMIT 1',[$id])->fetchColumn();
                $item['documents']=$manifest?array_map(static fn($d)=>['kind'=>$d['kind'],'version'=>$d['document_version'],'pages'=>count($d['pages'])],json_decode($manifest,true,16,JSON_THROW_ON_ERROR)):[];
                $item['admission_case_id']=$id;$item['personal_editable']=$item['staff_status']!=='REMOVED';$item['personal_profile']=$p;$item['identity_review']=['current'=>false];
                try{$review=$this->approved($c);$item['identity_review']=['current'=>true,'review_version'=>$review['review_id']];}catch(\RuntimeException){}
                if(isset($input['target']))$item['history']=\Identity\Team\ActivityProjection::read($this->p,$item['account_reference'],$data['authority'],$id);
                $this->audit($a,$c,'read',$this->uuid());
            }unset($item);
            $data['personal_profile_source']='STAFF_ADMISSION';$data['identity_review_source']='VERSIONED_REVIEW';return $data;
        });
    }
    public function denial(array $cookies,array $b):void
    {
        $id=$b['case_id']??null;
        if($id===null&&is_string($b['intent_id']??null))$id=$this->query('SELECT case_id FROM auth_staff_review_intents WHERE intent_id=?',[$b['intent_id']])->fetchColumn();
        if(!is_string($id)||!preg_match('/^adm_[a-f0-9]{32}$/D',$id))return;
        $this->unit->run($cookies,function($a)use($id){\Identity\StaffMedia\Audit::append($this->p,$a,$id,Policy::EVIDENCE,'workflow_denied',0,$this->uuid(),'DENIED');return [];});
    }
    public function mediaStatus(array $cookies,array $b):array
    {
        self::keys($b,['case_id','purpose']);Policy::purpose($b['purpose']);
        return $this->unit->run($cookies,function($a)use($b){$c=$this->case($b['case_id']);$this->own($a,$c);$this->audit($a,$c,'read',$this->uuid());return ['version'=>(int)$this->query('SELECT COALESCE(MAX(asset_version),0) FROM auth_staff_media_assets WHERE case_id=? AND purpose=?',[$c['case_id'],$b['purpose']])->fetchColumn()];});
    }
    public function cases(array $cookies,array $input=[]):array
    {
        foreach($input as $key=>$value)if(!in_array($key,['q','page','page_size','pending'],true))throw new \RuntimeException('workflow_invalid');
        $query=$input['q']??'';$page=$input['page']??1;$size=$input['page_size']??50;
        if(!is_string($query)||($query!==''&&!preg_match('/^[\\p{L}\\p{N} ._:\\x{0027}-]{1,64}$/uD',$query))||!is_int($page)||$page<1||$page>1000||!is_int($size)||$size<1||$size>50||isset($input['pending'])&&!is_bool($input['pending']))throw new \RuntimeException('workflow_invalid');
        return $this->unit->run($cookies,function($a)use($input,$query,$page,$size){
            $staff=$this->query("SELECT 1 FROM internal_staff WHERE account_id=? AND governance_class='DIRECTOR' AND status='ACTIVE'",[$a->accountId()])->fetchColumn();
            $where=$staff?'c.creator_account_id=?':'c.bound_account_id=?';$args=[$a->accountId()];
            if(!$staff){$where.=' AND c.binding_credential_version=? AND c.binding_reference IS NOT NULL';$args[]=$a->principal()->credentialVersion();}
            if($input['pending']??false)$where.=" AND c.state<>'REVOKED' AND NOT EXISTS(SELECT 1 FROM auth_staff_admission_completions x WHERE x.case_id=c.case_id)";
            if($query!==''){$where.=' AND (LOCATE(?,c.case_id)>0 OR LOCATE(?,CONCAT_WS(CHAR(32),v.given_names,v.first_surname,v.second_surname))>0)';array_push($args,$query,$query);}
            $rows=$this->query('SELECT c.case_id,c.state,c.version,CONCAT_WS(CHAR(32),v.given_names,v.first_surname,v.second_surname) AS display_name,EXISTS(SELECT 1 FROM auth_staff_admission_completions x WHERE x.case_id=c.case_id) AS admitted FROM auth_staff_admission_cases c JOIN auth_staff_admission_versions v ON v.case_id=c.case_id AND v.version=c.version WHERE '.$where.' ORDER BY c.updated_at DESC,c.case_id LIMIT '.($size+1).' OFFSET '.(($page-1)*$size),$args)->fetchAll(PDO::FETCH_ASSOC);
            $more=count($rows)>$size;$rows=array_slice($rows,0,$size);
            foreach($rows as $c)$this->audit($a,$c,'read',$this->uuid());return ['items'=>$rows,'page'=>$page,'has_more'=>$more];
        });
    }
    public function view(array $cookies,string $id):array
    {
        return $this->unit->run($cookies,function($a)use($id){$c=$this->case($id);
            if($c['bound_account_id']===$a->accountId())$this->own($a,$c);else $this->director($a,$c);
            $sets=$this->query('SELECT set_id,case_version,sequence_number,manifest,created_at FROM auth_staff_document_sets WHERE case_id=? ORDER BY sequence_number DESC LIMIT 20',[$id])->fetchAll(PDO::FETCH_ASSOC);
            $review=$this->query('SELECT review_id,set_id,decision,reason,created_at FROM auth_staff_identity_reviews WHERE case_id=? ORDER BY created_at DESC,review_id DESC LIMIT 1',[$id])->fetch(PDO::FETCH_ASSOC)?:null;
            $current=false;try{$s=$this->currentSet($c);$current=$review&&$review['set_id']===$s['set_id'];}catch(\RuntimeException){}
            $admitted=(bool)$this->query('SELECT 1 FROM auth_staff_admission_completions WHERE case_id=?',[$id])->fetchColumn();$actions=[];
            // Eligibility projection only: each operation revalidates all authority and versions.
            if($c['bound_account_id']!==$a->accountId()){
                try{$s=$this->currentSet($c);$actions[]='READ';
                    if(!$admitted){$actions[]='CORRECTIONS';$actions[]='REJECT';
                        if($this->query("SELECT 1 FROM auth_staff_review_intents WHERE case_id=? AND set_id=? AND actor_account_id=? AND session_id=? AND action='READ' AND consumed_at IS NOT NULL AND expires_at>UTC_TIMESTAMP(6)",[$id,$s['set_id'],$a->accountId(),(string)$a->session()->sessionId()])->fetchColumn())$actions[]='ACCEPT';
                        try{$this->approved($c);if($c['proposed_category']==='ADVISOR'&&!$this->query('SELECT 1 FROM internal_staff WHERE account_id=?',[$c['bound_account_id']])->fetchColumn())$actions[]='ADMIT';}catch(\RuntimeException){}
                    }
                }catch(\RuntimeException){}
            }
            $this->audit($a,$c,'read',$this->uuid());return ['case_id'=>$id,'sets'=>$sets,'review'=>$review,'review_current'=>$current,'legal_reference'=>$this->legalReference,'admitted'=>$admitted,'available_actions'=>$actions];});
    }
    public function challenge(array $cookies,array $b):array
    {
        self::keys($b,['case_id','set_id','action','reason','password']);
        if(!in_array($b['action'],['READ','CORRECTIONS','ACCEPT','REJECT','ADMIT'],true)||!is_string($b['reason'])||strlen(trim($b['reason']))<8||strlen($b['reason'])>500)throw new \RuntimeException('workflow_invalid');
        $a=$this->sessions->resolve($cookies,true);if(!$a)throw new \RuntimeException('workflow_denied');
        // Password authentication and rate limits use their own canonical transaction before domain locks.
        $reauth=$this->security->reauthenticate($a,$this->row('SELECT email_address FROM auth_accounts WHERE account_id=?',[$a->accountId()])['email_address'],$b['password'],['ip'=>$_SERVER['REMOTE_ADDR']??'127.0.0.1']);
        return $this->unit->run($cookies,function($actor)use($b,$reauth){
            $c=$this->case($b['case_id']);$this->director($actor,$c);$s=$this->currentSet($c);
            if($s['set_id']!==$b['set_id'])throw new \RuntimeException('workflow_stale');
            $this->query("UPDATE auth_governance_cases SET status='REVOKED' WHERE operation='STAFF_ADMISSION_REVIEW' AND beneficiary_account_id=? AND status IN ('OPEN','APPROVED')",[$c['bound_account_id']]);
            $id='intent_'.bin2hex(random_bytes(16));
            $this->query('INSERT INTO auth_staff_review_intents(intent_id,actor_account_id,case_id,set_id,action,reason,session_id,credential_version,expires_at) VALUES(?,?,?,?,?,?,?,?,DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 5 MINUTE))',[$id,$actor->accountId(),$c['case_id'],$s['set_id'],$b['action'],$b['reason'],(string)$actor->session()->sessionId(),$actor->principal()->credentialVersion()]);
            $this->query("INSERT INTO auth_governance_cases(case_id,operation_id,operation,requester_account_id,beneficiary_account_id,reason,expires_at) VALUES(?,?,'STAFF_ADMISSION_REVIEW',?,?,?,DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 5 MINUTE))",[$id,$this->uuid(),$actor->accountId(),$c['bound_account_id'],$b['reason']]);
            $r=$this->mfa->issueChallenge($actor->accountId(),'STAFF_ADMISSION_REVIEW',$id,$c['bound_account_id'],$c['bound_account_id'],null,$reauth);
            $this->audit($actor,$c,'review_intent',$this->uuid());return ['intent_id'=>$id,...$r];
        });
    }
    public function verify(array $cookies,array $b):array
    {self::keys($b,['challenge_id','challenge','code']);$a=$this->sessions->resolve($cookies,true);if(!$a)throw new \RuntimeException('workflow_denied');return $this->mfa->verifyChallenge($a->accountId(),(string)$a->session()->sessionId(),$a->principal()->credentialVersion(),$b['challenge_id'],$b['challenge'],$b['code']);}
    public function intent($actor,string $id):array
    {
        $i=$this->row('SELECT * FROM auth_staff_review_intents WHERE intent_id=? FOR UPDATE',[$id]);
        if($i['actor_account_id']!==$actor->accountId()||$i['session_id']!==(string)$actor->session()->sessionId()||(int)$i['credential_version']!==$actor->principal()->credentialVersion())throw new \RuntimeException('workflow_denied');
        return $i;
    }
    private function consume($a,array $c,array $i,array $b):void
    {
        $this->director($a,$c);$s=$this->currentSet($c);
        if($s['set_id']!==$i['set_id']||$i['consumed_at']!==null||$i['expires_at']<=(new \DateTimeImmutable('now',new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.u'))throw new \RuntimeException('workflow_stale');
        $g=$this->row('SELECT status FROM auth_governance_cases WHERE case_id=? FOR UPDATE',[$i['intent_id']]);if($g['status']!=='OPEN')throw new \RuntimeException('workflow_stale');
        $this->mfa->consumeProof($this->p,$b['proof_id'],$b['proof'],$a->accountId(),(string)$a->session()->sessionId(),$a->principal()->credentialVersion(),'STAFF_ADMISSION_REVIEW',$i['intent_id'],$c['bound_account_id'],$c['bound_account_id'],null,$i['intent_id']);
        $this->query("UPDATE auth_governance_cases SET status='CONSUMED',approved_at=UTC_TIMESTAMP(6),consumed_at=UTC_TIMESTAMP(6) WHERE case_id=?",[$i['intent_id']]);
        $this->query('UPDATE auth_staff_review_intents SET consumed_at=UTC_TIMESTAMP(6) WHERE intent_id=?',[$i['intent_id']]);
    }
    public function decide(array $cookies,array $b):array
    {
        self::keys($b,['intent_id','proof_id','proof']);
        $pre=$this->row('SELECT case_id FROM auth_staff_review_intents WHERE intent_id=?',[$b['intent_id']]);
        return $this->unit->run($cookies,function($a)use($b,$pre){$c=$this->case($pre['case_id']);$i=$this->intent($a,$b['intent_id']);$this->director($a,$c);
            if(!in_array($i['action'],['ACCEPT','REJECT','CORRECTIONS'],true))throw new \RuntimeException('workflow_invalid');
            $old=$this->query('SELECT review_id,decision FROM auth_staff_identity_reviews WHERE review_id=?',[$i['intent_id']])->fetch(PDO::FETCH_ASSOC);if($old)return $old+['committed'=>true];
            $this->consume($a,$c,$i,$b);
            if($i['action']==='ACCEPT'){
                $s=$this->currentSet($c);foreach(json_decode($s['manifest'],true) as $d)foreach($d['pages'] as $page)$this->bytes($page['asset_id'],$c);
                $seen=$this->query("SELECT 1 FROM auth_staff_review_intents WHERE case_id=? AND set_id=? AND actor_account_id=? AND session_id=? AND action='READ' AND consumed_at IS NOT NULL AND expires_at>UTC_TIMESTAMP(6)",[$c['case_id'],$i['set_id'],$a->accountId(),(string)$a->session()->sessionId()])->fetchColumn();if(!$seen)throw new \RuntimeException('workflow_read_required');
            }
            $audit=$this->audit($a,$c,'review_decision',$this->uuid());
            $this->query('INSERT INTO auth_staff_identity_reviews(review_id,case_id,set_id,reviewer_account_id,reviewer_credential_version,applicant_credential_version,decision,reason,audit_event_id) VALUES(?,?,?,?,?,?,?,?,?)',[$i['intent_id'],$c['case_id'],$i['set_id'],$a->accountId(),$a->principal()->credentialVersion(),$c['binding_credential_version'],$i['action'],$i['reason'],$audit]);
            return ['review_id'=>$i['intent_id'],'decision'=>$i['action'],'committed'=>true];});
    }
    private function bytes(string $asset,array $c):array
    {
        $a=$this->row('SELECT * FROM auth_staff_media_assets WHERE asset_id=?',[$asset]);
        if($a['case_id']!==$c['case_id']||$a['account_id']!==$c['bound_account_id']||$a['purpose']!==Policy::EVIDENCE||$a['storage_key']!==Storage::key($asset)||$a['cipher_algorithm']!==Cipher::ALGORITHM)throw new \RuntimeException('workflow_denied');
        $f=$this->storage->openReadStream($a['storage_key']);try{$bytes=stream_get_contents($f['stream'],$f['bytes']+1);}finally{fclose($f['stream']);}
        if(strlen($bytes)!==(int)$a['ciphertext_bytes']||!hash_equals($a['ciphertext_hash'],hash('sha256',$bytes)))throw new \RuntimeException('workflow_integrity_failed');
        $plain=$this->cipher->open($bytes,$a,$a['key_id']);if(strlen($plain)!==(int)$a['byte_length'])throw new \RuntimeException('workflow_integrity_failed');
        return ['asset_id'=>$asset,'mime_type'=>$a['mime_type'],'base64'=>base64_encode($plain)];
    }
    public function readEvidence(array $cookies,array $b):array
    {
        self::keys($b,['intent_id','proof_id','proof']);$pre=$this->row('SELECT case_id FROM auth_staff_review_intents WHERE intent_id=?',[$b['intent_id']]);
        return $this->unit->run($cookies,function($a)use($b,$pre){$c=$this->case($pre['case_id']);$i=$this->intent($a,$b['intent_id']);if($i['action']!=='READ')throw new \RuntimeException('workflow_denied');$this->consume($a,$c,$i,$b);$set=$this->currentSet($c);$pages=[];
            foreach(json_decode($set['manifest'],true) as $d)foreach($d['pages'] as $page){$pages[]=['document_id'=>$d['document_id'],'side'=>$page['side'],...$this->bytes($page['asset_id'],$c)];}
            \Identity\StaffMedia\Audit::append($this->p,$a,$c['case_id'],Policy::EVIDENCE,'review_read',(int)$set['sequence_number'],$this->uuid());return ['pages'=>$pages];});
    }
    public function approved(array $c):array
    {
        $s=$this->currentSet($c);$r=$this->row('SELECT * FROM auth_staff_identity_reviews WHERE case_id=? ORDER BY created_at DESC,review_id DESC LIMIT 1',[$c['case_id']]);
        if($r['set_id']!==$s['set_id']||$r['decision']!=='ACCEPT'||(int)$r['applicant_credential_version']!==(int)$c['binding_credential_version'])throw new \RuntimeException('workflow_review_required');
        $account=$this->row('SELECT a.status,a.email_verified_at,c.credential_version FROM auth_accounts a JOIN auth_account_credentials c USING(account_id) WHERE account_id=? FOR UPDATE',[$c['bound_account_id']]);
        if($account['status']!=='active'||$account['email_verified_at']===null||(int)$account['credential_version']!==(int)$c['binding_credential_version'])throw new \RuntimeException('workflow_binding_invalid');
        return $r;
    }
    /** Called only by the canonical staff writer after its sorted account/staff locks. */
    public function admissionGate(PDO $p,$actor,array $b,string $target):array
    {
        if($p!==$this->p||!$p->inTransaction())throw new \RuntimeException('workflow_transaction_required');
        self::keys($b,['case_id','intent_id','proof_id','proof']);$c=$this->case($b['case_id']);$this->director($actor,$c);
        if($c['bound_account_id']!==$target||$c['proposed_category']!=='ADVISOR'||!$c['binding_reference'])throw new \RuntimeException('workflow_denied');
        $i=$this->intent($actor,$b['intent_id']);if($i['case_id']!==$c['case_id']||$i['action']!=='ADMIT')throw new \RuntimeException('workflow_denied');
        $old=$this->query('SELECT * FROM auth_staff_admission_completions WHERE case_id=?',[$c['case_id']])->fetch(PDO::FETCH_ASSOC);
        if($old){if($old['intent_id']!==$i['intent_id']||$old['actor_account_id']!==$actor->accountId())throw new \RuntimeException('workflow_conflict');return ['replay'=>$old];}
        $r=$this->approved($c);$this->consume($actor,$c,$i,$b);return ['case'=>$c,'review'=>$r,'intent'=>$i];
    }
    public function finishAdmission(PDO $p,$actor,array $gate,string $audit):void
    {
        if($p!==$this->p||!$p->inTransaction())throw new \RuntimeException('workflow_transaction_required');
        $this->query('INSERT INTO auth_staff_admission_completions(case_id,account_id,intent_id,review_id,actor_account_id,audit_event_id) VALUES(?,?,?,?,?,?)',[$gate['case']['case_id'],$gate['case']['bound_account_id'],$gate['intent']['intent_id'],$gate['review']['review_id'],$actor->accountId(),$audit]);
    }
    public static function keys(array $b,array $keys):void{$a=array_keys($b);sort($a);sort($keys);if($a!==$keys)throw new \RuntimeException('workflow_invalid');}
    public static function uuidInput($v):string{if(!is_string($v)||!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D',$v))throw new \RuntimeException('workflow_invalid');return $v;}
}
