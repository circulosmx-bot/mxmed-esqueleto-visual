<?php
declare(strict_types=1);
require_once dirname(__DIR__,3).'/api/_lib/clinical_private_binary_retrieval.php';
final class RetrievalPdoFake extends PDO
{
    public array $queries=[];
    public array $doc=[];
    public ?array $manifest=null;
    public ?array $encounter=['doctor_id'=>'doctor','patient_id'=>'patient'];
    public bool $linked=true;
    public bool $transaction=false;
    public function __construct() {}
    public function getAttribute(int $attribute): mixed {return PDO::ERRMODE_EXCEPTION;}
    public function inTransaction(): bool {return $this->transaction;}
    public function prepare(string $query,array $options=[]): PDOStatement|false {
        if (!str_starts_with($query,'SELECT ')) throw new RuntimeException('non-SELECT attempted');
        $this->queries[]=$query;
        return new RetrievalStatementFake($this,$query);
    }
}
final class RetrievalStatementFake extends PDOStatement
{
    private array $params=[];
    public function __construct(private RetrievalPdoFake $db,private string $sql) {}
    public function execute(?array $params=null): bool {$this->params=$params??[];return true;}
    public function fetch(int $mode=PDO::FETCH_DEFAULT,int $orientation=PDO::FETCH_ORI_NEXT,int $offset=0): mixed {
        $p=$this->params;$d=$this->db;
        if(str_contains($this->sql,'FROM clinical_documents ')) {
            $field=str_contains($this->sql,'WHERE id=')?'id':'document_uuid';
            return (string)($d->doc[$field]??'')===$p[':token']?$d->doc:false;
        }
        if(str_contains($this->sql,'FROM patients_doctor_links')) {
            qa($p[':doctor']==='doctor'&&$p[':patient']==='patient'&&str_contains($this->sql,"status='active'"),'canonical active scope');
            return $d->linked?['patient_id'=>'patient']:false;
        }
        if(str_contains($this->sql,'FROM clinical_encounters'))return $d->encounter??false;
        if(str_contains($this->sql,'FROM clinical_document_binaries')) {
            qa(str_contains($this->sql,'variant_version=1'),'variant version');
            return $d->manifest && $d->manifest['variant_role']===$p[':variant']?$d->manifest:false;
        }
        throw new RuntimeException('unexpected query');
    }
}
function qa(bool $ok,string $message): void {if(!$ok)throw new RuntimeException($message);}
function rmqa(string $path):void {if(is_file($path)||is_link($path)){unlink($path);return;}if(!is_dir($path))return;foreach(new FilesystemIterator($path) as $e)rmqa($e->getPathname());rmdir($path);}
$root=sys_get_temp_dir().'/mxmed_multi04a_'.bin2hex(random_bytes(8));mkdir($root,0700);
$cases=0;
try {
    $storage=new ClinicalPrivateBinaryStorage($root.'/private',dirname(__DIR__,3));
    $bytes="%PDF-1.4\nSynthetic retrieval QA\n%%EOF\n";$fixture=$root.'/fixture.pdf';file_put_contents($fixture,$bytes);
    $staged=$storage->stageFile($fixture);$docUuid=ClinicalPrivateBinaryStorage::uuidV4();$binUuid=ClinicalPrivateBinaryStorage::uuidV4();$key=$storage->buildFinalKey($docUuid,$binUuid,'ORIGINAL');
    $storage->finalizeCreateOnly($staged['staging_key'],$key,$staged['sha256'],$staged['byte_length']);$storage->deleteUncommitted($staged['staging_key']);
    $manifest=['binary_uuid'=>$binUuid,'variant_role'=>'ORIGINAL','variant_version'=>1,'storage_key'=>$key,'sha256'=>$staged['sha256'],'byte_length'=>(string)strlen($bytes),'mime_type'=>'application/pdf','source_filename'=>"../folder/qa\r\n\".pdf"];
    foreach(['R01','R02','R03','R04','R05','R06','R07','R08','R09','R10','R11','R12','R13','R14','voided','uuid','patient-mismatch','missing-encounter','invalid-mime','invalid-variant','uncommitted','missing-document'] as $case) {
        $db=new RetrievalPdoFake();$db->doc=['id'=>'1','document_uuid'=>$docUuid,'patient_id'=>'patient','encounter_ref_id'=>'9','document_type'=>'pdf','status'=>'generated'];$db->manifest=$manifest;
        $expected=null;$variant='ORIGINAL';$token='1';
        switch($case){
            case 'R02':case 'R12':$db->linked=false;$expected='DOCUMENT_BINARY_NOT_FOUND';break;
            case 'R03':$db->encounter['doctor_id']='other';$expected='DOCUMENT_BINARY_NOT_FOUND';break;
            case 'R04':$db->encounter['doctor_id']=null;$expected='DOCUMENT_BINARY_NOT_FOUND';break;
            case 'R05':$db->doc['encounter_ref_id']=null;break;
            case 'R06':$db->manifest=null;$expected='DOCUMENT_BINARY_NOT_FOUND';break;
            case 'R07':$variant='DISPLAY';$expected='DOCUMENT_BINARY_NOT_FOUND';break;
            case 'R08':$db->manifest['storage_key']=$storage->buildFinalKey(ClinicalPrivateBinaryStorage::uuidV4(),$binUuid,'ORIGINAL');$expected='DOCUMENT_BINARY_MISSING';break;
            case 'R09':$db->manifest['sha256']=str_repeat('0',64);$expected='DOCUMENT_BINARY_INTEGRITY_MISMATCH';break;
            case 'R10':$db->manifest['byte_length']='1';$expected='DOCUMENT_BINARY_INTEGRITY_MISMATCH';break;
            case 'voided':$db->doc['status']='voided';break;
            case 'uuid':$token=$docUuid;break;
            case 'patient-mismatch':$db->encounter['patient_id']='other';$expected='DOCUMENT_BINARY_NOT_FOUND';break;
            case 'missing-encounter':$db->encounter=null;$expected='DOCUMENT_BINARY_NOT_FOUND';break;
            case 'invalid-mime':$db->manifest['mime_type']='text/html';$expected='DOCUMENT_BINARY_INTEGRITY_MISMATCH';break;
            case 'invalid-variant':$variant='OTHER';$expected='DOCUMENT_BINARY_NOT_FOUND';break;
            case 'uncommitted':$db->transaction=true;$expected='DOCUMENT_BINARY_NOT_FOUND';break;
            case 'missing-document':$token='2';$expected='DOCUMENT_BINARY_NOT_FOUND';break;
        }
        $before=serialize([$db->doc,$db->manifest,$db->encounter,$storage->inventory(true)]);
        try {
            $result=(new ClinicalPrivateBinaryRetrieval($db,$storage))->retrieve('doctor','user',$token,$variant);
            $stream=$result['stream'];
            try {
                qa($expected===null,'unexpected success '.$case);
                qa(stream_get_contents($result['stream'])===$bytes,'stream bytes');
                qa($result['binary']['mime_type']==='application/pdf'&&$result['binary']['byte_length']===strlen($bytes),'descriptor integrity');
                qa($result['document']['status']===$db->doc['status'],'status policy');
                qa($result['binary']['source_filename']==='qa.pdf','filename sanitation');
                unset($result['stream']);$json=json_encode($result,JSON_THROW_ON_ERROR);
                foreach(['storage_key','staging_key',$root,$key,'://'] as $forbidden)qa(!str_contains($json,$forbidden),'descriptor disclosure');
            } finally {if(is_resource($stream))fclose($stream);}
        } catch (RuntimeException $e) {qa($expected!==null&&$e->getMessage()===$expected,'wrong error '.$case.': '.$e->getMessage());}
        qa($before===serialize([$db->doc,$db->manifest,$db->encounter,$storage->inventory(true)]),'retrieval mutated state');
        if(in_array($case,['R02','R03','R04','R12','patient-mismatch','missing-encounter','missing-document'],true)) {
            qa(!str_contains(implode(' ',$db->queries),'clinical_document_binaries'),'denial reached manifest/storage');
        }
        if($case==='R05')qa(!str_contains(implode(' ',$db->queries),'FROM clinical_encounters'),'patient-level encounter manufactured');
        $cases++;
    }
    echo "MULTI04A_RETRIEVAL_QA=PASS\nMULTI04A_RETRIEVAL_QA_SCENARIOS=$cases\nANY_DATABASE_CONNECTED=false\n";
} finally {rmqa($root);}
qa(!file_exists($root),'QA root remains');echo "MULTI04A_RESIDUAL_TEMP_ROOT_COUNT=0\n";
