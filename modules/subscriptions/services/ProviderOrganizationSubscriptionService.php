<?php
declare(strict_types=1);

namespace Subscriptions\Services;

use Agenda\Contracts\HealthcareOrganizationType;
use Identity\Contracts\AuthenticatedAccessContext;
use PDO;
use RuntimeException;

require_once __DIR__.'/../../agenda/contracts/HealthcareOrganizationType.php';

final class ProviderOrganizationSubscriptionException extends RuntimeException
{
    public function __construct(public readonly string $reason) { parent::__construct($reason); }
}

/** Internal governed writer. No checkout, payment claim, or public HTTP route. */
final class ProviderOrganizationSubscriptionService
{
    public const ENTITY_TYPE='provider_organization';
    public const INTERNAL_CAPABILITY='provider_subscription_activate';
    private const PROVIDER_TYPES=[
        HealthcareOrganizationType::LABORATORY,HealthcareOrganizationType::DIAGNOSTIC_CENTER,
        HealthcareOrganizationType::CLINIC,HealthcareOrganizationType::HOSPITAL,
        HealthcareOrganizationType::DENTAL_ORGANIZATION,HealthcareOrganizationType::OTHER_HEALTHCARE_ORGANIZATION,
        HealthcareOrganizationType::MEDICAL_GROUP,
    ];

    public function __construct(private PDO $pdo) {}

    public function activateInternal(?AuthenticatedAccessContext $operator,string $groupId,
        string $planCode,string $billingPeriod): array
    {
        $operatorId=$this->operatorId($operator);
        return $this->transaction(function () use ($operatorId,$groupId,$planCode,$billingPeriod): array {
            $this->requireOperator($operatorId);
            $this->requireOrganizationWithGovernedOwner($groupId);
            $plan=$this->plan($planCode,$billingPeriod);
            $this->releaseElapsedSlot($groupId);
            $active=$this->pdo->prepare("SELECT subscription_id FROM profile_subscriptions
                WHERE entity_type='provider_organization' AND entity_id=? AND deleted_at IS NULL
                  AND status IN ('active','expiring_soon','grace_period') LIMIT 1 FOR UPDATE");
            $active->execute([$groupId]);
            if ($active->fetchColumn()!==false) throw new ProviderOrganizationSubscriptionException('active_provider_subscription_exists');
            $id=self::uuid();
            $start=new \DateTimeImmutable('now',new \DateTimeZone('UTC'));
            $end=$start->modify('+'.(int)$plan['duration_days'].' days');
            $this->pdo->prepare("INSERT INTO profile_subscriptions
                (subscription_id,entity_type,entity_id,doctor_id,profile_id,plan_code,plan_label,
                 billing_period,duration_days,contracted_plan_code,effective_plan_code,
                 starts_at,expires_at,status,auto_renew,source,contract_accepted_by_user_id)
                 VALUES (?,'provider_organization',?,NULL,NULL,?,?,?,?,?,?,?,?,'active',0,
                   'internal_provider_governance_v1',?)")
                ->execute([$id,$groupId,$planCode,$plan['plan_label'],$billingPeriod,(int)$plan['duration_days'],
                    $planCode,$planCode,$start->format('Y-m-d H:i:s'),$end->format('Y-m-d H:i:s'),$operatorId]);
            $this->event($id,$groupId,$operatorId,'ACTIVATED');
            return ['subscription_id'=>$id,'entity_type'=>self::ENTITY_TYPE,'entity_id'=>$groupId,
                'plan_code'=>$planCode,'status'=>'active','starts_at'=>$start->format('Y-m-d H:i:s'),
                'expires_at'=>$end->format('Y-m-d H:i:s')];
        });
    }

    public function cancelInternal(?AuthenticatedAccessContext $operator,string $groupId,string $subscriptionId): array
    {
        $operatorId=$this->operatorId($operator);
        return $this->transaction(function () use ($operatorId,$groupId,$subscriptionId): array {
            $this->requireOperator($operatorId);
            $this->requireOrganizationWithGovernedOwner($groupId);
            $stmt=$this->pdo->prepare("SELECT subscription_id,status FROM profile_subscriptions
                WHERE subscription_id=? AND entity_type='provider_organization' AND entity_id=?
                  AND deleted_at IS NULL FOR UPDATE");
            $stmt->execute([$subscriptionId,$groupId]);
            $row=$stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row) || !in_array($row['status'],['active','expiring_soon','grace_period'],true))
                throw new ProviderOrganizationSubscriptionException('subscription_not_cancellable');
            $this->pdo->prepare("UPDATE profile_subscriptions SET status='cancelled',cancelled_at=UTC_TIMESTAMP()
                WHERE subscription_id=?")->execute([$subscriptionId]);
            $this->event($subscriptionId,$groupId,$operatorId,'CANCELLED');
            return ['subscription_id'=>$subscriptionId,'entity_type'=>self::ENTITY_TYPE,
                'entity_id'=>$groupId,'status'=>'cancelled'];
        });
    }

    private function plan(string $code,string $period): array
    {
        $stmt=$this->pdo->prepare("SELECT plan_code,plan_label,billing_period,duration_days FROM subscription_plans
            WHERE plan_code=? AND billing_period=? AND product_family='PROVIDER_ORGANIZATION'
              AND is_active=1 AND duration_days>0 FOR UPDATE");
        $stmt->execute([$code,$period]);
        $plan=$stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($plan)) throw new ProviderOrganizationSubscriptionException('provider_plan_incompatible');
        return $plan;
    }

    private function requireOrganizationWithGovernedOwner(string $groupId): void
    {
        $stmt=$this->pdo->prepare('SELECT organization_type_key,status FROM medical_groups WHERE group_id=? FOR UPDATE');
        $stmt->execute([$groupId]);
        $group=$stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($group) || !in_array($group['organization_type_key'],self::PROVIDER_TYPES,true)
            || !in_array($group['status'],['pending','verified'],true))
            throw new ProviderOrganizationSubscriptionException('provider_organization_ineligible');
        if ($group['organization_type_key']===HealthcareOrganizationType::MEDICAL_GROUP) {
            $enabled=$this->pdo->prepare('SELECT 1 FROM healthcare_organization_provider_status WHERE group_id=?');
            $enabled->execute([$groupId]);
            if ($enabled->fetchColumn()===false) throw new ProviderOrganizationSubscriptionException('medical_group_not_provider_enabled');
        }
        $owner=$this->pdo->prepare("SELECT 1 FROM auth_account_memberships WHERE entity_group_id=?
            AND role_code='owner' AND status='active' AND scope_code='organization'
            AND assignment_source='provider_claim_approval' LIMIT 1 FOR UPDATE");
        $owner->execute([$groupId]);
        if ($owner->fetchColumn()===false) throw new ProviderOrganizationSubscriptionException('governed_owner_required');
    }

    private function releaseElapsedSlot(string $groupId): void
    {
        $this->pdo->prepare("UPDATE profile_subscriptions SET status='expired'
            WHERE entity_type='provider_organization' AND entity_id=? AND deleted_at IS NULL
              AND status IN ('active','expiring_soon','grace_period')
              AND ((status IN ('active','expiring_soon') AND expires_at<UTC_TIMESTAMP()
                     AND (grace_ends_at IS NULL OR grace_ends_at<UTC_TIMESTAMP()))
                   OR (status='grace_period' AND (grace_ends_at IS NULL OR grace_ends_at<UTC_TIMESTAMP())))")
            ->execute([$groupId]);
    }

    private function operatorId(?AuthenticatedAccessContext $actor): string
    {
        if ($actor===null || $actor->principal()->accountStatus()!=='active'
            || $actor->session()->state()!=='active'
            || $actor->session()->expiresAt()<=new \DateTimeImmutable('now')
            || $actor->session()->principal()->accountId()!==$actor->accountId())
            throw new ProviderOrganizationSubscriptionException('authentication_required');
        return $actor->accountId();
    }

    private function event(string $subscriptionId,string $groupId,string $actorId,string $action): void
    {
        $this->pdo->prepare('INSERT INTO provider_subscription_governance_events
            (subscription_id,group_id,actor_account_id,action) VALUES (?,?,?,?)')
            ->execute([$subscriptionId,$groupId,$actorId,$action]);
    }

    private function requireOperator(string $accountId): void
    {
        $stmt=$this->pdo->prepare("SELECT 1 FROM auth_accounts a
            JOIN internal_staff s ON s.account_id=a.account_id AND s.status='ACTIVE'
            JOIN internal_operator_grants g ON g.account_id=a.account_id AND g.capability=?
              AND g.status='ACTIVE' AND g.revoked_at IS NULL
            WHERE a.account_id=? AND a.status='active' LIMIT 1 FOR UPDATE");
        $stmt->execute([self::INTERNAL_CAPABILITY,$accountId]);
        if ($stmt->fetchColumn()===false) throw new ProviderOrganizationSubscriptionException('internal_governance_required');
    }

    private function transaction(callable $operation): array
    {
        if ($this->pdo->inTransaction()) throw new ProviderOrganizationSubscriptionException('nested_transaction_denied');
        $this->pdo->beginTransaction();
        try { $result=$operation(); $this->pdo->commit(); return $result; }
        catch (\Throwable $e) { if ($this->pdo->inTransaction()) $this->pdo->rollBack(); throw $e; }
    }

    private static function uuid(): string
    {
        $bytes=random_bytes(16);$bytes[6]=chr((ord($bytes[6])&0x0f)|0x40);$bytes[8]=chr((ord($bytes[8])&0x3f)|0x80);
        $hex=bin2hex($bytes);
        return substr($hex,0,8).'-'.substr($hex,8,4).'-'.substr($hex,12,4).'-'.substr($hex,16,4).'-'.substr($hex,20);
    }
}
