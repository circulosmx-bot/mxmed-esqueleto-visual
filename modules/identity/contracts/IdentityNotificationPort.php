<?php
declare(strict_types=1);

namespace Identity\Contracts;

interface IdentityNotificationPort
{
    public function send(NotificationMessage $message): void;
}
