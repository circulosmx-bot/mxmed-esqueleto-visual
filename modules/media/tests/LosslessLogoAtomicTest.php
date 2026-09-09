<?php
declare(strict_types=1);
require __DIR__.'/LogoImprovementFixture.php';
final class LosslessFailureStorage implements Media\Contracts\PrivateMediaStoragePort {
 public function __construct(private Media\Contracts\PrivateMediaStoragePort $inner,private string $mode){}
 public function exists(string $key):bool{return $this->inner->exists($key);}
 public function delete(string $key):void{$this->inner->delete($key);}
 public function storeImmutable(string $key,string $path):void{$this->inner->storeImmutable($key,$path);if($this->mode==='store'&&str_contains($key,'/improvement_input/'))throw new RuntimeException('synthetic_png_store_failure');}
 public function openReadStream(string $key):array{$o=$this->inner->openReadStream($key);if($this->mode==='integrity'&&str_contains($key,'/improvement_input/'))$o['bytes']++;return $o;}
}
function check(bool $ok,string $name):void{if(!$ok)throw new RuntimeException('FAIL '.$name);echo "PASS $name\n";}
$p=mr5Pdo();[$private]=mr5Storage();
$state=function()use($p){$tables=[];foreach(['media_review_files','media_review_submissions','profiles_doctors','media_assets','platform_audit_events'] as $table)$tables[$table]=$p->query("SELECT * FROM $table ORDER BY 1")->fetchAll();$files=[];foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator(getenv('MR5_FIXTURE_ROOT').'/private',FilesystemIterator::SKIP_DOTS)) as $file)if($file->isFile())$files[$file->getPathname()]=hash_file('sha256',$file->getPathname());ksort($files);return json_encode([$tables,$files]);};
foreach(['candidate','corrected'] as $action)foreach(['store','integrity','insert'] as $mode){
 $f=mr8Candidate('thin');
 if($action==='corrected'){$u=mr8Upload('thin');try{(new Media\Services\MediaReviewInterventionService($p,$private))->corrected(mr6Context('upload_corrected',['media_review_corrected_upload']),$f['id'],$u);}finally{unlink($u['tmp_name']);}}
 $before=$state();$storage=new LosslessFailureStorage($private,$mode);$u=mr8Upload('thin');
 if($mode==='insert')$p->exec("CREATE TRIGGER mr812_lossless_fail BEFORE INSERT ON media_review_files FOR EACH ROW BEGIN IF NEW.role='IMPROVEMENT_INPUT' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic_png_insert_failure'; END IF; END");
 try{
  if($action==='candidate')(new Media\Services\PhysicianMediaReviewCandidateService($p,$storage,'PHYSICIAN_PERSONAL_LOGO'))->upload($f['doctor'],$u);
  else (new Media\Services\MediaReviewInterventionService($p,$storage))->corrected(mr6Context('upload_corrected',['media_review_corrected_upload']),$f['id'],$u);
  throw new LogicException('unexpected_success');
 }catch(RuntimeException|PDOException $e){check($state()===$before,'lossless_failure_preserves_rows_files_'.$action.'_'.$mode);}
 finally{unlink($u['tmp_name']);if($mode==='insert')$p->exec('DROP TRIGGER mr812_lossless_fail');}
}
$photo=mr5Candidate($p);check(!isset(mr6Rows($p,$photo['id'])['IMPROVEMENT_INPUT']),'photo_no_lossless_derivative');
$f=mr8Candidate();$rows=mr6Rows($p,$f['id']);$p->prepare("DELETE FROM media_review_files WHERE submission_id=? AND role='IMPROVEMENT_INPUT'")->execute([$f['id']]);$private->delete($rows['IMPROVEMENT_INPUT']['storage_key']);
$s=new Media\Services\LogoImprovementService($p,$private);$before=$state();$result=$s->mutate(mr8Context('generate'),'generate',$f['id']);check($result['status']==='IMPROVEMENT_INPUT_UNAVAILABLE'&&$state()===$before,'legacy_candidate_no_fallback_no_mutation');
$f=mr8Candidate('thin');$s->mutate(mr8Context('generate'),'generate',$f['id']);$before=mr6Rows($p,$f['id']);$s->mutate(mr8Context('accept'),'accept',$f['id']);$after=mr6Rows($p,$f['id']);check($before['IMPROVEMENT_INPUT']===$after['IMPROVEMENT_INPUT'],'accept_preserves_lossless_input');
$before=$state();$r=$s->mutate(mr8Context('generate'),'generate',$f['id']);check($r['status']==='ALREADY_IMPROVED'&&$state()===$before,'repeated_improvement_no_duplicate');
// A real 25 MP upload normalizes once; subsequent automation receives only its bounded PNG.
$f=mr8Candidate('white',5000,5000);$rows=mr6Rows($p,$f['id']);
check((int)$rows['SOURCE']['width']===5000&&(int)$rows['SOURCE']['height']===5000&&(int)$rows['IMPROVEMENT_INPUT']['width']===800&&(int)$rows['IMPROVEMENT_INPUT']['height']===800,'25MP_candidate_bounded_lossless_and_review');
$u=mr8Upload('thin',1600,800);try{(new Media\Services\MediaReviewInterventionService($p,$private))->corrected(mr6Context('upload_corrected',['media_review_corrected_upload']),$f['id'],$u);}finally{unlink($u['tmp_name']);}
$after=mr6Rows($p,$f['id']);check($after['SOURCE']===$rows['SOURCE']&&(int)$after['IMPROVEMENT_INPUT']['width']===800&&(int)$after['IMPROVEMENT_INPUT']['height']===400&&$after['IMPROVEMENT_INPUT']['file_id']!==$rows['IMPROVEMENT_INPUT']['file_id'],'corrected_replaces_bounded_input_preserves_source');
$u=mr8Upload();try{(new Media\Services\MediaReviewInterventionService($p,$private))->corrected(mr6Context('upload_corrected',['media_review_corrected_upload']),$photo['id'],$u);}finally{unlink($u['tmp_name']);}
check(!isset(mr6Rows($p,$photo['id'])['IMPROVEMENT_INPUT']),'corrected_photo_no_lossless_derivative');
