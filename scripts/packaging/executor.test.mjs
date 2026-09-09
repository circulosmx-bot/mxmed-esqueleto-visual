import assert from 'node:assert/strict';
import {spawn,execFileSync} from 'node:child_process';
const env={...process.env,MR5_FIXTURE_ROOT:'/tmp/mxmed-mr5-mr113qa'};
const sql=code=>execFileSync('php',['-r','require "modules/media/tests/ReviewBatchFixture.php";$p=mr5Pdo();'+code],{env,encoding:'utf8'}).trim();
// Only the disposable local MySQL fixture port; no application configuration.
const ids=JSON.parse(sql('$ids=[];foreach([29,31] as $m){$f=mr11Batch(1,false);$p->exec("UPDATE media_review_batches SET last_activity_at=DATE_SUB(CURRENT_TIMESTAMP(6),INTERVAL $m MINUTE) WHERE batch_id=\\x27".$f["batch_id"]."\\x27");$ids[$m]=$f["batch_id"];}echo json_encode($ids);'));
const launch=()=>new Promise((resolve,reject)=>{
 const p=spawn('docker',['run','--rm','--platform','linux/amd64','--read-only','-e','MXMED_DB_HOST=host.docker.internal','-e','MXMED_DB_PORT=3309','-e','MXMED_DB_NAME=mxmed','-e','MXMED_DB_USER=root','mxmed-application:local-validation','php','modules/media/bin/submit-inactive-review-batches.php','100']);
 let out='',err='';p.stdout.on('data',b=>out+=b);p.stderr.on('data',b=>err+=b);p.on('error',reject);p.on('exit',code=>code===0?resolve(JSON.parse(out)):reject(Error(err)));
});
const results=await Promise.all([launch(),launch()]);
for(const [minutes,id] of Object.entries(ids)) {
 const row=JSON.parse(sql('echo json_encode($p->query("SELECT status,(SELECT COUNT(*) FROM media_review_batch_ready_events WHERE batch_id=\\x27'+id+'\\x27) signals FROM media_review_batches WHERE batch_id=\\x27'+id+'\\x27")->fetch());'));
 assert.equal(row.status,minutes==='29'?'OPEN':'SUBMITTED');assert.equal(Number(row.signals),minutes==='29'?0:1);
}
await launch();
assert.equal(Number(sql('echo $p->query("SELECT COUNT(*) FROM media_review_batch_ready_events WHERE batch_id=\\x27'+ids[31]+'\\x27")->fetchColumn();')),1);
console.log('PACKAGED_EXECUTOR_29_31_CONCURRENT_REPEAT=PASS',JSON.stringify(results));
