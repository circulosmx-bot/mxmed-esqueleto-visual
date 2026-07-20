<?php
declare(strict_types=1);

require_once __DIR__ . '/../contracts/SessionCapabilityAuthorityPort.php';
require_once __DIR__ . '/../../subscriptions/contracts/ExistingCapabilityDecision.php';
require_once __DIR__ . '/../../subscriptions/services/ExistingCapabilityAuthorityService.php';
require_once __DIR__ . '/../adapters/ExistingCapabilityAuthorityAdapter.php';

use Identity\Adapters\ExistingCapabilityAuthorityAdapter;
use Identity\Contracts\SessionCapabilityAuthorityPort;
use Subscriptions\Services\ExistingCapabilityAuthorityService;

function gate4cAdapterAssert(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }

$adapter = new ExistingCapabilityAuthorityAdapter(new ExistingCapabilityAuthorityService());
gate4cAdapterAssert($adapter instanceof SessionCapabilityAuthorityPort, 'adapter implements typed capability port');
$standard = $adapter->resolve('agenda_appointments', ['plan_code' => 'standard', 'subscription_status' => 'active', 'is_active' => true]);
gate4cAdapterAssert($standard->available() && $standard->reasonCode() === 'allowed', 'real authority allows standard agenda');
$basic = $adapter->resolve('agenda_appointments', ['plan_code' => 'basic', 'subscription_status' => 'active', 'is_active' => true]);
gate4cAdapterAssert(!$basic->available() && $basic->reasonCode() === 'plan_not_entitled', 'real authority denies basic agenda');
$optimum = $adapter->resolve('patients', ['plan_code' => 'optimum', 'subscription_status' => 'active', 'is_active' => true]);
gate4cAdapterAssert($optimum->available(), 'real authority allows optimum patients');
$missing = $adapter->resolve('agenda_appointments', []);
gate4cAdapterAssert(!$missing->available() && $missing->reasonCode() === 'context_missing', 'missing context fails closed');
$inactive = $adapter->resolve('patients', ['plan_code' => 'optimum', 'subscription_status' => 'inactive']);
gate4cAdapterAssert(!$inactive->available() && $inactive->reasonCode() === 'subscription_inactive', 'inactive subscription fails closed');
$unknown = $adapter->resolve('unknown_capability', ['plan_code' => 'professional', 'subscription_status' => 'active', 'is_active' => true]);
gate4cAdapterAssert(!$unknown->available() && $unknown->reasonCode() === 'unknown_capability', 'unknown capability fails closed');

echo "Gate4C ExistingCapabilityAuthorityAdapter tests PASS\n";
