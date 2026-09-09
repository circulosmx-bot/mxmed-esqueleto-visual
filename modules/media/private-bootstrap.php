<?php
declare(strict_types=1);
require_once __DIR__.'/bootstrap.php';
require_once __DIR__.'/storage/LocalPersistentPrivateMediaStorage.php';
require_once __DIR__.'/services/ProfilePhotoReviewCandidateService.php';

function mxmed_private_media_storage(): \Media\Storage\LocalPersistentPrivateMediaStorage
{
    $root = trim((string)(getenv('MXMED_PRIVATE_MEDIA_ROOT') ?: ''));
    if ($root === '') {
        $home = trim((string)(getenv('HOME') ?: ''));
        if ($home === '') throw new RuntimeException('MXMED_PRIVATE_MEDIA_ROOT_required');
        $root = $home.'/.local/share/mxmed/private-media';
    }
    return new \Media\Storage\LocalPersistentPrivateMediaStorage($root, [mxmed_public_media_root()]);
}
