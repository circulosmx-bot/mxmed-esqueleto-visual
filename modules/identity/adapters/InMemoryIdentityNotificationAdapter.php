<?php
declare(strict_types=1);

namespace Identity\Adapters;

use Identity\Contracts\IdentityNotificationPort;
use Identity\Contracts\NotificationMessage;

final class InMemoryIdentityNotificationAdapter implements IdentityNotificationPort
{
    /** @var list<NotificationMessage> */
    private array $messages = [];

    public function send(NotificationMessage $message): void
    {
        $this->messages[] = $message;
    }

    /** @return list<NotificationMessage> */
    public function messages(): array { return $this->messages; }
    public function lastMessage(): ?NotificationMessage { return $this->messages[count($this->messages) - 1] ?? null; }
}
