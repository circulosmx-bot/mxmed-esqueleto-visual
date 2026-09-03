<?php
declare(strict_types=1);

require_once __DIR__ . '/../repositories/PublicDiscoveryRepository.php';
require_once __DIR__ . '/../controllers/PublicDiscoveryController.php';

use Profiles\Controllers\PublicDiscoveryController;
use Profiles\Repositories\PublicDiscoveryRepository;

function pdb02Assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE profiles_doctors (
    doctor_id TEXT PRIMARY KEY,
    display_name TEXT,
    prefix TEXT,
    professional_license TEXT,
    specialty_primary TEXT,
    photo_url TEXT,
    avatar_url TEXT,
    logo_url TEXT,
    plan_code TEXT,
    profile_status TEXT,
    is_public_candidate INTEGER,
    account_id TEXT,
    password_hash TEXT,
    identity_email TEXT,
    security_state TEXT
)');
$pdo->exec('CREATE TABLE consultorios (
    doctor_id TEXT,
    consultorio_id TEXT,
    titulo TEXT,
    calle TEXT,
    num_ext TEXT,
    num_int TEXT,
    colonia TEXT,
    cp TEXT,
    municipio TEXT,
    estado TEXT,
    telefonos_json TEXT,
    whatsapp TEXT,
    private_contact TEXT,
    PRIMARY KEY (doctor_id, consultorio_id)
)');

$insertProfile = $pdo->prepare('INSERT INTO profiles_doctors VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
$profiles = [
    ['doc-01', 'Ana López', 'Dra.', 'PRO-1', 'Cardiología', '/ana.jpg', null, '/ana-logo.jpg', 'free', 'active', 1, 'acct-1', 'hash-1', 'ana@private.test', 'active'],
    ['doc-02', 'Bruno Díaz', 'Dr.', 'PRO-2', 'Pediatría', '/bruno.jpg', null, null, 'standard', 'active', 1, 'acct-2', 'hash-2', 'bruno@private.test', 'active'],
    ['doc-03', 'Inactivo Uno', 'Dr.', 'PRO-3', 'Cardiología', '/inactive.jpg', null, null, 'professional', 'hidden', 1, 'acct-3', 'hash-3', 'hidden@private.test', 'active'],
    ['doc-04', 'No Público', 'Dra.', 'PRO-4', 'Cardiología', '/private.jpg', null, null, 'professional', 'active', 0, 'acct-4', 'hash-4', 'private@private.test', 'active'],
    ['doc-05', 'Carla Ruiz', 'Dra.', 'PRO-5', 'Cardiología', '/carla.jpg', null, null, 'basic', 'active', 1, 'acct-5', 'hash-5', 'carla@private.test', 'active'],
    ['doc-06', 'Sin Cédula', 'Dr.', '', 'Cardiología', '/missing.jpg', null, null, 'standard', 'active', 1, 'acct-6', 'hash-6', 'missing@private.test', 'active'],
    ['doc-07', 'Ana López', 'Dr.', 'PRO-7', 'Cardiología', '/ana-2.jpg', null, null, 'professional', 'active', 1, 'acct-7', 'hash-7', 'ana2@private.test', 'active'],
];
foreach ($profiles as $profile) {
    $insertProfile->execute($profile);
}

$insertLocation = $pdo->prepare('INSERT INTO consultorios VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
$locations = [
    ['doc-01', '2', 'Consultorio Norte', 'Av. Salud', '10', null, 'Centro', '44100', 'Guadalajara', 'Jalisco', '["private"]', 'private', 'never-public'],
    ['doc-01', '1', 'Consultorio Centro', 'Calle Uno', '1', null, 'Americana', '44160', 'Guadalajara', 'Jalisco', '["private"]', 'private', 'never-public'],
    ['doc-02', '1', 'Consultorio Puebla', 'Calle Dos', '2', null, 'Centro', '72000', 'Puebla', 'Puebla', '["private"]', 'private', 'never-public'],
    ['doc-03', '1', 'Oculto', 'Calle Tres', '3', null, 'Centro', '44100', 'Guadalajara', 'Jalisco', null, null, 'never-public'],
    ['doc-04', '1', 'Privado', 'Calle Cuatro', '4', null, 'Centro', '44100', 'Guadalajara', 'Jalisco', null, null, 'never-public'],
    ['doc-05', '1', 'Consultorio Zapopan', 'Calle Cinco', '5', null, 'Centro', '45000', 'Zapopan', 'Jalisco', null, null, 'never-public'],
    ['doc-06', '1', 'Incompleto', 'Calle Seis', '6', null, 'Centro', '44100', 'Guadalajara', 'Jalisco', null, null, 'never-public'],
    ['doc-07', '1', 'Consultorio Sur', 'Calle Siete', '7', null, 'Centro', '44100', 'Guadalajara', 'Jalisco', null, null, 'never-public'],
];
foreach ($locations as $location) {
    $insertLocation->execute($location);
}

$controller = new PublicDiscoveryController(new PublicDiscoveryRepository($pdo));
$all = $controller->index([]);
pdb02Assert($all['ok'] === true, 'unfiltered listing succeeds');
$items = $all['data']['items'];
pdb02Assert(array_column($items, 'doctor_id') === ['doc-01', 'doc-07', 'doc-02', 'doc-05'], 'deterministic name and doctor-id ordering');
pdb02Assert(!in_array('doc-03', array_column($items, 'doctor_id'), true), 'inactive doctor excluded');
pdb02Assert(!in_array('doc-04', array_column($items, 'doctor_id'), true), 'non-public candidate excluded');
pdb02Assert(!in_array('doc-06', array_column($items, 'doctor_id'), true), 'minimum profile readiness enforced');

$state = $controller->index(['state' => 'jalisco']);
pdb02Assert(array_column($state['data']['items'], 'doctor_id') === ['doc-01', 'doc-07', 'doc-05'], 'state filter exact case-insensitive');
$city = $controller->index(['city' => 'Guadalajara']);
pdb02Assert(array_column($city['data']['items'], 'doctor_id') === ['doc-01', 'doc-07'], 'city filter');
$specialty = $controller->index(['specialty' => 'cardiología']);
pdb02Assert(array_column($specialty['data']['items'], 'doctor_id') === ['doc-01', 'doc-07', 'doc-05'], 'primary specialty filter');
$combined = $controller->index(['state' => 'Jalisco', 'city' => 'Zapopan', 'specialty' => 'Cardiología']);
pdb02Assert(array_column($combined['data']['items'], 'doctor_id') === ['doc-05'], 'combined filters');
$empty = $controller->index(['state' => 'Sonora']);
pdb02Assert($empty['data']['items'] === [] && $empty['meta']['pagination']['total_count'] === 0, 'empty results contract');

$firstPage = $controller->index(['page' => '1', 'page_size' => '2']);
$secondPage = $controller->index(['page' => '2', 'page_size' => '2']);
pdb02Assert(count($firstPage['data']['items']) === 2 && count($secondPage['data']['items']) === 2, 'bounded pagination pages');
pdb02Assert($firstPage['meta']['pagination']['has_next'] === true, 'pagination next indicator');
$bounded = $controller->index(['page_size' => '500']);
pdb02Assert($bounded['meta']['pagination']['page_size'] === 50, 'maximum page size enforced');
pdb02Assert(PublicDiscoveryController::DEFAULT_PAGE_SIZE === 20 && PublicDiscoveryController::MAX_PAGE_SIZE === 50, 'pagination constants');

$allowedKeys = ['doctor_id', 'display_name', 'prefix', 'primary_specialty', 'photo_url', 'logo_url', 'location', 'has_public_agenda', 'profile_url'];
foreach ($items as $item) {
    pdb02Assert(array_keys($item) === $allowedKeys, 'public card top-level allowlist exact');
    pdb02Assert(array_keys($item['location']) === ['consultorio_id', 'name', 'address_summary', 'city', 'state'], 'location allowlist exact');
    pdb02Assert($item['profile_url'] === '/profiles/doctor.php?doctor_id=' . rawurlencode($item['doctor_id']), 'real profile URL exact');
    $serialized = json_encode($item, JSON_THROW_ON_ERROR);
    foreach (['account_id', 'password_hash', 'identity_email', 'security_state', 'private_contact', 'telefonos_json', 'whatsapp', 'patient'] as $privateField) {
        pdb02Assert(!str_contains($serialized, $privateField), 'private field absent: ' . $privateField);
    }
}

$byId = [];
foreach ($items as $item) {
    $byId[$item['doctor_id']] = $item;
}
pdb02Assert($byId['doc-01']['photo_url'] === null && $byId['doc-01']['logo_url'] === null, 'free photo/logo entitlement preserved');
pdb02Assert($byId['doc-01']['has_public_agenda'] === false, 'free agenda entitlement preserved');
pdb02Assert($byId['doc-05']['photo_url'] === '/carla.jpg' && $byId['doc-05']['has_public_agenda'] === false, 'basic capability preserved');
pdb02Assert($byId['doc-02']['photo_url'] === '/bruno.jpg' && $byId['doc-02']['has_public_agenda'] === true, 'standard capability preserved');

$repositorySource = file_get_contents(__DIR__ . '/../repositories/PublicDiscoveryRepository.php');
pdb02Assert(is_string($repositorySource) && !preg_match('/SELECT\s+\*/i', $repositorySource), 'repository has no SELECT star');

echo "PublicDiscoveryTest PASS\n";
