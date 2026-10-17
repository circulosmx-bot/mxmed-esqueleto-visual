<?php
declare(strict_types=1);

namespace Identity\Contracts;

final class NotificationMessage
{
    public function __construct(
        private string $purpose,
        private string $recipient,
        private string $token,
        private string $expiresAt,
        private ?string $continuationPath=null
    ) {
        OneTimeTokenPurpose::assertValid($purpose);
        if ($recipient === '' || $token === '' || $expiresAt === '') throw new \InvalidArgumentException('invalid_notification_message');
        if($continuationPath!==null&&($purpose!==OneTimeTokenPurpose::EMAIL_VERIFICATION||!preg_match('~^/public/identity/perfil-medico\.php\?application=[a-f0-9-]{36}$~D',$continuationPath)))throw new \InvalidArgumentException('invalid_notification_continuation');
    }

    public function purpose(): string { return $this->purpose; }
    public function recipient(): string { return $this->recipient; }
    public function token(): string { return $this->token; }
    public function expiresAt(): string { return $this->expiresAt; }
    public function continuationPath():?string{return $this->continuationPath;}
}
