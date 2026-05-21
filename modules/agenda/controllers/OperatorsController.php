<?php
declare(strict_types=1);

namespace Agenda\Controllers;

use Agenda\Helpers as DbHelpers;
use Agenda\Repositories\OperatorsRepository;
use RuntimeException;
use Throwable;

require_once __DIR__ . '/../repositories/OperatorsRepository.php';
require_once __DIR__ . '/../helpers/db_helpers.php';
require_once __DIR__ . '/../../../api/_lib/db.php';

class OperatorsController
{
    private ?OperatorsRepository $repository = null;
    private ?string $dbError = null;
    private bool $qaNotReady = false;
    private array $actorContext = [];
    private array $contextWarnings = [];

    public function __construct()
    {
        $this->qaNotReady = DbHelpers\isQaModeNotReady();
        if ($this->qaNotReady) {
            return;
        }

        try {
            $pdo = mxmed_pdo();
            $this->repository = new OperatorsRepository($pdo);
        } catch (RuntimeException $e) {
            $this->dbError = $e->getMessage();
        } catch (Throwable $e) {
            $this->dbError = 'database error';
        }
    }

    public function setActorContext(array $context = []): void
    {
        $this->actorContext = $context;
    }

    public function index(array $params = []): array
    {
        $notReady = $this->ensureReady();
        if ($notReady) {
            return $notReady;
        }

        $doctorIdRequested = trim((string)($params['doctor_id'] ?? ''));
        $doctorScope = $this->resolveDoctorScope($doctorIdRequested, true);
        if (!$doctorScope['ok']) {
            return $this->error(
                (string)$doctorScope['error'],
                (string)$doctorScope['message'],
                (array)($doctorScope['meta'] ?? [])
            );
        }

        $doctorId = (string)$doctorScope['doctor_id'];
        $auditLimit = isset($params['audit_limit']) ? (int)$params['audit_limit'] : 120;
        if ($auditLimit <= 0) {
            $auditLimit = 120;
        }

        try {
            $state = $this->repository->readStateByDoctor($doctorId, $auditLimit);
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'operators tables not ready') {
                return $this->error('db_not_ready', 'operators tables not ready');
            }
            return $this->error('db_error', 'database error');
        } catch (Throwable $e) {
            return $this->error('db_error', 'database error');
        }

        return $this->success($state, [
            'doctor_id_effective' => $doctorId,
            'doctor_id_requested' => ($doctorIdRequested !== '' ? $doctorIdRequested : null),
        ]);
    }

    public function store(array $payload = []): array
    {
        $notReady = $this->ensureReady();
        if ($notReady) {
            return $notReady;
        }

        $doctorIdRequested = trim((string)($payload['doctor_id'] ?? ''));
        $doctorScope = $this->resolveDoctorScope($doctorIdRequested, true);
        if (!$doctorScope['ok']) {
            return $this->error(
                (string)$doctorScope['error'],
                (string)$doctorScope['message'],
                (array)($doctorScope['meta'] ?? [])
            );
        }
        $doctorId = (string)$doctorScope['doctor_id'];

        $prepared = $this->preparePayload($payload);
        if (!empty($prepared['errors'])) {
            return $this->error('invalid_params', 'invalid payload for operator create', $prepared['errors']);
        }

        try {
            $result = $this->repository->createOperator($doctorId, $prepared['payload'], $this->actorContext);
        } catch (RuntimeException $e) {
            $message = $e->getMessage();
            if ($message === 'quota_limit_reached') {
                return $this->error('conflict', 'operator quota limit reached', [
                    'max_allowed' => 3,
                ]);
            }
            if ($message === 'alias_duplicated') {
                return $this->error('conflict', 'operator alias already exists', [
                    'field' => 'alias',
                ]);
            }
            if ($message === 'login_duplicated') {
                return $this->error('conflict', 'operator login already exists', [
                    'field' => 'login',
                ]);
            }
            if ($message === 'operator_id_duplicated') {
                return $this->error('conflict', 'operator id already exists', [
                    'field' => 'operator_id',
                ]);
            }
            if ($message === 'operators tables not ready' || $message === 'operator audit table not ready') {
                return $this->error('db_not_ready', 'operators tables not ready');
            }
            return $this->error('db_error', 'database error');
        } catch (Throwable $e) {
            return $this->error('db_error', 'database error');
        }

        return $this->success($result, [
            'write' => 'create',
            'doctor_id_effective' => $doctorId,
            'doctor_id_requested' => ($doctorIdRequested !== '' ? $doctorIdRequested : null),
            'events_appended' => 1,
        ]);
    }

    public function migrationPreview(array $payload = []): array
    {
        $notReady = $this->ensureReady();
        if ($notReady) {
            return $notReady;
        }

        $doctorIdRequested = trim((string)($payload['doctor_id'] ?? ''));
        $doctorScope = $this->resolveDoctorScope($doctorIdRequested, true);
        if (!$doctorScope['ok']) {
            return $this->error(
                (string)$doctorScope['error'],
                (string)$doctorScope['message'],
                (array)($doctorScope['meta'] ?? [])
            );
        }
        $doctorId = (string)$doctorScope['doctor_id'];
        $source = $this->extractMigrationSource($payload);

        try {
            $preview = $this->repository->previewMigrationFromLocalState($doctorId, $source);
        } catch (RuntimeException $e) {
            $message = $e->getMessage();
            if ($message === 'operators tables not ready' || $message === 'operator audit table not ready') {
                return $this->error('db_not_ready', 'operators tables not ready');
            }
            return $this->error('db_error', 'database error');
        } catch (Throwable $e) {
            return $this->error('db_error', 'database error');
        }

        return $this->success($preview, [
            'write' => 'migration_preview',
            'doctor_id_effective' => $doctorId,
            'doctor_id_requested' => ($doctorIdRequested !== '' ? $doctorIdRequested : null),
        ]);
    }

    public function migrationApply(array $payload = []): array
    {
        $notReady = $this->ensureReady();
        if ($notReady) {
            return $notReady;
        }

        if (!$this->isMigrationConfirmed($payload['confirm'] ?? null)) {
            return $this->error('invalid_params', 'confirm is required', [
                'field' => 'confirm',
            ]);
        }

        $doctorIdRequested = trim((string)($payload['doctor_id'] ?? ''));
        $doctorScope = $this->resolveDoctorScope($doctorIdRequested, true);
        if (!$doctorScope['ok']) {
            return $this->error(
                (string)$doctorScope['error'],
                (string)$doctorScope['message'],
                (array)($doctorScope['meta'] ?? [])
            );
        }
        $doctorId = (string)$doctorScope['doctor_id'];
        $source = $this->extractMigrationSource($payload);

        try {
            $preview = $this->repository->previewMigrationFromLocalState($doctorId, $source);
        } catch (RuntimeException $e) {
            $message = $e->getMessage();
            if ($message === 'operators tables not ready' || $message === 'operator audit table not ready') {
                return $this->error('db_not_ready', 'operators tables not ready');
            }
            return $this->error('db_error', 'database error');
        } catch (Throwable $e) {
            return $this->error('db_error', 'database error');
        }

        if (!empty($preview['has_blocking_conflicts'])) {
            return $this->error('conflict', 'migration has blocking conflicts', [
                'conflicts' => $preview['conflicts'] ?? [],
                'warnings' => $preview['warnings'] ?? [],
                'summary_before' => $preview['summary_before'] ?? [],
                'summary_after' => $preview['summary_after_if_applied'] ?? [],
            ]);
        }

        try {
            $result = $this->repository->applyMigrationFromLocalState($doctorId, $source, $this->actorContext, $preview);
        } catch (RuntimeException $e) {
            $message = $e->getMessage();
            if ($message === 'quota_limit_reached') {
                return $this->error('conflict', 'operator quota limit reached', [
                    'max_allowed' => 3,
                ]);
            }
            if ($message === 'alias_duplicated') {
                return $this->error('conflict', 'operator alias already exists', [
                    'field' => 'alias',
                ]);
            }
            if ($message === 'login_duplicated') {
                return $this->error('conflict', 'operator login already exists', [
                    'field' => 'login',
                ]);
            }
            if ($message === 'migration_conflicts_blocking') {
                return $this->error('conflict', 'migration has blocking conflicts', []);
            }
            if ($message === 'operators tables not ready' || $message === 'operator audit table not ready') {
                return $this->error('db_not_ready', 'operators tables not ready');
            }
            return $this->error('db_error', 'database error');
        } catch (Throwable $e) {
            return $this->error('db_error', 'database error');
        }

        return $this->success($result, [
            'write' => 'migration_apply',
            'doctor_id_effective' => $doctorId,
            'doctor_id_requested' => ($doctorIdRequested !== '' ? $doctorIdRequested : null),
        ]);
    }

    public function pause(string $operatorId, array $payload = []): array
    {
        return $this->mutateOperatorStatusAction($operatorId, 'pause', $payload);
    }

    public function reactivate(string $operatorId, array $payload = []): array
    {
        return $this->mutateOperatorStatusAction($operatorId, 'reactivate', $payload);
    }

    public function archive(string $operatorId, array $payload = []): array
    {
        return $this->mutateOperatorStatusAction($operatorId, 'archive', $payload);
    }

    public function restore(string $operatorId, array $payload = []): array
    {
        return $this->mutateOperatorStatusAction($operatorId, 'restore', $payload);
    }

    private function preparePayload(array $payload): array
    {
        $errors = [];

        $aliasRaw = trim((string)($payload['alias'] ?? ''));
        $aliasNormalized = $this->normalizeAlias($aliasRaw);
        if ($aliasNormalized === '') {
            $errors['alias'] = 'required';
        } elseif (strlen($aliasNormalized) < 3) {
            $errors['alias'] = 'min_length_3';
        } elseif (strlen($aliasNormalized) > 15) {
            $errors['alias'] = 'max_length_15';
        } elseif (!$this->isAliasValid($aliasNormalized)) {
            $errors['alias'] = 'invalid_chars';
        }

        $loginRaw = trim((string)($payload['login'] ?? ''));
        $loginNormalized = $this->normalizeLogin($loginRaw);
        if ($loginNormalized === '') {
            $errors['login'] = 'required';
        } elseif (!$this->isLoginValid($loginNormalized)) {
            $errors['login'] = 'invalid_chars';
        }

        $fullName = trim((string)($payload['full_name'] ?? ''));
        if ($fullName === '') {
            $errors['full_name'] = 'required';
        }

        $status = strtolower(trim((string)($payload['status'] ?? 'pending')));
        if ($status === '') {
            $status = 'pending';
        }
        if (!in_array($status, ['active', 'paused', 'pending'], true)) {
            $errors['status'] = 'invalid';
        }

        $role = strtolower(trim((string)($payload['role'] ?? 'operator')));
        if ($role === '') {
            $role = 'operator';
        }
        if (!in_array($role, ['operator', 'assistant'], true)) {
            $errors['role'] = 'invalid';
        }

        $permissions = $this->sanitizePermissionKeys($payload['permissions'] ?? []);
        $forcePasswordChange = !empty($payload['force_password_change']);

        $tempPasswordRaw = (string)($payload['temp_password'] ?? '');
        $tempPasswordHash = trim((string)($payload['temp_password_hash'] ?? ''));
        if ($tempPasswordRaw !== '') {
            $tempPasswordHash = password_hash($tempPasswordRaw, PASSWORD_DEFAULT);
        }
        if ($tempPasswordHash !== '' && strpos($tempPasswordHash, '$') !== 0) {
            $errors['temp_password_hash'] = 'invalid_hash';
        }

        return [
            'errors' => $errors,
            'payload' => [
                'operator_id' => trim((string)($payload['operator_id'] ?? '')),
                'operator_label' => trim((string)($payload['operator_label'] ?? '')),
                'alias' => $aliasNormalized,
                'alias_normalized' => $aliasNormalized,
                'full_name' => $fullName,
                'phone' => trim((string)($payload['phone'] ?? '')),
                'email' => strtolower(trim((string)($payload['email'] ?? ''))),
                'gender' => strtolower(trim((string)($payload['gender'] ?? ''))),
                'role' => $role,
                'status' => $status,
                'login' => $loginNormalized,
                'login_normalized' => $loginNormalized,
                'temp_password_hash' => $tempPasswordHash,
                'force_password_change' => $forcePasswordChange,
                'invitation_status' => strtolower(trim((string)($payload['invitation_status'] ?? 'pending'))),
                'operator_credentials_sent_at' => trim((string)($payload['operator_credentials_sent_at'] ?? '')),
                'last_access' => trim((string)($payload['last_access'] ?? '')),
                'permissions' => $permissions,
            ],
        ];
    }

    private function normalizeAlias(string $value): string
    {
        $withoutAccents = $this->removeAccents($value);
        $normalized = strtoupper(trim($withoutAccents));
        $normalized = preg_replace('/\s+/', '', $normalized) ?: '';
        $normalized = preg_replace('/[^A-Z0-9-]/', '', $normalized) ?: '';
        return $normalized;
    }

    private function normalizeLogin(string $value): string
    {
        $withoutAccents = $this->removeAccents($value);
        $normalized = strtolower(trim($withoutAccents));
        $normalized = preg_replace('/\s+/', '', $normalized) ?: '';
        $normalized = preg_replace('/[^a-z0-9.-]/', '', $normalized) ?: '';
        $normalized = preg_replace('/[.]{2,}/', '.', $normalized) ?: '';
        $normalized = preg_replace('/[-]{2,}/', '-', $normalized) ?: '';
        $normalized = preg_replace('/([.-]){2,}/', '$1', $normalized) ?: '';
        $normalized = trim($normalized, '.-');
        return $normalized;
    }

    private function isAliasValid(string $alias): bool
    {
        return (bool)preg_match('/^[A-Z0-9-]{3,15}$/', $alias);
    }

    private function isLoginValid(string $login): bool
    {
        return (bool)preg_match('/^[a-z0-9.-]+$/', $login);
    }

    private function removeAccents(string $value): string
    {
        if (class_exists(\Normalizer::class)) {
            $normalized = \Normalizer::normalize($value, \Normalizer::FORM_D);
            if (is_string($normalized)) {
                $value = preg_replace('/[\x{0300}-\x{036f}]/u', '', $normalized) ?: $value;
            }
        } else {
            $value = strtr($value, [
                'Á' => 'A', 'À' => 'A', 'Ä' => 'A', 'Â' => 'A', 'Ã' => 'A',
                'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'ã' => 'a',
                'É' => 'E', 'È' => 'E', 'Ë' => 'E', 'Ê' => 'E',
                'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
                'Í' => 'I', 'Ì' => 'I', 'Ï' => 'I', 'Î' => 'I',
                'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
                'Ó' => 'O', 'Ò' => 'O', 'Ö' => 'O', 'Ô' => 'O', 'Õ' => 'O',
                'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o', 'õ' => 'o',
                'Ú' => 'U', 'Ù' => 'U', 'Ü' => 'U', 'Û' => 'U',
                'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
                'Ñ' => 'N', 'ñ' => 'n',
            ]);
        }
        return $value;
    }

    private function sanitizePermissionKeys($permissions): array
    {
        $allowed = ['agenda', 'patients', 'billing', 'payment_proof'];
        $seen = [];
        foreach ((array)$permissions as $item) {
            $key = strtolower(trim((string)$item));
            if ($key === '' || !in_array($key, $allowed, true)) {
                continue;
            }
            $seen[$key] = true;
        }
        return array_keys($seen);
    }

    private function mutateOperatorStatusAction(string $operatorIdRaw, string $mutation, array $payload = []): array
    {
        $notReady = $this->ensureReady();
        if ($notReady) {
            return $notReady;
        }

        $operatorId = trim($operatorIdRaw);
        if ($operatorId === '') {
            return $this->error('invalid_params', 'operator_id is required', []);
        }

        $doctorIdRequested = trim((string)($payload['doctor_id'] ?? ''));
        $doctorScope = $this->resolveDoctorScope($doctorIdRequested, true);
        if (!$doctorScope['ok']) {
            return $this->error(
                (string)$doctorScope['error'],
                (string)$doctorScope['message'],
                (array)($doctorScope['meta'] ?? [])
            );
        }
        $doctorId = (string)$doctorScope['doctor_id'];

        $verificationCode = trim((string)($payload['verification_code'] ?? ''));
        if (!$this->isValidVerificationCode($verificationCode)) {
            return $this->error('invalid_verification_code', 'invalid verification code', [
                'field' => 'verification_code',
            ]);
        }

        $reason = trim((string)($payload['reason'] ?? ''));
        $metadata = (isset($payload['metadata']) && is_array($payload['metadata'])) ? $payload['metadata'] : [];
        $restoreStatus = strtolower(trim((string)($payload['restore_status'] ?? '')));

        try {
            $result = $this->repository->mutateOperatorStatus($doctorId, $operatorId, $mutation, $this->actorContext, [
                'reason' => $reason,
                'metadata' => $metadata,
                'restore_status' => $restoreStatus,
            ]);
        } catch (RuntimeException $e) {
            $message = $e->getMessage();
            if ($message === 'operator_not_found') {
                return $this->error('not_found', 'operator not found', []);
            }
            if ($message === 'quota_limit_reached') {
                return $this->error('conflict', 'operator quota limit reached', [
                    'max_allowed' => 3,
                ]);
            }
            if ($message === 'alias_duplicated') {
                return $this->error('conflict', 'operator alias already exists', [
                    'field' => 'alias',
                ]);
            }
            if ($message === 'login_duplicated') {
                return $this->error('conflict', 'operator login already exists', [
                    'field' => 'login',
                ]);
            }
            if ($message === 'invalid_transition') {
                return $this->error('conflict', 'invalid status transition', []);
            }
            if ($message === 'invalid_mutation') {
                return $this->error('invalid_params', 'invalid mutation', []);
            }
            if ($message === 'operators tables not ready' || $message === 'operator audit table not ready') {
                return $this->error('db_not_ready', 'operators tables not ready');
            }
            return $this->error('db_error', 'database error');
        } catch (Throwable $e) {
            return $this->error('db_error', 'database error');
        }

        return $this->success($result, [
            'write' => $mutation,
            'doctor_id_effective' => $doctorId,
            'doctor_id_requested' => ($doctorIdRequested !== '' ? $doctorIdRequested : null),
            'events_appended' => 1,
        ]);
    }

    private function isValidVerificationCode(string $value): bool
    {
        return (bool)preg_match('/^\d{6}$/', $value);
    }

    private function extractMigrationSource(array $payload): array
    {
        $source = (isset($payload['source']) && is_array($payload['source'])) ? $payload['source'] : $payload;
        return [
            'operators' => (isset($source['operators']) && is_array($source['operators'])) ? $source['operators'] : [],
            'archived_operators' => (isset($source['archived_operators']) && is_array($source['archived_operators'])) ? $source['archived_operators'] : [],
            'audit_trail' => (isset($source['audit_trail']) && is_array($source['audit_trail'])) ? $source['audit_trail'] : [],
        ];
    }

    private function isMigrationConfirmed($confirm): bool
    {
        if (is_array($confirm)) {
            $accepted = $confirm['accepted'] ?? null;
            return $accepted === true || $accepted === 1 || $accepted === '1' || $accepted === 'true';
        }
        return $confirm === true || $confirm === 1 || $confirm === '1' || $confirm === 'true';
    }

    private function ensureReady(): ?array
    {
        if ($this->qaNotReady || $this->dbError) {
            $message = $this->dbError ?: 'operators tables not ready';
            return $this->error('db_not_ready', $message);
        }
        if (!$this->repository) {
            return $this->error('db_error', 'database error');
        }
        return null;
    }

    private function resolveDoctorScope(string $doctorIdRequested, bool $doctorIsRequired): array
    {
        $doctorIdContext = trim((string)($this->actorContext['doctor_id'] ?? ''));
        $strictMode = ($this->actorContext['strict'] ?? false) === true;
        if ($doctorIdContext !== '') {
            if ($doctorIdRequested !== '' && $doctorIdRequested !== $doctorIdContext) {
                if ($strictMode) {
                    return [
                        'ok' => false,
                        'error' => 'forbidden',
                        'message' => 'doctor scope mismatch',
                        'meta' => [
                            'doctor_id_requested' => $doctorIdRequested,
                            'doctor_id_context' => $doctorIdContext,
                        ],
                    ];
                }
                $this->contextWarnings[] = [
                    'type' => 'doctor_scope_mismatch',
                    'doctor_id_requested' => $doctorIdRequested,
                    'doctor_id_context' => $doctorIdContext,
                ];
            }
            return ['ok' => true, 'doctor_id' => $doctorIdContext];
        }
        if ($doctorIsRequired && $doctorIdRequested === '') {
            return [
                'ok' => false,
                'error' => 'invalid_params',
                'message' => 'doctor_id is required',
                'meta' => [],
            ];
        }
        return ['ok' => true, 'doctor_id' => $doctorIdRequested];
    }

    private function getQaMode(): string
    {
        $headers = function_exists('getallheaders') ? (array)getallheaders() : [];
        $qa = $headers['X-QA-Mode'] ?? $headers['x-qa-mode'] ?? null;
        if (is_string($qa) && $qa !== '') {
            return $qa;
        }
        $env = getenv('QA_MODE');
        return is_string($env) ? $env : '';
    }

    private function success(array $data, array $meta = []): array
    {
        $meta = $this->appendAuthMeta($meta);
        $meta['qa_mode_seen'] = $this->getQaMode();
        return [
            'ok' => true,
            'error' => null,
            'message' => '',
            'data' => $data,
            'meta' => empty($meta) ? (object)[] : (object)$meta,
        ];
    }

    private function error(string $code, string $message, array $meta = []): array
    {
        $meta = $this->appendAuthMeta($meta);
        $meta['qa_mode_seen'] = $this->getQaMode();
        return [
            'ok' => false,
            'error' => $code,
            'message' => $message,
            'data' => null,
            'meta' => empty($meta) ? (object)[] : (object)$meta,
        ];
    }

    private function appendAuthMeta(array $meta): array
    {
        $meta['auth_mode'] = trim((string)($this->actorContext['mode'] ?? ''));
        $meta['auth_warnings'] = $this->contextWarnings;
        return $meta;
    }
}
