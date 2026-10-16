<?php
declare(strict_types=1);
namespace Identity\Accreditation;

use Identity\Contracts\AuthenticatedAccessContext;
use Agenda\Contracts\{OtpProviderPort,RejectingOtpProvider};
use PDO;
require_once __DIR__.'/../../agenda/contracts/OtpProviderPort.php';

/** Account/contact/version-bound SMS OTP. Real composition has no default delivery. */
final class PhoneVerificationService
{
    public function usesConnection(PDO $pdo):bool {return $pdo===$this->a->pdo;}
    public function __construct(private EvidenceAuthority $a,private EvidenceCipher $cipher,
        private EvidenceAudit $audit,private OtpProviderPort $delivery=new RejectingOtpProvider())
    { if (!$audit->usesConnection($a->pdo)) throw new \RuntimeException('c4_audit_connection_mismatch'); }

    public function issue(AuthenticatedAccessContext $actor,string $phone,string $email,string $password): array
    {
        if (!$this->delivery->configured()) throw new \RuntimeException('c4_sms_delivery_unavailable');
        if (!preg_match('/^\+[1-9][0-9]{7,14}$/D',$phone)) throw new \InvalidArgumentException('c4_phone_e164_required');
        $binding=$this->a->security->reauthenticate($actor,$email,$password,['ip'=>null]);
        $code=str_pad((string)random_int(0,999999),6,'0',STR_PAD_LEFT);
        $issued=$this->a->transaction($actor,function()use($actor,$phone,$binding,$code):array {
            $this->a->applicant($actor->accountId());$this->a->security->assertLive($binding,true);
            $old=$this->a->query('SELECT * FROM auth_account_phone_verifications WHERE account_id=? FOR UPDATE',[$actor->accountId()])->fetch(PDO::FETCH_ASSOC);
            $now=$this->a->clock->now();$window=$this->a->sqlTime();$count=1;
            if (is_array($old)) {
                if (new \DateTimeImmutable($old['last_sent_at'],new \DateTimeZone('UTC'))>$now->modify('-60 seconds'))
                    throw new \RuntimeException('c4_phone_resend_throttled');
                if (new \DateTimeImmutable($old['send_window_at'],new \DateTimeZone('UTC'))>$now->modify('-1 hour')) {
                    $window=$old['send_window_at'];$count=(int)$old['send_count']+1;
                    if ($count>5) throw new \RuntimeException('c4_phone_send_limit');
                }
            }
            $version=is_array($old)?(int)$old['contact_version']+1:1;$id=$this->a->uuid();
            $sealed=$this->cipher->seal($phone,'phone:'.$actor->accountId().':'.$version);
            $hash=$this->cipher->digest('phone-otp:'.$actor->accountId().':'.$version.':'.$id,$code);
            $audit=$this->audit->append($actor,$id,'PHONE_CHALLENGE',$version,$old['status']??'ABSENT','PENDING');
            $values=[$version,$this->cipher->digest('phone-contact',$phone),$sealed['bytes'],$sealed['key_id'],$id,$hash,
                json_encode($binding,JSON_THROW_ON_ERROR),$this->a->sqlTime($now->modify('+10 minutes')),$window,$count,$this->a->sqlTime(),$audit,$actor->accountId()];
            if (is_array($old)) $this->a->query("UPDATE auth_account_phone_verifications SET contact_version=?,contact_digest=?,contact_ciphertext=?,key_id=?,
                challenge_id=?,challenge_hash=?,session_context=?,expires_at=?,send_window_at=?,send_count=?,last_sent_at=?,audit_reference=?,status='PENDING',verified_at=NULL,attempts=0,delivery_accepted=0 WHERE account_id=?",$values);
            else $this->a->query("INSERT INTO auth_account_phone_verifications(contact_version,contact_digest,contact_ciphertext,key_id,challenge_id,challenge_hash,
                session_context,expires_at,send_window_at,send_count,last_sent_at,audit_reference,account_id,status) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,'PENDING')",$values);
            return ['challenge_id'=>$id,'contact_version'=>$version];
        });
        // Reserve/rate-limit and audit BEFORE delivery. SQL failure cannot send an active OTP.
        // Provider acknowledgement is not verification; a failed acknowledgement commit denies use.
        try { $accepted=$this->delivery->deliver('sms',$phone,$code,['challenge_id'=>$issued['challenge_id']])->accepted(); }
        catch (\Throwable) { $accepted=false; }
        return $this->a->transaction($actor,function()use($actor,$issued,$accepted):array {
            $r=$this->a->query('SELECT * FROM auth_account_phone_verifications WHERE account_id=? FOR UPDATE',[$actor->accountId()])->fetch(PDO::FETCH_ASSOC);
            if (!is_array($r)||$r['challenge_id']!==$issued['challenge_id']||(int)$r['contact_version']!==$issued['contact_version'])
                throw new \RuntimeException('c4_phone_challenge_replaced');
            $event=$this->audit->append($actor,$issued['challenge_id'],'PHONE_DELIVERY',$issued['contact_version'],'PENDING',$accepted?'PENDING':'REVOKED');
            $this->a->query('UPDATE auth_account_phone_verifications SET delivery_accepted=?,status=?,audit_reference=? WHERE account_id=?',
                [$accepted?1:0,$accepted?'PENDING':'REVOKED',$event,$actor->accountId()]);
            return $issued+['delivery_accepted'=>$accepted];
        });
    }

    public function verify(AuthenticatedAccessContext $actor,string $challengeId,int $version,string $code): bool
    {
        return $this->a->transaction($actor,function()use($actor,$challengeId,$version,$code):bool {
            $r=$this->a->query('SELECT * FROM auth_account_phone_verifications WHERE account_id=? FOR UPDATE',[$actor->accountId()])->fetch(PDO::FETCH_ASSOC);
            if (!is_array($r)||$r['challenge_id']!==$challengeId||(int)$r['contact_version']!==$version||$r['status']!=='PENDING'
                ||(int)$r['delivery_accepted']!==1||(int)$r['attempts']>=5) return false;
            $binding=json_decode($r['session_context'],true,32,JSON_THROW_ON_ERROR);
            if (($binding['session_id']??null)!==(string)$actor->session()->sessionId()) throw new \RuntimeException('c4_phone_session_mismatch');
            $this->a->security->assertLive($binding);
            $expired=new \DateTimeImmutable($r['expires_at'],new \DateTimeZone('UTC'))<=$this->a->clock->now();
            $valid=!$expired&&preg_match('/^[0-9]{6}$/D',$code)===1&&hash_equals($r['challenge_hash'],
                $this->cipher->digest('phone-otp:'.$actor->accountId().':'.$version.':'.$challengeId,$code,$r['key_id']));
            $attempts=(int)$r['attempts']+1;$status=$valid?'VERIFIED':(($expired||$attempts>=5)?'REVOKED':'PENDING');
            $event=$this->audit->append($actor,$challengeId,$valid?'PHONE_VERIFIED':'PHONE_DENIED',$version,$r['status'],$status);
            $this->a->query('UPDATE auth_account_phone_verifications SET attempts=?,status=?,verified_at=?,audit_reference=? WHERE account_id=?',
                [$attempts,$status,$valid?$this->a->sqlTime():null,$event,$actor->accountId()]);
            return $valid;
        });
    }
}
