<?php
declare(strict_types=1);

require_once __DIR__.'/../contracts/CanonicalAuditEnvelope.php';
require_once __DIR__.'/../services/AuditV1PhysicalMapper.php';

use Platform\Contracts\CanonicalAuditEnvelope;
use Platform\Services\AuditV1PhysicalMapper;

$staticTotal=0;$staticPass=0;$negativeTotal=0;$negativePass=0;
function a1g1TimeOk(bool $condition,string $name):void{global $staticTotal,$staticPass;$staticTotal++;if(!$condition)throw new RuntimeException($name);$staticPass++;}
function a1g1TimeBlocked(callable $operation,string $name):void{global $negativeTotal,$negativePass;$negativeTotal++;try{$operation();}catch(InvalidArgumentException $exception){if($exception->getMessage()==='invalid_canonical_audit_timestamp'){$negativePass++;return;}}throw new RuntimeException($name);}

$canonical=[
    'event_id'=>'018f2f3a-7b1c-7abc-8def-0123456789ab',
    'occurred_at'=>'2026-08-23T02:44:59.529251Z',
    'event_type'=>'AUTH_LOGIN_SUCCEEDED',
    'event_version'=>'audit.v1',
    'severity'=>'INFO',
    'result'=>'SUCCESS',
    'actor_identity_id'=>'identity-42',
    'actor_type'=>'ACCOUNT',
    'actor_role'=>'ACCOUNT_OWNER',
    'actor_scope'=>'SELF',
    'effective_entity_type'=>null,
    'effective_entity_id'=>null,
    'target_type'=>'ACCOUNT',
    'target_id'=>'identity-42',
    'session_id'=>'session-7',
    'request_id'=>'7a6db34d-9c6a-4da5-b5ca-c6c43c6b88e6',
    'correlation_id'=>'d2036e9f-11bd-456f-bdc6-de5c483c3f76',
    'source_module'=>'AUTH',
    'source_route'=>'POST /auth/login',
    'reason_code'=>'USER_REQUEST',
    'retention_class'=>'AUTH_SECURITY',
    'metadata_json'=>[],
    'ip_hmac'=>null,
    'user_agent_summary'=>null,
    'sequence_number'=>1,
    'previous_hash'=>str_repeat('0',64),
    'event_hash'=>str_repeat('a',64),
    'created_at'=>'2026-08-23T02:44:59.987654Z',
];
$envelope=CanonicalAuditEnvelope::assembleByWriter($canonical);$before=$envelope->toArray();$mapper=new AuditV1PhysicalMapper();$row=$mapper->map($envelope,'account:identity-42',null);

a1g1TimeOk($row['occurred_at_utc']==='2026-08-23 02:44:59.529251','occurred_at_mysql_datetime_6');
a1g1TimeOk($row['created_at_utc']==='2026-08-23 02:44:59.987654','created_at_mysql_datetime_6');
a1g1TimeOk(!str_contains($row['occurred_at_utc'],'T')&&!str_contains($row['occurred_at_utc'],'Z'),'occurred_at_no_rfc3339_markers');
a1g1TimeOk(!str_contains($row['created_at_utc'],'T')&&!str_contains($row['created_at_utc'],'Z'),'created_at_no_rfc3339_markers');
a1g1TimeOk(str_ends_with($row['occurred_at_utc'],'.529251'),'occurred_at_microseconds_preserved');
a1g1TimeOk(str_ends_with($row['created_at_utc'],'.987654'),'created_at_microseconds_preserved');
a1g1TimeOk($envelope->toArray()===$before,'canonical_envelope_unchanged');
a1g1TimeOk($envelope->value('occurred_at')==='2026-08-23T02:44:59.529251Z','canonical_occurred_at_preserved');
a1g1TimeOk($envelope->value('created_at')==='2026-08-23T02:44:59.987654Z','canonical_created_at_preserved');
a1g1TimeOk($envelope->value('event_hash')===str_repeat('a',64),'event_hash_input_preserved');

$mapWith=static function(string $field,string $value)use($canonical,$mapper):array{$changed=$canonical;$changed[$field]=$value;return $mapper->map(CanonicalAuditEnvelope::assembleByWriter($changed),'account:identity-42',null);};
a1g1TimeBlocked(fn()=>$mapWith('occurred_at','2026-08-23T02:44:59.529251+00:00'),'offset_blocked');
a1g1TimeBlocked(fn()=>$mapWith('occurred_at','2026-08-23T02:44:59.529251'),'missing_z_blocked');
a1g1TimeBlocked(fn()=>$mapWith('occurred_at','2026-08-23T02:44:59.52925Z'),'wrong_precision_blocked');
a1g1TimeBlocked(fn()=>$mapWith('occurred_at','2026-02-30T02:44:59.529251Z'),'invalid_date_blocked');
a1g1TimeBlocked(fn()=>$mapWith('occurred_at','2026-08-23T24:44:59.529251Z'),'invalid_time_blocked');
a1g1TimeBlocked(fn()=>$mapWith('created_at','not-a-canonical-time'),'invalid_created_at_blocked');

echo "DATETIME_MAPPING_STATIC_TEST_COUNT={$staticTotal}\nDATETIME_MAPPING_STATIC_TEST_PASS_COUNT={$staticPass}\nDATETIME_MAPPING_STATIC_TEST_FAIL_COUNT=".($staticTotal-$staticPass)."\n";
echo "DATETIME_MAPPING_NEGATIVE_TEST_COUNT={$negativeTotal}\nDATETIME_MAPPING_NEGATIVE_TEST_PASS_COUNT={$negativePass}\nFALSE_PASS_COUNT=".($negativeTotal-$negativePass)."\n";
