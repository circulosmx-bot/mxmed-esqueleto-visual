<?php
declare(strict_types=1);
namespace Identity\PhysicianAdministration;
use PDO;
/** Versioned administrative projection contract; works with canonical or isolated source PDOs. */
final class SqlSource implements Source
{
    public function __construct(private \Closure $connect,private string $prefix,private bool $joinCanonicalTransaction=false,private int $projectionVersion=1)
    {if(!preg_match('/^[a-z][a-z0-9_]{1,40}$/D',$prefix))throw new \InvalidArgumentException('admin_source_invalid');}
    private function read(bool $operators,\Closure $read):array
    {
        $p=($this->connect)($operators);if(!$p instanceof PDO)throw new \RuntimeException('admin_source_unavailable');
        if($p->inTransaction()){if(!$this->joinCanonicalTransaction)throw new \RuntimeException('admin_source_unavailable');$p->exec('SET SESSION MAX_EXECUTION_TIME=1500');return $read($p);}
        try{$p->exec('SET SESSION MAX_EXECUTION_TIME=1500');$p->exec('SET TRANSACTION READ ONLY');$p->beginTransaction();$v=$read($p);$p->rollBack();return $v;}
        catch(\Throwable $e){if($p->inTransaction())$p->rollBack();throw new \RuntimeException('admin_source_unavailable');}
    }
    private function rows(PDO $p,string $sql,array $args):array{$s=$p->prepare($sql);$s->execute($args);return $s->fetchAll(PDO::FETCH_ASSOC);}
    public function search(array $ids,string $query,string $after,int $limit):array
    {
        if(!$ids)return [];if(count($ids)>100||$limit<1||$limit>20)throw new \InvalidArgumentException('admin_scope_invalid');
        return $this->read(false,fn($p)=>$this->rows($p,'SELECT doctor_id,display_name,specialty_primary,profile_status FROM '.$this->prefix.'_profiles WHERE doctor_id IN ('.implode(',',array_fill(0,count($ids),'?')).') AND doctor_id>? AND (display_name LIKE ? ESCAPE \'!\' OR doctor_id=?) ORDER BY doctor_id LIMIT '.($limit+1),[...$ids,$after,strtr($query,['!'=>'!!','%'=>'!%','_'=>'!_']).'%',$query]));
    }
    public function summary(string $id):?array
    {
        $r=$this->read(false,function($p)use($id){$r=$this->rows($p,'SELECT doctor_id,display_name,specialty_primary,profile_status'.($this->projectionVersion>=2?',portrait_path':'').' FROM '.$this->prefix.'_profiles WHERE doctor_id=?',[$id]);if(!$r)return [];
            $r[0]['consultorios']=$this->rows($p,'SELECT consultorio_id,titulo,municipio,estado FROM '.$this->prefix.'_locations WHERE doctor_id=? ORDER BY consultorio_id LIMIT 21',[$id]);return $r;});return $r[0]??null;
    }
    public function locations(string $id,string $after,int $limit):array
    {
        if($limit<1||$limit>20)throw new \InvalidArgumentException('admin_request_invalid');
        return $this->read(false,fn($p)=>$this->rows($p,'SELECT consultorio_id,titulo,municipio,estado'.($this->projectionVersion>=2?',calle,num_ext,num_int,cp,colonia,document_publication_enabled':'').' FROM '.$this->prefix.'_locations WHERE doctor_id=? AND consultorio_id>? ORDER BY consultorio_id LIMIT '.($limit+1),[$id,$after]));
    }
    public function operators(string $id):array
    {
        return $this->read(true,function($p)use($id){
            $r=$this->rows($p,'SELECT membership_id,role_code,status,account_status,contact_verified FROM '.$this->prefix.'_relationships WHERE profile_doctor_id=? ORDER BY membership_id LIMIT 101',[$id]);
            if(count($r)>100)throw new \RuntimeException('admin_inventory_unavailable');
            foreach($r as &$member)$member['contact_verified']=(bool)$member['contact_verified'];unset($member);
            $owners=array_values(array_filter($r,fn($m)=>$m['role_code']==='owner'&&$m['status']==='active'&&$m['account_status']==='active'&&(bool)$m['contact_verified']));
            return ['members'=>$r,'owner_state'=>count($owners)===1?'confirmed':(count($owners)>1?'ambiguous':'unavailable'),'invitations_available'=>false,'delegations_available'=>false,'support_actions'=>[]];
        });
    }
}
