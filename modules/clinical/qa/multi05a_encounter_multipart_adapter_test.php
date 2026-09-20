<?php
declare(strict_types=1);
// Load route function definitions through an unknown CLI route; no DB path runs.
$_SERVER['REQUEST_METHOD']='GET';
$_SERVER['REQUEST_URI']='/api/clinical/__multi05a_pure__';
$_SERVER['SCRIPT_NAME']='/api/clinical/index.php';
ob_start(); require dirname(__DIR__,3).'/api/clinical/index.php'; ob_end_clean();
require_once dirname(__DIR__,3).'/api/_lib/clinical_encounter_multipart_adapter.php';
function verify(bool $ok,string $name):void {if(!$ok)throw new RuntimeException($name);echo "PASS $name\n";}
function rejected(callable $fn,string $code):bool {try{$fn();return false;}catch(Throwable $e){return $e->getMessage()===$code;}}
verify(rejected(fn()=>clinical_encounter_multipart_file([]),'MULTIPART_FILE_REQUIRED'),'W02 no file cannot become JSON');
verify(rejected(fn()=>clinical_encounter_multipart_file(['file'=>['error'=>UPLOAD_ERR_OK,'tmp_name'=>__FILE__,'name'=>'fake.pdf']]),'MULTIPART_FILE_REQUIRED'),'W02 local client path is not PHP upload');
$oldRoot=getenv('MXMED_CLINICAL_PRIVATE_STORAGE_ROOT');$oldTtl=getenv('MXMED_CLINICAL_STAGING_TTL_SECONDS');
try {
 putenv('MXMED_CLINICAL_PRIVATE_STORAGE_ROOT');putenv('MXMED_CLINICAL_STAGING_TTL_SECONDS=60');
 verify(rejected(fn()=>clinical_encounter_multipart_config(),'V1_MULTIPART_STORAGE_NOT_READY'),'W03 missing root');
 putenv('MXMED_CLINICAL_PRIVATE_STORAGE_ROOT=/synthetic/not-created');
 foreach([false,'','0','-1','1.5','1e3','86401','999999999999999999999'] as $ttl){putenv('MXMED_CLINICAL_STAGING_TTL_SECONDS'.($ttl===false?'':'='.$ttl));verify(rejected(fn()=>clinical_encounter_multipart_config(),'V1_MULTIPART_STORAGE_NOT_READY'),'W04 invalid TTL '.var_export($ttl,true));}
 putenv('MXMED_CLINICAL_STAGING_TTL_SECONDS=60');verify(clinical_encounter_multipart_config()===['/synthetic/not-created',60],'explicit TTL only');
} finally {putenv('MXMED_CLINICAL_PRIVATE_STORAGE_ROOT'.($oldRoot===false?'':'='.$oldRoot));putenv('MXMED_CLINICAL_STAGING_TTL_SECONDS'.($oldTtl===false?'':'='.$oldTtl));}
foreach(['STAGING_MAX_BYTES_EXCEEDED','STAGING_MIME_NOT_ALLOWED','STAGING_SOURCE_NOT_REGULAR_FILE'] as $code)verify(clinical_encounter_multipart_error(new RuntimeException($code))===[400,'MULTIPART_FILE_INVALID'],'W14 '.$code);
verify(clinical_encounter_multipart_error(new RuntimeException('MULTIPART_STORAGE_SCHEMA_NOT_READY:private-detail'))===[503,'V1_MULTIPART_STORAGE_NOT_READY'],'schema unavailable');
verify(clinical_encounter_multipart_error(new RuntimeException('/private/secret'))===[500,'server_error'],'W14 unknown path redacted');
foreach(['MULTIPART_DATABASE_COORDINATION_FAILED','MULTIPART_STAGED_COORDINATION_FAILED'] as $code)verify(clinical_encounter_multipart_error(new RuntimeException($code))===[500,'server_error'],'W13 coordination fails closed');
foreach([false,true] as $replay){[$status,$response]=clinical_encounter_multipart_response(['document_id'=>1,'document_uuid'=>'uuid','_idempotency_replay'=>$replay,'cleanup_pending'=>true]);verify($status===($replay?200:201)&&$response['meta']['idempotency_replay']===$replay&&!isset($response['data']['cleanup_pending'])&&$response['meta']['binary_cleanup_pending']===true,'W15 canonical response replay '.(int)$replay);}
foreach([false,true] as $replay){[$status,$response]=clinical_document_amendment_multipart_response(['document_revision_id'=>3,'new_document_id'=>2,'new_document_uuid'=>'uuid-2','_idempotency_replay'=>$replay,'cleanup_pending'=>false]);verify($status===($replay?200:201)&&$response['meta']['idempotency_replay']===$replay&&!isset($response['data']['cleanup_pending'])&&$response['data']['document_revision_id']===3,'C05 amendment response replay '.(int)$replay);}
// Fake PDO captures actual canonical builder/writer parameters without a driver/connection.
class W05Statement extends PDOStatement {
 public function __construct(private W05Pdo $pdo,private bool $columns=false,private bool $document=false){}
 public function execute(?array $params=null):bool {if($this->document)$this->pdo->insert=$params;return true;}
 public function fetchAll(int $mode=PDO::FETCH_DEFAULT,mixed ...$args):array {return array_map(fn($x)=>['Field'=>$x],['document_uuid','document_type','patient_id','encounter_ref_id']);}
}
class W05Pdo extends PDO {
 public array $insert=[];
 public function __construct(){}
 public function inTransaction():bool{return true;}
 public function query(string $query,?int $fetchMode=null,mixed ...$args):PDOStatement|false{return new W05Statement($this,true);}
 public function prepare(string $query,array $options=[]):PDOStatement|false{return new W05Statement($this,false,str_starts_with($query,'INSERT INTO clinical_documents '));}
 public function lastInsertId(?string $name=null):string|false{return '1';}
}
$p=new W05Pdo();$row=['patient_id'=>'synthetic','encounter_id'=>1];$payload=['document_type'=>'pdf'];$uuid='11111111-1111-4111-8111-111111111111';
clinical_v1_document_insert($p,$row,$payload,'synthetic-user',$uuid);verify($p->insert[':doc_document_uuid']===$uuid,'W08 supplied UUID persisted exactly');
clinical_v1_document_insert($p,$row,$payload,'synthetic-user');$generated=$p->insert[':doc_document_uuid'];verify($generated!==$uuid&&preg_match('/^[a-f0-9-]{36}$/',$generated)===1,'W09 default builder UUID preserved');
echo "MULTI05A_ADAPTER_QA=PASS; ANY_DATABASE_CONNECTED=false\n";

foreach(['IDEMPOTENCY_KEY_INVALID'=>400,'IDEMPOTENCY_KEY_REUSED'=>409,'IDEMPOTENCY_RESULT_NOT_READY'=>409] as $code=>$status) verify(clinical_encounter_multipart_error(new ClinicalIdempotencyException($code,'nonpublic internal details',$status))===[$status,$code],'canonical idempotency '.$code);
foreach(['ENCOUNTER_VOIDED','ENCOUNTER_TERMINAL','DOCUMENT_CONTEXT_MISMATCH','DOCUMENT_OPERATION_UNSUPPORTED'] as $code){$error=new RuntimeException($code);verify(clinical_encounter_multipart_error($error)===[clinical_v1_error_status($error),clinical_v1_error_code($error)],'canonical policy '.$code);}
foreach(['DOCUMENT_TYPE_MISMATCH','DOCUMENT_ALREADY_SUPERSEDED','DOCUMENT_LINEAGE_INVALID','DOCUMENT_NOT_FOUND'] as $code){$error=new RuntimeException($code);verify(clinical_encounter_multipart_error($error)===[clinical_v1_error_status($error),clinical_v1_error_code($error)],'canonical amendment '.$code);}
