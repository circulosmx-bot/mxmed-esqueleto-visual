<?php
declare(strict_types=1);
namespace Platform\Contracts;
final readonly class CanonicalAuditEnvelope
{
    private const FIELDS=['event_id','occurred_at','event_type','event_version','severity','result','actor_identity_id','actor_type','actor_role','actor_scope','effective_entity_type','effective_entity_id','target_type','target_id','session_id','request_id','correlation_id','source_module','source_route','reason_code','retention_class','metadata_json','ip_hmac','user_agent_summary','sequence_number','previous_hash','event_hash','created_at'];
    private function __construct(private array $fields) { if(array_keys($fields)!==self::FIELDS) throw new \InvalidArgumentException('invalid_audit_v1_field_order'); }
    public static function assembleByWriter(array $orderedFields): self { return new self($orderedFields); }
    public function withChain(int $sequence,string $previousHash): self { $v=$this->fields;$v['sequence_number']=$sequence;$v['previous_hash']=$previousHash;return new self($v); }
    public function withEventHash(string $eventHash): self { $v=$this->fields;$v['event_hash']=$eventHash;return new self($v); }
    public function value(string $field): mixed { if(!in_array($field,self::FIELDS,true)) throw new \InvalidArgumentException('unknown_audit_v1_field'); return $this->fields[$field]; }
    public function toArray(): array { return $this->fields; }
}
