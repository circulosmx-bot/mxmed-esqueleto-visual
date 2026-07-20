<?php
declare(strict_types=1);

foreach (glob(__DIR__ . '/../contracts/*.php') as $file) require_once $file;
foreach (glob(__DIR__ . '/../repositories/*.php') as $file) require_once $file;
foreach (glob(__DIR__ . '/../adapters/*.php') as $file) require_once $file;
foreach (glob(__DIR__ . '/../services/*.php') as $file) require_once $file;

use Identity\Adapters\InMemoryIdentityNotificationAdapter;
use Identity\Adapters\RejectingIdentityNotificationAdapter;
use Identity\Contracts\AccountStatus;
use Identity\Contracts\Clock;
use Identity\Contracts\IdentityAccount;
use Identity\Contracts\PasswordHash;
use Identity\Contracts\PasswordPolicy;
use Identity\Contracts\RateLimitKeyHasher;
use Identity\Contracts\RateLimitOperation;
use Identity\Contracts\ReasonCode;
use Identity\Contracts\SystemClock;
use Identity\Repositories\AccountConsentRepository;
use Identity\Repositories\AccountCredentialRepository;
use Identity\Repositories\IdentityAccountRepository;
use Identity\Repositories\OneTimeTokenRepository;
use Identity\Services\CredentialAuthenticationService;
use Identity\Services\EmailVerificationService;
use Identity\Services\RateLimitService;
use Identity\Services\RecoveryService;
use Identity\Services\RegistrationService;

final class Gate4BTestClock implements Clock
{
    public function __construct(private DateTimeImmutable $time) {}
    public function now(): DateTimeImmutable { return $this->time; }
    public function advance(string $modifier): void { $this->time = $this->time->modify($modifier); }
}

function gate4bAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function execSql(PDO $pdo, string $file): void
{
    $sql = file_get_contents($file);
    if (!is_string($sql) || trim($sql) === '') throw new RuntimeException('empty migration: ' . basename($file));
    $pdo->exec($sql);
}

$host = getenv('MXMED_GATE4B_TEST_HOST') ?: '127.0.0.1';
$port = (int)(getenv('MXMED_GATE4B_TEST_PORT') ?: 3306);
$user = getenv('MXMED_GATE4B_TEST_USER') ?: 'root';
$pass = getenv('MXMED_GATE4B_TEST_PASS') ?: '';
$admin = new PDO("mysql:host={$host};port={$port}", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$database = 'mxmed_gate4b_test_' . getmypid() . '_' . bin2hex(random_bytes(3));
if (!str_starts_with($database, 'mxmed_gate4b_test_')) throw new RuntimeException('protected temporary database prefix mismatch');
$quotedDatabase = '`' . str_replace('`', '``', $database) . '`';
$admin->exec("CREATE DATABASE {$quotedDatabase} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

try {
    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $pdo->exec("CREATE TABLE profiles_doctors (doctor_id VARCHAR(64) NOT NULL PRIMARY KEY) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE medical_groups (group_id VARCHAR(64) NOT NULL PRIMARY KEY) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $identityDb = realpath(__DIR__ . '/../db/migrations');
    foreach ([
        $identityDb . '/2026_07_19_01_create_auth_accounts.sql',
        $identityDb . '/2026_07_19_02_create_auth_account_consents.sql',
        $identityDb . '/2026_07_19_03_create_auth_account_memberships.sql',
        $identityDb . '/2026_07_20_04_create_auth_account_credentials.sql',
        $identityDb . '/2026_07_20_05_create_auth_account_one_time_tokens.sql',
        $identityDb . '/2026_07_20_06_create_auth_rate_limit_buckets.sql',
    ] as $migration) execSql($pdo, $migration);

    $clock = new Gate4BTestClock(new DateTimeImmutable('2026-07-20 12:00:00', new DateTimeZone('UTC')));
    $accounts = new IdentityAccountRepository($pdo);
    $credentials = new AccountCredentialRepository($pdo);
    $consents = new AccountConsentRepository($pdo);
    $tokens = new OneTimeTokenRepository($pdo);
    $rateLimits = new RateLimitService($pdo, new RateLimitKeyHasher('gate4b-test-pepper-not-production'), $clock);
    $notifications = new InMemoryIdentityNotificationAdapter();
    $registration = new RegistrationService($pdo, $accounts, $credentials, $consents, $tokens, $rateLimits, $notifications, $clock);
    $verification = new EmailVerificationService($pdo, $accounts, $consents, $tokens, $rateLimits, $notifications, $clock);
    $authentication = new CredentialAuthenticationService($accounts, $credentials, $rateLimits, $clock);
    $recovery = new RecoveryService($pdo, $accounts, $credentials, $tokens, $rateLimits, $notifications, $clock);

    PasswordHash::assertAvailable();
    PasswordPolicy::assertValid('A secure phrase 123!', 'user@example.test');
    $hash = PasswordHash::hash('A secure phrase 123!');
    gate4bAssert(str_starts_with($hash, '$argon2id$'), 'Argon2id hash expected');
    gate4bAssert(PasswordHash::verify('A secure phrase 123!', $hash), 'positive password verification expected');
    gate4bAssert(!PasswordHash::verify('wrong secure phrase', $hash), 'negative password verification expected');
    gate4bAssert(!PasswordHash::needsRehash($hash), 'new hash should not require rehash');
    foreach (['short', 'A secure phrase 123!' . str_repeat('x', 120)] as $invalid) {
        try { PasswordPolicy::assertValid($invalid, 'user@example.test'); throw new RuntimeException('password policy accepted invalid length'); } catch (InvalidArgumentException) {}
    }
    try { PasswordPolicy::assertValid('user@example.test', 'user@example.test'); throw new RuntimeException('password equal to email accepted'); } catch (InvalidArgumentException) {}

    $registrationInput = [
        'email' => 'User@example.test', 'password' => 'A secure phrase 123!',
        'terms_accepted' => true, 'terms_version' => 'terms-v1',
        'privacy_notice_accepted' => true, 'privacy_notice_version' => 'privacy-v1',
    ];
    $registrationDecision = $registration->register($registrationInput, ['ip' => '198.51.100.10', 'device' => 'device-a']);
    gate4bAssert($registrationDecision->accepted(), 'registration accepted internally');
    gate4bAssert($registrationDecision->publicCode() === 'REGISTRATION_RECEIVED', 'registration response generic');
    $accountId = (string)$registrationDecision->accountId();
    $account = $accounts->findById($accountId);
    gate4bAssert(is_array($account) && $account['status'] === AccountStatus::PENDING_VERIFICATION, 'new account pending verification');
    gate4bAssert(is_array($credentials->findByAccountId($accountId)), 'credential created');
    gate4bAssert(count($notifications->messages()) === 1, 'verification notification captured in memory');
    $verificationToken = (string)$notifications->lastMessage()?->token();
    $verificationDecision = $verification->verify($verificationToken, ['ip' => '198.51.100.10', 'device' => 'device-a']);
    gate4bAssert($verificationDecision->verified(), 'verification activates eligible account');
    gate4bAssert($accounts->findById($accountId)['status'] === AccountStatus::ACTIVE, 'account active after verification');
    $verificationAgain = $verification->verify($verificationToken, ['ip' => '198.51.100.11']);
    gate4bAssert(!$verificationAgain->verified(), 'consumed verification token cannot be reused');

    $duplicate = $registration->register($registrationInput, ['ip' => '198.51.100.20', 'device' => 'device-b']);
    gate4bAssert($duplicate->accepted() && $duplicate->reasonCode() === ReasonCode::DUPLICATE_ACCOUNT, 'duplicate account response remains generic');
    gate4bAssert((int)$pdo->query("SELECT COUNT(*) FROM auth_accounts WHERE email_normalized = 'user@example.test'")->fetchColumn() === 1, 'duplicate did not create account');

    $authOk = $authentication->authenticate('USER@example.test', 'A secure phrase 123!', ['ip' => '198.51.100.30', 'device' => 'device-c']);
    gate4bAssert($authOk->isAllowed(), 'valid credentials allowed');
    gate4bAssert($authOk->publicCode() === 'AUTHENTICATION_PRINCIPAL_CANDIDATE', 'authentication returns principal candidate');
    gate4bAssert(is_array($authOk->candidate()?->internalArray()) && count($authOk->candidate()?->internalArray()) === 5, 'candidate contains minimal fields');
    gate4bAssert(!array_key_exists('password_hash', $authOk->candidate()?->internalArray() ?? []), 'candidate excludes password hash');
    $authBad = $authentication->authenticate('USER@example.test', 'Wrong phrase 123!', ['ip' => '198.51.100.40', 'device' => 'device-d']);
    gate4bAssert(!$authBad->isAllowed() && $authBad->publicCode() === 'INVALID_CREDENTIALS', 'invalid credentials generic');
    for ($i = 0; $i < 5; $i++) $authentication->authenticate('USER@example.test', 'Wrong phrase 123!', ['ip' => '198.51.100.50', 'device' => 'device-e']);
    $blockedAttempt = $authentication->authenticate('USER@example.test', 'Wrong phrase 123!', ['ip' => '198.51.100.50', 'device' => 'device-e']);
    gate4bAssert($blockedAttempt->reasonCode() === ReasonCode::RATE_LIMITED, 'sixth credential failure rate limited');

    $recoveryRequest = $recovery->request('USER@example.test', ['ip' => '198.51.100.60', 'device' => 'device-f']);
    gate4bAssert($recoveryRequest->publicCode() === 'RECOVERY_REQUEST_RECEIVED', 'recovery request anti-enumeration response');
    $oldRecoveryToken = (string)$notifications->lastMessage()?->token();
    $recovery->request('USER@example.test', ['ip' => '198.51.100.61', 'device' => 'device-g']);
    $newRecoveryToken = (string)$notifications->lastMessage()?->token();
    gate4bAssert($oldRecoveryToken !== $newRecoveryToken, 'new recovery request rotates token');
    $oldReset = $recovery->reset($oldRecoveryToken, 'A newer secure phrase 456!', ['ip' => '198.51.100.62']);
    gate4bAssert(!$oldReset->reset() && $oldReset->reasonCode() === ReasonCode::TOKEN_INVALIDATED, 'previous recovery token invalidated');
    $reset = $recovery->reset($newRecoveryToken, 'A newer secure phrase 456!', ['ip' => '198.51.100.63']);
    gate4bAssert($reset->reset() && $reset->credentialVersion() === 2, 'password reset increments credential version');
    $reuse = $recovery->reset($newRecoveryToken, 'Another secure phrase 789!', ['ip' => '198.51.100.64']);
    gate4bAssert(!$reuse->reset() && $reuse->reasonCode() === ReasonCode::TOKEN_CONSUMED, 'recovery token single use');

    $pendingRegistration = $registration->register([
        'email' => 'pending@example.test', 'password' => 'A pending secure phrase!',
        'terms_accepted' => true, 'terms_version' => 'terms-v1', 'privacy_notice_accepted' => true, 'privacy_notice_version' => 'privacy-v1',
    ], ['ip' => '198.51.100.70']);
    $pendingAuth = $authentication->authenticate('pending@example.test', 'A pending secure phrase!', ['ip' => '198.51.100.71']);
    gate4bAssert(!$pendingAuth->isAllowed() && $pendingAuth->reasonCode() === ReasonCode::ACCOUNT_NOT_ACTIVE, 'pending account cannot authenticate');
    gate4bAssert($pendingRegistration->accountId() !== null, 'pending test account created');

    $failedNotifications = new RejectingIdentityNotificationAdapter();
    $failedRegistration = new RegistrationService($pdo, $accounts, $credentials, $consents, $tokens, $rateLimits, $failedNotifications, $clock);
    $failed = $failedRegistration->register([
        'email' => 'notify-failure@example.test', 'password' => 'A notify failure phrase!',
        'terms_accepted' => true, 'terms_version' => 'terms-v1', 'privacy_notice_accepted' => true, 'privacy_notice_version' => 'privacy-v1',
    ], ['ip' => '198.51.100.80']);
    gate4bAssert(!$failed->accepted() && $failed->reasonCode() === ReasonCode::NOTIFICATION_UNAVAILABLE, 'notification failure is explicit internally');
    gate4bAssert($accounts->findById((string)$failed->accountId())['status'] === AccountStatus::PENDING_VERIFICATION, 'notification failure never activates account');

    $rollbackCount = 0;
    try { $registration->register(['email' => 'rollback@example.test', 'password' => 'rollback phrase 123!', 'terms_accepted' => true, 'terms_version' => 'v1', 'privacy_notice_accepted' => false, 'privacy_notice_version' => 'v1'], ['ip' => '198.51.100.90']); } catch (Throwable) { $rollbackCount++; }
    gate4bAssert((int)$pdo->query("SELECT COUNT(*) FROM auth_accounts WHERE email_normalized = 'rollback@example.test'")->fetchColumn() === 0, 'invalid registration leaves no partial account');
    gate4bAssert($rollbackCount === 0, 'validation failure remains controlled');

    $rawDimensionColumns = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'auth_rate_limit_buckets'")->fetchAll(PDO::FETCH_COLUMN);
    foreach (['ip_address', 'email', 'device_id', 'raw_dimension'] as $forbidden) gate4bAssert(!in_array($forbidden, $rawDimensionColumns, true), 'raw rate-limit dimension absent: ' . $forbidden);
    $hashLength = (int)$pdo->query("SELECT CHAR_LENGTH(dimension_key_hash) FROM auth_rate_limit_buckets LIMIT 1")->fetchColumn();
    gate4bAssert($hashLength === 64, 'rate-limit dimensions are HMAC-sized hashes');
    $tokenColumns = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'auth_account_one_time_tokens'")->fetchAll(PDO::FETCH_COLUMN);
    gate4bAssert(!in_array('token', $tokenColumns, true), 'clear token column absent');

    $gate4bDir = realpath(__DIR__ . '/../db/migrations');
    $rollbackFiles = [
        $gate4bDir . '/2026_07_20_06_rollback_auth_rate_limit_buckets.sql',
        $gate4bDir . '/2026_07_20_05_rollback_auth_account_one_time_tokens.sql',
        $gate4bDir . '/2026_07_20_04_rollback_auth_account_credentials.sql',
    ];
    foreach ($rollbackFiles as $migration) execSql($pdo, $migration);
    $remaining = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ('auth_account_credentials','auth_account_one_time_tokens','auth_rate_limit_buckets')")->fetchColumn();
    gate4bAssert($remaining === 0, 'Gate 4B rollback removes only new tables');
    $forwardFiles = [
        $gate4bDir . '/2026_07_20_04_create_auth_account_credentials.sql',
        $gate4bDir . '/2026_07_20_05_create_auth_account_one_time_tokens.sql',
        $gate4bDir . '/2026_07_20_06_create_auth_rate_limit_buckets.sql',
    ];
    foreach ($forwardFiles as $migration) execSql($pdo, $migration);
    $restored = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ('auth_account_credentials','auth_account_one_time_tokens','auth_rate_limit_buckets')")->fetchColumn();
    gate4bAssert($restored === 3, 'Gate 4B second forward restores tables');
    echo "Gate4B secure authentication/recovery tests PASS\n";
} finally {
    if (!str_starts_with($database, 'mxmed_gate4b_test_')) throw new RuntimeException('refusing DROP DATABASE outside protected prefix');
    $admin->exec("DROP DATABASE IF EXISTS {$quotedDatabase}");
}
