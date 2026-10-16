<?php
declare(strict_types=1);
namespace Identity\Repositories;

use Identity\Contracts\{Clock,SystemClock};
use PDO;

/** Transactional consumer for ownership and the future clinical gate. No authority writes. */
final class ProfessionalAccountBindingProjection
{
    public function __construct(private PDO $pdo,private Clock $clock=new SystemClock(),
        private ?\Identity\Accreditation\ProtectedPhysicianEvidencePort $evidence=null){}
    public function usesConnection(PDO $pdo):bool{return $pdo===$this->pdo;}
    public static function identityRevision(array $row):string
    {
        return self::revision($row,[
            'verified_identity_id','doctor_id','given_names','first_surname','second_surname',
            'source_type','source_reference','verified_at','verified_by_account_id'
        ]);
    }
    public static function credentialRevision(array $row):string
    {
        return self::revision($row,[
            'credential_id','doctor_id','credential_type','license_number','verification_status',
            'lifecycle_status','verified_at','verified_by_account_id','source_type','source_reference','updated_at'
        ]);
    }
    private static function revision(array $row,array $keys):string
    {
        $facts=[];foreach($keys as $key)$facts[$key]=$row[$key]===null?null:(string)$row[$key];
        return hash('sha256',json_encode($facts,JSON_THROW_ON_ERROR));
    }
    public function assertEligible(string $accountId,string $doctorId):void
    { $this->requireCurrent($this->pdo,$accountId,$doctorId); }

    /** Future clinical consumer MUST additionally validate session, owner, operation,
     * entitlement, patient/document scope, intention and audit in its own transaction.
     * These minimized facts are not a signing permission or a cacheable credential.
     */
    public function requireCurrent(PDO $transaction,string $accountId,string $doctorId):array
    {
        if($transaction!==$this->pdo||!$this->pdo->inTransaction())throw new \RuntimeException('professional_binding_transaction_required');
        if($this->evidence===null)throw new \RuntimeException('professional_protected_evidence_required');
        $q=$this->pdo->prepare("SELECT b.*,a.status admission_status,a.authority_version current_admission_version,
            a.doctor_id admission_doctor_id,a.verified_identity_id admission_identity_id,a.professional_credential_id,
            a.reviewed_by_account_id,a.approved_at,a.valid_from admission_valid_from,a.valid_until admission_valid_until,a.reverify_at admission_reverify_at,
            a.evidence_request_id admission_request_id,a.evidence_version admission_evidence_version,a.account_id admission_account_id,
            a.case_reference,a.decision_reference
            FROM auth_account_professional_bindings b JOIN profiles_physician_admissions a ON a.admission_id=b.admission_id
            WHERE b.account_id=? AND b.profile_doctor_id=? AND b.status='ACTIVE' FOR UPDATE");
        $q->execute([$accountId,$doctorId]);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
        if(count($rows)!==1)throw new \RuntimeException('professional_binding_missing_or_ambiguous');
        $b=$rows[0];
        if($b['admission_status']!=='APPROVED'||$b['admission_doctor_id']!==$doctorId
            ||(int)$b['admission_identity_id']!==(int)$b['verified_identity_id']
            ||(int)$b['admission_version']!==(int)$b['current_admission_version']
            ||$b['reviewed_by_account_id']===$accountId||$b['approved_at']===null)
            throw new \RuntimeException('professional_admission_denied');
        $now=$this->clock->now();
        foreach(['valid_from','admission_valid_from','approved_at'] as $k)
            if(new \DateTimeImmutable($b[$k],new \DateTimeZone('UTC'))>$now)throw new \RuntimeException('professional_evidence_not_effective');
        foreach(['valid_until','reverify_at','admission_valid_until','admission_reverify_at'] as $k)
            if($b[$k]!==null&&new \DateTimeImmutable($b[$k],new \DateTimeZone('UTC'))<=$now)throw new \RuntimeException('professional_evidence_expired');
        $q=$this->pdo->prepare("SELECT a.account_id FROM auth_accounts a JOIN auth_account_credentials c ON c.account_id=a.account_id
            WHERE a.account_id=? AND a.status='active' AND a.email_verified_at IS NOT NULL AND c.credential_version>=1 FOR UPDATE");
        $q->execute([$accountId]);if($q->fetchColumn()===false)throw new \RuntimeException('professional_account_inactive');
        $q=$this->pdo->prepare("SELECT membership_id FROM auth_account_memberships WHERE account_id=? AND assignment_source='owner_operator_invitation' LIMIT 1 FOR UPDATE");
        $q->execute([$accountId]);if($q->fetchColumn()!==false)throw new \RuntimeException('nonmedical_operator_not_physician_owner');
        $q=$this->pdo->prepare("SELECT g.grant_id FROM internal_operator_grants g JOIN internal_staff s ON s.account_id=g.account_id AND s.status='ACTIVE'
            JOIN auth_accounts a ON a.account_id=s.account_id AND a.status='active' AND a.email_verified_at IS NOT NULL
            WHERE g.account_id=? AND g.capability='physician_accreditation_review' AND g.status='ACTIVE' AND g.revoked_at IS NULL FOR UPDATE");
        $q->execute([$b['reviewed_by_account_id']]);if($q->fetchColumn()===false)throw new \RuntimeException('professional_reviewer_authority_required');
        $q=$this->pdo->prepare('SELECT * FROM profiles_verified_identities WHERE verified_identity_id=? AND doctor_id=? FOR UPDATE');
        $q->execute([$b['verified_identity_id'],$doctorId]);$identity=$q->fetch(PDO::FETCH_ASSOC);
        $q=$this->pdo->prepare("SELECT * FROM profiles_doctor_credentials WHERE credential_id=? AND doctor_id=? AND credential_type='PROFESSIONAL' AND verification_status='VERIFIED' AND lifecycle_status='ACTIVE' FOR UPDATE");
        $q->execute([$b['professional_credential_id'],$doctorId]);$credential=$q->fetch(PDO::FETCH_ASSOC);
        if(!is_array($identity)||!is_array($credential)||$identity['source_type']==='synthetic_test'||$credential['source_type']==='synthetic_test'
            ||!hash_equals($b['identity_revision_hash'],self::identityRevision($identity))
            ||!hash_equals($b['credential_revision_hash'],self::credentialRevision($credential)))
            throw new \RuntimeException('professional_source_evidence_changed');
        if($b['account_id']!==$accountId||$b['profile_doctor_id']!==$doctorId||$b['admission_account_id']!==$accountId
            ||$b['admission_request_id']!==$b['evidence_request_id']||(int)$b['admission_evidence_version']!==(int)$b['evidence_version'])
            throw new \RuntimeException('professional_evidence_scope_mismatch');
        $r=$this->evidence->requireConfirmed($this->pdo,$b['evidence_request_id'],(int)$b['evidence_version'],$accountId,$doctorId);
        if((int)$r['verified_identity_id']!==(int)$b['verified_identity_id']||(int)$r['professional_credential_id']!==(int)$b['professional_credential_id']
            ||$r['reviewer_account_id']!==$b['reviewed_by_account_id']||$r['case_id']!==$b['case_reference']
            ||$r['review_audit_reference']!==$b['decision_reference']||$r['review_audit_reference']!==$b['relationship_evidence_reference']
            ||$r['confirmation_audit_reference']!==$b['acceptance_reference']||$r['valid_until']!==$b['valid_until']
            ||$r['valid_until']!==$b['admission_valid_until']||!hash_equals($b['identity_revision_hash'],$r['identity_revision_hash'])
            ||!hash_equals($b['credential_revision_hash'],$r['credential_revision_hash']))throw new \RuntimeException('professional_evidence_revision_mismatch');
        return ['binding_id'=>$b['binding_id'],'binding_version'=>(int)$b['authority_version'],'admission_id'=>$b['admission_id'],
            'admission_version'=>(int)$b['admission_version'],'account_id'=>$accountId,'profile_doctor_id'=>$doctorId,
            'verified_identity_id'=>(int)$b['verified_identity_id'],'professional_credential_id'=>(int)$b['professional_credential_id'],
            'evidence_request_id'=>$b['evidence_request_id'],'evidence_version'=>(int)$b['evidence_version'],
            'identity_revision_hash'=>$b['identity_revision_hash'],'credential_revision_hash'=>$b['credential_revision_hash'],
            'audit_reference'=>$b['audit_reference']];
    }
}
