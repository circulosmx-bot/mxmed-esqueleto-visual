<?php
declare(strict_types=1);

namespace Media\Storage;

use Media\Contracts\PublicMediaStoragePort;
use RuntimeException;

require_once __DIR__ . '/../contracts/PublicMediaStoragePort.php';

final class LocalPersistentPublicMediaStorage implements PublicMediaStoragePort
{
    private string $root;

    public function __construct(string $root)
    {
        $root = rtrim(trim($root), DIRECTORY_SEPARATOR);
        if ($root === '' || !$this->isAbsolutePath($root)) {
            throw new RuntimeException('public_media_root_must_be_absolute');
        }
        $this->root = $root;
    }

    public function storeImmutable(string $storageKey, string $sourcePath): void
    {
        $target = $this->pathForKey($storageKey);
        if (!is_file($sourcePath)) {
            throw new RuntimeException('public_media_source_missing');
        }
        if (is_file($target)) {
            throw new RuntimeException('public_media_immutable_key_exists');
        }

        $directory = dirname($target);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('public_media_directory_create_failed');
        }
        chmod($directory, 0700);

        $temporaryTarget = $directory . DIRECTORY_SEPARATOR . '.' . basename($target) . '.' . bin2hex(random_bytes(8)) . '.tmp';
        try {
            if (!copy($sourcePath, $temporaryTarget)) {
                throw new RuntimeException('public_media_copy_failed');
            }
            chmod($temporaryTarget, 0600);
            if (!rename($temporaryTarget, $target)) {
                throw new RuntimeException('public_media_atomic_publish_failed');
            }
        } finally {
            if (is_file($temporaryTarget)) {
                unlink($temporaryTarget);
            }
        }
    }

    public function openReadStream(string $storageKey): array
    {
        $path = $this->pathForKey($storageKey);
        if (!is_file($path)) {
            throw new RuntimeException('public_media_object_not_found');
        }
        $stream = fopen($path, 'rb');
        if (!is_resource($stream)) {
            throw new RuntimeException('public_media_object_unreadable');
        }
        return [
            'stream' => $stream,
            'bytes' => (int)(filesize($path) ?: 0),
        ];
    }

    public function exists(string $storageKey): bool
    {
        return is_file($this->pathForKey($storageKey));
    }

    public function delete(string $storageKey): void
    {
        $path = $this->pathForKey($storageKey);
        if (is_file($path) && !unlink($path)) {
            throw new RuntimeException('public_media_delete_failed');
        }
    }

    private function pathForKey(string $storageKey): string
    {
        if (preg_match('#^public/(?:physician-personal-logo|consultorio-group-logo|doctor-gallery|doctor-profile-photo)/[a-f0-9]{64}/[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\.webp$#', $storageKey) !== 1) {
            throw new RuntimeException('invalid_public_media_storage_key');
        }
        return $this->root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $storageKey);
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR)
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
    }
}
