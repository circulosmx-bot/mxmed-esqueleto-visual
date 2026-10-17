<?php
declare(strict_types=1);
namespace Identity\Accreditation;

use Identity\Contracts\{AuthenticatedAccessContext,Clock,SystemClock};
use Identity\Services\GovernanceSecurityContext;
use Identity\Repositories\ProfessionalAccountBindingProjection;
use PDO;

/** Shared transaction, exact current account, professional sources and scope checks. */
final class EvidenceAuthority
{
    public function __construct(public readonly PDO $pdo,public readonly GovernanceSecurityContext $security,
        public readonly Clock $clock=new SystemClock(),public readonly ?\Identity\Operational\WorkflowScope $scope=null) {}
    public function sqlTime(?\DateTimeImmutable $at=null): string
    { return ($at??$this->clock->now())->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.u'); }
    public function uuid(): string { return (new \Platform\Services\RandomAuditUuidProvider())->generateCanonicalUuid(); }
    public function query(string $sql,array $args=[]): \PDOStatement
    { $q=$this->pdo->prepare($sql);$q->execute($args);return $q; }
    public function transaction(AuthenticatedAccessContext $actor,callable $fn): mixed
    {
        if ($this->pdo->inTransaction()) throw new \RuntimeException('c4_nested_transaction_denied');
        $binding=$this->security->binding($actor);
        try {
            $this->pdo->beginTransaction();$this->account($actor->accountId(),$actor->principal()->credentialVersion());
            $result=$fn();$this->security->assertLive($binding);
            if (!$this->pdo->commit()) throw new \RuntimeException('c4_commit_failed');
            return $result;
        } catch (\Throwable $e) { if ($this->pdo->inTransaction()) $this->pdo->rollBack();throw $e; }
    }
    public function account(string $id,?int $version=null): array
    {
        $this->scope?->account($id);
        if (!$this->pdo->inTransaction()) throw new \RuntimeException('c4_transaction_required');
        $r=$this->query("SELECT a.account_id,a.status,a.email_verified_at,c.credential_version FROM auth_accounts a
            JOIN auth_account_credentials c ON c.account_id=a.account_id WHERE a.account_id=? FOR UPDATE",[$id])->fetch(PDO::FETCH_ASSOC);
        if (!is_array($r)||$r['account_id']!==$id||$r['status']!=='active'||$r['email_verified_at']===null
            ||(int)$r['credential_version']<1||($version!==null&&(int)$r['credential_version']!==$version))
            throw new \RuntimeException('c4_account_denied');
        return $r;
    }
    public function applicant(string $id): void
    {
        $this->account($id);
        if ($this->query("SELECT membership_id FROM auth_account_memberships WHERE account_id=? AND assignment_source='owner_operator_invitation' LIMIT 1 FOR UPDATE",[$id])->fetchColumn()!==false)
            throw new \RuntimeException('c4_nonmedical_operator_denied');
    }
    public function reviewer(string $id,string $beneficiary,?int $version=null): void
    {
        $this->scope?->staff($id,'physician_accreditation_review');
        if ($id===$beneficiary) throw new \RuntimeException('c4_self_review_denied');
        $this->account($id,$version);
        if ($this->query("SELECT g.grant_id FROM internal_staff s JOIN internal_operator_grants g ON g.account_id=s.account_id
            WHERE s.account_id=? AND s.status='ACTIVE' AND g.capability='physician_accreditation_review'
            AND g.status='ACTIVE' AND g.revoked_at IS NULL FOR UPDATE",[$id])->fetchColumn()===false)
            throw new \RuntimeException('c4_reviewer_capability_required');
    }
    public function phone(string $account,?int $expectedVersion=null): array
    {
        $r=$this->query("SELECT contact_version,status,verified_at,delivery_accepted,audit_reference FROM auth_account_phone_verifications WHERE account_id=? FOR UPDATE",[$account])->fetch(PDO::FETCH_ASSOC);
        if (!is_array($r)||$r['status']!=='VERIFIED'||$r['verified_at']===null||(int)$r['delivery_accepted']!==1
            ||($expectedVersion!==null&&(int)$r['contact_version']!==$expectedVersion))
            throw new \RuntimeException('c4_phone_verification_required');
        return $r;
    }
    public function sources(string $doctor,int $identityId,int $credentialId): array
    {
        $this->scope?->profile($doctor);
        $i=$this->query('SELECT * FROM profiles_verified_identities WHERE verified_identity_id=? FOR UPDATE',[$identityId])->fetch(PDO::FETCH_ASSOC);
        $c=$this->query('SELECT * FROM profiles_doctor_credentials WHERE credential_id=? FOR UPDATE',[$credentialId])->fetch(PDO::FETCH_ASSOC);
        if (!is_array($i)||!is_array($c)||$i['doctor_id']!==$doctor||$c['doctor_id']!==$doctor
            ||$c['credential_type']!=='PROFESSIONAL'||$c['verification_status']!=='VERIFIED'||$c['lifecycle_status']!=='ACTIVE'
            ||$i['source_type']==='synthetic_test'||$c['source_type']==='synthetic_test'
            ||$i['verified_by_account_id']===null||$c['verified_by_account_id']===null||$c['verified_at']===null
            ||new \DateTimeImmutable($i['verified_at'],new \DateTimeZone('UTC'))>$this->clock->now()
            ||new \DateTimeImmutable($c['verified_at'],new \DateTimeZone('UTC'))>$this->clock->now())
            throw new \RuntimeException('c4_professional_sources_denied');
        foreach([$i,$c] as $source)\Identity\ProfessionalSources\SourceEvidenceReference::assertCanonical($this->pdo,(string)$source['source_reference'],$doctor);
        return ['identity_hash'=>ProfessionalAccountBindingProjection::identityRevision($i),
            'credential_hash'=>ProfessionalAccountBindingProjection::credentialRevision($c),'credential'=>$c];
    }
    public function request(string $id,?int $version=null): array
    {
        $r=$this->query('SELECT * FROM auth_physician_evidence_requests WHERE request_id=? FOR UPDATE',[$id])->fetch(PDO::FETCH_ASSOC);
        if (!is_array($r)||$r['request_id']!==$id||($version!==null&&(int)$r['version']!==$version))
            throw new \RuntimeException('c4_request_missing_or_stale');
        $this->scope?->profile($r['profile_doctor_id']);$this->scope?->account($r['account_id']);
        return $r;
    }
    public function currentFacts(array $r,bool $initialApproval=false): void
    {
        $this->applicant($r['account_id']);$this->phone($r['account_id'],(int)$r['phone_version']);
        if(($r['enrollment_resolution_id']??null)!==null){
            (new EnrollmentResolutionAuthority($this))->forEvidence($r);
        } elseif ($r['origin']!=='CLAIM_EXISTING_PROFILE'||$r['profile_doctor_id']===null)
            throw new \RuntimeException('c4_exception_or_new_profile_resolution_required');
        $this->query('SELECT doctor_id FROM profiles_doctors WHERE doctor_id=? FOR UPDATE',[$r['profile_doctor_id']]);
        $owners=$this->query("SELECT account_id FROM auth_account_memberships WHERE profile_doctor_id=? AND role_code='owner' AND status='active' FOR UPDATE",[$r['profile_doctor_id']])->fetchAll(PDO::FETCH_COLUMN);
        if (count($owners)>1||($owners!==[]&&($initialApproval||$owners[0]!==$r['account_id']))) throw new \RuntimeException('c4_existing_owner_conflict');
        if ($this->query("SELECT request_id FROM auth_physician_evidence_requests WHERE profile_doctor_id=? AND account_id<>? AND reviewed_version IS NOT NULL LIMIT 1 FOR UPDATE",
            [$r['profile_doctor_id'],$r['account_id']])->fetchColumn()!==false
            ||$this->query('SELECT binding_id FROM auth_account_professional_bindings WHERE profile_doctor_id=? AND account_id<>? LIMIT 1 FOR UPDATE',
                [$r['profile_doctor_id'],$r['account_id']])->fetchColumn()!==false)
            throw new \RuntimeException('c4_account_transfer_review_required');
        $s=$this->sources($r['profile_doctor_id'],(int)$r['verified_identity_id'],(int)$r['professional_credential_id']);
        if (!hash_equals($r['identity_revision_hash'],$s['identity_hash'])||!hash_equals($r['credential_revision_hash'],$s['credential_hash']))
            throw new \RuntimeException('c4_professional_revision_changed');
        $matches=$this->query("SELECT doctor_id FROM profiles_doctor_credentials WHERE credential_type='PROFESSIONAL' AND license_number=? FOR UPDATE",[$s['credential']['license_number']])->fetchAll(PDO::FETCH_COLUMN);
        $legacy=$this->query('SELECT doctor_id FROM profiles_doctors WHERE BINARY professional_license=BINARY ? FOR UPDATE',[$s['credential']['license_number']])->fetchAll(PDO::FETCH_COLUMN);
        $matches=array_values(array_unique([...$matches,...$legacy]));
        if (count($matches)!==1||$matches[0]!==$r['profile_doctor_id'])
            throw new \RuntimeException('c4_license_conflict');
        if ($r['valid_until']!==null&&new \DateTimeImmutable($r['valid_until'],new \DateTimeZone('UTC'))<=$this->clock->now())
            throw new \RuntimeException('c4_evidence_expired');
    }
}
