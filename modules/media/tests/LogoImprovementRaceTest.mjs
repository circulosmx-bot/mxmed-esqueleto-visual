import assert from 'node:assert/strict';
import {spawn,execFileSync} from 'node:child_process';
import {access,writeFile,rm} from 'node:fs/promises';
const env={...process.env,MR5_FIXTURE_ROOT:process.env.MR5_FIXTURE_ROOT||'/tmp/mxmed-mr5-mr8qa'};
const candidate=()=>JSON.parse(execFileSync('php',['modules/media/tests/LogoImprovementFixture.php','candidate'],{env,encoding:'utf8'}));
const sql=code=>execFileSync('php',['-r','require "modules/media/tests/LogoImprovementFixture.php";$p=mr5Pdo();'+code],{env,encoding:'utf8'}).trim();
const worker=(mode,id,gate='')=>{const p=spawn('php',['modules/media/tests/LogoImprovementRaceWorker.php',mode,id,gate],{env,stdio:['ignore','pipe','pipe']});let out='',err='';p.stdout.on('data',b=>out+=b);p.stderr.on('data',b=>err+=b);return new Promise(resolve=>p.on('exit',code=>resolve({code,...JSON.parse(out||'{}'),err})));};
for(const [first,second] of [['accept','approve'],['approve','accept'],['corrected','accept'],['approve','generate'],['generate','approve']]){
 const f=candidate();if(first!=='generate')assert.equal((await worker('generate',f.id)).code,0);const gate=env.MR5_FIXTURE_ROOT+'/race-mr8-'+Date.now();const a=worker(first,f.id,gate);
 try{
  let ready=false;for(let i=0;i<250;i++){try{await access(gate+'.ready');ready=true;break;}catch{await new Promise(r=>setTimeout(r,20));}}assert.ok(ready,'lock acquired');
  const b=worker(second,f.id);await new Promise(r=>setTimeout(r,150));await writeFile(gate+'.go','release');const [one,two]=await Promise.all([a,b]);assert.equal(one.code,0,JSON.stringify(one));
  if(['approve','corrected'].includes(first)){assert.equal(two.code,2);assert.equal(two.error,'improvement_conflict');}
  else{assert.equal(two.code,0,JSON.stringify(two));const hashes=JSON.parse(sql(`echo json_encode($p->query("SELECT (SELECT checksum_sha256 FROM media_review_files WHERE submission_id='${f.id}' AND role='REVIEW') review_hash,(SELECT a.checksum_sha256 FROM media_assets a JOIN profiles_doctors p ON p.logo_url=a.public_url WHERE p.doctor_id='${f.doctor}') public_hash")->fetch());`));assert.equal(hashes.review_hash,hashes.public_hash);}
  console.log(`PASS race ${first} before ${second}`);
 }finally{await writeFile(gate+'.go','release');await a;await rm(gate+'.go',{force:true});await rm(gate+'.ready',{force:true});}
}
