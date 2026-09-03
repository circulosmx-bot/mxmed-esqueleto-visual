<?php
declare(strict_types=1);

use Media\Repositories\LogoReferenceRepository;
use Media\Repositories\MediaAssetsRepository;
use Media\Services\GdPublicLogoProcessor;
use Media\Services\PublicLogoMediaService;
use Media\Storage\LocalPersistentPublicMediaStorage;

require_once __DIR__ . '/storage/LocalPersistentPublicMediaStorage.php';
require_once __DIR__ . '/services/GdPublicLogoProcessor.php';
require_once __DIR__ . '/repositories/MediaAssetsRepository.php';
require_once __DIR__ . '/repositories/LogoReferenceRepository.php';
require_once __DIR__ . '/services/PublicLogoMediaService.php';

function mxmed_public_media_root(): string
{
    $configured = trim((string)(getenv('MXMED_PUBLIC_MEDIA_ROOT') ?: ''));
    if ($configured !== '') {
        return $configured;
    }
    $userHome = trim((string)(getenv('HOME') ?: ''));
    if ($userHome === '') {
        throw new RuntimeException('MXMED_PUBLIC_MEDIA_ROOT_required');
    }
    return $userHome . '/.local/share/mxmed/public-media';
}

function mxmed_public_media_storage(): LocalPersistentPublicMediaStorage
{
    return new LocalPersistentPublicMediaStorage(mxmed_public_media_root());
}

function mxmed_public_logo_media_service(PDO $pdo): PublicLogoMediaService
{
    return new PublicLogoMediaService(
        $pdo,
        mxmed_public_media_storage(),
        new GdPublicLogoProcessor(),
        new MediaAssetsRepository($pdo),
        new LogoReferenceRepository($pdo)
    );
}
