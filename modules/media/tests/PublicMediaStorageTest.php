<?php
declare(strict_types=1);

use Media\Storage\LocalPersistentPublicMediaStorage;

require_once __DIR__ . '/../storage/LocalPersistentPublicMediaStorage.php';

function mediaStorageAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$root = sys_get_temp_dir() . '/mxmed-media-storage-' . bin2hex(random_bytes(8));
$source = tempnam(sys_get_temp_dir(), 'mxmed-media-source-');
file_put_contents($source, 'persistent-media-test');
$key = 'public/physician-personal-logo/' . str_repeat('a', 64) . '/c9a0c96c-2871-4fe8-bb54-1aeb45f0423f.webp';

try {
    $firstProcessAdapter = new LocalPersistentPublicMediaStorage($root);
    $firstProcessAdapter->storeImmutable($key, $source);
    mediaStorageAssert($firstProcessAdapter->exists($key), 'stored media exists');

    $restartedAdapter = new LocalPersistentPublicMediaStorage($root);
    $stored = $restartedAdapter->openReadStream($key);
    mediaStorageAssert(stream_get_contents($stored['stream']) === 'persistent-media-test', 'media survives adapter/application restart');
    fclose($stored['stream']);

    foreach (['../../etc/passwd', 'public/physician-personal-logo/' . str_repeat('a', 64) . '/../../secret.webp', '/etc/passwd'] as $unsafe) {
        try {
            $restartedAdapter->openReadStream($unsafe);
            throw new RuntimeException('unsafe storage key accepted');
        } catch (RuntimeException $e) {
            mediaStorageAssert($e->getMessage() === 'invalid_public_media_storage_key', 'path traversal rejected');
        }
    }

    $restartedAdapter->delete($key);
    mediaStorageAssert(!$restartedAdapter->exists($key), 'owned media deletion works');
} finally {
    if (is_file($source)) unlink($source);
    if (is_dir($root)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($root);
    }
}

echo "PublicMediaStorageTest PASS\n";
