<?php
declare(strict_types=1);
// Explicit disposable local MySQL only; never select the Director database.
$port = getenv('MXMED_CRD034_DISPOSABLE_PORT');
if (getenv('MXMED_CRD034_DISPOSABLE') !== '1' || !$port || !ctype_digit($port) || (int)$port === 3306) throw new RuntimeException('explicit_disposable_local_server_required');
$pdo = new PDO("mysql:host=127.0.0.1;port=$port;charset=utf8mb4", 'root', '', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$database = 'mxmed_crd034_' . getmypid();
$pdo->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$root = dirname(__DIR__, 3);
$temp = sys_get_temp_dir().'/mxmed-crd034-http-'.getmypid();
mkdir($temp); mkdir($temp.'/sessions');
$server = null;
function genderCheck(bool $value, string $label): void {if(!$value)throw new RuntimeException($label);echo "PASS $label\n";}
try {
    $pdo->exec("USE `$database`");
    $pdo->exec(file_get_contents($root.'/modules/profiles/db/profiles_doctors_schema.sql'));
    $pdo->exec('CREATE TABLE internal_staff(account_id VARCHAR(64) PRIMARY KEY, governance_class VARCHAR(32),status VARCHAR(16)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $pdo->exec(file_get_contents($root.'/modules/profiles/db/2026_09_11_create_profiles_verified_identities.sql'));
    $pdo->exec(file_get_contents($root.'/modules/profiles/db/2026_09_12_create_profiles_doctor_credentials.sql'));
    $pdo->exec("INSERT INTO profiles_doctors(doctor_id,display_name,gender,gender_label,prefix,professional_designation,bio_short) VALUES('1','Perfil sintético QA','femenino','Femenino','Dra.','Médica','Actividad profesional sintética.')");
    file_put_contents($temp.'/router.php', '<?php session_start();$_SESSION=["doctor_id"=>"1","user_id"=>"crd034-synthetic","role"=>"doctor"];session_write_close();return false;');
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    $httpPort = substr(stream_socket_get_name($socket, false), strrpos(stream_socket_get_name($socket, false), ':')+1);fclose($socket);
    $env = array_merge(getenv(), ['MXMED_DB_HOST'=>'127.0.0.1','MXMED_DB_PORT'=>$port,'MXMED_DB_NAME'=>$database,'MXMED_DB_USER'=>'root','MXMED_DB_PASS'=>'','MXMED_PROFILES_PRIVATE_AUTH_REQUIRED'=>'1']);
    $server = proc_open([PHP_BINARY,'-d','session.save_path='.$temp.'/sessions','-S','127.0.0.1:'.$httpPort,'-t',$root,$temp.'/router.php'], [0=>['pipe','r'],1=>['file',$temp.'/server.log','a'],2=>['file',$temp.'/server.log','a']], $pipes, $root, $env);
    for($i=0;$i<100;$i++){if($s=@fsockopen('127.0.0.1',(int)$httpPort,$errno,$error,0.1)){fclose($s);break;}usleep(50000);}
    $request = function(array $payload) use($httpPort): array {
        $body = file_get_contents('http://127.0.0.1:'.$httpPort.'/api/profiles/private/doctor/1', false, stream_context_create(['http'=>['method'=>'PATCH','ignore_errors'=>true,'header'=>"Content-Type: application/json\r\n",'content'=>json_encode($payload),'timeout'=>10]]));
        preg_match('/\s(\d{3})\s/', $http_response_header[0]??'', $status);
        genderCheck(($status[1]??'')==='200','existing blocked-field HTTP 200 semantics');
        return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    };
    $row = fn()=> $pdo->query("SELECT * FROM profiles_doctors WHERE doctor_id='1'")->fetch();
    foreach([['gender'=>'masculino'],['gender_label'=>'Masculino'],['gender'=>null,'gender_label'=>null],['gender'=>['invalid'],'gender_label'=>['invalid']]] as $payload){
        $before=$row();$result=$request($payload);
        genderCheck($result['ok'] && $result['meta']['no_editable_fields_applied']===true,'gender-only PATCH ignored by blocked policy');
        genderCheck($result['meta']['blocked_fields_ignored']===array_keys($payload),'gender reported blocked');
        genderCheck($row()===$before,'direct blocked PATCH preserves complete stored row');
    }
    foreach([['prefix'=>'Dr.'],['professional_designation'=>'Médico general'],['bio_short'=>str_repeat('á',150)],['bio_short'=>null],['display_name'=>'Nombre público sintético','prefix'=>'Dra.','professional_designation'=>'Médica general','bio_short'=>'Actividad profesional.','profile_theme_key'=>'mxmed_teal'],['prefix'=>'Dr.','gender'=>'masculino','gender_label'=>'Masculino']] as $payload){
        $result=$request($payload);genderCheck($result['ok'],'other editable fields save');
        foreach($payload as $key=>$value)if(!in_array($key,['gender','gender_label'],true))genderCheck($row()[$key]===$value,'exact persistence '.$key);
        genderCheck($row()['gender']==='femenino' && $row()['gender_label']==='Femenino','other field save preserves admission gender');
    }
    // Reuse the focused Bio persistence suite on this synthetic database only.
    $command=[PHP_BINARY,$root.'/modules/profiles/tests/BioShortPersistenceTest.php'];
    $bio=proc_open($command,[0=>['pipe','r'],1=>STDOUT,2=>STDERR],$bioPipes,$root,$env);
    genderCheck(proc_close($bio)===0,'Bio 150 persistence and rejection suite');
} finally {
    if(is_resource($server)){proc_terminate($server);proc_close($server);}
    $pdo->exec("DROP DATABASE `$database`");
    foreach(glob($temp.'/sessions/*')?:[] as $file)unlink($file);rmdir($temp.'/sessions');
    foreach(glob($temp.'/*')?:[] as $file)unlink($file);rmdir($temp);
}
echo "GENDER_WRITE_AUTHORITY_HTTP=PASS\n";
