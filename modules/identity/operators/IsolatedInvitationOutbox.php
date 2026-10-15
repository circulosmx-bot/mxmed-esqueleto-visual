<?php
declare(strict_types=1);
namespace Identity\Operators;
use Identity\Contracts\{IdentityNotificationPort,NotificationMessage};
use PDO;

/** Durable encrypted delivery in the SAME invitation transaction; never email. */
final class IsolatedInvitationOutbox implements IdentityNotificationPort
{
    private string $key;
    public function __construct(private PDO $pdo)
    {
        OperatorQaConfiguration::assertEnabled($pdo);
        $this->key=base64_decode((string)getenv('MXMED_OP02_OUTBOX_KEY'),true)?:'';
        if(strlen($this->key)!==SODIUM_CRYPTO_SECRETBOX_KEYBYTES)throw new \RuntimeException('op02_outbox_key_required');
    }
    public function send(NotificationMessage $message): void
    {
        if(!$this->pdo->inTransaction()||$message->purpose()!=='operator_invitation'||!str_ends_with($message->recipient(),'@example.invalid'))throw new \RuntimeException('op02_outbox_transaction_required');
        [$id,$token]=explode('.',$message->token(),2);
        $nonce=random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher=$nonce.sodium_crypto_secretbox($token,$nonce,$this->key);
        $this->pdo->prepare('INSERT INTO auth_operator_invitation_outbox(invitation_id,recipient,ciphertext) VALUES(?,?,?)')->execute([$id,$message->recipient(),$cipher]);
    }
    public function retire(string $id): void
    {
        if(!$this->pdo->inTransaction())throw new \RuntimeException('op02_outbox_transaction_required');
        $this->pdo->prepare('UPDATE auth_operator_invitation_outbox SET ciphertext=NULL,retired_at=UTC_TIMESTAMP(6) WHERE invitation_id=? AND ciphertext IS NOT NULL')->execute([$id]);
    }
    public function open(string $id,string $verifiedRecipient): string
    {
        if(!$this->pdo->inTransaction())throw new \RuntimeException('op02_outbox_transaction_required');
        $s=$this->pdo->prepare("SELECT o.ciphertext FROM auth_operator_invitation_outbox o JOIN auth_profile_operator_invitations i ON i.invitation_id=o.invitation_id JOIN auth_account_memberships m ON m.membership_id=i.owner_membership_id
            WHERE o.invitation_id=? AND o.recipient=? AND o.opened_at IS NULL AND i.status='pending' AND i.expires_at>UTC_TIMESTAMP(6) AND m.status='active' AND m.role_code='owner' FOR UPDATE");
        $s->execute([$id,$verifiedRecipient]);$cipher=$s->fetchColumn();
        if(!is_string($cipher))throw new \RuntimeException('op02_message_unavailable');
        $token=sodium_crypto_secretbox_open(substr($cipher,SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),substr($cipher,0,SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),$this->key);
        if(!is_string($token))throw new \RuntimeException('op02_message_unavailable');
        $this->pdo->prepare('UPDATE auth_operator_invitation_outbox SET ciphertext=NULL,opened_at=UTC_TIMESTAMP(6) WHERE invitation_id=? AND opened_at IS NULL')->execute([$id]);
        return $token;
    }
}
