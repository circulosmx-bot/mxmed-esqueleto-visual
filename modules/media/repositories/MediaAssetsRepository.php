<?php
declare(strict_types=1);

namespace Media\Repositories;

use PDO;

final class MediaAssetsRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function insertReady(array $asset): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO media_assets (
                media_id, owner_type, owner_id, purpose, classification,
                storage_key, public_url, mime_type, format, width, height,
                byte_size, checksum_sha256, alt_text, display_order, status, created_at, updated_at
             ) VALUES (
                :media_id, :owner_type, :owner_id, :purpose, :classification,
                :storage_key, :public_url, :mime_type, :format, :width, :height,
                :byte_size, :checksum_sha256, :alt_text, :display_order, :status, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
             )'
        );
        $displayOrder = null;
        if ($asset['owner_type'] === 'PHYSICIAN' && $asset['purpose'] === 'DOCTOR_GALLERY') {
            $displayOrder = $asset['display_order'] ?? $this->nextDoctorGalleryDisplayOrder((string)$asset['owner_id']);
        }
        $stmt->execute([
            'media_id' => $asset['media_id'],
            'owner_type' => $asset['owner_type'],
            'owner_id' => $asset['owner_id'],
            'purpose' => $asset['purpose'],
            'classification' => 'PUBLIC',
            'storage_key' => $asset['storage_key'],
            'public_url' => $asset['public_url'],
            'mime_type' => $asset['mime_type'],
            'format' => $asset['format'],
            'width' => $asset['width'],
            'height' => $asset['height'],
            'byte_size' => $asset['byte_size'],
            'checksum_sha256' => $asset['checksum_sha256'],
            'alt_text' => $asset['alt_text'],
            'display_order' => $displayOrder,
            'status' => 'READY',
        ]);
    }

    public function listDoctorGallery(string $doctorId): array
    {
        $stmt = $this->pdo->prepare("SELECT media_id, public_url, alt_text, width, height, display_order FROM media_assets WHERE owner_type='PHYSICIAN' AND owner_id=? AND purpose='DOCTOR_GALLERY' AND classification='PUBLIC' AND status='READY' ORDER BY display_order IS NULL, display_order, created_at, media_id");
        $stmt->execute([$doctorId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function lockDoctorGallery(string $doctorId): array
    {
        $stmt = $this->pdo->prepare("SELECT media_id, display_order FROM media_assets WHERE owner_type='PHYSICIAN' AND owner_id=? AND purpose='DOCTOR_GALLERY' AND classification='PUBLIC' AND status='READY' ORDER BY display_order IS NULL, display_order, created_at, media_id FOR UPDATE");
        $stmt->execute([$doctorId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function replaceDoctorGalleryOrder(string $doctorId, array $mediaIds): void
    {
        $stmt = $this->pdo->prepare("UPDATE media_assets SET display_order=? WHERE media_id=? AND owner_type='PHYSICIAN' AND owner_id=? AND purpose='DOCTOR_GALLERY' AND classification='PUBLIC' AND status='READY'");
        foreach (array_values($mediaIds) as $index => $mediaId) {
            $stmt->execute([$index + 1, $mediaId, $doctorId]);
        }
    }

    private function nextDoctorGalleryDisplayOrder(string $doctorId): int
    {
        $stmt = $this->pdo->prepare("SELECT COALESCE(MAX(display_order),0)+1 FROM media_assets WHERE owner_type='PHYSICIAN' AND owner_id=? AND purpose='DOCTOR_GALLERY' AND classification='PUBLIC' AND status='READY'");
        $stmt->execute([$doctorId]);
        return (int)$stmt->fetchColumn();
    }

    public function findReadyDuplicate(string $ownerType, string $ownerId, string $purpose, string $checksum): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM media_assets
              WHERE owner_type = :owner_type
                AND owner_id = :owner_id
                AND purpose = :purpose
                AND checksum_sha256 = :checksum
                AND status = \'READY\'
              ORDER BY created_at DESC
              LIMIT 1'
        );
        $stmt->execute([
            'owner_type' => $ownerType,
            'owner_id' => $ownerId,
            'purpose' => $purpose,
            'checksum' => $checksum,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function findPublicReady(string $mediaId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT media_id, storage_key, public_url, mime_type, byte_size, checksum_sha256, width, height, alt_text
               FROM media_assets
              WHERE media_id = :media_id AND classification = \'PUBLIC\' AND status = \'READY\'
              LIMIT 1'
        );
        $stmt->execute(['media_id' => $mediaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function findByPublicUrl(string $publicUrl): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM media_assets WHERE public_url = :public_url LIMIT 1');
        $stmt->execute(['public_url' => $publicUrl]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function markDeleted(string $mediaId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE media_assets SET status = \'DELETED\', deleted_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE media_id = :media_id AND status <> \'DELETED\''
        );
        $stmt->execute(['media_id' => $mediaId]);
    }
}
