<?php
declare(strict_types=1);

namespace Identity\Adapters;

use Identity\Contracts\IdentityNotificationPort;
use Identity\Contracts\NotificationMessage;

/** Preview-only notification sink. It is read by local CLI QA, never HTTP. */
final class PreviewIdentityNotificationAdapter implements IdentityNotificationPort
{
    public function __construct(private string $path)
    {
        if ($this->path === '' || str_starts_with($this->path, '/tmp/') !== true) throw new \InvalidArgumentException('preview_notification_path_required');
    }

    public function send(NotificationMessage $message): void
    {
        $dir = dirname($this->path);
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) throw new \RuntimeException('preview_notification_storage_unavailable');
        $messages = [];
        if (is_file($this->path)) {
            $decoded = json_decode((string)file_get_contents($this->path), true);
            if (is_array($decoded)) $messages = $decoded;
        }
        $messages[] = ['purpose' => $message->purpose(), 'recipient' => $message->recipient(), 'token' => $message->token(), 'expires_at' => $message->expiresAt()];
        if (file_put_contents($this->path, json_encode($messages, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), LOCK_EX) === false) throw new \RuntimeException('preview_notification_storage_unavailable');
        @chmod($this->path, 0600);
    }
}
