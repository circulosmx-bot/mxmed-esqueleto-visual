<?php
declare(strict_types=1);

namespace Profiles\Repositories;

use PDO;
use PDOException;
use RuntimeException;

final class VerifiedPhysicianIdentityRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findByDoctorId(string $doctorId): ?array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT verified_identity_id, doctor_id, given_names, first_surname, second_surname,
                        source_type, source_reference, verified_at, verified_by_account_id, created_at
                 FROM profiles_verified_identities
                 WHERE doctor_id = :doctor_id
                 LIMIT 1'
            );
            $stmt->execute(['doctor_id' => $doctorId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new RuntimeException('verified_identity_read_failed', 0, $e);
        }

        return is_array($row) ? $this->mapRow($row) : null;
    }

    public function create(array $identity): array
    {
        if ($this->findByDoctorId((string)($identity['doctor_id'] ?? '')) !== null) {
            throw new RuntimeException('verified_identity_already_exists');
        }

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO profiles_verified_identities (
                    doctor_id, given_names, first_surname, second_surname,
                    source_type, source_reference, verified_at, verified_by_account_id
                 ) VALUES (
                    :doctor_id, :given_names, :first_surname, :second_surname,
                    :source_type, :source_reference, :verified_at, :verified_by_account_id
                 )'
            );
            $stmt->execute([
                'doctor_id' => $identity['doctor_id'],
                'given_names' => $identity['given_names'],
                'first_surname' => $identity['first_surname'],
                'second_surname' => $identity['second_surname'],
                'source_type' => $identity['source_type'],
                'source_reference' => $identity['source_reference'],
                'verified_at' => $identity['verified_at'],
                'verified_by_account_id' => $identity['verified_by_account_id'],
            ]);
        } catch (PDOException $e) {
            $driverCode = (int)($e->errorInfo[1] ?? 0);
            if ((string)$e->getCode() === '23000' && $driverCode === 1062) {
                throw new RuntimeException('verified_identity_already_exists', 0, $e);
            }
            throw new RuntimeException('verified_identity_create_failed', 0, $e);
        }

        $created = $this->findByDoctorId((string)$identity['doctor_id']);
        if ($created === null) {
            throw new RuntimeException('verified_identity_read_after_create_failed');
        }
        return $created;
    }

    public function isActiveInternalApprover(string $accountId): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT 1
                 FROM internal_staff
                 WHERE account_id = :account_id
                   AND status = 'ACTIVE'
                   AND governance_class IN ('DIRECTOR', 'MASTER_ADMIN', 'ADVISOR')
                 LIMIT 1"
            );
            $stmt->execute(['account_id' => $accountId]);
            return $stmt->fetchColumn() !== false;
        } catch (PDOException $e) {
            throw new RuntimeException('verified_identity_approver_check_failed', 0, $e);
        }
    }

    private function mapRow(array $row): array
    {
        return [
            'verified_identity_id' => (string)($row['verified_identity_id'] ?? ''),
            'doctor_id' => trim((string)($row['doctor_id'] ?? '')),
            'given_names' => trim((string)($row['given_names'] ?? '')),
            'first_surname' => trim((string)($row['first_surname'] ?? '')),
            'second_surname' => $this->nullableText($row['second_surname'] ?? null),
            'source_type' => trim((string)($row['source_type'] ?? '')),
            'source_reference' => trim((string)($row['source_reference'] ?? '')),
            'verified_at' => trim((string)($row['verified_at'] ?? '')),
            'verified_by_account_id' => $this->nullableText($row['verified_by_account_id'] ?? null),
            'created_at' => trim((string)($row['created_at'] ?? '')),
        ];
    }

    private function nullableText($value): ?string
    {
        $text = trim((string)($value ?? ''));
        return $text === '' ? null : $text;
    }
}
