<?php
declare(strict_types=1);
require __DIR__.'/LogoImprovementFixture.php';
use Media\Contracts\PrivateMediaStoragePort;
use Media\Services\LogoImprovementService;
final class LosslessOnlyStorage implements PrivateMediaStoragePort {
 public array $opened=[];
 public bool $generating=true;
 public function __construct(private PrivateMediaStoragePort $inner){}
 public function openReadStream(string $key):array{
  $this->opened[]=$key;
  if(str_contains($key,'/source/')||str_contains($key,'/corrected/')||($this->generating&&str_contains($key,'/review/')))throw new LogicException('Original opened by improvement');
  return $this->inner->openReadStream($key);
 }
 public function storeImmutable(string $key,string $path):void{$this->inner->storeImmutable($key,$path);}
 public function exists(string $key):bool{return $this->inner->exists($key);}
 public function delete(string $key):void{$this->inner->delete($key);}
}
$failures=[];
function ensure(bool $value,string $label):void{global $failures;if(!$value)$failures[]=$label;echo ($value?'PASS ':'FAIL ').$label."\n";}
$p=mr5Pdo();[$private]=mr5Storage();$spy=new LosslessOnlyStorage($private);$service=new LogoImprovementService($p,$spy);
foreach(['white','offwhite','thin','gradient','photo','transparent'] as $kind){
 $f=mr8Candidate($kind);$before=mr6Rows($p,$f['id']);$spy->opened=[];
 $result=$service->mutate(mr8Context('generate'),'generate',$f['id']);$after=mr6Rows($p,$f['id']);
 foreach(['source','corrected','review'] as $role)ensure(count(array_filter($spy->opened,fn($key)=>str_contains($key,'/'.$role.'/')))===0,'zero_'.$role.'_opens_'.$kind);
 ensure(in_array($before['IMPROVEMENT_INPUT']['storage_key'],$spy->opened,true),'lossless_open_'.$kind);
 ensure($before['REVIEW']===$after['REVIEW'],'review_unchanged_'.$kind);
 if($result['status']==='PROPOSAL_CREATED'){
  if($kind==='thin'){$o=$private->openReadStream($before['IMPROVEMENT_INPUT']['storage_key']);$png=imagecreatefromstring(stream_get_contents($o['stream']));fclose($o['stream']);ensure(imagecolorat($png,20,5)===0xfefefe&&imagecolorat($png,20,0)===0xffffff,'actual_candidate_lossless_RGB254_vs_RGB255');$png=null;}
  $proposal=$after['AUTO_PROPOSAL'];ensure($proposal['input_file_id']===$before['IMPROVEMENT_INPUT']['file_id']&&$proposal['input_checksum_sha256']===$before['IMPROVEMENT_INPUT']['checksum_sha256'],'lossless_fingerprint_'.$kind);
  $o=$private->openReadStream($proposal['storage_key']);$image=imagecreatefromstring(stream_get_contents($o['stream']));fclose($o['stream']);
  $color=imagecolorsforindex($image,imagecolorat($image,300,150));ensure($color['alpha']===0&&min($color['red'],$color['green'],$color['blue'])>=240,'interior_white_'.$kind);
  if($kind==='thin')ensure(((imagecolorat($image,20,5)>>24)&127)===0,'thin_foreground_opaque');
  $transparent=0;for($y=0;$y<imagesy($image);$y++)for($x=0;$x<imagesx($image);$x++)if(((imagecolorat($image,$x,$y)>>24)&127)===127)$transparent++;
  ensure($transparent>=imagesx($image)*imagesy($image)*0.05,'useful_exterior_removed_'.$kind);$image=null;
 }else ensure($kind!=='white'&&in_array($result['status'],['NO_SAFE_IMPROVEMENT','ALREADY_TRANSPARENT'],true),'safe_abstention_'.$kind);
 echo 'NORMALIZED_'.$kind.'='.$result['status'].PHP_EOL;
}
$f=mr8Candidate();$upload=mr8Upload('offwhite');
try{(new Media\Services\MediaReviewInterventionService($p,$private))->corrected(mr6Context('upload_corrected',['media_review_corrected_upload']),$f['id'],$upload);}finally{unlink($upload['tmp_name']);}
$before=mr6Rows($p,$f['id']);$spy->opened=[];$service->mutate(mr8Context('generate'),'generate',$f['id']);
ensure(in_array($before['IMPROVEMENT_INPUT']['storage_key'],$spy->opened,true),'corrected_present_only_lossless_open');
$spy->generating=false;$service->mutate(mr8Context('accept'),'accept',$f['id']);$after=mr6Rows($p,$f['id']);$spy->generating=true;
ensure($before['SOURCE']===$after['SOURCE']&&$before['CORRECTED']===$after['CORRECTED'],'accept_without_original_reads');
// Corrupt private REVIEW bytes without changing authoritative metadata: fail closed.
$f=mr8Candidate();$files=mr6Rows($p,$f['id']);$key=$files['IMPROVEMENT_INPUT']['storage_key'];$private->delete($key);$temp=tempnam(sys_get_temp_dir(),'mr81-corrupt-');file_put_contents($temp,'corrupt');$private->storeImmutable($key,$temp);unlink($temp);
try{$service->mutate(mr8Context('generate'),'generate',$f['id']);throw new LogicException('corrupt accepted');}catch(RuntimeException $e){ensure($e->getMessage()==='improvement_integrity_failed','corrupt_review_rejected');}
// Stored metadata is checked before opening or decoding the graphic input.
foreach(['width'=>801,'byte_size'=>4194305,'checksum_sha256'=>str_repeat('z',64)] as $field=>$value){
 $f=mr8Candidate();$spy->opened=[];try{$p->prepare("UPDATE media_review_files SET $field=? WHERE submission_id=? AND role='IMPROVEMENT_INPUT'")->execute([$value,$f['id']]);}catch(PDOException $e){if(($e->errorInfo[1]??null)!==3819)throw $e;ensure($spy->opened===[],'database_rejects_review_invariant_'.$field);continue;}
 try{$service->mutate(mr8Context('generate'),'generate',$f['id']);throw new LogicException('invalid invariant accepted');}catch(RuntimeException $e){ensure($e->getMessage()==='improvement_integrity_failed'&&$spy->opened===[],'review_invariant_'.$field);}
}
echo "SOURCE_OPEN_DURING_GENERATION=false\nCORRECTED_OPEN_DURING_GENERATION=false\nREVIEW_OPEN_DURING_GENERATION=false\nIMPROVEMENT_INPUT_OPEN_DURING_GENERATION=true\n";
if($failures!==[])throw new RuntimeException('Review input regressions: '.implode(', ',$failures));
