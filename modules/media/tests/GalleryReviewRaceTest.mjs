import assert from 'node:assert/strict';
import {spawn,execFileSync} from 'node:child_process';
import {access,writeFile,rm} from 'node:fs/promises';
const env={...process.env,MR5_FIXTURE_ROOT:process.env.MR5_FIXTURE_ROOT||'/tmp/mxmed-mr5-mr10qa'};
const sql=code=>execFileSync('php',['-r','require "modules/media/tests/GalleryReviewFixture.php";$p=mr5Pdo();'+code],{env,encoding:'utf8'}).trim();
const worker=(mode,f,gate='')=>{const p=spawn('php',['modules/media/tests/GalleryReviewRaceWorker.php',mode,f.doctor,f.id||'',gate],{env});let out='',err='';p.stdout.on('data',b=>out+=b);p.stderr.on('data',b=>err+=b);return new Promise(resolve=>p.on('exit',code=>resolve({code,...JSON.parse(out||'{}'),err})));};
async function race(first,second,a,b,expected){
 const gate=env.MR5_FIXTURE_ROOT+'/race-mr10-'+Date.now();const one=worker(first,a,gate);
 try{
  let ready=false;for(let i=0;i<250;i++){try{await access(gate+'.ready');ready=true;break;}catch{await new Promise(r=>setTimeout(r,20));}}assert.ok(ready,'lock acquired');
  const two=worker(second,b);await new Promise(r=>setTimeout(r,180));await writeFile(gate+'.go','release');const [r1,r2]=await Promise.all([one,two]);assert.equal(r1.code,0,JSON.stringify(r1));assert.equal(r2.code,expected,JSON.stringify(r2));
  const counts=JSON.parse(sql(`echo json_encode(Media\\Services\\GalleryCapacity::counts($p,'${a.doctor}'));`));assert.ok(counts.public<=16);console.log(`PASS ${first} before ${second}: ${JSON.stringify(counts)}`);
 }finally{await writeFile(gate+'.go','release');await one;await rm(gate+'.ready',{force:true});await rm(gate+'.go',{force:true});}
}
let f=JSON.parse(sql('$doctor=mr10Doctor();for($i=0;$i<15;$i++)mr10Candidate($doctor);echo json_encode(["doctor"=>$doctor]);'));await race('candidate','candidate',f,f,2);
let pair=JSON.parse(sql('$a=mr10Candidate();$b=mr10Candidate($a["doctor"]);mr10SeedPublic($a["doctor"],15);echo json_encode([$a,$b]);'));await race('approve','approve',...pair,2);
for(const other of ['replace','withdraw','corrected'])for(const first of ['approve',other]){f=JSON.parse(sql('echo json_encode(mr10Candidate());'));const second=first==='approve'?other:'approve';await race(first,second,f,f,first==='corrected'?0:2);}
// Both current upload paths reserve the same combined capacity under the physician lock.
f=JSON.parse(sql('$d=mr10Doctor();mr10SeedPublic($d,15);echo json_encode(["doctor"=>$d]);'));await race('candidate','direct',f,f,2);
f=JSON.parse(sql('$d=mr10Doctor();mr10SeedPublic($d,15);echo json_encode(["doctor"=>$d]);'));await race('direct','candidate',f,f,2);
f=JSON.parse(sql('$f=mr10Candidate();mr10SeedPublic($f["doctor"],15);echo json_encode($f);'));await race('approve','direct',f,f,2);
// A historical/out-of-band public addition can leave 16 public + 1 pending: publication must still conflict.
f=JSON.parse(sql('$f=mr10Candidate();mr10SeedPublic($f["doctor"],16);echo json_encode($f);'));assert.equal((await worker('approve',f)).code,2);assert.equal(JSON.parse(sql(`echo json_encode(Media\\Services\\GalleryCapacity::counts($p,'${f.doctor}'));`)).public,16);console.log('PASS historical 16 public + 1 pending stays pending; no seventeenth public asset');
