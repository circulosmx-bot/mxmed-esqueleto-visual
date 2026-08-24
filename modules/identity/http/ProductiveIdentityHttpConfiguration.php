<?php
declare(strict_types=1);

namespace Identity\Http;

final class ProductiveIdentityHttpConfiguration
{
    private const REQUIRED_VARIABLES = [
        'MXMED_DB_HOST',
        'MXMED_DB_PORT',
        'MXMED_DB_NAME',
        'MXMED_DB_USER',
        'MXMED_DB_PASS',
        'MXMED_IDENTITY_PEPPER',
        'MXMED_IDENTITY_ORIGIN',
    ];

    private function __construct(
        private string $environment,
        private string $databaseHost,
        private int $databasePort,
        private string $databaseName,
        private string $databaseUser,
        private string $databasePassword,
        private string $pepper,
        private string $allowedOrigin
    ) {}

    public static function fromProcessEnvironment(string $expectedEnvironment): self
    {
        $values = ['MXMED_ENVIRONMENT' => (string)(getenv('MXMED_ENVIRONMENT') ?: '')];
        foreach (self::REQUIRED_VARIABLES as $name) {
            $value = getenv($name);
            $values[$name] = $value === false ? '' : (string)$value;
        }

        return self::fromValues($expectedEnvironment, $values);
    }

    /** @param array<string, string> $values */
    public static function fromValues(string $expectedEnvironment, array $values): self
    {
        $environment = strtolower(trim((string)($values['MXMED_ENVIRONMENT'] ?? '')));
        if (!in_array($environment, ['staging', 'production'], true) || $environment !== $expectedEnvironment) {
            throw new \RuntimeException('identity_productive_configuration_unavailable');
        }

        foreach (self::REQUIRED_VARIABLES as $name) {
            if (!array_key_exists($name, $values) || (string)$values[$name] === '') {
                throw new \RuntimeException('identity_productive_configuration_unavailable');
            }
        }

        $host = trim((string)$values['MXMED_DB_HOST']);
        $port = (string)$values['MXMED_DB_PORT'];
        $database = trim((string)$values['MXMED_DB_NAME']);
        $user = trim((string)$values['MXMED_DB_USER']);
        $password = (string)$values['MXMED_DB_PASS'];
        $pepper = (string)$values['MXMED_IDENTITY_PEPPER'];
        if (
            preg_match('/^[A-Za-z0-9.:-]+$/D', $host) !== 1
            || !ctype_digit($port)
            || (int)$port < 1
            || (int)$port > 65535
            || preg_match('/^[A-Za-z0-9_]+$/D', $database) !== 1
            || str_starts_with($database, 'mxmed_gate4d_preview_')
            || $user === ''
            || strlen($pepper) < 32
        ) {
            throw new \RuntimeException('identity_productive_configuration_unavailable');
        }

        $origin = self::normalizeHttpsOrigin((string)$values['MXMED_IDENTITY_ORIGIN']);

        return new self($environment, $host, (int)$port, $database, $user, $password, $pepper, $origin);
    }

    public function environment(): string { return $this->environment; }
    public function databaseHost(): string { return $this->databaseHost; }
    public function databasePort(): int { return $this->databasePort; }
    public function databaseName(): string { return $this->databaseName; }
    public function databaseUser(): string { return $this->databaseUser; }
    public function databasePassword(): string { return $this->databasePassword; }
    public function pepper(): string { return $this->pepper; }
    public function allowedOrigin(): string { return $this->allowedOrigin; }

    public function databaseDsn(): string
    {
        return sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $this->databaseHost,
            $this->databasePort,
            $this->databaseName
        );
    }

    private static function normalizeHttpsOrigin(string $origin): string
    {
        $origin = rtrim(trim($origin), '/');
        $parts = parse_url($origin);
        if (
            !is_array($parts)
            || strtolower((string)($parts['scheme'] ?? '')) !== 'https'
            || trim((string)($parts['host'] ?? '')) === ''
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || (isset($parts['path']) && $parts['path'] !== '')
        ) {
            throw new \RuntimeException('identity_productive_configuration_unavailable');
        }

        return $origin;
    }
}
