<?php
declare(strict_types=1);

require_once __DIR__ . '/../repositories/CurrentSubscriptionRepository.php';
require_once __DIR__ . '/../services/CurrentSubscriptionReadModelService.php';

use Subscriptions\Repositories\CurrentSubscriptionRepository;
use Subscriptions\Services\CurrentSubscriptionReadModelService;

function readModelAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$pdo = new \PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec(<<<'SQL'
CREATE TABLE subscription_plans (
    plan_code TEXT NOT NULL,
    plan_label TEXT NOT NULL,
    billing_period TEXT NOT NULL,
    duration_days INTEGER NOT NULL,
    is_active INTEGER NOT NULL DEFAULT 1,
    sort_order INTEGER NOT NULL DEFAULT 0,
    source TEXT NOT NULL DEFAULT 'test'
);
CREATE TABLE profile_subscriptions (
    subscription_id INTEGER PRIMARY KEY AUTOINCREMENT,
    entity_type TEXT NOT NULL,
    entity_id TEXT NOT NULL,
    doctor_id TEXT NULL,
    profile_id TEXT NULL,
    plan_code TEXT NULL,
    plan_label TEXT NULL,
    billing_period TEXT NULL,
    duration_days INTEGER NULL,
    contracted_plan_code TEXT NULL,
    effective_plan_code TEXT NULL,
    contract_version TEXT NULL,
    contract_accepted_at TEXT NULL,
    contract_accepted_by_user_id TEXT NULL,
    contract_acceptance_source TEXT NULL,
    starts_at TEXT NULL,
    expires_at TEXT NULL,
    grace_starts_at TEXT NULL,
    grace_ends_at TEXT NULL,
    status TEXT NOT NULL,
    auto_renew INTEGER NULL,
    cancelled_at TEXT NULL,
    renewed_from_subscription_id INTEGER NULL,
    renewed_to_subscription_id INTEGER NULL,
    source TEXT NULL,
    created_at TEXT NULL,
    updated_at TEXT NULL,
    deleted_at TEXT NULL
);
SQL);

$plans = [
    ['free', 'Gratuito', 'lifetime', 0],
    ['basic', 'Básico', 'annual', 365],
    ['standard', 'Estándar', 'annual', 365],
    ['optimum', 'Óptimo', 'annual', 365],
    ['professional', 'Profesional', 'annual', 365],
];
$insertPlan = $pdo->prepare('INSERT INTO subscription_plans (plan_code, plan_label, billing_period, duration_days) VALUES (?, ?, ?, ?)');
foreach ($plans as $plan) {
    $insertPlan->execute($plan);
}

$now = new \DateTimeImmutable('2026-07-19 12:00:00');
$repository = new CurrentSubscriptionRepository($pdo);
$service = new CurrentSubscriptionReadModelService($repository, $now);
$freeModel = $service->resolveForEntity('doctor', 'qa-free');
$freeAccess = $freeModel['feature_access'] ?? [];
readModelAssert(count($freeAccess) === 7, 'read-model should contain seven feature decisions');
readModelAssert(($freeAccess['profile_directory_basic']['available'] ?? false) === true, 'free profile should be available');
readModelAssert(($freeAccess['public_contact']['available'] ?? true) === false, 'free contact should be denied');
readModelAssert(!array_key_exists('reason_code', $freeAccess['profile_directory_basic']), 'read-model must not expose internal reason codes');

$insertSubscription = $pdo->prepare(<<<'SQL'
INSERT INTO profile_subscriptions (
    entity_type, entity_id, doctor_id, plan_code, plan_label, billing_period,
    duration_days, contracted_plan_code, effective_plan_code, status, starts_at,
    expires_at, source, created_at, updated_at
) VALUES ('doctor', ?, ?, ?, ?, 'annual', 365, ?, ?, 'active', ?, ?, 'qa', ?, ?)
SQL);
$insertSubscription->execute([
    'qa-optimum',
    'qa-optimum',
    'optimum',
    'Óptimo',
    'optimum',
    'optimum',
    '2026-07-01 00:00:00',
    '2027-07-01 00:00:00',
    '2026-07-01 00:00:00',
    '2026-07-01 00:00:00',
]);
$optimumModel = $service->resolveForEntity('doctor', 'qa-optimum');
$optimumAccess = $optimumModel['feature_access'] ?? [];
foreach (['patients', 'clinical_record', 'prescriptions'] as $capabilityId) {
    readModelAssert(($optimumAccess[$capabilityId]['available'] ?? false) === true, $capabilityId . ' should be available for optimum');
}

echo "CurrentSubscriptionFeatureAccessReadModelTest PASS\n";
