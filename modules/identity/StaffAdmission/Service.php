<?php
declare(strict_types=1);
namespace Identity\StaffAdmission;
use Identity\Contracts\AuthenticatedAccessContext;
use Identity\Http\CanonicalHttpSessionResolver;
use PDO;
/** Admission data only. No dependency on staff registration, grants, review or notifications. */
final class Service
{
    public function __construct(private PDO $pdo,private CanonicalHttpSessionResolver $sessions){}
    private function query(string $sql,array $args=[]):\PDOStatement
    {$q=$this->pdo->prepare($sql);$q->execute($args);return $q;}
    private function row(string $sql,array $args=[]):array
    {return $this->query($sql,$args)->fetch(PDO::FETCH_ASSOC)?:throw new \RuntimeException('admission_denied');}
    private function authorize(AuthenticatedAccessContext $actor,?array $case,bool $directorOnly=false):bool
    {
        $staff=$this->query('SELECT governance_class,status FROM internal_staff WHERE account_id=? FOR UPDATE',[$actor->accountId()])->fetch(PDO::FETCH_ASSOC);
        $director=$staff&&$staff['governance_class']==='DIRECTOR'&&$staff['status']==='ACTIVE';
        if($director)return true;
        if($directorOnly||$case===null||$case['bound_account_id']!==$actor->accountId()
            ||(int)$case['binding_credential_version']!==$actor->principal()->credentialVersion()
            ||!is_string($case['binding_reference'])||$case['binding_reference']==='')throw new \RuntimeException('admission_denied');
        return false;
    }
    private function transaction(array $cookies,callable $fn):array
    {return (new UnitOfWork($this->pdo,$this->sessions))->run($cookies,$fn);}
    /** Internal domain writer; caller must hold UnitOfWork actor locks and exact case lock. */
    public function bindInvitationWithinTransaction(AuthenticatedAccessContext $actor,string $caseId,int $version,string $invitationId):void
    {
        if(!$this->pdo->inTransaction())throw new \RuntimeException('admission_transaction_required');
        $case=$this->case($caseId);
        if($case['state']!=='PREPARING'||(int)$case['version']!==$version||$case['bound_account_id']!==null
            ||$this->query('SELECT account_id FROM internal_staff WHERE account_id=? FOR UPDATE',[$actor->accountId()])->fetchColumn())throw new \RuntimeException('admission_denied');
        $this->query('UPDATE auth_staff_admission_cases SET bound_account_id=?,binding_credential_version=?,binding_reference=?,updated_at=UTC_TIMESTAMP(6) WHERE case_id=?',[$actor->accountId(),$actor->principal()->credentialVersion(),$invitationId,$caseId]);
    }
    private function case(string $id):array
    {return $this->row('SELECT * FROM auth_staff_admission_cases WHERE case_id=? FOR UPDATE',[$id]);}
    private function outcome(array $r):array
    {return ['operation_id'=>$r['operation_id'],'case_id'=>$r['case_id'],'version'=>(int)$r['resulting_version'],'command'=>$r['command'],'audit_event_id'=>$r['audit_event_id'],'committed'=>true,'refresh_required'=>true];}
    public function read(array $cookies,string $action,array $input):array
    {
        $b=Input::validate($action,$input);
        return $this->transaction($cookies,function($actor)use($action,$b){
            if($action==='csrf'){
                // Applicants receive CSRF only with an exact case through read; this bootstrap is Director-only.
                $this->authorize($actor,null,true);return ['ready'=>true];
            }
            if($action==='result'){
                $r=$this->row('SELECT * FROM auth_staff_admission_operations WHERE actor_account_id=? AND operation_id=?',[$actor->accountId(),$b['operation_id']]);
                $case=$this->case($r['case_id']);$this->authorize($actor,$case);
                Audit::append($this->pdo,$actor,$case['case_id'],'result',(new \Platform\Services\RandomAuditUuidProvider())->generateCanonicalUuid(),(int)$r['resulting_version'],'OPERATION_RESULT');return $this->outcome($r);
            }
            $case=$this->case($b['case_id']);$director=$this->authorize($actor,$case);
            $profile=$this->row('SELECT * FROM auth_staff_admission_versions WHERE case_id=? AND version=?',[$case['case_id'],$case['current_profile_version']]);
            $history=$this->query('SELECT version,previous_version,provenance,source_actor_account_id,recorded_at FROM auth_staff_admission_versions WHERE case_id=? ORDER BY version DESC LIMIT 20',[$case['case_id']])->fetchAll(PDO::FETCH_ASSOC);
            $data=['case_id'=>$case['case_id'],'channel'=>$case['channel'],'proposed_category'=>$case['proposed_category'],'state'=>$case['state'],'version'=>(int)$case['version'],'profile'=>$profile,'history'=>$history,'history_truncated'=>(int)$case['version']>20,'created_at'=>$case['created_at'],'updated_at'=>$case['updated_at'],'account_bound'=>$case['bound_account_id']!==null,'confirmation_available'=>$case['state']!=='REVOKED'&&$case['bound_account_id']===$actor->accountId()&&(int)$case['binding_credential_version']===$actor->principal()->credentialVersion()];
            if($director)$data+=['creator_account_id'=>$case['creator_account_id'],'recipient_reference'=>$case['recipient_reference']];
            // No recipient email copy: linked email remains a canonical Identity fact, not profile authority.
            if($case['bound_account_id']!==null)$data['account_email']=$this->row('SELECT email_address,email_verified_at FROM auth_accounts WHERE account_id=?',[$case['bound_account_id']]);
            unset($data['profile']['source_credential_version']);
            Audit::append($this->pdo,$actor,$case['case_id'],'read',(new \Platform\Services\RandomAuditUuidProvider())->generateCanonicalUuid(),(int)$case['version'],'CASE_DETAIL');
            return $data;
        });
    }
    public function command(array $cookies,string $action,array $input):array
    {
        $b=Input::validate($action,$input);
        if(!in_array($action,['prepare','update','correct','confirm','revoke'],true))throw new \InvalidArgumentException('admission_invalid');
        ksort($b);$digest=hash('sha256',json_encode([$action,$b],JSON_THROW_ON_ERROR));
        return $this->transaction($cookies,function($actor)use($action,$b,$digest){
            $admin=in_array($action,['prepare','update','revoke'],true);
            if($action==='prepare')$this->authorize($actor,null,true);
            $old=$this->query('SELECT * FROM auth_staff_admission_operations WHERE actor_account_id=? AND operation_id=?',[$actor->accountId(),$b['operation_id']])->fetch(PDO::FETCH_ASSOC);
            $case=$action==='prepare'?($old?$this->case($old['case_id']):null):$this->case($b['case_id']);
            if($case!==null){
                $this->authorize($actor,$case,$admin);
                if(!$admin&&($case['bound_account_id']!==$actor->accountId()||(int)$case['binding_credential_version']!==$actor->principal()->credentialVersion()))throw new \RuntimeException('admission_denied');
                if($case['state']==='REVOKED'&&!($old&&$action==='revoke'))throw new \RuntimeException('admission_conflict');
            }
            if($old){if(!hash_equals($old['request_digest'],$digest)||($case&&$old['case_id']!==$case['case_id']))throw new \RuntimeException('admission_conflict');return $this->outcome($old);}
            $now=(new \DateTimeImmutable('now',new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
            if($action==='prepare'){
                $id='adm_'.bin2hex(random_bytes(16));$version=1;
                $this->query('INSERT INTO auth_staff_admission_cases(case_id,channel,creator_account_id,recipient_reference,proposed_category,created_at,updated_at) VALUES(?,?,?,?,?,?,?)',[$id,$b['channel'],$actor->accountId(),$b['recipient_reference'],$b['proposed_category'],$now,$now]);
                $profile=array_intersect_key($b,array_flip(Input::FIELDS));
            }else{
                if((int)$case['version']!==$b['expected_version'])throw new \RuntimeException('admission_conflict');
                $id=$case['case_id'];$version=(int)$case['version']+1;
                $profile=$this->row('SELECT * FROM auth_staff_admission_versions WHERE case_id=? AND version=?',[$id,$case['version']]);
                if(in_array($action,['update','correct'],true))$profile=array_intersect_key($b,array_flip(Input::FIELDS));
            }
            if($action==='confirm'&&($profile['given_names']===''||$profile['first_surname']===''||$case['state']==='PERSON_CONFIRMED'))throw new \RuntimeException('admission_conflict');
            $provenance=match($action){'prepare','update'=>'ADMIN_PREPARED','correct'=>'APPLICANT_CORRECTED','confirm'=>'APPLICANT_CONFIRMED','revoke'=>'INTENTION_REVOKED'};
            $this->query('INSERT INTO auth_staff_admission_versions(case_id,version,previous_version,given_names,first_surname,second_surname,operation_state,operation_city,provenance,source_actor_account_id,source_credential_version,recorded_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)',[$id,$version,$version===1?null:$version-1,...array_map(fn($k)=>$profile[$k],Input::FIELDS),$provenance,$actor->accountId(),$actor->principal()->credentialVersion(),$now]);
            $state=match($action){'confirm'=>'PERSON_CONFIRMED','revoke'=>'REVOKED',default=>'PREPARING'};
            $this->query('UPDATE auth_staff_admission_cases SET version=?,current_profile_version=?,state=?,updated_at=? WHERE case_id=?',[$version,$version,$state,$now,$id]);
            $event=Audit::append($this->pdo,$actor,$id,$action,$b['operation_id'],$version,$provenance);
            $this->query('INSERT INTO auth_staff_admission_operations(actor_account_id,operation_id,case_id,resulting_version,command,request_digest,audit_event_id,recorded_at) VALUES(?,?,?,?,?,?,?,?)',[$actor->accountId(),$b['operation_id'],$id,$version,$action,$digest,$event,$now]);
            return $this->outcome(['operation_id'=>$b['operation_id'],'case_id'=>$id,'resulting_version'=>$version,'command'=>$action,'audit_event_id'=>$event]);
        });
    }
}
