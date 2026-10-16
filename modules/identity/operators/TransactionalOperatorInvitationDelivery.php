<?php
declare(strict_types=1);
namespace Identity\Operators;
use Identity\Contracts\IdentityNotificationPort;
/** Enqueue/retire participate in the existing invitation transaction. Never dispatch here. */
interface TransactionalOperatorInvitationDelivery extends IdentityNotificationPort
{
    public function retire(string $invitationId): void;
    /** Minimal delivery presentation, never a token or recipient. */
    public function inboxState(string $invitationId): array;
}
