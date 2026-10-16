<?php
declare(strict_types=1);
namespace Identity\Operators;
use Identity\Contracts\{NotificationMessage,OneTimeTokenPurpose,Clock,SystemClock};
use PDO;
/** Durable encrypted Identity purpose. Has no transport/client and cannot send mail. */
final class DurableOperatorInvitationDelivery implements TransactionalOperatorInvitationDelivery
{
    public function __construct(private PDO $pdo,private string $key,private string $keyId,private Clock $clock=new SystemClock())
    {if(strlen($key)!==SODIUM_CRYPTO_SECRETBOX_KEYBYTES||!preg_match('/^[A-Za-z0-9_-]{1,64}$/D',$keyId))throw new \RuntimeException('operator_delivery_key_required');}
    private function active():void{if(!$this->pdo->inTransaction())throw new \RuntimeException('operator_delivery_transaction_required');}
    public function send(NotificationMessage $message):void
    {
        $this->active();if($message->purpose()!==OneTimeTokenPurpose::OPERATOR_INVITATION)throw new \RuntimeException('operator_delivery_purpose_denied');
        [$id,$token]=explode('.',$message->token(),2);$s=$this->pdo->prepare("SELECT invitation_id,email_normalized,created_at,expires_at FROM auth_profile_operator_invitations WHERE invitation_id=? AND status='pending' FOR UPDATE");$s->execute([$id]);$i=$s->fetch(PDO::FETCH_ASSOC);
        if(!$i||$i['email_normalized']!==$message->recipient()||$i['expires_at']!==$message->expiresAt()||$i['expires_at']!== (new \DateTimeImmutable($i['created_at'],new \DateTimeZone('UTC')))->modify('+7 days')->format('Y-m-d H:i:s.u'))throw new \RuntimeException('operator_delivery_binding_denied');
        $nonce=random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);$cipher=$nonce.sodium_crypto_secretbox($message->token(),$nonce,$this->key);
        $this->pdo->prepare("INSERT INTO auth_identity_notification_deliveries(delivery_id,purpose,invitation_id,recipient,ciphertext,key_id,state,expires_at,next_attempt_at,created_at) VALUES(?, 'operator_invitation', ?,?,?,?,'queued',?,?,?)")->execute(['opdelivery_'.bin2hex(random_bytes(16)),$id,$message->recipient(),$cipher,$this->keyId,$message->expiresAt(),$this->now(),$i['created_at']]);
    }
    public function retire(string $invitationId):void
    {
        $this->active();$this->pdo->prepare("UPDATE auth_identity_notification_deliveries SET state='retired',ciphertext=NULL,lease_token=NULL,lease_until=NULL WHERE invitation_id=? AND state<>'retired'")->execute([$invitationId]);
    }
    public function inboxState(string $invitationId):array
    {
        $s=$this->pdo->prepare('SELECT state FROM auth_identity_notification_deliveries WHERE invitation_id=?');$s->execute([$invitationId]);
        return ['opened_at'=>null,'requires_email_link'=>true,'delivery_state'=>$s->fetchColumn()?:'unavailable'];
    }
    private function now():string{return $this->clock->now()->format('Y-m-d H:i:s.u');}
}
