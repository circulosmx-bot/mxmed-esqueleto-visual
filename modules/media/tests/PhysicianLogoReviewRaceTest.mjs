import assert from 'node:assert/strict';
import {spawn,execFileSync} from 'node:child_process';
import {access,writeFile,rm} from 'node:fs/promises';
const env={...process.env,MR5_FIXTURE_ROOT:process.env.MR5_FIXTURE_ROOT||'/tmp/mxmed-mr5-mr6qa'};
const run=mode=>JSON.parse(execFileSync('php',['modules/media/tests/PhysicianLogoReviewFixture.php',mode],{env,encoding:'utf8'}));
const query=code=>execFileSync('php',['-r','require "modules/media/tests/MediaReviewInterventionFixture.php";$p=mr5Pdo();'+code],{env,encoding:'utf8'}).trim();
const worker=(mode,id,gate='')=>{const p=spawn('php',['modules/media/tests/PhysicianLogoReviewRaceWorker.php',mode,id,gate],{env,stdio:['ignore','pipe','pipe']});let out='',error='';p.stdout.on('data',b=>out+=b);p.stderr.on('data',b=>error+=b);return new Promise(resolve=>p.on('exit',code=>resolve({code,...JSON.parse(out||'{}'),stderr:error})));};
for(const first of ['corrected','approve']){
 const f=run('candidate'),gate=env.MR5_FIXTURE_ROOT+'/race-'+first+'-'+Date.now();
 const a=worker(first,f.id,gate);
 try{
  let ready=false;for(let i=0;i<150;i++){try{await access(gate+'.ready');ready=true;break;}catch{await new Promise(r=>setTimeout(r,20));}}
  assert.equal(ready,true,'first operation holds shared locks');
  const b=worker(first==='corrected'?'approve':'corrected',f.id);
  await new Promise(r=>setTimeout(r,150));await writeFile(gate+'.go','release');
  const [one,two]=await Promise.all([a,b]);assert.equal(one.code,0,JSON.stringify(one));
  if(first==='corrected'){
   assert.equal(two.code,0,JSON.stringify(two));
   const row=JSON.parse(query(`echo json_encode($p->query("SELECT (SELECT checksum_sha256 FROM media_review_files WHERE submission_id='${f.id}' AND role='REVIEW') review_hash,(SELECT a.checksum_sha256 FROM media_assets a JOIN profiles_doctors p ON p.logo_url=a.public_url WHERE p.doctor_id='${f.doctor}' AND a.status='READY') public_hash")->fetch());`));
   assert.equal(row.public_hash,row.review_hash,'approval publishes committed corrected REVIEW');
  }else{assert.equal(two.code,2);assert.equal(two.error,'intervention_conflict');assert.equal(query(`echo $p->query("SELECT COUNT(*) FROM media_review_files WHERE submission_id='${f.id}' AND role='CORRECTED'")->fetchColumn();`),'0');}
  assert.equal(query(`echo $p->query("SELECT COUNT(*) FROM media_assets WHERE owner_id='${f.doctor}' AND status='READY'")->fetchColumn();`),'2');
  console.log('PASS deterministic race: '+first+' wins first');
 }finally{await writeFile(gate+'.go','release');await a;await rm(gate+'.ready',{force:true});await rm(gate+'.go',{force:true});}
}

const f=run('candidate');const both=await Promise.all([worker('approve',f.id),worker('approve',f.id)]);assert.deepEqual(both.map(r=>r.code).sort(),[0,2]);assert.equal(both.find(r=>r.code===2).error,'approval_conflict');console.log('PASS two reviewers: one publication');
