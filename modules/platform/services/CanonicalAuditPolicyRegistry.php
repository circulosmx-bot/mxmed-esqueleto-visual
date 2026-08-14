<?php
declare(strict_types=1);
namespace Platform\Services;
final class CanonicalAuditPolicyRegistry
{
    private array $rows=[];
    private function __construct(array $rows){foreach($rows as $row){$event=$row['event_type']??null;if(!is_string($event)||isset($this->rows[$event]))throw new \InvalidArgumentException('invalid_canonical_policy_rows');$this->rows[$event]=$row;}if(count($this->rows)!==28)throw new \InvalidArgumentException('canonical_policy_row_count');}
    public static function canonical(): self{return new self(self::canonicalRows());}
    public static function canonicalRows(): array{return [
            [
                'actor_required' => false,
                'allowed_producer_metadata' => [
                ],
                'allowed_reason_codes' => [
                    'USER_REQUEST',
                    'VALIDATION_FAILED',
                    'RATE_LIMITED',
                    'POLICY_DENIED',
                ],
                'allowed_result_reason_pairs' => [
                    [
                        'reason_code' => 'USER_REQUEST',
                        'result' => 'SUCCESS',
                    ],
                    [
                        'reason_code' => 'VALIDATION_FAILED',
                        'result' => 'FAILURE',
                    ],
                    [
                        'reason_code' => 'RATE_LIMITED',
                        'result' => 'FAILURE',
                    ],
                    [
                        'reason_code' => 'VALIDATION_FAILED',
                        'result' => 'DENIED',
                    ],
                    [
                        'reason_code' => 'RATE_LIMITED',
                        'result' => 'DENIED',
                    ],
                    [
                        'reason_code' => 'POLICY_DENIED',
                        'result' => 'DENIED',
                    ],
                ],
                'allowed_results' => [
                    'SUCCESS',
                    'FAILURE',
                    'DENIED',
                ],
                'allowed_writer_internal_metadata' => [
                ],
                'authority_status' => 'DIRECTOR_RATIFIED_COMPLETE',
                'event_type' => 'AUTH_REGISTRATION_REQUESTED',
                'producer' => 'Identity',
                'reason_required' => true,
                'retention_class' => 'AUTH_SECURITY',
                'self_timeline' => false,
                'session_required' => false,
                'severity' => 'INFO',
                'target_required' => true,
            ],
            [
                'actor_required' => false,
                'allowed_producer_metadata' => [
                ],
                'allowed_reason_codes' => [
                    'USER_REQUEST',
                    'SYSTEM_POLICY',
                    'EXTERNAL_PROVIDER_ERROR',
                    'RATE_LIMITED',
                ],
                'allowed_result_reason_pairs' => [
                    [
                        'reason_code' => 'USER_REQUEST',
                        'result' => 'SUCCESS',
                    ],
                    [
                        'reason_code' => 'SYSTEM_POLICY',
                        'result' => 'SUCCESS',
                    ],
                    [
                        'reason_code' => 'EXTERNAL_PROVIDER_ERROR',
                        'result' => 'FAILURE',
                    ],
                    [
                        'reason_code' => 'RATE_LIMITED',
                        'result' => 'FAILURE',
                    ],
                ],
                'allowed_results' => [
                    'SUCCESS',
                    'FAILURE',
                ],
                'allowed_writer_internal_metadata' => [
                ],
                'authority_status' => 'DIRECTOR_RATIFIED_COMPLETE',
                'event_type' => 'AUTH_EMAIL_VERIFICATION_SENT',
                'producer' => 'Identity',
                'reason_required' => true,
                'retention_class' => 'AUTH_SECURITY',
                'self_timeline' => false,
                'session_required' => false,
                'severity' => 'INFO',
                'target_required' => true,
            ],
            [
                'actor_required' => true,
                'allowed_producer_metadata' => [
                ],
                'allowed_reason_codes' => [
                    'USER_REQUEST',
                    'EXPIRED',
                    'REVOKED',
                    'VALIDATION_FAILED',
                ],
                'allowed_result_reason_pairs' => [
                    [
                        'reason_code' => 'USER_REQUEST',
                        'result' => 'SUCCESS',
                    ],
                    [
                        'reason_code' => 'EXPIRED',
                        'result' => 'FAILURE',
                    ],
                    [
                        'reason_code' => 'REVOKED',
                        'result' => 'FAILURE',
                    ],
                    [
                        'reason_code' => 'VALIDATION_FAILED',
                        'result' => 'FAILURE',
                    ],
                    [
                        'reason_code' => 'EXPIRED',
                        'result' => 'DENIED',
                    ],
                    [
                        'reason_code' => 'REVOKED',
                        'result' => 'DENIED',
                    ],
                    [
                        'reason_code' => 'VALIDATION_FAILED',
                        'result' => 'DENIED',
                    ],
                ],
                'allowed_results' => [
                    'SUCCESS',
                    'FAILURE',
                    'DENIED',
                ],
                'allowed_writer_internal_metadata' => [
                ],
                'authority_status' => 'DIRECTOR_RATIFIED_COMPLETE',
                'event_type' => 'AUTH_EMAIL_VERIFIED',
                'producer' => 'Identity',
                'reason_required' => true,
                'retention_class' => 'AUTH_SECURITY',
                'self_timeline' => true,
                'session_required' => false,
                'severity' => 'INFO',
                'target_required' => true,
            ],
            [
                'actor_required' => true,
                'allowed_producer_metadata' => [
                ],
                'allowed_reason_codes' => [
                    'USER_REQUEST',
                    'SYSTEM_POLICY',
                ],
                'allowed_result_reason_pairs' => [
                    [
                        'reason_code' => 'USER_REQUEST',
                        'result' => 'SUCCESS',
                    ],
                    [
                        'reason_code' => 'SYSTEM_POLICY',
                        'result' => 'SUCCESS',
                    ],
                ],
                'allowed_results' => [
                    'SUCCESS',
                ],
                'allowed_writer_internal_metadata' => [
                    'ip_hmac_key_version',
                ],
                'authority_status' => 'DIRECTOR_RATIFIED_COMPLETE',
                'event_type' => 'AUTH_LOGIN_SUCCEEDED',
                'producer' => 'Identity',
                'reason_required' => true,
                'retention_class' => 'AUTH_SECURITY',
                'self_timeline' => true,
                'session_required' => true,
                'severity' => 'INFO',
                'target_required' => true,
            ],
            [
                'actor_required' => false,
                'allowed_producer_metadata' => [
                ],
                'allowed_reason_codes' => [
                    'INVALID_CREDENTIALS',
                    'ACCOUNT_LOCKED',
                    'RATE_LIMITED',
                    'POLICY_DENIED',
                    'STEP_UP_REQUIRED',
                    'INTERNAL_ERROR',
                ],
                'allowed_result_reason_pairs' => [
                    [
                        'reason_code' => 'INVALID_CREDENTIALS',
                        'result' => 'FAILURE',
                    ],
                    [
                        'reason_code' => 'ACCOUNT_LOCKED',
                        'result' => 'FAILURE',
                    ],
                    [
                        'reason_code' => 'RATE_LIMITED',
                        'result' => 'FAILURE',
                    ],
                    [
                        'reason_code' => 'INTERNAL_ERROR',
                        'result' => 'FAILURE',
                    ],
                    [
                        'reason_code' => 'INVALID_CREDENTIALS',
                        'result' => 'DENIED',
                    ],
                    [
                        'reason_code' => 'ACCOUNT_LOCKED',
                        'result' => 'DENIED',
                    ],
                    [
                        'reason_code' => 'RATE_LIMITED',
                        'result' => 'DENIED',
                    ],
                    [
                        'reason_code' => 'POLICY_DENIED',
                        'result' => 'DENIED',
                    ],
                    [
                        'reason_code' => 'STEP_UP_REQUIRED',
                        'result' => 'DENIED',
                    ],
                ],
                'allowed_results' => [
                    'FAILURE',
                    'DENIED',
                ],
                'allowed_writer_internal_metadata' => [
                    'ip_hmac_key_version',
                ],
                'authority_status' => 'DIRECTOR_RATIFIED_COMPLETE',
                'event_type' => 'AUTH_LOGIN_FAILED',
                'producer' => 'Identity',
                'reason_required' => true,
                'retention_class' => 'AUTH_SECURITY',
                'self_timeline' => true,
                'session_required' => false,
                'severity' => 'WARN',
                'target_required' => true,
            ],
            [
                'actor_required' => false,
                'allowed_producer_metadata' => [
                ],
                'allowed_reason_codes' => [
                    'USER_REQUEST',
                    'RATE_LIMITED',
                    'POLICY_DENIED',
                ],
                'allowed_result_reason_pairs' => [
                    [
                        'reason_code' => 'USER_REQUEST',
                        'result' => 'SUCCESS',
                    ],
                    [
                        'reason_code' => 'RATE_LIMITED',
                        'result' => 'FAILURE',
                    ],
                    [
                        'reason_code' => 'RATE_LIMITED',
                        'result' => 'DENIED',
                    ],
                    [
                        'reason_code' => 'POLICY_DENIED',
                        'result' => 'DENIED',
                    ],
                ],
                'allowed_results' => [
                    'SUCCESS',
                    'FAILURE',
                    'DENIED',
                ],
                'allowed_writer_internal_metadata' => [
                ],
                'authority_status' => 'DIRECTOR_RATIFIED_COMPLETE',
                'event_type' => 'AUTH_PASSWORD_RECOVERY_REQUESTED',
                'producer' => 'Identity',
                'reason_required' => true,
                'retention_class' => 'AUTH_SECURITY',
                'self_timeline' => true,
                'session_required' => false,
                'severity' => 'INFO',
                'target_required' => true,
            ],
            [
                'actor_required' => true,
                'allowed_producer_metadata' => [
                ],
                'allowed_reason_codes' => [
                    'USER_REQUEST',
                    'EXPIRED',
                    'REVOKED',
                    'VALIDATION_FAILED',
                ],
                'allowed_result_reason_pairs' => [
                    [
                        'reason_code' => 'USER_REQUEST',
                        'result' => 'SUCCESS',
                    ],
                ],
                'allowed_results' => [
                    'SUCCESS',
                ],
                'allowed_writer_internal_metadata' => [
                ],
                'authority_status' => 'DIRECTOR_RATIFIED_COMPLETE',
                'event_type' => 'AUTH_PASSWORD_RESET_SUCCEEDED',
                'producer' => 'Identity',
                'reason_required' => true,
                'retention_class' => 'AUTH_SECURITY',
                'self_timeline' => true,
                'session_required' => false,
                'severity' => 'HIGH',
                'target_required' => true,
            ],
            [
                'actor_required' => true,
                'allowed_producer_metadata' => [
                    'changed_field_names',
                ],
                'allowed_reason_codes' => [
                    'USER_REQUEST',
                    'ADMIN_DECISION',
                    'SECURITY_RESPONSE',
                    'VALIDATION_FAILED',
                    'POLICY_DENIED',
                ],
                'allowed_result_reason_pairs' => [
                    [
                        'reason_code' => 'USER_REQUEST',
                        'result' => 'SUCCESS',
                    ],
                    [
                        'reason_code' => 'ADMIN_DECISION',
                        'result' => 'SUCCESS',
                    ],
                    [
                        'reason_code' => 'SECURITY_RESPONSE',
                        'result' => 'SUCCESS',
                    ],
                    [
                        'reason_code' => 'VALIDATION_FAILED',
                        'result' => 'FAILURE',
                    ],
                    [
                        'reason_code' => 'VALIDATION_FAILED',
                        'result' => 'DENIED',
                    ],
                    [
                        'reason_code' => 'POLICY_DENIED',
                        'result' => 'DENIED',
                    ],
                ],
                'allowed_results' => [
                    'SUCCESS',
                    'FAILURE',
                    'DENIED',
                ],
                'allowed_writer_internal_metadata' => [
                ],
                'authority_status' => 'DIRECTOR_RATIFIED_COMPLETE',
                'event_type' => 'AUTH_PASSWORD_CHANGED',
                'producer' => 'Identity',
                'reason_required' => true,
                'retention_class' => 'AUTH_SECURITY',
                'self_timeline' => true,
                'session_required' => true,
                'severity' => 'HIGH',
                'target_required' => true,
            ],
            [
                'actor_required' => true,
                'allowed_producer_metadata' => [
                ],
                'allowed_reason_codes' => [
                    'USER_REQUEST',
                    'SYSTEM_POLICY',
                    'STEP_UP_REQUIRED',
                    'INTERNAL_ERROR',
                    'POLICY_DENIED',
                ],
                'allowed_result_reason_pairs' => [
                    [
                        'reason_code' => 'USER_REQUEST',
                        'result' => 'SUCCESS',
                    ],
                    [
                        'reason_code' => 'SYSTEM_POLICY',
                        'result' => 'SUCCESS',
                    ],
                    [
                        'reason_code' => 'STEP_UP_REQUIRED',
                        'result' => 'SUCCESS',
                    ],
                    [
                        'reason_code' => 'INTERNAL_ERROR',
                        'result' => 'FAILURE',
                    ],
                    [
                        'reason_code' => 'STEP_UP_REQUIRED',
                        'result' => 'DENIED',
                    ],
                    [
                        'reason_code' => 'POLICY_DENIED',
                        'result' => 'DENIED',
                    ],
                ],
                'allowed_results' => [
                    'SUCCESS',
                    'FAILURE',
                    'DENIED',
                ],
                'allowed_writer_internal_metadata' => [
                    'ip_hmac_key_version',
                ],
                'authority_status' => 'DIRECTOR_RATIFIED_COMPLETE',
                'event_type' => 'AUTH_SESSION_CREATED',
                'producer' => 'Sessions',
                'reason_required' => true,
                'retention_class' => 'AUTH_SECURITY',
                'self_timeline' => true,
                'session_required' => true,
                'severity' => 'INFO',
                'target_required' => true,
            ],
            [
                'actor_required' => true,
                'allowed_producer_metadata' => [
                ],
                'allowed_reason_codes' => [
                    'SYSTEM_POLICY',
                    'SECURITY_RESPONSE',
                    'EXPIRED',
                    'STATE_CONFLICT',
                    'POLICY_DENIED',
                ],
                'allowed_result_reason_pairs' => [
                    [
                        'reason_code' => 'SYSTEM_POLICY',
                        'result' => 'SUCCESS',
                    ],
                    [
                        'reason_code' => 'SECURITY_RESPONSE',
                        'result' => 'SUCCESS',
                    ],
                    [
                        'reason_code' => 'EXPIRED',
                        'result' => 'FAILURE',
                    ],
                    [
                        'reason_code' => 'STATE_CONFLICT',
                        'result' => 'FAILURE',
                    ],
                    [
                        'reason_code' => 'EXPIRED',
                        'result' => 'DENIED',
                    ],
                    [
                        'reason_code' => 'POLICY_DENIED',
                        'result' => 'DENIED',
                    ],
                ],
                'allowed_results' => [
                    'SUCCESS',
                    'FAILURE',
                    'DENIED',
                ],
                'allowed_writer_internal_metadata' => [
                ],
                'authority_status' => 'DIRECTOR_RATIFIED_COMPLETE',
                'event_type' => 'AUTH_SESSION_ROTATED',
                'producer' => 'Sessions',
                'reason_required' => true,
                'retention_class' => 'AUTH_SECURITY',
                'self_timeline' => false,
                'session_required' => true,
                'severity' => 'INFO',
                'target_required' => true,
            ],
            [
                'actor_required' => true,
                'allowed_producer_metadata' => [
                ],
                'allowed_reason_codes' => [
                    'USER_REQUEST',
                    'ADMIN_DECISION',
                    'REVOKED',
                    'SECURITY_RESPONSE',
                    'INTERNAL_ERROR',
                ],
                'allowed_result_reason_pairs' => [
                    [
                        'reason_code' => 'USER_REQUEST',
                        'result' => 'SUCCESS',
                    ],
                    [
                        'reason_code' => 'ADMIN_DECISION',
                        'result' => 'SUCCESS',
                    ],
                    [
                        'reason_code' => 'SECURITY_RESPONSE',
                        'result' => 'SUCCESS',
                    ],
                    [
                        'reason_code' => 'REVOKED',
                        'result' => 'FAILURE',
                    ],
                    [
                        'reason_code' => 'INTERNAL_ERROR',
                        'result' => 'FAILURE',
                    ],
                ],
                'allowed_results' => [
                    'SUCCESS',
                    'FAILURE',
                ],
                'allowed_writer_internal_metadata' => [
                ],
                'authority_status' => 'DIRECTOR_RATIFIED_COMPLETE',
                'event_type' => 'AUTH_SESSION_REVOKED',
                'producer' => 'Sessions',
                'reason_required' => true,
                'retention_class' => 'AUTH_SECURITY',
                'self_timeline' => true,
                'session_required' => true,
                'severity' => 'HIGH',
                'target_required' => true,
            ],
            [
                'actor_required' => true,
                'allowed_producer_metadata' => [
                ],
                'allowed_reason_codes' => [
                    'USER_REQUEST',
                    'SYSTEM_POLICY',
                    'INTERNAL_ERROR',
                ],
                'allowed_result_reason_pairs' => [
                    [
                        'reason_code' => 'USER_REQUEST',
                        'result' => 'SUCCESS',
                    ],
                    [
                        'reason_code' => 'SYSTEM_POLICY',
                        'result' => 'SUCCESS',
                    ],
                    [
                        'reason_code' => 'INTERNAL_ERROR',
                        'result' => 'FAILURE',
                    ],
                ],
                'allowed_results' => [
                    'SUCCESS',
                    'FAILURE',
                ],
                'allowed_writer_internal_metadata' => [
                ],
                'authority_status' => 'DIRECTOR_RATIFIED_COMPLETE',
                'event_type' => 'AUTH_LOGOUT',
                'producer' => 'Sessions',
                'reason_required' => true,
                'retention_class' => 'AUTH_SECURITY',
                'self_timeline' => true,
                'session_required' => true,
                'severity' => 'INFO',
                'target_required' => true,
            ],
            [
                'actor_required' => true,
                'allowed_producer_metadata' => [
                ],
                'allowed_reason_codes' => [
                    'USER_REQUEST',
                    'SECURITY_RESPONSE',
                    'ADMIN_DECISION',
                    'INTERNAL_ERROR',
                    'STATE_CONFLICT',
                ],
                'allowed_result_reason_pairs' => [
                    [
                        'reason_code' => 'USER_REQUEST',
                        'result' => 'SUCCESS',
                    ],
                    [
                        'reason_code' => 'SECURITY_RESPONSE',
                        'result' => 'SUCCESS',
                    ],
                    [
                        'reason_code' => 'ADMIN_DECISION',
                        'result' => 'SUCCESS',
                    ],
                    [
                        'reason_code' => 'INTERNAL_ERROR',
                        'result' => 'FAILURE',
                    ],
                    [
                        'reason_code' => 'STATE_CONFLICT',
                        'result' => 'FAILURE',
                    ],
                    [
                        'reason_code' => 'INTERNAL_ERROR',
                        'result' => 'PARTIAL',
                    ],
                    [
                        'reason_code' => 'STATE_CONFLICT',
                        'result' => 'PARTIAL',
                    ],
                ],
                'allowed_results' => [
                    'SUCCESS',
                    'FAILURE',
                    'PARTIAL',
                ],
                'allowed_writer_internal_metadata' => [
                ],
                'authority_status' => 'DIRECTOR_RATIFIED_COMPLETE',
                'event_type' => 'AUTH_LOGOUT_ALL',
                'producer' => 'Sessions',
                'reason_required' => true,
                'retention_class' => 'AUTH_SECURITY',
                'self_timeline' => true,
                'session_required' => false,
                'severity' => 'HIGH',
                'target_required' => true,
            ],
            [
                'actor_required' => true,
                'allowed_producer_metadata' => [
                ],
                'allowed_reason_codes' => [
                    'USER_REQUEST',
                    'VALIDATION_FAILED',
                    'POLICY_DENIED',
                ],
                'allowed_result_reason_pairs' => [
                    [
                        'reason_code' => 'USER_REQUEST',
                        'result' => 'SUCCESS',
                    ],
                    [
                        'reason_code' => 'VALIDATION_FAILED',
                        'result' => 'FAILURE',
                    ],
                    [
                        'reason_code' => 'VALIDATION_FAILED',
                        'result' => 'DENIED',
                    ],
                    [
                        'reason_code' => 'POLICY_DENIED',
                        'result' => 'DENIED',
                    ],
                ],
                'allowed_results' => [
                    'SUCCESS',
                    'FAILURE',
                    'DENIED',
                ],
                'allowed_writer_internal_metadata' => [
                ],
                'authority_status' => 'DIRECTOR_RATIFIED_COMPLETE',
                'event_type' => 'PROFILE_CLAIM_REQUESTED',
                'producer' => 'Ownership',
                'reason_required' => true,
                'retention_class' => 'OWNERSHIP',
                'self_timeline' => false,
                'session_required' => true,
                'severity' => 'INFO',
                'target_required' => true,
            ],
            [
                'actor_required' => true,
                'allowed_producer_metadata' => [
                ],
                'allowed_reason_codes' => [
                    'ADMIN_DECISION',
                    'SYSTEM_POLICY',
                ],
                'allowed_result_reason_pairs' => [
                    [
                        'reason_code' => 'ADMIN_DECISION',
                        'result' => 'SUCCESS',
                    ],
                    [
                        'reason_code' => 'SYSTEM_POLICY',
                        'result' => 'SUCCESS',
                    ],
                ],
                'allowed_results' => [
                    'SUCCESS',
                ],
                'allowed_writer_internal_metadata' => [
                ],
                'authority_status' => 'DIRECTOR_RATIFIED_COMPLETE',
                'event_type' => 'PROFILE_CLAIM_APPROVED',
                'producer' => 'Ownership',
                'reason_required' => true,
                'retention_class' => 'OWNERSHIP',
                'self_timeline' => false,
                'session_required' => true,
                'severity' => 'HIGH',
                'target_required' => true,
            ],
            [
                'actor_required' => true,
                'allowed_producer_metadata' => [
                ],
                'allowed_reason_codes' => [
                    'ADMIN_DECISION',
                    'POLICY_DENIED',
                    'VALIDATION_FAILED',
                ],
                'allowed_result_reason_pairs' => [
                    [
                        'reason_code' => 'POLICY_DENIED',
                        'result' => 'DENIED',
                    ],
                    [
                        'reason_code' => 'VALIDATION_FAILED',
                        'result' => 'DENIED',
                    ],
                ],
                'allowed_results' => [
                    'DENIED',
                ],
                'allowed_writer_internal_metadata' => [
                ],
                'authority_status' => 'DIRECTOR_RATIFIED_COMPLETE',
                'event_type' => 'PROFILE_CLAIM_REJECTED',
                'producer' => 'Ownership',
                'reason_required' => true,
                'retention_class' => 'OWNERSHIP',
                'self_timeline' => false,
                'session_required' => true,
                'severity' => 'WARN',
                'target_required' => true,
            ],
            [
                'actor_required' => true,
                'allowed_producer_metadata' => [
                ],
                'allowed_reason_codes' => [
                    'ADMIN_DECISION',
                    'SYSTEM_POLICY',
                    'STATE_CONFLICT',
                    'POLICY_DENIED',
                ],
                'allowed_result_reason_pairs' => [
                    [
                        'reason_code' => 'ADMIN_DECISION',
                        'result' => 'SUCCESS',
                    ],
                    [
                        'reason_code' => 'SYSTEM_POLICY',
                        'result' => 'SUCCESS',
                    ],
                    [
                        'reason_code' => 'STATE_CONFLICT',
                        'result' => 'FAILURE',
                    ],
                    [
                        'reason_code' => 'POLICY_DENIED',
                        'result' => 'DENIED',
                    ],
                ],
                'allowed_results' => [
                    'SUCCESS',
                    'FAILURE',
                    'DENIED',
                ],
                'allowed_writer_internal_metadata' => [
                ],
                'authority_status' => 'DIRECTOR_RATIFIED_COMPLETE',
                'event_type' => 'PROFILE_OWNERSHIP_ASSIGNED',
                'producer' => 'Ownership',
                'reason_required' => true,
                'retention_class' => 'OWNERSHIP',
                'self_timeline' => false,
                'session_required' => true,
                'severity' => 'HIGH',
                'target_required' => true,
            ],
            [
                'actor_required' => true,
                'allowed_producer_metadata' => [
                    'changed_field_names',
                ],
                'allowed_reason_codes' => [
                    'ADMIN_DECISION',
                    'USER_REQUEST',
                    'STATE_CONFLICT',
                    'POLICY_DENIED',
                ],
                'allowed_result_reason_pairs' => [
                    [
                        'reason_code' => 'ADMIN_DECISION',
                        'result' => 'SUCCESS',
                    ],
                    [
                        'reason_code' => 'USER_REQUEST',
                        'result' => 'SUCCESS',
                    ],
                    [
                        'reason_code' => 'STATE_CONFLICT',
                        'result' => 'FAILURE',
                    ],
                    [
                        'reason_code' => 'POLICY_DENIED',
                        'result' => 'DENIED',
                    ],
                ],
                'allowed_results' => [
                    'SUCCESS',
                    'FAILURE',
                    'DENIED',
                ],
                'allowed_writer_internal_metadata' => [
                ],
                'authority_status' => 'DIRECTOR_RATIFIED_COMPLETE',
                'event_type' => 'PROFILE_OWNERSHIP_TRANSFERRED',
                'producer' => 'Ownership',
                'reason_required' => true,
                'retention_class' => 'OWNERSHIP',
                'self_timeline' => false,
                'session_required' => true,
                'severity' => 'CRITICAL',
                'target_required' => true,
            ],
            [
                'actor_required' => true,
                'allowed_producer_metadata' => [
                ],
                'allowed_reason_codes' => [
                    'USER_REQUEST',
                    'ADMIN_DECISION',
                    'EXTERNAL_PROVIDER_ERROR',
                    'POLICY_DENIED',
                ],
                'allowed_result_reason_pairs' => [
                    [
                        'reason_code' => 'USER_REQUEST',
                        'result' => 'SUCCESS',
                    ],
                    [
                        'reason_code' => 'ADMIN_DECISION',
                        'result' => 'SUCCESS',
                    ],
                    [
                        'reason_code' => 'EXTERNAL_PROVIDER_ERROR',
                        'result' => 'FAILURE',
                    ],
                    [
                        'reason_code' => 'POLICY_DENIED',
                        'result' => 'DENIED',
                    ],
                ],
                'allowed_results' => [
                    'SUCCESS',
                    'FAILURE',
                    'DENIED',
                ],
                'allowed_writer_internal_metadata' => [
                ],
                'authority_status' => 'DIRECTOR_RATIFIED_COMPLETE',
                'event_type' => 'INVITATION_CREATED',
                'producer' => 'Ownership',
                'reason_required' => true,
                'retention_class' => 'OWNERSHIP',
                'self_timeline' => false,
                'session_required' => true,
                'severity' => 'INFO',
                'target_required' => true,
            ],
            [
                'actor_required' => true,
                'allowed_producer_metadata' => [
                ],
                'allowed_reason_codes' => [
                    'USER_REQUEST',
                    'EXPIRED',
                    'REVOKED',
                    'STATE_CONFLICT',
                    'POLICY_DENIED',
                ],
                'allowed_result_reason_pairs' => [
                    [
                        'reason_code' => 'USER_REQUEST',
                        'result' => 'SUCCESS',
                    ],
                    [
                        'reason_code' => 'EXPIRED',
                        'result' => 'FAILURE',
                    ],
                    [
                        'reason_code' => 'REVOKED',
                        'result' => 'FAILURE',
                    ],
                    [
                        'reason_code' => 'STATE_CONFLICT',
                        'result' => 'FAILURE',
                    ],
                    [
                        'reason_code' => 'EXPIRED',
                        'result' => 'DENIED',
                    ],
                    [
                        'reason_code' => 'REVOKED',
                        'result' => 'DENIED',
                    ],
                    [
                        'reason_code' => 'POLICY_DENIED',
                        'result' => 'DENIED',
                    ],
                ],
                'allowed_results' => [
                    'SUCCESS',
                    'FAILURE',
                    'DENIED',
                ],
                'allowed_writer_internal_metadata' => [
                ],
                'authority_status' => 'DIRECTOR_RATIFIED_COMPLETE',
                'event_type' => 'INVITATION_ACCEPTED',
                'producer' => 'Ownership',
                'reason_required' => true,
                'retention_class' => 'OWNERSHIP',
                'self_timeline' => false,
                'session_required' => true,
                'severity' => 'HIGH',
                'target_required' => true,
            ],
            [
                'actor_required' => true,
                'allowed_producer_metadata' => [
                ],
                'allowed_reason_codes' => [
                    'ADMIN_DECISION',
                    'SECURITY_RESPONSE',
                    'REVOKED',
                    'INTERNAL_ERROR',
                ],
                'allowed_result_reason_pairs' => [
                    [
                        'reason_code' => 'ADMIN_DECISION',
                        'result' => 'SUCCESS',
                    ],
                    [
                        'reason_code' => 'SECURITY_RESPONSE',
                        'result' => 'SUCCESS',
                    ],
                    [
                        'reason_code' => 'REVOKED',
                        'result' => 'FAILURE',
                    ],
                    [
                        'reason_code' => 'INTERNAL_ERROR',
                        'result' => 'FAILURE',
                    ],
                ],
                'allowed_results' => [
                    'SUCCESS',
                    'FAILURE',
                ],
                'allowed_writer_internal_metadata' => [
                ],
                'authority_status' => 'DIRECTOR_RATIFIED_COMPLETE',
                'event_type' => 'INVITATION_REVOKED',
                'producer' => 'Ownership',
                'reason_required' => true,
                'retention_class' => 'OWNERSHIP',
                'self_timeline' => false,
                'session_required' => true,
                'severity' => 'HIGH',
                'target_required' => true,
            ],
            [
                'actor_required' => true,
                'allowed_producer_metadata' => [
                    'changed_field_names',
                ],
                'allowed_reason_codes' => [
                    'ADMIN_DECISION',
                    'SYSTEM_POLICY',
                    'STATE_CONFLICT',
                    'POLICY_DENIED',
                ],
                'allowed_result_reason_pairs' => [
                    [
                        'reason_code' => 'ADMIN_DECISION',
                        'result' => 'SUCCESS',
                    ],
                    [
                        'reason_code' => 'SYSTEM_POLICY',
                        'result' => 'SUCCESS',
                    ],
                    [
                        'reason_code' => 'STATE_CONFLICT',
                        'result' => 'FAILURE',
                    ],
                    [
                        'reason_code' => 'POLICY_DENIED',
                        'result' => 'DENIED',
                    ],
                ],
                'allowed_results' => [
                    'SUCCESS',
                    'FAILURE',
                    'DENIED',
                ],
                'allowed_writer_internal_metadata' => [
                ],
                'authority_status' => 'DIRECTOR_RATIFIED_COMPLETE',
                'event_type' => 'ROLE_ASSIGNED',
                'producer' => 'Roles',
                'reason_required' => true,
                'retention_class' => 'ROLE_ADMIN',
                'self_timeline' => false,
                'session_required' => true,
                'severity' => 'HIGH',
                'target_required' => true,
            ],
            [
                'actor_required' => true,
                'allowed_producer_metadata' => [
                    'changed_field_names',
                ],
                'allowed_reason_codes' => [
                    'ADMIN_DECISION',
                    'SECURITY_RESPONSE',
                    'REVOKED',
                    'INTERNAL_ERROR',
                ],
                'allowed_result_reason_pairs' => [
                    [
                        'reason_code' => 'ADMIN_DECISION',
                        'result' => 'SUCCESS',
                    ],
                    [
                        'reason_code' => 'SECURITY_RESPONSE',
                        'result' => 'SUCCESS',
                    ],
                    [
                        'reason_code' => 'REVOKED',
                        'result' => 'FAILURE',
                    ],
                    [
                        'reason_code' => 'INTERNAL_ERROR',
                        'result' => 'FAILURE',
                    ],
                ],
                'allowed_results' => [
                    'SUCCESS',
                    'FAILURE',
                ],
                'allowed_writer_internal_metadata' => [
                ],
                'authority_status' => 'DIRECTOR_RATIFIED_COMPLETE',
                'event_type' => 'ROLE_REVOKED',
                'producer' => 'Roles',
                'reason_required' => true,
                'retention_class' => 'ROLE_ADMIN',
                'self_timeline' => false,
                'session_required' => true,
                'severity' => 'HIGH',
                'target_required' => true,
            ],
            [
                'actor_required' => true,
                'allowed_producer_metadata' => [
                ],
                'allowed_reason_codes' => [
                    'STEP_UP_REQUIRED',
                    'USER_REQUEST',
                ],
                'allowed_result_reason_pairs' => [
                    [
                        'reason_code' => 'STEP_UP_REQUIRED',
                        'result' => 'SUCCESS',
                    ],
                    [
                        'reason_code' => 'USER_REQUEST',
                        'result' => 'SUCCESS',
                    ],
                ],
                'allowed_results' => [
                    'SUCCESS',
                ],
                'allowed_writer_internal_metadata' => [
                ],
                'authority_status' => 'DIRECTOR_RATIFIED_COMPLETE',
                'event_type' => 'STEP_UP_CHALLENGE_SUCCEEDED',
                'producer' => 'Roles',
                'reason_required' => true,
                'retention_class' => 'ROLE_ADMIN',
                'self_timeline' => true,
                'session_required' => true,
                'severity' => 'HIGH',
                'target_required' => true,
            ],
            [
                'actor_required' => true,
                'allowed_producer_metadata' => [
                ],
                'allowed_reason_codes' => [
                    'INVALID_CREDENTIALS',
                    'EXPIRED',
                    'RATE_LIMITED',
                    'POLICY_DENIED',
                ],
                'allowed_result_reason_pairs' => [
                    [
                        'reason_code' => 'INVALID_CREDENTIALS',
                        'result' => 'FAILURE',
                    ],
                    [
                        'reason_code' => 'EXPIRED',
                        'result' => 'FAILURE',
                    ],
                    [
                        'reason_code' => 'RATE_LIMITED',
                        'result' => 'FAILURE',
                    ],
                    [
                        'reason_code' => 'INVALID_CREDENTIALS',
                        'result' => 'DENIED',
                    ],
                    [
                        'reason_code' => 'EXPIRED',
                        'result' => 'DENIED',
                    ],
                    [
                        'reason_code' => 'RATE_LIMITED',
                        'result' => 'DENIED',
                    ],
                    [
                        'reason_code' => 'POLICY_DENIED',
                        'result' => 'DENIED',
                    ],
                ],
                'allowed_results' => [
                    'FAILURE',
                    'DENIED',
                ],
                'allowed_writer_internal_metadata' => [
                ],
                'authority_status' => 'DIRECTOR_RATIFIED_COMPLETE',
                'event_type' => 'STEP_UP_CHALLENGE_FAILED',
                'producer' => 'Roles',
                'reason_required' => true,
                'retention_class' => 'ROLE_ADMIN',
                'self_timeline' => true,
                'session_required' => true,
                'severity' => 'HIGH',
                'target_required' => true,
            ],
            [
                'actor_required' => true,
                'allowed_producer_metadata' => [
                ],
                'allowed_reason_codes' => [
                    'ADMIN_DECISION',
                    'SECURITY_RESPONSE',
                    'STEP_UP_REQUIRED',
                    'POLICY_DENIED',
                    'INTERNAL_ERROR',
                ],
                'allowed_result_reason_pairs' => [
                    [
                        'reason_code' => 'ADMIN_DECISION',
                        'result' => 'SUCCESS',
                    ],
                    [
                        'reason_code' => 'SECURITY_RESPONSE',
                        'result' => 'SUCCESS',
                    ],
                    [
                        'reason_code' => 'STEP_UP_REQUIRED',
                        'result' => 'SUCCESS',
                    ],
                    [
                        'reason_code' => 'INTERNAL_ERROR',
                        'result' => 'FAILURE',
                    ],
                    [
                        'reason_code' => 'STEP_UP_REQUIRED',
                        'result' => 'DENIED',
                    ],
                    [
                        'reason_code' => 'POLICY_DENIED',
                        'result' => 'DENIED',
                    ],
                ],
                'allowed_results' => [
                    'SUCCESS',
                    'FAILURE',
                    'DENIED',
                ],
                'allowed_writer_internal_metadata' => [
                ],
                'authority_status' => 'DIRECTOR_RATIFIED_COMPLETE',
                'event_type' => 'BREAK_GLASS_STARTED',
                'producer' => 'Roles',
                'reason_required' => true,
                'retention_class' => 'BREAK_GLASS_LEGAL_HOLD',
                'self_timeline' => false,
                'session_required' => true,
                'severity' => 'CRITICAL',
                'target_required' => true,
            ],
            [
                'actor_required' => true,
                'allowed_producer_metadata' => [
                ],
                'allowed_reason_codes' => [
                    'ADMIN_DECISION',
                    'EXPIRED',
                    'SECURITY_RESPONSE',
                    'INTERNAL_ERROR',
                    'STATE_CONFLICT',
                ],
                'allowed_result_reason_pairs' => [
                    [
                        'reason_code' => 'ADMIN_DECISION',
                        'result' => 'SUCCESS',
                    ],
                    [
                        'reason_code' => 'SECURITY_RESPONSE',
                        'result' => 'SUCCESS',
                    ],
                    [
                        'reason_code' => 'EXPIRED',
                        'result' => 'FAILURE',
                    ],
                    [
                        'reason_code' => 'INTERNAL_ERROR',
                        'result' => 'FAILURE',
                    ],
                    [
                        'reason_code' => 'STATE_CONFLICT',
                        'result' => 'FAILURE',
                    ],
                    [
                        'reason_code' => 'INTERNAL_ERROR',
                        'result' => 'PARTIAL',
                    ],
                    [
                        'reason_code' => 'STATE_CONFLICT',
                        'result' => 'PARTIAL',
                    ],
                ],
                'allowed_results' => [
                    'SUCCESS',
                    'FAILURE',
                    'PARTIAL',
                ],
                'allowed_writer_internal_metadata' => [
                ],
                'authority_status' => 'DIRECTOR_RATIFIED_COMPLETE',
                'event_type' => 'BREAK_GLASS_ENDED',
                'producer' => 'Roles',
                'reason_required' => true,
                'retention_class' => 'BREAK_GLASS_LEGAL_HOLD',
                'self_timeline' => false,
                'session_required' => true,
                'severity' => 'CRITICAL',
                'target_required' => true,
            ],
            [
                'actor_required' => true,
                'allowed_producer_metadata' => [
                    'changed_field_names',
                ],
                'allowed_reason_codes' => [
                    'ADMIN_DECISION',
                    'SECURITY_RESPONSE',
                    'POLICY_DENIED',
                    'STATE_CONFLICT',
                    'INTERNAL_ERROR',
                ],
                'allowed_result_reason_pairs' => [
                    [
                        'reason_code' => 'ADMIN_DECISION',
                        'result' => 'SUCCESS',
                    ],
                    [
                        'reason_code' => 'SECURITY_RESPONSE',
                        'result' => 'SUCCESS',
                    ],
                    [
                        'reason_code' => 'STATE_CONFLICT',
                        'result' => 'FAILURE',
                    ],
                    [
                        'reason_code' => 'INTERNAL_ERROR',
                        'result' => 'FAILURE',
                    ],
                    [
                        'reason_code' => 'POLICY_DENIED',
                        'result' => 'DENIED',
                    ],
                    [
                        'reason_code' => 'STATE_CONFLICT',
                        'result' => 'PARTIAL',
                    ],
                    [
                        'reason_code' => 'INTERNAL_ERROR',
                        'result' => 'PARTIAL',
                    ],
                ],
                'allowed_results' => [
                    'SUCCESS',
                    'FAILURE',
                    'DENIED',
                    'PARTIAL',
                ],
                'allowed_writer_internal_metadata' => [
                ],
                'authority_status' => 'DIRECTOR_RATIFIED_COMPLETE',
                'event_type' => 'SENSITIVE_ADMIN_ACTION',
                'producer' => 'Roles',
                'reason_required' => true,
                'retention_class' => 'ROLE_ADMIN',
                'self_timeline' => false,
                'session_required' => true,
                'severity' => 'HIGH',
                'target_required' => true,
            ],
        ];}
    public function count(): int{return count($this->rows);}
    public function policyFor(string $event): array{return $this->rows[$event]??throw new \InvalidArgumentException('unknown_audit_event_type');}
    public function assertAllowed(string $event,string $result,string $reason): array{$row=$this->policyFor($event);foreach($row['allowed_result_reason_pairs'] as $pair)if($pair['result']===$result&&$pair['reason_code']===$reason)return $row;throw new \InvalidArgumentException('result_reason_pair_not_allowed');}
}
