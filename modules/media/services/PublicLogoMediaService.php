<?php
declare(strict_types=1);

namespace Media\Services;

use Media\Contracts\PublicMediaPurpose;
use Media\Contracts\PublicMediaStoragePort;
use Media\Repositories\LogoReferenceRepository;
use Media\Repositories\MediaAssetsRepository;
use PDO;
use RuntimeException;
use Throwable;

require_once __DIR__ . '/../contracts/PublicMediaPurpose.php';
require_once __DIR__ . '/../contracts/PublicMediaStoragePort.php';
require_once __DIR__ . '/../repositories/MediaAssetsRepository.php';
require_once __DIR__ . '/../repositories/LogoReferenceRepository.php';
require_once __DIR__ . '/GdPublicLogoProcessor.php';

final class PublicLogoMediaService
{
    public function __construct(
        private PDO $pdo,
        private PublicMediaStoragePort $storage,
        private GdPublicLogoProcessor $processor,
        private MediaAssetsRepository $assets,
        private LogoReferenceRepository $references
    ) {
    }

    public function replacePhysicianLogo(string $doctorId, array $upload): array
    {
        $doctorId = $this->assertEntityId($doctorId, 'invalid_doctor_id');
        $profile = $this->references->physician($doctorId);
        $displayName = $this->text($profile['display_name'] ?? null) ?? 'médico';
        return $this->replace(
            'PHYSICIAN',
            $doctorId,
            PublicMediaPurpose::PHYSICIAN_PERSONAL_LOGO,
            'Logotipo profesional de ' . $displayName,
            $this->text($profile['logo_url'] ?? null),
            $upload,
            fn(?string $url) => $this->references->updatePhysician($doctorId, $url)
        );
    }

    public function replaceConsultorioLogo(string $doctorId, string $consultorioId, array $upload): array
    {
        $doctorId = $this->assertEntityId($doctorId, 'invalid_doctor_id');
        $consultorioId = $this->assertEntityId($consultorioId, 'invalid_consultorio_id');
        $consultorio = $this->references->consultorio($doctorId, $consultorioId);
        $name = $this->text($consultorio['grupo_nombre'] ?? null)
            ?? $this->text($consultorio['titulo'] ?? null)
            ?? 'Consultorio';
        return $this->replace(
            'CONSULTORIO',
            $doctorId . ':' . $consultorioId,
            PublicMediaPurpose::CONSULTORIO_GROUP_LOGO,
            'Logotipo de ' . $name,
            $this->text($consultorio['logo_url'] ?? null),
            $upload,
            fn(?string $url) => $this->references->updateConsultorio($doctorId, $consultorioId, $url)
        );
    }

    public function removePhysicianLogo(string $doctorId): array
    {
        $doctorId = $this->assertEntityId($doctorId, 'invalid_doctor_id');
        $profile = $this->references->physician($doctorId);
        return $this->remove(
            $this->text($profile['logo_url'] ?? null),
            fn(?string $url) => $this->references->updatePhysician($doctorId, $url)
        );
    }

    public function removeConsultorioLogo(string $doctorId, string $consultorioId): array
    {
        $doctorId = $this->assertEntityId($doctorId, 'invalid_doctor_id');
        $consultorioId = $this->assertEntityId($consultorioId, 'invalid_consultorio_id');
        $consultorio = $this->references->consultorio($doctorId, $consultorioId);
        return $this->remove(
            $this->text($consultorio['logo_url'] ?? null),
            fn(?string $url) => $this->references->updateConsultorio($doctorId, $consultorioId, $url)
        );
    }

    private function replace(
        string $ownerType,
        string $ownerId,
        string $purpose,
        string $altText,
        ?string $previousUrl,
        array $upload,
        callable $updateReference
    ): array {
        PublicMediaPurpose::assertAllowed($purpose);
        $sourcePath = trim((string)($upload['tmp_name'] ?? ''));
        $derivativePath = null;
        $storedNewObject = false;
        $storageKey = null;

        try {
            $derivative = $this->processor->process($upload, false);
            $derivativePath = (string)$derivative['path'];
            $duplicate = $this->assets->findReadyDuplicate(
                $ownerType,
                $ownerId,
                $purpose,
                (string)$derivative['checksum_sha256']
            );
            if (is_array($duplicate) && $this->storage->exists((string)$duplicate['storage_key'])) {
                $this->assertStoredIntegrity($duplicate);
                $asset = $duplicate;
            } else {
                $mediaId = $this->uuidV4();
                $purposePath = $purpose === PublicMediaPurpose::PHYSICIAN_PERSONAL_LOGO
                    ? 'physician-personal-logo'
                    : 'consultorio-group-logo';
                $storageKey = sprintf(
                    'public/%s/%s/%s.webp',
                    $purposePath,
                    hash('sha256', $ownerType . ':' . $ownerId),
                    $mediaId
                );
                $publicUrl = '/api/media/index.php/public/' . rawurlencode($mediaId);
                $this->storage->storeImmutable($storageKey, $derivativePath);
                $storedNewObject = true;
                $asset = array_merge($derivative, [
                    'media_id' => $mediaId,
                    'owner_type' => $ownerType,
                    'owner_id' => $ownerId,
                    'purpose' => $purpose,
                    'storage_key' => $storageKey,
                    'public_url' => $publicUrl,
                    'alt_text' => mb_substr($altText, 0, 255, 'UTF-8'),
                ]);
                try {
                    $this->assertStoredIntegrity($asset);
                } catch (Throwable $e) {
                    $this->storage->delete($storageKey);
                    $storedNewObject = false;
                    throw $e;
                }
            }

            $this->pdo->beginTransaction();
            try {
                if ($storedNewObject) {
                    $this->assets->insertReady($asset);
                }
                $updateReference((string)$asset['public_url']);
                $this->pdo->commit();
            } catch (Throwable $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                if ($storedNewObject && is_string($storageKey)) {
                    $this->storage->delete($storageKey);
                }
                throw $e;
            }

            if ($previousUrl !== null && $previousUrl !== (string)$asset['public_url']) {
                $this->retireIfUnreferenced($previousUrl);
            }

            return $this->publicAssetResult($asset);
        } finally {
            if (is_string($derivativePath) && is_file($derivativePath)) {
                unlink($derivativePath);
            }
            if ($sourcePath !== '' && is_file($sourcePath)) {
                unlink($sourcePath);
            }
        }
    }

    private function remove(?string $previousUrl, callable $updateReference): array
    {
        $this->pdo->beginTransaction();
        try {
            $updateReference(null);
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
        if ($previousUrl !== null) {
            $this->retireIfUnreferenced($previousUrl);
        }
        return ['removed' => true, 'public_url' => null];
    }

    private function retireIfUnreferenced(string $publicUrl): void
    {
        $asset = $this->assets->findByPublicUrl($publicUrl);
        if (!is_array($asset) || $this->references->countReferences($publicUrl) !== 0) {
            return;
        }
        $this->assets->markDeleted((string)$asset['media_id']);
        $this->storage->delete((string)$asset['storage_key']);
    }

    private function publicAssetResult(array $asset): array
    {
        return [
            'media_id' => (string)$asset['media_id'],
            'public_url' => (string)$asset['public_url'],
            'mime_type' => (string)$asset['mime_type'],
            'width' => (int)$asset['width'],
            'height' => (int)$asset['height'],
            'byte_size' => (int)$asset['byte_size'],
            'checksum_sha256' => (string)$asset['checksum_sha256'],
            'alt_text' => (string)$asset['alt_text'],
            'variant_count' => 1,
        ];
    }

    private function assertStoredIntegrity(array $asset): void
    {
        $stored = $this->storage->openReadStream((string)$asset['storage_key']);
        try {
            $context = hash_init('sha256');
            hash_update_stream($context, $stored['stream']);
            $checksum = hash_final($context);
        } finally {
            if (is_resource($stored['stream'])) {
                fclose($stored['stream']);
            }
        }
        if ((int)$stored['bytes'] !== (int)$asset['byte_size']
            || !hash_equals((string)$asset['checksum_sha256'], $checksum)) {
            throw new RuntimeException('public_media_integrity_check_failed');
        }
    }

    private function uuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4)
            . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
    }

    private function assertEntityId(string $value, string $error): string
    {
        $value = trim($value);
        if ($value === '' || strlen($value) > 64 || preg_match('/^[A-Za-z0-9._:-]+$/', $value) !== 1) {
            throw new RuntimeException($error);
        }
        return $value;
    }

    private function text($value): ?string
    {
        $text = trim((string)($value ?? ''));
        return $text === '' ? null : $text;
    }
}
