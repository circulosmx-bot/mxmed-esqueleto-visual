<?php
declare(strict_types=1);
namespace Identity\Accreditation;

use Identity\Contracts\AuthenticatedAccessContext;
use Identity\Services\StaffMfaService;
use PDO;

/** Human resolution only. Reuses Identity, exact review capability, MFA and audit.
 * No public profile, verified-source, admission, membership or ownership writes. */
final class ReviewedEnrollmentService
{
    private EvidenceAuthority $a;
    private EvidenceAudit $audit;
    public function __construct(private PublicEnrollmentService $enrollment,private StaffMfaService $mfa,
        private ?\Identity\Repositories\ProfessionalAccountBindingProjection $projection=null,private bool $sourceWorkflow=false)
    {
        $this->a=$enrollment->a;
        if(!$mfa->usesConnection($this->a->pdo))throw new \RuntimeException('enrollment_review_connection_mismatch');
        if($projection!==null&&!$projection->usesConnection($this->a->pdo))throw new \RuntimeException('enrollment_review_connection_mismatch');
        $this->audit=new EvidenceAudit($this->a->pdo);
    }
    public function usesConnection(PDO $pdo):bool{return $this->a->pdo===$pdo;}
    private function head(string $id):?array
    {
        $r=$this->a->query('SELECT * FROM auth_physician_enrollment_resolutions WHERE origin_id=? ORDER BY resolution_version DESC LIMIT 1 FOR UPDATE',[$id])->fetch(PDO::FETCH_ASSOC);
        return is_array($r)?$r:null;
    }
    private static function hash(array $facts):string{return hash('sha256',json_encode($facts,JSON_THROW_ON_ERROR));}
    /** Recomputes matching from the exact encrypted submitted revision, never browser data. */
    private function facts(AuthenticatedAccessContext $actor,string $id,int $version):array
    {
        $this->a->scope?->workflow('enrollment_review');
        ['origin'=>$o,'intake'=>$d]=$this->enrollment->reviewIntakeInTransaction($actor,$id,$version);
        $m=ProfessionalMatchCandidates::find($this->a,$d['professional_license'],$d['given_names'],$d['first_surname'],$d['specialty'],$d['state'],$d['city']);
        sort($m['candidate_profile_ids'],SORT_STRING);
        $target=$o['original_profile_doctor_id'];$gate='PENDING_SOURCES';$sources=null;$i=null;$c=null;$profileHash=null;
        if($m['ambiguous'])$gate='CONFLICT';
        elseif($target!==null&&$m['candidate_profile_ids']!==[]&&$m['candidate_profile_ids']!==[$target])$gate='CONFLICT';
        elseif($target===null&&$m['candidate_profile_ids']===[])$gate='PENDING_NEW_PROFILE';
        else{
            $target=$target??$m['candidate_profile_ids'][0];
            $this->a->scope?->profile($target);
            $this->a->query('SELECT doctor_id FROM profiles_doctors WHERE doctor_id=? FOR UPDATE',[$target]);
            require_once dirname(__DIR__,2).'/profiles/controllers/PublicProfileController.php';
            $dto=(new \Profiles\Controllers\PublicProfileController(new \Profiles\Repositories\PublicProfileRepository($this->a->pdo)))->showByDoctorId($target);
            $profileHash=self::hash([$dto['data']['profile']??null,$dto['data']['plan']??null,$dto['data']['claim']??null]);
            $blocked=($dto['ok']??false)!==true||($dto['data']['profile']['doctor_id']??null)!==$target||(($dto['data']['claim']['claim_allowed']??false)!==true&&!\Identity\PhysicianRequests\MaterializedApplication::allows($this->a->pdo,$o,$target));
            // Known canonical conflict sources must exist; query failures deny the operation.
            $owner=$this->a->query("SELECT membership_id FROM auth_account_memberships WHERE profile_doctor_id=? AND role_code='owner' AND status='active' FOR UPDATE",[$target])->fetchColumn();
            $binding=$this->a->query('SELECT binding_id FROM auth_account_professional_bindings WHERE profile_doctor_id=? FOR UPDATE',[$target])->fetchColumn();
            $reviewed=$this->a->query('SELECT request_id FROM auth_physician_evidence_requests WHERE profile_doctor_id=? AND reviewed_version IS NOT NULL FOR UPDATE',[$target])->fetchColumn();
            if($blocked||$owner!==false||$binding!==false||$reviewed!==false)$gate='CONFLICT';
            else{
                $is=$this->a->query('SELECT verified_identity_id FROM profiles_verified_identities WHERE doctor_id=? FOR UPDATE',[$target])->fetchAll(PDO::FETCH_COLUMN);
                $cs=$this->a->query("SELECT credential_id FROM profiles_doctor_credentials WHERE doctor_id=? AND credential_type='PROFESSIONAL' AND license_number=? AND verification_status='VERIFIED' AND lifecycle_status='ACTIVE' FOR UPDATE",[$target,$d['professional_license']])->fetchAll(PDO::FETCH_COLUMN);
                if(count($is)===1&&count($cs)===1){
                    $i=(int)$is[0];$c=(int)$cs[0];
                    try{$sources=$this->a->sources($target,$i,$c);$gate='READY_FOR_REVIEW';}
                    catch(\RuntimeException $e){if($e->getMessage()!=='c4_professional_sources_denied')throw $e;}
                }
            }
        }
        $account=$this->a->account($o['account_id']);
        return ['origin_id'=>$id,'account_id'=>$o['account_id'],'applicant_credential_version'=>(int)$account['credential_version'],'intake_version'=>$version,'initial_origin'=>$o['initial_origin'],
            'original_profile_doctor_id'=>$o['original_profile_doctor_id'],'resolved_profile_doctor_id'=>$target,
            'matching'=>$m,'gate'=>$gate,'profile_hash'=>$profileHash,'verified_identity_id'=>$i,'professional_credential_id'=>$c,
            'identity_revision_hash'=>$sources['identity_hash']??null,'credential_revision_hash'=>$sources['credential_hash']??null];
    }
    private function legal(?array $head,string $decision,array $facts):void
    {
        $state=$head['state']??'PENDING_REVIEW';
        if(in_array($state,['REJECTED','REVOKED'],true)||($state==='APPROVED'&&$decision!=='REVOKED')||($state!=='APPROVED'&&$decision==='REVOKED'))throw new \RuntimeException('enrollment_review_transition_denied');
        $always=['PENDING_REVIEW','NEEDS_INFORMATION','REJECTED','REVOKED'];
        if(!in_array($decision,[...$always,'APPROVED','PENDING_SOURCES','PENDING_NEW_PROFILE','CONFLICT'],true))throw new \InvalidArgumentException('enrollment_review_decision_invalid');
        if(!in_array($decision,$always,true)&&$decision!==($facts['gate']==='READY_FOR_REVIEW'?'APPROVED':$facts['gate']))throw new \RuntimeException('enrollment_review_gate_denied');
    }
    public function begin(AuthenticatedAccessContext $reviewer,string $id,int $intakeVersion,int $reviewVersion,string $decision,string $email,string $password):array
    {
        $binding=$this->a->security->reauthenticate($reviewer,$email,$password,['ip'=>null]);
        return $this->a->transaction($reviewer,function()use($reviewer,$id,$intakeVersion,$reviewVersion,$decision,$binding){
            $f=$this->facts($reviewer,$id,$intakeVersion);$h=$this->head($id);
            if((int)($h['resolution_version']??0)!==$reviewVersion)throw new \RuntimeException('enrollment_review_version_conflict');
            $this->legal($h,$decision,$f);$this->a->security->assertLive($binding,true);
            $previous=$this->a->query("SELECT * FROM auth_governance_cases WHERE operation='PHYSICIAN_ENROLLMENT_REVIEW' AND beneficiary_account_id=? AND profile_doctor_id<=>? AND status IN ('OPEN','APPROVED') FOR UPDATE",[$f['account_id'],$f['resolved_profile_doctor_id']])->fetchAll(PDO::FETCH_ASSOC);
            foreach($previous as $old){
                $context=json_decode($old['security_context'],true,32,JSON_THROW_ON_ERROR);
                $expired=new \DateTimeImmutable($old['expires_at'],new \DateTimeZone('UTC'))<=$this->a->clock->now();
                if(!$expired&&($old['requester_account_id']!==$reviewer->accountId()||($context['origin_id']??null)!==$id))throw new \RuntimeException('enrollment_review_version_conflict');
                // A fresh explicit reauthentication may restart this reviewer's
                // own unfinished ceremony. Old case/proofs stay historical and unusable.
                $this->a->query("UPDATE auth_governance_cases SET status='REVOKED' WHERE case_id=? AND status IN ('OPEN','APPROVED')",[$old['case_id']]);
            }
            $case='enrcase_'.bin2hex(random_bytes(16));
            $context=['contract'=>'PUB-ENR03.1','origin_id'=>$id,'intake_version'=>$intakeVersion,'expected_review_version'=>$reviewVersion,
                'decision'=>$decision,'facts_hash'=>self::hash($f),'facts'=>$f,'reviewer'=>$binding];
            $this->a->query("INSERT INTO auth_governance_cases(case_id,operation_id,operation,requester_account_id,beneficiary_account_id,profile_doctor_id,reason,status,expires_at,security_context)
                VALUES(?,?,'PHYSICIAN_ENROLLMENT_REVIEW',?,?,?,'ENROLLMENT_PROFILE_REVIEW','OPEN',?,?)",
                [$case,$this->a->uuid(),$reviewer->accountId(),$f['account_id'],$f['resolved_profile_doctor_id'],$this->a->sqlTime($this->a->clock->now()->modify('+5 minutes')),json_encode($context,JSON_THROW_ON_ERROR)]);
            $challenge=$this->mfa->issueChallenge($reviewer->accountId(),'PHYSICIAN_ENROLLMENT_REVIEW',$case,$f['account_id'],$f['account_id'],$f['resolved_profile_doctor_id'],$binding);
            $this->audit->resolution($reviewer,$id,'RESOLUTION_CHALLENGE',$reviewVersion,$h['state']??'PENDING_REVIEW','IN_REVIEW',$case);
            return $challenge+['case_id'=>$case,'application_id'=>$id,'decision'=>$decision];
        });
    }
    private function reviewCase(AuthenticatedAccessContext $reviewer,string $caseId):array
    {
        $case=$this->a->query("SELECT * FROM auth_governance_cases WHERE case_id=? AND operation='PHYSICIAN_ENROLLMENT_REVIEW' FOR UPDATE",[$caseId])->fetch(PDO::FETCH_ASSOC);
        if(!is_array($case)||$case['case_id']!==$caseId||$case['requester_account_id']!==$reviewer->accountId())throw new \RuntimeException('enrollment_review_case_denied');
        $c=json_decode($case['security_context'],true,32,JSON_THROW_ON_ERROR);
        if(($c['contract']??null)!=='PUB-ENR03.1'||($c['reviewer']['session_id']??null)!==(string)$reviewer->session()->sessionId())throw new \RuntimeException('enrollment_review_case_denied');
        $this->a->reviewer($reviewer->accountId(),$case['beneficiary_account_id'],$reviewer->principal()->credentialVersion());
        $this->a->security->assertLive($c['reviewer'],true);
        return [$case,$c];
    }
    public function verify(AuthenticatedAccessContext $reviewer,string $caseId,string $challengeId,string $challenge,string $code):array
    {
        $this->a->transaction($reviewer,function()use($reviewer,$caseId,$challengeId){
            [$case]=$this->reviewCase($reviewer,$caseId);
            if($case['status']!=='OPEN'||$this->a->query("SELECT challenge_id FROM auth_staff_mfa_challenges WHERE challenge_id=? AND case_id=? AND operation='PHYSICIAN_ENROLLMENT_REVIEW' AND actor_account_id=?",[$challengeId,$caseId,$reviewer->accountId()])->fetchColumn()!==$challengeId)throw new \RuntimeException('enrollment_review_case_denied');
        });
        return $this->mfa->verifyChallenge($reviewer->accountId(),(string)$reviewer->session()->sessionId(),$reviewer->principal()->credentialVersion(),$challengeId,$challenge,$code);
    }
    public function decide(AuthenticatedAccessContext $reviewer,string $caseId,string $proofId,string $proof,bool $confirmed):array
    {
        return $this->a->transaction($reviewer,function()use($reviewer,$caseId,$proofId,$proof,$confirmed){
            [$case,$c]=$this->reviewCase($reviewer,$caseId);$digest=hash('sha256',json_encode([$proofId,$proof,$confirmed],JSON_THROW_ON_ERROR));
            $old=$this->a->query('SELECT * FROM auth_physician_enrollment_resolutions WHERE case_id=? FOR UPDATE',[$caseId])->fetch(PDO::FETCH_ASSOC);
            if(is_array($old)){
                if(!hash_equals($old['command_digest'],$digest))throw new \RuntimeException('enrollment_review_command_conflict');
                return ['resolution_id'=>$old['resolution_id'],'state'=>$old['state'],'version'=>(int)$old['resolution_version'],'replayed'=>true];
            }
            if($case['status']!=='OPEN'||new \DateTimeImmutable($case['expires_at'],new \DateTimeZone('UTC'))<=$this->a->clock->now())throw new \RuntimeException('enrollment_review_case_denied');
            $f=$this->facts($reviewer,$c['origin_id'],$c['intake_version']);$h=$this->head($c['origin_id']);
            if((int)($h['resolution_version']??0)!==$c['expected_review_version'])throw new \RuntimeException('enrollment_review_version_conflict');
            if(!hash_equals($c['facts_hash'],self::hash($f)))throw new \RuntimeException('enrollment_review_revision_changed');
            if($case['beneficiary_account_id']!==$f['account_id']||$case['profile_doctor_id']!==$f['resolved_profile_doctor_id'])throw new \RuntimeException('enrollment_review_case_denied');
            $this->legal($h,$c['decision'],$f);
            if($c['decision']==='APPROVED'&&!$confirmed)throw new \RuntimeException('enrollment_human_resolution_required');
            $id=$this->a->uuid();$version=$c['expected_review_version']+1;$before=$h['state']??'PENDING_REVIEW';
            $this->mfa->consumeProof($this->a->pdo,$proofId,$proof,$reviewer->accountId(),(string)$reviewer->session()->sessionId(),$reviewer->principal()->credentialVersion(),
                'PHYSICIAN_ENROLLMENT_REVIEW',$caseId,$f['account_id'],$f['account_id'],$f['resolved_profile_doctor_id'],$id);
            $audit=$this->audit->resolution($reviewer,$f['origin_id'],'RESOLUTION_DECIDED',$version,$before,$c['decision'],$caseId);
            $this->a->query('INSERT INTO auth_physician_enrollment_resolutions(resolution_id,origin_id,account_id,intake_version,resolution_version,previous_state,state,initial_origin,original_profile_doctor_id,resolved_profile_doctor_id,matching_snapshot,facts_hash,verified_identity_id,professional_credential_id,identity_revision_hash,credential_revision_hash,reviewer_account_id,reviewer_credential_version,case_id,proof_id,command_digest,audit_reference,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                [$id,$f['origin_id'],$f['account_id'],$f['intake_version'],$version,$before,$c['decision'],$f['initial_origin'],$f['original_profile_doctor_id'],$f['resolved_profile_doctor_id'],json_encode($f['matching'],JSON_THROW_ON_ERROR),$c['facts_hash'],$f['verified_identity_id'],$f['professional_credential_id'],$f['identity_revision_hash'],$f['credential_revision_hash'],$reviewer->accountId(),$reviewer->principal()->credentialVersion(),$caseId,$proofId,$digest,$audit,$this->a->sqlTime()]);
            $this->a->query("UPDATE auth_governance_cases SET status='CONSUMED',approved_at=?,consumed_at=? WHERE case_id=? AND status='OPEN'",[$this->a->sqlTime(),$this->a->sqlTime(),$caseId]);
            return ['resolution_id'=>$id,'state'=>$c['decision'],'version'=>$version,'replayed'=>false];
        });
    }
    /** Minimized applicant state, no candidates, staff details or protected source references. */
    public function applicant(AuthenticatedAccessContext $actor,string $id):array
    {
        if(!preg_match('/^[a-f0-9-]{36}$/D',$id))throw new \RuntimeException('enrollment_account_denied');
        return $this->a->transaction($actor,function()use($actor,$id){
            $o=$this->a->query('SELECT origin_id,account_id,intake_state FROM auth_physician_enrollment_origins WHERE origin_id=? FOR UPDATE',[$id])->fetch(PDO::FETCH_ASSOC);
            if(!is_array($o)||$o['origin_id']!==$id||$o['account_id']!==$actor->accountId())throw new \RuntimeException('enrollment_account_denied');
            $this->a->applicant($actor->accountId());$r=$this->head($id);$state=$r['state']??($o['intake_state']==='SUBMITTED'?'PENDING_REVIEW':'DRAFT');$available=false;
            if($state==='APPROVED'){try{(new EnrollmentResolutionAuthority($this->a))->current($r['resolution_id']);$available=true;}catch(\RuntimeException){$state='NEEDS_INFORMATION';}}
            $open=$this->a->query("SELECT case_id FROM auth_governance_cases WHERE operation='PHYSICIAN_ENROLLMENT_REVIEW' AND status='OPEN' AND expires_at>? AND JSON_UNQUOTE(JSON_EXTRACT(security_context,'$.origin_id'))=? AND CAST(JSON_UNQUOTE(JSON_EXTRACT(security_context,'$.expected_review_version')) AS UNSIGNED)=? LIMIT 1",[$this->a->sqlTime(),$id,(int)($r['resolution_version']??0)])->fetchColumn();
            if($open!==false&&!in_array($state,['APPROVED','REJECTED','REVOKED'],true))$state='IN_REVIEW';
            if($available){
                $e=$this->a->query('SELECT e.request_id,e.status FROM auth_physician_enrollment_handoffs h JOIN auth_physician_evidence_requests e ON e.request_id=h.evidence_request_id WHERE h.origin_id=? AND h.resolution_id=? FOR UPDATE',[$id,$r['resolution_id']])->fetch(PDO::FETCH_ASSOC);
                if(is_array($e)){
                    $state=match($e['status']){'DRAFT'=>'ACCREDITATION_DRAFT','SUBMITTED'=>'IN_REVIEW','APPROVED'=>'APPROVED_PENDING_CONFIRMATION','CONFIRMED'=>'CONFIRMED',default=>'NEEDS_INFORMATION'};
                    $binding=$this->a->query("SELECT binding_id FROM auth_account_professional_bindings WHERE evidence_request_id=? AND status='ACTIVE' FOR UPDATE",[$e['request_id']])->fetchColumn();
                    if($binding!==false){
                        try{if($this->projection===null)throw new \RuntimeException('professional_projection_unavailable');$this->projection->requireCurrent($this->a->pdo,$actor->accountId(),$r['resolved_profile_doctor_id']);$state='ACCREDITATION_COMPLETED';}
                        catch(\RuntimeException){$state='NEEDS_INFORMATION';}
                    }
                }
            }
            $name=$available?$this->a->query('SELECT display_name FROM profiles_doctors WHERE doctor_id=?',[$r['resolved_profile_doctor_id']])->fetchColumn():null;
            return ['state'=>$state,'resolved_profile_name'=>is_string($name)?$name:null,'handoff_available'=>$available];
        });
    }
    public function staff(AuthenticatedAccessContext $actor,?string $selected=null):array
    {
        return $this->a->transaction($actor,function()use($actor,$selected){
            // A capability check even when the queue is empty. No broad staff fallback.
            $this->a->reviewer($actor->accountId(),'@enrollment-queue',$actor->principal()->credentialVersion());
            [$where,$args]=$this->a->scope?->sqlCohort('o.original_profile_doctor_id','o.account_id')??['1=1',[]];
            $rows=$this->a->query("SELECT o.origin_id,o.intake_version,o.initial_origin,p.display_name FROM auth_physician_enrollment_origins o LEFT JOIN profiles_doctors p ON p.doctor_id=o.original_profile_doctor_id WHERE o.intake_state='SUBMITTED' AND o.account_id<>? AND $where ORDER BY o.bound_at DESC,o.origin_id DESC LIMIT 30",array_merge([$actor->accountId()],$args))->fetchAll(PDO::FETCH_ASSOC);
            $queue=array_map(static fn($r)=>['application_id'=>$r['origin_id'],'label'=>$r['display_name']??'Formar parte de México Médico'],$rows);
            $selected=$selected??($rows[0]['origin_id']??null);if($selected===null)return ['queue'=>$queue,'application'=>null];
            $row=$this->a->query('SELECT intake_version FROM auth_physician_enrollment_origins WHERE origin_id=? FOR UPDATE',[$selected])->fetch(PDO::FETCH_ASSOC);
            if(!is_array($row))throw new \RuntimeException('enrollment_not_found');
            $f=$this->facts($actor,$selected,(int)$row['intake_version']);$h=$this->head($selected);
            $declared=$this->enrollment->reviewIntakeInTransaction($actor,$selected,(int)$row['intake_version'])['intake'];
            $this->audit->enrollment($actor,$selected,'MATCH_CANDIDATES_READ',$f['intake_version'],'SUBMITTED','SUBMITTED');
            $name=$f['resolved_profile_doctor_id']===null?null:$this->a->query('SELECT display_name FROM profiles_doctors WHERE doctor_id=?',[$f['resolved_profile_doctor_id']])->fetchColumn();
            $current=null;
            if(($h['state']??null)==='APPROVED'){
                try{(new EnrollmentResolutionAuthority($this->a))->current($h['resolution_id']);$current=true;}
                catch(\RuntimeException){$current=false;}
            }
            return ['queue'=>$queue,'application'=>['application_id'=>$selected,'intake_version'=>$f['intake_version'],'review_version'=>(int)($h['resolution_version']??0),'state'=>$h['state']??'PENDING_REVIEW','gate'=>$f['gate'],
                'resolution_current'=>$current,
                'source_readiness'=>\Identity\Operational\ProfessionalSourceReadiness::inspect($this->a,$f['resolved_profile_doctor_id'],$actor->accountId(),$this->sourceWorkflow),
                'origin_label'=>$f['initial_origin']==='CLAIM_EXISTING_PROFILE'?'Solicitud del perfil público original':'Formar parte de México Médico','target_name'=>is_string($name)?$name:null,
                'declared_name'=>trim(implode(' ',[$declared['given_names'],$declared['first_surname'],$declared['second_surname']])),
                'declared_specialty'=>$declared['specialty'],'declared_location'=>$declared['city'].', '.$declared['state']]];
        });
    }
}
