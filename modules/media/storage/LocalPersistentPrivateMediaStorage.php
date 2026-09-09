<?php
declare(strict_types=1);
namespace Media\Storage;

use Media\Contracts\PrivateMediaStoragePort;
use RuntimeException;
require_once __DIR__.'/../contracts/PrivateMediaStoragePort.php';

final class LocalPersistentPrivateMediaStorage implements PrivateMediaStoragePort
{
    private string $root;

    public function __construct(string $root, array $forbiddenRoots = [])
    {
        $this->root = self::canonicalPath($root);
        // Never permit the application tree (including storage/) or document root.
        $forbiddenRoots[] = dirname(__DIR__, 3);
        if (!empty($_SERVER['DOCUMENT_ROOT'])) $forbiddenRoots[] = $_SERVER['DOCUMENT_ROOT'];
        foreach ($forbiddenRoots as $forbidden) {
            $path = self::canonicalPath($forbidden);
            if ($this->root === $path || str_starts_with($this->root, $path.'/')
                || str_starts_with($path, $this->root.'/')) {
                throw new RuntimeException('private_media_root_overlaps_public_root');
            }
        }
    }

    private static function canonicalPath(string $path): string
    {
        $path = rtrim(trim($path), '/');
        if ($path === '' || !str_starts_with($path, '/') || preg_match('#/(?:\.\.?)(?:/|$)#', $path)) {
            throw new RuntimeException('private_media_root_invalid');
        }
        $suffix = [];
        while (!file_exists($path) && !is_link($path)) {
            array_unshift($suffix, basename($path));
            $path = dirname($path);
        }
        $resolved = realpath($path);
        if ($resolved === false || !is_dir($resolved)) throw new RuntimeException('private_media_root_invalid');
        return rtrim($resolved, '/').($suffix ? '/'.implode('/', $suffix) : '');
    }

    private function path(string $key, bool $create = false): string
    {
        $uuid = '[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}';
        if (!preg_match('#^private/media-review/[a-f0-9]{64}/'.$uuid.'/(?:(?:source|corrected)/'.$uuid.'\.(?:jpeg|png|webp)|(?:review|auto_proposal)/'.$uuid.'\.webp|improvement_input/'.$uuid.'\.png)$#', $key)) {
            throw new RuntimeException('invalid_private_media_storage_key');
        }
        $target = $this->root.'/'.$key;
        $cursor = '';
        foreach (explode('/', ltrim(dirname($target), '/')) as $component) {
            $cursor .= '/'.$component;
            if (is_link($cursor)) throw new RuntimeException('private_media_symlink_forbidden');
            if ($create && !is_dir($cursor)) {
                if (!mkdir($cursor, 0700) && !is_dir($cursor)) throw new RuntimeException('private_media_directory_failed');
            }
            if ($create && ($cursor === $this->root || str_starts_with($cursor, $this->root.'/'))) {
                if (!chmod($cursor, 0700)) throw new RuntimeException('private_media_permissions_failed');
            }
        }
        if (is_link($target)) throw new RuntimeException('private_media_symlink_forbidden');
        return $target;
    }

    public function storeImmutable(string $storageKey, string $sourcePath): void
    {
        $target = $this->path($storageKey, true);
        $temporary = dirname($target).'/.'.bin2hex(random_bytes(16)).'.tmp';
        try {
            $handle = fopen($temporary, 'x+b');
            if ($handle === false) throw new RuntimeException('private_media_temp_failed');
            fclose($handle);
            if (!chmod($temporary, 0600) || !copy($sourcePath, $temporary)) throw new RuntimeException('private_media_copy_failed');
            if (hash_file('sha256', $sourcePath) !== hash_file('sha256', $temporary)) throw new RuntimeException('private_media_integrity_failed');
            // Atomic no-clobber materialization on the same filesystem.
            if (!@link($temporary, $target)) throw new RuntimeException('private_media_immutable_store_failed');
        } finally {
            if (is_file($temporary) && !unlink($temporary)) error_log('private_media_temp_cleanup_failed');
        }
    }

    public function openReadStream(string $storageKey): array
    {
        $path = $this->path($storageKey);
        $stream = @fopen($path, 'rb');
        if ($stream === false) throw new RuntimeException('private_media_object_unreadable');
        return ['stream'=>$stream, 'bytes'=>(int)fstat($stream)['size']];
    }
    public function exists(string $storageKey): bool { return is_file($this->path($storageKey)); }
    public function delete(string $storageKey): void
    {
        $path = $this->path($storageKey);
        if (is_file($path) && !unlink($path)) throw new RuntimeException('private_media_delete_failed');
    }
}
