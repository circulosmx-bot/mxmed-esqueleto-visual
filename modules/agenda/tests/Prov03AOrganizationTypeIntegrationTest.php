<?php
declare(strict_types=1);

// Run only against an explicitly named disposable database after the PROV03A migration.
$database = (string)(getenv('MXMED_PROV03A_TEST_DB') ?: '');
if ($database === '' || !str_contains($database, 'disposable')) {
    throw new RuntimeException('disposable database required');
}

require_once __DIR__ . '/../repositories/MedicalGroupsRepository.php';
require_once __DIR__ . '/../repositories/MedicalGroupMembershipsRepository.php';
require_once __DIR__ . '/../repositories/ConsultoriosRepository.php';
require_once __DIR__ . '/../controllers/MedicalGroupsController.php';
require_once __DIR__ . '/../../identity/contracts/OrganizationTypeLookupPort.php';
require_once __DIR__ . '/../../identity/repositories/MedicalGroupTypeRepository.php';

function prov03aCheck(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function prov03aReject(callable $operation, string $message): void
{
    try { $operation(); } catch (InvalidArgumentException|RuntimeException $e) { return; }
    throw new RuntimeException($message);
}

$pdo = new PDO('mysql:host=localhost;dbname=' . $database . ';charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
prov03aCheck($pdo->query('SELECT DATABASE()')->fetchColumn() === $database, 'database identity');
$groups = new Agenda\Repositories\MedicalGroupsRepository($pdo);
$memberships = new Agenda\Repositories\MedicalGroupMembershipsRepository($pdo);
$offices = new Agenda\Repositories\ConsultoriosRepository($pdo);

prov03aCheck(($groups->findById('mg_prov03a_legacy')['organization_type_key'] ?? null) === 'MEDICAL_GROUP', 'historical type backfilled');
prov03aReject(fn() => $groups->upsertGroup(['group_id' => 'mg_prov03a_missing_type', 'display_name' => 'Missing type']), 'new type is required');
prov03aReject(fn() => $groups->upsertGroup(['group_id' => 'mg_prov03a_unknown', 'organization_type_key' => 'UNKNOWN', 'display_name' => 'Unknown']), 'unknown type rejected');

$groups->upsertGroup(['group_id' => 'mg_prov03a_med', 'organization_type_key' => 'MEDICAL_GROUP', 'display_name' => 'PROV03A Medical Group', 'status' => 'verified']);
foreach (['LABORATORY', 'DIAGNOSTIC_CENTER', 'HOSPITAL'] as $type) {
    $groups->upsertGroup(['group_id' => 'mg_prov03a_' . strtolower($type), 'organization_type_key' => $type, 'display_name' => 'PROV03A ' . $type, 'status' => 'verified']);
}
$groups->upsertGroup(['group_id' => 'mg_prov03a_pending_med', 'organization_type_key' => 'MEDICAL_GROUP', 'display_name' => 'PROV03A Pending Medical', 'status' => 'pending']);
$groups->upsertGroup(['group_id' => 'mg_prov03a_pending_lab', 'organization_type_key' => 'LABORATORY', 'display_name' => 'PROV03A Pending Laboratory', 'status' => 'pending']);
$laboratory = 'mg_prov03a_laboratory';
$typeLookup = new Identity\Repositories\MedicalGroupTypeRepository($pdo);
prov03aCheck($typeLookup->typeForGroup($laboratory) === 'LABORATORY', 'authorization reads database type');
prov03aCheck($typeLookup->typeForGroup('mg_prov03a_med') === 'MEDICAL_GROUP', 'authorization reads existing medical type');
prov03aReject(fn() => $groups->upsertGroup(['group_id' => $laboratory, 'organization_type_key' => 'MEDICAL_GROUP', 'display_name' => 'Invalid conversion']), 'type immutable');
prov03aReject(fn() => $pdo->exec("UPDATE medical_groups SET organization_type_key = 'MEDICAL_GROUP' WHERE group_id = 'mg_prov03a_laboratory'"), 'direct SQL type change rejected');
prov03aCheck($typeLookup->typeForGroup($laboratory) === 'LABORATORY', 'direct SQL type unchanged');

foreach ([$groups->searchVerified(), $groups->searchVerifiedByContext(), $groups->searchVerified('PROV03A')] as $rows) {
    foreach ($rows as $row) prov03aCheck(($groups->findById($row['group_id'])['organization_type_key'] ?? null) === 'MEDICAL_GROUP', 'provider excluded from physician search');
}
foreach ($groups->listPending() as $row) prov03aCheck($row['organization_type_key'] === 'MEDICAL_GROUP', 'provider excluded from physician review list');
$pdo->exec("INSERT IGNORE INTO consultorios (doctor_id, consultorio_id) VALUES ('doctor_prov03a', 'office_prov03a')");
prov03aReject(fn() => $memberships->upsertMembership(['doctor_id' => 'doctor_prov03a', 'consultorio_id' => 'office_prov03a', 'group_id' => $laboratory]), 'provider affiliation rejected');
prov03aReject(fn() => $offices->updateGroupSnapshot('doctor_prov03a', 'office_prov03a', $laboratory, 'Laboratory', null), 'provider office snapshot rejected');
$offices->updateGroupSnapshot('doctor_prov03a', 'office_prov03a', 'mg_prov03a_med', 'Medical Group', null);
$memberships->upsertMembership(['doctor_id' => 'doctor_prov03a', 'consultorio_id' => 'office_prov03a', 'group_id' => 'mg_prov03a_med']);

$controller = new Agenda\Controllers\MedicalGroupsController();
prov03aCheck(($controller->join($laboratory, ['doctor_id' => 'doctor_prov03a', 'consultorio_id' => 'office_prov03a'])['ok'] ?? null) === false, 'provider join rejected');
prov03aCheck(($controller->join('mg_prov03a_med', ['doctor_id' => 'doctor_prov03a', 'consultorio_id' => 'office_prov03a'])['ok'] ?? null) === true, 'medical group join preserved');
foreach (($controller->pending()['data'] ?? []) as $row) prov03aCheck(($groups->findById($row['group_id'])['organization_type_key'] ?? null) === 'MEDICAL_GROUP', 'provider excluded from review API');
prov03aCheck(($controller->approve($laboratory)['ok'] ?? null) === false, 'provider review rejected');
prov03aCheck(($controller->merge($laboratory, ['target_group_id' => 'mg_prov03a_med'])['ok'] ?? null) === false, 'provider source merge rejected');
prov03aCheck(($controller->merge('mg_prov03a_med', ['target_group_id' => $laboratory])['ok'] ?? null) === false, 'provider target merge rejected');
prov03aCheck(($controller->approve('mg_prov03a_med')['ok'] ?? null) === true, 'medical group review preserved');
prov03aCheck(($controller->merge('mg_prov03a_med', ['target_group_id' => 'mg_prov03a_legacy'])['ok'] ?? null) === true, 'medical group merge preserved');
prov03aCheck(($groups->findById('mg_prov03a_med')['organization_type_key'] ?? null) === 'MEDICAL_GROUP', 'merge kept type');

echo "PROV03A disposable organization type integration PASS\n";
