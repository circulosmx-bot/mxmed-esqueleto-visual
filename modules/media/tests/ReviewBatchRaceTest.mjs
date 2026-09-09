import assert from 'node:assert/strict';
import {spawn,execFileSync} from 'node:child_process';
import {access,writeFile,rm} from 'node:fs/promises';
const env={...process.env,MR5_FIXTURE_ROOT:process.env.MR5_FIXTURE_ROOT||'/tmp/mxmed-mr5-mr11qa'};
const sql=code=>execFileSync('php',['-r','require "modules/media/tests/ReviewBatchFixture.php";$p=mr5Pdo();'+code],{env,encoding:'utf8'}).trim();
const worker=(mode,f,gate='')=>{const p=spawn('php',['modules/media/tests/ReviewBatchRaceWorker.php',mode,f.doctor,f.batch_id||'',gate],{env});let out='',err='';p.stdout.on('data',b=>out+=b);p.stderr.on('data',b=>err+=b);return new Promise(resolve=>p.on('exit',code=>resolve({code,...JSON.parse(out||'{}'),err})));};
for(const [first,second,fresh] of [['upload','auto'],['auto','upload'],['upload','manual'],['manual','upload'],['manual','auto'],['auto','manual'],['upload','upload'],['upload','upload',true]]){
 const f=fresh?JSON.parse(sql('echo json_encode(["doctor"=>mr11Doctor(),"batch_id"=>""]);')):JSON.parse(sql('$f=mr11Batch(1,false);$p->exec("UPDATE media_review_batches SET last_activity_at=DATE_SUB(CURRENT_TIMESTAMP(6),INTERVAL 31 MINUTE) WHERE batch_id=\\x27".$f["batch_id"]."\\x27");echo json_encode($f);'));
 const gate=env.MR5_FIXTURE_ROOT+'/race-mr11-'+Date.now();const one=worker(first,f,gate);
 try{
  let ready=false;for(let i=0;i<250;i++){try{await access(gate+'.ready');ready=true;break;}catch{await new Promise(r=>setTimeout(r,20));}}assert.ok(ready);
  const two=worker(second,f);await new Promise(r=>setTimeout(r,180));await writeFile(gate+'.go','release');const results=await Promise.all([one,two]);for(const r of results)assert.equal(r.code,0,JSON.stringify(r));
  const state=JSON.parse(sql('$d="'+f.doctor+'";echo json_encode($p->query("SELECT b.batch_id,b.status,COUNT(s.submission_id) n,(SELECT COUNT(*) FROM media_review_batch_ready_events e WHERE e.batch_id=b.batch_id) signals FROM media_review_batches b LEFT JOIN media_review_submissions s ON s.batch_id=b.batch_id WHERE b.owner_id=\\x27$d\\x27 GROUP BY b.batch_id")->fetchAll());'));
  assert.ok(state.filter(b=>b.status==='OPEN').length<=1);for(const b of state)assert.equal(Number(b.signals),b.status==='SUBMITTED'?1:0);
  const old=state.find(b=>b.batch_id===f.batch_id);
  if(fresh){assert.equal(state.length,1);assert.equal(state[0].status,'OPEN');assert.equal(Number(state[0].n),2);}
  else if(first==='upload'&&second==='auto'){assert.equal(old.status,'OPEN');assert.equal(Number(old.n),2);}
  else if(first==='upload'&&second==='upload'){assert.equal(state.length,1);assert.equal(Number(old.n),3);}
  else if(first==='upload'){assert.equal(old.status,'SUBMITTED');assert.equal(Number(old.n),2);}
  else {assert.equal(old.status,'SUBMITTED');assert.equal(Number(old.n),1);if(second==='upload'){assert.equal(state.length,2);assert.equal(Number(state.find(b=>b.status==='OPEN').n),1);}}
  console.log('PASS '+first+' before '+second+(fresh?' without existing batch':''));
 }finally{await writeFile(gate+'.go','release');await one;await rm(gate+'.ready',{force:true});await rm(gate+'.go',{force:true});}
}
console.log('MR11_RACE_QA=PASS');
