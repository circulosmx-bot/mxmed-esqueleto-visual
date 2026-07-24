<?php
declare(strict_types=1);

$surfaces = [
    'canonical_actor_authority',
    'canonical_schedule_read',
    'canonical_availability_compare',
    'canonical_appointment_lifecycle',
    'canonical_patient_identity',
];

$categories = [
    'nominal',
    'boundary',
    'invalid_closed',
    'privacy_rejection',
    'hard_stop',
    'legacy_invariant',
    'outcome_difference_without_response_mutation',
    'deterministic_repeat',
];

$hardStops = [
    'canonical_actor_authority' => 'AUTHORITY_AUDIT_UNAVAILABLE',
    'canonical_schedule_read' => 'CANONICAL_WRITE_ATTEMPTED',
    'canonical_availability_compare' => 'NEW_DB_CONNECTION_ATTEMPTED',
    'canonical_appointment_lifecycle' => 'CLINICAL_REQUEST_ATTEMPTED',
    'canonical_patient_identity' => 'SCOPE_LEAKAGE_DETECTED',
];

$sideEffectBySurface = [
    'canonical_schedule_read' => 'canonical_write_attempted',
    'canonical_availability_compare' => 'new_db_connection_attempted',
    'canonical_appointment_lifecycle' => 'clinical_request_attempted',
    'canonical_patient_identity' => 'scope_leakage_detected',
];

$catalog = [];

foreach ($surfaces as $surface) {
    foreach ($categories as $category) {
        $referencePrefix = 'test:cut02c:' . $surface . ':' . $category;
        $sideEffects = [
            'canonical_write_attempted' => false,
            'new_db_connection_attempted' => false,
            'sql_or_ddl_attempted' => false,
            'real_otp_attempted' => false,
            'clinical_request_attempted' => false,
            'scope_leakage_detected' => false,
            'unexpected_side_effect' => false,
        ];
        $payload = [
            'synthetic_state' => 'ready',
            'synthetic_items' => ['alpha', 'beta'],
        ];
        $legacyOutcome = 'BASELINE_MATCH';
        $canonicalOutcome = 'BASELINE_MATCH';
        $expectedHarnessResult = 'PASS';
        $expectedHardStop = null;
        $fixtureVersion = 1;
        $authorityAuditAvailable = true;
        $status = 200;
        $headers = [
            'Content-Type' => 'application/json',
            'X-Synthetic-Mode' => 'offline',
        ];

        if ($category === 'boundary') {
            $status = 206;
            $headers = ['X-Synthetic-Boundary' => 'valid'];
            $payload = [
                'synthetic_nested' => [
                    'synthetic_list' => [0, 1, 2],
                    'synthetic_object' => [
                        'empty' => '',
                        'enabled' => false,
                        'limit' => 0,
                    ],
                ],
            ];
        }

        if ($category === 'invalid_closed') {
            $fixtureVersion = 2;
            $expectedHarnessResult = 'REJECTED';
        }

        if ($category === 'privacy_rejection') {
            $payload['patient_id'] = 'synthetic-forbidden-value';
            $expectedHarnessResult = 'FAIL';
            $expectedHardStop = 'PII_OR_CLINICAL_DATA_DETECTED';
        }

        if ($category === 'hard_stop') {
            $expectedHarnessResult = 'FAIL';
            $expectedHardStop = $hardStops[$surface];
            if ($surface === 'canonical_actor_authority') {
                $authorityAuditAvailable = false;
            } else {
                $sideEffects[$sideEffectBySurface[$surface]] = true;
            }
        }

        if ($category === 'outcome_difference_without_response_mutation') {
            $canonicalOutcome = 'CANONICAL_DIAGNOSTIC';
        }

        $legacy = [
            'status' => $status,
            'headers' => $headers,
            'payload' => $payload,
            'outcome_code' => $legacyOutcome,
        ];
        $canonical = [
            'status' => $status,
            'headers' => array_reverse($headers, true),
            'payload' => array_reverse($payload, true),
            'outcome_code' => $canonicalOutcome,
        ];

        $catalog[] = [
            'fixture_id' => 'cut02c:' . $surface . ':' . $category,
            'scenario_category' => $category,
            'expected_harness_result' => $expectedHarnessResult,
            'expected_hard_stop' => $expectedHardStop,
            'fixture' => [
                'fixture_version' => $fixtureVersion,
                'surface' => $surface,
                'correlation_ref' => $referencePrefix . ':correlation',
                'scope_ref' => $referencePrefix . ':scope',
                'legacy' => $legacy,
                'canonical' => $canonical,
                'side_effects' => $sideEffects,
                'authority_audit_available' => $authorityAuditAvailable,
            ],
        ];
    }
}

return $catalog;
