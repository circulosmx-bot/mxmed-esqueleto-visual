<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('MXMED_OPERATOR_DEPLOYMENT_REHEARSAL')!=='1')exit(1);
require_once __DIR__.'/../modules/identity/http/IdentityHttpComposition.php';
Identity\Http\IdentityHttpComposition::registerAutoloader();
use Identity\Operators\{OperatorDeploymentPackage,OperatorDeploymentSchema};
$db=(string)getenv('MXMED_DB_NAME');$user=(string)getenv('MXMED_DB_USER');if(!preg_match('/^mxmed_operator_deploy_([a-f0-9]{10})$/D',$db,$m)||$user!=='mxmed_act01_'.$m[1])throw new RuntimeException('synthetic_deployment_target_required');
$p=new PDO('mysql:host=127.0.0.1;dbname='.$db.';charset=utf8mb4',$user,getenv('MXMED_DB_PASS'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
$support=getenv('HOME').'/Library/Application Support/MXMED/op-act01-rehearsal';$root=dirname(__DIR__);
function seedLegacy(PDO $p):void{
 if((int)$p->query('SELECT COUNT(*) FROM agenda_operators')->fetchColumn()!==0)throw new RuntimeException('legacy_seed_requires_fresh_owned_fixture');
 foreach(['active','paused','archived'] as $n=>$status){$id='act01-legacy-'.$n;$alias='ARCHIVO'.($n+1);$p->prepare("INSERT INTO agenda_operators(operator_id,doctor_id,alias,alias_normalized,full_name,login,login_normalized,status) VALUES(?,'op02-doctor',?,?,?,?,?,?)")->execute([$id,$alias,$alias,'Synthetic historical record',$alias,$alias,$status]);
  for($j=0;$j<3;$j++)$p->prepare("INSERT INTO agenda_operator_audit_events(event_id,doctor_id,operator_id,event_type,action_label) VALUES(?,'op02-doctor',?,'synthetic_history','Acción anterior sintética')")->execute([$id.'-'.$j,$id]);
 }
}
if(($argv[1]??'')==='--legacy-seed'){seedLegacy($p);echo "OP_ACT01_SYNTHETIC_LEGACY_READY=true\n";exit;}
if(($argv[1]??'')!=='--seal'){
    $migration=new PDO('mysql:host=127.0.0.1;dbname='.$db.';charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    foreach(['2026_10_14_02_profile_ownership_r3_endorsements.sql','2026_10_15_01_identity_governance_r3_runtime.sql'] as $f)$migration->exec(file_get_contents($root.'/modules/identity/db/migrations/'.$f));
    $package=new OperatorDeploymentPackage($migration);$approved=$package->inspect()+['authorization_reference'=>'OP-ACT01-SYNTHETIC-REHEARSAL'];$package->apply($approved,$support.'/migration-journal.json');
    if((int)$p->query('SELECT COUNT(*) FROM auth_accounts')->fetchColumn()!==0)throw new RuntimeException('fresh_synthetic_schema_required');
    // Existing synthetic seeder's data block only; never its schema/factory/reset.
    $seed=file_get_contents($root.'/scripts/op02-fixture.php');$seed=substr($seed,strpos($seed,'$credentials=json_decode'));putenv('MXMED_OP02_CREDENTIALS_JSON='.$support.'/credentials.json');eval($seed);seedLegacy($p);
}
$journal=json_decode(file_get_contents($support.'/migration-journal.json'),true,512,JSON_THROW_ON_ERROR);
if(($journal['state']??'')!=='complete'||($journal['package_hash']??'')!==OperatorDeploymentSchema::packageHash()||($journal['schema_fingerprint']??'')!==OperatorDeploymentSchema::fingerprint($p))throw new RuntimeException('synthetic_schema_drift_requires_review');
$receipt=OperatorDeploymentSchema::identity($p)+['state'=>'ready_for_runtime','authorization_reference'=>'OP-ACT01-SYNTHETIC-REHEARSAL','http_boundary_approved'=>true,'mutations_approved'=>true,'runtime_db_user'=>$p->query('SELECT CURRENT_USER()')->fetchColumn(),'package_hash'=>OperatorDeploymentSchema::packageHash(),'source_hash'=>OperatorDeploymentSchema::sourceHash(),'schema_fingerprint'=>OperatorDeploymentSchema::fingerprint($p),'delivery_key_id'=>'synthetic-key-v1','public_invitation_origin'=>'https://review.mexicomedico.com'];
file_put_contents($support.'/receipt.json',json_encode($receipt,JSON_THROW_ON_ERROR));chmod($support.'/receipt.json',0600);
$config=json_decode(file_get_contents($support.'/environment.json'),true,512,JSON_THROW_ON_ERROR);$config['MXMED_OPERATOR_DEPLOYMENT_RECEIPT_SHA256']=hash_file('sha256',$support.'/receipt.json');file_put_contents($support.'/environment.json',json_encode($config,JSON_THROW_ON_ERROR));chmod($support.'/environment.json',0600);
echo "OP_ACT01_RECEIPT_SEALED=true\n";
