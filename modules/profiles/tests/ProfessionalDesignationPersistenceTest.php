<?php
declare(strict_types=1);

use Profiles\Controllers\PrivateProfileController;
use Profiles\Controllers\PublicProfileController;
use Profiles\Repositories\PrivateProfileRepository;
use Profiles\Repositories\PublicProfileRepository;

require_once __DIR__ . '/../../../api/_lib/db.php';
require_once __DIR__ . '/../controllers/PrivateProfileController.php';
require_once __DIR__ . '/../controllers/PublicProfileController.php';

function designationAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

// Local integration test: all test writes (including timestamps) are rolled back.
$pdo = mxmed_pdo();
$private = new PrivateProfileController(new PrivateProfileRepository($pdo));
$public = new PublicProfileController(new PublicProfileRepository($pdo));
$before = $public->showByDoctorId('1');
designationAssert(($before['ok'] ?? false) === true, 'reviewed local profile exists');
$pdo->beginTransaction();
try {
    foreach ([
        ['  Endocrinóloga  ', 'Endocrinóloga'],
        ['Cirujana dentista', 'Cirujana dentista'],
        ['<b>Médico internista</b>', 'Médico internista'],
        [str_repeat('á', 121), str_repeat('á', 120)],
        ['', null],
        [null, null],
    ] as [$input, $expected]) {
        $saved = $private->patchByDoctorId('1', ['professional_designation' => $input], 'test');
        designationAssert(($saved['ok'] ?? false) === true, 'save succeeds');
        $read = $private->showByDoctorId('1', 'test')['data']['identity_public'];
        designationAssert(array_key_exists('professional_designation', $read), 'private contract includes field');
        designationAssert($read['professional_designation'] === $expected, 'exact trimmed bounded plain-text readback');
        $dto = $public->showByDoctorId('1');
        designationAssert(array_key_exists('professional_designation', $dto['data']['identity']), 'public contract includes field');
        designationAssert($dto['data']['identity']['professional_designation'] === $expected, 'public value preserves case/accents/null without derivation');
        designationAssert($dto['data']['specialties'] === $before['data']['specialties'], 'canonical specialties unchanged');
    }
} finally {
    $pdo->rollBack();
}
echo "ProfessionalDesignationPersistenceTest PASS (save/edit/reload/trim/accents/plain-text/120/null/public DTO/specialty preservation; rolled back)\n";
