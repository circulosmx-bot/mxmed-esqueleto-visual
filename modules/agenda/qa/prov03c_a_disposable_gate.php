<?php
declare(strict_types=1);

use Agenda\Services\HealthcareOrganizationDirectoryService;
use Agenda\Services\HealthcareProviderCoverageService;

require_once __DIR__.'/../services/HealthcareOrganizationDirectoryService.php';
require_once __DIR__.'/../services/HealthcareProviderCoverageService.php';

$db = getenv('PROV03C_QA_DB');
if (!is_string($db) || !preg_match('/^prov03c_qa_[a-f0-9]{12}$/D', $db)) {
    throw new RuntimeException('disposable_db_required');
}
$pdo = new PDO('mysql:host=localhost;dbname='.$db.';charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$directory = new HealthcareOrganizationDirectoryService($pdo);
$coverage = new HealthcareProviderCoverageService($pdo);
$check = static function (bool $condition, string $name): void {
    if (!$condition) {
        throw new RuntimeException($name);
    }
    echo $name."=PASS\n";
};
$denied = static function (callable $operation, string $name) use ($check): void {
    try {
        $operation();
    } catch (InvalidArgumentException|RuntimeException $e) {
        $check(true, $name);
        return;
    }
    $check(false, $name);
};
$check($pdo->query("SELECT service_mode FROM healthcare_organization_location_study_offerings o
    JOIN healthcare_organization_locations l ON l.location_id=o.location_id WHERE l.group_id='org_existing'")->fetchColumn() === 'ON_SITE',
    'QA_PREEXISTING_ON_SITE_PRESERVED');

foreach (['org_a' => 'LABORATORY', 'org_b' => 'HOSPITAL', 'org_med' => 'MEDICAL_GROUP'] as $id => $type) {
    $pdo->prepare('INSERT INTO medical_groups(group_id,organization_type_key,canonical_name,display_name,status,source)
        VALUES(:id,:type,:canonical,:display,:status,:source)')->execute([
            'id' => $id, 'type' => $type, 'canonical' => $id, 'display' => $id,
            'status' => 'verified', 'source' => 'operator_created',
        ]);
}
$a = $coverage->initializeProvider('org_a');
$b = $coverage->initializeProvider('org_b');
$check($a['operational_state'] === 'INACTIVE' && $a['verification_state'] === 'UNVERIFIED'
    && $b['verification_state'] === 'UNVERIFIED', 'QA_ORGANIZATION_TYPE_NO_AUTO_GRANT');
$denied(fn () => $coverage->initializeProvider('missing'), 'QA_PROVIDER_ORGANIZATION_REQUIRED');
$denied(fn () => $coverage->setProviderVerification('org_a', 'VERIFIED', ''), 'QA_PROVIDER_VERIFICATION_ACTOR_REQUIRED');
$a = $coverage->setProviderVerification('org_a', 'VERIFIED', 'operator_qa', 'Diagnostic provider reviewed');
$check($a['verification_state'] === 'VERIFIED' && $a['operational_state'] === 'INACTIVE'
    && $a['verification_actor_user_id'] === 'operator_qa' && $a['verification_at'] !== null,
    'QA_PROVIDER_VERIFIED_INACTIVE');
$a = $coverage->setProviderOperationalState('org_a', 'ACTIVE');
$check($a['verification_state'] === 'VERIFIED' && $a['operational_state'] === 'ACTIVE', 'QA_PROVIDER_VERIFIED_ACTIVE');
$check($coverage->initializeProvider('org_b')['verification_state'] === 'UNVERIFIED', 'QA_PROVIDER_UNVERIFIED');
$coverage->initializeProvider('org_med');
$coverage->setProviderVerification('org_med', 'VERIFIED', 'operator_qa');
$check($coverage->initializeProvider('org_med')['verification_state'] === 'VERIFIED', 'QA_MEDICAL_GROUP_EXPLICIT_PROVIDER_ALLOWED');

$pdo->exec("INSERT INTO profile_subscriptions(subscription_id,entity_type,entity_id,plan_code,contracted_plan_code,effective_plan_code,status)
    VALUES('00000000-0000-4000-8000-000000000003','hospital','org_b','professional','professional','professional','active')");
$check($coverage->initializeProvider('org_b')['verification_state'] === 'UNVERIFIED'
    && $coverage->initializeProvider('org_b')['operational_state'] === 'INACTIVE',
    'QA_SUBSCRIPTION_INDEPENDENCE');

$studyIds = array_map('intval', $pdo->query('SELECT study_type_id FROM clinical_study_types WHERE is_active=1 ORDER BY study_type_id LIMIT 2')->fetchAll(PDO::FETCH_COLUMN));
$check(count($studyIds) === 2, 'QA_ACTIVE_STUDIES_AVAILABLE');
[$x, $y] = $studyIds;
$a1 = $directory->createLocation('org_a', ['branch_name' => 'A1', 'postal_code' => '01000']);
$b1 = $directory->createLocation('org_b', ['branch_name' => 'B1', 'postal_code' => '02000']);
$directory->createOffering('org_a', $a1['location_uuid'], $x, ['service_mode' => 'HOME_SERVICE']);
$directory->createOffering('org_a', $a1['location_uuid'], $y, ['service_mode' => 'MOBILE']);
$directory->createOffering('org_b', $b1['location_uuid'], $x);
$check($directory->readOrganization('org_a')['locations'][0]['verification_state'] === 'UNVERIFIED'
    && $directory->readOrganization('org_a')['locations'][0]['offerings'][0]['verification_state'] === 'UNVERIFIED',
    'QA_PROVIDER_VERIFICATION_DOES_NOT_CASCADE');
$directory->setLocationVerification('org_a', $a1['location_uuid'], 'VERIFIED', 'operator_qa');
$directory->setOfferingVerification('org_a', $a1['location_uuid'], $x, 'VERIFIED', 'operator_qa');
$directory->setOfferingVerification('org_a', $a1['location_uuid'], $y, 'VERIFIED', 'operator_qa');
$check($directory->readOrganization('org_a')['provider_status']['verification_state'] === 'VERIFIED'
    && $directory->readOrganization('org_a')['provider_status']['operational_state'] === 'ACTIVE'
    && $directory->readOrganization('org_a')['locations'][0]['verification_state'] === 'VERIFIED',
    'QA_PROVIDER_LOCATION_OFFERING_INDEPENDENT_STATES');
$check($directory->readOrganization('org_b')['provider_status']['verification_state'] === 'UNVERIFIED'
    && $directory->readOrganization('org_b')['locations'][0]['verification_state'] === 'UNVERIFIED'
    && $directory->readOrganization('org_b')['locations'][0]['offerings'][0]['verification_state'] === 'UNVERIFIED'
    && $directory->readOrganization('org_b')['locations'][0]['offerings'][0]['service_areas'] === [],
    'QA_ON_SITE_NO_SERVICE_AREA');
$denied(fn () => $coverage->createServiceArea('org_b', $b1['location_uuid'], $x, 'POSTAL_CODE', '01000'),
    'QA_ON_SITE_AREA_REJECTED');

$x1 = $coverage->createServiceArea('org_a', $a1['location_uuid'], $x, 'POSTAL_CODE', '01000');
$x2 = $coverage->createServiceArea('org_a', $a1['location_uuid'], $x, 'POSTAL_CODE', '01001');
$y1 = $coverage->createServiceArea('org_a', $a1['location_uuid'], $y, 'POSTAL_CODE', '01000');
$check($x1['region_key'] === 'MX|CP|01000' && $x2['region_key'] === 'MX|CP|01001'
    && $x1['service_area_id'] !== $x2['service_area_id'], 'QA_MULTIPLE_AREAS_AND_REGION_KEY');
$check($y1['region_key'] === $x1['region_key'] && $y1['offering_id'] !== $x1['offering_id'],
    'QA_SHARED_REGION_DIFFERENT_OFFERING');
$denied(fn () => $coverage->createServiceArea('org_a', $a1['location_uuid'], $x, 'POSTAL_CODE', '01000'),
    'QA_DUPLICATE_SERVICE_AREA');
$denied(fn () => $pdo->prepare('INSERT INTO healthcare_organization_location_study_service_areas
    (offering_id,scope_type,region_key) VALUES(:offering_id,:scope_type,:region_key)')->execute([
        'offering_id' => $x1['offering_id'], 'scope_type' => 'POSTAL_CODE', 'region_key' => 'MX|CP|01000',
    ]), 'QA_DATABASE_DUPLICATE_UNIQUE');
$denied(fn () => $coverage->createServiceArea('org_a', $a1['location_uuid'], $x, 'POSTAL_CODE', '1000'),
    'QA_INVALID_POSTAL_CODE');
$denied(fn () => $coverage->createServiceArea('org_a', $a1['location_uuid'], $x, 'STATE', '01000'),
    'QA_UNSUPPORTED_SCOPE_REJECTED');

$x1 = $coverage->setServiceAreaVerification('org_a', $a1['location_uuid'], $x, (int)$x1['service_area_id'], 'VERIFIED', 'operator_qa');
$check($x1['verification_state'] === 'VERIFIED' && $x1['verification_at'] !== null
    && $x1['operational_state'] === 'ACTIVE', 'QA_SERVICE_AREA_VERIFICATION');
$x1 = $coverage->setServiceAreaOperationalState('org_a', $a1['location_uuid'], $x, (int)$x1['service_area_id'], 'INACTIVE');
$check($x1['verification_state'] === 'VERIFIED' && $x1['operational_state'] === 'INACTIVE'
    && count($directory->readOrganization('org_a')['locations'][0]['offerings'][0]['service_areas']) === 2,
    'QA_SERVICE_AREA_LIFECYCLE');
$denied(fn () => $coverage->setServiceAreaOperationalState('org_b', $a1['location_uuid'], $x,
    (int)$x1['service_area_id'], 'ACTIVE'), 'QA_CROSS_ORGANIZATION_AREA_WRITE');
$check(count($directory->readOrganization('org_b')['locations'][0]['offerings'][0]['service_areas']) === 0,
    'QA_CROSS_ORGANIZATION_READ_SCOPE');

$pdo->exec("CREATE TABLE catalog_cp_colonias (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,cp VARCHAR(5) NOT NULL,
    colonia VARCHAR(190) NOT NULL,municipio VARCHAR(190) NOT NULL,estado VARCHAR(190) NOT NULL,is_active TINYINT(1) NOT NULL)");
$pdo->exec("INSERT INTO catalog_cp_colonias(cp,colonia,municipio,estado,is_active)
    VALUES('01002','Centro','Ciudad de México','Ciudad de México',1)");
$coverage->createServiceArea('org_a', $a1['location_uuid'], $x, 'POSTAL_CODE', '01002');
$denied(fn () => $coverage->createServiceArea('org_a', $a1['location_uuid'], $x, 'POSTAL_CODE', '01003'),
    'QA_CATALOG_VALIDATION_WHEN_AVAILABLE');
$check(count($directory->readOrganization('org_a')['locations'][0]['offerings'][0]['service_areas']) === 3,
    'QA_DIRECTORY_READ_MODEL_EXTENDED');

$check((int)$pdo->query('SELECT COUNT(*) FROM clinical_study_types')->fetchColumn() === 183,
    'QA_STUDY_CATALOG_UNCHANGED');
$check((int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='consultorios'")->fetchColumn() === 0,
    'QA_CONSULTORIOS_SEPARATE');
