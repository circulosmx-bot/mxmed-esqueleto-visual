<?php
declare(strict_types=1);

namespace Identity\Adapters;

use Identity\Contracts\IdentityNotificationPort;
use Identity\Contracts\NotificationMessage;

final class RejectingIdentityNotificationAdapter implements IdentityNotificationPort
{
    public function send(NotificationMessage $message): void
    {
        throw new \RuntimeException('notification_unavailable');
    }
}
