<?php
declare(strict_types=1);
namespace Media\Services;
require_once __DIR__.'/MediaReviewAudit.php';
require_once __DIR__.'/ConservativeLogoBackground.php';
require_once __DIR__.'/GdPublicLogoProcessor.php';
require_once __DIR__.'/../contracts/PrivateMediaStoragePort.php';
use Media\Contracts\PrivateMediaStoragePort;
use Platform\Contracts\{TrustedAuthorizationContext,AuthorizationRequirement,AuthorizationPlane,RiskLevel,CapabilitySet};
use Platform\Services\{AuthorizationBoundary,RandomAuditUuidProvider};
use PDO;
use RuntimeException;

final class LogoImprovementService
{
    public const CAPABILITY='media_review_improve';
    private const EVENTS=['generate'=>'MEDIA_LOGO_IMPROVEMENT_PROPOSED','accept'=>'MEDIA_LOGO_IMPROVEMENT_ACCEPTED','discard'=>'MEDIA_LOGO_IMPROVEMENT_DISCARDED'];
    private const ROUTES=['generate'=>'improve-logo.php','accept'=>'accept-improvement.php','discard'=>'discard-improvement.php'];
    public function __construct(private PDO $pdo,private PrivateMediaStoragePort $storage){}
    public static function requirement(string $action):AuthorizationRequirement
    {
        if(!isset(self::EVENTS[$action]))throw new RuntimeException('improvement_invalid_request');
        return new AuthorizationRequirement(authorizationPlane:AuthorizationPlane::INTERNAL_OPERATOR,riskLevel:RiskLevel::R1,
            action:'improvement_'.$action,resourceType:'media_review_submission',actorAuthenticatedRequired:true,
            capabilitiesRequired:new CapabilitySet([self::CAPABILITY]),auditTrailRequired:true);
    }
    /** All mutations share physician -> submission -> files locks with correction and approval. */
    public function mutate(?TrustedAuthorizationContext $context,string $action,string $id):array
    {
        $requirement=self::requirement($action);
        if($context===null||$context->trustSource()!=='canonical_internal_operator')throw new RuntimeException('improvement_denied');
        if($this->pdo->inTransaction())throw new RuntimeException('improvement_outer_transaction');
        $new=[];$old=[];$temps=[];$attempt=false;$safe=true;$result=null;
        try {
            if(!$this->pdo->beginTransaction())throw new RuntimeException('improvement_begin_failed');
            $prepare=function()use($action,$id,&$new,&$old,&$temps,&$result):array{
                [$candidate,$files]=$this->resolve($id,true);$input=$files['CORRECTED']??$files['SOURCE'];$proposal=$files['AUTO_PROPOSAL']??null;
                if($action==='generate'){
                    // Deterministic replacement, at most one proposal; REVIEW is never touched here.
                    $original=$this->verified($input);$path=$this->temporary($original);$temps[]=$path;
                    if($input['mime_type']==='image/jpeg'&&!function_exists('exif_read_data'))throw new RuntimeException('improvement_processor_unavailable');
                    $output=(new GdPublicLogoProcessor(10485760,8192,25000000))->process(['tmp_name'=>$path,'type'=>$input['mime_type'],'error'=>0],false,null,
                        static function(\GdImage $image):void{$status=(new ConservativeLogoBackground())->apply($image);if($status!=='PROPOSAL_CREATED')throw new RuntimeException($status);});
                    $temps[]=$output['path'];$file=$this->newFile($candidate,'AUTO_PROPOSAL',$output);
                    $file['input_file_id']=$input['file_id'];$file['input_checksum_sha256']=$input['checksum_sha256'];
                    $this->store($file,$output['path'],$new);
                    if($proposal){$this->remove($proposal);$old[]=$proposal['storage_key'];}
                    $this->insert($file,$id);$result=['status'=>'PROPOSAL_CREATED','proposal'=>$this->metadata($file)];
                }else{
                    if(!$proposal)throw new RuntimeException('improvement_conflict');
                    if($action==='accept'){
                        if($proposal['input_file_id']!==$input['file_id']||!hash_equals($proposal['input_checksum_sha256'],$input['checksum_sha256']))throw new RuntimeException('improvement_conflict');
                        $this->verified($input);$bytes=$this->verified($proposal);$path=$this->temporary($bytes);$temps[]=$path;
                        $file=$this->newFile($candidate,'REVIEW',$proposal);$this->store($file,$path,$new);
                        $this->remove($files['REVIEW']);$old[]=$files['REVIEW']['storage_key'];$this->insert($file,$id);
                        $result=['status'=>'PROPOSAL_ACCEPTED','review'=>$this->metadata($file)];
                    }else{$result=['status'=>'PROPOSAL_DISCARDED'];}
                    $this->remove($proposal);$old[]=$proposal['storage_key'];
                }
                $this->pdo->prepare('UPDATE media_review_submissions SET updated_at=CURRENT_TIMESTAMP WHERE submission_id=?')->execute([$id]);
                return ['submission_id'=>$id,'physician_id'=>(string)$candidate['owner_id']];
            };
            $audit=new MediaReviewAudit($this->pdo,$context,$prepare,self::EVENTS[$action],self::CAPABILITY,'POST /api/internal/media-review/'.self::ROUTES[$action]);
            if(!(new AuthorizationBoundary())->authorize($context,$requirement,$audit)->allowed())throw new RuntimeException('improvement_denied');
            $attempt=true;if(!$this->pdo->commit())throw new RuntimeException('improvement_commit_failed');
        }catch(\Throwable $e){
            $safe=!$attempt;
            if($this->pdo->inTransaction())try{$safe=$this->pdo->rollBack();}catch(\Throwable){$safe=false;error_log('improvement_rollback_unconfirmed');}
            if($safe)$this->cleanup($new);else error_log('improvement_commit_requires_reconciliation');
            if($safe&&in_array($e->getMessage(),['NO_SAFE_IMPROVEMENT','ALREADY_TRANSPARENT'],true))return ['ok'=>true,'status'=>$e->getMessage(),'submission_id'=>$id];
            throw $e;
        }finally{foreach($temps as $path)if(is_file($path))unlink($path);}
        $this->cleanup($old);
        return ['ok'=>true,'submission_id'=>$id,'technical_status'=>'READY','review_status'=>'PENDING_REVIEW',...$result];
    }
    /** Fixed-role read, canonical read authority checked by the HTTP adapter and service. */
    public function preview(?TrustedAuthorizationContext $context,string $id):string
    {
        if($context===null||$context->trustSource()!=='canonical_internal_operator')throw new RuntimeException('improvement_denied');
        MediaReviewAuthority::requireRead($context);
        [$candidate,$files]=$this->resolve($id,false);$proposal=$files['AUTO_PROPOSAL']??null;$input=$files['CORRECTED']??$files['SOURCE'];
        if(!$proposal||$proposal['input_file_id']!==$input['file_id']||$proposal['input_checksum_sha256']!==$input['checksum_sha256'])throw new RuntimeException('improvement_conflict');
        return $this->verified($proposal);
    }
    private function resolve(string $id,bool $lock):array
    {
        if(!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D',$id))throw new RuntimeException('improvement_invalid_request');
        $s=$this->pdo->prepare('SELECT owner_id FROM media_review_submissions WHERE submission_id=?');$s->execute([$id]);$owner=$s->fetchColumn();if($owner===false)throw new RuntimeException('improvement_not_found');
        if($lock){$s=$this->pdo->prepare('SELECT doctor_id FROM profiles_doctors WHERE doctor_id=? FOR UPDATE');$s->execute([$owner]);if($s->fetchColumn()===false)throw new RuntimeException('improvement_conflict');}
        $s=$this->pdo->prepare('SELECT * FROM media_review_submissions WHERE submission_id=?'.($lock?' FOR UPDATE':''));$s->execute([$id]);$candidate=$s->fetch(PDO::FETCH_ASSOC);
        if(!$candidate||$candidate['owner_id']!==$owner||$candidate['owner_type']!=='PHYSICIAN'||$candidate['purpose']!=='PHYSICIAN_PERSONAL_LOGO'||$candidate['technical_status']!=='READY'||$candidate['review_status']!=='PENDING_REVIEW')throw new RuntimeException('improvement_conflict');
        $s=$this->pdo->prepare('SELECT * FROM media_review_files WHERE submission_id=? ORDER BY role'.($lock?' FOR UPDATE':''));$s->execute([$id]);$files=[];
        foreach($s->fetchAll(PDO::FETCH_ASSOC) as $file){$role=$file['role'];$format=$file['format'];$key='private/media-review/'.hash('sha256','PHYSICIAN:'.$owner).'/'.$id.'/'.strtolower($role).'/'.$file['file_id'].'.'.$format;
            if(!in_array($role,['SOURCE','REVIEW','CORRECTED','AUTO_PROPOSAL'],true)||isset($files[$role])||!preg_match('/^[a-f0-9-]{36}$/D',$file['file_id'])||!in_array($format,['jpeg','png','webp'],true)||$file['mime_type']!=='image/'.$format||$file['storage_key']!==$key||!preg_match('/^[a-f0-9]{64}$/D',$file['checksum_sha256'])||min((int)$file['width'],(int)$file['height'],(int)$file['byte_size'])<1||max((int)$file['width'],(int)$file['height'])>8192||(int)$file['width']*(int)$file['height']>25000000||(int)$file['byte_size']>10485760)throw new RuntimeException('improvement_integrity_failed');
            if(in_array($role,['REVIEW','AUTO_PROPOSAL'],true)&&($format!=='webp'||max((int)$file['width'],(int)$file['height'])>800||(int)$file['byte_size']>153600))throw new RuntimeException('improvement_integrity_failed');
            $files[$role]=$file;
        }
        if(!isset($files['SOURCE'],$files['REVIEW']))throw new RuntimeException('improvement_integrity_failed');return [$candidate,$files];
    }
    private function verified(array $file):string
    {
        $o=$this->storage->openReadStream($file['storage_key']);try{$bytes=stream_get_contents($o['stream'],10485761);}finally{fclose($o['stream']);}
        if($bytes===false||strlen($bytes)!==(int)$file['byte_size']||$o['bytes']!==(int)$file['byte_size']||!hash_equals($file['checksum_sha256'],hash('sha256',$bytes)))throw new RuntimeException('improvement_integrity_failed');
        $info=@getimagesizefromstring($bytes);if(!$info||$info[0]!=(int)$file['width']||$info[1]!=(int)$file['height']||$info['mime']!==$file['mime_type'])throw new RuntimeException('improvement_integrity_failed');return $bytes;
    }
    private function temporary(string $bytes):string{$p=tempnam(sys_get_temp_dir(),'mxmed-improve-');if($p===false)throw new RuntimeException('improvement_temp_failed');if(!chmod($p,0600)||file_put_contents($p,$bytes)!==strlen($bytes)){unlink($p);throw new RuntimeException('improvement_temp_failed');}return $p;}
    private function newFile(array $c,string $role,array $data):array{$id=(new RandomAuditUuidProvider())->generateCanonicalUuid();return ['file_id'=>$id,'role'=>$role,'storage_key'=>'private/media-review/'.hash('sha256','PHYSICIAN:'.$c['owner_id']).'/'.$c['submission_id'].'/'.strtolower($role).'/'.$id.'.webp','mime_type'=>'image/webp','format'=>'webp','width'=>$data['width'],'height'=>$data['height'],'byte_size'=>$data['byte_size'],'checksum_sha256'=>$data['checksum_sha256']];}
    private function insert(array $f,string $id):void{$this->pdo->prepare('INSERT INTO media_review_files(file_id,submission_id,role,storage_key,mime_type,format,width,height,byte_size,checksum_sha256,input_file_id,input_checksum_sha256) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)')->execute([$f['file_id'],$id,$f['role'],$f['storage_key'],$f['mime_type'],$f['format'],$f['width'],$f['height'],$f['byte_size'],$f['checksum_sha256'],$f['input_file_id']??null,$f['input_checksum_sha256']??null]);}
    private function remove(array $f):void{$this->pdo->prepare('DELETE FROM media_review_files WHERE file_id=?')->execute([$f['file_id']]);}
    private function store(array $f,string $p,array &$keys):void{if($this->storage->exists($f['storage_key']))throw new RuntimeException('improvement_key_conflict');$keys[]=$f['storage_key'];$this->storage->storeImmutable($f['storage_key'],$p);$this->verified($f);}
    private function metadata(array $f):array{return ['mime_type'=>'image/webp','format'=>'webp','width'=>(int)$f['width'],'height'=>(int)$f['height'],'byte_size'=>(int)$f['byte_size']];}
    private function cleanup(array $keys):void{foreach($keys as $key)try{$this->storage->delete($key);}catch(\Throwable){error_log('improvement_private_cleanup_failed');}}
}
