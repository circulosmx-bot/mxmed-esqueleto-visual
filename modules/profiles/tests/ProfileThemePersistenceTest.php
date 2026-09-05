<?php
declare(strict_types=1);

use Profiles\Controllers\PrivateProfileController;
use Profiles\Repositories\PrivateProfileRepository;

require_once __DIR__ . '/../../../api/_lib/db.php';
require_once __DIR__ . '/../repositories/PrivateProfileRepository.php';
require_once __DIR__ . '/../controllers/PrivateProfileController.php';

function theme01aPersistenceAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$pdo = mxmed_pdo();
$repository = new PrivateProfileRepository($pdo);
$controller = new PrivateProfileController($repository);
$doctorId = '1';
$before = $repository->fetchIdentity($doctorId);
theme01aPersistenceAssert(is_array($before), 'fixture doctor exists');
$original = $before['profile_theme_key'] ?? null;

try {
    $saved = $controller->patchByDoctorId($doctorId, ['profile_theme_key' => 'royal_blue'], 'test');
    theme01aPersistenceAssert(($saved['ok'] ?? false) === true, 'approved theme save succeeds');
    theme01aPersistenceAssert(($repository->fetchIdentity($doctorId)['profile_theme_key'] ?? null) === 'royal_blue', 'approved theme reload succeeds');

    $rejected = $controller->patchByDoctorId($doctorId, ['profile_theme_key' => '#ff00ff'], 'test');
    theme01aPersistenceAssert(($rejected['ok'] ?? true) === false, 'arbitrary value is rejected');
    theme01aPersistenceAssert(($repository->fetchIdentity($doctorId)['profile_theme_key'] ?? null) === 'royal_blue', 'rejected value does not mutate storage');

    $reset = $controller->patchByDoctorId($doctorId, ['profile_theme_key' => null], 'test');
    theme01aPersistenceAssert(($reset['ok'] ?? false) === true, 'reset succeeds');
    $resetReadback = $repository->fetchIdentity($doctorId);
    theme01aPersistenceAssert(array_key_exists('profile_theme_key', $resetReadback) && $resetReadback['profile_theme_key'] === null, 'reset reloads as null');
} finally {
    $repository->upsertIdentity($doctorId, ['profile_theme_key' => $original]);
}

echo "ProfileThemePersistenceTest PASS\n";
