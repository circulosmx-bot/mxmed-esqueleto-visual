<?php
declare(strict_types=1);
namespace Media\Services;
require_once __DIR__.'/MediaReviewAudit.php';
require_once __DIR__.'/BatchOriginals.php';
require_once __DIR__.'/TemporaryOriginalsZip.php';
require_once __DIR__.'/GdPublicLogoProcessor.php';
require_once __DIR__.'/LosslessLogoInput.php';
require_once __DIR__.'/../contracts/PrivateMediaStoragePort.php';
use Media\Contracts\PrivateMediaStoragePort;
use Platform\Contracts\{TrustedAuthorizationContext,AuthorizationRequirement,AuthorizationPlane,RiskLevel,CapabilitySet};
use Platform\Services\{AuthorizationBoundary,RandomAuditUuidProvider};
use PDO;
use RuntimeException;

final class MediaReviewInterventionService
{
    public const DOWNLOAD='media_review_source_download';
    public const CORRECT='media_review_corrected_upload';
    public function __construct(private PDO $pdo,private PrivateMediaStoragePort $storage) {}
    public static function requirement(string $capability): AuthorizationRequirement
    {
        if (!in_array($capability,[self::DOWNLOAD,self::CORRECT],true)) throw new RuntimeException('intervention_denied');
        return new AuthorizationRequirement(authorizationPlane:AuthorizationPlane::INTERNAL_OPERATOR,riskLevel:RiskLevel::R1,
            action:$capability===self::DOWNLOAD?'download_source':'upload_corrected',resourceType:'media_review_submission',
            actorAuthenticatedRequired:true,capabilitiesRequired:new CapabilitySet([$capability]),auditTrailRequired:true);
    }
    /** Full bounded verification and committed audit precede all HTTP attachment bytes. No reservation. */
    public function download(?TrustedAuthorizationContext $context,string $id): array
    {
        $result=null;
        $this->transaction($context,self::DOWNLOAD,'MEDIA_REVIEW_SOURCE_DOWNLOADED','GET /api/internal/media-review/source-download.php',
            function() use($id,&$result):array {
                $candidate=$this->candidate($id,false);
                $s=$this->pdo->prepare("SELECT * FROM media_review_files WHERE submission_id=? AND role='SOURCE'");$s->execute([$id]);$rows=$s->fetchAll(PDO::FETCH_ASSOC);
                if(count($rows)!==1)throw new RuntimeException('intervention_integrity_failed');
                $source=$rows[0];$this->validateFile($candidate,$source);
                $bytes=$this->verified($source);
                $extension=$source['format']==='jpeg'?'jpg':$source['format'];
                $result=['bytes'=>$bytes,'mime'=>$source['mime_type'],'filename'=>($candidate['purpose']==='PHYSICIAN_PERSONAL_LOGO'?'logo-original-':'foto-original-').substr($id,0,8).'.'.$extension];
                return ['submission_id'=>$id,'physician_id'=>(string)$candidate['owner_id']];
            });
        return $result;
    }
    /** Every SOURCE is audited using the existing authority before any ZIP bytes are sent. */
    public function downloadBatch(?TrustedAuthorizationContext $context,string $id):array
    {
        if($context===null||$context->trustSource()!=='canonical_internal_operator'||!$context->context()->capabilities()->contains(self::DOWNLOAD)||!$context->context()->capabilities()->contains('media_review_read'))throw new RuntimeException('intervention_denied');
        $data=BatchOriginals::load($this->pdo,$id);$zip=new TemporaryOriginalsZip();$manifest=[];$total=0;
        try {
            foreach($data['items'] as $i=>$item){
                $this->transaction($context,self::DOWNLOAD,'MEDIA_REVIEW_SOURCE_DOWNLOADED','GET /api/internal/media-review/batch-source-download.php',function()use($item,$data,$i,$zip,&$manifest,&$total):array{
                    $bytes=$this->sourceBytes($item,$item['source']);$total+=strlen($bytes);
                    if($total>209715200)throw new RuntimeException('intervention_conflict');
                    $entry=BatchOriginals::entry($data['batch'],$item,$i+1);$zip->add($entry['archive_filename'],$bytes);$manifest[]=$entry;
                    return ['submission_id'=>$item['submission_id'],'physician_id'=>$item['owner_id']];
                });
            }
            $zip->add('manifest.json',json_encode(['archive_schema_version'=>1,'sources'=>$manifest],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES));$zip->finish();
            return ['zip'=>$zip,'filename'=>'MXMED_Originales_'.substr(hash('sha256',$data['batch']['owner_id']),0,12).'_'.substr($data['batch']['submitted_at'],0,10).'_'.substr($id,0,8).'.zip'];
        }catch(\Throwable $e){$zip->remove();throw $e;}
    }
    /** Internal verified read shared by download and archive; no public route or authority bypass. */
    public function sourceBytes(array $candidate,array $source):string
    {
        if($source['role']!=='SOURCE'||$source['submission_id']!==$candidate['submission_id'])throw new RuntimeException('intervention_integrity_failed');
        $this->validateFile($candidate,$source);return $this->verified($source);
    }
    public function corrected(?TrustedAuthorizationContext $context,string $id,array $upload): array
    {
        $newKeys=[];$oldKeys=[];$review=null;$safeCleanup=true;
        try {
            $this->transaction($context,self::CORRECT,'MEDIA_REVIEW_CORRECTED_UPLOADED','POST /api/internal/media-review/corrected.php',
                function() use($id,$upload,&$newKeys,&$oldKeys,&$review):array {
                    // Shared MR1/MR5 order: physician -> submission -> file relationships.
                    $candidate=$this->candidate($id,true);
                    $s=$this->pdo->prepare('SELECT * FROM media_review_files WHERE submission_id=? ORDER BY role FOR UPDATE');$s->execute([$id]);$files=$s->fetchAll(PDO::FETCH_ASSOC);$roles=[];
                    foreach($files as $file){$this->validateFile($candidate,$file);$roles[$file['role']]=$file;if($file['role']!=='SOURCE')$oldKeys[]=$file['storage_key'];}
                    if(!isset($roles['SOURCE'],$roles['REVIEW']) || count($roles)!==count($files) || !in_array(count($files),[2,3,4,5],true))throw new RuntimeException('intervention_integrity_failed');
                    $path=$upload['tmp_name']??null;
                    if(($upload['error']??-1)!==UPLOAD_ERR_OK || !is_string($path) || !is_file($path))throw new RuntimeException('intervention_invalid_upload');
                    $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($path);$ext=strtolower(pathinfo((string)($upload['name']??''),PATHINFO_EXTENSION));
                    if(!in_array($ext,['image/jpeg'=>['jpg','jpeg'],'image/png'=>['png'],'image/webp'=>['webp']][$mime]??[],true))throw new RuntimeException('intervention_invalid_upload');
                    if($mime==='image/jpeg' && !function_exists('exif_read_data'))throw new RuntimeException('intervention_processor_unavailable');
                    $prefix='private/media-review/'.hash('sha256','PHYSICIAN:'.$candidate['owner_id']).'/'.$id.'/';$corrected=null;$input=null;
                    $review=(new GdPublicLogoProcessor(10485760,8192,25000000))->process($upload,false,
                        function(string $source,string $mime,int $width,int $height,int $bytes) use($prefix,&$corrected,&$newKeys):void {
                            $fileId=(new RandomAuditUuidProvider())->generateCanonicalUuid();$format=substr($mime,6);$key=$prefix.'corrected/'.$fileId.'.'.$format;
                            $corrected=['file_id'=>$fileId,'role'=>'CORRECTED','storage_key'=>$key,'mime_type'=>$mime,'format'=>$format,'width'=>$width,'height'=>$height,'byte_size'=>$bytes,'checksum_sha256'=>hash_file('sha256',$source)];
                            $this->store($corrected,$source,$newKeys);
                        },null,$candidate['purpose']==='PHYSICIAN_PERSONAL_LOGO' ? function(\GdImage $working) use($prefix,&$input,&$newKeys):void {
                            $derivative=LosslessLogoInput::export($working);
                            try {
                                $fileId=(new RandomAuditUuidProvider())->generateCanonicalUuid();
                                $input=array_merge($derivative,['file_id'=>$fileId,'role'=>'IMPROVEMENT_INPUT','storage_key'=>$prefix.'improvement_input/'.$fileId.'.png']);
                                $this->store($input,$derivative['path'],$newKeys);
                            }finally{unlink($derivative['path']);}
                        }:null);
                    $fileId=(new RandomAuditUuidProvider())->generateCanonicalUuid();$review=array_merge($review,['file_id'=>$fileId,'role'=>'REVIEW','storage_key'=>$prefix.'review/'.$fileId.'.webp']);
                    $this->store($review,$review['path'],$newKeys);
                    $s=$this->pdo->prepare("DELETE FROM media_review_files WHERE submission_id=? AND role IN ('CORRECTED','REVIEW','AUTO_PROPOSAL','IMPROVEMENT_INPUT')");$s->execute([$id]);
                    $s=$this->pdo->prepare('INSERT INTO media_review_files(file_id,submission_id,role,storage_key,mime_type,format,width,height,byte_size,checksum_sha256) VALUES(?,?,?,?,?,?,?,?,?,?)');
                    foreach(array_filter([$corrected,$input,$review]) as $f)$s->execute([$f['file_id'],$id,$f['role'],$f['storage_key'],$f['mime_type'],$f['format'],$f['width'],$f['height'],$f['byte_size'],$f['checksum_sha256']]);
                    $this->pdo->prepare('UPDATE media_review_submissions SET updated_at=CURRENT_TIMESTAMP WHERE submission_id=?')->execute([$id]);
                    return ['submission_id'=>$id,'physician_id'=>(string)$candidate['owner_id'],'corrected_file_id'=>$corrected['file_id'],'review_file_id'=>$review['file_id']];
                },$safeCleanup);
        } catch(\Throwable $e) {
            if($safeCleanup)$this->cleanup($newKeys);else error_log('media_corrected_commit_outcome_requires_reconciliation');
            throw $e;
        } finally {if(is_array($review) && is_file($review['path']))unlink($review['path']);}
        $this->cleanup($oldKeys);
        return ['ok'=>true,'submission_id'=>$id,'technical_status'=>'READY','review_status'=>'PENDING_REVIEW',
            'review'=>['role'=>'REVIEW','mime_type'=>'image/webp','format'=>'webp','width'=>$review['width'],'height'=>$review['height'],'byte_size'=>$review['byte_size']]];
    }
    private function transaction(?TrustedAuthorizationContext $context,string $cap,string $event,string $route,\Closure $prepare,?bool &$safeCleanup=null):void
    {
        if($context===null || $context->trustSource()!=='canonical_internal_operator')throw new RuntimeException('intervention_denied');
        if($this->pdo->inTransaction())throw new RuntimeException('intervention_outer_transaction_not_allowed');
        $commitAttempt=false;
        try {
            if(!$this->pdo->beginTransaction())throw new RuntimeException('intervention_begin_failed');
            $audit=new MediaReviewAudit($this->pdo,$context,$prepare,$event,$cap,$route);
            if(!(new AuthorizationBoundary())->authorize($context,self::requirement($cap),$audit)->allowed())throw new RuntimeException('intervention_denied');
            $commitAttempt=true;
            if(!$this->pdo->commit())throw new RuntimeException('intervention_commit_failed');
        } catch(\Throwable $e) {
            $safeCleanup=!$commitAttempt;
            if($this->pdo->inTransaction())try{$safeCleanup=$this->pdo->rollBack();}catch(\Throwable){$safeCleanup=false;error_log('media_intervention_rollback_unconfirmed');}
            throw $e;
        }
    }
    private function candidate(string $id,bool $lock):array
    {
        if(!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D',$id))throw new RuntimeException('intervention_invalid_request');
        if($lock){
            $s=$this->pdo->prepare('SELECT owner_id FROM media_review_submissions WHERE submission_id=?');$s->execute([$id]);$owner=$s->fetchColumn();if($owner===false)throw new RuntimeException('intervention_not_found');
            $s=$this->pdo->prepare('SELECT doctor_id FROM profiles_doctors WHERE doctor_id=? FOR UPDATE');$s->execute([$owner]);if($s->fetchColumn()===false)throw new RuntimeException('intervention_conflict');
        }
        $s=$this->pdo->prepare('SELECT * FROM media_review_submissions WHERE submission_id=?'.($lock?' FOR UPDATE':''));$s->execute([$id]);$row=$s->fetch(PDO::FETCH_ASSOC);
        if(!$row)throw new RuntimeException('intervention_not_found');
        if(($lock && (string)$row['owner_id']!==(string)$owner) || $row['owner_type']!=='PHYSICIAN' || !in_array($row['purpose'],['DOCTOR_PROFILE_PHOTO','PHYSICIAN_PERSONAL_LOGO','DOCTOR_GALLERY'],true) || $row['technical_status']!=='READY' || $row['review_status']!=='PENDING_REVIEW')throw new RuntimeException('intervention_conflict');
        return $row;
    }
    private function validateFile(array $candidate,array $file):void
    {
        $role=$file['role'];$format=$file['format'];$prefix='private/media-review/'.hash('sha256','PHYSICIAN:'.$candidate['owner_id']).'/'.$candidate['submission_id'].'/';
        if(!in_array($role,['SOURCE','REVIEW','CORRECTED','AUTO_PROPOSAL','IMPROVEMENT_INPUT'],true) || !in_array($format,['jpeg','png','webp'],true) || $file['mime_type']!=='image/'.$format
            || !preg_match('/^[0-9a-f-]{36}$/D',$file['file_id']) || $file['storage_key']!==$prefix.strtolower($role).'/'.$file['file_id'].'.'.$format
            || (int)$file['byte_size']<1 || (int)$file['byte_size']>10485760 || min((int)$file['width'],(int)$file['height'])<1
            || max((int)$file['width'],(int)$file['height'])>8192 || (int)$file['width']*(int)$file['height']>25000000
            || !preg_match('/^[0-9a-f]{64}$/D',$file['checksum_sha256']))throw new RuntimeException('intervention_integrity_failed');
        if($role==='IMPROVEMENT_INPUT' && ($candidate['purpose']!=='PHYSICIAN_PERSONAL_LOGO'||$format!=='png'||(int)$file['byte_size']>4194304||max((int)$file['width'],(int)$file['height'])>800))throw new RuntimeException('intervention_integrity_failed');
        if(in_array($role,['REVIEW','AUTO_PROPOSAL'],true) && ($format!=='webp' || (int)$file['byte_size']>153600 || max((int)$file['width'],(int)$file['height'])>800))throw new RuntimeException('intervention_integrity_failed');
    }
    private function verified(array $file):string
    {
        $object=$this->storage->openReadStream($file['storage_key']);
        try{$bytes=stream_get_contents($object['stream'],10485761);
            if($bytes===false || strlen($bytes)!==(int)$file['byte_size'] || $object['bytes']!==(int)$file['byte_size'] || !hash_equals($file['checksum_sha256'],hash('sha256',$bytes)))throw new RuntimeException('intervention_integrity_failed');
            return $bytes;
        } finally {fclose($object['stream']);}
    }
    private function store(array $file,string $path,array &$keys):void
    {
        if($this->storage->exists($file['storage_key']))throw new RuntimeException('intervention_immutable_key_conflict');
        $keys[]=$file['storage_key'];$this->storage->storeImmutable($file['storage_key'],$path);$this->verified($file);
    }
    private function cleanup(array $keys):void
    {
        foreach($keys as $key)try{$this->storage->delete($key);}catch(\Throwable){error_log('media_intervention_private_cleanup_failed');}
    }
}
