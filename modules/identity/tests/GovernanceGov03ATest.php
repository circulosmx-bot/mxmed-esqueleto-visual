<?php
declare(strict_types=1);
/** Historical entry point retained. The v1 beneficiary-as-requester fixture is no
 * longer a valid runtime contract. Exercise its replacement through the complete
 * v2 chain, canonical sessions, native audit, real SQL isolation and concurrency. */
if(PHP_SAPI!=='cli')exit(1);
$root=dirname(__DIR__,3);$dir=sys_get_temp_dir().'/mxmed-gov03b-regression-'.bin2hex(random_bytes(6));
if(!mkdir($dir,0700))throw new RuntimeException('private_qa_directory');
$input=(string)getenv('GOV03B_QA_ADMIN_CONNECTION');$temporary=$input==='';
if($temporary){$input=$dir.'/admin-connection.json';file_put_contents($input,json_encode(['MXMED_DB_HOST'=>'127.0.0.1','MXMED_DB_PORT'=>'3306','MXMED_DB_NAME'=>'mxmed','MXMED_DB_USER'=>'root','MXMED_DB_PASS'=>''],JSON_THROW_ON_ERROR));chmod($input,0600);}
try{
    $process=proc_open(['python3',$root.'/scripts/gov03b-disposable-qa.py','--connection',$input,'--evidence',$dir],[0=>['file','/dev/null','r'],1=>STDOUT,2=>STDERR],$pipes,$root);
    if(!is_resource($process))throw new RuntimeException('disposable_qa_start_failed');
    $result=proc_close($process);
}finally{if($temporary&&is_file($input))unlink($input);}
if($result!==0)exit($result);
echo "IDENTITY_GOV03A_UPDATED_V2_REGRESSION=PASS\n";
