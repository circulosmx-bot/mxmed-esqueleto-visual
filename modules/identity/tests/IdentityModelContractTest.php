<?php
declare(strict_types=1);

use Identity\Contracts\AccountMembership;
use Identity\Contracts\AccountStatus;
use Identity\Contracts\CanonicalProfileReference;
use Identity\Contracts\ConsentDocumentType;
use Identity\Contracts\IdentityAccount;
use Identity\Contracts\MembershipRole;
use Identity\Contracts\MembershipStatus;

require_once __DIR__ . '/../contracts/AccountStatus.php';
require_once __DIR__ . '/../contracts/MembershipStatus.php';
require_once __DIR__ . '/../contracts/ConsentDocumentType.php';
require_once __DIR__ . '/../contracts/MembershipRole.php';
require_once __DIR__ . '/../contracts/IdentityAccount.php';
require_once __DIR__ . '/../contracts/CanonicalProfileReference.php';
require_once __DIR__ . '/../contracts/AccountMembership.php';

function identityContractAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function identityContractThrows(callable $callback, string $message): void
{
    try { $callback(); } catch (InvalidArgumentException) { return; }
    throw new RuntimeException($message);
}

$account = new IdentityAccount('acct_gate4a_01', ' User@example.test ', AccountStatus::PENDING_VERIFICATION);
identityContractAssert($account->status() === AccountStatus::PENDING_VERIFICATION, 'account starts pending verification');
identityContractAssert($account->emailNormalized() === 'user@example.test', 'email is normalized');
identityContractThrows(fn() => new IdentityAccount('acct_gate4a_02', 'second@example.test', 'unknown'), 'unknown account status rejected');
identityContractThrows(fn() => new IdentityAccount('acct_gate4a_03', 'bad email'), 'invalid email rejected');

$profileReference = CanonicalProfileReference::profile('doctor_gate4a_01');
$organizationReference = CanonicalProfileReference::organization('group_gate4a_01');
$membership = new AccountMembership(
    'membership_gate4a_01',
    $account->accountId(),
    $profileReference,
    MembershipRole::OWNER,
    'profile',
    MembershipStatus::ACTIVE
);
identityContractAssert($membership->grantsAuthority(), 'only active membership grants authority');
identityContractAssert($organizationReference->isOrganization(), 'organization reference uses existing entity authority');
identityContractThrows(fn() => new AccountMembership('membership_gate4a_02', $account->accountId(), $profileReference, 'unknown', 'profile'), 'unknown role rejected');
identityContractThrows(fn() => new AccountMembership('membership_gate4a_03', $account->accountId(), $profileReference, MembershipRole::OWNER, ''), 'empty scope rejected');
identityContractThrows(fn() => ConsentDocumentType::assertValid('unknown'), 'unknown consent type rejected');

echo "IdentityModelContractTest PASS\n";
