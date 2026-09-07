<?php
declare(strict_types=1);
namespace Profiles\Services;
require_once __DIR__ . '/PublicProfilePlanCapabilities.php';

final class PublicProfileRequestContext
{
    /** Same localhost-only QA override used by the public profile page. */
    public static function devPlanOverride(array $params, array $server): ?string
    {
        $host = trim((string)($server['HTTP_HOST'] ?? $server['SERVER_NAME'] ?? ''));
        $host = trim(strtolower((string)preg_replace('/:\d+$/', '', $host)), '[]');
        if (!in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) return null;
        $raw = $params['mxmed_plan'] ?? null;
        if (!is_string($raw) || trim($raw) === '') return null;
        $code = PublicProfilePlanCapabilities::normalizePlanCode($raw);
        return in_array($code, ['free', 'basic', 'standard', 'optimum', 'professional'], true) ? $code : null;
    }
}
