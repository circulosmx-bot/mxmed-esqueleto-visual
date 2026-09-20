<?php
declare(strict_types=1);

/**
 * Repository-only private binary storage primitives.
 *
 * This library deliberately has no database, HTTP, authorization or clinical
 * policy dependencies. Callers remain responsible for those authorities.
 */

final class ClinicalPrivateBinaryStorageException extends RuntimeException
{
    /** @var array<string,mixed> */
    private array $details;

    /** @param array<string,mixed> $details */
    public function __construct(string $stableCode, array $details = [])
    {
        parent::__construct($stableCode);
        $this->details = $details;
    }

    /** @return array<string,mixed> */
    public function details(): array
    {
        return $this->details;
    }
}

final class ClinicalPrivateBinaryStorage
{
    public const MAX_BYTES = 26214400;

    /** @var array<string,true> */
    private const ALLOWED_MIME_TYPES = [
        'application/pdf' => true,
        'image/jpeg' => true,
        'image/png' => true,
        'image/webp' => true,
    ];

    /** @var array<string,true> */
    private const FINAL_VARIANTS = [
        'ORIGINAL' => true,
        'DISPLAY' => true,
        'THUMBNAIL' => true,
    ];

    private string $root;

    /** @var null|callable():string */
    private $opaqueStagingIdentityFactory;

    /**
     * The private root is always supplied by the caller. The optional document
     * root exists for dependency-injected QA; production callers may omit it
     * only when the effective server document root is available.
     *
     * @param null|callable():string $opaqueStagingIdentityFactory QA-only collision injection seam
     */
    public function __construct(
        string $root,
        ?string $documentRoot = null,
        ?callable $opaqueStagingIdentityFactory = null
    ) {
        $root = self::normalizeAbsolutePath($root, 'PRIVATE_STORAGE_ROOT_MUST_BE_ABSOLUTE');
        $documentRoot = $documentRoot ?? (isset($_SERVER['DOCUMENT_ROOT']) ? (string) $_SERVER['DOCUMENT_ROOT'] : '');
        if ($documentRoot === '') {
            // Conservative CLI fallback: the repository is the effective local document root.
            $documentRoot = dirname(__DIR__, 2);
        }
        $documentRoot = self::normalizeAbsolutePath($documentRoot, 'DOCUMENT_ROOT_MUST_BE_ABSOLUTE');
        $resolvedDocumentRoot = realpath($documentRoot);
        if ($resolvedDocumentRoot === false || !is_dir($resolvedDocumentRoot)) {
            throw new ClinicalPrivateBinaryStorageException('DOCUMENT_ROOT_UNRESOLVABLE');
        }
        $documentRoot = self::normalizeSeparators($resolvedDocumentRoot);

        self::assertNotEqualOrInside($root, $documentRoot);
        self::assertNotEqualOrInside(self::resolveProspectivePath($root), $documentRoot);
        $this->assertRootIsNotSymlink($root);
        $this->makeDirectory($root);

        $resolvedRoot = realpath($root);
        if ($resolvedRoot === false) {
            throw new ClinicalPrivateBinaryStorageException('PRIVATE_STORAGE_ROOT_UNRESOLVABLE');
        }
        $resolvedRoot = self::normalizeSeparators($resolvedRoot);
        self::assertNotEqualOrInside($resolvedRoot, $documentRoot);

        $this->root = rtrim($resolvedRoot, '/');
        $this->opaqueStagingIdentityFactory = $opaqueStagingIdentityFactory;

        foreach (['staging', 'clinical', 'quarantine'] as $namespace) {
            $this->ensureDirectoryForKey($namespace);
        }
    }

    public static function uuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }

    public static function sanitizeSourceFilename(?string $sourceFilename): ?string
    {
        if ($sourceFilename === null) {
            return null;
        }

        $value = str_replace('\\', '/', $sourceFilename);
        $value = basename($value);
        $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? '';
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, 255, 'UTF-8');
        }

        return substr($value, 0, 255);
    }

    /**
     * Stage exact source bytes under an opaque server-generated key.
     *
     * @return array{staging_key:string,sha256:string,byte_length:int,mime_type:string,source_filename?:string}
     */
    public function stageFile(string $sourcePath, ?string $sourceFilename = null): array
    {
        if (str_contains($sourcePath, "\0")) {
            throw new ClinicalPrivateBinaryStorageException('STAGING_SOURCE_NOT_REGULAR_FILE');
        }
        $sourceLstat = @lstat($sourcePath);
        if (
            is_link($sourcePath)
            || !is_array($sourceLstat)
            || (((int) $sourceLstat['mode']) & 0170000) !== 0100000
        ) {
            throw new ClinicalPrivateBinaryStorageException('STAGING_SOURCE_NOT_REGULAR_FILE');
        }

        $source = fopen($sourcePath, 'rb');
        if ($source === false) {
            throw new ClinicalPrivateBinaryStorageException('STAGING_SOURCE_OPEN_FAILED');
        }
        $sourceStat = fstat($source);
        if (
            !is_array($sourceStat)
            || (((int) $sourceStat['mode']) & 0170000) !== 0100000
            || (int) $sourceStat['dev'] !== (int) $sourceLstat['dev']
            || (int) $sourceStat['ino'] !== (int) $sourceLstat['ino']
        ) {
            fclose($source);
            throw new ClinicalPrivateBinaryStorageException('STAGING_SOURCE_NOT_REGULAR_FILE');
        }
        if ((int) $sourceStat['size'] > self::MAX_BYTES) {
            fclose($source);
            throw new ClinicalPrivateBinaryStorageException('STAGING_MAX_BYTES_EXCEEDED');
        }
        if (!flock($source, LOCK_SH)) {
            fclose($source);
            throw new ClinicalPrivateBinaryStorageException('STAGING_SOURCE_LOCK_FAILED');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $destination = false;
        $stagingKey = '';
        $destinationPath = '';
        try {
            for ($attempt = 0; $attempt < 8; $attempt++) {
                $stagingKey = 'staging/' . $this->newOpaqueStagingIdentity();
                self::validateStorageKey($stagingKey);
                $destinationPath = $this->pathForKey($stagingKey, true);
                $destination = @fopen($destinationPath, 'x+b');
                if (is_resource($destination)) {
                    break;
                }
            }

            if (!is_resource($destination)) {
                throw new ClinicalPrivateBinaryStorageException('STAGING_KEY_COLLISION');
            }

            @chmod($destinationPath, 0600);
            $hash = hash_init('sha256');
            $byteLength = 0;
            while (!feof($source)) {
                $chunk = fread($source, 1024 * 1024);
                if ($chunk === false) {
                    throw new ClinicalPrivateBinaryStorageException('STAGING_SOURCE_READ_FAILED');
                }
                if ($chunk === '') {
                    continue;
                }

                $byteLength += strlen($chunk);
                if ($byteLength > self::MAX_BYTES) {
                    throw new ClinicalPrivateBinaryStorageException('STAGING_MAX_BYTES_EXCEEDED');
                }
                hash_update($hash, $chunk);
                self::writeAll($destination, $chunk);
            }

            if (!fflush($destination)) {
                throw new ClinicalPrivateBinaryStorageException('STAGING_FLUSH_FAILED');
            }
            if (function_exists('fsync') && !fsync($destination)) {
                throw new ClinicalPrivateBinaryStorageException('STAGING_FSYNC_FAILED');
            }

            $sha256 = hash_final($hash);
            $destinationStat = fstat($destination);
            if (!is_array($destinationStat) || (int) $destinationStat['size'] !== $byteLength) {
                throw new ClinicalPrivateBinaryStorageException('STAGING_BINARY_INTEGRITY_MISMATCH');
            }
            $sourceFinalStat = fstat($source);
            if (!is_array($sourceFinalStat) || (int) $sourceFinalStat['size'] !== $byteLength) {
                throw new ClinicalPrivateBinaryStorageException('STAGING_SOURCE_CHANGED');
            }

            fclose($destination);
            $destination = false;
            flock($source, LOCK_UN);
            fclose($source);
            $source = false;

            $storedMimeType = $finfo->file($destinationPath);
            if (!is_string($storedMimeType) || !isset(self::ALLOWED_MIME_TYPES[$storedMimeType])) {
                throw new ClinicalPrivateBinaryStorageException('STAGING_MIME_NOT_ALLOWED');
            }
            $storedSha256 = hash_file('sha256', $destinationPath);
            if ($storedSha256 !== $sha256 || filesize($destinationPath) !== $byteLength) {
                throw new ClinicalPrivateBinaryStorageException('STAGING_BINARY_INTEGRITY_MISMATCH');
            }

            $result = [
                'staging_key' => $stagingKey,
                'sha256' => $sha256,
                'byte_length' => $byteLength,
                'mime_type' => $storedMimeType,
            ];
            $safeFilename = self::sanitizeSourceFilename($sourceFilename);
            if ($safeFilename !== null) {
                $result['source_filename'] = $safeFilename;
            }

            return $result;
        } catch (Throwable $exception) {
            if (is_resource($destination)) {
                fclose($destination);
            }
            if (is_resource($source)) {
                flock($source, LOCK_UN);
                fclose($source);
            }
            if ($destinationPath !== '' && is_file($destinationPath)) {
                @unlink($destinationPath);
            }
            throw $exception;
        }
    }

    /** @return array{key:string,exists:bool,byte_length?:int,sha256?:string,mtime?:int} */
    public function stat(string $storageKey): array
    {
        $path = $this->pathForKey($storageKey, false);
        if (!file_exists($path)) {
            return ['key' => $storageKey, 'exists' => false];
        }
        $this->assertRegularControlledFile($path);

        $size = filesize($path);
        $sha256 = hash_file('sha256', $path);
        $mtime = filemtime($path);
        if ($size === false || $sha256 === false) {
            throw new ClinicalPrivateBinaryStorageException('STORAGE_STAT_FAILED');
        }

        $result = [
            'key' => $storageKey,
            'exists' => true,
            'byte_length' => (int) $size,
            'sha256' => $sha256,
        ];
        if ($mtime !== false) {
            $result['mtime'] = (int) $mtime;
        }

        return $result;
    }

    /** @return resource */
    public function openReadStream(string $storageKey)
    {
        $path = $this->pathForKey($storageKey, false);
        $this->assertRegularControlledFile($path);
        $stream = fopen($path, 'rb');
        if ($stream === false) {
            throw new ClinicalPrivateBinaryStorageException('STORAGE_READ_OPEN_FAILED');
        }

        return $stream;
    }

    public function buildFinalKey(
        string $documentUuid,
        string $binaryUuid,
        string $variantRole,
        ?DateTimeInterface $partitionDate = null
    ): string {
        $documentUuid = self::normalizeUuid($documentUuid);
        $binaryUuid = self::normalizeUuid($binaryUuid);
        $variantRole = strtoupper($variantRole);
        if (!isset(self::FINAL_VARIANTS[$variantRole])) {
            throw new ClinicalPrivateBinaryStorageException('FINAL_VARIANT_ROLE_INVALID');
        }
        $partitionDate = $partitionDate ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));

        return sprintf(
            'clinical/%s/%s/%s/%s-%s',
            $partitionDate->format('Y'),
            $partitionDate->format('m'),
            $documentUuid,
            $binaryUuid,
            strtolower($variantRole)
        );
    }

    /**
     * Create and verify the immutable final object while retaining staging.
     * The future coordinating service is solely responsible for calling
     * deleteUncommitted() after its canonical database commit succeeds.
     *
     * @return array{final_key:string,sha256:string,byte_length:int,staging_retained:true}
     */
    public function finalizeCreateOnly(
        string $stagingKey,
        string $finalKey,
        string $expectedSha256,
        int $expectedBytes
    ): array {
        self::assertNamespace($stagingKey, 'staging');
        self::assertNamespace($finalKey, 'clinical');
        self::assertExpectedIntegrity($expectedSha256, $expectedBytes);

        $stagingPath = $this->pathForKey($stagingKey, false);
        $this->assertRegularControlledFile($stagingPath);
        $finalPath = $this->pathForKey($finalKey, true);

        if (file_exists($finalPath)) {
            throw new ClinicalPrivateBinaryStorageException(
                'FINAL_KEY_ALREADY_EXISTS',
                ['stored' => $this->stat($finalKey)]
            );
        }

        $source = fopen($stagingPath, 'rb');
        if ($source === false || !flock($source, LOCK_EX)) {
            if (is_resource($source)) {
                fclose($source);
            }
            throw new ClinicalPrivateBinaryStorageException('FINALIZATION_SOURCE_LOCK_FAILED');
        }

        try {
            $sourceIntegrity = self::streamIntegrity($source);
            if (
                !hash_equals($expectedSha256, $sourceIntegrity['sha256'])
                || $expectedBytes !== $sourceIntegrity['byte_length']
            ) {
                throw new ClinicalPrivateBinaryStorageException('FINAL_BINARY_INTEGRITY_MISMATCH');
            }

            if (!@link($stagingPath, $finalPath)) {
                if (file_exists($finalPath)) {
                    throw new ClinicalPrivateBinaryStorageException(
                        'FINAL_KEY_ALREADY_EXISTS',
                        ['stored' => $this->stat($finalKey)]
                    );
                }
                throw new ClinicalPrivateBinaryStorageException('FINALIZATION_CREATE_FAILED');
            }
            @chmod($finalPath, 0600);

            $finalIntegrity = $this->stat($finalKey);
            if (
                ($finalIntegrity['exists'] ?? false) !== true
                || ($finalIntegrity['byte_length'] ?? -1) !== $expectedBytes
                || !hash_equals($expectedSha256, (string) ($finalIntegrity['sha256'] ?? ''))
            ) {
                @unlink($finalPath);
                throw new ClinicalPrivateBinaryStorageException('FINAL_BINARY_INTEGRITY_MISMATCH');
            }

            return [
                'final_key' => $finalKey,
                'sha256' => $expectedSha256,
                'byte_length' => $expectedBytes,
                'staging_retained' => true,
            ];
        } finally {
            flock($source, LOCK_UN);
            fclose($source);
        }
    }

    public function deleteUncommitted(string $stagingKey): bool
    {
        self::assertNamespace($stagingKey, 'staging');
        $path = $this->pathForKey($stagingKey, false);
        if (!file_exists($path)) {
            return false;
        }
        $this->assertRegularControlledFile($path);
        if (!unlink($path)) {
            throw new ClinicalPrivateBinaryStorageException('STAGING_DELETE_FAILED');
        }

        return true;
    }

    /** @return array{source_key:string,quarantine_key:string,sha256:string,byte_length:int,cleanup_pending:bool} */
    public function quarantine(string $finalKey): array
    {
        self::assertNamespace($finalKey, 'clinical');
        $sourcePath = $this->pathForKey($finalKey, false);
        $this->assertRegularControlledFile($sourcePath);
        $source = fopen($sourcePath, 'rb');
        if ($source === false || !flock($source, LOCK_EX)) {
            if (is_resource($source)) {
                fclose($source);
            }
            throw new ClinicalPrivateBinaryStorageException('QUARANTINE_SOURCE_LOCK_FAILED');
        }

        try {
            $sourceIntegrity = self::streamIntegrity($source);
            $quarantineKey = '';
            $quarantinePath = '';
            for ($attempt = 0; $attempt < 8; $attempt++) {
                $quarantineKey = 'quarantine/' . self::uuidV4() . '/' . bin2hex(random_bytes(16));
                $quarantinePath = $this->pathForKey($quarantineKey, true);
                if (@link($sourcePath, $quarantinePath)) {
                    break;
                }
                $quarantineKey = '';
            }
            if ($quarantineKey === '') {
                throw new ClinicalPrivateBinaryStorageException('QUARANTINE_CREATE_FAILED');
            }
            @chmod($quarantinePath, 0600);

            $quarantineIntegrity = $this->stat($quarantineKey);
            if (
                ($quarantineIntegrity['byte_length'] ?? -1) !== $sourceIntegrity['byte_length']
                || !hash_equals($sourceIntegrity['sha256'], (string) ($quarantineIntegrity['sha256'] ?? ''))
            ) {
                @unlink($quarantinePath);
                throw new ClinicalPrivateBinaryStorageException('QUARANTINE_BINARY_INTEGRITY_MISMATCH');
            }

            $cleanupPending = !@unlink($sourcePath);

            return [
                'source_key' => $finalKey,
                'quarantine_key' => $quarantineKey,
                'sha256' => $sourceIntegrity['sha256'],
                'byte_length' => $sourceIntegrity['byte_length'],
                'cleanup_pending' => $cleanupPending,
            ];
        } finally {
            flock($source, LOCK_UN);
            fclose($source);
        }
    }

    /**
     * Enumerate adapter-controlled objects only. No content or source name is returned.
     *
     * @return list<array{key:string,byte_length:int,mtime:int,sha256?:string}>
     */
    public function inventory(bool $recomputeSha256 = false): array
    {
        $result = [];
        foreach (['staging', 'clinical', 'quarantine'] as $namespace) {
            $namespacePath = $this->root . '/' . $namespace;
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($namespacePath, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
            foreach ($iterator as $entry) {
                if ($entry->isLink() || !$entry->isFile()) {
                    continue;
                }
                $path = self::normalizeSeparators($entry->getPathname());
                $key = substr($path, strlen($this->root) + 1);
                self::validateStorageKey($key);
                $item = [
                    'key' => $key,
                    'byte_length' => (int) $entry->getSize(),
                    'mtime' => (int) $entry->getMTime(),
                ];
                if ($recomputeSha256) {
                    $sha256 = hash_file('sha256', $path);
                    if ($sha256 === false) {
                        throw new ClinicalPrivateBinaryStorageException('INVENTORY_HASH_FAILED');
                    }
                    $item['sha256'] = $sha256;
                }
                $result[] = $item;
            }
        }
        usort($result, static fn(array $left, array $right): int => strcmp($left['key'], $right['key']));

        return $result;
    }

    private function newOpaqueStagingIdentity(): string
    {
        if ($this->opaqueStagingIdentityFactory !== null) {
            $identity = ($this->opaqueStagingIdentityFactory)();
            if (!is_string($identity) || $identity === '') {
                throw new ClinicalPrivateBinaryStorageException('STAGING_IDENTITY_INVALID');
            }
            return $identity;
        }

        return self::uuidV4() . '/' . bin2hex(random_bytes(16));
    }

    private static function normalizeUuid(string $uuid): string
    {
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid)) {
            throw new ClinicalPrivateBinaryStorageException('OPAQUE_UUID_INVALID');
        }

        return strtolower($uuid);
    }

    private static function assertExpectedIntegrity(string $sha256, int $byteLength): void
    {
        if (!preg_match('/^[0-9a-f]{64}$/', $sha256) || $byteLength < 0 || $byteLength > self::MAX_BYTES) {
            throw new ClinicalPrivateBinaryStorageException('EXPECTED_BINARY_INTEGRITY_INVALID');
        }
    }

    /** @param resource $stream @return array{sha256:string,byte_length:int} */
    private static function streamIntegrity($stream): array
    {
        if (fseek($stream, 0) !== 0) {
            throw new ClinicalPrivateBinaryStorageException('BINARY_INTEGRITY_SEEK_FAILED');
        }
        $hash = hash_init('sha256');
        $byteLength = 0;
        while (!feof($stream)) {
            $chunk = fread($stream, 1024 * 1024);
            if ($chunk === false) {
                throw new ClinicalPrivateBinaryStorageException('BINARY_INTEGRITY_READ_FAILED');
            }
            if ($chunk === '') {
                continue;
            }
            $byteLength += strlen($chunk);
            hash_update($hash, $chunk);
        }

        return ['sha256' => hash_final($hash), 'byte_length' => $byteLength];
    }

    /** @param resource $destination */
    private static function writeAll($destination, string $bytes): void
    {
        $offset = 0;
        $length = strlen($bytes);
        while ($offset < $length) {
            $written = fwrite($destination, substr($bytes, $offset));
            if ($written === false || $written === 0) {
                throw new ClinicalPrivateBinaryStorageException('STAGING_WRITE_FAILED');
            }
            $offset += $written;
        }
    }

    private static function assertNamespace(string $storageKey, string $namespace): void
    {
        self::validateStorageKey($storageKey);
        if (!str_starts_with($storageKey, $namespace . '/')) {
            throw new ClinicalPrivateBinaryStorageException('STORAGE_NAMESPACE_NOT_ALLOWED');
        }
    }

    private static function validateStorageKey(string $storageKey): void
    {
        if (
            $storageKey === ''
            || str_contains($storageKey, "\0")
            || str_contains($storageKey, '\\')
            || str_starts_with($storageKey, '/')
            || preg_match('/^[A-Za-z]:/', $storageKey)
        ) {
            throw new ClinicalPrivateBinaryStorageException('STORAGE_KEY_INVALID');
        }
        $segments = explode('/', $storageKey);
        if (!in_array($segments[0] ?? '', ['staging', 'clinical', 'quarantine'], true)) {
            throw new ClinicalPrivateBinaryStorageException('STORAGE_KEY_INVALID');
        }
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..' || !preg_match('/^[A-Za-z0-9._-]+$/', $segment)) {
                throw new ClinicalPrivateBinaryStorageException('STORAGE_KEY_INVALID');
            }
        }
    }

    private function pathForKey(string $storageKey, bool $createParent): string
    {
        self::validateStorageKey($storageKey);
        $segments = explode('/', $storageKey);
        $filename = array_pop($segments);
        $parentKey = implode('/', $segments);
        if ($createParent && $parentKey !== '') {
            $this->ensureDirectoryForKey($parentKey);
        } elseif ($parentKey !== '') {
            $this->assertDirectoryChain($parentKey);
        }

        return $this->root . '/' . ($parentKey === '' ? '' : $parentKey . '/') . $filename;
    }

    private function ensureDirectoryForKey(string $directoryKey): void
    {
        self::validateStorageKey($directoryKey);
        $current = $this->root;
        foreach (explode('/', $directoryKey) as $segment) {
            $current .= '/' . $segment;
            if (is_link($current)) {
                throw new ClinicalPrivateBinaryStorageException('PRIVATE_STORAGE_SYMLINK_REFUSED');
            }
            if (!is_dir($current)) {
                $this->makeDirectory($current);
            }
            @chmod($current, 0700);
        }
    }

    private function assertDirectoryChain(string $directoryKey): void
    {
        $current = $this->root;
        foreach (explode('/', $directoryKey) as $segment) {
            $current .= '/' . $segment;
            if (is_link($current) || !is_dir($current)) {
                throw new ClinicalPrivateBinaryStorageException('PRIVATE_STORAGE_DIRECTORY_INVALID');
            }
        }
    }

    private function assertRegularControlledFile(string $path): void
    {
        if (is_link($path) || !is_file($path)) {
            throw new ClinicalPrivateBinaryStorageException('STORAGE_OBJECT_NOT_REGULAR_FILE');
        }
        $resolved = realpath($path);
        if ($resolved === false || !self::isEqualOrInside(self::normalizeSeparators($resolved), $this->root)) {
            throw new ClinicalPrivateBinaryStorageException('STORAGE_OBJECT_OUTSIDE_PRIVATE_ROOT');
        }
    }

    private function assertRootIsNotSymlink(string $root): void
    {
        if (is_link($root)) {
            throw new ClinicalPrivateBinaryStorageException('PRIVATE_STORAGE_SYMLINK_REFUSED');
        }
    }

    private function makeDirectory(string $path): void
    {
        if (!is_dir($path) && !@mkdir($path, 0700, true) && !is_dir($path)) {
            throw new ClinicalPrivateBinaryStorageException('PRIVATE_STORAGE_DIRECTORY_CREATE_FAILED');
        }
        @chmod($path, 0700);
    }

    private static function normalizeAbsolutePath(string $path, string $errorCode): string
    {
        if ($path === '' || str_contains($path, "\0")) {
            throw new ClinicalPrivateBinaryStorageException($errorCode);
        }
        $path = self::normalizeSeparators($path);
        if (!str_starts_with($path, '/') && !preg_match('/^[A-Za-z]:\//', $path)) {
            throw new ClinicalPrivateBinaryStorageException($errorCode);
        }
        $segments = explode('/', $path);
        foreach ($segments as $segment) {
            if ($segment === '..') {
                throw new ClinicalPrivateBinaryStorageException($errorCode);
            }
        }

        return rtrim($path, '/');
    }

    private static function normalizeSeparators(string $path): string
    {
        return str_replace('\\', '/', $path);
    }

    private static function resolveProspectivePath(string $path): string
    {
        $missing = [];
        $cursor = $path;
        while (!file_exists($cursor) && !is_link($cursor)) {
            $basename = basename($cursor);
            if ($basename === '' || $basename === '.' || $basename === DIRECTORY_SEPARATOR) {
                throw new ClinicalPrivateBinaryStorageException('PRIVATE_STORAGE_ROOT_UNRESOLVABLE');
            }
            array_unshift($missing, $basename);
            $parent = dirname($cursor);
            if ($parent === $cursor) {
                throw new ClinicalPrivateBinaryStorageException('PRIVATE_STORAGE_ROOT_UNRESOLVABLE');
            }
            $cursor = $parent;
        }

        $resolved = realpath($cursor);
        if ($resolved === false) {
            throw new ClinicalPrivateBinaryStorageException('PRIVATE_STORAGE_ROOT_UNRESOLVABLE');
        }
        $resolved = rtrim(self::normalizeSeparators($resolved), '/');

        return $resolved . ($missing === [] ? '' : '/' . implode('/', $missing));
    }

    private static function assertNotEqualOrInside(string $root, string $documentRoot): void
    {
        $resolvedDocumentRoot = realpath($documentRoot);
        if ($resolvedDocumentRoot !== false) {
            $documentRoot = self::normalizeSeparators($resolvedDocumentRoot);
        }
        if (self::isEqualOrInside(rtrim($root, '/'), rtrim($documentRoot, '/'))) {
            throw new ClinicalPrivateBinaryStorageException('CLINICAL_PRIVATE_STORAGE_ROOT_PUBLIC');
        }
    }

    private static function isEqualOrInside(string $candidate, string $parent): bool
    {
        return $candidate === $parent || str_starts_with($candidate, $parent . '/');
    }
}

final class ClinicalBinaryReconciliation
{
    /**
     * Pure reconciliation. This method performs no filesystem or database I/O.
     *
     * Inventory records: key, sha256, byte_length.
     * Coordination records: storage_state, staging_key/final_key, expires_at,
     * lease_until, document_id or committed_resource.
     * Manifest records: storage_key, sha256, byte_length, document_id.
     *
     * @param list<array<string,mixed>> $inventory
     * @param list<array<string,mixed>> $coordination
     * @param list<array<string,mixed>> $manifests
     * @return list<array<string,mixed>>
     */
    public static function classify(
        array $inventory,
        array $coordination,
        array $manifests,
        DateTimeImmutable $now
    ): array {
        $findings = [];
        $inventoryByKey = [];
        $inventoryCounts = [];
        foreach ($inventory as $item) {
            $key = (string) ($item['key'] ?? '');
            if ($key === '') {
                continue;
            }
            $inventoryCounts[$key] = ($inventoryCounts[$key] ?? 0) + 1;
            $inventoryByKey[$key] ??= $item;
        }

        $manifestByKey = [];
        $manifestCounts = [];
        foreach ($manifests as $manifest) {
            $key = (string) ($manifest['storage_key'] ?? '');
            if ($key === '') {
                continue;
            }
            $manifestCounts[$key] = ($manifestCounts[$key] ?? 0) + 1;
            $manifestByKey[$key] ??= $manifest;
        }

        $duplicateKeys = array_unique(array_merge(
            array_keys(array_filter($inventoryCounts, static fn(int $count): bool => $count > 1)),
            array_keys(array_filter($manifestCounts, static fn(int $count): bool => $count > 1))
        ));
        foreach ($duplicateKeys as $key) {
            if (str_starts_with($key, 'clinical/')) {
                $findings[] = self::finding('UNEXPECTED_DUPLICATE_FINAL_KEY', $key);
            }
        }

        $coordinatedStaging = [];
        foreach ($coordination as $record) {
            $state = strtoupper((string) ($record['storage_state'] ?? ''));
            $stagingKey = (string) ($record['staging_key'] ?? '');
            $finalKey = (string) ($record['final_key'] ?? '');
            $committedResource = (bool) ($record['committed_resource'] ?? false)
                || !empty($record['document_id'])
                || ($finalKey !== '' && isset($manifestByKey[$finalKey]));

            if ($stagingKey !== '') {
                $coordinatedStaging[$stagingKey] = true;
            }
            if (str_starts_with($stagingKey, 'staging/') && isset($inventoryByKey[$stagingKey]) && $committedResource) {
                $findings[] = self::finding('STAGING_RETAINED_AFTER_FINALIZATION', $stagingKey);
            }

            if ($state === 'STAGED' && $stagingKey !== '' && isset($inventoryByKey[$stagingKey])) {
                $expiresAt = self::dateValue($record['expires_at'] ?? null);
                $leaseUntil = self::dateValue($record['lease_until'] ?? null);
                $expired = $expiresAt !== null && $expiresAt <= $now;
                $activeLease = $leaseUntil !== null && $leaseUntil > $now;
                if ($expired && !$activeLease && !$committedResource) {
                    $findings[] = self::finding('STALE_STAGED', $stagingKey);
                }
            }

            if ($state === 'FINALIZED' && (!$committedResource || $finalKey === '' || !isset($manifestByKey[$finalKey]))) {
                $findings[] = self::finding(
                    'FINALIZED_COORDINATION_WITHOUT_COMMITTED_RESOURCE',
                    $finalKey !== '' ? $finalKey : $stagingKey
                );
            }
        }

        foreach ($inventoryByKey as $key => $item) {
            if (str_starts_with($key, 'staging/') && !isset($coordinatedStaging[$key])) {
                $findings[] = self::finding('STAGING_WITHOUT_COORDINATION', $key);
            }
            if (str_starts_with($key, 'clinical/') && !isset($manifestByKey[$key])) {
                $findings[] = self::finding('FINAL_WITHOUT_COMMITTED_MANIFEST', $key);
            }
        }

        foreach ($manifestByKey as $key => $manifest) {
            if (!isset($inventoryByKey[$key])) {
                $findings[] = self::finding('COMMITTED_MANIFEST_MISSING_BINARY', $key);
                continue;
            }
            $item = $inventoryByKey[$key];
            $storedSha256 = (string) ($item['sha256'] ?? '');
            $expectedSha256 = (string) ($manifest['sha256'] ?? '');
            if ($storedSha256 === '' || $expectedSha256 === '' || !hash_equals($expectedSha256, $storedSha256)) {
                $findings[] = self::finding('HASH_MISMATCH', $key);
                continue;
            }
            if ((int) ($item['byte_length'] ?? -1) !== (int) ($manifest['byte_length'] ?? -2)) {
                $findings[] = self::finding('BYTE_LENGTH_MISMATCH', $key);
                continue;
            }
            $findings[] = self::finding('HEALTHY_FINALIZED', $key);
        }

        usort($findings, static function (array $left, array $right): int {
            return [$left['classification'], $left['key']] <=> [$right['classification'], $right['key']];
        });

        return $findings;
    }

    /** @return array{classification:string,key:string} */
    private static function finding(string $classification, string $key): array
    {
        return ['classification' => $classification, 'key' => $key];
    }

    private static function dateValue(mixed $value): ?DateTimeImmutable
    {
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }
        if ($value instanceof DateTimeInterface) {
            return new DateTimeImmutable($value->format(DateTimeInterface::ATOM));
        }
        if (!is_string($value) || trim($value) === '') {
            return null;
        }
        try {
            return new DateTimeImmutable($value);
        } catch (Throwable) {
            return null;
        }
    }
}
