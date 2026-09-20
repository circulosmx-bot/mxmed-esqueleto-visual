<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/api/_lib/clinical_private_binary_storage.php';

$repositoryRoot = realpath(dirname(__DIR__, 3));
if ($repositoryRoot === false) {
    fwrite(STDERR, "MULTI03A_FILESYSTEM_QA=FAIL repository root\n");
    exit(1);
}

$prefix = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'mxmed_multi03a_';
$temporaryRoot = $prefix . bin2hex(random_bytes(8));
$fixtureRoot = $temporaryRoot . '/fixtures';
$privateRoot = $temporaryRoot . '/private';
$scenarioCount = 0;
$passed = [];

function multi03a_remove_tree(string $path): void
{
    if (is_link($path) || is_file($path)) {
        @unlink($path);
        return;
    }
    if (!is_dir($path)) {
        return;
    }
    $iterator = new FilesystemIterator($path, FilesystemIterator::SKIP_DOTS);
    foreach ($iterator as $entry) {
        multi03a_remove_tree($entry->getPathname());
    }
    @rmdir($path);
}

function multi03a_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function multi03a_expect_code(callable $operation, string $stableCode): void
{
    try {
        $operation();
    } catch (ClinicalPrivateBinaryStorageException $exception) {
        multi03a_assert($exception->getMessage() === $stableCode, 'Expected ' . $stableCode . ', got ' . $exception->getMessage());
        return;
    }
    throw new RuntimeException('Expected exception ' . $stableCode);
}

function multi03a_case(string $id, callable $test): void
{
    global $scenarioCount, $passed;
    $test();
    $scenarioCount++;
    $passed[] = $id;
}

function multi03a_has_classification(array $findings, string $classification, ?string $key = null): bool
{
    foreach ($findings as $finding) {
        if (($finding['classification'] ?? null) !== $classification) {
            continue;
        }
        if ($key === null || ($finding['key'] ?? null) === $key) {
            return true;
        }
    }
    return false;
}

try {
    if (!mkdir($fixtureRoot, 0700, true) && !is_dir($fixtureRoot)) {
        throw new RuntimeException('Could not create fixture root');
    }
    $pdfBytes = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";
    $pdfPath = $fixtureRoot . '/synthetic.pdf';
    file_put_contents($pdfPath, $pdfBytes);
    $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
    if (!is_string($pngBytes)) {
        throw new RuntimeException('PNG fixture decode failed');
    }
    $pngPath = $fixtureRoot . '/synthetic.png';
    file_put_contents($pngPath, $pngBytes);

    $storage = null;
    multi03a_case('FS01', function () use (&$storage, $privateRoot, $repositoryRoot): void {
        $storage = new ClinicalPrivateBinaryStorage($privateRoot, $repositoryRoot);
        multi03a_assert(is_dir($privateRoot . '/staging'), 'staging namespace missing');
        multi03a_assert(is_dir($privateRoot . '/clinical'), 'clinical namespace missing');
        multi03a_assert(is_dir($privateRoot . '/quarantine'), 'quarantine namespace missing');
    });

    multi03a_case('FS02', function () use ($repositoryRoot, $temporaryRoot): void {
        $publicCandidate = $repositoryRoot . '/.multi03a_public_refused_' . bin2hex(random_bytes(4));
        multi03a_expect_code(
            static fn() => new ClinicalPrivateBinaryStorage($publicCandidate, $repositoryRoot),
            'CLINICAL_PRIVATE_STORAGE_ROOT_PUBLIC'
        );
        multi03a_assert(!file_exists($publicCandidate), 'public root was created');

        $publicLink = $temporaryRoot . '/public-root-link';
        multi03a_assert(symlink($repositoryRoot, $publicLink), 'public-root symlink fixture failed');
        $linkedCandidate = $publicLink . '/.multi03a_linked_public_refused_' . bin2hex(random_bytes(4));
        multi03a_expect_code(
            static fn() => new ClinicalPrivateBinaryStorage($linkedCandidate, $repositoryRoot),
            'CLINICAL_PRIVATE_STORAGE_ROOT_PUBLIC'
        );
        multi03a_assert(!file_exists($linkedCandidate), 'symlinked public root was created');
    });

    multi03a_case('FS03', function () use ($repositoryRoot): void {
        multi03a_expect_code(
            static fn() => new ClinicalPrivateBinaryStorage('relative/private', $repositoryRoot),
            'PRIVATE_STORAGE_ROOT_MUST_BE_ABSOLUTE'
        );
    });

    $stagedPdf = [];
    multi03a_case('FS04', function () use (&$storage, &$stagedPdf, $pdfPath, $pdfBytes): void {
        $stagedPdf = $storage->stageFile($pdfPath, "../synthetic\0.pdf");
        multi03a_assert($stagedPdf['mime_type'] === 'application/pdf', 'PDF MIME mismatch');
        multi03a_assert($stagedPdf['sha256'] === hash('sha256', $pdfBytes), 'PDF SHA mismatch');
        multi03a_assert($stagedPdf['byte_length'] === strlen($pdfBytes), 'PDF length mismatch');
        multi03a_assert(($stagedPdf['source_filename'] ?? null) === 'synthetic.pdf', 'filename not sanitized');
        multi03a_assert(str_starts_with($stagedPdf['staging_key'], 'staging/'), 'staging key namespace mismatch');
    });

    multi03a_case('FS05', function () use (&$storage, $pngPath): void {
        $staged = $storage->stageFile($pngPath, 'synthetic.png');
        multi03a_assert($staged['mime_type'] === 'image/png', 'PNG MIME mismatch');
        $storage->deleteUncommitted($staged['staging_key']);

        if (function_exists('imagecreatetruecolor') && function_exists('imagejpeg')) {
            $path = dirname($pngPath) . '/synthetic.jpg';
            $image = imagecreatetruecolor(2, 2);
            imagejpeg($image, $path, 90);
            unset($image);
            $jpeg = $storage->stageFile($path, 'wrong-extension.bin');
            multi03a_assert($jpeg['mime_type'] === 'image/jpeg', 'JPEG content MIME mismatch');
            $storage->deleteUncommitted($jpeg['staging_key']);
        }
        if (function_exists('imagecreatetruecolor') && function_exists('imagewebp')) {
            $path = dirname($pngPath) . '/synthetic.webp';
            $image = imagecreatetruecolor(2, 2);
            imagewebp($image, $path, 90);
            unset($image);
            $webp = $storage->stageFile($path, 'synthetic.data');
            multi03a_assert($webp['mime_type'] === 'image/webp', 'WEBP content MIME mismatch');
            $storage->deleteUncommitted($webp['staging_key']);
        }
    });

    multi03a_case('FS06', function () use (&$storage, $fixtureRoot): void {
        $path = $fixtureRoot . '/unsupported.txt';
        file_put_contents($path, 'not a clinical binary fixture');
        multi03a_expect_code(static fn() => $storage->stageFile($path), 'STAGING_MIME_NOT_ALLOWED');
    });

    multi03a_case('FS07', function () use (&$storage, $fixtureRoot): void {
        $path = $fixtureRoot . '/oversized.pdf';
        $stream = fopen($path, 'wb');
        fwrite($stream, '%PDF-1.4');
        fseek($stream, ClinicalPrivateBinaryStorage::MAX_BYTES);
        fwrite($stream, 'X');
        fclose($stream);
        multi03a_expect_code(static fn() => $storage->stageFile($path), 'STAGING_MAX_BYTES_EXCEEDED');
    });

    multi03a_case('FS08', function () use (&$storage): void {
        multi03a_expect_code(static fn() => $storage->stat('staging/../escape'), 'STORAGE_KEY_INVALID');
        multi03a_expect_code(static fn() => $storage->stat('/absolute/key'), 'STORAGE_KEY_INVALID');
        multi03a_expect_code(static fn() => $storage->stat('staging\\escape'), 'STORAGE_KEY_INVALID');
        multi03a_expect_code(static fn() => $storage->stat('staging//empty'), 'STORAGE_KEY_INVALID');
    });

    multi03a_case('FS09', function () use ($temporaryRoot, $repositoryRoot, $pdfPath): void {
        $fixedIdentity = '11111111-1111-4111-8111-111111111111/fixed-token';
        $collisionStorage = new ClinicalPrivateBinaryStorage(
            $temporaryRoot . '/collision-private',
            $repositoryRoot,
            static fn(): string => $fixedIdentity
        );
        $first = $collisionStorage->stageFile($pdfPath);
        multi03a_expect_code(static fn() => $collisionStorage->stageFile($pdfPath), 'STAGING_KEY_COLLISION');
        $collisionStorage->deleteUncommitted($first['staging_key']);
    });

    $finalKey = '';
    multi03a_case('FS10', function () use (&$storage, &$stagedPdf, &$finalKey, $pdfBytes): void {
        $finalKey = $storage->buildFinalKey(
            'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
            'ORIGINAL',
            new DateTimeImmutable('2026-09-19T00:00:00Z')
        );
        $result = $storage->finalizeCreateOnly(
            $stagedPdf['staging_key'],
            $finalKey,
            $stagedPdf['sha256'],
            $stagedPdf['byte_length']
        );
        multi03a_assert(($result['staging_retained'] ?? false) === true, 'staging retention not explicit');
        multi03a_assert(!array_key_exists('cleanup_pending', $result), 'finalization reported unattempted cleanup');
        multi03a_assert($storage->stat($stagedPdf['staging_key'])['exists'] === true, 'staging object removed before commit');
        $final = $storage->stat($finalKey);
        multi03a_assert($final['exists'] === true, 'final object missing');
        multi03a_assert($final['sha256'] === hash('sha256', $pdfBytes), 'final bytes changed');
    });

    multi03a_case('FS11', function () use (&$storage, $pdfPath, &$finalKey): void {
        $second = $storage->stageFile($pdfPath);
        multi03a_expect_code(
            static fn() => $storage->finalizeCreateOnly($second['staging_key'], $finalKey, $second['sha256'], $second['byte_length']),
            'FINAL_KEY_ALREADY_EXISTS'
        );
        multi03a_assert($storage->stat($second['staging_key'])['exists'] === true, 'collision staging object lost');
        $storage->deleteUncommitted($second['staging_key']);
    });

    multi03a_case('FS12', function () use (&$storage, $pdfPath): void {
        $staged = $storage->stageFile($pdfPath);
        $mismatchKey = $storage->buildFinalKey(
            'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
            'dddddddd-dddd-4ddd-8ddd-dddddddddddd',
            'ORIGINAL',
            new DateTimeImmutable('2026-09-19T00:00:00Z')
        );
        multi03a_expect_code(
            static fn() => $storage->finalizeCreateOnly($staged['staging_key'], $mismatchKey, str_repeat('0', 64), $staged['byte_length']),
            'FINAL_BINARY_INTEGRITY_MISMATCH'
        );
        multi03a_assert($storage->stat($mismatchKey)['exists'] === false, 'mismatched final object exposed');
        multi03a_expect_code(
            static fn() => $storage->finalizeCreateOnly($staged['staging_key'], $mismatchKey, $staged['sha256'], $staged['byte_length'] + 1),
            'FINAL_BINARY_INTEGRITY_MISMATCH'
        );
        $storage->deleteUncommitted($staged['staging_key']);
    });

    multi03a_case('FS13', function () use (&$storage, &$stagedPdf, &$finalKey): void {
        $finalBeforeCleanup = $storage->stat($finalKey);
        multi03a_assert($storage->deleteUncommitted($stagedPdf['staging_key']) === true, 'staging delete failed');
        multi03a_assert($storage->stat($stagedPdf['staging_key'])['exists'] === false, 'deleted staging remains');
        multi03a_assert($storage->stat($finalKey) === $finalBeforeCleanup, 'post-commit staging cleanup changed final');
    });

    multi03a_case('FS14', function () use (&$storage, &$finalKey): void {
        multi03a_expect_code(static fn() => $storage->deleteUncommitted($finalKey), 'STORAGE_NAMESPACE_NOT_ALLOWED');
        multi03a_assert($storage->stat($finalKey)['exists'] === true, 'final object deleted by staging cleanup');
    });

    $quarantine = [];
    multi03a_case('FS15', function () use (&$storage, &$finalKey, &$quarantine, $pdfBytes): void {
        $quarantine = $storage->quarantine($finalKey);
        multi03a_assert(str_starts_with($quarantine['quarantine_key'], 'quarantine/'), 'quarantine namespace mismatch');
        multi03a_assert($quarantine['cleanup_pending'] === false, 'final cleanup pending unexpectedly');
        multi03a_assert($storage->stat($finalKey)['exists'] === false, 'orphan final remains after quarantine');
        multi03a_assert($storage->stat($quarantine['quarantine_key'])['sha256'] === hash('sha256', $pdfBytes), 'quarantine bytes changed');
    });

    multi03a_case('FS16', function () use (&$storage, &$quarantine, $pdfBytes): void {
        $stream = $storage->openReadStream($quarantine['quarantine_key']);
        $read = stream_get_contents($stream);
        fclose($stream);
        multi03a_assert($read === $pdfBytes, 'read stream bytes mismatch');
    });

    $now = new DateTimeImmutable('2026-09-19T12:00:00Z');
    multi03a_case('FS17', function () use ($now): void {
        $findings = ClinicalBinaryReconciliation::classify(
            [['key' => 'clinical/2026/09/doc/bin-original', 'sha256' => str_repeat('a', 64), 'byte_length' => 10]],
            [],
            [['storage_key' => 'clinical/2026/09/doc/bin-original', 'sha256' => str_repeat('a', 64), 'byte_length' => 10, 'document_id' => 1]],
            $now
        );
        multi03a_assert(multi03a_has_classification($findings, 'HEALTHY_FINALIZED'), 'healthy final not classified');
    });

    multi03a_case('FS18', function () use ($now): void {
        $key = 'clinical/2026/09/orphan/bin-original';
        $findings = ClinicalBinaryReconciliation::classify(
            [['key' => $key, 'sha256' => str_repeat('b', 64), 'byte_length' => 11]],
            [],
            [],
            $now
        );
        multi03a_assert(multi03a_has_classification($findings, 'FINAL_WITHOUT_COMMITTED_MANIFEST', $key), 'orphan final not classified');
    });

    multi03a_case('FS19', function () use ($now): void {
        $key = 'clinical/2026/09/missing/bin-original';
        $findings = ClinicalBinaryReconciliation::classify(
            [],
            [],
            [['storage_key' => $key, 'sha256' => str_repeat('c', 64), 'byte_length' => 12, 'document_id' => 2]],
            $now
        );
        multi03a_assert(multi03a_has_classification($findings, 'COMMITTED_MANIFEST_MISSING_BINARY', $key), 'missing binary not classified');
    });

    multi03a_case('FS20', function () use ($now): void {
        $hashKey = 'clinical/2026/09/hash/bin-original';
        $lengthKey = 'clinical/2026/09/length/bin-original';
        $findings = ClinicalBinaryReconciliation::classify(
            [
                ['key' => $hashKey, 'sha256' => str_repeat('d', 64), 'byte_length' => 13],
                ['key' => $lengthKey, 'sha256' => str_repeat('e', 64), 'byte_length' => 99],
            ],
            [],
            [
                ['storage_key' => $hashKey, 'sha256' => str_repeat('f', 64), 'byte_length' => 13, 'document_id' => 3],
                ['storage_key' => $lengthKey, 'sha256' => str_repeat('e', 64), 'byte_length' => 14, 'document_id' => 4],
            ],
            $now
        );
        multi03a_assert(multi03a_has_classification($findings, 'HASH_MISMATCH', $hashKey), 'hash mismatch not classified');
        multi03a_assert(multi03a_has_classification($findings, 'BYTE_LENGTH_MISMATCH', $lengthKey), 'length mismatch not classified');
    });

    multi03a_case('FS21', function () use ($now): void {
        $stale = 'staging/stale/item';
        $leased = 'staging/leased/item';
        $committed = 'staging/committed/item';
        $inventory = [
            ['key' => $stale, 'sha256' => str_repeat('1', 64), 'byte_length' => 1],
            ['key' => $leased, 'sha256' => str_repeat('2', 64), 'byte_length' => 1],
            ['key' => $committed, 'sha256' => str_repeat('3', 64), 'byte_length' => 1],
        ];
        $coordination = [
            ['storage_state' => 'STAGED', 'staging_key' => $stale, 'expires_at' => '2026-09-19T11:00:00Z', 'lease_until' => null],
            ['storage_state' => 'STAGED', 'staging_key' => $leased, 'expires_at' => '2026-09-19T11:00:00Z', 'lease_until' => '2026-09-19T13:00:00Z'],
            ['storage_state' => 'STAGED', 'staging_key' => $committed, 'expires_at' => '2026-09-19T11:00:00Z', 'lease_until' => null, 'committed_resource' => true],
        ];
        $findings = ClinicalBinaryReconciliation::classify($inventory, $coordination, [], $now);
        multi03a_assert(multi03a_has_classification($findings, 'STALE_STAGED', $stale), 'expired unleased staging not stale');
        multi03a_assert(!multi03a_has_classification($findings, 'STALE_STAGED', $leased), 'active lease classified stale');
        multi03a_assert(!multi03a_has_classification($findings, 'STALE_STAGED', $committed), 'committed staging classified stale');
    });

    multi03a_case('FS22', function () use (&$storage, &$quarantine, $privateRoot): void {
        $stat = $storage->stat($quarantine['quarantine_key']);
        $inventory = $storage->inventory(true);
        foreach ([$stat, $inventory] as $output) {
            $encoded = json_encode($output, JSON_THROW_ON_ERROR);
            multi03a_assert(!str_contains($encoded, $privateRoot), 'absolute private path emitted');
            multi03a_assert(!preg_match('/https?:\\/\\//i', $encoded), 'public URL emitted');
        }
    });

    multi03a_case('FS23', function () use (&$storage, $fixtureRoot, $pdfPath): void {
        $link = $fixtureRoot . '/source-link.pdf';
        if (!symlink($pdfPath, $link)) {
            throw new RuntimeException('Could not create symlink fixture');
        }
        multi03a_expect_code(static fn() => $storage->stageFile($link), 'STAGING_SOURCE_NOT_REGULAR_FILE');
    });

    multi03a_case('FS24', function () use ($privateRoot, &$storage, &$quarantine): void {
        if (DIRECTORY_SEPARATOR !== '/') {
            return;
        }
        $rootMode = fileperms($privateRoot) & 0777;
        multi03a_assert(($rootMode & 0077) === 0, 'private root is group/world accessible');
        $stagingMode = fileperms($privateRoot . '/staging') & 0777;
        multi03a_assert(($stagingMode & 0077) === 0, 'staging namespace is group/world accessible');
        $stream = $storage->openReadStream($quarantine['quarantine_key']);
        $metadata = stream_get_meta_data($stream);
        fclose($stream);
        $fileMode = fileperms((string) $metadata['uri']) & 0777;
        multi03a_assert(($fileMode & 0077) === 0, 'private file is group/world accessible');
    });

    $r1Staged = [];
    $r1FinalKey = '';
    multi03a_case('R1-01', function () use (&$storage, &$r1Staged, &$r1FinalKey, $pdfPath): void {
        $r1Staged = $storage->stageFile($pdfPath);
        $r1FinalKey = $storage->buildFinalKey(
            '11111111-2222-4333-8444-555555555555',
            '66666666-7777-4888-8999-aaaaaaaaaaaa',
            'ORIGINAL',
            new DateTimeImmutable('2026-09-19T00:00:00Z')
        );
        $result = $storage->finalizeCreateOnly(
            $r1Staged['staging_key'],
            $r1FinalKey,
            $r1Staged['sha256'],
            $r1Staged['byte_length']
        );
        multi03a_assert(($result['staging_retained'] ?? false) === true, 'R1 finalization did not report retained staging');
        multi03a_assert(!array_key_exists('cleanup_pending', $result), 'R1 finalization reported cleanup that was not attempted');
        multi03a_assert($storage->stat($r1Staged['staging_key'])['exists'] === true, 'R1 finalization removed staging');
    });

    multi03a_case('R1-02', function () use (&$storage, &$r1FinalKey, $pdfBytes): void {
        $final = $storage->stat($r1FinalKey);
        multi03a_assert($final['exists'] === true, 'R1 final object missing');
        multi03a_assert($final['sha256'] === hash('sha256', $pdfBytes), 'R1 final hash mismatch');
        multi03a_assert($final['byte_length'] === strlen($pdfBytes), 'R1 final length mismatch');
    });

    multi03a_case('R1-03', function () use (&$storage, &$r1Staged, &$r1FinalKey): void {
        $finalBeforeCleanup = $storage->stat($r1FinalKey);
        multi03a_assert($storage->deleteUncommitted($r1Staged['staging_key']) === true, 'R1 post-commit staging cleanup failed');
        multi03a_assert($storage->stat($r1Staged['staging_key'])['exists'] === false, 'R1 staging remains after explicit cleanup');
        multi03a_assert($storage->stat($r1FinalKey) === $finalBeforeCleanup, 'R1 explicit cleanup changed final');
    });

    multi03a_case('R1-04', function () use (&$storage, &$r1FinalKey, $pdfPath): void {
        $collisionStage = $storage->stageFile($pdfPath);
        multi03a_expect_code(
            static fn() => $storage->finalizeCreateOnly(
                $collisionStage['staging_key'],
                $r1FinalKey,
                $collisionStage['sha256'],
                $collisionStage['byte_length']
            ),
            'FINAL_KEY_ALREADY_EXISTS'
        );
        multi03a_assert($storage->stat($collisionStage['staging_key'])['exists'] === true, 'R1 collision removed staging');
        $storage->deleteUncommitted($collisionStage['staging_key']);
    });

    multi03a_case('R1-05', function () use (&$storage, $pdfPath): void {
        $integrityStage = $storage->stageFile($pdfPath);
        $integrityFinal = $storage->buildFinalKey(
            'bbbbbbbb-cccc-4ddd-8eee-ffffffffffff',
            '01234567-89ab-4cde-8fab-0123456789ab',
            'ORIGINAL',
            new DateTimeImmutable('2026-09-19T00:00:00Z')
        );
        multi03a_expect_code(
            static fn() => $storage->finalizeCreateOnly(
                $integrityStage['staging_key'],
                $integrityFinal,
                str_repeat('0', 64),
                $integrityStage['byte_length']
            ),
            'FINAL_BINARY_INTEGRITY_MISMATCH'
        );
        multi03a_assert($storage->stat($integrityStage['staging_key'])['exists'] === true, 'R1 integrity failure removed staging');
        multi03a_assert($storage->stat($integrityFinal)['exists'] === false, 'R1 defective final remains');

        $method = new ReflectionMethod(ClinicalPrivateBinaryStorage::class, 'finalizeCreateOnly');
        $sourceLines = file($method->getFileName());
        $methodSource = implode('', array_slice(
            $sourceLines,
            $method->getStartLine() - 1,
            $method->getEndLine() - $method->getStartLine() + 1
        ));
        multi03a_assert(str_contains($methodSource, '@unlink($finalPath)'), 'R1 final integrity compensation missing');
        multi03a_assert(!str_contains($methodSource, 'unlink($stagingPath)'), 'R1 final integrity path can remove staging');
        $storage->deleteUncommitted($integrityStage['staging_key']);
    });

    multi03a_case('R1-06', function () use (&$storage, $pdfPath, $pdfBytes): void {
        $quarantineStage = $storage->stageFile($pdfPath);
        $quarantineFinal = $storage->buildFinalKey(
            'fedcba98-7654-4321-8fed-cba987654321',
            'abcdef01-2345-4678-8abc-def012345678',
            'ORIGINAL',
            new DateTimeImmutable('2026-09-19T00:00:00Z')
        );
        $storage->finalizeCreateOnly(
            $quarantineStage['staging_key'],
            $quarantineFinal,
            $quarantineStage['sha256'],
            $quarantineStage['byte_length']
        );
        $quarantineResult = $storage->quarantine($quarantineFinal);
        multi03a_assert($storage->stat($quarantineFinal)['exists'] === false, 'R1 quarantine retained final path');
        multi03a_assert($storage->stat($quarantineStage['staging_key'])['exists'] === true, 'R1 quarantine destroyed staging');
        multi03a_assert($storage->stat($quarantineResult['quarantine_key'])['sha256'] === hash('sha256', $pdfBytes), 'R1 quarantine bytes changed');
        $storage->deleteUncommitted($quarantineStage['staging_key']);
        multi03a_assert($storage->stat($quarantineResult['quarantine_key'])['exists'] === true, 'R1 staging cleanup destroyed quarantine');
    });

    multi03a_case('R1-07', function () use (&$storage, &$r1FinalKey): void {
        multi03a_expect_code(static fn() => $storage->deleteUncommitted($r1FinalKey), 'STORAGE_NAMESPACE_NOT_ALLOWED');
        multi03a_assert($storage->stat($r1FinalKey)['exists'] === true, 'R1 staging cleanup deleted final namespace');
    });

    multi03a_case('R1-08', function (): void {
        $method = new ReflectionMethod(ClinicalPrivateBinaryStorage::class, 'finalizeCreateOnly');
        $sourceLines = file($method->getFileName());
        $methodSource = implode('', array_slice(
            $sourceLines,
            $method->getStartLine() - 1,
            $method->getEndLine() - $method->getStartLine() + 1
        ));
        multi03a_assert(!str_contains($methodSource, 'unlink($stagingPath)'), 'automatic staging unlink remains in finalization');
        multi03a_assert(str_contains($methodSource, "'staging_retained' => true"), 'explicit staging retention result missing');
    });

    multi03a_assert($scenarioCount === 32, 'Expected 32 scenarios');
    multi03a_remove_tree($temporaryRoot);
    $residualCount = count(glob($temporaryRoot, GLOB_ONLYDIR) ?: []);
    multi03a_assert($residualCount === 0, 'Temporary root remains');

    echo "MULTI03A_FILESYSTEM_QA=PASS\n";
    echo 'FILESYSTEM_QA_SCENARIOS=' . $scenarioCount . "\n";
    echo "MULTI03A_R1_QA_SCENARIOS=8\n";
    echo "FINALIZATION_PRESERVES_STAGING=true\n";
    echo "STAGING_CLEANUP_SEPARATE_FROM_FINALIZATION=true\n";
    echo "POST_COMMIT_STAGING_CLEANUP_PRESERVES_FINAL=true\n";
    echo "QUARANTINE_DOES_NOT_DESTROY_STAGING=true\n";
    echo "PERMISSION_QA=PASS\n";
    echo "QA_PRIVATE_ROOT_INSIDE_REPOSITORY=false\n";
    echo "MULTI03A_RESIDUAL_TEMP_ROOT_COUNT=0\n";
    echo 'FILESYSTEM_QA_CASES=' . implode(',', $passed) . "\n";
} catch (Throwable $exception) {
    multi03a_remove_tree($temporaryRoot);
    fwrite(STDERR, "MULTI03A_FILESYSTEM_QA=FAIL\n");
    fwrite(STDERR, 'FIRST_BLOCKER=' . $exception->getMessage() . "\n");
    fwrite(STDERR, "MULTI03A_RESIDUAL_TEMP_ROOT_COUNT=" . count(glob($temporaryRoot, GLOB_ONLYDIR) ?: []) . "\n");
    exit(1);
}
