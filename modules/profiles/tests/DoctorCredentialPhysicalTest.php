<?php
declare(strict_types=1);
// Run only against an explicitly supplied, disposable LOCAL database server.
// The canonical adapter calls routines in `mxmed`, so an existing database is refused.
require_once __DIR__.'/../services/VerifiedDoctorCredentialService.php';
use Profiles\Repositories\DoctorCredentialRepository;
use Profiles\Services\VerifiedDoctorCredentialService;
use Platform\Contracts\TrustedAuditContext;
function crdCheck(bool $ok,string $label): void { if(!$ok) throw new RuntimeException($label); echo "PASS $label\n"; }
function crdDenied(callable $f,string $label): void { try {$f();} catch(Throwable $e) {echo "PASS $label: ".get_class($e)."\n";return;}throw new RuntimeException('unexpected acceptance: '.$label); }
function crdSql(PDO $pdo,string $source): void {
    $delimiter=';';$buffer='';
    foreach(explode("\n",$source) as $line) {
        if(preg_match('/^DELIMITER\s+(\S+)/',trim($line),$m)) {$delimiter=$m[1];continue;}
        if(str_starts_with(trim($line),'--')) continue;
        $buffer.=$line."\n";
        if(str_ends_with(rtrim($buffer),$delimiter)) {$sql=substr(rtrim($buffer),0,-strlen($delimiter));if(trim($sql)!=='')$pdo->exec($sql);$buffer='';}
    }
    if(trim($buffer)!=='')$pdo->exec($buffer);
}
$port=getenv('MXMED_CRD02_DISPOSABLE_PORT');
if(!$port || !ctype_digit($port) || getenv('MXMED_CRD02_DISPOSABLE')!=='1') throw new RuntimeException('explicit_disposable_local_server_required');
$pdo=new PDO("mysql:host=127.0.0.1;port=$port;charset=utf8mb4",'root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
if($pdo->query("SELECT SCHEMA_NAME FROM information_schema.schemata WHERE SCHEMA_NAME='mxmed'")->fetchColumn())throw new RuntimeException('existing_database_refused');
$root=dirname(__DIR__,3);
$httpServer=null; $httpPipes=[]; $httpRoot=sys_get_temp_dir().'/mxmed_crd02_http_'.getmypid();
$pdo->exec('CREATE DATABASE mxmed CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
try {
    $pdo->exec('USE mxmed');
    $pdo->exec(file_get_contents($root.'/modules/profiles/db/profiles_doctors_schema.sql'));
    $pdo->exec("CREATE TABLE internal_staff(account_id VARCHAR(64) PRIMARY KEY,governance_class VARCHAR(32),status VARCHAR(16)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("INSERT INTO profiles_doctors(doctor_id,professional_license,specialty_license,specialty_primary) VALUES ('qa','0099','0088','Legacy'),('other',NULL,NULL,NULL)");
    $legacy=$pdo->query('SELECT * FROM profiles_doctors ORDER BY doctor_id')->fetchAll(PDO::FETCH_ASSOC);
    $migration=file_get_contents(__DIR__.'/../db/2026_09_12_create_profiles_doctor_credentials.sql');
    crdSql($pdo,$migration);crdSql($pdo,$migration);
    crdCheck((int)$pdo->query('SELECT COUNT(*) FROM profiles_doctor_credentials')->fetchColumn()===0,'idempotent migration, zero backfill');
    $after=$pdo->query('SELECT * FROM profiles_doctors ORDER BY doctor_id')->fetchAll(PDO::FETCH_ASSOC);
    foreach($after as &$row)unset($row['primary_specialty_credential_id']);unset($row);
    crdCheck($legacy===$after,'legacy values preserved');
    $ddl=$pdo->query('SHOW CREATE TABLE profiles_doctor_credentials')->fetch(PDO::FETCH_NUM)[1];
    foreach(['varchar(64)','uq_active_verified_professional','uq_credential_license','fk_credential_approver','ck_credential_verified'] as $marker)crdCheck(str_contains($ddl,$marker),'schema '.$marker);
    if(getenv('MXMED_CRD02_SCHEMA_ONLY')==='1'){echo "PHYSICAL_LOCAL_MIGRATION_TEST=PASS\n";return;}
    crdSql($pdo,file_get_contents($root.'/modules/platform/db/migrations/2026_07_20_01_create_platform_audit_events.sql'));
    $pdo->exec('CREATE TABLE platform_audit_stream_heads(stream_key VARCHAR(191) PRIMARY KEY,last_sequence_number BIGINT NOT NULL,last_event_hash CHAR(64) NOT NULL,hash_version VARCHAR(32) NULL,updated_at DATETIME(6) NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    foreach(['lock','cas'] as $routine)crdSql($pdo,str_replace('{{RESTRICTED_DEFINER}}','CURRENT_USER',file_get_contents($root.'/modules/platform/db/routines/audit_mp01c_r2_'.$routine.'.sql')));
    $pdo->exec("INSERT INTO internal_staff VALUES ('qa-advisor','ADVISOR','ACTIVE'),('suspended','ADVISOR','SUSPENDED')");
    $context=TrustedAuditContext::fromServer('qa-advisor','account','ADVISOR','profile','qa-request','qa-correlation','qa-session',null,null,'PROFILE','synthetic-qa');
    $repo=new DoctorCredentialRepository($pdo);$service=new VerifiedDoctorCredentialService($repo,true);
    $source=['source_type'=>'synthetic_test','source_reference'=>'CRD02 disposable synthetic fixture'];
    $create=function(string $license,string $type='SPECIALTY',string $area='Pediatría',string $institution='UNAM',string $doctor='qa')use($service,$context,$source):array {
        return $service->provisionFromTrustedAuthority(['doctor_id'=>$doctor,'credential_type'=>$type,'license_number'=>$license,'professional_area_label'=>$area,'institution_name'=>$institution]+$source,$context);
    };
    $count=fn():int=>(int)$pdo->query('SELECT COUNT(*) FROM platform_audit_events')->fetchColumn();
    $professional=$create('1111111','PROFESSIONAL','Médico Cirujano','Universidad Autónoma de Aguascalientes');
    $service->verify((string)$professional['credential_id'],$source,$context);
    crdCheck($service->resolveVerifiedProfessional('qa')!==null,'one active verified professional');
    $second=$create('0111111','PROFESSIONAL');$before=$count();
    crdDenied(fn()=>$service->verify((string)$second['credential_id'],$source,$context),'second professional rejected');
    crdCheck($count()===$before && $repo->findById((string)$second['credential_id'])['verification_status']==='PENDING_REVIEW','state failure emits no false success');
    $specialties=[];
    foreach([['2222222','Pediatría','UNAM'],['3333333','Nefrología','UNAM'],['4444444','Medicina Interna','UdeG'],['5555555','Cuarta especialidad','Institución QA']] as [$license,$area,$institution]) {
        $r=$create($license,'SPECIALTY',$area,$institution);$r=$service->verify((string)$r['credential_id'],$source,$context);$specialties[]=$r;
        crdCheck($r['license_number']===$license && $r['institution_name']===$institution && $r['source_reference']===$source['source_reference'] && $r['verified_by_account_id']==='qa-advisor','independent credential authority '.$area);
    }
    crdCheck(count($service->resolveVerifiedSpecialties('qa'))===4,'more than three specialties allowed');
    $primary=(string)$specialties[1]['credential_id'];$service->assertEligiblePrimarySpecialty('qa',$primary);
    $pdo->exec("UPDATE profiles_doctors SET primary_specialty_credential_id=$primary WHERE doctor_id='qa'");
    crdDenied(fn()=>$pdo->exec("UPDATE profiles_doctors SET primary_specialty_credential_id=$primary WHERE doctor_id='other'"),'ownership FK');
    crdCheck($service->physicianReadModel('qa')['verified_credentials']['specialties'][1]['is_primary'],'Nefrología primary projection');
    $pdo->exec("UPDATE profiles_doctors SET primary_specialty_credential_id=NULL WHERE doctor_id='qa'");
    $pending=$create('000123');crdCheck($pending['license_number']==='000123','leading zeroes');
    crdDenied(fn()=>$create('000123'),'duplicate doctor license rejected');
    $rejected=$create('6666666');$service->reject((string)$rejected['credential_id'],$context);
    $inactive=$specialties[2];$service->inactivate((string)$inactive['credential_id'],$context);
    $revoked=$specialties[3];$service->revoke((string)$revoked['credential_id'],$context);
    $foreign=$create('7777777','SPECIALTY','Otra','QA','other');$service->verify((string)$foreign['credential_id'],$source,$context);
    foreach([$foreign,$professional,$pending,$rejected,$inactive,$revoked,['credential_id'=>'999999']] as $invalid)crdDenied(fn()=>$service->assertEligiblePrimarySpecialty('qa',(string)$invalid['credential_id']),'ineligible primary '.$invalid['credential_id']);
    $service->revoke((string)$inactive['credential_id'],$context);
    $before=$count();
    $pdo->exec("CREATE TRIGGER qa_audit_failure BEFORE INSERT ON platform_audit_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic audit failure'");
    crdDenied(fn()=>$service->verify((string)$pending['credential_id'],$source,$context),'physical audit failure');
    crdCheck($repo->findById((string)$pending['credential_id'])['verification_status']==='PENDING_REVIEW' && $count()===$before,'audit failure rolls back credential mutation');
    $beforeRows=count($repo->listByDoctorId('qa'));
    crdDenied(fn()=>$create('8888888'),'provision audit failure');
    crdCheck(count($repo->listByDoctorId('qa'))===$beforeRows,'provision rollback');
    $pdo->exec('DROP TRIGGER qa_audit_failure');
    $incomplete=$service->provisionFromTrustedAuthority(['doctor_id'=>'qa','credential_type'=>'SPECIALTY','license_number'=>'9999999']+$source,$context);
    crdDenied(fn()=>$service->verify((string)$incomplete['credential_id'],$source,$context),'verified completeness');
    foreach(['PROVISIONED','VERIFIED','REJECTED','INACTIVATED','REVOKED'] as $event) {
        $s=$pdo->prepare('SELECT COUNT(*) FROM platform_audit_events WHERE action=?');$s->execute(['PHYSICIAN_CREDENTIAL_'.$event]);crdCheck((int)$s->fetchColumn()>0,'audit '.$event);
    }
    foreach($pdo->query('SELECT metadata_json FROM platform_audit_events')->fetchAll(PDO::FETCH_COLUMN) as $json)foreach(['license_number','institution_name','professional_area_label','source_reference'] as $key)crdCheck(!str_contains($json,$key),'audit excludes '.$key);
    $projection=json_encode($service->physicianReadModel('qa'));
    foreach(['verified_by_account_id','source_reference','synthetic_test'] as $private)crdCheck(!str_contains($projection,$private),'private projection excludes '.$private);
    $pdo->exec("INSERT INTO profiles_doctors(doctor_id) VALUES ('empty')");
    crdCheck($service->physicianReadModel('empty')['verified_credentials']===['professional'=>null,'specialties'=>[]],'empty legacy projection');

    $noSession=TrustedAuditContext::fromServer('qa-advisor','account','ADVISOR','profile','r','c',null,null,null,'PROFILE','qa');
    crdDenied(fn()=>$service->reject((string)$pending['credential_id'],$noSession),'session required');
    $physician=TrustedAuditContext::fromServer('qa-doctor','account','doctor','profile','r','c','s',null,null,'PROFILE','qa');
    crdDenied(fn()=>$service->reject((string)$pending['credential_id'],$physician),'physician cannot mutate');
    $suspended=TrustedAuditContext::fromServer('suspended','account','ADVISOR','profile','r','c','s',null,null,'PROFILE','qa');
    crdDenied(fn()=>$service->reject((string)$pending['credential_id'],$suspended),'suspended authority rejected');
    $productionService=new VerifiedDoctorCredentialService($repo);
    crdDenied(fn()=>$productionService->provisionFromTrustedAuthority(['doctor_id'=>'qa','credential_type'=>'SPECIALTY','license_number'=>'test']+$source,$context),'synthetic provenance disabled by default');
    crdDenied(fn()=>$service->provisionFromTrustedAuthority(['doctor_id'=>'qa','credential_type'=>'SPECIALTY','license_number'=>123]+$source,$context),'numeric license rejected');
    crdDenied(fn()=>$service->verify((string)$professional['credential_id'],$source,$context),'repeat verify rejected');
    crdDenied(fn()=>$service->revoke((string)$revoked['credential_id'],$context),'repeat revoke rejected');
    $pdo->exec(file_get_contents(__DIR__.'/../db/2026_09_11_create_profiles_verified_identities.sql'));
    mkdir($httpRoot.'/api/profiles',0700,true);mkdir($httpRoot.'/api/_lib',0700,true);mkdir($httpRoot.'/sessions',0700);
    copy($root.'/api/profiles/index.php',$httpRoot.'/api/profiles/index.php');
    copy($root.'/api/_lib/db.php',$httpRoot.'/api/_lib/db.php');
    symlink($root.'/modules',$httpRoot.'/modules');
    // Server-owned PHP session fixture, never a request header or login endpoint.
    file_put_contents($httpRoot.'/sessions/sess_crd02qa','user_id|s:9:"qa-doctor";doctor_id|s:2:"qa";role|s:6:"doctor";');
    $httpPort=20000+(getmypid()%1000);
    $httpServer=proc_open([PHP_BINARY,'-d','session.save_path='.$httpRoot.'/sessions','-S','127.0.0.1:'.$httpPort,'-t',$httpRoot],
        [0=>['pipe','r'],1=>['file',$httpRoot.'/server.log','a'],2=>['file',$httpRoot.'/server.log','a']],$httpPipes,$httpRoot,
        array_merge($_ENV,['MXMED_DB_HOST'=>'127.0.0.1','MXMED_DB_PORT'=>$port,'MXMED_DB_NAME'=>'mxmed','MXMED_DB_USER'=>'root','MXMED_DB_PASS'=>'','MXMED_PROFILES_PRIVATE_AUTH_REQUIRED'=>'0']));
    crdCheck(is_resource($httpServer),'private HTTP runtime started');
    for($i=0;$i<50;$i++){ $socket=@fsockopen('127.0.0.1',$httpPort,$errno,$error,0.1);if($socket){fclose($socket);break;}usleep(100000); }
    $request=function(string $doctor,array $headers=[],?array $patch=null)use($httpPort):array {
        $options=['method'=>$patch===null?'GET':'PATCH','ignore_errors'=>true,'timeout'=>10,'header'=>implode("\r\n",array_merge(['Content-Type: application/json'],$headers))];
        if($patch!==null)$options['content']=json_encode($patch);
        $body=file_get_contents('http://127.0.0.1:'.$httpPort.'/api/profiles/index.php/private/doctor/'.$doctor,false,stream_context_create(['http'=>$options]));
        return json_decode($body,true,512,JSON_THROW_ON_ERROR);
    };
    $own=$request('qa',['Cookie: PHPSESSID=crd02qa']);
    crdCheck($own['ok'] && count($own['data']['verified_credentials']['specialties'])===2,'HTTP physician reads own credentials');
    foreach([['qa',[]],['qa',['X-User-Id: forged','X-Doctor-Id: qa']],['other',['Cookie: PHPSESSID=crd02qa']]] as [$doctor,$headers]) {
        $response=$request($doctor,$headers);
        crdCheck(($response['data']['verified_credentials']??null)===['professional'=>null,'specialties'=>[]],'HTTP no credential disclosure without matching server scope');
    }
    foreach(['verified_credentials','primary_specialty_credential_id','license_number','institution_name','verification_status','lifecycle_status','source_reference'] as $field) {
        $response=$request('qa',['Cookie: PHPSESSID=crd02qa'],[$field=>'forged']);
        crdCheck(!$response['ok'] && $response['error']==='invalid_payload','HTTP credential PATCH rejected '.$field);
    }
    crdCheck($repo->findById((string)$professional['credential_id'])['license_number']==='1111111','HTTP writes preserve credential');
    echo "PHYSICAL_LOCAL_DB_TEST=PASS\n";
} finally {
    if(is_resource($httpServer)){proc_terminate($httpServer);foreach($httpPipes as $pipe)if(is_resource($pipe))fclose($pipe);proc_close($httpServer);}
    if(is_link($httpRoot.'/modules'))unlink($httpRoot.'/modules');
    if(is_dir($httpRoot)){
        $entries=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($httpRoot,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
        foreach($entries as $entry){if($entry->isDir())rmdir($entry->getPathname());else unlink($entry->getPathname());}rmdir($httpRoot);
    }
    $pdo->exec('DROP DATABASE mxmed');
}
