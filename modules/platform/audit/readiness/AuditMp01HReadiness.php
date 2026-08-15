<?php
declare(strict_types=1);

namespace Platform\Audit\Readiness;

/**
 * Dormant MP01H readiness contract. It describes future activation work and
 * never performs writes, binding, migration, networking, or cutover.
 */
final class AuditMp01HReadiness
{
    public const SELF_SUBJECT_SCOPE_PAGINATION_COMPATIBLE =
        'REQUIRED_BEFORE_PRODUCTIVE_READ_WIRING';

    private const STATUSES = [
        'STATIC_READY',
        'IMPLEMENTED_DORMANT',
        'REQUIRES_RUNTIME_IMPLEMENTATION',
        'REQUIRES_SECRET_OR_PRIVILEGE',
        'REQUIRES_DB_EXECUTION',
        'REQUIRES_STAGING_E2E',
        'REQUIRES_DIRECTOR_AUTHORIZATION',
        'BLOCKED',
        'NOT_APPLICABLE',
    ];

    /** @return array<string,array<string,mixed>> */
    public static function readinessMatrix(): array
    {
        $rows = [
            'PERSISTENCE_SCHEMA' => self::row(
                'IMPLEMENTED_DORMANT',
                'MP01B versioned migrations and manifest; final R54 semantics published',
                'Execute only through a separately authorized migration procedure',
                'DB_SCHEMA_WRITE', true,
                'Disable dependent runtime; preserve valid audit history; schema reversal only from an approved backup/forward-fix plan'
            ),
            'D11_MIGRATION' => self::row(
                'REQUIRES_DB_EXECUTION',
                '2026_08_13_01_align_platform_audit_stream_heads_d11.sql is versioned and statically validated',
                'Single-execution DB authorization, identity, backup, deterministic precheck and postconditions',
                'DB_MIGRATION_EXECUTION', true,
                'Stop before runtime binding; prefer forward-fix; never delete valid history'
            ),
            'DB_PRIVILEGES' => self::row(
                'REQUIRES_SECRET_OR_PRIVILEGE',
                'MP01B least-privilege authority and writer/read port operations are published',
                'Provision separately scoped migration, writer and read principals; verify grants physically',
                'DB_PRIVILEGE_CHANGE', true,
                'Revoke newly granted privileges without changing persisted history'
            ),
            'DB_SECRETS' => self::row(
                'REQUIRES_SECRET_OR_PRIVILEGE',
                'Secret provider contracts and cursor HMAC behavior are published; values are intentionally absent',
                'Provision versioned secrets through the authorized runtime secret manager',
                'SECRET_PROVISIONING', true,
                'Revert provider version/binding; never disclose or embed secret values'
            ),
            'WRITER_RUNTIME_BINDING' => self::row(
                'REQUIRES_RUNTIME_IMPLEMENTATION',
                'CanonicalAuditWriter, transaction port and PDO adapter are implemented dormant',
                'Productive composition binding plus failure-signal observability after D11/grants/secrets',
                'REPO_IMPLEMENTATION_AND_RUNTIME_BINDING', true,
                'Disable writer binding; preserve already appended audit events'
            ),
            'REQUEST_CONTEXT_WIRING' => self::row(
                'REQUIRES_RUNTIME_IMPLEMENTATION',
                'MP01D trusted request/correlation/actor contracts are published dormant',
                'Front-controller/middleware composition using only trusted backend inputs',
                'REPO_IMPLEMENTATION_AND_RUNTIME_BINDING', true,
                'Remove middleware binding and restore prior request path'
            ),
            'IDENTITY_PRODUCER_WIRING' => self::row(
                'REQUIRES_RUNTIME_IMPLEMENTATION',
                'Eight MP01E identity event producers and policy are published dormant',
                'Wire each authoritative postcondition once after writer/context readiness',
                'REPO_IMPLEMENTATION_AND_RUNTIME_BINDING', true,
                'Disable producer binding; do not delete emitted history'
            ),
            'SESSION_PRODUCER_WIRING' => self::row(
                'REQUIRES_RUNTIME_IMPLEMENTATION',
                'Five MP01E session event producers and policy are published dormant',
                'Wire authoritative session lifecycle outcomes once after writer/context readiness',
                'REPO_IMPLEMENTATION_AND_RUNTIME_BINDING', true,
                'Disable producer binding; do not delete emitted history'
            ),
            'MP01F_PRODUCER_WIRING' => self::row(
                'REQUIRES_RUNTIME_IMPLEMENTATION',
                'Fifteen MP01F ownership/role/security/admin producers are published dormant',
                'Wire only physical authoritative flows; keep the sensitive-admin finite catalog fail-closed',
                'REPO_IMPLEMENTATION_AND_RUNTIME_BINDING', true,
                'Disable producer bindings; do not rewrite emitted history'
            ),
            'AUDIT_READ_REPOSITORY_ADAPTER' => self::row(
                'REQUIRES_RUNTIME_IMPLEMENTATION',
                'Bounded AuditReadRepositoryPort and deterministic keyset contract are published',
                'Implement least-privilege normalized reads with subject scope inside the paged query',
                'REPO_IMPLEMENTATION_AND_RUNTIME_BINDING', true,
                'Disable adapter/read surface; persistence remains intact'
            ),
            'SELF_SUBJECT_RESOLVER_ADAPTER' => self::row(
                'REQUIRES_RUNTIME_IMPLEMENTATION',
                'Fail-closed SelfSecuritySubjectResolverPort is published',
                self::SELF_SUBJECT_SCOPE_PAGINATION_COMPATIBLE,
                'REPO_IMPLEMENTATION_AND_RUNTIME_BINDING', true,
                'Disable resolver and self-timeline route; deny rather than widen scope'
            ),
            'AUDIT_READ_ROUTE_WIRING' => self::row(
                'REQUIRES_RUNTIME_IMPLEMENTATION',
                'MP01G authorization, filters, cursor, minimization and service are implemented dormant',
                'Productive route/controller authorization after repository and resolver adapters pass staging',
                'REPO_IMPLEMENTATION_AND_RUNTIME_BINDING', true,
                'Disable route/controller binding'
            ),
            'AUDIT_OF_READ_WIRING' => self::row(
                'REQUIRES_RUNTIME_IMPLEMENTATION',
                'Read intent is modeled as auditable with emission explicitly inactive',
                'Bind emission only after writer and read paths are stable and recursion is prevented',
                'REPO_IMPLEMENTATION_AND_RUNTIME_BINDING', true,
                'Disable audit-of-read emission without disabling authorized reads'
            ),
            'STAGING_E2E' => self::row(
                'REQUIRES_STAGING_E2E',
                'Static and isolated foundations are validated; productive composition is absent',
                'Execute the approved staging matrix after all runtime prerequisites',
                'STAGING_RUNTIME_EXECUTION', true,
                'Disable staging bindings and restore the pre-activation configuration'
            ),
            'ROLLBACK_READINESS' => self::row(
                'STATIC_READY',
                'Layered deterministic rollback boundaries are defined by MP01H',
                'Rehearse DB and runtime rollback before production go/no-go',
                'ROLLBACK_REHEARSAL', true,
                'Rollback policy itself is versioned; history deletion is never a normal rollback'
            ),
            'OBSERVABILITY' => self::row(
                'REQUIRES_RUNTIME_IMPLEMENTATION',
                'Required signals are enumerated without invented thresholds',
                'Implement metrics/logs/alerts and set authorized thresholds before cutover',
                'OBSERVABILITY_IMPLEMENTATION', true,
                'Remove/disable new telemetry binding without altering audit history'
            ),
            'PRODUCTION_CUTOVER' => self::row(
                'REQUIRES_DIRECTOR_AUTHORIZATION',
                'Static subsystem and activation plan are ready; productive prerequisites remain incomplete',
                'All prior activation steps, staging evidence, observation window and explicit go/no-go',
                'PRODUCTION_CUTOVER', true,
                'Runtime disable/code rollback first; schema/data actions require separate authority'
            ),
            'POST_CUTOVER_MONITORING' => self::row(
                'REQUIRES_RUNTIME_IMPLEMENTATION',
                'Signal catalog is defined but no productive telemetry is deployed by MP01H',
                'Operational ownership, dashboards, alert routing and observation protocol',
                'POST_CUTOVER_OPERATIONS', true,
                'Disable affected activation layer if go/no-go thresholds are breached'
            ),
        ];
        self::assertMatrix($rows);
        return $rows;
    }

    /** @return list<array<string,mixed>> */
    public static function activationSequence(): array
    {
        $steps = [
            self::step('A0', 'Freeze final static baseline', null, false, 'No runtime state changed'),
            self::step('A1', 'Provision and verify runtime secrets and least privileges', 'A0', true, 'Revert providers/grants'),
            self::step('A2', 'Execute D11 migration once', 'A1', true, 'Stop; use approved forward-fix/backup boundary'),
            self::step('A3', 'Verify D11 and persistence postconditions', 'A2', false, 'Do not continue on mismatch'),
            self::step('A4', 'Bind canonical writer runtime', 'A3', true, 'Disable writer binding'),
            self::step('A5', 'Bind trusted request/correlation/actor context', 'A4', true, 'Remove context composition'),
            self::step('A6', 'Wire identity and session producers', 'A5', true, 'Disable producer bindings'),
            self::step('A7', 'Wire MP01F producers', 'A6', true, 'Disable MP01F bindings'),
            self::step('A8', 'Bind read repository and self-subject resolver adapters', 'A7', true, 'Disable both adapters'),
            self::step('A9', 'Wire bounded audit read routes', 'A8', true, 'Disable read routes'),
            self::step('A10', 'Wire audit-of-read emission', 'A9', true, 'Disable audit-of-read emission'),
            self::step('A11', 'Execute staging E2E matrix', 'A10', true, 'Disable staging activation'),
            self::step('A12', 'Complete staging observation window', 'A11', true, 'Extend observation or roll back staging'),
            self::step('A13', 'Record production go/no-go', 'A12', true, 'NO-GO keeps production dormant'),
            self::step('A14', 'Perform controlled production cutover', 'A13', true, 'Runtime disable/code rollback first'),
            self::step('A15', 'Run post-cutover monitoring', 'A14', true, 'Trigger the affected layer rollback boundary'),
        ];
        foreach ($steps as $index => $step) {
            if ($step['code'] !== 'A' . $index) {
                throw new \LogicException('invalid_activation_sequence');
            }
        }
        return $steps;
    }

    /** @return array<string,array<string,mixed>> */
    public static function rollbackMatrix(): array
    {
        return [
            'MIGRATION' => ['code_rollback' => false, 'runtime_disable' => true, 'schema_rollback' => 'SEPARATE_AUTHORITY_OR_FORWARD_FIX', 'data_deletion' => false],
            'WRITER_BINDING' => ['code_rollback' => true, 'runtime_disable' => true, 'schema_rollback' => false, 'data_deletion' => false],
            'PRODUCER_WIRING' => ['code_rollback' => true, 'runtime_disable' => true, 'schema_rollback' => false, 'data_deletion' => false],
            'READ_ROUTE' => ['code_rollback' => true, 'runtime_disable' => true, 'schema_rollback' => false, 'data_deletion' => false],
            'AUDIT_OF_READ' => ['code_rollback' => true, 'runtime_disable' => true, 'schema_rollback' => false, 'data_deletion' => false],
            'STAGING' => ['code_rollback' => true, 'runtime_disable' => true, 'schema_rollback' => 'ONLY_IF_SEPARATELY_AUTHORIZED', 'data_deletion' => false],
            'PRODUCTION' => ['code_rollback' => true, 'runtime_disable' => true, 'schema_rollback' => 'ONLY_IF_SEPARATELY_AUTHORIZED', 'data_deletion' => false],
        ];
    }

    /** @return list<array<string,string>> */
    public static function secretRequirements(): array
    {
        return [
            ['class' => 'AUDIT_IP_HMAC', 'provider_contract' => 'Platform\\Contracts\\AuditSecretProvider', 'environment' => 'STAGING_AND_PRODUCTION', 'rotation' => 'VERSIONED', 'provisioned' => 'UNKNOWN'],
            ['class' => 'AUTH_IDENTIFIER_HMAC', 'provider_contract' => 'Identity\\Audit\\Contracts\\AuthIdentifierAuditSecretProvider', 'environment' => 'STAGING_AND_PRODUCTION', 'rotation' => 'NAMESPACE_AND_VERSION', 'provisioned' => 'UNKNOWN'],
            ['class' => 'AUDIT_READ_CURSOR_HMAC', 'provider_contract' => 'PRODUCTIVE_PROVIDER_REQUIRED_FOR_AuditReadCursorCodec', 'environment' => 'STAGING_AND_PRODUCTION', 'rotation' => 'VERSIONED_CUTOVER_REQUIRED', 'provisioned' => 'UNKNOWN'],
            ['class' => 'DATABASE_CREDENTIALS', 'provider_contract' => 'ENVIRONMENT_SECRET_PROVIDER_REQUIRED', 'environment' => 'MIGRATION_WRITER_AND_READ_PRINCIPALS', 'rotation' => 'SEPARATE_PRINCIPALS_AND_ROTATION', 'provisioned' => 'UNKNOWN'],
            ['class' => 'CANONICAL_EVENT_HASH', 'provider_contract' => 'NONE_PUBLISHED_SHA256_CHAIN', 'environment' => 'NOT_APPLICABLE', 'rotation' => 'FUTURE_POLICY_REQUIRED_FOR_VERSION_CHANGE', 'provisioned' => 'NOT_APPLICABLE'],
        ];
    }

    /** @return list<array<string,string>> */
    public static function privilegeRequirements(): array
    {
        return [
            ['principal' => 'MIGRATION', 'least_privilege' => 'DDL/TRIGGER/METADATA privileges certified by MP01B; exact SHOW GRANTS precheck required'],
            ['principal' => 'WRITER', 'least_privilege' => 'history INSERT/SELECT plus stream-head SELECT/INSERT/UPDATE; no history UPDATE/DELETE'],
            ['principal' => 'READ', 'least_privilege' => 'bounded SELECT only on required audit/read subject authorities; no INSERT/UPDATE/DELETE'],
        ];
    }

    /** @return list<array<string,string>> */
    public static function stagingScenarios(): array
    {
        return [
            ['scenario' => 'identity successful event', 'status' => 'REQUIRES_STAGING_E2E'],
            ['scenario' => 'identity denied or failed event', 'status' => 'REQUIRES_STAGING_E2E'],
            ['scenario' => 'session create revoke logout', 'status' => 'REQUIRES_STAGING_E2E'],
            ['scenario' => 'profile claim event', 'status' => 'REQUIRES_STAGING_E2E'],
            ['scenario' => 'ownership or role event', 'status' => 'REQUIRES_STAGING_E2E'],
            ['scenario' => 'step-up success and failure', 'status' => 'REQUIRES_STAGING_E2E'],
            ['scenario' => 'break-glass lifecycle', 'status' => 'DEFERRED_PHYSICAL_FLOW_ABSENT'],
            ['scenario' => 'sensitive-admin eligible action', 'status' => 'DEFERRED_PHYSICAL_FLOW_ABSENT'],
            ['scenario' => 'sensitive-admin unknown catalog rejection', 'status' => 'REQUIRES_STAGING_E2E'],
            ['scenario' => 'self timeline ACCOUNT', 'status' => 'REQUIRES_STAGING_E2E'],
            ['scenario' => 'self timeline SESSION', 'status' => 'REQUIRES_STAGING_E2E'],
            ['scenario' => 'self timeline AUTH_IDENTIFIER_HMAC', 'status' => 'REQUIRES_STAGING_E2E'],
            ['scenario' => 'self timeline STEP_UP', 'status' => 'REQUIRES_STAGING_E2E'],
            ['scenario' => 'cross-account self timeline denial', 'status' => 'REQUIRES_STAGING_E2E'],
            ['scenario' => 'internal scoped read allow', 'status' => 'REQUIRES_STAGING_E2E'],
            ['scenario' => 'internal scoped read deny', 'status' => 'REQUIRES_STAGING_E2E'],
            ['scenario' => 'cursor pagination and subject scope', 'status' => 'REQUIRES_STAGING_E2E'],
            ['scenario' => 'read data minimization', 'status' => 'REQUIRES_STAGING_E2E'],
            ['scenario' => 'audit-of-read emission', 'status' => 'DEFERRED_UNTIL_ACTIVATED'],
        ];
    }

    /** @return list<array<string,string>> */
    public static function observabilitySignals(): array
    {
        $names = [
            'audit write attempts', 'audit write success', 'audit write failure signal',
            'producer event counts by type', 'policy denials', 'read authorization denials',
            'self-subject binding denials', 'read latency', 'pagination/cursor errors',
            'DB errors', 'chain/sealing verification failures',
        ];
        return array_map(static fn(string $name): array => [
            'signal' => $name,
            'threshold' => 'TO_BE_SET_BEFORE_CUTOVER',
        ], $names);
    }

    /** @return list<array<string,mixed>> */
    public static function authorizationBoundaries(): array
    {
        return [
            ['action' => 'repo implementation commit', 'director_authorization_required' => true],
            ['action' => 'repo publication push', 'director_authorization_required' => true],
            ['action' => 'DB migration execution', 'director_authorization_required' => true],
            ['action' => 'secret provisioning or change', 'director_authorization_required' => true],
            ['action' => 'DB privilege change', 'director_authorization_required' => true],
            ['action' => 'staging runtime activation', 'director_authorization_required' => true],
            ['action' => 'production runtime activation', 'director_authorization_required' => true],
            ['action' => 'production cutover', 'director_authorization_required' => true],
            ['action' => 'rollback', 'director_authorization_required' => true],
        ];
    }

    /** @return array<string,mixed> */
    public static function summary(): array
    {
        return [
            'STATIC_SUBSYSTEM_READY' => true,
            'ACTIVATION_PLAN_READY' => true,
            'PRODUCTIVE_ACTIVATION_READY' => false,
            'PRODUCTION_CUTOVER_READY' => false,
            'SELF_SUBJECT_SCOPE_PAGINATION_COMPATIBLE' => self::SELF_SUBJECT_SCOPE_PAGINATION_COMPATIBLE,
        ];
    }

    /** @return array<string,mixed> */
    private static function row(
        string $status,
        string $authority,
        string $missing,
        string $futureClass,
        bool $authorization,
        string $rollback
    ): array {
        return [
            'status' => $status,
            'current_authority_evidence' => $authority,
            'missing_prerequisite' => $missing,
            'future_write_execution_class' => $futureClass,
            'director_authorization_required' => $authorization,
            'rollback_boundary' => $rollback,
        ];
    }

    /** @return array<string,mixed> */
    private static function step(string $code, string $action, ?string $dependsOn, bool $authorization, string $rollback): array
    {
        return [
            'code' => $code,
            'action' => $action,
            'depends_on' => $dependsOn,
            'director_authorization_required' => $authorization,
            'rollback_boundary' => $rollback,
        ];
    }

    /** @param array<string,array<string,mixed>> $rows */
    private static function assertMatrix(array $rows): void
    {
        $required = [
            'PERSISTENCE_SCHEMA', 'D11_MIGRATION', 'DB_PRIVILEGES', 'DB_SECRETS',
            'WRITER_RUNTIME_BINDING', 'REQUEST_CONTEXT_WIRING',
            'IDENTITY_PRODUCER_WIRING', 'SESSION_PRODUCER_WIRING',
            'MP01F_PRODUCER_WIRING', 'AUDIT_READ_REPOSITORY_ADAPTER',
            'SELF_SUBJECT_RESOLVER_ADAPTER', 'AUDIT_READ_ROUTE_WIRING',
            'AUDIT_OF_READ_WIRING', 'STAGING_E2E', 'ROLLBACK_READINESS',
            'OBSERVABILITY', 'PRODUCTION_CUTOVER', 'POST_CUTOVER_MONITORING',
        ];
        if (array_keys($rows) !== $required) {
            throw new \LogicException('invalid_readiness_matrix_rows');
        }
        foreach ($rows as $row) {
            if (!in_array($row['status'] ?? null, self::STATUSES, true)
                || array_keys($row) !== [
                    'status', 'current_authority_evidence', 'missing_prerequisite',
                    'future_write_execution_class', 'director_authorization_required',
                    'rollback_boundary',
                ]) {
                throw new \LogicException('invalid_readiness_matrix_row');
            }
        }
    }
}
