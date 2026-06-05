<?php
declare(strict_types=1);

namespace Profiles\Controllers;

use Profiles\Repositories\DoctorContactPointsRepository;
use RuntimeException;

require_once __DIR__ . '/../repositories/DoctorContactPointsRepository.php';

final class DoctorContactPointsController
{
    private DoctorContactPointsRepository $repository;

    private const CREATE_ALLOWED_FIELDS = [
        'type',
        'value',
        'label',
        'scope',
        'use_for_security',
        'use_for_platform_admin',
        'use_for_appointments',
        'status',
        'sort_order',
    ];

    private const CREATE_BLOCKED_FIELDS = [
        'doctor_id',
        'contact_point_id',
        'normalized_value',
        'is_public',
        'use_for_public_profile',
        'visibility_plan_min',
        'is_verified',
        'verification_status',
        'consultorio_id',
        'source',
        'metadata_json',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    private const ALLOWED_TYPES = ['email', 'phone', 'whatsapp'];
    private const ALLOWED_SCOPES = ['private', 'operational', 'platform_admin'];
    private const ALLOWED_STATUSES = ['active', 'inactive', 'archived'];
    private const LEGACY_IMPORT_KEYS = ['dp:dp-correo', 'dp:dp-whatsapp'];
    private const LEGACY_IMPORT_TYPE_BY_KEY = [
        'dp:dp-correo' => 'email',
        'dp:dp-whatsapp' => 'whatsapp',
    ];
    private const LEGACY_IMPORT_BLOCKED_FIELDS = [
        'doctor_id',
        'contact_point_id',
        'source',
        'normalized_value',
        'label',
        'scope',
        'is_public',
        'use_for_public_profile',
        'is_verified',
        'verification_status',
        'consultorio_id',
        'metadata_json',
        'created_at',
        'updated_at',
        'deleted_at',
        'use_for_security',
        'use_for_platform_admin',
        'use_for_appointments',
    ];

    public function __construct(DoctorContactPointsRepository $repository)
    {
        $this->repository = $repository;
    }

    public function index(string $doctorId, string $authMode = 'transitional_open'): array
    {
        $doctorId = trim($doctorId);
        if (!$this->isValidDoctorId($doctorId)) {
            return $this->error('invalid_doctor_id', 'doctor_id invalid', $authMode);
        }

        try {
            $items = $this->repository->listByDoctor($doctorId);
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'doctor_contact_points table not ready') {
                return $this->error('db_not_ready', 'doctor_contact_points table not ready', $authMode, [
                    'schema_executed' => false,
                ]);
            }
            return $this->error('profile_contact_points_unavailable', 'contact points unavailable', $authMode);
        }

        return [
            'ok' => true,
            'error' => null,
            'message' => '',
            'data' => [
                'items' => $items,
            ],
            'meta' => $this->meta($authMode, [
                'schema_executed' => true,
                'count' => count($items),
            ]),
        ];
    }

    public function store(string $doctorId, array $payload, string $authMode = 'transitional_open'): array
    {
        $doctorId = trim($doctorId);
        if (!$this->isValidDoctorId($doctorId)) {
            return $this->error('invalid_doctor_id', 'doctor_id invalid', $authMode);
        }
        if (!$this->isAssociative($payload)) {
            return $this->error('invalid_payload', 'payload object required', $authMode);
        }

        $prepared = $this->prepareCreatePayload($payload);
        if (!empty($prepared['unknown_fields'])) {
            return $this->error('invalid_payload', 'unsupported fields in payload', $authMode, [
                'unknown_fields' => array_values($prepared['unknown_fields']),
                'blocked_fields_ignored' => array_values($prepared['blocked_fields']),
            ]);
        }
        if (!empty($prepared['validation_errors'])) {
            return $this->error('validation_error', 'validation error', $authMode, [
                'validation_errors' => array_values($prepared['validation_errors']),
                'blocked_fields_ignored' => array_values($prepared['blocked_fields']),
            ]);
        }

        $contact = $prepared['contact_point'];
        try {
            $existing = $this->repository->findByNormalizedValue(
                $doctorId,
                (string)$contact['type'],
                (string)$contact['normalized_value']
            );
            if (is_array($existing)) {
                return $this->error('duplicate_active_contact', 'duplicate active contact', $authMode, [
                    'existing_contact_point_id' => (int)($existing['contact_point_id'] ?? 0),
                    'blocked_fields_ignored' => array_values($prepared['blocked_fields']),
                ], [
                    'existing_contact_point_id' => (int)($existing['contact_point_id'] ?? 0),
                ]);
            }

            $created = $this->repository->createForDoctor($doctorId, $contact);
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'doctor_contact_points table not ready') {
                return $this->error('db_not_ready', 'doctor_contact_points table not ready', $authMode, [
                    'schema_executed' => false,
                ]);
            }
            if ($e->getMessage() === 'duplicate_active_contact') {
                return $this->error('duplicate_active_contact', 'duplicate active contact', $authMode, [
                    'blocked_fields_ignored' => array_values($prepared['blocked_fields']),
                ]);
            }
            return $this->error('profile_contact_points_unavailable', 'contact points unavailable', $authMode);
        }

        return [
            'ok' => true,
            'error' => null,
            'message' => '',
            'data' => [
                'contact_point' => $created,
            ],
            'meta' => $this->meta($authMode, [
                'schema_executed' => true,
                'created' => true,
                'blocked_fields_ignored' => array_values($prepared['blocked_fields']),
            ]),
        ];
    }

    public function update(
        string $doctorId,
        string $contactPointId,
        array $payload,
        string $authMode = 'transitional_open'
    ): array {
        $doctorId = trim($doctorId);
        $contactPointId = trim($contactPointId);
        if (!$this->isValidDoctorId($doctorId)) {
            return $this->error('invalid_doctor_id', 'doctor_id invalid', $authMode);
        }
        if (!$this->isValidContactPointId($contactPointId)) {
            return $this->error('invalid_contact_point_id', 'contact_point_id invalid', $authMode);
        }
        if (!$this->isAssociative($payload)) {
            return $this->error('invalid_payload', 'payload object required', $authMode);
        }

        try {
            $current = $this->repository->findById($doctorId, $contactPointId);
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'doctor_contact_points table not ready') {
                return $this->error('db_not_ready', 'doctor_contact_points table not ready', $authMode, [
                    'schema_executed' => false,
                ]);
            }
            return $this->error('profile_contact_points_unavailable', 'contact points unavailable', $authMode);
        }

        if (!is_array($current)) {
            return $this->error('contact_point_not_found', 'contact point not found', $authMode);
        }

        $prepared = $this->prepareUpdatePayload($payload, $current);
        if (!empty($prepared['unknown_fields'])) {
            return $this->error('invalid_payload', 'unsupported fields in payload', $authMode, [
                'unknown_fields' => array_values($prepared['unknown_fields']),
                'blocked_fields_ignored' => array_values($prepared['blocked_fields']),
            ]);
        }
        if (!empty($prepared['validation_errors'])) {
            return $this->error('validation_error', 'validation error', $authMode, [
                'validation_errors' => array_values($prepared['validation_errors']),
                'blocked_fields_ignored' => array_values($prepared['blocked_fields']),
            ]);
        }

        $editable = $prepared['contact_point'];
        if (empty($editable)) {
            return [
                'ok' => true,
                'error' => null,
                'message' => '',
                'data' => [
                    'contact_point' => $current,
                ],
                'meta' => $this->meta($authMode, [
                    'schema_executed' => true,
                    'updated' => false,
                    'blocked_fields_ignored' => array_values($prepared['blocked_fields']),
                    'editable_fields_applied' => [],
                    'no_editable_fields_applied' => true,
                ]),
            ];
        }

        try {
            $duplicate = $this->repository->findDuplicateActive(
                $doctorId,
                (string)($editable['type'] ?? $current['type']),
                (string)($editable['normalized_value'] ?? $current['normalized_value']),
                $contactPointId
            );
            if (is_array($duplicate)) {
                return $this->error('duplicate_active_contact', 'duplicate active contact', $authMode, [
                    'existing_contact_point_id' => (int)($duplicate['contact_point_id'] ?? 0),
                    'blocked_fields_ignored' => array_values($prepared['blocked_fields']),
                ], [
                    'existing_contact_point_id' => (int)($duplicate['contact_point_id'] ?? 0),
                ]);
            }

            $updated = $this->repository->updateForDoctor($doctorId, $contactPointId, $editable);
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'doctor_contact_points table not ready') {
                return $this->error('db_not_ready', 'doctor_contact_points table not ready', $authMode, [
                    'schema_executed' => false,
                ]);
            }
            if ($e->getMessage() === 'contact_point_not_found') {
                return $this->error('contact_point_not_found', 'contact point not found', $authMode);
            }
            if ($e->getMessage() === 'duplicate_active_contact') {
                return $this->error('duplicate_active_contact', 'duplicate active contact', $authMode, [
                    'blocked_fields_ignored' => array_values($prepared['blocked_fields']),
                ]);
            }
            return $this->error('profile_contact_points_unavailable', 'contact points unavailable', $authMode);
        }

        return [
            'ok' => true,
            'error' => null,
            'message' => '',
            'data' => [
                'contact_point' => $updated,
            ],
            'meta' => $this->meta($authMode, [
                'schema_executed' => true,
                'updated' => true,
                'blocked_fields_ignored' => array_values($prepared['blocked_fields']),
                'editable_fields_applied' => array_values($prepared['editable_fields']),
            ]),
        ];
    }

    public function destroy(
        string $doctorId,
        string $contactPointId,
        string $authMode = 'transitional_open'
    ): array {
        $doctorId = trim($doctorId);
        $contactPointId = trim($contactPointId);
        if (!$this->isValidDoctorId($doctorId)) {
            return $this->error('invalid_doctor_id', 'doctor_id invalid', $authMode);
        }
        if (!$this->isValidContactPointId($contactPointId)) {
            return $this->error('invalid_contact_point_id', 'contact_point_id invalid', $authMode);
        }

        try {
            $deleted = $this->repository->softDeleteForDoctor($doctorId, $contactPointId);
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'doctor_contact_points table not ready') {
                return $this->error('db_not_ready', 'doctor_contact_points table not ready', $authMode, [
                    'schema_executed' => false,
                ]);
            }
            return $this->error('profile_contact_points_unavailable', 'contact points unavailable', $authMode);
        }

        if (!$deleted) {
            return $this->error('contact_point_not_found', 'contact point not found', $authMode);
        }

        return [
            'ok' => true,
            'error' => null,
            'message' => '',
            'data' => [
                'contact_point_id' => (int)$contactPointId,
            ],
            'meta' => $this->meta($authMode, [
                'schema_executed' => true,
                'deleted' => true,
            ]),
        ];
    }

    public function importLegacy(string $doctorId, array $payload, string $authMode = 'transitional_open'): array
    {
        $doctorId = trim($doctorId);
        if (!$this->isValidDoctorId($doctorId)) {
            return $this->error('invalid_doctor_id', 'doctor_id invalid', $authMode);
        }
        if (!$this->isAssociative($payload)) {
            return $this->error('invalid_payload', 'payload object required', $authMode);
        }

        $items = $payload['items'] ?? null;
        if (!is_array($items)) {
            return $this->error('invalid_payload', 'items array required', $authMode, [
                'import_source' => 'legacy_dp',
            ]);
        }
        if (count($items) === 0 || count($items) > 2) {
            return $this->error('validation_error', 'items must contain one or two entries', $authMode, [
                'import_source' => 'legacy_dp',
                'validation_errors' => ['items must contain one or two entries'],
            ], [
                'results' => [],
            ]);
        }

        $results = [];
        $seen = [];
        $created = 0;
        $alreadyExists = 0;
        $failed = 0;

        foreach (array_values($items) as $item) {
            if (!is_array($item) || !$this->isAssociative($item)) {
                $results[] = [
                    'legacy_key' => null,
                    'type' => null,
                    'status' => 'error',
                    'message' => 'item object required',
                ];
                $failed++;
                continue;
            }

            $prepared = $this->prepareLegacyImportItem($item);
            if ($prepared['status'] !== 'ready') {
                $results[] = $prepared['result'];
                $failed++;
                continue;
            }

            $contact = $prepared['contact_point'];
            $dedupeKey = (string)$contact['type'] . ':' . (string)$contact['normalized_value'];
            if (isset($seen[$dedupeKey])) {
                $results[] = [
                    'legacy_key' => $contact['legacy_key'],
                    'type' => $contact['type'],
                    'status' => 'duplicate_in_payload',
                ];
                $failed++;
                continue;
            }
            $seen[$dedupeKey] = true;

            try {
                $existing = $this->repository->findByNormalizedValue(
                    $doctorId,
                    (string)$contact['type'],
                    (string)$contact['normalized_value']
                );
                if (is_array($existing)) {
                    $results[] = [
                        'legacy_key' => $contact['legacy_key'],
                        'type' => $contact['type'],
                        'status' => 'already_exists',
                        'existing_contact_point_id' => (int)($existing['contact_point_id'] ?? 0),
                    ];
                    $alreadyExists++;
                    continue;
                }

                $createdContact = $this->repository->createLegacyForDoctor($doctorId, $contact);
                $result = [
                    'legacy_key' => $contact['legacy_key'],
                    'type' => $contact['type'],
                    'status' => 'created',
                    'contact_point_id' => (int)($createdContact['contact_point_id'] ?? 0),
                ];
                if (!empty($prepared['blocked_fields'])) {
                    $result['blocked_fields_ignored'] = array_values($prepared['blocked_fields']);
                }
                $results[] = $result;
                $created++;
            } catch (RuntimeException $e) {
                if ($e->getMessage() === 'doctor_contact_points table not ready') {
                    return $this->error('db_not_ready', 'doctor_contact_points table not ready', $authMode, [
                        'schema_executed' => false,
                        'import_source' => 'legacy_dp',
                    ]);
                }
                if ($e->getMessage() === 'duplicate_active_contact') {
                    $results[] = [
                        'legacy_key' => $contact['legacy_key'],
                        'type' => $contact['type'],
                        'status' => 'already_exists',
                    ];
                    $alreadyExists++;
                    continue;
                }
                $results[] = [
                    'legacy_key' => $contact['legacy_key'],
                    'type' => $contact['type'],
                    'status' => 'error',
                ];
                $failed++;
            }
        }

        $response = [
            'ok' => true,
            'error' => null,
            'message' => '',
            'data' => [
                'results' => $results,
            ],
            'meta' => $this->meta($authMode, [
                'schema_executed' => true,
                'import_source' => 'legacy_dp',
                'processed' => count($results),
                'created' => $created,
                'already_exists' => $alreadyExists,
                'failed' => $failed,
            ]),
        ];

        if (($created + $alreadyExists) === 0) {
            $response['ok'] = false;
            $response['error'] = 'validation_error';
            $response['message'] = 'no importable legacy items';
        }

        return $response;
    }

    private function error(string $code, string $message, string $authMode, array $metaExtra = [], $data = null): array
    {
        return [
            'ok' => false,
            'error' => $code,
            'message' => $message,
            'data' => $data,
            'meta' => $this->meta($authMode, $metaExtra),
        ];
    }

    private function meta(string $authMode, array $extra = []): array
    {
        return array_merge([
            'contract' => 'doctor_contact_points_private',
            'version' => 'SYS-Data-01P',
            'generated_at' => gmdate('c'),
            'auth_mode' => $authMode,
            'source' => 'doctor_contact_points',
        ], $extra);
    }

    private function prepareLegacyImportItem(array $item): array
    {
        $blocked = [];
        foreach ($item as $key => $_value) {
            $field = trim((string)$key);
            if ($field !== '' && in_array($field, self::LEGACY_IMPORT_BLOCKED_FIELDS, true)) {
                $blocked[] = $field;
            }
        }

        $legacyKey = $this->sanitizeText($item['legacy_key'] ?? null, 64);
        $type = strtolower((string)$this->sanitizeText($item['type'] ?? null, 32));
        $baseResult = [
            'legacy_key' => $legacyKey,
            'type' => $type !== '' ? $type : null,
        ];
        if (!empty($blocked)) {
            $baseResult['blocked_fields_ignored'] = array_values(array_unique($blocked));
        }

        if ($legacyKey === null || !in_array($legacyKey, self::LEGACY_IMPORT_KEYS, true)) {
            return [
                'status' => 'unsupported_legacy_key',
                'result' => array_merge($baseResult, ['status' => 'unsupported_legacy_key']),
            ];
        }

        if ($type === '' || !in_array($type, ['email', 'whatsapp'], true)) {
            return [
                'status' => 'unsupported_type',
                'result' => array_merge($baseResult, ['status' => 'unsupported_type']),
            ];
        }

        if ((self::LEGACY_IMPORT_TYPE_BY_KEY[$legacyKey] ?? '') !== $type) {
            return [
                'status' => 'mismatch_legacy_key_type',
                'result' => array_merge($baseResult, ['status' => 'mismatch_legacy_key_type']),
            ];
        }

        $value = $this->sanitizeText($item['value'] ?? null, 255);
        if ($value === null) {
            return [
                'status' => 'empty_value',
                'result' => array_merge($baseResult, ['status' => 'empty_value']),
            ];
        }

        $normalizedValue = $this->repository->normalizeValue($type, $value);
        if ($type === 'email' && filter_var($normalizedValue, FILTER_VALIDATE_EMAIL) === false) {
            return [
                'status' => 'invalid_value',
                'result' => array_merge($baseResult, ['status' => 'invalid_value']),
            ];
        }
        if ($type === 'whatsapp' && ($normalizedValue === '' || $normalizedValue === '+')) {
            return [
                'status' => 'invalid_value',
                'result' => array_merge($baseResult, ['status' => 'invalid_value']),
            ];
        }

        return [
            'status' => 'ready',
            'blocked_fields' => array_values(array_unique($blocked)),
            'contact_point' => $this->buildLegacyContactPayload($legacyKey, $type, $value, $normalizedValue),
        ];
    }

    private function buildLegacyContactPayload(
        string $legacyKey,
        string $type,
        string $value,
        string $normalizedValue
    ): array {
        if ($legacyKey === 'dp:dp-correo') {
            return [
                'legacy_key' => $legacyKey,
                'type' => 'email',
                'value' => $value,
                'normalized_value' => $normalizedValue,
                'label' => 'Correo privado',
                'scope' => 'private',
                'use_for_platform_admin' => true,
                'use_for_appointments' => false,
                'status' => 'active',
                'sort_order' => 100,
            ];
        }

        return [
            'legacy_key' => $legacyKey,
            'type' => $type,
            'value' => $value,
            'normalized_value' => $normalizedValue,
            'label' => 'WhatsApp privado',
            'scope' => 'private',
            'use_for_platform_admin' => false,
            'use_for_appointments' => false,
            'status' => 'active',
            'sort_order' => 110,
        ];
    }

    private function prepareCreatePayload(array $payload): array
    {
        $blocked = [];
        $unknown = [];
        foreach ($payload as $key => $_value) {
            $field = trim((string)$key);
            if ($field === '') {
                continue;
            }
            if (in_array($field, self::CREATE_BLOCKED_FIELDS, true)) {
                $blocked[] = $field;
                continue;
            }
            if (!in_array($field, self::CREATE_ALLOWED_FIELDS, true)) {
                $unknown[] = $field;
            }
        }

        $errors = [];
        $type = strtolower((string)$this->sanitizeText($payload['type'] ?? null, 32));
        if ($type === '' || !in_array($type, self::ALLOWED_TYPES, true)) {
            $errors[] = 'type must be one of: email, phone, whatsapp';
        }

        $value = $this->sanitizeText($payload['value'] ?? null, 255);
        if ($value === null) {
            $errors[] = 'value is required';
        }

        $normalizedValue = '';
        if ($type !== '' && $value !== null) {
            $normalizedValue = $this->repository->normalizeValue($type, $value);
            if ($type === 'email' && filter_var($normalizedValue, FILTER_VALIDATE_EMAIL) === false) {
                $errors[] = 'value must be a valid email';
            }
            if (($type === 'phone' || $type === 'whatsapp') && ($normalizedValue === '' || $normalizedValue === '+')) {
                $errors[] = 'value must contain phone digits';
            }
        }

        $scope = strtolower((string)$this->sanitizeText($payload['scope'] ?? 'private', 32));
        if ($scope === '') {
            $scope = 'private';
        }
        if (!in_array($scope, self::ALLOWED_SCOPES, true)) {
            $errors[] = 'scope must be one of: private, operational, platform_admin';
        }

        $status = strtolower((string)$this->sanitizeText($payload['status'] ?? 'active', 32));
        if ($status === '') {
            $status = 'active';
        }
        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
            $errors[] = 'status must be one of: active, inactive, archived';
        }

        $label = $this->sanitizeText($payload['label'] ?? null, 120);
        $sortOrder = $this->parseSortOrder($payload['sort_order'] ?? 100, $errors);
        $useForSecurity = $this->parseBoolean($payload['use_for_security'] ?? false, 'use_for_security', $errors);
        $useForPlatformAdmin = $this->parseBoolean($payload['use_for_platform_admin'] ?? false, 'use_for_platform_admin', $errors);
        $useForAppointments = $this->parseBoolean($payload['use_for_appointments'] ?? false, 'use_for_appointments', $errors);

        return [
            'contact_point' => [
                'type' => $type,
                'value' => $value,
                'normalized_value' => $normalizedValue,
                'label' => $label,
                'scope' => $scope,
                'use_for_security' => $useForSecurity,
                'use_for_platform_admin' => $useForPlatformAdmin,
                'use_for_appointments' => $useForAppointments,
                'status' => $status,
                'sort_order' => $sortOrder,
            ],
            'blocked_fields' => array_values(array_unique($blocked)),
            'unknown_fields' => array_values(array_unique($unknown)),
            'validation_errors' => array_values(array_unique($errors)),
        ];
    }

    private function prepareUpdatePayload(array $payload, array $current): array
    {
        $blocked = [];
        $unknown = [];
        $editable = [];
        $editableFields = [];
        foreach ($payload as $key => $_value) {
            $field = trim((string)$key);
            if ($field === '') {
                continue;
            }
            if (in_array($field, self::CREATE_BLOCKED_FIELDS, true)) {
                $blocked[] = $field;
                continue;
            }
            if (!in_array($field, self::CREATE_ALLOWED_FIELDS, true)) {
                $unknown[] = $field;
            }
        }

        $errors = [];
        if (array_key_exists('type', $payload)) {
            $type = strtolower((string)$this->sanitizeText($payload['type'] ?? null, 32));
            if ($type === '' || !in_array($type, self::ALLOWED_TYPES, true)) {
                $errors[] = 'type must be one of: email, phone, whatsapp';
            } else {
                $editable['type'] = $type;
                $editableFields[] = 'type';
            }
        }

        if (array_key_exists('value', $payload)) {
            $value = $this->sanitizeText($payload['value'] ?? null, 255);
            if ($value === null) {
                $errors[] = 'value is required';
            } else {
                $editable['value'] = $value;
                $editableFields[] = 'value';
            }
        }

        $finalType = (string)($editable['type'] ?? $current['type'] ?? '');
        $finalValue = (string)($editable['value'] ?? $current['value'] ?? '');
        if (array_key_exists('type', $editable) || array_key_exists('value', $editable)) {
            $normalizedValue = $this->repository->normalizeValue($finalType, $finalValue);
            if ($finalType === 'email' && filter_var($normalizedValue, FILTER_VALIDATE_EMAIL) === false) {
                $errors[] = 'value must be a valid email';
            }
            if (($finalType === 'phone' || $finalType === 'whatsapp') && ($normalizedValue === '' || $normalizedValue === '+')) {
                $errors[] = 'value must contain phone digits';
            }
            $editable['normalized_value'] = $normalizedValue;
        }

        if (array_key_exists('label', $payload)) {
            $editable['label'] = $this->sanitizeText($payload['label'] ?? null, 120);
            $editableFields[] = 'label';
        }

        if (array_key_exists('scope', $payload)) {
            $scope = strtolower((string)$this->sanitizeText($payload['scope'] ?? null, 32));
            if ($scope === '' || !in_array($scope, self::ALLOWED_SCOPES, true)) {
                $errors[] = 'scope must be one of: private, operational, platform_admin';
            } else {
                $editable['scope'] = $scope;
                $editableFields[] = 'scope';
            }
        }

        if (array_key_exists('status', $payload)) {
            $status = strtolower((string)$this->sanitizeText($payload['status'] ?? null, 32));
            if ($status === '' || !in_array($status, self::ALLOWED_STATUSES, true)) {
                $errors[] = 'status must be one of: active, inactive, archived';
            } else {
                $editable['status'] = $status;
                $editableFields[] = 'status';
            }
        }

        foreach (['use_for_security', 'use_for_platform_admin', 'use_for_appointments'] as $field) {
            if (!array_key_exists($field, $payload)) {
                continue;
            }
            $editable[$field] = $this->parseBoolean($payload[$field], $field, $errors);
            $editableFields[] = $field;
        }

        if (array_key_exists('sort_order', $payload)) {
            $editable['sort_order'] = $this->parseSortOrder($payload['sort_order'], $errors);
            $editableFields[] = 'sort_order';
        }

        return [
            'contact_point' => $editable,
            'editable_fields' => array_values(array_unique($editableFields)),
            'blocked_fields' => array_values(array_unique($blocked)),
            'unknown_fields' => array_values(array_unique($unknown)),
            'validation_errors' => array_values(array_unique($errors)),
        ];
    }

    private function sanitizeText($value, int $maxLen): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = trim(strip_tags((string)$value));
        if ($text === '') {
            return null;
        }
        if (mb_strlen($text, 'UTF-8') > $maxLen) {
            $text = mb_substr($text, 0, $maxLen, 'UTF-8');
            $text = rtrim($text);
        }
        return $text === '' ? null : $text;
    }

    private function parseBoolean($value, string $field, array &$errors): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) && ($value === 0 || $value === 1)) {
            return $value === 1;
        }
        if (is_string($value)) {
            $raw = strtolower(trim($value));
            if (in_array($raw, ['0', 'false'], true)) {
                return false;
            }
            if (in_array($raw, ['1', 'true'], true)) {
                return true;
            }
        }
        $errors[] = sprintf('%s must be boolean', $field);
        return false;
    }

    private function parseSortOrder($value, array &$errors): int
    {
        if (is_int($value)) {
            $sortOrder = $value;
        } elseif (is_string($value) && preg_match('/^\d+$/', trim($value)) === 1) {
            $sortOrder = (int)trim($value);
        } else {
            $errors[] = 'sort_order must be a non-negative integer';
            return 100;
        }

        if ($sortOrder < 0) {
            $errors[] = 'sort_order must be a non-negative integer';
            return 100;
        }
        return min($sortOrder, 100000);
    }

    private function isAssociative(array $payload): bool
    {
        if ($payload === []) {
            return true;
        }
        return array_keys($payload) !== range(0, count($payload) - 1);
    }

    private function isValidDoctorId(string $doctorId): bool
    {
        if ($doctorId === '' || strlen($doctorId) > 64) {
            return false;
        }
        return preg_match('/^[A-Za-z0-9._:-]+$/', $doctorId) === 1;
    }

    private function isValidContactPointId(string $contactPointId): bool
    {
        return preg_match('/^[1-9][0-9]*$/', $contactPointId) === 1;
    }
}
