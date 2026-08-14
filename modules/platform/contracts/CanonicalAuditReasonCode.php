<?php
declare(strict_types=1);
namespace Platform\Contracts;
final class CanonicalAuditReasonCode { private const VALUES=['USER_REQUEST','SYSTEM_POLICY','ADMIN_DECISION','INVALID_CREDENTIALS','ACCOUNT_LOCKED','EXPIRED','REVOKED','POLICY_DENIED','VALIDATION_FAILED','RATE_LIMITED','STATE_CONFLICT','SECURITY_RESPONSE','STEP_UP_REQUIRED','INTERNAL_ERROR','EXTERNAL_PROVIDER_ERROR']; public static function all(): array{return self::VALUES;} public static function assertKnown(string $v): string{if(!in_array($v,self::VALUES,true))throw new \InvalidArgumentException('unknown_audit_reason');return $v;} }
