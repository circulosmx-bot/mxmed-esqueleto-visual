<?php
declare(strict_types=1);
namespace Media\Services;
require_once __DIR__.'/BatchOriginals.php';
require_once __DIR__.'/MediaReviewInterventionService.php';
require_once __DIR__.'/../contracts/HistoricalArchivePort.php';
use Media\Contracts\HistoricalArchivePort;
final class HistoricalOriginalArchive
{
    public const SOURCE_HOT_RETENTION_DAYS=30;
    // Separate receipt states, never media_review_batches.status values.
    public const STATES=['NOT_ARCHIVED','ARCHIVED_UNVERIFIED','ARCHIVED_VERIFIED','FAILED','OPERATIONAL_SOURCE_PURGED'];
    public function __construct(private \PDO $pdo,private MediaReviewInterventionService $sources,private HistoricalArchivePort $archive){}
    public static function prefix(array $batch):string
    {
        foreach(['owner_id','batch_id'] as $key)if(!preg_match('/^[A-Za-z0-9_-]{1,100}$/D',(string)$batch[$key]))throw new \RuntimeException('archive_invalid_identity');
        $date=\DateTimeImmutable::createFromFormat('!Y-m-d',substr((string)$batch['submitted_at'],0,10),new \DateTimeZone('UTC'));
        if(!$date||$date->format('Y-m-d')!==substr($batch['submitted_at'],0,10))throw new \RuntimeException('archive_invalid_date');
        return 'media-archive/v1/physicians/'.$date->format('Y/m').'/'.$batch['owner_id'].'/'.$batch['batch_id'].'/';
    }
    public static function resolved(array $data):?\DateTimeImmutable
    {
        $latest=null;
        foreach($data['items'] as $item){
            // WITHDRAWN is not a review decision; fail closed for it and unknown states.
            if(!in_array($item['review_status'],['APPROVED','NEEDS_WORK'],true))return null;
            $value=$item['review_status']==='NEEDS_WORK'?($item['review_decided_at']??null):$item['updated_at'];
            if(!$value)return null;
            try{$time=new \DateTimeImmutable($value,new \DateTimeZone('UTC'));}catch(\Throwable){return null;}
            if($latest===null||$time>$latest)$latest=$time;
        }
        return $latest;
    }
    public function archive(string $batchId):array
    {
        $data=BatchOriginals::load($this->pdo,$batchId);$resolved=self::resolved($data);
        if(!$resolved)throw new \RuntimeException('archive_batch_unresolved');
        $prefix=self::prefix($data['batch']);$receipt=['schema_version'=>1,'batch_id'=>$batchId,'state'=>'NOT_ARCHIVED','resolved_at'=>$resolved->format(DATE_ATOM),'verified_at'=>null,'objects'=>[],'manifest_key'=>$prefix.'manifest.json'];
        try{
            $entries=[];
            foreach($data['items'] as $i=>$item){
                $entry=BatchOriginals::entry($data['batch'],$item,$i+1);$bytes=$this->sources->sourceBytes($item,$item['source']);
                $key=$prefix.'sources/'.$item['submission_id'].'/original.'.pathinfo($entry['archive_filename'],PATHINFO_EXTENSION);
                $identity=['owner_type'=>'PHYSICIAN','owner_id'=>$entry['owner_id'],'batch_id'=>$batchId,'submission_id'=>$entry['submission_id'],'sha256'=>$entry['sha256'],'byte_size'=>$entry['byte_size'],'mime_type'=>$entry['mime_type']];
                $this->archive->put($key,$bytes,$identity);$receipt['objects'][]=['key'=>$key,'identity'=>$identity];$entries[]=$entry;
            }
            $manifest=json_encode(['archive_schema_version'=>1,'sources'=>$entries],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
            $identity=['owner_type'=>'PHYSICIAN','owner_id'=>$data['batch']['owner_id'],'batch_id'=>$batchId,'sha256'=>hash('sha256',$manifest),'byte_size'=>strlen($manifest),'mime_type'=>'application/json'];
            $this->archive->put($receipt['manifest_key'],$manifest,$identity);$receipt['objects'][]=['key'=>$receipt['manifest_key'],'identity'=>$identity];$receipt['state']='ARCHIVED_UNVERIFIED';
            foreach($receipt['objects'] as $object)if(!$this->archive->verify($object['key'],$object['identity']))return $receipt;
            $receipt['state']='ARCHIVED_VERIFIED';$receipt['verified_at']=gmdate(DATE_ATOM);return $receipt;
        }catch(\Throwable){$receipt['state']='FAILED';return $receipt;}
    }
    /** Policy seam only: no deletion implementation or operational caller in MR12A.2. */
    public function purgeEligible(array $receipt,\DateTimeImmutable $now,int $retentionDays=self::SOURCE_HOT_RETENTION_DAYS):bool
    {
        if($retentionDays<1||($receipt['state']??'')!=='ARCHIVED_VERIFIED'||empty($receipt['verified_at'])||empty($receipt['batch_id']))return false;
        try{
            $data=BatchOriginals::load($this->pdo,$receipt['batch_id']);$resolved=self::resolved($data);if(!$resolved)return false;
            // Re-derive all expected records from current canonical membership, not caller receipts.
            $prefix=self::prefix($data['batch']);$entries=[];$objects=[];
            foreach($data['items'] as $i=>$item){$e=BatchOriginals::entry($data['batch'],$item,$i+1);$entries[]=$e;$objects[]=['key'=>$prefix.'sources/'.$item['submission_id'].'/original.'.pathinfo($e['archive_filename'],PATHINFO_EXTENSION),'identity'=>['owner_type'=>'PHYSICIAN','owner_id'=>$e['owner_id'],'batch_id'=>$e['batch_id'],'submission_id'=>$e['submission_id'],'sha256'=>$e['sha256'],'byte_size'=>$e['byte_size'],'mime_type'=>$e['mime_type']]];}
            $manifest=json_encode(['archive_schema_version'=>1,'sources'=>$entries],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);$objects[]=['key'=>$prefix.'manifest.json','identity'=>['owner_type'=>'PHYSICIAN','owner_id'=>$data['batch']['owner_id'],'batch_id'=>$data['batch']['batch_id'],'sha256'=>hash('sha256',$manifest),'byte_size'=>strlen($manifest),'mime_type'=>'application/json']];
            $verified=new \DateTimeImmutable($receipt['verified_at']);$start=$verified>$resolved?$verified:$resolved;
            if($now<$start->modify('+'.$retentionDays.' days'))return false;
            foreach($objects as $o)if(!$this->archive->verify($o['key'],$o['identity']))return false;
            return true;
        }catch(\Throwable){return false;}
    }
}
