// Isolated MR02 acceptance runner. No Director DB configuration or media copied.
import {execFileSync} from 'node:child_process';
import {mkdir,mkdtemp,readFile,writeFile,copyFile,readdir,rm} from 'node:fs/promises';
import path from 'node:path';
import {fileURLToPath} from 'node:url';
const source=path.resolve(path.dirname(fileURLToPath(import.meta.url)),'../../..');
await mkdir('/tmp/mxmed-mr02',{recursive:true});
const root=await mkdtemp('/tmp/mxmed-mr02/run-'),storage=await mkdtemp('/tmp/mxmed-mr5-mr02-');
const name='mxmed-mr02-'+path.basename(root),port=3313;
const env={...process.env,MR5_FIXTURE_ROOT:storage};
const run=(cmd,args,cwd=root)=>execFileSync(cmd,args,{cwd,env,encoding:'utf8',stdio:['ignore','pipe','pipe']});
let started=false;
try {
 const paths=new Set(JSON.parse(await readFile(source+'/scripts/packaging/runtime-files.json','utf8')));
 const collect=async folder=>{for(const entry of await readdir(source+'/'+folder,{withFileTypes:true})){const file=folder+'/'+entry.name;if(entry.isDirectory())await collect(file);else paths.add(file);}};
 for(const dir of ['modules/media','modules/platform/db','modules/platform/tests','modules/profiles/db','scripts/packaging'])await collect(dir);
 for(const file of paths){const dest=root+'/'+file;await mkdir(path.dirname(dest),{recursive:true});if(/\.(php|mjs)$/.test(file))await writeFile(dest,(await readFile(source+'/'+file,'utf8')).replaceAll('port=3309','port='+port));else await copyFile(source+'/'+file,dest);}
 await writeFile(root+'/api/mxmed-db.config.php',`<?php return ['mysql'=>['host'=>'127.0.0.1','port'=>${port},'dbname'=>'mxmed','user'=>'root','pass'=>'','charset'=>'utf8mb4','collation'=>'utf8mb4_unicode_ci']];`);
 await mkdir(storage+'/private');await mkdir(storage+'/public');
 run('docker',['run','--rm','-d','--name',name,'-p',`127.0.0.1:${port}:3306`,'-e','MYSQL_ALLOW_EMPTY_PASSWORD=yes','mysql:8.4']);started=true;
 for(let i=0;;i++){try{run('docker',['exec',name,'mysqladmin','--protocol=TCP','-h127.0.0.1','ping']);break;}catch(e){if(i===90)throw e;await new Promise(r=>setTimeout(r,300));}}
 run('php',['scripts/packaging/setup-test-db.php']);
 const tests=['ItemSubmissionMigrationTest.php','ItemSubmissionTest.php','ReviewBatchTest.php','ReviewBatchAtomicTest.php','EmptyReviewBatchTest.php','ProfilePhotoApprovalTest.php','PhysicianLogoApprovalTest.php','PhysicianLogoReviewTest.php','MediaReviewInterventionTest.php','MediaReplacementTest.php','GalleryReviewTest.php','LogoImprovementTest.php','OriginalArchiveTest.php','ItemSubmissionRaceTest.mjs','ItemSubmissionHttpTest.mjs'];
 for(const test of tests){const output=run(test.endsWith('.mjs')?'node':'php',['modules/media/tests/'+test]);await writeFile('/tmp/mxmed-mr02/'+test+'.log',output);console.log('PASS '+test);}
 console.log('MR02_ISOLATED_QA=PASS; 15 suites; no Director database/media; logs=/tmp/mxmed-mr02');
}finally{if(started)run('docker',['stop',name]);await rm(root,{recursive:true,force:true});await rm(storage,{recursive:true,force:true});}
