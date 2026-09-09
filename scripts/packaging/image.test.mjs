import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
const image='mxmed-application:local-validation';
const docker=(args)=>execFileSync('docker',args,{encoding:'utf8'}).trim();
const run=(args)=>docker(['run','--rm','--platform','linux/amd64','--network','none','--read-only',image,...args]);
const config=JSON.parse(docker(['image','inspect',image]))[0];
assert.equal(config.Architecture,'amd64');assert.equal(config.Config.User,'www-data');assert.equal(config.Config.WorkingDir,'/var/www/html');assert.deepEqual(config.Config.Cmd,['apache2-foreground']);
console.log(run(['php','-v']));
console.log(run(['php','-l','/var/www/html/modules/media/bin/submit-inactive-review-batches.php']));
console.log(run(['php','-r',String.raw`
$executor='modules/media/bin/submit-inactive-review-batches.php';
$code=file_get_contents($executor);
preg_match_all("~require_once __DIR__\\.'([^']+)';~",$code,$matches);
if(count($matches[1])!==2)exit(2);
foreach($matches[1] as $relative)require_once dirname($executor).$relative;
// These dependencies are leaves; any new nested include needs a reviewed test update.
foreach(get_included_files() as $file){if(preg_match('/(?:require|include)(?:_once)?\\s+/',file_get_contents($file)) && basename($file)!=='db.php')exit(3);}
if(count(get_included_files())!==2||!function_exists('mxmed_pdo')||!class_exists('Media\\Services\\MediaReviewBatchService'))exit(4);
if(is_file('api/mxmed-db.config.php'))exit(5);
foreach(['PDO','pdo_mysql','gd','exif','mbstring','intl','zip','redis'] as $ext)if(!extension_loaded($ext))exit(6);
require 'vendor/autoload.php';if(!class_exists('Aws\\SesV2\\SesV2Client'))exit(7);
echo "BOOTSTRAP_RESOLVES_WITHOUT_DB\n";
`]));
const name='mxmed-package-web-'+process.pid;
try{
 docker(['run','-d','--name',name,'--platform','linux/amd64','--network','none','--read-only','--tmpfs','/tmp:uid=33,gid=33,mode=1770','--tmpfs','/var/run/apache2:uid=33,gid=33,mode=0770','--tmpfs','/var/lock/apache2:uid=33,gid=33,mode=0770',image]);
 for(const uri of ['/healthz','/index.html','/assets/js/app.js']){
  const result=docker(['exec',name,'curl','--retry','10','--retry-delay','1','--retry-connrefused','--fail','--silent','--show-error','--output','/dev/null','--write-out','%{http_code}','http://127.0.0.1:8080'+uri]);assert.equal(result,'200');
 }
 const error=JSON.parse(docker(['exec',name,'curl','--silent','--show-error','http://127.0.0.1:8080/geocode-proxy.php']));
 assert.equal(error.error,'missing_query');
 console.log('WEB_RUNTIME_SMOKE=PASS');
} finally{docker(['rm','-f',name]);}
