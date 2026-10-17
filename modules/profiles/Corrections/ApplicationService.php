<?php
declare(strict_types=1);
namespace Profiles\Corrections;
use Identity\Contracts\AuthenticatedAccessContext;
use Identity\Services\StaffMfaService;
use PDO;

/** One exact public phone per accepted report. No cross-service/batch writer. */
final class ApplicationService
{
    private PublicPhoneCorrectionAdapter $writer;
    public function __construct(public readonly ApplicationAuthority $authority,private StaffMfaService $mfa)
    {
        if(!$mfa->usesConnection($authority->reports->pdo))throw new \RuntimeException('application_same_connection_required');
        $this->writer=new PublicPhoneCorrectionAdapter($authority->reports->pdo);
    }
    private function reports():ReportService{return $this->authority->reports;}
    private function query(string $sql,array $args=[]):\PDOStatement{return $this->reports()->query($sql,$args);}
    private function digest(array $data):string{return $this->reports()->cipher->digest('PUB-COR02-command',json_encode($data,JSON_THROW_ON_ERROR));}
    private function accepted(string $id,?int $version=null,?string $profile=null):array
    {
        $r=$this->reports()->report($id);
        if($r['status']!=='ACCEPTED_FOR_APPLICATION'||!in_array($r['category'],ApplicationCatalog::CATEGORIES,true))throw new ReportDenied(403);
        if($version!==null&&$r['version']!==$version)throw new ReportDenied(409);
        if($profile!==null&&$r['profile_doctor_id']!==$profile)throw new ReportDenied(403);
        $d=$this->query('SELECT decision_id FROM profiles_correction_decisions WHERE report_id=? AND version=? AND status=? FOR UPDATE',[$id,$r['version'],'ACCEPTED_FOR_APPLICATION'])->fetchColumn();
        if(!is_string($d))throw new ReportDenied(403);$r['decision_id']=$d;return $r;
    }
    private function command(string $id,AuthenticatedAccessContext $actor):array
    {
        ApplicationCatalog::uuid($id);
        $row=$this->query('SELECT * FROM profiles_correction_application_commands WHERE command_id=? FOR UPDATE',[$id])->fetch(PDO::FETCH_ASSOC);
        if(!is_array($row)||$row['actor_account_id']!==$actor->accountId())throw new ReportDenied(403);return $row;
    }
    private function material(array $c):array
    {return $this->reports()->cipher->open('application:'.$c['command_id'],$c['key_id'],$c['command_ciphertext']);}
    private function result(array $c):?array
    {
        $r=$this->query('SELECT * FROM profiles_correction_application_results WHERE command_id=?',[$c['command_id']])->fetch(PDO::FETCH_ASSOC);
        if(!is_array($r))return null;
        return $this->reports()->cipher->open('application-result:'.$c['command_id'],$r['key_id'],$r['result_ciphertext']);
    }
    private function state(string $id):string
    {return (string)($this->query('SELECT state FROM profiles_correction_application_journal WHERE command_id=? ORDER BY sequence_number DESC LIMIT 1',[$id])->fetchColumn()?:'REQUESTED');}
    private function journal(AuthenticatedAccessContext $actor,array $c,string $state):string
    {
        $seq=(int)$this->query('SELECT COALESCE(MAX(sequence_number),0) FROM profiles_correction_application_journal WHERE command_id=?',[$c['command_id']])->fetchColumn()+1;
        $audit=$this->reports()->audit->application($actor,$c['command_id'],$c['report_id'],$state,$seq,$c['case_id']);
        $this->query('INSERT INTO profiles_correction_application_journal(event_id,command_id,sequence_number,state,actor_account_id,audit_reference,created_at) VALUES(?,?,?,?,?,?,?)',
            [$this->reports()->uuid(),$c['command_id'],$seq,$state,$actor->accountId(),$audit,$this->reports()->now()]);return $audit;
    }
    private function assertCase(AuthenticatedAccessContext $actor,array $c,array $grants):void
    {
        if(!isset($grants[ApplicationCatalog::CAPABILITY])||!hash_equals($c['grant_reference'],$grants[ApplicationCatalog::CAPABILITY])
            ||(int)$c['credential_version']!==$actor->principal()->credentialVersion())throw new ReportDenied(403);
        $case=$this->query('SELECT * FROM auth_governance_cases WHERE case_id=? FOR UPDATE',[$c['case_id']])->fetch(PDO::FETCH_ASSOC);
        if(!is_array($case)||$case['operation']!==ApplicationCatalog::OPERATION||$case['status']!=='OPEN'
            ||$case['requester_account_id']!==$actor->accountId()||$case['profile_doctor_id']!==$c['profile_doctor_id']||$case['expires_at']<=$this->reports()->now())throw new ReportDenied(403);
        $ctx=json_decode($case['security_context'],true,16,JSON_THROW_ON_ERROR);
        if(($ctx['command_id']??null)!==$c['command_id']||($ctx['binding']['account_id']??null)!==$actor->accountId()
            ||($ctx['binding']['session_id']??null)!==(string)$actor->session()->sessionId())throw new ReportDenied(403);
        $this->authority->security->assertLive($ctx['binding'],true);
    }
    private function current(array $c,array $material):array
    {
        $this->accepted($c['report_id'],(int)$c['report_version'],$c['profile_doctor_id']);
        $r=$this->writer->snapshot($c['profile_doctor_id'],(string)$c['contact_point_id']);
        if(!hash_equals($c['expected_version'],$r['expected_version'])||$material['request']['target']['consultorio_id']!==null)throw new ReportDenied(409);
        return $r;
    }
    private function authorization(AuthenticatedAccessContext $actor,array $c):array
    {
        $r=$this->query("SELECT a.*,p.status proof_status,p.expires_at,p.actor_account_id,p.staff_session_id,p.credential_version,p.operation,p.case_id,p.profile_doctor_id,f.status factor_status
            FROM profiles_correction_application_authorizations a JOIN auth_staff_mfa_proofs p ON p.proof_id=a.proof_id
            JOIN auth_staff_mfa_factors f ON f.factor_id=p.factor_id WHERE a.command_id=? FOR UPDATE",[$c['command_id']])->fetch(PDO::FETCH_ASSOC);
        if(!is_array($r)||$r['proof_status']!=='VERIFIED'||$r['factor_status']!=='ACTIVE'||$r['expires_at']<=$this->reports()->now()
            ||$r['actor_account_id']!==$actor->accountId()||$r['staff_session_id']!==(string)$actor->session()->sessionId()
            ||(int)$r['credential_version']!==$actor->principal()->credentialVersion()||$r['operation']!==ApplicationCatalog::OPERATION
            ||$r['case_id']!==$c['case_id']||$r['profile_doctor_id']!==$c['profile_doctor_id'])throw new ReportDenied(403);
        return $r;
    }
    public function snapshot(AuthenticatedAccessContext $actor,string $id):array
    {
        return $this->authority->transaction($actor,function(array $grants)use($id,$actor):array{
            $r=$this->reports()->report($id);$can=isset($grants[ApplicationCatalog::CAPABILITY]);
            $result=$this->query('SELECT command_id,status,created_at FROM profiles_correction_application_results WHERE report_id=?',[$id])->fetch(PDO::FETCH_ASSOC);
            $history=$this->query('SELECT j.command_id,j.sequence_number,j.state,j.created_at FROM profiles_correction_application_journal j JOIN profiles_correction_application_commands c ON c.command_id=j.command_id WHERE c.report_id=? ORDER BY c.created_at,j.command_id,j.sequence_number',[$id])->fetchAll(PDO::FETCH_ASSOC);
            $eligible=$r['status']==='ACCEPTED_FOR_APPLICATION'&&in_array($r['category'],ApplicationCatalog::CATEGORIES,true);
            $targets=$can&&$eligible&&!is_array($result)?$this->writer->targets($r['profile_doctor_id']):[];
            $pending=$this->query('SELECT command_id FROM profiles_correction_application_commands WHERE report_id=? AND actor_account_id=? ORDER BY created_at DESC,command_id DESC LIMIT 1',[$id,$actor->accountId()])->fetchColumn();
            $this->reports()->audit->application($actor,$id,$id,'READ',0,'read',$can?ApplicationCatalog::CAPABILITY:ReportCatalog::CAPABILITY);
            return ['enabled'=>true,'can_apply'=>$can&&$eligible&&!is_array($result)&&$targets!==[],
                'report_id'=>$id,'report_version'=>$r['version'],'profile_id'=>$r['profile_doctor_id'],'field_family'=>ApplicationCatalog::FAMILY,
                'targets'=>$targets,'result'=>is_array($result)?$result:null,'history'=>$history,'pending_command'=>is_string($pending)?$pending:null,
                'reason'=>is_array($result)?null:(!$can?'APPLICATION_PERMISSION_REQUIRED':(!$eligible?'REPORT_NOT_ELIGIBLE':($targets===[]?'NO_SUPPORTED_PUBLISHED_TARGET':null))),
                'blocked_families'=>ApplicationCatalog::BLOCKED];
        },true);
    }
    public function decorateQueue(AuthenticatedAccessContext $actor,array $queue):array
    {
        return $this->authority->transaction($actor,function()use($queue):array{
            foreach($queue['reports'] as &$r){$result=$this->query('SELECT status FROM profiles_correction_application_results WHERE report_id=?',[$r['report_id']])->fetchColumn();$r['application_state']=is_string($result)?$result:null;}
            unset($r);return $queue;
        },true);
    }
    public function begin(AuthenticatedAccessContext $actor,array $input,string $email,string $password):array
    {
        $request=ApplicationCatalog::command($input,$this->writer);$digest=$this->digest($request);
        $replay=$this->authority->transaction($actor,function(array $grants)use($actor,$request,$digest):?array{
            $old=$this->query('SELECT command_id FROM profiles_correction_application_commands WHERE command_id=? FOR UPDATE',[$request['command_id']])->fetchColumn();
            if(is_string($old))return $this->existing($actor,$this->command($old,$actor),$grants,$digest);
            $this->accepted($request['report_id'],$request['report_version'],$request['profile_id']);
            $r=$this->writer->snapshot($request['profile_id'],$request['target']['contact_point_id']);
            if(!hash_equals($request['expected_version'],$r['expected_version']))throw new ReportDenied(409);return null;
        });
        if($replay!==null)return $replay;
        $reauth=$this->authority->security->reauthenticate($actor,$email,$password,['ip'=>null]);
        return $this->authority->transaction($actor,function(array $grants)use($actor,$request,$digest,$reauth):array{
            $old=$this->query('SELECT command_id FROM profiles_correction_application_commands WHERE command_id=? FOR UPDATE',[$request['command_id']])->fetchColumn();
            if(is_string($old))return $this->existing($actor,$this->command($old,$actor),$grants,$digest);
            $r=$this->accepted($request['report_id'],$request['report_version'],$request['profile_id']);
            if($this->query('SELECT command_id FROM profiles_correction_application_results WHERE report_id=?',[$r['report_id']])->fetchColumn()!==false)throw new ReportDenied(409);
            $before=$this->writer->snapshot($request['profile_id'],$request['target']['contact_point_id']);
            if(!hash_equals($request['expected_version'],$before['expected_version']))throw new ReportDenied(409);
            $case=$this->reports()->uuid();$id=$request['command_id'];$now=$this->reports()->now();
            $this->query("UPDATE auth_governance_cases SET status='REVOKED' WHERE operation=? AND requester_account_id=? AND profile_doctor_id=? AND status='OPEN'",[ApplicationCatalog::OPERATION,$actor->accountId(),$r['profile_doctor_id']]);
            $this->query("INSERT INTO auth_governance_cases(case_id,operation_id,operation,requester_account_id,beneficiary_account_id,profile_doctor_id,reason,status,expires_at,created_at,security_context) VALUES(?,?,?,?,?,?,?,'OPEN',?,?,?)",
                [$case,$this->reports()->uuid(),ApplicationCatalog::OPERATION,$actor->accountId(),$actor->accountId(),$r['profile_doctor_id'],'Apply exact verified public phone correction',
                    $this->reports()->clock->now()->setTimezone(new \DateTimeZone('UTC'))->modify('+5 minutes')->format('Y-m-d H:i:s.u'),$now,json_encode(['binding'=>$reauth,'command_id'=>$id],JSON_THROW_ON_ERROR)]);
            $challenge=$this->mfa->issueChallenge($actor->accountId(),ApplicationCatalog::OPERATION,$case,$actor->accountId(),$actor->accountId(),$r['profile_doctor_id'],$reauth);
            $material=['request'=>$request,'before'=>$before,'challenge_id'=>$challenge['challenge_id'],'challenge'=>$challenge['challenge']];
            $this->query('INSERT INTO profiles_correction_application_commands(command_id,report_id,decision_id,report_version,profile_doctor_id,field_family,contact_point_id,consultorio_id,expected_version,command_digest,command_ciphertext,key_id,retention_policy,actor_account_id,credential_version,grant_reference,case_id,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                [$id,$r['report_id'],$r['decision_id'],$r['version'],$r['profile_doctor_id'],ApplicationCatalog::FAMILY,$request['target']['contact_point_id'],null,$request['expected_version'],$digest,
                    $this->reports()->cipher->seal('application:'.$id,$material),$this->reports()->cipher->keyId,$this->reports()->cipher->retentionPolicy,$actor->accountId(),$actor->principal()->credentialVersion(),$grants[ApplicationCatalog::CAPABILITY],$case,$now]);
            $c=$this->command($id,$actor);$this->journal($actor,$c,'REQUESTED');return ['command_id'=>$id,'state'=>'REQUESTED','replayed'=>false,'challenge_id'=>$challenge['challenge_id'],'challenge'=>$challenge['challenge']];
        });
    }
    private function existing(AuthenticatedAccessContext $actor,array $c,array $grants,string $digest):array
    {
        if(!hash_equals($c['command_digest'],$digest))throw new ReportDenied(409);
        $result=$this->result($c);if($result!==null)return $result+['replayed'=>true];
        $this->assertCase($actor,$c,$grants);$m=$this->material($c);
        return ['command_id'=>$c['command_id'],'state'=>$this->state($c['command_id']),'replayed'=>true,'challenge_id'=>$m['challenge_id'],'challenge'=>$m['challenge']];
    }
    public function authorize(AuthenticatedAccessContext $actor,string $id,string $challengeId,string $challenge,string $code):array
    {
        $ready=$this->authority->transaction($actor,function(array $grants)use($actor,$id,$challengeId):bool{
            $c=$this->command($id,$actor);$this->assertCase($actor,$c,$grants);$m=$this->material($c);$this->current($c,$m);
            if($m['challenge_id']!==$challengeId)throw new ReportDenied(403);
            return $this->query('SELECT proof_id FROM profiles_correction_application_authorizations WHERE command_id=?',[$id])->fetchColumn()!==false;
        });
        if($ready){$this->authority->transaction($actor,fn()=>$this->authorization($actor,$this->command($id,$actor)));return ['command_id'=>$id,'state'=>'AUTHORIZED','profile_changed'=>false];}
        $proof=$this->mfa->verifyChallenge($actor->accountId(),(string)$actor->session()->sessionId(),$actor->principal()->credentialVersion(),$challengeId,$challenge,$code);
        return $this->authority->transaction($actor,function(array $grants)use($actor,$id,$proof):array{
            $c=$this->command($id,$actor);$this->assertCase($actor,$c,$grants);$this->current($c,$this->material($c));
            $this->query('INSERT INTO profiles_correction_application_authorizations(command_id,proof_id,proof_ciphertext,key_id,created_at) VALUES(?,?,?,?,?)',
                [$id,$proof['proof_id'],$this->reports()->cipher->seal('application-proof:'.$id,['proof'=>$proof['proof']]),$this->reports()->cipher->keyId,$this->reports()->now()]);
            $this->journal($actor,$c,'AUTHORIZED');return ['command_id'=>$id,'state'=>'AUTHORIZED','profile_changed'=>false];
        });
    }
    public function execute(AuthenticatedAccessContext $actor,string $id):array
    {
        $start=$this->authority->transaction($actor,function(array $grants)use($actor,$id):?array{
            $c=$this->command($id,$actor);$result=$this->result($c);if($result!==null)return $result+['replayed'=>true];
            $this->assertCase($actor,$c,$grants);$this->current($c,$this->material($c));
            $this->authorization($actor,$c);
            if(!in_array($this->state($id),['AUTHORIZED','RETRYABLE_FAILURE','RECONCILED_RETRYABLE','APPLY_STARTED'],true))throw new ReportDenied(409);
            $this->journal($actor,$c,'APPLY_STARTED');return null;
        });
        if($start!==null)return $start;
        try{return $this->authority->transaction($actor,function(array $grants)use($actor,$id):array{
            $c=$this->command($id,$actor);$result=$this->result($c);if($result!==null)return $result+['replayed'=>true];
            if($this->query('SELECT command_id FROM profiles_correction_application_results WHERE report_id=?',[$c['report_id']])->fetchColumn()!==false)throw new ReportDenied(409);
            $this->assertCase($actor,$c,$grants);$m=$this->material($c);$before=$this->current($c,$m);
            $auth=$this->authorization($actor,$c);
            $proof=$this->reports()->cipher->open('application-proof:'.$id,$auth['key_id'],$auth['proof_ciphertext']);
            $this->mfa->consumeProof($this->reports()->pdo,$auth['proof_id'],$proof['proof'],$actor->accountId(),(string)$actor->session()->sessionId(),$actor->principal()->credentialVersion(),
                ApplicationCatalog::OPERATION,$c['case_id'],$actor->accountId(),$actor->accountId(),$c['profile_doctor_id'],$id);
            $changed=$before['current_value']!==$m['request']['proposed_value'];
            $after=$changed?$this->writer->apply($m['request'],$before):$before;
            $now=$this->reports()->now();$this->query("UPDATE auth_governance_cases SET status='CONSUMED',approved_at=?,consumed_at=? WHERE case_id=? AND status='OPEN'",[$now,$now,$c['case_id']]);
            $state=$changed?'APPLIED':'NO_CHANGE';$audit=$this->journal($actor,$c,$state);
            $result=['command_id'=>$id,'report_id'=>$c['report_id'],'state'=>$state,'profile_changed'=>$changed,'target_version'=>$after['field_version'],
                'after_fingerprint'=>$after['expected_version'],'committed_at'=>$now];
            $this->query('INSERT INTO profiles_correction_application_results(command_id,report_id,status,target_version,after_fingerprint,result_ciphertext,key_id,audit_reference,created_at) VALUES(?,?,?,?,?,?,?,?,?)',
                [$id,$c['report_id'],$state,$after['field_version'],$after['expected_version'],$this->reports()->cipher->seal('application-result:'.$id,$result),$this->reports()->cipher->keyId,$audit,$now]);
            return $result+['replayed'=>false];
        });}catch(\Throwable $e){
            // The mutation, version trigger, proof consumption, audit and result share ONE
            // native transaction. A lost connection/commit leaves durable APPLY_STARTED.
            // Never infer success from matching values; status() reconciles the journal.
            try{$this->reconcile($actor,$id);}catch(\Throwable){}
            throw $e;
        }
    }
    public function reconcile(AuthenticatedAccessContext $actor,string $id):array
    {
        return $this->authority->transaction($actor,function(array $grants)use($actor,$id):array{
            $c=$this->command($id,$actor);$result=$this->result($c);if($result!==null)return $result+['replayed'=>true];
            $state=$this->state($id);$m=$this->material($c);
            if($state==='APPLY_STARTED'){
                try{$this->assertCase($actor,$c,$grants);$this->current($c,$m);$this->authorization($actor,$c);$state='RECONCILED_RETRYABLE';}
                catch(\Throwable){$state='RECONCILIATION_REQUIRED';}
                $this->journal($actor,$c,$state);
            }
            $retry=false;
            if(in_array($state,['AUTHORIZED','RETRYABLE_FAILURE','RECONCILED_RETRYABLE'],true)){
                try{$this->assertCase($actor,$c,$grants);$this->current($c,$m);$this->authorization($actor,$c);$retry=true;}catch(\Throwable){}
            }
            $response=['command_id'=>$id,'state'=>$state,'profile_changed'=>false,'retry_available'=>$retry,
                'comparison'=>['before'=>$m['before']['current_value'],'proposed'=>$m['request']['proposed_value']]];
            if($state==='REQUESTED'){
                try{$this->assertCase($actor,$c,$grants);$this->current($c,$m);$response+=['challenge_id'=>$m['challenge_id'],'challenge'=>$m['challenge']];}catch(\Throwable){}
            }
            return $response;
        });
    }
}
