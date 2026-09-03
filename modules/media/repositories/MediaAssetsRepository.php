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
                byte_size, checksum_sha256, alt_text, status, created_at, updated_at
             ) VALUES (
                :media_id, :owner_type, :owner_id, :purpose, :classification,
                :storage_key, :public_url, :mime_type, :format, :width, :height,
                :byte_size, :checksum_sha256, :alt_text, :status, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
             )'
        );
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
            'status' => 'READY',
        ]);
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
