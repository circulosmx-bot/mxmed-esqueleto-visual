<?php
declare(strict_types=1);

function cut01cDdlAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$root = dirname(__DIR__, 3);
$runtimeFiles = [
    'modules/agenda/repositories/AgendaSettingsRepository.php',
    'modules/agenda/repositories/ConsultoriosRepository.php',
    'modules/agenda/repositories/MedicalGroupsRepository.php',
    'modules/agenda/repositories/MedicalGroupMembershipsRepository.php',
    'modules/agenda/repositories/MedicalGroupReviewLogRepository.php',
    'modules/agenda/controllers/PublicAppointmentsController.php',
];
$surfaces = [
    'agenda_settings',
    'consultorios',
    'medical_groups',
    'medical_group_memberships',
    'medical_group_review_log',
    'agenda_public_otp_requests',
    'agenda_public_appointment_flows',
];
cut01cDdlAssert(count($surfaces) === 7 && count(array_unique($surfaces)) === 7, 'seven exact DDL surfaces');
foreach ($runtimeFiles as $relative) {
    $source = file_get_contents($root . '/' . $relative);
    cut01cDdlAssert(is_string($source), 'runtime source readable: ' . $relative);
    cut01cDdlAssert(preg_match('/\b(?:CREATE|ALTER|DROP)\s+TABLE\b/i', $source) === 0, 'runtime DDL removed: ' . $relative);
    cut01cDdlAssert(!str_contains($source, '$this->pdo->exec('), 'runtime direct exec removed: ' . $relative);
    cut01cDdlAssert(str_contains($source, 'information_schema'), 'read-only schema readiness present: ' . $relative);
    cut01cDdlAssert(str_contains($source, 'schema_not_ready'), 'homogeneous readiness failure present: ' . $relative);
}

$migrationDir = $root . '/modules/agenda/db/migrations';
$expected = [];
for ($index = 1; $index <= 7; $index++) {
    $number = sprintf('%02d', $index);
    $names = [
        1 => 'agenda_settings',
        2 => 'consultorios',
        3 => 'medical_groups',
        4 => 'medical_group_memberships',
        5 => 'medical_group_review_log',
        6 => 'agenda_public_otp_requests',
        7 => 'agenda_public_appointment_flows',
    ];
    $expected[] = "2026_07_23_{$number}_create_{$names[$index]}.sql";
    $expected[] = "2026_07_23_{$number}_rollback_{$names[$index]}.sql";
}
$actual = array_map('basename', glob($migrationDir . '/2026_07_23_*.sql') ?: []);
sort($actual, SORT_STRING);
$sortedExpected = $expected;
sort($sortedExpected, SORT_STRING);
cut01cDdlAssert($actual === $sortedExpected && count($actual) === 14, 'seven exact migration pairs');

foreach ($surfaces as $offset => $table) {
    $number = sprintf('%02d', $offset + 1);
    $forward = file_get_contents("{$migrationDir}/2026_07_23_{$number}_create_{$table}.sql");
    $rollback = file_get_contents("{$migrationDir}/2026_07_23_{$number}_rollback_{$table}.sql");
    cut01cDdlAssert(str_starts_with($forward, '-- Declarative CUT01-C artifact. This migration is not executed by Activity 13.'), 'forward declarative marker');
    cut01cDdlAssert(str_contains($forward, 'CREATE TABLE IF NOT EXISTS ' . $table), 'forward table exact: ' . $table);
    cut01cDdlAssert(str_contains($forward, 'ENGINE=InnoDB') && str_contains($forward, 'CHARSET=utf8mb4') && str_contains($forward, 'utf8mb4_unicode_ci'), 'forward storage definition');
    cut01cDdlAssert(str_starts_with($rollback, '-- Declarative CUT01-C rollback. This file is not executed by Activity 13.'), 'rollback declarative marker');
}

foreach (['01_rollback_agenda_settings', '03_rollback_medical_groups', '04_rollback_medical_group_memberships'] as $stem) {
    $rollback = file_get_contents($migrationDir . '/2026_07_23_' . $stem . '.sql');
    cut01cDdlAssert(str_contains($rollback, 'PRE-DATA ONLY') && str_contains($rollback, 'DROP TABLE IF EXISTS'), 'pre-data rollback constrained');
}
foreach ([
    '02_rollback_consultorios' => 'snapshot',
    '05_rollback_medical_group_review_log' => 'audit',
    '06_rollback_agenda_public_otp_requests' => 'evidence',
    '07_rollback_agenda_public_appointment_flows' => 'reconcile',
] as $stem => $marker) {
    $rollback = file_get_contents($migrationDir . '/2026_07_23_' . $stem . '.sql');
    cut01cDdlAssert(!str_contains($rollback, 'DROP TABLE') && stripos($rollback, $marker) !== false, 'non-destructive rollback: ' . $stem);
}

$consultorios = file_get_contents($root . '/modules/agenda/repositories/ConsultoriosRepository.php');
foreach (['group_id', 'idx_consultorios_group_id', 'lat', 'lng', 'geocode_source', 'geocode_updated_at', 'logo_url', 'foto_url', 'longtext', 'columnExists', 'indexExists', 'columnDataType'] as $needle) {
    cut01cDdlAssert(str_contains(strtolower($consultorios), strtolower($needle)), 'consultorios readiness: ' . $needle);
}
$consultMigration = file_get_contents($migrationDir . '/2026_07_23_02_create_consultorios.sql');
cut01cDdlAssert(substr_count($consultMigration, 'ALTER TABLE consultorios') === 8, 'eight legacy consultorios preparations transferred');
$publicController = file_get_contents($root . '/modules/agenda/controllers/PublicAppointmentsController.php');
cut01cDdlAssert(str_contains($publicController, 'private function ensureOtpTable') && str_contains($publicController, 'private function ensureFlowTable'), 'public helpers preserved');
cut01cDdlAssert(substr_count($publicController, "'schema_not_ready', 'service temporarily unavailable'") >= 6, 'public schema errors are generic');

echo "Cut01CRequestDdlContainmentTest PASS\n";
