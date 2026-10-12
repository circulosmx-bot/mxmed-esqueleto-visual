<?php
declare(strict_types=1);
namespace Subscriptions\Services;

/** Local review state only. Never a subscription, payment or Identity writer. */
final class NormalLocalReviewScenario
{
    public static function environmentAllowed(): bool
    {
        if (getenv('APP_ENV') !== 'local' || getenv('MXMED_NORMAL_LOCAL_IDENTITY_EXPLICIT') !== '1'
            || getenv('MXMED_DB_NAME') !== 'mxmed' || getenv('MXMED_DB_HOST') !== '127.0.0.1'
            || !in_array((string)getenv('MXMED_ENVIRONMENT'), ['', 'local'], true)
            || !in_array((string)getenv('MXMED_PREVIEW_EXPLICIT'), ['', '0'], true)) return false;
        if (PHP_SAPI === 'cli') return true;
        return ($_SERVER['REMOTE_ADDR'] ?? '') === '127.0.0.1'
            && ($_SERVER['HTTP_HOST'] ?? '') === 'localhost:8443'
            && in_array($_SERVER['HTTP_ORIGIN'] ?? '', ['', 'https://localhost:8443'], true);
    }

    private static function path(): string
    {
        if (!self::environmentAllowed()) throw new \RuntimeException('local_review_forbidden');
        $home = (string)getenv('HOME');
        if ($home === '' || $home[0] !== '/') throw new \RuntimeException('local_review_storage_unavailable');
        return $home.'/Library/Application Support/MXMED/local-identity-runtime/doctor-1-review-scenario.json';
    }

    private static function locked(callable $operation): mixed
    {
        $path = self::path(); $dir = dirname($path);
        if (is_link($dir) || !is_dir($dir) || (fileperms($dir) & 0077) !== 0
            || is_link($path) || is_link($path.'.lock')) throw new \RuntimeException('local_review_storage_unsafe');
        $old = umask(0077);
        try { $lock = fopen($path.'.lock', 'c'); } finally { umask($old); }
        if (!$lock || !flock($lock, LOCK_EX)) throw new \RuntimeException('local_review_storage_unavailable');
        try { return $operation($path); } finally { flock($lock, LOCK_UN); fclose($lock); }
    }

    public static function read(): ?array
    {
        return self::locked(static function(string $path): ?array {
            if (!file_exists($path)) return null;
            if ((fileperms($path) & 0077) !== 0) throw new \RuntimeException('local_review_storage_unsafe');
            $r = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($r) || ($r['entity_type'] ?? '') !== 'doctor' || ($r['entity_id'] ?? '') !== '1'
                || !in_array($r['plan'] ?? '', ['standard','optimum','professional'], true)
                || !preg_match('/^[a-f0-9]{64}$/D', $r['canonical_fingerprint'] ?? '')) throw new \RuntimeException('local_review_record_invalid');
            return $r;
        });
    }

    public static function select(string $plan, string $fingerprint, ?string $actorAccountId = null): void
    {
        if (!in_array($plan, ['real','standard','optimum','professional'], true)) throw new \InvalidArgumentException('unsupported_plan');
        if (!preg_match('/^[a-f0-9]{64}$/D', $fingerprint)) throw new \InvalidArgumentException('invalid_fingerprint');
        self::locked(static function(string $path) use ($plan,$fingerprint,$actorAccountId): void {
            if ($plan === 'real') { if (file_exists($path) && !unlink($path)) throw new \RuntimeException('reset_failed'); return; }
            $now = gmdate('c');
            $r = ['entity_type'=>'doctor','entity_id'=>'1','plan'=>$plan,'canonical_fingerprint'=>$fingerprint,
                'created_at'=>$now,'updated_at'=>$now,'actor_account_id'=>$actorAccountId];
            $tmp = tempnam(dirname($path), '.scenario-');
            if ($tmp === false) throw new \RuntimeException('local_review_storage_unavailable');
            try { chmod($tmp,0600); if (file_put_contents($tmp,json_encode($r,JSON_THROW_ON_ERROR)) === false
                || !rename($tmp,$path)) throw new \RuntimeException('local_review_storage_unavailable'); }
            finally { if (file_exists($tmp)) unlink($tmp); }
        });
    }
}
