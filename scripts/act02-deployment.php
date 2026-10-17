<?php
declare(strict_types=1);
/** Read-only package/target inspector. No apply, receipt generation or SQL mutation mode. */
if(PHP_SAPI!=='cli')exit(1);
require_once dirname(__DIR__).'/modules/identity/http/IdentityHttpComposition.php';
Identity\Http\IdentityHttpComposition::registerAutoloader();
use Identity\Operational\{DeploymentPackage,ActivationGuard};
try{
    if(($argv[1]??null)==='--manifest'&&$argc===2){
        $steps=[];foreach(DeploymentPackage::migrations() as $i=>$f)$steps[]=['sequence'=>$i+1,'file'=>$f,'sha256'=>hash_file('sha256',DeploymentPackage::root().'/'.$f),
            'before'=>'Verify previous checkpoint and preserved original-column data hashes','after'=>'Record schema fingerprint, data hashes, FK/trigger checks; no blind replay'];
        echo json_encode(['contract'=>'MXMED-ACT02.1','precondition'=>'Current normal-local OP-ACT02A schema, C4 authority tables absent/empty as migration 04 requires',
            'source_hash'=>DeploymentPackage::sourceHash(),'steps'=>$steps,'authorization'=>null,'activation'=>false,
            'partial_DDL_recovery'=>'Stop. Do not rerun a partially executed migration. Restore the full verified backup to a fresh target, verify preflight fingerprint, then start at step 1.',
            'runtime_principal'=>'Dedicated non-root, no global privileges or DDL; exact table DML and native audit procedure EXECUTE only',
            'rollback'=>'Restore the complete consistent backup. Do not remove additive tables after accepting new writes.'],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;exit;
    }
    if(($argv[1]??null)!=='--connection'||$argc!==3)throw new RuntimeException('arguments_required');
    $c=ActivationGuard::json($argv[2]);
    if(($c['MXMED_DB_HOST']??null)!=='127.0.0.1'||(string)($c['MXMED_DB_PORT']??'')!=='3306'
        ||!preg_match('/^mxmed(?:_gate4d_preview_act02_[a-f0-9]{10})?$/D',$c['MXMED_DB_NAME']??''))throw new RuntimeException('target_denied');
    $p=new PDO('mysql:host=127.0.0.1;port=3306;dbname='.$c['MXMED_DB_NAME'].';charset=utf8mb4',$c['MXMED_DB_USER'],$c['MXMED_DB_PASS'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    $p->exec('SET TRANSACTION READ ONLY');$p->beginTransaction();$v=DeploymentPackage::inspect($p);
    try{DeploymentPackage::assertSchema($p);$v['integrated_schema_ready']=true;}catch(Throwable){$v['integrated_schema_ready']=false;}
    $p->rollBack();echo json_encode($v,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
}catch(Throwable){fwrite(STDERR,"ACT02_INSPECTION_REFUSED: private input, target or metadata unavailable; no activation or DDL performed.\n");exit(78);}
