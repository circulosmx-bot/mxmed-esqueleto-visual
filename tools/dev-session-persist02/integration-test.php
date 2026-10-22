<?php
declare(strict_types=1);
$root=$argv[1]??dirname(__DIR__,2);
require_once $root.'/modules/identity/http/IdentityHttpComposition.php';
\Identity\Http\IdentityHttpComposition::registerAutoloader();
use Identity\Http\NormalLocalIdentityConfiguration as Config;
use Identity\Contracts\DevelopmentSessionCookiePolicy as Cookie;
function pass(bool $ok,string $s):void{if(!$ok)throw new RuntimeException($s);echo $s."=PASS\n";}
function deny(callable $f):bool{try{$f();return false;}catch(RuntimeException){return true;}}
$d='/tmp/mxmed-persist02-config-'.bin2hex(random_bytes(6));mkdir($d,0700);
try{
$v=['APP_ENV'=>'local','MXMED_NORMAL_LOCAL_IDENTITY_EXPLICIT'=>'1','MXMED_DB_NAME'=>'mxmed','MXMED_DB_USER'=>'synthetic_no_connection','MXMED_NORMAL_LOCAL_VALKEY_PORT'=>'55801','MXMED_NORMAL_LOCAL_IDENTITY_PEPPER'=>str_repeat('x',32),'MXMED_NORMAL_LOCAL_IDENTITY_ORIGIN'=>'https://localhost:8443','MXMED_NORMAL_LOCAL_NOTIFICATION_FILE'=>$d.'/inert.json'];
pass(Cookie::forNormalLocal(Config::fromValues($v))===null,'Original default unchanged');
$enabled=$v+['MXMED_NORMAL_LOCAL_PERSISTENT_COOKIE'=>'1','MXMED_NORMAL_LOCAL_VALKEY_AOF'=>'1'];
pass(Cookie::forNormalLocal(Config::fromValues($enabled)) instanceof Cookie,'Validated local explicit opt-in');
foreach(['production','staging'] as $e)pass(deny(fn()=>Config::fromValues(array_replace($enabled,['APP_ENV'=>$e,'MXMED_ENVIRONMENT'=>$e]))),'Production environment rejected '.$e);
foreach(['MXMED_NORMAL_LOCAL_PERSISTENT_COOKIE','MXMED_NORMAL_LOCAL_VALKEY_AOF'] as $k)pass(deny(fn()=>Config::fromValues(array_replace($enabled,[$k=>'true']))),'Ambiguous flag rejected '.$k);
pass(deny(fn()=>Config::fromValues(array_replace($enabled,['MXMED_NORMAL_LOCAL_IDENTITY_ORIGIN'=>'https://example.test']))),'Public origin rejected');
pass(deny(fn()=>Config::fromValues(array_replace($enabled,['MXMED_PREVIEW_EXPLICIT'=>'1']))),'Mixed composition rejected');
pass(deny(fn()=>Config::fromValues(array_replace($enabled,['MXMED_NORMAL_LOCAL_IDENTITY_EXPLICIT'=>'0']))),'Missing explicit normal-local gate rejected');
pass(\Identity\Contracts\SessionPolicy::IDLE_TTL_SECONDS===3600&&\Identity\Contracts\SessionPolicy::ABSOLUTE_TTL_SECONDS===43200,'Canonical durations unchanged');
}finally{rmdir($d);}
