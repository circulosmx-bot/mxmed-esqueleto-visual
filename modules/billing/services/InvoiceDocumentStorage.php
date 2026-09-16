<?php
declare(strict_types=1);
namespace Billing\Services;

use RuntimeException;

/** Billing-only immutable objects; its key space never broadens media-review keys. */
final class InvoiceDocumentStorage
{
    private string $root;

    public function __construct(string $root)
    {
        $this->root = self::canonicalPath($root);
        foreach ([dirname(__DIR__, 3), $_SERVER['DOCUMENT_ROOT'] ?? ''] as $forbidden) {
            if ($forbidden === '') continue;
            $path = self::canonicalPath($forbidden);
            if ($path === $this->root || str_starts_with($this->root, $path.'/') || str_starts_with($path, $this->root.'/')) {
                throw new RuntimeException('invoice_private_root_overlaps_public_root');
            }
        }
    }

    public static function runtime(): self
    {
        $root = trim((string)(getenv('MXMED_PRIVATE_BILLING_ROOT') ?: ''));
        if ($root === '') {
            $home = trim((string)(getenv('HOME') ?: ''));
            if ($home === '') throw new RuntimeException('private_billing_root_required');
            $root = $home.'/.local/share/mxmed/private-billing';
        }
        return new self($root);
    }

    public static function key(string $doctorId, string $invoiceId, string $kind): string
    {
        if (!in_array($kind, ['xml','pdf'], true) || !preg_match('/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/D', $invoiceId)) {
            throw new RuntimeException('invalid_invoice_storage_key');
        }
        return 'private/billing-invoices/'.hash('sha256', 'PHYSICIAN:'.$doctorId).'/'.$invoiceId.'/'.($kind === 'xml' ? 'source.xml' : 'associated.pdf');
    }

    public function storeImmutable(string $key, string $sourcePath, string $expectedSha256): void
    {
        $target = $this->path($key, true);
        $temporary = dirname($target).'/.'.bin2hex(random_bytes(16)).'.tmp';
        try {
            $handle = fopen($temporary, 'x+b');
            if ($handle === false) throw new RuntimeException('invoice_private_temp_failed');
            fclose($handle);
            if (!preg_match('/^[a-f0-9]{64}$/D', $expectedSha256) || !chmod($temporary, 0600)
                || !copy($sourcePath, $temporary)
                || !hash_equals($expectedSha256, (string)hash_file('sha256', $temporary))) {
                throw new RuntimeException('invoice_private_copy_failed');
            }
            if (!@link($temporary, $target)) throw new RuntimeException('invoice_private_immutable_store_failed');
        } finally {
            if (is_file($temporary)) unlink($temporary);
        }
    }

    public function readVerified(string $key, string $sha256, int $expectedBytes, int $limit): string
    {
        $path = $this->path($key);
        if (!is_file($path) || filesize($path) !== $expectedBytes || $expectedBytes < 1 || $expectedBytes > $limit) {
            throw new RuntimeException('invoice_private_integrity_failed');
        }
        $bytes = file_get_contents($path);
        if (!is_string($bytes) || strlen($bytes) !== $expectedBytes || !hash_equals($sha256, hash('sha256', $bytes))) {
            throw new RuntimeException('invoice_private_integrity_failed');
        }
        return $bytes;
    }

    public function delete(string $key): void
    {
        $path = $this->path($key);
        if (is_file($path) && !unlink($path)) throw new RuntimeException('invoice_private_delete_failed');
    }

    private function path(string $key, bool $create = false): string
    {
        if (!preg_match('#^private/billing-invoices/[a-f0-9]{64}/[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}/(?:source\.xml|associated\.pdf)$#D', $key)) {
            throw new RuntimeException('invalid_invoice_storage_key');
        }
        $target = $this->root.'/'.$key;
        $cursor = '';
        foreach (explode('/', ltrim(dirname($target), '/')) as $part) {
            $cursor .= '/'.$part;
            if (is_link($cursor)) throw new RuntimeException('invoice_private_symlink_forbidden');
            if ($create && !is_dir($cursor) && !mkdir($cursor, 0700) && !is_dir($cursor)) {
                throw new RuntimeException('invoice_private_directory_failed');
            }
            if ($create && ($cursor === $this->root || str_starts_with($cursor, $this->root.'/')) && !chmod($cursor, 0700)) {
                throw new RuntimeException('invoice_private_permissions_failed');
            }
        }
        if (is_link($target)) throw new RuntimeException('invoice_private_symlink_forbidden');
        return $target;
    }

    private static function canonicalPath(string $path): string
    {
        $path = rtrim(trim($path), '/');
        if ($path === '' || !str_starts_with($path, '/') || preg_match('#/(?:\.\.?)(?:/|$)#', $path)) {
            throw new RuntimeException('invoice_private_root_invalid');
        }
        $suffix = [];
        while (!file_exists($path) && !is_link($path)) {
            array_unshift($suffix, basename($path));
            $path = dirname($path);
        }
        $resolved = realpath($path);
        if ($resolved === false || !is_dir($resolved)) throw new RuntimeException('invoice_private_root_invalid');
        return rtrim($resolved, '/').($suffix ? '/'.implode('/', $suffix) : '');
    }
}
