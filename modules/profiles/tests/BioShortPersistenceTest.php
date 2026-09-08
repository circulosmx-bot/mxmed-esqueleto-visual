<?php
declare(strict_types=1);

use Profiles\Controllers\PrivateProfileController;
use Profiles\Repositories\PrivateProfileRepository;

require_once __DIR__ . '/../../../api/_lib/db.php';
require_once __DIR__ . '/../controllers/PrivateProfileController.php';

function bioAssert(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
$pdo = mxmed_pdo();
$controller = new PrivateProfileController(new PrivateProfileRepository($pdo));
$pdo->beginTransaction();
try {
    foreach ([str_repeat('á', 90), str_repeat('😀', 90), '  Médico especialista.  ', '', null] as $input) {
        $result = $controller->patchByDoctorId('1', ['bio_short' => $input], 'test');
        bioAssert($result['ok'] === true, 'Valid bio saves');
        $read = $controller->showByDoctorId('1', 'test')['data']['identity_public'];
        bioAssert($read['bio_short'] === (trim((string)$input) ?: null), 'Exact Unicode save/reload without truncation');
    }
    $before = $controller->showByDoctorId('1', 'test')['data'];
    foreach ([str_repeat('ñ', 91), str_repeat('😀', 91), ['invalid']] as $input) {
        $result = $controller->patchByDoctorId('1', ['bio_short' => $input, 'professional_designation' => 'Must not save'], 'test');
        bioAssert($result['ok'] === false && $result['error'] === 'validation_error', 'Structured rejection');
        bioAssert($controller->showByDoctorId('1', 'test')['data'] === $before, 'Entire invalid mutation rejected');
    }
} finally {
    $pdo->rollBack();
}
echo "BioShortPersistenceTest PASS (90 Unicode/save/reload/null/91 rejection/atomicity; writes rolled back)\n";
