import assert from 'node:assert/strict';
import {spawn,execFileSync} from 'node:child_process';
import {access,writeFile,rm} from 'node:fs/promises';
const env={...process.env,MR5_FIXTURE_ROOT:process.env.MR5_FIXTURE_ROOT||'/tmp/mxmed-mr5-mr9qa'};
const sql=code=>execFileSync('php',['-r','require "modules/media/tests/MediaReplacementFixture.php";$p=mr5Pdo();'+code],{env,encoding:'utf8'}).trim();
const worker=(mode,id,kind,gate='')=>{const p=spawn('php',['modules/media/tests/MediaReplacementRaceWorker.php',mode,id,kind,gate],{env});let out='',err='';p.stdout.on('data',b=>out+=b);p.stderr.on('data',b=>err+=b);return new Promise(resolve=>p.on('exit',code=>resolve({code,...JSON.parse(out||'{}'),err})));};
for(const kind of ['photo','logo'])for(const other of (kind==='photo'?['approve','corrected']:['approve','corrected','generate','accept','discard']))for(const first of ['replace',other]){
 const second=first==='replace'?other:'replace';const f=JSON.parse(sql(`echo json_encode(${kind==='photo'?'mr5Candidate($p)':'mr8Candidate()'});`));
 if(['accept','discard'].includes(other))assert.equal((await worker('generate',f.id,kind)).code,0);
 const gate=env.MR5_FIXTURE_ROOT+'/race-mr9-'+Date.now();const a=worker(first,f.id,kind,gate);
 try{
  let ready=false;for(let i=0;i<250;i++){try{await access(gate+'.ready');ready=true;break;}catch{await new Promise(r=>setTimeout(r,20));}}assert.ok(ready,'lock acquired');
  const b=worker(second,f.id,kind);await new Promise(r=>setTimeout(r,120));await writeFile(gate+'.go','release');const [one,two]=await Promise.all([a,b]);assert.equal(one.code,0,JSON.stringify(one));
  if(first==='replace'||first==='approve'){assert.equal(two.code,2,JSON.stringify(two));assert.ok(two.error.endsWith('_conflict'));}else assert.equal(two.code,0,JSON.stringify(two));
  assert.equal(sql(`echo $p->query("SELECT review_status FROM media_review_submissions WHERE submission_id='${f.id}'")->fetchColumn();`),first==='approve'?'APPROVED':'NEEDS_WORK');
  console.log(`PASS ${kind}: ${first} before ${second}`);
 }finally{await writeFile(gate+'.go','release');await a;await rm(gate+'.go',{force:true});await rm(gate+'.ready',{force:true});}
}
