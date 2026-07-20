<?php
declare(strict_types=1);

require_once __DIR__ . '/../services/ExistingCapabilityAuthorityService.php';

use Subscriptions\Services\ExistingCapabilityAuthorityService;

function capabilityAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$service = new ExistingCapabilityAuthorityService();

$free = $service->resolve('profile_directory_basic', [
    'plan_code' => 'free',
    'subscription_status' => 'free_default',
]);
capabilityAssert($free->available(), 'free profile directory should be allowed');
capabilityAssert($free->reasonCode() === 'allowed', 'allowed reason code expected');
capabilityAssert($free->publicArray()['capability_id'] === 'profile_directory_basic', 'public capability id expected');
capabilityAssert(!array_key_exists('reason_code', $free->publicArray()), 'public decision must not expose reason code');

$basicContact = $service->resolve('public_contact', [
    'plan_code' => 'basic',
    'subscription_status' => 'active',
    'is_active' => true,
]);
capabilityAssert($basicContact->available(), 'basic contact should be allowed');

$basicAgenda = $service->resolve('agenda_appointments', [
    'plan_code' => 'basic',
    'subscription_status' => 'active',
    'is_active' => true,
]);
capabilityAssert(!$basicAgenda->available(), 'basic agenda should be denied');
capabilityAssert($basicAgenda->reasonCode() === 'plan_not_entitled', 'plan denial reason expected');

$optimum = $service->resolveMany([
    'profile_directory_basic',
    'public_contact',
    'gallery',
    'agenda_appointments',
    'patients',
    'clinical_record',
    'prescriptions',
], [
    'plan_code' => 'optimum',
    'subscription_status' => 'active',
    'is_active' => true,
]);
capabilityAssert(count($optimum) === 7, 'minimum catalog should contain seven decisions');
foreach ($optimum as $decision) {
    capabilityAssert($decision->available(), 'optimum existing capabilities should be allowed');
}

$inactive = $service->resolve('patients', [
    'plan_code' => 'optimum',
    'subscription_status' => 'inactive',
]);
capabilityAssert(!$inactive->available(), 'inactive subscription must deny');
capabilityAssert($inactive->reasonCode() === 'subscription_inactive', 'inactive reason expected');

$missingContext = $service->resolve('profile_directory_basic', []);
capabilityAssert(!$missingContext->available(), 'missing context must deny');
capabilityAssert($missingContext->reasonCode() === 'context_missing', 'missing context reason expected');

$unknown = $service->resolve('assistant_ai', [
    'plan_code' => 'professional',
    'subscription_status' => 'active',
    'is_active' => true,
]);
capabilityAssert(!$unknown->available(), 'future capability must deny');
capabilityAssert($unknown->reasonCode() === 'unknown_capability', 'future capability should be unknown');

echo "ExistingCapabilityAuthorityServiceTest PASS\n";
