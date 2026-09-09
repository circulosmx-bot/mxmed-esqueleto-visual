<?php
declare(strict_types=1);
require __DIR__.'/GalleryReviewFixture.php';
class Mr10RacePdo extends PDO{
 public function __construct(private string $gate){parent::__construct('mysql:host=127.0.0.1;port=3309;dbname=mxmed;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);}
 public function commit():bool{if($this->gate!==''){file_put_contents($this->gate.'.ready','locked');$until=microtime(true)+15;while(!is_file($this->gate.'.go')){if(microtime(true)>$until)throw new RuntimeException('race_timeout');usleep(10000);}}return parent::commit();}
}
$mode=$argv[1];$doctor=$argv[2];$id=$argv[3];$gate=$argv[4]??'';
if($gate!==''&&!str_starts_with($gate,getenv('MR5_FIXTURE_ROOT').'/race-'))throw new RuntimeException('isolated_gate_required');
$p=new Mr10RacePdo($gate);[$private,$public]=mr5Storage();
try{
 if($mode==='approve')$r=(new Media\Services\GalleryApprovalService($p,$private,$public))->approve(mr5Context(),$id);
 elseif($mode==='replace')$r=(new Media\Services\MediaReplacementService($p,$private))->request(mr9Context(),$id,'OTHER');
 elseif($mode==='withdraw'){(new Media\Services\GalleryReviewCandidateService($p,$private))->withdraw($doctor,$id);$r=['ok'=>true];}
 else{$u=mr8Upload();try{if($mode==='candidate')(new Media\Services\GalleryReviewCandidateService($p,$private))->upload($doctor,$u);elseif($mode==='direct')(new Media\Services\DoctorGalleryService($p,$public))->upload($doctor,$u);elseif($mode==='corrected')(new Media\Services\MediaReviewInterventionService($p,$private))->corrected(mr6Context('upload_corrected',['media_review_corrected_upload']),$id,$u);else throw new RuntimeException('invalid_mode');$r=['ok'=>true];}finally{if(is_file($u['tmp_name']))unlink($u['tmp_name']);}}
 echo json_encode($r);
}catch(Throwable $e){echo json_encode(['error'=>$e->getMessage()]);exit(2);}
