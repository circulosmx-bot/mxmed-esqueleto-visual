<?php
declare(strict_types=1);

namespace Identity\Http;

use Identity\Adapters\ExistingCapabilityAuthorityAdapter;
use Identity\Adapters\PdoSessionAccountStateAdapter;
use Identity\Adapters\PreviewIdentityNotificationAdapter;
use Identity\Adapters\PreviewValkeyClient;
use Identity\Adapters\ProductiveValkeyClient;
use Identity\Adapters\RejectingSessionStoreAdapter;
use Identity\Adapters\SesIdentityNotificationAdapter;
use Identity\Audit\AuditProducerFailureSignal;
use Identity\Audit\BoundedBestEffortAuditEmitter;
use Identity\Audit\CanonicalAuditWriterAdapter;
use Identity\Audit\CanonicalIdentityAuditProducer;
use Identity\Audit\CanonicalSessionAuditProducer;
use Identity\Audit\EnvironmentAuthIdentifierAuditSecretProvider;
use Identity\Audit\HmacSha256AuthIdentifierAuditHasher;
use Identity\Audit\IdentityAuditReasonResolver;
use Identity\Audit\Mp01eEventScopePolicy;
use Identity\Audit\Contracts\AuditProducerFailureSignalPort;
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
use Platform\Repositories\PdoCanonicalAuditTransactionAdapter;
use Platform\Services\ActorContextFactory;
use Platform\Services\AuditV1PhysicalMapper;
use Platform\Services\AuditWriterContextBridge;
use Platform\Services\CanonicalAuditMetadataSanitizer;
use Platform\Services\CanonicalAuditPolicyRegistry;
use Platform\Services\CanonicalAuditSealer;
use Platform\Services\CanonicalAuditSerializer;
use Platform\Services\CanonicalAuditWriter;
use Platform\Services\CanonicalSourceRoutePolicy;
use Platform\Services\CoarseAuditUserAgentSummarizer;
use Platform\Services\CorrelatableOperationCatalog;
use Platform\Services\EnvironmentAuditSecretProvider;
use Platform\Services\HmacSha256AuditIpHasher;
use Platform\Services\RandomAuditUuidProvider;
use Platform\Services\RandomCorrelationIdProvider;
use Platform\Services\RandomRequestIdProvider;
use Platform\Services\RequestContextFactory;
use Platform\Services\SourceModuleCatalog;
use Platform\Services\SystemAuditUtcClock;
use Platform\Services\TrustedAuditContextValidator;
use Platform\Services\UuidV4ContextIdPolicy;
use Platform\Services\UuidV4Generator;
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
        private string $allowedOrigin,
        private ?CanonicalIdentityAuditProducer $identityAudit = null,
        private ?CanonicalSessionAuditProducer $sessionAudit = null,
        private ?RequestContextFactory $auditRequests = null,
        private ?ActorContextFactory $auditActors = null
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
        $clock = new SystemClock();
        $store = SessionStoreFactory::create('local', ['driver' => 'valkey', 'prefix' => 'mxmed:gate4d:preview:session:', 'explicit_preview_flag' => true], $valkey, $clock);

        return self::build(
            $pdo,
            $pepper,
            new PreviewIdentityNotificationAdapter((string)(getenv('MXMED_PREVIEW_NOTIFICATION_FILE') ?: '/tmp/mxmed-activity04-gate4d-http-integration-v2/notifications.json')),
            $store,
            $environment,
            $allowedOrigin,
            $clock
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

        $clock = new SystemClock();
        $store = new RejectingSessionStoreAdapter();
        if ($configuration->sessionConfigured()) {
            $client = new ProductiveValkeyClient(
                $configuration->sessionHost(),
                $configuration->sessionPort(),
                $configuration->sessionUsername(),
                $configuration->sessionPassword()
            );
            $store = SessionStoreFactory::create(
                $configuration->environment(),
                $configuration->sessionStoreConfig(),
                $client,
                $clock
            );
        }

        if (!class_exists(\Aws\SesV2\SesV2Client::class)) {
            throw new \RuntimeException('identity_productive_configuration_unavailable');
        }
        $sesClient = new \Aws\SesV2\SesV2Client([
            'version' => 'latest',
            'region' => $configuration->sesRegion(),
        ]);
        $notifications = new SesIdentityNotificationAdapter(
            $sesClient,
            $configuration->sesRegion(),
            $configuration->emailFromAddress(),
            $configuration->emailFromName(),
            $configuration->allowedOrigin(),
            $configuration->emailReplyTo()
        );

        [$identityAudit, $sessionAudit, $auditRequests, $auditActors] = self::buildAudit($pdo);
        return self::build(
            $pdo,
            $configuration->pepper(),
            $notifications,
            $store,
            $configuration->environment(),
            $configuration->allowedOrigin(),
            $clock,
            $identityAudit,
            $sessionAudit,
            $auditRequests,
            $auditActors
        );
    }

    private static function build(
        PDO $pdo,
        string $pepper,
        IdentityNotificationPort $notifications,
        SessionStorePort $store,
        string $environment,
        string $allowedOrigin,
        ?\Identity\Contracts\Clock $clock = null,
        ?CanonicalIdentityAuditProducer $identityAudit = null,
        ?CanonicalSessionAuditProducer $sessionAudit = null,
        ?RequestContextFactory $auditRequests = null,
        ?ActorContextFactory $auditActors = null
    ): self {
        $clock ??= new SystemClock();
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
        return new self($pdo, new CsrfTokenService($pepper, 900, $clock, $allowedOrigin), $registration, $verification, $authentication, $recovery, $sessions, $accounts, $credentials, $memberships, $tokens, $authorization, $environment, $allowedOrigin, $identityAudit, $sessionAudit, $auditRequests, $auditActors);
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
    public function identityAudit(): ?CanonicalIdentityAuditProducer { return $this->identityAudit; }
    public function sessionAudit(): ?CanonicalSessionAuditProducer { return $this->sessionAudit; }
    public function auditRequests(): ?RequestContextFactory { return $this->auditRequests; }
    public function auditActors(): ?ActorContextFactory { return $this->auditActors; }

    /** @return array{CanonicalIdentityAuditProducer,CanonicalSessionAuditProducer,RequestContextFactory,ActorContextFactory} */
    private static function buildAudit(PDO $pdo): array
    {
        $writer = new CanonicalAuditWriter(
            CanonicalAuditPolicyRegistry::canonical(),
            new CanonicalAuditMetadataSanitizer(),
            new TrustedAuditContextValidator(),
            new RandomAuditUuidProvider(),
            new SystemAuditUtcClock(),
            new HmacSha256AuditIpHasher(new EnvironmentAuditSecretProvider()),
            new CoarseAuditUserAgentSummarizer(),
            new PdoCanonicalAuditTransactionAdapter($pdo),
            new CanonicalAuditSealer(new CanonicalAuditSerializer()),
            new AuditV1PhysicalMapper()
        );
        $failureSignal = new class implements AuditProducerFailureSignalPort {
            public function signal(AuditProducerFailureSignal $signal): void
            {
                error_log(json_encode($signal->safePayload(), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            }
        };
        $emitter = new BoundedBestEffortAuditEmitter(
            new CanonicalAuditWriterAdapter($writer),
            new AuditWriterContextBridge(),
            $failureSignal,
            new Mp01eEventScopePolicy()
        );
        $identityProducer = new CanonicalIdentityAuditProducer(
            $emitter,
            new HmacSha256AuthIdentifierAuditHasher(new EnvironmentAuthIdentifierAuditSecretProvider()),
            new IdentityAuditReasonResolver()
        );
        $sessionProducer = new CanonicalSessionAuditProducer($emitter);
        $uuid = new UuidV4Generator();
        $requests = new RequestContextFactory(
            new RandomRequestIdProvider($uuid),
            new RandomCorrelationIdProvider($uuid),
            new UuidV4ContextIdPolicy(),
            new CorrelatableOperationCatalog(),
            new SourceModuleCatalog(),
            new CanonicalSourceRoutePolicy([
                '/api/identity/index.php/login',
                '/api/identity/index.php/logout',
                '/api/identity/index.php/session-rotate',
                '/api/identity/index.php/session-revoke',
                '/api/identity/index.php/password-reset',
                '/api/identity/index.php/registration-request',
                '/api/identity/index.php/email-verification',
            ], [], [], [])
        );
        return [$identityProducer, $sessionProducer, $requests, new ActorContextFactory()];
    }

    public static function registerAutoloader(): void
    {
        static $registered = false;
        if ($registered) return;
        $registered = true;
        $root = dirname(__DIR__, 2);
        spl_autoload_register(static function (string $class) use ($root): void {
            $prefixes = ['Identity\\' => $root . '/identity/', 'Subscriptions\\' => $root . '/subscriptions/', 'Platform\\' => $root . '/platform/'];
            foreach ($prefixes as $prefix => $base) {
                if (!str_starts_with($class, $prefix)) continue;
                $relative = str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
                $file = $base . $relative;
                if (is_file($file)) require_once $file;
            }
        });
    }
}
