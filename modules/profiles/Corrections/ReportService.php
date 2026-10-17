<?php
declare(strict_types=1);
namespace Profiles\Corrections;
use Identity\Contracts\{Clock,SystemClock};
use Identity\Http\CsrfTokenService;
use PDO;

/** The sole public intake writer. It cannot mutate a physician or Identity account. */
final class ReportService
{
    public readonly ReportEligibility $eligibility;
    public readonly ReportAudit $audit;
    public function __construct(public readonly PDO $pdo,public readonly ReportCipher $cipher,private CsrfTokenService $csrf,
        public readonly ReportRatePolicy $rates,public readonly Clock $clock=new SystemClock())
    {
        if(!$rates->usesConnection($pdo))throw new \RuntimeException('correction_shared_rate_storage_required');
        $this->eligibility=new ReportEligibility($pdo);$this->audit=new ReportAudit($pdo);
    }
    public function now():string{return $this->clock->now()->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');}
    public function uuid():string{return (new \Platform\Services\RandomAuditUuidProvider())->generateCanonicalUuid();}
    public function query(string $sql,array $args=[]):\PDOStatement
    {$q=$this->pdo->prepare($sql);$q->execute($args);return $q;}
    public function intent(string $profile,string $route,string $browser):array
    {
        $context=$this->eligibility->resolve($profile);
        if($route!==$context['route']||!preg_match('/^[a-f0-9]{64}$/D',$browser))throw new ReportDenied(403);
        $csrf=$this->csrf->issuePreAuth();
        $data=['profile'=>$profile,'route'=>$route,'browser'=>$this->cipher->digest('browser',$browser),
            'csrf'=>$this->cipher->digest('csrf',$csrf),'expires'=>$this->clock->now()->getTimestamp()+900,'nonce'=>bin2hex(random_bytes(24))];
        $encoded=rtrim(strtr(base64_encode(json_encode($data,JSON_THROW_ON_ERROR)),'+/','-_'),'=');
        return ['intent'=>$encoded.'.'.$this->cipher->digest('intent',$encoded),'csrf_token'=>$csrf];
    }
    private function origin(string $ticket,string $csrf,string $browser,string $refererRoute):array
    {
        if(strlen($ticket)>2048||!preg_match('/^([A-Za-z0-9_-]+)\.([a-f0-9]{64})$/D',$ticket,$m)
            ||!hash_equals($m[2],$this->cipher->digest('intent',$m[1]))||!$this->csrf->validPreAuth($csrf))throw new ReportDenied(403);
        $r=json_decode((string)base64_decode(strtr($m[1],'-_','+/'),true),true,12,JSON_THROW_ON_ERROR);
        if(!is_array($r)||!is_int($r['expires']??null)||$r['expires']<=$this->clock->now()->getTimestamp()
            ||$r['expires']>$this->clock->now()->getTimestamp()+900
            ||!preg_match('/^[a-f0-9]{64}$/D',$browser)
            ||!hash_equals($r['browser']??'',$this->cipher->digest('browser',$browser))
            ||!hash_equals($r['csrf']??'',$this->cipher->digest('csrf',$csrf))
            ||($r['route']??null)!==$refererRoute||($r['route']??null)!==ReportEligibility::route((string)($r['profile']??'')))throw new ReportDenied(403);
        return $r;
    }
    public function submit(string $ticket,string $csrf,string $browser,string $peer,string $refererRoute,array $input):array
    {
        $origin=$this->origin($ticket,$csrf,$browser,$refererRoute);$payload=ReportCatalog::payload($input);
        $intent=$this->cipher->digest('idempotency',$ticket);$command=$this->cipher->digest('command',json_encode($payload,JSON_THROW_ON_ERROR));
        $this->eligibility->resolve($origin['profile']);
        $existing=$this->query('SELECT report_id,command_digest FROM profiles_correction_reports WHERE intent_digest=?',[$intent])->fetch(PDO::FETCH_ASSOC);
        if(is_array($existing))return $this->receipt($existing,$command,true);
        // Reserve abuse budget durably BEFORE the intake transaction. Audit/storage rollback
        // must not give an attacker an unlimited retry budget. Exact successful replay is free.
        $abuse=$this->rates->consume($browser,$peer,$origin['profile']);
        if($this->pdo->inTransaction())throw new ReportDenied(503);
        try {
            $this->pdo->beginTransaction();$this->eligibility->resolve($origin['profile'],true);
            $existing=$this->query('SELECT report_id,command_digest FROM profiles_correction_reports WHERE intent_digest=? FOR UPDATE',[$intent])->fetch(PDO::FETCH_ASSOC);
            if(is_array($existing)){$result=$this->receipt($existing,$command,true);$this->pdo->commit();return $result;}
            $id=$this->uuid();$audit=$this->audit->append($id,'RECEIVED',1,'NONE','PENDING_REVIEW');
            $payload['profile']=$origin['profile'];$payload['original_route']=$origin['route'];
            $this->query('INSERT INTO profiles_correction_reports(report_id,profile_doctor_id,original_route,catalog_version,category,payload_ciphertext,key_id,retention_policy,intent_digest,command_digest,abuse_reference,audit_reference,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)',
                [$id,$origin['profile'],$origin['route'],ReportCatalog::VERSION,$payload['category'],$this->cipher->seal('report:'.$id.':'.$origin['profile'],$payload),
                    $this->cipher->keyId,$this->cipher->retentionPolicy,$intent,$command,$abuse,$audit,$this->now()]);
            $this->pdo->commit();return ['receipt_id'=>$id,'replayed'=>false];
        }catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }
    private function receipt(array $row,string $command,bool $replayed):array
    {if(!hash_equals($row['command_digest'],$command))throw new ReportDenied(409);return ['receipt_id'=>$row['report_id'],'replayed'=>$replayed];}
    public function report(string $id,?int $version=null):array
    {
        if(!$this->pdo->inTransaction()||!preg_match('/^[a-f0-9-]{36}$/D',$id))throw new ReportDenied(404);
        $r=$this->query('SELECT * FROM profiles_correction_reports WHERE report_id=? FOR UPDATE',[$id])->fetch(PDO::FETCH_ASSOC);
        if(!is_array($r))throw new ReportDenied(404);
        $last=$this->query('SELECT version,status FROM profiles_correction_decisions WHERE report_id=? ORDER BY version DESC LIMIT 1',[$id])->fetch(PDO::FETCH_ASSOC);
        $r['version']=(int)($last['version']??1);$r['status']=$last['status']??'PENDING_REVIEW';
        if($version!==null&&$r['version']!==$version)throw new ReportDenied(409);
        return $r;
    }
}
