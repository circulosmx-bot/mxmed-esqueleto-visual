<?php
declare(strict_types=1);
namespace Identity\PhysicianAdministration;
use Identity\Contracts\AuthenticatedAccessContext;
use Identity\Accreditation\EvidenceAuthority;
use Identity\PhysicianRequests\RequestAuthority;
use Identity\Repositories\InternalOperatorGrantRepository;
/** Authorization lives in canonical staff storage, never in the source credential. */
final class Reader
{
    public const CAPABILITY='physician_administrative_read';
    public function __construct(private EvidenceAuthority $a,private Source $source,private string $origin,private \Closure $scopes)
    {if(!preg_match('/^[a-z][a-z0-9_-]{1,40}$/D',$origin))throw new \InvalidArgumentException('admin_origin_invalid');}
    public function read(AuthenticatedAccessContext $actor,string $mode,string $ref='',string $query='',string $after='',int $limit=20):array
    {
        if(!in_array($mode,['admin_search','admin_summary','admin_operators','admin_locations'],true)||mb_strlen($query)>80||$limit<1||$limit>20||strlen($after)>64)throw new \InvalidArgumentException('admin_request_invalid');
        $id='';if($mode!=='admin_search'){if(!str_starts_with($ref,$this->origin.':'))throw new \RuntimeException('admin_resource_denied');$id=substr($ref,strlen($this->origin)+1);if(!preg_match('/^[A-Za-z0-9_-]{1,64}$/D',$id))throw new \InvalidArgumentException('admin_request_invalid');}
        return $this->a->transaction($actor,function()use($actor,$mode,$id,$query,$after,$limit){
            $staff=(new RequestAuthority($this->a->pdo,$this->a->clock))->assertActiveStaff($actor,true);
            $caps=(new InternalOperatorGrantRepository($this->a->pdo))->activeCapabilities($actor->accountId(),true);
            if(!$caps->contains(self::CAPABILITY)||($this->a->scope!==null&&!$this->a->scope->allowsStaff($actor->accountId(),self::CAPABILITY)))throw new \RuntimeException('admin_capability_denied');
            $scope=($this->scopes)($actor->accountId(),$this->origin);$ids=$scope[self::CAPABILITY]??[];
            if(!is_array($ids)||count($ids)>100||!$ids||array_filter($ids,fn($v)=>!is_string($v)||!preg_match('/^[A-Za-z0-9_-]{1,64}$/D',$v)))throw new \RuntimeException('admin_scope_denied');
            if($id!==''&&!in_array($id,$ids,true))throw new \RuntimeException('admin_resource_denied');
            $operators=$caps->contains('physician_operator_support_read')&&($this->a->scope===null||$this->a->scope->allowsStaff($actor->accountId(),'physician_operator_support_read'))&&in_array($id,$scope['physician_operator_support_read']??[],true);
            $locations=$caps->contains('physician_locations_read')&&($this->a->scope===null||$this->a->scope->allowsStaff($actor->accountId(),'physician_locations_read'))&&in_array($id,$scope['physician_locations_read']??[],true);
            if($mode==='admin_locations'&&!$locations)throw new \RuntimeException('admin_locations_denied');
            if($mode==='admin_operators'&&!$operators)throw new \RuntimeException('admin_operator_denied');
            if($mode==='admin_search'){$rows=$this->source->search($ids,$query,$after,$limit);$more=count($rows)>$limit;$rows=array_slice($rows,0,$limit);$result=['physicians'=>array_map(fn($r)=>$this->profile($r),$rows),'next_after'=>$more?end($rows)['doctor_id']:null,'limit'=>$limit];}
            elseif($mode==='admin_locations'){$rows=$this->source->locations($id,$after,$limit);$more=count($rows)>$limit;$rows=array_slice($rows,0,$limit);$result=['doctor_id'=>$this->origin.':'.$id,'locations'=>$rows,'next_after'=>$more?(string)end($rows)['consultorio_id']:null,'limit'=>$limit,'provenance'=>'consultorios'];}
            elseif($mode==='admin_operators')$result=$this->source->operators($id)+['doctor_id'=>$this->origin.':'.$id];
            else{$row=$this->source->summary($id);if($row===null)throw new \RuntimeException('admin_profile_not_found');$loc=$row['consultorios'];$more=count($loc)>20;$loc=array_slice($loc,0,20);$result=['physician'=>$this->profile($row)+['city'=>$loc[0]['municipio']??null,'state'=>$loc[0]['estado']??null,'consultorios_count'=>count($loc),'consultorios_has_more'=>$more,'portrait_path'=>$row['portrait_path']??null,'public_reference'=>null,'publication_verified'=>false],'permissions'=>['operators'=>$operators,'offices'=>$locations],'pending_items'=>[],'sections'=>[],'activity'=>[]];}
            if(($this->scopes)($actor->accountId(),$this->origin)!==$scope)throw new \RuntimeException('admin_scope_denied');
            // Same-source canonical audit commits with staff authorization; source is an independent read, not a distributed transaction.
            (new ReadAudit($this->a->pdo))->append($actor,$staff['governance_class'],$this->origin.':'.($id?:'search'),$mode);
            return $result;
        });
    }
    private function profile(array $r):array{return ['doctor_id'=>$this->origin.':'.$r['doctor_id'],'canonical_id'=>$r['doctor_id'],'origin'=>$this->origin,'name'=>$r['display_name'],'specialty'=>$r['specialty_primary'],'status'=>$r['profile_status'],'provenance'=>['name'=>'profiles_doctors.display_name','specialty'=>'profiles_doctors.specialty_primary','status'=>'profiles_doctors.profile_status','locations'=>'consultorios']];}
}
