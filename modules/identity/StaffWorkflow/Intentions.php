<?php
declare(strict_types=1);
namespace Identity\StaffWorkflow;
use Identity\Services\InternalCapabilityCatalog;
use Identity\StaffAdmission\{UnitOfWork,Audit};
/** Versioned Director preparation. No effective grant is written here. */
final class Intentions
{
    public function __construct(private \PDO $p,private \Identity\Http\CanonicalHttpSessionResolver $sessions,private Service $flow){}
    private function query(string $sql,array $args=[]):\PDOStatement{$q=$this->p->prepare($sql);$q->execute($args);return $q;}
    private function current(string $case):?array{return $this->query('SELECT * FROM auth_staff_capability_intentions WHERE case_id=? ORDER BY version DESC LIMIT 1',[$case])->fetch(\PDO::FETCH_ASSOC)?:null;}
    public function usesConnection(\PDO $p):bool{return $p===$this->p;}
    public function target(string $case):string
    {return (string)$this->query('SELECT bound_account_id FROM auth_staff_admission_cases WHERE case_id=?',[$case])->fetchColumn();}
    public function authorizeActivation($actor,string $target,string $cap,array $b):bool
    {
        Service::keys($b,['case_id','expected_case_version','expected_version','capability']);
        if(!$this->p->inTransaction()||!is_int($b['expected_case_version'])||!is_int($b['expected_version']))throw new \RuntimeException('workflow_invalid');
        $case=$this->flow->case($b['case_id']);$this->flow->director($actor,$case);$v=$this->current($case['case_id']);
        if(!$v||$case['bound_account_id']!==$target||$case['proposed_category']!=='ADVISOR'||(int)$case['version']!==$b['expected_case_version']||(int)$v['version']!==$b['expected_version']||(int)$v['case_version']!==(int)$case['version']||$v['recipient_reference']!==$case['recipient_reference']||$v['bound_account_id']!==$target||$v['binding_reference']!==$case['binding_reference']||!in_array($cap,json_decode($v['capabilities'],true),true)||!isset(self::catalog()[$cap]))throw new \RuntimeException('workflow_stale');
        $this->flow->approved($case);
        if(!$this->query('SELECT 1 FROM auth_staff_admission_completions WHERE case_id=? AND account_id=?',[$case['case_id'],$target])->fetchColumn())throw new \RuntimeException('workflow_denied');
        return (bool)$this->query('SELECT 1 FROM auth_staff_intention_activations WHERE case_id=? AND intention_version=? AND capability=?',[$case['case_id'],$v['version'],$cap])->fetchColumn();
    }
    public function finishActivation($actor,array $b,string $audit):void
    {$this->query('INSERT INTO auth_staff_intention_activations(case_id,intention_version,capability,actor_account_id,audit_event_id) VALUES(?,?,?,?,?)',[$b['case_id'],$b['expected_version'],$b['capability'],$actor->accountId(),$audit]);}
    public static function catalog():array{return array_filter(InternalCapabilityCatalog::all(),static fn($c)=>$c['kind']==='operational'&&$c['delegable']===true);}
    public function handle(array $cookies,string $action,array $b):array
    {
        Service::keys($b,$action==='intentions'?['case_id']:['case_id','expected_case_version','expected_version','capabilities','operation_id']);
        if(!is_string($b['case_id'])||!preg_match('/^adm_[a-f0-9]{32}$/D',$b['case_id']))throw new \RuntimeException('workflow_invalid');
        if($action!=='intentions'){
            Service::uuidInput($b['operation_id']);
            if(!is_int($b['expected_case_version'])||!is_int($b['expected_version'])||!is_array($b['capabilities'])||!array_is_list($b['capabilities'])||count($b['capabilities'])>100)throw new \RuntimeException('workflow_invalid');
            foreach($b['capabilities'] as $cap)if(!is_string($cap)||!isset(self::catalog()[$cap]))throw new \RuntimeException('workflow_invalid');
            if(count(array_unique($b['capabilities']))!==count($b['capabilities']))throw new \RuntimeException('workflow_invalid');
            sort($b['capabilities']);
        }
        return (new UnitOfWork($this->p,$this->sessions))->run($cookies,function($actor)use($action,$b){
            $case=$this->flow->case($b['case_id']);$this->flow->director($actor,$case);
            if($case['proposed_category']!=='ADVISOR')throw new \RuntimeException('workflow_denied');
            $current=$this->current($case['case_id']);$version=(int)($current['version']??0);
            if($action!=='intentions'){
                $digest=hash('sha256',json_encode($b,JSON_THROW_ON_ERROR));
                $old=$this->query('SELECT * FROM auth_staff_capability_intentions WHERE actor_account_id=? AND operation_id=?',[$actor->accountId(),$b['operation_id']])->fetch(\PDO::FETCH_ASSOC);
                if($old){if(!hash_equals($old['request_digest'],$digest))throw new \RuntimeException('workflow_conflict');return ['committed'=>true,'version'=>(int)$old['version'],'operation_id'=>$b['operation_id']];}
                if($version!==$b['expected_version']||(int)$case['version']!==$b['expected_case_version'])throw new \RuntimeException('workflow_conflict');
                $event=Audit::append($this->p,$actor,$case['case_id'],'update',$b['operation_id'],(int)$case['version'],'PENDING_CAPABILITY_INTENTIONS');
                $this->query('INSERT INTO auth_staff_capability_intentions(case_id,version,case_version,recipient_reference,bound_account_id,binding_reference,capabilities,actor_account_id,operation_id,request_digest,audit_event_id) VALUES(?,?,?,?,?,?,?,?,?,?,?)',[$case['case_id'],$version+1,$case['version'],$case['recipient_reference'],$case['bound_account_id'],$case['binding_reference'],json_encode($b['capabilities']),$actor->accountId(),$b['operation_id'],$digest,$event]);
                return ['committed'=>true,'version'=>$version+1,'operation_id'=>$b['operation_id'],'effective'=>false];
            }
            $admitted=(bool)$this->query('SELECT 1 FROM auth_staff_admission_completions WHERE case_id=?',[$case['case_id']])->fetchColumn();
            $effective=$admitted?$this->query("SELECT g.capability FROM internal_operator_grants g JOIN internal_staff s ON s.account_id=g.account_id JOIN auth_accounts a ON a.account_id=g.account_id WHERE g.account_id=? AND g.status='ACTIVE' AND g.revoked_at IS NULL AND s.status='ACTIVE' AND a.status='active' AND a.email_verified_at IS NOT NULL",[$case['bound_account_id']])->fetchAll(\PDO::FETCH_COLUMN):[];
            $confirmed=$this->query('SELECT capability FROM auth_staff_intention_activations WHERE case_id=? AND intention_version=?',[$case['case_id'],$version])->fetchAll(\PDO::FETCH_COLUMN);
            return ['effective_capabilities'=>$effective,'confirmed_capabilities'=>$confirmed,'version'=>$version,'case_version'=>(int)$case['version'],'prepared_case_version'=>$current?(int)$current['case_version']:null,'capabilities'=>$current?json_decode($current['capabilities'],true):[],'catalog'=>array_values(self::catalog()),'admitted'=>$admitted,'effective'=>false,'confirmation_required'=>true];
        });
    }
}
