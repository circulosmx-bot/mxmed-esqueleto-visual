<?php
declare(strict_types=1);
namespace Billing\Services;

use PDO;

require_once __DIR__.'/SatCfdiCatalog.php';
require_once __DIR__.'/BillingIssuanceAudit.php';

final class IssuerProfileService
{
    public function __construct(private PDO $pdo, private SatCfdiCatalog $catalog) {}

    public function list(string $doctorId): array
    {
        $q=$this->pdo->prepare('SELECT issuer_profile_id,alias,issuer_legal_name,rfc,fiscal_regime_code,expedition_postal_code,is_default,created_at,updated_at FROM billing_issuer_profiles WHERE doctor_id=? AND archived_at IS NULL ORDER BY is_default DESC, created_at,issuer_profile_id');
        $q->execute([$doctorId]); return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    public function get(string $doctorId,string $id,bool $lock=false): array
    {
        $q=$this->pdo->prepare('SELECT * FROM billing_issuer_profiles WHERE doctor_id=? AND issuer_profile_id=? AND archived_at IS NULL'.($lock && $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''));
        $q->execute([$doctorId,$id]); $row=$q->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new \DomainException('issuer_profile_not_found');
        return $row;
    }

    public function create(string $doctorId,array $input): array
    {
        $fields=$this->normalize($input); $id=self::uuid();
        return $this->transaction(function() use($doctorId,$input,$fields,$id):array {
            $this->lockDoctor($doctorId);
            $default=($input['is_default']??false) || $this->list($doctorId)===[];
            if ($default) $this->clearDefault($doctorId);
            $q=$this->pdo->prepare('INSERT INTO billing_issuer_profiles (issuer_profile_id,doctor_id,alias,issuer_legal_name,rfc,fiscal_regime_code,expedition_postal_code,is_default) VALUES (?,?,?,?,?,?,?,?)');
            $q->execute([$id,$doctorId,$fields['alias'],$fields['issuer_legal_name'],$fields['rfc'],$fields['fiscal_regime_code'],$fields['expedition_postal_code'],$default?1:0]);
            (new BillingIssuanceAudit($this->pdo))->record($doctorId,'ISSUER_CREATED','SUCCESS',null,null,$id);
            return $this->get($doctorId,$id);
        });
    }

    public function update(string $doctorId,string $id,array $input):array
    {
        $fields=$this->normalize($input);
        return $this->transaction(function() use($doctorId,$id,$input,$fields):array {
            $this->lockDoctor($doctorId); $this->get($doctorId,$id,true);
            $q=$this->pdo->prepare('UPDATE billing_issuer_profiles SET alias=?,issuer_legal_name=?,rfc=?,fiscal_regime_code=?,expedition_postal_code=? WHERE doctor_id=? AND issuer_profile_id=? AND archived_at IS NULL');
            $q->execute([$fields['alias'],$fields['issuer_legal_name'],$fields['rfc'],$fields['fiscal_regime_code'],$fields['expedition_postal_code'],$doctorId,$id]);
            if (($input['is_default']??false)===true) $this->setDefaultLocked($doctorId,$id);
            (new BillingIssuanceAudit($this->pdo))->record($doctorId,'ISSUER_UPDATED','SUCCESS',null,null,$id);
            return $this->get($doctorId,$id);
        });
    }

    public function makeDefault(string $doctorId,string $id):array
    {
        return $this->transaction(function() use($doctorId,$id):array {
            $this->lockDoctor($doctorId); $this->get($doctorId,$id,true); $this->setDefaultLocked($doctorId,$id);
            (new BillingIssuanceAudit($this->pdo))->record($doctorId,'ISSUER_DEFAULT_CHANGED','SUCCESS',null,null,$id);
            return $this->get($doctorId,$id);
        });
    }

    public function archive(string $doctorId,string $id):void
    {
        $this->transaction(function() use($doctorId,$id):void {
            $this->lockDoctor($doctorId); $this->get($doctorId,$id,true);
            $q=$this->pdo->prepare('UPDATE billing_issuer_profiles SET archived_at=CURRENT_TIMESTAMP,is_default=0 WHERE doctor_id=? AND issuer_profile_id=?');
            $q->execute([$doctorId,$id]);
            (new BillingIssuanceAudit($this->pdo))->record($doctorId,'ISSUER_ARCHIVED','SUCCESS',null,null,$id);
        });
    }

    private function normalize(array $input):array
    {
        if (array_diff(array_keys($input),['alias','issuer_legal_name','rfc','fiscal_regime_code','expedition_postal_code','is_default'])
          || isset($input['is_default']) && !is_bool($input['is_default'])) throw new \InvalidArgumentException('invalid_issuer_fields');
        foreach (['alias','issuer_legal_name','rfc','fiscal_regime_code','expedition_postal_code'] as $key)
            if (!is_string($input[$key]??null)) throw new \InvalidArgumentException('invalid_issuer_fields');
        $alias=trim($input['alias']); $name=mb_strtoupper(trim($input['issuer_legal_name']),'UTF-8');
        $rfc=mb_strtoupper(trim($input['rfc']),'UTF-8'); $regime=trim($input['fiscal_regime_code']); $zip=trim($input['expedition_postal_code']);
        if ($alias==='' || mb_strlen($alias)>80 || preg_match('/[\x00-\x1f\x7f]/',$alias)) throw new \InvalidArgumentException('invalid_alias');
        if ($name==='' || mb_strlen($name)>254 || preg_match('/[\x00-\x1f\x7f]/',$name)) throw new \InvalidArgumentException('invalid_issuer_legal_name');
        if (!preg_match('/^[A-Z&Ñ]{3,4}[0-9]{6}[A-Z0-9]{3}$/uD',$rfc) || !in_array(mb_strlen($rfc),[12,13],true)) throw new \InvalidArgumentException('invalid_rfc');
        if (!preg_match('/^[0-9]{5}$/D',$zip)) throw new \InvalidArgumentException('invalid_expedition_postal_code');
        $this->catalog->validateRegime($rfc,$regime);
        return ['alias'=>$alias,'issuer_legal_name'=>$name,'rfc'=>$rfc,'fiscal_regime_code'=>$regime,'expedition_postal_code'=>$zip];
    }

    private function lockDoctor(string $doctorId):void
    {
        $sql='SELECT doctor_id FROM profiles_doctors WHERE doctor_id=?';
        if($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')$sql.=' FOR UPDATE';
        $q=$this->pdo->prepare($sql); $q->execute([$doctorId]);
        if (!$q->fetchColumn()) throw new \DomainException('doctor_not_found');
    }
    private function clearDefault(string $doctorId):void { $q=$this->pdo->prepare('UPDATE billing_issuer_profiles SET is_default=0 WHERE doctor_id=? AND archived_at IS NULL'); $q->execute([$doctorId]); }
    private function setDefaultLocked(string $doctorId,string $id):void { $this->clearDefault($doctorId); $q=$this->pdo->prepare('UPDATE billing_issuer_profiles SET is_default=1 WHERE doctor_id=? AND issuer_profile_id=? AND archived_at IS NULL'); $q->execute([$doctorId,$id]); }
    private function transaction(callable $fn):mixed { $this->pdo->beginTransaction();try {$out=$fn();$this->pdo->commit();return $out;}catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;} }
    public static function uuid():string { $b=random_bytes(16);$b[6]=chr((ord($b[6])&15)|64);$b[8]=chr((ord($b[8])&63)|128);$h=bin2hex($b);return substr($h,0,8).'-'.substr($h,8,4).'-'.substr($h,12,4).'-'.substr($h,16,4).'-'.substr($h,20); }
}
