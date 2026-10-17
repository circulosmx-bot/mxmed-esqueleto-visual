<?php
declare(strict_types=1);
namespace Identity\Accreditation;

use Identity\Contracts\AuthenticatedAccessContext;
use Identity\Services\StaffMfaService;
use Media\Contracts\PrivateMediaStoragePort;
use PDO;
require_once __DIR__.'/../../media/contracts/PrivateMediaStoragePort.php';

/** Private callable foundation. No HTTP route, admission, membership or clinical writer. */
final class PhysicianEvidenceService implements ProtectedPhysicianEvidencePort
{
    private EvidenceAudit $audit;
    public function __construct(private EvidenceAuthority $a,private EvidenceCipher $cipher,
        private PrivateMediaStoragePort $storage,private StaffMfaService $mfa,
        private ?ProfessionalIssuanceWorkflow $issuanceWorkflow=null)
    {
        if (!$mfa->usesConnection($a->pdo)) throw new \RuntimeException('c4_mfa_connection_mismatch');
        if ($issuanceWorkflow!==null&&!$issuanceWorkflow->usesConnection($a->pdo)) throw new \RuntimeException('physician_workflow_connection_mismatch');
        $this->audit=new EvidenceAudit($a->pdo);
    }

    public function create(AuthenticatedAccessContext $actor,string $origin,?string $doctorId=null,
        ?int $identityId=null,?int $credentialId=null): array
    {
        return $this->a->transaction($actor,function()use($actor,$origin,$doctorId,$identityId,$credentialId):array {
            $this->a->applicant($actor->accountId());$this->a->phone($actor->accountId());
            if (!in_array($origin,['CLAIM_EXISTING_PROFILE','REQUEST_NEW_PROFILE','CONFLICT_OR_TRANSFER_REVIEW'],true))
                throw new \InvalidArgumentException('c4_origin_invalid');
            $s=null;
            if ($origin==='REQUEST_NEW_PROFILE') {
                if ($doctorId!==null||$identityId!==null||$credentialId!==null) throw new \RuntimeException('c4_new_request_has_no_profile_yet');
            } else {
                if ($doctorId===null||$identityId===null||$credentialId===null) throw new \RuntimeException('c4_exact_professional_references_required');
                $s=$this->a->sources($doctorId,$identityId,$credentialId);
            }
            $id=$this->a->uuid();$event=$this->audit->append($actor,$id,'REQUEST_CREATED',1,'ABSENT','DRAFT');
            $this->a->query("INSERT INTO auth_physician_evidence_requests(request_id,account_id,origin,profile_doctor_id,
                verified_identity_id,professional_credential_id,identity_revision_hash,credential_revision_hash,audit_reference,created_at)
                VALUES(?,?,?,?,?,?,?,?,?,?)",[$id,$actor->accountId(),$origin,$doctorId,$identityId,$credentialId,
                $s['identity_hash']??null,$s['credential_hash']??null,$event,$this->a->sqlTime()]);
            return ['request_id'=>$id,'version'=>1,'status'=>'DRAFT','origin'=>$origin];
        });
    }

    /** Explicit internal handoff. All professional references come from the reviewed
     * server resolution; the applicant supplies only their original application. */
    public function createFromEnrollment(AuthenticatedAccessContext $actor,string $originId):array
    {
        if(!preg_match('/^[a-f0-9-]{36}$/D',$originId))throw new \RuntimeException('enrollment_account_denied');
        return $this->a->transaction($actor,function()use($actor,$originId){
            $this->a->applicant($actor->accountId());$this->a->phone($actor->accountId());
            $o=$this->a->query('SELECT origin_id,account_id FROM auth_physician_enrollment_origins WHERE origin_id=? FOR UPDATE',[$originId])->fetch(PDO::FETCH_ASSOC);
            if(!is_array($o)||$o['origin_id']!==$originId||$o['account_id']!==$actor->accountId())throw new \RuntimeException('enrollment_account_denied');
            $resolution=$this->a->query('SELECT resolution_id FROM auth_physician_enrollment_resolutions WHERE origin_id=? ORDER BY resolution_version DESC LIMIT 1 FOR UPDATE',[$originId])->fetchColumn();
            if(!is_string($resolution))throw new \RuntimeException('enrollment_approved_resolution_required');
            $r=(new EnrollmentResolutionAuthority($this->a))->current($resolution);
            $h=$this->a->query('SELECT evidence_request_id,resolution_id FROM auth_physician_enrollment_handoffs WHERE origin_id=? FOR UPDATE',[$originId])->fetch(PDO::FETCH_ASSOC);
            if(is_array($h)){
                if($h['resolution_id']!==$resolution)throw new \RuntimeException('enrollment_handoff_scope_denied');
                $e=$this->a->request($h['evidence_request_id']);(new EnrollmentResolutionAuthority($this->a))->forEvidence($e);
                return ['request_id'=>$e['request_id'],'version'=>(int)$e['version'],'status'=>$e['status'],'replayed'=>true];
            }
            $this->a->query('SELECT doctor_id FROM profiles_doctors WHERE doctor_id=? FOR UPDATE',[$r['resolved_profile_doctor_id']]);
            if($this->a->query("SELECT membership_id FROM auth_account_memberships WHERE profile_doctor_id=? AND role_code='owner' AND status='active' FOR UPDATE",[$r['resolved_profile_doctor_id']])->fetchColumn()!==false
                ||$this->a->query('SELECT binding_id FROM auth_account_professional_bindings WHERE profile_doctor_id=? FOR UPDATE',[$r['resolved_profile_doctor_id']])->fetchColumn()!==false)
                throw new \RuntimeException('c4_existing_owner_conflict');
            $id=$this->a->uuid();$audit=$this->audit->append($actor,$id,'REQUEST_CREATED',1,'ABSENT','DRAFT');
            $this->a->query('INSERT INTO auth_physician_evidence_requests(request_id,account_id,origin,profile_doctor_id,verified_identity_id,professional_credential_id,identity_revision_hash,credential_revision_hash,enrollment_resolution_id,audit_reference,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?)',
                [$id,$actor->accountId(),$r['initial_origin'],$r['resolved_profile_doctor_id'],$r['verified_identity_id'],$r['professional_credential_id'],$r['identity_revision_hash'],$r['credential_revision_hash'],$resolution,$audit,$this->a->sqlTime()]);
            $handoffAudit=$this->audit->resolution($actor,$originId,'RESOLUTION_HANDOFF',(int)$r['resolution_version'],'APPROVED','EVIDENCE_DRAFT',$r['case_id'],false);
            $this->a->query('INSERT INTO auth_physician_enrollment_handoffs(origin_id,resolution_id,evidence_request_id,audit_reference,created_at) VALUES(?,?,?,?,?)',[$originId,$resolution,$id,$handoffAudit,$this->a->sqlTime()]);
            return ['request_id'=>$id,'version'=>1,'status'=>'DRAFT','replayed'=>false];
        });
    }

    public function addDocument(AuthenticatedAccessContext $actor,string $id,int $version,string $kind,string $bytes): array
    {
        if (!in_array($kind,['OFFICIAL_ID','PROFESSIONAL_CREDENTIAL'],true)||strlen($bytes)<1||strlen($bytes)>8*1024*1024)
            throw new \InvalidArgumentException('c4_document_invalid');
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if (!in_array($mime,['application/pdf','image/jpeg','image/png'],true)) throw new \InvalidArgumentException('c4_document_type_invalid');
        return $this->a->transaction($actor,function()use($actor,$id,$version,$kind,$bytes,$mime):array {
            $r=$this->own($actor,$id,$version);
            if ($r['status']!=='DRAFT') throw new \RuntimeException('c4_document_revision_frozen');
            if ((int)$this->a->query('SELECT COUNT(*) FROM auth_physician_evidence_files WHERE request_id=?',[$id])->fetchColumn()>=10)
                throw new \RuntimeException('c4_document_limit');
            $file=$this->a->uuid();$scope=$id.':'.$file.':'.$kind;
            $sealed=$this->cipher->seal($bytes,$scope);
            $key='private/physician-evidence/'.hash('sha256',$actor->accountId()).'/'.$id.'/'.$file.'.enc';
            $temp=tempnam(sys_get_temp_dir(),'mxmed-c4-cipher-');
            if ($temp===false) throw new \RuntimeException('c4_private_staging_unavailable');
            try {
                if (!chmod($temp,0600)||file_put_contents($temp,$sealed['bytes'])!==strlen($sealed['bytes']))
                    throw new \RuntimeException('c4_private_staging_failed');
                $this->storage->storeImmutable($key,$temp);
            } finally { @unlink($temp); }
            // An ambiguous SQL failure retains the encrypted orphan for governed reconciliation.
            // Never delete potentially committed evidence as compensation or invent retention.
            $this->a->query('INSERT INTO auth_physician_evidence_files(file_id,request_id,kind,storage_key,key_id,ciphertext_hash,mime_type,retention_policy_reference,created_at) VALUES(?,?,?,?,?,?,?,?,?)',
                [$file,$id,$kind,$key,$sealed['key_id'],hash('sha256',$sealed['bytes']),$mime,$this->cipher->retentionPolicyReference,$this->a->sqlTime()]);
            $event=$this->audit->append($actor,$id,'FILE_ADDED',$version+1,'DRAFT','DRAFT');
            $this->update($r,['version'=>$version+1,'audit_reference'=>$event]);
            return ['file_id'=>$file,'request_id'=>$id,'version'=>$version+1];
        });
    }

    public function submit(AuthenticatedAccessContext $actor,string $id,int $version): array
    {
        return $this->a->transaction($actor,function()use($actor,$id,$version):array {
            $r=$this->own($actor,$id,$version);
            if ($r['status']!=='DRAFT') throw new \RuntimeException('c4_submission_state_denied');
            $phone=$this->a->phone($actor->accountId());$files=$this->files($id);$this->requireKinds($files);
            if (($r['enrollment_resolution_id']??null)!==null)(new EnrollmentResolutionAuthority($this->a))->forEvidence($r);
            if ($r['origin']!=='REQUEST_NEW_PROFILE'||($r['enrollment_resolution_id']??null)!==null) $this->assertSources($r);
            $event=$this->audit->append($actor,$id,'SUBMITTED',$version+1,'DRAFT','SUBMITTED');
            $this->update($r,['status'=>'SUBMITTED','version'=>$version+1,'phone_version'=>(int)$phone['contact_version'],'audit_reference'=>$event]);
            return ['request_id'=>$id,'version'=>$version+1,'status'=>'SUBMITTED'];
        });
    }

    /** Makes an exact operation/version case, with the existing password + MFA ceremony. */
    public function beginReview(AuthenticatedAccessContext $reviewer,string $id,int $version,string $action,
        string $email,string $password,?string $reason=null): array
    {
        $binding=$this->a->security->reauthenticate($reviewer,$email,$password,['ip'=>null]);
        return $this->a->transaction($reviewer,function()use($reviewer,$id,$version,$action,$binding,$reason):array {
            $r=$this->a->request($id,$version);$this->a->reviewer($reviewer->accountId(),$r['account_id'],$reviewer->principal()->credentialVersion());
            $this->a->security->assertLive($binding,true);
            $this->transitionAllowed($r,$action);
            $reason=$reason??($action==='APPROVED'?'DOCUMENT_RELATIONSHIP_VERIFIED':null);
            $allowedReasons=['APPROVED'=>['DOCUMENT_RELATIONSHIP_VERIFIED'],'REJECTED'=>['DOCUMENT_IDENTITY_MISMATCH','DOCUMENT_UNREADABLE','ACCOUNT_PERSON_MISMATCH','PROFILE_CONFLICT'],
                'SUSPENDED'=>['SECURITY_REVIEW'],'REVOKED'=>['SECURITY_REVOCATION','BENEFICIARY_REQUEST'],'EXPIRED'=>['SOURCE_EXPIRED']];
            if (!in_array($reason,$allowedReasons[$action]??[],true)) throw new \InvalidArgumentException('c4_explicit_review_reason_required');
            if ($action==='APPROVED') $this->a->currentFacts($r,true);
            if ($r['case_id']!==null) $this->a->query("UPDATE auth_governance_cases SET status='REVOKED' WHERE case_id=? AND status IN ('OPEN','APPROVED')",[$r['case_id']]);
            $case='c4case_'.bin2hex(random_bytes(16));
            $context=['contract'=>'C4-EV01','request_id'=>$id,'request_version'=>$version,'action'=>$action,'reason'=>$reason,'reviewer'=>$binding];
            $this->a->query("INSERT INTO auth_governance_cases(case_id,operation_id,operation,requester_account_id,beneficiary_account_id,profile_doctor_id,reason,status,expires_at,security_context)
                VALUES(?,?,'PHYSICIAN_EVIDENCE_REVIEW',?,?,?,'C4_EVIDENCE_REVIEW','OPEN',?,?)",[$case,$this->a->uuid(),$reviewer->accountId(),$r['account_id'],$r['profile_doctor_id'],
                $this->a->sqlTime($this->a->clock->now()->modify('+30 minutes')),json_encode($context,JSON_THROW_ON_ERROR)]);
            $challenge=$this->mfa->issueChallenge($reviewer->accountId(),'PHYSICIAN_EVIDENCE_REVIEW',$case,$r['account_id'],$r['account_id'],$r['profile_doctor_id'],$binding);
            $event=$this->audit->append($reviewer,$id,'REVIEW_CHALLENGE',$version,$r['status'],$r['status'],$case,$reason);
            $this->update($r,['case_id'=>$case,'audit_reference'=>$event]);
            return $challenge+['case_id'=>$case,'request_id'=>$id,'version'=>$version,'action'=>$action];
        });
    }

    /** Binary result for a future authenticated, no-store response; never a URL or storage key. */
    public function readDocument(AuthenticatedAccessContext $actor,string $id,int $version,string $fileId): array
    {
        return $this->a->transaction($actor,function()use($actor,$id,$version,$fileId):array {
            $r=$this->a->request($id,$version);$isReviewer=$actor->accountId()!==$r['account_id'];
            if ($isReviewer) $this->a->reviewer($actor->accountId(),$r['account_id'],$actor->principal()->credentialVersion());
            else $this->a->applicant($actor->accountId());
            $f=$this->a->query('SELECT * FROM auth_physician_evidence_files WHERE file_id=? AND request_id=? FOR UPDATE',[$fileId,$id])->fetch(PDO::FETCH_ASSOC);
            if (!is_array($f)||$f['file_id']!==$fileId) throw new \RuntimeException('c4_document_scope_denied');
            $bytes=$this->documentBytes($f);
            if ($isReviewer&&$r['status']==='SUBMITTED'&&$r['case_id']!==null) {
                $case=$this->case($r);
                if ($case['status']==='OPEN'&&$case['requester_account_id']===$actor->accountId()) {
                    $receipt=['actor'=>$this->a->security->binding($actor),'request_version'=>$version,'ciphertext_hash'=>$f['ciphertext_hash'],'read_at'=>$this->a->sqlTime()];
                    $this->a->query('UPDATE auth_physician_evidence_files SET review_access_context=? WHERE file_id=?',[json_encode($receipt,JSON_THROW_ON_ERROR),$fileId]);
                }
            }
            $this->audit->append($actor,$id,'FILE_READ',$version,$r['status'],$r['status'],$r['case_id'],null,$isReviewer);
            return ['bytes'=>$bytes,'mime_type'=>$f['mime_type'],'headers'=>[
                'Cache-Control'=>'no-store, private, max-age=0','Pragma'=>'no-cache','X-Content-Type-Options'=>'nosniff',
                'Content-Disposition'=>'attachment','Content-Security-Policy'=>"default-src 'none'; sandbox"]];
        });
    }

    /** Approval requires an explicit human attestation AFTER reading every exact document.
     * documentFacts: file UUID => actual source expiry UTC or null (not a fixed annual TTL).
     */
    public function decide(AuthenticatedAccessContext $reviewer,string $id,int $version,string $proofId,string $proof,
        bool $relationshipConfirmed,array $documentFacts=[]): array
    {
        return $this->a->transaction($reviewer,function()use($reviewer,$id,$version,$proofId,$proof,$relationshipConfirmed,$documentFacts):array {
            $r=$this->a->request($id,$version);$this->a->reviewer($reviewer->accountId(),$r['account_id'],$reviewer->principal()->credentialVersion());
            $case=$this->case($r);$context=json_decode($case['security_context'],true,32,JSON_THROW_ON_ERROR);$action=$context['action']??'';
            if (($context['contract']??null)!=='C4-EV01'||($context['request_id']??null)!==$id||($context['request_version']??null)!==$version
                ||$case['requester_account_id']!==$reviewer->accountId()||$case['status']!=='OPEN'
                ||new \DateTimeImmutable($case['expires_at'],new \DateTimeZone('UTC'))<=$this->a->clock->now())
                throw new \RuntimeException('c4_review_case_denied');
            $this->transitionAllowed($r,$action);$this->a->security->assertLive($context['reviewer'],true);
            $this->mfa->consumeProof($this->a->pdo,$proofId,$proof,$reviewer->accountId(),(string)$reviewer->session()->sessionId(),
                $reviewer->principal()->credentialVersion(),'PHYSICIAN_EVIDENCE_REVIEW',$case['case_id'],$r['account_id'],$r['account_id'],$r['profile_doctor_id'],$id.':'.($version+1));
            $expiry=null;
            if ($action==='APPROVED') {
                if (!$relationshipConfirmed) throw new \RuntimeException('c4_human_relationship_attestation_required');
                $this->a->currentFacts($r,true);$files=$this->files($id);$this->requireKinds($files);
                $expected=array_column($files,'file_id');$actual=array_keys($documentFacts);sort($expected);sort($actual);
                if ($expected!==$actual) throw new \RuntimeException('c4_exact_document_review_required');
                foreach ($files as $f) {
                    $receipt=json_decode($f['review_access_context']??'null',true,32,JSON_THROW_ON_ERROR);
                    if (!is_array($receipt)||($receipt['actor']['account_id']??null)!==$reviewer->accountId()
                        ||($receipt['actor']['session_id']??null)!==(string)$reviewer->session()->sessionId()
                        ||($receipt['request_version']??null)!==$version||($receipt['ciphertext_hash']??null)!==$f['ciphertext_hash'])
                        throw new \RuntimeException('c4_actual_document_access_required');
                    $this->documentBytes($f); // Check private availability, key and integrity again.
                    $date=$documentFacts[$f['file_id']];
                    if ($date!==null) {
                        if (!is_string($date)||!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?Z$/D',$date))
                            throw new \InvalidArgumentException('c4_source_expiry_utc_required');
                        $at=new \DateTimeImmutable($date);
                        $errors=\DateTimeImmutable::getLastErrors();
                        if (is_array($errors)&&($errors['warning_count']>0||$errors['error_count']>0))
                            throw new \InvalidArgumentException('c4_source_expiry_utc_required');
                        if ($at<=$this->a->clock->now()) throw new \RuntimeException('c4_reviewed_document_expired');
                        $sql=$this->a->sqlTime($at);if ($expiry===null||$sql<$expiry) $expiry=$sql;
                        $this->a->query('UPDATE auth_physician_evidence_files SET review_expires_at=? WHERE file_id=?',[$sql,$f['file_id']]);
                    }
                }
            }
            if ($action==='EXPIRED'&&($r['valid_until']===null||new \DateTimeImmutable($r['valid_until'],new \DateTimeZone('UTC'))>$this->a->clock->now()))
                throw new \RuntimeException('c4_not_expired');
            $event=$this->audit->append($reviewer,$id,$action,$version+1,$r['status'],$action,$case['case_id'],$context['reason']);
            $fields=['status'=>$action,'version'=>$version+1,'confirmation_hash'=>null,'audit_reference'=>$event,'review_reason'=>$context['reason']];
            if ($action==='APPROVED') $fields+=['reviewer_account_id'=>$reviewer->accountId(),'reviewer_credential_version'=>$reviewer->principal()->credentialVersion(),
                'reviewed_at'=>$this->a->sqlTime(),'reviewed_version'=>$version+1,'review_proof_id'=>$proofId,'review_audit_reference'=>$event,
                'valid_until'=>$expiry];
            $this->update($r,$fields);
            $this->a->query("UPDATE auth_governance_cases SET status='CONSUMED',approved_at=?,consumed_at=? WHERE case_id=? AND status='OPEN'",[$this->a->sqlTime(),$this->a->sqlTime(),$case['case_id']]);
            return ['request_id'=>$id,'version'=>$version+1,'status'=>$action];
        });
    }

    public function issueConfirmation(AuthenticatedAccessContext $actor,string $id,int $version,string $email,string $password): array
    {
        $binding=$this->a->security->reauthenticate($actor,$email,$password,['ip'=>null]);
        return $this->a->transaction($actor,function()use($actor,$id,$version,$binding):array {
            $r=$this->own($actor,$id,$version);
            if ($r['status']!=='APPROVED') throw new \RuntimeException('c4_review_approval_required');
            $this->approvedFacts($r);$this->a->security->assertLive($binding,true);
            $raw=bin2hex(random_bytes(32));$until=$this->a->sqlTime($this->a->clock->now()->modify('+5 minutes'));
            $event=$this->audit->append($actor,$id,'CONFIRMATION_ISSUED',$version,'APPROVED','APPROVED');
            $this->update($r,['confirmation_hash'=>hash('sha256',$raw),'confirmation_context'=>json_encode(['binding'=>$binding,
                'request_version'=>$version,'reviewed_version'=>(int)$r['reviewed_version']],JSON_THROW_ON_ERROR),'confirmation_expires_at'=>$until,'audit_reference'=>$event]);
            return ['request_id'=>$id,'version'=>$version,'confirmation'=>$raw,'expires_at'=>$until];
        });
    }

    public function confirm(AuthenticatedAccessContext $actor,string $id,int $version,string $challenge,bool $accepted): array
    {
        return $this->a->transaction($actor,function()use($actor,$id,$version,$challenge,$accepted):array {
            $r=$this->own($actor,$id,$version);
            if (!$accepted||$r['status']!=='APPROVED'||$r['confirmation_hash']===null||!preg_match('/^[a-f0-9]{64}$/D',$challenge)
                ||!hash_equals($r['confirmation_hash'],hash('sha256',$challenge))||$r['confirmation_expires_at']===null
                ||new \DateTimeImmutable($r['confirmation_expires_at'],new \DateTimeZone('UTC'))<=$this->a->clock->now())
                throw new \RuntimeException('c4_confirmation_denied');
            $context=json_decode($r['confirmation_context'],true,32,JSON_THROW_ON_ERROR);
            if (($context['request_version']??null)!==$version||($context['reviewed_version']??null)!==(int)$r['reviewed_version']
                ||($context['binding']['account_id']??null)!==$actor->accountId()
                ||($context['binding']['session_id']??null)!==(string)$actor->session()->sessionId())
                throw new \RuntimeException('c4_confirmation_binding_denied');
            $this->a->security->assertLive($context['binding'],true);$this->approvedFacts($r);
            $event=$this->audit->append($actor,$id,'CONFIRMED',$version+1,'APPROVED','CONFIRMED');
            $this->update($r,['status'=>'CONFIRMED','version'=>$version+1,'confirmation_hash'=>null,'confirmed_at'=>$this->a->sqlTime(),
                'confirmation_audit_reference'=>$event,'audit_reference'=>$event]);
            $handoff=$this->issuanceWorkflow?->record($this->a->pdo,$this,$id,$version+1);
            return ['request_id'=>$id,'version'=>$version+1,'status'=>'CONFIRMED']+($handoff===null?[]:['issuance_command_id'=>$handoff]);
        });
    }

    public function requireConfirmed(PDO $transaction,string $requestId,int $version,string $accountId,string $doctorId): array
    {
        if ($transaction!==$this->a->pdo||!$transaction->inTransaction()) throw new \RuntimeException('c4_consumer_transaction_required');
        $r=$this->a->request($requestId,$version);
        if ($r['account_id']!==$accountId||$r['profile_doctor_id']!==$doctorId||$r['status']!=='CONFIRMED'||$r['confirmed_at']===null
            ||$r['confirmation_hash']!==null||$r['confirmation_context']===null) throw new \RuntimeException('c4_confirmed_scope_required');
        $this->approvedFacts($r);$context=json_decode($r['confirmation_context'],true,32,JSON_THROW_ON_ERROR);
        if (($context['binding']['account_id']??null)!==$accountId||($context['reviewed_version']??null)!==(int)$r['reviewed_version']
            ||($context['request_version']??0)+1!==$version) throw new \RuntimeException('c4_confirmation_revision_changed');
        // A completed confirmation is durable evidence, not an immortal session. The issuer
        // independently validates its current actor/session and own transaction/operation.
        $this->a->account($accountId,(int)$context['binding']['credential_version']);
        $phone=$this->a->phone($accountId,(int)$r['phone_version']);
        $enrollment=[];
        if(($r['enrollment_resolution_id']??null)!==null){
            $resolution=(new EnrollmentResolutionAuthority($this->a))->forEvidence($r);
            $enrollment=['enrollment_origin_id'=>$resolution['origin_id'],'enrollment_origin_type'=>$resolution['initial_origin'],
                'enrollment_original_profile_id'=>$resolution['original_profile_doctor_id'],'enrollment_intake_version'=>(int)$resolution['intake_version'],
                'enrollment_resolution_version'=>(int)$resolution['resolution_version'],'enrollment_reviewer_account_id'=>$resolution['reviewer_account_id'],
                'enrollment_case_reference'=>$resolution['case_id'],'enrollment_audit_reference'=>$resolution['audit_reference']];
        }
        return array_intersect_key($r,array_flip(['request_id','account_id','origin','profile_doctor_id','verified_identity_id','professional_credential_id','enrollment_resolution_id',
            'identity_revision_hash','credential_revision_hash','version','policy_version','phone_version','case_id','reviewer_account_id','reviewed_at','reviewed_version',
            'review_audit_reference','confirmation_audit_reference','confirmed_at','valid_until','audit_reference']))+['phone_audit_reference'=>$phone['audit_reference']]+$enrollment;
    }

    private function own(AuthenticatedAccessContext $actor,string $id,int $version): array
    {
        $r=$this->a->request($id,$version);
        if ($r['account_id']!==$actor->accountId()) throw new \RuntimeException('c4_applicant_scope_denied');
        $this->a->applicant($actor->accountId());$this->a->phone($actor->accountId());return $r;
    }
    private function assertSources(array $r): void
    {
        $s=$this->a->sources($r['profile_doctor_id'],(int)$r['verified_identity_id'],(int)$r['professional_credential_id']);
        if (!hash_equals($r['identity_revision_hash'],$s['identity_hash'])||!hash_equals($r['credential_revision_hash'],$s['credential_hash']))
            throw new \RuntimeException('c4_professional_revision_changed');
    }
    private function approvedFacts(array $r): void
    {
        $this->a->currentFacts($r);$this->a->reviewer($r['reviewer_account_id'],$r['account_id'],(int)$r['reviewer_credential_version']);
        $case=$this->case($r);$context=json_decode($case['security_context'],true,32,JSON_THROW_ON_ERROR);
        if ($case['status']!=='CONSUMED'||($context['contract']??null)!=='C4-EV01'||($context['request_id']??null)!==$r['request_id']
            ||($context['action']??null)!=='APPROVED'||$case['requester_account_id']!==$r['reviewer_account_id']
            ||($context['request_version']??0)+1!==(int)$r['reviewed_version']) throw new \RuntimeException('c4_review_revision_denied');
        $proof=$this->a->query("SELECT p.* FROM auth_staff_mfa_proofs p JOIN auth_staff_mfa_factors f ON f.factor_id=p.factor_id AND f.status='ACTIVE'
            WHERE p.proof_id=? AND p.status='CONSUMED' FOR UPDATE",[$r['review_proof_id']])->fetch(PDO::FETCH_ASSOC);
        if (!is_array($proof)||$proof['actor_account_id']!==$r['reviewer_account_id']||$proof['case_id']!==$case['case_id']
            ||$proof['operation']!=='PHYSICIAN_EVIDENCE_REVIEW'||$proof['beneficiary_account_id']!==$r['account_id']
            ||$proof['profile_doctor_id']!==$r['profile_doctor_id']||(int)$proof['credential_version']!==(int)$r['reviewer_credential_version']
            ||$proof['consumed_by_reference']!==$r['request_id'].':'.$r['reviewed_version']) throw new \RuntimeException('c4_review_mfa_evidence_denied');
        $files=$this->files($r['request_id']);$this->requireKinds($files);
        foreach ($files as $f) {
            $read=json_decode($f['review_access_context']??'null',true,32,JSON_THROW_ON_ERROR);
            if (!is_array($read)||($read['actor']['account_id']??null)!==$r['reviewer_account_id']
                ||($read['actor']['session_id']??null)!==$proof['staff_session_id']
                ||($read['request_version']??null)!==($context['request_version']??null)||($read['ciphertext_hash']??null)!==$f['ciphertext_hash'])
                throw new \RuntimeException('c4_review_document_evidence_denied');
            if ($f['review_expires_at']!==null&&new \DateTimeImmutable($f['review_expires_at'],new \DateTimeZone('UTC'))<=$this->a->clock->now())
                throw new \RuntimeException('c4_document_expired');
            $this->documentBytes($f);
        }
    }
    private function files(string $id): array
    { return $this->a->query('SELECT * FROM auth_physician_evidence_files WHERE request_id=? ORDER BY file_id FOR UPDATE',[$id])->fetchAll(PDO::FETCH_ASSOC); }
    private function requireKinds(array $files): void
    { if (count(array_unique(array_column($files,'kind')))!==2) throw new \RuntimeException('c4_identity_and_credential_documents_required'); }
    private function case(array $r): array
    {
        $c=$this->a->query("SELECT * FROM auth_governance_cases WHERE case_id=? AND operation='PHYSICIAN_EVIDENCE_REVIEW' FOR UPDATE",[$r['case_id']])->fetch(PDO::FETCH_ASSOC);
        if (!is_array($c)||$c['beneficiary_account_id']!==$r['account_id']||$c['profile_doctor_id']!==$r['profile_doctor_id'])
            throw new \RuntimeException('c4_exact_review_case_required');
        return $c;
    }
    private function documentBytes(array $f): string
    {
        if(($f['professional_source_file_id']??null)!==null)return \Identity\ProfessionalSources\SourceEvidenceReference::open($this->a->pdo,$f,$this->cipher,$this->storage);
        $opened=$this->storage->openReadStream($f['storage_key']);
        try { $ciphertext=stream_get_contents($opened['stream'],8*1024*1024+128); }
        finally { fclose($opened['stream']); }
        if (!is_string($ciphertext)||strlen($ciphertext)!==$opened['bytes']||!hash_equals($f['ciphertext_hash'],hash('sha256',$ciphertext)))
            throw new \RuntimeException('c4_document_integrity_denied');
        return $this->cipher->open($ciphertext,$f['request_id'].':'.$f['file_id'].':'.$f['kind'],$f['key_id']);
    }
    private function transitionAllowed(array $r,string $action): void
    {
        $allowed=['APPROVED'=>['SUBMITTED'],'REJECTED'=>['SUBMITTED'],'SUSPENDED'=>['APPROVED','CONFIRMED'],
            'REVOKED'=>['DRAFT','SUBMITTED','APPROVED','CONFIRMED','SUSPENDED'],'EXPIRED'=>['APPROVED','CONFIRMED','SUSPENDED']];
        if (!in_array($r['status'],$allowed[$action]??[],true)) throw new \RuntimeException('c4_transition_denied');
    }
    private function update(array $r,array $fields): void
    {
        // Field names are exclusively server-owned call-site constants, never request keys.
        $columns=array_map(static fn(string $k):string=>'`'.$k.'`=?',array_keys($fields));
        $q=$this->a->query('UPDATE auth_physician_evidence_requests SET '.implode(',',$columns).' WHERE request_id=? AND version=?',
            [...array_values($fields),$r['request_id'],(int)$r['version']]);
        if ($q->rowCount()!==1) throw new \RuntimeException('c4_request_version_conflict');
    }
}
