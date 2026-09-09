import assert from 'node:assert/strict';
import {spawn,execFileSync} from 'node:child_process';
import {access,writeFile,rm} from 'node:fs/promises';
const env={...process.env,MR5_FIXTURE_ROOT:process.env.MR5_FIXTURE_ROOT||'/tmp/mxmed-mr5-mr11qa'};
const sql=code=>execFileSync('php',['-r','require "modules/media/tests/ReviewBatchFixture.php";$p=mr5Pdo();'+code],{env,encoding:'utf8'}).trim();
const worker=(mode,f,gate='')=>{const p=spawn('php',['modules/media/tests/ReviewBatchRaceWorker.php',mode,f.doctor,f.batch_id||'',gate,f.id||''],{env});let out='',err='';p.stdout.on('data',b=>out+=b);p.stderr.on('data',b=>err+=b);return new Promise(resolve=>p.on('exit',code=>resolve({code,...JSON.parse(out||'{}'),err})));};

for(const [first,second] of [['withdraw','upload'],['upload','withdraw']]){
 const f=JSON.parse(sql('echo json_encode(mr11Batch(1,false));'));
 const gate=env.MR5_FIXTURE_ROOT+'/race-empty-'+Date.now();const one=worker(first,f,gate);
 try{
  let ready=false;for(let i=0;i<250;i++){try{await access(gate+'.ready');ready=true;break;}catch{await new Promise(r=>setTimeout(r,20));}}assert.ok(ready);
  const two=worker(second,f);await new Promise(r=>setTimeout(r,180));await writeFile(gate+'.go','release');for(const r of await Promise.all([one,two]))assert.equal(r.code,0,JSON.stringify(r));
  const rows=JSON.parse(sql('echo json_encode($p->query("SELECT submission_id,batch_id,review_status FROM media_review_submissions WHERE owner_id=\\x27'+f.doctor+'\\x27")->fetchAll());'));
  const pending=rows.filter(r=>r.review_status==='PENDING_REVIEW');assert.equal(pending.length,1);
  if(first==='withdraw'){assert.notEqual(pending[0].batch_id,f.batch_id);assert.equal(rows.find(r=>r.submission_id===f.id).batch_id,null);}
  else assert.equal(pending[0].batch_id,f.batch_id);
  assert.equal(Number(sql('echo $p->query("SELECT COUNT(*) FROM media_review_batches WHERE owner_id=\\x27'+f.doctor+'\\x27 AND status=\\x27OPEN\\x27")->fetchColumn();')),1);
  assert.equal(Number(sql('echo $p->query("SELECT COUNT(*) FROM media_review_batch_ready_events WHERE batch_id=\\x27'+f.batch_id+'\\x27")->fetchColumn();')),0);
  console.log('PASS '+first+' before '+second);
 }finally{await writeFile(gate+'.go','release');await one;await rm(gate+'.ready',{force:true});await rm(gate+'.go',{force:true});}
}
console.log('EMPTY_BATCH_RACE_QA=PASS');
