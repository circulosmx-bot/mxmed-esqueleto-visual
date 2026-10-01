<?php
declare(strict_types=1);

namespace Agenda\Services;

use Agenda\Contracts\HealthcareOrganizationType;
use Agenda\Repositories\MedicalGroupsRepository;
use Identity\Contracts\AuthenticatedAccessContext;
use PDO;
use PDOException;
use RuntimeException;

require_once __DIR__.'/../contracts/HealthcareOrganizationType.php';
require_once __DIR__.'/../repositories/MedicalGroupsRepository.php';

final class HealthcareOrganizationClaimException extends RuntimeException
{
    public function __construct(public readonly string $reason, public readonly array $details = [])
    {
        parent::__construct($reason);
    }
}

/** Trusted server-side claim authority. Callers must supply a validated canonical session context. */
final class HealthcareOrganizationClaimService
{
    private const PROVIDER_TYPES = [
        HealthcareOrganizationType::LABORATORY,
        HealthcareOrganizationType::DIAGNOSTIC_CENTER,
        HealthcareOrganizationType::CLINIC,
        HealthcareOrganizationType::HOSPITAL,
        HealthcareOrganizationType::DENTAL_ORGANIZATION,
        HealthcareOrganizationType::OTHER_HEALTHCARE_ORGANIZATION,
    ];
    private const EVIDENCE_TYPES = ['NONE','BUSINESS_EMAIL','PHONE','REPRESENTATIVE_DOCUMENT','OTHER'];
    private const REJECTION_REASONS = ['EVIDENCE_INSUFFICIENT','ORGANIZATION_DUPLICATE','OWNERSHIP_CONFLICT','OTHER_REVIEW'];
    public const REVIEW_CAPABILITY = 'provider_claim_review';

    public function __construct(private PDO $pdo) {}

    public function submitExisting(?AuthenticatedAccessContext $actor, string $groupId,
        string $submissionKey, string $evidenceType = 'NONE'): array
    {
        $accountId = $this->actorId($actor);
        $groupId = $this->identifier($groupId, 'invalid_group_id');
        $key = $this->submissionKey($submissionKey);
        $evidence = $this->evidenceType($evidenceType);
        $hash = hash('sha256', json_encode(['existing',$groupId,$evidence], JSON_THROW_ON_ERROR));
        return $this->transaction(function () use ($accountId,$groupId,$key,$evidence,$hash): array {
            $this->lockActiveAccount($accountId);
            $replay = $this->replay($accountId,$key,$hash);
            if ($replay !== null) return $this->ownProjection($replay);
            $this->claimableOrganization($groupId);
            return $this->createClaim($accountId,$groupId,$key,$hash,$evidence);
        });
    }

    public function submitNewCandidate(?AuthenticatedAccessContext $actor, string $type, string $displayName,
        string $submissionKey, string $evidenceType = 'NONE'): array
    {
        $accountId = $this->actorId($actor);
        $type = $this->providerType($type);
        $name = trim(preg_replace('/\s+/u', ' ', $displayName) ?? '');
        if ($name === '' || mb_strlen($name) > 190 || preg_match('/[\x00-\x1F\x7F]/', $name)) {
            throw new HealthcareOrganizationClaimException('invalid_organization_name');
        }
        $canonical = mb_strtolower($name, 'UTF-8');
        $key = $this->submissionKey($submissionKey);
        $evidence = $this->evidenceType($evidenceType);
        $hash = hash('sha256', json_encode(['candidate',$type,$canonical,$evidence], JSON_THROW_ON_ERROR));
        $lockName = 'prov04b:'.substr(hash('sha256',$canonical),0,48);
        $lock = $this->pdo->prepare('SELECT GET_LOCK(:name,10)');
        $lock->execute(['name'=>$lockName]);
        if ((int)$lock->fetchColumn() !== 1) throw new HealthcareOrganizationClaimException('duplicate_screen_unavailable');
        try {
            return $this->transaction(function () use ($accountId,$type,$name,$canonical,$key,$evidence,$hash): array {
                $this->lockActiveAccount($accountId);
                $replay = $this->replay($accountId,$key,$hash);
                if ($replay !== null) return $this->ownProjection($replay);
                $duplicate = $this->pdo->prepare('SELECT group_id,status,merged_into_group_id FROM medical_groups
                    WHERE canonical_name=:canonical OR LOWER(TRIM(display_name))=:display
                    ORDER BY created_at,group_id LIMIT 1');
                $duplicate->execute(['canonical'=>$canonical,'display'=>$canonical]);
                $existing = $duplicate->fetch(PDO::FETCH_ASSOC);
                if (is_array($existing)) {
                    throw new HealthcareOrganizationClaimException('duplicate_review_required', [
                        'existing_group_id' => $existing['merged_into_group_id'] ?: $existing['group_id'],
                    ]);
                }
                $group = (new MedicalGroupsRepository($this->pdo))->upsertGroup([
                    'organization_type_key'=>$type, 'display_name'=>$name,
                    'canonical_name'=>$canonical, 'status'=>'pending',
                    'source'=>'user_submitted', 'created_by_user_id'=>$accountId,
                ]);
                return $this->createClaim($accountId,(string)$group['group_id'],$key,$hash,$evidence);
            });
        } finally {
            $this->pdo->prepare('SELECT RELEASE_LOCK(:name)')->execute(['name'=>$lockName]);
        }
    }

    public function cancel(?AuthenticatedAccessContext $actor, string $claimUuid): array
    {
        $accountId = $this->actorId($actor);
        return $this->transaction(function () use ($accountId,$claimUuid): array {
            $this->lockActiveAccount($accountId);
            $claim = $this->claim($claimUuid,true);
            if ($claim['claimant_account_id'] !== $accountId) throw new HealthcareOrganizationClaimException('claim_not_found');
            if ($claim['status'] !== 'PENDING') throw new HealthcareOrganizationClaimException('claim_terminal');
            $this->pdo->prepare("UPDATE healthcare_organization_claims SET status='CANCELED',decision_reason_code='CLAIMANT_CANCELED'
                WHERE claim_uuid=:id AND status='PENDING'")->execute(['id'=>$claimUuid]);
            $this->event($claimUuid,$accountId,'CANCELED','PENDING','CANCELED','CLAIMANT_CANCELED',null);
            return $this->ownProjection($this->claim($claimUuid,false));
        });
    }

    public function decide(?AuthenticatedAccessContext $actor, string $claimUuid, string $decision,
        string $reasonCode): array
    {
        $reviewer = $this->actorId($actor);
        if (!in_array($decision,['APPROVED','REJECTED'],true)
            || ($decision === 'APPROVED' && $reasonCode !== 'EVIDENCE_ACCEPTED')
            || ($decision === 'REJECTED' && !in_array($reasonCode,self::REJECTION_REASONS,true))) {
            throw new HealthcareOrganizationClaimException('invalid_decision');
        }
        return $this->transaction(function () use ($reviewer,$claimUuid,$decision,$reasonCode): array {
            // Lock the current grant and staff state for the whole decision.
            $this->requireReviewer($reviewer,true);
            // All competing approvals lock the same organization row first.
            $read = $this->claim($claimUuid,false);
            $this->claimableOrganization((string)$read['group_id'],true);
            $claim = $this->claim($claimUuid,true);
            if ($claim['group_id'] !== $read['group_id'] || $claim['status'] !== 'PENDING') {
                throw new HealthcareOrganizationClaimException('claim_terminal');
            }
            if ($reviewer === $claim['claimant_account_id']) {
                throw new HealthcareOrganizationClaimException('self_review_denied');
            }
            if ($decision === 'APPROVED') {
                $this->lockActiveAccount((string)$claim['claimant_account_id']);
                $owner = $this->pdo->prepare("SELECT membership_id FROM auth_account_memberships
                    WHERE entity_group_id=:group_id AND role_code='owner' AND status='active' LIMIT 1 FOR UPDATE");
                $owner->execute(['group_id'=>$claim['group_id']]);
                if ($owner->fetchColumn() !== false) {
                    throw new HealthcareOrganizationClaimException('active_owner_exists');
                }
                $this->establishOwner((string)$claim['claimant_account_id'],(string)$claim['group_id']);
            }
            $update = $this->pdo->prepare('UPDATE healthcare_organization_claims
                SET status=:status,reviewed_by_account_id=:reviewer,reviewed_at=NOW(6),decision_reason_code=:reason
                WHERE claim_uuid=:id AND status=\'PENDING\'');
            $update->execute(['status'=>$decision,'reviewer'=>$reviewer,'reason'=>$reasonCode,'id'=>$claimUuid]);
            if ($update->rowCount() !== 1) throw new HealthcareOrganizationClaimException('claim_terminal');
            $this->event($claimUuid,$reviewer,$decision,'PENDING',$decision,$reasonCode,null);
            return $this->reviewProjection($this->claim($claimUuid,false));
        });
    }

    public function readOwn(?AuthenticatedAccessContext $actor, string $claimUuid): array
    {
        $accountId = $this->actorId($actor);
        $this->activeAccount($accountId);
        $claim = $this->claim($claimUuid,false);
        if ($claim['claimant_account_id'] !== $accountId) throw new HealthcareOrganizationClaimException('claim_not_found');
        return $this->ownProjection($claim);
    }

    public function readForReview(?AuthenticatedAccessContext $actor, string $claimUuid): array
    {
        $reviewer = $this->actorId($actor);
        $this->requireReviewer($reviewer);
        $claim = $this->claim($claimUuid,false);
        return $this->reviewProjection($claim);
    }

    private function actorId(?AuthenticatedAccessContext $actor): string
    {
        if ($actor === null || $actor->principal()->accountStatus() !== 'active'
            || $actor->session()->state() !== 'active'
            || $actor->session()->expiresAt() <= new \DateTimeImmutable('now')
            || $actor->session()->principal()->accountId() !== $actor->accountId()) {
            throw new HealthcareOrganizationClaimException('authentication_required');
        }
        return $actor->accountId();
    }

    private function activeAccount(string $accountId): void
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM auth_accounts WHERE account_id=:id AND status='active'");
        $stmt->execute(['id'=>$accountId]);
        if ($stmt->fetchColumn() === false) throw new HealthcareOrganizationClaimException('authentication_required');
    }

    private function lockActiveAccount(string $accountId): void
    {
        $stmt = $this->pdo->prepare('SELECT status FROM auth_accounts WHERE account_id=:id FOR UPDATE');
        $stmt->execute(['id'=>$accountId]);
        if ($stmt->fetchColumn() !== 'active') throw new HealthcareOrganizationClaimException('authentication_required');
    }

    private function requireReviewer(string $accountId, bool $lock = false): void
    {
        try {
            $stmt = $this->pdo->prepare("SELECT 1 FROM auth_accounts a
                JOIN internal_staff s ON s.account_id=a.account_id AND s.status='ACTIVE'
                JOIN internal_operator_grants g ON g.account_id=a.account_id
                    AND g.status='ACTIVE' AND g.revoked_at IS NULL AND g.capability=:capability
                WHERE a.account_id=:id AND a.status='active' LIMIT 1".($lock?' FOR UPDATE':''));
            $stmt->execute(['capability'=>self::REVIEW_CAPABILITY,'id'=>$accountId]);
            if ($stmt->fetchColumn() !== false) return;
        } catch (PDOException) {
            // Missing internal-governance schema must fail closed.
        }
        throw new HealthcareOrganizationClaimException('review_forbidden');
    }

    private function claimableOrganization(string $groupId, bool $lock = false): array
    {
        $stmt = $this->pdo->prepare('SELECT group_id,organization_type_key,status FROM medical_groups
            WHERE group_id=:id'.($lock?' FOR UPDATE':''));
        $stmt->execute(['id'=>$groupId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row) || !in_array($row['organization_type_key'],self::PROVIDER_TYPES,true)
            || !in_array($row['status'],['pending','verified'],true)) {
            throw new HealthcareOrganizationClaimException('organization_not_claimable');
        }
        return $row;
    }

    private function createClaim(string $accountId,string $groupId,string $key,string $hash,string $evidence): array
    {
        $id = self::uuid();
        try {
            $stmt = $this->pdo->prepare("INSERT INTO healthcare_organization_claims
                (claim_uuid,claimant_account_id,group_id,requested_role,status,evidence_type,submission_key,request_hash)
                VALUES (:id,:account,:group_id,'owner','PENDING',:evidence,:submission_key,:request_hash)");
            $stmt->execute(['id'=>$id,'account'=>$accountId,'group_id'=>$groupId,'evidence'=>$evidence,
                'submission_key'=>$key,'request_hash'=>$hash]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') throw new HealthcareOrganizationClaimException('claim_pending_exists');
            throw $e;
        }
        $this->event($id,$accountId,'SUBMITTED',null,'PENDING',null,$key);
        return $this->ownProjection($this->claim($id,false));
    }

    private function establishOwner(string $accountId,string $groupId): void
    {
        $stmt = $this->pdo->prepare("SELECT membership_id,status FROM auth_account_memberships
            WHERE account_id=:account AND entity_group_id=:group_id AND role_code='owner'
              AND scope_code='organization' AND status IN ('pending','active','suspended') FOR UPDATE");
        $stmt->execute(['account'=>$accountId,'group_id'=>$groupId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) > 1 || ($rows !== [] && $rows[0]['status'] !== 'pending')) {
            throw new HealthcareOrganizationClaimException('owner_membership_conflict');
        }
        if ($rows !== []) {
            $this->pdo->prepare("UPDATE auth_account_memberships SET status='active',assignment_source='provider_claim_approval'
                WHERE membership_id=:id AND status='pending'")->execute(['id'=>$rows[0]['membership_id']]);
            return;
        }
        $this->pdo->prepare("INSERT INTO auth_account_memberships
            (membership_id,account_id,entity_group_id,role_code,scope_code,status,assignment_source)
            VALUES (:id,:account,:group_id,'owner','organization','active','provider_claim_approval')")
            ->execute(['id'=>self::uuid(),'account'=>$accountId,'group_id'=>$groupId]);
    }

    private function replay(string $accountId,string $key,string $hash): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM healthcare_organization_claims
            WHERE claimant_account_id=:account AND submission_key=:submission_key LIMIT 1');
        $stmt->execute(['account'=>$accountId,'submission_key'=>$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) return null;
        if (!hash_equals((string)$row['request_hash'],$hash)) {
            throw new HealthcareOrganizationClaimException('idempotency_conflict');
        }
        return $row;
    }

    private function claim(string $id,bool $lock): array
    {
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/iD',$id) !== 1) {
            throw new HealthcareOrganizationClaimException('claim_not_found');
        }
        $stmt = $this->pdo->prepare('SELECT * FROM healthcare_organization_claims WHERE claim_uuid=:id'.($lock?' FOR UPDATE':''));
        $stmt->execute(['id'=>$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) throw new HealthcareOrganizationClaimException('claim_not_found');
        return $row;
    }

    private function event(string $id,string $actor,string $action,?string $before,string $after,
        ?string $reason,?string $correlation): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO healthcare_organization_claim_events
            (claim_uuid,actor_account_id,action,previous_status,new_status,reason_code,correlation_key)
            VALUES (:id,:actor,:action,:before,:after,:reason,:correlation)');
        $stmt->execute(['id'=>$id,'actor'=>$actor,'action'=>$action,'before'=>$before,'after'=>$after,
            'reason'=>$reason,'correlation'=>$correlation]);
    }

    private function ownProjection(array $claim): array
    {
        return ['claim_uuid'=>$claim['claim_uuid'],'group_id'=>$claim['group_id'],
            'requested_role'=>$claim['requested_role'],'status'=>$claim['status'],
            'evidence_type'=>$claim['evidence_type'],'submitted_at'=>$claim['submitted_at'],
            'reviewed_at'=>$claim['reviewed_at']];
    }

    private function reviewProjection(array $claim): array
    {
        $events = $this->pdo->prepare('SELECT actor_account_id,action,previous_status,new_status,reason_code,
            correlation_key,created_at FROM healthcare_organization_claim_events WHERE claim_uuid=:id ORDER BY event_id');
        $events->execute(['id'=>$claim['claim_uuid']]);
        return $this->ownProjection($claim) + [
            'claimant_account_id'=>$claim['claimant_account_id'],
            'reviewed_by_account_id'=>$claim['reviewed_by_account_id'],
            'decision_reason_code'=>$claim['decision_reason_code'],
            'events'=>$events->fetchAll(PDO::FETCH_ASSOC),
        ];
    }

    private function transaction(callable $operation): array
    {
        if ($this->pdo->inTransaction()) throw new HealthcareOrganizationClaimException('nested_transaction_denied');
        $this->pdo->beginTransaction();
        try {
            $result = $operation();
            $this->pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    private function identifier(string $value,string $error): string
    {
        $value = trim($value);
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{1,63}$/D',$value) !== 1) {
            throw new HealthcareOrganizationClaimException($error);
        }
        return $value;
    }

    private function submissionKey(string $key): string
    {
        if (preg_match('/^[A-Za-z0-9._:-]{8,128}$/D',$key) !== 1) {
            throw new HealthcareOrganizationClaimException('invalid_submission_key');
        }
        return $key;
    }

    private function providerType(string $type): string
    {
        if (!in_array($type,self::PROVIDER_TYPES,true)) {
            throw new HealthcareOrganizationClaimException('invalid_provider_organization_type');
        }
        return $type;
    }

    private function evidenceType(string $type): string
    {
        if (!in_array($type,self::EVIDENCE_TYPES,true)) {
            throw new HealthcareOrganizationClaimException('invalid_evidence_type');
        }
        return $type;
    }

    private static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return substr($hex,0,8).'-'.substr($hex,8,4).'-'.substr($hex,12,4).'-'.substr($hex,16,4).'-'.substr($hex,20);
    }
}
