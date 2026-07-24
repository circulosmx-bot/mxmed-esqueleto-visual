<?php
declare(strict_types=1);

namespace Platform\Shadow;

use InvalidArgumentException;
use JsonException;

final class R0ShadowEvaluationHarness
{
    private const FIXTURE_VERSION = 1;

    private const ELIGIBLE_SURFACES = [
        'canonical_actor_authority',
        'canonical_schedule_read',
        'canonical_availability_compare',
        'canonical_appointment_lifecycle',
        'canonical_patient_identity',
    ];

    private const FIXTURE_KEYS = [
        'fixture_version',
        'surface',
        'correlation_ref',
        'scope_ref',
        'legacy',
        'canonical',
        'side_effects',
        'authority_audit_available',
    ];

    private const SNAPSHOT_KEYS = [
        'status',
        'headers',
        'payload',
        'outcome_code',
    ];

    private const SIDE_EFFECT_KEYS = [
        'canonical_write_attempted',
        'new_db_connection_attempted',
        'sql_or_ddl_attempted',
        'real_otp_attempted',
        'clinical_request_attempted',
        'scope_leakage_detected',
        'unexpected_side_effect',
    ];

    private const SIDE_EFFECT_HARD_STOPS = [
        'canonical_write_attempted' => 'CANONICAL_WRITE_ATTEMPTED',
        'new_db_connection_attempted' => 'NEW_DB_CONNECTION_ATTEMPTED',
        'sql_or_ddl_attempted' => 'SQL_OR_DDL_ATTEMPTED',
        'real_otp_attempted' => 'REAL_OTP_ATTEMPTED',
        'clinical_request_attempted' => 'CLINICAL_REQUEST_ATTEMPTED',
        'scope_leakage_detected' => 'SCOPE_LEAKAGE_DETECTED',
        'unexpected_side_effect' => 'UNEXPECTED_SIDE_EFFECT',
    ];

    private const FORBIDDEN_KEYS = [
        'patient_id',
        'appointment_id',
        'doctor_id',
        'name',
        'phone',
        'email',
        'address',
        'birthdate',
        'contact',
        'otp',
        'token',
        'diagnosis',
        'clinical',
        'notes',
        'cookies',
        'authorization',
        'password',
        'secret',
    ];

    public static function eligibleSurfaces(): array
    {
        return self::ELIGIBLE_SURFACES;
    }

    public static function fixtureSchema(): array
    {
        return [
            'fixture_version' => self::FIXTURE_VERSION,
            'fixture_keys' => self::FIXTURE_KEYS,
            'snapshot_keys' => self::SNAPSHOT_KEYS,
            'side_effect_keys' => self::SIDE_EFFECT_KEYS,
        ];
    }

    public function readiness(): array
    {
        return [
            'mode' => 'offline_deterministic',
            'sampling' => 0,
            'baseline_collection_authorized' => false,
            'observation_window_approved' => false,
            'rollout_stage' => 'R0',
            'rollout_mode' => 'disabled',
            'runtime_wired' => false,
            'real_traffic' => false,
        ];
    }

    public function evaluate(array $fixture): R0ShadowEvaluationResult
    {
        $this->assertFixtureVersion($fixture);
        $surface = $this->surface($fixture);
        $correlationRef = $this->testReference($fixture['correlation_ref'] ?? null);
        $scopeRef = $this->testReference($fixture['scope_ref'] ?? null);

        if ($this->containsForbiddenKey($fixture)) {
            return $this->privacyHardStop($surface, $correlationRef, $scopeRef);
        }

        $this->assertExactKeys($fixture, self::FIXTURE_KEYS, 'invalid_r0_shadow_fixture_keys');
        $legacy = $this->snapshot($fixture['legacy'] ?? null);
        $canonical = $this->snapshot($fixture['canonical'] ?? null);
        $sideEffects = $this->sideEffects($fixture['side_effects'] ?? null);
        if (!is_bool($fixture['authority_audit_available'] ?? null)) {
            throw new InvalidArgumentException('invalid_authority_audit_availability');
        }

        $legacyNormalized = $this->normalizedResponse($legacy);
        $canonicalNormalized = $this->normalizedResponse($canonical);
        $legacyDigest = $this->digest($legacyNormalized);
        $canonicalDigest = $this->digest($canonicalNormalized);

        foreach (self::SIDE_EFFECT_HARD_STOPS as $key => $hardStop) {
            if ($sideEffects[$key]) {
                return $this->hardStopResult(
                    $surface,
                    $legacy,
                    $canonical,
                    $correlationRef,
                    $scopeRef,
                    $legacyDigest,
                    $canonicalDigest,
                    $hardStop
                );
            }
        }

        if (
            $surface === 'canonical_actor_authority'
            && $fixture['authority_audit_available'] === false
        ) {
            return $this->hardStopResult(
                $surface,
                $legacy,
                $canonical,
                $correlationRef,
                $scopeRef,
                $legacyDigest,
                $canonicalDigest,
                'AUTHORITY_AUDIT_UNAVAILABLE'
            );
        }

        $statusChanged = $legacyNormalized['status'] !== $canonicalNormalized['status'];
        $headersChanged = $legacyNormalized['headers'] !== $canonicalNormalized['headers'];
        $payloadChanged = $legacyNormalized['payload'] !== $canonicalNormalized['payload'];
        $responseChanges = (int) $statusChanged + (int) $headersChanged + (int) $payloadChanged;

        if ($responseChanges > 1) {
            return $this->hardStopResult(
                $surface,
                $legacy,
                $canonical,
                $correlationRef,
                $scopeRef,
                $legacyDigest,
                $canonicalDigest,
                'LEGACY_RESPONSE_CHANGED'
            );
        }
        if ($statusChanged) {
            return $this->hardStopResult(
                $surface,
                $legacy,
                $canonical,
                $correlationRef,
                $scopeRef,
                $legacyDigest,
                $canonicalDigest,
                'HTTP_STATUS_CHANGED'
            );
        }
        if ($headersChanged) {
            return $this->hardStopResult(
                $surface,
                $legacy,
                $canonical,
                $correlationRef,
                $scopeRef,
                $legacyDigest,
                $canonicalDigest,
                'HTTP_HEADERS_CHANGED'
            );
        }
        if ($payloadChanged) {
            return $this->hardStopResult(
                $surface,
                $legacy,
                $canonical,
                $correlationRef,
                $scopeRef,
                $legacyDigest,
                $canonicalDigest,
                'PAYLOAD_CHANGED'
            );
        }

        return new R0ShadowEvaluationResult(
            $surface,
            $legacy['outcome_code'],
            $canonical['outcome_code'],
            'LEGACY_INVARIANT',
            $correlationRef,
            $scopeRef,
            $legacyDigest,
            $canonicalDigest,
            true,
            null,
            null
        );
    }

    private function assertFixtureVersion(array $fixture): void
    {
        if (($fixture['fixture_version'] ?? null) !== self::FIXTURE_VERSION) {
            throw new InvalidArgumentException('unsupported_r0_shadow_fixture_version');
        }
    }

    private function surface(array $fixture): string
    {
        $surface = $fixture['surface'] ?? null;
        if (!is_string($surface) || !in_array($surface, self::ELIGIBLE_SURFACES, true)) {
            throw new InvalidArgumentException('UNKNOWN_OPERATION');
        }
        return $surface;
    }

    private function testReference(mixed $reference): string
    {
        if (
            !is_string($reference)
            || preg_match('/\Atest:[a-z0-9][a-z0-9._:-]*\z/', $reference) !== 1
        ) {
            throw new InvalidArgumentException('invalid_r0_shadow_test_reference');
        }
        return $reference;
    }

    private function snapshot(mixed $snapshot): array
    {
        if (!is_array($snapshot)) {
            throw new InvalidArgumentException('invalid_r0_shadow_snapshot');
        }
        $this->assertExactKeys($snapshot, self::SNAPSHOT_KEYS, 'invalid_r0_shadow_snapshot_keys');
        if (!is_int($snapshot['status'])) {
            throw new InvalidArgumentException('invalid_r0_shadow_status');
        }
        if (!is_array($snapshot['headers'])) {
            throw new InvalidArgumentException('invalid_r0_shadow_headers');
        }
        if (
            !is_string($snapshot['outcome_code'])
            || preg_match('/\A[A-Z][A-Z0-9_]*\z/', $snapshot['outcome_code']) !== 1
        ) {
            throw new InvalidArgumentException('invalid_r0_shadow_outcome_code');
        }
        $this->normalizedHeaders($snapshot['headers']);
        $this->canonicalize($snapshot['payload']);
        return $snapshot;
    }

    private function sideEffects(mixed $sideEffects): array
    {
        if (!is_array($sideEffects)) {
            throw new InvalidArgumentException('invalid_r0_shadow_side_effects');
        }
        $this->assertExactKeys(
            $sideEffects,
            self::SIDE_EFFECT_KEYS,
            'invalid_r0_shadow_side_effect_keys'
        );
        foreach ($sideEffects as $value) {
            if (!is_bool($value)) {
                throw new InvalidArgumentException('invalid_r0_shadow_side_effect_value');
            }
        }
        return $sideEffects;
    }

    private function assertExactKeys(array $value, array $expected, string $reason): void
    {
        $actual = array_keys($value);
        sort($actual, SORT_STRING);
        $closed = $expected;
        sort($closed, SORT_STRING);
        if ($actual !== $closed) {
            throw new InvalidArgumentException($reason);
        }
    }

    private function containsForbiddenKey(array $value): bool
    {
        foreach ($value as $key => $nested) {
            if (is_string($key) && in_array(strtolower($key), self::FORBIDDEN_KEYS, true)) {
                return true;
            }
            if (is_array($nested) && $this->containsForbiddenKey($nested)) {
                return true;
            }
        }
        return false;
    }

    private function normalizedResponse(array $snapshot): array
    {
        return [
            'status' => $snapshot['status'],
            'headers' => $this->normalizedHeaders($snapshot['headers']),
            'payload' => $this->canonicalize($snapshot['payload']),
        ];
    }

    private function normalizedHeaders(array $headers): array
    {
        $normalized = [];
        foreach ($headers as $key => $value) {
            if (
                !is_string($key)
                || $key === ''
                || preg_match('/\A[A-Za-z0-9-]+\z/', $key) !== 1
                || !is_string($value)
            ) {
                throw new InvalidArgumentException('invalid_r0_shadow_header');
            }
            $lowerKey = strtolower($key);
            if (array_key_exists($lowerKey, $normalized)) {
                throw new InvalidArgumentException('duplicate_r0_shadow_header');
            }
            $normalized[$lowerKey] = $value;
        }
        ksort($normalized, SORT_STRING);
        return $normalized;
    }

    private function canonicalize(mixed $value): mixed
    {
        if ($value === null || is_bool($value) || is_int($value) || is_string($value)) {
            return $value;
        }
        if (is_float($value)) {
            if (!is_finite($value)) {
                throw new InvalidArgumentException('non_serializable_r0_shadow_payload');
            }
            return $value;
        }
        if (!is_array($value)) {
            throw new InvalidArgumentException('non_serializable_r0_shadow_payload');
        }
        if (array_is_list($value)) {
            return array_map(fn(mixed $item): mixed => $this->canonicalize($item), $value);
        }
        $normalized = [];
        foreach ($value as $key => $nested) {
            if (!is_string($key)) {
                throw new InvalidArgumentException('invalid_r0_shadow_payload_key');
            }
            $normalized[$key] = $this->canonicalize($nested);
        }
        ksort($normalized, SORT_STRING);
        return $normalized;
    }

    private function digest(array $response): string
    {
        try {
            $encoded = json_encode(
                $response,
                JSON_THROW_ON_ERROR
                    | JSON_PRESERVE_ZERO_FRACTION
                    | JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
            );
        } catch (JsonException) {
            throw new InvalidArgumentException('non_serializable_r0_shadow_payload');
        }
        return hash('sha256', $encoded);
    }

    private function privacyHardStop(
        string $surface,
        string $correlationRef,
        string $scopeRef
    ): R0ShadowEvaluationResult {
        $hardStop = 'PII_OR_CLINICAL_DATA_DETECTED';
        return new R0ShadowEvaluationResult(
            $surface,
            'LEGACY_REDACTED',
            'CANONICAL_REDACTED',
            $hardStop,
            $correlationRef,
            $scopeRef,
            hash('sha256', 'r0-shadow-redacted-legacy'),
            hash('sha256', 'r0-shadow-redacted-canonical'),
            false,
            $hardStop,
            new R0ShadowSafeReturnPlan($hardStop)
        );
    }

    private function hardStopResult(
        string $surface,
        array $legacy,
        array $canonical,
        string $correlationRef,
        string $scopeRef,
        string $legacyDigest,
        string $canonicalDigest,
        string $hardStop
    ): R0ShadowEvaluationResult {
        return new R0ShadowEvaluationResult(
            $surface,
            $legacy['outcome_code'],
            $canonical['outcome_code'],
            $hardStop,
            $correlationRef,
            $scopeRef,
            $legacyDigest,
            $canonicalDigest,
            false,
            $hardStop,
            new R0ShadowSafeReturnPlan($hardStop)
        );
    }
}
