<?php
declare(strict_types=1);
namespace Identity\ProfessionalSources;

use Identity\Accreditation\{EvidenceAuthority,EvidenceAudit,EvidenceCipher,PublicEnrollmentService,ProfessionalMatchCandidates};
use Identity\Contracts\AuthenticatedAccessContext;
use Identity\Services\StaffMfaService;
use Media\Contracts\PrivateMediaStoragePort;
use Platform\Contracts\TrustedAuditContext;
use Profiles\Services\{VerifiedPhysicianIdentityService,VerifiedDoctorCredentialService};
use Profiles\Repositories\{VerifiedPhysicianIdentityRepository,DoctorCredentialRepository};
use PDO;

/** Original enrollment only. A document/declared license is never a verified source.
 * AUTHORIZE commits a frozen, encrypted human decision. EXECUTE commits VID01,
 * CRD02, their audit and the result together through an explicit managed port.
 */
final class SourceProvisioningService
{
    public const CAPABILITY='professional_source_provision';
    public const WORKFLOW='professional_source_provisioning';
    private EvidenceAuthority $a;
    private EvidenceAudit $audit;
    public function __construct(private PublicEnrollmentService $enrollment,private EvidenceCipher $cipher,
        private PrivateMediaStoragePort $storage,private StaffMfaService $mfa)
    {
        $this->a=$enrollment->a;$this->audit=new EvidenceAudit($this->a->pdo);
        if(!$mfa->usesConnection($this->a->pdo))throw new \RuntimeException('source_connection_mismatch');
    }
    private static function hash(mixed $v):string{return hash('sha256',json_encode($v,JSON_THROW_ON_ERROR));}
    private function staff(AuthenticatedAccessContext $actor,string $beneficiary):string
    {
        $this->a->scope?->workflow(self::WORKFLOW);
        return self::hash($this->a->sourceReviewer($actor->accountId(),$beneficiary,$actor->principal()->credentialVersion()));
    }
    private function origin(string $id):array
    {
        if(!preg_match('/^[a-f0-9-]{36}$/D',$id))throw new \RuntimeException('source_application_denied');
        $o=$this->a->query('SELECT * FROM auth_physician_enrollment_origins WHERE origin_id=? FOR UPDATE',[$id])->fetch(PDO::FETCH_ASSOC);
        if(!is_array($o)||$o['origin_id']!==$id||$o['intake_state']!=='SUBMITTED'||$o['account_id']===null)throw new \RuntimeException('source_application_denied');
        $this->a->scope?->workflow(self::WORKFLOW);$this->a->scope?->account($o['account_id']);$this->a->applicant($o['account_id']);return $o;
    }
    private function context(AuthenticatedAccessContext $actor,string $id):array
    {
        $o=$this->origin($id);$grant=$this->staff($actor,$o['account_id']);
        $d=$this->enrollment->sourceIntakeInTransaction($actor,$id,(int)$o['intake_version'])['intake'];
        $matches=ProfessionalMatchCandidates::find($this->a,$d['professional_license'],$d['given_names'],$d['first_surname'],$d['specialty'],$d['state'],$d['city']);
        $target=$o['original_profile_doctor_id'];
        if($matches['ambiguous']||($target!==null&&$matches['candidate_profile_ids']!==[]&&$matches['candidate_profile_ids']!==[$target]))throw new \RuntimeException('source_profile_conflict');
        $target??=$matches['candidate_profile_ids'][0]??null;
        if($target===null)throw new \RuntimeException('source_pending_new_profile');
        $this->a->scope?->profile($target);
        $this->a->query('SELECT doctor_id FROM profiles_doctors WHERE doctor_id=? FOR UPDATE',[$target]);
        $this->enrollment->claim($target);
        if($this->a->query("SELECT membership_id FROM auth_account_memberships WHERE profile_doctor_id=? AND role_code='owner' AND status='active' FOR UPDATE",[$target])->fetchColumn()!==false
            ||$this->a->query('SELECT binding_id FROM auth_account_professional_bindings WHERE profile_doctor_id=? FOR UPDATE',[$target])->fetchColumn()!==false)throw new \RuntimeException('source_profile_owned_conflict');
        return ['origin'=>$o,'intake'=>$d,'profile'=>$target,'grant'=>$grant];
    }
    private function request(string $id):array
    {
        $r=$this->a->query('SELECT * FROM auth_professional_source_requests WHERE origin_id=? FOR UPDATE',[$id])->fetch(PDO::FETCH_ASSOC);
        if(!is_array($r))throw new \RuntimeException('source_collection_required');return $r;
    }
    private function checkRequest(array $r,array $c):void
    {
        if($r['account_id']!==$c['origin']['account_id']||$r['profile_doctor_id']!==$c['profile']||(int)$r['intake_version']!==(int)$c['origin']['intake_version']||$r['state']==='REVOKED')throw new \RuntimeException('source_request_stale');
    }
    private function sources(string $profile):array
    {
        $i=$this->a->query('SELECT * FROM profiles_verified_identities WHERE doctor_id=? FOR UPDATE',[$profile])->fetchAll(PDO::FETCH_ASSOC);
        $c=$this->a->query("SELECT * FROM profiles_doctor_credentials WHERE doctor_id=? AND credential_type='PROFESSIONAL' FOR UPDATE",[$profile])->fetchAll(PDO::FETCH_ASSOC);
        if(count($i)>1||count($c)>1)throw new \RuntimeException('source_duplicate_conflict');
        foreach([...$i,...$c] as $v)if($v['source_type']==='synthetic_test'||$v['verified_by_account_id']===null||$v['verified_at']===null||new \DateTimeImmutable($v['verified_at'],new \DateTimeZone('UTC'))>$this->a->clock->now())throw new \RuntimeException('source_existing_unverified_conflict');
        if($c!==[]&&($c[0]['verification_status']!=='VERIFIED'||$c[0]['lifecycle_status']!=='ACTIVE'))throw new \RuntimeException('source_existing_credential_conflict');
        return ['identity'=>$i[0]??null,'credential'=>$c[0]??null];
    }
    private function files(string $id):array{return $this->a->query('SELECT * FROM auth_professional_source_files WHERE origin_id=? ORDER BY kind FOR UPDATE',[$id])->fetchAll(PDO::FETCH_ASSOC);}
    private function fileManifest(string $id):array{return array_map(fn($f)=>[$f['file_id'],$f['kind'],$f['ciphertext_hash']],$this->files($id));}
    private function facts(array $input,array $c,array $baseline):array
    {
        $fields=['given_names','first_surname','second_surname','license_number','professional_area_label','institution_name','identity_source','identity_reference','identity_procedure','credential_source','credential_reference','credential_procedure','verified_at','verification_outcome','relationship_confirmed'];
        if(array_diff(array_keys($input),$fields)!==[]||array_diff($fields,array_keys($input))!==[])throw new \InvalidArgumentException('source_facts_fields_invalid');
        $f=[];foreach($fields as $key){if($key==='relationship_confirmed')continue;$v=$input[$key];if(!is_string($v)||strlen($v)>760||($key!=='second_surname'&&trim($v)==='')||strip_tags($v)!==$v)throw new \InvalidArgumentException('source_verified_facts_required');$f[$key]=trim($v);}
        foreach(['given_names'=>190,'first_surname'=>120,'second_surname'=>120,'license_number'=>64,'professional_area_label'=>190,'institution_name'=>190] as $field=>$limit)
            if(mb_strlen($f[$field],'UTF-8')>$limit)throw new \InvalidArgumentException('source_verified_facts_too_long');
        if($input['relationship_confirmed']!==true||$f['verification_outcome']!=='VERIFIED'||$f['license_number']!==$c['intake']['professional_license'])throw new \RuntimeException('source_human_verification_required');
        foreach(['identity','credential'] as $type){if(!in_array($f[$type.'_procedure'],['OFFICIAL_REGISTRY_HUMAN_CHECK','DIRECT_ISSUING_INSTITUTION_CONFIRMATION'],true)||strlen($f[$type.'_reference'])<8)throw new \InvalidArgumentException('source_trusted_procedure_required');}
        $at=\DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$f['verified_at'],new \DateTimeZone('UTC'));
        if(!$at||$at->format('Y-m-d H:i:s')!==$f['verified_at']||$at>$this->a->clock->now())throw new \InvalidArgumentException('source_actual_verification_date_required');
        if($baseline['identity']!==null)foreach(['given_names','first_surname','second_surname'] as $k)if(($baseline['identity'][$k]??'')!==$f[$k])throw new \RuntimeException('source_identity_conflict');
        if($baseline['credential']!==null)foreach(['license_number','professional_area_label','institution_name'] as $k)if(($baseline['credential'][$k]??'')!==$f[$k])throw new \RuntimeException('source_credential_conflict');
        return $f;
    }
    public function begin(AuthenticatedAccessContext $actor,string $origin,string $action,array $facts,string $email,string $password):array
    {
        if(!in_array($action,['COLLECT','AUTHORIZE','RECOVER','REVOKE'],true))throw new \InvalidArgumentException('source_action_invalid');
        $binding=$this->a->security->reauthenticate($actor,$email,$password,['ip'=>null]);
        return $this->a->transaction($actor,function()use($actor,$origin,$action,$facts,$binding){
            $c=$this->context($actor,$origin);$baseline=$this->sources($c['profile']);$r=$this->a->query('SELECT * FROM auth_professional_source_requests WHERE origin_id=? FOR UPDATE',[$origin])->fetch(PDO::FETCH_ASSOC);
            $payload=['action'=>$action,'origin'=>$origin,'profile'=>$c['profile'],'account'=>$c['origin']['account_id'],'intake_version'=>(int)$c['origin']['intake_version'],'grant'=>$c['grant'],'binding'=>$binding,'baseline_hash'=>self::hash($baseline)];
            if($action==='COLLECT'){
                if(is_array($r)||($baseline['identity']!==null&&$baseline['credential']!==null))throw new \RuntimeException('source_collection_not_missing');
            }else{
                if(!is_array($r))throw new \RuntimeException('source_collection_required');$this->checkRequest($r,$c);$payload['request_version']=(int)$r['version'];
                $old=$this->a->query('SELECT * FROM auth_professional_source_operations WHERE origin_id=? FOR UPDATE',[$origin])->fetch(PDO::FETCH_ASSOC);
                if($action==='AUTHORIZE'){
                    if($r['state']!=='COLLECTING'||is_array($old))throw new \RuntimeException('source_operation_exists');
                    $files=$this->files($origin);if(array_column($files,'kind')!==['OFFICIAL_ID','PROFESSIONAL_CREDENTIAL'])throw new \RuntimeException('source_both_documents_required');
                    foreach($files as $f){$this->bytes($f);$read=$this->a->query('SELECT ciphertext_hash FROM auth_professional_source_reads WHERE file_id=? AND actor_account_id=? AND session_id=? AND request_version=?',[$f['file_id'],$actor->accountId(),(string)$actor->session()->sessionId(),$r['version']])->fetchColumn();if($read!==$f['ciphertext_hash'])throw new \RuntimeException('source_documents_must_be_read');}
                    $payload['facts']=$this->facts($facts,$c,$baseline);$payload['files']=$this->fileManifest($origin);
                }else{
                    if(!is_array($old)||($action==='RECOVER'&&($old['state']!=='AUTHORIZED'||$old['reviewer_account_id']!==$actor->accountId()))||$old['state']==='REVOKED')throw new \RuntimeException('source_recovery_state_denied');
                    $payload['operation_id']=$old['operation_id'];$payload['frozen_hash']=$old['frozen_hash'];
                }
            }
            $case='srccase_'.bin2hex(random_bytes(16));$operation=$this->a->uuid();$sealed=$this->cipher->seal(json_encode($payload,JSON_THROW_ON_ERROR),'ACT03:case:'.$case);
            $ctx=['contract'=>'ACT03.1','payload'=>base64_encode($sealed['bytes']),'key_id'=>$sealed['key_id']];
            $this->a->query("INSERT INTO auth_governance_cases(case_id,operation_id,operation,requester_account_id,beneficiary_account_id,profile_doctor_id,reason,status,expires_at,security_context) VALUES(?,?,'PROFESSIONAL_SOURCE_PROVISION',?,?,?,'PROFESSIONAL_SOURCE_REVIEW','OPEN',?,?)",[$case,$operation,$actor->accountId(),$payload['account'],$payload['profile'],$this->a->sqlTime($this->a->clock->now()->modify('+5 minutes')),json_encode($ctx,JSON_THROW_ON_ERROR)]);
            $challenge=$this->mfa->issueChallenge($actor->accountId(),'PROFESSIONAL_SOURCE_PROVISION',$case,$payload['account'],$payload['account'],$payload['profile'],$binding);
            $this->audit->source($actor,$origin,'CHALLENGE',(int)($r['version']??0),$case);
            return $challenge+['case_id'=>$case,'application_id'=>$origin];
        });
    }
    private function reviewCase(AuthenticatedAccessContext $actor,string $id,bool $expiry=true):array
    {
        $r=$this->a->query("SELECT * FROM auth_governance_cases WHERE case_id=? AND operation='PROFESSIONAL_SOURCE_PROVISION' FOR UPDATE",[$id])->fetch(PDO::FETCH_ASSOC);
        if(!is_array($r)||$r['requester_account_id']!==$actor->accountId())throw new \RuntimeException('source_case_denied');
        $ctx=json_decode($r['security_context'],true,32,JSON_THROW_ON_ERROR);if(($ctx['contract']??null)!=='ACT03.1')throw new \RuntimeException('source_case_denied');
        $f=json_decode($this->cipher->open(base64_decode($ctx['payload'],true),'ACT03:case:'.$id,$ctx['key_id']),true,32,JSON_THROW_ON_ERROR);
        $this->staff($actor,$f['account']);$this->a->scope?->profile($f['profile']);
        if($r['beneficiary_account_id']!==$f['account']||$r['profile_doctor_id']!==$f['profile']||$f['binding']['session_id']!==(string)$actor->session()->sessionId())throw new \RuntimeException('source_case_denied');
        $this->a->security->assertLive($f['binding'],true);if($expiry)$this->a->security->assertUnexpired($r['expires_at']);return [$r,$f];
    }
    public function verify(AuthenticatedAccessContext $actor,string $case,string $challengeId,string $challenge,string $code):array
    {
        $this->a->transaction($actor,function()use($actor,$case,$challengeId){[$c]=$this->reviewCase($actor,$case);if($c['status']!=='OPEN'||$this->a->query("SELECT challenge_id FROM auth_staff_mfa_challenges WHERE challenge_id=? AND case_id=? AND operation='PROFESSIONAL_SOURCE_PROVISION' AND actor_account_id=?",[$challengeId,$case,$actor->accountId()])->fetchColumn()!==$challengeId)throw new \RuntimeException('source_case_denied');});
        return $this->mfa->verifyChallenge($actor->accountId(),(string)$actor->session()->sessionId(),$actor->principal()->credentialVersion(),$challengeId,$challenge,$code);
    }
    private function journal(AuthenticatedAccessContext $actor,string $origin,?string $operation,string $step,string $case):string
    {
        $audit=$this->audit->source($actor,$origin,$step,1,$case);
        $this->a->query('INSERT INTO auth_professional_source_journal(event_id,origin_id,operation_id,step,audit_reference,created_at) VALUES(?,?,?,?,?,?)',[$this->a->uuid(),$origin,$operation,$step,$audit,$this->a->sqlTime()]);return $audit;
    }
    public function authorize(AuthenticatedAccessContext $actor,string $case,string $proofId,string $proof):array
    {
        return $this->a->transaction($actor,function()use($actor,$case,$proofId,$proof){
            [$c,$f]=$this->reviewCase($actor,$case);$current=$this->context($actor,$f['origin']);
            if($f['grant']!==$current['grant']||$f['profile']!==$current['profile']||$f['intake_version']!==(int)$current['origin']['intake_version'])throw new \RuntimeException('source_revision_changed');
            if($c['status']==='CONSUMED'){
                if((json_decode($c['security_context'],true,32,JSON_THROW_ON_ERROR)['command_digest']??null)!==self::hash([$proofId,$proof]))throw new \RuntimeException('source_command_conflict');
                return $this->statusInTransaction($actor,$f['origin'],true);
            }
            if($c['status']!=='OPEN')throw new \RuntimeException('source_case_denied');
            $ref=$f['operation_id']??$c['operation_id'];
            $this->mfa->consumeProof($this->a->pdo,$proofId,$proof,$actor->accountId(),(string)$actor->session()->sessionId(),$actor->principal()->credentialVersion(),'PROFESSIONAL_SOURCE_PROVISION',$case,$f['account'],$f['account'],$f['profile'],$ref);
            $baseline=$this->sources($f['profile']);if($f['baseline_hash']!==self::hash($baseline))throw new \RuntimeException('source_revision_changed');
            if($f['action']==='COLLECT'){
                $audit=$this->audit->source($actor,$f['origin'],'COLLECTION_OPENED',1,$case);
                $this->a->query('INSERT INTO auth_professional_source_requests(origin_id,account_id,profile_doctor_id,intake_version,collection_case_id,audit_reference,created_at) VALUES(?,?,?,?,?,?,?)',[$f['origin'],$f['account'],$f['profile'],$f['intake_version'],$case,$audit,$this->a->sqlTime()]);
                $this->journal($actor,$f['origin'],null,'COLLECTION_OPENED',$case);
            }else{
                $r=$this->request($f['origin']);$this->checkRequest($r,$current);if((int)$r['version']!==$f['request_version'])throw new \RuntimeException('source_revision_changed');
                if($f['action']==='AUTHORIZE'){
                    if($r['state']!=='COLLECTING'||$this->fileManifest($f['origin'])!==$f['files'])throw new \RuntimeException('source_revision_changed');
                    $sealed=$this->cipher->seal(json_encode($f,JSON_THROW_ON_ERROR),'ACT03:operation:'.$ref);
                    $audit=$this->audit->source($actor,$f['origin'],'AUTHORIZED',(int)$r['version'],$case);
                    $this->a->query("INSERT INTO auth_professional_source_operations(operation_id,origin_id,state,frozen_hash,payload_ciphertext,key_id,authorization_case_id,reviewer_account_id,audit_reference,created_at) VALUES(?,?,'AUTHORIZED',?,?,?,?,?,?,?)",[$ref,$f['origin'],self::hash($f),$sealed['bytes'],$sealed['key_id'],$case,$actor->accountId(),$audit,$this->a->sqlTime()]);
                    $this->a->query("UPDATE auth_professional_source_requests SET state='FROZEN' WHERE origin_id=?",[$f['origin']]);$this->journal($actor,$f['origin'],$ref,'AUTHORIZED',$case);
                }else{
                    $op=$this->a->query('SELECT * FROM auth_professional_source_operations WHERE operation_id=? FOR UPDATE',[$ref])->fetch(PDO::FETCH_ASSOC);
                    if(!is_array($op)||$op['origin_id']!==$f['origin']||$op['frozen_hash']!==$f['frozen_hash']||$op['state']==='REVOKED')throw new \RuntimeException('source_recovery_state_denied');
                    $state=$f['action']==='REVOKE'?'REVOKED':'AUTHORIZED';if($state==='AUTHORIZED'&&$op['state']!=='AUTHORIZED')throw new \RuntimeException('source_recovery_state_denied');
                    $this->a->query('UPDATE auth_professional_source_operations SET state=?,authorization_case_id=?,reviewer_account_id=? WHERE operation_id=?',[$state,$case,$actor->accountId(),$ref]);
                    if($state==='REVOKED')$this->a->query("UPDATE auth_professional_source_requests SET state='REVOKED' WHERE origin_id=?",[$f['origin']]);
                    $this->journal($actor,$f['origin'],$ref,$state==='REVOKED'?'REVOKED':'RECOVERY_AUTHORIZED',$case);
                }
            }
            $ctx=json_decode($c['security_context'],true,32,JSON_THROW_ON_ERROR);$ctx['command_digest']=self::hash([$proofId,$proof]);
            $this->a->query("UPDATE auth_governance_cases SET status='CONSUMED',consumed_at=?,approved_at=?,security_context=? WHERE case_id=? AND status='OPEN'",[$this->a->sqlTime(),$this->a->sqlTime(),json_encode($ctx,JSON_THROW_ON_ERROR),$case]);
            return $this->statusInTransaction($actor,$f['origin'],true);
        });
    }
    private function payload(array $op):array
    {
        $f=json_decode($this->cipher->open($op['payload_ciphertext'],'ACT03:operation:'.$op['operation_id'],$op['key_id']),true,32,JSON_THROW_ON_ERROR);
        if(!hash_equals($op['frozen_hash'],self::hash($f)))throw new \RuntimeException('source_frozen_integrity_denied');return $f;
    }
    public function execute(AuthenticatedAccessContext $actor,string $origin):array
    {
        for($attempt=0;$attempt<4;$attempt++){
            try{return $this->executeTransaction($actor,$origin);}
            catch(\Throwable $error){
                // VID01 preserves the PDO cause when wrapping insertion errors.
                // Retry only an aborted serialization transaction. A lost COMMIT
                // acknowledgement is reconciled by a NEW call, never retried here.
                $deadlock=null;
                for($cause=$error;$cause!==null;$cause=$cause->getPrevious())
                    if($cause instanceof \PDOException&&($cause->getCode()==='40001'||(int)($cause->errorInfo[1]??0)===1213)){$deadlock=$cause;break;}
                if($deadlock!==null&&!$this->a->pdo->inTransaction()&&$attempt<3){usleep(10000*($attempt+1));continue;}
                if($deadlock!==null)throw $deadlock;throw $error;
            }
        }
        throw new \RuntimeException('source_execution_not_confirmed');
    }
    private function executeTransaction(AuthenticatedAccessContext $actor,string $origin):array
    {
        return $this->a->transaction($actor,function()use($actor,$origin){
            $o=$this->origin($origin);$this->staff($actor,$o['account_id']);
            $target=$this->a->query('SELECT profile_doctor_id FROM auth_professional_source_requests WHERE origin_id=?',[$origin])->fetchColumn();
            if(!is_string($target))throw new \RuntimeException('source_collection_required');
            $this->a->scope?->profile($target);$this->a->query('SELECT doctor_id FROM profiles_doctors WHERE doctor_id=? FOR UPDATE',[$target]);
            $r=$this->request($origin);
            if($r['account_id']!==$o['account_id']||(int)$r['intake_version']!==(int)$o['intake_version']||$r['state']==='REVOKED')throw new \RuntimeException('source_request_stale');
            $op=$this->a->query('SELECT * FROM auth_professional_source_operations WHERE origin_id=? FOR UPDATE',[$origin])->fetch(PDO::FETCH_ASSOC);
            if(!is_array($op)||$op['state']==='REVOKED')throw new \RuntimeException('source_authorization_required');
            // A completed command is a scoped result read, including after its
            // original C4 handoff. Replaying it must never reopen provisioning.
            if($op['state']==='COMPLETE'){$this->assertResult($op,$r['profile_doctor_id']);return $this->statusInTransaction($actor,$origin,true);}
            $c=$this->context($actor,$origin);$this->checkRequest($r,$c);
            [$case,$authorization]=$this->reviewCase($actor,$op['authorization_case_id']);
            if($case['status']!=='CONSUMED'||!in_array($authorization['action'],['AUTHORIZE','RECOVER'],true))throw new \RuntimeException('source_authorization_required');
            $proof=$this->a->query("SELECT p.proof_id FROM auth_staff_mfa_proofs p JOIN auth_staff_mfa_factors f ON f.factor_id=p.factor_id AND f.status='ACTIVE' WHERE p.case_id=? AND p.actor_account_id=? AND p.operation='PROFESSIONAL_SOURCE_PROVISION' AND p.status='CONSUMED' AND p.consumed_by_reference=? AND p.staff_session_id=? AND p.credential_version=? AND p.target_account_id=? AND p.beneficiary_account_id=? AND p.profile_doctor_id=? FOR UPDATE",[$case['case_id'],$actor->accountId(),$op['operation_id'],(string)$actor->session()->sessionId(),$actor->principal()->credentialVersion(),$c['origin']['account_id'],$c['origin']['account_id'],$c['profile']])->fetchColumn();
            if(!is_string($proof))throw new \RuntimeException('source_authorization_required');
            $f=$this->payload($op);$baseline=$this->sources($c['profile']);
            if(self::hash($baseline)!==$f['baseline_hash']||$c['grant']!==$authorization['grant']||$this->fileManifest($origin)!==$f['files'])throw new \RuntimeException('source_revision_changed');
            foreach($this->files($origin) as $file)$this->bytes($file);
            $facts=$f['facts'];$profile=$c['profile'];$pdo=$this->a->pdo;
            $validate=function()use($actor,$origin,$c,$authorization){$now=$this->context($actor,$origin);if($now['profile']!==$c['profile']||$now['grant']!==$c['grant'])throw new \RuntimeException('source_revision_changed');$this->a->security->assertLive($authorization['binding'],true);};
            $identity=$baseline['identity'];
            if($identity===null){$identity=(new VerifiedPhysicianIdentityService(new VerifiedPhysicianIdentityRepository($pdo)))->provisionFromTrustedAuthority(['doctor_id'=>$profile,'given_names'=>$facts['given_names'],'first_surname'=>$facts['first_surname'],'second_surname'=>$facts['second_surname']],['source_type'=>'internal_provisioning','source_reference'=>'act03:'.$op['operation_id'].':VID01','verified_by_account_id'=>$actor->accountId(),'verified_at'=>$facts['verified_at']]);}
            $this->journal($actor,$origin,$op['operation_id'],'VID01_RECORDED',$case['case_id']);$validate();
            $credential=$baseline['credential'];
            if($credential===null){
                $role=$this->a->query('SELECT governance_class FROM internal_staff WHERE account_id=?',[$actor->accountId()])->fetchColumn();
                $context=TrustedAuditContext::fromServer($actor->accountId(),'account',$role,'professional_source',$this->a->uuid(),$op['operation_id'],(string)$actor->session()->sessionId(),null,null,'AUTH','ACT03/source-provision');
                $credential=(new VerifiedDoctorCredentialService(new DoctorCredentialRepository($pdo)))->provisionInGovernedTransaction(['doctor_id'=>$profile,'credential_type'=>'PROFESSIONAL','license_number'=>$facts['license_number'],'professional_area_label'=>$facts['professional_area_label'],'institution_name'=>$facts['institution_name'],'verification_status'=>'VERIFIED','source_type'=>'internal_provisioning','source_reference'=>'act03:'.$op['operation_id'].':CRD02'],$context,$pdo,$validate);
            }
            $this->journal($actor,$origin,$op['operation_id'],'CRD02_RECORDED',$case['case_id']);$validate();
            $current=$this->sources($profile);$result=['identity_id'=>(string)$identity['verified_identity_id'],'credential_id'=>(string)$credential['credential_id'],'sources_hash'=>self::hash($current)];
            $audit=$this->journal($actor,$origin,$op['operation_id'],'COMPLETE',$case['case_id']);
            $this->a->query("UPDATE auth_professional_source_operations SET state='COMPLETE',result_json=?,result_identity_id=?,result_credential_id=?,audit_reference=?,completed_at=? WHERE operation_id=? AND state='AUTHORIZED'",[json_encode($result,JSON_THROW_ON_ERROR),$result['identity_id'],$result['credential_id'],$audit,$this->a->sqlTime(),$op['operation_id']]);
            $this->a->query("UPDATE auth_professional_source_requests SET state='COMPLETE' WHERE origin_id=?",[$origin]);
            return $this->statusInTransaction($actor,$origin,true);
        });
    }
    public function attachToC4(AuthenticatedAccessContext $actor,string $origin,string $requestId):array
    {
        return $this->a->transaction($actor,function()use($actor,$origin,$requestId){
            $o=$this->origin($origin);$r=$this->request($origin);if($o['account_id']!==$actor->accountId())throw new \RuntimeException('source_reference_scope_denied');
            $op=$this->a->query('SELECT * FROM auth_professional_source_operations WHERE origin_id=? FOR UPDATE',[$origin])->fetch(PDO::FETCH_ASSOC);
            if(!is_array($op))throw new \RuntimeException('source_authorization_required');$this->assertResult($op,$r['profile_doctor_id']);
            $e=$this->a->request($requestId);$h=$this->a->query('SELECT origin_id FROM auth_physician_enrollment_handoffs WHERE evidence_request_id=?',[$requestId])->fetchColumn();
            if($h!==$origin||$e['account_id']!==$actor->accountId()||$e['profile_doctor_id']!==$r['profile_doctor_id'])throw new \RuntimeException('source_reference_scope_denied');
            foreach($this->files($origin) as $f){
                $exists=$this->a->query('SELECT file_id FROM auth_physician_evidence_files WHERE request_id=? AND professional_source_file_id=?',[$requestId,$f['file_id']])->fetchColumn();
                if($exists!==false)continue;
                if($e['status']!=='DRAFT')throw new \RuntimeException('source_C4_reference_frozen');
                $file=$this->a->uuid();$this->a->query('INSERT INTO auth_physician_evidence_files(file_id,request_id,kind,storage_key,key_id,ciphertext_hash,mime_type,retention_policy_reference,created_at,professional_source_file_id) VALUES(?,?,?,?,?,?,?,?,?,?)',[$file,$requestId,$f['kind'],$f['storage_key'],$f['key_id'],$f['ciphertext_hash'],$f['mime_type'],$f['retention_policy_reference'],$this->a->sqlTime(),$f['file_id']]);
                $audit=$this->audit->source($actor,$origin,'C4_REFERENCE_LINKED',(int)$e['version'],null,false);
                $this->a->query("INSERT INTO auth_professional_source_journal(event_id,origin_id,operation_id,step,audit_reference,created_at) VALUES(?,?,?,'C4_REFERENCE_LINKED',?,?)",[$this->a->uuid(),$origin,$op['operation_id'],$audit,$this->a->sqlTime()]);
                $this->a->query('UPDATE auth_physician_evidence_requests SET version=version+1,audit_reference=? WHERE request_id=?',[$audit,$requestId]);
            }
            $e=$this->a->request($requestId);return ['request_id'=>$requestId,'version'=>(int)$e['version'],'status'=>$e['status']];
        });
    }
    private function assertResult(array $op,string $profile):void
    {
        $r=json_decode($op['result_json']??'null',true,32,JSON_THROW_ON_ERROR);$current=$this->sources($profile);
        if($op['state']!=='COMPLETE'||!is_array($r)||self::hash($current)!==($r['sources_hash']??null)
            ||(string)$op['result_identity_id']!==($r['identity_id']??null)||(string)$op['result_credential_id']!==($r['credential_id']??null)
            ||(string)($current['identity']['verified_identity_id']??'')!==($r['identity_id']??null)||(string)($current['credential']['credential_id']??'')!==($r['credential_id']??null))throw new \RuntimeException('source_completed_revision_changed');
    }
    public function upload(AuthenticatedAccessContext $actor,string $origin,int $version,string $kind,string $bytes,string $email,string $password):array
    {
        if(!in_array($kind,['OFFICIAL_ID','PROFESSIONAL_CREDENTIAL'],true)||strlen($bytes)<1||strlen($bytes)>8*1024*1024)throw new \InvalidArgumentException('source_document_invalid');
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);if(!in_array($mime,['image/png','image/jpeg','application/pdf'],true))throw new \InvalidArgumentException('source_document_invalid');
        $binding=$this->a->security->reauthenticate($actor,$email,$password,['ip'=>null]);
        return $this->a->transaction($actor,function()use($actor,$origin,$version,$kind,$bytes,$mime,$binding){
            $o=$this->origin($origin);$r=$this->request($origin);if($o['account_id']!==$actor->accountId()||$r['account_id']!==$actor->accountId()||$r['state']!=='COLLECTING'||(int)$r['version']!==$version||(int)$o['intake_version']!==(int)$r['intake_version'])throw new \RuntimeException('source_document_scope_denied');
            $this->a->security->assertLive($binding,true);$this->a->scope?->profile($r['profile_doctor_id']);
            $file=$this->a->uuid();$sealed=$this->cipher->seal($bytes,'ACT03:file:'.$origin.':'.$file.':'.$kind);$key='private/physician-evidence/'.hash('sha256',$actor->accountId()).'/'.$origin.'/'.$file.'.enc';
            $tmp=tempnam(sys_get_temp_dir(),'mxmed-source-');if($tmp===false)throw new \RuntimeException('source_private_staging_failed');
            try{chmod($tmp,0600);if(file_put_contents($tmp,$sealed['bytes'])!==strlen($sealed['bytes']))throw new \RuntimeException('source_private_staging_failed');$this->storage->storeImmutable($key,$tmp);}finally{@unlink($tmp);}
            // Retain any encrypted orphan after ambiguous SQL outcome; no destructive compensation.
            $this->a->query('INSERT INTO auth_professional_source_files(file_id,origin_id,kind,storage_key,key_id,ciphertext_hash,mime_type,retention_policy_reference,created_at) VALUES(?,?,?,?,?,?,?,?,?)',[$file,$origin,$kind,$key,$sealed['key_id'],hash('sha256',$sealed['bytes']),$mime,$this->cipher->retentionPolicyReference,$this->a->sqlTime()]);
            $this->a->query('UPDATE auth_professional_source_requests SET version=version+1 WHERE origin_id=?',[$origin]);
            $audit=$this->audit->source($actor,$origin,'FILE_ADDED',$version+1,null,false);
            $this->a->query("INSERT INTO auth_professional_source_journal(event_id,origin_id,step,audit_reference,created_at) VALUES(?,?,'FILE_ADDED',?,?)",[$this->a->uuid(),$origin,$audit,$this->a->sqlTime()]);return ['version'=>$version+1];
        });
    }
    private function bytes(array $file):string
    {
        $opened=$this->storage->openReadStream($file['storage_key']);try{$bytes=stream_get_contents($opened['stream'],8*1024*1024+128);}finally{fclose($opened['stream']);}
        if(!is_string($bytes)||strlen($bytes)!==$opened['bytes']||!hash_equals($file['ciphertext_hash'],hash('sha256',$bytes)))throw new \RuntimeException('source_document_integrity_denied');
        return $this->cipher->open($bytes,'ACT03:file:'.$file['origin_id'].':'.$file['file_id'].':'.$file['kind'],$file['key_id']);
    }
    public function document(AuthenticatedAccessContext $actor,string $origin,string $file):array
    {
        return $this->a->transaction($actor,function()use($actor,$origin,$file){$c=$this->context($actor,$origin);$r=$this->request($origin);$this->checkRequest($r,$c);
            $f=$this->a->query('SELECT * FROM auth_professional_source_files WHERE file_id=? AND origin_id=?',[$file,$origin])->fetch(PDO::FETCH_ASSOC);if(!is_array($f))throw new \RuntimeException('source_document_scope_denied');$bytes=$this->bytes($f);
            $audit=$this->audit->source($actor,$origin,'FILE_READ',(int)$r['version']);
            $this->a->query('INSERT IGNORE INTO auth_professional_source_reads(file_id,actor_account_id,session_id,request_version,ciphertext_hash,audit_reference,created_at) VALUES(?,?,?,?,?,?,?)',[$file,$actor->accountId(),(string)$actor->session()->sessionId(),$r['version'],$f['ciphertext_hash'],$audit,$this->a->sqlTime()]);
            return ['bytes'=>$bytes,'mime_type'=>$f['mime_type'],'headers'=>['Cache-Control'=>'no-store, private','Content-Disposition'=>'inline','X-Content-Type-Options'=>'nosniff','Content-Security-Policy'=>"sandbox; default-src 'none'; img-src 'self' blob: data:; style-src 'none'; script-src 'none'; frame-ancestors 'self'"]];
        });
    }
    private function statusInTransaction(AuthenticatedAccessContext $actor,string $origin,bool $staff):array
    {
        $o=$this->origin($origin);if($staff)$this->staff($actor,$o['account_id']);elseif($o['account_id']!==$actor->accountId())throw new \RuntimeException('source_application_denied');
        $r=$this->a->query('SELECT * FROM auth_professional_source_requests WHERE origin_id=?',[$origin])->fetch(PDO::FETCH_ASSOC);
        if(!is_array($r))return ['available'=>true,'state'=>'NOT_REQUESTED','application_id'=>$origin,'files'=>[]];
        $this->a->scope?->profile($r['profile_doctor_id']);$op=$this->a->query('SELECT * FROM auth_professional_source_operations WHERE origin_id=?',[$origin])->fetch(PDO::FETCH_ASSOC);
        $state=$r['state'];if(is_array($op)&&$op['state']==='AUTHORIZED'){try{$f=$this->payload($op);if(self::hash($this->sources($r['profile_doctor_id']))!==$f['baseline_hash']||$this->fileManifest($origin)!==$f['files'])$state='NEEDS_INTERVENTION';}catch(\RuntimeException){$state='NEEDS_INTERVENTION';}}
        if(is_array($op)&&$op['state']==='COMPLETE'){try{$this->assertResult($op,$r['profile_doctor_id']);}catch(\RuntimeException){$state='NEEDS_INTERVENTION';}}
        $out=['available'=>true,'state'=>$state,'application_id'=>$origin,'version'=>(int)$r['version'],'files'=>array_map(fn($f)=>['file_id'=>$f['file_id'],'kind'=>$f['kind']],$this->files($origin))];
        if($staff&&is_array($op))$out['operation_id']=$op['operation_id'];return $out;
    }
    public function status(AuthenticatedAccessContext $actor,string $origin,bool $staff):array
    {return $this->a->transaction($actor,fn()=>$this->statusInTransaction($actor,$origin,$staff));}
    /** Reuse the original workspace without inheriting accreditation-review authority. */
    public function staffQueue(AuthenticatedAccessContext $actor,?string $selected=null):array
    {
        return $this->a->transaction($actor,function()use($actor,$selected){
            $this->staff($actor,'@source-queue');
            [$where,$args]=$this->a->scope?->sqlCohort('o.original_profile_doctor_id','o.account_id')??['1=1',[]];
            $rows=$this->a->query("SELECT o.origin_id,p.display_name FROM auth_physician_enrollment_origins o LEFT JOIN profiles_doctors p ON p.doctor_id=o.original_profile_doctor_id
                WHERE o.intake_state='SUBMITTED' AND o.account_id<>? AND $where ORDER BY o.bound_at DESC,o.origin_id DESC LIMIT 30",array_merge([$actor->accountId()],$args))->fetchAll(PDO::FETCH_ASSOC);
            $queue=array_map(static fn($r)=>['application_id'=>$r['origin_id'],'label'=>$r['display_name']??'Formar parte de México Médico'],$rows);
            $selected??=$rows[0]['origin_id']??null;
            if($selected===null)return ['queue'=>$queue,'application'=>null];
            $o=$this->origin($selected);
            $d=$this->enrollment->sourceIntakeInTransaction($actor,$selected,(int)$o['intake_version'])['intake'];
            $this->a->scope?->profile($o['original_profile_doctor_id']);
            $name=$o['original_profile_doctor_id']===null?null:$this->a->query('SELECT display_name FROM profiles_doctors WHERE doctor_id=?',[$o['original_profile_doctor_id']])->fetchColumn();
            $this->audit->source($actor,$selected,'INTAKE_READ',(int)$o['intake_version']);
            return ['queue'=>$queue,'application'=>['application_id'=>$selected,'intake_version'=>(int)$o['intake_version'],'review_version'=>0,
                'state'=>'PENDING_SOURCES','gate'=>'PENDING_SOURCES','review_allowed'=>false,
                'origin_label'=>$o['initial_origin']==='CLAIM_EXISTING_PROFILE'?'Solicitud del perfil público original':'Formar parte de México Médico',
                'target_name'=>is_string($name)?$name:null,'declared_name'=>trim(implode(' ',[$d['given_names'],$d['first_surname'],$d['second_surname']])),
                'declared_specialty'=>$d['specialty'],'declared_location'=>$d['city'].', '.$d['state']]];
        });
    }
}
