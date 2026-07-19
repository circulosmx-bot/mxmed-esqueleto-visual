<?php
declare(strict_types=1);

namespace Subscriptions\Services;

require_once __DIR__ . '/../policy/MxmedPlanCapabilityPolicy.php';

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

final class MxmedCommercialLifecycleService
{
    private const LEGACY_STATUS_MAP = [
        'free_default' => 'free',
        'inactive' => 'draft',
        'expiring_soon' => 'active',
        'grace_period' => 'grace',
        'renewed' => 'superseded',
        'payment_pending' => 'pending_payment',
        'paid' => 'pending_activation',
    ];

    private DateTimeImmutable $now;

    public function __construct(?DateTimeImmutable $now = null)
    {
        $this->now = $now ?? new DateTimeImmutable('now');
    }

    public function resolve(array $subscription): array
    {
        $storedStatus = $this->normalizeStatus($subscription['status'] ?? null);
        $originalExpiresAt = $this->date($subscription['original_expires_at'] ?? ($subscription['expires_at'] ?? null));
        $voluntaryEnd = $this->boolean($subscription['voluntary_cancel_at_period_end'] ?? false)
            || ($storedStatus === 'cancelled' && $this->date($subscription['cancelled_at'] ?? null) !== null);
        $extension = $this->validatedExtension($subscription);

        $state = $storedStatus;
        $daysPastDue = null;
        if (
            $originalExpiresAt !== null
            && $this->now > $originalExpiresAt
            && !$voluntaryEnd
            && !in_array($storedStatus, ['cancelled', 'superseded', 'failed'], true)
        ) {
            $seconds = $this->now->getTimestamp() - $originalExpiresAt->getTimestamp();
            $daysPastDue = max(1, (int)ceil($seconds / 86400));
            $graceLimit = 15 + (int)$extension['approved_days'];
            if ($daysPastDue <= 3) {
                $state = 'past_due';
            } elseif ($daysPastDue <= $graceLimit) {
                $state = 'grace';
            } else {
                $state = 'restricted';
            }
        }

        $scheduled = $this->scheduledPlan($subscription);
        $scheduledEffectiveAt = $this->date($scheduled['effective_at']);
        $scheduledEffective = $scheduled['plan_code'] !== null
            && $scheduledEffectiveAt !== null
            && $this->now >= $scheduledEffectiveAt
            && $scheduled['status'] === 'scheduled';

        return [
            'state' => $state,
            'stored_state' => $storedStatus,
            'original_expires_at' => $this->format($originalExpiresAt),
            'days_past_due' => $daysPastDue,
            'grace_starts_at' => $this->format($originalExpiresAt),
            'grace_ends_at' => $originalExpiresAt !== null
                ? $this->format($originalExpiresAt->modify('+' . (15 + (int)$extension['approved_days']) . ' days'))
                : null,
            'restricted_at' => $originalExpiresAt !== null
                ? $this->format($originalExpiresAt->modify('+' . (15 + (int)$extension['approved_days']) . ' days'))
                : null,
            'extension' => $extension,
            'retry_resets_grace' => false,
            'scheduled_plan' => $scheduled['plan_code'],
            'scheduled_effective_at' => $scheduled['effective_at'],
            'scheduled_change_status' => $scheduled['status'],
            'scheduled_change_effective' => $scheduledEffective,
            'cancel_scheduled_change_allowed' => $scheduled['status'] === 'scheduled' && !$scheduledEffective,
            'replace_scheduled_change_allowed' => $scheduled['status'] === 'scheduled' && !$scheduledEffective,
        ];
    }

    public function validateExtension(string $type, int $days, string $approvalStatus): array
    {
        $type = strtolower(trim($type));
        $approvalStatus = strtolower(trim($approvalStatus));
        $maximum = $type === 'ordinary' ? 7 : ($type === 'exceptional' ? 15 : 0);
        if ($maximum === 0) {
            throw new InvalidArgumentException('grace_extension_type_invalid');
        }
        if ($approvalStatus !== 'approved') {
            throw new InvalidArgumentException('grace_extension_not_approved');
        }
        if ($days < 1 || $days > $maximum) {
            throw new InvalidArgumentException('grace_extension_days_invalid');
        }
        return [
            'type' => $type,
            'approved_days' => $days,
            'approval_status' => 'approved',
            'maximum_days' => $maximum,
            'changes_original_expiration' => false,
        ];
    }

    public function schedulePlanChange(
        string $currentPlan,
        string $targetPlan,
        DateTimeImmutable $effectiveAt,
        array $activeAddOns = []
    ): array {
        $currentRank = \Subscriptions\Policy\MxmedPlanCapabilityPolicy::planRank($currentPlan);
        $targetRank = \Subscriptions\Policy\MxmedPlanCapabilityPolicy::planRank($targetPlan);
        if ($currentRank === null || $targetRank === null) {
            throw new InvalidArgumentException('scheduled_plan_invalid');
        }
        if ($effectiveAt <= $this->now) {
            throw new InvalidArgumentException('scheduled_effective_at_invalid');
        }

        $incompatible = [];
        foreach ($activeAddOns as $addOnCode) {
            $eligibility = \Subscriptions\Policy\MxmedPlanCapabilityPolicy::addOnEligibility(
                (string)$addOnCode,
                $targetPlan
            );
            if (!($eligibility['eligible'] ?? false)) {
                $incompatible[] = [
                    'code' => (string)$addOnCode,
                    'status' => 'cancel_at_period_end',
                    'auto_renew' => false,
                    'data_preserved' => true,
                ];
            }
        }

        return [
            'current_plan' => \Subscriptions\Policy\MxmedPlanCapabilityPolicy::requirePlanCode($currentPlan, true),
            'scheduled_plan' => \Subscriptions\Policy\MxmedPlanCapabilityPolicy::requirePlanCode($targetPlan, true),
            'scheduled_effective_at' => $this->format($effectiveAt),
            'change_type' => $targetRank < $currentRank ? 'downgrade' : 'scheduled_change',
            'status' => 'scheduled',
            'automatic_refund' => false,
            'data_preserved' => true,
            'incompatible_addons' => $incompatible,
        ];
    }

    public function cancelScheduledChange(array $scheduled): array
    {
        return array_replace($scheduled, ['status' => 'cancelled']);
    }

    public function replaceScheduledChange(array $scheduled, string $targetPlan, DateTimeImmutable $effectiveAt): array
    {
        $replacement = $this->schedulePlanChange(
            (string)($scheduled['current_plan'] ?? 'free'),
            $targetPlan,
            $effectiveAt,
            []
        );
        $replacement['replaces_scheduled_plan'] = $scheduled['scheduled_plan'] ?? null;
        return $replacement;
    }

    private function validatedExtension(array $subscription): array
    {
        $days = (int)($subscription['grace_extension_days'] ?? 0);
        if ($days <= 0) {
            return [
                'type' => null,
                'approved_days' => 0,
                'approval_status' => null,
                'maximum_days' => 0,
                'changes_original_expiration' => false,
            ];
        }
        try {
            return $this->validateExtension(
                (string)($subscription['grace_extension_type'] ?? ''),
                $days,
                (string)($subscription['grace_extension_status'] ?? '')
            );
        } catch (InvalidArgumentException $e) {
            return [
                'type' => null,
                'approved_days' => 0,
                'approval_status' => 'invalid_fail_closed',
                'maximum_days' => 0,
                'changes_original_expiration' => false,
            ];
        }
    }

    private function scheduledPlan(array $subscription): array
    {
        $planCode = \Subscriptions\Policy\MxmedPlanCapabilityPolicy::normalizePlanCode(
            $subscription['scheduled_plan_code'] ?? null
        );
        $effectiveAt = $this->date($subscription['scheduled_effective_at'] ?? null);
        $status = strtolower(trim((string)($subscription['scheduled_change_status'] ?? '')));
        if ($planCode === null || $effectiveAt === null) {
            return ['plan_code' => null, 'effective_at' => null, 'status' => null];
        }
        if (!in_array($status, ['scheduled', 'cancelled', 'applied', 'replaced'], true)) {
            $status = 'scheduled';
        }
        return ['plan_code' => $planCode, 'effective_at' => $this->format($effectiveAt), 'status' => $status];
    }

    private function normalizeStatus($value): string
    {
        $status = strtolower(trim((string)($value ?? 'draft')));
        $status = self::LEGACY_STATUS_MAP[$status] ?? $status;
        return in_array($status, \Subscriptions\Policy\MxmedPlanCapabilityPolicy::commercialStates(), true)
            ? $status
            : 'draft';
    }

    private function date($value): ?DateTimeImmutable
    {
        $text = trim((string)($value ?? ''));
        if ($text === '') {
            return null;
        }
        try {
            return new DateTimeImmutable($text);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function format(?DateTimeInterface $date): ?string
    {
        return $date?->format('Y-m-d H:i:s');
    }

    private function boolean($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
    }
}
