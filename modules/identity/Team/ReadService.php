<?php
declare(strict_types=1);
namespace Identity\Team;

use Identity\Http\CanonicalHttpSessionResolver;
use Identity\Repositories\InternalOperatorGrantRepository;
use Identity\Services\InternalCapabilityCatalog;
use PDO;

/** Minimized governance projection. No account enumeration, personal-data reader or commands. */
final class ReadService
{
    public function __construct(private PDO $pdo, private CanonicalHttpSessionResolver $sessions, private bool $admissionNames=false) {}

    public function authority(array $cookies): array
    {
        $session=$this->sessions->resolve($cookies);
        if(!$session)throw new ReadFailure(401,'authentication_required');
        $id=$session->accountId();
        $query=$this->pdo->prepare("SELECT s.governance_class,s.status FROM internal_staff s JOIN auth_accounts a ON a.account_id=s.account_id WHERE s.account_id=? AND a.status='active'");
        $query->execute([$id]);$staff=$query->fetch(PDO::FETCH_ASSOC);
        if(!$staff||$staff['status']!=='ACTIVE')throw new ReadFailure(403,'team_read_denied');
        $director=$staff['governance_class']==='DIRECTOR';
        if(!$director&&($staff['governance_class']!=='MASTER_ADMIN'||!(new InternalOperatorGrantRepository($this->pdo))->activeCapabilities($id)->contains(InternalCapabilityCatalog::MANAGE_ADVISORS)))throw new ReadFailure(403,'team_read_denied');
        return ['class'=>$staff['governance_class'],'delegations'=>$director?[]:$this->delegations($id),'can_read_masters'=>$director,'grant_scope'=>$director?'all_operational':'delegated_operational_only','mutations_enabled'=>getenv('MXMED_TEAM_ADVISOR_MUTATIONS_ENABLED')==='1'];
    }

    public function read(array $cookies,array $input): array
    {
        // New instance/queries on every request: no authorization cache or browser role input.
        $authority=$this->authority($cookies);
        $viewer=$this->sessions->resolve($cookies)?->accountId();
        if($viewer===null)throw new ReadFailure(401,'authentication_required');
        foreach($input as $key=>$value)if(!in_array($key,['category','page','page_size','q','target','status'],true)||!is_string($value))throw new ReadFailure(400,'invalid_query');
        $category=$input['category']??'ADVISOR';
        if(!in_array($category,['ADVISOR','MASTER_ADMIN'],true))throw new ReadFailure(400,'invalid_category');
        if(!$authority['can_read_masters']&&$category!=='ADVISOR')throw new ReadFailure(403,'team_read_denied');
        $query=$input['q']??'';
        if($query!==''&&(!preg_match('/^[\p{L}\p{N} ._:\x{0027}-]{1,64}$/uD',$query)))throw new ReadFailure(400,'invalid_search');
        $page=$this->integer($input['page']??'1',1,1000);$size=$this->integer($input['page_size']??'20',1,50);
        $target=$input['target']??null;
        if($target!==null&&!preg_match('/^[A-Za-z0-9_.:-]{1,64}$/D',$target))throw new ReadFailure(400,'invalid_target');
        // Lists default to current membership; exact target reads retain historical access.
        $status=$input['status']??($target===null?'ACTIVE':'ALL');
        if(!in_array($status,['ACTIVE','SUSPENDED','REMOVED','ALL'],true))throw new ReadFailure(400,'invalid_status');
        $where='s.governance_class=?';$params=[$category];
        if($target!==null){$where.=' AND s.account_id=?';$params[]=$target;}
        elseif($query!==''){
            $where.=' AND (LOCATE(?,s.account_id)>0';$params[]=$query;
            // Only the connected admission reader enables names, and only for Director.
            // Same completed, current, non-self, non-revoked profiles as its authorized projection.
            if($this->admissionNames&&$authority['can_read_masters']){
                $where.=' OR EXISTS (SELECT 1 FROM auth_staff_admission_completions d JOIN auth_staff_admission_cases c ON c.case_id=d.case_id JOIN auth_staff_admission_versions v ON v.case_id=c.case_id AND v.version=c.version WHERE d.account_id=s.account_id AND c.bound_account_id<>? AND c.state<>? AND LOCATE(?,CONCAT_WS(CHAR(32),v.given_names,v.first_surname,v.second_surname))>0)';
                array_push($params,$viewer,'REVOKED',$query);
            }
            $where.=')';
        }
        if($status!=='ALL'){$where.=' AND s.status=?';$params[]=$status;}
        // Category predicate is mandatory also for target reads: a Master cannot probe peers/Director.
        $select=$this->pdo->prepare('SELECT s.account_id,s.governance_class,s.status,a.status AS account_status FROM internal_staff s JOIN auth_accounts a ON a.account_id=s.account_id WHERE '.$where.' ORDER BY s.account_id LIMIT '.($target!==null?1:$size+1).' OFFSET '.($target!==null?0:($page-1)*$size));
        $select->execute($params);$rows=$select->fetchAll(PDO::FETCH_ASSOC);
        if($target!==null&&!$rows)throw new ReadFailure(404,'member_not_available');
        $hasMore=$target===null&&count($rows)>$size;$rows=array_slice($rows,0,$size);
        $items=[];
        foreach($rows as $row){
            $grants=$this->grants($row['account_id'],$authority);
            $operational=array_values(array_filter($grants,static fn($key)=>InternalCapabilityCatalog::all()[$key]['kind']==='operational'));
            $items[]=['account_reference'=>$row['account_id'],'governance_class'=>$row['governance_class'],'staff_status'=>$row['status'],'account_status'=>$row['account_status'],'active_operational_grant_count'=>count($operational),'grants'=>$operational,
                'management_granted'=>$authority['can_read_masters']&&in_array(InternalCapabilityCatalog::MANAGE_ADVISORS,$grants,true),
                'master_delegations'=>$authority['can_read_masters']&&$row['governance_class']==='MASTER_ADMIN'?$this->delegations($row['account_id']):null,
                'editable_capabilities'=>$this->editable($row,$authority,$viewer),
                'lifecycle_operations'=>$authority['mutations_enabled']&&$row['account_id']!==$viewer&&$row['governance_class']==='ADVISOR'&&$row['account_status']==='active'&&in_array($row['status'],['ACTIVE','SUSPENDED'],true)?[$row['status']==='ACTIVE'?'suspend':'reactivate']:[],
                'removal_allowed'=>$authority['mutations_enabled']&&$authority['class']==='DIRECTOR'&&$row['account_id']!==$viewer&&$row['governance_class']==='ADVISOR'&&in_array($row['status'],['ACTIVE','SUSPENDED'],true),
                'master_actions'=>$this->masterActions($row,$authority,$viewer,$grants),
                'personal_profile'=>null,'identity_review'=>null,'commercial_activity'=>null,'history'=>$target!==null?ActivityProjection::read($this->pdo,$row['account_id'],$authority):null];
        }
        return ['authority'=>$authority,'catalog'=>InternalCapabilityCatalog::all(),'items'=>$items,'page'=>$page,'page_size'=>$size,'has_more'=>$hasMore,'category'=>$category,'status'=>$status,'grant_semantics'=>'stored_active; use_requires_current_consumer_authority','personal_profile_source'=>'NOT_CONNECTED','identity_review_source'=>'NOT_CONNECTED','commercial_source'=>'NOT_CONNECTED'];
    }

    /** Availability only; the privileged composition and locked writer authorize each action. */
    private function masterActions(array $row,array $authority,string $viewer,array $grants):array
    {
        $result=['state'=>'NO_AUTHORITY','appointment'=>'MISSING_IDENTITY_REVIEW','operations'=>[]];
        if(!$authority['can_read_masters']||$row['account_id']===$viewer)return $result;
        if(getenv('MXMED_MASTER_GOVERNANCE_ENABLED')!=='1'){$result['state']='MASTER_GATE_DISABLED';return $result;}
        if($row['governance_class']==='ADVISOR'){$result['state']='MISSING_IDENTITY_REVIEW';return $result;}
        if($row['governance_class']!=='MASTER_ADMIN'||!in_array($row['status'],['ACTIVE','SUSPENDED'],true)||!in_array($row['account_status'],['active','disabled'],true))return $result;
        $result['state']='READY_FOR_EXACT_ACTION';$result['operations'][]=['operation'=>'INTERNAL_MASTER_REMOVE','capability'=>null];
        $increase=$row['status']==='ACTIVE'&&$row['account_status']==='active';
        $manage=in_array(InternalCapabilityCatalog::MANAGE_ADVISORS,$grants,true);
        if($manage||$increase)$result['operations'][]=['operation'=>$manage?'INTERNAL_ADVISOR_MANAGEMENT_REVOKE':'INTERNAL_ADVISOR_MANAGEMENT_GRANT','capability'=>InternalCapabilityCatalog::MANAGE_ADVISORS];
        $delegations=$this->delegations($row['account_id']);
        foreach(InternalCapabilityCatalog::all() as $key=>$definition){
            if($definition['kind']!=='operational'||!$definition['delegable'])continue;
            $assigned=in_array($key,$delegations,true);
            if($assigned||$increase)$result['operations'][]=['operation'=>$assigned?'INTERNAL_CAPABILITY_DELEGATION_REVOKE':'INTERNAL_CAPABILITY_DELEGATE','capability'=>$key];
        }
        return $result;
    }

    /** UI eligibility projection only; every command still uses canonical locked authority.
     * Preserve the approved UI exclusions for incomplete generic profile consumers and
     * corrections unavailable to Advisors. No new grant or consumer authority is created. */
    private function editable(array $row,array $authority,string $viewer): array
    {
        if(!$authority['mutations_enabled']||$row['account_id']===$viewer||$row['governance_class']!=='ADVISOR'||$row['status']!=='ACTIVE'||$row['account_status']!=='active')return [];
        return array_keys(array_filter(InternalCapabilityCatalog::all(),static fn($c)=>$c['kind']==='operational'&&$c['delegable']
            &&(in_array($c['domain'],['media_review','physician_requests','physician_operator_support','physician_accreditation','professional_sources','provider_claim','provider_commercial'],true)||$c['key']==='physician_administrative_read')
            &&($authority['can_read_masters']||in_array($c['key'],$authority['delegations'],true))));
    }

    private function grants(string $id,array $authority): array
    {
        $s=$this->pdo->prepare("SELECT capability FROM internal_operator_grants WHERE account_id=? AND status='ACTIVE' AND revoked_at IS NULL ORDER BY capability");$s->execute([$id]);
        return array_values(array_filter($s->fetchAll(PDO::FETCH_COLUMN),static fn($key)=>InternalCapabilityCatalog::known($key)&&($authority['can_read_masters']||in_array($key,$authority['delegations'],true))));
    }
    private function delegations(string $id): array
    {
        $s=$this->pdo->prepare("SELECT capability FROM internal_capability_delegations WHERE master_account_id=? AND status='ACTIVE' AND revoked_at IS NULL ORDER BY capability");$s->execute([$id]);
        return array_values(array_filter($s->fetchAll(PDO::FETCH_COLUMN),static fn($key)=>InternalCapabilityCatalog::known($key)&&InternalCapabilityCatalog::all()[$key]['delegable']));
    }
    private function integer(string $input,int $min,int $max): int
    {
        if(!preg_match('/^[1-9][0-9]{0,3}$/D',$input)||($value=(int)$input)<$min||$value>$max)throw new ReadFailure(400,'invalid_page');return $value;
    }
}
