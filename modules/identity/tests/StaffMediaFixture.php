<?php
declare(strict_types=1);
// CLI only. Configuration comes from the owning fresh-instance runner, never HTTP.
if(PHP_SAPI!=='cli')exit(1);
require_once __DIR__.'/../http/IdentityHttpComposition.php';
\Identity\Http\IdentityHttpComposition::registerAutoloader();
use Identity\Http\{IdentityHttpComposition,CanonicalHttpSessionResolver};
use Identity\Services\{CredentialAuthenticationService,RateLimitService,SessionService,SessionTokenCodec,SessionStoreFactory,InternalGovernanceService};
use Identity\Repositories\{IdentityAccountRepository,AccountCredentialRepository};
use Identity\Contracts\{RateLimitKeyHasher,SystemClock,Clock,SessionPolicy};
use Identity\Adapters\{PreviewValkeyClient,PdoSessionAccountStateAdapter};
$phase=getenv('ASE_TEAM_QA_PHASE')?:'ase-int02a';
if(!in_array($phase,['ase-adm02imp01'],true))throw new RuntimeException('isolated_phase_required');
$owned=(string)getenv('ASE_INT02A_OWNED');
if(!preg_match('~^/private/tmp/mxmed-'.preg_quote($phase,'~').'-[a-zA-Z0-9_]+$~D',realpath($owned)?:'')||!preg_match('/^mxmed_gate4d_preview_'.str_replace('-','',$phase).'_[a-f0-9]{10}$/D',(string)getenv('MXMED_DB_NAME'))||in_array((int)getenv('MXMED_DB_PORT'),[3306,3309],true))throw new RuntimeException('isolated_target_required');
$fixture=new PDO('mysql:unix_socket='.$owned.'/mysql.sock;dbname='.getenv('MXMED_DB_NAME').';charset=utf8mb4','ase_fixture',(string)getenv('ASE_INT02A_FIXTURE_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
if(realpath((string)$fixture->query('SELECT @@datadir')->fetchColumn())!==realpath($owned.'/mysql'))throw new RuntimeException('isolated_datadir_required');
$identity=IdentityHttpComposition::fromProcessEnvironment();$clock=new SystemClock();$action=$argv[1]??'';

// Test entry only, unreachable from the allowlisted TLS router; canonical services below are real.
$tokens=json_decode(file_get_contents($owned.'/sessions.json'),true,512,JSON_THROW_ON_ERROR);
$b=json_decode(stream_get_contents(STDIN),true,16,JSON_THROW_ON_ERROR);
$fixture->setAttribute(PDO::ATTR_EMULATE_PREPARES,false);

use Identity\StaffMedia\{Policy,Cipher,Storage,Images,Service,Composition};
$root=realpath($owned.'/staff-private');
if(!$root||fileowner($root)!==posix_geteuid()||(fileperms($root)&0777)!==0700)throw new RuntimeException('owned_private_root_required');
$policy=new Policy(65536,512,65536,800,153600,'synthetic-custody-only','synthetic-owned-disposal-only',file_get_contents($owned.'/media-operation-key'));
$keys=['synthetic-media-v1'=>file_get_contents($owned.'/media-key')];
if(($b['key_mode']??'')==='wrong')$keys['synthetic-media-v1']=str_repeat('x',32);
if(($b['key_mode']??'')==='unknown')$keys=['synthetic-media-v2'=>str_repeat('y',32)];
$cipher=new Cipher(array_key_first($keys),$keys);$storage=new Storage($root,153640);
$p=new PDO('mysql:host=127.0.0.1;port='.getenv('MXMED_DB_PORT').';dbname='.getenv('MXMED_DB_NAME').';charset=utf8mb4','ase_media',trim(file_get_contents($owned.'/media-db-password')),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
if(realpath((string)$p->query('SELECT @@datadir')->fetchColumn())!==realpath($owned.'/mysql'))throw new RuntimeException('isolated_datadir_required');
$service=new Service($p,new CanonicalHttpSessionResolver($identity->sessions()),$policy,$cipher,$storage,new Images($policy,$root),$root);
$cookies=isset($tokens[$b['actor']??''])?['__Host-mxmed_session'=>$tokens[$b['actor']]]:[];
try{
 if($action==='setup'){
  $im=imagecreatetruecolor(64,48);$color=imagecolorallocate($im,50,120,190);imagefilledrectangle($im,0,0,63,47,$color);imagepng($im,$root.'/synthetic.png');imagejpeg($im,$root.'/synthetic.jpg',90);
  // GD adds density/comment metadata. Evidence fixtures deliberately use the metadata-free subset.
  $raw=file_get_contents($root.'/synthetic.png');$clean=substr($raw,0,8);$at=8;
  while($at<strlen($raw)){$len=unpack('N',substr($raw,$at,4))[1];$type=substr($raw,$at+4,4);if($type!=='pHYs')$clean.=substr($raw,$at,$len+12);$at+=$len+12;}
  file_put_contents($root.'/synthetic.png',$clean);
  $raw=file_get_contents($root.'/synthetic.jpg');$clean=substr($raw,0,2);$at=2;
  while($at<strlen($raw)){$marker=ord($raw[$at+1]);if($marker===218){$clean.=substr($raw,$at);break;}$len=unpack('n',substr($raw,$at+2,2))[1];if($marker!==254)$clean.=substr($raw,$at,$len+2);$at+=$len+2;}
  file_put_contents($root.'/synthetic.jpg',$clean);
  $png=file_get_contents($root.'/synthetic.png');$text="Comment\0SYNTHETIC_METADATA_REMOVE";$chunk='tEXt'.$text;$chunk=pack('N',strlen($text)).$chunk.hash('crc32b',$chunk,true);
  file_put_contents($root.'/metadata.png',substr($png,0,-12).$chunk.substr($png,-12));
  file_put_contents($root.'/polyglot.png',$png.'<script>synthetic()</script>');
  file_put_contents($root.'/malformed.png',substr($png,0,50));file_put_contents($root.'/oversized.png',str_repeat('x',65537));
  file_put_contents($root.'/unsupported.pdf',"%PDF-1.4\nSYNTHETIC NON-ID /JavaScript\n%%EOF");
  $im=imagecreatetruecolor(513,1);imagepng($im,$root.'/wide.png');
  $im=imagecreatetruecolor(300,300);imagepng($im,$root.'/pixels.png');
  $r=['synthetic_images'=>true];
 }elseif($action==='production'){
  putenv('MXMED_STAFF_PRIVATE_MEDIA_ENABLED='.($b['enabled']??'0'));Composition::fromIdentity($identity);throw new RuntimeException('unexpected_composition');
 }elseif($action==='storage'){
  $checks=[];$deny=function($name,$fn)use(&$checks){try{$fn();$checks[$name]=false;}catch(Throwable){$checks[$name]=true;}};
  foreach(['../escape','private/staff-admission/../../escape','private/physician-evidence/x.enc','private/staff-admission/sma_'.str_repeat('a',32).'.enc/extra'] as $i=>$key)$deny('traversal_or_namespace_'.$i,fn()=>$storage->exists($key));
  $deny('application_root',fn()=>new Storage(dirname(__DIR__,3),1024));
  mkdir($owned.'/public-root',0700);mkdir($owned.'/public-root/child',0700);$deny('document_root',fn()=>new Storage(realpath($owned.'/public-root/child'),1024,[realpath($owned.'/public-root')]));
  symlink($root,$owned.'/media-alias');$deny('root_symlink',fn()=>new Storage($owned.'/media-alias',1024));unlink($owned.'/media-alias');
  if(!is_dir($root.'/private/staff-admission'))mkdir($root.'/private/staff-admission',0700,true);
  $key=Storage::key('sma_'.str_repeat('a',32));symlink($root.'/synthetic.png',$root.'/'.$key);$deny('target_symlink',fn()=>$storage->openReadStream($key));unlink($root.'/'.$key);
  $deny('disposal_closed',fn()=>$storage->delete($key));
  require_once __DIR__.'/../../media/storage/LocalPersistentPrivateMediaStorage.php';
  $legacy=new \Media\Storage\LocalPersistentPrivateMediaStorage($root);$deny('legacy_allowlist_unchanged',fn()=>$legacy->exists($key));
  $r=$checks;
 }elseif($action==='crypto'){
  $q=$p->prepare('SELECT * FROM auth_staff_media_assets WHERE asset_id=?');$q->execute([$b['asset_id']]);$a=$q->fetch(PDO::FETCH_ASSOC);
  $opened=$storage->openReadStream($a['storage_key']);$bytes=stream_get_contents($opened['stream']);fclose($opened['stream']);
  $plain=$cipher->open($bytes,$a,$a['key_id']);$checks=['valid_decrypt'=>strlen($plain)===(int)$a['byte_length']];
  foreach(['case_id','account_id','asset_id','purpose','asset_version','case_version','custody_reference','retention_reference'] as $field){$other=$a;$other[$field]=in_array($field,['asset_version','case_version'],true)?(int)$a[$field]+1:($field==='purpose'?Policy::PORTRAIT:'wrong');
   try{$cipher->open($bytes,$other,$a['key_id']);$checks['wrong_'.$field]=false;}catch(Throwable){$checks['wrong_'.$field]=true;}}
  try{$cipher->open($bytes,$a,'unknown-key');$checks['unknown_key']=false;}catch(Throwable){$checks['unknown_key']=true;}
  $bytes[strlen($bytes)-1]=chr(ord($bytes[strlen($bytes)-1])^1);
  try{$cipher->open($bytes,$a,$a['key_id']);$checks['tamper']=false;}catch(Throwable){$checks['tamper']=true;}
  $r=$checks;
 }elseif($action==='write'){
  $file=$b['file']??'';if(!in_array($file,['synthetic.png','synthetic.jpg','metadata.png','polyglot.png','malformed.png','oversized.png','unsupported.pdf','wide.png','pixels.png'],true))throw new RuntimeException('synthetic_fixture_required');
  $r=$service->write($cookies,$b['input'],file_get_contents($root.'/'.$file));
 }elseif($action==='read'){
  $r=$service->read($cookies,$b['input']);$bytes=$r['bytes'];unset($r['bytes']);
  $r+=['byte_length'=>strlen($bytes),'sha256'=>hash('sha256',$bytes),'metadata_removed'=>!str_contains($bytes,'SYNTHETIC_METADATA_REMOVE')];
 }elseif($action==='result')$r=$service->result($cookies,$b['input']);
 else throw new RuntimeException('fixture_action_invalid');
 echo json_encode(['ok'=>true,'data'=>$r],JSON_THROW_ON_ERROR);
}catch(Throwable $e){
 $message=$e->getMessage();$allowed=preg_match('/^(staff_media_[a-z_]+|admission_[a-z_]+|logo_[a-z_]+)$/D',$message);
 if(!$allowed)file_put_contents($owned.'/media-private-error.log',get_class($e).': '.$message);
 echo json_encode(['ok'=>false,'error'=>$allowed?$message:'internal_failure'],JSON_THROW_ON_ERROR);
}
