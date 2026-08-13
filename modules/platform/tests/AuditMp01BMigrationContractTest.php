<?php
declare(strict_types=1);

$root = $argv[1] ?? dirname(__DIR__, 3);
$manifestPath = $root . '/modules/platform/db/migrations/AuditMp01BMigrationManifest.php';
if (!is_file($manifestPath)) { fwrite(STDERR, "manifest missing\n"); exit(2); }
require $manifestPath;

$expectedPaths = [
    'modules/platform/db/migrations/2026_07_27_01_expand_platform_audit_events_audit_v1.sql',
    'modules/platform/db/migrations/2026_07_27_02_backfill_platform_audit_events_audit_v1.sql',
    'modules/platform/db/migrations/2026_07_27_03_tighten_platform_audit_events_audit_v1.sql',
    'modules/platform/db/migrations/2026_07_27_04_guard_platform_audit_events_audit_v1.sql',
    'modules/platform/db/migrations/2026_07_27_05_verify_platform_audit_events_audit_v1.sql',
    'modules/platform/db/migrations/AuditMp01BMigrationManifest.php',
    'modules/platform/tests/AuditMp01BMigrationContractTest.php',
    'docs/product-completion/audit-mp01b-migration-runbook.md'
];
$tests = [];
function audit_check(array &$tests, string $name, bool $condition, $actual = null): void {
    $tests[] = ['name' => $name, 'result' => $condition ? 'PASS' : 'FAIL', 'actual' => $actual];
}
function audit_read(string $root, string $path): string {
    $data = @file_get_contents($root . '/' . $path);
    return $data === false ? '' : $data;
}

$actualPaths = [];
if (is_dir($root)) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if ($file->isFile() && !$file->isLink()) {
            $actualPaths[] = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
        }
    }
}
sort($actualPaths); $expectedSorted = $expectedPaths; sort($expectedSorted);
audit_check($tests, 'exact eight-file scope', $actualPaths === $expectedSorted, $actualPaths);
audit_check($tests, 'no symlinks', count($actualPaths) === count($expectedPaths), count($actualPaths));

$migrations = AuditMp01BMigrationManifest::migrations();
$contracts = AuditMp01BMigrationManifest::contracts();
audit_check($tests, 'manifest enumerates exactly B1-B5', count($migrations) === 5 && array_column($migrations, 'phase') === ['B1','B2','B3','B4','B5'], array_column($migrations, 'phase'));
audit_check($tests, 'manifest migration paths exact', array_column($migrations, 'path') === array_slice($expectedPaths, 0, 5), array_column($migrations, 'path'));
foreach ($migrations as $row) {
    $physical = $root . '/' . $row['path'];
    audit_check($tests, 'migration present ' . $row['phase'], is_file($physical));
    audit_check($tests, 'manifest SHA binding ' . $row['phase'], is_file($physical) && hash_file('sha256', $physical) === $row['sha256']);
}

$b1 = audit_read($root, $expectedPaths[0]);
$b2 = audit_read($root, $expectedPaths[1]);
$b3 = audit_read($root, $expectedPaths[2]);
$b4 = audit_read($root, $expectedPaths[3]);
$b5 = audit_read($root, $expectedPaths[4]);
$runbook = audit_read($root, $expectedPaths[7]);
$allSql = $b1 . "\n" . $b2 . "\n" . $b3 . "\n" . $b4 . "\n" . $b5;
$allBundle = $allSql . "\n" . $runbook . "\n" . json_encode($contracts);

audit_check($tests, 'R46 fold runtime lineage', strpos($b1, 'COUNT -> recursive fold -> final accumulator -> final SHA') !== false);
audit_check($tests, 'R47 incompatible syntax absent', stripos($allSql, 'ADD COLUMN IF NOT EXISTS') === false);
audit_check($tests, 'R47 metadata idempotence', strpos($b1, '@r47_canonical_event_id_exists') !== false && strpos($b1, 'information_schema.columns') !== false && strpos($b1, 'PREPARE r47_add_canonical_event_id_stmt') !== false);
audit_check($tests, 'canonical_event_id remains nullable', strpos($b1, 'ADD COLUMN `canonical_event_id` VARCHAR(128) NULL') !== false && stripos($b1, 'canonical_event_id` VARCHAR(128) NOT NULL') === false);
audit_check($tests, 'R48 direct temporary probes', substr_count($b1, 'R48_BEGIN_B1_') === 2 && substr_count($b1, 'FROM audit_mp01b_phase_receipts;') >= 2 && strpos($b1, 'support_objects < 5') !== false);
audit_check($tests, 'R49 EMPTY batch relation', strpos($b2, '@r43__empty__b2__complete__batch_identifier=CAST(@r43_batch_identifier AS CHAR)') !== false);
audit_check($tests, 'R49 EMPTY inserted relation', strpos($b2, '@r43__empty__b2__complete__inserted_count=@r43_last_inserted_count') !== false);
audit_check($tests, 'R49 EMPTY NO_ROWS resume', strpos($b2, "@r43__empty__b2__complete__resume_stream_key=CAST('NO_ROWS' AS CHAR)") !== false && strpos($b2, "@r43__empty__b2__complete__resume_sequence_number=CAST('NO_ROWS' AS CHAR)") !== false);
audit_check($tests, 'R49 no invented EMPTY IN_PROGRESS', strpos($b2, '@r43__empty__b2__in_progress__') === false);

audit_check($tests, 'B3 insert-only marker', strpos($b3, 'R50_BEGIN_B3_STREAM_HEAD_INSERT_ONLY') !== false && strpos($b3, 'h.stream_key IS NULL') !== false);
audit_check($tests, 'no UPDATE stream heads', preg_match('/\bUPDATE\s+`?platform_audit_stream_heads`?/i', $allSql) === 0);
audit_check($tests, 'no heads ON DUPLICATE KEY UPDATE', preg_match('/platform_audit_stream_heads[\s\S]{0,1200}ON\s+DUPLICATE\s+KEY\s+UPDATE/i', $b3) === 0);
audit_check($tests, 'no heads INSERT IGNORE', preg_match('/INSERT\s+IGNORE\s+INTO\s+`?platform_audit_stream_heads`?/i', $allSql) === 0);
audit_check($tests, 'no heads REPLACE', preg_match('/REPLACE\s+INTO\s+`?platform_audit_stream_heads`?/i', $allSql) === 0);
audit_check($tests, 'R54 four NULL exclusions', substr_count($b3 . $b5, 's.canonical_event_id IS NOT NULL GROUP BY s.canonical_event_id') === 4, substr_count($b3 . $b5, 's.canonical_event_id IS NOT NULL GROUP BY s.canonical_event_id'));

$triggerNames = ['platform_audit_events_no_update','platform_audit_events_no_delete','platform_audit_events_audit_v1_shadow_no_update','platform_audit_events_audit_v1_shadow_no_delete'];
foreach ($triggerNames as $name) audit_check($tests, 'append-only trigger ' . $name, substr_count($allSql, 'CREATE TRIGGER ' . $name . ' ') === 1);
audit_check($tests, 'four append-only trigger definitions', preg_match_all('/CREATE\s+TRIGGER\s+platform_audit_events[^\s]*/i', $allSql) === 4);
audit_check($tests, 'R53 raw guard count', strpos($b4, 'SELECT COUNT(*) INTO @r43_pc_guard_identity_count') !== false && strpos($b4, "event_manipulation IN ('UPDATE','DELETE')") !== false);
audit_check($tests, 'R51 route-aware B4 binding', strpos($b4, "WHEN @audit_mp01b_route='EMPTY'") !== false && strpos($b4, "WHEN @audit_mp01b_route='POPULATED'") !== false);
audit_check($tests, 'R52 pseudo assertions absent', strpos($allSql, 'ASSERT_EXPECTED') === false);

preg_match('/INSERT INTO audit_mp01b_required_privileges VALUES(.*?);/s', $b1, $requiredMatch);
preg_match('/INSERT INTO audit_mp01b_prohibited_privileges VALUES(.*?);/s', $b1, $prohibitedMatch);
$requiredBlock = $requiredMatch[1] ?? ''; $prohibitedBlock = $prohibitedMatch[1] ?? '';
audit_check($tests, 'required privileges 13', substr_count($requiredBlock, "('") === 13, substr_count($requiredBlock, "('"));
audit_check($tests, 'prohibited privileges 3', substr_count($prohibitedBlock, "('") === 3, substr_count($prohibitedBlock, "('"));
audit_check($tests, 'UPDATE heads not required', preg_match("/\('UPDATE'[^\n]*platform_audit_stream_heads/i", $requiredBlock) === 0);
audit_check($tests, 'SUPER privilege absent', preg_match("/\('SUPER'|GRANT\s+SUPER/i", $allSql) === 0);

audit_check($tests, 'legacy identifier and hash copied', strpos($b2, 'SELECT e.*,NULL FROM platform_audit_events e') !== false && strpos($allSql, 'preserved_event_hash_sha256') !== false);
audit_check($tests, 'no canonical ID invention/backfill', preg_match('/UPDATE\s+platform_audit_events_audit_v1_shadow\s+SET\s+canonical_event_id/i', $allSql) === 0);
audit_check($tests, 'receipt schema present', strpos($b1, 'audit_mp01b_phase_receipts') !== false && strpos($allSql, 'previous_receipt_sha256') !== false);
audit_check($tests, 'EMPTY and POPULATED paths', strpos($allSql, "p_route='EMPTY'") !== false && strpos($allSql, "p_route='POPULATED'") !== false);

audit_check($tests, 'writer remains disabled', strpos($allBundle, 'RUNTIME_WRITER_ACTIVATED=true') === false && $contracts['runtime']['writer_activated'] === false);
audit_check($tests, 'producers remain disabled', strpos($allBundle, 'RUNTIME_PRODUCERS_ACTIVATED=true') === false && $contracts['runtime']['producers_activated'] === false);
audit_check($tests, 'MP01C deferred', strpos($runbook, 'WRITER_IMPLEMENTATION_DEFERRED_TO_MP01C=true') !== false && $contracts['runtime']['writer_deferred_to'] === 'AUDIT-MP01C');
audit_check($tests, 'rollback honesty', $contracts['rollback']['automatic_destructive_rollback'] === false && strpos($runbook, 'backup required') !== false && strpos($runbook, 'restore rehearsal required') !== false);
audit_check($tests, 'real database not authorized', strpos($runbook, 'REAL_DATABASE_EXECUTION_AUTHORIZED=false') !== false);
audit_check($tests, 'future results not hardcoded', $contracts['future_execution_results_hardcoded'] === false && strpos(audit_read($root, 'modules/platform/db/migrations/AuditMp01BMigrationManifest.php'), '58/58') === false);

$failures = array_values(array_filter($tests, static function(array $row): bool { return $row['result'] !== 'PASS'; }));
foreach ($tests as $row) echo json_encode($row, JSON_UNESCAPED_SLASHES) . "\n";
echo 'STATIC_TESTS_TOTAL=' . count($tests) . "\n";
echo 'STATIC_TESTS_PASS=' . (count($tests) - count($failures)) . '/' . count($tests) . "\n";
echo 'STATIC_TESTS_FAIL=' . count($failures) . "\n";
exit(count($failures) === 0 ? 0 : 1);
