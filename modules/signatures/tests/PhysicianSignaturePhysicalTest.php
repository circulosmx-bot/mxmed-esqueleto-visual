<?php
declare(strict_types=1);
require_once __DIR__.'/../PhysicianSignatureService.php';
require_once __DIR__.'/../../media/storage/LocalPersistentPrivateMediaStorage.php';
use Signatures\{PhysicianSignatureService,SignatureImage};
use Media\Storage\LocalPersistentPrivateMediaStorage;
use Platform\Contracts\TrustedAuditContext;
function sigCheck(bool $ok,string $label):void {if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
function sigDeny(callable $f,string $label):void {try{$f();}catch(Throwable){echo "PASS $label\n";return;}throw new RuntimeException('unexpected acceptance '.$label);}
function sigSql(PDO $pdo,string $source): void {
    $delimiter=';';$buffer='';
    foreach(explode("\n",$source) as $line) {
        if(preg_match('/^DELIMITER\s+(\S+)/',trim($line),$m)) {$delimiter=$m[1];continue;}
        if(str_starts_with(trim($line),'--')) continue;
        $buffer.=$line."\n";
        if(str_ends_with(rtrim($buffer),$delimiter)) {$sql=substr(rtrim($buffer),0,-strlen($delimiter));if(trim($sql)!=='')$pdo->exec($sql);$buffer='';}
    }
    if(trim($buffer)!=='')$pdo->exec($buffer);
}
$port=getenv('MXMED_SIG03A_DISPOSABLE_PORT');
if(!$port||!ctype_digit($port)||getenv('MXMED_SIG03A_DISPOSABLE')!=='1')throw new RuntimeException('explicit_disposable_server_required');
$pdo=new PDO("mysql:host=127.0.0.1;port=$port;charset=utf8mb4",'root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
if($pdo->query("SELECT SCHEMA_NAME FROM information_schema.schemata WHERE SCHEMA_NAME='mxmed'")->fetchColumn())throw new RuntimeException('existing_database_refused');
$root=dirname(__DIR__,3);$private='/tmp/mxmed-signature-test-'.bin2hex(random_bytes(8));
$pdo->exec('CREATE DATABASE mxmed CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
try{
 $pdo->exec('USE mxmed');sigSql($pdo,file_get_contents($root.'/modules/profiles/db/profiles_doctors_schema.sql'));
 $pdo->exec("INSERT INTO profiles_doctors (doctor_id) VALUES ('synthetic-a'),('synthetic-b')");
 $migration=file_get_contents(__DIR__.'/../db/2026_09_14_physician_signatures.sql');sigSql($pdo,$migration);sigSql($pdo,$migration);
 sigSql($pdo,file_get_contents($root.'/modules/platform/db/migrations/2026_07_20_01_create_platform_audit_events.sql'));
 $pdo->exec('CREATE TABLE platform_audit_stream_heads(stream_key VARCHAR(191) PRIMARY KEY,last_sequence_number BIGINT NOT NULL,last_event_hash CHAR(64) NOT NULL,hash_version VARCHAR(32) NULL,updated_at DATETIME(6) NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
 foreach(['lock','cas'] as $routine)sigSql($pdo,str_replace('{{RESTRICTED_DEFINER}}','CURRENT_USER',file_get_contents($root.'/modules/platform/db/routines/audit_mp01c_r2_'.$routine.'.sql')));
 $storage=new LocalPersistentPrivateMediaStorage($private);$service=new PhysicianSignatureService($pdo,$storage);
 $context=TrustedAuditContext::fromServer('synthetic-owner','account','doctor','signature:synthetic-a','req','corr','session',null,null,'PROFILE','synthetic');
 $image=imagecreatetruecolor(600,180);imagealphablending($image,false);imagesavealpha($image,true);imagefill($image,0,0,imagecolorallocatealpha($image,255,255,255,127));$ink=imagecolorallocatealpha($image,10,30,40,0);imagesetthickness($image,3);imageline($image,30,80,450,95,$ink);imageline($image,450,95,490,40,$ink);ob_start();imagepng($image);$raw=ob_get_clean();unset($image);$data='data:image/png;base64,'.base64_encode($raw);
 $chunk='tEXt'.'Comment'.chr(0).'synthetic-signature-metadata';$withMetadata=substr($raw,0,-12).pack('N',strlen($chunk)-4).$chunk.pack('N',crc32($chunk)).substr($raw,-12);sigCheck(!str_contains(SignatureImage::normalize('data:image/png;base64,'.base64_encode($withMetadata))['bytes'],'synthetic-signature-metadata'),'metadata stripped');
 sigCheck($service->current('synthetic-a')===null,'GET none');$service->save('synthetic-a',$data,$context);
 $first=$service->current('synthetic-a');sigCheck($first!==null,'first signature created');sigCheck((new PhysicianSignatureService($pdo,new LocalPersistentPrivateMediaStorage($private)))->current('synthetic-a')===$first,'another device canonical read');
 $normalized=SignatureImage::normalize($data);sigCheck($normalized['width']<600&&$normalized['height']<180,'whitespace cropped');sigCheck(strlen($normalized['bytes'])<=153600,'output bounded');$decoded=imagecreatefromstring($normalized['bytes']);sigCheck(imagecolorsforindex($decoded,imagecolorat($decoded,0,0))['alpha']===127,'transparency retained');unset($decoded);
 sigDeny(fn()=>$service->save('synthetic-a','data:image/png;base64,broken',$context),'malformed payload denied');sigCheck($service->current('synthetic-a')===$first,'failed decode preserves previous');
 sigDeny(fn()=>$service->save('synthetic-a','data:image/png;base64,'.str_repeat('A',3000000),$context),'oversized payload denied');
 sigDeny(fn()=>$service->delete('synthetic-b',$context),'cross physician delete denied');
 sigDeny(fn()=>$service->save('synthetic-b',$data,$context),'cross physician mutation denied');sigCheck($service->current('synthetic-b')===null,'other physician untouched');
 $pdo->exec('RENAME TABLE platform_audit_stream_heads TO missing_audit_heads');sigDeny(fn()=>$service->save('synthetic-a',$data,$context),'audit failure replacement denied');$pdo->exec('RENAME TABLE missing_audit_heads TO platform_audit_stream_heads');sigCheck($service->current('synthetic-a')===$first,'failed audit replacement preserves previous');
 $old=$pdo->query("SELECT storage_key FROM physician_signatures WHERE doctor_id='synthetic-a'")->fetchColumn();$service->save('synthetic-a',$data,$context);sigCheck(!$storage->exists($old),'old asset cleaned only after successful replacement');
 $service->delete('synthetic-a',$context);sigCheck($service->current('synthetic-a')===null,'delete clears authority');
 $events=$pdo->query('SELECT action,metadata_json FROM platform_audit_events ORDER BY sequence_number')->fetchAll(PDO::FETCH_ASSOC);sigCheck(array_column($events,'action')===['PHYSICIAN_SIGNATURE_CREATED','PHYSICIAN_SIGNATURE_REPLACED','PHYSICIAN_SIGNATURE_DELETED'],'canonical lifecycle audit');sigCheck(!str_contains(json_encode($events),'data:image')&&!str_contains(json_encode($events),base64_encode($raw)),'no signature content logged');
 require __DIR__.'/SignatureHttpChecks.php';
 echo "SIG03A_PHYSICAL_SERVICE=PASS\n";
}finally{$pdo->exec('DROP DATABASE mxmed');if(is_dir($private)){foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($private,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST)as $f){$f->isDir()?rmdir($f->getPathname()):unlink($f->getPathname());}rmdir($private);}}
