<?php
declare(strict_types=1);
namespace Billing\Repositories;

use PDO;

final class PatientBillingProfilesRepository
{
    public function __construct(private PDO $pdo) {}

    public function transaction(callable $work): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $result = $work();
            $this->pdo->commit();
            return $result;
        } catch (\Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
    }

    public function requireActiveLink(string $doctorId, string $patientId, bool $lock = false): void
    {
        $sql = 'SELECT l.link_id FROM patients_doctor_links l JOIN patients_patients p ON p.patient_id = l.patient_id WHERE l.doctor_id = :doctor_id AND l.patient_id = :patient_id AND l.status = :status AND p.status = :status LIMIT 1';
        if ($lock && $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') $sql .= ' FOR UPDATE';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['doctor_id'=>$doctorId, 'patient_id'=>$patientId, 'status'=>'active']);
        if (!$stmt->fetchColumn()) throw new \DomainException('patient_scope_denied');
    }

    public function listActive(string $doctorId, string $patientId): array
    {
        $stmt = $this->pdo->prepare('SELECT billing_profile_id, patient_id, alias, receiver_legal_name, rfc, fiscal_zip_code, fiscal_regime_code, default_cfdi_use_code, billing_email, is_default, created_at, updated_at FROM billing_patient_profiles WHERE doctor_id = :doctor_id AND patient_id = :patient_id AND archived_at IS NULL ORDER BY is_default DESC, created_at ASC, billing_profile_id ASC');
        $stmt->execute(['doctor_id'=>$doctorId, 'patient_id'=>$patientId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findActive(string $doctorId, string $patientId, string $profileId): array
    {
        $stmt = $this->pdo->prepare('SELECT billing_profile_id, patient_id, alias, receiver_legal_name, rfc, fiscal_zip_code, fiscal_regime_code, default_cfdi_use_code, billing_email, is_default, created_at, updated_at FROM billing_patient_profiles WHERE doctor_id = :doctor_id AND patient_id = :patient_id AND billing_profile_id = :profile_id AND archived_at IS NULL');
        $stmt->execute(['doctor_id'=>$doctorId, 'patient_id'=>$patientId, 'profile_id'=>$profileId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new \DomainException('billing_profile_not_found');
        return $row;
    }

    public function insert(string $id, string $doctorId, string $patientId, array $fields, bool $default): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO billing_patient_profiles (billing_profile_id, doctor_id, patient_id, alias, receiver_legal_name, rfc, fiscal_zip_code, fiscal_regime_code, default_cfdi_use_code, billing_email, is_default) VALUES (:id, :doctor_id, :patient_id, :alias, :name, :rfc, :zip, :regime, :use_code, :email, :is_default)');
        $stmt->execute(['id'=>$id, 'doctor_id'=>$doctorId, 'patient_id'=>$patientId, 'alias'=>$fields['alias'], 'name'=>$fields['receiver_legal_name'], 'rfc'=>$fields['rfc'], 'zip'=>$fields['fiscal_zip_code'], 'regime'=>$fields['fiscal_regime_code'], 'use_code'=>$fields['default_cfdi_use_code'], 'email'=>$fields['billing_email'], 'is_default'=>$default ? 1 : 0]);
    }

    public function update(string $doctorId, string $patientId, string $profileId, array $fields): void
    {
        $stmt = $this->pdo->prepare('UPDATE billing_patient_profiles SET alias=:alias, receiver_legal_name=:name, rfc=:rfc, fiscal_zip_code=:zip, fiscal_regime_code=:regime, default_cfdi_use_code=:use_code, billing_email=:email, updated_at=CURRENT_TIMESTAMP WHERE doctor_id=:doctor_id AND patient_id=:patient_id AND billing_profile_id=:id AND archived_at IS NULL');
        $stmt->execute(['alias'=>$fields['alias'], 'name'=>$fields['receiver_legal_name'], 'rfc'=>$fields['rfc'], 'zip'=>$fields['fiscal_zip_code'], 'regime'=>$fields['fiscal_regime_code'], 'use_code'=>$fields['default_cfdi_use_code'], 'email'=>$fields['billing_email'], 'doctor_id'=>$doctorId, 'patient_id'=>$patientId, 'id'=>$profileId]);
    }

    public function clearDefault(string $doctorId, string $patientId): void
    {
        $stmt = $this->pdo->prepare('UPDATE billing_patient_profiles SET is_default=0, updated_at=CURRENT_TIMESTAMP WHERE doctor_id=:doctor_id AND patient_id=:patient_id AND archived_at IS NULL AND is_default=1');
        $stmt->execute(['doctor_id'=>$doctorId, 'patient_id'=>$patientId]);
    }

    public function setDefault(string $doctorId, string $patientId, string $profileId, bool $value): void
    {
        $stmt = $this->pdo->prepare('UPDATE billing_patient_profiles SET is_default=:value, updated_at=CURRENT_TIMESTAMP WHERE doctor_id=:doctor_id AND patient_id=:patient_id AND billing_profile_id=:id AND archived_at IS NULL');
        $stmt->execute(['value'=>$value ? 1 : 0, 'doctor_id'=>$doctorId, 'patient_id'=>$patientId, 'id'=>$profileId]);
    }

    public function archive(string $doctorId, string $patientId, string $profileId): void
    {
        $stmt = $this->pdo->prepare('UPDATE billing_patient_profiles SET is_default=0, archived_at=CURRENT_TIMESTAMP, updated_at=CURRENT_TIMESTAMP WHERE doctor_id=:doctor_id AND patient_id=:patient_id AND billing_profile_id=:id AND archived_at IS NULL');
        $stmt->execute(['doctor_id'=>$doctorId, 'patient_id'=>$patientId, 'id'=>$profileId]);
    }
}
