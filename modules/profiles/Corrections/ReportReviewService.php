<?php
declare(strict_types=1);
namespace Profiles\Corrections;
use Identity\Contracts\AuthenticatedAccessContext;
use Identity\Services\{GovernanceSecurityContext,StaffMfaService};
use PDO;

/** Human decisions only. No profile, enrollment, owner, subscription or clinical writer. */
final class ReportReviewService
{
    public function __construct(public readonly ReportService $reports,private GovernanceSecurityContext $security,private StaffMfaService $mfa)
    {if(!$mfa->usesConnection($reports->pdo))throw new \RuntimeException('correction_shared_transaction_required');}
    private function authority(AuthenticatedAccessContext $actor):string
    {
        $r=$this->reports->query("SELECT a.status,a.email_verified_at,c.credential_version,s.governance_class,g.grant_id
            FROM auth_accounts a JOIN auth_account_credentials c ON c.account_id=a.account_id
            JOIN internal_staff s ON s.account_id=a.account_id JOIN internal_operator_grants g ON g.account_id=s.account_id
            WHERE a.account_id=? AND s.status='ACTIVE' AND s.governance_class IN ('DIRECTOR','MASTER_ADMIN')
            AND g.capability=? AND g.status='ACTIVE' AND g.revoked_at IS NULL FOR UPDATE",[$actor->accountId(),ReportCatalog::CAPABILITY])->fetchAll(PDO::FETCH_ASSOC);
        if(count($r)!==1||$r[0]['status']!=='active'||$r[0]['email_verified_at']===null
            ||(int)$r[0]['credential_version']!==$actor->principal()->credentialVersion())throw new ReportDenied(403);
        return hash('sha256',json_encode($r[0],JSON_THROW_ON_ERROR));
    }
    private function transaction(AuthenticatedAccessContext $actor,callable $fn):mixed
    {
        $p=$this->reports->pdo;if($p->inTransaction())throw new ReportDenied(503);
        $binding=$this->security->binding($actor);
        try{$p->beginTransaction();$grant=$this->authority($actor);$result=$fn($grant);
            if(!hash_equals($grant,$this->authority($actor)))throw new ReportDenied(403);
            $this->security->assertLive($binding);$p->commit();return $result;
        }catch(\Throwable $e){if($p->inTransaction())$p->rollBack();throw $e;}
    }
    public function queue(AuthenticatedAccessContext $actor,int $page=0):array
    {
        if($page<0||$page>100000)throw new ReportDenied(400);
        return $this->transaction($actor,function()use($actor,$page):array{
            $rows=$this->reports->query("SELECT r.report_id,r.profile_doctor_id,r.category,r.created_at,p.display_name,
                COALESCE(d.status,'PENDING_REVIEW') status,COALESCE(d.version,1) version FROM profiles_correction_reports r
                JOIN profiles_doctors p ON p.doctor_id=r.profile_doctor_id
                LEFT JOIN profiles_correction_decisions d ON d.report_id=r.report_id AND d.version=(SELECT MAX(x.version) FROM profiles_correction_decisions x WHERE x.report_id=r.report_id)
                ORDER BY CASE WHEN COALESCE(d.status,'PENDING_REVIEW')='PENDING_REVIEW' THEN 0 WHEN d.status='NEEDS_CLARIFICATION' THEN 1 ELSE 2 END,
                r.created_at,r.report_id LIMIT 51 OFFSET ".($page*50))->fetchAll(PDO::FETCH_ASSOC);
            $this->reports->audit->append('review-queue','QUEUE_READ',0,'NONE','NONE',$actor);
            return ['reports'=>array_slice($rows,0,50),'page'=>$page,'has_more'=>count($rows)>50];
        });
    }
    public function open(AuthenticatedAccessContext $actor,string $id):array
    {
        return $this->transaction($actor,function()use($id,$actor):array{
            $r=$this->reports->report($id);$payload=$this->reports->cipher->open('report:'.$id.':'.$r['profile_doctor_id'],$r['key_id'],$r['payload_ciphertext']);
            $payload['category_label']=ReportCatalog::CATEGORIES[$payload['category']];
            $payload['relation_label']=ReportCatalog::RELATIONS[$payload['relation']]??null;
            $history=$this->reports->query('SELECT * FROM profiles_correction_decisions WHERE report_id=? ORDER BY version',[$id])->fetchAll(PDO::FETCH_ASSOC);
            $privateHistory=[];
            foreach($history as $d)$privateHistory[]=['version'=>(int)$d['version'],'status'=>$d['status'],'reason'=>$d['reason_code'],
                'reviewer'=>$d['reviewer_account_id'],'at'=>$d['created_at'],'audit_reference'=>$d['audit_reference'],
                'decision'=>$this->reports->cipher->open('decision:'.$d['decision_id'].':'.$id,$d['key_id'],$d['evidence_ciphertext'])];
            $name=$this->reports->query('SELECT display_name FROM profiles_doctors WHERE doctor_id=?',[$r['profile_doctor_id']])->fetchColumn();
            $this->reports->audit->append($id,'REPORT_READ',$r['version'],$r['status'],$r['status'],$actor);
            return ['report_id'=>$id,'profile'=>$r['profile_doctor_id'],'profile_name'=>$name,'original_route'=>$r['original_route'],
                'version'=>$r['version'],'status'=>$r['status'],'created_at'=>$r['created_at'],'payload'=>$payload,'history'=>$privateHistory];
        });
    }
    private function command(array $data):array
    {
        ReportCatalog::fields($data,['status','reason','evidence_refs','decision_note','facts_confirmed']);
        $status=$data['status']??null;$reason=$data['reason']??null;
        if(!is_string($status)||!in_array($status,['NEEDS_CLARIFICATION','REJECTED','ACCEPTED_FOR_APPLICATION'],true)
            ||!is_string($reason)||!isset(ReportCatalog::REASONS[$reason])
            ||($status==='NEEDS_CLARIFICATION'&&$reason!=='NEEDS_DETAILS')
            ||($status==='REJECTED'&&!in_array($reason,['NOT_SUPPORTED','OUT_OF_SCOPE','DUPLICATE_REPORT'],true))
            ||($status==='ACCEPTED_FOR_APPLICATION'&&($reason!=='FACTS_SUPPORTED'||($data['facts_confirmed']??null)!==true))
            ||!is_bool($data['facts_confirmed']??null))throw new ReportDenied(400);
        $refs=$data['evidence_refs']??null;
        if(!is_array($refs)||!array_is_list($refs)||count($refs)>5||($status==='ACCEPTED_FOR_APPLICATION'&&count($refs)<1))throw new ReportDenied(400);
        foreach($refs as $ref)if(!is_string($ref)||!preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{7,95}$/D',$ref))throw new ReportDenied(400);
        return ['status'=>$status,'reason'=>$reason,'evidence_refs'=>array_values(array_unique($refs)),
            'decision_note'=>ReportCatalog::text($data['decision_note']??null,8,500),'facts_confirmed'=>$data['facts_confirmed']];
    }
    public function begin(AuthenticatedAccessContext $actor,string $id,int $version,array $data,string $email,string $password):array
    {
        $command=$this->command($data);
        // Fail before attempting password authentication for an actor without the exact capability.
        $this->transaction($actor,static fn()=>null);
        $reauth=$this->security->reauthenticate($actor,$email,$password,['ip'=>null]);
        return $this->transaction($actor,function(string $grant)use($actor,$id,$version,$command,$reauth):array{
            $r=$this->reports->report($id,$version);$this->pending($r);
            $profile=$r['profile_doctor_id'];$case=$this->reports->uuid();$now=$this->reports->now();
            $this->reports->query("UPDATE auth_governance_cases SET status='REVOKED' WHERE operation=? AND requester_account_id=? AND profile_doctor_id=? AND status='OPEN'",
                [ReportCatalog::OPERATION,$actor->accountId(),$profile]);
            // target/beneficiary are the reviewer themself for this anonymous-report operation;
            // no fabricated reporter account. Exact report/profile/command lives in immutable intent.
            $this->reports->query("INSERT INTO auth_governance_cases(case_id,operation_id,operation,requester_account_id,beneficiary_account_id,profile_doctor_id,reason,status,expires_at,created_at,security_context)
                VALUES(?,?,?,?,?,?,?,'OPEN',?,?,?)",[$case,$this->reports->uuid(),ReportCatalog::OPERATION,$actor->accountId(),$actor->accountId(),$profile,
                    'Human public profile correction review',$this->reports->clock->now()->modify('+5 minutes')->format('Y-m-d H:i:s.u'),$now,
                    json_encode(['binding'=>$reauth,'report_id'=>$id,'version'=>$version],JSON_THROW_ON_ERROR)]);
            $this->reports->query('INSERT INTO profiles_correction_review_intents(case_id,report_id,report_version,command_digest,command_ciphertext,key_id,grant_reference) VALUES(?,?,?,?,?,?,?)',
                [$case,$id,$version,$this->reports->cipher->digest('review-command',json_encode($command,JSON_THROW_ON_ERROR)),
                    $this->reports->cipher->seal('review:'.$case.':'.$id,$command),$this->reports->cipher->keyId,$grant]);
            $challenge=$this->mfa->issueChallenge($actor->accountId(),ReportCatalog::OPERATION,$case,$actor->accountId(),$actor->accountId(),$profile,$reauth);
            $this->reports->audit->append($id,'REVIEW_CHALLENGE',$version,$r['status'],$r['status'],$actor,$case);
            return ['case_id'=>$case,'report_id'=>$id,'version'=>$version,'challenge_id'=>$challenge['challenge_id'],'challenge'=>$challenge['challenge']];
        });
    }
    private function pending(array $r):void
    {if(!in_array($r['status'],['PENDING_REVIEW','NEEDS_CLARIFICATION'],true))throw new ReportDenied(409);}
    public function decide(AuthenticatedAccessContext $actor,string $id,int $version,string $case,string $challengeId,string $challenge,string $code):array
    {
        // Preflight exact case/resource/actor before consuming any MFA attempt.
        $scope=$this->transaction($actor,fn(string $grant)=>$this->case($actor,$id,$version,$case,$grant));
        if($scope['challenge_id']!==$challengeId)throw new ReportDenied(403);
        $proof=$this->mfa->verifyChallenge($actor->accountId(),(string)$actor->session()->sessionId(),$actor->principal()->credentialVersion(),$challengeId,$challenge,$code);
        return $this->commit($actor,$id,$version,$case,$proof['proof_id'],$proof['proof']);
    }
    /** Kept as a service command for native concurrency tests; never a proof-by-ID shortcut. */
    public function commit(AuthenticatedAccessContext $actor,string $id,int $version,string $case,string $proofId,string $proof):array
    {
        return $this->transaction($actor,function(string $grant)use($actor,$id,$version,$case,$proofId,$proof):array{
            $intent=$this->case($actor,$id,$version,$case,$grant);$r=$this->reports->report($id,$version);$this->pending($r);
            $command=$this->command($this->reports->cipher->open('review:'.$case.':'.$id,$intent['key_id'],$intent['command_ciphertext']));
            if(!hash_equals($intent['command_digest'],$this->reports->cipher->digest('review-command',json_encode($command,JSON_THROW_ON_ERROR))))throw new ReportDenied(403);
            $decision=$this->reports->uuid();
            $this->mfa->consumeProof($this->reports->pdo,$proofId,$proof,$actor->accountId(),(string)$actor->session()->sessionId(),
                $actor->principal()->credentialVersion(),ReportCatalog::OPERATION,$case,$actor->accountId(),$actor->accountId(),$r['profile_doctor_id'],$decision);
            $now=$this->reports->now();
            $this->reports->query("UPDATE auth_governance_cases SET status='CONSUMED',approved_at=?,consumed_at=? WHERE case_id=? AND status='OPEN'",[$now,$now,$case]);
            $audit=$this->reports->audit->append($id,$command['status'],$version+1,$r['status'],$command['status'],$actor,$case);
            $this->reports->query('INSERT INTO profiles_correction_decisions(decision_id,report_id,version,previous_status,status,reviewer_account_id,credential_version,case_id,proof_id,reason_code,evidence_ciphertext,key_id,audit_reference,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                [$decision,$id,$version+1,$r['status'],$command['status'],$actor->accountId(),$actor->principal()->credentialVersion(),$case,$proofId,$command['reason'],
                    $this->reports->cipher->seal('decision:'.$decision.':'.$id,$command),$this->reports->cipher->keyId,$audit,$now]);
            return ['report_id'=>$id,'version'=>$version+1,'status'=>$command['status'],'profile_changed'=>false];
        });
    }
    private function case(AuthenticatedAccessContext $actor,string $id,int $version,string $case,string $grant):array
    {
        $r=$this->reports->query('SELECT i.*,c.profile_doctor_id,c.requester_account_id,c.status,c.expires_at,c.security_context,h.challenge_id FROM profiles_correction_review_intents i
            JOIN auth_governance_cases c ON c.case_id=i.case_id JOIN profiles_correction_reports r ON r.report_id=i.report_id AND r.profile_doctor_id=c.profile_doctor_id
            JOIN auth_staff_mfa_challenges h ON h.case_id=c.case_id AND h.operation=c.operation WHERE i.case_id=? AND c.operation=? FOR UPDATE',[$case,ReportCatalog::OPERATION])->fetch(PDO::FETCH_ASSOC);
        if(!is_array($r)||$r['report_id']!==$id||(int)$r['report_version']!==$version||$r['requester_account_id']!==$actor->accountId()
            ||$r['status']!=='OPEN'||$r['expires_at']<=$this->reports->now()||!hash_equals($r['grant_reference'],$grant))throw new ReportDenied(403);
        $binding=json_decode($r['security_context'],true,16,JSON_THROW_ON_ERROR)['binding']??[];
        if(($binding['account_id']??null)!==$actor->accountId()||($binding['session_id']??null)!==(string)$actor->session()->sessionId()
            ||($binding['credential_version']??null)!==$actor->principal()->credentialVersion())throw new ReportDenied(403);
        $this->security->assertLive($binding,true);return $r;
    }
}
