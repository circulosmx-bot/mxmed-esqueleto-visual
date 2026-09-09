<?php
declare(strict_types=1);
require __DIR__.'/MediaReplacementFixture.php';
class Mr9PausingPdo extends PDO {
 public function __construct(private string $gate){parent::__construct('mysql:host=127.0.0.1;port=3309;dbname=mxmed','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);}
 public function commit():bool{
  if($this->gate!==''){file_put_contents($this->gate.'.ready','locked');$deadline=microtime(true)+15;while(!is_file($this->gate.'.go')){if(microtime(true)>$deadline)throw new RuntimeException('race_timeout');usleep(10000);}}
  return parent::commit();
 }
}
$mode=$argv[1];$id=$argv[2];$kind=$argv[3];$gate=$argv[4]??'';
if($gate!==''&&!str_starts_with($gate,getenv('MR5_FIXTURE_ROOT').'/race-'))throw new RuntimeException('isolated_gate_required');
$p=new Mr9PausingPdo($gate);[$private,$public]=mr5Storage();
try{
 if($mode==='replace')$r=(new Media\Services\MediaReplacementService($p,$private))->request(mr9Context(),$id,'OTHER','Otra imagen');
 elseif($mode==='approve'){$class=$kind==='photo'?Media\Services\ProfilePhotoApprovalService::class:Media\Services\PhysicianLogoApprovalService::class;$r=(new $class($p,$private,$public))->approve(mr5Context(),$id);}
 elseif($mode==='corrected'){$u=mr8Upload('white');try{$r=(new Media\Services\MediaReviewInterventionService($p,$private))->corrected(mr6Context('upload_corrected',['media_review_corrected_upload']),$id,$u);}finally{unlink($u['tmp_name']);}}
 else $r=(new Media\Services\LogoImprovementService($p,$private))->mutate(mr8Context($mode),$mode,$id);
 echo json_encode($r);
}catch(Throwable $e){echo json_encode(['error'=>$e->getMessage()]);exit(2);}
