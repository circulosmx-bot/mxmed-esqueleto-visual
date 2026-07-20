<?php
declare(strict_types=1);

namespace Identity\Contracts;

final class NotificationMessage
{
    public function __construct(
        private string $purpose,
        private string $recipient,
        private string $token,
        private string $expiresAt
    ) {
        OneTimeTokenPurpose::assertValid($purpose);
        if ($recipient === '' || $token === '' || $expiresAt === '') throw new \InvalidArgumentException('invalid_notification_message');
    }

    public function purpose(): string { return $this->purpose; }
    public function recipient(): string { return $this->recipient; }
    public function token(): string { return $this->token; }
    public function expiresAt(): string { return $this->expiresAt; }
}
