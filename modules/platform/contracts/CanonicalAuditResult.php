<?php
declare(strict_types=1);
namespace Platform\Contracts;
final class CanonicalAuditResult { private const VALUES=['SUCCESS','FAILURE','DENIED','PARTIAL']; public static function all(): array{return self::VALUES;} public static function assertKnown(string $v): string{if(!in_array($v,self::VALUES,true))throw new \InvalidArgumentException('unknown_audit_result');return $v;} }
