<?php
declare(strict_types=1);
require __DIR__.'/MediaReviewInterventionFixture.php';
final class Mr6PausingStorage implements Media\Contracts\PrivateMediaStoragePort,Media\Contracts\PublicMediaStoragePort {
    private bool $paused=false;
    public function __construct(private Media\Contracts\PrivateMediaStoragePort|Media\Contracts\PublicMediaStoragePort $inner,private string $gate){}
    public function storeImmutable(string $k,string $p):void{
        $this->inner->storeImmutable($k,$p);
        if($this->gate!==''&&!$this->paused){$this->paused=true;file_put_contents($this->gate.'.ready','locked');$deadline=microtime(true)+10;while(!is_file($this->gate.'.go')){if(microtime(true)>$deadline)throw new RuntimeException('race_gate_timeout');usleep(10000);}}
    }
    public function openReadStream(string $k):array{return $this->inner->openReadStream($k);}
    public function exists(string $k):bool{return $this->inner->exists($k);}
    public function delete(string $k):void{$this->inner->delete($k);}
}
$mode=$argv[1]??'';$id=$argv[2]??'';$gate=$argv[3]??'';[$private,$public]=mr5Storage();
if($gate!==''&&!str_starts_with($gate,getenv('MR5_FIXTURE_ROOT').'/race-'))throw new RuntimeException('isolated_gate_required');
try{
 if($mode==='corrected'){$upload=mr6File();try{$result=(new Media\Services\MediaReviewInterventionService(mr5Pdo(),new Mr6PausingStorage($private,$gate)))->corrected(mr6Context('upload_corrected',['media_review_corrected_upload']),$id,$upload);}finally{unlink($upload['tmp_name']);}}
 elseif($mode==='approve')$result=(new Media\Services\ProfilePhotoApprovalService(mr5Pdo(),$private,new Mr6PausingStorage($public,$gate)))->approve(mr5Context(),$id);
 else throw new RuntimeException('invalid_worker');
 echo json_encode($result);
}catch(Throwable $e){echo json_encode(['error'=>$e->getMessage()]);exit(2);}
