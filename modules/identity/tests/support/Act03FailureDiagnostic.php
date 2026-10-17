<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('ACT03_QA')!=='1'||getenv('MXMED_NORMAL_LOCAL_IDENTITY_EXPLICIT')!=='0'||!preg_match('/^mxmed_gate4d_preview_c4ui_[a-f0-9]{10}$/D',(string)getenv('MXMED_DB_NAME')))exit(1);
// Private failure evidence only. Reuses the failed synthetic actor's canonical
// session, same factory and same command; never constructs an authenticated user.
require_once __DIR__.'/../../http/IdentityHttpComposition.php';Identity\Http\IdentityHttpComposition::registerAutoloader();
require_once __DIR__.'/Act02SyntheticComposition.php';
$input=json_decode(stream_get_contents(STDIN),true,8,JSON_THROW_ON_ERROR);
putenv('MXMED_GOVERNANCE_PROXY_KEY='.str_repeat('t',64));$identity=Identity\Http\IdentityHttpComposition::fromProcessEnvironment();$bundle=Act02SyntheticComposition::install($identity);$d=$identity->sessions()->validate($input['session']);
if(!$d->allowed()||$d->context()===null)exit(2);
try{$bundle['sources']->execute($d->context(),$input['application']);echo json_encode(['replayed'=>'COMPLETE']);}
catch(Throwable $e){echo json_encode(['exception'=>get_class($e),'reason'=>preg_match('/^[a-z0-9_:]+$/D',$e->getMessage())?$e->getMessage():'non_contract_exception']);}
