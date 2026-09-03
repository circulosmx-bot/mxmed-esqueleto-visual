<?php
declare(strict_types=1);

use Media\Contracts\PublicMediaPurpose;
use Media\Services\GdPublicLogoProcessor;

require_once __DIR__ . '/../contracts/PublicMediaPurpose.php';
require_once __DIR__ . '/../services/GdPublicLogoProcessor.php';

function mediaStaticAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$purposes = PublicMediaPurpose::all();
mediaStaticAssert($purposes === ['PHYSICIAN_PERSONAL_LOGO', 'CONSULTORIO_GROUP_LOGO'], 'purpose allowlist exact and closed');
mediaStaticAssert(count($purposes) === 2, 'purpose allowlist count is two');

$migration = file_get_contents(__DIR__ . '/../db/migrations/2026_09_03_01_create_media_assets.sql');
mediaStaticAssert(str_contains($migration, 'CREATE TABLE IF NOT EXISTS media_assets'), 'generic media metadata migration present');
foreach (['owner_type', 'owner_id', 'purpose', 'classification', 'storage_key', 'public_url', 'mime_type', 'width', 'height', 'byte_size', 'checksum_sha256', 'alt_text', 'status'] as $column) {
    mediaStaticAssert(preg_match('/\\b' . preg_quote($column, '/') . '\\b/', $migration) === 1, 'migration field present: ' . $column);
}
mediaStaticAssert(!preg_match('/\\b(?:BLOB|LONGBLOB|MEDIUMBLOB|TINYBLOB)\\b/i', $migration), 'metadata table has no binary BLOB');
mediaStaticAssert(str_contains($migration, "purpose ENUM('PHYSICIAN_PERSONAL_LOGO','CONSULTORIO_GROUP_LOGO')"), 'migration purpose values closed');
mediaStaticAssert(str_contains($migration, "classification ENUM('PUBLIC')"), 'migration classification closed to PUBLIC');
mediaStaticAssert(str_contains($migration, 'byte_size > 0 AND byte_size <= 153600'), 'byte constraint present');
mediaStaticAssert(str_contains($migration, 'width > 0 AND width <= 800'), 'width constraint present');

$html = file_get_contents(__DIR__ . '/../../../index.html');
$app = file_get_contents(__DIR__ . '/../../../assets/js/app.js');
$consultorio = file_get_contents(__DIR__ . '/../../../assets/js/perfil/consultorio/multisede.js');
$profilePage = file_get_contents(__DIR__ . '/../../../profiles/doctor.php');
$delivery = file_get_contents(__DIR__ . '/../../../api/media/index.php');
mediaStaticAssert(str_contains($html, 'id="mx-dg-logo"') && str_contains($html, 'accept="image/jpeg,image/png,image/webp"'), 'physician file input enabled with raster allowlist');
mediaStaticAssert(str_contains($app, 'new FormData()') && str_contains($app, '/logo`'), 'physician admin uses multipart logo endpoint');
mediaStaticAssert(str_contains($consultorio, '/api/profiles/index.php/private/doctor/'), 'consultorio admin uses private media endpoint');
mediaStaticAssert(str_contains($consultorio, "form.append('logo', file"), 'consultorio admin sends binary multipart upload');
mediaStaticAssert(!preg_match('/inputId\.startsWith\(\'cons-logo\'\)[\\s\\S]{0,500}readAsDataURL/', $consultorio), 'new consultorio logo path does not encode data URL');
mediaStaticAssert(str_contains($delivery, "preg_match('/^[0-9a-f]{8}-"), 'public delivery accepts media UUID only');
mediaStaticAssert(!str_contains($delivery, '$_GET[\'path\']') && !str_contains($delivery, '$_GET["path"]'), 'public delivery has no arbitrary path input');
mediaStaticAssert(str_contains($delivery, 'Cache-Control: public, max-age=31536000, immutable'), 'immutable public cache policy present');
mediaStaticAssert(str_contains($delivery, 'X-Content-Type-Options: nosniff'), 'public delivery nosniff present');
mediaStaticAssert(str_contains($profilePage, 'syncBranding(panels.find(function (panel)'), 'active consultorio branding synchronization preserved');
mediaStaticAssert(str_contains($profilePage, "brandLogo.removeAttribute('src');"), 'missing consultorio logo fallback preserved');
mediaStaticAssert(GdPublicLogoProcessor::MAX_UPLOAD_BYTES === 2097152, 'explicit upload byte limit');
mediaStaticAssert(GdPublicLogoProcessor::MAX_PIXEL_COUNT === 4000000, 'explicit pixel limit');

echo "MediaFoundationStaticTest PASS\n";
