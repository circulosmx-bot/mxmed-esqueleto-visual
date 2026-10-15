<?php
declare(strict_types=1);
namespace Identity\Services;

use Identity\Adapters\SecureRandomMfaSecretGenerator;
use Identity\Contracts\{Clock,MfaSecretGeneratorPort,SystemClock};
use PDO;

/** RFC 6238 TOTP with encrypted secrets, bounded drift, action binding, throttling and durable replay prevention. */
final class StaffMfaService
{
    private string $key;
    public function __construct(private PDO $pdo,string $base64EncryptionKey,private string $keyId,
        private MfaSecretGeneratorPort $secrets=new SecureRandomMfaSecretGenerator(),private Clock $clock=new SystemClock())
    {
        $key=base64_decode($base64EncryptionKey,true);
        if(!extension_loaded('sodium')||!is_string($key)||strlen($key)!==SODIUM_CRYPTO_SECRETBOX_KEYBYTES
            ||preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{2,63}$/D',$keyId)!==1)
            throw new \RuntimeException('staff_mfa_configuration_required');
        $this->key=$key;
    }

    /** Enrollment is deliberately not exposed by the governance HTTP API. */
    public function beginEnrollment(string $accountId,string $issuer='MXMED'):array
    {
        $this->assertId($accountId);$this->assertActiveStaff($accountId);
        $secret=$this->secrets->generate();if(strlen($secret)!==20)throw new \RuntimeException('staff_mfa_secret_generation_failed');
        $factor='mfa_'.bin2hex(random_bytes(16));$nonce=random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher=$nonce.sodium_crypto_secretbox($secret,$nonce,$this->key);
        $s=$this->pdo->prepare("INSERT INTO auth_staff_mfa_factors(factor_id,account_id,factor_type,secret_ciphertext,key_id,status) VALUES(?,?,'TOTP',?,?,'PENDING')");
        $s->execute([$factor,$accountId,$cipher,$this->keyId]);$encoded=self::base32Encode($secret);
        return ['factor_id'=>$factor,'secret'=>$encoded,'otpauth_uri'=>'otpauth://totp/'.rawurlencode($issuer.':'.$accountId).'?secret='.$encoded.'&issuer='.rawurlencode($issuer).'&algorithm=SHA1&digits=6&period=30'];
    }

    public function activateEnrollment(string $accountId,string $factorId,string $code):void
    {
        $this->transaction(function()use($accountId,$factorId,$code):void{
            $row=$this->factor($factorId,true);
            if($row['account_id']!==$accountId||$row['status']!=='PENDING')throw new \RuntimeException('staff_mfa_enrollment_denied');
            $step=$this->matchingStep($this->decrypt($row),$code,$this->clock->now()->getTimestamp(),0);
            if($step===null)throw new \RuntimeException('staff_mfa_enrollment_denied');
            $s=$this->pdo->prepare("UPDATE auth_staff_mfa_factors SET status='ACTIVE',verified_at=?,last_totp_time_step=? WHERE factor_id=? AND status='PENDING'");
            $s->execute([$this->sql($this->clock->now()),$step,$factorId]);if($s->rowCount()!==1)throw new \RuntimeException('staff_mfa_enrollment_conflict');
        });
    }

    public function issueChallenge(string $actor,string $operation,string $caseId,string $target,string $beneficiary,?string $profile):array
    {
        foreach([$actor,$target,$beneficiary] as $id)$this->assertId($id);$this->assertReference($caseId);
        if(!in_array($operation,['INITIAL_OWNER','OWNER_REPLACEMENT','OWNER_REMOVAL','FIRST_DIRECTOR_BOOTSTRAP','MFA_RECOVERY'],true))throw new \InvalidArgumentException('staff_mfa_invalid_operation');
        $this->assertActiveStaff($actor);$now=$this->clock->now();$raw=rtrim(strtr(base64_encode(random_bytes(32)),'+/','-_'),'=');
        $id='challenge_'.bin2hex(random_bytes(16));$s=$this->pdo->prepare("INSERT INTO auth_staff_mfa_challenges(challenge_id,actor_account_id,operation,case_id,target_account_id,beneficiary_account_id,profile_doctor_id,nonce_hash,status,expires_at) VALUES(?,?,?,?,?,?,?,?,'PENDING',?)");
        $s->execute([$id,$actor,$operation,$caseId,$target,$beneficiary,$profile,hash('sha256',$raw),$this->sql($now->modify('+5 minutes'))]);
        return ['challenge_id'=>$id,'challenge'=>$raw,'expires_at'=>$now->modify('+5 minutes')];
    }

    public function verifyChallenge(string $actor,string $sessionId,int $credentialVersion,string $challengeId,string $rawChallenge,string $code):array
    {
        if($credentialVersion<1||strlen($sessionId)<8)throw new \RuntimeException('staff_mfa_verification_denied');
        $result=$this->transaction(function()use($actor,$sessionId,$credentialVersion,$challengeId,$rawChallenge,$code):array{
            $now=$this->clock->now();$s=$this->pdo->prepare('SELECT * FROM auth_staff_mfa_challenges WHERE challenge_id=? FOR UPDATE');$s->execute([$challengeId]);$c=$s->fetch(PDO::FETCH_ASSOC);
            if(!is_array($c)||$c['actor_account_id']!==$actor||$c['status']!=='PENDING'||new \DateTimeImmutable($c['expires_at'],new \DateTimeZone('UTC'))<=$now
                ||!hash_equals($c['nonce_hash'],hash('sha256',$rawChallenge))||($c['blocked_until']!==null&&new \DateTimeImmutable($c['blocked_until'],new \DateTimeZone('UTC'))>$now))
                throw new \RuntimeException('staff_mfa_verification_denied');
            $a=$this->pdo->prepare("SELECT a.status,c.credential_version,s.status staff_status,s.governance_class FROM auth_accounts a JOIN auth_account_credentials c ON c.account_id=a.account_id JOIN internal_staff s ON s.account_id=a.account_id WHERE a.account_id=? FOR UPDATE");$a->execute([$actor]);$authority=$a->fetch(PDO::FETCH_ASSOC);
            if(!is_array($authority)||$authority['status']!=='active'||$authority['staff_status']!=='ACTIVE'||!in_array($authority['governance_class'],['DIRECTOR','MASTER_ADMIN'],true)||(int)$authority['credential_version']!==$credentialVersion)
                throw new \RuntimeException('staff_mfa_verification_denied');
            $f=$this->pdo->prepare("SELECT * FROM auth_staff_mfa_factors WHERE account_id=? AND status='ACTIVE' FOR UPDATE");$f->execute([$actor]);$factor=$f->fetch(PDO::FETCH_ASSOC);
            $step=is_array($factor)?$this->matchingStep($this->decrypt($factor),$code,$now->getTimestamp(),1):null;
            if($step!==null&&$factor['last_totp_time_step']!==null&&$step<=(int)$factor['last_totp_time_step'])$step=null;
            if($step===null){$attempts=(int)$c['attempts_count']+1;$blocked=$attempts>=5?$this->sql($now->modify('+15 minutes')):null;$u=$this->pdo->prepare('UPDATE auth_staff_mfa_challenges SET attempts_count=?,blocked_until=? WHERE challenge_id=?');$u->execute([$attempts,$blocked,$challengeId]);return ['denied'=>true];}
            $proof='proof_'.bin2hex(random_bytes(16));$rawProof=rtrim(strtr(base64_encode(random_bytes(32)),'+/','-_'),'=');
            try{$i=$this->pdo->prepare("INSERT INTO auth_staff_mfa_proofs(proof_id,proof_hash,factor_id,challenge_id,actor_account_id,staff_session_id,credential_version,operation,case_id,target_account_id,beneficiary_account_id,profile_doctor_id,totp_time_step,status,verified_at,expires_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,'VERIFIED',?,?)");
                $i->execute([$proof,hash('sha256',$rawProof),$factor['factor_id'],$challengeId,$actor,$sessionId,$credentialVersion,$c['operation'],$c['case_id'],$c['target_account_id'],$c['beneficiary_account_id'],$c['profile_doctor_id'],$step,$this->sql($now),$this->sql($now->modify('+5 minutes'))]);
            }catch(\PDOException $e){if((string)$e->getCode()==='23000')throw new \RuntimeException('staff_mfa_replay_denied',0,$e);throw $e;}
            $advance=$this->pdo->prepare("UPDATE auth_staff_mfa_factors SET last_totp_time_step=? WHERE factor_id=? AND status='ACTIVE' AND (last_totp_time_step IS NULL OR last_totp_time_step<?)");$advance->execute([$step,$factor['factor_id'],$step]);if($advance->rowCount()!==1)throw new \RuntimeException('staff_mfa_replay_denied');
            $u=$this->pdo->prepare("UPDATE auth_staff_mfa_challenges SET status='CONSUMED',consumed_at=? WHERE challenge_id=? AND status='PENDING'");$u->execute([$this->sql($now),$challengeId]);
            return ['proof_id'=>$proof,'proof'=>$rawProof,'expires_at'=>$now->modify('+5 minutes')];
        });
        if(($result['denied']??false)===true)throw new \RuntimeException('staff_mfa_verification_denied');
        return $result;
    }

    public function consumeProof(PDO $transaction,string $proofId,string $rawProof,string $actor,string $sessionId,int $credentialVersion,
        string $operation,string $caseId,string $target,string $beneficiary,?string $profile,string $consumedBy):array
    {
        if($transaction!==$this->pdo||!$this->pdo->inTransaction())throw new \RuntimeException('staff_mfa_transaction_required');
        $s=$this->pdo->prepare('SELECT * FROM auth_staff_mfa_proofs WHERE proof_id=? FOR UPDATE');$s->execute([$proofId]);$p=$s->fetch(PDO::FETCH_ASSOC);$now=$this->clock->now();
        $exact=is_array($p)&&$p['actor_account_id']===$actor&&$p['staff_session_id']===$sessionId&&(int)$p['credential_version']===$credentialVersion
            &&$p['operation']===$operation&&$p['case_id']===$caseId&&$p['target_account_id']===$target&&$p['beneficiary_account_id']===$beneficiary
            &&($p['profile_doctor_id']??null)===$profile&&$p['status']==='VERIFIED'&&hash_equals($p['proof_hash'],hash('sha256',$rawProof))
            &&new \DateTimeImmutable($p['expires_at'],new \DateTimeZone('UTC'))>$now;
        if(!$exact)throw new \RuntimeException('staff_mfa_proof_denied');
        $u=$this->pdo->prepare("UPDATE auth_staff_mfa_proofs SET status='CONSUMED',consumed_at=?,consumed_by_reference=? WHERE proof_id=? AND status='VERIFIED'");$u->execute([$this->sql($now),$consumedBy,$proofId]);if($u->rowCount()!==1)throw new \RuntimeException('staff_mfa_proof_replay');
        return $p;
    }

    public function revokeFactor(PDO $transaction,string $factorId,string $accountId,string $approvedRecoveryCase):void
    {
        if($transaction!==$this->pdo||!$this->pdo->inTransaction())throw new \RuntimeException('staff_mfa_transaction_required');
        $c=$this->pdo->prepare("SELECT requester_account_id FROM auth_governance_cases WHERE case_id=? AND operation='MFA_RECOVERY' AND beneficiary_account_id=? AND status='APPROVED' AND expires_at>NOW(6) FOR UPDATE");$c->execute([$approvedRecoveryCase,$accountId]);$requester=$c->fetchColumn();if($requester===false)throw new \RuntimeException('staff_mfa_recovery_denied');
        $e=$this->pdo->prepare("SELECT e.approver_account_id,e.credential_version,c.credential_version current_version FROM auth_governance_case_endorsements e JOIN auth_accounts a ON a.account_id=e.approver_account_id AND a.status='active' AND a.email_verified_at IS NOT NULL JOIN auth_account_credentials c ON c.account_id=e.approver_account_id JOIN internal_staff s ON s.account_id=e.approver_account_id AND s.status='ACTIVE' AND s.governance_class IN ('DIRECTOR','MASTER_ADMIN') JOIN internal_operator_grants g ON g.account_id=e.approver_account_id AND g.capability='profile_initial_ownership_approve' AND g.status='ACTIVE' AND g.revoked_at IS NULL WHERE e.case_id=? AND e.status='APPROVED' FOR UPDATE");$e->execute([$approvedRecoveryCase]);$endorsements=$e->fetchAll(PDO::FETCH_ASSOC);if(count($endorsements)!==2||count(array_unique(array_column($endorsements,'approver_account_id'),SORT_STRING))!==2)throw new \RuntimeException('staff_mfa_recovery_denied');foreach($endorsements as $row)if((int)$row['credential_version']!==(int)$row['current_version'])throw new \RuntimeException('staff_mfa_recovery_denied');
        $u=$this->pdo->prepare("UPDATE auth_staff_mfa_factors SET status='REVOKED',revoked_at=NOW(6) WHERE factor_id=? AND account_id=? AND status='ACTIVE'");$u->execute([$factorId,$accountId]);if($u->rowCount()!==1)throw new \RuntimeException('staff_mfa_recovery_denied');
        $this->pdo->prepare("UPDATE auth_governance_case_endorsements SET status='CONSUMED',consumed_at=NOW(6) WHERE case_id=? AND status='APPROVED'")->execute([$approvedRecoveryCase]);
        $this->pdo->prepare("UPDATE auth_governance_cases SET status='CONSUMED',consumed_at=NOW(6) WHERE case_id=? AND status='APPROVED'")->execute([$approvedRecoveryCase]);
        $this->pdo->prepare("INSERT INTO auth_governance_events(event_id,case_id,event_type,actor_account_id,target_account_id,profile_doctor_id,reason_code,evidence_hash) VALUES(UUID(),?,'MFA_FACTOR_REVOKED',?,?,NULL,'SECURITY_RESPONSE',?)")->execute([$approvedRecoveryCase,$requester,$accountId,hash('sha256',$factorId)]);
    }

    private function factor(string $id,bool $lock):array{$s=$this->pdo->prepare('SELECT * FROM auth_staff_mfa_factors WHERE factor_id=?'.($lock?' FOR UPDATE':''));$s->execute([$id]);$r=$s->fetch(PDO::FETCH_ASSOC);if(!is_array($r))throw new \RuntimeException('staff_mfa_factor_unavailable');return $r;}
    private function decrypt(array $row):string{if($row['key_id']!==$this->keyId||strlen($row['secret_ciphertext'])<=SODIUM_CRYPTO_SECRETBOX_NONCEBYTES)throw new \RuntimeException('staff_mfa_key_unavailable');$nonce=substr($row['secret_ciphertext'],0,SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);$plain=sodium_crypto_secretbox_open(substr($row['secret_ciphertext'],SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),$nonce,$this->key);if(!is_string($plain))throw new \RuntimeException('staff_mfa_decryption_failed');return $plain;}
    private function matchingStep(string $secret,string $code,int $timestamp,int $drift):?int{if(preg_match('/^[0-9]{6}$/D',$code)!==1)return null;$base=intdiv($timestamp,30);for($d=-$drift;$d<=$drift;$d++){if(hash_equals(self::totp($secret,$base+$d),$code))return $base+$d;}return null;}
    public static function totp(string $secret,int $step):string{$counter=pack('N2',intdiv($step,0x100000000),$step%0x100000000);$h=hash_hmac('sha1',$counter,$secret,true);$o=ord($h[19])&15;$n=((ord($h[$o])&127)<<24)|(ord($h[$o+1])<<16)|(ord($h[$o+2])<<8)|ord($h[$o+3]);return str_pad((string)($n%1000000),6,'0',STR_PAD_LEFT);}
    private static function base32Encode(string $v):string{$alphabet='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';$bits='';foreach(str_split($v) as $c)$bits.=str_pad(decbin(ord($c)),8,'0',STR_PAD_LEFT);$out='';foreach(str_split($bits,5) as $chunk){$out.=$alphabet[bindec(str_pad($chunk,5,'0',STR_PAD_RIGHT))];}return $out;}
    private function assertActiveStaff(string $id):void{$s=$this->pdo->prepare("SELECT 1 FROM auth_accounts a JOIN internal_staff i ON i.account_id=a.account_id WHERE a.account_id=? AND a.status='active' AND a.email_verified_at IS NOT NULL AND i.status='ACTIVE'");$s->execute([$id]);if($s->fetchColumn()===false)throw new \RuntimeException('staff_mfa_staff_authority_required');}
    private function assertId(string $v):void{if(preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,63}$/D',$v)!==1)throw new \InvalidArgumentException('staff_mfa_invalid_identifier');}
    private function assertReference(string $v):void{if(preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{7,127}$/D',$v)!==1)throw new \InvalidArgumentException('staff_mfa_invalid_reference');}
    private function sql(\DateTimeImmutable $d):string{return $d->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');}
    private function transaction(callable $fn):mixed{if($this->pdo->inTransaction())throw new \RuntimeException('staff_mfa_outer_transaction_forbidden');try{$this->pdo->beginTransaction();$r=$fn();$this->pdo->commit();return $r;}catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}}
}
