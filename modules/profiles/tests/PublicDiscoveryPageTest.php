<?php
declare(strict_types=1);

function pdb02PageAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$_GET = ['state' => 'Jalisco', 'city' => 'Guadalajara', 'specialty' => 'Cardiología', 'page' => '1', 'page_size' => '2'];
$publicDiscoveryResponse = [
    'ok' => true,
    'error' => null,
    'message' => '',
    'data' => [
        'entity_type' => 'MEDICO',
        'items' => [[
            'doctor_id' => 'doctor-fixture',
            'display_name' => 'Ana Segura',
            'prefix' => 'Dra.',
            'primary_specialty' => 'Cardiología',
            'photo_url' => null,
            'logo_url' => null,
            'location' => [
                'consultorio_id' => '1',
                'name' => 'Consultorio Centro',
                'address_summary' => 'Calle Uno 1, Guadalajara, Jalisco',
                'city' => 'Guadalajara',
                'state' => 'Jalisco',
            ],
            'has_public_agenda' => true,
            'profile_url' => '/profiles/doctor.php?doctor_id=doctor-fixture',
        ]],
    ],
    'meta' => [
        'filters' => ['state' => 'Jalisco', 'city' => 'Guadalajara', 'specialty' => 'Cardiología'],
        'pagination' => ['page' => 1, 'page_size' => 2, 'result_count' => 1, 'total_count' => 3, 'total_pages' => 2, 'has_previous' => false, 'has_next' => true],
    ],
];

ob_start();
require __DIR__ . '/../../../profiles/listing.php';
$html = (string)ob_get_clean();

pdb02PageAssert(str_contains($html, 'data-doctor-card'), 'fixture doctor card rendered');
pdb02PageAssert(str_contains($html, 'Ana Segura') && str_contains($html, 'Cardiología'), 'fixture public fields rendered');
pdb02PageAssert(str_contains($html, '/profiles/doctor.php?doctor_id=doctor-fixture'), 'profile transition rendered');
pdb02PageAssert(str_contains($html, 'name="state"') && str_contains($html, 'name="city"') && str_contains($html, 'name="specialty"'), 'filter controls represented');
pdb02PageAssert(str_contains($html, 'state=Jalisco') && str_contains($html, 'city=Guadalajara') && str_contains($html, 'specialty=Cardiolog%C3%ADa'), 'pagination preserves filters');
pdb02PageAssert(str_contains($html, 'rel="next"'), 'next page rendered');

echo "PublicDiscoveryPageTest PASS\n";
