<?php
declare(strict_types=1);

namespace Identity\Http;

final class ProductiveIdentityHttpConfiguration
{
    private function __construct(
        private string $environment,
        private string $databaseHost,
        private int $databasePort,
        private string $databaseName,
        private string $databaseUser,
        private string $databasePassword,
        private string $pepper,
        private string $allowedOrigin,
        private string $emailProvider,
        private string $sesRegion,
        private string $emailFromAddress,
        private string $emailFromName,
        private ?string $emailReplyTo,
        private ?array $session
    ) {}

    public static function fromProcessEnvironment(string $expectedEnvironment): self
    {
        $names = [
            'APP_ENV','DB_HOST','DB_PORT','DB_NAME','DB_USERNAME','DB_PASSWORD','SESSION_SIGNING_KEY','MXMED_IDENTITY_ORIGIN',
            'SESSION_HOST','SESSION_PORT','SESSION_PREFIX','SESSION_IDLE_TTL','SESSION_ABSOLUTE_LIFETIME','SESSION_TOUCH_INTERVAL',
            'SESSION_MAX_ACTIVE','SESSION_TLS_REQUIRED','SESSION_LOCK_ENABLED','SESSION_LOCK_TIMEOUT_SECONDS','SESSION_LOCK_WAIT_MICROSECONDS',
            'SESSION_STORE_USERNAME','SESSION_STORE_PASSWORD',
            'MXMED_EMAIL_PROVIDER','MXMED_SES_REGION','MXMED_EMAIL_FROM_ADDRESS','MXMED_EMAIL_FROM_NAME','MXMED_EMAIL_REPLY_TO',
            'AWS_ACCESS_KEY_ID','AWS_SECRET_ACCESS_KEY','AWS_SESSION_TOKEN','AWS_PROFILE','AWS_SHARED_CREDENTIALS_FILE',
            'SMTP_USERNAME','SMTP_PASSWORD','MXMED_SMTP_USERNAME','MXMED_SMTP_PASSWORD','SES_SMTP_USERNAME','SES_SMTP_PASSWORD',
        ];
        $values = [];
        foreach ($names as $name) { $value = getenv($name); $values[$name] = $value === false ? '' : (string)$value; }
        $configuration = self::fromValues($expectedEnvironment, $values);
        if (!$configuration->sessionConfigured()) throw new \RuntimeException('identity_productive_configuration_unavailable');
        return $configuration;
    }

    /**
     * @param array<string, string> $values
     * Legacy MXMED_* aliases are accepted only for deterministic C2 unit compatibility;
     * the productive process reader uses the single AWS APP_ENV, DB_* and SESSION_* authority.
     */
    public static function fromValues(string $expectedEnvironment, array $values): self
    {
        $environment = strtolower(trim(self::first($values, 'APP_ENV', 'MXMED_ENVIRONMENT')));
        if (!in_array($environment, ['staging', 'production'], true) || $environment !== $expectedEnvironment) throw new \RuntimeException('identity_productive_configuration_unavailable');

        $host = trim(self::first($values, 'DB_HOST', 'MXMED_DB_HOST'));
        $port = self::first($values, 'DB_PORT', 'MXMED_DB_PORT');
        $database = trim(self::first($values, 'DB_NAME', 'MXMED_DB_NAME'));
        $user = trim(self::first($values, 'DB_USERNAME', 'MXMED_DB_USER'));
        $password = self::first($values, 'DB_PASSWORD', 'MXMED_DB_PASS');
        $pepper = self::first($values, 'SESSION_SIGNING_KEY', 'MXMED_IDENTITY_PEPPER');
        $origin = self::normalizeHttpsOrigin((string)($values['MXMED_IDENTITY_ORIGIN'] ?? ''));
        $emailProvider = strtolower(trim((string)($values['MXMED_EMAIL_PROVIDER'] ?? '')));
        $sesRegion = trim((string)($values['MXMED_SES_REGION'] ?? ''));
        $emailFromAddress = strtolower(trim((string)($values['MXMED_EMAIL_FROM_ADDRESS'] ?? '')));
        $emailFromName = trim((string)($values['MXMED_EMAIL_FROM_NAME'] ?? ''));
        $emailReplyToValue = trim((string)($values['MXMED_EMAIL_REPLY_TO'] ?? ''));
        $emailReplyTo = $emailReplyToValue === '' ? null : $emailReplyToValue;
        if (
            preg_match('/^[A-Za-z0-9.:-]+$/D', $host) !== 1
            || !ctype_digit($port) || (int)$port < 1 || (int)$port > 65535
            || preg_match('/^[A-Za-z0-9_]+$/D', $database) !== 1 || str_starts_with($database, 'mxmed_gate4d_preview_')
            || $user === '' || $password === '' || strlen($pepper) < 32
            || $emailProvider !== 'ses' || $sesRegion !== 'us-east-1'
            || $emailFromAddress !== 'no-reply@mexicomedico.com'
            || self::emailDomain($emailFromAddress) !== 'mexicomedico.com'
            || $emailFromName !== 'México Médico'
            || ($emailReplyTo !== null && filter_var($emailReplyTo, FILTER_VALIDATE_EMAIL) === false)
            || self::forbiddenCredentialConfigurationPresent($values)
        ) throw new \RuntimeException('identity_productive_configuration_unavailable');

        $session = null;
        if ((string)($values['SESSION_HOST'] ?? '') !== '') $session = self::sessionValues($environment, $values);
        return new self(
            $environment, $host, (int)$port, $database, $user, $password, $pepper, $origin,
            $emailProvider, $sesRegion, $emailFromAddress, $emailFromName, $emailReplyTo, $session
        );
    }

    public function environment(): string { return $this->environment; }
    public function databaseHost(): string { return $this->databaseHost; }
    public function databasePort(): int { return $this->databasePort; }
    public function databaseName(): string { return $this->databaseName; }
    public function databaseUser(): string { return $this->databaseUser; }
    public function databasePassword(): string { return $this->databasePassword; }
    public function pepper(): string { return $this->pepper; }
    public function allowedOrigin(): string { return $this->allowedOrigin; }
    public function emailProvider(): string { return $this->emailProvider; }
    public function sesRegion(): string { return $this->sesRegion; }
    public function emailFromAddress(): string { return $this->emailFromAddress; }
    public function emailFromName(): string { return $this->emailFromName; }
    public function emailReplyTo(): ?string { return $this->emailReplyTo; }
    public function sessionConfigured(): bool { return $this->session !== null; }
    public function sessionHost(): string { return (string)$this->requiredSession('host'); }
    public function sessionPort(): int { return (int)$this->requiredSession('port'); }
    public function sessionPrefix(): string { return (string)$this->requiredSession('prefix'); }
    public function sessionUsername(): string { return (string)$this->requiredSession('username'); }
    public function sessionPassword(): string { return (string)$this->requiredSession('password'); }
    public function sessionStoreConfig(): array { if ($this->session === null) throw new \RuntimeException('identity_productive_configuration_unavailable'); return $this->session['store']; }

    public function databaseDsn(): string
    {
        return sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $this->databaseHost, $this->databasePort, $this->databaseName);
    }

    private static function sessionValues(string $environment, array $values): array
    {
        $host = trim((string)($values['SESSION_HOST'] ?? ''));
        $port = (string)($values['SESSION_PORT'] ?? '');
        $prefix = (string)($values['SESSION_PREFIX'] ?? '');
        $username = trim((string)($values['SESSION_STORE_USERNAME'] ?? ''));
        $password = (string)($values['SESSION_STORE_PASSWORD'] ?? '');
        $expectedPrefix = $environment === 'production' ? 'mxmed:prd:session:' : 'mxmed:stg:session:';
        if (
            preg_match('/^[A-Za-z0-9.-]{1,253}$/D', $host) !== 1 || filter_var($host, FILTER_VALIDATE_IP) !== false
            || $port !== '6379' || $prefix !== $expectedPrefix
            || (string)($values['SESSION_IDLE_TTL'] ?? '') !== '3600'
            || (string)($values['SESSION_ABSOLUTE_LIFETIME'] ?? '') !== '43200'
            || (string)($values['SESSION_TOUCH_INTERVAL'] ?? '') !== '300'
            || (string)($values['SESSION_MAX_ACTIVE'] ?? '') !== '5'
            || strtolower((string)($values['SESSION_TLS_REQUIRED'] ?? '')) !== 'true'
            || strtolower((string)($values['SESSION_LOCK_ENABLED'] ?? '')) !== 'true'
            || (string)($values['SESSION_LOCK_TIMEOUT_SECONDS'] ?? '') !== '10'
            || (string)($values['SESSION_LOCK_WAIT_MICROSECONDS'] ?? '') !== '100000'
            || preg_match('/^[A-Za-z0-9_.-]{1,128}$/D', $username) !== 1 || $password === ''
        ) throw new \RuntimeException('identity_productive_configuration_unavailable');
        return [
            'host'=>$host,'port'=>6379,'prefix'=>$prefix,'username'=>$username,'password'=>$password,
            'store'=>[
                'driver'=>'valkey','prefix'=>$prefix,'idle_ttl'=>3600,'absolute_ttl'=>43200,'touch_interval'=>300,
                'maximum_active'=>5,'tls_required'=>true,'lock_enabled'=>true,'lock_timeout_seconds'=>10,'lock_wait_microseconds'=>100000,
            ],
        ];
    }

    private function requiredSession(string $field): mixed
    {
        if ($this->session === null || !array_key_exists($field, $this->session)) throw new \RuntimeException('identity_productive_configuration_unavailable');
        return $this->session[$field];
    }

    private static function first(array $values, string $canonical, string $legacy): string
    {
        $current = (string)($values[$canonical] ?? '');
        $old = (string)($values[$legacy] ?? '');
        if ($current !== '' && $old !== '' && $current !== $old) throw new \RuntimeException('identity_productive_configuration_unavailable');
        return $current !== '' ? $current : $old;
    }

    private static function normalizeHttpsOrigin(string $origin): string
    {
        $origin = rtrim(trim($origin), '/');
        $parts = parse_url($origin);
        if (!is_array($parts) || strtolower((string)($parts['scheme'] ?? '')) !== 'https' || trim((string)($parts['host'] ?? '')) === '' || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment']) || (isset($parts['path']) && $parts['path'] !== '')) throw new \RuntimeException('identity_productive_configuration_unavailable');
        return $origin;
    }

    private static function emailDomain(string $address): string
    {
        $separator = strrpos($address, '@');
        return $separator === false ? '' : substr($address, $separator + 1);
    }

    /** @param array<string,string> $values */
    private static function forbiddenCredentialConfigurationPresent(array $values): bool
    {
        foreach ([
            'AWS_ACCESS_KEY_ID','AWS_SECRET_ACCESS_KEY','AWS_SESSION_TOKEN','AWS_PROFILE','AWS_SHARED_CREDENTIALS_FILE',
            'SMTP_USERNAME','SMTP_PASSWORD','MXMED_SMTP_USERNAME','MXMED_SMTP_PASSWORD','SES_SMTP_USERNAME','SES_SMTP_PASSWORD',
        ] as $name) {
            if (trim((string)($values[$name] ?? '')) !== '') return true;
        }
        return false;
    }
}
