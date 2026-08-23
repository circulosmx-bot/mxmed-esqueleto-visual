<?php
declare(strict_types=1);
namespace Platform\Repositories;
use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;
final class PdoCanonicalAuditTransactionAdapter implements CanonicalAuditTransactionPort
{
    private const HASH_VERSION='sha256-hex-v1';
    public function __construct(private PDO $pdo) {}
    public function begin(): void { if(!$this->pdo->beginTransaction()) throw new RuntimeException('audit_begin_failed'); }
    public function ensureHead(string $streamKey,string $genesisHash,string $hashVersion): void
    {
        if($hashVersion!==self::HASH_VERSION) throw new RuntimeException('unsupported_runtime_hash_version');
        $sql='INSERT INTO platform_audit_stream_heads (stream_key,last_sequence_number,last_event_hash,hash_version,updated_at) SELECT :stream_key,0,:genesis_hash,:hash_version,NULL WHERE NOT EXISTS (SELECT 1 FROM platform_audit_stream_heads WHERE stream_key=:stream_key_probe)';
        try{$s=$this->pdo->prepare($sql);$s->execute(['stream_key'=>$streamKey,'genesis_hash'=>$genesisHash,'hash_version'=>$hashVersion,'stream_key_probe'=>$streamKey]);}
        catch(PDOException $e){if((string)$e->getCode()!=='23000') throw $e;}
    }
    public function lockHead(string $streamKey): array
    {
        $sql='CALL `mxmed`.`audit_mp01c_lock_stream_head_v1`(:stream_key)';
        $s=$this->pdo->prepare($sql);
        try{
            $s->execute(['stream_key'=>$streamKey]);
            $row=$s->fetch(PDO::FETCH_ASSOC);$second=$s->fetch(PDO::FETCH_ASSOC);
            self::drainCallResults($s);
        }catch(\Throwable $e){try{$s->closeCursor();}catch(\Throwable){}throw $e;}
        if(!is_array($row)||$second!==false) throw new RuntimeException('unexpected_audit_head_row_count');
        return ['last_sequence_number'=>(int)$row['last_sequence_number'],'last_event_hash'=>(string)$row['last_event_hash'],'hash_version'=>$row['hash_version']===null?null:(string)$row['hash_version'],'updated_at'=>self::canonicalTime($row['updated_at']??null)];
    }
    public function assertLegacyHeadMatchesLatest(string $streamKey,int $sequenceNumber,string $eventHash): void
    {
        $exact=$this->pdo->prepare('SELECT stream_key,sequence_number,event_hash FROM platform_audit_events WHERE stream_key=:stream_key AND sequence_number=:sequence_number AND event_hash=:event_hash FOR SHARE');
        $exact->execute(['stream_key'=>$streamKey,'sequence_number'=>$sequenceNumber,'event_hash'=>$eventHash]);$row=$exact->fetch(PDO::FETCH_ASSOC);
        if(!is_array($row)||$exact->fetch(PDO::FETCH_ASSOC)!==false) throw new RuntimeException('legacy_head_event_match_not_unique');
        $latest=$this->pdo->prepare('SELECT stream_key,sequence_number,event_hash FROM platform_audit_events WHERE stream_key=:stream_key ORDER BY sequence_number DESC LIMIT 1 FOR SHARE');
        $latest->execute(['stream_key'=>$streamKey]);$last=$latest->fetch(PDO::FETCH_ASSOC);
        if(!is_array($last)||$latest->fetch(PDO::FETCH_ASSOC)!==false||(int)$last['sequence_number']!==$sequenceNumber||(string)$last['event_hash']!==$eventHash) throw new RuntimeException('legacy_head_not_latest_event');
    }
    public function insertEvent(array $row): int
    {
        $columns=['stream_key','sequence_number','event_id','schema_version','occurred_at_utc','action','risk_level','outcome','reason_code','real_actor_reference','effective_actor_reference','affected_subject_reference','correlation_id','request_id','case_reference','resource_type','resource_reference','metadata_json','previous_hash','event_hash','created_at_utc'];
        $sql='INSERT INTO platform_audit_events (`'.implode('`,`',$columns).'`) VALUES (:'.implode(',:',$columns).')';$s=$this->pdo->prepare($sql);$s->execute(array_intersect_key($row,array_flip($columns)));return $s->rowCount();
    }
    public function updateHead(string $streamKey,int $expectedSequence,string $expectedHash,?string $expectedHashVersion,?string $expectedUpdatedAt,int $newSequence,string $newHash,string $newHashVersion,string $newUpdatedAt): int
    {
        if($newHashVersion!==self::HASH_VERSION||!self::isCanonicalTime($newUpdatedAt)) throw new RuntimeException('invalid_head_version_or_time');
        if($expectedSequence===0&&$expectedHashVersion===null) throw new RuntimeException('invalid_unversioned_empty_head');
        if($expectedHashVersion===null&&$expectedSequence<=0) throw new RuntimeException('invalid_legacy_bridge_transition');
        if($expectedHashVersion!==null&&$expectedHashVersion!==$newHashVersion) throw new RuntimeException('forbidden_head_version_change');
        if($expectedUpdatedAt!==null&&!self::isCanonicalTime($expectedUpdatedAt)) throw new RuntimeException('invalid_expected_head_time');
        $sql='CALL `mxmed`.`audit_mp01c_advance_stream_head_cas_v1`(:stream_key,:expected_sequence,:expected_hash,:expected_hash_version,:expected_updated_at,:new_sequence,:new_hash,:new_hash_version,:new_updated_at)';
        $s=$this->pdo->prepare($sql);
        try{
            $s->execute(['stream_key'=>$streamKey,'expected_sequence'=>$expectedSequence,'expected_hash'=>$expectedHash,'expected_hash_version'=>$expectedHashVersion,'expected_updated_at'=>$expectedUpdatedAt,'new_sequence'=>$newSequence,'new_hash'=>$newHash,'new_hash_version'=>$newHashVersion,'new_updated_at'=>$newUpdatedAt]);
            self::drainCallResults($s);
        }catch(PDOException $e){
            try{$s->closeCursor();}catch(\Throwable){}
            if((string)$e->getCode()==='45000') throw new RuntimeException('head_update_failed',0,$e);
            throw $e;
        }
        return 1;
    }
    public function commit(): void { if(!$this->pdo->commit()) throw new RuntimeException('audit_commit_failed'); }
    public function rollBack(): void { if($this->pdo->inTransaction()) $this->pdo->rollBack(); }
    private static function drainCallResults(PDOStatement $statement): void { do{while($statement->fetch(PDO::FETCH_ASSOC)!==false){}}while($statement->nextRowset());$statement->closeCursor(); }
    private static function isCanonicalTime(string $value): bool { return preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z$/',$value)===1; }
    private static function canonicalTime(mixed $value): ?string { if($value===null) return null;$text=(string)$value;if(preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{6}$/',$text)!==1) throw new RuntimeException('invalid_stored_head_time');return str_replace(' ','T',$text).'Z'; }
}
