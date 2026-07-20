<?php
declare(strict_types=1);

namespace Identity\Repositories;

use Identity\Contracts\ConsentDocumentType;
use PDO;
use PDOException;

final class AccountConsentRepository
{
    public function __construct(private PDO $pdo) {}

    /** @param array<string, scalar|null> $metadata */
    public function record(string $consentId, string $accountId, string $documentType, string $version, string $acceptedAt, array $metadata = []): void
    {
        ConsentDocumentType::assertValid($documentType);
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]{0,63}$/', $consentId) || !preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]{0,63}$/', $version)) {
            throw new \InvalidArgumentException('invalid_consent_identifier');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $acceptedAt)) {
            throw new \InvalidArgumentException('invalid_consent_timestamp');
        }
        $this->assertSafeMetadata($metadata);
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO auth_account_consents (consent_id, account_id, document_type, document_version, accepted_at, metadata_json)
                 VALUES (:consent_id, :account_id, :document_type, :document_version, :accepted_at, :metadata_json)'
            );
            $stmt->execute([
                ':consent_id' => $consentId,
                ':account_id' => $accountId,
                ':document_type' => $documentType,
                ':document_version' => $version,
                ':accepted_at' => $acceptedAt,
                ':metadata_json' => $metadata === [] ? null : json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            ]);
        } catch (PDOException $e) {
            throw new \RuntimeException('identity_consent_record_failed', 0, $e);
        }
    }

    public function hasRequiredForAccount(string $accountId): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(DISTINCT document_type) FROM auth_account_consents
             WHERE account_id = :account_id AND document_type IN ('terms','privacy_notice')"
        );
        $stmt->execute([':account_id' => $accountId]);
        return (int)$stmt->fetchColumn() === 2;
    }

    /** @param array<string, scalar|null> $metadata */
    private function assertSafeMetadata(array $metadata): void
    {
        foreach ($metadata as $key => $value) {
            if (!is_string($key) || preg_match('/password|token|cookie|session|clinical|patient|secret/i', $key)) {
                throw new \InvalidArgumentException('unsafe_consent_metadata');
            }
            if (!is_scalar($value) && $value !== null) {
                throw new \InvalidArgumentException('invalid_consent_metadata');
            }
        }
    }
}
