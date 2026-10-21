<?php
declare(strict_types=1);
namespace Identity\StaffAdmission;
use Identity\Contracts\{AuthenticatedAccessContext,Clock,RateLimitOperation};
use Identity\Http\CanonicalHttpSessionResolver;
use Identity\Services\{OneTimeTokenCodec,RateLimitService};
use PDO;
/** No account/staff writer, login token, or delivery provider. All authority is canonical. */
final class InvitationService
{
    private UnitOfWork $unit;
    private Service $cases;
    public function __construct(private PDO $pdo,private CanonicalHttpSessionResolver $sessions,
        private InvitationPolicy $policy,private Clock $clock,private RateLimitService $limits,private InvitationDeliveryPort $delivery)
    {$this->unit=new UnitOfWork($pdo,$sessions);$this->cases=new Service($pdo,$sessions);}
    private function query(string $sql,array $args=[]):\PDOStatement
    {$q=$this->pdo->prepare($sql);$q->execute($args);return $q;}
    private function row(string $sql,array $args=[]):array
    {return $this->query($sql,$args)->fetch(PDO::FETCH_ASSOC)?:throw new \RuntimeException('admission_denied');}
    private function director(AuthenticatedAccessContext $actor):void
    {
        $s=$this->query('SELECT governance_class,status FROM internal_staff WHERE account_id=? FOR UPDATE',[$actor->accountId()])->fetch(PDO::FETCH_ASSOC);
        if(!$s||$s['governance_class']!=='DIRECTOR'||$s['status']!=='ACTIVE')throw new \RuntimeException('admission_denied');
    }
    private function receiptAuthority(AuthenticatedAccessContext $actor,array $r,array $case):void
    {
        if($r['command']!=='accept'){$this->director($actor);return;}
        if($case['bound_account_id']!==$actor->accountId()||(int)$case['binding_credential_version']!==$actor->principal()->credentialVersion()
            ||$case['binding_reference']!==$r['invitation_id'])throw new \RuntimeException('admission_denied');
    }
    private function outcome(array $r):array
    {
        $delivery=$this->row('SELECT delivery_state,state,expires_at FROM auth_staff_admission_invitations WHERE invitation_id=?',[$r['invitation_id']]);
        return ['operation_id'=>$r['operation_id'],'case_id'=>$r['case_id'],'invitation_id'=>$r['invitation_id'],
            'version'=>(int)$r['case_version'],'command'=>$r['command'],'audit_event_id'=>$r['audit_event_id'],'committed'=>true,
            'outcome'=>match($r['command']){'issue','reissue'=>'INVITATION_CREATED','accept'=>'ACCOUNT_CASE_BOUND','revoke'=>'INVITATION_REVOKED'},
            'delivery_state'=>$delivery['delivery_state'],'invitation_state'=>$delivery['state'],'expires_at'=>$delivery['expires_at'],'staff_accreditation'=>'PENDING','refresh_required'=>true];
    }
    private function now(?array $invitation=null):string
    {
        // SQL UTC time is a floor even if an injected clock moves backwards. Observed future time is durable.
        return max($this->clock->now()->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.u'),
            (string)$this->pdo->query('SELECT UTC_TIMESTAMP(6)')->fetchColumn(),$invitation['last_observed_at']??'');
    }
    public function read(array $cookies,string $action,array $input):array
    {
        $b=InvitationInput::validate($action,$input);
        if(!in_array($action,['csrf','result','case_status'],true))throw new \InvalidArgumentException('admission_invalid');
        return $this->unit->run($cookies,function($actor)use($action,$b){
            if($action==='csrf')return ['ready'=>true]; // Authenticated bootstrap only; no case discovery.
            if($action==='case_status'){
                $this->director($actor);
                $case=$this->row('SELECT * FROM auth_staff_admission_cases WHERE case_id=? FOR UPDATE',[$b['case_id']]);
                $inv=$this->query('SELECT invitation_id,case_version,state,delivery_state,issued_at,delivered_at,expires_at,consumed_at,consumed_account_id,last_observed_at FROM auth_staff_admission_invitations WHERE case_id=? ORDER BY issued_at DESC,invitation_id DESC LIMIT 1',[$case['case_id']])->fetch(PDO::FETCH_ASSOC);
                if($inv){$inv['expired']=$inv['state']!=='CONSUMED'&&$inv['expires_at']<=$this->now($inv);$inv['usable']=$case['state']==='PREPARING'&&$case['bound_account_id']===null&&$inv['state']==='ACTIVE'&&(int)$inv['case_version']===(int)$case['version']&&$inv['expires_at']>$this->now($inv);}
                Audit::append($this->pdo,$actor,$case['case_id'],'result',(new \Platform\Services\RandomAuditUuidProvider())->generateCanonicalUuid(),(int)$case['version'],'INVITATION_STATUS');
                return ['case_id'=>$case['case_id'],'version'=>(int)$case['version'],'case_state'=>$case['state'],'account_bound'=>$case['bound_account_id']!==null,'invitation'=>$inv?:null];
            }
            $r=$this->query('SELECT * FROM auth_staff_admission_invitation_operations WHERE actor_account_id=? AND operation_id=?',[$actor->accountId(),$b['operation_id']])->fetch(PDO::FETCH_ASSOC);
            if(!$r){$this->director($actor);return ['operation_id'=>$b['operation_id'],'committed'=>false,'outcome'=>'NOT_RECORDED'];}
            $case=$this->row('SELECT * FROM auth_staff_admission_cases WHERE case_id=? FOR UPDATE',[$r['case_id']]);
            $this->receiptAuthority($actor,$r,$case);
            Audit::append($this->pdo,$actor,$case['case_id'],'result',(new \Platform\Services\RandomAuditUuidProvider())->generateCanonicalUuid(),(int)$r['case_version'],'INVITATION_RESULT');
            return $this->outcome($r);
        });
    }
    public function command(array $cookies,string $action,array $input):array
    {
        $b=InvitationInput::validate($action,$input);
        if(!in_array($action,['issue','reissue','revoke','accept'],true))throw new \InvalidArgumentException('admission_invalid');
        if($this->pdo->inTransaction())throw new \RuntimeException('admission_transaction_required');
        if($action==='accept'){
            $actor=$this->sessions->resolve($cookies,true);
            if(!$actor)throw new \RuntimeException('admission_authentication_required');
            // Independent security transaction persists attempts even when acceptance rolls back.
            if(!$this->limits->consume(RateLimitOperation::TOKEN_CONSUME,['identity'=>'staff-admission:'.$actor->accountId()])->allowed())throw new \RuntimeException('admission_denied');
        }
        $digest=$this->policy->request($action,$b);$message=null;
        $result=$this->unit->run($cookies,function($actor)use($action,$b,$digest,&$message){
            if($action!=='accept')$this->director($actor);
            $case=$this->row('SELECT * FROM auth_staff_admission_cases WHERE case_id=? FOR UPDATE',[$b['case_id']]);
            $old=$this->query('SELECT * FROM auth_staff_admission_invitation_operations WHERE actor_account_id=? AND operation_id=?',[$actor->accountId(),$b['operation_id']])->fetch(PDO::FETCH_ASSOC);
            if($old){
                $this->receiptAuthority($actor,$old,$case);
                if($old['case_id']!==$case['case_id']||!hash_equals($old['request_digest'],$digest))throw new \RuntimeException('admission_denied');
                return $this->outcome($old);
            }
            if($case['state']!=='PREPARING'||$case['bound_account_id']!==null||(int)$case['version']!==$b['expected_version'])throw new \RuntimeException('admission_denied');
            $now=$this->now();$id=$b['invitation_id']??null;
            if($action==='issue'||$action==='reissue'){
                $active=$this->query("SELECT invitation_id FROM auth_staff_admission_invitations WHERE case_id=? AND state='ACTIVE' FOR UPDATE",[$case['case_id']])->fetchColumn();
                if($action==='issue'){
                    if($this->query('SELECT invitation_id FROM auth_staff_admission_invitations WHERE case_id=? LIMIT 1',[$case['case_id']])->fetchColumn())throw new \RuntimeException('admission_denied');
                }else{
                    $previous=$this->row('SELECT * FROM auth_staff_admission_invitations WHERE invitation_id=? AND case_id=? FOR UPDATE',[$id,$case['case_id']]);
                    if(($active&&$active!==$id)||!in_array($previous['state'],['ACTIVE','EXPIRED','REVOKED'],true))throw new \RuntimeException('admission_denied');
                    $now=$this->now($previous);
                    if($previous['state']==='ACTIVE')$this->query("UPDATE auth_staff_admission_invitations SET state='REPLACED',last_observed_at=? WHERE invitation_id=?",[$now,$id]);
                }
                $id='sti_'.bin2hex(random_bytes(16));$raw=OneTimeTokenCodec::issue();
                $expiry=(new \DateTimeImmutable($now,new \DateTimeZone('UTC')))->modify('+'.$this->policy->validitySeconds.' seconds')->format('Y-m-d H:i:s.u');
                $this->query("INSERT INTO auth_staff_admission_invitations(invitation_id,case_id,case_version,creator_account_id,creator_credential_version,purpose,recipient_digest,recipient_key_id,token_digest,validity_reference,issued_at,expires_at,last_observed_at,state) VALUES(?,?,?,?,?,'STAFF_ADMISSION_ACCOUNT_BINDING',?,?,?,?,?,?,?,'ACTIVE')",
                    [$id,$case['case_id'],$case['version'],$actor->accountId(),$actor->principal()->credentialVersion(),$this->policy->recipient($case['case_id'],$b['recipient_email']),$this->policy->keyId,OneTimeTokenCodec::hash($raw),$this->policy->validityReference,$now,$expiry,$now]);
                $message=[$id,$case['case_id'],$b['recipient_email'],$raw,$expiry];
            }else{
                $inv=$this->row('SELECT * FROM auth_staff_admission_invitations WHERE invitation_id=? AND case_id=? FOR UPDATE',[$id,$case['case_id']]);
                if($action==='revoke'){
                    if($inv['state']!=='ACTIVE')throw new \RuntimeException('admission_denied');
                    if($inv['state']==='ACTIVE')$this->query("UPDATE auth_staff_admission_invitations SET state='REVOKED',last_observed_at=? WHERE invitation_id=?",[$this->now($inv),$id]);
                }else{
                    $account=$this->row('SELECT email_normalized FROM auth_accounts WHERE account_id=?',[$actor->accountId()]);
                    if($inv['state']!=='ACTIVE'||(int)$inv['case_version']!==(int)$case['version']||$inv['purpose']!=='STAFF_ADMISSION_ACCOUNT_BINDING'
                        ||$inv['recipient_key_id']!==$this->policy->keyId||!hash_equals($inv['token_digest'],OneTimeTokenCodec::hash($b['token']))
                        ||!hash_equals($inv['recipient_digest'],$this->policy->recipient($case['case_id'],$account['email_normalized'])))throw new \RuntimeException('admission_denied');
                    $now=$this->now($inv);
                    if($now>=$inv['expires_at']){
                        $this->query("UPDATE auth_staff_admission_invitations SET state='EXPIRED',last_observed_at=? WHERE invitation_id=?",[$now,$id]);
                        Audit::append($this->pdo,$actor,$case['case_id'],'invitation_expire',$b['operation_id'],(int)$case['version'],'INVITATION_EXPIRED');
                        return ['denied'=>true]; // Commit terminal expiry, then return the same generic denial.
                    }
                    $this->cases->bindInvitationWithinTransaction($actor,$case['case_id'],(int)$case['version'],$id);
                    $this->query("UPDATE auth_staff_admission_invitations SET state='CONSUMED',consumed_account_id=?,consumed_credential_version=?,consumed_at=?,last_observed_at=? WHERE invitation_id=?",[$actor->accountId(),$actor->principal()->credentialVersion(),$now,$now,$id]);
                }
            }
            $event=Audit::append($this->pdo,$actor,$case['case_id'],'invitation_'.$action,$b['operation_id'],(int)$case['version'],match($action){'issue','reissue'=>'INVITATION_CREATED','revoke'=>'INVITATION_REVOKED','accept'=>'INVITATION_ACCEPTED'});
            $r=['actor_account_id'=>$actor->accountId(),'operation_id'=>$b['operation_id'],'case_id'=>$case['case_id'],'invitation_id'=>$id,'case_version'=>$case['version'],'command'=>$action,'request_digest'=>$digest,'audit_event_id'=>$event,'recorded_at'=>$now];
            $this->query('INSERT INTO auth_staff_admission_invitation_operations(actor_account_id,operation_id,case_id,invitation_id,case_version,command,request_digest,audit_event_id,recorded_at) VALUES(?,?,?,?,?,?,?,?,?)',array_values($r));
            return $this->outcome($r);
        });
        if(isset($result['denied']))throw new \RuntimeException('admission_denied');
        if($message!==null){
            // Commit precedes transport. A lost/failed ACK remains pending. No automatic resend/replacement.
            try{
                $this->delivery->deliver(...$message);
                $result=$this->unit->run($cookies,function($actor)use($result){
                    $this->director($actor);
                    $this->row('SELECT case_id FROM auth_staff_admission_cases WHERE case_id=? FOR UPDATE',[$result['case_id']]);
                    $this->query("UPDATE auth_staff_admission_invitations SET delivery_state='DELIVERY_CONFIRMED',delivered_at=COALESCE(delivered_at,UTC_TIMESTAMP(6)) WHERE invitation_id=?",[$result['invitation_id']]);
                    Audit::append($this->pdo,$actor,$result['case_id'],'invitation_delivery',(new \Platform\Services\RandomAuditUuidProvider())->generateCanonicalUuid(),$result['version'],'DELIVERY_CONFIRMED');
                    return [...$result,'delivery_state'=>'DELIVERY_CONFIRMED'];
                });
            }catch(\Throwable){/* Canonical creation receipt survives; authorized result lookup reconciles. */}
            finally{$message=null;}
        }
        return $result;
    }
}
