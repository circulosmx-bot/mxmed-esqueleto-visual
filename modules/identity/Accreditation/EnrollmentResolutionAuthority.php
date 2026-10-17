<?php
declare(strict_types=1);
namespace Identity\Accreditation;

use PDO;

/** Internal exact-transaction read port. A row alone never authorizes accreditation. */
final class EnrollmentResolutionAuthority
{
    public function __construct(private EvidenceAuthority $a) {}
    public function current(string $id):array
    {
        if(!$this->a->pdo->inTransaction())throw new \RuntimeException('enrollment_resolution_transaction_required');
        $r=$this->a->query('SELECT * FROM auth_physician_enrollment_resolutions WHERE resolution_id=? FOR UPDATE',[$id])->fetch(PDO::FETCH_ASSOC);
        if(!is_array($r)||$r['resolution_id']!==$id||$r['state']!=='APPROVED')throw new \RuntimeException('enrollment_approved_resolution_required');
        $o=$this->a->query('SELECT * FROM auth_physician_enrollment_origins WHERE origin_id=? FOR UPDATE',[$r['origin_id']])->fetch(PDO::FETCH_ASSOC);
        $head=$this->a->query('SELECT resolution_id FROM auth_physician_enrollment_resolutions WHERE origin_id=? ORDER BY resolution_version DESC LIMIT 1 FOR UPDATE',[$r['origin_id']])->fetchColumn();
        if(!is_array($o)||$head!==$id||$o['account_id']!==$r['account_id']||$o['intake_state']!=='SUBMITTED'
            ||(int)$o['intake_version']!==(int)$r['intake_version']||$o['initial_origin']!==$r['initial_origin']
            ||$o['original_profile_doctor_id']!==$r['original_profile_doctor_id']
            ||($r['initial_origin']==='CLAIM_EXISTING_PROFILE'&&$r['resolved_profile_doctor_id']!==$o['original_profile_doctor_id'])
            ||!in_array($r['initial_origin'],['CLAIM_EXISTING_PROFILE','REQUEST_NEW_PROFILE'],true))throw new \RuntimeException('enrollment_resolution_stale');
        $this->a->applicant($r['account_id']);
        $this->a->reviewer($r['reviewer_account_id'],$r['account_id'],(int)$r['reviewer_credential_version']);
        $case=$this->a->query("SELECT * FROM auth_governance_cases WHERE case_id=? AND operation='PHYSICIAN_ENROLLMENT_REVIEW' FOR UPDATE",[$r['case_id']])->fetch(PDO::FETCH_ASSOC);
        $c=is_array($case)?json_decode($case['security_context'],true,32,JSON_THROW_ON_ERROR):[];
        $proof=$this->a->query("SELECT p.* FROM auth_staff_mfa_proofs p JOIN auth_staff_mfa_factors f ON f.factor_id=p.factor_id AND f.status='ACTIVE' WHERE p.proof_id=? FOR UPDATE",[$r['proof_id']])->fetch(PDO::FETCH_ASSOC);
        if(!is_array($case)||$case['status']!=='CONSUMED'||$case['requester_account_id']!==$r['reviewer_account_id']
            ||$case['beneficiary_account_id']!==$r['account_id']||$case['profile_doctor_id']!==$r['resolved_profile_doctor_id']
            ||($c['contract']??null)!=='PUB-ENR03.1'||($c['origin_id']??null)!==$r['origin_id']||($c['decision']??null)!=='APPROVED'
            ||($c['facts_hash']??null)!==$r['facts_hash']||($c['expected_review_version']??-1)+1!==(int)$r['resolution_version']
            ||!is_array($proof)||$proof['status']!=='CONSUMED'||$proof['case_id']!==$r['case_id']||$proof['actor_account_id']!==$r['reviewer_account_id']
            ||$proof['operation']!=='PHYSICIAN_ENROLLMENT_REVIEW'||$proof['beneficiary_account_id']!==$r['account_id']
            ||$proof['target_account_id']!==$r['account_id']||$proof['profile_doctor_id']!==$r['resolved_profile_doctor_id']
            ||(int)$proof['credential_version']!==(int)$r['reviewer_credential_version']
            ||$proof['staff_session_id']!==($c['reviewer']['session_id']??null)||$proof['consumed_by_reference']!==$id)
            throw new \RuntimeException('enrollment_review_evidence_denied');
        $this->a->account($r['account_id'],(int)($c['facts']['applicant_credential_version']??0));
        $s=$this->a->sources($r['resolved_profile_doctor_id'],(int)$r['verified_identity_id'],(int)$r['professional_credential_id']);
        if(!hash_equals($r['identity_revision_hash'],$s['identity_hash'])||!hash_equals($r['credential_revision_hash'],$s['credential_hash']))
            throw new \RuntimeException('enrollment_professional_revision_changed');
        $matches=$this->a->query("SELECT doctor_id FROM profiles_doctor_credentials WHERE credential_type='PROFESSIONAL' AND license_number=? FOR UPDATE",[$s['credential']['license_number']])->fetchAll(PDO::FETCH_COLUMN);
        $legacy=$this->a->query('SELECT doctor_id FROM profiles_doctors WHERE BINARY professional_license=BINARY ? FOR UPDATE',[$s['credential']['license_number']])->fetchAll(PDO::FETCH_COLUMN);
        $matches=array_values(array_unique([...$matches,...$legacy]));
        if($matches!==[$r['resolved_profile_doctor_id']])throw new \RuntimeException('enrollment_matching_revision_changed');
        $owners=$this->a->query("SELECT account_id FROM auth_account_memberships WHERE profile_doctor_id=? AND role_code='owner' AND status='active' FOR UPDATE",[$r['resolved_profile_doctor_id']])->fetchAll(PDO::FETCH_COLUMN);
        if(count($owners)>1||($owners!==[]&&$owners[0]!==$r['account_id'])
            ||$this->a->query('SELECT binding_id FROM auth_account_professional_bindings WHERE profile_doctor_id=? AND account_id<>? LIMIT 1 FOR UPDATE',[$r['resolved_profile_doctor_id'],$r['account_id']])->fetchColumn()!==false)
            throw new \RuntimeException('enrollment_owner_or_binding_conflict');
        return $r;
    }
    public function forEvidence(array $evidence):array
    {
        $r=$this->current((string)($evidence['enrollment_resolution_id']??''));
        foreach(['account_id'=>'account_id','origin'=>'initial_origin','profile_doctor_id'=>'resolved_profile_doctor_id',
            'verified_identity_id'=>'verified_identity_id','professional_credential_id'=>'professional_credential_id',
            'identity_revision_hash'=>'identity_revision_hash','credential_revision_hash'=>'credential_revision_hash'] as $ek=>$rk)
            if((string)$evidence[$ek]!== (string)$r[$rk])throw new \RuntimeException('enrollment_handoff_scope_denied');
        $h=$this->a->query('SELECT * FROM auth_physician_enrollment_handoffs WHERE resolution_id=? FOR UPDATE',[$r['resolution_id']])->fetch(PDO::FETCH_ASSOC);
        if(!is_array($h)||$h['origin_id']!==$r['origin_id']||$h['evidence_request_id']!==$evidence['request_id'])throw new \RuntimeException('enrollment_handoff_scope_denied');
        return $r;
    }
}
