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
    foreach ([str_repeat('á', 150), str_repeat('😀', 150), '  Médico especialista.  ', '', null] as $input) {
        $result = $controller->patchByDoctorId('1', ['bio_short' => $input], 'test');
        bioAssert($result['ok'] === true, 'Valid bio saves');
        $read = $controller->showByDoctorId('1', 'test')['data']['identity_public'];
        bioAssert($read['bio_short'] === (trim((string)$input) ?: null), 'Exact Unicode save/reload without truncation');
    }
    $before = $controller->showByDoctorId('1', 'test')['data'];
    foreach ([str_repeat('ñ', 151), str_repeat('😀', 151), ['invalid']] as $input) {
        $result = $controller->patchByDoctorId('1', ['bio_short' => $input, 'professional_designation' => 'Must not save'], 'test');
        bioAssert($result['ok'] === false && $result['error'] === 'validation_error', 'Structured rejection');
        bioAssert($result['meta']['max_characters'] === 150 && $result['meta']['field'] === 'bio_short', 'Canonical server limit reported');
        bioAssert($controller->showByDoctorId('1', 'test')['data'] === $before, 'Entire invalid mutation rejected');
    }
    // Legacy oversized data is readable without rewriting it on hydration.
    $legacy = str_repeat('á', 160);
    $stmt = $pdo->prepare('UPDATE profiles_doctors SET bio_short=? WHERE doctor_id=?');
    $stmt->execute([$legacy, '1']);
    $row = $controller->showByDoctorId('1', 'test')['data']['identity_public'];
    bioAssert($row['bio_short'] === $legacy, 'Legacy oversized Bio stays intact on read');
    $payload = [
        'display_name' => $row['display_name'],
        'professional_designation' => 'Endocrinóloga',
        'prefix' => 'Dra.',
        'gender' => 'femenino',
        'gender_label' => 'Femenino',
        'bio_short' => str_repeat('ñ', 150),
        'profile_theme_key' => 'mxmed_teal',
        'profile_status' => 'hidden',
        'is_public_candidate' => false,
    ];
    $saved = $controller->patchByDoctorId('1', $payload, 'test');
    bioAssert($saved['ok'] === true, 'Grouped identity PATCH accepted');
    bioAssert(count($saved['meta']['editable_fields_applied']) === 7, 'Seven grouped editable fields preserved');
    bioAssert(count($saved['meta']['blocked_fields_ignored']) === 2, 'System authority remains blocked');
    bioAssert($saved['data']['identity_public']['profile_status'] === $row['profile_status'], 'Publication state preserved');
    bioAssert($saved['data']['identity_public']['is_public_candidate'] === $row['is_public_candidate'], 'Candidate state preserved');
} finally {
    $pdo->rollBack();
}
echo "BioShortPersistenceTest PASS (150 Unicode/save/reload/null/151 rejection/grouped PATCH/blocked fields/atomicity; writes rolled back)\n";
