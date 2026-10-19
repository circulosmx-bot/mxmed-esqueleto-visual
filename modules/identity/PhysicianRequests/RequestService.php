<?php
declare(strict_types=1);
namespace Identity\PhysicianRequests;
use Identity\Accreditation\{EvidenceAuthority,PublicEnrollmentService,EvidenceCipher,ProfessionalMatchCandidates};
use Identity\Contracts\AuthenticatedAccessContext;
use Identity\Services\StaffMfaService;
use Media\Contracts\PrivateMediaStoragePort;
use PDO;
/** Typed review over canonical PUB-ENR02 intake and C4 evidence. No C4 approval,
 * ownership, staff admission, publication or plan activation is implied. */
final class RequestService
{
    private RequestAuthority $authority;
    private RequestAudit $audit;
    public function __construct(public readonly EvidenceAuthority $a,private PublicEnrollmentService $enrollment,
        private EvidenceCipher $cipher,private StaffMfaService $mfa,private PrivateMediaStoragePort $storage)
    {
        if(!$mfa->usesConnection($a->pdo)||$enrollment->a->pdo!==$a->pdo)throw new \RuntimeException('physician_request_connection_mismatch');
        $this->authority=new RequestAuthority($a->pdo,$a->clock);$this->audit=new RequestAudit($a->pdo);
    }
    private static function hash(mixed $value):string{return hash('sha256',json_encode($value,JSON_THROW_ON_ERROR));}
    private function origin(string $id):array
    {
        if(!preg_match('/^[a-f0-9-]{36}$/D',$id))throw new \InvalidArgumentException('physician_request_invalid');
        $o=$this->a->query("SELECT * FROM auth_physician_enrollment_origins WHERE origin_id=? AND status='BOUND' AND intake_state='SUBMITTED' FOR UPDATE",[$id])->fetch(PDO::FETCH_ASSOC);
        if(!is_array($o)||$o['origin_id']!==$id)throw new \RuntimeException('physician_request_not_found');
        $this->a->scope?->account($o['account_id']);$this->a->scope?->profile($o['original_profile_doctor_id']);return $o;
    }
    private function head(string $id):?array
    { $r=$this->a->query('SELECT * FROM auth_physician_profile_request_decisions WHERE origin_id=? ORDER BY resolution_version DESC LIMIT 1 FOR UPDATE',[$id])->fetch(PDO::FETCH_ASSOC);return is_array($r)?$r:null; }
    private function evidence(array $o):?array
    {
        $r=$this->a->query('SELECT e.* FROM auth_physician_profile_request_evidence l JOIN auth_physician_evidence_requests e ON e.request_id=l.evidence_request_id WHERE l.origin_id=? AND l.account_id=? FOR UPDATE',[$o['origin_id'],$o['account_id']])->fetch(PDO::FETCH_ASSOC);
        if(!is_array($r))return null;
        if($r['account_id']!==$o['account_id']||$r['origin']!==$o['initial_origin']||($o['initial_origin']==='CLAIM_EXISTING_PROFILE'&&$r['profile_doctor_id']!==$o['original_profile_doctor_id']))throw new \RuntimeException('physician_request_evidence_denied');
        return $r;
    }
    private function files(?array $e):array
    {return $e===null?[]:$this->a->query('SELECT * FROM auth_physician_evidence_files WHERE request_id=? ORDER BY file_id FOR UPDATE',[$e['request_id']])->fetchAll(PDO::FETCH_ASSOC);}
    private static function capability(array $o):string{return $o['initial_origin']==='CLAIM_EXISTING_PROFILE'?RequestAuthority::CLAIM:RequestAuthority::NEW_PROFILE;}
    private static function operation(array $o):string{return $o['initial_origin']==='CLAIM_EXISTING_PROFILE'?'PHYSICIAN_CLAIM_RESOLVE':'PHYSICIAN_NEW_PROFILE_RESOLVE';}
    /** Applicant explicitly binds its existing evidence; name/email matching never binds it. */
    public function linkEvidence(AuthenticatedAccessContext $applicant,string $id,string $evidenceId):array
    {
        return $this->a->transaction($applicant,function()use($applicant,$id,$evidenceId){
            $o=$this->origin($id);$this->a->applicant($applicant->accountId());if($o['account_id']!==$applicant->accountId())throw new \RuntimeException('physician_request_applicant_denied');
            $e=$this->a->request($evidenceId);
            if($e['account_id']!==$applicant->accountId()||$e['origin']!==$o['initial_origin']||$e['profile_doctor_id']!==$o['original_profile_doctor_id'])throw new \RuntimeException('physician_request_evidence_denied');
            $old=$this->evidence($o);if($old!==null){if($old['request_id']!==$evidenceId)throw new \RuntimeException('physician_request_evidence_conflict');return ['linked'=>true,'replayed'=>true];}
            if($this->head($id)!==null)throw new \RuntimeException('physician_request_evidence_conflict');
            $audit=$this->audit->append($applicant,$o,'EVIDENCE_LINKED',1,'UNLINKED','LINKED',profile:$o['original_profile_doctor_id']);
            $this->a->query('INSERT INTO auth_physician_profile_request_evidence(origin_id,account_id,evidence_request_id,audit_reference,created_at) VALUES(?,?,?,?,?)',[$id,$o['account_id'],$evidenceId,$audit,$this->a->sqlTime()]);return ['linked'=>true,'replayed'=>false];
        });
    }
    private function facts(AuthenticatedAccessContext $actor,string $id):array
    {
        $o=$this->origin($id);$this->authority->assert($actor,RequestAuthority::READ,$o['account_id'],true);
        $d=$this->enrollment->requestIntakeInTransaction($actor,$id,(int)$o['intake_version'])['intake'];
        $account=$this->a->account($o['account_id']);$h=$this->head($id);$e=$this->evidence($o);$files=$this->files($e);
        $link=$this->a->query('SELECT profile_doctor_id FROM auth_physician_profile_request_profiles WHERE origin_id=?',[$id])->fetchColumn();
        $profile=$o['original_profile_doctor_id']??($link===false?null:$link);$p=null;$owners=[];$claimable=false;$plan=null;
        if($profile!==null){
            $p=$this->a->query('SELECT * FROM profiles_doctors WHERE doctor_id=? FOR UPDATE',[$profile])->fetch(PDO::FETCH_ASSOC);
            if(!is_array($p))throw new \RuntimeException('physician_request_profile_conflict');
            $owners=$this->a->query("SELECT membership_id,account_id FROM auth_account_memberships WHERE profile_doctor_id=? AND role_code='owner' AND status='active' FOR UPDATE",[$profile])->fetchAll(PDO::FETCH_ASSOC);
            require_once dirname(__DIR__,2).'/profiles/controllers/PublicProfileController.php';
            $dto=(new \Profiles\Controllers\PublicProfileController(new \Profiles\Repositories\PublicProfileRepository($this->a->pdo)))->showByDoctorId($profile);
            $plan=$dto['data']['plan']??null;$claimable=($dto['data']['claim']['claim_allowed']??false)===true;
        }
        $matches=ProfessionalMatchCandidates::find($this->a,$d['professional_license'],$d['given_names'],$d['first_surname'],$d['specialty'],$d['state'],$d['city']);sort($matches['candidate_profile_ids'],SORT_STRING);
        $competing=$profile===null?[]:$this->a->query("SELECT origin_id,account_id FROM auth_physician_profile_request_decisions WHERE profile_doctor_id=? AND origin_id<>? AND state='APPROVED' FOR UPDATE",[$profile,$id])->fetchAll(PDO::FETCH_ASSOC);
        $foreignBindings=$profile===null?[]:$this->a->query('SELECT binding_id,account_id FROM auth_account_professional_bindings WHERE profile_doctor_id=? AND account_id<>? FOR UPDATE',[$profile,$o['account_id']])->fetchAll(PDO::FETCH_ASSOC);
        $foreignReviews=$profile===null?[]:$this->a->query('SELECT request_id,account_id FROM auth_physician_evidence_requests WHERE profile_doctor_id=? AND account_id<>? AND reviewed_version IS NOT NULL FOR UPDATE',[$profile,$o['account_id']])->fetchAll(PDO::FETCH_ASSOC);
        $sourcesCurrent=true;$sourceHashes=null;
        if($o['initial_origin']==='CLAIM_EXISTING_PROFILE'&&$e!==null){
            try{$sources=$this->a->sources((string)$profile,(int)$e['verified_identity_id'],(int)$e['professional_credential_id']);$sourceHashes=[$sources['identity_hash'],$sources['credential_hash']];
                $sourcesCurrent=hash_equals((string)$e['identity_revision_hash'],$sources['identity_hash'])&&hash_equals((string)$e['credential_revision_hash'],$sources['credential_hash']);}
            catch(\RuntimeException $error){if($error instanceof \PDOException)throw $error;$sourcesCurrent=false;}
        }
        $expired=$e!==null&&$e['valid_until']!==null&&new \DateTimeImmutable($e['valid_until'],new \DateTimeZone('UTC'))<=$this->a->clock->now();
        foreach($files as $file)foreach(['source_expires_at','review_expires_at'] as $field)if($file[$field]!==null&&new \DateTimeImmutable($file[$field],new \DateTimeZone('UTC'))<=$this->a->clock->now())$expired=true;
        $responses=$this->a->query('SELECT response_id,resolution_id,created_at FROM auth_physician_profile_request_responses WHERE origin_id=? ORDER BY created_at,response_id FOR UPDATE',[$id])->fetchAll(PDO::FETCH_ASSOC);
        $kinds=array_values(array_unique(array_column($files,'kind')));sort($kinds);
        $evidenceReady=$e!==null&&in_array($e['status'],['SUBMITTED','APPROVED','CONFIRMED'],true)&&$kinds===['OFFICIAL_ID','PROFESSIONAL_CREDENTIAL']&&$sourcesCurrent&&!$expired;
        $warnings=[];
        if($foreignBindings!==[]||$foreignReviews!==[])$warnings[]='Existe un vínculo o revisión profesional de otra cuenta; requiere resolver el conflicto fuera de este flujo.';
        if(!$sourcesCurrent)$warnings[]='Las fuentes profesionales cambiaron o ya no están vigentes; revisa evidencia actual antes de decidir.';
        if($expired)$warnings[]='La evidencia vinculada venció y requiere actualización por su flujo canónico.';
        if($owners!==[])$warnings[]='El perfil ya tiene un propietario. No se admite un traslado de titularidad.';
        if($competing!==[])$warnings[]='Otra solicitud fue aprobada para este perfil; requiere resolver el conflicto fuera de este flujo.';
        if($o['initial_origin']==='REQUEST_NEW_PROFILE'&&$profile===null&&$matches['candidate_profile_ids']!==[])$warnings[]='Hay posibles coincidencias. Deben resolverse explícitamente antes de crear un perfil; no se reasignará por nombre.';
        if($o['initial_origin']==='CLAIM_EXISTING_PROFILE'&&($matches['ambiguous']||($matches['candidate_profile_ids']!==[]&&$matches['candidate_profile_ids']!==[$profile])))$warnings[]='La información profesional coincide con otro perfil; la aprobación está bloqueada.';
        $eligible=$evidenceReady&&$owners===[]&&$competing===[]&&$warnings===[]&&($o['initial_origin']==='REQUEST_NEW_PROFILE'||$claimable);
        $snapshot=['origin'=>$id,'account'=>$o['account_id'],'account_credential_version'=>(int)$account['credential_version'],'intake_version'=>(int)$o['intake_version'],'review_version'=>(int)($h['resolution_version']??0),
            'evidence'=>$e===null?null:[$e['request_id'],(int)$e['version'],$e['status'],$e['identity_revision_hash'],$e['credential_revision_hash'],$e['valid_until']],
            'files'=>array_map(static fn($v)=>[$v['file_id'],$v['ciphertext_hash'],$v['source_expires_at'],$v['review_expires_at']],$files),'profile'=>$p,'plan'=>$plan,'owners'=>$owners,'matching'=>$matches,'competing'=>$competing,'foreign_bindings'=>$foreignBindings,'foreign_reviews'=>$foreignReviews,'current_source_hashes'=>$sourceHashes,'responses'=>$responses];
        return compact('o','d','h','e','files','profile','p','owners','matches','eligible','warnings','evidenceReady','snapshot');
    }
    private function outcome(array $f):array
    {
        $state=$f['h']['state']??'PENDING_REVIEW';$owner=false;
        foreach($f['owners'] as $row)if($row['account_id']===$f['o']['account_id'])$owner=true;
        return ['request_state'=>$state,'profile_id'=>$f['profile'],'profile_status'=>$f['p']['profile_status']??null,'ownership_status'=>$state==='APPROVED'?($owner?'OWNERSHIP_ACTIVE':'OWNERSHIP_PENDING'):'NOT_APPROVED',
            'ownership_label'=>$state==='APPROVED'?($owner?'Titularidad activada para el solicitante.':'Solicitud aprobada. Titularidad pendiente de acreditación, vínculo profesional y aceptación canónica del solicitante.'):'Sin activación de titularidad.',
            'publication_activated_by_request'=>false,'plan_activated_by_request'=>false,'accreditation_approved_by_request'=>false,'continuation_url'=>'/public/identity/perfil-medico.php?application='.rawurlencode($f['o']['origin_id'])];
    }
    private function publicProfile(?array $p):?array
    {return $p===null?null:['doctor_id'=>$p['doctor_id'],'display_name'=>$p['display_name'],'specialty'=>$p['specialty_primary']??null,'professional_license'=>$p['professional_license']??null,'profile_status'=>$p['profile_status'],'plan'=>$p['plan_code']??null];}
    private function projection(AuthenticatedAccessContext $actor,array $f):array
    {
        $o=$f['o'];$d=$f['d'];$id=$o['origin_id'];$state=$f['h']['state']??'PENDING_REVIEW';$caps=$this->authority->capabilities($actor);$hasEvidence=in_array(RequestAuthority::EVIDENCE,$caps,true);
        $responses=$this->a->query('SELECT * FROM auth_physician_profile_request_responses WHERE origin_id=? ORDER BY created_at,response_id',[$id])->fetchAll(PDO::FETCH_ASSOC);$history=[];
        foreach($this->a->query("SELECT resolution_id,state,reason_ciphertext,key_id,created_at FROM auth_physician_profile_request_decisions WHERE origin_id=? AND state IN ('NEEDS_INFORMATION','REJECTED') ORDER BY resolution_version",[$id])->fetchAll(PDO::FETCH_ASSOC) as $r){
            $reason=$this->cipher->open($r['reason_ciphertext'],'profile-request-reason:'.$r['resolution_id'],$r['key_id']);
            $response=null;foreach($responses as $v)if($v['resolution_id']===$r['resolution_id'])$response=['date'=>$v['created_at'],'text'=>$this->cipher->open($v['response_ciphertext'],'profile-request-response:'.$v['response_id'],$v['key_id'])];
            $history[]=['state'=>$r['state'],'date'=>$r['created_at'],'reason'=>$reason,'response'=>$response];
        }
        $actionable=!in_array($state,['APPROVED','REJECTED'],true);$canResolve=in_array(self::capability($o),$caps,true);
        $actions=$actionable&&$canResolve?['NEEDS_INFORMATION','REJECTED']:[];
        if($actionable&&$canResolve&&$hasEvidence&&$f['eligible'])array_unshift($actions,'APPROVED');
        return ['request_id'=>$id,'application_id'=>$id,'type'=>$o['initial_origin'],'state'=>$state,'submitted_at'=>$this->a->query('SELECT created_at FROM auth_physician_enrollment_intake_revisions WHERE origin_id=? AND intake_version=?',[$id,$o['intake_version']])->fetchColumn(),
            'intake_version'=>(int)$o['intake_version'],'review_version'=>(int)($f['h']['resolution_version']??0),'doctor_id'=>$f['profile'],'label'=>$f['p']['display_name']??trim($d['given_names'].' '.$d['first_surname'].' '.$d['second_surname']),
            'context_label'=>$f['profile']===null?'Solicitud de perfil nuevo':($f['p']['display_name']??'Perfil médico'),'actionable'=>$actionable,
            'applicant'=>['account_id'=>$o['account_id'],'label'=>'Cuenta solicitante '.$o['account_id'],'email_verified'=>true],
            'proposed_profile'=>['display_name'=>trim($d['given_names'].' '.$d['first_surname'].' '.$d['second_surname']),'specialty'=>$d['specialty'],'professional_license'=>$d['professional_license'],'location'=>$d['city'].', '.$d['state']],
            'existing_profile'=>$this->publicProfile($f['p']),'evidence_status'=>$f['e']['status']??'NOT_LINKED','evidence_ready'=>$f['evidenceReady'],'has_evidence_access'=>$hasEvidence,
            'evidence'=>array_map(fn($file)=>['id'=>$file['file_id'],'label'=>$file['kind']==='OFFICIAL_ID'?'Documento de identidad':'Documento profesional','status'=>$f['e']['status'],'mime'=>$file['mime_type'],'url'=>$hasEvidence?'/api/internal/workspace/requests.php?mode=evidence&request_id='.rawurlencode($id).'&file_id='.rawurlencode($file['file_id']):null],$f['files']),
            'warnings'=>$f['warnings'],'information_history'=>$history,'allowed_actions'=>$actions,'outcome'=>$this->outcome($f),'revision_token'=>self::hash($f['snapshot'])];
    }
    public function read(AuthenticatedAccessContext $actor,?string $id=null,?string $doctor=null,string $query=''):array
    {
        if(mb_strlen($query)>120)throw new \InvalidArgumentException('physician_request_invalid');
        return $this->a->transaction($actor,function()use($actor,$id,$doctor,$query){
            $caps=$this->authority->capabilities($actor);
            if(!in_array(RequestAuthority::READ,$caps,true)){if($id!==null||$doctor!==null)throw new \RuntimeException('physician_request_capability_denied');return ['requests'=>[],'pending_count'=>0,'request'=>null,'capabilities'=>[]];}
            $this->authority->assert($actor,RequestAuthority::READ,null,true);
            $rows=$this->a->query("SELECT origin_id FROM auth_physician_enrollment_origins WHERE intake_state='SUBMITTED' AND account_id<>? ORDER BY bound_at DESC,origin_id DESC LIMIT 200",[$actor->accountId()])->fetchAll(PDO::FETCH_COLUMN);
            $list=[];$pending=0;$selected=null;
            foreach($rows as $row){$f=$this->facts($actor,$row);$v=$this->projection($actor,$f);if($v['actionable'])$pending++;
                if($row===$id)$selected=$v;
                if($doctor!==null&&$v['doctor_id']!==$doctor)continue;
                if($query!==''&&!str_contains(mb_strtolower($v['label'].' '.$v['proposed_profile']['display_name'].' '.$v['request_id']),mb_strtolower($query)))continue;
                $list[]=array_intersect_key($v,array_flip(['request_id','type','state','submitted_at','doctor_id','label','context_label','actionable']));
            }
            if($id!==null&&$selected===null){$selected=$this->projection($actor,$this->facts($actor,$id));}
            if($selected!==null&&$doctor!==null&&$selected['doctor_id']!==$doctor)throw new \RuntimeException('physician_request_context_conflict');
            return ['requests'=>$list,'pending_count'=>$pending,'request'=>$selected,'capabilities'=>$caps];
        });
    }
    private function bytes(array $file):string
    {
        foreach(['source_expires_at','review_expires_at'] as $k)if($file[$k]!==null&&new \DateTimeImmutable($file[$k],new \DateTimeZone('UTC'))<=$this->a->clock->now())throw new \RuntimeException('physician_request_evidence_expired');
        $opened=$this->storage->openReadStream($file['storage_key']);$stream=$opened['stream'];
        try{$bytes=stream_get_contents($stream,8*1024*1024+128);}finally{fclose($stream);}
        if(!is_string($bytes)||!hash_equals($file['ciphertext_hash'],hash('sha256',$bytes)))throw new \RuntimeException('physician_request_evidence_integrity');
        return $this->cipher->open($bytes,$file['request_id'].':'.$file['file_id'].':'.$file['kind'],$file['key_id']);
    }
    public function readEvidence(AuthenticatedAccessContext $actor,string $id,string $fileId):array
    {
        return $this->a->transaction($actor,function()use($actor,$id,$fileId){
            $f=$this->facts($actor,$id);$this->authority->assert($actor,RequestAuthority::EVIDENCE,$f['o']['account_id'],true);$file=null;foreach($f['files'] as $v)if($v['file_id']===$fileId)$file=$v;
            if($file===null)throw new \RuntimeException('physician_request_evidence_denied');$bytes=$this->bytes($file);
            $old=$this->a->query('SELECT ciphertext_hash FROM auth_physician_profile_request_reads WHERE origin_id=? AND file_id=? AND actor_account_id=? AND session_id=? AND evidence_version=?',[$id,$fileId,$actor->accountId(),(string)$actor->session()->sessionId(),$f['e']['version']])->fetchColumn();
            if($old===false){$audit=$this->audit->append($actor,$f['o'],'EVIDENCE_READ',(int)$f['e']['version'],$f['e']['status'],$f['e']['status'],profile:$f['profile'],capability:RequestAuthority::EVIDENCE);
                $this->a->query('INSERT INTO auth_physician_profile_request_reads(origin_id,file_id,actor_account_id,session_id,evidence_version,ciphertext_hash,audit_reference,created_at) VALUES(?,?,?,?,?,?,?,?)',[$id,$fileId,$actor->accountId(),(string)$actor->session()->sessionId(),$f['e']['version'],$file['ciphertext_hash'],$audit,$this->a->sqlTime()]);}
            elseif(!hash_equals($old,$file['ciphertext_hash']))throw new \RuntimeException('physician_request_evidence_conflict');
            return ['bytes'=>$bytes,'mime'=>$file['mime_type']];
        });
    }
    private function legal(AuthenticatedAccessContext $actor,array $f,string $decision):void
    {
        if(!in_array($decision,['APPROVED','REJECTED','NEEDS_INFORMATION'],true))throw new \InvalidArgumentException('physician_request_invalid_decision');
        $this->authority->assert($actor,self::capability($f['o']),$f['o']['account_id'],true);
        if(in_array($f['h']['state']??'', ['APPROVED','REJECTED'],true))throw new \RuntimeException('physician_request_state_conflict');
        if($decision==='APPROVED'){
            $this->authority->assert($actor,RequestAuthority::EVIDENCE,$f['o']['account_id'],true);
            if(!$f['eligible'])throw new \RuntimeException('physician_request_approval_conflict');
            foreach($f['files'] as $file){$this->bytes($file);$read=$this->a->query('SELECT ciphertext_hash FROM auth_physician_profile_request_reads WHERE origin_id=? AND file_id=? AND actor_account_id=? AND session_id=? AND evidence_version=?',[$f['o']['origin_id'],$file['file_id'],$actor->accountId(),(string)$actor->session()->sessionId(),$f['e']['version']])->fetchColumn();if($read!==$file['ciphertext_hash'])throw new \RuntimeException('physician_request_evidence_read_required');}
        }
    }
    public function begin(AuthenticatedAccessContext $actor,string $id,int $intakeVersion,int $reviewVersion,string $revision,string $decision,string $reason,string $email,string $password):array
    {
        if(strlen($reason)>2000||!mb_check_encoding($reason,'UTF-8')||preg_match('/[\x00-\x08\x0b-\x1f\x7f<>]/u',$reason)||($decision!=='APPROVED'&&mb_strlen(trim($reason))<8))throw new \InvalidArgumentException('physician_request_reason_required');
        $binding=$this->a->security->reauthenticate($actor,$email,$password,['ip'=>null]);
        return $this->a->transaction($actor,function()use($actor,$id,$intakeVersion,$reviewVersion,$revision,$decision,$reason,$binding){
            $f=$this->facts($actor,$id);if((int)$f['o']['intake_version']!==$intakeVersion||(int)($f['h']['resolution_version']??0)!==$reviewVersion||!hash_equals(self::hash($f['snapshot']),$revision))throw new \RuntimeException('physician_request_revision_conflict');
            $this->legal($actor,$f,$decision);$operation=self::operation($f['o']);
            $this->a->security->assertLive($binding,true);
            $previous=$this->a->query("SELECT * FROM auth_governance_cases WHERE operation=? AND beneficiary_account_id=? AND profile_doctor_id<=>? AND status IN ('OPEN','APPROVED') FOR UPDATE",[$operation,$f['o']['account_id'],$f['profile']])->fetchAll(PDO::FETCH_ASSOC);
            foreach($previous as $old){
                $context=json_decode($old['security_context'],true,32,JSON_THROW_ON_ERROR);
                $expired=new \DateTimeImmutable($old['expires_at'],new \DateTimeZone('UTC'))<=$this->a->clock->now();
                if(($context['contract']??null)!=='WORKSPACE02.1'||(!$expired&&($old['requester_account_id']!==$actor->accountId()||($context['origin_id']??null)!==$id)))throw new \RuntimeException('physician_request_review_conflict');
                // As in PUB-ENR03, explicit reauthentication restarts only this
                // reviewer's unfinished ceremony. Retain the old case and proof;
                // their revoked case can no longer verify or commit.
                $this->a->query("UPDATE auth_governance_cases SET status='REVOKED' WHERE case_id=? AND status IN ('OPEN','APPROVED')",[$old['case_id']]);
                $this->audit->append($actor,$f['o'],'REVIEW_CHALLENGE',$reviewVersion,$old['status'],'REVOKED',$old['case_id'],$f['profile'],self::capability($f['o']));
            }
            $case='prcase_'.bin2hex(random_bytes(16));
            $sealed=$this->cipher->seal($reason,'profile-request-case-reason:'.$case);
            $context=['contract'=>'WORKSPACE02.1','origin_id'=>$id,'intake_version'=>$intakeVersion,'review_version'=>$reviewVersion,'facts_hash'=>$revision,'decision'=>$decision,'reason'=>base64_encode($sealed['bytes']),'reason_key'=>$sealed['key_id'],'reviewer'=>$binding];
            $this->a->query("INSERT INTO auth_governance_cases(case_id,operation_id,operation,requester_account_id,beneficiary_account_id,profile_doctor_id,reason,status,expires_at,security_context) VALUES(?,?,?,?,?,?,?,'OPEN',?,?)",[$case,$this->a->uuid(),$operation,$actor->accountId(),$f['o']['account_id'],$f['profile'],'PHYSICIAN_PROFILE_REQUEST',$this->a->sqlTime($this->a->clock->now()->modify('+5 minutes')),json_encode($context,JSON_THROW_ON_ERROR)]);
            $challenge=$this->mfa->issueChallenge($actor->accountId(),$operation,$case,$f['o']['account_id'],$f['o']['account_id'],$f['profile'],$binding);
            $this->audit->append($actor,$f['o'],'REVIEW_CHALLENGE',$reviewVersion,$f['h']['state']??'PENDING_REVIEW','IN_REVIEW',$case,$f['profile'],self::capability($f['o']));
            return $challenge+['case_id'=>$case,'request_id'=>$id,'decision'=>$decision];
        });
    }
    private function reviewCase(AuthenticatedAccessContext $actor,string $id,bool $fresh=true):array
    {
        $case=$this->a->query("SELECT * FROM auth_governance_cases WHERE case_id=? AND operation IN ('PHYSICIAN_CLAIM_RESOLVE','PHYSICIAN_NEW_PROFILE_RESOLVE') FOR UPDATE",[$id])->fetch(PDO::FETCH_ASSOC);
        if(!is_array($case)||$case['requester_account_id']!==$actor->accountId())throw new \RuntimeException('physician_request_case_denied');
        $c=json_decode($case['security_context'],true,32,JSON_THROW_ON_ERROR);if(($c['contract']??null)!=='WORKSPACE02.1'||($c['reviewer']['session_id']??null)!==(string)$actor->session()->sessionId())throw new \RuntimeException('physician_request_case_denied');
        $o=$this->origin($c['origin_id']);$this->authority->assert($actor,self::capability($o),$o['account_id'],true);
        if($case['beneficiary_account_id']!==$o['account_id']||$case['operation']!==self::operation($o))throw new \RuntimeException('physician_request_case_denied');
        if($fresh)$this->a->security->assertLive($c['reviewer'],true);return [$case,$c,$o];
    }
    public function verify(AuthenticatedAccessContext $actor,string $caseId,string $challengeId,string $challenge,string $code):array
    {
        $this->a->transaction($actor,function()use($actor,$caseId,$challengeId){[$case]=$this->reviewCase($actor,$caseId);if($case['status']!=='OPEN'||$this->a->query('SELECT challenge_id FROM auth_staff_mfa_challenges WHERE challenge_id=? AND case_id=? AND actor_account_id=?',[$challengeId,$caseId,$actor->accountId()])->fetchColumn()!==$challengeId)throw new \RuntimeException('physician_request_case_denied');});
        return $this->mfa->verifyChallenge($actor->accountId(),(string)$actor->session()->sessionId(),$actor->principal()->credentialVersion(),$challengeId,$challenge,$code);
    }
    private function materialize(array $f,string $id):string
    {
        if($f['o']['initial_origin']!=='REQUEST_NEW_PROFILE'||$f['profile']!==null||$f['matches']['candidate_profile_ids']!==[])throw new \RuntimeException('physician_request_materialization_conflict');
        require_once dirname(__DIR__,2).'/profiles/repositories/PrivateProfileRepository.php';
        $profile='pr_'.str_replace('-','',$id);$writer=new \Profiles\Repositories\PrivateProfileRepository($this->a->pdo);
        if($writer->fetchIdentity($profile)!==null)throw new \RuntimeException('physician_request_materialization_conflict');
        $writer->upsertIdentity($profile,['display_name'=>trim($f['d']['given_names'].' '.$f['d']['first_surname'].' '.$f['d']['second_surname'])]);
        $this->a->query("UPDATE profiles_doctors SET professional_license=?,specialty_primary=?,profile_status='hidden',is_public_candidate=0,plan_code='free' WHERE doctor_id=?",[$f['d']['professional_license'],$f['d']['specialty'],$profile]);return $profile;
    }
    public function decide(AuthenticatedAccessContext $actor,string $caseId,string $proofId,string $proof,bool $confirmed):array
    {
        return $this->a->transaction($actor,function()use($actor,$caseId,$proofId,$proof,$confirmed){
            // Acquire before any consistent read establishes an InnoDB snapshot.
            // Independent applications must compare against the prior winner.
            $lock=$this->a->query("SELECT lock_name FROM auth_physician_profile_request_locks WHERE lock_name='profile_materialization' FOR UPDATE")->fetchColumn();
            if($lock!=='profile_materialization')throw new \RuntimeException('physician_request_serialization_unavailable');
            [$case,$c,$o]=$this->reviewCase($actor,$caseId,false);$digest=self::hash([$proofId,$proof,$confirmed]);
            $old=$this->a->query('SELECT * FROM auth_physician_profile_request_decisions WHERE case_id=? FOR UPDATE',[$caseId])->fetch(PDO::FETCH_ASSOC);
            if(is_array($old)){if(!hash_equals($old['command_digest'],$digest))throw new \RuntimeException('physician_request_command_conflict');return ['request_id'=>$o['origin_id'],'state'=>$old['state'],'version'=>(int)$old['resolution_version'],'replayed'=>true,'outcome'=>$this->outcome($this->facts($actor,$o['origin_id']))];}
            $this->a->security->assertLive($c['reviewer'],true);
            if($case['status']!=='OPEN'||new \DateTimeImmutable($case['expires_at'],new \DateTimeZone('UTC'))<=$this->a->clock->now())throw new \RuntimeException('physician_request_case_denied');
            $f=$this->facts($actor,$o['origin_id']);if(!hash_equals(self::hash($f['snapshot']),$c['facts_hash']))throw new \RuntimeException('physician_request_revision_conflict');
            $this->legal($actor,$f,$c['decision']);if(!$confirmed)throw new \RuntimeException('physician_request_confirmation_required');
            $id=$this->a->uuid();$version=(int)($f['h']['resolution_version']??0)+1;$before=$f['h']['state']??'PENDING_REVIEW';
            $this->mfa->consumeProof($this->a->pdo,$proofId,$proof,$actor->accountId(),(string)$actor->session()->sessionId(),$actor->principal()->credentialVersion(),$case['operation'],$caseId,$o['account_id'],$o['account_id'],$case['profile_doctor_id'],$id);
            $profile=$f['profile'];if($c['decision']==='APPROVED'&&$o['initial_origin']==='REQUEST_NEW_PROFILE')$profile=$this->materialize($f,$o['origin_id']);
            $op=match($c['decision']){'APPROVED'=>'REQUEST_APPROVED','REJECTED'=>'REQUEST_REJECTED','NEEDS_INFORMATION'=>'INFORMATION_REQUESTED'};
            $audit=$this->audit->append($actor,$o,$op,$version,$before,$c['decision'],$caseId,$profile,self::capability($o));
            $reason=$this->cipher->open(base64_decode($c['reason'],true),'profile-request-case-reason:'.$caseId,$c['reason_key']);$sealed=$this->cipher->seal($reason,'profile-request-reason:'.$id);
            $this->a->query('INSERT INTO auth_physician_profile_request_decisions(resolution_id,origin_id,account_id,intake_version,resolution_version,previous_state,state,request_type,profile_doctor_id,reviewer_account_id,reviewer_credential_version,case_id,proof_id,facts_hash,command_digest,audit_reference,reason_ciphertext,key_id,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[$id,$o['origin_id'],$o['account_id'],$o['intake_version'],$version,$before,$c['decision'],$o['initial_origin'],$profile,$actor->accountId(),$actor->principal()->credentialVersion(),$caseId,$proofId,$c['facts_hash'],$digest,$audit,$sealed['bytes'],$sealed['key_id'],$this->a->sqlTime()]);
            if($c['decision']==='APPROVED'&&$o['initial_origin']==='REQUEST_NEW_PROFILE'){
                $audit=$this->audit->append($actor,$o,'PROFILE_MATERIALIZED',$version,'ABSENT','HIDDEN_FREE',$caseId,$profile,self::capability($o));
                $this->a->query('INSERT INTO auth_physician_profile_request_profiles(origin_id,account_id,profile_doctor_id,resolution_id,audit_reference,created_at) VALUES(?,?,?,?,?,?)',[$o['origin_id'],$o['account_id'],$profile,$id,$audit,$this->a->sqlTime()]);
            }
            $committedAt=$this->a->sqlTime();
            $this->a->query("UPDATE auth_governance_cases SET status='CONSUMED',approved_at=?,consumed_at=? WHERE case_id=? AND status='OPEN'",[$committedAt,$committedAt,$caseId]);
            return ['request_id'=>$o['origin_id'],'state'=>$c['decision'],'version'=>$version,'resolution_id'=>$id,'replayed'=>false,'outcome'=>$this->outcome($this->facts($actor,$o['origin_id']))];
        });
    }
    public function result(AuthenticatedAccessContext $actor,string $caseId):array
    {
        return $this->a->transaction($actor,function()use($actor,$caseId){[$case,$c,$o]=$this->reviewCase($actor,$caseId,false);$r=$this->a->query('SELECT resolution_id,state,resolution_version FROM auth_physician_profile_request_decisions WHERE case_id=?',[$caseId])->fetch(PDO::FETCH_ASSOC);
            return ['request_id'=>$o['origin_id'],'case_id'=>$caseId,'committed'=>is_array($r),'status'=>is_array($r)?'COMMITTED':($case['status']==='OPEN'?'NOT_COMMITTED':'OUTCOME_PENDING'),'resolution'=>$r?:null,'outcome'=>$this->outcome($this->facts($actor,$o['origin_id']))];});
    }
    public function denial(AuthenticatedAccessContext $actor):void
    { $this->a->transaction($actor,function()use($actor){$this->audit->denied($actor);}); }
    /** Applicant read is exact account-bound and does not grant staff/evidence authority. */
    public function applicantRead(AuthenticatedAccessContext $applicant,string $id):array
    {
        return $this->a->transaction($applicant,function()use($applicant,$id){
            $o=$this->origin($id);$this->a->applicant($applicant->accountId());if($o['account_id']!==$applicant->accountId())throw new \RuntimeException('physician_request_applicant_denied');
            $h=$this->head($id);$items=[];
            foreach($this->a->query("SELECT * FROM auth_physician_profile_request_decisions WHERE origin_id=? AND state IN ('NEEDS_INFORMATION','REJECTED') ORDER BY resolution_version",[$id])->fetchAll(PDO::FETCH_ASSOC) as $v){
                $r=$this->a->query('SELECT * FROM auth_physician_profile_request_responses WHERE resolution_id=?',[$v['resolution_id']])->fetch(PDO::FETCH_ASSOC);
                $items[]=['resolution_id'=>$v['resolution_id'],'state'=>$v['state'],'date'=>$v['created_at'],'reason'=>$this->cipher->open($v['reason_ciphertext'],'profile-request-reason:'.$v['resolution_id'],$v['key_id']),
                  'response'=>is_array($r)?$this->cipher->open($r['response_ciphertext'],'profile-request-response:'.$r['response_id'],$r['key_id']):null];
            }
            $q=$this->a->query('SELECT email_normalized FROM auth_accounts WHERE account_id=?',[$o['account_id']]);
            return ['request_id'=>$id,'type'=>$o['initial_origin'],'account_id'=>$o['account_id'],'email'=>$q->fetchColumn(),'state'=>$h['state']??'PENDING_REVIEW','resolution_id'=>$h['resolution_id']??null,
              'doctor_id'=>$h['profile_doctor_id']??$o['original_profile_doctor_id'],'information_history'=>$items,'can_respond'=>($h['state']??null)==='NEEDS_INFORMATION'&&$this->a->query('SELECT response_id FROM auth_physician_profile_request_responses WHERE resolution_id=?',[$h['resolution_id']])->fetchColumn()===false];
        });
    }
    public function respond(AuthenticatedAccessContext $applicant,string $id,string $resolutionId,string $text):array
    {
        if(mb_strlen(trim($text))<8||strlen($text)>4000||!mb_check_encoding($text,'UTF-8')||preg_match('/[\x00-\x08\x0b-\x1f\x7f<>]/u',$text))throw new \InvalidArgumentException('physician_request_invalid_response');
        return $this->a->transaction($applicant,function()use($applicant,$id,$resolutionId,$text){$o=$this->origin($id);$this->a->applicant($applicant->accountId());if($o['account_id']!==$applicant->accountId())throw new \RuntimeException('physician_request_applicant_denied');$h=$this->head($id);if($h===null||$h['resolution_id']!==$resolutionId||$h['state']!=='NEEDS_INFORMATION')throw new \RuntimeException('physician_request_response_conflict');
            $old=$this->a->query('SELECT * FROM auth_physician_profile_request_responses WHERE resolution_id=?',[$resolutionId])->fetch(PDO::FETCH_ASSOC);if(is_array($old)){if($this->cipher->open($old['response_ciphertext'],'profile-request-response:'.$old['response_id'],$old['key_id'])!==$text)throw new \RuntimeException('physician_request_response_conflict');return ['response_id'=>$old['response_id'],'state'=>'NEEDS_INFORMATION','response_received'=>true,'replayed'=>true];}
            $response=$this->a->uuid();$sealed=$this->cipher->seal($text,'profile-request-response:'.$response);$audit=$this->audit->append($applicant,$o,'INFORMATION_RESPONDED',(int)$h['resolution_version'],'NEEDS_INFORMATION','RESPONSE_RECEIVED',profile:$h['profile_doctor_id']);
            $this->a->query('INSERT INTO auth_physician_profile_request_responses(response_id,origin_id,resolution_id,account_id,response_ciphertext,key_id,audit_reference,created_at) VALUES(?,?,?,?,?,?,?,?)',[$response,$id,$resolutionId,$o['account_id'],$sealed['bytes'],$sealed['key_id'],$audit,$this->a->sqlTime()]);return ['response_id'=>$response,'state'=>'NEEDS_INFORMATION','response_received'=>true,'replayed'=>false];});
    }
}
