<?php
declare(strict_types=1);
namespace Identity\Operators;
use PDO;
use RuntimeException;
use Platform\Repositories\CanonicalAuditTransactionPort;
/** Isolated joined SQL port; no calls to mxmed-qualified production routines. */
final class IsolatedOperatorAuditTransaction implements CanonicalAuditTransactionPort
{

    public function __construct(private PDO $pdo){OperatorQaConfiguration::assertEnabled($pdo);}
    public function begin():void{if(!$this->pdo->inTransaction())throw new RuntimeException('outer_transaction_required');}
    public function commit():void{if(!$this->pdo->inTransaction())throw new RuntimeException('outer_transaction_required');}
    public function rollBack():void{if($this->pdo->inTransaction())$this->pdo->rollBack();}
    public function ensureHead(string $streamKey,string $genesisHash,string $hashVersion):void
    {$s=$this->pdo->prepare('INSERT IGNORE INTO platform_audit_stream_heads VALUES(?,0,?,?,NULL)');$s->execute([$streamKey,$genesisHash,$hashVersion]);}
    public function lockHead(string $streamKey):array
    {$s=$this->pdo->prepare('SELECT last_sequence_number,last_event_hash,hash_version,updated_at FROM platform_audit_stream_heads WHERE stream_key=? FOR UPDATE');$s->execute([$streamKey]);$r=$s->fetch(PDO::FETCH_ASSOC);return ['last_sequence_number'=>(int)$r['last_sequence_number'],'last_event_hash'=>$r['last_event_hash'],'hash_version'=>$r['hash_version'],'updated_at'=>$r['updated_at']===null?null:str_replace(' ','T',$r['updated_at']).'Z'];}
    public function assertLegacyHeadMatchesLatest(string $streamKey,int $sequenceNumber,string $eventHash):void{throw new RuntimeException('legacy_not_used');}
    public function insertEvent(array $row):int
    {$c=array_keys($row);$s=$this->pdo->prepare('INSERT INTO platform_audit_events(`'.implode('`,`',$c).'`) VALUES(:'.implode(',:',$c).')');$s->execute($row);return $s->rowCount();}
    public function updateHead(string $streamKey,int $expectedSequence,string $expectedHash,?string $expectedHashVersion,?string $expectedUpdatedAt,int $newSequence,string $newHash,string $newHashVersion,string $newUpdatedAt):int
    {$s=$this->pdo->prepare('UPDATE platform_audit_stream_heads SET last_sequence_number=?,last_event_hash=?,hash_version=?,updated_at=? WHERE stream_key=? AND last_sequence_number=? AND last_event_hash=? AND hash_version <=> ? AND updated_at <=> ?');$s->execute([$newSequence,$newHash,$newHashVersion,str_replace(['T','Z'],[' ', ''],$newUpdatedAt),$streamKey,$expectedSequence,$expectedHash,$expectedHashVersion,$expectedUpdatedAt===null?null:str_replace(['T','Z'],[' ', ''],$expectedUpdatedAt)]);return $s->rowCount();}
}
