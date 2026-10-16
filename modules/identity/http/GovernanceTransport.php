<?php
declare(strict_types=1);
namespace Identity\Http;

/** A forwarded scheme alone is never evidence of TLS. The TLS terminator signs
 * the complete request with a private deployment key, after stripping client headers. */
final class GovernanceTransport
{
    public static function secure(array $server, string $body, string $key): bool
    {
        // PHP's development server never terminates TLS. An inherited HTTPS=on
        // environment value must not turn its cleartext socket into trusted TLS.
        if (PHP_SAPI !== 'cli-server' && strtolower((string)($server['HTTPS'] ?? '')) === 'on') return true;
        if (strlen($key) < 32 || !in_array($server['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) return false;
        $stamp = (string)($server['HTTP_X_MXMED_TLS_TIME'] ?? '');
        $nonce = (string)($server['HTTP_X_MXMED_TLS_NONCE'] ?? '');
        if (!ctype_digit($stamp) || abs(time() - (int)$stamp) > 30 || !preg_match('/^[a-f0-9]{32}$/D', $nonce)) return false;
        $parts = [$stamp, $nonce, (string)($server['REQUEST_METHOD'] ?? ''), (string)($server['REQUEST_URI'] ?? ''),
            (string)($server['HTTP_HOST'] ?? ''), (string)($server['HTTP_ORIGIN'] ?? ''),
            (string)($server['HTTP_REFERER'] ?? ''), (string)($server['HTTP_COOKIE'] ?? ''),
            (string)($server['HTTP_X_CSRF_TOKEN'] ?? ''), hash('sha256', $body)];
        return hash_equals(hash_hmac('sha256', implode("\n", $parts), $key),
            (string)($server['HTTP_X_MXMED_TLS_PROOF'] ?? ''));
    }
}
