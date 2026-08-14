<?php
declare(strict_types=1);
namespace Platform\Services;
use Platform\Contracts\CanonicalAuditEnvelope;
final class CanonicalAuditSerializer
{
    public const CANONICALIZATION_VERSION='audit-v1-json-v1'; public const HASH_VERSION='sha256-hex-v1';
    private const FIELDS=['canonicalization_version','hash_version','stream_key','event_id','occurred_at','event_type','event_version','severity','result','actor_identity_id','actor_type','actor_role','actor_scope','effective_entity_type','effective_entity_id','target_type','target_id','session_id','request_id','correlation_id','source_module','source_route','reason_code','retention_class','metadata_json','ip_hmac','user_agent_summary','sequence_number','previous_hash','created_at'];
    public function bytes(CanonicalAuditEnvelope $envelope,string $streamKey): string { $source=['canonicalization_version'=>self::CANONICALIZATION_VERSION,'hash_version'=>self::HASH_VERSION,'stream_key'=>$streamKey]+$envelope->toArray();$ordered=[];foreach(self::FIELDS as $field)$ordered[$field]=$this->normalize($source[$field]);return json_encode($ordered,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRESERVE_ZERO_FRACTION|JSON_THROW_ON_ERROR); }
    private function normalize(mixed $v): mixed { if(!is_array($v))return $v;if(array_is_list($v))return array_map(fn($x)=>$this->normalize($x),$v);ksort($v,SORT_STRING);foreach($v as $k=>$x)$v[$k]=$this->normalize($x);return $v; }
}
