<?php
declare(strict_types=1);

use Identity\Contracts\AccountMembership;
use Identity\Contracts\AccountStatus;
use Identity\Contracts\CanonicalProfileReference;
use Identity\Contracts\ConsentDocumentType;
use Identity\Contracts\IdentityAccount;
use Identity\Contracts\MembershipRole;
use Identity\Contracts\MembershipStatus;
use Identity\Repositories\AccountConsentRepository;
use Identity\Repositories\AccountMembershipRepository;
use Identity\Repositories\IdentityAccountRepository;

require_once __DIR__ . '/../contracts/AccountStatus.php';
require_once __DIR__ . '/../contracts/MembershipStatus.php';
require_once __DIR__ . '/../contracts/ConsentDocumentType.php';
require_once __DIR__ . '/../contracts/MembershipRole.php';
require_once __DIR__ . '/../contracts/IdentityAccount.php';
require_once __DIR__ . '/../contracts/CanonicalProfileReference.php';
require_once __DIR__ . '/../contracts/AccountMembership.php';
require_once __DIR__ . '/../repositories/IdentityAccountRepository.php';
require_once __DIR__ . '/../repositories/AccountConsentRepository.php';
require_once __DIR__ . '/../repositories/AccountMembershipRepository.php';

function identityPersistenceAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function runIdentityPersistenceTest(PDO $pdo): void
{
    $accounts = new IdentityAccountRepository($pdo);
    $consents = new AccountConsentRepository($pdo);
    $memberships = new AccountMembershipRepository($pdo);

    $account = new IdentityAccount('acct_gate4a_01', 'User@example.test', AccountStatus::PENDING_VERIFICATION);
    $accounts->create($account);
    identityPersistenceAssert($accounts->existsByNormalizedEmail('user@example.test'), 'normalized email lookup works');

    try {
        $accounts->create(new IdentityAccount('acct_gate4a_02', 'USER@example.test'));
        throw new RuntimeException('case-insensitive unique email was not enforced');
    } catch (RuntimeException $e) {
        identityPersistenceAssert($e->getMessage() === 'identity_account_create_failed', 'duplicate email rejected by repository');
    }

    $consents->record('consent_gate4a_terms_1', $account->accountId(), ConsentDocumentType::TERMS, 'v1', '2026-07-19 12:00:00', ['locale' => 'es-MX']);
    $consents->record('consent_gate4a_privacy_1', $account->accountId(), ConsentDocumentType::PRIVACY_NOTICE, 'v1', '2026-07-19 12:00:00');
    try {
        $consents->record('consent_gate4a_terms_duplicate', $account->accountId(), ConsentDocumentType::TERMS, 'v1', '2026-07-19 12:00:01');
        throw new RuntimeException('duplicate consent version was not rejected');
    } catch (RuntimeException $e) {
        identityPersistenceAssert($e->getMessage() === 'identity_consent_record_failed', 'consent uniqueness enforced');
    }

    $memberships->create(new AccountMembership(
        'membership_gate4a_profile', $account->accountId(), CanonicalProfileReference::profile('doctor_gate4a_01'),
        MembershipRole::OWNER, 'profile', MembershipStatus::ACTIVE
    ));
    $memberships->create(new AccountMembership(
        'membership_gate4a_group', $account->accountId(), CanonicalProfileReference::organization('group_gate4a_01'),
        MembershipRole::COLLABORATOR, 'organization', MembershipStatus::ACTIVE
    ));
    identityPersistenceAssert(count($memberships->activeForAccount($account->accountId())) === 2, 'one account supports multiple memberships');

    try {
        $memberships->create(new AccountMembership(
            'membership_gate4a_duplicate', $account->accountId(), CanonicalProfileReference::profile('doctor_gate4a_01'),
            MembershipRole::OWNER, 'profile', MembershipStatus::ACTIVE
        ));
        throw new RuntimeException('duplicate active membership was not rejected');
    } catch (RuntimeException $e) {
        identityPersistenceAssert($e->getMessage() === 'identity_membership_create_failed', 'active membership uniqueness enforced');
    }

    $memberships->create(new AccountMembership(
        'membership_gate4a_revoked', $account->accountId(), CanonicalProfileReference::profile('doctor_gate4a_01'),
        MembershipRole::OWNER, 'profile', MembershipStatus::REVOKED
    ));
    identityPersistenceAssert(count($memberships->activeForAccount($account->accountId())) === 2, 'revoked membership grants no authority');

    $columns = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'auth_accounts'")->fetchAll(PDO::FETCH_COLUMN);
    foreach (['account_id', 'email_address', 'email_normalized', 'status', 'email_verified_at', 'created_at', 'updated_at'] as $column) {
        identityPersistenceAssert(in_array($column, $columns, true), "account column exists: {$column}");
    }
    foreach (['password', 'password_hash', 'mfa_secret', 'recovery_token', 'session_id', 'cookie'] as $forbidden) {
        identityPersistenceAssert(!in_array($forbidden, $columns, true), "sensitive account column absent: {$forbidden}");
    }
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $dsn = getenv('MXMED_GATE4A_TEST_DSN') ?: '';
    $user = getenv('MXMED_GATE4A_TEST_USER') ?: '';
    if ($dsn === '' || $user === '') throw new RuntimeException('isolated test DSN and user are required');
    runIdentityPersistenceTest(new PDO($dsn, $user, getenv('MXMED_GATE4A_TEST_PASS') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]));
    echo "IdentityPersistenceTest PASS\n";
}
