<?php
declare(strict_types=1);
namespace Identity\Accreditation;

use Identity\Contracts\AuthenticatedAccessContext;
use Identity\Services\StaffMfaService;
use PDO;

/** The single admission/binding writer. Private callable source; no runtime route. */
final class ProfessionalAuthorityIssuer
{
    private EvidenceAudit $audit;
    public function __construct(private EvidenceAuthority $a,private ProtectedPhysicianEvidencePort $evidence,private StaffMfaService $mfa)
    {
        if (!$mfa->usesConnection($a->pdo)) throw new \RuntimeException('physician_mfa_connection_mismatch');
        $this->audit=new EvidenceAudit($a->pdo);
    }

    /** Optional pending checkpoint; consumes no new documents/review/acceptance. */
    public function stage(AuthenticatedAccessContext $applicant,string $commandId):array
    {return $this->run($applicant,$commandId,false);}

    /** Atomic admission approval + binding activation, including staging if absent. */
    public function issue(AuthenticatedAccessContext $applicant,string $commandId):array
    {return $this->run($applicant,$commandId,true);}

    private function run(AuthenticatedAccessContext $actor,string $command,bool $activate):array
    {
        return $this->a->transaction($actor,function()use($actor,$command,$activate):array {
            $w=$this->workflow($command);
            if ($w['account_id']!==$actor->accountId()) throw new \RuntimeException('physician_issuer_account_scope_denied');
            // Only this immutable server handoff supplies request/version/account/profile.
            $r=$this->evidence->requireConfirmed($this->a->pdo,$w['evidence_request_id'],(int)$w['evidence_version'],$actor->accountId(),$w['profile_doctor_id']);
            if ($r['origin']!=='CLAIM_EXISTING_PROFILE'||!hash_equals($w['payload_hash'],ProfessionalIssuanceWorkflow::payloadHash($r)))
                throw new \RuntimeException('physician_command_payload_conflict');
            $result=$w['staged_result']===null?null:json_decode($w['staged_result'],true,32,JSON_THROW_ON_ERROR);
            if ($result===null) {
                // Serialize profile/identity claims; do not adopt another command's authority.
                $this->a->query('SELECT doctor_id FROM profiles_doctors WHERE doctor_id=? FOR UPDATE',[$r['profile_doctor_id']]);
                if ($this->a->query("SELECT admission_id FROM profiles_physician_admissions WHERE doctor_id=? AND status IN ('PENDING','APPROVED','SUSPENDED') FOR UPDATE",[$r['profile_doctor_id']])->fetchColumn()!==false)
                    throw new \RuntimeException('physician_effective_admission_exists');
                if ($this->a->query("SELECT binding_id FROM auth_account_professional_bindings WHERE (profile_doctor_id=? OR verified_identity_id=?) AND status IN ('PENDING','ACTIVE','SUSPENDED') FOR UPDATE",[$r['profile_doctor_id'],$r['verified_identity_id']])->fetchColumn()!==false)
                    throw new \RuntimeException('physician_effective_binding_exists');
                // Terminal history is not a reusable approval. Re-review needs a NEW C4 request.
                if ($this->a->query('SELECT admission_id FROM profiles_physician_admissions WHERE evidence_request_id=? FOR UPDATE',[$r['request_id']])->fetchColumn()!==false)
                    throw new \RuntimeException('physician_evidence_already_issued');
                $admission=$this->a->uuid();$binding=$this->a->uuid();$now=$this->a->sqlTime();
                $ea=$this->audit->authority($actor,$admission,'ADMISSION_STAGED',1,'ABSENT','PENDING',$r['case_id']);
                $eb=$this->audit->authority($actor,$binding,'BINDING_STAGED',1,'ABSENT','PENDING',$r['case_id']);
                $this->a->query("INSERT INTO profiles_physician_admissions(admission_id,doctor_id,verified_identity_id,professional_credential_id,
                    status,reviewed_by_account_id,case_reference,decision_reference,audit_reference,evidence_reference,approved_at,valid_from,valid_until,
                    account_id,evidence_request_id,evidence_version) VALUES(?,?,?,?,'PENDING',?,?,?,?,?,?,?,?,?,?,?)",
                    [$admission,$r['profile_doctor_id'],$r['verified_identity_id'],$r['professional_credential_id'],$r['reviewer_account_id'],$r['case_id'],
                    $r['review_audit_reference'],$ea,$r['request_id'].':'.$r['version'],$r['reviewed_at'],$now,$r['valid_until'],$r['account_id'],$r['request_id'],$r['version']]);
                $this->a->query("INSERT INTO auth_account_professional_bindings(binding_id,account_id,profile_doctor_id,admission_id,admission_version,
                    verified_identity_id,identity_revision_hash,credential_revision_hash,status,relationship_evidence_reference,acceptance_reference,
                    audit_reference,valid_from,valid_until,evidence_request_id,evidence_version) VALUES(?,?,?,?,1,?,?,?,'PENDING',?,?,?,?,?,?,?)",
                    [$binding,$r['account_id'],$r['profile_doctor_id'],$admission,$r['verified_identity_id'],$r['identity_revision_hash'],$r['credential_revision_hash'],
                    $r['review_audit_reference'],$r['confirmation_audit_reference'],$eb,$now,$r['valid_until'],$r['request_id'],$r['version']]);
                foreach ([['ADMISSION',$admission,$ea],['BINDING',$binding,$eb]] as [$type,$id,$event])
                    $this->history($actor,$type,$id,1,'ABSENT','PENDING',$r,$r['case_id'],$r['review_audit_reference'],$event,$command.':stage');
                $result=['admission_id'=>$admission,'binding_id'=>$binding,'admission_version'=>1,'binding_version'=>1,'status'=>'PENDING'];
                $this->a->query('UPDATE auth_physician_issuance_workflows SET staged_result=? WHERE command_id=? AND staged_result IS NULL',[json_encode($result,JSON_THROW_ON_ERROR),$command]);
            }
            $admission=$this->row('ADMISSION',$result['admission_id']);$binding=$this->row('BINDING',$result['binding_id']);
            $this->exactPair($admission,$binding,$w);
            if ($w['issued_result']!==null) {
                $issued=json_decode($w['issued_result'],true,32,JSON_THROW_ON_ERROR);
                if ($admission['status']!=='APPROVED'||$binding['status']!=='ACTIVE'
                    ||(int)$admission['authority_version']!==$issued['admission_version']||(int)$binding['authority_version']!==$issued['binding_version'])
                    throw new \RuntimeException('physician_issued_authority_changed');
                return $issued; // Exact replay; no new audit/event/state mutation.
            }
            if ($admission['status']!=='PENDING'||$binding['status']!=='PENDING'||(int)$admission['authority_version']!==1||(int)$binding['authority_version']!==1)
                throw new \RuntimeException('physician_pending_authority_changed');
            if (!$activate) return $result;
            $ea=$this->audit->authority($actor,$admission['admission_id'],'ADMISSION_APPROVED',2,'PENDING','APPROVED',$r['case_id']);
            $eb=$this->audit->authority($actor,$binding['binding_id'],'BINDING_ACTIVE',2,'PENDING','ACTIVE',$r['case_id']);
            $this->update('ADMISSION',$admission,'APPROVED',$ea);
            $this->update('BINDING',$binding,'ACTIVE',$eb,2);
            foreach ([['ADMISSION',$admission['admission_id'],$ea,'APPROVED'],['BINDING',$binding['binding_id'],$eb,'ACTIVE']] as [$type,$id,$event,$state])
                $this->history($actor,$type,$id,2,'PENDING',$state,$r,$r['case_id'],$r['review_audit_reference'],$event,$command.':issue');
            $result=['admission_id'=>$admission['admission_id'],'binding_id'=>$binding['binding_id'],'admission_version'=>2,'binding_version'=>2,'status'=>'ACTIVE'];
            $this->a->query('UPDATE auth_physician_issuance_workflows SET issued_result=? WHERE command_id=? AND issued_result IS NULL',[json_encode($result,JSON_THROW_ON_ERROR),$command]);
            return $result;
        });
    }

    /** A lifecycle decision, not a second accreditation review. Case ID is its idempotency key. */
    public function beginChange(AuthenticatedAccessContext $staff,string $type,string $id,int $expectedVersion,string $action,
        string $reason,string $email,string $password):array
    {
        $reauth=$this->a->security->reauthenticate($staff,$email,$password,['ip'=>null]);
        return $this->a->transaction($staff,function()use($staff,$type,$id,$expectedVersion,$action,$reason,$reauth):array {
            $row=$this->row($type,$id);$this->a->reviewer($staff->accountId(),$row['account_id'],$staff->principal()->credentialVersion());
            $this->legal($type,$row,$expectedVersion,$action,$reason);$this->a->security->assertLive($reauth,true);
            $case='c4authority_'.bin2hex(random_bytes(16));$payload=['contract'=>'C4-ADM02-IMP02','type'=>$type,'id'=>$id,'expected_version'=>$expectedVersion,
                'action'=>$action,'reason'=>$reason,'account_id'=>$row['account_id'],'profile_doctor_id'=>$row['profile_doctor_id']??$row['doctor_id'],
                'evidence_request_id'=>$row['evidence_request_id'],'evidence_version'=>(int)$row['evidence_version'],'actor'=>$reauth];
            $this->a->query("INSERT INTO auth_governance_cases(case_id,operation_id,operation,requester_account_id,beneficiary_account_id,profile_doctor_id,
                reason,status,expires_at,security_context) VALUES(?,?,'PHYSICIAN_AUTHORITY_CHANGE',?,?,?,'C4_AUTHORITY_LIFECYCLE','OPEN',?,?)",
                [$case,$this->a->uuid(),$staff->accountId(),$payload['account_id'],$payload['profile_doctor_id'],$this->a->sqlTime($this->a->clock->now()->modify('+5 minutes')),json_encode($payload,JSON_THROW_ON_ERROR)]);
            $challenge=$this->mfa->issueChallenge($staff->accountId(),'PHYSICIAN_AUTHORITY_CHANGE',$case,$payload['account_id'],$payload['account_id'],$payload['profile_doctor_id'],$reauth);
            $this->audit->authority($staff,$id,'CHANGE_CHALLENGE',$expectedVersion,$row['status'],$row['status'],$case,$reason,true);
            return $challenge+['case_id'=>$case];
        });
    }

    /** Browser supplies only the existing case and its proof, never authority payload fields. */
    public function completeChange(AuthenticatedAccessContext $staff,string $caseId,string $proofId,string $proof):array
    {
        return $this->a->transaction($staff,function()use($staff,$caseId,$proofId,$proof):array {
            $case=$this->a->query("SELECT * FROM auth_governance_cases WHERE case_id=? AND operation='PHYSICIAN_AUTHORITY_CHANGE' FOR UPDATE",[$caseId])->fetch(PDO::FETCH_ASSOC);
            if (!is_array($case)||$case['case_id']!==$caseId||$case['requester_account_id']!==$staff->accountId()) throw new \RuntimeException('physician_change_case_denied');
            $c=json_decode($case['security_context'],true,32,JSON_THROW_ON_ERROR);
            if (($c['contract']??null)!=='C4-ADM02-IMP02'||($c['actor']['account_id']??null)!==$staff->accountId()
                ||($c['actor']['session_id']??null)!==(string)$staff->session()->sessionId()
                ||$case['beneficiary_account_id']!==$c['account_id']||$case['profile_doctor_id']!==$c['profile_doctor_id'])
                throw new \RuntimeException('physician_change_scope_denied');
            $this->a->reviewer($staff->accountId(),$c['account_id'],$staff->principal()->credentialVersion());
            $this->a->security->assertLive($c['actor'],true);
            $row=$this->row($c['type'],$c['id']);
            if ($row['account_id']!==$c['account_id']||($row['profile_doctor_id']??$row['doctor_id'])!==$c['profile_doctor_id']
                ||$row['evidence_request_id']!==$c['evidence_request_id']||(int)$row['evidence_version']!==$c['evidence_version'])
                throw new \RuntimeException('physician_change_scope_denied');
            $proofHash=hash('sha256',$proofId.':'.$proof);
            if ($case['status']==='CONSUMED') {
                if (($c['consumed_proof_hash']??null)!==$proofHash) throw new \RuntimeException('physician_command_payload_conflict');
                return $c['result']; // Historical receipt; never reactivates an authority.
            }
            if ($case['status']!=='OPEN'||new \DateTimeImmutable($case['expires_at'],new \DateTimeZone('UTC'))<=$this->a->clock->now())
                throw new \RuntimeException('physician_change_case_denied');
            $this->legal($c['type'],$row,$c['expected_version'],$c['action'],$c['reason']);
            $this->mfa->consumeProof($this->a->pdo,$proofId,$proof,$staff->accountId(),(string)$staff->session()->sessionId(),
                $staff->principal()->credentialVersion(),'PHYSICIAN_AUTHORITY_CHANGE',$caseId,$c['account_id'],$c['account_id'],$c['profile_doctor_id'],$caseId);
            $version=$c['expected_version']+1;
            $event=$this->audit->authority($staff,$c['id'],$c['type'].'_'.$c['action'],$version,$row['status'],$c['action'],$caseId,$c['reason'],true);
            $this->update($c['type'],$row,$c['action'],$event);
            $this->history($staff,$c['type'],$c['id'],$version,$row['status'],$c['action'],
                ['request_id'=>$c['evidence_request_id'],'version'=>$c['evidence_version']],$caseId,$proofId,$event,$caseId);
            $c['result']=['authority_type'=>$c['type'],'authority_id'=>$c['id'],'version'=>$version,'status'=>$c['action'],'audit_reference'=>$event];
            $c['consumed_proof_hash']=$proofHash;
            $now=$this->a->sqlTime();
            $this->a->query("UPDATE auth_governance_cases SET status='CONSUMED',approved_at=?,consumed_at=?,security_context=? WHERE case_id=? AND status='OPEN'",
                [$now,$now,json_encode($c,JSON_THROW_ON_ERROR),$caseId]);
            return $c['result'];
        });
    }

    private function workflow(string $id):array
    {
        $w=$this->a->query('SELECT * FROM auth_physician_issuance_workflows WHERE command_id=? FOR UPDATE',[$id])->fetch(PDO::FETCH_ASSOC);
        if (!is_array($w)||$w['command_id']!==$id) throw new \RuntimeException('physician_trusted_workflow_required');
        return $w;
    }
    private function table(string $type):array
    {return match($type){'ADMISSION'=>['profiles_physician_admissions','admission_id'],'BINDING'=>['auth_account_professional_bindings','binding_id'],default=>throw new \InvalidArgumentException('physician_authority_type_invalid')};}
    private function row(string $type,string $id):array
    {
        [$table,$key]=$this->table($type);$r=$this->a->query("SELECT * FROM $table WHERE $key=? FOR UPDATE",[$id])->fetch(PDO::FETCH_ASSOC);
        if (!is_array($r)||$r[$key]!==$id) throw new \RuntimeException('physician_authority_missing');return $r;
    }
    private function exactPair(array $admission,array $binding,array $w):void
    {
        foreach([$admission,$binding] as $r) if ($r['account_id']!==$w['account_id']||($r['profile_doctor_id']??$r['doctor_id'])!==$w['profile_doctor_id']
            ||$r['evidence_request_id']!==$w['evidence_request_id']||(int)$r['evidence_version']!==(int)$w['evidence_version']) throw new \RuntimeException('physician_authority_scope_mismatch');
        if ($binding['admission_id']!==$admission['admission_id']) throw new \RuntimeException('physician_authority_scope_mismatch');
    }
    private function legal(string $type,array $row,int $version,string $action,string $reason):void
    {
        $active=$type==='ADMISSION'?'APPROVED':'ACTIVE';
        $from=['REJECTED'=>['PENDING'],'SUSPENDED'=>[$active],'REVOKED'=>['PENDING',$active,'SUSPENDED'],'EXPIRED'=>['PENDING',$active,'SUSPENDED']];
        $reasons=['REJECTED'=>['REVIEW_WITHDRAWN'],'SUSPENDED'=>['SECURITY_REVIEW'],'REVOKED'=>['SECURITY_REVOCATION','BENEFICIARY_REQUEST'],'EXPIRED'=>['SOURCE_EXPIRED']];
        if ($version!==(int)$row['authority_version']) throw new \RuntimeException('physician_authority_version_conflict');
        if (!in_array($row['status'],$from[$action]??[],true)||!in_array($reason,$reasons[$action]??[],true)) throw new \RuntimeException('physician_authority_transition_denied');
        if ($action==='EXPIRED'&&($row['valid_until']===null||new \DateTimeImmutable($row['valid_until'],new \DateTimeZone('UTC'))>$this->a->clock->now()))
            throw new \RuntimeException('physician_authority_not_expired');
        // Downgrades remain possible when the original evidence/source/contact is revoked.
        // They confer no authority and must not be blocked by the failing positive gate.
    }
    private function update(string $type,array $row,string $status,string $event,?int $admissionVersion=null):void
    {
        [$table,$key]=$this->table($type);$extra=$admissionVersion===null?'':',admission_version=?';
        $args=[$status,(int)$row['authority_version']+1,$event];if ($admissionVersion!==null)$args[]=$admissionVersion;
        $q=$this->a->query("UPDATE $table SET status=?,authority_version=?,audit_reference=?$extra WHERE $key=? AND authority_version=? AND status=?",[...$args,$row[$key],$row['authority_version'],$row['status']]);
        if ($q->rowCount()!==1) throw new \RuntimeException('physician_authority_version_conflict');
    }
    private function history(AuthenticatedAccessContext $actor,string $type,string $id,int $version,string $before,string $status,array $r,
        string $case,string $decision,string $event,string $command):void
    {
        $this->a->query('INSERT INTO auth_physician_authority_history(history_id,authority_type,authority_id,authority_version,previous_status,status,
            actor_account_id,evidence_request_id,evidence_version,case_reference,decision_reference,audit_reference,command_reference,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [$this->a->uuid(),$type,$id,$version,$before,$status,$actor->accountId(),$r['request_id'],$r['version'],$case,$decision,$event,$command,$this->a->sqlTime()]);
    }
}
