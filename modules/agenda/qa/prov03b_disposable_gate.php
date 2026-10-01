<?php
declare(strict_types=1);

use Agenda\Repositories\MedicalGroupsRepository;
use Agenda\Services\HealthcareOrganizationDirectoryService;

require_once __DIR__.'/../services/HealthcareOrganizationDirectoryService.php';
require_once __DIR__.'/../repositories/MedicalGroupsRepository.php';

$db = getenv('PROV03B_QA_DB');
if (!is_string($db) || !preg_match('/^prov03b_qa_[a-f0-9]{12}$/D', $db)) {
    throw new RuntimeException('disposable_db_required');
}
$pdo = new PDO('mysql:host=localhost;dbname='.$db.';charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$service = new HealthcareOrganizationDirectoryService($pdo);
$check = static function (bool $condition, string $name): void {
    if (!$condition) {
        throw new RuntimeException($name);
    }
    echo $name."=PASS\n";
};
$denied = static function (callable $fn, string $name) use ($check): void {
    try {
        $fn();
    } catch (InvalidArgumentException|RuntimeException $e) {
        $check(true, $name);
        return;
    }
    $check(false, $name);
};

foreach (['org_a' => 'LABORATORY', 'org_b' => 'HOSPITAL', 'org_physician' => 'MEDICAL_GROUP'] as $id => $type) {
    $pdo->prepare('INSERT INTO medical_groups(group_id,organization_type_key,canonical_name,display_name,status,source)
        VALUES(:id,:type,:canonical,:display,:status,:source)')->execute([
        'id' => $id, 'type' => $type, 'canonical' => $id, 'display' => $id,
        'status' => 'verified', 'source' => 'operator_created',
    ]);
}
$catalogBefore = $pdo->query('SELECT COUNT(*) FROM clinical_study_types')->fetchColumn();
$pdo->exec("INSERT INTO catalog_cp_colonias(cp,colonia,municipio,estado) VALUES('01000','Centro','Ciudad de México','Ciudad de México')");
$studyIds = array_map('intval', $pdo->query('SELECT study_type_id FROM clinical_study_types WHERE is_active=1 ORDER BY study_type_id LIMIT 3')->fetchAll(PDO::FETCH_COLUMN));
$check(count($studyIds) === 3, 'QA_ACTIVE_STUDIES_AVAILABLE');
[$x, $y, $z] = $studyIds;

$a1 = $service->createLocation('org_a', ['branch_name' => 'Sucursal A1', 'postal_code' => '01000',
    'colonia' => 'Centro', 'municipality' => 'Ciudad de México', 'state_name' => 'Ciudad de México']);
$a2 = $service->createLocation('org_a', ['branch_name' => 'Sucursal A2']);
$b1 = $service->createLocation('org_b', ['branch_name' => 'Sucursal B1',
    'latitude' => '19.4326000', 'longitude' => '-99.1332000', 'coordinate_source' => 'operator_qa']);
$physicianLocation = $service->createLocation('org_physician', ['branch_name' => 'Sede administrativa']);
$check($physicianLocation['group_id'] === 'org_physician', 'QA_MEDICAL_GROUP_LOCATION_ALLOWED_BUT_SEPARATE');
$check($a1['location_id'] !== $a2['location_id'] && $a1['location_uuid'] !== $a2['location_uuid']
    && $a1['group_id'] === 'org_a' && $b1['group_id'] === 'org_b', 'QA_MULTIPLE_LOCATIONS');
$originalUuid = $a1['location_uuid'];
$renamed = $service->updateLocation('org_a', $originalUuid, ['branch_name' => 'Sucursal A1 renovada', 'phone' => '5512345678']);
$check($renamed['location_uuid'] === $originalUuid && $renamed['location_id'] === $a1['location_id'], 'QA_STABLE_LOCATION_IDENTITY');
$denied(fn () => $service->createLocation('org_a', ['branch_name' => 'Bad geography', 'postal_code' => '01000', 'colonia' => 'No catalogada']), 'QA_POSTAL_CATALOG_VALIDATION');
$denied(fn () => $service->updateLocation('org_a', $originalUuid, ['location_uuid' => $b1['location_uuid']]), 'QA_LOCATION_IDENTITY_NOT_WRITABLE');
$denied(fn () => $service->createLocation('org_a', ['branch_name' => 'Bad coordinate', 'latitude' => '19.4']), 'QA_COORDINATE_PAIR_REQUIRED');
$check($b1['latitude'] !== null && $b1['longitude'] !== null && $b1['coordinate_source'] === 'operator_qa', 'QA_OPTIONAL_COORDINATES');

$service->createOffering('org_a', $a1['location_uuid'], $x, ['requires_appointment' => true,
    'preparation_instructions' => 'Ayuno según indicación del proveedor.']);
$service->createOffering('org_a', $a1['location_uuid'], $y);
$service->createOffering('org_a', $a2['location_uuid'], $x);
$service->createOffering('org_b', $b1['location_uuid'], $x);
$service->createOffering('org_b', $b1['location_uuid'], $z);
$projectionA = $service->readOrganization('org_a');
$projectionB = $service->readOrganization('org_b');
$check(count($projectionA['locations']) === 2 && count($projectionA['locations'][0]['offerings']) === 2
    && count($projectionA['locations'][1]['offerings']) === 1 && count($projectionB['locations']) === 1
    && count($projectionB['locations'][0]['offerings']) === 2, 'QA_BRANCH_LEVEL_OFFERINGS');
$check($projectionA['locations'][0]['offerings'][0]['study_type_key'] !== ''
    && $projectionA['locations'][0]['offerings'][0]['display_name_es'] !== ''
    && $projectionA['locations'][0]['offerings'][0]['category_key'] !== '', 'QA_CANONICAL_READ_PROJECTION');
$denied(fn () => $service->createOffering('org_a', $a1['location_uuid'], $x), 'QA_DUPLICATE_OFFERING');
$denied(fn () => $service->updateLocation('org_b', $a1['location_uuid'], ['branch_name' => 'Cross scope']), 'QA_CROSS_ORG_LOCATION_WRITE');
$denied(fn () => $service->updateOffering('org_b', $a1['location_uuid'], $x, ['operational_state' => 'INACTIVE']), 'QA_CROSS_ORG_OFFERING_WRITE');
$check(count($service->readOrganization('org_b')['locations']) === 1, 'QA_CROSS_ORG_READ_SCOPE');

$pdo->prepare('UPDATE clinical_study_types SET is_active=0 WHERE study_type_id=:id')->execute(['id' => $z]);
$denied(fn () => $service->createOffering('org_a', $a2['location_uuid'], $z), 'QA_INACTIVE_STUDY_NEW_OFFERING');
$check(count($service->readOrganization('org_b')['locations'][0]['offerings']) === 2, 'QA_INACTIVE_STUDY_HISTORICAL_ASSOCIATION');
$denied(fn () => $service->createOffering('org_a', $a2['location_uuid'], 999999999), 'QA_UNKNOWN_STUDY_REJECTED');
$denied(fn () => $service->createLocation('missing_org', ['branch_name' => 'No existe']), 'QA_UNKNOWN_ORGANIZATION_REJECTED');
$denied(fn () => $service->setOfferingVerification('org_a', $a1['location_uuid'], $x, 'VERIFIED', ''), 'QA_VERIFICATION_ACTOR_REQUIRED');

$service->setLocationVerification('org_a', $a1['location_uuid'], 'VERIFIED', 'operator_qa');
$service->setOfferingVerification('org_a', $a1['location_uuid'], $x, 'VERIFIED', 'operator_qa');
$service->updateLocation('org_a', $a1['location_uuid'], ['operational_state' => 'INACTIVE']);
$service->updateOffering('org_a', $a1['location_uuid'], $x, ['operational_state' => 'INACTIVE']);
$lifecycle = $service->readOrganization('org_a')['locations'][0];
$check($lifecycle['operational_state'] === 'INACTIVE' && $lifecycle['verification_state'] === 'VERIFIED'
    && count($lifecycle['offerings']) === 2, 'QA_LOCATION_LIFECYCLE');
$check($lifecycle['offerings'][0]['operational_state'] === 'INACTIVE'
    && $lifecycle['offerings'][0]['verification_state'] === 'VERIFIED', 'QA_OFFERING_LIFECYCLE');
$denied(fn () => $service->createOffering('org_a', $a1['location_uuid'], $x, ['service_mode' => 'HOME_SERVICE']), 'QA_ON_SITE_ONLY');

$groups = new MedicalGroupsRepository($pdo);
$visible = $groups->searchVerified();
$check(count($visible) === 1 && $visible[0]['group_id'] === 'org_physician', 'QA_MEDICAL_GROUP_SEARCH_REGRESSION');
$check((int)$pdo->query('SELECT COUNT(*) FROM clinical_study_types')->fetchColumn() === (int)$catalogBefore,
    'QA_STUDY_CATALOG_COUNT_REGRESSION');
$check((int)$pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name="consultorios"')->fetchColumn() === 0,
    'QA_CONSULTORIOS_SEPARATE');
