<?php
declare(strict_types=1);

use Agenda\Repositories\ConsultoriosRepository;

require_once __DIR__ . '/../repositories/ConsultoriosRepository.php';

$host=getenv('MXMED_DB_HOST')?:'127.0.0.1';$port=(int)(getenv('MXMED_DB_PORT')?:3306);
$user=getenv('MXMED_DB_USER')?:'root';$pass=getenv('MXMED_DB_PASS')?:'';$root=dirname(__DIR__,3);
function consVisPdo(string $database=''):PDO{global $host,$port,$user,$pass;$dsn="mysql:host={$host};port={$port};charset=utf8mb4".($database===''?'':";dbname={$database}");return new PDO($dsn,$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);}
function consVisAssert(bool $ok,string $label):void{if(!$ok)throw new RuntimeException('FAIL '.$label);echo "CONS_VIS01_{$label}=PASS\n";}

if(($argv[1]??'')==='--worker'){
    $repo=new ConsultoriosRepository(consVisPdo((string)$argv[2]));
    try{$repo->reorderDocumentPresentation('doctor-a',['office-2','office-1','office-3'],(string)$argv[3]);echo "WORKER_OK\n";}catch(RuntimeException $e){echo 'WORKER_'.$e->getMessage()."\n";}
    exit(0);
}

$database='cons_vis01_'.bin2hex(random_bytes(6));$admin=consVisPdo();
try{
    $admin->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo=consVisPdo($database);$pdo->exec((string)file_get_contents($root.'/modules/agenda/db/consultorios_schema.sql'));
    $pdo->exec("CREATE TABLE agenda_appointments (appointment_id VARCHAR(64) PRIMARY KEY,doctor_id VARCHAR(64),consultorio_id VARCHAR(64)) ENGINE=InnoDB");
    $pdo->exec("INSERT INTO consultorios (doctor_id,consultorio_id,titulo,document_publication_enabled) VALUES
      ('doctor-a','office-1','Uno',1),('doctor-a','office-2','Dos',0),('doctor-a','office-3','Tres',1),
      ('doctor-b','office-1','B Uno',1),('doctor-b','office-2','B Dos',0)");
    $pdo->exec("INSERT INTO agenda_appointments VALUES ('apt-1','doctor-a','office-1'),('apt-2','doctor-a','office-3')");
    $repo=new ConsultoriosRepository($pdo);$before=$repo->documentPresentationState('doctor-a');
    consVisAssert(!$before['configured']&&$before['order']===['office-1','office-2','office-3'],'UNCONFIGURED_SAFE');
    $publicationBefore=$pdo->query("SELECT consultorio_id,document_publication_enabled FROM consultorios WHERE doctor_id='doctor-a' ORDER BY consultorio_id")->fetchAll(PDO::FETCH_KEY_PAIR);
    $appointmentsBefore=$pdo->query("SELECT appointment_id,consultorio_id FROM agenda_appointments ORDER BY appointment_id")->fetchAll(PDO::FETCH_KEY_PAIR);
    $after=$repo->reorderDocumentPresentation('doctor-a',['office-3','office-1','office-2'],$before['fingerprint']);
    consVisAssert($after['configured']&&$after['order']===['office-3','office-1','office-2'],'ORDER_PERSISTENCE');
    $rows=$pdo->query("SELECT consultorio_id,document_is_primary,document_display_order FROM consultorios WHERE doctor_id='doctor-a' ORDER BY document_display_order")->fetchAll();
    consVisAssert(array_column($rows,'consultorio_id')===['office-3','office-1','office-2']&&(int)$rows[0]['document_is_primary']===1&&array_sum(array_map(static fn($r)=>(int)$r['document_is_primary'],$rows))===1,'FIRST_PRIMARY_ATOMIC');
    consVisAssert($publicationBefore===$pdo->query("SELECT consultorio_id,document_publication_enabled FROM consultorios WHERE doctor_id='doctor-a' ORDER BY consultorio_id")->fetchAll(PDO::FETCH_KEY_PAIR),'PUBLICATION_UNCHANGED');
    consVisAssert($appointmentsBefore===$pdo->query("SELECT appointment_id,consultorio_id FROM agenda_appointments ORDER BY appointment_id")->fetchAll(PDO::FETCH_KEY_PAIR),'APPOINTMENTS_UNCHANGED');
    $same=$repo->reorderDocumentPresentation('doctor-a',$after['order'],$after['fingerprint']);
    consVisAssert($same===$after,'IDEMPOTENT_REORDER');
    try{$repo->reorderDocumentPresentation('doctor-a',['office-1','office-2','office-3'],$before['fingerprint']);throw new RuntimeException('FAIL stale');}catch(RuntimeException $e){consVisAssert($e->getMessage()==='stale_consultorio_order','STALE_REJECTED');}
    try{$repo->reorderDocumentPresentation('doctor-a',['office-1','office-2','office-bad'],$after['fingerprint']);throw new RuntimeException('FAIL scope');}catch(RuntimeException $e){consVisAssert($e->getMessage()==='consultorio_order_scope_mismatch','CROSS_PHYSICIAN_REJECTED');}
    consVisAssert(!$repo->documentPresentationState('doctor-b')['configured'],'OTHER_PHYSICIAN_UNCHANGED');

    $pdo->beginTransaction();$pdo->query("SELECT consultorio_id FROM consultorios WHERE doctor_id='doctor-a' ORDER BY consultorio_id FOR UPDATE")->fetchAll();
    $process=proc_open([PHP_BINARY,__FILE__,'--worker',$database,$after['fingerprint']],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if(!is_resource($process))throw new RuntimeException('FAIL worker');usleep(250000);
    consVisAssert((proc_get_status($process)['running']??false)===true,'CONCURRENT_WAIT');$pdo->commit();
    $stdout=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);
    consVisAssert($exit===0&&str_contains($stdout,'WORKER_OK')&&$stderr==='','CONCURRENT_SERIALIZED');
    $final=$repo->documentPresentationState('doctor-a');
    consVisAssert($final['configured']&&$final['order']===['office-2','office-1','office-3'],'ORDER_RELOAD');
}finally{$admin->exec("DROP DATABASE IF EXISTS `{$database}`");}
echo "CONS_VIS01_REORDER_INTEGRATION=PASS\n";
