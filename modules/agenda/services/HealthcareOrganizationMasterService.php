<?php
declare(strict_types=1);
namespace Agenda\Services;

use InvalidArgumentException;
use PDO;
use PDOException;
use RuntimeException;

/** Organization service configuration. This grants no access by itself. */
final class HealthcareOrganizationMasterService
{
    public function __construct(private PDO $pdo) {}

    public function list(string $group): array
    {
        $stmt=$this->pdo->prepare('SELECT m.*,s.study_type_key,s.display_name_es,s.is_active AS study_type_active
            FROM healthcare_organization_master_services m JOIN clinical_study_types s ON s.study_type_id=m.study_type_id
            WHERE m.group_id=? ORDER BY s.display_name_es,m.master_service_id');
        $stmt->execute([$group]);
        return array_map($this->public(...),$stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function read(string $group,string $uuid): array
    {
        $stmt=$this->pdo->prepare('SELECT m.*,s.study_type_key,s.display_name_es,s.is_active AS study_type_active
            FROM healthcare_organization_master_services m JOIN clinical_study_types s ON s.study_type_id=m.study_type_id
            WHERE m.group_id=? AND m.master_service_uuid=?');
        $stmt->execute([$group,$uuid]);$row=$stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) throw new RuntimeException('master_service_not_found');
        return $this->public($row);
    }

    /** The legacy offering writer calls this in the same transaction as its insert. */
    public function resolveOrCreate(string $group,int $study): int
    {
        $active=$this->pdo->prepare('SELECT is_active FROM clinical_study_types WHERE study_type_id=? FOR UPDATE');
        $active->execute([$study]);$value=$active->fetchColumn();
        if ($value===false) throw new InvalidArgumentException('study_type_not_found');
        if ((int)$value!==1) throw new InvalidArgumentException('study_type_inactive');
        $this->pdo->prepare('INSERT INTO healthcare_organization_master_services
            (master_service_uuid,group_id,study_type_id) VALUES (?,?,?)
            ON DUPLICATE KEY UPDATE master_service_id=LAST_INSERT_ID(master_service_id)')
            ->execute([self::uuid(),$group,$study]);
        $id=(int)$this->pdo->lastInsertId();
        $state=$this->pdo->prepare('SELECT operational_state FROM healthcare_organization_master_services WHERE master_service_id=? FOR UPDATE');
        $state->execute([$id]);
        if ($state->fetchColumn()!=='ACTIVE') throw new InvalidArgumentException('master_service_inactive');
        return $id;
    }

    public function create(string $group,int $study,array $data): array
    {
        $fields=$this->fields($data);
        $owns=!$this->pdo->inTransaction();if($owns)$this->pdo->beginTransaction();
        try {
            $active=$this->pdo->prepare('SELECT is_active FROM clinical_study_types WHERE study_type_id=? FOR UPDATE');
            $active->execute([$study]);$value=$active->fetchColumn();
            if($value===false)throw new InvalidArgumentException('study_type_not_found');
            if((int)$value!==1)throw new InvalidArgumentException('study_type_inactive');
            $uuid=self::uuid();$names=array_keys($fields);
            $sql='INSERT INTO healthcare_organization_master_services (master_service_uuid,group_id,study_type_id'.($names?','.implode(',',$names):'').') VALUES (?,?,?'.($names?','.implode(',',array_fill(0,count($names),'?')):'').')';
            $this->pdo->prepare($sql)->execute([$uuid,$group,$study,...array_values($fields)]);
            if($owns)$this->pdo->commit();return $this->read($group,$uuid);
        }catch(PDOException $e){if($owns&&$this->pdo->inTransaction())$this->pdo->rollBack();if($e->getCode()==='23000')throw new InvalidArgumentException('master_service_already_exists',0,$e);throw $e;}
        catch(\Throwable $e){if($owns&&$this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

    public function update(string $group,string $uuid,array $data): array
    {
        $this->read($group,$uuid);$fields=$this->fields($data);
        if($fields===[])throw new InvalidArgumentException('no_master_service_changes');
        $set=implode(',',array_map(static fn($key)=>$key.'=?',array_keys($fields)));
        $this->pdo->prepare('UPDATE healthcare_organization_master_services SET '.$set.' WHERE group_id=? AND master_service_uuid=?')
            ->execute([...array_values($fields),$group,$uuid]);
        return $this->read($group,$uuid);
    }

    private function fields(array $data): array
    {
        $allowed=['commercial_unit','internal_cost','default_public_price','public_visibility','default_preparation_instructions','default_requires_appointment','operational_state'];
        $fields=[];foreach($data as $key=>$value){
            if(!in_array($key,$allowed,true))throw new InvalidArgumentException('unsupported_master_field');
            if($key==='commercial_unit'){if(!in_array($value,['STUDY','SESSION'],true))throw new InvalidArgumentException('invalid_commercial_unit');$fields[$key]=$value;}
            elseif(in_array($key,['internal_cost','default_public_price'],true)){
                if($value!==null&&(!is_string($value)||!preg_match('/^(0|[1-9][0-9]{0,9})(\.[0-9]{1,2})?$/D',$value)))throw new InvalidArgumentException('invalid_money');$fields[$key]=$value;
            }elseif(in_array($key,['public_visibility','default_requires_appointment'],true)){
                if(!is_bool($value))throw new InvalidArgumentException('invalid_boolean');$fields[$key]=$value?1:0;
            }elseif($key==='default_preparation_instructions'){
                if($value!==null&&(!is_string($value)||mb_strlen($value)>2000))throw new InvalidArgumentException('invalid_preparation');$fields[$key]=$value===null?null:trim($value);
            }else{if(!in_array($value,['ACTIVE','INACTIVE'],true))throw new InvalidArgumentException('invalid_operational_state');$fields[$key]=$value;}
        }return $fields;
    }

    private function public(array $row): array
    {
        unset($row['master_service_id']);
        $row['study_type_id']=(int)$row['study_type_id'];
        $row['public_visibility']=(bool)$row['public_visibility'];
        $row['default_requires_appointment']=(bool)$row['default_requires_appointment'];
        $row['study_type_active']=(bool)$row['study_type_active'];
        return $row;
    }

    private static function uuid(): string
    {
        $b=random_bytes(16);$b[6]=chr((ord($b[6])&15)|64);$b[8]=chr((ord($b[8])&63)|128);$h=bin2hex($b);
        return substr($h,0,8).'-'.substr($h,8,4).'-'.substr($h,12,4).'-'.substr($h,16,4).'-'.substr($h,20);
    }
}
