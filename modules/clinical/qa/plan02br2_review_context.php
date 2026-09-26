<?php
declare(strict_types=1);

// Optional local Director router setup only; never loaded by product entrypoints.
function plan02br2_review_context(): void
{
    require_once __DIR__ . '/plan02a_review_context.php';
    plan02a_review_context(); // Verifies loopback, effective disposable DB and cohort mode.
    $pairs = (string)getenv('MXMED_CLINICAL_M6_COHORT_PAIRS');
    if (!in_array('1|p_plan02ux_review', preg_split('/[\r\n,;]+/', $pairs) ?: [], true)) {
        putenv('MXMED_CLINICAL_M6_COHORT_PAIRS=' . $pairs . ',1|p_plan02ux_review');
    }
}
