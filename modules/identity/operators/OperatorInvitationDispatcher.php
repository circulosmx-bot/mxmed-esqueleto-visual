<?php
declare(strict_types=1);
namespace Identity\Operators;
use Identity\Contracts\{Clock,SystemClock,NotificationMessage,OneTimeTokenPurpose};
use PDO;
/** An explicitly composed worker only. HTTP enqueue never constructs/calls a transport. */
final class OperatorInvitationDispatcher
{
    public function __construct(private PDO $pdo,private OperatorInvitationTransport $transport,private string $key,private string $keyId,private Clock $clock=new SystemClock())
    {if(strlen($key)!==SODIUM_CRYPTO_SECRETBOX_KEYBYTES)throw new \RuntimeException('operator_delivery_key_required');}
    public function dispatchOne():array
    {
        if($this->pdo->inTransaction())throw new \RuntimeException('operator_dispatch_after_commit_required');
        $s=$this->pdo->prepare("SELECT d.delivery_id,i.profile_doctor_id FROM auth_identity_notification_deliveries d JOIN auth_profile_operator_invitations i ON i.invitation_id=d.invitation_id WHERE (d.state IN ('queued','retry') AND d.next_attempt_at<=?) OR (d.state='dispatching' AND d.lease_until<=?) ORDER BY d.created_at LIMIT 1");$s->execute([$this->now(),$this->now()]);$candidate=$s->fetch(PDO::FETCH_ASSOC);if(!$candidate)return ['state'=>'idle'];
        $lease=bin2hex(random_bytes(16));$row=null;
        try{
            $this->pdo->beginTransaction();$s=$this->pdo->prepare('SELECT doctor_id FROM profiles_doctors WHERE doctor_id=? FOR UPDATE');$s->execute([$candidate['profile_doctor_id']]);
            $s=$this->pdo->prepare('SELECT * FROM auth_identity_notification_deliveries WHERE delivery_id=? FOR UPDATE');$s->execute([$candidate['delivery_id']]);$row=$s->fetch(PDO::FETCH_ASSOC);
            if(!$row||!in_array($row['state'],['queued','retry','dispatching'],true)){$this->pdo->commit();return ['state'=>'skipped'];}
            if($row['state']==='dispatching'){
                if($row['lease_until']>$this->now()){$this->pdo->commit();return ['state'=>'skipped'];}
                $this->pdo->prepare("UPDATE auth_identity_notification_deliveries SET state='uncertain',ciphertext=NULL,lease_token=NULL,lease_until=NULL WHERE delivery_id=?")->execute([$row['delivery_id']]);
                $this->pdo->commit();return ['state'=>'uncertain'];
            }
            if($row['next_attempt_at']>$this->now()){$this->pdo->commit();return ['state'=>'skipped'];}
            $s=$this->pdo->prepare("SELECT i.invitation_id FROM auth_profile_operator_invitations i JOIN auth_account_memberships m ON m.membership_id=i.owner_membership_id AND m.profile_doctor_id=i.profile_doctor_id JOIN auth_accounts a ON a.account_id=m.account_id WHERE i.invitation_id=? AND i.status='pending' AND i.expires_at>? AND m.role_code='owner' AND m.status='active' AND a.status='active' AND a.email_verified_at IS NOT NULL FOR UPDATE");$s->execute([$row['invitation_id'],$this->now()]);
            if($s->fetchColumn()===false||$row['expires_at']<=$this->now()){
                $this->pdo->prepare("UPDATE auth_identity_notification_deliveries SET state='retired',ciphertext=NULL,lease_token=NULL,lease_until=NULL WHERE delivery_id=?")->execute([$row['delivery_id']]);$this->pdo->commit();return ['state'=>'retired'];
            }
            if($row['key_id']!==$this->keyId)throw new \RuntimeException('operator_delivery_key_version_unavailable');
            $this->pdo->prepare("UPDATE auth_identity_notification_deliveries SET state='dispatching',attempt_count=attempt_count+1,lease_token=?,lease_until=? WHERE delivery_id=?")->execute([$lease,$this->clock->now()->modify('+2 minutes')->format('Y-m-d H:i:s.u'),$row['delivery_id']]);
            $this->pdo->commit();
        }catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
        $state='uncertain';$providerId=null;
        try{
            $cipher=$row['ciphertext'];$raw=is_string($cipher)?sodium_crypto_secretbox_open(substr($cipher,SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),substr($cipher,0,SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),$this->key):false;
            if(!is_string($raw))throw new OperatorDeliveryFailure('permanent_not_accepted');
            $providerId=$this->transport->deliver(new NotificationMessage(OneTimeTokenPurpose::OPERATOR_INVITATION,$row['recipient'],$raw,$row['expires_at']));sodium_memzero($raw);
            if($providerId==='')throw new OperatorDeliveryFailure('uncertain');$state='provider_accepted';
        }catch(OperatorDeliveryFailure $e){$state=$e->outcome==='retryable_not_accepted'?((int)$row['attempt_count']+1<5?'retry':'failed'):($e->outcome==='permanent_not_accepted'?'failed':'uncertain');}
        catch(\Throwable){$state='uncertain';}
        finally{if(isset($raw)&&is_string($raw))sodium_memzero($raw);}
        $next=$this->clock->now()->modify('+'.min(3600,30*(2**(int)$row['attempt_count'])).' seconds')->format('Y-m-d H:i:s.u');
        $s=$this->pdo->prepare("UPDATE auth_identity_notification_deliveries SET state=?,provider_message_id=?,next_attempt_at=?,ciphertext=IF(?='retry',ciphertext,NULL),lease_until=NULL,lease_token=NULL WHERE delivery_id=? AND state='dispatching' AND lease_token=?");$s->execute([$state,$providerId,$next,$state,$row['delivery_id'],$lease]);
        return ['state'=>$s->rowCount()===1?$state:'retired']; // A concurrent cancellation wins; no false delivery success.
    }
    private function now():string{return $this->clock->now()->format('Y-m-d H:i:s.u');}
}
