<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/api/_lib/clinical_private_binary_storage.php';

function reconcile_check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function reconcile_has(array $findings, string $classification, string $key): bool
{
    foreach ($findings as $finding) {
        if (($finding['classification'] ?? null) === $classification && ($finding['key'] ?? null) === $key) {
            return true;
        }
    }
    return false;
}

$now = new DateTimeImmutable('2026-09-19T12:00:00Z');
$healthy = 'clinical/2026/09/healthy/bin-original';
$orphan = 'clinical/2026/09/orphan/bin-original';
$missing = 'clinical/2026/09/missing/bin-original';
$hashMismatch = 'clinical/2026/09/hash/bin-original';
$lengthMismatch = 'clinical/2026/09/length/bin-original';
$duplicate = 'clinical/2026/09/duplicate/bin-original';
$finalizedWithoutResource = 'clinical/2026/09/uncommitted/bin-original';
$stale = 'staging/stale/item';
$leased = 'staging/leased/item';
$future = 'staging/future/item';
$committedStage = 'staging/committed/item';

$inventory = [
    ['key' => $healthy, 'sha256' => str_repeat('a', 64), 'byte_length' => 10],
    ['key' => $orphan, 'sha256' => str_repeat('b', 64), 'byte_length' => 11],
    ['key' => $hashMismatch, 'sha256' => str_repeat('c', 64), 'byte_length' => 12],
    ['key' => $lengthMismatch, 'sha256' => str_repeat('d', 64), 'byte_length' => 13],
    ['key' => $duplicate, 'sha256' => str_repeat('e', 64), 'byte_length' => 14],
    ['key' => $duplicate, 'sha256' => str_repeat('e', 64), 'byte_length' => 14],
    ['key' => $finalizedWithoutResource, 'sha256' => str_repeat('f', 64), 'byte_length' => 15],
    ['key' => $stale, 'sha256' => str_repeat('1', 64), 'byte_length' => 1],
    ['key' => $leased, 'sha256' => str_repeat('2', 64), 'byte_length' => 1],
    ['key' => $future, 'sha256' => str_repeat('3', 64), 'byte_length' => 1],
    ['key' => $committedStage, 'sha256' => str_repeat('4', 64), 'byte_length' => 1],
];

$coordination = [
    ['storage_state' => 'STAGED', 'staging_key' => $stale, 'expires_at' => '2026-09-19T11:59:59Z'],
    ['storage_state' => 'STAGED', 'staging_key' => $leased, 'expires_at' => '2026-09-19T11:00:00Z', 'lease_until' => '2026-09-19T12:00:01Z'],
    ['storage_state' => 'STAGED', 'staging_key' => $future, 'expires_at' => '2026-09-19T12:00:01Z'],
    ['storage_state' => 'STAGED', 'staging_key' => $committedStage, 'expires_at' => '2026-09-19T11:00:00Z', 'committed_resource' => true],
    ['storage_state' => 'FINALIZED', 'final_key' => $finalizedWithoutResource, 'document_id' => null],
];

$manifests = [
    ['storage_key' => $healthy, 'sha256' => str_repeat('a', 64), 'byte_length' => 10, 'document_id' => 1],
    ['storage_key' => $missing, 'sha256' => str_repeat('9', 64), 'byte_length' => 99, 'document_id' => 2],
    ['storage_key' => $hashMismatch, 'sha256' => str_repeat('8', 64), 'byte_length' => 12, 'document_id' => 3],
    ['storage_key' => $lengthMismatch, 'sha256' => str_repeat('d', 64), 'byte_length' => 99, 'document_id' => 4],
    ['storage_key' => $duplicate, 'sha256' => str_repeat('e', 64), 'byte_length' => 14, 'document_id' => 5],
];

try {
    $before = serialize([$inventory, $coordination, $manifests]);
    $findings = ClinicalBinaryReconciliation::classify($inventory, $coordination, $manifests, $now);
    $after = serialize([$inventory, $coordination, $manifests]);

    reconcile_check($before === $after, 'classifier mutated supplied records');
    reconcile_check(reconcile_has($findings, 'HEALTHY_FINALIZED', $healthy), 'HEALTHY_FINALIZED missing');
    reconcile_check(reconcile_has($findings, 'FINAL_WITHOUT_COMMITTED_MANIFEST', $orphan), 'FINAL_WITHOUT_COMMITTED_MANIFEST missing');
    reconcile_check(reconcile_has($findings, 'COMMITTED_MANIFEST_MISSING_BINARY', $missing), 'COMMITTED_MANIFEST_MISSING_BINARY missing');
    reconcile_check(reconcile_has($findings, 'HASH_MISMATCH', $hashMismatch), 'HASH_MISMATCH missing');
    reconcile_check(reconcile_has($findings, 'BYTE_LENGTH_MISMATCH', $lengthMismatch), 'BYTE_LENGTH_MISMATCH missing');
    reconcile_check(reconcile_has($findings, 'UNEXPECTED_DUPLICATE_FINAL_KEY', $duplicate), 'UNEXPECTED_DUPLICATE_FINAL_KEY missing');
    reconcile_check(reconcile_has($findings, 'FINALIZED_COORDINATION_WITHOUT_COMMITTED_RESOURCE', $finalizedWithoutResource), 'FINALIZED_COORDINATION_WITHOUT_COMMITTED_RESOURCE missing');
    reconcile_check(reconcile_has($findings, 'STALE_STAGED', $stale), 'STALE_STAGED missing');
    reconcile_check(!reconcile_has($findings, 'STALE_STAGED', $leased), 'active lease classified stale');
    reconcile_check(!reconcile_has($findings, 'STALE_STAGED', $future), 'unexpired staging classified stale');
    reconcile_check(!reconcile_has($findings, 'STALE_STAGED', $committedStage), 'committed resource classified stale');

    // R1-05: a crash before coordination leaves a discoverable, untouched staging file.
    $untracked = 'staging/untracked/item';
    $retained = 'staging/finalized/item';
    $recoveryStage = 'staging/reconciliation/item';
    $manifestStage = 'staging/manifest/item';
    $r1Inventory = array_merge($inventory, array_map(static fn($key) => ['key'=>$key,'byte_length'=>10,'sha256'=>str_repeat('a',64)], [$untracked,$retained,$recoveryStage,$manifestStage]));
    $r1Rows = array_merge($coordination, [
        ['storage_state'=>'FINALIZED','staging_key'=>$retained,'final_key'=>$healthy,'document_id'=>1],
        ['storage_state'=>'RECONCILIATION_REQUIRED','staging_key'=>$recoveryStage,'committed_resource'=>true],
        ['storage_state'=>'RECONCILIATION_REQUIRED','staging_key'=>$manifestStage,'final_key'=>$healthy],
    ]);
    $r1Before = serialize([$r1Inventory,$r1Rows,$manifests]);
    $r1 = ClinicalBinaryReconciliation::classify($r1Inventory,$r1Rows,$manifests,$now);
    reconcile_check(reconcile_has($r1,'STAGING_WITHOUT_COORDINATION',$untracked), 'R1-05 missing untracked staging');
    reconcile_check(reconcile_has($r1,'HEALTHY_FINALIZED',$healthy) && reconcile_has($r1,'STAGING_RETAINED_AFTER_FINALIZATION',$retained), 'R1-06 healthy final hides staging');
    reconcile_check(reconcile_has($r1,'STAGING_RETAINED_AFTER_FINALIZATION',$recoveryStage), 'R1-07 reconciliation state omitted');
    reconcile_check(reconcile_has($r1,'STAGING_RETAINED_AFTER_FINALIZATION',$manifestStage), 'manifest evidence omitted');
    reconcile_check(reconcile_has($r1,'STALE_STAGED',$stale) && !reconcile_has($r1,'STAGING_WITHOUT_COORDINATION',$stale), 'R1-08 stale staging correlation changed');
    foreach ([$leased,$future,$retained,$recoveryStage,$manifestStage] as $key) {
        reconcile_check(!reconcile_has($r1,'STAGING_WITHOUT_COORDINATION',$key), 'referenced staging classified untracked');
    }
    reconcile_check($r1Before===serialize([$r1Inventory,$r1Rows,$manifests]), 'R1 mutated input');
    echo "MULTI03B_R1_RECONCILIATION_QA=PASS\nR1_RECONCILIATION_CASES=R1-05,R1-06,R1-07,R1-08\n";
    echo "MULTI03A_RECONCILIATION_QA=PASS\n";
    echo "RECONCILIATION_CLASSIFICATIONS_COVERED=10\n";
    echo "RECONCILIATION_CLASSIFIER_MUTATES_STORAGE=false\n";
    echo "RECONCILIATION_CLASSIFIER_MUTATES_DB=false\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "MULTI03A_RECONCILIATION_QA=FAIL\n");
    fwrite(STDERR, 'FIRST_BLOCKER=' . $exception->getMessage() . "\n");
    exit(1);
}
