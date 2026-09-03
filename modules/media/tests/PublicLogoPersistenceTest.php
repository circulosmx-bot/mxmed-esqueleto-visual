<?php
declare(strict_types=1);

use Media\Repositories\LogoReferenceRepository;
use Media\Repositories\MediaAssetsRepository;
use Media\Services\GdPublicLogoProcessor;
use Media\Services\PublicLogoMediaService;
use Media\Storage\LocalPersistentPublicMediaStorage;
use Profiles\Controllers\PublicProfileController;
use Profiles\Repositories\PublicProfileRepository;

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../profiles/repositories/PublicProfileRepository.php';
require_once __DIR__ . '/../../profiles/controllers/PublicProfileController.php';

function mediaPersistenceAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function mediaPersistenceImage(int $red, int $green, int $blue): string
{
    $path = tempnam(sys_get_temp_dir(), 'media01-persistence-');
    $image = imagecreatetruecolor(360, 180);
    $color = imagecolorallocate($image, $red, $green, $blue);
    imagefilledrectangle($image, 0, 0, 359, 179, $color);
    imagepng($image, $path, 4);
    $image = null;
    return $path;
}

function mediaPersistenceUpload(string $path): array
{
    return ['tmp_name' => $path, 'type' => 'image/png', 'error' => UPLOAD_ERR_OK, 'size' => filesize($path), 'name' => 'synthetic-logo.png'];
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec("CREATE TABLE profiles_doctors (doctor_id TEXT PRIMARY KEY, display_name TEXT, logo_url TEXT, updated_at TEXT)");
$pdo->exec("CREATE TABLE consultorios (doctor_id TEXT, consultorio_id TEXT, titulo TEXT, grupo_nombre TEXT, logo_url TEXT, updated_at TEXT, PRIMARY KEY (doctor_id, consultorio_id))");
$pdo->exec("CREATE TABLE media_assets (
    media_id TEXT PRIMARY KEY, owner_type TEXT, owner_id TEXT, purpose TEXT, classification TEXT,
    storage_key TEXT UNIQUE, public_url TEXT UNIQUE, mime_type TEXT, format TEXT, width INTEGER,
    height INTEGER, byte_size INTEGER, checksum_sha256 TEXT, alt_text TEXT, status TEXT,
    created_at TEXT, updated_at TEXT, deleted_at TEXT
)");
$pdo->exec("INSERT INTO profiles_doctors VALUES ('media-doctor', 'Dra. Imagen Segura', NULL, NULL)");
$pdo->exec("INSERT INTO consultorios VALUES ('media-doctor', '1', 'Consultorio Centro', 'Grupo Prueba', NULL, NULL)");
$pdo->exec("INSERT INTO consultorios VALUES ('media-doctor', '2', 'Consultorio Legado', NULL, 'data:image/png;base64,aGVsbG8=', NULL)");

$root = sys_get_temp_dir() . '/mxmed-media-persistence-' . bin2hex(random_bytes(8));
$storage = new LocalPersistentPublicMediaStorage($root);
$assets = new MediaAssetsRepository($pdo);
$service = new PublicLogoMediaService($pdo, $storage, new GdPublicLogoProcessor(), $assets, new LogoReferenceRepository($pdo));

try {
    $physician = $service->replacePhysicianLogo('media-doctor', mediaPersistenceUpload(mediaPersistenceImage(12, 90, 180)));
    $physicianUrl = (string)$physician['public_url'];
    mediaPersistenceAssert(str_starts_with($physicianUrl, '/api/media/index.php/public/'), 'physician stable application URL persisted');
    mediaPersistenceAssert($pdo->query("SELECT logo_url FROM profiles_doctors WHERE doctor_id='media-doctor'")->fetchColumn() === $physicianUrl, 'physician reference persisted');
    mediaPersistenceAssert(!str_starts_with($physicianUrl, 'data:'), 'physician reference is not a data URL');
    mediaPersistenceAssert(str_contains((string)$physician['alt_text'], 'Dra. Imagen Segura'), 'physician semantic alt metadata');

    $consultorio = $service->replaceConsultorioLogo('media-doctor', '1', mediaPersistenceUpload(mediaPersistenceImage(180, 70, 22)));
    $consultorioUrl = (string)$consultorio['public_url'];
    mediaPersistenceAssert($pdo->query("SELECT logo_url FROM consultorios WHERE consultorio_id='1'")->fetchColumn() === $consultorioUrl, 'consultorio stable reference persisted');
    mediaPersistenceAssert(!str_starts_with($consultorioUrl, 'data:'), 'consultorio new upload is not a data URL/blob');
    mediaPersistenceAssert(str_contains((string)$consultorio['alt_text'], 'Grupo Prueba'), 'consultorio semantic alt metadata');

    $controller = (new ReflectionClass(PublicProfileController::class))->newInstanceWithoutConstructor();
    $sanitizer = new ReflectionMethod(PublicProfileController::class, 'sanitizePublicLogoUrl');
    mediaPersistenceAssert($sanitizer->invoke($controller, $physicianUrl) === $physicianUrl, 'physician public profile accepts stable media URL');
    mediaPersistenceAssert($sanitizer->invoke($controller, $consultorioUrl) === $consultorioUrl, 'consultorio public profile accepts stable media URL');
    $legacy = (string)$pdo->query("SELECT logo_url FROM consultorios WHERE consultorio_id='2'")->fetchColumn();
    mediaPersistenceAssert($sanitizer->invoke($controller, $legacy) === $legacy, 'legacy consultorio data URL remains readable');

    $failedSource = tempnam(sys_get_temp_dir(), 'media01-invalid-');
    file_put_contents($failedSource, 'not-an-image');
    try {
        $service->replacePhysicianLogo('media-doctor', mediaPersistenceUpload($failedSource));
        throw new RuntimeException('invalid image unexpectedly accepted');
    } catch (RuntimeException $e) {
        mediaPersistenceAssert($pdo->query("SELECT logo_url FROM profiles_doctors WHERE doctor_id='media-doctor'")->fetchColumn() === $physicianUrl, 'processing failure leaves entity reference unchanged');
    }

    $replacement = $service->replacePhysicianLogo('media-doctor', mediaPersistenceUpload(mediaPersistenceImage(30, 170, 90)));
    mediaPersistenceAssert((string)$replacement['public_url'] !== $physicianUrl, 'replacement gets an immutable versioned URL');
    mediaPersistenceAssert($assets->findPublicReady((string)$replacement['media_id']) !== null, 'replacement is readable when reference becomes active');
    mediaPersistenceAssert($assets->findByPublicUrl($physicianUrl)['status'] === 'DELETED', 'previous unreferenced asset retired after replacement');

    $service->removePhysicianLogo('media-doctor');
    $service->removeConsultorioLogo('media-doctor', '1');
    mediaPersistenceAssert($pdo->query("SELECT logo_url FROM profiles_doctors WHERE doctor_id='media-doctor'")->fetchColumn() === null, 'physician remove clears reference');
    mediaPersistenceAssert($pdo->query("SELECT logo_url FROM consultorios WHERE consultorio_id='1'")->fetchColumn() === null, 'consultorio remove clears reference');
    mediaPersistenceAssert($pdo->query("SELECT logo_url FROM consultorios WHERE consultorio_id='2'")->fetchColumn() === $legacy, 'legacy row remains untouched');
} finally {
    if (is_dir($root)) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $item) $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        rmdir($root);
    }
}

echo "PublicLogoPersistenceTest PASS\n";
