<?php
declare(strict_types=1);
namespace Platform\Services;
use Platform\Contracts\{AuditIpHasher,AuditUserAgentSummarizer,AuditUtcClock,AuditUuidProvider,CanonicalAuditEnvelope,CanonicalAuditEventInput,TrustedAuditContext};
use Platform\Repositories\CanonicalAuditTransactionPort;
final class CanonicalAuditWriter
{
    public const GENESIS_HASH='0000000000000000000000000000000000000000000000000000000000000000';
    public function __construct(private CanonicalAuditPolicyRegistry $policy,private CanonicalAuditMetadataSanitizer $sanitizer,private TrustedAuditContextValidator $contextValidator,private AuditUuidProvider $uuidProvider,private AuditUtcClock $clock,private AuditIpHasher $ipHasher,private AuditUserAgentSummarizer $uaSummarizer,private CanonicalAuditTransactionPort $tx,private CanonicalAuditSealer $sealer,private AuditV1PhysicalMapper $mapper) {}
    public function append(CanonicalAuditEventInput $input,TrustedAuditContext $context): CanonicalAuditEnvelope
    {
        $policy=$this->policy->assertAllowed($input->eventType,$input->result,$input->reasonCode);$metadata=$this->sanitizer->sanitize($input->metadata,$policy['allowed_producer_metadata']);$this->contextValidator->assertTrusted($context);
        $eventId=$this->uuidProvider->generateCanonicalUuid();$occurredAt=$this->clock->nowUtc();$createdAt=$this->clock->nowUtc();$ipHmac=null;$ipHmacKeyVersion=null;
        if($context->trustedClientIp!==null){$ip=$this->ipHasher->hashTrustedNetworkAddress($context->trustedClientIp);$ipHmac=$ip['ip_hmac']??null;$ipHmacKeyVersion=$ip['ip_hmac_key_version']??null;if(!is_string($ipHmac)||$ipHmac===''||!is_string($ipHmacKeyVersion)||$ipHmacKeyVersion==='') throw new \RuntimeException('ip_hmac_key_version_required');}
        $ua=$context->trustedRawUserAgent===null?null:$this->uaSummarizer->summarizeTrustedUserAgent($context->trustedRawUserAgent);
        $event=CanonicalAuditEnvelope::assembleByWriter(['event_id'=>$eventId,'occurred_at'=>$occurredAt,'event_type'=>$input->eventType,'event_version'=>'audit.v1','severity'=>$policy['severity'],'result'=>$input->result,'actor_identity_id'=>$context->actorIdentityId,'actor_type'=>$context->actorType,'actor_role'=>$context->actorRole,'actor_scope'=>$context->actorScope,'effective_entity_type'=>$input->effectiveEntityType,'effective_entity_id'=>$input->effectiveEntityId,'target_type'=>$input->targetType,'target_id'=>$input->targetId,'session_id'=>$context->sessionId,'request_id'=>$context->requestId,'correlation_id'=>$context->correlationId,'source_module'=>$context->sourceModule,'source_route'=>$context->sourceRoute,'reason_code'=>$input->reasonCode,'retention_class'=>$policy['retention_class'],'metadata_json'=>$metadata,'ip_hmac'=>$ipHmac,'user_agent_summary'=>$ua,'sequence_number'=>0,'previous_hash'=>self::GENESIS_HASH,'event_hash'=>null,'created_at'=>$createdAt]);
        $streamKey=strtolower($input->targetType).':'.$input->targetId;$runtimeVersion=CanonicalAuditSerializer::HASH_VERSION;$this->tx->begin();
        try{
            $this->tx->ensureHead($streamKey,self::GENESIS_HASH,$runtimeVersion);$head=$this->tx->lockHead($streamKey);
            if($head['last_sequence_number']<0||preg_match('/^[a-f0-9]{64}$/',$head['last_event_hash'])!==1) throw new \RuntimeException('invalid_audit_head');
            if($head['last_sequence_number']===0){if($head['hash_version']===null) throw new \RuntimeException('invalid_unversioned_empty_head');if($head['hash_version']!==$runtimeVersion) throw new \RuntimeException('hash_version_mismatch');if($head['last_event_hash']!==self::GENESIS_HASH||$head['updated_at']!==null) throw new \RuntimeException('invalid_empty_head');}
            elseif($head['hash_version']===null){if($head['last_sequence_number']<=0) throw new \RuntimeException('invalid_legacy_head');$this->tx->assertLegacyHeadMatchesLatest($streamKey,$head['last_sequence_number'],$head['last_event_hash']);if($head['updated_at']===null) throw new \RuntimeException('legacy_head_missing_updated_at');}
            elseif($head['hash_version']!==$runtimeVersion){throw new \RuntimeException('hash_version_mismatch');}
            elseif($head['updated_at']===null){throw new \RuntimeException('versioned_head_missing_updated_at');}
            $event=$event->withChain($head['last_sequence_number']+1,$head['last_event_hash']);$sealed=$this->sealer->seal($event,$streamKey);$row=$this->mapper->map($sealed,$streamKey,$ipHmacKeyVersion);
            if($this->tx->insertEvent($row)!==1) throw new \RuntimeException('event_insert_failed');
            if($this->tx->updateHead($streamKey,$head['last_sequence_number'],$head['last_event_hash'],$head['hash_version'],$head['updated_at'],$sealed->value('sequence_number'),$sealed->value('event_hash'),$runtimeVersion,$createdAt)!==1) throw new \RuntimeException('head_update_failed');
            $this->tx->commit();return $sealed;
        }catch(\Throwable $e){$this->tx->rollBack();throw $e;}
    }
}
