<?php
declare(strict_types=1);
namespace Profiles\Repositories;
use PDO;
use RuntimeException;

/** Persistence only; the trusted service owns policy and transaction boundaries. */
final class DoctorCredentialRepository
{
    public function __construct(private PDO $pdo) {}
    public function connection(): PDO { return $this->pdo; }
    public function findById(string $id, bool $lock = false): ?array
    {
        $s=$this->pdo->prepare('SELECT * FROM profiles_doctor_credentials WHERE credential_id=?'.($lock?' FOR UPDATE':''));
        $s->execute([$id]); return $s->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    public function listByDoctorId(string $doctorId): array
    {
        $s=$this->pdo->prepare('SELECT * FROM profiles_doctor_credentials WHERE doctor_id=? ORDER BY credential_id');
        $s->execute([$doctorId]); return $s->fetchAll(PDO::FETCH_ASSOC);
    }
    public function resolveVerifiedProfessional(string $doctorId): ?array
    {
        return $this->eligible($doctorId,'PROFESSIONAL')[0] ?? null;
    }
    public function resolveVerifiedSpecialties(string $doctorId): array { return $this->eligible($doctorId,'SPECIALTY'); }
    private function eligible(string $doctorId,string $type): array
    {
        $s=$this->pdo->prepare("SELECT * FROM profiles_doctor_credentials WHERE doctor_id=? AND credential_type=? AND verification_status='VERIFIED' AND lifecycle_status='ACTIVE' ORDER BY credential_id");
        $s->execute([$doctorId,$type]);return $s->fetchAll(PDO::FETCH_ASSOC);
    }
    public function primaryId(string $doctorId): ?string
    {
        $s=$this->pdo->prepare('SELECT primary_specialty_credential_id FROM profiles_doctors WHERE doctor_id=?');
        $s->execute([$doctorId]);$id=$s->fetchColumn();return $id===false || $id===null ? null : (string)$id;
    }
    public function create(array $row): array
    {
        $keys=['doctor_id','credential_type','license_number','professional_area_label','institution_name','verification_status','lifecycle_status','verified_at','verified_by_account_id','source_type','source_reference'];
        $s=$this->pdo->prepare('INSERT INTO profiles_doctor_credentials ('.implode(',',$keys).') VALUES ('.implode(',',array_fill(0,count($keys),'?')).')');
        $s->execute(array_map(static fn($k)=>$row[$k],$keys));
        return $this->findById($this->pdo->lastInsertId()) ?? throw new RuntimeException('credential_create_failed');
    }
    public function persistTransition(string $id,array $row): array
    {
        $s=$this->pdo->prepare('UPDATE profiles_doctor_credentials SET verification_status=?,lifecycle_status=?,professional_area_label=?,institution_name=?,verified_at=?,verified_by_account_id=?,source_type=?,source_reference=? WHERE credential_id=?');
        $s->execute([$row['verification_status'],$row['lifecycle_status'],$row['professional_area_label'],$row['institution_name'],$row['verified_at'],$row['verified_by_account_id'],$row['source_type'],$row['source_reference'],$id]);
        return $this->findById($id) ?? throw new RuntimeException('credential_transition_failed');
    }
    public function isActiveInternalApprover(string $accountId): bool
    {
        $s=$this->pdo->prepare("SELECT 1 FROM internal_staff WHERE account_id=? AND status='ACTIVE' AND governance_class IN ('DIRECTOR','MASTER_ADMIN','ADVISOR') FOR UPDATE");
        $s->execute([$accountId]);return $s->fetchColumn()!==false;
    }
}
