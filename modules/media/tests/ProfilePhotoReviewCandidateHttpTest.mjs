import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
const base=process.env.PUBLIC_URL||'http://127.0.0.1:8097';
const php=code=>execFileSync('php',['-r',code],{encoding:'utf8'}).trim();
const sessions=['1','2','fixture','conflict'].map(d=>php(`session_id(bin2hex(random_bytes(16)));session_start();$_SESSION=['user_id'=>'mr1-synthetic-qa','doctor_id'=>'${d==='2'?'2':'1'}'];${d==='fixture'?"$_SESSION['subscriptions_dev_session_fixture']=true;":''}${d==='conflict'?"$_SESSION['active_doctor_id']='2';":''}echo session_id();session_write_close();`));
const call=async(session,method='GET',body=null,token='',suffix='',extra={})=>{
 const r=await fetch(base+'/api/media/profile-photo-review-candidate.php'+suffix,{method,body,headers:{...(session?{Cookie:'PHPSESSID='+session}:{}),...(token?{'X-Profile-Photo-Candidate-CSRF':token}:{}),...extra}});
 assert.match(r.headers.get('cache-control'),/no-store/);
 return {status:r.status,body:await r.json()};
};
const bytes=Buffer.from(php(`require 'modules/media/tests/ReviewCandidateFixture.php';$f=candidateFixture('jpeg',4000,3000);echo base64_encode(file_get_contents($f['tmp_name']));unlink($f['tmp_name']);`),'base64');
const form=(data=bytes,type='image/jpeg',name='synthetic.jpeg')=>{const f=new FormData();f.append('image',new Blob([data],{type}),name);f.append('doctor_id','2');return f;};
const state=()=>JSON.parse(php(`require 'api/_lib/db.php';$p=mxmed_pdo();echo json_encode(['assets'=>$p->query('SELECT * FROM media_assets ORDER BY media_id')->fetchAll(),'profiles'=>$p->query('SELECT doctor_id,photo_url,logo_url,updated_at FROM profiles_doctors ORDER BY doctor_id')->fetchAll()]);`));
const files=id=>JSON.parse(php(`require 'api/_lib/db.php';$s=mxmed_pdo()->prepare('SELECT * FROM media_review_files WHERE submission_id=?');$s->execute(['${id}']);echo json_encode($s->fetchAll());`));
const before=state(),photo=before.profiles.find(p=>p.doctor_id==='1').photo_url;
const html=async()=>await (await fetch(base+'/profiles/doctor.php?doctor_id=1&mxmed_plan=standard')).text();
assert.ok((await html()).includes(photo));
const pub=await fetch(base+photo);assert.equal(pub.status,200);const publicBytes=Buffer.from(await pub.arrayBuffer());
const ids=[];let token='',keep=false;
try{
 assert.equal((await call(null)).status,401);
 assert.equal((await call(null,'POST',form(),'', '',{'X-User-Id':'fake','X-Doctor-Id':'1'})).status,401);
 assert.equal((await call(sessions[2])).status,401);
 assert.equal((await call(sessions[3])).status,401);
 const first=await call(sessions[0]);assert.equal(first.status,200);assert.equal(first.body.data.candidate,null,'Refusing to replace existing candidate');token=first.body.data.csrf_token;
 assert.equal((await call(sessions[0],'POST',form())).status,403);
 assert.equal((await call(sessions[0],'DELETE',null,'bad')).status,403);
 let r=await call(sessions[0],'POST',form(),token,'?doctor_id=2',{'X-Doctor-Id':'2'});assert.equal(r.status,200,JSON.stringify(r));let candidate=r.body.data.candidate;ids.push(candidate.submission_id);
 assert.equal(candidate.technical_status,'READY');assert.equal(candidate.review_status,'PENDING_REVIEW');
 assert.ok(!JSON.stringify(candidate).includes('storage_key'));assert.ok(!JSON.stringify(candidate).includes('public_url'));
 const other=await call(sessions[1],'GET',null,'','?doctor_id=1&submission_id='+candidate.submission_id);assert.equal(other.body.data.candidate,null);
 assert.equal((await call(sessions[1],'DELETE',null,other.body.data.csrf_token,'?doctor_id=1&submission_id='+candidate.submission_id)).status,200);
 assert.equal((await call(sessions[0])).body.data.candidate.submission_id,candidate.submission_id);
 const allFiles=files(candidate.submission_id);
 for(const f of allFiles){
  assert.ok(!('public_url' in f));
  for(const key of [f.storage_key,encodeURIComponent(f.storage_key),f.file_id])assert.equal((await fetch(base+'/api/media/index.php/public/'+key)).status,404);
 }
 assert.equal((await fetch(base+'/api/media/index.php/public/'+candidate.submission_id)).status,404);
 for(const f of [form(bytes,'image/png','synthetic.jpeg'),form(bytes,'image/jpeg','bad.svg'),form(new Uint8Array(10485761)),form(bytes.subarray(0,70))]){
  const bad=await call(sessions[0],'POST',f,token);assert.equal(bad.status,422,JSON.stringify(bad));assert.equal((await call(sessions[0])).body.data.candidate.submission_id,candidate.submission_id);
 }
 r=await call(sessions[0],'POST',form(),token);assert.equal(r.status,200);const old=candidate;candidate=r.body.data.candidate;ids.push(candidate.submission_id);assert.notEqual(candidate.submission_id,old.submission_id);
 assert.deepEqual(state(),before);
 assert.deepEqual(Buffer.from(await (await fetch(base+photo)).arrayBuffer()),publicBytes);
 const page=await html();assert.ok(page.includes(photo));assert.ok(!page.includes(candidate.submission_id));assert.ok(!page.includes('private/media-review/'));
 const immediate=await fetch(base+'/api/media/profile-photo.php',{headers:{Cookie:'PHPSESSID='+sessions[0]}});assert.equal((await immediate.json()).data.photo.public_url,photo);
 assert.equal((await call(sessions[0],'DELETE',null,token)).status,200);assert.equal((await call(sessions[0])).body.data.candidate,null);
 if(process.env.MR1_RETAIN_SYNTHETIC_CANDIDATE==='1'){
  r=await call(sessions[0],'POST',form(),token);assert.equal(r.status,200);ids.push(r.body.data.candidate.submission_id);keep=true;
  console.log('RETAINED_SYNTHETIC_CANDIDATE='+r.body.data.candidate.submission_id);
 }
 assert.deepEqual(state(),before);
 console.log('PASS: real multipart 12MP upload; anonymous/header/fixture/conflict/CSRF denial; forged owner ignored; cross-doctor read/delete isolation; public UUID/key/file denial; limits; replacement/withdrawal; public HTML/bytes/rows and immediate GET unchanged');
}finally{
 const current=token?(await call(sessions[0])).body.data.candidate:null;
 if(!keep&&current&&ids.includes(current.submission_id))await call(sessions[0],'DELETE',null,token);
 const remove=ids.filter(id=>!keep||id!==current?.submission_id);
 for(const id of remove)php(`require 'api/_lib/db.php';$s=mxmed_pdo()->prepare("DELETE FROM media_review_submissions WHERE submission_id=? AND review_status='WITHDRAWN'");$s->execute(['${id}']);`);
 for(const id of sessions)php(`session_id('${id}');session_start();session_destroy();`);
}
