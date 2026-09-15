<?php
declare(strict_types=1);

require __DIR__.'/GalleryReviewFixture.php';

use Media\Repositories\MediaAssetsRepository;
use Media\Services\DoctorGalleryService;

function galleryOrderCheck(bool $condition, string $name): void
{
    if (!$condition) throw new RuntimeException('FAIL '.$name);
    echo "PASS $name\n";
}

function galleryOrderRejects(callable $action, string $error, string $name): void
{
    try {
        $action();
        throw new RuntimeException('FAIL '.$name);
    } catch (RuntimeException $exception) {
        galleryOrderCheck($exception->getMessage() === $error, $name);
    }
}

function galleryOrderFailsWith(callable $action, string $errorFragment, string $name): void
{
    try {
        $action();
        throw new RuntimeException('FAIL '.$name);
    } catch (Throwable $exception) {
        galleryOrderCheck(str_contains($exception->getMessage(), $errorFragment), $name);
    }
}

$pdo = mr5Pdo();
[, $storage] = mr5Storage();
$service = new DoctorGalleryService($pdo, $storage);
$repository = new MediaAssetsRepository($pdo);

$doctor = mr10Doctor();
mr10SeedPublic($doctor, 4);
$initial = $service->list($doctor);
$initialIds = array_column($initial, 'media_id');
galleryOrderCheck(array_column($initial, 'display_order') === [1, 2, 3, 4], 'new gallery assets append in canonical order');

$reversedIds = array_reverse($initialIds);
$reordered = $service->reorder($doctor, $reversedIds);
galleryOrderCheck(array_column($reordered, 'media_id') === $reversedIds, 'complete order persists');
galleryOrderCheck(array_column($reordered, 'display_order') === [1, 2, 3, 4], 'saved order normalized to consecutive positions');

$otherDoctor = mr10Doctor();
mr10SeedPublic($otherDoctor, 2);
$otherBefore = $service->list($otherDoctor);
$foreignOrder = $reversedIds;
$foreignOrder[0] = $otherBefore[0]['media_id'];
galleryOrderRejects(fn() => $service->reorder($doctor, $foreignOrder), 'gallery_order_conflict', 'cross physician media denied');
galleryOrderCheck(array_column($service->list($doctor), 'media_id') === $reversedIds, 'cross physician rejection changes nothing');
galleryOrderCheck($service->list($otherDoctor) === $otherBefore, 'foreign physician gallery unchanged');

$duplicates = $reversedIds;
$duplicates[1] = $duplicates[0];
galleryOrderRejects(fn() => $service->reorder($doctor, $duplicates), 'gallery_invalid_order', 'duplicate media ids rejected');
galleryOrderRejects(fn() => $service->reorder($doctor, array_slice($reversedIds, 0, 3)), 'gallery_order_conflict', 'partial order rejected');
galleryOrderRejects(fn() => $service->reorder($doctor, [...$reversedIds, '00000000-0000-4000-8000-000000000000']), 'gallery_order_conflict', 'unknown media id rejected');

$candidate = mr10Candidate($doctor);
galleryOrderCheck($candidate['doctor'] === $doctor, 'pending review candidate created');
galleryOrderCheck(array_column($service->reorder($doctor, $initialIds), 'media_id') === $initialIds, 'pending candidates excluded from public order authority');

mr10SeedPublic($doctor, 1);
$withAppend = $service->list($doctor);
galleryOrderCheck(array_slice(array_column($withAppend, 'media_id'), 0, 4) === $initialIds, 'saved public order retained after approval append');
galleryOrderCheck(array_column($withAppend, 'display_order') === [1, 2, 3, 4, 5], 'newly public asset appended after saved order');

$beforeFailure = $service->list($doctor);
$pdo->exec("CREATE TRIGGER gal01_order_failure BEFORE UPDATE ON media_assets FOR EACH ROW BEGIN IF NEW.owner_id='".$doctor."' AND NEW.display_order=3 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic_order_failure'; END IF; END");
try {
    galleryOrderFailsWith(fn() => $service->reorder($doctor, array_reverse(array_column($beforeFailure, 'media_id'))), 'synthetic_order_failure', 'write failure surfaced');
} finally {
    $pdo->exec('DROP TRIGGER gal01_order_failure');
}
galleryOrderCheck($service->list($doctor) === $beforeFailure, 'failed reorder rolls back every position');

$emptyDoctor = mr10Doctor();
galleryOrderCheck($service->reorder($emptyDoctor, []) === [], 'empty public gallery accepts empty canonical order');

echo "GAL01_GALLERY_ORDER_SERVICE=PASS\n";
