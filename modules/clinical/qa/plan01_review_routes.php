<?php
declare(strict_types=1);

// Opt-in PHP development-router helper. Never included by product entry points.
// The Director router must return false for these routes, before fixture handlers,
// so the existing API performs authentication, ownership checks and persistence.
function plan01_review_canonical_route(string $path): bool
{
    $tasks = preg_match('~^/api/clinical/index\.php/(?:patients/[^/]+/longitudinal/tasks(?:/.*)?|longitudinal/follow-ups/agenda)$~', $path) === 1;
    $agenda = preg_match('~^/api/agenda/index\.php/(?:appointments(?:/.*)?|availability(?:/blocks)?|consultorios|schedule)$~', $path) === 1;
    if (!$tasks && !$agenda) {
        return false;
    }

    if (PHP_SAPI !== 'cli-server' || !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
        throw new RuntimeException('PLAN01 requires the local disposable review server');
    }
    require_once __DIR__ . '/../../../api/_lib/db.php';
    $config = mxmed_load_db_config();
    $mysql = $config['mysql'] ?? [];
    if (($mysql['dbname'] ?? '') !== 'mxmed_director_review_lon07c'
        || !in_array($mysql['host'] ?? '', ['localhost', '127.0.0.1'], true)) {
        throw new RuntimeException('PLAN01 requires the verified disposable review database');
    }

    // Request-local review opt-in only. Canonical session and write-window
    // admission still apply; global feature-gate defaults are unchanged.
    if ($tasks) {
        putenv('MXMED_LON06A_WRITE_ENABLED=1');
    }
    return true;
}
