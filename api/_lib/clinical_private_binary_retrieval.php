<?php
declare(strict_types=1);

require_once __DIR__ . '/clinical_private_binary_storage.php';
require_once __DIR__ . '/clinical_m6_observability.php';

/** Internal only: trusted server identity in, verified private stream out. */
final class ClinicalPrivateBinaryRetrieval
{
    public function __construct(private PDO $pdo, private ClinicalPrivateBinaryStorage $storage)
    {
        if ($pdo->getAttribute(PDO::ATTR_ERRMODE) !== PDO::ERRMODE_EXCEPTION) {
            throw new InvalidArgumentException('BINARY_RETRIEVAL_EXCEPTION_MODE_REQUIRED');
        }
    }

    /**
     * The future controller supplies authenticated server identities, never client authority.
     * Caller owns the returned stream and must close it. No transport output is emitted.
     */
    public function retrieve(string $doctorId, string $userId, string $token, string $variant = 'ORIGINAL'): array
    {
        if (trim($doctorId) === '' || trim($userId) === ''
            || !in_array($variant, ['ORIGINAL', 'DISPLAY', 'THUMBNAIL'], true)
            || $this->pdo->inTransaction()) {
            // Do not expose a caller's uncommitted document/manifest through this service.
            throw new RuntimeException('DOCUMENT_BINARY_NOT_FOUND');
        }
        $document = $this->documentByToken($token);
        if ($document === null || empty($document['patient_id'])) {
            throw new RuntimeException('DOCUMENT_BINARY_NOT_FOUND');
        }
        $link = $this->selectOne("SELECT patient_id FROM patients_doctor_links
            WHERE doctor_id=:doctor AND patient_id=:patient AND status='active' LIMIT 1",
            [':doctor' => $doctorId, ':patient' => $document['patient_id']]);
        if ($link === null) {
            throw new RuntimeException('DOCUMENT_BINARY_NOT_FOUND');
        }
        $encounterId = $document['encounter_ref_id'];
        if ($encounterId !== null) {
            if (!ctype_digit((string)$encounterId) || (int)$encounterId <= 0) {
                throw new RuntimeException('DOCUMENT_BINARY_NOT_FOUND');
            }
            $encounter = $this->selectOne('SELECT doctor_id,patient_id FROM clinical_encounters WHERE encounter_id=:id LIMIT 1', [':id' => $encounterId]);
            if ($encounter === null || $encounter['doctor_id'] === null
                || (string)$encounter['doctor_id'] !== $doctorId
                || (string)$encounter['patient_id'] !== (string)$document['patient_id']) {
                throw new RuntimeException('DOCUMENT_BINARY_NOT_FOUND');
            }
        }
        $manifest = $this->selectOne('SELECT binary_uuid,variant_role,variant_version,storage_key,sha256,byte_length,mime_type,source_filename
            FROM clinical_document_binaries WHERE document_id=:id AND variant_role=:variant AND variant_version=1 LIMIT 1',
            [':id' => $document['id'], ':variant' => $variant]);
        if ($manifest === null) {
            throw new RuntimeException('DOCUMENT_BINARY_NOT_FOUND');
        }
        $key = (string)$manifest['storage_key'];
        $sha = (string)$manifest['sha256'];
        $length = filter_var($manifest['byte_length'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!str_starts_with($key, 'clinical/') || !preg_match('/^[a-f0-9]{64}$/', $sha)
            || $length === false || !in_array($manifest['mime_type'], ['application/pdf','image/jpeg','image/png','image/webp'], true)) {
            throw new RuntimeException('DOCUMENT_BINARY_INTEGRITY_MISMATCH');
        }
        $stream = null;
        try {
            $stat = $this->storage->stat($key);
            if (!$stat['exists']) {
                throw new RuntimeException('DOCUMENT_BINARY_MISSING');
            }
            if ($stat['byte_length'] !== $length || !hash_equals($sha, $stat['sha256'])) {
                throw new RuntimeException('DOCUMENT_BINARY_INTEGRITY_MISMATCH');
            }
            $stream = $this->storage->openReadStream($key);
            // Recheck the opened handle: a path replacement between stat and open
            // must not allow different bytes to escape. Return the same verified handle.
            $hash = hash_init('sha256');
            $read = hash_update_stream($hash, $stream);
            if ($read !== $length || !hash_equals($sha, hash_final($hash)) || !rewind($stream)) {
                throw new RuntimeException('DOCUMENT_BINARY_INTEGRITY_MISMATCH');
            }
        } catch (Throwable $error) {
            clinical_m6_observability_storage('PRIVATE_READ_FAILED', false, $error->getMessage());
            if (is_resource($stream)) {
                fclose($stream);
            }
            if ($error instanceof ClinicalPrivateBinaryStorageException) {
                throw new RuntimeException($error->getMessage() === 'PRIVATE_STORAGE_DIRECTORY_INVALID'
                    ? 'DOCUMENT_BINARY_MISSING' : 'DOCUMENT_BINARY_INTEGRITY_MISMATCH', 0, $error);
            }
            throw $error;
        }
        clinical_m6_observability_storage('PRIVATE_READ_VERIFIED', true);
        return [
            'document' => [
                'document_id' => (int)$document['id'], 'document_uuid' => $document['document_uuid'],
                'patient_id' => $document['patient_id'], 'encounter_ref_id' => $encounterId === null ? null : (int)$encounterId,
                'document_type' => $document['document_type'], 'status' => $document['status'],
            ],
            'binary' => [
                'binary_uuid' => $manifest['binary_uuid'], 'variant_role' => $manifest['variant_role'],
                'mime_type' => $manifest['mime_type'], 'byte_length' => $length, 'sha256' => $sha,
                'source_filename' => self::displayFilename($manifest['source_filename']),
            ],
            'stream' => $stream,
        ];
    }

    /** Bounded read-only equivalent of clinical_v1_document_record_by_token(); no router dependency. */
    private function documentByToken(string $token): ?array
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }
        $where = preg_match('/^\d+$/', $token) === 1 ? 'id=:token' : 'document_uuid=:token';
        return $this->selectOne('SELECT id,document_uuid,patient_id,encounter_ref_id,document_type,status FROM clinical_documents WHERE '.$where.' LIMIT 1', [':token' => $token]);
    }

    private function selectOne(string $sql, array $params): ?array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private static function displayFilename(?string $filename): ?string
    {
        if ($filename === null) {
            return null;
        }
        $filename = basename(str_replace('\\', '/', $filename));
        // Display metadata only. Future HTTP disposition must additionally encode it.
        $filename = preg_replace('/[\x00-\x1F\x7F"<>]/u', '', $filename);
        return $filename === null || trim($filename, " .") === '' ? null : trim($filename);
    }
}
