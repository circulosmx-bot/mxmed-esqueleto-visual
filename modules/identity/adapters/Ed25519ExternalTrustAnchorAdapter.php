<?php
declare(strict_types=1);
namespace Identity\Adapters;

use Identity\Contracts\{Clock,FirstDirectorBootstrapAuthorization,FirstDirectorBootstrapAuthorizationPort,SystemClock};
use PDO;

/** Verifies externally signed, environment-bound bootstrap evidence. No key or evidence is trusted by default. */
final class Ed25519ExternalTrustAnchorAdapter implements FirstDirectorBootstrapAuthorizationPort
{
    private string $publicKey;
    public function __construct(private PDO $pdo,string $base64PublicKey,private string $organizationId,
        private string $environmentName,private Clock $clock=new SystemClock())
    {
        $key=base64_decode($base64PublicKey,true);
        if(!extension_loaded('sodium')||!is_string($key)||strlen($key)!==SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
            ||preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{2,127}$/D',$organizationId)!==1
            ||!in_array($environmentName,['local','staging','production'],true))
            throw new \RuntimeException('external_trust_anchor_configuration_required');
        $this->publicKey=$key;
    }

    /** Stores verified public metadata only; the signed envelope and signature are never persisted. */
    public function stage(string $envelope, array $expected=[]):string
    {
        [$payloadPart,$signaturePart]=$this->parts($envelope);$payload=self::decode($payloadPart);$signature=self::decode($signaturePart);
        if(strlen($signature)!==SODIUM_CRYPTO_SIGN_BYTES||!sodium_crypto_sign_verify_detached($signature,$payload,$this->publicKey))
            throw new \RuntimeException('external_trust_anchor_invalid_signature');
        $claims=json_decode($payload,true,32,JSON_THROW_ON_ERROR);if(!is_array($claims))throw new \RuntimeException('external_trust_anchor_invalid_payload');
        $required=['contract_version','operation','profile_doctor_id','membership_id','membership_version','requester_account_id','trust_anchor_reference','operation_id','target_account_id','organization_id','environment','case_reference','ratifier_session_reference','authorized_at','expires_at','ratifiers'];
        foreach($required as $key)if(!array_key_exists($key,$claims))throw new \RuntimeException('external_trust_anchor_invalid_payload');
        if($claims['contract_version']!==2 || !in_array($claims['operation'],['FIRST_DIRECTOR_BOOTSTRAP','INITIAL_OWNER','OWNER_REPLACEMENT','OWNER_REMOVAL','MFA_RECOVERY'],true))throw new \RuntimeException('external_trust_anchor_contract_version_required');
        foreach($expected as $key=>$value)if(!array_key_exists($key,$claims)||$claims[$key]!==$value)throw new \RuntimeException('external_trust_anchor_binding_mismatch');
        if($claims['operation']==='FIRST_DIRECTOR_BOOTSTRAP'){
            foreach(['profile_doctor_id','membership_id','membership_version','requester_account_id'] as $key)if($claims[$key]!==null)throw new \RuntimeException('external_trust_anchor_bootstrap_scope');
        }else{
            $this->reference((string)$claims['requester_account_id'],64);
            if($claims['operation']!=='MFA_RECOVERY'){
                foreach(['profile_doctor_id','membership_id'] as $key){if(!is_string($claims[$key])||preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,63}$/D',$claims[$key])!==1)throw new \RuntimeException('external_trust_anchor_target_required');}
                if(!is_string($claims['membership_version'])||preg_match('/^[a-f0-9]{64}$/D',$claims['membership_version'])!==1)throw new \RuntimeException('external_trust_anchor_version_required');
            }elseif($claims['profile_doctor_id']!==null||$claims['membership_id']!==null||$claims['membership_version']!==null)throw new \RuntimeException('external_trust_anchor_mfa_scope');
        }
        if($claims['organization_id']!==$this->organizationId||$claims['environment']!==$this->environmentName)throw new \RuntimeException('external_trust_anchor_wrong_audience');
        $ratifiers=$claims['ratifiers'];if(!is_array($ratifiers)||count($ratifiers)!==2)throw new \RuntimeException('external_trust_anchor_dual_ratification_required');
        $ids=[];$approvals=[];foreach($ratifiers as $ratifier){if(!is_array($ratifier)||($ratifier['mfa_verified']??false)!==true||($ratifier['reauthenticated']??false)!==true)throw new \RuntimeException('external_trust_anchor_r3_controls_required');$id=(string)($ratifier['account_id']??'');$approval=(string)($ratifier['approval_reference']??'');$this->reference($id,64);$this->reference($approval);$ids[]=$id;$approvals[]=$approval;}
        if(count(array_unique($ids,SORT_STRING))!==2||count(array_unique($approvals,SORT_STRING))!==2||in_array((string)$claims['target_account_id'],$ids,true)||in_array($claims['requester_account_id'],$ids,true))throw new \RuntimeException('external_trust_anchor_independence_required');
        $authorized=$this->date((string)$claims['authorized_at']);$expires=$this->date((string)$claims['expires_at']);$now=$this->clock->now();
        if($authorized>$now->modify('+60 seconds')||$expires<=$now||$expires<=$authorized||$expires>$authorized->modify('+15 minutes'))throw new \RuntimeException('external_trust_anchor_expired');
        $this->reference((string)$claims['trust_anchor_reference']);$this->reference((string)$claims['case_reference']);$this->reference((string)$claims['ratifier_session_reference']);$this->reference((string)$claims['target_account_id'],64);
        if(preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/iD',(string)$claims['operation_id'])!==1)throw new \RuntimeException('external_trust_anchor_invalid_operation');
        try{$s=$this->pdo->prepare("INSERT INTO auth_external_trust_anchor_evidence(trust_anchor_reference,operation_id,target_account_id,organization_id,environment_name,case_reference,ratifier_session_reference,ratifier_one_id,ratifier_two_id,approval_one_reference,approval_two_reference,payload_hash,signature_hash,status,authorized_at,expires_at,contract_version,operation,profile_doctor_id,membership_id,membership_version,requester_account_id) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,'VERIFIED',?,?,?,?,?,?,?,?)");
            $s->execute([$claims['trust_anchor_reference'],$claims['operation_id'],$claims['target_account_id'],$claims['organization_id'],$claims['environment'],$claims['case_reference'],$claims['ratifier_session_reference'],$ids[0],$ids[1],$approvals[0],$approvals[1],hash('sha256',$payload),hash('sha256',$signature),$this->sql($authorized),$this->sql($expires),2,$claims['operation'],$claims['profile_doctor_id'],$claims['membership_id'],$claims['membership_version'],$claims['requester_account_id']]);
        }catch(\PDOException $e){if((string)$e->getCode()==='23000')throw new \RuntimeException('external_trust_anchor_replay_denied',0,$e);throw $e;}
        return (string)$claims['trust_anchor_reference'];
    }

    public function consume(PDO $transaction,string $operationId,string $targetAccountId):FirstDirectorBootstrapAuthorization
    {
        if($transaction!==$this->pdo||!$this->pdo->inTransaction())throw new \RuntimeException('external_trust_anchor_transaction_required');
        $s=$this->pdo->prepare('SELECT * FROM auth_external_trust_anchor_evidence WHERE operation_id=? AND target_account_id=? FOR UPDATE');$s->execute([$operationId,$targetAccountId]);$r=$s->fetch(PDO::FETCH_ASSOC);$now=$this->clock->now();
        if(!is_array($r)||(int)($r['contract_version']??0)!==2||$r['operation']!=='FIRST_DIRECTOR_BOOTSTRAP'||$r['organization_id']!==$this->organizationId||$r['environment_name']!==$this->environmentName||$r['status']!=='VERIFIED'||$this->date($r['expires_at'])<=$now)throw new \RuntimeException('external_trust_anchor_authorization_denied');
        $u=$this->pdo->prepare("UPDATE auth_external_trust_anchor_evidence SET status='CONSUMED',consumed_at=? WHERE trust_anchor_reference=? AND status='VERIFIED'");$u->execute([$this->sql($now),$r['trust_anchor_reference']]);if($u->rowCount()!==1)throw new \RuntimeException('external_trust_anchor_replay_denied');
        return new FirstDirectorBootstrapAuthorization($r['operation_id'],$r['target_account_id'],$r['case_reference'],$r['trust_anchor_reference'],$r['ratifier_session_reference'],[$r['ratifier_one_id'],$r['ratifier_two_id']],[$r['ratifier_one_id']=>$r['approval_one_reference'],$r['ratifier_two_id']=>$r['approval_two_reference']],true,true,$this->date($r['authorized_at']),$this->date($r['expires_at']));
    }

    public function assertCase(array $expected, bool $consume=false):void
    {
        if(!$this->pdo->inTransaction())throw new \RuntimeException('external_trust_anchor_transaction_required');
        $s=$this->pdo->prepare('SELECT * FROM auth_external_trust_anchor_evidence WHERE operation_id=? FOR UPDATE');$s->execute([$expected['operation_id']]);$r=$s->fetch(PDO::FETCH_ASSOC);
        if(!is_array($r)||(int)$r['contract_version']!==2||$r['organization_id']!==$this->organizationId||$r['environment_name']!==$this->environmentName||$r['status']!=='VERIFIED'||$this->date($r['expires_at'])<=$this->clock->now())throw new \RuntimeException('external_trust_case_unavailable');
        foreach($expected as $key=>$value)if(($r[$key]??null)!==$value)throw new \RuntimeException('external_trust_case_mismatch');
        if($consume){$u=$this->pdo->prepare("UPDATE auth_external_trust_anchor_evidence SET status='CONSUMED',consumed_at=? WHERE operation_id=? AND status='VERIFIED'");$u->execute([$this->sql($this->clock->now()),$expected['operation_id']]);if($u->rowCount()!==1)throw new \RuntimeException('external_trust_replay');}
    }

    private function parts(string $v):array{$p=explode('.',$v);if(count($p)!==2)throw new \RuntimeException('external_trust_anchor_invalid_envelope');return $p;}
    private static function decode(string $v):string{$v=strtr($v,'-_','+/');$v.=str_repeat('=',(4-strlen($v)%4)%4);$d=base64_decode($v,true);if(!is_string($d))throw new \RuntimeException('external_trust_anchor_invalid_envelope');return $d;}
    private function reference(string $v,int $max=128):void{if(strlen($v)>$max||preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{7,127}$/D',$v)!==1)throw new \RuntimeException('external_trust_anchor_invalid_reference');}
    private function date(string $v):\DateTimeImmutable{try{return new \DateTimeImmutable($v,new \DateTimeZone('UTC'));}catch(\Throwable){throw new \RuntimeException('external_trust_anchor_invalid_time');}}
    private function sql(\DateTimeImmutable $v):string{return $v->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');}
}
