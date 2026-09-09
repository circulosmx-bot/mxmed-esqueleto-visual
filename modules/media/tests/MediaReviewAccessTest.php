<?php
declare(strict_types=1);
require __DIR__.'/../../../api/_lib/db.php';
require __DIR__.'/../private-bootstrap.php';
require __DIR__.'/../services/MediaReviewAccessService.php';
require __DIR__.'/../http/MediaReviewHttpContext.php';
use Media\Services\{MediaReviewAuthority as Authority,MediaReviewAccessService as Access};
use Media\Http\MediaReviewHttpContext;
use Media\Contracts\PrivateMediaStoragePort;
use Platform\Contracts\{AuthorizationContext,TrustedAuthorizationContext,AuthorizationPlane,ActorReference,SessionReference,CapabilitySet};
function verify(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
function denied(callable $call,string $error):void{try{$call();}catch(Throwable $e){verify($e->getMessage()===$error,'Unexpected '.$e->getMessage());return;}throw new RuntimeException('Expected '.$error);}
function subject(array $replace=[]):AuthorizationContext{
 return new AuthorizationContext(...array_replace(['realActor'=>new ActorReference('operator','mr2_qa'),'sessionReference'=>new SessionReference('mr2_qa_session'),'accountId'=>'mr2_qa','credentialVersion'=>1,'capabilities'=>new CapabilitySet([Authority::CAPABILITY]),'action'=>'read','resource'=>'media_review_submission','authorizationPlane'=>AuthorizationPlane::INTERNAL_OPERATOR,'riskLevel'=>'R0'],$replace));
}
$p=mxmed_pdo();$storage=mxmed_private_media_storage();$access=new Access($p,$storage);
$id='42adffaa-3344-47cf-9bf5-6831c969cbd5';
$good=TrustedAuthorizationContext::fromBackend(subject());
verify(Authority::requirement()->actorAuthenticatedRequired(),'authentication mandatory');
verify(!Authority::requirement()->auditTrailRequired(),'no per-thumbnail mandatory audit');
Authority::requireRead($good);
foreach([null,subject(),TrustedAuthorizationContext::fromClient(subject()),
 TrustedAuthorizationContext::fromBackend(subject(['authorizationPlane'=>AuthorizationPlane::CUSTOMER_PROFESSIONAL])),
 TrustedAuthorizationContext::fromBackend(subject(['capabilities'=>new CapabilitySet()])),
 TrustedAuthorizationContext::fromBackend(subject(['capabilities'=>new CapabilitySet(['other_read'])])),
 TrustedAuthorizationContext::fromBackend(subject(),transitionalOpen:true),
 TrustedAuthorizationContext::fromBackend(subject(),accountActive:false),
 TrustedAuthorizationContext::fromBackend(subject(['sessionReference'=>null])),
 TrustedAuthorizationContext::fromBackend(subject(['credentialVersion'=>null]))] as $bad){
 denied(fn()=>$access->metadata($bad,$id),'review_access_denied');
 denied(fn()=>$access->reviewBytes($bad,'invalid'),'review_access_denied');
}
foreach(['invalid','expired','revoked','superseded'] as $status)denied(fn()=>Authority::requireRead(TrustedAuthorizationContext::fromBackend(subject(),sessionStatus:$status)),'review_access_denied');
$fixture=['media_review_dev_operator'=>['account_id'=>'mr2_local_operator','expires_at'=>time()+120,'session_status'=>'active','account_active'=>true,'review_read_granted'=>true]];
$env=['MXMED_ENVIRONMENT'=>'local','MXMED_MEDIA_REVIEW_DEV_OPERATOR_ENABLED'=>'1'];$server=['REMOTE_ADDR'=>'127.0.0.1'];
verify(MediaReviewHttpContext::resolve($fixture,'qa_session',$server,$env)!==null,'opt-in fixture');
foreach([[],['MXMED_MEDIA_REVIEW_DEV_OPERATOR_ENABLED'=>'1'],array_replace($env,['MXMED_MEDIA_REVIEW_DEV_OPERATOR_ENABLED'=>'0'])] as $badEnv)verify(MediaReviewHttpContext::resolve($fixture,'qa_session',$server,$badEnv)===null,'fixture default denied');
foreach(['APP_ENV','MXMED_ENV','MXMED_ENVIRONMENT','ENVIRONMENT'] as $name)foreach(['prod','production','staging'] as $value)verify(MediaReviewHttpContext::resolve($fixture,'qa_session',$server,array_replace($env,[$name=>$value]))===null,'production fixture denied');
verify(MediaReviewHttpContext::resolve($fixture,'qa_session',['REMOTE_ADDR'=>'203.0.113.1'],$env)===null,'nonloopback denied');
verify(MediaReviewHttpContext::resolve(['doctor_id'=>'1','user_id'=>'doctor'],'qa_session',$server,$env)===null,'physician not reviewer');
$expired=$fixture;$expired['media_review_dev_operator']['expires_at']=time()-1;
denied(fn()=>Authority::requireRead(MediaReviewHttpContext::resolve($expired,'qa_session',$server,$env)),'review_access_denied');
$metadata=$access->metadata($good,$id);verify($metadata['review_status']==='PENDING_REVIEW'&&$metadata['technical_status']==='READY','candidate states');
$json=json_encode($metadata);
foreach(['storage_key','public_url','/source/','SOURCE','checksum','private-media'] as $secret)verify(!str_contains($json,$secret),'metadata leak '.$secret);
$s=$p->prepare("SELECT * FROM media_review_files WHERE submission_id=? AND role='REVIEW'");$s->execute([$id]);$review=$s->fetch();
$bytes=$access->reviewBytes($good,$id);verify(hash('sha256',$bytes)===$review['checksum_sha256'],'exact checksum');
verify(strlen($bytes)===(int)$review['byte_size'],'exact length');
denied(fn()=>$access->metadata($good,'11111111-1111-4111-8111-111111111111'),'review_not_found');
foreach(['missing','tampered','wrong_size'] as $fault){
 $fake=new class($fault,$bytes) implements PrivateMediaStoragePort {
  public function __construct(private string $fault,private string $bytes){}
  public function openReadStream(string $k):array{if($this->fault==='missing')throw new RuntimeException('missing');$s=fopen('php://memory','w+b');$data=$this->fault==='tampered'?str_repeat('x',strlen($this->bytes)):$this->bytes;fwrite($s,$data);rewind($s);return ['stream'=>$s,'bytes'=>strlen($data)+($this->fault==='wrong_size'?1:0)];}
  public function exists(string $k):bool{return true;}public function storeImmutable(string $k,string $p):void{throw new RuntimeException('write forbidden');}public function delete(string $k):void{throw new RuntimeException('delete forbidden');}
 };
 denied(fn()=>(new Access($p,$fake))->reviewBytes($good,$id),'review_unavailable');
}
// Simulate unavailable DB without touching the actual database.
$unavailable=new class extends PDO {public function __construct(){}public function prepare(string $q,array $o=[]):PDOStatement|false{throw new RuntimeException('db_unavailable');}};
denied(fn()=>(new Access($unavailable,$storage))->metadata(null,$id),'review_access_denied');
denied(fn()=>(new Access($unavailable,$storage))->metadata($good,$id),'db_unavailable');
echo "PASS: trusted plane/capability/session boundary; dev environment guards; metadata privacy; exact REVIEW integrity; missing/tampered/size failure; authorization before DB; read-only candidate access\n";
