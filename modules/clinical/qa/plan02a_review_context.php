<?php
declare(strict_types=1);

// Explicit local Director setup only; never included by product entry points.
function plan02a_review_context(): void
{
    require_once __DIR__ . '/../../../api/_lib/db.php';
    $mysql = mxmed_load_db_config()['mysql'] ?? [];
    if (PHP_SAPI !== 'cli-server'
        || !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)
        || ($mysql['dbname'] ?? '') !== 'mxmed_director_review_lon07c'
        || !in_array($mysql['host'] ?? '', ['localhost', '127.0.0.1'], true)) {
        throw new RuntimeException('PLAN02A requires the verified local disposable Director');
    }
    // Preserve the established cohort and emergency/write-window authorities.
    // Only add the dedicated synthetic pair to this review process's allowlist.
    if (getenv('MXMED_CLINICAL_M6_COHORT_MODE') !== 'allowlist') {
        throw new RuntimeException('PLAN02A requires the existing review allowlist');
    }
    $pairs = (string)getenv('MXMED_CLINICAL_M6_COHORT_PAIRS');
    if (!in_array('1|p_plan02_review', preg_split('/[\r\n,;]+/', $pairs) ?: [], true)) {
        putenv('MXMED_CLINICAL_M6_COHORT_PAIRS=' . $pairs . ',1|p_plan02_review');
    }
}
