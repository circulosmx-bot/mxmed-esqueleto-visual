<?php
declare(strict_types=1);

namespace Identity\Http;

use Identity\Adapters\ExistingCapabilityAuthorityAdapter;
use Identity\Adapters\PdoSessionAccountStateAdapter;
use Identity\Adapters\PreviewIdentityNotificationAdapter;
use Identity\Adapters\PreviewValkeyClient;
use Identity\Adapters\RejectingIdentityNotificationAdapter;
use Identity\Adapters\RejectingSessionStoreAdapter;
use Identity\Contracts\IdentityNotificationPort;
use Identity\Contracts\RateLimitKeyHasher;
use Identity\Contracts\SessionPolicy;
use Identity\Contracts\SessionStorePort;
use Identity\Contracts\SystemClock;
use Identity\Repositories\AccountConsentRepository;
use Identity\Repositories\AccountCredentialRepository;
use Identity\Repositories\AccountMembershipRepository;
use Identity\Repositories\IdentityAccountRepository;
use Identity\Repositories\OneTimeTokenRepository;
use Identity\Services\CredentialAuthenticationService;
use Identity\Services\EmailVerificationService;
use Identity\Services\FailClosedAuthorizationService;
use Identity\Services\RateLimitService;
use Identity\Services\RecoveryService;
use Identity\Services\RegistrationService;
use Identity\Services\SessionService;
use Identity\Services\SessionStoreFactory;
use Identity\Services\SessionTokenCodec;
use PDO;
use Subscriptions\Services\ExistingCapabilityAuthorityService;

final class IdentityHttpComposition
{
    private function __construct(
        private PDO $pdo,
        private CsrfTokenService $csrf,
        private RegistrationService $registration,
        private EmailVerificationService $verification,
        private CredentialAuthenticationService $authentication,
        private RecoveryService $recovery,
        private SessionService $sessions,
        private IdentityAccountRepository $accounts,
        private AccountCredentialRepository $credentials,
        private AccountMembershipRepository $memberships,
        private OneTimeTokenRepository $tokens,
        private FailClosedAuthorizationService $authorization,
        private string $environment,
        private string $allowedOrigin
    ) {}

    public static function preview(): self
    {
        self::registerAutoloader();
        $environment = strtolower((string)getenv('MXMED_ENVIRONMENT'));
        if (!in_array($environment, ['local', 'development'], true) || getenv('MXMED_PREVIEW_EXPLICIT') !== '1') throw new \RuntimeException('preview_composition_required');
        $pepper = (string)getenv('MXMED_PREVIEW_PEPPER');
        if ($pepper === '') throw new \RuntimeException('preview_pepper_required');
        $host = (string)(getenv('MXMED_DB_HOST') ?: '127.0.0.1');
        $port = (int)(getenv('MXMED_DB_PORT') ?: 3306);
        $database = (string)getenv('MXMED_DB_NAME');
        $user = (string)(getenv('MXMED_DB_USER') ?: 'root');
        $password = (string)(getenv('MXMED_DB_PASS') ?: '');
        if ($database === '' || !str_starts_with($database, 'mxmed_gate4d_preview_')) throw new \RuntimeException('preview_database_required');
        $pdo = new PDO("mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4", $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $pdo->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');

        $allowedOrigin = rtrim((string)(getenv('MXMED_PREVIEW_ORIGIN') ?: 'https://127.0.0.1:8140'), '/');
        $valkey = new PreviewValkeyClient('127.0.0.1', (int)(getenv('MXMED_SESSION_STORE_PORT') ?: 6384));
        $store = SessionStoreFactory::create('local', ['driver' => 'valkey', 'prefix' => 'mxmed:gate4d:preview:session:', 'explicit_preview_flag' => true], $valkey);

        return self::build(
            $pdo,
            $pepper,
            new PreviewIdentityNotificationAdapter((string)(getenv('MXMED_PREVIEW_NOTIFICATION_FILE') ?: '/tmp/mxmed-activity04-gate4d-http-integration-v2/notifications.json')),
            $store,
            $environment,
            $allowedOrigin
        );
    }

    public static function productive(ProductiveIdentityHttpConfiguration $configuration): self
    {
        self::registerAutoloader();
        if (!in_array($configuration->environment(), ['staging', 'production'], true)) {
            throw new \RuntimeException('identity_productive_configuration_unavailable');
        }

        $pdo = new PDO(
            $configuration->databaseDsn(),
            $configuration->databaseUser(),
            $configuration->databasePassword(),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        return self::build(
            $pdo,
            $configuration->pepper(),
            new RejectingIdentityNotificationAdapter(),
            new RejectingSessionStoreAdapter(),
            $configuration->environment(),
            $configuration->allowedOrigin()
        );
    }

    private static function build(
        PDO $pdo,
        string $pepper,
        IdentityNotificationPort $notifications,
        SessionStorePort $store,
        string $environment,
        string $allowedOrigin
    ): self {
        $clock = new SystemClock();
        $accounts = new IdentityAccountRepository($pdo);
        $credentials = new AccountCredentialRepository($pdo);
        $consents = new AccountConsentRepository($pdo);
        $memberships = new AccountMembershipRepository($pdo);
        $tokens = new OneTimeTokenRepository($pdo);
        $rateLimits = new RateLimitService($pdo, new RateLimitKeyHasher($pepper), $clock);
        $registration = new RegistrationService($pdo, $accounts, $credentials, $consents, $tokens, $rateLimits, $notifications, $clock);
        $verification = new EmailVerificationService($pdo, $accounts, $consents, $tokens, $rateLimits, $notifications, $clock);
        $authentication = new CredentialAuthenticationService($accounts, $credentials, $rateLimits, $clock);
        $recovery = new RecoveryService($pdo, $accounts, $credentials, $tokens, $rateLimits, $notifications, $clock);
        $sessions = new SessionService($store, new SessionTokenCodec($pepper), $clock, new SessionPolicy(), new PdoSessionAccountStateAdapter($accounts, $credentials));
        $capabilityAuthority = new ExistingCapabilityAuthorityAdapter(new ExistingCapabilityAuthorityService());
        $authorization = new FailClosedAuthorizationService($memberships, $capabilityAuthority);
        return new self($pdo, new CsrfTokenService($pepper), $registration, $verification, $authentication, $recovery, $sessions, $accounts, $credentials, $memberships, $tokens, $authorization, $environment, $allowedOrigin);
    }

    public function pdo(): PDO { return $this->pdo; }
    public function csrf(): CsrfTokenService { return $this->csrf; }
    public function registration(): RegistrationService { return $this->registration; }
    public function verification(): EmailVerificationService { return $this->verification; }
    public function authentication(): CredentialAuthenticationService { return $this->authentication; }
    public function recovery(): RecoveryService { return $this->recovery; }
    public function sessions(): SessionService { return $this->sessions; }
    public function accounts(): IdentityAccountRepository { return $this->accounts; }
    public function credentials(): AccountCredentialRepository { return $this->credentials; }
    public function memberships(): AccountMembershipRepository { return $this->memberships; }
    public function tokens(): OneTimeTokenRepository { return $this->tokens; }
    public function authorization(): FailClosedAuthorizationService { return $this->authorization; }
    public function environment(): string { return $this->environment; }
    public function allowedOrigin(): string { return $this->allowedOrigin; }

    public static function registerAutoloader(): void
    {
        static $registered = false;
        if ($registered) return;
        $registered = true;
        $root = dirname(__DIR__, 2);
        spl_autoload_register(static function (string $class) use ($root): void {
            $prefixes = ['Identity\\' => $root . '/identity/', 'Subscriptions\\' => $root . '/subscriptions/'];
            foreach ($prefixes as $prefix => $base) {
                if (!str_starts_with($class, $prefix)) continue;
                $relative = str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
                $file = $base . $relative;
                if (is_file($file)) require_once $file;
            }
        });
    }
}
