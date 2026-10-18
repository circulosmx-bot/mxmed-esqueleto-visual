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
    public function __construct(private PDO $pdo, private CanonicalHttpSessionResolver $sessions) {}

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
        foreach($input as $key=>$value)if(!in_array($key,['category','page','page_size','q','target'],true)||!is_string($value))throw new ReadFailure(400,'invalid_query');
        $category=$input['category']??'ADVISOR';
        if(!in_array($category,['ADVISOR','MASTER_ADMIN'],true))throw new ReadFailure(400,'invalid_category');
        if(!$authority['can_read_masters']&&$category!=='ADVISOR')throw new ReadFailure(403,'team_read_denied');
        $query=$input['q']??'';
        if($query!==''&&(!preg_match('/^[A-Za-z0-9_.:-]{2,64}$/D',$query)))throw new ReadFailure(400,'invalid_search');
        $page=$this->integer($input['page']??'1',1,1000);$size=$this->integer($input['page_size']??'20',1,50);
        $target=$input['target']??null;
        if($target!==null&&!preg_match('/^[A-Za-z0-9_.:-]{1,64}$/D',$target))throw new ReadFailure(400,'invalid_target');
        $where='s.governance_class=?';$params=[$category];
        if($target!==null){$where.=' AND s.account_id=?';$params[]=$target;}
        elseif($query!==''){$where.=' AND LOCATE(?,s.account_id)>0';$params[]=$query;}
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
                'personal_profile'=>null,'identity_review'=>null,'commercial_activity'=>null,'history'=>null];
        }
        return ['authority'=>$authority,'catalog'=>InternalCapabilityCatalog::all(),'items'=>$items,'page'=>$page,'page_size'=>$size,'has_more'=>$hasMore,'category'=>$category,'grant_semantics'=>'stored_active; use_requires_current_consumer_authority','personal_profile_source'=>'NOT_CONNECTED','identity_review_source'=>'NOT_CONNECTED','commercial_source'=>'NOT_CONNECTED'];
    }

    /** UI eligibility projection only; every command still uses canonical locked authority.
     * Preserve the approved UI exclusions for incomplete generic profile consumers and
     * corrections unavailable to Advisors. No new grant or consumer authority is created. */
    private function editable(array $row,array $authority,string $viewer): array
    {
        if(!$authority['mutations_enabled']||$row['account_id']===$viewer||$row['governance_class']!=='ADVISOR'||$row['status']!=='ACTIVE'||$row['account_status']!=='active')return [];
        return array_keys(array_filter(InternalCapabilityCatalog::all(),static fn($c)=>$c['kind']==='operational'&&$c['delegable']
            &&in_array($c['domain'],['media_review','physician_accreditation','professional_sources','provider_claim','provider_commercial'],true)
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
