<?php
declare(strict_types=1);
require __DIR__.'/ReviewBatchFixture.php';
class Mr11RacePdo extends PDO{
 public function __construct(private string $gate){parent::__construct('mysql:host=127.0.0.1;port=3309;dbname=mxmed;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);}
 public function commit():bool{if($this->gate!==''){file_put_contents($this->gate.'.ready','locked');$until=microtime(true)+15;while(!is_file($this->gate.'.go')){if(microtime(true)>$until)throw new RuntimeException('race_timeout');usleep(10000);}}return parent::commit();}
}
[$script,$mode,$doctor,$batch]=$argv;$gate=$argv[4]??'';
if($gate!==''&&!str_starts_with($gate,getenv('MR5_FIXTURE_ROOT').'/race-'))throw new RuntimeException('isolated_gate_required');
$p=new Mr11RacePdo($gate);
try{
 if($mode==='upload')$result=mr11Candidate($doctor,'DOCTOR_GALLERY',$p);
 else $result=['submitted'=>(new Media\Services\MediaReviewBatchService($p))->submit($doctor,$mode==='auto'?$batch:null)];
 echo json_encode($result);
}catch(Throwable $e){echo json_encode(['error'=>$e->getMessage()]);exit(2);}
