<?php
declare(strict_types=1);
namespace Identity\Http;

/** Explicit local configuration. No productive or preview secret aliases. */
final class NormalLocalIdentityConfiguration
{
    private function __construct(private array $values) {}

    public static function fromProcessEnvironment(): self
    {
        $names = ['APP_ENV','MXMED_ENVIRONMENT','MXMED_PREVIEW_EXPLICIT','MXMED_NORMAL_LOCAL_IDENTITY_EXPLICIT',
            'MXMED_DB_HOST','MXMED_DB_PORT','MXMED_DB_NAME','MXMED_DB_USER','MXMED_DB_PASS',
            'MXMED_NORMAL_LOCAL_VALKEY_HOST','MXMED_NORMAL_LOCAL_VALKEY_PORT','MXMED_NORMAL_LOCAL_SESSION_PREFIX',
            'MXMED_NORMAL_LOCAL_IDENTITY_PEPPER','MXMED_NORMAL_LOCAL_IDENTITY_ORIGIN','MXMED_NORMAL_LOCAL_NOTIFICATION_FILE'];
        $values = [];
        foreach ($names as $name) $values[$name] = (string)(getenv($name) ?: '');
        return self::fromValues($values);
    }

    public static function fromValues(array $values): self
    {
        $v = [];
        foreach ($values as $key => $value) {
            if (!is_string($value)) throw new \RuntimeException('normal_local_configuration_unavailable');
            $v[$key] = $value;
        }
        if (($v['APP_ENV'] ?? '') !== 'local' || ($v['MXMED_NORMAL_LOCAL_IDENTITY_EXPLICIT'] ?? '') !== '1'
            || !in_array($v['MXMED_ENVIRONMENT'] ?? '', ['', 'local'], true)
            || !in_array($v['MXMED_PREVIEW_EXPLICIT'] ?? '', ['', '0'], true)) {
            throw new \RuntimeException('normal_local_configuration_unavailable');
        }
        foreach (['MXMED_DB_HOST'=>'127.0.0.1','MXMED_DB_PORT'=>'3306',
            'MXMED_NORMAL_LOCAL_VALKEY_HOST'=>'127.0.0.1','MXMED_NORMAL_LOCAL_SESSION_PREFIX'=>'mxmed:local:session:'] as $key=>$default) {
            if (($v[$key] ?? '') === '') $v[$key] = $default;
        }
        if (($v['MXMED_DB_NAME'] ?? '') !== 'mxmed') throw new \RuntimeException('normal_local_database_forbidden');
        if ($v['MXMED_DB_HOST'] !== '127.0.0.1' || $v['MXMED_NORMAL_LOCAL_VALKEY_HOST'] !== '127.0.0.1'
            || $v['MXMED_NORMAL_LOCAL_SESSION_PREFIX'] !== 'mxmed:local:session:'
            || ($v['MXMED_DB_USER'] ?? '') === '') throw new \RuntimeException('normal_local_configuration_unavailable');
        foreach (['MXMED_DB_PORT','MXMED_NORMAL_LOCAL_VALKEY_PORT'] as $key) {
            $port = $v[$key] ?? '';
            if (!ctype_digit($port) || (int)$port < 1 || (int)$port > 65535) throw new \RuntimeException('normal_local_configuration_unavailable');
        }
        // Reserved preview/productive defaults cannot accidentally become the normal-local store.
        if (in_array((int)$v['MXMED_NORMAL_LOCAL_VALKEY_PORT'], [6379,6384], true)) throw new \RuntimeException('normal_local_configuration_unavailable');
        if (strlen($v['MXMED_NORMAL_LOCAL_IDENTITY_PEPPER'] ?? '') < 32) throw new \RuntimeException('normal_local_secret_required');
        $origin = $v['MXMED_NORMAL_LOCAL_IDENTITY_ORIGIN'] ?? '';
        if ($origin !== 'https://localhost:8443') throw new \RuntimeException('normal_local_origin_required');
        $file = $v['MXMED_NORMAL_LOCAL_NOTIFICATION_FILE'] ?? '';
        // Private external directory only; no default notification file or implicit creation.
        $parent = realpath(dirname($file));
        $repo = realpath(dirname(__DIR__, 3));
        if (!str_starts_with($file, '/tmp/') || str_contains($file, '/gate4d') || $parent === false || !is_writable($parent)
            || ($repo !== false && ($parent === $repo || str_starts_with($parent, $repo.'/')))
            || is_link($file) || (fileperms($parent) & 0077) !== 0
            || (file_exists($file) && (!is_file($file) || (fileperms($file) & 0077) !== 0))) {
            throw new \RuntimeException('normal_local_notification_path_required');
        }
        $v['MXMED_DB_PASS'] ??= '';
        return new self($v);
    }
    public function value(string $name): string { return $this->values[$name] ?? ''; }
    public function dsn(): string { return 'mysql:host=127.0.0.1;port='.$this->value('MXMED_DB_PORT').';dbname=mxmed;charset=utf8mb4'; }
}
